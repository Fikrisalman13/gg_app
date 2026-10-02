<?php
// delete_ptco_air.php
header('Content-Type: application/json');
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

$response = ["success" => false];

if (!isset($_POST['id'])) {
    $response['error'] = 'ID tidak ditemukan.';
    echo json_encode($response);
    exit;
}

$id = intval($_POST['id']);
$sql = "DELETE FROM dbo.PTCO_Air WHERE Id = ?";
$stmt = sqlsrv_query($conn, $sql, [$id]);

if ($stmt) {
    $response['success'] = true;
} else {
    $err = sqlsrv_errors();
    $response['error'] = $err ? $err[0]['message'] : 'Gagal menghapus data PTCO.';
}

echo json_encode($response);
