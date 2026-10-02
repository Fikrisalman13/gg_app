<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

date_default_timezone_set('Asia/Jakarta');

$timezone = date_default_timezone_get();
$nowMs = null;

try {
    include __DIR__ . '/../../koneksi3.php';
    if (isset($conn3) && $conn3 instanceof PDO) {
        $stmt = $conn3->query("SELECT (EXTRACT(EPOCH FROM CURRENT_TIMESTAMP) * 1000)::bigint AS server_now_ms");
        $nowMs = (int) $stmt->fetchColumn();
    }
} catch (Throwable $e) {
    $nowMs = null;
}

if (!$nowMs) {
    try {
        include __DIR__ . '/../../koneksi.php';
        if (isset($conn) && $conn) {
            $stmt = sqlsrv_query($conn, "SELECT DATEDIFF(SECOND, '19700101', GETUTCDATE()) AS server_epoch_seconds");
            if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
                $nowMs = ((int) $row['server_epoch_seconds']) * 1000;
            }
            if ($stmt) {
                sqlsrv_free_stmt($stmt);
            }
        }
    } catch (Throwable $e) {
        $nowMs = null;
    }
}

if (!$nowMs) {
    $nowMs = (int) round(microtime(true) * 1000);
}

$now = new DateTimeImmutable('@' . (int) floor($nowMs / 1000));
$now = $now->setTimezone(new DateTimeZone($timezone));

echo json_encode([
    'success' => true,
    'server_now_ms' => $nowMs,
    'timezone' => $timezone,
    'timezone_offset_ms' => $now->getOffset() * 1000,
    'time' => $now->format('H:i:s'),
]);
