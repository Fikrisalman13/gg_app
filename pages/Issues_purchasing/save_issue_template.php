<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
require_once __DIR__ . '/issue_crud.php';

/**
 * Membuat atau memperbarui template milik pengguna aktif.
 *
 * @param resource|null $conn Koneksi SQL Server aktif.
 * @return void
 */
function savePurchasingIssueTemplate($conn): void
{
    $templateId = filter_var($_POST['template_id'] ?? null, FILTER_VALIDATE_INT);
    $isUpdate = $templateId !== false && $templateId !== null && $templateId > 0;
    $context = purchasingIssueMutationContext($conn, $isUpdate ? 'edit' : 'add');
    if ($context === null) {
        return;
    }

    $templateName = purchasingIssueText($_POST['template_name'] ?? null, 'Nama template', 255, true);
    $description = purchasingIssueText($_POST['description'] ?? null, 'Deskripsi', 4000);
    $type = purchasingIssueChoice($_POST['issue_type'] ?? null, ['Task', 'Support Activities'], 'Jenis template');
    $priority = purchasingIssueChoice($_POST['priority'] ?? null, ['Low', 'Medium', 'High', 'Critical'], 'Prioritas template');
    $status = purchasingIssueChoice($_POST['initial_status'] ?? null, ['To Do', 'In Progress'], 'Status awal template');
    $pair = purchasingIssueCategoryPair($conn, $_POST['category_id'] ?? null, $_POST['sub_id'] ?? null);

    $validationResults = [$templateName, $description, $pair, $type, $priority, $status];
    $validationMessage = purchasingIssueFirstValidationMessage($validationResults);
    if ($validationMessage !== null) {
        purchasingIssueJson(400, false, $validationMessage);
        return;
    }

    if ($isUpdate) {
        $statement = sqlsrv_query(
            $conn,
            'UPDATE dbo.purchasing_issues_template
             SET template_name = ?, issue_type = ?, category_id = ?, sub_id = ?, sub_category_owner_id = ?, priority = ?, description = ?, initial_status = ?
             WHERE template_id = ? AND created_by = ?',
            [
                $templateName['value'],
                $type['value'],
                $pair['category_id'],
                $pair['sub_id'],
                $pair['sub_id'] === null ? null : $pair['category_id'],
                $priority['value'],
                $description['value'],
                $status['value'],
                $templateId,
                $context['username']
            ]
        );
    } else {
        $statement = sqlsrv_query(
            $conn,
            'INSERT INTO dbo.purchasing_issues_template
                (template_name, issue_type, category_id, sub_id, sub_category_owner_id, priority, description, created_by, created_at, initial_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), ?)',
            [
                $templateName['value'],
                $type['value'],
                $pair['category_id'],
                $pair['sub_id'],
                $pair['sub_id'] === null ? null : $pair['category_id'],
                $priority['value'],
                $description['value'],
                $context['username'],
                $status['value']
            ]
        );
    }

    if ($statement === false) {
        purchasingIssueDatabaseFailure('save_template', 'write');
        return;
    }

    $affected = sqlsrv_rows_affected($statement);
    sqlsrv_free_stmt($statement);
    if ($affected !== 1) {
        purchasingIssueJson(409, false, 'Template tidak tersedia atau sudah berubah.');
        return;
    }

    purchasingIssueJson($isUpdate ? 200 : 201, true, 'Template berhasil disimpan.');
}

savePurchasingIssueTemplate($conn ?? null);
