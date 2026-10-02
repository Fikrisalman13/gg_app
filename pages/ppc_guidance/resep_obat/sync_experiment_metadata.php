<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../resep_obat/experiment/experiment_visibility_helper.php';
header('Content-Type: application/json; charset=utf-8');
$requestId = bin2hex(random_bytes(8));
$user = $_SESSION['UserName'] ?? '';
const BULK_SYNC_LIMIT = 100;

/** Return a safe JSON response. */
function syncRespond(int $status, array $payload): void
{
    global $requestId;
    http_response_code($status);
    echo json_encode(array_merge(['request_id' => $requestId], $payload), JSON_UNESCAPED_UNICODE);
    exit;
}

/** Classify a target/source metadata pair without overwriting valid PPC values. */
function syncFieldState(string $field, ?string $currentValue, ?string $sourceValue): string
{
    $current = trim((string) $currentValue);
    $source = trim((string) $sourceValue);
    if ($source === '' || $source === '-') return 'source_empty';
    if (strcasecmp($current, $source) === 0) return 'same';
    $placeholders = $field === 'no_cp' ? ['', '-', 'TUNGGU CP'] : ['', '-'];
    foreach ($placeholders as $placeholder) {
        if (strcasecmp($current, $placeholder) === 0) return 'update';
    }
    return 'conflict';
}

/** Build shared field states from one linked PPC/Experiment row. */
function syncFields(array $row): array
{
    $fields = [
        'no_cp' => ['label' => 'No CP', 'current' => trim((string) $row['ppc_no_cp']), 'source' => trim((string) $row['experiment_no_cp'])],
        'no_so' => ['label' => 'No SO', 'current' => trim((string) $row['ppc_no_so']), 'source' => trim((string) $row['experiment_soi'])],
    ];
    foreach ($fields as $key => &$field) $field['state'] = syncFieldState($key, $field['current'], $field['source']);
    unset($field);
    return $fields;
}

/** Load one visible, previously approved, Experiment-status source link. */
function loadSyncRow($conn, int $recipeId): ?array
{
    $conditions = ["p.id=?", "UPPER(LTRIM(RTRIM(ISNULL(p.status_resep_lipat,''))))='EXPERIMENT'", 'e.was_approved=1'];
    $params = [$recipeId];
    resepExperimentApplyVisibility($conditions, $params, 'e', $conn);
    $sql = 'SELECT p.id,p.cus_color,p.no_cp AS ppc_no_cp,p.no_so AS ppc_no_so,p.source_experiment_id,e.no_cp AS experiment_no_cp,e.soi AS experiment_soi FROM dbo.resep_obat p INNER JOIN dbo.resep_obat_experiment e ON e.id=p.source_experiment_id WHERE ' . implode(' AND ', $conditions);
    $statement = sqlsrv_query($conn, $sql, $params);
    $row = $statement ? sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC) : null;
    return $row ?: null;
}

/** Update one recipe inside its own transaction after checking preview baselines. */
function applySyncRow($conn, array $row, array $baseline, string $user): string
{
    $fields = syncFields($row);
    if ((string) ($baseline['no_cp'] ?? '') !== $fields['no_cp']['current'] || (string) ($baseline['no_so'] ?? '') !== $fields['no_so']['current']) return 'skipped';
    $updates = array_filter($fields, fn($field) => $field['state'] === 'update');
    if (!$updates) return count(array_filter($fields, fn($field) => $field['state'] === 'conflict')) ? 'conflict' : 'skipped';
    $newNoCp = isset($updates['no_cp']) ? $fields['no_cp']['source'] : $fields['no_cp']['current'];
    $newNoSo = isset($updates['no_so']) ? $fields['no_so']['source'] : $fields['no_so']['current'];
    if (!sqlsrv_begin_transaction($conn)) return 'failed';
    $update = sqlsrv_query($conn, "UPDATE dbo.resep_obat SET no_cp=?,no_so=?,updated_at=?,updated_by=? WHERE id=? AND UPPER(LTRIM(RTRIM(ISNULL(status_resep_lipat,''))))='EXPERIMENT' AND ISNULL(no_cp,'')=? AND ISNULL(no_so,'')=?", [$newNoCp, $newNoSo, date('Y-m-d H:i:s'), $user, (int) $row['id'], $fields['no_cp']['current'], $fields['no_so']['current']]);
    if (!$update || sqlsrv_rows_affected($update) !== 1) { sqlsrv_rollback($conn); return 'skipped'; }
    if (!sqlsrv_commit($conn)) { sqlsrv_rollback($conn); return 'failed'; }
    return 'updated';
}

if ($user === '') syncRespond(401, ['ok' => false, 'message' => 'Sesi login berakhir.']);
$permissionStatement = sqlsrv_query($conn, 'SELECT CanView, CanEdit FROM dbo.SMGroupTrustee WHERE GroupId=? AND MenuId=215', [$_SESSION['GroupId'] ?? 0]);
$permission = $permissionStatement ? sqlsrv_fetch_array($permissionStatement, SQLSRV_FETCH_ASSOC) : null;
if (($permission['CanView'] ?? 0) != 1 || ($permission['CanEdit'] ?? 0) != 1) syncRespond(403, ['ok' => false, 'message' => 'Tidak memiliki hak sinkronisasi resep.']);
$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$action = $input['action'] ?? 'single';

if ($action === 'bulk_preview') {
    $conditions = ["UPPER(LTRIM(RTRIM(ISNULL(p.status_resep_lipat,''))))='EXPERIMENT'", 'p.source_experiment_id IS NOT NULL', 'e.was_approved=1'];
    $params = [];
    resepExperimentApplyVisibility($conditions, $params, 'e', $conn);
    $sql = 'SELECT TOP 500 p.id,p.cus_color,p.no_cp AS ppc_no_cp,p.no_so AS ppc_no_so,p.source_experiment_id,e.no_cp AS experiment_no_cp,e.soi AS experiment_soi FROM dbo.resep_obat p INNER JOIN dbo.resep_obat_experiment e ON e.id=p.source_experiment_id WHERE ' . implode(' AND ', $conditions) . ' ORDER BY p.id DESC';
    $statement = sqlsrv_query($conn, $sql, $params);
    if (!$statement) syncRespond(500, ['ok' => false, 'message' => 'Gagal memindai resep Experiment.']);
    $summary = ['scanned' => 0, 'can_update' => 0, 'already_sync' => 0, 'source_empty' => 0, 'conflict' => 0, 'no_source_id' => 0];
    $details = ['can_update' => [], 'already_sync' => [], 'source_empty' => [], 'conflict' => [], 'no_source_id' => [], 'scanned' => []];
    $unlinkedStatement = sqlsrv_query(
        $conn,
        "SELECT TOP 100 id,cus_color,no_cp AS ppc_no_cp,no_so AS ppc_no_so
         FROM dbo.resep_obat
         WHERE UPPER(LTRIM(RTRIM(ISNULL(status_resep_lipat,''))))='EXPERIMENT'
           AND source_experiment_id IS NULL
         ORDER BY id DESC"
    );
    while ($unlinkedStatement && $unlinkedRow = sqlsrv_fetch_array($unlinkedStatement, SQLSRV_FETCH_ASSOC)) {
        $summary['no_source_id']++;
        $details['no_source_id'][] = [
            'resep_id' => (int) $unlinkedRow['id'],
            'cus_color' => $unlinkedRow['cus_color'] ?? '-',
            'fields' => syncFields(array_merge($unlinkedRow, ['experiment_no_cp' => '', 'experiment_soi' => ''])),
        ];
    }
    $candidates = [];
    while ($row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC)) {
        $summary['scanned']++;
        $fields = syncFields($row);
        $states = array_column($fields, 'state');
        $previewRow = ['resep_id' => (int) $row['id'], 'cus_color' => $row['cus_color'] ?? '-', 'fields' => $fields];
        $details['scanned'][] = $previewRow;
        if (in_array('update', $states, true)) {
            $category = 'can_update';
            if (count($candidates) < BULK_SYNC_LIMIT) $candidates[] = $previewRow;
        } elseif (in_array('conflict', $states, true)) {
            $category = 'conflict';
        } elseif (count(array_filter($states, fn($state) => $state === 'source_empty')) === count($states)) {
            $category = 'source_empty';
        } else {
            $category = 'already_sync';
        }
        $summary[$category]++;
        $details[$category][] = $previewRow;
    }
    syncRespond(200, ['ok' => true, 'summary' => $summary, 'details' => $details, 'candidates' => $candidates, 'batch_limit' => BULK_SYNC_LIMIT, 'truncated' => $summary['can_update'] > count($candidates)]);
}

if ($action === 'bulk_sync') {
    $candidates = json_decode((string) ($input['candidates'] ?? ''), true);
    if (!is_array($candidates) || count($candidates) > BULK_SYNC_LIMIT) syncRespond(422, ['ok' => false, 'message' => 'Daftar kandidat bulk tidak valid.']);
    $result = ['updated' => 0, 'skipped' => 0, 'conflict' => 0, 'failed' => 0];
    foreach ($candidates as $candidate) {
        $recipeId = filter_var($candidate['resep_id'] ?? null, FILTER_VALIDATE_INT);
        $row = $recipeId ? loadSyncRow($conn, $recipeId) : null;
        $status = $row ? applySyncRow($conn, $row, ['no_cp' => $candidate['baseline_no_cp'] ?? '', 'no_so' => $candidate['baseline_no_so'] ?? ''], $user) : 'skipped';
        $result[$status]++;
    }
    syncRespond(200, ['ok' => true, 'message' => $result['updated'] . ' resep berhasil disinkronkan.', 'operation_id' => bin2hex(random_bytes(6)), 'result' => $result]);
}

$recipeId = filter_var($input['resep_id'] ?? null, FILTER_VALIDATE_INT);
if (!$recipeId) syncRespond(422, ['ok' => false, 'message' => 'ID resep tidak valid.']);
$row = loadSyncRow($conn, $recipeId);
if (!$row) syncRespond(404, ['ok' => false, 'message' => 'Resep harus berstatus Experiment dan memiliki source yang dapat diakses.']);
$fields = syncFields($row);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') syncRespond(200, ['ok' => true, 'resep_id' => (int) $row['id'], 'source_experiment_id' => (int) $row['source_experiment_id'], 'fields' => $fields, 'update_count' => count(array_filter($fields, fn($field) => $field['state'] === 'update'))]);
$status = applySyncRow($conn, $row, ['no_cp' => $input['baseline_no_cp'] ?? '', 'no_so' => $input['baseline_no_so'] ?? ''], $user);
if ($status === 'failed') syncRespond(500, ['ok' => false, 'message' => 'Gagal menyelesaikan sinkronisasi.']);
if ($status !== 'updated') syncRespond(409, ['ok' => false, 'message' => 'Data berubah atau tidak lagi dapat disinkronkan. Muat ulang preview.']);
syncRespond(200, ['ok' => true, 'message' => 'Metadata berhasil disinkronkan.', 'updated_fields' => array_keys(array_filter($fields, fn($field) => $field['state'] === 'update'))]);
