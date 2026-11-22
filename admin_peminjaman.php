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

    if (!is_string($token)) {
        http_response_code(403);
        exit("Permintaan tidak valid.");
    }

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
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

    if (!$id) {
        $pesan = "Data permintaan tidak valid.";
        $tipePesan = "error";
    } elseif (!in_array($aksi, ["approved", "rejected"], true)) {
        $pesan = "Aksi persetujuan tidak valid.";
        $tipePesan = "error";
    } elseif (strlen($catatan) > 1000) {
        $pesan = "Catatan admin maksimal 1000 karakter.";
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

            if (!$data) {
                throw new RuntimeException(
                    "Pengajuan tidak ditemukan."
                );
            }

            if ($data["status_peminjaman"] !== "pending") {
                throw new RuntimeException(
                    "Pengajuan sudah diproses atau berubah."
                );
            }

            if ((int)$data["id_ruangan"] !== (int)$idRuangan) {
                throw new RuntimeException(
                    "Data ruangan pengajuan tidak sesuai."
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

if (isset($_GET["hasil"]) && $_GET["hasil"] === "berhasil") {
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

$csrfToken = aman($_SESSION["csrf_token"]);

// Susun baris tabel HTML
$barisTabelHtml = "";

if (empty($daftar)) {
    $barisTabelHtml = '<tr><td colspan="9">Belum ada peminjaman pada kategori ini.</td></tr>';
} else {
    foreach ($daftar as $p) {
        $namaPeminjam = aman($p["nama_lengkap"]);
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
        $idPeminjaman = (int)$p["id_peminjaman"];

        if ($statusRaw === "pending") {
            $kolomAksi = '
                <form method="post" action="admin_peminjaman.php">
                    <input type="hidden" name="csrf_token" value="' . $csrfToken . '" />
                    <input type="hidden" name="id_peminjaman" value="' . $idPeminjaman . '" />
                    <textarea name="catatan_admin" rows="2" cols="20" maxlength="1000" placeholder="Catatan admin"></textarea>
                    <div class="grup-tombol-aksi">
                        <button class="aksi-btn setuju" type="submit" name="aksi" value="approved">Setujui</button>
                        <button class="aksi-btn tolak" type="submit" name="aksi" value="rejected">Tolak</button>
                    </div>
                </form>';
        } else {
            $kolomAksi = aman($p["catatan_admin"] ?: "-");
        }

        $barisTabelHtml .= '
            <tr>
                <td>' . $namaPeminjam . '</td>
                <td>' . $namaRuangan . '</td>
                <td>' . $namaGedung . '</td>
                <td>' . $tanggal . '</td>
                <td>' . $jamMulai . ' - ' . $jamSelesai . '</td>
                <td>' . $keperluan . '</td>
                <td>' . $jumlahPeserta . ' orang</td>
                <td><span class="status-badge ' . $statusKelas . '">' . $statusLabel . '</span></td>
                <td>' . $kolomAksi . '</td>
            </tr>';
    }
}

// Muat template HTML
$html = file_get_contents(__DIR__ . "/admin_peminjaman.xhtml");

if ($html === false) {
    http_response_code(500);
    exit("Template admin peminjaman tidak ditemukan.");
}

// Tampilkan atau hapus blok pesan notifikasi
if ($pesan !== "") {
    $html = str_replace(["<!-- PESAN_START -->", "<!-- PESAN_END -->"], "", $html);
} else {
    $html = preg_replace('/<!-- PESAN_START -->.*?<!-- PESAN_END -->/s', "", $html);
}

// Isi placeholder ke template HTML
$html = strtr($html, [
    "{{CSRF_TOKEN}}" => $csrfToken,
    "{{PESAN}}" => aman($pesan),
    "{{KELAS_PESAN}}" => $tipePesan === "error" ? "error" : "",
    "{{AKTIF_SEMUA}}" => $filterAktif === "semua" ? "aktif" : "",
    "{{AKTIF_PENDING}}" => $filterAktif === "pending" ? "aktif" : "",
    "{{AKTIF_APPROVED}}" => $filterAktif === "approved" ? "aktif" : "",
    "{{AKTIF_REJECTED}}" => $filterAktif === "rejected" ? "aktif" : "",
    "{{AKTIF_CANCELLED}}" => $filterAktif === "cancelled" ? "aktif" : "",
    "{{JML_SEMUA}}" => (string)$jumlahStatus["semua"],
    "{{JML_PENDING}}" => (string)$jumlahStatus["pending"],
    "{{JML_APPROVED}}" => (string)$jumlahStatus["approved"],
    "{{JML_REJECTED}}" => (string)$jumlahStatus["rejected"],
    "{{JML_CANCELLED}}" => (string)$jumlahStatus["cancelled"],
    "{{DAFTAR_BARIS}}" => $barisTabelHtml
]);

echo $html;