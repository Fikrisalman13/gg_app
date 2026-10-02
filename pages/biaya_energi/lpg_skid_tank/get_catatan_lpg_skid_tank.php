<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) { echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']); exit; }

$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) { echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak untuk melihat catatan.']); exit; }

$start = trim($_POST['start_date'] ?? '');
$end = trim($_POST['end_date'] ?? '');
if ($start === '' || $end === '') { echo json_encode(['success' => false, 'message' => 'Rentang tanggal wajib diisi.']); exit; }

$sql = "SELECT CAST(h.tanggal AS DATE) AS tanggal, h.tank_kode, m.nama AS tank_nama, m.urutan, h.note, COALESCE(h.updateby, h.creatby, '') AS creatby
        FROM dbo.lpg_skid_tank_harian h
        LEFT JOIN dbo.lpg_skid_tank_master m ON m.kode=h.tank_kode
        WHERE CAST(h.tanggal AS DATE) BETWEEN ? AND ?
          AND h.note IS NOT NULL AND LTRIM(RTRIM(h.note)) <> ''
        ORDER BY CAST(h.tanggal AS DATE) DESC, m.urutan ASC";
$stmt = sqlsrv_query($conn, $sql, [$start, $end]);
if ($stmt === false) { echo json_encode(['success' => false, 'message' => 'Gagal mengambil catatan.']); exit; }

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tanggal = $row['tanggal'];
    if ($tanggal instanceof DateTime) $tanggal = $tanggal->format('Y-m-d');
    $urutan = isset($row['urutan']) ? (int)$row['urutan'] : 0;
    $label = 'Pencatatan Pemakaian ' . ($urutan > 0 ? $urutan : '-');
    $namaTank = trim((string)($row['tank_nama'] ?? $row['tank_kode'] ?? ''));
    $data[] = [
        'tanggal' => $tanggal,
        'tank_nama' => $label . ($namaTank !== '' ? ' - ' . $namaTank : ''),
        'catatan' => $row['note'] ?? '',
        'creatby' => $row['creatby'] ?? ''
    ];
}
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['success' => true, 'data' => $data]);
