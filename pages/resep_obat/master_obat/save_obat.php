<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../koneksi.php';
header('Content-Type: application/json');
$requestId = bin2hex(random_bytes(8));

function masterObatRespond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['UserName'])) {
    masterObatRespond(401, ['status' => 'error', 'message' => 'Unauthorized', 'request_id' => $requestId]);
}

$mode = ($_POST['mode'] ?? 'add') === 'edit' ? 'edit' : 'add';
$permissionColumn = $mode === 'edit' ? 'CanEdit' : 'CanAdd';
$permissionStatement = sqlsrv_query($conn, "SELECT $permissionColumn AS allowed FROM dbo.SMGroupTrustee WHERE GroupId=? AND MenuId=213", [$_SESSION['GroupId'] ?? 0]);
$permission = $permissionStatement ? sqlsrv_fetch_array($permissionStatement, SQLSRV_FETCH_ASSOC) : null;
if (($permission['allowed'] ?? 0) != 1) {
    masterObatRespond(403, ['status' => 'error', 'message' => 'Tidak memiliki hak menyimpan Master Obat.', 'request_id' => $requestId]);
}

$obatId = filter_var($_POST['obat_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$kodeObat = trim((string) ($_POST['kode_obat'] ?? ''));
$codeprodProint = trim((string) ($_POST['codeprod_proint'] ?? ''));
$namaObat = trim((string) ($_POST['nama_obat'] ?? ''));
$groupObat = trim((string) ($_POST['group_obat'] ?? ''));
$uom = trim((string) ($_POST['uom'] ?? ''));
$minimumRaw = trim((string) ($_POST['minimum_stock_kg'] ?? ''));
$minimumStockKg = $minimumRaw === '' ? null : filter_var($minimumRaw, FILTER_VALIDATE_FLOAT);
$user = $_SESSION['UserName'];
$now = date('Y-m-d H:i:s');

if ($kodeObat === '' || $namaObat === '') {
    masterObatRespond(422, ['status' => 'error', 'message' => 'Kode dan Nama Obat wajib diisi.', 'request_id' => $requestId]);
}
if ($minimumRaw !== '' && ($minimumStockKg === false || $minimumStockKg < 0)) {
    masterObatRespond(422, ['status' => 'error', 'message' => 'Target minimum harus berupa angka 0 atau lebih.', 'request_id' => $requestId]);
}
if ($mode === 'edit' && !$obatId) {
    masterObatRespond(422, ['status' => 'error', 'message' => 'ID Master Obat tidak valid.', 'request_id' => $requestId]);
}

if ($mode === 'edit') {
    $sql = 'UPDATE dbo.resep_master_obat SET kode_obat=?, codeprod_proint=?, nama_obat=?, group_obat=?, uom=?, minimum_stock_kg=?, update_at=?, update_by=? WHERE id=?';
    $params = [$kodeObat, $codeprodProint, $namaObat, $groupObat, $uom, $minimumStockKg, $now, $user, $obatId];
} else {
    $sql = 'INSERT INTO dbo.resep_master_obat (kode_obat, codeprod_proint, nama_obat, group_obat, uom, minimum_stock_kg, created_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
    $params = [$kodeObat, $codeprodProint, $namaObat, $groupObat, $uom, $minimumStockKg, $now, $user];
}

if (sqlsrv_query($conn, $sql, $params) === false) {
    $logDirectory = __DIR__ . '/../../../logs';
    if (!is_dir($logDirectory)) @mkdir($logDirectory, 0775, true);
    @file_put_contents($logDirectory . '/error-' . date('Y-m-d') . '.log', json_encode([
        'timestamp' => date(DATE_ATOM), 'severity' => 'ERROR', 'request_id' => $requestId,
        'module' => 'resep_obat/master_obat', 'action' => 'save_obat', 'user' => $user,
        'message' => 'Failed to save Master Obat.', 'source' => basename(__FILE__),
        'context' => ['mode' => $mode, 'material_id' => $obatId],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    masterObatRespond(500, ['status' => 'error', 'message' => 'Gagal menyimpan Master Obat.', 'request_id' => $requestId]);
}

masterObatRespond(200, ['status' => 'success']);
