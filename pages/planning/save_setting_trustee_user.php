<?php
session_start();
include '../../koneksi.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $group_name = $_POST['group_name'];
    $username = $_POST['username'];
    $user = $_SESSION['UserName'] ?? 'System';

    if (empty($group_name) || empty($username)) {
        echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap.']);
        exit;
    }

    // Check if already assigned to this group
    $sqlCheck = "SELECT id FROM planning_trustee_user_group WHERE username = ? AND group_name = ?";
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, [$username, $group_name]);
    if (sqlsrv_has_rows($stmtCheck)) {
        echo json_encode(['status' => 'error', 'message' => 'User sudah terdaftar di grup ini.']);
        exit;
    }

    $sqlInsert = "INSERT INTO planning_trustee_user_group (username, group_name, created_by, created_at) VALUES (?, ?, ?, GETDATE())";
    if (sqlsrv_query($conn, $sqlInsert, [$username, $group_name, $user])) {
        echo json_encode(['status' => 'success', 'message' => 'User berhasil dihubungkan ke grup.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data.']);
    }
}
