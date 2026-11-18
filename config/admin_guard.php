<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/database.php";

// Pastikan sudah login
if (!isset($_SESSION["id_pengguna"])) {
    header("Location: /campusspace/login.php");
    exit;
}

// Periksa peran langsung dari database
$stmtAdmin = $koneksi->prepare(
    "SELECT peran FROM pengguna
     WHERE id_pengguna = ?"
);

$stmtAdmin->execute([
    (int)$_SESSION["id_pengguna"]
]);

$peranAktual = $stmtAdmin->fetchColumn();

if ($peranAktual !== "admin") {
    http_response_code(403);
    exit("Akses ditolak. Halaman khusus admin.");
}

// Siapkan token keamanan form
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
?>
