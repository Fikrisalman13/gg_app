<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../koneksi.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['UserName'])) { echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }
$user = $_SESSION['UserName'];
$now = date('Y-m-d H:i:s');
$resep_id = $_POST['resep_id'] ?? $_GET['resep_id'] ?? '';
if (!$resep_id) { echo json_encode(['status'=>'error','message'=>'ID experiment tidak ditemukan']); exit; }

function canApproveExperiment($conn, $groupId, $username)
{
  if ((int)$groupId === 1) return true;
  $stmt = sqlsrv_query($conn, "SELECT TOP 1 g.id FROM dbo.resep_obat_group_members m INNER JOIN dbo.resep_obat_groups g ON m.group_id = g.id WHERE m.username=? AND (g.role_type IN ('KABAG', 'LAB_APPROVAL_EXPERIMENT') OR UPPER(g.group_name)='KABAG LAB')", [$username]);
  return $stmt && sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}
if (!canApproveExperiment($conn, $_SESSION['GroupId'] ?? 0, $user)) {
  echo json_encode(['status'=>'error','message'=>'Unapprove hanya untuk Kabag Lab.']);
  exit;
}

$stmt = sqlsrv_query($conn, "SELECT id, group_id, experiment_status, status_before_approve FROM dbo.resep_obat_experiment WHERE id=?", [$resep_id]);
$exp = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
if (!$exp || empty($exp['group_id'])) { echo json_encode(['status'=>'error','message'=>'Experiment/group tidak ditemukan']); exit; }
if (($exp['experiment_status'] ?? '') !== 'Approved') { echo json_encode(['status'=>'error','message'=>'Experiment belum Approved']); exit; }

$group_id = (int)$exp['group_id'];
$targetStatus = trim((string)($exp['status_before_approve'] ?? '')) ?: 'Draft';

sqlsrv_begin_transaction($conn);

$u1 = sqlsrv_query($conn, "UPDATE dbo.resep_obat_experiment SET experiment_status=?, status_before_approve=NULL, updated_at=?, updated_by=? WHERE id=?", [$targetStatus,$now,$user,$resep_id]);
if (!$u1) { sqlsrv_rollback($conn); echo json_encode(['status'=>'error','message'=>'Update experiment gagal: '.print_r(sqlsrv_errors(),true)]); exit; }

$apStmt = sqlsrv_query($conn, "SELECT TOP 1 id FROM dbo.resep_obat_experiment WHERE group_id=? AND experiment_status='Approved' ORDER BY experiment_seq DESC, id DESC", [$group_id]);
$ap = $apStmt ? sqlsrv_fetch_array($apStmt, SQLSRV_FETCH_ASSOC) : null;
$approvedId = $ap['id'] ?? null;

$stStmt = sqlsrv_query($conn, "SELECT TOP 1 experiment_status FROM dbo.resep_obat_experiment WHERE group_id=? ORDER BY CASE WHEN experiment_status IN ('Process','Prosess') THEN 0 WHEN experiment_status='Approved' THEN 1 WHEN experiment_status IN ('Sukses','Success') THEN 2 WHEN experiment_status='Lunas' THEN 3 ELSE 4 END, experiment_seq DESC, id DESC", [$group_id]);
$st = $stStmt ? sqlsrv_fetch_array($stStmt, SQLSRV_FETCH_ASSOC) : null;
$groupStatus = $st ? trim((string)$st['experiment_status']) : 'Draft';
if ($groupStatus === '') $groupStatus = 'Draft';

$u2 = sqlsrv_query($conn, "UPDATE dbo.resep_obat_experiment_group SET group_status=?, approved_experiment_id=?, approved_at=CASE WHEN ? IS NULL THEN NULL ELSE approved_at END, approved_by=CASE WHEN ? IS NULL THEN NULL ELSE approved_by END, updated_at=?, updated_by=? WHERE id=?", [$groupStatus,$approvedId,$approvedId,$approvedId,$now,$user,$group_id]);
if (!$u2) { sqlsrv_rollback($conn); echo json_encode(['status'=>'error','message'=>'Update group gagal: '.print_r(sqlsrv_errors(),true)]); exit; }

sqlsrv_commit($conn);
echo json_encode(['status'=>'success','group_id'=>$group_id,'new_status'=>$targetStatus]);
