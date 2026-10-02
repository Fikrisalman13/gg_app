<?php
$host = "103.93.129.3"; // Ganti dengan IP atau domain PostgreSQL Server
$port = "5432"; // Port default PostgreSQL
$dbname = "ERP_Crystal_SUM";
$username = "postgres"; // Username PostgreSQL
$password = "sapg172839-15034";

try {
    // Membuat koneksi PDO
    $conn3 = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password);

    // Set agar menampilkan error saat terjadi masalah
    $conn3->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    
} catch (PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}
?>
