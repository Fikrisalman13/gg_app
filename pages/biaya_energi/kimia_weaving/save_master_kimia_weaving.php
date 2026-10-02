<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanEdit']) && $permissions['CanEdit'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak setting master.']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$kode = strtoupper(trim($_POST['kode'] ?? ''));
$nama = trim($_POST['nama_item'] ?? '');
$grup = trim($_POST['grup_laporan'] ?? '');
$satuan = trim($_POST['satuan_pakai'] ?? '');
$aktif = intval($_POST['aktif'] ?? 1) === 1 ? 1 : 0;

$allowedGroup = ['WEAVING'];
if ($kode === '' || $nama === '' || $satuan === '' || !in_array($grup, $allowedGroup, true)) {
    echo json_encode(['success' => false, 'message' => 'Kode, nama, grup, dan satuan wajib valid.']);
    exit;
}

if ($id > 0) {
    $dup = sqlsrv_query($conn, "SELECT TOP 1 id FROM dbo.kimia_weaving_master WHERE kode=? AND id<>?", [$kode, $id]);
    $dupRow = $dup ? sqlsrv_fetch_array($dup, SQLSRV_FETCH_ASSOC) : null;
    if ($dup) sqlsrv_free_stmt($dup);
    if ($dupRow) {
        echo json_encode(['success' => false, 'message' => 'Kode master sudah dipakai item lain.']);
        exit;
    }

    $sql = "UPDATE dbo.kimia_weaving_master
            SET kode=?, nama_item=?, grup_laporan=?, satuan_pakai=?, aktif=?, updateby=?, updateat=GETDATE()
            WHERE id=?";
    $params = [$kode, $nama, $grup, $satuan, $aktif, $_SESSION['UserName'], $id];
    $msg = 'Master berhasil diperbarui.';
} else {
    $dup = sqlsrv_query($conn, "SELECT TOP 1 id FROM dbo.kimia_weaving_master WHERE kode=?", [$kode]);
    $dupRow = $dup ? sqlsrv_fetch_array($dup, SQLSRV_FETCH_ASSOC) : null;
    if ($dup) sqlsrv_free_stmt($dup);
    if ($dupRow) {
        echo json_encode(['success' => false, 'message' => 'Kode master sudah ada.']);
        exit;
    }

    $sql = "INSERT INTO dbo.kimia_weaving_master (kode, nama_item, grup_laporan, satuan_pakai, aktif, creatby)
            VALUES (?, ?, ?, ?, ?, ?)";
    $params = [$kode, $nama, $grup, $satuan, $aktif, $_SESSION['UserName']];
    $msg = 'Master berhasil ditambahkan.';
}

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    $err = sqlsrv_errors(SQLSRV_ERR_ERRORS);
    $detail = (!empty($err) && isset($err[0]['message'])) ? (' ' . $err[0]['message']) : '';
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan master.' . $detail]);
    exit;
}
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['success' => true, 'message' => $msg]);


