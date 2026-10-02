<?php
// Konfigurasi koneksi SQL Server
$serverName = "192.168.7.184"; // Ganti dengan server SQL Server Anda
$connectionOptions = array(
    "Database" => "GG", // Ganti dengan nama database Anda
    "Uid" => "sa",           // Ganti dengan username SQL Server Anda
    "PWD" => "@sempreperte2003", // Ganti dengan password SQL Server Anda
    //"CharacterSet" => "UTF-8", // agar semua hasil query UTF-8
    "TrustServerCertificate" => true

);

// Membuat koneksi
$conn = sqlsrv_connect($serverName, $connectionOptions);

// Mengecek koneksi
if (!$conn) {
    die(print_r(sqlsrv_errors(), true));
}
