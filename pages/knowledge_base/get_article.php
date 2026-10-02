<?php
require_once __DIR__ . '/kb_helpers.php';

$context = kb_context($conn);
$articleId = (int)($_GET['id'] ?? 0);

if ($articleId <= 0) {
    kb_fail('get_article', 'Artikel tidak valid.', 422);
}

$sql = "
    SELECT article.*, category.name AS category_name,
           owner_bagian.bagian AS owner_bagian_name
    FROM dbo.knowledge_base_articles article
    INNER JOIN dbo.knowledge_base_categories category ON category.id = article.category_id
    INNER JOIN dbo.m_bag owner_bagian ON owner_bagian.id_bag = article.bagian_id
    WHERE article.id = ? AND article.is_deleted = 0
";
$statement = sqlsrv_query($conn, $sql, [$articleId]);
if ($statement === false) {
    kb_fail('get_article', 'Gagal membaca artikel.', 500);
}

$article = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC);
$action = $_GET['action'] ?? 'view';
$canView = $article && kb_can_view_article($conn, $context, $articleId, (int)($article['bagian_id'] ?? 0));
$canEdit = $article && kb_can_access($context, (int)($article['bagian_id'] ?? 0));
if (!$article || ($action === 'edit' ? !$canEdit : !$canView)) {
    kb_fail('get_article', 'Artikel tidak ditemukan atau tidak dapat diakses.', 404);
}

$sharedStatement = sqlsrv_query($conn, 'SELECT bagian_id, b.bagian AS bagian_name FROM dbo.knowledge_base_article_bagian v INNER JOIN dbo.m_bag b ON b.id_bag = v.bagian_id WHERE v.article_id = ? ORDER BY b.bagian', [$articleId]);
$shared = [];
if ($sharedStatement !== false) {
    while ($row = sqlsrv_fetch_array($sharedStatement, SQLSRV_FETCH_ASSOC)) { $shared[] = ['id' => (int)$row['bagian_id'], 'name' => (string)$row['bagian_name']]; }
}

kb_json([
    'ok' => true,
    'article' => [
        'id' => (int)$article['id'],
        'title' => $article['title'],
        'category_id' => (int)$article['category_id'],
        'category_name' => $article['category_name'],
        'bagian_id' => (int)$article['bagian_id'],
        'bagian' => $article['bagian_name_snapshot'],
        'owner_bagian' => $article['owner_bagian_name'],
        'shared_bagian' => $shared,
        'message_html' => $article['message_html'],
    ],
]);
