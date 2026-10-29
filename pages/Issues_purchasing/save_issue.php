<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
require_once __DIR__ . '/issue_crud.php';

/**
 * Membuat satu Issue Purchasing dengan retry idempoten berbasis request id.
 *
 * @param resource|null $conn Koneksi SQL Server aktif.
 * @return void
 */
function createPurchasingIssue($conn): void
{
    $context = purchasingIssueMutationContext($conn, 'add');
    if ($context === null) {
        return;
    }

    $issueName = purchasingIssueText($_POST['issue_name'] ?? null, 'Nama issue', 255, true);
    $description = purchasingIssueText($_POST['description'] ?? null, 'Deskripsi', 4000);
    $dueDate = purchasingIssueDate($_POST['due_date'] ?? null);
    $type = purchasingIssueChoice($_POST['issue_type'] ?? null, ['Task', 'Support Activities'], 'Jenis issue');
    $status = purchasingIssueChoice($_POST['status'] ?? null, ['To Do', 'In Progress'], 'Status awal');
    $priority = purchasingIssueChoice($_POST['priority'] ?? null, ['Low', 'Medium', 'High', 'Critical'], 'Prioritas issue');
    $requestId = purchasingIssueRequestId($_POST['request_id'] ?? null);
    $pair = purchasingIssueCategoryPair($conn, $_POST['category_id'] ?? null, $_POST['sub_id'] ?? null);

    $validationResults = [$issueName, $description, $dueDate, $pair, $type, $status, $priority];
    $validationMessage = purchasingIssueFirstValidationMessage($validationResults);
    if ($validationMessage !== null) {
        purchasingIssueJson(400, false, $validationMessage);
        return;
    }

    if ($requestId === null) {
        purchasingIssueJson(400, false, 'ID permintaan tidak valid. Muat ulang halaman lalu coba kembali.');
        return;
    }

    $profile = purchasingIssueCurrentProfile($conn, $context['username']);

    $existingStatement = sqlsrv_query(
        $conn,
        'SELECT issue_id FROM dbo.purchasing_issues WHERE created_by = ? AND request_id = ?',
        [$context['username'], $requestId]
    );
    if ($existingStatement === false) {
        purchasingIssueDatabaseFailure('create_issue', 'idempotency_lookup');
        return;
    }

    $existing = sqlsrv_fetch_array($existingStatement, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($existingStatement);
    if (is_array($existing)) {
        purchasingIssueJson(200, true, 'Issue sudah pernah disimpan.', ['issue_id' => (int) $existing['issue_id'], 'idempotent' => true]);
        return;
    }

    if (!sqlsrv_begin_transaction($conn)) {
        purchasingIssueDatabaseFailure('create_issue', 'transaction_begin');
        return;
    }

    $insertStatement = sqlsrv_query(
        $conn,
        'INSERT INTO dbo.purchasing_issues
            (issue_name, issue_type, category_id, sub_id, sub_category_owner_id, status, priority, due_date, description, created_at, created_by, departemen, bagian, jabatan, request_id)
             OUTPUT INSERTED.issue_id
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), ?, ?, ?, ?, ?)',
        [
            $issueName['value'],
            $type['value'],
            $pair['category_id'],
            $pair['sub_id'],
            $pair['sub_id'] === null ? null : $pair['category_id'],
            $status['value'],
            $priority['value'],
            $dueDate['value'],
            $description['value'],
            $profile['name'],
            $profile['departemen'] !== '' ? $profile['departemen'] : null,
            $profile['bagian'] !== '' ? $profile['bagian'] : null,
            $profile['jabatan'] !== '' ? $profile['jabatan'] : null,
            $requestId
        ]
    );
    if ($insertStatement === false) {
        sqlsrv_rollback($conn);
        purchasingIssueDatabaseFailure('create_issue', 'insert');
        return;
    }

    $inserted = sqlsrv_fetch_array($insertStatement, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($insertStatement);
    if (!is_array($inserted) || empty($inserted['issue_id']) || !sqlsrv_commit($conn)) {
        sqlsrv_rollback($conn);
        purchasingIssueDatabaseFailure('create_issue', 'commit');
        return;
    }

    purchasingIssueJson(201, true, 'Issue berhasil disimpan.', ['issue_id' => (int) $inserted['issue_id'], 'idempotent' => false]);
}

createPurchasingIssue($conn ?? null);
