<?php
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode([
        "valid" => false,
        "tersedia" => false,
        "pesan" => "Metode permintaan tidak diizinkan."
    ]);
    exit;
}

require_once __DIR__ . "/config/database.php";

$email = strtolower(trim($_GET["email"] ?? ""));

if ($email === "") {
    echo json_encode([
        "valid" => false,
        "tersedia" => false,
        "pesan" => ""
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        "valid" => false,
        "tersedia" => false,
        "pesan" => "Format email belum valid."
    ]);
    exit;
}

try {
    $stmt = $koneksi->prepare(
        "SELECT COUNT(*) FROM pengguna WHERE LOWER(email) = ?"
    );
    $stmt->execute([$email]);
    $jumlah = (int)$stmt->fetchColumn();

    if ($jumlah > 0) {
        echo json_encode([
            "valid" => true,
            "tersedia" => false,
            "pesan" => "Email sudah dipakai. Silakan gunakan email lain."
        ]);
    } else {
        echo json_encode([
            "valid" => true,
            "tersedia" => true,
            "pesan" => "Email tersedia dan dapat digunakan."
        ]);
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode([
        "valid" => false,
        "tersedia" => false,
        "pesan" => "Gagal memeriksa ketersediaan email."
    ]);
}