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
$dateFrom = trim((string)($_GET['date_from'] ?? '')) ?: date('Y-m-01\T00:00');
$dateTo = trim((string)($_GET['date_to'] ?? '')) ?: date('Y-m-d\TH:i');
$dateFromSql = DateTime::createFromFormat('Y-m-d\TH:i', $dateFrom);
$dateToSql = DateTime::createFromFormat('Y-m-d\TH:i', $dateTo);
if (!$dateFromSql || !$dateToSql || $dateFromSql > $dateToSql) {
    $dateFromSql = DateTime::createFromFormat('Y-m-d\TH:i', date('Y-m-01\T00:00'));
    $dateToSql = DateTime::createFromFormat('Y-m-d\TH:i', date('Y-m-d\TH:i'));
}
$dateFromValue = $dateFromSql->format('Y-m-d H:i:s');
$dateToValue = $dateToSql->format('Y-m-d H:i:s');
$statusFilter = (string)($_GET['status'] ?? '');
if (!in_array($statusFilter, ['', 'complete', 'running', 'draft'], true)) $statusFilter = '';
$machineFilter = max(0, (int)($_GET['machine_id'] ?? 0));
$colorFilter = trim((string)($_GET['color'] ?? ''));

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

function excel_text($value): string
{
    $value = (string)($value ?? '');
    return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
}

$rows = [];
$histories = [];
if (!empty($conn)) {
    $where = ['o.is_cancelled=0', 'CASE WHEN o.status_draft=1 THEN o.start_time ELSE COALESCE(times.first_start,o.start_time) END BETWEEN ? AND ?'];
    $params = [$dateFromValue, $dateToValue];
    if ($statusFilter === 'complete') $where[] = 'o.status_draft=0 AND mo.prdqty IS NOT NULL AND o.meter>=mo.prdqty-0.00001';
    if ($statusFilter === 'running') $where[] = 'o.status_draft=0 AND (mo.prdqty IS NULL OR o.meter<mo.prdqty-0.00001)';
    if ($statusFilter === 'draft') $where[] = 'o.status_draft=1';
    if ($machineFilter > 0) { $where[] = 'o.mesin_id=?'; $params[] = $machineFilter; }
    if ($colorFilter !== '') { $where[] = 'o.cuscolor=?'; $params[] = $colorFilter; }
    if ($q !== '') { $where[] = '(o.prdnmbr LIKE ? OR o.iso LIKE ? OR o.partai LIKE ? OR o.cuscolor LIKE ? OR o.labeljual LIKE ? OR o.op_mesin LIKE ? OR o.ket LIKE ?)'; $like='%'.$q.'%'; array_push($params,$like,$like,$like,$like,$like,$like,$like); }
    $filter = implode(' AND ', $where);
    $timesApply = "OUTER APPLY (SELECT (SELECT TOP 1 ss.start_time FROM manual_output_shift ss WHERE ss.manual_output_id=o.id ORDER BY ss.id ASC) first_start) times";
    $completedApply = "OUTER APPLY (SELECT CASE WHEN o.status_draft=0 AND mo.prdqty IS NOT NULL AND o.meter>=mo.prdqty-0.00001 THEN COALESCE((SELECT TOP 1 sf.finish_time FROM manual_output_shift sf WHERE sf.manual_output_id=o.id ORDER BY sf.id DESC),o.finish_time) END finish_time) completed";

    $sql = "SELECT o.id,o.prdnmbr,o.iso,o.partai,o.cuscolor,o.meter,o.gol,o.grey,o.tengah,o.start_time,completed.finish_time,o.stdcutfg,o.op_mesin,o.labeljual,o.ket,o.created_by
             FROM manual_output o LEFT JOIN manual_output_master mo ON mo.prdnmbr=o.prdnmbr $timesApply $completedApply
             WHERE $filter ORDER BY o.start_time DESC,o.id DESC";
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt !== false) { while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $rows[] = $row; sqlsrv_free_stmt($stmt); }

    $historySql = "SELECT o.prdnmbr,o.iso,o.partai,s.operator,m.nama_mesin,s.meter,s.gol,s.grey,s.tengah,s.start_time,CASE WHEN s.finish_time IS NULL AND s.handover_from IS NULL THEN completed.finish_time ELSE s.finish_time END finish_time,
                          CASE WHEN s.is_cancelled=1 THEN 'Dibatalkan' WHEN s.is_correction_pending=1 THEN 'Menunggu Koreksi' WHEN s.status_active=1 THEN 'Aktif' ELSE 'Selesai' END status_shift,s.handover_from,s.ket
                   FROM manual_output_shift s JOIN manual_output o ON o.id=s.manual_output_id LEFT JOIN manual_output_master mo ON mo.prdnmbr=o.prdnmbr LEFT JOIN manual_output_master_mesin m ON m.id=s.mesin_id $timesApply $completedApply
                   WHERE $filter ORDER BY o.start_time DESC,o.id DESC,s.id";
    $historyStmt = sqlsrv_query($conn, $historySql, $params);
    if ($historyStmt !== false) { while ($row = sqlsrv_fetch_array($historyStmt, SQLSRV_FETCH_ASSOC)) $histories[] = $row; sqlsrv_free_stmt($historyStmt); }
}

$spreadsheet = new Spreadsheet();
$mainSheet = $spreadsheet->getActiveSheet();
$mainSheet->setTitle('Output Manual');
$mainHeaders = ['No','No CP','ISO','Partai','Warna','','','','','Start','Finish','Status','STD Potong','OP Mesin','L Jual','Ket','Created By'];
$mainSheet->fromArray($mainHeaders, null, 'A1');
$mainSheet->setCellValue('F1', 'Quantity')->mergeCells('F1:G1');
$mainSheet->setCellValue('H1', 'Sambungan')->mergeCells('H1:I1');
$mainSheet->fromArray(['Meter','Gol','Grey','Tengah'], null, 'F2');
foreach (['A','B','C','D','E','J','K','L','M','N','O','P','Q'] as $column) $mainSheet->mergeCells($column . '1:' . $column . '2');
$excelRow = 3;
foreach ($rows as $index => $row) {
    $mainSheet->fromArray([
        $index + 1,excel_text($row['prdnmbr']),excel_text($row['iso']),excel_text($row['partai']),excel_text($row['cuscolor']),$row['meter'],$row['gol'],$row['grey'],$row['tengah'],
        excel_dt($row['start_time']),excel_dt($row['finish_time']),!empty($row['status_draft']) ? 'Belum Berjalan' : (!empty($row['finish_time']) ? 'Selesai' : 'Berjalan'),$row['stdcutfg'],excel_text($row['op_mesin']),excel_text($row['labeljual']),excel_text($row['ket']),excel_text($row['created_by']),
    ], null, 'A' . $excelRow++);
}

$totalMeter = array_sum(array_map(static fn(array $row): float => (float)($row['meter'] ?? 0), $rows));
$mainSheet->setCellValue('E' . $excelRow, 'Total Meter');
$mainSheet->setCellValue('F' . $excelRow, $totalMeter);
$mainSheet->getStyle('E' . $excelRow . ':F' . $excelRow)->getFont()->setBold(true);

$historySheet = $spreadsheet->createSheet();
$historySheet->setTitle('Histori Shift');
$historyHeaders = ['No','No CP','ISO','Partai','Operator','Mesin','','','','','Start','Finish','Status','Handover Dari','Ket'];
$historySheet->fromArray($historyHeaders, null, 'A1');
$historySheet->setCellValue('G1', 'Quantity')->mergeCells('G1:H1');
$historySheet->setCellValue('I1', 'Sambungan')->mergeCells('I1:J1');
$historySheet->fromArray(['Meter','Gol','Grey','Tengah'], null, 'G2');
foreach (['A','B','C','D','E','F','K','L','M','N','O'] as $column) $historySheet->mergeCells($column . '1:' . $column . '2');
$excelRow = 3;
foreach ($histories as $index => $row) {
    $historySheet->fromArray([
        $index + 1,excel_text($row['prdnmbr']),excel_text($row['iso']),excel_text($row['partai']),excel_text($row['operator']),excel_text($row['nama_mesin']),$row['meter'],$row['gol'],$row['grey'],$row['tengah'],
        excel_dt($row['start_time']),excel_dt($row['finish_time']),excel_text($row['status_shift']),excel_text($row['handover_from']),excel_text($row['ket']),
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


