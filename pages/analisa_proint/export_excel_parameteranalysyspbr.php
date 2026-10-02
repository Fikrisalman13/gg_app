<?php
session_start();
ob_start();

include '../../koneksi.php';
include '../../koneksi3.php';

if (!isset($_SESSION['UserName'])) {
    die("Silakan login terlebih dahulu!");
}

date_default_timezone_set('Asia/Jakarta');

if (!$conn3) {
    die("Koneksi ke database gagal");
}

// PDO SQL Server untuk data sensor MonitoringMesinDataAb
$pdoSensor = null;
try {
    include '../../koneksi.php'; // re-use $serverName, $connectionOptions
    $dsn = "sqlsrv:Server={$serverName};Database={$connectionOptions['Database']}";
    if (!empty($connectionOptions['TrustServerCertificate'])) {
        $dsn .= ';TrustServerCertificate=1';
    }
    $pdoSensor = new PDO($dsn, $connectionOptions['Uid'], $connectionOptions['PWD'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    // sensor data will be empty
}

$startDate = isset($_GET['startdate']) ? trim($_GET['startdate']) : date('Y-m-d');
$endDate = isset($_GET['enddate']) ? trim($_GET['enddate']) : date('Y-m-d');

if ($startDate === '' || $endDate === '') {
    die("Start Date dan End Date wajib diisi.");
}

if ($startDate > $endDate) {
    die("Start Date tidak boleh lebih besar dari End Date.");
}

function px_pbr_filter_date(string $date, bool $isEnd = false): ?DateTime {
    $date = trim($date);
    if ($date === '') return null;
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    if ($dt instanceof DateTime) {
        $dt->setTime($isEnd ? 23 : 0, $isEnd ? 59 : 0, $isEnd ? 59 : 0);
        return $dt;
    }
    $ts = strtotime($date);
    if ($ts === false) return null;
    $dt = (new DateTime())->setTimestamp($ts);
    $dt->setTime($isEnd ? 23 : 0, $isEnd ? 59 : 0, $isEnd ? 59 : 0);
    return $dt;
}
function px_pbr_parse_dt($date, $time): ?DateTime {
    $date = trim((string)$date); $time = trim((string)$time);
    if ($date === '' || $time === '') return null;
    $time = preg_replace('/\.\d+$/', '', $time);
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $time) ?: DateTime::createFromFormat('Y-m-d H:i', $time);
    if ($dt instanceof DateTime) return $dt;
    $dateOnly = substr($date, 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOnly)) {
        $dateDt = DateTime::createFromFormat('Y-m-d H:i:s', $date) ?: DateTime::createFromFormat('Y-m-d H:i', $date);
        if ($dateDt) $dateOnly = $dateDt->format('Y-m-d');
    }
    $combined = $dateOnly . ' ' . $time;
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $combined) ?: DateTime::createFromFormat('Y-m-d H:i', $combined);
    if ($dt instanceof DateTime) return $dt;
    $ts = strtotime($combined);
    return $ts !== false ? (new DateTime())->setTimestamp($ts) : null;
}
function px_pbr_norm(string $v): string { $v = str_replace(["\r", "\n", "\t"], ' ', $v); $v = trim($v, " \t\n\r\0\x0B\"'"); $v = preg_replace('/\s+/u', ' ', $v ?? ''); return strtolower(trim((string)$v)); }
function px_pbr_map(): array {
    return [
        'actual_1' => ['[a] production speed [0.1m/min]'],
        'actual_2' => ['[b] production speed [0.1m/min]'],
        'conveyor_speed' => ['conveyor speed [0.1m/min]'],
        'cloth_volume' => ['cloth volume [m]'],
        'a_washer_1' => ['[a] no.1washer [0.1℃]', '[a] no.1washer [0.1°c]'],
        'a_washer_2' => ['[a] no.2washer [0.1℃]', '[a] no.2washer [0.1°c]'],
        'a_washer_3' => ['[a] no.3washer [0.1℃]', '[a] no.3washer [0.1°c]'],
        'a_washer_4' => ['[a] no.4washer [0.1℃]', '[a] no.4washer [0.1°c]'],
        'a_washer_5' => ['[a] no.5washer [0.1℃]', '[a] no.5washer [0.1°c]'],
        'top_part' => ['top part [0.1℃]', 'top part (°c)'],
        'boiling_box' => ['boiling box [0.1℃]', 'boiling box (°c)'],
        'b_washer_1' => ['[b] no.1washer [0.1℃]', '[b] no.1washer [0.1°c]'],
        'b_washer_2' => ['[b] no.2washer [0.1℃]', '[b] no.2washer [0.1°c]'],
        'b_washer_3' => ['[b] no.3washer [0.1℃]', '[b] no.3washer [0.1°c]'],
        'b_washer_4' => ['[b] no.4washer [0.1℃]', '[b] no.4washer [0.1°c]'],
        'b_washer_5' => ['[b] no.5washer [0.1℃]', '[b] no.5washer [0.1°c]'],
        'b_washer_6' => ['[b] no.6washer [0.1℃]', '[b] no.6washer [0.1°c]'],
        'hot_water_tank' => ['hot water tank [0.1℃]', 'temp hot water tank (°c)'],
        'no4_dryer' => ['[b] no.4 dryer [0.1℃]', 'temp [no.4] dryer (°c)'],
        'front_main_water' => ['[front] main water [0.1m3/h]'],
        'front_main_steam' => ['[front] main steam [kg/h]'],
        'back_main_water' => ['[back] main water [0.1m3/h]'],
        'back_main_steam' => ['[back] main steam [kg/h]'],
        'watt_meter' => ['watt meter [kw]'],
    ];
}
function px_pbr_load_sensor(PDO $conn, DateTime $start, DateTime $end): array {
    $recordDateExpr = "COALESCE(
        RecordDate,
        TRY_CONVERT(datetime2, RecordDateText, 126),
        TRY_CONVERT(datetime2, RecordDateText, 120),
        TRY_CONVERT(datetime2, RecordDateText, 103),
        TRY_CONVERT(datetime2, RecordDateText, 110),
        TRY_CONVERT(datetime2, RecordDateText, 101)
    )";
    $sql = "SELECT CONVERT(VARCHAR(19), {$recordDateExpr}, 120) AS RD, RowJson FROM dbo.MonitoringMesinDataAb WHERE {$recordDateExpr} BETWEEN :start AND :end ORDER BY {$recordDateExpr} ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['start' => $start->format('Y-m-d H:i:s'), 'end' => $end->format('Y-m-d H:i:s')]);
    $map = px_pbr_map(); $records = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $ts = strtotime((string)($row['RD'] ?? '')); $data = json_decode((string)($row['RowJson'] ?? ''), true);
        if ($ts === false || !is_array($data)) continue;
        $normalized = []; foreach ($data as $k => $v) $normalized[px_pbr_norm((string)$k)] = $v;
        $mapped = []; foreach ($map as $alias => $candidates) { $mapped[$alias] = null; foreach ($candidates as $candidate) { if (isset($normalized[$candidate]) && $normalized[$candidate] !== '' && is_numeric($normalized[$candidate])) { $mapped[$alias] = (float)$normalized[$candidate]; break; } } }
        $records[] = ['ts' => $ts, 'data' => $mapped];
    }
    return $records;
}
function px_pbr_avg(array $records, DateTime $start, DateTime $end): array {
    $map = px_pbr_map(); $avg = array_fill_keys(array_keys($map), null); $sum = array_fill_keys(array_keys($map), 0.0); $count = array_fill_keys(array_keys($map), 0);
    $startTs = $start->getTimestamp(); $endTs = $end->getTimestamp();
    foreach ($records as $rec) {
        if ($rec['ts'] < $startTs || $rec['ts'] > $endTs) continue;
        foreach ($rec['data'] as $alias => $value) { if ($value === null) continue; $sum[$alias] += $value; $count[$alias]++; }
    }
    foreach ($avg as $alias => $_) $avg[$alias] = $count[$alias] > 0 ? $sum[$alias] / $count[$alias] : null;
    return $avg;
}

// --- Main Query ---
try {
    $query = <<<'SQL'
WITH rtg AS (
    SELECT r.productionhdid, r.productionrtgid, r.rtgmsid, r.famasterid, r.prdqty, r.prdstdqty, r.startdate, r.enddate, r.starttime, r.endtime
    FROM pdproductionrtg r
    JOIN pdrtgms rm ON rm.rtgmsid = r.rtgmsid
    WHERE r.startdate >= :startdate
      AND r.startdate <= :enddate
      AND rm.rtgname ILIKE '%PBR%'
),
ps AS (
    SELECT s.productionrtgid, MAX(s.prdtemp) AS prdtemp, MAX(s.prdtempfinish) AS prdtempfinish, MAX(s.prdspeed) AS prdspeed
    FROM pdproductionsum s
    JOIN rtg r ON r.productionrtgid = s.productionrtgid
    GROUP BY s.productionrtgid
),
hd AS (
    SELECT productionhdid, prdnmbr, colorid, prodid, prodname FROM pdproductionhd
),
rtgms AS (
    SELECT rtgmsid, rtgname FROM pdrtgms
),
article AS (
    SELECT m.productionhdid, MAX(m.prodname) AS prodname
    FROM pdresultmat m
    JOIN rtg r ON r.productionhdid = m.productionhdid
    WHERE m.fgusedtype = 'A'
    GROUP BY m.productionhdid
)
SELECT
    r.startdate AS "Start Date",
    r.starttime AS "Start Time",
    r.enddate AS "End Date",
    r.endtime AS "End Time",
    r.prdqty AS "Actual Production",
    r.prdstdqty AS "Production Standard (DB)",
    rm.rtgname AS "Routing Name",
    h.prdnmbr AS "CP No",
    a.prodname AS "Article",
    ps.prdspeed AS "Speed"
FROM rtg r
LEFT JOIN ps ON ps.productionrtgid = r.productionrtgid
LEFT JOIN hd h ON h.productionhdid = r.productionhdid
LEFT JOIN rtgms rm ON rm.rtgmsid = r.rtgmsid
LEFT JOIN article a ON a.productionhdid = r.productionhdid
ORDER BY r.startdate
SQL;
    $startFilter = px_pbr_filter_date($startDate, false);
    $endFilter = px_pbr_filter_date($endDate, true);
    if (!$startFilter || !$endFilter) {
        throw new RuntimeException('Format tanggal filter tidak valid.');
    }
    $stmt = $conn3->prepare($query);
    $stmt->bindValue(':startdate', $startFilter->format('Y-m-d H:i:s'));
    $stmt->bindValue(':enddate', $endFilter->format('Y-m-d H:i:s'));
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sensorRecords = [];
    if (!empty($results)) {
        $starts = []; $ends = [];
        foreach ($results as $row) {
            $sdt = px_pbr_parse_dt($row['Start Date'] ?? '', $row['Start Time'] ?? '');
            $edt = px_pbr_parse_dt($row['End Date'] ?? '', $row['End Time'] ?? '');
            if ($sdt) $starts[] = $sdt;
            if ($edt) $ends[] = $edt;
        }
        if ($starts && $ends && $pdoSensor) $sensorRecords = px_pbr_load_sensor($pdoSensor, min($starts), max($ends));
    }

    foreach ($results as &$row) {
        $row['_start_dt'] = px_pbr_parse_dt($row['Start Date'] ?? '', $row['Start Time'] ?? '');
        $row['_end_dt'] = px_pbr_parse_dt($row['End Date'] ?? '', $row['End Time'] ?? '');
        $row['Sensor'] = ($row['_start_dt'] && $row['_end_dt']) ? px_pbr_avg($sensorRecords, $row['_start_dt'], $row['_end_dt']) : array_fill_keys(array_keys(px_pbr_map()), null);
    }
    unset($row);
} catch (Throwable $e) {
    die("Error: " . $e->getMessage());
}

function px_fmt($v): string { return $v === null || $v === '' ? '' : htmlspecialchars((string)$v); }
function px_num($v, int $d = 2): string { return $v === null || $v === '' ? '' : htmlspecialchars(number_format((float)$v, $d, '.', '')); }

$filename = 'Parameter_Analysis_PBR_' . date('Ymd_His') . '.xls';
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=" . $filename);
header("Pragma: no-cache");
header("Expires: 0");

$colCount = 37;
?>
<style>
.th-purple { background-color: #d5a6e0; color: #333; font-weight: bold; }
.th-blue   { background-color: #a8d8ff; color: #333; font-weight: bold; }
.th-red    { background-color: #f5a0a0; color: #333; font-weight: bold; }
.th-yellow { background-color: #ffec80; color: #333; font-weight: bold; }
</style>
<table border='1'>
<tr><td colspan="<?= $colCount ?>" style='font-weight:bold; text-align:center; background-color:#d9edf7; font-size:16px;'>PARAMETER ANALYSIS PBR REPORT</td></tr>
<tr><td colspan="<?= $colCount ?>" style='font-weight:bold; background-color:#f5f5f5;'>Periode: <?= htmlspecialchars($startDate) ?> s/d <?= htmlspecialchars($endDate) ?> | Export: <?= date('d/m/Y H:i') ?> | User: <?= htmlspecialchars($_SESSION['UserName'] ?? 'Unknown') ?></td></tr>
<tr><td colspan="<?= $colCount ?>" style='font-size:11px;'>
<span style='background-color:#d5a6e0; padding:1px 4px; margin-right:4px;'><b>Proint</b></span>
<span style='background-color:#a8d8ff; padding:1px 4px; margin-right:4px;'><b>Rumus</b></span>
<span style='background-color:#f5a0a0; padding:1px 4px; margin-right:4px;'><b>PBR Aplikasi</b></span>
<span style='background-color:#ffec80; padding:1px 4px; margin-right:4px;'><b>Tanda Tanya</b></span>
</td></tr>
<tr><td colspan="<?= $colCount ?>" style='font-size:10px; color:#666;'>Kuning: Standard = speed pdproductionsum.prdspeed; Production Standard (M) = pdproductionrtg.prdstdqty; Efficiency = Actual Production / Production Standard</td></tr>
<tr style='background-color:#f8f9fa; font-weight:bold; text-align:center;'>
    <th rowspan="2">No</th>
    <th rowspan="2">Input Date</th>
    <th rowspan="2">CP No</th>
    <th rowspan="2">Routing</th>
    <th rowspan="2">Article</th>
    <th colspan="3">Process Date</th>
    <th rowspan="2">Time (min)</th>
    <th colspan="4">Speed</th>
    <th rowspan="2" class="th-purple">Actual Production (M)</th>
    <th rowspan="2" class="th-yellow">Production Standard (M)</th>
    <th rowspan="2" class="th-yellow">Efficiency</th>
    <th rowspan="2" class="th-red">Conveyor Speed</th>
    <th rowspan="2" class="th-red">Cloth Volume [m]</th>
    <th colspan="5" class="th-red">Temperature A</th>
    <th rowspan="2" class="th-red">Top Part (°C)</th>
    <th rowspan="2" class="th-red">Boiling Box (°C)</th>
    <th colspan="6" class="th-red">Temperature B</th>
    <th rowspan="2" class="th-red">Temp Hot Water Tank (°C)</th>
    <th rowspan="2" class="th-red">Temp [No.4] Dryer (°C)</th>
    <th rowspan="2" class="th-red">Front Main Water (m3/h)</th>
    <th rowspan="2" class="th-red">Front Main Steam (kg/h)</th>
    <th rowspan="2" class="th-red">Back Main Water (m3/h)</th>
    <th rowspan="2" class="th-red">Back Main Steam (kg/h)</th>
    <th rowspan="2" class="th-red">Watt Meter (kW)</th>
</tr>
<tr style='background-color:#f8f9fa; font-weight:bold; text-align:center;'>
    <th>Date</th><th>Start Time</th><th>End Time</th>
    <th class="th-red">Actual 1</th>
    <th class="th-red">Actual 2</th>
    <th class="th-blue">Rata-Rata</th>
    <th class="th-yellow">Standard</th>
    <th class="th-red">Washer 1</th><th class="th-red">Washer 2</th><th class="th-red">Washer 3</th><th class="th-red">Washer 4</th><th class="th-red">Washer 5</th>
    <th class="th-red">Washer 1</th><th class="th-red">Washer 2</th><th class="th-red">Washer 3</th><th class="th-red">Washer 4</th><th class="th-red">Washer 5</th><th class="th-red">Washer 6</th>
</tr>
<?php if (!empty($results)): $no = 1; foreach ($results as $row):
    $sensor = $row['Sensor'] ?? [];
    $act1 = $sensor['actual_1'] ?? null;
    $act2 = $sensor['actual_2'] ?? null;
    $speedAvg = ($act1 !== null && $act2 !== null) ? ($act1 + $act2) / 2 : null;
    $timeMinutes = ($row['_start_dt'] && $row['_end_dt']) ? (($row['_end_dt']->getTimestamp() - $row['_start_dt']->getTimestamp()) / 60) : null;
    $speed = is_numeric($row['Speed'] ?? null) ? (float)$row['Speed'] : null;
    $actualProd = is_numeric($row['Actual Production'] ?? null) ? (float)$row['Actual Production'] : null;
    $prodStd = is_numeric($row['Production Standard (DB)'] ?? null) ? (float)$row['Production Standard (DB)'] : null;
    $efficiency = ($prodStd !== null && $prodStd > 0 && $actualProd !== null) ? $actualProd / $prodStd : null;
?>
<tr>
    <td style='text-align:center;'><?= $no++ ?></td>
    <td><?= px_fmt($row['Start Date'] ?? '') ?></td>
    <td><?= px_fmt($row['CP No'] ?? '') ?></td>
    <td><?= px_fmt($row['Routing Name'] ?? '') ?></td>
    <td><?= px_fmt($row['Article'] ?? '') ?></td>
    <td><?= px_fmt($row['Start Date'] ?? '') ?></td>
    <td><?= px_fmt($row['Start Time'] ?? '') ?></td>
    <td><?= px_fmt($row['End Time'] ?? '') ?></td>
    <td style='text-align:right;'><?= $timeMinutes === null ? '' : number_format($timeMinutes, 2, '.', '') ?></td>
    <td style='text-align:right;'><?= px_num($act1) ?></td>
    <td style='text-align:right;'><?= px_num($act2) ?></td>
    <td style='text-align:right;'><?= $speedAvg === null ? '' : number_format($speedAvg, 2, '.', '') ?></td>
    <td style='text-align:right;'><?= $speed === null ? '' : number_format($speed, 2, '.', '') ?></td>
    <td style='text-align:right;'><?= px_num($actualProd) ?></td>
    <td style='text-align:right;'><?= px_num($prodStd) ?></td>
    <td style='text-align:right;'><?= $efficiency === null ? '' : number_format($efficiency, 4, '.', '') ?></td>
    <td style='text-align:right;'><?= px_num($sensor['conveyor_speed'] ?? null) ?></td>
    <td style='text-align:right;'><?= px_num($sensor['cloth_volume'] ?? null) ?></td>
    <td style='text-align:right;'><?= px_num($sensor['a_washer_1'] ?? null) ?></td>
    <td style='text-align:right;'><?= px_num($sensor['a_washer_2'] ?? null) ?></td>
    <td style='text-align:right;'><?= px_num($sensor['a_washer_3'] ?? null) ?></td>
    <td style='text-align:right;'><?= px_num($sensor['a_washer_4'] ?? null) ?></td>
    <td style='text-align:right;'><?= px_num($sensor['a_washer_5'] ?? null) ?></td>
    <td style='text-align:right;'><?= px_num($sensor['top_part'] ?? null) ?></td>
    <td style='text-align:right;'><?= px_num($sensor['boiling_box'] ?? null) ?></td>
    <td style='text-align:right;'><?= px_num($sensor['b_washer_1'] ?? null) ?></td>
    <td style='text-align:right;'><?= px_num($sensor['b_washer_2'] ?? null) ?></td>
    <td style='text-align:right;'><?= px_num($sensor['b_washer_3'] ?? null) ?></td>
    <td style='text-align:right;'><?= px_num($sensor['b_washer_4'] ?? null) ?></td>
    <td style='text-align:right;'><?= px_num($sensor['b_washer_5'] ?? null) ?></td>
    <td style='text-align:right;'><?= px_num($sensor['b_washer_6'] ?? null) ?></td>
    <td style='text-align:right;'><?= px_num($sensor['hot_water_tank'] ?? null) ?></td>
    <td style='text-align:right;'><?= px_num($sensor['no4_dryer'] ?? null) ?></td>
    <td style='text-align:right;'><?= px_num($sensor['front_main_water'] ?? null) ?></td>
    <td style='text-align:right;'><?= px_num($sensor['front_main_steam'] ?? null) ?></td>
    <td style='text-align:right;'><?= px_num($sensor['back_main_water'] ?? null) ?></td>
    <td style='text-align:right;'><?= px_num($sensor['back_main_steam'] ?? null) ?></td>
    <td style='text-align:right;'><?= px_num($sensor['watt_meter'] ?? null) ?></td>
</tr>
<?php endforeach; ?>
<tr><td colspan="<?= $colCount ?>" style='font-weight:bold; background-color:#f0f0f0;'>Total Records: <?= count($results) ?></td></tr>
<?php else: ?>
<tr><td colspan="<?= $colCount ?>" style='text-align:center;'>Data tidak ditemukan</td></tr>
<?php endif; ?>
</table>
