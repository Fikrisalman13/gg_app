<?php
// update_tss_air.php - Proses update data TSS Air
header('Content-Type: application/json');
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

$id = $_POST['id'] ?? '';
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID tidak ditemukan']);
    exit;
}

$tanggal = $_POST['tanggal'] ?? null;
$pengecek = $_POST['pengecek'] ?? null;
$shift = $_POST['shift'] ?? null;
$username = $_SESSION['NamaLengkap'] ?? '';

// Bak fields
$fields = [
    'TSS_0', 'Pekat_Besar', 'Pekat_Kecil', 'Dwatring', 'Anoxit', 'Daff_1', 'Daff_2', 'Daff_3',
    'Sedimen', 'Equal', 'Outlet'
];

$values = [];
foreach ($fields as $f) {
    $values[$f] = $_POST[$f] ?? null;
}

// Build SQL

$sql = "UPDATE dbo.TSS_Air SET Tanggal = ?, Pengecek = ?, Shift = ?, " .
    implode(', ', array_map(function($f) { return "$f = ?"; }, $fields)) .
    ", UpdateAt = GETDATE() WHERE Id = ?";
$params = array_merge([
    $tanggal, $pengecek, $shift
], array_values($values), [$id]);

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt) {
    echo json_encode(['success' => true]);
} else {
    $err = sqlsrv_errors();
    echo json_encode(['success' => false, 'error' => 'Gagal update data', 'debug' => $err]);
}
