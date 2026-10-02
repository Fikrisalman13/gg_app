<?php
session_start();
$_SESSION['UserName'] = 'IT4';
$_SESSION['GroupId'] = 2; // operator
try {
    ob_start();
    require 'get_summary_data.php';
    echo "SUMMARY: " . ob_get_clean() . "\n";
} catch (Exception $e) {
    echo "SUMMARY ERROR: " . $e->getMessage() . "\n";
}

try {
    ob_start();
    $_POST = [
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'search' => ['value' => ''],
        'order' => [['column' => 0, 'dir' => 'asc']]
    ];
    require 'get_planning_data.php';
    echo "PLANNING: " . substr(ob_get_clean(), 0, 100) . "...\n";
} catch (Exception $e) {
    echo "PLANNING ERROR: " . $e->getMessage() . "\n";
}
echo "OK\n";
