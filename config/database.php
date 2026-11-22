<?php
$host = "localhost";
$port = 3306; 
$nama_database = "campusspace";
$username = "root";
$password = "";

try {
    $koneksi = new PDO(
        "mysql:host=$host;port=$port;dbname=$nama_database;charset=utf8mb4",
        $username,      
        $password
    );

    $koneksi->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

} catch (PDOException $e) {
    error_log($e->getMessage());
    exit("Koneksi database gagal.". $e->getMessage());
}
?>
