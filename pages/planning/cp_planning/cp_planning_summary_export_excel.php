<?php
if (function_exists('ini_set')) {
    @ini_set('display_errors', '0');
    @ini_set('display_startup_errors', '0');
}
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);

while (ob_get_level() > 0) {
    @ob_end_clean();
}
ob_start();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

if (!defined('CP_SUMMARY_EXPORT_BOOTSTRAP')) {
    define('CP_SUMMARY_EXPORT_BOOTSTRAP', true);
}
require __DIR__ . '/cp_planning_summary.php';

while (ob_get_level() > 0) {
    @ob_end_clean();
}
ob_start();

$summaryMatrixDates = is_array($summaryMatrixDates ?? null) ? $summaryMatrixDates : [];
$summaryMatrixRows = is_array($summaryMatrixRows ?? null) ? $summaryMatrixRows : [];
$summaryMatrixGrandCp = (int)($summaryMatrixGrandCp ?? 0);
$summaryMatrixGrandQty = (float)($summaryMatrixGrandQty ?? 0);
$summaryMatrixGrandQtyRealisasi = (float)($summaryMatrixGrandQtyRealisasi ?? 0);
$summaryMatrixGrandQtyPct = isset($summaryMatrixGrandQtyPct) ? $summaryMatrixGrandQtyPct : null;

$esc = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$metaPlanType = trim((string)($selectedPlanType ?? '')) !== '' ? (string)$selectedPlanType : 'Semua';
$metaPeriod = (string)($periodLabel ?? ((string)($fromDate ?? '-') . ' s/d ' . (string)($toDate ?? '-')));
$metaGenerated = (string)($summaryGeneratedAt ?? date('d/m/Y H:i'));
$dateCount = count($summaryMatrixDates);
$totalColspan = 2 + ($dateCount * 4) + 4;

$fileName = 'cp_planning_summary_' . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo '<!DOCTYPE html>';
echo '<html><head><meta charset="UTF-8">';
echo '<style>
body{font-family:Calibri,Arial,sans-serif;font-size:11pt;}
.meta{margin:0 0 4px 0;}
table{border-collapse:collapse;}
th,td{border:1px solid #8ea3bf;padding:4px 6px;text-align:center;white-space:nowrap;}
th{background:#dbe5f1;font-weight:700;}
.subhead th{background:#eef3fa;font-size:10pt;}
.left{text-align:left;}
.total{background:#e8eef7;font-weight:700;}
</style></head><body>';

echo '<h3 style="margin:0 0 8px 0;">CP Planning Summary</h3>';
echo '<div class="meta"><strong>Tipe Planning:</strong> ' . $esc($metaPlanType) . '</div>';
echo '<div class="meta"><strong>Periode:</strong> ' . $esc($metaPeriod) . '</div>';
echo '<div class="meta"><strong>Generated:</strong> ' . $esc($metaGenerated) . '</div>';
echo '<br>';

echo '<table>';
echo '<tr>';
echo '<th rowspan="2">Mesin</th>';
echo '<th rowspan="2">Kapasitas</th>';
foreach ($summaryMatrixDates as $dtIso) {
    echo '<th colspan="4">' . $esc(cpsSummaryFormatDateCell($dtIso)) . '</th>';
}
echo '<th colspan="4">Total Qty</th>';
echo '</tr>';

echo '<tr class="subhead">';
foreach ($summaryMatrixDates as $dtIso) {
    echo '<th>CP</th>';
    echo '<th>Qty</th>';
    echo '<th>Qty Realisasi</th>';
    echo '<th>Persentase</th>';
}
echo '<th>CP</th>';
echo '<th>Qty</th>';
echo '<th>Qty Realisasi</th>';
echo '<th>Persentase</th>';
echo '</tr>';

foreach ($summaryMatrixRows as $row) {
    echo '<tr>';
    echo '<td class="left">' . $esc((string)($row['machine_name'] ?? '')) . '</td>';
    echo '<td>' . $esc((string)($row['capacity'] ?? '')) . '</td>';
    foreach (($row['dates'] ?? []) as $cell) {
        echo '<td>' . $esc((string)($cell['cp'] ?? '')) . '</td>';
        echo '<td>' . $esc((string)($cell['qty'] ?? '')) . '</td>';
        echo '<td>' . $esc((string)($cell['qty_realisasi'] ?? '')) . '</td>';
        echo '<td>' . $esc((string)($cell['qty_pct'] ?? '')) . '</td>';
    }
    echo '<td>' . $esc((string)($row['total_cp'] ?? '')) . '</td>';
    echo '<td>' . $esc((string)($row['total_qty'] ?? '')) . '</td>';
    echo '<td>' . $esc((string)($row['total_qty_realisasi'] ?? '')) . '</td>';
    echo '<td>' . $esc((string)($row['total_qty_pct'] ?? '')) . '</td>';
    echo '</tr>';
}

echo '<tr class="total">';
echo '<td colspan="' . $esc((string)(2 + ($dateCount * 4))) . '">Total</td>';
echo '<td>' . $esc($summaryMatrixGrandCp > 0 ? (string)$summaryMatrixGrandCp : '') . '</td>';
echo '<td>' . $esc($summaryMatrixGrandQty > 0 ? cpsSummaryFormatQty($summaryMatrixGrandQty) : '') . '</td>';
echo '<td>' . $esc($summaryMatrixGrandQtyRealisasi > 0 ? cpsSummaryFormatQty($summaryMatrixGrandQtyRealisasi) : '') . '</td>';
echo '<td>' . $esc($summaryMatrixGrandQtyPct !== null ? cpsSummaryFormatPct($summaryMatrixGrandQtyPct) : '') . '</td>';
echo '</tr>';
echo '</table>';
echo '</body></html>';

while (ob_get_level() > 0) {
    @ob_end_flush();
}
exit;
