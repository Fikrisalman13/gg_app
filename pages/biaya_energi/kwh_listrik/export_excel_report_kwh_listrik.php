<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
require_once __DIR__ . '/kwh_listrik_common.php';

$start = kwhl_normalize_date($_POST['start_date'] ?? date('Y-m-01'));
$end = kwhl_normalize_date($_POST['end_date'] ?? date('Y-m-d'));
if ($start === '') $start = date('Y-m-01');
if ($end === '') $end = date('Y-m-d');

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="report_kwh_listrik_' . date('Ymd_His') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

$rows = [];
if (kwhl_table_exists($conn)) {
    $tableName = kwhl_table_full_name($conn);
    if ($tableName !== '') {
        $sql = "SELECT * FROM {$tableName}
                WHERE CAST(tanggal AS DATE) BETWEEN ? AND ?
                ORDER BY CAST(tanggal AS DATE) ASC, id ASC";
        $stmt = sqlsrv_query($conn, $sql, [$start, $end]);
        if ($stmt) {
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $rows[] = kwhl_row_from_db($r);
            }
            sqlsrv_free_stmt($stmt);
        }
    }
}

$machines = kwhl_machine_defs();
$totAmp = [];
$totKwh = [];
$totBiaya = [];
foreach ($machines as $code => $m) {
    $totAmp[$m['amp_key']] = 0.0;
    $totKwh['kwh_' . $code] = 0.0;
    $totBiaya['biaya_' . $code] = 0.0;
}
$totAllKwh = 0.0;
$totAllBiaya = 0.0;
$tot21TKwh = 0.0;
$tot21TBiaya = 0.0;
foreach ($rows as $r) {
    foreach ($machines as $code => $m) {
        $totAmp[$m['amp_key']] += (float)($r[$m['amp_key']] ?? 0);
        $totKwh['kwh_' . $code] += (float)($r['kwh_' . $code] ?? 0);
        $totBiaya['biaya_' . $code] += (float)($r['biaya_' . $code] ?? 0);
    }
    $totAllKwh += (float)($r['total_kwh'] ?? 0);
    $totAllBiaya += (float)($r['total_biaya'] ?? 0);
    $tot21TKwh += (float)($r['kwh_21ton_actom'] ?? 0);
    $tot21TBiaya += (float)($r['biaya_21ton_actom'] ?? 0);
}

$count = count($rows);
$avgAmp = [];
$avgKwh = [];
$avgBiaya = [];
foreach ($machines as $code => $m) {
    $avgAmp[$m['amp_key']] = $count > 0 ? ($totAmp[$m['amp_key']] / $count) : 0;
    $avgKwh['kwh_' . $code] = $count > 0 ? ($totKwh['kwh_' . $code] / $count) : 0;
    $avgBiaya['biaya_' . $code] = $count > 0 ? ($totBiaya['biaya_' . $code] / $count) : 0;
}
$avgAllKwh = $count > 0 ? ($totAllKwh / $count) : 0;
$avgAllBiaya = $count > 0 ? ($totAllBiaya / $count) : 0;
$avg21TKwh = $count > 0 ? ($tot21TKwh / $count) : 0;
$avg21TBiaya = $count > 0 ? ($tot21TBiaya / $count) : 0;

$monthMap = [
    'January' => 'JANUARI', 'February' => 'FEBRUARI', 'March' => 'MARET', 'April' => 'APRIL',
    'May' => 'MEI', 'June' => 'JUNI', 'July' => 'JULI', 'August' => 'AGUSTUS',
    'September' => 'SEPTEMBER', 'October' => 'OKTOBER', 'November' => 'NOVEMBER', 'December' => 'DESEMBER',
];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));

echo "\xEF\xBB\xBF";
echo "<html><head><meta charset='UTF-8'><style>
table{border-collapse:collapse;font-size:11px;background:#fff}
th,td{border:1px solid #000;text-align:center;vertical-align:middle;padding:2px 4px;white-space:nowrap}
.title{font-weight:700;font-size:28px}
.month{font-weight:700;font-size:14px;text-align:left}
.datecol{background:#d9d9d9;font-weight:700}
.grp-amp{background:#d9d9d9;font-weight:700}
.grp-kwh{background:#ffc000;font-weight:700}
.grp-biaya{background:#efefef;font-weight:700}
.hdr{background:#fff200;font-weight:700}
.amp{background:#f5f5f5}
.kwh{background:#d9e2f3}
.biaya{background:#ece6db}
.sum td{background:#ffc000 !important;font-weight:700}
</style></head><body>";

$colspan = 1 + (count($machines) * 3) + 2 + 2;
echo "<table>";
echo "<tr><th class='month' colspan='{$colspan}'>BULAN : " . htmlspecialchars($monthLabel) . "</th></tr>";
echo "<tr><th class='title' colspan='{$colspan}'>PENCATATAN AMPERE DAN KWH METER</th></tr>";
echo "<tr>";
echo "<th class='datecol' rowspan='3'>TANGGAL</th>";
echo "<th class='grp-amp' colspan='" . count($machines) . "'>AMPERE</th>";
echo "<th class='grp-kwh' colspan='" . (count($machines) + 1) . "'>KWH</th>";
echo "<th class='grp-biaya' colspan='" . (count($machines) + 1) . "'>TOTAL BIAYA</th>";
echo "<th class='grp-kwh' rowspan='2'>TOTAL KWH</th>";
echo "<th class='grp-biaya' rowspan='2'>TOTAL BIAYA</th>";
echo "</tr>";

echo "<tr>";
foreach ($machines as $m) echo "<th class='hdr'>" . htmlspecialchars($m['label']) . "</th>";
foreach ($machines as $m) echo "<th class='hdr'>" . htmlspecialchars($m['label']) . "</th>";
echo "<th class='hdr'>21TON ACTOM</th>";
foreach ($machines as $m) echo "<th class='hdr'>" . htmlspecialchars($m['label']) . "</th>";
echo "<th class='hdr'>21TON ACTOM</th>";
echo "</tr>";

echo "<tr>";
for ($i = 0; $i < count($machines); $i++) echo "<th>A</th>";
for ($i = 0; $i < count($machines); $i++) echo "<th>kWh</th>";
echo "<th>kWh</th>";
for ($i = 0; $i < count($machines); $i++) echo "<th>Rp</th>";
echo "<th>Rp</th><th>kWh</th><th>Rp</th>";
echo "</tr>";

if (empty($rows)) {
    echo "<tr><td colspan='{$colspan}'>Tidak ada data pada rentang tanggal ini.</td></tr>";
} else {
    foreach ($rows as $r) {
        $rowTotalKwh = (float)($r['total_kwh'] ?? 0) + (float)($r['kwh_21ton_actom'] ?? 0);
        $rowTotalBiaya = (float)($r['total_biaya'] ?? 0) + (float)($r['biaya_21ton_actom'] ?? 0);
        echo "<tr>";
        echo "<td class='datecol'>" . htmlspecialchars(kwhl_format_day($r['tanggal'])) . "</td>";
        foreach ($machines as $m) echo "<td class='amp'>" . htmlspecialchars(kwhl_format_num($r[$m['amp_key']] ?? 0, 2)) . "</td>";
        foreach ($machines as $code => $m) echo "<td class='kwh'>" . htmlspecialchars(kwhl_format_num($r['kwh_' . $code] ?? 0, 3)) . "</td>";
        echo "<td class='kwh'>" . htmlspecialchars(kwhl_format_num($r['kwh_21ton_actom'] ?? 0, 3)) . "</td>";
        foreach ($machines as $code => $m) echo "<td class='biaya'>" . htmlspecialchars(kwhl_format_num($r['biaya_' . $code] ?? 0, 0)) . "</td>";
        echo "<td class='biaya'>" . htmlspecialchars(kwhl_format_num($r['biaya_21ton_actom'] ?? 0, 0)) . "</td>";
        echo "<td class='kwh'>" . htmlspecialchars(kwhl_format_num($rowTotalKwh, 3)) . "</td>";
        echo "<td class='biaya'>" . htmlspecialchars(kwhl_format_num($rowTotalBiaya, 0)) . "</td>";
        echo "</tr>";
    }

    echo "<tr class='sum'><td>TOTAL</td>";
    foreach ($machines as $m) echo "<td>" . htmlspecialchars(kwhl_format_num($totAmp[$m['amp_key']], 2)) . "</td>";
    foreach ($machines as $code => $m) echo "<td>" . htmlspecialchars(kwhl_format_num($totKwh['kwh_' . $code], 3)) . "</td>";
    echo "<td>" . htmlspecialchars(kwhl_format_num($tot21TKwh, 3)) . "</td>";
    foreach ($machines as $code => $m) echo "<td>" . htmlspecialchars(kwhl_format_num($totBiaya['biaya_' . $code], 0)) . "</td>";
    echo "<td>" . htmlspecialchars(kwhl_format_num($tot21TBiaya, 0)) . "</td>";
    echo "<td>" . htmlspecialchars(kwhl_format_num($totAllKwh + $tot21TKwh, 3)) . "</td>";
    echo "<td>" . htmlspecialchars(kwhl_format_num($totAllBiaya + $tot21TBiaya, 0)) . "</td></tr>";

    echo "<tr class='sum'><td>RATA-RATA</td>";
    foreach ($machines as $m) echo "<td>" . htmlspecialchars(kwhl_format_num($avgAmp[$m['amp_key']], 2)) . "</td>";
    foreach ($machines as $code => $m) echo "<td>" . htmlspecialchars(kwhl_format_num($avgKwh['kwh_' . $code], 3)) . "</td>";
    echo "<td>" . htmlspecialchars(kwhl_format_num($avg21TKwh, 3)) . "</td>";
    foreach ($machines as $code => $m) echo "<td>" . htmlspecialchars(kwhl_format_num($avgBiaya['biaya_' . $code], 0)) . "</td>";
    echo "<td>" . htmlspecialchars(kwhl_format_num($avg21TBiaya, 0)) . "</td>";
    echo "<td>" . htmlspecialchars(kwhl_format_num($avgAllKwh + $avg21TKwh, 3)) . "</td>";
    echo "<td>" . htmlspecialchars(kwhl_format_num($avgAllBiaya + $avg21TBiaya, 0)) . "</td></tr>";
}

echo "</table></body></html>";
