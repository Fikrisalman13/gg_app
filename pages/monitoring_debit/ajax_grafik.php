<?php
/**
 * ajax_grafik.php
 * Endpoint JSON: Dataset untuk Chart.js line chart.
 * Parameter: start_date, end_date, station_id, parameter_name
 * Sampling adaptif: <=1 hari → raw, <=7 hari → per jam, >7 hari → per hari (AVG).
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();

if (!isset($_SESSION['UserId'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';

$startDate     = $_GET['start_date']     ?? date('Y-m-d');
$endDate       = $_GET['end_date']       ?? date('Y-m-d');
$stationId     = $_GET['station_id']     ?? '';
$parameterName = $_GET['parameter_name'] ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) $startDate = date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate))   $endDate   = date('Y-m-d');

// Hitung range hari untuk menentukan sampling
$diffDays = (int)ceil((strtotime($endDate) - strtotime($startDate)) / 86400) + 1;

// Tentukan mode sampling
if ($diffDays <= 1) {
    $mode = 'raw';
} elseif ($diffDays <= 7) {
    $mode = 'hourly';
} else {
    $mode = 'daily';
}

// Susun WHERE
$whereParts = ["r.captured_at >= ? AND r.captured_at < DATEADD(DAY, 1, CAST(? AS DATE))"];
$params     = [$startDate, $endDate];

if (!empty($stationId)) {
    $whereParts[] = "r.station_id = ?";
    $params[]     = $stationId;
}
if (!empty($parameterName)) {
    $whereParts[] = "r.parameter_name = ?";
    $params[]     = $parameterName;
}

$whereClause = implode(' AND ', $whereParts);

// Query berdasarkan mode sampling
switch ($mode) {
    case 'raw':
        $sql = "
            SELECT
                r.station_id,
                COALESCE(s.station_name, r.station_id) AS station_name,
                r.parameter_name,
                r.unit,
                r.reading_value,
                r.captured_at,
                r.quality
            FROM emds_readings r
            LEFT JOIN emds_stations s ON r.station_id = s.station_id
            WHERE $whereClause AND r.reading_value IS NOT NULL
            ORDER BY r.station_id, r.captured_at ASC
        ";
        break;

    case 'hourly':
        $sql = "
            SELECT
                r.station_id,
                COALESCE(s.station_name, r.station_id) AS station_name,
                r.parameter_name,
                r.unit,
                AVG(r.reading_value) AS reading_value,
                CAST(FORMAT(r.captured_at, 'yyyy-MM-dd HH:00:00') AS DATETIME2) AS captured_at,
                'fresh' AS quality
            FROM emds_readings r
            LEFT JOIN emds_stations s ON r.station_id = s.station_id
            WHERE $whereClause AND r.reading_value IS NOT NULL
            GROUP BY r.station_id, s.station_name, r.parameter_name, r.unit,
                     FORMAT(r.captured_at, 'yyyy-MM-dd HH:00:00')
            ORDER BY r.station_id, captured_at ASC
        ";
        break;

    default: // daily
        $sql = "
            SELECT
                r.station_id,
                COALESCE(s.station_name, r.station_id) AS station_name,
                r.parameter_name,
                r.unit,
                AVG(r.reading_value) AS reading_value,
                CAST(CAST(CAST(r.captured_at AS DATE) AS VARCHAR(10)) + ' 00:00:00' AS DATETIME2) AS captured_at,
                'fresh' AS quality
            FROM emds_readings r
            LEFT JOIN emds_stations s ON r.station_id = s.station_id
            WHERE $whereClause AND r.reading_value IS NOT NULL
            GROUP BY r.station_id, s.station_name, r.parameter_name, r.unit,
                     CAST(r.captured_at AS DATE)
            ORDER BY r.station_id, captured_at ASC
        ";
        break;
}

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Query gagal', 'detail' => sqlsrv_errors()]);
    exit;
}

// Kelompokkan per stasiun
$byStation   = [];
$allLabels   = [];
$unitGlobal  = '';

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $capturedAt = null;
    if ($row['captured_at'] instanceof DateTime) {
        $capturedAt = $row['captured_at']->format('Y-m-d H:i:s');
    } elseif (is_string($row['captured_at'])) {
        $capturedAt = $row['captured_at'];
    }

    $sid  = $row['station_id'];
    $name = $row['station_name'] ?? $sid;
    $val  = $row['reading_value'] !== null ? round((float)$row['reading_value'], 4) : null;

    if (!isset($byStation[$sid])) {
        $byStation[$sid] = ['name' => $name, 'points' => []];
    }
    $byStation[$sid]['points'][] = ['t' => $capturedAt, 'v' => $val];
    $allLabels[$capturedAt] = true;
    if (!$unitGlobal && !empty($row['unit'])) {
        $unitGlobal = $row['unit'];
    }
}
sqlsrv_free_stmt($stmt);

// Susun labels terurut
ksort($allLabels);
$labels = array_keys($allLabels);

// Palet warna
$colors = [
    ['rgba(21,101,192,1)', 'rgba(21,101,192,0.12)'],
    ['rgba(0,150,136,1)',  'rgba(0,150,136,0.12)'],
    ['rgba(230,74,25,1)',  'rgba(230,74,25,0.12)'],
    ['rgba(123,31,162,1)', 'rgba(123,31,162,0.12)'],
    ['rgba(255,143,0,1)',  'rgba(255,143,0,0.12)'],
    ['rgba(0,131,143,1)',  'rgba(0,131,143,0.12)'],
    ['rgba(183,28,28,1)',  'rgba(183,28,28,0.12)'],
];

$datasets = [];
$colorIdx = 0;
foreach ($byStation as $sid => $info) {
    // Map data ke label
    $pointMap = [];
    foreach ($info['points'] as $p) {
        $pointMap[$p['t']] = $p['v'];
    }
    $data = [];
    foreach ($labels as $lbl) {
        $data[] = $pointMap[$lbl] ?? null;
    }

    $c = $colors[$colorIdx % count($colors)];
    $datasets[] = [
        'label'           => $info['name'],
        'data'            => $data,
        'borderColor'     => $c[0],
        'backgroundColor' => $c[1],
        'borderWidth'     => 2,
        'pointRadius'     => count($labels) > 100 ? 0 : 3,
        'fill'            => count($byStation) === 1,
        'tension'         => 0.3,
        'spanGaps'        => true,
    ];
    $colorIdx++;
}

echo json_encode([
    'status'     => 'ok',
    'mode'       => $mode,
    'diff_days'  => $diffDays,
    'unit'       => $unitGlobal,
    'labels'     => $labels,
    'datasets'   => $datasets,
], JSON_UNESCAPED_UNICODE);
