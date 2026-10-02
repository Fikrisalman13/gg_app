<?php
session_start();
ob_start();

require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../libs/fpdf.php';

$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && (int)$permissions['CanView'] !== 1) {
    while (ob_get_level() > 0) ob_end_clean();
    exit('Anda tidak memiliki hak melihat data.');
}

$start = trim($_POST['start_date'] ?? date('Y-m-01'));
$end = trim($_POST['end_date'] ?? date('Y-m-d'));

$sql = "SELECT id, tanggal, temp_umpan_c, dh_std_lt1, tds_umpan_ms,
               CASE WHEN tds_umpan_ms IS NULL THEN NULL ELSE CAST(ROUND(tds_umpan_ms * 0.64, 2) AS DECIMAL(12,2)) END AS tds_umpan_ppm,
               ph_boiler, temp_boiler_c, tds_boiler_ms,
               CASE WHEN tds_boiler_ms IS NULL THEN NULL ELSE CAST(ROUND(tds_boiler_ms * 0.64, 2) AS DECIMAL(12,2)) END AS tds_boiler_ppm,
               blowdown_jumlah, keterangan,
               CASE WHEN tds_umpan_ms IS NULL THEN NULL ELSE CAST(ROUND(tds_umpan_ms / 1000.0, 2) AS DECIMAL(12,2)) END AS tds_umpan_display_ms,
               CASE WHEN tds_boiler_ms IS NULL THEN NULL ELSE CAST(ROUND(tds_boiler_ms / 1000.0, 2) AS DECIMAL(12,2)) END AS tds_boiler_display_ms
        FROM dbo.air_boiler_alstom_harian
        WHERE CAST(tanggal AS DATE) BETWEEN ? AND ?
        ORDER BY CAST(tanggal AS DATE) ASC, id ASC";
$stmt = sqlsrv_query($conn, $sql, [$start, $end]);
if ($stmt === false) {
    while (ob_get_level() > 0) ob_end_clean();
    exit('SQL error: ' . print_r(sqlsrv_errors(), true));
}

$rows = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $row;
}
sqlsrv_free_stmt($stmt);

$fmtDate = function ($v) {
    if ($v instanceof DateTime) return $v->format('d-M-y');
    return $v ? date('d-M-y', strtotime((string)$v)) : '';
};
$fmtNum = function ($v, $dec = 2) {
    if ($v === null || $v === '') return '';
    if (!is_numeric($v)) return (string)$v;
    $s = number_format((float)$v, $dec, '.', '');
    $s = rtrim(rtrim($s, '0'), '.');
    return $s === '' ? '0' : $s;
};

$pdf = new FPDF('L', 'mm', 'A4');
$pdf->SetMargins(8, 8, 8);
$pdf->SetAutoPageBreak(true, 10);
$pdf->AddPage();

$w = [
    'tanggal' => 22,
    'temp_umpan' => 14,
    'dh' => 13,
    'tds_umpan_ms' => 16,
    'tds_umpan_ppm' => 16,
    'ph' => 11,
    'temp_boiler' => 14,
    'tds_boiler_ms' => 16,
    'tds_boiler_ppm' => 16,
    'blowdown' => 26,
    'keterangan' => 70,
    'disp_umpan' => 17,
    'disp_boiler' => 17
];
$wAirUmpan = $w['temp_umpan'] + $w['dh'] + $w['tds_umpan_ms'] + $w['tds_umpan_ppm'];
$wAirBoiler = $w['ph'] + $w['temp_boiler'] + $w['tds_boiler_ms'] + $w['tds_boiler_ppm'];
$wDisplay = $w['disp_umpan'] + $w['disp_boiler'];
$tableWidth = $w['tanggal'] + $wAirUmpan + $wAirBoiler + $w['blowdown'] + $w['keterangan'] + $wDisplay;
$leftX = ($pdf->GetPageWidth() - $tableWidth) / 2;
$hTitle = 7;
$h1 = 6;
$h2 = 6;
$h3 = 6;
$headTotalH = $hTitle + $h1 + $h2 + $h3;
$rowH = 6;
$bottomLimit = $pdf->GetPageHeight() - 10;
$pdf->SetLineWidth(0.2);

$drawTitle = function () use ($pdf, $start, $end) {
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 6, 'Periode: ' . date('d-m-Y', strtotime($start)) . ' s/d ' . date('d-m-Y', strtotime($end)), 0, 1, 'C');
    $pdf->Ln(2);
};

$drawHeader = function () use (
    $pdf, $leftX, $tableWidth, $w, $wAirUmpan, $wAirBoiler, $wDisplay, $hTitle, $h1, $h2, $h3
) {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetX($leftX);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Cell($tableWidth, $hTitle, 'MONITORING AIR BOILER - ALSTOM', 1, 1, 'C', true);

    $pdf->SetFont('Arial', 'B', 7.5);

    // Row 1
    $pdf->SetX($leftX);
    $pdf->SetFillColor(230, 230, 230);
    $pdf->Cell($w['tanggal'], $h1 + $h2 + $h3, 'Tanggal', 1, 0, 'C', true);
    $pdf->Cell($wAirUmpan, $h1, 'AIR UMPAN', 1, 0, 'C', true);
    $pdf->Cell($wAirBoiler, $h1, 'AIR BOILER', 1, 0, 'C', true);
    $pdf->Cell($w['blowdown'], $h1, 'BLOWDOWN', 1, 0, 'C', true);
    $pdf->Cell($w['keterangan'], $h1 + $h2 + $h3, 'Keterangan', 1, 0, 'C', true);
    $pdf->SetFillColor(255, 242, 0);
    $pdf->Cell($wDisplay, $h1, 'ms/cm (display alat)', 1, 1, 'C', true);

    // Row 2
    $pdf->SetX($leftX + $w['tanggal']);
    $pdf->SetFillColor(230, 230, 230);
    $pdf->Cell($w['temp_umpan'], $h2, 'Temp', 1, 0, 'C', true);
    $pdf->Cell($w['dh'], $h2, 'DH', 1, 0, 'C', true);
    $pdf->Cell($w['tds_umpan_ms'] + $w['tds_umpan_ppm'], $h2, 'TDS', 1, 0, 'C', true);
    $pdf->Cell($w['ph'], $h2 + $h3, 'pH', 1, 0, 'C', true);
    $pdf->Cell($w['temp_boiler'], $h2, 'Temp', 1, 0, 'C', true);
    $pdf->Cell($w['tds_boiler_ms'] + $w['tds_boiler_ppm'], $h2, 'TDS', 1, 0, 'C', true);
    $pdf->Cell($w['blowdown'], $h2, 'Jumlah blowdown', 1, 0, 'C', true);

    $pdf->SetX($leftX + $w['tanggal'] + $wAirUmpan + $wAirBoiler + $w['blowdown'] + $w['keterangan']);
    $pdf->SetFillColor(255, 242, 0);
    $pdf->Cell($w['disp_umpan'], $h2 + $h3, 'Air Umpan', 1, 0, 'C', true);
    $pdf->Cell($w['disp_boiler'], $h2 + $h3, 'Air Boiler', 1, 1, 'C', true);

    // Row 3 (unit)
    $pdf->SetX($leftX + $w['tanggal']);
    $pdf->SetFillColor(230, 230, 230);
    $pdf->Cell($w['temp_umpan'], $h3, 'C', 1, 0, 'C', true);
    $pdf->Cell($w['dh'], $h3, 'std < 1', 1, 0, 'C', true);
    $pdf->Cell($w['tds_umpan_ms'], $h3, 'ms/cm *', 1, 0, 'C', true);
    $pdf->Cell($w['tds_umpan_ppm'], $h3, 'ppm', 1, 0, 'C', true);

    $pdf->SetX($leftX + $w['tanggal'] + $wAirUmpan + $w['ph']);
    $pdf->Cell($w['temp_boiler'], $h3, 'C', 1, 0, 'C', true);
    $pdf->Cell($w['tds_boiler_ms'], $h3, 'ms/cm *', 1, 0, 'C', true);
    $pdf->Cell($w['tds_boiler_ppm'], $h3, 'ppm', 1, 0, 'C', true);
    $pdf->Cell($w['blowdown'], $h3, '', 1, 1, 'C', true);
};

$fitText = function ($v, $maxWidth) use ($pdf) {
    $s = trim(str_replace(["\r", "\n"], ' ', (string)$v));
    if ($s === '') return '';
    if ($pdf->GetStringWidth($s) <= $maxWidth) return $s;
    $suffix = '...';
    while ($s !== '' && $pdf->GetStringWidth($s . $suffix) > $maxWidth) {
        $s = substr($s, 0, -1);
    }
    return rtrim($s) . $suffix;
};

$drawTitle();
$drawHeader();
$pdf->SetFont('Arial', '', 7.5);

if (empty($rows)) {
    $pdf->SetX($leftX);
    $pdf->Cell($tableWidth, 7, 'Tidak ada data pada rentang tanggal ini.', 1, 1, 'C');
} else {
    $grouped = [];
    foreach ($rows as $r) {
        $dateKey = ($r['tanggal'] instanceof DateTime)
            ? $r['tanggal']->format('Y-m-d')
            : date('Y-m-d', strtotime((string)($r['tanggal'] ?? '')));
        if (!isset($grouped[$dateKey])) $grouped[$dateKey] = [];
        $grouped[$dateKey][] = $r;
    }

    foreach ($grouped as $dateKey => $items) {
        $groupHeight = $rowH * count($items);
        if (($pdf->GetY() + $groupHeight) > $bottomLimit) {
            $pdf->AddPage();
            $drawTitle();
            $drawHeader();
            $pdf->SetFont('Arial', '', 7.5);
        }

        $rowspan = count($items);
        foreach ($items as $idx => $r) {
            if ($idx === 0) {
                $pdf->SetX($leftX);
                $pdf->Cell($w['tanggal'], $rowH * $rowspan, $fmtDate($dateKey), 1, 0, 'C');
            } else {
                $pdf->SetX($leftX + $w['tanggal']);
            }

            $pdf->Cell($w['temp_umpan'], $rowH, $fmtNum($r['temp_umpan_c'] ?? null), 1, 0, 'C');
            $pdf->Cell($w['dh'], $rowH, $fmtNum($r['dh_std_lt1'] ?? null), 1, 0, 'C');
            $pdf->Cell($w['tds_umpan_ms'], $rowH, $fmtNum($r['tds_umpan_ms'] ?? null), 1, 0, 'C');
            $pdf->Cell($w['tds_umpan_ppm'], $rowH, $fmtNum($r['tds_umpan_ppm'] ?? null), 1, 0, 'C');

            $pdf->Cell($w['ph'], $rowH, (string)($r['ph_boiler'] ?? ''), 1, 0, 'C');
            $pdf->Cell($w['temp_boiler'], $rowH, $fmtNum($r['temp_boiler_c'] ?? null), 1, 0, 'C');
            $pdf->Cell($w['tds_boiler_ms'], $rowH, $fmtNum($r['tds_boiler_ms'] ?? null), 1, 0, 'C');
            $pdf->Cell($w['tds_boiler_ppm'], $rowH, $fmtNum($r['tds_boiler_ppm'] ?? null), 1, 0, 'C');

            $pdf->Cell($w['blowdown'], $rowH, $fitText((string)($r['blowdown_jumlah'] ?? ''), $w['blowdown'] - 1), 1, 0, 'C');
            $pdf->Cell($w['keterangan'], $rowH, $fitText($r['keterangan'] ?? '', $w['keterangan'] - 1), 1, 0, 'C');
            $pdf->Cell($w['disp_umpan'], $rowH, $fmtNum($r['tds_umpan_display_ms'] ?? null), 1, 0, 'C');
            $pdf->Cell($w['disp_boiler'], $rowH, $fmtNum($r['tds_boiler_display_ms'] ?? null), 1, 1, 'C');
        }
    }
}

while (ob_get_level() > 0) ob_end_clean();
$filename = 'report_air_boiler_alstom_' . date('Ymd_His') . '.pdf';
$pdfContent = $pdf->Output('S');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfContent));
echo $pdfContent;

sqlsrv_close($conn);
exit;
