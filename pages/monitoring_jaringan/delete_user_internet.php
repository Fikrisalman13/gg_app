<?php
// ======================================================
// delete_user_internet.php
// Menghapus Data User Internet dari Mikrotik & Database
// ======================================================
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['LAST_ACTIVITY'] = time();

header('Content-Type: application/json; charset=utf-8');

// ======================================================
// 1. VALIDASI LOGIN
// ======================================================
if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu!']);
    exit;
}

// ======================================================
// 2. KONEKSI & DEPENDENSI
// ======================================================
require_once '../../koneksi.php';
require_once '../../config.php';
require_once '../../routeros_api.class.php';

// ======================================================
// 3. CEK HAK AKSES (CanDelete)
// ======================================================
$groupId = $_SESSION['GroupId'] ?? 0;
$menuId = 140; // Menu ID untuk User Internet

$sql = "SELECT CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);
$canDelete = false;

if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    $canDelete = ($row['CanDelete'] == 1);
    sqlsrv_free_stmt($stmt);
}

if (!$canDelete) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak akses untuk menghapus data user internet.']);
    exit;
}

// ======================================================
// 4. AMBIL INPUT POST
// ======================================================
$name = trim($_POST['name'] ?? '');
$target_ip = trim($_POST['target_ip'] ?? '');

if (empty($name) && empty($target_ip)) {
    echo json_encode(['success' => false, 'message' => 'Nama user atau Target IP wajib disediakan.']);
    exit;
}

$ipClean = explode('/', $target_ip)[0];

$deletedItems = [
    'queue' => 0,
    'firewall' => 0,
    'dhcp' => 0,
    'arp' => 0,
    'db' => 0
];

// ======================================================
// 5. PROSES HAPUS DI MIKROTIK
// ======================================================
try {
    $API = new RouterosAPI();
    if ($API->connect($mt_ip, $mt_user, $mt_pass)) {

        // Jika IP belum ada tapi nama ada, cari target IP dari queue
        if (empty($ipClean) && !empty($name)) {
            $qFind = $API->comm('/queue/simple/print', ['?name' => $name]);
            if (!empty($qFind) && !empty($qFind[0]['target'])) {
                $ipClean = explode('/', $qFind[0]['target'])[0];
            }
        }

        // 1️⃣ HAPUS DARI SIMPLE QUEUE
        if (!empty($name)) {
            $queues = $API->comm('/queue/simple/print', ['?name' => $name]);
            foreach ($queues as $q) {
                if (!empty($q['.id'])) {
                    $API->comm('/queue/simple/remove', ['.id' => $q['.id']]);
                    $deletedItems['queue']++;
                }
            }
        }
        if (!empty($ipClean)) {
            $queuesByTarget = $API->comm('/queue/simple/print', ['?target' => $ipClean . '/32']);
            foreach ($queuesByTarget as $q) {
                if (!empty($q['.id'])) {
                    $API->comm('/queue/simple/remove', ['.id' => $q['.id']]);
                    $deletedItems['queue']++;
                }
            }
        }

        // 2️⃣ HAPUS DARI FIREWALL ADDRESS-LIST
        if (!empty($ipClean)) {
            $fwByIp = $API->comm('/ip/firewall/address-list/print', ['?address' => $ipClean]);
            foreach ($fwByIp as $fw) {
                if (!empty($fw['.id'])) {
                    $API->comm('/ip/firewall/address-list/remove', ['.id' => $fw['.id']]);
                    $deletedItems['firewall']++;
                }
            }
        }
        if (!empty($name)) {
            $fwByComment = $API->comm('/ip/firewall/address-list/print', ['?comment' => $name]);
            foreach ($fwByComment as $fw) {
                if (!empty($fw['.id'])) {
                    $API->comm('/ip/firewall/address-list/remove', ['.id' => $fw['.id']]);
                    $deletedItems['firewall']++;
                }
            }
        }

        // 3️⃣ HAPUS DARI DHCP SERVER LEASES
        if (!empty($ipClean)) {
            $leasesByIp = $API->comm('/ip/dhcp-server/lease/print', ['?address' => $ipClean]);
            foreach ($leasesByIp as $l) {
                if (!empty($l['.id'])) {
                    $API->comm('/ip/dhcp-server/lease/remove', ['.id' => $l['.id']]);
                    $deletedItems['dhcp']++;
                }
            }
        }
        if (!empty($name)) {
            $leasesByComment = $API->comm('/ip/dhcp-server/lease/print', ['?comment' => $name]);
            foreach ($leasesByComment as $l) {
                if (!empty($l['.id'])) {
                    $API->comm('/ip/dhcp-server/lease/remove', ['.id' => $l['.id']]);
                    $deletedItems['dhcp']++;
                }
            }
        }

        // 4️⃣ HAPUS DARI ARP TABLE
        if (!empty($ipClean)) {
            $arpsByIp = $API->comm('/ip/arp/print', ['?address' => $ipClean]);
            foreach ($arpsByIp as $a) {
                if (!empty($a['.id'])) {
                    $API->comm('/ip/arp/remove', ['.id' => $a['.id']]);
                    $deletedItems['arp']++;
                }
            }
        }
        if (!empty($name)) {
            $arpsByComment = $API->comm('/ip/arp/print', ['?comment' => $name]);
            foreach ($arpsByComment as $a) {
                if (!empty($a['.id'])) {
                    $API->comm('/ip/arp/remove', ['.id' => $a['.id']]);
                    $deletedItems['arp']++;
                }
            }
        }

        $API->disconnect();
    } else {
        throw new Exception("Gagal terhubung ke Mikrotik.");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan saat menghapus data di Mikrotik: ' . $e->getMessage()
    ]);
    exit;
}

// ======================================================
// 6. PROSES HAPUS DI DATABASE (dbo.user_internet)
// ======================================================
try {
    if (!empty($ipClean) && !empty($name)) {
        $delSql = "DELETE FROM dbo.user_internet WHERE target_ip = ? OR nama = ?";
        $delStmt = sqlsrv_query($conn, $delSql, [$ipClean, $name]);
    } elseif (!empty($ipClean)) {
        $delSql = "DELETE FROM dbo.user_internet WHERE target_ip = ?";
        $delStmt = sqlsrv_query($conn, $delSql, [$ipClean]);
    } else {
        $delSql = "DELETE FROM dbo.user_internet WHERE nama = ?";
        $delStmt = sqlsrv_query($conn, $delSql, [$name]);
    }

    if ($delStmt) {
        $rowsAffected = sqlsrv_rows_affected($delStmt);
        $deletedItems['db'] = ($rowsAffected !== false && $rowsAffected > 0) ? $rowsAffected : 0;
        sqlsrv_free_stmt($delStmt);
    }
} catch (Exception $dbEx) {
    // Abaikan jika error db, Mikrotik sudah terhapus
}

// ======================================================
// 7. RESPONSE BERHASIL
// ======================================================
$displayTarget = $ipClean ? " (IP: {$ipClean})" : "";
echo json_encode([
    'success' => true,
    'message' => "User '{$name}'{$displayTarget} berhasil dihapus dari Mikrotik dan database.",
    'details' => $deletedItems
]);
?>
