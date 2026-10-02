<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

$start = trim($_POST['start_date'] ?? '');
$end = trim($_POST['end_date'] ?? '');

$where = "WHERE 1=1";
$params = [];
if ($start !== '') { $where .= " AND CAST(tanggal AS DATE) >= ?"; $params[] = $start; }
if ($end !== '') { $where .= " AND CAST(tanggal AS DATE) <= ?"; $params[] = $end; }

$sql = "SELECT id, tanggal, note, creatby, creatat, updateby, updateat
        FROM dbo.pencatatan_ipal_catatan
        $where
        ORDER BY CAST(tanggal AS DATE) DESC, id DESC";
$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) { echo json_encode(['success'=>false,'message'=>'Gagal mengambil catatan.']); exit; }

$rows = [];
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tgl = $r['tanggal'] instanceof DateTime ? $r['tanggal']->format('d-m-Y') : date('d-m-Y', strtotime((string)$r['tanggal']));
    $rows[] = [
        'id' => $r['id'],
        'tanggal' => $tgl,
        'note' => (string)($r['note'] ?? ''),
        'by' => (string)($r['updateby'] ?: $r['creatby'] ?: '-')
    ];
}
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['success'=>true,'data'=>$rows]);
