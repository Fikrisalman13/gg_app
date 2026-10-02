<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/temp_compressor_v2_helper.php');

$permissions = weaving_require($conn, 'CanView');
$canEdit = weaving_can($permissions, 'CanEdit');
$canDelete = weaving_can($permissions, 'CanDelete');
if (!$canEdit && !$canDelete) {
    header('Location: temp_compressor_v2.php');
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$start = trim($_POST['start_date'] ?? $_GET['start_date'] ?? date('Y-m-d'));
$end = trim($_POST['end_date'] ?? $_GET['end_date'] ?? date('Y-m-d'));
$weavingFilter = intval($_POST['weaving'] ?? $_GET['weaving'] ?? 0);
$compressorFilter = intval($_POST['compressor_no'] ?? $_GET['compressor_no'] ?? 0);

$whereParts = ['CAST(Tanggal AS DATE) >= ?', 'CAST(Tanggal AS DATE) <= ?'];
$params = [$start, $end];

if (in_array($weavingFilter, [1, 2], true)) {
    $whereParts[] = 'Weaving = ?';
    $params[] = $weavingFilter;
}
if (in_array($compressorFilter, [1, 2, 3], true)) {
    $whereParts[] = 'Compressor_No = ?';
    $params[] = $compressorFilter;
}

$whereClause = implode(' AND ', $whereParts);

$sheetListSql = "SELECT CAST(Tanggal AS DATE) AS Tanggal, Weaving, Compressor_No
                 FROM dbo.temp_compressor_v2
                 WHERE $whereClause
                 GROUP BY CAST(Tanggal AS DATE), Weaving, Compressor_No
                 ORDER BY CAST(Tanggal AS DATE) ASC, Weaving ASC, Compressor_No ASC";
$sheetStmt = sqlsrv_query($conn, $sheetListSql, $params);

$sheetsData = [];
if ($sheetStmt) {
    while ($sRow = sqlsrv_fetch_array($sheetStmt, SQLSRV_FETCH_ASSOC)) {
        $tglStr = temp_compressor_v2_fmt_date($sRow['Tanggal'] ?? null, 'Y-m-d');
        $w = (int)$sRow['Weaving'];
        $c = (int)$sRow['Compressor_No'];

        $cells = temp_compressor_v2_get_sheet_cells($conn, $tglStr, $w, $c);
        $ket = temp_compressor_v2_keterangan_for_sheet_query($conn, $tglStr, $w, $c);

        $sheetsData[] = [
            'tanggal'       => $tglStr,
            'tanggal_disp'  => temp_compressor_v2_fmt_date($sRow['Tanggal'] ?? null, 'd/m/Y'),
            'weaving'       => $w,
            'compressor_no' => $c,
            'cells'         => $cells,
            'keterangan'    => $ket,
        ];
    }
    sqlsrv_free_stmt($sheetStmt);
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Sullair V2");

$colWidths = [
    'A' => 10, 'B' => 10, 'C' => 10, 'D' => 10, 'E' => 10, 'F' => 10,
    'G' => 11, 'H' => 14, 'I' => 12, 'J' => 12, 'K' => 12, 'L' => 12, 'M' => 24
];
foreach ($colWidths as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

$currentRow = 1;
$hours = temp_compressor_v2_hours();

if (count($sheetsData) === 0) {
    $sheet->setCellValue("A1", "Tidak ada data untuk periode filter ini.");
} else {
    foreach ($sheetsData as $sIdx => $sData) {
        $rStart = $currentRow;

        // Row 1: Title
        $sheet->mergeCells("A{$currentRow}:M{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", "CHECK SHEET KOMPRESSOR SULLAIR");
        $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($currentRow)->setRowHeight(24);
        $currentRow++;

        // Row 2: Metadata (Compressor No, Weaving, Tanggal, Form Doc Code)
        $sheet->mergeCells("A{$currentRow}:F{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", "COMPRESSOR NO: " . $sData['compressor_no'] . " (1 / 2 / 3)      WEAVING: " . $sData['weaving'] . " (1 / 2)");
        $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->mergeCells("G{$currentRow}:J{$currentRow}");
        $sheet->setCellValue("G{$currentRow}", "TANGGAL : " . $sData['tanggal_disp']);
        $sheet->getStyle("G{$currentRow}")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("G{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->mergeCells("K{$currentRow}:M{$currentRow}");
        $sheet->setCellValue("K{$currentRow}", "SUM-FM-THK-016");
        $sheet->getStyle("K{$currentRow}")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("K{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($currentRow)->setRowHeight(20);
        $currentRow++;

        // Header Rows (2 rows)
        $h1 = $currentRow;
        $h2 = $currentRow + 1;

        $sheet->mergeCells("A{$h1}:A{$h2}"); $sheet->setCellValue("A{$h1}", "JAM");
        $sheet->mergeCells("B{$h1}:C{$h1}"); $sheet->setCellValue("B{$h1}", "PRESSURE (BAR)");
        $sheet->setCellValue("B{$h2}", "P1");
        $sheet->setCellValue("C{$h2}", "P2");

        $sheet->mergeCells("D{$h1}:F{$h1}"); $sheet->setCellValue("D{$h1}", "TEMPERATURE");
        $sheet->setCellValue("D{$h2}", "T1");
        $sheet->setCellValue("E{$h2}", "T2");
        $sheet->setCellValue("F{$h2}", "T3");

        $sheet->mergeCells("G{$h1}:G{$h2}"); $sheet->setCellValue("G{$h1}", "DRYER\n(°C)");
        $sheet->mergeCells("H{$h1}:H{$h2}"); $sheet->setCellValue("H{$h1}", "ARUS\nLISTRIK (A)");

        $sheet->mergeCells("I{$h1}:L{$h1}"); $sheet->setCellValue("I{$h1}", "AIR COOLING");
        $sheet->setCellValue("I{$h2}", "PRESS. IN");
        $sheet->setCellValue("J{$h2}", "PRESS. OUT");
        $sheet->setCellValue("K{$h2}", "TEMP. IN");
        $sheet->setCellValue("L{$h2}", "TEMP. OUT");

        $sheet->mergeCells("M{$h1}:M{$h2}"); $sheet->setCellValue("M{$h1}", "PELAKSANA");

        $headerStyle = [
            'font' => ['bold' => true, 'size' => 9],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '9DC3E6']
            ]
        ];
        $sheet->getStyle("A{$h1}:M{$h2}")->applyFromArray($headerStyle);
        $sheet->getRowDimension($h1)->setRowHeight(18);
        $sheet->getRowDimension($h2)->setRowHeight(18);

        $currentRow += 2;

        // Data Rows
        $dataStart = $currentRow;
        foreach ($hours as $hour) {
            $c = $sData['cells'][$hour] ?? [];
            $displayHour = temp_compressor_v2_display_hour($hour);

            $sheet->setCellValue("A{$currentRow}", $displayHour);
            $sheet->setCellValue("B{$currentRow}", $c['pressure_p1'] ?? '');
            $sheet->setCellValue("C{$currentRow}", $c['pressure_p2'] ?? '');
            $sheet->setCellValue("D{$currentRow}", $c['temp_t1'] ?? '');
            $sheet->setCellValue("E{$currentRow}", $c['temp_t2'] ?? '');
            $sheet->setCellValue("F{$currentRow}", $c['temp_t3'] ?? '');
            $sheet->setCellValue("G{$currentRow}", $c['dryer_c'] ?? '');
            $sheet->setCellValue("H{$currentRow}", $c['arus_a'] ?? '');
            $sheet->setCellValue("I{$currentRow}", $c['press_in'] ?? '');
            $sheet->setCellValue("J{$currentRow}", $c['press_out'] ?? '');
            $sheet->setCellValue("K{$currentRow}", $c['temp_in'] ?? '');
            $sheet->setCellValue("L{$currentRow}", $c['temp_out'] ?? '');
            $sheet->setCellValue("M{$currentRow}", $c['pelaksana'] ?? '');

            $sheet->getStyle("A{$currentRow}:L{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("M{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getRowDimension($currentRow)->setRowHeight(18);

            $currentRow++;
        }

        // Keterangan Row
        $sheet->setCellValue("A{$currentRow}", "KETERANGAN");
        $sheet->mergeCells("B{$currentRow}:M{$currentRow}");
        $sheet->setCellValue("B{$currentRow}", $sData['keterangan'] ?: '-');
        $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$currentRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('B7B7B7');
        $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($currentRow)->setRowHeight(20);

        // Apply borders to the sheet block
        $sheet->getStyle("A{$rStart}:M{$currentRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $currentRow += 3; // Space between sheets
    }
}

$sheet->setShowGridLines(true);

$filename = 'Check_Sheet_Kompressor_Sullair_V2_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

if (ob_get_length()) {
    ob_end_clean();
}

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
