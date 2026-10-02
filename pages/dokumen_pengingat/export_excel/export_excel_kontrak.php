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
// QUERY SQL SERVER (DR_KONTRAK)
// ============================
$sql = "
SELECT
    k.id,
    k.nama_vendor,
    k.nama_pekerjaan,
    k.no_kontrak,
    k.expire_date,
    k.file_path,
    k.keterangan,
    k.email_reminder,
    k.no_whatsapp,
    k.createdate,
    k.updatedate,
    k.bagian_id,
    b.nama_bagian,
    CASE
        WHEN k.expire_date < CAST(GETDATE() AS DATE) THEN 'Kadaluarsa'
        WHEN k.expire_date BETWEEN CAST(GETDATE() AS DATE)
             AND DATEADD(
                    DAY,
                    CAST(ri.nilai AS INT),   -- ⬅️ FIX UTAMA
                    CAST(GETDATE() AS DATE)
                )
             THEN 'Reminder'
        ELSE 'Aktif'
    END AS status
FROM dbo.dr_kontrak k
LEFT JOIN dbo.dr_bagian b
    ON k.bagian_id = b.id
LEFT JOIN dbo.dr_reminder_interval ri
    ON ri.kunci = 'reminder_interval_kontrak'
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

// Header
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
        : $row['expire_date'];

    $sheet->setCellValue("A{$rowNumber}", $no++);
    $sheet->setCellValue("B{$rowNumber}", $row['nama_vendor']);
    $sheet->setCellValue("C{$rowNumber}", $row['nama_pekerjaan']);
    $sheet->setCellValue("D{$rowNumber}", $row['no_kontrak']);
    $sheet->setCellValue("E{$rowNumber}", $expireDate);
    $sheet->setCellValue("F{$rowNumber}", $row['nama_bagian']);
    $sheet->setCellValue("G{$rowNumber}", $row['status']);

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

foreach (range('A', 'G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// ============================
// DOWNLOAD
// ============================
$fileName = 'Kontrak_' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
