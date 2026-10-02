<?php
declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

function mm_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $server = $GLOBALS['serverName'] ?? '';
    $opts = $GLOBALS['connectionOptions'] ?? [];
    $db = $opts['Database'] ?? '';
    $uid = $opts['Uid'] ?? '';
    $pwd = $opts['PWD'] ?? '';

    $dsn = "sqlsrv:Server={$server};Database={$db}";
    if (!empty($opts['TrustServerCertificate'])) {
        $dsn .= ';TrustServerCertificate=1';
    }

    $pdo = new PDO($dsn, $uid, $pwd, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function mm_sheet_configs(): array
{
    return [
        'Data_AB' => [
            'date_key' => 'TIME',
            'table' => 'dbo.MonitoringMesinDataAb',
            'upload_header_column' => 'DataAbHeadersJson',
        ],
        'Alarm_AB' => [
            'date_key' => 'Date',
            'table' => 'dbo.MonitoringMesinAlarmAb',
            'upload_header_column' => 'AlarmAbHeadersJson',
        ],
        'ErrorLog' => [
            'date_key' => 'Date',
            'table' => 'dbo.MonitoringMesinErrorLog',
            'upload_header_column' => 'ErrorLogHeadersJson',
            'expected_headers' => ['Kind', 'Date', 'Cell Area Name', 'ErrorNo', 'Contents'],
        ],
    ];
}

function mm_ensure_tables(PDO $pdo): void
{
    $sqlList = [
        "
        IF OBJECT_ID('dbo.MonitoringMesinUploads', 'U') IS NULL
        BEGIN
            CREATE TABLE dbo.MonitoringMesinUploads (
                UploadId INT IDENTITY(1,1) PRIMARY KEY,
                UniqueCode NVARCHAR(255) NOT NULL,
                OriginalFileName NVARCHAR(255) NOT NULL,
                StoredFilePath NVARCHAR(500) NULL,
                DataAbHeadersJson NVARCHAR(MAX) NULL,
                AlarmAbHeadersJson NVARCHAR(MAX) NULL,
                ErrorLogHeadersJson NVARCHAR(MAX) NULL,
                DataAbCount INT NOT NULL DEFAULT 0,
                AlarmAbCount INT NOT NULL DEFAULT 0,
                ErrorLogCount INT NOT NULL DEFAULT 0,
                UploadedBy NVARCHAR(150) NULL,
                UploadedAt DATETIME NOT NULL DEFAULT GETDATE()
            );
        END
        ",
        "
        IF NOT EXISTS (
            SELECT 1
            FROM sys.indexes
            WHERE name = 'UQ_MonitoringMesinUploads_UniqueCode'
              AND object_id = OBJECT_ID('dbo.MonitoringMesinUploads')
        )
        BEGIN
            CREATE UNIQUE INDEX UQ_MonitoringMesinUploads_UniqueCode
            ON dbo.MonitoringMesinUploads (UniqueCode);
        END
        ",
        "
        IF OBJECT_ID('dbo.MonitoringMesinDataAb', 'U') IS NULL
        BEGIN
            CREATE TABLE dbo.MonitoringMesinDataAb (
                DataAbId BIGINT IDENTITY(1,1) PRIMARY KEY,
                UploadId INT NOT NULL,
                UniqueCode NVARCHAR(255) NOT NULL,
                RowNumber INT NOT NULL,
                RecordDate DATETIME NULL,
                RecordDateText NVARCHAR(100) NULL,
                RowJson NVARCHAR(MAX) NOT NULL,
                CreatedAt DATETIME NOT NULL DEFAULT GETDATE()
            );
        END
        ",
        "
        IF OBJECT_ID('dbo.MonitoringMesinAlarmAb', 'U') IS NULL
        BEGIN
            CREATE TABLE dbo.MonitoringMesinAlarmAb (
                AlarmAbId BIGINT IDENTITY(1,1) PRIMARY KEY,
                UploadId INT NOT NULL,
                UniqueCode NVARCHAR(255) NOT NULL,
                RowNumber INT NOT NULL,
                RecordDate DATETIME NULL,
                RecordDateText NVARCHAR(100) NULL,
                RowJson NVARCHAR(MAX) NOT NULL,
                CreatedAt DATETIME NOT NULL DEFAULT GETDATE()
            );
        END
        ",
        "
        IF OBJECT_ID('dbo.MonitoringMesinErrorLog', 'U') IS NULL
        BEGIN
            CREATE TABLE dbo.MonitoringMesinErrorLog (
                ErrorLogId BIGINT IDENTITY(1,1) PRIMARY KEY,
                UploadId INT NOT NULL,
                UniqueCode NVARCHAR(255) NOT NULL,
                RowNumber INT NOT NULL,
                RecordDate DATETIME NULL,
                RecordDateText NVARCHAR(100) NULL,
                RowJson NVARCHAR(MAX) NOT NULL,
                CreatedAt DATETIME NOT NULL DEFAULT GETDATE()
            );
        END
        ",
    ];

    foreach ($sqlList as $sql) {
        $pdo->exec($sql);
    }
}

function mm_normalize_header(string $header): string
{
    $header = str_replace(["\r", "\n", "\t"], ' ', $header);
    $header = trim($header, " \t\n\r\0\x0B\"'");
    $header = preg_replace('/\s+/u', ' ', $header ?? '');
    return trim((string) $header);
}

function mm_make_unique_headers(array $headers): array
{
    $counts = [];
    $unique = [];

    foreach ($headers as $header) {
        $header = mm_normalize_header((string) $header);
        if ($header === '') {
            $header = 'Column';
        }

        $counts[$header] = ($counts[$header] ?? 0) + 1;
        $suffix = $counts[$header] > 1 ? ' #' . $counts[$header] : '';
        $unique[] = $header . $suffix;
    }

    return $unique;
}

function mm_is_row_empty(array $row): bool
{
    foreach ($row as $value) {
        if (trim((string) $value) !== '') {
            return false;
        }
    }
    return true;
}

function mm_find_header_row(array $rows): int
{
    foreach ($rows as $index => $row) {
        $filled = 0;
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                $filled++;
            }
        }
        if ($filled >= 2) {
            return $index;
        }
    }

    return 0;
}

function mm_find_header_row_by_expected(array $rows, array $expectedHeaders): int
{
    $expectedMap = array_map(
        static fn(string $header): string => strtolower(mm_normalize_header($header)),
        $expectedHeaders
    );

    $bestIndex = 0;
    $bestScore = -1;

    foreach ($rows as $index => $row) {
        $score = 0;
        foreach ($row as $value) {
            $normalized = strtolower(mm_normalize_header((string) $value));
            if ($normalized !== '' && in_array($normalized, $expectedMap, true)) {
                $score++;
            }
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestIndex = $index;
        }
    }

    return $bestIndex;
}

function mm_map_expected_header_indexes(array $headerRow, array $expectedHeaders): array
{
    $normalizedRow = [];
    foreach ($headerRow as $idx => $value) {
        $normalizedRow[$idx] = strtolower(mm_normalize_header((string) $value));
    }

    $indexes = [];
    foreach ($expectedHeaders as $position => $header) {
        $expected = strtolower(mm_normalize_header($header));
        $foundIndex = array_search($expected, $normalizedRow, true);
        $indexes[$header] = $foundIndex !== false ? (int) $foundIndex : $position;
    }

    return $indexes;
}

function mm_format_scalar_value(mixed $value): string
{
    if ($value === null) {
        return '';
    }

    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d H:i:s');
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    if (is_float($value)) {
        $formatted = rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');
        return $formatted === '' ? '0' : $formatted;
    }

    return trim((string) $value);
}

function mm_parse_excel_date_value(mixed $value): ?DateTimeImmutable
{
    if ($value instanceof DateTimeInterface) {
        return DateTimeImmutable::createFromInterface($value);
    }

    if (is_numeric($value) && (float) $value > 0) {
        try {
            return DateTimeImmutable::createFromMutable(ExcelDate::excelToDateTimeObject((float) $value));
        } catch (Throwable $e) {
        }
    }

    $text = trim((string) $value);
    if ($text === '') {
        return null;
    }

    $formats = [
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y-m-d',
        'd/m/Y H:i:s',
        'd/m/Y H:i',
        'd/m/Y',
        'd-m-Y H:i:s',
        'd-m-Y H:i',
        'd-m-Y',
        'm/d/Y H:i:s',
        'm/d/Y H:i',
        'm/d/Y',
    ];

    foreach ($formats as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $text);
        if ($dt instanceof DateTimeImmutable) {
            return $dt;
        }
    }

    try {
        return new DateTimeImmutable($text);
    } catch (Throwable $e) {
        return null;
    }
}

function mm_read_sheet_rows(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
{
    $highestRow = $sheet->getHighestDataRow();
    $highestColumn = $sheet->getHighestDataColumn();
    $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
    $rows = [];

    for ($row = 1; $row <= $highestRow; $row++) {
        $rowValues = [];
        for ($column = 1; $column <= $highestColumnIndex; $column++) {
            $cellAddress = Coordinate::stringFromColumnIndex($column) . $row;
            $cell = $sheet->getCell($cellAddress);
            $rowValues[] = mm_format_scalar_value($cell->getCalculatedValue());
        }
        $rows[] = $rowValues;
    }

    return $rows;
}

function mm_normalize_sheet_name(string $sheetName): string
{
    return strtolower(trim(preg_replace('/\s+/u', ' ', $sheetName) ?? ''));
}

function mm_find_sheet_by_name(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, string $expectedName): ?\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
{
    $target = mm_normalize_sheet_name($expectedName);

    foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
        if (mm_normalize_sheet_name($sheet->getTitle()) === $target) {
            return $sheet;
        }
    }

    return null;
}

function mm_parse_workbook(string $filePath): array
{
    $spreadsheet = IOFactory::load($filePath);
    $parsed = [];

    foreach (mm_sheet_configs() as $sheetName => $config) {
        $sheet = mm_find_sheet_by_name($spreadsheet, $sheetName);
        if (!$sheet) {
            throw new RuntimeException("Sheet {$sheetName} tidak ditemukan di file Excel.");
        }

        $rows = mm_read_sheet_rows($sheet);
        if ($rows === []) {
            $parsed[$sheetName] = ['headers' => [], 'rows' => []];
            continue;
        }

        $expectedHeaders = $config['expected_headers'] ?? null;
        if (is_array($expectedHeaders) && $expectedHeaders !== []) {
            $headerIndex = mm_find_header_row_by_expected($rows, $expectedHeaders);
            $headers = $expectedHeaders;
            $headerIndexes = mm_map_expected_header_indexes($rows[$headerIndex] ?? [], $expectedHeaders);
            $normalizedMap = [];
            foreach ($headers as $header) {
                $normalizedMap[mm_normalize_header($header)] = $headerIndexes[$header];
            }
        } else {
            $headerIndex = mm_find_header_row($rows);
            $headers = mm_make_unique_headers($rows[$headerIndex] ?? []);
            $normalizedMap = [];
            foreach ($headers as $idx => $header) {
                $normalizedMap[mm_normalize_header($header)] = $idx;
            }
        }

        $dateIndex = $normalizedMap[mm_normalize_header($config['date_key'])] ?? null;
        $dataRows = [];

        for ($i = $headerIndex + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (mm_is_row_empty($row)) {
                continue;
            }

            $assoc = [];
            foreach ($headers as $idx => $header) {
                $sourceIndex = $normalizedMap[mm_normalize_header($header)] ?? $idx;
                $assoc[$header] = mm_format_scalar_value($row[$sourceIndex] ?? '');
            }

            $rawDateValue = $dateIndex !== null ? ($row[$dateIndex] ?? null) : null;
            $parsedDate = mm_parse_excel_date_value($rawDateValue);
            $assoc['__record_date'] = $parsedDate?->format('Y-m-d H:i:s');
            $assoc['__record_date_text'] = mm_format_scalar_value($rawDateValue);

            $dataRows[] = $assoc;
        }

        $parsed[$sheetName] = [
            'headers' => $headers,
            'rows' => $dataRows,
        ];
    }

    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);

    return $parsed;
}

function mm_store_upload(PDO $pdo, array $parsedWorkbook, string $uniqueCode, string $originalFileName, string $storedPath, string $uploadedBy): void
{
    $configs = mm_sheet_configs();

    $checkStmt = $pdo->prepare("SELECT COUNT(1) FROM dbo.MonitoringMesinUploads WHERE UniqueCode = ?");
    $checkStmt->execute([$uniqueCode]);
    if ((int) $checkStmt->fetchColumn() > 0) {
        throw new RuntimeException("File dengan kode unik {$uniqueCode} sudah pernah di-upload.");
    }

    $pdo->beginTransaction();

    try {
        $insertUpload = $pdo->prepare("
            INSERT INTO dbo.MonitoringMesinUploads (
                UniqueCode,
                OriginalFileName,
                StoredFilePath,
                DataAbHeadersJson,
                AlarmAbHeadersJson,
                ErrorLogHeadersJson,
                DataAbCount,
                AlarmAbCount,
                ErrorLogCount,
                UploadedBy
            )
            OUTPUT INSERTED.UploadId
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $uploadId = $insertUpload->execute([
            $uniqueCode,
            $originalFileName,
            $storedPath,
            json_encode($parsedWorkbook['Data_AB']['headers'], JSON_UNESCAPED_UNICODE),
            json_encode($parsedWorkbook['Alarm_AB']['headers'], JSON_UNESCAPED_UNICODE),
            json_encode($parsedWorkbook['ErrorLog']['headers'], JSON_UNESCAPED_UNICODE),
            count($parsedWorkbook['Data_AB']['rows']),
            count($parsedWorkbook['Alarm_AB']['rows']),
            count($parsedWorkbook['ErrorLog']['rows']),
            $uploadedBy,
        ]) ? (int) $insertUpload->fetchColumn() : 0;

        if ($uploadId <= 0) {
            throw new RuntimeException('Gagal menyimpan header upload.');
        }

        foreach ($configs as $sheetName => $config) {
            $table = $config['table'];
            $rows = $parsedWorkbook[$sheetName]['rows'] ?? [];
            if ($rows === []) {
                continue;
            }

            $insertRow = $pdo->prepare("
                INSERT INTO {$table} (
                    UploadId,
                    UniqueCode,
                    RowNumber,
                    RecordDate,
                    RecordDateText,
                    RowJson
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            foreach ($rows as $idx => $row) {
                $recordDate = $row['__record_date'] ?? null;
                $recordDateText = $row['__record_date_text'] ?? null;
                unset($row['__record_date'], $row['__record_date_text']);

                $insertRow->execute([
                    $uploadId,
                    $uniqueCode,
                    $idx + 1,
                    $recordDate !== '' ? $recordDate : null,
                    $recordDateText !== '' ? $recordDateText : null,
                    json_encode($row, JSON_UNESCAPED_UNICODE),
                ]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function mm_flash(string $type, string $message): void
{
    $_SESSION['mm_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function mm_take_flash(): ?array
{
    if (!isset($_SESSION['mm_flash'])) {
        return null;
    }

    $flash = $_SESSION['mm_flash'];
    unset($_SESSION['mm_flash']);
    return $flash;
}

function mm_allowed_pbr_upload_user_ids(): array
{
    return mm_allowed_upload_user_ids('pbr');
}

function mm_can_access_pbr_upload(string $userId): bool
{
    return mm_can_access_upload_area('pbr', $userId);
}

function mm_build_date_filter_sql(string $column, array &$params, ?string $startDate, ?string $endDate): string
{
    $clauses = [];

    if ($startDate) {
        $clauses[] = "{$column} >= ?";
        $params[] = $startDate;
    }
    if ($endDate) {
        $clauses[] = "{$column} <= ?";
        $params[] = $endDate;
    }

    return $clauses ? ' AND ' . implode(' AND ', $clauses) : '';
}

function mm_get_upload_list(PDO $pdo, ?string $uniqueCodeFilter = null): array
{
    $sql = "
        SELECT TOP 50
            UploadId,
            UniqueCode,
            OriginalFileName,
            DataAbCount,
            AlarmAbCount,
            ErrorLogCount,
            UploadedBy,
            CONVERT(VARCHAR(19), UploadedAt, 120) AS UploadedAt
        FROM dbo.MonitoringMesinUploads
        WHERE 1 = 1
    ";

    $params = [];
    if ($uniqueCodeFilter) {
        $sql .= " AND UniqueCode LIKE ?";
        $params[] = '%' . $uniqueCodeFilter . '%';
    }

    $sql .= " ORDER BY UploadId DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function mm_get_headers_for_sheet(PDO $pdo, string $sheetName, ?string $uniqueCodeFilter = null): array
{
    $config = mm_sheet_configs()[$sheetName];
    $column = $config['upload_header_column'];
    $sql = "SELECT TOP 1 {$column} AS HeaderJson FROM dbo.MonitoringMesinUploads WHERE {$column} IS NOT NULL";
    $params = [];

    if ($uniqueCodeFilter) {
        $sql .= " AND UniqueCode LIKE ?";
        $params[] = '%' . $uniqueCodeFilter . '%';
    }

    $sql .= " ORDER BY UploadId DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $json = $stmt->fetchColumn();
    if (!$json) {
        return [];
    }

    $headers = json_decode((string) $json, true);
    return is_array($headers) ? $headers : [];
}

function mm_fetch_sheet_rows(PDO $pdo, string $sheetName, ?string $startDate, ?string $endDate, ?string $uniqueCodeFilter = null, ?int $limit = null): array
{
    $config = mm_sheet_configs()[$sheetName];
    $table = $config['table'];
    $topSql = $limit !== null ? 'TOP ' . max(1, $limit) : '';

    $sql = "
        SELECT {$topSql}
            UniqueCode,
            RowNumber,
            CONVERT(VARCHAR(19), RecordDate, 120) AS RecordDate,
            RecordDateText,
            RowJson
        FROM {$table}
        WHERE 1 = 1
    ";

    $params = [];
    if ($uniqueCodeFilter) {
        $sql .= " AND UniqueCode LIKE ?";
        $params[] = '%' . $uniqueCodeFilter . '%';
    }
    $sql .= mm_build_date_filter_sql('RecordDate', $params, $startDate, $endDate);
    $sql .= " ORDER BY RecordDate ASC, RowNumber ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $decoded = json_decode((string) $row['RowJson'], true);
        $row['RowData'] = is_array($decoded) ? $decoded : [];
    }

    return $rows;
}

function mm_get_sheet_rows(PDO $pdo, string $sheetName, ?string $startDate, ?string $endDate, ?string $uniqueCodeFilter = null): array
{
    return mm_fetch_sheet_rows($pdo, $sheetName, $startDate, $endDate, $uniqueCodeFilter, 200);
}

function mm_count_sheet_rows(PDO $pdo, string $sheetName, ?string $startDate, ?string $endDate, ?string $uniqueCodeFilter = null): int
{
    $config = mm_sheet_configs()[$sheetName];
    $table = $config['table'];
    $sql = "SELECT COUNT(1) FROM {$table} WHERE 1 = 1";
    $params = [];

    if ($uniqueCodeFilter) {
        $sql .= " AND UniqueCode LIKE ?";
        $params[] = '%' . $uniqueCodeFilter . '%';
    }

    $sql .= mm_build_date_filter_sql('RecordDate', $params, $startDate, $endDate);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function mm_get_sheet_rows_page(PDO $pdo, string $sheetName, ?string $startDate, ?string $endDate, ?string $uniqueCodeFilter = null, int $page = 1, int $perPage = 15): array
{
    $offset = (max(1, $page) - 1) * max(1, $perPage);
    return mm_get_sheet_rows_slice($pdo, $sheetName, $startDate, $endDate, $uniqueCodeFilter, $offset, $perPage);
}

function mm_get_sheet_rows_slice(PDO $pdo, string $sheetName, ?string $startDate, ?string $endDate, ?string $uniqueCodeFilter = null, int $offset = 0, int $limit = 15): array
{
    $config = mm_sheet_configs()[$sheetName];
    $table = $config['table'];
    $offset = max(0, $offset);
    $limit = max(1, $limit);

    $sql = "
        SELECT
            UniqueCode,
            RowNumber,
            CONVERT(VARCHAR(19), RecordDate, 120) AS RecordDate,
            RecordDateText,
            RowJson
        FROM {$table}
        WHERE 1 = 1
    ";

    $params = [];
    if ($uniqueCodeFilter) {
        $sql .= " AND UniqueCode LIKE ?";
        $params[] = '%' . $uniqueCodeFilter . '%';
    }

    $sql .= mm_build_date_filter_sql('RecordDate', $params, $startDate, $endDate);
    $sql .= " ORDER BY RecordDate ASC, RowNumber ASC OFFSET {$offset} ROWS FETCH NEXT {$limit} ROWS ONLY";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $decoded = json_decode((string) $row['RowJson'], true);
        $row['RowData'] = is_array($decoded) ? $decoded : [];
    }

    return $rows;
}

function mm_get_sheet_rows_for_export(PDO $pdo, string $sheetName, ?string $startDate, ?string $endDate, ?string $uniqueCodeFilter = null): array
{
    return mm_fetch_sheet_rows($pdo, $sheetName, $startDate, $endDate, $uniqueCodeFilter, null);
}

function mm_collect_export_headers(array $rows): array
{
    $headers = [];
    foreach ($rows as $row) {
        $rowData = $row['RowData'] ?? [];
        if (!is_array($rowData)) {
            continue;
        }

        foreach (array_keys($rowData) as $key) {
            if (!in_array($key, $headers, true)) {
                $headers[] = (string) $key;
            }
        }
    }

    return $headers;
}

function mm_normalize_filter_datetime(?string $value, bool $isEnd = false): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $formats = [
        'Y-m-d\TH:i:s',
        'Y-m-d\TH:i',
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y-m-d',
    ];

    foreach ($formats as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $value);
        if ($dt instanceof DateTimeImmutable) {
            if ($format === 'Y-m-d') {
                $dt = $isEnd ? $dt->setTime(23, 59, 59) : $dt->setTime(0, 0, 0);
            } elseif (in_array($format, ['Y-m-d\TH:i', 'Y-m-d H:i'], true)) {
                $dt = $dt->setTime((int) $dt->format('H'), (int) $dt->format('i'), $isEnd ? 59 : 0);
            }
            return $dt->format('Y-m-d H:i:s');
        }
    }

    try {
        return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return null;
    }
}

function mm_format_datetime_local_value(?string $value): string
{
    $normalized = mm_normalize_filter_datetime($value);
    if ($normalized === null) {
        return '';
    }

    try {
        return (new DateTimeImmutable($normalized))->format('Y-m-d\TH:i:s');
    } catch (Throwable $e) {
        return '';
    }
}

function mm_format_export_datetime(mixed $value): string
{
    $parsed = mm_parse_excel_date_value($value);
    if ($parsed instanceof DateTimeImmutable) {
        return $parsed->format('n/j/Y H:i');
    }

    $text = trim((string) $value);
    return $text;
}

function mm_format_preview_datetime(mixed $value): string
{
    $formatted = mm_format_export_datetime($value);
    return $formatted !== '' ? $formatted : '-';
}

function mm_delete_by_unique_code(PDO $pdo, string $uniqueCode): array
{
    $uniqueCode = trim($uniqueCode);
    if ($uniqueCode === '') {
        throw new RuntimeException('Kode unik wajib diisi.');
    }

    $stmt = $pdo->prepare("
        SELECT UploadId, StoredFilePath
        FROM dbo.MonitoringMesinUploads
        WHERE UniqueCode = ?
    ");
    $stmt->execute([$uniqueCode]);
    $uploads = $stmt->fetchAll();

    if ($uploads === []) {
        throw new RuntimeException("Kode unik {$uniqueCode} tidak ditemukan.");
    }

    $uploadIds = array_map(static fn(array $row): int => (int) $row['UploadId'], $uploads);
    $placeholders = implode(',', array_fill(0, count($uploadIds), '?'));

    $pdo->beginTransaction();
    try {
        foreach (mm_sheet_configs() as $config) {
            $deleteStmt = $pdo->prepare("DELETE FROM {$config['table']} WHERE UploadId IN ({$placeholders})");
            $deleteStmt->execute($uploadIds);
        }

        $deleteUploadStmt = $pdo->prepare("DELETE FROM dbo.MonitoringMesinUploads WHERE UploadId IN ({$placeholders})");
        $deleteUploadStmt->execute($uploadIds);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $deletedFiles = 0;
    foreach ($uploads as $upload) {
        $storedFilePath = trim((string) ($upload['StoredFilePath'] ?? ''));
        if ($storedFilePath === '') {
            continue;
        }

        $relativePath = preg_replace('#^/gg_app/#', '', str_replace('\\', '/', $storedFilePath));
        $absolutePath = dirname(__DIR__, 2) . '/' . ltrim((string) $relativePath, '/');
        if (is_file($absolutePath) && @unlink($absolutePath)) {
            $deletedFiles++;
        }
    }

    return [
        'upload_count' => count($uploads),
        'deleted_files' => $deletedFiles,
    ];
}

function mm_format_header_display(string $header): string
{
    $normalized = mm_normalize_header($header);
    if ($normalized === '') {
        return '';
    }

    $map = [
        '[a] production speed [0.1m/min]' => "[A]\nProduction speed\n[0.1m/min]",
        '[b] production speed [0.1m/min]' => "[B]\nProduction speed\n[0.1m/min]",
        'conveyor speed [0.1m/min]' => "Conveyor speed\n[0.1m/min]",
        'cloth volume [m]' => "Cloth volume\n[m]",
        'front chamber blower[hz]' => "Front Chamber\nBlower[Hz]",
        'rear chamber blower[hz]' => "Rear Chamber\nBlower[Hz]",
        '[a] no.1washer [0.1?]' => "[A]\nNo.1Washer\n[0.1?]",
        '[a] no.2washer [0.1?]' => "[A]\nNo.2Washer\n[0.1?]",
        '[a] no.3washer [0.1?]' => "[A]\nNo.3Washer\n[0.1?]",
        '[a] no.4washer [0.1?]' => "[A]\nNo.4Washer\n[0.1?]",
        '[a] no.5washer [0.1?]' => "[A]\nNo.5Washer\n[0.1?]",
        '[a] no.6washer [0.1?]' => "[A]\nNo.6Washer\n[0.1?]",
        'top part [0.1?]' => "Top part\n[0.1?]",
        'boiling box [0.1?]' => "Boiling box\n[0.1?]",
        '[b] no.1washer [0.1?]' => "[B]No.1Washer[0.1?]",
        '[b] no.2washer [0.1?]' => "[B]\nNo.2Washer\n[0.1?]",
        '[b] no.3washer [0.1?]' => "[B]\nNo.3Washer\n[0.1?]",
        '[b] no.4washer [0.1?]' => "[B]\nNo.4Washer\n[0.1?]",
        '[b] no.5washer [0.1?]' => "[B]\nNo.5Washer\n[0.1?]",
        '[b] no.6washer [0.1?]' => "[B]\nNo.6Washer\n[0.1?]",
        'hot water tank [0.1?]' => "Hot Water\nTank\n[0.1?]",
        'no.3 dryer valve output [%]' => "No.3 Dryer\nValve Output\n[%]",
        '[b] no.4 dryer [0.1?]' => "[B]\nNo.4 Dryer\n[0.1?]",
        '[front] main water [0.1m3/h]' => "[Front]\nMain Water\n[0.1m3/h]",
        '[front] main steam [kg/h]' => "[Front]\nMain Steam\n[kg/h]",
        '[back] main water [0.1m3/h]' => "[Back]\nMain Water\n[0.1m3/h]",
        '[back] main steam [kg/h]' => "[Back]\nMain Steam\n[kg/h]",
        'watt meter [kw]' => "Watt meter\n[kW]",
        'gas singeing running meter [hour]' => "Gas Singeing\nRunning Meter\n[Hour]",
        'production counter [m]' => "Production\nCounter\n[m]",
    ];

    $lookupKey = strtolower($normalized);
    if (isset($map[$lookupKey])) {
        return $map[$lookupKey];
    }

    $prefix = '';
    $body = $normalized;
    if (preg_match('/^(\[[^\]]+\])\s*(.+)$/u', $body, $matches) === 1) {
        $prefix = $matches[1];
        $body = trim($matches[2]);
    }

    $unit = '';
    if (preg_match('/^(.*?)(\[[^\]]+\])$/u', $body, $matches) === 1) {
        $body = trim($matches[1]);
        $unit = trim($matches[2]);
    }

    $lines = [];
    if ($prefix !== '') {
        $lines[] = $prefix;
    }

    if ($body !== '') {
        $lines[] = $body;
    }

    if ($unit !== '') {
        $lines[] = $unit;
    }

    return implode("\n", $lines);
}



