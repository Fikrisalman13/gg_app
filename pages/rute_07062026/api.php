<?php
// /gg_app/pages/rute/api.php
declare(strict_types=1);

ob_start();
ini_set('display_errors','0');
ini_set('log_errors','1');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../koneksi.php';   // harus menyediakan $conn (sqlsrv_connect)
date_default_timezone_set('Asia/Jakarta');

/* ---------- Utils ---------- */
function nocache(): void {
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: 0');
}
function json_out(array $arr, int $status=200): never {
  if (ob_get_length()) { ob_clean(); }
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  header('X-Content-Type-Options: nosniff');
  nocache();
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}
function sqlerr(): string {
  $es = sqlsrv_errors(SQLSRV_ERR_ERRORS);
  if (!$es) return 'Unknown database error';
  $msgs = array_map(fn($e)=>"[{$e['SQLSTATE']}] {$e['code']} {$e['message']}", $es);
  return implode(' | ', $msgs);
}
function required_post(string $key): string {
  $v = $_POST[$key] ?? '';
  if ($v === '' && $v !== '0') json_out(['success'=>false, 'error'=>"Missing field: {$key}"], 400);
  return (string)$v;
}
/** Normalisasi smart punctuation ke ASCII agar tidak “â€”” */
function normalize_ascii(string $s): string {
  return strtr($s, [
    "\xE2\x80\x93" => "-",  // –
    "\xE2\x80\x94" => "-",  // —
    "\xE2\x80\x98" => "'",  // ‘
    "\xE2\x80\x99" => "'",  // ’
    "\xE2\x80\x9C" => '"',  // “
    "\xE2\x80\x9D" => '"',  // ”
  ]);
}

/* ========= Auth & CRON bypass ========= */
nocache();

$action  = $_POST['action'] ?? '';
$JOB_KEY = getenv('AUTO_CLOSE_KEY') ?: 'ganti_dengan_string_acak_panjang';
$isCron  = ($action === 'autoclose_overdue' && (($_POST['cron_key'] ?? '') === $JOB_KEY));

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $action==='') {
  json_out(['success'=>false,'error'=>'Invalid request'], 400);
}
if (!isset($_SESSION['UserName']) && !$isCron) {
  json_out(['success'=>false, 'error'=>'Unauthorized'], 401);
}
$CREATED_BY = $_SESSION['UserName'] ?? ($isCron ? 'system-cron' : 'web');

if (!$conn) json_out(['success'=>false,'error'=>'Koneksi ke database gagal: '.sqlerr()], 500);

/* ========= Helpers SQL & Compat ========= */
function has_column($conn, string $table, string $col): bool {
  $st = sqlsrv_query($conn,
    "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='dbo' AND TABLE_NAME=? AND COLUMN_NAME=?",
    [$table,$col]
  );
  $ok = $st && sqlsrv_fetch($st) !== false;
  if ($st) sqlsrv_free_stmt($st);
  return (bool)$ok;
}
function first_existing_col($conn, string $table, array $candidates): ?string {
  foreach ($candidates as $c) { if (has_column($conn, $table, $c)) return $c; }
  return null;
}
function ensure_routes_audit_columns($conn): void {
  $ddl = "
    IF COL_LENGTH('dbo.Routes','UpdateBy') IS NULL
      ALTER TABLE dbo.Routes ADD UpdateBy NVARCHAR(100) NULL;
    IF COL_LENGTH('dbo.Routes','UpdateTime') IS NULL
      ALTER TABLE dbo.Routes ADD UpdateTime DATETIME2 NULL;
  ";
  sqlsrv_query($conn, $ddl);
}
function set_route_updated_meta($conn, int $routeId, string $by): void {
  $q = sqlsrv_query(
    $conn,
    "UPDATE dbo.Routes
     SET UpdateBy = ?, UpdateTime = DATEADD(HOUR, 7, SYSUTCDATETIME())
     WHERE RouteId = ?",
    [$by, $routeId]
  );
  if ($q) sqlsrv_free_stmt($q);
}
ensure_routes_audit_columns($conn);
function batch_status($conn, int $batchId): string {
  $st = sqlsrv_query($conn, "SELECT Status FROM dbo.Routes WHERE BatchId = ?", [$batchId]);
  if (!$st) return 'open';
  $allDone = true; $anyInprog=false;
  while ($row = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
    $s = strtolower((string)($row['Status'] ?? 'open'));
    if ($s !== 'done') $allDone = false;
    if ($s === 'inprog') $anyInprog = true;
  }
  sqlsrv_free_stmt($st);
  if ($allDone) return 'done';
  if ($anyInprog) return 'inprog';
  return 'open';
}
function base_name_from_route($conn, int $routeId): ?string {
  $st = sqlsrv_query($conn, "SELECT Name FROM dbo.Routes WHERE RouteId=?", [$routeId]);
  if (!$st) return null;
  $name = (string)(sqlsrv_fetch_array($st, SQLSRV_FETCH_NUMERIC)[0] ?? '');
  sqlsrv_free_stmt($st);
  if ($name==='') return null;
  return trim(preg_replace('/\s*\(Rute Awal\)\s*$/i','', $name));
}
function route_master_name_for_route($conn, int $routeId): ?string {
  $base = base_name_from_route($conn, $routeId);
  if (!$base) return null;
  $st = sqlsrv_query($conn, "SELECT TOP 1 Name FROM dbo.RouteMaster WHERE Name = ?", [$base]);
  if ($st && ($row = sqlsrv_fetch_array($st, SQLSRV_FETCH_NUMERIC))) {
    $name = (string)($row[0] ?? '');
    sqlsrv_free_stmt($st);
    return $name ?: $base;
  }
  return $base;
}
function ensure_route_activities_note_column($conn): void {
  $ddl = "
    IF COL_LENGTH('dbo.RouteActivities','Note') IS NULL
      ALTER TABLE dbo.RouteActivities ADD Note NVARCHAR(MAX) NULL;
  ";
  sqlsrv_query($conn, $ddl);
}
function db_now_expr(): string {
  return "DATEADD(HOUR, 7, SYSUTCDATETIME())";
}

/* ========= Insert RouteBatch (kompatibel) ========= */
function insert_routebatch_compat($conn, string $batchNo, string $createdBy, ?string $compCode, ?int &$outBatchId): void {
  $hasComp = false; $hasIsClosed=false; $hasLocked=false; $hasCreatedBy=false;
  $chk = sqlsrv_query($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='dbo' AND TABLE_NAME='RouteBatch'");
  while ($chk && ($r=sqlsrv_fetch_array($chk, SQLSRV_FETCH_ASSOC))) {
    $c=strtolower($r['COLUMN_NAME']);
    if($c==='compcode') $hasComp=true;
    if($c==='isclosed') $hasIsClosed=true;
    if($c==='locked') $hasLocked=true;
    if($c==='createdby') $hasCreatedBy=true;
  }
  if($chk) sqlsrv_free_stmt($chk);

  if ($hasComp && $hasIsClosed) {
    $ins = sqlsrv_query(
      $conn,
      "INSERT INTO dbo.RouteBatch (BatchNo, CompCode, CreatedAt, IsClosed, CreatedBy)
       OUTPUT INSERTED.BatchId VALUES(?, ?, ".db_now_expr().", 0, ?)",
      [$batchNo, ($compCode?:'IT1'), $createdBy]
    );
  } elseif ($hasLocked && $hasCreatedBy) {
    $ins = sqlsrv_query(
      $conn,
      "INSERT INTO dbo.RouteBatch (BatchNo, CreatedBy, Locked, CreatedAt)
       OUTPUT INSERTED.BatchId VALUES(?, ?, 0, ".db_now_expr().")",
      [$batchNo, $createdBy]
    );
  } else {
    $ins = sqlsrv_query(
      $conn,
      "INSERT INTO dbo.RouteBatch (BatchNo, CreatedAt)
       OUTPUT INSERTED.BatchId VALUES(?, ".db_now_expr().")",
      [$batchNo]
    );
  }
  if (!$ins) throw new RuntimeException(sqlerr());
  $row = sqlsrv_fetch_array($ins, SQLSRV_FETCH_ASSOC);
  sqlsrv_free_stmt($ins);
  $outBatchId = (int)($row['BatchId'] ?? 0);
  if (!$outBatchId) throw new RuntimeException('Gagal membuat BatchId');
}

/* ========= ACTIONS ========= */

if ($action === 'save_batch') {
  $routes = json_decode($_POST['routes'] ?? '[]', true);
  if (!is_array($routes) || count($routes) === 0) {
    json_out(['success'=>false,'error'=>'Invalid routes payload'], 400);
  }
  $veh = (string)($routes[0]['vehicle_plate'] ?? '');
  $drv = (string)($routes[0]['driver_name'] ?? '');
  if ($veh==='' || $drv==='') {
    json_out(['success'=>false,'error'=>'vehicle/driver required'], 400);
  }

  $schedule_date = substr((string)($_POST['schedule_date'] ?? date('Y-m-d')), 0, 10);
  $route_created_at = (string)($_POST['schedule_created_at'] ?? ($schedule_date.' 00:00:01'));
  if ($schedule_date > date('Y-m-d')) $route_created_at = $schedule_date.' 00:00:01';

  $find = sqlsrv_query(
    $conn,
    "SELECT TOP 1 b.BatchId, b.BatchNo
     FROM dbo.RouteBatch b
     JOIN dbo.Routes r ON r.BatchId=b.BatchId
     WHERE r.VehiclePlate=? AND r.DriverName=? AND r.Status <> 'done'
       AND CAST(r.RouteDate AS date)=?
     ORDER BY b.BatchId DESC",
    [$veh,$drv,$schedule_date]
  );
  if ($find === false) json_out(['success'=>false,'error'=>sqlerr()], 500);
  $exist = sqlsrv_fetch_array($find, SQLSRV_FETCH_ASSOC) ?: null;
  if($find) sqlsrv_free_stmt($find);

  if (!sqlsrv_begin_transaction($conn)) {
    json_out(['success'=>false,'error'=>'Begin transaction failed: '.sqlerr()], 500);
  }

  try {
    $mode = 'new';
    if ($exist) {
      $batchId = (int)$exist['BatchId'];
      $batchNo = (string)$exist['BatchNo'];
      $mode = 'append';
    } else {
      $prefix = 'RB'.str_replace('-','',$schedule_date).'-';
      $qmax = sqlsrv_query($conn, "SELECT TOP 1 CAST(RIGHT(BatchNo,4) AS INT) AS N FROM dbo.RouteBatch WHERE BatchNo LIKE ? ORDER BY N DESC", [$prefix.'%']);
      if ($qmax === false) throw new RuntimeException(sqlerr());
      $n = 0;
      $rw = sqlsrv_fetch_array($qmax, SQLSRV_FETCH_ASSOC);
      if ($rw && isset($rw['N'])) $n = (int)$rw['N'];
      if($qmax) sqlsrv_free_stmt($qmax);
      $batchNo = $prefix . str_pad((string)($n+1), 4, '0', STR_PAD_LEFT);

      $comp = $_SESSION['CompCode'] ?? 'IT1';
      $batchId = 0;
      insert_routebatch_compat($conn, $batchNo, $CREATED_BY, $comp, $batchId);
      $mode = 'new';
    }

    $skipStart = ($mode==='append' && (function($conn,$batchId){
      $st = sqlsrv_query($conn, "SELECT COUNT(*) AS C FROM dbo.Routes WHERE BatchId=? AND Name LIKE '%(Rute Awal)%'", [$batchId]);
      if (!$st) return false; $c=(int)(sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)['C'] ?? 0); sqlsrv_free_stmt($st); return $c>0;
    })($conn,$batchId));

    $sqlInsRoute = "INSERT INTO dbo.Routes
      (BatchId, Name, Address, VehiclePlate, DriverName, Status, RouteDate, CreatedAt)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    foreach ($routes as $r) {
      $name = (string)($r['name'] ?? '');
      if ($skipStart && stripos($name, '(Rute Awal)') !== false) continue;
      $params = [
        $batchId,
        $name,
        (string)($r['address'] ?? ''),
        (string)($r['vehicle_plate'] ?? ''),
        (string)($r['driver_name'] ?? ''),
        strtolower((string)($r['status'] ?? 'open')),
        $schedule_date,
        $route_created_at
      ];
      $ok = sqlsrv_query($conn, $sqlInsRoute, $params);
      if (!$ok) throw new RuntimeException(sqlerr());
      if($ok) sqlsrv_free_stmt($ok);
    }

    if (!sqlsrv_commit($conn)) {
      throw new RuntimeException('Commit failed: '.sqlerr());
    }
    json_out(['success'=>true,'batchNo'=>$batchNo,'batchId'=>$batchId,'mode'=>$mode]);

  } catch (Throwable $e) {
    sqlsrv_rollback($conn);
    json_out(['success'=>false,'error'=>$e->getMessage()], 500);
  }
}

if ($action === 'delete_route') {
  $routeId = (int)($_POST['routeId'] ?? 0);
  if (!$routeId) json_out(['success'=>false,'error'=>'routeId missing'], 400);

  $st = sqlsrv_query($conn, "SELECT Status, BatchId FROM dbo.Routes WHERE RouteId=?", [$routeId]);
  if (!$st) json_out(['success'=>false,'error'=>sqlerr()], 500);
  $row = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC);
  if($st) sqlsrv_free_stmt($st);
  if (!$row) json_out(['success'=>false,'error'=>'Route tidak ditemukan'], 404);

  $status  = strtolower((string)($row['Status'] ?? 'open'));
  $batchId = (int)($row['BatchId'] ?? 0);

  if (in_array($status, ['inprog','done'], true)) {
    json_out(['success'=>false,'error'=>'Route tidak dapat dihapus (status inprog/selesai)'], 400);
  }

  if (!sqlsrv_begin_transaction($conn)) {
    json_out(['success'=>false,'error'=>'Begin transaction failed: '.sqlerr()], 500);
  }

  try {
    $ok = sqlsrv_query($conn, "DELETE FROM dbo.RouteActivities WHERE RouteId=?", [$routeId]);
    if (!$ok) throw new RuntimeException(sqlerr());
    if($ok) sqlsrv_free_stmt($ok);

    $ok = sqlsrv_query($conn, "DELETE FROM dbo.Routes WHERE RouteId=?", [$routeId]);
    if (!$ok) throw new RuntimeException(sqlerr());
    if($ok) sqlsrv_free_stmt($ok);

    if ($batchId) {
      $cst = sqlsrv_query($conn, "SELECT COUNT(*) AS C FROM dbo.Routes WHERE BatchId=?", [$batchId]);
      if (!$cst) throw new RuntimeException(sqlerr());
      $c = (int)(sqlsrv_fetch_array($cst, SQLSRV_FETCH_ASSOC)['C'] ?? 0);
      if($cst) sqlsrv_free_stmt($cst);

      if ($c === 0) {
        $ok = sqlsrv_query($conn, "DELETE FROM dbo.RouteBatch WHERE BatchId=?", [$batchId]);
        if (!$ok) throw new RuntimeException(sqlerr());
        if($ok) sqlsrv_free_stmt($ok);
      }
    }

    sqlsrv_commit($conn);
    json_out(['success'=>true]);
  } catch (Throwable $e) {
    sqlsrv_rollback($conn);
    json_out(['success'=>false,'error'=>$e->getMessage()], 500);
  }
}

if ($action === 'update_status') {
  $rid = (int)($_POST['routeId'] ?? 0);
  $status = (string)($_POST['status'] ?? 'open');
  $updatedBy = (string)($_POST['updatedBy'] ?? ($_SESSION['UserName'] ?? 'web'));
  if(!$rid) json_out(['success'=>false,'error'=>'routeId missing'], 400);

  $ok = sqlsrv_query($conn, "UPDATE dbo.Routes SET Status=? WHERE RouteId=?", [$status, $rid]);
  if (!$ok) json_out(['success'=>false,'error'=>sqlerr()], 500);
  if($ok) sqlsrv_free_stmt($ok);

  set_route_updated_meta($conn, $rid, $updatedBy);

  $comment = null;
  if($status==='inprog') $comment = 'Menuju lokasi';
  elseif($status==='done') $comment = 'Selesai pengiriman';

  if ($comment) {
    $ok = sqlsrv_query(
      $conn,
      "INSERT INTO dbo.RouteActivities (RouteId, FromLocation, ToLocation, DepartTime, ArriveTime, Comment, CreatedAt)
       VALUES (?, NULL, NULL, NULL, NULL, ?, ".db_now_expr().")",
      [$rid, $comment]
    );
    if (!$ok) json_out(['success'=>false,'error'=>sqlerr()], 500);
    if($ok) sqlsrv_free_stmt($ok);
  }
  json_out(['success'=>true]);
}

if ($action === 'start_route') {
  $rid = (int)($_POST['routeId'] ?? 0);
  $updatedBy = (string)($_POST['updatedBy'] ?? ($_SESSION['UserName'] ?? 'web'));
  if(!$rid) json_out(['success'=>false,'error'=>'routeId missing'], 400);

  $name = route_master_name_for_route($conn, $rid) ?? null;

  if (!sqlsrv_begin_transaction($conn)) json_out(['success'=>false,'error'=>'Begin transaction failed: '.sqlerr()], 500);
  try {
    $ok = sqlsrv_query($conn, "UPDATE dbo.Routes SET Status='inprog' WHERE RouteId=?", [$rid]);
    if (!$ok) throw new RuntimeException(sqlerr());
    if($ok) sqlsrv_free_stmt($ok);
    set_route_updated_meta($conn, $rid, $updatedBy);

    $ok = sqlsrv_query(
      $conn,
      "INSERT INTO dbo.RouteActivities
       (RouteId, FromLocation, ToLocation, DepartTime, ArriveTime, Comment, CreatedAt)
       VALUES(?, ?, NULL, ".db_now_expr().", NULL, 'Mulai Perjalanan', ".db_now_expr().")",
      [$rid, $name]
    );
    if (!$ok) throw new RuntimeException(sqlerr());
    if($ok) sqlsrv_free_stmt($ok);

    sqlsrv_commit($conn);
    json_out(['success'=>true]);
  } catch (Throwable $e) {
    sqlsrv_rollback($conn);
    json_out(['success'=>false,'error'=>$e->getMessage()], 500);
  }
}

if ($action === 'arrive_route') {
  $rid = (int)($_POST['routeId'] ?? 0);
  $updatedBy = (string)($_POST['updatedBy'] ?? ($_SESSION['UserName'] ?? 'web'));
  if(!$rid) json_out(['success'=>false,'error'=>'routeId missing'], 400);

  $name = route_master_name_for_route($conn, $rid) ?? null;

  $ok = sqlsrv_query(
    $conn,
    "INSERT INTO dbo.RouteActivities
     (RouteId, FromLocation, ToLocation, DepartTime, ArriveTime, Comment, CreatedAt)
     VALUES(?, NULL, ?, NULL, ".db_now_expr().", 'Tiba lokasi', ".db_now_expr().")",
    [$rid, $name]
  );
  if (!$ok) json_out(['success'=>false,'error'=>sqlerr()], 500);
  if($ok) sqlsrv_free_stmt($ok);

  set_route_updated_meta($conn, $rid, $updatedBy);
  json_out(['success'=>true]);
}

if ($action === 'cancel_route') {
  $rid = (int)($_POST['routeId'] ?? 0);
  $updatedBy = (string)($_POST['updatedBy'] ?? ($_SESSION['UserName'] ?? 'web'));
  if(!$rid) json_out(['success'=>false,'error'=>'routeId missing'], 400);

  if (!sqlsrv_begin_transaction($conn)) json_out(['success'=>false,'error'=>'Begin transaction failed: '.sqlerr()], 500);
  try {
    $ok = sqlsrv_query($conn, "UPDATE dbo.Routes SET Status='open' WHERE RouteId=?", [$rid]);
    if (!$ok) throw new RuntimeException(sqlerr());
    if($ok) sqlsrv_free_stmt($ok);
    set_route_updated_meta($conn, $rid, $updatedBy);

    $ok = sqlsrv_query(
      $conn,
      "DELETE FROM dbo.RouteActivities
       WHERE RouteId=? AND Comment IN ('Mulai Perjalanan','Menuju lokasi','Tiba lokasi')",
      [$rid]
    );
    if (!$ok) throw new RuntimeException(sqlerr());
    if($ok) sqlsrv_free_stmt($ok);

    sqlsrv_commit($conn);
    json_out(['success'=>true]);
  } catch (Throwable $e) {
    sqlsrv_rollback($conn);
    json_out(['success'=>false,'error'=>$e->getMessage()], 500);
  }
}

if ($action === 'finish_route') {
  $rid = (int)($_POST['routeId'] ?? 0);
  $updatedBy = (string)($_POST['updatedBy'] ?? ($_SESSION['UserName'] ?? 'web'));
  if(!$rid) json_out(['success'=>false,'error'=>'routeId missing'], 400);

  $category = normalize_ascii((string)($_POST['category'] ?? ''));
  $qty      = normalize_ascii((string)($_POST['qty'] ?? ''));
  $noteUi   = normalize_ascii((string)($_POST['note'] ?? ''));

  $categoryDisp = ($category !== '') ? $category : '-';
  $qtyDisp      = ($qty !== '') ? $qty : '-';
  $noteDisp     = ($noteUi !== '') ? $noteUi : '-';

  $comment  = "Selesai pengiriman - Kategori: {$categoryDisp} - Qty: {$qtyDisp} - Note: {$noteDisp}";

  if (!sqlsrv_begin_transaction($conn)) {
    json_out(['success'=>false,'error'=>'Begin transaction failed: '.sqlerr()], 500);
  }
  try {
    $ok = sqlsrv_query($conn, "UPDATE dbo.Routes SET Status='done' WHERE RouteId=?", [$rid]);
    if (!$ok) throw new RuntimeException(sqlerr());
    if($ok) sqlsrv_free_stmt($ok);
    set_route_updated_meta($conn, $rid, $updatedBy);

    $ok = sqlsrv_query(
      $conn,
      "INSERT INTO dbo.RouteActivities
       (RouteId, FromLocation, ToLocation, DepartTime, ArriveTime, Comment, Note, CreatedAt)
       VALUES(?, NULL, NULL, NULL, NULL, ?, NULL, ".db_now_expr().")",
      [$rid, $comment]
    );
    if (!$ok) throw new RuntimeException(sqlerr());
    if($ok) sqlsrv_free_stmt($ok);

    sqlsrv_commit($conn);
    json_out(['success'=>true]);
  } catch (Throwable $e) {
    sqlsrv_rollback($conn);
    json_out(['success'=>false,'error'=>$e->getMessage()], 500);
  }
}

if ($action === 'start_full' || $action === 'finish_full') {
  $batchId = (int)($_POST['batchId'] ?? 0);
  if(!$batchId) json_out(['success'=>false,'error'=>'batchId missing'], 400);

  $q = sqlsrv_query(
    $conn,
    "SELECT TOP 1 RouteId
     FROM dbo.Routes
     WHERE BatchId = ?
     ORDER BY CASE WHEN CHARINDEX('(Rute Awal)', Name) > 0 THEN 0 ELSE 1 END, RouteId ASC",
    [$batchId]
  );
  if (!$q) json_out(['success'=>false,'error'=>sqlerr()], 500);
  $rid = (int)(sqlsrv_fetch_array($q, SQLSRV_FETCH_NUMERIC)[0] ?? 0);
  if($q) sqlsrv_free_stmt($q);
  if(!$rid) json_out(['success'=>false,'error'=>'Rute awal tidak ditemukan'], 404);

  $rmName = route_master_name_for_route($conn, $rid) ?? null;

  if ($action === 'start_full') {
    $ok = sqlsrv_query(
      $conn,
      "INSERT INTO dbo.RouteActivities
       (RouteId, FromLocation, ToLocation, DepartTime, ArriveTime, Comment, CreatedAt)
       VALUES(?, ?, NULL, ".db_now_expr().", NULL, 'Menuju FULL', ".db_now_expr().")",
      [$rid, $rmName]
    );
    if (!$ok) json_out(['success'=>false,'error'=>sqlerr()], 500);
    if($ok) sqlsrv_free_stmt($ok);

    $ts = date('Y-m-d H:i:s');
    json_out(['success'=>true,'startedAt'=>$ts,'routeId'=>$rid]);
  } else {
    $ok = sqlsrv_query(
      $conn,
      "INSERT INTO dbo.RouteActivities
       (RouteId, FromLocation, ToLocation, DepartTime, ArriveTime, Comment, CreatedAt)
       VALUES(?, NULL, ?, NULL, ".db_now_expr().", 'Tiba Di FULL', ".db_now_expr().")",
      [$rid, $rmName]
    );
    if (!$ok) json_out(['success'=>false,'error'=>sqlerr()], 500);
    if($ok) sqlsrv_free_stmt($ok);

    $ts = date('Y-m-d H:i:s');
    json_out(['success'=>true,'finishedAt'=>$ts,'routeId'=>$rid]);
  }
}

/* ======== Inline note dari report.php ======== */
if ($action === 'save_inline_note') {
  ensure_route_activities_note_column($conn);

  $activityId = (int)($_POST['activity_id'] ?? 0);
  $noteTxt    = normalize_ascii((string)($_POST['note'] ?? ''));

  if (!$activityId) json_out(['success'=>false,'error'=>'activity_id missing'], 400);

  $st = sqlsrv_query($conn, "SELECT Comment FROM dbo.RouteActivities WHERE ActivityId=?", [$activityId]);
  if (!$st) json_out(['success'=>false,'error'=>sqlerr()], 500);
  $cmt = sqlsrv_fetch_array($st, SQLSRV_FETCH_NUMERIC)[0] ?? '';
  if($st) sqlsrv_free_stmt($st);

  if (stripos((string)$cmt, 'Selesai pengiriman') !== 0) {
    json_out(['success'=>false,'error'=>'Hanya bisa menyimpan Note untuk baris "Selesai pengiriman"'], 400);
  }

  $ok = sqlsrv_query($conn, "UPDATE dbo.RouteActivities SET Note=? WHERE ActivityId=?", [($noteTxt!==''?$noteTxt:null), $activityId]);
  if (!$ok) json_out(['success'=>false,'error'=>sqlerr()], 500);
  if($ok) sqlsrv_free_stmt($ok);

  json_out(['success'=>true]);
}

/* ======== Auto-close overdue (lengkap + jejak activities) ======== */
if ($action === 'autoclose_overdue') {
  ensure_route_activities_note_column($conn);

  $today = date('Y-m-d');
  $closedIds = [];
  $touchedBatches = [];

  // Ambil rute yang belum selesai (OPEN/INPROG) dan tanggalnya < hari ini
  // + ikutkan CreatedAt dari RouteBatch sebagai basis tanggal (HH:MM → 23:59)
  $q = sqlsrv_query(
    $conn,
    "SELECT
       r.RouteId,
       r.BatchId,
       CONVERT(varchar(10), r.RouteDate, 23) AS RouteDate,
       b.CreatedAt AS BatchCreatedAt
     FROM dbo.Routes r
     LEFT JOIN dbo.RouteBatch b ON b.BatchId = r.BatchId
     WHERE r.Status IN ('open','inprog') AND CAST(r.RouteDate AS date) < ?",
    [$today]
  );
  if ($q === false) json_out(['success'=>false,'error'=>sqlerr()], 500);

  $targets = [];
  while ($r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
    // Normalisasi RouteDate
    $routeDateVal = $r['RouteDate'];
    $routeDateStr = ($routeDateVal instanceof DateTimeInterface)
      ? $routeDateVal->format('Y-m-d')
      : substr((string)$routeDateVal, 0, 10);

    // Normalisasi BatchCreatedAt (bisa datetime/txt/null)
    $bca = $r['BatchCreatedAt'] ?? null;
    $batchDateStr = null;
    if ($bca instanceof DateTimeInterface) {
      $batchDateStr = $bca->format('Y-m-d');
    } else if (is_string($bca) && $bca !== '') {
      $batchDateStr = substr($bca, 0, 10);
    }

    // Basis tanggal: utamakan tanggal dari RouteBatch.CreatedAt; fallback RouteDate
    $baseDate = $batchDateStr ?: $routeDateStr;
    if (!$baseDate) $baseDate = date('Y-m-d');

    // Semua jejak dipasang di jam 23:59 pada baseDate
    $ts2359 = $baseDate . ' 23:59:59';

    $targets[] = [
      'id'        => (int)$r['RouteId'],
      'batchId'   => (int)$r['BatchId'],
      'baseDate'  => $baseDate,
      'at2359'    => $ts2359
    ];
  }
  if ($q) sqlsrv_free_stmt($q);

  if (!$targets) json_out(['success'=>true, 'closedIds'=>[]]);

  // helpers
  // FIX: deteksi keberadaan baris harus pakai sqlsrv_fetch($st) === true (bukan !== false)
  $exists = function(int $rid, string $comment, bool $prefix=false) use ($conn): bool {
    $sql = $prefix
      ? "SELECT TOP 1 1 FROM dbo.RouteActivities WHERE RouteId=? AND Comment LIKE ?"
      : "SELECT TOP 1 1 FROM dbo.RouteActivities WHERE RouteId=? AND Comment=?";
    $params = $prefix ? [$rid, $comment.'%'] : [$rid, $comment];

    $st = sqlsrv_query($conn, $sql, $params);
    if (!$st) return false;

    $has = (sqlsrv_fetch($st) === true); // true hanya jika ada baris
    sqlsrv_free_stmt($st);
    return $has;
  };
  $insAct = function(
      int $rid, ?string $from, ?string $to, $depart, $arrive,
      string $comment, string $createdAt, ?string $note = null
    ) use ($conn): void {
    $ok = sqlsrv_query(
      $conn,
      "INSERT INTO dbo.RouteActivities
       (RouteId, FromLocation, ToLocation, DepartTime, ArriveTime, Comment, Note, CreatedAt)
       VALUES(?, ?, ?, ?, ?, ?, ?, ?)",
      [$rid, $from, $to, $depart, $arrive, $comment, $note, $createdAt]
    );
    if (!$ok) throw new RuntimeException(sqlerr());
    if ($ok) sqlsrv_free_stmt($ok);
  };

  if (!sqlsrv_begin_transaction($conn)) {
    json_out(['success'=>false,'error'=>'Begin transaction failed: '.sqlerr()], 500);
  }

  try {
    foreach ($targets as $t) {
      $rid     = $t['id'];
      $bid     = $t['batchId'];
      $created = $t['at2359'];
      $rmName  = route_master_name_for_route($conn, $rid) ?? null;

      // 1) Mulai Perjalanan (pakai DepartTime 23:59)
      if (!$exists($rid, 'Mulai Perjalanan')) {
        $insAct($rid, $rmName, null, $created, null, 'Mulai Perjalanan', $created, null);
      }
      // 2) Menuju lokasi
      if (!$exists($rid, 'Menuju lokasi')) {
        $insAct($rid, null, $rmName, null, null, 'Menuju lokasi', $created, null);
      }
      // 3) Tiba lokasi (pakai ArriveTime 23:59)
      if (!$exists($rid, 'Tiba lokasi')) {
        $insAct($rid, null, $rmName, null, $created, 'Tiba lokasi', $created, null);
      }
      // 4) Selesai pengiriman — Note = automatic_close (prefix check)
      if (!$exists($rid, 'Selesai pengiriman', true)) {
        $comment = "Selesai pengiriman - Kategori: - - Qty: - - Note: automatic_close";
        $insAct($rid, null, null, null, null, $comment, $created, 'automatic_close');
      }

      // Update status route -> done
      $ok = sqlsrv_query($conn, "UPDATE dbo.Routes SET Status='done' WHERE RouteId=?", [$rid]);
      if (!$ok) throw new RuntimeException(sqlerr());
      if ($ok) sqlsrv_free_stmt($ok);
      set_route_updated_meta($conn, $rid, 'automatic_close');

      $closedIds[] = $rid;
      $touchedBatches[$bid] = true;
    }

    // Jejak FULL di rute awal tiap batch (kalau belum ada)
    foreach (array_keys($touchedBatches) as $bid) {
      $st = sqlsrv_query(
        $conn,
        "SELECT TOP 1 r.RouteId,
                CONVERT(varchar(10), COALESCE(b.CreatedAt, r.RouteDate), 23) AS BaseDate
         FROM dbo.Routes r
         LEFT JOIN dbo.RouteBatch b ON b.BatchId = r.BatchId
         WHERE r.BatchId=?
         ORDER BY CASE WHEN CHARINDEX('(Rute Awal)', r.Name) > 0 THEN 0 ELSE 1 END, r.RouteId ASC",
        [$bid]
      );
      if ($st && ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC))) {
        $ridStart = (int)$r['RouteId'];
        $d        = (string)($r['BaseDate'] ?? date('Y-m-d'));
        $ts       = $d . ' 23:59:59';
        $rmStart  = route_master_name_for_route($conn, $ridStart) ?? null;

        if (!$exists($ridStart, 'Menuju FULL'))   { $insAct($ridStart, $rmStart, null, $ts,   null, 'Menuju FULL',   $ts, null); }
        if (!$exists($ridStart, 'Tiba Di FULL'))  { $insAct($ridStart, null, $rmStart, null, $ts,  'Tiba Di FULL',  $ts, null); }
      }
      if ($st) sqlsrv_free_stmt($st);
    }

    if (!sqlsrv_commit($conn)) throw new RuntimeException('Commit failed: '.sqlerr());
    json_out(['success'=>true,'closedIds'=>$closedIds]);

  } catch (Throwable $e) {
    sqlsrv_rollback($conn);
    json_out(['success'=>false,'error'=>'Auto-close gagal: '.$e->getMessage()], 500);
  }
}

json_out(['success'=>false,'error'=>'Unknown action'], 400);
