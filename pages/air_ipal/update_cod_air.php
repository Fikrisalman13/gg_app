<?php
// update_cod_air.php - Proses update data COD Air
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
$kalibrasi = (isset($_POST['nilai_kalibrasi']) && $_POST['nilai_kalibrasi'] !== '') ? floatval($_POST['nilai_kalibrasi']) : null;

$bakList = [
    'PekatBesar', 'PekatKecil', 'Anoxit', 'Daff1', 'Daff2', 'Daff3',
    'Aerasi1', 'Aerasi2', 'Aerasi3', 'Aerasi4', 'SelokanPekat', 'SelokanReaktif', 'Dwatring', 'Sedimen', 'Outlet', 'EqualSum'
];

$set = "Tanggal = ?, Pengecek = ?, Shift = ?, NilaiKalibrasi = ?";
$params = [$tanggal, $pengecek, $shift, $kalibrasi];
foreach ($bakList as $bak) {
    $set .= ", $bak = ?";
    if (isset($_POST[$bak]) && $_POST[$bak] !== '') {
        $params[] = round(floatval($_POST[$bak]), 2);
    } else {
        $params[] = null;
    }
}
$set .= ", UpdateAt = GETDATE()";
$params[] = $id;

$sql = "UPDATE dbo.COD_air SET $set WHERE Id = ?";
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt) {
    echo json_encode(['success' => true]);
} else {
    $err = sqlsrv_errors();
    echo json_encode(['success' => false, 'error' => $err ? $err[0]['message'] : 'Gagal update']);
}
