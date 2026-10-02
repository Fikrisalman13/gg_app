<?php
require_once '../../koneksi.php';
require_once '../../koneksi3.php';

$search   = trim($_GET['search'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = max(1, min(50, (int)($_GET['limit'] ?? 5)));
$offset   = ($page - 1) * $limit;
$uniqueid = trim($_GET['uniqueid'] ?? '');

// Get already-selected balenmbrs for this parent
$excludeBalenmbr = [];
if ($uniqueid !== '') {
    $qEx = sqlsrv_query($conn, "SELECT balenmbr FROM orderitem_gistex_dt WHERE uniqueid_parent = ?", [$uniqueid]);
    if ($qEx) {
        while ($rEx = sqlsrv_fetch_array($qEx, SQLSRV_FETCH_ASSOC)) {
            if (!empty($rEx['balenmbr'])) $excludeBalenmbr[] = $rEx['balenmbr'];
        }
    }
}

// Count total
$countSql = "
SELECT COUNT(*) as total
FROM whbalehd
INNER JOIN whbaleprod ON whbalehd.balehdid = whbaleprod.balehdid
WHERE whbaleprod.fgstatus = 'O'
";
$countParams = [];
if ($search !== '') {
    $countSql .= " AND whbalehd.balenmbr ILIKE :search";
    $countParams[':search'] = "%$search%";
}
if (!empty($excludeBalenmbr)) {
    $excludePlaceholders = [];
    foreach ($excludeBalenmbr as $i => $bm) {
        $key = ":ex$i";
        $excludePlaceholders[] = $key;
        $countParams[$key] = $bm;
    }
    $countSql .= " AND whbalehd.balenmbr NOT IN (" . implode(',', $excludePlaceholders) . ")";
}
$countStmt = $conn3->prepare($countSql);
$countStmt->execute($countParams);
$total = (int)$countStmt->fetchColumn();

// Fetch page
$dataSql = "
SELECT
    whbalehd.balenmbr,
    whbaleprod.fgstatus,
    whbaleprod.wrhsid,
    whbaleprod.prodcode,
    whbaleprod.prodname,
    whbaleprod.totqtym,
    whbaleprod.totqtyyard,
    whbaleprod.totqtykg
FROM whbalehd
INNER JOIN whbaleprod ON whbalehd.balehdid = whbaleprod.balehdid
WHERE whbaleprod.fgstatus = 'O'
";
$dataParams = [];
if ($search !== '') {
    $dataSql .= " AND whbalehd.balenmbr ILIKE :search";
}
if (!empty($excludeBalenmbr)) {
    $excludePlaceholders2 = [];
    foreach ($excludeBalenmbr as $i => $bm) {
        $key = ":ex2_$i";
        $excludePlaceholders2[] = $key;
        $dataParams[$key] = $bm;
    }
    $dataSql .= " AND whbalehd.balenmbr NOT IN (" . implode(',', $excludePlaceholders2) . ")";
}
$dataSql .= " ORDER BY whbalehd.balenmbr ASC OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY";

$dataStmt = $conn3->prepare($dataSql);
if ($search !== '') {
    $dataStmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
}
$dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$dataStmt->execute();
$rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode([
    'data'  => $rows,
    'total' => $total,
    'page'  => $page,
    'limit' => $limit
]);
