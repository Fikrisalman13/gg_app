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

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date']   ?? date('Y-m-d');
$stationId = $_GET['station_id'] ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) $startDate = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate))   $endDate   = date('Y-m-d');

$whereParts = ["captured_at >= ? AND captured_at < DATEADD(DAY, 1, CAST(? AS DATE))"];
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
