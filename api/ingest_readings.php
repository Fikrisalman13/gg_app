<?php
header('Content-Type: application/json; charset=UTF-8');

// 1. Validasi HTTP Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Hanya menerima request method POST']);
    exit;
}

// 2. Load Konfigurasi API
$config = file_exists(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
$validApiKey = $config['api_key'] ?? 'EMDS_SECRET_KEY_2026_DEFAULT';

// 3. Validasi Otorisasi Keamanan (Shared API Key)
$headers = function_exists('getallheaders') ? getallheaders() : [];
$receivedApiKey = $headers['X-API-KEY'] ?? $headers['x-api-key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';

if (empty($receivedApiKey) || !hash_equals($validApiKey, $receivedApiKey)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Otorisasi ditolak: API Key tidak valid']);
    exit;
}

// 4. Hubungkan ke database menggunakan koneksi.php
require_once __DIR__ . '/../koneksi.php';

if (!$conn) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Koneksi ke SQL Server gagal']);
    exit;
}

// 5. Membaca & Memvalidasi Payload JSON
$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);

if (!$payload) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Format JSON tidak valid']);
    exit;
}

// Dukung single object atau array of objects
$items = isset($payload[0]) && is_array($payload[0]) ? $payload : [$payload];

$clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
$insertedCount = 0;

// Query MERGE untuk update / insert stasiun
$sqlStation = "
    MERGE INTO emds_stations AS target
    USING (SELECT ? AS station_id, ? AS station_name, ? AS ip_address) AS source
    ON target.station_id = source.station_id
    WHEN MATCHED THEN
        UPDATE SET last_ping_at = GETDATE(), ip_address = source.ip_address
    WHEN NOT MATCHED THEN
        INSERT (station_id, station_name, ip_address, last_ping_at)
        VALUES (source.station_id, source.station_name, source.ip_address, GETDATE());
";

// Query INSERT untuk data telemetri
$sqlReading = "
    INSERT INTO emds_readings 
    (station_id, device_name, parameter_name, reading_value, unit, quality, captured_at, local_reading_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
";

// Map stasiun yang sudah di-upsert pada request ini agar tidak query MERGE berulang kali untuk stasiun yang sama
$registeredStations = [];

foreach ($items as $item) {
    if (!isset($item['parameter'], $item['capturedAt'])) {
        continue;
    }

    $stationId   = trim($item['stationId'] ?? 'PC-DEFAULT');
    $deviceName  = trim($item['deviceName'] ?? 'Perangkat Utama');
    $parameter   = trim($item['parameter']);
    $value       = isset($item['value']) && is_numeric($item['value']) ? (float)$item['value'] : null;
    $unit        = trim($item['unit'] ?? '');
    $quality     = trim($item['quality'] ?? 'fresh');
    $capturedAt  = date('Y-m-d H:i:s', strtotime($item['capturedAt']));
    $localReadId = isset($item['localReadingId']) ? (int)$item['localReadingId'] : null;

    if (!isset($registeredStations[$stationId])) {
        $stmtStation = sqlsrv_query($conn, $sqlStation, [$stationId, $stationId, $clientIp]);
        if ($stmtStation !== false) {
            sqlsrv_free_stmt($stmtStation);
            $registeredStations[$stationId] = true;
        }
    }

    $paramsReading = [$stationId, $deviceName, $parameter, $value, $unit, $quality, $capturedAt, $localReadId];
    $stmtReading = sqlsrv_query($conn, $sqlReading, $paramsReading);

    if ($stmtReading === false) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal menyimpan bacaan ke SQL Server',
            'detail' => sqlsrv_errors()
        ]);
        exit;
    }
    sqlsrv_free_stmt($stmtReading);
    $insertedCount++;
}

http_response_code(200);
echo json_encode([
    'status' => 'success',
    'message' => 'Data berhasil disimpan ke SQL Server',
    'inserted' => $insertedCount
]);
