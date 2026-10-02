<?php
session_start();
require_once '../../koneksi.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

if (!isset($_SESSION['UserId'])) {
    header("Location: ../../login.php");
    exit();
}

// Reuse helper function to check column existence
function submissionsHasColumn($conn, $column)
{
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

    $sql = "SELECT 1 FROM sys.columns WHERE (object_id = OBJECT_ID('Form_Dynamic_Submissions') OR object_id = OBJECT_ID('dbo.Form_Dynamic_Submissions')) AND name = ?";
    $stmt = sqlsrv_query($conn, $sql, [$column]);
    $cache[$column] = ($stmt && sqlsrv_fetch_array($stmt)) ? true : false;
    if ($stmt) {
        sqlsrv_free_stmt($stmt);
    }

    return $cache[$column];
}

function exitWithMessage($message)
{
    // Simple error display
    header('Content-Type: text/html; charset=utf-8');
    echo "<html><head><title>Export Error</title></head><body><h3>Export Gagal</h3><p>" . htmlspecialchars($message) . "</p></body></html>";
    exit();
}

$submissionsHasCreatedAt = submissionsHasColumn($conn, 'created_at');

$templateSlug = isset($_GET['template_name']) ? trim($_GET['template_name']) : null;
$templateIdParam = $_GET['id'] ?? null;

if (!$templateSlug && !$templateIdParam) {
    exitWithMessage('Template tidak ditemukan.');
}

if ($templateSlug) {
    $sqlTemplate = "SELECT * FROM Form_Dynamic_Templates WHERE template_name = ?";
    $stmtTemplate = sqlsrv_query($conn, $sqlTemplate, [$templateSlug]);
} else {
    $sqlTemplate = "SELECT * FROM Form_Dynamic_Templates WHERE id = ?";
    $stmtTemplate = sqlsrv_query($conn, $sqlTemplate, [$templateIdParam]);
}

if (!$stmtTemplate || !($template = sqlsrv_fetch_array($stmtTemplate, SQLSRV_FETCH_ASSOC))) {
    exitWithMessage('Template tidak ditemukan.');
}
if ($stmtTemplate) {
    sqlsrv_free_stmt($stmtTemplate);
}

$templateId = $template['id'];
$templateName = $template['template_name'];
$fields = json_decode($template['fields_json'], true) ?: [];
$displayFields = [];
foreach ($fields as $field) {
    if (!isset($field['item_type']) || $field['item_type'] === 'field') {
        $displayFields[] = $field;
    }
}

// Filter Logic
$startDateValue = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDateValue = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$dateFilterError = null;
$startDateSqlValue = null;
$endDateSqlValue = null;
$startDateObj = null;
$endDateObj = null;

if ($startDateValue !== '') {
    $startDateObj = DateTime::createFromFormat('Y-m-d', $startDateValue);
    if ($startDateObj) {
        $startDateSqlValue = (clone $startDateObj)->setTime(0, 0, 0)->format('Y-m-d H:i:s');
    } else {
        $dateFilterError = 'Tanggal awal tidak valid.';
    }
}

if ($endDateValue !== '') {
    $endDateObj = DateTime::createFromFormat('Y-m-d', $endDateValue);
    if ($endDateObj) {
        $endDateSqlValue = (clone $endDateObj)->setTime(23, 59, 59)->format('Y-m-d H:i:s');
    } else {
        $dateFilterError = $dateFilterError ?: 'Tanggal akhir tidak valid.';
    }
}

if ($startDateObj && $endDateObj && $startDateObj > $endDateObj) {
    $dateFilterError = 'Tanggal awal tidak boleh setelah tanggal akhir.';
}

if (!$submissionsHasCreatedAt && ($startDateValue !== '' || $endDateValue !== '')) {
    $dateFilterError = 'Filter tanggal tidak dapat digunakan karena kolom tanggal tidak tersedia.';
}

if ($dateFilterError) {
    exitWithMessage($dateFilterError);
}

$orderBy = $submissionsHasCreatedAt ? ' ORDER BY created_at DESC' : ' ORDER BY submission_id DESC';
$whereClauses = ['template_id = ?'];
$sqlParams = [$templateId];
if ($submissionsHasCreatedAt) {
    if ($startDateSqlValue) {
        $whereClauses[] = 'created_at >= ?';
        $sqlParams[] = $startDateSqlValue;
    }
    if ($endDateSqlValue) {
        $whereClauses[] = 'created_at <= ?';
        $sqlParams[] = $endDateSqlValue;
    }
}
$whereSql = implode(' AND ', $whereClauses);
$sqlSub = "SELECT * FROM Form_Dynamic_Submissions WHERE " . $whereSql . $orderBy;
$stmtSub = sqlsrv_query($conn, $sqlSub, $sqlParams);
if (!$stmtSub) {
    exitWithMessage('Gagal mengambil data.');
}

$rows = [];
while ($row = sqlsrv_fetch_array($stmtSub, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $row;
}
sqlsrv_free_stmt($stmtSub);

// --- PHPSpreadsheet Generation ---

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Submissions');

// Styles
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['argb' => 'FFFFFFFF'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['argb' => 'FF4F81BD'],
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
        ],
    ],
];

$dataStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
        ],
    ],
    'alignment' => [
        'vertical' => Alignment::VERTICAL_TOP,
    ],
];

// 1. Headers
$col = 1;

// "No" Column
$cellAddress = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . '1';
$sheet->setCellValue($cellAddress, 'No');
$sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
$col++;

// Form Fields Headers
foreach ($displayFields as $field) {
    $cellAddress = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . '1';
    $sheet->setCellValue($cellAddress, $field['label']);
    $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
    $col++;
}

// "User" & "Tanggal" Headers
$cellAddress = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . '1';
$sheet->setCellValue($cellAddress, 'User');
$sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
$col++;

$cellAddress = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . '1';
$sheet->setCellValue($cellAddress, 'Tanggal');
$sheet->getColumnDimensionByColumn($col)->setAutoSize(true);

// Apply Header Style
$lastColIndex = $col;
$lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColIndex);
$sheet->getStyle("A1:{$lastColLetter}1")->applyFromArray($headerStyle);

// 2. Data Rows
$rowNum = 2;
$no = 1;

foreach ($rows as $row) {
    $col = 1;
    $data = json_decode($row['submission_data'], true) ?: [];

    // No
    $cellAddress = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $rowNum;
    $sheet->setCellValue($cellAddress, $no++);
    $sheet->getStyle($cellAddress)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $col++;

    // Fields
    foreach ($displayFields as $field) {
        $label = $field['label'];
        $valObj = $data[$label] ?? null;
        $display = '';
        
        if ($valObj) {
            $value = $valObj['value'] ?? '';
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            // Handle file type
            if (($valObj['type'] ?? '') === 'file') {
                 // For Excel, maybe just show filename or a note
                 $display = $valObj['original_name'] ?? $value;
                 if (!$display) {
                     $display = '(File)';
                 }
            } elseif (($valObj['type'] ?? '') === 'color' && !empty($value)) {
                $display = $value; // show hex code as text
            } else {
                $display = $value;
            }
        }
        
        $cellAddress = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $rowNum;
        $sheet->setCellValueExplicit($cellAddress, $display, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        // If color type, fill the cell background with the color
        if (($valObj['type'] ?? '') === 'color' && !empty($valObj['value'] ?? '')) {
            $hexColor = ltrim($valObj['value'], '#');
            if (strlen($hexColor) === 6 && ctype_xdigit($hexColor)) {
                // Calculate luminance to decide text color (black or white)
                $r = hexdec(substr($hexColor, 0, 2));
                $g = hexdec(substr($hexColor, 2, 2));
                $b = hexdec(substr($hexColor, 4, 2));
                $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b);
                $textColor = $luminance > 128 ? '000000' : 'FFFFFF';

                $sheet->getStyle($cellAddress)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF' . strtoupper($hexColor)],
                    ],
                    'font' => [
                        'color' => ['argb' => 'FF' . $textColor],
                        'bold' => true,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                ]);
            }
        }

        $col++;
    }

    // User
    $cellAddress = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $rowNum;
    $sheet->setCellValue($cellAddress, $row['created_by'] ?? '');
    $col++;

    // Created At
    $createdAtLabel = '';
    if (!empty($row['created_at'])) {
        if ($row['created_at'] instanceof DateTime) {
            $createdAtLabel = $row['created_at']->format('d-m-Y H:i');
        } elseif (is_string($row['created_at'])) {
            $createdAtLabel = $row['created_at'];
        }
    }
    $cellAddress = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $rowNum;
    $sheet->setCellValue($cellAddress, $createdAtLabel);
    
    // Apply data style to the row
    $sheet->getStyle("A{$rowNum}:{$lastColLetter}{$rowNum}")->applyFromArray($dataStyle);

    $rowNum++;
}

// Auto filter for usability
$sheet->setAutoFilter("A1:{$lastColLetter}" . ($rowNum - 1));

// Output
$filenameSafe = preg_replace('/[^A-Za-z0-9_-]+/', '_', $templateName);
$filename = 'Submissions_' . $filenameSafe . '_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1'); // If you're serving to IE 9/10/11
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
header('Pragma: public'); // HTTP/1.0

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
