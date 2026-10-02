<?php
session_start();
ob_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/vendor/autoload.php';
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/air_dryer_helper.php');

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
if (air_dryer_table_exists($conn)) {
    $sql = "SELECT Tanggal, Jam_Pengecekan, AirDryer1_Temp_In_C, AirDryer1_Temp_Out_C,
                AirDryer1_Tekanan_In_Bar, AirDryer1_Tekanan_Out_Bar,
                AirDryer2_Temp_In_C, AirDryer2_Temp_Out_C,
                AirDryer2_Tekanan_In_Bar, AirDryer2_Tekanan_Out_Bar, Petugas, CreatAt, Keterangan
            FROM dbo.air_dryer
            WHERE CAST(Tanggal AS DATE) BETWEEN ? AND ?
            ORDER BY CAST(Tanggal AS DATE) ASC, CAST(Jam_Pengecekan AS TIME) ASC, Id ASC";
    $stmt = sqlsrv_query($conn, $sql, [$start, $end]);
    if ($stmt === false) {
        while (ob_get_level() > 0) ob_end_clean();
        echo '<b>SQL error</b>: ' . esc_pdf(print_r(sqlsrv_errors(), true));
        exit;
    }
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $dateKey = air_dryer_fmt_date($row['Tanggal'] ?? null, 'Y-m-d');
        $hourKey = air_dryer_fmt_time($row['Jam_Pengecekan'] ?? null);
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
@page { margin: 7mm; size: A4 landscape; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8px; color:#000; }
.report-page { page-break-after: always; }
.report-page:last-child { page-break-after: auto; }
table { border-collapse: collapse; width: 100%; table-layout: fixed; }
th, td { border: 1px solid #000; text-align: center; vertical-align: middle; padding: 2px 3px; line-height: 1.1; }
.title { font-weight: 700; font-size: 11px; background: #fff; padding: 4px; }
.date { text-align: left; font-weight: 700; background: #fff; padding: 3px 5px; font-size: 9px; }
.doc-code { text-align: center; font-weight: 700; background: #fff; padding: 3px 5px; font-size: 8px; }
.head { background: #9dc3e6; font-weight: 700; font-size: 7px; }
.jam { width: 8%; }
.val { width: 9%; }
.petugas { width: 20%; white-space: nowrap; }
.empty { color: #666; }
</style></head><body>';

if (count($rowsByDate) === 0) {
    $html .= '<table><tr><th class="title">PENCATATAN AIR DRYER WEAVING</th></tr><tr><td class="empty">Tidak ada data.</td></tr></table>';
} else {
    foreach ($rowsByDate as $dateKey => $dateRows) {
        $html .= '<div class="report-page"><table>';
        $html .= '<colgroup><col class="jam"><col class="val"><col class="val"><col class="val"><col class="val"><col class="val"><col class="val"><col class="val"><col class="val"><col class="petugas"></colgroup>';
        $html .= '<tr><th colspan="10" class="title">PENCATATAN AIR DRYER WEAVING</th></tr>';
        $html .= '<tr><th colspan="9" class="date">TANGGAL : ' . esc_pdf(date('d/m/Y', strtotime($dateKey))) . '</th>';
        $html .= '<th class="doc-code">SUM-FM-THK-WV-017</th></tr>';
        $html .= '<tr><th class="head" rowspan="3">JAM<br>PENGECEKAN</th><th class="head" colspan="4">AIR DRYER 1</th><th class="head" colspan="4">AIR DRYER 2</th><th class="head" rowspan="3">PETUGAS</th></tr>';
        $html .= '<tr><th class="head" colspan="2">TEMPERATUR AIR</th><th class="head" colspan="2">TEKANAN AIR</th><th class="head" colspan="2">TEMPERATUR AIR</th><th class="head" colspan="2">TEKANAN AIR</th></tr>';
        $html .= '<tr><th class="head">IN (&deg;C)</th><th class="head">OUT (&deg;C)</th><th class="head">IN (BAR)</th><th class="head">OUT (BAR)</th><th class="head">IN (&deg;C)</th><th class="head">OUT (&deg;C)</th><th class="head">IN (BAR)</th><th class="head">OUT (BAR)</th></tr>';
        foreach (air_dryer_hours() as $hour) {
            $row = $dateRows[$hour] ?? [];
            $html .= '<tr>';
            $html .= '<td>' . esc_pdf($hour) . '</td>';
            $html .= '<td>' . esc_pdf(air_dryer_fmt_num($row['AirDryer1_Temp_In_C'] ?? null, 1)) . '</td>';
            $html .= '<td>' . esc_pdf(air_dryer_fmt_num($row['AirDryer1_Temp_Out_C'] ?? null, 1)) . '</td>';
            $html .= '<td>' . esc_pdf(air_dryer_fmt_num($row['AirDryer1_Tekanan_In_Bar'] ?? null, 1)) . '</td>';
            $html .= '<td>' . esc_pdf(air_dryer_fmt_num($row['AirDryer1_Tekanan_Out_Bar'] ?? null, 1)) . '</td>';
            $html .= '<td>' . esc_pdf(air_dryer_fmt_num($row['AirDryer2_Temp_In_C'] ?? null, 1)) . '</td>';
            $html .= '<td>' . esc_pdf(air_dryer_fmt_num($row['AirDryer2_Temp_Out_C'] ?? null, 1)) . '</td>';
            $html .= '<td>' . esc_pdf(air_dryer_fmt_num($row['AirDryer2_Tekanan_In_Bar'] ?? null, 1)) . '</td>';
            $html .= '<td>' . esc_pdf(air_dryer_fmt_num($row['AirDryer2_Tekanan_Out_Bar'] ?? null, 1)) . '</td>';
            $html .= '<td class="petugas">' . esc_pdf($row['Petugas'] ?? '') . '</td>';
            $html .= '</tr>';
        }
        $html .= '<tr>';
        $html .= '<td class="head" style="background:#b7b7b7; font-weight:700;">KETERANGAN</td>';
        $html .= '<td colspan="9" style="text-align:left; padding:3px 6px; font-size:8px;">' . esc_pdf(!empty($keteranganByDate[$dateKey]) ? implode('; ', $keteranganByDate[$dateKey]) : '-') . '</td>';
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
$dompdf->stream('Report_Air_Dryer_' . date('Ymd_His') . '.pdf', ['Attachment' => false]);
exit;
