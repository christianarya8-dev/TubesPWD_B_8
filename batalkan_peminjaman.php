<?php
session_start();

require_once __DIR__ . "/config/database.php";

// Pastikan pengguna sudah login
if (!isset($_SESSION["id_pengguna"])) {
    header("Location: login.php");
    exit;
}

// Pembatalan hanya menerima metode POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Metode tidak diizinkan.");
}

// Validasi keamanan formulir
$token = $_POST["csrf_token"] ?? "";

if (!is_string($token) ||
    empty($_SESSION["csrf_token"]) ||
    !hash_equals($_SESSION["csrf_token"], $token)) {
    http_response_code(403);
    exit("Permintaan tidak valid.");
}

// Ambil ID peminjaman
$idPeminjaman = filter_input(
    INPUT_POST,
    "id_peminjaman",
    FILTER_VALIDATE_INT
);

$idPengguna = (int)$_SESSION["id_pengguna"];

if (!$idPeminjaman || $idPeminjaman < 1) {
    exit("ID peminjaman tidak valid.");
}

// Ubah status peminjaman milik pengguna sendiri
// dan hanya jika masih pending
$sql = "UPDATE peminjaman
        SET status_peminjaman = 'cancelled'
        WHERE id_peminjaman = ?
          AND id_pengguna = ?
          AND status_peminjaman = 'pending'";

$stmt = $koneksi->prepare($sql);
$stmt->execute([
    $idPeminjaman,
    $idPengguna
]);

header("Location: riwayat_peminjaman.php");
exit;
?>
