<?php
// Disable error display to prevent HTML output
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Start output buffering
ob_start();
ob_start();

session_start();

$formType = $_GET['form_type'] ?? '';

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

// Map form types to file paths - easily expandable in the future
$formFiles = [
    'buka_tanggal_closingan'  => 'form_contents/form_buka_tanggal_closingan.php',
    'izin_keluar_pabrik'      => 'form_contents/form_izin_keluar_pabrik.php',
    'izin_pulang_cepat'       => 'form_contents/form_izin_pulang_cepat.php',
];

if (!isset($formFiles[$formType])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Form type not found']);
    exit;
}

$formFile = $formFiles[$formType];
$formPath = __DIR__ . '/' . $formFile;

if (!file_exists($formPath)) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Form file not found']);
    exit;
}

try {
    $initialOutput = ob_get_clean();
    
    ob_start();
    include $formPath;
    $content = ob_get_clean();
    
    if ($content === false || $content === null) {
        $content = '';
    }
    
    $content = trim($content);
    
    $outerOutput = ob_get_clean();
    
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode(['success' => true, 'content' => $content], JSON_UNESCAPED_UNICODE);
    exit;
    
} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Error loading form: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
