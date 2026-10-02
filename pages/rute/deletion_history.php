<?php
declare(strict_types=1);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  header('Location: hapus.php');
  exit;
}

function dh_sqlerr(): string {
  $es = sqlsrv_errors(SQLSRV_ERR_ERRORS);
  if (!$es) return 'Unknown database error';
  $msgs = array_map(fn($e) => "[{$e['SQLSTATE']}] {$e['code']} {$e['message']}", $es);
  return implode(' | ', $msgs);
}

function dh_now_expr(): string {
  return "DATEADD(HOUR, 7, SYSUTCDATETIME())";
}

function dh_current_user(): string {
  return (string)($_SESSION['UserName'] ?? 'System');
}

function dh_is_it1(): bool {
  return strtoupper(dh_current_user()) === 'IT1';
}

function dh_json_value($v) {
  if ($v instanceof DateTimeInterface) return $v->format('Y-m-d H:i:s');
  if (is_resource($v)) return null;
  return $v;
}

function dh_json_row(array $row): string {
  $out = [];
  foreach ($row as $k => $v) $out[$k] = dh_json_value($v);
  return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function dh_new_delete_group_id(): string {
  try {
    return bin2hex(random_bytes(16));
  } catch (Throwable $e) {
    return str_replace('.', '', uniqid('', true));
  }
}

function ensure_hapus_table($conn): void {
  $sql = "
    IF OBJECT_ID('dbo.hapus', 'U') IS NULL
    BEGIN
      CREATE TABLE dbo.hapus (
        HapusId INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        SourceTable NVARCHAR(128) NOT NULL,
        SourcePk NVARCHAR(128) NOT NULL,
        SourcePkValue NVARCHAR(100) NOT NULL,
        DataJson NVARCHAR(MAX) NOT NULL,
        DeletedBy NVARCHAR(100) NOT NULL,
        DeletedAt DATETIME2 NOT NULL DEFAULT ".dh_now_expr().",
        RestoredBy NVARCHAR(100) NULL,
        RestoredAt DATETIME2 NULL,
        Status NVARCHAR(20) NOT NULL DEFAULT 'deleted',
        DeleteGroupId NVARCHAR(64) NULL
      );
    END
    IF COL_LENGTH('dbo.hapus','DeleteGroupId') IS NULL
      ALTER TABLE dbo.hapus ADD DeleteGroupId NVARCHAR(64) NULL;
    IF NOT EXISTS (
      SELECT 1 FROM sys.indexes
      WHERE name = 'IX_hapus_Status_DeletedAt' AND object_id = OBJECT_ID('dbo.hapus')
    )
      CREATE INDEX IX_hapus_Status_DeletedAt ON dbo.hapus(Status, DeletedAt DESC);
  ";
  $st = sqlsrv_query($conn, $sql);
  if ($st === false) throw new RuntimeException(dh_sqlerr());
  sqlsrv_free_stmt($st);
}

function log_deleted_row($conn, string $table, string $pkCol, $pkValue, ?string $deletedBy = null, ?string $deleteGroupId = null): void {
  ensure_hapus_table($conn);
  $sql = "SELECT * FROM dbo.".$table." WHERE ".$pkCol." = ?";
  $st = sqlsrv_query($conn, $sql, [$pkValue]);
  if ($st === false) throw new RuntimeException(dh_sqlerr());

  $deletedBy = $deletedBy ?: dh_current_user();
  $deleteGroupId = $deleteGroupId ?: dh_new_delete_group_id();
  while ($row = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
    $json = dh_json_row($row);
    $ins = sqlsrv_query(
      $conn,
      "INSERT INTO dbo.hapus (SourceTable, SourcePk, SourcePkValue, DataJson, DeletedBy, DeletedAt, Status, DeleteGroupId)
       VALUES (?, ?, ?, ?, ?, ".dh_now_expr().", 'deleted', ?)",
      [$table, $pkCol, (string)$pkValue, $json, $deletedBy, $deleteGroupId]
    );
    if ($ins === false) throw new RuntimeException(dh_sqlerr());
    sqlsrv_free_stmt($ins);
  }
  sqlsrv_free_stmt($st);
}

function log_deleted_rows_by_where($conn, string $table, string $whereSql, array $params, string $pkCol, ?string $deletedBy = null, ?string $deleteGroupId = null): void {
  ensure_hapus_table($conn);
  $st = sqlsrv_query($conn, "SELECT * FROM dbo.".$table." WHERE ".$whereSql, $params);
  if ($st === false) throw new RuntimeException(dh_sqlerr());

  $deletedBy = $deletedBy ?: dh_current_user();
  $deleteGroupId = $deleteGroupId ?: dh_new_delete_group_id();
  while ($row = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
    $pkValue = $row[$pkCol] ?? '';
    $ins = sqlsrv_query(
      $conn,
      "INSERT INTO dbo.hapus (SourceTable, SourcePk, SourcePkValue, DataJson, DeletedBy, DeletedAt, Status, DeleteGroupId)
       VALUES (?, ?, ?, ?, ?, ".dh_now_expr().", 'deleted', ?)",
      [$table, $pkCol, (string)dh_json_value($pkValue), dh_json_row($row), $deletedBy, $deleteGroupId]
    );
    if ($ins === false) throw new RuntimeException(dh_sqlerr());
    sqlsrv_free_stmt($ins);
  }
  sqlsrv_free_stmt($st);
}

function dh_table_columns($conn, string $table): array {
  $st = sqlsrv_query(
    $conn,
    "SELECT COLUMN_NAME
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA='dbo' AND TABLE_NAME=?
     ORDER BY ORDINAL_POSITION",
    [$table]
  );
  if ($st === false) throw new RuntimeException(dh_sqlerr());
  $cols = [];
  while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) $cols[] = (string)$r['COLUMN_NAME'];
  sqlsrv_free_stmt($st);
  return $cols;
}

function dh_has_identity($conn, string $table, string $col): bool {
  $st = sqlsrv_query($conn, "SELECT COLUMNPROPERTY(OBJECT_ID(?), ?, 'IsIdentity') AS IsIdentity", ['dbo.'.$table, $col]);
  if ($st === false) return false;
  $row = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC) ?: [];
  sqlsrv_free_stmt($st);
  return (int)($row['IsIdentity'] ?? 0) === 1;
}

function restore_hapus_row($conn, int $hapusId, string $restoredBy): void {
  ensure_hapus_table($conn);
  $st = sqlsrv_query($conn, "SELECT * FROM dbo.hapus WHERE HapusId=? AND Status='deleted'", [$hapusId]);
  if ($st === false) throw new RuntimeException(dh_sqlerr());
  $hist = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC);
  sqlsrv_free_stmt($st);
  if (!$hist) throw new RuntimeException('Data history tidak ditemukan atau sudah di-rollback.');

  $table = (string)$hist['SourceTable'];
  $pkCol = (string)$hist['SourcePk'];
  if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $pkCol)) {
    throw new RuntimeException('Nama tabel history tidak valid.');
  }

  $data = json_decode((string)$hist['DataJson'], true);
  if (!is_array($data)) throw new RuntimeException('Data JSON history rusak.');

  $cols = dh_table_columns($conn, $table);
  $insertData = [];
  foreach ($cols as $col) {
    if (array_key_exists($col, $data)) $insertData[$col] = $data[$col];
  }
  if (!$insertData) throw new RuntimeException('Tidak ada kolom yang bisa direstore.');

  $pkVal = $insertData[$pkCol] ?? $hist['SourcePkValue'];
  $exists = sqlsrv_query($conn, "SELECT TOP 1 1 FROM dbo.".$table." WHERE ".$pkCol."=?", [$pkVal]);
  if ($exists === false) throw new RuntimeException(dh_sqlerr());
  $already = sqlsrv_fetch($exists) === true;
  sqlsrv_free_stmt($exists);
  if ($already) throw new RuntimeException('Data sudah ada di tabel asli, rollback dibatalkan.');

  $identityOn = dh_has_identity($conn, $table, $pkCol);
  $names = array_keys($insertData);
  $placeholders = implode(', ', array_fill(0, count($names), '?'));
  $colSql = implode(', ', array_map(fn($c) => '['.$c.']', $names));
  $params = array_values($insertData);

  if ($identityOn) {
    $on = sqlsrv_query($conn, "SET IDENTITY_INSERT dbo.".$table." ON");
    if ($on === false) throw new RuntimeException(dh_sqlerr());
    sqlsrv_free_stmt($on);
  }

  try {
    $ins = sqlsrv_query($conn, "INSERT INTO dbo.".$table." (".$colSql.") VALUES (".$placeholders.")", $params);
    if ($ins === false) throw new RuntimeException(dh_sqlerr());
    sqlsrv_free_stmt($ins);
  } finally {
    if ($identityOn) {
      $off = sqlsrv_query($conn, "SET IDENTITY_INSERT dbo.".$table." OFF");
      if ($off) sqlsrv_free_stmt($off);
    }
  }

  $upd = sqlsrv_query(
    $conn,
    "UPDATE dbo.hapus SET Status='restored', RestoredBy=?, RestoredAt=".dh_now_expr()." WHERE HapusId=?",
    [$restoredBy, $hapusId]
  );
  if ($upd === false) throw new RuntimeException(dh_sqlerr());
  sqlsrv_free_stmt($upd);
}

function dh_restore_priority(string $table): int {
  if ($table === 'RouteBatch') return 10;
  if ($table === 'RouteMaster' || $table === 'Vehicles' || $table === 'Routes') return 20;
  if ($table === 'RouteActivities') return 30;
  return 50;
}

function restore_hapus_rows($conn, array $hapusIds, string $restoredBy): void {
  $hapusIds = array_values(array_unique(array_filter(array_map('intval', $hapusIds), fn($id) => $id > 0)));
  if (!$hapusIds) throw new RuntimeException('ID history tidak valid.');

  $items = [];
  foreach ($hapusIds as $id) {
    $st = sqlsrv_query($conn, "SELECT HapusId, SourceTable FROM dbo.hapus WHERE HapusId=? AND Status='deleted'", [$id]);
    if ($st === false) throw new RuntimeException(dh_sqlerr());
    if ($row = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) $items[] = $row;
    sqlsrv_free_stmt($st);
  }
  if (!$items) throw new RuntimeException('Data history tidak ditemukan atau sudah di-rollback.');

  usort($items, function($a, $b) {
    $pa = dh_restore_priority((string)$a['SourceTable']);
    $pb = dh_restore_priority((string)$b['SourceTable']);
    if ($pa === $pb) return ((int)$a['HapusId']) <=> ((int)$b['HapusId']);
    return $pa <=> $pb;
  });

  foreach ($items as $item) {
    restore_hapus_row($conn, (int)$item['HapusId'], $restoredBy);
  }
}
