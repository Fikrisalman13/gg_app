<?php
// scrap_serverside.php - Server-side processing untuk DataTables scrap
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
    $length = isset($input['length']) ? intval($input['length']) : 10;
    $searchValue = isset($input['search']['value']) ? $input['search']['value'] : '';
    
    // Filter parameters
    $padderId = isset($input['padder_id']) ? $input['padder_id'] : '';
    $startDate = isset($input['start_date']) ? $input['start_date'] : '';
    $endDate = isset($input['end_date']) ? $input['end_date'] : '';

    // Build WHERE conditions
    $whereConditions = [];
    $params = [];
    $paramTypes = [];

    // Base condition - hanya data scrap
    $whereConditions[] = "s.scrap_date IS NOT NULL";

    // Search filter
    if (!empty($searchValue)) {
        $whereConditions[] = "(p.padder_id LIKE ? OR p.padder_name LIKE ? OR s.scrap_location LIKE ? OR s.scrap_notes LIKE ?)";
        $searchParam = "%{$searchValue}%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
        $paramTypes = array_merge($paramTypes, ['s', 's', 's', 's']);
    }

    // Padder filter
    if (!empty($padderId)) {
        $whereConditions[] = "s.padder_id = ?";
        $params[] = $padderId;
        $paramTypes[] = 's';
    }

    // Date filter
    if (!empty($startDate)) {
        $whereConditions[] = "s.scrap_date >= ?";
        $params[] = $startDate;
        $paramTypes[] = 's';
    }
    if (!empty($endDate)) {
        $whereConditions[] = "s.scrap_date <= ?";
        $params[] = $endDate;
        $paramTypes[] = 's';
    }

    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }

    // Base query
    $baseQuery = "FROM dbo.pad_t_scrap s
                  INNER JOIN dbo.pad_m_padder p ON s.padder_id = p.padder_id
                  LEFT JOIN (
                      SELECT scrap_id, COUNT(*) as photo_count
                      FROM dbo.pad_t_scrap_files
                      GROUP BY scrap_id
                  ) pf ON s.id = pf.scrap_id
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
        's.scrap_date', 'p.padder_id', 'p.padder_name', 
        's.scrap_location', 'pf.photo_count', 's.scrap_notes'
    ];
    
    $orderBy = "ORDER BY " . $columns[$orderColumn] . " " . $orderDir;

    // Main data query
    $dataSql = "SELECT 
                    s.id,
                    s.padder_id,
                    p.padder_name,
                    s.scrap_date,
                    FORMAT(s.scrap_date, 'dd/MM/yyyy') as scrap_date_formatted,
                    s.scrap_location,
                    s.scrap_notes,
                    ISNULL(pf.photo_count, 0) as photo_count
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
    error_log("Error in scrap_serverside.php: " . $e->getMessage());
    echo json_encode([
        'draw' => isset($draw) ? $draw : 0,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Terjadi kesalahan server: ' . $e->getMessage()
    ]);
}
?>