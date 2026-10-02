<?php
session_start();
ob_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/vendor/autoload.php';
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/dryer_weaving_helper.php');

weaving_require($conn, 'CanView');

use Dompdf\Dompdf;

function esc_pdf($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$start = $_POST['start_date'] ?? date('Y-m-d');
$end = $_POST['end_date'] ?? $start;
$dryerNoFilter = trim($_POST['dryer_no'] ?? '');
$ctNoFilter = trim($_POST['ct_no'] ?? '');
if (strtotime($start) > strtotime($end)) [$start, $end] = [$end, $start];

$items = dryer_weaving_items();
$hours = dryer_weaving_hours();
$sheets = [];

if (dryer_weaving_table_exists($conn)) {
    $whereExtra = '';
    $params = [$start, $end];
    if ($dryerNoFilter !== '') {
        $whereExtra .= ' AND Dryer_No = ?';
        $params[] = $dryerNoFilter;
    }
    if ($ctNoFilter !== '') {
        $whereExtra .= ' AND Ct_No = ?';
        $params[] = $ctNoFilter;
    }

    $sql = "SELECT CAST(Tanggal AS DATE) AS Tanggal, Dryer_No, Ct_No, MAX([Shift]) AS ShiftName
            FROM dbo.dryer_weaving
            WHERE CAST(Tanggal AS DATE) BETWEEN ? AND ?
            $whereExtra
            GROUP BY CAST(Tanggal AS DATE), Dryer_No, Ct_No
            ORDER BY CAST(Tanggal AS DATE) ASC, Dryer_No ASC, Ct_No ASC";
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        while (ob_get_level() > 0) ob_end_clean();
        echo '<b>SQL error</b>: ' . esc_pdf(print_r(sqlsrv_errors(), true));
        exit;
    }
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $tanggal = dryer_weaving_fmt_date($row['Tanggal'] ?? null, 'Y-m-d');
        $row['Cells'] = dryer_weaving_get_sheet_cells($conn, $tanggal, $row['Dryer_No'], $row['Ct_No']);
        $row['Shifts'] = dryer_weaving_shifts_for_sheet($row['Cells']);
        $row['PetugasByHour'] = dryer_weaving_petugas_by_hour_for_sheet($row['Cells']);
        $row['Keterangan'] = dryer_weaving_keterangan_for_sheet_query($conn, $tanggal, $row['Dryer_No'], $row['Ct_No']);
        $sheets[] = $row;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
}

$renderSheet = function ($sheet) use ($items, $hours) {
    $html = "<div class='report-page'>";
    $html .= "<table class='dryer-report'>";
    $html .= "<tr><th colspan='" . (2 + count($hours)) . "' class='title'>LOG SHEET PERSHIFT DRYER D IN - W DAN COOLING TOWER (CT) INGERSOLL RAND</th></tr>";
    $html .= "<tr class='info'><th colspan='2' style='text-align:left;'>TANGGAL : " . esc_pdf(dryer_weaving_fmt_date($sheet['Tanggal'] ?? null, 'd/m/Y')) . "</th>";
    $html .= "<th colspan='8' style='text-align:center;'>DRYER NO : " . esc_pdf($sheet['Dryer_No'] ?? '') . "</th>";
    $html .= "<th colspan='8' style='text-align:center;'>CT NO : " . esc_pdf($sheet['Ct_No'] ?? '') . "</th>";
    $html .= "<th colspan='8' style='text-align:center;'>SUM-FM-THK-WV-006</th></tr>";
    $html .= "<tr><th class='head' rowspan='2'>ITEM CHECK</th><th class='head' rowspan='2'>STANDARD</th><th class='head' colspan='" . count($hours) . "'>JAM PEMERIKSAAN</th></tr><tr>";
    foreach ($hours as $hour) $html .= "<th class='head'>" . esc_pdf(dryer_weaving_display_hour($hour)) . "</th>";
    $html .= "</tr>";
    $currentCategory = '';
    foreach ($items as $item) {
        if ($item['category'] !== $currentCategory) {
            $currentCategory = $item['category'];
            $html .= "<tr><td class='cat' colspan='" . (2 + count($hours)) . "'>" . esc_pdf($currentCategory) . "</td></tr>";
        }
        $html .= "<tr><td class='item'>" . $item['item'] . "</td><td class='standard'>" . esc_pdf($item['standard']) . "</td>";
        foreach ($hours as $hour) {
            $key = $item['key'] . '|' . $hour;
            $html .= "<td>" . esc_pdf($sheet['Cells'][$key]['nilai'] ?? '') . "</td>";
        }
        $html .= "</tr>";
    }
    $html .= "<tr><td class='cat' colspan='2' style='text-align:center;'>SHIFT</td>";
    foreach ($hours as $hour) {
        $html .= "<td>" . esc_pdf($sheet['Shifts'][$hour] ?? '') . "</td>";
    }
    $html .= "</tr>";
    $html .= "<tr><td class='cat' colspan='2' style='text-align:center;'>PETUGAS</td>";
    foreach ($hours as $hour) {
        $html .= "<td style='font-size: 5px; word-wrap: break-word;'>" . esc_pdf($sheet['PetugasByHour'][$hour] ?? '') . "</td>";
    }
    $html .= "</tr>";
    $ketText = !empty($sheet['Keterangan']) ? $sheet['Keterangan'] : '-';
    $html .= "<tr><td class='cat' colspan='2' style='text-align:center;'>KETERANGAN</td>";
    $html .= "<td colspan='" . count($hours) . "' style='text-align:left; padding:3px 5px; font-size:7px; background:#fff;'>" . esc_pdf($ketText) . "</td>";
    $html .= "</tr>";
    $html .= "</table></div>";
    return $html;
};

$html = '<!doctype html><html><head><meta charset="UTF-8"><style>
@page { margin: 7mm; size: A4 landscape; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 7px; color: #000; }
.report-page { page-break-after: always; }
.report-page:last-child { page-break-after: auto; }
.dryer-report { width: 100%; border-collapse: collapse; table-layout: fixed; }
.dryer-report th, .dryer-report td { border: 1px solid #000; text-align: center; vertical-align: middle; padding: 2px 2px; line-height: 1.1; }
.dryer-report .title { font-size: 10px; font-weight: 700; background: #fff; }
.dryer-report .info th { text-align: left; font-size: 8px; background: #fff; }
.dryer-report .head { background: #9dc3e6; font-weight: 700; font-size: 6px; }
.dryer-report .cat { background: #b7b7b7; font-weight: 700; text-align: left; }
.dryer-report .item { text-align: left; width: 70px; }
.dryer-report .standard { width: 52px; }
</style></head><body>';

if (count($sheets) === 0) {
    $html .= '<div>Tidak ada data.</div>';
} else {
    foreach ($sheets as $sheet) $html .= $renderSheet($sheet);
}
$html .= '</body></html>';

while (ob_get_level() > 0) ob_end_clean();
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream('Report_Dryer_Weaving_' . date('Ymd_His') . '.pdf', ['Attachment' => false]);
exit;
