<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
require_once __DIR__ . '/../weaving_permissions.php';
require_once __DIR__ . '/temp_compressor_helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

weaving_require($conn, 'CanView');

$start = $_POST['start_date'] ?? date('Y-m-d');
$end = $_POST['end_date'] ?? $start;
if (strtotime($start) > strtotime($end)) [$start, $end] = [$end, $start];

$rows = [];
$keteranganList = [];
if (temp_compressor_table_exists($conn)) {
    $sql = "SELECT Tanggal, Jam, Compressor1_In_C, Compressor1_Out_C, Compressor2_In_C, Compressor2_Out_C,
                   Amper, PressureBar_P1, PressureBar_P2,
                   Temperature_T1, Temperature_T2, Temperature_T3,
                   Dryer_C, TekananAir_In, TekananAir_Out,
                   Petugas, Keterangan
            FROM dbo.temp_compressor
            WHERE CAST(Tanggal AS DATE) BETWEEN ? AND ?
            ORDER BY CAST(Tanggal AS DATE) ASC, CAST(Jam AS TIME) ASC, Id ASC";
    $stmt = sqlsrv_query($conn, $sql, [$start, $end]);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $row;
            $ket = trim((string)($row['Keterangan'] ?? ''));
            if ($ket !== '' && !in_array($ket, $keteranganList, true)) {
                $keteranganList[] = $ket;
            }
        }
        sqlsrv_free_stmt($stmt);
    }
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Report Temp Compressor');
$spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

// Column widths: A=Jam, B-E=Comp1/2, F=Amper, G-H=PressureBar, I-K=Temp, L=Dryer, M-N=TekananAir, O=Petugas
$colWidths = ['A'=>12,'B'=>12,'C'=>12,'D'=>12,'E'=>12,'F'=>10,'G'=>10,'H'=>10,'I'=>10,'J'=>10,'K'=>10,'L'=>12,'M'=>10,'N'=>10,'O'=>26];
foreach ($colWidths as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

// Row 1: Title
$sheet->mergeCells("A1:O1");
$sheet->setCellValue("A1", "CHECK SHEET KOMPRESSOR SULLAIR");
$sheet->getStyle("A1")->getFont()->setBold(true)->setSize(12);
$sheet->getStyle("A1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getRowDimension(1)->setRowHeight(24);

// Row 2: Date & Doc Code
$dateLabel = ($start === $end)
    ? date('d/m/Y', strtotime($start))
    : date('d/m/Y', strtotime($start)) . " - " . date('d/m/Y', strtotime($end));

$sheet->mergeCells("A2:N2");
$sheet->setCellValue("A2", "TANGGAL : " . $dateLabel);
$sheet->getStyle("A2")->getFont()->setBold(true)->setSize(10);
$sheet->getStyle("A2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->setCellValue("O2", "SUM-FM-THK-016");
$sheet->getStyle("O2")->getFont()->setBold(true)->setSize(10);
$sheet->getStyle("O2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getRowDimension(2)->setRowHeight(20);

// Row 3 & 4: Table Headers
// Row 3: group headers
$sheet->mergeCells("A3:A4"); $sheet->setCellValue("A3", "JAM");
$sheet->mergeCells("B3:C3"); $sheet->setCellValue("B3", "COMPRESSOR 1");
$sheet->mergeCells("D3:E3"); $sheet->setCellValue("D3", "COMPRESSOR 2");
$sheet->mergeCells("F3:F4"); $sheet->setCellValue("F3", "AMPER");
$sheet->mergeCells("G3:H3"); $sheet->setCellValue("G3", "PRESSURE BAR");
$sheet->mergeCells("I3:K3"); $sheet->setCellValue("I3", "TEMPERATURE °C");
$sheet->mergeCells("L3:L4"); $sheet->setCellValue("L3", "DRYER °C");
$sheet->mergeCells("M3:N3"); $sheet->setCellValue("M3", "TEKANAN AIR");
$sheet->mergeCells("O3:O4"); $sheet->setCellValue("O3", "PETUGAS");

// Row 4: sub-headers
$sheet->setCellValue("B4", "IN °C");
$sheet->setCellValue("C4", "OUT °C");
$sheet->setCellValue("D4", "IN °C");
$sheet->setCellValue("E4", "OUT °C");
$sheet->setCellValue("G4", "P1");
$sheet->setCellValue("H4", "P2");
$sheet->setCellValue("I4", "T1");
$sheet->setCellValue("J4", "T2");
$sheet->setCellValue("K4", "T3");
$sheet->setCellValue("M4", "IN");
$sheet->setCellValue("N4", "OUT");

$headerRange = "A3:O4";
$sheet->getStyle($headerRange)->getFont()->setBold(true)->setSize(10);
$sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('9DC3E6');
$sheet->getRowDimension(3)->setRowHeight(20);
$sheet->getRowDimension(4)->setRowHeight(20);

// Data Rows
$r = 5;
if (count($rows) === 0) {
    $sheet->mergeCells("A5:O5");
    $sheet->setCellValue("A5", "Tidak ada data.");
    $sheet->getStyle("A5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getRowDimension(5)->setRowHeight(20);
    $lastRow = 5;
} else {
    foreach ($rows as $row) {
        $sheet->getRowDimension($r)->setRowHeight(20);

        $jamFormatted = temp_compressor_display_hour(temp_compressor_fmt_time($row['Jam'] ?? null));
        $sheet->setCellValueExplicit("A{$r}", (string)$jamFormatted, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue("B{$r}", temp_compressor_fmt_num($row['Compressor1_In_C'] ?? null, 0));
        $sheet->setCellValue("C{$r}", temp_compressor_fmt_num($row['Compressor1_Out_C'] ?? null, 0));
        $sheet->setCellValue("D{$r}", temp_compressor_fmt_num($row['Compressor2_In_C'] ?? null, 0));
        $sheet->setCellValue("E{$r}", temp_compressor_fmt_num($row['Compressor2_Out_C'] ?? null, 0));
        $sheet->setCellValue("F{$r}", temp_compressor_fmt_num($row['Amper'] ?? null, 0));
        $sheet->setCellValue("G{$r}", temp_compressor_fmt_num($row['PressureBar_P1'] ?? null, 1));
        $sheet->setCellValue("H{$r}", temp_compressor_fmt_num($row['PressureBar_P2'] ?? null, 1));
        $sheet->setCellValue("I{$r}", temp_compressor_fmt_num($row['Temperature_T1'] ?? null, 1));
        $sheet->setCellValue("J{$r}", temp_compressor_fmt_num($row['Temperature_T2'] ?? null, 1));
        $sheet->setCellValue("K{$r}", temp_compressor_fmt_num($row['Temperature_T3'] ?? null, 1));
        $sheet->setCellValue("L{$r}", temp_compressor_fmt_num($row['Dryer_C'] ?? null, 0));
        $sheet->setCellValue("M{$r}", temp_compressor_fmt_num($row['TekananAir_In'] ?? null, 1));
        $sheet->setCellValue("N{$r}", temp_compressor_fmt_num($row['TekananAir_Out'] ?? null, 1));
        $sheet->setCellValueExplicit("O{$r}", (string)($row['Petugas'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        $sheet->getStyle("A{$r}:N{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("O{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);

        $r++;
    }

    // Row Keterangan
    $sheet->getRowDimension($r)->setRowHeight(24);
    $sheet->setCellValue("A{$r}", "KETERANGAN");
    $sheet->mergeCells("B{$r}:O{$r}");
    $sheet->setCellValue("B{$r}", !empty($keteranganList) ? implode('; ', $keteranganList) : '-');
    $sheet->getStyle("A{$r}")->getFont()->setBold(true)->setSize(10);
    $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("A{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('B7B7B7');
    $sheet->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);

    $lastRow = $r;
}

$sheet->getStyle("A1:O{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->setShowGridLines(true);

$filename = 'Check_Sheet_Kompressor_Sullair_' . date('Ymd_His') . '.xlsx';

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
