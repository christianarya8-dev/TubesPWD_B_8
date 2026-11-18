<?php
require_once __DIR__ . "/config/admin_guard.php";

function aman($nilai) {
    return htmlspecialchars((string)$nilai, ENT_QUOTES, "UTF-8");
}

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    exit("ID ruangan tidak valid.");
}

$pesan = "";

// Mengambil data ruangan yang akan diedit
$stmt = $koneksi->prepare(
    "SELECT * FROM ruangan WHERE id_ruangan = ?"
);
$stmt->execute([$id]);
$ruangan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ruangan) {
    exit("Ruangan tidak ditemukan.");
}

// Mengambil daftar gedung untuk pilihan dropdown
$daftarGedung = $koneksi->query(
    "SELECT id_gedung, nama_gedung
     FROM gedung ORDER BY nama_gedung"
)->fetchAll(PDO::FETCH_ASSOC);

// Memproses perubahan dari form
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";
    if (!is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)) {
        http_response_code(403);
        exit("Permintaan tidak valid.");
    }

    $nama = trim($_POST["nama_ruangan"] ?? "");
    $kode = trim($_POST["kode_ruangan"] ?? "");
    $gedung = filter_input(
        INPUT_POST, "id_gedung", FILTER_VALIDATE_INT
    );
    $kapasitas = filter_input(
        INPUT_POST, "kapasitas", FILTER_VALIDATE_INT
    );
    $deskripsi = trim($_POST["deskripsi"] ?? "");

    if ($nama === "" || $kode === "" ||
        !$gedung || !$kapasitas || $kapasitas < 1) {
        $pesan = "Data belum lengkap atau tidak valid.";
    } else {
        try {
            $sql = "UPDATE ruangan SET
                        nama_ruangan = :nama,
                        kode_ruangan = :kode,
                        id_gedung = :gedung,
                        kapasitas = :kapasitas,
                        deskripsi = :deskripsi
                    WHERE id_ruangan = :id";

            $query = $koneksi->prepare($sql);

            $query->execute([
                ":nama" => $nama,
                ":kode" => $kode,
                ":gedung" => $gedung,
                ":kapasitas" => $kapasitas,
                ":deskripsi" => $deskripsi,
                ":id" => $id
            ]);

            header("Location: daftar_ruangan.php");
            exit;

        } catch (PDOException $e) {
            if ($e->getCode() === "23000") {
                $pesan = "Kode ruangan sudah digunakan atau gedung tidak valid.";
            } else {
                error_log($e->getMessage());
                $pesan = "Gagal mengubah data ruangan.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>Edit Ruangan - CampusSpace</title>
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
            box-sizing: border-box;
            margin-top: 5px;
        }
        button {
            margin-top: 20px;
            padding: 12px 20px;
            background: #243b53;
            color: white;
            border: none;
            cursor: pointer;
        }
    </style>
</head>
<body>

<div class="form-box">
    <h1>Edit Data Ruangan</h1>

    <?php if ($pesan !== ""): ?>
        <p><?= aman($pesan) ?></p>
    <?php endif; ?>

    <form method="post"
          action="edit_ruangan.php?id=<?= (int)$id ?>">

        <input type="hidden"
            name="csrf_token"
            value="<?= aman($_SESSION["csrf_token"]) ?>" />



        <label for="kode">Kode Ruangan</label>
        <input type="text" id="kode"
               name="kode_ruangan"
               value="<?= aman($ruangan['kode_ruangan']) ?>"
               required="required" />

        <label for="nama">Nama Ruangan</label>
        <input type="text" id="nama"
               name="nama_ruangan"
               value="<?= aman($ruangan['nama_ruangan']) ?>"
               required="required" />

        <label for="gedung">Gedung</label>
        <select id="gedung" name="id_gedung"
                required="required">
            <?php foreach ($daftarGedung as $item): ?>
                <option
                    value="<?= (int)$item['id_gedung'] ?>"
                    <?php
                    if ($item['id_gedung'] == $ruangan['id_gedung']) {
                        echo 'selected="selected"';
                    }
                    ?>>
                    <?= aman($item['nama_gedung']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="kapasitas">Kapasitas</label>
        <input type="number" id="kapasitas"
               name="kapasitas" min="1"
               value="<?= (int)$ruangan['kapasitas'] ?>"
               required="required" />

        <label for="deskripsi">Deskripsi</label>
        <textarea id="deskripsi" name="deskripsi"
                  rows="4" cols="40"><?= aman($ruangan['deskripsi']) ?></textarea>

        <button type="submit">Simpan Perubahan</button>
    </form>

    <p>
        <a href="daftar_ruangan.php">Kembali ke Daftar Ruangan</a>
    </p>
</div>

</body>
</html>
