<?php
session_start();
require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/report_export_data.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['UserName']) || !report_export_can_view($conn)) { http_response_code(403); exit('Forbidden'); }
$rows = report_export_rows($conn, $conn3, $_GET);
$esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$html = '<!doctype html><html><head><meta charset="utf-8"><style>
  body{font-family:Arial,sans-serif;font-size:9px;color:#111} h2{text-align:center;margin:0 0 10px}
  table{width:100%;border-collapse:collapse} th,td{border:1px solid #777;padding:4px;vertical-align:top} th{background:#eee}
  .right{text-align:right}.center{text-align:center}
</style></head><body><h2>Laporan Hasil Eksperimen</h2><table><thead><tr>
<th>No</th><th>Tgl Match</th><th>Warna</th><th>Tgl Celup Padd</th><th>Mesin Paddry</th><th>No CP</th><th>KodeLab</th><th>Qty</th><th>Posisi Hari Ini</th><th>ACC Warna R</th><th>Keputusan</th><th>Catatan QC</th>
</tr></thead><tbody>';
foreach ($rows as $i => $row) {
  $html .= '<tr><td class="center">' . ($i + 1) . '</td><td>' . $esc($row['tgl_match']) . '</td><td>' . $esc($row['warna']) . '</td><td>' . $esc($row['tgl_celup_padd']) . '</td><td>' . $esc($row['mesin_paddry']) . '</td><td>' . $esc($row['no_cp']) . '</td><td>' . $esc($row['kode_lab']) . '</td><td class="right">' . $esc($row['qty']) . '</td><td>' . $esc($row['posisi_hari_ini']) . '</td><td>' . $esc($row['acc_warna_r']) . '</td><td>' . $esc($row['keputusan']) . '</td><td>' . $esc($row['qc_catatan']) . '</td></tr>';
}
if (!$rows) $html .= '<tr><td colspan="12" class="center">Tidak ada data</td></tr>';
$html .= '</tbody></table></body></html>';
$options = new Options();
$options->set('isRemoteEnabled', false);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream('laporan_hasil_eksperimen_' . date('Ymd_His') . '.pdf', ['Attachment' => true]);
exit;
