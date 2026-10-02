<?php
require_once '../../koneksi.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id_cacat'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$id_cacat       = (int)$_POST['id_cacat'];
$kode_cacat     = (string)($_POST['kode_cacat'] ?? '');
$nama_cacat     = (string)($_POST['nama_cacat'] ?? '');
$status_cacat   = (string)($_POST['status_cacat'] ?? '');
$dari           = (float)($_POST['dari'] ?? 0);
$sampai         = (float)($_POST['sampai'] ?? 0);
$panjang_cacat  = (float)($_POST['panjang_cacat'] ?? 0);

try {

    // =====================================================
    // AMBIL UOM DARI HEADER (VIA PIECE)
    // =====================================================
    $stmtInfo = sqlsrv_query($conn, "
        SELECT 
            h.type_counter AS uom,
            h.uom_cp       AS uom_conv
        FROM cl_cutting_cacat c
        JOIN cl_cutting_piece p ON c.id_piece = p.id_piece
        JOIN cl_cutting_header h ON p.id_header = h.id_header
        WHERE c.id_cacat = ?
    ", [$id_cacat]);

    if (!$stmtInfo) {
        throw new Exception('Query UOM gagal');
    }

    $info = sqlsrv_fetch_array($stmtInfo, SQLSRV_FETCH_ASSOC);
    if (!$info) {
        throw new Exception('Data cacat tidak ditemukan');
    }

    $uom      = $info['uom'];       // M / Y  (type_counter)
    $uom_conv = $info['uom_conv'];  // M / Y  (uom_cp)

    // =====================================================
    // HITUNG FAKTOR KONVERSI
    // =====================================================
    $factor = 1;

    if ($uom === 'M' && $uom_conv === 'Y') {
        $factor = 1 / 0.9144;   // Meter → Yard
    } elseif ($uom === 'Y' && $uom_conv === 'M') {
        $factor = 0.9144;       // Yard → Meter
    }

    // =====================================================
    // NILAI CONVERTED
    // =====================================================
    $dari_conv          = $dari * $factor;
    $sampai_conv        = $sampai * $factor;
    $panjang_cacat_conv = $panjang_cacat * $factor;

    // =====================================================
    // UPDATE CACAT (FULL)
    // =====================================================
    $stmt = sqlsrv_query($conn, "
        UPDATE cl_cutting_cacat
        SET
            kode_cacat            = ?,
            nama_cacat            = ?,
            status_cacat          = ?,
            dari                  = ?,
            sampai                = ?,
            panjang_cacat         = ?,
            uom                   = ?,
            uom_conv              = ?,
            dari_conv             = ?,
            sampai_conv           = ?,
            panjang_cacat_conv    = ?
        WHERE id_cacat = ?
    ", [
        $kode_cacat,
        $nama_cacat,
        $status_cacat,
        $dari,
        $sampai,
        $panjang_cacat,
        $uom,
        $uom_conv,
        $dari_conv,
        $sampai_conv,
        $panjang_cacat_conv,
        $id_cacat
    ]);

    if (!$stmt) {
        throw new Exception('Update cacat gagal');
    }

    echo json_encode(['status' => 'ok']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
