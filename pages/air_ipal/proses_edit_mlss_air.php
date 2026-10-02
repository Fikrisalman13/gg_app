<?php
// proses_edit_mlss_air.php
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
header('Content-Type: application/json');
if (!isset($_POST['id'])) {
    echo json_encode(['success' => false, 'error' => 'ID tidak ditemukan.']);
    exit;
}
$id = intval($_POST['id']);
$tanggal = $_POST['Tanggal'] ?? '';
$pengecek = $_POST['Pengecek'] ?? '';
$shift = $_POST['Shift'] ?? '';
function parseFloatOrNull($val) {
    if ($val === null || $val === '' || !is_numeric($val)) return null;
    return floatval($val);
}
$aerasi1 = parseFloatOrNull($_POST['Aerasi1'] ?? null);
$aerasi2 = parseFloatOrNull($_POST['Aerasi2'] ?? null);
$aerasi3 = parseFloatOrNull($_POST['Aerasi3'] ?? null);
$aerasi4 = parseFloatOrNull($_POST['Aerasi4'] ?? null);
$raspam = parseFloatOrNull($_POST['Raspam'] ?? null);
if ($tanggal === '' || $pengecek === '' || $shift === '') {
    echo json_encode(['success' => false, 'error' => 'Tanggal, Pengecek, dan Shift wajib diisi.']);
    exit;
}
$sql = "UPDATE MLSS_Air SET Tanggal = ?, Pengecek = ?, Shift = ?, Aerasi1 = ?, Aerasi2 = ?, Aerasi3 = ?, Aerasi4 = ?, Raspam = ?, UpdateAt = GETDATE() WHERE Id = ?";
$params = array($tanggal, $pengecek, $shift, $aerasi1, $aerasi2, $aerasi3, $aerasi4, $raspam, $id);
$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt) {
    echo json_encode(['success' => true]);
} else {
    $errors = sqlsrv_errors();
    echo json_encode(['success' => false, 'error' => 'Gagal update data: ' . ($errors ? $errors[0]['message'] : 'Unknown error')]);
}
