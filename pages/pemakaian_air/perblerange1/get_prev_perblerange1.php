<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak akses.']);
    exit;
}

$tanggal = trim($_POST['tanggal'] ?? '');
if ($tanggal === '') {
    echo json_encode(['success' => false, 'message' => 'Tanggal wajib diisi.']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    echo json_encode(['success' => false, 'message' => 'Format tanggal tidak valid.']);
    exit;
}

$sql = "SELECT TOP 1 CAST(tanggal AS DATE) AS tanggal,
               meter_akhir_m3,
               meter_akhir_debit_m3
        FROM dbo.perblerange1_harian
        WHERE CAST(tanggal AS DATE) = DATEADD(day, -1, ?)
        ORDER BY id DESC";
$stmt = sqlsrv_query($conn, $sql, [$tanggal]);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil data sebelumnya.']);
    exit;
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if ($stmt) {
    sqlsrv_free_stmt($stmt);
}

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Data tanggal sebelumnya tidak ditemukan.']);
    exit;
}

$tglObj = $row['tanggal'] ?? null;
$tgl = ($tglObj instanceof DateTime) ? $tglObj->format('Y-m-d') : date('Y-m-d', strtotime((string)$tglObj));

echo json_encode([
    'success' => true,
    'data' => [
        'tanggal' => $tgl,
        'meter_akhir_m3' => $row['meter_akhir_m3'],
        'meter_akhir_debit_m3' => $row['meter_akhir_debit_m3']
    ]
]);
