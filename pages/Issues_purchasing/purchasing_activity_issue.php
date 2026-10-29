<?php
require_once __DIR__ . '/issue_crud.php';

function purchasingActivityCsrfToken(): string
{
    if (empty($_SESSION['purchasing_activity_csrf'])) {
        $_SESSION['purchasing_activity_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['purchasing_activity_csrf'];
}

function purchasingActivityCsrfIsValid($token): bool
{
    return is_string($token)
        && isset($_SESSION['purchasing_activity_csrf'])
        && hash_equals((string) $_SESSION['purchasing_activity_csrf'], $token);
}

function purchasingActivityUuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);

    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
}

/**
 * Membuat snapshot Issue selesai untuk satu aktivitas serah-terima Purchasing.
 *
 * @param resource $conn Koneksi SQL Server dalam transaksi aktif.
 * @param int $headerId ID header serah-terima.
 * @param int|null $poItemId ID rincian PO bila aktivitas memiliki banyak PO.
 * @param string $type Jenis dokumen serah-terima.
 * @param string $noPo Nomor PO sumber aktivitas.
 * @param string $nominal Nominal atau uraian sumber aktivitas.
 * @param string $vendor Nama supplier sumber aktivitas.
 * @param string $grn Nomor GRN sumber aktivitas.
 * @param array<int,string> $attachments Uraian berkas lampiran.
 * @param string $dueDate Tanggal jatuh tempo snapshot.
 * @param string $username Username pembuat aktivitas.
 * @return int ID Issue yang dibuat.
 * @throws RuntimeException Bila kategori, data, atau relasi Issue tidak valid.
 */
function purchasingActivityCreateIssue(
    $conn,
    int $headerId,
    ?int $poItemId,
    string $type,
    string $noPo,
    string $nominal,
    string $vendor,
    string $grn,
    array $attachments,
    string $dueDate,
    string $username
): int
{
    $subNames = [
        'PO' => 'Dokumen PO',
        'COD' => 'Dokumen COD',
        'RFP' => 'Dokumen RFP',
        'OFFSET' => 'Dokumen Offset',
        'Kontrabon' => 'Dokumen Kontrabon'
    ];
    $subName = $subNames[$type] ?? null;
    if ($subName === null || $noPo === '') {
        throw new RuntimeException('Type atau nomor PO belum valid untuk aktivitas Issue.');
    }

    $categoryStatement = sqlsrv_query($conn, "SELECT c.category_id, s.sub_id FROM dbo.purchasing_issue_categories c INNER JOIN dbo.purchasing_issue_sub_categories s ON s.category_id = c.category_id WHERE c.category_name = ? AND c.is_active = 1 AND s.sub_name = ? AND s.is_active = 1", ['Serah Terima Dokumen', $subName]);
    $mapping = $categoryStatement ? sqlsrv_fetch_array($categoryStatement, SQLSRV_FETCH_ASSOC) : false;
    if ($categoryStatement) {
        sqlsrv_free_stmt($categoryStatement);
    }
    if (!is_array($mapping)) {
        throw new RuntimeException('Kategori Serah Terima Dokumen atau subkategori Type tidak tersedia.');
    }

    $lines = ['Nominal / Description: ' . ($nominal !== '' ? $nominal : '-'), 'Nama Supplier: ' . ($vendor !== '' ? $vendor : '-'), 'No. GRN: ' . ($grn !== '' ? $grn : '-')];
    if ($attachments) {
        $lines[] = 'Uraian Berkas:';
        foreach ($attachments as $attachment) {
            $lines[] = '- ' . $attachment;
        }
    }
    $description = implode("\n", $lines);
    if (mb_strlen($description) > 4000) {
        throw new RuntimeException('Description aktivitas melebihi 4.000 karakter.');
    }

    $profile = purchasingIssueCurrentProfile($conn, $username);
    $issueName = 'Membuat Serah Terima Dokumen ' . $type . ' ' . $noPo;
    $issueSql = 'INSERT INTO dbo.purchasing_issues (
            issue_name, issue_type, category_id, sub_id, sub_category_owner_id,
            status, priority, due_date, description, created_at, created_by,
            departemen, bagian, jabatan, request_id, tanggal_selesai
        )
        OUTPUT INSERTED.issue_id
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), ?, ?, ?, ?, ?, GETDATE())';
    $issueParameters = [
        $issueName,
        'Task',
        $mapping['category_id'],
        $mapping['sub_id'],
        $mapping['category_id'],
        'Done',
        'Medium',
        $dueDate,
        $description,
        $profile['name'],
        $profile['departemen'] ?: null,
        $profile['bagian'] ?: null,
        $profile['jabatan'] ?: null,
        purchasingActivityUuid()
    ];
    $statement = sqlsrv_query($conn, $issueSql, $issueParameters);
    $issue = $statement ? sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC) : false;
    if ($statement) {
        sqlsrv_free_stmt($statement);
    }
    if (!is_array($issue) || empty($issue['issue_id'])) {
        throw new RuntimeException('Issue aktivitas tidak dapat dibuat.');
    }

    $link = sqlsrv_query($conn, 'INSERT INTO dbo.purchasing_issue_activity_links (purchasing_header_id, purchasing_po_item_id, issue_id) VALUES (?, ?, ?)', [$headerId, $poItemId, $issue['issue_id']]);
    if ($link === false) {
        throw new RuntimeException('Relasi aktivitas Issue tidak dapat dibuat.');
    }
    sqlsrv_free_stmt($link);

    return (int) $issue['issue_id'];
}
