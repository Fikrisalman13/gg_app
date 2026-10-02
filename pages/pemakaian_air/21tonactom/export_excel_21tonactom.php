<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';

$start = $_POST['start_date'] ?? date('Y-m-01');
$end = $_POST['end_date'] ?? date('Y-m-d');

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Laporan_21TonActom_' . date('Ymd_His') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

function fetchRows($conn, $table, $start, $end) {
    $sql = "SELECT tanggal, awal, ahir, total_pemakaian, pemakaianrata2perjam
            FROM dbo.$table
            WHERE tanggal BETWEEN ? AND ?
            ORDER BY tanggal ASC";
    $stmt = sqlsrv_query($conn, $sql, [$start, $end]);
    if ($stmt === false) {
        echo '<b>SQL error</b>: ' . htmlspecialchars(print_r(sqlsrv_errors(), true));
        exit;
    }
    $rows = [];
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $r;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    return $rows;
}

function calcSummary($rows) {
    $sumTotal = 0.0; $sumRata = 0.0; $cntTotal = 0; $cntRata = 0;
    foreach ($rows as $r) {
        if (is_numeric($r['total_pemakaian'])) { $sumTotal += (float)$r['total_pemakaian']; $cntTotal++; }
        if (is_numeric($r['pemakaianrata2perjam'])) { $sumRata += (float)$r['pemakaianrata2perjam']; $cntRata++; }
    }
    return [
        'sumTotal' => $sumTotal,
        'sumRata' => $sumRata,
        'avgTotal' => $cntTotal ? ($sumTotal / $cntTotal) : null,
        'avgRata' => $cntRata ? ($sumRata / $cntRata) : null
    ];
}

$rowsAirBoiler = fetchRows($conn, 'air_boiler_actom', $start, $end);
$rowsSteam = fetchRows($conn, 'steam_boiler_actom', $start, $end);
$rowsAirAnalog = fetchRows($conn, 'air_analog_actom', $start, $end);
$rowsAnalogSteam = fetchRows($conn, 'analog_steam_actom', $start, $end);

$sumAirBoiler = calcSummary($rowsAirBoiler);
$sumSteam = calcSummary($rowsSteam);
$sumAirAnalog = calcSummary($rowsAirAnalog);
$sumAnalogSteam = calcSummary($rowsAnalogSteam);

$fmtNum = function($v){ if($v===null||$v==='') return ''; return is_numeric($v)?number_format((float)$v,2,'.',','):(string)$v; };
$fmtDay = function($v){ if($v instanceof DateTime) return $v->format('j'); return date('j', strtotime((string)$v)); };

$monthMap=['January'=>'JANUARI','February'=>'FEBRUARI','March'=>'MARET','April'=>'APRIL','May'=>'MEI','June'=>'JUNI','July'=>'JULI','August'=>'AGUSTUS','September'=>'SEPTEMBER','October'=>'OKTOBER','November'=>'NOVEMBER','December'=>'DESEMBER'];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));

$sections = [
    ['title' => 'AIR BOILER 21TON ACTOM', 'unit' => 'M3', 'rows' => $rowsAirBoiler, 'summary' => $sumAirBoiler],
    ['title' => 'STEAM BOILER 21TON ACTOM', 'unit' => 'TON', 'rows' => $rowsSteam, 'summary' => $sumSteam],
    ['title' => 'AIR ANALOG 21TON ACTOM', 'unit' => 'M3', 'rows' => $rowsAirAnalog, 'summary' => $sumAirAnalog],
    ['title' => 'ANALOG STEAM 21TON ACTOM', 'unit' => 'Ton', 'rows' => $rowsAnalogSteam, 'summary' => $sumAnalogSteam],
];

$maxRows = 1;
foreach ($sections as $section) {
    $maxRows = max($maxRows, count($section['rows']));
}

function excelCell($value, $class = '', $tag = 'td', $colspan = 1) {
    $classAttr = $class !== '' ? " class='{$class}'" : '';
    $colspanAttr = $colspan > 1 ? " colspan='{$colspan}'" : '';
    echo "<{$tag}{$classAttr}{$colspanAttr}>" . htmlspecialchars((string)$value) . "</{$tag}>";
}

function excelGap() {
    echo "<td class='gap'></td>";
}

function renderSectionHeaderRow($sections, $valueKey, $class, $monthLabel = '') {
    echo "<tr>";
    $last = count($sections) - 1;
    foreach ($sections as $idx => $section) {
        $value = $valueKey === 'month' ? $monthLabel : $section[$valueKey];
        excelCell($value, $class, 'th', 5);
        if ($idx !== $last) excelGap();
    }
    echo "</tr>";
}

function renderColumnHeaderRow($sections) {
    echo "<tr>";
    $last = count($sections) - 1;
    foreach ($sections as $idx => $section) {
        excelCell('TANGGAL', 'sub w-date', 'th');
        excelCell('AWAL', 'sub w-num', 'th');
        excelCell('AKHIR', 'sub w-num', 'th');
        excelCell('TOTAL PEMAKAIAN', 'sub w-total', 'th');
        excelCell('PEMAKAIAN RATA RATA PER JAM', 'sub w-rata', 'th');
        if ($idx !== $last) excelGap();
    }
    echo "</tr>";
}

function renderUnitRow($sections) {
    echo "<tr>";
    $last = count($sections) - 1;
    foreach ($sections as $idx => $section) {
        excelCell('', 'unit', 'th');
        excelCell($section['unit'], 'unit', 'th');
        excelCell($section['unit'], 'unit', 'th');
        excelCell($section['unit'], 'unit', 'th');
        excelCell($section['unit'], 'unit', 'th');
        if ($idx !== $last) excelGap();
    }
    echo "</tr>";
}

function renderDataCells($row, $sectionRows, $rowIndex, $fmtNum, $fmtDay) {
    if (!$row) {
        if ($rowIndex === 0 && empty($sectionRows)) {
            excelCell('Tidak ada data', 'empty', 'td', 5);
            return;
        }
        for ($i = 0; $i < 5; $i++) excelCell('', 'blank');
        return;
    }

    excelCell($fmtDay($row['tanggal'] ?? ''), 'day');
    excelCell($fmtNum($row['awal'] ?? null), 'num2');
    excelCell($fmtNum($row['ahir'] ?? null), 'num2');
    excelCell($fmtNum($row['total_pemakaian'] ?? null), 'num2 total');
    excelCell($fmtNum($row['pemakaianrata2perjam'] ?? null), 'num2');
}

function renderSummaryCells($section, $kind, $fmtNum) {
    if (empty($section['rows'])) {
        excelCell('', 'sum empty-sum', 'td', 5);
        return;
    }

    $summary = $section['summary'];
    $label = $kind === 'total' ? 'TOTAL' : 'RATA-RATA';
    $totalValue = $kind === 'total' ? $summary['sumTotal'] : $summary['avgTotal'];
    $rataValue = $kind === 'total' ? $summary['sumRata'] : $summary['avgRata'];

    excelCell($label, 'sum');
    excelCell('', 'sum');
    excelCell('', 'sum');
    excelCell($fmtNum($totalValue), 'sum num2');
    excelCell($fmtNum($rataValue), 'sum num2');
}

echo "<html><head><meta charset='UTF-8'><style>
body{margin:0}
table.report-grid{border-collapse:collapse;font-family:Arial,sans-serif;font-size:11px}
.report-grid th,.report-grid td{border:1px solid #000;padding:4px;text-align:center;vertical-align:middle;white-space:nowrap}
.top{background:#afc0d6;font-weight:bold}
.title{font-size:18px;height:30px}
.month{font-size:24px;height:26px}
.sub{background:#cfdeef;font-weight:bold}
.unit{background:#f3e6e6;font-weight:bold}
.total{background:#b7c9de}
.sum{background:#d9d6c4;font-weight:bold}
.day,.num2{font-weight:bold}
.num2{mso-number-format:'0.00'}
.w-date{width:58px}
.w-num{width:82px}
.w-total{width:112px}
.w-rata{width:150px}
.gap{border:none !important;width:12px;background:#fff}
.empty{height:20px}
.blank{background:#fff}
.empty-sum{background:#fff;border:none !important}
</style></head><body>";

echo "<table class='report-grid'>";
renderSectionHeaderRow($sections, 'title', 'top title');
renderSectionHeaderRow($sections, 'month', 'top month', $monthLabel);
renderColumnHeaderRow($sections);
renderUnitRow($sections);

$last = count($sections) - 1;
for ($i = 0; $i < $maxRows; $i++) {
    echo "<tr>";
    foreach ($sections as $idx => $section) {
        renderDataCells($section['rows'][$i] ?? null, $section['rows'], $i, $fmtNum, $fmtDay);
        if ($idx !== $last) excelGap();
    }
    echo "</tr>";
}

foreach (['total', 'average'] as $summaryType) {
    echo "<tr>";
    foreach ($sections as $idx => $section) {
        renderSummaryCells($section, $summaryType, $fmtNum);
        if ($idx !== $last) excelGap();
    }
    echo "</tr>";
}
echo "</table>";

echo "</body></html>";
