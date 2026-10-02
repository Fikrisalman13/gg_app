<?php
// repair_serverside.php - Server-side processing untuk DataTable Repair
session_start();
require_once __DIR__ . '/../../koneksi.php';

date_default_timezone_set('Asia/Jakarta');

// ====== Auth Check ======
if (!isset($_SESSION['UserName'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

try {
    // Parameters dari DataTables
    $draw = $_POST['draw'] ?? 1;
    $start = $_POST['start'] ?? 0;
    $length = $_POST['length'] ?? 10;
    $searchValue = $_POST['search']['value'] ?? '';
    $padderId = $_POST['padder_id'] ?? '';
    $vendorId = $_POST['vendor_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';

    // Base query dengan JOIN ke master padder dan vendor
    $baseQuery = "FROM dbo.pad_t_repair r 
                  INNER JOIN dbo.pad_m_padder p ON r.padder_id = p.padder_id 
                  LEFT JOIN dbo.pad_m_vendor v ON r.vendor_id = v.vendor_id 
                  WHERE 1=1";

    $countQuery = "SELECT COUNT(*) as total " . $baseQuery;
    $filteredQuery = "SELECT COUNT(*) as filtered " . $baseQuery;

    $params = [];
    $types = [];

    // Apply filters
    if (!empty($padderId)) {
        $baseQuery .= " AND r.padder_id = ?";
        $countQuery .= " AND r.padder_id = ?";
        $filteredQuery .= " AND r.padder_id = ?";
        $params[] = $padderId;
        $types[] = SQLSRV_PARAM_IN;
    }

    if (!empty($vendorId)) {
        $baseQuery .= " AND r.vendor_id = ?";
        $countQuery .= " AND r.vendor_id = ?";
        $filteredQuery .= " AND r.vendor_id = ?";
        $params[] = $vendorId;
        $types[] = SQLSRV_PARAM_IN;
    }

    if (!empty($status)) {
        $baseQuery .= " AND r.status = ?";
        $countQuery .= " AND r.status = ?";
        $filteredQuery .= " AND r.status = ?";
        $params[] = $status;
        $types[] = SQLSRV_PARAM_IN;
    }

    if (!empty($startDate)) {
        $baseQuery .= " AND r.send_date >= ?";
        $countQuery .= " AND r.send_date >= ?";
        $filteredQuery .= " AND r.send_date >= ?";
        $params[] = $startDate;
        $types[] = SQLSRV_PARAM_IN;
    }

    if (!empty($endDate)) {
        $baseQuery .= " AND r.send_date <= ?";
        $countQuery .= " AND r.send_date <= ?";
        $filteredQuery .= " AND r.send_date <= ?";
        $params[] = $endDate;
        $types[] = SQLSRV_PARAM_IN;
    }

    // Search filter
    if (!empty($searchValue)) {
        $searchFilter = " AND (r.padder_id LIKE ? OR p.padder_name LIKE ? OR v.vendor_name LIKE ? OR r.sj_number LIKE ? OR r.repair_notes LIKE ? OR r.status LIKE ?)";
        $baseQuery .= $searchFilter;
        $filteredQuery .= $searchFilter;
        
        $searchParam = "%" . $searchValue . "%";
        for ($i = 0; $i < 6; $i++) {
            $params[] = $searchParam;
            $types[] = SQLSRV_PARAM_IN;
        }
    }

    // Get total records count (without filters)
    $totalRecords = 0;
    $stmtTotal = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM dbo.pad_t_repair");
    if ($stmtTotal !== false && $row = sqlsrv_fetch_array($stmtTotal, SQLSRV_FETCH_ASSOC)) {
        $totalRecords = $row['total'];
    }
    if ($stmtTotal) sqlsrv_free_stmt($stmtTotal);

    // Get filtered records count
    $filteredRecords = 0;
    $stmtFiltered = sqlsrv_query($conn, $filteredQuery, $params);
    if ($stmtFiltered !== false && $row = sqlsrv_fetch_array($stmtFiltered, SQLSRV_FETCH_ASSOC)) {
        $filteredRecords = $row['filtered'];
    }
    if ($stmtFiltered) sqlsrv_free_stmt($stmtFiltered);

    // Main data query dengan informasi foto
    $dataQuery = "SELECT 
                    r.id,
                    r.padder_id,
                    p.padder_name,
                    p.status as padder_status,
                    r.send_date,
                    FORMAT(r.send_date, 'dd/MM/yyyy') as send_date_formatted,
                    r.sj_number,
                    r.vendor_id,
                    v.vendor_name,
                    r.repair_notes,
                    r.status,
                    (
                        SELECT COUNT(*) 
                        FROM dbo.pad_t_repair_files rf 
                        WHERE rf.repair_id = r.id
                    ) as photo_count,
                    (
                        SELECT COUNT(*) 
                        FROM dbo.pad_t_repair_files rf 
                        WHERE rf.repair_id = r.id AND rf.file_category = 'BEFORE'
                    ) as has_before,
                    (
                        SELECT COUNT(*) 
                        FROM dbo.pad_t_repair_files rf 
                        WHERE rf.repair_id = r.id AND rf.file_category = 'AFTER'
                    ) as has_after
                  " . $baseQuery;

    // Order by
    $orderColumn = $_POST['order'][0]['column'] ?? 1;
    $orderDirection = $_POST['order'][0]['dir'] ?? 'desc';
    
    $columns = [
        1 => 'r.send_date',
        2 => 'r.padder_id',
        3 => 'p.padder_name',
        4 => 'v.vendor_name',
        5 => 'r.sj_number',
        6 => 'r.status',
        7 => 'r.repair_notes'
    ];

    $orderBy = $columns[$orderColumn] ?? 'r.send_date';
    $dataQuery .= " ORDER BY " . $orderBy . " " . $orderDirection;

    // Add pagination
    $dataQuery .= " OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
    $params[] = (int)$start;
    $types[] = SQLSRV_PARAM_IN;
    $params[] = (int)$length;
    $types[] = SQLSRV_PARAM_IN;

    // Execute main query
    $stmt = sqlsrv_query($conn, $dataQuery, $params);
    
    $data = [];
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // Format dates properly
            if ($row['send_date'] instanceof DateTime) {
                $row['send_date'] = $row['send_date']->format('Y-m-d');
            }
            
            $data[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    } else {
        // Log error for debugging
        $errors = sqlsrv_errors();
        error_log("SQL Server Error in repair_serverside: " . print_r($errors, true));
        throw new Exception("Database query failed");
    }

    // Prepare response
    $response = [
        "draw" => intval($draw),
        "recordsTotal" => intval($totalRecords),
        "recordsFiltered" => intval($filteredRecords),
        "data" => $data
    ];

    header('Content-Type: application/json');
    echo json_encode($response);

} catch (Exception $e) {
    error_log("Exception in repair_serverside: " . $e->getMessage());
    
    $response = [
        "draw" => intval($draw ?? 1),
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => [],
        "error" => "Terjadi kesalahan saat memuat data: " . $e->getMessage()
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
}
?>