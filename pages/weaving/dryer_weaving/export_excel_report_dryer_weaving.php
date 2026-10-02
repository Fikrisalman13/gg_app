<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
require_once __DIR__ . '/../weaving_permissions.php';
require_once __DIR__ . '/dryer_weaving_helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

weaving_require($conn, 'CanView');

$start = $_POST['start_date'] ?? date('Y-m-d');
$end = $_POST['end_date'] ?? $start;
$dryerNoFilter = trim($_POST['dryer_no'] ?? '');
$ctNoFilter = trim($_POST['ct_no'] ?? '');
if (strtotime($start) > strtotime($end)) [$start, $end] = [$end, $start];

$items = dryer_weaving_items();
$hours = dryer_weaving_hours();
$sheets = [];

if (dryer_weaving_table_exists($conn)) {
    $whereExtra = '';
    $params = [$start, $end];
    if ($dryerNoFilter !== '') {
        $whereExtra .= ' AND Dryer_No = ?';
        $params[] = $dryerNoFilter;
    }
    if ($ctNoFilter !== '') {
        $whereExtra .= ' AND Ct_No = ?';
        $params[] = $ctNoFilter;
    }

    $sql = "SELECT CAST(Tanggal AS DATE) AS Tanggal, Dryer_No, Ct_No, MAX([Shift]) AS ShiftName
            FROM dbo.dryer_weaving
            WHERE CAST(Tanggal AS DATE) BETWEEN ? AND ?
            $whereExtra
            GROUP BY CAST(Tanggal AS DATE), Dryer_No, Ct_No
            ORDER BY CAST(Tanggal AS DATE) ASC, Dryer_No ASC, Ct_No ASC";
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $tanggal = dryer_weaving_fmt_date($row['Tanggal'] ?? null, 'Y-m-d');
            $row['Cells'] = dryer_weaving_get_sheet_cells($conn, $tanggal, $row['Dryer_No'], $row['Ct_No']);
            $row['Shifts'] = dryer_weaving_shifts_for_sheet($row['Cells']);
            $row['PetugasByHour'] = dryer_weaving_petugas_by_hour_for_sheet($row['Cells']);
            $row['Keterangan'] = dryer_weaving_keterangan_for_sheet_query($conn, $tanggal, $row['Dryer_No'], $row['Ct_No']);
            $sheets[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Report Dryer Weaving');
$spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

$numCols = 2 + count($hours);
$lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($numCols);

$sheet->getColumnDimension('A')->setWidth(30);
$sheet->getColumnDimension('B')->setWidth(18);
for ($c = 3; $c <= $numCols; $c++) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
    $sheet->getColumnDimension($colLetter)->setWidth(7.5);
}

$currentRow = 1;

if (count($sheets) === 0) {
    $sheet->mergeCells("A1:{$lastColLetter}1");
    $sheet->setCellValue("A1", "LOG SHEET PERSHIFT DRYER D IN - W DAN COOLING TOWER (CT) INGERSOLL RAND");
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
        $rInfo = $currentRow + 1;
        $rH1 = $currentRow + 2;
        $rH2 = $currentRow + 3;
        $rDataStart = $currentRow + 4;

        // Row 1: Title
        $sheet->mergeCells("A{$rTitle}:{$lastColLetter}{$rTitle}");
        $sheet->setCellValue("A{$rTitle}", "LOG SHEET PERSHIFT DRYER D IN - W DAN COOLING TOWER (CT) INGERSOLL RAND");
        $sheet->getStyle("A{$rTitle}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A{$rTitle}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($rTitle)->setRowHeight(24);

        // Row 2: Tanggal, Dryer No, CT No, Doc Code
        // Total cols = 26 (A to Z)
        $sheet->mergeCells("A{$rInfo}:B{$rInfo}");
        $sheet->setCellValue("A{$rInfo}", "TANGGAL : " . dryer_weaving_fmt_date($sData['Tanggal'] ?? null, 'd/m/Y'));
        $sheet->getStyle("A{$rInfo}")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("A{$rInfo}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->mergeCells("C{$rInfo}:J{$rInfo}");
        $sheet->setCellValue("C{$rInfo}", "DRYER NO : " . ($sData['Dryer_No'] ?? ''));
        $sheet->getStyle("C{$rInfo}")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("C{$rInfo}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->mergeCells("K{$rInfo}:R{$rInfo}");
        $sheet->setCellValue("K{$rInfo}", "CT NO : " . ($sData['Ct_No'] ?? ''));
        $sheet->getStyle("K{$rInfo}")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("K{$rInfo}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->mergeCells("S{$rInfo}:{$lastColLetter}{$rInfo}");
        $sheet->setCellValue("S{$rInfo}", "SUM-FM-THK-WV-006");
        $sheet->getStyle("S{$rInfo}")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("S{$rInfo}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($rInfo)->setRowHeight(20);

        // Row 3 & 4: Headers
        $sheet->mergeCells("A{$rH1}:A{$rH2}");
        $sheet->setCellValue("A{$rH1}", "ITEM CHECK");

        $sheet->mergeCells("B{$rH1}:B{$rH2}");
        $sheet->setCellValue("B{$rH1}", "STANDARD");

        $sheet->mergeCells("C{$rH1}:{$lastColLetter}{$rH1}");
        $sheet->setCellValue("C{$rH1}", "JAM PEMERIKSAAN");

        $sheet->getRowDimension($rH1)->setRowHeight(20);
        $sheet->getRowDimension($rH2)->setRowHeight(20);

        for ($i = 0; $i < count($hours); $i++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + $i);
            $sheet->setCellValue("{$colLetter}{$rH2}", dryer_weaving_display_hour($hours[$i]));
        }

        $headerRange = "A{$rH1}:{$lastColLetter}{$rH2}";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('9DC3E6');

        // Items and Categories
        $r = $rDataStart;
        $currentCategory = '';
        foreach ($items as $item) {
            if ($item['category'] !== $currentCategory) {
                $currentCategory = $item['category'];
                $sheet->mergeCells("A{$r}:{$lastColLetter}{$r}");
                $sheet->setCellValue("A{$r}", $currentCategory);
                $sheet->getStyle("A{$r}")->getFont()->setBold(true)->setSize(10);
                $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("A{$r}:{$lastColLetter}{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('B7B7B7');
                $sheet->getRowDimension($r)->setRowHeight(20);
                $r++;
            }

            $cleanItem = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $item['item'])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $sheet->setCellValue("A{$r}", $cleanItem);
            $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);

            $sheet->setCellValue("B{$r}", $item['standard']);
            $sheet->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getRowDimension($r)->setRowHeight(str_contains($cleanItem, "\n") ? 28 : 20);

            for ($i = 0; $i < count($hours); $i++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + $i);
                $key = $item['key'] . '|' . $hours[$i];
                $val = (string)($sData['Cells'][$key]['nilai'] ?? '');
                $sheet->setCellValueExplicit("{$colLetter}{$r}", $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->getStyle("{$colLetter}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            }
            $r++;
        }

        // Shift Row
        $rShift = $r;
        $sheet->mergeCells("A{$rShift}:B{$rShift}");
        $sheet->setCellValue("A{$rShift}", "SHIFT");
        $sheet->getStyle("A{$rShift}")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("A{$rShift}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A{$rShift}:{$lastColLetter}{$rShift}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('B7B7B7');
        $sheet->getRowDimension($rShift)->setRowHeight(20);

        for ($i = 0; $i < count($hours); $i++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + $i);
            $shift = (string)($sData['Shifts'][$hours[$i]] ?? '');
            $sheet->setCellValueExplicit("{$colLetter}{$rShift}", $shift, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getStyle("{$colLetter}{$rShift}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        }
        $r++;

        // Petugas Row
        $rPetugas = $r;
        $sheet->mergeCells("A{$rPetugas}:B{$rPetugas}");
        $sheet->setCellValue("A{$rPetugas}", "PETUGAS");
        $sheet->getStyle("A{$rPetugas}")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("A{$rPetugas}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A{$rPetugas}:{$lastColLetter}{$rPetugas}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('B7B7B7');
        $sheet->getRowDimension($rPetugas)->setRowHeight(20);

        for ($i = 0; $i < count($hours); $i++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + $i);
            $petugas = (string)($sData['PetugasByHour'][$hours[$i]] ?? '');
            $sheet->setCellValueExplicit("{$colLetter}{$rPetugas}", $petugas, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getStyle("{$colLetter}{$rPetugas}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        }
        $r++;

        // Keterangan Row
        $rKet = $r;
        $sheet->mergeCells("A{$rKet}:B{$rKet}");
        $sheet->setCellValue("A{$rKet}", "KETERANGAN");
        $sheet->getStyle("A{$rKet}")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("A{$rKet}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A{$rKet}:B{$rKet}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('B7B7B7');

        $cStart = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3);
        $sheet->mergeCells("{$cStart}{$rKet}:{$lastColLetter}{$rKet}");
        $ketText = (string)($sData['Keterangan'] ?? '');
        $sheet->setCellValueExplicit("{$cStart}{$rKet}", $ketText !== '' ? $ketText : '-', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->getStyle("{$cStart}{$rKet}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension($rKet)->setRowHeight(22);

        $lastRow = $rKet;
        $sheet->getStyle("A{$rTitle}:{$lastColLetter}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $currentRow = $lastRow;
    }
}

$sheet->setShowGridLines(true);

$filename = 'Report_Dryer_Weaving_' . date('Ymd_His') . '.xlsx';

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
