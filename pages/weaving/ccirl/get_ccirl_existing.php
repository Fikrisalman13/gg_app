<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/ccirl_helper.php');

function json_response($payload)
{
    echo json_encode($payload);
    exit;
}

if (!isset($_SESSION['UserName'])) json_response(['success' => false, 'message' => 'Silakan login terlebih dahulu.', 'data' => []]);
weaving_require($conn, 'CanAdd', true);

$tanggal = trim($_GET['tanggal'] ?? '');
$compressorNo = trim($_GET['compressor_no'] ?? '');

if ($tanggal === '' || !in_array($compressorNo, ccirl_no_options(), true)) {
    json_response(['success' => false, 'message' => 'Parameter tidak valid.', 'data' => []]);
}
if (!ccirl_table_exists($conn)) json_response(['success' => true, 'data' => [], 'keterangan' => '']);

$data = ccirl_get_cells($conn, $tanggal, $compressorNo);
$keterangan = ccirl_keterangan_for_sheet_query($conn, $tanggal, $compressorNo);
json_response(['success' => true, 'data' => $data, 'keterangan' => $keterangan]);

