<?php
require_once __DIR__ . '/issue_access.php';

/**
 * Mengirim respons JSON Purchasing Issue yang tidak menyertakan detail teknis.
 *
 * @param int $status Kode HTTP respons.
 * @param bool $success Menandakan keberhasilan operasi.
 * @param string $message Pesan aman untuk pengguna.
 * @param array<string, mixed> $extra Data tambahan yang sudah aman diekspos.
 * @return void
 */
function purchasingIssueJson(int $status, bool $success, string $message, array $extra = []): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(
        array_merge(['success' => $success, 'message' => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
}

/**
 * Memeriksa method, login, hak aksi, dan CSRF sebelum endpoint mutasi berjalan.
 *
 * @param resource|null $conn Koneksi SQL Server aktif.
 * @param string $action Hak aksi: add, edit, atau delete.
 * @return array{username: string, access: array<string, mixed>}|null Konteks mutasi atau null bila respons penolakan telah dikirim.
 */
function purchasingIssueMutationContext($conn, string $action): ?array
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        purchasingIssueJson(405, false, 'Metode permintaan tidak didukung.');
        return null;
    }

    $access = purchasingIssueAccess($conn);
    if (empty($access['allowed'])) {
        purchasingIssueJson((int) $access['status'], false, (string) $access['message']);
        return null;
    }

    if (!purchasingIssueCan($access, $action)) {
        purchasingIssueJson(403, false, 'Anda tidak memiliki hak untuk menjalankan aksi ini.');
        return null;
    }

    if (!purchasingIssueCsrfIsValid()) {
        purchasingIssueJson(403, false, 'Permintaan tidak dapat diverifikasi. Muat ulang halaman lalu coba kembali.');
        return null;
    }

    return [
        'username' => trim((string) ($_SESSION['UserName'] ?? '')),
        'access' => $access
    ];
}

/**
 * Menulis log kegagalan teknis tanpa SQL, payload, atau data pribadi.
 *
 * @param string $action Nama aksi yang gagal.
 * @param string $stage Tahap internal yang diizinkan untuk diagnosis.
 * @param string $requestId ID aman untuk korelasi pengguna dan log.
 * @return void
 */
function purchasingIssueLogFailure(string $action, string $stage, string $requestId): void
{
    $logDirectory = dirname(__DIR__, 2) . '/logs';
    if (!is_dir($logDirectory)) {
        @mkdir($logDirectory, 0750, true);
    }

    $entry = [
        'timestamp' => gmdate('c'),
        'severity' => 'ERROR',
        'module' => 'issues_purchasing',
        'action' => $action,
        'stage' => $stage,
        'request_id' => $requestId
    ];
    $json = json_encode($entry, JSON_UNESCAPED_SLASHES);
    if ($json !== false) {
        @file_put_contents($logDirectory . '/error-' . date('Y-m-d') . '.log', $json . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Menghasilkan ID korelasi non-sensitif untuk respons gagal.
 *
 * @return string ID hex acak.
 */
function purchasingIssueOperationId(): string
{
    return bin2hex(random_bytes(12));
}

/**
 * Membatasi dan memvalidasi teks input yang wajib atau opsional.
 *
 * @param mixed $value Nilai input mentah.
 * @param string $fieldLabel Nama aman untuk pesan validasi.
 * @param int $maxLength Batas panjang karakter.
 * @param bool $required Menentukan apakah nilai wajib ada.
 * @return array{valid: bool, value: string|null, message: string} Hasil normalisasi.
 */
function purchasingIssueText($value, string $fieldLabel, int $maxLength, bool $required = false): array
{
    if (!is_string($value)) {
        return [
            'valid' => !$required,
            'value' => null,
            'message' => $required ? $fieldLabel . ' wajib diisi.' : ''
        ];
    }

    $text = trim($value);
    if ($text === '') {
        return [
            'valid' => !$required,
            'value' => null,
            'message' => $required ? $fieldLabel . ' wajib diisi.' : ''
        ];
    }

    if (mb_strlen($text) > $maxLength) {
        return [
            'valid' => false,
            'value' => null,
            'message' => $fieldLabel . ' terlalu panjang.'
        ];
    }

    return ['valid' => true, 'value' => $text, 'message' => ''];
}

/**
 * Memvalidasi satu pilihan dengan daftar nilai yang diizinkan dan menyiapkan
 * pesan yang menjelaskan field input kepada pengguna.
 *
 * @param mixed $value Nilai input mentah.
 * @param array<int, string> $allowedValues Nilai pilihan yang diizinkan.
 * @param string $fieldLabel Nama field yang aman untuk pesan validasi.
 * @return array{valid: bool, value: string|null, message: string} Hasil validasi pilihan.
 */
function purchasingIssueChoice($value, array $allowedValues, string $fieldLabel): array
{
    if (!is_string($value)) {
        return ['valid' => false, 'value' => null, 'message' => $fieldLabel . ' wajib dipilih.'];
    }

    $choice = trim($value);
    if ($choice === '') {
        return ['valid' => false, 'value' => null, 'message' => $fieldLabel . ' wajib dipilih.'];
    }

    if (!in_array($choice, $allowedValues, true)) {
        return ['valid' => false, 'value' => null, 'message' => $fieldLabel . ' tidak valid.'];
    }

    return ['valid' => true, 'value' => $choice, 'message' => ''];
}

/**
 * Mengambil pesan pertama dari kumpulan hasil validasi yang gagal.
 *
 * @param array<int, array{valid: bool, message: string}> $validationResults Hasil validasi berurutan.
 * @return string|null Pesan aman untuk input pertama yang perlu diperbaiki, atau null bila semua valid.
 */
function purchasingIssueFirstValidationMessage(array $validationResults): ?string
{
    foreach ($validationResults as $result) {
        if (empty($result['valid'])) {
            return (string) ($result['message'] ?? 'Input issue tidak valid.');
        }
    }

    return null;
}

/**
 * Memvalidasi tanggal ISO tanpa meneruskan nilai ambigu ke SQL Server.
 *
 * @param mixed $value Nilai input mentah.
 * @param bool $required Menentukan apakah tanggal wajib ada.
 * @return array{valid: bool, value: string|null, message: string} Hasil validasi.
 */
function purchasingIssueDate($value, bool $required = false): array
{
    if ($value === null || $value === '') {
        return [
            'valid' => !$required,
            'value' => null,
            'message' => $required ? 'Tanggal wajib diisi.' : ''
        ];
    }

    if (!is_string($value)) {
        return ['valid' => false, 'value' => null, 'message' => 'Tanggal tidak valid.'];
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if ($date === false || $date->format('Y-m-d') !== $value) {
        return ['valid' => false, 'value' => null, 'message' => 'Tanggal tidak valid.'];
    }

    return ['valid' => true, 'value' => $value, 'message' => ''];
}

/**
 * Memvalidasi UUID request id untuk retry Create yang idempoten.
 *
 * @param mixed $value Nilai input mentah.
 * @return string|null UUID kanonis atau null bila tidak valid.
 */
function purchasingIssueRequestId($value): ?string
{
    if (!is_string($value)) {
        return null;
    }

    $requestId = strtolower(trim($value));
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $requestId)) {
        return null;
    }

    return $requestId;
}

/**
 * Memvalidasi representasi rowversion heksadesimal dari server.
 *
 * @param mixed $value Nilai input mentah.
 * @return string|null Rowversion `0x...` atau null bila tidak valid.
 */
function purchasingIssueRowVersion($value): ?string
{
    if (!is_string($value)) {
        return null;
    }

    $rowVersion = strtoupper(trim($value));
    return preg_match('/^0X[0-9A-F]{16}$/', $rowVersion) ? $rowVersion : null;
}

/**
 * Memastikan pasangan kategori dan subkategori aktif serta sesuai FK komposit aplikasi.
 *
 * @param resource $conn Koneksi SQL Server aktif.
 * @param mixed $categoryValue ID kategori mentah.
 * @param mixed $subValue ID subkategori mentah.
 * @return array{valid: bool, category_id: int|null, sub_id: int|null, message: string} Hasil validasi relasi.
 */
function purchasingIssueCategoryPair($conn, $categoryValue, $subValue): array
{
    $categoryId = filter_var($categoryValue, FILTER_VALIDATE_INT);
    if ($categoryId === false || $categoryId < 1) {
        return ['valid' => false, 'category_id' => null, 'sub_id' => null, 'message' => 'Kategori wajib dipilih.'];
    }

    $categoryStatement = sqlsrv_query(
        $conn,
        'SELECT category_id FROM dbo.purchasing_issue_categories WHERE category_id = ? AND is_active = 1',
        [$categoryId]
    );
    if ($categoryStatement === false) {
        return ['valid' => false, 'category_id' => null, 'sub_id' => null, 'message' => 'Kategori tidak dapat diverifikasi.'];
    }

    $category = sqlsrv_fetch_array($categoryStatement, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($categoryStatement);
    if ($category === false || $category === null) {
        return ['valid' => false, 'category_id' => null, 'sub_id' => null, 'message' => 'Kategori tidak tersedia.'];
    }

    if ($subValue === null || $subValue === '') {
        return ['valid' => false, 'category_id' => null, 'sub_id' => null, 'message' => 'Subkategori wajib dipilih setelah kategori dipilih.'];
    }

    $subId = filter_var($subValue, FILTER_VALIDATE_INT);
    if ($subId === false || $subId < 1) {
        return ['valid' => false, 'category_id' => null, 'sub_id' => null, 'message' => 'Subkategori tidak valid.'];
    }

    $subStatement = sqlsrv_query(
        $conn,
        'SELECT sub_id
         FROM dbo.purchasing_issue_sub_categories
         WHERE sub_id = ? AND category_id = ? AND is_active = 1',
        [$subId, $categoryId]
    );
    if ($subStatement === false) {
        return ['valid' => false, 'category_id' => null, 'sub_id' => null, 'message' => 'Subkategori tidak dapat diverifikasi.'];
    }

    $sub = sqlsrv_fetch_array($subStatement, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($subStatement);
    if ($sub === false || $sub === null) {
        return ['valid' => false, 'category_id' => null, 'sub_id' => null, 'message' => 'Subkategori tidak tersedia untuk kategori tersebut.'];
    }

    return ['valid' => true, 'category_id' => $categoryId, 'sub_id' => $subId, 'message' => ''];
}

/**
 * Mengirim kegagalan database yang dapat dikorelasikan tanpa mengekspos driver maupun SQL.
 *
 * @param string $action Nama aksi endpoint.
 * @param string $stage Tahap yang diizinkan untuk log.
 * @return void
 */
function purchasingIssueDatabaseFailure(string $action, string $stage): void
{
    $operationId = purchasingIssueOperationId();
    purchasingIssueLogFailure($action, $stage, $operationId);
    purchasingIssueJson(500, false, 'Operasi tidak dapat diselesaikan. ID permintaan: ' . $operationId . '.');
}
