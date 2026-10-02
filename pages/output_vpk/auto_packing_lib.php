<?php

declare(strict_types=1);

const OUTPUT_VPK_AUTO_MENU_ID = 67;

/** Return safe random ID for worker and request failure correlation. */
function outputVpkAutoOperationId(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
}

/** Validate one ISO date without accepting alternate formats. */
function outputVpkAutoDate(string $date): string
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException('Tanggal tidak valid.');
    }
    return $date;
}

/** Require a logged-in Output Packing editor for browser write actions. */
function outputVpkAutoRequireEditor($conn): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $username = trim((string)($_SESSION['UserName'] ?? ''));
    $groupId = (int)($_SESSION['GroupId'] ?? 0);
    if ($username === '' || $groupId <= 0) {
        throw new RuntimeException('Sesi login berakhir.');
    }
    $statement = sqlsrv_query($conn, 'SELECT CanEdit FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?', [$groupId, OUTPUT_VPK_AUTO_MENU_ID]);
    $permission = $statement ? sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC) : null;
    if ($statement !== false) {
        sqlsrv_free_stmt($statement);
    }
    if (!$permission || (int)$permission['CanEdit'] !== 1) {
        throw new RuntimeException('Anda tidak memiliki hak mengubah otomatisasi Output Packing.');
    }
    return ['username' => $username, 'group_id' => $groupId];
}

/** Return the dedicated CSRF token used by Output Packing automation forms. */
function outputVpkAutoCsrfToken(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!isset($_SESSION['output_vpk_auto_csrf']) || !is_string($_SESSION['output_vpk_auto_csrf'])) {
        $_SESSION['output_vpk_auto_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['output_vpk_auto_csrf'];
}

/** Reject a browser write action whose CSRF token does not match. */
function outputVpkAutoRequireCsrf(): void
{
    $provided = (string)($_POST['csrf_token'] ?? '');
    if ($provided === '' || !hash_equals(outputVpkAutoCsrfToken(), $provided)) {
        throw new RuntimeException('Token keamanan tidak valid. Muat ulang halaman.');
    }
}

/** Issue a short-lived server-side approval after previewing one target date. */
function outputVpkAutoPreviewToken(string $targetDate): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $token = bin2hex(random_bytes(32));
    $_SESSION['output_vpk_auto_preview'] = [
        'token' => $token,
        'target_date' => $targetDate,
        'expires_at' => time() + 300,
    ];
    return $token;
}

/** Require an unexpired preview approval for the exact manual snapshot date. */
function outputVpkAutoRequirePreviewToken(string $targetDate, string $token): void
{
    $preview = $_SESSION['output_vpk_auto_preview'] ?? null;
    unset($_SESSION['output_vpk_auto_preview']);
    if (!is_array($preview)
        || !isset($preview['token'], $preview['target_date'], $preview['expires_at'])
        || !is_string($preview['token'])
        || !hash_equals($preview['token'], $token)
        || $preview['target_date'] !== $targetDate
        || (int)$preview['expires_at'] < time()) {
        throw new RuntimeException('Preview sudah tidak berlaku. Preview data kembali sebelum menyimpan.');
    }
}

/** Load singleton configuration and the newest run status. */
function outputVpkAutoStatus($conn): array
{
    $config = sqlsrv_query($conn, 'SELECT config_id, is_enabled, run_time, updated_at, updated_by FROM dbo.output_vpk_auto_config WHERE config_id = 1');
    $configRow = $config ? sqlsrv_fetch_array($config, SQLSRV_FETCH_ASSOC) : null;
    if ($config !== false) {
        sqlsrv_free_stmt($config);
    }
    if (!$configRow) {
        throw new RuntimeException('Konfigurasi otomatisasi belum tersedia.');
    }
    $run = sqlsrv_query($conn, 'SELECT TOP 1 target_date, run_status, attempted_at, completed_at, qty, qty_a1, message, triggered_by FROM dbo.output_vpk_auto_run ORDER BY attempted_at DESC');
    $runRow = $run ? sqlsrv_fetch_array($run, SQLSRV_FETCH_ASSOC) : null;
    if ($run !== false) {
        sqlsrv_free_stmt($run);
    }
    return ['config' => $configRow, 'last_run' => $runRow];
}

/** Save validated schedule values. */
function outputVpkAutoSaveConfig($conn, bool $enabled, string $runTime, string $username): void
{
    if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $runTime)) {
        throw new InvalidArgumentException('Jam harus berformat HH:MM.');
    }
    $statement = sqlsrv_query($conn, 'UPDATE dbo.output_vpk_auto_config SET is_enabled = ?, run_time = ?, updated_at = SYSDATETIME(), updated_by = ? WHERE config_id = 1', [$enabled ? 1 : 0, $runTime . ':00', $username]);
    if ($statement === false) {
        throw new RuntimeException('Konfigurasi otomatisasi gagal disimpan.');
    }
    sqlsrv_free_stmt($statement);
}

/** Ask the existing Susut library for the exact one-day summary used by the report. */
function outputVpkAutoFetchSusut(PDO $conn3, string $targetDate): ?array
{
    require_once __DIR__ . '/hasil_produksi_susut_data.php';
    $result = hasilProduksiSusutFetchSummaryByDate($conn3, $targetDate, $targetDate);
    if (!empty($result['errors'])) {
        throw new RuntimeException('Tarikan Susut Verpacking tidak dapat dibaca.');
    }
    $row = $result['rows'][0] ?? null;
    if (!$row) {
        return null;
    }
    return ['qty' => (float)($row['greige_meter'] ?? 0), 'qty_a1' => (float)($row['total_a1_meter'] ?? 0)];
}

/** Write terminal run state safely; status table has one record per target date. */
function outputVpkAutoRecordRun($conn, string $targetDate, string $status, string $operationId, ?array $summary, string $message, string $triggeredBy): void
{
    $statement = sqlsrv_query($conn, 'MERGE dbo.output_vpk_auto_run WITH (HOLDLOCK) AS target USING (SELECT CAST(? AS DATE) AS target_date) AS source ON target.target_date = source.target_date WHEN MATCHED AND target.run_status = \'failed\' THEN UPDATE SET run_status = ?, attempted_at = SYSDATETIME(), completed_at = SYSDATETIME(), operation_id = ?, qty = ?, qty_a1 = ?, message = ?, triggered_by = ? WHEN NOT MATCHED THEN INSERT (target_date, run_status, completed_at, operation_id, qty, qty_a1, message, triggered_by) VALUES (source.target_date, ?, SYSDATETIME(), ?, ?, ?, ?, ?) ;', [$targetDate, $status, $operationId, $summary['qty'] ?? null, $summary['qty_a1'] ?? null, $message === '' ? null : mb_substr($message, 0, 250), $triggeredBy, $status, $operationId, $summary['qty'] ?? null, $summary['qty_a1'] ?? null, $message === '' ? null : mb_substr($message, 0, 250), $triggeredBy]);
    if ($statement === false) {
        throw new RuntimeException('Status proses otomatisasi gagal disimpan.');
    }
    sqlsrv_free_stmt($statement);
}

/** Append only sanitized failures; retention prevents indefinite log growth. */
function outputVpkAutoLogFailure(string $operationId, string $action, string $message): void
{
    $directory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
        return;
    }
    foreach (glob($directory . DIRECTORY_SEPARATOR . 'error-*.log') ?: [] as $file) {
        if (@filemtime($file) < strtotime('-7 days')) {
            @unlink($file);
        }
    }
    $entry = ['timestamp' => date(DATE_ATOM), 'severity' => 'ERROR', 'operation_id' => $operationId, 'module' => 'output_vpk', 'action' => $action, 'message' => mb_substr(preg_replace('/\s+/', ' ', $message), 0, 300)];
    @file_put_contents($directory . DIRECTORY_SEPARATOR . 'error-' . date('Y-m-d') . '.log', json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/** Create one snapshot only when no existing Output Packing row or terminal run exists. */
function outputVpkAutoPull($conn, PDO $conn3, string $targetDate, string $triggeredBy, bool $recordEmpty = true): array
{
    $targetDate = outputVpkAutoDate($targetDate);
    $operationId = outputVpkAutoOperationId();
    $lock = sqlsrv_query($conn, "DECLARE @result INT; EXEC @result = sp_getapplock @Resource = 'output_vpk_auto_snapshot', @LockMode = 'Exclusive', @LockOwner = 'Session', @LockTimeout = 10000; SELECT @result AS lock_result;");
    $lockRow = $lock ? sqlsrv_fetch_array($lock, SQLSRV_FETCH_ASSOC) : null;
    if ($lock !== false) { sqlsrv_free_stmt($lock); }
    if (!$lockRow || (int)$lockRow['lock_result'] < 0) {
        throw new RuntimeException('Proses otomatisasi sedang berjalan.');
    }
    try {
        $existing = sqlsrv_query($conn, 'SELECT TOP 1 id FROM dbo.packing_output WHERE tanggal = ?', [$targetDate]);
        $existingRow = $existing ? sqlsrv_fetch_array($existing, SQLSRV_FETCH_ASSOC) : null;
        if ($existing !== false) { sqlsrv_free_stmt($existing); }
        if ($existingRow) {
            return ['status' => 'existing', 'operation_id' => $operationId, 'message' => 'Data Packing untuk tanggal ini sudah ada dan tidak diubah.'];
        }
        $prior = sqlsrv_query($conn, 'SELECT run_status FROM dbo.output_vpk_auto_run WHERE target_date = ?', [$targetDate]);
        $priorRow = $prior ? sqlsrv_fetch_array($prior, SQLSRV_FETCH_ASSOC) : null;
        if ($prior !== false) { sqlsrv_free_stmt($prior); }
        if ($priorRow && ((string)$priorRow['run_status'] !== 'failed' || $triggeredBy === 'scheduler')) {
            return ['status' => (string)$priorRow['run_status'], 'operation_id' => $operationId, 'message' => 'Tanggal ini sudah memiliki status proses.'];
        }
        $summary = outputVpkAutoFetchSusut($conn3, $targetDate);
        if ($summary === null) {
            if ($recordEmpty) { outputVpkAutoRecordRun($conn, $targetDate, 'empty', $operationId, null, 'Tarikan Susut tidak memiliki data.', $triggeredBy); }
            return ['status' => 'empty', 'operation_id' => $operationId, 'message' => 'Tarikan Susut tidak memiliki data untuk tanggal ini.'];
        }
        $insert = sqlsrv_query($conn, 'INSERT INTO dbo.packing_output (tanggal, qty, qty_a1) VALUES (?, ?, ?)', [$targetDate, $summary['qty'], $summary['qty_a1']]);
        if ($insert === false) { throw new RuntimeException('Snapshot Output Packing gagal disimpan.'); }
        sqlsrv_free_stmt($insert);
        outputVpkAutoRecordRun($conn, $targetDate, 'success', $operationId, $summary, '', $triggeredBy);
        return ['status' => 'success', 'operation_id' => $operationId, 'summary' => $summary, 'message' => 'Snapshot Output Packing berhasil dibuat.'];
    } catch (Throwable $error) {
        try { outputVpkAutoRecordRun($conn, $targetDate, 'failed', $operationId, null, 'Proses gagal. Periksa log dengan operation ID.', $triggeredBy); } catch (Throwable $ignored) { }
        outputVpkAutoLogFailure($operationId, 'snapshot', $error->getMessage());
        throw new RuntimeException('Tarikan otomatis gagal. Operation ID: ' . $operationId);
    } finally {
        @sqlsrv_query($conn, "EXEC sp_releaseapplock @Resource = 'output_vpk_auto_snapshot', @LockOwner = 'Session';");
    }
}