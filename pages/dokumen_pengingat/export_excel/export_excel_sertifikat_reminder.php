<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

// ============================
// AUTOLOAD & DB
// ============================
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// ============================
// QUERY: SERTIFIKAT REMINDER
// ============================
$sql = "
SELECT
    s.id,
    s.nama_lembaga,
    s.nama_sertifikat,
    s.no_sertifikat,
    s.expire_date,
    b.nama_bagian
FROM dbo.dr_sertifikat s
LEFT JOIN dbo.dr_bagian b
    ON s.bagian_id = b.id
LEFT JOIN dbo.dr_reminder_interval ri
    ON ri.kunci = 'reminder_interval_sertifikat'
WHERE s.expire_date BETWEEN CAST(GETDATE() AS DATE)
      AND DATEADD(
            DAY,
            CAST(ri.nilai AS INT),
            CAST(GETDATE() AS DATE)
          )
ORDER BY s.expire_date ASC
";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

// ============================
// SPREADSHEET
// ============================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Header
$sheet->fromArray([
    'No',
    'Nama Lembaga',
    'Nama Sertifikat',
    'No Sertifikat',
    'Tanggal Expired',
    'Bagian',
    'Status'
], null, 'A1');

// Data
$rowNumber = 2;
$no = 1;

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {

    $expireDate = $row['expire_date'] instanceof DateTime
        ? $row['expire_date']->format('Y-m-d')
        : '';

    $sheet->setCellValue("A{$rowNumber}", $no++);
    $sheet->setCellValue("B{$rowNumber}", $row['nama_lembaga']);
    $sheet->setCellValue("C{$rowNumber}", $row['nama_sertifikat']);
    $sheet->setCellValue("D{$rowNumber}", $row['no_sertifikat']);
    $sheet->setCellValue("E{$rowNumber}", $expireDate);
    $sheet->setCellValue("F{$rowNumber}", $row['nama_bagian']);
    $sheet->setCellValue("G{$rowNumber}", 'Reminder');

    $rowNumber++;
}

// ============================
// STYLE HEADER
// ============================
$sheet->getStyle('A1:G1')->applyFromArray([
    'font' => ['bold' => true],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical'   => Alignment::VERTICAL_CENTER
    ],
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN]
    ]
]);

// Auto width
foreach (range('A', 'G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// ============================
// DOWNLOAD
// ============================
$fileName = 'sertifikat_reminder_' . date('Y-m-d') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
