<?php
// sync_user_internet.php
// Sinkronisasi data dari Mikrotik (queues + firewall address-list) ke tabel dbo.user_internet

ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

require_once '../../koneksi.php';
require_once '../../config.php';
require_once '../../routeros_api.class.php';

// Basic permission check: require logged in
if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    http_response_code(401);
    echo json_encode(['success'=>false, 'message'=>'Unauthorized']);
    exit;
}

try {
    $API = new RouterosAPI();
    $connected = $API->connect($mt_ip, $mt_user, $mt_pass);
    if (!$connected) {
        throw new Exception('Gagal koneksi ke Mikrotik');
    }

    $rawQueues = $API->comm('/queue/simple/print');
    $rawFW = $API->comm('/ip/firewall/address-list/print');
    $API->disconnect();

    // Map firewall entries by comment
    $fwMap = [];
    foreach ($rawFW as $f) {
        $comment = isset($f['comment']) ? trim($f['comment']) : '';
        if ($comment === '') continue;
        if (!isset($fwMap[$comment])) $fwMap[$comment] = [];
        $fwMap[$comment][] = $f;
    }

    // Normalisasi waktu RouterOS ke format SQL (Y-m-d H:i:s)
    $normalizeTime = function ($s) {
        if (!isset($s) || $s === null) return null;
        $s = trim($s);
        if ($s === '') return null;

        $ts = strtotime($s);
        if ($ts === false) {
            $ts = strtotime(str_replace('/', ' ', $s));
        }
        if ($ts === false) return null;

        // Jika tahun 1970 → dianggap tidak valid
        $year = (int) date('Y', $ts);
        if ($year < 1971) return null;

        return date('Y-m-d H:i:s', $ts);
    };

    $inserted = 0;
    $updated = 0;

    $currentUser = (!empty($_SESSION['UserName'])) ? $_SESSION['UserName'] : 'SYSTEM';

    foreach ($rawQueues as $q) {

        $name = $q['name'] ?? ($q['target'] ?? null);
        if (!$name) continue;

        $rawTarget = $q['target'] ?? ($q['dst-address'] ?? null);
        if (!$rawTarget) continue;

        $target_ip = explode('/', $rawTarget)[0];

        $upload_max = $q['max-limit'] ?? ($q['limit-at'] ?? '');
        $download_max = '';

        if (strpos($upload_max, '/') !== false) {
            [$upload_max, $download_max] = explode('/', $upload_max, 2);
        } else {
            if (isset($q['rate']) && strpos($q['rate'], '/') !== false) {
                [$upload_max, $download_max] = explode('/', $q['rate'], 2);
            }
        }

        $upload_max = trim($upload_max);
        $download_max = trim($download_max);

        // Cari firewall match
        $mode_koneksi = null;
        $creation_time = null;

        if (isset($fwMap[$name])) {
            $match = null;

            foreach ($fwMap[$name] as $fw) {
                if (isset($fw['address'])) {
                    $fwAddrClean = explode('/', $fw['address'])[0];
                    if ($fwAddrClean === $target_ip) {
                        $match = $fw;
                        break;
                    }
                }
            }

            if (!$match) $match = $fwMap[$name][0];

            if ($match) {
                $mode_koneksi = $match['list'] ?? $match['address-list'] ?? null;
                $creation_time_raw = $match['creation-time'] ?? $match['time'] ?? null;
                $creation_time = $normalizeTime($creation_time_raw);
            }
        }

        // CEK apakah data sudah ada di DB
        $selectSql = "SELECT id FROM dbo.user_internet WHERE target_ip = ?";
        $stmt = sqlsrv_query($conn, $selectSql, [$target_ip]);
        if ($stmt === false) {
            throw new Exception("DB select error: " . print_r(sqlsrv_errors(), true));
        }

        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);

        if ($row) {
            // UPDATE
            $updateSql = "UPDATE dbo.user_internet 
                SET nama = ?, upload_max_limit = ?, download_max_limit = ?, mode_koneksi = ?, 
                    creation_time = ?, update_by = ?, updated_at = GETDATE()
                WHERE id = ?";

            $uParams = [
                $name,
                $upload_max,
                $download_max,
                $mode_koneksi,
                $creation_time,
                $currentUser,
                $row['id']
            ];

            $uStmt = sqlsrv_query($conn, $updateSql, $uParams);
            if ($uStmt === false) {
                throw new Exception("DB update error: " . print_r(sqlsrv_errors(), true));
            }

            sqlsrv_free_stmt($uStmt);
            $updated++;

        } else {

            // INSERT (perbaikan SOLUSI 2 → created_at & updated_at otomatis GETDATE())
            $insertSql = "INSERT INTO dbo.user_internet 
                (nama, target_ip, upload_max_limit, download_max_limit, mode_koneksi, creation_time, update_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE(), GETDATE())";

            $iParams = [
                $name,
                $target_ip,
                $upload_max,
                $download_max,
                $mode_koneksi,
                $creation_time,
                $currentUser
            ];

            $iStmt = sqlsrv_query($conn, $insertSql, $iParams);
            if ($iStmt === false) {
                throw new Exception("DB insert error: " . print_r(sqlsrv_errors(), true));
            }

            sqlsrv_free_stmt($iStmt);
            $inserted++;
        }
    }

    echo json_encode([
        'success' => true,
        'inserted' => $inserted,
        'updated' => $updated
    ]);
    exit;

} catch (Exception $ex) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $ex->getMessage()]);
    exit;
}
