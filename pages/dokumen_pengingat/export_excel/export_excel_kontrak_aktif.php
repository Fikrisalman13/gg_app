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
// QUERY: KONTRAK AKTIF (SQL SERVER)
// ============================
$sql = "
SELECT
    k.id,
    k.nama_vendor,
    k.nama_pekerjaan,
    k.no_kontrak,
    k.expire_date,
    b.nama_bagian
FROM dbo.dr_kontrak k
LEFT JOIN dbo.dr_bagian b
    ON k.bagian_id = b.id
WHERE k.expire_date >= CAST(GETDATE() AS DATE)
ORDER BY k.expire_date ASC
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

// Header kolom
$sheet->fromArray([
    'No',
    'Nama Vendor',
    'Nama Pekerjaan',
    'No Kontrak',
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
    $sheet->setCellValue("B{$rowNumber}", $row['nama_vendor']);
    $sheet->setCellValue("C{$rowNumber}", $row['nama_pekerjaan']);
    $sheet->setCellValue("D{$rowNumber}", $row['no_kontrak']);
    $sheet->setCellValue("E{$rowNumber}", $expireDate);
    $sheet->setCellValue("F{$rowNumber}", $row['nama_bagian']);
    $sheet->setCellValue("G{$rowNumber}", 'Aktif');

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
$fileName = 'kontrak_aktif_' . date('Y-m-d') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
