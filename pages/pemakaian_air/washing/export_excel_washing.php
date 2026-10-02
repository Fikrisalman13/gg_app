<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';

$start = $_POST['start_date'] ?? date('Y-m-01');
$end = $_POST['end_date'] ?? date('Y-m-d');

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Laporan_washing_' . date('Ymd_His') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

$sql = "SELECT Tanggal, Meter_Awal, Meter_Ahir, Total_Pemakaian, Operasional_Mesin, Pemakaian_rata2perjam, Keterangan
        FROM dbo.washing_air
        WHERE Tanggal BETWEEN ? AND ?
          AND (
              Meter_Awal IS NOT NULL
              OR Meter_Ahir IS NOT NULL
              OR Total_Pemakaian IS NOT NULL
              OR Operasional_Mesin IS NOT NULL
              OR Pemakaian_rata2perjam IS NOT NULL
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

$sumTotal = 0.0;
$sumOperasional = 0.0;
$sumRata = 0.0;
$cntTotal = 0;
$cntOperasional = 0;
$cntRata = 0;
foreach ($rows as $r) {
    $isOff = preg_match('/^\s*off\s*$/i', (string)($r['Keterangan'] ?? '')) === 1;
    if ($isOff) {
        continue;
    }

    if (is_numeric($r['Total_Pemakaian'])) { $sumTotal += (float)$r['Total_Pemakaian']; $cntTotal++; }
    if (is_numeric($r['Operasional_Mesin'])) { $sumOperasional += (float)$r['Operasional_Mesin']; $cntOperasional++; }
    if (is_numeric($r['Pemakaian_rata2perjam'])) { $sumRata += (float)$r['Pemakaian_rata2perjam']; $cntRata++; }
}
$avgTotal = $cntTotal ? $sumTotal / $cntTotal : null;
$avgOperasional = $cntOperasional ? $sumOperasional / $cntOperasional : null;
$avgRata = $cntRata ? $sumRata / $cntRata : null;

$monthMap = [
    'January' => 'JANUARI', 'February' => 'FEBRUARI', 'March' => 'MARET', 'April' => 'APRIL',
    'May' => 'MEI', 'June' => 'JUNI', 'July' => 'JULI', 'August' => 'AGUSTUS',
    'September' => 'SEPTEMBER', 'October' => 'OKTOBER', 'November' => 'NOVEMBER', 'December' => 'DESEMBER'
];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));

echo "<html><head><meta charset='UTF-8'><style>
table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 11px; }
th, td { border: 1px solid #000; padding: 4px; text-align: center; vertical-align: middle; }
.top { background:#afc0d6; font-weight:bold; }
.sub { background:#cfdeef; font-weight:bold; }
.unit { background:#efe9e9; font-weight:bold; }
.total, .avg { background:#d9d6c4; font-weight:bold; }
.left { text-align:left; }
.num2 { mso-number-format:'0.00'; }
</style></head><body>";

echo "<table>";
echo "<tr><th rowspan='2' class='top' style='width:70px;'></th><th colspan='5' class='top'>PEMAKAIAN AIR DI MESIN WASHING</th><th rowspan='2' class='top'>KET</th></tr>";
echo "<tr><th colspan='5' class='top'>" . htmlspecialchars($monthLabel) . "</th></tr>";
echo "<tr><th class='sub'>AWAL</th><th class='sub'>AKHIR</th><th class='sub'>TOTAL PEMAKAIAN</th><th class='sub'>OPERASIONAL MESIN</th><th class='sub'>PEMAKAIAN RATA RATA PER JAM</th><th class='sub'>CUT OFF JAM 09.00</th></tr>";
echo "<tr><th class='unit'>TANGGAL</th><th class='unit'>M3</th><th class='unit'>M3</th><th class='unit'>M3</th><th class='unit'>PER JAM</th><th class='unit'>M3</th><th class='unit'></th></tr>";

if (empty($rows)) {
    echo "<tr><td colspan='7'>Tidak ada data</td></tr>";
} else {
    foreach ($rows as $r) {
        $tgl = ($r['Tanggal'] instanceof DateTime) ? $r['Tanggal']->format('j') : date('j', strtotime((string)$r['Tanggal']));
        $isOff = preg_match('/^\s*off\s*$/i', (string)($r['Keterangan'] ?? '')) === 1;
        echo "<tr>";
        echo "<td>" . htmlspecialchars((string)$tgl) . "</td>";
        echo "<td class='num2'>" . htmlspecialchars($fmtNum($r['Meter_Awal'], 2)) . "</td>";
        echo "<td class='num2'>" . htmlspecialchars($fmtNum($r['Meter_Ahir'], 2)) . "</td>";
        echo "<td class='num2'>" . ($isOff ? "" : htmlspecialchars($fmtNum($r['Total_Pemakaian'], 2))) . "</td>";
        echo "<td class='num2'>" . ($isOff ? "" : htmlspecialchars($fmtNum($r['Operasional_Mesin'], 2))) . "</td>";
        echo "<td class='num2'>" . ($isOff ? "" : htmlspecialchars($fmtNum($r['Pemakaian_rata2perjam'], 2))) . "</td>";
        echo "<td class='left'>" . htmlspecialchars((string)($r['Keterangan'] ?? '')) . "</td>";
        echo "</tr>";
    }
    echo "<tr class='total'><td>TOTAL</td><td></td><td></td><td class='num2'>" . htmlspecialchars($fmtNum($sumTotal, 2)) . "</td><td class='num2'>" . htmlspecialchars($fmtNum($sumOperasional, 2)) . "</td><td class='num2'>" . htmlspecialchars($fmtNum($sumRata, 2)) . "</td><td></td></tr>";
    echo "<tr class='avg'><td>RATA-RATA</td><td></td><td></td><td class='num2'>" . htmlspecialchars($fmtNum($avgTotal, 2)) . "</td><td class='num2'>" . htmlspecialchars($fmtNum($avgOperasional, 2)) . "</td><td class='num2'>" . htmlspecialchars($fmtNum($avgRata, 2)) . "</td><td></td></tr>";
}

echo "</table></body></html>";
sqlsrv_close($conn);
exit;

