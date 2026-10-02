<?php
// receive_serverside.php - FIXED VERSION
session_start();
require_once __DIR__ . '/../../koneksi.php';

// Set header untuk JSON
header('Content-Type: application/json');

// Enable error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Nonaktifkan display error, gunakan log saja

try {
    // Ambil parameter dengan cara yang lebih aman
    $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
    $padderId = isset($_POST['padder_id']) ? $_POST['padder_id'] : '';
    $startDate = isset($_POST['start_date']) ? $_POST['start_date'] : '';
    $endDate = isset($_POST['end_date']) ? $_POST['end_date'] : '';

    // Debug log
    error_log("=== RECEIVE SERVERSIDE START ===");
    error_log("Draw: $draw, Start: $start, Length: $length");
    error_log("Search: $searchValue, Padder: $padderId");
    error_log("Start Date: $startDate, End Date: $endDate");

    // Build WHERE clause dengan cara yang lebih sederhana
    $whereConditions = [];
    $params = [];
    
    if (!empty($padderId)) {
        $whereConditions[] = "r.padder_id = ?";
        $params[] = $padderId;
    }
    
    if (!empty($startDate)) {
        $whereConditions[] = "r.receive_date >= ?";
        $params[] = $startDate;
    }
    
    if (!empty($endDate)) {
        $whereConditions[] = "r.receive_date <= ?";
        $params[] = $endDate;
    }
    
    if (!empty($searchValue)) {
        $whereConditions[] = "(r.padder_id LIKE ? OR p.padder_name LIKE ? OR r.grn_number LIKE ? OR r.remarks LIKE ?)";
        $searchParam = "%" . $searchValue . "%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }

    // Query untuk data - tanpa menggunakan $types array
    $sql = "SELECT r.id, r.padder_id, r.receive_date, r.grn_number, r.remarks,
                   p.padder_name,
                   CASE WHEN EXISTS (
                       SELECT 1 FROM dbo.pad_t_purchase pur 
                       WHERE pur.padder_id = r.padder_id
                   ) THEN 1 ELSE 0 END as has_purchase
            FROM dbo.pad_t_receive r
            LEFT JOIN dbo.pad_m_padder p ON r.padder_id = p.padder_id
            {$whereClause}
            ORDER BY r.receive_date DESC
            OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

    // Tambahkan parameter pagination
    $params[] = $start;
    $params[] = $length;

    error_log("SQL: " . $sql);
    error_log("Params count: " . count($params));
    error_log("Params: " . print_r($params, true));

    // Eksekusi query TANPA parameter types
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        error_log("SQL Server Error: " . print_r($errors, true));
        throw new Exception("Query execution failed: " . print_r($errors, true));
    }

    $data = [];
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // Format tanggal
            if ($row['receive_date'] instanceof DateTime) {
                $row['receive_date'] = $row['receive_date']->format('Y-m-d');
            }
            
            // Ambil foto untuk setiap penerimaan
            $photoSql = "SELECT file_path, file_type 
                        FROM dbo.pad_t_receive_files 
                        WHERE receive_id = ?";
            $photoStmt = sqlsrv_query($conn, $photoSql, [$row['id']]);
            
            $photos = [];
            if ($photoStmt !== false) {
                while ($photo = sqlsrv_fetch_array($photoStmt, SQLSRV_FETCH_ASSOC)) {
                    $photos[] = $photo;
                }
                sqlsrv_free_stmt($photoStmt);
            }
            
            $row['photos'] = $photos;
            $data[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }

    error_log("Data fetched: " . count($data) . " records");

    // Query untuk total records (tanpa filter)
    $countAllSql = "SELECT COUNT(*) as total FROM dbo.pad_t_receive";
    $countAllStmt = sqlsrv_query($conn, $countAllSql);
    $totalRecords = 0;
    if ($countAllStmt !== false && $countAllRow = sqlsrv_fetch_array($countAllStmt, SQLSRV_FETCH_ASSOC)) {
        $totalRecords = $countAllRow['total'];
    }
    if ($countAllStmt) sqlsrv_free_stmt($countAllStmt);

    // Query untuk filtered records (dengan filter)
    $filteredRecords = $totalRecords;
    if (!empty($whereConditions)) {
        $countFilteredSql = "SELECT COUNT(*) as filtered
                            FROM dbo.pad_t_receive r
                            LEFT JOIN dbo.pad_m_padder p ON r.padder_id = p.padder_id
                            {$whereClause}";
        
        // Hapus parameter pagination untuk count query
        $countParams = array_slice($params, 0, count($params) - 2);
        
        error_log("Count SQL: " . $countFilteredSql);
        error_log("Count Params: " . print_r($countParams, true));
        
        $countFilteredStmt = sqlsrv_query($conn, $countFilteredSql, $countParams);
        if ($countFilteredStmt !== false && $countFilteredRow = sqlsrv_fetch_array($countFilteredStmt, SQLSRV_FETCH_ASSOC)) {
            $filteredRecords = $countFilteredRow['filtered'];
        }
        if ($countFilteredStmt) sqlsrv_free_stmt($countFilteredStmt);
    }

    $response = [
        'draw' => $draw,
        'recordsTotal' => intval($totalRecords),
        'recordsFiltered' => intval($filteredRecords),
        'data' => $data
    ];

    error_log("Response prepared. Total: $totalRecords, Filtered: $filteredRecords, Data: " . count($data));
    error_log("=== RECEIVE SERVERSIDE END ===");

    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Exception in receive_serverside: " . $e->getMessage());
    
    echo json_encode([
        'draw' => isset($_POST['draw']) ? intval($_POST['draw']) : 1,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Terjadi kesalahan sistem. Silakan coba lagi.'
    ]);
}
?>