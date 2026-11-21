<?php
/**
 * ajax_stations.php
 * Endpoint JSON: Daftar semua stasiun dari emds_stations.
 * Dipakai oleh dropdown filter di data_list.php dan grafik.php.
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();

if (!isset($_SESSION['UserId'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';

$sql = "SELECT station_id, station_name, is_active, last_ping_at
        FROM emds_stations
        ORDER BY station_name";

$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Query gagal', 'detail' => sqlsrv_errors()]);
    exit;
}

$stations = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $lastPing = null;
    if ($row['last_ping_at'] instanceof DateTime) {
        $lastPing = $row['last_ping_at']->format('Y-m-d H:i:s');
    } elseif (is_string($row['last_ping_at'])) {
        $lastPing = $row['last_ping_at'];
    }

    $stations[] = [
        'station_id'   => $row['station_id'],
        'station_name' => $row['station_name'],
        'is_active'    => (bool)$row['is_active'],
        'last_ping_at' => $lastPing,
    ];
}
sqlsrv_free_stmt($stmt);

echo json_encode(['status' => 'ok', 'data' => $stations]);
