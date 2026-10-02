<?php
// ======================================================
// Make Static ARP + Add Comment
// ======================================================
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

require_once '../../../config.php';
require_once '../../../routeros_api.class.php';

header('Content-Type: application/json');

$arp_id = $_POST['arp_id'] ?? '';
$comment = $_POST['comment'] ?? '';

if (!$arp_id) {
    die(json_encode(['success' => false, 'message' => 'ARP ID tidak ditemukan']));
}

try {
    $API = new RouterosAPI();
    
    if (!$API->connect($mt_ip, $mt_user, $mt_pass)) {
        throw new Exception("Koneksi Mikrotik gagal");
    }

    // Ambil data ARP awal untuk verifikasi
    $arpBefore = $API->comm('/ip/arp/print', ['?.id' => $arp_id]);
    if (empty($arpBefore)) {
        throw new Exception("ARP ID tidak ditemukan pada Mikrotik.");
    }

    $addr = $arpBefore[0]['address'] ?? null;

    // Make Static
    $makeRes = $API->comm('/ip/arp/make-static', ['.id' => $arp_id]);

    // Set Comment jika ada
    $setRes = null;
    if (!empty($comment)) {
        $setRes = $API->comm('/ip/arp/set', [
            '.id' => $arp_id,
            'comment' => $comment
        ]);
    }

    // Verifikasi: ambil ulang berdasarkan .id atau address
    $arpAfter = $API->comm('/ip/arp/print', ['?.id' => $arp_id]);
    if (empty($arpAfter) && $addr) {
        // coba cek berdasarkan address
        $arpAfter = $API->comm('/ip/arp/print', ['?address' => $addr]);
    }

    // Cek apakah status/flag berubah menjadi static atau dynamic == false dan apakah comment terpasang
    $isStatic = false;
    $hasComment = false;
    if (!empty($arpAfter)) {
        $a = $arpAfter[0];
        $status = $a['status'] ?? '';
        $dynamic = $a['dynamic'] ?? null;
        $flags = $a['flags'] ?? '';
        $c = $a['comment'] ?? '';

        if (strtolower($status) === 'static') $isStatic = true;
        if ($dynamic !== null && (strtolower($dynamic) === 'false' || $dynamic === false)) $isStatic = true;
        if (is_string($flags) && strpos($flags, 'D') === false) $isStatic = true;

        if (!empty($c)) $hasComment = true;
    }

    if ($isStatic && ($hasComment || empty($comment))) {
        $API->disconnect();
        echo json_encode(['success' => true, 'message' => 'ARP berhasil dijadikan Static dan comment diset.']);
        exit;
    }

    // Jika sampai sini verifikasi gagal, coba fallback: remove old entry dan tambahkan entry static baru
    $fallbackResult = null;
    try {
        // Ambil field penting dari arpBefore
        $mac = $arpBefore[0]['mac-address'] ?? ($arpBefore[0]['mac'] ?? null);
        $iface = $arpBefore[0]['interface'] ?? null;

        // Hapus entry lama (dynamic)
        if (!empty($arp_id)) {
            $API->comm('/ip/arp/remove', ['.id' => $arp_id]);
        }

        // Tambah entry static baru
        $addParams = ['address' => $addr];
        if (!empty($mac)) $addParams['mac-address'] = $mac;
        if (!empty($iface)) $addParams['interface'] = $iface;
        if (!empty($comment)) $addParams['comment'] = $comment;

        $addRes = $API->comm('/ip/arp/add', $addParams);

        // Verifikasi ulang berdasarkan address
        $arpAfter2 = $API->comm('/ip/arp/print', ['?address' => $addr]);

        $API->disconnect();

        $isStatic2 = false;
        $hasComment2 = false;
        if (!empty($arpAfter2)) {
            $b = $arpAfter2[0];
            $status2 = $b['status'] ?? '';
            $dynamic2 = $b['dynamic'] ?? null;
            $flags2 = $b['flags'] ?? '';
            $c2 = $b['comment'] ?? '';

            if (strtolower($status2) === 'static') $isStatic2 = true;
            if ($dynamic2 !== null && (strtolower($dynamic2) === 'false' || $dynamic2 === false)) $isStatic2 = true;
            if (is_string($flags2) && strpos($flags2, 'D') === false) $isStatic2 = true;
            if (!empty($c2)) $hasComment2 = true;
        }

        if ($isStatic2) {
            $fallbackResult = ['success' => true, 'message' => 'Fallback: ARP dibuat static dengan remove/add.'];
            echo json_encode($fallbackResult);
            exit;
        } else {
            $debug = [
                'before' => $arpBefore,
                'after' => $arpAfter,
                'makeRes' => $makeRes,
                'setRes' => $setRes,
                'addRes' => $addRes ?? null,
                'after2' => $arpAfter2 ?? null
            ];

            // Simpan debug ke file untuk analisa lebih lanjut
            $logDir = __DIR__ . '/../../../logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            $logFile = $logDir . '/make_static_arp_debug_' . date('Ymd_His') . '.json';
            @file_put_contents($logFile, json_encode(['error' => 'Verifikasi gagal setelah fallback', 'debug' => $debug], JSON_PRETTY_PRINT));

            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Verifikasi ARP gagal setelah fallback. Detail disimpan di logs.', 'debug' => $debug]);
            exit;
        }

    } catch (Exception $exFallback) {
        try { $API->disconnect(); } catch (Throwable $_) {}
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Fallback gagal: ' . $exFallback->getMessage()]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
