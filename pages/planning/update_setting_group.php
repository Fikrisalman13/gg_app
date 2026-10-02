<?php
session_start();
include '../../koneksi.php';
header('Content-Type: application/json');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi Berakhir.']); exit;
}

$old_name = $_POST['old_group_name'] ?? '';
$new_name = strtoupper(trim($_POST['new_group_name'] ?? ''));
$routings = $_POST['rtg_name'] ?? []; // Ini adalah array nama routing

if (empty($old_name) || empty($new_name) || empty($routings)) {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap. Pilih minimal 1 routing.']); exit;
}

// Start transaction
sqlsrv_begin_transaction($conn);

// 1. Jika nama berubah, update mapping user
if ($old_name !== $new_name) {
    // Cek apakah nama baru sudah dipakai grup LAIN (kecuali grup ini sendiri)
    $sqlCheck = "SELECT TOP 1 group_name FROM planning_group_rtg WHERE group_name = ? AND group_name != ?";
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, [$new_name, $old_name]);
    if ($stmtCheck && sqlsrv_fetch_array($stmtCheck)) {
        sqlsrv_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => 'Nama grup baru sudah digunakan oleh grup lain.']); exit;
    }
    
    $sqlU = "UPDATE planning_user_group SET group_name = ? WHERE group_name = ?";
    sqlsrv_query($conn, $sqlU, [$new_name, $old_name]);
}

// 2. Berishkan routing lama baik di nama lama maupun baru (untuk sinkronisasi total)
$sqlDel = "DELETE FROM planning_group_rtg WHERE group_name = ? OR group_name = ?";
sqlsrv_query($conn, $sqlDel, [$old_name, $new_name]);

// 3. Masukkan routing baru dengan nama baru
$sqlIns = "INSERT INTO planning_group_rtg (group_name, rtg_name, created_by, created_at) VALUES (?, ?, ?, GETDATE())";
$success = true;
foreach ($routings as $rtg) {
    $stmtIns = sqlsrv_query($conn, $sqlIns, [$new_name, $rtg, $_SESSION['UserName']]);
    if (!$stmtIns) {
        $success = false;
        break;
    }
}

if ($success) {
    sqlsrv_commit($conn);
    echo json_encode(['status' => 'success']);
} else {
    sqlsrv_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => 'Gagal sinkronisasi data grup.']);
}
?>
