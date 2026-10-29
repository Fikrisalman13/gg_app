<?php
/**
 * Memeriksa hak pengguna terhadap menu Issues Purchasing.
 *
 * @param resource|null $conn Koneksi SQL Server aktif.
 * @return array{allowed: bool, status: int, message: string, can_add: bool, can_edit: bool, can_delete: bool} Hasil otorisasi aman.
 */
function purchasingIssueAccess($conn): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $denied = static function (int $status, string $message): array {
        return [
            'allowed' => false,
            'status' => $status,
            'message' => $message,
            'can_add' => false,
            'can_edit' => false,
            'can_delete' => false
        ];
    };

    $username = trim((string) ($_SESSION['UserName'] ?? ''));
    if ($username === '') {
        return $denied(401, 'Silakan login terlebih dahulu.');
    }

    if ($conn === null) {
        return $denied(500, 'Akses tidak dapat diverifikasi.');
    }

    $userStatement = sqlsrv_query(
        $conn,
        'SELECT TOP (2) UserName, GroupId FROM dbo.SMUserMs WHERE UserName = ?',
        [$username]
    );
    if ($userStatement === false) {
        return $denied(500, 'Akses tidak dapat diverifikasi.');
    }

    $userRows = purchasingIssueFetchRows($userStatement);
    sqlsrv_free_stmt($userStatement);
    if ($userRows === null) {
        return $denied(500, 'Akses tidak dapat diverifikasi.');
    }

    if (count($userRows) !== 1 || empty($userRows[0]['GroupId'])) {
        return $denied(403, 'Anda tidak memiliki hak untuk melihat halaman ini.');
    }

    $trusteeStatement = sqlsrv_query(
        $conn,
        'SELECT TOP (2) CanView, CanAdd, CanEdit, CanDelete
         FROM dbo.SMGroupTrustee
         WHERE GroupId = ? AND MenuId = ?',
        [$userRows[0]['GroupId'], 1329]
    );
    if ($trusteeStatement === false) {
        return $denied(500, 'Akses tidak dapat diverifikasi.');
    }

    $trusteeRows = purchasingIssueFetchRows($trusteeStatement);
    sqlsrv_free_stmt($trusteeStatement);
    if ($trusteeRows === null) {
        return $denied(500, 'Akses tidak dapat diverifikasi.');
    }

    if (count($trusteeRows) !== 1 || (int) ($trusteeRows[0]['CanView'] ?? 0) !== 1) {
        return $denied(403, 'Anda tidak memiliki hak untuk melihat halaman ini.');
    }

    return [
        'allowed' => true,
        'status' => 200,
        'message' => 'OK',
        'can_add' => (int) ($trusteeRows[0]['CanAdd'] ?? 0) === 1,
        'can_edit' => (int) ($trusteeRows[0]['CanEdit'] ?? 0) === 1,
        'can_delete' => (int) ($trusteeRows[0]['CanDelete'] ?? 0) === 1
    ];
}

/**
 * Mengambil seluruh baris statement kecil dan mengembalikan null saat driver gagal membaca.
 *
 * @param resource $statement Statement SQL Server yang sudah dieksekusi.
 * @return array<int, array<string, mixed>>|null Baris hasil atau null bila gagal.
 */
function purchasingIssueFetchRows($statement): ?array
{
    $rows = [];
    while (true) {
        $row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC);
        if ($row === null) {
            return $rows;
        }
        if ($row === false) {
            return null;
        }
        $rows[] = $row;
    }
}

/**
 * Mengambil snapshot profil organisasi pengguna aktif dari master karyawan.
 *
 * Nilai dari master dipakai untuk tampilan dan saat menyimpan issue agar tidak
 * bergantung pada session login lama yang hanya memuat nama pengguna.
 *
 * @param resource|null $conn Koneksi SQL Server aktif.
 * @param string $username Nama pengguna dari sesi yang sudah diautentikasi.
 * @return array{name: string, jabatan: string, departemen: string, bagian: string} Profil aman untuk tampilan atau snapshot issue.
 */
function purchasingIssueCurrentProfile($conn, string $username): array
{
    $fallbackName = trim((string) ($_SESSION['NamaLengkap'] ?? $username));
    $profile = [
        'name' => $fallbackName !== '' ? $fallbackName : $username,
        'jabatan' => trim((string) ($_SESSION['Jabatan'] ?? '')),
        'departemen' => trim((string) ($_SESSION['Departemen'] ?? '')),
        'bagian' => trim((string) ($_SESSION['Bagian'] ?? ''))
    ];

    if ($conn === null || $username === '') {
        return $profile;
    }

    $statement = sqlsrv_query(
        $conn,
        'SELECT TOP (1)
            COALESCE(NULLIF(LTRIM(RTRIM(e.nama_lengkap)), \'\'), u.UserName) AS name,
            COALESCE(NULLIF(LTRIM(RTRIM(j.jabatan)), \'\'), \'\') AS jabatan,
            COALESCE(NULLIF(LTRIM(RTRIM(d.dept)), \'\'), \'\') AS departemen,
            COALESCE(NULLIF(LTRIM(RTRIM(b.bagian)), \'\'), \'\') AS bagian
         FROM dbo.SMUserMs AS u
         LEFT JOIN dbo.m_emp AS e ON e.id_emp = u.EmpId
         LEFT JOIN dbo.m_jab AS j ON j.id_jab = e.id_jab
         LEFT JOIN dbo.m_bag AS b ON b.id_bag = e.id_bag
         LEFT JOIN dbo.m_dept AS d ON d.id_dept = e.id_dept
         WHERE u.UserName = ?',
        [$username]
    );
    if ($statement === false) {
        return $profile;
    }

    $row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($statement);
    if (!is_array($row)) {
        return $profile;
    }

    foreach (['name', 'jabatan', 'departemen', 'bagian'] as $field) {
        $value = trim((string) ($row[$field] ?? ''));
        if ($value !== '') {
            $profile[$field] = $value;
        }
    }

    return $profile;
}

/**
 * Memastikan pengguna mempunyai hak aksi menu yang diminta.
 *
 * @param array{allowed: bool, can_add: bool, can_edit: bool, can_delete: bool} $access Hasil otorisasi menu.
 * @param string $action Nama aksi: add, edit, atau delete.
 * @return bool True bila aksi diizinkan.
 */
function purchasingIssueCan(array $access, string $action): bool
{
    $permissionKey = [
        'add' => 'can_add',
        'edit' => 'can_edit',
        'delete' => 'can_delete'
    ][$action] ?? null;

    return !empty($access['allowed'])
        && $permissionKey !== null
        && !empty($access[$permissionKey]);
}

/**
 * Menghasilkan token CSRF sesi untuk seluruh formulir Purchasing Issue.
 *
 * @return string Token acak yang aman untuk ditempatkan pada halaman terautentikasi.
 */
function purchasingIssueCsrfToken(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $token = $_SESSION['purchasing_issue_csrf'] ?? null;
    if (!is_string($token) || strlen($token) !== 64) {
        $token = bin2hex(random_bytes(32));
        $_SESSION['purchasing_issue_csrf'] = $token;
    }

    return $token;
}

/**
 * Memverifikasi token CSRF dari header atau body untuk mutasi berbasis sesi.
 *
 * @return bool True bila token valid.
 */
function purchasingIssueCsrfIsValid(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $expected = $_SESSION['purchasing_issue_csrf'] ?? null;
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');

    return is_string($expected)
        && is_string($provided)
        && hash_equals($expected, $provided);
}

/**
 * Mengirim penolakan akses dengan bentuk JSON atau redirect halaman yang aman.
 *
 * @param array{allowed: bool, status: int, message: string} $access Hasil otorisasi.
 * @param bool $json Menentukan format respons JSON atau redirect halaman.
 * @return void
 */
function denyPurchasingIssueAccess(array $access, bool $json = false): void
{
    if (!empty($access['allowed'])) {
        return;
    }

    http_response_code((int) ($access['status'] ?? 403));
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => (string) ($access['message'] ?? 'Akses ditolak.')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (($access['status'] ?? 0) === 401) {
        $_SESSION['error'] = 'Silakan login terlebih dahulu.';
        header('Location: /gg_app/login.php');
        exit;
    }

    $_SESSION['error'] = 'Anda tidak memiliki hak untuk melihat halaman ini.';
    header('Location: /gg_app/pages/dashboard.php');
    exit;
}
