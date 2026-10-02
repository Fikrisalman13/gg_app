<?php
// uploadmikro.php — Export data Mikrotik ke Excel

require 'vendor/autoload.php'; // pastikan sudah ada PhpSpreadsheet di folder vendor
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// === Cek data dikirim via POST ===
if (!isset($_POST['data'])) {
    http_response_code(400);
    echo "❌ Tidak ada data dikirim.";
    exit;
}

$data = json_decode($_POST['data'], true);
if (!$data || !is_array($data)) {
    http_response_code(400);
    echo "❌ Format data tidak valid.";
    exit;
}

try {
    // === Buat Spreadsheet ===
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // === Judul Sheet ===
    $sheet->setTitle("Queue Data");

    // === Header Kolom ===
    $headers = [
        'A1' => 'No',
        'B1' => 'Name',
        'C1' => 'Target',
        'D1' => 'Upload Max Limit',
        'E1' => 'Download Max Limit',
        'F1' => 'Upload Avg. Rate',
        'G1' => 'Download Avg. Rate',
        'H1' => 'Total Uploaded',
        'I1' => 'Total Downloaded'
    ];

    foreach ($headers as $cell => $value) {
        $sheet->setCellValue($cell, $value);
        $sheet->getStyle($cell)->getFont()->setBold(true);
        $sheet->getStyle($cell)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFCCE5FF');
    }

    // === Isi Data ===
    $rowNum = 2;
    foreach ($data as $index => $row) {
        $sheet->setCellValue('A' . $rowNum, $index + 1);
        $sheet->setCellValue('B' . $rowNum, $row['Name'] ?? '');
        $sheet->setCellValue('C' . $rowNum, $row['Target'] ?? '');
        $sheet->setCellValue('D' . $rowNum, $row['Upload Max Limit'] ?? '');
        $sheet->setCellValue('E' . $rowNum, $row['Download Max Limit'] ?? '');
        $sheet->setCellValue('F' . $rowNum, $row['Upload Avg. Rate'] ?? '');
        $sheet->setCellValue('G' . $rowNum, $row['Download Avg. Rate'] ?? '');
        $sheet->setCellValue('H' . $rowNum, $row['Total Uploaded'] ?? '');
        $sheet->setCellValue('I' . $rowNum, $row['Total Downloaded'] ?? '');
        $rowNum++;
    }

    // === Auto Width Kolom ===
    foreach (range('A', 'I') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // === Format Border ===
    $styleArray = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['argb' => 'FFAAAAAA'],
            ],
        ],
    ];
    $sheet->getStyle('A1:I' . ($rowNum - 1))->applyFromArray($styleArray);

    // === Nama File ===
    $date = date('Ymd_His');
    $filename = "Mikrotik_Queue_{$date}.xlsx";

    // === Output ke Browser ===
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo "❌ Gagal membuat file Excel: " . $e->getMessage();
}
