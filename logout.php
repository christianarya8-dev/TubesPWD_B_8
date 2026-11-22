<?php
session_start();

function aman($teks) {
    return htmlspecialchars((string)$teks, ENT_QUOTES, "UTF-8");
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$sudahLogout = false;
$namaPengguna = aman($_SESSION["nama_lengkap"] ?? "Pengguna");
$csrfToken = aman($_SESSION["csrf_token"]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Jika sesi sudah habis sebelumnya, langsung anggap sudah logout
    if (!isset($_SESSION["id_pengguna"])) {
        $sudahLogout = true;
    } else {
        $token = $_POST["csrf_token"] ?? "";

        if (!is_string($token) || !hash_equals($_SESSION["csrf_token"], $token)) {
            http_response_code(403);
            exit("Permintaan tidak valid. Silakan kembali dan refresh halaman terlebih dahulu.");
        }

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                "",
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();
        $sudahLogout = true;
    }
} elseif (!isset($_SESSION["id_pengguna"])) {
    $sudahLogout = true;
}

// Muat template HTML
$html = file_get_contents(__DIR__ . "/logout.xhtml");

if ($html === false) {
    http_response_code(500);
    exit("Template logout tidak ditemukan.");
}

// Tampilkan bagian sesuai status sesi
if ($sudahLogout) {
    $html = preg_replace('/<!-- KONFIRMASI_START -->.*?<!-- KONFIRMASI_END -->/s', "", $html);
    $html = str_replace(["<!-- SELESAI_START -->", "<!-- SELESAI_END -->"], "", $html);
} else {
    $html = str_replace(["<!-- KONFIRMASI_START -->", "<!-- KONFIRMASI_END -->"], "", $html);
    $html = preg_replace('/<!-- SELESAI_START -->.*?<!-- SELESAI_END -->/s', "", $html);
}

$html = strtr($html, [
    "{{NAMA_PENGGUNA}}" => $namaPengguna,
    "{{CSRF_TOKEN}}" => $csrfToken
]);

echo $html;