<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../koneksi.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['UserName'])) {
  echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit;
}
$user = $_SESSION['UserName'];
$now  = date('Y-m-d H:i:s');

$subbag_id = (int)($_POST['subbag_id'] ?? 0);
if ($subbag_id <= 0) {
  echo json_encode(['status'=>'error','message'=>'Sub bagian tidak valid']); exit;
}

$raw = $_POST['fields'] ?? '[]';
$fields = json_decode($raw, true);
if (!is_array($fields) || count($fields) === 0) {
  echo json_encode(['status'=>'error','message'=>'Data field kosong']); exit;
}

$ok = 0; $err = '';
sqlsrv_query($conn, "BEGIN TRAN");
try {
  foreach ($fields as $f) {
    $key = $f['key'] ?? '';
    $ro  = !empty($f['readonly']) ? 1 : 0;
    if ($key === '') continue;

    $check = sqlsrv_query($conn, "SELECT id FROM dbo.resep_field_trustee WHERE subbag_id=? AND field_key=?", [$subbag_id, $key]);
    $exists = $check && sqlsrv_fetch_array($check, SQLSRV_FETCH_ASSOC);

    if ($exists) {
      $sql = "UPDATE dbo.resep_field_trustee SET is_readonly=?, updated_at=?, updated_by=? WHERE subbag_id=? AND field_key=?";
      $params = [$ro, $now, $user, $subbag_id, $key];
    } else {
      $sql = "INSERT INTO dbo.resep_field_trustee (subbag_id, field_key, is_readonly, created_at, created_by) VALUES (?,?,?,?,?)";
      $params = [$subbag_id, $key, $ro, $now, $user];
    }
    $r = sqlsrv_query($conn, $sql, $params);
    if ($r === false) {
      throw new Exception(print_r(sqlsrv_errors(), true));
    }
    $ok++;
  }
  sqlsrv_query($conn, "COMMIT");
  echo json_encode(['status'=>'success','message'=>"$ok field berhasil disimpan."]);
} catch (Exception $e) {
  sqlsrv_query($conn, "ROLLBACK");
  echo json_encode(['status'=>'error','message'=>'Gagal simpan: '.$e->getMessage()]);
}
