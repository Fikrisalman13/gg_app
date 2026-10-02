<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
session_start();

require __DIR__ . '/../../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

date_default_timezone_set('Asia/Jakarta');
require __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/approval_helper.php';

if (!isset($_SESSION['UserName'])) {
    die('Silakan login terlebih dahulu.');
}

$dari = isset($_GET['dari']) && trim($_GET['dari']) !== '' ? trim($_GET['dari']) : null;
$sampai = isset($_GET['sampai']) && trim($_GET['sampai']) !== '' ? trim($_GET['sampai']) : null;
$status = isset($_GET['status']) && trim($_GET['status']) !== '' ? trim($_GET['status']) : null;
$download = isset($_GET['download']) && $_GET['download'] == '1';

$where = [];
$params = [];

// Apply User Visibility Filter
$visFilter = formUmumGetVisibilityFilter($conn, 'Izin Pulang Cepat', 'x');
if ($visFilter['where'] !== '') {
    $where[] = $visFilter['where'];
    $params = array_merge($params, $visFilter['params']);
}

if ($dari) {
    $where[] = 'CAST(x.tgl_pengajuan AS DATE) >= ?';
    $params[] = $dari;
}
if ($sampai) {
    $where[] = 'CAST(x.tgl_pengajuan AS DATE) <= ?';
    $params[] = $sampai;
}
if ($status) {
    $where[] = 'x.status_ticket = ?';
    $params[] = $status;
}
$whereSql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT x.* FROM Form_Umum_Izin_Pulang_Cepat x $whereSql ORDER BY x.tgl_pengajuan ASC, x.ticket ASC";
$stmt = count($params) ? sqlsrv_query($conn, $sql, $params) : sqlsrv_query($conn, $sql);
$rows = [];
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))
        $rows[] = $row;
    sqlsrv_free_stmt($stmt);
}

function ipcRekapFmtDate($d)
{
    if ($d instanceof DateTime)
        return $d->format('d/m/Y');
    if (is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}/', $d)) {
        $p = explode('-', substr($d, 0, 10));
        return $p[2] . '/' . $p[1] . '/' . $p[0];
    }
    if (is_string($d) && trim($d) !== '')
        return date('d/m/Y', strtotime($d));
    return '';
}
function ipcRekapFmtTime($d)
{
    if ($d instanceof DateTime)
        return $d->format('H:i');
    if (is_string($d) && preg_match('/^\d{1,2}:\d{2}/', $d))
        return substr($d, 0, 5);
    if (is_string($d) && trim($d) !== '')
        return date('H:i', strtotime($d));
    return '';
}
function ipcRekapEsc($v)
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

$flatRows = [];
foreach ($rows as $i => $r) {
    $alasan = ($r['alasan'] ?? '') === 'Lainnya' && !empty($r['alasan_lain']) ? 'Lainnya: ' . $r['alasan_lain'] : ($r['alasan'] ?? '');
    $flatRows[] = [
        'no' => $i + 1,
        'tgl_pengajuan' => ipcRekapFmtDate($r['tgl_pengajuan'] ?? ''),
        'ticket' => $r['ticket'] ?? '',
        'nama' => $r['nama_pemohon'] ?? '',
        'departemen' => $r['departemen'] ?? '',
        'bagian' => $r['bagian'] ?? '',
        'tanggal' => ipcRekapFmtDate($r['tanggal'] ?? ''),
        'normal' => ipcRekapFmtTime($r['jam_pulang_normal'] ?? ''),
        'diminta' => ipcRekapFmtTime($r['jam_pulang_diminta'] ?? ''),
        'aktual' => ipcRekapFmtTime($r['jam_keluar_real'] ?? ''),
        'alasan' => $alasan,
        'status' => $r['status_ticket'] ?? '',
        'status_keluar' => !empty($r['jam_keluar_real']) ? 'Sudah Keluar' : 'Belum Keluar',
    ];
}

$tglCetak = date('d/m/Y H:i');
$filterLabel = [];
if ($dari)
    $filterLabel[] = 'Dari: ' . ipcRekapFmtDate($dari);
if ($sampai)
    $filterLabel[] = 'Sampai: ' . ipcRekapFmtDate($sampai);
if ($status)
    $filterLabel[] = 'Status: ' . ipcRekapEsc($status);
$filterText = implode('   |   ', $filterLabel) ?: 'Semua data';

$css = 'body{font-family:Arial,sans-serif;font-size:8pt;margin:0}h2{font-size:11pt;text-align:center;margin:0 0 2px}.subtitle{font-size:8pt;text-align:center;color:#444;margin-bottom:8px}table{border-collapse:collapse;width:100%}th{background:#3c8dbc;color:#fff;font-weight:bold;font-size:7pt;padding:4px 3px;border:.5px solid #2575a0;text-align:center;vertical-align:middle}td{font-size:7pt;padding:3px;border:.5px solid #ccc;vertical-align:top}tr.even td{background:#f9f9f9}.center{text-align:center}.nodata{text-align:center;padding:20px;color:#888;font-size:9pt}';
$thead = '<th style="width:3%">No</th><th style="width:8%">Tgl Pengajuan</th><th style="width:11%">Nomor Tiket</th><th style="width:11%">Pemohon</th><th style="width:9%">Dept</th><th style="width:8%">Tanggal</th><th style="width:7%">Normal</th><th style="width:7%">Diminta</th><th style="width:7%">Aktual</th><th style="width:13%">Alasan</th><th style="width:8%">Approval</th><th style="width:8%">Status</th>';
$colCount = 12;
$tbody = '';
if (empty($flatRows)) {
    $tbody = '<tr><td colspan="' . $colCount . '" class="nodata">Tidak ada data</td></tr>';
} else {
    foreach ($flatRows as $i => $fr) {
        $tbody .= '<tr class="' . (($i % 2 === 0) ? 'even' : 'odd') . '">';
        $tbody .= '<td class="center">' . $fr['no'] . '</td>';
        $tbody .= '<td class="center">' . ipcRekapEsc($fr['tgl_pengajuan']) . '</td>';
        $tbody .= '<td>' . ipcRekapEsc($fr['ticket']) . '</td>';
        $tbody .= '<td>' . ipcRekapEsc($fr['nama']) . '</td>';
        $tbody .= '<td>' . ipcRekapEsc($fr['departemen']) . '</td>';
        $tbody .= '<td class="center">' . ipcRekapEsc($fr['tanggal']) . '</td>';
        $tbody .= '<td class="center">' . ipcRekapEsc($fr['normal']) . '</td>';
        $tbody .= '<td class="center">' . ipcRekapEsc($fr['diminta']) . '</td>';
        $tbody .= '<td class="center">' . ipcRekapEsc($fr['aktual'] ?: '-') . '</td>';
        $tbody .= '<td>' . ipcRekapEsc($fr['alasan']) . '</td>';
        $tbody .= '<td class="center">' . ipcRekapEsc($fr['status']) . '</td>';
        $tbody .= '<td class="center">' . ipcRekapEsc($fr['status_keluar']) . '</td>';
        $tbody .= '</tr>';
    }
}

$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . $css . '</style></head><body><h2>REKAP FORM IZIN PULANG CEPAT</h2><p class="subtitle">Filter: ' . $filterText . '&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;Dicetak: ' . $tglCetak . '</p><table><thead><tr>' . $thead . '</tr></thead><tbody>' . $tbody . '</tbody></table></body></html>';

$options = new Options();
$options->set('defaultFont', 'Arial');
$options->set('isRemoteEnabled', false);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$canvas = $dompdf->getCanvas();
$canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
    $font = $fontMetrics->getFont('Arial');
    $text = 'Halaman ' . $pageNumber . ' dari ' . $pageCount;
    $tw = $fontMetrics->getTextWidth($text, $font, 7);
    $canvas->text($canvas->get_width() - $tw - 15, $canvas->get_height() - 20, $text, $font, 7, [0.4, 0.4, 0.4]);
});
$dompdf->render();
$pdf = $dompdf->output();
ob_end_clean();
$filename = 'Rekap_Izin_Pulang_Cepat_' . date('Ymd_His') . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
exit;
