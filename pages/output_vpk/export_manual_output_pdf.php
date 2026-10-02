<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function pdf_escape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function pdf_dt_date($value): string
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('d M Y');
    }
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    $timestamp = strtotime($value);
    return $timestamp ? date('d M Y', $timestamp) : $value;
}

function pdf_dt_time($value): string
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('H:i');
    }
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    $timestamp = strtotime($value);
    return $timestamp ? date('H:i', $timestamp) : $value;
}

function pdf_dt_inline($value): string
{
    $date = pdf_dt_date($value);
    $time = pdf_dt_time($value);
    if ($date === '' && $time === '') {
        return '-';
    }
    return trim($date . ' ' . $time);
}

$q = trim((string)($_GET['q'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? '')) ?: date('Y-m-01');
$dateTo = trim((string)($_GET['date_to'] ?? '')) ?: date('Y-m-d');

$rows = [];
if (!empty($conn)) {
    $sql = "SELECT o.id,o.prdnmbr,o.iso,o.partai,o.cuscolor,o.meter,o.gol,o.grey,o.tengah,
            COALESCE(times.first_start,o.start_time) start_time,
            CASE WHEN o.status_draft=1 THEN NULL WHEN m.prdqty IS NOT NULL AND o.meter>=m.prdqty-0.00001 THEN COALESCE(times.last_finish,o.finish_time) END finish_time,
            o.stdcutfg,o.op_mesin,mm.kode_mesin,o.labeljual,o.ket,o.status_draft
        FROM manual_output o
        LEFT JOIN manual_output_master m ON m.prdnmbr=o.prdnmbr
        LEFT JOIN manual_output_master_mesin mm ON mm.id=o.mesin_id
        OUTER APPLY (SELECT
            (SELECT TOP 1 s1.start_time FROM manual_output_shift s1 WHERE s1.manual_output_id=o.id ORDER BY s1.id ASC) first_start,
            (SELECT TOP 1 s2.finish_time FROM manual_output_shift s2 WHERE s2.manual_output_id=o.id ORDER BY s2.id DESC) last_finish
        ) times
        WHERE CAST(o.created_at AS date) BETWEEN ? AND ?
          AND (?='' OR o.prdnmbr LIKE ? OR o.cuscolor LIKE ? OR o.labeljual LIKE ? OR o.op_mesin LIKE ? OR o.ket LIKE ? OR mm.kode_mesin LIKE ? OR mm.nama_mesin LIKE ?)
        ORDER BY o.id DESC";
    $like = '%' . $q . '%';
    $stmt = sqlsrv_query($conn, $sql, [$dateFrom, $dateTo, $q, $like, $like, $like, $like, $like, $like, $like]);
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
}

$filterParts = [];
if ($q !== '') {
    $filterParts[] = 'Search: ' . $q;
}
$filterParts[] = 'Tanggal: ' . date('d-m-Y', strtotime($dateFrom)) . ' s/d ' . date('d-m-Y', strtotime($dateTo));
$filterText = implode(' | ', $filterParts);
$totalRows = count($rows);

$html = '<!doctype html><html><head><meta charset="UTF-8"><style>
    @page { margin: 10mm 7mm; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 6.3pt; color: #222; }
    .header { margin-bottom: 6px; }
    .title { font-size: 12pt; font-weight: bold; margin: 0 0 2px 0; color: #1f4e79; }
    .meta { font-size: 7pt; color: #444; margin: 0 0 2px 0; }
    .summary { font-size: 7pt; margin: 4px 0 6px 0; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    th, td { border: 0.5px solid #7f8c8d; padding: 2px 2px; vertical-align: middle; line-height: 1.04; }
    th { background: #3c8dbc; color: #fff; font-weight: bold; text-align: center; }
    tbody tr:nth-child(even) td { background: #f8f8f8; }
    .center { text-align: center; }
    .right { text-align: right; }
    .nowrap { white-space: nowrap; }
    .wrap { white-space: normal; word-break: break-word; overflow-wrap: break-word; }
    .break-all { word-break: break-all; }
    .dt { font-size: 6pt; line-height: 1; }
</style></head><body>';
$html .= '<div class="header"><div class="title">Output Manual</div><div class="meta">' . pdf_escape($filterText) . '</div><div class="meta">Dicetak: ' . pdf_escape(date('d-m-Y H:i:s')) . '</div></div>';
$html .= '<div class="summary">Total data: <strong>' . $totalRows . '</strong></div>';
$html .= '<table><thead><tr>'
    . '<th style="width:3%">No</th>'
    . '<th style="width:11%">PRDNMBR</th>'
    . '<th style="width:5%">ISO</th>'
    . '<th style="width:5%">Partai</th>'
    . '<th style="width:6%">Warna</th>'
    . '<th style="width:6%">Meter</th>'
    . '<th style="width:4%">Gol</th>'
    . '<th style="width:4%">Grey</th>'
    . '<th style="width:4%">Tengah</th>'
    . '<th style="width:9%">Start</th>'
    . '<th style="width:9%">Finish</th>'
    . '<th style="width:6%">Status</th>'
    . '<th style="width:6%">STD Potong</th>'
    . '<th style="width:6%">OP<br>Mesin</th>'
    . '<th style="width:5%">Mesin</th>'
    . '<th style="width:5%">L Jual</th>'
    . '<th style="width:6%">Ket</th>'
    . '</tr></thead><tbody>';

if ($rows) {
    foreach ($rows as $index => $row) {
        $status = !empty($row['status_draft']) ? 'Belum Berjalan' : (!empty($row['finish_time']) ? 'Selesai' : 'Berjalan');
        $startDate = pdf_dt_date($row['start_time'] ?? '');
        $startTime = pdf_dt_time($row['start_time'] ?? '');
        $finishDate = !empty($row['finish_time']) ? pdf_dt_date($row['finish_time']) : '-';
        $finishTime = !empty($row['finish_time']) ? pdf_dt_time($row['finish_time']) : '-';
        $html .= '<tr>'
            . '<td class="center nowrap">' . ($index + 1) . '</td>'
            . '<td class="break-all">' . pdf_escape($row['prdnmbr'] ?? '') . '</td>'
            . '<td class="center nowrap">' . pdf_escape($row['iso'] ?? '') . '</td>'
            . '<td class="center nowrap">' . pdf_escape($row['partai'] ?? '') . '</td>'
            . '<td class="wrap">' . pdf_escape($row['cuscolor'] ?? '') . '</td>'
            . '<td class="right nowrap">' . pdf_escape((string)($row['meter'] ?? '')) . '</td>'
            . '<td class="center nowrap">' . pdf_escape((string)($row['gol'] ?? '')) . '</td>'
            . '<td class="center nowrap">' . pdf_escape((string)($row['grey'] ?? '')) . '</td>'
            . '<td class="center nowrap">' . pdf_escape((string)($row['tengah'] ?? '')) . '</td>'
            . '<td class="center dt">' . pdf_escape($startDate) . '<br>' . pdf_escape($startTime) . '</td>'
            . '<td class="center dt">' . pdf_escape($finishDate) . '<br>' . pdf_escape($finishTime) . '</td>'
            . '<td class="center nowrap">' . pdf_escape($status) . '</td>'
            . '<td class="center nowrap">' . pdf_escape((string)($row['stdcutfg'] ?? '')) . '</td>'
            . '<td class="wrap">' . pdf_escape((string)($row['op_mesin'] ?? '')) . '</td>'
            . '<td class="center nowrap">' . pdf_escape((string)($row['kode_mesin'] ?? '-')) . '</td>'
            . '<td class="wrap">' . pdf_escape((string)($row['labeljual'] ?? '')) . '</td>'
            . '<td class="wrap">' . pdf_escape((string)($row['ket'] ?? '')) . '</td>'
            . '</tr>';
    }
} else {
    $html .= '<tr><td class="center" colspan="17">Tidak ada data</td></tr>';
}

$html .= '</tbody></table></body></html>';

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

while (ob_get_level() > 0) {
    ob_end_clean();
}

$dompdf->stream('Output_Manual_' . date('Ymd_His') . '.pdf', ['Attachment' => true]);
exit;
