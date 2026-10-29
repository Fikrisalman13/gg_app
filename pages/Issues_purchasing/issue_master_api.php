<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
require_once __DIR__ . '/issue_crud.php';

/**
 * Mengirim daftar master kecil yang dibutuhkan form Issue Purchasing.
 *
 * @param resource|null $conn Koneksi SQL Server aktif.
 * @return void
 */
function sendPurchasingIssueMasterRead($conn): void
{
    $access = purchasingIssueAccess($conn);
    if (empty($access['allowed'])) {
        purchasingIssueJson((int) $access['status'], false, (string) $access['message']);
        return;
    }

    $action = (string) ($_GET['action'] ?? '');
    if ($action === 'categories') {
        $includeInactive = !empty($access['can_edit']) && ($_GET['include_inactive'] ?? '') === '1';
        $statement = sqlsrv_query(
            $conn,
            'SELECT c.category_id,
                    c.category_name,
                    c.is_active,
                    (SELECT COUNT_BIG(1)
                     FROM dbo.purchasing_issues AS i
                     WHERE i.category_id = c.category_id) AS issue_reference_count,
                    (SELECT COUNT_BIG(1)
                     FROM dbo.purchasing_issues_template AS t
                     WHERE t.category_id = c.category_id) AS template_reference_count,
                    (SELECT COUNT_BIG(1)
                     FROM dbo.purchasing_issues AS i
                     WHERE i.category_id = c.category_id)
                    +
                    (SELECT COUNT_BIG(1)
                     FROM dbo.purchasing_issues_template AS t
                     WHERE t.category_id = c.category_id) AS reference_count
             FROM dbo.purchasing_issue_categories AS c
             WHERE ' . ($includeInactive ? '1 = 1' : 'c.is_active = 1') . '
             ORDER BY c.category_name ASC, c.category_id ASC'
        );
    } elseif ($action === 'subcategories') {
        $categoryId = filter_var($_GET['category_id'] ?? null, FILTER_VALIDATE_INT);
        if ($categoryId === false || $categoryId < 1) {
            purchasingIssueJson(400, false, 'Kategori tidak valid.');
            return;
        }
        $includeInactive = !empty($access['can_edit']) && ($_GET['include_inactive'] ?? '') === '1';
        $statement = sqlsrv_query(
            $conn,
            'SELECT s.sub_id,
                    s.category_id,
                    s.sub_name,
                    s.is_active,
                    (SELECT COUNT_BIG(1)
                     FROM dbo.purchasing_issues AS i
                     WHERE i.sub_id = s.sub_id) AS issue_reference_count,
                    (SELECT COUNT_BIG(1)
                     FROM dbo.purchasing_issues_template AS t
                     WHERE t.sub_id = s.sub_id) AS template_reference_count,
                    (SELECT COUNT_BIG(1)
                     FROM dbo.purchasing_issues AS i
                     WHERE i.sub_id = s.sub_id)
                    +
                    (SELECT COUNT_BIG(1)
                     FROM dbo.purchasing_issues_template AS t
                     WHERE t.sub_id = s.sub_id) AS reference_count
             FROM dbo.purchasing_issue_sub_categories AS s
             WHERE s.category_id = ? AND ' . ($includeInactive ? '1 = 1' : 's.is_active = 1') . '
             ORDER BY s.sub_name ASC, s.sub_id ASC',
            [$categoryId]
        );
    } elseif ($action === 'templates') {
        $statement = sqlsrv_query(
            $conn,
            'SELECT template_id, template_name, issue_type, category_id, sub_id, priority, description, initial_status, issue_names_json
             FROM dbo.purchasing_issues_template
             WHERE created_by = ?
             ORDER BY created_at DESC, template_id DESC',
            [trim((string) $_SESSION['UserName'])]
        );
    } else {
        purchasingIssueJson(400, false, 'Aksi master tidak valid.');
        return;
    }

    if ($statement === false) {
        purchasingIssueDatabaseFailure('master_read', 'query');
        return;
    }

    $rows = purchasingIssueFetchRows($statement);
    sqlsrv_free_stmt($statement);
    if ($rows === null) {
        purchasingIssueDatabaseFailure('master_read', 'fetch');
        return;
    }

    purchasingIssueJson(200, true, 'OK', ['data' => $rows]);
}

/**
 * Memastikan master tidak lagi direferensikan sebelum penghapusan fisik.
 *
 * @param resource $conn Koneksi SQL Server aktif.
 * @param string $kind Jenis master: category atau subcategory.
 * @param int $id ID master.
 * @return bool True bila tidak ada referensi.
 */
function purchasingIssueMasterIsUnused($conn, string $kind, int $id): bool
{
    if ($kind === 'category') {
        $sql = 'SELECT CASE WHEN EXISTS (SELECT 1 FROM dbo.purchasing_issues WHERE category_id = ?)
                              OR EXISTS (SELECT 1 FROM dbo.purchasing_issues_template WHERE category_id = ?)
                              OR EXISTS (SELECT 1 FROM dbo.purchasing_issue_sub_categories WHERE category_id = ?)
                         THEN 0 ELSE 1 END AS unused';
        $parameters = [$id, $id, $id];
    } else {
        $sql = 'SELECT CASE WHEN EXISTS (SELECT 1 FROM dbo.purchasing_issues WHERE sub_id = ?)
                              OR EXISTS (SELECT 1 FROM dbo.purchasing_issues_template WHERE sub_id = ?)
                         THEN 0 ELSE 1 END AS unused';
        $parameters = [$id, $id];
    }

    $statement = sqlsrv_query($conn, $sql, $parameters);
    if ($statement === false) {
        return false;
    }

    $row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($statement);
    return is_array($row) && (int) ($row['unused'] ?? 0) === 1;
}

/**
 * Menjalankan mutasi master kategori, subkategori, atau template yang aman.
 *
 * @param resource|null $conn Koneksi SQL Server aktif.
 * @return void
 */
function mutatePurchasingIssueMaster($conn): void
{
    $operation = trim((string) ($_POST['operation'] ?? ''));
    $permission = str_starts_with($operation, 'create_') ? 'add' : (str_starts_with($operation, 'delete_') ? 'delete' : 'edit');
    $context = purchasingIssueMutationContext($conn, $permission);
    if ($context === null) {
        return;
    }

    $name = purchasingIssueText($_POST['name'] ?? null, 'Nama', 200, true);
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT);

    if (in_array($operation, ['create_category', 'update_category'], true) && !$name['valid']) {
        purchasingIssueJson(400, false, $name['message']);
        return;
    }

    if (in_array($operation, ['create_subcategory', 'update_subcategory'], true) && (!$name['valid'] || $categoryId === false || $categoryId < 1)) {
        purchasingIssueJson(400, false, !$name['valid'] ? $name['message'] : 'Kategori tidak valid.');
        return;
    }

    if (str_starts_with($operation, 'delete_') && ($id === false || $id < 1)) {
        purchasingIssueJson(400, false, 'ID master tidak valid.');
        return;
    }

    if ($operation === 'create_category') {
        $statement = sqlsrv_query($conn, 'INSERT INTO dbo.purchasing_issue_categories (category_name, is_active, created_at) VALUES (?, 1, GETDATE())', [$name['value']]);
    } elseif ($operation === 'update_category') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $active = ($_POST['is_active'] ?? '1') === '1' ? 1 : 0;
        if ($id === false || $id < 1) {
            purchasingIssueJson(400, false, 'ID kategori tidak valid.');
            return;
        }
        $statement = sqlsrv_query($conn, 'UPDATE dbo.purchasing_issue_categories SET category_name = ?, is_active = ? WHERE category_id = ?', [$name['value'], $active, $id]);
    } elseif ($operation === 'delete_category') {
        if (!purchasingIssueMasterIsUnused($conn, 'category', (int) $id)) {
            purchasingIssueJson(409, false, 'Kategori masih digunakan dan tidak dapat dihapus.');
            return;
        }
        $statement = sqlsrv_query($conn, 'DELETE FROM dbo.purchasing_issue_categories WHERE category_id = ?', [$id]);
    } elseif ($operation === 'create_subcategory') {
        $statement = sqlsrv_query($conn, 'INSERT INTO dbo.purchasing_issue_sub_categories (category_id, sub_name, is_active, created_at) VALUES (?, ?, 1, GETDATE())', [$categoryId, $name['value']]);
    } elseif ($operation === 'update_subcategory') {
        $active = ($_POST['is_active'] ?? '1') === '1' ? 1 : 0;
        $statement = sqlsrv_query($conn, 'UPDATE dbo.purchasing_issue_sub_categories SET category_id = ?, sub_name = ?, is_active = ? WHERE sub_id = ?', [$categoryId, $name['value'], $active, $id]);
    } elseif ($operation === 'delete_subcategory') {
        if (!purchasingIssueMasterIsUnused($conn, 'subcategory', (int) $id)) {
            purchasingIssueJson(409, false, 'Subkategori masih digunakan dan tidak dapat dihapus.');
            return;
        }
        $statement = sqlsrv_query($conn, 'DELETE FROM dbo.purchasing_issue_sub_categories WHERE sub_id = ?', [$id]);
    } else {
        purchasingIssueJson(400, false, 'Operasi master tidak valid.');
        return;
    }

    if ($statement === false) {
        purchasingIssueDatabaseFailure('master_mutation', 'write');
        return;
    }

    $affected = sqlsrv_rows_affected($statement);
    sqlsrv_free_stmt($statement);
    if ($affected !== 1) {
        purchasingIssueJson(409, false, 'Data master sudah berubah atau tidak tersedia.');
        return;
    }

    purchasingIssueJson(200, true, 'Data master berhasil disimpan.');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    sendPurchasingIssueMasterRead($conn ?? null);
    return;
}

mutatePurchasingIssueMaster($conn ?? null);
