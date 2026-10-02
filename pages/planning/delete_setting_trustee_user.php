<?php
session_start();
include '../../koneksi.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    $username = $_POST['username'] ?? null;

    if ($id) {
        $sql = "DELETE FROM planning_trustee_user_group WHERE id = ?";
        sqlsrv_query($conn, $sql, [$id]);
        echo json_encode(['status' => 'success', 'message' => 'Akses berhasil dihapus.']);
    } elseif ($username) {
        $sql = "DELETE FROM planning_trustee_user_group WHERE username = ?";
        sqlsrv_query($conn, $sql, [$username]);
        echo json_encode(['status' => 'success', 'message' => 'Semua akses untuk user tersebut berhasil dihapus.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ID atau Username tidak ditemukan.']);
    }
}
