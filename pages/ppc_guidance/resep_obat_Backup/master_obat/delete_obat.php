<?php
// pages/resep_obat/master_obat/delete_obat.php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../../koneksi.php';
header('Content-Type: application/json');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$id = $_POST['id'] ?? '';
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'ID missing']);
    exit;
}

$sql = "DELETE FROM dbo.resep_master_obat WHERE id = ?";
$stmt = sqlsrv_query($conn, $sql, [$id]);

if ($stmt === false) {
    echo json_encode(['status' => 'error', 'message' => print_r(sqlsrv_errors(), true)]);
} else {
    echo json_encode(['status' => 'success']);
}
?>
