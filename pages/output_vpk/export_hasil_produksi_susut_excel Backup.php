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

$result = hasilProduksiSusutFetchData($conn3, $_GET['tanggal_awal'] ?? date('Y-m-d'), $_GET['tanggal_akhir'] ?? date('Y-m-d'), 1000);
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

$headerRow = 11;
foreach ($columns as $i => $column) {
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $headerRow, $column);
}
$rowNum = $headerRow + 1;
foreach ($rows as $row) {
    foreach ($columns as $i => $column) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $rowNum, $row[$column] ?? '');
    }
    $rowNum++;
}
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('B4:C9')->getFont()->setBold(true);
$sheet->getStyle('C5:C9')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
if ($columns) {
    $lastColumn = Coordinate::stringFromColumnIndex(count($columns));
    $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $headerRow)->getFont()->setBold(true);
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
