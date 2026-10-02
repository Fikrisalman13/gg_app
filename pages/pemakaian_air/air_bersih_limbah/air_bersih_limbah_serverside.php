<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
include(__DIR__ . '/sync_air_bersih_limbah.php');
header('Content-Type: application/json');

$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) {
    echo json_encode(['draw' => intval($_POST['draw'] ?? 1), 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Anda tidak memiliki hak melihat data.']);
    exit;
}

$draw = intval($_POST['draw'] ?? 1);
$start = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);
$search = trim($_POST['search']['value'] ?? '');
$startDate = trim($_POST['start_date'] ?? '');
$endDate = trim($_POST['end_date'] ?? '');

$where = "WHERE 1=1";
$params = [];
if ($startDate !== '') { $where .= " AND CAST(x.tanggal AS DATE) >= ?"; $params[] = $startDate; }
if ($endDate !== '') { $where .= " AND CAST(x.tanggal AS DATE) <= ?"; $params[] = $endDate; }
if ($search !== '') {
    $where .= " AND (
        CONVERT(VARCHAR(10), x.tanggal, 120) LIKE ?
        OR CONVERT(VARCHAR(50), x.flow_meter_intake_ipab_m3_hari) LIKE ?
        OR CONVERT(VARCHAR(50), x.flowmeter_output_ipal_m3_hari) LIKE ?
        OR COALESCE(x.updateby, x.creatby, '') LIKE ?
    )";
    $sp = "%{$search}%";
    $params = array_merge($params, [$sp, $sp, $sp, $sp]);
}

$baseFrom = "FROM dbo.air_bersih_limbah_harian x";

$countAllStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total " . $baseFrom);
$totalRecords = 0;
if ($countAllStmt && ($r = sqlsrv_fetch_array($countAllStmt, SQLSRV_FETCH_ASSOC))) $totalRecords = (int)$r['total'];
if ($countAllStmt) sqlsrv_free_stmt($countAllStmt);

$countFilteredStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total " . $baseFrom . " " . $where, $params);
$totalFiltered = 0;
if ($countFilteredStmt && ($r = sqlsrv_fetch_array($countFilteredStmt, SQLSRV_FETCH_ASSOC))) $totalFiltered = (int)$r['total'];
if ($countFilteredStmt) sqlsrv_free_stmt($countFilteredStmt);

$sql = "SELECT x.id, x.tanggal,
               x.flow_meter_intake_ipab_m3_hari,
               x.flow_meter_bak_dua_ke_ipal_m3_hari,
               x.flow_meter_bak3_ke_bak4_jasa_tirta_m3_hari,
               x.washing_1_m3_hari,
               x.washing_2_m3_hari,
               x.washing_3_m3_hari,
               x.perble_range_1_m3_hari,
               x.perble_range_2_m3_hari,
               x.pad_steam_m3_hari,
               x.jetdying_sizing_la_m3_hari,
               x.kantin_mes_pos_security_m3_hari,
               x.boiler_m3_hari,
               x.air_mc_produksi_dan_lain_lain_m3_hari,
               x.buangan_air_produk_ke_ipal_m3_hari,
               x.flowmeter_output_ipal_m3_hari,
               COALESCE(x.updateby, x.creatby, '') AS created_by
        " . $baseFrom . " " . $where . "
        ORDER BY CAST(x.tanggal AS DATE) DESC, x.id DESC
        OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$dataParams = $params; $dataParams[] = $start; $dataParams[] = $length;
$stmt = sqlsrv_query($conn, $sql, $dataParams);
if ($stmt === false) {
    echo json_encode(['draw'=>$draw,'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[],'error'=>'Gagal mengambil data.']);
    exit;
}

$data = [];
$sourceCache = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tgl = $row['tanggal'];
    if ($tgl instanceof DateTime) { $tanggalRaw = $tgl->format('Y-m-d'); $tanggalFmt = $tgl->format('d-m-Y'); }
    else { $tanggalRaw = (string)$tgl; $tanggalFmt = $tanggalRaw !== '' ? date('d-m-Y', strtotime($tanggalRaw)) : ''; }

    if ($tanggalRaw !== '') {
        if (!array_key_exists($tanggalRaw, $sourceCache)) {
            $sourceCache[$tanggalRaw] = abl_collect_source_values($conn, $tanggalRaw);
            abl_sync_date($conn, $tanggalRaw, $sourceCache[$tanggalRaw]);
        }
        $row = abl_overlay_row_with_source_values($row, $sourceCache[$tanggalRaw]);
    }

    $data[] = [
        'id' => $row['id'] ?? null,
        'tanggal' => $tanggalRaw,
        'tanggal_formatted' => $tanggalFmt,
        'flow_meter_intake_ipab_m3_hari' => $row['flow_meter_intake_ipab_m3_hari'] ?? 0,
        'flow_meter_bak_dua_ke_ipal_m3_hari' => $row['flow_meter_bak_dua_ke_ipal_m3_hari'] ?? 0,
        'flow_meter_bak3_ke_bak4_jasa_tirta_m3_hari' => $row['flow_meter_bak3_ke_bak4_jasa_tirta_m3_hari'] ?? 0,
        'washing_1_m3_hari' => $row['washing_1_m3_hari'] ?? 0,
        'washing_2_m3_hari' => $row['washing_2_m3_hari'] ?? 0,
        'washing_3_m3_hari' => $row['washing_3_m3_hari'] ?? 0,
        'perble_range_1_m3_hari' => $row['perble_range_1_m3_hari'] ?? 0,
        'perble_range_2_m3_hari' => $row['perble_range_2_m3_hari'] ?? 0,
        'pad_steam_m3_hari' => $row['pad_steam_m3_hari'] ?? 0,
        'jetdying_sizing_la_m3_hari' => $row['jetdying_sizing_la_m3_hari'] ?? 0,
        'kantin_mes_pos_security_m3_hari' => $row['kantin_mes_pos_security_m3_hari'] ?? 0,
        'boiler_m3_hari' => $row['boiler_m3_hari'] ?? 0,
        'air_mc_produksi_dan_lain_lain_m3_hari' => $row['air_mc_produksi_dan_lain_lain_m3_hari'] ?? 0,
        'buangan_air_produk_ke_ipal_m3_hari' => $row['buangan_air_produk_ke_ipal_m3_hari'] ?? 0,
        'flowmeter_output_ipal_m3_hari' => $row['flowmeter_output_ipal_m3_hari'] ?? 0,
        'created_by' => $row['created_by'] ?? ''
    ];
}
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['draw'=>$draw,'recordsTotal'=>$totalRecords,'recordsFiltered'=>$totalFiltered,'data'=>$data]);
