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
$tipePesan = "sukses";

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
        $tipePesan = "error";
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

            // Kunci ruangan terlebih dahulu
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
            $tipePesan = "error";

        } catch (Throwable $e) {
            if ($koneksi->inTransaction()) {
                $koneksi->rollBack();
            }
            error_log($e->getMessage());
            $pesan = "Gagal memproses pengajuan.";
            $tipePesan = "error";
        }
    }
}

if (isset($_GET["hasil"]) &&
    $_GET["hasil"] === "berhasil") {
    $pesan = "Status peminjaman berhasil diperbarui.";
    $tipePesan = "sukses";
}

// Ambil seluruh pengajuan peminjaman
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

$semuaPeminjaman = $koneksi->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Hitung jumlah per status untuk menu tab
$jumlahStatus = [
    "semua" => count($semuaPeminjaman),
    "pending" => 0,
    "approved" => 0,
    "rejected" => 0,
    "cancelled" => 0
];

foreach ($semuaPeminjaman as $item) {
    $st = $item["status_peminjaman"];
    if (isset($jumlahStatus[$st])) {
        $jumlahStatus[$st]++;
    }
}

// Filter berdasarkan tab menu yang dipilih
$filterAktif = $_GET["status"] ?? "semua";
if (!array_key_exists($filterAktif, $jumlahStatus)) {
    $filterAktif = "semua";
}

if ($filterAktif === "semua") {
    $daftar = $semuaPeminjaman;
} else {
    $daftar = array_filter(
        $semuaPeminjaman,
        function ($row) use ($filterAktif) {
            return $row["status_peminjaman"] === $filterAktif;
        }
    );
}
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
            margin: 0;
            padding: 25px;
            color: #1f2933;
        }
        .navbar-admin {
            background: #243b53;
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 22px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .navbar-brand {
            color: #ffffff;
            font-weight: bold;
            font-size: 18px;
        }
        .navbar-links {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }
        .navbar-links a {
            color: #e2e8f0;
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 14px;
        }
        .navbar-links a:hover {
            background: #334e68;
            color: #ffffff;
        }
        .navbar-links a.aktif {
            background: #ffffff;
            color: #243b53;
            font-weight: bold;
        }
        .btn-logout {
            background: #b91c1c;
            color: #ffffff;
            border: none;
            padding: 8px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-logout:hover {
            background: #991b1b;
        }
        h1 {
            color: #243b53;
            margin-top: 0;
            margin-bottom: 16px;
        }
        .menu-status {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 18px;
        }
        .menu-status a {
            text-decoration: none;
            background: #ffffff;
            color: #243b53;
            padding: 9px 14px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 14px;
            font-weight: bold;
        }
        .menu-status a:hover {
            background: #e2e8f0;
        }
        .menu-status a.aktif {
            background: #243b53;
            color: #ffffff;
            border-color: #243b53;
        }
        .badge-hitung {
            background: rgba(148, 163, 184, 0.25);
            padding: 2px 7px;
            border-radius: 10px;
            margin-left: 5px;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ccc;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #243b53;
            color: white;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 9px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
        }
        .pending { background: #fef3c7; color: #a16207; }
        .approved { background: #dcfce7; color: #15803d; }
        .rejected { background: #fee2e2; color: #b91c1c; }
        .cancelled { background: #f1f5f9; color: #64748b; }
        textarea {
            width: 180px;
            box-sizing: border-box;
            padding: 6px;
        }
        .aksi-btn {
            padding: 7px 10px;
            cursor: pointer;
            border-radius: 5px;
            font-weight: bold;
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
            padding: 12px 15px;
            background: #dcfce7;
            color: #15803d;
            border-left: 4px solid #15803d;
            border-radius: 6px;
            margin-bottom: 16px;
            font-weight: bold;
        }
        .pesan.error {
            background: #fee2e2;
            color: #b91c1c;
            border-left-color: #b91c1c;
        }
        form { margin: 0; }
    </style>
</head>
<body>

    <nav class="navbar-admin">
        <div class="navbar-brand">CampusSpace — Panel Admin</div>
        <div class="navbar-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="admin_peminjaman.php" class="aktif">Kelola Peminjaman</a>
            <a href="daftar_ruangan.php">Kelola Ruangan</a>
            <a href="tambah_ruangan.php">+ Tambah Ruangan</a>
            <a href="profil.php">Profil Saya</a>
            <form method="post" action="logout.php" style="display:inline;">
                <input type="hidden"
                       name="csrf_token"
                       value="<?= aman($_SESSION["csrf_token"]) ?>" />
                <button type="submit" class="btn-logout">Logout</button>
            </form>
        </div>
    </nav>

    <h1>Admin - Pengelolaan Peminjaman</h1>

    <div class="menu-status">
        <a href="admin_peminjaman.php?status=semua"
           class="<?= $filterAktif === 'semua' ? 'aktif' : '' ?>">
            Semua <span class="badge-hitung"><?= $jumlahStatus["semua"] ?></span>
        </a>
        <a href="admin_peminjaman.php?status=pending"
           class="<?= $filterAktif === 'pending' ? 'aktif' : '' ?>">
            Pending <span class="badge-hitung"><?= $jumlahStatus["pending"] ?></span>
        </a>
        <a href="admin_peminjaman.php?status=approved"
           class="<?= $filterAktif === 'approved' ? 'aktif' : '' ?>">
            Approved <span class="badge-hitung"><?= $jumlahStatus["approved"] ?></span>
        </a>
        <a href="admin_peminjaman.php?status=rejected"
           class="<?= $filterAktif === 'rejected' ? 'aktif' : '' ?>">
            Rejected <span class="badge-hitung"><?= $jumlahStatus["rejected"] ?></span>
        </a>
        <a href="admin_peminjaman.php?status=cancelled"
           class="<?= $filterAktif === 'cancelled' ? 'aktif' : '' ?>">
            Cancelled <span class="badge-hitung"><?= $jumlahStatus["cancelled"] ?></span>
        </a>
    </div>

    <?php if ($pesan !== ""): ?>
        <p class="pesan <?= $tipePesan === 'error' ? 'error' : '' ?>">
            <?= aman($pesan) ?>
        </p>
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
                    <td colspan="9">Belum ada peminjaman pada kategori ini.</td>
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
                        <span class="status-badge <?= aman($p["status_peminjaman"]) ?>">
                            <?= aman(ucfirst($p["status_peminjaman"])) ?>
                        </span>
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

                                <p style="margin: 6px 0 0 0;">
                                    <button class="aksi-btn setuju"
                                            type="submit"
                                            name="aksi"
                                            value="approved">
                                        Setujui
                                    </button>

                                    <button class="aksi-btn tolak"
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
<script src="JS/konfirmasi.js"></script>
</body>
</html>