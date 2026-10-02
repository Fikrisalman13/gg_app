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

$cpno = isset($_GET['cpno']) ? trim($_GET['cpno']) : '';
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$workcenterId = '111';

if ($cpno === '' && ($dateFrom === '' || $dateTo === '')) {
    die("No CP atau rentang tanggal wajib diisi.");
}

// Ambil daftar rtgmsid yg sudah disimpan (SQL Server - $conn)
$selectedRtgmsIds = [];
$filterQuery = "SELECT rtgmsid FROM monitoringstartstopkurang_filter WHERE is_active = 1 ORDER BY id";
$filterStmt = sqlsrv_query($conn, $filterQuery);
if ($filterStmt) {
    while ($fRow = sqlsrv_fetch_array($filterStmt, SQLSRV_FETCH_ASSOC)) {
        $selectedRtgmsIds[] = (int)$fRow['rtgmsid'];
    }
    sqlsrv_free_stmt($filterStmt);
}

function getDurationSeconds($startTime, $endTime)
{
    if (empty($startTime) || empty($endTime)) {
        return null;
    }

    $startTimestamp = strtotime($startTime);
    $endTimestamp = strtotime($endTime);
    if ($startTimestamp === false || $endTimestamp === false || $endTimestamp < $startTimestamp) {
        return null;
    }

    return $endTimestamp - $startTimestamp;
}

function formatSecondsToHMS($seconds)
{
    if ($seconds === null) {
        return '';
    }

    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);

    return sprintf('%02d:%02d:00', $hours, $minutes);
}

function formatDurationHMS($startTime, $endTime)
{
    return formatSecondsToHMS(getDurationSeconds($startTime, $endTime));
}

// Bangun klausa filter rtgmsid
$rtgmsFilter = '';
if (!empty($selectedRtgmsIds)) {
    $placeholders = [];
    for ($i = 0; $i < count($selectedRtgmsIds); $i++) {
        $placeholders[] = ':rtgmsid_' . $i;
    }
    $rtgmsFilter = ' AND r.rtgmsid IN (' . implode(',', $placeholders) . ')';
}

if ($cpno !== '') {
    $query = "WITH hd AS (
        SELECT 
            productionhdid, 
            prdnmbr,
            prddate
        FROM pdproductionhd
        WHERE prdnmbr = :cpno
          AND workcenterid = :workcenterid
    ),
    last_p AS (
        SELECT 
            r.productionhdid,
            MAX(r.rtgseq) AS last_p_seq
        FROM pdproductionrtg r
        JOIN hd h 
            ON h.productionhdid = r.productionhdid
        GROUP BY r.productionhdid
    )
    SELECT 
        h.prdnmbr,
        h.prddate,
        r.rtgseq,
        r.rtgmsid,
        m.rtgname,
        m.rtgcode,
        r.starttime,
        r.endtime,
        r.upddate,
        r.upduser,
        e.empname
    FROM pdproductionrtg r
    JOIN hd h 
        ON h.productionhdid = r.productionhdid
    JOIN last_p lp 
        ON lp.productionhdid = r.productionhdid
    LEFT JOIN pdrtgms m 
        ON m.rtgmsid = r.rtgmsid
    LEFT JOIN msuser u
        ON u.userid = r.upduser
    LEFT JOIN smemployee e
        ON e.empid = u.empid
    WHERE r.rtgseq <= lp.last_p_seq + 1" . $rtgmsFilter . "
    ORDER BY h.prdnmbr, r.rtgseq";
    $stmt = $conn3->prepare($query);
    $stmt->bindValue(':cpno', $cpno);
    $stmt->bindValue(':workcenterid', $workcenterId);
    for ($i = 0; $i < count($selectedRtgmsIds); $i++) {
        $stmt->bindValue(':rtgmsid_' . $i, $selectedRtgmsIds[$i], PDO::PARAM_INT);
    }
} else {
    $query = "WITH hd AS (
        SELECT 
            productionhdid, 
            prdnmbr,
            prddate
        FROM pdproductionhd
        WHERE prddate BETWEEN :date_from AND :date_to
          AND workcenterid = :workcenterid
    ),
    last_p AS (
        SELECT 
            r.productionhdid,
            MAX(r.rtgseq) AS last_p_seq
        FROM pdproductionrtg r
        JOIN hd h 
            ON h.productionhdid = r.productionhdid
        GROUP BY r.productionhdid
    )
    SELECT 
        h.prdnmbr,
        h.prddate,
        r.rtgseq,
        r.rtgmsid,
        m.rtgname,
        m.rtgcode,
        r.starttime,
        r.endtime,
        r.upddate,
        r.upduser,
        e.empname
    FROM pdproductionrtg r
    JOIN hd h 
        ON h.productionhdid = r.productionhdid
    JOIN last_p lp 
        ON lp.productionhdid = r.productionhdid
    LEFT JOIN pdrtgms m 
        ON m.rtgmsid = r.rtgmsid
    LEFT JOIN msuser u
        ON u.userid = r.upduser
    LEFT JOIN smemployee e
        ON e.empid = u.empid
    WHERE r.rtgseq <= lp.last_p_seq + 1" . $rtgmsFilter . "
    ORDER BY h.prdnmbr, r.rtgseq";
    $stmt = $conn3->prepare($query);
    $stmt->bindValue(':date_from', $dateFrom);
    $stmt->bindValue(':date_to', $dateTo);
    $stmt->bindValue(':workcenterid', $workcenterId);
    for ($i = 0; $i < count($selectedRtgmsIds); $i++) {
        $stmt->bindValue(':rtgmsid_' . $i, $selectedRtgmsIds[$i], PDO::PARAM_INT);
    }
}

$stmt->execute();
$allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter: hanya data dengan durasi <= 30 menit (1800 detik)
$rows = [];
foreach ($allRows as $row) {
    $durationSeconds = getDurationSeconds($row['starttime'] ?? '', $row['endtime'] ?? '');
    if ($durationSeconds !== null && $durationSeconds <= 1800) {
        $rows[] = $row;
    }
}

$filename = 'Monitoring_StartStop_Kurang';
if ($cpno !== '') {
    $filename .= '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $cpno);
} else {
    $filename .= '_' . $dateFrom . '_to_' . $dateTo;
}
$filename .= '.xls';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=" . $filename);
header("Pragma: no-cache");
header("Expires: 0");

echo "<table border='1'>";
echo "<tr><td colspan='13' style='font-weight:bold; text-align:center; background-color:#e0e0e0;'>MONITORING START STOP KURANG</td></tr>";

$filterInfo = [];
if ($cpno !== '') {
    $filterInfo[] = "No CP: " . htmlspecialchars($cpno);
} else {
    $filterInfo[] = "Tanggal Dari: " . htmlspecialchars($dateFrom);
    $filterInfo[] = "Sampai Tanggal: " . htmlspecialchars($dateTo);
}




echo "<tr><td colspan='13' style='font-weight:bold;'>Filter: " . implode(" | ", $filterInfo) . "</td></tr>";
echo "<tr><td colspan='13' style='font-weight:bold;'>Export Date: " . date("d/m/Y H:i") . "</td></tr>";
echo "<tr><td colspan='13'></td></tr>";

echo "<thead><tr style='background-color:#f0f0f0; font-weight:bold;'>
    <th>No</th>
    <th>No CP</th>
    <th>Production Date</th>
    <th>Routing Seq</th>
    <th>Routing Code</th>
    <th>Routing Name</th>
    <th>Start Time</th>
    <th>End Time</th>
    <th>Durasi</th>
    <th>Total Waktu Pengerjaan</th>
    <th>Update By</th>
    <th>Emp Name</th>
    <th>Update</th>
</tr></thead><tbody>";

$totalDurations = [];
foreach ($rows as $row) {
    $cpNumber = trim((string) ($row['prdnmbr'] ?? ''));
    $durationSeconds = getDurationSeconds($row['starttime'] ?? '', $row['endtime'] ?? '');
    if ($durationSeconds !== null) {
        if (!isset($totalDurations[$cpNumber])) {
            $totalDurations[$cpNumber] = 0;
        }
        $totalDurations[$cpNumber] += $durationSeconds;
    }
}

$no = 1;
foreach ($rows as $row) {
    $cpNumber = trim((string) ($row['prdnmbr'] ?? ''));
    $durationSeconds = getDurationSeconds($row['starttime'] ?? '', $row['endtime'] ?? '');
    $duration = formatSecondsToHMS($durationSeconds);
    $totalPerCp = formatSecondsToHMS($totalDurations[$cpNumber] ?? null);

    echo "<tr>";
    echo "<td>" . $no++ . "</td>";
    echo "<td>" . htmlspecialchars($row['prdnmbr'] ?? '') . "</td>";
    echo "<td>" . (!empty($row['prddate']) ? date("d/m/Y", strtotime($row['prddate'])) : '') . "</td>";
    echo "<td>" . htmlspecialchars($row['rtgseq'] ?? '') . "</td>";
    echo "<td>" . htmlspecialchars($row['rtgcode'] ?? '') . "</td>";
    echo "<td>" . htmlspecialchars($row['rtgname'] ?? '') . "</td>";
    echo "<td>" . (!empty($row['starttime']) ? date("d/m/Y H:i", strtotime($row['starttime'])) : '') . "</td>";
    echo "<td>" . (!empty($row['endtime']) ? date("d/m/Y H:i", strtotime($row['endtime'])) : '') . "</td>";
    echo "<td>" . htmlspecialchars($duration) . "</td>";
    echo "<td>" . htmlspecialchars($totalPerCp) . "</td>";
    echo "<td>" . htmlspecialchars($row['upduser'] ?? '') . "</td>";
    echo "<td>" . htmlspecialchars($row['empname'] ?? '') . "</td>";
    echo "<td>" . (!empty($row['upddate']) ? date("d/m/Y H:i", strtotime($row['upddate'])) : '') . "</td>";
    echo "</tr>";
}

echo "<tr><td colspan='13' style='font-weight:bold; background-color:#f0f0f0;'>Total Records: " . count($rows) . "</td></tr>";
echo "</tbody></table>";
