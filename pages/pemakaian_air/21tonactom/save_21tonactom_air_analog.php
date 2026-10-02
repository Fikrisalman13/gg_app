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
$permissions = getPermissions($conn, $_SESSION['GroupId'], $menuId);
if (!empty($permissions) && isset($permissions['CanAdd']) && $permissions['CanAdd'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak menambah data.']);
    exit;
}

function n($v) {
    $v = trim((string) $v);
    if ($v === '') return null;
    $v = preg_replace('/[^0-9.,]/', '', $v);
    if ($v === '') return null;
    if (strpos($v, '.') !== false) {
        $a = str_replace(',', '', $v);
        if (is_numeric($a)) return (float) $a;
    }
    $f = str_replace('.', '', $v);
    $f = str_replace(',', '.', $f);
    return is_numeric($f) ? (float) $f : null;
}

$tanggal = trim($_POST['tanggal'] ?? '');
$awal = n($_POST['water_awal'] ?? '');
$akhir = n($_POST['water_akhir'] ?? '');
$catatan = trim($_POST['keterangan'] ?? '');

if ($tanggal === '' || $awal === null || $akhir === null) {
    echo json_encode(['success' => false, 'message' => 'Tanggal, Awal, dan Akhir wajib diisi.']);
    exit;
}

$total = round($akhir - $awal, 2);
$rata = round($total / 24, 2);

$cek = sqlsrv_query($conn, "SELECT TOP 1 id FROM dbo.air_analog_actom WHERE tanggal=?", [$tanggal]);
$row = $cek ? sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC) : null;
if ($cek) sqlsrv_free_stmt($cek);

if ($row) {
    $sql = "UPDATE dbo.air_analog_actom
            SET awal=?, ahir=?, total_pemakaian=?, pemakaianrata2perjam=?, catatan=?, updateby=?, updateat=GETDATE()
            WHERE tanggal=?";
    $params = [$awal, $akhir, $total, $rata, $catatan, $_SESSION['UserName'], $tanggal];
} else {
    $sql = "INSERT INTO dbo.air_analog_actom
            (tanggal, awal, ahir, total_pemakaian, pemakaianrata2perjam, catatan, creatby)
            VALUES (?,?,?,?,?,?,?)";
    $params = [$tanggal, $awal, $akhir, $total, $rata, $catatan, $_SESSION['UserName']];
}

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data Air Analog.']);
    exit;
}
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['success' => true, 'message' => 'Data Air Analog berhasil disimpan.']);

