<?php
session_start();
ob_start();
require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../../libs/fpdf.php';

$start = $_REQUEST['start_date'] ?? date('Y-m-01');
$end = $_REQUEST['end_date'] ?? date('Y-m-d');

$sql = "SELECT Tanggal, Meter_Awal, Meter_Ahir, Total_Pemakaian, Pemakaian_rata2perjam, Keterangan
        FROM dbo.jetdyeing_air
        WHERE Tanggal BETWEEN ? AND ?
        ORDER BY Tanggal ASC, Id ASC";
$stmt = sqlsrv_query($conn, $sql, [$start, $end]);
if ($stmt === false) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo "SQL error: " . print_r(sqlsrv_errors(), true);
    exit;
}

$rows = [];
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $r;
}
sqlsrv_free_stmt($stmt);

$sumTotal = 0.0;
$sumRata = 0.0;
$cntTotal = 0;
$cntRata = 0;
foreach ($rows as $r) {
    if (is_numeric($r['Total_Pemakaian'])) {
        $sumTotal += (float)$r['Total_Pemakaian'];
        $cntTotal++;
    }
    if (is_numeric($r['Pemakaian_rata2perjam'])) {
        $sumRata += (float)$r['Pemakaian_rata2perjam'];
        $cntRata++;
    }
}
$avgTotal = $cntTotal ? $sumTotal / $cntTotal : null;
$avgRata = $cntRata ? $sumRata / $cntRata : null;

$fmtDate = function($val) {
    if ($val instanceof DateTime) return $val->format('Y-m-d');
    return (string)$val;
};
$fmtNum = function($val, $decimals) {
    if ($val === null || $val === '') return '-';
    if (!is_numeric($val)) return (string)$val;
    return number_format((float)$val, $decimals, '.', ',');
};

$pdf = new FPDF('L', 'mm', 'A4');
$pdf->SetMargins(8, 8, 8);
$pdf->SetAutoPageBreak(true, 10);
$pdf->AddPage();

$monthLabel = strtoupper(date('F Y', strtotime($start)));
if (date('Y-m', strtotime($start)) !== date('Y-m', strtotime($end))) {
    $monthLabel = strtoupper(date('d M Y', strtotime($start)) . ' - ' . date('d M Y', strtotime($end)));
}

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, 'METER JET DYEING, SIZING DAN LA', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(0, 5, $monthLabel, 0, 1, 'C');
$pdf->Ln(1);

$wTanggal = 28;
$wAwal = 35;
$wAkhir = 35;
$wTotal = 38;
$wRata = 52;
$wKet = 40;
$tableWidth = $wTanggal + $wAwal + $wAkhir + $wTotal + $wRata + $wKet;
$leftX = ($pdf->GetPageWidth() - $tableWidth) / 2;

$pdf->SetFont('Arial', 'B', 8);
// Header row (title + KET)
$pdf->SetFillColor(207, 226, 243);
$pdf->SetX($leftX);
$pdf->Cell($wTanggal + $wAwal + $wAkhir + $wTotal + $wRata, 7, '', 1, 0, 'C', true);
$pdf->Cell($wKet, 7, "KET\nCUT OFF JAM 09.00", 1, 0, 'C', true);
$pdf->Ln();

// Column labels
$pdf->SetFillColor(169, 211, 109);
$pdf->SetX($leftX);
$pdf->Cell($wTanggal, 7, 'TANGGAL', 1, 0, 'C', true);
$pdf->SetFillColor(139, 195, 74);
$pdf->Cell($wAwal, 7, 'AWAL', 1, 0, 'C', true);
$pdf->Cell($wAkhir, 7, 'AKHIR', 1, 0, 'C', true);
$pdf->Cell($wTotal, 7, 'TOTAL PEMAKAIAN', 1, 0, 'C', true);
$pdf->SetFillColor(246, 212, 212);
$pdf->Cell($wRata, 7, 'PEMAKAIAN RATA RATA / JAM', 1, 0, 'C', true);
$pdf->SetFillColor(207, 226, 243);
$pdf->Cell($wKet, 7, '', 1, 0, 'C', true);
$pdf->Ln();

// Units row
$pdf->SetFont('Arial', 'B', 7);
$pdf->SetFillColor(169, 211, 109);
$pdf->SetX($leftX);
$pdf->Cell($wTanggal, 6, '', 1, 0, 'C', true);
$pdf->SetFillColor(139, 195, 74);
$pdf->Cell($wAwal, 6, 'M3', 1, 0, 'C', true);
$pdf->Cell($wAkhir, 6, 'M3', 1, 0, 'C', true);
$pdf->Cell($wTotal, 6, 'M3', 1, 0, 'C', true);
$pdf->SetFillColor(246, 212, 212);
$pdf->Cell($wRata, 6, 'M3', 1, 0, 'C', true);
$pdf->SetFillColor(207, 226, 243);
$pdf->Cell($wKet, 6, '', 1, 0, 'C', true);
$pdf->Ln();

$pdf->SetFont('Arial', '', 8);
foreach ($rows as $r) {
    $pdf->SetX($leftX);
    $pdf->SetFillColor(169, 211, 109);
    $pdf->Cell($wTanggal, 6, $fmtDate($r['Tanggal']), 1, 0, 'C', true);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Cell($wAwal, 6, $fmtNum($r['Meter_Awal'], 2), 1, 0, 'R');
    $pdf->Cell($wAkhir, 6, $fmtNum($r['Meter_Ahir'], 2), 1, 0, 'R');
    $pdf->SetFillColor(207, 226, 243);
    $pdf->Cell($wTotal, 6, $fmtNum($r['Total_Pemakaian'], 2), 1, 0, 'R', true);
    $pdf->Cell($wRata, 6, $fmtNum($r['Pemakaian_rata2perjam'], 2), 1, 0, 'R');
    $pdf->Cell($wKet, 6, (string)($r['Keterangan'] ?? ''), 1, 0, 'L');
    $pdf->Ln();
}

$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(224, 224, 224);
$pdf->SetX($leftX);
$pdf->Cell($wTanggal + $wAwal + $wAkhir, 7, 'TOTAL', 1, 0, 'C', true);
$pdf->Cell($wTotal, 7, $fmtNum($sumTotal, 2), 1, 0, 'R', true);
$pdf->Cell($wRata, 7, $fmtNum($sumRata, 2), 1, 0, 'R', true);
$pdf->Cell($wKet, 7, '', 1, 0, 'L', true);
$pdf->Ln();

$pdf->SetX($leftX);
$pdf->Cell($wTanggal + $wAwal + $wAkhir, 7, 'RATA - RATA', 1, 0, 'C', true);
$pdf->Cell($wTotal, 7, $fmtNum($avgTotal, 2), 1, 0, 'R', true);
$pdf->Cell($wRata, 7, $fmtNum($avgRata, 2), 1, 0, 'R', true);
$pdf->Cell($wKet, 7, '', 1, 0, 'L', true);
$pdf->Ln();

while (ob_get_level() > 0) { ob_end_clean(); }
$filename = 'Laporan_jetdyeing_' . date('Ymd_His') . '.pdf';
$pdfContent = $pdf->Output('S');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfContent));
echo $pdfContent;

sqlsrv_close($conn);
exit;



