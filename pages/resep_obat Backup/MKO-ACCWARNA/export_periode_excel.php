<?php

session_start();
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/functions.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

if (!isset($_SESSION['UserName'])) {
    die('Access Denied');
}

$kodePeriode = trim((string) ($_GET['kode_periode'] ?? ''));
if ($kodePeriode === '') {
    die('Kode periode wajib diisi.');
}

set_time_limit(300);
ini_set('memory_limit', '512M');

function exportCellValue($value)
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('d-m-Y H:i:s');
    }

    if ($value === null) {
        return '';
    }

    return (string) $value;
}

function addSequenceColumn(array $rows)
{
    foreach ($rows as $index => &$row) {
        $row['no'] = $index + 1;
    }
    unset($row);

    return $rows;
}

function formatRowDecimals(array $rows, array $columns, $scale = 4)
{
    foreach ($rows as &$row) {
        foreach ($columns as $column) {
            if (array_key_exists($column, $row)) {
                $row[$column] = formatDecimalValue($row[$column], $scale);
            }
        }
    }
    unset($row);

    return $rows;
}

function styleSheetHeader($sheet, $lastColumn)
{
    $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '1F4E78'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN],
        ],
    ]);
    $sheet->freezePane('A2');
}

function writeSheetData($sheet, array $headers, array $rows, array $keys)
{
    $colIndex = 1;
    foreach ($headers as $header) {
        $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex) . '1';
        $sheet->setCellValue($cell, $header);
        $sheet->getColumnDimensionByColumn($colIndex)->setAutoSize(true);
        $colIndex++;
    }

    $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
    styleSheetHeader($sheet, $lastColumn);

    $rowNum = 2;
    foreach ($rows as $row) {
        foreach ($keys as $i => $key) {
            $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1) . $rowNum;
            $sheet->setCellValue($cell, exportCellValue($row[$key] ?? null));
        }
        $rowNum++;
    }

    if ($rowNum > 2) {
        $sheet->getStyle('A2:' . $lastColumn . ($rowNum - 1))->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
    }
}

function styleRangeBorders($sheet, $startCell, $endCell)
{
    $sheet->getStyle($startCell . ':' . $endCell)->applyFromArray([
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN],
        ],
        'alignment' => [
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
    ]);
}

function styleCustomHeader($sheet, $startColumn, $row, array $headers)
{
    foreach ($headers as $index => $header) {
        $column = Coordinate::stringFromColumnIndex($startColumn + $index);
        $sheet->setCellValue($column . $row, $header);
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    $start = Coordinate::stringFromColumnIndex($startColumn) . $row;
    $end = Coordinate::stringFromColumnIndex($startColumn + count($headers) - 1) . $row;
    $sheet->getStyle($start . ':' . $end)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '1F7287'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
            'wrapText' => true,
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN],
        ],
    ]);
}

function writeCustomTable($sheet, $startColumn, $titleRow, $headerRow, array $headers, array $rows, array $keys, $title)
{
    $titleStart = Coordinate::stringFromColumnIndex($startColumn) . $titleRow;
    $titleEnd = Coordinate::stringFromColumnIndex($startColumn + count($headers) - 1) . $titleRow;
    $sheet->mergeCells($titleStart . ':' . $titleEnd);
    $sheet->setCellValue($titleStart, $title);
    $sheet->getStyle($titleStart)->getFont()->setBold(true);

    styleCustomHeader($sheet, $startColumn, $headerRow, $headers);

    $rowNum = $headerRow + 1;
    foreach ($rows as $row) {
        foreach ($keys as $index => $key) {
            $column = Coordinate::stringFromColumnIndex($startColumn + $index);
            $sheet->setCellValue($column . $rowNum, exportCellValue($row[$key] ?? null));
        }
        $rowNum++;
    }

    $lastDataRow = max($headerRow + 1, $rowNum - 1);
    styleRangeBorders(
        $sheet,
        Coordinate::stringFromColumnIndex($startColumn) . $headerRow,
        Coordinate::stringFromColumnIndex($startColumn + count($headers) - 1) . $lastDataRow
    );
}

try {
    $gg = getSqlsrvConnection('gg');
    ensureTablesExist($gg);

    $rawRows = getRowsByKodePeriode(
        $gg,
        'mko_rawdata',
        $kodePeriode,
        ['id', 'productionhdid', 'rtgmsid', 'prdnmbr', 'fgresult', 'rtgname', 'fgstatus', 'startdate', 'enddate', 'created_at'],
        'startdate, productionhdid'
    );

    $materialRows = getRowsByKodePeriode(
        $gg,
        'MKO_materialobat',
        $kodePeriode,
        ['id', 'prdnmbr', 'prodcode', 'prodname', 'labeljual', 'cuscolor', 'colorcode', 'colorname', 'rtgname', 'material_code', 'material_name', 'matqty', 'uomcode', 'unit_price', 'subtotal', 'prodcf', 'kelompok', 'vlot', 'created_at'],
        'prdnmbr, material_code'
    );

    $rekapRows = getRowsByKodePeriode(
        $gg,
        'MKO_rekap',
        $kodePeriode,
        ['labeljual', 'colorname', 'cuscolor', 'colorcode', 'master_resep', 'prdnmbr', 'status_proses_acc', 'status_cp', 'vlot', 'rtgname', 'disperse', 'reactive', 'grand_total'],
        'prdnmbr, labeljual, colorname'
    );

    $pptFailRows = getRowsByKodePeriode(
        $gg,
        'MKO_PPT_CPSTATUS_FAIL',
        $kodePeriode,
        ['labeljual', 'colorname', 'cuscolor', 'colorcode', 'cp', 'status_proses_acc', 'status_cp', 'routing', 'disperse', 'reactive', 'created_at'],
        'colorcode, cp'
    );

    $pptBedaRows = getRowsByKodePeriode(
        $gg,
        'MKO_PPT_PERBEDAAN_RESEP',
        $kodePeriode,
        ['labeljual', 'colorname', 'cuscolor', 'colorcode', 'cp', 'status_cp', 'routing', 'disperse', 'reactive', 'grand_total', 'created_at'],
        'colorcode, cp'
    );

    $rawRows = addSequenceColumn($rawRows);
    $materialRows = addSequenceColumn($materialRows);
    $materialRows = formatRowDecimals($materialRows, ['matqty', 'unit_price', 'subtotal', 'prodcf'], 4);
    $rekapRows = formatRowDecimals($rekapRows, ['disperse', 'reactive', 'grand_total'], 4);
    $pptFailRows = formatRowDecimals($pptFailRows, ['disperse', 'reactive'], 4);
    $pptBedaRows = formatRowDecimals($pptBedaRows, ['disperse', 'reactive', 'grand_total'], 4);

    $spreadsheet = new Spreadsheet();

    $rawSheet = $spreadsheet->getActiveSheet();
    $rawSheet->setTitle('mko_rawdata');
    writeSheetData(
        $rawSheet,
        ['No', 'Production HD ID', 'RTGMSID', 'prdnmbr', 'fgresult', 'rtgname', 'fgstatus', 'startdate', 'enddate', 'created_at'],
        $rawRows,
        ['no', 'productionhdid', 'rtgmsid', 'prdnmbr', 'fgresult', 'rtgname', 'fgstatus', 'startdate', 'enddate', 'created_at']
    );

    $materialSheet = $spreadsheet->createSheet();
    $materialSheet->setTitle('MKO_materialobat');
    writeSheetData(
        $materialSheet,
        ['No', 'prdnmbr', 'Prod Code', 'Prod Name', 'Label Jual', 'Cus Color', 'Color Code', 'Color Name', 'rtgname', 'Material Code', 'Material Name', 'Mat Qty', 'UOM', 'Unit Price', 'Subtotal', 'prodcf', 'kelompok', 'Vlot', 'created_at'],
        $materialRows,
        ['no', 'prdnmbr', 'prodcode', 'prodname', 'labeljual', 'cuscolor', 'colorcode', 'colorname', 'rtgname', 'material_code', 'material_name', 'matqty', 'uomcode', 'unit_price', 'subtotal', 'prodcf', 'kelompok', 'vlot', 'created_at']
    );

    $rekapSheet = $spreadsheet->createSheet();
    $rekapSheet->setTitle('MKO_rekap');
    writeSheetData(
        $rekapSheet,
        ['Label Jual', 'Color Name', 'Cus Color', 'Color Code', 'Master Resep', 'prdnmbr', 'Status Proses Acc Warna/Ulang', 'Status CP', 'Vlot', 'rtgname', 'Disperse', 'Reactive', 'Grand Total'],
        $rekapRows,
        ['labeljual', 'colorname', 'cuscolor', 'colorcode', 'master_resep', 'prdnmbr', 'status_proses_acc', 'status_cp', 'vlot', 'rtgname', 'disperse', 'reactive', 'grand_total']
    );

    $pptSheet = $spreadsheet->createSheet();
    $pptSheet->setTitle('MKO_PPT');
    writeCustomTable(
        $pptSheet,
        1,
        1,
        3,
        ['Label Jual', 'Color Name', 'Cus Color', 'Color Code', 'CP', 'Status Proses Acc Warna/Ulang', 'Status CP', 'Routing', 'Disperse', 'Reactive'],
        $pptFailRows,
        ['labeljual', 'colorname', 'cuscolor', 'colorcode', 'cp', 'status_proses_acc', 'status_cp', 'routing', 'disperse', 'reactive'],
        'CP DENGAN STATUS FAIL (SUDAH ADA MASTER RESEP)'
    );
    writeCustomTable(
        $pptSheet,
        13,
        1,
        3,
        ['Label Jual', 'Color Name', 'Cus Color', 'Color Code', 'CP', 'Status CP', 'Routing', 'Disperse', 'Reactive', 'Grand Total'],
        $pptBedaRows,
        ['labeljual', 'colorname', 'cuscolor', 'colorcode', 'cp', 'status_cp', 'routing', 'disperse', 'reactive', 'grand_total'],
        'PERBEDAAN RESEP DISPERSE & REACTIVE PADA KODE WARNA YANG SAMA'
    );
    $pptSheet->freezePane('A4');

    $spreadsheet->setActiveSheetIndex(0);

    $filename = 'MKO_ACCWARNA_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $kodePeriode) . '_' . date('Ymd_His') . '.xlsx';

    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Pragma: public');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Export gagal: ' . $e->getMessage();
    exit;
}
