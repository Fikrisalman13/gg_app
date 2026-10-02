<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../../koneksi.php';
date_default_timezone_set('Asia/Jakarta');

function getKioskAccessToken(): string
{
    // Token khusus layar kiosk fullscreen agar tetap bisa polling saat session user berakhir.
    return hash('sha256', 'gg_app_dashboard_kantin_kiosk_always_on_v1');
}

function esc($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function jsonOut(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function normalizePhotoUrl(string $rawPath): string
{
    $path = trim(str_replace('\\', '/', $rawPath));
    if ($path === '') {
        return '';
    }

    $path = preg_replace('#^(\./|\.\./)+#', '', $path);
    if ($path === null) {
        $path = trim($rawPath);
    }

    if (preg_match('/^(https?:)?\/\//i', $path) === 1 || stripos($path, 'data:image/') === 0) {
        return $path;
    }

    $uploadsPos = stripos($path, 'uploads/');
    if ($uploadsPos !== false) {
        $path = substr($path, $uploadsPos);
    }

    if (strpos($path, '/gg_app/') === 0) {
        return $path;
    }

    if (strpos($path, '/') === 0) {
        return '/gg_app' . $path;
    }

    if (stripos($path, 'uploads/') === 0) {
        return '/gg_app/' . $path;
    }

    if (stripos($path, 'foto_karyawan/') === 0) {
        return '/gg_app/uploads/' . $path;
    }

    return '/gg_app/uploads/foto_karyawan/' . $path;
}

function fallbackAvatarDataUri(): string
{
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="360" height="480" viewBox="0 0 360 480">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#ecf4ef"/>
      <stop offset="100%" stop-color="#d6e8dc"/>
    </linearGradient>
  </defs>
  <rect width="360" height="480" fill="url(#g)"/>
  <circle cx="180" cy="165" r="70" fill="#8cb09a"/>
  <rect x="95" y="250" width="170" height="140" rx="24" fill="#7a9f89"/>
  <rect x="70" y="372" width="220" height="80" rx="18" fill="#5a7f69"/>
</svg>
SVG;

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function getMempColumns($conn): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='dbo' AND TABLE_NAME='m_emp'";
    $stmt = sqlsrv_query($conn, $sql);
    $columns = [];

    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $col = (string)($row['COLUMN_NAME'] ?? '');
            if ($col !== '') {
                $columns[strtolower($col)] = $col;
            }
        }
        sqlsrv_free_stmt($stmt);
    }

    $cache = $columns;
    return $cache;
}

function detectPhotoColumn($conn): string
{
    $columns = getMempColumns($conn);
    $candidates = [
        'foto_path', 'photo_path', 'foto_karyawan', 'photo_karyawan',
        'foto', 'photo', 'image', 'image_path',
        'img', 'img_path', 'profile_photo'
    ];
    foreach ($candidates as $candidate) {
        if (isset($columns[$candidate])) {
            return $columns[$candidate];
        }
    }
    return '';
}

function detectEmpNameColumn($conn): string
{
    $columns = getMempColumns($conn);
    $candidates = ['nama_lengkap', 'nama', 'name', 'full_name'];
    foreach ($candidates as $candidate) {
        if (isset($columns[$candidate])) {
            return $columns[$candidate];
        }
    }
    return '';
}

function formatDateIndo(string $dateYmd): string
{
    $dt = DateTime::createFromFormat('Y-m-d', $dateYmd);
    if (!$dt) {
        return $dateYmd;
    }

    $bulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    $b = $bulan[(int)$dt->format('n')] ?? $dt->format('m');
    return $dt->format('d') . ' ' . $b . ' ' . $dt->format('Y');
}

function numberId($value): string
{
    return number_format((float)$value, 0, ',', '.');
}

function resolveShiftByTime(string $timeHms): string
{
    $time = substr(trim($timeHms), 0, 8);
    if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
        return '-';
    }

    // Batas akhir dibuat eksklusif agar 11:30 masuk Non Shift (bukan Shift Pagi).
    if ($time >= '11:30:00' && $time < '13:30:00') return 'Non Shift';
    if ($time >= '01:00:00' && $time < '04:00:00') return 'Shift Malam';
    if ($time >= '09:00:00' && $time < '11:30:00') return 'Shift Pagi';
    if ($time >= '17:00:00' && $time < '20:00:00') return 'Shift Siang';
    if (($time >= '04:00:00' && $time < '09:00:00') || ($time >= '14:00:00' && $time < '17:00:00') || ($time >= '20:00:00' || $time < '01:00:00')) {
        return 'No All';
    }
    return '-';
}

function quickDuplicateAcceptMarker(): string
{
    return '__ACCEPT_DUP_1MIN__';
}

function rejectOverTwoMarker(): string
{
    return '__REJECT_OVER2__';
}

function getLogDashboardColumns($conn): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='dbo' AND TABLE_NAME='LogDashboardKantin'";
    $stmt = sqlsrv_query($conn, $sql);
    $columns = [];

    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $col = (string)($row['COLUMN_NAME'] ?? '');
            if ($col !== '') {
                $columns[strtolower($col)] = $col;
            }
        }
        sqlsrv_free_stmt($stmt);
    }

    $cache = $columns;
    return $cache;
}

function fetchDashboardMachines($conn): array
{
    $rows = [];
    $sql = "SELECT CAST(id AS NVARCHAR(100)) AS id, COALESCE(NULLIF(nama_mesin, ''), '-') AS nama_mesin
            FROM dbo.m_fingerprint
            ORDER BY id DESC";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt) {
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $r;
        }
        sqlsrv_free_stmt($stmt);
    }
    return $rows;
}

function fetchLatestAttendance($conn, string $mesinFilter, string $tanggalAwal, string $tanggalAkhir): ?array
{
    $photoColumn = detectPhotoColumn($conn);
    $empNameColumn = detectEmpNameColumn($conn);
    $logCols = getLogDashboardColumns($conn);
    $photoSelect = $photoColumn !== '' ? ", emp.[$photoColumn] AS emp_photo" : ", CAST(NULL AS NVARCHAR(255)) AS emp_photo";
    $empNameExpr = $empNameColumn !== '' ? "emp.[$empNameColumn]" : "NULL";
    $empNameOrderExpr = $empNameColumn !== '' ? "e.[$empNameColumn]" : "''";
    $rejectReasonSelect = isset($logCols['rejectreason'])
        ? ", ISNULL(LTRIM(RTRIM(CAST(k.[" . $logCols['rejectreason'] . "] AS NVARCHAR(255)))), '') AS reject_reason"
        : ", CAST('' AS NVARCHAR(255)) AS reject_reason";

    $sql = "SELECT TOP 1
                k.Waktu AS waktu,
                k.UserId AS user_id,
                k.ShiftLabel AS shift_label,
                k.IsRejected AS is_rejected,
                COALESCE(NULLIF(k.Nama, ''), NULLIF($empNameExpr, ''), '-') AS nama_karyawan,
                COALESCE(NULLIF(d.dept, ''), '-') AS dept,
                COALESCE(NULLIF(b.bagian, ''), '-') AS bagian,
                COALESCE(NULLIF(f.nama_mesin, ''), '-') AS nama_mesin,
                CAST(ISNULL(cnt.total_masuk, 1) AS INT) AS total_masuk_hari,
                prev.prev_waktu AS prev_waktu
                $rejectReasonSelect
                $photoSelect
            FROM dbo.LogDashboardKantin AS k
            LEFT JOIN dbo.m_fingerprint AS f ON k.MesinId = f.id
            OUTER APPLY (
                SELECT TOP 1 e.*
                FROM dbo.m_emp AS e
                WHERE CAST(k.UserId AS NVARCHAR(100)) = CAST(e.id_emp AS NVARCHAR(100))
                   OR CAST(k.UserId AS NVARCHAR(100)) = CAST(e.nik AS NVARCHAR(100))
                   OR CAST(k.UserId AS NVARCHAR(100)) = CAST(e.finger_key AS NVARCHAR(100))
                ORDER BY
                    CASE
                        WHEN LTRIM(RTRIM(COALESCE(NULLIF(k.Nama, ''), ''))) <> ''
                             AND LOWER(LTRIM(RTRIM(COALESCE($empNameOrderExpr, ''))))
                                 = LOWER(LTRIM(RTRIM(COALESCE(k.Nama, '')))) THEN 0
                        WHEN CAST(k.UserId AS NVARCHAR(100)) = CAST(e.finger_key AS NVARCHAR(100)) THEN 1
                        WHEN CAST(k.UserId AS NVARCHAR(100)) = CAST(e.id_emp AS NVARCHAR(100)) THEN 2
                        WHEN CAST(k.UserId AS NVARCHAR(100)) = CAST(e.nik AS NVARCHAR(100)) THEN 3
                        ELSE 99
                    END,
                    ISNULL(e.aktif, 0) DESC,
                    e.id_emp DESC
            ) AS emp
            LEFT JOIN dbo.m_dept AS d ON emp.id_dept = d.id_dept
            LEFT JOIN dbo.m_bag AS b ON emp.id_bag = b.id_bag
            OUTER APPLY (
                SELECT COUNT(*) AS total_masuk
                FROM dbo.LogDashboardKantin AS k2
                WHERE CAST(k2.Waktu AS DATE) = CAST(k.Waktu AS DATE)
                  AND CAST(k2.UserId AS NVARCHAR(100)) = CAST(k.UserId AS NVARCHAR(100))
            ) AS cnt
            OUTER APPLY (
                SELECT TOP 1
                    k3.Waktu AS prev_waktu
                FROM dbo.LogDashboardKantin AS k3
                WHERE CAST(k3.UserId AS NVARCHAR(100)) = CAST(k.UserId AS NVARCHAR(100))
                  AND k3.Waktu < k.Waktu
                ORDER BY k3.Waktu DESC, k3.LogId DESC
            ) AS prev
            WHERE k.Waktu >= ?
              AND k.Waktu < DATEADD(DAY, 1, ?)";

    $params = [$tanggalAwal . ' 00:00:00', $tanggalAkhir . ' 00:00:00'];
    if ($mesinFilter !== '') {
        $sql .= " AND CAST(k.MesinId AS NVARCHAR(100)) = ?";
        $params[] = $mesinFilter;
    }

    $sql .= " ORDER BY k.Waktu DESC, k.LogId DESC";
    $stmt = sqlsrv_query($conn, $sql, $params);
    if (!$stmt) {
        return null;
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    if (!$row) {
        return null;
    }

    $waktuRaw = $row['waktu'] ?? ($row['Waktu'] ?? null);
    $dt = null;
    if ($waktuRaw instanceof DateTime) {
        $dt = clone $waktuRaw;
    } elseif (!empty($waktuRaw)) {
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', (string)$waktuRaw) ?: new DateTime((string)$waktuRaw);
    }

    if (!$dt instanceof DateTime) {
        $dt = new DateTime();
    }
    $dt->setTimezone(new DateTimeZone('Asia/Jakarta'));

    $tanggalYmd = $dt->format('Y-m-d');
    $jam = $dt->format('H:i:s');
    $shiftLabel = trim((string)($row['shift_label'] ?? ''));
    if ($shiftLabel === '') {
        $shiftLabel = resolveShiftByTime($jam);
    }
    $eventKey = $dt->format('YmdHis') . '|' . trim((string)($row['user_id'] ?? '')) . '|' . trim((string)($row['nama_mesin'] ?? ''));

    $photoUrl = normalizePhotoUrl((string)($row['emp_photo'] ?? ''));
    if ($photoUrl === '') {
        $photoUrl = fallbackAvatarDataUri();
    }

    $totalMasuk = (int)($row['total_masuk_hari'] ?? 1);
    $isRejectedFlag = (int)($row['is_rejected'] ?? 0) === 1;
    $isViolation = $isRejectedFlag;
    $rejectReasonRaw = trim((string)($row['reject_reason'] ?? ''));
    $rejectAudioKey = ($isViolation && $rejectReasonRaw === rejectOverTwoMarker()) ? 'reject_over2' : 'reject_default';
    $firstScanTime = '';
    $secondScanTime = '';
    if ($isViolation) {
        $dtFirst = null;
        $firstRaw = $row['prev_waktu'] ?? null;
        if ($firstRaw instanceof DateTime) {
            $dtFirst = clone $firstRaw;
        } elseif (!empty($firstRaw)) {
            $dtFirst = DateTime::createFromFormat('Y-m-d H:i:s', (string)$firstRaw) ?: new DateTime((string)$firstRaw);
        }
        if ($dtFirst instanceof DateTime) {
            $dtFirst->setTimezone(new DateTimeZone('Asia/Jakarta'));
            $firstScanTime = $dtFirst->format('H:i:s');
        }
        $secondScanTime = $jam;
    }

    return [
        'event_key' => $eventKey,
        'user_id' => trim((string)($row['user_id'] ?? '')),
        'nama' => (string)($row['nama_karyawan'] ?? '-'),
        'departemen' => (string)($row['dept'] ?? '-'),
        'bagian' => (string)($row['bagian'] ?? '-'),
        'shift_label' => $shiftLabel,
        'mesin' => (string)($row['nama_mesin'] ?? '-'),
        'jam' => $jam,
        'tanggal_iso' => $tanggalYmd,
        'tanggal_text' => formatDateIndo($tanggalYmd),
        'photo_url' => $photoUrl,
        'total_masuk_hari' => $totalMasuk,
        'first_scan_time' => $firstScanTime,
        'second_scan_time' => $secondScanTime,
        'reject_audio_key' => $rejectAudioKey,
        'status_code' => $isViolation ? 'violation' : 'success',
        'status_title' => $isViolation ? 'ABSEN DITOLAK' : 'ABSEN BERHASIL',
        'status_message' => $isViolation
            ? 'Absensi berikutnya hanya boleh setelah jeda minimal 3 jam.'
            : 'Terima kasih, absensi kantin Anda telah tercatat.'
    ];
}

function fetchKioskStats($conn, string $mesinFilter, string $tanggalAwal, string $tanggalAkhir): array
{
    $stats = [
        'total_absensi' => 0,
        'total_karyawan_unik' => 0,
        'hari_aktif' => 0,
        'avg_harian' => 0,
        'total_berhasil' => 0,
        'total_ditolak' => 0,
        'shift_siang' => 0,
        'shift_malam' => 0,
        'shift_pagi' => 0,
        'non_shift' => 0,
        'no_all' => 0
    ];

    $logCols = getLogDashboardColumns($conn);
    $quickMarker = quickDuplicateAcceptMarker();
    $rejectReasonSelect = "CAST('' AS NVARCHAR(255)) AS reject_reason,";
    if (isset($logCols['rejectreason'])) {
        $rejectReasonCol = $logCols['rejectreason'];
        $rejectReasonSelect = "ISNULL(LTRIM(RTRIM(CAST(k.[$rejectReasonCol] AS NVARCHAR(255)))), '') AS reject_reason,";
    }

    $sql = "WITH base AS (
                SELECT
                    CAST(k.Waktu AS DATE) AS tanggal,
                    p.person_key,
                    CASE WHEN ISNULL(k.IsRejected, 0) = 1 THEN 1 ELSE 0 END AS is_violation,
                    $rejectReasonSelect
                    CASE
                        WHEN LOWER(LTRIM(RTRIM(COALESCE(NULLIF(k.ShiftLabel, ''), '')))) IN ('shift siang', 'sift siang') THEN 'Shift Siang'
                        WHEN LOWER(LTRIM(RTRIM(COALESCE(NULLIF(k.ShiftLabel, ''), '')))) = 'shift malam' THEN 'Shift Malam'
                        WHEN LOWER(LTRIM(RTRIM(COALESCE(NULLIF(k.ShiftLabel, ''), '')))) = 'shift pagi' THEN 'Shift Pagi'
                        WHEN LOWER(LTRIM(RTRIM(COALESCE(NULLIF(k.ShiftLabel, ''), '')))) = 'non shift' THEN 'Non Shift'
                        WHEN LOWER(LTRIM(RTRIM(COALESCE(NULLIF(k.ShiftLabel, ''), '')))) = 'no all' THEN 'No All'
                        WHEN CAST(k.Waktu AS TIME) >= '11:30:00' AND CAST(k.Waktu AS TIME) < '13:30:00' THEN 'Non Shift'
                        WHEN CAST(k.Waktu AS TIME) >= '01:00:00' AND CAST(k.Waktu AS TIME) < '04:00:00' THEN 'Shift Malam'
                        WHEN CAST(k.Waktu AS TIME) >= '09:00:00' AND CAST(k.Waktu AS TIME) < '11:30:00' THEN 'Shift Pagi'
                        WHEN CAST(k.Waktu AS TIME) >= '17:00:00' AND CAST(k.Waktu AS TIME) < '20:00:00' THEN 'Shift Siang'
                        WHEN (CAST(k.Waktu AS TIME) >= '04:00:00' AND CAST(k.Waktu AS TIME) < '09:00:00')
                          OR (CAST(k.Waktu AS TIME) >= '14:00:00' AND CAST(k.Waktu AS TIME) < '17:00:00')
                          OR (CAST(k.Waktu AS TIME) >= '20:00:00' OR CAST(k.Waktu AS TIME) < '01:00:00') THEN 'No All'
                        ELSE '-'
                    END AS shift_label
                FROM dbo.LogDashboardKantin AS k
                CROSS APPLY (
                    SELECT CASE
                        WHEN k.UserId IS NULL OR LTRIM(RTRIM(CAST(k.UserId AS NVARCHAR(100)))) = ''
                            THEN COALESCE(NULLIF(LTRIM(RTRIM(k.Nama)), ''), '-')
                        ELSE LTRIM(RTRIM(CAST(k.UserId AS NVARCHAR(100))))
                    END AS person_key
                ) AS p
                WHERE CAST(k.Waktu AS DATE) BETWEEN ? AND ?";

    $params = [$tanggalAwal, $tanggalAkhir];
    if ($mesinFilter !== '') {
        $sql .= " AND CAST(k.MesinId AS NVARCHAR(100)) = ?";
        $params[] = $mesinFilter;
    }

    $sql .= "),
            scored AS (
                SELECT
                    b.*,
                    CASE
                        WHEN b.is_violation = 0
                             AND b.reject_reason <> ?
                            THEN 1
                        ELSE 0
                    END AS is_success_countable
                FROM base AS b
            )
            SELECT
                COUNT(*) AS total_absensi,
                COUNT(DISTINCT scored.person_key) AS total_karyawan_unik,
                COUNT(DISTINCT scored.tanggal) AS hari_aktif,
                ISNULL(SUM(scored.is_success_countable), 0) AS total_berhasil,
                ISNULL(SUM(CASE WHEN scored.is_violation = 1 THEN 1 ELSE 0 END), 0) AS total_ditolak,
                ISNULL(SUM(CASE WHEN scored.is_success_countable = 1 AND scored.shift_label = 'Shift Siang' THEN 1 ELSE 0 END), 0) AS shift_siang,
                ISNULL(SUM(CASE WHEN scored.is_success_countable = 1 AND scored.shift_label = 'Shift Malam' THEN 1 ELSE 0 END), 0) AS shift_malam,
                ISNULL(SUM(CASE WHEN scored.is_success_countable = 1 AND scored.shift_label = 'Shift Pagi' THEN 1 ELSE 0 END), 0) AS shift_pagi,
                ISNULL(SUM(CASE WHEN scored.is_success_countable = 1 AND scored.shift_label = 'Non Shift' THEN 1 ELSE 0 END), 0) AS non_shift,
                ISNULL(SUM(CASE WHEN scored.is_success_countable = 1 AND scored.shift_label = 'No All' THEN 1 ELSE 0 END), 0) AS no_all
            FROM scored";
    $params[] = $quickMarker;

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
        $stats['total_absensi'] = (int)($row['total_absensi'] ?? 0);
        $stats['total_karyawan_unik'] = (int)($row['total_karyawan_unik'] ?? 0);
        $stats['hari_aktif'] = (int)($row['hari_aktif'] ?? 0);
        $stats['total_berhasil'] = (int)($row['total_berhasil'] ?? 0);
        $stats['total_ditolak'] = (int)($row['total_ditolak'] ?? 0);
        $stats['shift_siang'] = (int)($row['shift_siang'] ?? 0);
        $stats['shift_malam'] = (int)($row['shift_malam'] ?? 0);
        $stats['shift_pagi'] = (int)($row['shift_pagi'] ?? 0);
        $stats['non_shift'] = (int)($row['non_shift'] ?? 0);
        $stats['no_all'] = (int)($row['no_all'] ?? 0);
        if ($stats['hari_aktif'] > 0) {
            $stats['avg_harian'] = round($stats['total_berhasil'] / $stats['hari_aktif'], 2);
        }
    }
    if ($stmt) {
        sqlsrv_free_stmt($stmt);
    }

    return $stats;
}

function fetchMachineSuccessCards($conn, string $mesinFilter, string $tanggalAwal, string $tanggalAkhir): array
{
    $cards = [
        'kantin_depan' => ['label' => 'Kantin Depan', 'total' => 0],
        'kantin_produksi' => ['label' => 'Kantin Produksi', 'total' => 0],
        'kantin_weaving_1' => ['label' => 'Kantin Weaving 1', 'total' => 0],
        'kantin_weaving_2' => ['label' => 'Kantin Weaving 2', 'total' => 0],
    ];

    $nameMap = [
        'kantin depan' => 'kantin_depan',
        'kantin produksi' => 'kantin_produksi',
        'kantin weaving 1' => 'kantin_weaving_1',
        'kantin weaving 2' => 'kantin_weaving_2',
    ];

    $logCols = getLogDashboardColumns($conn);
    $quickMarker = quickDuplicateAcceptMarker();
    $rejectReasonSelect = "CAST('' AS NVARCHAR(255)) AS reject_reason,";
    if (isset($logCols['rejectreason'])) {
        $rejectReasonCol = $logCols['rejectreason'];
        $rejectReasonSelect = "ISNULL(LTRIM(RTRIM(CAST(k.[$rejectReasonCol] AS NVARCHAR(255)))), '') AS reject_reason,";
    }

    $sql = "WITH base AS (
                SELECT
                    LOWER(LTRIM(RTRIM(COALESCE(f.nama_mesin, '')))) AS mesin_name,
                    CASE WHEN ISNULL(k.IsRejected, 0) = 1 THEN 1 ELSE 0 END AS is_violation,
                    $rejectReasonSelect
                FROM dbo.LogDashboardKantin AS k
                LEFT JOIN dbo.m_fingerprint AS f ON k.MesinId = f.id
                WHERE CAST(k.Waktu AS DATE) BETWEEN ? AND ?";
    $params = [$tanggalAwal, $tanggalAkhir];

    if ($mesinFilter !== '') {
        $sql .= " AND CAST(k.MesinId AS NVARCHAR(100)) = ?";
        $params[] = $mesinFilter;
    }

    $sql .= "),
            scored AS (
                SELECT
                    b.mesin_name,
                    CASE
                        WHEN b.is_violation = 0
                             AND b.reject_reason <> ?
                            THEN 1
                        ELSE 0
                    END AS is_success_countable
                FROM base AS b
            )
            SELECT
                mesin_name,
                ISNULL(SUM(is_success_countable), 0) AS total
            FROM scored
            GROUP BY mesin_name";
    $params[] = $quickMarker;

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $nameKey = trim((string)($row['mesin_name'] ?? ''));
            if ($nameKey === '' || !isset($nameMap[$nameKey])) {
                continue;
            }
            $cardKey = $nameMap[$nameKey];
            $cards[$cardKey]['total'] = (int)($row['total'] ?? 0);
        }
        sqlsrv_free_stmt($stmt);
    }

    return $cards;
}

function fetchAttendanceRows($conn, string $mesinFilter, string $tanggalAwal, string $tanggalAkhir, int $limit = 300): array
{
    $limit = max(50, min(1000, $limit));
    $empNameColumn = detectEmpNameColumn($conn);
    $empNameOrderExpr = $empNameColumn !== '' ? "e.[$empNameColumn]" : "''";
    $sql = "SELECT TOP {$limit}
                a.waktu,
                a.nama,
                COALESCE(NULLIF(d.dept, ''), '-') AS dept,
                COALESCE(NULLIF(b.bagian, ''), '-') AS bagian,
                COALESCE(NULLIF(f.nama_mesin, ''), '-') AS nama_mesin,
                COUNT(*) OVER (
                    PARTITION BY
                        CAST(a.waktu AS DATE),
                        CASE
                            WHEN a.user_id IS NULL OR CAST(a.user_id AS NVARCHAR(100)) = '' THEN COALESCE(NULLIF(a.nama, ''), '-')
                            ELSE CAST(a.user_id AS NVARCHAR(100))
                        END
                ) AS total_masuk_hari
            FROM dbo.log_absensi AS a
            LEFT JOIN dbo.m_fingerprint AS f ON a.mesin_id = f.id
            OUTER APPLY (
                SELECT TOP 1 e.*
                FROM dbo.m_emp AS e
                WHERE CAST(a.user_id AS NVARCHAR(100)) = CAST(e.id_emp AS NVARCHAR(100))
                   OR CAST(a.user_id AS NVARCHAR(100)) = CAST(e.nik AS NVARCHAR(100))
                   OR CAST(a.user_id AS NVARCHAR(100)) = CAST(e.finger_key AS NVARCHAR(100))
                ORDER BY
                    CASE
                        WHEN LTRIM(RTRIM(COALESCE(NULLIF(a.nama, ''), ''))) <> ''
                             AND LOWER(LTRIM(RTRIM(COALESCE($empNameOrderExpr, ''))))
                                 = LOWER(LTRIM(RTRIM(COALESCE(a.nama, '')))) THEN 0
                        WHEN CAST(a.user_id AS NVARCHAR(100)) = CAST(e.finger_key AS NVARCHAR(100)) THEN 1
                        WHEN CAST(a.user_id AS NVARCHAR(100)) = CAST(e.id_emp AS NVARCHAR(100)) THEN 2
                        WHEN CAST(a.user_id AS NVARCHAR(100)) = CAST(e.nik AS NVARCHAR(100)) THEN 3
                        ELSE 99
                    END,
                    ISNULL(e.aktif, 0) DESC,
                    e.id_emp DESC
            ) AS emp
            LEFT JOIN dbo.m_dept AS d ON emp.id_dept = d.id_dept
            LEFT JOIN dbo.m_bag AS b ON emp.id_bag = b.id_bag
            WHERE CAST(a.waktu AS DATE) BETWEEN ? AND ?";

    $params = [$tanggalAwal, $tanggalAkhir];
    if ($mesinFilter !== '') {
        $sql .= " AND CAST(f.id AS NVARCHAR(100)) = ?";
        $params[] = $mesinFilter;
    }

    $sql .= " ORDER BY a.waktu DESC";
    $stmt = sqlsrv_query($conn, $sql, $params);
    if (!$stmt) {
        return [];
    }

    $rows = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $dt = null;
        if (($row['waktu'] ?? null) instanceof DateTime) {
            $dt = clone $row['waktu'];
        } elseif (!empty($row['waktu'])) {
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', (string)$row['waktu']) ?: new DateTime((string)$row['waktu']);
        }
        if (!($dt instanceof DateTime)) {
            $dt = new DateTime();
        }
        $dt->setTimezone(new DateTimeZone('Asia/Jakarta'));

        $rows[] = [
            'tanggal' => $dt->format('d-m-Y'),
            'jam' => $dt->format('H:i:s'),
            'nama' => (string)($row['nama'] ?? '-'),
            'dept' => (string)($row['dept'] ?? '-'),
            'bagian' => (string)($row['bagian'] ?? '-'),
            'mesin' => (string)($row['nama_mesin'] ?? '-'),
            'total_masuk_hari' => (int)($row['total_masuk_hari'] ?? 1),
        ];
    }
    sqlsrv_free_stmt($stmt);
    return $rows;
}

function fetchEmployeeAttendanceHistory(
    $conn,
    string $mesinFilter,
    string $tanggalAwal,
    string $tanggalAkhir,
    string $userId,
    string $namaKaryawan,
    int $limit = 8
): array {
    $rows = [];
    $userId = trim($userId);
    $namaKaryawan = trim($namaKaryawan);
    if ($userId === '' && $namaKaryawan === '') {
        return $rows;
    }

    $limit = max(1, min(20, $limit));
    $sql = "SELECT TOP {$limit}
                k.Waktu AS waktu,
                ISNULL(k.IsRejected, 0) AS is_rejected
            FROM dbo.LogDashboardKantin AS k
            WHERE k.Waktu >= ?
              AND k.Waktu < DATEADD(DAY, 1, ?)";
    $params = [$tanggalAwal . ' 00:00:00', $tanggalAkhir . ' 00:00:00'];

    if ($mesinFilter !== '') {
        $sql .= " AND CAST(k.MesinId AS NVARCHAR(100)) = ?";
        $params[] = $mesinFilter;
    }

    if ($userId !== '') {
        $sql .= " AND LTRIM(RTRIM(CAST(ISNULL(k.UserId, '') AS NVARCHAR(100)))) = ?";
        $params[] = $userId;
    } else {
        $sql .= " AND LOWER(LTRIM(RTRIM(COALESCE(k.Nama, '')))) = LOWER(?)";
        $params[] = $namaKaryawan;
    }

    $sql .= " ORDER BY k.Waktu DESC, k.LogId DESC";

    $stmt = sqlsrv_query($conn, $sql, $params);
    if (!$stmt) {
        return $rows;
    }

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $dt = null;
        $waktuRaw = $row['waktu'] ?? null;
        if ($waktuRaw instanceof DateTime) {
            $dt = clone $waktuRaw;
        } elseif (!empty($waktuRaw)) {
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', (string)$waktuRaw) ?: new DateTime((string)$waktuRaw);
        }
        if (!($dt instanceof DateTime)) {
            $dt = new DateTime();
        }
        $dt->setTimezone(new DateTimeZone('Asia/Jakarta'));

        $tanggalIso = $dt->format('Y-m-d');
        $isReject = ((int)($row['is_rejected'] ?? 0) === 1);
        $rows[] = [
            'tanggal_iso' => $tanggalIso,
            'tanggal' => formatDateIndo($tanggalIso),
            'jam' => $dt->format('H:i:s'),
            'status_code' => $isReject ? 'reject' : 'success',
            'status_label' => $isReject ? 'Ditolak' : 'Diterima',
        ];
    }
    sqlsrv_free_stmt($stmt);

    return $rows;
}

if (!$conn) {
    if (isset($_GET['ajax']) && $_GET['ajax'] === 'latest') {
        jsonOut(['ok' => false, 'message' => 'Koneksi database gagal.'], 500);
    }
    die('Koneksi database gagal.');
}

if (!isset($_SESSION['UserName'])) {
    if (isset($_GET['ajax']) && $_GET['ajax'] === 'latest') {
        jsonOut(['ok' => false, 'message' => 'Session expired. Silakan login kembali.'], 401);
    }
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

$mesinFilter = isset($_GET['mesin_id']) ? trim((string)$_GET['mesin_id']) : '';
if ($mesinFilter === '' && isset($_GET['mesin'])) {
    $mesinFilter = trim((string)$_GET['mesin']);
}

$todayDate = date('Y-m-d');
$tanggalAwal = $todayDate;
$tanggalAkhir = $todayDate;

$machineOptions = fetchDashboardMachines($conn);

// Default mesin dashboard: Kantin Depan (jika user belum memilih filter mesin).
if ($mesinFilter === '') {
    foreach ($machineOptions as $optMesin) {
        $nameRaw = (string)($optMesin['nama_mesin'] ?? '');
        $nameNorm = strtolower(trim(preg_replace('/\s+/', ' ', $nameRaw) ?? $nameRaw));
        if ($nameNorm === 'kantin depan') {
            $mesinFilter = (string)($optMesin['id'] ?? '');
            break;
        }
    }
    if ($mesinFilter === '') {
        foreach ($machineOptions as $optMesin) {
            $nameRaw = (string)($optMesin['nama_mesin'] ?? '');
            $nameNorm = strtolower(trim(preg_replace('/\s+/', ' ', $nameRaw) ?? $nameRaw));
            if (strpos($nameNorm, 'kantin depan') !== false) {
                $mesinFilter = (string)($optMesin['id'] ?? '');
                break;
            }
        }
    }
}

$selectedMachineLabel = 'Semua Mesin';
$selectedInOptions = false;
foreach ($machineOptions as $m) {
    if ((string)($m['id'] ?? '') === $mesinFilter) {
        $selectedMachineLabel = (string)($m['nama_mesin'] ?? 'Semua Mesin');
        $selectedInOptions = true;
        break;
    }
}
if ($mesinFilter !== '' && !$selectedInOptions) {
    $stmtMesin = sqlsrv_query(
        $conn,
        "SELECT CAST(id AS NVARCHAR(100)) AS id, COALESCE(NULLIF(nama_mesin, ''), '-') AS nama_mesin
         FROM dbo.m_fingerprint WHERE CAST(id AS NVARCHAR(100)) = ?",
        [$mesinFilter]
    );
    if ($stmtMesin && ($rowMesin = sqlsrv_fetch_array($stmtMesin, SQLSRV_FETCH_ASSOC))) {
        $machineOptions[] = $rowMesin;
        $selectedMachineLabel = (string)($rowMesin['nama_mesin'] ?? 'Mesin Terpilih');
    }
    if ($stmtMesin) {
        sqlsrv_free_stmt($stmtMesin);
    }
}

$kioskMainTitle = 'ABSEN KANTIN';
if (trim(strtolower($selectedMachineLabel)) !== 'semua mesin' && trim($selectedMachineLabel) !== '') {
    $kioskMainTitle = 'ABSEN ' . strtoupper($selectedMachineLabel);
}

$initialEvent = fetchLatestAttendance($conn, $mesinFilter, $tanggalAwal, $tanggalAkhir);
$initialKioskStats = fetchKioskStats($conn, $mesinFilter, $tanggalAwal, $tanggalAkhir);
$initialMachineCards = fetchMachineSuccessCards($conn, $mesinFilter, $tanggalAwal, $tanggalAkhir);
$initialEmployeeHistory = [];
if (is_array($initialEvent) && !empty($initialEvent)) {
    $initialEmployeeHistory = fetchEmployeeAttendanceHistory(
        $conn,
        $mesinFilter,
        $tanggalAwal,
        $tanggalAkhir,
        (string)($initialEvent['user_id'] ?? ''),
        (string)($initialEvent['nama'] ?? ''),
        8
    );
}
$kioskAccessToken = getKioskAccessToken();
$initialViolation = (($initialEvent['status_code'] ?? '') === 'violation');
$initialSuccessTime = $initialViolation
    ? ((trim((string)($initialEvent['first_scan_time'] ?? '')) !== '') ? (string)$initialEvent['first_scan_time'] : '--:--:--')
    : (string)($initialEvent['jam'] ?? '--:--:--');
$initialSuccessDate = ($initialSuccessTime !== '--:--:--') ? (string)($initialEvent['tanggal_text'] ?? '-') : '-';
$initialRejectTime = $initialViolation
    ? ((trim((string)($initialEvent['second_scan_time'] ?? '')) !== '') ? (string)$initialEvent['second_scan_time'] : (string)($initialEvent['jam'] ?? '--:--:--'))
    : '--:--:--';
$initialRejectDate = ($initialViolation && $initialRejectTime !== '--:--:--') ? (string)($initialEvent['tanggal_text'] ?? '-') : '-';
$initialMachineName = (string)($initialEvent['mesin'] ?? $selectedMachineLabel);
$initialSuccessInfo = ($initialSuccessTime !== '--:--:--')
    ? ('Absen berhasil di ' . $initialMachineName)
    : 'Menunggu data absen berhasil.';
$initialRejectInfo = ($initialViolation && $initialRejectTime !== '--:--:--')
    ? ('Absen ditolak di ' . $initialMachineName . ' jam ' . $initialRejectTime)
    : 'Belum ada absen ditolak.';
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/sidebar.php'; ?>

<style>
    html.dk-no-scroll,
    body.dk-no-scroll {
        overflow: hidden !important;
        height: 100vh !important;
    }
    .dk-filter-box {
        border: 1px solid #d9e3ef;
        border-radius: 10px;
        padding: 12px;
        background: linear-gradient(180deg, #fcfdff 0%, #f6f9fe 100%);
        height: 100%;
        box-shadow: 0 6px 16px rgba(33, 63, 99, 0.06);
    }
    .dk-preset-box {
        margin-top: 8px;
    }
    .dk-side-box {
        border: 1px solid #d9e3ef;
        border-radius: 10px;
        padding: 12px;
        background: linear-gradient(180deg, #ffffff 0%, #f6fbf9 100%);
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: 0 6px 16px rgba(24, 84, 53, 0.06);
    }
    .dk-clock {
        text-align: left;
        line-height: 1.1;
        color: #155b34;
        font-weight: 700;
        border-bottom: 1px dashed #cfe0d5;
        padding-bottom: 8px;
        margin-bottom: 8px;
    }
    .dk-clock .dk-date {
        font-size: 1.25rem;
    }
    .dk-clock .dk-time {
        font-size: 2.2rem;
        letter-spacing: 1px;
    }
    .dk-full-btn {
        width: 100%;
        border-radius: 8px;
        font-weight: 700;
        letter-spacing: 0.2px;
    }
    .dk-tools-stack label {
        margin-bottom: 4px;
        font-weight: 600;
        color: #3d5c4d;
        font-size: 0.78rem;
        letter-spacing: 0.2px;
        text-transform: uppercase;
    }
    .dk-table-wrap {
        max-height: 60vh;
        overflow: auto;
    }
    .dk-summary-card {
        margin-top: 12px;
        border: 1px solid #d7e2ee;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 8px 20px rgba(24, 53, 88, 0.07);
        overflow: hidden;
    }
    .dk-summary-card .card-header {
        background: linear-gradient(180deg, #f7faff 0%, #edf4fc 100%);
        border-bottom: 1px solid #dbe6f2;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding-top: 10px;
        padding-bottom: 10px;
    }
    .dk-summary-card .card-title {
        font-weight: 700;
        font-size: 1rem;
        color: #1f3852;
    }
    .dk-summary-hint {
        font-size: 0.8rem;
        color: #5f7287;
    }
    .dk-summary-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-left: auto;
    }
    .dk-export-btn {
        font-weight: 700;
        border-radius: 7px;
        white-space: nowrap;
    }
    .dk-summary-matrix-wrap {
        max-height: 410px;
    }
    .dk-summary-matrix-table thead th {
        white-space: nowrap;
        font-size: 0.79rem;
        vertical-align: middle;
        background: #eef4fb;
        color: #1e3a56;
        font-weight: 700;
        border-color: #d7e2ef;
    }
    .dk-summary-matrix-table tbody td {
        white-space: nowrap;
        font-size: 0.82rem;
        border-color: #dde6f0;
    }
    .dk-summary-matrix-table tbody tr:nth-child(even) {
        background: #fbfdff;
    }
    .dk-summary-matrix-table tbody tr:hover {
        background: #f1f7ff;
    }
    .dk-summary-matrix-table .dk-sticky-col {
        position: sticky;
        left: 0;
        background: #ffffff;
        z-index: 2;
        min-width: 180px;
        font-weight: 600;
    }
    .dk-summary-matrix-table thead .dk-sticky-col {
        z-index: 3;
        background: #eef4fa;
    }
    #summaryMatrixBody .dk-loading {
        padding: 18px;
        text-align: center;
        color: #5f6f81;
        font-size: 0.9rem;
    }
    .dk-badge {
        display: inline-block;
        min-width: 40px;
        text-align: center;
        border-radius: 999px;
        padding: 2px 8px;
        font-size: 11px;
        font-weight: 700;
        border: 1px solid;
    }
    .dk-badge-ok {
        color: #1b6f3b;
        background: #e8f6ee;
        border-color: #bde3ca;
    }
    .dk-badge-warn {
        color: #8c4500;
        background: #fff1e3;
        border-color: #f2c89b;
    }
    #filterInfo {
        margin-top: 10px;
        margin-bottom: 10px !important;
        background: #eef3fb;
        border: 1px solid #d8e2ef;
        border-radius: 999px;
        padding: 6px 12px;
        display: inline-block;
        color: #46607b !important;
        font-weight: 600;
        font-size: 0.8rem !important;
    }
    #dkFilterForm label.small {
        color: #385772;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.2px;
        font-size: 0.73rem;
    }
    #dkFilterForm .form-control-sm,
    #tvPreset {
        border-radius: 8px;
        border-color: #cfdbe9;
        box-shadow: inset 0 1px 2px rgba(19, 42, 70, 0.04);
    }
    #dkFilterForm .form-control-sm:focus,
    #tvPreset:focus {
        border-color: #8fb8e6;
        box-shadow: 0 0 0 0.1rem rgba(63, 134, 214, 0.16);
    }
    .dk-table-wrap {
        margin-top: 12px;
        border: 1px solid #dde5ef;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 6px 16px rgba(33, 63, 99, 0.05);
        padding: 4px 6px;
    }
    .dk-machine-cards {
        margin-top: 10px;
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 8px;
    }
    .dk-machine-card {
        border: 1px solid #d9e2ee;
        border-radius: 10px;
        background: linear-gradient(180deg, #ffffff 0%, #f6fbff 100%);
        padding: 10px;
        box-shadow: 0 5px 14px rgba(29, 62, 98, 0.08);
    }
    .dk-machine-card .label {
        font-size: 0.74rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.22px;
        color: #3e5a74;
        margin-bottom: 4px;
    }
    .dk-machine-card .value {
        font-size: 2rem;
        line-height: 1;
        font-weight: 800;
        color: #154f83;
    }
    .dk-machine-card .sub {
        margin-top: 4px;
        font-size: 0.72rem;
        color: #64798f;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.2px;
    }
    .dk-machine-card.type-depan .value { color: #166a3c; }
    .dk-machine-card.type-produksi .value { color: #1c6fb2; }
    .dk-machine-card.type-weaving1 .value { color: #6552cc; }
    .dk-machine-card.type-weaving2 .value { color: #8a4f24; }
    @media (max-width: 1200px) {
        .dk-machine-cards {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 768px) {
        .dk-machine-cards {
            grid-template-columns: 1fr;
        }
    }

    .dk-kiosk-overlay {
        position: fixed;
        inset: 0;
        z-index: 99999;
        display: none;
        --dk-scale: 1;
        background: radial-gradient(circle at top right, #183049 0, #0e1c2d 40%, #0a1322 100%);
        padding: 18px;
        overflow: auto;
    }
    .dk-kiosk-overlay.active {
        overflow: hidden;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }
    .dk-kiosk-overlay.active::-webkit-scrollbar {
        width: 0;
        height: 0;
    }
    .dk-kiosk-overlay.active {
        display: block;
    }
    .dk-kiosk-page {
        max-width: 1380px;
        margin: 0 auto;
        background: #eff3f6;
        border: 1px solid rgba(255,255,255,0.18);
        border-radius: 12px;
        box-shadow: 0 18px 48px rgba(0,0,0,0.35);
        padding: 14px;
    }
    .dk-kiosk-overlay.is-full {
        padding: 0;
        overflow: hidden;
    }
    .dk-kiosk-overlay.is-full .dk-kiosk-page {
        max-width: none;
        width: calc(100vw / var(--dk-scale));
        height: calc(100vh / var(--dk-scale));
        margin: 0;
        border-radius: 0;
        border: 0;
        box-shadow: none;
        padding: 14px 16px 10px;
        display: flex;
        flex-direction: column;
        transform: scale(var(--dk-scale));
        transform-origin: top left;
    }
    .dk-kiosk-overlay.is-full .dk-kiosk-header {
        min-height: 86px;
        margin-bottom: 8px;
    }
    .dk-kiosk-overlay.is-full .dk-kiosk-brand h1 {
        font-size: 3rem;
    }
    .dk-kiosk-overlay.is-full .dk-kiosk-brand p {
        font-size: 1.6rem;
    }
    .dk-kiosk-overlay.is-full .dk-kiosk-clock {
        padding-right: 6px;
    }
    .dk-kiosk-overlay.is-full .dk-kiosk-clock .dk-date {
        font-size: 2rem;
    }
    .dk-kiosk-overlay.is-full .dk-kiosk-clock .dk-time {
        font-size: 3.3rem;
        letter-spacing: 1px;
    }
    .dk-kiosk-overlay.is-full .dk-kiosk-shell {
        flex: 1;
        min-height: 0;
        display: flex;
        flex-direction: column;
    }
    .dk-kiosk-overlay.is-full .dk-kiosk-grid {
        flex: 1;
        min-height: 0;
        grid-template-columns: 59% 41%;
    }
    .dk-kiosk-overlay.is-full .dk-k-left {
        grid-template-columns: 360px 1fr;
        gap: 20px;
        padding: 18px 20px;
    }
    .dk-kiosk-overlay.is-full .dk-k-photo {
        height: 520px;
    }
    .dk-kiosk-overlay.is-full .dk-k-value {
        font-size: 3rem;
    }
    .dk-kiosk-overlay.is-full .dk-k-title {
        font-size: 4rem;
    }
    .dk-kiosk-overlay.is-full .dk-k-msg {
        font-size: 2rem;
    }
    .dk-kiosk-overlay.is-full .dk-k-time {
        font-size: 5.4rem;
    }
    .dk-kiosk-overlay.is-full .dk-k-date {
        font-size: 2.4rem;
    }
    .dk-kiosk-overlay.is-full .dk-k-footer {
        font-size: 1.05rem;
    }
    .dk-kiosk-overlay.is-full .dk-k-exit {
        position: fixed;
        right: 14px;
        bottom: 10px;
        margin-top: 0;
        z-index: 5;
    }
    .dk-kiosk-overlay.preset-auto { --dk-scale: 1; }
    .dk-kiosk-overlay.preset-tv-1366 { --dk-scale: 0.86; }
    .dk-kiosk-overlay.preset-tv-1600 { --dk-scale: 0.93; }
    .dk-kiosk-overlay.preset-tv-1920 { --dk-scale: 1; }
    .dk-kiosk-overlay.preset-tv-4k { --dk-scale: 1.34; }
    .dk-kiosk-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        margin-bottom: 8px;
    }
    .dk-kiosk-head-actions {
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }
    .dk-kiosk-settings-trigger {
        display: none;
        width: 40px;
        height: 40px;
        border-radius: 10px;
        border: 1px solid #cfdbe7;
        background: #ffffff;
        color: #20415f;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        cursor: pointer;
        box-shadow: 0 4px 10px rgba(17, 44, 77, 0.08);
    }
    .dk-kiosk-settings-trigger:hover {
        background: #f2f7fd;
    }
    .dk-kiosk-overlay.is-full .dk-kiosk-settings-trigger {
        display: inline-flex;
    }
    .dk-kiosk-summary-trigger {
        display: none;
        width: 40px;
        height: 40px;
        border-radius: 10px;
        border: 1px solid #cfe0d2;
        background: #eaf7ef;
        color: #1c6a38;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        cursor: pointer;
        box-shadow: 0 4px 10px rgba(18, 62, 33, 0.12);
    }
    .dk-kiosk-summary-trigger:hover {
        background: #dcf1e4;
    }
    .dk-kiosk-overlay.is-full .dk-kiosk-summary-trigger {
        display: inline-flex;
    }
    .dk-kiosk-settings-panel {
        position: absolute;
        top: 76px;
        right: 16px;
        z-index: 8;
        width: 320px;
        border: 1px solid #d3deea;
        border-radius: 10px;
        background: #ffffff;
        box-shadow: 0 16px 34px rgba(19, 47, 80, 0.18);
        padding: 10px 12px;
        display: none;
    }
    .dk-kiosk-settings-panel.active {
        display: block;
    }
    .dk-kiosk-summary-modal {
        position: absolute;
        inset: 0;
        z-index: 10;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 18px;
        background: rgba(9, 24, 15, 0.3);
    }
    .dk-kiosk-summary-modal.active {
        display: flex;
    }
    .dk-kiosk-summary-dialog {
        width: min(1040px, 96vw);
        border-radius: 16px;
        border: 1px solid #cfe0d2;
        background: #ffffff;
        box-shadow: 0 20px 42px rgba(16, 41, 25, 0.3);
        padding: 18px 20px 20px;
    }
    .dk-kiosk-summary-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 12px;
    }
    .dk-kiosk-summary-title {
        margin: 0;
        font-size: 1.35rem;
        font-weight: 800;
        color: #1b5a33;
        text-transform: uppercase;
        letter-spacing: 0.2px;
    }
    .dk-kiosk-summary-period {
        margin-top: 4px;
        font-size: 0.94rem;
        font-weight: 700;
        color: #446352;
    }
    .dk-kiosk-summary-close {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        border: 1px solid #d6e2da;
        background: #f6fbf8;
        color: #2f5540;
        font-size: 16px;
        line-height: 1;
        cursor: pointer;
    }
    .dk-kiosk-summary-close:hover {
        background: #eef6f1;
    }
    .dk-kiosk-summary-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }
    .dk-kiosk-summary-item {
        border: 1px solid #dce8e0;
        border-radius: 12px;
        background: linear-gradient(180deg, #f6fbf8 0, #eef7f2 100%);
        padding: 14px 16px;
    }
    .dk-kiosk-summary-label {
        font-size: 0.84rem;
        font-weight: 700;
        color: #4a6a56;
        text-transform: uppercase;
        letter-spacing: 0.2px;
    }
    .dk-kiosk-summary-value {
        margin-top: 6px;
        font-size: 2.45rem;
        line-height: 1.02;
        font-weight: 800;
        color: #124c2a;
    }
    @media (max-width: 760px) {
        .dk-kiosk-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    .dk-kiosk-settings-title {
        font-size: 0.86rem;
        font-weight: 800;
        color: #1f3b57;
        text-transform: uppercase;
        letter-spacing: 0.2px;
        margin-bottom: 8px;
    }
    .dk-kiosk-setting-item {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 6px;
        font-size: 0.82rem;
        color: #334e68;
        font-weight: 600;
    }
    .dk-kiosk-setting-item:last-child {
        margin-bottom: 0;
    }
    .dk-hidden-by-setting {
        display: none !important;
    }
    .dk-kiosk-brand h1 {
        margin: 0;
        font-size: 2.3rem;
        line-height: 1;
        color: #0f5f2b;
        letter-spacing: 0.5px;
    }
    .dk-kiosk-brand p {
        margin: 2px 0 0;
        color: #51645a;
        font-size: 1.15rem;
    }
    .dk-kiosk-clock {
        text-align: right;
        color: #0f4f2b;
    }
    .dk-kiosk-clock .dk-date {
        font-size: 1.5rem;
        font-weight: 700;
    }
    .dk-kiosk-clock .dk-time {
        font-size: 2.9rem;
        font-weight: 800;
        letter-spacing: 2px;
        line-height: 1;
        margin-top: 4px;
    }
    .dk-kiosk-shell {
        background: #f4f7f6;
        border: 1px solid #d8e0e8;
        border-radius: 10px;
        overflow: hidden;
    }
    .dk-kiosk-grid {
        display: grid;
        grid-template-columns: 58% 42%;
        min-height: 440px;
    }
    .dk-k-left {
        display: grid;
        grid-template-columns: 290px 1fr;
        gap: 14px;
        padding: 12px;
        border-right: 1px solid #d8e0e8;
    }
    .dk-k-photo {
        height: 420px;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid #cfd8d1;
        background: #e8efea;
    }
    .dk-k-photo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .dk-k-fields {
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        gap: 4px;
        padding-top: 2px;
    }
    .dk-k-item {
        border-bottom: 1px solid #e6ece7;
        padding-bottom: 5px;
    }
    .dk-k-item:last-child { border-bottom: 0; }
    .dk-k-label {
        font-size: 12px;
        color: #607166;
    }
    .dk-k-value {
        margin-top: 1px;
        font-size: 2.35rem;
        font-weight: 800;
        line-height: 1.08;
        color: #10251b;
    }
    .dk-k-right {
        background: linear-gradient(180deg, #eff6f2 0, #f7faf8 100%);
        padding: 12px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 10px;
    }
    .dk-k-bubble {
        width: 130px;
        height: 130px;
        border-radius: 50%;
        margin: 0 auto;
        display: grid;
        place-items: center;
        background: #1b8e42;
        color: #fff;
        font-size: 62px;
        font-weight: 900;
        box-shadow: 0 0 0 12px rgba(27,142,66,.09), 0 0 0 24px rgba(27,142,66,.05);
    }
    .dk-k-bubble.violation {
        background: #e23a3a;
        box-shadow: 0 0 0 12px rgba(226,58,58,.14), 0 0 0 24px rgba(226,58,58,.07);
    }
    .dk-k-title {
        text-align: center;
        font-size: 3rem;
        font-weight: 900;
        color: #0f6a36;
        letter-spacing: 0.3px;
    }
    .dk-k-title.violation { color: #c42b2b; }
    .dk-k-right.violation {
        background: linear-gradient(180deg, #fff3f3 0, #fff8f8 100%);
    }
    .dk-k-time-card.violation {
        background: #fff0f0;
        border-color: #f0c3c3;
    }
    .dk-k-time.violation {
        color: #b52626;
    }
    .dk-k-violation-note {
        display: none;
        margin: 0 auto;
        width: 85%;
        border: 1px solid #f0c8c8;
        border-radius: 8px;
        background: #fff3f3;
        color: #a82626;
        text-align: center;
        font-size: 1rem;
        font-weight: 600;
        padding: 8px 10px;
    }
    .dk-k-violation-note.active {
        display: block;
    }
    .dk-k-dual-time {
        display: none;
        width: 84%;
        margin: 2px auto 0;
        border: 1px solid #f0bebe;
        border-radius: 10px;
        background: #fff4f4;
        padding: 8px 12px;
    }
    .dk-k-dual-time.active {
        display: block;
    }
    .dk-k-dual-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 4px 0;
        color: #9e2a2a;
        font-size: 0.95rem;
        border-bottom: 1px dashed #efcaca;
    }
    .dk-k-dual-row:last-child {
        border-bottom: 0;
        padding-bottom: 0;
    }
    .dk-k-dual-row b {
        color: #c22727;
        font-size: 1.1rem;
        letter-spacing: 0.4px;
    }
    .dk-k-msg {
        text-align: center;
        font-size: 1.5rem;
        color: #4a5d52;
        min-height: 38px;
    }
    .dk-k-time-card {
        margin: 0 auto;
        width: 85%;
        border: 1px solid #d4ddd7;
        border-radius: 10px;
        background: #ecf4ef;
        text-align: center;
        padding: 10px 14px;
    }
    .dk-k-time-lbl {
        color: #53645a;
        font-size: 1.35rem;
    }
    .dk-k-time {
        margin-top: 4px;
        color: #165c35;
        font-weight: 900;
        font-size: 4.4rem;
        line-height: 1;
        letter-spacing: 1.1px;
    }
    .dk-k-date {
        margin-top: 4px;
        color: #364840;
        font-size: 2.05rem;
        font-weight: 700;
    }
    .dk-k-footer {
        border-top: 1px solid #d8e0e8;
        background: #dfe7ef;
        font-size: 0.92rem;
        color: #435461;
        padding: 8px 10px;
        display: flex;
        justify-content: space-between;
        gap: 12px;
    }
    .dk-k-exit {
        margin-top: 8px;
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }
    .dk-k-flash {
        animation: dkPulse 0.9s ease;
    }
    @keyframes dkPulse {
        0% { transform: scale(1); }
        35% { transform: scale(1.03); }
        100% { transform: scale(1); }
    }
    @media (max-width: 1120px) {
        .dk-side-box {
            margin-top: 10px;
        }
            .dk-kiosk-grid { grid-template-columns: 1fr; }
            .dk-k-left { border-right: 0; border-bottom: 1px solid #d8e0e8; }
            .dk-k-value { font-size: 2rem; }
            .dk-k-title { font-size: 2.4rem; }
            .dk-k-time { font-size: 3.6rem; }
            .dk-k-date { font-size: 1.5rem; }
            .dk-kiosk-overlay.is-full .dk-kiosk-shell {
                height: auto;
            }
            .dk-kiosk-overlay.is-full .dk-kiosk-page {
                height: auto;
                min-height: 100vh;
            }
            .dk-kiosk-overlay.is-full .dk-k-left {
                grid-template-columns: 1fr;
            }
            .dk-kiosk-overlay.is-full .dk-k-photo {
                width: 220px;
                margin: 0 auto;
                height: 280px;
            }
        }

    /* Redesign Fullscreen Kiosk */
    .dk-kiosk-page {
        background: #f6f8f7;
        border: 1px solid #d9e2dc;
        border-radius: 12px;
        padding: 12px 12px 10px;
        position: relative;
    }
    .dk-kiosk-header {
        margin-bottom: 10px;
    }
    .dk-kiosk-brand {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .dk-kiosk-brand-icon {
        width: 52px;
        height: 52px;
        border-radius: 10px;
        background: #1b8e42;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 22px;
        box-shadow: 0 8px 18px rgba(27,142,66,0.24);
    }
    .dk-kiosk-brand-text h1 {
        margin: 0;
        font-size: 2.4rem;
        line-height: 1;
        letter-spacing: 0.2px;
        color: #145e32;
        font-weight: 800;
    }
    .dk-kiosk-brand-text p {
        margin: 3px 0 0;
        font-size: 1.05rem;
        color: #50685b;
    }
    .dk-kiosk-clock {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        text-align: right;
    }
    .dk-kiosk-clock-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        background: #e8f4ec;
        color: #1b8e42;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        margin-top: 2px;
    }
    .dk-kiosk-clock-text {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
    }
    .dk-kiosk-clock .dk-date {
        font-size: 1.25rem;
    }
    .dk-kiosk-clock .dk-time {
        margin-top: 1px;
        font-size: 2.45rem;
        letter-spacing: 1px;
    }
    .dk-kiosk-shell {
        background: transparent;
        border: 0;
        border-radius: 0;
        overflow: visible;
    }
    .dk-kiosk-grid {
        display: grid;
        grid-template-columns: 56% 44%;
        gap: 8px;
        min-height: 435px;
    }
    .dk-k-left,
    .dk-k-right {
        border: 1px solid #dde6e0;
        border-radius: 10px;
        min-height: 100%;
    }
    .dk-k-left {
        background: #f9fbfa;
        display: grid;
        grid-template-columns: 230px 1fr;
        gap: 14px;
        padding: 12px;
        border-right: 1px solid #dde6e0;
    }
    .dk-k-photo {
        height: 290px;
        border: 1px solid #d2ddd5;
        border-radius: 8px;
        background: #edf2ee;
        overflow: hidden;
    }
    .dk-k-fields {
        gap: 0;
    }
    .dk-k-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 9px 0;
        border-bottom: 1px solid #e3ebe6;
    }
    .dk-k-item-icon {
        width: 40px;
        height: 40px;
        border-radius: 9px;
        background: #e8f4ec;
        color: #1f7f3f;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }
    .dk-k-item-body {
        min-width: 0;
        flex: 1;
    }
    .dk-k-label {
        font-size: 12px;
        color: #5f7268;
    }
    .dk-k-value {
        margin-top: 1px;
        font-size: 2rem;
        line-height: 1.12;
        color: #10251b;
        font-weight: 800;
    }
    .dk-k-right {
        position: relative;
        background: linear-gradient(180deg, #eef7f1 0, #f8fcf9 100%);
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 8px;
        padding: 14px 12px;
        overflow: hidden;
    }
    .dk-k-right::before {
        content: '';
        position: absolute;
        inset: 0;
        pointer-events: none;
        opacity: 0.5;
        background-image:
            radial-gradient(circle at 15% 22%, rgba(45,166,79,.35) 1.8px, transparent 2px),
            radial-gradient(circle at 84% 24%, rgba(45,166,79,.35) 1.8px, transparent 2px),
            radial-gradient(circle at 30% 36%, rgba(45,166,79,.30) 1.6px, transparent 2px),
            radial-gradient(circle at 73% 34%, rgba(45,166,79,.30) 1.6px, transparent 2px);
        background-size: 160px 160px;
        background-repeat: no-repeat;
    }
    .dk-k-right.violation {
        background: linear-gradient(180deg, #fff1f1 0, #fff9f9 100%);
    }
    .dk-k-right.violation::before {
        background-image:
            radial-gradient(circle at 15% 22%, rgba(217,54,54,.35) 1.8px, transparent 2px),
            radial-gradient(circle at 84% 24%, rgba(217,54,54,.35) 1.8px, transparent 2px),
            radial-gradient(circle at 30% 36%, rgba(217,54,54,.30) 1.6px, transparent 2px),
            radial-gradient(circle at 73% 34%, rgba(217,54,54,.30) 1.6px, transparent 2px);
    }
    .dk-k-right > * {
        position: relative;
        z-index: 1;
    }
    .dk-k-bubble {
        width: 112px;
        height: 112px;
        font-size: 58px;
    }
    .dk-k-title {
        font-size: 3rem;
        line-height: 1.05;
    }
    .dk-k-msg {
        font-size: 1.3rem;
        line-height: 1.35;
        padding: 0 8px;
        min-height: 30px;
    }
    .dk-k-time-card {
        width: 84%;
        border-radius: 10px;
        padding: 10px 14px;
    }
    .dk-k-time-lbl {
        font-size: 1.05rem;
        font-weight: 700;
        letter-spacing: 0.4px;
        text-transform: uppercase;
    }
    .dk-k-time {
        font-size: 4.2rem;
    }
    .dk-k-date {
        font-size: 1.85rem;
    }
    .dk-k-violation-note {
        width: 84%;
        font-size: 0.94rem;
        padding: 8px 12px 8px 38px;
        text-align: left;
        position: relative;
        line-height: 1.35;
    }
    .dk-k-violation-note::before {
        content: '!';
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #d73939;
        color: #fff;
        font-size: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
    }
    .dk-k-footer {
        margin-top: 8px;
        border: 1px solid #d6e2ef;
        background: #edf4ff;
        border-radius: 10px;
        padding: 10px 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }
    .dk-k-footer-main {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }
    .dk-k-footer-icon {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: #2a79c7;
        color: #fff;
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .dk-k-footer-title {
        font-size: 0.98rem;
        font-weight: 700;
        color: #28465f;
        line-height: 1.2;
    }
    .dk-k-footer-sub {
        font-size: 0.86rem;
        color: #4f677b;
        line-height: 1.2;
    }
    .dk-k-footer-right {
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }
    #todayCountNote {
        font-size: 0.86rem;
        color: #4c5f6f;
        white-space: nowrap;
    }
    .dk-k-footer-art {
        color: #95bcaa;
        font-size: 30px;
        line-height: 1;
    }
    .dk-kiosk-overlay.is-full .dk-kiosk-page {
        padding: 10px 12px 8px;
    }
    .dk-kiosk-overlay.is-full .dk-kiosk-header {
        min-height: 70px;
        margin-bottom: 8px;
    }
    .dk-kiosk-overlay.is-full .dk-kiosk-brand-text h1 {
        font-size: 2.8rem;
    }
    .dk-kiosk-overlay.is-full .dk-kiosk-brand-text p {
        font-size: 1.24rem;
    }
    .dk-kiosk-overlay.is-full .dk-kiosk-clock .dk-date {
        font-size: 1.7rem;
    }
    .dk-kiosk-overlay.is-full .dk-kiosk-clock .dk-time {
        font-size: 3rem;
    }
    .dk-kiosk-overlay.is-full .dk-kiosk-grid {
        grid-template-columns: 54.5% 45.5%;
        gap: 10px;
        min-height: 0;
    }
    .dk-kiosk-overlay.is-full .dk-k-left {
        grid-template-columns: 320px 1fr;
        padding: 16px;
    }
    .dk-kiosk-overlay.is-full .dk-k-photo {
        height: 430px;
    }
    .dk-kiosk-overlay.is-full .dk-k-item {
        padding: 10px 0;
        gap: 12px;
    }
    .dk-kiosk-overlay.is-full .dk-k-item-icon {
        width: 48px;
        height: 48px;
        font-size: 20px;
    }
    .dk-kiosk-overlay.is-full .dk-k-value {
        font-size: 2.9rem;
    }
    .dk-kiosk-overlay.is-full .dk-k-bubble {
        width: 136px;
        height: 136px;
        font-size: 66px;
    }
    .dk-kiosk-overlay.is-full .dk-k-title {
        font-size: 4.1rem;
    }
    .dk-kiosk-overlay.is-full .dk-k-msg {
        font-size: 1.95rem;
    }
    .dk-kiosk-overlay.is-full .dk-k-time {
        font-size: 5.2rem;
    }
    .dk-kiosk-overlay.is-full .dk-k-date {
        font-size: 2.35rem;
    }
    .dk-kiosk-overlay.is-full .dk-k-time-lbl {
        font-size: 1.3rem;
    }
    .dk-kiosk-overlay.is-full .dk-k-dual-row {
        font-size: 1.12rem;
    }
    .dk-kiosk-overlay.is-full .dk-k-dual-row b {
        font-size: 1.45rem;
    }
    .dk-kiosk-overlay.is-full .dk-k-footer {
        font-size: 1.05rem;
        padding: 10px 14px;
    }
    .dk-kiosk-overlay.is-full #todayCountNote {
        font-size: 1.02rem;
    }
    @media (max-width: 1120px) {
        .dk-kiosk-brand-text h1 {
            font-size: 1.85rem;
        }
        .dk-kiosk-clock .dk-time {
            font-size: 2rem;
        }
        .dk-kiosk-grid {
            grid-template-columns: 1fr;
            min-height: auto;
        }
        .dk-k-left {
            grid-template-columns: 1fr;
        }
        .dk-k-photo {
            width: 220px;
            margin: 0 auto;
        }
        .dk-k-footer {
            flex-direction: column;
            align-items: stretch;
        }
        .dk-k-footer-right {
            margin-left: 0;
            justify-content: space-between;
        }
        #todayCountNote {
            white-space: normal;
        }
    }

    /* Fullscreen Layout - Compact KPI + Detail Cards */
    .dk-kiosk-shell {
        background: transparent;
        border: 0;
        border-radius: 0;
        overflow: visible;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .dk-fs-kpi-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 10px;
    }
    .dk-fs-kpi-grid.cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)) !important; }
    .dk-fs-kpi-grid.cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
    .dk-fs-kpi-grid.cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
    .dk-fs-kpi-grid.cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)) !important; }
    .dk-fs-kpi-grid.cols-5 { grid-template-columns: repeat(5, minmax(0, 1fr)) !important; }
    .dk-fs-kpi-grid.cols-6 { grid-template-columns: repeat(6, minmax(0, 1fr)) !important; }
    .dk-fs-kpi-card {
        background: #ffffff;
        border: 1px solid #dde6e0;
        border-radius: 10px;
        padding: 14px 10px 12px;
        min-height: 205px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 5px;
        text-align: center;
    }
    .dk-fs-kpi-icon {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        font-size: 38px;
    }
    .dk-fs-kpi-label {
        font-size: 1rem;
        font-weight: 800;
        color: #2f4757;
        letter-spacing: 0.2px;
        line-height: 1.2;
    }
    .dk-fs-kpi-value {
        font-size: 4.35rem;
        line-height: 1;
        font-weight: 900;
    }
    .dk-fs-kpi-foot {
        margin-top: 3px;
        width: 100%;
        border-radius: 7px;
        padding: 6px 8px;
        font-size: 0.9rem;
        font-weight: 700;
    }
    .dk-fs-kpi-card.type-total .dk-fs-kpi-icon {
        background: #deebff;
        color: #1d76db;
    }
    .dk-fs-kpi-card.type-total .dk-fs-kpi-value {
        color: #1d76db;
    }
    .dk-fs-kpi-card.type-total .dk-fs-kpi-foot {
        background: #eaf2ff;
        color: #2764a8;
    }
    .dk-fs-kpi-card.type-unique .dk-fs-kpi-icon {
        background: #e6f7ec;
        color: #1f8f45;
    }
    .dk-fs-kpi-card.type-unique .dk-fs-kpi-value {
        color: #1f8f45;
    }
    .dk-fs-kpi-card.type-unique .dk-fs-kpi-foot {
        background: #ecf9f0;
        color: #2b7d45;
    }
    .dk-fs-kpi-card.type-average .dk-fs-kpi-icon {
        background: #f2e9ff;
        color: #6f2dd1;
    }
    .dk-fs-kpi-card.type-average .dk-fs-kpi-value {
        color: #6f2dd1;
    }
    .dk-fs-kpi-card.type-average .dk-fs-kpi-foot {
        background: #f5eeff;
        color: #6f2dd1;
    }
    .dk-fs-kpi-card.type-success .dk-fs-kpi-icon {
        background: #e6f7ec;
        color: #1f9647;
    }
    .dk-fs-kpi-card.type-success .dk-fs-kpi-value {
        color: #1f9647;
    }
    .dk-fs-kpi-card.type-success .dk-fs-kpi-foot {
        background: #e9f9ee;
        color: #2a7f47;
    }
    .dk-fs-kpi-card.type-reject .dk-fs-kpi-icon {
        background: #ffe8e8;
        color: #df2f2f;
    }
    .dk-fs-kpi-card.type-reject .dk-fs-kpi-value {
        color: #df2f2f;
    }
    .dk-fs-kpi-card.type-reject .dk-fs-kpi-foot {
        background: #ffefef;
        color: #be3030;
    }
    .dk-fs-kpi-card.type-shift-siang .dk-fs-kpi-icon {
        background: #fff4dd;
        color: #d88b10;
    }
    .dk-fs-kpi-card.type-shift-siang .dk-fs-kpi-value {
        color: #d88b10;
    }
    .dk-fs-kpi-card.type-shift-siang .dk-fs-kpi-foot {
        background: #fff8e8;
        color: #a97416;
    }
    .dk-fs-kpi-card.type-shift-malam .dk-fs-kpi-icon {
        background: #e9ebff;
        color: #4257c9;
    }
    .dk-fs-kpi-card.type-shift-malam .dk-fs-kpi-value {
        color: #4257c9;
    }
    .dk-fs-kpi-card.type-shift-malam .dk-fs-kpi-foot {
        background: #f0f2ff;
        color: #3e4ea8;
    }
    .dk-fs-kpi-card.type-shift-pagi .dk-fs-kpi-icon {
        background: #e8f7ff;
        color: #1686b8;
    }
    .dk-fs-kpi-card.type-shift-pagi .dk-fs-kpi-value {
        color: #1686b8;
    }
    .dk-fs-kpi-card.type-shift-pagi .dk-fs-kpi-foot {
        background: #eefbff;
        color: #2d7594;
    }
    .dk-fs-kpi-card.type-non-shift .dk-fs-kpi-icon {
        background: #e9f7f0;
        color: #1d8a5a;
    }
    .dk-fs-kpi-card.type-non-shift .dk-fs-kpi-value {
        color: #1d8a5a;
    }
    .dk-fs-kpi-card.type-non-shift .dk-fs-kpi-foot {
        background: #effbf5;
        color: #2f7c5c;
    }
    .dk-fs-kpi-card.type-no-all .dk-fs-kpi-icon {
        background: #f4ecec;
        color: #8f4b4b;
    }
    .dk-fs-kpi-card.type-no-all .dk-fs-kpi-value {
        color: #8f4b4b;
    }
    .dk-fs-kpi-card.type-no-all .dk-fs-kpi-foot {
        background: #fbf3f3;
        color: #7e4f4f;
    }
    .dk-fs-main-grid {
        display: grid;
        grid-template-columns: 40% 60%;
        gap: 10px;
        min-height: 0;
        flex: 1 1 auto;
    }
    .dk-fs-main-grid.single-visible {
        grid-template-columns: 1fr;
    }
    .dk-fs-panel {
        border: 1px solid #dce7e0;
        border-radius: 10px;
        background: #fcfdfc;
        min-width: 0;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    .dk-fs-panel-title {
        padding: 8px 10px;
        border-bottom: 1px solid #e3ece6;
        font-size: 0.92rem;
        font-weight: 800;
        text-transform: uppercase;
        color: #2b4f41;
        letter-spacing: 0.2px;
    }
    .dk-fs-panel-title i {
        margin-right: 7px;
        color: #2a9952;
    }
    .dk-fs-employee-body {
        display: grid;
        grid-template-columns: 230px 1fr;
        gap: 10px;
        padding: 10px;
        flex: 1 1 auto;
        min-height: 0;
        align-items: stretch;
    }
    .dk-fs-photo-column {
        display: flex;
        flex-direction: column;
        gap: 8px;
        min-height: 0;
        height: 100%;
    }
    .dk-fs-photo {
        border: 1px solid #cfddd4;
        border-radius: 9px;
        overflow: hidden;
        background: #edf2ee;
        min-height: 0;
        width: 100%;
        height: 250px;
        max-height: 250px;
        align-self: start;
        flex: 0 0 auto;
    }
    .dk-fs-photo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center top;
    }
    .dk-fs-emp-history {
        border: 1px solid #d5e2da;
        border-radius: 9px;
        background: #f6fbf8;
        padding: 8px;
        min-height: 0;
        flex: 1 1 auto;
        overflow: auto;
    }
    .dk-fs-emp-history-title {
        font-size: 0.74rem;
        font-weight: 800;
        color: #476457;
        text-transform: uppercase;
        letter-spacing: 0.2px;
        margin-bottom: 6px;
    }
    .dk-fs-emp-history-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    .dk-fs-emp-history-item {
        border: 1px solid #dbe8e0;
        border-radius: 7px;
        background: #ffffff;
        padding: 6px 7px;
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 6px 8px;
        align-items: center;
    }
    .dk-fs-emp-history-meta {
        display: flex;
        flex-direction: column;
        min-width: 0;
        line-height: 1.2;
    }
    .dk-fs-emp-history-date {
        font-size: 0.76rem;
        font-weight: 700;
        color: #40564a;
    }
    .dk-fs-emp-history-time {
        font-size: 0.88rem;
        font-weight: 800;
        color: #143224;
    }
    .dk-fs-emp-history-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 3px 8px;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.1px;
        line-height: 1;
        white-space: nowrap;
        border: 1px solid transparent;
    }
    .dk-fs-emp-history-badge.status-success {
        color: #1d7f41;
        background: #e7f7ed;
        border-color: #bfe2cb;
    }
    .dk-fs-emp-history-badge.status-reject {
        color: #b13636;
        background: #fff0f0;
        border-color: #efc5c5;
    }
    .dk-fs-emp-history-empty {
        border-radius: 7px;
        border: 1px dashed #d0dfd6;
        background: #fbfefd;
        padding: 8px;
        text-align: center;
        font-size: 0.8rem;
        color: #6f8578;
        font-style: italic;
    }
    .dk-fs-fields {
        display: flex;
        flex-direction: column;
        gap: 2px;
        justify-content: flex-start;
        min-width: 0;
    }
    .dk-fs-field-item {
        display: flex;
        align-items: flex-start;
        gap: 9px;
        border-bottom: 1px solid #e5eee8;
        padding: 9px 0;
    }
    .dk-fs-field-item:last-child {
        border-bottom: 0;
    }
    .dk-fs-field-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        background: #e7f3eb;
        color: #1f7f40;
        display: grid;
        place-items: center;
        font-size: 17px;
        flex-shrink: 0;
    }
    .dk-fs-field-label {
        font-size: 11px;
        color: #607168;
        line-height: 1.2;
        margin-bottom: 3px;
        letter-spacing: 0.15px;
    }
    .dk-fs-field-value {
        margin-top: 0;
        font-size: 1.72rem;
        line-height: 1.12;
        color: #10251b;
        font-weight: 800;
        word-break: break-word;
        overflow-wrap: anywhere;
    }
    .dk-fs-field-body {
        min-width: 0;
        flex: 1 1 auto;
    }
    .dk-fs-side-status {
        margin-top: auto;
        padding-top: 12px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .dk-fs-fields .dk-fs-status-line,
    .dk-fs-fields .dk-k-dual-time,
    .dk-fs-fields .dk-k-violation-note {
        width: 100%;
        margin: 0;
    }
    .dk-fs-scan-panel {
        display: flex;
        flex-direction: column;
        padding: 10px;
        gap: 10px;
        background: #f8fbf9;
        min-height: 0;
    }
    .dk-fs-scan-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        flex: 1 1 auto;
        min-height: 0;
        grid-auto-rows: 1fr;
    }
    .dk-fs-scan-grid.cols-2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .dk-fs-scan-grid.cols-1 {
        grid-template-columns: 1fr;
    }
    .dk-fs-scan-card {
        border: 1px solid #d8e4dc;
        border-radius: 10px;
        background: #ffffff;
        padding: 10px 8px 10px;
        text-align: center;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
    }
    .dk-fs-scan-card.active {
        border-color: #2eab58;
        box-shadow: 0 0 0 2px rgba(46,171,88,0.15);
    }
    .dk-fs-reject-card.active {
        border-color: #df3b3b;
        box-shadow: 0 0 0 2px rgba(223,59,59,0.16);
    }
    .dk-fs-pill {
        display: inline-block;
        border-radius: 6px;
        padding: 4px 8px;
        font-size: 0.72rem;
        font-weight: 800;
        color: #fff;
        margin-bottom: 8px;
        letter-spacing: 0.2px;
    }
    .dk-fs-pill-location {
        background: #2a9952;
    }
    .dk-fs-pill-success {
        background: #22a048;
    }
    .dk-fs-pill-reject {
        background: #df3131;
    }
    .dk-fs-scan-icon {
        width: 88px;
        height: 88px;
        border-radius: 50%;
        margin: 0 auto 7px;
        display: grid;
        place-items: center;
        font-size: 48px;
    }
    .dk-fs-scan-icon.icon-location {
        background: #e6f5eb;
        color: #2b8f4b;
    }
    .dk-fs-scan-icon.icon-success {
        background: #e7f8ed;
        color: #219548;
    }
    .dk-fs-scan-icon.icon-reject {
        background: #ffe8e8;
        color: #d53030;
    }
    .dk-fs-scan-meta {
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #51645a;
    }
    .dk-fs-scan-time {
        margin-top: 3px;
        font-size: 2.55rem;
        font-weight: 900;
        line-height: 1;
    }
    .dk-fs-scan-date {
        margin-top: 3px;
        font-size: 1.25rem;
        color: #44584e;
        font-weight: 700;
        line-height: 1.1;
    }
    .dk-fs-scan-note {
        margin-top: 10px;
        font-size: 1.02rem;
        line-height: 1.35;
        color: #355447;
        font-weight: 800;
        min-height: 52px;
        padding: 8px 10px;
        border-radius: 9px;
        background: #edf7f0;
        border: 1px solid #cae6d2;
    }
    .dk-fs-success-card .dk-fs-scan-note {
        color: #176638;
        background: #e8f7ed;
        border-color: #bfe2cb;
    }
    .dk-fs-reject-card .dk-fs-scan-note {
        color: #9d4141;
        background: #fff1f1;
        border-color: #f2cccc;
    }
    .dk-fs-history {
        margin-top: 8px;
        border-radius: 8px;
        border: 1px dashed #d8e6dd;
        background: #f5faf7;
        padding: 8px;
        text-align: left;
        display: flex;
        flex-direction: column;
        min-height: 120px;
        flex: 1 1 auto;
        overflow: hidden;
    }
    .dk-fs-history-title {
        font-size: 0.76rem;
        font-weight: 800;
        color: #476457;
        text-transform: uppercase;
        margin-bottom: 6px;
        letter-spacing: 0.2px;
    }
    .dk-fs-history-list {
        list-style: none;
        padding: 0;
        margin: 0;
        overflow: auto;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .dk-fs-history-item {
        border-radius: 6px;
        padding: 5px 7px;
        border: 1px solid #dce9e1;
        background: #ffffff;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
        font-size: 0.86rem;
        color: #2d4a3d;
        line-height: 1.2;
    }
    .dk-fs-history-item .name {
        font-weight: 700;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .dk-fs-history-item .time {
        font-weight: 800;
        color: #5f7a6c;
        font-size: 0.8rem;
        flex-shrink: 0;
    }
    .dk-fs-history-item.is-empty {
        justify-content: center;
        color: #70877b;
        font-style: italic;
    }
    .dk-fs-success-card .dk-fs-history {
        border-color: #c5e4d0;
        background: #eef9f2;
    }
    .dk-fs-reject-card .dk-fs-history {
        border-color: #efcbcb;
        background: #fff5f5;
    }
    .dk-fs-reject-card .dk-fs-history-item {
        border-color: #f1d9d9;
        background: #fffefe;
        color: #6d4343;
    }
    .dk-fs-scan-time.text-location {
        color: #1d8442;
        font-size: 2.3rem;
    }
    .dk-fs-scan-time.text-success {
        color: #1e9447;
    }
    .dk-fs-scan-time.text-reject {
        color: #d53030;
    }
    .dk-fs-status-line {
        border-radius: 8px;
        border: 1px solid #d7e5dc;
        background: #edf8f1;
        color: #1a8e43;
        font-size: 1.05rem;
        font-weight: 900;
        padding: 6px 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        letter-spacing: 0.2px;
    }
    .dk-fs-status-line.violation {
        border-color: #f0c6c6;
        background: #fff0f0;
        color: #c93030;
    }
    .dk-fs-status-icon {
        min-width: 30px;
        height: 30px;
        border-radius: 50%;
        background: rgba(0,0,0,0.08);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.72rem;
        padding: 0 6px;
    }
    .dk-fs-scan-panel.violation {
        background: #fffafb;
    }
    .dk-kiosk-overlay.is-full .dk-kiosk-shell {
        flex: 1;
        min-height: 0;
        gap: 10px;
    }
    .dk-kiosk-overlay.is-full .dk-fs-kpi-grid {
        gap: 10px;
        flex: 0 0 auto;
    }
    .dk-kiosk-overlay.is-full .dk-fs-kpi-card {
        min-height: 250px;
        padding: 16px 12px 12px;
    }
    .dk-kiosk-overlay.is-full .dk-fs-kpi-icon {
        width: 86px;
        height: 86px;
        font-size: 44px;
    }
    .dk-kiosk-overlay.is-full .dk-fs-kpi-label {
        font-size: 1.08rem;
    }
    .dk-kiosk-overlay.is-full .dk-fs-kpi-value {
        font-size: 5rem;
    }
    .dk-kiosk-overlay.is-full .dk-fs-kpi-foot {
        font-size: 0.96rem;
        padding: 7px 9px;
    }
    .dk-kiosk-overlay.is-full .dk-fs-main-grid {
        flex: 1 1 auto;
        min-height: 0;
        grid-template-columns: 34% 66%;
        gap: 10px;
    }
    .dk-kiosk-overlay.is-full .dk-fs-employee-body {
        grid-template-columns: 41% 59%;
        padding: 10px;
    }
    .dk-kiosk-overlay.is-full .dk-fs-photo-column {
        gap: 9px;
    }
    .dk-kiosk-overlay.is-full .dk-fs-photo {
        min-height: 0;
        height: 250px;
        max-height: 250px;
        align-self: start;
    }
    .dk-kiosk-overlay.is-full .dk-fs-field-value {
        font-size: 1.92rem;
        line-height: 1.1;
    }
    .dk-kiosk-overlay.is-full .dk-fs-field-item {
        padding: 8px 0;
        gap: 8px;
    }
    .dk-kiosk-overlay.is-full .dk-fs-field-icon {
        width: 34px;
        height: 34px;
        font-size: 14px;
    }
    .dk-kiosk-overlay.is-full .dk-fs-field-label {
        font-size: 11px;
    }
    .dk-kiosk-overlay.is-full .dk-fs-scan-time {
        font-size: 3.05rem;
    }
    .dk-kiosk-overlay.is-full .dk-fs-scan-date {
        font-size: 1.5rem;
    }
    .dk-kiosk-overlay.is-full .dk-fs-scan-time.text-location {
        font-size: 2.8rem;
    }
    .dk-kiosk-overlay.is-full .dk-fs-status-line {
        font-size: 1.3rem;
        padding: 10px 12px;
    }
    .dk-kiosk-overlay.is-full .dk-fs-status-icon {
        min-width: 34px;
        height: 34px;
        font-size: 0.78rem;
    }
    @media (max-width: 1300px) {
        .dk-fs-kpi-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        .dk-fs-main-grid {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 920px) {
        .dk-fs-kpi-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .dk-fs-employee-body {
            grid-template-columns: 1fr;
        }
        .dk-fs-photo-column {
            max-width: 240px;
            margin: 0 auto;
            width: 100%;
        }
        .dk-fs-photo {
            height: 220px;
            max-height: 220px;
        }
        .dk-fs-scan-grid {
            grid-template-columns: 1fr;
        }
        .dk-fs-field-value {
            font-size: 1.55rem;
        }
        .dk-fs-scan-time {
            font-size: 1.95rem;
        }
        .dk-fs-scan-date {
            font-size: 1rem;
        }
    }
</style>

<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row align-items-center mb-2">
                    <div class="col-md-6">
                        <h1 class="m-0">Absen Kantin Dashboard</h1>
                    </div>
                    <div class="col-md-6 text-md-right text-sm-left mt-sm-2 mt-md-0">
                        <ol class="breadcrumb float-md-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item active">Absen Kantin Dashboard</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-header bg-<?= esc($themeColor) ?> text-white">
                        <h3 class="card-title m-0">Data Absensi Kantin</h3>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3 align-items-center">
                            <div class="col-md-4">
                                <div class="dk-side-box">
                                    <div class="dk-clock">
                                        <div class="dk-date" id="clockDate">-</div>
                                        <div class="dk-time" id="clockTime">-</div>
                                    </div>
                                    <div class="dk-tools-stack">
                                        <div class="dk-preset-box">
                                            <label class="small mb-1">Preset Ukuran TV</label>
                                            <select id="tvPreset" class="form-control form-control-sm">
                                                <option value="auto">Auto (Rekomendasi)</option>
                                                <option value="tv-1366">TV 1366 x 768</option>
                                                <option value="tv-1600">TV 1600 x 900</option>
                                                <option value="tv-1920">TV 1920 x 1080</option>
                                                <option value="tv-4k">TV 4K (3840 x 2160)</option>
                                            </select>
                                        </div>
                                        <button type="button" id="btnFullScreen" class="btn btn-dark btn-sm mt-2 dk-full-btn">
                                            <i class="fas fa-expand"></i> Full Screen Mode
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="dk-filter-box">
                                    <div id="dkFilterForm">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <label class="small">Pilih Mesin Dashboard</label>
                                                <select class="form-control form-control-sm" id="mesinPicker" name="mesin_id">
                                                    <option value="">Semua Mesin</option>
                                                    <?php foreach ($machineOptions as $optMesin): ?>
                                                        <?php
                                                            $optId = (string)($optMesin['id'] ?? '');
                                                            $optName = (string)($optMesin['nama_mesin'] ?? '-');
                                                        ?>
                                                        <option value="<?= esc($optId) ?>" <?= $mesinFilter === $optId ? 'selected' : '' ?>><?= esc($optName) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="small">Tanggal Awal</label>
                                                <input class="form-control form-control-sm" type="date" id="tanggalAwal" name="tanggal_awal" value="<?= esc($tanggalAwal) ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="small">Tanggal Akhir</label>
                                                <input class="form-control form-control-sm" type="date" id="tanggalAkhir" name="tanggal_akhir" value="<?= esc($tanggalAkhir) ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="dk-machine-cards" id="machineSuccessCards">
                                    <div class="dk-machine-card type-depan">
                                        <div class="label">Kantin Depan</div>
                                        <div class="value" id="cardKantinDepan"><?= esc(numberId((int)($initialMachineCards['kantin_depan']['total'] ?? 0))) ?></div>
                                        <div class="sub">Total Absen Berhasil</div>
                                    </div>
                                    <div class="dk-machine-card type-produksi">
                                        <div class="label">Kantin Produksi</div>
                                        <div class="value" id="cardKantinProduksi"><?= esc(numberId((int)($initialMachineCards['kantin_produksi']['total'] ?? 0))) ?></div>
                                        <div class="sub">Total Absen Berhasil</div>
                                    </div>
                                    <div class="dk-machine-card type-weaving1">
                                        <div class="label">Kantin Weaving 1</div>
                                        <div class="value" id="cardKantinWeaving1"><?= esc(numberId((int)($initialMachineCards['kantin_weaving_1']['total'] ?? 0))) ?></div>
                                        <div class="sub">Total Absen Berhasil</div>
                                    </div>
                                    <div class="dk-machine-card type-weaving2">
                                        <div class="label">Kantin Weaving 2</div>
                                        <div class="value" id="cardKantinWeaving2"><?= esc(numberId((int)($initialMachineCards['kantin_weaving_2']['total'] ?? 0))) ?></div>
                                        <div class="sub">Total Absen Berhasil</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-2 small text-muted" id="filterInfo">
                            Filter: <?= esc($selectedMachineLabel) ?> | Periode: <?= esc($tanggalAwal) ?> s/d <?= esc($tanggalAkhir) ?>
                        </div>

                        <div class="card dk-summary-card">
                            <div class="card-header">
                                <h3 class="card-title m-0">Tabel Summary Absensi Per Mesin (Absen Berhasil)</h3>
                                <div class="dk-summary-actions">
                                    <div class="dk-summary-hint">.</div>
                                    <a href="export_excel_summary_kantin.php?tanggal_awal=<?= esc($tanggalAwal) ?>&tanggal_akhir=<?= esc($tanggalAkhir) ?>&mesin_id=<?= esc($mesinFilter) ?>"
                                       class="btn btn-success btn-sm dk-export-btn"
                                       id="btnExportSummary">
                                        <i class="fas fa-file-excel"></i> Export Excel Summary
                                    </a>
                                </div>
                            </div>
                            <div class="card-body p-0" id="summaryMatrixBody">
                                <div class="dk-loading">Memuat tabel summary...</div>
                            </div>
                        </div>

                        <div class="dk-table-wrap">
                            <table id="absenTable" class="table table-sm table-striped table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="text-center" style="width:60px;">No</th>
                                        <th>Tanggal</th>
                                        <th>Jam</th>
                                        <th>Nama Karyawan</th>
                                        <th>Departemen</th>
                                        <th>Bagian</th>
                                        <th>Mesin</th>
                                        <th class="text-center">Jumlah Absen Kantin</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer small text-muted">
                        Gunakan Full Screen Mode untuk menampilkan layar absensi real-time ke monitor.
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<div class="dk-kiosk-overlay" id="kioskOverlay">
    <div class="dk-kiosk-page">
        <div class="dk-kiosk-header">
            <div class="dk-kiosk-brand">
                <div class="dk-kiosk-brand-icon"><i class="fas fa-utensils"></i></div>
                <div class="dk-kiosk-brand-text">
                    <h1 id="kioskMainTitle"><?= esc($kioskMainTitle) ?></h1>
                    <p>Sistem Absensi Kantin Karyawan</p>
                </div>
            </div>
            <div class="dk-kiosk-head-actions">
                <button type="button" class="dk-kiosk-settings-trigger" id="btnKioskSettingsToggle" title="Pengaturan Tampilan">
                    <i class="fas fa-cog"></i>
                </button>
                <button type="button" class="dk-kiosk-summary-trigger" id="btnKioskSummaryToggle" title="Ringkasan Total Absen">
                    <i class="fas fa-chart-bar"></i>
                </button>
                <div class="dk-kiosk-clock">
                    <div class="dk-kiosk-clock-icon"><i class="far fa-calendar-alt"></i></div>
                    <div class="dk-kiosk-clock-text">
                    <div class="dk-date" id="kioskClockDate">-</div>
                    <div class="dk-time" id="kioskClockTime">-</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="dk-kiosk-settings-panel" id="kioskSettingsPanel">
            <div class="dk-kiosk-settings-title">Pengaturan Tampilan Fullscreen</div>
            <label class="dk-kiosk-setting-item"><input type="checkbox" class="kiosk-setting-check" data-key="kpi_total_absensi" checked> Total Absensi</label>
            <label class="dk-kiosk-setting-item"><input type="checkbox" class="kiosk-setting-check" data-key="kpi_karyawan_unik" checked> Karyawan Unik</label>
            <label class="dk-kiosk-setting-item"><input type="checkbox" class="kiosk-setting-check" data-key="kpi_rata_harian" checked> Rata-Rata/Hari</label>
            <label class="dk-kiosk-setting-item"><input type="checkbox" class="kiosk-setting-check" data-key="kpi_total_berhasil" checked> Total Absensi Berhasil</label>
            <label class="dk-kiosk-setting-item"><input type="checkbox" class="kiosk-setting-check" data-key="kpi_total_ditolak" checked> Total Absensi Ditolak</label>
            <label class="dk-kiosk-setting-item"><input type="checkbox" class="kiosk-setting-check" data-key="kpi_shift_siang" checked> Shift Siang</label>
            <label class="dk-kiosk-setting-item"><input type="checkbox" class="kiosk-setting-check" data-key="kpi_shift_malam" checked> Shift Malam</label>
            <label class="dk-kiosk-setting-item"><input type="checkbox" class="kiosk-setting-check" data-key="kpi_shift_pagi" checked> Shift Pagi</label>
            <label class="dk-kiosk-setting-item"><input type="checkbox" class="kiosk-setting-check" data-key="kpi_non_shift" checked> Non Shift</label>
            <label class="dk-kiosk-setting-item"><input type="checkbox" class="kiosk-setting-check" data-key="kpi_no_all" checked> No All</label>
            <label class="dk-kiosk-setting-item"><input type="checkbox" class="kiosk-setting-check" data-key="panel_info_karyawan" checked> Panel Informasi Karyawan</label>
            <label class="dk-kiosk-setting-item"><input type="checkbox" class="kiosk-setting-check" data-key="card_lokasi_absen" checked> Card Lokasi Absen</label>
            <label class="dk-kiosk-setting-item"><input type="checkbox" class="kiosk-setting-check" data-key="card_absen_berhasil" checked> Card Absen Berhasil</label>
            <label class="dk-kiosk-setting-item"><input type="checkbox" class="kiosk-setting-check" data-key="card_absen_ditolak" checked> Card Absen Ditolak</label>
            <label class="dk-kiosk-setting-item"><input type="checkbox" class="kiosk-setting-check" data-key="status_line" checked> Status Line</label>
            <label class="dk-kiosk-setting-item"><input type="checkbox" class="kiosk-setting-check" data-key="footer_info" checked> Footer Informasi</label>
        </div>
        <div class="dk-kiosk-summary-modal" id="kioskSummaryModal">
            <div class="dk-kiosk-summary-dialog" role="dialog" aria-modal="true" aria-labelledby="kioskSummaryTitle">
                <div class="dk-kiosk-summary-header">
                    <div>
                        <h3 class="dk-kiosk-summary-title" id="kioskSummaryTitle">Ringkasan Total Absen</h3>
                        <div class="dk-kiosk-summary-period" id="kioskSummaryPeriod">
                            Periode: <?= esc($tanggalAwal) ?> s/d <?= esc($tanggalAkhir) ?>
                        </div>
                    </div>
                    <button type="button" class="dk-kiosk-summary-close" id="btnKioskSummaryClose" title="Tutup Ringkasan">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="dk-kiosk-summary-grid">
                    <div class="dk-kiosk-summary-item">
                        <div class="dk-kiosk-summary-label">Total Absen Berhasil</div>
                        <div class="dk-kiosk-summary-value" id="popupTotalBerhasil"><?= esc(number_format((int)($initialKioskStats['total_berhasil'] ?? 0), 0, ',', '.')) ?></div>
                    </div>
                    <div class="dk-kiosk-summary-item">
                        <div class="dk-kiosk-summary-label">Shift Pagi</div>
                        <div class="dk-kiosk-summary-value" id="popupShiftPagi"><?= esc(number_format((int)($initialKioskStats['shift_pagi'] ?? 0), 0, ',', '.')) ?></div>
                    </div>
                    <div class="dk-kiosk-summary-item">
                        <div class="dk-kiosk-summary-label">Shift Siang</div>
                        <div class="dk-kiosk-summary-value" id="popupShiftSiang"><?= esc(number_format((int)($initialKioskStats['shift_siang'] ?? 0), 0, ',', '.')) ?></div>
                    </div>
                    <div class="dk-kiosk-summary-item">
                        <div class="dk-kiosk-summary-label">Shift Malam</div>
                        <div class="dk-kiosk-summary-value" id="popupShiftMalam"><?= esc(number_format((int)($initialKioskStats['shift_malam'] ?? 0), 0, ',', '.')) ?></div>
                    </div>
                    <div class="dk-kiosk-summary-item">
                        <div class="dk-kiosk-summary-label">Non Shift</div>
                        <div class="dk-kiosk-summary-value" id="popupNonShift"><?= esc(number_format((int)($initialKioskStats['non_shift'] ?? 0), 0, ',', '.')) ?></div>
                    </div>
                    <div class="dk-kiosk-summary-item">
                        <div class="dk-kiosk-summary-label">No All</div>
                        <div class="dk-kiosk-summary-value" id="popupNoAll"><?= esc(number_format((int)($initialKioskStats['no_all'] ?? 0), 0, ',', '.')) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="dk-kiosk-shell" id="kioskShell">
            <div class="dk-fs-kpi-grid">
                <div class="dk-fs-kpi-card type-total" data-setting-key="kpi_total_absensi">
                    <div class="dk-fs-kpi-icon"><i class="fas fa-users"></i></div>
                    <div class="dk-fs-kpi-label">TOTAL ABSENSI</div>
                    <div class="dk-fs-kpi-value" id="statTotalAbsensi"><?= esc(number_format((int)($initialKioskStats['total_absensi'] ?? 0), 0, ',', '.')) ?></div>
                    <div class="dk-fs-kpi-foot">Total seluruh absensi</div>
                </div>
                <div class="dk-fs-kpi-card type-unique" data-setting-key="kpi_karyawan_unik">
                    <div class="dk-fs-kpi-icon"><i class="far fa-user"></i></div>
                    <div class="dk-fs-kpi-label">KARYAWAN UNIK</div>
                    <div class="dk-fs-kpi-value" id="statKaryawanUnik"><?= esc(number_format((int)($initialKioskStats['total_karyawan_unik'] ?? 0), 0, ',', '.')) ?></div>
                    <div class="dk-fs-kpi-foot">Jumlah karyawan unik</div>
                </div>
                <div class="dk-fs-kpi-card type-average" data-setting-key="kpi_rata_harian">
                    <div class="dk-fs-kpi-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="dk-fs-kpi-label">RATA-RATA/HARI</div>
                    <div class="dk-fs-kpi-value" id="statRataHarian"><?= esc(number_format((float)($initialKioskStats['avg_harian'] ?? 0), 2, ',', '.')) ?></div>
                    <div class="dk-fs-kpi-foot">Rata-rata absen per hari</div>
                </div>
                <div class="dk-fs-kpi-card type-success" data-setting-key="kpi_total_berhasil">
                    <div class="dk-fs-kpi-icon"><i class="fas fa-check"></i></div>
                    <div class="dk-fs-kpi-label">TOTAL ABSENSI BERHASIL</div>
                    <div class="dk-fs-kpi-value" id="statAbsenBerhasil"><?= esc(number_format((int)($initialKioskStats['total_berhasil'] ?? 0), 0, ',', '.')) ?></div>
                    <div class="dk-fs-kpi-foot">Total absensi berhasil</div>
                </div>
                <div class="dk-fs-kpi-card type-reject" data-setting-key="kpi_total_ditolak">
                    <div class="dk-fs-kpi-icon"><i class="fas fa-times"></i></div>
                    <div class="dk-fs-kpi-label">TOTAL ABSENSI DITOLAK</div>
                    <div class="dk-fs-kpi-value" id="statAbsenDitolak"><?= esc(number_format((int)($initialKioskStats['total_ditolak'] ?? 0), 0, ',', '.')) ?></div>
                    <div class="dk-fs-kpi-foot">Total absensi ditolak</div>
                </div>
                <div class="dk-fs-kpi-card type-shift-pagi" data-setting-key="kpi_shift_pagi">
                    <div class="dk-fs-kpi-icon"><i class="fas fa-cloud-sun"></i></div>
                    <div class="dk-fs-kpi-label">SHIFT PAGI</div>
                    <div class="dk-fs-kpi-value" id="statShiftPagi"><?= esc(number_format((int)($initialKioskStats['shift_pagi'] ?? 0), 0, ',', '.')) ?></div>
                    <div class="dk-fs-kpi-foot">Total absen shift pagi</div>
                </div>
                <div class="dk-fs-kpi-card type-shift-siang" data-setting-key="kpi_shift_siang">
                    <div class="dk-fs-kpi-icon"><i class="fas fa-sun"></i></div>
                    <div class="dk-fs-kpi-label">SHIFT SIANG</div>
                    <div class="dk-fs-kpi-value" id="statShiftSiang"><?= esc(number_format((int)($initialKioskStats['shift_siang'] ?? 0), 0, ',', '.')) ?></div>
                    <div class="dk-fs-kpi-foot">Total absen shift siang</div>
                </div>
                <div class="dk-fs-kpi-card type-shift-malam" data-setting-key="kpi_shift_malam">
                    <div class="dk-fs-kpi-icon"><i class="fas fa-moon"></i></div>
                    <div class="dk-fs-kpi-label">SHIFT MALAM</div>
                    <div class="dk-fs-kpi-value" id="statShiftMalam"><?= esc(number_format((int)($initialKioskStats['shift_malam'] ?? 0), 0, ',', '.')) ?></div>
                    <div class="dk-fs-kpi-foot">Total absen shift malam</div>
                </div>
                <div class="dk-fs-kpi-card type-non-shift" data-setting-key="kpi_non_shift">
                    <div class="dk-fs-kpi-icon"><i class="fas fa-user-clock"></i></div>
                    <div class="dk-fs-kpi-label">NON SHIFT</div>
                    <div class="dk-fs-kpi-value" id="statNonShift"><?= esc(number_format((int)($initialKioskStats['non_shift'] ?? 0), 0, ',', '.')) ?></div>
                    <div class="dk-fs-kpi-foot">Total absen non shift</div>
                </div>
                <div class="dk-fs-kpi-card type-no-all" data-setting-key="kpi_no_all">
                    <div class="dk-fs-kpi-icon"><i class="fas fa-ban"></i></div>
                    <div class="dk-fs-kpi-label">NO ALL</div>
                    <div class="dk-fs-kpi-value" id="statNoAll"><?= esc(number_format((int)($initialKioskStats['no_all'] ?? 0), 0, ',', '.')) ?></div>
                    <div class="dk-fs-kpi-foot">Total absen no all</div>
                </div>
            </div>

            <div class="dk-fs-main-grid">
                <div class="dk-fs-panel dk-fs-employee-panel" data-setting-key="panel_info_karyawan">
                    <div class="dk-fs-panel-title"><i class="far fa-user"></i> Informasi Karyawan</div>
                    <div class="dk-fs-employee-body">
                        <div class="dk-fs-photo-column">
                            <div class="dk-fs-photo">
                                <img id="empPhoto" src="<?= esc($initialEvent['photo_url'] ?? fallbackAvatarDataUri()) ?>" alt="Foto Karyawan">
                            </div>
                            <div class="dk-fs-emp-history">
                                <div class="dk-fs-emp-history-title">Riwayat Absensi Karyawan</div>
                                <ul class="dk-fs-emp-history-list" id="employeeHistoryList">
                                    <?php if (!empty($initialEmployeeHistory)): ?>
                                        <?php foreach ($initialEmployeeHistory as $hist): ?>
                                            <?php
                                                $statusCode = (string)($hist['status_code'] ?? '');
                                                $badgeClass = ($statusCode === 'reject') ? 'status-reject' : 'status-success';
                                                $statusLabel = (string)($hist['status_label'] ?? ($statusCode === 'reject' ? 'Ditolak' : 'Diterima'));
                                            ?>
                                            <li class="dk-fs-emp-history-item">
                                                <div class="dk-fs-emp-history-meta">
                                                    <span class="dk-fs-emp-history-date"><?= esc((string)($hist['tanggal'] ?? '-')) ?></span>
                                                    <span class="dk-fs-emp-history-time"><?= esc((string)($hist['jam'] ?? '--:--:--')) ?></span>
                                                </div>
                                                <span class="dk-fs-emp-history-badge <?= esc($badgeClass) ?>"><?= esc($statusLabel) ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li class="dk-fs-emp-history-empty">Belum ada riwayat absensi karyawan.</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                        <div class="dk-fs-fields">
                            <div class="dk-fs-field-item">
                                <div class="dk-fs-field-icon"><i class="far fa-user"></i></div>
                                <div class="dk-fs-field-body">
                                    <div class="dk-fs-field-label">Nama</div>
                                    <div class="dk-fs-field-value" id="empName"><?= esc($initialEvent['nama'] ?? 'Menunggu scan...') ?></div>
                                </div>
                            </div>
                            <div class="dk-fs-field-item">
                                <div class="dk-fs-field-icon"><i class="fas fa-building"></i></div>
                                <div class="dk-fs-field-body">
                                    <div class="dk-fs-field-label">Departemen</div>
                                    <div class="dk-fs-field-value" id="empDept"><?= esc($initialEvent['departemen'] ?? '-') ?></div>
                                </div>
                            </div>
                            <div class="dk-fs-field-item">
                                <div class="dk-fs-field-icon"><i class="fas fa-users"></i></div>
                                <div class="dk-fs-field-body">
                                    <div class="dk-fs-field-label">Bagian</div>
                                    <div class="dk-fs-field-value" id="empBagian"><?= esc($initialEvent['bagian'] ?? '-') ?></div>
                                </div>
                            </div>
                            <div class="dk-fs-field-item">
                                <div class="dk-fs-field-icon"><i class="far fa-clock"></i></div>
                                <div class="dk-fs-field-body">
                                    <div class="dk-fs-field-label">Shift</div>
                                    <div class="dk-fs-field-value" id="empShift"><?= esc($initialEvent['shift_label'] ?? '-') ?></div>
                                </div>
                            </div>
                            <div class="dk-fs-side-status">
                                <div class="dk-fs-status-line <?= $initialViolation ? 'violation' : '' ?>" id="statusLine" data-setting-key="status_line">
                                    <span class="dk-fs-status-icon" id="statusIcon"><?= $initialViolation ? 'X' : 'OK' ?></span>
                                    <span id="statusTitle"><?= esc($initialEvent['status_title'] ?? 'MENUNGGU ABSEN') ?></span>
                                </div>
                                <div class="dk-k-dual-time <?= $initialViolation ? 'active' : '' ?>" id="dualTimeBox">
                                    <div class="dk-k-dual-row">
                                        <span>Absen Sebelumnya</span>
                                        <b id="firstScanTime"><?= esc($initialEvent['first_scan_time'] ?? '-') ?></b>
                                    </div>
                                    <div class="dk-k-dual-row">
                                        <span>Absen Ditolak</span>
                                        <b id="secondScanTime"><?= esc($initialEvent['second_scan_time'] ?? '-') ?></b>
                                    </div>
                                </div>
                                <div class="dk-k-violation-note <?= $initialViolation ? 'active' : '' ?>" id="violationNote">
                                    Absen kantin berikutnya bisa dilakukan besok.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dk-fs-panel dk-fs-scan-panel" id="scanPanel">
                    <div class="dk-fs-scan-grid">
                        <div class="dk-fs-scan-card dk-fs-location-card" data-setting-key="card_lokasi_absen">
                            <div class="dk-fs-pill dk-fs-pill-location">LOKASI ABSEN</div>
                            <div class="dk-fs-scan-icon icon-location"><i class="fas fa-utensils"></i></div>
                            <div class="dk-fs-scan-meta">Lokasi Absen</div>
                            <div class="dk-fs-scan-time text-location" id="scanMachine"><?= esc($initialEvent['mesin'] ?? $selectedMachineLabel) ?></div>
                        </div>
                        <div class="dk-fs-scan-card dk-fs-success-card <?= $initialViolation ? '' : 'active' ?>" id="successCard" data-setting-key="card_absen_berhasil">
                            <div class="dk-fs-pill dk-fs-pill-success">ABSEN BERHASIL</div>
                            <div class="dk-fs-scan-icon icon-success"><i class="fas fa-check"></i></div>
                            <div class="dk-fs-scan-meta">Waktu Absen</div>
                            <div class="dk-fs-scan-time text-success" id="successTime"><?= esc($initialSuccessTime) ?></div>
                            <div class="dk-fs-scan-date" id="successDate"><?= esc($initialSuccessDate) ?></div>
                            <div class="dk-fs-scan-note" id="successInfoText"><?= esc($initialSuccessInfo) ?></div>
                            <div class="dk-fs-history">
                                <div class="dk-fs-history-title">Riwayat 7 Absen Berhasil</div>
                                <ul class="dk-fs-history-list" id="successHistoryList">
                                    <li class="dk-fs-history-item is-empty">Menunggu data riwayat...</li>
                                </ul>
                            </div>
                        </div>
                        <div class="dk-fs-scan-card dk-fs-reject-card <?= $initialViolation ? 'active' : '' ?>" id="rejectCard" data-setting-key="card_absen_ditolak">
                            <div class="dk-fs-pill dk-fs-pill-reject">ABSEN DITOLAK</div>
                            <div class="dk-fs-scan-icon icon-reject"><i class="fas fa-times"></i></div>
                            <div class="dk-fs-scan-meta">Waktu Absen Kantin</div>
                            <div class="dk-fs-scan-time text-reject" id="rejectTime"><?= esc($initialRejectTime) ?></div>
                            <div class="dk-fs-scan-date" id="rejectDate"><?= esc($initialRejectDate) ?></div>
                            <div class="dk-fs-scan-note" id="rejectInfoText"><?= esc($initialRejectInfo) ?></div>
                            <div class="dk-fs-history">
                                <div class="dk-fs-history-title">Riwayat 7 Absen Ditolak</div>
                                <ul class="dk-fs-history-list" id="rejectHistoryList">
                                    <li class="dk-fs-history-item is-empty">Belum ada data penolakan.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dk-k-footer" data-setting-key="footer_info">
                <div class="dk-k-footer-main">
                    <div class="dk-k-footer-icon"><i class="fas fa-info"></i></div>
                    <div class="dk-k-footer-text">
                        <div class="dk-k-footer-title">Pastikan data di atas sudah sesuai.</div>
                        <div class="dk-k-footer-sub">Terima kasih telah melakukan absensi kantin.</div>
                    </div>
                </div>
                <div class="dk-k-footer-right">
                    <div id="todayCountNote">
                    Filter: <?= esc($selectedMachineLabel) ?>
                    <?= isset($initialEvent['total_masuk_hari']) ? (' | Total absen hari ini: ' . (int)$initialEvent['total_masuk_hari'] . 'x') : '' ?>
                    </div>
                    <div class="dk-k-footer-art"><i class="fas fa-utensils"></i></div>
                </div>
            </div>
        </div>
        <div class="dk-k-exit">
            <select id="tvPresetKiosk" class="form-control form-control-sm" style="width:240px;">
                <option value="auto">Auto (Rekomendasi)</option>
                <option value="tv-1366">TV 1366 x 768</option>
                <option value="tv-1600">TV 1600 x 900</option>
                <option value="tv-1920">TV 1920 x 1080</option>
                <option value="tv-4k">TV 4K (3840 x 2160)</option>
            </select>
            <button type="button" id="btnExitKiosk" class="btn btn-light btn-sm"><i class="fas fa-compress"></i> Keluar Fullscreen</button>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>

<script>
    const AJAX_URL = 'ajax_dashboard_kantin.php';
    const KIOSK_MODE = '1';
    const KIOSK_TOKEN = <?= json_encode($kioskAccessToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    let tableRef = null;

    const fallbackAvatar = <?= json_encode(fallbackAvatarDataUri(), JSON_UNESCAPED_SLASHES) ?>;
    const initialSelectedMachineLabel = <?= json_encode($selectedMachineLabel, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const TV_PRESET_KEY = 'dk_tv_preset';
    const KIOSK_VISIBILITY_KEY = 'dk_kiosk_visibility';
    const TV_PRESET_CLASSES = ['preset-auto', 'preset-tv-1366', 'preset-tv-1600', 'preset-tv-1920', 'preset-tv-4k'];
    const AUTO_SHIFT_KPI_MODE = true;
    const AUTO_SHIFT_KPI_STATIC_KEYS = ['kpi_total_berhasil', 'kpi_total_ditolak'];
    const AUTO_SHIFT_KPI_DYNAMIC_KEYS = ['kpi_shift_pagi', 'kpi_shift_siang', 'kpi_shift_malam', 'kpi_non_shift', 'kpi_no_all'];
    const AUTO_SHIFT_KPI_HIDE_KEYS = ['kpi_total_absensi', 'kpi_karyawan_unik', 'kpi_rata_harian'];
    const KIOSK_VISIBILITY_DEFAULTS = {
        kpi_total_absensi: false,
        kpi_karyawan_unik: false,
        kpi_rata_harian: false,
        kpi_total_berhasil: true,
        kpi_total_ditolak: true,
        kpi_shift_siang: false,
        kpi_shift_malam: false,
        kpi_shift_pagi: false,
        kpi_non_shift: false,
        kpi_no_all: false,
        panel_info_karyawan: true,
        card_lokasi_absen: true,
        card_absen_berhasil: true,
        card_absen_ditolak: true,
        status_line: true,
        footer_info: true
    };
    let lastEventKey = <?= json_encode($initialEvent['event_key'] ?? '') ?>;
    const SOUND_ABSEN_SUCCESS = '/gg_app/pages/absen/sound/AbsensiBerhasil.mp3';
    const SOUND_ABSEN_REJECT_DEFAULT = '/gg_app/pages/absen/sound/AbsensiDitolak2.mp3';
    const SOUND_ABSEN_REJECT_OVER2 = '/gg_app/pages/absen/sound/AbsensiDitolak3.mp3';
    const audioSuccess = new Audio(SOUND_ABSEN_SUCCESS);
    const audioRejectDefault = new Audio(SOUND_ABSEN_REJECT_DEFAULT);
    const audioRejectOver2 = new Audio(SOUND_ABSEN_REJECT_OVER2);
    let audioUnlocked = false;
    let lastSoundAt = 0;
    let latestPollTimer = null;
    let latestPollInFlight = false;
    let latestFetchFailCount = 0;
    let kioskRecoveryReloading = false;
    let summaryMatrixInFlight = false;
    let machineCardsInFlight = false;
    let lastCalendarYmd = '';
    let lastAutoShiftKpiKey = '';
    let latestRejectHistoryEntry = null;
    const KIOSK_FETCH_TIMEOUT_MS = 12000;
    const KIOSK_RECOVERY_FAILURE_LIMIT = 120;

    const el = {
        kioskOverlay: document.getElementById('kioskOverlay'),
        kioskShell: document.getElementById('kioskShell'),
        empPhoto: document.getElementById('empPhoto'),
        empName: document.getElementById('empName'),
        empDept: document.getElementById('empDept'),
        empBagian: document.getElementById('empBagian'),
        empShift: document.getElementById('empShift'),
        scanPanel: document.getElementById('scanPanel'),
        scanMachine: document.getElementById('scanMachine'),
        successCard: document.getElementById('successCard'),
        rejectCard: document.getElementById('rejectCard'),
        successTime: document.getElementById('successTime'),
        successDate: document.getElementById('successDate'),
        successInfoText: document.getElementById('successInfoText'),
        successHistoryList: document.getElementById('successHistoryList'),
        rejectTime: document.getElementById('rejectTime'),
        rejectDate: document.getElementById('rejectDate'),
        rejectInfoText: document.getElementById('rejectInfoText'),
        rejectHistoryList: document.getElementById('rejectHistoryList'),
        employeeHistoryList: document.getElementById('employeeHistoryList'),
        statusLine: document.getElementById('statusLine'),
        statusIcon: document.getElementById('statusIcon'),
        statusTitle: document.getElementById('statusTitle'),
        dualTimeBox: document.getElementById('dualTimeBox'),
        firstScanTime: document.getElementById('firstScanTime'),
        secondScanTime: document.getElementById('secondScanTime'),
        violationNote: document.getElementById('violationNote'),
        fsKpiGrid: document.querySelector('.dk-fs-kpi-grid'),
        fsMainGrid: document.querySelector('.dk-fs-main-grid'),
        fsScanGrid: document.querySelector('.dk-fs-scan-grid'),
        statTotalAbsensi: document.getElementById('statTotalAbsensi'),
        statKaryawanUnik: document.getElementById('statKaryawanUnik'),
        statRataHarian: document.getElementById('statRataHarian'),
        statAbsenBerhasil: document.getElementById('statAbsenBerhasil'),
        statAbsenDitolak: document.getElementById('statAbsenDitolak'),
        statShiftSiang: document.getElementById('statShiftSiang'),
        statShiftMalam: document.getElementById('statShiftMalam'),
        statShiftPagi: document.getElementById('statShiftPagi'),
        statNonShift: document.getElementById('statNonShift'),
        statNoAll: document.getElementById('statNoAll'),
        cardKantinDepan: document.getElementById('cardKantinDepan'),
        cardKantinProduksi: document.getElementById('cardKantinProduksi'),
        cardKantinWeaving1: document.getElementById('cardKantinWeaving1'),
        cardKantinWeaving2: document.getElementById('cardKantinWeaving2'),
        todayCountNote: document.getElementById('todayCountNote'),
        clockDate: document.getElementById('clockDate'),
        clockTime: document.getElementById('clockTime'),
        kioskClockDate: document.getElementById('kioskClockDate'),
        kioskClockTime: document.getElementById('kioskClockTime'),
        kioskMainTitle: document.getElementById('kioskMainTitle'),
        tvPreset: document.getElementById('tvPreset'),
        tvPresetKiosk: document.getElementById('tvPresetKiosk'),
        btnKioskSettingsToggle: document.getElementById('btnKioskSettingsToggle'),
        btnKioskSummaryToggle: document.getElementById('btnKioskSummaryToggle'),
        kioskSettingsPanel: document.getElementById('kioskSettingsPanel'),
        kioskSummaryModal: document.getElementById('kioskSummaryModal'),
        kioskSummaryPeriod: document.getElementById('kioskSummaryPeriod'),
        btnKioskSummaryClose: document.getElementById('btnKioskSummaryClose'),
        btnExportSummary: document.getElementById('btnExportSummary'),
        btnFullScreen: document.getElementById('btnFullScreen'),
        btnExitKiosk: document.getElementById('btnExitKiosk'),
        mesinPicker: document.getElementById('mesinPicker'),
        tanggalAwal: document.getElementById('tanggalAwal'),
        tanggalAkhir: document.getElementById('tanggalAkhir'),
        filterInfo: document.getElementById('filterInfo'),
        summaryMatrixBody: document.getElementById('summaryMatrixBody'),
        popupTotalBerhasil: document.getElementById('popupTotalBerhasil'),
        popupShiftPagi: document.getElementById('popupShiftPagi'),
        popupShiftSiang: document.getElementById('popupShiftSiang'),
        popupShiftMalam: document.getElementById('popupShiftMalam'),
        popupNonShift: document.getElementById('popupNonShift'),
        popupNoAll: document.getElementById('popupNoAll')
    };

    function isKioskModeActive() {
        return !!(el.kioskOverlay && el.kioskOverlay.classList.contains('active'));
    }

    function fetchWithTimeout(url, options = {}, timeoutMs = KIOSK_FETCH_TIMEOUT_MS) {
        if (!window.AbortController) {
            return fetch(url, options);
        }

        const controller = new AbortController();
        const timer = setTimeout(function () {
            controller.abort();
        }, timeoutMs);

        return fetch(url, Object.assign({}, options, { signal: controller.signal }))
            .finally(function () {
                clearTimeout(timer);
            });
    }

    function recordLatestFetchHealth(isHealthy) {
        if (isHealthy) {
            latestFetchFailCount = 0;
            return;
        }

        if (!isKioskModeActive() || kioskRecoveryReloading) {
            return;
        }

        latestFetchFailCount++;
        if (latestFetchFailCount >= KIOSK_RECOVERY_FAILURE_LIMIT) {
            kioskRecoveryReloading = true;
            window.location.reload();
        }
    }

    let kioskVisibilityState = { ...KIOSK_VISIBILITY_DEFAULTS };

    function getFilterPayload() {
        return {
            mesin_id: el.mesinPicker ? (el.mesinPicker.value || '') : '',
            tanggal_awal: el.tanggalAwal ? (el.tanggalAwal.value || '') : '',
            tanggal_akhir: el.tanggalAkhir ? (el.tanggalAkhir.value || '') : ''
        };
    }

    function appendKioskAuth(params) {
        params.set('kiosk_mode', KIOSK_MODE);
        params.set('kiosk_token', KIOSK_TOKEN);
        return params;
    }

    function getSelectedMachineLabel() {
        if (!el.mesinPicker) return initialSelectedMachineLabel;
        const opt = el.mesinPicker.options[el.mesinPicker.selectedIndex];
        return opt ? opt.text : initialSelectedMachineLabel;
    }

    function updateFilterInfoLabel() {
        if (!el.filterInfo) return;
        const p = getFilterPayload();
        const machineLabel = getSelectedMachineLabel();
        el.filterInfo.textContent = 'Filter: ' + machineLabel + ' | Periode: ' + (p.tanggal_awal || '-') + ' s/d ' + (p.tanggal_akhir || '-');
        updateKioskSummaryPeriodLabel();
        updateExportSummaryLink();
    }

    function updateExportSummaryLink() {
        if (!el.btnExportSummary) return;
        const p = getFilterPayload();
        const q = new URLSearchParams({
            tanggal_awal: p.tanggal_awal || '',
            tanggal_akhir: p.tanggal_akhir || '',
            mesin_id: p.mesin_id || ''
        });
        el.btnExportSummary.href = 'export_excel_summary_kantin.php?' + q.toString();
    }

    function buildKioskTitle(machineLabel) {
        const label = (machineLabel || '').trim();
        if (!label || label.toLowerCase() === 'semua mesin') {
            return 'ABSEN KANTIN';
        }
        return 'ABSEN ' + label.toUpperCase();
    }

    function formatIntegerId(value) {
        const n = Number(value || 0);
        return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number.isFinite(n) ? n : 0);
    }

    function formatDecimalId(value) {
        const n = Number(value || 0);
        return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number.isFinite(n) ? n : 0);
    }

    function escapeHtml(text) {
        return String(text ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderHistoryList(listEl, entries, emptyText) {
        if (!listEl) return;
        if (!Array.isArray(entries) || entries.length === 0) {
            listEl.innerHTML = '<li class="dk-fs-history-item is-empty">' + escapeHtml(emptyText) + '</li>';
            return;
        }

        const html = entries.slice(0, 7).map(item => {
            const name = escapeHtml(item && item.nama ? item.nama : '-');
            const jam = escapeHtml(item && item.jam ? item.jam : '--:--:--');
            return '<li class="dk-fs-history-item"><span class="name">' + name + '</span><span class="time">' + jam + '</span></li>';
        }).join('');

        listEl.innerHTML = html || ('<li class="dk-fs-history-item is-empty">' + escapeHtml(emptyText) + '</li>');
    }

    function updateLatestRejectHistoryEntry(rejectList) {
        latestRejectHistoryEntry = null;
        if (!Array.isArray(rejectList) || rejectList.length === 0) {
            return;
        }

        const topReject = rejectList[0] || null;
        const jam = String(topReject && topReject.jam ? topReject.jam : '').trim();
        if (jam === '') {
            return;
        }

        const nama = String(topReject && topReject.nama ? topReject.nama : '').trim();
        latestRejectHistoryEntry = {
            jam: jam,
            nama: nama
        };
    }

    function buildRejectInfoText(violation, rejectTime, machineName) {
        if (violation && rejectTime !== '--:--:--') {
            return 'Absen ditolak di ' + machineName + ' jam ' + rejectTime;
        }
        if (latestRejectHistoryEntry && latestRejectHistoryEntry.jam) {
            return 'Absen ditolak terakhir jam ' + latestRejectHistoryEntry.jam + '.';
        }
        return 'Belum ada absen ditolak.';
    }

    function applyRecentHistory(historyData) {
        const successList = historyData && Array.isArray(historyData.success) ? historyData.success : [];
        const rejectList = historyData && Array.isArray(historyData.reject) ? historyData.reject : [];
        updateLatestRejectHistoryEntry(rejectList);

        renderHistoryList(el.successHistoryList, successList, 'Belum ada absen berhasil.');
        renderHistoryList(el.rejectHistoryList, rejectList, 'Belum ada data penolakan.');
    }

    function applyEmployeeHistory(historyData) {
        if (!el.employeeHistoryList) return;
        const entries = Array.isArray(historyData) ? historyData : [];
        if (entries.length === 0) {
            el.employeeHistoryList.innerHTML = '<li class="dk-fs-emp-history-empty">Belum ada riwayat absensi karyawan.</li>';
            return;
        }

        const html = entries.slice(0, 8).map(item => {
            const tanggal = escapeHtml(item && item.tanggal ? item.tanggal : '-');
            const jam = escapeHtml(item && item.jam ? item.jam : '--:--:--');
            const statusCodeRaw = String(item && item.status_code ? item.status_code : '').toLowerCase();
            const statusCode = statusCodeRaw === 'reject' ? 'reject' : 'success';
            const statusLabel = escapeHtml(item && item.status_label ? item.status_label : (statusCode === 'reject' ? 'Ditolak' : 'Diterima'));
            const badgeClass = statusCode === 'reject' ? 'status-reject' : 'status-success';

            return '<li class="dk-fs-emp-history-item">' +
                '<div class="dk-fs-emp-history-meta">' +
                    '<span class="dk-fs-emp-history-date">' + tanggal + '</span>' +
                    '<span class="dk-fs-emp-history-time">' + jam + '</span>' +
                '</div>' +
                '<span class="dk-fs-emp-history-badge ' + badgeClass + '">' + statusLabel + '</span>' +
            '</li>';
        }).join('');

        el.employeeHistoryList.innerHTML = html || '<li class="dk-fs-emp-history-empty">Belum ada riwayat absensi karyawan.</li>';
    }

    function formatYmdLocal(dateObj) {
        const y = dateObj.getFullYear();
        const m = String(dateObj.getMonth() + 1).padStart(2, '0');
        const d = String(dateObj.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + d;
    }

    function formatYmdIndo(ymd) {
        const raw = String(ymd || '').trim();
        if (!/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
            return raw || '-';
        }
        const parts = raw.split('-');
        const year = Number(parts[0]);
        const month = Number(parts[1]);
        const day = Number(parts[2]);
        if (!Number.isFinite(year) || !Number.isFinite(month) || !Number.isFinite(day)) {
            return raw;
        }
        const dt = new Date(year, month - 1, day);
        if (Number.isNaN(dt.getTime())) {
            return raw;
        }
        return dt.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
    }

    function updateKioskSummaryPeriodLabel() {
        if (!el.kioskSummaryPeriod) return;
        const p = getFilterPayload();
        const start = String(p.tanggal_awal || '').trim();
        const end = String(p.tanggal_akhir || '').trim();

        if (start && end) {
            if (start === end) {
                el.kioskSummaryPeriod.textContent = 'Tanggal: ' + formatYmdIndo(start);
            } else {
                el.kioskSummaryPeriod.textContent = 'Periode: ' + formatYmdIndo(start) + ' s/d ' + formatYmdIndo(end);
            }
            return;
        }
        if (start) {
            el.kioskSummaryPeriod.textContent = 'Tanggal Awal: ' + formatYmdIndo(start);
            return;
        }
        if (end) {
            el.kioskSummaryPeriod.textContent = 'Tanggal Akhir: ' + formatYmdIndo(end);
            return;
        }
        el.kioskSummaryPeriod.textContent = 'Periode: -';
    }

    function syncDateFiltersToToday(reload = true) {
        const now = new Date();
        const todayYmd = formatYmdLocal(now);
        let changed = false;

        if (el.tanggalAwal && el.tanggalAwal.value !== todayYmd) {
            el.tanggalAwal.value = todayYmd;
            changed = true;
        }
        if (el.tanggalAkhir && el.tanggalAkhir.value !== todayYmd) {
            el.tanggalAkhir.value = todayYmd;
            changed = true;
        }

        if (reload && changed) {
            reloadTable(true);
        }
    }

    function isFastRealtimeWindow(now = new Date()) {
        const h = now.getHours();
        const m = now.getMinutes();
        const s = now.getSeconds();
        const secondsOfDay = (h * 3600) + (m * 60) + s;

        const inShiftMalam = secondsOfDay >= (1 * 3600) && secondsOfDay < (4 * 3600);
        const inShiftPagi = secondsOfDay >= (9 * 3600) && secondsOfDay < (11 * 3600 + 30 * 60);
        const inNonShift = secondsOfDay >= (11 * 3600 + 30 * 60) && secondsOfDay < (13 * 3600 + 30 * 60);
        const inShiftSiang = secondsOfDay >= (17 * 3600) && secondsOfDay < (20 * 3600);

        return inShiftMalam || inShiftPagi || inNonShift || inShiftSiang;
    }

    function getLatestPollIntervalMs(now = new Date()) {
        return isFastRealtimeWindow(now) ? 1500 : 3000;
    }

    function getAutoShiftKpiKey(now = new Date()) {
        const h = now.getHours();
        const m = now.getMinutes();
        const s = now.getSeconds();
        const secondsOfDay = (h * 3600) + (m * 60) + s;

        const inShiftMalam = secondsOfDay >= (1 * 3600) && secondsOfDay < (4 * 3600);
        const inShiftPagi = secondsOfDay >= (9 * 3600) && secondsOfDay < (11 * 3600 + 30 * 60);
        const inNonShift = secondsOfDay >= (11 * 3600 + 30 * 60) && secondsOfDay < (13 * 3600 + 30 * 60);
        const inShiftSiang = secondsOfDay >= (17 * 3600) && secondsOfDay < (20 * 3600);

        if (inShiftMalam) return 'kpi_shift_malam';
        if (inShiftPagi) return 'kpi_shift_pagi';
        if (inNonShift) return 'kpi_non_shift';
        if (inShiftSiang) return 'kpi_shift_siang';
        return 'kpi_no_all';
    }

    function enforceAutoShiftKpiVisibility(now = new Date()) {
        if (!AUTO_SHIFT_KPI_MODE) return false;

        const activeShiftKey = getAutoShiftKpiKey(now);
        let changed = false;

        AUTO_SHIFT_KPI_HIDE_KEYS.forEach(key => {
            if (kioskVisibilityState[key] !== false) {
                kioskVisibilityState[key] = false;
                changed = true;
            }
        });

        AUTO_SHIFT_KPI_STATIC_KEYS.forEach(key => {
            if (kioskVisibilityState[key] !== true) {
                kioskVisibilityState[key] = true;
                changed = true;
            }
        });

        AUTO_SHIFT_KPI_DYNAMIC_KEYS.forEach(key => {
            const shouldShow = key === activeShiftKey;
            if (kioskVisibilityState[key] !== shouldShow) {
                kioskVisibilityState[key] = shouldShow;
                changed = true;
            }
        });

        if (changed || lastAutoShiftKpiKey !== activeShiftKey) {
            lastAutoShiftKpiKey = activeShiftKey;
            applyKioskVisibility();
            return true;
        }

        return false;
    }

    function lockAutoShiftKpiSettings() {
        if (!AUTO_SHIFT_KPI_MODE || !el.kioskSettingsPanel) return;

        const lockedKeys = [...AUTO_SHIFT_KPI_HIDE_KEYS, ...AUTO_SHIFT_KPI_STATIC_KEYS, ...AUTO_SHIFT_KPI_DYNAMIC_KEYS];
        const checks = el.kioskSettingsPanel.querySelectorAll('.kiosk-setting-check');
        checks.forEach(chk => {
            const key = chk.getAttribute('data-key');
            if (!key || !lockedKeys.includes(key)) return;
            chk.disabled = true;
            chk.title = 'Mode otomatis aktif: KPI mengikuti pengaturan jadwal shift.';
        });
    }

    function applyKioskStats(stats) {
        if (!stats) return;
        if (el.statTotalAbsensi) el.statTotalAbsensi.textContent = formatIntegerId(stats.total_absensi);
        if (el.statKaryawanUnik) el.statKaryawanUnik.textContent = formatIntegerId(stats.total_karyawan_unik);
        if (el.statRataHarian) el.statRataHarian.textContent = formatDecimalId(stats.avg_harian);
        if (el.statAbsenBerhasil) el.statAbsenBerhasil.textContent = formatIntegerId(stats.total_berhasil);
        if (el.statAbsenDitolak) el.statAbsenDitolak.textContent = formatIntegerId(stats.total_ditolak);
        if (el.statShiftSiang) el.statShiftSiang.textContent = formatIntegerId(stats.shift_siang);
        if (el.statShiftMalam) el.statShiftMalam.textContent = formatIntegerId(stats.shift_malam);
        if (el.statShiftPagi) el.statShiftPagi.textContent = formatIntegerId(stats.shift_pagi);
        if (el.statNonShift) el.statNonShift.textContent = formatIntegerId(stats.non_shift);
        if (el.statNoAll) el.statNoAll.textContent = formatIntegerId(stats.no_all);
        if (el.popupTotalBerhasil) el.popupTotalBerhasil.textContent = formatIntegerId(stats.total_berhasil);
        if (el.popupShiftPagi) el.popupShiftPagi.textContent = formatIntegerId(stats.shift_pagi);
        if (el.popupShiftSiang) el.popupShiftSiang.textContent = formatIntegerId(stats.shift_siang);
        if (el.popupShiftMalam) el.popupShiftMalam.textContent = formatIntegerId(stats.shift_malam);
        if (el.popupNonShift) el.popupNonShift.textContent = formatIntegerId(stats.non_shift);
        if (el.popupNoAll) el.popupNoAll.textContent = formatIntegerId(stats.no_all);
    }

    function applyMachineCards(cards) {
        if (!cards) return;
        if (el.cardKantinDepan) el.cardKantinDepan.textContent = formatIntegerId(cards.kantin_depan || 0);
        if (el.cardKantinProduksi) el.cardKantinProduksi.textContent = formatIntegerId(cards.kantin_produksi || 0);
        if (el.cardKantinWeaving1) el.cardKantinWeaving1.textContent = formatIntegerId(cards.kantin_weaving_1 || 0);
        if (el.cardKantinWeaving2) el.cardKantinWeaving2.textContent = formatIntegerId(cards.kantin_weaving_2 || 0);
    }

    function updateClock() {
        const now = new Date();
        const dateText = now.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
        const timeText = now.toLocaleTimeString('id-ID', { hour12: false });
        el.clockDate.textContent = dateText;
        el.clockTime.textContent = timeText;
        el.kioskClockDate.textContent = dateText;
        el.kioskClockTime.textContent = timeText;
        enforceAutoShiftKpiVisibility(now);

        const currentYmd = formatYmdLocal(now);
        if (lastCalendarYmd === '') {
            lastCalendarYmd = currentYmd;
        } else if (currentYmd !== lastCalendarYmd) {
            lastCalendarYmd = currentYmd;
            syncDateFiltersToToday(true);
        }
    }

    function showKioskMode() {
        el.kioskOverlay.classList.add('active');
        document.documentElement.classList.add('dk-no-scroll');
        document.body.classList.add('dk-no-scroll');
        enforceAutoShiftKpiVisibility(new Date());
        applyKioskVisibility();
    }

    function hideKioskMode() {
        el.kioskOverlay.classList.remove('active');
        document.documentElement.classList.remove('dk-no-scroll');
        document.body.classList.remove('dk-no-scroll');
        hideKioskSettingsPanel();
        hideKioskSummaryPopup();
    }

    function applyTvPreset(preset, persist = true) {
        const value = (preset || 'auto');
        TV_PRESET_CLASSES.forEach(cls => el.kioskOverlay.classList.remove(cls));

        let cls = 'preset-auto';
        if (value === 'tv-1366') cls = 'preset-tv-1366';
        if (value === 'tv-1600') cls = 'preset-tv-1600';
        if (value === 'tv-1920') cls = 'preset-tv-1920';
        if (value === 'tv-4k') cls = 'preset-tv-4k';

        el.kioskOverlay.classList.add(cls);

        if (el.tvPreset) el.tvPreset.value = value;
        if (el.tvPresetKiosk) el.tvPresetKiosk.value = value;

        if (persist) {
            try {
                localStorage.setItem(TV_PRESET_KEY, value);
            } catch (e) {}
        }
    }

    function loadKioskVisibilityState() {
        try {
            const raw = localStorage.getItem(KIOSK_VISIBILITY_KEY);
            if (!raw) return { ...KIOSK_VISIBILITY_DEFAULTS };
            const parsed = JSON.parse(raw);
            return { ...KIOSK_VISIBILITY_DEFAULTS, ...(parsed || {}) };
        } catch (e) {
            return { ...KIOSK_VISIBILITY_DEFAULTS };
        }
    }

    function saveKioskVisibilityState() {
        try {
            localStorage.setItem(KIOSK_VISIBILITY_KEY, JSON.stringify(kioskVisibilityState));
        } catch (e) {}
    }

    function applyKioskVisibility() {
        if (!el.kioskOverlay) return;
        const targets = el.kioskOverlay.querySelectorAll('[data-setting-key]');
        targets.forEach(node => {
            const key = node.getAttribute('data-setting-key');
            const show = kioskVisibilityState[key] !== false;
            node.classList.toggle('dk-hidden-by-setting', !show);
        });

        if (el.kioskSettingsPanel) {
            const checks = el.kioskSettingsPanel.querySelectorAll('.kiosk-setting-check');
            checks.forEach(chk => {
                const key = chk.getAttribute('data-key');
                chk.checked = kioskVisibilityState[key] !== false;
            });
        }

        if (el.fsMainGrid) {
            const visiblePanels = el.fsMainGrid.querySelectorAll('.dk-fs-panel:not(.dk-hidden-by-setting)').length;
            el.fsMainGrid.classList.toggle('single-visible', visiblePanels <= 1);
        }

        if (el.fsKpiGrid) {
            const visibleKpi = el.fsKpiGrid.querySelectorAll('.dk-fs-kpi-card:not(.dk-hidden-by-setting)').length;
            el.fsKpiGrid.classList.remove('cols-1', 'cols-2', 'cols-3', 'cols-4', 'cols-5', 'cols-6');
            const kpiCols = Math.max(1, Math.min(6, visibleKpi || 1));
            el.fsKpiGrid.classList.add('cols-' + String(kpiCols));
        }

        if (el.fsScanGrid) {
            const visibleCards = el.fsScanGrid.querySelectorAll('.dk-fs-scan-card:not(.dk-hidden-by-setting)').length;
            el.fsScanGrid.classList.remove('cols-1', 'cols-2');
            if (visibleCards <= 1) {
                el.fsScanGrid.classList.add('cols-1');
            } else if (visibleCards === 2) {
                el.fsScanGrid.classList.add('cols-2');
            }
        }
    }

    function hideKioskSettingsPanel() {
        if (el.kioskSettingsPanel) {
            el.kioskSettingsPanel.classList.remove('active');
        }
    }

    function hideKioskSummaryPopup() {
        if (el.kioskSummaryModal) {
            el.kioskSummaryModal.classList.remove('active');
        }
    }

    function toggleKioskSummaryPopup() {
        if (!el.kioskSummaryModal) return;
        hideKioskSettingsPanel();
        updateKioskSummaryPeriodLabel();
        el.kioskSummaryModal.classList.toggle('active');
    }

    function toggleKioskSettingsPanel() {
        if (!el.kioskSettingsPanel) return;
        hideKioskSummaryPopup();
        el.kioskSettingsPanel.classList.toggle('active');
    }

    function initKioskVisibilitySettings() {
        kioskVisibilityState = loadKioskVisibilityState();
        lockAutoShiftKpiSettings();
        enforceAutoShiftKpiVisibility(new Date());
        applyKioskVisibility();

        if (el.btnKioskSettingsToggle) {
            el.btnKioskSettingsToggle.addEventListener('click', function (ev) {
                ev.stopPropagation();
                toggleKioskSettingsPanel();
            });
        }

        if (el.kioskSettingsPanel) {
            el.kioskSettingsPanel.addEventListener('click', function (ev) {
                ev.stopPropagation();
            });

            const checks = el.kioskSettingsPanel.querySelectorAll('.kiosk-setting-check');
            checks.forEach(chk => {
                chk.addEventListener('change', function () {
                    const key = this.getAttribute('data-key');
                    if (!key) return;
                    kioskVisibilityState[key] = !!this.checked;
                    saveKioskVisibilityState();
                    applyKioskVisibility();
                });
            });
        }

        document.addEventListener('click', function (ev) {
            if (!el.kioskSettingsPanel || !el.kioskSettingsPanel.classList.contains('active')) return;
            if (el.btnKioskSettingsToggle && (ev.target === el.btnKioskSettingsToggle || el.btnKioskSettingsToggle.contains(ev.target))) {
                return;
            }
            hideKioskSettingsPanel();
        });
    }

    function initKioskSummaryPopup() {
        if (el.btnKioskSummaryToggle) {
            el.btnKioskSummaryToggle.addEventListener('click', function (ev) {
                ev.stopPropagation();
                toggleKioskSummaryPopup();
            });
        }

        if (el.btnKioskSummaryClose) {
            el.btnKioskSummaryClose.addEventListener('click', function () {
                hideKioskSummaryPopup();
            });
        }

        if (el.kioskSummaryModal) {
            el.kioskSummaryModal.addEventListener('click', function (ev) {
                if (ev.target === el.kioskSummaryModal) {
                    hideKioskSummaryPopup();
                }
            });
        }

        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape') {
                hideKioskSummaryPopup();
            }
        });
    }

    function unlockAudio() {
        if (audioUnlocked) return;
        const list = [audioSuccess, audioRejectDefault, audioRejectOver2];
        list.forEach(a => {
            a.preload = 'auto';
            a.volume = 1;
            a.muted = true;
            try {
                const p = a.play();
                if (p && typeof p.then === 'function') {
                    p.then(() => {
                        a.pause();
                        a.currentTime = 0;
                        a.muted = false;
                    }).catch(() => {
                        a.muted = false;
                    });
                } else {
                    a.pause();
                    a.currentTime = 0;
                    a.muted = false;
                }
            } catch (e) {
                a.muted = false;
            }
        });
        audioUnlocked = true;
    }

    function playEventSound(statusCode, rejectAudioKey) {
        const now = Date.now();
        if ((now - lastSoundAt) < 900) return;
        lastSoundAt = now;
        let target = audioSuccess;
        if (statusCode === 'violation') {
            target = String(rejectAudioKey || '') === 'reject_over2' ? audioRejectOver2 : audioRejectDefault;
        }
        try {
            target.pause();
            target.currentTime = 0;
            const p = target.play();
            if (p && typeof p.catch === 'function') p.catch(() => {});
        } catch (e) {}
    }

    function getFullscreenElementCompat() {
        return document.fullscreenElement
            || document.webkitFullscreenElement
            || document.mozFullScreenElement
            || document.msFullscreenElement
            || null;
    }

    function isFullscreenActive() {
        return !!getFullscreenElementCompat();
    }

    async function requestFullscreenCompat(targetEl) {
        const req = targetEl.requestFullscreen
            || targetEl.webkitRequestFullscreen
            || targetEl.webkitRequestFullScreen
            || targetEl.mozRequestFullScreen
            || targetEl.msRequestFullscreen;

        if (!req) return false;
        try {
            const result = req.call(targetEl);
            if (result && typeof result.then === 'function') {
                await result;
            }
            return true;
        } catch (e) {
            return false;
        }
    }

    async function exitFullscreenCompat() {
        const exit = document.exitFullscreen
            || document.webkitExitFullscreen
            || document.webkitCancelFullScreen
            || document.mozCancelFullScreen
            || document.msExitFullscreen;

        if (!exit) return false;
        try {
            const result = exit.call(document);
            if (result && typeof result.then === 'function') {
                await result;
            }
            return true;
        } catch (e) {
            return false;
        }
    }

    async function enterFullScreenKiosk() {
        unlockAudio();
        showKioskMode();
        fetchLatest();
        if (!isFullscreenActive()) {
            await requestFullscreenCompat(document.documentElement);
        }
        if (isFullscreenActive()) {
            el.kioskOverlay.classList.add('is-full');
        }
    }

    async function exitFullScreenKiosk() {
        if (isFullscreenActive()) {
            await exitFullscreenCompat();
        }
        el.kioskOverlay.classList.remove('is-full');
        hideKioskSettingsPanel();
        hideKioskMode();
    }

    function applyEvent(eventData, animate = false) {
        if (!eventData) {
            return;
        }

        el.empPhoto.src = eventData.photo_url || fallbackAvatar;
        el.empName.textContent = eventData.nama || '-';
        el.empDept.textContent = eventData.departemen || '-';
        el.empBagian.textContent = eventData.bagian || '-';
        el.empShift.textContent = eventData.shift_label || '-';
        if (el.scanMachine) {
            el.scanMachine.textContent = eventData.mesin || getSelectedMachineLabel() || '-';
        }
        if (el.kioskMainTitle) {
            el.kioskMainTitle.textContent = buildKioskTitle(getSelectedMachineLabel());
        }
        el.statusTitle.textContent = eventData.status_title || 'MENUNGGU ABSEN';

        const violation = eventData.status_code === 'violation';
        const baseTime = eventData.jam || '--:--:--';
        const baseDate = eventData.tanggal_text || '-';
        const machineName = eventData.mesin || getSelectedMachineLabel() || '-';
        const successTime = violation
            ? ((eventData.first_scan_time && eventData.first_scan_time !== '-') ? eventData.first_scan_time : '--:--:--')
            : baseTime;
        const successDate = successTime === '--:--:--' ? '-' : baseDate;
        const rejectTime = violation
            ? ((eventData.second_scan_time && eventData.second_scan_time !== '-') ? eventData.second_scan_time : baseTime)
            : '--:--:--';
        const rejectDate = rejectTime === '--:--:--' ? '-' : baseDate;

        if (el.successTime) el.successTime.textContent = successTime;
        if (el.successDate) el.successDate.textContent = successDate;
        if (el.rejectTime) el.rejectTime.textContent = rejectTime;
        if (el.rejectDate) el.rejectDate.textContent = rejectDate;
        if (el.successInfoText) {
            el.successInfoText.textContent = successTime !== '--:--:--'
                ? ('Absen berhasil di ' + machineName)
                : 'Menunggu data absen berhasil.';
        }
        if (el.rejectInfoText) {
            el.rejectInfoText.textContent = buildRejectInfoText(violation, rejectTime, machineName);
        }

        if (el.scanPanel) el.scanPanel.classList.toggle('violation', violation);
        if (el.statusLine) el.statusLine.classList.toggle('violation', violation);
        if (el.successCard) el.successCard.classList.toggle('active', !violation);
        if (el.rejectCard) el.rejectCard.classList.toggle('active', violation);
        el.statusIcon.textContent = violation ? 'X' : 'OK';
        el.dualTimeBox.classList.toggle('active', violation);
        el.violationNote.classList.toggle('active', violation);
        el.firstScanTime.textContent = (eventData.first_scan_time && eventData.first_scan_time !== '') ? eventData.first_scan_time : '-';
        el.secondScanTime.textContent = (eventData.second_scan_time && eventData.second_scan_time !== '') ? eventData.second_scan_time : (violation ? baseTime : '-');

        const cnt = Number(eventData.total_masuk_hari || 0);
        const activeMachineLabel = getSelectedMachineLabel();
        el.todayCountNote.textContent = 'Filter: ' + activeMachineLabel + (cnt > 0 ? (' | Total absen hari ini: ' + cnt + 'x') : '');

        if (animate && el.kioskOverlay.classList.contains('active')) {
            el.kioskShell.classList.remove('dk-k-flash');
            void el.kioskShell.offsetWidth;
            el.kioskShell.classList.add('dk-k-flash');
        }
    }

    function applyWaitingState() {
        el.empPhoto.src = fallbackAvatar;
        el.empName.textContent = 'Menunggu scan...';
        el.empDept.textContent = '-';
        el.empBagian.textContent = '-';
        el.empShift.textContent = '-';
        if (el.scanMachine) {
            el.scanMachine.textContent = getSelectedMachineLabel() || '-';
        }
        if (el.successTime) el.successTime.textContent = '--:--:--';
        if (el.successDate) el.successDate.textContent = '-';
        if (el.rejectTime) el.rejectTime.textContent = '--:--:--';
        if (el.rejectDate) el.rejectDate.textContent = '-';
        if (el.successInfoText) el.successInfoText.textContent = 'Menunggu data absen berhasil.';
        if (el.rejectInfoText) el.rejectInfoText.textContent = buildRejectInfoText(false, '--:--:--', getSelectedMachineLabel() || '-');
        el.statusTitle.textContent = 'MENUNGGU ABSEN';
        if (el.scanPanel) el.scanPanel.classList.remove('violation');
        if (el.statusLine) el.statusLine.classList.remove('violation');
        if (el.successCard) el.successCard.classList.remove('active');
        if (el.rejectCard) el.rejectCard.classList.remove('active');
        el.statusIcon.textContent = 'OK';
        el.dualTimeBox.classList.remove('active');
        el.violationNote.classList.remove('active');
        el.firstScanTime.textContent = '-';
        el.secondScanTime.textContent = '-';
        const activeMachineLabel = getSelectedMachineLabel();
        el.todayCountNote.textContent = 'Filter: ' + activeMachineLabel + ' | Menunggu data absen...';
        applyEmployeeHistory([]);
    }
    async function fetchLatest() {
        try {
            const p = getFilterPayload();
            const q = new URLSearchParams({
                action: 'latest',
                mesin_id: p.mesin_id,
                tanggal_awal: p.tanggal_awal,
                tanggal_akhir: p.tanggal_akhir,
                _: String(Date.now())
            });
            appendKioskAuth(q);
            const resp = await fetchWithTimeout(AJAX_URL + '?' + q.toString(), { cache: 'no-store' });
            if (!resp.ok) {
                recordLatestFetchHealth(false);
                return;
            }
            const json = await resp.json();
            if (!json || !json.ok) {
                recordLatestFetchHealth(false);
                return;
            }
            recordLatestFetchHealth(true);
            if (json.kiosk_stats) {
                applyKioskStats(json.kiosk_stats);
            }
            applyRecentHistory(json.recent_history || null);
            applyEmployeeHistory(json.employee_history || []);
            if (!json.event) {
                applyWaitingState();
                return;
            }

            const ev = json.event;
            const isNewEvent = ev.event_key && ev.event_key !== lastEventKey;
            if (isNewEvent) {
                lastEventKey = ev.event_key;
                if (el.kioskOverlay.classList.contains('active') && isFullscreenActive()) {
                    playEventSound(ev.status_code || '', ev.reject_audio_key || '');
                }
            }
            applyEvent(ev, isNewEvent);
        } catch (err) {
            recordLatestFetchHealth(false);
        }
    }

    function scheduleLatestPolling() {
        if (latestPollTimer) {
            clearTimeout(latestPollTimer);
            latestPollTimer = null;
        }

        const delay = getLatestPollIntervalMs(new Date());
        latestPollTimer = setTimeout(async function () {
            if (el.kioskOverlay.classList.contains('active') && !latestPollInFlight) {
                latestPollInFlight = true;
                try {
                    await fetchLatest();
                } finally {
                    latestPollInFlight = false;
                }
            }
            scheduleLatestPolling();
        }, delay);
    }

    async function loadSummaryMatrix() {
        if (!el.summaryMatrixBody) return;
        if (summaryMatrixInFlight) return;
        summaryMatrixInFlight = true;
        try {
            const p = getFilterPayload();
            const q = new URLSearchParams({
                action: 'summary_matrix',
                mesin_id: p.mesin_id,
                tanggal_awal: p.tanggal_awal,
                tanggal_akhir: p.tanggal_akhir,
                _: String(Date.now())
            });
            appendKioskAuth(q);
            const resp = await fetchWithTimeout(AJAX_URL + '?' + q.toString(), { cache: 'no-store' });
            if (!resp.ok) {
                el.summaryMatrixBody.innerHTML = '<div class="dk-loading text-danger">Gagal memuat summary.</div>';
                return;
            }
            const json = await resp.json();
            if (!json || !json.ok) {
                el.summaryMatrixBody.innerHTML = '<div class="dk-loading text-danger">Summary tidak tersedia.</div>';
                return;
            }
            el.summaryMatrixBody.innerHTML = json.html || '<div class="dk-loading text-muted">Tidak ada data.</div>';
        } catch (err) {
            el.summaryMatrixBody.innerHTML = '<div class="dk-loading text-danger">Terjadi kesalahan saat memuat summary.</div>';
        } finally {
            summaryMatrixInFlight = false;
        }
    }

    async function loadMachineSuccessCards() {
        if (machineCardsInFlight) return;
        machineCardsInFlight = true;
        try {
            const p = getFilterPayload();
            const q = new URLSearchParams({
                action: 'machine_success_cards',
                mesin_id: p.mesin_id,
                tanggal_awal: p.tanggal_awal,
                tanggal_akhir: p.tanggal_akhir,
                _: String(Date.now())
            });
            appendKioskAuth(q);
            const resp = await fetchWithTimeout(AJAX_URL + '?' + q.toString(), { cache: 'no-store' });
            if (!resp.ok) return;
            const json = await resp.json();
            if (!json || !json.ok || !json.cards) return;
            applyMachineCards(json.cards);
        } catch (err) {
        } finally {
            machineCardsInFlight = false;
        }
    }

    function reloadTable(resetPaging = false) {
        updateFilterInfoLabel();
        if (el.kioskMainTitle) {
            el.kioskMainTitle.textContent = buildKioskTitle(getSelectedMachineLabel());
        }
        if (tableRef && !isKioskModeActive()) {
            tableRef.ajax.reload(null, resetPaging);
        }
        loadMachineSuccessCards();
        loadSummaryMatrix();
        if (isKioskModeActive()) {
            fetchLatest();
        }
    }

    $(function () {
        tableRef = $('#absenTable').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            autoWidth: false,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            order: [[1, 'desc'], [2, 'desc']],
            ajax: {
                url: AJAX_URL,
                type: 'GET',
                data: function (d) {
                    const p = getFilterPayload();
                    d.action = 'table';
                    d.mesin_id = p.mesin_id;
                    d.tanggal_awal = p.tanggal_awal;
                    d.tanggal_akhir = p.tanggal_akhir;
                    d.kiosk_mode = KIOSK_MODE;
                    d.kiosk_token = KIOSK_TOKEN;
                }
            },
            columns: [
                { data: 'no', className: 'text-center' },
                { data: 'tanggal' },
                { data: 'jam' },
                { data: 'nama' },
                { data: 'dept' },
                { data: 'bagian' },
                { data: 'mesin' },
                { data: 'jumlah_badge', className: 'text-center', orderable: false, searchable: false }
            ],
            language: {
                lengthMenu: "Tampilkan _MENU_ data per halaman",
                zeroRecords: "Tidak ada data ditemukan",
                info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
                infoEmpty: "Tidak ada data tersedia",
                search: "Cari:",
                paginate: {
                    previous: "Sebelumnya",
                    next: "Selanjutnya"
                }
            }
        });

        if (el.mesinPicker) {
            el.mesinPicker.addEventListener('change', function () { reloadTable(true); });
        }
        if (el.tanggalAwal) {
            el.tanggalAwal.addEventListener('change', function () { reloadTable(true); });
        }
        if (el.tanggalAkhir) {
            el.tanggalAkhir.addEventListener('change', function () { reloadTable(true); });
        }

        let initialPreset = 'auto';
        try {
            const saved = localStorage.getItem(TV_PRESET_KEY);
            if (saved) initialPreset = saved;
        } catch (e) {}
        applyTvPreset(initialPreset, false);

        if (el.tvPreset) {
            el.tvPreset.addEventListener('change', function () {
                applyTvPreset(this.value, true);
            });
        }
        if (el.tvPresetKiosk) {
            el.tvPresetKiosk.addEventListener('change', function () {
                applyTvPreset(this.value, true);
            });
        }

        syncDateFiltersToToday(false);
        updateFilterInfoLabel();
        loadMachineSuccessCards();
        loadSummaryMatrix();
        initKioskVisibilitySettings();
        initKioskSummaryPopup();
        if (el.kioskMainTitle) {
            el.kioskMainTitle.textContent = buildKioskTitle(getSelectedMachineLabel());
        }
    });

    function handleFullscreenChange() {
        if (isFullscreenActive()) {
            el.kioskOverlay.classList.add('is-full');
            applyKioskVisibility();
        } else {
            el.kioskOverlay.classList.remove('is-full');
            hideKioskMode();
        }
    }

    document.addEventListener('fullscreenchange', handleFullscreenChange);
    document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
    document.addEventListener('mozfullscreenchange', handleFullscreenChange);
    document.addEventListener('MSFullscreenChange', handleFullscreenChange);

    el.btnFullScreen.addEventListener('click', enterFullScreenKiosk);
    el.btnExitKiosk.addEventListener('click', exitFullScreenKiosk);

    updateClock();
    setInterval(updateClock, 1000);
    scheduleLatestPolling();
    setInterval(function () {
        if (tableRef && !isKioskModeActive()) {
            tableRef.ajax.reload(null, false);
        }
    }, 8000);
    setInterval(function () {
        const summaryOpen = el.kioskSummaryModal && el.kioskSummaryModal.classList.contains('active');
        if (!isKioskModeActive() || summaryOpen) {
            loadSummaryMatrix();
        }
    }, 15000);
    setInterval(function () {
        loadMachineSuccessCards();
    }, 15000);
</script>
