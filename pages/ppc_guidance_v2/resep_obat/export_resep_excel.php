<?php
// pages/resep_obat/export_resep_excel.php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../../koneksi3.php';
require_once __DIR__ . '/label_jual_lookup.php';
require_once __DIR__ . '/includes/resep_config.php';

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
            (
                SELECT COALESCE(SUM(d.total), 0)
                FROM dbo.resep_obat_detail_v2 d
                WHERE d.id_resep = r.id
            ) / NULLIF(CASE WHEN r.plan_qty > 0 THEN r.plan_qty ELSE 1 END, 0) AS total_cost_per_meter,
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
            r.updated_by,
            r.status_resep_lipat
        FROM dbo.resep_obat_v2 r";

$whereConditions = [];
$params = [];

if (!empty($startDate) && !empty($endDate)) {
    $whereConditions[] = "(r.created_at >= ? AND r.created_at <= ?)";
    $params[] = $startDate . ' 00:00:00';
    $params[] = $endDate . ' 23:59:59';
}

$statusFilter = $_GET['statusFilter'] ?? '';
$statusFilter = is_array($statusFilter) ? $statusFilter : explode(',', $statusFilter);
$statusFilter = array_values(array_filter(array_map('trim', $statusFilter), static fn($v) => $v !== ''));
if (!empty($statusFilter)) {
    $placeholders = implode(',', array_fill(0, count($statusFilter), '?'));
    $whereConditions[] = "r.status_resep_lipat IN ($placeholders)";
    foreach ($statusFilter as $status) {
        $params[] = $status;
    }
}

$cusColorFilter = trim($_GET['cusColorFilter'] ?? '');
if ($cusColorFilter !== '') {
    $whereConditions[] = "r.cus_color LIKE ?";
    $params[] = "%{$cusColorFilter}%";
}

$saleLabelFilters = $_GET['labelJualFilter'] ?? [];
$saleLabelFilters = is_array($saleLabelFilters) ? $saleLabelFilters : explode(',', (string) $saleLabelFilters);
$saleLabelFilters = array_values(array_unique(array_filter(array_map(
    static fn($value): string => trim((string) $value),
    $saleLabelFilters,
))));
if ($saleLabelFilters) {
    $productionNumbers = [];
    foreach ($saleLabelFilters as $saleLabelFilter) {
        array_push(
            $productionNumbers,
            ...recipeLocalProductionNumbersBySaleLabel($conn, $conn3, $saleLabelFilter),
        );
    }
    $productionNumbers = array_values(array_unique($productionNumbers));
    if (!$productionNumbers) {
        $whereConditions[] = '1 = 0';
    } else {
        $productionNumberConditions = [];
        foreach (array_chunk($productionNumbers, 500) as $productionNumberChunk) {
            $productionNumberConditions[] = 'LTRIM(RTRIM(r.no_cp)) IN ('
                . implode(',', array_fill(0, count($productionNumberChunk), '?')) . ')';
            array_push($params, ...$productionNumberChunk);
        }
        $whereConditions[] = '(' . implode(' OR ', $productionNumberConditions) . ')';
    }
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

$productionNumbersForLabels = array_values(array_unique(array_filter(array_map(
    static fn(array $recipe): string => trim((string) ($recipe['no_cp'] ?? '')),
    $resepData,
))));
$labelsByProductionNumber = [];
foreach (array_chunk($productionNumbersForLabels, 500) as $productionNumberChunk) {
    $labelsByProductionNumber += recipeSaleLabelsByProductionNumber($conn3, $productionNumberChunk);
}

$includeDetails = ($_GET['includeDetails'] ?? '1') === '1';
$showProIntMetadata = showResepPpcGuidanceProIntMetadata($conn);

// Create Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Data Resep');

// Define Headers
$headers = [
    'No', 'No CP', 'Kode Grey', 'Mesin', 'Kode Warna', 'Color Name', 'Total Cost / Meter',
    'Resep Prod Code', 'Resep Prod Name', 'Resep No', 'Resep Seq', 'Resep Date',
    'Resep Type', 'No CP Resep', 'No SO', 'Rtg Code', 'Rtg Name', 'Status Desc',
    'Cus Color', 'Label Jual', 'Lot No', 'Weight', 'Plan Qty', 'Vlot', 'Status Resep',
    'Created At', 'Created By', 'Updated At', 'Updated By'
];
if ($includeDetails) {
    $headers = array_merge($headers, [
        'Item Kode', 'Item Name', 'Category', 'Qty/Recipe', 'UOM', 'CF', 'UOM CF', 'Price', 'Total'
    ]);
}

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
    
    $details = [null];
    if ($includeDetails) {
        $detailSql = "
            SELECT detail.*,
                COALESCE(NULLIF(LTRIM(RTRIM(legacy_master.codeprod_proint)), ''), detail.kode) AS display_kode
            FROM dbo.resep_obat_detail_v2 detail
            OUTER APPLY (
                SELECT TOP 1 master.codeprod_proint
                FROM dbo.resep_master_obat master
                WHERE (master.kode_obat = detail.kode OR master.codeprod_proint = detail.kode)
                ORDER BY CASE WHEN LTRIM(RTRIM(master.nama_obat)) = LTRIM(RTRIM(detail.name)) THEN 0 ELSE 1 END, master.id DESC
            ) legacy_master
            WHERE detail.id_resep = ?
            ORDER BY detail.table_index, detail.id
        ";
        $detailStmt = sqlsrv_query($conn, $detailSql, [$resepId]);
        if ($detailStmt === false) {
            die(print_r(sqlsrv_errors(), true));
        }

        $details = [];
        while ($detailRow = sqlsrv_fetch_array($detailStmt, SQLSRV_FETCH_ASSOC)) {
            $details[] = $detailRow;
        }
        if (empty($details)) {
            $details = [null];
        }
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
        'G' => (isset($resep['total_cost_per_meter']) && (float)$resep['total_cost_per_meter'] > 0) ? 'Rp ' . number_format((float)$resep['total_cost_per_meter'], 2, ',', '.') : '-',
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
        'T' => $labelsByProductionNumber[trim((string) ($resep['no_cp'] ?? ''))] ?? '-',
        'U' => $resep['lot_no'] ? $resep['lot_no'] : '-',
        'V' => number_format($resep['weight'], 4, '.', ''),
        'W' => number_format($resep['plan_qty'], 2, '.', ''),
        'X' => $resep['vlot'] ? $resep['vlot'] : '-',
        'Y' => $resep['status_resep_lipat'] ? $resep['status_resep_lipat'] : '-',
        'Z' => $created_at ? $created_at : '-',
        'AA' => $resep['created_by'] ? $resep['created_by'] : '-',
        'AB' => ($resep['updated_at'] instanceof DateTime ? $resep['updated_at']->format('d-m-Y H:i') : '-'),
        'AC' => $resep['updated_by'] ? $resep['updated_by'] : '-'
    ];

    foreach ($details as $detail) {
        foreach ($headerTemplate as $col => $val) {
            $sheet->setCellValue($col . $rowNum, $val);
        }

        if ($detail) {
            $sheet->setCellValue('AD' . $rowNum, $detail['display_kode'] ?? $detail['kode'] ?? '-');
            $sheet->getStyle('AD' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->setCellValue('AE' . $rowNum, $detail['name'] ?? '-');
            $sheet->setCellValue('AF' . $rowNum, $detail['category'] ?? '-');
            $sheet->setCellValue('AG' . $rowNum, number_format($detail['receipe'] ?? 0, 4, '.', ''));
            $sheet->setCellValue('AH' . $rowNum, $detail['uom'] ?? '-');
            $sheet->setCellValue('AI' . $rowNum, number_format($detail['cf'] ?? 0, 4, '.', ''));
            $sheet->setCellValue('AJ' . $rowNum, $detail['uom_cf'] ?? '-');

            $priceText = 'Rp ' . number_format($detail['std_price'] ?? 0, 2, ',', '.');
            if (!empty($detail['price_satuan'])) {
                $priceText .= ' / ' . $detail['price_satuan'];
            }
            $sheet->setCellValue('AK' . $rowNum, $priceText);
            $sheet->setCellValue('AL' . $rowNum, 'Rp ' . number_format($detail['total'] ?? 0, 2, ',', '.'));
        }
        
        $rowNum++;
    }
    
    if (!$includeDetails) {
        $no++;
        continue;
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
    
    $sheet->setCellValue('AD' . $rowNum, 'COST SUMMARY PER METER');
    $sheet->mergeCells('AD' . $rowNum . ':AL' . $rowNum);
    $sheet->getStyle('AD' . $rowNum . ':AL' . $rowNum)->applyFromArray([
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
        
        $sheet->setCellValue('AD' . $rowNum, strtoupper($cat) . ' - DYE STUFF');
        $sheet->mergeCells('AD' . $rowNum . ':AH' . $rowNum);
        
        $sheet->setCellValue('AI' . $rowNum, number_format($totalCF, 2, '.', ',') . ' CF');
        $sheet->mergeCells('AI' . $rowNum . ':AK' . $rowNum);
        
        $sheet->setCellValue('AL' . $rowNum, 'Rp ' . number_format($cCost, 0, ',', '.'));
        
        $rowNum++;
    }
    
    // 3. Total Row
    foreach ($headerTemplate as $col => $val) {
        $sheet->setCellValue($col . $rowNum, $val);
    }
    
    $sheet->setCellValue('AD' . $rowNum, 'TOTAL COST/METER');
    $sheet->mergeCells('AD' . $rowNum . ':AK' . $rowNum);
    $sheet->setCellValue('AL' . $rowNum, 'Rp ' . number_format($totalCost, 0, ',', '.'));
    
    $sheet->getStyle('AD' . $rowNum . ':AK' . $rowNum)->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF2CC']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER]
    ]);
    $sheet->getStyle('AL' . $rowNum)->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF2CC']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER]
    ]);
    
    $rowNum++;

    // Merge Header Columns A-AB
    $lastRow = $rowNum - 1;
    if ($lastRow > $firstRow) {
        $headerColumns = array_keys($headerTemplate);
        foreach ($headerColumns as $colLetter) {
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

if (!$showProIntMetadata) {
    // H:R contains all ProInt metadata, including Resep Seq and split Routing columns.
    $sheet->removeColumn('H', 11);
}

// Output
$filename = ($includeDetails ? 'Data_Resep_Detail_' : 'Data_Resep_Ringkasan_') . date('Ymd_His') . '.xlsx';

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
