<?php
// pages/resep_obat/master_artikel_grey/delete_artikel.php
session_start();
require_once __DIR__ . '/../../../../koneksi.php';

header('Content-Type: application/json');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$id = $_POST['id'] ?? '';

if (empty($id)) {
    echo json_encode(['status' => 'error', 'message' => 'ID tidak valid']);
    exit;
}

$sql = "DELETE FROM dbo.master_artikel_grey WHERE id = ?";
$stmt = sqlsrv_query($conn, $sql, [$id]);

if ($stmt === false) {
    echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus: ' . print_r(sqlsrv_errors(), true)]);
} else {
    echo json_encode(['status' => 'success', 'message' => 'Data berhasil dihapus']);
}
?>
