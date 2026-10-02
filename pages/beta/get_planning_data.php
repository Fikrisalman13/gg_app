<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
include '../../koneksi.php';
require_once __DIR__ . '/planning_master_routing_helper.php';

// Path cache
$cacheFile = __DIR__ . '/planning_cache.json';
$workerPath = __DIR__ . '/generate_planning_cache.php';
$minimumCacheVersion = 4;

if (!isset($_SESSION['UserName'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$currUser = $_SESSION['UserName'] ?? '';
$groupId = $_SESSION['GroupId'] ?? 0;

planning_refresh_master_routing_snapshot($conn);

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
    if ($hasEntries) {
        $isRestricted = true;
    }
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
if (($cache['cache_version'] ?? 1) < $minimumCacheVersion) {
    pclose(popen("start /B php " . escapeshellarg($workerPath), "r"));
}
if (!$cache || !isset($cache['data'])) {
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Cache belum siap, silakan refresh lagi'
    ]);
    exit;
}

$allRows = $cache['data'];
$recordsTotal = count($allRows); // Unfiltered total

function normalizePlanningLocationLabel(string $value): string
{
    $value = strtoupper(trim($value));
    if ($value === '' || $value === '-') {
        return $value;
    }

    $parts = preg_split('/\s*,\s*/', $value) ?: [];
    $normalizedParts = [];

    foreach ($parts as $part) {
        $part = strtoupper(trim($part));
        if ($part === '' || $part === '-') {
            continue;
        }

        if (preg_match('/PADDRY\s+(?:MC\s+)?(\d+)\s+WARNA\s+(.+)/i', $part, $matches)) {
            $part = 'PADDRY ' . trim($matches[1]) . ' WARNA ' . trim($matches[2]);
        } else {
            $part = preg_replace('/\s+/', ' ', $part);
        }

        $normalizedParts[$part] = $part;
    }

    if (empty($normalizedParts)) {
        return '-';
    }

    return implode(', ', array_values($normalizedParts));
}

function resolvePlanningGroup(array $row): string
{
    $lokasiPaddry = trim((string) ($row['lokasi_paddry'] ?? ''));
    if ($lokasiPaddry !== '' && $lokasiPaddry !== '-') {
        return normalizePlanningLocationLabel($lokasiPaddry);
    }

    $nextRouting = trim((string) ($row['next_routing'] ?? ''));
    if ($nextRouting !== '' && $nextRouting !== '-') {
        return $nextRouting;
    }

    return '-';
}

function formatVariance($diffMin) {
    if ($diffMin === null || is_nan($diffMin)) return '';
    
    $absMin = abs($diffMin);
    $hours = floor($absMin / 60);
    $mins = round($absMin % 60);
    
    $parts = [];
    if ($hours > 0) $parts[] = $hours . 'J';
    if ($mins > 0 || empty($parts)) $parts[] = $mins . 'M';
    
    $str = implode(' ', $parts);
    if ($diffMin > 5) {
        return '+ ' . $str . ' LAMBAT';
    } elseif ($diffMin < -5) {
        return '- ' . $str . ' CEPAT';
    }
    return '';
}

function planningTimeSortValue($value): string
{
    $value = trim((string) $value);
    if ($value === '' || $value === '-') {
        return '99:99';
    }

    return preg_match('/^\d{2}:\d{2}$/', $value) ? $value : '99:99';
}

function planningDateSortValue($value): string
{
    if (empty($value)) {
        return '9999-12-31';
    }

    $ts = strtotime((string) $value);
    return $ts ? date('Y-m-d', $ts) : '9999-12-31';
}

function planningDateTimeTs($dateValue, $timeValue): ?int
{
    $dateValue = trim((string) $dateValue);
    $timeValue = trim((string) $timeValue);
    if ($dateValue === '' || $dateValue === '-' || $timeValue === '' || $timeValue === '-') {
        return null;
    }

    // Jika format DD/MM/YYYY, ubah ke YYYY-MM-DD agar strtotime tidak bingung
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dateValue, $matches)) {
        $dateValue = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
    }

    $ts = strtotime($dateValue . ' ' . $timeValue);
    return $ts ?: null;
}

function buildPlanningTimingMeta(array $row, array $planningMetaMap, array $stageMetaMap, array $erpStageMetaMap, array $rtgGroupMap, array $stageCurrentMetaMap = []): array
{
    $cpNo = trim((string) ($row['no_cp'] ?? ''));
    $planningMeta = $planningMetaMap[$cpNo] ?? null;
    $paddryId = (int) ($planningMeta['paddry_id'] ?? 0);
    $tglPlanningRaw = $planningMeta['tgl_planning'] ?? ($row['tgl_planning'] ?? null);

    $currentRtgId = (int) ($row['current_routing_id'] ?? 0);
    $nextRtgId = (int) ($row['next_routing_id'] ?? 0);
    $activeRtgId = ($nextRtgId > 0) ? $nextRtgId : $currentRtgId;
    $activeGroup = $rtgGroupMap[$activeRtgId] ?? '';
    $activeRtgName = strtoupper(($nextRtgId > 0) ? ($row['next_routing'] ?? '') : ($row['current_routing'] ?? ''));

    $planStart = '-';
    $planFinish = '-';

    if ($nextRtgId === 876 || $nextRtgId === 878) {
        $planStart = ($planningMeta['est_timbang'] ?? null) ?: '-';
    } elseif ($nextRtgId === 877 || $nextRtgId === 879) {
        $planFinish = ($planningMeta['est_pelarutan'] ?? null) ?: '-';
    } elseif ($nextRtgId === 880) {
        $planStart = ($planningMeta['est_pelarutan'] ?? null) ?: '-';
        $planFinish = ($planningMeta['rencana_start'] ?? null) ?: '-';
    } elseif (($activeGroup && strpos($activeGroup, 'PADDRY') !== false) || strpos($activeRtgName, 'PADDRY') !== false) {
        $planStart = ($planningMeta['rencana_start'] ?? null) ?: '-';
    } elseif ($activeGroup && strpos($activeGroup, 'PENIMBANGAN') !== false) {
        $planStart = ($planningMeta['est_timbang'] ?? null) ?: '-';
    } elseif ($activeGroup && strpos($activeGroup, 'PELARUTAN') !== false) {
        $planFinish = ($planningMeta['est_pelarutan'] ?? null) ?: '-';
    }

    $currentStageMeta = $paddryId > 0
        ? ($stageCurrentMetaMap[$paddryId]['current_stage'] ?? null)
        : ($stageCurrentMetaMap[$cpNo]['current_stage'] ?? null);
    $targetRtgId = $activeRtgId > 0 ? $activeRtgId : (int) ($currentStageMeta['rtgmsid'] ?? 0);

    $localStageBucket = $paddryId > 0 ? ($stageMetaMap[$paddryId] ?? []) : ($stageMetaMap[$cpNo] ?? []);
    $stg = ($targetRtgId > 0) ? ($localStageBucket[$targetRtgId] ?? null) : null;
    $erpStg = ($targetRtgId > 0) ? ($erpStageMetaMap[$cpNo][$targetRtgId] ?? null) : null;

    $isPaddryGroup = ($activeGroup && strpos($activeGroup, 'PADDRY') !== false) || strpos($activeRtgName, 'PADDRY') !== false;
    $isPenimbanganOrPelarutan = in_array($nextRtgId, [876, 877, 878, 879, 880], true)
        || ($activeGroup && (strpos($activeGroup, 'PENIMBANGAN') !== false || strpos($activeGroup, 'PELARUTAN') !== false));

    if ($nextRtgId > 0 && $isPenimbanganOrPelarutan) {
        // Khusus penimbangan/pelarutan: aktual harus milik routing target,
        // tidak boleh diwarisi dari stage sebelumnya yang sudah selesai.
        $waktuStart = ($stg['start_at'] ?? null)
            ?: ($erpStg['start_at'] ?? null)
            ?: '-';
        $waktuFinish = ($stg['finish_at'] ?? null)
            ?: ($erpStg['finish_at'] ?? null)
            ?: '-';
    } elseif ($isPaddryGroup) {
        // Paddry: ambil murni dari routing target (ERP/Local), no fallback ke jam mesin
        $waktuStart = ($stg['start_at'] ?? null)
            ?: ($erpStg['start_at'] ?? null)
            ?: '-';
        $waktuFinish = ($stg['finish_at'] ?? null)
            ?: ($erpStg['finish_at'] ?? null)
            ?: '-';
    } else {
        $waktuStart = ($stg['start_at'] ?? null)
            ?: ($erpStg['start_at'] ?? null)
            ?: ($currentStageMeta['start_at'] ?? null)
            ?: ($planningMeta['aktual_start'] ?? '-');
        $waktuFinish = ($stg['finish_at'] ?? null)
            ?: ($erpStg['finish_at'] ?? null)
            ?: ($currentStageMeta['finish_at'] ?? null)
            ?: ($planningMeta['aktual_finish'] ?? '-');
    }

    $statusOperasional = 'waiting';
    if ($waktuFinish !== '-') {
        $statusOperasional = 'done';
    } elseif ($waktuStart !== '-') {
        $statusOperasional = 'running';
    }

    return [
        'tgl_planning_raw' => $tglPlanningRaw,
        'plan_start' => $planStart,
        'plan_finish' => $planFinish,
        'waktu_start' => $waktuStart,
        'waktu_finish' => $waktuFinish,
        'status_operasional' => $statusOperasional,
    ];
}

function resolvePlanningStageView(array $localStage, ?array $erpStage = null): array
{
    $isBreaktime = ((int) ($localStage['rtgmsid'] ?? 0) === 999);

    if ($isBreaktime) {
        $fgresult = strtoupper((string) ($localStage['fgresult'] ?? ''));
        $startAt = $localStage['start_at'] ?? null;
        $finishAt = $localStage['finish_at'] ?? null;
        $prdqty = 1.0;
    } else {
        $fgresult = strtoupper((string) ($erpStage['fgresult'] ?? ''));
        $startAt = $erpStage['start_at'] ?? null;
        $finishAt = $erpStage['finish_at'] ?? null;
        $prdqty = $erpStage ? (float) ($erpStage['prdqty'] ?? 0) : 0.0;
    }

    $isPassed = ($fgresult === 'P' || $fgresult === 'PASS') && $finishAt && ($prdqty != 0.0 || $isBreaktime);
    $isFailed = ($fgresult === 'F' || $fgresult === 'FAIL');
    $isRunning = !empty($startAt) && empty($finishAt) && !$isFailed;

    return [
        'rtgmsid' => (int) ($localStage['rtgmsid'] ?? 0),
        'rtgseq' => $localStage['rtgseq'] ?? ($erpStage['rtgseq'] ?? null),
        'stage_no' => (int) ($localStage['stage_no'] ?? 0),
        'rtgname' => (string) (($localStage['rtgname'] ?? '') ?: ($erpStage['rtgname'] ?? '')),
        'start_at' => $startAt,
        'finish_at' => $finishAt,
        'fgresult' => $fgresult,
        'is_passed' => $isPassed,
        'is_failed' => $isFailed,
        'is_running' => $isRunning,
    ];
}

function determinePlanningCurrentStage(array $stages): array
{
    $currentStage = null;
    $lastPassed = null;
    $hasFailed = false;

    foreach ($stages as $stage) {
        if ($stage['is_failed']) {
            $hasFailed = true;
            if ($currentStage === null) {
                $currentStage = $stage;
            }
            break;
        }
        if ($stage['is_running']) {
            $currentStage = $stage;
            break;
        }
        if ($stage['is_passed']) {
            $lastPassed = $stage;
            continue;
        }
        $currentStage = $stage;
        break;
    }

    if ($currentStage === null && !empty($stages)) {
        $currentStage = end($stages);
    }

    return [
        'current_stage' => $currentStage,
        'last_passed' => $lastPassed,
        'has_failed' => $hasFailed,
    ];
}

function planningDebugEnabled(): bool
{
    return isset($_GET['debug_sort']) && $_GET['debug_sort'] === '1';
}

function planningWriteDebug(array $payload): void
{
    $logFile = __DIR__ . '/planning_sort_debug.log';
    $payload['logged_at'] = date('Y-m-d H:i:s');
    file_put_contents($logFile, json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
}

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

// 6. LOAD PLANNING META UNTUK SEMUA HASIL FILTER
$planningMetaMap = [];
$stageMetaMap = [];
$erpStageMetaMap = [];
$stageCurrentMetaMap = [];
$allTrackedRtgIds = [];
$rtgGroupMap = []; 

$pageCpList = array_values(array_unique(array_filter(array_map(function ($row) {
    return trim((string) ($row['no_cp'] ?? ''));
}, $filteredRows))));

if (!empty($pageCpList)) {
    $placeholders = array_fill(0, count($pageCpList), '?');

    // 0. Fetch All Tracked Routing IDs & Groups
    $sqlAllRtg = "SELECT routing_id, group_name FROM planning_group_rtg";
    $stmtAllRtg = sqlsrv_query($conn, $sqlAllRtg);
    if ($stmtAllRtg) {
        while ($ar = sqlsrv_fetch_array($stmtAllRtg, SQLSRV_FETCH_ASSOC)) {
            $rid = (int)$ar['routing_id'];
            $allTrackedRtgIds[] = $rid;
            $rtgGroupMap[$rid] = strtoupper(trim($ar['group_name']));
        }
    }
    $allTrackedRtgIds = !empty($allTrackedRtgIds) ? array_unique($allTrackedRtgIds) : [0];
    $rtgIdListStr = implode(',', $allTrackedRtgIds);

    // a. Fetch from Main Planning (cpp_paddry) - SQL Server
    $sqlPlanning = "SELECT id, cp_no, rencana_start, rencana_finish, aktual_start, aktual_finish, period_date, est_tmbng_plrtn_lalab, est_plrtn_prdks
                    FROM dbo.cpp_paddry 
                    WHERE cp_no IN (" . implode(',', $placeholders) . ")";
    $stmtPlanning = sqlsrv_query($conn, $sqlPlanning, $pageCpList);
    if ($stmtPlanning) {
        while ($plan = sqlsrv_fetch_array($stmtPlanning, SQLSRV_FETCH_ASSOC)) {
            $cpNo = trim((string) $plan['cp_no']);
            $planningMetaMap[$cpNo] = [
                'paddry_id' => (int) ($plan['id'] ?? 0),
                'rencana_start' => $plan['rencana_start'] ? $plan['rencana_start']->format('H:i') : null,
                'rencana_finish' => $plan['rencana_finish'] ? $plan['rencana_finish']->format('H:i') : null,
                'aktual_start' => $plan['aktual_start'] ? $plan['aktual_start']->format('H:i') : null,
                'aktual_finish' => $plan['aktual_finish'] ? $plan['aktual_finish']->format('H:i') : null,
                'est_timbang' => $plan['est_tmbng_plrtn_lalab'] ? $plan['est_tmbng_plrtn_lalab']->format('H:i') : null,
                'est_pelarutan' => $plan['est_plrtn_prdks'] ? $plan['est_plrtn_prdks']->format('H:i') : null,
                'tgl_planning' => $plan['period_date'] ? $plan['period_date']->format('Y-m-d') : null,
            ];
        }
        sqlsrv_free_stmt($stmtPlanning);
    }

    // b. Fetch from Stage Tracking (cpp_paddry_stage) - SQL Server
    $paddryIds = array_values(array_filter(array_unique(array_map(function ($meta) {
        return (int) ($meta['paddry_id'] ?? 0);
    }, $planningMetaMap))));

    $sqlStage = "SELECT paddry_id, cp_no, stage_no, rtgmsid, rtgseq, rtgname, start_at, finish_at, fgresult, origin_system
                 FROM dbo.cpp_paddry_stage 
                 WHERE paddry_id IN (" . implode(',', array_fill(0, count($paddryIds), '?')) . ") 
                   AND rtgmsid IN ($rtgIdListStr)
                 ORDER BY cp_no ASC, stage_no ASC, rtgseq ASC, rtgmsid ASC";
    $stmtStage = !empty($paddryIds) ? sqlsrv_query($conn, $sqlStage, $paddryIds) : false;
    if ($stmtStage) {
        while ($stg = sqlsrv_fetch_array($stmtStage, SQLSRV_FETCH_ASSOC)) {
            $paddryId = (int) ($stg['paddry_id'] ?? 0);
            $rtgId = (int) $stg['rtgmsid'];
            $stageMetaMap[$paddryId][$rtgId] = [
                'stage_no' => (int) ($stg['stage_no'] ?? 0),
                'rtgseq' => isset($stg['rtgseq']) ? (int) $stg['rtgseq'] : null,
                'rtgname' => trim((string) ($stg['rtgname'] ?? '')),
                'start_at' => $stg['start_at'] ? $stg['start_at']->format('H:i') : null,
                'finish_at' => $stg['finish_at'] ? $stg['finish_at']->format('H:i') : null,
                'fgresult' => trim((string) ($stg['fgresult'] ?? '')),
                'origin_system' => trim((string) ($stg['origin_system'] ?? '')),
            ];
        }
        sqlsrv_free_stmt($stmtStage);
    }

    // c. Fetch from ERP Routing (pdproductionrtg) - PostgreSQL
    try {
        @include '../../koneksi3.php';
        if (isset($conn3)) {
            $pgPlaceholders = implode(',', array_fill(0, count($pageCpList), '?'));
            $sqlErp = "
                SELECT phd.prdnmbr, rtg.rtgmsid, rtg.rtgseq, ms.rtgname, rtg.prdqty, rtg.fgresult, rtg.starttime, rtg.endtime
                FROM pdproductionhd phd
                INNER JOIN pdproductionrtg rtg ON rtg.productionhdid = phd.productionhdid
                LEFT JOIN pdrtgms ms ON ms.rtgmsid = rtg.rtgmsid
                WHERE phd.prdnmbr IN ($pgPlaceholders)
                  AND rtg.rtgmsid IN ($rtgIdListStr)
            ";
            $stmtErp = $conn3->prepare($sqlErp);
            $stmtErp->execute(array_values($pageCpList));
            while ($erpRow = $stmtErp->fetch(PDO::FETCH_ASSOC)) {
                $cpNo = trim((string) $erpRow['prdnmbr']);
                $rtgId = (int) $erpRow['rtgmsid'];
                $erpStageMetaMap[$cpNo][$rtgId] = [
                    'rtgseq' => isset($erpRow['rtgseq']) ? (int) $erpRow['rtgseq'] : null,
                    'rtgname' => trim((string) ($erpRow['rtgname'] ?? '')),
                    'prdqty' => (float) ($erpRow['prdqty'] ?? 0),
                    'fgresult' => trim((string) ($erpRow['fgresult'] ?? '')),
                    'start_at' => $erpRow['starttime'] ? date('H:i', strtotime($erpRow['starttime'])) : null,
                    'finish_at' => $erpRow['endtime'] ? date('H:i', strtotime($erpRow['endtime'])) : null,
                ];
            }
        }
    } catch (Exception $e) {}
}

foreach ($planningMetaMap as $cpNo => $meta) {
    $paddryId = (int) ($meta['paddry_id'] ?? 0);
    $localStages = array_values($stageMetaMap[$paddryId] ?? []);
    if (empty($localStages)) {
        continue;
    }

    usort($localStages, function ($a, $b) {
        $aStageNo = (int) ($a['stage_no'] ?? 0);
        $bStageNo = (int) ($b['stage_no'] ?? 0);
        if ($aStageNo !== $bStageNo) {
            return $aStageNo <=> $bStageNo;
        }

        $aSeq = (int) ($a['rtgseq'] ?? 0);
        $bSeq = (int) ($b['rtgseq'] ?? 0);
        if ($aSeq !== $bSeq) {
            return $aSeq <=> $bSeq;
        }

        return ((int) ($a['rtgmsid'] ?? 0)) <=> ((int) ($b['rtgmsid'] ?? 0));
    });

    $stageViews = [];
    foreach ($localStages as $localStage) {
        $stageViews[] = resolvePlanningStageView(
            $localStage,
            $erpStageMetaMap[$cpNo][(int) ($localStage['rtgmsid'] ?? 0)] ?? null
        );
    }

    $stageCurrentMetaMap[$paddryId] = determinePlanningCurrentStage($stageViews);
}

foreach ($filteredRows as &$row) {
    $timingMeta = buildPlanningTimingMeta($row, $planningMetaMap, $stageMetaMap, $erpStageMetaMap, $rtgGroupMap, $stageCurrentMetaMap);
    $row['__planning_timing'] = $timingMeta;
    $row['__sort_tgl_plan'] = planningDateSortValue($timingMeta['tgl_planning_raw']);
    $row['__sort_tgl_cp'] = planningDateSortValue($row['tgl_cp'] ?? null);
    $row['__sort_plan_start'] = planningTimeSortValue($timingMeta['plan_start']);
    $row['__sort_plan_finish'] = planningTimeSortValue($timingMeta['plan_finish']);
    $row['__sort_waktu_start'] = planningTimeSortValue($timingMeta['waktu_start']);
    $row['__sort_waktu_finish'] = planningTimeSortValue($timingMeta['waktu_finish']);
    $row['__sort_display_start'] = ($row['__sort_plan_start'] !== '99:99') ? $row['__sort_plan_start'] : $row['__sort_waktu_start'];
    $row['__sort_display_finish'] = ($row['__sort_plan_finish'] !== '99:99') ? $row['__sort_plan_finish'] : $row['__sort_waktu_finish'];
    $row['__sort_status_operasional'] = $timingMeta['status_operasional'];
}
unset($row);

// 7. DYNAMIC PHP SORTING (Berdasarkan Nama Kolom, Setelah Waktu Aktual Terbentuk)
$orderIdx = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 0;
$orderDir = (isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'desc') ? -1 : 1;
$sortField = isset($_POST['columns'][$orderIdx]['data']) ? $_POST['columns'][$orderIdx]['data'] : 'waktu_start';

$sortFieldMap = [
    'tgl_plan' => '__sort_tgl_plan',
    'tgl_cp' => '__sort_tgl_cp',
    'plan_start' => '__sort_display_start',
    'plan_finish' => '__sort_display_finish',
    'waktu_start' => '__sort_display_start',
    'waktu_finish' => '__sort_display_finish',
    'status_operasional' => '__sort_status_operasional',
];

$effectiveSortField = $sortFieldMap[$sortField] ?? $sortField;
if ($sortField === 'no' || !isset($filteredRows[0][$effectiveSortField])) {
    $effectiveSortField = '__sort_waktu_start';
}

usort($filteredRows, function ($a, $b) use ($effectiveSortField, $orderDir) {
    $locA = resolvePlanningGroup($a);
    $locB = resolvePlanningGroup($b);
    if ($locA !== $locB) {
        return strcmp($locA, $locB);
    }

    // Urutan utama halaman planning harus stabil:
    // lokasi paddry -> tgl planning -> waktu start -> tgl cp -> no_cp
    $planDateA = $a['__sort_tgl_plan'] ?? '9999-12-31';
    $planDateB = $b['__sort_tgl_plan'] ?? '9999-12-31';
    if ($planDateA !== $planDateB) {
        return ($planDateA < $planDateB) ? -1 : 1;
    }

    $startA = $a['__sort_display_start'] ?? '99:99';
    $startB = $b['__sort_display_start'] ?? '99:99';
    if ($startA !== $startB) {
        return ($startA < $startB) ? -1 : 1;
    }

    $valA = $a[$effectiveSortField] ?? '';
    $valB = $b[$effectiveSortField] ?? '';
    if ($valA !== $valB) {
        $res = ($valA < $valB) ? -1 : 1;
        return $res * $orderDir;
    }

    $cpDateA = $a['__sort_tgl_cp'] ?? '9999-12-31';
    $cpDateB = $b['__sort_tgl_cp'] ?? '9999-12-31';
    if ($cpDateA !== $cpDateB) {
        return ($cpDateA < $cpDateB) ? -1 : 1;
    }

    return strcmp((string) ($a['no_cp'] ?? ''), (string) ($b['no_cp'] ?? ''));
});

if (planningDebugEnabled()) {
    $postedColumns = [];
    if (isset($_POST['columns']) && is_array($_POST['columns'])) {
        foreach ($_POST['columns'] as $idx => $col) {
            $postedColumns[] = [
                'idx' => $idx,
                'data' => $col['data'] ?? null,
                'name' => $col['name'] ?? null,
                'orderable' => $col['orderable'] ?? null,
            ];
        }
    }

    $topRows = array_slice($filteredRows, 0, 10);
    $topRowsDebug = array_map(function ($row) {
        return [
            'no_cp' => $row['no_cp'] ?? null,
            'lokasi_paddry' => resolvePlanningGroup($row),
            'tgl_plan' => $row['__sort_tgl_plan'] ?? null,
            'waktu_start' => $row['__sort_waktu_start'] ?? null,
            'display_start' => $row['__sort_display_start'] ?? null,
            'tgl_cp' => $row['__sort_tgl_cp'] ?? null,
            'effective_sort' => $row['__sort_display_start'] ?? null,
        ];
    }, $topRows);

    planningWriteDebug([
        'post_order' => $_POST['order'] ?? null,
        'order_idx' => $orderIdx,
        'order_dir' => $orderDir,
        'sort_field' => $sortField,
        'effective_sort_field' => $effectiveSortField,
        'posted_columns' => $postedColumns,
        'top_rows_after_sort' => $topRowsDebug,
    ]);
}

// 8. Hitung nomor urut per grup Lokasi sebelum dipotong Pagination
$currentGroup = null;
$groupCounter = 1;
foreach ($filteredRows as &$row) {
    $loc = trim(strip_tags(resolvePlanningGroup($row)));
    if ($currentGroup !== $loc) {
        $currentGroup = $loc;
        $groupCounter = 1;
    }
    $row['calculated_no'] = $groupCounter++;
}
unset($row);

// 9. PHP PAGINATION
$dataPage = array_slice($filteredRows, $start, $length);

// 10. FORMAT RESPONSE UNTUK DATATABLES
$finalData = [];

foreach ($dataPage as $r) {
    $timingMeta = $r['__planning_timing'] ?? buildPlanningTimingMeta($r, $planningMetaMap, $stageMetaMap, $erpStageMetaMap, $rtgGroupMap, $stageCurrentMetaMap);
    $tglPlanningRaw = $timingMeta['tgl_planning_raw'] ?? null;
    $planStart = $timingMeta['plan_start'] ?? '-';
    $planFinish = $timingMeta['plan_finish'] ?? '-';
    $waktuStart = $timingMeta['waktu_start'] ?? '-';
    $waktuFinish = $timingMeta['waktu_finish'] ?? '-';
    $statusOperasional = $timingMeta['status_operasional'] ?? 'waiting';

    // 2. Revised Delay Logic (Any stage late = Delay)
    $statusDelay = 'waiting'; 
    $isDelayed = false;
    $isEarly = false;

    $now = time();
    $planDateForCompare = $tglPlanningRaw ?: date('Y-m-d');

    // 3. Check Start Delay
    if ($planStart !== '-' && $planStart !== '') {
        $pStartSec = planningDateTimeTs($planDateForCompare, $planStart);
        $aStartSec = planningDateTimeTs($planDateForCompare, $waktuStart);
        if ($pStartSec && $aStartSec) {
            $diffStart = ($aStartSec - $pStartSec) / 60;
            if ($diffStart > 0) $isDelayed = true;
            elseif ($diffStart < 0) $isEarly = true;
        } elseif ($pStartSec && $statusOperasional !== 'done') {
            // Belum start tapi sudah lewat jam rencana?
            if (($now - $pStartSec) > 0) $isDelayed = true;
        }
    }

    // 4. Check Finish Delay (If not delayed by start, check finish)
    if (!$isDelayed && $planFinish !== '-' && $planFinish !== '') {
        $pFinishSec = planningDateTimeTs($planDateForCompare, $planFinish);
        $aFinishSec = planningDateTimeTs($planDateForCompare, $waktuFinish);
        if ($pFinishSec && $aFinishSec) {
            $diffFinish = ($aFinishSec - $pFinishSec) / 60;
            if ($diffFinish > 0) $isDelayed = true;
        } elseif ($pFinishSec && $statusOperasional === 'running') {
            // Sedang jalan tapi sudah lewat jam finish?
            if (($now - $pFinishSec) > 0) $isDelayed = true;
        }
    }

    if ($isDelayed) {
        $statusDelay = 'delay';
    } elseif ($isEarly) {
        $statusDelay = 'early';
    } elseif ($waktuStart !== '-' || $waktuFinish !== '-') {
        $statusDelay = 'ontime';
    }
    // 5. Hitung Variance per Kolom (untuk tampilan detail)
    $varStartStr = '';
    if ($waktuStart !== '-' && $planStart !== '-') {
        $pStartSec = planningDateTimeTs($planDateForCompare, $planStart);
        $aStartSec = planningDateTimeTs($planDateForCompare, $waktuStart);
        if ($pStartSec && $aStartSec) {
            $vDiff = ($aStartSec - $pStartSec) / 60;
            $varStartStr = formatVariance($vDiff);
        }
    }
    $varFinishStr = '';
    if ($waktuFinish !== '-' && $planFinish !== '-') {
        $pFinishSec = planningDateTimeTs($planDateForCompare, $planFinish);
        $aFinishSec = planningDateTimeTs($planDateForCompare, $waktuFinish);
        if ($pFinishSec && $aFinishSec) {
            $vDiff = ($aFinishSec - $pFinishSec) / 60;
            $varFinishStr = formatVariance($vDiff);
        }
    }
    $tglCp = $r['tgl_cp'] ? date('d/m/Y', strtotime($r['tgl_cp'])) : '-';
    $tglPlan = $tglPlanningRaw ? date('d/m/Y', strtotime($tglPlanningRaw)) : '-';
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
        'no' => $r['calculated_no'],
        'tgl_plan' => $tglPlan,
        'no_cp' => htmlspecialchars($r['no_cp']),
        'tgl_cp' => $tglCp,
        'plan_start' => $planStart,
        'plan_finish' => $planFinish,
        'waktu_start' => $waktuStart,
        'waktu_finish' => $waktuFinish,
        'var_start' => $varStartStr,
        'var_finish' => $varFinishStr,
        'status_delay' => $statusDelay,
        'status_operasional' => $statusOperasional,
        'label' => htmlspecialchars($r['label'] ?? '-'),
        'cust_color' => htmlspecialchars($r['cust_color'] ?? '-'),
        'kode_lab' => htmlspecialchars($r['kode_lab'] ?? '-'),
        'color_name' => htmlspecialchars($r['color_name'] ?? '-'),
        'material' => htmlspecialchars($r['material'] ?? '-'),
        'qty' => $qty,
        'current_routing' => htmlspecialchars($r['current_routing'] ?? '-'),
        'next_routing' => htmlspecialchars($r['next_routing'] ?? '-'),
        'vlot' => $vlot,
        'kategori_timbang' => $badge,
        'lokasi_paddry' => htmlspecialchars(trim(strip_tags(resolvePlanningGroup($r)))) // for RowGroup
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

// 9. SETTINGS SYNC TIMESTAMP (Mencegah manual refresh jika user lain ganti setting)
$settingsFile = __DIR__ . '/planning_settings.json';
$settingsTime = file_exists($settingsFile) ? filemtime($settingsFile) : 0;

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => $finalData,
    'cache_time' => $cache['generated_at'],
    'cache_duration' => $cache['execution_time'] ?? 0,
    'cache_rows' => $cache['row_count'] ?? 0,
    'summary' => $summary, // Kirim summary yang sinkron dengan tabel
    'settings_time' => $settingsTime // Metadata untuk sinkronisasi setting global
]);
exit;
