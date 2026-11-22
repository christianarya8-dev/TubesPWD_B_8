<?php
session_start();

if (!isset($_SESSION["id_pengguna"])) {
    header("Location: login.php");
    exit;
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function aman($teks) {
    return htmlspecialchars((string)$teks, ENT_QUOTES, "UTF-8");
}

$nama = aman($_SESSION["nama_lengkap"] ?? "");
$csrfToken = aman($_SESSION["csrf_token"]);
$isAdmin = (($_SESSION["peran"] ?? "") === "admin");

// Muat template HTML
$html = file_get_contents(__DIR__ . "/dashboard.xhtml");

if ($html === false) {
    http_response_code(500);
    exit("Template dashboard tidak ditemukan.");
}

// Tampilkan atau hapus blok Menu Admin sesuai peran pengguna
if ($isAdmin) {
    $html = str_replace(["<!-- ADMIN_START -->", "<!-- ADMIN_END -->"], "", $html);
} else {
    $html = preg_replace('/<!-- ADMIN_START -->.*?<!-- ADMIN_END -->/s', "", $html);
}

// Isi placeholder dengan data pengguna yang sudah diamankan
$html = strtr($html, [
    "{{NAMA_PENGGUNA}}" => $nama,
    "{{CSRF_TOKEN}}" => $csrfToken
]);

echo $html;