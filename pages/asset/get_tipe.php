<?php
session_start();
include '../../koneksi.php';

header('Content-Type: application/json');

if (!isset($_SESSION['UserName']) || !isset($_GET['id_merk'])) {
    echo json_encode([]);
    exit;
}

$id_merk = $_GET['id_merk'];
$sql = "SELECT id_tipe, nama_tipe FROM dbo.m_tipe WHERE id_merk = ? ORDER BY nama_tipe";
$stmt = sqlsrv_query($conn, $sql, [$id_merk]);
$tipe = [];

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $tipe[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

echo json_encode($tipe);
?>