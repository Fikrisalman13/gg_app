<?php
// delete_turbidity_ipab.php - Hapus data Turbidity IPAB
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
header('Content-Type: application/json');

$id = $_POST['id'] ?? '';
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID tidak valid']);
    exit;
}

$sql = "DELETE FROM dbo.turbidity_ipab WHERE Id = ?";
$params = [$id];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt) {
    echo json_encode(['success' => true]);
} else {
    $err = sqlsrv_errors();
    echo json_encode(['success' => false, 'error' => $err ? $err[0]['message'] : 'Gagal menghapus data']);
}
