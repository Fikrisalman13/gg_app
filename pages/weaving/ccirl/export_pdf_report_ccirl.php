<?php
session_start();
ob_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/vendor/autoload.php';
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/ccirl_helper.php');

weaving_require($conn, 'CanView');

use Dompdf\Dompdf;

function esc_pdf($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$start = $_POST['start_date'] ?? date('Y-m-d');
$end = $_POST['end_date'] ?? $start;
$compressorNo = trim($_POST['compressor_no'] ?? '');
if (strtotime($start) > strtotime($end)) [$start, $end] = [$end, $start];

$items = ccirl_items();
$hours = ccirl_hours();
$sheets = [];
if (ccirl_table_exists($conn)) {
    $whereExtra = '';
    $params = [$start, $end];
    if ($compressorNo !== '') {
        $whereExtra = ' AND Compressor_No = ?';
        $params[] = $compressorNo;
    }
    $sql = "SELECT CAST(Tanggal AS DATE) AS Tanggal, Compressor_No
            FROM dbo.ccirl
            WHERE CAST(Tanggal AS DATE) BETWEEN ? AND ? $whereExtra
            GROUP BY CAST(Tanggal AS DATE), Compressor_No
            ORDER BY CAST(Tanggal AS DATE) ASC, Compressor_No ASC";
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        while (ob_get_level() > 0) ob_end_clean();
        echo '<b>SQL error</b>: ' . esc_pdf(print_r(sqlsrv_errors(), true));
        exit;
    }
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $dateKey = ccirl_fmt_date($row['Tanggal'] ?? null, 'Y-m-d');
        $row['Cells'] = ccirl_get_cells($conn, $dateKey, $row['Compressor_No']);
        $row['Keterangan'] = ccirl_keterangan_for_sheet_query($conn, $dateKey, $row['Compressor_No']);
        $sheets[] = $row;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
}

$html = '<!doctype html><html><head><meta charset="UTF-8"><style>
@page { margin: 6mm; size: A4 landscape; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 5.8px; color:#000; }
.page { page-break-after: always; }
.page:last-child { page-break-after: auto; }
table { border-collapse: collapse; width: 100%; table-layout: fixed; }
th,td { border:1px solid #000; text-align:center; vertical-align:middle; padding:1px 2px; line-height:1.05; }
.title { font-weight:700; font-size:8px; background:#fff; }
.date { text-align:left; font-weight:700; background:#fff; font-size:7px; }
.doc-code { text-align:right; font-weight:700; background:#fff; font-size:7px; }
.head,.status,.petugas { background:#d9d9d9; font-weight:700; }
.status { text-align:left; width: 20%; }
.hour { width: 3.333%; }
</style></head><body>';

if (count($sheets) === 0) {
    $html .= '<table><tr><td>Tidak ada data.</td></tr></table>';
} else {
    foreach ($sheets as $sheet) {
        $html .= '<div class="page"><table>';
        $html .= '<tr><th colspan="' . (1 + count($hours)) . '" class="title">CENTAC COMPRESSOR INGERSOLL RAND LOG SHEET</th></tr>';
        $html .= '<tr><th class="date">COMPRESSOR NO : ' . esc_pdf($sheet['Compressor_No'] ?? '') . '</th>';
        $html .= '<th colspan="' . count($hours) . '" class="doc-code">SUM-FM-THK-WV-013</th></tr>';
        $html .= '<tr><th colspan="' . (1 + count($hours)) . '" class="date">TANGGAL : ' . esc_pdf(ccirl_fmt_date($sheet['Tanggal'] ?? null, 'd/m/Y')) . '</th></tr>';
        $html .= '<tr><th class="head" rowspan="2">STATUS MESSAGE</th><th class="head" colspan="' . count($hours) . '">JAM PEMERIKSAAN</th></tr><tr>';
        foreach ($hours as $hour) $html .= '<th class="head hour">' . esc_pdf(ccirl_display_hour($hour)) . '</th>';
        $html .= '</tr>';
        foreach ($items as $item) {
            $html .= '<tr><td class="status">' . $item['label'] . '</td>';
            foreach ($hours as $hour) {
                $key = $item['key'] . '|' . $hour;
                $html .= '<td>' . esc_pdf($sheet['Cells'][$key]['nilai'] ?? '') . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '<tr><td class="petugas">PETUGAS</td>';
        foreach ($hours as $hour) {
            $petugas = ccirl_petugas_for_hour($sheet['Cells'], $items, $hour);
            $html .= '<td>' . esc_pdf($petugas) . '</td>';
        }
        $html .= '</tr>';
        $html .= '<tr><td class="petugas">KETERANGAN</td>';
        $html .= '<td colspan="' . count($hours) . '" style="text-align:left; padding:2px 4px; background:#fff; font-weight:normal;">' . esc_pdf(!empty($sheet['Keterangan']) ? $sheet['Keterangan'] : '-') . '</td></tr>';
        $html .= '</table></div>';
    }
}
$html .= '</body></html>';

while (ob_get_level() > 0) ob_end_clean();
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream('Report_CCIRL_' . date('Ymd_His') . '.pdf', ['Attachment' => false]);
exit;
