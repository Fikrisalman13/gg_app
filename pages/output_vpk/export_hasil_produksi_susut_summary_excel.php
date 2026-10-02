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

$startDate = $_GET['tanggal_awal'] ?? date('Y-m-d');
$endDate = $_GET['tanggal_akhir'] ?? date('Y-m-d');

$result = hasilProduksiSusutFetchSummaryByDate($conn3, $startDate, $endDate);
extract($result);

if (!empty($errors)) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: text/plain; charset=utf-8');
    die(implode("\n", $errors));
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('SUMMARY SUSUT');

// Header Grouping Row 1 & Row 2
$sheet->setCellValue('A1', 'TGL');
$sheet->mergeCells('A1:A2');

$sheet->setCellValue('B1', 'Greige Asal');
$sheet->mergeCells('B1:C1');
$sheet->setCellValue('B2', 'Meter');
$sheet->setCellValue('C2', 'Yard');

$sheet->setCellValue('D1', 'A1');
$sheet->mergeCells('D1:F1');
$sheet->setCellValue('D2', 'Meter');
$sheet->setCellValue('E2', 'Yard');
$sheet->setCellValue('F2', 'Total/M');

$sheet->setCellValue('G1', 'A2');
$sheet->mergeCells('G1:I1');
$sheet->setCellValue('G2', 'Meter');
$sheet->setCellValue('H2', 'Yard');
$sheet->setCellValue('I2', 'Total/M');

$sheet->setCellValue('J1', 'A3');
$sheet->mergeCells('J1:M1');
$sheet->setCellValue('J2', 'Kg');
$sheet->setCellValue('K2', 'Meter');
$sheet->setCellValue('L2', 'Yard');
$sheet->setCellValue('M2', 'Total/M');

$sheet->setCellValue('N1', 'B1');
$sheet->mergeCells('N1:P1');
$sheet->setCellValue('N2', 'Meter');
$sheet->setCellValue('O2', 'Yard');
$sheet->setCellValue('P2', 'Total/M');

$sheet->setCellValue('Q1', 'B2');
$sheet->mergeCells('Q1:S1');
$sheet->setCellValue('Q2', 'Meter');
$sheet->setCellValue('R2', 'Yard');
$sheet->setCellValue('S2', 'Total/M');

$sheet->setCellValue('T1', 'B3');
$sheet->mergeCells('T1:W1');
$sheet->setCellValue('T2', 'Kg');
$sheet->setCellValue('U2', 'Meter');
$sheet->setCellValue('V2', 'Yard');
$sheet->setCellValue('W2', 'Total/M');

$sheet->setCellValue('X1', 'C1');
$sheet->mergeCells('X1:AA1');
$sheet->setCellValue('X2', 'Kg');
$sheet->setCellValue('Y2', 'Meter');
$sheet->setCellValue('Z2', 'Yard');
$sheet->setCellValue('AA2', 'Total/M');

$sheet->setCellValue('AB1', 'Meter');
$sheet->mergeCells('AB1:AB2');

$sheet->setCellValue('AC1', 'Susut');
$sheet->mergeCells('AC1:AC2');

$sheet->setCellValue('AD1', 'Persentase Susut');
$sheet->mergeCells('AD1:AD2');

// Populate Data
$currentRow = 3;
$fieldKeys = [
    'greige_meter',     // B
    'greige_yard',      // C
    'a1_meter',         // D
    'a1_yard',          // E
    'total_a1_meter',   // F
    'a2_meter',         // G
    'a2_yard',          // H
    'total_a2_meter',   // I
    'a3_kg',            // J
    'a3_meter',         // K
    'a3_yard',          // L
    'total_a3_meter',   // M
    'b1_meter',         // N
    'b1_yard',          // O
    'total_b1_meter',   // P
    'b2_meter',         // Q
    'b2_yard',          // R
    'total_b2_meter',   // S
    'b3_kg',            // T
    'b3_meter',         // U
    'b3_yard',          // V
    'total_b3_meter',   // W
    'c1_kg',            // X
    'c1_meter',         // Y
    'c1_yard',          // Z
    'total_c1_meter',   // AA
    'total_hasil_meter',// AB
    'qty_susut_meter',  // AC
];

foreach ($rows as $row) {
    $tglLabel = !empty($row['tgl_selesai']) ? strtoupper(date('d-M-y', strtotime($row['tgl_selesai']))) : '-';
    $sheet->setCellValue('A' . $currentRow, $tglLabel);

    foreach ($fieldKeys as $idx => $key) {
        $colLetter = Coordinate::stringFromColumnIndex($idx + 2); // Start from column B (index 2)
        $sheet->setCellValue($colLetter . $currentRow, (float)($row[$key] ?? 0));
    }

    $persenSusut = (float)($row['persen_susut'] ?? 0) / 100;
    $sheet->setCellValue('AD' . $currentRow, $persenSusut);

    $currentRow++;
}

// Grand Total Row
if (!empty($rows)) {
    $sheet->setCellValue('A' . $currentRow, '');
    foreach ($fieldKeys as $idx => $key) {
        $colLetter = Coordinate::stringFromColumnIndex($idx + 2);
        $sheet->setCellValue($colLetter . $currentRow, (float)($grandTotal[$key] ?? 0));
    }
    $grandPersenSusut = (float)($grandTotal['persen_susut'] ?? 0) / 100;
    $sheet->setCellValue('AD' . $currentRow, $grandPersenSusut);

    // Bold on Grand Total
    $sheet->getStyle('A' . $currentRow . ':AD' . $currentRow)->getFont()->setBold(true);
}

$lastRow = max(2, $currentRow);

// Formatting & Styling
// Header styling
$sheet->getStyle('A1:AD2')->getFont()->setBold(true);
$sheet->getStyle('A1:AD2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

// Data column alignments & number formats
if ($lastRow >= 3) {
    $sheet->getStyle('A3:A' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('B3:AC' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('AD3:AD' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    // Number format for quantities (4 decimals)
    $sheet->getStyle('B3:AC' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.0000');
    // Percentage format
    $sheet->getStyle('AD3:AD' . $lastRow)->getNumberFormat()->setFormatCode('0.00%');
}

// Borders
$sheet->getStyle('A1:AD' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Column Auto-width
foreach (range(1, 30) as $colIdx) {
    $colLetter = Coordinate::stringFromColumnIndex($colIdx);
    $sheet->getColumnDimension($colLetter)->setAutoSize(true);
}

$filename = 'laporan_hasil_produksi_susut_summary_' . $startDate . '_' . $endDate . '.xlsx';
while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
(new Xlsx($spreadsheet))->save('php://output');
exit;
