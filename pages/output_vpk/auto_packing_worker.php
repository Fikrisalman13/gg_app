<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../../koneksi3.php';
require_once __DIR__ . '/auto_packing_lib.php';

if (!$conn) {
    fwrite(STDERR, "SQL Server connection unavailable.\n");
    exit(1);
}

$lockPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gg-output-vpk-auto.lock';
$lock = @fopen($lockPath, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Output VPK worker already running.\n");
    exit(1);
}

try {
    $status = outputVpkAutoStatus($conn);
    $config = $status['config'];
    if ((int)$config['is_enabled'] !== 1) {
        exit(0);
    }
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
    $scheduled = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $now->format('Y-m-d') . ' ' . $config['run_time']->format('H:i:s'), new DateTimeZone('Asia/Jakarta'));
    if (!$scheduled || $now < $scheduled) {
        exit(0);
    }
    $targetDate = $now->modify('-1 day')->format('Y-m-d');
    $result = outputVpkAutoPull($conn, $conn3, $targetDate, 'scheduler');
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit($result['status'] === 'success' || $result['status'] === 'empty' || $result['status'] === 'existing' ? 0 : 1);
} catch (Throwable $error) {
    $operationId = outputVpkAutoOperationId();
    outputVpkAutoLogFailure($operationId, 'scheduler', $error->getMessage());
    fwrite(STDERR, "Output VPK worker failed. Operation ID: {$operationId}\n");
    exit(1);
} finally {
    if (isset($lock) && is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}