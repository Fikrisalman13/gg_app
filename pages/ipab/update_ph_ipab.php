<?php
// update_ph_ipab.php - Proses update data pH IPAB
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

function toDecimalOrNull($val) {
    if (!isset($val) || $val === '') return null;
    $val = str_replace(',', '.', $val);
    return floatval($val);
}

$bakMap = [
    'Bak_Clarivier',
    'Bak_2',
    'Bak_3',
    'Bak_4',
    'Air_Sungai',
];

$fields = "Tanggal = ?, Pengecek = ?, Shift = ?";
$params = [$tanggal, $pengecek, $shift];

foreach ($bakMap as $dbField) {
    $fields .= ", $dbField = ?";
    $params[] = toDecimalOrNull($_POST[$dbField] ?? null);
}

$fields .= ", UpdatedAt = GETDATE()";
$params[] = $id;

$sql = "UPDATE dbo.ph_ipab SET $fields WHERE Id = ?";
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt) {
    echo json_encode(['success' => true]);
} else {
    $err = sqlsrv_errors();
    echo json_encode(['success' => false, 'error' => $err ? $err[0]['message'] : 'Gagal update']);
}
