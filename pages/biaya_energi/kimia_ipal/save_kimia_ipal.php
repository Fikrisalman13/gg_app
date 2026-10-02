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
if (!empty($permissions) && isset($permissions['CanAdd']) && $permissions['CanAdd'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak menambah data.']);
    exit;
}

function n($v){
    $v = trim((string)$v);
    if ($v === '') return null;
    $v = preg_replace('/[^0-9.,]/', '', $v);
    if ($v === '') return null;
    if (strpos($v, '.') !== false) {
        $a = str_replace(',', '', $v);
        if (is_numeric($a)) return (float)$a;
    }
    $f = str_replace('.', '', $v);
    $f = str_replace(',', '.', $f);
    return is_numeric($f) ? (float)$f : null;
}

$masterId = intval($_POST['master_id'] ?? 0);
$tanggal = trim($_POST['tanggal'] ?? '');
$pakai = n($_POST['pakai_kg'] ?? '');
$harga = n($_POST['harga_rp'] ?? '');
$catatan = trim($_POST['catatan'] ?? '');

if ($masterId <= 0 || $tanggal === '' || $pakai === null || $harga === null) {
    echo json_encode(['success' => false, 'message' => 'Master item, tanggal, pakai, dan harga wajib diisi.']);
    exit;
}

$cekMaster = sqlsrv_query($conn, "SELECT TOP 1 id FROM dbo.kimia_ipal_master WHERE id=? AND aktif=1", [$masterId]);
$masterRow = $cekMaster ? sqlsrv_fetch_array($cekMaster, SQLSRV_FETCH_ASSOC) : null;
if ($cekMaster) sqlsrv_free_stmt($cekMaster);
if (!$masterRow) {
    echo json_encode(['success' => false, 'message' => 'Master item tidak valid.']);
    exit;
}

$cek = sqlsrv_query($conn, "SELECT TOP 1 id FROM dbo.kimia_ipal_harian WHERE tanggal=? AND master_id=?", [$tanggal, $masterId]);
$row = $cek ? sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC) : null;
if ($cek) sqlsrv_free_stmt($cek);

if ($row) {
    $sql = "UPDATE dbo.kimia_ipal_harian
            SET pakai_kg=?, harga_rp=?, catatan=?, updateby=?, updateat=GETDATE()
            WHERE id=?";
    $params = [$pakai, $harga, $catatan, $_SESSION['UserName'], $row['id']];
} else {
    $sql = "INSERT INTO dbo.kimia_ipal_harian (tanggal, master_id, pakai_kg, harga_rp, catatan, creatby)
            VALUES (?, ?, ?, ?, ?, ?)";
    $params = [$tanggal, $masterId, $pakai, $harga, $catatan, $_SESSION['UserName']];
}

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    $err = sqlsrv_errors(SQLSRV_ERR_ERRORS);
    $msg = 'Gagal menyimpan data.';
    if (!empty($err) && isset($err[0]['message'])) $msg .= ' ' . $err[0]['message'];
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['success' => true, 'message' => 'Data berhasil disimpan.']);

