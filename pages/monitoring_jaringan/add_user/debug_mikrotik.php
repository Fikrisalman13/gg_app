<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../routeros_api.class.php';

echo "Starting Mikrotik debug...\n";
$API = new RouterosAPI();
if ($API->connect($mt_ip, $mt_user, $mt_pass)) {
    echo "CONNECTED\n";
    $leases = $API->comm('/ip/dhcp-server/lease/print');
    echo "DHCP_LEASES: " . count($leases) . "\n";
    foreach ($leases as $l) {
        $id = $l['.id'] ?? ($l['id'] ?? '');
        echo "lease: {$id} | address: " . ($l['address'] ?? '-') . " | host: " . ($l['host-name'] ?? '-') . " | comment: " . ($l['comment'] ?? '-') . "\n";
    }

    $arp = $API->comm('/ip/arp/print');
    echo "ARP: " . count($arp) . "\n";
    foreach ($arp as $a) {
        $id = $a['.id'] ?? ($a['id'] ?? '');
        echo "arp: {$id} | address: " . ($a['address'] ?? '-') . " | flags: " . ($a['flags'] ?? '-') . " | dynamic: " . ($a['dynamic'] ?? '-') . " | comment: " . ($a['comment'] ?? '-') . "\n";
    }

    $API->disconnect();
} else {
    echo "CANNOT CONNECT\n";
}

?>