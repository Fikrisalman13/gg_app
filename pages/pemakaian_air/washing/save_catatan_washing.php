<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$menuId = 230; // TODO: ganti dengan MenuId washing di database
$permissions = getPermissions($conn, $_SESSION['GroupId'], $menuId);
if (!empty($permissions) && isset($permissions['CanAdd']) && $permissions['CanAdd'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak untuk menambah catatan.']);
    exit;
}

$tanggal = trim($_POST['tanggal'] ?? '');
$catatan = trim($_POST['catatan'] ?? '');
if ($tanggal === '' || $catatan === '') {
    echo json_encode(['success' => false, 'message' => 'Tanggal dan catatan wajib diisi.']);
    exit;
}

// Catatan disimpan pada baris data washing di tanggal yang sama.
$findSql = "SELECT TOP 1 Id
            FROM dbo.washing_air
            WHERE CAST(Tanggal AS DATE) = ?
              AND (
                  Meter_Awal IS NOT NULL
                  OR Meter_Ahir IS NOT NULL
                  OR Total_Pemakaian IS NOT NULL
                  OR Operasional_Mesin IS NOT NULL
                  OR Pemakaian_rata2perjam IS NOT NULL
              )
            ORDER BY Id DESC";
$findStmt = sqlsrv_query($conn, $findSql, [$tanggal]);
if ($findStmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal mencari data washing pada tanggal tersebut.']);
    exit;
}

$found = sqlsrv_fetch_array($findStmt, SQLSRV_FETCH_ASSOC);
if ($findStmt) sqlsrv_free_stmt($findStmt);

if (!$found || empty($found['Id'])) {
    echo json_encode(['success' => false, 'message' => 'Data washing pada tanggal tersebut belum ada. Silakan simpan data meter terlebih dahulu.']);
    exit;
}

$sql = "UPDATE dbo.washing_air
        SET Catatan = ?, UpdateBy = ?, UpdateAt = GETDATE()
        WHERE Id = ?";
$params = [$catatan, $_SESSION['UserName'], (int)$found['Id']];

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan catatan.']);
    exit;
}

if ($stmt) {
    sqlsrv_free_stmt($stmt);
}

echo json_encode(['success' => true, 'message' => 'Catatan berhasil disimpan pada data tanggal tersebut.']);
