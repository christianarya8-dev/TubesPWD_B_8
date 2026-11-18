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

$nama = aman($_SESSION["nama_lengkap"]);
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>Dashboard - CampusSpace</title>
    <meta charset="UTF-8" />
    <style type="text/css">
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            padding: 40px;
        }
        .kotak {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 650px;
        }
        a { margin-right: 15px; }
        button {
            margin-top: 20px;
            padding: 10px 15px;
            cursor: pointer;
        }
    </style>
</head>
<body>
<div class="kotak">
    <h1>Dashboard CampusSpace</h1>

    <h2>Selamat datang, <?= $nama ?>!</h2>

    <p>Kamu berhasil login ke sistem
       peminjaman ruangan kampus.</p>

    <p>
        <a href="daftar_ruangan.php">Lihat Ruangan</a>
        <a href="profil.php">Profil Saya</a>
        <a href="pinjam_ruangan.php">Pinjam Ruangan</a>
        <a href="riwayat_peminjaman.php">Riwayat Peminjaman</a>

        <?php if (($_SESSION["peran"] ?? "") === "admin"): ?>
            <a href="admin_peminjaman.php">
                Kelola Peminjaman (Admin)
            </a>
        <?php endif; ?>

    </p>

    <form method="post" action="logout.php">
        <input type="hidden"
               name="csrf_token"
               value="<?= aman($_SESSION["csrf_token"]) ?>" />

        <button type="submit">Logout</button>
    </form>
</div>
</body>
</html>
