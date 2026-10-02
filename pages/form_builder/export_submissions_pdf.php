<?php
session_start();
require_once '../../koneksi.php';
require_once '../../vendor/autoload.php';
date_default_timezone_set('Asia/Jakarta');
use Dompdf\Dompdf;

if (!isset($_SESSION['UserId'])) {
    header("Location: ../../login.php");
    exit();
}

function submissionsHasColumn($conn, $column)
{
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

    $sql = "SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.Form_Dynamic_Submissions') AND name = ?";
    $stmt = sqlsrv_query($conn, $sql, [$column]);
    $cache[$column] = ($stmt && sqlsrv_fetch_array($stmt)) ? true : false;
    if ($stmt) {
        sqlsrv_free_stmt($stmt);
    }

    return $cache[$column];
}

function exitWithMessage($message)
{
    header('Content-Type: text/html; charset=utf-8');
    echo "<html><head><title>Export Data</title><style>body{font-family:Arial,sans-serif;background:#f8f9fa;color:#333;padding:30px;} .alert{max-width:600px;margin:40px auto;background:#fff;border:1px solid #dee2e6;border-radius:6px;padding:20px;box-shadow:0 2px 6px rgba(0,0,0,0.08);} .alert h3{margin-top:0;color:#c0392b;} .alert a{color:#007bff;text-decoration:none;}</style></head><body><div class='alert'><h3>Export Tidak Dapat Dilanjutkan</h3><p>" . htmlspecialchars($message) . "</p><p><a href='javascript:window.close();'>&laquo; Tutup</a></p></div></body></html>";
    exit();
}

$submissionsHasCreatedAt = submissionsHasColumn($conn, 'created_at');

$templateSlug = isset($_GET['template_name']) ? trim($_GET['template_name']) : null;
$templateIdParam = $_GET['id'] ?? null; // legacy fallback
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

$columnCount = 1 + count($displayFields) + 2; // No + fields + Oleh + Tanggal

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color:#000; }
        h2 { text-align: center; margin-bottom: 5px; }
        p.meta { text-align: center; margin: 0 0 15px 0; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #444; padding: 6px 5px; }
        th { background: #f5f5f5; font-weight: bold; text-transform: uppercase; font-size: 10px; }
        td { vertical-align: top; }
    </style>
</head>
<body>
    <h2>Rekap Data Form: <?php echo htmlspecialchars($templateName); ?></h2>
    <p class="meta">
        Dicetak pada <?php echo date('d-m-Y H:i'); ?>
        <?php if ($startDateValue || $endDateValue): ?>
            | Rentang Tanggal: <?php echo $startDateValue ? htmlspecialchars($startDateValue) : 'Semua'; ?> &mdash; <?php echo $endDateValue ? htmlspecialchars($endDateValue) : 'Semua'; ?>
        <?php endif; ?>
    </p>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <?php foreach ($displayFields as $field): ?>
                    <th><?php echo htmlspecialchars($field['label']); ?></th>
                <?php endforeach; ?>
                <th>Oleh</th>
                <th>Tanggal</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr>
                <td colspan="<?php echo $columnCount; ?>" style="text-align:center;">Tidak ada data untuk kriteria ini.</td>
            </tr>
        <?php else: ?>
            <?php $no = 1; foreach ($rows as $row): ?>
                <?php $data = json_decode($row['submission_data'], true) ?: []; ?>
                <tr>
                    <td><?php echo $no++; ?></td>
                    <?php foreach ($displayFields as $field): ?>
                        <?php
                            $label = $field['label'];
                            $valObj = $data[$label] ?? null;
                            $display = '';
                            $isColor = false;
                            if ($valObj) {
                                $value = $valObj['value'] ?? '';
                                if (is_array($value)) {
                                    $value = implode(', ', $value);
                                }
                                if (($valObj['type'] ?? '') === 'file') {
                                    $display = 'File: ' . ($valObj['original_name'] ?? $value);
                                } elseif (($valObj['type'] ?? '') === 'color' && !empty($value)) {
                                    $isColor = true;
                                    $display = $value;
                                } else {
                                    $display = $value;
                                }
                            }
                            if ($display === '' || $display === null) {
                                $display = '-';
                            }
                        ?>
                        <td style="text-align:center; vertical-align:middle;">
                            <?php if ($isColor && $display !== '-'): ?>
                                <table style="margin:0 auto; border-collapse:collapse;">
                                    <tr>
                                        <td style="padding:0; border:none; vertical-align:middle; padding-right:4px;">
                                            <span style="display:block; width:14px; height:14px; border-radius:3px; background:<?php echo htmlspecialchars($display); ?>; border:1px solid rgba(0,0,0,0.25);"></span>
                                        </td>
                                        <td style="padding:0; border:none; vertical-align:middle; font-family:monospace; font-size:9px;"><?php echo htmlspecialchars($display); ?></td>
                                    </tr>
                                </table>
                            <?php else: ?>
                                <?php echo htmlspecialchars($display); ?>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                    <td><?php echo htmlspecialchars($row['created_by'] ?? '-'); ?></td>
                    <td>
                        <?php
                            $createdAtLabel = '-';
                            if (!empty($row['created_at'])) {
                                if ($row['created_at'] instanceof DateTime) {
                                    $createdAtLabel = $row['created_at']->format('d-m-Y H:i');
                                } elseif (is_string($row['created_at'])) {
                                    $createdAtLabel = $row['created_at'];
                                }
                            }
                            echo htmlspecialchars($createdAtLabel);
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
<?php
$html = ob_get_clean();

$dompdf = new Dompdf([
    'isHtml5ParserEnabled' => true,
    'isRemoteEnabled' => true,
]);
$dompdf->setPaper('A4', 'landscape');
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->render();
$filenameSafe = preg_replace('/[^A-Za-z0-9_-]+/', '_', $templateName);
$dompdf->stream('Submissions_' . $filenameSafe . '_' . date('Ymd_His') . '.pdf', ['Attachment' => false]);
exit();
