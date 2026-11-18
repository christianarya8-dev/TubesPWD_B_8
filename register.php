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
            $sql = "INSERT INTO pengguna
                    (nama_lengkap, email, kata_sandi, peran)
                    VALUES
                    (:nama, :email, :sandi, 'mahasiswa')";

            $stmt = $koneksi->prepare($sql);

            $stmt->execute([
                ":nama" => $nama,
                ":email" => $email,
                ":sandi" => $hash
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
?>

<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>Registrasi - CampusSpace</title>
    <meta charset="UTF-8" />

    <style type="text/css">
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 40px 15px;
        }

        .kotak {
            max-width: 420px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
        }

        h1 {
            color: #243b53;
            text-align: center;
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 11px;
            margin-top: 6px;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            padding: 12px;
            margin-top: 22px;
            background: #243b53;
            color: white;
            border: none;
            cursor: pointer;
        }

        .pesan {
            color: #b42318;
        }

        .bawah {
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>

<body>
<div class="kotak">

    <h1>CampusSpace</h1>
    <h2>Daftar Akun Mahasiswa</h2>

    <?php if ($pesan !== ""): ?>
        <p class="pesan"><?= aman($pesan) ?></p>
    <?php endif; ?>

    <form action="register.php" method="post">

        <input type="hidden"
               name="csrf_token"
               value="<?= aman($_SESSION["csrf_token"]) ?>" />

        <label for="nama">Nama Lengkap</label>
        <input type="text"
               id="nama"
               name="nama_lengkap"
               maxlength="100"
               value="<?= aman($nama) ?>"
               required="required" />

        <label for="email">Email</label>
        <input type="email"
               id="email"
               name="email"
               maxlength="150"
               value="<?= aman($email) ?>"
               required="required" />

        <label for="sandi">Password</label>
        <input type="password"
               id="sandi"
               name="kata_sandi"
               minlength="8"
               required="required" />

        <label for="konfirmasi">Konfirmasi Password</label>
        <input type="password"
               id="konfirmasi"
               name="konfirmasi_sandi"
               minlength="8"
               required="required" />

        <button type="submit">Daftar Sekarang</button>
    </form>

    <p class="bawah">
        Sudah punya akun?
        <a href="login.php">Login</a>
    </p>

</div>
</body>
</html>
