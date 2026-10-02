<?php
// ======================================================
// Proses Add User - LENGKAP (Final Queue + Comment)
// ======================================================
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
    header("Location: /gg_app/login.php");
    exit;
}

require_once '../../../koneksi.php';
require_once '../../../config.php';
require_once '../../../routeros_api.class.php';

// ======================================================
// AMBIL INPUT FORM
// ======================================================
$arp_id          = $_POST['arp_id'] ?? '';
$employee_select = trim($_POST['employee_select'] ?? '');
$device_type     = trim($_POST['device_type'] ?? '');
$bw_up           = $_POST['bw_up'] ?? 'unlimited';
$bw_down         = $_POST['bw_down'] ?? 'unlimited';
$fw_rule         = $_POST['fw_rule'] ?? '';

if (!$arp_id || !$employee_select || !$device_type) {
    $_SESSION['error'] = "Pilih IP, Karyawan, dan Jenis Device!";
    header("Location: add_user.php");
    exit;
}

// ======================================================
// AMBIL DATA KARYAWAN DARI DATABASE
// ======================================================
$dept = '';
$bagian = '';
$nama_lengkap = '';

// employee_select now contains NIK (more stable). Ambil nama & dept berdasarkan NIK
$sql = "SELECT m_emp.nama_lengkap, m_bag.bagian, m_dept.dept 
        FROM dbo.m_emp 
        LEFT JOIN dbo.m_bag ON m_emp.id_bag = m_bag.id_bag 
        LEFT JOIN dbo.m_dept ON m_emp.id_dept = m_dept.id_dept 
        WHERE m_emp.nik = ? AND m_emp.aktif = 1";

$stmt = sqlsrv_query($conn, $sql, [$employee_select]);
if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    $bagian = $row['bagian'] ?? '';
    $dept   = $row['dept'] ?? '';
    $nama_lengkap = $row['nama_lengkap'] ?? '';
}

// ======================================================
// FORMAT COMMENT: Departemen - Nama (Device Type)
// ======================================================
$comment = trim(
    ($dept ? $dept : ($bagian ?: 'Tidak Ada')) .
    ' - ' . $nama_lengkap .
    ' (' . $device_type . ')'
);

// ======================================================
// VALIDASI BANDWIDTH
// ======================================================
$allowed_bw = ['unlimited','64k','128k','256k','384k','512k','768k','1M','2M','4M','5M','10M','20M','50M','100M'];
if (!in_array($bw_up, $allowed_bw)) $bw_up = 'unlimited';
if (!in_array($bw_down, $allowed_bw)) $bw_down = 'unlimited';

$bw_final = "{$bw_up}/{$bw_down}";


// ======================================================
// KONEKSI MIKROTIK
// ======================================================
$API = new RouterosAPI();

try {
    if (!$API->connect($mt_ip, $mt_user, $mt_pass)) {
        throw new Exception("Tidak dapat terhubung ke Mikrotik.");
    }

    // ======================================================
    // 1️⃣ AMBIL DATA ARP BERDASARKAN .id
    // ======================================================
    $arp = $API->comm('/ip/arp/print', ['?.id' => $arp_id]);
    if (empty($arp)) {
        throw new Exception("Data ARP tidak ditemukan.");
    }

    $ip = $arp[0]['address'] ?? null;
    if (!$ip) {
        throw new Exception("IP ARP tidak valid.");
    }

    $target = $ip . "/32";


    // ======================================================
    // 2️⃣ CEK DUPLIKAT QUEUE
    // ======================================================
    $queueExist = $API->comm('/queue/simple/print', ['?target' => $target]);
    if (!empty($queueExist)) {
        throw new Exception("Queue untuk IP {$ip} sudah ada!");
    }


    // ======================================================
    // 3️⃣ SET ARP STATIC + COMMENT (jika belum)
    // ======================================================
    $arpData = $API->comm('/ip/arp/print', ['?.id' => $arp_id]);
    if (!empty($arpData)) {
        $currentArp = $arpData[0];
        
        // Jika belum static, buat static
        if (($currentArp['status'] ?? '') !== 'static') {
            $API->comm('/ip/arp/make-static', ['.id' => $arp_id]);
        }
    }

    $API->comm('/ip/arp/set', [
        '.id' => $arp_id,
        'comment' => $comment
    ]);


    // ======================================================
    // 4️⃣ DHCP LEASE FIX — CREATE IF NOT EXIST + MAKE STATIC
    // ======================================================
    $dhcp = $API->comm('/ip/dhcp-server/lease/print', ['?address' => $ip]);
    $lease_id = null;

    if (empty($dhcp)) {
        // ➕ Tambahkan lease baru jika belum ada
        $API->comm('/ip/dhcp-server/lease/add', [
            'address' => $ip,
            'comment' => $comment
        ]);

        // Ambil ulang ID lease
        $dhcp2 = $API->comm('/ip/dhcp-server/lease/print', ['?address' => $ip]);
        if (!empty($dhcp2)) {
            $lease_id = $dhcp2[0]['.id'] ?? ($dhcp2[0]['id'] ?? null);
        }
    } else {
        $lease_id = $dhcp[0]['.id'] ?? ($dhcp[0]['id'] ?? null);
    }

    if ($lease_id) {
        // Cek apakah sudah static
        $leaseData = $API->comm('/ip/dhcp-server/lease/print', ['?.id' => $lease_id]);
        if (!empty($leaseData)) {
            $currentLease = $leaseData[0];
            
            // Jika belum static, buat static
            if (($currentLease['status'] ?? '') !== 'static') {
                $API->comm('/ip/dhcp-server/lease/make-static', ['.id' => $lease_id]);
            }
        }

        // Update comment
        $API->comm('/ip/dhcp-server/lease/set', [
            '.id' => $lease_id,
            'comment' => $comment
        ]);
    }


    // ======================================================
    // 5️⃣ ADD SIMPLE QUEUE dengan nama = comment format
    // ======================================================
    $queueName = $comment; 

    $API->comm('/queue/simple/add', [
        'name'      => $queueName,
        'target'    => $target,
        'max-limit' => $bw_final
    ]);


    // ======================================================
    // 6️⃣ FIREWALL ADDRESS-LIST (jika dipilih)
    // ======================================================
    $fw_msg = "";
    
    if (!empty($fw_rule)) {
        $fwExists = $API->comm('/ip/firewall/address-list/print', [
            '?address' => $ip,
            '?list'    => $fw_rule
        ]);

        if (empty($fwExists)) {
            $API->comm('/ip/firewall/address-list/add', [
                'list' => $fw_rule,
                'address' => $ip,
                'comment' => $comment
            ]);
            $fw_msg = "✓ Firewall list '{$fw_rule}' ditambahkan.";
        } else {
            // Update comment jika sudah ada
            $fw_id = $fwExists[0]['.id'] ?? ($fwExists[0]['id'] ?? null);
            if ($fw_id) {
                $API->comm('/ip/firewall/address-list/set', [
                    '.id' => $fw_id,
                    'comment' => $comment
                ]);
            }
            $fw_msg = "✓ Firewall list '{$fw_rule}' comment diupdate.";
        }
    }


    // ======================================================
    // 7️⃣ SUCCESS MESSAGE
    // ======================================================

    // ======================================================
    // 8️⃣ SIMPAN KE DATABASE user_internet
    // ======================================================
    $currentUser = $_SESSION['UserName'] ?? '';
    $creation_time = date('Y-m-d H:i:s');
    $mode_koneksi = $device_type;

    // Cek apakah sudah ada user dengan IP ini
    $cekSql = "SELECT id FROM dbo.user_internet WHERE target_ip = ?";
    $cekStmt = sqlsrv_query($conn, $cekSql, [$ip]);
    if ($cekStmt && ($cekRow = sqlsrv_fetch_array($cekStmt, SQLSRV_FETCH_ASSOC))) {
        // UPDATE
        $updateSql = "UPDATE dbo.user_internet SET nama = ?, upload_max_limit = ?, download_max_limit = ?, mode_koneksi = ?, creation_time = ?, update_by = ?, updated_at = GETDATE() WHERE id = ?";
        $uParams = [
            $comment,
            $bw_up,
            $bw_down,
            $mode_koneksi,
            $creation_time,
            $currentUser,
            $cekRow['id']
        ];
        sqlsrv_query($conn, $updateSql, $uParams);
    } else {
        // INSERT
        $insertSql = "INSERT INTO dbo.user_internet (nama, target_ip, upload_max_limit, download_max_limit, mode_koneksi, creation_time, update_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE(), GETDATE())";
        $iParams = [
            $comment,
            $ip,
            $bw_up,
            $bw_down,
            $mode_koneksi,
            $creation_time,
            $currentUser
        ];
        sqlsrv_query($conn, $insertSql, $iParams);
    }

    $API->disconnect();

    $_SESSION['success'] = "
        <div class='alert-heading'>
            <h4><i class='icon fas fa-check'></i> Sukses!</h4>
        </div>
        <p><strong>User berhasil didaftarkan:</strong></p>
        <ul>
            <li><strong>Comment/Nama:</strong> {$comment}</li>
            <li><strong>IP Address:</strong> {$ip}</li>
            <li><strong>Queue Name:</strong> {$queueName}</li>
            <li><strong>Bandwidth:</strong> {$bw_final}</li>
            <li><strong>Karyawan:</strong> {$dept} - {$nama_lengkap}</li>
            <li><strong>Device:</strong> {$device_type}</li>
            <li>{$fw_msg}</li>
        </ul>
    ";

    header("Location: add_user.php");
    exit;

} catch (Exception $ex) {

    try { $API->disconnect(); } catch (Throwable $t) {}

    $_SESSION['error'] = "❌ Gagal: " . $ex->getMessage();
    header("Location: add_user.php");
    exit;
}

?>
