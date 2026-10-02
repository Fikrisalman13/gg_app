<?php
// pages/resep_obat/master_obat/save_obat.php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../koneksi.php';
header('Content-Type: application/json');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$obat_id = $_POST['obat_id'] ?? '';
$kode_obat = $_POST['kode_obat'] ?? '';
$codeprod_proint = $_POST['codeprod_proint'] ?? '';
$nama_obat = $_POST['nama_obat'] ?? '';
$group_obat = $_POST['group_obat'] ?? '';
$uom = $_POST['uom'] ?? ''; // New Input
$user = $_SESSION['UserName'];
$now = date('Y-m-d H:i:s');

if (empty($kode_obat) || empty($nama_obat)) {
    echo json_encode(['status' => 'error', 'message' => 'Kode dan Nama Obat wajib diisi']);
    exit;
}

// Debug Logging
function logMsg($msg) {
    file_put_contents('debug_master_obat.log', date('Y-m-d H:i:s') . ": " . $msg . "\n", FILE_APPEND);
}

logMsg("POST Data: " . print_r($_POST, true));

if ($obat_id) {
    // UPDATE
    $sql = "UPDATE dbo.resep_master_obat SET kode_obat=?, codeprod_proint=?, nama_obat=?, group_obat=?, uom=?, update_at=?, update_by=? WHERE id=?";
    $params = [$kode_obat, $codeprod_proint, $nama_obat, $group_obat, $uom, $now, $user, $obat_id];
} else {
    // INSERT
    $sql = "INSERT INTO dbo.resep_master_obat (kode_obat, codeprod_proint, nama_obat, group_obat, uom, created_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $params = [$kode_obat, $codeprod_proint, $nama_obat, $group_obat, $uom, $now, $user];
}

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    $err = print_r(sqlsrv_errors(), true);
    logMsg("Error: " . $err);
    echo json_encode(['status' => 'error', 'message' => $err]);
} else {
    logMsg("Success");
    echo json_encode(['status' => 'success']);
}
?>
