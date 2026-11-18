<?php
session_start();
require_once __DIR__ . "/config/database.php";

// Hanya admin yang boleh masuk
if (!isset($_SESSION["id_pengguna"])) {
    header("Location: login.php");
    exit;
}

if (($_SESSION["peran"] ?? "") !== "admin") {
    http_response_code(403);
    exit("Akses ditolak. Halaman khusus admin.");
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function aman($nilai) {
    return htmlspecialchars(
        (string)$nilai,
        ENT_QUOTES,
        "UTF-8"
    );
}

$pesan = "";

// Proses persetujuan atau penolakan
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)) {
        http_response_code(403);
        exit("Permintaan tidak valid.");
    }

    $id = filter_input(
        INPUT_POST,
        "id_peminjaman",
        FILTER_VALIDATE_INT
    );

    $aksi = $_POST["aksi"] ?? "";
    $catatan = trim($_POST["catatan_admin"] ?? "");

    if (!$id ||
        !in_array($aksi, ["approved", "rejected"], true) ||
        strlen($catatan) > 1000) {
        $pesan = "Data permintaan tidak valid.";
    } else {
        try {
            // Ambil ID ruangan dari pengajuan
            $cari = $koneksi->prepare(
                "SELECT id_ruangan
                 FROM peminjaman
                 WHERE id_peminjaman = ?"
            );
            $cari->execute([$id]);
            $idRuangan = $cari->fetchColumn();

            if (!$idRuangan) {
                throw new RuntimeException(
                    "Pengajuan tidak ditemukan."
                );
            }

            $koneksi->beginTransaction();

            // Urutan penguncian mengikuti form peminjaman:
            // kunci ruangan terlebih dahulu.
            $kunciRuang = $koneksi->prepare(
                "SELECT id_ruangan
                 FROM ruangan
                 WHERE id_ruangan = ?
                 FOR UPDATE"
            );
            $kunciRuang->execute([$idRuangan]);

            if (!$kunciRuang->fetchColumn()) {
                throw new RuntimeException(
                    "Ruangan tidak ditemukan."
                );
            }

            $kunci = $koneksi->prepare(
                "SELECT *
                 FROM peminjaman
                 WHERE id_peminjaman = ?
                 FOR UPDATE"
            );
            $kunci->execute([$id]);
            $data = $kunci->fetch(PDO::FETCH_ASSOC);

            if (!$data ||
                $data["status_peminjaman"] !== "pending" ||
                (int)$data["id_ruangan"] !== (int)$idRuangan) {
                throw new RuntimeException(
                    "Pengajuan sudah diproses atau berubah."
                );
            }

            if ($aksi === "approved") {
                // Pemeriksaan terakhir sebelum disetujui
                $cek = $koneksi->prepare(
                    "SELECT COUNT(*)
                     FROM peminjaman
                     WHERE id_ruangan = ?
                       AND tanggal = ?
                       AND id_peminjaman <> ?
                       AND status_peminjaman = 'approved'
                       AND jam_mulai < ?
                       AND jam_selesai > ?"
                );

                $cek->execute([
                    $data["id_ruangan"],
                    $data["tanggal"],
                    $id,
                    $data["jam_selesai"],
                    $data["jam_mulai"]
                ]);

                if ((int)$cek->fetchColumn() > 0) {
                    throw new RuntimeException(
                        "Jadwal bentrok dengan peminjaman disetujui."
                    );
                }
            }

            $ubah = $koneksi->prepare(
                "UPDATE peminjaman
                 SET status_peminjaman = ?,
                     catatan_admin = ?
                 WHERE id_peminjaman = ?
                   AND status_peminjaman = 'pending'"
            );

            $ubah->execute([
                $aksi,
                $catatan,
                $id
            ]);

            $koneksi->commit();

            header("Location: admin_peminjaman.php?hasil=berhasil");
            exit;

        } catch (RuntimeException $e) {
            if ($koneksi->inTransaction()) {
                $koneksi->rollBack();
            }
            $pesan = $e->getMessage();

        } catch (Throwable $e) {
            if ($koneksi->inTransaction()) {
                $koneksi->rollBack();
            }
            error_log($e->getMessage());
            $pesan = "Gagal memproses pengajuan.";
        }
    }
}

if (isset($_GET["hasil"]) &&
    $_GET["hasil"] === "berhasil") {
    $pesan = "Status peminjaman berhasil diperbarui.";
}

// Menampilkan seluruh pengajuan
$sql = "SELECT
            p.id_peminjaman,
            u.nama_lengkap,
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
        JOIN pengguna u
            ON p.id_pengguna = u.id_pengguna
        JOIN ruangan r
            ON p.id_ruangan = r.id_ruangan
        JOIN gedung g
            ON r.id_gedung = g.id_gedung
        ORDER BY p.tanggal DESC, p.id_peminjaman DESC";

$daftar = $koneksi->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>Admin Peminjaman - CampusSpace</title>
    <meta charset="UTF-8" />
    <style type="text/css">
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            padding: 25px;
        }
        h1 { color: #243b53; }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ccc;
            text-align: left;
        }
        th {
            background: #243b53;
            color: white;
        }
        textarea {
            width: 170px;
            box-sizing: border-box;
        }
        button {
            padding: 8px;
            cursor: pointer;
        }
        .setuju {
            background: #15803d;
            color: white;
            border: none;
        }
        .tolak {
            background: #b91c1c;
            color: white;
            border: none;
        }
        .pesan {
            padding: 12px;
            background: white;
        }
        form { margin-bottom: 5px; }
    </style>
</head>
<body>
    <h1>Admin - Pengelolaan Peminjaman</h1>

    <p>
        <a href="dashboard.php">Kembali ke Dashboard</a>
    </p>

    <?php if ($pesan !== ""): ?>
        <p class="pesan"><?= aman($pesan) ?></p>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Peminjam</th>
                <th>Ruangan</th>
                <th>Gedung</th>
                <th>Tanggal</th>
                <th>Jam</th>
                <th>Keperluan</th>
                <th>Peserta</th>
                <th>Status</th>
                <th>Aksi Admin</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($daftar)): ?>
                <tr>
                    <td colspan="9">Belum ada peminjaman.</td>
                </tr>
            <?php endif; ?>

            <?php foreach ($daftar as $p): ?>
                <tr>
                    <td><?= aman($p["nama_lengkap"]) ?></td>
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
                        <?= aman($p["status_peminjaman"]) ?>
                    </td>
                    <td>
                        <?php if ($p["status_peminjaman"] === "pending"): ?>
                            <form method="post"
                                  action="admin_peminjaman.php">

                                <input type="hidden"
                                       name="csrf_token"
                                       value="<?= aman($_SESSION["csrf_token"]) ?>" />

                                <input type="hidden"
                                       name="id_peminjaman"
                                       value="<?= (int)$p["id_peminjaman"] ?>" />

                                <textarea name="catatan_admin"
                                          rows="2" cols="20"
                                          maxlength="1000"
                                          placeholder="Catatan admin"></textarea>

                                <p>
                                    <button class="setuju"
                                            type="submit"
                                            name="aksi"
                                            value="approved">
                                        Setujui
                                    </button>

                                    <button class="tolak"
                                            type="submit"
                                            name="aksi"
                                            value="rejected">
                                        Tolak
                                    </button>
                                </p>
                            </form>
                        <?php else: ?>
                            <?= aman($p["catatan_admin"] ?: "-") ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<script src="js/konfirmasi.js"></script>
</body>
</html>
