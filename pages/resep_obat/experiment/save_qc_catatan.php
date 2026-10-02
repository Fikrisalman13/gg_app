<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../koneksi.php';

function respond($status, $message, $extra = []) {
  echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
  exit;
}

if (!isset($_SESSION['UserName'])) respond('error', 'Session habis. Silakan login ulang.');

$resepId = (int)($_POST['resep_id'] ?? 0);
$catatan = trim((string)($_POST['catatan'] ?? ''));
$keputusan = trim((string)($_POST['keputusan'] ?? ''));
$tindakan = trim((string)($_POST['tindakan'] ?? ''));
$allowedKeputusan = ['Master Resep', 'Matching Ulang', 'Test Repeat 1x Lagi'];
$allowedTindakan = ['Soaping Ulang', 'Shading', 'Topping Padry', 'Topping CPB', 'Over Warna', 'Pass Upgrade'];
if ($resepId <= 0) respond('error', 'ID resep tidak valid.');
if ($keputusan !== '' && !in_array($keputusan, $allowedKeputusan, true)) respond('error', 'Keputusan QC tidak valid.');
if ($tindakan !== '' && !in_array($tindakan, $allowedTindakan, true)) respond('error', 'Tindakan QC tidak valid.');

$isAdmin = (int)($_SESSION['GroupId'] ?? 0) === 1;
$isQc = false;
if (!$isAdmin) {
  $stmtQc = sqlsrv_query(
    $conn,
    "SELECT TOP 1 g.id
       FROM dbo.resep_obat_group_members m
       INNER JOIN dbo.resep_obat_groups g ON m.group_id = g.id
       WHERE m.username = ? AND g.role_type = 'QC'",
    [$_SESSION['UserName'] ?? '']
  );
  $isQc = $stmtQc && sqlsrv_fetch_array($stmtQc, SQLSRV_FETCH_ASSOC);
  if ($stmtQc) sqlsrv_free_stmt($stmtQc);
}
if (!$isAdmin && !$isQc) respond('error', 'Hanya QC atau Administrator yang boleh menyimpan Catatan QC.');

$stmt = sqlsrv_query($conn, "SELECT id, no_cp, experiment_status FROM dbo.resep_obat_experiment WHERE id=?", [$resepId]);
$data = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
if (!$data) respond('error', 'Data experiment tidak ditemukan.');

$displayStatus = trim((string)($data['experiment_status'] ?? 'Draft')) ?: 'Draft';
$cpNo = trim((string)($data['no_cp'] ?? ''));
if (in_array($displayStatus, ['Process', 'Prosess'], true) && $cpNo !== '') {
  try {
    require_once __DIR__ . '/../../../koneksi3.php';
    $pgStmt = $conn3->prepare("
      WITH latest_cp AS (
        SELECT productionhdid
        FROM pdproductionhd
        WHERE UPPER(TRIM(CAST(prdnmbr AS TEXT))) = UPPER(:cp_no)
        ORDER BY prddate DESC NULLS LAST, productionhdid DESC
        LIMIT 1
      )
      SELECT COALESCE(r.failmsid, 0) AS failmsid,
             COALESCE(f.failcode, '') AS failcode,
             COALESCE(f.faildesc, '') AS faildesc
      FROM latest_cp l
      JOIN pdproductionrtg r ON r.productionhdid = l.productionhdid AND r.rtgmsid = 589
      LEFT JOIN pdfailms f ON r.failmsid = f.failmsid
      ORDER BY CASE WHEN r.failmsid IS NOT NULL AND r.failmsid <> 0 THEN 0 ELSE 1 END ASC,
               r.starttime DESC NULLS LAST,
               r.productionrtgid DESC
      LIMIT 1
    ");
    $pgStmt->execute([':cp_no' => $cpNo]);
    if ($row = $pgStmt->fetch(PDO::FETCH_ASSOC)) {
      $hasFailName = trim((string)($row['failcode'] ?? '')) !== '' || trim((string)($row['faildesc'] ?? '')) !== '';
      if ((int)($row['failmsid'] ?? 0) !== 0 && $hasFailName) $displayStatus = 'Fail';
    }
  } catch (Throwable $e) {
    error_log('save_qc_catatan status error: ' . $e->getMessage());
  }
}
if (!in_array($displayStatus, ['Pass', 'Fail'], true)) respond('error', 'Keputusan QC hanya bisa disimpan untuk experiment status Pass atau Fail.');
if ($displayStatus === 'Fail') {
  $update = sqlsrv_query(
    $conn,
    "UPDATE dbo.resep_obat_experiment
        SET qc_keputusan=?, qc_tindakan=?, qc_catatan=?, qc_catatan_by=?, qc_catatan_at=GETDATE()
      WHERE id=?",
    [$keputusan, $tindakan, $catatan, $_SESSION['UserName'] ?? '', $resepId]
  );
} else {
  $update = sqlsrv_query(
    $conn,
    "UPDATE dbo.resep_obat_experiment
        SET qc_keputusan=?, qc_tindakan=?, qc_catatan_by=?, qc_catatan_at=GETDATE()
      WHERE id=?",
    [$keputusan, $tindakan, $_SESSION['UserName'] ?? '', $resepId]
  );
}
if (!$update) respond('error', 'Gagal menyimpan Catatan QC.', ['debug' => sqlsrv_errors()]);

respond('success', 'QC tersimpan.', [
  'catatan_by' => $_SESSION['UserName'] ?? '',
  'catatan_at' => date('d-m-Y H:i'),
]);
