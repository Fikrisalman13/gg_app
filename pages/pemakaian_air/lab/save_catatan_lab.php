<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$menuId = 230; // TODO: ganti dengan MenuId LAB di database
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanAdd']) && (int)$permissions['CanAdd'] !== 1) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak untuk menambah catatan.']);
    exit;
}

$tanggal = trim($_POST['tanggal'] ?? '');
$catatan = trim($_POST['catatan'] ?? '');
if ($tanggal === '' || $catatan === '') {
    echo json_encode(['success' => false, 'message' => 'Tanggal dan catatan wajib diisi.']);
    exit;
}

$cekStmt = sqlsrv_query($conn, "SELECT TOP 1 Id FROM dbo.lab_air WHERE CAST(Tanggal AS DATE)=? ORDER BY Id DESC", [$tanggal]);
if ($cekStmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal mengecek data catatan.']);
    exit;
}
$existing = sqlsrv_fetch_array($cekStmt, SQLSRV_FETCH_ASSOC);
if ($cekStmt) sqlsrv_free_stmt($cekStmt);

if ($existing && !empty($existing['Id'])) {
    $sql = "UPDATE dbo.lab_air
            SET Catatan = ?, UpdateBy = ?, UpdateAt = GETDATE()
            WHERE Id = ?";
    $params = [$catatan, $_SESSION['UserName'], (int)$existing['Id']];
    $message = 'Catatan berhasil diperbarui pada tanggal tersebut.';
} else {
    $sql = "INSERT INTO dbo.lab_air (Tanggal, Catatan, CreatBy)
            VALUES (?, ?, ?)";
    $params = [$tanggal, $catatan, $_SESSION['UserName']];
    $message = 'Catatan berhasil disimpan.';
}

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan catatan.']);
    exit;
}
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['success' => true, 'message' => $message]);

