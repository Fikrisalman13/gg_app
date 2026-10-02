<?php
session_start();
include '../../koneksi.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit(json_encode(['status' => 'error', 'message' => 'Invalid Request Method']));
if (!isset($_SESSION['UserName'])) exit(json_encode(['status' => 'error', 'message' => 'Sesi berakhir']));

$id = $_POST['id'] ?? '';
if (empty($id)) exit(json_encode(['status' => 'error', 'message' => 'ID tidak ditemukan.']));

if (sqlsrv_query($conn, "DELETE FROM planning_user_group WHERE id = ?", [$id])) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Hapus gagal.']);
}
?>
