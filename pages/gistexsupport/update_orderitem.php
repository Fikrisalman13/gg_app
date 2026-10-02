<?php
session_start();

require_once '../../koneksi.php';
require_once 'helpers.php';

$uniqueid    = trim($_POST['uniqueid'] ?? '');
$description = trim($_POST['description'] ?? '');
$qtyOrder    = $_POST['qty_order'] ?? 0;
$packaging   = trim($_POST['packaging'] ?? '');
$uom         = trim($_POST['uom'] ?? '');

// Get no_po for redirect
$q = sqlsrv_query($conn, "SELECT no_po FROM orderitem_gistex WHERE uniqueid = ?", [$uniqueid]);
$r = $q ? sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC) : null;
$no_po = $r['no_po'] ?? '';

$user = gistexCurrentUser();

$result = sqlsrv_query(
    $conn,
    "UPDATE orderitem_gistex
     SET
        description = ?,
        [Qty.Order] = ?,
        packaging = ?,
        uom = ?,
        updatedate = GETDATE(),
        updateby = ?
     WHERE uniqueid = ?",
    [
        $description,
        $qtyOrder,
        $packaging,
        $uom,
        $user,
        $uniqueid
    ]
);

if ($result) {
    $_SESSION['success'] = 'Data berhasil diupdate';
} else {
    $_SESSION['error'] = print_r(sqlsrv_errors(), true);
}

header('Location: detail_po.php?no_po=' . urlencode($no_po));
exit;