<?php
session_start();
header('Content-Type: application/json');
require_once '../../koneksi.php';

$result = ['success' => false, 'message' => 'Error'];

$ticket = $_POST['ticket'] ?? '';
$roleCode = $_POST['role_code'] ?? '';
$userId = $_SESSION['UserId'] ?? 0;

if (!$ticket || !$roleCode || !$userId) {
    $result['message'] = 'Missing parameters';
    echo json_encode($result);
    exit;
}

// Map role_code to GroupRole
$roleMap = [
    'pemohon'         => 'Pemohon',
    'atasan_pemohon'  => 'Atasan Pemohon',
    'petugas_it'      => 'Petugas IT',
    'petugas_cctv'    => 'Petugas CCTV',
    'kabag_it'        => 'Kabag IT',
    'kadept_it'       => 'Kadept IT',
    'direksi'         => 'Direksi'
];

$groupRole = $roleMap[$roleCode] ?? null;
if (!$groupRole) {
    $result['message'] = 'Invalid role';
    echo json_encode($result);
    exit;
}

try {
    // Delete the TTD entry from Form_Pengajuan_Barang_TTD
    $sql = "DELETE FROM Form_Pengajuan_Barang_TTD WHERE Ticket = ? AND GroupRole = ?";
    $stmt = sqlsrv_query($conn, $sql, [$ticket, $groupRole]);
    
    if ($stmt !== false) {
        sqlsrv_free_stmt($stmt);
        $result['success'] = true;
        $result['message'] = 'Tanda tangan berhasil dihapus';
    } else {
        $result['message'] = 'Database error: ' . print_r(sqlsrv_errors(), true);
    }
} catch (Exception $ex) {
    $result['message'] = 'Exception: ' . $ex->getMessage();
}

echo json_encode($result);
exit;
