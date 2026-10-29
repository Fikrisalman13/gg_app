<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
require_once __DIR__ . '/issue_crud.php';

/**
 * Menandai Issue Purchasing aktif sebagai selesai oleh pembuatnya sendiri.
 *
 * Endpoint ini tidak memakai hak CanEdit karena penyelesaian Issue adalah aksi
 * pemilik. Akses menu CanView, CSRF, pembuat snapshot, status aktif, dan
 * rowversion tetap diverifikasi di server sebelum data diubah.
 *
 * @param resource|null $conn Koneksi SQL Server aktif.
 * @return void
 */
function markPurchasingIssueDone($conn): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        purchasingIssueJson(405, false, 'Metode permintaan tidak didukung.');
        return;
    }

    $access = purchasingIssueAccess($conn);
    if (empty($access['allowed'])) {
        purchasingIssueJson((int) $access['status'], false, (string) $access['message']);
        return;
    }

    if (!purchasingIssueCsrfIsValid()) {
        purchasingIssueJson(403, false, 'Permintaan tidak dapat diverifikasi. Muat ulang halaman lalu coba kembali.');
        return;
    }

    $issueId = filter_var($_POST['issue_id'] ?? null, FILTER_VALIDATE_INT);
    $rowVersion = purchasingIssueRowVersion($_POST['row_version'] ?? null);
    if ($issueId === false || $issueId < 1 || $rowVersion === null) {
        purchasingIssueJson(400, false, 'Versi issue tidak valid. Muat ulang detail lalu coba kembali.');
        return;
    }

    $username = trim((string) ($_SESSION['UserName'] ?? ''));
    $profile = purchasingIssueCurrentProfile($conn, $username);
    $creatorName = trim((string) ($profile['name'] ?? ''));
    if ($creatorName === '') {
        purchasingIssueJson(403, false, 'Identitas pembuat tidak dapat diverifikasi.');
        return;
    }

    if (!sqlsrv_begin_transaction($conn)) {
        purchasingIssueDatabaseFailure('mark_issue_done', 'begin_transaction');
        return;
    }

    $issueStatement = sqlsrv_query(
        $conn,
        'SELECT issue_id, created_by, status
         FROM dbo.purchasing_issues WITH (UPDLOCK, ROWLOCK)
         WHERE issue_id = ?',
        [$issueId]
    );
    if ($issueStatement === false) {
        sqlsrv_rollback($conn);
        purchasingIssueDatabaseFailure('mark_issue_done', 'lookup_issue');
        return;
    }

    $issue = sqlsrv_fetch_array($issueStatement, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($issueStatement);
    if (!is_array($issue)) {
        sqlsrv_rollback($conn);
        purchasingIssueJson(404, false, 'Issue tidak lagi tersedia. Muat ulang daftar lalu coba kembali.');
        return;
    }

    $storedCreator = trim((string) ($issue['created_by'] ?? ''));
    if (mb_strtolower($storedCreator) !== mb_strtolower($creatorName)) {
        sqlsrv_rollback($conn);
        purchasingIssueJson(403, false, 'Hanya pembuat Issue yang dapat menandai selesai.');
        return;
    }

    if (!in_array((string) ($issue['status'] ?? ''), ['To Do', 'In Progress'], true)) {
        sqlsrv_rollback($conn);
        purchasingIssueJson(409, false, 'Issue sudah selesai atau statusnya tidak dapat diubah. Muat ulang data lalu coba kembali.');
        return;
    }

    $updateStatement = sqlsrv_query(
        $conn,
        'UPDATE dbo.purchasing_issues
         SET status = ?,
             tanggal_selesai = GETDATE(),
             update_by = ?,
             update_at = GETDATE()
         WHERE issue_id = ?
           AND row_version = CONVERT(binary(8), ?, 1)
           AND created_by = ?
           AND status IN (?, ?)',
        ['Done', $username, $issueId, $rowVersion, $storedCreator, 'To Do', 'In Progress']
    );
    if ($updateStatement === false) {
        sqlsrv_rollback($conn);
        purchasingIssueDatabaseFailure('mark_issue_done', 'update_issue');
        return;
    }

    $affectedRows = sqlsrv_rows_affected($updateStatement);
    sqlsrv_free_stmt($updateStatement);
    if ($affectedRows !== 1) {
        sqlsrv_rollback($conn);
        purchasingIssueJson(409, false, 'Issue sudah berubah. Muat ulang data lalu coba kembali.');
        return;
    }

    if (!sqlsrv_commit($conn)) {
        sqlsrv_rollback($conn);
        purchasingIssueDatabaseFailure('mark_issue_done', 'commit_transaction');
        return;
    }

    purchasingIssueJson(200, true, 'Issue berhasil ditandai selesai.', ['issue_id' => (int) $issueId]);
}

markPurchasingIssueDone($conn ?? null);
