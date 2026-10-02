<?php
ini_set('memory_limit', '512M');
set_time_limit(300);

session_start();
date_default_timezone_set('Asia/Jakarta');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/printer_whitelist_helpers.php');

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/gg_app/vendor/autoload.php');

use Mpdf\Mpdf;

function checkReportIsiTintaPermissions($conn, $groupId, $menuId)
{
    $sql = "SELECT CanView FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    $permissions = ['CanView' => 0];
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    if ($stmt !== false) {
        sqlsrv_free_stmt($stmt);
    }
    return $permissions;
}

function pdfText($value)
{
    $text = trim((string) ($value ?? ''));
    return htmlspecialchars($text !== '' ? $text : '-', ENT_QUOTES, 'UTF-8');
}

function pdfDate($value)
{
    $date = normalizePrinterSqlsrvDate($value);
    if ($date instanceof DateTimeImmutable) {
        return $date->format('d-m-Y H:i');
    }
    return trim((string) $value) !== '' ? (string) $value : '-';
}

function svgText($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function shortChartLabel($value, $maxLength = 18)
{
    $text = trim((string) $value);
    if ($text === '') {
        return '-';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text) > $maxLength ? mb_substr($text, 0, $maxLength - 3) . '...' : $text;
    }

    return strlen($text) > $maxLength ? substr($text, 0, $maxLength - 3) . '...' : $text;
}

function buildComboChartImage(array $labels, array $barValues, array $lineValues, string $barLabel, string $lineLabel, string $lineColor = '#1f2937')
{
    if (empty($labels)) {
        return '<div class="empty chart-empty">Tidak ada data untuk grafik.</div>';
    }

    $width = 1040;
    $height = 360;
    $left = 58;
    $right = 44;
    $top = 48;
    $bottom = 84;
    $plotWidth = $width - $left - $right;
    $plotHeight = $height - $top - $bottom;
    $count = count($labels);
    $slot = $plotWidth / max(1, $count);
    $barWidth = max(9, min(28, $slot * 0.58));
    $maxBar = max(1, max(array_map('floatval', $barValues)));
    $maxLine = max(1, max(array_map('floatval', $lineValues)));
    $leftTicks = max(3, min(6, (int) ceil($maxBar)));
    $rightTicks = max(4, min(8, (int) ceil($maxLine)));
    $palette = ['#42a5f5', '#66bb6a', '#ffa726', '#ec407a', '#ab47bc', '#26c6da', '#ef5350', '#9ccc65', '#ffca28', '#5c6bc0', '#26a69a', '#8d6e63'];

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">';
    $svg .= '<rect x="0" y="0" width="' . $width . '" height="' . $height . '" fill="#ffffff"/>';
    $svg .= '<rect x="' . $left . '" y="' . $top . '" width="' . $plotWidth . '" height="' . $plotHeight . '" fill="#ffffff"/>';

    for ($i = 0; $i <= $leftTicks; $i++) {
        $y = $top + $plotHeight - (($plotHeight / $leftTicks) * $i);
        $value = round(($maxBar / $leftTicks) * $i, 1);
        $svg .= '<line x1="' . $left . '" y1="' . $y . '" x2="' . ($left + $plotWidth) . '" y2="' . $y . '" stroke="#e5e7eb" stroke-width="1"/>';
        $svg .= '<text x="' . ($left - 10) . '" y="' . ($y + 4) . '" text-anchor="end" font-size="10" fill="#4b5563">' . svgText($value) . '</text>';
    }

    for ($i = 0; $i <= $rightTicks; $i++) {
        $y = $top + $plotHeight - (($plotHeight / $rightTicks) * $i);
        $value = round(($maxLine / $rightTicks) * $i, 1);
        $svg .= '<text x="' . ($left + $plotWidth + 10) . '" y="' . ($y + 4) . '" font-size="10" fill="#4b5563">' . svgText($value) . '</text>';
    }

    $svg .= '<line x1="' . $left . '" y1="' . $top . '" x2="' . $left . '" y2="' . ($top + $plotHeight) . '" stroke="#9ca3af" stroke-width="1"/>';
    $svg .= '<line x1="' . $left . '" y1="' . ($top + $plotHeight) . '" x2="' . ($left + $plotWidth) . '" y2="' . ($top + $plotHeight) . '" stroke="#9ca3af" stroke-width="1"/>';
    $svg .= '<line x1="' . ($left + $plotWidth) . '" y1="' . $top . '" x2="' . ($left + $plotWidth) . '" y2="' . ($top + $plotHeight) . '" stroke="#d1d5db" stroke-width="1"/>';

    $points = [];
    foreach ($labels as $index => $label) {
        $centerX = $left + ($slot * $index) + ($slot / 2);
        $barValue = (float) ($barValues[$index] ?? 0);
        $barHeight = ($barValue / $maxBar) * $plotHeight;
        $barX = $centerX - ($barWidth / 2);
        $barY = $top + $plotHeight - $barHeight;
        $barColor = $palette[$index % count($palette)];

        $svg .= '<rect x="' . $barX . '" y="' . $barY . '" width="' . $barWidth . '" height="' . $barHeight . '" fill="' . $barColor . '"/>';
        $svg .= '<text x="' . $centerX . '" y="' . ($top + $plotHeight + 17) . '" text-anchor="end" transform="rotate(-35 ' . $centerX . ' ' . ($top + $plotHeight + 17) . ')" font-size="9" fill="#374151">' . svgText(shortChartLabel($label)) . '</text>';

        $lineValue = (float) ($lineValues[$index] ?? 0);
        $lineY = $top + $plotHeight - (($lineValue / $maxLine) * $plotHeight);
        $points[] = [$centerX, $lineY, $barColor];
    }

    if (count($points) > 1) {
        $polyline = [];
        foreach ($points as $point) {
            $polyline[] = $point[0] . ',' . $point[1];
        }
        $svg .= '<polyline points="' . implode(' ', $polyline) . '" fill="none" stroke="' . $lineColor . '" stroke-width="3"/>';
    }

    foreach ($points as $point) {
        $svg .= '<circle cx="' . $point[0] . '" cy="' . $point[1] . '" r="4" fill="' . $point[2] . '" stroke="' . $lineColor . '" stroke-width="1"/>';
    }

    $svg .= '<rect x="390" y="16" width="36" height="10" fill="#42a5f5"/>';
    $svg .= '<text x="432" y="25" font-size="11" fill="#111827">' . svgText($barLabel) . '</text>';
    $svg .= '<rect x="520" y="16" width="36" height="10" fill="' . svgText($lineColor) . '"/>';
    $svg .= '<text x="562" y="25" font-size="11" fill="#111827">' . svgText($lineLabel) . '</text>';
    $svg .= '</svg>';

    return '<img class="chart-img" src="data:image/svg+xml;base64,' . base64_encode($svg) . '" alt="' . pdfText($barLabel) . '">';
}

$permissions = checkReportIsiTintaPermissions($conn, $_SESSION['GroupId'], 162);
if ($permissions['CanView'] != 1) {
    die('Anda tidak memiliki hak untuk mengekspor report ini.');
}

$todayDate = new DateTimeImmutable('today');
$defaultEndDate = $todayDate->format('Y-m-d');
$defaultStartDate = $todayDate->modify('-1 month')->format('Y-m-d');
$selectedAssetId = isset($_GET['id_asset']) ? (int) $_GET['id_asset'] : 0;
$startDate = $_GET['start_date'] ?? $defaultStartDate;
$endDate = $_GET['end_date'] ?? $defaultEndDate;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $startDate = $defaultStartDate;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $endDate = $defaultEndDate;
}
if ($startDate > $endDate) {
    $tempDate = $startDate;
    $startDate = $endDate;
    $endDate = $tempDate;
}

if (!ensurePrinterWhitelistTable($conn) || !syncPrinterLateLogs($conn)) {
    die('Gagal menyiapkan data report isi tinta.');
}

$sql = "SELECT
            ah.id_asset,
            ah.id_history,
            ah.created_at,
            owner_emp.nama_lengkap,
            a.kode_asset_seq,
            pw.jenis_tinta,
            ISNULL(performer_emp.nama_lengkap, ah.created_by) AS dikerjakan_oleh,
            pl.log_message AS late_fill_log
        FROM dbo.asset_history ah
        INNER JOIN dbo.m_asset a ON ah.id_asset = a.id_asset
        INNER JOIN dbo.printer_user_whitelist pw ON a.id_asset = pw.id_asset AND pw.is_active = 1
        LEFT JOIN dbo.printer_isi_tinta_log pl ON ah.id_history = pl.id_history
        LEFT JOIN dbo.m_emp owner_emp ON a.id_emp = owner_emp.id_emp
        LEFT JOIN dbo.SMUserMs smu ON LTRIM(RTRIM(smu.UserName)) = LTRIM(RTRIM(ah.created_by))
        LEFT JOIN dbo.m_emp performer_emp ON smu.EmpId = performer_emp.id_emp
        WHERE a.id_kode = ?
          AND (? = 0 OR ah.id_asset = ?)
          AND a.id_status IN (1, 6)
          AND ah.new_status = 'Maintenance'
          AND LOWER(ISNULL(ah.note, '')) LIKE '%isi tinta%'
          AND ah.created_at >= ?
          AND ah.created_at < DATEADD(DAY, 1, ?)
        ORDER BY ah.created_at DESC, ah.id_history DESC";
$stmt = sqlsrv_query($conn, $sql, [4, $selectedAssetId, $selectedAssetId, $startDate, $endDate]);
if ($stmt === false) {
    die('Gagal mengambil report isi tinta: ' . print_r(sqlsrv_errors(), true));
}

$reportData = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $row['created_at_obj'] = normalizePrinterSqlsrvDate($row['created_at'] ?? null);
    $row['interval_days'] = null;
    $reportData[] = $row;
}
sqlsrv_free_stmt($stmt);

$groupedByAsset = [];
foreach ($reportData as $idx => $row) {
    $assetId = (int) ($row['id_asset'] ?? 0);
    if (!isset($groupedByAsset[$assetId])) {
        $groupedByAsset[$assetId] = [];
    }
    $groupedByAsset[$assetId][] = $idx;
}

foreach ($groupedByAsset as $indexes) {
    usort($indexes, function ($a, $b) use ($reportData) {
        $dateA = $reportData[$a]['created_at_obj'] instanceof DateTimeImmutable ? $reportData[$a]['created_at_obj']->getTimestamp() : 0;
        $dateB = $reportData[$b]['created_at_obj'] instanceof DateTimeImmutable ? $reportData[$b]['created_at_obj']->getTimestamp() : 0;
        return $dateA <=> $dateB;
    });

    $prevDate = null;
    foreach ($indexes as $idx) {
        $currentDate = $reportData[$idx]['created_at_obj'] ?? null;
        if ($prevDate instanceof DateTimeImmutable && $currentDate instanceof DateTimeImmutable) {
            $reportData[$idx]['interval_days'] = (int) $prevDate->setTime(0, 0, 0)->diff($currentDate->setTime(0, 0, 0))->format('%a');
        }
        $prevDate = $currentDate;
    }
}

$userStats = [];
$performerStats = [];
foreach ($reportData as $row) {
    $userName = trim((string) ($row['nama_lengkap'] ?? ''));
    if ($userName === '') {
        $userName = '-';
    }
    if (!isset($userStats[$userName])) {
        $userStats[$userName] = [
            'nama' => $userName,
            'total' => 0,
            'interval_total' => 0,
            'interval_count' => 0,
            'avg_interval' => 0,
        ];
    }
    $userStats[$userName]['total']++;
    if ($row['interval_days'] !== null) {
        $userStats[$userName]['interval_total'] += (int) $row['interval_days'];
        $userStats[$userName]['interval_count']++;
    }

    $performerName = trim((string) ($row['dikerjakan_oleh'] ?? ''));
    if ($performerName === '') {
        $performerName = '-';
    }
    if (!isset($performerStats[$performerName])) {
        $performerStats[$performerName] = [
            'nama' => $performerName,
            'total' => 0,
            'users' => [],
        ];
    }
    $performerStats[$performerName]['total']++;
    if ($userName !== '-') {
        $performerStats[$performerName]['users'][$userName] = $userName;
    }
}

foreach ($userStats as &$userRow) {
    $userRow['avg_interval'] = $userRow['interval_count'] > 0
        ? round($userRow['interval_total'] / $userRow['interval_count'], 1)
        : 0;
}
unset($userRow);

usort($userStats, function ($a, $b) {
    if ($a['total'] === $b['total']) {
        return strcasecmp($a['nama'], $b['nama']);
    }
    return $b['total'] <=> $a['total'];
});

foreach ($performerStats as &$performerRow) {
    ksort($performerRow['users'], SORT_NATURAL | SORT_FLAG_CASE);
}
unset($performerRow);

usort($performerStats, function ($a, $b) {
    if ($a['total'] === $b['total']) {
        return strcasecmp($a['nama'], $b['nama']);
    }
    return $b['total'] <=> $a['total'];
});

$topPerformer = $performerStats[0] ?? null;
$overallIntervalTotal = array_sum(array_column($userStats, 'interval_total'));
$overallIntervalCount = array_sum(array_column($userStats, 'interval_count'));
$overallAvgInterval = $overallIntervalCount > 0 ? round($overallIntervalTotal / $overallIntervalCount, 1) : 0;
$userChartLabels = array_column($userStats, 'nama');
$userChartTotals = array_map('intval', array_column($userStats, 'total'));
$userChartAvgIntervals = array_map('floatval', array_column($userStats, 'avg_interval'));
$performerChartLabels = array_column($performerStats, 'nama');
$performerChartTotals = array_map('intval', array_column($performerStats, 'total'));
$performerChartUsers = array_map(function ($row) {
    return count($row['users'] ?? []);
}, $performerStats);
$periodLabel = DateTimeImmutable::createFromFormat('Y-m-d', $startDate)->format('d/m/Y')
    . ' - '
    . DateTimeImmutable::createFromFormat('Y-m-d', $endDate)->format('d/m/Y');

$mpdf = new Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4-L',
    'margin_left' => 8,
    'margin_right' => 8,
    'margin_top' => 12,
    'margin_bottom' => 10,
    'margin_header' => 6,
    'margin_footer' => 6,
]);
$mpdf->SetTitle('Report Isi Tinta');
$mpdf->SetAuthor('GG App');
$mpdf->SetFooter('Generated ' . date('d/m/Y H:i') . ' | {PAGENO}/{nbpg}');

$css = '
body { font-family: sans-serif; font-size: 8.5px; color: #1f2933; }
h1 { text-align: center; font-size: 16px; margin: 0 0 4px 0; }
h2 { font-size: 11px; margin: 12px 0 6px 0; color: #111827; }
.meta { text-align: center; color: #555; margin-bottom: 10px; }
.summary { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
.summary td { border: 0.3mm solid #b8c2cc; padding: 6px; background: #f8fafc; }
.summary .value { display: block; font-size: 13px; font-weight: bold; color: #111827; }
.summary .label { color: #5b6777; text-transform: uppercase; font-size: 7px; }
.chart-panel { border: 0.3mm solid #d6dde8; border-radius: 3mm; padding: 7px; margin-bottom: 10px; background: #ffffff; }
.chart-stats { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin-bottom: 8px; }
.chart-stats td { border: 0.3mm solid #93c5fd; border-radius: 2mm; padding: 9px 12px; background: #dbeafe; }
.chart-stats td.green { border-color: #86efac; background: #dcfce7; }
.chart-stats .value { display: block; font-size: 15px; font-weight: bold; color: #111827; }
.chart-stats .label { display: block; font-size: 8px; color: #4b5563; text-transform: uppercase; letter-spacing: 0.04em; }
.chart-img { width: 100%; height: auto; }
.chart-empty { border: 0.3mm solid #d1d5db; border-radius: 2mm; margin: 4px 0 8px 0; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
th, td { border: 0.3mm solid #555; padding: 3px 4px; vertical-align: top; }
th { background: #e8eef5; font-weight: bold; text-align: center; }
tr.even { background: #f7f7f7; }
.empty { text-align: center; padding: 8px; color: #666; }
.num { text-align: center; white-space: nowrap; }
.small { font-size: 7.5px; color: #4b5563; }
.page-break { page-break-before: always; }
';

$html = '<style>' . $css . '</style>';
$html .= '<h1>Report Isi Tinta</h1>';
$html .= '<div class="meta">Periode: ' . pdfText($periodLabel) . ($selectedAssetId > 0 ? ' | Asset ID: ' . (int) $selectedAssetId : '') . '</div>';

$html .= '<table class="summary"><tr>'
    . '<td><span class="value">' . number_format(count($reportData)) . '</span><span class="label">Total Isi Tinta</span></td>'
    . '<td><span class="value">' . number_format(count($userStats)) . '</span><span class="label">Total User</span></td>'
    . '<td><span class="value">' . number_format(count($performerStats)) . '</span><span class="label">Total Pelaksana</span></td>'
    . '<td><span class="value">' . pdfText($topPerformer['nama'] ?? '-') . '</span><span class="label">Pelaksana Terbanyak</span></td>'
    . '</tr></table>';

$html .= '<h2>Grafik User</h2>';
$html .= '<div class="chart-panel">';
$html .= '<table class="chart-stats"><tr>'
    . '<td><span class="value">' . number_format(count($reportData)) . '</span><span class="label">Total Isi Tinta</span></td>'
    . '<td class="green"><span class="value">' . pdfText($overallAvgInterval . ' hari') . '</span><span class="label">Rata-rata Interval</span></td>'
    . '</tr></table>';
$html .= buildComboChartImage($userChartLabels, $userChartTotals, $userChartAvgIntervals, 'Total Isi Tinta', 'Rata-rata Interval (hari)', '#1f2937');
$html .= '</div>';

$html .= '<h2>Statistik Pelaksana</h2>';
$html .= '<table class="summary"><tr>'
    . '<td><span class="value">' . pdfText($topPerformer['nama'] ?? '-') . '</span><span class="label">Pelaksana Terbanyak</span></td>'
    . '<td><span class="value">' . number_format((int) ($topPerformer['total'] ?? 0)) . 'x</span><span class="label">Total Dikerjakan</span></td>'
    . '<td><span class="value">' . number_format(count($performerStats)) . '</span><span class="label">Total Pelaksana</span></td>'
    . '<td><span class="value">' . number_format(isset($topPerformer['users']) ? count($topPerformer['users']) : 0) . '</span><span class="label">User Top Pelaksana</span></td>'
    . '</tr></table>';

$html .= '<h2>Grafik Ranking Pelaksana Isi Tinta</h2>';
$html .= '<div class="chart-panel">';
$html .= buildComboChartImage($performerChartLabels, $performerChartTotals, $performerChartUsers, 'Total Isi Tinta', 'Jumlah User Ditangani', '#2f4858');
$html .= '</div>';

$html .= '<h2>Ranking Pelaksana Isi Tinta</h2>';
$html .= '<table><thead><tr><th style="width:5%">No</th><th style="width:28%">Dikerjakan Oleh</th><th style="width:10%">Total</th><th style="width:57%">User Yang Pernah Ditangani</th></tr></thead><tbody>';
if (empty($performerStats)) {
    $html .= '<tr><td colspan="4" class="empty">Tidak ada data.</td></tr>';
} else {
    foreach ($performerStats as $index => $performerRow) {
        $html .= '<tr class="' . (($index + 1) % 2 === 0 ? 'even' : 'odd') . '">'
            . '<td class="num">' . ($index + 1) . '</td>'
            . '<td>' . pdfText($performerRow['nama']) . '</td>'
            . '<td class="num">' . number_format((int) $performerRow['total']) . '</td>'
            . '<td>' . pdfText(implode(', ', array_values($performerRow['users']))) . '</td>'
            . '</tr>';
    }
}
$html .= '</tbody></table>';

$html .= '<div class="page-break"></div><h2>Record Isi Tinta</h2>';
$html .= '<table><thead><tr>'
    . '<th style="width:4%">No</th>'
    . '<th style="width:12%">Tanggal Isi Tinta</th>'
    . '<th style="width:20%">Nama Lengkap</th>'
    . '<th style="width:13%">Kode Asset</th>'
    . '<th style="width:10%">Jenis Tinta</th>'
    . '<th style="width:18%">Dikerjakan Oleh</th>'
    . '<th style="width:23%">Log Reminder</th>'
    . '</tr></thead><tbody>';

if (empty($reportData)) {
    $html .= '<tr><td colspan="7" class="empty">Tidak ada data sesuai filter.</td></tr>';
} else {
    foreach ($reportData as $index => $row) {
        $html .= '<tr class="' . (($index + 1) % 2 === 0 ? 'even' : 'odd') . '">'
            . '<td class="num">' . ($index + 1) . '</td>'
            . '<td>' . pdfText(pdfDate($row['created_at'] ?? null)) . '</td>'
            . '<td>' . pdfText($row['nama_lengkap'] ?? '-') . '</td>'
            . '<td>' . pdfText($row['kode_asset_seq'] ?? '-') . '</td>'
            . '<td>' . pdfText($row['jenis_tinta'] ?? '-') . '</td>'
            . '<td>' . pdfText($row['dikerjakan_oleh'] ?? '-') . '</td>'
            . '<td>' . pdfText($row['late_fill_log'] ?? '-') . '</td>'
            . '</tr>';
    }
}
$html .= '</tbody></table>';

$mpdf->WriteHTML($html);

sqlsrv_close($conn);
gc_collect_cycles();

$filename = 'report_isi_tinta_' . date('Ymd_His') . '.pdf';
$mpdf->Output($filename, \Mpdf\Output\Destination::INLINE);
exit;
