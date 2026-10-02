<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/sync_air_bersih_limbah.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
$menuId = 230; requireView($conn, $menuId);

$start = trim($_POST['start_date'] ?? date('Y-m-01'));
$end = trim($_POST['end_date'] ?? date('Y-m-d'));

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename=report_air_bersih_limbah_' . $start . '_sd_' . $end . '.xls');

$sql = "SELECT CAST(tanggal AS DATE) AS tanggal, *
        FROM dbo.air_bersih_limbah_harian
        WHERE CAST(tanggal AS DATE) BETWEEN ? AND ?
        ORDER BY CAST(tanggal AS DATE) ASC";
$stmt = sqlsrv_query($conn, $sql, [$start, $end]);
$rows = [];
if ($stmt) {
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $d = $r['tanggal'];
        $r['tanggal'] = ($d instanceof DateTime) ? $d->format('Y-m-d') : date('Y-m-d', strtotime((string)$d));
        $rows[] = $r;
    }
    sqlsrv_free_stmt($stmt);
}

$sourceCache = [];
foreach ($rows as &$syncRow) {
    $tanggalSync = abl_normalize_date($syncRow['tanggal'] ?? '');
    if ($tanggalSync === '') continue;
    if (!array_key_exists($tanggalSync, $sourceCache)) {
        $sourceCache[$tanggalSync] = abl_collect_source_values($conn, $tanggalSync);
        abl_sync_date($conn, $tanggalSync, $sourceCache[$tanggalSync]);
    }
    $syncRow = abl_overlay_row_with_source_values($syncRow, $sourceCache[$tanggalSync]);
}
unset($syncRow);

$keys = [
    'flow_meter_intake_ipab_m3_hari','flow_meter_bak_dua_ke_ipal_m3_hari','flow_meter_bak3_ke_bak4_jasa_tirta_m3_hari',
    'washing_1_m3_hari','washing_2_m3_hari','washing_3_m3_hari','perble_range_1_m3_hari','perble_range_2_m3_hari',
    'pad_steam_m3_hari','jetdying_sizing_la_m3_hari','kantin_mes_pos_security_m3_hari','boiler_m3_hari',
    'air_mc_produksi_dan_lain_lain_m3_hari','weaving_dan_lain_lain_m3_hari','buangan_air_produk_ke_ipal_m3_hari','flowmeter_output_ipal_m3_hari'
];
$tot = array_fill_keys($keys, 0);
foreach ($rows as $r) { foreach ($keys as $k) $tot[$k] += (float)($r[$k] ?? 0); }
$rowCount = count($rows);
$avg = []; foreach ($keys as $k) $avg[$k] = $rowCount > 0 ? ($tot[$k] / $rowCount) : 0;

$fmt = function ($v, $d = 2) { return is_numeric($v) ? number_format((float)$v, $d, '.', ',') : ''; };
$fmtDay = function ($ymd) { return $ymd ? date('j', strtotime($ymd)) : ''; };

$monthMap = [
    'January' => 'JANUARI', 'February' => 'FEBRUARI', 'March' => 'MARET', 'April' => 'APRIL',
    'May' => 'MEI', 'June' => 'JUNI', 'July' => 'JULI', 'August' => 'AGUSTUS',
    'September' => 'SEPTEMBER', 'October' => 'OKTOBER', 'November' => 'NOVEMBER', 'December' => 'DESEMBER'
];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));

echo "\xEF\xBB\xBF";
echo "<html><head><meta charset='UTF-8'><style>
.abl-report{border-collapse:collapse;border-spacing:0;font-size:11px;background:#fff;table-layout:fixed}
.abl-report th,.abl-report td{border:1px solid #000;text-align:center;vertical-align:middle;padding:2px 4px;white-space:nowrap}
.abl-report .title{background:#e8dccb;font-weight:700;font-size:30px}
.abl-report .month{background:#fff;font-weight:700;font-size:20px;text-align:left}
.abl-report .datecol{background:#d9d9c9;font-weight:700;min-width:70px}
.abl-report .flow{background:#a9bdd6;font-weight:700}
.abl-report .flow2{background:#e0d0d0;font-weight:700}
.abl-report .flow3{background:#a9bdd6;font-weight:700}
.abl-report .ipal{background:#fff200;font-weight:700}
.abl-report .out{background:#ffc000;font-weight:700}
.abl-report .data1{background:#a9bdd6}
.abl-report .data2{background:#e0d0d0}
.abl-report .data3{background:#a9bdd6}
.abl-report .data4{background:#fff200}
.abl-report .data5{background:#ffc000}
.abl-report .sum td{background:#ffc000 !important;font-weight:700}
</style></head><body>";

echo "<table class='abl-report'>";
echo "<colgroup>";
echo "<col style='width:55px'>";
echo "<col style='width:120px'>";
echo "<col style='width:120px'>";
echo "<col style='width:130px'>";
echo "<col style='width:95px'>";
echo "<col style='width:95px'>";
echo "<col style='width:95px'>";
echo "<col style='width:95px'>";
echo "<col style='width:95px'>";
echo "<col style='width:95px'>";
echo "<col style='width:105px'>";
echo "<col style='width:120px'>";
echo "<col style='width:95px'>";
echo "<col style='width:125px'>";
echo "<col style='width:105px'>";
echo "<col style='width:150px'>";
echo "<col style='width:120px'>";
echo "</colgroup>";
echo "<thead>";
echo "<tr><th class='month' colspan='17'>BULAN : " . htmlspecialchars($monthLabel) . "</th></tr>";
echo "<tr><th class='title' colspan='17'>PEMAKAIAN AIR BERSIH DARI IPAB</th></tr>";
echo "<tr>";
echo "<th class='datecol' rowspan='2'>TGL</th>";
echo "<th class='flow'>FLOW METER<br>DARI WATER<br>INTAKE KE<br>IPAB</th>";
echo "<th class='flow'>FLOW METER<br>BAK DUA KE<br>IPAL</th>";
echo "<th class='flow'>FLOW METER<br>DARI BAK 3<br>KE BAK 4<br>JASA TIRTA</th>";
echo "<th class='flow2'>WASHING 1</th>";
echo "<th class='flow2'>WASHING 2</th>";
echo "<th class='flow2'>WASHING 3</th>";
echo "<th class='flow2'>PERBLE<br>RANGE 1</th>";
echo "<th class='flow2'>PERBLE<br>RANGE 2</th>";
echo "<th class='flow2'>PAD<br>STEAM</th>";
echo "<th class='flow3'>JETDYING,<br>SIZING,LA</th>";
echo "<th class='flow3'>KANTIN,MES<br>DAN POS<br>SECURITY</th>";
echo "<th class='flow3'>BOILER</th>";
echo "<th class='flow3'>AIR MC<br>PRODUKSI,<br>Dan Lain lain</th>";
echo "<th class='flow3'>WEAVING<br>DAN LAIN<br>LAIN</th>";
echo "<th class='ipal'>TOTAL PEMAKAIAN AIR<br>PRODUKSI DF</th>";
echo "<th class='out'>FLOWMETER<br>OUTPUT</th>";
echo "</tr>";
echo "<tr>";
for ($i = 0; $i < 16; $i++) {
    echo "<th>m&#179;/hari</th>";
}
echo "</tr>";
echo "</thead><tbody>";

if (empty($rows)) {
    echo "<tr><td colspan='17'>Tidak ada data pada rentang tanggal ini.</td></tr>";
} else {
    foreach ($rows as $r) {
        echo "<tr>";
        echo "<td class='datecol'>" . htmlspecialchars($fmtDay($r['tanggal'])) . "</td>";
        echo "<td class='data1'>" . htmlspecialchars($fmt($r['flow_meter_intake_ipab_m3_hari'] ?? 0)) . "</td>";
        echo "<td class='data1'>" . htmlspecialchars($fmt($r['flow_meter_bak_dua_ke_ipal_m3_hari'] ?? 0)) . "</td>";
        echo "<td class='data1'>" . htmlspecialchars($fmt($r['flow_meter_bak3_ke_bak4_jasa_tirta_m3_hari'] ?? 0)) . "</td>";
        echo "<td class='data2'>" . htmlspecialchars($fmt($r['washing_1_m3_hari'] ?? 0)) . "</td>";
        echo "<td class='data2'>" . htmlspecialchars($fmt($r['washing_2_m3_hari'] ?? 0)) . "</td>";
        echo "<td class='data2'>" . htmlspecialchars($fmt($r['washing_3_m3_hari'] ?? 0)) . "</td>";
        echo "<td class='data2'>" . htmlspecialchars($fmt($r['perble_range_1_m3_hari'] ?? 0)) . "</td>";
        echo "<td class='data2'>" . htmlspecialchars($fmt($r['perble_range_2_m3_hari'] ?? 0)) . "</td>";
        echo "<td class='data2'>" . htmlspecialchars($fmt($r['pad_steam_m3_hari'] ?? 0)) . "</td>";
        echo "<td class='data3'>" . htmlspecialchars($fmt($r['jetdying_sizing_la_m3_hari'] ?? 0)) . "</td>";
        echo "<td class='data3'>" . htmlspecialchars($fmt($r['kantin_mes_pos_security_m3_hari'] ?? 0)) . "</td>";
        echo "<td class='data3'>" . htmlspecialchars($fmt($r['boiler_m3_hari'] ?? 0)) . "</td>";
        echo "<td class='data3'>" . htmlspecialchars($fmt($r['air_mc_produksi_dan_lain_lain_m3_hari'] ?? 0)) . "</td>";
        echo "<td class='data3'>" . htmlspecialchars($fmt($r['weaving_dan_lain_lain_m3_hari'] ?? 0)) . "</td>";
        echo "<td class='data4'>" . htmlspecialchars($fmt($r['buangan_air_produk_ke_ipal_m3_hari'] ?? 0)) . "</td>";
        echo "<td class='data5'>" . htmlspecialchars($fmt($r['flowmeter_output_ipal_m3_hari'] ?? 0)) . "</td>";
        echo "</tr>";
    }
    echo "<tr class='sum'><td>Total</td>";
    foreach ($keys as $k) {
        echo "<td>" . htmlspecialchars($fmt($tot[$k])) . "</td>";
    }
    echo "</tr>";
    echo "<tr class='sum'><td>RATA-RATA</td>";
    foreach ($keys as $k) {
        echo "<td>" . htmlspecialchars($fmt($avg[$k])) . "</td>";
    }
    echo "</tr>";
}

echo "</tbody></table></body></html>";
