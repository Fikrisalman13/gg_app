<?php
/**
 * ajax_dashboard.php
 * Endpoint JSON: Statistik ringkasan untuk dashboard overview.
 * Dipanggil via AJAX setiap 60 detik dari index.php.
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();

if (!isset($_SESSION['UserId'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';

$result = [];

// --- 1. Statistik Utama ---
$sqlStats = "
    SELECT
        (SELECT COUNT(*) FROM emds_stations) AS total_stations,
        (SELECT COUNT(*) FROM emds_stations WHERE is_active = 1) AS active_stations,
        (SELECT COUNT(*) FROM emds_readings
            WHERE captured_at >= CAST(GETDATE() AS DATE)) AS readings_today,
        (SELECT COUNT(*) FROM emds_stations
            WHERE last_ping_at >= DATEADD(MINUTE, -5, GETDATE())) AS online_stations,
        (SELECT AVG(reading_value) FROM emds_readings
            WHERE captured_at >= DATEADD(HOUR, -24, GETDATE())
              AND reading_value IS NOT NULL) AS avg_reading_24h
";
$stmtStats = sqlsrv_query($conn, $sqlStats);
if ($stmtStats && $row = sqlsrv_fetch_array($stmtStats, SQLSRV_FETCH_ASSOC)) {
    $result['stats'] = [
        'total_stations'  => (int)$row['total_stations'],
        'active_stations' => (int)$row['active_stations'],
        'online_stations' => (int)$row['online_stations'],
        'readings_today'  => (int)$row['readings_today'],
        'avg_reading_24h' => $row['avg_reading_24h'] !== null ? round((float)$row['avg_reading_24h'], 2) : null,
    ];
    sqlsrv_free_stmt($stmtStats);
} else {
    $result['stats'] = ['total_stations'=>0,'active_stations'=>0,'online_stations'=>0,'readings_today'=>0,'avg_reading_24h'=>null];
}

// --- 2. Status Per Stasiun ---
$sqlStations = "
    SELECT
        s.station_id,
        s.station_name,
        s.ip_address,
        s.is_active,
        s.last_ping_at,
        (SELECT TOP 1 r.reading_value FROM emds_readings r
         WHERE r.station_id = s.station_id
         ORDER BY r.captured_at DESC) AS last_value,
        (SELECT TOP 1 r.unit FROM emds_readings r
         WHERE r.station_id = s.station_id
         ORDER BY r.captured_at DESC) AS last_unit,
        (SELECT TOP 1 r.parameter_name FROM emds_readings r
         WHERE r.station_id = s.station_id
         ORDER BY r.captured_at DESC) AS last_parameter,
        (SELECT TOP 1 r.quality FROM emds_readings r
         WHERE r.station_id = s.station_id
         ORDER BY r.captured_at DESC) AS last_quality,
        (SELECT TOP 1 r.captured_at FROM emds_readings r
         WHERE r.station_id = s.station_id
         ORDER BY r.captured_at DESC) AS last_reading_at
    FROM emds_stations s
    ORDER BY s.station_name
";
$stmtSt = sqlsrv_query($conn, $sqlStations);
$stations = [];
if ($stmtSt) {
    while ($row = sqlsrv_fetch_array($stmtSt, SQLSRV_FETCH_ASSOC)) {
        $lastPing = null;
        if ($row['last_ping_at'] instanceof DateTime) {
            $lastPing = $row['last_ping_at']->format('Y-m-d H:i:s');
        } elseif (is_string($row['last_ping_at'])) {
            $lastPing = $row['last_ping_at'];
        }
        $lastReadingAt = null;
        if ($row['last_reading_at'] instanceof DateTime) {
            $lastReadingAt = $row['last_reading_at']->format('Y-m-d H:i:s');
        } elseif (is_string($row['last_reading_at'])) {
            $lastReadingAt = $row['last_reading_at'];
        }

        // Tentukan status online: last_ping dalam 5 menit
        $isOnline = false;
        if ($lastPing) {
            $diff = (time() - strtotime($lastPing));
            $isOnline = $diff <= 300; // 5 menit
        }

        $stations[] = [
            'station_id'    => $row['station_id'],
            'station_name'  => $row['station_name'],
            'ip_address'    => $row['ip_address'],
            'is_active'     => (bool)$row['is_active'],
            'is_online'     => $isOnline,
            'last_ping_at'  => $lastPing,
            'last_value'    => $row['last_value'] !== null ? round((float)$row['last_value'], 3) : null,
            'last_unit'     => $row['last_unit'],
            'last_parameter'=> $row['last_parameter'],
            'last_quality'  => $row['last_quality'],
            'last_reading_at'=> $lastReadingAt,
        ];
    }
    sqlsrv_free_stmt($stmtSt);
}
$result['stations'] = $stations;

// --- 3. 10 Pembacaan Terbaru ---
$sqlRecent = "
    SELECT TOP 10
        r.id,
        r.station_id,
        s.station_name,
        r.device_name,
        r.parameter_name,
        r.reading_value,
        r.unit,
        r.quality,
        r.captured_at
    FROM emds_readings r
    LEFT JOIN emds_stations s ON r.station_id = s.station_id
    ORDER BY r.captured_at DESC
";
$stmtRecent = sqlsrv_query($conn, $sqlRecent);
$recent = [];
if ($stmtRecent) {
    while ($row = sqlsrv_fetch_array($stmtRecent, SQLSRV_FETCH_ASSOC)) {
        $capturedAt = null;
        if ($row['captured_at'] instanceof DateTime) {
            $capturedAt = $row['captured_at']->format('Y-m-d H:i:s');
        } elseif (is_string($row['captured_at'])) {
            $capturedAt = $row['captured_at'];
        }
        $recent[] = [
            'id'             => $row['id'],
            'station_id'     => $row['station_id'],
            'station_name'   => $row['station_name'],
            'device_name'    => $row['device_name'],
            'parameter_name' => $row['parameter_name'],
            'reading_value'  => $row['reading_value'] !== null ? round((float)$row['reading_value'], 3) : null,
            'unit'           => $row['unit'],
            'quality'        => $row['quality'],
            'captured_at'    => $capturedAt,
        ];
    }
    sqlsrv_free_stmt($stmtRecent);
}
$result['recent'] = $recent;

$result['status']     = 'ok';
$result['server_time'] = date('Y-m-d H:i:s');

echo json_encode($result, JSON_UNESCAPED_UNICODE);
