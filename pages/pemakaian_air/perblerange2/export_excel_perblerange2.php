<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';

$start = $_POST['start_date'] ?? date('Y-m-01');
$end = $_POST['end_date'] ?? date('Y-m-d');

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Laporan_perblerange2_' . date('Ymd_His') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

$sql = "SELECT Tanggal, Meter_Awal, Meter_Ahir, Oprasional_Mesin, Pemakaian_Rata2perjam, Keterangan
        FROM dbo.perblerange2_air
        WHERE Tanggal BETWEEN ? AND ?
        ORDER BY Tanggal ASC, Id ASC";
$stmt = sqlsrv_query($conn, $sql, [$start, $end]);
if ($stmt === false) {
    echo "<b>SQL error</b>: " . htmlspecialchars(print_r(sqlsrv_errors(), true));
    exit;
}

$rows = [];
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $r;
}
sqlsrv_free_stmt($stmt);

$fmtDate = function($val) {
    if ($val instanceof DateTime) return $val->format('Y-m-d');
    return (string)$val;
};
$fmtNum = function($val, $decimals) {
    if ($val === null || $val === '') return '-';
    if (!is_numeric($val)) return (string)$val;
    return number_format((float)$val, $decimals, '.', ',');
};

$sumTotal = 0.0;
$sumRata = 0.0;
$sumOpr = 0.0;
$cntTotal = 0;
$cntRata = 0;
$cntOpr = 0;
foreach ($rows as $r) {
    if (is_numeric($r['Meter_Awal']) && is_numeric($r['Meter_Ahir'])) {
        $sumTotal += (float)$r['Meter_Ahir'] - (float)$r['Meter_Awal'];
        $cntTotal++;
    }
    $isOff = strtolower(trim((string)($r['Keterangan'] ?? ''))) === 'off';
    if (!$isOff && is_numeric($r['Pemakaian_Rata2perjam'])) {
        $sumRata += (float)$r['Pemakaian_Rata2perjam'];
        $cntRata++;
    }
    if (!$isOff && is_numeric($r['Oprasional_Mesin'])) {
        $sumOpr += (float)$r['Oprasional_Mesin'];
        $cntOpr++;
    }
}
$avgTotal = $cntTotal ? $sumTotal / $cntTotal : null;
$avgRata = $cntRata ? $sumRata / $cntRata : null;
$avgOpr = $cntOpr ? $sumOpr / $cntOpr : null;

$monthMap = [
    'January' => 'JANUARI', 'February' => 'FEBRUARI', 'March' => 'MARET', 'April' => 'APRIL',
    'May' => 'MEI', 'June' => 'JUNI', 'July' => 'JULI', 'August' => 'AGUSTUS',
    'September' => 'SEPTEMBER', 'October' => 'OKTOBER', 'November' => 'NOVEMBER', 'December' => 'DESEMBER'
];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));
if (date('Y-m', strtotime($start)) !== date('Y-m', strtotime($end))) {
    $monthLabel = strtoupper(date('d M Y', strtotime($start)) . ' - ' . date('d M Y', strtotime($end)));
}

echo "<html><head><meta charset='UTF-8'><style>
table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 11px; }
th, td { border: 1px solid #000; padding: 4px; text-align: center; vertical-align: middle; }
th.top { background: #afc0d6; font-weight: bold; }
th.green { background: #cfdeef; font-weight: bold; }
th.lightgreen { background: #cfdeef; font-weight: bold; }
th.pink { background: #cfdeef; font-weight: bold; }
th.blue { background: #cfdeef; font-weight: bold; }
th.ket { background: #afc0d6; font-weight: bold; }
th.unit { background: #efe9e9; font-weight: bold; }
td.col-blue { background: #b7c9de; }
tr.total td, tr.avg td { font-weight: bold; background: #d9d6c4; }
.num2 { mso-number-format:'0.00'; }
</style></head><body>";

echo "<table>";
echo "<tr>
        <th class='top' colspan='6'>METER AIR Perble Range 2</th>
        <th class='ket' rowspan='4'>KET<br>CUT OFF JAM 09.00</th>
      </tr>";
echo "<tr><th class='top' colspan='6'>" . htmlspecialchars($monthLabel) . "</th></tr>";
echo "<tr>
        <th class='lightgreen'>TANGGAL</th>
        <th class='green'>AWAL</th>
        <th class='green'>AKHIR</th>
        <th class='green'>TOTAL PEMAKAIAN</th>
        <th class='blue'>OPERASIONAL MESIN</th>
        <th class='pink'>PEMAKAIAN RATA RATA / JAM</th>
      </tr>";
echo "<tr>
        <th class='lightgreen'></th>
        <th class='unit'>M<sup>3</sup></th>
        <th class='unit'>M<sup>3</sup></th>
        <th class='unit'>M<sup>3</sup></th>
        <th class='unit'>PER JAM</th>
        <th class='unit'>M<sup>3</sup></th>
      </tr>";

if (empty($rows)) {
    echo "<tr><td colspan='7'>Tidak ada data</td></tr>";
} else {
    foreach ($rows as $r) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($fmtDate($r['Tanggal'])) . "</td>";
        echo "<td class='num2'>" . htmlspecialchars($fmtNum($r['Meter_Awal'], 2)) . "</td>";
        echo "<td class='num2'>" . htmlspecialchars($fmtNum($r['Meter_Ahir'], 2)) . "</td>";
        $total = (is_numeric($r['Meter_Awal']) && is_numeric($r['Meter_Ahir'])) ? ((float)$r['Meter_Ahir'] - (float)$r['Meter_Awal']) : null;
        echo "<td class='col-blue num2'>" . htmlspecialchars($fmtNum($total, 2)) . "</td>";
        $rowOff = strtolower(trim((string)($r['Keterangan'] ?? ''))) === 'off';
        echo "<td class='num2'>" . ($rowOff ? '-' : htmlspecialchars($fmtNum($r['Oprasional_Mesin'], 2))) . "</td>";
        echo "<td class='num2'>" . ($rowOff ? '-' : htmlspecialchars($fmtNum($r['Pemakaian_Rata2perjam'], 2))) . "</td>";
        echo "<td>" . htmlspecialchars((string)($r['Keterangan'] ?? '')) . "</td>";
        echo "</tr>";
    }
    echo "<tr class='total'>
            <td colspan='3'>TOTAL</td>
            <td class='col-blue num2'>" . htmlspecialchars($fmtNum($sumTotal, 2)) . "</td>
            <td class='num2'>" . htmlspecialchars($fmtNum($sumOpr, 2)) . "</td>
            <td class='num2'>" . htmlspecialchars($fmtNum($sumRata, 2)) . "</td>
            <td></td>
          </tr>";
    echo "<tr class='avg'>
            <td colspan='3'>RATA - RATA</td>
            <td class='col-blue num2'>" . htmlspecialchars($fmtNum($avgTotal, 2)) . "</td>
            <td class='num2'>" . htmlspecialchars($fmtNum($avgOpr, 2)) . "</td>
            <td class='num2'>" . htmlspecialchars($fmtNum($avgRata, 2)) . "</td>
            <td></td>
          </tr>";
}

echo "</table></body></html>";
sqlsrv_close($conn);
exit;






