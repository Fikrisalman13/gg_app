<?php

session_start();
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/functions.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

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

    $spreadsheet = new Spreadsheet();

    $rawSheet = $spreadsheet->getActiveSheet();
    $rawSheet->setTitle('mko_rawdata');
    writeSheetData(
        $rawSheet,
        ['ID', 'Production HD ID', 'RTGMSID', 'prdnmbr', 'fgresult', 'rtgname', 'fgstatus', 'startdate', 'enddate', 'created_at'],
        $rawRows,
        ['id', 'productionhdid', 'rtgmsid', 'prdnmbr', 'fgresult', 'rtgname', 'fgstatus', 'startdate', 'enddate', 'created_at']
    );

    $materialSheet = $spreadsheet->createSheet();
    $materialSheet->setTitle('MKO_materialobat');
    writeSheetData(
        $materialSheet,
        ['ID', 'prdnmbr', 'Prod Code', 'Prod Name', 'Label Jual', 'Cus Color', 'Color Code', 'Color Name', 'rtgname', 'Material Code', 'Material Name', 'Mat Qty', 'UOM', 'Unit Price', 'Subtotal', 'prodcf', 'kelompok', 'Vlot', 'created_at'],
        $materialRows,
        ['id', 'prdnmbr', 'prodcode', 'prodname', 'labeljual', 'cuscolor', 'colorcode', 'colorname', 'rtgname', 'material_code', 'material_name', 'matqty', 'uomcode', 'unit_price', 'subtotal', 'prodcf', 'kelompok', 'vlot', 'created_at']
    );

    $rekapSheet = $spreadsheet->createSheet();
    $rekapSheet->setTitle('MKO_rekap');
    writeSheetData(
        $rekapSheet,
        ['Label Jual', 'Color Name', 'Cus Color', 'Color Code', 'Master Resep', 'prdnmbr', 'Status Proses Acc Warna/Ulang', 'Status CP', 'Vlot', 'rtgname', 'Disperse', 'Reactive', 'Grand Total'],
        $rekapRows,
        ['labeljual', 'colorname', 'cuscolor', 'colorcode', 'master_resep', 'prdnmbr', 'status_proses_acc', 'status_cp', 'vlot', 'rtgname', 'disperse', 'reactive', 'grand_total']
    );

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
