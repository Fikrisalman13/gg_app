<?php
session_start();
ob_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/libs/fpdf.php';

$menuId = 230;
requireView($conn, $menuId);

$normalizeDate = function ($value) {
    $value = trim((string)$value);
    if ($value === '') return '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
    if (preg_match('/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/', $value, $m)) return $m[3] . '-' . $m[2] . '-' . $m[1];
    return '';
};

$start = $normalizeDate($_POST['start_date'] ?? date('Y-m-01')) ?: date('Y-m-01');
$end = $normalizeDate($_POST['end_date'] ?? date('Y-m-d')) ?: date('Y-m-d');

if (strtotime($start) > strtotime($end)) {
    $tmp = $start;
    $start = $end;
    $end = $tmp;
}

$sql = "SELECT CAST(tanggal AS DATE) AS tanggal,
               pemakaian_kg,
               harga_rp_per_kg,
               biaya_rp,
               total_biaya_boiler_rp,
               extractor_kg,
               cgrate_kg,
               fly_ash_kg,
               total_kg,
               ket
        FROM dbo.bb_20t_lama_harian
        WHERE CAST(tanggal AS DATE) BETWEEN ? AND ?
        ORDER BY CAST(tanggal AS DATE) ASC";

$stmt = sqlsrv_query($conn, $sql, [$start, $end]);
if ($stmt === false) {
    while (ob_get_level() > 0) ob_end_clean();
    echo '<b>SQL error</b>: ' . htmlspecialchars(print_r(sqlsrv_errors(), true));
    exit;
}

$rows = [];
$tot = [
    'pemakaian_kg' => 0.0,
    'biaya_rp' => 0.0,
    'total_biaya_boiler_rp' => 0.0,
    'extractor_kg' => 0.0,
    'cgrate_kg' => 0.0,
    'fly_ash_kg' => 0.0,
    'total_kg' => 0.0,
];

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $dateObj = $r['tanggal'] ?? null;
    if ($dateObj instanceof DateTime) $dateKey = $dateObj->format('Y-m-d');
    else $dateKey = date('Y-m-d', strtotime((string)$dateObj));

    $row = [
        'tanggal' => $dateKey,
        'pemakaian_kg' => is_numeric($r['pemakaian_kg']) ? (float)$r['pemakaian_kg'] : 0.0,
        'harga_rp_per_kg' => is_numeric($r['harga_rp_per_kg']) ? (float)$r['harga_rp_per_kg'] : 0.0,
        'biaya_rp' => is_numeric($r['biaya_rp']) ? (float)$r['biaya_rp'] : 0.0,
        'total_biaya_boiler_rp' => is_numeric($r['total_biaya_boiler_rp']) ? (float)$r['total_biaya_boiler_rp'] : 0.0,
        'extractor_kg' => is_numeric($r['extractor_kg']) ? (float)$r['extractor_kg'] : 0.0,
        'cgrate_kg' => is_numeric($r['cgrate_kg']) ? (float)$r['cgrate_kg'] : 0.0,
        'fly_ash_kg' => is_numeric($r['fly_ash_kg']) ? (float)$r['fly_ash_kg'] : 0.0,
        'total_kg' => is_numeric($r['total_kg']) ? (float)$r['total_kg'] : 0.0,
        'ket' => (string)($r['ket'] ?? ''),
    ];

    $rows[] = $row;
    foreach ($tot as $k => $_) {
        $tot[$k] += $row[$k];
    }
}
sqlsrv_free_stmt($stmt);

$rowCount = count($rows);
$avg = [
    'pemakaian_kg' => $rowCount > 0 ? ($tot['pemakaian_kg'] / $rowCount) : 0,
    'biaya_rp' => $rowCount > 0 ? ($tot['biaya_rp'] / $rowCount) : 0,
    'total_biaya_boiler_rp' => $rowCount > 0 ? ($tot['total_biaya_boiler_rp'] / $rowCount) : 0,
    'extractor_kg' => $rowCount > 0 ? ($tot['extractor_kg'] / $rowCount) : 0,
    'cgrate_kg' => $rowCount > 0 ? ($tot['cgrate_kg'] / $rowCount) : 0,
    'fly_ash_kg' => $rowCount > 0 ? ($tot['fly_ash_kg'] / $rowCount) : 0,
    'total_kg' => $rowCount > 0 ? ($tot['total_kg'] / $rowCount) : 0,
];

$fmt = function ($val, $dec = 2) {
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
$pdf->SetMargins(6, 6, 6);
$pdf->SetAutoPageBreak(true, 10);

$widths = [
    'tgl' => 14,
    'pemakaian' => 26,
    'harga' => 24,
    'biaya' => 24,
    'total_biaya' => 30,
    'extractor' => 22,
    'cgrate' => 22,
    'fly_ash' => 22,
    'total_kg' => 20,
    'ket' => 81,
];
$tableWidth = array_sum($widths);

$fitKet = function ($text) use ($pdf, $widths) {
    $text = trim((string)$text);
    if ($text === '') return '';
    $maxW = $widths['ket'] - 2;
    while ($pdf->GetStringWidth($text) > $maxW && strlen($text) > 0) {
        $text = substr($text, 0, -1);
    }
    return $text;
};

$renderHeader = function () use ($pdf, $tableWidth, $widths, $monthLabel) {
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetFillColor(118, 146, 60);
    $pdf->Cell($tableWidth, 9, 'PEMAKAIAN BATU BARA STEAM 20TON, PEMBUANGAN BOTTOM ASH DAN FLY ASH', 1, 1, 'C', true);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Cell(28, 8, 'Bulan :', 1, 0, 'L', true);
    $pdf->Cell($tableWidth - 28, 8, $monthLabel, 1, 1, 'L', true);

    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(155, 194, 209);
    $pdf->Cell($widths['tgl'], 7, 'Tanggal', 1, 0, 'C', true);
    $pdf->Cell($widths['pemakaian'], 7, 'Pemakaian (KG)', 1, 0, 'C', true);
    $pdf->Cell($widths['harga'], 7, 'Harga/KG (Rp)', 1, 0, 'C', true);
    $pdf->Cell($widths['biaya'], 7, 'Biaya (Rp)', 1, 0, 'C', true);
    $pdf->SetFillColor(255, 192, 0);
    $pdf->Cell($widths['total_biaya'], 7, 'Total Biaya Boiler (Rp)', 1, 0, 'C', true);
    $pdf->SetFillColor(155, 194, 209);
    $pdf->Cell($widths['extractor'], 7, 'Extractor KG', 1, 0, 'C', true);
    $pdf->Cell($widths['cgrate'], 7, 'C/Grate KG', 1, 0, 'C', true);
    $pdf->Cell($widths['fly_ash'], 7, 'Fly Ash KG', 1, 0, 'C', true);
    $pdf->Cell($widths['total_kg'], 7, 'Total KG', 1, 0, 'C', true);
    $pdf->Cell($widths['ket'], 7, 'KET', 1, 1, 'C', true);
};

$pdf->AddPage();
$renderHeader();

$rowH = 6;
$pageBottom = $pdf->GetPageHeight() - 10;

if (empty($rows)) {
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell($tableWidth, 8, 'Tidak ada data pada rentang tanggal ini.', 1, 1, 'C');
} else {
    $pdf->SetFont('Arial', '', 8);
    foreach ($rows as $r) {
        if (($pdf->GetY() + $rowH) > $pageBottom) {
            $pdf->AddPage();
            $renderHeader();
            $pdf->SetFont('Arial', '', 8);
        }

        $pdf->SetFillColor(230, 239, 213);
        $pdf->Cell($widths['tgl'], $rowH, $fmtDay($r['tanggal']), 1, 0, 'C', true);
        $pdf->SetFillColor(216, 213, 184);
        $pdf->Cell($widths['pemakaian'], $rowH, $fmt($r['pemakaian_kg']), 1, 0, 'R', true);
        $pdf->Cell($widths['harga'], $rowH, $fmt($r['harga_rp_per_kg']), 1, 0, 'R', true);
        $pdf->Cell($widths['biaya'], $rowH, $fmt($r['biaya_rp']), 1, 0, 'R', true);
        $pdf->SetFillColor(255, 192, 0);
        $pdf->Cell($widths['total_biaya'], $rowH, $fmt($r['total_biaya_boiler_rp']), 1, 0, 'R', true);
        $pdf->SetFillColor(216, 213, 184);
        $pdf->Cell($widths['extractor'], $rowH, $fmt($r['extractor_kg']), 1, 0, 'R', true);
        $pdf->Cell($widths['cgrate'], $rowH, $fmt($r['cgrate_kg']), 1, 0, 'R', true);
        $pdf->Cell($widths['fly_ash'], $rowH, $fmt($r['fly_ash_kg']), 1, 0, 'R', true);
        $pdf->SetFillColor(255, 192, 0);
        $pdf->Cell($widths['total_kg'], $rowH, $fmt($r['total_kg']), 1, 0, 'R', true);
        $pdf->SetFillColor(216, 213, 184);
        $pdf->Cell($widths['ket'], $rowH, $fitKet($r['ket']), 1, 1, 'L', true);
    }

    if (($pdf->GetY() + ($rowH * 2)) > $pageBottom) {
        $pdf->AddPage();
        $renderHeader();
    }

    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(255, 210, 77);
    $pdf->Cell($widths['tgl'], $rowH, 'TOTAL', 1, 0, 'C', true);
    $pdf->Cell($widths['pemakaian'], $rowH, $fmt($tot['pemakaian_kg']), 1, 0, 'R', true);
    $pdf->Cell($widths['harga'], $rowH, '', 1, 0, 'C', true);
    $pdf->Cell($widths['biaya'], $rowH, $fmt($tot['biaya_rp']), 1, 0, 'R', true);
    $pdf->Cell($widths['total_biaya'], $rowH, $fmt($tot['total_biaya_boiler_rp']), 1, 0, 'R', true);
    $pdf->Cell($widths['extractor'], $rowH, $fmt($tot['extractor_kg']), 1, 0, 'R', true);
    $pdf->Cell($widths['cgrate'], $rowH, $fmt($tot['cgrate_kg']), 1, 0, 'R', true);
    $pdf->Cell($widths['fly_ash'], $rowH, $fmt($tot['fly_ash_kg']), 1, 0, 'R', true);
    $pdf->Cell($widths['total_kg'], $rowH, $fmt($tot['total_kg']), 1, 0, 'R', true);
    $pdf->Cell($widths['ket'], $rowH, '', 1, 1, 'C', true);

    $pdf->Cell($widths['tgl'], $rowH, 'RATA-RATA', 1, 0, 'C', true);
    $pdf->Cell($widths['pemakaian'], $rowH, $fmt($avg['pemakaian_kg']), 1, 0, 'R', true);
    $pdf->Cell($widths['harga'], $rowH, '', 1, 0, 'C', true);
    $pdf->Cell($widths['biaya'], $rowH, $fmt($avg['biaya_rp']), 1, 0, 'R', true);
    $pdf->Cell($widths['total_biaya'], $rowH, $fmt($avg['total_biaya_boiler_rp']), 1, 0, 'R', true);
    $pdf->Cell($widths['extractor'], $rowH, $fmt($avg['extractor_kg']), 1, 0, 'R', true);
    $pdf->Cell($widths['cgrate'], $rowH, $fmt($avg['cgrate_kg']), 1, 0, 'R', true);
    $pdf->Cell($widths['fly_ash'], $rowH, $fmt($avg['fly_ash_kg']), 1, 0, 'R', true);
    $pdf->Cell($widths['total_kg'], $rowH, $fmt($avg['total_kg']), 1, 0, 'R', true);
    $pdf->Cell($widths['ket'], $rowH, '', 1, 1, 'C', true);
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

$fileName = 'Report_bb_20t_lama_' . date('Ymd_His') . '.pdf';
$pdfContent = $pdf->Output('S');

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . strlen($pdfContent));
echo $pdfContent;
exit;
