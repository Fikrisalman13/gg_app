<?php
// pages/resep_obat/export_resep_excel.php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Auth check
session_start();
if (!isset($_SESSION['UserName'])) {
    die("Access Denied");
}

// Params
$startDate = $_GET['startDate'] ?? '';
$endDate   = $_GET['endDate'] ?? '';

// Base Query
$sql = "SELECT 
            r.no_cp, 
            r.kode_warna, 
            r.cus_color,
            r.lot_no, 
            r.weight, 
            r.plan_qty,
            r.created_at, 
            r.created_by
        FROM dbo.resep_obat r";

$whereConditions = [];
$params = [];

if (!empty($startDate) && !empty($endDate)) {
    $whereConditions[] = "(r.created_at >= ? AND r.created_at <= ?)";
    $params[] = $startDate . ' 00:00:00';
    $params[] = $endDate . ' 23:59:59';
}

if (!empty($whereConditions)) {
    $sql .= " WHERE " . implode(' AND ', $whereConditions);
}

$sql .= " ORDER BY r.created_at DESC";

// Execute
$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Create Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Data Resep');

// Headers
$headers = ['No', 'No CP', 'Kode Warna', 'Cus Color', 'Lot No', 'Weight', 'Plan Qty', 'Created At'];
$col = 'A';
foreach ($headers as $h) {
    $sheet->setCellValue($col . '1', $h);
    $sheet->getColumnDimension($col)->setAutoSize(true);
    $col++;
}

// Style Header
$headerStyle = [
    'font' => ['bold' => true],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN]]
];
$sheet->getStyle('A1:H1')->applyFromArray($headerStyle);
$sheet->freezePane('A2');

// Data
$rowNum = 2;
$no = 1;

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $created_at = '';
    if ($row['created_at'] instanceof DateTime) {
        $created_at = $row['created_at']->format('d-m-Y H:i');
    }
    
    $sheet->setCellValue('A' . $rowNum, $no++);
    $sheet->setCellValue('B' . $rowNum, $row['no_cp']);
    $sheet->setCellValue('C' . $rowNum, $row['kode_warna']);
    $sheet->setCellValue('D' . $rowNum, $row['cus_color'] ?? '-');
    $sheet->setCellValue('E' . $rowNum, $row['lot_no']);
    $sheet->setCellValue('F' . $rowNum, $row['weight']);
    $sheet->setCellValue('G' . $rowNum, $row['plan_qty']);
    $sheet->setCellValue('H' . $rowNum, $created_at);
    $rowNum++;
}

// Output
$filename = 'Data_Resep_Obat_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
