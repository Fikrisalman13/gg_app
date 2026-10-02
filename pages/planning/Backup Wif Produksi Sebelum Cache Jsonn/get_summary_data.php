<?php
session_start();
include '../../koneksi.php';
include '../../koneksi3.php';

if (!isset($_SESSION['UserName'])) {
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
    $allowedRoutings = $isRestricted
        ? array_intersect($allowedRoutings, $grupRoutings)
        : $grupRoutings;
    $isRestricted = true;
}

// Prepare Routing CTE filter
$routingCondition = "";
if ($isRestricted && count($allowedRoutings) > 0) {
    $quotedList = implode(",", array_map(function($rtg) use ($conn3) { return $conn3->quote($rtg); }, $allowedRoutings));
    $routingCondition = " AND rtgname IN ($quotedList) ";
}

try {
    $baseSql = "
    WITH active_hd AS (
        SELECT h.productionhdid
        FROM pdproductionhd h
        INNER JOIN pdresultmat rm
            ON h.productionhdid = rm.productionhdid
            AND rm.matseq = '1'
        WHERE h.workcenterid = '111'
          AND h.fgstatus = 'U'
    ),
    fast_rtg AS (
        SELECT productionhdid, rtgseq, rtgmsid, prdqty 
        FROM pdproductionrtg 
        WHERE productionhdid IN (SELECT productionhdid FROM active_hd)
    ),
    last_process AS (
        SELECT rtg.productionhdid, MAX(rtg.rtgseq) AS current_rtgseq
        FROM fast_rtg rtg
        WHERE rtg.prdqty > 0 
        GROUP BY rtg.productionhdid
    ),
    base_data AS (
        SELECT ah.productionhdid, r2.rtgname
        FROM active_hd ah
        LEFT JOIN last_process lp ON ah.productionhdid = lp.productionhdid
        LEFT JOIN fast_rtg a ON a.productionhdid = lp.productionhdid AND a.rtgseq = lp.current_rtgseq
        LEFT JOIN fast_rtg b ON b.productionhdid = a.productionhdid AND b.rtgseq = a.rtgseq + 1
        LEFT JOIN pdrtgms r2 ON b.rtgmsid = r2.rtgmsid
    )
    SELECT * FROM base_data WHERE rtgname IS NOT NULL $routingCondition
    ";

    // Count total based on allowed routing
    $countSql = "SELECT COUNT(DISTINCT productionhdid) AS total FROM ($baseSql) AS tmp";
    $stmt = $conn3->query($countSql);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get distinct routing list for filter (Next Routing)
    $routingSql = "SELECT DISTINCT rtgname FROM ($baseSql) AS tmp ORDER BY rtgname";
    $stmtRtg = $conn3->query($routingSql);
    $routings = $stmtRtg->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'total'     => (int)($row['total']     ?? 0),
        'total_lab' => 0,
        'total_la'  => 0,
        'total_mix' => 0,
        'routings'  => $routings,
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
