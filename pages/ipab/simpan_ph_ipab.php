<?php
// simpan_ph_ipab.php - Proses simpan data pH IPAB ke database
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
header('Content-Type: application/json');

// Validasi minimal
if (!isset($_POST['tanggal'], $_POST['pengecek'], $_POST['shift'])) {
    echo json_encode(['success' => false, 'error' => 'Data tidak lengkap']);
    exit;
}

$tanggal = $_POST['tanggal'];
$pengecek = $_POST['pengecek'];
$shift = $_POST['shift'];
$created_by = $_SESSION['UserName'] ?? ($_POST['created_by'] ?? '');

// Normalisasi shift jika masih berupa angka
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

// Helper untuk konversi angka dengan koma
function toDecimalOrNull($val) {
    if (!isset($val) || $val === '') return null;
    $val = str_replace(',', '.', $val);
    return floatval($val);
}

$bakMap = [
    'Bak_Clarivier' => 'BakClarivier',
    'Bak_2' => 'Bak2',
    'Bak_3' => 'Bak3',
    'Bak_4' => 'Bak4',
    'Air_Sungai' => 'AirSungai',
];

$fields = "Tanggal, Pengecek, Shift";
$values = "?, ?, ?";
$params = [$tanggal, $pengecek, $shift];

foreach ($bakMap as $dbField => $postKey) {
    $fields .= ", $dbField";
    $values .= ", ?";
    $params[] = toDecimalOrNull($_POST[$postKey] ?? null);
}

$fields .= ", CreatedBy, CreatedAt, UpdatedAt";
$values .= ", ?, GETDATE(), NULL";
$params[] = $created_by;

$sql = "INSERT INTO dbo.ph_ipab ($fields) VALUES ($values)";
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt) {
    echo json_encode(['success' => true]);
} else {
    $err = sqlsrv_errors();
    echo json_encode(['success' => false, 'error' => $err ? $err[0]['message'] : 'Gagal simpan']);
}
