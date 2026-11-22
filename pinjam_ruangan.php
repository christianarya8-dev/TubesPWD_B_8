<?php
session_start();
require_once __DIR__ . "/config/database.php";

if (!isset($_SESSION["id_pengguna"])) {
    header("Location: login.php");
    exit;
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function aman($nilai) {
    return htmlspecialchars((string)$nilai, ENT_QUOTES, "UTF-8");
}

$pesan = "";
$berhasil = false;

// Nilai awal form agar tidak hilang jika submit gagal
$idRuanganLama = "";
$tanggalLama = "";
$mulaiLama = "";
$selesaiLama = "";
$keperluanLama = "";
$pesertaLama = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)) {
        http_response_code(403);
        exit("Permintaan tidak valid.");
    }

    $idRuangan = filter_input(
        INPUT_POST, "id_ruangan", FILTER_VALIDATE_INT
    );
    $tanggal = $_POST["tanggal"] ?? "";
    $mulai = $_POST["jam_mulai"] ?? "";
    $selesai = $_POST["jam_selesai"] ?? "";
    $keperluan = trim($_POST["keperluan"] ?? "");
    $peserta = filter_input(
        INPUT_POST, "jumlah_peserta", FILTER_VALIDATE_INT
    );

    // Simpan nilai input sementara
    $idRuanganLama = (string)($_POST["id_ruangan"] ?? "");
    $tanggalLama = $tanggal;
    $mulaiLama = $mulai;
    $selesaiLama = $selesai;
    $keperluanLama = $keperluan;
    $pesertaLama = (string)($_POST["jumlah_peserta"] ?? "");

    $awal = DateTimeImmutable::createFromFormat(
        "!Y-m-d", $tanggal
    );
    $tanggalValid = $awal &&
        $awal->format("Y-m-d") === $tanggal;

    $jamValid = preg_match(
        '/^([01][0-9]|2[0-3]):[0-5][0-9]$/',
        $mulai
    ) && preg_match(
        '/^([01][0-9]|2[0-3]):[0-5][0-9]$/',
        $selesai
    );

    if (!$idRuangan || !$tanggalValid || !$jamValid ||
        $mulai >= $selesai || !$peserta ||
        $peserta < 1 || $keperluan === "" ||
        strlen($keperluan) > 255) {
        $pesan = "Periksa kembali data peminjaman.";
    } elseif ($tanggal < date("Y-m-d")) {
        $pesan = "Tanggal peminjaman tidak boleh di masa lalu.";
    } else {
        try {
            $koneksi->beginTransaction();

            $cekRuang = $koneksi->prepare(
                "SELECT kapasitas, status_ruangan
                 FROM ruangan
                 WHERE id_ruangan = ?
                 FOR UPDATE"
            );
            $cekRuang->execute([$idRuangan]);
            $ruang = $cekRuang->fetch(PDO::FETCH_ASSOC);

            if (!$ruang || $ruang["status_ruangan"] !== "aktif") {
                throw new RuntimeException(
                    "Ruangan tidak tersedia."
                );
            }

            if ($peserta > (int)$ruang["kapasitas"]) {
                throw new RuntimeException(
                    "Jumlah peserta (" . $peserta . " orang) melebihi kapasitas ruangan (" . (int)$ruang["kapasitas"] . " orang)."
                );
            }

            $cekBentrok = $koneksi->prepare(
                "SELECT COUNT(*)
                 FROM peminjaman
                 WHERE id_ruangan = ?
                   AND tanggal = ?
                   AND status_peminjaman
                       IN ('pending', 'approved')
                   AND jam_mulai < ?
                   AND jam_selesai > ?"
            );
            $cekBentrok->execute([
                $idRuangan, $tanggal, $selesai, $mulai
            ]);

            if ((int)$cekBentrok->fetchColumn() > 0) {
                throw new RuntimeException(
                    "Ruangan sudah dipesan pada jadwal tersebut."
                );
            }

            $simpan = $koneksi->prepare(
                "INSERT INTO peminjaman
                 (id_pengguna, id_ruangan, tanggal,
                  jam_mulai, jam_selesai, keperluan,
                  jumlah_peserta)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $simpan->execute([
                (int)$_SESSION["id_pengguna"],
                $idRuangan, $tanggal, $mulai,
                $selesai, $keperluan, $peserta
            ]);

            $koneksi->commit();
            $berhasil = true;
            $pesan = "Pengajuan berhasil untuk " . $peserta . " peserta! Menunggu persetujuan admin.";

            // Kosongkan form setelah berhasil tersimpan
            $idRuanganLama = "";
            $tanggalLama = "";
            $mulaiLama = "";
            $selesaiLama = "";
            $keperluanLama = "";
            $pesertaLama = "";

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
            $pesan = "Gagal menyimpan peminjaman.";
        }
    }
}

// Ambil ruangan aktif untuk pilihan form
$sql = "SELECT r.id_ruangan, r.nama_ruangan,
               r.kapasitas, g.nama_gedung
        FROM ruangan r
        JOIN gedung g ON r.id_gedung = g.id_gedung
        WHERE r.status_ruangan = 'aktif'
        ORDER BY g.nama_gedung, r.nama_ruangan";

$ruangan = $koneksi->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>Pinjam Ruangan - CampusSpace</title>
    <meta charset="UTF-8" />
    <style type="text/css">
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            padding: 40px 15px;
        }
        .kotak {
            max-width: 550px;
            margin: auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
        }
        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }
        input, select, textarea {
            width: 100%;
            margin-top: 6px;
            padding: 10px;
            box-sizing: border-box;
        }
        button {
            margin-top: 20px;
            padding: 12px 18px;
            background: #243b53;
            color: white;
            border: none;
            cursor: pointer;
        }
        .pesan {
            padding: 10px 12px;
            border-radius: 6px;
            font-weight: bold;
        }
        .pesan.sukses {
            background: #dcfce7;
            color: #15803d;
        }
        .pesan.error {
            background: #fee2e2;
            color: #b91c1c;
        }
    </style>
</head>
<body>

<div class="kotak">
    <h1>Form Peminjaman Ruangan</h1>

    <?php if ($pesan !== ""): ?>
        <p class="pesan <?= $berhasil ? 'sukses' : 'error' ?>">
            <?= aman($pesan) ?>
        </p>
    <?php endif; ?>

    <form method="post" action="pinjam_ruangan.php">
        <input type="hidden"
               name="csrf_token"
               value="<?= aman($_SESSION["csrf_token"]) ?>" />

        <label for="ruangan">Pilih Ruangan</label>
        <select id="ruangan" name="id_ruangan"
                required="required">
            <option value="">-- Pilih Ruangan --</option>

            <?php foreach ($ruangan as $r): ?>
                <option value="<?= (int)$r["id_ruangan"] ?>"
                    <?= ((string)$r["id_ruangan"] === $idRuanganLama) ? 'selected="selected"' : '' ?>>
                    <?= aman($r["nama_ruangan"]) ?>
                    - <?= aman($r["nama_gedung"]) ?>
                    (<?= (int)$r["kapasitas"] ?> orang)
                </option>
            <?php endforeach; ?>
        </select>

        <label for="tanggal">Tanggal Peminjaman</label>
        <input type="date" id="tanggal" name="tanggal"
               min="<?= date("Y-m-d") ?>"
               value="<?= aman($tanggalLama) ?>"
               required="required" />

        <label for="mulai">Jam Mulai</label>
        <input type="time" id="mulai" name="jam_mulai"
               value="<?= aman($mulaiLama) ?>"
               required="required" />

        <label for="selesai">Jam Selesai</label>
        <input type="time" id="selesai" name="jam_selesai"
               value="<?= aman($selesaiLama) ?>"
               required="required" />

        <label for="keperluan">Keperluan</label>
        <textarea id="keperluan" name="keperluan"
                  rows="3" cols="40"
                  maxlength="255"
                  required="required"><?= aman($keperluanLama) ?></textarea>

        <label for="peserta">Jumlah Peserta</label>
        <input type="number" id="peserta"
               name="jumlah_peserta" min="1"
               value="<?= aman($pesertaLama) ?>"
               required="required" />

        <button type="submit">Ajukan Peminjaman</button>
    </form>

    <p>
        <a href="dashboard.php">Kembali ke Dashboard</a> |
        <a href="riwayat_peminjaman.php">Lihat Riwayat Peminjaman</a>
    </p>
</div>
<script src="JS/index.js"></script>
</body>
</html>