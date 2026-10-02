<?php
require_once dirname(dirname(__DIR__)) . '/koneksi.php';

echo "Running migration..." . PHP_EOL;
// 1. Add jenis_pengajuan
$sql1 = "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Form_Umum_Buka_Tanggal_Closingan' AND COLUMN_NAME = 'jenis_pengajuan')
BEGIN
    ALTER TABLE dbo.Form_Umum_Buka_Tanggal_Closingan ADD jenis_pengajuan VARCHAR(20) NULL;
    PRINT 'jenis_pengajuan added';
END";
$stmt1 = sqlsrv_query($conn, $sql1);
if ($stmt1) { echo "jenis_pengajuan: OK" . PHP_EOL; } else { echo "jenis_pengajuan ERROR" . PHP_EOL; print_r(sqlsrv_errors()); }

// 2. Add is_revisi_harga
$sql2 = "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Form_Umum_Buka_Tanggal_Closingan' AND COLUMN_NAME = 'is_revisi_harga')
BEGIN
    ALTER TABLE dbo.Form_Umum_Buka_Tanggal_Closingan ADD is_revisi_harga INT DEFAULT 0;
    PRINT 'is_revisi_harga added';
END";
$stmt2 = sqlsrv_query($conn, $sql2);
if ($stmt2) { echo "is_revisi_harga: OK" . PHP_EOL; } else { echo "is_revisi_harga ERROR" . PHP_EOL; print_r(sqlsrv_errors()); }

echo "Migration done." . PHP_EOL;
