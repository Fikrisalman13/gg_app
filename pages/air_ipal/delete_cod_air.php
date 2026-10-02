<?php
// delete_cod_air.php
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

$sql = "DELETE FROM dbo.COD_air WHERE Id = ?";
$stmt = sqlsrv_query($conn, $sql, [$id]);

if ($stmt) {
    $response['success'] = true;
} else {
    $response['error'] = 'Gagal menghapus data COD.';
}

echo json_encode($response);
