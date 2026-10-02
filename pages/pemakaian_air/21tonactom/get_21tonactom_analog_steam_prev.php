<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

$tanggal = trim($_GET['tanggal'] ?? '');
if ($tanggal === '') {
    echo json_encode(['success' => false, 'message' => 'Tanggal tidak valid.']);
    exit;
}

$sql = "SELECT TOP 1 ahir FROM dbo.analog_steam_actom WHERE tanggal < ? ORDER BY tanggal DESC, id DESC";
$stmt = sqlsrv_query($conn, $sql, [$tanggal]);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil data sebelumnya.']);
    exit;
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode([
    'success' => true,
    'analog_steam_akhir' => $row['ahir'] ?? null
]);
