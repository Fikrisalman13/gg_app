<?php
session_start();
include '../../koneksi.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit(json_encode(['status' => 'error', 'message' => 'Invalid Request Method']));
if (!isset($_SESSION['UserName'])) exit(json_encode(['status' => 'error', 'message' => 'Sesi berakhir']));

$id = $_POST['id'] ?? '';
$group = $_POST['group_name'] ?? '';

if (empty($id) && empty($group)) exit(json_encode(['status' => 'error', 'message' => 'Parameter tidak lengkap.']));

$success = false;
if (!empty($id)) {
    $success = sqlsrv_query($conn, "DELETE FROM planning_group_rtg WHERE id = ?", [$id]);
} elseif (!empty($group)) {
    // Cascade delete: hapus grup dan penugasan usernya
    $success = sqlsrv_query($conn, "DELETE FROM planning_group_rtg WHERE group_name = ?", [$group]);
    sqlsrv_query($conn, "DELETE FROM planning_user_group WHERE group_name = ?", [$group]);
}

if ($success) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Hapus gagal.']);
}
?>
