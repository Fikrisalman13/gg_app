<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';

$start = $_POST['start_date'] ?? date('Y-m-01');
$end = $_POST['end_date'] ?? date('Y-m-d');

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Laporan_Washing3_' . date('Ymd_His') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

$sql = "SELECT Tanggal, WaterFlow, OperasionalMesin, TotalPemakaian, Keterangan
        FROM dbo.washing3_air
        WHERE Tanggal BETWEEN ? AND ?
          AND (
                WaterFlow IS NOT NULL
                OR OperasionalMesin IS NOT NULL
                OR TotalPemakaian IS NOT NULL
                OR (Keterangan IS NOT NULL AND LTRIM(RTRIM(Keterangan)) <> '')
              )
        ORDER BY Tanggal ASC, Id ASC";
$stmt = sqlsrv_query($conn, $sql, [$start, $end]);
if ($stmt === false) {
    echo "<b>SQL error</b>: " . htmlspecialchars(print_r(sqlsrv_errors(), true));
    exit;
}

$rows = [];
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $rows[] = $r;
if ($stmt) sqlsrv_free_stmt($stmt);

$fmtDate = function($val) {
    if ($val instanceof DateTime) return $val->format('d-M-y');
    return (string)$val;
};
$fmtNum = function($val, $decimals = 2) {
    if ($val === null || $val === '') return '';
    if (!is_numeric($val)) return (string)$val;
    return number_format((float)$val, $decimals, '.', '');
};

$sumOperasional = 0.0;
$sumTotal = 0.0;
$sumWater = 0.0;
$cntWater = 0;
$cntOperasional = 0;
$cntTotal = 0;
foreach ($rows as $r) {
    if (is_numeric($r['WaterFlow'])) { $sumWater += (float)$r['WaterFlow']; $cntWater++; }
    if (is_numeric($r['OperasionalMesin'])) { $sumOperasional += (float)$r['OperasionalMesin']; $cntOperasional++; }
    if (is_numeric($r['TotalPemakaian'])) { $sumTotal += (float)$r['TotalPemakaian']; $cntTotal++; }
}
$avgWater = $cntWater ? $sumWater / $cntWater : null;
$avgOperasional = $cntOperasional ? $sumOperasional / $cntOperasional : null;
$avgTotal = $cntTotal ? $sumTotal / $cntTotal : null;

$monthMap = [
    'January' => 'JANUARI', 'February' => 'FEBRUARI', 'March' => 'MARET', 'April' => 'APRIL',
    'May' => 'MEI', 'June' => 'JUNI', 'July' => 'JULI', 'August' => 'AGUSTUS',
    'September' => 'SEPTEMBER', 'October' => 'OKTOBER', 'November' => 'NOVEMBER', 'December' => 'DESEMBER'
];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));

$grouped = [];
foreach ($rows as $r) {
    $key = ($r['Tanggal'] instanceof DateTime) ? $r['Tanggal']->format('Y-m-d') : (string)$r['Tanggal'];
    if (!isset($grouped[$key])) $grouped[$key] = [];
    $grouped[$key][] = $r;
}

echo "<html><head><meta charset='UTF-8'><style>
table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 11px; }
th, td { border: 1px solid #000; padding: 4px; text-align: center; vertical-align: middle; }
.top { background:#afc0d6; font-weight:bold; }
.sub { background:#cfdeef; font-weight:bold; }
.unit { background:#efe9e9; font-weight:bold; }
.yellow { background:#fff200; font-weight:bold; }
.total, .avg { background:#d9d6c4; font-weight:bold; }
.left { text-align:left; }
.num2 { mso-number-format:'0.00'; }
</style></head><body>";

echo "<table>";
echo "<tr><th rowspan='2' class='top' style='width:70px;'></th><th colspan='8' class='top'>PEMAKAIAN AIR WASHING 3</th><th rowspan='2' class='top'>KET</th></tr>";
echo "<tr><th colspan='8' class='top'>" . htmlspecialchars($monthLabel) . "</th></tr>";
echo "<tr><th class='sub'></th><th class='sub'>WATER FLOW</th><th class='sub'>OPERASIONAL MESIN</th><th class='sub'>TOTAL PEMAKAIAN</th><th class='sub' colspan='5'></th><th class='sub'>CUT OFF JAM 09.00</th></tr>";
echo "<tr><th class='unit'>TANGGAL</th><th class='unit'>M3/h</th><th class='unit'>PER JAM</th><th class='unit'>M3</th><th class='unit' colspan='5'></th><th class='unit'></th></tr>";

if (empty($grouped)) {
    echo "<tr><td colspan='10'>Tidak ada data</td></tr>";
} else {
    foreach ($grouped as $dateRows) {
        $rowspan = count($dateRows);
        foreach ($dateRows as $i => $r) {
            $isOff = stripos((string)($r['Keterangan'] ?? ''), 'off') !== false;
            echo "<tr>";
            if ($i === 0) echo "<td rowspan='" . $rowspan . "'>" . htmlspecialchars($fmtDate($r['Tanggal'])) . "</td>";
            echo "<td class='num2'>" . htmlspecialchars($fmtNum($r['WaterFlow'], 2)) . "</td>";
            echo "<td class='num2'>" . htmlspecialchars($fmtNum($r['OperasionalMesin'], 2)) . "</td>";
            echo "<td class='num2" . ($isOff ? " yellow" : "") . "'>" . htmlspecialchars($fmtNum($r['TotalPemakaian'], 2)) . "</td>";
            echo "<td colspan='5'></td>";
            echo "<td class='left" . ($isOff ? " yellow" : "") . "'>" . htmlspecialchars((string)($r['Keterangan'] ?? '')) . "</td>";
            echo "</tr>";
        }
    }
    echo "<tr class='total'><td>TOTAL</td><td></td><td class='num2'>" . htmlspecialchars($fmtNum($sumOperasional, 2)) . "</td><td class='num2'>" . htmlspecialchars($fmtNum($sumTotal, 2)) . "</td><td colspan='5'></td><td></td></tr>";
    echo "<tr class='avg'><td>RATA-RATA</td><td class='num2'>" . htmlspecialchars($fmtNum($avgWater, 2)) . "</td><td class='num2'>" . htmlspecialchars($fmtNum($avgOperasional, 2)) . "</td><td class='num2'>" . htmlspecialchars($fmtNum($avgTotal, 2)) . "</td><td colspan='5'></td><td></td></tr>";
}

echo "</table></body></html>";
sqlsrv_close($conn);
exit;
