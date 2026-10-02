<?php
// update_dh_ipab.php - Proses update data DH IPAB
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

$shiftMap = [
    '1' => 'Pagi',
    '2' => 'Siang',
    '3' => 'Malam'
];
if (isset($shiftMap[$shift])) {
    $shift = $shiftMap[$shift];
}
if (!in_array($shift, ['Pagi', 'Siang', 'Malam'], true)) {
    echo json_encode(['success' => false, 'error' => 'Shift tidak valid']);
    exit;
}

function toIntOrNull($val) {
    if (!isset($val) || $val === '') return null;
    return intval($val);
}

$bak3 = toIntOrNull($_POST['Bak_3'] ?? null);
$bak4 = toIntOrNull($_POST['Bak_4'] ?? null);

$sql = "UPDATE dbo.dh_ipab
        SET Tanggal = ?, Pengecek = ?, Shift = ?, Bak_3 = ?, Bak_4 = ?, UpdatedAt = GETDATE()
        WHERE Id = ?";
$params = [$tanggal, $pengecek, $shift, $bak3, $bak4, $id];

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt) {
    echo json_encode(['success' => true]);
} else {
    $err = sqlsrv_errors();
    echo json_encode(['success' => false, 'error' => $err ? $err[0]['message'] : 'Gagal update']);
}
