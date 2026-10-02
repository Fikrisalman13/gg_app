<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
require_once __DIR__ . '/../weaving_permissions.php';
require_once __DIR__ . '/air_dryer_helper.php';
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

$rowsByDate = [];
$keteranganByDate = [];
if (air_dryer_table_exists($conn)) {
    $sql = "SELECT Tanggal, Jam_Pengecekan, AirDryer1_Temp_In_C, AirDryer1_Temp_Out_C,
                AirDryer1_Tekanan_In_Bar, AirDryer1_Tekanan_Out_Bar,
                AirDryer2_Temp_In_C, AirDryer2_Temp_Out_C,
                AirDryer2_Tekanan_In_Bar, AirDryer2_Tekanan_Out_Bar, Petugas, CreatAt, Keterangan
            FROM dbo.air_dryer
            WHERE CAST(Tanggal AS DATE) BETWEEN ? AND ?
            ORDER BY CAST(Tanggal AS DATE) ASC, CAST(Jam_Pengecekan AS TIME) ASC, Id ASC";
    $stmt = sqlsrv_query($conn, $sql, [$start, $end]);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $dateKey = air_dryer_fmt_date($row['Tanggal'] ?? null, 'Y-m-d');
            $hourKey = air_dryer_fmt_time($row['Jam_Pengecekan'] ?? null);
            if ($dateKey === '' || $hourKey === '') continue;
            if (!isset($rowsByDate[$dateKey])) $rowsByDate[$dateKey] = [];
            $rowsByDate[$dateKey][$hourKey] = $row;
            $ket = trim((string)($row['Keterangan'] ?? ''));
            if ($ket !== '') {
                if (!isset($keteranganByDate[$dateKey])) $keteranganByDate[$dateKey] = [];
                if (!in_array($ket, $keteranganByDate[$dateKey], true)) {
                    $keteranganByDate[$dateKey][] = $ket;
                }
            }
        }
        sqlsrv_free_stmt($stmt);
    }
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Report Air Dryer');
$spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

// Set column widths
$sheet->getColumnDimension('A')->setWidth(14);
$sheet->getColumnDimension('B')->setWidth(11);
$sheet->getColumnDimension('C')->setWidth(11);
$sheet->getColumnDimension('D')->setWidth(11);
$sheet->getColumnDimension('E')->setWidth(11);
$sheet->getColumnDimension('F')->setWidth(11);
$sheet->getColumnDimension('G')->setWidth(11);
$sheet->getColumnDimension('H')->setWidth(11);
$sheet->getColumnDimension('I')->setWidth(11);
$sheet->getColumnDimension('J')->setWidth(28);

$currentRow = 1;

if (count($rowsByDate) === 0) {
    $sheet->mergeCells("A1:J1");
    $sheet->setCellValue("A1", "PENCATATAN AIR DRYER WEAVING");
    $sheet->getStyle("A1")->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle("A1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

    $sheet->mergeCells("A2:J2");
    $sheet->setCellValue("A2", "Tidak ada data.");
    $sheet->getStyle("A2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("A1:J2")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
} else {
    $firstTable = true;
    foreach ($rowsByDate as $dateKey => $dateRows) {
        if (!$firstTable) {
            $currentRow += 2; // Spacer between dates
        }
        $firstTable = false;

        $rTitle = $currentRow;
        $rDate = $currentRow + 1;
        $rH1 = $currentRow + 2;
        $rH2 = $currentRow + 3;
        $rH3 = $currentRow + 4;
        $rDataStart = $currentRow + 5;

        // Row 1: Title
        $sheet->mergeCells("A{$rTitle}:J{$rTitle}");
        $sheet->setCellValue("A{$rTitle}", "PENCATATAN AIR DRYER WEAVING");
        $sheet->getStyle("A{$rTitle}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A{$rTitle}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($rTitle)->setRowHeight(24);

        // Row 2: Date & Doc Code
        $sheet->mergeCells("A{$rDate}:I{$rDate}");
        $sheet->setCellValue("A{$rDate}", "TANGGAL : " . date('d/m/Y', strtotime($dateKey)));
        $sheet->getStyle("A{$rDate}")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("A{$rDate}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->setCellValue("J{$rDate}", "SUM-FM-THK-WV-017");
        $sheet->getStyle("J{$rDate}")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("J{$rDate}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($rDate)->setRowHeight(20);

        // Row 3, 4, 5: Table Header
        $sheet->mergeCells("A{$rH1}:A{$rH3}");
        $sheet->setCellValue("A{$rH1}", "JAM\nPENGECEKAN");

        $sheet->mergeCells("B{$rH1}:E{$rH1}");
        $sheet->setCellValue("B{$rH1}", "AIR DRYER 1");

        $sheet->mergeCells("F{$rH1}:I{$rH1}");
        $sheet->setCellValue("F{$rH1}", "AIR DRYER 2");

        $sheet->mergeCells("J{$rH1}:J{$rH3}");
        $sheet->setCellValue("J{$rH1}", "PETUGAS");

        // Subheader Row 4
        $sheet->mergeCells("B{$rH2}:C{$rH2}");
        $sheet->setCellValue("B{$rH2}", "TEMPERATUR AIR");
        $sheet->mergeCells("D{$rH2}:E{$rH2}");
        $sheet->setCellValue("D{$rH2}", "TEKANAN AIR");

        $sheet->mergeCells("F{$rH2}:G{$rH2}");
        $sheet->setCellValue("F{$rH2}", "TEMPERATUR AIR");
        $sheet->mergeCells("H{$rH2}:I{$rH2}");
        $sheet->setCellValue("H{$rH2}", "TEKANAN AIR");

        // Subheader Row 5
        $sheet->setCellValue("B{$rH3}", "IN (°C)");
        $sheet->setCellValue("C{$rH3}", "OUT (°C)");
        $sheet->setCellValue("D{$rH3}", "IN (BAR)");
        $sheet->setCellValue("E{$rH3}", "OUT (BAR)");
        $sheet->setCellValue("F{$rH3}", "IN (°C)");
        $sheet->setCellValue("G{$rH3}", "OUT (°C)");
        $sheet->setCellValue("H{$rH3}", "IN (BAR)");
        $sheet->setCellValue("I{$rH3}", "OUT (BAR)");

        // Styling Headers
        $headerRange = "A{$rH1}:J{$rH3}";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('9DC3E6');
        $sheet->getRowDimension($rH1)->setRowHeight(20);
        $sheet->getRowDimension($rH2)->setRowHeight(18);
        $sheet->getRowDimension($rH3)->setRowHeight(18);

        // Data Rows
        $r = $rDataStart;
        foreach (air_dryer_hours() as $hour) {
            $row = $dateRows[$hour] ?? [];
            $sheet->getRowDimension($r)->setRowHeight(20);

            $sheet->setCellValueExplicit("A{$r}", $hour, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("B{$r}", air_dryer_fmt_num($row['AirDryer1_Temp_In_C'] ?? null, 1));
            $sheet->setCellValue("C{$r}", air_dryer_fmt_num($row['AirDryer1_Temp_Out_C'] ?? null, 1));
            $sheet->setCellValue("D{$r}", air_dryer_fmt_num($row['AirDryer1_Tekanan_In_Bar'] ?? null, 1));
            $sheet->setCellValue("E{$r}", air_dryer_fmt_num($row['AirDryer1_Tekanan_Out_Bar'] ?? null, 1));
            $sheet->setCellValue("F{$r}", air_dryer_fmt_num($row['AirDryer2_Temp_In_C'] ?? null, 1));
            $sheet->setCellValue("G{$r}", air_dryer_fmt_num($row['AirDryer2_Temp_Out_C'] ?? null, 1));
            $sheet->setCellValue("H{$r}", air_dryer_fmt_num($row['AirDryer2_Tekanan_In_Bar'] ?? null, 1));
            $sheet->setCellValue("I{$r}", air_dryer_fmt_num($row['AirDryer2_Tekanan_Out_Bar'] ?? null, 1));
            $sheet->setCellValueExplicit("J{$r}", (string)($row['Petugas'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            $sheet->getStyle("A{$r}:I{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("J{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);

            $r++;
        }

        // Row Keterangan
        $sheet->getRowDimension($r)->setRowHeight(24);
        $sheet->setCellValue("A{$r}", "KETERANGAN");
        $sheet->mergeCells("B{$r}:J{$r}");
        $sheet->setCellValue("B{$r}", !empty($keteranganByDate[$dateKey]) ? implode('; ', $keteranganByDate[$dateKey]) : '-');
        $sheet->getStyle("A{$r}")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('B7B7B7');
        $sheet->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);

        $lastRow = $r;
        $sheet->getStyle("A{$rTitle}:J{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $currentRow = $lastRow;
    }
}

$sheet->setShowGridLines(true);

$filename = 'Report_Air_Dryer_' . date('Ymd_His') . '.xlsx';

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
