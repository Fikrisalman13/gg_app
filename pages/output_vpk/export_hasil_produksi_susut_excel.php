<?php
ob_start();
session_start();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi3.php';
require_once __DIR__ . '/hasil_produksi_susut_data.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

$result = hasilProduksiSusutFetchData($conn3, $_GET['tanggal_awal'] ?? date('Y-m-d'), $_GET['tanggal_akhir'] ?? date('Y-m-d'));
extract($result);
if ($errors) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: text/plain; charset=utf-8');
    die(implode("\n", $errors));
}
$columns = $rows ? array_keys($rows[0]) : [];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('HASIL SUSUT');
$sheet->mergeCells('A1:D1');
$sheet->setCellValue('A1', 'Laporan Tarikan Susut Verpacking');
$sheet->setCellValue('A2', 'Periode');
$sheet->setCellValue('B2', $startDate . ' s/d ' . $endDate);
$sheet->setCellValue('B4', 'Summary');
$sheet->setCellValue('B5', 'Jumlah Baris Data');
$sheet->setCellValue('C5', $summary['total_rows']);
$sheet->setCellValue('B6', 'Total Greige Meter');
$sheet->setCellValue('C6', $summary['total_greige_meter']);
$sheet->setCellValue('B7', 'Total Hasil Meter');
$sheet->setCellValue('C7', $summary['total_hasil_meter']);
$sheet->setCellValue('B8', 'Total Susut Meter');
$sheet->setCellValue('C8', $summary['total_susut_meter']);
$sheet->setCellValue('B9', 'Susut Rata-rata (%)');
$sheet->setCellValue('C9', $summary['susut_percent']);
$gradeSummary = [
    ['Total Meter A1', 'total_a1_meter'],
    ['Total Meter A2', 'total_a2_meter'],
    ['Total Meter A3', 'total_a3_meter'],
    ['Total Meter B1', 'total_b1_meter'],
    ['Total Meter B2', 'total_b2_meter'],
    ['Total Meter B3', 'total_b3_meter'],
    ['Total Meter C1', 'total_c1_meter'],
];
$summaryRow = 10;
$gradeSummaryRow = 5;
$sheet->setCellValue('E4', 'Summary Grade');
foreach ($gradeSummary as $i => [$label, $key]) {
    $row = $gradeSummaryRow + $i;
    $sheet->setCellValue('E' . $row, $label);
    $sheet->setCellValue('F' . $row, $summary[$key] ?? 0);
}
$lastSummaryRow = max(9, $gradeSummaryRow + count($gradeSummary) - 1);

$headerRow = $lastSummaryRow + 2;
$subHeaderRow = $headerRow + 1;
$groupHeaders = [
    'A1 Meter' => ['title' => 'A1', 'columns' => ['A1 Meter' => 'Meter', 'A1 Yard' => 'Yard', 'Total A1 Meter' => 'Total Meter']],
    'A2 Meter' => ['title' => 'A2', 'columns' => ['A2 Meter' => 'Meter', 'A2 Yard' => 'Yard', 'Total A2 Meter' => 'Total Meter']],
    'A3 KG' => ['title' => 'A3', 'columns' => ['A3 KG' => 'KG', 'A3 Meter' => 'Meter', 'A3 Yard' => 'Yard', 'Total A3 Meter' => 'Total Meter']],
    'B1 Meter' => ['title' => 'B1', 'columns' => ['B1 Meter' => 'Meter', 'B1 Yard' => 'Yard', 'Total B1 Meter' => 'Total Meter']],
    'B2 Meter' => ['title' => 'B2', 'columns' => ['B2 Meter' => 'Meter', 'B2 Yard' => 'Yard', 'Total B2 Meter' => 'Total Meter']],
    'B3 KG' => ['title' => 'B3', 'columns' => ['B3 KG' => 'KG', 'B3 Meter' => 'Meter', 'B3 Yard' => 'Yard', 'Total B3 Meter' => 'Total Meter']],
    'C1 KG' => ['title' => 'C1', 'columns' => ['C1 KG' => 'KG', 'C1 Meter' => 'Meter', 'C1 Yard' => 'Yard', 'Total C1 Meter' => 'Total Meter']],
];
$colIndex = 1;
for ($i = 0; $i < count($columns); $i++) {
    $column = $columns[$i];
    if (isset($groupHeaders[$column])) {
        $group = $groupHeaders[$column];
        $startColumn = Coordinate::stringFromColumnIndex($colIndex);
        $endColumn = Coordinate::stringFromColumnIndex($colIndex + count($group['columns']) - 1);
        $sheet->mergeCells($startColumn . $headerRow . ':' . $endColumn . $headerRow);
        $sheet->setCellValue($startColumn . $headerRow, $group['title']);
        foreach ($group['columns'] as $originalColumn => $shortLabel) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex) . $subHeaderRow, $shortLabel);
            $colIndex++;
            $i++;
        }
        $i--;
    } else {
        $cell = Coordinate::stringFromColumnIndex($colIndex);
        $sheet->mergeCells($cell . $headerRow . ':' . $cell . $subHeaderRow);
        $sheet->setCellValue($cell . $headerRow, $column);
        $colIndex++;
    }
}
$rowNum = $subHeaderRow + 1;
foreach ($rows as $row) {
    foreach ($columns as $i => $column) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $rowNum, $row[$column] ?? '');
    }
    $rowNum++;
}
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('B4:F' . $lastSummaryRow)->getFont()->setBold(true);
$sheet->getStyle('C5:C' . $lastSummaryRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle('F' . $gradeSummaryRow . ':F' . $lastSummaryRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle('C5:C' . $lastSummaryRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$sheet->getStyle('F' . $gradeSummaryRow . ':F' . $lastSummaryRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
if ($columns) {
    $lastColumn = Coordinate::stringFromColumnIndex(count($columns));
    $lastRow = max($headerRow, $rowNum - 1);
    $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $subHeaderRow)->getFont()->setBold(true);
    $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $subHeaderRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}
$sheet->getColumnDimension('A')->setAutoSize(false);
$sheet->getColumnDimension('A')->setWidth(6);
foreach (range(2, max(2, count($columns))) as $col) {
    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
}

$filename = 'laporan_hasil_produksi_susut_' . $startDate . '_' . $endDate . '.xlsx';
while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
(new Xlsx($spreadsheet))->save('php://output');
exit;
