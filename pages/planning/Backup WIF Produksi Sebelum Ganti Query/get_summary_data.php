<?php
session_start();
include '../../koneksi.php';

$cacheFile = __DIR__ . '/planning_cache.json';

if (!isset($_SESSION['UserName'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$currUser = $_SESSION['UserName'] ?? '';
$groupId = $_SESSION['GroupId'] ?? 0;

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
        echo json_encode(['total' => 0, 'total_lab' => 0, 'total_la' => 0, 'total_mix' => 0, 'routings' => []]);
        exit;
    }
    $isRestricted = true;
}

// Filter by Grup (dari POST)
$filterGrup = trim($_POST['filter_grup'] ?? '');
if ($filterGrup !== '') {
    $sqlGrupRtg = "SELECT rtg_name FROM planning_group_rtg WHERE group_name = ?";
    $stmtGrupRtg = sqlsrv_query($conn, $sqlGrupRtg, [$filterGrup]);
    $grupRoutings = [];
    if ($stmtGrupRtg) {
        while ($gr = sqlsrv_fetch_array($stmtGrupRtg, SQLSRV_FETCH_ASSOC)) {
            $grupRoutings[] = $gr['rtg_name'];
        }
    }
    // Jika isRestricted, intersect; jika admin, pakai grup filter saja
    if ($isRestricted) {
        $allowedRoutings = array_intersect($allowedRoutings, $grupRoutings);
    } else {
        $allowedRoutings = $grupRoutings;
        $isRestricted = true;
    }
}

// LOAD JSON CACHE
if (!file_exists($cacheFile)) {
    echo json_encode(['total' => 0, 'total_lab' => 0, 'total_la' => 0, 'total_mix' => 0, 'routings' => [], 'message' => 'Cache missing']);
    exit;
}

$cache = json_decode(file_get_contents($cacheFile), true);
$allRows = $cache['data'] ?? [];

// FILTER DATA IN PHP
$filteredRoutings = [];
$countTotal = 0;

foreach ($allRows as $r) {
    $rtg = $r['next_routing'] ?? null;
    if (!$rtg) continue;

    // Apply allowed routing security/filter
    if ($isRestricted && !in_array($rtg, $allowedRoutings)) {
        continue;
    }

    $countTotal++;
    if (!in_array($rtg, $filteredRoutings)) {
        $filteredRoutings[] = $rtg;
    }
}

sort($filteredRoutings);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'total'     => $countTotal,
    'total_lab' => 0, // Placeholder as per original
    'total_la'  => 0, // Placeholder
    'total_mix' => 0, // Placeholder
    'routings'  => $filteredRoutings,
    'generated_at' => $cache['generated_at'] ?? null
]);
