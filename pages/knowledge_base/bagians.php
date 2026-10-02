<?php
require_once __DIR__ . '/kb_helpers.php';
kb_context($conn);
$statement = sqlsrv_query($conn, 'SELECT id_bag AS id, bagian AS name FROM dbo.m_bag ORDER BY bagian');
if ($statement === false) { kb_fail('bagians', 'Gagal membaca daftar Bagian.', 500, ['stage' => 'bagian_lookup']); }
$items = [];
while ($row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC)) { $items[] = ['id' => (int)$row['id'], 'name' => (string)$row['name']]; }
kb_json(['ok' => true, 'items' => $items]);
