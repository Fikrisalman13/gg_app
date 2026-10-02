<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../koneksi.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['UserName'])) { echo json_encode(['exists'=>false,'message'=>'Unauthorized']); exit; }

$kode_warna = trim($_GET['kode_warna'] ?? $_POST['kode_warna'] ?? '');
if ($kode_warna === '') { echo json_encode(['exists'=>false]); exit; }

function aggregate_group_display_status(array $statuses): string
{
  $summary = ['Draft'=>0, 'Approved'=>0, 'Process'=>0, 'Pass'=>0, 'Fail'=>0];
  foreach ($statuses as $status) {
    $st = trim((string)$status) ?: 'Draft';
    if ($st === 'Prosess') $st = 'Process';
    if (!isset($summary[$st])) $summary[$st] = 0;
    $summary[$st]++;
  }
  $total = count($statuses);
  if (($summary['Pass'] ?? 0) > 0) return 'Pass';
  if ($total > 0 && ($summary['Fail'] ?? 0) === $total) return 'Fail';
  if (($summary['Process'] ?? 0) > 0 || ($summary['Fail'] ?? 0) > 0) return 'Process';
  if (($summary['Approved'] ?? 0) > 0) return 'Approved';
  if (($summary['Draft'] ?? 0) > 0) return 'Draft';
  return 'Draft';
}

$sql = "SELECT TOP 1 id, soi, no_cp, kode_warna, color_name, group_status, approved_experiment_id, created_by
        FROM dbo.resep_obat_experiment_group
        WHERE kode_warna = ?
        ORDER BY id DESC";
$stmt = sqlsrv_query($conn, $sql, [$kode_warna]);
$row = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;

if (!$row) { echo json_encode(['exists'=>false]); exit; }

$statuses = [];
$expStmt = sqlsrv_query($conn, "SELECT experiment_status FROM dbo.resep_obat_experiment WHERE group_id=? ORDER BY experiment_seq ASC, id ASC", [(int)($row['id'] ?? 0)]);
while ($expStmt && $exp = sqlsrv_fetch_array($expStmt, SQLSRV_FETCH_ASSOC)) {
  $statuses[] = $exp['experiment_status'] ?? 'Draft';
}
if ($expStmt) sqlsrv_free_stmt($expStmt);
$displayStatus = aggregate_group_display_status($statuses);

echo json_encode([
  'exists' => true,
  'group' => [
    'id' => (int)($row['id'] ?? 0),
    'soi' => $row['soi'] ?? '',
    'no_cp' => $row['no_cp'] ?? '',
    'kode_warna' => $row['kode_warna'] ?? '',
    'color_name' => $row['color_name'] ?? '',
    'group_status' => $displayStatus,
    'group_status_raw' => $row['group_status'] ?? '',
    'created_by' => $row['created_by'] ?? '',
    'approved_experiment_id' => $row['approved_experiment_id'] ?? null,
  ]
]);
