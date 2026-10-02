<?php
session_start();
require '../../koneksi3.php';

header('Content-Type: application/json');

$balenmbr = trim((string)($_POST['balenmbr'] ?? ''));
$direction = trim((string)($_POST['direction'] ?? ''));

if ($balenmbr === '' || !in_array($direction, ['baledt-to-stockbatch', 'stockbatch-to-baledt'], true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Parameter tidak valid.']);
    exit;
}

try {
    $conn3->beginTransaction();

    if ($direction === 'baledt-to-stockbatch') {
        $sql = "UPDATE whstockbatch sb
                SET lot = d.lot
                FROM whbalehd h,
                     whbaledt d,
                     whbaleprod p
                WHERE h.balenmbr = :balenmbr
                  AND h.balehdid = d.balehdid
                  AND h.balehdid = p.balehdid
                  AND d.baleprodid = p.baleprodid
                  AND d.batchno = sb.batchno
                  AND p.wrhsid = sb.wrhsid
                  AND d.lot IS NOT NULL";
    } else {
        $sql = "UPDATE whbaledt d
                SET lot = sb.lot
                FROM whbalehd h,
                     whbaleprod p,
                     whstockbatch sb
                WHERE h.balenmbr = :balenmbr
                  AND d.balehdid = h.balehdid
                  AND h.balehdid = p.balehdid
                  AND d.baleprodid = p.baleprodid
                  AND d.batchno = sb.batchno
                  AND p.wrhsid = sb.wrhsid
                  AND sb.lot IS NOT NULL";
    }

    $stmt = $conn3->prepare($sql);
    $stmt->execute([':balenmbr' => $balenmbr]);
    $updated = $stmt->rowCount();

    if ($updated === 0) {
        throw new Exception('Tidak ada row yang berubah.');
    }

    $conn3->commit();

    echo json_encode([
        'status' => 'success',
        'message' => $updated . ' row berhasil disamakan untuk bale number ' . $balenmbr . '.'
    ]);
} catch (Throwable $e) {
    if ($conn3->inTransaction()) {
        $conn3->rollBack();
    }

    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
