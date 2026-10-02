<?php
require_once '../../vendor/autoload.php';
require_once '../../koneksi.php';

use Dompdf\Dompdf;

date_default_timezone_set('Asia/Jakarta');

function hpdf($value)
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function monthOrderPdf($monthName)
{
    static $months = [
        'Januari' => 1,
        'Februari' => 2,
        'Maret' => 3,
        'April' => 4,
        'Mei' => 5,
        'Juni' => 6,
        'Juli' => 7,
        'Agustus' => 8,
        'September' => 9,
        'Oktober' => 10,
        'November' => 11,
        'Desember' => 12
    ];

    return $months[$monthName] ?? 13;
}

function renderChartSection($labels, $series)
{
    $width = 1100;
    $height = 360;
    $left = 70;
    $right = 20;
    $top = 35;
    $bottom = 65;
    $plotWidth = $width - $left - $right;
    $plotHeight = $height - $top - $bottom;
    $maxValue = 0;
    foreach ($series as $item) {
        foreach ($item['values'] as $value) {
            $maxValue = max($maxValue, (int) $value);
        }
    }
    if ($maxValue <= 0) {
        $maxValue = 1;
    }
    $gridSteps = 5;
    $roundedMax = (int) ceil($maxValue / $gridSteps);
    $roundedMax = max(1, $roundedMax) * $gridSteps;
    $groupWidth = $plotWidth / max(1, count($labels));
    $barWidth = min(22, max(10, ($groupWidth - 12) / 3));
    $seriesColors = [
        '#4472c4',
        '#ed7d31',
        '#a5a5a5'
    ];

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">';
    $svg .= '<rect width="100%" height="100%" fill="#ffffff"/>';
    $svg .= '<text x="' . ($width / 2) . '" y="24" text-anchor="middle" font-size="18" font-weight="700" fill="#2d4d73">GRAFIK PEMAKAIAN INTERNET</text>';

    for ($step = 0; $step <= $gridSteps; $step++) {
        $y = $top + ($plotHeight / $gridSteps) * $step;
        $value = (int) round($roundedMax - (($roundedMax / $gridSteps) * $step));
        $svg .= '<line x1="' . $left . '" y1="' . $y . '" x2="' . ($width - $right) . '" y2="' . $y . '" stroke="#d9e0e8" stroke-width="1"/>';
        $svg .= '<text x="' . ($left - 8) . '" y="' . ($y + 4) . '" text-anchor="end" font-size="10" fill="#666">' . $value . '</text>';
    }

    $svg .= '<line x1="' . $left . '" y1="' . $top . '" x2="' . $left . '" y2="' . ($top + $plotHeight) . '" stroke="#c7d1db" stroke-width="1"/>';
    $svg .= '<line x1="' . $left . '" y1="' . ($top + $plotHeight) . '" x2="' . ($width - $right) . '" y2="' . ($top + $plotHeight) . '" stroke="#c7d1db" stroke-width="1"/>';

    foreach ($labels as $index => $label) {
        $groupStartX = $left + ($groupWidth * $index) + (($groupWidth - (($barWidth * 3) + 10)) / 2);
        foreach ($series as $seriesIndex => $item) {
            $value = (int) ($item['values'][$index] ?? 0);
            $barHeight = $value > 0 ? ($value / $roundedMax) * ($plotHeight - 8) : 0;
            $x = $groupStartX + ($seriesIndex * ($barWidth + 4));
            $y = $top + $plotHeight - $barHeight;
            $svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . $barWidth . '" height="' . $barHeight . '" fill="' . $seriesColors[$seriesIndex] . '"/>';
            if ($value > 0) {
                $svg .= '<text x="' . ($x + ($barWidth / 2)) . '" y="' . max(12, $y - 4) . '" text-anchor="middle" font-size="8" fill="#666">' . $value . '</text>';
            }
        }
        $svg .= '<text x="' . ($left + ($groupWidth * $index) + ($groupWidth / 2)) . '" y="' . ($height - 28) . '" text-anchor="middle" font-size="10" fill="#555">' . hpdf($label) . '</text>';
    }

    $legendY = $height - 10;
    $legendX = ($width / 2) - 220;
    foreach ($series as $seriesIndex => $item) {
        $x = $legendX + ($seriesIndex * 180);
        $svg .= '<rect x="' . $x . '" y="' . ($legendY - 10) . '" width="14" height="10" fill="' . $seriesColors[$seriesIndex] . '"/>';
        $svg .= '<text x="' . ($x + 20) . '" y="' . $legendY . '" font-size="10" fill="#555">' . hpdf($item['label']) . '</text>';
    }

    $svg .= '</svg>';
    $svgData = 'data:image/svg+xml;base64,' . base64_encode($svg);

    return '<div class="chart-title"></div><div class="chart-image-wrap"><img src="' . $svgData . '" class="chart-image" alt="Grafik Pemakaian Internet"></div>';
}

$providerId = (int) ($_GET['provider_id'] ?? 0);
$locationId = (int) ($_GET['location_id'] ?? 0);
$mode = strtolower(trim((string) ($_GET['mode'] ?? 'view')));
$attachment = $mode === 'export';

if ($providerId <= 0) {
    die('Provider tidak valid.');
}

$providerStmt = sqlsrv_query($conn, "SELECT TOP 1 id, provider_name FROM dbo.internet_usage_providers WHERE id = ?", [$providerId]);
$provider = $providerStmt ? sqlsrv_fetch_array($providerStmt, SQLSRV_FETCH_ASSOC) : null;
if ($providerStmt) {
    sqlsrv_free_stmt($providerStmt);
}
if (!$provider) {
    die('Provider tidak ditemukan.');
}

$locationName = 'Semua Lokasi';
if ($locationId > 0) {
    $locationStmt = sqlsrv_query($conn, "SELECT TOP 1 id, location_name FROM dbo.internet_usage_locations WHERE id = ? AND provider_id = ?", [$locationId, $providerId]);
    $location = $locationStmt ? sqlsrv_fetch_array($locationStmt, SQLSRV_FETCH_ASSOC) : null;
    if ($locationStmt) {
        sqlsrv_free_stmt($locationStmt);
    }
    if (!$location) {
        die('Lokasi tidak ditemukan.');
    }
    $locationName = (string) $location['location_name'];
}

$sql = "
    SELECT
        r.id,
        r.month_name,
        r.total_data_used_gb,
        r.total_usage_hours,
        r.average_daily_usage_gb,
        r.keterangan,
        l.location_name
    FROM dbo.internet_usage_reports r
    LEFT JOIN dbo.internet_usage_locations l ON l.id = r.location_id
    WHERE r.provider_id = ?
";
$params = [$providerId];
if ($locationId > 0) {
    $sql .= " AND r.location_id = ?";
    $params[] = $locationId;
}
$sql .= "
    ORDER BY
        CASE r.month_name
            WHEN 'Januari' THEN 1
            WHEN 'Februari' THEN 2
            WHEN 'Maret' THEN 3
            WHEN 'April' THEN 4
            WHEN 'Mei' THEN 5
            WHEN 'Juni' THEN 6
            WHEN 'Juli' THEN 7
            WHEN 'Agustus' THEN 8
            WHEN 'September' THEN 9
            WHEN 'Oktober' THEN 10
            WHEN 'November' THEN 11
            WHEN 'Desember' THEN 12
            ELSE 13
        END ASC,
        r.id ASC
";

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    die('Gagal mengambil data pemakaian.');
}

$rows = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $row;
}
sqlsrv_free_stmt($stmt);

$months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$chartData = array_fill(0, 12, 0);
$chartHours = array_fill(0, 12, 0);
$chartAverage = array_fill(0, 12, 0);

foreach ($rows as $row) {
    $idx = monthOrderPdf($row['month_name']) - 1;
    if ($idx >= 0 && $idx < 12) {
        $chartData[$idx] += (int) $row['total_data_used_gb'];
        $chartHours[$idx] += (int) $row['total_usage_hours'];
        $chartAverage[$idx] += (int) $row['average_daily_usage_gb'];
    }
}

$totalData = array_sum(array_map(static function ($item) { return (int) $item['total_data_used_gb']; }, $rows));
$totalHours = array_sum(array_map(static function ($item) { return (int) $item['total_usage_hours']; }, $rows));
$totalAverage = array_sum(array_map(static function ($item) { return (int) $item['average_daily_usage_gb']; }, $rows));

$chartHtml = renderChartSection($months, [
    ['label' => 'Total Data Digunakan (GB)', 'class' => 'bar-blue', 'values' => $chartData],
    ['label' => 'Total Waktu Penggunaan (Jam)', 'class' => 'bar-green', 'values' => $chartHours],
    ['label' => 'Rata-rata Penggunaan per Hari (GB)', 'class' => 'bar-yellow', 'values' => $chartAverage],
]);

$html = '
<style>
    @page { margin: 16px 18px; }
    body { font-family: DejaVu Sans, Arial, sans-serif; color: #222; font-size: 11px; }
    .title { font-size: 22px; font-weight: bold; margin-bottom: 6px; }
    .meta { margin-bottom: 12px; line-height: 1.5; }
    .chart-panel { border: 1px solid #d7dce2; padding: 12px 14px 10px; margin-bottom: 14px; page-break-inside: avoid; }
    .chart-title { text-align: center; font-weight: bold; font-size: 18px; color: #2d4d73; margin-bottom: 8px; }
    .chart-image-wrap { width: 100%; text-align: center; }
    .chart-image { width: 100%; height: auto; display: block; }
    table.report { width: 100%; border-collapse: collapse; page-break-inside: auto; }
    table.report th, table.report td { border: 1px solid #444; padding: 6px; }
    table.report th { background: #eef2f6; text-align: center; }
    table.report td.center { text-align: center; }
    table.report tfoot td { font-weight: bold; background: #f4f6f8; }
</style>

<div class="title">Laporan Pemakaian Internet</div>
<div class="meta">
    <div>Provider: ' . hpdf($provider['provider_name']) . '</div>
    <div>Sheet Lokasi: ' . hpdf($locationName) . '</div>
    <div>Dicetak: ' . date('d-m-Y H:i:s') . '</div>
</div>

<div class="chart-panel">' . $chartHtml . '</div>

<table class="report">
    <thead>
        <tr>
            <th style="width:38px;">No</th>
            <th>Nama Lokasi</th>
            <th>Bulan</th>
            <th>Total Data Digunakan (GB)</th>
            <th>Total Waktu Penggunaan (Jam)</th>
            <th>Rata-rata Penggunaan per Hari (GB)</th>
            <th>Keterangan</th>
        </tr>
    </thead>
    <tbody>';

if (empty($rows)) {
    $html .= '<tr><td colspan="7" class="center">Belum ada data pemakaian.</td></tr>';
} else {
    foreach ($rows as $index => $row) {
        $html .= '<tr>';
        $html .= '<td class="center">' . ($index + 1) . '</td>';
        $html .= '<td>' . hpdf($row['location_name'] ?: $locationName) . '</td>';
        $html .= '<td>' . hpdf($row['month_name']) . '</td>';
        $html .= '<td class="center">' . (int) $row['total_data_used_gb'] . '</td>';
        $html .= '<td class="center">' . (int) $row['total_usage_hours'] . '</td>';
        $html .= '<td class="center">' . (int) $row['average_daily_usage_gb'] . '</td>';
        $html .= '<td>' . hpdf($row['keterangan']) . '</td>';
        $html .= '</tr>';
    }
}

$html .= '
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3" class="center">Total</td>
            <td class="center">' . $totalData . '</td>
            <td class="center">' . $totalHours . '</td>
            <td class="center">' . $totalAverage . '</td>
            <td></td>
        </tr>
    </tfoot>
</table>';

$dompdf = new Dompdf(['isRemoteEnabled' => true]);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$safeProvider = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $provider['provider_name']);
$safeLocation = preg_replace('/[^A-Za-z0-9_-]+/', '_', $locationName);
$filename = 'LaporanPemakaianInternet_' . $safeProvider . '_' . $safeLocation . '.pdf';

$dompdf->stream($filename, ['Attachment' => $attachment]);
exit;
