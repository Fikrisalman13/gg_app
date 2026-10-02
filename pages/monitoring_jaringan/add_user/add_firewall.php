<?php
// ======================================================
// Add IP to Firewall Address List
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

$ip = $_POST['ip'] ?? '';
$fw_list = $_POST['fw_list'] ?? '';
$comment = $_POST['comment'] ?? '';

if (!$ip || !$fw_list) {
    die(json_encode(['success' => false, 'message' => 'IP atau Firewall List tidak lengkap']));
}

try {
    $API = new RouterosAPI();
    
    if (!$API->connect($mt_ip, $mt_user, $mt_pass)) {
        throw new Exception("Koneksi Mikrotik gagal");
    }

    // Cek apakah sudah ada
    $exists = $API->comm('/ip/firewall/address-list/print', [
        '?address' => $ip,
        '?list' => $fw_list
    ]);

    if (!empty($exists)) {
        // Sudah ada, update comment saja
        $id = $exists[0]['.id'] ?? ($exists[0]['id'] ?? null);
        if ($id && !empty($comment)) {
            $API->comm('/ip/firewall/address-list/set', [
                '.id' => $id,
                'comment' => $comment
            ]);
        }
        $message = "Firewall Address sudah ada, comment diupdate";
    } else {
        // Tambah baru
        $API->comm('/ip/firewall/address-list/add', [
            'list' => $fw_list,
            'address' => $ip,
            'comment' => $comment
        ]);
        $message = "IP berhasil ditambahkan ke Firewall Address List";
    }

    $API->disconnect();

    echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
