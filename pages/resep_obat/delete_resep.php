<?php
// pages/resep_obat/delete_resep.php
session_start();
require_once __DIR__ . '/../../koneksi.php';
header('Content-Type: application/json');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$id = $_POST['id'] ?? '';
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'ID Resep tidak valid']);
    exit;
}

// Transaction not strictly supported by sqlsrv_query without complexity, 
// but we will delete detail first then header.

// 1. Delete Details
$sqlDetail = "DELETE FROM dbo.resep_obat_detail WHERE id_resep = ?";
$stmtDetail = sqlsrv_query($conn, $sqlDetail, [$id]);

if ($stmtDetail === false) {
    echo json_encode(['status' => 'error', 'message' => 'Gagal hapus detail: ' . print_r(sqlsrv_errors(), true)]);
    exit;
}

// 2. Delete Header
$sqlHeader = "DELETE FROM dbo.resep_obat WHERE id = ?";
$stmtHeader = sqlsrv_query($conn, $sqlHeader, [$id]);

if ($stmtHeader === false) {
    echo json_encode(['status' => 'error', 'message' => 'Gagal hapus header: ' . print_r(sqlsrv_errors(), true)]);
    exit;
}

echo json_encode(['status' => 'success']);
?>
