<?php
require_once '../../koneksi.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id_piece'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$id_piece       = (int)$_POST['id_piece'];
$panjang_awal   = (float)($_POST['panjang_awal'] ?? 0);   // SELALU Meter
$panjang_akhir  = (float)($_POST['panjang_akhir'] ?? 0);  // SELALU Meter
$lebar_kain     = (float)($_POST['lebar_kain'] ?? 0);

// Nilai dari form (SUDAH termasuk toleransi, dalam uom_cp)
$std_input = (float)($_POST['standart_potong'] ?? 0);
$max_input = (float)($_POST['max_potong'] ?? 0);
$min_input = (float)($_POST['min_potong'] ?? 0);

// Toleransi asli dari input (CM)
$toleransi_cm = (float)($_POST['toleransi'] ?? 0);

try {

    // ================= AMBIL INFO HEADER =================
    $stmtInfo = sqlsrv_query($conn, "
        SELECT 
            p.id_header,
            h.uom_cp,
            h.type_counter
        FROM cl_cutting_piece p
        JOIN cl_cutting_header h ON p.id_header = h.id_header
        WHERE p.id_piece = ?
    ", [$id_piece]);

    if (!$stmtInfo) {
        throw new Exception('Query piece info gagal');
    }

    $info = sqlsrv_fetch_array($stmtInfo, SQLSRV_FETCH_ASSOC);
    if (!$info) {
        throw new Exception('Piece tidak ditemukan');
    }

    $uom_cp       = $info['uom_cp'];        // M / Y
    $type_counter = $info['type_counter'];  // M / Y

    // =====================================================
    // PANJANG CONVERSION (FIX SESUAI REQUIREMENT)
    // panjang_awal & panjang_akhir SELALU dalam Meter
    // =====================================================

    $panjang_awal_conv  = $panjang_awal;
    $panjang_akhir_conv = $panjang_akhir;

    /*
        RULE:
        - uom_cp = Y & type_counter = M → M ke Y
        - uom_cp = M & type_counter = M → M ke M
        - uom_cp = Y & type_counter = Y → M ke Y
    */

    if ($uom_cp === 'Y') {
        // Jika CP pakai Yard → simpan conv dalam Yard
        $panjang_awal_conv  = $panjang_awal / 0.9144;
        $panjang_akhir_conv = $panjang_akhir / 0.9144;
    }
    // else uom_cp === 'M' → tetap Meter

    // =====================================================
    // STANDART / MIN / MAX CONVERSION
    // nilai input sudah termasuk toleransi (dalam uom_cp)
    // =====================================================

    $std_conv = $std_input;
    $min_conv = $min_input;
    $max_conv = $max_input;

    if ($uom_cp === 'M' && $type_counter === 'Y') {
        // M → Y
        $std_conv = $std_input / 0.9144;
        $min_conv = $min_input / 0.9144;
        $max_conv = $max_input / 0.9144;
    } elseif ($uom_cp === 'Y' && $type_counter === 'M') {
        // Y → M
        $std_conv = $std_input * 0.9144;
        $min_conv = $min_input * 0.9144;
        $max_conv = $max_input * 0.9144;
    }

    // =====================================================
    // TOLERANSI CONVERSION
    // toleransi asli = CM
    // toleransi_conv = sesuai type_counter
    // =====================================================

    $toleransi_conv = $toleransi_cm;

    if ($type_counter === 'M') {
        $toleransi_conv = $toleransi_cm * 0.01; // CM → M
    } elseif ($type_counter === 'Y') {
        $toleransi_conv = $toleransi_cm * (0.01 / 0.9144); // CM → Y
    }

    // =====================================================
    // UPDATE DATABASE
    // =====================================================

    $stmt = sqlsrv_query($conn, "
        UPDATE cl_cutting_piece
        SET
            panjang_awal           = ?,
            panjang_akhir          = ?,
            panjang_awal_conv      = ?,
            panjang_akhir_conv     = ?,
            susut                  = ?,
            lebar_kain             = ?,
            standart_potong        = ?,
            max_potong             = ?,
            min_potong             = ?,
            toleransi              = ?,
            standart_potong_conv   = ?,
            min_potong_conv        = ?,
            max_potong_conv        = ?,
            toleransi_conv         = ?
        WHERE id_piece = ?
    ", [
        $panjang_awal,
        $panjang_akhir,
        $panjang_awal_conv,
        $panjang_akhir_conv,
        $panjang_awal - $panjang_akhir,
        $lebar_kain,
        $std_input,
        $max_input,
        $min_input,
        $toleransi_cm,
        $std_conv,
        $min_conv,
        $max_conv,
        $toleransi_conv,
        $id_piece
    ]);

    if (!$stmt) {
        throw new Exception('Update gagal');
    }

    echo json_encode(['status' => 'ok']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
