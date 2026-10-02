<?php
session_start();
ob_start();

require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../libs/fpdf.php';
require_once __DIR__ . '/rekap_biaya_energi_data.php';

$menuId = 230;
requireView($conn, $menuId);

$start = normalizeDateInputRekapBiayaEnergi($_POST['start_date'] ?? date('Y-m-01'));
$end = normalizeDateInputRekapBiayaEnergi($_POST['end_date'] ?? date('Y-m-d'));

if ($start === '') {
    $start = date('Y-m-01');
}
if ($end === '') {
    $end = date('Y-m-d');
}
if (strtotime($start) > strtotime($end)) {
    $tmp = $start;
    $start = $end;
    $end = $tmp;
}

$errorMsg = '';
$data = buildRekapBiayaEnergiData($conn, $start, $end, $errorMsg);
if ($errorMsg !== '') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo '<b>Gagal menyiapkan export PDF:</b> ' . htmlspecialchars($errorMsg);
    exit;
}

$monthLabel = monthLabelRekapBiayaEnergi($start);

$columns = [
    ['key' => 'tanggal', 'width' => 14, 'header' => "Tanggal", 'hfill' => [217, 217, 201], 'dfill' => [240, 244, 227], 'align' => 'C'],
    ['key' => 'biaya_listrik', 'width' => 21, 'header' => "Biaya Listrik", 'hfill' => [255, 242, 0], 'dfill' => [255, 242, 0], 'align' => 'R'],
    ['key' => 'biaya_kimia_ipab', 'width' => 21, 'header' => "Biaya Chemical\nAir IPAB", 'hfill' => [149, 179, 215], 'dfill' => [184, 204, 228], 'align' => 'R'],
    ['key' => 'biaya_kimia_ipal', 'width' => 21, 'header' => "Biaya Kimia\nIPAL", 'hfill' => [217, 217, 217], 'dfill' => [217, 217, 217], 'align' => 'R'],
    ['key' => 'biaya_kimia_boiler', 'width' => 21, 'header' => "BIAYA KIMIA\nBOILER", 'hfill' => [217, 217, 217], 'dfill' => [217, 217, 217], 'align' => 'R'],
    ['key' => 'biaya_kimia_weaving', 'width' => 21, 'header' => "BIAYA KIMIA\nWEAVING", 'hfill' => [217, 217, 217], 'dfill' => [217, 217, 217], 'align' => 'R'],
    ['key' => 'biaya_bb_wuxi', 'width' => 21, 'header' => "Biaya Batu Bara\nBoiler Wuxi", 'hfill' => [230, 184, 175], 'dfill' => [201, 201, 201], 'align' => 'R'],
    ['key' => 'biaya_bb_oil_xineng', 'width' => 21, 'header' => "Biaya Batu Bara\nBoiler OIL\nXINENG", 'hfill' => [230, 184, 175], 'dfill' => [201, 201, 201], 'align' => 'R'],
    ['key' => 'biaya_bb_20t_lama', 'width' => 21, 'header' => "Biaya Batu Bara\nBoiler STEAM\n20TON LAMA", 'hfill' => [230, 184, 175], 'dfill' => [201, 201, 201], 'align' => 'R'],
    ['key' => 'biaya_bb_20t_baru_longchuan', 'width' => 21, 'header' => "Biaya Batu Bara\nBoiler STEAM 20T\nBARU LONGCHUAN", 'hfill' => [230, 184, 175], 'dfill' => [201, 201, 201], 'align' => 'R'],
    ['key' => 'biaya_bb_21t_actom', 'width' => 21, 'header' => "Biaya Batu Bara\nBoiler STEAM\n21T ACTOM", 'hfill' => [230, 184, 175], 'dfill' => [201, 201, 201], 'align' => 'R'],
    ['key' => 'biaya_bb_jineng', 'width' => 21, 'header' => "Biaya Batu Bara\nBoiler JINENG", 'hfill' => [230, 184, 175], 'dfill' => [201, 201, 201], 'align' => 'R'],
    ['key' => 'biaya_lpg_skid_tank', 'width' => 21, 'header' => "Biaya Gas LPG\nSkid Tank", 'hfill' => [255, 242, 0], 'dfill' => [255, 242, 0], 'align' => 'R'],
    ['key' => 'total_biaya', 'width' => 19, 'header' => "Total Biaya", 'hfill' => [255, 192, 0], 'dfill' => [255, 192, 0], 'align' => 'R'],
];

$pdf = new FPDF('L', 'mm', 'A4');
$pdf->SetMargins(6, 6, 6);
$pdf->SetAutoPageBreak(true, 10);
$totalWidth = 0;
foreach ($columns as $col) {
    $totalWidth += $col['width'];
}

$setFill = function (array $rgb) use ($pdf) {
    $pdf->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
};

$drawMultilineCell = function ($w, $h, $text, $align, $fill, $lineHeight = 3.4) use ($pdf) {
    $x = $pdf->GetX();
    $y = $pdf->GetY();

    $pdf->Cell($w, $h, '', 1, 0, $align, $fill);

    $lines = explode("\n", (string)$text);
    $lineCount = count($lines);
    $textHeight = $lineCount * $lineHeight;
    $startY = $y + max(0, ($h - $textHeight) / 2);

    foreach ($lines as $line) {
        $pdf->SetXY($x, $startY);
        $pdf->Cell($w, $lineHeight, trim($line), 0, 0, $align, false);
        $startY += $lineHeight;
    }
    $pdf->SetXY($x + $w, $y);
};

$renderTitleAndHeader = function () use ($pdf, $columns, $setFill, $drawMultilineCell, $monthLabel, $start, $end, $totalWidth) {
    $headerRowHeight = 13;

    $pdf->SetFont('Arial', 'B', 14);
    $setFill([255, 242, 0]);
    $pdf->Cell($totalWidth, 10, 'REKAPAN BIAYA ENERGI', 1, 1, 'C', true);

    $pdf->SetFont('Arial', 'B', 11);
    $setFill([239, 232, 184]);
    $pdf->Cell($totalWidth, 8, 'Bulan: ' . $monthLabel . ' | Periode: ' . $start . ' s/d ' . $end, 1, 1, 'C', true);

    $pdf->SetFont('Arial', 'B', 7.0);
    foreach ($columns as $col) {
        $setFill($col['hfill']);
        $drawMultilineCell($col['width'], $headerRowHeight, $col['header'], 'C', true, 3.4);
    }
    $pdf->Ln($headerRowHeight);

    $pdf->SetFont('Arial', 'B', 6.2);
    foreach ($columns as $idx => $col) {
        $setFill([252, 229, 205]);
        $unitText = $idx === 0 ? '' : '(Rp)';
        $pdf->Cell($col['width'], 6, $unitText, 1, 0, 'C', true);
    }
    $pdf->Ln();
};

$newPage = function () use ($pdf, $renderTitleAndHeader) {
    $pdf->AddPage();
    $renderTitleAndHeader();
};

$newPage();

$dataRowHeight = 6;
$summaryRowHeight = 6;
$bottomMargin = 10;
$pageHeight = $pdf->GetPageHeight();

if (empty($data['rows'])) {
    $pdf->SetFont('Arial', '', 7);
    $pdf->Cell($totalWidth, 8, 'Tidak ada data pada rentang tanggal ini.', 1, 1, 'C');
} else {
    $pdf->SetFont('Arial', '', 6.4);
    foreach ($data['rows'] as $row) {
        if (($pdf->GetY() + $dataRowHeight) > ($pageHeight - $bottomMargin)) {
            $newPage();
            $pdf->SetFont('Arial', '', 6.4);
        }

        foreach ($columns as $col) {
            $setFill($col['dfill']);
            if ($col['key'] === 'tanggal') {
                $value = fmtDayRekapBiayaEnergi($row['tanggal'] ?? '');
                $align = 'C';
            } else {
                $value = fmtNumRekapBiayaEnergi($row[$col['key']] ?? 0);
                $align = 'C';
            }
            $pdf->Cell($col['width'], $dataRowHeight, $value, 1, 0, $align, true);
        }
        $pdf->Ln();
    }

    if (($pdf->GetY() + ($summaryRowHeight * 2)) > ($pageHeight - $bottomMargin)) {
        $newPage();
    }

    $pdf->SetFont('Arial', 'B', 6.5);
    foreach ($columns as $col) {
        $setFill([255, 242, 0]);
        if ($col['key'] === 'tanggal') {
            $value = 'TOTAL';
            $align = 'C';
        } else {
            $value = fmtNumRekapBiayaEnergi($data['totals'][$col['key']] ?? 0);
            $align = 'C';
        }
        $pdf->Cell($col['width'], $summaryRowHeight, $value, 1, 0, $align, true);
    }
    $pdf->Ln();

    foreach ($columns as $col) {
        $setFill([255, 242, 0]);
        if ($col['key'] === 'tanggal') {
            $value = 'RATA-RATA';
            $align = 'C';
        } else {
            $value = fmtNumRekapBiayaEnergi($data['averages'][$col['key']] ?? 0);
            $align = 'C';
        }
        $pdf->Cell($col['width'], $summaryRowHeight, $value, 1, 0, $align, true);
    }
    $pdf->Ln();
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

$fileName = 'rekapan_biaya_energi_' . $start . '_sd_' . $end . '.pdf';
$pdfContent = $pdf->Output('S');

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . strlen($pdfContent));
echo $pdfContent;
exit;
