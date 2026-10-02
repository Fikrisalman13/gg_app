<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
header('Content-Type: application/json');

$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) {
    echo json_encode(['draw' => intval($_POST['draw'] ?? 1), 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Anda tidak memiliki hak melihat data.']);
    exit;
}

$draw = intval($_POST['draw'] ?? 1);
$start = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);
$search = trim($_POST['search']['value'] ?? '');
$startDate = trim($_POST['start_date'] ?? '');
$endDate = trim($_POST['end_date'] ?? '');
$parameterId = intval($_POST['parameter_id'] ?? 0);

$where = "WHERE 1=1";
$params = [];
if ($startDate !== '') { $where .= " AND CAST(h.tanggal AS DATE) >= ?"; $params[] = $startDate; }
if ($endDate !== '') { $where .= " AND CAST(h.tanggal AS DATE) <= ?"; $params[] = $endDate; }
if ($parameterId > 0) { $where .= " AND h.master_id = ?"; $params[] = $parameterId; }
if ($search !== '') {
    $where .= " AND (
      CONVERT(VARCHAR(10), h.tanggal, 120) LIKE ?
      OR m.nama_item LIKE ?
      OR m.grup_laporan LIKE ?
      OR CONVERT(VARCHAR(50), h.pakai_kg) LIKE ?
      OR CONVERT(VARCHAR(50), h.harga_rp) LIKE ?
      OR CONVERT(VARCHAR(50), h.biaya_rp) LIKE ?
      OR COALESCE(h.updateby, h.creatby, '') LIKE ?
    )";
    $sp = "%{$search}%";
    $params = array_merge($params, [$sp, $sp, $sp, $sp, $sp, $sp, $sp]);
}

$baseFrom = "FROM dbo.kimia_weaving_harian h
             INNER JOIN dbo.kimia_weaving_master m ON m.id = h.master_id";

$countAllSql = "SELECT COUNT(*) AS total " . $baseFrom;
$countAllStmt = sqlsrv_query($conn, $countAllSql);
$totalRecords = 0;
if ($countAllStmt && ($r = sqlsrv_fetch_array($countAllStmt, SQLSRV_FETCH_ASSOC))) $totalRecords = (int)$r['total'];
if ($countAllStmt) sqlsrv_free_stmt($countAllStmt);

$countFilteredSql = "SELECT COUNT(*) AS total " . $baseFrom . " " . $where;
$countFilteredStmt = sqlsrv_query($conn, $countFilteredSql, $params);
$totalFiltered = 0;
if ($countFilteredStmt && ($r = sqlsrv_fetch_array($countFilteredStmt, SQLSRV_FETCH_ASSOC))) $totalFiltered = (int)$r['total'];
if ($countFilteredStmt) sqlsrv_free_stmt($countFilteredStmt);

$sql = "SELECT h.id, h.tanggal, m.nama_item, m.grup_laporan, h.pakai_kg, h.harga_rp, h.biaya_rp, COALESCE(h.updateby, h.creatby, '') AS created_by
        " . $baseFrom . "
        " . $where . "
        ORDER BY CAST(h.tanggal AS DATE) DESC, h.id DESC
        OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$dataParams = $params;
$dataParams[] = $start;
$dataParams[] = $length;
$stmt = sqlsrv_query($conn, $sql, $dataParams);
if ($stmt === false) {
    echo json_encode(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Gagal mengambil data.']);
    exit;
}

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tgl = $row['tanggal'];
    if ($tgl instanceof DateTime) { $tanggalRaw = $tgl->format('Y-m-d'); $tanggalFmt = $tgl->format('d-m-Y'); }
    else { $tanggalRaw = (string)$tgl; $tanggalFmt = $tanggalRaw !== '' ? date('d-m-Y', strtotime($tanggalRaw)) : ''; }

    $data[] = [
        'id' => $row['id'] ?? null,
        'tanggal' => $tanggalRaw,
        'tanggal_formatted' => $tanggalFmt,
        'nama_item' => $row['nama_item'] ?? '',
        'grup_laporan' => $row['grup_laporan'] ?? '',
        'pakai_kg' => $row['pakai_kg'] ?? null,
        'harga_rp' => $row['harga_rp'] ?? null,
        'biaya_rp' => $row['biaya_rp'] ?? null,
        'created_by' => $row['created_by'] ?? '',
        'aksi' => ''
    ];
}
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['draw' => $draw, 'recordsTotal' => $totalRecords, 'recordsFiltered' => $totalFiltered, 'data' => $data]);


