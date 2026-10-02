<?php
session_start();
include '../../koneksi.php';
header('Content-Type: application/json');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi Berakhir.']); exit;
}

$id         = $_POST['id'] ?? '';
$group_name = $_POST['group_name'] ?? '';

if (empty($id) || empty($group_name)) {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap.']); exit;
}

$sql = "UPDATE planning_user_group SET group_name = ? WHERE id = ?";
$stmt = sqlsrv_query($conn, $sql, [$group_name, $id]);

if ($stmt) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Gagal update database.']);
}
?>
