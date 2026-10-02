<?php
// get_maintenance_detail.php - Get detail maintenance record
session_start();
require_once __DIR__ . '/../../koneksi.php';

date_default_timezone_set('Asia/Jakarta');

// ====== Auth Check ======
if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// ====== Validation ======
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID maintenance tidak valid']);
    exit;
}

$maintenanceId = $_POST['id'];

try {
    // Query untuk mendapatkan detail maintenance
    $sql = "SELECT 
                m.id,
                m.padder_id,
                p.padder_name,
                m.maintenance_date,
                FORMAT(m.maintenance_date, 'dd/MM/yyyy') as maintenance_date_formatted,
                m.work_done,
                m.hardness_check,
                m.notes,
                p.status as padder_status,
                p.remarks as padder_remarks,
                m.maintenance_date as created_at,
                FORMAT(m.maintenance_date, 'dd/MM/yyyy HH:mm') as created_at_formatted
            FROM dbo.pad_t_maintenance m 
            INNER JOIN dbo.pad_m_padder p ON m.padder_id = p.padder_id 
            WHERE m.id = ?";
    
    $params = [$maintenanceId];
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        error_log("SQL Server Error in get_maintenance_detail: " . print_r($errors, true));
        throw new Exception("Gagal mengambil data dari database");
    }
    
    $maintenanceData = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    
    if (!$maintenanceData) {
        echo json_encode([
            'success' => false, 
            'message' => 'Data maintenance tidak ditemukan'
        ]);
        exit;
    }
    
    // Get specifications for the padder
    $specSql = "SELECT spec_name, spec_value 
                FROM dbo.pad_m_padder_spec 
                WHERE padder_id = ? 
                ORDER BY spec_name";
    
    $specParams = [$maintenanceData['padder_id']];
    $specStmt = sqlsrv_query($conn, $specSql, $specParams);
    
    $specifications = [];
    if ($specStmt !== false) {
        while ($spec = sqlsrv_fetch_array($specStmt, SQLSRV_FETCH_ASSOC)) {
            $specifications[] = $spec;
        }
        sqlsrv_free_stmt($specStmt);
    }
    
    // Get maintenance history for this padder
    $historySql = "SELECT 
                    maintenance_date,
                    FORMAT(maintenance_date, 'dd/MM/yyyy') as maintenance_date_formatted,
                    work_done,
                    hardness_check,
                    notes
                  FROM dbo.pad_t_maintenance 
                  WHERE padder_id = ? 
                  AND id != ?
                  ORDER BY maintenance_date DESC";
    
    $historyParams = [$maintenanceData['padder_id'], $maintenanceId];
    $historyStmt = sqlsrv_query($conn, $historySql, $historyParams);
    
    $maintenanceHistory = [];
    if ($historyStmt !== false) {
        while ($history = sqlsrv_fetch_array($historyStmt, SQLSRV_FETCH_ASSOC)) {
            $maintenanceHistory[] = $history;
        }
        sqlsrv_free_stmt($historyStmt);
    }
    
    // Format dates
    if ($maintenanceData['maintenance_date'] instanceof DateTime) {
        $maintenanceData['maintenance_date'] = $maintenanceData['maintenance_date']->format('Y-m-d');
    }
    
    if ($maintenanceData['created_at'] instanceof DateTime) {
        $maintenanceData['created_at'] = $maintenanceData['created_at']->format('Y-m-d H:i:s');
    }
    
    // Prepare response data
    $responseData = [
        'id' => $maintenanceData['id'],
        'padder_id' => $maintenanceData['padder_id'],
        'padder_name' => $maintenanceData['padder_name'],
        'maintenance_date' => $maintenanceData['maintenance_date'],
        'maintenance_date_formatted' => $maintenanceData['maintenance_date_formatted'],
        'work_done' => $maintenanceData['work_done'],
        'hardness_check' => $maintenanceData['hardness_check'],
        'notes' => $maintenanceData['notes'],
        'padder_status' => $maintenanceData['padder_status'],
        'padder_remarks' => $maintenanceData['padder_remarks'],
        'created_at' => $maintenanceData['created_at'],
        'created_at_formatted' => $maintenanceData['created_at_formatted'],
        'specifications' => $specifications,
        'maintenance_history' => $maintenanceHistory
    ];
    
    sqlsrv_free_stmt($stmt);
    
    echo json_encode([
        'success' => true,
        'data' => $responseData
    ]);
    
} catch (Exception $e) {
    error_log("Exception in get_maintenance_detail: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ]);
}
?>