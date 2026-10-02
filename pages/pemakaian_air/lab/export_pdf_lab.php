<?php
session_start();
ob_start();
require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../../libs/fpdf.php';

$start = $_REQUEST['start_date'] ?? date('Y-m-01');
$end = $_REQUEST['end_date'] ?? date('Y-m-d');

$sql = "SELECT CAST(Tanggal AS DATE) AS Tanggal, Meter_Awal, Meter_Akhir, Total_Pemakaian
        FROM dbo.lab_air
        WHERE CAST(Tanggal AS DATE) BETWEEN ? AND ?
          AND (
                Meter_Awal IS NOT NULL
                OR Meter_Akhir IS NOT NULL
                OR Total_Pemakaian IS NOT NULL
                OR (Keterangan IS NOT NULL AND LTRIM(RTRIM(Keterangan)) <> '')
              )
        ORDER BY CAST(Tanggal AS DATE) ASC, Id ASC";
$stmt = sqlsrv_query($conn, $sql, [$start, $end]);
if ($stmt === false) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo "SQL error: " . print_r(sqlsrv_errors(), true);
    exit;
}

$rows = [];
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $rows[] = $r;
if ($stmt) sqlsrv_free_stmt($stmt);

$sumPemakaian = 0.0;
$countPemakaian = 0;
foreach ($rows as $r) {
    $pemakaian = $r['Total_Pemakaian'] ?? null;
    if ($pemakaian === null && is_numeric($r['Meter_Awal'] ?? null) && is_numeric($r['Meter_Akhir'] ?? null)) {
        $pemakaian = (float)$r['Meter_Akhir'] - (float)$r['Meter_Awal'];
    }
    if (is_numeric($pemakaian)) {
        $sumPemakaian += (float)$pemakaian;
        $countPemakaian++;
    }
}
$avgPemakaian = $countPemakaian > 0 ? ($sumPemakaian / $countPemakaian) : 0;

$fmtNum = function($val, $decimals = 2) {
    if ($val === null || $val === '' || !is_numeric($val)) return '';
    return number_format((float)$val, $decimals, '.', ',');
};

$monthMap = [
    'January' => 'JANUARI', 'February' => 'FEBRUARI', 'March' => 'MARET', 'April' => 'APRIL',
    'May' => 'MEI', 'June' => 'JUNI', 'July' => 'JULI', 'August' => 'AGUSTUS',
    'September' => 'SEPTEMBER', 'October' => 'OKTOBER', 'November' => 'NOVEMBER', 'December' => 'DESEMBER'
];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));
if (date('Y-m', strtotime($start)) !== date('Y-m', strtotime($end))) {
    $monthLabel = strtoupper(date('d M Y', strtotime($start)) . ' - ' . date('d M Y', strtotime($end)));
}

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 10);
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 7, 'LAPORAN METER AIR LAB', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(0, 6, $monthLabel, 0, 1, 'C');
$pdf->Ln(2);

$wNo = 16;
$wAwal = 50;
$wAkhir = 50;
$wPemakaian = 40;
$tableWidth = $wNo + $wAwal + $wAkhir + $wPemakaian;
$leftX = ($pdf->GetPageWidth() - $tableWidth) / 2;

$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(191, 183, 152);
$pdf->SetX($leftX);
$pdf->Cell($wNo, 12, 'NO', 1, 0, 'C', true);
$pdf->Cell($wAwal, 12, 'AWAL (M^3)', 1, 0, 'C', true);
$pdf->Cell($wAkhir, 12, 'AKHIR (M^3)', 1, 0, 'C', true);
$pdf->Cell($wPemakaian, 12, 'PEMAKAIAN (M^3)', 1, 0, 'C', true);
$pdf->Ln();

$pdf->SetFont('Arial', '', 8);
$no = 1;
foreach ($rows as $r) {
    $pemakaian = $r['Total_Pemakaian'] ?? null;
    if ($pemakaian === null && is_numeric($r['Meter_Awal'] ?? null) && is_numeric($r['Meter_Akhir'] ?? null)) {
        $pemakaian = (float)$r['Meter_Akhir'] - (float)$r['Meter_Awal'];
    }

    $pdf->SetX($leftX);
    $pdf->Cell($wNo, 6, (string)$no++, 1, 0, 'C');
    $pdf->Cell($wAwal, 6, $fmtNum($r['Meter_Awal'] ?? null, 3), 1, 0, 'C');
    $pdf->Cell($wAkhir, 6, $fmtNum($r['Meter_Akhir'] ?? null, 3), 1, 0, 'C');
    $pdf->Cell($wPemakaian, 6, $fmtNum($pemakaian, 2), 1, 0, 'C');
    $pdf->Ln();
}

$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(236, 233, 214);
$pdf->SetX($leftX);
$pdf->Cell($wNo, 7, 'Total', 1, 0, 'C', true);
$pdf->Cell($wAwal, 7, '', 1, 0, 'C', true);
$pdf->Cell($wAkhir, 7, '', 1, 0, 'C', true);
$pdf->Cell($wPemakaian, 7, $fmtNum($sumPemakaian, 2), 1, 0, 'C', true);
$pdf->Ln();

$pdf->SetX($leftX);
$pdf->Cell($wNo, 7, 'Rata', 1, 0, 'C', true);
$pdf->Cell($wAwal, 7, '', 1, 0, 'C', true);
$pdf->Cell($wAkhir, 7, '', 1, 0, 'C', true);
$pdf->Cell($wPemakaian, 7, $fmtNum($avgPemakaian, 2), 1, 0, 'C', true);
$pdf->Ln();

while (ob_get_level() > 0) { ob_end_clean(); }
$filename = 'Laporan_LAB_' . date('Ymd_His') . '.pdf';
$pdfContent = $pdf->Output('S');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfContent));
echo $pdfContent;

sqlsrv_close($conn);
exit;
