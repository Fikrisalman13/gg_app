<?php
/**
 * ajax_data.php
 * Endpoint JSON: DataTables server-side untuk list data emds_readings.
 * Parameter GET/POST: draw, start, length, start_date, end_date, station_id, search[value]
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();

if (!isset($_SESSION['UserId'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';

// --- Ambil Parameter ---
$draw      = isset($_GET['draw'])   ? (int)$_GET['draw']   : 1;
$start     = isset($_GET['start'])  ? (int)$_GET['start']  : 0;
$length    = isset($_GET['length']) ? (int)$_GET['length'] : 50;
$search    = isset($_GET['search']['value']) ? trim($_GET['search']['value']) : '';

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date']   ?? date('Y-m-d');
$stationId = $_GET['station_id'] ?? '';

// Validasi tanggal
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) $startDate = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate))   $endDate   = date('Y-m-d');

// Batas length
$length = max(10, min(200, $length));

// --- Susun WHERE ---
$whereParts = ["r.captured_at >= ? AND r.captured_at < DATEADD(DAY, 1, CAST(? AS DATE))"];
$params     = [$startDate, $endDate];

if (!empty($stationId)) {
    $whereParts[] = "r.station_id = ?";
    $params[]     = $stationId;
}
if ($search !== '') {
    $whereParts[] = "(r.station_id LIKE ? OR r.parameter_name LIKE ? OR r.device_name LIKE ? OR r.unit LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}

$whereClause = implode(' AND ', $whereParts);

// --- Count Total ---
$sqlTotal = "SELECT COUNT_BIG(*) AS cnt FROM emds_readings r WHERE $whereClause";
$stmtTotal = sqlsrv_query($conn, $sqlTotal, $params);
$totalFiltered = 0;
if ($stmtTotal && $row = sqlsrv_fetch_array($stmtTotal, SQLSRV_FETCH_ASSOC)) {
    $totalFiltered = (int)$row['cnt'];
    sqlsrv_free_stmt($stmtTotal);
}

// --- Count Total Tanpa Filter Search (untuk recordsTotal) ---
$paramsBase = [$startDate, $endDate];
$baseWhere  = "r.captured_at >= ? AND r.captured_at < DATEADD(DAY, 1, CAST(? AS DATE))";
if (!empty($stationId)) {
    $baseWhere   .= " AND r.station_id = ?";
    $paramsBase[] = $stationId;
}
$sqlBase  = "SELECT COUNT_BIG(*) AS cnt FROM emds_readings r WHERE $baseWhere";
$stmtBase = sqlsrv_query($conn, $sqlBase, $paramsBase);
$totalAll = 0;
if ($stmtBase && $row = sqlsrv_fetch_array($stmtBase, SQLSRV_FETCH_ASSOC)) {
    $totalAll = (int)$row['cnt'];
    sqlsrv_free_stmt($stmtBase);
}

// --- Ambil Data ---
$paramsData = array_merge($params, [$start, $length]);
$sqlData = "
    SELECT
        r.id,
        r.station_id,
        COALESCE(s.station_name, r.station_id) AS station_name,
        r.device_name,
        r.parameter_name,
        r.reading_value,
        r.unit,
        r.quality,
        r.captured_at,
        r.synced_at
    FROM emds_readings r
    LEFT JOIN emds_stations s ON r.station_id = s.station_id
    WHERE $whereClause
    ORDER BY r.captured_at DESC
    OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
";

$stmtData = sqlsrv_query($conn, $sqlData, $paramsData);

$rows = [];
if ($stmtData) {
    while ($row = sqlsrv_fetch_array($stmtData, SQLSRV_FETCH_ASSOC)) {
        $capturedAt = null;
        if ($row['captured_at'] instanceof DateTime) {
            $capturedAt = $row['captured_at']->format('Y-m-d H:i:s');
        } elseif (is_string($row['captured_at'])) {
            $capturedAt = $row['captured_at'];
        }
        $syncedAt = null;
        if ($row['synced_at'] instanceof DateTime) {
            $syncedAt = $row['synced_at']->format('Y-m-d H:i:s');
        } elseif (is_string($row['synced_at'])) {
            $syncedAt = $row['synced_at'];
        }

        $rows[] = [
            'id'             => $row['id'],
            'station_name'   => $row['station_name'],
            'device_name'    => $row['device_name'],
            'parameter_name' => $row['parameter_name'],
            'reading_value'  => $row['reading_value'] !== null ? round((float)$row['reading_value'], 4) : null,
            'unit'           => $row['unit'],
            'quality'        => $row['quality'],
            'captured_at'    => $capturedAt,
            'synced_at'      => $syncedAt,
        ];
    }
    sqlsrv_free_stmt($stmtData);
}

echo json_encode([
    'draw'            => $draw,
    'recordsTotal'    => $totalAll,
    'recordsFiltered' => $totalFiltered,
    'data'            => $rows,
], JSON_UNESCAPED_UNICODE);
