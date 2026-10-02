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
// QUERY: SURAT KENDARAAN
// ============================
$sql = "
SELECT
    sk.id,
    sk.no_polisi,
    sk.jenis_kendaraan,
    sk.nama_kendaraan,
    sk.nama_pemilik,
    sk.expire_date,
    sk.file_kendaraan,
    sk.keterangan,
    sk.email_reminder,
    sk.no_whatsapp,
    sk.createdate,
    sk.update_at,
    b.nama_bagian,
    CASE
        WHEN sk.expire_date < CAST(GETDATE() AS DATE) THEN 'Expired'
        WHEN sk.expire_date BETWEEN CAST(GETDATE() AS DATE)
             AND DATEADD(
                    DAY,
                    CAST(ri.nilai AS INT),
                    CAST(GETDATE() AS DATE)
                )
             THEN 'Reminder'
        ELSE 'Aktif'
    END AS status
FROM dbo.dr_surat_kendaraan sk
LEFT JOIN dbo.dr_bagian b
    ON sk.bagian_id = b.id
LEFT JOIN dbo.dr_reminder_interval ri
    ON ri.kunci = 'reminder_interval_surat_kendaraan'
ORDER BY sk.expire_date ASC
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
    'Nama Kendaraan',
    'No Polisi',
    'Tanggal Expired',
    'Keterangan',
    'Tanggal Dibuat',
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

    $createDate = $row['createdate'] instanceof DateTime
        ? $row['createdate']->format('Y-m-d H:i')
        : '';

    $sheet->setCellValue("A{$rowNumber}", $no++);
    $sheet->setCellValue("B{$rowNumber}", $row['nama_kendaraan']);
    $sheet->setCellValue("C{$rowNumber}", $row['no_polisi']);
    $sheet->setCellValue("D{$rowNumber}", $expireDate);
    $sheet->setCellValue("E{$rowNumber}", $row['keterangan']);
    $sheet->setCellValue("F{$rowNumber}", $createDate);
    $sheet->setCellValue("G{$rowNumber}", $row['nama_bagian']);
    $sheet->setCellValue("H{$rowNumber}", $row['status']);

    $rowNumber++;
}

// ============================
// STYLE HEADER
// ============================
$sheet->getStyle('A1:H1')->applyFromArray([
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
foreach (range('A', 'H') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// ============================
// DOWNLOAD
// ============================
$fileName = 'surat_kendaraan_' . date('Y-m-d') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
