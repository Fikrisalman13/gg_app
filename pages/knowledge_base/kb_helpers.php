<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';

/**
 * Create a short request ID for correlating user-facing errors with log entries.
 */
function kb_request_id(): string
{
    return bin2hex(random_bytes(8));
}

/**
 * Send a JSON response and stop endpoint execution.
 */
function kb_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

/**
 * Write a sanitized structured error entry to the project daily error log.
 */
function kb_log(string $severity, string $action, string $message, array $context = [], ?string $requestId = null): string
{
    $requestId = $requestId ?: kb_request_id();
    $logDirectory = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/logs';

    if (!is_dir($logDirectory)) {
        @mkdir($logDirectory, 0775, true);
    }

    $entry = [
        'timestamp' => date('c'),
        'severity' => $severity,
        'request_id' => $requestId,
        'module' => 'knowledge_base',
        'action' => $action,
        'user' => $_SESSION['UserName'] ?? null,
        'message' => mb_substr($message, 0, 300),
        'source_file' => basename($_SERVER['SCRIPT_NAME'] ?? ''),
        'line' => null,
        'context' => $context,
    ];

    @file_put_contents(
        $logDirectory . '/error-' . date('Y-m-d') . '.log',
        json_encode($entry) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );

    return $requestId;
}

/**
 * Return a safe JSON error and log it with a request ID.
 */
function kb_fail(string $action, string $message, int $status = 400, array $context = []): void
{
    $severity = $status >= 500 ? 'ERROR' : 'WARNING';
    $requestId = kb_log($severity, $action, $message, $context);

    kb_json([
        'ok' => false,
        'message' => $message,
        'request_id' => $requestId,
    ], $status);
}

/**
 * Load current user context and Bagian from SQL Server.
 *
 * Side effect: sends JSON error response if the user is not usable for Knowledge Base.
 */
function kb_context($conn): array
{
    if (empty($_SESSION['UserId'])) {
        kb_fail('auth', 'Silakan login terlebih dahulu.', 401);
    }

    $sql = "
        SELECT u.UserId, u.UserName, u.GroupId, u.EmpId, employee.id_bag, bagian.bagian
        FROM dbo.SMUserMs u
        LEFT JOIN dbo.m_emp employee ON u.EmpId = employee.id_emp
        LEFT JOIN dbo.m_bag bagian ON employee.id_bag = bagian.id_bag
        WHERE u.UserId = ?
    ";
    $statement = sqlsrv_query($conn, $sql, [(int)$_SESSION['UserId']]);
    if ($statement === false) {
        kb_fail('context', 'Gagal membaca data user.', 500, ['stage' => 'query_user']);
    }

    $user = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC);
    if (!$user) {
        kb_fail('context', 'User tidak ditemukan.', 401);
    }

    if (empty($user['id_bag'])) {
        kb_fail('context', 'Bagian user belum terdaftar.', 403);
    }

    return [
        'user_id' => (int)$user['UserId'],
        'username' => (string)$user['UserName'],
        'group_id' => (int)($user['GroupId'] ?? 0),
        'emp_id' => (int)($user['EmpId'] ?? 0),
        'bagian_id' => (int)$user['id_bag'],
        'bagian' => (string)($user['bagian'] ?? ''),
        'is_admin' => ((int)($user['GroupId'] ?? 0) === 1),
    ];
}

/**
 * Check Trustee Model A: same Bagian or administrator GroupId 1.
 */
function kb_can_access(array $context, int $bagianId): bool
{
    return $context['is_admin'] || $context['bagian_id'] === $bagianId;
}

/** Check article visibility through canonical owner or sharing targets. */
function kb_can_view_article($conn, array $context, int $articleId, int $ownerBagianId): bool
{
    if ($context['is_admin'] || $context['bagian_id'] === $ownerBagianId) {
        return true;
    }

    $statement = sqlsrv_query(
        $conn,
        'SELECT 1 FROM dbo.knowledge_base_article_bagian WHERE article_id = ? AND bagian_id = ?',
        [$articleId, $context['bagian_id']]
    );
    return $statement !== false && sqlsrv_fetch_array($statement, SQLSRV_FETCH_NUMERIC) !== null;
}

/**
 * Remove unsafe executable HTML from Summernote content.
 *
 * This is a small legacy-safe sanitizer. Replace with a full HTML purifier if richer
 * embeds or broader HTML trust boundaries become required.
 */
function kb_clean_html(string $html): string
{
    $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
    $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    $html = preg_replace('/(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i', '$1="#"', $html);

    return trim($html);
}

/**
 * Find or create a per-Bagian category.
 *
 * The unique `(bagian_id, name)` constraint prevents duplicate categories in one Bagian.
 */
function kb_get_or_create_category($conn, string $name, int $bagianId, int $userId): int
{
    $categoryName = trim(preg_replace('/\s+/', ' ', $name));
    if ($categoryName === '' || mb_strlen($categoryName) > 150) {
        kb_fail('category', 'Kategori tidak valid.', 422);
    }

    $existingCategoryId = kb_find_category_id($conn, $categoryName, $bagianId);
    if ($existingCategoryId > 0) {
        return $existingCategoryId;
    }

    $insertStatement = sqlsrv_query(
        $conn,
        'INSERT INTO dbo.knowledge_base_categories (name, bagian_id, created_by)
         OUTPUT INSERTED.id
         VALUES (?, ?, ?)',
        [$categoryName, $bagianId, $userId]
    );

    if ($insertStatement === false) {
        $existingCategoryId = kb_find_category_id($conn, $categoryName, $bagianId);
        if ($existingCategoryId > 0) {
            return $existingCategoryId;
        }

        kb_fail('category', 'Gagal menyimpan kategori.', 500, ['stage' => 'insert_category']);
    }

    $insertedCategory = sqlsrv_fetch_array($insertStatement, SQLSRV_FETCH_ASSOC);
    return (int)$insertedCategory['id'];
}

/**
 * Find active category ID by Bagian and exact normalized name.
 */
function kb_find_category_id($conn, string $categoryName, int $bagianId): int
{
    $statement = sqlsrv_query(
        $conn,
        'SELECT id FROM dbo.knowledge_base_categories WHERE bagian_id = ? AND name = ? AND is_active = 1',
        [$bagianId, $categoryName]
    );

    if ($statement && $category = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC)) {
        return (int)$category['id'];
    }

    return 0;
}

/** Replace article visibility targets after owner authorization. */
function kb_sync_article_visibility($conn, int $articleId, int $ownerBagianId, array $sharedBagianIds, int $userId): bool
{
    $targetIds = array_values(array_unique(array_map('intval', $sharedBagianIds)));
    $targetIds[] = $ownerBagianId;
    $targetIds = array_values(array_unique($targetIds));
    $placeholders = implode(',', array_fill(0, count($targetIds), '?'));
    $check = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM dbo.m_bag WHERE id_bag IN ($placeholders)", $targetIds);
    $row = $check ? sqlsrv_fetch_array($check, SQLSRV_FETCH_ASSOC) : null;
    if (!$row || (int)$row['total'] !== count($targetIds)) { kb_fail('save_article', 'Target Bagian tidak valid.', 422); }
    if (sqlsrv_query($conn, 'DELETE FROM dbo.knowledge_base_article_bagian WHERE article_id = ?', [$articleId]) === false) { return false; }
    foreach ($targetIds as $targetId) {
        if (sqlsrv_query($conn, 'INSERT INTO dbo.knowledge_base_article_bagian (article_id, bagian_id, created_by) VALUES (?, ?, ?)', [$articleId, $targetId, $userId]) === false) { return false; }
    }
    return true;
}
