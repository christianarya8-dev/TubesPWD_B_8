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
$tipePesan = "error";
$emailLama = "";
$peranDipilih = "mahasiswa";

if (isset($_GET["daftar"]) && $_GET["daftar"] === "berhasil") {
    $pesan = "Registrasi berhasil! Silakan login.";
    $tipePesan = "sukses";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) || !hash_equals($_SESSION["csrf_token"], $token)) {
        http_response_code(403);
        exit("Permintaan tidak valid.");
    }

    $peranDipilih = $_POST["peran"] ?? "mahasiswa";
    $email = strtolower(trim($_POST["email"] ?? ""));
    $password = $_POST["kata_sandi"] ?? "";
    $emailLama = $email;

    if (!in_array($peranDipilih, ["mahasiswa", "admin"], true)) {
        $pesan = "Pilihan peran tidak valid.";
        $peranDipilih = "mahasiswa";
    } elseif ($email === "" || $password === "") {
        $pesan = "Email dan password wajib diisi.";
    } else {
        $stmt = $koneksi->prepare(
            "SELECT id_pengguna, nama_lengkap,
                    email, kata_sandi, peran
             FROM pengguna
             WHERE email = ?
             LIMIT 1"
        );

        $stmt->execute([$email]);
        $pengguna = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pengguna || !password_verify($password, $pengguna["kata_sandi"])) {
            $pesan = "Email atau password salah.";
        } elseif ($peranDipilih === "admin" && $pengguna["peran"] !== "admin") {
            $pesan = "Akun ini bukan akun Admin. Silakan pilih peran Mahasiswa.";
        } elseif ($peranDipilih === "mahasiswa" && $pengguna["peran"] !== "mahasiswa") {
            $pesan = "Akun ini terdaftar sebagai " . ucfirst($pengguna["peran"]) . ". Silakan pilih peran yang sesuai.";
        } else {
            session_regenerate_id(true);

            $_SESSION["id_pengguna"] = (int)$pengguna["id_pengguna"];
            $_SESSION["nama_lengkap"] = $pengguna["nama_lengkap"];
            $_SESSION["peran"] = $pengguna["peran"];
            $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

            header("Location: dashboard.php");
            exit;
        }
    }
}

// Muat template XHTML
$html = file_get_contents(__DIR__ . "/login.xhtml");

if ($html === false) {
    http_response_code(500);
    exit("Template login tidak ditemukan.");
}

// Tampilkan atau hapus blok pesan notifikasi
if ($pesan !== "") {
    $html = str_replace(["<!-- PESAN_START -->", "<!-- PESAN_END -->"], "", $html);
} else {
    $html = preg_replace('/<!-- PESAN_START -->.*?<!-- PESAN_END -->/s', "", $html);
}

// Isi placeholder ke template XHTML
$html = strtr($html, [
    "{{PESAN}}" => aman($pesan),
    "{{KELAS_PESAN}}" => $tipePesan === "sukses" ? "sukses" : "error",
    "{{CSRF_TOKEN}}" => aman($_SESSION["csrf_token"]),
    "{{EMAIL_LAMA}}" => aman($emailLama),
    "{{CHECKED_MAHASISWA}}" => $peranDipilih === "mahasiswa" ? 'checked="checked"' : "",
    "{{CHECKED_ADMIN}}" => $peranDipilih === "admin" ? 'checked="checked"' : ""
]);

echo $html;