<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) { echo json_encode(['success'=>false,'message'=>'Silakan login terlebih dahulu.']); exit; }
$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanAdd']) && $permissions['CanAdd'] != 1) { echo json_encode(['success'=>false,'message'=>'Anda tidak memiliki hak menambah data.']); exit; }

function numOrNull($v){
    $v = trim((string)$v);
    if ($v === '') return null;
    $v = preg_replace('/[^0-9.,]/','',$v);
    if ($v === '') return null;
    if (strpos($v,'.') !== false) {
        $a = str_replace(',','',$v);
        return is_numeric($a) ? (float)$a : null;
    }
    $f = str_replace('.','',$v);
    $f = str_replace(',','.',$f);
    return is_numeric($f) ? (float)$f : null;
}

$id = intval($_POST['id'] ?? 0);
$tanggal = trim($_POST['tanggal'] ?? '');
$note = trim($_POST['note'] ?? '');
if ($tanggal === '') { echo json_encode(['success'=>false,'message'=>'Tanggal wajib diisi.']); exit; }

$jamArr = $_POST['jam'] ?? [];
$airArr = $_POST['air'] ?? [];
$steamArr = $_POST['steam'] ?? [];

sqlsrv_begin_transaction($conn);
try {
    $hdrId = 0;
    if ($id > 0) {
        $upd = sqlsrv_query($conn, "UPDATE dbo.pmlonchuan_hdr SET periode=?, note=?, updated_by=?, updated_at=GETDATE() WHERE id=?", [$tanggal, $note, $_SESSION['UserName'], $id]);
        if ($upd === false) throw new Exception('Gagal memperbarui header.');
        if ($upd) sqlsrv_free_stmt($upd);
        $hdrId = $id;
    } else {
        $cek = sqlsrv_query($conn, "SELECT TOP 1 id FROM dbo.pmlonchuan_hdr WHERE periode=?", [$tanggal]);
        $row = $cek ? sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC) : null;
        if ($cek) sqlsrv_free_stmt($cek);

        if ($row) {
            $hdrId = (int)$row['id'];
            $upd = sqlsrv_query($conn, "UPDATE dbo.pmlonchuan_hdr SET note=?, updated_by=?, updated_at=GETDATE() WHERE id=?", [$note, $_SESSION['UserName'], $hdrId]);
            if ($upd === false) throw new Exception('Gagal memperbarui header.');
            if ($upd) sqlsrv_free_stmt($upd);
        } else {
            $ins = sqlsrv_query($conn, "INSERT INTO dbo.pmlonchuan_hdr (periode, created_at, created_by, note) OUTPUT INSERTED.id VALUES (?,?,?,?)", [$tanggal, date('Y-m-d H:i:s'), $_SESSION['UserName'], $note]);
            if ($ins === false) throw new Exception('Gagal menyimpan header.');
            $row = sqlsrv_fetch_array($ins, SQLSRV_FETCH_ASSOC);
            if ($ins) sqlsrv_free_stmt($ins);
            $hdrId = (int)($row['id'] ?? 0);
        }
    }

    if ($hdrId <= 0) throw new Exception('ID header tidak valid.');

    $del = sqlsrv_query($conn, "DELETE FROM dbo.pmlonchuan_dtl WHERE hdr_id=?", [$hdrId]);
    if ($del === false) throw new Exception('Gagal membersihkan detail lama.');
    if ($del) sqlsrv_free_stmt($del);

    $insertSql = "INSERT INTO dbo.pmlonchuan_dtl (hdr_id, tgl, jam, jenis, nilai_ton, created_by) VALUES (?,?,?,?,?,?)";

    $count = is_array($jamArr) ? count($jamArr) : 0;
    for ($i=0; $i<$count; $i++) {
        $jam = isset($jamArr[$i]) ? (int)$jamArr[$i] : null;
        if ($jam === null || $jam < 0 || $jam > 23) continue;

        $airVal = numOrNull($airArr[$i] ?? '');
        $steamVal = numOrNull($steamArr[$i] ?? '');

        if ($airVal !== null) {
            $ins = sqlsrv_query($conn, $insertSql, [$hdrId, $tanggal, $jam, 'AIR', $airVal, $_SESSION['UserName']]);
            if ($ins === false) throw new Exception('Gagal menyimpan detail AIR.');
            if ($ins) sqlsrv_free_stmt($ins);
        }
        if ($steamVal !== null) {
            $ins = sqlsrv_query($conn, $insertSql, [$hdrId, $tanggal, $jam, 'STEAM', $steamVal, $_SESSION['UserName']]);
            if ($ins === false) throw new Exception('Gagal menyimpan detail STEAM.');
            if ($ins) sqlsrv_free_stmt($ins);
        }
    }

    sqlsrv_commit($conn);
    echo json_encode(['success'=>true,'message'=>'Data berhasil disimpan.']);
} catch (Exception $e) {
    sqlsrv_rollback($conn);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
