<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../koneksi.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['UserName'])) { echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }
$user = $_SESSION['UserName']; $now = date('Y-m-d H:i:s');
function canApproveExperiment($conn, $groupId, $username)
{
  if ((int)$groupId === 1) return true;
  $stmt = sqlsrv_query($conn, "SELECT TOP 1 g.id FROM dbo.resep_obat_group_members m INNER JOIN dbo.resep_obat_groups g ON m.group_id = g.id WHERE m.username=? AND (g.role_type='KABAG' OR UPPER(g.group_name)='KABAG LAB')", [$username]);
  return $stmt && sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}
if (!canApproveExperiment($conn, $_SESSION['GroupId'] ?? 0, $user)) { echo json_encode(['status'=>'error','message'=>'Approve hanya untuk Kabag Lab.']); exit; }
$resep_id = $_POST['resep_id'] ?? $_GET['resep_id'] ?? '';
if (!$resep_id) { echo json_encode(['status'=>'error','message'=>'ID experiment tidak ditemukan']); exit; }

$stmt = sqlsrv_query($conn, "SELECT id, group_id, experiment_status FROM dbo.resep_obat_experiment WHERE id=?", [$resep_id]);
$exp = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
if (!$exp || empty($exp['group_id'])) { echo json_encode(['status'=>'error','message'=>'Experiment/group tidak ditemukan']); exit; }
if (($exp['experiment_status'] ?? '') === 'Approved') { echo json_encode(['status'=>'error','message'=>'Experiment sudah Approved']); exit; }
$group_id = $exp['group_id'];
$statusBefore = trim((string)($exp['experiment_status'] ?? '')) ?: 'Draft';

$u1 = sqlsrv_query($conn, "UPDATE dbo.resep_obat_experiment SET status_before_approve=?, experiment_status='Approved', was_approved=1, updated_at=?, updated_by=? WHERE id=?", [$statusBefore,$now,$user,$resep_id]);
if (!$u1) { echo json_encode(['status'=>'error','message'=>'Update experiment gagal: '.print_r(sqlsrv_errors(),true)]); exit; }
$u2 = sqlsrv_query($conn, "UPDATE dbo.resep_obat_experiment_group SET group_status='Approved', approved_experiment_id=?, approved_at=?, approved_by=?, updated_at=?, updated_by=? WHERE id=?", [$resep_id,$now,$user,$now,$user,$group_id]);
if (!$u2) { echo json_encode(['status'=>'error','message'=>'Update group gagal: '.print_r(sqlsrv_errors(),true)]); exit; }

echo json_encode(['status'=>'success','group_id'=>$group_id]);
