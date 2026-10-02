<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) { echo json_encode(['success'=>false,'message'=>'Silakan login terlebih dahulu.']); exit; }
$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanDelete']) && $permissions['CanDelete'] != 1) { echo json_encode(['success'=>false,'message'=>'Anda tidak memiliki hak menghapus data.']); exit; }

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) { echo json_encode(['success'=>false,'message'=>'ID tidak valid.']); exit; }

sqlsrv_begin_transaction($conn);
try {
    $delDtl = sqlsrv_query($conn, "DELETE FROM dbo.pmlonchuan_dtl WHERE hdr_id=?", [$id]);
    if ($delDtl === false) throw new Exception('Gagal menghapus detail.');
    if ($delDtl) sqlsrv_free_stmt($delDtl);

    $delHdr = sqlsrv_query($conn, "DELETE FROM dbo.pmlonchuan_hdr WHERE id=?", [$id]);
    if ($delHdr === false) throw new Exception('Gagal menghapus header.');
    if ($delHdr) sqlsrv_free_stmt($delHdr);

    sqlsrv_commit($conn);
    echo json_encode(['success'=>true,'message'=>'Data berhasil dihapus.']);
} catch (Exception $e) {
    sqlsrv_rollback($conn);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
