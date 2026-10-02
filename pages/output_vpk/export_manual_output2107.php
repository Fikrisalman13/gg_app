<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
if (!isset($_SESSION['UserName'])) {
    die('Silakan login terlebih dahulu!');
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$q = trim((string)($_GET['q'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? '')) ?: date('Y-m-01');
$dateTo = trim((string)($_GET['date_to'] ?? '')) ?: date('Y-m-d');

function excel_dt($value): string
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('d-m-Y H:i:s');
    }
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    $timestamp = strtotime($value);
    return $timestamp ? date('d-m-Y H:i:s', $timestamp) : $value;
}

$rows = [];
$histories = [];
if (!empty($conn)) {
    $filter = "CAST(o.created_at AS date) BETWEEN ? AND ?
               AND (? = '' OR o.prdnmbr LIKE ? OR o.iso LIKE ? OR o.partai LIKE ? OR o.cuscolor LIKE ? OR o.labeljual LIKE ? OR o.op_mesin LIKE ? OR o.ket LIKE ?)";
    $like = '%' . $q . '%';
    $params = [$dateFrom, $dateTo, $q, $like, $like, $like, $like, $like, $like, $like];

    $sql = "SELECT o.id, o.prdnmbr, o.iso, o.partai, o.cuscolor, o.meter, o.gol, o.grey, o.tengah, o.start_time, o.finish_time, o.stdcutfg, o.op_mesin, o.labeljual, o.ket, o.created_by
            FROM manual_output o
            WHERE $filter
            ORDER BY o.id DESC";
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $rows[] = $row;
        sqlsrv_free_stmt($stmt);
    }

    $historySql = "SELECT o.prdnmbr, o.iso, o.partai, s.operator, m.nama_mesin, s.meter, s.gol, s.grey, s.tengah,
                          s.start_time, CASE WHEN s.finish_time IS NULL AND s.handover_from IS NULL THEN o.finish_time ELSE s.finish_time END finish_time,
                          CASE WHEN s.status_active=1 THEN 'Aktif' ELSE 'Selesai' END status_shift, s.handover_from, s.ket
                   FROM manual_output_shift s
                   JOIN manual_output o ON o.id=s.manual_output_id
                   LEFT JOIN manual_output_master_mesin m ON m.id=s.mesin_id
                   WHERE $filter
                   ORDER BY o.id DESC, s.id";
    $historyStmt = sqlsrv_query($conn, $historySql, $params);
    if ($historyStmt !== false) {
        while ($row = sqlsrv_fetch_array($historyStmt, SQLSRV_FETCH_ASSOC)) $histories[] = $row;
        sqlsrv_free_stmt($historyStmt);
    }
}

$spreadsheet = new Spreadsheet();
$mainSheet = $spreadsheet->getActiveSheet();
$mainSheet->setTitle('Output Manual');
$mainHeaders = ['No','PRDNMBR','ISO','Partai','Warna','','','','','Start','Finish','STD Potong','OP Mesin','L Jual','Ket','Created By'];
$mainSheet->fromArray($mainHeaders, null, 'A1');
$mainSheet->setCellValue('F1', 'Quantity')->mergeCells('F1:G1');
$mainSheet->setCellValue('H1', 'Sambungan')->mergeCells('H1:I1');
$mainSheet->fromArray(['Meter','Gol','Grey','Tengah'], null, 'F2');
foreach (['A','B','C','D','E','J','K','L','M','N','O','P'] as $column) $mainSheet->mergeCells($column . '1:' . $column . '2');
$excelRow = 3;
foreach ($rows as $index => $row) {
    $mainSheet->fromArray([
        $index + 1,$row['prdnmbr'],$row['iso'],$row['partai'],$row['cuscolor'],$row['meter'],$row['gol'],$row['grey'],$row['tengah'],
        excel_dt($row['start_time']),excel_dt($row['finish_time']),$row['stdcutfg'],$row['op_mesin'],$row['labeljual'],$row['ket'],$row['created_by'],
    ], null, 'A' . $excelRow++);
}

$historySheet = $spreadsheet->createSheet();
$historySheet->setTitle('Histori Shift');
$historyHeaders = ['No','PRDNMBR','ISO','Partai','Operator','Mesin','','','','','Start','Finish','Status','Handover Dari','Ket'];
$historySheet->fromArray($historyHeaders, null, 'A1');
$historySheet->setCellValue('G1', 'Quantity')->mergeCells('G1:H1');
$historySheet->setCellValue('I1', 'Sambungan')->mergeCells('I1:J1');
$historySheet->fromArray(['Meter','Gol','Grey','Tengah'], null, 'G2');
foreach (['A','B','C','D','E','F','K','L','M','N','O'] as $column) $historySheet->mergeCells($column . '1:' . $column . '2');
$excelRow = 3;
foreach ($histories as $index => $row) {
    $historySheet->fromArray([
        $index + 1,$row['prdnmbr'],$row['iso'],$row['partai'],$row['operator'],$row['nama_mesin'],$row['meter'],$row['gol'],$row['grey'],$row['tengah'],
        excel_dt($row['start_time']),excel_dt($row['finish_time']),$row['status_shift'],$row['handover_from'],$row['ket'],
    ], null, 'A' . $excelRow++);
}

foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
    $lastColumn = $sheet->getHighestColumn();
    $sheet->getStyle('A1:' . $lastColumn . '2')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF5B9BD5']],
        'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
    ]);
    $sheet->getStyle('A1:' . $lastColumn . $sheet->getHighestRow())->getBorders()->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF808080');
    $sheet->freezePane('A3');
    $sheet->setAutoFilter('A2:' . $lastColumn . $sheet->getHighestRow());
    foreach (range('A', $lastColumn) as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
}

$spreadsheet->setActiveSheetIndex(0);
while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Output_Manual_' . date('Ymd_His') . '.xlsx"');
header('Cache-Control: max-age=0');
(new Xlsx($spreadsheet))->save('php://output');
exit;


