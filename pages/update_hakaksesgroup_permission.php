<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json; charset=utf-8');

$requestId = bin2hex(random_bytes(8));

function permissionResponse(int $status, array $payload): void
{
    global $requestId;
    http_response_code($status);
    echo json_encode(array_merge(['request_id' => $requestId], $payload), JSON_UNESCAPED_UNICODE);
    exit;
}

function logPermissionFailure(string $severity, string $message, array $context = []): void
{
    global $requestId;
    $logDirectory = dirname(__DIR__) . '/logs';
    if (!is_dir($logDirectory)) {
        @mkdir($logDirectory, 0775, true);
    }

    $entry = [
        'timestamp' => date(DATE_ATOM),
        'severity' => in_array($severity, ['WARNING', 'ERROR', 'CRITICAL', 'SECURITY'], true) ? $severity : 'ERROR',
        'request_id' => $requestId,
        'module' => 'hakaksesgroup',
        'action' => 'toggle_permission',
        'user' => isset($_SESSION['UserName']) ? substr((string) $_SESSION['UserName'], 0, 40) : null,
        'message' => substr($message, 0, 200),
        'source_file' => basename(__FILE__),
        'source_line' => __LINE__,
        'context' => $context
    ];

    @file_put_contents(
        $logDirectory . '/error-' . date('Y-m-d') . '.log',
        json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

if (!isset($_SESSION['UserName'], $_SESSION['GroupId'])) {
    logPermissionFailure('SECURITY', 'Permission update rejected: unauthenticated session', ['stage' => 'authentication']);
    permissionResponse(401, ['ok' => false, 'message' => 'Sesi login berakhir. Silakan login kembali.']);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    permissionResponse(405, ['ok' => false, 'message' => 'Metode request tidak diizinkan.']);
}

$sessionToken = $_SESSION['hakaksesgroup_csrf'] ?? '';
$postedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
if ($sessionToken === '' || $postedToken === '' || !hash_equals($sessionToken, $postedToken)) {
    logPermissionFailure('SECURITY', 'Permission update rejected: invalid CSRF token', ['stage' => 'csrf']);
    permissionResponse(403, ['ok' => false, 'message' => 'Token keamanan tidak valid. Muat ulang halaman.']);
}

require '../koneksi.php';
require '../includes/menu_constants.php';
require '../includes/permissions.php';

if (!$conn) {
    logPermissionFailure('CRITICAL', 'Permission update failed: database unavailable', ['stage' => 'connection', 'dependency' => 'SQL Server GG']);
    permissionResponse(500, ['ok' => false, 'message' => 'Layanan database tidak tersedia.']);
}

$actorPermission = userPermissions($conn, MENU_GROUP_ACCESS);
if (($actorPermission['CanEdit'] ?? 0) != 1) {
    logPermissionFailure('SECURITY', 'Permission update rejected: CanEdit denied', ['stage' => 'authorization']);
    permissionResponse(403, ['ok' => false, 'message' => 'Anda tidak memiliki hak untuk mengubah hak akses.']);
}

$trusteeId = filter_input(INPUT_POST, 'trustee_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$permission = isset($_POST['permission']) ? (string) $_POST['permission'] : '';
$value = filter_input(INPUT_POST, 'value', FILTER_VALIDATE_INT);
$allowedPermissions = ['CanView', 'CanAdd', 'CanEdit', 'CanDelete'];

if ($trusteeId === false || $trusteeId === null || !in_array($permission, $allowedPermissions, true) || !in_array($value, [0, 1], true)) {
    permissionResponse(422, ['ok' => false, 'message' => 'Data perubahan hak akses tidak valid.']);
}

$sql = "UPDATE dbo.SMGroupTrustee SET {$permission} = ? WHERE TrusteeId = ?";
$stmt = sqlsrv_query($conn, $sql, [$value, $trusteeId]);

if ($stmt === false) {
    logPermissionFailure('ERROR', 'Permission update query failed', [
        'stage' => 'database_update',
        'dependency' => 'SQL Server GG',
        'trustee_id' => $trusteeId,
        'permission' => $permission
    ]);
    permissionResponse(500, ['ok' => false, 'message' => 'Hak akses gagal disimpan.']);
}

$affectedRows = sqlsrv_rows_affected($stmt);
sqlsrv_free_stmt($stmt);

if ($affectedRows === 0) {
    permissionResponse(404, ['ok' => false, 'message' => 'Data hak akses tidak ditemukan.']);
}

if ($affectedRows === false || $affectedRows < 0) {
    logPermissionFailure('ERROR', 'Permission update result could not be verified', [
        'stage' => 'verify_update',
        'trustee_id' => $trusteeId,
        'permission' => $permission
    ]);
    permissionResponse(500, ['ok' => false, 'message' => 'Hasil penyimpanan hak akses tidak dapat diverifikasi.']);
}

permissionResponse(200, [
    'ok' => true,
    'message' => 'Hak akses berhasil diperbarui.',
    'trustee_id' => $trusteeId,
    'permission' => $permission,
    'value' => $value
]);
