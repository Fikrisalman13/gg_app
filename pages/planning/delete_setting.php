<?php
session_start();
include '../../koneksi.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
    exit;
}

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir, silakan login ulang.']);
    exit;
}

$id = $_POST['id'] ?? '';

if (empty($id)) {
    echo json_encode(['status' => 'error', 'message' => 'ID akses tidak ditemukan.']);
    exit;
}

$sqlDel = "DELETE FROM planning_setting WHERE id = ?";
$stmtDel = sqlsrv_query($conn, $sqlDel, [$id]);

if ($stmtDel) {
    echo json_encode(['status' => 'success', 'message' => 'Akses berhasil dihapus.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Hapus data gagal di database.']);
}
?>
