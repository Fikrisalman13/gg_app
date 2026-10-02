<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
header('Content-Type: application/json');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$menuId = 230; // TODO: ganti dengan MenuId perblerange2 di database
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Tidak memiliki akses.']);
    exit;
}

$tanggal = $_GET['tanggal'] ?? '';
if ($tanggal === '') {
    echo json_encode(['success' => false, 'message' => 'Tanggal tidak valid.']);
    exit;
}

$dt = DateTime::createFromFormat('Y-m-d', $tanggal);
if (!$dt) {
    echo json_encode(['success' => false, 'message' => 'Format tanggal tidak valid.']);
    exit;
}
$nextDate = $dt->modify('+1 day')->format('Y-m-d');

$sql = "SELECT TOP 1 Meter_Awal
        FROM dbo.perblerange2_air
        WHERE CAST(Tanggal AS DATE) = ?
        ORDER BY Tanggal ASC, Id ASC";
$stmt = sqlsrv_query($conn, $sql, [$nextDate]);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil data.']);
    exit;
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);

$meterAwal = $row['Meter_Awal'] ?? null;

echo json_encode([
    'success' => true,
    'meter_awal' => $meterAwal
]);
