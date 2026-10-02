<?php
ob_start();
session_start();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi3.php';
require_once __DIR__ . '/routing_692_data.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Border;

$selectedRoutingGroupId = isset($_GET['routing_group_id']) && preg_match('/^\d+$/', (string) $_GET['routing_group_id']) ? (string) $_GET['routing_group_id'] : '';
$selectedRoutingIds = $_GET['routing_ids'] ?? ['692'];
if (!is_array($selectedRoutingIds)) { $selectedRoutingIds = preg_split('/[\s,]+/', $selectedRoutingIds); }
$result = routing692FetchData($conn3, $_GET['tanggal_awal'] ?? date('Y-m-d') . ' 00:00', $_GET['tanggal_akhir'] ?? date('Y-m-d') . ' 23:59', $selectedRoutingIds);
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
$sheet->setTitle('LKP OUTPUT DYEING');
$sheet->mergeCells('A1:D1');
$sheet->setCellValue('A1', 'Laporan LKP OUTPUT DYEING');
$sheet->setCellValue('A2', 'Periode');
$sheet->setCellValue('B2', $startDate . ' s/d ' . $endDate);
$sheet->setCellValue('A3', 'Grup Routing');
$sheet->setCellValue('B3', $selectedRoutingGroupId !== '' ? $selectedRoutingGroupId : '-');
$sheet->setCellValue('A4', 'Routing');
$sheet->setCellValue('B4', implode(', ', $selectedRoutingIds));
$sheet->setCellValue('B6', 'Summary');
$sheet->setCellValue('B7', 'Total Qty Permartaian Meter (M)');
$sheet->setCellValue('C7', $summary['total_pemartaian_m']);
$sheet->setCellValue('B8', 'Total Qty Pemartaian Yard (Y)');
$sheet->setCellValue('C8', $summary['total_pemartaian_y']);
$sheet->setCellValue('B9', 'Total Qty Produksi');
$sheet->setCellValue('C9', $summary['total_qty_produksi']);
$sheet->setCellValue('B10', 'Jumlah Baris Data');
$sheet->setCellValue('C10', $summary['total_rows']);

$headerRow = 12;
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
$sheet->getStyle('B6:C10')->getFont()->setBold(true);
$sheet->getStyle('C7:C10')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
if ($columns) {
    $lastColumn = Coordinate::stringFromColumnIndex(count($columns));
    $lastRow = max($headerRow, $rowNum - 1);
    $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $headerRow)->getFont()->setBold(true);
    $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}
$sheet->getColumnDimension('A')->setAutoSize(false);
$sheet->getColumnDimension('A')->setWidth(6);
foreach (range(2, max(2, count($columns))) as $col) {
    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
}

$filename = 'laporan_verpacking_' . $startDate . '_' . $endDate . '.xlsx';
while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
(new Xlsx($spreadsheet))->save('php://output');
exit;