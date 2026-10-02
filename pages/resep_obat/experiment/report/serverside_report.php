<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../../koneksi.php';
require_once __DIR__ . '/../experiment_visibility_helper.php';
header('Content-Type: application/json; charset=utf-8');
function reportPerm($conn): bool { $stmt = sqlsrv_query($conn, "SELECT CanView FROM dbo.SMGroupTrustee WHERE GroupId=? AND MenuId=212", [$_SESSION['GroupId'] ?? 0]); return $stmt && ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) && (int)($r['CanView'] ?? 0) === 1; }
function fmtDate($v): string { if ($v instanceof DateTimeInterface) return $v->format('d/m/Y'); if (!$v) return '-'; $ts = strtotime((string)$v); return $ts ? date('d/m/Y', $ts) : '-'; }
function fmtNum($v): string { return number_format((float)($v ?? 0), 2, '.', ','); }
if (!isset($_SESSION['UserName']) || !reportPerm($conn)) { echo json_encode(['data'=>[], 'recordsTotal'=>0, 'recordsFiltered'=>0]); exit; }
$start = (int) ($_GET['start'] ?? 0);
$length = (int) ($_GET['length'] ?? 50);
$search = trim($_GET['search']['value'] ?? '');
$orderBy = (int) ($_GET['order'][0]['column'] ?? 1);
$dir = strtolower($_GET['order'][0]['dir'] ?? 'desc');
if (!in_array($dir, ['asc', 'desc'], true)) $dir = 'desc';
$cols = [null, 'e.created_at', 'e.cus_color', 'e.kode_warna', 'e.experiment_status', 'e.no_cp', 'e.experiment_seq', 'pplan.tgl_planning', null, 'e.mesin', 'e.plan_qty', null, null, null, 'e.qc_keputusan', 'e.qc_tindakan', 'e.qc_catatan', null];
$orderByCol = $cols[$orderBy] ?? 'e.created_at';
$conditions = [];
$params = [];
resepExperimentApplyVisibility($conditions, $params, 'e', $conn);
if ($search !== '') {
    $conditions[] = "(e.no_cp LIKE ? OR e.kode_warna LIKE ? OR e.color_name LIKE ? OR e.cus_color LIKE ? OR e.mesin LIKE ? OR e.experiment_status LIKE ? OR e.qc_keputusan LIKE ? OR e.qc_tindakan LIKE ? OR e.qc_catatan LIKE ?)";
    $s = "%$search%";
    array_push($params, $s, $s, $s, $s, $s, $s, $s, $s, $s);
}
if (trim($_GET['filter_date_from'] ?? '') !== '') { $conditions[] = 'e.created_at >= ?'; $params[] = $_GET['filter_date_from']; }
if (trim($_GET['filter_date_to'] ?? '') !== '') { $conditions[] = 'e.created_at <= ?'; $params[] = $_GET['filter_date_to'] . ' 23:59:59'; }
if (trim($_GET['filter_created_by'] ?? '') !== '') {
    $createdByFilter = array_values(array_filter(array_map('trim', explode('|', (string) $_GET['filter_created_by'])), fn($v) => $v !== ''));
    if ($createdByFilter) { $conditions[] = 'e.created_by IN (' . implode(',', array_fill(0, count($createdByFilter), '?')) . ')'; array_push($params, ...$createdByFilter); }
}
$filterStatus = trim((string) ($_GET['filter_status'] ?? ''));
if ($filterStatus !== '') { $conditions[] = 'e.experiment_status = ?'; $params[] = $filterStatus; }
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$stmtCount = sqlsrv_query($conn, "SELECT COUNT(*) AS cnt FROM dbo.resep_obat_experiment e $where", $params);
$filtered = 0;
if ($stmtCount && $r = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC)) $filtered = (int) $r['cnt'];
if ($stmtCount) sqlsrv_free_stmt($stmtCount);
$baseConditions = [];
$baseParams = [];
resepExperimentApplyVisibility($baseConditions, $baseParams, 'e', $conn);
$whereBase = $baseConditions ? 'WHERE ' . implode(' AND ', $baseConditions) : '';
$stmtAll = sqlsrv_query($conn, "SELECT COUNT(*) AS cnt FROM dbo.resep_obat_experiment e $whereBase", $baseParams);
$total = $filtered;
if ($stmtAll && $r = sqlsrv_fetch_array($stmtAll, SQLSRV_FETCH_ASSOC)) $total = (int) $r['cnt'];
if ($stmtAll) sqlsrv_free_stmt($stmtAll);
$sql = "SELECT e.id,e.created_at,e.created_by,e.cus_color,e.kode_warna,e.color_name,e.mesin,e.no_cp,e.experiment_seq,e.experiment_status,e.plan_qty,e.qc_keputusan,e.qc_tindakan,e.qc_catatan,pplan.tgl_planning FROM dbo.resep_obat_experiment e OUTER APPLY (SELECT TOP 1 p.period_date AS tgl_planning FROM dbo.cpp_paddry p WHERE UPPER(LTRIM(RTRIM(CAST(p.cp_no AS NVARCHAR(200)))))=UPPER(LTRIM(RTRIM(CAST(e.no_cp AS NVARCHAR(200))))) ORDER BY CASE WHEN p.period_date IS NULL THEN 1 ELSE 0 END ASC,p.period_date DESC,p.id DESC) pplan $where ORDER BY $orderByCol $dir OFFSET $start ROWS FETCH NEXT $length ROWS ONLY";
$stmt = sqlsrv_query($conn, $sql, $params);
$data = [];
while ($stmt && $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $hasCp = trim((string) ($r['no_cp'] ?? '')) !== '';
    $mesin = trim((string) ($r['mesin'] ?? ''));
    $status = trim((string) ($r['experiment_status'] ?? '')) ?: 'Draft';
    $data[] = ['id'=>(int)$r['id'],'tgl_match'=>fmtDate($r['created_at']??null),'created_by'=>trim((string)($r['created_by']??''))?:'-','label'=>trim((string)($r['cus_color']??''))?:'-','warna'=>$r['color_name']??'-','status'=>$status,'experiment_seq'=>(int)($r['experiment_seq']??1),'tgl_planning'=>fmtDate($r['tgl_planning']??null),'tgl_celup_padd'=>$hasCp?'<span class="erp-spinner" data-erp="tgl"></span>':'-','mesin_paddry'=>$mesin!==''?$mesin:'-','no_cp'=>$hasCp?$r['no_cp']:'Tunggu CP','kode_lab'=>$r['kode_warna']??'-','qty'=>fmtNum($r['plan_qty']??0),'aktual_qty'=>$hasCp?'<span class="erp-spinner" data-erp="aktual_qty"></span>':'-','posisi_hari_ini'=>$hasCp?'<span class="erp-spinner" data-erp="pos"></span>':'-','acc_warna_r_status'=>'-','acc_warna_r_tgl'=>$hasCp?'<span class="erp-spinner" data-erp="acc"></span>':'-','keputusan'=>trim((string)($r['qc_keputusan']??''))?:'-','tindakan'=>trim((string)($r['qc_tindakan']??''))?:'-','qc_catatan'=>trim((string)($r['qc_catatan']??''))?:'-'];
}
if ($stmt) sqlsrv_free_stmt($stmt);
echo json_encode(['draw'=>(int)($_GET['draw']??1),'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$data]);
