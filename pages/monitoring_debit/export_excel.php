<?php
/**
 * export_excel.php - Export data emds_readings ke file .xlsx
 * Menggunakan PhpSpreadsheet yang sudah ada di vendor/.
 * Dipanggil via POST dari data_list.php.
 */
session_start();

if (!isset($_SESSION['UserId'])) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// --- Parameter ---
$startDate = $_POST['start_date'] ?? date('Y-m-01');
$endDate   = $_POST['end_date']   ?? date('Y-m-d');
$stationId = $_POST['station_id'] ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) $startDate = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate))   $endDate   = date('Y-m-d');

// --- Query ---
$whereParts = ["r.captured_at >= ? AND r.captured_at < DATEADD(DAY, 1, CAST(? AS DATE))"];
$params     = [$startDate, $endDate];

if (!empty($stationId)) {
    $whereParts[] = "r.station_id = ?";
    $params[]     = $stationId;
}
$whereClause = implode(' AND ', $whereParts);

$sql = "
    SELECT
        r.id,
        r.station_id,
        COALESCE(s.station_name, r.station_id) AS station_name,
        r.device_name,
        r.parameter_name,
        r.reading_value,
        r.unit,
        r.quality,
        r.captured_at,
        r.synced_at
    FROM emds_readings r
    LEFT JOIN emds_stations s ON r.station_id = s.station_id
    WHERE $whereClause
    ORDER BY r.captured_at DESC
";

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    http_response_code(500);
    echo 'Gagal mengambil data: ' . print_r(sqlsrv_errors(), true);
    exit;
}

// --- Buat Spreadsheet ---
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Data Debit');

// Judul laporan
$sheet->mergeCells('A1:I1');
$sheet->setCellValue('A1', 'LAPORAN DATA MONITORING DEBIT');
$sheet->mergeCells('A2:I2');
$sheet->setCellValue('A2', 'Periode: ' . $startDate . ' s/d ' . $endDate .
    ($stationId ? ' | Stasiun: ' . $stationId : ' | Semua Stasiun') .
    ' | Dicetak: ' . date('d/m/Y H:i:s'));

$titleStyle = [
    'font' => ['bold' => true, 'size' => 13, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1565C0']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
];
$subTitleStyle = [
    'font' => ['bold' => false, 'size' => 10, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1976D2']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
];
$sheet->getStyle('A1')->applyFromArray($titleStyle);
$sheet->getStyle('A2')->applyFromArray($subTitleStyle);
$sheet->getRowDimension(1)->setRowHeight(24);
$sheet->getRowDimension(2)->setRowHeight(18);

// --- Header Kolom ---
$headers = ['No', 'Waktu Capture', 'Stasiun', 'ID Stasiun', 'Device', 'Parameter', 'Nilai', 'Satuan', 'Kualitas', 'Waktu Sync'];
$headerRow = 4;
$col = 'A';
foreach ($headers as $h) {
    $sheet->setCellValue($col . $headerRow, $h);
    $col++;
}

$headerStyle = [
    'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FF000000']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE3F2FD']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFB0BEC5']],
    ],
];
$sheet->getStyle('A' . $headerRow . ':J' . $headerRow)->applyFromArray($headerStyle);
$sheet->getRowDimension($headerRow)->setRowHeight(18);

// --- Data Rows ---
$rowNum  = $headerRow + 1;
$counter = 1;
$qualityLabel = [
    'fresh'    => 'Segar',
    'stale'    => 'Lama',
    'timeout'  => 'Timeout',
    'invalid'  => 'Invalid',
    'isolated' => 'Isolated',
];

$evenStyle = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF5F5F5']]];
$borderAll = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE0E0E0']]]];

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $capturedAt = '-';
    if ($row['captured_at'] instanceof DateTime) {
        $capturedAt = $row['captured_at']->format('d/m/Y H:i:s');
    } elseif (is_string($row['captured_at'])) {
        $capturedAt = $row['captured_at'];
    }
    $syncedAt = '-';
    if ($row['synced_at'] instanceof DateTime) {
        $syncedAt = $row['synced_at']->format('d/m/Y H:i:s');
    } elseif (is_string($row['synced_at'])) {
        $syncedAt = $row['synced_at'];
    }

    $readingValue = $row['reading_value'] !== null ? (float)$row['reading_value'] : '';

    $sheet->setCellValue('A' . $rowNum, $counter);
    $sheet->setCellValue('B' . $rowNum, $capturedAt);
    $sheet->setCellValue('C' . $rowNum, $row['station_name']);
    $sheet->setCellValue('D' . $rowNum, $row['station_id']);
    $sheet->setCellValue('E' . $rowNum, $row['device_name']);
    $sheet->setCellValue('F' . $rowNum, $row['parameter_name']);
    $sheet->setCellValue('G' . $rowNum, $readingValue);
    $sheet->setCellValue('H' . $rowNum, $row['unit']);
    $sheet->setCellValue('I' . $rowNum, $qualityLabel[$row['quality']] ?? $row['quality']);
    $sheet->setCellValue('J' . $rowNum, $syncedAt);

    // Style nilai numerik
    if ($readingValue !== '') {
        $sheet->getStyle('G' . $rowNum)->getNumberFormat()->setFormatCode('#,##0.000');
        $sheet->getStyle('G' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }
    $sheet->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Warna selang-seling
    if ($counter % 2 === 0) {
        $sheet->getStyle('A' . $rowNum . ':J' . $rowNum)->applyFromArray($evenStyle);
    }
    $sheet->getStyle('A' . $rowNum . ':J' . $rowNum)->applyFromArray($borderAll);

    // Badge kualitas warna
    $qualityColors = [
        'fresh'    => 'FF4CAF50',
        'stale'    => 'FFFFC107',
        'timeout'  => 'FFF44336',
        'invalid'  => 'FFF44336',
        'isolated' => 'FF9E9E9E',
    ];
    if (isset($qualityColors[$row['quality']])) {
        $sheet->getStyle('I' . $rowNum)->getFont()->setColor(
            new \PhpOffice\PhpSpreadsheet\Style\Color($qualityColors[$row['quality']])
        );
        $sheet->getStyle('I' . $rowNum)->getFont()->setBold(true);
    }

    $counter++;
    $rowNum++;
}
sqlsrv_free_stmt($stmt);

// --- Lebar Kolom ---
$colWidths = ['A'=>6,'B'=>22,'C'=>22,'D'=>18,'E'=>22,'F'=>22,'G'=>14,'H'=>10,'I'=>12,'J'=>22];
foreach ($colWidths as $c => $w) {
    $sheet->getColumnDimension($c)->setWidth($w);
}

// --- Freeze Header ---
$sheet->freezePane('A' . ($headerRow + 1));

// --- Summary row di akhir ---
$summaryRow = $rowNum + 1;
$sheet->mergeCells('A' . $summaryRow . ':F' . $summaryRow);
$sheet->setCellValue('A' . $summaryRow, 'Total Data');
if ($counter > 1) {
    $sheet->setCellValue('G' . $summaryRow, '=IFERROR(AVERAGE(G' . ($headerRow+1) . ':G' . ($rowNum-1) . '), "-")');
} else {
    $sheet->setCellValue('G' . $summaryRow, '-');
}
$sheet->getStyle('G' . $summaryRow)->getNumberFormat()->setFormatCode('#,##0.000');
$sheet->mergeCells('H' . $summaryRow . ':J' . $summaryRow);
$sheet->setCellValue('H' . $summaryRow, 'Total: ' . ($counter - 1) . ' baris');
$sheet->getStyle('A' . $summaryRow . ':J' . $summaryRow)->applyFromArray([
    'font' => ['bold' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE3F2FD']],
]);

// --- Output ---
$filename = 'Data_Debit_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
