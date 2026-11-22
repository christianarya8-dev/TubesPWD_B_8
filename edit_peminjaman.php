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

if (!$idPeminjaman) {
    exit("ID peminjaman tidak valid.");
}

if ($idPeminjaman < 1) {
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

// Nilai awal form diambil dari database
$tanggalForm = $peminjaman["tanggal"];
$mulaiForm = substr($peminjaman["jam_mulai"], 0, 5);
$selesaiForm = substr($peminjaman["jam_selesai"], 0, 5);
$keperluanForm = $peminjaman["keperluan"];
$pesertaForm = (string)(int)$peminjaman["jumlah_peserta"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token)) {
        http_response_code(403);
        exit("Permintaan tidak valid.");
    }

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
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

    // Simpan input pengguna agar tidak hilang jika terjadi error
    $tanggalForm = $tanggal;
    $mulaiForm = $mulai;
    $selesaiForm = $selesai;
    $keperluanForm = $keperluan;
    $pesertaForm = (string)($_POST["jumlah_peserta"] ?? "");

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

    if (!$tanggalValid) {
        $pesan = "Format tanggal tidak valid.";
    } elseif (!$jamValid) {
        $pesan = "Format jam tidak valid.";
    } elseif ($mulai >= $selesai) {
        $pesan = "Jam selesai harus lebih besar dari jam mulai.";
    } elseif (!$peserta) {
        $pesan = "Jumlah peserta tidak valid.";
    } elseif ($peserta < 1) {
        $pesan = "Jumlah peserta minimal 1 orang.";
    } elseif ($keperluan === "") {
        $pesan = "Keperluan wajib diisi.";
    } elseif (strlen($keperluan) > 255) {
        $pesan = "Keperluan maksimal 255 karakter.";
    } elseif ($tanggal < date("Y-m-d")) {
        $pesan = "Tanggal peminjaman tidak boleh di masa lalu.";
    } else {
        try {
            $koneksi->beginTransaction();

            $idRuangan = (int)$peminjaman["id_ruangan"];

            // Kunci ruangan agar pemeriksaan jadwal tidak bertabrakan
            $cekRuang = $koneksi->prepare(
                "SELECT kapasitas, status_ruangan
                 FROM ruangan
                 WHERE id_ruangan = ?
                 FOR UPDATE"
            );
            $cekRuang->execute([$idRuangan]);
            $ruang = $cekRuang->fetch(PDO::FETCH_ASSOC);

            if (!$ruang) {
                throw new RuntimeException(
                    "Ruangan tidak ditemukan."
                );
            }

            if ($ruang["status_ruangan"] !== "aktif") {
                throw new RuntimeException(
                    "Ruangan sedang tidak aktif."
                );
            }

            // Pastikan transaksi masih milik pengguna dan berstatus pending
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

            if (!$dataTerbaru) {
                throw new RuntimeException(
                    "Peminjaman tidak ditemukan."
                );
            }

            if ($dataTerbaru["status_peminjaman"] !== "pending") {
                throw new RuntimeException(
                    "Peminjaman tidak dapat diubah lagi."
                );
            }

            if ((int)$dataTerbaru["id_ruangan"] !== $idRuangan) {
                throw new RuntimeException(
                    "Peminjaman tidak dapat diubah lagi."
                );
            }

            if ($peserta > (int)$ruang["kapasitas"]) {
                throw new RuntimeException(
                    "Jumlah peserta melebihi kapasitas ruangan (" . (int)$ruang["kapasitas"] . " orang)."
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

// Muat template HTML
$html = file_get_contents(__DIR__ . "/edit_peminjaman.xhtml");

if ($html === false) {
    http_response_code(500);
    exit("Template edit peminjaman tidak ditemukan.");
}

// Tampilkan atau hapus blok pesan error
if ($pesan !== "") {
    $html = str_replace(["<!-- PESAN_START -->", "<!-- PESAN_END -->"], "", $html);
} else {
    $html = preg_replace('/<!-- PESAN_START -->.*?<!-- PESAN_END -->/s', "", $html);
}

// Isi placeholder ke template HTML
$html = strtr($html, [
    "{{ID_PEMINJAMAN}}" => (string)(int)$idPeminjaman,
    "{{NAMA_RUANGAN}}" => aman($peminjaman["nama_ruangan"]),
    "{{KAPASITAS}}" => (string)(int)$peminjaman["kapasitas"],
    "{{PESAN}}" => aman($pesan),
    "{{CSRF_TOKEN}}" => aman($_SESSION["csrf_token"]),
    "{{TANGGAL_MIN}}" => date("Y-m-d"),
    "{{TANGGAL}}" => aman($tanggalForm),
    "{{JAM_MULAI}}" => aman($mulaiForm),
    "{{JAM_SELESAI}}" => aman($selesaiForm),
    "{{KEPERLUAN}}" => aman($keperluanForm),
    "{{JUMLAH_PESERTA}}" => aman($pesertaForm)
]);

echo $html;