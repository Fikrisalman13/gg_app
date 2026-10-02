<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['UserId'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$groupId = (int)($_SESSION['GroupId'] ?? 0);
if ($groupId !== 1) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$userId = (string)$_SESSION['UserId'];
$enabled = isset($_POST['enabled']) ? (int)$_POST['enabled'] : null;
if ($enabled === null || ($enabled !== 0 && $enabled !== 1)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid payload']);
    exit;
}

$configPath = __DIR__ . '/config/sidebar_prefs.json';

$data = [];
if (file_exists($configPath)) {
    $raw = file_get_contents($configPath);
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
}

if (!isset($data['sidebar_search_enabled']) || !is_array($data['sidebar_search_enabled'])) {
    $data['sidebar_search_enabled'] = [];
}

if (!isset($data['sidebar_search_hint_shown']) || !is_array($data['sidebar_search_hint_shown'])) {
    $data['sidebar_search_hint_shown'] = [];
}

$showHint = false;
$hintAlreadyShown = isset($data['sidebar_search_hint_shown'][$userId]) && (int)$data['sidebar_search_hint_shown'][$userId] === 1;
if ($enabled === 0 && !$hintAlreadyShown) {
    $showHint = true;
    $data['sidebar_search_hint_shown'][$userId] = 1;
}

$data['sidebar_search_enabled'][$userId] = $enabled;

$dir = dirname($configPath);
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$fp = fopen($configPath, 'c+');
if (!$fp) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Cannot write config']);
    exit;
}

if (!flock($fp, LOCK_EX)) {
    fclose($fp);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Cannot lock config']);
    exit;
}

ftruncate($fp, 0);
rewind($fp);

$written = fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

if ($written === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Write failed']);
    exit;
}

echo json_encode(['ok' => true, 'enabled' => $enabled, 'show_hint' => $showHint]);
