<?php
// ======================================================
// export_cek_user_csv.php
// Export Data Pengecekan IP 4 Menu Mikrotik ke Format CSV (Excel Compatible)
// ======================================================

ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    http_response_code(401);
    die("Unauthorized");
}

require_once '../../../koneksi.php';
require_once '../../../config.php';
require_once '../../../routeros_api.class.php';

function cleanIp($raw) {
    if (!$raw) return '';
    $raw = trim($raw);
    return explode('/', $raw)[0];
}

function cleanCommentText($comment) {
    if (!$comment) return '';
    return trim(preg_replace('/^:+/i', '', trim($comment)));
}

$categoryFilter = $_GET['category'] ?? 'NEED_ATTENTION';
$subnetFilter   = $_GET['subnet'] ?? 'ALL';
$searchFilter   = trim($_GET['search'] ?? '');

$rawQueues = [];
$rawArp = [];
$rawFw = [];
$rawDhcp = [];
$rawAddresses = [];

$ipDataMap = [];
$detectedSubnets = [];

try {
    $API = new RouterosAPI();
    $API->timeout = 10;
    
    if ($API->connect($mt_ip, $mt_user, $mt_pass)) {
        $rawQueues    = $API->comm('/queue/simple/print') ?: [];
        $rawArp       = $API->comm('/ip/arp/print') ?: [];
        $rawFw        = $API->comm('/ip/firewall/address-list/print') ?: [];
        $rawDhcp      = $API->comm('/ip/dhcp-server/lease/print') ?: [];
        $rawAddresses = $API->comm('/ip/address/print') ?: [];
        $API->disconnect();
    }
} catch (Exception $ex) {
    die("Error connecting to Mikrotik: " . $ex->getMessage());
}

// 1. Subnets
foreach ($rawAddresses as $addr) {
    $addressWithCidr = $addr['address'] ?? '';
    $network = $addr['network'] ?? '';
    $disabled = isset($addr['disabled']) && ($addr['disabled'] === 'true' || $addr['disabled'] === true);
    if ($disabled || empty($addressWithCidr)) continue;

    if (strpos($addressWithCidr, '/') !== false) {
        list($ip, $prefix) = explode('/', $addressWithCidr, 2);
        $prefix = (int)$prefix;
        if ($prefix >= 22 && $prefix <= 30 && !empty($network)) {
            $subnetKey = $network . '/' . $prefix;
            if (!isset($detectedSubnets[$subnetKey])) {
                $detectedSubnets[$subnetKey] = [
                    'network' => $network,
                    'prefix'  => $prefix,
                    'interface' => $addr['interface'] ?? '-',
                    'router_ip' => $ip
                ];
            }
        }
    }
}

if (empty($detectedSubnets)) {
    $detectedSubnets['192.168.1.0/24'] = ['network' => '192.168.1.0', 'prefix' => 24, 'interface' => 'bridge1', 'router_ip' => '192.168.1.1'];
    $detectedSubnets['192.168.7.0/24'] = ['network' => '192.168.7.0', 'prefix' => 24, 'interface' => 'bridge1', 'router_ip' => '192.168.7.1'];
}

// 2. Init IP Pool
foreach ($detectedSubnets as $subnetKey => $subInfo) {
    $prefix = $subInfo['prefix'];
    $netLong = ip2long($subInfo['network']);

    if ($netLong !== false && $prefix == 24) {
        for ($i = 1; $i <= 254; $i++) {
            $hostIp = long2ip($netLong + $i);
            if (!isset($ipDataMap[$hostIp])) {
                $ipDataMap[$hostIp] = [
                    'ip' => $hostIp,
                    'subnet' => $subnetKey,
                    'queue' => null,
                    'arp' => null,
                    'firewall' => [],
                    'dhcp' => null
                ];
            }
        }
    }
}

// 3. Simple Queues
foreach ($rawQueues as $q) {
    $rawTarget = $q['target'] ?? ($q['dst-address'] ?? '');
    $ip = cleanIp($rawTarget);
    if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) continue;

    if (!isset($ipDataMap[$ip])) {
        $ipDataMap[$ip] = ['ip' => $ip, 'subnet' => 'Lainnya', 'queue' => null, 'arp' => null, 'firewall' => [], 'dhcp' => null];
    }

    $rawName = trim($q['name'] ?? '');
    $rawComment = trim($q['comment'] ?? '');
    $cleanComm = cleanCommentText($rawComment);
    $hasTag = (!empty($cleanComm) || (!empty($rawName) && $rawName !== $ip && $rawName !== ($ip . '/32') && strtolower($rawName) !== 'default'));

    $ipDataMap[$ip]['queue'] = [
        'name' => $rawName,
        'comment' => $cleanComm,
        'max_limit' => $q['max-limit'] ?? ($q['limit-at'] ?? '-'),
        'has_tag' => $hasTag
    ];
}

// 4. ARP
foreach ($rawArp as $arp) {
    $ip = cleanIp($arp['address'] ?? '');
    if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) continue;

    if (!isset($ipDataMap[$ip])) {
        $ipDataMap[$ip] = ['ip' => $ip, 'subnet' => 'Lainnya', 'queue' => null, 'arp' => null, 'firewall' => [], 'dhcp' => null];
    }

    $rawComment = trim($arp['comment'] ?? '');
    $cleanComm = cleanCommentText($rawComment);

    $ipDataMap[$ip]['arp'] = [
        'mac' => $arp['mac-address'] ?? '-',
        'comment' => $cleanComm,
        'has_tag' => !empty($cleanComm)
    ];
}

// 5. Firewall
foreach ($rawFw as $fw) {
    $ip = cleanIp($fw['address'] ?? '');
    if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) continue;

    if (!isset($ipDataMap[$ip])) {
        $ipDataMap[$ip] = ['ip' => $ip, 'subnet' => 'Lainnya', 'queue' => null, 'arp' => null, 'firewall' => [], 'dhcp' => null];
    }

    $rawComment = trim($fw['comment'] ?? '');
    $cleanComm = cleanCommentText($rawComment);
    $listName = trim($fw['list'] ?? '');

    $ipDataMap[$ip]['firewall'][] = [
        'list' => $listName,
        'comment' => $cleanComm,
        'has_tag' => !empty($cleanComm)
    ];
}

// 6. DHCP
foreach ($rawDhcp as $dhcp) {
    $ip = cleanIp($dhcp['address'] ?? '');
    if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) continue;

    if (!isset($ipDataMap[$ip])) {
        $ipDataMap[$ip] = ['ip' => $ip, 'subnet' => 'Lainnya', 'queue' => null, 'arp' => null, 'firewall' => [], 'dhcp' => null];
    }

    $rawComment = trim($dhcp['comment'] ?? '');
    $cleanComm = cleanCommentText($rawComment);
    $hostName = trim($dhcp['host-name'] ?? '');

    $ipDataMap[$ip]['dhcp'] = [
        'mac' => $dhcp['mac-address'] ?? ($dhcp['active-mac-address'] ?? '-'),
        'hostname' => $hostName,
        'comment' => $cleanComm,
        'status' => $dhcp['status'] ?? '-',
        'has_tag' => (!empty($cleanComm) || !empty($hostName))
    ];
}

// 7. Sort
uksort($ipDataMap, function($a, $b) {
    return ip2long($a) <=> ip2long($b);
});

// Filter & Prepare Export Rows
$exportRows = [];
$no = 1;

foreach ($ipDataMap as $ip => $data) {
    $menus = [];
    $tags = [];

    if ($data['queue'] !== null) {
        $menus[] = 'Queues';
        if ($data['queue']['has_tag']) $tags[] = 'Queues: ' . ($data['queue']['comment'] ?: $data['queue']['name']);
    }
    if ($data['arp'] !== null) {
        $menus[] = 'ARP List';
        if ($data['arp']['has_tag']) $tags[] = 'ARP: ' . $data['arp']['comment'];
    }
    if (!empty($data['firewall'])) {
        $menus[] = 'Firewall List';
        foreach ($data['firewall'] as $fwItem) {
            if ($fwItem['has_tag']) $tags[] = 'Firewall (' . $fwItem['list'] . '): ' . $fwItem['comment'];
        }
    }
    if ($data['dhcp'] !== null) {
        $menus[] = 'DHCP Lease';
        if ($data['dhcp']['has_tag']) {
            $tagVal = $data['dhcp']['comment'] ?: ('Hostname: ' . $data['dhcp']['hostname']);
            $tags[] = 'DHCP: ' . $tagVal;
        }
    }

    $menuCount = count($menus);
    $hasAnyTag = !empty($tags);

    // Classification
    $cat = '';
    $statusText = '';
    $keteranganMenu = '';

    if ($menuCount === 0) {
        $cat = 'UNUSED';
        $statusText = 'IP Kosong / Belum Dipakai';
        $keteranganMenu = 'Belum terdaftar di 4 menu';
    } elseif ($menuCount === 4) {
        if ($hasAnyTag) {
            $cat = 'COMPLETE';
            $statusText = 'Terdaftar Lengkap (4 Menu)';
            $keteranganMenu = 'Terdaftar lengkap di 4 menu';
        } else {
            $cat = 'COMPLETE_NO_TAG';
            $statusText = 'Lengkap tapi Tanpa Tag/Nama';
            $keteranganMenu = 'Terdaftar di 4 menu (Tanpa Keterangan)';
        }
    } else {
        if ($hasAnyTag) {
            $cat = 'INCOMPLETE_WITH_TAG';
            $statusText = "Parsial ({$menuCount}/4 Menu)";
            $keteranganMenu = 'Hanya terdaftar di: ' . implode(', ', $menus);
        } else {
            $cat = 'INCOMPLETE_NO_TAG';
            $statusText = "Parsial & Tanpa Tag ({$menuCount}/4 Menu)";
            $keteranganMenu = 'Hanya terdaftar di: ' . implode(', ', $menus) . ' (Tanpa Nama/Tag)';
        }
    }

    $isNeedAttention = ($cat === 'UNUSED' || $cat === 'INCOMPLETE_NO_TAG' || $cat === 'INCOMPLETE_WITH_TAG' || $cat === 'COMPLETE_NO_TAG');

    // Filter Subnet
    if ($subnetFilter !== 'ALL' && $data['subnet'] !== $subnetFilter) {
        continue;
    }

    // Filter Category
    if ($categoryFilter === 'NEED_ATTENTION') {
        if (!$isNeedAttention) continue;
    } elseif ($categoryFilter !== 'ALL') {
        if ($cat !== $categoryFilter) continue;
    }

    // Filter Search
    if ($searchFilter !== '') {
        $haystack = strtolower($ip . ' ' . $statusText . ' ' . $keteranganMenu . ' ' . implode(' ', $tags) . ' ' . implode(' ', $menus));
        if (strpos($haystack, strtolower($searchFilter)) === false) {
            continue;
        }
    }

    $exportRows[] = [
        $no++,
        $ip,
        $data['subnet'],
        ($data['queue'] !== null ? 'Ada' : 'Tidak'),
        ($data['arp'] !== null ? 'Ada' : 'Tidak'),
        (!empty($data['firewall']) ? 'Ada' : 'Tidak'),
        ($data['dhcp'] !== null ? 'Ada' : 'Tidak'),
        $keteranganMenu,
        $statusText,
        (!empty($tags) ? implode(' | ', $tags) : '-')
    ];
}

$filename = "Laporan_Cek_IP_Mikrotik_" . date("Ymd_His") . ".csv";

header('Content-Type: text/csv; charset=UTF-8');
header("Content-Disposition: attachment; filename=\"$filename\"");
echo "\xEF\xBB\xBF"; // UTF-8 BOM

$output = fopen("php://output", "w");

fputcsv($output, ["LAPORAN PENGECEKAN STATUS IP & KONSISTENSI 4 MENU MIKROTIK"]);
fputcsv($output, ["Tanggal Cetak:", date("d/m/Y H:i:s") . " WIB"]);
fputcsv($output, ["Filter Kategori:", $categoryFilter]);
fputcsv($output, ["Filter Subnet:", $subnetFilter]);
fputcsv($output, ["Total IP Ditampilkan:", count($exportRows)]);
fputcsv($output, []);

fputcsv($output, [
    "No",
    "IP Address",
    "Subnet",
    "Queues",
    "ARP List",
    "Firewall List",
    "DHCP Lease",
    "Keterangan / Terdaftar di Menu Mana Saja",
    "Status Kategori",
    "Tag / Nama Terdeteksi"
]);

foreach ($exportRows as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit;
