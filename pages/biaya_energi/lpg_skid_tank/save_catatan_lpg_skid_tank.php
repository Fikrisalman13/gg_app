<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) { echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']); exit; }

$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanAdd']) && $permissions['CanAdd'] != 1) { echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak untuk menambah catatan.']); exit; }

$tanggal = trim($_POST['tanggal'] ?? '');
$tankKode = trim($_POST['tank_kode'] ?? '');
$note = trim($_POST['note'] ?? '');
if ($tanggal === '' || $tankKode === '' || $note === '') { echo json_encode(['success' => false, 'message' => 'Tanggal, tank dan catatan wajib diisi.']); exit; }

$cek = sqlsrv_query($conn, "SELECT TOP 1 id FROM dbo.lpg_skid_tank_harian WHERE CAST(tanggal AS DATE)=? AND tank_kode=?", [$tanggal, $tankKode]);
$row = $cek ? sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC) : null;
if ($cek) sqlsrv_free_stmt($cek);
if (!$row) { echo json_encode(['success' => false, 'message' => 'Data tanggal tersebut belum ada untuk tank ini.']); exit; }

$sql = "UPDATE dbo.lpg_skid_tank_harian SET note=?, updateby=?, updateat=GETDATE() WHERE id=?";
$stmt = sqlsrv_query($conn, $sql, [$note, $_SESSION['UserName'], $row['id']]);
if ($stmt === false) { echo json_encode(['success' => false, 'message' => 'Gagal menyimpan catatan.']); exit; }
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['success' => true, 'message' => 'Catatan berhasil disimpan.']);