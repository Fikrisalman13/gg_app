<?php
session_start();
include '../../koneksi.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit(json_encode(['status' => 'error', 'message' => 'Invalid Request Method']));
if (!isset($_SESSION['UserName'])) exit(json_encode(['status' => 'error', 'message' => 'Sesi berakhir, silakan login ulang.']));

$group_name = strtoupper(trim($_POST['group_name'] ?? ''));
$rtg_names = $_POST['rtg_name'] ?? [];
$createdBy = $_SESSION['UserName'];

if (empty($group_name) || empty($rtg_names) || !is_array($rtg_names)) exit(json_encode(['status' => 'error', 'message' => 'Grup dan Routing tidak boleh kosong.']));

$successCount = 0;
foreach ($rtg_names as $rtg_name) {
    $rtg_name = trim($rtg_name);
    if ($rtg_name === '') continue;

    // Cek duplicated
    $sqlCek = "SELECT id FROM planning_group_rtg WHERE group_name = ? AND rtg_name = ?";
    $stmtCek = sqlsrv_query($conn, $sqlCek, [$group_name, $rtg_name]);
    if ($stmtCek && sqlsrv_has_rows($stmtCek)) continue;

    $sqlInsert = "INSERT INTO planning_group_rtg (group_name, rtg_name, created_at, created_by) VALUES (?, ?, GETDATE(), ?)";
    if (sqlsrv_query($conn, $sqlInsert, [$group_name, $rtg_name, $createdBy])) {
        $successCount++;
    }
}

if ($successCount > 0) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan, atau semua routing tersebut sudah ada di grup ini.']);
}
?>
