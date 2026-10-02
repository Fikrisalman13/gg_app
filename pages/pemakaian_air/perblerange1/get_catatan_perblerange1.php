<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) { echo json_encode(['success'=>false,'message'=>'Silakan login terlebih dahulu.']); exit; }
$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) { echo json_encode(['success'=>false,'message'=>'Anda tidak memiliki hak akses.']); exit; }

$startDate = trim($_POST['start_date'] ?? '');
$endDate = trim($_POST['end_date'] ?? '');
if ($startDate === '' || $endDate === '') { echo json_encode(['success'=>false,'message'=>'Rentang tanggal wajib diisi.']); exit; }

$sql = "SELECT CAST(tanggal AS DATE) AS tanggal, note, COALESCE(updateby,creatby,'-') AS creatby
        FROM dbo.perblerange1_harian
        WHERE CAST(tanggal AS DATE) BETWEEN ? AND ?
          AND LTRIM(RTRIM(ISNULL(note,''))) <> ''
        ORDER BY CAST(tanggal AS DATE) ASC";
$stmt = sqlsrv_query($conn, $sql, [$startDate, $endDate]);
if ($stmt === false) { echo json_encode(['success'=>false,'message'=>'Gagal mengambil catatan.']); exit; }

$data = [];
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tglObj = $r['tanggal'] ?? null;
    $tgl = ($tglObj instanceof DateTime) ? $tglObj->format('d-m-Y') : date('d-m-Y', strtotime((string)$tglObj));
    $data[] = ['tanggal' => $tgl, 'catatan' => (string)($r['note'] ?? ''), 'creatby' => (string)($r['creatby'] ?? '-')];
}
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['success'=>true,'data'=>$data]);
