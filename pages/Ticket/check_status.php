<?php
// ===================================================
// 1. INISIALISASI KONEKSI DATABASE
// ===================================================
require_once __DIR__ . '/../../koneksi.php';

// ===================================================
// 2. CETAK DAFTAR STATUS UNTUK DEBUG/VERIFIKASI
// ===================================================
echo "=== Status List ===\n";
$sql = "SELECT * FROM dbo.m_status";
$stmt = sqlsrv_query($conn, $sql);
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    echo "ID: " . $row['id_status'] . " | Name: " . $row['nama_status'] . "\n";
}
?>
