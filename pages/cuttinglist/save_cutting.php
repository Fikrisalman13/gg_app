<?php
require_once '../../koneksi.php';
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['payload'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$data = json_decode($_POST['payload'], true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Payload invalid']);
    exit;
}

try {
    sqlsrv_begin_transaction($conn);

    /* ================= HEADER ================= */
    $stmtH = sqlsrv_query($conn, "
        INSERT INTO cl_cutting_header (
            cp_no,
            type_counter,
            uom_cp,
            id_mesin,
            processed,
            created_by,
            created_date
        )
        OUTPUT INSERTED.id_header
        VALUES (?, ?, ?, ?, 0, ?, GETDATE())
    ", [
        $data['cp_no'],          // 1
        $data['type_counter'],   // 2
        $data['uom_cp'],         // 3
        (int)$data['id_mesin'],  // 4
        $_SESSION['UserName'] ?? 'SYSTEM' // 5
    ]);

    if (!$stmtH) {
        throw new Exception(print_r(sqlsrv_errors(), true));
    }

    $rowH = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC);
    $id_header = (int)$rowH['id_header'];

    $uom_cp      = $data['uom_cp'];
    $uom_counter = $data['type_counter'];

    /* ================= PIECE ================= */
    foreach ($data['pieces'] as $p) {

        $panjang_awal  = (float)$p['panjang_awal'];
        $panjang_akhir = (float)$p['panjang_akhir'];

        $std = (float)$p['std'];
        $min = (float)$p['min'];
        $max = (float)$p['max'];

        $toleransi_cm = (float)($p['toleransi'] ?? 0);

        $tol_unit = ($uom_cp === 'Y')
            ? $toleransi_cm * (0.01 / 0.9144)
            : $toleransi_cm * 0.01;

        $std_in = $std + $tol_unit;
        $min_in = $min + $tol_unit;
        $max_in = $max + $tol_unit;

        $pa_conv = ($uom_cp === 'Y') ? $panjang_awal / 0.9144 : $panjang_awal;
        $pk_conv = ($uom_cp === 'Y') ? $panjang_akhir / 0.9144 : $panjang_akhir;

        $factor = ($uom_cp === $uom_counter)
            ? 1
            : (($uom_cp === 'M') ? 1 / 0.9144 : 0.9144);

        $stmtP = sqlsrv_query($conn, "
            INSERT INTO cl_cutting_piece (
                id_header,
                piece_no,
                piece_code,
                panjang_awal,
                panjang_akhir,
                panjang_awal_conv,
                panjang_akhir_conv,
                susut,
                lebar_kain,
                standart_potong,
                min_potong,
                max_potong,
                uom_cp,
                toleransi,
                standart_potong_conv,
                min_potong_conv,
                max_potong_conv,
                uom_counter,
                toleransi_conv,
                created_by,
                created_date
            )
            OUTPUT INSERTED.id_piece
            VALUES (
                ?,?,?,?,?,?,?,?,?,?,
                ?,?,?,?,?,?,?,?,?,?,
                GETDATE()
            )
        ", [
            $id_header,                 // 1
            (int)$p['piece_no'],        // 2
            $p['piece_code'],           // 3
            $panjang_awal,              // 4
            $panjang_akhir,             // 5
            $pa_conv,                   // 6
            $pk_conv,                   // 7
            $panjang_awal - $panjang_akhir, // 8
            (float)$p['lebar'],         // 9
            $std_in,                    // 10
            $min_in,                    // 11
            $max_in,                    // 12
            $uom_cp,                    // 13
            $toleransi_cm,              // 14
            $std_in * $factor,          // 15
            $min_in * $factor,          // 16
            $max_in * $factor,          // 17
            $uom_counter,               // 18
            $toleransi_cm * $factor,    // 19
            $_SESSION['UserName'] ?? 'SYSTEM' // 20
        ]);

        if (!$stmtP) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }

        $rowP = sqlsrv_fetch_array($stmtP, SQLSRV_FETCH_ASSOC);
        $id_piece = (int)$rowP['id_piece'];

        /* ================= CACAT ================= */
        foreach ($p['cacat'] ?? [] as $c) {

            $stmtC = sqlsrv_query($conn, "
                INSERT INTO cl_cutting_cacat (
                    id_piece,
                    cacat_no,
                    kode_cacat,
                    nama_cacat,
                    status_cacat,
                    dari,
                    sampai,
                    panjang_cacat,
                    uom,
                    uom_conv,
                    dari_conv,
                    sampai_conv,
                    panjang_cacat_conv,
                    created_by,
                    created_date
                )
                VALUES (
                    ?,?,?,?,?,?,
                    ?,?,?,?,?,?,
                    ?,?,GETDATE()
                )
            ", [
                $id_piece,                 // 1
                (int)$c['no'],             // 2
                $c['kode'],                // 3
                $c['nama'],                // 4
                $c['status'],              // 5
                $c['dari'],                // 6
                $c['sampai'],              // 7
                $c['panjang'],             // 8
                $uom_counter,              // 9
                $uom_cp,                   // 10
                $c['dari'] * $factor,      // 11
                $c['sampai'] * $factor,    // 12
                $c['panjang'] * $factor,   // 13
                $_SESSION['UserName'] ?? 'SYSTEM' // 14
            ]);

            if (!$stmtC) {
                throw new Exception(print_r(sqlsrv_errors(), true));
            }
        }
    }

    sqlsrv_commit($conn);
    echo json_encode(['status' => 'ok']);

} catch (Throwable $e) {
    sqlsrv_rollback($conn);
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
