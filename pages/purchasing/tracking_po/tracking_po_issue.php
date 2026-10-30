<?php
require_once dirname(__DIR__, 2) . '/Issues_purchasing/issue_crud.php';

/**
 * Menghasilkan token CSRF untuk submit batch Tracking PO.
 *
 * @return string Token CSRF sesi aktif.
 */
function trackingPoIssueCsrfToken(): string
{
    if (empty($_SESSION['tracking_po_issue_csrf'])) {
        $_SESSION['tracking_po_issue_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['tracking_po_issue_csrf'];
}

/**
 * Memastikan token CSRF request sesuai dengan sesi aktif.
 *
 * @param mixed $token Token dari request.
 * @return bool True apabila token valid.
 */
function trackingPoIssueCsrfIsValid($token): bool
{
    return is_string($token)
        && isset($_SESSION['tracking_po_issue_csrf'])
        && hash_equals((string) $_SESSION['tracking_po_issue_csrf'], $token);
}

/**
 * Membuat UUID v4 untuk idempotensi batch Tracking PO.
 *
 * @return string UUID v4.
 */
function trackingPoIssueUuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20)
    );
}

/**
 * Memeriksa format UUID v4 request idempotensi Tracking PO.
 *
 * @param string $value UUID dari request.
 * @return bool True apabila format valid.
 */
function trackingPoIssueIsUuid(string $value): bool
{
    return (bool) preg_match(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
        $value
    );
}

/**
 * Membuat snapshot Issue untuk satu detail Tracking PO dalam transaksi aktif.
 *
 * @param resource $conn Koneksi SQL Server dalam transaksi aktif.
 * @param int $trackingDetailId ID detail Tracking PO.
 * @param array<string,mixed> $trackingData Data detail Tracking PO tervalidasi.
 * @param string $username Username pembuat Tracking PO.
 * @return int ID Issue baru.
 * @throws RuntimeException Bila master atau write Issue/link gagal.
 */
function trackingPoIssueCreate($conn, int $trackingDetailId, array $trackingData, string $username): int
{
    $mappingStatement = sqlsrv_query(
        $conn,
        'SELECT category.category_id, subcategory.sub_id
         FROM dbo.purchasing_issue_categories category
         INNER JOIN dbo.purchasing_issue_sub_categories subcategory ON subcategory.category_id = category.category_id
         WHERE category.category_name = ? AND category.is_active = 1
           AND subcategory.sub_name = ? AND subcategory.is_active = 1',
        ['Membuat PO', 'Membuat Dokumen PO']
    );
    $mapping = $mappingStatement ? sqlsrv_fetch_array($mappingStatement, SQLSRV_FETCH_ASSOC) : false;
    if ($mappingStatement) {
        sqlsrv_free_stmt($mappingStatement);
    }
    if (!is_array($mapping)) {
        throw new RuntimeException('Kategori Membuat PO atau subkategori Membuat Dokumen PO tidak tersedia.');
    }

    $profile = purchasingIssueCurrentProfile($conn, $username);
    $tanggalKirim = $trackingData['tanggal_kirim'] ?: 'Belum diisi';
    $description = implode("\n", [
        'No. PO: ' . $trackingData['ponmbr'],
        'Nama Supplier: ' . ($trackingData['vendor'] ?: '-'),
        'Tanggal PO: ' . ($trackingData['podate'] ?: '-'),
        'Tanggal Penyerahan: ' . $tanggalKirim,
        'Status Awal: ' . $trackingData['status'],
        'Diserahkan Oleh: ' . ($trackingData['diserahkan_oleh'] ?: '-')
    ]);
    if (mb_strlen($description) > 4000) {
        throw new RuntimeException('Description aktivitas Tracking PO melebihi 4.000 karakter.');
    }

    $issueStatement = sqlsrv_query(
        $conn,
        'INSERT INTO dbo.purchasing_issues (
            issue_name, issue_type, category_id, sub_id, sub_category_owner_id,
            status, priority, due_date, description, created_at, created_by,
            departemen, bagian, jabatan, request_id, tanggal_selesai
        )
        OUTPUT INSERTED.issue_id
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), ?, ?, ?, ?, ?, GETDATE())',
        [
            'Membuat Dokumen PO ' . $trackingData['ponmbr'],
            'Task',
            $mapping['category_id'],
            $mapping['sub_id'],
            $mapping['category_id'],
            'Done',
            'Medium',
            $trackingData['due_date'],
            $description,
            $profile['name'],
            $profile['departemen'] ?: null,
            $profile['bagian'] ?: null,
            $profile['jabatan'] ?: null,
            trackingPoIssueUuid()
        ]
    );
    $issue = $issueStatement ? sqlsrv_fetch_array($issueStatement, SQLSRV_FETCH_ASSOC) : false;
    if ($issueStatement) {
        sqlsrv_free_stmt($issueStatement);
    }
    if (!is_array($issue) || empty($issue['issue_id'])) {
        throw new RuntimeException('Issue aktivitas Tracking PO tidak dapat dibuat.');
    }

    $linkStatement = sqlsrv_query(
        $conn,
        'INSERT INTO dbo.tracking_po_issue_links (tracking_detail_id, issue_id) VALUES (?, ?)',
        [$trackingDetailId, $issue['issue_id']]
    );
    if ($linkStatement === false) {
        throw new RuntimeException('Relasi Tracking PO dan Issue tidak dapat dibuat.');
    }
    sqlsrv_free_stmt($linkStatement);

    return (int) $issue['issue_id'];
}
