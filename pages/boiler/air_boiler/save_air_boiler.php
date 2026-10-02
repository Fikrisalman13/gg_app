<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$menuId = 1239;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);

function n($v) {
    $v = trim((string)$v);
    if ($v === '') return null;
    $v = preg_replace('/[^0-9.,-]/', '', $v);
    if ($v === '' || $v === '-' || $v === '-.' || $v === '-,') return null;
    if (strpos($v, '.') !== false) {
        $a = str_replace(',', '', $v);
        if (is_numeric($a)) return (float)$a;
    }
    $f = str_replace('.', '', $v);
    $f = str_replace(',', '.', $f);
    return is_numeric($f) ? (float)$f : null;
}

$id = intval($_POST['id'] ?? 0);
$tanggal = trim($_POST['tanggal'] ?? '');
if ($tanggal === '') {
    echo json_encode(['success' => false, 'message' => 'Tanggal wajib diisi.']);
    exit;
}

if ($id > 0) {
    if (!empty($permissions) && isset($permissions['CanEdit']) && (int)$permissions['CanEdit'] !== 1) {
        echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak mengubah data.']);
        exit;
    }
} else {
    if (!empty($permissions) && isset($permissions['CanAdd']) && (int)$permissions['CanAdd'] !== 1) {
        echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak menambah data.']);
        exit;
    }
}

$tempUmpan = n($_POST['temp_umpan_c'] ?? null);
$dh = n($_POST['dh_std_lt1'] ?? null);
$tdsUmpanMs = n($_POST['tds_umpan_ms'] ?? null);
$phBoiler = trim((string)($_POST['ph_boiler'] ?? ''));
$tempBoiler = n($_POST['temp_boiler_c'] ?? null);
$tdsBoilerMs = n($_POST['tds_boiler_ms'] ?? null);
$blowdownJumlah = trim((string)($_POST['blowdown_jumlah'] ?? ''));
$keterangan = trim((string)($_POST['keterangan'] ?? ''));

if ($phBoiler === '') $phBoiler = null;
if ($blowdownJumlah === '') $blowdownJumlah = null;
if ($keterangan === '') $keterangan = null;

if ($id > 0) {
    $sql = "UPDATE dbo.air_boiler_alstom_harian
            SET temp_umpan_c=?, dh_std_lt1=?, tds_umpan_ms=?,
                ph_boiler=?, temp_boiler_c=?, tds_boiler_ms=?,
                blowdown_jumlah=?, keterangan=?,
                updateby=?, updateat=GETDATE()
            WHERE id=?";
    $params = [
        $tempUmpan, $dh, $tdsUmpanMs,
        $phBoiler, $tempBoiler, $tdsBoilerMs,
        $blowdownJumlah, $keterangan,
        $_SESSION['UserName'], $id
    ];
    $msg = 'Data berhasil diperbarui.';
} else {
    $sql = "INSERT INTO dbo.air_boiler_alstom_harian
            (tanggal, temp_umpan_c, dh_std_lt1, tds_umpan_ms, ph_boiler, temp_boiler_c, tds_boiler_ms, blowdown_jumlah, keterangan, creatby)
            VALUES (?,?,?,?,?,?,?,?,?,?)";
    $params = [
        $tanggal, $tempUmpan, $dh, $tdsUmpanMs,
        $phBoiler, $tempBoiler, $tdsBoilerMs,
        $blowdownJumlah, $keterangan, $_SESSION['UserName']
    ];
    $msg = 'Data berhasil disimpan.';
}

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    $err = sqlsrv_errors(SQLSRV_ERR_ERRORS);
    $detail = (!empty($err) && isset($err[0]['message'])) ? (' ' . $err[0]['message']) : '';
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data.' . $detail]);
    exit;
}
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['success' => true, 'message' => $msg]);
