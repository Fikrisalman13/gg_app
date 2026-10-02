<?php
// pages/ai_v2/documents.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = ai_session_user_id();
if (!$userId) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

global $AI_TABLES, $AI_COLS;
$docTable = $AI_TABLES['documents'];
$docCols = $AI_COLS['documents'];
$idCol = $docCols['id'] ?? null;
$titleCol = $docCols['title'] ?? null;
$activeCol = $docCols['is_active'] ?? null;

if (!$idCol || !$titleCol) {
    echo json_encode(['ok' => true, 'data' => []]);
    exit;
}

if (!ai_db_has_col($docTable, $idCol) || !ai_db_has_col($docTable, $titleCol)) {
    echo json_encode(['ok' => true, 'data' => []]);
    exit;
}

$where = "1=1";
$params = [];
if ($activeCol && ai_db_has_col($docTable, $activeCol)) {
    $where .= " AND [$activeCol] = 1";
}

$sql = "SELECT TOP 200
            [$idCol] AS document_id,
            [$titleCol] AS title
        FROM {$docTable} WITH (NOLOCK)
        WHERE {$where}
        ORDER BY [$titleCol] ASC";

try {
    $rows = ai_db_fetch_all(ai_db_query($sql, $params));
    echo json_encode(['ok' => true, 'data' => $rows]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'DB error']);
}
