<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../../koneksi3.php';
require_once __DIR__ . '/auto_packing_lib.php';

try {
    outputVpkAutoRequireEditor($conn);
    $targetDate = outputVpkAutoDate((string)($_GET['target_date'] ?? ''));
    $existing = sqlsrv_query($conn, 'SELECT TOP 1 id FROM dbo.packing_output WHERE tanggal = ?', [$targetDate]);
    $existingRow = $existing ? sqlsrv_fetch_array($existing, SQLSRV_FETCH_ASSOC) : null;
    if ($existing !== false) {
        sqlsrv_free_stmt($existing);
    }
    if ($existingRow) {
        echo json_encode(['ok' => true, 'state' => 'existing', 'message' => 'Data Packing untuk tanggal ini sudah ada dan tidak akan diubah.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $summary = outputVpkAutoFetchSusut($conn3, $targetDate);
    if ($summary === null) {
        echo json_encode(['ok' => true, 'state' => 'empty', 'message' => 'Tarikan Susut tidak memiliki data untuk tanggal ini.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    echo json_encode(['ok' => true, 'state' => 'ready', 'summary' => $summary, 'preview_token' => outputVpkAutoPreviewToken($targetDate)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    http_response_code($error instanceof InvalidArgumentException ? 422 : 403);
    echo json_encode(['ok' => false, 'message' => $error instanceof InvalidArgumentException ? $error->getMessage() : 'Preview tidak dapat dimuat.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}