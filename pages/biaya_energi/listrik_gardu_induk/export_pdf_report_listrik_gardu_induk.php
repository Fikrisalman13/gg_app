<?php
session_start();
ob_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php';

use Dompdf\Dompdf;

$menuId = 236;
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

$sql = "WITH cte AS (
            SELECT x.id, x.tanggal, x.lvbp_kwh, x.vbp_kwh, x.kvarh, x.cos_phi, x.faktor_kali, x.rp_per_kwh, x.pf_standar, x.kapasitas_kva,
                   LEAD(x.lvbp_kwh) OVER (ORDER BY CAST(x.tanggal AS DATE), x.id) AS lvbp_next,
                   LEAD(x.vbp_kwh) OVER (ORDER BY CAST(x.tanggal AS DATE), x.id) AS vbp_next
            FROM dbo.listrik_gardu_induk_harian x
        )
        SELECT CAST(cte.tanggal AS DATE) AS tanggal,
               cte.lvbp_kwh,
               cte.vbp_kwh,
               cte.kvarh,
               cte.cos_phi,
               cte.rp_per_kwh,
               CASE WHEN cte.lvbp_next IS NULL OR cte.vbp_next IS NULL THEN NULL
                    ELSE (((cte.lvbp_next - cte.lvbp_kwh) * cte.faktor_kali) + ((cte.vbp_next - cte.vbp_kwh) * cte.faktor_kali)) END AS total_daya_perday_kw,
               CASE WHEN cte.lvbp_next IS NULL OR cte.vbp_next IS NULL THEN NULL
                    ELSE ((((cte.lvbp_next - cte.lvbp_kwh) * cte.faktor_kali) + ((cte.vbp_next - cte.vbp_kwh) * cte.faktor_kali)) / 24.0) END AS total_daya_perjam_kwh,
               CASE WHEN cte.lvbp_next IS NULL OR cte.vbp_next IS NULL THEN NULL
                    ELSE ((((cte.lvbp_next - cte.lvbp_kwh) * cte.faktor_kali) + ((cte.vbp_next - cte.vbp_kwh) * cte.faktor_kali)) * cte.rp_per_kwh) END AS biaya_perday_rp,
               CASE WHEN cte.lvbp_next IS NULL OR cte.vbp_next IS NULL OR ISNULL(cte.pf_standar,0)=0 THEN NULL
                    ELSE (((((cte.lvbp_next - cte.lvbp_kwh) * cte.faktor_kali) + ((cte.vbp_next - cte.vbp_kwh) * cte.faktor_kali)) / 24.0) / cte.pf_standar) END AS kva_pln_perjam,
               CASE WHEN cte.lvbp_next IS NULL OR cte.vbp_next IS NULL OR ISNULL(cte.kapasitas_kva,0)=0 THEN NULL
                    ELSE ((((((cte.lvbp_next - cte.lvbp_kwh) * cte.faktor_kali) + ((cte.vbp_next - cte.vbp_kwh) * cte.faktor_kali)) / 24.0) / cte.kapasitas_kva) * 100.0) END AS efisiensi_persen
        FROM cte
        WHERE CAST(cte.tanggal AS DATE) BETWEEN ? AND ?
        ORDER BY CAST(cte.tanggal AS DATE) ASC, cte.id ASC";

$stmt = sqlsrv_query($conn, $sql, [$start, $end]);
if ($stmt === false) {
    while (ob_get_level() > 0) ob_end_clean();
    echo '<b>SQL error</b>: ' . htmlspecialchars(print_r(sqlsrv_errors(), true));
    exit;
}

$rows = [];
$tot = [
    'total_daya_perday_kw' => 0.0,
    'total_daya_perjam_kwh' => 0.0,
    'biaya_perday_rp' => 0.0,
    'efisiensi_persen' => 0.0,
    'kva_pln_perjam' => 0.0,
];
$validCount = 0;

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $dateObj = $r['tanggal'] ?? null;
    if ($dateObj instanceof DateTime) $dateKey = $dateObj->format('Y-m-d');
    else $dateKey = date('Y-m-d', strtotime((string)$dateObj));

    $row = [
        'tanggal' => $dateKey,
        'lvbp_kwh' => is_numeric($r['lvbp_kwh']) ? (float)$r['lvbp_kwh'] : 0.0,
        'vbp_kwh' => is_numeric($r['vbp_kwh']) ? (float)$r['vbp_kwh'] : 0.0,
        'kvarh' => is_numeric($r['kvarh']) ? (float)$r['kvarh'] : 0.0,
        'cos_phi' => is_numeric($r['cos_phi']) ? (float)$r['cos_phi'] : 0.0,
        'rp_per_kwh' => is_numeric($r['rp_per_kwh']) ? (float)$r['rp_per_kwh'] : 0.0,
        'total_daya_perday_kw' => is_numeric($r['total_daya_perday_kw']) ? (float)$r['total_daya_perday_kw'] : null,
        'total_daya_perjam_kwh' => is_numeric($r['total_daya_perjam_kwh']) ? (float)$r['total_daya_perjam_kwh'] : null,
        'biaya_perday_rp' => is_numeric($r['biaya_perday_rp']) ? (float)$r['biaya_perday_rp'] : null,
        'efisiensi_persen' => is_numeric($r['efisiensi_persen']) ? (float)$r['efisiensi_persen'] : null,
        'kva_pln_perjam' => is_numeric($r['kva_pln_perjam']) ? (float)$r['kva_pln_perjam'] : null,
    ];

    $rows[] = $row;
    if ($row['total_daya_perday_kw'] !== null) {
        $validCount++;
        foreach ($tot as $k => $_) {
            $tot[$k] += (float)$row[$k];
        }
    }
}
sqlsrv_free_stmt($stmt);

$avg = [
    'total_daya_perday_kw' => $validCount > 0 ? ($tot['total_daya_perday_kw'] / $validCount) : 0,
    'total_daya_perjam_kwh' => $validCount > 0 ? ($tot['total_daya_perjam_kwh'] / $validCount) : 0,
    'biaya_perday_rp' => $validCount > 0 ? ($tot['biaya_perday_rp'] / $validCount) : 0,
    'efisiensi_persen' => $validCount > 0 ? ($tot['efisiensi_persen'] / $validCount) : 0,
    'kva_pln_perjam' => $validCount > 0 ? ($tot['kva_pln_perjam'] / $validCount) : 0,
];

$fmt = function ($val, $dec = 2) {
    if ($val === null || $val === '') return '-';
    if (!is_numeric($val)) return (string)$val;
    return number_format((float)$val, $dec, '.', ',');
};
$fmtDay = function ($ymd) {
    return $ymd ? date('j', strtotime($ymd)) : '';
};
$esc = function ($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
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
table.rep th, table.rep td { border: 1px solid #000; padding: 2px 3px; text-align: center; vertical-align: middle; }
.title { background: #fff200; font-weight: 700; font-size: 13px; line-height: 1.15; }
.monthTag, .monthVal { background: #ffffff; font-weight: 700; font-size: 10px; text-align: left; }
.unit { background: #ead1dc; font-weight: 700; font-size: 12px; }
.blank { background: #ffffff; }
.datecol { background: #e6efd5; font-weight: 700; }
.sec-meter { background: #ead1dc; font-weight: 700; }
.sub-meter { background: #cfe2f3; font-weight: 700; }
.yellow { background: #fff200; font-weight: 700; }
.data-blue { background: #cfe2f3; }
.data-yellow { background: #fff200; }
.data-cost { background: #d9d2b6; }
.sum { background: #cfe2f3; font-weight: 700; }
.sum-yellow { background: #d9d2b6; font-weight: 700; }
.txt-right { text-align: right; }
.txt-left { text-align: left; }
</style>
</head>
<body>
<table class="rep">
<colgroup>
  <col style="width:6%">
  <col style="width:7%">
  <col style="width:7%">
  <col style="width:7%">
  <col style="width:4%">
  <col style="width:15%">
  <col style="width:15%">
  <col style="width:6%">
  <col style="width:17%">
  <col style="width:7%">
  <col style="width:9%">
</colgroup>
<thead>
<tr><th class="title" colspan="11">PENCATATAN DAN PERHITUNGAN HARIAN<br>PEMAKAIAN ENERGI LISTRIK PLN</th></tr>
<tr><th class="monthTag">Bulan :</th><th class="monthVal" colspan="10">' . $esc($monthLabel) . '</th></tr>
<tr><th class="blank"></th><th class="unit" colspan="4">GARDU 1</th><th class="blank" colspan="6"></th></tr>
<tr>
  <th rowspan="3" class="datecol">TGL</th>
  <th colspan="4" class="sec-meter">METER TERCATAT</th>
  <th rowspan="2" class="yellow">TOTAL DAYA TERPAKAI PERDAY (kW)</th>
  <th rowspan="2" class="yellow">TOTAL DAYA TERPAKAI PERJAM (kWh)</th>
  <th rowspan="2" class="yellow">Rp PER KWH</th>
  <th rowspan="2" class="yellow">BIAYA PEMAKAIAN LISTRIK PER DAY (Rp)</th>
  <th class="yellow">EFISIENSI</th>
  <th class="yellow">KVA PLN</th>
</tr>
<tr>
  <th class="sub-meter">LVBP (kWh)</th>
  <th class="sub-meter">VBP (kWh)</th>
  <th class="sub-meter">KVAR / ER.EXP</th>
  <th class="sub-meter">Cos &theta;</th>
  <th class="yellow">PERSEN</th>
  <th class="yellow">PER JAM</th>
</tr>
<tr>
  <th class="sub-meter">EA.EXP 2</th>
  <th class="sub-meter">EA.EXP 1</th>
  <th class="sub-meter"></th>
  <th class="sub-meter"></th>
  <th class="yellow"></th>
  <th class="yellow"></th>
  <th class="yellow"></th>
  <th class="yellow"></th>
  <th class="yellow"></th>
  <th class="yellow"></th>
</tr>
</thead>
<tbody>';

if (empty($rows)) {
    $html .= '<tr><td colspan="11">Tidak ada data pada rentang tanggal ini.</td></tr>';
} else {
    foreach ($rows as $r) {
        $html .= '<tr>';
        $html .= '<td class="datecol">' . $esc($fmtDay($r['tanggal'])) . '</td>';
        $html .= '<td class="data-blue txt-right">' . $esc($fmt($r['lvbp_kwh'], 3)) . '</td>';
        $html .= '<td class="data-blue txt-right">' . $esc($fmt($r['vbp_kwh'], 3)) . '</td>';
        $html .= '<td class="data-blue txt-right">' . $esc($fmt($r['kvarh'], 3)) . '</td>';
        $html .= '<td class="data-blue txt-right">' . $esc($fmt($r['cos_phi'], 3)) . '</td>';
        $html .= '<td class="data-yellow txt-right">' . $esc($fmt($r['total_daya_perday_kw'], 2)) . '</td>';
        $html .= '<td class="data-yellow txt-right">' . $esc($fmt($r['total_daya_perjam_kwh'], 2)) . '</td>';
        $html .= '<td class="data-yellow txt-right">' . $esc($fmt($r['rp_per_kwh'], 3)) . '</td>';
        $html .= '<td class="data-cost txt-right">' . $esc($fmt($r['biaya_perday_rp'], 2)) . '</td>';
        $html .= '<td class="data-yellow txt-right">' . $esc($fmt($r['efisiensi_persen'], 2)) . '</td>';
        $html .= '<td class="data-yellow txt-right">' . $esc($fmt($r['kva_pln_perjam'], 2)) . '</td>';
        $html .= '</tr>';
    }

    $html .= '<tr>';
    $html .= '<td class="sum">TOTAL</td><td class="sum"></td><td class="sum"></td><td class="sum"></td><td class="sum"></td>';
    $html .= '<td class="sum txt-right">' . $esc($fmt($tot['total_daya_perday_kw'], 2)) . '</td>';
    $html .= '<td class="sum txt-right">' . $esc($fmt($tot['total_daya_perjam_kwh'], 2)) . '</td>';
    $html .= '<td class="sum"></td>';
    $html .= '<td class="sum-yellow txt-right">' . $esc($fmt($tot['biaya_perday_rp'], 2)) . '</td>';
    $html .= '<td class="sum txt-right">' . $esc($fmt($tot['efisiensi_persen'], 2)) . '</td>';
    $html .= '<td class="sum txt-right">' . $esc($fmt($tot['kva_pln_perjam'], 2)) . '</td>';
    $html .= '</tr>';

    $html .= '<tr>';
    $html .= '<td class="sum">RATA-RATA</td><td class="sum"></td><td class="sum"></td><td class="sum"></td><td class="sum"></td>';
    $html .= '<td class="sum txt-right">' . $esc($fmt($avg['total_daya_perday_kw'], 2)) . '</td>';
    $html .= '<td class="sum txt-right">' . $esc($fmt($avg['total_daya_perjam_kwh'], 2)) . '</td>';
    $html .= '<td class="sum"></td>';
    $html .= '<td class="sum-yellow txt-right">' . $esc($fmt($avg['biaya_perday_rp'], 2)) . '</td>';
    $html .= '<td class="sum txt-right">' . $esc($fmt($avg['efisiensi_persen'], 2)) . '</td>';
    $html .= '<td class="sum txt-right">' . $esc($fmt($avg['kva_pln_perjam'], 2)) . '</td>';
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

$dompdf->stream('Report_listrik_gardu_induk_' . date('Ymd_His') . '.pdf', ['Attachment' => true]);
exit;
