<?php
// ======================================================
// Make Static DHCP Lease + Add Comment
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

$lease_id = $_POST['lease_id'] ?? '';
$comment = $_POST['comment'] ?? '';

if (!$lease_id) {
    die(json_encode(['success' => false, 'message' => 'Lease ID tidak ditemukan']));
}

try {
    $API = new RouterosAPI();
    
    if (!$API->connect($mt_ip, $mt_user, $mt_pass)) {
        throw new Exception("Koneksi Mikrotik gagal");
    }

    // Make Static
    $API->comm('/ip/dhcp-server/lease/make-static', ['.id' => $lease_id]);

    // Set Comment
    if (!empty($comment)) {
        $API->comm('/ip/dhcp-server/lease/set', [
            '.id' => $lease_id,
            'comment' => $comment
        ]);
    }

    $API->disconnect();

    echo json_encode(['success' => true, 'message' => 'DHCP Lease berhasil dijadikan Static']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
