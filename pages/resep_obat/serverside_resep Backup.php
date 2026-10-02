<?php
// pages/resep_obat/serverside_resep.php

// ====== CLEAN OUTPUT BUFFER ======
ob_clean();
ob_start();

session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../koneksi.php';

// ====== Auth ======
if (!isset($_SESSION['UserName'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ====== ERROR HANDLING ======
error_reporting(0);
ini_set('display_errors', 0);

try {
    // ====== Parameters from DataTables ======
    $start  = $_POST['start'] ?? 0;
    $length = $_POST['length'] ?? 10;
    $search = $_POST['search']['value'] ?? '';
    $draw   = $_POST['draw'] ?? 1;

    // ====== Build Query ======
    $whereConditions = ["r.status_resep_lipat = ?"];
    $params = ['Master Resep'];

    // Base query
    $baseQuery = "FROM dbo.resep_obat r";

    // ====== Search ======
    // ====== Search ======
    if (!empty($search)) {
        $whereConditions[] = "(r.no_cp LIKE ? OR r.kode_warna LIKE ? OR r.cus_color LIKE ? OR r.color_name LIKE ? OR r.lot_no LIKE ? OR r.created_by LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    // ====== Date Filter ======
    if (!empty($_POST['startDate']) && !empty($_POST['endDate'])) {
        $whereConditions[] = "(r.created_at >= ? AND r.created_at <= ?)";
        
        $startDate = $_POST['startDate'] . ' 00:00:00';
        $endDate   = $_POST['endDate'] . ' 23:59:59';
        
        $params[] = $startDate;
        $params[] = $endDate;
    }

    // ====== Combine WHERE ======
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }

    // ====== Total records ======
    $sqlTotal = "SELECT COUNT(*) as total " . $baseQuery . " WHERE r.status_resep_lipat = ?";
    $stmtTotal = sqlsrv_query($conn, $sqlTotal, ['Master Resep']);
    $totalRecords = 0;
    if ($stmtTotal && $row = sqlsrv_fetch_array($stmtTotal, SQLSRV_FETCH_ASSOC)) {
        $totalRecords = $row['total'];
    }
    if ($stmtTotal) sqlsrv_free_stmt($stmtTotal);

    // ====== Total filtered records ======
    $sqlFiltered = "SELECT COUNT(*) as total " . $baseQuery . " " . $whereClause;
    $stmtFiltered = sqlsrv_query($conn, $sqlFiltered, $params);
    $totalFiltered = 0;
    if ($stmtFiltered && $row = sqlsrv_fetch_array($stmtFiltered, SQLSRV_FETCH_ASSOC)) {
        $totalFiltered = $row['total'];
    }
    if ($stmtFiltered) sqlsrv_free_stmt($stmtFiltered);

    // ====== Data records ======
    $sqlData = "SELECT 
                    r.id, 
                    r.no_cp, 
                    r.kode_warna, 
                    r.cus_color,
                    r.color_name,
                    r.lot_no, 
                    r.weight, 
                    r.plan_qty,
                    r.color_desc,
                    r.resep_no,
                    r.is_manual,
                    r.status_resep_lipat,
                    r.created_at, 
                    r.created_by
                $baseQuery
                $whereClause
                ORDER BY r.created_at DESC
                OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

    $paramsData = array_merge($params, [(int)$start, (int)$length]);
    $stmtData = sqlsrv_query($conn, $sqlData, $paramsData);

    $data = [];
    if ($stmtData) {
        while ($row = sqlsrv_fetch_array($stmtData, SQLSRV_FETCH_ASSOC)) {
            $created_at = '-';
            if ($row['created_at'] instanceof DateTime) {
                $created_at = $row['created_at']->format('d-m-Y H:i');
            } elseif ($row['created_at']) {
                $created_at = date('d-m-Y H:i', strtotime($row['created_at']));
            }

            // Calculate COST on the fly if needed, or query from details.
            // For list view, we might need to join or subquery if we want to show COST here.
            // For now, let's just show basic info.
            
            $data[] = [
                'id'            => $row['id'],
                'no_cp'         => !empty($row['no_cp']) ? $row['no_cp'] : '-',
                'kode_warna'    => $row['kode_warna'] ?? '-',
                'cus_color'     => $row['cus_color'] ?? '-',
                'color_name'    => $row['color_name'] ?? '-',
                'lot_no'        => $row['lot_no'] ?? '-',
                'weight'        => $row['weight'] ?? 0,
                'plan_qty'      => $row['plan_qty'] ?? 0,
                'color_desc'    => $row['color_desc'] ?? '-',
                'resep_no'      => $row['resep_no'] ?? '',
                'is_manual'     => $row['is_manual'] ?? 0,
                'status_resep_lipat' => $row['status_resep_lipat'] ?? '-',
                'created_at'    => $created_at,
                'created_by'    => $row['created_by'] ?? '-'
            ];
        }
        sqlsrv_free_stmt($stmtData);
    }

    $response = [
        'draw' => intval($draw),
        'recordsTotal' => intval($totalRecords),
        'recordsFiltered' => intval($totalFiltered),
        'data' => $data
    ];

} catch (Exception $e) {
    $response = [
        'draw' => intval($draw ?? 0),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
    ];
}

ob_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
?>
