<?php
declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

function mk2_pdo(): PDO
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

function mk2_expected_headers(): array
{
    return [
        'Time', 'Total Production', 'Main Speed', 'Exhaust Humidity', 'Exhaust Speed1', 'Exhaust Speed2', 'Exhaust Speed3',
        'Circulation Fan Speed1', 'Circulation Fan Speed2', 'Circulation Fan Speed3', 'Circulation Fan Speed4', 'Circulation Fan Speed5',
        'Circulation Fan Speed6', 'Circulation Fan Speed7', 'Circulation Fan Speed8', 'Circulation Fan Speed9', 'Circulation Fan Speed10',
        'Air Temp3', 'Air Temp5', 'Air Temp6', 'Air Temp7', 'Air Temp8', 'Air Temp9',
        'Chamber Temp1', 'Chamber Temp2', 'Chamber Temp3', 'Chamber Temp4', 'Chamber Temp5', 'Chamber Temp6',
        'Chamber Temp7', 'Chamber Temp8', 'Chamber Temp9', 'Chamber Temp10',
        'Fabric Temp3', 'Fabric Temp5', 'Fabric Temp6', 'Fabric Temp7', 'Fabric Temp8', 'Fabric Temp9',
        'Oil Temp IN', 'Oil Temp OUT', 'Width', 'Width unit', 'Pinning', 'Over Feed', 'Feed In', 'Equipment'
    ];
}

function mk2_ensure_tables(PDO $pdo): void
{
    $sqlList = [
        "IF OBJECT_ID('dbo.Mikwang2Uploads', 'U') IS NULL BEGIN CREATE TABLE dbo.Mikwang2Uploads (UploadId INT IDENTITY(1,1) PRIMARY KEY, UniqueCode NVARCHAR(255) NOT NULL, OriginalFileName NVARCHAR(255) NOT NULL, StoredFilePath NVARCHAR(500) NULL, HeaderJson NVARCHAR(MAX) NULL, TotalRows INT NOT NULL DEFAULT 0, UploadedBy NVARCHAR(150) NULL, UploadedAt DATETIME NOT NULL DEFAULT GETDATE()); END",
        "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UQ_Mikwang2Uploads_UniqueCode' AND object_id = OBJECT_ID('dbo.Mikwang2Uploads')) BEGIN CREATE UNIQUE INDEX UQ_Mikwang2Uploads_UniqueCode ON dbo.Mikwang2Uploads (UniqueCode); END",
        "IF OBJECT_ID('dbo.Mikwang2Data', 'U') IS NULL BEGIN CREATE TABLE dbo.Mikwang2Data (DataId BIGINT IDENTITY(1,1) PRIMARY KEY, UploadId INT NOT NULL, UniqueCode NVARCHAR(255) NOT NULL, RowNumber INT NOT NULL, RecordDate DATETIME NULL, RecordDateText NVARCHAR(100) NULL, RowJson NVARCHAR(MAX) NOT NULL, CreatedAt DATETIME NOT NULL DEFAULT GETDATE()); END",
    ];

    foreach ($sqlList as $sql) {
        $pdo->exec($sql);
    }
}

function mk2_normalize_header(string $header): string
{
    $header = str_replace(["\r", "\n", "\t"], ' ', $header);
    $header = trim($header, " \t\n\r\0\x0B\"'");
    $header = preg_replace('/\s+/u', ' ', $header ?? '');
    return trim((string) $header);
}

function mk2_is_row_empty(array $row): bool
{
    foreach ($row as $value) {
        if (trim((string) $value) !== '') {
            return false;
        }
    }
    return true;
}

function mk2_find_header_row_by_expected(array $rows, array $expectedHeaders): int
{
    $expectedMap = array_map(static fn(string $header): string => strtolower(mk2_normalize_header($header)), $expectedHeaders);
    $bestIndex = 0;
    $bestScore = -1;

    foreach ($rows as $index => $row) {
        $score = 0;
        foreach ($row as $value) {
            $normalized = strtolower(mk2_normalize_header((string) $value));
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

function mk2_map_expected_header_indexes(array $headerRow, array $expectedHeaders): array
{
    $normalizedRow = [];
    foreach ($headerRow as $idx => $value) {
        $normalizedRow[$idx] = strtolower(mk2_normalize_header((string) $value));
    }

    $indexes = [];
    foreach ($expectedHeaders as $position => $header) {
        $expected = strtolower(mk2_normalize_header($header));
        $foundIndex = array_search($expected, $normalizedRow, true);
        $indexes[$header] = $foundIndex !== false ? (int) $foundIndex : $position;
    }

    return $indexes;
}

function mk2_format_scalar_value(mixed $value): string
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

function mk2_parse_excel_date_value(mixed $value): ?DateTimeImmutable
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

    foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'd-m-Y H:i:s', 'd-m-Y H:i', 'd-m-Y', 'm/d/Y H:i:s', 'm/d/Y H:i', 'm/d/Y'] as $format) {
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

function mk2_read_sheet_rows(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
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
            $rowValues[] = mk2_format_scalar_value($cell->getCalculatedValue());
        }
        $rows[] = $rowValues;
    }

    return $rows;
}

function mk2_parse_workbook(string $filePath): array
{
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getSheet(0);
    $rows = mk2_read_sheet_rows($sheet);
    $expectedHeaders = mk2_expected_headers();

    if ($rows === []) {
        return ['headers' => $expectedHeaders, 'rows' => []];
    }

    $headerIndex = mk2_find_header_row_by_expected($rows, $expectedHeaders);
    $headerIndexes = mk2_map_expected_header_indexes($rows[$headerIndex] ?? [], $expectedHeaders);
    $timeIndex = $headerIndexes['Time'] ?? 0;
    $dataRows = [];

    for ($i = $headerIndex + 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        if (mk2_is_row_empty($row)) {
            continue;
        }

        $assoc = [];
        foreach ($expectedHeaders as $header) {
            $sourceIndex = $headerIndexes[$header] ?? null;
            $assoc[$header] = mk2_format_scalar_value($sourceIndex !== null ? ($row[$sourceIndex] ?? '') : '');
        }

        $rawDateValue = $row[$timeIndex] ?? null;
        $parsedDate = mk2_parse_excel_date_value($rawDateValue);
        $assoc['__record_date'] = $parsedDate?->format('Y-m-d H:i:s');
        $assoc['__record_date_text'] = mk2_format_scalar_value($rawDateValue);
        $dataRows[] = $assoc;
    }

    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);

    return ['headers' => $expectedHeaders, 'rows' => $dataRows];
}

function mk2_store_upload(PDO $pdo, array $parsedWorkbook, string $uniqueCode, string $originalFileName, string $storedPath, string $uploadedBy): void
{
    $checkStmt = $pdo->prepare("SELECT COUNT(1) FROM dbo.Mikwang2Uploads WHERE UniqueCode = ?");
    $checkStmt->execute([$uniqueCode]);
    if ((int) $checkStmt->fetchColumn() > 0) {
        throw new RuntimeException("File dengan kode unik {$uniqueCode} sudah pernah di-upload.");
    }

    $pdo->beginTransaction();
    try {
        $insertUpload = $pdo->prepare("INSERT INTO dbo.Mikwang2Uploads (UniqueCode, OriginalFileName, StoredFilePath, HeaderJson, TotalRows, UploadedBy) OUTPUT INSERTED.UploadId VALUES (?, ?, ?, ?, ?, ?)");
        $uploadId = $insertUpload->execute([$uniqueCode, $originalFileName, $storedPath, json_encode($parsedWorkbook['headers'], JSON_UNESCAPED_UNICODE), count($parsedWorkbook['rows']), $uploadedBy]) ? (int) $insertUpload->fetchColumn() : 0;
        if ($uploadId <= 0) {
            throw new RuntimeException('Gagal menyimpan header upload Mikwang2.');
        }

        if ($parsedWorkbook['rows'] !== []) {
            $insertRow = $pdo->prepare("INSERT INTO dbo.Mikwang2Data (UploadId, UniqueCode, RowNumber, RecordDate, RecordDateText, RowJson) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($parsedWorkbook['rows'] as $idx => $row) {
                $recordDate = $row['__record_date'] ?? null;
                $recordDateText = $row['__record_date_text'] ?? null;
                unset($row['__record_date'], $row['__record_date_text']);
                $insertRow->execute([$uploadId, $uniqueCode, $idx + 1, $recordDate !== '' ? $recordDate : null, $recordDateText !== '' ? $recordDateText : null, json_encode($row, JSON_UNESCAPED_UNICODE)]);
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

function mk2_flash(string $type, string $message): void
{
    $_SESSION['mk2_flash'] = ['type' => $type, 'message' => $message];
}

function mk2_take_flash(): ?array
{
    if (!isset($_SESSION['mk2_flash'])) {
        return null;
    }
    $flash = $_SESSION['mk2_flash'];
    unset($_SESSION['mk2_flash']);
    return is_array($flash) ? $flash : null;
}

function mk2_build_date_filter_sql(string $column, array &$params, ?string $startDate, ?string $endDate): string
{
    $sql = '';
    if ($startDate !== null) {
        $sql .= " AND {$column} >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== null) {
        $sql .= " AND {$column} <= ?";
        $params[] = $endDate;
    }
    return $sql;
}

function mk2_get_upload_list(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT UploadId, UniqueCode, OriginalFileName, TotalRows, UploadedBy, CONVERT(VARCHAR(19), UploadedAt, 120) AS UploadedAt FROM dbo.Mikwang2Uploads ORDER BY UploadId DESC");
    return $stmt->fetchAll();
}

function mk2_get_headers(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT TOP 1 HeaderJson FROM dbo.Mikwang2Uploads WHERE HeaderJson IS NOT NULL ORDER BY UploadId DESC");
    $json = $stmt->fetchColumn();
    if (!$json) {
        return mk2_expected_headers();
    }
    $headers = json_decode((string) $json, true);
    return is_array($headers) ? $headers : mk2_expected_headers();
}

function mk2_fetch_rows(PDO $pdo, ?string $startDate, ?string $endDate, ?int $limit = null): array
{
    $topSql = $limit !== null ? 'TOP ' . max(1, $limit) : '';
    $sql = "SELECT {$topSql} UniqueCode, RowNumber, CONVERT(VARCHAR(19), RecordDate, 120) AS RecordDate, RecordDateText, RowJson FROM dbo.Mikwang2Data WHERE 1 = 1";
    $params = [];
    $sql .= mk2_build_date_filter_sql('RecordDate', $params, $startDate, $endDate);
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

function mk2_count_rows(PDO $pdo, ?string $startDate, ?string $endDate): int
{
    $sql = "SELECT COUNT(1) FROM dbo.Mikwang2Data WHERE 1 = 1";
    $params = [];
    $sql .= mk2_build_date_filter_sql('RecordDate', $params, $startDate, $endDate);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function mk2_get_rows_page(PDO $pdo, ?string $startDate, ?string $endDate, int $page = 1, int $perPage = 15): array
{
    $offset = (max(1, $page) - 1) * max(1, $perPage);
    return mk2_get_rows_slice($pdo, $startDate, $endDate, $offset, $perPage);
}

function mk2_get_rows_slice(PDO $pdo, ?string $startDate, ?string $endDate, int $offset = 0, int $limit = 15): array
{
    $offset = max(0, $offset);
    $limit = max(1, $limit);
    $sql = "SELECT UniqueCode, RowNumber, CONVERT(VARCHAR(19), RecordDate, 120) AS RecordDate, RecordDateText, RowJson FROM dbo.Mikwang2Data WHERE 1 = 1";
    $params = [];
    $sql .= mk2_build_date_filter_sql('RecordDate', $params, $startDate, $endDate);
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

function mk2_get_rows_for_export(PDO $pdo, ?string $startDate, ?string $endDate): array
{
    return mk2_fetch_rows($pdo, $startDate, $endDate, null);
}

function mk2_collect_export_headers(array $rows): array
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

function mk2_normalize_filter_datetime(?string $value, bool $isEnd = false): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $formats = ['Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'];
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

function mk2_format_datetime_local_value(?string $value): string
{
    $normalized = mk2_normalize_filter_datetime($value);
    if ($normalized === null) {
        return '';
    }
    try {
        return (new DateTimeImmutable($normalized))->format('Y-m-d\TH:i:s');
    } catch (Throwable $e) {
        return '';
    }
}

function mk2_format_export_datetime(mixed $value): string
{
    $parsed = mk2_parse_excel_date_value($value);
    if ($parsed instanceof DateTimeImmutable) {
        return $parsed->format('n/j/Y H:i');
    }
    return trim((string) $value);
}

function mk2_format_preview_datetime(mixed $value): string
{
    $formatted = mk2_format_export_datetime($value);
    return $formatted !== '' ? $formatted : '-';
}

function mk2_delete_by_unique_code(PDO $pdo, string $uniqueCode): array
{
    $uniqueCode = trim($uniqueCode);
    if ($uniqueCode === '') {
        throw new RuntimeException('Kode unik wajib diisi.');
    }

    $stmt = $pdo->prepare("SELECT UploadId, StoredFilePath FROM dbo.Mikwang2Uploads WHERE UniqueCode = ?");
    $stmt->execute([$uniqueCode]);
    $uploads = $stmt->fetchAll();
    if ($uploads === []) {
        throw new RuntimeException("Kode unik {$uniqueCode} tidak ditemukan.");
    }

    $uploadIds = array_map(static fn(array $row): int => (int) $row['UploadId'], $uploads);
    $placeholders = implode(',', array_fill(0, count($uploadIds), '?'));

    $pdo->beginTransaction();
    try {
        $deleteDataStmt = $pdo->prepare("DELETE FROM dbo.Mikwang2Data WHERE UploadId IN ({$placeholders})");
        $deleteDataStmt->execute($uploadIds);
        $deleteUploadStmt = $pdo->prepare("DELETE FROM dbo.Mikwang2Uploads WHERE UploadId IN ({$placeholders})");
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

function mk2_format_header_display(string $header): string
{
    return mk2_normalize_header($header);
}

