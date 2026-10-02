<?php
// simpan_ph_air.php - Proses simpan data pH Air ke database
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
header('Content-Type: application/json');

// Validasi minimal
if (!isset($_POST['tanggal'], $_POST['pengecek'], $_POST['shift'])) {
    echo json_encode(['success' => false, 'error' => 'Data tidak lengkap']);
    exit;
}

// Ambil data
$tanggal = $_POST['tanggal'];
$pengecek = $_POST['pengecek'];
$shift = $_POST['shift'];
$created_by = $_SESSION['UserName'] ?? ($_POST['created_by'] ?? '');

// Bak air
$baks = [
    'PekatBesar', 'PekatKecil', 'Anoxit', 'Daff1', 'Daff2', 'Daff3',
    'Aerasi1', 'Aerasi2', 'Aerasi3', 'Aerasi4', 'SelokanPekat', 'SelokanReaktif', 'Sedimen', 'Outlet'
];

$params = [
    $tanggal, $pengecek, $shift
];
$fields = "Tanggal, Pengecek, Shift";
$values = "?, ?, ?";

foreach ($baks as $bak) {
    $fields .= ", $bak";
    $values .= ", ?";
    if (isset($_POST[$bak]) && $_POST[$bak] !== '') {
        $params[] = floatval($_POST[$bak]);
    } else {
        $params[] = null;
    }
}

// Tambahkan field EqualSum jika ada
if (isset($_POST['EqualSum']) && $_POST['EqualSum'] !== '') {
    $fields .= ", EqualSum";
    $values .= ", ?";
    $params[] = floatval($_POST['EqualSum']);
}

$fields .= ", CreatedBy, CreatedAt, UpdateAt";
$values .= ", ?, GETDATE(), GETDATE()";
$params[] = $created_by;

$sql = "INSERT INTO dbo.PH_Air ($fields) VALUES ($values)";
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt) {
    echo json_encode(['success' => true]);
} else {
    $err = sqlsrv_errors();
    echo json_encode(['success' => false, 'error' => $err ? $err[0]['message'] : 'Gagal simpan']);
}
