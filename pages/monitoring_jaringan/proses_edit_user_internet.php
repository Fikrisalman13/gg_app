<?php
// ======================================================
// proses_edit_user_internet.php
// Proses Edit Data User Internet Mikrotik
// ======================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['LAST_ACTIVITY'] = time();

// ======================================================
// 1. VALIDASI LOGIN
// ======================================================
if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    http_response_code(401);
    echo "Unauthorized";
    exit;
}

// ======================================================
// 5. FUNGSI BANTU (HELPERS)
// ======================================================
function convertToMikrotikFormat($speed)
{
    // Jika unlimited, return as-is
    if ($speed === 'unlimited') {
        return $speed;
    }
    
    // Jika sudah dalam format Mikrotik (1M, 2M, dll), return as-is
    if (preg_match('/^\d+[kMG]$/', $speed)) {
        return $speed;
    }
    
    // Jika numeric, konversi ke format Mikrotik
    if (is_numeric($speed)) {
        $bps = (int)$speed;
        
        // Konversi bps ke Kbps dulu
        $kbps = $bps / 1000;
        
        if ($kbps < 1000) {
            return $kbps . 'k';
        }
        
        $mbps = $kbps / 1000;
        
        // Bulatkan ke nilai yang umum
        if ($mbps <= 1.5) {
            return $mbps == 1 ? '1M' : '1.5M';
        }
        if ($mbps <= 2.5) {
            return '2M';
        }
        if ($mbps <= 3.5) {
            return '3M';
        }
        if ($mbps <= 4.5) {
            return '4M';
        }
        if ($mbps <= 5.5) {
            return '5M';
        }
        if ($mbps <= 6.5) {
            return '6M';
        }
        if ($mbps <= 8.5) {
            return '8M';
        }
        if ($mbps <= 12) {
            return $mbps <= 10 ? '10M' : '12M';
        }
        if ($mbps <= 15) {
            return '15M';
        }
        if ($mbps <= 20) {
            return '20M';
        }
        if ($mbps <= 25) {
            return '25M';
        }
        if ($mbps <= 30) {
            return '30M';
        }
        if ($mbps <= 40) {
            return '40M';
        }
        if ($mbps <= 50) {
            return '50M';
        }
        if ($mbps <= 60) {
            return '60M';
        }
        if ($mbps <= 80) {
            return '80M';
        }
        if ($mbps <= 100) {
            return '100M';
        }
        
        // Jika di atas 100Mbps, return dalam Mbps dengan pembulatan
        return round($mbps) . 'M';
    }
    
    return $speed; // Return as-is jika tidak dikenal
}

// ======================================================
// 2. KONEKSI & DEPENDENSI
// ======================================================
require_once '../../koneksi.php';
require_once '../../config.php';
require_once '../../routeros_api.class.php';

// ======================================================
// 2.1. PERMISSION CHECK
// ======================================================
$groupId = $_SESSION['GroupId'];
$menuId = 45; // Menu ID untuk User Internet

$sql = "SELECT CanEdit FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);
$canEdit = false;

if ($stmt === false) {
    die("Kesalahan hak akses: " . print_r(sqlsrv_errors(), true));
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
$canEdit = $row && $row['CanEdit'] == 1;
sqlsrv_free_stmt($stmt);

if (!$canEdit) {
    echo "Anda tidak memiliki hak untuk mengedit data.";
    exit;
}

// ======================================================
// 3. AMBIL DAN VALIDASI INPUT
// ======================================================

$originalName = trim($_POST['original_name'] ?? '');
// Ambil mode input nama
$name_mode = $_POST['name_mode'] ?? 'dropdown';
if ($name_mode === 'dropdown') {
    $name = trim($_POST['name_dropdown'] ?? '');
} else {
    $name = trim($_POST['name_manual'] ?? '');
}
$device_type = trim($_POST['device_type'] ?? '');
$target = trim($_POST['target'] ?? '');
$uploadMax = trim($_POST['upload_max'] ?? '');
$downloadMax = trim($_POST['download_max'] ?? '');

$firewallMode = trim($_POST['firewall_mode'] ?? '');
$currentUser = $_SESSION['UserName'] ?? '';

// Validasi input
if (empty($originalName) || empty($name) || empty($target) || empty($uploadMax) || empty($downloadMax) || empty($device_type)) {
    echo "Semua field wajib diisi.";
    exit;
}

// Validasi format Mikrotik
if (!preg_match('/^(unlimited|\\d+[kMG])$/', $uploadMax) || !preg_match('/^(unlimited|\\d+[kMG])$/', $downloadMax)) {
    echo "Upload/Download Max harus dalam format Mikrotik (contoh: 1M, 512k, unlimited).";
    exit;
}

// ======================================================
// 4. PROSES UPDATE KE MIKROTIK
// ======================================================
try {
    $API = new RouterosAPI();
    if (!$API->connect($mt_ip, $mt_user, $mt_pass)) {
        echo "Gagal koneksi ke Mikrotik.";
        exit;
    }

    // Parsing departemen dan nama dari field name
    $dept = '';
    $nama_lengkap = '';
    if (preg_match('/^(.*?)\s*-\s*(.*?)$/', $name, $matches)) {
        $dept = trim($matches[1]);
        $nama_lengkap = trim($matches[2]);
    } else {
        $nama_lengkap = $name;
    }
    $comment = $dept . ' - ' . $nama_lengkap . ' (' . $device_type . ')';

    // Cek apakah queue dengan nama asli ada
    $existingQueues = $API->comm('/queue/simple/print', [
        '?name' => $originalName
    ]);
    if (empty($existingQueues)) {
        $API->disconnect();
        echo "User tidak ditemukan di Mikrotik.";
        exit;
    }
    $queueId = $existingQueues[0]['.id'];

    // Jika nama berubah, cek apakah nama baru sudah ada (dengan format baru)
    if ($comment !== $originalName) {
        $checkQueues = $API->comm('/queue/simple/print', [
            '?name' => $comment
        ]);
        if (!empty($checkQueues)) {
            $API->disconnect();
            echo "Nama user '{$comment}' sudah digunakan.";
            exit;
        }
    }

    // Update queue dengan format baru
    $updateData = [
        '.id' => $queueId,
        'name' => $comment,
        'target' => $target,
        'max-limit' => $uploadMax . '/' . $downloadMax
    ];
    $API->comm('/queue/simple/set', $updateData);

    // === SET FIREWALL ADDRESS-LIST SESUAI MODE ===
    if ($firewallMode) {
        // Hapus semua address-list user ini dari semua list yang relevan
        $fwLists = [
            'Allow-Internet','Block-Internet','LAN','Mailserver','allow-access-router','allow-ftp-ssh-telnet','allow-smb-rdp','dns-allow','dst-allow-spesifik','safe-access','src-allow-spesifik','sumber-flooding','trusted-server'
        ];
        foreach ($fwLists as $fwList) {
            $fwEntries = $API->comm('/ip/firewall/address-list/print', [
                '?address' => $target,
                '?list' => $fwList
            ]);
            foreach ($fwEntries as $entry) {
                if (isset($entry['.id'])) {
                    $API->comm('/ip/firewall/address-list/remove', ['.id' => $entry['.id']]);
                }
            }
        }
        // Tambahkan ke list sesuai pilihan
        $API->comm('/ip/firewall/address-list/add', [
            'list' => $firewallMode,
            'address' => $target,
            'comment' => $comment
        ]);
    }

    // === UPDATE DHCP LEASE COMMENT ===
    $dhcp = $API->comm('/ip/dhcp-server/lease/print', ['?address' => $target]);
    if (!empty($dhcp)) {
        $lease_id = $dhcp[0]['.id'] ?? ($dhcp[0]['id'] ?? null);
        if ($lease_id) {
            $API->comm('/ip/dhcp-server/lease/set', [
                '.id' => $lease_id,
                'comment' => $comment
            ]);
        }
    }

    // === UPDATE ARP COMMENT ===
    $arpData = $API->comm('/ip/arp/print', ['?address' => $target]);
    if (!empty($arpData)) {
        $arpId = $arpData[0]['.id'] ?? null;
        if ($arpId) {
            $API->comm('/ip/arp/set', [
                '.id' => $arpId,
                'comment' => $comment
            ]);
        }
    }

    $API->disconnect();

    // Update ke database user_internet
    $sql = "UPDATE dbo.user_internet SET nama = ?, upload_max_limit = ?, download_max_limit = ?, firewall_mode = ?, update_by = ?, updated_at = GETDATE(), device_type = ? WHERE target_ip = ?";
    $params = [$comment, $uploadMax, $downloadMax, $firewallMode, $currentUser, $device_type, $target];
    sqlsrv_query($conn, $sql, $params);

    echo "SUCCESS";

} catch (Exception $e) {
    echo "Terjadi kesalahan: " . $e->getMessage();
}
?>
