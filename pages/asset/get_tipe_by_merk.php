<?php
session_start();
include '../../koneksi.php';

header('Content-Type: text/html');

if (!isset($_POST['id_merk'])) {
    die('<option value="">-- Pilih Tipe --</option>');
}

$idMerk = $_POST['id_merk'];

// Ambil data tipe berdasarkan merk
$sql = "SELECT id_tipe, nama_tipe FROM dbo.m_tipe WHERE id_merk = ? ORDER BY nama_tipe";
$params = [$idMerk];
$stmt = sqlsrv_query($conn, $sql, $params);

$options = '<option value="">-- Pilih Tipe --</option>';
if ($stmt !== false) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $options .= sprintf(
            '<option value="%s">%s</option>',
            htmlspecialchars($row['id_tipe']),
            htmlspecialchars($row['nama_tipe'])
        );
    }
    sqlsrv_free_stmt($stmt);
}

echo $options;
?>