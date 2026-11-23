<?php
/**
 * ajax_parameters.php
 * Endpoint JSON: Daftar parameter unik yang tersedia di emds_readings.
 * Filter opsional: station_id, start_date, end_date.
 * Dipakai oleh dropdown parameter di grafik.php.
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();

if (!isset($_SESSION['UserId'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';

$rawStart  = trim($_GET['start_date'] ?? '');
$rawEnd    = trim($_GET['end_date'] ?? '');
$stationId = $_GET['station_id'] ?? '';

// Normalisasi 'T' pemisah tanggal dan waktu dari datetime-local
$rawStart = str_replace('T', ' ', $rawStart);
$rawEnd   = str_replace('T', ' ', $rawEnd);

// Default jika kosong: awal hari ini s/d sekarang
if (empty($rawStart)) $rawStart = date('Y-m-d 00:00:00');
if (empty($rawEnd))   $rawEnd   = date('Y-m-d H:i:s');

if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawStart)) {
    $startDate = $rawStart . ' 00:00:00';
} elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $rawStart)) {
    $startDate = $rawStart . ':00';
} elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $rawStart)) {
    $startDate = $rawStart;
} else {
    $startDate = date('Y-m-d 00:00:00');
}

if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawEnd)) {
    $endDate = $rawEnd . ' 23:59:59';
} elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $rawEnd)) {
    $endDate = $rawEnd . ':59';
} elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $rawEnd)) {
    $endDate = $rawEnd;
} else {
    $endDate = date('Y-m-d H:i:s');
}

$whereParts = ["captured_at >= ? AND captured_at <= ?"];
$params     = [$startDate, $endDate];

if (!empty($stationId)) {
    $whereParts[] = "station_id = ?";
    $params[]     = $stationId;
}

$sql = "
    SELECT DISTINCT parameter_name, unit
    FROM emds_readings
    WHERE " . implode(' AND ', $whereParts) . "
    ORDER BY parameter_name
";

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Query gagal']);
    exit;
}

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $data[] = [
        'parameter_name' => $row['parameter_name'],
        'unit'           => $row['unit'],
    ];
}
sqlsrv_free_stmt($stmt);

echo json_encode(['status' => 'ok', 'data' => $data], JSON_UNESCAPED_UNICODE);
