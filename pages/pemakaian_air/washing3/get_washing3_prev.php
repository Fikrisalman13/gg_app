<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
header('Content-Type: application/json');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$menuId = 230; // TODO: ganti dengan MenuId Washing3 di database
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

function has_meter_flow_column($conn) {
    $stmt = sqlsrv_query($conn, "SELECT COL_LENGTH('dbo.washing3_air','MeterFlow') AS meter_flow");
    if ($stmt === false) return false;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($stmt) sqlsrv_free_stmt($stmt);
    return isset($row['meter_flow']) && $row['meter_flow'] !== null;
}

$excludeId = $_GET['exclude_id'] ?? '';
$excludeId = ctype_digit((string)$excludeId) ? (int)$excludeId : null;

if (!has_meter_flow_column($conn)) {
    echo json_encode([
        'success' => true,
        'meter_flow' => null,
        'message' => 'Kolom MeterFlow belum tersedia.'
    ]);
    exit;
}

$sql = "SELECT TOP 1 MeterFlow
        FROM dbo.washing3_air
        WHERE CAST(Tanggal AS DATE) < ?
          AND MeterFlow IS NOT NULL";
$params = [$tanggal];
if ($excludeId !== null) {
    $sql .= " AND Id <> ?";
    $params[] = $excludeId;
}
$sql .= " ORDER BY CAST(Tanggal AS DATE) DESC, Id DESC";

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil data.']);
    exit;
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode([
    'success' => true,
    'meter_flow' => $row['MeterFlow'] ?? null
]);
