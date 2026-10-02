<?php
session_start();
ob_start();

require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../libs/fpdf.php';

$menuId = 230;
requireView($conn, $menuId);

$start = trim($_POST['start_date'] ?? date('Y-m-01'));
$end = trim($_POST['end_date'] ?? date('Y-m-d'));

$sql = "SELECT CAST(tanggal AS DATE) AS tanggal, *
        FROM dbo.perblerange1_harian
        WHERE CAST(tanggal AS DATE) BETWEEN ? AND ?
        ORDER BY CAST(tanggal AS DATE) ASC, id ASC";
$stmt = sqlsrv_query($conn, $sql, [$start, $end]);
if ($stmt === false) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo 'SQL error: ' . print_r(sqlsrv_errors(), true);
    exit;
}

$rows = [];
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $dateObj = $r['tanggal'] ?? null;
    $r['tanggal'] = ($dateObj instanceof DateTime) ? $dateObj->format('Y-m-d') : date('Y-m-d', strtotime((string)$dateObj));
    $rows[] = $r;
}
sqlsrv_free_stmt($stmt);

$keys = [
    'meter_awal_m3', 'meter_akhir_m3', 'total_pemakaian_m3',
    'meter_awal_debit_m3', 'meter_akhir_debit_m3', 'total_pemakaian_debit_m3',
    'operasional_mc_pbr1_jam', 'pemakaian_rata_per_jam_m3',
    'jumlah_debit_m3', 'operasional_mc_pbr1_debit_jam', 'pemakaian_rata_per_jam_debit_m3'
];
$tot = array_fill_keys($keys, 0.0);
foreach ($rows as $r) {
    foreach ($keys as $k) {
        $tot[$k] += (float)($r[$k] ?? 0);
    }
}
$rowCount = count($rows);
$avg = [];
foreach ($keys as $k) {
    $avg[$k] = $rowCount > 0 ? ($tot[$k] / $rowCount) : 0.0;
}

$fmtNum = function ($val, $dec = 2) {
    if (!is_numeric($val)) return '';
    return number_format((float)$val, $dec, '.', ',');
};
$fmtDay = function ($ymd) {
    return $ymd ? date('j', strtotime($ymd)) : '';
};

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

$w = [18, 22, 22, 20, 22, 22, 28, 18, 16, 20, 28, 24, 18];
$tableWidth = array_sum($w);
$leftX = ($pdf->GetPageWidth() - $tableWidth) / 2;

$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(183, 202, 226);
$pdf->SetX($leftX);
$pdf->Cell($tableWidth, 7, 'METER PERBLE RANGE 1 - ' . $monthLabel, 1, 1, 'C', true);

$headers = [
    'Tanggal', 'Awal Meter', 'Akhir Meter', 'Total Meter',
    'Awal Debit', 'Akhir Debit', 'Total Debit Meter',
    'Operasional', 'Rata/Jam', 'Jumlah Debit',
    'Operasional Debit', 'Rata Debit/Jam', 'KET MC'
];

$pdf->SetFont('Arial', 'B', 7);
$pdf->SetFillColor(241, 221, 221);
$pdf->SetX($leftX);
for ($i = 0; $i < count($headers); $i++) {
    $pdf->Cell($w[$i], 6, $headers[$i], 1, 0, 'C', true);
}
$pdf->Ln();

if (empty($rows)) {
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetX($leftX);
    $pdf->Cell($tableWidth, 7, 'Tidak ada data pada rentang tanggal ini.', 1, 1, 'C');
} else {
    $pdf->SetFont('Arial', '', 7);
    foreach ($rows as $r) {
        $pdf->SetX($leftX);

        $pdf->SetFillColor(230, 237, 217);
        $pdf->Cell($w[0], 5.5, $fmtDay($r['tanggal']), 1, 0, 'C', true);

        $pdf->SetFillColor(196, 213, 233);
        $pdf->Cell($w[1], 5.5, $fmtNum($r['meter_awal_m3']), 1, 0, 'R', true);
        $pdf->Cell($w[2], 5.5, $fmtNum($r['meter_akhir_m3']), 1, 0, 'R', true);
        $pdf->Cell($w[3], 5.5, $fmtNum($r['total_pemakaian_m3']), 1, 0, 'R', true);
        $pdf->Cell($w[4], 5.5, $fmtNum($r['meter_awal_debit_m3']), 1, 0, 'R', true);
        $pdf->Cell($w[5], 5.5, $fmtNum($r['meter_akhir_debit_m3']), 1, 0, 'R', true);
        $pdf->Cell($w[6], 5.5, $fmtNum($r['total_pemakaian_debit_m3']), 1, 0, 'R', true);
        $pdf->Cell($w[7], 5.5, $fmtNum($r['operasional_mc_pbr1_jam']), 1, 0, 'R', true);
        $pdf->Cell($w[8], 5.5, $fmtNum($r['pemakaian_rata_per_jam_m3']), 1, 0, 'R', true);
        $pdf->Cell($w[9], 5.5, $fmtNum($r['jumlah_debit_m3']), 1, 0, 'R', true);
        $pdf->Cell($w[10], 5.5, $fmtNum($r['operasional_mc_pbr1_debit_jam']), 1, 0, 'R', true);
        $pdf->Cell($w[11], 5.5, $fmtNum($r['pemakaian_rata_per_jam_debit_m3']), 1, 0, 'R', true);
        $pdf->Cell($w[12], 5.5, (string)($r['ket_mc_yang_jalan'] ?? ''), 1, 1, 'C', true);
    }

    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetFillColor(236, 233, 214);
    $pdf->SetX($leftX);
    $pdf->Cell($w[0], 6, 'TOTAL', 1, 0, 'C', true);
    $pdf->Cell($w[1], 6, $fmtNum($tot['meter_awal_m3']), 1, 0, 'R', true);
    $pdf->Cell($w[2], 6, $fmtNum($tot['meter_akhir_m3']), 1, 0, 'R', true);
    $pdf->Cell($w[3], 6, $fmtNum($tot['total_pemakaian_m3']), 1, 0, 'R', true);
    $pdf->Cell($w[4], 6, $fmtNum($tot['meter_awal_debit_m3']), 1, 0, 'R', true);
    $pdf->Cell($w[5], 6, $fmtNum($tot['meter_akhir_debit_m3']), 1, 0, 'R', true);
    $pdf->Cell($w[6], 6, $fmtNum($tot['total_pemakaian_debit_m3']), 1, 0, 'R', true);
    $pdf->Cell($w[7], 6, $fmtNum($tot['operasional_mc_pbr1_jam']), 1, 0, 'R', true);
    $pdf->Cell($w[8], 6, $fmtNum($tot['pemakaian_rata_per_jam_m3']), 1, 0, 'R', true);
    $pdf->Cell($w[9], 6, $fmtNum($tot['jumlah_debit_m3']), 1, 0, 'R', true);
    $pdf->Cell($w[10], 6, $fmtNum($tot['operasional_mc_pbr1_debit_jam']), 1, 0, 'R', true);
    $pdf->Cell($w[11], 6, $fmtNum($tot['pemakaian_rata_per_jam_debit_m3']), 1, 0, 'R', true);
    $pdf->Cell($w[12], 6, '', 1, 1, 'C', true);

    $pdf->SetX($leftX);
    $pdf->Cell($w[0], 6, 'RATA-RATA', 1, 0, 'C', true);
    $pdf->Cell($w[1], 6, $fmtNum($avg['meter_awal_m3']), 1, 0, 'R', true);
    $pdf->Cell($w[2], 6, $fmtNum($avg['meter_akhir_m3']), 1, 0, 'R', true);
    $pdf->Cell($w[3], 6, $fmtNum($avg['total_pemakaian_m3']), 1, 0, 'R', true);
    $pdf->Cell($w[4], 6, $fmtNum($avg['meter_awal_debit_m3']), 1, 0, 'R', true);
    $pdf->Cell($w[5], 6, $fmtNum($avg['meter_akhir_debit_m3']), 1, 0, 'R', true);
    $pdf->Cell($w[6], 6, $fmtNum($avg['total_pemakaian_debit_m3']), 1, 0, 'R', true);
    $pdf->Cell($w[7], 6, $fmtNum($avg['operasional_mc_pbr1_jam']), 1, 0, 'R', true);
    $pdf->Cell($w[8], 6, $fmtNum($avg['pemakaian_rata_per_jam_m3']), 1, 0, 'R', true);
    $pdf->Cell($w[9], 6, $fmtNum($avg['jumlah_debit_m3']), 1, 0, 'R', true);
    $pdf->Cell($w[10], 6, $fmtNum($avg['operasional_mc_pbr1_debit_jam']), 1, 0, 'R', true);
    $pdf->Cell($w[11], 6, $fmtNum($avg['pemakaian_rata_per_jam_debit_m3']), 1, 0, 'R', true);
    $pdf->Cell($w[12], 6, '', 1, 1, 'C', true);
}

while (ob_get_level() > 0) { ob_end_clean(); }
$filename = 'report_perblerange1_' . date('Ymd_His') . '.pdf';
$pdfContent = $pdf->Output('S');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfContent));
echo $pdfContent;

sqlsrv_close($conn);
exit;
