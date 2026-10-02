<?php
/**
 * API for Centralized Planning Dashboard Settings
 * Manages planning_settings.json on the server.
 */
session_start();

$settingsFile = __DIR__ . '/planning_settings.json';

if (!isset($_SESSION['UserName'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? 'get';

// Default Settings
$defaultSettings = [
    'auto_refresh' => 30000,
    'tv_mode'      => 'scroll',
    'tv_speed'     => 2,
    'col_prefs'    => [
        '0' => true, '1' => true, '2' => false, '3' => true, '4' => true,
        '5' => true, '6' => true, '7' => true, '8' => true, '9' => true,
        '10' => true, '11' => true, '12' => false, '13' => true
    ],
    'col_order'    => [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13],
    'last_updated' => date('Y-m-d H:i:s'),
    'updated_by'   => 'System'
];

if (!file_exists($settingsFile)) {
    file_put_contents($settingsFile, json_encode($defaultSettings, JSON_PRETTY_PRINT));
}

if ($action === 'get') {
    $content = file_get_contents($settingsFile);
    $settings = json_decode($content, true);
    if (!is_array($settings)) {
        $settings = $defaultSettings;
    }
    
    // Ensure all keys exist (schema migration)
    $settings = array_merge($defaultSettings, $settings);

    // Ensure col_prefs is an object for JSON (prevents numeric index list conversion)
    if (isset($settings['col_prefs']) && is_array($settings['col_prefs'])) {
        $settings['col_prefs'] = (object)$settings['col_prefs'];
    }
    
    echo json_encode($settings);
    exit;
}

if ($action === 'save') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        echo json_encode(['error' => 'Invalid input']);
        exit;
    }

    $currentContent = file_get_contents($settingsFile);
    $currentSettings = json_decode($currentContent, true);
    if (!is_array($currentSettings)) {
        $currentSettings = $defaultSettings;
    }
    $currentSettings = array_merge($defaultSettings, $currentSettings);

    $currentUser = $_SESSION['UserName'] ?? '';

    // Merge new settings (Deep merge for col_prefs to prevent array vs object collision)
    $newSettings = $currentSettings;
    foreach ($input as $key => $value) {
        if ($key === 'col_prefs' && is_array($value)) {
            // Ensure col_prefs maintains its keys
            foreach ($value as $idx => $val) {
                $newSettings['col_prefs'][(string)$idx] = $val;
            }
        } else {
            $newSettings[$key] = $value;
        }
    }

    $newSettings['last_updated'] = date('Y-m-d H:i:s');
    $newSettings['updated_by']   = $currentUser;

    // Ensure col_prefs is an object
    if (isset($newSettings['col_prefs']) && is_array($newSettings['col_prefs'])) {
        $newSettings['col_prefs'] = (object)$newSettings['col_prefs'];
    }

    if (file_put_contents($settingsFile, json_encode($newSettings, JSON_PRETTY_PRINT), LOCK_EX)) {
        echo json_encode($newSettings);
    } else {
        echo json_encode(['error' => 'Failed to save settings']);
    }
    exit;
}
