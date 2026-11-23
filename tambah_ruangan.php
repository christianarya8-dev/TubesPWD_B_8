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

// Mengambil daftar gedung
$queryGedung = $koneksi->query(
    "SELECT id_gedung, nama_gedung FROM gedung ORDER BY nama_gedung"
);
$daftarGedung = $queryGedung->fetchAll(PDO::FETCH_ASSOC);

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

    // Simpan input sementara
    $gedungLama = $idGedung ? (int)$idGedung : 0;
    $kodeLama = $kode;
    $namaLama = $nama;
    $kapasitasLama = (string)($_POST["kapasitas"] ?? "");
    $deskripsiLama = $deskripsi;

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

            $pesan = "Ruangan berhasil ditambahkan!";
            $berhasil = true;

            // Kosongkan input setelah berhasil tersimpan
            $gedungLama = 0;
            $kodeLama = "";
            $namaLama = "";
            $kapasitasLama = "";
            $deskripsiLama = "";

        } catch (PDOException $e) {
            if ($e->getCode() === "23000") {
                $pesan = "Kode ruangan sudah digunakan atau gedung tidak valid.";
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
    "{{DESKRIPSI_LAMA}}" => aman($deskripsiLama)
]);

echo $html;