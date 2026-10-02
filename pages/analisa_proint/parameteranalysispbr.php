<?php
session_start();
ob_start();

date_default_timezone_set('Asia/Jakarta');

include '../../koneksi3.php';
include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu!';
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
$startDate = isset($_GET['startdate']) ? trim($_GET['startdate']) : '2026-06-16';
$endDate = isset($_GET['enddate']) ? trim($_GET['enddate']) : '2026-06-17';
$results = [];
$errorMessage = '';
$isFiltered = isset($_GET['filter']);

function parseTimeToDateTime($date, $time): ?DateTime
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
    $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $date . ' ' . $time)
        ?: DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time);

    if ($dateTime instanceof DateTime) {
        return $dateTime;
    }

    $timestamp = strtotime($date . ' ' . $time);
    return $timestamp !== false ? (new DateTime())->setTimestamp($timestamp) : null;
}

function formatCellTime($value): string
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d H:i:s');
    }

    $value = trim((string) $value);
    return $value === '' ? '' : preg_replace('/\.\d+$/', '', $value);
}

function formatNumberCell($value, int $decimals = 2): string
{
    if ($value === null || $value === '') {
        return '';
    }

    if (!is_numeric($value)) {
        return htmlspecialchars((string) $value);
    }

    return number_format((float) $value, $decimals, '.', '');
}

function pbrCellValue($value): string
{
    if ($value === null) {
        return '';
    }
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d');
    }
    return trim((string) $value);
}

function pbrCellTime($value): string
{
    if ($value === null) {
        return '';
    }
    if ($value instanceof DateTimeInterface) {
        return $value->format('H:i:s');
    }
    $text = trim((string) $value);
    if ($text === '') {
        return '';
    }
    return preg_replace('/\\.\\d+$/', '', $text);
}

function pbrSensorKeyMap(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    // Each alias maps to an ordered list of candidate JSON keys.
    // We try each variant per record so legacy files using '?' and
    // newer files using the proper Unicode '℃' character both work.
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

    // For every '?' variant, also auto-generate the '℃' sibling if missing.
    foreach ($candidates as $alias => $list) {
        $expanded = $list;
        foreach ($list as $key) {
            if (strpos($key, '[0.1?]') !== false) {
                $alt = str_replace('[0.1?]', '[0.1℃]', $key);
                if (!in_array($alt, $expanded, true)) {
                    $expanded[] = $alt;
                }
            }
            if (strpos($key, '[0.1m3/h]') !== false) {
                $expanded[] = str_replace('[0.1m3/h]', '[0.1m3/h]', $key);
            }
        }
        $candidates[$alias] = $expanded;
    }

    $map = $candidates;
    return $map;
}

/**
 * Load all sensor rows whose RecordDate falls in [minStart, maxEnd].
 * Decodes RowJson once per row. Returns list of ['ts' => unix_ts, 'data' => array].
 * SQL Server 2014 has no JSON_VALUE/OPENJSON, so we decode in PHP.
 */
function pbrLoadSensorRecords($conn, DateTime $minStart, DateTime $maxEnd): array
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
        $decoded = json_decode((string) $r['RowJson'], true);
        if (!is_array($decoded)) {
            continue;
        }
        $records[] = ['ts' => $ts, 'data' => $decoded];
    }
    sqlsrv_free_stmt($stmt);

    return $records;
}

/**
 * Compute per-alias averages from pre-loaded records whose ts is in [start, end].
 * Records must be sorted by ts ascending (caller ensures this) so we can early-break.
 */
function pbrComputeAverages(array $records, DateTime $start, DateTime $end): array
{
    $keyMap = pbrSensorKeyMap();
    $averages = array_fill_keys(array_keys($keyMap), null);
    $sums = array_fill_keys(array_keys($keyMap), 0.0);
    $counts = array_fill_keys(array_keys($keyMap), 0);

    $startTs = $start->getTimestamp();
    $endTs = $end->getTimestamp();
    if ($endTs < $startTs) {
        return $averages;
    }

    foreach ($records as $rec) {
        $ts = $rec['ts'];
        if ($ts < $startTs) {
            continue;
        }
        if ($ts > $endTs) {
            break; // sorted, nothing else in window
        }
        $data = $rec['data'];
        foreach ($keyMap as $alias => $candidates) {
            $raw = null;
            foreach ($candidates as $key) {
                if (array_key_exists($key, $data)) {
                    $raw = $data[$key];
                    break;
                }
            }
            if ($raw === null || $raw === '') {
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

/**
 * Single-pass aggregation across many ERP windows.
 * Iterates sorted sensor records exactly once instead of once per window.
 * Mutates each row's 'Sensor' key in place.
 */
function pbrComputeAveragesMulti(array &$rows, array $records): void
{
    $keyMap = pbrSensorKeyMap();
    $aliases = array_keys($keyMap);
    $nullRow = array_fill_keys($aliases, null);

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

    usort($windows, static function ($a, $b) {
        return $a['startTs'] <=> $b['startTs'];
    });

    $windowCount = count($windows);
    $nextStartIdx = 0;

    foreach ($records as $rec) {
        $ts = $rec['ts'];

        while ($nextStartIdx < $windowCount && $windows[$nextStartIdx]['startTs'] <= $ts) {
            $nextStartIdx++;
        }

        if ($nextStartIdx === 0) {
            continue;
        }

        $data = $rec['data'] ?? null;
        if (!is_array($data)) {
            continue;
        }

        for ($i = 0; $i < $nextStartIdx; $i++) {
            $w = &$windows[$i];
            if ($w['endTs'] < $ts) {
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
                    $w['sums'][$alias] += (float) $raw;
                    $w['counts'][$alias]++;
                }
            }
        }
        unset($w);
    }

    foreach ($windows as $w) {
        foreach ($w['sums'] as $alias => $sum) {
            if ($w['counts'][$alias] > 0) {
                $w['result'][$alias] = $sum / $w['counts'][$alias];
            }
        }
        $rows[$w['idx']]['Sensor'] = $w['result'];
    }
}

if ($isFiltered) {
    if ($startDate === '' || $endDate === '') {
        $errorMessage = 'Start Date dan End Date wajib diisi.';
    } else {
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

            // Bulk-load sensor records covering the entire ERP window in one query.
            // SQL Server 2014 has no JSON_VALUE, so we decode JSON in PHP per record.
            $sensorRecords = [];
            if (!empty($results)) {
                $windowStarts = [];
                $windowEnds = [];
                foreach ($results as $tmpRow) {
                    $sdt = parseTimeToDateTime($tmpRow['Start Date'] ?? '', $tmpRow['Start Time'] ?? '');
                    $edt = parseTimeToDateTime($tmpRow['End Date'] ?? '', $tmpRow['End Time'] ?? '');
                    if ($sdt) { $windowStarts[] = $sdt; }
                    if ($edt) { $windowEnds[] = $edt; }
                }
                if ($windowStarts && $windowEnds) {
                    $minStart = clone min($windowStarts);
                    $maxEnd = clone max($windowEnds);
                    $sensorRecords = pbrLoadSensorRecords($conn, $minStart, $maxEnd);
                }
            }

            foreach ($results as &$row) {
                $row['_start_dt'] = parseTimeToDateTime($row['Start Date'] ?? '', $row['Start Time'] ?? '');
                $row['_end_dt'] = parseTimeToDateTime($row['End Date'] ?? '', $row['End Time'] ?? '');
            }
            unset($row);

            // Single-pass aggregation across all ERP windows.
            pbrComputeAveragesMulti($results, $sensorRecords);
        } catch (PDOException $e) {
            $errorMessage = 'Error executing query: ' . $e->getMessage();
        } catch (Throwable $e) {
            $errorMessage = 'Error executing sensor query: ' . $e->getMessage();
        }
    }
}
?>

<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6"><h1 class="m-0">Parameter Analysis PBR</h1></div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                                <h3 class="card-title"><i class="fas fa-filter mr-1"></i> Parameter Analysis PBR</h3>
                            </div>
                            <div class="card-body">
                                <form method="get" class="mb-3">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="startdate">Start Date:</label>
                                                <input type="date" id="startdate" name="startdate" class="form-control form-control-sm" value="<?= htmlspecialchars($startDate) ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="enddate">End Date:</label>
                                                <input type="date" id="enddate" name="enddate" class="form-control form-control-sm" value="<?= htmlspecialchars($endDate) ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6 d-flex align-items-end">
                                            <div class="form-group mb-0">
                                                <button type="submit" name="filter" value="1" class="btn btn-<?php echo htmlspecialchars($themeColor); ?> btn-sm">
                                                    <i class="fas fa-search"></i> Filter
                                                </button>
                                                <?php if ($isFiltered && $errorMessage === '' && !empty($results)) : ?>
                                                    <a href="export_excel_parameteranalysispbr.php?startdate=<?= urlencode($startDate) ?>&enddate=<?= urlencode($endDate) ?>" class="btn btn-success btn-sm ml-2">
                                                        <i class="fas fa-file-excel"></i> Export Excel
                                                    </a>
                                                <?php endif; ?>
                                                <a href="parameteranalysispbr.php" class="btn btn-secondary btn-sm ml-2">
                                                    <i class="fas fa-refresh"></i> Reset
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </form>

                                <?php if ($errorMessage !== '') : ?>
                                    <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
                                <?php endif; ?>

                                <?php if ($isFiltered && $errorMessage === '' && !empty($results)) : ?>
                                    <div class="alert alert-light border">
                                        <strong>Periode:</strong> <?= htmlspecialchars($startDate) ?> s/d <?= htmlspecialchars($endDate) ?>
                                        | <strong>Total Data:</strong> <?= count($results) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($isFiltered && $errorMessage === '' && empty($results)) : ?>
                                    <div class="alert alert-secondary text-center mb-3">
                                        Data tidak ditemukan untuk periode tersebut
                                    </div>
                                <?php endif; ?>

                                <div class="table-responsive" style="overflow-x:auto;">
                                    <style>
                                        #parameterAnalysisTable { min-width: 2400px; }
                                        #parameterAnalysisTable thead th { vertical-align: middle; white-space: nowrap; background: #f4f6f9; position: sticky; top: 0; z-index: 2; }
                                        #parameterAnalysisTable tbody td { vertical-align: middle; }
                                        #parameterAnalysisTable .bg-blue { background: #007bff; color: #fff; }
                                    </style>
                                    <table id="parameterAnalysisTable" class="table table-bordered table-hover table-sm text-nowrap small mb-0">
                                        <thead>
                                            <tr class="text-center align-middle">
                                                <th rowspan="2" class="align-middle">No</th>
                                                <th rowspan="2" class="align-middle">Input Date</th>
                                                <th rowspan="2" class="align-middle">CP No</th>
                                                <th rowspan="2" class="align-middle">Routing</th>
                                                <th rowspan="2" class="align-middle">Article</th>
                                                <th colspan="3" class="align-middle">Process Date</th>
                                                <th rowspan="2" class="bg-blue align-middle">Time (min)</th>
                                                <th colspan="4" class="align-middle">Speed</th>
                                                <th rowspan="2" class="align-middle">Actual Production (M)</th>
                                                <th rowspan="2" class="align-middle">Production Standard (M)</th>
                                                <th rowspan="2" class="align-middle">Efficiency</th>
                                                <th rowspan="2" class="align-middle">Conveyor Speed</th>
                                                <th rowspan="2" class="align-middle">&quot;Cloth Volume<br>[m]&quot;</th>
                                                <th colspan="5" class="align-middle">Temperature A (&#176;C)</th>
                                                <th rowspan="2" class="align-middle">Top Part (&#176;C)</th>
                                                <th rowspan="2" class="align-middle">Boiling Box (&#176;C)</th>
                                                <th colspan="6" class="align-middle">Temperature B (&#176;C)</th>
                                                <th rowspan="2" class="align-middle">Temp Hot Water Tank (&#176;C)</th>
                                                <th rowspan="2" class="align-middle">Temp [No.4] Dryer (&#176;C)</th>
                                                <th rowspan="2" class="align-middle">Front Main Water (m3/h)</th>
                                                <th rowspan="2" class="align-middle">Front Main Steam (kg/h)</th>
                                                <th rowspan="2" class="align-middle">Back Main Water (m3/h)</th>
                                                <th rowspan="2" class="align-middle">Back Main Steam (kg/h)</th>
                                                <th rowspan="2" class="align-middle">&quot;Watt Meter<br>(kW)&quot;</th>
                                            </tr>
                                            <tr class="text-center align-middle">
                                                <th>Date</th>
                                                <th>Start Time</th>
                                                <th>End Time</th>
                                                <th>Actual 1</th>
                                                <th>Actual 2</th>
                                                <th>Rata-Rata</th>
                                                <th>Standard</th>
                                                <th>Washer 1</th>
                                                <th>Washer 2</th>
                                                <th>Washer 3</th>
                                                <th>Washer 4</th>
                                                <th>Washer 5</th>
                                                <th>Washer 1</th>
                                                <th>Washer 2</th>
                                                <th>Washer 3</th>
                                                <th>Washer 4</th>
                                                <th>Washer 5</th>
                                                <th>Washer 6</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($results)) : ?>
                                                <?php $no = 1; ?>
                                                <?php foreach ($results as $row) : ?>
                                                    <?php
                                                        $startDateTime = parseTimeToDateTime($row['Start Date'] ?? '', $row['Start Time'] ?? '');
                                                        $endDateTime = parseTimeToDateTime($row['End Date'] ?? '', $row['End Time'] ?? '');
                                                        $timeMinutes = ($startDateTime && $endDateTime)
                                                            ? ($endDateTime->getTimestamp() - $startDateTime->getTimestamp()) / 60
                                                            : null;

                                                        $speed = is_numeric($row['Speed'] ?? null) ? (float) $row['Speed'] : null;
                                                        $actualProduction = is_numeric($row['Actual Production'] ?? null) ? (float) $row['Actual Production'] : null;
                                                        $standardProduction = ($timeMinutes !== null && $speed !== null) ? $timeMinutes * $speed : null;
                                                        $efficiency = ($standardProduction !== null && $standardProduction > 0 && $actualProduction !== null)
                                                            ? $actualProduction / $standardProduction
                                                            : null;

                                                        $sensor = $row['Sensor'] ?? [];
                                                        $act1 = $sensor['actual_1'] ?? null;
                                                        $act2 = $sensor['actual_2'] ?? null;
                                                        $speedAvg = ($act1 !== null && $act2 !== null)
                                                            ? ($act1 + $act2) / 2
                                                            : null;
                                                        $cell = static function ($alias) use ($sensor) {
                                                            $value = $sensor[$alias] ?? null;
                                                            if ($value === null) {
                                                                return '';
                                                            }
                                                            return htmlspecialchars(number_format((float) $value, 2, '.', ''));
                                                        };
                                                        $cellRaw = static function ($value) {
                                                            if ($value === null) {
                                                                return '';
                                                            }
                                                            return htmlspecialchars(number_format((float) $value, 2, '.', ''));
                                                        };
                                                    ?>
                                                    <tr>
                                                        <td class="text-center"><?= $no++ ?></td>
                                                        <td><?= htmlspecialchars(pbrCellValue($row['Input Date'] ?? '')) ?></td>
                                                        <td><?= htmlspecialchars(pbrCellValue($row['CP No'] ?? '')) ?></td>
                                                        <td><?= htmlspecialchars(pbrCellValue($row['Routing Name'] ?? '')) ?></td>
                                                        <td><?= htmlspecialchars(pbrCellValue($row['Article'] ?? '')) ?></td>
                                                        <td><?= htmlspecialchars(pbrCellValue($row['Start Date'] ?? '')) ?></td>
                                                        <td><?= htmlspecialchars(pbrCellTime($row['Start Time'] ?? '')) ?></td>
                                                        <td><?= htmlspecialchars(pbrCellTime($row['End Time'] ?? '')) ?></td>
                                                        <td class="text-right" style="<?= ($timeMinutes !== null && $timeMinutes < 0) ? 'color:red; font-weight:bold;' : '' ?>">
                                                            <?= $timeMinutes === null ? '' : htmlspecialchars(number_format($timeMinutes, 2, '.', '')) ?>
                                                        </td>
                                                        <td class="text-right"><?= $cell('actual_1') ?></td>
                                                        <td class="text-right"><?= $cell('actual_2') ?></td>
                                                        <td class="text-right"><?= $cellRaw($speedAvg) ?></td>
                                                        <td class="text-right"><?= $cellRaw($speed) ?></td>
                                                        <td class="text-right"><?= $cellRaw($actualProduction) ?></td>
                                                        <td class="text-right"><?= $cellRaw($standardProduction) ?></td>
                                                        <td class="text-right"><?= $efficiency === null ? '' : htmlspecialchars(number_format($efficiency, 4, '.', '')) ?></td>
                                                        <td class="text-right"><?= $cell('conveyor_speed') ?></td>
                                                        <td class="text-right"><?= $cell('cloth_volume') ?></td>
                                                        <td class="text-right"><?= $cell('a_washer_1') ?></td>
                                                        <td class="text-right"><?= $cell('a_washer_2') ?></td>
                                                        <td class="text-right"><?= $cell('a_washer_3') ?></td>
                                                        <td class="text-right"><?= $cell('a_washer_4') ?></td>
                                                        <td class="text-right"><?= $cell('a_washer_5') ?></td>
                                                        <td class="text-right"><?= $cell('top_part') ?></td>
                                                        <td class="text-right"><?= $cell('boiling_box') ?></td>
                                                        <td class="text-right"><?= $cell('b_washer_1') ?></td>
                                                        <td class="text-right"><?= $cell('b_washer_2') ?></td>
                                                        <td class="text-right"><?= $cell('b_washer_3') ?></td>
                                                        <td class="text-right"><?= $cell('b_washer_4') ?></td>
                                                        <td class="text-right"><?= $cell('b_washer_5') ?></td>
                                                        <td class="text-right"><?= $cell('b_washer_6') ?></td>
                                                        <td class="text-right"><?= $cell('hot_water_tank') ?></td>
                                                        <td class="text-right"><?= $cell('no4_dryer') ?></td>
                                                        <td class="text-right"><?= $cell('front_main_water') ?></td>
                                                        <td class="text-right"><?= $cell('front_main_steam') ?></td>
                                                        <td class="text-right"><?= $cell('back_main_water') ?></td>
                                                        <td class="text-right"><?= $cell('back_main_steam') ?></td>
                                                        <td class="text-right"><?= $cell('watt_meter') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script>
    $(document).ready(function() {
        <?php if (!empty($results)) : ?>
        $("#parameterAnalysisTable").DataTable({
            responsive: false,
            autoWidth: false,
            scrollX: true,
            paging: true,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Semua"]],
            info: true,
            language: {
                processing: "Memproses...",
                lengthMenu: "Tampilkan _MENU_ data per halaman",
                zeroRecords: "Tidak ada data ditemukan",
                info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
                infoEmpty: "Tidak ada data tersedia",
                infoFiltered: "(disaring dari _MAX_ total data)",
                search: "Cari:",
                paginate: {
                    first: "Pertama",
                    last: "Terakhir",
                    next: "Selanjutnya",
                    previous: "Sebelumnya"
                }
            }
        });
        <?php endif; ?>
    });
</script>
