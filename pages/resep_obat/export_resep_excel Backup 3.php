<?php
// pages/resep_obat/export_resep_excel.php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

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
            r.created_at, 
            r.created_by,
            r.updated_at,
            r.updated_by
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
    'No', 'No CP', 'Kode Grey', 'Kode Warna', 'Color Name', 'Description',
    'Resep Prod Code', 'Resep Prod Name', 'Resep No', 'Resep Seq', 'Resep Date',
    'Resep Type', 'No CP Resep', 'No SO', 'Rtg Code', 'Rtg Name', 'Status Desc',
    'Cus Color', 'Lot No', 'Weight', 'Plan Qty', 'Vlot',
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
    $firstRow = $rowNum;
    foreach ($details as $detail) {
        // Header columns (A-X)
        $sheet->setCellValue('A' . $rowNum, $no);
        $sheet->setCellValue('B' . $rowNum, $resep['no_cp']);
        $sheet->setCellValue('C' . $rowNum, $resep['kode_grey'] ?? '-');
        $sheet->setCellValue('D' . $rowNum, $resep['kode_warna']);
        $sheet->setCellValue('E' . $rowNum, $resep['color_name'] ?? '-');
        $sheet->setCellValue('F' . $rowNum, $resep['color_desc'] ?? '-');
        $sheet->setCellValue('G' . $rowNum, $resep['resep_prod_code'] ?? '-');
        $sheet->setCellValue('H' . $rowNum, $resep['resep_prod_name'] ?? '-');
        $sheet->setCellValue('I' . $rowNum, $resep['resep_no'] ?? '-');
        $sheet->setCellValue('J' . $rowNum, $resep['resep_seq'] ?? '-');
        $sheet->setCellValue('K' . $rowNum, $resep_date);
        $sheet->setCellValue('L' . $rowNum, $resep['resep_type'] ?? '-');
        $sheet->setCellValue('M' . $rowNum, $resep['no_cp_resep'] ?? '-');
        $sheet->setCellValue('N' . $rowNum, $resep['no_so'] ?? '-');
        $sheet->setCellValue('O' . $rowNum, $resep['rtg_code'] ?? '-');
        $sheet->setCellValue('P' . $rowNum, $resep['rtg_name'] ?? '-');
        $sheet->setCellValue('Q' . $rowNum, $resep['status_desc'] ?? '-');
        $sheet->setCellValue('R' . $rowNum, $resep['cus_color'] ?? '-');
        $sheet->setCellValue('S' . $rowNum, $resep['lot_no']);
        $sheet->setCellValue('T' . $rowNum, number_format($resep['weight'], 4, '.', ''));
        $sheet->setCellValue('U' . $rowNum, number_format($resep['plan_qty'], 2, '.', ''));
        $sheet->setCellValue('V' . $rowNum, $resep['vlot'] ?? '-');
        
        $sheet->setCellValue('W' . $rowNum, $created_at);
        $sheet->setCellValue('X' . $rowNum, $resep['created_by'] ?? '-');
        
        $updated_at = '';
        if ($resep['updated_at'] instanceof DateTime) {
            $updated_at = $resep['updated_at']->format('d-m-Y H:i');
        }
        $sheet->setCellValue('Y' . $rowNum, $updated_at);
        $sheet->setCellValue('Z' . $rowNum, $resep['updated_by'] ?? '-');
        
        // Detail columns (AA-AI)
        if ($detail) {
            $sheet->setCellValue('AA' . $rowNum, $detail['kode'] ?? '-');
            $sheet->getStyle('AA' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->setCellValue('AB' . $rowNum, $detail['name'] ?? '-');
            $sheet->setCellValue('AC' . $rowNum, $detail['category'] ?? '-');
            $sheet->setCellValue('AD' . $rowNum, number_format($detail['receipe'] ?? 0, 4, '.', ''));
            $sheet->setCellValue('AE' . $rowNum, $detail['uom'] ?? '-');
            $sheet->setCellValue('AF' . $rowNum, number_format($detail['cf'] ?? 0, 4, '.', ''));
            $sheet->setCellValue('AG' . $rowNum, $detail['uom_cf'] ?? '-');
            
            // Price with unit (e.g., Rp 150,000 / KG)
            $priceText = 'Rp ' . number_format($detail['std_price'] ?? 0, 2, ',', '.');
            if (!empty($detail['price_satuan'])) {
                $priceText .= ' / ' . $detail['price_satuan'];
            }
            $sheet->setCellValue('AH' . $rowNum, $priceText);
            
            $sheet->setCellValue('AI' . $rowNum, 'Rp ' . number_format($detail['total'] ?? 0, 2, ',', '.'));
        }
        
        $rowNum++;
    }
    

    
    // Calculate Cost Summary Per Meter
    $grandTotal = 0;
    $catTotals = [];
    $catCFTotals = [];
    
    foreach ($details as $detail) {
        if ($detail) {
            $grandTotal += $detail['total'] ?? 0;
            
            $cat = trim($detail['category'] ?? 'Others');
            if ($cat === '') $cat = 'Others';
            
            if (!isset($catTotals[$cat])) $catTotals[$cat] = 0;
            $catTotals[$cat] += $detail['total'] ?? 0;
            
            if (!isset($catCFTotals[$cat])) $catCFTotals[$cat] = 0;
            $catCFTotals[$cat] += (float)($detail['cf'] ?? 0);
        }
    }
    
    $planQty = $resep['plan_qty'] > 0 ? $resep['plan_qty'] : 1;
    $totalCost = $grandTotal / $planQty;
    
    // --- Add Cost Summary Rows (Duplicating Header Info) ---
    
    // 1. Title Row
    // Copy Header columns A-Z
    $sheet->setCellValue('A' . $rowNum, $no);
    $sheet->setCellValue('B' . $rowNum, $resep['no_cp']);
    $sheet->setCellValue('C' . $rowNum, $resep['kode_grey'] ?? '-');
    $sheet->setCellValue('D' . $rowNum, $resep['kode_warna']);
    $sheet->setCellValue('E' . $rowNum, $resep['color_name'] ?? '-');
    $sheet->setCellValue('F' . $rowNum, $resep['color_desc'] ?? '-');
    $sheet->setCellValue('G' . $rowNum, $resep['resep_prod_code'] ?? '-');
    $sheet->setCellValue('H' . $rowNum, $resep['resep_prod_name'] ?? '-');
    $sheet->setCellValue('I' . $rowNum, $resep['resep_no'] ?? '-');
    $sheet->setCellValue('J' . $rowNum, $resep['resep_seq'] ?? '-');
    $sheet->setCellValue('K' . $rowNum, $resep_date);
    $sheet->setCellValue('L' . $rowNum, $resep['resep_type'] ?? '-');
    $sheet->setCellValue('M' . $rowNum, $resep['no_cp_resep'] ?? '-');
    $sheet->setCellValue('N' . $rowNum, $resep['no_so'] ?? '-');
    $sheet->setCellValue('O' . $rowNum, $resep['rtg_code'] ?? '-');
    $sheet->setCellValue('P' . $rowNum, $resep['rtg_name'] ?? '-');
    $sheet->setCellValue('Q' . $rowNum, $resep['status_desc'] ?? '-');
    $sheet->setCellValue('R' . $rowNum, $resep['cus_color'] ?? '-');
    $sheet->setCellValue('S' . $rowNum, $resep['lot_no']);
    $sheet->setCellValue('T' . $rowNum, number_format($resep['weight'], 4, '.', ''));
    $sheet->setCellValue('U' . $rowNum, number_format($resep['plan_qty'], 2, '.', ''));
    $sheet->setCellValue('V' . $rowNum, $resep['vlot'] ?? '-');
    
    $sheet->setCellValue('W' . $rowNum, $created_at);
    $sheet->setCellValue('X' . $rowNum, $resep['created_by'] ?? '-');
    $sheet->setCellValue('Y' . $rowNum, $updated_at);
    $sheet->setCellValue('Z' . $rowNum, $resep['updated_by'] ?? '-');
    
    // Summary Title in Column AA (Item Kode)
    $sheet->setCellValue('AA' . $rowNum, 'COST SUMMARY PER METER');
    $sheet->mergeCells('AA' . $rowNum . ':AI' . $rowNum); // Merge across detail columns
    $sheet->getStyle('AA' . $rowNum . ':AI' . $rowNum)->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
    ]);
    
    $rowNum++;

    // 2. Category Rows
    foreach ($catTotals as $cat => $total) {
        $cCost = $total / $planQty;
        $totalCF = $catCFTotals[$cat] ?? 0;
        
        // Copy Header columns A-Z again
        $sheet->setCellValue('A' . $rowNum, $no);
        $sheet->setCellValue('B' . $rowNum, $resep['no_cp']);
        $sheet->setCellValue('C' . $rowNum, $resep['kode_grey'] ?? '-');
        $sheet->setCellValue('D' . $rowNum, $resep['kode_warna']);
        $sheet->setCellValue('E' . $rowNum, $resep['color_name'] ?? '-');
        $sheet->setCellValue('F' . $rowNum, $resep['color_desc'] ?? '-');
        $sheet->setCellValue('G' . $rowNum, $resep['resep_prod_code'] ?? '-');
        $sheet->setCellValue('H' . $rowNum, $resep['resep_prod_name'] ?? '-');
        $sheet->setCellValue('I' . $rowNum, $resep['resep_no'] ?? '-');
        $sheet->setCellValue('J' . $rowNum, $resep['resep_seq'] ?? '-');
        $sheet->setCellValue('K' . $rowNum, $resep_date);
        $sheet->setCellValue('L' . $rowNum, $resep['resep_type'] ?? '-');
        $sheet->setCellValue('M' . $rowNum, $resep['no_cp_resep'] ?? '-');
        $sheet->setCellValue('N' . $rowNum, $resep['no_so'] ?? '-');
        $sheet->setCellValue('O' . $rowNum, $resep['rtg_code'] ?? '-');
        $sheet->setCellValue('P' . $rowNum, $resep['rtg_name'] ?? '-');
        $sheet->setCellValue('Q' . $rowNum, $resep['status_desc'] ?? '-');
        $sheet->setCellValue('R' . $rowNum, $resep['cus_color'] ?? '-');
        $sheet->setCellValue('S' . $rowNum, $resep['lot_no']);
        $sheet->setCellValue('T' . $rowNum, number_format($resep['weight'], 4, '.', ''));
        $sheet->setCellValue('U' . $rowNum, number_format($resep['plan_qty'], 2, '.', ''));
        $sheet->setCellValue('V' . $rowNum, $resep['vlot'] ?? '-');
        
        $sheet->setCellValue('W' . $rowNum, $created_at);
        $sheet->setCellValue('X' . $rowNum, $resep['created_by'] ?? '-');
        $sheet->setCellValue('Y' . $rowNum, $updated_at);
        $sheet->setCellValue('Z' . $rowNum, $resep['updated_by'] ?? '-');
        
        // Summary Data
        // AA: Category Name  |  AF: CF Qty  |  AI: Cost
        
        $sheet->setCellValue('AA' . $rowNum, strtoupper($cat) . ' - DYE STUFF');
        $sheet->mergeCells('AA' . $rowNum . ':AE' . $rowNum);
        
        $sheet->setCellValue('AF' . $rowNum, number_format($totalCF, 2, '.', ',') . ' CF');
        $sheet->mergeCells('AF' . $rowNum . ':AH' . $rowNum);
        
        $sheet->setCellValue('AI' . $rowNum, 'Rp ' . number_format($cCost, 0, ',', '.'));
        
        $rowNum++;
    }
    
    // 3. Total Row
    // Copy Header columns A-Z one last time
    $sheet->setCellValue('A' . $rowNum, $no);
    $sheet->setCellValue('B' . $rowNum, $resep['no_cp']);
    $sheet->setCellValue('C' . $rowNum, $resep['kode_grey'] ?? '-');
    $sheet->setCellValue('D' . $rowNum, $resep['kode_warna']);
    $sheet->setCellValue('E' . $rowNum, $resep['color_name'] ?? '-');
    $sheet->setCellValue('F' . $rowNum, $resep['color_desc'] ?? '-');
    $sheet->setCellValue('G' . $rowNum, $resep['resep_prod_code'] ?? '-');
    $sheet->setCellValue('H' . $rowNum, $resep['resep_prod_name'] ?? '-');
    $sheet->setCellValue('I' . $rowNum, $resep['resep_no'] ?? '-');
    $sheet->setCellValue('J' . $rowNum, $resep['resep_seq'] ?? '-');
    $sheet->setCellValue('K' . $rowNum, $resep_date);
    $sheet->setCellValue('L' . $rowNum, $resep['resep_type'] ?? '-');
    $sheet->setCellValue('M' . $rowNum, $resep['no_cp_resep'] ?? '-');
    $sheet->setCellValue('N' . $rowNum, $resep['no_so'] ?? '-');
    $sheet->setCellValue('O' . $rowNum, $resep['rtg_code'] ?? '-');
    $sheet->setCellValue('P' . $rowNum, $resep['rtg_name'] ?? '-');
    $sheet->setCellValue('Q' . $rowNum, $resep['status_desc'] ?? '-');
    $sheet->setCellValue('R' . $rowNum, $resep['cus_color'] ?? '-');
    $sheet->setCellValue('S' . $rowNum, $resep['lot_no']);
    $sheet->setCellValue('T' . $rowNum, number_format($resep['weight'], 4, '.', ''));
    $sheet->setCellValue('U' . $rowNum, number_format($resep['plan_qty'], 2, '.', ''));
    $sheet->setCellValue('V' . $rowNum, $resep['vlot'] ?? '-');
    
    $sheet->setCellValue('W' . $rowNum, $created_at);
    $sheet->setCellValue('X' . $rowNum, $resep['created_by'] ?? '-');
    $sheet->setCellValue('Y' . $rowNum, $updated_at);
    $sheet->setCellValue('Z' . $rowNum, $resep['updated_by'] ?? '-');
    
    $sheet->setCellValue('AA' . $rowNum, 'TOTAL COST/METER');
    $sheet->mergeCells('AA' . $rowNum . ':AH' . $rowNum);
    $sheet->setCellValue('AI' . $rowNum, 'Rp ' . number_format($totalCost, 0, ',', '.'));
    
    $sheet->getStyle('AA' . $rowNum . ':AH' . $rowNum)->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF2CC']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER]
    ]);
    $sheet->getStyle('AI' . $rowNum)->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF2CC']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER]
    ]);
    
    $rowNum++;

    // Merge header cells (now including the summary rows)
    $lastRow = $rowNum - 1;
    if ($lastRow > $firstRow) {
        for ($colIndex = 0; $colIndex < 26; $colIndex++) { // A-Z (header columns)
            $colLetter = chr(ord('A') + $colIndex);
            $sheet->mergeCells($colLetter . $firstRow . ':' . $colLetter . $lastRow);
            $sheet->getStyle($colLetter . $firstRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        }
    }
    
    $no++;
}

// Add borders to all data
if ($rowNum > 2) {
    $sheet->getStyle('A2:' . $lastCol . ($rowNum - 1))->applyFromArray([
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    
    // Set Cus Color column (R) to left alignment
    $sheet->getStyle('R2:R' . ($rowNum - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
}

// Output
$filename = 'Data_Resep_Detail_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
