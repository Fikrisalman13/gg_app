<?php
session_start();
ob_start();
date_default_timezone_set('Asia/Jakarta');
include '../../koneksi3.php';
include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';
if (!isset($_SESSION['UserName'])) { $_SESSION['error'] = 'Silakan login terlebih dahulu!'; header('Location: /gg_app/login.php'); exit; }
if (!$conn3) { die('Koneksi ke database gagal'); }

// PDO SQL Server untuk data sensor MonitoringMesinDataAb
$pdoSensor = null;
try {
    $dsn = "sqlsrv:Server={$serverName};Database={$connectionOptions['Database']}";
    if (!empty($connectionOptions['TrustServerCertificate'])) {
        $dsn .= ';TrustServerCertificate=1';
    }
    $pdoSensor = new PDO($dsn, $connectionOptions['Uid'], $connectionOptions['PWD'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    $errorMessage = 'Koneksi SQL Server untuk data sensor gagal: ' . $e->getMessage();
}

$startDate = isset($_GET['startdate']) ? trim($_GET['startdate']) : date('Y-m-d');
$endDate = isset($_GET['enddate']) ? trim($_GET['enddate']) : date('Y-m-d');
$isFiltered = isset($_GET['filter']);
$errorMessage = '';
$results = [];

function pa_pbr_filter_date(string $date, bool $isEnd = false): ?DateTime {
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
function pa_pbr_parse_dt($date, $time): ?DateTime {
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
function pa_pbr_norm(string $v): string { $v = str_replace(["\r", "\n", "\t"], ' ', $v); $v = trim($v, " \t\n\r\0\x0B\"'"); $v = preg_replace('/\s+/u', ' ', $v ?? ''); return strtolower(trim((string)$v)); }
function pa_pbr_map(): array {
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
function pa_pbr_load_sensor(PDO $conn, DateTime $start, DateTime $end): array {
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
    $map = pa_pbr_map(); $records = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $ts = strtotime((string)($row['RD'] ?? '')); $data = json_decode((string)($row['RowJson'] ?? ''), true);
        if ($ts === false || !is_array($data)) continue;
        $normalized = []; foreach ($data as $k => $v) $normalized[pa_pbr_norm((string)$k)] = $v;
        $mapped = []; foreach ($map as $alias => $candidates) { $mapped[$alias] = null; foreach ($candidates as $candidate) { if (isset($normalized[$candidate]) && $normalized[$candidate] !== '' && is_numeric($normalized[$candidate])) { $mapped[$alias] = (float)$normalized[$candidate]; break; } } }
        $records[] = ['ts' => $ts, 'data' => $mapped];
    }
    return $records;
}
function pa_pbr_avg(array $records, DateTime $start, DateTime $end): array {
    $map = pa_pbr_map(); $avg = array_fill_keys(array_keys($map), null); $sum = array_fill_keys(array_keys($map), 0.0); $count = array_fill_keys(array_keys($map), 0);
    $startTs = $start->getTimestamp(); $endTs = $end->getTimestamp();
    foreach ($records as $rec) {
        if ($rec['ts'] < $startTs || $rec['ts'] > $endTs) continue;
        foreach ($rec['data'] as $alias => $value) { if ($value === null) continue; $sum[$alias] += $value; $count[$alias]++; }
    }
    foreach ($avg as $alias => $_) $avg[$alias] = $count[$alias] > 0 ? $sum[$alias] / $count[$alias] : null;
    return $avg;
}

if ($isFiltered) {
    if ($startDate === '' || $endDate === '') $errorMessage = 'Start Date dan End Date wajib diisi.';
    elseif ($startDate > $endDate) $errorMessage = 'Start Date tidak boleh lebih besar dari End Date.';
    else {
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
            $startFilter = pa_pbr_filter_date($startDate, false);
            $endFilter = pa_pbr_filter_date($endDate, true);
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
                    $sdt = pa_pbr_parse_dt($row['Start Date'] ?? '', $row['Start Time'] ?? '');
                    $edt = pa_pbr_parse_dt($row['End Date'] ?? '', $row['End Time'] ?? '');
                    if ($sdt) $starts[] = $sdt;
                    if ($edt) $ends[] = $edt;
                }
                if ($starts && $ends && $pdoSensor) $sensorRecords = pa_pbr_load_sensor($pdoSensor, min($starts), max($ends));
            }

            foreach ($results as &$row) {
                $row['_start_dt'] = pa_pbr_parse_dt($row['Start Date'] ?? '', $row['Start Time'] ?? '');
                $row['_end_dt'] = pa_pbr_parse_dt($row['End Date'] ?? '', $row['End Time'] ?? '');
                $row['Sensor'] = ($row['_start_dt'] && $row['_end_dt']) ? pa_pbr_avg($sensorRecords, $row['_start_dt'], $row['_end_dt']) : array_fill_keys(array_keys(pa_pbr_map()), null);
            }
            unset($row);
        } catch (Throwable $e) { $errorMessage = 'Error executing query: ' . $e->getMessage(); }
    }
}

function pa_fmt($v): string { return $v === null || $v === '' ? '' : htmlspecialchars((string)$v); }
function pa_num($v, int $d = 2): string { return $v === null || $v === '' ? '' : htmlspecialchars(number_format((float)$v, $d, '.', '')); }
?>
<style>
.th-purple { background-color: #d5a6e0 !important; color: #333; }
.th-blue   { background-color: #a8d8ff !important; color: #333; }
.th-red    { background-color: #f5a0a0 !important; color: #333; }
.th-yellow { background-color: #ffec80 !important; color: #333; }
.legend-box { display: inline-block; width: 16px; height: 16px; border-radius: 3px; margin-right: 4px; vertical-align: middle; }
.legend-label { margin-right: 16px; font-size: 12px; vertical-align: middle; }
</style>
<div class="wrapper"><div class="content-wrapper"><div class="content-header"><div class="container-fluid"><div class="row mb-2"><div class="col-12"><h1 class="m-0">Parameter Analysis PBR</h1></div></div></div></div><div class="content"><div class="container-fluid"><div class="card"><div class="card-body">
<form method="get" class="mb-3"><div class="row"><div class="col-md-3"><label>Start Date:</label><input type="date" name="startdate" class="form-control form-control-sm" value="<?= htmlspecialchars($startDate) ?>" required></div><div class="col-md-3"><label>End Date:</label><input type="date" name="enddate" class="form-control form-control-sm" value="<?= htmlspecialchars($endDate) ?>" required></div><div class="col-md-6 d-flex align-items-end"><button type="submit" name="filter" value="1" class="btn btn-primary btn-sm mr-2">Filter</button><a href="parameteranalysyspbr.php" class="btn btn-secondary btn-sm">Reset</a>
<a href="export_excel_parameteranalysyspbr.php?startdate=<?= urlencode($startDate) ?>&enddate=<?= urlencode($endDate) ?>" target="_blank" class="btn btn-success btn-sm"><i class="fas fa-file-excel"></i> Export Excel</a>
</div></div></form>
<div class="mb-2">
    <span class="legend-box" style="background-color:#d5a6e0;"></span><span class="legend-label"><strong>Proint</strong></span>
    <span class="legend-box" style="background-color:#a8d8ff;"></span><span class="legend-label"><strong>Rumus</strong></span>
    <span class="legend-box" style="background-color:#f5a0a0;"></span><span class="legend-label"><strong>PBR Aplikasi</strong></span>
    <span class="legend-box" style="background-color:#ffec80;"></span><span class="legend-label"><strong>Tanda Tanya</strong></span>
</div>
<div class="mb-2 text-muted small">
    <strong>Kuning:</strong> Standard = speed dari <code>pdproductionsum.prdspeed</code>; Production Standard (M) = <code>pdproductionrtg.prdstdqty</code>; Efficiency = Actual Production ÷ Production Standard
</div>
<?php if ($errorMessage !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>
<div class="table-responsive" style="overflow-x:auto;"><table class="table table-bordered table-hover table-sm text-nowrap small mb-0">
<thead>
<tr class="text-center align-middle">
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
<tr class="text-center align-middle">
    <th>Date</th><th>Start Time</th><th>End Time</th>
    <th class="th-red">Actual 1</th>
    <th class="th-red">Actual 2</th>
    <th class="th-blue">Rata-Rata</th>
    <th class="th-yellow">Standard</th>
    <th class="th-red">Washer 1</th><th class="th-red">Washer 2</th><th class="th-red">Washer 3</th><th class="th-red">Washer 4</th><th class="th-red">Washer 5</th>
    <th class="th-red">Washer 1</th><th class="th-red">Washer 2</th><th class="th-red">Washer 3</th><th class="th-red">Washer 4</th><th class="th-red">Washer 5</th><th class="th-red">Washer 6</th>
</tr>
</thead>
<tbody>
<?php if (!empty($results)): $no = 1; foreach ($results as $row):
    $sensor = $row['Sensor'] ?? [];
    $act1 = $sensor['actual_1'] ?? null;
    $act2 = $sensor['actual_2'] ?? null;
    $speedAvg = ($act1 !== null && $act2 !== null) ? ($act1 + $act2) / 2 : null;
    $timeMinutes = ($row['_start_dt'] && $row['_end_dt']) ? (($row['_end_dt']->getTimestamp() - $row['_start_dt']->getTimestamp()) / 60) : null;
    $speed = is_numeric($row['Speed'] ?? null) ? (float)$row['Speed'] : null;
    $actualProd = is_numeric($row['Actual Production'] ?? null) ? (float)$row['Actual Production'] : null;
    $prodStd = is_numeric($row['Production Standard (DB)'] ?? null) ? (float)$row['Production Standard (DB)'] : null;
    $standardProduction = ($timeMinutes !== null && $speed !== null) ? $timeMinutes * $speed : null;
    $efficiency = ($prodStd !== null && $prodStd > 0 && $actualProd !== null) ? $actualProd / $prodStd : null;
?>
<tr>
<td class="text-center"><?= $no++ ?></td>
<td><?= pa_fmt($row['Start Date'] ?? '') ?></td>
<td><?= pa_fmt($row['CP No'] ?? '') ?></td>
<td><?= pa_fmt($row['Routing Name'] ?? '') ?></td>
<td><?= pa_fmt($row['Article'] ?? '') ?></td>
<td><?= pa_fmt($row['Start Date'] ?? '') ?></td>
<td><?= pa_fmt($row['Start Time'] ?? '') ?></td>
<td><?= pa_fmt($row['End Time'] ?? '') ?></td>
<td class="text-right"><?= $timeMinutes === null ? '' : number_format($timeMinutes, 2, '.', '') ?></td>
<td class="text-right"><?= pa_num($act1) ?></td>
<td class="text-right"><?= pa_num($act2) ?></td>
<td class="text-right"><?= $speedAvg === null ? '' : number_format($speedAvg, 2, '.', '') ?></td>
<td class="text-right"><?= $speed === null ? '' : number_format($speed, 2, '.', '') ?></td>
<td class="text-right"><?= pa_num($actualProd) ?></td>
<td class="text-right"><?= pa_num($prodStd) ?></td>
<td class="text-right"><?= $efficiency === null ? '' : number_format($efficiency, 4, '.', '') ?></td>
<td class="text-right"><?= pa_num($sensor['conveyor_speed'] ?? null) ?></td>
<td class="text-right"><?= pa_num($sensor['cloth_volume'] ?? null) ?></td>
<td class="text-right"><?= pa_num($sensor['a_washer_1'] ?? null) ?></td>
<td class="text-right"><?= pa_num($sensor['a_washer_2'] ?? null) ?></td>
<td class="text-right"><?= pa_num($sensor['a_washer_3'] ?? null) ?></td>
<td class="text-right"><?= pa_num($sensor['a_washer_4'] ?? null) ?></td>
<td class="text-right"><?= pa_num($sensor['a_washer_5'] ?? null) ?></td>
<td class="text-right"><?= pa_num($sensor['top_part'] ?? null) ?></td>
<td class="text-right"><?= pa_num($sensor['boiling_box'] ?? null) ?></td>
<td class="text-right"><?= pa_num($sensor['b_washer_1'] ?? null) ?></td>
<td class="text-right"><?= pa_num($sensor['b_washer_2'] ?? null) ?></td>
<td class="text-right"><?= pa_num($sensor['b_washer_3'] ?? null) ?></td>
<td class="text-right"><?= pa_num($sensor['b_washer_4'] ?? null) ?></td>
<td class="text-right"><?= pa_num($sensor['b_washer_5'] ?? null) ?></td>
<td class="text-right"><?= pa_num($sensor['b_washer_6'] ?? null) ?></td>
<td class="text-right"><?= pa_num($sensor['hot_water_tank'] ?? null) ?></td>
<td class="text-right"><?= pa_num($sensor['no4_dryer'] ?? null) ?></td>
<td class="text-right"><?= pa_num($sensor['front_main_water'] ?? null) ?></td>
<td class="text-right"><?= pa_num($sensor['front_main_steam'] ?? null) ?></td>
<td class="text-right"><?= pa_num($sensor['back_main_water'] ?? null) ?></td>
<td class="text-right"><?= pa_num($sensor['back_main_steam'] ?? null) ?></td>
<td class="text-right"><?= pa_num($sensor['watt_meter'] ?? null) ?></td>
</tr>
<?php endforeach; else: ?><tr><td colspan="37" class="text-center">Data tidak ditemukan</td></tr><?php endif; ?>
</tbody></table></div></div></div></div></div></div>
<?php include '../../includes/footer.php'; ?>
