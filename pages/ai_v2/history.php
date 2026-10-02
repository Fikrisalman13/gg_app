<?php
// pages/ai_v2/history.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = ai_session_user_id();
if (!$userId) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Unauthorized']);
    exit;
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
if ($limit < 1 || $limit > 200) $limit = 50;

global $AI_TABLES, $AI_COLS;
$table = $AI_TABLES['history'];
$cols = $AI_COLS['history'];

$hasCreatedAt = ai_db_has_col($table, $cols['created_at']);
$hasId = ai_db_has_col($table, $cols['id']);

$orderBy = [];
if ($hasCreatedAt) {
    $orderBy[] = "[{$cols['created_at']}] DESC";
}
if ($hasId) {
    $orderBy[] = "[{$cols['id']}] DESC";
}
if (empty($orderBy)) {
    $orderBy[] = "[{$cols['role']}] DESC";
}

$createdAtSelect = $hasCreatedAt ? "[{$cols['created_at']}]" : "NULL";
$sql = "SELECT TOP {$limit}
            {$createdAtSelect} AS created_at,
            [{$cols['role']}] AS role,
            [{$cols['message']}] AS message
        FROM {$table} WITH (NOLOCK)
        WHERE [{$cols['user_id']}] = ?
        ORDER BY " . implode(', ', $orderBy);

$rows = ai_db_fetch_all(ai_db_query($sql, [$userId]));
$rows = array_reverse($rows);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'data' => $rows]);
