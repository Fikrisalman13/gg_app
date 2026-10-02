<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu!']);
    exit;
}

$ticket = $_POST['ticket'] ?? '';
if ($ticket === '') {
    echo json_encode(['success' => false, 'message' => 'Ticket wajib diisi.']);
    exit;
}

if (!isset($conn) || $conn === false) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal.']);
    exit;
}

sqlsrv_begin_transaction($conn);

$stmtTtd = sqlsrv_query($conn, "DELETE FROM dbo.Form_Pengajuan_Barang_TTD WHERE Ticket = ?", [$ticket]);
if ($stmtTtd === false) {
    sqlsrv_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus TTD: ' . print_r(sqlsrv_errors(), true)]);
    exit;
}

$stmt = sqlsrv_query($conn, "DELETE FROM dbo.Form_Serah_Terima_Aplikasi WHERE ticket = ?", [$ticket]);
if ($stmt === false) {
    sqlsrv_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus data: ' . print_r(sqlsrv_errors(), true)]);
    exit;
}

if (sqlsrv_rows_affected($stmt) <= 0) {
    sqlsrv_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan.']);
    exit;
}

sqlsrv_commit($conn);

echo json_encode(['success' => true, 'message' => 'Data berhasil dihapus.', 'ticket' => $ticket]);
