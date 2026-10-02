<?php
// ======================================================
// cek_user.php
// Halaman untuk Cek Status IP & List IP Tidak Terpakai / Belum Lengkap di 4 Menu Mikrotik:
// 1. Simple Queues
// 2. ARP List
// 3. Firewall Address List
// 4. DHCP Server Leases
// ======================================================

ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['LAST_ACTIVITY'] = time();

// 1. TIMEZONE & LOGIN CHECK
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header("Location: /gg_app/login.php");
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

// 2. REQUIRE FILES & DATABASE
require_once '../../../koneksi.php';

// ======================================================
// 3. IDENTITAS MENU & PERMISSION CHECK (HAK AKSES)
// ======================================================
$groupId = $_SESSION['GroupId'] ?? null;
$menuId  = 1326; // Menu ID untuk Cek User (Monitoring Jaringan)

// Cek apakah MenuId terdaftar di database (SMMenu)
$sqlMenu = "SELECT MenuId, MenuName FROM dbo.SMMenu WHERE MenuId = ? OR MenuUrl LIKE '%cek_user%'";
$stmtMenu = sqlsrv_query($conn, $sqlMenu, [$menuId]);
$menuName = "Cek User";
if ($stmtMenu && ($rowMenu = sqlsrv_fetch_array($stmtMenu, SQLSRV_FETCH_ASSOC))) {
    $menuId   = $rowMenu['MenuId'];
    $menuName = $rowMenu['MenuName'] ?? "Cek User";
}
if ($stmtMenu) sqlsrv_free_stmt($stmtMenu);

// Pengecekan Hak Akses dari SMGroupTrustee
$canView = $canAdd = $canEdit = $canDelete = false;

if ($groupId !== null) {
    $sqlTrustee = "SELECT CanView, CanAdd, CanEdit, CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $paramsTrustee = [$groupId, $menuId];
    $stmtTrustee = sqlsrv_query($conn, $sqlTrustee, $paramsTrustee);

    if ($stmtTrustee === false) {
        die("Kesalahan hak akses: " . print_r(sqlsrv_errors(), true));
    }

    if ($rowTrustee = sqlsrv_fetch_array($stmtTrustee, SQLSRV_FETCH_ASSOC)) {
        $canView   = ($rowTrustee['CanView'] == 1);
        $canAdd    = (($rowTrustee['CanAdd'] ?? 0) == 1);
        $canEdit   = ($rowTrustee['CanEdit'] == 1);
        $canDelete = ($rowTrustee['CanDelete'] == 1);
    } else {
        // Fallback jika belum diset di trustee: Administrator (GroupId 1) otomatis diizinkan
        if ($groupId == 1) {
            $canView = $canAdd = $canEdit = $canDelete = true;
        }
    }
    sqlsrv_free_stmt($stmtTrustee);
}

if (!$canView) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: /gg_app/index.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

// 4. REQUIRE MIKROTIK & LAYOUT
require_once '../../../config.php';
require_once '../../../routeros_api.class.php';

include '../../../includes/header.php';
include '../../../includes/sidebar.php';

// 3. HELPERS
function e($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cleanIp($raw) {
    if (!$raw) return '';
    $raw = trim($raw);
    return explode('/', $raw)[0];
}

function cleanCommentText($comment) {
    if (!$comment) return '';
    // Hapus tanda ::: di awal jika ada
    return trim(preg_replace('/^:+/i', '', trim($comment)));
}

function ipToLongSafe($ip) {
    $long = ip2long($ip);
    return ($long !== false) ? sprintf('%u', $long) : 0;
}

// 4. FETCH DATA FROM MIKROTIK
$connected = false;
$errorMessage = null;

$rawQueues = [];
$rawArp = [];
$rawFw = [];
$rawDhcp = [];
$rawAddresses = [];

$ipDataMap = []; // Key: IP Address
$detectedSubnets = []; // Subnet list

try {
    $API = new RouterosAPI();
    // Set timeout to prevent hanging
    $API->timeout = 10;
    
    if ($API->connect($mt_ip, $mt_user, $mt_pass)) {
        $connected = true;

        // Ambil data dari 4 menu
        $rawQueues    = $API->comm('/queue/simple/print') ?: [];
        $rawArp       = $API->comm('/ip/arp/print') ?: [];
        $rawFw        = $API->comm('/ip/firewall/address-list/print') ?: [];
        $rawDhcp      = $API->comm('/ip/dhcp-server/lease/print') ?: [];
        $rawAddresses = $API->comm('/ip/address/print') ?: [];

        $API->disconnect();
    } else {
        $errorMessage = "Gagal terhubung ke Mikrotik ($mt_ip). Pastikan API Mikrotik aktif di port 8728.";
    }
} catch (Exception $ex) {
    $errorMessage = "Error Mikrotik: " . $ex->getMessage();
}

if ($connected) {
    // -------------------------------------------------------------
    // 4.1. PROSES SUB-NET DARI /ip/address
    // -------------------------------------------------------------
    foreach ($rawAddresses as $addr) {
        $addressWithCidr = $addr['address'] ?? '';
        $network = $addr['network'] ?? '';
        $disabled = isset($addr['disabled']) && ($addr['disabled'] === 'true' || $addr['disabled'] === true);

        if ($disabled || empty($addressWithCidr)) continue;

        if (strpos($addressWithCidr, '/') !== false) {
            list($ip, $prefix) = explode('/', $addressWithCidr, 2);
            $prefix = (int)$prefix;

            // Kita proses subnet /24 yang umum digunakan (atau /23-/30)
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

    // Default subnets jika tidak ada yang terdeteksi dari /ip/address
    if (empty($detectedSubnets)) {
        $detectedSubnets['192.168.1.0/24'] = ['network' => '192.168.1.0', 'prefix' => 24, 'interface' => 'bridge1', 'router_ip' => '192.168.1.1'];
        $detectedSubnets['192.168.7.0/24'] = ['network' => '192.168.7.0', 'prefix' => 24, 'interface' => 'bridge1', 'router_ip' => '192.168.7.1'];
    }

    // -------------------------------------------------------------
    // 4.2. INISIALISASI POOL IP DARI SUBNET (Host 1 - 254 untuk /24)
    // -------------------------------------------------------------
    foreach ($detectedSubnets as $subnetKey => $subInfo) {
        $prefix = $subInfo['prefix'];
        $netLong = ip2long($subInfo['network']);

        if ($netLong !== false && $prefix == 24) {
            // Untuk /24: host .1 sampai .254
            for ($i = 1; $i <= 254; $i++) {
                $hostIp = long2ip($netLong + $i);
                if (!isset($ipDataMap[$hostIp])) {
                    $ipDataMap[$hostIp] = [
                        'ip' => $hostIp,
                        'subnet' => $subnetKey,
                        'is_router' => ($hostIp === $subInfo['router_ip']),
                        'queue' => null,
                        'arp' => null,
                        'firewall' => [],
                        'dhcp' => null
                    ];
                }
            }
        }
    }

    // -------------------------------------------------------------
    // 4.3. PEMETAAN DATA 1: SIMPLE QUEUES
    // -------------------------------------------------------------
    foreach ($rawQueues as $q) {
        $rawTarget = $q['target'] ?? ($q['dst-address'] ?? '');
        $ip = cleanIp($rawTarget);
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) continue;

        if (!isset($ipDataMap[$ip])) {
            $ipDataMap[$ip] = [
                'ip' => $ip,
                'subnet' => 'Lainnya',
                'is_router' => false,
                'queue' => null,
                'arp' => null,
                'firewall' => [],
                'dhcp' => null
            ];
        }

        $rawName = trim($q['name'] ?? '');
        $rawComment = trim($q['comment'] ?? '');
        $cleanComm = cleanCommentText($rawComment);

        // Cek apakah memiliki tag / nama yang bermakna
        $hasTag = false;
        if (!empty($cleanComm)) {
            $hasTag = true;
        } elseif (!empty($rawName) && $rawName !== $ip && $rawName !== ($ip . '/32') && strtolower($rawName) !== 'default') {
            $hasTag = true;
        }

        $maxLimit = $q['max-limit'] ?? ($q['limit-at'] ?? '-');

        $ipDataMap[$ip]['queue'] = [
            'id' => $q['.id'] ?? '',
            'name' => $rawName,
            'comment' => $cleanComm,
            'max_limit' => $maxLimit,
            'disabled' => ($q['disabled'] ?? 'false') === 'true',
            'has_tag' => $hasTag
        ];
    }

    // -------------------------------------------------------------
    // 4.4. PEMETAAN DATA 2: ARP LIST
    // -------------------------------------------------------------
    foreach ($rawArp as $arp) {
        $ip = cleanIp($arp['address'] ?? '');
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) continue;

        if (!isset($ipDataMap[$ip])) {
            $ipDataMap[$ip] = [
                'ip' => $ip,
                'subnet' => 'Lainnya',
                'is_router' => false,
                'queue' => null,
                'arp' => null,
                'firewall' => [],
                'dhcp' => null
            ];
        }

        $rawComment = trim($arp['comment'] ?? '');
        $cleanComm = cleanCommentText($rawComment);
        $hasTag = !empty($cleanComm);

        $ipDataMap[$ip]['arp'] = [
            'id' => $arp['.id'] ?? '',
            'mac' => $arp['mac-address'] ?? '-',
            'interface' => $arp['interface'] ?? '-',
            'comment' => $cleanComm,
            'dynamic' => ($arp['dynamic'] ?? 'false') === 'true',
            'complete' => ($arp['complete'] ?? 'true') === 'true',
            'disabled' => ($arp['disabled'] ?? 'false') === 'true',
            'has_tag' => $hasTag
        ];
    }

    // -------------------------------------------------------------
    // 4.5. PEMETAAN DATA 3: FIREWALL ADDRESS LIST
    // -------------------------------------------------------------
    foreach ($rawFw as $fw) {
        $rawAddr = $fw['address'] ?? '';
        $ip = cleanIp($rawAddr);
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) continue;

        if (!isset($ipDataMap[$ip])) {
            $ipDataMap[$ip] = [
                'ip' => $ip,
                'subnet' => 'Lainnya',
                'is_router' => false,
                'queue' => null,
                'arp' => null,
                'firewall' => [],
                'dhcp' => null
            ];
        }

        $rawComment = trim($fw['comment'] ?? '');
        $cleanComm = cleanCommentText($rawComment);
        $listName = trim($fw['list'] ?? '');
        $hasTag = !empty($cleanComm);

        $ipDataMap[$ip]['firewall'][] = [
            'id' => $fw['.id'] ?? '',
            'list' => $listName,
            'comment' => $cleanComm,
            'creation_time' => $fw['creation-time'] ?? '-',
            'disabled' => ($fw['disabled'] ?? 'false') === 'true',
            'has_tag' => $hasTag
        ];
    }

    // -------------------------------------------------------------
    // 4.6. PEMETAAN DATA 4: DHCP SERVER LEASES
    // -------------------------------------------------------------
    foreach ($rawDhcp as $dhcp) {
        $ip = cleanIp($dhcp['address'] ?? '');
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) continue;

        if (!isset($ipDataMap[$ip])) {
            $ipDataMap[$ip] = [
                'ip' => $ip,
                'subnet' => 'Lainnya',
                'is_router' => false,
                'queue' => null,
                'arp' => null,
                'firewall' => [],
                'dhcp' => null
            ];
        }

        $rawComment = trim($dhcp['comment'] ?? '');
        $cleanComm = cleanCommentText($rawComment);
        $hostName = trim($dhcp['host-name'] ?? '');
        $hasTag = (!empty($cleanComm) || !empty($hostName));

        $ipDataMap[$ip]['dhcp'] = [
            'id' => $dhcp['.id'] ?? '',
            'mac' => $dhcp['mac-address'] ?? ($dhcp['active-mac-address'] ?? '-'),
            'server' => $dhcp['server'] ?? '-',
            'hostname' => $hostName,
            'comment' => $cleanComm,
            'status' => $dhcp['status'] ?? '-',
            'dynamic' => ($dhcp['dynamic'] ?? 'false') === 'true',
            'disabled' => ($dhcp['disabled'] ?? 'false') === 'true',
            'has_tag' => $hasTag
        ];
    }

    // -------------------------------------------------------------
    // 4.7. ANALISIS STATUS, MENU TERDAFTAR & TAG UNTUK SETIAP IP
    // -------------------------------------------------------------
    $summary = [
        'total_ip' => 0,
        'unused' => 0,          // 0 dari 4 menu
        'incomplete_no_tag' => 0,// 1-3 menu tanpa tag
        'incomplete_with_tag' => 0, // 1-3 menu ada tag
        'complete_no_tag' => 0,  // 4 menu tanpa tag
        'complete' => 0         // 4 menu lengkap dengan tag
    ];

    foreach ($ipDataMap as $ip => &$data) {
        $menus = [];
        $tags = [];

        // 1. Queues
        if ($data['queue'] !== null) {
            $menus[] = 'Queues';
            if ($data['queue']['has_tag']) {
                $tags[] = 'Queues: ' . ($data['queue']['comment'] ?: $data['queue']['name']);
            }
        }

        // 2. ARP List
        if ($data['arp'] !== null) {
            $menus[] = 'ARP List';
            if ($data['arp']['has_tag']) {
                $tags[] = 'ARP: ' . $data['arp']['comment'];
            }
        }

        // 3. Firewall Address List
        if (!empty($data['firewall'])) {
            $menus[] = 'Firewall List';
            foreach ($data['firewall'] as $fwItem) {
                if ($fwItem['has_tag']) {
                    $tags[] = 'Firewall (' . $fwItem['list'] . '): ' . $fwItem['comment'];
                }
            }
        }

        // 4. DHCP Server Leases
        if ($data['dhcp'] !== null) {
            $menus[] = 'DHCP Lease';
            if ($data['dhcp']['has_tag']) {
                $tagVal = $data['dhcp']['comment'] ?: ('Hostname: ' . $data['dhcp']['hostname']);
                $tags[] = 'DHCP: ' . $tagVal;
            }
        }

        $menuCount = count($menus);
        $hasAnyTag = !empty($tags);

        $data['menus_registered'] = $menus;
        $data['menu_count'] = $menuCount;
        $data['tags'] = $tags;
        $data['has_tag'] = $hasAnyTag;

        // Kategori & Status
        if ($menuCount === 0) {
            $data['category'] = 'UNUSED';
            $data['status_label'] = 'IP Kosong / Belum Dipakai';
            $data['status_class'] = 'success';
            $data['keterangan_menu'] = 'Belum terdaftar di 4 menu';
            $summary['unused']++;
        } elseif ($menuCount === 4) {
            if ($hasAnyTag) {
                $data['category'] = 'COMPLETE';
                $data['status_label'] = 'Terdaftar Lengkap (4 Menu)';
                $data['status_class'] = 'primary';
                $data['keterangan_menu'] = 'Terdaftar lengkap di 4 menu';
                $summary['complete']++;
            } else {
                $data['category'] = 'COMPLETE_NO_TAG';
                $data['status_label'] = 'Lengkap tapi Tanpa Tag/Nama';
                $data['status_class'] = 'warning';
                $data['keterangan_menu'] = 'Terdaftar di 4 menu (Tanpa Keterangan)';
                $summary['complete_no_tag']++;
            }
        } else {
            // Parsial (1 - 3 menu)
            if ($hasAnyTag) {
                $data['category'] = 'INCOMPLETE_WITH_TAG';
                $data['status_label'] = "Parsial ({$menuCount}/4 Menu)";
                $data['status_class'] = 'info';
                $data['keterangan_menu'] = 'Hanya terdaftar di: ' . implode(', ', $menus);
                $summary['incomplete_with_tag']++;
            } else {
                $data['category'] = 'INCOMPLETE_NO_TAG';
                $data['status_label'] = "Parsial & Tanpa Tag ({$menuCount}/4 Menu)";
                $data['status_class'] = 'danger';
                $data['keterangan_menu'] = 'Hanya terdaftar di: ' . implode(', ', $menus) . ' (Tanpa Nama/Tag)';
                $summary['incomplete_no_tag']++;
            }
        }

        $summary['total_ip']++;
    }
    unset($data);

    // Sort IP list secara numerik (ip2long)
    uksort($ipDataMap, function($a, $b) {
        return ip2long($a) <=> ip2long($b);
    });
}
?>

<!-- ======================================================
     CUSTOM STYLING
====================================================== -->
<style>
.badge-status {
    font-size: 0.85rem;
    padding: 0.35em 0.6em;
    font-weight: 600;
}
.badge-menu-yes {
    background-color: #28a745;
    color: #fff;
}
.badge-menu-no {
    background-color: #e9ecef;
    color: #6c757d;
    border: 1px dashed #ced4da;
}
.card-counter {
    box-shadow: 0 4px 8px rgba(0,0,0,0.05);
    border-radius: 8px;
    transition: all 0.3s ease;
    cursor: pointer;
}
.card-counter:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.1);
}
.filter-box {
    background: #fdfdfd;
    border: 1px solid #e3e6f0;
    border-radius: 8px;
    padding: 18px;
    margin-bottom: 20px;
}
.ip-font {
    font-family: 'Consolas', 'Courier New', monospace;
    font-weight: 700;
    font-size: 0.95rem;
}
.table td, .table th {
    vertical-align: middle !important;
}
.tag-text {
    font-size: 0.85rem;
    color: #333;
    background-color: #f1f3f5;
    padding: 2px 6px;
    border-radius: 4px;
    display: inline-block;
    margin-top: 3px;
}
/* Freeze / Sticky Table Header */
.table-scroll-container {
    max-height: 68vh;
    overflow-y: auto;
    overflow-x: auto;
    position: relative;
    border-bottom: 1px solid #dee2e6;
}
#ipCheckTable thead th {
    position: sticky !important;
    top: 0 !important;
    z-index: 20 !important;
    background-color: #eaedf2 !important;
    color: #343a40 !important;
    border-top: none !important;
    border-bottom: 2px solid #ced4da !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
}
</style>

<!-- ======================================================
     CONTENT WRAPPER
====================================================== -->
<div class="content-wrapper">
    <!-- Header Content -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0 font-weight-bold text-dark">
                        <i class="fas fa-search-location text-<?= htmlspecialchars($themeColor); ?> mr-2"></i>Cek User & IP Tidak Terpakai
                        <span class="badge badge-light border text-muted ml-2" style="font-size: 0.72rem; vertical-align: middle;" title="Menu ID Database: <?= htmlspecialchars($menuId); ?>">
                            <i class="fas fa-hashtag mr-1"></i>Menu ID: <?= htmlspecialchars($menuId); ?>
                        </span>
                    </h1>
                    <p class="text-muted mb-0 small">
                        Pengecekan konsistensi 4 Menu Mikrotik: <b>Queues</b>, <b>ARP List</b>, <b>Firewall Address List</b>, dan <b>DHCP Leases</b>.
                    </p>
                </div>
                <div class="col-sm-6 text-right">
                    <button type="button" class="btn btn-outline-<?= htmlspecialchars($themeColor); ?> btn-sm mr-1" onclick="location.reload();">
                        <i class="fas fa-sync-alt mr-1"></i> Refresh Data
                    </button>
                    <button type="button" id="btnExportCsv" class="btn btn-success btn-sm mr-1">
                        <i class="fas fa-file-excel mr-1"></i> Export Excel/CSV
                    </button>
                    <?php if ($canAdd): ?>
                        <a href="/gg_app/pages/monitoring_jaringan/add_user/add_user.php" class="btn btn-<?= htmlspecialchars($themeColor); ?> btn-sm">
                            <i class="fas fa-user-plus mr-1"></i> Tambah User Baru
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <section class="content">
        <div class="container-fluid">

            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <h5><i class="icon fas fa-ban"></i> Terjadi Kesalahan!</h5>
                    <?= e($errorMessage); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- STATISTIC COUNTER CARDS -->
            <div class="row">
                <!-- IP Kosong / Tersedia -->
                <div class="col-lg-3 col-md-6 col-12">
                    <div class="card card-counter bg-gradient-success text-white mb-3" onclick="filterByQuick('UNUSED')">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase mb-1" style="font-size: 0.8rem; letter-spacing: 0.5px;">IP Kosong / Bebas</h6>
                                    <h2 class="font-weight-bold mb-0"><?= number_format($summary['unused']); ?></h2>
                                    <small class="text-white-50">0 dari 4 Menu (Siap Pakai)</small>
                                </div>
                                <i class="fas fa-check-double fa-3x" style="opacity: 0.35;"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- IP Parsial / Tanpa Tag -->
                <div class="col-lg-3 col-md-6 col-12">
                    <div class="card card-counter bg-gradient-danger text-white mb-3" onclick="filterByQuick('INCOMPLETE_NO_TAG')">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase mb-1" style="font-size: 0.8rem; letter-spacing: 0.5px;">Parsial & Tanpa Tag</h6>
                                    <h2 class="font-weight-bold mb-0"><?= number_format($summary['incomplete_no_tag']); ?></h2>
                                    <small class="text-white-50">1-3 Menu Tanpa Nama/Tag</small>
                                </div>
                                <i class="fas fa-exclamation-triangle fa-3x" style="opacity: 0.35;"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- IP Parsial (Ada Tag) -->
                <div class="col-lg-3 col-md-6 col-12">
                    <div class="card card-counter bg-gradient-info text-white mb-3" onclick="filterByQuick('INCOMPLETE_WITH_TAG')">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase mb-1" style="font-size: 0.8rem; letter-spacing: 0.5px;">Parsial (Ada Tag)</h6>
                                    <h2 class="font-weight-bold mb-0"><?= number_format($summary['incomplete_with_tag']); ?></h2>
                                    <small class="text-white-50">1-3 Menu Belum Lengkap</small>
                                </div>
                                <i class="fas fa-puzzle-piece fa-3x" style="opacity: 0.35;"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- IP Lengkap -->
                <div class="col-lg-3 col-md-6 col-12">
                    <div class="card card-counter bg-gradient-primary text-white mb-3" onclick="filterByQuick('COMPLETE')">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase mb-1" style="font-size: 0.8rem; letter-spacing: 0.5px;">Terdaftar Lengkap</h6>
                                    <h2 class="font-weight-bold mb-0"><?= number_format($summary['complete']); ?></h2>
                                    <small class="text-white-50">Lengkap 4 Menu & Ber-tag</small>
                                </div>
                                <i class="fas fa-users-cog fa-3x" style="opacity: 0.35;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FILTER CARD -->
            <div class="card filter-box">
                <div class="row align-items-end">
                    <!-- Filter Subnet -->
                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="font-weight-bold text-secondary small"><i class="fas fa-network-wired mr-1"></i>Pilih Subnet:</label>
                        <select id="filterSubnet" class="form-control form-control-sm select2" style="width: 100%;">
                            <option value="ALL">-- Semua Subnet (<?= count($detectedSubnets); ?> Subnet) --</option>
                            <?php foreach ($detectedSubnets as $subKey => $subInfo): ?>
                                <option value="<?= htmlspecialchars($subKey); ?>">
                                    Subnet <?= htmlspecialchars($subKey); ?> (<?= htmlspecialchars($subInfo['interface']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Filter Status -->
                    <div class="col-md-4 col-sm-6 mb-2">
                        <label class="font-weight-bold text-secondary small"><i class="fas fa-filter mr-1"></i>Filter Kategori / Status:</label>
                        <select id="filterCategory" class="form-control form-control-sm select2" style="width: 100%;">
                            <option value="NEED_ATTENTION" selected>⭐ IP Tidak Terpakai / Belum Lengkap (Default)</option>
                            <option value="UNUSED">🟢 IP Kosong / Bebas Saja (0 dari 4 Menu)</option>
                            <option value="INCOMPLETE_NO_TAG">🔴 Parsial & Tanpa Tag (1-3 Menu Tanpa Nama)</option>
                            <option value="INCOMPLETE_WITH_TAG">🔵 Parsial tapi Ada Tag (1-3 Menu)</option>
                            <option value="COMPLETE_NO_TAG">🟠 Lengkap 4 Menu (Tanpa Tag)</option>
                            <option value="COMPLETE">🟣 Terdaftar Lengkap (4 Menu)</option>
                            <option value="ALL">⚪ Tampilkan Semua IP (Seluruh Range)</option>
                        </select>
                    </div>

                    <!-- Pencarian Cepat -->
                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="font-weight-bold text-secondary small"><i class="fas fa-search mr-1"></i>Pencarian Kata Kunci:</label>
                        <input type="text" id="customSearch" class="form-control form-control-sm" placeholder="Ketik IP, Nama, Dept, MAC...">
                    </div>

                    <!-- Reset & Actions -->
                    <div class="col-md-2 col-sm-6 mb-2 text-right">
                        <button type="button" id="btnResetFilter" class="btn btn-outline-secondary btn-sm btn-block">
                            <i class="fas fa-undo mr-1"></i> Reset Filter
                        </button>
                    </div>
                </div>
            </div>

            <!-- TABLE CARD -->
            <div class="card card-outline card-<?= htmlspecialchars($themeColor); ?> shadow-sm">
                <div class="card-header border-0 bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="card-title font-weight-bold text-dark mb-0">
                            <i class="fas fa-table mr-2 text-<?= htmlspecialchars($themeColor); ?>"></i>
                            Daftar Pengecekan IP di 4 Menu Mikrotik
                        </h3>
                        <div>
                            <span class="badge badge-light border px-2 py-1 text-muted" id="tableRecordCount">
                                Menampilkan: <b id="displayedCount">0</b> dari <?= number_format($summary['total_ip']); ?> IP
                            </span>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive table-scroll-container">
                        <table id="ipCheckTable" class="table table-hover table-striped table-bordered mb-0" style="width: 100%;">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 50px;" class="text-center">#</th>
                                    <th style="width: 130px;">IP Address</th>
                                    <th style="width: 110px;" class="text-center">Queues</th>
                                    <th style="width: 110px;" class="text-center">ARP List</th>
                                    <th style="width: 110px;" class="text-center">Firewall List</th>
                                    <th style="width: 110px;" class="text-center">DHCP Lease</th>
                                    <th>Keterangan / Terdaftar di Menu Mana Saja</th>
                                    <th style="width: 170px;" class="text-center">Tag / Nama Terdeteksi</th>
                                    <th style="width: 120px;" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                foreach ($ipDataMap as $ip => $row):
                                    $cat = $row['category'];
                                    $sub = $row['subnet'];
                                    $isNeedAttention = ($cat === 'UNUSED' || $cat === 'INCOMPLETE_NO_TAG' || $cat === 'INCOMPLETE_WITH_TAG' || $cat === 'COMPLETE_NO_TAG');
                                ?>
                                    <tr data-category="<?= htmlspecialchars($cat); ?>"
                                        data-subnet="<?= htmlspecialchars($sub); ?>"
                                        data-need-attention="<?= $isNeedAttention ? '1' : '0'; ?>"
                                        data-ip="<?= htmlspecialchars($ip); ?>">
                                        <td class="text-center font-weight-bold text-muted"><?= $no++; ?></td>
                                        
                                        <!-- IP Address -->
                                        <td>
                                            <span class="ip-font <?= ($cat === 'UNUSED' ? 'text-success' : ($cat === 'INCOMPLETE_NO_TAG' ? 'text-danger' : 'text-dark')); ?>">
                                                <?= htmlspecialchars($ip); ?>
                                            </span>
                                            <?php if ($row['is_router']): ?>
                                                <span class="badge badge-secondary ml-1" style="font-size: 0.65rem;">Gateway/Router</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- 1. Queues -->
                                        <td class="text-center">
                                            <?php if ($row['queue'] !== null): ?>
                                                <span class="badge badge-menu-yes badge-pill px-2 py-1" title="Terdaftar di Simple Queues. Limit: <?= e($row['queue']['max_limit']); ?>">
                                                    <i class="fas fa-check mr-1"></i> Ada
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-menu-no badge-pill px-2 py-1">
                                                    <i class="fas fa-times mr-1"></i> Tidak
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- 2. ARP List -->
                                        <td class="text-center">
                                            <?php if ($row['arp'] !== null): ?>
                                                <span class="badge badge-menu-yes badge-pill px-2 py-1" title="MAC: <?= e($row['arp']['mac']); ?> (<?= $row['arp']['dynamic'] ? 'Dynamic' : 'Static'; ?>)">
                                                    <i class="fas fa-check mr-1"></i> Ada
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-menu-no badge-pill px-2 py-1">
                                                    <i class="fas fa-times mr-1"></i> Tidak
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- 3. Firewall Address List -->
                                        <td class="text-center">
                                            <?php if (!empty($row['firewall'])): ?>
                                                <?php
                                                    $listNames = array_map(function($f) { return $f['list']; }, $row['firewall']);
                                                    $listStr = implode(', ', $listNames);
                                                ?>
                                                <span class="badge badge-menu-yes badge-pill px-2 py-1" title="List: <?= e($listStr); ?>">
                                                    <i class="fas fa-check mr-1"></i> Ada
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-menu-no badge-pill px-2 py-1">
                                                    <i class="fas fa-times mr-1"></i> Tidak
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- 4. DHCP Server Leases -->
                                        <td class="text-center">
                                            <?php if ($row['dhcp'] !== null): ?>
                                                <span class="badge badge-menu-yes badge-pill px-2 py-1" title="Status: <?= e($row['dhcp']['status']); ?>, MAC: <?= e($row['dhcp']['mac']); ?>">
                                                    <i class="fas fa-check mr-1"></i> Ada
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-menu-no badge-pill px-2 py-1">
                                                    <i class="fas fa-times mr-1"></i> Tidak
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Keterangan Menu -->
                                        <td>
                                            <?php if ($cat === 'UNUSED'): ?>
                                                <span class="badge badge-success badge-status">
                                                    <i class="fas fa-check-circle mr-1"></i> IP Bebas / Kosong (Tidak terdaftar di 4 menu)
                                                </span>
                                            <?php elseif ($cat === 'COMPLETE'): ?>
                                                <span class="badge badge-primary badge-status">
                                                    <i class="fas fa-shield-alt mr-1"></i> Terdaftar Lengkap (4 Menu)
                                                </span>
                                            <?php elseif ($cat === 'COMPLETE_NO_TAG'): ?>
                                                <span class="badge badge-warning badge-status">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i> Terdaftar di 4 Menu (Tanpa Tag/Keterangan)
                                                </span>
                                            <?php elseif ($cat === 'INCOMPLETE_NO_TAG'): ?>
                                                <span class="badge badge-danger badge-status">
                                                    <i class="fas fa-exclamation-circle mr-1"></i> Hanya di: <?= e(implode(', ', $row['menus_registered'])); ?>
                                                </span>
                                                <span class="badge badge-secondary badge-status ml-1">Tanpa Tag/Nama</span>
                                            <?php else: ?>
                                                <span class="badge badge-info badge-status">
                                                    <i class="fas fa-info-circle mr-1"></i> Hanya di: <?= e(implode(', ', $row['menus_registered'])); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Tag / Nama Terdeteksi -->
                                        <td>
                                            <?php if (!empty($row['tags'])): ?>
                                                <?php foreach ($row['tags'] as $tg): ?>
                                                    <span class="tag-text" title="<?= e($tg); ?>">
                                                        <i class="fas fa-tag text-muted mr-1"></i><?= e($tg); ?>
                                                    </span><br>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="text-muted font-italic small">- Tidak ada tag / nama -</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Aksi -->
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <!-- Detail Info Modal -->
                                                <button type="button" class="btn btn-outline-info btn-detail"
                                                        data-info="<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>"
                                                        title="Lihat Rincian Teknis Mikrotik">
                                                    <i class="fas fa-info-circle"></i>
                                                </button>

                                                <!-- Salin IP -->
                                                <button type="button" class="btn btn-outline-secondary" onclick="copyIp('<?= htmlspecialchars($ip); ?>')" title="Salin IP Address">
                                                    <i class="fas fa-copy"></i>
                                                </button>

                                                <!-- Daftarkan User jika belum lengkap -->
                                                <?php if ($cat !== 'COMPLETE'): ?>
                                                    <a href="/gg_app/pages/monitoring_jaringan/add_user/add_user.php" class="btn btn-outline-success" title="Daftarkan User Baru dengan IP ini">
                                                        <i class="fas fa-user-plus"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<!-- ======================================================
     DETAIL MODAL
====================================================== -->
<div class="modal fade" id="modalDetail" tabindex="-1" role="dialog" aria-labelledby="modalDetailLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor); ?> text-white">
                <h5 class="modal-title" id="modalDetailLabel">
                    <i class="fas fa-microchip mr-2"></i>Rincian Status IP Mikrotik: <span id="modalIpTitle" class="font-weight-bold"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="modalDetailContent">
                    <!-- Dynamic details loaded by JS -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
                <a id="btnModalAddUser" href="/gg_app/pages/monitoring_jaringan/add_user/add_user.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-user-plus mr-1"></i> Buka Menu Add User
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================
     FOOTER & SCRIPTS
====================================================== -->
<?php include '../../../includes/footer.php'; ?>

<!-- DataTables & Plugins -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
$(function() {
    // Inisialisasi Select2
    if ($('.select2').length) {
        $('.select2').select2({
            theme: 'bootstrap4'
        });
    }

    // Inisialisasi DataTable
    const table = $('#ipCheckTable').DataTable({
        "paging": true,
        "pageLength": 50,
        "lengthMenu": [[25, 50, 100, 250, -1], [25, 50, 100, 250, "Semua"]],
        "ordering": false,
        "info": true,
        "autoWidth": false,
        "responsive": true,
        "language": {
            "search": "Cari Cepat:",
            "lengthMenu": "Tampilkan _MENU_ data",
            "zeroRecords": "Tidak ada data IP yang cocok dengan filter",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ IP",
            "infoEmpty": "Tidak ada data tersedia",
            "infoFiltered": "(difilter dari _MAX_ total IP)",
            "paginate": {
                "first": "Awal",
                "last": "Akhir",
                "next": "Berikutnya",
                "previous": "Sebelumnya"
            }
        }
    });

    // Custom filtering function
    $.fn.dataTable.ext.search.push(
        function(settings, data, dataIndex) {
            const rowNode = table.row(dataIndex).node();
            if (!rowNode) return true;

            const category = $(rowNode).attr('data-category');
            const subnet = $(rowNode).attr('data-subnet');
            const isNeedAttention = $(rowNode).attr('data-need-attention') === '1';

            const selectedCategory = $('#filterCategory').val();
            const selectedSubnet = $('#filterSubnet').val();

            // 1. Filter Subnet
            if (selectedSubnet !== 'ALL' && subnet !== selectedSubnet) {
                return false;
            }

            // 2. Filter Category
            if (selectedCategory === 'NEED_ATTENTION') {
                if (!isNeedAttention) return false;
            } else if (selectedCategory !== 'ALL') {
                if (category !== selectedCategory) return false;
            }

            return true;
        }
    );

    // Event listener untuk filter dropdown
    $('#filterCategory, #filterSubnet').on('change', function() {
        table.draw();
        updateCount();
    });

    // Custom search box
    $('#customSearch').on('keyup', function() {
        table.search(this.value).draw();
        updateCount();
    });

    // Reset filter
    $('#btnResetFilter').on('click', function() {
        $('#filterCategory').val('NEED_ATTENTION').trigger('change');
        $('#filterSubnet').val('ALL').trigger('change');
        $('#customSearch').val('');
        table.search('').draw();
        updateCount();
    });

    // Quick filter dari Card Atas
    window.filterByQuick = function(cat) {
        $('#filterCategory').val(cat).trigger('change');
    };

    function updateCount() {
        const info = table.page.info();
        $('#displayedCount').text(info.recordsDisplay.toLocaleString('id-ID'));
    }

    // Trigger initial count & draw
    table.draw();
    updateCount();

    // Modal Detail click handler
    $(document).on('click', '.btn-detail', function() {
        const rawJson = $(this).attr('data-info');
        if (!rawJson) return;

        try {
            const data = JSON.parse(rawJson);
            $('#modalIpTitle').text(data.ip);

            let html = `
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="card card-outline card-info h-100 mb-0">
                            <div class="card-header py-2 font-weight-bold">
                                <i class="fas fa-tachometer-alt mr-1"></i> 1. Simple Queues
                            </div>
                            <div class="card-body p-2 small">
                                ${data.queue ? `
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr><td class="text-muted" style="width: 100px;">Nama Queue:</td><td><b>${data.queue.name || '-'}</b></td></tr>
                                        <tr><td class="text-muted">Comment/Tag:</td><td>${data.queue.comment || '-'}</td></tr>
                                        <tr><td class="text-muted">Max Limit:</td><td><span class="badge badge-info">${data.queue.max_limit || '-'}</span></td></tr>
                                        <tr><td class="text-muted">Status:</td><td>${data.queue.disabled ? '<span class="badge badge-danger">Disabled</span>' : '<span class="badge badge-success">Active</span>'}</td></tr>
                                    </table>
                                ` : '<div class="text-center text-muted py-3"><i class="fas fa-times-circle text-danger mr-1"></i> Tidak terdaftar di Simple Queues</div>'}
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="card card-outline card-warning h-100 mb-0">
                            <div class="card-header py-2 font-weight-bold">
                                <i class="fas fa-network-wired mr-1"></i> 2. ARP List
                            </div>
                            <div class="card-body p-2 small">
                                ${data.arp ? `
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr><td class="text-muted" style="width: 100px;">MAC Address:</td><td><code>${data.arp.mac || '-'}</code></td></tr>
                                        <tr><td class="text-muted">Comment/Tag:</td><td>${data.arp.comment || '-'}</td></tr>
                                        <tr><td class="text-muted">Interface:</td><td>${data.arp.interface || '-'}</td></tr>
                                        <tr><td class="text-muted">Tipe ARP:</td><td>${data.arp.dynamic ? '<span class="badge badge-secondary">Dynamic</span>' : '<span class="badge badge-primary">Static</span>'}</td></tr>
                                    </table>
                                ` : '<div class="text-center text-muted py-3"><i class="fas fa-times-circle text-danger mr-1"></i> Tidak terdaftar di ARP List</div>'}
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="card card-outline card-danger h-100 mb-0">
                            <div class="card-header py-2 font-weight-bold">
                                <i class="fas fa-shield-alt mr-1"></i> 3. Firewall Address List
                            </div>
                            <div class="card-body p-2 small">
                                ${data.firewall && data.firewall.length > 0 ? `
                                    ${data.firewall.map(fw => `
                                        <div class="border-bottom pb-1 mb-1">
                                            <div><b>List:</b> <span class="badge badge-danger">${fw.list}</span></div>
                                            <div><b>Comment:</b> ${fw.comment || '-'}</div>
                                            <div class="text-muted" style="font-size: 0.75rem;">Waktu Dibuat: ${fw.creation_time || '-'}</div>
                                        </div>
                                    `).join('')}
                                ` : '<div class="text-center text-muted py-3"><i class="fas fa-times-circle text-danger mr-1"></i> Tidak terdaftar di Firewall Address List</div>'}
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="card card-outline card-success h-100 mb-0">
                            <div class="card-header py-2 font-weight-bold">
                                <i class="fas fa-server mr-1"></i> 4. DHCP Server Leases
                            </div>
                            <div class="card-body p-2 small">
                                ${data.dhcp ? `
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr><td class="text-muted" style="width: 100px;">Hostname:</td><td><b>${data.dhcp.hostname || '-'}</b></td></tr>
                                        <tr><td class="text-muted">MAC Address:</td><td><code>${data.dhcp.mac || '-'}</code></td></tr>
                                        <tr><td class="text-muted">Comment/Tag:</td><td>${data.dhcp.comment || '-'}</td></tr>
                                        <tr><td class="text-muted">Status:</td><td><span class="badge badge-success">${data.dhcp.status || '-'}</span> (${data.dhcp.dynamic ? 'Dynamic' : 'Static'})</td></tr>
                                    </table>
                                ` : '<div class="text-center text-muted py-3"><i class="fas fa-times-circle text-danger mr-1"></i> Tidak terdaftar di DHCP Leases</div>'}
                            </div>
                        </div>
                    </div>
                </div>
            `;

            $('#modalDetailContent').html(html);
            $('#modalDetail').modal('show');
        } catch (e) {
            console.error(e);
        }
    });

    // Export CSV / Excel Button
    $('#btnExportCsv').on('click', function() {
        const cat = $('#filterCategory').val();
        const sub = $('#filterSubnet').val();
        const search = $('#customSearch').val();

        const url = 'export_cek_user_csv.php?category=' + encodeURIComponent(cat) +
                    '&subnet=' + encodeURIComponent(sub) +
                    '&search=' + encodeURIComponent(search);
        window.open(url, '_blank');
    });

    // Copy IP Function with Toast
    window.copyIp = function(ip) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(ip).then(function() {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'IP ' + ip + ' berhasil disalin!',
                    showConfirmButton: false,
                    timer: 1800
                });
            });
        } else {
            const temp = $('<input>');
            $('body').append(temp);
            temp.val(ip).select();
            document.execCommand('copy');
            temp.remove();
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'IP ' + ip + ' berhasil disalin!',
                showConfirmButton: false,
                timer: 1800
            });
        }
    };
});
</script>
