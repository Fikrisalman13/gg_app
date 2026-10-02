<?php
require_once '../../koneksi.php';
require_once 'includes/cutting_helper.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$ids = isset($_POST['ids']) ? (array)$_POST['ids'] : [];
if (empty($ids)) {
    http_response_code(400);
    echo json_encode(['error' => 'No IDs selected']);
    exit;
}

try {
    sqlsrv_begin_transaction($conn);
    $processed = 0;

    foreach ($ids as $id_header) {

        $id_header = (int)$id_header;

        /* ================= HEADER ================= */
        $hStmt = sqlsrv_query($conn, "
            SELECT id_header, type_counter, uom_cp
            FROM cl_cutting_header
            WHERE id_header = ?
        ", [$id_header]);

        $header = sqlsrv_fetch_array($hStmt, SQLSRV_FETCH_ASSOC);
        if (!$header) {
            continue;
        }

        $uom_hasil_cutting = $header['type_counter']; // M / Y
        $uom_conv          = $header['uom_cp'];       // M / Y

        // ===== FACTOR KONVERSI =====
        $factor = 1;
        if ($uom_hasil_cutting === 'M' && $uom_conv === 'Y') {
            $factor = 1 / 0.9144; // meter → yard
        } elseif ($uom_hasil_cutting === 'Y' && $uom_conv === 'M') {
            $factor = 0.9144;     // yard → meter
        }

        /* ================= PIECE ================= */
        $pStmt = sqlsrv_query($conn, "
            SELECT *
            FROM cl_cutting_piece
            WHERE id_header = ?
            ORDER BY piece_no
        ", [$id_header]);

        while ($piece = sqlsrv_fetch_array($pStmt, SQLSRV_FETCH_ASSOC)) {

            $id_piece     = (int)$piece['id_piece'];
            $panjang_jadi = (float)$piece['panjang_akhir_conv'];

            // ===== WAJIB PAKAI *_conv =====
            $standart = (float)$piece['standart_potong_conv'];
            $min      = (float)$piece['min_potong_conv'];
            $max      = (float)$piece['max_potong_conv'];

            // ===== HITUNG SUSUT_CONV =====
            $panjang_awal_conv = (float)$piece['panjang_awal'] * $factor;
            $panjang_akhir_conv = (float)$piece['panjang_akhir_conv'];
            $susut_conv = $panjang_awal_conv - $panjang_akhir_conv;

            /* ================= CACAT ================= */
            $cacatStmt = sqlsrv_query($conn, "
                SELECT dari, sampai, status_cacat
                FROM cl_cutting_cacat
                WHERE id_piece = ?
                ORDER BY dari
            ", [$id_piece]);

            $cacat_list = [];
            while ($c = sqlsrv_fetch_array($cacatStmt, SQLSRV_FETCH_ASSOC)) {

                $status = strtoupper(trim($c['status_cacat']));
                if ($status === 'BS KG' || $status === 'BSKG') {
                    $status = 'B3';
                }

                $cacat_list[] = [
                    'dari'   => (float)$c['dari'],
                    'sampai' => (float)$c['sampai'],
                    'status' => $status
                ];
            }

            /* ================= BASE CUTTING ================= */
            $result_cutting = [];
            $current = 0.0;

            while ($current < $panjang_jadi) {

                $end_cut = min($current + $standart, $panjang_jadi);
                $is_defect = false;

                foreach ($cacat_list as $cacat) {

                    if ($cacat['dari'] < $end_cut && $cacat['sampai'] > $current) {
                        $is_defect = true;

                        if ($current < $cacat['dari']) {
                            $valid_end = min($cacat['dari'], $end_cut);
                            $hasil = $valid_end - $current;

                            $result_cutting[] = [
                                'start_cutting' => $current,
                                'end_cutting'   => $valid_end,
                                'hasil_cutting' => $hasil,
                                'kategori_kain' => determineCategory($hasil, $standart, $min)
                            ];
                        }

                        $result_cutting[] = [
                            'start_cutting' => $cacat['dari'],
                            'end_cutting'   => $cacat['sampai'],
                            'hasil_cutting' => $cacat['sampai'] - $cacat['dari'],
                            'kategori_kain' => $cacat['status']
                        ];

                        $current = $cacat['sampai'];
                        break;
                    }
                }

                if (!$is_defect) {
                    $hasil = $end_cut - $current;

                    $result_cutting[] = [
                        'start_cutting' => $current,
                        'end_cutting'   => $end_cut,
                        'hasil_cutting' => $hasil,
                        'kategori_kain' => determineCategory($hasil, $standart, $min)
                    ];

                    $current = $end_cut;
                }
            }

            /* ================= REDISTRIBUSI A2 dan B3 ================= */
            $final = [];
            $i = 0;

            while ($i < count($result_cutting)) {
                $current_cut = $result_cutting[$i];
                $katNow = isset($current_cut['kategori_kain']) ? strtoupper(trim($current_cut['kategori_kain'])) : '';
                // Normalize B3 untuk comparison
                $katNow = (strcasecmp(str_replace(' ','',trim($katNow)), 'BSKG') === 0) ? 'B3' : $katNow;

                if ($katNow === "A2" || $katNow === "B3") {
                    $j = $i - 1;
                    $remaining_length = (float)$current_cut['hasil_cutting'];

                    while ($j >= 0 && $remaining_length > 0) {
                        if (isset($final[$j])) {
                            $previous_cut = &$final[$j];
                            $prevKat = isset($previous_cut['kategori_kain']) ? strtoupper(trim($previous_cut['kategori_kain'])) : '';
                            // Normalize B3 untuk comparison
                            $prevKat = (strcasecmp(str_replace(' ','',trim($prevKat)), 'BSKG') === 0) ? 'B3' : $prevKat;
                            
                            // Jangan distribusikan ke B1 atau B2
                            if ($prevKat === "B1" || $prevKat === "B2") {
                                break;
                            }

                            $available_space = $max - $previous_cut['hasil_cutting'];
                            if ($available_space > 0) {
                                $distributed_length = min($available_space, $remaining_length);
                                $new_hasil = $previous_cut['hasil_cutting'] + $distributed_length;
                                
                                // Validasi: ensure tidak melebihi max_potong_conv
                                if ($new_hasil <= $max) {
                                    $previous_cut['hasil_cutting'] = $new_hasil;
                                    $previous_cut['end_cutting']    += $distributed_length;
                                    $remaining_length               -= $distributed_length;
                                    $previous_cut['kategori_kain'] = determineCategory($previous_cut['hasil_cutting'], $standart, $min);
                                }
                            }
                        }
                        $j--;
                    }

                    if ($remaining_length > 0) {
                        $current_cut['start_cutting'] = isset($final[count($final) - 1]['end_cutting'])
                            ? $final[count($final) - 1]['end_cutting']
                            : $current_cut['start_cutting'];

                        $current_cut['hasil_cutting'] = $remaining_length;
                        $current_cut['end_cutting']   = $current_cut['start_cutting'] + $remaining_length;
                        $current_cut['kategori_kain'] = determineCategory($remaining_length, $standart, $min);
                        $final[] = $current_cut;
                    }
                } else {
                    $current_cut['start_cutting'] = isset($final[count($final) - 1]['end_cutting'])
                        ? $final[count($final) - 1]['end_cutting']
                        : $current_cut['start_cutting'];
                    $final[] = $current_cut;
                }
                $i++;
            }

            /* ================= RENUMBER ================= */
            $pos = 0.0;
            foreach ($final as &$f) {
                $f['start_cutting'] = $pos;
                $f['end_cutting']   = $pos + (float)$f['hasil_cutting'];
                $pos = $f['end_cutting'];
            }
            unset($f);

            /* ================= SAVE ================= */
            sqlsrv_query($conn, "
                DELETE FROM cl_cutting_process
                WHERE id_piece = ?
            ", [$id_piece]);

            $no = 1;
            foreach ($final as $r) {

                // ===== HASIL KONVERSI =====
                $start_pos_conv  = $r['start_cutting'] * $factor;
                $end_pos_conv    = $r['end_cutting'] * $factor;
                $hasil_conv      = $r['hasil_cutting'] * $factor;

                sqlsrv_query($conn, "
                    INSERT INTO cl_cutting_process
                    (
                        id_piece, process_no,
                        start_pos, end_pos, hasil_cutting,
                        start_pos_conv, end_pos_conv, hasil_cutting_conv,
                        kategori,
                        uom_hasil_cutting, uom_conv,
                        created_by, created_date
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())
                ", [
                    $id_piece,
                    $no++,
                    $r['start_cutting'],
                    $r['end_cutting'],
                    $r['hasil_cutting'],
                    $start_pos_conv,
                    $end_pos_conv,
                    $hasil_conv,
                    $r['kategori_kain'],
                    $uom_hasil_cutting,
                    $uom_conv,
                    $_SESSION['UserName'] ?? 'SYSTEM'
                ]);
            }

            /* ================= SUMMARY KATEGORI ================= */
            sqlsrv_query($conn, "
                DELETE FROM cl_cutting_summary
                WHERE id_piece = ?
            ", [$id_piece]);

            $summaryStmt = sqlsrv_query($conn, "
                SELECT 
                    kategori,
                    COUNT(*) as total_pcs,
                    SUM(hasil_cutting) as total_panjang
                FROM cl_cutting_process
                WHERE id_piece = ?
                GROUP BY kategori
            ", [$id_piece]);

            while ($summary = sqlsrv_fetch_array($summaryStmt, SQLSRV_FETCH_ASSOC)) {
                sqlsrv_query($conn, "
                    INSERT INTO cl_cutting_summary
                    (id_piece, kategori, total_pcs, total_panjang, created_by, created_date)
                    VALUES (?, ?, ?, ?, ?, GETDATE())
                ", [
                    $id_piece,
                    $summary['kategori'],
                    (int)$summary['total_pcs'],
                    (float)$summary['total_panjang'],
                    $_SESSION['UserName'] ?? 'SYSTEM'
                ]);
            }
        }

        sqlsrv_query($conn, "
            UPDATE cl_cutting_piece
            SET susut_conv = panjang_awal * ? - panjang_akhir_conv
            WHERE id_header = ?
        ", [$factor, $id_header]);

        sqlsrv_query($conn, "
            UPDATE cl_cutting_header
            SET processed = 1,
                updated_by = ?,
                updated_date = GETDATE()
            WHERE id_header = ?
        ", [$_SESSION['UserName'] ?? 'SYSTEM', $id_header]);

        $processed++;
    }

    sqlsrv_commit($conn);
    echo json_encode([
        'status'    => 'ok',
        'processed' => $processed
    ]);

} catch (Throwable $e) {
    sqlsrv_rollback($conn);
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
