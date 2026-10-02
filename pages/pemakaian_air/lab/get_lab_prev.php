<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
header('Content-Type: application/json');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$menuId = 230; // TODO: ganti dengan MenuId LAB di database
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

$excludeIdRaw = trim($_GET['exclude_id'] ?? '');
$excludeId = ctype_digit($excludeIdRaw) ? (int)$excludeIdRaw : null;

$sql = "SELECT TOP 1 Meter_Akhir
        FROM dbo.lab_air
        WHERE CAST(Tanggal AS DATE) < ?
          AND Meter_Akhir IS NOT NULL";
$params = [$tanggal];
if ($excludeId !== null) {
    $sql .= " AND Id <> ?";
    $params[] = $excludeId;
}
$sql .= " ORDER BY CAST(Tanggal AS DATE) DESC, Id DESC";

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil data sebelumnya.']);
    exit;
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode([
    'success' => true,
    'meter_akhir' => $row['Meter_Akhir'] ?? null,
    'message' => empty($row['Meter_Akhir']) ? 'Data sebelumnya tidak ditemukan.' : ''
]);

