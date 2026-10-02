<?php
session_start();
include '../../koneksi.php';

// Path cache
$cacheFile = __DIR__ . '/planning_cache.json';
$workerPath = __DIR__ . '/generate_planning_cache.php';

if (!isset($_SESSION['UserName'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$currUser = $_SESSION['UserName'] ?? '';
$groupId = $_SESSION['GroupId'] ?? 0;

// 1. Otorisasi Routing Operator (Sama seperti versi SQL)
$isRestricted = false;
$allowedRoutings = [];

if ($groupId != 1) {
    $sqlFilter = "
        SELECT DISTINCT r.rtg_name 
        FROM planning_user_group u
        INNER JOIN planning_group_rtg r ON u.group_name = r.group_name
        WHERE u.username = ?
    ";
    $stmtFilter = sqlsrv_query($conn, $sqlFilter, [$currUser]);

    $hasEntries = false;
    if ($stmtFilter) {
        while ($r = sqlsrv_fetch_array($stmtFilter, SQLSRV_FETCH_ASSOC)) {
            $hasEntries = true;
            $allowedRoutings[] = $r['rtg_name'];
        }
    }
    if (!$hasEntries) {
        $draw = $_POST['draw'] ?? 1;
        echo json_encode(['draw' => intval($draw), 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
        exit;
    }
    $isRestricted = true;
}

// 2. DataTables parameters
$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 25;
$search = isset($_POST['search']['value']) ? trim((string) $_POST['search']['value']) : '';

$filterRouting = isset($_POST['filter_routing']) ? trim((string) $_POST['filter_routing']) : '';
$filterKategori = isset($_POST['filter_kategori']) ? trim((string) $_POST['filter_kategori']) : '';
$filterGrup = isset($_POST['filter_grup']) ? trim((string) $_POST['filter_grup']) : '';

// 2b. Fetch Group Routings if filter_grup is selected
if ($filterGrup !== '') {
    $sqlGrupRtg = "SELECT rtg_name FROM planning_group_rtg WHERE group_name = ?";
    $stmtGrupRtg = sqlsrv_query($conn, $sqlGrupRtg, [$filterGrup]);
    $grupRoutings = [];
    if ($stmtGrupRtg) {
        while ($gr = sqlsrv_fetch_array($stmtGrupRtg, SQLSRV_FETCH_ASSOC)) {
            $grupRoutings[] = $gr['rtg_name'];
        }
    }
    // Update allowed routings by intersecting with group routings
    if ($isRestricted) {
        $allowedRoutings = array_intersect($allowedRoutings, $grupRoutings);
    } else {
        $allowedRoutings = $grupRoutings;
        $isRestricted = true;
    }
}

// 3. SILENT BACKGROUND UPDATE LOGIC
$forceUpdate = isset($_POST['force_update']) && $_POST['force_update'] == '1';
$cacheExists = file_exists($cacheFile);
$cacheAge = $cacheExists ? (time() - filemtime($cacheFile)) : 999999;

// Trigger update jika: dipaksa, file tidak ada, atau sudah lebih dari 2 menit (120s)
if ($forceUpdate || !$cacheExists || $cacheAge > 120) {
    if ($forceUpdate) {
        // JIKA TOMBOL REFRESH DIKLIK: Jalankan sinkron agar browser menunggu (7s)
        set_time_limit(60);
        exec("php " . escapeshellarg($workerPath));
    } else {
        // JIKA AUTO/BG: Jalankan background (silent)
        pclose(popen("start /B php " . escapeshellarg($workerPath), "r"));
    }
}

if (!$cacheExists) {
    // Kasus sangat langka: cache belum pernah digenerate
    echo json_encode(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'message' => 'Memuat data perdana...']);
    exit;
}

// 4. LOAD JSON DATA
$content = file_get_contents($cacheFile);
$cache = json_decode($content, true);
if (!$cache || !isset($cache['data'])) {
    echo json_encode(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Cache file corrupt']);
    exit;
}

$allRows = $cache['data'];
$recordsTotal = count($allRows); // Unfiltered total

// 5. PHP FILTERING (Logic mapping SQL ke PHP)
$filteredRows = array_filter($allRows, function ($r) use ($isRestricted, $allowedRoutings, $filterRouting, $filterKategori, $filterGrup, $search, $conn) {

    // a. Security Restriction
    if ($isRestricted) {
        if (!in_array($r['next_routing'], $allowedRoutings))
            return false;
    }

    // b. Filter Routing UI
    if ($filterRouting !== '' && $r['next_routing'] !== $filterRouting)
        return false;

    // c. Filter Kategori UI
    if ($filterKategori !== '' && $r['kategori_penimbangan'] !== $filterKategori)
        return false;

    // d. Search Global
    if ($search !== '') {
        $found = false;
        $searchFields = ['no_cp', 'label', 'cust_color', 'kode_lab', 'color_name', 'material', 'current_routing', 'next_routing'];
        $lowSearch = strtolower($search);
        foreach ($searchFields as $f) {
            if (isset($r[$f]) && strpos(strtolower((string) $r[$f]), $lowSearch) !== false) {
                $found = true;
                break;
            }
        }
        if (!$found)
            return false;
    }

    return true;
});

$recordsFiltered = count($filteredRows);

// 6. DYNAMIC PHP SORTING (Berdasarkan Nama Kolom, Bukan Index)
$orderIdx = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 0;
$orderDir = (isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'desc') ? -1 : 1;
$sortField = isset($_POST['columns'][$orderIdx]['data']) ? $_POST['columns'][$orderIdx]['data'] : 'waktu_start';

// Fallback jika 'no' atau index tak terdaftar
if ($sortField === 'no' || !isset($filteredRows[0][$sortField])) {
    $sortField = 'waktu_start';
}

usort($filteredRows, function ($a, $b) use ($sortField, $orderDir) {
    if ($a[$sortField] == $b[$sortField])
        return 0;
    $res = ($a[$sortField] < $b[$sortField]) ? -1 : 1;
    return $res * $orderDir;
});

// 7. PHP PAGINATION
$dataPage = array_slice($filteredRows, $start, $length);

// 8. FORMAT RESPONSE UNTUK DATATABLES
$finalData = [];
$counter = $start + 1;

foreach ($dataPage as $r) {
    $tglCp = $r['tgl_cp'] ? date('d/m/Y', strtotime($r['tgl_cp'])) : '-';
    $tglPlan = $r['tgl_planning'] ? date('d/m/Y', strtotime($r['tgl_planning'])) : '-';
    $qty = ($r['qty'] > 0) ? rtrim(rtrim(number_format((float) $r['qty'], 2, '.', ','), '0'), '.') : '-';
    $vlot = ($r['vlot'] > 0) ? rtrim(rtrim(number_format((float) $r['vlot'], 4, '.', ','), '0'), '.') : '-';

    // Badge Kategori
    $kat = $r['kategori_penimbangan'] ?? '-';
    switch ($kat) {
        case 'LAB':
            $badge = '<span class="badge badge-pill badge-lab">LAB</span>';
            break;
        case 'LA':
            $badge = '<span class="badge badge-pill badge-la">LA</span>';
            break;
        case 'MIX':
            $badge = '<span class="badge badge-pill badge-mix">MIX</span>';
            break;
        default:
            $badge = '<span class="badge badge-pill badge-secondary">-</span>';
            break;
    }

    $finalData[] = [
        'no' => $counter++,
        'tgl_plan' => $tglPlan,
        'no_cp' => htmlspecialchars($r['no_cp']),
        'tgl_cp' => $tglCp,
        'waktu_start' => $r['waktu_start'],
        'label' => htmlspecialchars($r['label'] ?? '-'),
        'cust_color' => htmlspecialchars($r['cust_color'] ?? '-'),
        'kode_lab' => htmlspecialchars($r['kode_lab'] ?? '-'),
        'color_name' => htmlspecialchars($r['color_name'] ?? '-'),
        'material' => htmlspecialchars($r['material'] ?? '-'),
        'qty' => $qty,
        'current_routing' => htmlspecialchars($r['current_routing'] ?? '-'),
        'next_routing' => htmlspecialchars($r['next_routing'] ?? '-'),
        'vlot' => $vlot,
        'kategori_timbang' => $badge
    ];
}

// 7. CALCULATE SUMMARY FROM FILTERED DATA (Untuk Sinkronisasi 1:1)
$summary = [
    'total' => count($filteredRows),
    'lab' => 0,
    'la' => 0,
    'mix' => 0
];

foreach ($filteredRows as $row) {
    $kat = $row['kategori_penimbangan'] ?? '-';
    if ($kat === 'LAB')
        $summary['lab']++;
    elseif ($kat === 'LA')
        $summary['la']++;
    elseif ($kat === 'MIX')
        $summary['mix']++;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => $finalData,
    'cache_time' => $cache['generated_at'],
    'cache_duration' => $cache['execution_time'] ?? 0,
    'cache_rows' => $cache['row_count'] ?? 0,
    'summary' => $summary // Kirim summary yang sinkron dengan tabel
]);
exit;
