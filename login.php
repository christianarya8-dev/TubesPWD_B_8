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

if (isset($_GET["daftar"]) &&
    $_GET["daftar"] === "berhasil") {
    $pesan = "Registrasi berhasil! Silakan login.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)) {
        http_response_code(403);
        exit("Permintaan tidak valid.");
    }

    $email = strtolower(trim($_POST["email"] ?? ""));
    $password = $_POST["kata_sandi"] ?? "";

    $stmt = $koneksi->prepare(
        "SELECT id_pengguna, nama_lengkap,
                email, kata_sandi, peran
         FROM pengguna
         WHERE email = ?
         LIMIT 1"
    );

    $stmt->execute([$email]);
    $pengguna = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pengguna &&
        password_verify($password, $pengguna["kata_sandi"])) {

        session_regenerate_id(true);

        $_SESSION["id_pengguna"] =
            (int)$pengguna["id_pengguna"];

        $_SESSION["nama_lengkap"] =
            $pengguna["nama_lengkap"];

        $_SESSION["peran"] = $pengguna["peran"];

        $_SESSION["csrf_token"] =
            bin2hex(random_bytes(32));

        header("Location: dashboard.php");
        exit;

    } else {
        $pesan = "Email atau password salah.";
    }
}
?>

<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>Login - CampusSpace</title>
    <meta charset="UTF-8" />

    <style type="text/css">
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            padding: 40px 15px;
        }

        .kotak {
            max-width: 420px;
            margin: auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
        }

        label {
            display: block;
            margin-top: 15px;
        }

        input {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            box-sizing: border-box;
        }

        button {
            margin-top: 20px;
            padding: 12px;
            width: 100%;
            color: white;
            background: #243b53;
            border: none;
            cursor: pointer;
        }
    </style>
</head>

<body>
<div class="kotak">
    <h1>Login CampusSpace</h1>

    <?php if ($pesan !== ""): ?>
        <p><?= aman($pesan) ?></p>
    <?php endif; ?>

    <form method="post" action="login.php">

        <input type="hidden"
               name="csrf_token"
               value="<?= aman($_SESSION["csrf_token"]) ?>" />

        <label for="email">Email</label>
        <input type="email"
               id="email"
               name="email"
               required="required" />

        <label for="sandi">Password</label>
        <input type="password"
               id="sandi"
               name="kata_sandi"
               required="required" />

        <button type="submit">Login</button>

    </form>

    <p>
        Belum punya akun?
        <a href="register.php">Daftar di sini</a>
    </p>
</div>
<script src="js/auth.js"></script>
</body>
</html>
