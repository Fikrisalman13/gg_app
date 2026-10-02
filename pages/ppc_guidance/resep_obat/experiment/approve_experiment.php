<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../../koneksi.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['UserName'])) { echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }
$user = $_SESSION['UserName']; $now = date('Y-m-d H:i:s');
$resep_id = $_POST['resep_id'] ?? $_GET['resep_id'] ?? '';
$soi = trim($_POST['soi'] ?? '');
$no_cp = trim($_POST['no_cp'] ?? '');
if (!$resep_id) { echo json_encode(['status'=>'error','message'=>'ID experiment tidak ditemukan']); exit; }
if ($soi === '') { echo json_encode(['status'=>'error','message'=>'SOI wajib diisi saat approve experiment']); exit; }
if ($no_cp === '') { echo json_encode(['status'=>'error','message'=>'No CP wajib diisi saat approve experiment']); exit; }

$stmt = sqlsrv_query($conn, "SELECT id, group_id FROM dbo.resep_obat_experiment WHERE id=?", [$resep_id]);
$exp = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
if (!$exp || empty($exp['group_id'])) { echo json_encode(['status'=>'error','message'=>'Experiment/group tidak ditemukan']); exit; }
$group_id = $exp['group_id'];

$u1 = sqlsrv_query($conn, "UPDATE dbo.resep_obat_experiment SET experiment_status='Approved', no_cp=?, updated_at=?, updated_by=? WHERE id=?", [$no_cp,$now,$user,$resep_id]);
if (!$u1) { echo json_encode(['status'=>'error','message'=>'Update experiment gagal: '.print_r(sqlsrv_errors(),true)]); exit; }
$u2 = sqlsrv_query($conn, "UPDATE dbo.resep_obat_experiment_group SET soi=?, no_cp=?, group_status='Approved', approved_experiment_id=?, approved_at=?, approved_by=?, updated_at=?, updated_by=? WHERE id=?", [$soi,$no_cp,$resep_id,$now,$user,$now,$user,$group_id]);
if (!$u2) { echo json_encode(['status'=>'error','message'=>'Update group gagal: '.print_r(sqlsrv_errors(),true)]); exit; }

echo json_encode(['status'=>'success','group_id'=>$group_id]);
