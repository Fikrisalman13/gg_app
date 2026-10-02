<?php
// accounts_history_fetch.php - Server-side for account transaction history
error_reporting(0);
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    die(json_encode(['error' => 'Unauthorized']));
}

$accountId = $_POST['AccountId'] ?? $_GET['AccountId'] ?? '';
if (empty($accountId)) {
    die(json_encode([
        "draw" => 0,
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => []
    ]));
}

// DataTables parameters
$draw = intval($_POST['draw'] ?? 0);
$start = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);
$searchValue = $_POST['search']['value'] ?? '';

// Columns mapping for ordering
$columns = [
    0 => 'l.TransTimestamp',
    1 => 'l.Description',
    2 => 'l.Type',
    3 => 'l.Amount',
    4 => 'u.UserName'
];
$orderColumnIndex = intval($_POST['order'][0]['column'] ?? 0);
$orderDir = $_POST['order'][0]['dir'] ?? 'desc';
$orderBy = $columns[$orderColumnIndex] ?? 'l.TransTimestamp';
$startDate = $_POST['startDate'] ?? '';
$endDate = $_POST['endDate'] ?? '';

// Base Query
$baseSql = "FROM fin_ledger l
            LEFT JOIN dbo.SMUserMs u ON l.CreatedBy = u.UserId
            LEFT JOIN fin_requests r ON l.RequestId = r.RequestId
            WHERE l.AccountId = ?";

$params = [$accountId];
$where = [];

// Search Filter
if (!empty($searchValue)) {
    $where[] = "(l.Description LIKE ? OR r.Title LIKE ? OR u.UserName LIKE ?)";
    $v = "%$searchValue%";
    $params[] = $v; $params[] = $v; $params[] = $v;
}

// Date Filter
if (!empty($startDate)) {
    $where[] = "CAST(l.TransTimestamp AS DATE) >= ?";
    $params[] = $startDate;
}
if (!empty($endDate)) {
    $where[] = "CAST(l.TransTimestamp AS DATE) <= ?";
    $params[] = $endDate;
}

$whereSql = "";
if (!empty($where)) {
    $whereSql = " AND " . implode(" AND ", $where);
}

// Total records
$totalSql = "SELECT COUNT(*) as Total FROM fin_ledger WHERE AccountId = ?";
$totalStmt = sqlsrv_query($conn, $totalSql, [$accountId]);
$totalRecords = 0;
if ($totalStmt && $row = sqlsrv_fetch_array($totalStmt, SQLSRV_FETCH_ASSOC)) {
    $totalRecords = $row['Total'];
}

// Filtered records
$filteredSql = "SELECT COUNT(*) as Total " . $baseSql . $whereSql;
$filteredStmt = sqlsrv_query($conn, $filteredSql, $params);
$totalFiltered = 0;
if ($filteredStmt && $row = sqlsrv_fetch_array($filteredStmt, SQLSRV_FETCH_ASSOC)) {
    $totalFiltered = $row['Total'];
}

// Main Data Query
$dataSql = "SELECT l.*, u.UserName, r.Title as RequestTitle 
            " . $baseSql . $whereSql . "
            ORDER BY $orderBy $orderDir
            OFFSET $start ROWS FETCH NEXT $length ROWS ONLY";

$dataStmt = sqlsrv_query($conn, $dataSql, $params);
$data = [];

if ($dataStmt) {
    while ($row = sqlsrv_fetch_array($dataStmt, SQLSRV_FETCH_ASSOC)) {
        
        $dateCol = '<div class="small">' . ($row['TransTimestamp'] ? $row['TransTimestamp']->format('d/m/Y H:i') : '-') . '</div>';
        
        $descCol = '<div>' . htmlspecialchars($row['Description']) . '</div>';
        if ($row['RequestTitle']) {
            $descCol .= '<small class="text-info font-italic">#' . $row['RequestId'] . ' ' . htmlspecialchars($row['RequestTitle']) . '</small>';
        }
        
        $badgeClass = $row['Type'] === 'IN' ? 'badge-success' : 'badge-danger';
        $typeCol = '<span class="badge ' . $badgeClass . '">' . $row['Type'] . '</span>';
        
        $prefix = $row['Type'] === 'IN' ? '+' : '-';
        $amountCol = '<div class="text-right font-weight-bold text-' . ($row['Type'] === 'IN' ? 'success' : 'danger') . '">' . 
                     $prefix . ' ' . number_format($row['Amount'], 0, ',', '.') . '</div>';
        
        $userCol = '<small>' . htmlspecialchars($row['UserName']) . '</small>';
        
        $data[] = [
            $dateCol,
            $descCol,
            $typeCol,
            $amountCol,
            $userCol
        ];
    }
}

echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $totalFiltered,
    "data" => $data
]);
