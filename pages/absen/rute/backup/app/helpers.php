<?php
// app/helpers.php
declare(strict_types=1);

/** Hitung status batch berdasarkan isi tabel Routes */
function batch_status(PDO $pdo, int $batchId): string {
  $row = $pdo->query("
    SELECT
      SUM(CASE WHEN LOWER(Status)='open' THEN 1 ELSE 0 END) AS openCnt,
      SUM(CASE WHEN LOWER(Status)='inprog' THEN 1 ELSE 0 END) AS inCnt,
      SUM(CASE WHEN LOWER(Status)='done' THEN 1 ELSE 0 END) AS doneCnt,
      COUNT(*) AS totalCnt
    FROM dbo.Routes WHERE BatchId={$batchId}
  ")->fetch();

  $open = (int)$row['openCnt']; $in = (int)$row['inCnt']; $done = (int)$row['doneCnt']; $tot = (int)$row['totalCnt'];

  if ($tot>0 && $done === $tot) return 'done';
  if ($in>0 || ($open>0 && $done>0)) return 'inprog'; // campuran open+done juga inprog
  return 'open';
}

/** Ambil batchId terakhir utk pairing (plate, driver) */
function last_batch_for_pair(PDO $pdo, string $plate, string $driver): ?int {
  $q = $pdo->prepare("SELECT TOP 1 BatchId FROM dbo.Routes WHERE VehiclePlate=? AND DriverName=? ORDER BY BatchId DESC");
  $q->execute([$plate,$driver]);
  $id = $q->fetchColumn();
  return $id ? (int)$id : null;
}

/** Sudah punya rute awal di batch tsb? */
function batch_has_start_route(PDO $pdo, int $batchId): bool {
  $q = $pdo->prepare("SELECT COUNT(*) FROM dbo.Routes WHERE BatchId=? AND Name LIKE '%(Rute Awal)%'");
  $q->execute([$batchId]);
  return ((int)$q->fetchColumn()) > 0;
}

/** Ambil nama rute awal (tanpa embel) pada batch (untuk auto-fill) */
function batch_start_base_name(PDO $pdo, int $batchId): ?string {
  $q = $pdo->prepare("SELECT TOP 1 Name FROM dbo.Routes WHERE BatchId=? AND Name LIKE '%(Rute Awal)%' ORDER BY RouteId ASC");
  $q->execute([$batchId]);
  $name = $q->fetchColumn();
  if(!$name) return null;
  return trim(str_replace('(Rute Awal)', '', $name));
}
/* ====== SAVE TEMUAN / KOMENTAR LAPORAN ====== */
if ($action === 'save_finding_detail') {
  $drv  = trim((string)($_POST['driver'] ?? ''));
  $st   = trim((string)($_POST['start_time'] ?? '')); // 'YYYY-MM-DD HH:MM:SS'
  $en   = trim((string)($_POST['end_time'] ?? ''));
  $from = trim((string)($_POST['from_text'] ?? ''));
  $to   = trim((string)($_POST['to_text'] ?? ''));
  $note = (string)($_POST['note'] ?? '');

  if ($drv==='' || $st==='') {
    json_out(['success'=>false,'error'=>'driver & start_time wajib diisi']);
  }

  // pastikan tabel ada (aman kalau sudah ada)
  $pdo->exec("
    IF NOT EXISTS (SELECT * FROM sys.objects WHERE name = 'ReportFindingsDetail' AND type = 'U')
    BEGIN
      CREATE TABLE dbo.ReportFindingsDetail(
        Id INT IDENTITY(1,1) PRIMARY KEY,
        DriverName NVARCHAR(100) NOT NULL,
        StartTime  DATETIME2      NOT NULL,
        EndTime    DATETIME2      NULL,
        FromText   NVARCHAR(500)  NULL,
        ToText     NVARCHAR(500)  NULL,
        Note       NVARCHAR(MAX)  NULL,
        CreatedAt  DATETIME2      NOT NULL DEFAULT SYSUTCDATETIME(),
        UpdatedAt  DATETIME2      NULL
      );
    END
    IF NOT EXISTS (
      SELECT * FROM sys.indexes
      WHERE name = 'UX_ReportFindingsDetail_Driver_Start_End'
        AND object_id = OBJECT_ID('dbo.ReportFindingsDetail')
    )
    BEGIN
      CREATE UNIQUE INDEX UX_ReportFindingsDetail_Driver_Start_End
        ON dbo.ReportFindingsDetail(DriverName, StartTime, EndTime);
    END
  ");

  // Upsert (UPDATE jika ada; jika tidak INSERT)
  $pdo->beginTransaction();
  try {
    $upd = $pdo->prepare("
      UPDATE dbo.ReportFindingsDetail
         SET FromText = :from, ToText = :to, Note = :note, UpdatedAt = SYSUTCDATETIME()
       WHERE DriverName = :drv AND StartTime = :st AND (EndTime = :en OR (:en IS NULL AND EndTime IS NULL))
    ");
    $upd->execute([':from'=>$from, ':to'=>$to, ':note'=>$note, ':drv'=>$drv, ':st'=>$st, ':en'=>$en !== '' ? $en : null]);

    if ($upd->rowCount() === 0) {
      $ins = $pdo->prepare("
        INSERT INTO dbo.ReportFindingsDetail (DriverName, StartTime, EndTime, FromText, ToText, Note)
        VALUES (:drv, :st, :en, :from, :to, :note)
      ");
      $ins->execute([':drv'=>$drv, ':st'=>$st, ':en'=>$en !== '' ? $en : null, ':from'=>$from, ':to'=>$to, ':note'=>$note]);
    }

    $pdo->commit();
    json_out(['success'=>true]);
  } catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_out(['success'=>false,'error'=>$e->getMessage()]);
  }
}
