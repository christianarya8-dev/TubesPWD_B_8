<?php
session_start();

require_once __DIR__ . "/config/database.php";

// Pengguna harus login
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
$csrfToken = aman($_SESSION["csrf_token"]);

// Susun baris tabel riwayat peminjaman
$barisTabelHtml = "";

if (empty($riwayat)) {
    $barisTabelHtml = '<tr><td colspan="9">Belum ada riwayat peminjaman.</td></tr>';
} else {
    foreach ($riwayat as $p) {
        $idPeminjaman = (int)$p["id_peminjaman"];
        $namaRuangan = aman($p["nama_ruangan"]);
        $namaGedung = aman($p["nama_gedung"]);
        $tanggal = aman($p["tanggal"]);
        $jamMulai = aman(substr($p["jam_mulai"], 0, 5));
        $jamSelesai = aman(substr($p["jam_selesai"], 0, 5));
        $keperluan = aman($p["keperluan"]);
        $jumlahPeserta = (int)$p["jumlah_peserta"];
        $statusRaw = $p["status_peminjaman"];
        $statusKelas = aman($statusRaw);
        $statusLabel = aman(ucfirst($statusRaw));
        $catatanAdmin = aman($p["catatan_admin"] ?: "-");

        if ($statusRaw === "pending") {
            $kolomAksi = '
                <div class="grup-aksi">
                    <a href="edit_peminjaman.php?id=' . $idPeminjaman . '" class="btn-edit">Edit</a>
                    <form method="post"
                          action="batalkan_peminjaman.php"
                          class="form-batal"
                          onsubmit="return confirm(\'Yakin ingin membatalkan peminjaman ini?\');">
                        <input type="hidden" name="csrf_token" value="' . $csrfToken . '" />
                        <input type="hidden" name="id_peminjaman" value="' . $idPeminjaman . '" />
                        <button type="submit" class="btn-batal">Batalkan</button>
                    </form>
                </div>';
        } else {
            $kolomAksi = "-";
        }

        $barisTabelHtml .= '
            <tr>
                <td>' . $namaRuangan . '</td>
                <td>' . $namaGedung . '</td>
                <td>' . $tanggal . '</td>
                <td>' . $jamMulai . ' - ' . $jamSelesai . '</td>
                <td>' . $keperluan . '</td>
                <td>' . $jumlahPeserta . ' orang</td>
                <td><span class="status-badge ' . $statusKelas . '">' . $statusLabel . '</span></td>
                <td>' . $catatanAdmin . '</td>
                <td>' . $kolomAksi . '</td>
            </tr>';
    }
}

// Muat template HTML
$html = file_get_contents(__DIR__ . "/riwayat_peminjaman.xhtml");

if ($html === false) {
    http_response_code(500);
    exit("Template riwayat peminjaman tidak ditemukan.");
}

// Isi placeholder ke template HTML
$html = strtr($html, [
    "{{DAFTAR_BARIS}}" => $barisTabelHtml
]);

echo $html;