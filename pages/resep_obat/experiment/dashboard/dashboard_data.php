<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../../koneksi.php';
require_once __DIR__ . '/../experiment_visibility_helper.php';
header('Content-Type: application/json; charset=utf-8');

function dashboardFail(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'message' => $message, 'data' => []]);
    exit;
}
function dashboardCanView($conn): bool
{
    $stmt = sqlsrv_query($conn, "SELECT CanView FROM dbo.SMGroupTrustee WHERE GroupId=? AND MenuId=212", [$_SESSION['GroupId'] ?? 0]);
    return $stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) && (int)($row['CanView'] ?? 0) === 1;
}

if (!isset($_SESSION['UserName'])) dashboardFail('Unauthorized', 401);
if (!dashboardCanView($conn)) dashboardFail('Forbidden', 403);

$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));
$search = trim((string)($_GET['q'] ?? ''));
$creators = $_GET['created_by'] ?? [];
if (!is_array($creators)) $creators = [$creators];
$creators = array_values(array_filter(array_unique(array_map('trim', $creators)), static fn($v) => $v !== ''));
if ($dateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) dashboardFail('Tanggal awal tidak valid');
if ($dateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) dashboardFail('Tanggal akhir tidak valid');
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) dashboardFail('Rentang tanggal tidak valid');

$where = [];
$params = [];
resepExperimentApplyVisibility($where, $params, 'e', $conn);
if ($dateFrom !== '') { $where[] = 'e.created_at >= ?'; $params[] = $dateFrom; }
if ($dateTo !== '') { $where[] = 'e.created_at <= ?'; $params[] = $dateTo . ' 23:59:59'; }
if ($creators) {
    $where[] = 'e.created_by IN (' . implode(',', array_fill(0, count($creators), '?')) . ')';
    array_push($params, ...$creators);
}
if ($search !== '') {
    $where[] = '(e.kode_warna LIKE ? OR e.color_name LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = sqlsrv_query($conn, "SELECT e.id,e.group_id,e.experiment_seq,e.experiment_status,e.was_approved,e.was_in_process,e.no_cp,e.kode_warna,e.color_name,e.created_by,e.created_at FROM dbo.resep_obat_experiment e $whereSql ORDER BY e.created_at ASC,e.id ASC", $params);
if (!$stmt) dashboardFail('Gagal memuat data dashboard', 500);

$rows = [];
$cp = [];
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $status = trim((string)($r['experiment_status'] ?? 'Draft')) ?: 'Draft';
    if ($status === 'Prosess') $status = 'Process';
    $noCp = strtoupper(trim((string)($r['no_cp'] ?? '')));
    if ($noCp !== '' && ((int)($r['was_in_process'] ?? 0) === 1 || $status === 'Process')) $cp[$noCp] = true;
    $createdAt = $r['created_at'];
    $rows[] = [
        'id' => (int)$r['id'], 'group_id' => (int)($r['group_id'] ?? 0),
        'experiment_seq' => (int)($r['experiment_seq'] ?? 1), 'status' => $status,
        'was_approved' => (int)($r['was_approved'] ?? 0), 'was_in_process' => (int)($r['was_in_process'] ?? 0),
        'no_cp' => $noCp, 'kode_warna' => trim((string)($r['kode_warna'] ?? '')) ?: '-',
        'color_name' => trim((string)($r['color_name'] ?? '')) ?: '-',
        'created_by' => trim((string)($r['created_by'] ?? '')) ?: '-',
        'created_at' => $createdAt instanceof DateTimeInterface ? $createdAt->format('Y-m-d H:i:s') : (string)$createdAt,
    ];
}
sqlsrv_free_stmt($stmt);

$creatorWhere = [];
$creatorParams = [];
resepExperimentApplyVisibility($creatorWhere, $creatorParams, 'e', $conn);
$creatorSql = "SELECT DISTINCT e.created_by FROM dbo.resep_obat_experiment e WHERE e.created_by IS NOT NULL AND LTRIM(RTRIM(e.created_by))<>''";
if ($creatorWhere) $creatorSql .= ' AND ' . implode(' AND ', $creatorWhere);
$creatorSql .= ' ORDER BY e.created_by';
$creatorStmt = sqlsrv_query($conn, $creatorSql, $creatorParams);
$creatorOptions = [];
while ($creatorStmt && $r = sqlsrv_fetch_array($creatorStmt, SQLSRV_FETCH_ASSOC)) $creatorOptions[] = trim((string)$r['created_by']);

echo json_encode(['ok' => true, 'scope' => resepExperimentVisibilityScope($conn), 'data' => $rows, 'cp' => array_keys($cp), 'creators' => $creatorOptions], JSON_UNESCAPED_UNICODE);

