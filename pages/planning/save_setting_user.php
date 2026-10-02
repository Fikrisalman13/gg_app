<?php
session_start();
include '../../koneksi.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit(json_encode(['status' => 'error', 'message' => 'Invalid Request Method']));
if (!isset($_SESSION['UserName'])) exit(json_encode(['status' => 'error', 'message' => 'Sesi berakhir, silakan login ulang.']));

$username = $_POST['username'] ?? '';
$group_name = strtoupper(trim($_POST['group_name'] ?? ''));
$createdBy = $_SESSION['UserName'];

if (empty($username) || empty($group_name)) exit(json_encode(['status' => 'error', 'message' => 'User dan Grup tidak boleh kosong.']));

// Cek duplicated
$sqlCek = "SELECT id FROM planning_user_group WHERE username = ? AND group_name = ?";
$stmtCek = sqlsrv_query($conn, $sqlCek, [$username, $group_name]);
if ($stmtCek && sqlsrv_has_rows($stmtCek)) exit(json_encode(['status' => 'error', 'message' => 'User tersebut sudah ada di grup ini.']));

$sqlInsert = "INSERT INTO planning_user_group (username, group_name, created_at, created_by) VALUES (?, ?, GETDATE(), ?)";
if (sqlsrv_query($conn, $sqlInsert, [$username, $group_name, $createdBy])) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan ke database.']);
}
?>
