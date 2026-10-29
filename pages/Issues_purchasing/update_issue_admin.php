<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
require_once __DIR__ . '/issue_crud.php';

/**
 * Memperbarui Issue Purchasing dengan perlindungan rowversion.
 *
 * @param resource|null $conn Koneksi SQL Server aktif.
 * @return void
 */
function updatePurchasingIssue($conn): void
{
    $context = purchasingIssueMutationContext($conn, 'edit');
    if ($context === null) {
        return;
    }

    $issueId = filter_var($_POST['issue_id'] ?? null, FILTER_VALIDATE_INT);
    $rowVersion = purchasingIssueRowVersion($_POST['row_version'] ?? null);
    $issueName = purchasingIssueText($_POST['issue_name'] ?? null, 'Nama issue', 255, true);
    $description = purchasingIssueText($_POST['description'] ?? null, 'Deskripsi', 4000);
    $dueDate = purchasingIssueDate($_POST['due_date'] ?? null);
    $type = purchasingIssueChoice($_POST['issue_type'] ?? null, ['Task', 'Support Activities'], 'Jenis issue');
    $status = purchasingIssueChoice($_POST['status'] ?? null, ['To Do', 'In Progress', 'Done'], 'Status issue');
    $priority = purchasingIssueChoice($_POST['priority'] ?? null, ['Low', 'Medium', 'High', 'Critical'], 'Prioritas issue');
    $pair = purchasingIssueCategoryPair($conn, $_POST['category_id'] ?? null, $_POST['sub_id'] ?? null);

    if ($issueId === false || $issueId < 1 || $rowVersion === null) {
        purchasingIssueJson(400, false, 'Versi issue tidak valid. Muat ulang detail lalu coba kembali.');
        return;
    }

    $validationResults = [$issueName, $description, $dueDate, $pair, $type, $status, $priority];
    $validationMessage = purchasingIssueFirstValidationMessage($validationResults);
    if ($validationMessage !== null) {
        purchasingIssueJson(400, false, $validationMessage);
        return;
    }

    $completedAtSql = $status['value'] === 'Done' ? 'GETDATE()' : 'NULL';
    $sql = 'UPDATE dbo.purchasing_issues
            SET issue_name = ?, issue_type = ?, category_id = ?, sub_id = ?, sub_category_owner_id = ?,
                status = ?, priority = ?, due_date = ?, description = ?, update_at = GETDATE(),
                update_by = ?, tanggal_selesai = ' . $completedAtSql . '
            WHERE issue_id = ? AND row_version = CONVERT(binary(8), ?, 1)';
    $statement = sqlsrv_query(
        $conn,
        $sql,
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
            trim((string) ($_SESSION['NamaLengkap'] ?? $context['username'])) ?: $context['username'],
            $issueId,
            $rowVersion
        ]
    );
    if ($statement === false) {
        purchasingIssueDatabaseFailure('update_issue', 'update');
        return;
    }

    $affected = sqlsrv_rows_affected($statement);
    sqlsrv_free_stmt($statement);
    if ($affected !== 1) {
        purchasingIssueJson(409, false, 'Issue sudah berubah atau tidak lagi tersedia. Muat ulang data lalu coba kembali.');
        return;
    }

    purchasingIssueJson(200, true, 'Issue berhasil diperbarui.', ['issue_id' => (int) $issueId]);
}

updatePurchasingIssue($conn ?? null);
