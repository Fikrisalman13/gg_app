<?php
session_start();
ob_start();

require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../libs/fpdf.php';

$menuId = 230;
requireView($conn, $menuId);

$startDate = trim($_GET['start_date'] ?? date('Y-m-01'));
$endDate = trim($_GET['end_date'] ?? date('Y-m-d'));
$shift = trim($_GET['shift'] ?? '');

$where = "WHERE CAST(x.tanggal AS DATE) BETWEEN ? AND ?";
$params = [$startDate, $endDate];
if ($shift !== '') {
    $where .= " AND x.shift_kode = ?";
    $params[] = $shift;
}

$sql = "SELECT x.tanggal, x.shift_kode,
               x.sv30_aerasi1_pct, x.sv30_aerasi2_pct, x.sv30_aerasi3_pct, x.sv30_aerasi4_pct,
               x.ph_equal, x.ph_akhir,
               x.dewatering_bawah_per_day, x.dewatering_atas_sinci1_per_day_ton, x.sinci2_per_day_ton
        FROM dbo.pencatatan_ipal_harian x
        $where
        ORDER BY CAST(x.tanggal AS DATE) ASC,
                 CASE x.shift_kode WHEN 'P' THEN 1 WHEN 'S' THEN 2 WHEN 'M' THEN 3 ELSE 4 END ASC";
$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo 'SQL error: ' . print_r(sqlsrv_errors(), true);
    exit;
}

$rows = [];
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $r;
}
sqlsrv_free_stmt($stmt);

$sum = ['sv1' => 0, 'sv2' => 0, 'sv3' => 0, 'sv4' => 0, 'ph1' => 0, 'ph2' => 0, 'db1' => 0, 'db2' => 0, 'db3' => 0];
foreach ($rows as $r) {
    $sum['sv1'] += (float)$r['sv30_aerasi1_pct'];
    $sum['sv2'] += (float)$r['sv30_aerasi2_pct'];
    $sum['sv3'] += (float)$r['sv30_aerasi3_pct'];
    $sum['sv4'] += (float)$r['sv30_aerasi4_pct'];
    $sum['ph1'] += (float)$r['ph_equal'];
    $sum['ph2'] += (float)$r['ph_akhir'];
    $sum['db1'] += (float)$r['dewatering_bawah_per_day'];
    $sum['db2'] += (float)$r['dewatering_atas_sinci1_per_day_ton'];
    $sum['db3'] += (float)$r['sinci2_per_day_ton'];
}
$count = count($rows);

$grouped = [];
foreach ($rows as $r) {
    $d = $r['tanggal'] instanceof DateTime ? $r['tanggal']->format('Y-m-d') : date('Y-m-d', strtotime((string)$r['tanggal']));
    if (!isset($grouped[$d])) {
        $grouped[$d] = [];
    }
    $grouped[$d][] = $r;
}
$shiftOrder = ['P' => 1, 'S' => 2, 'M' => 3];
foreach ($grouped as $d => $items) {
    usort($items, function ($a, $b) use ($shiftOrder) {
        $sa = strtoupper((string)($a['shift_kode'] ?? ''));
        $sb = strtoupper((string)($b['shift_kode'] ?? ''));
        return ($shiftOrder[$sa] ?? 9) <=> ($shiftOrder[$sb] ?? 9);
    });
    $grouped[$d] = $items;
}

$fmt2 = function ($v) { return number_format((float)$v, 2, '.', ','); };
$fmt0 = function ($v) { return number_format((float)$v, 0, '.', ','); };

$monthMap = [
    'January' => 'JANUARI', 'February' => 'FEBRUARI', 'March' => 'MARET', 'April' => 'APRIL',
    'May' => 'MEI', 'June' => 'JUNI', 'July' => 'JULI', 'August' => 'AGUSTUS',
    'September' => 'SEPTEMBER', 'October' => 'OKTOBER', 'November' => 'NOVEMBER', 'December' => 'DESEMBER'
];
$monthEn = date('F', strtotime($startDate));
$bulanLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($startDate));

$pdf = new FPDF('L', 'mm', 'A4');
$pdf->SetMargins(6, 6, 6);
$pdf->SetAutoPageBreak(true, 8);
$pdf->AddPage();

$w = [12, 14, 16, 16, 16, 16, 12, 12, 38, 46, 16];
$tableWidth = array_sum($w);
$leftX = ($pdf->GetPageWidth() - $tableWidth) / 2;

$drawHead = function () use ($pdf, $leftX, $w, $tableWidth, $bulanLabel) {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(214, 225, 191);
    $pdf->SetX($leftX);
    $pdf->Cell($tableWidth, 8, 'PENCATATAN SV30, PH, SLUDGE IPAL', 1, 1, 'C', true);

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(223, 232, 210);
    $pdf->SetX($leftX);
    $pdf->Cell($tableWidth, 7, 'Bulan : ' . $bulanLabel, 1, 1, 'C', true);

    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(217, 213, 194);
    $pdf->SetX($leftX);
    $pdf->Cell($w[0], 6, 'TGL', 1, 0, 'C', true);
    $pdf->Cell($w[1], 6, 'SHIFT', 1, 0, 'C', true);
    $pdf->Cell($w[2], 6, 'SV30', 1, 0, 'C', true);
    $pdf->Cell($w[3], 6, 'SV30', 1, 0, 'C', true);
    $pdf->Cell($w[4], 6, 'SV30', 1, 0, 'C', true);
    $pdf->Cell($w[5], 6, 'SV30', 1, 0, 'C', true);
    $pdf->Cell($w[6], 6, 'PH', 1, 0, 'C', true);
    $pdf->Cell($w[7], 6, 'PH', 1, 0, 'C', true);
    $pdf->Cell($w[8], 6, 'DEWATERING BAWAH', 1, 0, 'C', true);
    $pdf->Cell($w[9], 6, 'DEWATERING ATAS DAN SINCI 1', 1, 0, 'C', true);
    $pdf->Cell($w[10], 6, 'SINCI 2', 1, 1, 'C', true);

    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetFillColor(239, 232, 220);
    $pdf->SetX($leftX);
    $pdf->Cell($w[0], 5, '', 1, 0, 'C', true);
    $pdf->Cell($w[1], 5, '', 1, 0, 'C', true);
    $pdf->Cell($w[2], 5, '(%)', 1, 0, 'C', true);
    $pdf->Cell($w[3], 5, '(%)', 1, 0, 'C', true);
    $pdf->Cell($w[4], 5, '(%)', 1, 0, 'C', true);
    $pdf->Cell($w[5], 5, '(%)', 1, 0, 'C', true);
    $pdf->Cell($w[6], 5, 'Equal', 1, 0, 'C', true);
    $pdf->Cell($w[7], 5, 'Akhir', 1, 0, 'C', true);
    $pdf->Cell($w[8], 5, 'Per Day', 1, 0, 'C', true);
    $pdf->Cell($w[9], 5, 'Per Day/Ton', 1, 0, 'C', true);
    $pdf->Cell($w[10], 5, 'Per Day/Ton', 1, 1, 'C', true);

    $pdf->SetX($leftX);
    $pdf->Cell($w[0], 5, '', 1, 0, 'C', true);
    $pdf->Cell($w[1], 5, '', 1, 0, 'C', true);
    $pdf->Cell($w[2], 5, 'AERASI 1', 1, 0, 'C', true);
    $pdf->Cell($w[3], 5, 'AERASI 2', 1, 0, 'C', true);
    $pdf->Cell($w[4], 5, 'AERASI 3', 1, 0, 'C', true);
    $pdf->Cell($w[5], 5, 'AERASI 4', 1, 0, 'C', true);
    $pdf->Cell($w[6], 5, '-', 1, 0, 'C', true);
    $pdf->Cell($w[7], 5, '-', 1, 0, 'C', true);
    $pdf->Cell($w[8], 5, '', 1, 0, 'C', true);
    $pdf->Cell($w[9], 5, '', 1, 0, 'C', true);
    $pdf->Cell($w[10], 5, '', 1, 1, 'C', true);
};

$drawHead();

if (empty($grouped)) {
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetX($leftX);
    $pdf->Cell($tableWidth, 7, 'Tidak ada data.', 1, 1, 'C');
} else {
    foreach ($grouped as $dateYmd => $items) {
        $dayNum = (int)date('j', strtotime($dateYmd));
        foreach ($items as $i => $r) {
            if ($pdf->GetY() > 185) {
                $pdf->AddPage();
                $drawHead();
            }

            $pdf->SetFont('Arial', '', 8);
            $pdf->SetX($leftX);

            $pdf->SetFillColor(228, 234, 217);
            $pdf->Cell($w[0], 6, $i === 0 ? (string)$dayNum : '', 1, 0, 'C', true);
            $pdf->Cell($w[1], 6, (string)($r['shift_kode'] ?? ''), 1, 0, 'C', true);

            $pdf->SetFillColor(242, 228, 216);
            $pdf->Cell($w[2], 6, $fmt0($r['sv30_aerasi1_pct'] ?? 0), 1, 0, 'C', true);
            $pdf->Cell($w[3], 6, $fmt0($r['sv30_aerasi2_pct'] ?? 0), 1, 0, 'C', true);
            $pdf->Cell($w[4], 6, $fmt0($r['sv30_aerasi3_pct'] ?? 0), 1, 0, 'C', true);
            $pdf->Cell($w[5], 6, $fmt0($r['sv30_aerasi4_pct'] ?? 0), 1, 0, 'C', true);

            $pdf->SetFillColor(200, 217, 236);
            $pdf->Cell($w[6], 6, $fmt2($r['ph_equal'] ?? 0), 1, 0, 'C', true);
            $pdf->Cell($w[7], 6, $fmt2($r['ph_akhir'] ?? 0), 1, 0, 'C', true);

            $pdf->SetFillColor(149, 207, 82);
            $pdf->Cell($w[8], 6, $fmt0($r['dewatering_bawah_per_day'] ?? 0), 1, 0, 'C', true);
            $pdf->Cell($w[9], 6, $fmt0($r['dewatering_atas_sinci1_per_day_ton'] ?? 0), 1, 0, 'C', true);
            $pdf->Cell($w[10], 6, $fmt0($r['sinci2_per_day_ton'] ?? 0), 1, 1, 'C', true);
        }
    }
}

if ($count > 0) {
    if ($pdf->GetY() > 185) {
        $pdf->AddPage();
        $drawHead();
    }

    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(244, 216, 94);
    $pdf->SetX($leftX);
    $pdf->Cell($w[0] + $w[1], 6, 'TOTAL', 1, 0, 'C', true);
    $pdf->Cell($w[2], 6, $fmt2($sum['sv1']), 1, 0, 'C', true);
    $pdf->Cell($w[3], 6, $fmt2($sum['sv2']), 1, 0, 'C', true);
    $pdf->Cell($w[4], 6, $fmt2($sum['sv3']), 1, 0, 'C', true);
    $pdf->Cell($w[5], 6, $fmt2($sum['sv4']), 1, 0, 'C', true);
    $pdf->Cell($w[6], 6, $fmt2($sum['ph1']), 1, 0, 'C', true);
    $pdf->Cell($w[7], 6, $fmt2($sum['ph2']), 1, 0, 'C', true);
    $pdf->Cell($w[8], 6, $fmt2($sum['db1']), 1, 0, 'C', true);
    $pdf->Cell($w[9], 6, $fmt2($sum['db2']), 1, 0, 'C', true);
    $pdf->Cell($w[10], 6, $fmt2($sum['db3']), 1, 1, 'C', true);

    $pdf->SetFillColor(236, 229, 196);
    $pdf->SetX($leftX);
    $pdf->Cell($w[0] + $w[1], 6, 'RATA-RATA', 1, 0, 'C', true);
    $pdf->Cell($w[2], 6, $fmt2($sum['sv1'] / $count), 1, 0, 'C', true);
    $pdf->Cell($w[3], 6, $fmt2($sum['sv2'] / $count), 1, 0, 'C', true);
    $pdf->Cell($w[4], 6, $fmt2($sum['sv3'] / $count), 1, 0, 'C', true);
    $pdf->Cell($w[5], 6, $fmt2($sum['sv4'] / $count), 1, 0, 'C', true);
    $pdf->Cell($w[6], 6, $fmt2($sum['ph1'] / $count), 1, 0, 'C', true);
    $pdf->Cell($w[7], 6, $fmt2($sum['ph2'] / $count), 1, 0, 'C', true);
    $pdf->Cell($w[8], 6, $fmt2($sum['db1'] / $count), 1, 0, 'C', true);
    $pdf->Cell($w[9], 6, $fmt2($sum['db2'] / $count), 1, 0, 'C', true);
    $pdf->Cell($w[10], 6, $fmt2($sum['db3'] / $count), 1, 1, 'C', true);
}

while (ob_get_level() > 0) { ob_end_clean(); }
$filename = 'report_pencatatan_ipal_' . date('Ymd_His') . '.pdf';
$pdfContent = $pdf->Output('S');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfContent));
echo $pdfContent;

sqlsrv_close($conn);
exit;
