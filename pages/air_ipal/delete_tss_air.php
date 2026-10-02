<?php
// delete_tss_air.php - Hapus data TSS Air berdasarkan id
header('Content-Type: application/json');
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID tidak valid']);
    exit;
}

$sql = "DELETE FROM dbo.TSS_Air WHERE Id = ?";
$stmt = sqlsrv_query($conn, $sql, [$id]);
if ($stmt) {
    echo json_encode(['success' => true]);
} else {
    $err = sqlsrv_errors();
    echo json_encode(['success' => false, 'error' => $err ? $err[0]['message'] : 'Gagal hapus']);
}
