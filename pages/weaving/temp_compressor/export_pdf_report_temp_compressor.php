<?php
session_start();
ob_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/vendor/autoload.php';
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/temp_compressor_helper.php');

weaving_require($conn, 'CanView');

use Dompdf\Dompdf;

function esc_pdf($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$start = $_POST['start_date'] ?? date('Y-m-d');
$end = $_POST['end_date'] ?? $start;
if (strtotime($start) > strtotime($end)) [$start, $end] = [$end, $start];

$rowsByDate = [];
$keteranganByDate = [];
if (temp_compressor_table_exists($conn)) {
    $sql = "SELECT Tanggal, Jam, Compressor1_In_C, Compressor1_Out_C, Compressor2_In_C, Compressor2_Out_C,
                   Amper, PressureBar_P1, PressureBar_P2,
                   Temperature_T1, Temperature_T2, Temperature_T3,
                   Dryer_C, TekananAir_In, TekananAir_Out,
                   Petugas, Keterangan
            FROM dbo.temp_compressor
            WHERE CAST(Tanggal AS DATE) BETWEEN ? AND ?
            ORDER BY CAST(Tanggal AS DATE) ASC, CAST(Jam AS TIME) ASC, Id ASC";
    $stmt = sqlsrv_query($conn, $sql, [$start, $end]);
    if ($stmt === false) {
        while (ob_get_level() > 0) ob_end_clean();
        echo '<b>SQL error</b>: ' . esc_pdf(print_r(sqlsrv_errors(), true));
        exit;
    }
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $dateKey = temp_compressor_fmt_date($row['Tanggal'] ?? null, 'Y-m-d');
        $hourKey = temp_compressor_fmt_time($row['Jam'] ?? null);
        if ($dateKey === '' || $hourKey === '') continue;
        if (!isset($rowsByDate[$dateKey])) $rowsByDate[$dateKey] = [];
        $rowsByDate[$dateKey][$hourKey] = $row;
        $ket = trim((string)($row['Keterangan'] ?? ''));
        if ($ket !== '') {
            if (!isset($keteranganByDate[$dateKey])) $keteranganByDate[$dateKey] = [];
            if (!in_array($ket, $keteranganByDate[$dateKey], true)) {
                $keteranganByDate[$dateKey][] = $ket;
            }
        }
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
}

$html = '<!doctype html><html><head><meta charset="UTF-8"><style>
@page { margin: 9mm 8mm; size: A4 landscape; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8px; color:#000; }
.report-page { page-break-after: always; }
.report-page:last-child { page-break-after: auto; }
table { border-collapse: collapse; width: 100%; table-layout: fixed; }
th, td { border: 1px solid #000; text-align: center; vertical-align: middle; padding: 2px 3px; line-height: 1.15; }
.title { font-weight: 700; font-size: 11px; background: #fff; padding: 4px; }
.date { text-align: left; font-weight: 700; background: #fff; padding: 4px 6px; }
.doc-code { text-align: center; font-weight: 700; background: #fff; padding: 4px 6px; }
.head { background: #9dc3e6; font-weight: 700; font-size: 7px; }
.petugas { text-align: left; }
.empty { color: #666; }
</style></head><body>';

if (count($rowsByDate) === 0) {
    $html .= '<table>';
    $html .= '<tr><th colspan="15" class="title">CHECK SHEET KOMPRESSOR SULLAIR</th></tr>';
    $html .= '<tr><td colspan="15" class="empty">Tidak ada data.</td></tr>';
    $html .= '</table>';
} else {
    foreach ($rowsByDate as $dateKey => $dateRows) {
        $html .= '<div class="report-page"><table>';
        $html .= '<colgroup>'
            . '<col style="width:6%"><col style="width:5%"><col style="width:5%"><col style="width:5%"><col style="width:5%">'
            . '<col style="width:5%"><col style="width:5%"><col style="width:5%">'
            . '<col style="width:5%"><col style="width:5%"><col style="width:5%">'
            . '<col style="width:6%"><col style="width:5%"><col style="width:5%">'
            . '<col style="width:18%">'
            . '</colgroup>';
        $html .= '<tr><th colspan="15" class="title">CHECK SHEET KOMPRESSOR SULLAIR</th></tr>';
        $html .= '<tr><th colspan="14" class="date">TANGGAL : ' . esc_pdf(date('d/m/Y', strtotime($dateKey))) . '</th>';
        $html .= '<th class="doc-code">SUM-FM-THK-016</th></tr>';
        $html .= '<tr>'
            . '<th class="head" rowspan="2">JAM</th>'
            . '<th class="head" colspan="2">COMPRESSOR 1</th>'
            . '<th class="head" colspan="2">COMPRESSOR 2</th>'
            . '<th class="head" rowspan="2">AMPER</th>'
            . '<th class="head" colspan="2">PRESSURE BAR</th>'
            . '<th class="head" colspan="3">TEMPERATURE &deg;C</th>'
            . '<th class="head" rowspan="2">DRYER &deg;C</th>'
            . '<th class="head" colspan="2">TEKANAN AIR</th>'
            . '<th class="head" rowspan="2">PETUGAS</th>'
            . '</tr>';
        $html .= '<tr>'
            . '<th class="head">IN &deg;C</th><th class="head">OUT &deg;C</th>'
            . '<th class="head">IN &deg;C</th><th class="head">OUT &deg;C</th>'
            . '<th class="head">P1</th><th class="head">P2</th>'
            . '<th class="head">T1</th><th class="head">T2</th><th class="head">T3</th>'
            . '<th class="head">IN</th><th class="head">OUT</th>'
            . '</tr>';
        foreach (temp_compressor_hours() as $hour) {
            $row = $dateRows[$hour] ?? [];
            $html .= '<tr>';
            $html .= '<td>' . esc_pdf(temp_compressor_display_hour($hour)) . '</td>';
            $html .= '<td>' . esc_pdf(temp_compressor_fmt_num($row['Compressor1_In_C'] ?? null, 0)) . '</td>';
            $html .= '<td>' . esc_pdf(temp_compressor_fmt_num($row['Compressor1_Out_C'] ?? null, 0)) . '</td>';
            $html .= '<td>' . esc_pdf(temp_compressor_fmt_num($row['Compressor2_In_C'] ?? null, 0)) . '</td>';
            $html .= '<td>' . esc_pdf(temp_compressor_fmt_num($row['Compressor2_Out_C'] ?? null, 0)) . '</td>';
            $html .= '<td>' . esc_pdf(temp_compressor_fmt_num($row['Amper'] ?? null, 0)) . '</td>';
            $html .= '<td>' . esc_pdf(temp_compressor_fmt_num($row['PressureBar_P1'] ?? null, 1)) . '</td>';
            $html .= '<td>' . esc_pdf(temp_compressor_fmt_num($row['PressureBar_P2'] ?? null, 1)) . '</td>';
            $html .= '<td>' . esc_pdf(temp_compressor_fmt_num($row['Temperature_T1'] ?? null, 1)) . '</td>';
            $html .= '<td>' . esc_pdf(temp_compressor_fmt_num($row['Temperature_T2'] ?? null, 1)) . '</td>';
            $html .= '<td>' . esc_pdf(temp_compressor_fmt_num($row['Temperature_T3'] ?? null, 1)) . '</td>';
            $html .= '<td>' . esc_pdf(temp_compressor_fmt_num($row['Dryer_C'] ?? null, 0)) . '</td>';
            $html .= '<td>' . esc_pdf(temp_compressor_fmt_num($row['TekananAir_In'] ?? null, 1)) . '</td>';
            $html .= '<td>' . esc_pdf(temp_compressor_fmt_num($row['TekananAir_Out'] ?? null, 1)) . '</td>';
            $html .= '<td class="petugas">' . esc_pdf($row['Petugas'] ?? '') . '</td>';
            $html .= '</tr>';
        }
        $html .= '<tr>';
        $html .= '<td class="head" style="background:#b7b7b7; font-weight:700;">KETERANGAN</td>';
        $html .= '<td colspan="14" style="text-align:left; padding:3px 6px; font-size:7.5px;">' . esc_pdf(!empty($keteranganByDate[$dateKey]) ? implode('; ', $keteranganByDate[$dateKey]) : '-') . '</td>';
        $html .= '</tr>';
        $html .= '</table></div>';
    }
}
$html .= '</body></html>';

while (ob_get_level() > 0) ob_end_clean();
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream('Check_Sheet_Kompressor_Sullair_' . date('Ymd_His') . '.pdf', ['Attachment' => false]);
exit;
