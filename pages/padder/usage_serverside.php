<?php
// usage_serverside.php - Server-side processing untuk DataTables
session_start();
require_once __DIR__ . '/../../koneksi.php';

// Response JSON header
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Ambil parameter dari DataTables
    $draw = intval($_POST['draw'] ?? 1);
    $start = intval($_POST['start'] ?? 0);
    $length = intval($_POST['length'] ?? 10);
    $searchValue = $_POST['search']['value'] ?? '';
    $padderId = $_POST['padder_id'] ?? '';
    $machineName = $_POST['machine_name'] ?? '';
    $location = $_POST['location'] ?? '';
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';

    // Query dasar
    $sql = "SELECT 
                u.id,
                u.used_date,
                u.location,
                u.machine_name,
                u.remarks,
                u.padder_id,
                p.padder_name,
                p.status as padder_status
            FROM dbo.pad_t_usage u
            INNER JOIN dbo.pad_m_padder p ON u.padder_id = p.padder_id
            WHERE 1=1";

    $params = [];
    $types = [];

    // Filter pencarian
    if (!empty($searchValue)) {
        $sql .= " AND (u.padder_id LIKE ? OR p.padder_name LIKE ? OR u.location LIKE ? OR u.machine_name LIKE ? OR u.remarks LIKE ?)";
        $searchParam = "%{$searchValue}%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
        $types = array_merge($types, [SQLSRV_PARAM_IN, SQLSRV_PARAM_IN, SQLSRV_PARAM_IN, SQLSRV_PARAM_IN, SQLSRV_PARAM_IN]);
    }

    // Filter padder_id
    if (!empty($padderId)) {
        $sql .= " AND u.padder_id = ?";
        $params[] = $padderId;
        $types[] = SQLSRV_PARAM_IN;
    }

    // Filter machine_name
    if (!empty($machineName)) {
        $sql .= " AND u.machine_name = ?";
        $params[] = $machineName;
        $types[] = SQLSRV_PARAM_IN;
    }

    // Filter location
    if (!empty($location)) {
        $sql .= " AND u.location LIKE ?";
        $params[] = "%{$location}%";
        $types[] = SQLSRV_PARAM_IN;
    }

    // Filter tanggal
    if (!empty($startDate)) {
        $sql .= " AND CONVERT(DATE, u.used_date) >= ?";
        $params[] = $startDate;
        $types[] = SQLSRV_PARAM_IN;
    }

    if (!empty($endDate)) {
        $sql .= " AND CONVERT(DATE, u.used_date) <= ?";
        $params[] = $endDate;
        $types[] = SQLSRV_PARAM_IN;
    }

    // Query untuk total records (tanpa filter)
    $countAllSql = "SELECT COUNT(*) as total FROM dbo.pad_t_usage";
    $countAllStmt = sqlsrv_query($conn, $countAllSql);
    $totalRecords = 0;
    if ($countAllStmt !== false && $row = sqlsrv_fetch_array($countAllStmt, SQLSRV_FETCH_ASSOC)) {
        $totalRecords = $row['total'];
    }
    if ($countAllStmt) sqlsrv_free_stmt($countAllStmt);

    // Query untuk total records filtered (dengan filter)
    $countFilteredSql = "SELECT COUNT(*) as total FROM ($sql) as count_table";
    $countFilteredStmt = sqlsrv_query($conn, $countFilteredSql, $params);
    $totalFiltered = 0;
    if ($countFilteredStmt !== false && $row = sqlsrv_fetch_array($countFilteredStmt, SQLSRV_FETCH_ASSOC)) {
        $totalFiltered = $row['total'];
    }
    if ($countFilteredStmt) sqlsrv_free_stmt($countFilteredStmt);

    // Query untuk data dengan pagination - TANPA MENGGUNAKAN $types ARRAY
    $sql .= " ORDER BY u.used_date DESC, u.id DESC";
    $sql .= " OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
    
    // Tambahkan parameter pagination
    $params[] = $start;
    $params[] = $length;

    // Eksekusi query dengan parameter saja (tanpa $types)
    $stmt = sqlsrv_query($conn, $sql, $params);

    $data = [];
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // Format tanggal
            $usedDate = $row['used_date'];
            if ($usedDate instanceof DateTime) {
                $row['used_date_formatted'] = $usedDate->format('d-m-Y');
            } else {
                $row['used_date_formatted'] = $row['used_date'];
            }

            // Pastikan semua field required ada
            $formattedRow = [
                'id' => $row['id'] ?? 0,
                'used_date' => $row['used_date'] ?? '',
                'used_date_formatted' => $row['used_date_formatted'] ?? '',
                'padder_id' => $row['padder_id'] ?? '',
                'padder_name' => $row['padder_name'] ?? '',
                'location' => $row['location'] ?? '',
                'machine_name' => $row['machine_name'] ?? '',
                'padder_status' => $row['padder_status'] ?? '',
                'remarks' => $row['remarks'] ?? ''
            ];

            $data[] = $formattedRow;
        }
        sqlsrv_free_stmt($stmt);
    } else {
        $errors = sqlsrv_errors();
        // Return error details for debugging
        $response = [
            'draw' => $draw,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => 'SQL Server Error',
            'debug' => [
                'sql_errors' => $errors,
                'final_sql' => $sql,
                'params' => $params,
                'param_count' => count($params)
            ]
        ];
        echo json_encode($response);
        exit;
    }

    // Response untuk DataTables
    $response = [
        'draw' => $draw,
        'recordsTotal' => intval($totalRecords),
        'recordsFiltered' => intval($totalFiltered),
        'data' => $data
    ];

    echo json_encode($response);
    exit;

} catch (Exception $e) {
    $response = [
        'draw' => intval($draw ?? 1),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => $e->getMessage()
    ];
    echo json_encode($response);
    exit;
}
?>