<?php
/**
 * Session Keep-Alive Endpoint
 * Dipanggil secara periodik oleh browser untuk mencegah sesi PHP
 * dari timeout ketika halaman dashboard ditampilkan 24 jam.
 */

// Sama seperti header.php - gunakan cookie_lifetime 24 jam agar konsisten
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', '86400');
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_path'     => '/gg_app/',
        'cookie_secure'   => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
    ]);
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (
    (!isset($_SESSION['UserId']) || empty($_SESSION['UserId'])) &&
    (!isset($_SESSION['UserName']) || empty($_SESSION['UserName']))
) {
    echo json_encode(['alive' => false, 'message' => 'Session expired']);
    exit;
}

// Sentuh session agar idle timer di-reset
$_SESSION['last_ping'] = time();

require_once __DIR__ . '/../koneksi.php';
$requestId = bin2hex(random_bytes(8));
$userId = (int) $_SESSION['UserId'];
$sql = "UPDATE dbo.SMUserOnline SET LastSeenAt = SYSDATETIME() WHERE UserId = ?;
        IF @@ROWCOUNT = 0
            INSERT INTO dbo.SMUserOnline (UserId, LastSeenAt) VALUES (?, SYSDATETIME());";
$stmt = sqlsrv_query($conn, $sql, [$userId, $userId]);

if ($stmt === false) {
    http_response_code(500);
    $logDir = __DIR__ . '/../logs';
    if (is_dir($logDir) || @mkdir($logDir, 0775, true)) {
        $entry = [
            'timestamp' => date(DATE_ATOM), 'severity' => 'ERROR', 'request_id' => $requestId,
            'module' => 'user_presence', 'action' => 'heartbeat', 'user_id' => $userId,
            'message' => 'Gagal memperbarui status online.', 'source_file' => __FILE__, 'source_line' => __LINE__,
            'context' => ['stage' => 'presence_upsert', 'dependency' => 'SQL Server'],
        ];
        @file_put_contents($logDir . '/error-' . date('Y-m-d') . '.log', json_encode($entry) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
    echo json_encode(['alive' => false, 'message' => 'Status online gagal diperbarui.', 'request_id' => $requestId]);
    exit;
}

sqlsrv_free_stmt($stmt);

$countStmt = sqlsrv_query(
    $conn,
    "SELECT COUNT_BIG(*) AS OnlineUsers
     FROM dbo.SMUserOnline
     WHERE LastSeenAt >= DATEADD(MINUTE, -5, SYSDATETIME())"
);
$onlineUsers = null;
if ($countStmt !== false && ($countRow = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC))) {
    $onlineUsers = (int) $countRow['OnlineUsers'];
    sqlsrv_free_stmt($countStmt);
}

sqlsrv_close($conn);
echo json_encode(['alive' => true, 'ts' => time(), 'online_users' => $onlineUsers]);
exit;
