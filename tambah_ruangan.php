<?php
require_once __DIR__ . "/config/admin_guard.php";

$pesan = "";
$berhasil = false;

// Mengambil daftar gedung
$queryGedung = $koneksi->query(
    "SELECT id_gedung, nama_gedung FROM gedung ORDER BY nama_gedung"
);
$daftarGedung = $queryGedung->fetchAll(PDO::FETCH_ASSOC);

// Memproses form
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)) {
        http_response_code(403);
        exit("Permintaan tidak valid.");
    }

    $idGedung = filter_input(INPUT_POST, "id_gedung", FILTER_VALIDATE_INT);
    $kode = trim($_POST["kode_ruangan"] ?? "");
    $nama = trim($_POST["nama_ruangan"] ?? "");
    $kapasitas = filter_input(INPUT_POST, "kapasitas", FILTER_VALIDATE_INT);
    $deskripsi = trim($_POST["deskripsi"] ?? "");

    if (!$idGedung || $kode === "" || $nama === "" ||
        !$kapasitas || $kapasitas < 1) {
        $pesan = "Data belum lengkap atau tidak valid.";
    } else {
        try {
            $sql = "INSERT INTO ruangan
                    (id_gedung, kode_ruangan, nama_ruangan,
                     kapasitas, deskripsi)
                    VALUES (:gedung, :kode, :nama, :kapasitas, :deskripsi)";

            $stmt = $koneksi->prepare($sql);
            $stmt->execute([
                ":gedung" => $idGedung,
                ":kode" => $kode,
                ":nama" => $nama,
                ":kapasitas" => $kapasitas,
                ":deskripsi" => $deskripsi
            ]);

            $pesan = "Ruangan berhasil ditambahkan!";
            $berhasil = true;

        } catch (PDOException $e) {
            if ($e->getCode() === "23000") {
                $pesan = "Kode ruangan sudah digunakan atau gedung tidak valid.";
            } else {
                error_log($e->getMessage());
                $pesan = "Terjadi kesalahan saat menyimpan data.";
            }
        }
    }
}

function aman($teks) {
    return htmlspecialchars((string)$teks, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>Tambah Ruangan - CampusSpace</title>
    <meta charset="UTF-8" />
    <style type="text/css">
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 40px;
        }
        .form-box {
            max-width: 500px;
            background: white;
            padding: 25px;
            border-radius: 10px;
        }
        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }
        input, select, textarea {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            box-sizing: border-box;
        }
        button {
            margin-top: 20px;
            padding: 12px 20px;
            background: #243b53;
            color: white;
            border: none;
            cursor: pointer;
        }
        .pesan {
            margin: 15px 0;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="form-box">
    <h1>Tambah Ruangan</h1>

    <?php if ($pesan !== ""): ?>
        <p class="pesan"><?= aman($pesan) ?></p>
    <?php endif; ?>

    <form method="post" action="tambah_ruangan.php">

        <input type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                $_SESSION["csrf_token"],
                ENT_QUOTES,
                "UTF-8"
            ) ?>" />
            
        <label for="id_gedung">Gedung</label>
        <select name="id_gedung" id="id_gedung" required="required">
            <option value="">-- Pilih Gedung --</option>
            <?php foreach ($daftarGedung as $gedung): ?>
                <option value="<?= (int)$gedung['id_gedung'] ?>">
                    <?= aman($gedung['nama_gedung']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="kode_ruangan">Kode Ruangan</label>
        <input type="text" name="kode_ruangan"
               id="kode_ruangan" required="required" />

        <label for="nama_ruangan">Nama Ruangan</label>
        <input type="text" name="nama_ruangan"
               id="nama_ruangan" required="required" />

        <label for="kapasitas">Kapasitas</label>
        <input type="number" name="kapasitas" id="kapasitas"
               min="1" required="required" />

        <label for="deskripsi">Deskripsi</label>
        <textarea name="deskripsi" id="deskripsi"
                  rows="4" cols="40"></textarea>

        <button type="submit">Simpan Ruangan</button>
    </form>

    <p>
        <a href="daftar_ruangan.php">Lihat Daftar Ruangan</a>
    </p>
</div>

</body>
</html>
