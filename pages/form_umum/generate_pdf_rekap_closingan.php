<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
session_start();

require '../../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;
// Set timezone
date_default_timezone_set('Asia/Jakarta');
require '../../koneksi.php';
require_once __DIR__ . '/items_parser.php';
require_once __DIR__ . '/approval_helper.php';

if (!isset($_SESSION['UserName'])) {
    die('Silakan login terlebih dahulu.');
}

// ─── Parameter filter ─────────────────────────────────────────────────────────
$dari       = isset($_GET['dari'])    && trim($_GET['dari'])    !== '' ? trim($_GET['dari'])    : null;
$sampai     = isset($_GET['sampai'])  && trim($_GET['sampai'])  !== '' ? trim($_GET['sampai'])  : null;
$status     = isset($_GET['status'])  && trim($_GET['status'])  !== '' ? trim($_GET['status'])  : null;
$filterGudang    = isset($_GET['gudang'])    && $_GET['gudang']    === '1';
$filterTransaksi = isset($_GET['transaksi']) && $_GET['transaksi'] === '1';
$download = isset($_GET['download']) && $_GET['download'] == '1';

// Mode kolom berdasarkan filter closingan
// 'full'      = semua kolom (default, tanpa filter closingan)
// 'gudang'    = hanya Closingan Gudang (tanpa kolom transaksi/supp/no trans)
// 'transaksi' = hanya Closingan Transaksi (tanpa kolom gudang)
if ($filterGudang && !$filterTransaksi) {
    $colMode = 'gudang';
} elseif ($filterTransaksi && !$filterGudang) {
    $colMode = 'transaksi';
} else {
    $colMode = 'full';
}

// ─── Query ────────────────────────────────────────────────────────────────────
$where  = [];
$params = [];

// Apply User Visibility Filter
$visFilter = formUmumGetVisibilityFilter($conn, 'Buka Tanggal Closingan', 'x');
if ($visFilter['where'] !== '') {
    $where[] = $visFilter['where'];
    $params = array_merge($params, $visFilter['params']);
}

if ($dari) {
    $where[]  = 'CAST(x.tgl_pengajuan AS DATE) >= ?';
    $params[] = $dari;
}
if ($sampai) {
    $where[]  = 'CAST(x.tgl_pengajuan AS DATE) <= ?';
    $params[] = $sampai;
}
if ($status) {
    $where[]  = 'x.status_ticket = ?';
    $params[] = $status;
}

// Filter berdasarkan jenis request (gudang/transaksi)
// "Closingan Gudang" = hanya tiket yang request_gudang=1 AND request_transaksi=0
// "Closingan Transaksi" = hanya tiket yang request_transaksi=1 AND request_gudang=0
if ($filterGudang && !$filterTransaksi) {
    // Hanya tiket gudang saja
    $where[] = "(x.request_gudang = 1 AND x.request_transaksi = 0)";
} elseif ($filterTransaksi && !$filterGudang) {
    // Hanya tiket transaksi saja
    $where[] = "(x.request_transaksi = 1 AND x.request_gudang = 0)";
}

$whereSql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';
$sql = "SELECT x.ticket, x.tgl_pengajuan, x.nama_pemohon, x.departemen, x.gudang_transaksi, x.status_ticket, x.closed_at, x.unclosing_at
        FROM Form_Umum_Buka_Tanggal_Closingan x
        $whereSql
        ORDER BY x.tgl_pengajuan ASC, x.ticket ASC";

$stmt = count($params) ? sqlsrv_query($conn, $sql, $params) : sqlsrv_query($conn, $sql);

$rows = [];
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

// ─── Helper: format tanggal ───────────────────────────────────────────────────
function rekapFmtDate($d)
{
    if ($d instanceof DateTime) return $d->format('d/m/Y');
    if (is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}/', $d)) {
        $p = explode('-', substr($d, 0, 10));
        return $p[2] . '/' . $p[1] . '/' . $p[0];
    }
    if (is_string($d) && !empty(trim($d))) return date('d/m/Y', strtotime($d));
    return '';
}

function rekapFmtDateTime($d)
{
    if ($d instanceof DateTime) return $d->format('d/m/Y H:i');
    if (is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}/', $d)) {
        return date('d/m/Y H:i', strtotime($d));
    }
    if (is_string($d) && !empty(trim($d))) return date('d/m/Y H:i', strtotime($d));
    return '';
}

function rekapEsc($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

// ─── Bangun baris flatten ─────────────────────────────────────────────────────
// Setiap item (dari parseItemsFromGudangTransaksi) jadi 1 baris tersendiri.
// Kolom No / Tgl Pengajuan / Nomor Pengajuan / Pemohon / Dept diulang.

$flatRows = [];
foreach ($rows as $r) {
    $items = parseItemsFromGudangTransaksi($r['gudang_transaksi'] ?? '');
    if (empty($items)) {
        $items = [['buka_tgl'=>'','request_gudang'=>0,'request_transaksi'=>0,'gudang'=>'','jenis_transaksi'=>'','transaksi'=>'','nomor_transaksi'=>'','vendor_cust'=>'','keterangan'=>'']];
    }

    foreach ($items as $item) {
        // Filter item berdasarkan colMode (strict: gudang saja atau transaksi saja)
        if ($colMode === 'gudang') {
            // Hanya item yang gudang saja (request_gudang=1 AND request_transaksi=0)
            if (empty($item['request_gudang']) || !empty($item['request_transaksi'])) continue;
        }
        if ($colMode === 'transaksi') {
            // Hanya item yang transaksi saja (request_transaksi=1 AND request_gudang=0)
            if (empty($item['request_transaksi']) || !empty($item['request_gudang'])) continue;
        }

        $flatRows[] = [
            'no_counter'      => 0,
            'tgl_pengajuan'   => rekapFmtDate($r['tgl_pengajuan']),
            'ticket'          => $r['ticket'],
            'nama_pemohon'    => $r['nama_pemohon'],
            'departemen'      => $r['departemen'],
            'keterangan'      => $item['keterangan'] ?? '',
            'tgl_buka'        => rekapFmtDate($item['buka_tgl'] ?? ''),
            'gudang'          => $item['gudang'] ?? '',
            'transaksi'       => ($item['jenis_transaksi'] ?? '') !== ''
                                    ? trim(($item['jenis_transaksi'] ?? '') . ' - ' . ($item['transaksi'] ?? ''))
                                    : ($item['transaksi'] ?? ''),
            'vendor_cust'     => $item['vendor_cust'] ?? '',
            'nomor_transaksi' => $item['nomor_transaksi'] ?? '',
            'tgl_mulai'       => rekapFmtDateTime($r['unclosing_at']),
            'tgl_selesai'     => rekapFmtDateTime($r['closed_at']),
        ];
    }
}

// Nomor urut setelah flatten
foreach ($flatRows as $i => &$fr) {
    $fr['no_counter'] = $i + 1;
}
unset($fr);

// ─── Header info ──────────────────────────────────────────────────────────────
$tglCetak    = date('d/m/Y H:i');
$filterLabel = [];
if ($dari)   $filterLabel[] = 'Dari: ' . rekapFmtDate($dari);
if ($sampai) $filterLabel[] = 'Sampai: ' . rekapFmtDate($sampai);
if ($status) $filterLabel[] = 'Status: ' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8');
if ($filterGudang && !$filterTransaksi)  $filterLabel[] = 'Filter: Closingan Gudang';
if ($filterTransaksi && !$filterGudang)  $filterLabel[] = 'Filter: Closingan Transaksi';
if ($filterGudang && $filterTransaksi)   $filterLabel[] = 'Filter: Closingan Gudang & Transaksi';
$filterText = implode('   |   ', $filterLabel) ?: 'Semua data';

// ─── CSS ──────────────────────────────────────────────────────────────────────
$css = '
body { font-family: Arial, sans-serif; font-size: 8pt; margin: 0; padding: 0; }
h2 { font-size: 11pt; text-align: center; margin: 0 0 2px 0; }
.subtitle { font-size: 8pt; text-align: center; color: #444; margin-bottom: 8px; }
table { border-collapse: collapse; width: 100%; }
th {
    background-color: #3c8dbc;
    color: #fff;
    font-weight: bold;
    font-size: 7.5pt;
    padding: 4px 3px;
    border: 0.5px solid #2575a0;
    text-align: center;
    vertical-align: middle;
}
td {
    font-size: 7.5pt;
    padding: 3px 3px;
    border: 0.5px solid #ccc;
    vertical-align: top;
}
tr.even td  { background-color: #f9f9f9; }
tr.odd  td  { background-color: #ffffff; }
.center { text-align: center; }
.nodata { text-align: center; padding: 20px; color: #888; font-size: 9pt; }
';

// ─── Definisi kolom berdasarkan colMode ──────────────────────────────────────
if ($colMode === 'gudang') {
    // Kolom: No | Tgl Pengajuan | Nomor Pengajuan | Pemohon | Dept | Deskripsi | Tgl Buka | Gudang | Tgl Mulai | Tgl Selesai
    $colCount = 10;
    $theadHtml = '
    <th style="width:3%">No</th>
    <th style="width:8%">Tgl Pengajuan</th>
    <th style="width:11%">Nomor Pengajuan</th>
    <th style="width:11%">Pemohon</th>
    <th style="width:8%">Dept</th>
    <th style="width:16%">Deskripsi</th>
    <th style="width:7%">Tgl Buka</th>
    <th style="width:16%">Gudang</th>
    <th style="width:10%">Tgl Mulai</th>
    <th style="width:10%">Tgl Selesai</th>';
} elseif ($colMode === 'transaksi') {
    // Kolom: No | Tgl Pengajuan | Nomor Pengajuan | Pemohon | Dept | Deskripsi | Tgl Buka | Transaksi | Supp/Cust | No Trans | Tgl Mulai | Tgl Selesai
    $colCount = 12;
    $theadHtml = '
    <th style="width:3%">No</th>
    <th style="width:7%">Tgl Pengajuan</th>
    <th style="width:10%">Nomor Pengajuan</th>
    <th style="width:10%">Pemohon</th>
    <th style="width:6%">Dept</th>
    <th style="width:12%">Deskripsi</th>
    <th style="width:6%">Tgl Buka</th>
    <th style="width:10%">Transaksi</th>
    <th style="width:9%">Supp/Cust</th>
    <th style="width:9%">No Trans</th>
    <th style="width:9%">Tgl Mulai</th>
    <th style="width:9%">Tgl Selesai</th>';
} else {
    // Full: semua 13 kolom (mode default)
    $colCount = 13;
    $theadHtml = '
    <th style="width:3%">No</th>
    <th style="width:7%">Tgl Pengajuan</th>
    <th style="width:10%">Nomor Pengajuan</th>
    <th style="width:10%">Pemohon</th>
    <th style="width:7%">Dept</th>
    <th style="width:11%">Deskripsi</th>
    <th style="width:6%">Tgl Buka</th>
    <th style="width:8%">Gudang</th>
    <th style="width:9%">Transaksi</th>
    <th style="width:7%">Supp/Cust</th>
    <th style="width:8%">No Trans</th>
    <th style="width:8%">Tgl Mulai</th>
    <th style="width:8%">Tgl Selesai</th>';
}

// ─── Tabel baris ─────────────────────────────────────────────────────────────
$tbody = '';
if (empty($flatRows)) {
    $tbody = '<tr><td colspan="' . $colCount . '" class="nodata">Tidak ada data</td></tr>';
} else {
    foreach ($flatRows as $i => $fr) {
        $rowClass = ($i % 2 === 0) ? 'even' : 'odd';
        $tbody .= '<tr class="' . $rowClass . '">';
        $tbody .= '<td class="center">' . $fr['no_counter']                    . '</td>';
        $tbody .= '<td class="center">' . rekapEsc($fr['tgl_pengajuan'])        . '</td>';
        $tbody .= '<td>'               . rekapEsc($fr['ticket'])                . '</td>';
        $tbody .= '<td>'               . rekapEsc($fr['nama_pemohon'])          . '</td>';
        $tbody .= '<td>'               . rekapEsc($fr['departemen'])            . '</td>';
        $tbody .= '<td>'               . rekapEsc($fr['keterangan'])            . '</td>';
        $tbody .= '<td class="center">' . rekapEsc($fr['tgl_buka'])            . '</td>';

        if ($colMode === 'gudang') {
            $tbody .= '<td>'               . rekapEsc($fr['gudang'])            . '</td>';
        } elseif ($colMode === 'transaksi') {
            $tbody .= '<td>'               . rekapEsc($fr['transaksi'])         . '</td>';
            $tbody .= '<td>'               . rekapEsc($fr['vendor_cust'])       . '</td>';
            $tbody .= '<td>'               . rekapEsc($fr['nomor_transaksi'])   . '</td>';
        } else {
            // full
            $tbody .= '<td>'               . rekapEsc($fr['gudang'])            . '</td>';
            $tbody .= '<td>'               . rekapEsc($fr['transaksi'])         . '</td>';
            $tbody .= '<td>'               . rekapEsc($fr['vendor_cust'])       . '</td>';
            $tbody .= '<td>'               . rekapEsc($fr['nomor_transaksi'])   . '</td>';
        }

        $tbody .= '<td class="center">' . rekapEsc($fr['tgl_mulai'])            . '</td>';
        $tbody .= '<td class="center">' . rekapEsc($fr['tgl_selesai'])          . '</td>';
        $tbody .= '</tr>';
    }
}

// ─── Judul PDF berdasarkan colMode ────────────────────────────────────────────
if ($colMode === 'gudang') {
    $pdfTitle = 'REKAP FORM BUKA TANGGAL CLOSINGAN - GUDANG';
} elseif ($colMode === 'transaksi') {
    $pdfTitle = 'REKAP FORM BUKA TANGGAL CLOSINGAN - TRANSAKSI';
} else {
    $pdfTitle = 'REKAP FORM BUKA TANGGAL CLOSINGAN';
}

// ─── HTML lengkap ─────────────────────────────────────────────────────────────
$html = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>' . $css . '</style>
</head>
<body>
<h2>' . $pdfTitle . '</h2>
<p class="subtitle">
    Filter: ' . $filterText . '&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;Dicetak: ' . $tglCetak . '
</p>
<table>
<thead>
<tr>' . $theadHtml . '</tr>
</thead>
<tbody>
' . $tbody . '
</tbody>
</table>
</body>
</html>';

// ─── Generate PDF via Dompdf ──────────────────────────────────────────────────
$options = new Options();
$options->set('defaultFont', 'Arial');
$options->set('isRemoteEnabled', false);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');

// Footer: nomor halaman
$dompdf->render();

// Inject page numbers via canvas script
$canvas = $dompdf->getCanvas();
$canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
    $font = $fontMetrics->getFont('Arial');
    $size = 7;
    $text = 'Halaman ' . $pageNumber . ' dari ' . $pageCount;
    $width  = $canvas->get_width();
    $height = $canvas->get_height();
    $tw = $fontMetrics->getTextWidth($text, $font, $size);
    $canvas->text($width - $tw - 15, $height - 20, $text, $font, $size, [0.4, 0.4, 0.4]);
});

$dompdf->render();

$pdf = $dompdf->output();
ob_end_clean();

$filename = 'Rekap_Buka_Tanggal_Closingan_' . date('Ymd_His') . '.pdf';

if ($download) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

// Inline preview
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
exit;
