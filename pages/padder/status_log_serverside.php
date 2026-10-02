<?php
// status_log_serverside.php - Server-side processing untuk DataTables status log
session_start();
require_once __DIR__ . '/../../koneksi.php';

header('Content-Type: application/json');

// Check permission
if (!isset($_SESSION['UserName'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $input = $_POST;
    
    // Parse parameters
    $draw = isset($input['draw']) ? intval($input['draw']) : 0;
    $start = isset($input['start']) ? intval($input['start']) : 0;
    $length = isset($input['length']) ? intval($input['length']) : 25;
    $searchValue = isset($input['search']['value']) ? $input['search']['value'] : '';
    
    // Filter parameters
    $padderId = isset($input['padder_id']) ? $input['padder_id'] : '';
    $status = isset($input['status']) ? $input['status'] : '';
    $user = isset($input['user']) ? $input['user'] : '';
    $startDate = isset($input['start_date']) ? $input['start_date'] : '';
    $endDate = isset($input['end_date']) ? $input['end_date'] : '';

    // Build WHERE conditions
    $whereConditions = [];
    $params = [];
    $paramTypes = [];

    // Search filter
    if (!empty($searchValue)) {
        $whereConditions[] = "(p.padder_id LIKE ? OR p.padder_name LIKE ? OR l.status LIKE ? OR l.changed_by LIKE ? OR l.remarks LIKE ?)";
        $searchParam = "%{$searchValue}%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
        $paramTypes = array_merge($paramTypes, ['s', 's', 's', 's', 's']);
    }

    // Padder filter
    if (!empty($padderId)) {
        $whereConditions[] = "l.padder_id = ?";
        $params[] = $padderId;
        $paramTypes[] = 's';
    }

    // Status filter
    if (!empty($status)) {
        $whereConditions[] = "l.status = ?";
        $params[] = $status;
        $paramTypes[] = 's';
    }

    // User filter
    if (!empty($user)) {
        $whereConditions[] = "l.changed_by LIKE ?";
        $params[] = "%{$user}%";
        $paramTypes[] = 's';
    }

    // Date filter
    if (!empty($startDate)) {
        $whereConditions[] = "CAST(l.changed_at AS DATE) >= ?";
        $params[] = $startDate;
        $paramTypes[] = 's';
    }
    if (!empty($endDate)) {
        $whereConditions[] = "CAST(l.changed_at AS DATE) <= ?";
        $params[] = $endDate;
        $paramTypes[] = 's';
    }

    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }

    // Base query
    $baseQuery = "FROM dbo.pad_status_log l
                  INNER JOIN dbo.pad_m_padder p ON l.padder_id = p.padder_id
                  $whereClause";

    // Count total records
    $countTotalSql = "SELECT COUNT(*) as total $baseQuery";
    $stmt = sqlsrv_query($conn, $countTotalSql, $params);
    $totalRecords = 0;
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $totalRecords = $row['total'];
    }
    if ($stmt) sqlsrv_free_stmt($stmt);

    // Count filtered records
    $countFilteredSql = "SELECT COUNT(*) as filtered $baseQuery";
    $stmt = sqlsrv_query($conn, $countFilteredSql, $params);
    $filteredRecords = 0;
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $filteredRecords = $row['filtered'];
    }
    if ($stmt) sqlsrv_free_stmt($stmt);

    // Order by
    $orderColumn = isset($input['order'][0]['column']) ? intval($input['order'][0]['column']) : 1;
    $orderDir = isset($input['order'][0]['dir']) ? $input['order'][0]['dir'] : 'DESC';
    
    $columns = [
        'l.changed_at', 'l.padder_id', 'p.padder_name', 
        'l.status', 'l.changed_by', 'l.remarks'
    ];
    
    $orderBy = "ORDER BY " . $columns[$orderColumn] . " " . $orderDir;

    // Main data query
    $dataSql = "SELECT 
                    l.id,
                    l.padder_id,
                    p.padder_name,
                    l.status,
                    l.changed_at,
                    FORMAT(l.changed_at, 'dd/MM/yyyy HH:mm') as changed_at_formatted,
                    l.changed_by,
                    l.remarks
                $baseQuery
                $orderBy
                OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

    // Add pagination parameters
    $params[] = $start;
    $params[] = $length;
    $paramTypes[] = 'i';
    $paramTypes[] = 'i';

    $stmt = sqlsrv_query($conn, $dataSql, $params);
    
    $data = [];
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }

    // Prepare response
    $response = [
        'draw' => $draw,
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $filteredRecords,
        'data' => $data
    ];

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error in status_log_serverside.php: " . $e->getMessage());
    echo json_encode([
        'draw' => isset($draw) ? $draw : 0,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Terjadi kesalahan server: ' . $e->getMessage()
    ]);
}
?>