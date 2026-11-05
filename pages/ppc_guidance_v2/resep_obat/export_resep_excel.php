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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// Increase execution time and memory limit for large exports
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '512M'); // Increase memory limit
ini_set('max_execution_time', '300'); // 5 minutes

// Auth check
session_start();
if (!isset($_SESSION['UserName'])) {
    die("Access Denied");
}

$canSeePrice = (int) ($_SESSION['GroupId'] ?? 0) === 1;
if (!$canSeePrice) {
    $pricePermissionStatement = sqlsrv_query(
        $conn,
        "SELECT TOP 1 g.id
         FROM dbo.resep_obat_group_members m
         INNER JOIN dbo.resep_obat_groups g ON m.group_id = g.id
         WHERE m.username = ? AND g.role_type = 'KABAG'",
        [$_SESSION['UserName']]
    );

    $canSeePrice = $pricePermissionStatement
        && (bool) sqlsrv_fetch_array($pricePermissionStatement, SQLSRV_FETCH_ASSOC);

    if ($pricePermissionStatement) {
        sqlsrv_free_stmt($pricePermissionStatement);
    }
}

// Params
$startDate = $_GET['startDate'] ?? '';
$endDate   = $_GET['endDate'] ?? '';

// Fetch Resep Headers
$totalCostSelect = $canSeePrice
    ? ",
            (
                SELECT COALESCE(SUM(d.total), 0)
                FROM dbo.resep_obat_detail_v2 d
                WHERE d.id_resep = r.id
            ) / NULLIF(CASE WHEN r.plan_qty > 0 THEN r.plan_qty ELSE 1 END, 0) AS total_cost_per_meter"
    : '';

$sql = "SELECT 
            r.id,
            r.no_cp, 
            r.kode_grey,
            r.mesin,
            r.kode_warna,
            r.color_name,
            r.color_desc$totalCostSelect,
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
$proIntHeaders = $showProIntMetadata
    ? [
        'resep_prod_code' => 'Resep Prod Code',
        'resep_prod_name' => 'Resep Prod Name',
        'resep_no' => 'Resep No',
        'resep_seq' => 'Resep Seq',
        'resep_date' => 'Resep Date',
        'resep_type' => 'Resep Type',
        'no_cp_resep' => 'No CP Resep',
        'no_so' => 'No SO',
        'rtg_code' => 'Rtg Code',
        'rtg_name' => 'Rtg Name',
        'status_desc' => 'Status Desc',
    ]
    : [];

$headers = array_merge(
    [
        'no' => 'No',
        'no_cp' => 'No CP',
        'kode_grey' => 'Kode Grey',
        'mesin' => 'Mesin',
        'kode_warna' => 'Kode Warna',
        'color_name' => 'Color Name',
    ],
    $canSeePrice ? ['total_cost_per_meter' => 'Total Cost / Meter'] : [],
    $proIntHeaders,
    [
        'cus_color' => 'Cus Color',
        'label_jual' => 'Label Jual',
        'lot_no' => 'Lot No',
        'weight' => 'Weight',
        'plan_qty' => 'Plan Qty',
        'vlot' => 'Vlot',
        'status_resep_lipat' => 'Status Resep',
        'created_at' => 'Created At',
        'created_by' => 'Created By',
        'updated_at' => 'Updated At',
        'updated_by' => 'Updated By',
    ]
);
if ($includeDetails) {
    $headers = array_merge(
        $headers,
        [
            'detail_item_code' => 'Item Kode',
            'detail_name' => 'Item Name',
            'detail_category' => 'Category',
            'detail_receipe' => 'Qty/Recipe',
            'detail_uom' => 'UOM',
            'detail_cf' => 'CF',
            'detail_uom_cf' => 'UOM CF',
        ],
        $canSeePrice
            ? ['detail_price' => 'Price', 'detail_total' => 'Total']
            : []
    );
}

$columnByKey = [];
foreach (array_keys($headers) as $index => $key) {
    $columnByKey[$key] = Coordinate::stringFromColumnIndex($index + 1);
}

// Write Headers
$col = 'A';
foreach ($headers as $headerLabel) {
    $sheet->setCellValue($col . '1', $headerLabel);
    $sheet->getColumnDimension($col)->setAutoSize(true);
    $col++;
}

// Style Header
$lastCol = Coordinate::stringFromColumnIndex(count($headers));

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
        $detailFields = $canSeePrice
            ? 'detail.*,'
            : 'detail.id, detail.id_resep, detail.table_index, detail.kode, detail.name, detail.category,
                detail.receipe, detail.uom, detail.cf, detail.uom_cf,';
        $detailSql = "
            SELECT $detailFields
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
    $firstRow = $rowNum;

    $headerTemplate = array_merge(
        [
            'no' => $no,
            'no_cp' => $resep['no_cp'] ? $resep['no_cp'] : '-',
            'kode_grey' => $resep['kode_grey'] ? $resep['kode_grey'] : '-',
            'mesin' => $resep['mesin'] ? $resep['mesin'] : '-',
            'kode_warna' => $resep['kode_warna'] ? $resep['kode_warna'] : '-',
            'color_name' => $resep['color_name'] ? $resep['color_name'] : '-',
        ],
        $canSeePrice
            ? [
                'total_cost_per_meter' => isset($resep['total_cost_per_meter'])
                    && (float) $resep['total_cost_per_meter'] > 0
                    ? 'Rp ' . number_format((float) $resep['total_cost_per_meter'], 2, ',', '.')
                    : '-',
            ]
            : [],
        $showProIntMetadata
            ? [
                'resep_prod_code' => $resep['resep_prod_code'] ? $resep['resep_prod_code'] : '-',
                'resep_prod_name' => $resep['resep_prod_name'] ? $resep['resep_prod_name'] : '-',
                'resep_no' => $resep['resep_no'] ? $resep['resep_no'] : '-',
                'resep_seq' => $resep['resep_seq'] ? $resep['resep_seq'] : '-',
                'resep_date' => $resep_date ?: '-',
                'resep_type' => $resep['resep_type'] ? $resep['resep_type'] : '-',
                'no_cp_resep' => $resep['no_cp_resep'] ? $resep['no_cp_resep'] : '-',
                'no_so' => $resep['no_so'] ? $resep['no_so'] : '-',
                'rtg_code' => $resep['rtg_code'] ? $resep['rtg_code'] : '-',
                'rtg_name' => $resep['rtg_name'] ? $resep['rtg_name'] : '-',
                'status_desc' => $resep['status_desc'] ? $resep['status_desc'] : '-',
            ]
            : [],
        [
            'cus_color' => $resep['cus_color'] ? $resep['cus_color'] : '-',
            'label_jual' => $labelsByProductionNumber[trim((string) ($resep['no_cp'] ?? ''))] ?? '-',
            'lot_no' => $resep['lot_no'] ? $resep['lot_no'] : '-',
            'weight' => number_format($resep['weight'], 4, '.', ''),
            'plan_qty' => number_format($resep['plan_qty'], 2, '.', ''),
            'vlot' => $resep['vlot'] ? $resep['vlot'] : '-',
            'status_resep_lipat' => $resep['status_resep_lipat'] ? $resep['status_resep_lipat'] : '-',
            'created_at' => $created_at ?: '-',
            'created_by' => $resep['created_by'] ? $resep['created_by'] : '-',
            'updated_at' => $resep['updated_at'] instanceof DateTime
                ? $resep['updated_at']->format('d-m-Y H:i')
                : '-',
            'updated_by' => $resep['updated_by'] ? $resep['updated_by'] : '-',
        ]
    );

    foreach ($details as $detail) {
        foreach ($headerTemplate as $key => $value) {
            $sheet->setCellValue($columnByKey[$key] . $rowNum, $value);
        }

        if ($detail) {
            $sheet->setCellValue($columnByKey['detail_item_code'] . $rowNum, $detail['display_kode'] ?? $detail['kode'] ?? '-');
            $sheet->getStyle($columnByKey['detail_item_code'] . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->setCellValue($columnByKey['detail_name'] . $rowNum, $detail['name'] ?? '-');
            $sheet->setCellValue($columnByKey['detail_category'] . $rowNum, $detail['category'] ?? '-');
            $sheet->setCellValue($columnByKey['detail_receipe'] . $rowNum, number_format($detail['receipe'] ?? 0, 4, '.', ''));
            $sheet->setCellValue($columnByKey['detail_uom'] . $rowNum, $detail['uom'] ?? '-');
            $sheet->setCellValue($columnByKey['detail_cf'] . $rowNum, number_format($detail['cf'] ?? 0, 4, '.', ''));
            $sheet->setCellValue($columnByKey['detail_uom_cf'] . $rowNum, $detail['uom_cf'] ?? '-');

            if ($canSeePrice) {
                $priceText = 'Rp ' . number_format($detail['std_price'] ?? 0, 2, ',', '.');
                if (!empty($detail['price_satuan'])) {
                    $priceText .= ' / ' . $detail['price_satuan'];
                }
                $sheet->setCellValue($columnByKey['detail_price'] . $rowNum, $priceText);
                $sheet->setCellValue($columnByKey['detail_total'] . $rowNum, 'Rp ' . number_format($detail['total'] ?? 0, 2, ',', '.'));
            }
        }
        
        $rowNum++;
    }
    
    if (!$includeDetails) {
        $no++;
        continue;
    }

    if (!$canSeePrice) {
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
    $detailStartColumn = $columnByKey['detail_item_code'];
    $detailUomColumn = $columnByKey['detail_uom'];
    $detailCfColumn = $columnByKey['detail_cf'];
    $detailPriceColumn = $columnByKey['detail_price'];
    $detailTotalColumn = $columnByKey['detail_total'];

    // 1. Title Row
    foreach ($headerTemplate as $key => $value) {
        $sheet->setCellValue($columnByKey[$key] . $rowNum, $value);
    }

    $sheet->setCellValue($detailStartColumn . $rowNum, 'COST SUMMARY PER METER');
    $sheet->mergeCells($detailStartColumn . $rowNum . ':' . $detailTotalColumn . $rowNum);
    $sheet->getStyle($detailStartColumn . $rowNum . ':' . $detailTotalColumn . $rowNum)->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
    ]);
    
    $rowNum++;

    // 2. Category Rows
    foreach ($catTotals as $cat => $total) {
        $cCost = $total / $planQty;
        $totalCF = $catCFTotals[$cat] ?? 0;
        
        foreach ($headerTemplate as $key => $value) {
            $sheet->setCellValue($columnByKey[$key] . $rowNum, $value);
        }

        $sheet->setCellValue($detailStartColumn . $rowNum, strtoupper($cat) . ' - DYE STUFF');
        $sheet->mergeCells($detailStartColumn . $rowNum . ':' . $detailUomColumn . $rowNum);

        $sheet->setCellValue($detailCfColumn . $rowNum, number_format($totalCF, 2, '.', ',') . ' CF');
        $sheet->mergeCells($detailCfColumn . $rowNum . ':' . $detailPriceColumn . $rowNum);

        $sheet->setCellValue($detailTotalColumn . $rowNum, 'Rp ' . number_format($cCost, 0, ',', '.'));

        $rowNum++;
    }

    // 3. Total Row
    foreach ($headerTemplate as $key => $value) {
        $sheet->setCellValue($columnByKey[$key] . $rowNum, $value);
    }

    $sheet->setCellValue($detailStartColumn . $rowNum, 'TOTAL COST/METER');
    $sheet->mergeCells($detailStartColumn . $rowNum . ':' . $detailPriceColumn . $rowNum);
    $sheet->setCellValue($detailTotalColumn . $rowNum, 'Rp ' . number_format($totalCost, 0, ',', '.'));

    $sheet->getStyle($detailStartColumn . $rowNum . ':' . $detailPriceColumn . $rowNum)->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF2CC']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER]
    ]);
    $sheet->getStyle($detailTotalColumn . $rowNum)->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF2CC']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER]
    ]);
    
    $rowNum++;

    // Merge header columns for rows with multiple detail or cost-summary records.
    $lastRow = $rowNum - 1;
    if ($lastRow > $firstRow) {
        foreach (array_keys($headerTemplate) as $key) {
            $column = $columnByKey[$key];
            $sheet->mergeCells($column . $firstRow . ':' . $column . $lastRow);
            $sheet->getStyle($column . $firstRow)->getAlignment()
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
