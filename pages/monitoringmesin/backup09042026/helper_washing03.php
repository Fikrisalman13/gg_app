<?php
declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

function ws03_pdo(): PDO
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

function ws03_expected_headers(): array
{
    return ws03_expected_sheet_definitions()['Data_WD2']['expected_headers'];
}

function ws03_expected_sheet_definitions(): array
{
    return [
        'Data_WD2' => [
            'required' => true,
            'headers_mode' => 'expected',
            'expected_headers' => [
                "Production\nCounter\n[m]",
                "Production\nSpeed\n[0.1m/min]",
                "Production\nCounter\n[m]",
                "No.3 Washer\nACETIC SCID+H2O2\n[0.1L/min]",
                "No.3 Washer\nSOAPING-1\n[0.1L/min]",
                "No.4 Washer\nACETIC SCID+H2O2\n[0.1L/min]",
                "No.4 Washer\nSOAPING-2\n[0.1L/min]",
                "No.6 Washer\nSOAPING-1\n[0.1L/min]",
                "No.7 Washer\nSOAPING-2\n[0.1L/min]",
                "No.11 Washer\nFIX\n[0.1L/min]",
                "No.1 Washer\nWater Supply\n[0.1L/min]",
                "No.2 Washer\nWater Supply\n[0.1L/min]",
                "No.5 Washer\nWater Supply\n[0.1L/min]",
                "No.6-7 Washer\nWater Supply\n[0.1L/min]",
                "No.9-10 Washer\nWater Supply\n[0.1L/min]",
                "Cooling Cylinder\nWater Supply\n[0.1L/min]",
                "No.3 Dryer\nValve Output\n[0.1%]",
                "No.4 Dryer\n[0.1C]",
                "No.1 Washer\n[0.1C]",
                "No.2 Washer\n[0.1C]",
                "No.3 Washer\n[0.1C]",
                "No.4 Washer\n[0.1C]",
                "No.5 Washer\n[0.1C]",
                "No.6 Washer\n[0.1C]",
                "No.7 Washer\n[0.1C]",
                "No.8 Washer\n[0.1C]",
                "No.9 Washer\n[0.1C]",
                "No.10 Washer\n[0.1C]",
                "Hot Water Tank\n[0.1C]",
                "No.2 Washer\n[0.1pH]",
                "No.10 Washer\n[0.1pH]",
                "Batch off\nTENTION\n[N]",
                "No.1 Washer\nTENTION\n[N]",
                "No.2 Washer\nTENTION\n[N]",
                "No.3 Washer\nTENTION\n[N]",
                "No.4 Washer\nTENTION\n[N]",
                "No.5 Washer\nTENTION\n[N]",
                "No.6 Washer\nTENTION\n[N]",
                "No.7 Washer\nTENTION\n[N]",
                "No.8 Washer\nTENTION\n[N]",
                "No.9 Washer\nTENTION\n[N]",
                "No.11 Washer\nTENTION\n[N]",
                "Feed Roll\nTENTION\n[N]",
                "No.1 Dryer\nTENTION\n[N]",
                "No.2 Dryer\nTENTION\n[N]",
                "No.3 Dryer\nTENTION\n[N]",
                "No.4 Dryer\nTENTION\n[N]",
                "Main Water\nFlow\n[0.1m3/h]",
                "Main Steam\nFlow\n[kg/h]",
                "Watt Meter\n[kw]",
            ],
            'datetime_columns' => [1],
            'filter_header' => 'Production Counter [m]',
        ],
        'Alarm_WD2' => [
            'required' => false,
            'headers_mode' => 'actual',
            'expected_headers' => [
                'Date',
                "Alarm\nCode[1]",
                "Alarm\nCode[2]",
                'Alarm Comment',
            ],
            'datetime_columns' => [1],
            'filter_header' => 'Date',
        ],
        'ErrorLog' => [
            'required' => false,
            'headers_mode' => 'actual',
            'expected_headers' => [
                'Kind',
                'Date',
                'Cell Area Name',
                'ErrorNo',
                'Contents',
            ],
            'datetime_columns' => [2],
            'filter_header' => 'Date',
        ],
    ];
}

function ws03_ensure_tables(PDO $pdo): void
{
    $sqlList = [
        "IF OBJECT_ID('dbo.Washing03Uploads', 'U') IS NULL BEGIN CREATE TABLE dbo.Washing03Uploads (UploadId INT IDENTITY(1,1) PRIMARY KEY, UniqueCode NVARCHAR(255) NOT NULL, OriginalFileName NVARCHAR(255) NOT NULL, StoredFilePath NVARCHAR(500) NULL, HeaderJson NVARCHAR(MAX) NULL, TotalRows INT NOT NULL DEFAULT 0, UploadedBy NVARCHAR(150) NULL, UploadedAt DATETIME NOT NULL DEFAULT GETDATE()); END",
        "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UQ_Washing03Uploads_UniqueCode' AND object_id = OBJECT_ID('dbo.Washing03Uploads')) BEGIN CREATE UNIQUE INDEX UQ_Washing03Uploads_UniqueCode ON dbo.Washing03Uploads (UniqueCode); END",
        "IF OBJECT_ID('dbo.Washing03Data', 'U') IS NULL BEGIN CREATE TABLE dbo.Washing03Data (DataId BIGINT IDENTITY(1,1) PRIMARY KEY, UploadId INT NOT NULL, UniqueCode NVARCHAR(255) NOT NULL, SheetName NVARCHAR(100) NOT NULL DEFAULT 'Data_WD2', RowNumber INT NOT NULL, MeasuredAt DATETIME NULL, RowJson NVARCHAR(MAX) NOT NULL, CreatedAt DATETIME NOT NULL DEFAULT GETDATE()); END",
        "IF COL_LENGTH('dbo.Washing03Data', 'MeasuredAt') IS NULL BEGIN ALTER TABLE dbo.Washing03Data ADD MeasuredAt DATETIME NULL; END",
        "IF COL_LENGTH('dbo.Washing03Data', 'SheetName') IS NULL BEGIN ALTER TABLE dbo.Washing03Data ADD SheetName NVARCHAR(100) NOT NULL CONSTRAINT DF_Washing03Data_SheetName DEFAULT 'Data_WD2'; END",
        "UPDATE dbo.Washing03Data SET SheetName = 'Data_WD2' WHERE ISNULL(SheetName, '') = '';",
    ];

    foreach ($sqlList as $sql) {
        $pdo->exec($sql);
    }
}

function ws03_normalize_header(string $header): string
{
    $header = str_replace(["\r", "\n", "\t"], ' ', $header);
    $header = trim($header, " \t\n\r\0\x0B\"'");
    $header = preg_replace('/\s+/u', ' ', $header ?? '');
    return trim((string) $header);
}

function ws03_canonical_header(string $header): string
{
    $header = ws03_normalize_header($header);
    $replacements = [
        '?' => 'C',
        '°C' => 'C',
        'â„ƒ' => 'C',
        '%' => '%',
        'ï¼…' => '%',
        '“' => '',
        '”' => '',
    ];
    $header = strtr($header, $replacements);
    $header = strtolower($header);
    $header = preg_replace('/[^a-z0-9\[\]\.#\+\-\/ ]+/u', ' ', $header) ?? $header;
    $header = preg_replace('/\s+/u', ' ', $header) ?? $header;
    return trim($header);
}

function ws03_make_unique_headers(array $headers): array
{
    $counts = [];
    $unique = [];

    foreach ($headers as $header) {
        $normalized = ws03_normalize_header((string) $header);
        if ($normalized === '') {
            $normalized = 'Column';
        }

        $counts[$normalized] = ($counts[$normalized] ?? 0) + 1;
        $suffix = $counts[$normalized] > 1 ? ' #' . $counts[$normalized] : '';
        $unique[] = $normalized . $suffix;
    }

    return $unique;
}

function ws03_is_row_empty(array $row): bool
{
    foreach ($row as $value) {
        if (trim((string) $value) !== '') {
            return false;
        }
    }
    return true;
}

function ws03_find_header_row_by_expected(array $rows, array $expectedHeaders): int
{
    $expectedMap = array_map(static fn(string $header): string => ws03_canonical_header($header), $expectedHeaders);
    $bestIndex = 0;
    $bestScore = -1;

    foreach ($rows as $index => $row) {
        $score = 0;
        foreach ($row as $value) {
            $normalized = ws03_canonical_header((string) $value);
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

function ws03_map_expected_header_indexes(array $headerRow, array $expectedHeaders): array
{
    $uniqueHeaderRow = ws03_make_unique_headers($headerRow);
    $normalizedRow = [];
    foreach ($uniqueHeaderRow as $idx => $value) {
        $normalizedRow[$idx] = ws03_canonical_header($value);
    }

    $indexes = [];
    foreach (ws03_make_unique_headers($expectedHeaders) as $position => $header) {
        $expected = ws03_canonical_header($header);
        $foundIndex = array_search($expected, $normalizedRow, true);
        $indexes[$header] = $foundIndex !== false ? (int) $foundIndex : $position;
    }

    return $indexes;
}

function ws03_format_scalar_value(mixed $value): string
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

function ws03_format_excel_datetime_value(mixed $value): string
{
    if ($value === null) {
        return '';
    }

    try {
        if ($value instanceof DateTimeInterface) {
            return $value->format('n/j/Y g:i:s A');
        }

        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('n/j/Y g:i:s A');
        }

        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            return '';
        }

        return (new DateTimeImmutable($stringValue))->format('n/j/Y g:i:s A');
    } catch (Throwable $e) {
        return trim((string) $value);
    }
}

function ws03_extract_timestamp_from_value(string $rawValue): ?string
{
    $rawValue = trim($rawValue);
    if ($rawValue === '') {
        return null;
    }

    $formats = [
        'n/j/Y g:i:s A',
        'n/j/Y g:i A',
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        DateTimeInterface::ATOM,
    ];

    foreach ($formats as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $rawValue);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('Y-m-d H:i:s');
        }
    }

    try {
        return (new DateTimeImmutable($rawValue))->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return null;
    }
}

function ws03_extract_sheet_timestamp(string $sheetName, array $rowData): ?string
{
    $definitions = ws03_expected_sheet_definitions();
    $definition = $definitions[$sheetName] ?? null;
    $filterHeader = is_array($definition) ? (string) ($definition['filter_header'] ?? '') : '';
    if ($filterHeader === '') {
        return null;
    }

    foreach ($rowData as $header => $value) {
        if (ws03_canonical_header((string) $header) === ws03_canonical_header($filterHeader)) {
            return ws03_extract_timestamp_from_value((string) $value);
        }
    }

    return null;
}

function ws03_backfill_measured_at(PDO $pdo): void
{
    $stmt = $pdo->query("SELECT DataId, SheetName, RowJson FROM dbo.Washing03Data WHERE MeasuredAt IS NULL OR ISNULL(SheetName, '') = '' ORDER BY DataId ASC");
    $rows = $stmt->fetchAll() ?: [];
    if ($rows === []) {
        return;
    }

    $updateStmt = $pdo->prepare("UPDATE dbo.Washing03Data SET SheetName = ?, MeasuredAt = ? WHERE DataId = ?");
    foreach ($rows as $row) {
        $decoded = json_decode((string) ($row['RowJson'] ?? '{}'), true);
        if (!is_array($decoded)) {
            continue;
        }

        $sheetName = trim((string) ($row['SheetName'] ?? '')) ?: 'Data_WD2';
        $measuredAt = ws03_extract_sheet_timestamp($sheetName, $decoded);
        $updateStmt->execute([$sheetName, $measuredAt, (int) ($row['DataId'] ?? 0)]);
    }
}

function ws03_read_sheet_rows(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $datetimeColumns = []): array
{
    $highestRow = $sheet->getHighestDataRow();
    $highestColumn = $sheet->getHighestDataColumn();
    $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
    $rows = [];
    $datetimeLookup = array_fill_keys($datetimeColumns, true);

    for ($row = 1; $row <= $highestRow; $row++) {
        $rowValues = [];
        for ($column = 1; $column <= $highestColumnIndex; $column++) {
            $cellAddress = Coordinate::stringFromColumnIndex($column) . $row;
            $cell = $sheet->getCell($cellAddress);
            $cellValue = $cell->getCalculatedValue();
            $rowValues[] = isset($datetimeLookup[$column])
                ? ws03_format_excel_datetime_value($cellValue)
                : ws03_format_scalar_value($cellValue);
        }
        $rows[] = $rowValues;
    }

    return $rows;
}

function ws03_parse_sheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $definition): array
{
    $rows = ws03_read_sheet_rows($sheet, $definition['datetime_columns'] ?? []);
    if ($rows === []) {
        return ['headers' => [], 'rows' => []];
    }

    $expectedHeaders = $definition['expected_headers'] ?? [];
    $headerIndex = ws03_find_header_row_by_expected($rows, $expectedHeaders);
    $dataRows = [];

    if (($definition['headers_mode'] ?? 'actual') === 'expected') {
        $expectedUniqueHeaders = ws03_make_unique_headers($expectedHeaders);
        $headerIndexes = ws03_map_expected_header_indexes($rows[$headerIndex] ?? [], $expectedHeaders);

        for ($i = $headerIndex + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (ws03_is_row_empty($row)) {
                continue;
            }

            $assoc = [];
            foreach ($expectedUniqueHeaders as $position => $header) {
                $sourceIndex = $headerIndexes[$header] ?? $position;
                $assoc[$header] = ws03_format_scalar_value($row[$sourceIndex] ?? '');
            }
            $dataRows[] = $assoc;
        }

        return ['headers' => $expectedUniqueHeaders, 'rows' => $dataRows];
    }

    $sheetHeaders = ws03_make_unique_headers($rows[$headerIndex] ?? []);
    for ($i = $headerIndex + 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        if (ws03_is_row_empty($row)) {
            continue;
        }

        $assoc = [];
        foreach ($sheetHeaders as $position => $header) {
            $assoc[$header] = ws03_format_scalar_value($row[$position] ?? '');
        }
        $dataRows[] = $assoc;
    }

    return ['headers' => $sheetHeaders, 'rows' => $dataRows];
}

function ws03_parse_workbook(string $filePath): array
{
    $spreadsheet = IOFactory::load($filePath);
    $definitions = ws03_expected_sheet_definitions();
    $sheets = [];
    $sheetHeaders = [];

    foreach ($definitions as $sheetName => $definition) {
        $sheet = $spreadsheet->getSheetByName($sheetName);
        if (!$sheet) {
            if (!empty($definition['required'])) {
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
                throw new RuntimeException("Sheet {$sheetName} tidak ditemukan pada file Excel.");
            }
            continue;
        }

        $parsedSheet = ws03_parse_sheet($sheet, $definition);
        $sheets[$sheetName] = $parsedSheet['rows'];
        $sheetHeaders[$sheetName] = $parsedSheet['headers'];
    }

    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);

    if ($sheets === []) {
        throw new RuntimeException('Tidak ada sheet Washing03 yang berhasil dibaca dari file Excel.');
    }

    return [
        'headers' => $sheetHeaders['Data_WD2'] ?? ws03_make_unique_headers(ws03_expected_headers()),
        'rows' => $sheets['Data_WD2'] ?? [],
        'sheet_headers' => $sheetHeaders,
        'sheets' => $sheets,
    ];
}

function ws03_insert_rows_batch(PDO $pdo, array $batchRows): void
{
    if ($batchRows === []) {
        return;
    }

    $valuesSql = [];
    $params = [];
    foreach ($batchRows as $batchRow) {
        $valuesSql[] = '(?, ?, ?, ?, ?, ?)';
        array_push($params,
            $batchRow['UploadId'],
            $batchRow['UniqueCode'],
            $batchRow['SheetName'],
            $batchRow['RowNumber'],
            $batchRow['MeasuredAt'],
            $batchRow['RowJson']
        );
    }

    $sql = "INSERT INTO dbo.Washing03Data (UploadId, UniqueCode, SheetName, RowNumber, MeasuredAt, RowJson) VALUES " . implode(', ', $valuesSql);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function ws03_store_upload(PDO $pdo, array $parsedWorkbook, string $uniqueCode, string $originalFileName, string $storedPath, string $uploadedBy): void
{
    $checkStmt = $pdo->prepare("SELECT COUNT(1) FROM dbo.Washing03Uploads WHERE UniqueCode = ?");
    $checkStmt->execute([$uniqueCode]);
    if ((int) $checkStmt->fetchColumn() > 0) {
        throw new RuntimeException("File dengan kode unik {$uniqueCode} sudah pernah di-upload.");
    }

    $pdo->beginTransaction();
    try {
        $totalRows = 0;
        foreach (($parsedWorkbook['sheets'] ?? []) as $sheetRows) {
            $totalRows += is_array($sheetRows) ? count($sheetRows) : 0;
        }

        $insertUpload = $pdo->prepare("INSERT INTO dbo.Washing03Uploads (UniqueCode, OriginalFileName, StoredFilePath, HeaderJson, TotalRows, UploadedBy) OUTPUT INSERTED.UploadId VALUES (?, ?, ?, ?, ?, ?)");
        $uploadId = $insertUpload->execute([
            $uniqueCode,
            $originalFileName,
            $storedPath,
            json_encode($parsedWorkbook['sheet_headers'] ?? [], JSON_UNESCAPED_UNICODE),
            $totalRows,
            $uploadedBy,
        ]) ? (int) $insertUpload->fetchColumn() : 0;

        if ($uploadId <= 0) {
            throw new RuntimeException('Gagal menyimpan header upload Washing03.');
        }

        $batchRows = [];
        $batchSize = 200;
        foreach (($parsedWorkbook['sheets'] ?? []) as $sheetName => $sheetRows) {
            foreach ($sheetRows as $idx => $row) {
                $batchRows[] = [
                    'UploadId' => $uploadId,
                    'UniqueCode' => $uniqueCode,
                    'SheetName' => (string) $sheetName,
                    'RowNumber' => $idx + 1,
                    'MeasuredAt' => ws03_extract_sheet_timestamp((string) $sheetName, is_array($row) ? $row : []),
                    'RowJson' => json_encode($row, JSON_UNESCAPED_UNICODE),
                ];

                if (count($batchRows) >= $batchSize) {
                    ws03_insert_rows_batch($pdo, $batchRows);
                    $batchRows = [];
                }
            }
        }

        ws03_insert_rows_batch($pdo, $batchRows);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function ws03_flash(string $type, string $message): void
{
    $_SESSION['ws03_flash'] = ['type' => $type, 'message' => $message];
}

function ws03_take_flash(): ?array
{
    if (!isset($_SESSION['ws03_flash'])) {
        return null;
    }
    $flash = $_SESSION['ws03_flash'];
    unset($_SESSION['ws03_flash']);
    return is_array($flash) ? $flash : null;
}

function ws03_get_upload_list(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT TOP 100 UniqueCode, OriginalFileName, TotalRows, UploadedBy, UploadedAt FROM dbo.Washing03Uploads ORDER BY UploadedAt DESC, UploadId DESC");
    return $stmt->fetchAll() ?: [];
}

function ws03_get_headers(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT TOP 1 HeaderJson FROM dbo.Washing03Uploads ORDER BY UploadedAt DESC, UploadId DESC");
    $headerJson = $stmt->fetchColumn();
    if (!is_string($headerJson) || trim($headerJson) === '') {
        return ws03_make_unique_headers(ws03_expected_headers());
    }

    $decoded = json_decode($headerJson, true);
    if (is_array($decoded) && isset($decoded['Data_WD2']) && is_array($decoded['Data_WD2'])) {
        return $decoded['Data_WD2'];
    }

    return is_array($decoded) && $decoded !== [] ? $decoded : ws03_make_unique_headers(ws03_expected_headers());
}

function ws03_normalize_filter_datetime(?string $value, bool $isEnd = false): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $value = str_replace('T', ' ', $value);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
        $value .= $isEnd ? ' 23:59:59' : ' 00:00:00';
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}$/', $value) === 1) {
        $value .= $isEnd ? ':59' : ':00';
    }

    try {
        return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return null;
    }
}

function ws03_format_datetime_local_value(?string $value): string
{
    $normalized = ws03_normalize_filter_datetime($value);
    if ($normalized === null) {
        return '';
    }

    try {
        return (new DateTimeImmutable($normalized))->format('Y-m-d\TH:i:s');
    } catch (Throwable $e) {
        return '';
    }
}

function ws03_fetch_rows_by_sheet(PDO $pdo, string $sheetName, ?string $startDate, ?string $endDate, ?int $limit = null, ?int $offset = null): array
{
    $sheetName = trim($sheetName);
    if ($sheetName === '') {
        return [];
    }

    $sql = "SELECT RowNumber, RowJson, MeasuredAt, CreatedAt FROM dbo.Washing03Data WHERE SheetName = ?";
    $params = [$sheetName];

    if ($startDate !== null) {
        $sql .= ' AND MeasuredAt >= ?';
        $params[] = $startDate;
    }
    if ($endDate !== null) {
        $sql .= ' AND MeasuredAt <= ?';
        $params[] = $endDate;
    }

    $sql .= ' ORDER BY DataId DESC';
    if ($limit !== null) {
        $offsetValue = max(0, (int) ($offset ?? 0));
        $limitValue = max(1, $limit);
        $sql .= " OFFSET {$offsetValue} ROWS FETCH NEXT {$limitValue} ROWS ONLY";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];

    return array_map(static function (array $row): array {
        $decoded = json_decode((string) ($row['RowJson'] ?? '{}'), true);
        return [
            'RowNumber' => (int) ($row['RowNumber'] ?? 0),
            'RowData' => is_array($decoded) ? $decoded : [],
            'MeasuredAt' => (string) ($row['MeasuredAt'] ?? ''),
            'CreatedAt' => (string) ($row['CreatedAt'] ?? ''),
        ];
    }, $rows);
}

function ws03_count_rows_by_sheet(PDO $pdo, string $sheetName, ?string $startDate, ?string $endDate): int
{
    $sql = "SELECT COUNT(1) FROM dbo.Washing03Data WHERE SheetName = ?";
    $params = [$sheetName];

    if ($startDate !== null) {
        $sql .= ' AND MeasuredAt >= ?';
        $params[] = $startDate;
    }
    if ($endDate !== null) {
        $sql .= ' AND MeasuredAt <= ?';
        $params[] = $endDate;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function ws03_get_rows(PDO $pdo, ?string $startDate, ?string $endDate): array
{
    return ws03_fetch_rows_by_sheet($pdo, 'Data_WD2', $startDate, $endDate);
}

function ws03_count_rows(PDO $pdo, ?string $startDate, ?string $endDate): int
{
    return ws03_count_rows_by_sheet($pdo, 'Data_WD2', $startDate, $endDate);
}

function ws03_get_rows_page(PDO $pdo, ?string $startDate, ?string $endDate, int $page = 1, int $perPage = 15): array
{
    $offset = max(0, ($page - 1) * $perPage);
    return ws03_fetch_rows_by_sheet($pdo, 'Data_WD2', $startDate, $endDate, $perPage, $offset);
}

function ws03_get_rows_for_export(PDO $pdo, ?string $startDate, ?string $endDate): array
{
    $result = [];
    foreach (array_keys(ws03_expected_sheet_definitions()) as $sheetName) {
        $result[$sheetName] = ws03_fetch_rows_by_sheet($pdo, $sheetName, $startDate, $endDate);
    }
    return $result;
}

function ws03_collect_export_headers(string $sheetName, array $rows): array
{
    if ($rows !== [] && isset($rows[0]['RowData']) && is_array($rows[0]['RowData'])) {
        return array_keys($rows[0]['RowData']);
    }

    $definitions = ws03_expected_sheet_definitions();
    $definition = $definitions[$sheetName] ?? null;
    if (is_array($definition) && ($definition['headers_mode'] ?? '') === 'expected') {
        return ws03_make_unique_headers($definition['expected_headers'] ?? []);
    }

    $stmt = ws03_pdo()->prepare("SELECT TOP 1 HeaderJson FROM dbo.Washing03Uploads ORDER BY UploadedAt DESC, UploadId DESC");
    $stmt->execute();
    $headerJson = $stmt->fetchColumn();
    $decoded = is_string($headerJson) ? json_decode($headerJson, true) : null;
    return is_array($decoded) && isset($decoded[$sheetName]) && is_array($decoded[$sheetName]) ? $decoded[$sheetName] : [];
}

function ws03_delete_by_unique_code(PDO $pdo, string $uniqueCode): array
{
    $uniqueCode = trim($uniqueCode);
    if ($uniqueCode === '') {
        throw new RuntimeException('Kode unik wajib diisi.');
    }

    $stmt = $pdo->prepare("SELECT UploadId, StoredFilePath FROM dbo.Washing03Uploads WHERE UniqueCode = ?");
    $stmt->execute([$uniqueCode]);
    $uploads = $stmt->fetchAll();
    if ($uploads === []) {
        throw new RuntimeException("Kode unik {$uniqueCode} tidak ditemukan.");
    }

    $uploadIds = array_map(static fn(array $row): int => (int) $row['UploadId'], $uploads);
    $placeholders = implode(',', array_fill(0, count($uploadIds), '?'));

    $pdo->beginTransaction();
    try {
        $deleteDataStmt = $pdo->prepare("DELETE FROM dbo.Washing03Data WHERE UploadId IN ({$placeholders})");
        $deleteDataStmt->execute($uploadIds);
        $deleteUploadStmt = $pdo->prepare("DELETE FROM dbo.Washing03Uploads WHERE UploadId IN ({$placeholders})");
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

    return ['upload_count' => count($uploads), 'deleted_files' => $deletedFiles];
}

function ws03_format_header_display(string $header): string
{
    return preg_replace('/\s+#\d+$/', '', $header) ?? $header;
}

