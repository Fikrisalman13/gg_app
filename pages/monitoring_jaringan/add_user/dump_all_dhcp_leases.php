<?php
// Simple debug endpoint: dump all DHCP leases as JSON and log the raw response
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../../../config.php';
require_once '../../../routeros_api.class.php';

header('Content-Type: application/json');

try {
    $API = new RouterosAPI();
    if (!$API->connect($mt_ip, $mt_user, $mt_pass)) {
        throw new Exception('Koneksi Mikrotik gagal');
    }

    $leases = $API->comm('/ip/dhcp-server/lease/print');
    $API->disconnect();

    // log
    try {
        $log = date('Y-m-d H:i:s') . ' | dump_all called | count=' . count($leases) . ' | raw=' . json_encode($leases) . PHP_EOL;
        file_put_contents(__DIR__ . '/dump_all_dhcp_log.txt', $log, FILE_APPEND | LOCK_EX);
    } catch (Exception $e) {}

    $out = ['count' => count($leases), 'leases' => []];
    foreach ($leases as $l) {
        $out['leases'][] = [
            'id' => $l['.id'] ?? ($l['id'] ?? ''),
            'address' => $l['address'] ?? '',
            'hostname' => $l['host-name'] ?? ($l['host'] ?? ''),
            'comment' => $l['comment'] ?? ''
        ];
    }

    echo json_encode($out);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

?>
