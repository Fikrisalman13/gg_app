<?php
session_start();
include '../../koneksi.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $group_name = $_POST['group_name'];
    $user = $_SESSION['UserName'] ?? 'System';

    if (empty($id) || empty($group_name)) {
        echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap.']);
        exit;
    }

    $sql = "UPDATE planning_trustee_user_group SET group_name = ?, updated_by = ?, updated_at = GETDATE() WHERE id = ?";
    if (sqlsrv_query($conn, $sql, [$group_name, $user, $id])) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui data.']);
    }
}
