<?php
// delete_ph_air.php - Hapus data PH Air berdasarkan Id
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
header('Content-Type: application/json');

$id = $_POST['id'] ?? '';
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Parameter tidak lengkap']);
    exit;
}
$sql = "DELETE FROM dbo.PH_Air WHERE Id = ?";
$params = [$id];
$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt) {
    echo json_encode(['success' => true]);
} else {
    $err = sqlsrv_errors();
    echo json_encode(['success' => false, 'error' => $err ? $err[0]['message'] : 'Gagal hapus']);
}
