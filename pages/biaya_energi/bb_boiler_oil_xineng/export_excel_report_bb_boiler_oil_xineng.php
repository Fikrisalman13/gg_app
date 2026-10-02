<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';

$start = $_POST['start_date'] ?? date('Y-m-01');
$end = $_POST['end_date'] ?? date('Y-m-d');

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Report_BB_Boiler_Oil_Xineng_' . date('Ymd_His') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

$normalizeDate = function ($value) {
    $value = trim((string)$value);
    if ($value === '') return '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
    if (preg_match('/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/', $value, $m)) return $m[3] . '-' . $m[2] . '-' . $m[1];
    return '';
};

$start = $normalizeDate($start) ?: date('Y-m-01');
$end = $normalizeDate($end) ?: date('Y-m-d');

$sql = "SELECT CAST(tanggal AS DATE) AS tanggal,
               pemakaian_kg,
               harga_rp_per_kg,
               biaya_rp,
               total_biaya_boiler_oil_rp,
               extractor_kg,
               cgrate_kg,
               cyclon_kg,
               total_kg,
               ket
        FROM dbo.bb_boiler_oil_xineng_harian
        WHERE CAST(tanggal AS DATE) BETWEEN ? AND ?
        ORDER BY CAST(tanggal AS DATE) ASC";

$stmt = sqlsrv_query($conn, $sql, [$start, $end]);
if ($stmt === false) {
    echo '<b>SQL error</b>: ' . htmlspecialchars(print_r(sqlsrv_errors(), true));
    exit;
}

$rows = [];
$tot = [
    'pemakaian_kg' => 0.0,
    'biaya_rp' => 0.0,
    'total_biaya_boiler_oil_rp' => 0.0,
    'extractor_kg' => 0.0,
    'cgrate_kg' => 0.0,
    'cyclon_kg' => 0.0,
    'total_kg' => 0.0,
];
$sumHarga = 0.0;
$countHarga = 0;

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $dateObj = $r['tanggal'] ?? null;
    if ($dateObj instanceof DateTime) $dateKey = $dateObj->format('Y-m-d');
    else $dateKey = date('Y-m-d', strtotime((string)$dateObj));

    $row = [
        'tanggal' => $dateKey,
        'pemakaian_kg' => is_numeric($r['pemakaian_kg']) ? (float)$r['pemakaian_kg'] : 0.0,
        'harga_rp_per_kg' => is_numeric($r['harga_rp_per_kg']) ? (float)$r['harga_rp_per_kg'] : 0.0,
        'biaya_rp' => is_numeric($r['biaya_rp']) ? (float)$r['biaya_rp'] : 0.0,
        'total_biaya_boiler_oil_rp' => is_numeric($r['total_biaya_boiler_oil_rp']) ? (float)$r['total_biaya_boiler_oil_rp'] : 0.0,
        'extractor_kg' => is_numeric($r['extractor_kg']) ? (float)$r['extractor_kg'] : 0.0,
        'cgrate_kg' => is_numeric($r['cgrate_kg']) ? (float)$r['cgrate_kg'] : 0.0,
        'cyclon_kg' => is_numeric($r['cyclon_kg']) ? (float)$r['cyclon_kg'] : 0.0,
        'total_kg' => is_numeric($r['total_kg']) ? (float)$r['total_kg'] : 0.0,
        'ket' => (string)($r['ket'] ?? ''),
    ];

    $rows[] = $row;
    foreach ($tot as $k => $_) $tot[$k] += $row[$k];
    $sumHarga += $row['harga_rp_per_kg'];
    $countHarga++;
}
sqlsrv_free_stmt($stmt);

$rowCount = count($rows);
$avg = [
    'pemakaian_kg' => $rowCount > 0 ? ($tot['pemakaian_kg'] / $rowCount) : 0,
    'biaya_rp' => $rowCount > 0 ? ($tot['biaya_rp'] / $rowCount) : 0,
    'total_biaya_boiler_oil_rp' => $rowCount > 0 ? ($tot['total_biaya_boiler_oil_rp'] / $rowCount) : 0,
    'extractor_kg' => $rowCount > 0 ? ($tot['extractor_kg'] / $rowCount) : 0,
    'cgrate_kg' => $rowCount > 0 ? ($tot['cgrate_kg'] / $rowCount) : 0,
    'cyclon_kg' => $rowCount > 0 ? ($tot['cyclon_kg'] / $rowCount) : 0,
    'total_kg' => $rowCount > 0 ? ($tot['total_kg'] / $rowCount) : 0,
];

$fmt = function ($val, $dec = 2) {
    if (!is_numeric($val)) return '0.00';
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

echo "<html><head><meta charset='UTF-8'><style>
body{font-family:Calibri,Arial,sans-serif}
.rep{border-collapse:collapse;font-size:11px}
.rep th,.rep td{border:1px solid #000;padding:3px 4px;text-align:center;vertical-align:middle;white-space:nowrap}
.title{background:#76923c;font-weight:700;font-size:17px;text-align:left;padding-left:8px}
.monthTag{background:#fff;font-weight:700;font-size:14px;text-align:left}
.monthVal{background:#fff;font-weight:700;font-size:14px;text-align:left}
.datecol{background:#e6efd5;font-weight:700}
.sec{background:#9bc2d1;font-weight:700}
.secy{background:#ffc000;font-weight:700}
.sub{background:#9bc2d1;font-weight:700}
.unit{background:#cfeaf6;font-weight:700}
.data{background:#d8d5b8}
.boil{background:#ffc000}
.totalcol{background:#ffc000;font-weight:700}
.sum{background:#f1c232;font-weight:700}
.num2{mso-number-format:'0.00'}
.ket{font-weight:700}
</style></head><body>";

echo "<table class='rep'>";
echo "<tr><th class='title' colspan='10'>PEMAKAIAN BATU BARA XINENG, PEMBUANGAN EXTRACTOR &amp; CYCLONE</th></tr>";
echo "<tr><th class='monthTag'>Bulan :</th><th class='monthVal' colspan='9'>" . htmlspecialchars($monthLabel) . "</th></tr>";

echo "<tr>";
echo "<th class='datecol' rowspan='3'>Tanggal</th>";
echo "<th class='sec' colspan='3'>BATU BARA ADB 5600-5800 0-200 MM ASALAN</th>";
echo "<th class='secy' rowspan='3'>TOTAL BIAYA<br>BOILER OIL(Rp)</th>";
echo "<th class='sec' colspan='3'>BOTTOM</th>";
echo "<th class='sec' rowspan='2'>TOTAL</th>";
echo "<th class='sec' rowspan='2'>KET</th>";
echo "</tr>";

echo "<tr>";
echo "<th class='sub'>PEMAKAIAN</th>";
echo "<th class='sub'>HARGA/KG</th>";
echo "<th class='sub'>BIAYA</th>";
echo "<th class='sub'>EXTRACTOR</th>";
echo "<th class='sub'>CGRATE</th>";
echo "<th class='sub'>CYCLON</th>";
echo "</tr>";

echo "<tr>";
echo "<th class='unit'>(KG)</th>";
echo "<th class='unit'>(Rp)</th>";
echo "<th class='unit'>(Rp)</th>";
echo "<th class='unit'>KG</th>";
echo "<th class='unit'>KG</th>";
echo "<th class='unit'>KG</th>";
echo "<th class='unit'>KG</th>";
echo "<th class='unit'></th>";
echo "</tr>";

if (empty($rows)) {
    echo "<tr><td colspan='10'>Tidak ada data pada rentang tanggal ini.</td></tr>";
} else {
    foreach ($rows as $r) {
        echo "<tr>";
        echo "<td class='datecol'>" . htmlspecialchars($fmtDay($r['tanggal'])) . "</td>";
        echo "<td class='data num2'>" . htmlspecialchars($fmt($r['pemakaian_kg'])) . "</td>";
        echo "<td class='data num2'>" . htmlspecialchars($fmt($r['harga_rp_per_kg'])) . "</td>";
        echo "<td class='data num2'>" . htmlspecialchars($fmt($r['biaya_rp'])) . "</td>";
        echo "<td class='boil num2'>" . htmlspecialchars($fmt($r['total_biaya_boiler_oil_rp'])) . "</td>";
        echo "<td class='data num2'>" . htmlspecialchars($fmt($r['extractor_kg'])) . "</td>";
        echo "<td class='data num2'>" . htmlspecialchars($fmt($r['cgrate_kg'])) . "</td>";
        echo "<td class='data num2'>" . htmlspecialchars($fmt($r['cyclon_kg'])) . "</td>";
        echo "<td class='totalcol num2'>" . htmlspecialchars($fmt($r['total_kg'])) . "</td>";
        echo "<td class='ket'>" . htmlspecialchars($r['ket']) . "</td>";
        echo "</tr>";
    }

    echo "<tr>";
    echo "<td class='sum'>TOTAL</td>";
    echo "<td class='sum num2'>" . htmlspecialchars($fmt($tot['pemakaian_kg'])) . "</td>";
    echo "<td class='sum'></td>";
    echo "<td class='sum num2'>" . htmlspecialchars($fmt($tot['biaya_rp'])) . "</td>";
    echo "<td class='sum num2'>" . htmlspecialchars($fmt($tot['total_biaya_boiler_oil_rp'])) . "</td>";
    echo "<td class='sum num2'>" . htmlspecialchars($fmt($tot['extractor_kg'])) . "</td>";
    echo "<td class='sum num2'>" . htmlspecialchars($fmt($tot['cgrate_kg'])) . "</td>";
    echo "<td class='sum num2'>" . htmlspecialchars($fmt($tot['cyclon_kg'])) . "</td>";
    echo "<td class='sum num2'>" . htmlspecialchars($fmt($tot['total_kg'])) . "</td>";
    echo "<td class='sum'></td>";
    echo "</tr>";

    echo "<tr>";
    echo "<td class='sum'>RATA-RATA</td>";
    echo "<td class='sum num2'>" . htmlspecialchars($fmt($avg['pemakaian_kg'])) . "</td>";
    echo "<td class='sum'></td>";
    echo "<td class='sum num2'>" . htmlspecialchars($fmt($avg['biaya_rp'])) . "</td>";
    echo "<td class='sum num2'>" . htmlspecialchars($fmt($avg['total_biaya_boiler_oil_rp'])) . "</td>";
    echo "<td class='sum num2'>" . htmlspecialchars($fmt($avg['extractor_kg'])) . "</td>";
    echo "<td class='sum num2'>" . htmlspecialchars($fmt($avg['cgrate_kg'])) . "</td>";
    echo "<td class='sum num2'>" . htmlspecialchars($fmt($avg['cyclon_kg'])) . "</td>";
    echo "<td class='sum num2'>" . htmlspecialchars($fmt($avg['total_kg'])) . "</td>";
    echo "<td class='sum'></td>";
    echo "</tr>";
}

echo "</table></body></html>";
