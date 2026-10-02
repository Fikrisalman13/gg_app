<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../koneksi.php';
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['UserName'])) { echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }

/**
 * Konversi nilai input ke float atau null.
 * Untuk sqlsrv, null PHP otomatis menjadi NULL di SQL Server pada kolom DECIMAL
 * selama kolom memang nullable — ini sudah benar.
 * Pastikan string kosong dan string '0' dibedakan:
 *   '' / null  → null (tidak diisi)
 *   '0' / 0   → 0.0  (diisi dengan nol)
 */
function num($v) {
  if ($v === '' || $v === null) return null;
  $clean = str_replace([' ', ','], ['', '.'], (string)$v);
  return is_numeric($clean) ? (float)$clean : null;
}

$action   = $_POST['action'] ?? '';
$id       = trim($_POST['id'] ?? '');
$kode     = trim($_POST['kode_mesin'] ?? '');
$nama     = trim($_POST['nama_mesin'] ?? '');
$status   = trim($_POST['status'] ?? 'Active');
$user     = $_SESSION['UserName'];
$now      = date('Y-m-d H:i:s');


$paramsDefault = [
  'infra_red' => num($_POST['infra_red'] ?? null),
  'tekanan_padder' => num($_POST['tekanan_padder'] ?? null),
  'wpu' => num($_POST['wpu'] ?? null),
  'speed' => num($_POST['speed'] ?? null),
  'fan1' => num($_POST['fan1'] ?? null),
  'fan2' => num($_POST['fan2'] ?? null),
  'temp_chamber_1' => num($_POST['temp_chamber_1'] ?? null),
  'temp_chamber_2' => num($_POST['temp_chamber_2'] ?? null),
  'temp_chamber_1_time' => num($_POST['temp_chamber_1_time'] ?? null),
  'temp_chamber_2_time' => num($_POST['temp_chamber_2_time'] ?? null),
];

if ($action === 'insert') {
  if ($kode === '') { echo json_encode(['status'=>'error','message'=>'Kode Mesin wajib diisi']); exit; }
  if ($nama === '') { echo json_encode(['status'=>'error','message'=>'Nama Mesin wajib diisi']); exit; }

  $cek = sqlsrv_query($conn, "SELECT id FROM dbo.master_mesin_lab WHERE kode_mesin=?", [$kode]);
  if ($cek && sqlsrv_fetch_array($cek)) {
    echo json_encode(['status'=>'error','message'=>'Kode Mesin sudah ada']); exit;
  }

  $s = sqlsrv_query($conn,
    "INSERT INTO dbo.master_mesin_lab
      (kode_mesin, nama_mesin, status, infra_red, tekanan_padder, wpu, speed, fan1, fan2, temp_chamber_1, temp_chamber_2, temp_chamber_1_time, temp_chamber_2_time, created_at, created_by)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
    [
      $kode, $nama, $status,
      [$paramsDefault['infra_red'],    SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DECIMAL(18,4)],
      [$paramsDefault['tekanan_padder'],SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DECIMAL(18,4)],
      [$paramsDefault['wpu'],          SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DECIMAL(18,4)],
      [$paramsDefault['speed'],        SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DECIMAL(18,4)],
      [$paramsDefault['fan1'],         SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DECIMAL(18,4)],
      [$paramsDefault['fan2'],         SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DECIMAL(18,4)],
      [$paramsDefault['temp_chamber_1'],SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DECIMAL(18,4)],
      [$paramsDefault['temp_chamber_2'],SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DECIMAL(18,4)],
      [$paramsDefault['temp_chamber_1_time'],SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DECIMAL(18,4)],
      [$paramsDefault['temp_chamber_2_time'],SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DECIMAL(18,4)],
      $now, $user
    ]);
  if (!$s) { echo json_encode(['status'=>'error','message'=>'Gagal simpan: '.print_r(sqlsrv_errors(),true)]); exit; }
  echo json_encode(['status'=>'success','message'=>'Mesin berhasil disimpan.']);

} elseif ($action === 'update') {
  $idInt = (int)$id;
  if ($idInt <= 0) { echo json_encode(['status'=>'error','message'=>'ID tidak valid']); exit; }
  if ($kode === '') { echo json_encode(['status'=>'error','message'=>'Kode Mesin wajib diisi']); exit; }
  if ($nama === '') { echo json_encode(['status'=>'error','message'=>'Nama Mesin wajib diisi']); exit; }

  $cur = sqlsrv_query($conn, "SELECT kode_mesin FROM dbo.master_mesin_lab WHERE id=?", [$idInt]);
  if (!$cur) {
    echo json_encode(['status'=>'error','message'=>'Gagal mengambil data: '.print_r(sqlsrv_errors(),true)]); exit;
  }
  $row = sqlsrv_fetch_array($cur, SQLSRV_FETCH_ASSOC);
  if (!$row) {
    echo json_encode(['status'=>'error','message'=>'Data mesin tidak ditemukan']); exit;
  }
  $oldKode = trim($row['kode_mesin']);

  if (strtolower($kode) !== strtolower($oldKode)) {
    $cek = sqlsrv_query($conn, "SELECT id FROM dbo.master_mesin_lab WHERE kode_mesin=?", [$kode]);
    if ($cek && sqlsrv_fetch_array($cek)) {
      echo json_encode(['status'=>'error','message'=>'Kode Mesin sudah digunakan mesin lain']); exit;
    }
  }

  $s = sqlsrv_query($conn,
    "UPDATE dbo.master_mesin_lab SET
      kode_mesin=?, nama_mesin=?, status=?, infra_red=?, tekanan_padder=?, wpu=?, speed=?, fan1=?, fan2=?, temp_chamber_1=?, temp_chamber_2=?, temp_chamber_1_time=?, temp_chamber_2_time=?, updated_at=?, updated_by=?
      WHERE id=?",
    [
      $kode, $nama, $status,
      [$paramsDefault['infra_red'],    SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DECIMAL(18,4)],
      [$paramsDefault['tekanan_padder'],SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DECIMAL(18,4)],
      [$paramsDefault['wpu'],          SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DECIMAL(18,4)],
      [$paramsDefault['speed'],        SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DECIMAL(18,4)],
      [$paramsDefault['fan1'],         SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DECIMAL(18,4)],
      [$paramsDefault['fan2'],         SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DECIMAL(18,4)],
      [$paramsDefault['temp_chamber_1'],SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DECIMAL(18,4)],
      [$paramsDefault['temp_chamber_2'],SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DECIMAL(18,4)],
      [$paramsDefault['temp_chamber_1_time'],SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DECIMAL(18,4)],
      [$paramsDefault['temp_chamber_2_time'],SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DECIMAL(18,4)],
      $now, $user, $idInt
    ]);
  if (!$s) { echo json_encode(['status'=>'error','message'=>'Gagal update: '.print_r(sqlsrv_errors(),true)]); exit; }

  echo json_encode(['status'=>'success','message'=>'Mesin berhasil diupdate.']);

} else {
  echo json_encode(['status'=>'error','message'=>'Action tidak dikenal']);
}
