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
    return [
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
        "No.3 Dryer\nValve Output\n[0.1％]",
        "No.4 Dryer\n[0.1℃]",
        "No.1 Washer\n[0.1℃]",
        "No.2 Washer\n[0.1℃]",
        "No.3 Washer\n[0.1℃]",
        "No.4 Washer\n[0.1℃]",
        "No.5 Washer\n[0.1℃]",
        "No.6 Washer\n[0.1℃]",
        "No.7 Washer\n[0.1℃]",
        "No.8 Washer\n[0.1℃]",
        "No.9 Washer\n[0.1℃]",
        "No.10 Washer\n[0.1℃]",
        "Hot Water Tank\n[0.1℃]",
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
    ];
}

function ws03_ensure_tables(PDO $pdo): void
{
    $sqlList = [
        "IF OBJECT_ID('dbo.Washing03Uploads', 'U') IS NULL BEGIN CREATE TABLE dbo.Washing03Uploads (UploadId INT IDENTITY(1,1) PRIMARY KEY, UniqueCode NVARCHAR(255) NOT NULL, OriginalFileName NVARCHAR(255) NOT NULL, StoredFilePath NVARCHAR(500) NULL, HeaderJson NVARCHAR(MAX) NULL, TotalRows INT NOT NULL DEFAULT 0, UploadedBy NVARCHAR(150) NULL, UploadedAt DATETIME NOT NULL DEFAULT GETDATE()); END",
        "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UQ_Washing03Uploads_UniqueCode' AND object_id = OBJECT_ID('dbo.Washing03Uploads')) BEGIN CREATE UNIQUE INDEX UQ_Washing03Uploads_UniqueCode ON dbo.Washing03Uploads (UniqueCode); END",
        "IF OBJECT_ID('dbo.Washing03Data', 'U') IS NULL BEGIN CREATE TABLE dbo.Washing03Data (DataId BIGINT IDENTITY(1,1) PRIMARY KEY, UploadId INT NOT NULL, UniqueCode NVARCHAR(255) NOT NULL, RowNumber INT NOT NULL, MeasuredAt DATETIME NULL, RowJson NVARCHAR(MAX) NOT NULL, CreatedAt DATETIME NOT NULL DEFAULT GETDATE()); END",
        "IF COL_LENGTH('dbo.Washing03Data', 'MeasuredAt') IS NULL BEGIN ALTER TABLE dbo.Washing03Data ADD MeasuredAt DATETIME NULL; END",
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
    $expectedMap = array_map(static fn(string $header): string => strtolower(ws03_normalize_header($header)), $expectedHeaders);
    $bestIndex = 0;
    $bestScore = -1;

    foreach ($rows as $index => $row) {
        $score = 0;
        foreach ($row as $value) {
            $normalized = strtolower(ws03_normalize_header((string) $value));
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
        $normalizedRow[$idx] = strtolower(ws03_normalize_header($value));
    }

    $indexes = [];
    foreach (ws03_make_unique_headers($expectedHeaders) as $position => $header) {
        $expected = strtolower(ws03_normalize_header($header));
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

function ws03_extract_row_timestamp(array $rowData): ?string
{
    $rawValue = trim((string) ($rowData['Production Counter [m]'] ?? ''));
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
function ws03_backfill_measured_at(PDO $pdo): void
{
    $stmt = $pdo->query("SELECT DataId, RowJson FROM dbo.Washing03Data WHERE MeasuredAt IS NULL ORDER BY DataId ASC");
    $rows = $stmt->fetchAll() ?: [];
    if ($rows === []) {
        return;
    }

    $updateStmt = $pdo->prepare("UPDATE dbo.Washing03Data SET MeasuredAt = ? WHERE DataId = ?");
    foreach ($rows as $row) {
        $decoded = json_decode((string) ($row['RowJson'] ?? '{}'), true);
        if (!is_array($decoded)) {
            continue;
        }

        $measuredAt = ws03_extract_row_timestamp($decoded);
        if ($measuredAt === null) {
            continue;
        }

        $updateStmt->execute([$measuredAt, (int) ($row['DataId'] ?? 0)]);
    }
}

function ws03_read_sheet_rows(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
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
            $cellValue = $cell->getCalculatedValue();
            $rowValues[] = $column === 1
                ? ws03_format_excel_datetime_value($cellValue)
                : ws03_format_scalar_value($cellValue);
        }
        $rows[] = $rowValues;
    }

    return $rows;
}

function ws03_parse_workbook(string $filePath): array
{
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getSheet(0);
    $rows = ws03_read_sheet_rows($sheet);
    $expectedHeaders = ws03_expected_headers();
    $expectedUniqueHeaders = ws03_make_unique_headers($expectedHeaders);

    if ($rows === []) {
        return ['headers' => $expectedUniqueHeaders, 'rows' => []];
    }

    $headerIndex = ws03_find_header_row_by_expected($rows, $expectedHeaders);
    $headerIndexes = ws03_map_expected_header_indexes($rows[$headerIndex] ?? [], $expectedHeaders);
    $dataRows = [];

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

    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);

    return ['headers' => $expectedUniqueHeaders, 'rows' => $dataRows];
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
        $insertUpload = $pdo->prepare("INSERT INTO dbo.Washing03Uploads (UniqueCode, OriginalFileName, StoredFilePath, HeaderJson, TotalRows, UploadedBy) OUTPUT INSERTED.UploadId VALUES (?, ?, ?, ?, ?, ?)");
        $uploadId = $insertUpload->execute([
            $uniqueCode,
            $originalFileName,
            $storedPath,
            json_encode($parsedWorkbook['headers'], JSON_UNESCAPED_UNICODE),
            count($parsedWorkbook['rows']),
            $uploadedBy,
        ]) ? (int) $insertUpload->fetchColumn() : 0;

        if ($uploadId <= 0) {
            throw new RuntimeException('Gagal menyimpan header upload Washing03.');
        }

        if ($parsedWorkbook['rows'] !== []) {
            $insertRow = $pdo->prepare("INSERT INTO dbo.Washing03Data (UploadId, UniqueCode, RowNumber, MeasuredAt, RowJson) VALUES (?, ?, ?, ?, ?)");
            foreach ($parsedWorkbook['rows'] as $idx => $row) {
                $insertRow->execute([
                    $uploadId,
                    $uniqueCode,
                    $idx + 1,
                    ws03_extract_row_timestamp($row),
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

function ws03_get_rows(PDO $pdo, ?string $startDate, ?string $endDate): array
{
    $sql = "SELECT RowNumber, RowJson, MeasuredAt, CreatedAt FROM dbo.Washing03Data";
    $conditions = [];
    $params = [];

    if ($startDate !== null) {
        $conditions[] = 'MeasuredAt >= ?';
        $params[] = $startDate;
    }
    if ($endDate !== null) {
        $conditions[] = 'MeasuredAt <= ?';
        $params[] = $endDate;
    }

    if ($conditions !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= ' ORDER BY DataId DESC';
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

function ws03_count_rows(PDO $pdo, ?string $startDate, ?string $endDate): int
{
    $sql = "SELECT COUNT(1) FROM dbo.Washing03Data";
    $conditions = [];
    $params = [];

    if ($startDate !== null) {
        $conditions[] = 'MeasuredAt >= ?';
        $params[] = $startDate;
    }
    if ($endDate !== null) {
        $conditions[] = 'MeasuredAt <= ?';
        $params[] = $endDate;
    }

    if ($conditions !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function ws03_get_rows_page(PDO $pdo, ?string $startDate, ?string $endDate, int $page = 1, int $perPage = 15): array
{
    $offset = max(0, ($page - 1) * $perPage);
    $sql = "SELECT RowNumber, RowJson, MeasuredAt, CreatedAt FROM dbo.Washing03Data";
    $conditions = [];
    $params = [];

    if ($startDate !== null) {
        $conditions[] = 'MeasuredAt >= ?';
        $params[] = $startDate;
    }
    if ($endDate !== null) {
        $conditions[] = 'MeasuredAt <= ?';
        $params[] = $endDate;
    }

    if ($conditions !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= " ORDER BY DataId DESC OFFSET {$offset} ROWS FETCH NEXT {$perPage} ROWS ONLY";
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

function ws03_get_rows_for_export(PDO $pdo, ?string $startDate, ?string $endDate): array
{
    return ws03_get_rows($pdo, $startDate, $endDate);
}

function ws03_collect_export_headers(array $rows): array
{
    if ($rows === []) {
        return ws03_get_headers(ws03_pdo());
    }
    return array_keys($rows[0]['RowData'] ?? []);
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





