<?php
// pages/resep_obat/master_limit_warna/download_template_limit.php
require_once __DIR__ . '/../../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set Headers
$headers = [
    'A1' => 'Kode Warna (Wajib)',
    'B1' => 'Max Cost (Rp)',
    'C1' => 'Max CF Disperse (Qty)',
    'D1' => 'Max CF Reactive (Qty)',
    'E1' => 'Max CF Total (Qty)'
];

foreach ($headers as $cell => $text) {
    $sheet->setCellValue($cell, $text);
    $sheet->getStyle($cell)->getFont()->setBold(true);
    $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

// Set Sample Data
$sheet->setCellValue('A2', 'CONTOH-01');
$sheet->setCellValue('B2', '100000');
$sheet->setCellValue('C2', '200');
$sheet->setCellValue('D2', '300');
$sheet->setCellValue('E2', '500');

// Set Hint
$sheet->setCellValue('A3', 'Kosongkan nilai atau isi "-" jika Unlimited');
$sheet->mergeCells('A3:E3');
$sheet->getStyle('A3')->getFont()->setItalic(true);
$sheet->getStyle('A3')->getFont()->getColor()->setARGB('FF777777');

// Auto Size Columns
foreach (range('A', 'E') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$writer = new Xlsx($spreadsheet);
$filename = 'Template_Limit_Warna.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer->save('php://output');
exit;
?>
