<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$menuId = 230; // TODO: ganti dengan MenuId Washing3 di database
$permissions = getPermissions($conn, $_SESSION['GroupId'], $menuId);
if (!empty($permissions) && isset($permissions['CanAdd']) && $permissions['CanAdd'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak untuk menambah data.']);
    exit;
}

function normalize_decimal($value) {
    $value = trim((string)$value);
    if ($value === '') return null;
    $value = preg_replace('/[^0-9.,]/', '', $value);
    if ($value === '') return null;

    if (strpos($value, '.') !== false) {
        $value = str_replace(',', '', $value);
        if (is_numeric($value)) return $value;
    }

    if (strpos($value, '.') !== false && strpos($value, ',') === false) {
        $parts = explode('.', $value);
        if (count($parts) > 2) {
            $dec = array_pop($parts);
            $int = implode('', $parts);
            $normalized = $int . '.' . $dec;
            if (is_numeric($normalized)) return $normalized;
        }
    }

    $fallback = str_replace('.', '', $value);
    $fallback = str_replace(',', '.', $fallback);
    return is_numeric($fallback) ? $fallback : null;
}

function has_meter_flow_column($conn) {
    $stmt = sqlsrv_query($conn, "SELECT COL_LENGTH('dbo.washing3_air','MeterFlow') AS meter_flow");
    if ($stmt === false) return false;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($stmt) sqlsrv_free_stmt($stmt);
    return isset($row['meter_flow']) && $row['meter_flow'] !== null;
}

function get_previous_meter_flow($conn, $tanggal) {
    $sql = "SELECT TOP 1 MeterFlow
            FROM dbo.washing3_air
            WHERE CAST(Tanggal AS DATE) < ?
              AND MeterFlow IS NOT NULL
            ORDER BY CAST(Tanggal AS DATE) DESC, Id DESC";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal]);
    if ($stmt === false) return null;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($stmt) sqlsrv_free_stmt($stmt);
    $val = $row['MeterFlow'] ?? null;
    return is_numeric($val) ? (float)$val : null;
}

$tanggal = trim($_POST['tanggal'] ?? '');
$waterFlowInput = normalize_decimal($_POST['water_flow'] ?? '');
$operasionalMesin = normalize_decimal($_POST['operasional_mesin'] ?? '');
$totalInput = normalize_decimal($_POST['total_pemakaian'] ?? '');
$meterFlow = normalize_decimal($_POST['meter_flow'] ?? '');
$keterangan = trim($_POST['keterangan'] ?? '');

if ($tanggal === '') {
    echo json_encode(['success' => false, 'message' => 'Tanggal wajib diisi.']);
    exit;
}

$totalPemakaian = null;
$waterFlow = null;
$hasMeterFlowColumn = has_meter_flow_column($conn);
$prevMeterFlow = null;

if ($hasMeterFlowColumn && $meterFlow !== null && $operasionalMesin !== null && (float)$operasionalMesin > 0) {
    $prevMeterFlow = get_previous_meter_flow($conn, $tanggal);
    if ($prevMeterFlow !== null) {
        $waterFlow = round((((float)$meterFlow - (float)$prevMeterFlow) / (float)$operasionalMesin), 2);
    }
}

if ($waterFlow === null) {
    $waterFlow = $waterFlowInput !== null ? round((float)$waterFlowInput, 2) : null;
}

if ($waterFlow !== null && $operasionalMesin !== null) {
    $totalPemakaian = round(((float)$waterFlow * (float)$operasionalMesin), 2);
} elseif ($totalInput !== null) {
    $totalPemakaian = round((float)$totalInput, 2);
}

$columns = ['Tanggal', 'WaterFlow', 'OperasionalMesin', 'TotalPemakaian', 'Keterangan', 'CreatBy'];
$params = [
    $tanggal,
    $waterFlow,
    $operasionalMesin,
    $totalPemakaian,
    $keterangan,
    $_SESSION['UserName']
];

$placeholders = ['?', '?', '?', '?', '?', '?'];
if ($hasMeterFlowColumn) {
    $columns[] = 'MeterFlow';
    $placeholders[] = '?';
    $params[] = $meterFlow;
}

$sql = "INSERT INTO dbo.washing3_air
        (" . implode(', ', $columns) . ")
        VALUES (" . implode(', ', $placeholders) . ")";

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data.']);
    exit;
}

if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['success' => true, 'message' => 'Data berhasil disimpan.']);
