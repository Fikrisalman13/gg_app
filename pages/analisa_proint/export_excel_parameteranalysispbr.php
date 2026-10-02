<?php
session_start();
ob_start();

date_default_timezone_set('Asia/Jakarta');

include '../../koneksi3.php';
include '../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    die("Silakan login terlebih dahulu!");
}

if (!$conn3) {
    die("Koneksi ke database gagal");
}

$startDate = isset($_GET['startdate']) ? trim($_GET['startdate']) : '';
$endDate   = isset($_GET['enddate']) ? trim($_GET['enddate']) : '';

if ($startDate === '' || $endDate === '') {
    die("Start Date dan End Date wajib diisi.");
}

if ($startDate > $endDate) {
    die("Start Date tidak boleh lebih besar dari End Date.");
}

/**
 * Mirror helper functions from parameteranalysispbr.php so the export
 * file is self-contained and doesn't break if the main file changes.
 */
function pbrExportParseTimeToDateTime($date, $time): ?DateTime
{
    if ($date instanceof DateTimeInterface) {
        $date = $date->format('Y-m-d');
    } else {
        $date = trim((string) $date);
    }
    if (preg_match('/^(\d{4}-\d{2}-\d{2})(?:[ T]\d.*)?$/', $date, $m)) {
        $date = $m[1];
    }

    if ($time instanceof DateTimeInterface) {
        $time = $time->format('H:i:s');
    } else {
        $time = trim((string) $time);
    }
    if (preg_match('/(?:^|\s)(\d{2}:\d{2}(?::\d{2})?)$/', $time, $m)) {
        $time = $m[1];
    }
    if (preg_match('/^(\d{2}:\d{2})/', $time, $m)) {
        $time = $m[1] . ':00';
    }
    if ($date === '' || $time === '') {
        return null;
    }
    $time = preg_replace('/\.\d+$/', '', $time);
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $date . ' ' . $time)
        ?: DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time);
    if ($dt instanceof DateTime) {
        return $dt;
    }
    $ts = strtotime($date . ' ' . $time);
    return $ts !== false ? (new DateTime())->setTimestamp($ts) : null;
}

function pbrExportCellTime($value): string
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('H:i:s');
    }
    $text = trim((string) $value);
    if ($text === '') {
        return '';
    }
    return preg_replace('/\.\d+$/', '', $text);
}

function pbrExportCellValue($value): string
{
    if ($value === null) {
        return '';
    }
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d');
    }
    return trim((string) $value);
}

function pbrExportSensorKeyMap(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $candidates = [
        'actual_1'           => ['[A] Production speed [0.1m/min]', '[A] Production speed [0.1m/min]'],
        'actual_2'           => ['[B] Production speed [0.1m/min]', '[B] Production speed [0.1m/min]'],
        'conveyor_speed'     => ['Conveyor speed [0.1m/min]', 'Conveyor speed [0.1m/min]'],
        'cloth_volume'       => ['Cloth volume [m]', 'Cloth volume [m]'],
        'a_washer_1'         => ['[A] No.1Washer [0.1?]', '[A] No.1Washer [0.1℃]'],
        'a_washer_2'         => ['[A] No.2Washer [0.1?]', '[A] No.2Washer [0.1℃]'],
        'a_washer_3'         => ['[A] No.3Washer [0.1?]', '[A] No.3Washer [0.1℃]'],
        'a_washer_4'         => ['[A] No.4Washer [0.1?]', '[A] No.4Washer [0.1℃]'],
        'a_washer_5'         => ['[A] No.5Washer [0.1?]', '[A] No.5Washer [0.1℃]'],
        'top_part'           => ['Top part [0.1?]', 'Top part [0.1℃]'],
        'boiling_box'        => ['Boiling box [0.1?]', 'Boiling box [0.1℃]'],
        'b_washer_1'         => ['[B] No.1Washer [0.1?]', '[B] No.1Washer [0.1℃]'],
        'b_washer_2'         => ['[B] No.2Washer [0.1?]', '[B] No.2Washer [0.1℃]'],
        'b_washer_3'         => ['[B] No.3Washer [0.1?]', '[B] No.3Washer [0.1℃]'],
        'b_washer_4'         => ['[B] No.4Washer [0.1?]', '[B] No.4Washer [0.1℃]'],
        'b_washer_5'         => ['[B] No.5Washer [0.1?]', '[B] No.5Washer [0.1℃]'],
        'b_washer_6'         => ['[B] No.6Washer [0.1?]', '[B] No.6Washer [0.1℃]'],
        'hot_water_tank'     => ['Hot Water Tank [0.1?]', 'Hot Water Tank [0.1℃]'],
        'no4_dryer'          => ['[B] No.4 Dryer [0.1?]', '[B] No.4 Dryer [0.1℃]'],
        'front_main_water'   => ['[Front] Main Water [0.1m3/h]', '[Front] Main Water [0.1m3/h]'],
        'front_main_steam'   => ['[Front] Main Steam [kg/h]', '[Front] Main Steam [kg/h]'],
        'back_main_water'    => ['[Back] Main Water [0.1m3/h]', '[Back] Main Water [0.1m3/h]'],
        'back_main_steam'    => ['[Back] Main Steam [kg/h]', '[Back] Main Steam [kg/h]'],
        'watt_meter'         => ['Watt meter [kW]', 'Watt meter [kW]'],
    ];

    foreach ($candidates as $alias => $list) {
        $expanded = $list;
        foreach ($list as $key) {
            if (strpos($key, '[0.1?]') !== false) {
                $alt = str_replace('[0.1?]', '[0.1℃]', $key);
                if (!in_array($alt, $expanded, true)) {
                    $expanded[] = $alt;
                }
            }
        }
        $candidates[$alias] = $expanded;
    }

    $map = $candidates;
    return $map;
}

function pbrExportLoadSensorRecords($conn, DateTime $minStart, DateTime $maxEnd): array
{
    $records = [];
    if (!$conn) {
        return $records;
    }
    $sql = "
        SELECT
            CONVERT(VARCHAR(19), RecordDate, 120) AS RD,
            RowJson
        FROM dbo.MonitoringMesinDataAb
        WHERE RecordDate BETWEEN ? AND ?
        ORDER BY RecordDate ASC
    ";
    $stmt = sqlsrv_query($conn, $sql, [
        $minStart->format('Y-m-d H:i:s'),
        $maxEnd->format('Y-m-d H:i:s'),
    ]);
    if ($stmt === false) {
        return $records;
    }
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rd = $r['RD'] instanceof DateTimeInterface ? $r['RD']->format('Y-m-d H:i:s') : (string) $r['RD'];
        $ts = strtotime($rd);
        if ($ts === false) {
            continue;
        }
        $raw = (string) $r['RowJson'];
        if ($raw === '') {
            continue;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            continue;
        }
        $records[] = ['ts' => $ts, 'data' => $decoded];
    }
    sqlsrv_free_stmt($stmt);
    return $records;
}

/**
 * Single-pass aggregation across many ERP windows.
 * Iterates the (sorted) sensor records exactly once instead of once per window.
 * Mutates each row's 'Sensor' key in place.
 */
function pbrExportComputeAveragesMulti(array &$rows, array $records): void
{
    $keyMap = pbrExportSensorKeyMap();
    $aliases = array_keys($keyMap);
    $nullRow = array_fill_keys($aliases, null);

    // Pre-parse and assign each row's window + result container.
    $windows = [];
    foreach ($rows as $idx => &$row) {
        $sdt = $row['_start_dt'] ?? null;
        $edt = $row['_end_dt'] ?? null;
        $averages = $nullRow;
        $row['Sensor'] = $averages;

        if ($sdt instanceof DateTime && $edt instanceof DateTime) {
            $startTs = $sdt->getTimestamp();
            $endTs = $edt->getTimestamp();
            if ($endTs >= $startTs) {
                $windows[] = [
                    'idx' => $idx,
                    'startTs' => $startTs,
                    'endTs' => $endTs,
                    'sums' => array_fill_keys($aliases, 0.0),
                    'counts' => array_fill_keys($aliases, 0),
                    'result' => &$averages,
                ];
            }
        }
    }
    unset($row);

    if (empty($records) || empty($windows)) {
        return;
    }

    // Sort windows by startTs asc so we can advance a pointer as records march forward.
    usort($windows, static function ($a, $b) {
        return $a['startTs'] <=> $b['startTs'];
    });

    $windowCount = count($windows);
    $nextStartIdx = 0; // index of next window that hasn't been "started" yet

    foreach ($records as $rec) {
        $ts = $rec['ts'];

        // Activate all windows whose startTs <= ts.
        while ($nextStartIdx < $windowCount && $windows[$nextStartIdx]['startTs'] <= $ts) {
            $nextStartIdx++;
        }

        // No active windows yet (ts before first startTs).
        if ($nextStartIdx === 0) {
            continue;
        }

        $data = $rec['data'] ?? null;
        if (!is_array($data)) {
            continue;
        }

        // Walk active windows [0 .. nextStartIdx-1]; skip those already ended.
        for ($i = 0; $i < $nextStartIdx; $i++) {
            $w = &$windows[$i];
            if ($w['endTs'] < $ts) {
                continue; // this window already ended
            }

            foreach ($keyMap as $alias => $candidates) {
                $raw = null;
                foreach ($candidates as $key) {
                    if (isset($data[$key]) && $data[$key] !== '') {
                        $raw = $data[$key];
                        break;
                    }
                }
                if ($raw === null) {
                    continue;
                }
                if (is_numeric($raw)) {
                    $w['sums'][$alias] += (float) $raw;
                    $w['counts'][$alias]++;
                }
            }
        }
        unset($w);
    }

    // Finalize averages back into each row's 'Sensor' key.
    foreach ($windows as $w) {
        foreach ($w['sums'] as $alias => $sum) {
            if ($w['counts'][$alias] > 0) {
                $w['result'][$alias] = $sum / $w['counts'][$alias];
            }
        }
        $rows[$w['idx']]['Sensor'] = $w['result'];
    }
}

function pbrExportComputeAverages(array $records, DateTime $start, DateTime $end): array
{
    $keyMap = pbrExportSensorKeyMap();
    $averages = array_fill_keys(array_keys($keyMap), null);
    if (empty($records)) {
        return $averages;
    }

    $startTs = $start->getTimestamp();
    $endTs = $end->getTimestamp();
    if ($endTs < $startTs) {
        return $averages;
    }

    $sums = array_fill_keys(array_keys($keyMap), 0.0);
    $counts = array_fill_keys(array_keys($keyMap), 0);

    foreach ($records as $rec) {
        $ts = $rec['ts'];
        if ($ts < $startTs) {
            continue;
        }
        if ($ts > $endTs) {
            break;
        }
        $data = $rec['data'];
        if (!is_array($data)) {
            continue;
        }
        foreach ($keyMap as $alias => $candidates) {
            $raw = null;
            foreach ($candidates as $key) {
                if (isset($data[$key]) && $data[$key] !== '') {
                    $raw = $data[$key];
                    break;
                }
            }
            if ($raw === null) {
                continue;
            }
            if (is_numeric($raw)) {
                $sums[$alias] += (float) $raw;
                $counts[$alias]++;
            }
        }
    }

    foreach ($averages as $alias => $_) {
        $averages[$alias] = $counts[$alias] > 0 ? $sums[$alias] / $counts[$alias] : null;
    }
    return $averages;
}

try {
    $query = "
        WITH rtg AS (
            SELECT
                r.productionhdid,
                r.productionrtgid,
                r.rtgmsid,
                r.famasterid,
                r.prdqty,
                r.prdstdqty,
                r.fgresult,
                r.startdate,
                r.enddate,
                r.starttime,
                r.endtime
            FROM pdproductionrtg r
            JOIN pdrtgms rm
                ON rm.rtgmsid = r.rtgmsid
            WHERE
                r.startdate >= :startdate
                AND r.startdate <= :enddate
                AND rm.rtgname ILIKE '%PBR%'
                AND COALESCE(r.prdstdqty, 0) > 0
                AND r.fgresult IS NOT NULL
                AND TRIM(COALESCE(CAST(r.fgresult AS VARCHAR(50)), '')) <> ''
        ),
        ps AS (
            SELECT
                s.productionrtgid,
                MAX(s.prdtemp) AS prdtemp,
                MAX(s.prdtempfinish) AS prdtempfinish,
                MAX(s.prdspeed) AS prdspeed
            FROM pdproductionsum s
            JOIN rtg r
                ON r.productionrtgid = s.productionrtgid
            GROUP BY s.productionrtgid
        ),
        hd AS (
            SELECT
                productionhdid,
                prdnmbr,
                colorid,
                prodid,
                prodname
            FROM pdproductionhd
        ),
        rtgms AS (
            SELECT
                rtgmsid,
                rtgname
            FROM pdrtgms
        ),
        color AS (
            SELECT
                colormsid,
                colorcode,
                colorname
            FROM pdcolorms
        ),
        fam AS (
            SELECT
                famasterid,
                faname
            FROM famaster
        ),
        article AS (
            SELECT
                m.productionhdid,
                MAX(m.prodname) AS prodname
            FROM pdresultmat m
            JOIN rtg r
                ON r.productionhdid = m.productionhdid
            WHERE m.fgusedtype = 'A'
            GROUP BY m.productionhdid
        ),
        biaya_obat AS (
            SELECT
                m.productionhdid,
                SUM(m.actualprice) AS biaya_obat
            FROM pdresultmat m
            JOIN rtg r
                ON r.productionhdid = r.productionhdid
            JOIN pdbomhd bh
                ON m.bomhdid = bh.bomhdid
            GROUP BY m.productionhdid
        )
        SELECT
            r.startdate AS \"Start Date\",
            r.starttime AS \"Start Time\",
            r.enddate AS \"End Date\",
            r.endtime AS \"End Time\",
            r.prdqty AS \"Actual Production\",
            r.prdstdqty AS \"Production Standard (DB)\",
            r.fgresult AS \"FG Result\",
            f.faname AS \"No. MC\",
            rm.rtgname AS \"Routing Name\",
            ps.prdtemp AS \"Temp. Ch 1\",
            ps.prdtempfinish AS \"Temp. Ch 2\",
            ps.prdspeed AS \"Speed\",
            h.prdnmbr AS \"CP No\",
            c.colorcode AS \"Color Code\",
            c.colorname AS \"Color Name\",
            a.prodname AS \"Article\",
            r.startdate AS \"Input Date\",
            bo.biaya_obat AS \"Biaya Obat\"
        FROM rtg r
        LEFT JOIN ps ON ps.productionrtgid = r.productionrtgid
        LEFT JOIN hd h ON h.productionhdid = r.productionhdid
        LEFT JOIN rtgms rm ON rm.rtgmsid = r.rtgmsid
        LEFT JOIN fam f ON f.famasterid = r.famasterid
        LEFT JOIN color c ON c.colormsid = h.colorid
        LEFT JOIN article a ON a.productionhdid = r.productionhdid
        LEFT JOIN biaya_obat bo ON bo.productionhdid = r.productionhdid
        ORDER BY r.startdate
    ";

    $stmt = $conn3->prepare($query);
    $stmt->bindValue(':startdate', $startDate);
    $stmt->bindValue(':enddate', $endDate);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error executing query: " . $e->getMessage());
}

// Bulk-load sensor data covering all ERP rows.
$sensorRecords = [];
if (!empty($results)) {
    $windowStarts = [];
    $windowEnds = [];
    foreach ($results as $tmpRow) {
        $sdt = pbrExportParseTimeToDateTime($tmpRow['Start Date'] ?? '', $tmpRow['Start Time'] ?? '');
        $edt = pbrExportParseTimeToDateTime($tmpRow['End Date'] ?? '', $tmpRow['End Time'] ?? '');
        if ($sdt) { $windowStarts[] = $sdt; }
        if ($edt) { $windowEnds[] = $edt; }
    }
    if ($windowStarts && $windowEnds) {
        $minStart = clone min($windowStarts);
        $maxEnd = clone max($windowEnds);
        $sensorRecords = pbrExportLoadSensorRecords($conn, $minStart, $maxEnd);
    }
}

foreach ($results as &$row) {
    $row['_start_dt'] = pbrExportParseTimeToDateTime($row['Start Date'] ?? '', $row['Start Time'] ?? '');
    $row['_end_dt'] = pbrExportParseTimeToDateTime($row['End Date'] ?? '', $row['End Time'] ?? '');
}
unset($row);

// Single pass over sensor records; updates every row's 'Sensor' averages.
pbrExportComputeAveragesMulti($results, $sensorRecords);

// Clean any buffered output so the binary XLS stream is intact.
if (ob_get_length()) {
    ob_end_clean();
}

$sd = date('dmY', strtotime($startDate));
$ed = date('dmY', strtotime($endDate));
$filename = "parameteranalysispbr_{$sd}_{$ed}.xls";
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=" . $filename);
header("Pragma: no-cache");
header("Expires: 0");

echo "<table border='1'>";
echo "<tr><th colspan='36' style='background-color:#d9edf7; font-size:16px; font-weight:bold;'>PARAMETER ANALYSIS PBR REPORT ({$sd} - {$ed})</th></tr>";
echo "<tr><th colspan='36' style='background-color:#f5f5f5;'>Periode: " . htmlspecialchars($startDate) . " s/d " . htmlspecialchars($endDate) . "</th></tr>";
echo "<tr><th colspan='36' style='background-color:#f5f5f5;'>Filter: PRD Std Qty > 0 AND FG Result terisi</th></tr>";
echo "<tr><th colspan='36' style='background-color:#f5f5f5;'>Export Date: " . date('d/m/Y H:i:s') . " | User: " . htmlspecialchars($_SESSION['UserName'] ?? 'Unknown') . "</th></tr>";
echo "<tr style='background-color:#f8f9fa; font-weight:bold;'>
        <th>No</th>
        <th>Input Date</th>
        <th>CP No</th>
        <th>Routing</th>
        <th>Article</th>
        <th>Color Code</th>
        <th>Color Name</th>
        <th>Biaya Obat</th>
        <th>FG Result</th>
        <th>PRD Std Qty</th>
        <th>Start Time</th>
        <th>End Time</th>
        <th>Time (min)</th>
        <th>No. MC</th>
        <th>Speed</th>
        <th>Actual Production (M)</th>
        <th>Production Standard (M)</th>
        <th>Efficiency</th>
        <th>Temp. Ch 1</th>
        <th>Temp. Ch 2</th>
        <th>Act 1 Speed</th>
        <th>Act 2 Speed</th>
        <th>Avg Speed</th>
        <th>Conveyor Speed</th>
        <th>Cloth Volume [m]</th>
        <th>Washer A1-A5</th>
        <th>Top Part</th>
        <th>Boiling Box</th>
        <th>Washer B1-B6</th>
        <th>Hot Water Tank</th>
        <th>No.4 Dryer</th>
        <th>Front Water</th>
        <th>Front Steam</th>
        <th>Back Water</th>
        <th>Back Steam</th>
        <th>Watt Meter (kW)</th>
      </tr>";

$numCell = static function ($v) {
    if ($v === null || $v === '') {
        return '';
    }
    return number_format((float) $v, 2, '.', '');
};

$no = 1;
foreach ($results as $row) {
    $startDateTime = $row['_start_dt'] ?? null;
    $endDateTime = $row['_end_dt'] ?? null;

    $timeMinutes = null;
    if ($startDateTime instanceof DateTime && $endDateTime instanceof DateTime) {
        $timeMinutes = ($endDateTime->getTimestamp() - $startDateTime->getTimestamp()) / 60;
    }

    $speed = is_numeric($row['Speed'] ?? null) ? (float) $row['Speed'] : null;
    $actualProduction = is_numeric($row['Actual Production'] ?? null) ? (float) $row['Actual Production'] : null;
    $standardProduction = ($timeMinutes !== null && $speed !== null) ? $timeMinutes * $speed : null;
    $efficiency = ($standardProduction !== null && $standardProduction > 0 && $actualProduction !== null)
        ? $actualProduction / $standardProduction
        : null;

    $sensor = $row['Sensor'] ?? [];
    $act1 = $sensor['actual_1'] ?? null;
    $act2 = $sensor['actual_2'] ?? null;
    $speedAvg = ($act1 !== null && $act2 !== null) ? ($act1 + $act2) / 2 : null;

    $washerA = [
        $sensor['a_washer_1'] ?? null,
        $sensor['a_washer_2'] ?? null,
        $sensor['a_washer_3'] ?? null,
        $sensor['a_washer_4'] ?? null,
        $sensor['a_washer_5'] ?? null,
    ];
    $washerB = [
        $sensor['b_washer_1'] ?? null,
        $sensor['b_washer_2'] ?? null,
        $sensor['b_washer_3'] ?? null,
        $sensor['b_washer_4'] ?? null,
        $sensor['b_washer_5'] ?? null,
        $sensor['b_washer_6'] ?? null,
    ];

    $washerAText = implode(' | ', array_map($numCell, $washerA));
    $washerBText = implode(' | ', array_map($numCell, $washerB));

    echo "<tr>";
    echo "<td style='text-align:center;'>" . $no++ . "</td>";
    echo "<td>" . htmlspecialchars(pbrExportCellValue($row['Input Date'] ?? '')) . "</td>";
    echo "<td>" . htmlspecialchars((string) ($row['CP No'] ?? '')) . "</td>";
    echo "<td>" . htmlspecialchars((string) ($row['Routing Name'] ?? '')) . "</td>";
    echo "<td>" . htmlspecialchars((string) ($row['Article'] ?? '')) . "</td>";
    echo "<td>" . htmlspecialchars((string) ($row['Color Code'] ?? '')) . "</td>";
    echo "<td>" . htmlspecialchars((string) ($row['Color Name'] ?? '')) . "</td>";
    echo "<td style='text-align:right;'>" . ($row['Biaya Obat'] !== null ? number_format((float) $row['Biaya Obat'], 2, '.', '') : '') . "</td>";
    echo "<td style='text-align:center;'>" . htmlspecialchars((string) ($row['FG Result'] ?? '')) . "</td>";
    echo "<td style='text-align:right;'>" . ($row['Production Standard (DB)'] !== null ? number_format((float) $row['Production Standard (DB)'], 2, '.', '') : '') . "</td>";
    echo "<td>" . htmlspecialchars(pbrExportCellTime($row['Start Time'] ?? '')) . "</td>";
    echo "<td>" . htmlspecialchars(pbrExportCellTime($row['End Time'] ?? '')) . "</td>";
    $timeStyle = ($timeMinutes !== null && $timeMinutes < 0)
        ? " style='text-align:right; color:red; font-weight:bold;'"
        : " style='text-align:right;'";
    echo "<td" . $timeStyle . ">" . ($timeMinutes === null ? '' : number_format($timeMinutes, 2, '.', '')) . "</td>";
    echo "<td>" . htmlspecialchars((string) ($row['No. MC'] ?? '')) . "</td>";
    echo "<td style='text-align:right;'>" . $numCell($speed) . "</td>";
    echo "<td style='text-align:right;'>" . $numCell($actualProduction) . "</td>";
    echo "<td style='text-align:right;'>" . $numCell($standardProduction) . "</td>";
    echo "<td style='text-align:right;'>" . ($efficiency === null ? '' : number_format($efficiency, 4, '.', '')) . "</td>";
    echo "<td style='text-align:right;'>" . $numCell($row['Temp. Ch 1'] ?? null) . "</td>";
    echo "<td style='text-align:right;'>" . $numCell($row['Temp. Ch 2'] ?? null) . "</td>";
    echo "<td style='text-align:right;'>" . $numCell($act1) . "</td>";
    echo "<td style='text-align:right;'>" . $numCell($act2) . "</td>";
    echo "<td style='text-align:right;'>" . $numCell($speedAvg) . "</td>";
    echo "<td style='text-align:right;'>" . $numCell($sensor['conveyor_speed'] ?? null) . "</td>";
    echo "<td style='text-align:right;'>" . $numCell($sensor['cloth_volume'] ?? null) . "</td>";
    echo "<td>" . htmlspecialchars($washerAText) . "</td>";
    echo "<td style='text-align:right;'>" . $numCell($sensor['top_part'] ?? null) . "</td>";
    echo "<td style='text-align:right;'>" . $numCell($sensor['boiling_box'] ?? null) . "</td>";
    echo "<td>" . htmlspecialchars($washerBText) . "</td>";
    echo "<td style='text-align:right;'>" . $numCell($sensor['hot_water_tank'] ?? null) . "</td>";
    echo "<td style='text-align:right;'>" . $numCell($sensor['no4_dryer'] ?? null) . "</td>";
    echo "<td style='text-align:right;'>" . $numCell($sensor['front_main_water'] ?? null) . "</td>";
    echo "<td style='text-align:right;'>" . $numCell($sensor['front_main_steam'] ?? null) . "</td>";
    echo "<td style='text-align:right;'>" . $numCell($sensor['back_main_water'] ?? null) . "</td>";
    echo "<td style='text-align:right;'>" . $numCell($sensor['back_main_steam'] ?? null) . "</td>";
    echo "<td style='text-align:right;'>" . $numCell($sensor['watt_meter'] ?? null) . "</td>";
    echo "</tr>";
}

echo "<tr><td colspan='36' style='font-weight:bold; background-color:#f0f0f0;'>Total Records: " . count($results) . "</td></tr>";
echo "</table>";
