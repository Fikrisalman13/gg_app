<?php
// ======================================================
// Get DHCP Leases by IP
// ======================================================
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

require_once '../../../config.php';
require_once '../../../routeros_api.class.php';

header('Content-Type: application/json');

$ip = $_GET['ip'] ?? '';

try {
    $API = new RouterosAPI();
    
    if (!$API->connect($mt_ip, $mt_user, $mt_pass)) {
        throw new Exception("Koneksi Mikrotik gagal");
    }

    // Jika parameter ip dikirimkan, coba filter exact dulu
    if (!empty($ip)) {
        $leases = $API->comm('/ip/dhcp-server/lease/print', ['?address' => $ip]);

        // Jika tidak ada hasil, ambil semua leases dan cocokkan di server-side sebagai fallback
        if (empty($leases)) {
            $all = $API->comm('/ip/dhcp-server/lease/print');
            $matches = [];
            foreach ($all as $l) {
                $addr = $l['address'] ?? '';
                // normalisasi alamat (hilangkan mask jika ada)
                $addrNorm = explode('/', $addr)[0];
                $ipNorm = explode('/', $ip)[0];
                if (trim($addrNorm) === trim($ipNorm)) {
                    $matches[] = $l;
                }
            }
            $leases = $matches;
        }
    } else {
        // Jika tidak ada ip, kembalikan semua DHCP leases (mandiri)
        $leases = $API->comm('/ip/dhcp-server/lease/print');
    }

    $API->disconnect();

    $result = ['leases' => []];
    if (!empty($leases)) {
        foreach ($leases as $lease) {
            $result['leases'][] = [
                'id' => $lease['.id'] ?? ($lease['id'] ?? ''),
                'address' => $lease['address'] ?? '',
                'hostname' => $lease['host-name'] ?? ($lease['host'] ?? ''),
                'comment' => $lease['comment'] ?? ''
            ];
        }
    }

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
