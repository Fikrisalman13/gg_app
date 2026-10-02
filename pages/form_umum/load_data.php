<?php
// Disable error display to prevent HTML output
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Start output buffering
ob_start();
ob_start();

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

if (!isset($conn) || $conn === false) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

try {
    $isClosing = stripos($ticket, 'CLS-') === 0;
    $isIKP     = (stripos($ticket, 'IKP-') === 0 || stripos($ticket, 'IKS-') === 0);
    $isIPC     = stripos($ticket, 'IPC-') === 0;
    
    $row = null;
    if ($isClosing) {
        $sql = "SELECT TOP 1 * FROM Form_Umum_Buka_Tanggal_Closingan WHERE ticket = ?";
    } elseif ($isIKP) {
        $sql = "SELECT TOP 1 * FROM Form_Umum_Izin_Keluar_Pabrik WHERE ticket = ?";
    } elseif ($isIPC) {
        $sql = "SELECT TOP 1 * FROM Form_Umum_Izin_Pulang_Cepat WHERE ticket = ?";
    } else {
        // Jenis tiket tidak dikenali
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Jenis tiket tidak dikenali']);
        exit;
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
    
    // Normalize date fields (DateTime objects from sqlsrv, or DD-MM-YYYY strings)
    $dateFields = ['tgl_pengajuan', 'buka_tgl', 'tanggal'];
    foreach ($dateFields as $df) {
        if (isset($row[$df]) && $row[$df] instanceof DateTime) {
            $row[$df] = $row[$df]->format('Y-m-d');
        } elseif (isset($row[$df]) && is_string($row[$df]) && preg_match('/^(\d{2})-(\d{2})-(\d{4})$/',$row[$df],$m)) {
            $row[$df] = $m[3].'-'.$m[2].'-'.$m[1];
        }
    }
    
    $employees = [];
    if ($isIKP) {
        $detailStmt = sqlsrv_query($conn, "SELECT nik, nama_pemohon, departemen, bagian, jabatan, no_hp FROM Form_Umum_Izin_Keluar_Pabrik_Detail WHERE ticket = ? ORDER BY id", [$ticket]);
        if ($detailStmt) {
            while ($employee = sqlsrv_fetch_array($detailStmt, SQLSRV_FETCH_ASSOC)) $employees[] = $employee;
            sqlsrv_free_stmt($detailStmt);
        }
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>true, 'data'=>$row, 'employees'=>$employees, 'isClosing'=>$isClosing, 'isIKP'=>$isIKP, 'isIPC'=>$isIPC], JSON_UNESCAPED_UNICODE);
    exit;
    
} catch (Throwable $e) {
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
