<?php
session_start();

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
require_once __DIR__ . "/config/database.php";

// Pengguna harus login
if (!isset($_SESSION["id_pengguna"])) {
    header("Location: login.php");
    exit;
}

function aman($nilai) {
    return htmlspecialchars((string)$nilai, ENT_QUOTES, "UTF-8");
}

// Ambil hanya peminjaman milik pengguna yang login
$sql = "SELECT
            p.id_peminjaman,
            r.nama_ruangan,
            g.nama_gedung,
            p.tanggal,
            p.jam_mulai,
            p.jam_selesai,
            p.keperluan,
            p.jumlah_peserta,
            p.status_peminjaman,
            p.catatan_admin
        FROM peminjaman p
        INNER JOIN ruangan r
            ON p.id_ruangan = r.id_ruangan
        INNER JOIN gedung g
            ON r.id_gedung = g.id_gedung
        WHERE p.id_pengguna = :id_pengguna
        ORDER BY p.tanggal DESC, p.jam_mulai DESC";

$stmt = $koneksi->prepare($sql);
$stmt->execute([
    ":id_pengguna" => (int)$_SESSION["id_pengguna"]
]);

$riwayat = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>Riwayat Peminjaman - CampusSpace</title>
    <meta charset="UTF-8" />
    <style type="text/css">
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            padding: 30px;
        }

        h1 {
            color: #243b53;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #243b53;
            color: white;
        }

        .pending { color: #a16207; font-weight: bold; }
        .approved { color: #15803d; font-weight: bold; }
        .rejected { color: #b91c1c; font-weight: bold; }
        .cancelled { color: #64748b; font-weight: bold; }

        a {
            display: inline-block;
            margin: 15px 10px 15px 0;
        }
    </style>
</head>

<body>
    <h1>Riwayat Peminjaman Ruangan</h1>

    <a href="dashboard.php">Kembali ke Dashboard</a>
    <a href="pinjam_ruangan.php">Ajukan Peminjaman Baru</a>

    <table>
        <thead>
            <tr>
                <th>Ruangan</th>
                <th>Gedung</th>
                <th>Tanggal</th>
                <th>Jam</th>
                <th>Keperluan</th>
                <th>Peserta</th>
                <th>Status</th>
                <th>Catatan Admin</th>
                <th>Aksi</th>
            </tr>
        </thead>

        <tbody>
        <?php if (empty($riwayat)): ?>
            <tr>
                <td colspan="9">
                    Belum ada riwayat peminjaman.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($riwayat as $p): ?>
                <tr>
                    <td><?= aman($p["nama_ruangan"]) ?></td>
                    <td><?= aman($p["nama_gedung"]) ?></td>
                    <td><?= aman($p["tanggal"]) ?></td>
                    <td>
                        <?= aman(substr($p["jam_mulai"], 0, 5)) ?>
                        -
                        <?= aman(substr($p["jam_selesai"], 0, 5)) ?>
                    </td>
                    <td><?= aman($p["keperluan"]) ?></td>
                    <td><?= (int)$p["jumlah_peserta"] ?></td>
                    <td>
                        <span class="<?= aman($p["status_peminjaman"]) ?>">
                            <?= aman(ucfirst($p["status_peminjaman"])) ?>
                        </span>
                    </td>
                    <td><?= aman($p["catatan_admin"] ?? "-") ?></td>        
                    <td>
                        <?php if ($p["status_peminjaman"] === "pending"): ?>

                        <a href="edit_peminjaman.php?id=<?= (int)$p["id_peminjaman"] ?>">
                            Edit
                        </a>
                            <form method="post"
                                action="batalkan_peminjaman.php"
                                onsubmit="return confirm('Yakin ingin membatalkan peminjaman ini?');">

                                <input type="hidden"
                                    name="csrf_token"
                                    value="<?= aman($_SESSION["csrf_token"]) ?>" />

                                <input type="hidden"
                                    name="id_peminjaman"
                                    value="<?= (int)$p["id_peminjaman"] ?>" />

                                <button type="submit">Batalkan</button>
                            </form>

                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
<script src="js/konfirmasi.js"></script>
</body>
</html>
