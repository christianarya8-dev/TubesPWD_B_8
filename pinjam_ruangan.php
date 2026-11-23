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

    if (!is_string($token)) {
        http_response_code(403);
        exit("Permintaan tidak valid.");
    }

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
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

    // Simpan input sementara
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

    if (!$idRuangan) {
        $pesan = "Silakan pilih ruangan terlebih dahulu.";
    } elseif (!$tanggalValid) {
        $pesan = "Format tanggal tidak valid.";
    } elseif ($tanggal < date("Y-m-d")) {
        $pesan = "Tanggal peminjaman tidak boleh di masa lalu.";
    } elseif (!$jamValid) {
        $pesan = "Format jam mulai atau jam selesai tidak valid.";
    } elseif ($mulai >= $selesai) {
        $pesan = "Jam selesai harus lebih besar dari jam mulai.";
    } elseif ($keperluan === "") {
        $pesan = "Keperluan peminjaman wajib diisi.";
    } elseif (strlen($keperluan) > 255) {
        $pesan = "Keperluan maksimal 255 karakter.";
    } elseif (!$peserta) {
        $pesan = "Jumlah peserta tidak valid.";
    } elseif ($peserta < 1) {
        $pesan = "Jumlah peserta minimal 1 orang.";
    } else {
        try {
            $koneksi->beginTransaction();

            // Kunci baris ruangan agar peminjaman bersamaan diperiksa berurutan
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
                    "Ruangan sedang tidak tersedia."
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
            $pesan = "Pengajuan berhasil! Menunggu persetujuan admin.";

            // Kosongkan input setelah berhasil tersimpan
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

$daftarRuangan = $koneksi->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Susun opsi dropdown ruangan
$opsiRuanganHtml = "";
foreach ($daftarRuangan as $r) {
    $idItem = (int)$r["id_ruangan"];
    $namaRuanganItem = aman($r["nama_ruangan"]);
    $namaGedungItem = aman($r["nama_gedung"]);
    $kapasitasItem = (int)$r["kapasitas"];
    $selectedAttr = ((string)$idItem === $idRuanganLama) ? ' selected="selected"' : "";

    $opsiRuanganHtml .= '<option value="' . $idItem . '"' . $selectedAttr . '>'
        . $namaRuanganItem . " - " . $namaGedungItem . " (" . $kapasitasItem . " orang)"
        . "</option>\n";
}

// Muat template HTML
$html = file_get_contents(__DIR__ . "/pinjam_ruangan.xhtml");

if ($html === false) {
    http_response_code(500);
    exit("Template peminjaman ruangan tidak ditemukan.");
}

// Tampilkan atau hapus blok pesan notifikasi
if ($pesan !== "") {
    $html = str_replace(["<!-- PESAN_START -->", "<!-- PESAN_END -->"], "", $html);
} else {
    $html = preg_replace('/<!-- PESAN_START -->.*?<!-- PESAN_END -->/s', "", $html);
}

// Isi placeholder ke template HTML
$html = strtr($html, [
    "{{PESAN}}" => aman($pesan),
    "{{KELAS_PESAN}}" => $berhasil ? "sukses" : "error",
    "{{CSRF_TOKEN}}" => aman($_SESSION["csrf_token"]),
    "{{OPSI_RUANGAN}}" => $opsiRuanganHtml,
    "{{TANGGAL_MIN}}" => date("Y-m-d"),
    "{{TANGGAL_LAMA}}" => aman($tanggalLama),
    "{{MULAI_LAMA}}" => aman($mulaiLama),
    "{{SELESAI_LAMA}}" => aman($selesaiLama),
    "{{KEPERLUAN_LAMA}}" => aman($keperluanLama),
    "{{PESERTA_LAMA}}" => aman($pesertaLama)
]);

echo $html;