<?php
if (function_exists('ini_set')) {
    @ini_set('display_errors', '0');
    @ini_set('display_startup_errors', '0');
}
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);

while (ob_get_level() > 0) {
    @ob_end_clean();
}
ob_start();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

require_once __DIR__ . '/../../../libs/fpdf.php';

if (!defined('CP_SUMMARY_EXPORT_BOOTSTRAP')) {
    define('CP_SUMMARY_EXPORT_BOOTSTRAP', true);
}
require __DIR__ . '/cp_planning_summary.php';

$summaryMatrixDates = is_array($summaryMatrixDates ?? null) ? $summaryMatrixDates : [];
$summaryMatrixRows = is_array($summaryMatrixRows ?? null) ? $summaryMatrixRows : [];
$summaryMatrixGrandCp = (int)($summaryMatrixGrandCp ?? 0);
$summaryMatrixGrandQty = (float)($summaryMatrixGrandQty ?? 0);
$summaryMatrixGrandQtyRealisasi = (float)($summaryMatrixGrandQtyRealisasi ?? 0);
$summaryMatrixGrandQtyPct = isset($summaryMatrixGrandQtyPct) ? $summaryMatrixGrandQtyPct : null;

$toPdfText = static function ($value): string {
    $text = trim((string)$value);
    if ($text === '') {
        return '';
    }
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            return $converted;
        }
    }
    return preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';
};

$dateCount = count($summaryMatrixDates);
$metaPlanType = trim((string)($selectedPlanType ?? '')) !== '' ? (string)$selectedPlanType : 'Semua';
$metaPeriod = (string)($periodLabel ?? ((string)($fromDate ?? '-') . ' s/d ' . (string)($toDate ?? '-')));
$metaGenerated = (string)($summaryGeneratedAt ?? date('d/m/Y H:i'));

$pdf = new FPDF('L', 'mm', 'A3');
$pdf->SetMargins(6, 6, 6);
$pdf->SetAutoPageBreak(true, 8);
$pdf->AddPage();

$pageWidth = $pdf->GetPageWidth();
$innerWidth = $pageWidth - 12.0;
$wMachine = 36.0;
$wCapacity = 22.0;
$groupCount = max(1, $dateCount + 1); // +1 untuk grup "Total Qty"
$wDateGroup = ($innerWidth - ($wMachine + $wCapacity)) / $groupCount;

if ($wDateGroup < 12.0) {
    $wMachine = 30.0;
    $wCapacity = 18.0;
    $wDateGroup = ($innerWidth - ($wMachine + $wCapacity)) / $groupCount;
}
if ($wDateGroup < 8.0) {
    $wDateGroup = 8.0;
}

$tableWidth = $wMachine + $wCapacity + ($groupCount * $wDateGroup);
$startX = ($pageWidth - $tableWidth) / 2.0;

$hHead = 6.0;
$hRow = 5.4;
$fontHead = 6.6;
$fontSubHead = 6.2;
$fontBody = 6.0;
$fontTotal = 6.6;
if ($wDateGroup >= 26.0) {
    $hHead = 7.0;
    $hRow = 6.0;
    $fontHead = 7.6;
    $fontSubHead = 6.8;
    $fontBody = 6.8;
    $fontTotal = 7.0;
} elseif ($wDateGroup >= 20.0) {
    $hHead = 6.5;
    $hRow = 5.7;
    $fontHead = 7.0;
    $fontSubHead = 6.4;
    $fontBody = 6.3;
    $fontTotal = 6.8;
}

$subW1 = $wDateGroup * 0.18;
$subW2 = $wDateGroup * 0.25;
$subW3 = $wDateGroup * 0.34;
$subW4 = $wDateGroup - ($subW1 + $subW2 + $subW3);

$renderTableHeader = static function () use (
    $pdf,
    $startX,
    $hHead,
    $wMachine,
    $wCapacity,
    $wDateGroup,
    $subW1,
    $subW2,
    $subW3,
    $subW4,
    $summaryMatrixDates,
    $toPdfText,
    $fontHead,
    $fontSubHead
) {
    $pdf->SetFont('Arial', 'B', $fontHead);
    $pdf->SetFillColor(221, 229, 241);
    $pdf->SetX($startX);
    $pdf->Cell($wMachine, $hHead * 2, $toPdfText('Mesin'), 1, 0, 'C', true);
    $pdf->Cell($wCapacity, $hHead * 2, $toPdfText('Kapasitas'), 1, 0, 'C', true);
    foreach ($summaryMatrixDates as $dtIso) {
        $pdf->Cell($wDateGroup, $hHead, $toPdfText(cpsSummaryFormatDateCell($dtIso)), 1, 0, 'C', true);
    }
    $pdf->Cell($wDateGroup, $hHead, $toPdfText('Total Qty'), 1, 0, 'C', true);
    $pdf->Ln($hHead);

    $pdf->SetX($startX + $wMachine + $wCapacity);
    $pdf->SetFont('Arial', 'B', $fontSubHead);
    $pdf->SetFillColor(238, 243, 250);
    foreach ($summaryMatrixDates as $dtIso) {
        $pdf->Cell($subW1, $hHead, 'CP', 1, 0, 'C', true);
        $pdf->Cell($subW2, $hHead, 'Qty', 1, 0, 'C', true);
        $pdf->Cell($subW3, $hHead, 'Qty Real', 1, 0, 'C', true);
        $pdf->Cell($subW4, $hHead, '%', 1, 0, 'C', true);
    }
    $pdf->Cell($subW1, $hHead, 'CP', 1, 0, 'C', true);
    $pdf->Cell($subW2, $hHead, 'Qty', 1, 0, 'C', true);
    $pdf->Cell($subW3, $hHead, 'Qty Real', 1, 0, 'C', true);
    $pdf->Cell($subW4, $hHead, '%', 1, 0, 'C', true);
    $pdf->Ln($hHead);
};

$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 8, $toPdfText('CP Planning Summary'), 0, 1, 'L');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 5.5, $toPdfText('Tipe Planning: ' . $metaPlanType), 0, 1, 'L');
$pdf->Cell(0, 5.5, $toPdfText('Periode: ' . $metaPeriod), 0, 1, 'L');
$pdf->Cell(0, 5.5, $toPdfText('Generated: ' . $metaGenerated), 0, 1, 'L');
$pdf->Ln(1.6);

$renderTableHeader();
$pdf->SetFont('Arial', '', $fontBody);
foreach ($summaryMatrixRows as $row) {
    if (($pdf->GetY() + $hRow) > ($pdf->GetPageHeight() - 10)) {
        $pdf->AddPage();
        $renderTableHeader();
        $pdf->SetFont('Arial', '', $fontBody);
    }

    $pdf->SetX($startX);
    $pdf->Cell($wMachine, $hRow, $toPdfText((string)($row['machine_name'] ?? '')), 1, 0, 'C');
    $pdf->Cell($wCapacity, $hRow, $toPdfText((string)($row['capacity'] ?? '')), 1, 0, 'C');

    foreach (($row['dates'] ?? []) as $cell) {
        $pdf->Cell($subW1, $hRow, $toPdfText((string)($cell['cp'] ?? '')), 1, 0, 'C');
        $pdf->Cell($subW2, $hRow, $toPdfText((string)($cell['qty'] ?? '')), 1, 0, 'C');
        $pdf->Cell($subW3, $hRow, $toPdfText((string)($cell['qty_realisasi'] ?? '')), 1, 0, 'C');
        $pdf->Cell($subW4, $hRow, $toPdfText((string)($cell['qty_pct'] ?? '')), 1, 0, 'C');
    }

    $pdf->Cell($subW1, $hRow, $toPdfText((string)($row['total_cp'] ?? '')), 1, 0, 'C');
    $pdf->Cell($subW2, $hRow, $toPdfText((string)($row['total_qty'] ?? '')), 1, 0, 'C');
    $pdf->Cell($subW3, $hRow, $toPdfText((string)($row['total_qty_realisasi'] ?? '')), 1, 0, 'C');
    $pdf->Cell($subW4, $hRow, $toPdfText((string)($row['total_qty_pct'] ?? '')), 1, 0, 'C');
    $pdf->Ln($hRow);
}

$pdf->SetX($startX);
$pdf->SetFont('Arial', 'B', $fontTotal);
$pdf->SetFillColor(232, 238, 247);
$mergeWidth = $wMachine + $wCapacity + ($dateCount * $wDateGroup);
$pdf->Cell($mergeWidth, $hHead, 'Total', 1, 0, 'C', true);
$pdf->Cell($subW1, $hHead, $toPdfText($summaryMatrixGrandCp > 0 ? (string)$summaryMatrixGrandCp : ''), 1, 0, 'C', true);
$pdf->Cell($subW2, $hHead, $toPdfText($summaryMatrixGrandQty > 0 ? cpsSummaryFormatQty($summaryMatrixGrandQty) : ''), 1, 0, 'C', true);
$pdf->Cell($subW3, $hHead, $toPdfText($summaryMatrixGrandQtyRealisasi > 0 ? cpsSummaryFormatQty($summaryMatrixGrandQtyRealisasi) : ''), 1, 0, 'C', true);
$pdf->Cell($subW4, $hHead, $toPdfText($summaryMatrixGrandQtyPct !== null ? cpsSummaryFormatPct($summaryMatrixGrandQtyPct) : ''), 1, 0, 'C', true);
$pdf->Ln($hHead);

while (ob_get_level() > 0) {
    @ob_end_clean();
}

$fileName = 'cp_planning_summary_' . date('Ymd_His') . '.pdf';
$pdfContent = $pdf->Output('S');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . strlen($pdfContent));
echo $pdfContent;
exit;
