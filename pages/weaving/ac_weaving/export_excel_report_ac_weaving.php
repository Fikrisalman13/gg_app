<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
require_once __DIR__ . '/../weaving_permissions.php';
require_once __DIR__ . '/ac_weaving_helper.php';

weaving_require($conn, 'CanView');

$normalizeDate = function ($value) {
    $value = trim((string)$value);
    if ($value === '') return '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
    if (preg_match('/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/', $value, $m)) return $m[3] . '-' . $m[2] . '-' . $m[1];
    return '';
};

$start = $normalizeDate($_POST['start_date'] ?? date('Y-m-d')) ?: date('Y-m-d');
$end = $normalizeDate($_POST['end_date'] ?? $start) ?: $start;
if (strtotime($start) > strtotime($end)) {
    [$start, $end] = [$end, $start];
}

function fmt_date_report($value)
{
    if ($value instanceof DateTime) return $value->format('d/m/Y');
    if (is_string($value) && $value !== '') {
        $ts = strtotime($value);
        return $ts ? date('d/m/Y', $ts) : $value;
    }
    return '';
}

function fmt_time_report($value)
{
    if ($value instanceof DateTime) $time = $value->format('H:i');
    elseif (is_string($value) && $value !== '') {
        $ts = strtotime($value);
        $time = $ts ? date('H:i', $ts) : substr($value, 0, 5);
    } else {
        return '';
    }
    return $time;
}

function fmt_num_report($value, $decimals = 2)
{
    if ($value === null || $value === '') return '';
    if (!is_numeric($value)) return (string)$value;
    return number_format((float)$value, $decimals, '.', '');
}

function format_indo_date_range($start, $end)
{
    $bulanIndo = [
        1 => 'JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI',
        'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER'
    ];
    $tsStart = strtotime($start);
    $tsEnd = strtotime($end);
    if (!$tsStart) return $start;
    $d1 = (int)date('d', $tsStart);
    $m1 = $bulanIndo[(int)date('n', $tsStart)] ?? strtoupper(date('F', $tsStart));
    $y1 = date('Y', $tsStart);
    if ($start === $end || !$tsEnd) {
        return "$d1 $m1 $y1";
    }
    $d2 = (int)date('d', $tsEnd);
    $m2 = $bulanIndo[(int)date('n', $tsEnd)] ?? strtoupper(date('F', $tsEnd));
    $y2 = date('Y', $tsEnd);
    if ($y1 === $y2 && $m1 === $m2) {
        return "$d1 - $d2 $m1 $y1";
    }
    return "$d1 $m1 $y1 - $d2 $m2 $y2";
}

function load_report_rows($conn, $start, $end)
{
    $res = ac_weaving_load_report_data($conn, $start, $end);
    if ($res === false) {
        echo '<b>SQL error</b>: ' . htmlspecialchars(print_r(sqlsrv_errors(), true));
        exit;
    }
    [$reportRows, $keterangans] = $res;
    return [$reportRows, [
        'AC WEAVING 1' => implode('; ', $keterangans['AC WEAVING 1']),
        'AC WEAVING 2' => implode('; ', $keterangans['AC WEAVING 2']),
    ]];
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

function render_machine_sheet_block($sheet, $startColIdx, $machine, $rows, $maxRows, $dateLabel, $keteranganText = '')
{
    $colLetters = [];
    for ($i = 0; $i < 8; $i++) {
        $colLetters[] = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startColIdx + $i);
    }
    [$c1, $c2, $c3, $c4, $c5, $c6, $c7, $c8] = $colLetters;

    // Set column widths matching table proportions
    $sheet->getColumnDimension($c1)->setWidth(14); // Tanggal
    $sheet->getColumnDimension($c2)->setWidth(10); // Jam
    $sheet->getColumnDimension($c3)->setWidth(13); // Dew Point
    $sheet->getColumnDimension($c4)->setWidth(12); // Humidity
    $sheet->getColumnDimension($c5)->setWidth(10); // Amper
    $sheet->getColumnDimension($c6)->setWidth(16); // Differential
    $sheet->getColumnDimension($c7)->setWidth(26); // Petugas
    $sheet->getColumnDimension($c8)->setWidth(12); // Shift

    // Row 1: Title
    $sheet->mergeCells("{$c1}1:{$c8}1");
    $sheet->setCellValue("{$c1}1", "PENGECEKAN TEMPERATUR AREA WEAVING");
    $sheet->getStyle("{$c1}1")->getFont()->setBold(true)->setSize(11);
    $sheet->getStyle("{$c1}1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getRowDimension(1)->setRowHeight(22);

    // Row 2: Date & Doc Code
    $sheet->mergeCells("{$c1}2:{$c6}2");
    $sheet->setCellValue("{$c1}2", "TANGGAL : " . $dateLabel);
    $sheet->getStyle("{$c1}2")->getFont()->setBold(true)->setSize(10);
    $sheet->getStyle("{$c1}2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);

    $sheet->mergeCells("{$c7}2:{$c8}2");
    $sheet->setCellValue("{$c7}2", "SUM-FM-THK-WV-005");
    $sheet->getStyle("{$c7}2")->getFont()->setBold(true)->setSize(10);
    $sheet->getStyle("{$c7}2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getRowDimension(2)->setRowHeight(20);

    // Row 3: Machine Title
    $sheet->mergeCells("{$c1}3:{$c8}3");
    $sheet->setCellValue("{$c1}3", $machine);
    $sheet->getStyle("{$c1}3")->getFont()->setBold(true)->setSize(11);
    $sheet->getStyle("{$c1}3")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getRowDimension(3)->setRowHeight(20);

    // Row 4: Table Headers
    $headers = [
        $c1 => "TANGGAL",
        $c2 => "JAM",
        $c3 => "pB1 Dew\nPoint",
        $c4 => "HUMIDITY",
        $c5 => "AMPER",
        $c6 => "DEFFERENTIAL\nBEST AIR",
        $c7 => "PETUGAS",
        $c8 => "SHIFT",
    ];

    $sheet->getRowDimension(4)->setRowHeight(28);
    foreach ($headers as $c => $text) {
        $sheet->setCellValue("{$c}4", $text);
        $style = $sheet->getStyle("{$c}4");
        $style->getFont()->setBold(true)->setSize(10);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('9DC3E6');
    }

    $lastRow = 4;
    if ($maxRows === 0) {
        $lastRow = 5;
        $sheet->mergeCells("{$c1}5:{$c8}5");
        $sheet->setCellValue("{$c1}5", "Tidak ada data.");
        $sheet->getStyle("{$c1}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(5)->setRowHeight(20);
    } else {
        for ($i = 0; $i < $maxRows; $i++) {
            $r = 5 + $i;
            $row = $rows[$i] ?? [];
            $sheet->getRowDimension($r)->setRowHeight(20);

            $sheet->setCellValueExplicit("{$c1}{$r}", (string)($row['tanggal'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("{$c2}{$r}", (string)($row['jam'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("{$c3}{$r}", $row['dew_point'] ?? '');
            $sheet->setCellValue("{$c4}{$r}", $row['humidity'] ?? '');
            $sheet->setCellValue("{$c5}{$r}", $row['amper'] ?? '');
            $sheet->setCellValue("{$c6}{$r}", $row['differential'] ?? '');
            $sheet->setCellValueExplicit("{$c7}{$r}", (string)($row['petugas'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("{$c8}{$r}", (string)($row['shift'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            // Alignments
            $sheet->getStyle("{$c1}{$r}:{$c6}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("{$c7}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("{$c8}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

            $lastRow = $r;
        }
    }

    // Apply borders to table (from row 1 to lastRow)
    $tableRange = "{$c1}1:{$c8}{$lastRow}";
    $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    // Keterangan di bawah tabel (sesuai tampilan Excel pada gambar)
    $ketRow = $lastRow + 2;
    $sheet->mergeCells("{$c1}{$ketRow}:{$c8}{$ketRow}");
    $sheet->setCellValue("{$c1}{$ketRow}", "Keterangan : " . ($keteranganText !== '' ? $keteranganText : '-'));
    $sheet->getStyle("{$c1}{$ketRow}")->getFont()->setSize(10);
    $sheet->getStyle("{$c1}{$ketRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    $lineCount = max(1, (int)ceil(strlen("Keterangan : " . ($keteranganText !== '' ? $keteranganText : '-')) / 80));
    $sheet->getRowDimension($ketRow)->setRowHeight(max(20, $lineCount * 18));
}

[$reportRows, $reportKeterangans] = load_report_rows($conn, $start, $end);
$maxRows = max(count($reportRows['AC WEAVING 1']), count($reportRows['AC WEAVING 2']));
$dateLabel = format_indo_date_range($start, $end);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Report AC Weaving');

// Set default font to Calibri 10
$spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

// Block 1: AC WEAVING 1 (Cols A - H = index 1 to 8)
render_machine_sheet_block($sheet, 1, 'AC WEAVING 1', $reportRows['AC WEAVING 1'], $maxRows, $dateLabel, $reportKeterangans['AC WEAVING 1']);

// Spacer Column I (index 9)
$sheet->getColumnDimension('I')->setWidth(4);

// Block 2: AC WEAVING 2 (Cols J - Q = index 10 to 17)
render_machine_sheet_block($sheet, 10, 'AC WEAVING 2', $reportRows['AC WEAVING 2'], $maxRows, $dateLabel, $reportKeterangans['AC WEAVING 2']);

// Disable grid lines visibility setting or leave default
$sheet->setShowGridLines(true);

// Output XLSX
$filename = 'Report_AC_Weaving_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

// Clean output buffer to prevent corrupted xlsx files
if (ob_get_length()) {
    ob_end_clean();
}

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
