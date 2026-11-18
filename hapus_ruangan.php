<?php
require_once __DIR__ . "/config/admin_guard.php";

// 1. Memastikan permintaan menggunakan POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Metode tidak diizinkan.");
}

// 2. Memeriksa token CSRF
$token = $_POST["csrf_token"] ?? "";

if (!is_string($token) ||
    !hash_equals($_SESSION["csrf_token"], $token)) {
    http_response_code(403);
    exit("Permintaan tidak valid.");
}

// 3. Mengambil ID ruangan
$id = filter_input(
    INPUT_POST,
    "id",
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    exit("ID ruangan tidak valid.");
}

// 4. Menonaktifkan ruangan (soft delete)
$stmt = $koneksi->prepare(
    "UPDATE ruangan
     SET status_ruangan = 'nonaktif'
     WHERE id_ruangan = ?"
);

$stmt->execute([$id]);

// 5. Kembali ke daftar ruangan
header("Location: daftar_ruangan.php");
exit;
?>
