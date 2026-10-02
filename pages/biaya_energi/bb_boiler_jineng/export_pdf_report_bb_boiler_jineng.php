<?php
session_start();
ob_start();

require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../libs/fpdf.php';

$menuId = 230;
requireView($conn, $menuId);

$start = $_POST['start_date'] ?? date('Y-m-01');
$end = $_POST['end_date'] ?? date('Y-m-d');

$normalizeDate = function ($value) {
    $value = trim((string)$value);
    if ($value === '') return '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
    if (preg_match('/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/', $value, $m)) return $m[3] . '-' . $m[2] . '-' . $m[1];
    return '';
};

$start = $normalizeDate($start) ?: date('Y-m-01');
$end = $normalizeDate($end) ?: date('Y-m-d');

$sql = "SELECT CAST(tanggal AS DATE) AS tanggal,
               pemakaian_kg,
               harga_rp_per_kg,
               biaya_rp,
               total_biaya_boiler_jineng_rp,
               extractor_kg,
               cgrate_kg,
               cyclon_kg,
               total_kg,
               ket
        FROM dbo.bb_boiler_jineng_harian
        WHERE CAST(tanggal AS DATE) BETWEEN ? AND ?
        ORDER BY CAST(tanggal AS DATE) ASC";

$stmt = sqlsrv_query($conn, $sql, [$start, $end]);
if ($stmt === false) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo 'SQL error: ' . print_r(sqlsrv_errors(), true);
    exit;
}

$rows = [];
$tot = [
    'pemakaian_kg' => 0.0,
    'biaya_rp' => 0.0,
    'total_biaya_boiler_jineng_rp' => 0.0,
    'extractor_kg' => 0.0,
    'cgrate_kg' => 0.0,
    'cyclon_kg' => 0.0,
    'total_kg' => 0.0,
];
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $dateObj = $r['tanggal'] ?? null;
    $dateKey = $dateObj instanceof DateTime ? $dateObj->format('Y-m-d') : date('Y-m-d', strtotime((string)$dateObj));
    $row = [
        'tanggal' => $dateKey,
        'pemakaian_kg' => is_numeric($r['pemakaian_kg']) ? (float)$r['pemakaian_kg'] : 0.0,
        'harga_rp_per_kg' => is_numeric($r['harga_rp_per_kg']) ? (float)$r['harga_rp_per_kg'] : 0.0,
        'biaya_rp' => is_numeric($r['biaya_rp']) ? (float)$r['biaya_rp'] : 0.0,
        'total_biaya_boiler_jineng_rp' => is_numeric($r['total_biaya_boiler_jineng_rp']) ? (float)$r['total_biaya_boiler_jineng_rp'] : 0.0,
        'extractor_kg' => is_numeric($r['extractor_kg']) ? (float)$r['extractor_kg'] : 0.0,
        'cgrate_kg' => is_numeric($r['cgrate_kg']) ? (float)$r['cgrate_kg'] : 0.0,
        'cyclon_kg' => is_numeric($r['cyclon_kg']) ? (float)$r['cyclon_kg'] : 0.0,
        'total_kg' => is_numeric($r['total_kg']) ? (float)$r['total_kg'] : 0.0,
        'ket' => (string)($r['ket'] ?? ''),
    ];
    $rows[] = $row;
    foreach ($tot as $k => $_) $tot[$k] += $row[$k];
}
sqlsrv_free_stmt($stmt);

$rowCount = count($rows);
$avg = [
    'pemakaian_kg' => $rowCount > 0 ? ($tot['pemakaian_kg'] / $rowCount) : 0,
    'biaya_rp' => $rowCount > 0 ? ($tot['biaya_rp'] / $rowCount) : 0,
    'total_biaya_boiler_jineng_rp' => $rowCount > 0 ? ($tot['total_biaya_boiler_jineng_rp'] / $rowCount) : 0,
    'extractor_kg' => $rowCount > 0 ? ($tot['extractor_kg'] / $rowCount) : 0,
    'cgrate_kg' => $rowCount > 0 ? ($tot['cgrate_kg'] / $rowCount) : 0,
    'cyclon_kg' => $rowCount > 0 ? ($tot['cyclon_kg'] / $rowCount) : 0,
    'total_kg' => $rowCount > 0 ? ($tot['total_kg'] / $rowCount) : 0,
];

$fmt = function ($val, $dec = 2) {
    if (!is_numeric($val)) return '0.00';
    return number_format((float)$val, $dec, '.', ',');
};
$fmtDay = function ($ymd) { return $ymd ? date('j', strtotime($ymd)) : ''; };

$monthMap = [
    'January' => 'JANUARI', 'February' => 'FEBRUARI', 'March' => 'MARET', 'April' => 'APRIL',
    'May' => 'MEI', 'June' => 'JUNI', 'July' => 'JULI', 'August' => 'AGUSTUS',
    'September' => 'SEPTEMBER', 'October' => 'OKTOBER', 'November' => 'NOVEMBER', 'December' => 'DESEMBER'
];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));

$pdf = new FPDF('L', 'mm', 'A4');
$pdf->SetMargins(6, 6, 6);
$pdf->SetAutoPageBreak(true, 8);
$pdf->AddPage();

$w = [14, 23, 23, 27, 34, 19, 16, 16, 16, 22];
$tableWidth = array_sum($w);
$leftX = ($pdf->GetPageWidth() - $tableWidth) / 2;

$drawHeader = function () use ($pdf, $leftX, $w, $tableWidth, $monthLabel) {
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetFillColor(118, 146, 60);
    $pdf->SetX($leftX);
    $pdf->Cell($tableWidth, 8, 'PEMAKAIAN BATU BARA BOILER JINENG , PEMBUANGAN BUTON ASH DAN FLY ASH', 1, 1, 'C', true);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetX($leftX);
    $pdf->Cell($w[0], 7, 'Bulan :', 1, 0, 'L', true);
    $pdf->Cell($tableWidth - $w[0], 7, $monthLabel, 1, 1, 'L', true);

    $pdf->SetFont('Arial', 'B', 6.5);
    $pdf->SetFillColor(155, 194, 209);
    $pdf->SetX($leftX);
    $pdf->Cell($w[0], 6, 'Tanggal', 1, 0, 'C', true);
    $pdf->Cell($w[1] + $w[2] + $w[3], 6, 'BATU BARA ADB 5600-5800 0-200 MM ASALAN', 1, 0, 'C', true);
    $pdf->SetFillColor(255, 192, 0);
    $pdf->Cell($w[4], 6, 'TOTAL BIAYA', 1, 0, 'C', true);
    $pdf->SetFillColor(155, 194, 209);
    $pdf->Cell($w[5] + $w[6] + $w[7], 6, 'BUTON ASH', 1, 0, 'C', true);
    $pdf->Cell($w[8], 6, 'TOTAL', 1, 0, 'C', true);
    $pdf->Cell($w[9], 6, 'KET', 1, 1, 'C', true);

    $pdf->SetX($leftX);
    $pdf->Cell($w[0], 6, '', 1, 0, 'C', true);
    $pdf->Cell($w[1], 6, 'PEMAKAIAN', 1, 0, 'C', true);
    $pdf->Cell($w[2], 6, 'HARGA/KG', 1, 0, 'C', true);
    $pdf->Cell($w[3], 6, 'BIAYA', 1, 0, 'C', true);
    $pdf->SetFillColor(255, 192, 0);
    $pdf->Cell($w[4], 6, 'BOILER (Rp)', 1, 0, 'C', true);
    $pdf->SetFillColor(155, 194, 209);
    $pdf->Cell($w[5], 6, 'EXTRACTOR', 1, 0, 'C', true);
    $pdf->Cell($w[6], 6, 'C/GRATE', 1, 0, 'C', true);
    $pdf->Cell($w[7], 6, 'CYCLON', 1, 0, 'C', true);
    $pdf->Cell($w[8], 6, '', 1, 0, 'C', true);
    $pdf->Cell($w[9], 6, '', 1, 1, 'C', true);

    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetFillColor(207, 234, 246);
    $pdf->SetX($leftX);
    $pdf->Cell($w[0], 5, '', 1, 0, 'C', true);
    $pdf->Cell($w[1], 5, '(KG)', 1, 0, 'C', true);
    $pdf->Cell($w[2], 5, '(Rp)', 1, 0, 'C', true);
    $pdf->Cell($w[3], 5, '(Rp)', 1, 0, 'C', true);
    $pdf->SetFillColor(255, 192, 0);
    $pdf->Cell($w[4], 5, '', 1, 0, 'C', true);
    $pdf->SetFillColor(207, 234, 246);
    $pdf->Cell($w[5], 5, 'KG', 1, 0, 'C', true);
    $pdf->Cell($w[6], 5, 'KG', 1, 0, 'C', true);
    $pdf->Cell($w[7], 5, 'KG', 1, 0, 'C', true);
    $pdf->Cell($w[8], 5, 'KG', 1, 0, 'C', true);
    $pdf->Cell($w[9], 5, '', 1, 1, 'C', true);
};

$drawHeader();

if (empty($rows)) {
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetX($leftX);
    $pdf->Cell($tableWidth, 7, 'Tidak ada data pada rentang tanggal ini.', 1, 1, 'C');
} else {
    foreach ($rows as $r) {
        if ($pdf->GetY() > 185) {
            $pdf->AddPage();
            $drawHeader();
        }

        $pdf->SetFont('Arial', '', 7);
        $pdf->SetX($leftX);
        $pdf->SetFillColor(230, 239, 213);
        $pdf->Cell($w[0], 6, $fmtDay($r['tanggal']), 1, 0, 'C', true);
        $pdf->SetFillColor(216, 213, 184);
        $pdf->Cell($w[1], 6, $fmt($r['pemakaian_kg']), 1, 0, 'C', true);
        $pdf->Cell($w[2], 6, $fmt($r['harga_rp_per_kg']), 1, 0, 'C', true);
        $pdf->Cell($w[3], 6, $fmt($r['biaya_rp']), 1, 0, 'C', true);
        $pdf->SetFillColor(255, 192, 0);
        $pdf->Cell($w[4], 6, $fmt($r['total_biaya_boiler_jineng_rp']), 1, 0, 'C', true);
        $pdf->SetFillColor(216, 213, 184);
        $pdf->Cell($w[5], 6, $fmt($r['extractor_kg']), 1, 0, 'C', true);
        $pdf->Cell($w[6], 6, $fmt($r['cgrate_kg']), 1, 0, 'C', true);
        $pdf->Cell($w[7], 6, $fmt($r['cyclon_kg']), 1, 0, 'C', true);
        $pdf->SetFillColor(255, 192, 0);
        $pdf->Cell($w[8], 6, $fmt($r['total_kg']), 1, 0, 'C', true);
        $pdf->SetFillColor(216, 213, 184);
        $pdf->Cell($w[9], 6, $r['ket'], 1, 1, 'C', true);
    }

    if ($pdf->GetY() > 185) {
        $pdf->AddPage();
        $drawHeader();
    }

    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetFillColor(255, 210, 77);
    $pdf->SetX($leftX);
    $pdf->Cell($w[0], 6, 'TOTAL', 1, 0, 'C', true);
    $pdf->Cell($w[1], 6, $fmt($tot['pemakaian_kg']), 1, 0, 'C', true);
    $pdf->Cell($w[2], 6, '', 1, 0, 'C', true);
    $pdf->Cell($w[3], 6, $fmt($tot['biaya_rp']), 1, 0, 'C', true);
    $pdf->Cell($w[4], 6, $fmt($tot['total_biaya_boiler_jineng_rp']), 1, 0, 'C', true);
    $pdf->Cell($w[5], 6, $fmt($tot['extractor_kg']), 1, 0, 'C', true);
    $pdf->Cell($w[6], 6, $fmt($tot['cgrate_kg']), 1, 0, 'C', true);
    $pdf->Cell($w[7], 6, $fmt($tot['cyclon_kg']), 1, 0, 'C', true);
    $pdf->Cell($w[8], 6, $fmt($tot['total_kg']), 1, 0, 'C', true);
    $pdf->Cell($w[9], 6, '', 1, 1, 'C', true);

    $pdf->SetX($leftX);
    $pdf->Cell($w[0], 6, 'RATA-RATA', 1, 0, 'C', true);
    $pdf->Cell($w[1], 6, $fmt($avg['pemakaian_kg']), 1, 0, 'C', true);
    $pdf->Cell($w[2], 6, '', 1, 0, 'C', true);
    $pdf->Cell($w[3], 6, $fmt($avg['biaya_rp']), 1, 0, 'C', true);
    $pdf->Cell($w[4], 6, $fmt($avg['total_biaya_boiler_jineng_rp']), 1, 0, 'C', true);
    $pdf->Cell($w[5], 6, $fmt($avg['extractor_kg']), 1, 0, 'C', true);
    $pdf->Cell($w[6], 6, $fmt($avg['cgrate_kg']), 1, 0, 'C', true);
    $pdf->Cell($w[7], 6, $fmt($avg['cyclon_kg']), 1, 0, 'C', true);
    $pdf->Cell($w[8], 6, $fmt($avg['total_kg']), 1, 0, 'C', true);
    $pdf->Cell($w[9], 6, '', 1, 1, 'C', true);
}

while (ob_get_level() > 0) { ob_end_clean(); }
$filename = 'Report_bb_boiler_jineng_' . date('Ymd_His') . '.pdf';
$pdfContent = $pdf->Output('S');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfContent));
echo $pdfContent;

sqlsrv_close($conn);
exit;
