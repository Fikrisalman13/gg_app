<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
$menuId = 230; requireView($conn, $menuId);

$start = trim($_POST['start_date'] ?? date('Y-m-01'));
$end = trim($_POST['end_date'] ?? date('Y-m-d'));

$tanks = [];
$stTank = sqlsrv_query($conn, "SELECT kode,nama,urutan FROM dbo.lpg_skid_tank_master WHERE is_active=1 ORDER BY urutan,kode");
if ($stTank) {
    $idx = 1;
    while ($t = sqlsrv_fetch_array($stTank, SQLSRV_FETCH_ASSOC)) {
        $urutan = isset($t['urutan']) ? (int)$t['urutan'] : $idx;
        if ($urutan <= 0) $urutan = $idx;
        $t['urutan'] = $urutan;
        $t['display_label'] = 'Pencatatan Pemakaian ' . $urutan;
        $tanks[] = $t;
        $idx++;
    }
    sqlsrv_free_stmt($stTank);
}

$rows = [];
$sql = "SELECT CAST(h.tanggal AS DATE) AS tanggal,h.tank_kode,m.nama AS tank_nama,m.urutan,h.pemakaian_kg,h.harga_rp,h.biaya_rp,h.ket
        FROM dbo.lpg_skid_tank_harian h
        INNER JOIN dbo.lpg_skid_tank_master m ON m.kode=h.tank_kode
        WHERE CAST(h.tanggal AS DATE) BETWEEN ? AND ?
        ORDER BY CAST(h.tanggal AS DATE) ASC, m.urutan ASC";
$stmt = sqlsrv_query($conn, $sql, [$start, $end]);
if ($stmt) {
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $dateObj = $r['tanggal'] ?? null;
        $dateKey = ($dateObj instanceof DateTime) ? $dateObj->format('Y-m-d') : date('Y-m-d', strtotime((string)$dateObj));
        $rows[] = [
            'tanggal' => $dateKey,
            'tank_kode' => (string)($r['tank_kode'] ?? ''),
            'tank_nama' => (string)($r['tank_nama'] ?? ''),
            'urutan' => (int)($r['urutan'] ?? 0),
            'pemakaian_kg' => (float)($r['pemakaian_kg'] ?? 0),
            'harga_rp' => (float)($r['harga_rp'] ?? 0),
            'biaya_rp' => (float)($r['biaya_rp'] ?? 0),
            'ket' => (string)($r['ket'] ?? ''),
        ];
    }
    sqlsrv_free_stmt($stmt);
}

$panelByUrutan = [];
foreach ($tanks as $t) {
    $u = (int)$t['urutan'];
    if ($u < 1 || $u > 4) continue;
    $panelByUrutan[$u] = $t;
}
for ($i = 1; $i <= 4; $i++) {
    if (!isset($panelByUrutan[$i])) {
        $panelByUrutan[$i] = ['kode' => '', 'nama' => '-', 'urutan' => $i, 'display_label' => 'Pencatatan Pemakaian ' . $i];
    }
}
ksort($panelByUrutan);

$map = [];
$dates = [];
$dailyTotal = [];
$totPem = [1=>0,2=>0,3=>0,4=>0];
$totBiaya = [1=>0,2=>0,3=>0,4=>0];
$countPerPanel = [1=>0,2=>0,3=>0,4=>0];

foreach ($rows as $r) {
    $u = (int)$r['urutan'];
    if ($u < 1 || $u > 4) continue;
    $d = $r['tanggal'];
    if (!isset($map[$d])) $map[$d] = [];
    $map[$d][$u] = $r;
    $dates[$d] = true;
    if (!isset($dailyTotal[$d])) $dailyTotal[$d] = 0;
    $dailyTotal[$d] += $r['biaya_rp'];

    $totPem[$u] += $r['pemakaian_kg'];
    $totBiaya[$u] += $r['biaya_rp'];
    $countPerPanel[$u]++;
}

$dates = array_keys($dates);
sort($dates);

$avgPem = [];
$avgBiaya = [];
for ($i = 1; $i <= 4; $i++) {
    $avgPem[$i] = $countPerPanel[$i] > 0 ? ($totPem[$i] / $countPerPanel[$i]) : 0;
    $avgBiaya[$i] = $countPerPanel[$i] > 0 ? ($totBiaya[$i] / $countPerPanel[$i]) : 0;
}
$grandTotal = array_sum($dailyTotal);
$grandAvg = count($dates) > 0 ? ($grandTotal / count($dates)) : 0;

$fmtNum = function ($val, $dec = 2) {
    if (!is_numeric($val)) return '';
    return number_format((float)$val, $dec, '.', ',');
};
$fmtDay = function ($ymd) { return $ymd ? date('j', strtotime($ymd)) : ''; };

$monthMap = [
    'January' => 'JANUARI', 'February' => 'FEBRUARI', 'March' => 'MARET', 'April' => 'APRIL',
    'May' => 'MEI', 'June' => 'JUNI', 'July' => 'JULI', 'August' => 'AGUSTUS',
    'September' => 'SEPTEMBER', 'October' => 'OKTOBER', 'November' => 'NOVEMBER', 'December' => 'DESEMBER'
];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename=report_lpg_skid_tank_' . $start . '_sd_' . $end . '.xls');

echo "<html><head><meta charset='UTF-8'><style>
table{border-collapse:collapse;border-spacing:0;font-size:11px;min-width:2300px;background:#fff}
th,td{border:1px solid #000;text-align:center;vertical-align:middle;padding:2px 4px;white-space:nowrap}
.title{background:#f4b400;font-weight:700;font-size:22px;line-height:1.1;color:#000}
.month{background:#f4b400;font-weight:700;font-size:18px;text-align:left}
.subname{background:#f4b400;font-weight:700;font-size:14px}
.head{background:#fff200;font-weight:700}
.data-pakai{background:#ffc000}
.data-harga{background:#fff200}
.data-biaya{background:#d9d9d9}
.data-ket{background:#d9d9d9}
.total-col{background:#fff200;font-weight:700}
.sum{background:#dce6f1;font-weight:700}
.gap{background:#efefef;border:none;min-width:16px}
</style></head><body>";

echo "<table>";
echo "<thead>";
echo "<tr>";
echo "<th class='title' colspan='5'>" . htmlspecialchars($panelByUrutan[1]['display_label']) . "</th>";
echo "<th class='gap'></th>";
echo "<th class='title' colspan='5'>" . htmlspecialchars($panelByUrutan[2]['display_label']) . "</th>";
echo "<th class='gap'></th>";
echo "<th class='title' colspan='5'>" . htmlspecialchars($panelByUrutan[3]['display_label']) . "</th>";
echo "<th class='gap'></th>";
echo "<th class='title' colspan='5'>" . htmlspecialchars($panelByUrutan[4]['display_label']) . "</th>";
echo "<th class='gap'></th>";
echo "<th class='title' colspan='1'>TOTAL</th>";
echo "</tr>";

echo "<tr>";
echo "<th class='subname' colspan='5'>" . htmlspecialchars($panelByUrutan[1]['nama']) . "</th>";
echo "<th class='gap'></th>";
echo "<th class='subname' colspan='5'>" . htmlspecialchars($panelByUrutan[2]['nama']) . "</th>";
echo "<th class='gap'></th>";
echo "<th class='subname' colspan='5'>" . htmlspecialchars($panelByUrutan[3]['nama']) . "</th>";
echo "<th class='gap'></th>";
echo "<th class='subname' colspan='5'>" . htmlspecialchars($panelByUrutan[4]['nama']) . "</th>";
echo "<th class='gap'></th><th class='subname'></th>";
echo "</tr>";

echo "<tr>";
for($i=0;$i<4;$i++){
  echo "<th class='month' colspan='5'>Bulan: " . htmlspecialchars($monthLabel) . "</th>";
  echo "<th class='gap'></th>";
}
echo "<th class='month'></th>";
echo "</tr>";

echo "<tr>";
for($i=0;$i<4;$i++){
  echo "<th class='head'>TGL</th><th class='head'>Pemakaian<br>(KG)</th><th class='head'>Harga<br>(Rp)</th><th class='head'>Biaya<br>(Rp/hari)</th><th class='head'>KET</th><th class='gap'></th>";
}
echo "<th class='head'>Biaya<br>(Rp/hari)</th>";
echo "</tr>";
echo "</thead><tbody>";

if (empty($dates)) {
    echo "<tr><td colspan='29'>Tidak ada data pada rentang tanggal ini.</td></tr>";
} else {
    foreach ($dates as $d) {
        $r1 = $map[$d][1] ?? null; $r2 = $map[$d][2] ?? null; $r3 = $map[$d][3] ?? null; $r4 = $map[$d][4] ?? null;
        echo "<tr>";
        echo "<td>" . htmlspecialchars($fmtDay($d)) . "</td><td class='data-pakai'>" . htmlspecialchars($r1 ? $fmtNum($r1['pemakaian_kg']) : '') . "</td><td class='data-harga'>" . htmlspecialchars($r1 ? $fmtNum($r1['harga_rp']) : '') . "</td><td class='data-biaya'>" . htmlspecialchars($r1 ? $fmtNum($r1['biaya_rp']) : '') . "</td><td class='data-ket'>" . htmlspecialchars($r1['ket'] ?? '') . "</td><td class='gap'></td>";
        echo "<td>" . htmlspecialchars($fmtDay($d)) . "</td><td class='data-pakai'>" . htmlspecialchars($r2 ? $fmtNum($r2['pemakaian_kg']) : '') . "</td><td class='data-harga'>" . htmlspecialchars($r2 ? $fmtNum($r2['harga_rp']) : '') . "</td><td class='data-biaya'>" . htmlspecialchars($r2 ? $fmtNum($r2['biaya_rp']) : '') . "</td><td class='data-ket'>" . htmlspecialchars($r2['ket'] ?? '') . "</td><td class='gap'></td>";
        echo "<td>" . htmlspecialchars($fmtDay($d)) . "</td><td class='data-pakai'>" . htmlspecialchars($r3 ? $fmtNum($r3['pemakaian_kg']) : '') . "</td><td class='data-harga'>" . htmlspecialchars($r3 ? $fmtNum($r3['harga_rp']) : '') . "</td><td class='data-biaya'>" . htmlspecialchars($r3 ? $fmtNum($r3['biaya_rp']) : '') . "</td><td class='data-ket'>" . htmlspecialchars($r3['ket'] ?? '') . "</td><td class='gap'></td>";
        echo "<td>" . htmlspecialchars($fmtDay($d)) . "</td><td class='data-pakai'>" . htmlspecialchars($r4 ? $fmtNum($r4['pemakaian_kg']) : '') . "</td><td class='data-harga'>" . htmlspecialchars($r4 ? $fmtNum($r4['harga_rp']) : '') . "</td><td class='data-biaya'>" . htmlspecialchars($r4 ? $fmtNum($r4['biaya_rp']) : '') . "</td><td class='data-ket'>" . htmlspecialchars($r4['ket'] ?? '') . "</td><td class='gap'></td>";
        echo "<td class='total-col'>" . htmlspecialchars($fmtNum($dailyTotal[$d] ?? 0)) . "</td>";
        echo "</tr>";
    }

    echo "<tr class='sum'>";
    echo "<td>TOTAL</td><td>" . htmlspecialchars($fmtNum($totPem[1])) . "</td><td></td><td>" . htmlspecialchars($fmtNum($totBiaya[1])) . "</td><td></td><td class='gap'></td>";
    echo "<td>TOTAL</td><td>" . htmlspecialchars($fmtNum($totPem[2])) . "</td><td></td><td>" . htmlspecialchars($fmtNum($totBiaya[2])) . "</td><td></td><td class='gap'></td>";
    echo "<td>TOTAL</td><td>" . htmlspecialchars($fmtNum($totPem[3])) . "</td><td></td><td>" . htmlspecialchars($fmtNum($totBiaya[3])) . "</td><td></td><td class='gap'></td>";
    echo "<td>TOTAL</td><td>" . htmlspecialchars($fmtNum($totPem[4])) . "</td><td></td><td>" . htmlspecialchars($fmtNum($totBiaya[4])) . "</td><td></td><td class='gap'></td>";
    echo "<td>" . htmlspecialchars($fmtNum($grandTotal)) . "</td>";
    echo "</tr>";

    echo "<tr class='sum'>";
    echo "<td>RATA-RATA</td><td>" . htmlspecialchars($fmtNum($avgPem[1])) . "</td><td>INCLUDE</td><td>" . htmlspecialchars($fmtNum($avgBiaya[1])) . "</td><td></td><td class='gap'></td>";
    echo "<td>RATA-RATA</td><td>" . htmlspecialchars($fmtNum($avgPem[2])) . "</td><td>INCLUDE</td><td>" . htmlspecialchars($fmtNum($avgBiaya[2])) . "</td><td></td><td class='gap'></td>";
    echo "<td>RATA-RATA</td><td>" . htmlspecialchars($fmtNum($avgPem[3])) . "</td><td>INCLUDE</td><td>" . htmlspecialchars($fmtNum($avgBiaya[3])) . "</td><td></td><td class='gap'></td>";
    echo "<td>RATA-RATA</td><td>" . htmlspecialchars($fmtNum($avgPem[4])) . "</td><td>INCLUDE</td><td>" . htmlspecialchars($fmtNum($avgBiaya[4])) . "</td><td></td><td class='gap'></td>";
    echo "<td>" . htmlspecialchars($fmtNum($grandAvg)) . "</td>";
    echo "</tr>";
}

echo "</tbody></table></body></html>";