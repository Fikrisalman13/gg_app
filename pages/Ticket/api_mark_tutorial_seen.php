<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userName = $_SESSION['UserName'];
$configFile = __DIR__ . '/../../config/tutorial_data.json';

// Ensure config file exists
if (!file_exists($configFile)) {
    file_put_contents($configFile, json_encode(['universal_zoom' => []]));
}

// Read current data
$jsonContent = file_get_contents($configFile);
$data = json_decode($jsonContent, true);

if (!is_array($data)) {
    $data = ['universal_zoom' => []];
}
if (!isset($data['universal_zoom']) || !is_array($data['universal_zoom'])) {
    $data['universal_zoom'] = [];
}

// Add user if not already present
if (!in_array($userName, $data['universal_zoom'])) {
    $data['universal_zoom'][] = $userName;
    
    // Save back to file
    if (file_put_contents($configFile, json_encode($data, JSON_PRETTY_PRINT))) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to write config']);
    }
} else {
    echo json_encode(['success' => true, 'message' => 'Already marked']);
}
?>
