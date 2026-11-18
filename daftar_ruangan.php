<?php
session_start();

require_once __DIR__ . "/config/database.php";

// Fungsi untuk mengamankan tampilan teks
function aman($nilai) {
    return htmlspecialchars((string)$nilai, ENT_QUOTES, "UTF-8");
}

// Periksa apakah pengguna adalah admin
$admin = false;

if (isset($_SESSION["id_pengguna"])) {
    $cekAdmin = $koneksi->prepare(
        "SELECT peran
         FROM pengguna
         WHERE id_pengguna = ?"
    );

    $cekAdmin->execute([
        (int)$_SESSION["id_pengguna"]
    ]);

    $admin = ($cekAdmin->fetchColumn() === "admin");
}

// Siapkan token CSRF untuk admin
if ($admin && empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Mengambil daftar ruangan aktif beserta gedung
$sql = "SELECT
            r.id_ruangan,
            r.kode_ruangan,
            r.nama_ruangan,
            r.kapasitas,
            g.nama_gedung
        FROM ruangan r
        INNER JOIN gedung g
            ON r.id_gedung = g.id_gedung
        WHERE r.status_ruangan = 'aktif'
        ORDER BY r.id_ruangan";

$query = $koneksi->query($sql);
$data_ruangan = $query->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>Daftar Ruangan - CampusSpace</title>
    <meta charset="UTF-8" />

    <style type="text/css">
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 30px;
        }

        h1 {
            color: #243b53;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
        }

        th, td {
            padding: 12px;
            border: 1px solid #cccccc;
            text-align: left;
        }

        th {
            background-color: #243b53;
            color: white;
        }

        a {
            color: #2563eb;
            margin-right: 10px;
        }

        button {
            background: #b91c1c;
            color: white;
            border: none;
            padding: 8px 12px;
            cursor: pointer;
        }

        .navigasi {
            margin-bottom: 20px;
        }
    </style>
</head>

<body>

    <h1>Daftar Ruangan CampusSpace</h1>

    <p>Data berikut diambil langsung dari database MySQL.</p>

    <div class="navigasi">
        <a href="dashboard.php">Kembali ke Dashboard</a>

        <?php if ($admin): ?>
            <a href="tambah_ruangan.php">
                + Tambah Ruangan
            </a>
        <?php endif; ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>Kode</th>
                <th>Nama Ruangan</th>
                <th>Gedung</th>
                <th>Kapasitas</th>

                <?php if ($admin): ?>
                    <th>Aksi</th>
                <?php endif; ?>
            </tr>
        </thead>

        <tbody>
            <?php if (empty($data_ruangan)): ?>
                <tr>
                    <td colspan="<?= $admin ? 5 : 4 ?>">
                        Belum ada ruangan tersedia.
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($data_ruangan as $ruangan): ?>
                <tr>
                    <td>
                        <?= aman($ruangan["kode_ruangan"]) ?>
                    </td>

                    <td>
                        <?= aman($ruangan["nama_ruangan"]) ?>
                    </td>

                    <td>
                        <?= aman($ruangan["nama_gedung"]) ?>
                    </td>

                    <td>
                        <?= (int)$ruangan["kapasitas"] ?> orang
                    </td>

                    <?php if ($admin): ?>
                    <td>
                        <a href="edit_ruangan.php?id=<?= (int)$ruangan['id_ruangan'] ?>">
                            Edit
                        </a>

                        <form method="post"
                              action="hapus_ruangan.php"
                              style="display:inline;"
                              onsubmit="return confirm('Yakin ingin menonaktifkan ruangan ini?');">

                            <input type="hidden"
                                   name="csrf_token"
                                   value="<?= aman($_SESSION["csrf_token"]) ?>" />

                            <input type="hidden"
                                   name="id"
                                   value="<?= (int)$ruangan['id_ruangan'] ?>" />

                            <button type="submit">
                                Hapus
                            </button>
                        </form>
                    </td>
                    <?php endif; ?>

                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

<script src="js/ruangan.js"></script>
</body>
</html>
