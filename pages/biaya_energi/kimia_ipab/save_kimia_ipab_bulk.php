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

$tanggal = trim($_POST['tanggal'] ?? '');
$itemsRaw = $_POST['items'] ?? '';
if ($itemsRaw === '') {
    $itemsRaw = file_get_contents('php://input');
}

if ($tanggal === '') {
    echo json_encode(['success' => false, 'message' => 'Tanggal wajib diisi.']);
    exit;
}

$items = json_decode($itemsRaw, true);
if (!is_array($items) || count($items) === 0) {
    echo json_encode(['success' => false, 'message' => 'Data item kosong.']);
    exit;
}

// Load active master ids for validation
$masterMap = [];
$masterStmt = sqlsrv_query($conn, "SELECT id FROM dbo.kimia_ipab_master WHERE aktif=1");
if ($masterStmt) {
    while ($r = sqlsrv_fetch_array($masterStmt, SQLSRV_FETCH_ASSOC)) {
        $masterMap[(int)$r['id']] = true;
    }
    sqlsrv_free_stmt($masterStmt);
}

sqlsrv_begin_transaction($conn);
$inserted = 0;
$updated = 0;

foreach ($items as $it) {
    $masterId = intval($it['master_id'] ?? 0);
    if ($masterId <= 0 || !isset($masterMap[$masterId])) { sqlsrv_rollback($conn); echo json_encode(['success'=>false,'message'=>'Master item tidak valid.']); exit; }

    $pakai = n($it['pakai_kg'] ?? '');
    $harga = n($it['harga_rp'] ?? '');
    $catatan = trim((string)($it['catatan'] ?? ''));

    if ($pakai === null || $harga === null) { sqlsrv_rollback($conn); echo json_encode(['success'=>false,'message'=>'Pakai dan harga wajib diisi.']); exit; }

    $cek = sqlsrv_query($conn, "SELECT TOP 1 id FROM dbo.kimia_ipab_harian WHERE tanggal=? AND master_id=?", [$tanggal, $masterId]);
    $row = $cek ? sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC) : null;
    if ($cek) sqlsrv_free_stmt($cek);

    if ($row) {
        $sql = "UPDATE dbo.kimia_ipab_harian
                SET pakai_kg=?, harga_rp=?, catatan=?, updateby=?, updateat=GETDATE()
                WHERE id=?";
        $params = [$pakai, $harga, $catatan, $_SESSION['UserName'], $row['id']];
        $ok = sqlsrv_query($conn, $sql, $params);
        if ($ok === false) { sqlsrv_rollback($conn); echo json_encode(['success'=>false,'message'=>'Gagal update data.']); exit; }
        $updated++;
    } else {
        $sql = "INSERT INTO dbo.kimia_ipab_harian (tanggal, master_id, pakai_kg, harga_rp, catatan, creatby)
                VALUES (?, ?, ?, ?, ?, ?)";
        $params = [$tanggal, $masterId, $pakai, $harga, $catatan, $_SESSION['UserName']];
        $ok = sqlsrv_query($conn, $sql, $params);
        if ($ok === false) { sqlsrv_rollback($conn); echo json_encode(['success'=>false,'message'=>'Gagal insert data.']); exit; }
        $inserted++;
    }
}

sqlsrv_commit($conn);

$msg = 'Tersimpan. Insert: ' . $inserted . ', Update: ' . $updated . '.';
echo json_encode(['success' => true, 'message' => $msg]);

