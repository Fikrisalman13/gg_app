<?php
// requests_fetch.php - Server-side processing for DataTables in requests.php
session_start();
require_once __DIR__ . '/../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    die(json_encode(['error' => 'Unauthorized']));
}

$userId = $_SESSION['UserId'];
$groupId = $_SESSION['GroupId'];

// DataTables parameters
$draw = intval($_POST['draw'] ?? 0);
$start = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);
$searchValue = $_POST['search']['value'] ?? '';
$orderColumnIndex = intval($_POST['order'][0]['column'] ?? 0);
$orderDir = $_POST['order'][0]['dir'] ?? 'desc';
$startDate = $_POST['startDate'] ?? '';
$endDate = $_POST['endDate'] ?? '';

// Columns mapping for ordering
$columns = [
    0 => 'r.RequestId',
    1 => 'r.CreatedAt',
    2 => 'u.UnitName',
    3 => 'r.Title',
    4 => 'c.CategoryName',
    5 => 'r.Amount',
    6 => 'r.Status',
    7 => 's.StepName'
];
$orderBy = $columns[$orderColumnIndex] ?? 'r.CreatedAt';

// Base Query
$baseSql = "FROM fin_requests r
            LEFT JOIN fin_org_units u ON r.UnitId = u.UnitId
            LEFT JOIN fin_categories c ON r.CategoryId = c.CategoryId
            LEFT JOIN fin_workflow_steps s ON r.CurrentStepId = s.StepId";

// Filtering
$where = [];
$params = [];

// Privacy Filter: Only see own requests unless Admin/Finance (GroupId 1)
if ($groupId != 1) {
    $where[] = "r.CreatedBy = ?";
    $params[] = $userId;
}

// Search Filter
if (!empty($searchValue)) {
    $searchQuery = "(r.Title LIKE ? OR r.RequestId LIKE ? OR u.UnitName LIKE ? OR c.CategoryName LIKE ? OR r.Status LIKE ?)";
    $where[] = $searchQuery;
    $v = "%$searchValue%";
    $params[] = $v; $params[] = $v; $params[] = $v; $params[] = $v; $params[] = $v;
}

// Date Filter
if (!empty($startDate)) {
    $where[] = "CAST(r.CreatedAt AS DATE) >= ?";
    $params[] = $startDate;
}
if (!empty($endDate)) {
    $where[] = "CAST(r.CreatedAt AS DATE) <= ?";
    $params[] = $endDate;
}

$whereSql = "";
if (!empty($where)) {
    $whereSql = " WHERE " . implode(" AND ", $where);
}

// Total records without filtering
$totalSql = "SELECT COUNT(*) as Total FROM fin_requests r";
if ($groupId != 1) { $totalSql .= " WHERE r.CreatedBy = $userId"; }
$totalStmt = sqlsrv_query($conn, $totalSql);
$totalRecords = 0;
if ($totalStmt && $row = sqlsrv_fetch_array($totalStmt, SQLSRV_FETCH_ASSOC)) {
    $totalRecords = $row['Total'];
}

// Total records with filtering
$filteredSql = "SELECT COUNT(*) as Total" . $baseSql . $whereSql;
$filteredStmt = sqlsrv_query($conn, $filteredSql, $params);
$totalFiltered = 0;
if ($filteredStmt && $row = sqlsrv_fetch_array($filteredStmt, SQLSRV_FETCH_ASSOC)) {
    $totalFiltered = $row['Total'];
}

// Main Data Query
$dataSql = "SELECT r.*, u.UnitName, c.CategoryName, s.StepName as CurrentStepName,
           (SELECT COUNT(*) FROM fin_request_history h WHERE h.RequestId = r.RequestId AND h.Action = 'Approve') as ApprovalCount
           " . $baseSql . $whereSql . "
           ORDER BY $orderBy $orderDir
           OFFSET $start ROWS FETCH NEXT $length ROWS ONLY";

$dataStmt = sqlsrv_query($conn, $dataSql, $params);
$data = [];

if ($dataStmt) {
    while ($r = sqlsrv_fetch_array($dataStmt, SQLSRV_FETCH_ASSOC)) {
        // Prepare Row Data
        
        // ID & Tanggal
        $idCol = '<div class="align-middle font-weight-bold text-primary">#' . $r['RequestId'] . '</div>';
        $dateCol = '<div class="align-middle text-muted small">' . ($r['CreatedAt'] ? date_format($r['CreatedAt'], 'd M Y') : '-') . '</div>';
        
        // Unit
        $unitCol = '<span class="badge badge-light text-dark border">' . htmlspecialchars($r['UnitName'] ?? '-') . '</span>';
        
        // Judul
        $desc = '';
        if (!empty($r['Description'])) {
            $desc = '<small class="text-muted d-block text-truncate" style="max-width: 250px;">' . htmlspecialchars($r['Description']) . '</small>';
        }
        $titleCol = '<div class="font-weight-bold text-dark">' . htmlspecialchars($r['Title']) . '</div>' . $desc;
        
        // Kategori
        $catCol = '<div class="text-muted small">' . htmlspecialchars($r['CategoryName'] ?? '-') . '</div>';
        
        // Nominal
        $amountCol = '<div class="font-weight-bold text-indigo">Rp ' . number_format($r['Amount'], 0, ',', '.') . '</div>';
        
        // Status Badge
        $badgeClass = 'secondary';
        $statusLabel = $r['Status'];
        if ($r['Status'] == 'Approved') { $badgeClass = 'success'; }
        elseif ($r['Status'] == 'Rejected') { $badgeClass = 'danger'; }
        elseif ($r['Status'] == 'Paid') { $badgeClass = 'primary'; $statusLabel = 'Cair'; }
        elseif ($r['Status'] == 'Pending') { 
            if ($r['ApprovalCount'] > 0) {
                $badgeClass = 'info'; $statusLabel = 'On Process'; 
            } else {
                $badgeClass = 'warning'; $statusLabel = 'Waiting Approval';
            }
        }
        $statusCol = '<div class="text-center"><span class="badge badge-pill badge-' . $badgeClass . ' shadow-sm">' . $statusLabel . '</span></div>';
        
        // Posisi Approval
        $posCol = '<div class="small text-muted">' . ($r['CurrentStepName'] ?? ($r['Status'] == 'Paid' ? '<span class="text-success"><i class="fas fa-check-circle mr-1"></i>Selesai</span>' : '-')) . '</div>';
        
        // Aksi
        $actions = '<div class="d-flex justify-content-center" style="gap: 8px;">';
        $actions .= '<a href="request_details.php?id='.$r['RequestId'].'" class="btn btn-primary btn-xs py-2 shadow-sm" title="Detail" style="width: 35px; border-radius: 8px;"><i class="fas fa-eye"></i></a>';
        
        if ($r['Status'] != 'Rejected' && $r['Status'] != 'Draft') {
            $actions .= '<a href="requests_print.php?id='.$r['RequestId'].'" target="_blank" class="btn btn-info btn-xs py-2 shadow-sm" title="Print" style="width: 35px; border-radius: 8px;"><i class="fas fa-print"></i></a>';
        }
        
        if (in_array($r['Status'], ['Draft', 'Rejected', 'Pending'])) {
            $actions .= '<button class="btn btn-danger btn-xs py-2 shadow-sm btn-delete" data-id="'.$r['RequestId'].'" title="Hapus" style="width: 35px; border-radius: 8px;"><i class="fas fa-trash"></i></button>';
        }
        $actions .= '</div>';
        
        $data[] = [
            $idCol,
            $dateCol,
            $unitCol,
            $titleCol,
            $catCol,
            $amountCol,
            $statusCol,
            $posCol,
            $actions
        ];
    }
    sqlsrv_free_stmt($dataStmt);
}

echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $totalFiltered,
    "data" => $data
]);
