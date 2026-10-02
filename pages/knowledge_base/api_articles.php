<?php
require_once __DIR__ . '/kb_helpers.php';

$context = kb_context($conn);
$draw = (int)($_GET['draw'] ?? 1);
$start = max(0, (int)($_GET['start'] ?? 0));
$length = min(100, max(10, (int)($_GET['length'] ?? 10)));
$searchTerm = trim($_GET['search']['value'] ?? '');
$whereClause = 'WHERE article.is_deleted = 0';
$queryParams = [];

if (!$context['is_admin']) {
    $whereClause .= ' AND article.bagian_id = ?';
    $queryParams[] = $context['bagian_id'];
}

if ($searchTerm !== '') {
    $whereClause .= ' AND (article.title LIKE ? OR category.name LIKE ? OR article.message_html LIKE ?)';
    $searchLike = '%' . $searchTerm . '%';
    array_push($queryParams, $searchLike, $searchLike, $searchLike);
}

$countSql = "
    SELECT COUNT(1) AS total
    FROM dbo.knowledge_base_articles article
    INNER JOIN dbo.knowledge_base_categories category ON category.id = article.category_id
    $whereClause
";
$countStatement = sqlsrv_query($conn, $countSql, $queryParams);
if ($countStatement === false) {
    kb_fail('api_articles', 'Gagal menghitung artikel.', 500);
}

$totalRows = (int)(sqlsrv_fetch_array($countStatement, SQLSRV_FETCH_ASSOC)['total'] ?? 0);
$listSql = "
    SELECT
        article.id,
        article.title,
        article.bagian_id,
        article.bagian_name_snapshot,
        article.created_at,
        category.name AS category_name,
        creator.UserName AS created_by_name
    FROM dbo.knowledge_base_articles article
    INNER JOIN dbo.knowledge_base_categories category ON category.id = article.category_id
    LEFT JOIN dbo.SMUserMs creator ON creator.UserId = article.created_by
    $whereClause
    ORDER BY article.created_at DESC
    OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
";
$listParams = array_merge($queryParams, [$start, $length]);
$listStatement = sqlsrv_query($conn, $listSql, $listParams);
if ($listStatement === false) {
    kb_fail('api_articles', 'Gagal membaca artikel.', 500);
}

$rows = [];
while ($article = sqlsrv_fetch_array($listStatement, SQLSRV_FETCH_ASSOC)) {
    $articleId = (int)$article['id'];
    $createdAt = $article['created_at'] instanceof DateTimeInterface
        ? $article['created_at']->format('Y-m-d H:i')
        : '';

    $rows[] = [
        'row_number' => null,
        'title' => htmlspecialchars($article['title'] ?? '', ENT_QUOTES, 'UTF-8'),
        'category' => htmlspecialchars($article['category_name'] ?? '', ENT_QUOTES, 'UTF-8'),
        'bagian' => htmlspecialchars($article['bagian_name_snapshot'] ?? '', ENT_QUOTES, 'UTF-8'),
        'created_by' => htmlspecialchars($article['created_by_name'] ?? '', ENT_QUOTES, 'UTF-8'),
        'created_at' => $createdAt,
        'action' => kb_article_action_buttons($articleId),
    ];
}

kb_json([
    'draw' => $draw,
    'recordsTotal' => $totalRows,
    'recordsFiltered' => $totalRows,
    'data' => $rows,
]);

function kb_article_action_buttons(int $articleId): string
{
    return '<div class="action-button-group">'
        . '<button type="button" class="btn btn-info btn-sm action-btn kb-action" '
        . 'data-action="view" data-id="' . $articleId . '" title="Lihat artikel">'
        . '<i class="fas fa-eye"></i></button>'
        . '<button type="button" class="btn btn-warning btn-sm action-btn kb-action" '
        . 'data-action="edit" data-id="' . $articleId . '" title="Edit artikel">'
        . '<i class="fas fa-edit"></i></button>'
        . '<button type="button" class="btn btn-danger btn-sm action-btn kb-action" '
        . 'data-action="delete" data-id="' . $articleId . '" title="Hapus artikel">'
        . '<i class="fas fa-trash"></i></button>'
        . '</div>';
}
