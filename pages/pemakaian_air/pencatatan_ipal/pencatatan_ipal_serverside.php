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
$shift = trim($_POST['shift'] ?? '');

$where = "WHERE 1=1";
$params = [];
if ($startDate !== '') { $where .= " AND CAST(x.tanggal AS DATE) >= ?"; $params[] = $startDate; }
if ($endDate !== '') { $where .= " AND CAST(x.tanggal AS DATE) <= ?"; $params[] = $endDate; }
if ($shift !== '') { $where .= " AND x.shift_kode = ?"; $params[] = $shift; }
if ($search !== '') {
    $where .= " AND (
        CONVERT(VARCHAR(10), x.tanggal, 120) LIKE ?
        OR x.shift_kode LIKE ?
        OR CONVERT(VARCHAR(50), x.sv30_aerasi1_pct) LIKE ?
        OR CONVERT(VARCHAR(50), x.sv30_aerasi2_pct) LIKE ?
        OR CONVERT(VARCHAR(50), x.sv30_aerasi3_pct) LIKE ?
        OR CONVERT(VARCHAR(50), x.sv30_aerasi4_pct) LIKE ?
        OR CONVERT(VARCHAR(50), x.ph_equal) LIKE ?
        OR CONVERT(VARCHAR(50), x.ph_akhir) LIKE ?
        OR CONVERT(VARCHAR(50), x.dewatering_bawah_per_day) LIKE ?
        OR CONVERT(VARCHAR(50), x.dewatering_atas_sinci1_per_day_ton) LIKE ?
        OR CONVERT(VARCHAR(50), x.sinci2_per_day_ton) LIKE ?
        OR x.ket LIKE ?
        OR COALESCE(x.updateby, x.creatby, '') LIKE ?
    )";
    $sp = "%{$search}%";
    $params = array_merge($params, [$sp,$sp,$sp,$sp,$sp,$sp,$sp,$sp,$sp,$sp,$sp,$sp,$sp]);
}

$baseFrom = "FROM dbo.pencatatan_ipal_harian x";

$countAllStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total " . $baseFrom);
$totalRecords = 0;
if ($countAllStmt && ($r = sqlsrv_fetch_array($countAllStmt, SQLSRV_FETCH_ASSOC))) $totalRecords = (int)$r['total'];
if ($countAllStmt) sqlsrv_free_stmt($countAllStmt);

$countFilteredStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total " . $baseFrom . " " . $where, $params);
$totalFiltered = 0;
if ($countFilteredStmt && ($r = sqlsrv_fetch_array($countFilteredStmt, SQLSRV_FETCH_ASSOC))) $totalFiltered = (int)$r['total'];
if ($countFilteredStmt) sqlsrv_free_stmt($countFilteredStmt);

$sql = "SELECT x.id, x.tanggal, x.shift_kode,
               x.sv30_aerasi1_pct, x.sv30_aerasi2_pct, x.sv30_aerasi3_pct, x.sv30_aerasi4_pct,
               x.ph_equal, x.ph_akhir,
               x.dewatering_bawah_per_day, x.dewatering_atas_sinci1_per_day_ton, x.sinci2_per_day_ton,
               x.ket, COALESCE(x.updateby, x.creatby, '') AS created_by
        " . $baseFrom . " " . $where . "
        ORDER BY CAST(x.tanggal AS DATE) DESC,
                 CASE x.shift_kode WHEN 'P' THEN 1 WHEN 'S' THEN 2 WHEN 'M' THEN 3 ELSE 4 END ASC
        OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$dataParams = $params; $dataParams[] = $start; $dataParams[] = $length;
$stmt = sqlsrv_query($conn, $sql, $dataParams);
if ($stmt === false) {
    echo json_encode(['draw'=>$draw,'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[],'error'=>'Gagal mengambil data.']);
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
        'shift_kode' => $row['shift_kode'] ?? '',
        'sv30_aerasi1_pct' => $row['sv30_aerasi1_pct'] ?? 0,
        'sv30_aerasi2_pct' => $row['sv30_aerasi2_pct'] ?? 0,
        'sv30_aerasi3_pct' => $row['sv30_aerasi3_pct'] ?? 0,
        'sv30_aerasi4_pct' => $row['sv30_aerasi4_pct'] ?? 0,
        'ph_equal' => $row['ph_equal'] ?? 0,
        'ph_akhir' => $row['ph_akhir'] ?? 0,
        'dewatering_bawah_per_day' => $row['dewatering_bawah_per_day'] ?? 0,
        'dewatering_atas_sinci1_per_day_ton' => $row['dewatering_atas_sinci1_per_day_ton'] ?? 0,
        'sinci2_per_day_ton' => $row['sinci2_per_day_ton'] ?? 0,
        'ket' => $row['ket'] ?? '',
        'created_by' => $row['created_by'] ?? ''
    ];
}
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['draw'=>$draw,'recordsTotal'=>$totalRecords,'recordsFiltered'=>$totalFiltered,'data'=>$data]);
