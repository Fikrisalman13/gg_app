<?php
require_once __DIR__ . '/kb_helpers.php';

$context = kb_context($conn);
$articleId = (int)($_POST['id'] ?? 0);

if ($articleId <= 0) {
    kb_fail('delete_article', 'Artikel tidak valid.', 422);
}

$statement = sqlsrv_query(
    $conn,
    'SELECT bagian_id FROM dbo.knowledge_base_articles WHERE id = ? AND is_deleted = 0',
    [$articleId]
);
$article = $statement ? sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC) : null;

if (!$article || !kb_can_access($context, (int)$article['bagian_id'])) {
    kb_fail('delete_article', 'Artikel tidak ditemukan atau tidak dapat dihapus.', 404);
}

$deleteStatement = sqlsrv_query(
    $conn,
    'UPDATE dbo.knowledge_base_articles
     SET is_deleted = 1,
         deleted_at = SYSDATETIME(),
         deleted_by = ?,
         updated_at = SYSDATETIME(),
         updated_by = ?
     WHERE id = ?',
    [$context['user_id'], $context['user_id'], $articleId]
);

if ($deleteStatement === false) {
    kb_fail('delete_article', 'Gagal menghapus artikel.', 500);
}

kb_json(['ok' => true]);
