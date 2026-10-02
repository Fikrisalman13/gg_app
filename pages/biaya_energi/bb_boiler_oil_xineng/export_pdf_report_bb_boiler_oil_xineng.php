<?php
session_start();
ob_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php';

use Dompdf\Dompdf;

$menuId = 230;
requireView($conn, $menuId);

$normalizeDate = function ($value) {
    $value = trim((string)$value);
    if ($value === '') return '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
    if (preg_match('/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/', $value, $m)) return $m[3] . '-' . $m[2] . '-' . $m[1];
    return '';
};

$start = $normalizeDate($_POST['start_date'] ?? date('Y-m-01')) ?: date('Y-m-01');
$end = $normalizeDate($_POST['end_date'] ?? date('Y-m-d')) ?: date('Y-m-d');
if (strtotime($start) > strtotime($end)) {
    $tmp = $start;
    $start = $end;
    $end = $tmp;
}

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
    while (ob_get_level() > 0) ob_end_clean();
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
    foreach ($tot as $k => $_) {
        $tot[$k] += $row[$k];
    }
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
$fmtDay = function ($ymd) {
    return $ymd ? date('j', strtotime($ymd)) : '';
};
$esc = function ($txt) {
    return htmlspecialchars((string)$txt, ENT_QUOTES, 'UTF-8');
};

$monthMap = [
    'January' => 'JANUARI', 'February' => 'FEBRUARI', 'March' => 'MARET', 'April' => 'APRIL',
    'May' => 'MEI', 'June' => 'JUNI', 'July' => 'JULI', 'August' => 'AGUSTUS',
    'September' => 'SEPTEMBER', 'October' => 'OKTOBER', 'November' => 'NOVEMBER', 'December' => 'DESEMBER'
];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));

$html = '<!doctype html>
<html>
<head>
<meta charset="UTF-8">
<style>
@page { margin: 10px; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8px; }
table.rep { border-collapse: collapse; width: 100%; table-layout: fixed; }
table.rep th, table.rep td { border: 1px solid #000; text-align: center; vertical-align: middle; padding: 2px 3px; }
.title { background: #76923c; font-weight: 700; font-size: 13px; line-height: 1.1; color: #000; }
.month, .monthLabel { background: #ffffff; font-weight: 700; font-size: 11px; text-align: left; }
.datecol { background: #e6efd5; font-weight: 700; }
.sec { background: #9bc2d1; font-weight: 700; }
.sec-yellow { background: #ffc000; font-weight: 700; }
.sub { background: #9bc2d1; font-weight: 700; }
.unit { background: #cfeaf6; font-weight: 700; }
.data { background: #d8d5b8; text-align: right; }
.boil { background: #ffc000; text-align: right; }
.tot { background: #ffc000; font-weight: 700; text-align: right; }
.sum { background: #ffd24d; font-weight: 700; }
.sum-right { background: #ffd24d; font-weight: 700; text-align: right; }
.ket { font-weight: 700; }
</style>
</head>
<body>
<table class="rep">
<colgroup>
  <col style="width:9%">
  <col style="width:11%">
  <col style="width:9%">
  <col style="width:11%">
  <col style="width:11%">
  <col style="width:9%">
  <col style="width:7%">
  <col style="width:7%">
  <col style="width:6%">
  <col style="width:10%">
</colgroup>
<thead>
<tr><th class="title" colspan="10">PEMAKAIAN BATU BARA XINENG, PEMBUANGAN EXTRACTOR &amp; CYCLONE</th></tr>
<tr><th class="month">Bulan :</th><th class="monthLabel" colspan="9">' . $esc($monthLabel) . '</th></tr>
<tr>
  <th class="datecol" rowspan="3">Tanggal</th>
  <th class="sec" colspan="3">BATU BARA ADB 5600-5800 0-200 MM ASALAN</th>
  <th class="sec-yellow" rowspan="3">TOTAL BIAYA<br>BOILER OIL(Rp)</th>
  <th class="sec" colspan="3">BOTTOM</th>
  <th class="sec" rowspan="2">TOTAL</th>
  <th class="sec" rowspan="2">KET</th>
</tr>
<tr>
  <th class="sub">PEMAKAIAN</th>
  <th class="sub">HARGA/KG</th>
  <th class="sub">BIAYA</th>
  <th class="sub">EXTRACTOR</th>
  <th class="sub">CGRATE</th>
  <th class="sub">CYCLON</th>
</tr>
<tr>
  <th class="unit">(KG)</th>
  <th class="unit">(Rp)</th>
  <th class="unit">(Rp)</th>
  <th class="unit">KG</th>
  <th class="unit">KG</th>
  <th class="unit">KG</th>
  <th class="unit">KG</th>
  <th class="unit"></th>
</tr>
</thead>
<tbody>';

if (empty($rows)) {
    $html .= '<tr><td colspan="10">Tidak ada data pada rentang tanggal ini.</td></tr>';
} else {
    foreach ($rows as $r) {
        $html .= '<tr>';
        $html .= '<td class="datecol">' . $esc($fmtDay($r['tanggal'])) . '</td>';
        $html .= '<td class="data">' . $esc($fmt($r['pemakaian_kg'])) . '</td>';
        $html .= '<td class="data">' . $esc($fmt($r['harga_rp_per_kg'])) . '</td>';
        $html .= '<td class="data">' . $esc($fmt($r['biaya_rp'])) . '</td>';
        $html .= '<td class="boil">' . $esc($fmt($r['total_biaya_boiler_oil_rp'])) . '</td>';
        $html .= '<td class="data">' . $esc($fmt($r['extractor_kg'])) . '</td>';
        $html .= '<td class="data">' . $esc($fmt($r['cgrate_kg'])) . '</td>';
        $html .= '<td class="data">' . $esc($fmt($r['cyclon_kg'])) . '</td>';
        $html .= '<td class="tot">' . $esc($fmt($r['total_kg'])) . '</td>';
        $html .= '<td class="ket">' . $esc($r['ket']) . '</td>';
        $html .= '</tr>';
    }

    $html .= '<tr>';
    $html .= '<td class="sum">TOTAL</td>';
    $html .= '<td class="sum-right">' . $esc($fmt($tot['pemakaian_kg'])) . '</td>';
    $html .= '<td class="sum"></td>';
    $html .= '<td class="sum-right">' . $esc($fmt($tot['biaya_rp'])) . '</td>';
    $html .= '<td class="sum-right">' . $esc($fmt($tot['total_biaya_boiler_oil_rp'])) . '</td>';
    $html .= '<td class="sum-right">' . $esc($fmt($tot['extractor_kg'])) . '</td>';
    $html .= '<td class="sum-right">' . $esc($fmt($tot['cgrate_kg'])) . '</td>';
    $html .= '<td class="sum-right">' . $esc($fmt($tot['cyclon_kg'])) . '</td>';
    $html .= '<td class="sum-right">' . $esc($fmt($tot['total_kg'])) . '</td>';
    $html .= '<td class="sum"></td>';
    $html .= '</tr>';

    $html .= '<tr>';
    $html .= '<td class="sum">RATA-RATA</td>';
    $html .= '<td class="sum-right">' . $esc($fmt($avg['pemakaian_kg'])) . '</td>';
    $html .= '<td class="sum"></td>';
    $html .= '<td class="sum-right">' . $esc($fmt($avg['biaya_rp'])) . '</td>';
    $html .= '<td class="sum-right">' . $esc($fmt($avg['total_biaya_boiler_oil_rp'])) . '</td>';
    $html .= '<td class="sum-right">' . $esc($fmt($avg['extractor_kg'])) . '</td>';
    $html .= '<td class="sum-right">' . $esc($fmt($avg['cgrate_kg'])) . '</td>';
    $html .= '<td class="sum-right">' . $esc($fmt($avg['cyclon_kg'])) . '</td>';
    $html .= '<td class="sum-right">' . $esc($fmt($avg['total_kg'])) . '</td>';
    $html .= '<td class="sum"></td>';
    $html .= '</tr>';
}

$html .= '</tbody></table></body></html>';

$dompdf = new Dompdf(['isRemoteEnabled' => true]);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

while (ob_get_level() > 0) {
    ob_end_clean();
}

$dompdf->stream('Report_BB_Boiler_Oil_Xineng_' . date('Ymd_His') . '.pdf', ['Attachment' => true]);
exit;
