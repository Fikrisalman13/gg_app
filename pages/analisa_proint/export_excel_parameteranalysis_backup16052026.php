<?php
session_start();
ob_start();

include '../../koneksi.php';
include '../../koneksi3.php';
include '../../koneksi4.php';

if (!isset($_SESSION['UserName'])) {
    die("Silakan login terlebih dahulu!");
}

date_default_timezone_set('Asia/Jakarta');

if (!$conn3) {
    die("Koneksi ke database gagal");
}

$startDate = isset($_GET['startdate']) ? trim($_GET['startdate']) : '';
$endDate = isset($_GET['enddate']) ? trim($_GET['enddate']) : '';

if ($startDate === '' || $endDate === '') {
    die("Start Date dan End Date wajib diisi.");
}

if ($startDate > $endDate) {
    die("Start Date tidak boleh lebih besar dari End Date.");
}

function parseDateTimeValue($date, $time)
{
    if ($date instanceof DateTimeInterface && $time instanceof DateTimeInterface) {
        return new DateTime($time->format('Y-m-d H:i:s'));
    }

    if ($time instanceof DateTimeInterface) {
        return new DateTime($time->format('Y-m-d H:i:s'));
    }

    if ($date instanceof DateTimeInterface) {
        $date = $date->format('Y-m-d');
    }

    $date = trim((string) $date);
    $time = trim((string) $time);

    if ($date === '' || $time === '') {
        return null;
    }

    $time = preg_replace('/\.\d+$/', '', $time);

    $standaloneDateTimeFormats = [
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y-m-d\TH:i:s',
        'Y-m-d\TH:i',
    ];
    foreach ($standaloneDateTimeFormats as $format) {
        $dateTime = DateTime::createFromFormat($format, $time);
        if ($dateTime instanceof DateTime) {
            return $dateTime;
        }
    }

    $formats = ['Y-m-d H:i:s', 'Y-m-d H:i'];
    foreach ($formats as $format) {
        $dateTime = DateTime::createFromFormat($format, $date . ' ' . $time);
        if ($dateTime instanceof DateTime) {
            return $dateTime;
        }
    }

    $timestamp = strtotime($date . ' ' . $time);
    return $timestamp !== false ? (new DateTime())->setTimestamp($timestamp) : null;
}

function normalizeNumericValue($value)
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_numeric($value)) {
        return (float) $value;
    }

    $normalized = str_replace(',', '.', trim((string) $value));
    return is_numeric($normalized) ? (float) $normalized : null;
}

function formatTimeCell($value)
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d H:i:s');
    }

    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    return preg_replace('/\.\d+$/', '', $value);
}

function convertSqlsrvDateTimeValue($value)
{
    if ($value instanceof DateTimeInterface) {
        return new DateTime($value->format('Y-m-d H:i:s'));
    }

    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $value = preg_replace('/\.\d+$/', '', $value);
    $timestamp = strtotime($value);

    return $timestamp !== false ? (new DateTime())->setTimestamp($timestamp) : null;
}

function calculateMachineAverages(array $machineLogs, ?DateTime $startDateTime, ?DateTime $endDateTime)
{
    if (!$startDateTime || !$endDateTime || $endDateTime < $startDateTime) {
        return [
            'speed' => null,
            'temp_ch_1' => null,
            'temp_ch_2' => null,
        ];
    }

    $speedTotal = 0.0;
    $tempCh1Total = 0.0;
    $tempCh2Total = 0.0;
    $speedCount = 0;
    $tempCh1Count = 0;
    $tempCh2Count = 0;

    foreach ($machineLogs as $log) {
        $logTime = $log['timestamp'] ?? null;
        if (!$logTime instanceof DateTime) {
            continue;
        }

        if ($logTime < $startDateTime || $logTime > $endDateTime) {
            continue;
        }

        if ($log['speed'] !== null) {
            $speedTotal += $log['speed'];
            $speedCount++;
        }

        if ($log['hct1'] !== null) {
            $tempCh1Total += $log['hct1'];
            $tempCh1Count++;
        }

        if ($log['hct2'] !== null) {
            $tempCh2Total += $log['hct2'];
            $tempCh2Count++;
        }
    }

    return [
        'speed' => $speedCount > 0 ? $speedTotal / $speedCount : null,
        'temp_ch_1' => $tempCh1Count > 0 ? $tempCh1Total / $tempCh1Count : null,
        'temp_ch_2' => $tempCh2Count > 0 ? $tempCh2Total / $tempCh2Count : null,
    ];
}

function fetchMachineLogs($conn4, string $query, array $params)
{
    $logs = [];
    $stmt = sqlsrv_prepare($conn4, $query, $params);

    if (!$stmt || !sqlsrv_execute($stmt)) {
        return $logs;
    }

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $logs[] = [
            'timestamp' => convertSqlsrvDateTimeValue($row['Waktu'] ?? null),
            'speed' => normalizeNumericValue($row['Speed'] ?? null),
            'hct1' => normalizeNumericValue($row['HCT1'] ?? null),
            'hct2' => normalizeNumericValue($row['HCT2'] ?? null),
        ];
    }

    sqlsrv_free_stmt($stmt);

    return $logs;
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
                AND rm.rtgname ILIKE '%PADDRY%'
                AND rm.rtgname NOT ILIKE '%ACC WARNA%'
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
                ON r.productionhdid = m.productionhdid
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

$machineLogsByMachine = [];
if (!empty($results) && $conn4) {
    $machineStartDate = $startDate . ' 00:00:00';
    $machineEndDate = $endDate . ' 23:59:59';
    $machineQueries = [
        'PAD DRY 3' => "
            SELECT
                a.LogTimeStamp AS Waktu,
                ROUND(a.Value01 / 10.0, 2) AS Speed,
                a.Value18 AS HCT1,
                a.Value19 AS HCT2
            FROM dbo.logvaluefloat AS a
            WHERE
                a.LogType_ID = '1311'
                AND a.LogTimeStamp BETWEEN ? AND ?
            ORDER BY a.LogTimeStamp ASC
        ",
        'PAD DRY 4' => "
            SELECT
                a.LogTimeStamp AS Waktu,
                ROUND(c.Value01 / 10.0, 2) AS Speed,
                a.Value23 AS HCT1,
                a.Value25 AS HCT2
            FROM dbo.logvaluefloat AS a
            LEFT JOIN dbo.logvaluefloat AS c
                ON c.LogTimeStamp = a.LogTimeStamp
                AND c.LogType_ID = '1212'
            WHERE
                a.LogType_ID = '1211'
                AND a.LogTimeStamp BETWEEN ? AND ?
            ORDER BY a.LogTimeStamp ASC
        ",
        'PAD DRY 5' => "
            SELECT
                a.LogTimeStamp AS Waktu,
                ROUND(a.Value37 / 10.0, 2) AS Speed,
                c.Value07 AS HCT1,
                c.Value08 AS HCT2
            FROM dbo.logvaluefloat AS a
            LEFT JOIN dbo.logvaluefloat AS c
                ON c.LogTimeStamp = a.LogTimeStamp
                AND c.LogType_ID = '1411'
            WHERE
                a.LogType_ID = '1401'
                AND a.LogTimeStamp BETWEEN ? AND ?
            ORDER BY a.LogTimeStamp ASC
        ",
        'PAD DRY 6' => "
            SELECT
                a.LogTimeStamp AS Waktu,
                ROUND(a.Value37 / 10.0, 2) AS Speed,
                c.Value07 AS HCT1,
                c.Value08 AS HCT2
            FROM dbo.logvaluefloat AS a
            LEFT JOIN dbo.logvaluefloat AS c
                ON c.LogTimeStamp = a.LogTimeStamp
                AND c.LogType_ID = '1511'
            WHERE
                a.LogType_ID = '1501'
                AND a.LogTimeStamp BETWEEN ? AND ?
            ORDER BY a.LogTimeStamp ASC
        ",
    ];

    foreach ($machineQueries as $machineName => $machineQuery) {
        $machineLogsByMachine[$machineName] = fetchMachineLogs(
            $conn4,
            $machineQuery,
            [$machineStartDate, $machineEndDate]
        );
    }
}

foreach ($results as &$row) {
    $row['Speed Mesin'] = null;
    $row['Temp. Ch 1 Mesin'] = null;
    $row['Temp. Ch 2 Mesin'] = null;

    $machineName = strtoupper(trim((string) ($row['No. MC'] ?? '')));
    if (!isset($machineLogsByMachine[$machineName])) {
        continue;
    }

    $startDateTime = parseDateTimeValue($row['Start Date'] ?? '', $row['Start Time'] ?? '');
    $endDateTime = parseDateTimeValue($row['End Date'] ?? '', $row['End Time'] ?? '');
    $machineAverages = calculateMachineAverages($machineLogsByMachine[$machineName], $startDateTime, $endDateTime);

    $row['Speed Mesin'] = $machineAverages['speed'];
    $row['Temp. Ch 1 Mesin'] = $machineAverages['temp_ch_1'];
    $row['Temp. Ch 2 Mesin'] = $machineAverages['temp_ch_2'];
}
unset($row);

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Parameter_Analysis_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

echo "<table border='1'>";
echo "<tr><th colspan='20' style='background-color:#d9edf7; font-size:16px; font-weight:bold;'>PARAMETER ANALYSIS REPORT</th></tr>";
echo "<tr><th colspan='20' style='background-color:#f5f5f5;'>Periode: " . htmlspecialchars($startDate) . " s/d " . htmlspecialchars($endDate) . "</th></tr>";
echo "<tr><th colspan='20' style='background-color:#f5f5f5;'>Export Date: " . date('d/m/Y H:i:s') . " | User: " . htmlspecialchars($_SESSION['UserName'] ?? 'Unknown') . "</th></tr>";
echo "<tr style='background-color:#f8f9fa; font-weight:bold;'>
        <th>No</th>
        <th>Input Date</th>
        <th>CP No</th>
        <th>Article</th>
        <th>Color Code</th>
        <th>Color Name</th>
        <th>Biaya Obat</th>
        <th>Start Time</th>
        <th>End Time</th>
        <th>Time (min)</th>
        <th>No. MC</th>
        <th>Speed</th>
        <th>Speed Mesin</th>
        <th>Standard Production</th>
        <th>Actual Production</th>
        <th>Effic</th>
        <th>Temp. Ch 1</th>
        <th>Temp. Ch 2</th>
        <th>Temp. Ch 1 Mesin</th>
        <th>Temp. Ch 2 Mesin</th>
      </tr>";

$no = 1;
foreach ($results as $row) {
    $startDateTime = parseDateTimeValue($row['Start Date'] ?? '', $row['Start Time'] ?? '');
    $endDateTime = parseDateTimeValue($row['End Date'] ?? '', $row['End Time'] ?? '');
    $timeMinutes = '';
    if ($startDateTime && $endDateTime) {
        $timeMinutes = max(0, ($endDateTime->getTimestamp() - $startDateTime->getTimestamp()) / 60);
    }

    $speed = normalizeNumericValue($row['Speed'] ?? null);
    $actualProduction = normalizeNumericValue($row['Actual Production'] ?? null);
    $standardProduction = ($timeMinutes !== '' && $speed !== null) ? $timeMinutes * $speed : '';
    $effic = ($standardProduction !== '' && (float) $standardProduction != 0.0 && $actualProduction !== null)
        ? $actualProduction / $standardProduction
        : '';

    echo "<tr>";
    echo "<td style='text-align:center;'>" . $no++ . "</td>";
    echo "<td>" . htmlspecialchars((string) ($row['Input Date'] ?? '')) . "</td>";
    echo "<td>" . htmlspecialchars((string) ($row['CP No'] ?? '')) . "</td>";
    echo "<td>" . htmlspecialchars((string) ($row['Article'] ?? '')) . "</td>";
    echo "<td>" . htmlspecialchars((string) ($row['Color Code'] ?? '')) . "</td>";
    echo "<td>" . htmlspecialchars((string) ($row['Color Name'] ?? '')) . "</td>";
    echo "<td style='text-align:right;'>" . htmlspecialchars((string) ($row['Biaya Obat'] ?? '')) . "</td>";
    echo "<td>" . htmlspecialchars(formatTimeCell($row['Start Time'] ?? '')) . "</td>";
    echo "<td>" . htmlspecialchars(formatTimeCell($row['End Time'] ?? '')) . "</td>";
    echo "<td style='text-align:right;'>" . ($timeMinutes === '' ? '' : number_format($timeMinutes, 2, '.', '')) . "</td>";
    echo "<td>" . htmlspecialchars((string) ($row['No. MC'] ?? '')) . "</td>";
    echo "<td style='text-align:right;'>" . htmlspecialchars((string) ($row['Speed'] ?? '')) . "</td>";
    echo "<td style='text-align:right;'>" . (isset($row['Speed Mesin']) && $row['Speed Mesin'] !== null ? number_format((float) $row['Speed Mesin'], 2, '.', '') : '') . "</td>";
    echo "<td style='text-align:right;'>" . ($standardProduction === '' ? '' : number_format($standardProduction, 2, '.', '')) . "</td>";
    echo "<td style='text-align:right;'>" . htmlspecialchars((string) ($row['Actual Production'] ?? '')) . "</td>";
    echo "<td style='text-align:right;'>" . ($effic === '' ? '' : number_format($effic, 4, '.', '')) . "</td>";
    echo "<td style='text-align:right;'>" . htmlspecialchars((string) ($row['Temp. Ch 1'] ?? '')) . "</td>";
    echo "<td style='text-align:right;'>" . htmlspecialchars((string) ($row['Temp. Ch 2'] ?? '')) . "</td>";
    echo "<td style='text-align:right;'>" . (isset($row['Temp. Ch 1 Mesin']) && $row['Temp. Ch 1 Mesin'] !== null ? number_format((float) $row['Temp. Ch 1 Mesin'], 2, '.', '') : '') . "</td>";
    echo "<td style='text-align:right;'>" . (isset($row['Temp. Ch 2 Mesin']) && $row['Temp. Ch 2 Mesin'] !== null ? number_format((float) $row['Temp. Ch 2 Mesin'], 2, '.', '') : '') . "</td>";
    echo "</tr>";
}

echo "<tr><td colspan='20' style='font-weight:bold; background-color:#f0f0f0;'>Total Records: " . count($results) . "</td></tr>";
echo "</table>";
