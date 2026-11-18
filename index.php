<?php
session_start();

// Jika pengguna sudah login, masuk ke dashboard
if (isset($_SESSION["id_pengguna"])) {
    header("Location: dashboard.php");
    exit;
}

// Jika belum login, arahkan ke halaman login
header("Location: login.php");
exit;
