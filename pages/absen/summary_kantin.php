<?php
session_start();
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);

include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

date_default_timezone_set('Asia/Jakarta');

$groupId = $_SESSION['GroupId'] ?? 0;
$menuId = 126; // MenuId Absensi Kantin

function esc($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function checkUserPermissions($conn, $groupId, $menuId): array
{
    static $permissionsCache = [];

    $cacheKey = $groupId . '_' . $menuId;
    if (isset($permissionsCache[$cacheKey])) {
        return $permissionsCache[$cacheKey];
    }

    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete
            FROM dbo.SMGroupTrustee
            WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);

    $permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    if ($stmt !== false) {
        if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $permissions = $row;
        }
        sqlsrv_free_stmt($stmt);
    }

    $permissionsCache[$cacheKey] = $permissions;
    return $permissions;
}

function getAllMesin($conn): array
{
    static $mesinCache = null;

    if ($mesinCache !== null) {
        return $mesinCache;
    }

    $sql = "SELECT id, nama_mesin FROM dbo.m_fingerprint ORDER BY nama_mesin";
    $stmt = sqlsrv_query($conn, $sql);
    $mesinData = [];

    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $mesinData[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }

    $mesinCache = $mesinData;
    return $mesinData;
}

function getShiftRanges(): array
{
    return [
        'non_shift' => [
            'name' => 'Non Shift',
            'start' => '11:30:00',
            'end' => '13:30:00',
            'date_adjustment' => 0
        ],
        'shift_malam' => [
            'name' => 'Shift Malam',
            'start' => '01:00:00',
            'end' => '04:00:00',
            'date_adjustment' => 1
        ],
        'shift_pagi' => [
            'name' => 'Shift Pagi',
            'start' => '09:00:00',
            'end' => '11:30:00',
            'date_adjustment' => 0
        ],
        'shift_siang' => [
            'name' => 'Shift Siang',
            'start' => '17:00:00',
            'end' => '20:00:00',
            'date_adjustment' => 0
        ],
        'no_all' => [
            'name' => 'No All',
            'ranges' => [
                ['start' => '04:00:00', 'end' => '09:00:00', 'date_adjustment' => 0],
                ['start' => '14:00:00', 'end' => '17:00:00', 'date_adjustment' => 0],
                ['start' => '20:00:00', 'end' => '01:00:00', 'date_adjustment' => 1]
            ]
        ]
    ];
}

function applyShiftFilter(&$sql, &$params, $shift, $tanggalAwal, $tanggalAkhir): void
{
    $shiftRanges = getShiftRanges();
    if (empty($shift) || !isset($shiftRanges[$shift])) {
        return;
    }

    $range = $shiftRanges[$shift];

    if ($shift == 'no_all') {
        $sql .= " AND (";
        $first = true;
        foreach ($range['ranges'] as $subRange) {
            if (!$first) {
                $sql .= " OR ";
            }

            if ($subRange['date_adjustment'] == 0) {
                $sql .= " CAST(a.waktu AS TIME) BETWEEN ? AND ?";
                $params[] = $subRange['start'];
                $params[] = $subRange['end'];
            } else {
                $sql .= " (CAST(a.waktu AS TIME) BETWEEN ? AND '23:59:59' AND CAST(a.waktu AS DATE) = ?)";
                $params[] = $subRange['start'];
                $params[] = $tanggalAwal;

                $sql .= " OR (CAST(a.waktu AS TIME) BETWEEN '00:00:00' AND ? AND CAST(a.waktu AS DATE) = DATEADD(DAY, 1, ?))";
                $params[] = $subRange['end'];
                $params[] = $tanggalAwal;
            }
            $first = false;
        }
        $sql .= ")";
        return;
    }

    if ($range['date_adjustment'] == 0) {
        $sql .= " AND CAST(a.waktu AS TIME) BETWEEN ? AND ?";
        $params[] = $range['start'];
        $params[] = $range['end'];
    } else {
        if ($shift == 'shift_malam') {
            $sql .= " AND (CAST(a.waktu AS TIME) BETWEEN ? AND '23:59:59' AND CAST(a.waktu AS DATE) = ?)";
            $params[] = $range['start'];
            $params[] = $tanggalAwal;

            $sql .= " OR (CAST(a.waktu AS TIME) BETWEEN '00:00:00' AND ? AND CAST(a.waktu AS DATE) = DATEADD(DAY, 1, ?))";
            $params[] = $range['end'];
            $params[] = $tanggalAwal;
        }
    }
}

function numberId($value): string
{
    return number_format((int)$value, 0, ',', '.');
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

    return [
        'dates' => $dates,
        'truncated' => $truncated
    ];
}

$permissions = checkUserPermissions($conn, $groupId, $menuId);
if (($permissions['CanView'] ?? 0) == 0) {
    $error_message = "Anda tidak memiliki hak untuk melihat halaman ini.";
}

$filters = [
    'tanggal_awal' => $_GET['tanggal_awal'] ?? date('Y-m-01'),
    'tanggal_akhir' => $_GET['tanggal_akhir'] ?? date('Y-m-d'),
    'mesin' => $_GET['mesin'] ?? '',
    'shift' => $_GET['shift'] ?? ''
];

$mesinData = getAllMesin($conn);
$shiftRanges = getShiftRanges();

$kpi = [
    'total_absensi' => 0,
    'total_karyawan' => 0,
    'total_mesin' => 0,
    'hari_aktif' => 0,
    'avg_harian' => 0
];
$matrixDates = [];
$matrixRows = [];
$matrixWarning = '';
$matrixShiftColumns = ['Shift Pagi', 'Shift Siang', 'Shift Malam', 'Non Shift', 'No All'];
$duplicateRows = [];
$queryError = null;

if (!isset($error_message)) {
    $baseSql = " FROM dbo.log_absensi AS a
                 LEFT JOIN dbo.m_fingerprint AS f ON a.mesin_id = f.id
                 LEFT JOIN dbo.m_emp AS e ON a.user_id = e.id_emp
                 LEFT JOIN dbo.m_dept AS d ON e.id_dept = d.id_dept
                 LEFT JOIN dbo.m_bag AS b ON e.id_bag = b.id_bag
                 LEFT JOIN dbo.m_subbag AS s ON e.id_subbag = s.id_subbag
                 WHERE 1=1";

    $whereSql = $baseSql;
    $whereParams = [];

    if (!empty($filters['mesin'])) {
        $whereSql .= " AND f.id = ?";
        $whereParams[] = $filters['mesin'];
    }
    if (!empty($filters['tanggal_awal'])) {
        $whereSql .= " AND CAST(a.waktu AS DATE) >= ?";
        $whereParams[] = $filters['tanggal_awal'];
    }
    if (!empty($filters['tanggal_akhir'])) {
        $whereSql .= " AND CAST(a.waktu AS DATE) <= ?";
        $whereParams[] = $filters['tanggal_akhir'];
    }
    if (!empty($filters['shift'])) {
        applyShiftFilter($whereSql, $whereParams, $filters['shift'], $filters['tanggal_awal'], $filters['tanggal_akhir']);
    }

    $kpiSql = "SELECT
                    COUNT(*) AS total_absensi,
                    COUNT(DISTINCT CASE WHEN a.user_id IS NULL OR CAST(a.user_id AS NVARCHAR(100)) = '' THEN a.nama ELSE CAST(a.user_id AS NVARCHAR(100)) END) AS total_karyawan,
                    COUNT(DISTINCT f.id) AS total_mesin,
                    COUNT(DISTINCT CAST(a.waktu AS DATE)) AS hari_aktif
               " . $whereSql;
    $kpiStmt = sqlsrv_query($conn, $kpiSql, $whereParams);
    if ($kpiStmt && ($row = sqlsrv_fetch_array($kpiStmt, SQLSRV_FETCH_ASSOC))) {
        $kpi['total_absensi'] = (int)($row['total_absensi'] ?? 0);
        $kpi['total_karyawan'] = (int)($row['total_karyawan'] ?? 0);
        $kpi['total_mesin'] = (int)($row['total_mesin'] ?? 0);
        $kpi['hari_aktif'] = (int)($row['hari_aktif'] ?? 0);
        $kpi['avg_harian'] = $kpi['hari_aktif'] > 0 ? round($kpi['total_absensi'] / $kpi['hari_aktif'], 2) : 0;
    } else {
        $queryError = 'Gagal memuat ringkasan KPI.';
    }
    if ($kpiStmt) {
        sqlsrv_free_stmt($kpiStmt);
    }

    if ($queryError === null) {
        $dateRange = buildDateRangeKeys($filters['tanggal_awal'], $filters['tanggal_akhir'], 31);
        $matrixDates = $dateRange['dates'];
        if ($dateRange['truncated']) {
            $matrixWarning = 'Rentang tanggal lebih dari 31 hari. Tabel ditampilkan maksimal 31 hari pertama.';
        }

        $selectedMesin = [];
        foreach ($mesinData as $mesinRow) {
            if (!empty($filters['mesin']) && (string)$mesinRow['id'] !== (string)$filters['mesin']) {
                continue;
            }
            $selectedMesin[] = $mesinRow;
        }

        foreach ($selectedMesin as $mesinRow) {
            $cells = [];
            foreach ($matrixDates as $dateKey) {
                $shiftCounts = [];
                foreach ($matrixShiftColumns as $shiftCol) {
                    $shiftCounts[$shiftCol] = 0;
                }
                $cells[$dateKey] = [
                    'total' => 0,
                    'shift_counts' => $shiftCounts
                ];
            }
            $matrixRows[(string)$mesinRow['id']] = [
                'mesin_id' => (string)$mesinRow['id'],
                'nama_mesin' => (string)($mesinRow['nama_mesin'] ?? '-'),
                'cells' => $cells
            ];
        }

        $shiftCaseSql = "CASE
                            WHEN CAST(a.waktu AS TIME) BETWEEN '11:30:00' AND '13:30:00' THEN 'Non Shift'
                            WHEN CAST(a.waktu AS TIME) BETWEEN '01:00:00' AND '04:00:00' THEN 'Shift Malam'
                            WHEN CAST(a.waktu AS TIME) BETWEEN '09:00:00' AND '11:30:00' THEN 'Shift Pagi'
                            WHEN CAST(a.waktu AS TIME) BETWEEN '17:00:00' AND '20:00:00' THEN 'Shift Siang'
                            WHEN CAST(a.waktu AS TIME) BETWEEN '04:00:00' AND '09:00:00' THEN 'No All'
                            WHEN CAST(a.waktu AS TIME) BETWEEN '14:00:00' AND '17:00:00' THEN 'No All'
                            WHEN CAST(a.waktu AS TIME) >= '20:00:00' OR CAST(a.waktu AS TIME) <= '01:00:00' THEN 'No All'
                            ELSE 'Unknown'
                         END";

        $matrixSql = "SELECT
                        CAST(f.id AS NVARCHAR(100)) AS mesin_id,
                        COALESCE(NULLIF(f.nama_mesin, ''), '-') AS nama_mesin,
                        CAST(a.waktu AS DATE) AS tanggal,
                        $shiftCaseSql AS shift_name,
                        COUNT(*) AS total
                      " . $whereSql . "
                      GROUP BY
                        CAST(f.id AS NVARCHAR(100)),
                        COALESCE(NULLIF(f.nama_mesin, ''), '-'),
                        CAST(a.waktu AS DATE),
                        $shiftCaseSql
                      ORDER BY
                        COALESCE(NULLIF(f.nama_mesin, ''), '-'),
                        CAST(a.waktu AS DATE)";

        $matrixStmt = sqlsrv_query($conn, $matrixSql, $whereParams);
        if ($matrixStmt) {
            while ($row = sqlsrv_fetch_array($matrixStmt, SQLSRV_FETCH_ASSOC)) {
                $mesinId = (string)($row['mesin_id'] ?? '');
                if ($mesinId === '') {
                    continue;
                }

                $tanggalValue = $row['tanggal'] ?? null;
                if ($tanggalValue instanceof DateTime) {
                    $dateKey = $tanggalValue->format('Y-m-d');
                } else {
                    $timestamp = strtotime((string)$tanggalValue);
                    $dateKey = $timestamp ? date('Y-m-d', $timestamp) : '';
                }

                if ($dateKey === '' || !in_array($dateKey, $matrixDates, true)) {
                    continue;
                }

                if (!isset($matrixRows[$mesinId])) {
                    $cells = [];
                    foreach ($matrixDates as $dateInit) {
                        $shiftCounts = [];
                        foreach ($matrixShiftColumns as $shiftCol) {
                            $shiftCounts[$shiftCol] = 0;
                        }
                        $cells[$dateInit] = [
                            'total' => 0,
                            'shift_counts' => $shiftCounts
                        ];
                    }
                    $matrixRows[$mesinId] = [
                        'mesin_id' => $mesinId,
                        'nama_mesin' => (string)($row['nama_mesin'] ?? '-'),
                        'cells' => $cells
                    ];
                }

                $shiftName = trim((string)($row['shift_name'] ?? 'Unknown'));
                if ($shiftName === '') {
                    $shiftName = 'Unknown';
                }
                $totalValue = (int)($row['total'] ?? 0);

                $matrixRows[$mesinId]['cells'][$dateKey]['total'] += $totalValue;
                if (isset($matrixRows[$mesinId]['cells'][$dateKey]['shift_counts'][$shiftName])) {
                    $matrixRows[$mesinId]['cells'][$dateKey]['shift_counts'][$shiftName] += $totalValue;
                }
            }
            sqlsrv_free_stmt($matrixStmt);
        }

        $duplicateSql = "SELECT
                            CAST(a.waktu AS DATE) AS tanggal,
                            COALESCE(NULLIF(a.nama, ''), '-') AS nama_karyawan,
                            COALESCE(NULLIF(d.dept, ''), '-') AS departemen,
                            COALESCE(NULLIF(b.bagian, ''), '-') AS bagian,
                            COUNT(*) AS total_masuk
                         " . $whereSql . "
                         GROUP BY
                            CAST(a.waktu AS DATE),
                            COALESCE(NULLIF(a.nama, ''), '-'),
                            COALESCE(NULLIF(d.dept, ''), '-'),
                            COALESCE(NULLIF(b.bagian, ''), '-')
                         HAVING COUNT(*) >= 2
                         ORDER BY
                            CAST(a.waktu AS DATE) DESC,
                            COALESCE(NULLIF(a.nama, ''), '-') ASC";

        $duplicateStmt = sqlsrv_query($conn, $duplicateSql, $whereParams);
        if ($duplicateStmt) {
            while ($row = sqlsrv_fetch_array($duplicateStmt, SQLSRV_FETCH_ASSOC)) {
                $duplicateRows[] = $row;
            }
            sqlsrv_free_stmt($duplicateStmt);
        }
    }
}
?>

<style>
    .summary-page .content-header {
        padding-bottom: 0.25rem;
    }
    .summary-main-card {
        border: 0;
        border-radius: 10px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        overflow: hidden;
    }
    .summary-main-card > .card-header {
        border-bottom: 0;
        padding: 0.85rem 1rem;
    }
    .summary-main-card > .card-body {
        background: #f7f9fb;
        padding: 1rem;
    }
    .summary-filter {
        background-color: #ffffff;
        padding: 14px 14px 12px;
        border-radius: 8px;
        border: 1px solid #e4e8ee;
        margin-bottom: 14px;
    }
    .summary-actions .btn {
        margin-right: 6px;
        margin-bottom: 6px;
    }
    .summary-stat {
        border: 1px solid #e4e8ee;
        border-left: 4px solid #17a2b8;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .summary-stat:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 14px rgba(0, 0, 0, 0.08);
    }
    .summary-stat .summary-label {
        font-size: 0.76rem;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        margin-bottom: 0.2rem;
    }
    .summary-stat .summary-value {
        font-size: 1.6rem;
        font-weight: 700;
        line-height: 1.15;
        color: #1f2d3d;
    }
    .matrix-card {
        border: 1px solid #e4e8ee;
        border-radius: 8px;
        overflow: hidden;
        margin-top: 12px;
    }
    .matrix-card .card-header {
        background: #f4f7fb;
        border-bottom: 1px solid #e4e8ee;
        padding: 0.7rem 0.9rem;
    }
    .matrix-hint {
        font-size: 0.78rem;
        color: #6c757d;
        margin-top: 0.2rem;
    }
    .matrix-wrap {
        max-height: 68vh;
        overflow: auto;
    }
    .matrix-table th,
    .matrix-table td {
        border: 1px solid #cfd4da;
        vertical-align: middle;
        white-space: nowrap;
    }
    .matrix-table thead th {
        background: #eef2f6;
        color: #1f2d3d;
        font-weight: 700;
        text-align: center;
    }
    .matrix-table tbody tr:nth-child(even) {
        background: #fbfcfd;
    }
    .matrix-table td:first-child,
    .matrix-table th:first-child {
        position: sticky;
        left: 0;
        z-index: 2;
        background: #ffffff;
        font-weight: 600;
    }
    .matrix-table thead th:first-child {
        z-index: 4;
        background: #e6ecf3;
    }
    .matrix-table td:not(:first-child):nth-child(6n+1),
    .matrix-table thead tr:last-child th:nth-child(6n+1) {
        background: #f2f8ff;
        font-weight: 700;
    }
    .dup-table th {
        background: #eef2f6;
        text-align: center;
        white-space: nowrap;
    }
    .dup-table td {
        vertical-align: middle;
    }
    .dup-badge {
        font-size: 0.76rem;
        font-weight: 600;
        padding: 4px 8px;
    }
    @media (max-width: 767.98px) {
        .summary-main-card > .card-body {
            padding: 0.8rem;
        }
        .summary-stat .summary-value {
            font-size: 1.35rem;
        }
        .matrix-wrap {
            max-height: 60vh;
        }
    }
</style>

<div class="wrapper summary-page">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row align-items-center mb-2">
                    <div class="col-md-6">
                        <h1 class="m-0">Summary Absensi Kantin</h1>
                    </div>
                    <div class="col-md-6 text-md-right text-sm-left mt-sm-2 mt-md-0">
                        <ol class="breadcrumb float-md-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item"><a href="absensi_kantin.php">Absensi Kantin</a></li>
                            <li class="breadcrumb-item active">Summary</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?= esc($error_message) ?></div>
                <?php else: ?>
                    <?php if ($queryError !== null): ?>
                        <div class="alert alert-danger"><?= esc($queryError) ?></div>
                    <?php endif; ?>

                    <div class="card summary-main-card">
                        <div class="card-header bg-<?= esc($themeColor) ?> text-white">
                            <h3 class="card-title m-0">Filter Ringkasan</h3>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="summary-filter">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="small">Tanggal Awal</label>
                                        <input type="date" name="tanggal_awal" class="form-control form-control-sm" value="<?= esc($filters['tanggal_awal']) ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="small">Tanggal Akhir</label>
                                        <input type="date" name="tanggal_akhir" class="form-control form-control-sm" value="<?= esc($filters['tanggal_akhir']) ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="small">Mesin</label>
                                        <select name="mesin" class="form-control form-control-sm">
                                            <option value="">Semua Mesin</option>
                                            <?php foreach ($mesinData as $mesin): ?>
                                                <option value="<?= esc($mesin['id']) ?>" <?= ($filters['mesin'] == $mesin['id']) ? 'selected' : '' ?>>
                                                    <?= esc($mesin['nama_mesin']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="small">Tarikan Shift</label>
                                        <select name="shift" class="form-control form-control-sm">
                                            <option value="">Semua Shift</option>
                                            <?php foreach ($shiftRanges as $key => $shift): ?>
                                                <?php if ($key === 'no_all'): ?>
                                                    <option value="<?= esc($key) ?>" <?= ($filters['shift'] == $key) ? 'selected' : '' ?>>
                                                        <?= esc($shift['name']) ?> (04:00-09:00, 14:00-17:00, 20:00-01:00)
                                                    </option>
                                                <?php else: ?>
                                                    <option value="<?= esc($key) ?>" <?= ($filters['shift'] == $key) ? 'selected' : '' ?>>
                                                        <?= esc($shift['name']) ?> (<?= esc($shift['start']) ?> - <?= esc($shift['end']) ?>)
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-12 summary-actions">
                                        <button type="submit" class="btn btn-<?= esc($themeColor) ?> btn-sm">
                                            <i class="fas fa-filter"></i> Tampilkan Summary
                                        </button>
                                        <a href="summary_kantin.php" class="btn btn-secondary btn-sm">
                                            <i class="fas fa-eraser"></i> Reset Filter
                                        </a>
                                        <a href="absensi_kantin.php?<?= esc(http_build_query($filters)) ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-table"></i> Lihat Data Detail
                                        </a>
                                    </div>
                                </div>
                            </form>

                            <div class="row">
                                <div class="col-md-3">
                                    <div class="card summary-stat">
                                        <div class="card-body">
                                            <div class="summary-label">Total Absensi</div>
                                            <div class="summary-value"><?= numberId($kpi['total_absensi']) ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card summary-stat">
                                        <div class="card-body">
                                            <div class="summary-label">Karyawan Unik</div>
                                            <div class="summary-value"><?= numberId($kpi['total_karyawan']) ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card summary-stat">
                                        <div class="card-body">
                                            <div class="summary-label">Mesin Aktif</div>
                                            <div class="summary-value"><?= numberId($kpi['total_mesin']) ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card summary-stat">
                                        <div class="card-body">
                                            <div class="summary-label">Rata-rata/Hari</div>
                                            <div class="summary-value"><?= esc(number_format((float)$kpi['avg_harian'], 2, ',', '.')) ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card matrix-card">
                                <div class="card-header">
                                    <h3 class="card-title m-0">Tabel Summary Absensi Per Mesin</h3>
                                    <div class="matrix-hint">
                                        Geser horizontal untuk melihat tanggal lain. Kolom "Mesin" tetap terlihat.
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <?php if ($matrixWarning !== ''): ?>
                                        <div class="alert alert-warning m-3 mb-0 py-2"><?= esc($matrixWarning) ?></div>
                                    <?php endif; ?>
                                    <div class="table-responsive matrix-wrap">
                                        <table class="table table-sm matrix-table mb-0">
                                            <thead>
                                                <tr class="text-center">
                                                    <th rowspan="2" class="align-middle">Mesin</th>
                                                    <?php foreach ($matrixDates as $dateKey): ?>
                                                        <th colspan="6"><?= esc(date('d/m/Y', strtotime($dateKey))) ?></th>
                                                    <?php endforeach; ?>
                                                </tr>
                                                <tr class="text-center">
                                                    <?php foreach ($matrixDates as $dateKey): ?>
                                                        <th>Shift Pagi</th>
                                                        <th>Shift Siang</th>
                                                        <th>Shift Malam</th>
                                                        <th>Non Shift</th>
                                                        <th>No All</th>
                                                        <th>Total Absensi</th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($matrixRows) || empty($matrixDates)): ?>
                                                    <tr>
                                                        <td colspan="<?= esc((string)max(1, (count($matrixDates) * 6) + 1)) ?>" class="text-center text-muted">
                                                            Tidak ada data untuk ditampilkan.
                                                        </td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($matrixRows as $matrixRow): ?>
                                                        <tr>
                                                            <td><?= esc($matrixRow['nama_mesin'] ?? '-') ?></td>
                                                            <?php foreach ($matrixDates as $dateKey): ?>
                                                                <?php
                                                                    $cell = $matrixRow['cells'][$dateKey] ?? ['total' => 0, 'shift_counts' => []];
                                                                    $totalCell = (int)($cell['total'] ?? 0);
                                                                    $shiftCounts = is_array($cell['shift_counts'] ?? null) ? $cell['shift_counts'] : [];
                                                                ?>
                                                                <td class="text-center"><?= (($shiftCounts['Shift Pagi'] ?? 0) > 0) ? esc(numberId($shiftCounts['Shift Pagi'])) : '' ?></td>
                                                                <td class="text-center"><?= (($shiftCounts['Shift Siang'] ?? 0) > 0) ? esc(numberId($shiftCounts['Shift Siang'])) : '' ?></td>
                                                                <td class="text-center"><?= (($shiftCounts['Shift Malam'] ?? 0) > 0) ? esc(numberId($shiftCounts['Shift Malam'])) : '' ?></td>
                                                                <td class="text-center"><?= (($shiftCounts['Non Shift'] ?? 0) > 0) ? esc(numberId($shiftCounts['Non Shift'])) : '' ?></td>
                                                                <td class="text-center"><?= (($shiftCounts['No All'] ?? 0) > 0) ? esc(numberId($shiftCounts['No All'])) : '' ?></td>
                                                                <td class="text-center"><?= $totalCell > 0 ? esc(numberId($totalCell)) : '' ?></td>
                                                            <?php endforeach; ?>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="card matrix-card mt-3">
                                <div class="card-header">
                                    <h3 class="card-title m-0">Pelanggaran Absen Kantin (Lebih Dari 1 Kali)</h3>
                                    <div class="matrix-hint">
                                        Menampilkan karyawan yang absen kantin 2x atau lebih dalam 1 hari.
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover dup-table mb-0">
                                            <thead>
                                                <tr>
                                                    <th style="width: 60px;">No</th>
                                                    <th>Tanggal</th>
                                                    <th>Nama Karyawan</th>
                                                    <th>Departemen</th>
                                                    <th>Bagian</th>
                                                    <th>Jumlah Absen Kantin</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($duplicateRows)): ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center text-muted py-3">Tidak ada pelanggaran absen kantin (2x atau lebih per hari).</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php $dupNo = 1; ?>
                                                    <?php foreach ($duplicateRows as $dupRow): ?>
                                                        <?php
                                                            $tanggalDup = '-';
                                                            if (($dupRow['tanggal'] ?? null) instanceof DateTime) {
                                                                $tanggalDup = $dupRow['tanggal']->format('d-m-Y');
                                                            } elseif (!empty($dupRow['tanggal'])) {
                                                                $tanggalDupTs = strtotime((string)$dupRow['tanggal']);
                                                                $tanggalDup = $tanggalDupTs ? date('d-m-Y', $tanggalDupTs) : (string)$dupRow['tanggal'];
                                                            }

                                                            $totalMasuk = (int)($dupRow['total_masuk'] ?? 0);
                                                        ?>
                                                        <tr>
                                                            <td class="text-center"><?= esc((string)$dupNo++) ?></td>
                                                            <td class="text-center"><?= esc($tanggalDup) ?></td>
                                                            <td><?= esc($dupRow['nama_karyawan'] ?? '-') ?></td>
                                                            <td><?= esc($dupRow['departemen'] ?? '-') ?></td>
                                                            <td><?= esc($dupRow['bagian'] ?? '-') ?></td>
                                                            <td class="text-center">
                                                                <span class="badge badge-warning dup-badge"><?= esc(numberId($totalMasuk)) ?>x</span>
                                                            </td>
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
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
