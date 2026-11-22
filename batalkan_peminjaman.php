<?php
session_start();

require_once __DIR__ . "/config/database.php";

// Pastikan pengguna sudah login
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
$pesanError = "";
$dataPeminjaman = null;

// Proses pembatalan ketika menerima permintaan POST
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

    $idPeminjaman = filter_input(
        INPUT_POST,
        "id_peminjaman",
        FILTER_VALIDATE_INT
    );

    if (!$idPeminjaman) {
        $pesanError = "ID peminjaman tidak valid.";
    } elseif ($idPeminjaman < 1) {
        $pesanError = "ID peminjaman tidak valid.";
    } else {
        try {
            $sql = "UPDATE peminjaman
                    SET status_peminjaman = 'cancelled'
                    WHERE id_peminjaman = ?
                      AND id_pengguna = ?
                      AND status_peminjaman = 'pending'";

            $stmt = $koneksi->prepare($sql);
            $stmt->execute([
                $idPeminjaman,
                $idPengguna
            ]);

            if ($stmt->rowCount() > 0) {
                header("Location: riwayat_peminjaman.php");
                exit;
            }

            $pesanError = "Peminjaman tidak ditemukan atau statusnya sudah tidak dapat dibatalkan.";
        } catch (Throwable $e) {
            error_log($e->getMessage());
            $pesanError = "Terjadi kesalahan saat membatalkan peminjaman.";
        }
    }
} else {
    // Jika dibuka melalui GET (?id=...), tampilkan detail konfirmasi pembatalan
    $idGet = filter_input(
        INPUT_GET,
        "id",
        FILTER_VALIDATE_INT
    );

    if (!$idGet) {
        $pesanError = "Pilih peminjaman yang ingin dibatalkan melalui halaman Riwayat Peminjaman.";
    } elseif ($idGet < 1) {
        $pesanError = "ID peminjaman tidak valid.";
    } else {
        $sqlDetail = "SELECT
                        p.id_peminjaman,
                        r.nama_ruangan,
                        g.nama_gedung,
                        p.tanggal,
                        p.jam_mulai,
                        p.jam_selesai,
                        p.keperluan,
                        p.jumlah_peserta,
                        p.status_peminjaman
                      FROM peminjaman p
                      INNER JOIN ruangan r
                          ON p.id_ruangan = r.id_ruangan
                      INNER JOIN gedung g
                          ON r.id_gedung = g.id_gedung
                      WHERE p.id_peminjaman = ?
                        AND p.id_pengguna = ?";

        $stmtDetail = $koneksi->prepare($sqlDetail);
        $stmtDetail->execute([
            $idGet,
            $idPengguna
        ]);
        $hasil = $stmtDetail->fetch(PDO::FETCH_ASSOC);

        if (!$hasil) {
            $pesanError = "Data peminjaman tidak ditemukan.";
        } elseif ($hasil["status_peminjaman"] !== "pending") {
            $pesanError = "Hanya peminjaman berstatus pending yang dapat dibatalkan.";
        } else {
            $dataPeminjaman = $hasil;
        }
    }
}

// Muat template HTML
$html = file_get_contents(__DIR__ . "/batalkan_peminjaman.xhtml");

if ($html === false) {
    http_response_code(500);
    exit("Template pembatalan peminjaman tidak ditemukan.");
}

// Tampilkan blok konfirmasi atau blok pesan error
if ($dataPeminjaman !== null) {
    $html = str_replace(["<!-- KONFIRMASI_START -->", "<!-- KONFIRMASI_END -->"], "", $html);
    $html = preg_replace('/<!-- ERROR_START -->.*?<!-- ERROR_END -->/s', "", $html);

    $html = strtr($html, [
        "{{CSRF_TOKEN}}" => aman($_SESSION["csrf_token"]),
        "{{ID_PEMINJAMAN}}" => (string)(int)$dataPeminjaman["id_peminjaman"],
        "{{NAMA_RUANGAN}}" => aman($dataPeminjaman["nama_ruangan"]),
        "{{NAMA_GEDUNG}}" => aman($dataPeminjaman["nama_gedung"]),
        "{{TANGGAL}}" => aman($dataPeminjaman["tanggal"]),
        "{{JAM_MULAI}}" => aman(substr($dataPeminjaman["jam_mulai"], 0, 5)),
        "{{JAM_SELESAI}}" => aman(substr($dataPeminjaman["jam_selesai"], 0, 5)),
        "{{KEPERLUAN}}" => aman($dataPeminjaman["keperluan"]),
        "{{JUMLAH_PESERTA}}" => (string)(int)$dataPeminjaman["jumlah_peserta"]
    ]);
} else {
    $html = preg_replace('/<!-- KONFIRMASI_START -->.*?<!-- KONFIRMASI_END -->/s', "", $html);
    $html = str_replace(["<!-- ERROR_START -->", "<!-- ERROR_END -->"], "", $html);

    $html = strtr($html, [
        "{{PESAN_ERROR}}" => aman($pesanError !== "" ? $pesanError : "Permintaan tidak dapat diproses.")
    ]);
}

echo $html;