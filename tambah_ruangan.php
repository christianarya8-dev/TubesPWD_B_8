<?php
require_once __DIR__ . "/config/admin_guard.php";

function aman($teks) {
    return htmlspecialchars((string)$teks, ENT_QUOTES, "UTF-8");
}

$pesan = "";
$berhasil = false;

// Nilai awal form agar tidak hilang jika submit gagal
$gedungLama = 0;
$kodeLama = "";
$namaLama = "";
$kapasitasLama = "";
$deskripsiLama = "";
$fasilitasLama = [];

// Mengambil daftar gedung
$queryGedung = $koneksi->query(
    "SELECT id_gedung, nama_gedung FROM gedung ORDER BY nama_gedung"
);
$daftarGedung = $queryGedung->fetchAll(PDO::FETCH_ASSOC);

// Mengambil daftar fasilitas
$queryFasilitas = $koneksi->query(
    "SELECT id_fasilitas, nama_fasilitas FROM fasilitas ORDER BY nama_fasilitas"
);
$daftarFasilitas = $queryFasilitas->fetchAll(PDO::FETCH_ASSOC);

// Memproses form
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

    $idGedung = filter_input(INPUT_POST, "id_gedung", FILTER_VALIDATE_INT);
    $kode = trim($_POST["kode_ruangan"] ?? "");
    $nama = trim($_POST["nama_ruangan"] ?? "");
    $kapasitas = filter_input(INPUT_POST, "kapasitas", FILTER_VALIDATE_INT);
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

    // Simpan input sementara
    $gedungLama = $idGedung ? (int)$idGedung : 0;
    $kodeLama = $kode;
    $namaLama = $nama;
    $kapasitasLama = (string)($_POST["kapasitas"] ?? "");
    $deskripsiLama = $deskripsi;
    $fasilitasLama = $fasilitasDipilih;

    if (!$idGedung) {
        $pesan = "Gedung wajib dipilih.";
    } elseif ($kode === "") {
        $pesan = "Kode ruangan wajib diisi.";
    } elseif ($nama === "") {
        $pesan = "Nama ruangan wajib diisi.";
    } elseif (!$kapasitas) {
        $pesan = "Kapasitas ruangan tidak valid.";
    } elseif ($kapasitas < 1) {
        $pesan = "Kapasitas ruangan minimal 1 orang.";
    } else {
        try {
            $koneksi->beginTransaction();

            // 1. Simpan data utama ruangan
            $sql = "INSERT INTO ruangan
                    (id_gedung, kode_ruangan, nama_ruangan,
                     kapasitas, deskripsi)
                    VALUES (:gedung, :kode, :nama, :kapasitas, :deskripsi)";

            $stmt = $koneksi->prepare($sql);
            $stmt->execute([
                ":gedung" => $idGedung,
                ":kode" => $kode,
                ":nama" => $nama,
                ":kapasitas" => $kapasitas,
                ":deskripsi" => $deskripsi
            ]);

            $idRuanganBaru = (int)$koneksi->lastInsertId();

            // 2. Simpan relasi fasilitas yang dipilih (jika ada)
            if (!empty($fasilitasDipilih)) {
                $sqlFasilitas = "INSERT INTO fasilitas_ruangan (id_ruangan, id_fasilitas)
                                 VALUES (:ruangan, :fasilitas)";
                $stmtFasilitas = $koneksi->prepare($sqlFasilitas);

                foreach ($fasilitasDipilih as $idFasItem) {
                    $stmtFasilitas->execute([
                        ":ruangan" => $idRuanganBaru,
                        ":fasilitas" => $idFasItem
                    ]);
                }
            }

            $koneksi->commit();

            $pesan = "Ruangan berhasil ditambahkan!";
            $berhasil = true;

            // Kosongkan input setelah berhasil tersimpan
            $gedungLama = 0;
            $kodeLama = "";
            $namaLama = "";
            $kapasitasLama = "";
            $deskripsiLama = "";
            $fasilitasLama = [];

        } catch (PDOException $e) {
            if ($koneksi->inTransaction()) {
                $koneksi->rollBack();
            }

            if ($e->getCode() === "23000") {
                $pesan = "Kode ruangan sudah digunakan atau gedung/fasilitas tidak valid.";
            } else {
                error_log($e->getMessage());
                $pesan = "Terjadi kesalahan saat menyimpan data.";
            }
        }
    }
}

// Susun daftar opsi dropdown gedung
$opsiGedungHtml = "";
foreach ($daftarGedung as $gedung) {
    $idGedungItem = (int)$gedung["id_gedung"];
    $namaGedungItem = aman($gedung["nama_gedung"]);
    $selectedAttr = ($idGedungItem === $gedungLama) ? ' selected="selected"' : "";

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
        $checkedAttr = in_array($idFasItem, $fasilitasLama, true) ? ' checked="checked"' : "";

        $daftarFasilitasHtml .= '<label class="item-checkbox">'
            . '<input type="checkbox" name="fasilitas[]" value="' . $idFasItem . '"' . $checkedAttr . ' /> '
            . $namaFasItem
            . "</label>\n";
    }
}

// Muat template HTML
$html = file_get_contents(__DIR__ . "/tambah_ruangan.xhtml");

if ($html === false) {
    http_response_code(500);
    exit("Template tambah ruangan tidak ditemukan.");
}

// Tampilkan atau hapus blok pesan notifikasi
if ($pesan !== "") {
    $html = str_replace(["<!-- PESAN_START -->", "<!-- PESAN_END -->"], "", $html);
} else {
    $html = preg_replace('/<!-- PESAN_START -->.*?<!-- PESAN_END -->/s', "", $html);
}

// Isi placeholder ke template HTML
$html = strtr($html, [
    "{{PESAN}}" => aman($pesan),
    "{{KELAS_PESAN}}" => $berhasil ? "sukses" : "error",
    "{{CSRF_TOKEN}}" => aman($_SESSION["csrf_token"]),
    "{{OPSI_GEDUNG}}" => $opsiGedungHtml,
    "{{KODE_LAMA}}" => aman($kodeLama),
    "{{NAMA_LAMA}}" => aman($namaLama),
    "{{KAPASITAS_LAMA}}" => aman($kapasitasLama),
    "{{DESKRIPSI_LAMA}}" => aman($deskripsiLama),
    "{{DAFTAR_FASILITAS}}" => $daftarFasilitasHtml
]);

echo $html;