<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../../koneksi.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['UserName'])) { echo json_encode(['exists'=>false,'message'=>'Unauthorized']); exit; }

$kode_warna = trim($_GET['kode_warna'] ?? $_POST['kode_warna'] ?? '');
if ($kode_warna === '') { echo json_encode(['exists'=>false]); exit; }

$sql = "SELECT TOP 1 id, soi, no_cp, kode_warna, color_name, group_status, approved_experiment_id
        FROM dbo.resep_obat_experiment_group
        WHERE kode_warna = ?
        ORDER BY id DESC";
$stmt = sqlsrv_query($conn, $sql, [$kode_warna]);
$row = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;

if (!$row) { echo json_encode(['exists'=>false]); exit; }

echo json_encode([
  'exists' => true,
  'group' => [
    'id' => (int)($row['id'] ?? 0),
    'soi' => $row['soi'] ?? '',
    'no_cp' => $row['no_cp'] ?? '',
    'kode_warna' => $row['kode_warna'] ?? '',
    'color_name' => $row['color_name'] ?? '',
    'group_status' => $row['group_status'] ?? '',
    'approved_experiment_id' => $row['approved_experiment_id'] ?? null,
  ]
]);
