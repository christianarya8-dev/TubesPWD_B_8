# Tubes PWD B_8 — CampusSpace

Sistem peminjaman ruangan kampus berbasis PHP dan MySQL.

## Teknologi
- PHP Native
- MySQL / MariaDB
- XAMPP
- HTML, CSS, JavaScript

## Instalasi Lokal

1. Instal XAMPP.
2. Jalankan Apache dan MySQL.
3. Download atau clone repository ini.
4. Letakkan folder proyek di C:\xampp\htdocs.
5. Ubah nama folder proyek menjadi campusspace.
6. Buka http://localhost/phpmyadmin.
7. Buat database kosong bernama campusspace.
8. Import database/campusspace_siap_import.sql.
9. Periksa koneksi pada config/database.php.
10. Buka http://localhost/campusspace/.
11. Daftarkan akun menggunakan halaman registrasi.
12. Login untuk mengakses dashboard.

## Struktur Database

Database memiliki enam tabel:
- pengguna
- gedung
- ruangan
- fasilitas
- fasilitas_ruangan
- peminjaman

## Fitur
- Registrasi dan login
- Profil pengguna
- Daftar ruangan
- Pengajuan peminjaman
- Riwayat peminjaman
- Edit dan pembatalan peminjaman
- Pengelolaan ruangan oleh admin
- Persetujuan dan penolakan peminjaman

## Catatan Pengembangan
- Gunakan data contoh untuk pengujian.
- Jangan mengunggah akun pribadi atau password.
- Semua anggota perlu menggunakan struktur database yang sama.
- Perubahan kode dilakukan melalui branch dan pull request.
