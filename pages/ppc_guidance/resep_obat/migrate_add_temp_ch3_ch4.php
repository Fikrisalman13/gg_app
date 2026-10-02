<?php
// Add Temp Ch 3 through Temp Ch 12 columns for multi-machine production data.
session_start();
require_once __DIR__ . '/../../../koneksi.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>Migration: Add Temp Ch 3 - Temp Ch 12</h2>";

$sqlParts = [];
for ($ch = 3; $ch <= 12; $ch++) {
    $column = 'temperature_ch' . $ch;
    $sqlParts[] = "
IF COL_LENGTH('dbo.resep_obat_machines', '$column') IS NULL
BEGIN
    ALTER TABLE dbo.resep_obat_machines ADD $column DECIMAL(10,2) NULL;
    PRINT 'Column $column added.';
END
ELSE
BEGIN
    PRINT 'Column $column already exists.';
END;";
}

$sql = implode("\n", $sqlParts);
$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    echo "<span style='color:red'>FAILED</span>";
    echo "<pre>" . print_r(sqlsrv_errors(), true) . "</pre>";
    exit;
}

echo "<span style='color:green'>SUCCESS</span><br>";
echo "<p>Kolom temperature_ch3 sampai temperature_ch12 sudah siap digunakan.</p>";
