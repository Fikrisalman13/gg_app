<?php
// ajax.dataresult_inspecting_weaving_summary.php (with lightweight file caching)
session_start();
header('Content-Type: application/json; charset=utf-8');
include '../../koneksi.php';

// jika belum login, return empty structure (DataTables expects this shape)
if (!isset($_SESSION['UserName'])) {
    echo json_encode([
        'draw' => intval($_GET['draw'] ?? 0),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => []
    ]);
    exit;
}

// ---------- settings ----------
$cacheDir = __DIR__ . '/cache';
@mkdir($cacheDir, 0775, true); // buat jika belum ada (pastikan webserver bisa tulis)
$pageCacheTtl = 30; // detik untuk cache halaman (adjustable)
$countCacheTtl = 60; // detik untuk cache total count
$maxLength = 100; // batas maksimal row/halaman
// --------------------------------

// ambil param DataTables (safety sanitize)
$draw = intval($_GET['draw'] ?? 1);
$start = max(0, intval($_GET['start'] ?? 0));
$length = intval($_GET['length'] ?? 10);
$length = max(1, min($length, $maxLength));
$searchValue = trim($_GET['search']['value'] ?? '');

// base where flag cacat
$baseWhere = "fh.FgCacat = 1";

// mapping kolom untuk order
$columnsMap = [
    0 => null,
    1 => 's.NoCP',
    2 => 's.TotalKodeCacat',
    3 => 's.TotalMeterCacat',
    4 => 's.TotalPointCacat',
    5 => 's.Grade'
];

// build where (search)
$whereClauses = [];
$paramsSearch = [];
if ($searchValue !== '') {
    $whereClauses[] = "(s.NoCP LIKE ? OR s.Grade LIKE ?)";
    $paramsSearch[] = "%{$searchValue}%";
    $paramsSearch[] = "%{$searchValue}%";
}
array_unshift($whereClauses, $baseWhere);
$whereSql = 'WHERE ' . implode(' AND ', $whereClauses);

// build order by
$orderSql = "";
if (isset($_GET['order']) && is_array($_GET['order'])) {
    $orderParts = [];
    foreach ($_GET['order'] as $ord) {
        $colIdx = intval($ord['column']);
        $dir = (strtoupper($ord['dir']) === 'ASC') ? 'ASC' : 'DESC';
        if (isset($columnsMap[$colIdx]) && $columnsMap[$colIdx] !== null) {
            $orderParts[] = $columnsMap[$colIdx] . " " . $dir;
        }
    }
    if (count($orderParts)) {
        $orderSql = "ORDER BY " . implode(', ', $orderParts);
    }
}
if ($orderSql === "") {
    $orderSql = "ORDER BY s.NoCP DESC";
}

// ---------- caching keys (unique per query/order/page) ----------
$cacheKeyParts = [
    'start' => $start,
    'length' => $length,
    'search' => $searchValue,
    'order' => isset($_GET['order']) ? json_encode($_GET['order']) : '',
    'fgc' => 1
];
$cacheKey = md5(json_encode($cacheKeyParts));
$pageCacheFile = $cacheDir . '/page_' . $cacheKey . '.json';

// ---------- check page cache ----------
if (file_exists($pageCacheFile) && (time() - filemtime($pageCacheFile) < $pageCacheTtl)) {
    // baca file cached dan output langsung (sangat cepat)
    echo file_get_contents($pageCacheFile);
    exit;
}

// ---------- total count caching ----------
$countCacheFile = $cacheDir . '/count_fgc1.cache';
$totalData = 0;
if (file_exists($countCacheFile) && (time() - filemtime($countCacheFile) < $countCacheTtl)) {
    $totalData = intval(@file_get_contents($countCacheFile));
} else {
    // hitungan total (tanpa search) — gunakan index di FormInspectHd + SMCacatSummary
    $countTotalSql = "
        SELECT COUNT(*) AS total
        FROM dbo.SMCacatSummary s
        INNER JOIN dbo.FormInspectHd fh ON s.NoCP = fh.NoCP
        WHERE fh.FgCacat = 1
    ";
    $countTotalStmt = sqlsrv_query($conn, $countTotalSql);
    if ($countTotalStmt !== false) {
        $row = sqlsrv_fetch_array($countTotalStmt, SQLSRV_FETCH_ASSOC);
        $totalData = intval($row['total'] ?? 0);
        sqlsrv_free_stmt($countTotalStmt);
        // simpan cache
        @file_put_contents($countCacheFile, (string)$totalData, LOCK_EX);
    } else {
        // fallback ke 0, tapi log error
        // error_log("Count total failed: " . print_r(sqlsrv_errors(), true));
        $totalData = 0;
    }
}

// ---------- total filtered (dengan search) ----------
$countFilteredSql = "
    SELECT COUNT(*) AS total
    FROM dbo.SMCacatSummary s
    INNER JOIN dbo.FormInspectHd fh ON s.NoCP = fh.NoCP
    {$whereSql}
";
$countFilteredStmt = sqlsrv_query($conn, $countFilteredSql, $paramsSearch);
$totalFiltered = $totalData;
if ($countFilteredStmt !== false) {
    $row = sqlsrv_fetch_array($countFilteredStmt, SQLSRV_FETCH_ASSOC);
    $totalFiltered = intval($row['total'] ?? 0);
    sqlsrv_free_stmt($countFilteredStmt);
} else {
    // error_log("Count filtered failed: " . print_r(sqlsrv_errors(), true));
    $totalFiltered = $totalData;
}

// ---------- ambil data (pagination) ----------
$dataSql = "
    SELECT
        s.NoCP,
        ISNULL(s.TotalKodeCacat, 0) AS TotalKodeCacat,
        ISNULL(s.TotalMeterCacat, 0) AS TotalMeterCacat,
        ISNULL(s.TotalPointCacat, 0) AS TotalPointCacat,
        s.PanjangKainI,
        s.Grade
    FROM dbo.SMCacatSummary s
    INNER JOIN dbo.FormInspectHd fh ON s.NoCP = fh.NoCP
    {$whereSql}
    {$orderSql}
    OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
";

// gabungkan params: search params + offset + length
$paramsForData = $paramsSearch;
$paramsForData[] = $start;
$paramsForData[] = $length;

$stmt = sqlsrv_query($conn, $dataSql, $paramsForData);
if ($stmt === false) {
    // jika query gagal, kembalikan struktur kosong dan log
    // error_log("Data query failed: " . print_r(sqlsrv_errors(), true));
    $out = [
        'draw' => $draw,
        'recordsTotal' => $totalData,
        'recordsFiltered' => $totalFiltered,
        'data' => []
    ];
    $jsonOut = json_encode($out);
    // simpan cache kosong juga untuk mencegah flood (opsional)
    @file_put_contents($pageCacheFile, $jsonOut, LOCK_EX);
    echo $jsonOut;
    exit;
}

// helper format number (no trailing .00)
$formatNumber = function($val) {
    if ($val === null) return '0';
    $f = floatval($val);
    if (floor($f) == $f) {
        return (string) intval($f);
    }
    $s = rtrim(rtrim(number_format($f, 2, '.', ''), '0'), '.');
    return $s;
};

$data = [];
$no = $start + 1;
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $noCP = $row['NoCP'];
    $aksi = "<a href='view_result_datacacat.php?noCP=" . urlencode($noCP) . "' class='btn btn-info btn-sm'><i class='fas fa-eye'></i> View</a>";

    $meter_display = $formatNumber($row['TotalMeterCacat']);
    $point_display = $formatNumber($row['TotalPointCacat']);

    $data[] = [
        'no' => $no++,
        'NoCP' => htmlspecialchars($noCP),
        'TotalKodeCacat' => intval($row['TotalKodeCacat'] ?? 0),
        'TotalMeterCacat' => is_numeric($row['TotalMeterCacat']) ? (float)$row['TotalMeterCacat'] : 0,
        'TotalMeterCacat_display' => $meter_display,
        'TotalPointCacat' => is_numeric($row['TotalPointCacat']) ? (float)$row['TotalPointCacat'] : 0,
        'TotalPointCacat_display' => $point_display,
        'Grade' => $row['Grade'] ?? 'C',
        'Aksi' => $aksi
    ];
}
sqlsrv_free_stmt($stmt);

// prepare output
$out = [
    'draw' => $draw,
    'recordsTotal' => $totalData,
    'recordsFiltered' => $totalFiltered,
    'data' => $data
];

$jsonOut = json_encode($out);

// simpan cache file untuk halaman ini
@file_put_contents($pageCacheFile, $jsonOut, LOCK_EX);

// output
echo $jsonOut;
exit;
