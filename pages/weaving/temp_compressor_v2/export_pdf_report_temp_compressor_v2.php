<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/temp_compressor_v2_helper.php');

$permissions = weaving_require($conn, 'CanView');
$canEdit = weaving_can($permissions, 'CanEdit');
$canDelete = weaving_can($permissions, 'CanDelete');
if (!$canEdit && !$canDelete) {
    header('Location: temp_compressor_v2.php');
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/vendor/autoload.php';
use Dompdf\Dompdf;

$start = trim($_POST['start_date'] ?? $_GET['start_date'] ?? date('Y-m-d'));
$end = trim($_POST['end_date'] ?? $_GET['end_date'] ?? date('Y-m-d'));
$weavingFilter = intval($_POST['weaving'] ?? $_GET['weaving'] ?? 0);
$compressorFilter = intval($_POST['compressor_no'] ?? $_GET['compressor_no'] ?? 0);

$whereParts = ['CAST(Tanggal AS DATE) >= ?', 'CAST(Tanggal AS DATE) <= ?'];
$params = [$start, $end];

if (in_array($weavingFilter, [1, 2], true)) {
    $whereParts[] = 'Weaving = ?';
    $params[] = $weavingFilter;
}
if (in_array($compressorFilter, [1, 2, 3], true)) {
    $whereParts[] = 'Compressor_No = ?';
    $params[] = $compressorFilter;
}

$whereClause = implode(' AND ', $whereParts);

$sheetListSql = "SELECT CAST(Tanggal AS DATE) AS Tanggal, Weaving, Compressor_No
                 FROM dbo.temp_compressor_v2
                 WHERE $whereClause
                 GROUP BY CAST(Tanggal AS DATE), Weaving, Compressor_No
                 ORDER BY CAST(Tanggal AS DATE) ASC, Weaving ASC, Compressor_No ASC";
$sheetStmt = sqlsrv_query($conn, $sheetListSql, $params);

$sheetsData = [];
if ($sheetStmt) {
    while ($sRow = sqlsrv_fetch_array($sheetStmt, SQLSRV_FETCH_ASSOC)) {
        $tglStr = temp_compressor_v2_fmt_date($sRow['Tanggal'] ?? null, 'Y-m-d');
        $w = (int)$sRow['Weaving'];
        $c = (int)$sRow['Compressor_No'];

        $cells = temp_compressor_v2_get_sheet_cells($conn, $tglStr, $w, $c);
        $ket = temp_compressor_v2_keterangan_for_sheet_query($conn, $tglStr, $w, $c);

        $sheetsData[] = [
            'tanggal'       => $tglStr,
            'tanggal_disp'  => temp_compressor_v2_fmt_date($sRow['Tanggal'] ?? null, 'd/m/Y'),
            'weaving'       => $w,
            'compressor_no' => $c,
            'cells'         => $cells,
            'keterangan'    => $ket,
        ];
    }
    sqlsrv_free_stmt($sheetStmt);
}

function esc_pdf($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

$hours = temp_compressor_v2_hours();

$html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Check Sheet Kompressor Sullair V2</title><style>
@page { size: A4 landscape; margin: 8mm; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8px; color: #111; margin: 0; padding: 0; }
.report-page { page-break-after: always; margin-bottom: 12px; }
.report-page:last-child { page-break-after: auto; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 8px; }
th, td { border: 0.5px solid #222; text-align: center; vertical-align: middle; padding: 2px 3px; font-size: 7.5px; height: 14px; }
.title { font-size: 11px; font-weight: bold; background: #fff; padding: 4px; letter-spacing: 0.5px; }
.meta { text-align: left; font-weight: bold; background: #fff; padding: 3px 5px; font-size: 8px; }
.meta-right { text-align: right; font-weight: bold; background: #fff; padding: 3px 5px; font-size: 8px; }
.head { background: #9dc3e6; font-weight: bold; font-size: 7px; text-transform: uppercase; }
.petugas { text-align: left; }
.ket-title { background: #b7b7b7; font-weight: bold; }
.ket-val { text-align: left; padding: 2px 6px; font-size: 7.5px; }
.empty { color: #666; padding: 12px; font-size: 9px; }
</style></head><body>';

if (count($sheetsData) === 0) {
    $html .= '<table>';
    $html .= '<tr><th colspan="13" class="title">CHECK SHEET KOMPRESSOR SULLAIR</th></tr>';
    $html .= '<tr><td colspan="13" class="empty">Tidak ada data untuk periode dan filter yang dipilih.</td></tr>';
    $html .= '</table>';
} else {
    foreach ($sheetsData as $sItem) {
        $html .= '<div class="report-page"><table>';
        $html .= '<colgroup>'
            . '<col style="width:6%"><col style="width:6%"><col style="width:6%">'
            . '<col style="width:6%"><col style="width:6%"><col style="width:6%">'
            . '<col style="width:7%"><col style="width:8%">'
            . '<col style="width:7%"><col style="width:7%"><col style="width:7%"><col style="width:7%">'
            . '<col style="width:19%">'
            . '</colgroup>';
        $html .= '<tr><th colspan="13" class="title">CHECK SHEET KOMPRESSOR SULLAIR</th></tr>';
        $html .= '<tr><th colspan="6" class="meta">COMPRESSOR NO: ' . $sItem['compressor_no'] . ' (1 / 2 / 3) &nbsp;&nbsp;&nbsp;&nbsp; WEAVING: ' . $sItem['weaving'] . ' (1 / 2)</th>';
        $html .= '<th colspan="5" class="meta" style="text-align:center;">TANGGAL: ' . esc_pdf($sItem['tanggal_disp']) . '</th>';
        $html .= '<th colspan="2" class="meta-right">SUM-FM-THK-016</th></tr>';
        $html .= '<tr>'
            . '<th class="head" rowspan="2">JAM</th>'
            . '<th class="head" colspan="2">PRESSURE (BAR)</th>'
            . '<th class="head" colspan="3">TEMPERATURE</th>'
            . '<th class="head" rowspan="2">DRYER<br>(&deg;C)</th>'
            . '<th class="head" rowspan="2">ARUS<br>LISTRIK (A)</th>'
            . '<th class="head" colspan="4">AIR COOLING</th>'
            . '<th class="head" rowspan="2">PELAKSANA</th>'
            . '</tr>';
        $html .= '<tr>'
            . '<th class="head">P1</th>'
            . '<th class="head">P2</th>'
            . '<th class="head">T1</th>'
            . '<th class="head">T2</th>'
            . '<th class="head">T3</th>'
            . '<th class="head">PRESS. IN</th>'
            . '<th class="head">PRESS. OUT</th>'
            . '<th class="head">TEMP. IN</th>'
            . '<th class="head">TEMP. OUT</th>'
            . '</tr>';

        foreach ($hours as $hour) {
            $c = $sItem['cells'][$hour] ?? [];
            $displayHour = temp_compressor_v2_display_hour($hour);
            $html .= '<tr>';
            $html .= '<td><strong>' . esc_pdf($displayHour) . '</strong></td>';
            $html .= '<td>' . esc_pdf($c['pressure_p1'] ?? '') . '</td>';
            $html .= '<td>' . esc_pdf($c['pressure_p2'] ?? '') . '</td>';
            $html .= '<td>' . esc_pdf($c['temp_t1'] ?? '') . '</td>';
            $html .= '<td>' . esc_pdf($c['temp_t2'] ?? '') . '</td>';
            $html .= '<td>' . esc_pdf($c['temp_t3'] ?? '') . '</td>';
            $html .= '<td>' . esc_pdf($c['dryer_c'] ?? '') . '</td>';
            $html .= '<td>' . esc_pdf($c['arus_a'] ?? '') . '</td>';
            $html .= '<td>' . esc_pdf($c['press_in'] ?? '') . '</td>';
            $html .= '<td>' . esc_pdf($c['press_out'] ?? '') . '</td>';
            $html .= '<td>' . esc_pdf($c['temp_in'] ?? '') . '</td>';
            $html .= '<td>' . esc_pdf($c['temp_out'] ?? '') . '</td>';
            $html .= '<td class="petugas">' . esc_pdf($c['pelaksana'] ?? '') . '</td>';
            $html .= '</tr>';
        }

        $html .= '<tr>';
        $html .= '<td class="ket-title">KETERANGAN</td>';
        $html .= '<td colspan="12" class="ket-val">' . esc_pdf($sItem['keterangan'] ?: '-') . '</td>';
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
$dompdf->stream('Check_Sheet_Kompressor_Sullair_V2_' . date('Ymd_His') . '.pdf', ['Attachment' => false]);
exit;
