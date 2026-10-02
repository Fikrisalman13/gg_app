<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
if (!isset($_SESSION['UserName'])) {
    die('Silakan login terlebih dahulu!');
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';

$q = trim((string)($_GET['q'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? '')) ?: date('Y-m-01');
$dateTo = trim((string)($_GET['date_to'] ?? '')) ?: date('Y-m-d');

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename=Output_Manual_' . date('Ymd_His') . '.xls');
header('Pragma: no-cache');
header('Expires: 0');

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
if (!empty($conn)) {
    $sql = "SELECT id, prdnmbr, iso, partai, cuscolor, meter, gol, grey, tengah, start_time, finish_time, stdcutfg, op_mesin, labeljual, ket, created_by
            FROM manual_output
            WHERE CAST(created_at AS date) BETWEEN ? AND ?
              AND (? = '' OR prdnmbr LIKE ? OR iso LIKE ? OR partai LIKE ? OR cuscolor LIKE ? OR labeljual LIKE ? OR op_mesin LIKE ? OR ket LIKE ?)
            ORDER BY id DESC";
    $like = '%' . $q . '%';
    $stmt = sqlsrv_query($conn, $sql, [$dateFrom, $dateTo, $q, $like, $like, $like, $like, $like, $like, $like]);
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
}

echo '<table border="1">';
echo '<tr><th colspan="16" style="background:#d9edf7;font-size:16px;font-weight:bold;">OUTPUT MANUAL</th></tr>';
echo '<tr><th>No</th><th>PRDNMBR</th><th>ISO</th><th>Partai</th><th>Warna</th><th>Meter</th><th>Gol</th><th>Grey</th><th>Tengah</th><th>Start</th><th>Finish</th><th>STD Potong</th><th>OP Mesin</th><th>L Jual</th><th>Ket</th><th>Created By</th></tr>';
$no = 1;
foreach ($rows as $row) {
    echo '<tr>';
    echo '<td>' . $no++ . '</td>';
    echo '<td>' . htmlspecialchars((string)($row['prdnmbr'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($row['iso'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($row['partai'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($row['cuscolor'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($row['meter'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($row['gol'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($row['grey'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($row['tengah'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars(excel_dt($row['start_time'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars(excel_dt($row['finish_time'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($row['stdcutfg'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($row['op_mesin'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($row['labeljual'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($row['ket'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($row['created_by'] ?? '')) . '</td>';
    echo '</tr>';
}
echo '</table>';
