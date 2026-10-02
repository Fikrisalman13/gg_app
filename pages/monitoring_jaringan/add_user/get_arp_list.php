<?php
// ======================================================
// Get ARP List (HTML options) - AJAX
// ======================================================
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

require_once '../../../config.php';
require_once '../../../routeros_api.class.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $API = new RouterosAPI();
    if (!$API->connect($mt_ip, $mt_user, $mt_pass)) {
        throw new Exception('Koneksi Mikrotik gagal');
    }

    // Ambil DHCP leases untuk membantu identifikasi hostname
    $dhcpList = [];
    $leases = $API->comm('/ip/dhcp-server/lease/print');
    foreach ($leases as $d) {
        if (!empty($d['address'])) {
            $dhcpList[$d['address']] = $d;
        }
    }

    $arpAll = $API->comm('/ip/arp/print');

    $out = "";
    foreach ($arpAll as $arp) {
        $comment = $arp['comment'] ?? '';
        $address = $arp['address'] ?? '';
        $dynamic = $arp['dynamic'] ?? null;
        $flags = $arp['flags'] ?? '';

        if (empty($comment) && $address) {
            $isDhcpLike = false;
            if (!empty($dynamic) && strtolower($dynamic) !== 'false') {
                $isDhcpLike = true;
            }
            if (isset($dhcpList[$address])) {
                $isDhcpLike = true;
            }
            if (is_string($flags) && (strpos($flags, 'D') !== false || strpos($flags, 'C') !== false)) {
                $isDhcpLike = true;
            }

            if ($isDhcpLike) {
                $id = $arp['.id'] ?? ($arp['id'] ?? '');
                if (!$id) continue;
                $mac = $arp['mac-address'] ?? 'Unknown';
                $iface = $arp['interface'] ?? '';
                $hostname = $dhcpList[$address]['host-name'] ?? '';
                $status = $arp['status'] ?? '';

                $label = "$address - $mac";
                if (!empty($comment)) $label .= " ($comment)";
                elseif (!empty($hostname)) $label .= " ($hostname)";
                else $label .= " ($status)";

                $search = strtolower("$address $mac $comment $hostname $iface $status");

                $out .= '<option value="' . htmlspecialchars($id) . '" data-search="' . htmlspecialchars($search) . '" data-ip="' . htmlspecialchars($address) . '">' . htmlspecialchars($label) . "</option>\n";
            }
        }
    }

    $API->disconnect();

    echo $out;

} catch (Exception $e) {
    http_response_code(500);
    echo 'Error: ' . htmlspecialchars($e->getMessage());
}

?>
