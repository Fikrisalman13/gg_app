<?php
// maintenance_serverside.php - Server-side processing untuk DataTable Maintenance
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
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';

    // Base query dengan JOIN ke master padder
    $baseQuery = "FROM dbo.pad_t_maintenance m 
                  INNER JOIN dbo.pad_m_padder p ON m.padder_id = p.padder_id 
                  WHERE 1=1";

    $countQuery = "SELECT COUNT(*) as total " . $baseQuery;
    $filteredQuery = "SELECT COUNT(*) as filtered " . $baseQuery;

    $params = [];
    $types = [];

    // Apply filters
    if (!empty($padderId)) {
        $baseQuery .= " AND m.padder_id = ?";
        $countQuery .= " AND m.padder_id = ?";
        $filteredQuery .= " AND m.padder_id = ?";
        $params[] = $padderId;
        $types[] = SQLSRV_PARAM_IN;
    }

    if (!empty($startDate)) {
        $baseQuery .= " AND m.maintenance_date >= ?";
        $countQuery .= " AND m.maintenance_date >= ?";
        $filteredQuery .= " AND m.maintenance_date >= ?";
        $params[] = $startDate;
        $types[] = SQLSRV_PARAM_IN;
    }

    if (!empty($endDate)) {
        $baseQuery .= " AND m.maintenance_date <= ?";
        $countQuery .= " AND m.maintenance_date <= ?";
        $filteredQuery .= " AND m.maintenance_date <= ?";
        $params[] = $endDate;
        $types[] = SQLSRV_PARAM_IN;
    }

    // Search filter
    if (!empty($searchValue)) {
        $searchFilter = " AND (m.padder_id LIKE ? OR p.padder_name LIKE ? OR m.work_done LIKE ? OR m.hardness_check LIKE ? OR m.notes LIKE ? OR p.status LIKE ?)";
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
    $stmtTotal = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM dbo.pad_t_maintenance");
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

    // Main data query
    $dataQuery = "SELECT 
                    m.id,
                    m.padder_id,
                    p.padder_name,
                    m.maintenance_date,
                    FORMAT(m.maintenance_date, 'dd/MM/yyyy') as maintenance_date_formatted,
                    m.work_done,
                    m.hardness_check,
                    m.notes,
                    p.status as padder_status
                  " . $baseQuery;

    // Order by
    $orderColumn = $_POST['order'][0]['column'] ?? 1;
    $orderDirection = $_POST['order'][0]['dir'] ?? 'desc';
    
    $columns = [
        1 => 'm.maintenance_date',
        2 => 'm.padder_id',
        3 => 'p.padder_name',
        4 => 'm.work_done',
        5 => 'm.hardness_check',
        6 => 'm.notes',
        7 => 'p.status'
    ];

    $orderBy = $columns[$orderColumn] ?? 'm.maintenance_date';
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
            if ($row['maintenance_date'] instanceof DateTime) {
                $row['maintenance_date'] = $row['maintenance_date']->format('Y-m-d');
            }
            
            $data[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    } else {
        // Log error for debugging
        $errors = sqlsrv_errors();
        error_log("SQL Server Error in maintenance_serverside: " . print_r($errors, true));
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
    error_log("Exception in maintenance_serverside: " . $e->getMessage());
    
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