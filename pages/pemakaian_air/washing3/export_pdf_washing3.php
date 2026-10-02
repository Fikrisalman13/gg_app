<?php
session_start();
ob_start();
require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../../libs/fpdf.php';

$start = $_REQUEST['start_date'] ?? date('Y-m-01');
$end = $_REQUEST['end_date'] ?? date('Y-m-d');

$sql = "SELECT Tanggal, WaterFlow, OperasionalMesin, TotalPemakaian, Keterangan
        FROM dbo.washing3_air
        WHERE Tanggal BETWEEN ? AND ?
          AND (
                WaterFlow IS NOT NULL
                OR OperasionalMesin IS NOT NULL
                OR TotalPemakaian IS NOT NULL
                OR (Keterangan IS NOT NULL AND LTRIM(RTRIM(Keterangan)) <> '')
              )
        ORDER BY Tanggal ASC, Id ASC";
$stmt = sqlsrv_query($conn, $sql, [$start, $end]);
if ($stmt === false) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo "SQL error: " . print_r(sqlsrv_errors(), true);
    exit;
}

$rows = [];
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $rows[] = $r;
if ($stmt) sqlsrv_free_stmt($stmt);

$fmtDate = function($val) {
    if ($val instanceof DateTime) return $val->format('d-M-y');
    return (string)$val;
};
$fmtNum = function($val, $decimals = 2) {
    if ($val === null || $val === '') return '';
    if (!is_numeric($val)) return (string)$val;
    return number_format((float)$val, $decimals, '.', '');
};

$sumOperasional = 0.0;
$sumTotal = 0.0;
$sumWater = 0.0;
$cntWater = 0;
$cntOperasional = 0;
$cntTotal = 0;
foreach ($rows as $r) {
    if (is_numeric($r['WaterFlow'])) { $sumWater += (float)$r['WaterFlow']; $cntWater++; }
    if (is_numeric($r['OperasionalMesin'])) { $sumOperasional += (float)$r['OperasionalMesin']; $cntOperasional++; }
    if (is_numeric($r['TotalPemakaian'])) { $sumTotal += (float)$r['TotalPemakaian']; $cntTotal++; }
}
$avgWater = $cntWater ? $sumWater / $cntWater : null;
$avgOperasional = $cntOperasional ? $sumOperasional / $cntOperasional : null;
$avgTotal = $cntTotal ? $sumTotal / $cntTotal : null;

$pdf = new FPDF('L', 'mm', 'A4');
$pdf->SetMargins(8, 8, 8);
$pdf->SetAutoPageBreak(true, 10);
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, 'PEMAKAIAN AIR WASHING 3', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(0, 6, strtoupper(date('F Y', strtotime($start))), 0, 1, 'C');
$pdf->Ln(2);

$wTanggal = 30;
$wWater = 38;
$wOperasional = 45;
$wTotal = 38;
$wDummy = 65;
$wKet = 60;
$tableWidth = $wTanggal + $wWater + $wOperasional + $wTotal + $wDummy + $wKet;
$leftX = ($pdf->GetPageWidth() - $tableWidth) / 2;

$pdf->SetFont('Arial', 'B', 8);
$pdf->SetX($leftX);
$pdf->Cell($wTanggal, 12, 'TANGGAL', 1, 0, 'C');
$pdf->Cell($wWater, 12, "WATER FLOW\nM3/h", 1, 0, 'C');
$pdf->Cell($wOperasional, 12, "OPERASIONAL MESIN\nPER JAM", 1, 0, 'C');
$pdf->Cell($wTotal, 12, "TOTAL PEMAKAIAN\nM3", 1, 0, 'C');
$pdf->Cell($wDummy, 12, '', 1, 0, 'C');
$pdf->Cell($wKet, 12, "KET\nCUT OFF JAM 09.00", 1, 0, 'C');
$pdf->Ln();

$pdf->SetFont('Arial', '', 8);
foreach ($rows as $r) {
    $isOff = stripos((string)($r['Keterangan'] ?? ''), 'off') !== false;
    $pdf->SetX($leftX);
    $pdf->Cell($wTanggal, 6, $fmtDate($r['Tanggal']), 1, 0, 'C');
    $pdf->Cell($wWater, 6, $fmtNum($r['WaterFlow'], 2), 1, 0, 'R');
    $pdf->Cell($wOperasional, 6, $fmtNum($r['OperasionalMesin'], 2), 1, 0, 'R');
    if ($isOff) $pdf->SetFillColor(255, 242, 0);
    $pdf->Cell($wTotal, 6, $fmtNum($r['TotalPemakaian'], 2), 1, 0, 'R', $isOff);
    $pdf->Cell($wDummy, 6, '', 1, 0, 'C');
    $pdf->Cell($wKet, 6, (string)($r['Keterangan'] ?? ''), 1, 0, 'L', $isOff);
    $pdf->Ln();
}

$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(217, 214, 196);
$pdf->SetX($leftX);
$pdf->Cell($wTanggal, 7, 'TOTAL', 1, 0, 'C', true);
$pdf->Cell($wWater, 7, '', 1, 0, 'R', true);
$pdf->Cell($wOperasional, 7, $fmtNum($sumOperasional, 2), 1, 0, 'R', true);
$pdf->Cell($wTotal, 7, $fmtNum($sumTotal, 2), 1, 0, 'R', true);
$pdf->Cell($wDummy, 7, '', 1, 0, 'C', true);
$pdf->Cell($wKet, 7, '', 1, 0, 'L', true);
$pdf->Ln();

$pdf->SetX($leftX);
$pdf->Cell($wTanggal, 7, 'RATA-RATA', 1, 0, 'C', true);
$pdf->Cell($wWater, 7, $fmtNum($avgWater, 2), 1, 0, 'R', true);
$pdf->Cell($wOperasional, 7, $fmtNum($avgOperasional, 2), 1, 0, 'R', true);
$pdf->Cell($wTotal, 7, $fmtNum($avgTotal, 2), 1, 0, 'R', true);
$pdf->Cell($wDummy, 7, '', 1, 0, 'C', true);
$pdf->Cell($wKet, 7, '', 1, 0, 'L', true);
$pdf->Ln();

while (ob_get_level() > 0) { ob_end_clean(); }
$filename = 'Laporan_Washing3_' . date('Ymd_His') . '.pdf';
$pdfContent = $pdf->Output('S');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfContent));
echo $pdfContent;

sqlsrv_close($conn);
exit;
