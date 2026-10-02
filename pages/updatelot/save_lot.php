<?php
session_start();
require '../../koneksi3.php';

header('Content-Type: application/json');

$balenmbr = trim((string)($_POST['balenmbr'] ?? ''));
$balehdid = trim((string)($_POST['balehdid'] ?? ''));
$baleprodid = trim((string)($_POST['baleprodid'] ?? ''));
$batchno = trim((string)($_POST['batchno'] ?? ''));
$wrhsid = trim((string)($_POST['wrhsid'] ?? ''));
$lotBaledt = trim((string)($_POST['lot_baledt'] ?? ''));
$lotStockbatch = trim((string)($_POST['lot_stockbatch'] ?? ''));

if ($balenmbr === '' || $balehdid === '' || $baleprodid === '' || $batchno === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Data row tidak lengkap.']);
    exit;
}

try {
    $conn3->beginTransaction();

    $updated = 0;

    $stmtBaledt = $conn3->prepare("UPDATE whbaledt SET lot = :lot WHERE balehdid = :balehdid AND baleprodid = :baleprodid");
    $stmtBaledt->execute([
        ':lot' => $lotBaledt,
        ':balehdid' => $balehdid,
        ':baleprodid' => $baleprodid,
    ]);
    $updated += $stmtBaledt->rowCount();

    if ($wrhsid !== '') {
        $stmtStock = $conn3->prepare("UPDATE whstockbatch SET lot = :lot WHERE batchno = :batchno AND wrhsid = :wrhsid");
        $stmtStock->execute([
            ':lot' => $lotStockbatch,
            ':batchno' => $batchno,
            ':wrhsid' => $wrhsid,
        ]);
        $updated += $stmtStock->rowCount();
    }

    if ($updated === 0) {
        throw new Exception('Tidak ada data yang berubah.');
    }

    $conn3->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Lot berhasil disimpan untuk ' . $balenmbr . '.'
    ]);
} catch (Throwable $e) {
    if ($conn3->inTransaction()) {
        $conn3->rollBack();
    }

    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

