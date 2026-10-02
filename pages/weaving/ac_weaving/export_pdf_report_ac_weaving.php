<?php
session_start();
ob_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
require_once __DIR__ . '/../weaving_permissions.php';
require_once __DIR__ . '/ac_weaving_helper.php';

weaving_require($conn, 'CanView');

use Dompdf\Dompdf;

$normalizeDate = function ($value) {
    $value = trim((string)$value);
    if ($value === '') return '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
    if (preg_match('/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/', $value, $m)) return $m[3] . '-' . $m[2] . '-' . $m[1];
    return '';
};

$start = $normalizeDate($_POST['start_date'] ?? date('Y-m-d')) ?: date('Y-m-d');
$end = $normalizeDate($_POST['end_date'] ?? $start) ?: $start;
if (strtotime($start) > strtotime($end)) {
    [$start, $end] = [$end, $start];
}

function fmt_date_report_pdf($value)
{
    if ($value instanceof DateTime) return $value->format('d/m/Y');
    if (is_string($value) && $value !== '') {
        $ts = strtotime($value);
        return $ts ? date('d/m/Y', $ts) : $value;
    }
    return '';
}

function fmt_time_report_pdf($value)
{
    if ($value instanceof DateTime) $time = $value->format('H:i');
    elseif (is_string($value) && $value !== '') {
        $ts = strtotime($value);
        $time = $ts ? date('H:i', $ts) : substr($value, 0, 5);
    } else {
        return '';
    }
    return $time;
}

function fmt_num_report_pdf($value, $decimals = 2)
{
    if ($value === null || $value === '') return '';
    if (!is_numeric($value)) return (string)$value;
    return number_format((float)$value, $decimals, '.', '');
}

function format_indo_date_range($start, $end)
{
    $bulanIndo = [
        1 => 'JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI',
        'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER'
    ];
    $tsStart = strtotime($start);
    $tsEnd = strtotime($end);
    if (!$tsStart) return $start;
    $d1 = (int)date('d', $tsStart);
    $m1 = $bulanIndo[(int)date('n', $tsStart)] ?? strtoupper(date('F', $tsStart));
    $y1 = date('Y', $tsStart);
    if ($start === $end || !$tsEnd) {
        return "$d1 $m1 $y1";
    }
    $d2 = (int)date('d', $tsEnd);
    $m2 = $bulanIndo[(int)date('n', $tsEnd)] ?? strtoupper(date('F', $tsEnd));
    $y2 = date('Y', $tsEnd);
    if ($y1 === $y2 && $m1 === $m2) {
        return "$d1 - $d2 $m1 $y1";
    }
    return "$d1 $m1 $y1 - $d2 $m2 $y2";
}

function esc_pdf($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$res = ac_weaving_load_report_data($conn, $start, $end);
if ($res === false) {
    while (ob_get_level() > 0) ob_end_clean();
    echo '<b>SQL error</b>: ' . htmlspecialchars(print_r(sqlsrv_errors(), true));
    exit;
}
[$reportRows, $reportKeterangan] = $res;

$dateLabel = format_indo_date_range($start, $end);

$renderTable = function ($machine, $rows, $dateLabel, $keteranganText = '') {
    $html = "<table class='ac-report'>";
    $html .= "<colgroup>";
    $html .= "<col style='width: 11%;'>";
    $html .= "<col style='width: 8%;'>";
    $html .= "<col style='width: 10%;'>";
    $html .= "<col style='width: 9%;'>";
    $html .= "<col style='width: 8%;'>";
    $html .= "<col style='width: 14%;'>";
    $html .= "<col style='width: 31%;'>";
    $html .= "<col style='width: 9%;'>";
    $html .= "</colgroup>";
    $html .= "<thead>";
    $html .= "<tr><th class='sheet-title' colspan='8'>PENGECEKAN TEMPERATUR AREA WEAVING</th></tr>";
    $html .= "<tr><th class='date' colspan='6'>TANGGAL : " . esc_pdf($dateLabel) . "</th><th class='doc-code' colspan='2'>SUM-FM-THK-WV-005</th></tr>";
    $html .= "<tr><th class='machine-title' colspan='8'>" . esc_pdf($machine) . "</th></tr>";
    $html .= "<tr>";
    $html .= "<th class='head'>TANGGAL</th><th class='head'>JAM</th>";
    $html .= "<th class='head'>pB1 Dew<br>Point</th><th class='head'>HUMIDITY</th><th class='head'>AMPER</th>";
    $html .= "<th class='head'>DEFFERENTIAL<br>BEST AIR</th><th class='head'>PETUGAS</th><th class='head'>SHIFT</th>";
    $html .= "</tr>";
    $html .= "</thead>";
    $html .= "<tbody>";
    if (count($rows) === 0) {
        $html .= "<tr><td colspan='8'>Tidak ada data.</td></tr>";
    } else {
        foreach ($rows as $row) {
            $html .= "<tr>";
            $html .= "<td>" . esc_pdf($row['tanggal'] ?? '') . "</td>";
            $html .= "<td>" . esc_pdf($row['jam'] ?? '') . "</td>";
            $html .= "<td>" . esc_pdf($row['dew_point'] ?? '') . "</td>";
            $html .= "<td>" . esc_pdf($row['humidity'] ?? '') . "</td>";
            $html .= "<td>" . esc_pdf($row['amper'] ?? '') . "</td>";
            $html .= "<td>" . esc_pdf($row['differential'] ?? '') . "</td>";
            $html .= "<td class='text-left'>" . esc_pdf($row['petugas'] ?? '') . "</td>";
            $html .= "<td>" . esc_pdf($row['shift'] ?? '') . "</td>";
            $html .= "</tr>";
        }
    }
    $html .= "</tbody>";
    $html .= "</table>";
    $html .= "<div style='margin-top: 8px; font-size: 8.5px; text-align: left;'><strong>Keterangan :</strong> " . esc_pdf($keteranganText !== '' ? $keteranganText : '-') . "</div>";
    return $html;
};

$html = '<!doctype html>
<html>
<head>
<meta charset="UTF-8">
<style>
@page { margin: 8mm; size: A4 landscape; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8px; color: #000; }
.report-page { page-break-after: always; }
.report-page:last-child { page-break-after: auto; }
.ac-report { border-collapse: collapse; border-spacing: 0; background: #fff; width: 100%; table-layout: fixed; }
.ac-report th, .ac-report td { border: 1px solid #000; text-align: center; vertical-align: middle; padding: 2px 3px; line-height: 1.15; }
.ac-report th { white-space: nowrap; }
.ac-report td { word-wrap: break-word; }
.ac-report .sheet-title { background: #fff; text-align: center; font-weight: 700; font-size: 10px; padding: 3px; }
.ac-report .date { background: #fff; text-align: left; font-weight: 700; font-size: 8px; }
.ac-report .doc-code { background: #fff; text-align: center; font-weight: 700; font-size: 8px; }
.ac-report .machine-title { background: #fff; text-align: left; font-weight: 700; font-size: 9px; }
.ac-report .head { background: #9dc3e6; font-weight: 700; color: #000; font-size: 7.5px; }
.ac-report .text-left { text-align: left; padding-left: 5px; }
</style>
</head>
<body>';
$html .= '<div class="report-page">';
$html .= $renderTable('AC WEAVING 1', $reportRows['AC WEAVING 1'], $dateLabel, !empty($reportKeterangan['AC WEAVING 1']) ? implode('; ', $reportKeterangan['AC WEAVING 1']) : '-');
$html .= '</div>';
$html .= '<div class="report-page">';
$html .= $renderTable('AC WEAVING 2', $reportRows['AC WEAVING 2'], $dateLabel, !empty($reportKeterangan['AC WEAVING 2']) ? implode('; ', $reportKeterangan['AC WEAVING 2']) : '-');
$html .= '</div>';
$html .= '</body></html>';

while (ob_get_level() > 0) ob_end_clean();
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream('Report_AC_Weaving_' . date('Ymd_His') . '.pdf', ['Attachment' => false]);
exit;
