<?php
session_start();

require_once __DIR__ . "/config/database.php";

// Fungsi untuk mengamankan tampilan teks
function aman($nilai) {
    return htmlspecialchars((string)$nilai, ENT_QUOTES, "UTF-8");
}

// Periksa apakah pengguna adalah admin
$admin = false;

if (isset($_SESSION["id_pengguna"])) {
    $cekAdmin = $koneksi->prepare(
        "SELECT peran
         FROM pengguna
         WHERE id_pengguna = ?"
    );

    $cekAdmin->execute([
        (int)$_SESSION["id_pengguna"]
    ]);

    $admin = ($cekAdmin->fetchColumn() === "admin");
}

// Siapkan token CSRF untuk admin
if ($admin && empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Mengambil daftar ruangan aktif beserta gedung
$sql = "SELECT
            r.id_ruangan,
            r.kode_ruangan,
            r.nama_ruangan,
            r.kapasitas,
            g.nama_gedung
        FROM ruangan r
        INNER JOIN gedung g
            ON r.id_gedung = g.id_gedung
        WHERE r.status_ruangan = 'aktif'
        ORDER BY r.id_ruangan";

$query = $koneksi->query($sql);
$dataRuangan = $query->fetchAll(PDO::FETCH_ASSOC);

$csrfToken = $admin ? aman($_SESSION["csrf_token"]) : "";
$kolomHeaderAdmin = $admin ? "<th>Aksi</th>" : "";

// Susun baris tabel ruangan
$barisTabelHtml = "";

if (empty($dataRuangan)) {
    $colspan = $admin ? 5 : 4;
    $barisTabelHtml = '<tr><td colspan="' . $colspan . '">Belum ada ruangan tersedia.</td></tr>';
} else {
    foreach ($dataRuangan as $ruangan) {
        $idRuangan = (int)$ruangan["id_ruangan"];
        $kodeRuangan = aman($ruangan["kode_ruangan"]);
        $namaRuangan = aman($ruangan["nama_ruangan"]);
        $namaGedung = aman($ruangan["nama_gedung"]);
        $kapasitas = (int)$ruangan["kapasitas"];

        $kolomAksiAdmin = "";
        if ($admin) {
            $kolomAksiAdmin = '
                <td class="kolom-aksi">
                    <a href="edit_ruangan.php?id=' . $idRuangan . '" class="btn-edit">Edit</a>
                    <form method="post"
                          action="hapus_ruangan.php"
                          class="form-hapus"
                          onsubmit="return confirm(\'Yakin ingin menonaktifkan ruangan ini?\');">
                        <input type="hidden" name="csrf_token" value="' . $csrfToken . '" />
                        <input type="hidden" name="id" value="' . $idRuangan . '" />
                        <button type="submit" class="btn-hapus">Hapus</button>
                    </form>
                </td>';
        }

        $barisTabelHtml .= '
            <tr>
                <td>' . $kodeRuangan . '</td>
                <td>' . $namaRuangan . '</td>
                <td>' . $namaGedung . '</td>
                <td>' . $kapasitas . ' orang</td>
                ' . $kolomAksiAdmin . '
            </tr>';
    }
}

// Muat template HTML
$html = file_get_contents(__DIR__ . "/daftar_ruangan.xhtml");

if ($html === false) {
    http_response_code(500);
    exit("Template daftar ruangan tidak ditemukan.");
}

// Tampilkan atau sembunyikan tombol + Tambah Ruangan khusus admin
if ($admin) {
    $html = str_replace(["<!-- ADMIN_START -->", "<!-- ADMIN_END -->"], "", $html);
} else {
    $html = preg_replace('/<!-- ADMIN_START -->.*?<!-- ADMIN_END -->/s', "", $html);
}

// Isi placeholder ke template HTML
$html = strtr($html, [
    "{{KOLOM_HEADER_ADMIN}}" => $kolomHeaderAdmin,
    "{{DAFTAR_BARIS}}" => $barisTabelHtml
]);

echo $html;