<?php
require 'vendor/autoload.php';
use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\Int64;

ini_set('max_execution_time', 0);
set_time_limit(0);
date_default_timezone_set('Asia/Jakarta');

session_start();
$userSession = $_SESSION['username'] ?? 'Unknown';

// ==== SETUP OUTPUT STREAM ====
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

function sendProgress($percent, $message) {
    echo "data: " . json_encode(['percent' => $percent, 'message' => $message], JSON_UNESCAPED_UNICODE) . "\n\n";
    ob_flush();
    flush();
}

function sendError($msg) {
    sendProgress(100, "❌ $msg");
    exit;
}

$client = new Client("mongodb://doitAdmin:doitIntegra.sys@192.168.7.5:27017");
$dbName = "api_sum";

$collections = explode(',', $_POST['collections'] ?? '');
$updateMode  = $_POST['update_mode'] ?? 'baleno';
$keywords    = $_POST['keyword'] ?? [];
$fieldsInput = $_POST ?? [];

if (empty($collections) || empty($keywords)) {
    sendError("Data tidak lengkap: collections atau keyword kosong.");
}

$total = count($keywords);
$logItems = [];
$countUpdated = 0;
$countMatched = 0;

sendProgress(0, "🔍 Mulai memproses $total data...");

foreach ($keywords as $i => $keyword) {
    $keyword = trim($keyword);
    if ($keyword === '') continue;

    $uid = md5($keyword);
    $updateFields = [];

    $selectedFields = $_POST['update_field'][$uid] ?? [];
    if (empty($selectedFields)) continue;

    foreach ($selectedFields as $fieldName) {
        $updateFields[$fieldName] = $fieldsInput[$fieldName][$uid] ?? '';
    }

    if (empty($updateFields)) continue;

    foreach ($collections as $collName) {
        if (!$collName) continue;

        $coll = $client->$dbName->$collName;
        $filter = [$updateMode => new MongoDB\BSON\Regex("\\b" . preg_quote($keyword, '/') . "\\b", 'i')];
        $existingDoc = $coll->findOne($filter);

        if (!$existingDoc) continue;

        if (isset($updateFields['balehdid'])) {
            $updateFields['balehdid'] = new Int64($updateFields['balehdid']);
        }

        $result = $coll->updateMany($filter, ['$set' => $updateFields]);
        $countMatched += $result->getMatchedCount();
        $countUpdated += $result->getModifiedCount();

        if ($result->getModifiedCount() > 0) {
            $updatedFrom = [
                'batchno'        => $existingDoc['batchno'] ?? '',
                'baleno'         => $existingDoc['baleno'] ?? '',
                'balehdid'       => $existingDoc['balehdid'] ?? '',
                'packingno'      => $existingDoc['packingno'] ?? '',
                'transferinitem' => $existingDoc['transferinitem'] ?? '',
                'transferoutitem'=> $existingDoc['transferoutitem'] ?? '',
                'transferinbale' => $existingDoc['transferinbale'] ?? '',
                'transferoutbale'=> $existingDoc['transferoutbale'] ?? '',
                'status'         => $existingDoc['status'] ?? ''
            ];

            $updatedTo = $updatedFrom;
            foreach ($updateFields as $k => $v) {
                $updatedTo[$k] = $v;
            }

            $logItems[] = [
                'collection' => $collName,
                'identifier' => $keyword,
                'updated_from' => $updatedFrom,
                'updated_to' => $updatedTo,
                'timestamp' => date('Y-m-d H:i:s'),
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_session' => $userSession,
                'description' => "Perubahan data via web ($collName / mode: $updateMode)"
            ];
        }
    }

    $percent = intval((($i + 1) / $total) * 80);
    sendProgress($percent, "⚙️ Memproses data: $keyword ($i/$total)");
}

// ==== Simpan log ====
if (!empty($logItems)) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) mkdir($logDir, 0755, true);
    $logFile = $logDir . '/update_data_' . date('Y-m-d') . '.json';
    foreach ($logItems as $log) {
        file_put_contents($logFile, json_encode($log, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

if ($countUpdated > 0) {
    sendProgress(100, "✅ $countUpdated data berhasil diupdate 🎉");
} else {
    sendProgress(100, "⚠️ Tidak ada data yang berubah, semua sudah sesuai.");
}

flush();
?>
