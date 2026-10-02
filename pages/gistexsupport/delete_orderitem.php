<?php
session_start();

require_once '../../koneksi.php';

$uniqueid = $_GET['uniqueid'] ?? '';

if ($uniqueid == '') {
    $_SESSION['error'] = 'Unique ID tidak ditemukan';
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
| Ambil data detail yang akan dihapus
|--------------------------------------------------------------------------
*/
$getData = sqlsrv_query(
    $conn,
    "SELECT no_po
     FROM orderitem_gistex
     WHERE uniqueid = ?",
    [$uniqueid]
);

$row = sqlsrv_fetch_array($getData, SQLSRV_FETCH_ASSOC);

if (!$row) {
    sqlsrv_rollback($conn);

    $_SESSION['error'] = 'Data tidak ditemukan';
    header('Location: orderitem_gistex.php');
    exit;
}

$noPo = $row['no_po'];

/*
|--------------------------------------------------------------------------
| Hapus detail
|--------------------------------------------------------------------------
*/
$deleteDetail = sqlsrv_query(
    $conn,
    "DELETE FROM orderitem_gistex
     WHERE uniqueid = ?",
    [$uniqueid]
);

if (!$deleteDetail) {

    sqlsrv_rollback($conn);

    $_SESSION['error'] =
        'Gagal menghapus detail : ' .
        print_r(sqlsrv_errors(), true);

    header('Location: orderitem_gistex.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Cek sisa detail untuk no_po yang sama
|--------------------------------------------------------------------------
*/
$checkDetail = sqlsrv_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM orderitem_gistex
     WHERE no_po = ?",
    [$noPo]
);

$checkRow = sqlsrv_fetch_array($checkDetail, SQLSRV_FETCH_ASSOC);

$totalDetail = (int)$checkRow['total'];

/*
|--------------------------------------------------------------------------
| Jika sudah tidak ada detail, hapus header
|--------------------------------------------------------------------------
*/
if ($totalDetail === 0) {

    $deleteHeader = sqlsrv_query(
        $conn,
        "DELETE FROM orderitem_gistexhd
         WHERE no_po = ?",
        [$noPo]
    );

    if (!$deleteHeader) {

        sqlsrv_rollback($conn);

        $_SESSION['error'] =
            'Gagal menghapus header : ' .
            print_r(sqlsrv_errors(), true);

        header('Location: orderitem_gistex.php');
        exit;
    }
}

sqlsrv_commit($conn);

$_SESSION['success'] = 'Data berhasil dihapus';

header('Location: orderitem_gistex.php');
exit;