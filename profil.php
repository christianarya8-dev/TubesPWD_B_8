<?php
session_start();
require_once __DIR__ . "/config/database.php";

// Wajib login
if (!isset($_SESSION["id_pengguna"])) {
    header("Location: login.php");
    exit;
}

function aman($teks) {
    return htmlspecialchars((string)$teks, ENT_QUOTES, "UTF-8");
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$id = (int)$_SESSION["id_pengguna"];
$pesan = "";
$berhasil = false;

// Ambil data pengguna dari MySQL
$stmt = $koneksi->prepare(
    "SELECT id_pengguna, nama_lengkap, email,
            peran, nomor_identitas, no_telepon
     FROM pengguna
     WHERE id_pengguna = ?"
);
$stmt->execute([$id]);
$pengguna = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pengguna) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Ketika form disimpan
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)) {
        http_response_code(403);
        exit("Token tidak valid.");
    }

    $nama = trim($_POST["nama_lengkap"] ?? "");
    $identitas = trim($_POST["nomor_identitas"] ?? "");
    $telepon = trim($_POST["no_telepon"] ?? "");

    if ($nama === "" || strlen($nama) > 100 ||
        strlen($identitas) > 30 ||
        strlen($telepon) > 20) {
        $pesan = "Periksa kembali data yang diisi.";
    } else {
        $update = $koneksi->prepare(
            "UPDATE pengguna
             SET nama_lengkap = ?,
                 nomor_identitas = ?,
                 no_telepon = ?
             WHERE id_pengguna = ?"
        );

        $update->execute([
            $nama, $identitas, $telepon, $id
        ]);

        $_SESSION["nama_lengkap"] = $nama;
        $berhasil = true;
        $pesan = "Profil berhasil diperbarui!";

        // Ambil data terbaru
        $stmt->execute([$id]);
        $pengguna = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>Profil Pengguna - CampusSpace</title>
    <meta charset="UTF-8" />
    <style type="text/css">
        body {
            background: #f4f6f9;
            font-family: Arial, sans-serif;
            padding: 40px 15px;
        }
        .kotak {
            max-width: 500px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
        }
        h1 { color: #243b53; }
        label {
            display: block;
            font-weight: bold;
            margin-top: 15px;
        }
        input {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            box-sizing: border-box;
        }
        button {
            background: #243b53;
            color: white;
            border: none;
            padding: 12px 18px;
            margin-top: 20px;
            cursor: pointer;
        }
        .pesan { color: #167347; }
        .error { color: #b42318; }
    </style>
</head>
<body>
<div class="kotak">
    <h1>Profil Pengguna</h1>

    <?php if ($pesan !== ""): ?>
        <p class="<?= $berhasil ? 'pesan' : 'error' ?>">
            <?= aman($pesan) ?>
        </p>
    <?php endif; ?>

    <p><strong>Email:</strong>
        <?= aman($pengguna["email"]) ?>
    </p>

    <p><strong>Peran:</strong>
        <?= aman($pengguna["peran"]) ?>
    </p>

    <form method="post" action="profil.php">
        <input type="hidden"
               name="csrf_token"
               value="<?= aman($_SESSION["csrf_token"]) ?>" />

        <label for="nama">Nama Lengkap</label>
        <input type="text" id="nama"
               name="nama_lengkap"
               maxlength="100"
               value="<?= aman($pengguna["nama_lengkap"]) ?>"
               required="required" />

        <label for="identitas">Nomor Identitas / NPM</label>
        <input type="text" id="identitas"
               name="nomor_identitas"
               maxlength="30"
               value="<?= aman($pengguna["nomor_identitas"]) ?>" />

        <label for="telepon">Nomor Telepon</label>
        <input type="text" id="telepon"
               name="no_telepon"
               maxlength="20"
               value="<?= aman($pengguna["no_telepon"]) ?>" />

        <button type="submit">Simpan Perubahan</button>
    </form>

    <p><a href="dashboard.php">Kembali ke Dashboard</a></p>
</div>
<script src="js/auth.js"></script>
</body>
</html>
