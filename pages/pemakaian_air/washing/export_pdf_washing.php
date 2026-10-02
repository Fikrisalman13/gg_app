<?php
session_start();
ob_start();
require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../../libs/fpdf.php';

$start = $_REQUEST['start_date'] ?? date('Y-m-01');
$end = $_REQUEST['end_date'] ?? date('Y-m-d');

$sql = "SELECT Tanggal, Meter_Awal, Meter_Ahir, Total_Pemakaian, Operasional_Mesin, Pemakaian_rata2perjam, Keterangan
        FROM dbo.washing_air
        WHERE Tanggal BETWEEN ? AND ?
          AND (
              Meter_Awal IS NOT NULL
              OR Meter_Ahir IS NOT NULL
              OR Total_Pemakaian IS NOT NULL
              OR Operasional_Mesin IS NOT NULL
              OR Pemakaian_rata2perjam IS NOT NULL
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

$sumTotal = 0.0;
$sumOperasional = 0.0;
$sumRata = 0.0;
$cntTotal = 0;
$cntOperasional = 0;
$cntRata = 0;
foreach ($rows as $r) {
    $isOff = preg_match('/^\s*off\s*$/i', (string)($r['Keterangan'] ?? '')) === 1;
    if ($isOff) {
        continue;
    }

    if (is_numeric($r['Total_Pemakaian'])) { $sumTotal += (float)$r['Total_Pemakaian']; $cntTotal++; }
    if (is_numeric($r['Operasional_Mesin'])) { $sumOperasional += (float)$r['Operasional_Mesin']; $cntOperasional++; }
    if (is_numeric($r['Pemakaian_rata2perjam'])) { $sumRata += (float)$r['Pemakaian_rata2perjam']; $cntRata++; }
}
$avgTotal = $cntTotal ? $sumTotal / $cntTotal : null;
$avgOperasional = $cntOperasional ? $sumOperasional / $cntOperasional : null;
$avgRata = $cntRata ? $sumRata / $cntRata : null;

$monthMap = [
    'January' => 'JANUARI', 'February' => 'FEBRUARI', 'March' => 'MARET', 'April' => 'APRIL',
    'May' => 'MEI', 'June' => 'JUNI', 'July' => 'JULI', 'August' => 'AGUSTUS',
    'September' => 'SEPTEMBER', 'October' => 'OKTOBER', 'November' => 'NOVEMBER', 'December' => 'DESEMBER'
];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));

$pdf = new FPDF('L', 'mm', 'A4');
$pdf->SetMargins(8, 8, 8);
$pdf->SetAutoPageBreak(true, 10);
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, 'PEMAKAIAN AIR DI MESIN WASHING', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(0, 6, $monthLabel, 0, 1, 'C');
$pdf->Ln(2);

$wTanggal = 20;
$wAwal = 38;
$wAkhir = 38;
$wTotal = 35;
$wOperasional = 36;
$wRata = 38;
$wKet = 60;
$tableWidth = $wTanggal + $wAwal + $wAkhir + $wTotal + $wOperasional + $wRata + $wKet;
$leftX = ($pdf->GetPageWidth() - $tableWidth) / 2;

$pdf->SetFont('Arial', 'B', 8);
$pdf->SetX($leftX);
$pdf->Cell($wTanggal, 12, "TANGGAL", 1, 0, 'C');
$pdf->Cell($wAwal, 12, "AWAL\nM3", 1, 0, 'C');
$pdf->Cell($wAkhir, 12, "AKHIR\nM3", 1, 0, 'C');
$pdf->Cell($wTotal, 12, "TOTAL PEMAKAIAN\nM3", 1, 0, 'C');
$pdf->Cell($wOperasional, 12, "OPERASIONAL MESIN\nPER JAM", 1, 0, 'C');
$pdf->Cell($wRata, 12, "PEMAKAIAN RATA RATA\nM3", 1, 0, 'C');
$pdf->Cell($wKet, 12, "KET\nCUT OFF JAM 09.00", 1, 0, 'C');
$pdf->Ln();

$pdf->SetFont('Arial', '', 8);
foreach ($rows as $r) {
    $tgl = ($r['Tanggal'] instanceof DateTime) ? $r['Tanggal']->format('j') : date('j', strtotime((string)$r['Tanggal']));
    $isOff = preg_match('/^\s*off\s*$/i', (string)($r['Keterangan'] ?? '')) === 1;
    $pdf->SetX($leftX);
    $pdf->Cell($wTanggal, 6, $tgl, 1, 0, 'C');
    $pdf->Cell($wAwal, 6, $fmtNum($r['Meter_Awal'], 2), 1, 0, 'R');
    $pdf->Cell($wAkhir, 6, $fmtNum($r['Meter_Ahir'], 2), 1, 0, 'R');
    $pdf->Cell($wTotal, 6, $isOff ? '' : $fmtNum($r['Total_Pemakaian'], 2), 1, 0, 'R');
    $pdf->Cell($wOperasional, 6, $isOff ? '' : $fmtNum($r['Operasional_Mesin'], 2), 1, 0, 'R');
    $pdf->Cell($wRata, 6, $isOff ? '' : $fmtNum($r['Pemakaian_rata2perjam'], 2), 1, 0, 'R');
    $pdf->Cell($wKet, 6, (string)($r['Keterangan'] ?? ''), 1, 0, 'L');
    $pdf->Ln();
}

$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(217, 214, 196);
$pdf->SetX($leftX);
$pdf->Cell($wTanggal, 7, 'TOTAL', 1, 0, 'C', true);
$pdf->Cell($wAwal, 7, '', 1, 0, 'R', true);
$pdf->Cell($wAkhir, 7, '', 1, 0, 'R', true);
$pdf->Cell($wTotal, 7, $fmtNum($sumTotal, 2), 1, 0, 'R', true);
$pdf->Cell($wOperasional, 7, $fmtNum($sumOperasional, 2), 1, 0, 'R', true);
$pdf->Cell($wRata, 7, $fmtNum($sumRata, 2), 1, 0, 'R', true);
$pdf->Cell($wKet, 7, '', 1, 0, 'L', true);
$pdf->Ln();

$pdf->SetX($leftX);
$pdf->Cell($wTanggal, 7, 'RATA-RATA', 1, 0, 'C', true);
$pdf->Cell($wAwal, 7, '', 1, 0, 'R', true);
$pdf->Cell($wAkhir, 7, '', 1, 0, 'R', true);
$pdf->Cell($wTotal, 7, $fmtNum($avgTotal, 2), 1, 0, 'R', true);
$pdf->Cell($wOperasional, 7, $fmtNum($avgOperasional, 2), 1, 0, 'R', true);
$pdf->Cell($wRata, 7, $fmtNum($avgRata, 2), 1, 0, 'R', true);
$pdf->Cell($wKet, 7, '', 1, 0, 'L', true);
$pdf->Ln();

while (ob_get_level() > 0) { ob_end_clean(); }
$filename = 'Laporan_washing_' . date('Ymd_His') . '.pdf';
$pdfContent = $pdf->Output('S');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfContent));
echo $pdfContent;

sqlsrv_close($conn);
exit;

