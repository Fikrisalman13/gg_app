<?php
// Disable error display to prevent HTML output
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Start output buffering FIRST before any output
// Use nested output buffering to catch everything
ob_start();
ob_start();

// Start session (must be before checking session)
session_start();

// Get form type from request
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

// Map form types to file paths
$formFiles = [
    'pengajuan_perangkat'   => 'form_contents/form_pengajuan_perangkat.php',
    'maintenance_it'        => 'form_contents/form_maintenance_it.php',
    'pengajuan_product_ga'  => 'form_contents/form_pengajuan_product_ga.php',
    'pengajuan_cctv'        => 'form_contents/form_pengajuan_cctv.php',
    'pengajuan_akses_internet' => 'form_contents/form_pengajuan_akses_internet.php',
    'pengajuan_email_account' => 'form_contents/form_pengajuan_email_account.php',
    'grant_revoke_trustee'    => 'form_contents/form_grant_revoke_trustee.php',
    'perubahan_data_database' => 'form_contents/form_perubahan_data_database.php',
];

// Check if form type exists
if (!isset($formFiles[$formType])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Form type not found']);
    exit;
}

// Get the file path
$formFile = $formFiles[$formType];
$formPath = __DIR__ . '/' . $formFile;

// Check if file exists
if (!file_exists($formPath)) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Form file not found']);
    exit;
}

// Output the form content
try {
    // Get and discard any output from session/initialization
    $initialOutput = ob_get_clean();
    
    // Start new buffer for form content
    ob_start();
    
    // Include the form file - all output will be captured
    include $formPath;
    
    // Get the content from buffer
    $content = ob_get_clean();
    
    // Ensure content is valid string
    if ($content === false || $content === null) {
        $content = '';
    }
    
    // Clean any whitespace/newlines before actual content
    $content = trim($content);
    
    // Check if content looks like HTML error page (koneksi.php die() output)
    if (stripos($content, '<!DOCTYPE') !== false || 
        stripos($content, '<html') !== false ||
        stripos($content, 'Array') === 0 ||
        (strlen($content) < 100 && preg_match('/^[A-Z][a-z]+:/', $content))) {
        // This might be an error output from koneksi.php
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'error' => 'Error loading form: Database connection or PHP error detected',
            'debug' => substr($content, 0, 200)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Get and clean outer buffer
    $outerOutput = ob_get_clean();
    
    // Set JSON header and output
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode(['success' => true, 'content' => $content], JSON_UNESCAPED_UNICODE);
    exit;
    
} catch (Throwable $e) {
    // Catch all errors including fatal errors
    // Clean all output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Error loading form: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

