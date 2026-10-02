<?php
// pages/resep_obat/delete_resep.php
session_start();
require_once __DIR__ . '/../../../koneksi.php';
header('Content-Type: application/json');

if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Sesi login telah berakhir.']);
    exit;
}

$permissionStatement = sqlsrv_query(
    $conn,
    'SELECT CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?',
    [$_SESSION['GroupId'] ?? 0, 215]
);
$permission = $permissionStatement
    ? sqlsrv_fetch_array($permissionStatement, SQLSRV_FETCH_ASSOC)
    : null;
if ((int) ($permission['CanDelete'] ?? 0) !== 1) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki hak untuk menghapus resep.']);
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(422);
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
