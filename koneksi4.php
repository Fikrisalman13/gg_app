<?php
// Konfigurasi koneksi SQL Server
$serverName = "192.168.7.233,54955"; // Ganti dengan server SQL Server Anda
$connectionOptions = array(
    "Database" => "SuryaUsaha", // Ganti dengan nama database Anda
    "Uid" => "sa",           // Ganti dengan username SQL Server Anda
    "PWD" => "GRZ@Bandung93", // Ganti dengan password SQL Server Anda
    "TrustServerCertificate" => true
);

// Membuat koneksi
$conn4 = sqlsrv_connect($serverName, $connectionOptions);

// Mengecek koneksi
if (!$conn4) {
    die(print_r(sqlsrv_errors(), true));
}

