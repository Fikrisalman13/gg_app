<?php
session_start();
ob_start();

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/libs/fpdf.php';

$menuId = 230;
requireView($conn, $menuId);

$start = trim($_POST['start_date'] ?? date('Y-m-01'));
$end = trim($_POST['end_date'] ?? date('Y-m-d'));

if ($start === '' || strtotime($start) === false) {
    $start = date('Y-m-01');
}
if ($end === '' || strtotime($end) === false) {
    $end = date('Y-m-d');
}
if (strtotime($start) > strtotime($end)) {
    $tmp = $start;
    $start = $end;
    $end = $tmp;
}

$tanks = [];
$stTank = sqlsrv_query($conn, "SELECT kode,nama,urutan FROM dbo.lpg_skid_tank_master WHERE is_active=1 ORDER BY urutan,kode");
if ($stTank) {
    $idx = 1;
    while ($t = sqlsrv_fetch_array($stTank, SQLSRV_FETCH_ASSOC)) {
        $urutan = isset($t['urutan']) ? (int)$t['urutan'] : $idx;
        if ($urutan <= 0) $urutan = $idx;
        $t['urutan'] = $urutan;
        $t['display_label'] = 'Pencatatan Pemakaian ' . $urutan;
        $tanks[] = $t;
        $idx++;
    }
    sqlsrv_free_stmt($stTank);
}

$rows = [];
$sql = "SELECT CAST(h.tanggal AS DATE) AS tanggal,h.tank_kode,m.nama AS tank_nama,m.urutan,h.pemakaian_kg,h.harga_rp,h.biaya_rp,h.ket
        FROM dbo.lpg_skid_tank_harian h
        INNER JOIN dbo.lpg_skid_tank_master m ON m.kode=h.tank_kode
        WHERE CAST(h.tanggal AS DATE) BETWEEN ? AND ?
        ORDER BY CAST(h.tanggal AS DATE) ASC, m.urutan ASC";
$stmt = sqlsrv_query($conn, $sql, [$start, $end]);
if ($stmt) {
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $dateObj = $r['tanggal'] ?? null;
        $dateKey = ($dateObj instanceof DateTime) ? $dateObj->format('Y-m-d') : date('Y-m-d', strtotime((string)$dateObj));
        $rows[] = [
            'tanggal' => $dateKey,
            'tank_kode' => (string)($r['tank_kode'] ?? ''),
            'tank_nama' => (string)($r['tank_nama'] ?? ''),
            'urutan' => (int)($r['urutan'] ?? 0),
            'pemakaian_kg' => (float)($r['pemakaian_kg'] ?? 0),
            'harga_rp' => (float)($r['harga_rp'] ?? 0),
            'biaya_rp' => (float)($r['biaya_rp'] ?? 0),
            'ket' => (string)($r['ket'] ?? ''),
        ];
    }
    sqlsrv_free_stmt($stmt);
}

$panelByUrutan = [];
foreach ($tanks as $t) {
    $u = (int)$t['urutan'];
    if ($u < 1 || $u > 4) continue;
    $panelByUrutan[$u] = $t;
}
for ($i = 1; $i <= 4; $i++) {
    if (!isset($panelByUrutan[$i])) {
        $panelByUrutan[$i] = ['kode' => '', 'nama' => '-', 'urutan' => $i, 'display_label' => 'Pencatatan Pemakaian ' . $i];
    }
}
ksort($panelByUrutan);

$map = [];
$dates = [];
$dailyTotal = [];
$totPem = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
$totBiaya = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
$countPerPanel = [1 => 0, 2 => 0, 3 => 0, 4 => 0];

foreach ($rows as $r) {
    $u = (int)$r['urutan'];
    if ($u < 1 || $u > 4) continue;
    $d = $r['tanggal'];
    if (!isset($map[$d])) $map[$d] = [];
    $map[$d][$u] = $r;
    $dates[$d] = true;
    if (!isset($dailyTotal[$d])) $dailyTotal[$d] = 0;
    $dailyTotal[$d] += $r['biaya_rp'];

    $totPem[$u] += $r['pemakaian_kg'];
    $totBiaya[$u] += $r['biaya_rp'];
    $countPerPanel[$u]++;
}

$dates = array_keys($dates);
sort($dates);

$avgPem = [];
$avgBiaya = [];
for ($i = 1; $i <= 4; $i++) {
    $avgPem[$i] = $countPerPanel[$i] > 0 ? ($totPem[$i] / $countPerPanel[$i]) : 0;
    $avgBiaya[$i] = $countPerPanel[$i] > 0 ? ($totBiaya[$i] / $countPerPanel[$i]) : 0;
}
$grandTotal = array_sum($dailyTotal);
$grandAvg = count($dates) > 0 ? ($grandTotal / count($dates)) : 0;

$fmtNum = function ($val, $dec = 2) {
    if (!is_numeric($val)) return '';
    return number_format((float)$val, $dec, '.', ',');
};
$fmtDay = function ($ymd) {
    return $ymd ? date('j', strtotime($ymd)) : '';
};
$fitText = function ($text, $maxLen = 22) {
    $text = trim((string)$text);
    if ($text === '') return '';
    if (strlen($text) <= $maxLen) return $text;
    return substr($text, 0, $maxLen - 3) . '...';
};

$monthMap = [
    'January' => 'JANUARI', 'February' => 'FEBRUARI', 'March' => 'MARET', 'April' => 'APRIL',
    'May' => 'MEI', 'June' => 'JUNI', 'July' => 'JULI', 'August' => 'AGUSTUS',
    'September' => 'SEPTEMBER', 'October' => 'OKTOBER', 'November' => 'NOVEMBER', 'December' => 'DESEMBER'
];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));

$pdf = new FPDF('L', 'mm', 'A3');
$pdf->SetMargins(6, 6, 6);
$pdf->SetAutoPageBreak(true, 10);

$colTgl = 8;
$colPakai = 20;
$colHarga = 20;
$colBiaya = 22;
$colKet = 28;
$panelWidth = $colTgl + $colPakai + $colHarga + $colBiaya + $colKet;
$colTotal = 16;
$tableWidth = ($panelWidth * 4) + $colTotal;

$renderHeader = function () use (
    $pdf,
    $panelByUrutan,
    $panelWidth,
    $tableWidth,
    $colTgl,
    $colPakai,
    $colHarga,
    $colBiaya,
    $colKet,
    $colTotal,
    $monthLabel
) {
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->SetFillColor(244, 180, 0);
    $pdf->Cell($tableWidth, 9, 'REPORT LPG SKID TANK', 1, 1, 'C', true);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(255, 242, 0);
    $pdf->Cell($tableWidth, 7, 'Bulan: ' . $monthLabel, 1, 1, 'L', true);

    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(244, 180, 0);
    for ($i = 1; $i <= 4; $i++) {
        $pdf->Cell($panelWidth, 7, $panelByUrutan[$i]['display_label'] ?? ('Pencatatan Pemakaian ' . $i), 1, 0, 'C', true);
    }
    $pdf->Cell($colTotal, 7, 'TOTAL', 1, 1, 'C', true);

    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetFillColor(244, 180, 0);
    for ($i = 1; $i <= 4; $i++) {
        $pdf->Cell($panelWidth, 6, $panelByUrutan[$i]['nama'] ?? '-', 1, 0, 'C', true);
    }
    $pdf->Cell($colTotal, 6, '', 1, 1, 'C', true);

    $pdf->SetFont('Arial', 'B', 7);
    for ($i = 1; $i <= 4; $i++) {
        $pdf->SetFillColor(255, 242, 0);
        $pdf->Cell($colTgl, 7, 'TGL', 1, 0, 'C', true);
        $pdf->Cell($colPakai, 7, 'Pemakaian (KG)', 1, 0, 'C', true);
        $pdf->Cell($colHarga, 7, 'Harga (Rp)', 1, 0, 'C', true);
        $pdf->Cell($colBiaya, 7, 'Biaya (Rp/hari)', 1, 0, 'C', true);
        $pdf->Cell($colKet, 7, 'KET', 1, 0, 'C', true);
    }
    $pdf->Cell($colTotal, 7, 'Biaya (Rp/hari)', 1, 1, 'C', true);
};

$pdf->AddPage();
$renderHeader();

$dataRowH = 6;
$pageBottom = $pdf->GetPageHeight() - 10;

if (empty($dates)) {
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell($tableWidth, 8, 'Tidak ada data pada rentang tanggal ini.', 1, 1, 'C');
} else {
    $pdf->SetFont('Arial', '', 7);
    foreach ($dates as $d) {
        if (($pdf->GetY() + $dataRowH) > $pageBottom) {
            $pdf->AddPage();
            $renderHeader();
            $pdf->SetFont('Arial', '', 7);
        }

        for ($i = 1; $i <= 4; $i++) {
            $row = $map[$d][$i] ?? null;
            $pdf->Cell($colTgl, $dataRowH, $fmtDay($d), 1, 0, 'C');
            $pdf->SetFillColor(255, 192, 0);
            $pdf->Cell($colPakai, $dataRowH, $row ? $fmtNum($row['pemakaian_kg']) : '', 1, 0, 'R', true);
            $pdf->SetFillColor(255, 242, 0);
            $pdf->Cell($colHarga, $dataRowH, $row ? $fmtNum($row['harga_rp']) : '', 1, 0, 'R', true);
            $pdf->SetFillColor(217, 217, 217);
            $pdf->Cell($colBiaya, $dataRowH, $row ? $fmtNum($row['biaya_rp']) : '', 1, 0, 'R', true);
            $pdf->Cell($colKet, $dataRowH, $row ? $fitText($row['ket']) : '', 1, 0, 'L', true);
        }
        $pdf->SetFillColor(255, 242, 0);
        $pdf->Cell($colTotal, $dataRowH, $fmtNum($dailyTotal[$d] ?? 0), 1, 1, 'R', true);
    }

    if (($pdf->GetY() + ($dataRowH * 2)) > $pageBottom) {
        $pdf->AddPage();
        $renderHeader();
    }

    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetFillColor(220, 230, 241);
    for ($i = 1; $i <= 4; $i++) {
        $pdf->Cell($colTgl, $dataRowH, 'TOTAL', 1, 0, 'C', true);
        $pdf->Cell($colPakai, $dataRowH, $fmtNum($totPem[$i]), 1, 0, 'R', true);
        $pdf->Cell($colHarga, $dataRowH, '', 1, 0, 'C', true);
        $pdf->Cell($colBiaya, $dataRowH, $fmtNum($totBiaya[$i]), 1, 0, 'R', true);
        $pdf->Cell($colKet, $dataRowH, '', 1, 0, 'C', true);
    }
    $pdf->Cell($colTotal, $dataRowH, $fmtNum($grandTotal), 1, 1, 'R', true);

    for ($i = 1; $i <= 4; $i++) {
        $pdf->Cell($colTgl, $dataRowH, 'RATA2', 1, 0, 'C', true);
        $pdf->Cell($colPakai, $dataRowH, $fmtNum($avgPem[$i]), 1, 0, 'R', true);
        $pdf->Cell($colHarga, $dataRowH, 'INCLUDE', 1, 0, 'C', true);
        $pdf->Cell($colBiaya, $dataRowH, $fmtNum($avgBiaya[$i]), 1, 0, 'R', true);
        $pdf->Cell($colKet, $dataRowH, '', 1, 0, 'C', true);
    }
    $pdf->Cell($colTotal, $dataRowH, $fmtNum($grandAvg), 1, 1, 'R', true);
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

$fileName = 'report_lpg_skid_tank_' . $start . '_sd_' . $end . '.pdf';
$pdfContent = $pdf->Output('S');

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . strlen($pdfContent));
echo $pdfContent;
exit;
