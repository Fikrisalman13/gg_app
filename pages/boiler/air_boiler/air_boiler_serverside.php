<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
header('Content-Type: application/json');

$menuId = 1239;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && (int)$permissions['CanView'] !== 1) {
    echo json_encode([
        'draw' => intval($_POST['draw'] ?? 1),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Anda tidak memiliki hak melihat data.'
    ]);
    exit;
}

$draw = intval($_POST['draw'] ?? 1);
$start = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);
$search = trim($_POST['search']['value'] ?? '');
$startDate = trim($_POST['start_date'] ?? '');
$endDate = trim($_POST['end_date'] ?? '');

$where = "WHERE 1=1";
$params = [];
if ($startDate !== '') {
    $where .= " AND CAST(x.tanggal AS DATE) >= ?";
    $params[] = $startDate;
}
if ($endDate !== '') {
    $where .= " AND CAST(x.tanggal AS DATE) <= ?";
    $params[] = $endDate;
}
if ($search !== '') {
    $where .= " AND (
        CONVERT(VARCHAR(10), x.tanggal, 120) LIKE ?
        OR CONVERT(VARCHAR(50), x.temp_umpan_c) LIKE ?
        OR CONVERT(VARCHAR(50), x.dh_std_lt1) LIKE ?
        OR CONVERT(VARCHAR(50), x.tds_umpan_ms) LIKE ?
        OR x.ph_boiler LIKE ?
        OR CONVERT(VARCHAR(50), x.temp_boiler_c) LIKE ?
        OR CONVERT(VARCHAR(50), x.tds_boiler_ms) LIKE ?
        OR x.blowdown_jumlah LIKE ?
        OR x.keterangan LIKE ?
        OR COALESCE(x.updateby, x.creatby, '') LIKE ?
    )";
    $sp = "%{$search}%";
    $params = array_merge($params, [$sp, $sp, $sp, $sp, $sp, $sp, $sp, $sp, $sp, $sp]);
}

$baseFrom = "FROM dbo.air_boiler_alstom_harian x";

$countAllStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total " . $baseFrom);
$totalRecords = 0;
if ($countAllStmt && ($r = sqlsrv_fetch_array($countAllStmt, SQLSRV_FETCH_ASSOC))) {
    $totalRecords = (int)$r['total'];
}
if ($countAllStmt) sqlsrv_free_stmt($countAllStmt);

$countFilteredStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total " . $baseFrom . " " . $where, $params);
$totalFiltered = 0;
if ($countFilteredStmt && ($r = sqlsrv_fetch_array($countFilteredStmt, SQLSRV_FETCH_ASSOC))) {
    $totalFiltered = (int)$r['total'];
}
if ($countFilteredStmt) sqlsrv_free_stmt($countFilteredStmt);

$sql = "SELECT x.id, x.tanggal, x.temp_umpan_c, x.dh_std_lt1, x.tds_umpan_ms,
               CASE WHEN x.tds_umpan_ms IS NULL THEN NULL ELSE CAST(ROUND(x.tds_umpan_ms * 0.64, 2) AS DECIMAL(12,2)) END AS tds_umpan_ppm,
               x.ph_boiler, x.temp_boiler_c, x.tds_boiler_ms,
               CASE WHEN x.tds_boiler_ms IS NULL THEN NULL ELSE CAST(ROUND(x.tds_boiler_ms * 0.64, 2) AS DECIMAL(12,2)) END AS tds_boiler_ppm,
               x.blowdown_jumlah, x.keterangan,
               CASE WHEN x.tds_umpan_ms IS NULL THEN NULL ELSE CAST(ROUND(x.tds_umpan_ms / 1000.0, 2) AS DECIMAL(12,2)) END AS tds_umpan_display_ms,
               CASE WHEN x.tds_boiler_ms IS NULL THEN NULL ELSE CAST(ROUND(x.tds_boiler_ms / 1000.0, 2) AS DECIMAL(12,2)) END AS tds_boiler_display_ms,
               COALESCE(x.updateby, x.creatby, '') AS created_by
        " . $baseFrom . " " . $where . "
        ORDER BY CAST(x.tanggal AS DATE) DESC, x.id DESC
        OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

$dataParams = $params;
$dataParams[] = $start;
$dataParams[] = $length;

$stmt = sqlsrv_query($conn, $sql, $dataParams);
if ($stmt === false) {
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Gagal mengambil data.'
    ]);
    exit;
}

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tgl = $row['tanggal'] ?? null;
    if ($tgl instanceof DateTime) {
        $tanggalRaw = $tgl->format('Y-m-d');
        $tanggalFmt = $tgl->format('d-m-Y');
    } else {
        $tanggalRaw = (string)$tgl;
        $tanggalFmt = $tanggalRaw !== '' ? date('d-m-Y', strtotime($tanggalRaw)) : '';
    }

    $data[] = [
        'id' => $row['id'] ?? null,
        'tanggal' => $tanggalRaw,
        'tanggal_formatted' => $tanggalFmt,
        'temp_umpan_c' => $row['temp_umpan_c'] ?? null,
        'dh_std_lt1' => $row['dh_std_lt1'] ?? null,
        'tds_umpan_ms' => $row['tds_umpan_ms'] ?? null,
        'tds_umpan_ppm' => $row['tds_umpan_ppm'] ?? null,
        'ph_boiler' => $row['ph_boiler'] ?? '',
        'temp_boiler_c' => $row['temp_boiler_c'] ?? null,
        'tds_boiler_ms' => $row['tds_boiler_ms'] ?? null,
        'tds_boiler_ppm' => $row['tds_boiler_ppm'] ?? null,
        'blowdown_jumlah' => $row['blowdown_jumlah'] ?? '',
        'keterangan' => $row['keterangan'] ?? '',
        'tds_umpan_display_ms' => $row['tds_umpan_display_ms'] ?? null,
        'tds_boiler_display_ms' => $row['tds_boiler_display_ms'] ?? null,
        'created_by' => $row['created_by'] ?? ''
    ];
}
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $totalFiltered,
    'data' => $data
]);
