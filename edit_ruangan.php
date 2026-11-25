<?php
require_once __DIR__ . "/config/admin_guard.php";

function aman($nilai) {
    return htmlspecialchars((string)$nilai, ENT_QUOTES, "UTF-8");
}

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id) {
    exit("ID ruangan tidak valid.");
}

if ($id < 1) {
    exit("ID ruangan tidak valid.");
}

$pesan = "";

// Mengambil data ruangan yang akan diedit
$stmt = $koneksi->prepare(
    "SELECT * FROM ruangan WHERE id_ruangan = ?"
);
$stmt->execute([$id]);
$ruangan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ruangan) {
    exit("Ruangan tidak ditemukan.");
}

// Mengambil daftar gedung untuk pilihan dropdown
$daftarGedung = $koneksi->query(
    "SELECT id_gedung, nama_gedung
     FROM gedung ORDER BY nama_gedung"
)->fetchAll(PDO::FETCH_ASSOC);

// Mengambil semua master fasilitas
$daftarFasilitas = $koneksi->query(
    "SELECT id_fasilitas, nama_fasilitas
     FROM fasilitas ORDER BY nama_fasilitas"
)->fetchAll(PDO::FETCH_ASSOC);

// Mengambil daftar ID fasilitas yang sudah dimiliki ruangan ini
$stmtFasilitasLama = $koneksi->prepare(
    "SELECT id_fasilitas FROM fasilitas_ruangan WHERE id_ruangan = ?"
);
$stmtFasilitasLama->execute([$id]);
$fasilitasForm = array_map("intval", $stmtFasilitasLama->fetchAll(PDO::FETCH_COLUMN));

// Nilai awal form diambil dari database
$kodeForm = $ruangan["kode_ruangan"];
$namaForm = $ruangan["nama_ruangan"];
$gedungForm = (int)$ruangan["id_gedung"];
$kapasitasForm = (string)(int)$ruangan["kapasitas"];
$deskripsiForm = (string)($ruangan["deskripsi"] ?? "");

// Memproses perubahan dari form
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token)) {
        http_response_code(403);
        exit("Permintaan tidak valid.");
    }

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
        http_response_code(403);
        exit("Permintaan tidak valid.");
    }

    $nama = trim($_POST["nama_ruangan"] ?? "");
    $kode = trim($_POST["kode_ruangan"] ?? "");
    $gedung = filter_input(
        INPUT_POST, "id_gedung", FILTER_VALIDATE_INT
    );
    $kapasitas = filter_input(
        INPUT_POST, "kapasitas", FILTER_VALIDATE_INT
    );
    $deskripsi = trim($_POST["deskripsi"] ?? "");

    // Ambil dan sanitasi ID fasilitas yang dicentang
    $fasilitasDipilih = [];
    if (isset($_POST["fasilitas"]) && is_array($_POST["fasilitas"])) {
        foreach ($_POST["fasilitas"] as $idFas) {
            $idFasValid = filter_var($idFas, FILTER_VALIDATE_INT);
            if ($idFasValid && $idFasValid > 0) {
                $fasilitasDipilih[] = $idFasValid;
            }
        }
        $fasilitasDipilih = array_values(array_unique($fasilitasDipilih));
    }

    // Simpan input pengguna agar tidak hilang jika terjadi error
    $kodeForm = $kode;
    $namaForm = $nama;
    $gedungForm = $gedung ? (int)$gedung : 0;
    $kapasitasForm = (string)($_POST["kapasitas"] ?? "");
    $deskripsiForm = $deskripsi;
    $fasilitasForm = $fasilitasDipilih;

    if ($kode === "") {
        $pesan = "Kode ruangan wajib diisi.";
    } elseif ($nama === "") {
        $pesan = "Nama ruangan wajib diisi.";
    } elseif (!$gedung) {
        $pesan = "Gedung wajib dipilih.";
    } elseif (!$kapasitas) {
        $pesan = "Kapasitas ruangan tidak valid.";
    } elseif ($kapasitas < 1) {
        $pesan = "Kapasitas ruangan minimal 1 orang.";
    } else {
        try {
            $koneksi->beginTransaction();

            // 1. Update data utama ruangan
            $sql = "UPDATE ruangan SET
                        nama_ruangan = :nama,
                        kode_ruangan = :kode,
                        id_gedung = :gedung,
                        kapasitas = :kapasitas,
                        deskripsi = :deskripsi
                    WHERE id_ruangan = :id";

            $query = $koneksi->prepare($sql);

            $query->execute([
                ":nama" => $nama,
                ":kode" => $kode,
                ":gedung" => $gedung,
                ":kapasitas" => $kapasitas,
                ":deskripsi" => $deskripsi,
                ":id" => $id
            ]);

            // 2. Hapus relasi fasilitas lama ruangan ini
            $hapusFasilitas = $koneksi->prepare(
                "DELETE FROM fasilitas_ruangan WHERE id_ruangan = :id"
            );
            $hapusFasilitas->execute([":id" => $id]);

            // 3. Simpan ulang relasi fasilitas yang dicentang
            if (!empty($fasilitasDipilih)) {
                $simpanFasilitas = $koneksi->prepare(
                    "INSERT INTO fasilitas_ruangan (id_ruangan, id_fasilitas)
                     VALUES (:ruangan, :fasilitas)"
                );

                foreach ($fasilitasDipilih as $idFasItem) {
                    $simpanFasilitas->execute([
                        ":ruangan" => $id,
                        ":fasilitas" => $idFasItem
                    ]);
                }
            }

            $koneksi->commit();

            header("Location: daftar_ruangan.php");
            exit;

        } catch (PDOException $e) {
            if ($koneksi->inTransaction()) {
                $koneksi->rollBack();
            }

            if ($e->getCode() === "23000") {
                $pesan = "Kode ruangan sudah digunakan atau gedung/fasilitas tidak valid.";
            } else {
                error_log($e->getMessage());
                $pesan = "Gagal mengubah data ruangan.";
            }
        }
    }
}

// Susun daftar opsi dropdown gedung
$opsiGedungHtml = "";
foreach ($daftarGedung as $item) {
    $idGedungItem = (int)$item["id_gedung"];
    $namaGedungItem = aman($item["nama_gedung"]);
    $selectedAttr = ($idGedungItem === $gedungForm) ? ' selected="selected"' : "";

    $opsiGedungHtml .= '<option value="' . $idGedungItem . '"' . $selectedAttr . '>'
        . $namaGedungItem
        . "</option>\n";
}

// Susun daftar checkbox fasilitas (valid XHTML)
$daftarFasilitasHtml = "";
if (empty($daftarFasilitas)) {
    $daftarFasilitasHtml = '<p class="teks-kosong">Belum ada data fasilitas tersedia.</p>';
} else {
    foreach ($daftarFasilitas as $fasilitas) {
        $idFasItem = (int)$fasilitas["id_fasilitas"];
        $namaFasItem = aman($fasilitas["nama_fasilitas"]);
        $checkedAttr = in_array($idFasItem, $fasilitasForm, true) ? ' checked="checked"' : "";

        $daftarFasilitasHtml .= '<label class="item-checkbox">'
            . '<input type="checkbox" name="fasilitas[]" value="' . $idFasItem . '"' . $checkedAttr . ' /> '
            . $namaFasItem
            . "</label>\n";
    }
}

// Muat template HTML
$html = file_get_contents(__DIR__ . "/edit_ruangan.xhtml");

if ($html === false) {
    http_response_code(500);
    exit("Template edit ruangan tidak ditemukan.");
}

// Tampilkan atau hapus blok pesan error
if ($pesan !== "") {
    $html = str_replace(["<!-- PESAN_START -->", "<!-- PESAN_END -->"], "", $html);
} else {
    $html = preg_replace('/<!-- PESAN_START -->.*?<!-- PESAN_END -->/s', "", $html);
}

// Isi placeholder ke template HTML
$html = strtr($html, [
    "{{ID_RUANGAN}}" => (string)(int)$id,
    "{{PESAN}}" => aman($pesan),
    "{{CSRF_TOKEN}}" => aman($_SESSION["csrf_token"]),
    "{{KODE_RUANGAN}}" => aman($kodeForm),
    "{{NAMA_RUANGAN}}" => aman($namaForm),
    "{{OPSI_GEDUNG}}" => $opsiGedungHtml,
    "{{KAPASITAS}}" => aman($kapasitasForm),
    "{{DESKRIPSI}}" => aman($deskripsiForm),
    "{{DAFTAR_FASILITAS}}" => $daftarFasilitasHtml
]);

echo $html;