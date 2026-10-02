<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../../koneksi3.php';

$requestId = bin2hex(random_bytes(8));
$userName = trim((string) ($_SESSION['UserName'] ?? ''));

/** Return a consistent JSON response and stop execution. */
function productionDescriptionRespond(int $httpStatus, bool $ok, string $message, array $extra = []): void
{
  global $requestId;
  http_response_code($httpStatus);
  echo json_encode(array_merge(['ok' => $ok, 'status' => $ok ? 'success' : 'error', 'message' => $message, 'request_id' => $requestId], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

/** Write a sanitized structured application error. */
function productionDescriptionLog(string $message, string $stage, array $context = []): void
{
  global $requestId, $userName;
  $logDir = __DIR__ . '/../../../logs';
  if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
  $entry = ['timestamp' => date(DATE_ATOM), 'severity' => 'ERROR', 'request_id' => $requestId, 'module' => 'resep_obat/experiment', 'action' => 'save_production_description', 'user' => $userName ?: null, 'message' => $message, 'source' => basename(__FILE__), 'line' => null, 'context' => array_merge(['stage' => $stage, 'dependency' => 'PostgreSQL/SQL Server'], $context)];
  @file_put_contents($logDir . '/error-' . date('Y-m-d') . '.log', json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

if ($userName === '') productionDescriptionRespond(401, false, 'Session habis. Silakan login ulang.');
$resepId = (int) ($_POST['resep_id'] ?? 0);
$productionRtgId = (int) ($_POST['productionrtgid'] ?? 0);
$description = trim((string) ($_POST['description'] ?? ''));
if ($resepId <= 0 || $productionRtgId <= 0) productionDescriptionRespond(422, false, 'Data produksi tidak valid.');
if (mb_strlen($description, 'UTF-8') > 4000) productionDescriptionRespond(422, false, 'Deskripsi maksimal 4000 karakter.');

$isAdmin = (int) ($_SESSION['GroupId'] ?? 0) === 1;
$isQc = false;
if (!$isAdmin) {
  $qcStmt = sqlsrv_query($conn, "SELECT TOP 1 g.id FROM dbo.resep_obat_group_members m INNER JOIN dbo.resep_obat_groups g ON m.group_id = g.id WHERE m.username = ? AND g.role_type = 'QC'", [$userName]);
  $isQc = $qcStmt && sqlsrv_fetch_array($qcStmt, SQLSRV_FETCH_ASSOC);
  if ($qcStmt) sqlsrv_free_stmt($qcStmt);
}
if (!$isAdmin && !$isQc) productionDescriptionRespond(403, false, 'Hanya QC atau Administrator yang boleh mengubah Deskripsi.');

$experimentStmt = sqlsrv_query(
  $conn,
  "SELECT e.id,
          COALESCE(NULLIF(LTRIM(RTRIM(g.no_cp)), ''), NULLIF(LTRIM(RTRIM(e.no_cp)), ''), '') AS no_cp
     FROM dbo.resep_obat_experiment e
     LEFT JOIN dbo.resep_obat_experiment_group g ON g.id = e.group_id
    WHERE e.id = ?",
  [$resepId]
);
$experiment = $experimentStmt ? sqlsrv_fetch_array($experimentStmt, SQLSRV_FETCH_ASSOC) : null;
if ($experimentStmt) sqlsrv_free_stmt($experimentStmt);
$cpNo = trim((string) ($experiment['no_cp'] ?? ''));
if (!$experiment || $cpNo === '') productionDescriptionRespond(404, false, 'Experiment atau No CP tidak ditemukan.');

$pgTransactionStarted = false;
$sqlTransactionStarted = false;
try {
  $conn3->beginTransaction();
  $pgTransactionStarted = true;
  $targetStmt = $conn3->prepare("WITH latest_production AS (SELECT productionhdid, TRIM(CAST(prdnmbr AS TEXT)) AS prdnmbr, prddate FROM pdproductionhd WHERE UPPER(TRIM(CAST(prdnmbr AS TEXT))) = UPPER(:cp_no) ORDER BY prddate DESC NULLS LAST, productionhdid DESC LIMIT 1), selected_acc AS (SELECT r.productionrtgid, r.productionhdid, p.prdnmbr, p.prddate, r.rtgmsid, COALESCE(m.rtgname, '') AS rtgmsname, r.resultdesc, r.startdate, r.starttime, r.enddate, r.endtime FROM latest_production p JOIN pdproductionrtg r ON r.productionhdid = p.productionhdid AND r.rtgmsid = 589 LEFT JOIN pdrtgms m ON m.rtgmsid = r.rtgmsid ORDER BY CASE WHEN r.failmsid IS NOT NULL AND r.failmsid <> 0 THEN 0 ELSE 1 END ASC, r.starttime DESC NULLS LAST, r.productionrtgid DESC LIMIT 1) SELECT * FROM selected_acc WHERE productionrtgid = :productionrtgid FOR UPDATE");
  $targetStmt->execute([':cp_no' => $cpNo, ':productionrtgid' => $productionRtgId]);
  $snapshot = $targetStmt->fetch(PDO::FETCH_ASSOC);
  if (!$snapshot) { $conn3->rollBack(); productionDescriptionRespond(409, false, 'Data ACC Warna R sudah berubah. Muat ulang halaman.'); }
  $oldDescription = trim((string) ($snapshot['resultdesc'] ?? ''));
  if ($oldDescription === $description) { $conn3->rollBack(); productionDescriptionRespond(200, true, 'Deskripsi tidak berubah.', ['changed' => false]); }

  $updateStmt = $conn3->prepare("UPDATE public.pdproductionrtg SET resultdesc = :resultdesc WHERE productionrtgid = :productionrtgid");
  $updateStmt->execute([':resultdesc' => $description === '' ? null : $description, ':productionrtgid' => $productionRtgId]);
  if ($updateStmt->rowCount() !== 1) throw new RuntimeException('Target PostgreSQL tidak terbarui.');

  if (!sqlsrv_begin_transaction($conn)) throw new RuntimeException('Transaksi audit tidak dapat dimulai.');
  $sqlTransactionStarted = true;
  $changedName = trim((string) ($_SESSION['FullName'] ?? '')) ?: $userName;
  $historySql = "INSERT INTO dbo.history_productionrtg (productionrtgid, productionhdid, prdnmbr, prddate, rtgmsid, rtgmsname, old_resultdesc, new_resultdesc, old_startdate, old_starttime, old_enddate, old_endtime, new_startdate, new_starttime, new_enddate, new_endtime, change_type, changed_by, changed_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
  $historyParams = [(int) $snapshot['productionrtgid'], (int) $snapshot['productionhdid'], $snapshot['prdnmbr'], $snapshot['prddate'], (int) $snapshot['rtgmsid'], trim((string) $snapshot['rtgmsname']) ?: 'ACC WARNA R', $oldDescription, $description, $snapshot['startdate'], $snapshot['starttime'], $snapshot['enddate'], $snapshot['endtime'], $snapshot['startdate'], $snapshot['starttime'], $snapshot['enddate'], $snapshot['endtime'], 'resultdesc', $userName, $changedName];
  $historyStmt = sqlsrv_query($conn, $historySql, $historyParams);
  if (!$historyStmt) throw new RuntimeException('Audit SQL Server gagal disimpan.');
  sqlsrv_free_stmt($historyStmt);
  if (!sqlsrv_commit($conn)) throw new RuntimeException('Audit SQL Server gagal dikomit.');
  $sqlTransactionStarted = false;
  $conn3->commit();
  $pgTransactionStarted = false;
  productionDescriptionRespond(200, true, 'Deskripsi berhasil disimpan.', ['changed' => true, 'description' => $description]);
} catch (Throwable $error) {
  if ($sqlTransactionStarted) sqlsrv_rollback($conn);
  if ($pgTransactionStarted && $conn3->inTransaction()) $conn3->rollBack();
  productionDescriptionLog('Penyimpanan Deskripsi produksi gagal.', 'cross_database_save', ['exception_class' => get_class($error), 'resep_id' => $resepId, 'productionrtgid' => $productionRtgId]);
  productionDescriptionRespond(500, false, 'Deskripsi gagal disimpan.');
}
