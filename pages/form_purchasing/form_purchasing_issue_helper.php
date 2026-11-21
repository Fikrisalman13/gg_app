<?php
/**
 * form_purchasing_issue_helper.php
 * Helper untuk mengintegrasikan Form Purchasing (Bukti Pengembalian Uang & COD) ke modul Issues Purchasing.
 * Standar: Kategori "Membuat Form" (category_id = 2), status langsung "Done" sebagai catatan aktivitas kerja selesai.
 */

$accessPath = dirname(__DIR__) . '/Issues_purchasing/issue_access.php';
if (file_exists($accessPath)) {
    require_once $accessPath;
}

/**
 * Membuat UUID v4 untuk idempotensi request issue.
 *
 * @return string UUID v4.
 */
if (!function_exists('formPurchasingIssueUuid')) {
    function formPurchasingIssueUuid(): string
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
}

/**
 * Membuat Issue aktivitas selesai di modul Issues Purchasing saat form berhasil disimpan.
 *
 * @param resource $conn Koneksi SQL Server aktif.
 * @param string $formType Tipe form ('bukti_pengembalian_uang' atau 'cash_on_delivery').
 * @param int|string $formId ID baris header di tabel Form.
 * @param array $formData Data formulir yang baru disimpan.
 * @param string $username Username pembuat form.
 * @return int|null ID Issue yang baru dibuat atau null bila gagal.
 */
function formPurchasingCreateIssue($conn, string $formType, $formId, array $formData, string $username): ?int
{
    if (!$conn || empty($formId)) {
        return null;
    }

    $isBpu = ($formType === 'bukti_pengembalian_uang' || $formType === 'bukti_pengembalian_barang');
    $ticket = trim($formData['ticket'] ?? '');

    if ($isBpu) {
        $subCategoryName = 'BPU';
        $issueTitle = 'Membuat Form Bukti Pengembalian Uang ' . $ticket;
        $linkTable = 'dbo.form_purchasing_bpu_issue_links';
        $linkIdColumn = 'form_bpu_id';

        $nominalFormatted = number_format(floatval($formData['subtotal'] ?? 0), 2, ',', '.');
        $currency = !empty($formData['currency']) ? $formData['currency'] : 'IDR';

        $descriptionLines = [
            'Ticket: ' . ($ticket ?: '-'),
            'No. PO: ' . (!empty($formData['ref_po_no']) ? $formData['ref_po_no'] : '-'),
            'RFP NO: ' . (!empty($formData['rfp_no']) ? $formData['rfp_no'] : '-'),
            'Supplier: ' . (!empty($formData['supplier']) ? $formData['supplier'] : '-'),
            'Payment Method: ' . (!empty($formData['payment_method']) ? $formData['payment_method'] : '-'),
            'Nominal: ' . $currency . ' ' . $nominalFormatted,
            'Keterangan: ' . (!empty($formData['keterangan']) ? $formData['keterangan'] : '-')
        ];
    } else {
        // Cash On Delivery (COD)
        $subCategoryName = 'Form COD';
        $issueTitle = 'Membuat Form Cash On Delivery ' . $ticket;
        $linkTable = 'dbo.form_purchasing_cod_issue_links';
        $linkIdColumn = 'form_cod_id';

        $nominalFormatted = number_format(floatval($formData['subtotal'] ?? 0), 2, ',', '.');
        $currency = !empty($formData['currency']) ? $formData['currency'] : 'IDR';

        $descriptionLines = [
            'Ticket: ' . ($ticket ?: '-'),
            'No. PO: ' . (!empty($formData['ref_po_no']) ? $formData['ref_po_no'] : '-'),
            'No. Receipt: ' . (!empty($formData['no_receipt']) ? $formData['no_receipt'] : '-'),
            'Supplier: ' . (!empty($formData['supplier']) ? $formData['supplier'] : '-'),
            'Dibayar Kepada: ' . (!empty($formData['dibayar_kepada']) ? $formData['dibayar_kepada'] : '-'),
            'Nominal: ' . $currency . ' ' . $nominalFormatted,
            'Keterangan: ' . (!empty($formData['keterangan']) ? $formData['keterangan'] : '-')
        ];
    }

    // 1. Lookup Category 'Membuat Form' dan Subcategory ('BPU' atau 'Form COD')
    $sqlMapping = "
        SELECT category.category_id, subcategory.sub_id
        FROM dbo.purchasing_issue_categories category
        INNER JOIN dbo.purchasing_issue_sub_categories subcategory ON subcategory.category_id = category.category_id
        WHERE category.category_name = 'Membuat Form' AND category.is_active = 1
          AND subcategory.sub_name = ? AND subcategory.is_active = 1
    ";
    $stmtMapping = sqlsrv_query($conn, $sqlMapping, [$subCategoryName]);
    $mapping = ($stmtMapping && $row = sqlsrv_fetch_array($stmtMapping, SQLSRV_FETCH_ASSOC)) ? $row : null;
    if ($stmtMapping) {
        sqlsrv_free_stmt($stmtMapping);
    }

    if (!$mapping) {
        // Kategori atau subkategori tidak ditemukan/tidak aktif, batalkan tanpa memutus alur utama
        error_log("formPurchasingCreateIssue: Subkategori '$subCategoryName' di bawah 'Membuat Form' tidak ditemukan.");
        return null;
    }

    // 2. Ambil profil pembuat aktif
    $profile = function_exists('purchasingIssueCurrentProfile') 
        ? purchasingIssueCurrentProfile($conn, $username) 
        : [
            'name' => $_SESSION['NamaLengkap'] ?? $username,
            'jabatan' => $_SESSION['Jabatan'] ?? '',
            'departemen' => $_SESSION['Departemen'] ?? 'Purchasing',
            'bagian' => $_SESSION['Bagian'] ?? ''
        ];

    $description = implode("\n", $descriptionLines);
    if (mb_strlen($description) > 4000) {
        $description = mb_substr($description, 0, 3995) . '...';
    }

    $dueDate = !empty($formData['due_date']) ? $formData['due_date'] : (!empty($formData['tanggal']) ? $formData['tanggal'] : date('Y-m-d'));

    // 3. Insert ke dbo.purchasing_issues (Status 'Done')
    $sqlIssue = "
        INSERT INTO dbo.purchasing_issues (
            issue_name, issue_type, category_id, sub_id, sub_category_owner_id,
            status, priority, due_date, description, created_at, created_by,
            departemen, bagian, jabatan, request_id, tanggal_selesai
        )
        OUTPUT INSERTED.issue_id
        VALUES (?, 'Task', ?, ?, ?, 'Done', 'Medium', ?, ?, GETDATE(), ?, ?, ?, ?, ?, GETDATE())
    ";
    $paramsIssue = [
        $issueTitle,
        $mapping['category_id'],
        $mapping['sub_id'],
        $mapping['category_id'],
        $dueDate,
        $description,
        $profile['name'],
        $profile['departemen'] ?: null,
        $profile['bagian'] ?: null,
        $profile['jabatan'] ?: null,
        formPurchasingIssueUuid()
    ];

    $stmtIssue = sqlsrv_query($conn, $sqlIssue, $paramsIssue);
    $issueRow = ($stmtIssue && $r = sqlsrv_fetch_array($stmtIssue, SQLSRV_FETCH_ASSOC)) ? $r : null;
    if ($stmtIssue) {
        sqlsrv_free_stmt($stmtIssue);
    }

    if (!$issueRow || empty($issueRow['issue_id'])) {
        error_log("formPurchasingCreateIssue: Gagal menyimpan purchasing_issues untuk form $ticket");
        return null;
    }

    $issueId = (int)$issueRow['issue_id'];

    // 4. Simpan relasi ke tabel link
    $sqlLink = "INSERT INTO $linkTable ($linkIdColumn, issue_id, created_at) VALUES (?, ?, GETDATE())";
    $stmtLink = sqlsrv_query($conn, $sqlLink, [$formId, $issueId]);
    if ($stmtLink) {
        sqlsrv_free_stmt($stmtLink);
    }

    return $issueId;
}

/**
 * Menghapus Issue di Issues Purchasing jika form pengajuan dihapus dari list_form.php.
 *
 * @param resource $conn Koneksi SQL Server aktif.
 * @param string $formType Tipe form ('bukti_pengembalian_uang' atau 'cash_on_delivery').
 * @param string $ticket Nomor tiket formulir yang dihapus.
 * @return bool True bila berhasil atau tidak ada issue terkait.
 */
function formPurchasingDeleteIssue($conn, string $formType, string $ticket): bool
{
    if (!$conn || empty($ticket)) {
        return false;
    }

    $isBpu = ($formType === 'bukti_pengembalian_uang' || $formType === 'bukti_pengembalian_barang');

    if ($isBpu) {
        $formTable = 'dbo.Form_Purchasing_Pengembalian_Uang';
        $linkTable = 'dbo.form_purchasing_bpu_issue_links';
        $linkIdColumn = 'form_bpu_id';
    } else {
        $formTable = 'dbo.Form_Purchasing_COD';
        $linkTable = 'dbo.form_purchasing_cod_issue_links';
        $linkIdColumn = 'form_cod_id';
    }

    // 1. Cari issue_id yang berelasi dengan formulir ini
    $sqlFind = "
        SELECT l.link_id, l.issue_id 
        FROM $linkTable l
        INNER JOIN $formTable f ON l.$linkIdColumn = f.id
        WHERE f.ticket = ?
    ";
    $stmtFind = sqlsrv_query($conn, $sqlFind, [$ticket]);
    $links = [];
    if ($stmtFind) {
        while ($row = sqlsrv_fetch_array($stmtFind, SQLSRV_FETCH_ASSOC)) {
            $links[] = $row;
        }
        sqlsrv_free_stmt($stmtFind);
    }

    // 2. Hapus dari tabel link dan tabel purchasing_issues
    foreach ($links as $lnk) {
        $linkId = $lnk['link_id'];
        $issueId = $lnk['issue_id'];

        sqlsrv_query($conn, "DELETE FROM $linkTable WHERE link_id = ?", [$linkId]);
        if (!empty($issueId)) {
            sqlsrv_query($conn, "DELETE FROM dbo.purchasing_issues WHERE issue_id = ?", [$issueId]);
        }
    }

    return true;
}
