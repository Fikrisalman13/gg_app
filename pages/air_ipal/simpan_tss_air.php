<?php
// simpan_tss_air.php - Proses simpan data TSS Air ke database
header('Content-Type: application/json');
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

function response($success, $error = null) {
    echo json_encode(['success' => $success, 'error' => $error]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response(false, 'Invalid request method');
}

// Ambil data dari POST
$tanggal = $_POST['tanggal'] ?? null;
$pengecek = $_POST['pengecek'] ?? null;
$shift = $_POST['shift'] ?? null;
$created_by = $_SESSION['UserName'] ?? $_SESSION['NamaLengkap'] ?? ($_POST['created_by'] ?? '');

// Kolom bak
$fields = [
    'TSS_0', 'Pekat_Besar', 'Pekat_Kecil', 'Dwatring', 'Anoxit',
    'Daff_1', 'Daff_2', 'Daff_3',
    'Aerasi_1', 'Aerasi_2', 'Aerasi_3', 'Aerasi_4',
    'Sedimen', 'Equal', 'Outlet'
];

$data = [];
foreach ($fields as $f) {
    $data[$f] = isset($_POST[$f]) && $_POST[$f] !== '' ? intval($_POST[$f]) : null;
}

if (!$tanggal || !$pengecek || !$shift) {
    response(false, 'Tanggal, Pengecek, dan Shift wajib diisi!');
}

$sql = "INSERT INTO dbo.TSS_Air (
    Tanggal, Pengecek, Shift, TSS_0, Pekat_Besar, Pekat_Kecil, Dwatring, Anoxit,
    Daff_1, Daff_2, Daff_3, Aerasi_1, Aerasi_2, Aerasi_3, Aerasi_4,
    Sedimen, Equal, Outlet, Created_By, Created_At, UpdateAt
) VALUES (
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), GETDATE()
)";

$params = [
    $tanggal, $pengecek, $shift,
    $data['TSS_0'], $data['Pekat_Besar'], $data['Pekat_Kecil'], $data['Dwatring'], $data['Anoxit'],
    $data['Daff_1'], $data['Daff_2'], $data['Daff_3'],
    $data['Aerasi_1'], $data['Aerasi_2'], $data['Aerasi_3'], $data['Aerasi_4'],
    $data['Sedimen'], $data['Equal'], $data['Outlet'],
    $created_by
];

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    $err = sqlsrv_errors();
    response(false, $err[0]['message'] ?? 'Gagal menyimpan data');
}
response(true);
