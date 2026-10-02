<?php
session_start();
require_once '../../koneksi.php';

$balenmbr = trim($_GET['balenmbr'] ?? '');
$uniqueid = trim($_GET['uniqueid'] ?? '');
$no_po    = trim($_GET['no_po'] ?? '');
$ajax     = (int)($_GET['ajax'] ?? 0);

if ($balenmbr === '' || $uniqueid === '') {
    if ($ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Parameter tidak valid']);
        exit;
    }
    $_SESSION['error'] = 'Parameter tidak valid';
    header('Location: detail_po.php?no_po=' . urlencode($no_po));
    exit;
}

$del = sqlsrv_query($conn, "DELETE FROM orderitem_gistex_dt WHERE uniqueid_parent = ? AND balenmbr = ?", [$uniqueid, $balenmbr]);

if ($ajax) {
    header('Content-Type: application/json');
    if ($del) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => print_r(sqlsrv_errors(), true)]);
    }
    exit;
}

if ($del) {
    $_SESSION['success'] = "Balenmbr $balenmbr berhasil dihapus";
} else {
    $_SESSION['error'] = 'Gagal hapus: ' . print_r(sqlsrv_errors(), true);
}

header('Location: detail_po.php?no_po=' . urlencode($no_po));
exit;
