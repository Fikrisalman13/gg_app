<?php
header('Content-Type: application/json');
$file = __DIR__ . '/schedule.json';

if (isset($_GET['clear'])) {
    if (file_exists($file)) unlink($file);
    echo json_encode(['ok'=>true]);
    exit;
}
if (isset($_GET['get'])) {
    if (!file_exists($file)) {
        echo json_encode(['ok'=>false, 'data'=>null]);
        exit;
    }
    $json = json_decode(file_get_contents($file), true);
    echo json_encode(['ok'=>true, 'data'=>$json]);
    exit;
}
echo json_encode(['ok'=>false,'error'=>'Permintaan tidak valid.']);