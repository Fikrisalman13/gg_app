<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak melihat master.']);
    exit;
}

$sql = "SELECT id, kode, nama_item, grup_laporan, satuan_pakai, aktif
        FROM dbo.kimia_weaving_master
        ORDER BY
          CASE grup_laporan
            WHEN 'IPAL' THEN 1
            WHEN 'PROSES' THEN 2
            WHEN 'DAF_LAMA' THEN 3
            WHEN 'DAF3_BARU' THEN 4
            WHEN 'DAF2_BARU' THEN 5
            ELSE 99
          END,
          id ASC";
$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal memuat data master.']);
    exit;
}

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $data[] = [
        'id' => $row['id'] ?? null,
        'kode' => $row['kode'] ?? '',
        'nama_item' => $row['nama_item'] ?? '',
        'grup_laporan' => $row['grup_laporan'] ?? '',
        'satuan_pakai' => $row['satuan_pakai'] ?? '',
        'aktif' => isset($row['aktif']) ? (string)$row['aktif'] : '0'
    ];
}
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['success' => true, 'data' => $data]);


