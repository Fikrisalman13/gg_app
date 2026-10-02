<?php
// update_ph_air.php - Proses update data PH Air
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
header('Content-Type: application/json');

if (!isset($_POST['id'], $_POST['tanggal'], $_POST['pengecek'], $_POST['shift'])) {
    echo json_encode(['success' => false, 'error' => 'Data tidak lengkap']);
    exit;
}

$id = $_POST['id'];
$tanggal = $_POST['tanggal'];
$pengecek = $_POST['pengecek'];
$shift = $_POST['shift'];

$baks = [
    'PekatBesar', 'PekatKecil', 'Anoxit', 'Daff1', 'Daff2', 'Daff3',
    'Aerasi1', 'Aerasi2', 'Aerasi3', 'Aerasi4', 'SelokanPekat', 'SelokanReaktif', 'Sedimen', 'Outlet',
    'EqualSum'
];

$set = "Tanggal = ?, Pengecek = ?, Shift = ?";
$params = [$tanggal, $pengecek, $shift];
foreach ($baks as $bak) {
    $set .= ", $bak = ?";
    if (isset($_POST[$bak]) && $_POST[$bak] !== '') {
        $params[] = floatval($_POST[$bak]);
    } else {
        $params[] = null;
    }
}
$set .= ", UpdateAt = GETDATE()";
$params[] = $id;

$sql = "UPDATE dbo.PH_Air SET $set WHERE Id = ?";
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt) {
    echo json_encode(['success' => true]);
} else {
    $err = sqlsrv_errors();
    echo json_encode(['success' => false, 'error' => $err ? $err[0]['message'] : 'Gagal update']);
}
