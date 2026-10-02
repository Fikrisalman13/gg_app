<?php
require_once __DIR__ . '/kb_helpers.php';
$context = kb_context($conn);
$level = $_GET['level'] ?? 'root';
$bagianId = (int)($_GET['bagian_id'] ?? 0);
$categoryId = (int)($_GET['category_id'] ?? 0);
$search = trim((string)($_GET['search'] ?? ''));
$like = '%' . $search . '%';
if (!in_array($level, ['root', 'bagian', 'category'], true)) { kb_fail('archive', 'Level arsip tidak valid.', 422); }
$visibilityJoin = $context['is_admin'] ? '' : ' INNER JOIN dbo.knowledge_base_article_bagian viewer_visibility ON viewer_visibility.article_id = a.id AND viewer_visibility.bagian_id = ?';
$viewerParams = $context['is_admin'] ? [] : [$context['bagian_id']];

if ($level === 'root' && $search !== '') {
    $sql = "SELECT a.id, a.bagian_id, a.title, a.created_at, a.updated_at, a.bagian_name_snapshot,
                   (SELECT COUNT(*) FROM dbo.knowledge_base_article_bagian share_count WHERE share_count.article_id = a.id) AS share_count,
                   owner_bagian.bagian AS bagian_name, c.name AS category_name, u.UserName AS created_by_name
            FROM dbo.knowledge_base_articles a
            $visibilityJoin
            INNER JOIN dbo.m_bag owner_bagian ON owner_bagian.id_bag = a.bagian_id
            INNER JOIN dbo.knowledge_base_categories c ON c.id = a.category_id
            LEFT JOIN dbo.SMUserMs u ON u.UserId = a.created_by
            WHERE a.is_deleted = 0 AND (a.title LIKE ? OR a.message_html LIKE ? OR c.name LIKE ? OR owner_bagian.bagian LIKE ?)
            ORDER BY COALESCE(a.updated_at, a.created_at) DESC";
    $statement = sqlsrv_query($conn, $sql, array_merge($viewerParams, [$like, $like, $like, $like]));
    if ($statement === false) { kb_fail('archive', 'Gagal mencari artikel.', 500, ['stage' => 'global_search']); }
    $articles = [];
    while ($row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC)) {
        $date = $row['updated_at'] ?? $row['created_at'];
        $articles[] = ['id' => (int)$row['id'], 'title' => htmlspecialchars((string)$row['title'], ENT_QUOTES, 'UTF-8'), 'bagian' => htmlspecialchars((string)$row['bagian_name'], ENT_QUOTES, 'UTF-8'), 'category' => htmlspecialchars((string)$row['category_name'], ENT_QUOTES, 'UTF-8'), 'created_by' => htmlspecialchars((string)$row['created_by_name'], ENT_QUOTES, 'UTF-8'), 'updated_at' => $date instanceof DateTimeInterface ? $date->format('Y-m-d H:i') : '', 'can_manage' => $context['is_admin'] || (int)$row['bagian_id'] === $context['bagian_id'], 'is_shared' => (int)$row['share_count'] > 1];
    }
    kb_json(['ok' => true, 'level' => 'search', 'folders' => [], 'articles' => $articles]);
}

if ($level === 'root') {
    $sql = "SELECT owner.id_bag AS id, owner.bagian AS name, COUNT(DISTINCT a.category_id) AS category_count, COUNT(a.id) AS article_count
            FROM dbo.m_bag owner
            INNER JOIN dbo.knowledge_base_articles a ON a.bagian_id = owner.id_bag AND a.is_deleted = 0
            $visibilityJoin
            GROUP BY owner.id_bag, owner.bagian HAVING COUNT(a.id) > 0 ORDER BY owner.bagian";
    $statement = sqlsrv_query($conn, $sql, $viewerParams);
    if ($statement === false) { kb_fail('archive', 'Gagal membaca folder Bagian.', 500, ['stage' => 'bagian_folders']); }
    $folders = [];
    while ($row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC)) { $folders[] = ['id' => (int)$row['id'], 'name' => (string)$row['name'], 'category_count' => (int)$row['category_count'], 'article_count' => (int)$row['article_count']]; }
    kb_json(['ok' => true, 'level' => $level, 'folders' => $folders, 'articles' => []]);
}

if ($level === 'bagian') {
    $sql = "SELECT c.id, c.name, COUNT(DISTINCT a.id) AS article_count
            FROM dbo.knowledge_base_categories c
            INNER JOIN dbo.knowledge_base_articles a ON a.category_id = c.id AND a.bagian_id = ? AND a.is_deleted = 0
            $visibilityJoin
            WHERE c.is_active = 1 AND (? = '' OR c.name LIKE ?)
            GROUP BY c.id, c.name ORDER BY c.name";
    $statement = sqlsrv_query($conn, $sql, array_merge([$bagianId], $viewerParams, [$search, $like]));
    if ($statement === false) { kb_fail('archive', 'Gagal membaca folder kategori.', 500, ['stage' => 'category_folders']); }
    $folders = [];
    while ($row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC)) { $folders[] = ['id' => (int)$row['id'], 'name' => (string)$row['name'], 'article_count' => (int)$row['article_count']]; }
    kb_json(['ok' => true, 'level' => $level, 'folders' => $folders, 'articles' => []]);
}

$sql = "SELECT a.id, a.bagian_id, a.title, a.created_at, a.updated_at, a.bagian_name_snapshot, (SELECT COUNT(*) FROM dbo.knowledge_base_article_bagian share_count WHERE share_count.article_id = a.id) AS share_count, c.name AS category_name, u.UserName AS created_by_name
        FROM dbo.knowledge_base_articles a
        $visibilityJoin
        INNER JOIN dbo.knowledge_base_categories c ON c.id = a.category_id
        LEFT JOIN dbo.SMUserMs u ON u.UserId = a.created_by
        WHERE a.bagian_id = ? AND a.category_id = ? AND a.is_deleted = 0 AND (? = '' OR a.title LIKE ? OR a.message_html LIKE ?)
        ORDER BY COALESCE(a.updated_at, a.created_at) DESC";
$statement = sqlsrv_query($conn, $sql, array_merge($viewerParams, [$bagianId, $categoryId, $search, $like, $like]));
if ($statement === false) { kb_fail('archive', 'Gagal membaca artikel arsip.', 500, ['stage' => 'article_files']); }
$articles = [];
while ($row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC)) {
    $date = $row['updated_at'] ?? $row['created_at'];
    $articles[] = ['id' => (int)$row['id'], 'title' => htmlspecialchars((string)$row['title'], ENT_QUOTES, 'UTF-8'), 'bagian' => htmlspecialchars((string)$row['bagian_name_snapshot'], ENT_QUOTES, 'UTF-8'), 'category' => htmlspecialchars((string)$row['category_name'], ENT_QUOTES, 'UTF-8'), 'created_by' => htmlspecialchars((string)$row['created_by_name'], ENT_QUOTES, 'UTF-8'), 'updated_at' => $date instanceof DateTimeInterface ? $date->format('Y-m-d H:i') : '', 'can_manage' => $context['is_admin'] || (int)$row['bagian_id'] === $context['bagian_id'], 'is_shared' => (int)$row['share_count'] > 1];
}
kb_json(['ok' => true, 'level' => $level, 'folders' => [], 'articles' => $articles]);