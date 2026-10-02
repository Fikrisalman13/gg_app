<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require '../../koneksi.php';
$requestId = bin2hex(random_bytes(8));

function nikLookupRespond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function nikLookupLog(string $requestId, string $action, string $message, array $context = []): void
{
    $logDir = dirname(__DIR__, 2) . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $entry = [
        'timestamp' => date(DATE_ATOM), 'severity' => 'ERROR', 'request_id' => $requestId,
        'module' => 'sm_employee', 'action' => $action,
        'user' => isset($_SESSION['UserName']) ? substr((string) $_SESSION['UserName'], 0, 50) : null,
        'message' => $message, 'source_file' => basename(__FILE__), 'source_line' => null,
        'context' => $context,
    ];
    @file_put_contents($logDir . '/error-' . date('Y-m-d') . '.log', json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function nikLookupDate($value): ?string
{
    if ($value instanceof DateTimeInterface) return $value->format('Y-m-d');
    if ($value === null || trim((string) $value) === '') return null;
    foreach (['d/m/Y H:i:s.u', 'd/m/Y H:i:s', 'd/m/Y'] as $format) {
        $date = DateTime::createFromFormat($format, trim((string) $value));
        if ($date !== false) return $date->format('Y-m-d');
    }
    return null;
}

function nikLookupMapValue($value): ?string
{
    if ($value === null) return null;
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function nikLookupTitleCase($value): ?string
{
    $value = nikLookupMapValue($value);
    if ($value === null || $value !== mb_strtoupper($value, 'UTF-8')) return $value;
    return mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
}

function nikLookupNormalize(array $row): array
{
    $sex = strtolower(trim((string) ($row['SEX'] ?? '')));
    $gender = in_array($sex, ['pria', 'laki-laki', 'l'], true) ? 'L' : (in_array($sex, ['wanita', 'perempuan', 'p'], true) ? 'P' : null);
    $marital = ['belum menikah' => 'Belum Kawin', 'belum kawin' => 'Belum Kawin', 'sudah menikah' => 'Kawin', 'kawin' => 'Kawin', 'cerai hidup' => 'Cerai Hidup', 'cerai mati' => 'Cerai Mati'][strtolower(trim((string) ($row['STATUS_KAWIN'] ?? '')))] ?? null;
    $religion = ['01' => 'Islam', '1' => 'Islam', '02' => 'Kristen', '2' => 'Kristen', '03' => 'Katolik', '3' => 'Katolik', '04' => 'Hindu', '4' => 'Hindu', '05' => 'Budha', '5' => 'Budha', '06' => 'Konghucu', '6' => 'Konghucu'][trim((string) ($row['kd_agama'] ?? ''))] ?? null;
    $educationRaw = strtolower(trim((string) ($row['pend_akhir'] ?? '')));
    $education = null;
    foreach (['SD', 'SMP', 'SMA', 'D1', 'D2', 'D3', 'S1', 'S2', 'S3'] as $option) {
        if ($educationRaw === strtolower($option) || str_starts_with($educationRaw, strtolower($option) . '/')) { $education = $option; break; }
    }
    $blood = strtoupper(trim((string) ($row['gol_darah'] ?? '')));
    $data = [
        'nama_lengkap' => nikLookupTitleCase($row['NAMA_LENGKAP'] ?? null), 'alamat' => nikLookupTitleCase($row['ALAMAT'] ?? null),
        'kd_pos' => nikLookupMapValue($row['kd_pos'] ?? null), 'telp' => nikLookupMapValue($row['TELP'] ?? null), 'kelamin' => $gender,
        'kebangsaan' => nikLookupTitleCase($row['KEBANGSAAN'] ?? null), 'tmp_lahir' => nikLookupTitleCase($row['TMP_LAHIR'] ?? null),
        'tgl_lahir' => nikLookupDate($row['TGL_LAHIR'] ?? null), 'status_kawin' => $marital,
        'jml_anak' => isset($row['jml_anak']) ? (string) max(0, (int) $row['jml_anak']) : null, 'agama' => $religion,
        'gol_darah' => in_array($blood, ['A', 'B', 'AB', 'O'], true) ? $blood : null, 'pend_akhir' => $education,
        'jurusan' => $education !== null && !in_array($education, ['SD', 'SMP'], true) ? nikLookupTitleCase($row['jurusan'] ?? null) : null,
        'no_rek' => nikLookupMapValue($row['no_rek'] ?? null), 'bank' => nikLookupMapValue($row['bank'] ?? null),
        'no_jamsostek' => nikLookupMapValue($row['no_jamsostek'] ?? null), 'no_bpjs' => nikLookupMapValue($row['noBPJS'] ?? null),
        'no_ktp' => nikLookupMapValue($row['noKTP'] ?? null),
    ];
    return array_filter($data, static fn($value) => $value !== null);
}

if (!isset($_SESSION['UserName'], $_SESSION['GroupId'])) nikLookupRespond(401, ['ok' => false, 'message' => 'Sesi login tidak valid.', 'request_id' => $requestId]);
$permissionStmt = sqlsrv_query($conn, 'SELECT CanAdd FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?', [$_SESSION['GroupId'], 44]);
if ($permissionStmt === false) {
    nikLookupLog($requestId, 'authorize_lookup', 'Gagal memeriksa hak akses.', ['dependency' => 'GG']);
    nikLookupRespond(500, ['ok' => false, 'message' => 'Hak akses tidak dapat diperiksa.', 'request_id' => $requestId]);
}
$permission = sqlsrv_fetch_array($permissionStmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($permissionStmt);
if (!$permission || (int) $permission['CanAdd'] !== 1) nikLookupRespond(403, ['ok' => false, 'message' => 'Tidak memiliki hak tambah karyawan.', 'request_id' => $requestId]);

$action = (string) ($_GET['action'] ?? '');
$term = trim((string) ($_GET['term'] ?? ''));
if (!in_array($action, ['suggest', 'detail'], true)) nikLookupRespond(404, ['ok' => false, 'message' => 'Aksi pencarian tidak dikenal.', 'request_id' => $requestId]);
if (($action === 'suggest' && mb_strlen($term) < 2) || ($action === 'detail' && $term === '')) nikLookupRespond(422, ['ok' => false, 'message' => 'NIK belum cukup untuk dicari.', 'request_id' => $requestId]);
if (mb_strlen($term) > 20) nikLookupRespond(422, ['ok' => false, 'message' => 'NIK terlalu panjang.', 'request_id' => $requestId]);

define('KONEKSI2_NON_FATAL', true);
ob_start();
require '../../koneksi2.php';
ob_end_clean();
if (!isset($conn2) || !$conn2) {
    nikLookupLog($requestId, $action, 'Database SUM tidak tersedia.', ['dependency' => 'SUM']);
    nikLookupRespond(503, ['ok' => false, 'message' => 'Sumber data SUM sedang tidak tersedia. NIK tetap dapat diisi manual.', 'request_id' => $requestId]);
}

if ($action === 'suggest') {
    $stmt = sqlsrv_query($conn2, 'SELECT TOP 200 NIK FROM dbo.m_nik WHERE aktif = ? AND NIK LIKE ? ORDER BY NIK ASC', ['1', $term . '%']);
    if ($stmt === false) {
        nikLookupLog($requestId, $action, 'Query saran NIK gagal.', ['dependency' => 'SUM']);
        nikLookupRespond(503, ['ok' => false, 'message' => 'Saran NIK tidak dapat dimuat. NIK tetap dapat diisi manual.', 'request_id' => $requestId]);
    }
    $candidates = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $candidate = trim((string) ($row['NIK'] ?? ''));
        if ($candidate !== '') $candidates[] = $candidate;
    }
    sqlsrv_free_stmt($stmt);
    $used = [];
    foreach (array_chunk($candidates, 100) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $usedStmt = sqlsrv_query($conn, "SELECT nik FROM dbo.m_emp WHERE nik IN ($placeholders)", $chunk);
        if ($usedStmt === false) {
            nikLookupLog($requestId, $action, 'Filter NIK lokal gagal.', ['dependency' => 'GG', 'candidate_count' => count($chunk)]);
            nikLookupRespond(500, ['ok' => false, 'message' => 'Saran NIK tidak dapat disaring.', 'request_id' => $requestId]);
        }
        while ($row = sqlsrv_fetch_array($usedStmt, SQLSRV_FETCH_ASSOC)) $used[(string) $row['nik']] = true;
        sqlsrv_free_stmt($usedStmt);
    }
    $items = array_values(array_filter($candidates, static fn($nik) => !isset($used[$nik])));
    nikLookupRespond(200, ['ok' => true, 'items' => $items, 'request_id' => $requestId]);
}

$stmt = sqlsrv_query($conn2, 'SELECT TOP 1 NIK, aktif, NAMA_LENGKAP, ALAMAT, kd_pos, TELP, SEX, KEBANGSAAN, TMP_LAHIR, TGL_LAHIR, STATUS_KAWIN, jml_anak, kd_agama, gol_darah, pend_akhir, jurusan, no_rek, bank, no_jamsostek, noBPJS, noKTP FROM dbo.m_nik WHERE NIK = ?', [$term]);
if ($stmt === false) {
    nikLookupLog($requestId, $action, 'Query detail NIK gagal.', ['dependency' => 'SUM']);
    nikLookupRespond(503, ['ok' => false, 'message' => 'Data SUM tidak dapat diperiksa. NIK tetap dapat diisi manual.', 'request_id' => $requestId]);
}
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);
if (!$row) nikLookupRespond(200, ['ok' => true, 'found' => false, 'message' => 'NIK tidak ditemukan di HRIS. Data dapat diisi manual.', 'request_id' => $requestId]);
$active = (string) ($row['aktif'] ?? '') === '1';
nikLookupRespond(200, ['ok' => true, 'found' => true, 'active' => $active, 'message' => $active ? 'NIK aktif ditemukan di HRIS.' : 'NIK ditemukan tetapi tidak aktif. Data lain tidak diisi otomatis.', 'data' => $active ? nikLookupNormalize($row) : new stdClass(), 'request_id' => $requestId]);
