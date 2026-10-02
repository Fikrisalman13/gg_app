<?php
session_start();
ob_start();

include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';
require_once '../../libs/tcpdf/tcpdf_barcodes_1d.php';
require_once '../../libs/tcpdf/tcpdf_barcodes_2d.php';
require_once '../../vendor/autoload.php';

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

date_default_timezone_set('Asia/Jakarta');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$groupId = $_SESSION['GroupId'] ?? null;
$menuId = 75;
$message = '';
$error = '';
$printData = null;
$editData = null;
$recentRows = [];

if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

$sql = "SELECT TOP 1 CanView, CanEdit, CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
$permissions = ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) ? $row : [];
if ($stmt) {
    sqlsrv_free_stmt($stmt);
}
if (isset($permissions['CanView']) && (int) $permissions['CanView'] === 0) {
    die("Anda tidak memiliki hak untuk melihat halaman ini.");
}

$canEdit = !isset($permissions['CanEdit']) || (int) $permissions['CanEdit'] === 1;
$canDelete = !isset($permissions['CanDelete']) || (int) $permissions['CanDelete'] === 1;

function ensurePrintBarcodeBatchTable($conn)
{
    $createSql = "
IF OBJECT_ID('dbo.printbarcodebatch', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.printbarcodebatch (
        id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        batchno NVARCHAR(80) NOT NULL,
        prodcode NVARCHAR(80) NOT NULL,
        productname NVARCHAR(255) NOT NULL,
        qty DECIMAL(18,2) NULL,
        uom NVARCHAR(30) NULL,
        batchnorm NVARCHAR(100) NULL,
        colorcode NVARCHAR(80) NULL,
        colorname NVARCHAR(100) NULL,
        custcolor NVARCHAR(80) NULL,
        labeljual NVARCHAR(150) NULL,
        sono NVARCHAR(80) NULL,
        lot NVARCHAR(80) NULL,
        createdate DATETIME2(0) NOT NULL CONSTRAINT DF_printbarcodebatch_createdate DEFAULT SYSDATETIME(),
        createby NVARCHAR(100) NULL
    );
END

IF COL_LENGTH('dbo.printbarcodebatch', 'id') IS NULL
    ALTER TABLE dbo.printbarcodebatch ADD id INT IDENTITY(1,1) NOT NULL;
IF COL_LENGTH('dbo.printbarcodebatch', 'batchno') IS NULL
    ALTER TABLE dbo.printbarcodebatch ADD batchno NVARCHAR(80) NOT NULL CONSTRAINT DF_printbarcodebatch_batchno DEFAULT '';
IF COL_LENGTH('dbo.printbarcodebatch', 'prodcode') IS NULL
    ALTER TABLE dbo.printbarcodebatch ADD prodcode NVARCHAR(80) NOT NULL CONSTRAINT DF_printbarcodebatch_prodcode DEFAULT '';
IF COL_LENGTH('dbo.printbarcodebatch', 'productname') IS NULL
    ALTER TABLE dbo.printbarcodebatch ADD productname NVARCHAR(255) NOT NULL CONSTRAINT DF_printbarcodebatch_productname DEFAULT '';
IF COL_LENGTH('dbo.printbarcodebatch', 'qty') IS NULL
    ALTER TABLE dbo.printbarcodebatch ADD qty DECIMAL(18,2) NULL;
IF COL_LENGTH('dbo.printbarcodebatch', 'uom') IS NULL
    ALTER TABLE dbo.printbarcodebatch ADD uom NVARCHAR(30) NULL;
IF COL_LENGTH('dbo.printbarcodebatch', 'batchnorm') IS NULL
    ALTER TABLE dbo.printbarcodebatch ADD batchnorm NVARCHAR(100) NULL;
IF COL_LENGTH('dbo.printbarcodebatch', 'colorcode') IS NULL
    ALTER TABLE dbo.printbarcodebatch ADD colorcode NVARCHAR(80) NULL;
IF COL_LENGTH('dbo.printbarcodebatch', 'colorname') IS NULL
    ALTER TABLE dbo.printbarcodebatch ADD colorname NVARCHAR(100) NULL;
IF COL_LENGTH('dbo.printbarcodebatch', 'custcolor') IS NULL
    ALTER TABLE dbo.printbarcodebatch ADD custcolor NVARCHAR(80) NULL;
IF COL_LENGTH('dbo.printbarcodebatch', 'labeljual') IS NULL
    ALTER TABLE dbo.printbarcodebatch ADD labeljual NVARCHAR(150) NULL;
IF COL_LENGTH('dbo.printbarcodebatch', 'sono') IS NULL
    ALTER TABLE dbo.printbarcodebatch ADD sono NVARCHAR(80) NULL;
IF COL_LENGTH('dbo.printbarcodebatch', 'lot') IS NULL
    ALTER TABLE dbo.printbarcodebatch ADD lot NVARCHAR(80) NULL;
IF COL_LENGTH('dbo.printbarcodebatch', 'createdate') IS NULL
    ALTER TABLE dbo.printbarcodebatch ADD createdate DATETIME2(0) NOT NULL CONSTRAINT DF_printbarcodebatch_createdate DEFAULT SYSDATETIME();
IF COL_LENGTH('dbo.printbarcodebatch', 'createby') IS NULL
    ALTER TABLE dbo.printbarcodebatch ADD createby NVARCHAR(100) NULL;";

    $stmt = sqlsrv_query($conn, $createSql);
    if (!$stmt) {
        throw new RuntimeException(print_r(sqlsrv_errors(), true));
    }
    sqlsrv_free_stmt($stmt);
}

function postValue($key)
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatQty($value)
{
    if ($value === null || $value === '') {
        return '';
    }

    return number_format((float) $value, 2, '.', '');
}

function formatDateTimeValue($value)
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('d/m/Y H:i');
    }

    if ($value === null || $value === '') {
        return '';
    }

    $timestamp = strtotime((string) $value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : (string) $value;
}

function normalizeImportHeader($value)
{
    $value = strtolower(trim((string) $value));
    $value = preg_replace('/[^a-z0-9]+/', '', $value);
    return $value;
}

function excelCellValue($cell)
{
    if ($cell === null) {
        return '';
    }

    $value = $cell->getCalculatedValue();
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d H:i:s');
    }

    return trim((string) $value);
}

function importPrintBarcodeBatchFile($conn, $filePath, $createBy)
{
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestDataRow();
    $highestColumn = $sheet->getHighestDataColumn();
    $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

    if ($highestRow < 2) {
        throw new InvalidArgumentException('File import tidak memiliki data.');
    }

    $headerMap = [
        'batchno' => 'batchno',
        'batchno*' => 'batchno',
        'batch' => 'batchno',
        'prodcode' => 'prodcode',
        'productcode' => 'prodcode',
        'productcode*' => 'prodcode',
        'kodeproduct' => 'prodcode',
        'productname' => 'productname',
        'productname*' => 'productname',
        'prodname' => 'productname',
        'namaproduct' => 'productname',
        'qty' => 'qty',
        'quantity' => 'qty',
        'uom' => 'uom',
        'satuan' => 'uom',
        'batchnorm' => 'batchnorm',
        'colorcode' => 'colorcode',
        'kodewarna' => 'colorcode',
        'colorname' => 'colorname',
        'namawarna' => 'colorname',
        'custcolor' => 'custcolor',
        'customercolor' => 'custcolor',
        'labeljual' => 'labeljual',
        'label' => 'labeljual',
        'sono' => 'sono',
        'sonumber' => 'sono',
        'so' => 'sono',
        'lot' => 'lot',
    ];

    $columns = [];
    for ($col = 1; $col <= $highestColumnIndex; $col++) {
        $columnName = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
        $header = excelCellValue($sheet->getCell($columnName . '1'));
        $normalized = normalizeImportHeader($header);
        if (isset($headerMap[$normalized])) {
            $columns[$col] = $headerMap[$normalized];
        }
    }

    foreach (['batchno', 'prodcode', 'productname'] as $requiredColumn) {
        if (!in_array($requiredColumn, $columns, true)) {
            throw new InvalidArgumentException('Template wajib memiliki kolom Batch No, Product Code, dan Product Name.');
        }
    }

    $insertSql = "
INSERT INTO dbo.printbarcodebatch (
    batchno, prodcode, productname, qty, uom, batchnorm, colorcode, colorname, custcolor, labeljual, sono, lot, createby
)
OUTPUT INSERTED.id
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $imported = 0;
    $skipped = 0;
    $lastInsertedId = 0;

    for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
        $payload = [
            'batchno' => '',
            'prodcode' => '',
            'productname' => '',
            'qty' => '',
            'uom' => '',
            'batchnorm' => '',
            'colorcode' => '',
            'colorname' => '',
            'custcolor' => '',
            'labeljual' => '',
            'sono' => '',
            'lot' => '',
        ];

        foreach ($columns as $col => $fieldName) {
            $columnName = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $payload[$fieldName] = excelCellValue($sheet->getCell($columnName . $rowNumber));
        }

        if (implode('', array_map('trim', $payload)) === '') {
            continue;
        }

        if ($payload['batchno'] === '' || $payload['prodcode'] === '' || $payload['productname'] === '') {
            $skipped++;
            continue;
        }

        $qty = $payload['qty'] === '' ? null : (float) str_replace(',', '.', $payload['qty']);

        $params = [
            $payload['batchno'],
            $payload['prodcode'],
            $payload['productname'],
            $qty,
            $payload['uom'],
            $payload['batchnorm'],
            $payload['colorcode'],
            $payload['colorname'],
            $payload['custcolor'],
            $payload['labeljual'],
            $payload['sono'],
            $payload['lot'],
            $createBy,
        ];

        $stmt = sqlsrv_query($conn, $insertSql, $params);
        if (!$stmt) {
            throw new RuntimeException('Gagal import baris ' . $rowNumber . ': ' . print_r(sqlsrv_errors(), true));
        }
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);

        $lastInsertedId = (int) ($row['id'] ?? 0);
        $imported++;
    }

    return [
        'imported' => $imported,
        'skipped' => $skipped,
        'last_id' => $lastInsertedId,
    ];
}

function barcodeSvgDataUri($code)
{
    $barcode = new TCPDFBarcode((string) $code, 'C128');
    $svg = $barcode->getBarcodeSVGcode(1, 34, 'black');
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function qrSvgDataUri($code)
{
    $barcode = new TCPDF2DBarcode((string) $code, 'QRCODE,M');
    $svg = $barcode->getBarcodeSVGcode(3, 3, 'black');
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

try {
    ensurePrintBarcodeBatchTable($conn);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $formAction = postValue('form_action') ?: 'save';
        $recordId = ctype_digit(postValue('record_id')) ? (int) postValue('record_id') : 0;

        $isUpdate = false;

        if ($formAction === 'import') {
            if (!$canEdit) {
                throw new RuntimeException('Anda tidak memiliki hak untuk import data.');
            }
            if (!isset($_FILES['import_file']) || !is_uploaded_file($_FILES['import_file']['tmp_name'])) {
                throw new InvalidArgumentException('Pilih file Excel yang akan diimport.');
            }
            if ((int) ($_FILES['import_file']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Upload file gagal.');
            }

            $extension = strtolower(pathinfo((string) ($_FILES['import_file']['name'] ?? ''), PATHINFO_EXTENSION));
            if (!in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
                throw new InvalidArgumentException('Format file harus .xlsx, .xls, atau .csv.');
            }

            $importResult = importPrintBarcodeBatchFile($conn, $_FILES['import_file']['tmp_name'], $_SESSION['UserName'] ?? '');
            if ($importResult['imported'] <= 0) {
                throw new RuntimeException('Tidak ada data valid yang berhasil diimport.');
            }

            $stmt = sqlsrv_query($conn, "SELECT TOP 1 * FROM dbo.printbarcodebatch WHERE id = ?", [$importResult['last_id']]);
            $printData = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
            if ($stmt) {
                sqlsrv_free_stmt($stmt);
            }

            $message = 'Import berhasil: ' . $importResult['imported'] . ' data disimpan.';
            if ($importResult['skipped'] > 0) {
                $message .= ' ' . $importResult['skipped'] . ' baris dilewati karena kolom wajib kosong.';
            }
        } elseif ($formAction === 'delete') {
            if (!$canDelete) {
                throw new RuntimeException('Anda tidak memiliki hak untuk menghapus data.');
            }
            if ($recordId <= 0) {
                throw new InvalidArgumentException('Data yang akan dihapus tidak valid.');
            }

            $stmt = sqlsrv_query($conn, "DELETE FROM dbo.printbarcodebatch WHERE id = ?", [$recordId]);
            if (!$stmt) {
                throw new RuntimeException(print_r(sqlsrv_errors(), true));
            }
            $affectedRows = sqlsrv_rows_affected($stmt);
            sqlsrv_free_stmt($stmt);

            if ($affectedRows === 0) {
                throw new RuntimeException('Data tidak ditemukan atau sudah dihapus.');
            }

            $message = 'Data berhasil dihapus.';
        } else {
        $payload = [
            'batchno' => postValue('batchno'),
            'prodcode' => postValue('prodcode'),
            'productname' => postValue('productname'),
            'qty' => postValue('qty'),
            'uom' => postValue('uom'),
            'batchnorm' => postValue('batchnorm'),
            'colorcode' => postValue('colorcode'),
            'colorname' => postValue('colorname'),
            'custcolor' => postValue('custcolor'),
            'labeljual' => postValue('labeljual'),
            'sono' => postValue('sono'),
            'lot' => postValue('lot'),
        ];

        if ($payload['batchno'] === '' || $payload['prodcode'] === '' || $payload['productname'] === '') {
            throw new InvalidArgumentException('Batch No, Product Code, dan Product Name wajib diisi.');
        }

        $qty = $payload['qty'] === '' ? null : (float) str_replace(',', '.', $payload['qty']);

        if ($recordId > 0) {
            if (!$canEdit) {
                throw new RuntimeException('Anda tidak memiliki hak untuk mengubah data.');
            }

            $updateSql = "
UPDATE dbo.printbarcodebatch
SET batchno = ?,
    prodcode = ?,
    productname = ?,
    qty = ?,
    uom = ?,
    batchnorm = ?,
    colorcode = ?,
    colorname = ?,
    custcolor = ?,
    labeljual = ?,
    sono = ?,
    lot = ?
WHERE id = ?";

            $params = [
                $payload['batchno'],
                $payload['prodcode'],
                $payload['productname'],
                $qty,
                $payload['uom'],
                $payload['batchnorm'],
                $payload['colorcode'],
                $payload['colorname'],
                $payload['custcolor'],
                $payload['labeljual'],
                $payload['sono'],
                $payload['lot'],
                $recordId,
            ];
            $stmt = sqlsrv_query($conn, $updateSql, $params);
            if (!$stmt) {
                throw new RuntimeException(print_r(sqlsrv_errors(), true));
            }
            $affectedRows = sqlsrv_rows_affected($stmt);
            sqlsrv_free_stmt($stmt);

            if ($affectedRows === 0) {
                $existsStmt = sqlsrv_query($conn, "SELECT TOP 1 id FROM dbo.printbarcodebatch WHERE id = ?", [$recordId]);
                $exists = $existsStmt ? sqlsrv_fetch_array($existsStmt, SQLSRV_FETCH_ASSOC) : null;
                if ($existsStmt) {
                    sqlsrv_free_stmt($existsStmt);
                }
                if (!$exists) {
                    throw new RuntimeException('Data yang akan diubah tidak ditemukan.');
                }
            }

            $savedId = $recordId;
            $isUpdate = true;
            $message = 'Data berhasil diubah. Label siap dicetak.';
        } else {
            $insertSql = "
INSERT INTO dbo.printbarcodebatch (
    batchno, prodcode, productname, qty, uom, batchnorm, colorcode, colorname, custcolor, labeljual, sono, lot, createby
)
OUTPUT INSERTED.id
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $payload['batchno'],
                $payload['prodcode'],
                $payload['productname'],
                $qty,
                $payload['uom'],
                $payload['batchnorm'],
                $payload['colorcode'],
                $payload['colorname'],
                $payload['custcolor'],
                $payload['labeljual'],
                $payload['sono'],
                $payload['lot'],
                $_SESSION['UserName'] ?? '',
            ];
            $stmt = sqlsrv_query($conn, $insertSql, $params);
            if (!$stmt) {
                throw new RuntimeException(print_r(sqlsrv_errors(), true));
            }
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmt);
            $savedId = (int) ($row['id'] ?? 0);
            $message = 'Data berhasil disimpan. Label siap dicetak.';
        }

        $stmt = sqlsrv_query($conn, "SELECT TOP 1 * FROM dbo.printbarcodebatch WHERE id = ?", [$savedId]);
        $printData = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
        if ($stmt) {
            sqlsrv_free_stmt($stmt);
        }
        if ($isUpdate) {
            $editData = $printData;
        }
        }
    } elseif (isset($_GET['edit_id']) && ctype_digit((string) $_GET['edit_id'])) {
        if (!$canEdit) {
            throw new RuntimeException('Anda tidak memiliki hak untuk mengubah data.');
        }
        $stmt = sqlsrv_query($conn, "SELECT TOP 1 * FROM dbo.printbarcodebatch WHERE id = ?", [(int) $_GET['edit_id']]);
        $editData = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
        $printData = $editData;
        if ($stmt) {
            sqlsrv_free_stmt($stmt);
        }
        if (!$editData) {
            throw new RuntimeException('Data yang akan diubah tidak ditemukan.');
        }
    } elseif (isset($_GET['id']) && ctype_digit((string) $_GET['id'])) {
        $stmt = sqlsrv_query($conn, "SELECT TOP 1 * FROM dbo.printbarcodebatch WHERE id = ?", [(int) $_GET['id']]);
        $printData = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
        if ($stmt) {
            sqlsrv_free_stmt($stmt);
        }
    }

    $recentStmt = sqlsrv_query($conn, "SELECT TOP 10 * FROM dbo.printbarcodebatch ORDER BY id DESC");
    if ($recentStmt) {
        while ($row = sqlsrv_fetch_array($recentStmt, SQLSRV_FETCH_ASSOC)) {
            $recentRows[] = $row;
        }
        sqlsrv_free_stmt($recentStmt);
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$formData = [
    'batchno' => postValue('batchno'),
    'prodcode' => postValue('prodcode'),
    'productname' => postValue('productname'),
    'qty' => postValue('qty'),
    'uom' => postValue('uom') ?: 'M',
    'batchnorm' => postValue('batchnorm'),
    'colorcode' => postValue('colorcode'),
    'colorname' => postValue('colorname'),
    'custcolor' => postValue('custcolor'),
    'labeljual' => postValue('labeljual'),
    'sono' => postValue('sono'),
    'lot' => postValue('lot'),
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $formData = [
        'batchno' => 'D26A0010.01.0406.001',
        'prodcode' => 'B11A30487D722A',
        'productname' => 'UNIONE 3 722A DOUBLING',
        'qty' => '30.00',
        'uom' => 'M',
        'batchnorm' => 'X25L0836.01.0510.001-1',
        'colorcode' => '3.2.11.0.0272',
        'colorname' => 'MERAH',
        'custcolor' => '722A',
        'labeljual' => '',
        'sono' => 'SOI/2512/0158',
        'lot' => '',
    ];
}

if ($editData) {
    $formData = [
        'batchno' => $editData['batchno'] ?? '',
        'prodcode' => $editData['prodcode'] ?? '',
        'productname' => $editData['productname'] ?? '',
        'qty' => formatQty($editData['qty'] ?? ''),
        'uom' => $editData['uom'] ?? 'M',
        'batchnorm' => $editData['batchnorm'] ?? '',
        'colorcode' => $editData['colorcode'] ?? '',
        'colorname' => $editData['colorname'] ?? '',
        'custcolor' => $editData['custcolor'] ?? '',
        'labeljual' => $editData['labeljual'] ?? '',
        'sono' => $editData['sono'] ?? '',
        'lot' => $editData['lot'] ?? '',
    ];
}
?>
<style>
    .barcode-page .form-control-sm {
        font-size: 12px;
    }

    .barcode-actions {
        gap: 8px;
    }

    .label-sheet-wrap {
        border: 1px solid #d8dde6;
        background: #f7f8fa;
        padding: 12px;
        overflow: auto;
    }

    .label-sheet {
        width: 50mm;
        height: 34mm;
        background: #fff;
        box-sizing: border-box;
        color: #000;
        font-family: Arial, Helvetica, sans-serif;
        page-break-after: always;
        break-after: page;
    }

    .batch-label {
        width: 50mm;
        height: 34mm;
        box-sizing: border-box;
        padding: 1mm 1.2mm 1.1mm;
        overflow: hidden;
        font-size: 6px;
        line-height: 1.08;
        border: 1px solid #111;
        display: grid;
        grid-template-columns: 12.5mm 1fr 12.5mm;
        grid-template-rows: 10.5mm 3.4mm 3.4mm 3.2mm 3.2mm 3.2mm 1fr;
        column-gap: 1.1mm;
        align-items: start;
    }

    .label-qr {
        width: 11.8mm;
        height: 11.8mm;
        object-fit: contain;
        display: block;
    }

    .label-qty {
        grid-column: 2;
        grid-row: 1;
        text-align: center;
        font-size: 18px;
        line-height: 0.85;
        font-weight: 900;
        letter-spacing: 0;
        white-space: nowrap;
    }

    .label-uom {
        display: block;
        margin-top: 2.2mm;
        font-size: 7px;
        line-height: 1;
        font-weight: 900;
        text-align: center;
    }

    .label-batch {
        grid-column: 1 / 3;
        grid-row: 2;
        font-size: 6px;
        line-height: 1;
        white-space: nowrap;
        align-self: end;
    }

    .label-prod {
        grid-column: 1 / 3;
        grid-row: 3;
        display: grid;
        grid-template-columns: 1fr 38mm;
        column-gap: 1.1mm;
        font-size: 5.7px;
        line-height: 1;
        white-space: nowrap;
    }

    .label-product {
        grid-column: 1 / 4;
        grid-row: 4;
        font-size: 5.8px;
        font-weight: 900;
        line-height: 1;
        white-space: nowrap;
        align-self: center;
    }

    .label-color,
    .label-so {
        grid-column: 1 / 3;
        font-size: 5.8px;
        font-weight: 900;
        line-height: 1;
        white-space: nowrap;
    }

    .label-color {
        grid-row: 5;
    }

    .label-so {
        grid-row: 6;
    }

    .label-lot {
        grid-column: 3;
        grid-row: 5 / 7;
        font-size: 10px;
        font-weight: 900;
        line-height: 1;
        text-align: center;
        align-self: center;
    }

    .label-custcolor {
        grid-column: 1 / 4;
        grid-row: 7;
        font-size: 17px;
        font-weight: 900;
        line-height: 0.85;
        text-align: center;
        align-self: end;
        white-space: nowrap;
    }

    .recent-table td,
    .recent-table th {
        vertical-align: middle;
        white-space: nowrap;
    }

    @media print {
        html,
        body {
            width: 50mm !important;
            height: 34mm !important;
            min-width: 50mm !important;
            min-height: 34mm !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
            background: #fff !important;
        }

        body * {
            visibility: hidden !important;
        }

        #printArea,
        #printArea * {
            visibility: visible !important;
        }

        #printArea {
            position: fixed;
            left: 0;
            top: 0;
            width: 50mm !important;
            height: auto !important;
            margin: 0;
            padding: 0;
        }

        @page {
            size: 50mm 34mm;
            margin: 0;
        }

        .label-sheet-wrap {
            border: 0;
            background: #fff;
            padding: 0;
            overflow: visible;
        }

        .label-sheet {
            box-shadow: none;
        }

        .label-sheet:last-child {
            page-break-after: auto;
            break-after: auto;
        }
    }
</style>
<div class="wrapper barcode-page">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Print Barcode Batch</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item active">Print Barcode Batch</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <?php if ($message !== '') : ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= h($message) ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                <?php if ($error !== '') : ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= h($error) ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header bg-<?= h($themeColor) ?> text-white">
                        <h3 class="card-title"><i class="fas fa-file-import mr-1"></i> Import Excel</h3>
                    </div>
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data" class="form-inline">
                            <input type="hidden" name="form_action" value="import">
                            <div class="form-group mr-2 mb-2">
                                <label for="import_file" class="mr-2">File Import</label>
                                <input
                                    type="file"
                                    id="import_file"
                                    name="import_file"
                                    class="form-control form-control-sm"
                                    accept=".xlsx,.xls,.csv"
                                    required
                                >
                            </div>
                            <button type="submit" class="btn btn-<?= h($themeColor) ?> btn-sm mb-2 mr-2">
                                <i class="fas fa-upload"></i> Import
                            </button>
                            <a href="template_import_print_barcode_batch.php" class="btn btn-success btn-sm mb-2">
                                <i class="fas fa-file-excel"></i> Download Template
                            </a>
                        </form>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-7">
                        <div class="card">
                            <div class="card-header bg-<?= h($themeColor) ?> text-white">
                                <h3 class="card-title"><i class="fas fa-barcode mr-1"></i> <?= $editData ? 'Edit Data Barcode' : 'Input Data Barcode' ?></h3>
                            </div>
                            <div class="card-body">
                                <form method="post" autocomplete="off">
                                    <input type="hidden" name="form_action" value="save">
                                    <input type="hidden" name="record_id" value="<?= h($editData['id'] ?? '') ?>">
                                    <div class="row">
                                        <?php
                                        $fields = [
                                            ['batchno', 'Batch No', 'text', true],
                                            ['prodcode', 'Product Code', 'text', true],
                                            ['productname', 'Product Name', 'text', true],
                                            ['qty', 'Qty', 'number', false],
                                            ['uom', 'UOM', 'text', false],
                                            ['batchnorm', 'Batch Norm', 'text', false],
                                            ['colorcode', 'Color Code', 'text', false],
                                            ['colorname', 'Color Name', 'text', false],
                                            ['custcolor', 'Cust Color', 'text', false],
                                            ['labeljual', 'Label Jual', 'text', false],
                                            ['sono', 'SO No', 'text', false],
                                            ['lot', 'LOT', 'text', false],
                                        ];
                                        foreach ($fields as $field) :
                                            [$name, $label, $type, $required] = $field;
                                        ?>
                                            <div class="col-md-<?= $name === 'productname' ? '6' : '3' ?>">
                                                <div class="form-group">
                                                    <label for="<?= h($name) ?>"><?= h($label) ?><?= $required ? ' *' : '' ?></label>
                                                    <input
                                                        type="<?= h($type) ?>"
                                                        id="<?= h($name) ?>"
                                                        name="<?= h($name) ?>"
                                                        class="form-control form-control-sm"
                                                        value="<?= h($formData[$name] ?? '') ?>"
                                                        <?= $type === 'number' ? 'step="0.01"' : '' ?>
                                                        <?= $required ? 'required' : '' ?>
                                                    >
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="d-flex flex-wrap barcode-actions justify-content-end">
                                        <button type="submit" class="btn btn-<?= h($themeColor) ?> btn-sm">
                                            <i class="fas fa-save"></i> <?= $editData ? 'Update' : 'Simpan' ?>
                                        </button>
                                        <?php if ($printData) : ?>
                                            <button type="button" class="btn btn-success btn-sm" id="btnPrintSelected">
                                                <i class="fas fa-print"></i> Print
                                            </button>
                                        <?php endif; ?>
                                        <a href="print_barcode_batch.php" class="btn btn-secondary btn-sm">
                                            <i class="fas fa-refresh"></i> <?= $editData ? 'Batal Edit' : 'Reset' ?>
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="card">
                            <div class="card-header bg-<?= h($themeColor) ?> text-white">
                                <h3 class="card-title"><i class="fas fa-print mr-1"></i> Preview Label</h3>
                            </div>
                            <div class="card-body">
                                <?php if ($printData && count($recentRows) > 0) : ?>
                                    <div id="printArea" class="label-sheet-wrap">
                                        <?php foreach ($recentRows as $row) : ?>
                                            <?php
                                            $rowId = (string) ($row['id'] ?? '');
                                            $selectedId = (string) ($printData['id'] ?? '');
                                            $qrLeftUri = qrSvgDataUri($row['batchno'] ?? '');
                                            $qrRightValue = implode('|', [
                                                (string) ($row['batchno'] ?? ''),
                                                (string) ($row['prodcode'] ?? ''),
                                                (string) ($row['productname'] ?? ''),
                                                (string) ($row['custcolor'] ?? ''),
                                                (string) ($row['labeljual'] ?? ''),
                                                formatQty($row['qty'] ?? ''),
                                                (string) ($row['uom'] ?? ''),
                                            ]);
                                            $qrRightUri = qrSvgDataUri($qrRightValue);
                                            $qtyText = formatQty($row['qty'] ?? '');
                                            $uomText = trim((string) ($row['uom'] ?? ''));
                                            $colorText = trim(trim((string) ($row['colorcode'] ?? '')) . ' - ' . trim((string) ($row['colorname'] ?? '')), ' -');
                                            $lotValue = trim((string) ($row['lot'] ?? ''));
                                            $lotText = $lotValue !== '' ? 'LOT-' . $lotValue : 'LOT';
                                            ?>
                                            <div class="label-sheet preview-sheet" data-print-id="<?= h($rowId) ?>" style="<?= $rowId === $selectedId ? '' : 'display:none;' ?>">
                                                <div class="batch-label">
                                                    <img class="label-qr" style="grid-column:1;grid-row:1;" src="<?= h($qrLeftUri) ?>" alt="QR kiri">
                                                    <div class="label-qty"><?= h($qtyText) ?><span class="label-uom"><?= h($uomText) ?></span></div>
                                                    <img class="label-qr" style="grid-column:3;grid-row:1;justify-self:end;" src="<?= h($qrRightUri) ?>" alt="QR kanan">
                                                    <div class="label-batch"><?= h($row['batchno'] ?? '') ?></div>
                                                    <div class="label-prod">
                                                        <span><?= h($row['prodcode'] ?? '') ?></span>
                                                        <span><?= h($row['batchnorm'] ?? '') ?></span>
                                                    </div>
                                                    <div class="label-product"><?= h($row['productname'] ?? '') ?></div>
                                                    <div class="label-color">Color&nbsp;&nbsp;:&nbsp;&nbsp;<?= h($colorText) ?></div>
                                                    <div class="label-so">SO No&nbsp;&nbsp;:&nbsp;&nbsp;<?= h($row['sono'] ?? '') ?></div>
                                                    <div class="label-lot"><?= h($lotText) ?></div>
                                                    <div class="label-custcolor"><?= h($row['custcolor'] ?? '') ?></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else : ?>
                                    <div class="text-muted text-center py-4">Preview muncul setelah data disimpan.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-history mr-1"></i> Data Terakhir</h3>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-sm table-hover recent-table">
                            <thead>
                                <tr>
                                    <th class="text-center">Print</th>
                                    <th>Created</th>
                                    <th>Batch No</th>
                                    <th>Product Code</th>
                                    <th>Product Name</th>
                                    <th>Qty</th>
                                    <th>SO No</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recentRows) > 0) : ?>
                                    <?php foreach ($recentRows as $row) : ?>
                                        <?php $rowId = (string) ($row['id'] ?? ''); ?>
                                        <tr>
                                            <td class="text-center">
                                                <input
                                                    type="checkbox"
                                                    class="print-select"
                                                    value="<?= h($rowId) ?>"
                                                    <?= $printData && $rowId === (string) ($printData['id'] ?? '') ? 'checked' : '' ?>
                                                >
                                            </td>
                                            <td><?= h(formatDateTimeValue($row['createdate'] ?? '')) ?></td>
                                            <td><?= h($row['batchno'] ?? '') ?></td>
                                            <td><?= h($row['prodcode'] ?? '') ?></td>
                                            <td><?= h($row['productname'] ?? '') ?></td>
                                            <td><?= h(formatQty($row['qty'] ?? '')) ?> <?= h($row['uom'] ?? '') ?></td>
                                            <td><?= h($row['sono'] ?? '') ?></td>
                                            <td class="text-right">
                                                <a class="btn btn-outline-primary btn-xs" href="print_barcode_batch.php?id=<?= h($row['id'] ?? '') ?>">
                                                    <i class="fas fa-eye"></i> Preview
                                                </a>
                                                <?php if ($canEdit) : ?>
                                                    <a class="btn btn-outline-warning btn-xs" href="print_barcode_batch.php?edit_id=<?= h($row['id'] ?? '') ?>">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($canDelete) : ?>
                                                    <form method="post" class="d-inline delete-barcode-form">
                                                        <input type="hidden" name="form_action" value="delete">
                                                        <input type="hidden" name="record_id" value="<?= h($row['id'] ?? '') ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-xs">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-3">Belum ada data.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    (function() {
        var checkboxes = document.querySelectorAll('.print-select');
        var sheets = document.querySelectorAll('.preview-sheet');
        var printButton = document.getElementById('btnPrintSelected');
        var deleteForms = document.querySelectorAll('.delete-barcode-form');

        function syncPreview() {
            var selected = {};
            checkboxes.forEach(function(checkbox) {
                if (checkbox.checked) {
                    selected[checkbox.value] = true;
                }
            });

            sheets.forEach(function(sheet) {
                var isSelected = !!selected[sheet.getAttribute('data-print-id')];
                sheet.style.display = isSelected ? '' : 'none';
                sheet.setAttribute('aria-hidden', isSelected ? 'false' : 'true');
            });

            if (printButton) {
                printButton.disabled = Object.keys(selected).length === 0;
            }
        }

        checkboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', syncPreview);
        });

        if (printButton) {
            printButton.addEventListener('click', function(event) {
                var hasSelected = Array.prototype.some.call(checkboxes, function(checkbox) {
                    return checkbox.checked;
                });

                if (!hasSelected) {
                    event.preventDefault();
                    alert('Pilih minimal satu data untuk print.');
                    return;
                }

                syncPreview();
                window.print();
            });
        }

        deleteForms.forEach(function(form) {
            form.addEventListener('submit', function(event) {
                if (!confirm('Hapus data barcode ini?')) {
                    event.preventDefault();
                }
            });
        });

        syncPreview();
    })();
</script>
<?php include '../../includes/footer.php'; ?>
