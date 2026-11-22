<?php
session_start();
require_once __DIR__ . "/config/database.php";

// Wajib login
if (!isset($_SESSION["id_pengguna"])) {
    header("Location: login.php");
    exit;
}

function aman($teks) {
    return htmlspecialchars((string)$teks, ENT_QUOTES, "UTF-8");
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$id = (int)$_SESSION["id_pengguna"];
$pesan = "";
$berhasil = false;

// Ambil data pengguna dari MySQL
$stmt = $koneksi->prepare(
    "SELECT id_pengguna, nama_lengkap, email,
            peran, nomor_identitas, no_telepon
     FROM pengguna
     WHERE id_pengguna = ?"
);
$stmt->execute([$id]);
$pengguna = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pengguna) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Ketika form disimpan
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token)) {
        http_response_code(403);
        exit("Token tidak valid.");
    }

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
        http_response_code(403);
        exit("Token tidak valid.");
    }

    $nama = trim($_POST["nama_lengkap"] ?? "");
    $identitas = trim($_POST["nomor_identitas"] ?? "");
    $telepon = trim($_POST["no_telepon"] ?? "");

    if ($nama === "") {
        $pesan = "Nama lengkap wajib diisi.";
    } elseif (strlen($nama) > 100) {
        $pesan = "Nama lengkap maksimal 100 karakter.";
    } elseif (strlen($identitas) > 30) {
        $pesan = "Nomor identitas maksimal 30 karakter.";
    } elseif (strlen($telepon) > 20) {
        $pesan = "Nomor telepon maksimal 20 karakter.";
    } else {
        $update = $koneksi->prepare(
            "UPDATE pengguna
             SET nama_lengkap = ?,
                 nomor_identitas = ?,
                 no_telepon = ?
             WHERE id_pengguna = ?"
        );

        $update->execute([
            $nama, $identitas, $telepon, $id
        ]);

        $_SESSION["nama_lengkap"] = $nama;
        $berhasil = true;
        $pesan = "Profil berhasil diperbarui!";

        // Ambil data terbaru
        $stmt->execute([$id]);
        $pengguna = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Muat template HTML
$html = file_get_contents(__DIR__ . "/profil.xhtml");

if ($html === false) {
    http_response_code(500);
    exit("Template profil tidak ditemukan.");
}

// Tampilkan atau hapus blok pesan notifikasi
if ($pesan !== "") {
    $html = str_replace(["<!-- PESAN_START -->", "<!-- PESAN_END -->"], "", $html);
} else {
    $html = preg_replace('/<!-- PESAN_START -->.*?<!-- PESAN_END -->/s', "", $html);
}

// Isi placeholder dengan data pengguna yang sudah diamankan
$html = strtr($html, [
    "{{PESAN}}" => aman($pesan),
    "{{KELAS_PESAN}}" => $berhasil ? "pesan" : "error",
    "{{EMAIL}}" => aman($pengguna["email"]),
    "{{PERAN}}" => aman($pengguna["peran"]),
    "{{CSRF_TOKEN}}" => aman($_SESSION["csrf_token"]),
    "{{NAMA_LENGKAP}}" => aman($pengguna["nama_lengkap"]),
    "{{NOMOR_IDENTITAS}}" => aman($pengguna["nomor_identitas"]),
    "{{NO_TELEPON}}" => aman($pengguna["no_telepon"])
]);

echo $html;