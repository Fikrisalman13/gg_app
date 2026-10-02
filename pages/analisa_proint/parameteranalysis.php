<?php
session_start();
ob_start();

include '../../koneksi.php';
include '../../koneksi3.php';
include '../../koneksi4.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
date_default_timezone_set('Asia/Jakarta');

if (!$conn3) {
    die("Koneksi ke database gagal");
}

$startDate = isset($_GET['startdate']) ? trim($_GET['startdate']) : date('Y-m-01');
$endDate = isset($_GET['enddate']) ? trim($_GET['enddate']) : date('Y-m-d');
$results = [];
$errorMessage = '';
$isFiltered = isset($_GET['filter']);

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
            'temp_ch_3' => null,
            'temp_ch_4' => null,
        ];
    }

    $speedTotal = 0.0;
    $tempCh1Total = 0.0;
    $tempCh2Total = 0.0;
    $tempCh3Total = 0.0;
    $tempCh4Total = 0.0;
    $speedCount = 0;
    $tempCh1Count = 0;
    $tempCh2Count = 0;
    $tempCh3Count = 0;
    $tempCh4Count = 0;

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

        if ($log['hct3'] !== null) {
            $tempCh3Total += $log['hct3'];
            $tempCh3Count++;
        }

        if ($log['hct4'] !== null) {
            $tempCh4Total += $log['hct4'];
            $tempCh4Count++;
        }
    }

    return [
        'speed' => $speedCount > 0 ? $speedTotal / $speedCount : null,
        'temp_ch_1' => $tempCh1Count > 0 ? $tempCh1Total / $tempCh1Count : null,
        'temp_ch_2' => $tempCh2Count > 0 ? $tempCh2Total / $tempCh2Count : null,
        'temp_ch_3' => $tempCh3Count > 0 ? $tempCh3Total / $tempCh3Count : null,
        'temp_ch_4' => $tempCh4Count > 0 ? $tempCh4Total / $tempCh4Count : null,
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
            'hct3' => normalizeNumericValue($row['HCT3'] ?? null),
            'hct4' => normalizeNumericValue($row['HCT4'] ?? null),
        ];
    }

    sqlsrv_free_stmt($stmt);

    return $logs;
}

if ($isFiltered) {
    if ($startDate === '' || $endDate === '') {
        $errorMessage = 'Start Date dan End Date wajib diisi.';
    } elseif ($startDate > $endDate) {
        $errorMessage = 'Start Date tidak boleh lebih besar dari End Date.';
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
                            a.Value19 AS HCT2,
                            NULL AS HCT3,
                            NULL AS HCT4
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
                            a.Value25 AS HCT2,
                            a.Value27 AS HCT3,
                            a.Value29 AS HCT4
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
                            c.Value08 AS HCT2,
                            a.Value30 AS HCT3,
                            a.Value31 AS HCT4
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
                            c.Value08 AS HCT2,
                            a.Value30 AS HCT3,
                            a.Value31 AS HCT4
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
                $row['Temp. Ch 3 Mesin'] = null;
                $row['Temp. Ch 4 Mesin'] = null;

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
                $row['Temp. Ch 3 Mesin'] = $machineAverages['temp_ch_3'];
                $row['Temp. Ch 4 Mesin'] = $machineAverages['temp_ch_4'];
            }
            unset($row);
        } catch (PDOException $e) {
            $errorMessage = 'Error executing query: ' . $e->getMessage();
        }
    }
}
?>

<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Parameter Analysis</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item active">Parameter Analysis</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                                <h3 class="card-title">
                                    <i class="fas fa-filter mr-1"></i> Parameter Analysis
                                </h3>
                            </div>
                            <div class="card-body">
                                <form method="get" class="mb-3">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="startdate">Start Date:</label>
                                                <input
                                                    type="date"
                                                    id="startdate"
                                                    name="startdate"
                                                    class="form-control form-control-sm"
                                                    value="<?= htmlspecialchars($startDate) ?>"
                                                    required
                                                >
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="enddate">End Date:</label>
                                                <input
                                                    type="date"
                                                    id="enddate"
                                                    name="enddate"
                                                    class="form-control form-control-sm"
                                                    value="<?= htmlspecialchars($endDate) ?>"
                                                    required
                                                >
                                            </div>
                                        </div>
                                        <div class="col-md-6 d-flex align-items-end">
                                            <div class="form-group mb-0">
                                                <button type="submit" name="filter" value="1" class="btn btn-<?php echo htmlspecialchars($themeColor); ?> btn-sm">
                                                    <i class="fas fa-search"></i> Filter
                                                </button>
                                                <?php if ($isFiltered && $errorMessage === '' && !empty($results)) : ?>
                                                    <a
                                                        href="export_excel_parameteranalysis.php?startdate=<?= urlencode($startDate) ?>&enddate=<?= urlencode($endDate) ?>"
                                                        class="btn btn-success btn-sm ml-2"
                                                    >
                                                        <i class="fas fa-file-excel"></i> Export Excel
                                                    </a>
                                                <?php endif; ?>
                                                <a href="parameteranalysis.php" class="btn btn-secondary btn-sm ml-2">
                                                    <i class="fas fa-refresh"></i> Reset
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </form>

                                <?php if ($errorMessage !== '') : ?>
                                    <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
                                <?php endif; ?>

                                <?php if ($isFiltered && $errorMessage === '') : ?>
                                    <div class="alert alert-light border">
                                        <strong>Periode:</strong> <?= htmlspecialchars($startDate) ?> s/d <?= htmlspecialchars($endDate) ?>
                                        | <strong>Total Data:</strong> <?= count($results) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (empty($results)) : ?>
                                    <div class="alert alert-secondary text-center mb-3">
                                        <?= $isFiltered ? 'Data tidak ditemukan untuk periode tersebut' : 'Silakan pilih Start Date dan End Date lalu klik Filter' ?>
                                    </div>
                                <?php endif; ?>

                                <div class="table-responsive">
                                    <table id="parameterAnalysisTable" class="table table-hover table-sm">
                                        <thead class="thead-light">
                                            <tr class="text-center align-middle">
                                                <th>No</th>
                                                <th>Input Date</th>
                                                <th>CP No</th>
                                                <th>Article</th>
                                                <th>Color Code</th>
                                                <th>Color Name</th>
                                                <th>Biaya Obat</th>
                                                <th>Start Time</th>
                                                <th>End Time</th>
                                                <th class="bg-primary text-white">Time (min)</th>
                                                <th>No. MC</th>
                                                <th>Speed</th>
                                                <th>Speed Mesin</th>
                                                <th class="bg-primary text-white">Standard Production</th>
                                                <th>Actual Production</th>
                                                <th class="bg-primary text-white">Effic</th>
                                                <th>Temp. Ch 1</th>
                                                <th>Temp. Ch 2</th>
                                                <th>Temp. Ch 1 Mesin</th>
                                                <th>Temp. Ch 2 Mesin</th>
                                                <th>Temp. Ch 3 Mesin</th>
                                                <th>Temp. Ch 4 Mesin</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($results)) : ?>
                                                <?php $no = 1; ?>
                                                <?php foreach ($results as $row) : ?>
                                                    <?php
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
                                                    ?>
                                                    <tr>
                                                        <td class="text-center"><?= $no++ ?></td>
                                                        <td><?= htmlspecialchars($row['Input Date'] ?? '') ?></td>
                                                        <td><?= htmlspecialchars($row['CP No'] ?? '') ?></td>
                                                        <td><?= htmlspecialchars($row['Article'] ?? '') ?></td>
                                                        <td><?= htmlspecialchars($row['Color Code'] ?? '') ?></td>
                                                        <td><?= htmlspecialchars($row['Color Name'] ?? '') ?></td>
                                                        <td class="text-right"><?= htmlspecialchars($row['Biaya Obat'] ?? '') ?></td>
                                                        <td><?= htmlspecialchars(formatTimeCell($row['Start Time'] ?? '')) ?></td>
                                                        <td><?= htmlspecialchars(formatTimeCell($row['End Time'] ?? '')) ?></td>
                                                        <td class="text-right"><?= $timeMinutes === '' ? '' : htmlspecialchars(number_format($timeMinutes, 2, '.', '')) ?></td>
                                                        <td><?= htmlspecialchars($row['No. MC'] ?? '') ?></td>
                                                        <td class="text-right"><?= htmlspecialchars($row['Speed'] ?? '') ?></td>
                                                        <td class="text-right"><?= isset($row['Speed Mesin']) && $row['Speed Mesin'] !== null ? htmlspecialchars(number_format((float) $row['Speed Mesin'], 2, '.', '')) : '' ?></td>
                                                        <td class="text-right"><?= $standardProduction === '' ? '' : htmlspecialchars(number_format($standardProduction, 2, '.', '')) ?></td>
                                                        <td class="text-right"><?= htmlspecialchars($row['Actual Production'] ?? '') ?></td>
                                                        <td class="text-right"><?= $effic === '' ? '' : htmlspecialchars(number_format($effic, 4, '.', '')) ?></td>
                                                        <td class="text-right"><?= htmlspecialchars($row['Temp. Ch 1'] ?? '') ?></td>
                                                        <td class="text-right"><?= htmlspecialchars($row['Temp. Ch 2'] ?? '') ?></td>
                                                        <td class="text-right"><?= isset($row['Temp. Ch 1 Mesin']) && $row['Temp. Ch 1 Mesin'] !== null ? htmlspecialchars(number_format((float) $row['Temp. Ch 1 Mesin'], 2, '.', '')) : '' ?></td>
                                                        <td class="text-right"><?= isset($row['Temp. Ch 2 Mesin']) && $row['Temp. Ch 2 Mesin'] !== null ? htmlspecialchars(number_format((float) $row['Temp. Ch 2 Mesin'], 2, '.', '')) : '' ?></td>
                                                        <td class="text-right"><?= isset($row['Temp. Ch 3 Mesin']) && $row['Temp. Ch 3 Mesin'] !== null ? htmlspecialchars(number_format((float) $row['Temp. Ch 3 Mesin'], 2, '.', '')) : '' ?></td>
                                                        <td class="text-right"><?= isset($row['Temp. Ch 4 Mesin']) && $row['Temp. Ch 4 Mesin'] !== null ? htmlspecialchars(number_format((float) $row['Temp. Ch 4 Mesin'], 2, '.', '')) : '' ?></td>
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
                responsive: true,
                autoWidth: false,
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
