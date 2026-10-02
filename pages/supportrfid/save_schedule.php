<?php
header('Content-Type: application/json');
$file = __DIR__ . '/schedule.json';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) throw new Exception('Data tidak valid.');
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}