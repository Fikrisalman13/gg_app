<?php
// pages/resep_obat/export_resep_excel.php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Increase execution time and memory limit for large exports
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '512M'); // Increase memory limit
ini_set('max_execution_time', '300'); // 5 minutes

// Auth check
session_start();
if (!isset($_SESSION['UserName'])) {
    die("Access Denied");
}

// Params
$startDate = $_GET['startDate'] ?? '';
$endDate   = $_GET['endDate'] ?? '';

// Fetch Resep Headers
$sql = "SELECT 
            r.id,
            r.no_cp, 
            r.kode_grey,
            r.mesin,
            r.kode_warna,
            r.color_name,
            r.color_desc,
            r.resep_prod_code,
            r.resep_prod_name,
            r.resep_no,
            r.resep_seq,
            r.resep_date,
            r.resep_type,
            r.no_cp_resep,
            r.no_so,
            r.rtg_code,
            r.rtg_name,
            r.status_desc,
            r.cus_color,
            r.lot_no, 
            r.weight, 
            r.plan_qty,
            r.vlot,
            r.status_resep_lipat,
            r.created_at, 
            r.created_by,
            r.updated_at,
            r.updated_by
        FROM dbo.resep_obat r";

$whereConditions = ["r.status_resep_lipat = ?"];
$params = ['Master Resep'];

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

// Fetch all resep data
$resepData = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $resepData[] = $row;
}

// Create Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Data Resep');

// Define Headers
$headers = [
    'No', 'No CP', 'Kode Grey', 'Mesin', 'Kode Warna', 'Color Name', 'Description',
    'Resep Prod Code', 'Resep Prod Name', 'Resep No', 'Resep Seq', 'Resep Date',
    'Resep Type', 'No CP Resep', 'No SO', 'Rtg Code', 'Rtg Name', 'Status Desc',
    'Cus Color', 'Lot No', 'Weight', 'Plan Qty', 'Vlot', 'Status Resep',
    'Created At', 'Created By', 'Updated At', 'Updated By',
    // Detail columns
    'Item Kode', 'Item Name', 'Category', 'Qty/Recipe', 'UOM', 'CF', 'UOM CF', 'Price', 'Total'
];

// Write Headers
$col = 'A';
foreach ($headers as $h) {
    $sheet->setCellValue($col . '1', $h);
    $sheet->getColumnDimension($col)->setAutoSize(true);
    $col++;
}

// Style Header
// Calculate last column letter (handles AA, AB, etc.)
$colCount = count($headers);
if ($colCount <= 26) {
    $lastCol = chr(ord('A') + $colCount - 1);
} else {
    $firstLetter = chr(ord('A') + floor(($colCount - 1) / 26) - 1);
    $secondLetter = chr(ord('A') + (($colCount - 1) % 26));
    $lastCol = $firstLetter . $secondLetter;
}

$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '4472C4']
    ],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray($headerStyle);
$sheet->freezePane('A2');

// Data
$rowNum = 2;
$no = 1;

foreach ($resepData as $resep) {
    $resepId = $resep['id'];
    
    // Fetch detail items for this resep
    $detailSql = "SELECT * FROM dbo.resep_obat_detail WHERE id_resep = ? ORDER BY id";
    $detailStmt = sqlsrv_query($conn, $detailSql, [$resepId]);
    
    $details = [];
    if ($detailStmt) {
        while ($detailRow = sqlsrv_fetch_array($detailStmt, SQLSRV_FETCH_ASSOC)) {
            $details[] = $detailRow;
        }
    }
    
    // If no details, still show header row
    if (empty($details)) {
        $details = [null]; // Placeholder to ensure at least one row
    }
    
    // Format dates
    $created_at = '';
    if ($resep['created_at'] instanceof DateTime) {
        $created_at = $resep['created_at']->format('d-m-Y H:i');
    }
    
    $resep_date = '';
    if ($resep['resep_date'] instanceof DateTime) {
        $resep_date = $resep['resep_date']->format('d-m-Y H:i');
    }
    
    // Write rows for each detail item
    // Write rows for each detail item
    $firstRow = $rowNum;
    
    // Header Values Template
    $headerTemplate = [
        'A' => $no,
        'B' => $resep['no_cp'] ? $resep['no_cp'] : '-',
        'C' => $resep['kode_grey'] ? $resep['kode_grey'] : '-',
        'D' => $resep['mesin'] ? $resep['mesin'] : '-',
        'E' => $resep['kode_warna'] ? $resep['kode_warna'] : '-',
        'F' => $resep['color_name'] ? $resep['color_name'] : '-',
        'G' => $resep['color_desc'] ? $resep['color_desc'] : '-',
        'H' => $resep['resep_prod_code'] ? $resep['resep_prod_code'] : '-',
        'I' => $resep['resep_prod_name'] ? $resep['resep_prod_name'] : '-',
        'J' => $resep['resep_no'] ? $resep['resep_no'] : '-',
        'K' => $resep['resep_seq'] ? $resep['resep_seq'] : '-',
        'L' => $resep_date ? $resep_date : '-',
        'M' => $resep['resep_type'] ? $resep['resep_type'] : '-',
        'N' => $resep['no_cp_resep'] ? $resep['no_cp_resep'] : '-',
        'O' => $resep['no_so'] ? $resep['no_so'] : '-',
        'P' => $resep['rtg_code'] ? $resep['rtg_code'] : '-',
        'Q' => $resep['rtg_name'] ? $resep['rtg_name'] : '-',
        'R' => $resep['status_desc'] ? $resep['status_desc'] : '-',
        'S' => $resep['cus_color'] ? $resep['cus_color'] : '-',
        'T' => $resep['lot_no'] ? $resep['lot_no'] : '-',
        'U' => number_format($resep['weight'], 4, '.', ''),
        'V' => number_format($resep['plan_qty'], 2, '.', ''),
        'W' => $resep['vlot'] ? $resep['vlot'] : '-',
        'X' => $resep['status_resep_lipat'] ? $resep['status_resep_lipat'] : '-',
        'Y' => $created_at ? $created_at : '-',
        'Z' => $resep['created_by'] ? $resep['created_by'] : '-',
        'AA' => ($resep['updated_at'] instanceof DateTime ? $resep['updated_at']->format('d-m-Y H:i') : '-'),
        'AB' => $resep['updated_by'] ? $resep['updated_by'] : '-'
    ];

    foreach ($details as $detail) {
        // Header columns (A-Z)
        foreach ($headerTemplate as $col => $val) {
            $sheet->setCellValue($col . $rowNum, $val);
        }
        
        // Detail columns (AC-AK)
        if ($detail) {
            $sheet->setCellValue('AC' . $rowNum, $detail['kode'] ?? '-');
            $sheet->getStyle('AC' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->setCellValue('AD' . $rowNum, $detail['name'] ?? '-');
            $sheet->setCellValue('AE' . $rowNum, $detail['category'] ?? '-');
            $sheet->setCellValue('AF' . $rowNum, number_format($detail['receipe'] ?? 0, 4, '.', ''));
            $sheet->setCellValue('AG' . $rowNum, $detail['uom'] ?? '-');
            $sheet->setCellValue('AH' . $rowNum, number_format($detail['cf'] ?? 0, 4, '.', ''));
            $sheet->setCellValue('AI' . $rowNum, $detail['uom_cf'] ?? '-');
            
            // Price
            $priceText = 'Rp ' . number_format($detail['std_price'] ?? 0, 2, ',', '.');
            if (!empty($detail['price_satuan'])) {
                $priceText .= ' / ' . $detail['price_satuan'];
            }
            $sheet->setCellValue('AJ' . $rowNum, $priceText);
            
            $sheet->setCellValue('AK' . $rowNum, 'Rp ' . number_format($detail['total'] ?? 0, 2, ',', '.'));
        }
        
        $rowNum++;
    }
    
    // Cost Summary Calculation
    $grandTotal = 0;
    $catTotals = [];
    $catCFTotals = [];
    
    foreach ($details as $d) {
        if ($d) {
            $grandTotal += $d['total'] ?? 0;
            $cat = trim($d['category'] ?? 'Others') ?: 'Others';
            if (!isset($catTotals[$cat])) $catTotals[$cat] = 0;
            $catTotals[$cat] += $d['total'] ?? 0;
            if (!isset($catCFTotals[$cat])) $catCFTotals[$cat] = 0;
            $catCFTotals[$cat] += (float)($d['cf'] ?? 0);
        }
    }
    
    $planQty = $resep['plan_qty'] > 0 ? $resep['plan_qty'] : 1;
    $totalCost = $grandTotal / $planQty;
    
    // --- Cost Summary Rows ---
    
    // 1. Title Row
    foreach ($headerTemplate as $col => $val) {
        $sheet->setCellValue($col . $rowNum, $val);
    }
    
    $sheet->setCellValue('AC' . $rowNum, 'COST SUMMARY PER METER');
    $sheet->mergeCells('AC' . $rowNum . ':AK' . $rowNum);
    $sheet->getStyle('AC' . $rowNum . ':AK' . $rowNum)->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
    ]);
    
    $rowNum++;

    // 2. Category Rows
    foreach ($catTotals as $cat => $total) {
        $cCost = $total / $planQty;
        $totalCF = $catCFTotals[$cat] ?? 0;
        
        foreach ($headerTemplate as $col => $val) {
            $sheet->setCellValue($col . $rowNum, $val);
        }
        
        $sheet->setCellValue('AC' . $rowNum, strtoupper($cat) . ' - DYE STUFF');
        $sheet->mergeCells('AC' . $rowNum . ':AG' . $rowNum);
        
        $sheet->setCellValue('AH' . $rowNum, number_format($totalCF, 2, '.', ',') . ' CF');
        $sheet->mergeCells('AH' . $rowNum . ':AJ' . $rowNum);
        
        $sheet->setCellValue('AK' . $rowNum, 'Rp ' . number_format($cCost, 0, ',', '.'));
        
        $rowNum++;
    }
    
    // 3. Total Row
    foreach ($headerTemplate as $col => $val) {
        $sheet->setCellValue($col . $rowNum, $val);
    }
    
    $sheet->setCellValue('AC' . $rowNum, 'TOTAL COST/METER');
    $sheet->mergeCells('AC' . $rowNum . ':AJ' . $rowNum);
    $sheet->setCellValue('AK' . $rowNum, 'Rp ' . number_format($totalCost, 0, ',', '.'));
    
    $sheet->getStyle('AC' . $rowNum . ':AJ' . $rowNum)->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF2CC']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER]
    ]);
    $sheet->getStyle('AK' . $rowNum)->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF2CC']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER]
    ]);
    
    $rowNum++;

    // Merge Header Columns
    $lastRow = $rowNum - 1;
    if ($lastRow > $firstRow) {
        foreach (array_keys($headerTemplate) as $colLetter) {
            $sheet->mergeCells($colLetter . $firstRow . ':' . $colLetter . $lastRow);
            $sheet->getStyle($colLetter . $firstRow)->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
    }
    
    $no++;
}

// Add borders to all data
if ($rowNum > 2) {
    $sheet->getStyle('A2:' . $lastCol . ($rowNum - 1))->applyFromArray([
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    

}

// Output
$filename = 'Data_Resep_Detail_' . date('Ymd_His') . '.xlsx';

// Clean all output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Set headers
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1'); // For IE
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // Always modified
header('Pragma: public'); // HTTP/1.0

try {
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
} catch (Exception $e) {
    // Log error and send error response
    error_log('Excel Export Error: ' . $e->getMessage());
    
    // Clear headers and send error
    header_remove();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to generate Excel file: ' . $e->getMessage()]);
}

exit;
