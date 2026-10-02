<?php
session_start();
require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/report_export_data.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_SESSION['UserName']) || !report_export_can_view($conn)) { http_response_code(403); exit('Forbidden'); }
$rows = report_export_rows($conn, $conn3, $_GET);
$headers = ['No','Tgl Match','Warna','Tgl Celup Padd','Mesin Paddry','No CP','KodeLab','Qty','Posisi Hari Ini','ACC Warna R','Keputusan','Catatan QC'];
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Laporan Eksperimen');
$toCell = static function (int $col, int $row): string {
  $name = '';
  while ($col > 0) {
    $col--;
    $name = chr(65 + ($col % 26)) . $name;
    $col = intdiv($col, 26);
  }
  return $name . $row;
};
foreach ($headers as $i => $h) $sheet->setCellValue($toCell($i + 1, 1), $h);
$r = 2;
foreach ($rows as $idx => $row) {
  $vals = [$idx + 1, $row['tgl_match'], $row['warna'], $row['tgl_celup_padd'], $row['mesin_paddry'], $row['no_cp'], $row['kode_lab'], $row['qty'], $row['posisi_hari_ini'], $row['acc_warna_r'], $row['keputusan'], $row['qc_catatan']];
  foreach ($vals as $i => $v) $sheet->setCellValue($toCell($i + 1, $r), $v);
  $r++;
}
foreach (range(1, count($headers)) as $col) $sheet->getColumnDimension($toCell($col, 1)[0])->setAutoSize(true);
$sheet->getStyle('A1:L1')->getFont()->setBold(true);
$sheet->freezePane('A2');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="laporan_hasil_eksperimen_' . date('Ymd_His') . '.xlsx"');
header('Cache-Control: max-age=0');
(new Xlsx($spreadsheet))->save('php://output');
exit;
