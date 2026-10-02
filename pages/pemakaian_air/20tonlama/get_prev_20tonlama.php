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
if (!empty($permissions) && isset($permissions['CanView']) && (int)$permissions['CanView'] !== 1) {
    echo json_encode(['success' => false, 'message' => 'Tidak memiliki akses.']);
    exit;
}

$tanggal = trim($_GET['tanggal'] ?? '');
if ($tanggal === '') {
    echo json_encode(['success' => false, 'message' => 'Tanggal tidak valid.']);
    exit;
}

$sql = "SELECT TOP 1 air_akhir_m3, steam_akhir_ton
        FROM dbo.air_steam_20tonlama_harian
        WHERE tanggal < ?
        ORDER BY tanggal DESC, id DESC";
$stmt = sqlsrv_query($conn, $sql, [$tanggal]);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil data.']);
    exit;
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);

echo json_encode([
    'success' => true,
    'air_akhir_m3' => $row['air_akhir_m3'] ?? null,
    'steam_akhir_ton' => $row['steam_akhir_ton'] ?? null
]);

