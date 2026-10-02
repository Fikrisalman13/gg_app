<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

ob_start();
ob_start();
session_start();

$formType = $_GET['form_type'] ?? '';

if (!isset($_SESSION['UserName'])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$formFiles = [
    'serah_terima_aplikasi' => 'form_contents/form_serah_terima.php',
];

if (!isset($formFiles[$formType])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Form type not found']);
    exit;
}

$formPath = __DIR__ . '/' . $formFiles[$formType];
if (!file_exists($formPath)) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Form file not found']);
    exit;
}

try {
    ob_get_clean();
    ob_start();
    include $formPath;
    $content = trim((string) ob_get_clean());
    ob_get_clean();

    header('Content-Type: application/json; charset=utf-8');
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
        'error' => 'Error loading form: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
