<?php
session_start();
include '../../koneksi.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    $group_name = $_POST['group_name'] ?? null;

    if ($id) {
        // Delete specific routing from group
        $sql = "DELETE FROM planning_trustee_group WHERE id = ?";
        sqlsrv_query($conn, $sql, [$id]);
    } elseif ($group_name) {
        // Delete entire group and its user mappings
        $sql1 = "DELETE FROM planning_trustee_group WHERE group_name = ?";
        sqlsrv_query($conn, $sql1, [$group_name]);
        
        $sql2 = "DELETE FROM planning_trustee_user_group WHERE group_name = ?";
        sqlsrv_query($conn, $sql2, [$group_name]);
    }

    echo json_encode(['status' => 'success']);
}
