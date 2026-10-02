<?php
// simpan_mlss_air.php - Proses simpan data MLSS Air
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$tanggal = $_POST['tanggal'] ?? null;
$pengecek = $_POST['pengecek'] ?? null;
$shift    = $_POST['shift'] ?? null;
$createdBy = $_SESSION['UserName'] ?? $_SESSION['NamaLengkap'] ?? ($_POST['created_by'] ?? '');

if (!$tanggal || !$pengecek || !$shift) {
    echo json_encode(['success' => false, 'error' => 'Tanggal, Pengecek, dan Shift wajib diisi']);
    exit;
}

$aerasiFields = ['Aerasi1', 'Aerasi2', 'Aerasi3', 'Aerasi4', 'Raspam'];
$aerasiValues = [];
foreach ($aerasiFields as $field) {
    if (isset($_POST[$field]) && $_POST[$field] !== '') {
        $aerasiValues[$field] = round(floatval($_POST[$field]), 2);
    } else {
        $aerasiValues[$field] = null;
    }
}

$sql = "INSERT INTO dbo.MLSS_Air (
    Tanggal, Pengecek, Shift,
    Aerasi1, Aerasi2, Aerasi3, Aerasi4, Raspam,
    CreatedBy, CreatedAt, UpdateAt
) VALUES (
    ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), GETDATE()
)";

$params = [
    $tanggal,
    $pengecek,
    $shift,
    $aerasiValues['Aerasi1'],
    $aerasiValues['Aerasi2'],
    $aerasiValues['Aerasi3'],
    $aerasiValues['Aerasi4'],
    $aerasiValues['Raspam'],
    $createdBy
];

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt) {
    $response['success'] = true;
} else {
    $err = sqlsrv_errors();
    $response['error'] = $err ? $err[0]['message'] : 'Gagal menyimpan data';
}

echo json_encode($response);
