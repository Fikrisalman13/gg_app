<?php
session_start();
ob_start();

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
$entryFrom = isset($_GET['entry_from']) ? trim($_GET['entry_from']) : '';
$entryTo = isset($_GET['entry_to']) ? trim($_GET['entry_to']) : '';
$workcenterId = isset($_GET['workcenterid']) ? trim($_GET['workcenterid']) : '111';
$routingStartId = isset($_GET['routing_start_id']) ? trim($_GET['routing_start_id']) : '';
$routingEndId = isset($_GET['routing_end_id']) ? trim($_GET['routing_end_id']) : '';
$selectedRoutingStartLabel = '';
$selectedRoutingEndLabel = '';
$defaultRoutingStartId = '';
$defaultRoutingEndId = '';
$workcenterOptions = [
    '111' => 'DYEING',
    '118' => 'SIZING BARU',
    '125' => 'WARPING BARU',
    '100' => 'WEAVING BARU',
];

if (!isset($workcenterOptions[$workcenterId])) {
    $workcenterId = '111';
}

$routingOptionSql = "SELECT DISTINCT
    m.rtgmsid,
    COALESCE(m.rtgcode, '') AS rtgcode,
    COALESCE(m.rtgname, '') AS rtgname
FROM pdproductionrtg r
JOIN pdproductionhd h
    ON h.productionhdid = r.productionhdid
JOIN pdrtgms m
    ON m.rtgmsid = r.rtgmsid
WHERE h.workcenterid = :workcenterid
  AND COALESCE(m.rtgname, '') <> ''
ORDER BY rtgcode, rtgname";
$routingOptionStmt = $conn3->prepare($routingOptionSql);
$routingOptionStmt->bindValue(':workcenterid', $workcenterId);
$routingOptionStmt->execute();
$routingOptions = $routingOptionStmt->fetchAll(PDO::FETCH_ASSOC);
$validRoutingIds = array_map('strval', array_column($routingOptions, 'rtgmsid'));

foreach ($routingOptions as $routingOption) {
    $optionId = (string) ($routingOption['rtgmsid'] ?? '');
    $optionCode = strtoupper(trim((string) ($routingOption['rtgcode'] ?? '')));
    $optionName = strtoupper(trim((string) ($routingOption['rtgname'] ?? '')));
    $optionLabel = trim(trim((string) ($routingOption['rtgcode'] ?? '')) . ' - ' . trim((string) ($routingOption['rtgname'] ?? '')), ' -');

    if ($defaultRoutingStartId === '' && $optionCode === '01000' && $optionName === 'PEMARTAIAN') {
        $defaultRoutingStartId = $optionId;
    }
    if ($defaultRoutingEndId === '' && $optionCode === '09900' && $optionName === 'VERPACKING') {
        $defaultRoutingEndId = $optionId;
    }
    if ($routingStartId !== '' && $routingStartId === $optionId) {
        $selectedRoutingStartLabel = $optionLabel;
    }
    if ($routingEndId !== '' && $routingEndId === $optionId) {
        $selectedRoutingEndLabel = $optionLabel;
    }
}

if ($routingStartId === '') {
    $routingStartId = $defaultRoutingStartId;
}
if ($routingEndId === '') {
    $routingEndId = $defaultRoutingEndId;
}
if ($routingStartId !== '' && !in_array($routingStartId, $validRoutingIds, true)) {
    $routingStartId = $defaultRoutingStartId;
}
if ($routingEndId !== '' && !in_array($routingEndId, $validRoutingIds, true)) {
    $routingEndId = $defaultRoutingEndId;
}

if ($selectedRoutingStartLabel === '' || $selectedRoutingEndLabel === '') {
    foreach ($routingOptions as $routingOption) {
        $optionId = (string) ($routingOption['rtgmsid'] ?? '');
        $optionLabel = trim(trim((string) ($routingOption['rtgcode'] ?? '')) . ' - ' . trim((string) ($routingOption['rtgname'] ?? '')), ' -');
        if ($selectedRoutingStartLabel === '' && $routingStartId === $optionId) {
            $selectedRoutingStartLabel = $optionLabel;
        }
        if ($selectedRoutingEndLabel === '' && $routingEndId === $optionId) {
            $selectedRoutingEndLabel = $optionLabel;
        }
    }
}

if ($cpno === '' && ($dateFrom === '' || $dateTo === '') && $entryFrom === '' && $entryTo === '' && $routingStartId === '' && $routingEndId === '') {
    die("No CP, rentang tanggal, entry time, atau routing wajib diisi.");
}

function formatDurationMinutesSeconds($startTime, $endTime)
{
    if (empty($startTime) || empty($endTime)) {
        return '';
    }

    $startTimestamp = strtotime($startTime);
    $endTimestamp = strtotime($endTime);
    if ($startTimestamp === false || $endTimestamp === false || $endTimestamp < $startTimestamp) {
        return '';
    }

    $totalSeconds = $endTimestamp - $startTimestamp;
    $minutes = floor($totalSeconds / 60);
    $seconds = $totalSeconds % 60;

    return $minutes . ' menit ' . $seconds . ' detik';
}

$whereConditions = ["h.workcenterid = :workcenterid"];
$params = [':workcenterid' => $workcenterId];

if ($cpno !== '') {
    $whereConditions[] = "h.prdnmbr = :cpno";
    $params[':cpno'] = $cpno;
}

if ($dateFrom !== '' && $dateTo !== '') {
    $whereConditions[] = "h.prddate BETWEEN :date_from AND :date_to";
    $params[':date_from'] = $dateFrom;
    $params[':date_to'] = $dateTo;
}

if ($entryFrom !== '') {
    $params[':entry_from'] = $entryFrom . ' 00:00:00';
}

if ($entryTo !== '') {
    $params[':entry_to'] = $entryTo . ' 23:59:59';
}

if ($routingStartId !== '') {
    $params[':routing_start_id'] = $routingStartId;
}

if ($routingEndId !== '') {
    $params[':routing_end_id'] = $routingEndId;
}

$query = "WITH hd AS (
    SELECT
        h.productionhdid,
        h.prdnmbr,
        h.prddate
    FROM pdproductionhd h
    WHERE " . implode("\n      AND ", $whereConditions) . "
),
start_bounds AS (
    SELECT
        r.productionhdid,
        MIN(r.rtgseq) AS start_seq
    FROM pdproductionrtg r
    JOIN hd h
        ON h.productionhdid = r.productionhdid
    WHERE 1 = 1
      " . ($routingStartId !== '' ? "AND r.rtgmsid = :routing_start_id" : "") . "
    GROUP BY r.productionhdid
),
end_bounds AS (
    SELECT
        r.productionhdid,
        MIN(r.rtgseq) AS end_seq
    FROM pdproductionrtg r
    JOIN start_bounds sb
        ON sb.productionhdid = r.productionhdid
    WHERE sb.start_seq IS NOT NULL
      AND r.rtgseq >= sb.start_seq
      AND r.fgresult = 'P'
      " . ($routingEndId !== '' ? "AND r.rtgmsid = :routing_end_id" : "") . "
      " . ($entryFrom !== '' ? "AND r.starttime >= :entry_from" : "") . "
      " . ($entryTo !== '' ? "AND r.starttime <= :entry_to" : "") . "
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
JOIN start_bounds sb
    ON sb.productionhdid = r.productionhdid
JOIN end_bounds eb
    ON eb.productionhdid = r.productionhdid
LEFT JOIN pdrtgms m
    ON m.rtgmsid = r.rtgmsid
LEFT JOIN msuser u
    ON u.userid = r.upduser
LEFT JOIN smemployee e
    ON e.empid = u.empid
WHERE sb.start_seq IS NOT NULL
  AND eb.end_seq IS NOT NULL
  AND r.rtgseq BETWEEN sb.start_seq AND eb.end_seq
ORDER BY h.prdnmbr, r.rtgseq";

$stmt = $conn3->prepare($query);
foreach ($params as $paramName => $paramValue) {
    $stmt->bindValue($paramName, $paramValue);
}

$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = 'History_CP_Routing';
if ($cpno !== '') {
    $filename .= '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $cpno);
} elseif ($dateFrom !== '' && $dateTo !== '') {
    $filename .= '_' . $dateFrom . '_to_' . $dateTo;
}
if ($selectedRoutingStartLabel !== '' || $selectedRoutingEndLabel !== '') {
    $filename .= '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', trim($selectedRoutingStartLabel . '_to_' . $selectedRoutingEndLabel, '_'));
}
$filename .= '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $workcenterOptions[$workcenterId]);
$filename .= '.xls';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=" . $filename);
header("Pragma: no-cache");
header("Expires: 0");

echo "<table border='1'>";
echo "<tr><td colspan='12' style='font-weight:bold; text-align:center; background-color:#e0e0e0;'>HISTORY CP ROUTING</td></tr>";

$filterInfo = [];
if ($cpno !== '') {
    $filterInfo[] = "No CP: " . htmlspecialchars($cpno);
}
if ($dateFrom !== '' && $dateTo !== '') {
    $filterInfo[] = "Production Date Start: " . htmlspecialchars($dateFrom);
    $filterInfo[] = "Production Date End: " . htmlspecialchars($dateTo);
}
if ($entryFrom !== '') {
    $filterInfo[] = "Entry Time Start: " . htmlspecialchars($entryFrom . ' 00:00:00');
}
if ($entryTo !== '') {
    $filterInfo[] = "Entry Time End: " . htmlspecialchars($entryTo . ' 23:59:59');
}
$filterInfo[] = "Work Center: " . htmlspecialchars($workcenterOptions[$workcenterId]);
if ($selectedRoutingStartLabel !== '') {
    $filterInfo[] = "Routing Start: " . htmlspecialchars($selectedRoutingStartLabel);
}
if ($selectedRoutingEndLabel !== '') {
    $filterInfo[] = "Routing End: " . htmlspecialchars($selectedRoutingEndLabel);
}
echo "<tr><td colspan='12' style='font-weight:bold;'>Filter: " . implode(" | ", $filterInfo) . "</td></tr>";
echo "<tr><td colspan='12' style='font-weight:bold;'>Export Date: " . date("d/m/Y H:i") . "</td></tr>";
echo "<tr><td colspan='12'></td></tr>";

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
    <th>Update By</th>
    <th>Emp Name</th>
    <th>Update</th>
</tr></thead><tbody>";

$no = 1;
foreach ($rows as $row) {
    echo "<tr>";
    echo "<td>" . $no++ . "</td>";
    echo "<td>" . htmlspecialchars($row['prdnmbr'] ?? '') . "</td>";
    echo "<td>" . (!empty($row['prddate']) ? date("d/m/Y", strtotime($row['prddate'])) : '') . "</td>";
    echo "<td>" . htmlspecialchars($row['rtgseq'] ?? '') . "</td>";
    echo "<td>" . htmlspecialchars($row['rtgcode'] ?? '') . "</td>";
    echo "<td>" . htmlspecialchars($row['rtgname'] ?? '') . "</td>";
    echo "<td>" . (!empty($row['starttime']) ? date("d/m/Y H:i", strtotime($row['starttime'])) : '') . "</td>";
    echo "<td>" . (!empty($row['endtime']) ? date("d/m/Y H:i", strtotime($row['endtime'])) : '') . "</td>";
    echo "<td>" . htmlspecialchars(formatDurationMinutesSeconds($row['starttime'] ?? '', $row['endtime'] ?? '')) . "</td>";
    echo "<td>" . htmlspecialchars($row['upduser'] ?? '') . "</td>";
    echo "<td>" . htmlspecialchars($row['empname'] ?? '') . "</td>";
    echo "<td>" . (!empty($row['upddate']) ? date("d/m/Y H:i", strtotime($row['upddate'])) : '') . "</td>";
    echo "</tr>";
}

echo "<tr><td colspan='12' style='font-weight:bold; background-color:#f0f0f0;'>Total Records: " . count($rows) . "</td></tr>";
echo "</tbody></table>";
