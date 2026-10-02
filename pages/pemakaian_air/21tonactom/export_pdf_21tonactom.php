<?php
session_start();
ob_start();
require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../../libs/fpdf.php';

$start = $_REQUEST['start_date'] ?? date('Y-m-01');
$end = $_REQUEST['end_date'] ?? date('Y-m-d');

function fetchRows($conn, $table, $start, $end) {
    $sql = "SELECT tanggal, awal, ahir, total_pemakaian, pemakaianrata2perjam
            FROM dbo.$table
            WHERE tanggal BETWEEN ? AND ?
            ORDER BY tanggal ASC";
    $stmt = sqlsrv_query($conn, $sql, [$start, $end]);
    if ($stmt === false) {
        while (ob_get_level() > 0) ob_end_clean();
        echo "SQL error: " . print_r(sqlsrv_errors(), true);
        exit;
    }
    $rows = [];
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $r;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    return $rows;
}

function calcSummary($rows) {
    $sumTotal = 0.0; $sumRata = 0.0; $cntTotal = 0; $cntRata = 0;
    foreach ($rows as $r) {
        if (is_numeric($r['total_pemakaian'])) { $sumTotal += (float)$r['total_pemakaian']; $cntTotal++; }
        if (is_numeric($r['pemakaianrata2perjam'])) { $sumRata += (float)$r['pemakaianrata2perjam']; $cntRata++; }
    }
    return [
        'sumTotal' => $sumTotal,
        'sumRata' => $sumRata,
        'avgTotal' => $cntTotal ? ($sumTotal / $cntTotal) : null,
        'avgRata' => $cntRata ? ($sumRata / $cntRata) : null
    ];
}

$rowsAirBoiler = fetchRows($conn, 'air_boiler_actom', $start, $end);
$rowsSteam = fetchRows($conn, 'steam_boiler_actom', $start, $end);
$rowsAirAnalog = fetchRows($conn, 'air_analog_actom', $start, $end);
$rowsAnalogSteam = fetchRows($conn, 'analog_steam_actom', $start, $end);

$sumAirBoiler = calcSummary($rowsAirBoiler);
$sumSteam = calcSummary($rowsSteam);
$sumAirAnalog = calcSummary($rowsAirAnalog);
$sumAnalogSteam = calcSummary($rowsAnalogSteam);

$monthMap=['January'=>'JANUARI','February'=>'FEBRUARI','March'=>'MARET','April'=>'APRIL','May'=>'MEI','June'=>'JUNI','July'=>'JULI','August'=>'AGUSTUS','September'=>'SEPTEMBER','October'=>'OKTOBER','November'=>'NOVEMBER','December'=>'DESEMBER'];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));

$fmtNum = function($v){ if($v===null||$v==='') return ''; return is_numeric($v)?number_format((float)$v,2,'.',','):(string)$v; };
$fmtDay = function($v){ if($v instanceof DateTime) return $v->format('j'); return date('j', strtotime((string)$v)); };

function renderPdfTable($pdf, $title, $unit, $rows, $summary, $x, $y, $fmtNum, $fmtDay) {
    $w = [9,13,13,16,16];
    $totalWidth = array_sum($w);
    $rowH = 4.5;

    $pdf->SetXY($x, $y);
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetFillColor(175,192,214);
    $pdf->Cell($totalWidth, 7, $title, 1, 1, 'C', true);

    $pdf->SetX($x);
    $pdf->Cell($totalWidth, 7, '', 1, 1, 'C', true);

    $pdf->SetX($x);
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->SetFillColor(207,222,239);
    $pdf->Cell($w[0], 7, 'TGL', 1, 0, 'C', true);
    $pdf->Cell($w[1], 7, 'AWAL', 1, 0, 'C', true);
    $pdf->Cell($w[2], 7, 'AKHIR', 1, 0, 'C', true);
    $pdf->Cell($w[3], 7, 'TOTAL', 1, 0, 'C', true);
    $pdf->Cell($w[4], 7, 'RATA/JAM', 1, 1, 'C', true);

    $pdf->SetX($x);
    $pdf->SetFillColor(243,230,230);
    $pdf->Cell($w[0], 5, '', 1, 0, 'C', true);
    $pdf->Cell($w[1], 5, $unit, 1, 0, 'C', true);
    $pdf->Cell($w[2], 5, $unit, 1, 0, 'C', true);
    $pdf->Cell($w[3], 5, $unit, 1, 0, 'C', true);
    $pdf->Cell($w[4], 5, $unit, 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 6);
    if (empty($rows)) {
        $pdf->SetX($x);
        $pdf->Cell($totalWidth, $rowH, 'Tidak ada data', 1, 1, 'C');
    } else {
        foreach ($rows as $r) {
            $pdf->SetX($x);
            $pdf->Cell($w[0], $rowH, $fmtDay($r['tanggal'] ?? ''), 1, 0, 'C');
            $pdf->Cell($w[1], $rowH, $fmtNum($r['awal'] ?? null), 1, 0, 'R');
            $pdf->Cell($w[2], $rowH, $fmtNum($r['ahir'] ?? null), 1, 0, 'R');
            $pdf->Cell($w[3], $rowH, $fmtNum($r['total_pemakaian'] ?? null), 1, 0, 'R');
            $pdf->Cell($w[4], $rowH, $fmtNum($r['pemakaianrata2perjam'] ?? null), 1, 1, 'R');
        }

        $pdf->SetFont('Arial', 'B', 6);
        $pdf->SetFillColor(217,214,196);
        $pdf->SetX($x);
        $pdf->Cell($w[0], $rowH, 'TOTAL', 1, 0, 'C', true);
        $pdf->Cell($w[1], $rowH, '', 1, 0, 'C', true);
        $pdf->Cell($w[2], $rowH, '', 1, 0, 'C', true);
        $pdf->Cell($w[3], $rowH, $fmtNum($summary['sumTotal']), 1, 0, 'R', true);
        $pdf->Cell($w[4], $rowH, $fmtNum($summary['sumRata']), 1, 1, 'R', true);

        $pdf->SetX($x);
        $pdf->Cell($w[0], $rowH, 'RATA2', 1, 0, 'C', true);
        $pdf->Cell($w[1], $rowH, '', 1, 0, 'C', true);
        $pdf->Cell($w[2], $rowH, '', 1, 0, 'C', true);
        $pdf->Cell($w[3], $rowH, $fmtNum($summary['avgTotal']), 1, 0, 'R', true);
        $pdf->Cell($w[4], $rowH, $fmtNum($summary['avgRata']), 1, 1, 'R', true);
    }
}

$pdf = new FPDF('L', 'mm', 'A4');
$pdf->SetMargins(6, 6, 6);
$pdf->SetAutoPageBreak(true, 10);
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 7, 'REPORT 21 TON ACTOM', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, $monthLabel, 0, 1, 'C');
$pdf->Ln(2);

$startX = 8;
$startY = 22;
$gap = 3;
$boxWidth = 67;

renderPdfTable($pdf, 'AIR BOILER 21TON ACTOM', 'M3', $rowsAirBoiler, $sumAirBoiler, $startX, $startY, $fmtNum, $fmtDay);
renderPdfTable($pdf, 'STEAM BOILER 21TON ACTOM', 'TON', $rowsSteam, $sumSteam, $startX + $boxWidth + $gap, $startY, $fmtNum, $fmtDay);
renderPdfTable($pdf, 'AIR ANALOG 21TON ACTOM', 'M3', $rowsAirAnalog, $sumAirAnalog, $startX + ($boxWidth + $gap) * 2, $startY, $fmtNum, $fmtDay);
renderPdfTable($pdf, 'ANALOG STEAM 21TON ACTOM', 'Ton', $rowsAnalogSteam, $sumAnalogSteam, $startX + ($boxWidth + $gap) * 3, $startY, $fmtNum, $fmtDay);

while (ob_get_level() > 0) ob_end_clean();
$filename = 'Laporan_21TonActom_' . date('Ymd_His') . '.pdf';
$pdfContent = $pdf->Output('S');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfContent));
echo $pdfContent;
