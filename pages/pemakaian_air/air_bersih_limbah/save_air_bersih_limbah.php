<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) { echo json_encode(['success'=>false,'message'=>'Silakan login terlebih dahulu.']); exit; }
$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
$canAdd = !empty($permissions['CanAdd']) && (int)$permissions['CanAdd'] === 1;
$canEdit = !empty($permissions['CanEdit']) && (int)$permissions['CanEdit'] === 1;
if (!$canAdd && !$canEdit) { echo json_encode(['success'=>false,'message'=>'Anda tidak memiliki hak menambah atau mengubah data.']); exit; }

function n($v){ $v=trim((string)$v); if($v==='') return 0; $v=preg_replace('/[^0-9.,]/','',$v); if($v==='') return 0; if(strpos($v,'.')!==false){ $a=str_replace(',','',$v); if(is_numeric($a)) return (float)$a; } $f=str_replace('.','',$v); $f=str_replace(',','.',$f); return is_numeric($f)?(float)$f:0; }

function get_washing_total($conn, $tanggal) {
    $sql = "SELECT SUM(Total_Pemakaian) AS total_pemakaian
            FROM dbo.washing_air
            WHERE CAST(Tanggal AS DATE) = ?";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal]);
    if ($stmt === false) return null;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $row['total_pemakaian'] ?? null;
}

function get_washing2_total($conn, $tanggal) {
    $sql = "SELECT SUM(TotalPemakaian) AS total_pemakaian
            FROM dbo.washing2_water_meter
            WHERE CAST(Tanggal AS DATE) = ?";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal]);
    if ($stmt === false) return null;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $row['total_pemakaian'] ?? null;
}

function get_washing3_total($conn, $tanggal) {
    $sql = "SELECT SUM(TotalPemakaian) AS total_pemakaian
            FROM dbo.washing3_air
            WHERE CAST(Tanggal AS DATE) = ?";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal]);
    if ($stmt === false) return null;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $row['total_pemakaian'] ?? null;
}

function get_perblerange1_debit($conn, $tanggal) {
    $sql = "SELECT SUM(jumlah_debit_m3) AS total_debit
            FROM dbo.perblerange1_harian
            WHERE CAST(tanggal AS DATE) = ?";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal]);
    if ($stmt === false) return null;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $row['total_debit'] ?? null;
}

function get_perblerange2_total($conn, $tanggal) {
    $sql = "SELECT SUM(Total_Pemakaian) AS total_pemakaian
            FROM dbo.perblerange2_air
            WHERE CAST(Tanggal AS DATE) = ?";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal]);
    if ($stmt === false) return null;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $row['total_pemakaian'] ?? null;
}

function get_padsteam_total($conn, $tanggal) {
    $sql = "SELECT SUM(Meter_Ahir - Meter_Awal) AS total_pemakaian
            FROM dbo.padsteam_air
            WHERE CAST(Tanggal AS DATE) = ?";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal]);
    if ($stmt === false) return null;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $row['total_pemakaian'] ?? null;
}

function get_jetdyeing_total($conn, $tanggal) {
    $sql = "SELECT SUM(Total_Pemakaian) AS total_pemakaian
            FROM dbo.jetdyeing_air
            WHERE CAST(Tanggal AS DATE) = ?";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal]);
    if ($stmt === false) return null;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $row['total_pemakaian'] ?? null;
}

function get_mkp_total($conn, $tanggal) {
    $sql = "SELECT SUM(Total_Pemakaian) AS total_pemakaian
            FROM dbo.mkp_air
            WHERE CAST(Tanggal AS DATE) = ?";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal]);
    if ($stmt === false) return null;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $row['total_pemakaian'] ?? null;
}

$tanggal = trim($_POST['tanggal'] ?? '');
if ($tanggal === '') { echo json_encode(['success'=>false,'message'=>'Tanggal wajib diisi.']); exit; }

$fields = [
    'flow_meter_intake_ipab_m3_hari',
    'flow_meter_bak_dua_ke_ipal_m3_hari',
    'flow_meter_bak3_ke_bak4_jasa_tirta_m3_hari',
    'washing_1_m3_hari',
    'washing_2_m3_hari',
    'washing_3_m3_hari',
    'perble_range_1_m3_hari',
    'perble_range_2_m3_hari',
    'pad_steam_m3_hari',
    'jetdying_sizing_la_m3_hari',
    'kantin_mes_pos_security_m3_hari',
    'boiler_m3_hari',
    'air_mc_produksi_dan_lain_lain_m3_hari',
    'flowmeter_output_ipal_m3_hari'
];

$values = [];
foreach ($fields as $f) { $values[$f] = n($_POST[$f] ?? '0'); }

$washingTotal = get_washing_total($conn, $tanggal);
if ($washingTotal !== null) {
    $values['washing_1_m3_hari'] = (float)$washingTotal;
}

$washing2Total = get_washing2_total($conn, $tanggal);
if ($washing2Total !== null) {
    $values['washing_2_m3_hari'] = (float)$washing2Total;
}

$washing3Total = get_washing3_total($conn, $tanggal);
if ($washing3Total !== null) {
    $values['washing_3_m3_hari'] = (float)$washing3Total;
}

$perbleRange1Debit = get_perblerange1_debit($conn, $tanggal);
if ($perbleRange1Debit !== null) {
    $values['perble_range_1_m3_hari'] = (float)$perbleRange1Debit;
}

$perbleRange2Total = get_perblerange2_total($conn, $tanggal);
if ($perbleRange2Total !== null) {
    $values['perble_range_2_m3_hari'] = (float)$perbleRange2Total;
}

$padSteamTotal = get_padsteam_total($conn, $tanggal);
if ($padSteamTotal !== null) {
    $values['pad_steam_m3_hari'] = (float)$padSteamTotal;
}

$jetdyeingTotal = get_jetdyeing_total($conn, $tanggal);
if ($jetdyeingTotal !== null) {
    $values['jetdying_sizing_la_m3_hari'] = (float)$jetdyeingTotal;
}

$mkpTotal = get_mkp_total($conn, $tanggal);
if ($mkpTotal !== null) {
    $values['kantin_mes_pos_security_m3_hari'] = (float)$mkpTotal;
}

$cek = sqlsrv_query($conn, "SELECT TOP 1 id FROM dbo.air_bersih_limbah_harian WHERE tanggal=?", [$tanggal]);
$row = $cek ? sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC) : null;
if ($cek) sqlsrv_free_stmt($cek);

if ($row) {
    if (!$canEdit) { echo json_encode(['success'=>false,'message'=>'Anda tidak memiliki hak mengubah data.']); exit; }
    $sql = "UPDATE dbo.air_bersih_limbah_harian
            SET flow_meter_intake_ipab_m3_hari=?, flow_meter_bak_dua_ke_ipal_m3_hari=?, flow_meter_bak3_ke_bak4_jasa_tirta_m3_hari=?,
                washing_1_m3_hari=?, washing_2_m3_hari=?, washing_3_m3_hari=?, perble_range_1_m3_hari=?, perble_range_2_m3_hari=?,
                pad_steam_m3_hari=?, jetdying_sizing_la_m3_hari=?, kantin_mes_pos_security_m3_hari=?, boiler_m3_hari=?,
                air_mc_produksi_dan_lain_lain_m3_hari=?, flowmeter_output_ipal_m3_hari=?, updateby=?, updateat=GETDATE()
            WHERE id=?";
    $params = [
        $values['flow_meter_intake_ipab_m3_hari'], $values['flow_meter_bak_dua_ke_ipal_m3_hari'], $values['flow_meter_bak3_ke_bak4_jasa_tirta_m3_hari'],
        $values['washing_1_m3_hari'], $values['washing_2_m3_hari'], $values['washing_3_m3_hari'], $values['perble_range_1_m3_hari'], $values['perble_range_2_m3_hari'],
        $values['pad_steam_m3_hari'], $values['jetdying_sizing_la_m3_hari'], $values['kantin_mes_pos_security_m3_hari'], $values['boiler_m3_hari'],
        $values['air_mc_produksi_dan_lain_lain_m3_hari'], $values['flowmeter_output_ipal_m3_hari'],
        $_SESSION['UserName'], $row['id']
    ];
    $msg = 'Data berhasil diperbarui.';
} else {
    if (!$canAdd) { echo json_encode(['success'=>false,'message'=>'Anda tidak memiliki hak menambah data.']); exit; }
    $sql = "INSERT INTO dbo.air_bersih_limbah_harian (
                tanggal, flow_meter_intake_ipab_m3_hari, flow_meter_bak_dua_ke_ipal_m3_hari, flow_meter_bak3_ke_bak4_jasa_tirta_m3_hari,
                washing_1_m3_hari, washing_2_m3_hari, washing_3_m3_hari, perble_range_1_m3_hari, perble_range_2_m3_hari,
                pad_steam_m3_hari, jetdying_sizing_la_m3_hari, kantin_mes_pos_security_m3_hari, boiler_m3_hari,
                air_mc_produksi_dan_lain_lain_m3_hari, flowmeter_output_ipal_m3_hari, creatby
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $params = [
        $tanggal,
        $values['flow_meter_intake_ipab_m3_hari'], $values['flow_meter_bak_dua_ke_ipal_m3_hari'], $values['flow_meter_bak3_ke_bak4_jasa_tirta_m3_hari'],
        $values['washing_1_m3_hari'], $values['washing_2_m3_hari'], $values['washing_3_m3_hari'], $values['perble_range_1_m3_hari'], $values['perble_range_2_m3_hari'],
        $values['pad_steam_m3_hari'], $values['jetdying_sizing_la_m3_hari'], $values['kantin_mes_pos_security_m3_hari'], $values['boiler_m3_hari'],
        $values['air_mc_produksi_dan_lain_lain_m3_hari'], $values['flowmeter_output_ipal_m3_hari'],
        $_SESSION['UserName']
    ];
    $msg = 'Data berhasil disimpan.';
}

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) { echo json_encode(['success'=>false,'message'=>'Gagal menyimpan data.']); exit; }
if ($stmt) sqlsrv_free_stmt($stmt);
echo json_encode(['success'=>true,'message'=>$msg]);
