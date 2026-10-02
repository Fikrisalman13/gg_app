<?php
// export_excel_analisa.php
// Export Rekap Analisa Warna ke file Excel (.xlsx) lengkap dengan multi-sheet & chart native

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\GridLines;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

session_start();
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../../vendor/autoload.php';

// Auth check
if (!isset($_SESSION['UserName'])) {
    die("Akses ditolak. Silakan login terlebih dahulu.");
}

// Fetch selected or all periods from database GG
if (!$conn) {
    die("Koneksi ke database SQL Server GG tidak tersedia.");
}

$selectedIds = [];
if (!empty($_POST['selected_periode']) && is_array($_POST['selected_periode'])) {
    foreach ($_POST['selected_periode'] as $idVal) {
        $cleanId = (int)$idVal;
        if ($cleanId > 0) $selectedIds[] = $cleanId;
    }
} elseif (!empty($_GET['ids'])) {
    $rawIds = explode(',', $_GET['ids']);
    foreach ($rawIds as $idVal) {
        $cleanId = (int)trim($idVal);
        if ($cleanId > 0) $selectedIds[] = $cleanId;
    }
}

$periods = [];
if (!empty($selectedIds)) {
    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
    $sql = "SELECT * FROM analisa_warna_periode WHERE id IN ($placeholders) ORDER BY start_date ASC, id ASC";
    $stmt = sqlsrv_query($conn, $sql, $selectedIds);
} else {
    $sql = "SELECT * FROM analisa_warna_periode ORDER BY start_date ASC, id ASC";
    $stmt = sqlsrv_query($conn, $sql);
}

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $periods[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

if (empty($periods)) {
    die("Tidak ada data periode yang dipilih untuk diekspor. Silakan pilih minimal 1 periode di halaman Rekap Otomatis.");
}

$numRows = count($periods);
$spreadsheet = new Spreadsheet();

// Style presets
$headerFill = [
    'fillType' => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'D9E1F2'] // Soft corporate light blue
];
$subHeaderFill = [
    'fillType' => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'F2F2F2']
];
// Style presets
$allBorders = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];

// ============================================================================
// SHEET 1: % Pass Fail
// ============================================================================
$sheet1 = $spreadsheet->getActiveSheet();
$sheet1->setTitle('% Pass Fail');
$sheet1->setShowGridlines(true);

// Title in A1
$sheet1->setCellValue('A1', '% Pass & Fail Acc Warna R');
$sheet1->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setName('Calibri');

// Row dimensions
$sheet1->getRowDimension(1)->setRowHeight(24);
$sheet1->getRowDimension(2)->setRowHeight(14);
$sheet1->getRowDimension(3)->setRowHeight(22);
$sheet1->getRowDimension(4)->setRowHeight(20);

// Style presets sampled directly from screenshot
$fillD9E1F2 = [
    'fillType' => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'D9E1F2'] // Soft light blue-gray
];
$fillB4C6E7 = [
    'fillType' => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'B4C6E7'] // Steel blue
];
$fillD6DCE4 = [
    'fillType' => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'D6DCE4'] // Ice blue
];

// Headers (Row 3 & 4)
$sheet1->mergeCells('A3:A4');
$sheet1->setCellValue('A3', 'Periode');

$sheet1->mergeCells('B3:B4');
$sheet1->setCellValue('B3', 'Total CP');

$sheet1->mergeCells('C3:F3');
$sheet1->setCellValue('C3', 'CP');

$sheet1->mergeCells('G3:J3');
$sheet1->setCellValue('G3', 'Qty');

$sheet1->mergeCells('K3:L3');
$sheet1->setCellValue('K3', '%');

// Sub headers (Row 4)
$sheet1->setCellValue('C4', 'Pass');
$sheet1->setCellValue('D4', 'Pass Upg');
$sheet1->setCellValue('E4', 'Total Pass');
$sheet1->setCellValue('F4', 'Fail');

$sheet1->setCellValue('G4', 'Pass');
$sheet1->setCellValue('H4', 'Pass Upg');
$sheet1->setCellValue('I4', 'Total Pass');
$sheet1->setCellValue('J4', 'Fail');

$sheet1->setCellValue('K4', 'Pass');
$sheet1->setCellValue('L4', 'Fail');

// Style headers
$sheet1->getStyle('A3:L4')->applyFromArray($allBorders);
$sheet1->getStyle('A3:L4')->getFont()->setBold(true)->setName('Calibri')->setSize(11);
$sheet1->getStyle('A3:L4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet1->getStyle('A3:L4')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

// Apply precise alternating theme fills
$sheet1->getStyle('A3:A4')->applyFromArray($fillD9E1F2);
$sheet1->getStyle('B3:B4')->applyFromArray($fillD9E1F2);

// CP group
$sheet1->getStyle('C3:F3')->applyFromArray($fillB4C6E7);
$sheet1->getStyle('C4:F4')->applyFromArray($fillD6DCE4);

// Qty group
$sheet1->getStyle('G3:J3')->applyFromArray($fillD6DCE4);
$sheet1->getStyle('G4:J4')->applyFromArray($fillB4C6E7);

// % group
$sheet1->getStyle('K3:L3')->applyFromArray($fillB4C6E7);
$sheet1->getStyle('K4:L4')->applyFromArray($fillD9E1F2);

// Data Rows
$startRow = 5;
$currentRow = $startRow;

foreach ($periods as $p) {
    $sheet1->getRowDimension($currentRow)->setRowHeight(20);

    $sheet1->setCellValue("A$currentRow", $p['nama_periode']);
    $sheet1->setCellValue("B$currentRow", (int)$p['total_cp']);
    $sheet1->setCellValue("C$currentRow", (int)$p['cp_pass']);
    $sheet1->setCellValue("D$currentRow", (int)$p['cp_pass_upg']);
    $sheet1->setCellValue("E$currentRow", "=C$currentRow+D$currentRow");
    $sheet1->setCellValue("F$currentRow", (int)$p['cp_fail']);

    $sheet1->setCellValue("G$currentRow", (float)$p['qty_pass']);
    $sheet1->setCellValue("H$currentRow", (float)$p['qty_pass_upg']);
    $sheet1->setCellValue("I$currentRow", "=G$currentRow+H$currentRow");
    $sheet1->setCellValue("J$currentRow", (float)$p['qty_fail']);

    // Persentase Pass & Fail (Formula Excel)
    $sheet1->setCellValue("K$currentRow", "=IF(B$currentRow>0, E$currentRow/B$currentRow, 0)");
    $sheet1->setCellValue("L$currentRow", "=IF(B$currentRow>0, F$currentRow/B$currentRow, 0)");

    $currentRow++;
}
$endRow = $currentRow - 1;

// Formatting data rows
$sheet1->getStyle("A$startRow:L$endRow")->applyFromArray($allBorders);
$sheet1->getStyle("A$startRow:L$endRow")->getFont()->setName('Calibri')->setSize(11);
$sheet1->getStyle("A$startRow:L$endRow")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
$sheet1->getStyle("A$startRow:A$endRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$sheet1->getStyle("B$startRow:F$endRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet1->getStyle("B$startRow:F$endRow")->getNumberFormat()->setFormatCode('#,##0');
$sheet1->getStyle("G$startRow:J$endRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet1->getStyle("G$startRow:J$endRow")->getNumberFormat()->setFormatCode('#,##0.00');
$sheet1->getStyle("K$startRow:L$endRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet1->getStyle("K$startRow:L$endRow")->getNumberFormat()->setFormatCode('0%');

// Explicit column dimensions to match layout exactly
$sheet1->getColumnDimension('A')->setWidth(30);
$sheet1->getColumnDimension('B')->setWidth(11);
$sheet1->getColumnDimension('C')->setWidth(8);
$sheet1->getColumnDimension('D')->setWidth(11);
$sheet1->getColumnDimension('E')->setWidth(12);
$sheet1->getColumnDimension('F')->setWidth(8);
$sheet1->getColumnDimension('G')->setWidth(14);
$sheet1->getColumnDimension('H')->setWidth(13);
$sheet1->getColumnDimension('I')->setWidth(14);
$sheet1->getColumnDimension('J')->setWidth(13);
$sheet1->getColumnDimension('K')->setWidth(8);
$sheet1->getColumnDimension('L')->setWidth(8);

// Chart % Pass & Fail Acc Warna R
$dataSeriesLabels1 = [
    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, null, null, 1, ['Series1']),
    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, null, null, 1, ['Series2']),
];
$xAxisTickValues1 = [
    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'% Pass Fail'!\$A\$$startRow:\$A\$$endRow", null, $numRows),
];
$dataSeriesValues1 = [
    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'% Pass Fail'!\$K\$$startRow:\$K\$$endRow", null, $numRows),
    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'% Pass Fail'!\$L\$$startRow:\$L\$$endRow", null, $numRows),
];

// Hilangkan point markers (diamond/square) dan set warna garis (Series1: Biru, Series2: Orange)
$dataSeriesValues1[0]->setPointMarker('none');
$dataSeriesValues1[0]->setFillColor('4472C4');
$dataSeriesValues1[1]->setPointMarker('none');
$dataSeriesValues1[1]->setFillColor('ED7D31');

$series1 = new DataSeries(
    DataSeries::TYPE_LINECHART,
    DataSeries::GROUPING_STANDARD,
    range(0, count($dataSeriesValues1) - 1),
    $dataSeriesLabels1,
    $xAxisTickValues1,
    $dataSeriesValues1
);

$plotArea1 = new PlotArea(null, [$series1]);
$legend1 = new Legend(Legend::POSITION_BOTTOM, null, false);
$title1 = new Title('% Pass & Fail Acc Warna R');

$chart1 = new Chart(
    'chart_pass_fail',
    $title1,
    $legend1,
    $plotArea1,
    true,
    DataSeries::EMPTY_AS_GAP
);

// Tambahkan Garis Kisi Horizontal (Major Gridlines pada Sumbu Y)
$majorGridlines1 = new GridLines();
$majorGridlines1->setLineColorProperties('D9D9D9');
$majorGridlines1->setLineStyleProperties(0.75);
$chart1->getChartAxisY()->setMajorGridlines($majorGridlines1);

// Tambahkan Garis Bingkai Halus pada Grafik
$chart1->getBorderLines()->setLineColorProperties('D9D9D9');
$chart1->getBorderLines()->setLineStyleProperties(0.75);

// Chart position: starts 1 blank row below table, spans from Column A to F
$chartTop = $endRow + 2;
$chartBottom = $chartTop + 16;
$chart1->setTopLeftPosition("A$chartTop");
$chart1->setBottomRightPosition("F$chartBottom");
$sheet1->addChart($chart1);


// ============================================================================
// HELPER FUNCTION UNTUK SHEET KATEGORI FAIL
// ============================================================================
function createCategorySheet($spreadsheet, $sheetName, $titleText, $columnField, $periods, $headerFill, $allBorders) {
    $numRows = count($periods);
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle($sheetName);
    $sheet->setShowGridlines(true);

    // Title in A1
    $sheet->setCellValue('A1', $titleText);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

    // Headers in Row 3
    $sheet->setCellValue('A3', 'Periode');
    $sheet->setCellValue('B3', 'Total Qty');

    $sheet->getStyle('A3:B3')->applyFromArray($allBorders);
    $sheet->getStyle('A3:B3')->getFont()->setBold(true);
    $sheet->getStyle('A3:B3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A3:B3')->applyFromArray($headerFill);

    // Data in Row 4+
    $r = 4;
    foreach ($periods as $p) {
        $sheet->setCellValue("A$r", $p['nama_periode']);
        $sheet->setCellValue("B$r", (float)($p[$columnField] ?? 0));
        $r++;
    }
    $lastR = $r - 1;

    $sheet->getStyle("A4:B$lastR")->applyFromArray($allBorders);
    $sheet->getStyle("A4:A$lastR")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle("B4:B$lastR")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle("B4:B$lastR")->getNumberFormat()->setFormatCode('#,##0.00');

    $sheet->getColumnDimension('A')->setAutoSize(true);
    $sheet->getColumnDimension('B')->setAutoSize(true);

    // Chart Line
    $escapedSheet = "'" . str_replace("'", "''", $sheetName) . "'";
    $dataSeriesLabels = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "$escapedSheet!\$B\$3", null, 1)
    ];
    $xAxisTickValues = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "$escapedSheet!\$A\$4:\$A\$$lastR", null, $numRows)
    ];
    $dataSeriesValues = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "$escapedSheet!\$B\$4:\$B\$$lastR", null, $numRows)
    ];
    $dataSeriesValues[0]->setPointMarker('none');
    $dataSeriesValues[0]->setFillColor('4472C4');

    $series = new DataSeries(
        DataSeries::TYPE_LINECHART,
        DataSeries::GROUPING_STANDARD,
        range(0, count($dataSeriesValues) - 1),
        $dataSeriesLabels,
        $xAxisTickValues,
        $dataSeriesValues
    );

    $plotArea = new PlotArea(null, [$series]);
    $chartTitle = new Title($titleText);

    $chart = new Chart(
        'chart_' . preg_replace('/[^a-zA-Z0-9]/', '_', strtolower($sheetName)),
        $chartTitle,
        null, // No legend for single series chart
        $plotArea,
        true,
        DataSeries::EMPTY_AS_GAP
    );

    // Tambahkan Garis Kisi Horizontal (Major Gridlines pada Sumbu Y)
    $catGridlines = new GridLines();
    $catGridlines->setLineColorProperties('D9D9D9');
    $catGridlines->setLineStyleProperties(0.75);
    $chart->getChartAxisY()->setMajorGridlines($catGridlines);

    // Tambahkan Garis Bingkai Halus pada Grafik
    $chart->getBorderLines()->setLineColorProperties('D9D9D9');
    $chart->getBorderLines()->setLineStyleProperties(0.75);

    // Position chart next to table at D3 to K19
    $chart->setTopLeftPosition('D3');
    $chart->setBottomRightPosition('K19');
    $sheet->addChart($chart);
}

// ============================================================================
// SHEET 2: Over Warna
// ============================================================================
createCategorySheet($spreadsheet, 'Over Warna', 'Over Warna', 'qty_over_warna', $periods, $headerFill, $allBorders);

// ============================================================================
// SHEET 3: Repeat Shading
// ============================================================================
createCategorySheet($spreadsheet, 'Repeat Shading', 'Repeat Shading', 'qty_repeat_shading', $periods, $headerFill, $allBorders);

// ============================================================================
// SHEET 4: Shading
// ============================================================================
createCategorySheet($spreadsheet, 'Shading', 'Shading', 'qty_shading', $periods, $headerFill, $allBorders);

// ============================================================================
// SHEET 5: Soaping Ulang
// ============================================================================
createCategorySheet($spreadsheet, 'Soaping Ulang', 'Soaping Ulang', 'qty_soaping_ulang', $periods, $headerFill, $allBorders);

// ============================================================================
// SHEET 6: Top CPB
// ============================================================================
createCategorySheet($spreadsheet, 'Top CPB', 'Top CPB', 'qty_top_cpb', $periods, $headerFill, $allBorders);

// ============================================================================
// SHEET 7: Top Paddry
// ============================================================================
createCategorySheet($spreadsheet, 'Top Paddry', 'Top Paddry', 'qty_top_paddry', $periods, $headerFill, $allBorders);

// Set active sheet to the first sheet
$spreadsheet->setActiveSheetIndex(0);

// Stream as XLSX download
$filename = "Rekap_Analisa_Warna_" . date('Ymd_His') . ".xlsx";

// Clean any previous output buffer
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->setIncludeCharts(true);
$writer->save('php://output');
exit;
