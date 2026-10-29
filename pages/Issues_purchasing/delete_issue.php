<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
require_once __DIR__ . '/issue_crud.php';

/**
 * Menghapus satu Issue Purchasing menggunakan predicate rowversion.
 *
 * @param resource|null $conn Koneksi SQL Server aktif.
 * @return void
 */
function deletePurchasingIssue($conn): void
{
    $context = purchasingIssueMutationContext($conn, 'delete');
    if ($context === null) {
        return;
    }

    $issueId = filter_var($_POST['issue_id'] ?? null, FILTER_VALIDATE_INT);
    $rowVersion = purchasingIssueRowVersion($_POST['row_version'] ?? null);
    if ($issueId === false || $issueId < 1 || $rowVersion === null) {
        purchasingIssueJson(400, false, 'Versi issue tidak valid. Muat ulang detail lalu coba kembali.');
        return;
    }

    if (!sqlsrv_begin_transaction($conn)) {
        purchasingIssueDatabaseFailure('delete_issue', 'begin_transaction');
        return;
    }

    $linkStatement = sqlsrv_query(
        $conn,
        'DELETE FROM dbo.purchasing_issue_activity_links
         WHERE issue_id = ?',
        [$issueId]
    );
    if ($linkStatement === false) {
        sqlsrv_rollback($conn);
        purchasingIssueDatabaseFailure('delete_issue', 'delete_activity_link');
        return;
    }

    sqlsrv_free_stmt($linkStatement);

    $statement = sqlsrv_query(
        $conn,
        'DELETE FROM dbo.purchasing_issues
         WHERE issue_id = ? AND row_version = CONVERT(binary(8), ?, 1)',
        [$issueId, $rowVersion]
    );
    if ($statement === false) {
        sqlsrv_rollback($conn);
        purchasingIssueDatabaseFailure('delete_issue', 'delete');
        return;
    }

    $affected = sqlsrv_rows_affected($statement);
    sqlsrv_free_stmt($statement);
    if ($affected !== 1) {
        sqlsrv_rollback($conn);
        purchasingIssueJson(409, false, 'Issue sudah berubah atau tidak lagi tersedia. Muat ulang data lalu coba kembali.');
        return;
    }

    if (!sqlsrv_commit($conn)) {
        sqlsrv_rollback($conn);
        purchasingIssueDatabaseFailure('delete_issue', 'commit_transaction');
        return;
    }

    purchasingIssueJson(200, true, 'Issue berhasil dihapus.', ['issue_id' => (int) $issueId]);
}

deletePurchasingIssue($conn ?? null);
