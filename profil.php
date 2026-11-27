<?php
session_start();
require_once __DIR__ . "/config/database.php";

// Wajib login
if (!isset($_SESSION["id_pengguna"])) {
    header("Location: login.php");
    exit;
}

function aman($teks) {
    return htmlspecialchars((string)$teks, ENT_QUOTES, "UTF-8");
}

function buatAvatarDefault($nama) {
    $kata = preg_split('/\s+/', trim((string)$nama));
    $inisial = "";
    foreach ($kata as $bagian) {
        if ($bagian !== "") {
            $inisial .= strtoupper(substr($bagian, 0, 1));
            if (strlen($inisial) >= 2) {
                break;
            }
        }
    }
    if ($inisial === "") {
        $inisial = "CS";
    }

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="160" viewBox="0 0 160 160">'
        . '<rect width="160" height="160" rx="80" fill="#171717"/>'
        . '<text x="80" y="92" font-family="Arial, sans-serif" font-size="52" font-weight="bold" '
        . 'fill="#ffffff" text-anchor="middle">' . htmlspecialchars($inisial, ENT_XML1, "UTF-8") . '</text>'
        . '</svg>';

    return "data:image/svg+xml;charset=UTF-8," . rawurlencode($svg);
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$id = (int)$_SESSION["id_pengguna"];
$pesan = "";
$berhasil = false;

// Ambil data pengguna dari MySQL
$stmt = $koneksi->prepare(
    "SELECT id_pengguna, nama_lengkap, email,
            peran, nomor_identitas, no_telepon, foto_profil
     FROM pengguna
     WHERE id_pengguna = ?"
);
$stmt->execute([$id]);
$pengguna = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pengguna) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Ketika form disimpan
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) || !hash_equals($_SESSION["csrf_token"], $token)) {
        http_response_code(403);
        exit("Token tidak valid.");
    }

    $nama = trim($_POST["nama_lengkap"] ?? "");
    $identitas = trim($_POST["nomor_identitas"] ?? "");
    $telepon = trim($_POST["no_telepon"] ?? "");
    $hapusFoto = isset($_POST["hapus_foto"]) && $_POST["hapus_foto"] === "1";
    $fotoBaruDb = $pengguna["foto_profil"] ?? null;
    $errorUpload = "";

    // Proses upload foto profil baru jika dipilih
    if (isset($_FILES["foto_profil"]) && $_FILES["foto_profil"]["error"] !== UPLOAD_ERR_NO_FILE) {
        $fileFoto = $_FILES["foto_profil"];

        if ($fileFoto["error"] !== UPLOAD_ERR_OK) {
            $errorUpload = "Gagal mengunggah foto profil. Silakan coba lagi.";
        } elseif ($fileFoto["size"] > 2 * 1024 * 1024) {
            $errorUpload = "Ukuran foto profil maksimal 2 MB.";
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $fileFoto["tmp_name"]);
            finfo_close($finfo);

            $ekstensiDiizinkan = [
                "image/jpeg" => "jpg",
                "image/png" => "png",
                "image/webp" => "webp"
            ];

            if (!isset($ekstensiDiizinkan[$mime]) || @getimagesize($fileFoto["tmp_name"]) === false) {
                $errorUpload = "Format foto harus berupa JPG, PNG, atau WEBP.";
            } else {
                $dirUpload = __DIR__ . "/uploads/profil";
                if (!is_dir($dirUpload) && !mkdir($dirUpload, 0755, true)) {
                    $errorUpload = "Folder penyimpanan foto gagal disiapkan.";
                } else {
                    $namaFileBaru = "profil_" . $id . "_" . bin2hex(random_bytes(8)) . "." . $ekstensiDiizinkan[$mime];
                    $tujuanPenuh = $dirUpload . "/" . $namaFileBaru;

                    if (!move_uploaded_file($fileFoto["tmp_name"], $tujuanPenuh)) {
                        $errorUpload = "Gagal menyimpan file foto profil.";
                    } else {
                        // Hapus foto lama jika ada
                        if (!empty($pengguna["foto_profil"])) {
                            $pathLama = __DIR__ . "/" . ltrim($pengguna["foto_profil"], "/");
                            if (is_file($pathLama)) {
                                @unlink($pathLama);
                            }
                        }
                        $fotoBaruDb = "uploads/profil/" . $namaFileBaru;
                        $hapusFoto = false;
                    }
                }
            }
        }
    } elseif ($hapusFoto && !empty($pengguna["foto_profil"])) {
        $pathLama = __DIR__ . "/" . ltrim($pengguna["foto_profil"], "/");
        if (is_file($pathLama)) {
            @unlink($pathLama);
        }
        $fotoBaruDb = null;
    }

    if ($errorUpload !== "") {
        $pesan = $errorUpload;
    } elseif ($nama === "") {
        $pesan = "Nama lengkap wajib diisi.";
    } elseif (strlen($nama) > 100) {
        $pesan = "Nama lengkap maksimal 100 karakter.";
    } elseif (strlen($identitas) > 30) {
        $pesan = "Nomor identitas maksimal 30 karakter.";
    } elseif (strlen($telepon) > 20) {
        $pesan = "Nomor telepon maksimal 20 karakter.";
    } else {
        $update = $koneksi->prepare(
            "UPDATE pengguna
             SET nama_lengkap = ?,
                 nomor_identitas = ?,
                 no_telepon = ?,
                 foto_profil = ?
             WHERE id_pengguna = ?"
        );

        $update->execute([
            $nama,
            $identitas !== "" ? $identitas : null,
            $telepon !== "" ? $telepon : null,
            $fotoBaruDb,
            $id
        ]);

        $_SESSION["nama_lengkap"] = $nama;
        $berhasil = true;
        $pesan = "Profil dan foto berhasil diperbarui!";

        // Ambil data terbaru
        $stmt->execute([$id]);
        $pengguna = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Tentukan URL foto profil yang akan ditampilkan
$adaFotoTersimpan = false;
$fotoUrl = buatAvatarDefault($pengguna["nama_lengkap"]);

if (!empty($pengguna["foto_profil"])) {
    $relPath = ltrim((string)$pengguna["foto_profil"], "/");
    $fullPath = __DIR__ . "/" . $relPath;
    if (is_file($fullPath)) {
        $fotoUrl = $relPath . "?v=" . filemtime($fullPath);
        $adaFotoTersimpan = true;
    }
}

// Muat template HTML
$html = file_get_contents(__DIR__ . "/profil.xhtml");

if ($html === false) {
    http_response_code(500);
    exit("Template profil tidak ditemukan.");
}

// Tampilkan atau hapus blok pesan notifikasi
if ($pesan !== "") {
    $html = str_replace(["<!-- PESAN_START -->", "<!-- PESAN_END -->"], "", $html);
} else {
    $html = preg_replace('/<!-- PESAN_START -->.*?<!-- PESAN_END -->/s', "", $html);
}

// Tampilkan opsi hapus foto hanya jika user memiliki foto tersimpan
if ($adaFotoTersimpan) {
    $html = str_replace(["<!-- HAPUS_FOTO_START -->", "<!-- HAPUS_FOTO_END -->"], "", $html);
} else {
    $html = preg_replace('/<!-- HAPUS_FOTO_START -->.*?<!-- HAPUS_FOTO_END -->/s', "", $html);
}

// Isi placeholder dengan data pengguna yang sudah diamankan
$html = strtr($html, [
    "{{PESAN}}" => aman($pesan),
    "{{KELAS_PESAN}}" => $berhasil ? "pesan" : "error",
    "{{FOTO_PROFIL}}" => aman($fotoUrl),
    "{{EMAIL}}" => aman($pengguna["email"]),
    "{{PERAN}}" => aman(ucfirst((string)$pengguna["peran"])),
    "{{CSRF_TOKEN}}" => aman($_SESSION["csrf_token"]),
    "{{NAMA_LENGKAP}}" => aman($pengguna["nama_lengkap"]),
    "{{NOMOR_IDENTITAS}}" => aman($pengguna["nomor_identitas"] ?? ""),
    "{{NO_TELEPON}}" => aman($pengguna["no_telepon"] ?? "")
]);

echo $html;
