<?php
session_start();

require_once __DIR__ . "/config/database.php";

function aman($nilai) {
    return htmlspecialchars((string)$nilai, ENT_QUOTES, "UTF-8");
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$pesan = "";
$nama = "";
$email = "";

// Proses ketika tombol Daftar diklik
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)) {
        http_response_code(403);
        exit("Permintaan tidak valid.");
    }

    $nama = trim($_POST["nama_lengkap"] ?? "");
    $email = strtolower(trim($_POST["email"] ?? ""));
    $password = $_POST["kata_sandi"] ?? "";
    $konfirmasi = $_POST["konfirmasi_sandi"] ?? "";

    if ($nama === "" || $email === "" ||
        $password === "" || $konfirmasi === "") {

        $pesan = "Semua kolom wajib diisi.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $pesan = "Format email tidak valid.";

    } elseif (strlen($password) < 8) {

        $pesan = "Password minimal 8 karakter.";

    } elseif ($password !== $konfirmasi) {

        $pesan = "Konfirmasi password tidak cocok.";

    } else {

        // Hash password, jangan simpan password asli
        $hash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        try {
            // Tentukan peran otomatis berdasarkan akhiran email
            $peranBaru = str_ends_with($email, "@admin.kampus.ac.id")
                ? "admin"
                : "mahasiswa";

            $sql = "INSERT INTO pengguna
                    (nama_lengkap, email, kata_sandi, peran)
                    VALUES
                    (:nama, :email, :sandi, :peran)";

            $stmt = $koneksi->prepare($sql);

            $stmt->execute([
                ":nama" => $nama,
                ":email" => $email,
                ":sandi" => $hash,
                ":peran" => $peranBaru
            ]);

            $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

            header("Location: login.php?daftar=berhasil");
            exit;

        } catch (PDOException $e) {

            if ($e->getCode() === "23000") {
                $pesan = "Email sudah terdaftar.";
            } else {
                error_log($e->getMessage());
                $pesan = "Registrasi gagal. Silakan coba lagi.";
            }
        }
    }
}

// Muat template HTML
$html = file_get_contents(__DIR__ . "/register.xhtml");

if ($html === false) {
    http_response_code(500);
    exit("Template registrasi tidak ditemukan.");
}

// Tampilkan atau hapus blok pesan error
if ($pesan !== "") {
    $html = str_replace(["<!-- PESAN_START -->", "<!-- PESAN_END -->"], "", $html);
} else {
    $html = preg_replace('/<!-- PESAN_START -->.*?<!-- PESAN_END -->/s', "", $html);
}

// Isi placeholder dengan data yang sudah diamankan
$html = strtr($html, [
    "{{PESAN}}" => aman($pesan),
    "{{CSRF_TOKEN}}" => aman($_SESSION["csrf_token"]),
    "{{NAMA_LAMA}}" => aman($nama),
    "{{EMAIL_LAMA}}" => aman($email)
]);

echo $html;