<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$formType = $_GET['form_type'] ?? '';

if (!isset($_SESSION['UserName'])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$formFiles = [
    'cash_on_delivery'          => 'form_cod/form_cod.php',
    'pengajuan_cash_on_delivery'=> 'form_cod/form_cod.php',
];

if (!isset($formFiles[$formType])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Form type tidak ditemukan']);
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
    echo json_encode(['success' => false, 'error' => 'File form belum tersedia: ' . $formFile]);
    exit;
}

try {
    ob_start();
    include $formPath;
    $content = ob_get_clean();
    
    if ($content === false || $content === null) {
        $content = '';
    }
    
    $content = trim($content);
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
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
