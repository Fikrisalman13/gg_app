<?php
session_start();
require_once '../../koneksi.php';
require_once '../../koneksi3.php';
require_once 'helpers.php';

$uniqueid  = trim($_POST['uniqueid'] ?? '');
$no_po     = trim($_POST['no_po'] ?? '');
$balenmbrs = $_POST['balenmbr'] ?? [];

if (empty($uniqueid) || empty($balenmbrs)) {
    $_SESSION['error'] = 'UniqueID / balenmbr kosong';
    header('Location: detail_po.php?no_po=' . urlencode($no_po));
    exit;
}

// Verify parent exists
$stmtOri = sqlsrv_query($conn, "SELECT no_po FROM orderitem_gistex WHERE uniqueid = ?", [$uniqueid]);
$oriRow = $stmtOri ? sqlsrv_fetch_array($stmtOri, SQLSRV_FETCH_ASSOC) : null;
if (!$oriRow) {
    $_SESSION['error'] = 'Data order tidak ditemukan';
    header('Location: detail_po.php?no_po=' . urlencode($no_po));
    exit;
}
$no_po = $oriRow['no_po'];

// Placeholders for IN clause
$placeholders = [];
$params = [];
foreach ($balenmbrs as $i => $bm) {
    $bm = trim($bm);
    if ($bm === '') continue;
    $key = ":bm$i";
    $placeholders[] = $key;
    $params[$key] = $bm;
}
if (empty($placeholders)) {
    $_SESSION['error'] = 'Balenmbr tidak valid';
    header('Location: detail_po.php?no_po=' . urlencode($no_po));
    exit;
}

// Fetch detail from PostgreSQL
$sqlDetail = "
SELECT
    whbalehd.balenmbr,
    whbaleprod.prodname,
    whbaleprod.prodcode,
    whbaledt.batchno,
    whbaledt.qtym,
    whbaledt.qtyyard,
    whbaledt.stdqty,
    whbaledt.lot
FROM whbalehd
INNER JOIN whbaleprod ON whbalehd.balehdid = whbaleprod.balehdid
INNER JOIN whbaledt ON whbalehd.balehdid = whbaledt.balehdid
    AND whbaleprod.baleprodid = whbaledt.baleprodid
WHERE whbalehd.balenmbr IN (" . implode(',', $placeholders) . ")
ORDER BY whbaleprod.prodname ASC
";

$stmtDetail = $conn3->prepare($sqlDetail);
$stmtDetail->execute($params);
$detailRows = $stmtDetail->fetchAll(PDO::FETCH_ASSOC);

if (empty($detailRows)) {
    $_SESSION['error'] = 'Data detail balenmbr tidak ditemukan';
    header('Location: detail_po.php?no_po=' . urlencode($no_po));
    exit;
}

if (!sqlsrv_begin_transaction($conn)) {
    $_SESSION['error'] = 'Gagal memulai transaksi';
    header('Location: detail_po.php?no_po=' . urlencode($no_po));
    exit;
}

$inserted = 0;
$user = gistexCurrentUser();
foreach ($detailRows as $det) {
    $balenmbr = $det['balenmbr'] ?? '';
    $prodname = $det['prodname'] ?? '';
    $prodcode = $det['prodcode'] ?? '';
    $batchno  = $det['batchno'] ?? '';
    $qtym     = $det['qtym'] ?? 0;
    $qtyyard  = $det['qtyyard'] ?? 0;
    $stdqty   = $det['stdqty'] ?? 0;
    $lot      = $det['lot'] ?? '';

    $ins = sqlsrv_query($conn, "
        INSERT INTO orderitem_gistex_dt
        (uniqueid_parent, balenmbr, prodname, prodcode, batchno, qtym, qtyyard, stdqty, lot,
         create_date, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?,
                GETDATE(), ?)
    ", [
        $uniqueid, $balenmbr, $prodname, $prodcode, $batchno, $qtym, $qtyyard, $stdqty, $lot,
        $user
    ]);

    if (!$ins) {
        sqlsrv_rollback($conn);
        $_SESSION['error'] = 'Gagal simpan: ' . print_r(sqlsrv_errors(), true);
        header('Location: detail_po.php?no_po=' . urlencode($no_po));
        exit;
    }
    $inserted++;
}

if (!sqlsrv_commit($conn)) {
    sqlsrv_rollback($conn);
    $_SESSION['error'] = 'Gagal commit transaksi';
    header('Location: detail_po.php?no_po=' . urlencode($no_po));
    exit;
}

$_SESSION['success'] = "$inserted packingno berhasil ditambahkan";
header('Location: detail_po.php?no_po=' . urlencode($no_po));
exit;
