<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) {
    echo json_encode(['success'=>false,'message'=>'Anda tidak memiliki hak melihat data.']);
    exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { echo json_encode(['success'=>false,'message'=>'ID tidak valid.']); exit; }

$hdrStmt = sqlsrv_query($conn, "SELECT id, periode, note FROM dbo.pmlonchuan_hdr WHERE id=?", [$id]);
$hdr = $hdrStmt ? sqlsrv_fetch_array($hdrStmt, SQLSRV_FETCH_ASSOC) : null;
if ($hdrStmt) sqlsrv_free_stmt($hdrStmt);
if (!$hdr) { echo json_encode(['success'=>false,'message'=>'Data tidak ditemukan.']); exit; }

$tgl = $hdr['periode'];
if ($tgl instanceof DateTime) $tanggal = $tgl->format('Y-m-d');
else $tanggal = $tgl ? date('Y-m-d', strtotime((string)$tgl)) : '';

$rows = [];
$map = [];
$dtlStmt = sqlsrv_query($conn, "SELECT jam, jenis, nilai_ton FROM dbo.pmlonchuan_dtl WHERE hdr_id=?", [$id]);
if ($dtlStmt) {
    while ($r = sqlsrv_fetch_array($dtlStmt, SQLSRV_FETCH_ASSOC)) {
        $jam = (int)($r['jam'] ?? 0);
        $jenis = strtoupper(trim((string)($r['jenis'] ?? '')));
        if (!isset($map[$jam])) $map[$jam] = ['jam'=>$jam, 'air'=>null, 'steam'=>null];
        if ($jenis === 'AIR') $map[$jam]['air'] = (float)($r['nilai_ton'] ?? 0);
        if ($jenis === 'STEAM') $map[$jam]['steam'] = (float)($r['nilai_ton'] ?? 0);
    }
    sqlsrv_free_stmt($dtlStmt);
}

ksort($map);
foreach ($map as $m) $rows[] = $m;

echo json_encode(['success'=>true,'data'=>[
    'id' => $id,
    'tanggal' => $tanggal,
    'note' => $hdr['note'] ?? '',
    'rows' => $rows
]]);
