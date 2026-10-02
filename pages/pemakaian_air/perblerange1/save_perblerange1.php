<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) { echo json_encode(['success'=>false,'message'=>'Silakan login terlebih dahulu.']); exit; }
$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanAdd']) && $permissions['CanAdd'] != 1) { echo json_encode(['success'=>false,'message'=>'Anda tidak memiliki hak menambah data.']); exit; }

function n($v){
    $v=trim((string)$v);
    if($v==='') return 0;
    $v=preg_replace('/[^0-9.,]/','',$v);
    if($v==='') return 0;
    if(strpos($v,'.')!==false){ $a=str_replace(',','',$v); if(is_numeric($a)) return (float)$a; }
    $f=str_replace('.','',$v); $f=str_replace(',','.',$f);
    return is_numeric($f)?(float)$f:0;
}
function hasPost($k){ return array_key_exists($k, $_POST); }
function np($k){ return hasPost($k) ? n($_POST[$k]) : null; }
function sp($k){ return hasPost($k) ? trim((string)$_POST[$k]) : null; }

$id = intval($_POST['id'] ?? 0);
$tanggal = trim($_POST['tanggal'] ?? '');
if ($tanggal === '') { echo json_encode(['success'=>false,'message'=>'Tanggal wajib diisi.']); exit; }

$formMode = trim($_POST['form_mode'] ?? 'form1');
if ($formMode !== 'form2') $formMode = 'form1';

$existing = null;
if ($id > 0) {
    $q = sqlsrv_query($conn, "SELECT TOP 1 * FROM dbo.perblerange1_harian WHERE id=?", [$id]);
    $existing = $q ? sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC) : null;
    if ($q) sqlsrv_free_stmt($q);
}
if (!$existing) {
    $q = sqlsrv_query($conn, "SELECT TOP 1 * FROM dbo.perblerange1_harian WHERE tanggal=?", [$tanggal]);
    $existing = $q ? sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC) : null;
    if ($q) sqlsrv_free_stmt($q);
}

$data = [
    'meter_awal_m3' => isset($existing['meter_awal_m3']) ? (float)$existing['meter_awal_m3'] : 0,
    'meter_akhir_m3' => isset($existing['meter_akhir_m3']) ? (float)$existing['meter_akhir_m3'] : 0,
    'meter_awal_debit_m3' => isset($existing['meter_awal_debit_m3']) ? (float)$existing['meter_awal_debit_m3'] : 0,
    'meter_akhir_debit_m3' => isset($existing['meter_akhir_debit_m3']) ? (float)$existing['meter_akhir_debit_m3'] : 0,
    'operasional_mc_pbr1_jam' => isset($existing['operasional_mc_pbr1_jam']) ? (float)$existing['operasional_mc_pbr1_jam'] : 0,
    'jumlah_debit_m3' => isset($existing['jumlah_debit_m3']) ? (float)$existing['jumlah_debit_m3'] : 0,
    'operasional_mc_pbr1_debit_jam' => isset($existing['operasional_mc_pbr1_debit_jam']) ? (float)$existing['operasional_mc_pbr1_debit_jam'] : 0,
    'ket_mc_yang_jalan' => isset($existing['ket_mc_yang_jalan']) ? (string)$existing['ket_mc_yang_jalan'] : '',
    'ket' => isset($existing['ket']) ? (string)$existing['ket'] : ''
];

if ($formMode === 'form1') {
    $v = np('meter_awal_m3'); if ($v !== null) $data['meter_awal_m3'] = $v;
    $v = np('meter_akhir_m3'); if ($v !== null) $data['meter_akhir_m3'] = $v;
    $v = np('operasional_mc_pbr1_jam'); if ($v !== null) $data['operasional_mc_pbr1_jam'] = $v;
} else {
    $v = np('meter_awal_debit_m3'); if ($v !== null) $data['meter_awal_debit_m3'] = $v;
    $v = np('meter_akhir_debit_m3'); if ($v !== null) $data['meter_akhir_debit_m3'] = $v;
    $v = np('jumlah_debit_m3'); if ($v !== null) $data['jumlah_debit_m3'] = $v;
    $v = np('operasional_mc_pbr1_debit_jam'); if ($v !== null) $data['operasional_mc_pbr1_debit_jam'] = $v;
    $v = sp('ket_mc_yang_jalan'); if ($v !== null) $data['ket_mc_yang_jalan'] = $v;
}
$v = sp('ket'); if ($v !== null) $data['ket'] = $v;

$total = $data['meter_akhir_m3'] - $data['meter_awal_m3'];
if ($total < 0) { echo json_encode(['success'=>false,'message'=>'Meter akhir tidak boleh lebih kecil dari meter awal.']); exit; }
$totalDebit = $data['meter_akhir_debit_m3'] - $data['meter_awal_debit_m3'];
if ($totalDebit < 0) { echo json_encode(['success'=>false,'message'=>'Meter debit akhir tidak boleh lebih kecil dari meter debit awal.']); exit; }

$rata = $data['operasional_mc_pbr1_jam'] > 0 ? ($total / $data['operasional_mc_pbr1_jam']) : 0;
$rataDebit = $data['operasional_mc_pbr1_debit_jam'] > 0 ? ($data['jumlah_debit_m3'] / $data['operasional_mc_pbr1_debit_jam']) : 0;

if ($existing) {
    $targetId = (int)$existing['id'];
    $sql = "UPDATE dbo.perblerange1_harian
            SET meter_awal_m3=?, meter_akhir_m3=?, total_pemakaian_m3=?, meter_awal_debit_m3=?, meter_akhir_debit_m3=?, total_pemakaian_debit_m3=?,
                operasional_mc_pbr1_jam=?, pemakaian_rata_per_jam_m3=?,
                jumlah_debit_m3=?, operasional_mc_pbr1_debit_jam=?, pemakaian_rata_per_jam_debit_m3=?, ket_mc_yang_jalan=?, ket=?,
                updateby=?, updateat=GETDATE()
            WHERE id=?";
    $params = [
        $data['meter_awal_m3'], $data['meter_akhir_m3'], $total,
        $data['meter_awal_debit_m3'], $data['meter_akhir_debit_m3'], $totalDebit,
        $data['operasional_mc_pbr1_jam'], $rata,
        $data['jumlah_debit_m3'], $data['operasional_mc_pbr1_debit_jam'], $rataDebit,
        $data['ket_mc_yang_jalan'], $data['ket'], $_SESSION['UserName'], $targetId
    ];
    $msg = 'Data berhasil diperbarui.';
} else {
    $sql = "INSERT INTO dbo.perblerange1_harian (
                tanggal, meter_awal_m3, meter_akhir_m3, total_pemakaian_m3, meter_awal_debit_m3, meter_akhir_debit_m3, total_pemakaian_debit_m3,
                operasional_mc_pbr1_jam, pemakaian_rata_per_jam_m3,
                jumlah_debit_m3, operasional_mc_pbr1_debit_jam, pemakaian_rata_per_jam_debit_m3, ket_mc_yang_jalan, ket, creatby
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $params = [
        $tanggal, $data['meter_awal_m3'], $data['meter_akhir_m3'], $total,
        $data['meter_awal_debit_m3'], $data['meter_akhir_debit_m3'], $totalDebit,
        $data['operasional_mc_pbr1_jam'], $rata,
        $data['jumlah_debit_m3'], $data['operasional_mc_pbr1_debit_jam'], $rataDebit,
        $data['ket_mc_yang_jalan'], $data['ket'], $_SESSION['UserName']
    ];
    $msg = 'Data berhasil disimpan.';
}

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) { echo json_encode(['success'=>false,'message'=>'Gagal menyimpan data.']); exit; }
if ($stmt) sqlsrv_free_stmt($stmt);
echo json_encode(['success'=>true,'message'=>$msg]);
