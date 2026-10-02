<?php
require_once __DIR__ . '/kb_helpers.php';
$context = kb_context($conn);
$articleId = (int)($_POST['id'] ?? 0);
$shareMode = $_POST['share_mode'] ?? 'specific';
$sharedBagianIds = is_array($_POST['shared_bagian_ids'] ?? null) ? $_POST['shared_bagian_ids'] : [];
if ($shareMode === 'all') {
    $allStatement = sqlsrv_query($conn, 'SELECT id_bag FROM dbo.m_bag');
    if ($allStatement === false) { kb_fail('share_article', 'Gagal membaca daftar Bagian.', 500); }
    $sharedBagianIds = [];
    while ($row = sqlsrv_fetch_array($allStatement, SQLSRV_FETCH_ASSOC)) { $sharedBagianIds[] = (int)$row['id_bag']; }
}
if ($articleId <= 0) { kb_fail('share_article', 'Artikel tidak valid.', 422); }
$statement = sqlsrv_query($conn, 'SELECT bagian_id FROM dbo.knowledge_base_articles WHERE id = ? AND is_deleted = 0', [$articleId]);
$article = $statement ? sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC) : null;
if (!$article || !kb_can_access($context, (int)$article['bagian_id'])) { kb_fail('share_article', 'Artikel tidak ditemukan atau tidak dapat dibagikan.', 404); }
if (!sqlsrv_begin_transaction($conn)) { kb_fail('share_article', 'Gagal memulai transaksi.', 500); }
if (!kb_sync_article_visibility($conn, $articleId, (int)$article['bagian_id'], $sharedBagianIds, $context['user_id'])) {
    sqlsrv_rollback($conn);
    kb_fail('share_article', 'Gagal menyimpan Bagian berbagi.', 500);
}
sqlsrv_commit($conn);
kb_json(['ok' => true, 'id' => $articleId]);
