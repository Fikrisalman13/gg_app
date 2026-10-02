<?php
require_once __DIR__ . '/kb_helpers.php';

$context = kb_context($conn);
$searchTerm = trim($_GET['term'] ?? '');
$whereClause = $context['is_admin']
    ? 'WHERE category.is_active = 1'
    : 'WHERE category.is_active = 1 AND category.bagian_id = ?';
$queryParams = $context['is_admin'] ? [] : [$context['bagian_id']];

if ($searchTerm !== '') {
    $whereClause .= ' AND category.name LIKE ?';
    $queryParams[] = '%' . $searchTerm . '%';
}

$sql = "
    SELECT TOP 50 category.id, category.name, category.bagian_id, bagian.bagian
    FROM dbo.knowledge_base_categories category
    LEFT JOIN dbo.m_bag bagian ON bagian.id_bag = category.bagian_id
    $whereClause
    ORDER BY category.name
";
$statement = sqlsrv_query($conn, $sql, $queryParams);
if ($statement === false) {
    kb_fail('categories', 'Gagal membaca kategori.', 500);
}

$categories = [];
while ($category = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC)) {
    $displayText = $category['name'];
    if ($context['is_admin']) {
        $displayText .= ' - ' . ($category['bagian'] ?? '-');
    }

    $categories[] = [
        'id' => (int)$category['id'],
        'text' => $displayText,
        'name' => $category['name'],
    ];
}

kb_json(['ok' => true, 'results' => $categories]);
