<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../koneksi.php';
header('Content-Type: application/json; charset=utf-8');

function checkPermissions($conn, $groupId, $menuId) {
    $stmt = sqlsrv_query($conn, "SELECT CanView,CanAdd,CanEdit,CanDelete FROM dbo.SMGroupTrustee WHERE GroupId=? AND MenuId=?", [$groupId, $menuId]);
    $p = ['CanView'=>0,'CanAdd'=>0,'CanEdit'=>0,'CanDelete'=>0];
    if ($stmt && $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $p = $r;
    return $p;
}
function fail($m){ echo json_encode(['status'=>'error','message'=>$m]); exit; }

if (!isset($_SESSION['UserName'])) fail('Unauthorized');
$permissions = checkPermissions($conn, $_SESSION['GroupId'] ?? 0, 212);
$isAdmin = (int)($_SESSION['GroupId'] ?? 0) === 1;
if (!$isAdmin && ($permissions['CanDelete'] ?? 0) != 1) fail('Anda tidak memiliki hak untuk menghapus experiment.');

$resep_id = $_POST['resep_id'] ?? '';
if (!$resep_id) fail('ID experiment tidak ditemukan.');

$stmt = sqlsrv_query($conn, "SELECT id, group_id, experiment_status FROM dbo.resep_obat_experiment WHERE id=?", [$resep_id]);
$exp = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
if (!$exp) fail('Experiment tidak ditemukan.');
if (!$isAdmin && ($exp['experiment_status'] ?? '') === 'Approved') fail('Experiment yang sudah Approved tidak bisa dihapus.');

$group_id = $exp['group_id'];
$sg = sqlsrv_query($conn, "SELECT group_status FROM dbo.resep_obat_experiment_group WHERE id=?", [$group_id]);
$group = $sg ? sqlsrv_fetch_array($sg, SQLSRV_FETCH_ASSOC) : null;
if (!$isAdmin && ($group['group_status'] ?? '') === 'Approved') fail('Group yang sudah Approved tidak bisa dihapus.');

sqlsrv_begin_transaction($conn);
try {
    $d1 = sqlsrv_query($conn, "DELETE FROM dbo.resep_obat_experiment_detail WHERE id_resep_experiment=?", [$resep_id]);
    if (!$d1) throw new Exception('Hapus detail gagal: '.print_r(sqlsrv_errors(), true));
    $d2 = sqlsrv_query($conn, "DELETE FROM dbo.resep_obat_experiment WHERE id=?", [$resep_id]);
    if (!$d2) throw new Exception('Hapus experiment gagal: '.print_r(sqlsrv_errors(), true));

    $left = 0;
    if ($group_id) {
        $sc = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM dbo.resep_obat_experiment WHERE group_id=?", [$group_id]);
        if ($sc && $r = sqlsrv_fetch_array($sc, SQLSRV_FETCH_ASSOC)) $left = (int)$r['total'];
        if ($left === 0) {
            $dg = sqlsrv_query($conn, "DELETE FROM dbo.resep_obat_experiment_group WHERE id=?", [$group_id]);
            if (!$dg) throw new Exception('Hapus group kosong gagal: '.print_r(sqlsrv_errors(), true));
        }
    }
    sqlsrv_commit($conn);
    echo json_encode(['status'=>'success','group_deleted'=>$left===0,'group_id'=>$group_id]);
} catch (Exception $e) {
    sqlsrv_rollback($conn);
    fail($e->getMessage());
}
