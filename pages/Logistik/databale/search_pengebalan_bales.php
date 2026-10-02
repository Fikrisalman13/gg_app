<?php
session_start();
ob_start();
header('Content-Type: application/json; charset=utf-8');

function search_bales_json(array $payload, int $httpCode = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($httpCode);
    echo json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

require_once '../../../koneksi.php';
require_once '../../../koneksi3.php';
require_once __DIR__ . '/databale_helpers.php';

if (!isset($_SESSION['UserName'])) {
    search_bales_json(['status' => 'error', 'message' => 'Session habis. Silakan login kembali.', 'rows' => []], 401);
}
if (!$conn || !$conn3) {
    search_bales_json(['status' => 'error', 'message' => 'Koneksi database gagal.', 'rows' => []], 500);
}

$wrhsid = trim((string)($_GET['wrhsid'] ?? ''));
$search = trim((string)($_GET['q'] ?? ''));
if ($wrhsid === '') {
    search_bales_json(['status' => 'success', 'rows' => []]);
}

try {
    $uploadedSet = dbale_load_uploaded_balenmbr_set($conn);
    $uploadedBaleGroupMap = dbale_load_upload_group_map($conn);
    $rows = [];
    $sourceRows = dbale_load_pengebalan_bale_candidates($conn3, $wrhsid, $search, 500);
    foreach ($sourceRows as $row) {
        $balenmbr = (string)($row['balenmbr'] ?? '');
        $balehdid = (string)($row['balehdid'] ?? '');
        if ($balenmbr === '' || isset($uploadedSet[dbale_normalize_key($balenmbr)]) || isset($uploadedBaleGroupMap[$balehdid])) {
            continue;
        }

        $date = '-';
        $rawDate = $row['baledate'] ?? null;
        if ($rawDate instanceof DateTimeInterface) {
            $date = $rawDate->format('d/m/Y');
        } elseif (!empty($rawDate)) {
            $time = strtotime((string)$rawDate);
            $date = $time ? date('d/m/Y', $time) : '-';
        }

        $rows[] = [
            'balehdid' => $balehdid,
            'balenmbr' => $balenmbr,
            'refnmbr' => (string)($row['refnmbr'] ?? ''),
            'baledate' => $date,
        ];
        if (count($rows) >= 10) {
            break;
        }
    }
} catch (Throwable $error) {
    error_log('Pengebalan bale search failed: ' . $error->getMessage());
    search_bales_json(['status' => 'error', 'message' => 'Query data pengebalan gagal.', 'rows' => []], 500);
}

search_bales_json(['status' => 'success', 'rows' => $rows]);
