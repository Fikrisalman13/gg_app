<?php
// File: user_fingerprint_database_ajax.php
// HANYA untuk handle AJAX requests dari DataTables

// Mulai session dan cleanup buffer
session_start();
ob_start();

// Matikan semua output dan error display
error_reporting(0);
ini_set('display_errors', 0);

// Pastikan tidak ada output sebelum JSON
if (ob_get_level()) {
    ob_end_clean();
}

include '../../koneksi.php';

// CEK LOGIN - Hanya untuk API/AJAX
if (!isset($_SESSION['UserName'])) {
    sendJsonError('Unauthorized access', 401);
    exit;
}

// CEK PERMISSION
define('USER_FINGERPRINT_MENU_ID', 97);

/**
 * Send JSON response
 */
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/**
 * Send JSON error
 */
function sendJsonError($message, $statusCode = 500) {
    sendJsonResponse([
        'error' => true,
        'message' => $message
    ], $statusCode);
}

/**
 * Check user permissions
 */
function checkUserPermissions($conn, $groupId, $menuId) {
    $sql = "SELECT CanView FROM dbo.SMGroupTrustee WHERE GroupId=? AND MenuId=?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        return $row['CanView'] == 1;
    }
    return false;
}

// Cek permission untuk view
if (!checkUserPermissions($conn, $_SESSION['GroupId'], USER_FINGERPRINT_MENU_ID)) {
    sendJsonError('Access Denied', 403);
    exit;
}

/**
 * Process fingerprint data secara efisien
 */
function processFingerprintData($fingerprintData) {
    $result = [
        'has_fingerprint' => false,
        'fingerprint_count' => 0,
        'fingerprint_info' => 'Tidak ada'
    ];
    
    if (empty($fingerprintData)) {
        return $result;
    }
    
    try {
        // Konversi varbinary ke string jika perlu
        if (is_resource($fingerprintData)) {
            $fingerprintData = stream_get_contents($fingerprintData);
        }
        
        // Jika masih binary, coba decode sebagai string
        if (is_string($fingerprintData)) {
            // Coba decode sebagai JSON
            $decodedData = json_decode($fingerprintData, true);
            
            if ($decodedData !== null && is_array($decodedData)) {
                // Format JSON ditemukan
                $result['has_fingerprint'] = true;
                $result['fingerprint_count'] = count($decodedData);
                $result['fingerprint_info'] = $result['fingerprint_count'] . ' template';
            } else {
                // Cek jika data mengandung template fingerprint
                $dataLength = strlen($fingerprintData);
                
                // Jika data cukup panjang, kemungkinan ada template fingerprint
                if ($dataLength > 10) {
                    $result['has_fingerprint'] = true;
                    $result['fingerprint_count'] = 1;
                    $result['fingerprint_info'] = 'Binary data (' . $dataLength . ' bytes)';
                    
                    // Coba deteksi multiple templates
                    if (strpos($fingerprintData, '|') !== false || 
                        strpos($fingerprintData, ',') !== false ||
                        strpos($fingerprintData, ';') !== false) {
                        $templates = preg_split('/[|,;]/', $fingerprintData);
                        $validTemplates = array_filter($templates, function($template) {
                            return !empty(trim($template)) && strlen(trim($template)) > 5;
                        });
                        if (count($validTemplates) > 0) {
                            $result['fingerprint_count'] = count($validTemplates);
                            $result['fingerprint_info'] = $result['fingerprint_count'] . ' template';
                        }
                    }
                }
            }
        }
        
    } catch (Exception $e) {
        error_log("Error processing fingerprint data: " . $e->getMessage());
        $result['fingerprint_info'] = 'Error decoding';
    }
    
    return $result;
}

/**
 * Get users from database dengan search dan pagination
 */
function getUsersFromDatabase($conn, $mesinId = null, $search = '', $start = 0, $length = 10) {
    // Validasi input
    $mesinId = $mesinId ? (int)$mesinId : null;
    $start = (int)$start;
    $length = (int)$length;
    
    $sql = "SELECT 
                uf.id, uf.pin, uf.name, uf.privilege, uf.password, uf.mesin_id, 
                uf.fingerprint_data, uf.template_size, 
                uf.created_at, uf.updated_at,
                mf.nama_mesin, mf.ip_address, mf.last_download
            FROM dbo.user_fingerprint uf
            INNER JOIN dbo.m_fingerprint mf ON uf.mesin_id = mf.id
            WHERE 1=1";
    
    $params = [];
    
    if ($mesinId) {
        $sql .= " AND uf.mesin_id = ?";
        $params[] = $mesinId;
    }
    
    if (!empty($search)) {
        $sql .= " AND (uf.pin LIKE ? OR uf.name LIKE ? OR mf.nama_mesin LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Hitung total records filtered
    $countSql = "SELECT COUNT(*) as total FROM ($sql) as subquery";
    $countStmt = sqlsrv_query($conn, $countSql, $params);
    $totalFiltered = 0;
    if ($countStmt && $row = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC)) {
        $totalFiltered = (int)$row['total'];
    }
    if ($countStmt) sqlsrv_free_stmt($countStmt);
    
    // Tambahkan sorting dan pagination
    $sql .= " ORDER BY uf.created_at DESC, mf.nama_mesin, uf.pin
              OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
    
    $params[] = $start;
    $params[] = $length;
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    $users = [];
    
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // Format tanggal
            $row['created_at_formatted'] = $row['created_at'] ? 
                $row['created_at']->format('d-m-Y H:i:s') : '';
            $row['updated_at_formatted'] = $row['updated_at'] ? 
                $row['updated_at']->format('d-m-Y H:i:s') : '';
            $row['last_download_formatted'] = $row['last_download'] ? 
                $row['last_download']->format('d-m-Y H:i:s') : '';
            
            // Process fingerprint data
            $fingerprintInfo = processFingerprintData($row['fingerprint_data']);
            $row['has_fingerprint'] = $fingerprintInfo['has_fingerprint'];
            $row['fingerprint_count'] = $fingerprintInfo['fingerprint_count'];
            $row['fingerprint_info'] = $fingerprintInfo['fingerprint_info'];
            
            // Format template size
            $row['template_size_formatted'] = $row['template_size'] ? 
                number_format($row['template_size']) . ' bytes' : '-';
            
            // Format password (tampilkan sebagai asterisk atau teks biasa)
            $row['password_display'] = $row['password'] ? 
                '••••••••' : '-';
            
            $users[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
    
    return [
        'users' => $users,
        'totalFiltered' => $totalFiltered
    ];
}

/**
 * Get total users count
 */
function getTotalUsersCount($conn, $mesinId = null) {
    $sql = "SELECT COUNT(*) as total 
            FROM dbo.user_fingerprint uf
            WHERE 1=1";
    
    $params = [];
    
    if ($mesinId) {
        $sql .= " AND uf.mesin_id = ?";
        $params[] = $mesinId;
    }
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    $count = 0;
    
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $count = (int)$row['total'];
        sqlsrv_free_stmt($stmt);
    }
    
    return $count;
}

/**
 * Get all mesin
 */
function getAllMesin($conn) {
    $sql = "SELECT * FROM dbo.m_fingerprint ORDER BY nama_mesin";
    $stmt = sqlsrv_query($conn, $sql);
    $mesin = [];
    
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $mesin[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
    
    return $mesin;
}

// MAIN AJAX HANDLER
try {
    // Hanya terima GET request untuk DataTables
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendJsonError('Method not allowed', 405);
    }
    
    // Pastikan ini adalah request DataTables
    if (!isset($_GET['action']) || $_GET['action'] !== 'get_users') {
        sendJsonError('Invalid action', 400);
    }
    
    // Get mesin untuk validasi
    $mesin = getAllMesin($conn);
    
    // Validasi dan sanitize input
    $selectedMesinId = isset($_GET['mesin_id']) ? (int)$_GET['mesin_id'] : '';
    if ($selectedMesinId && !in_array($selectedMesinId, array_column($mesin, 'id'))) {
        $selectedMesinId = '';
    }
    
    // Parameters untuk DataTables
    $draw = isset($_GET['draw']) ? intval($_GET['draw']) : 1;
    $start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
    $length = isset($_GET['length']) ? (int)$_GET['length'] : 10;
    $search = isset($_GET['search']['value']) ? trim($_GET['search']['value']) : '';
    
    // Get data dengan search dan pagination
    $result = getUsersFromDatabase($conn, $selectedMesinId, $search, $start, $length);
    $users = $result['users'];
    $totalFiltered = $result['totalFiltered'];
    
    $totalUsers = getTotalUsersCount($conn, $selectedMesinId);
    
    // Prepare response data untuk DataTables
    $output = [
        'draw' => $draw,
        'recordsTotal' => $totalUsers,
        'recordsFiltered' => $totalFiltered,
        'data' => []
    ];
    
    foreach ($users as $i => $u) {
        $row = [];
        
        // Checkbox
        $row[] = '<input type="checkbox" class="user-checkbox" value="' . $u['id'] . '" 
                 data-pin="' . htmlspecialchars($u['pin']) . '" 
                 data-name="' . htmlspecialchars($u['name']) . '"
                 data-has-fingerprint="' . ($u['has_fingerprint'] ? '1' : '0') . '"
                 data-row-index="' . ($start + $i) . '">';
        
        // No
        $row[] = $start + $i + 1;
        
        // PIN
        $row[] = htmlspecialchars($u['pin']);
        
        // Name
        $row[] = htmlspecialchars($u['name']);
        
        // Privilege
        $row[] = htmlspecialchars($u['privilege']);
        
        // Password
        $passwordCell = $u['password_display'];
        if ($u['password']) {
            $passwordCell .= '<button class="btn btn-xs btn-outline-secondary view-password-btn ml-1" 
                            data-password="' . htmlspecialchars($u['password']) . '"
                            title="Lihat Password">
                            <i class="fas fa-eye"></i>
                        </button>';
        }
        $row[] = $passwordCell;
        
        // Mesin
        $row[] = htmlspecialchars($u['nama_mesin']);
        
        // Fingerprint
        $fingerprintCell = '<span class="fingerprint-indicator ' . ($u['has_fingerprint'] ? 'has-fingerprint' : 'no-fingerprint') . '"></span>';
        $fingerprintCell .= $u['fingerprint_info'];
        if ($u['has_fingerprint']) {
            $fingerprintCell .= '<button class="btn btn-xs btn-outline-info view-fingerprint-btn ml-1" 
                                data-user-id="' . $u['id'] . '" 
                                data-pin="' . htmlspecialchars($u['pin']) . '"
                                title="Lihat Detail Sidik Jari">
                                <i class="fas fa-eye"></i>
                            </button>';
        }
        $row[] = $fingerprintCell;
        
        // Template Size
        $row[] = $u['template_size_formatted'];
        
        // Created
        $row[] = $u['created_at_formatted'];
        
        // Updated
        $row[] = $u['updated_at_formatted'];
        
        // Actions (sederhana, tanpa permission check di sini)
        $actionCell = '<button class="btn btn-warning btn-sm edit-user mr-1" 
                      data-user-id="' . $u['id'] . '" 
                      data-pin="' . htmlspecialchars($u['pin']) . '"
                      data-name="' . htmlspecialchars($u['name']) . '"
                      data-privilege="' . htmlspecialchars($u['privilege']) . '"
                      data-password="' . htmlspecialchars($u['password']) . '"
                      title="Edit User">
                      <i class="fas fa-edit"></i>
                  </button>';
        $actionCell .= '<button class="btn btn-danger btn-sm delete-user" 
                      data-user-id="' . $u['id'] . '" 
                      data-pin="' . htmlspecialchars($u['pin']) . '"
                      title="Hapus dari Database">
                      <i class="fas fa-trash"></i>
                  </button>';
        
        $row[] = $actionCell;
        
        $output['data'][] = $row;
    }
    
    // Kirim response JSON
    sendJsonResponse($output);
    
} catch (Exception $e) {
    // Log error
    error_log("Error in user_fingerprint_database_ajax: " . $e->getMessage());
    
    // Kirim error response untuk DataTables
    $output = [
        'draw' => isset($_GET['draw']) ? intval($_GET['draw']) : 1,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Terjadi kesalahan saat memuat data'
    ];
    
    sendJsonResponse($output, 500);
}
?>