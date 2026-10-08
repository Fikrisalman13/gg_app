<?php
// pages/resep_obat/serverside_resep.php

// ====== CLEAN OUTPUT BUFFER ======
ob_clean();
ob_start();

session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../../koneksi3.php';
require_once __DIR__ . '/label_jual_lookup.php';

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

    $orderableColumns = [
        1 => 'r.no_cp',
        2 => 'r.kode_warna',
        3 => 'r.color_name',
        4 => 'r.cus_color',
        5 => 'r.weight',
        6 => 'r.plan_qty',
        7 => 'r.color_desc',
        8 => 'r.status_resep_lipat'
    ];
    $orderIndex = (int)($_POST['order'][0]['column'] ?? 1);
    $orderColumn = $orderableColumns[$orderIndex] ?? 'r.no_cp';
    $orderDirection = strtolower($_POST['order'][0]['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

    // ====== Build Query ======
    $whereConditions = [];
    $params = [];

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

    // ====== Status Filter ======
    $statusFilter = $_POST['statusFilter'] ?? [];
    if (!is_array($statusFilter)) {
        $statusFilter = [$statusFilter];
    }
    $statusFilter = array_values(array_filter(array_map('trim', $statusFilter), static fn($v) => $v !== ''));
    if (!empty($statusFilter)) {
        $placeholders = implode(',', array_fill(0, count($statusFilter), '?'));
        $whereConditions[] = "r.status_resep_lipat IN ($placeholders)";
        foreach ($statusFilter as $status) {
            $params[] = $status;
        }
    }

    // ====== Cus Color Filter ======
    $cusColorFilter = trim($_POST['cusColorFilter'] ?? '');
    if ($cusColorFilter !== '') {
        $whereConditions[] = "r.cus_color LIKE ?";
        $params[] = "%{$cusColorFilter}%";
    }

    // ====== Combine WHERE ======
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }

    // ====== Total records ======
    $sqlTotal = "SELECT COUNT(*) as total " . $baseQuery;
    $stmtTotal = sqlsrv_query($conn, $sqlTotal);
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

    // ====== Status summary for active filters ======
    $sqlSummary = "SELECT
        SUM(CASE WHEN LOWER(LTRIM(RTRIM(COALESCE(r.status_resep_lipat, '')))) = 'shading' THEN 1 ELSE 0 END) AS shading,
        SUM(CASE WHEN LOWER(LTRIM(RTRIM(COALESCE(r.status_resep_lipat, '')))) = 'experiment' THEN 1 ELSE 0 END) AS experiment,
        SUM(CASE WHEN LOWER(LTRIM(RTRIM(COALESCE(r.status_resep_lipat, '')))) IN ('kestabilan', 'kesetabilan') THEN 1 ELSE 0 END) AS kesetabilan,
        SUM(CASE WHEN LOWER(LTRIM(RTRIM(COALESCE(r.status_resep_lipat, '')))) IN ('master resep', 'master') THEN 1 ELSE 0 END) AS master_resep
        $baseQuery $whereClause";
    $stmtSummary = sqlsrv_query($conn, $sqlSummary, $params);
    $statusSummary = ['shading' => 0, 'experiment' => 0, 'kesetabilan' => 0, 'master_resep' => 0];
    if ($stmtSummary && $summaryRow = sqlsrv_fetch_array($stmtSummary, SQLSRV_FETCH_ASSOC)) {
        foreach ($statusSummary as $key => $value) {
            $statusSummary[$key] = (int)($summaryRow[$key] ?? 0);
        }
    }
    if ($stmtSummary) sqlsrv_free_stmt($stmtSummary);

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
                ORDER BY $orderColumn $orderDirection, r.id DESC
                OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

    $paramsData = array_merge($params, [(int)$start, (int)$length]);
    $stmtData = sqlsrv_query($conn, $sqlData, $paramsData);
    if (!$stmtData) {
        throw new RuntimeException('Gagal mengambil daftar resep.');
    }

    $pageRows = [];
    while ($row = sqlsrv_fetch_array($stmtData, SQLSRV_FETCH_ASSOC)) {
        $pageRows[] = $row;
    }
    $labelsByProductionNumber = recipeSaleLabelsByProductionNumber(
        $conn3,
        array_column($pageRows, 'no_cp'),
    );

    $data = [];
    foreach ($pageRows as $row) {
        $createdAt = '-';
        if ($row['created_at'] instanceof DateTime) {
            $createdAt = $row['created_at']->format('d-m-Y H:i');
        } elseif ($row['created_at']) {
            $createdAt = date('d-m-Y H:i', strtotime($row['created_at']));
        }
        $productionNumber = trim((string) ($row['no_cp'] ?? ''));

        $data[] = [
            'id' => $row['id'],
            'no_cp' => $productionNumber !== '' ? $productionNumber : '-',
            'label_jual' => $labelsByProductionNumber[$productionNumber] ?? '-',
            'kode_warna' => $row['kode_warna'] ?? '-',
            'cus_color' => $row['cus_color'] ?? '-',
            'color_name' => $row['color_name'] ?? '-',
            'lot_no' => $row['lot_no'] ?? '-',
            'weight' => $row['weight'] ?? 0,
            'plan_qty' => $row['plan_qty'] ?? 0,
            'color_desc' => $row['color_desc'] ?? '-',
            'resep_no' => $row['resep_no'] ?? '',
            'is_manual' => $row['is_manual'] ?? 0,
            'status_resep_lipat' => $row['status_resep_lipat'] ?? '-',
            'created_at' => $createdAt,
            'created_by' => $row['created_by'] ?? '-',
        ];
    }
    sqlsrv_free_stmt($stmtData);

    $response = [
        'draw' => intval($draw),
        'recordsTotal' => intval($totalRecords),
        'recordsFiltered' => intval($totalFiltered),
        'statusSummary' => $statusSummary,
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
