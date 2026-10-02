<?php
// Disable error display to prevent HTML output
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Start output buffering FIRST before any output
ob_start();
ob_start();

// Start session (must be before checking session)
session_start();

// Check if user is logged in
if (!isset($_SESSION['UserName'])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get ticket from request
$ticket = $_GET['ticket'] ?? '';

if (empty($ticket)) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ticket is required']);
    exit;
}

// Include koneksi
$koneksiPath = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
if (file_exists($koneksiPath)) {
    require_once $koneksiPath;
} else {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection file not found']);
    exit;
}

// Check database connection
if (!isset($conn) || $conn === false) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Query to get data by ticket (support CCTV and Internet Access prefix)
try {
    $isCCTV = stripos($ticket,'CCTV-') === 0;
    $isInternet = stripos($ticket,'INET-') === 0;
    $isEmail = stripos($ticket,'EMAIL-') === 0;
    $isGrantRevoke = stripos($ticket,'GRT-') === 0;
    $isDatabase = stripos($ticket, 'DB-') === 0;
    $row = null;
    if ($isCCTV) {
        $sql = "SELECT TOP 1 * FROM Form_Pengajuan_CCTV WHERE ticket = ?";
    } elseif ($isInternet) {
        $sql = "SELECT TOP 1 * FROM Form_Pengajuan_Akses_Internet WHERE ticket = ?";
    } elseif ($isEmail) {
        $sql = "SELECT TOP 1 * FROM Form_Pengajuan_Email_Account WHERE ticket = ?";
    } elseif ($isGrantRevoke) {
        $sql = "SELECT TOP 1 * FROM Form_Grant_Revoke_Trustee WHERE ticket = ?";
    } elseif ($isDatabase) {
        $sql = "SELECT TOP 1 * FROM Form_Perubahan_Data_Database WHERE ticket = ?";
    } else {
        $sql = "SELECT TOP 1 * FROM Form_Pengajuan_Barang WHERE ticket = ?";
    }
    $stmt = sqlsrv_query($conn, $sql, [$ticket]);
    if ($stmt === false) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database query failed: ' . print_r(sqlsrv_errors(), true)]);
        exit;
    }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    if (!$row) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Data not found']);
        exit;
    }
    // Normalize date fields
    $dateFields = ['tgl_pengajuan','tanggal_1','tanggal_2','akses_temporary_from','akses_temporary_to','bandwidth_temporary_from','bandwidth_temporary_to'];
    foreach ($dateFields as $df) {
        if (isset($row[$df]) && $row[$df] instanceof DateTime) {
            $row[$df] = $row[$df]->format('Y-m-d');
        } elseif (isset($row[$df]) && is_string($row[$df]) && preg_match('/^(\d{2})-(\d{2})-(\d{4})$/',$row[$df],$m)) {
            $row[$df] = $m[3].'-'.$m[2].'-'.$m[1];
        }
    }
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>true,'data'=>$row,'isCCTV'=>$isCCTV,'isInternet'=>$isInternet,'isEmail'=>$isEmail,'isGrantRevoke'=>$isGrantRevoke,'isDatabase'=>$isDatabase], JSON_UNESCAPED_UNICODE);
    exit;
    
} catch (Throwable $e) {
    // Clean all output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Error loading data: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

