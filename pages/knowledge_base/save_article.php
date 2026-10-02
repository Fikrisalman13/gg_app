<?php
require_once __DIR__ . '/kb_helpers.php';

$context = kb_context($conn);
$articleId = (int)($_POST['id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$categoryName = trim($_POST['category_name'] ?? '');
$messageHtml = kb_clean_html($_POST['message_html'] ?? '');

if ($title === '' || mb_strlen($title) > 200) {
    kb_fail('save_article', 'Nama artikel tidak valid.', 422);
}

if ($categoryName === '') {
    kb_fail('save_article', 'Kategori wajib diisi.', 422);
}

if ($messageHtml === '') {
    kb_fail('save_article', 'Message wajib diisi.', 422);
}

if (!sqlsrv_begin_transaction($conn)) {
    kb_fail('save_article', 'Gagal memulai transaksi.', 500);
}

$targetBagianId = $context['bagian_id'];
$targetBagianName = $context['bagian'];

if ($articleId > 0) {
    $existingArticle = kb_find_editable_article($conn, $context, $articleId);
    $targetBagianId = (int)$existingArticle['bagian_id'];
    $targetBagianName = (string)($existingArticle['bagian_name_snapshot'] ?? $context['bagian']);
}

$categoryId = kb_get_or_create_category($conn, $categoryName, $targetBagianId, $context['user_id']);
$saveStatement = $articleId > 0
    ? kb_update_article($conn, $articleId, $title, $categoryId, $messageHtml, $context['user_id'])
    : kb_insert_article($conn, $title, $categoryId, $targetBagianId, $targetBagianName, $messageHtml, $context['user_id']);

if ($saveStatement === false) {
    sqlsrv_rollback($conn);
    kb_fail('save_article', 'Gagal menyimpan artikel.', 500);
}

if ($articleId <= 0) {
    $insertedRow = sqlsrv_fetch_array($saveStatement, SQLSRV_FETCH_ASSOC);
    $articleId = (int)($insertedRow['id'] ?? 0);
}

if (!kb_sync_article_visibility($conn, $articleId, $targetBagianId, [], $context['user_id'])) {
    sqlsrv_rollback($conn);
    kb_fail('save_article', 'Gagal menyimpan Bagian pemilik.', 500);
}

sqlsrv_commit($conn);
kb_json(['ok' => true, 'id' => $articleId]);

/**
 * Fetch an existing article and enforce Bagian/Admin edit access.
 */
function kb_find_editable_article($conn, array $context, int $articleId): array
{
    $statement = sqlsrv_query(
        $conn,
        'SELECT bagian_id, bagian_name_snapshot FROM dbo.knowledge_base_articles WHERE id = ? AND is_deleted = 0',
        [$articleId]
    );
    $article = $statement ? sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC) : null;

    if (!$article || !kb_can_access($context, (int)$article['bagian_id'])) {
        sqlsrv_rollback($conn);
        kb_fail('save_article', 'Artikel tidak ditemukan atau tidak dapat diedit.', 404);
    }

    return $article;
}

/**
 * Insert a new article for the current user's Bagian.
 */
function kb_insert_article(
    $conn,
    string $title,
    int $categoryId,
    int $bagianId,
    string $bagianName,
    string $messageHtml,
    int $userId
) {
    return sqlsrv_query(
        $conn,
        'INSERT INTO dbo.knowledge_base_articles
            (title, category_id, bagian_id, bagian_name_snapshot, message_html, created_by)
         OUTPUT INSERTED.id
         VALUES (?, ?, ?, ?, ?, ?)',
        [$title, $categoryId, $bagianId, $bagianName, $messageHtml, $userId]
    );
}

/**
 * Update article content after trustee access has been verified.
 */
function kb_update_article($conn, int $articleId, string $title, int $categoryId, string $messageHtml, int $userId)
{
    return sqlsrv_query(
        $conn,
        'UPDATE dbo.knowledge_base_articles
         SET title = ?, category_id = ?, message_html = ?, updated_at = SYSDATETIME(), updated_by = ?
         WHERE id = ?',
        [$title, $categoryId, $messageHtml, $userId, $articleId]
    );
}
