<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../../koneksi.php';
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
if (!$isAdmin && ($permissions['CanDelete'] ?? 0) != 1) fail('Anda tidak memiliki hak untuk menghapus group experiment.');

$group_id = $_POST['group_id'] ?? '';
if (!$group_id) fail('Group ID tidak ditemukan.');

$sg = sqlsrv_query($conn, "SELECT id, group_status, approved_experiment_id FROM dbo.resep_obat_experiment_group WHERE id=?", [$group_id]);
$group = $sg ? sqlsrv_fetch_array($sg, SQLSRV_FETCH_ASSOC) : null;
if (!$group) fail('Group tidak ditemukan.');
if (!$isAdmin && (($group['group_status'] ?? '') === 'Approved' || !empty($group['approved_experiment_id']))) fail('Group yang sudah memiliki approved experiment tidak bisa dihapus.');

$sa = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM dbo.resep_obat_experiment WHERE group_id=? AND experiment_status='Approved'", [$group_id]);
if (!$isAdmin && $sa && $r = sqlsrv_fetch_array($sa, SQLSRV_FETCH_ASSOC)) {
    if ((int)$r['total'] > 0) fail('Group memiliki experiment Approved sehingga tidak bisa dihapus.');
}

sqlsrv_begin_transaction($conn);
try {
    $ids = [];
    $si = sqlsrv_query($conn, "SELECT id FROM dbo.resep_obat_experiment WHERE group_id=?", [$group_id]);
    while ($si && $r = sqlsrv_fetch_array($si, SQLSRV_FETCH_ASSOC)) $ids[] = (int)$r['id'];
    foreach ($ids as $id) {
        $d = sqlsrv_query($conn, "DELETE FROM dbo.resep_obat_experiment_detail WHERE id_resep_experiment=?", [$id]);
        if (!$d) throw new Exception('Hapus detail gagal: '.print_r(sqlsrv_errors(), true));
    }
    $de = sqlsrv_query($conn, "DELETE FROM dbo.resep_obat_experiment WHERE group_id=?", [$group_id]);
    if (!$de) throw new Exception('Hapus experiment gagal: '.print_r(sqlsrv_errors(), true));
    $dg = sqlsrv_query($conn, "DELETE FROM dbo.resep_obat_experiment_group WHERE id=?", [$group_id]);
    if (!$dg) throw new Exception('Hapus group gagal: '.print_r(sqlsrv_errors(), true));
    sqlsrv_commit($conn);
    echo json_encode(['status'=>'success']);
} catch (Exception $e) {
    sqlsrv_rollback($conn);
    fail($e->getMessage());
}
