<?php
// get_repair_detail.php - Get detail repair record
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
    echo json_encode(['success' => false, 'message' => 'ID repair tidak valid']);
    exit;
}

$repairId = $_POST['id'];

try {
    // Query untuk mendapatkan detail repair (tanpa created_at)
    $sql = "SELECT 
                r.id,
                r.padder_id,
                p.padder_name,
                p.status as padder_status,
                r.send_date,
                FORMAT(r.send_date, 'dd/MM/yyyy') as send_date_formatted,
                r.sj_number,
                r.vendor_id,
                v.vendor_name,
                v.contact_person,
                v.phone as vendor_phone,
                v.email as vendor_email,
                r.repair_notes,
                r.status
            FROM dbo.pad_t_repair r 
            INNER JOIN dbo.pad_m_padder p ON r.padder_id = p.padder_id 
            LEFT JOIN dbo.pad_m_vendor v ON r.vendor_id = v.vendor_id 
            WHERE r.id = ?";
    
    $params = [$repairId];
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        error_log("SQL Server Error in get_repair_detail: " . print_r($errors, true));
        throw new Exception("Gagal mengambil data dari database: " . $errors[0]['message']);
    }
    
    $repairData = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    
    if (!$repairData) {
        echo json_encode([
            'success' => false, 
            'message' => 'Data repair tidak ditemukan'
        ]);
        exit;
    }
    
    // Get photos for this repair
    $photoSql = "SELECT 
                    id,
                    file_path,
                    file_category,
                    uploaded_at,
                    FORMAT(uploaded_at, 'dd/MM/yyyy HH:mm') as uploaded_at_formatted
                 FROM dbo.pad_t_repair_files 
                 WHERE repair_id = ? 
                 ORDER BY file_category, uploaded_at";
    
    $photoParams = [$repairId];
    $photoStmt = sqlsrv_query($conn, $photoSql, $photoParams);
    
    $photos = [];
    if ($photoStmt !== false) {
        while ($photo = sqlsrv_fetch_array($photoStmt, SQLSRV_FETCH_ASSOC)) {
            $photos[] = $photo;
        }
        sqlsrv_free_stmt($photoStmt);
    }
    
    // Get specifications for the padder
    $specSql = "SELECT spec_name, spec_value 
                FROM dbo.pad_m_padder_spec 
                WHERE padder_id = ? 
                ORDER BY spec_name";
    
    $specParams = [$repairData['padder_id']];
    $specStmt = sqlsrv_query($conn, $specSql, $specParams);
    
    $specifications = [];
    if ($specStmt !== false) {
        while ($spec = sqlsrv_fetch_array($specStmt, SQLSRV_FETCH_ASSOC)) {
            $specifications[] = $spec;
        }
        sqlsrv_free_stmt($specStmt);
    }
    
    // Get repair history for this padder
    $historySql = "SELECT 
                    r.id,
                    r.send_date,
                    FORMAT(r.send_date, 'dd/MM/yyyy') as send_date_formatted,
                    v.vendor_name,
                    r.sj_number,
                    r.status,
                    r.repair_notes
                  FROM dbo.pad_t_repair r 
                  LEFT JOIN dbo.pad_m_vendor v ON r.vendor_id = v.vendor_id 
                  WHERE r.padder_id = ? 
                  AND r.id != ?
                  ORDER BY r.send_date DESC";
    
    $historyParams = [$repairData['padder_id'], $repairId];
    $historyStmt = sqlsrv_query($conn, $historySql, $historyParams);
    
    $repairHistory = [];
    if ($historyStmt !== false) {
        while ($history = sqlsrv_fetch_array($historyStmt, SQLSRV_FETCH_ASSOC)) {
            $repairHistory[] = $history;
        }
        sqlsrv_free_stmt($historyStmt);
    }
    
    // Get timeline information
    $timelineInfo = getTimelineInfo($conn, $repairData['padder_id'], $repairId);
    
    // Format dates properly
    $repairData = formatDates($repairData);
    
    // Prepare response data
    $responseData = [
        'id' => $repairData['id'],
        'padder_id' => $repairData['padder_id'],
        'padder_name' => $repairData['padder_name'],
        'padder_status' => $repairData['padder_status'],
        'send_date' => $repairData['send_date'],
        'send_date_formatted' => $repairData['send_date_formatted'],
        'sj_number' => $repairData['sj_number'],
        'vendor_id' => $repairData['vendor_id'],
        'vendor_name' => $repairData['vendor_name'],
        'vendor_contact' => $repairData['contact_person'],
        'vendor_phone' => $repairData['vendor_phone'],
        'vendor_email' => $repairData['vendor_email'],
        'repair_notes' => $repairData['repair_notes'],
        'status' => $repairData['status'],
        'photos' => $photos,
        'specifications' => $specifications,
        'repair_history' => $repairHistory,
        'timeline_info' => $timelineInfo
    ];
    
    // Add calculated timeline fields
    if (!empty($timelineInfo['completed_date_formatted'])) {
        $responseData['completed_date_formatted'] = $timelineInfo['completed_date_formatted'];
    }
    
    if (!empty($timelineInfo['received_date_formatted'])) {
        $responseData['received_date_formatted'] = $timelineInfo['received_date_formatted'];
    }
    
    if (!empty($timelineInfo['duration_days'])) {
        $responseData['duration_days'] = $timelineInfo['duration_days'];
    }
    
    sqlsrv_free_stmt($stmt);
    
    echo json_encode([
        'success' => true,
        'data' => $responseData
    ]);
    
} catch (Exception $e) {
    error_log("Exception in get_repair_detail: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ]);
}

// Function to get timeline information
function getTimelineInfo($conn, $padderId, $repairId) {
    $timeline = [];
    
    try {
        // Get send date (from repair table)
        $sendSql = "SELECT send_date FROM dbo.pad_t_repair WHERE id = ?";
        $sendStmt = sqlsrv_query($conn, $sendSql, [$repairId]);
        if ($sendStmt !== false && $sendRow = sqlsrv_fetch_array($sendStmt, SQLSRV_FETCH_ASSOC)) {
            if ($sendRow['send_date'] instanceof DateTime) {
                $timeline['send_date'] = $sendRow['send_date'];
                $timeline['send_date_formatted'] = $sendRow['send_date']->format('d/m/Y');
            }
        }
        if ($sendStmt) sqlsrv_free_stmt($sendStmt);
        
        // Get completed date (from status log when status changed to REPAIRED)
        $completedSql = "SELECT TOP 1 changed_at 
                         FROM dbo.pad_status_log 
                         WHERE padder_id = ? AND status = 'REPAIRED' 
                         AND changed_at >= (SELECT send_date FROM dbo.pad_t_repair WHERE id = ?)
                         ORDER BY changed_at ASC";
        $completedStmt = sqlsrv_query($conn, $completedSql, [$padderId, $repairId]);
        if ($completedStmt !== false && $completedRow = sqlsrv_fetch_array($completedStmt, SQLSRV_FETCH_ASSOC)) {
            if ($completedRow['changed_at'] instanceof DateTime) {
                $timeline['completed_date'] = $completedRow['changed_at'];
                $timeline['completed_date_formatted'] = $completedRow['changed_at']->format('d/m/Y');
            }
        }
        if ($completedStmt) sqlsrv_free_stmt($completedStmt);
        
        // Get received date (from status log when status changed to READY/IN_USE after repair)
        $receivedSql = "SELECT TOP 1 changed_at 
                        FROM dbo.pad_status_log 
                        WHERE padder_id = ? AND status IN ('READY', 'IN_USE') 
                        AND changed_at >= (SELECT send_date FROM dbo.pad_t_repair WHERE id = ?)
                        AND remarks LIKE '%repair%' 
                        ORDER BY changed_at ASC";
        $receivedStmt = sqlsrv_query($conn, $receivedSql, [$padderId, $repairId]);
        if ($receivedStmt !== false && $receivedRow = sqlsrv_fetch_array($receivedStmt, SQLSRV_FETCH_ASSOC)) {
            if ($receivedRow['changed_at'] instanceof DateTime) {
                $timeline['received_date'] = $receivedRow['changed_at'];
                $timeline['received_date_formatted'] = $receivedRow['changed_at']->format('d/m/Y');
            }
        }
        if ($receivedStmt) sqlsrv_free_stmt($receivedStmt);
        
        // Calculate duration if we have both send and completed dates
        if (!empty($timeline['send_date']) && !empty($timeline['completed_date'])) {
            $diff = $timeline['send_date']->diff($timeline['completed_date']);
            $timeline['duration_days'] = $diff->days;
        } elseif (!empty($timeline['send_date']) && empty($timeline['completed_date'])) {
            // If not completed yet, calculate from send date to today
            $today = new DateTime();
            $diff = $timeline['send_date']->diff($today);
            $timeline['duration_days'] = $diff->days;
        }
    } catch (Exception $e) {
        error_log("Error in getTimelineInfo: " . $e->getMessage());
    }
    
    return $timeline;
}

// Function to format dates properly
function formatDates($data) {
    if ($data['send_date'] instanceof DateTime) {
        $data['send_date'] = $data['send_date']->format('Y-m-d');
    }
    
    return $data;
}
?>