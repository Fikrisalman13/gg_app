<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';

$start = $_POST['start_date'] ?? date('Y-m-01');
$end = $_POST['end_date'] ?? date('Y-m-d');

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Laporan_LAB_' . date('Ymd_His') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

$sql = "SELECT CAST(Tanggal AS DATE) AS Tanggal, Meter_Awal, Meter_Akhir, Total_Pemakaian
        FROM dbo.lab_air
        WHERE CAST(Tanggal AS DATE) BETWEEN ? AND ?
          AND (
                Meter_Awal IS NOT NULL
                OR Meter_Akhir IS NOT NULL
                OR Total_Pemakaian IS NOT NULL
                OR (Keterangan IS NOT NULL AND LTRIM(RTRIM(Keterangan)) <> '')
              )
        ORDER BY CAST(Tanggal AS DATE) ASC, Id ASC";
$stmt = sqlsrv_query($conn, $sql, [$start, $end]);
if ($stmt === false) {
    echo "<b>SQL error</b>: " . htmlspecialchars(print_r(sqlsrv_errors(), true));
    exit;
}

$rows = [];
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $rows[] = $r;
if ($stmt) sqlsrv_free_stmt($stmt);

$sumPemakaian = 0.0;
$countPemakaian = 0;
foreach ($rows as $r) {
    $pemakaian = $r['Total_Pemakaian'] ?? null;
    if ($pemakaian === null && is_numeric($r['Meter_Awal'] ?? null) && is_numeric($r['Meter_Akhir'] ?? null)) {
        $pemakaian = (float)$r['Meter_Akhir'] - (float)$r['Meter_Awal'];
    }
    if (is_numeric($pemakaian)) {
        $sumPemakaian += (float)$pemakaian;
        $countPemakaian++;
    }
}
$avgPemakaian = $countPemakaian > 0 ? ($sumPemakaian / $countPemakaian) : 0;

$fmtNum = function($val, $decimals = 2) {
    if ($val === null || $val === '' || !is_numeric($val)) return '';
    return number_format((float)$val, $decimals, '.', ',');
};

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
.head { background:#bfb798; font-weight:bold; }
.sub { background:#9ec3f0; font-weight:bold; }
.unit { background:#d7ebff; font-weight:bold; }
.no-col { background:#ffff00; font-weight:bold; }
.data-gray { background:#efefef; }
.data-blue { background:#d8e7f7; }
.sum td { background:#ece9d6; font-weight:bold; }
</style></head><body>";

echo "<div style='font-family:Arial,sans-serif;font-size:14px;font-weight:bold;margin-bottom:8px;'>" . htmlspecialchars($monthLabel) . "</div>";
echo "<table>";
echo "<tr><th class='no-col' rowspan='3' style='width:70px;'>NO</th><th class='head' colspan='3'>LAB</th></tr>";
echo "<tr><th class='sub'>Awal</th><th class='sub'>Akhir</th><th class='sub'>Pemakaian</th></tr>";
echo "<tr><th class='unit'>(M<sup>3</sup>)</th><th class='unit'>(M<sup>3</sup>)</th><th class='unit'>(M<sup>3</sup>)</th></tr>";

if (empty($rows)) {
    echo "<tr><td colspan='4'>Tidak ada data</td></tr>";
} else {
    $no = 1;
    foreach ($rows as $r) {
        $pemakaian = $r['Total_Pemakaian'] ?? null;
        if ($pemakaian === null && is_numeric($r['Meter_Awal'] ?? null) && is_numeric($r['Meter_Akhir'] ?? null)) {
            $pemakaian = (float)$r['Meter_Akhir'] - (float)$r['Meter_Awal'];
        }
        echo "<tr>";
        echo "<td class='no-col'>" . $no++ . "</td>";
        echo "<td class='data-gray'>" . htmlspecialchars($fmtNum($r['Meter_Awal'] ?? null, 3)) . "</td>";
        echo "<td class='data-gray'>" . htmlspecialchars($fmtNum($r['Meter_Akhir'] ?? null, 3)) . "</td>";
        echo "<td class='data-blue'>" . htmlspecialchars($fmtNum($pemakaian, 2)) . "</td>";
        echo "</tr>";
    }
    echo "<tr class='sum'><td class='no-col'>Total</td><td></td><td></td><td>" . htmlspecialchars($fmtNum($sumPemakaian, 2)) . "</td></tr>";
    echo "<tr class='sum'><td class='no-col'>Rata-Rata</td><td></td><td></td><td>" . htmlspecialchars($fmtNum($avgPemakaian, 2)) . "</td></tr>";
}

echo "</table></body></html>";
sqlsrv_close($conn);
exit;
