<?php
session_start();

if (!isset($_SESSION['UserName'])) {
    die("Silakan login terlebih dahulu!");
}

require '../../vendor/autoload.php';

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Import Barcode Batch');

$headers = [
    'Batch No',
    'Product Code',
    'Product Name',
    'Qty',
    'UOM',
    'Batch Norm',
    'Color Code',
    'Color Name',
    'Cust Color',
    'Label Jual',
    'SO No',
    'LOT',
];

$sample = [
    'D26A0010.01.0406.001',
    'B11A30487D722A',
    'UNIONE 3 722A DOUBLING',
    '30.00',
    'M',
    'X25L0836.01.0510.001-1',
    '3.2.11.0.0272',
    'MERAH',
    '722A',
    '',
    'SOI/2512/0158',
    '',
];

foreach ($headers as $index => $header) {
    $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
    $sheet->setCellValue($column . '1', $header);
    $sheet->setCellValue($column . '2', $sample[$index]);
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

$sheet->getStyle('A1:L1')->getFont()->setBold(true);
$sheet->getStyle('A1:L1')->getFill()
    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
    ->getStartColor()->setARGB('FFD9EAF7');
$sheet->freezePane('A2');

$sheet->getStyle('A:L')->getNumberFormat()->setFormatCode('@');
$sheet->getStyle('D:D')->getNumberFormat()->setFormatCode('0.00');

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename=Template_Import_Print_Barcode_Batch.xlsx');
header('Cache-Control: max-age=0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
exit;
