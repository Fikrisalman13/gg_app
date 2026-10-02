<?php
session_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once '../../koneksi.php';
date_default_timezone_set('Asia/Jakarta');

function esc($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function numberId($value): string
{
    return number_format((int)$value, 0, ',', '.');
}

function normalizeDateRange(string $start, string $end): array
{
    $startDate = DateTime::createFromFormat('Y-m-d', $start) ?: new DateTime(date('Y-m-d'));
    $endDate = DateTime::createFromFormat('Y-m-d', $end) ?: new DateTime(date('Y-m-d'));
    if ($startDate > $endDate) {
        $tmp = $startDate;
        $startDate = $endDate;
        $endDate = $tmp;
    }
    return [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')];
}

function buildDateRangeKeys(string $startDate, string $endDate, int $maxDays = 31): array
{
    $start = DateTime::createFromFormat('Y-m-d', $startDate) ?: new DateTime(date('Y-m-d'));
    $end = DateTime::createFromFormat('Y-m-d', $endDate) ?: new DateTime(date('Y-m-d'));
    if ($start > $end) {
        $tmp = $start;
        $start = $end;
        $end = $tmp;
    }

    $totalDays = ((int)$start->diff($end)->format('%a')) + 1;
    $truncated = $totalDays > $maxDays;

    $dates = [];
    $cursor = clone $start;
    $count = 0;
    while ($cursor <= $end && $count < $maxDays) {
        $dates[] = $cursor->format('Y-m-d');
        $cursor->modify('+1 day');
        $count++;
    }

    return ['dates' => $dates, 'truncated' => $truncated];
}

function checkUserPermissions($conn, $groupId, $menuId): array
{
    $sql = "SELECT CanView FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    $permissions = ['CanView' => 0];
    if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
        $permissions = $row;
    }
    if ($stmt) {
        sqlsrv_free_stmt($stmt);
    }
    return $permissions;
}

function getMatrixMachines($conn, string $mesinFilter): array
{
    $params = [];
    $sql = "SELECT CAST(id AS NVARCHAR(100)) AS id, COALESCE(NULLIF(nama_mesin, ''), '-') AS nama_mesin
            FROM dbo.m_fingerprint";
    if ($mesinFilter !== '') {
        $sql .= " WHERE CAST(id AS NVARCHAR(100)) = ?";
        $params[] = $mesinFilter;
    }
    $sql .= " ORDER BY nama_mesin ASC";

    $rows = [];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
    return $rows;
}

if (!$conn) {
    die('Koneksi database gagal.');
}

if (!isset($_SESSION['UserName'])) {
    die('Session expired. Silakan login kembali.');
}

$groupId = $_SESSION['GroupId'] ?? 0;
$menuId = 126;
$permissions = checkUserPermissions($conn, $groupId, $menuId);
if (($permissions['CanView'] ?? 0) == 0) {
    die('Tidak ada akses.');
}

$mesinFilter = isset($_GET['mesin_id']) ? trim((string)$_GET['mesin_id']) : '';
if ($mesinFilter === '' && isset($_GET['mesin'])) {
    $mesinFilter = trim((string)$_GET['mesin']);
}

$tanggalAwal = isset($_GET['tanggal_awal']) && $_GET['tanggal_awal'] !== '' ? (string)$_GET['tanggal_awal'] : date('Y-m-d');
$tanggalAkhir = isset($_GET['tanggal_akhir']) && $_GET['tanggal_akhir'] !== '' ? (string)$_GET['tanggal_akhir'] : date('Y-m-d');
[$tanggalAwal, $tanggalAkhir] = normalizeDateRange($tanggalAwal, $tanggalAkhir);

$dateRange = buildDateRangeKeys($tanggalAwal, $tanggalAkhir, 31);
$matrixDates = $dateRange['dates'];
$matrixWarning = $dateRange['truncated'] ? 'Rentang tanggal lebih dari 31 hari. File menampilkan maksimal 31 hari pertama.' : '';

$machines = getMatrixMachines($conn, $mesinFilter);
$shiftColumns = ['Shift Pagi', 'Shift Siang', 'Shift Malam', 'Non Shift', 'No All'];

$matrixRows = [];
foreach ($machines as $machine) {
    $cells = [];
    foreach ($matrixDates as $dateKey) {
        $shiftCounts = [];
        foreach ($shiftColumns as $shiftCol) {
            $shiftCounts[$shiftCol] = 0;
        }
        $cells[$dateKey] = ['total' => 0, 'shift_counts' => $shiftCounts];
    }
    $matrixRows[(string)($machine['id'] ?? '')] = [
        'nama_mesin' => (string)($machine['nama_mesin'] ?? '-'),
        'cells' => $cells
    ];
}

$shiftCaseSql = "CASE
    WHEN CAST(k.Waktu AS TIME) BETWEEN '11:30:00' AND '13:30:00' THEN 'Non Shift'
    WHEN CAST(k.Waktu AS TIME) BETWEEN '01:00:00' AND '04:00:00' THEN 'Shift Malam'
    WHEN CAST(k.Waktu AS TIME) BETWEEN '09:00:00' AND '11:30:00' THEN 'Shift Pagi'
    WHEN CAST(k.Waktu AS TIME) BETWEEN '17:00:00' AND '20:00:00' THEN 'Shift Siang'
    WHEN CAST(k.Waktu AS TIME) BETWEEN '04:00:00' AND '09:00:00' THEN 'No All'
    WHEN CAST(k.Waktu AS TIME) BETWEEN '14:00:00' AND '17:00:00' THEN 'No All'
    WHEN CAST(k.Waktu AS TIME) >= '20:00:00' OR CAST(k.Waktu AS TIME) <= '01:00:00' THEN 'No All'
    ELSE 'Unknown' END";

$sql = "SELECT
            CAST(k.MesinId AS NVARCHAR(100)) AS mesin_id,
            CAST(k.Waktu AS DATE) AS tanggal,
            {$shiftCaseSql} AS shift_name,
            COUNT(*) AS total
        FROM dbo.LogDashboardKantin AS k
        WHERE CAST(k.Waktu AS DATE) BETWEEN ? AND ?
          AND ISNULL(k.IsRejected, 0) = 0";
$params = [$tanggalAwal, $tanggalAkhir];
if ($mesinFilter !== '') {
    $sql .= " AND CAST(k.MesinId AS NVARCHAR(100)) = ?";
    $params[] = $mesinFilter;
}
$sql .= " GROUP BY CAST(k.MesinId AS NVARCHAR(100)), CAST(k.Waktu AS DATE), {$shiftCaseSql}
          ORDER BY CAST(k.MesinId AS NVARCHAR(100)), CAST(k.Waktu AS DATE)";

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $mesinId = (string)($row['mesin_id'] ?? '');
        if ($mesinId === '' || !isset($matrixRows[$mesinId])) {
            continue;
        }

        $tanggalValue = $row['tanggal'] ?? null;
        $dateKey = ($tanggalValue instanceof DateTime) ? $tanggalValue->format('Y-m-d') : (strtotime((string)$tanggalValue) ? date('Y-m-d', strtotime((string)$tanggalValue)) : '');
        if ($dateKey === '' || !in_array($dateKey, $matrixDates, true)) {
            continue;
        }

        $shiftName = trim((string)($row['shift_name'] ?? ''));
        $total = (int)($row['total'] ?? 0);

        $matrixRows[$mesinId]['cells'][$dateKey]['total'] += $total;
        if (isset($matrixRows[$mesinId]['cells'][$dateKey]['shift_counts'][$shiftName])) {
            $matrixRows[$mesinId]['cells'][$dateKey]['shift_counts'][$shiftName] += $total;
        }
    }
    sqlsrv_free_stmt($stmt);
}

$selectedMachineLabel = 'Semua Mesin';
if ($mesinFilter !== '') {
    foreach ($machines as $machine) {
        if ((string)($machine['id'] ?? '') === $mesinFilter) {
            $selectedMachineLabel = (string)($machine['nama_mesin'] ?? 'Mesin Terpilih');
            break;
        }
    }
}

$safeStart = str_replace('-', '', $tanggalAwal);
$safeEnd = str_replace('-', '', $tanggalAkhir);
$filename = 'Summary_Absen_Berhasil_' . $safeStart . '_sd_' . $safeEnd . '_' . date('Ymd_His') . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo '<html><head><meta charset="utf-8"></head><body>';
echo '<h3>Summary Absensi Per Mesin (Absen Berhasil)</h3>';
echo '<table border="0" style="margin-bottom:10px;">';
echo '<tr><td><b>Periode</b></td><td>: ' . esc(date('d-m-Y', strtotime($tanggalAwal))) . ' s/d ' . esc(date('d-m-Y', strtotime($tanggalAkhir))) . '</td></tr>';
echo '<tr><td><b>Mesin</b></td><td>: ' . esc($selectedMachineLabel) . '</td></tr>';
echo '<tr><td><b>Export Time</b></td><td>: ' . esc(date('d-m-Y H:i:s')) . ' WIB</td></tr>';
echo '</table>';

if ($matrixWarning !== '') {
    echo '<div style="margin-bottom:10px;color:#8a6d3b;">' . esc($matrixWarning) . '</div>';
}

echo '<table border="1" cellpadding="4" cellspacing="0">';
echo '<thead>';
echo '<tr style="background:#e9eef5;text-align:center;font-weight:bold;">';
echo '<th rowspan="2">Mesin</th>';
foreach ($matrixDates as $dateKey) {
    echo '<th colspan="6">' . esc(date('d/m/Y', strtotime($dateKey))) . '</th>';
}
echo '</tr>';
echo '<tr style="background:#f2f6fb;text-align:center;font-weight:bold;">';
foreach ($matrixDates as $_dateKey) {
    echo '<th>Shift Pagi</th><th>Shift Siang</th><th>Shift Malam</th><th>Non Shift</th><th>No All</th><th>Total Absensi</th>';
}
echo '</tr>';
echo '</thead><tbody>';

if (empty($matrixRows) || empty($matrixDates)) {
    $colspan = max(1, (count($matrixDates) * 6) + 1);
    echo '<tr><td colspan="' . $colspan . '" style="text-align:center;">Tidak ada data absen berhasil untuk ditampilkan.</td></tr>';
} else {
    foreach ($matrixRows as $row) {
        echo '<tr>';
        echo '<td>' . esc($row['nama_mesin'] ?? '-') . '</td>';
        foreach ($matrixDates as $dateKey) {
            $cell = $row['cells'][$dateKey] ?? ['total' => 0, 'shift_counts' => []];
            $shiftCounts = is_array($cell['shift_counts'] ?? null) ? $cell['shift_counts'] : [];
            $totalCell = (int)($cell['total'] ?? 0);

            $vals = [
                (int)($shiftCounts['Shift Pagi'] ?? 0),
                (int)($shiftCounts['Shift Siang'] ?? 0),
                (int)($shiftCounts['Shift Malam'] ?? 0),
                (int)($shiftCounts['Non Shift'] ?? 0),
                (int)($shiftCounts['No All'] ?? 0),
                $totalCell
            ];
            foreach ($vals as $idx => $v) {
                $style = ($idx === 5) ? ' style="text-align:center;font-weight:bold;"' : ' style="text-align:center;"';
                echo '<td' . $style . '>' . ($v > 0 ? esc(numberId($v)) : '') . '</td>';
            }
        }
        echo '</tr>';
    }
}

echo '</tbody></table>';
echo '</body></html>';

sqlsrv_close($conn);
exit;
?>
