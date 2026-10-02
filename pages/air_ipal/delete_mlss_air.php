<?php
// delete_mlss_air.php
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
header('Content-Type: application/json');
$id = $_POST['id'] ?? '';
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID tidak ditemukan.']);
    exit;
}
$sql = "DELETE FROM MLSS_Air WHERE Id = ?";
$stmt = sqlsrv_query($conn, $sql, [$id]);
if ($stmt) {
    echo json_encode(['success' => true]);
} else {
    $errors = sqlsrv_errors();
    echo json_encode(['success' => false, 'error' => $errors ? $errors[0]['message'] : 'Gagal menghapus data.']);
}
