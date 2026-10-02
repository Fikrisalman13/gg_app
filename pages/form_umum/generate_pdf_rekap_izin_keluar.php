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

// ─── Parameter filter ─────────────────────────────────────────────────────────
$dari       = isset($_GET['dari'])    && trim($_GET['dari'])    !== '' ? trim($_GET['dari'])    : null;
$sampai     = isset($_GET['sampai'])  && trim($_GET['sampai'])  !== '' ? trim($_GET['sampai'])  : null;
$status     = isset($_GET['status'])  && trim($_GET['status'])  !== '' ? trim($_GET['status'])  : null;
$download   = isset($_GET['download']) && $_GET['download'] == '1';

// ─── Query ────────────────────────────────────────────────────────────────────
$where  = [];
$params = [];

// Apply User Visibility Filter
$visFilter = formUmumGetVisibilityFilter($conn, 'Izin Keluar Pabrik', 'x');
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

$whereSql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';
$sql = "SELECT x.*
        FROM Form_Umum_Izin_Keluar_Pabrik x
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

function rekapFmtTime($d)
{
    if ($d instanceof DateTime) return $d->format('H:i');
    if (is_string($d) && preg_match('/^\d{2}:\d{2}/', $d)) {
        return substr($d, 0, 5);
    }
    if (is_string($d) && !empty(trim($d))) return date('H:i', strtotime($d));
    return '';
}

function rekapEsc($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

// ─── Hitung status kembali ───────────────────────────────────────────────────
function calcStatusKembali($row)
{
    $jamKeluarReal = $row['jam_keluar_real'] ?? null;
    $jamKembaliReal = $row['jam_kembali_real'] ?? $row['jam_kembali'] ?? null;
    $estimasiKembali = $row['estimasi_kembali'] ?? $row['jam_keluar_sampai'] ?? null;

    if (empty($jamKeluarReal)) {
        return 'Belum Keluar';
    }
    if (!empty($jamKeluarReal) && empty($jamKembaliReal)) {
        return 'Sedang Keluar';
    }
    // jam keluar real & jam kembali real exist
    if ($estimasiKembali) {
        $tglKeluar = $row['tgl_keluar'] ?? $row['tgl_pengajuan'] ?? date('Y-m-d');
        if ($tglKeluar instanceof DateTime) {
            $estimasiDateTime = clone $tglKeluar;
        } else {
            $estimasiDateTime = new DateTime((string)$tglKeluar);
        }
        $timeParts = explode(':', $estimasiKembali);
        if (count($timeParts) >= 2) {
            $estimasiDateTime->setTime((int)$timeParts[0], (int)$timeParts[1], (int)($timeParts[2] ?? 0));
        }
        $kembaliTime = ($jamKembaliReal instanceof DateTime) ? $jamKembaliReal : new DateTime((string)$jamKembaliReal);
        if ($kembaliTime > $estimasiDateTime) {
            return 'Terlambat';
        }
    }
    return 'Sudah Kembali';
}

$flatRows = [];
foreach ($rows as $r) {
    $flatRows[] = [
        'no_counter'      => 0,
        'tgl_pengajuan'   => rekapFmtDate($r['tgl_pengajuan'] ?? ''),
        'ticket'          => $r['ticket'] ?? '',
        'nama_pemohon'    => $r['nama_pemohon'] ?? '',
        'departemen'      => $r['departemen'] ?? '',
        'bagian'          => $r['bagian'] ?? '',
        'tgl_keluar'      => rekapFmtDate($r['tgl_keluar'] ?? ''),
        'jam_keluar'      => !empty($r['jam_keluar_real']) ? rekapFmtTime($r['jam_keluar_real']) : '-',
        'jam_kembali'     => !empty($r['jam_kembali_real']) ? rekapFmtTime($r['jam_kembali_real']) : '-',
        'tipe_keluar'     => $r['tipe_keluar'] ?? $r['kendaraan'] ?? '',
        'status_ticket'   => $r['status_ticket'] ?? '',
        'status_kembali'  => calcStatusKembali($r),
    ];
}

// Nomor urut
foreach ($flatRows as $i => &$fr) {
    $fr['no_counter'] = $i + 1;
}
unset($fr);

// ─── Header info ──────────────────────────────────────────────────────────────
$tglCetak    = date('d/m/Y H:i');
$filterLabel = [];
if ($dari)   $filterLabel[] = 'Dari: ' . rekapFmtDate($dari);
if ($sampai) $filterLabel[] = 'Sampai: ' . rekapFmtDate($sampai);
if ($status) $filterLabel[] = 'Status: ' . rekapEsc($status);
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

// ─── Definisi kolom ──────────────────────────────────────────────────────────
// No | Tgl Pengajuan | Nomor Tiket | Pemohon | Dept | Tgl Keluar | Jam Keluar | Jam Kembali | Tipe Keluar | Status | Status Kembali
$colCount = 11;
$theadHtml = '
    <th style="width:3%">No</th>
    <th style="width:8%">Tgl Pengajuan</th>
    <th style="width:11%">Nomor Tiket</th>
    <th style="width:12%">Pemohon</th>
    <th style="width:10%">Dept</th>
    <th style="width:8%">Tgl Keluar</th>
    <th style="width:7%">Jam Keluar</th>
    <th style="width:7%">Jam Kembali</th>
    <th style="width:10%">Tipe Keluar</th>
    <th style="width:10%">Status</th>
    <th style="width:10%">Status Kembali</th>';

// ─── Tabel baris ─────────────────────────────────────────────────────────────
$tbody = '';
if (empty($flatRows)) {
    $tbody = '<tr><td colspan="' . $colCount . '" class="nodata">Tidak ada data</td></tr>';
} else {
    foreach ($flatRows as $i => $fr) {
        $rowClass = ($i % 2 === 0) ? 'even' : 'odd';
        $tbody .= '<tr class="' . $rowClass . '">';
        $tbody .= '<td class="center">' . $fr['no_counter']         . '</td>';
        $tbody .= '<td class="center">' . rekapEsc($fr['tgl_pengajuan']) . '</td>';
        $tbody .= '<td>'               . rekapEsc($fr['ticket'])       . '</td>';
        $tbody .= '<td>'               . rekapEsc($fr['nama_pemohon']) . '</td>';
        $tbody .= '<td>'               . rekapEsc($fr['departemen'])   . '</td>';
        $tbody .= '<td class="center">' . rekapEsc($fr['tgl_keluar'])  . '</td>';
        $tbody .= '<td class="center">' . rekapEsc($fr['jam_keluar'])  . '</td>';
        $tbody .= '<td class="center">' . rekapEsc($fr['jam_kembali']) . '</td>';
        $tbody .= '<td>'               . rekapEsc($fr['tipe_keluar'])  . '</td>';
        $tbody .= '<td class="center">' . rekapEsc($fr['status_ticket']) . '</td>';

        // Status Kembali dengan warna badge
        $sk = $fr['status_kembali'];
        $skColor = '#6c757d'; // default abu
        if ($sk === 'Sudah Kembali')     $skColor = '#28a745'; // hijau
        elseif ($sk === 'Terlambat')     $skColor = '#dc3545'; // merah
        elseif ($sk === 'Sedang Keluar') $skColor = '#ffc107'; // kuning
        $tbody .= '<td class="center"><span style="color:' . $skColor . ';font-weight:bold;">' . rekapEsc($sk) . '</span></td>';

        $tbody .= '</tr>';
    }
}

// ─── Judul PDF ────────────────────────────────────────────────────────────────
$pdfTitle = 'REKAP FORM IZIN KELUAR PABRIK';

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

$filename = 'Rekap_Izin_Keluar_Pabrik_' . date('Ymd_His') . '.pdf';

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
