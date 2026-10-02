<?php
session_start();

require_once '../../koneksi.php';
require_once 'helpers.php';

$nomorPo      = trim($_POST['no_po'] ?? '');
$descriptions = $_POST['description'] ?? []; // description[] dari form

file_put_contents(
    __DIR__.'/debug_post.txt',
    print_r($_POST, true)
);

$masterIds    = $_POST['master_id'] ?? [];
$masterItems  = $_POST['master_item'] ?? []; // item
$qtyOrders    = $_POST['qty_order'] ?? [];
$packagings   = $_POST['packaging'] ?? [];
$uoms         = $_POST['uom'] ?? [];

$user = gistexCurrentUser();

if (
    empty($nomorPo) ||
    !is_array($descriptions) ||
    count($descriptions) == 0
) {
    $_SESSION['error'] = 'Nomor PO / item kosong';
    header('Location: orderitem_gistex.php');
    exit;
}

$checkPo = sqlsrv_query(
    $conn,
    "SELECT TOP 1 uniqueid
     FROM orderitem_gistexhd
     WHERE no_po = ?",
    [$nomorPo]
);

if ($checkPo && sqlsrv_fetch_array($checkPo, SQLSRV_FETCH_ASSOC)) {

    $_SESSION['error'] =
        'Nomor PO sudah pernah dibuat : ' . $nomorPo;

    header('Location: orderitem_gistex.php');
    exit;
}

if (!sqlsrv_begin_transaction($conn)) {

    $_SESSION['error'] = 'Gagal memulai transaksi';
    header('Location: orderitem_gistex.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| SAVE HEADER
|--------------------------------------------------------------------------
*/

$headerUniqueId = uniqid('HD', true);

$saveHeader = sqlsrv_query(
    $conn,
    "INSERT INTO orderitem_gistexhd
    (
        uniqueid,
        no_po,
        create_date,
        created_by,
        updatedate,
        updateby
    )
    VALUES
    (
        ?,
        ?,
        GETDATE(),
        ?,
        GETDATE(),
        ?
    )",
    [
        $headerUniqueId,
        $nomorPo,
        $user,
        $user
    ]
);

if (!$saveHeader) {

    sqlsrv_rollback($conn);

    $_SESSION['error'] =
        'Gagal simpan header : ' .
        print_r(sqlsrv_errors(), true);

    header('Location: orderitem_gistex.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| SAVE DETAIL
|--------------------------------------------------------------------------
*/

$inserted = 0;

foreach ($descriptions as $i => $description) {

    $description = trim((string)$description);

    $masterId = trim((string)($masterIds[$i] ?? ''));
    $masterItem = trim((string)($masterItems[$i] ?? ''));

    $qtyOrder = $qtyOrders[$i] ?? 0;
    $packaging = trim((string)($packagings[$i] ?? ''));
    $uom = trim((string)($uoms[$i] ?? ''));

    if ($masterId == '') {
        continue;
    }

    $detailUniqueId = uniqid('GI', true);

    $saveDetail = sqlsrv_query(
        $conn,
        "INSERT INTO orderitem_gistex
        (
            uniqueid,
            no_po,
            id,
            item,
            description,
            [Qty.Order],
            uom,
            packaging,
            create_date,
            created_by,
            updatedate,
            updateby
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            GETDATE(),
            ?,
            GETDATE(),
            ?
        )",
        [
            $detailUniqueId,
            $nomorPo,
            $masterId,
            $masterItem,
            $description,
            $qtyOrder,
            $uom,
            $packaging,
            $user,
            $user
        ]
    );

    if (!$saveDetail) {

        sqlsrv_rollback($conn);

        $_SESSION['error'] =
            'Gagal simpan detail : ' .
            print_r(sqlsrv_errors(), true);

        header('Location: orderitem_gistex.php');
        exit;
    }

    $inserted++;
}

if ($inserted == 0) {

    sqlsrv_rollback($conn);

    $_SESSION['error'] =
        'Tidak ada item valid untuk disimpan';

    header('Location: orderitem_gistex.php');
    exit;
}

if (!sqlsrv_commit($conn)) {

    sqlsrv_rollback($conn);

    $_SESSION['error'] =
        'Gagal commit transaksi';

    header('Location: orderitem_gistex.php');
    exit;
}

$_SESSION['success'] =
    'PO berhasil disimpan : ' .
    $nomorPo .
    ' (' . $inserted . ' item)';

header('Location: orderitem_gistex.php');
exit;
