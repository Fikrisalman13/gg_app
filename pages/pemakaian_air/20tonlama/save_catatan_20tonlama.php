<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) { echo json_encode(['success'=>false,'message'=>'Silakan login terlebih dahulu.']); exit; }
$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanAdd']) && $permissions['CanAdd'] != 1) { echo json_encode(['success'=>false,'message'=>'Anda tidak memiliki hak menambah catatan.']); exit; }

$tanggal = trim($_POST['tanggal'] ?? '');
$note = trim($_POST['note'] ?? '');
if ($tanggal === '' || $note === '') { echo json_encode(['success'=>false,'message'=>'Tanggal dan catatan wajib diisi.']); exit; }

$cek = sqlsrv_query($conn, "SELECT TOP 1 id FROM dbo.air_steam_20tonlama_harian WHERE tanggal=?", [$tanggal]);
$row = $cek ? sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC) : null;
if ($cek) sqlsrv_free_stmt($cek);

if ($row) {
    $sql = "UPDATE dbo.air_steam_20tonlama_harian SET note=?, updateby=?, updateat=GETDATE() WHERE id=?";
    $params = [$note, $_SESSION['UserName'], $row['id']];
    $msg = 'Catatan berhasil diperbarui.';
} else {
    $sql = "INSERT INTO dbo.air_steam_20tonlama_harian (tanggal, note, creatby) VALUES (?,?,?)";
    $params = [$tanggal, $note, $_SESSION['UserName']];
    $msg = 'Catatan berhasil disimpan.';
}

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) { echo json_encode(['success'=>false,'message'=>'Gagal menyimpan catatan.']); exit; }
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['success'=>true,'message'=>$msg]);
