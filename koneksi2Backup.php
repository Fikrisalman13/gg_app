<?php
// Konfigurasi koneksi SQL Server
$serverName = "202.150.136.51"; // Ganti dengan server SQL Server Anda
$connectionOptions = array(
    "Database" => "SUM", // Ganti dengan nama database Anda
    "Uid" => "sa",           // Ganti dengan username SQL Server Anda
    "PWD" => "rahasiaIT2020", // Ganti dengan password SQL Server Anda
    "TrustServerCertificate" => true
);

// Membuat koneksi
$conn2 = sqlsrv_connect($serverName, $connectionOptions);

// Mengecek koneksi
if (!$conn2) {
    die(print_r(sqlsrv_errors(), true));
}
