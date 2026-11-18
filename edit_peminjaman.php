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

$idPengguna = (int)$_SESSION["id_pengguna"];
$idPeminjaman = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$idPeminjaman || $idPeminjaman < 1) {
    exit("ID peminjaman tidak valid.");
}

$pesan = "";

// Ambil peminjaman milik akun yang sedang login
$sql = "SELECT p.*, r.nama_ruangan, r.kapasitas,
               r.status_ruangan
        FROM peminjaman p
        JOIN ruangan r ON p.id_ruangan = r.id_ruangan
        WHERE p.id_peminjaman = ?
          AND p.id_pengguna = ?";

$stmt = $koneksi->prepare($sql);
$stmt->execute([$idPeminjaman, $idPengguna]);
$peminjaman = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$peminjaman) {
    exit("Data peminjaman tidak ditemukan.");
}

if ($peminjaman["status_peminjaman"] !== "pending") {
    exit("Hanya peminjaman pending yang dapat diedit.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)) {
        http_response_code(403);
        exit("Permintaan tidak valid.");
    }

    $tanggal = $_POST["tanggal"] ?? "";
    $mulai = $_POST["jam_mulai"] ?? "";
    $selesai = $_POST["jam_selesai"] ?? "";
    $keperluan = trim($_POST["keperluan"] ?? "");

    $peserta = filter_input(
        INPUT_POST,
        "jumlah_peserta",
        FILTER_VALIDATE_INT
    );

    // Memastikan format tanggal benar
    $tanggalObj = DateTimeImmutable::createFromFormat(
        "!Y-m-d",
        $tanggal
    );

    $tanggalValid = $tanggalObj &&
        $tanggalObj->format("Y-m-d") === $tanggal;

    $jamValid =
        preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $mulai) &&
        preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $selesai);

    if (!$tanggalValid || !$jamValid ||
        $mulai >= $selesai ||
        !$peserta || $peserta < 1 ||
        $keperluan === "" ||
        strlen($keperluan) > 255) {

        $pesan = "Data yang dimasukkan tidak valid.";

    } elseif ($tanggal < date("Y-m-d")) {
        $pesan = "Tanggal peminjaman tidak boleh di masa lalu.";

    } else {
        try {
            $koneksi->beginTransaction();

            $idRuangan = (int)$peminjaman["id_ruangan"];

            // Kunci ruangan agar pemeriksaan jadwal
            // tidak bertabrakan dengan pengajuan lain
            $cekRuang = $koneksi->prepare(
                "SELECT kapasitas, status_ruangan
                 FROM ruangan
                 WHERE id_ruangan = ?
                 FOR UPDATE"
            );
            $cekRuang->execute([$idRuangan]);
            $ruang = $cekRuang->fetch(PDO::FETCH_ASSOC);

            if (!$ruang ||
                $ruang["status_ruangan"] !== "aktif") {
                throw new RuntimeException(
                    "Ruangan sedang tidak aktif."
                );
            }

            // Pastikan transaksi masih milik pengguna
            // dan statusnya belum berubah oleh admin
            $cekPeminjaman = $koneksi->prepare(
                "SELECT status_peminjaman, id_ruangan
                 FROM peminjaman
                 WHERE id_peminjaman = ?
                   AND id_pengguna = ?
                 FOR UPDATE"
            );
            $cekPeminjaman->execute([
                $idPeminjaman,
                $idPengguna
            ]);
            $dataTerbaru = $cekPeminjaman->fetch(PDO::FETCH_ASSOC);

            if (!$dataTerbaru ||
                $dataTerbaru["status_peminjaman"] !== "pending" ||
                (int)$dataTerbaru["id_ruangan"] !== $idRuangan) {
                throw new RuntimeException(
                    "Peminjaman tidak dapat diubah lagi."
                );
            }

            if ($peserta > (int)$ruang["kapasitas"]) {
                throw new RuntimeException(
                    "Jumlah peserta melebihi kapasitas."
                );
            }

            // Kecualikan peminjaman yang sedang diedit
            $cekBentrok = $koneksi->prepare(
                "SELECT COUNT(*)
                 FROM peminjaman
                 WHERE id_ruangan = ?
                   AND tanggal = ?
                   AND id_peminjaman <> ?
                   AND status_peminjaman
                       IN ('pending', 'approved')
                   AND jam_mulai < ?
                   AND jam_selesai > ?"
            );

            $cekBentrok->execute([
                $idRuangan,
                $tanggal,
                $idPeminjaman,
                $selesai,
                $mulai
            ]);

            if ((int)$cekBentrok->fetchColumn() > 0) {
                throw new RuntimeException(
                    "Jadwal tersebut sudah digunakan."
                );
            }

            // Simpan perubahan
            $update = $koneksi->prepare(
                "UPDATE peminjaman
                 SET tanggal = ?,
                     jam_mulai = ?,
                     jam_selesai = ?,
                     keperluan = ?,
                     jumlah_peserta = ?
                 WHERE id_peminjaman = ?
                   AND id_pengguna = ?
                   AND status_peminjaman = 'pending'"
            );

            $update->execute([
                $tanggal,
                $mulai,
                $selesai,
                $keperluan,
                $peserta,
                $idPeminjaman,
                $idPengguna
            ]);

            $koneksi->commit();

            header("Location: riwayat_peminjaman.php");
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
            $pesan = "Gagal menyimpan perubahan.";
        }
    }
}
?>

<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>Edit Peminjaman - CampusSpace</title>
    <meta charset="UTF-8" />
    <style type="text/css">
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            padding: 40px 15px;
        }

        .kotak {
            max-width: 520px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
        }

        h1 { color: #243b53; }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        input, textarea {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
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

        .error { color: #b91c1c; }
    </style>
</head>
<body>
<div class="kotak">
    <h1>Edit Peminjaman</h1>

    <p>
        <strong>Ruangan:</strong>
        <?= aman($peminjaman["nama_ruangan"]) ?>
    </p>

    <p>
        <strong>Kapasitas:</strong>
        <?= (int)$peminjaman["kapasitas"] ?> orang
    </p>

    <?php if ($pesan !== ""): ?>
        <p class="error"><?= aman($pesan) ?></p>
    <?php endif; ?>

    <form method="post"
          action="edit_peminjaman.php?id=<?= (int)$idPeminjaman ?>">

        <input type="hidden"
               name="csrf_token"
               value="<?= aman($_SESSION["csrf_token"]) ?>" />

        <label for="tanggal">Tanggal Peminjaman</label>
        <input type="date"
               id="tanggal"
               name="tanggal"
               min="<?= date("Y-m-d") ?>"
               value="<?= aman($peminjaman["tanggal"]) ?>"
               required="required" />

        <label for="mulai">Jam Mulai</label>
        <input type="time"
               id="mulai"
               name="jam_mulai"
               value="<?= aman(substr($peminjaman["jam_mulai"], 0, 5)) ?>"
               required="required" />

        <label for="selesai">Jam Selesai</label>
        <input type="time"
               id="selesai"
               name="jam_selesai"
               value="<?= aman(substr($peminjaman["jam_selesai"], 0, 5)) ?>"
               required="required" />

        <label for="keperluan">Keperluan</label>
        <textarea id="keperluan"
                  name="keperluan"
                  rows="3"
                  cols="40"
                  maxlength="255"
                  required="required"><?= aman($peminjaman["keperluan"]) ?></textarea>

        <label for="peserta">Jumlah Peserta</label>
        <input type="number"
               id="peserta"
               name="jumlah_peserta"
               min="1"
               max="<?= (int)$peminjaman["kapasitas"] ?>"
               value="<?= (int)$peminjaman["jumlah_peserta"] ?>"
               required="required" />

        <button type="submit">Simpan Perubahan</button>
    </form>

    <p>
        <a href="riwayat_peminjaman.php">
            Kembali ke Riwayat
        </a>
    </p>
</div>
</body>
</html>
