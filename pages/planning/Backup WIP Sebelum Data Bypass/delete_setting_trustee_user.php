<?php
session_start();
include '../../koneksi.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    if ($id) {
        $sql = "DELETE FROM planning_trustee_user_group WHERE id = ?";
        sqlsrv_query($conn, $sql, [$id]);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ID tidak ditemukan.']);
    }
}
