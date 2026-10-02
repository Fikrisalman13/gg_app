<?php
// disbursements_fetch.php - Server-side for disbursements
error_reporting(0);
session_start();
require_once __DIR__ . '/../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    die(json_encode(['error' => 'Unauthorized']));
}

// Fetch available Cash Accounts for the dropdown inside the table
$accounts = [];
$accStmt = sqlsrv_query($conn, "SELECT AccountId, AccountName, CurrentBalance FROM fin_cash_accounts");
if ($accStmt) {
    while ($row = sqlsrv_fetch_array($accStmt, SQLSRV_FETCH_ASSOC)) $accounts[] = $row;
    sqlsrv_free_stmt($accStmt);
}

// Base Query
$baseSql = "FROM fin_requests r
            LEFT JOIN fin_org_units u ON r.UnitId = u.UnitId
            LEFT JOIN fin_categories c ON r.CategoryId = c.CategoryId
            LEFT JOIN dbo.SMUserMs usr ON r.CreatedBy = usr.UserId
            LEFT JOIN dbo.m_emp emp ON usr.EmpId = emp.id_emp
            WHERE r.Status = 'Approved'";

// Count total records
$totalSql = "SELECT COUNT(*) as Total " . $baseSql;
$totalStmt = sqlsrv_query($conn, $totalSql);
$totalRecords = 0;
if ($totalStmt && $row = sqlsrv_fetch_array($totalStmt, SQLSRV_FETCH_ASSOC)) {
    $totalRecords = $row['Total'];
}

// DataTables parameters
$draw = intval($_POST['draw'] ?? 0);
$start = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);

// Data Query with Pagination
$dataSql = "SELECT r.*, u.UnitName, c.CategoryName, emp.nama_lengkap as RequesterName " . $baseSql . " 
            ORDER BY r.UpdatedAt ASC
            OFFSET $start ROWS FETCH NEXT $length ROWS ONLY";
$totalFiltered = $totalRecords; // Simplified for now since no search is implemented
$dataStmt = sqlsrv_query($conn, $dataSql);
$data = [];

if ($dataStmt) {
    while ($r = sqlsrv_fetch_array($dataStmt, SQLSRV_FETCH_ASSOC)) {
        
        $idCol = '<div class="align-middle font-weight-bold text-primary">#' . $r['RequestId'] . '</div>';
        $requesterCol = '<div class="align-middle"><div class="font-weight-bold text-dark">' . htmlspecialchars($r['RequesterName'] ?? 'Unknown') . '</div>' .
                        '<small class="text-muted small">' . ($r['UpdatedAt'] ? date_format($r['UpdatedAt'], 'd M Y') : '-') . '</small></div>';
        
        $unitCol = '<div class="align-middle"><span class="badge badge-light border text-dark">' . htmlspecialchars($r['UnitName'] ?? '-') . '</span></div>';
        
        $titleCol = '<div class="align-middle"><div class="font-weight-bold text-dark">' . htmlspecialchars($r['Title']) . '</div>' .
                    '<div class="text-indigo font-weight-bold">Rp ' . number_format($r['Amount'], 0, ',', '.') . '</div></div>';
        
        // Account select
        $select = '<select class="form-control select-account shadow-sm" id="acc_'.$r['RequestId'].'">';
        $select .= '<option value="">-- Pilih Rekening --</option>';
        foreach ($accounts as $a) {
            $select .= '<option value="'.$a['AccountId'].'" data-name="'.htmlspecialchars($a['AccountName']).'" data-balance="Rp '.number_format($a['CurrentBalance'], 0, ',', '.').'">'.htmlspecialchars($a['AccountName']).'</option>';
        }
        $select .= '</select>';
        
        $actions = '<div class="text-center"><button class="btn btn-success btn-sm btn-pay px-3 shadow-sm font-weight-bold" style="border-radius: 8px;" data-id="'.$r['RequestId'].'" data-amount="'.$r['Amount'].'"><i class="fas fa-hand-holding-usd mr-1"></i> Cairkan</button></div>';
        
        $data[] = [
            $idCol,
            $requesterCol,
            $unitCol,
            $titleCol,
            $select,
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
