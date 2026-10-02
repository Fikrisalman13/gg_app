<?php
// pending_approvals_fetch.php - Server-side for pending approvals
error_reporting(0);
session_start();
require_once __DIR__ . '/../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    die(json_encode(['error' => 'Unauthorized']));
}

$userId = $_SESSION['UserId'];

// Base Query
$baseSql = "FROM fin_requests r
            JOIN fin_workflow_steps s ON r.CurrentStepId = s.StepId
            JOIN fin_group_members gm ON s.RequiredGroupId = gm.GroupId
            LEFT JOIN fin_org_units u ON r.UnitId = u.UnitId
            LEFT JOIN fin_categories c ON r.CategoryId = c.CategoryId
            LEFT JOIN dbo.SMUserMs usr ON r.CreatedBy = usr.UserId
            LEFT JOIN dbo.m_emp emp ON usr.EmpId = emp.id_emp
            WHERE r.Status = 'Pending' AND gm.MemberId = ?";

// Count total records
$totalSql = "SELECT COUNT(*) as Total " . $baseSql;
$totalStmt = sqlsrv_query($conn, $totalSql, [$userId]);
$totalRecords = 0;
if ($totalStmt && $row = sqlsrv_fetch_array($totalStmt, SQLSRV_FETCH_ASSOC)) {
    $totalRecords = $row['Total'];
}

// DataTables parameters
$draw = intval($_POST['draw'] ?? 0);
$start = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);

// Data Query with Pagination
$dataSql = "SELECT r.*, u.UnitName, c.CategoryName, s.StepName, emp.nama_lengkap as RequesterName " . $baseSql . " 
            ORDER BY r.RequestId DESC
            OFFSET $start ROWS FETCH NEXT $length ROWS ONLY";
$dataStmt = sqlsrv_query($conn, $dataSql, [$userId]);
$data = [];

if ($dataStmt) {
    while ($p = sqlsrv_fetch_array($dataStmt, SQLSRV_FETCH_ASSOC)) {
        
        $idCol = '<div class="align-middle font-weight-bold text-primary">#' . $p['RequestId'] . '</div>';
        $requesterCol = '<div class="align-middle"><div class="font-weight-bold text-dark">' . htmlspecialchars($p['RequesterName'] ?? 'Unknown') . '</div>' .
                        '<small class="text-muted small">' . ($p['CreatedAt'] ? date_format($p['CreatedAt'], 'd M Y') : '-') . '</small></div>';
        
        $unitCol = '<div class="align-middle"><span class="badge badge-light border text-dark">' . htmlspecialchars($p['UnitName']) . '</span><br>' .
                   '<small class="text-muted">' . htmlspecialchars($p['CategoryName']) . '</small></div>';
        
        $titleCol = '<div class="align-middle font-weight-bold text-dark">' . htmlspecialchars($p['Title']) . '</div>';
        $amountCol = '<div class="align-middle font-weight-bold text-indigo">Rp ' . number_format($p['Amount'], 0, ',', '.') . '</div>';
        $stepCol = '<div class="align-middle"><span class="badge badge-pill badge-warning shadow-sm">' . htmlspecialchars($p['StepName']) . '</span></div>';
        
        $actions = '<div class="d-flex justify-content-center" style="gap: 8px;">' .
                    '<button class="btn btn-success btn-xs py-2 shadow-sm btn-action-trigger" data-id="'.$p['RequestId'].'" data-action="Approve" data-title="'.htmlspecialchars($p['Title']).'" style="width: 40px; border-radius: 8px;" title="Approve"><i class="fas fa-check"></i></button>' .
                    '<button class="btn btn-danger btn-xs py-2 shadow-sm btn-action-trigger" data-id="'.$p['RequestId'].'" data-action="Reject" data-title="'.htmlspecialchars($p['Title']).'" style="width: 40px; border-radius: 8px;" title="Reject"><i class="fas fa-times"></i></button>' .
                   '</div>';
        
        $data[] = [
            $idCol,
            $requesterCol,
            $unitCol,
            $titleCol,
            $amountCol,
            $stepCol,
            $actions
        ];
    }
    sqlsrv_free_stmt($dataStmt);
}

echo json_encode([
    "draw" => intval($_POST['draw'] ?? 0),
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $totalRecords,
    "data" => $data
]);
