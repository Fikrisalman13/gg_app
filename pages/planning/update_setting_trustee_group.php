<?php
session_start();
include '../../koneksi.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $old_group_name = $_POST['old_group_name'];
    $new_group_name = strtoupper(trim($_POST['new_group_name']));
    $rtg_data = $_POST['rtg_data'] ?? [];
    $user = $_SESSION['UserName'] ?? 'System';

    if (empty($old_group_name) || empty($new_group_name) || empty($rtg_data)) {
        echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap.']);
        exit;
    }

    // 1. Update Group Name in all related tables if changed
    if ($old_group_name != $new_group_name) {
        $sqlUpdateUser = "UPDATE planning_trustee_user_group SET group_name = ?, updated_by = ?, updated_at = GETDATE() WHERE group_name = ?";
        sqlsrv_query($conn, $sqlUpdateUser, [$new_group_name, $user, $old_group_name]);
    }

    // 2. Full Sync Routings for the group
    // For simplicity: delete and re-insert (common pattern for many-to-many style updates)
    $sqlDelete = "DELETE FROM planning_trustee_group WHERE group_name = ?";
    sqlsrv_query($conn, $sqlDelete, [$old_group_name]);

    foreach ($rtg_data as $rd) {
        $parts = explode('|', $rd);
        if (count($parts) == 3) {
            $msid = $parts[0];
            $code = $parts[1];
            $name = $parts[2];

            $sqlInsert = "INSERT INTO planning_trustee_group (group_name, rtgmsid, rtgcode, rtgname, created_by, created_at, updated_by, updated_at) VALUES (?, ?, ?, ?, ?, GETDATE(), ?, GETDATE())";
            sqlsrv_query($conn, $sqlInsert, [$new_group_name, $msid, $code, $name, $user, $user]);
        }
    }

    echo json_encode(['status' => 'success']);
}
