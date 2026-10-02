<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
require_once __DIR__ . '/../weaving_permissions.php';
require_once __DIR__ . '/ccirl_helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

weaving_require($conn, 'CanView');

$start = $_POST['start_date'] ?? date('Y-m-d');
$end = $_POST['end_date'] ?? $start;
$compressorNo = trim($_POST['compressor_no'] ?? '');
if (strtotime($start) > strtotime($end)) [$start, $end] = [$end, $start];

$items = ccirl_items();
$hours = ccirl_hours();
$sheets = [];
if (ccirl_table_exists($conn)) {
    $whereExtra = '';
    $params = [$start, $end];
    if ($compressorNo !== '') {
        $whereExtra = ' AND Compressor_No = ?';
        $params[] = $compressorNo;
    }
    $sql = "SELECT CAST(Tanggal AS DATE) AS Tanggal, Compressor_No
            FROM dbo.ccirl
            WHERE CAST(Tanggal AS DATE) BETWEEN ? AND ? $whereExtra
            GROUP BY CAST(Tanggal AS DATE), Compressor_No
            ORDER BY CAST(Tanggal AS DATE) ASC, Compressor_No ASC";
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $dateKey = ccirl_fmt_date($row['Tanggal'] ?? null, 'Y-m-d');
            $row['Cells'] = ccirl_get_cells($conn, $dateKey, $row['Compressor_No']);
            $row['Keterangan'] = ccirl_keterangan_for_sheet_query($conn, $dateKey, $row['Compressor_No']);
            $sheets[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Report CCIRL');
$spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

// Total columns: Col A + count($hours)
$numCols = 1 + count($hours);
$lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($numCols);

$sheet->getColumnDimension('A')->setWidth(38);
for ($c = 2; $c <= $numCols; $c++) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
    $sheet->getColumnDimension($colLetter)->setWidth(7.5);
}

$currentRow = 1;

if (count($sheets) === 0) {
    $sheet->mergeCells("A1:{$lastColLetter}1");
    $sheet->setCellValue("A1", "CENTAC COMPRESSOR INGERSOLL RAND LOG SHEET");
    $sheet->getStyle("A1")->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle("A1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

    $sheet->mergeCells("A2:{$lastColLetter}2");
    $sheet->setCellValue("A2", "Tidak ada data.");
    $sheet->getStyle("A2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("A1:{$lastColLetter}2")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
} else {
    $firstTable = true;
    foreach ($sheets as $sData) {
        if (!$firstTable) {
            $currentRow += 2; // Spacer between tables
        }
        $firstTable = false;

        $rTitle = $currentRow;
        $rComp = $currentRow + 1;
        $rDate = $currentRow + 2;
        $rH1 = $currentRow + 3;
        $rH2 = $currentRow + 4;
        $rDataStart = $currentRow + 5;

        // Row 1: Title
        $sheet->mergeCells("A{$rTitle}:{$lastColLetter}{$rTitle}");
        $sheet->setCellValue("A{$rTitle}", "CENTAC COMPRESSOR INGERSOLL RAND LOG SHEET");
        $sheet->getStyle("A{$rTitle}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A{$rTitle}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($rTitle)->setRowHeight(24);

        // Row 2: Compressor No & Doc Code
        $sheet->setCellValue("A{$rComp}", "COMPRESSOR NO : " . ($sData['Compressor_No'] ?? ''));
        $sheet->getStyle("A{$rComp}")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("A{$rComp}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->mergeCells("B{$rComp}:{$lastColLetter}{$rComp}");
        $sheet->setCellValue("B{$rComp}", "SUM-FM-THK-WV-013");
        $sheet->getStyle("B{$rComp}")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("B{$rComp}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($rComp)->setRowHeight(20);

        // Row 3: Tanggal
        $sheet->mergeCells("A{$rDate}:{$lastColLetter}{$rDate}");
        $sheet->setCellValue("A{$rDate}", "TANGGAL : " . ccirl_fmt_date($sData['Tanggal'] ?? null, 'd/m/Y'));
        $sheet->getStyle("A{$rDate}")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("A{$rDate}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($rDate)->setRowHeight(20);

        // Row 4 & 5: Header
        $sheet->mergeCells("A{$rH1}:A{$rH2}");
        $sheet->setCellValue("A{$rH1}", "STATUS MESSAGE");

        $sheet->mergeCells("B{$rH1}:{$lastColLetter}{$rH1}");
        $sheet->setCellValue("B{$rH1}", "JAM PEMERIKSAAN");

        $sheet->getRowDimension($rH1)->setRowHeight(20);
        $sheet->getRowDimension($rH2)->setRowHeight(20);

        for ($i = 0; $i < count($hours); $i++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(2 + $i);
            $sheet->setCellValue("{$colLetter}{$rH2}", ccirl_display_hour($hours[$i]));
        }

        $headerRange = "A{$rH1}:{$lastColLetter}{$rH2}";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');

        // Items data
        $r = $rDataStart;
        foreach ($items as $item) {
            $cleanLabel = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $item['label'])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $sheet->setCellValue("A{$r}", $cleanLabel);
            $sheet->getStyle("A{$r}")->getFont()->setBold(true)->setSize(9.5);
            $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
            $sheet->getStyle("A{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EFEFEF');
            $sheet->getRowDimension($r)->setRowHeight(str_contains($cleanLabel, "\n") ? 26 : 20);

            for ($i = 0; $i < count($hours); $i++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(2 + $i);
                $key = $item['key'] . '|' . $hours[$i];
                $val = (string)($sData['Cells'][$key]['nilai'] ?? '');
                $sheet->setCellValueExplicit("{$colLetter}{$r}", $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->getStyle("{$colLetter}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            }
            $r++;
        }

        // Petugas Row
        $rPetugas = $r;
        $sheet->setCellValue("A{$rPetugas}", "PETUGAS");
        $sheet->getStyle("A{$rPetugas}")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("A{$rPetugas}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A{$rPetugas}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');
        $sheet->getRowDimension($rPetugas)->setRowHeight(20);

        for ($i = 0; $i < count($hours); $i++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(2 + $i);
            $petugas = ccirl_petugas_for_hour($sData['Cells'], $items, $hours[$i]);
            $sheet->setCellValueExplicit("{$colLetter}{$rPetugas}", (string)$petugas, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getStyle("{$colLetter}{$rPetugas}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        }

        // Keterangan Row
        $rKet = $rPetugas + 1;
        $sheet->setCellValue("A{$rKet}", "KETERANGAN");
        $sheet->getStyle("A{$rKet}")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("A{$rKet}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A{$rKet}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');
        $sheet->getRowDimension($rKet)->setRowHeight(20);

        $sheet->mergeCells("B{$rKet}:{$lastColLetter}{$rKet}");
        $sheet->setCellValue("B{$rKet}", !empty($sData['Keterangan']) ? $sData['Keterangan'] : '-');
        $sheet->getStyle("B{$rKet}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);

        $lastRow = $rKet;
        $sheet->getStyle("A{$rTitle}:{$lastColLetter}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $currentRow = $lastRow;
    }
}

$sheet->setShowGridLines(true);

$filename = 'Report_CCIRL_' . date('Ymd_His') . '.xlsx';

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
