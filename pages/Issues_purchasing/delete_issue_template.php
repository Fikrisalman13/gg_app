<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
require_once __DIR__ . '/issue_crud.php';

/**
 * Menghapus template yang dimiliki pengguna aktif.
 *
 * @param resource|null $conn Koneksi SQL Server aktif.
 * @return void
 */
function deletePurchasingIssueTemplate($conn): void
{
    $context = purchasingIssueMutationContext($conn, 'delete');
    if ($context === null) {
        return;
    }

    $templateId = filter_var($_POST['template_id'] ?? null, FILTER_VALIDATE_INT);
    if ($templateId === false || $templateId < 1) {
        purchasingIssueJson(400, false, 'ID template tidak valid.');
        return;
    }

    $statement = sqlsrv_query(
        $conn,
        'DELETE FROM dbo.purchasing_issues_template WHERE template_id = ? AND created_by = ?',
        [$templateId, $context['username']]
    );
    if ($statement === false) {
        purchasingIssueDatabaseFailure('delete_template', 'delete');
        return;
    }

    $affected = sqlsrv_rows_affected($statement);
    sqlsrv_free_stmt($statement);
    if ($affected !== 1) {
        purchasingIssueJson(404, false, 'Template tidak ditemukan atau bukan milik Anda.');
        return;
    }

    purchasingIssueJson(200, true, 'Template berhasil dihapus.');
}

deletePurchasingIssueTemplate($conn ?? null);
