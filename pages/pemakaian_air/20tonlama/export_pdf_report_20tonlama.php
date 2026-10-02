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
        FROM dbo.air_steam_20tonlama_harian
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

$airKeys = ['air_awal_m3', 'air_akhir_m3', 'air_total_pemakaian_m3', 'air_rata_rata_per_jam_m3'];
$steamKeys = ['steam_awal_ton', 'steam_akhir_ton', 'steam_total_pemakaian_ton', 'steam_rata_rata_per_jam_ton'];
$totAir = array_fill_keys($airKeys, 0.0);
$totSteam = array_fill_keys($steamKeys, 0.0);
foreach ($rows as $r) {
    foreach ($airKeys as $k) $totAir[$k] += (float)($r[$k] ?? 0);
    foreach ($steamKeys as $k) $totSteam[$k] += (float)($r[$k] ?? 0);
}
$rowCount = count($rows);
$avgAir = [];
$avgSteam = [];
foreach ($airKeys as $k) $avgAir[$k] = $rowCount > 0 ? ($totAir[$k] / $rowCount) : 0.0;
foreach ($steamKeys as $k) $avgSteam[$k] = $rowCount > 0 ? ($totSteam[$k] / $rowCount) : 0.0;

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

$tableCols = [20, 30, 30, 30, 28, 40];
$tableWidth = array_sum($tableCols);
$leftX = ($pdf->GetPageWidth() - $tableWidth) / 2;
$rowH = 5.5;

$drawTableHeader = function ($title) use ($pdf, $leftX, $tableWidth, $tableCols) {
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(183, 202, 226);
    $pdf->SetX($leftX);
    $pdf->Cell($tableWidth, 7, $title, 1, 1, 'C', true);

    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(243, 226, 226);
    $pdf->SetX($leftX);
    $pdf->Cell($tableCols[0], 6, 'Tanggal', 1, 0, 'C', true);
    $pdf->Cell($tableCols[1], 6, 'Awal', 1, 0, 'C', true);
    $pdf->Cell($tableCols[2], 6, 'Akhir', 1, 0, 'C', true);
    $pdf->Cell($tableCols[3], 6, 'Total', 1, 0, 'C', true);
    $pdf->Cell($tableCols[4], 6, 'Rata/Jam', 1, 0, 'C', true);
    $pdf->Cell($tableCols[5], 6, 'KET', 1, 1, 'C', true);
};

$ensureSpace = function ($needHeight) use ($pdf) {
    $bottomLimit = $pdf->GetPageHeight() - 10;
    if (($pdf->GetY() + $needHeight) > $bottomLimit) {
        $pdf->AddPage();
    }
};

$drawDataTable = function ($title, $group) use (
    $pdf, $rows, $totAir, $totSteam, $avgAir, $avgSteam,
    $tableCols, $leftX, $rowH, $fmtDay, $fmtNum,
    $drawTableHeader, $ensureSpace
) {
    $drawTableHeader($title);

    if (empty($rows)) {
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetX($leftX);
        $pdf->Cell(array_sum($tableCols), 7, 'Tidak ada data pada rentang tanggal ini.', 1, 1, 'C');
        return;
    }

    $pdf->SetFont('Arial', '', 7.5);
    foreach ($rows as $r) {
        $ensureSpace($rowH + 1);
        if ($pdf->GetY() <= 10) {
            $drawTableHeader($title);
        }

        if ($group === 'air') {
            $vAwal = $fmtNum($r['air_awal_m3'] ?? 0);
            $vAkhir = $fmtNum($r['air_akhir_m3'] ?? 0);
            $vTotal = $fmtNum($r['air_total_pemakaian_m3'] ?? 0);
            $vRata = $fmtNum($r['air_rata_rata_per_jam_m3'] ?? 0);
            $vKet = (string)($r['air_ket'] ?? '');
        } else {
            $vAwal = $fmtNum($r['steam_awal_ton'] ?? 0);
            $vAkhir = $fmtNum($r['steam_akhir_ton'] ?? 0);
            $vTotal = $fmtNum($r['steam_total_pemakaian_ton'] ?? 0);
            $vRata = $fmtNum($r['steam_rata_rata_per_jam_ton'] ?? 0);
            $vKet = (string)($r['steam_ket'] ?? '');
        }

        $pdf->SetX($leftX);
        $pdf->SetFillColor(230, 237, 217);
        $pdf->Cell($tableCols[0], $rowH, $fmtDay($r['tanggal'] ?? ''), 1, 0, 'C', true);
        $pdf->SetFillColor(196, 213, 233);
        $pdf->Cell($tableCols[1], $rowH, $vAwal, 1, 0, 'R', true);
        $pdf->Cell($tableCols[2], $rowH, $vAkhir, 1, 0, 'R', true);
        $pdf->Cell($tableCols[3], $rowH, $vTotal, 1, 0, 'R', true);
        $pdf->Cell($tableCols[4], $rowH, $vRata, 1, 0, 'R', true);
        $pdf->Cell($tableCols[5], $rowH, $vKet, 1, 1, 'C', true);
    }

    $sumSet = $group === 'air' ? $totAir : $totSteam;
    $avgSet = $group === 'air' ? $avgAir : $avgSteam;
    $sumKeys = $group === 'air'
        ? ['air_awal_m3', 'air_akhir_m3', 'air_total_pemakaian_m3', 'air_rata_rata_per_jam_m3']
        : ['steam_awal_ton', 'steam_akhir_ton', 'steam_total_pemakaian_ton', 'steam_rata_rata_per_jam_ton'];

    $pdf->SetFont('Arial', 'B', 7.5);
    $pdf->SetFillColor(236, 233, 214);

    $ensureSpace($rowH * 2 + 2);
    if ($pdf->GetY() <= 10) {
        $drawTableHeader($title);
    }

    $pdf->SetX($leftX);
    $pdf->Cell($tableCols[0], $rowH, 'TOTAL', 1, 0, 'C', true);
    $pdf->Cell($tableCols[1], $rowH, $fmtNum($sumSet[$sumKeys[0]]), 1, 0, 'R', true);
    $pdf->Cell($tableCols[2], $rowH, $fmtNum($sumSet[$sumKeys[1]]), 1, 0, 'R', true);
    $pdf->Cell($tableCols[3], $rowH, $fmtNum($sumSet[$sumKeys[2]]), 1, 0, 'R', true);
    $pdf->Cell($tableCols[4], $rowH, $fmtNum($sumSet[$sumKeys[3]]), 1, 0, 'R', true);
    $pdf->Cell($tableCols[5], $rowH, '', 1, 1, 'C', true);

    $pdf->SetX($leftX);
    $pdf->Cell($tableCols[0], $rowH, 'RATA-RATA', 1, 0, 'C', true);
    $pdf->Cell($tableCols[1], $rowH, $fmtNum($avgSet[$sumKeys[0]]), 1, 0, 'R', true);
    $pdf->Cell($tableCols[2], $rowH, $fmtNum($avgSet[$sumKeys[1]]), 1, 0, 'R', true);
    $pdf->Cell($tableCols[3], $rowH, $fmtNum($avgSet[$sumKeys[2]]), 1, 0, 'R', true);
    $pdf->Cell($tableCols[4], $rowH, $fmtNum($avgSet[$sumKeys[3]]), 1, 0, 'R', true);
    $pdf->Cell($tableCols[5], $rowH, '', 1, 1, 'C', true);
};

$drawDataTable('METER AIR BOILER STEAM 20 TON - ' . $monthLabel, 'air');
$pdf->Ln(8);
$ensureSpace(20);
$drawDataTable('METER STEAM BOILER STEAM 20 TON - ' . $monthLabel, 'steam');

while (ob_get_level() > 0) { ob_end_clean(); }
$filename = 'report_20tonlama_' . date('Ymd_His') . '.pdf';
$pdfContent = $pdf->Output('S');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfContent));
echo $pdfContent;

sqlsrv_close($conn);
exit;
