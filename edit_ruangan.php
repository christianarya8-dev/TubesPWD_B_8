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

    // Simpan input pengguna agar tidak hilang jika terjadi error
    $kodeForm = $kode;
    $namaForm = $nama;
    $gedungForm = $gedung ? (int)$gedung : 0;
    $kapasitasForm = (string)($_POST["kapasitas"] ?? "");
    $deskripsiForm = $deskripsi;

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

            header("Location: daftar_ruangan.php");
            exit;

        } catch (PDOException $e) {
            if ($e->getCode() === "23000") {
                $pesan = "Kode ruangan sudah digunakan atau gedung tidak valid.";
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
    "{{DESKRIPSI}}" => aman($deskripsiForm)
]);

echo $html;