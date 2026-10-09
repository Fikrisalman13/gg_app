<?php

date_default_timezone_set('Asia/Jakarta');

function getSqlsrvConnection($target = 'gg')
{
    if ($target !== 'gg') {
        throw new InvalidArgumentException("Koneksi sqlsrv hanya tersedia untuk target '{$target}' yang tidak valid.");
    }

    static $conn = null;
    if ($conn === null) {
        require __DIR__ . '/../../../koneksi.php';
        if (!isset($conn) || $conn === false) {
            throw new RuntimeException(formatSqlsrvErrors());
        }
    }

    return $conn;
}

function getErpConnection()
{
    static $conn3Instance = null;
    if ($conn3Instance === null) {
        require __DIR__ . '/../../../koneksi3.php';
        if (!isset($conn3) || !($conn3 instanceof PDO)) {
            throw new RuntimeException('Koneksi ERP dari koneksi3.php tidak tersedia.');
        }
        $conn3Instance = $conn3;
    }

    return $conn3Instance;
}

function formatSqlsrvErrors()
{
    $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
    if (!$errors) {
        return 'Unknown SQL Server error';
    }

    $messages = [];
    foreach ($errors as $error) {
        $messages[] = trim(($error['SQLSTATE'] ?? 'N/A') . ' - ' . ($error['message'] ?? 'Unknown error'));
    }

    return implode(' | ', $messages);
}

function jsonResponse(array $payload, int $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function normalizeDateInput($value, $endOfDay = false)
{
    $value = trim((string) $value);
    if ($value === '') {
        throw new InvalidArgumentException('Tanggal wajib diisi.');
    }

    $formats = ['Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'];
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date instanceof DateTime) {
            if ($format === 'Y-m-d') {
                $date->setTime($endOfDay ? 23 : 0, $endOfDay ? 59 : 0, $endOfDay ? 59 : 0);
            } elseif ($format === 'Y-m-d\TH:i' || $format === 'Y-m-d H:i') {
                $date->setTime((int) $date->format('H'), (int) $date->format('i'), $endOfDay ? 59 : 0);
            }

            return $date->format('Y-m-d H:i:s');
        }
    }

    throw new InvalidArgumentException('Format tanggal tidak valid.');
}

function parseRtgmsIds($raw)
{
    if (is_array($raw)) {
        $parts = $raw;
    } else {
        $parts = preg_split('/[\s,;]+/', (string) $raw);
    }

    $ids = [];
    foreach ($parts as $part) {
        $part = trim((string) $part);
        if ($part === '') {
            continue;
        }

        if (!ctype_digit($part)) {
            throw new InvalidArgumentException("RTGMSID '{$part}' tidak valid.");
        }

        $ids[] = (int) $part;
    }

    $ids = array_values(array_unique($ids));
    if (!$ids) {
        throw new InvalidArgumentException('Minimal satu RTGMSID wajib diisi.');
    }

    return $ids;
}

function sqlsrvAll($stmt)
{
    $rows = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }

    return $rows;
}

function sqlsrvExecOrFail($conn, $sql, array $params = [])
{
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        throw new RuntimeException(formatSqlsrvErrors());
    }

    return $stmt;
}

function pdoFetchAll(PDO $pdo, $sql, array $params = [])
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function pdoExecStatement(PDO $pdo, $sql, array $params = [])
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function ensureTablesExist($conn)
{
    $sql = "
    IF OBJECT_ID('dbo.mko_rawdata', 'U') IS NULL
    BEGIN
        CREATE TABLE dbo.mko_rawdata (
            id INT IDENTITY(1,1) PRIMARY KEY,
            kode_periode VARCHAR(50) NOT NULL,
            period_start DATETIME NOT NULL,
            period_end DATETIME NOT NULL,
            productionhdid BIGINT NULL,
            rtgmsid BIGINT NULL,
            prdnmbr VARCHAR(50) NULL,
            fgresult VARCHAR(100) NULL,
            rtgname VARCHAR(255) NULL,
            fgstatus VARCHAR(10) NULL,
            startdate DATETIME NULL,
            enddate DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT GETDATE(),
            created_by VARCHAR(100) NULL
        );
        CREATE INDEX IX_mko_rawdata_kode_periode ON dbo.mko_rawdata(kode_periode);
        CREATE INDEX IX_mko_rawdata_prdnmbr ON dbo.mko_rawdata(prdnmbr);
    END;
    ELSE
    BEGIN
        IF EXISTS (
            SELECT 1
            FROM sys.columns
            WHERE object_id = OBJECT_ID('dbo.mko_rawdata')
              AND name = 'fgresult'
              AND system_type_id <> 167
        )
        BEGIN
            ALTER TABLE dbo.mko_rawdata ALTER COLUMN fgresult VARCHAR(100) NULL;
        END;
    END;

    IF OBJECT_ID('dbo.MKO_materialobat', 'U') IS NULL
    BEGIN
        CREATE TABLE dbo.MKO_materialobat (
            id INT IDENTITY(1,1) PRIMARY KEY,
            kode_periode VARCHAR(50) NOT NULL,
            period_start DATETIME NOT NULL,
            period_end DATETIME NOT NULL,
            prdnmbr VARCHAR(50) NULL,
            prodcode VARCHAR(100) NULL,
            prodname VARCHAR(255) NULL,
            labeljual VARCHAR(255) NULL,
            cuscolor VARCHAR(255) NULL,
            colorcode VARCHAR(100) NULL,
            colorname VARCHAR(255) NULL,
            rtgproccode VARCHAR(100) NULL,
            rtgprocname VARCHAR(255) NULL,
            rtgcode VARCHAR(100) NULL,
            rtgname VARCHAR(255) NULL,
            material_code VARCHAR(100) NULL,
            material_name VARCHAR(255) NULL,
            matqty DECIMAL(18,6) NULL,
            uomcode VARCHAR(50) NULL,
            unit_price DECIMAL(18,6) NULL,
            subtotal DECIMAL(18,6) NULL,
            routing_qty DECIMAL(18,6) NULL,
            cost_meter DECIMAL(18,6) NULL,
            prdqty DECIMAL(18,6) NULL,
            bonweight DECIMAL(18,6) NULL,
            prodcf DECIMAL(18,6) NULL,
            bomcode VARCHAR(100) NULL,
            vlot VARCHAR(100) NULL,
            prdstdqty DECIMAL(18,6) NULL,
            kelompok VARCHAR(100) NULL,
            created_at DATETIME NOT NULL DEFAULT GETDATE(),
            created_by VARCHAR(100) NULL
        );
        CREATE INDEX IX_MKO_materialobat_kode_periode ON dbo.MKO_materialobat(kode_periode);
        CREATE INDEX IX_MKO_materialobat_prdnmbr ON dbo.MKO_materialobat(prdnmbr);
    END;
    ELSE
    BEGIN
        IF COL_LENGTH('dbo.MKO_materialobat', 'kelompok') IS NULL
        BEGIN
            ALTER TABLE dbo.MKO_materialobat ADD kelompok VARCHAR(100) NULL;
        END;
    END;

    IF OBJECT_ID('dbo.MKO_rekap', 'U') IS NULL
    BEGIN
        CREATE TABLE dbo.MKO_rekap (
            id INT IDENTITY(1,1) PRIMARY KEY,
            kode_periode VARCHAR(50) NOT NULL,
            period_start DATETIME NULL,
            period_end DATETIME NULL,
            prdnmbr VARCHAR(50) NULL,
            labeljual VARCHAR(255) NULL,
            colorname VARCHAR(255) NULL,
            cuscolor VARCHAR(255) NULL,
            colorcode VARCHAR(100) NULL,
            master_resep VARCHAR(20) NULL,
            rtgname VARCHAR(255) NULL,
            status_proses_acc VARCHAR(255) NULL,
            status_cp VARCHAR(30) NULL,
            vlot VARCHAR(100) NULL,
            disperse DECIMAL(18,6) NOT NULL DEFAULT 0,
            reactive DECIMAL(18,6) NOT NULL DEFAULT 0,
            grand_total DECIMAL(18,6) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT GETDATE(),
            created_by VARCHAR(100) NULL
        );
        CREATE INDEX IX_MKO_rekap_kode_periode ON dbo.MKO_rekap(kode_periode);
    END;
    ELSE
    BEGIN
        IF COL_LENGTH('dbo.MKO_rekap', 'prdnmbr') IS NULL
        BEGIN
            ALTER TABLE dbo.MKO_rekap ADD prdnmbr VARCHAR(50) NULL;
        END;
        IF COL_LENGTH('dbo.MKO_rekap', 'master_resep') IS NULL
        BEGIN
            ALTER TABLE dbo.MKO_rekap ADD master_resep VARCHAR(20) NULL;
        END;
        IF COL_LENGTH('dbo.MKO_rekap', 'status_proses_acc') IS NULL
        BEGIN
            ALTER TABLE dbo.MKO_rekap ADD status_proses_acc VARCHAR(50) NULL;
        END;
        IF COL_LENGTH('dbo.MKO_rekap', 'status_cp') IS NULL
        BEGIN
            ALTER TABLE dbo.MKO_rekap ADD status_cp VARCHAR(30) NULL;
        END;
        IF COL_LENGTH('dbo.MKO_rekap', 'vlot') IS NULL
        BEGIN
            ALTER TABLE dbo.MKO_rekap ADD vlot VARCHAR(100) NULL;
        END;
        IF EXISTS (
            SELECT 1
            FROM sys.columns
            WHERE object_id = OBJECT_ID('dbo.MKO_rekap')
              AND name = 'status_proses_acc'
              AND max_length < 255
        )
        BEGIN
            ALTER TABLE dbo.MKO_rekap ALTER COLUMN status_proses_acc VARCHAR(255) NULL;
        END;
    END;

    IF OBJECT_ID('dbo.MKO_PPT_CPSTATUS_FAIL', 'U') IS NULL
    BEGIN
        CREATE TABLE dbo.MKO_PPT_CPSTATUS_FAIL (
            id INT IDENTITY(1,1) PRIMARY KEY,
            kode_periode VARCHAR(50) NOT NULL,
            period_start DATETIME NULL,
            period_end DATETIME NULL,
            labeljual VARCHAR(255) NULL,
            colorname VARCHAR(255) NULL,
            cuscolor VARCHAR(255) NULL,
            colorcode VARCHAR(100) NULL,
            cp VARCHAR(50) NULL,
            status_proses_acc VARCHAR(255) NULL,
            status_cp VARCHAR(30) NULL,
            routing VARCHAR(255) NULL,
            disperse DECIMAL(18,6) NOT NULL DEFAULT 0,
            reactive DECIMAL(18,6) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT GETDATE(),
            created_by VARCHAR(100) NULL
        );
        CREATE INDEX IX_MKO_PPT_CPSTATUS_FAIL_kode_periode ON dbo.MKO_PPT_CPSTATUS_FAIL(kode_periode);
    END;

    IF OBJECT_ID('dbo.MKO_PPT_PERBEDAAN_RESEP', 'U') IS NULL
    BEGIN
        CREATE TABLE dbo.MKO_PPT_PERBEDAAN_RESEP (
            id INT IDENTITY(1,1) PRIMARY KEY,
            kode_periode VARCHAR(50) NOT NULL,
            period_start DATETIME NULL,
            period_end DATETIME NULL,
            labeljual VARCHAR(255) NULL,
            colorname VARCHAR(255) NULL,
            cuscolor VARCHAR(255) NULL,
            colorcode VARCHAR(100) NULL,
            cp VARCHAR(50) NULL,
            status_cp VARCHAR(30) NULL,
            routing VARCHAR(255) NULL,
            disperse DECIMAL(18,6) NOT NULL DEFAULT 0,
            reactive DECIMAL(18,6) NOT NULL DEFAULT 0,
            grand_total DECIMAL(18,6) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT GETDATE(),
            created_by VARCHAR(100) NULL
        );
        CREATE INDEX IX_MKO_PPT_PERBEDAAN_RESEP_kode_periode ON dbo.MKO_PPT_PERBEDAAN_RESEP(kode_periode);
    END;
    ";

    sqlsrvExecOrFail($conn, $sql);
}

function generateUniquePeriodCode($conn, $startDate, $endDate)
{
    $base = 'periode_' . date('Ymd', strtotime($startDate)) . '_' . date('Ymd', strtotime($endDate));
    $sql = "
        SELECT ISNULL(MAX(CAST(RIGHT(kode_periode, 2) AS INT)), 0) AS max_seq
        FROM dbo.mko_rawdata
        WHERE kode_periode LIKE ?
    ";
    $stmt = sqlsrvExecOrFail($conn, $sql, [$base . '_%']);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $next = (int) ($row['max_seq'] ?? 0) + 1;

    return $base . '_' . str_pad((string) $next, 2, '0', STR_PAD_LEFT);
}

function getRecentPeriods($conn, $limit = 10)
{
    $limit = max(1, (int) $limit);
    $sql = "
        SELECT TOP ({$limit})
            r.kode_periode,
            MIN(r.period_start) AS period_start,
            MAX(r.period_end) AS period_end,
            COUNT(*) AS raw_count,
            (
                SELECT COUNT(*)
                FROM dbo.MKO_materialobat m
                WHERE m.kode_periode = r.kode_periode
            ) AS material_count,
            (
                SELECT COUNT(*)
                FROM dbo.MKO_rekap rk
                WHERE rk.kode_periode = r.kode_periode
            ) AS rekap_count,
            (
                SELECT COUNT(*)
                FROM dbo.MKO_PPT_CPSTATUS_FAIL pf
                WHERE pf.kode_periode = r.kode_periode
            ) AS ppt_fail_count,
            (
                SELECT COUNT(*)
                FROM dbo.MKO_PPT_PERBEDAAN_RESEP pr
                WHERE pr.kode_periode = r.kode_periode
            ) AS ppt_beda_resep_count,
            MAX(r.created_at) AS created_at
        FROM dbo.mko_rawdata r
        GROUP BY r.kode_periode
        ORDER BY MAX(r.created_at) DESC, r.kode_periode DESC
    ";

    $stmt = sqlsrvExecOrFail($conn, $sql);
    return sqlsrvAll($stmt);
}

function getRequestUser()
{
    return $_SESSION['UserName'] ?? 'system';
}

function toNullableString($value)
{
    if ($value === null) {
        return null;
    }

    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function formatDisplayDate($value)
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('d/m/Y H:i');
    }

    $value = toNullableString($value);
    if ($value === null) {
        return '-';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    return date('d/m/Y H:i', $timestamp);
}

function roundDecimalValue($value, $scale = 4)
{
    if ($value === null || $value === '') {
        return null;
    }

    if (!is_numeric($value)) {
        return $value;
    }

    return round((float) $value, (int) $scale);
}

function formatDecimalValue($value, $scale = 2)
{
    if ($value === null || $value === '') {
        return '';
    }

    if (!is_numeric($value)) {
        return (string) $value;
    }

    return number_format((float) $value, (int) $scale, '.', '');
}

function getEligibleKelompokMap($conn)
{
    $sql = "
        SELECT
            LTRIM(RTRIM(CAST(product_code AS VARCHAR(100)))) AS product_code,
            LTRIM(RTRIM(CAST(kelompok AS VARCHAR(100)))) AS kelompok
        FROM dbo.mko_kelompok_obat
        WHERE
            ISNULL(LTRIM(RTRIM(CAST(product_code AS VARCHAR(100)))), '') <> ''
            AND ISNULL(LTRIM(RTRIM(CAST(kelompok AS VARCHAR(100)))), '') <> ''
            AND LTRIM(RTRIM(CAST(kelompok AS VARCHAR(100)))) <> 'Auxiliaries'
    ";

    $stmt = sqlsrvExecOrFail($conn, $sql);
    $rows = sqlsrvAll($stmt);
    $map = [];

    foreach ($rows as $row) {
        $code = toNullableString($row['product_code'] ?? null);
        $kelompok = toNullableString($row['kelompok'] ?? null);
        if ($code === null || $kelompok === null) {
            continue;
        }
        $map[$code] = $kelompok;
    }

    return $map;
}

function getMaterialCountByKode($conn, $kodePeriode)
{
    $stmt = sqlsrvExecOrFail(
        $conn,
        "SELECT COUNT(*) AS total FROM dbo.MKO_materialobat WHERE kode_periode = ?",
        [$kodePeriode]
    );
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return (int) ($row['total'] ?? 0);
}

function getRekapCountByKode($conn, $kodePeriode)
{
    $stmt = sqlsrvExecOrFail(
        $conn,
        "SELECT COUNT(*) AS total FROM dbo.MKO_rekap WHERE kode_periode = ?",
        [$kodePeriode]
    );
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return (int) ($row['total'] ?? 0);
}

function getPptFailCountByKode($conn, $kodePeriode)
{
    $stmt = sqlsrvExecOrFail(
        $conn,
        "SELECT COUNT(*) AS total FROM dbo.MKO_PPT_CPSTATUS_FAIL WHERE kode_periode = ?",
        [$kodePeriode]
    );
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return (int) ($row['total'] ?? 0);
}

function getPptPerbedaanCountByKode($conn, $kodePeriode)
{
    $stmt = sqlsrvExecOrFail(
        $conn,
        "SELECT COUNT(*) AS total FROM dbo.MKO_PPT_PERBEDAAN_RESEP WHERE kode_periode = ?",
        [$kodePeriode]
    );
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return (int) ($row['total'] ?? 0);
}

function getRowsByKodePeriode($conn, $tableName, $kodePeriode, array $columns = ['*'], $orderBy = '')
{
    $safeTableMap = [
        'mko_rawdata' => 'dbo.mko_rawdata',
        'MKO_materialobat' => 'dbo.MKO_materialobat',
        'MKO_rekap' => 'dbo.MKO_rekap',
        'MKO_PPT_CPSTATUS_FAIL' => 'dbo.MKO_PPT_CPSTATUS_FAIL',
        'MKO_PPT_PERBEDAAN_RESEP' => 'dbo.MKO_PPT_PERBEDAAN_RESEP',
    ];

    if (!isset($safeTableMap[$tableName])) {
        throw new InvalidArgumentException('Table tidak diizinkan.');
    }

    $select = implode(', ', $columns);
    $sql = "SELECT {$select} FROM {$safeTableMap[$tableName]} WHERE kode_periode = ?";
    if ($orderBy !== '') {
        $sql .= " ORDER BY {$orderBy}";
    }

    $stmt = sqlsrvExecOrFail($conn, $sql, [$kodePeriode]);
    return sqlsrvAll($stmt);
}

function getMasterResepMap($conn)
{
    $stmt = sqlsrvExecOrFail(
        $conn,
        "SELECT DISTINCT LTRIM(RTRIM(CAST(kode_warna AS VARCHAR(100)))) AS kode_warna 
         FROM dbo.resep_obat 
         WHERE ISNULL(LTRIM(RTRIM(CAST(kode_warna AS VARCHAR(100)))), '') <> ''
           AND LTRIM(RTRIM(CAST(status_resep_lipat AS VARCHAR(100)))) = 'Master Resep'"
    );
    $rows = sqlsrvAll($stmt);
    $map = [];
    foreach ($rows as $row) {
        $kode = toNullableString($row['kode_warna'] ?? null);
        if ($kode !== null) {
            $map[$kode] = true;
        }
    }
    return $map;
}

function getRawdataSummaryByKode($conn, $kodePeriode)
{
    $stmt = sqlsrvExecOrFail(
        $conn,
        "SELECT prdnmbr, fgresult, fgstatus, enddate FROM dbo.mko_rawdata WHERE kode_periode = ? AND ISNULL(prdnmbr, '') <> '' ORDER BY prdnmbr, enddate DESC, id DESC",
        [$kodePeriode]
    );
    $rows = sqlsrvAll($stmt);
    $grouped = [];

    foreach ($rows as $row) {
        $prdnmbr = toNullableString($row['prdnmbr'] ?? null);
        if ($prdnmbr === null) {
            continue;
        }

        if (!isset($grouped[$prdnmbr])) {
            $grouped[$prdnmbr] = [
                'fgresults' => [],
                'latest_fgstatus' => null,
            ];
        }

        if ($grouped[$prdnmbr]['latest_fgstatus'] === null) {
            $grouped[$prdnmbr]['latest_fgstatus'] = toNullableString($row['fgstatus'] ?? null);
        }

        $fgresult = strtoupper((string) toNullableString($row['fgresult'] ?? null));
        if ($fgresult !== '') {
            $grouped[$prdnmbr]['fgresults'][] = $fgresult;
        }
    }

    foreach ($grouped as $prdnmbr => $data) {
        $grouped[$prdnmbr]['status_proses_acc'] = buildStatusProsesAcc($data['fgresults']);
        $grouped[$prdnmbr]['status_cp'] = mapFgStatusToCp($data['latest_fgstatus']);
    }

    return $grouped;
}

function buildStatusProsesAcc(array $fgresults)
{
    if (!$fgresults) {
        return null;
    }

    $picked = array_values(array_unique(array_filter(array_map(static function ($value) {
        $value = strtoupper(trim((string) $value));
        return $value === '' ? null : $value;
    }, $fgresults))));

    if (!$picked) {
        return null;
    }

    $labels = array_map(static function ($value) {
        $value = strtoupper(trim((string) $value));
        if ($value === 'P') {
            return 'Pass';
        }
        if ($value === 'F') {
            return 'Fail';
        }
        return $value;
    }, $picked);

    return implode(' & ', $labels);
}

function mapFgStatusToCp($fgstatus)
{
    $fgstatus = strtoupper((string) toNullableString($fgstatus));
    if ($fgstatus === 'F') {
        return 'Fail';
    }
    if ($fgstatus === 'X') {
        return 'Close';
    }
    if ($fgstatus === 'O') {
        return 'Open';
    }
    if ($fgstatus === 'U') {
        return 'Outstanding';
    }
    if ($fgstatus === 'V') {
        return 'Approve';
    }
    return $fgstatus === '' ? null : $fgstatus;
}

function normalizeStatusWords($value)
{
    $value = strtoupper(trim((string) $value));
    return preg_replace('/\s+/', ' ', $value);
}

function statusProsesEndsWithFail($value)
{
    $value = normalizeStatusWords($value);
    if ($value === '') {
        return false;
    }

    return substr($value, -4) === 'FAIL';
}

function routingContainsPaddrY($value)
{
    $value = strtoupper((string) toNullableString($value));
    return $value !== '' && strpos($value, 'PADDRY') !== false;
}
