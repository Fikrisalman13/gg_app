<?php
/**
 * ============================================
 * CUTTING CALCULATION HELPER FUNCTIONS
 * ============================================
 * 
 * Fungsi-fungsi untuk menghitung cutting list dengan toleransi
 * Menangani konversi UOM dan kalkulasi cutting berdasarkan Type Counter
 */

/**
 * Konversi toleransi dari CM ke unit target
 * 
 * @param float $toleransi_cm Toleransi dalam satuan CM
 * @param string $target_uom Unit target (M atau Y)
 * @return float Toleransi dalam unit target
 */
function convertToleranceCMtoUOM($toleransi_cm, $target_uom) {
    if ($target_uom === 'M') {
        // 1 CM = 0.01 M
        return $toleransi_cm * 0.01;
    } elseif ($target_uom === 'Y') {
        // 1 CM = 0.01 / 0.9144 Y = 0.010936 Y
        return $toleransi_cm * (0.01 / 0.9144);
    }
    return $toleransi_cm;
}

/**
 * Hitung faktor konversi dari UOM CP ke UOM Counter
 * 
 * @param string $uom_cp Unit of Measure untuk potongan (M atau Y)
 * @param string $uom_counter Unit of Measure untuk mesin/counter (M atau Y)
 * @return float Faktor konversi
 */
function getConversionFactor($uom_cp, $uom_counter) {
    if ($uom_cp === $uom_counter) {
        return 1.0;
    }
    
    if ($uom_cp === 'M' && $uom_counter === 'Y') {
        // Konversi Meter ke Yard: bagi dengan 0.9144
        return 1 / 0.9144;
    } elseif ($uom_cp === 'Y' && $uom_counter === 'M') {
        // Konversi Yard ke Meter: kalikan dengan 0.9144
        return 0.9144;
    }
    
    return 1.0;
}

/**
 * Konversi panjang dari unit source ke unit target
 * 
 * @param float $panjang Panjang dalam unit source
 * @param string $source_uom Unit source (M atau Y)
 * @param string $target_uom Unit target (M atau Y)
 * @return float Panjang dalam unit target
 */
function convertPanjang($panjang, $source_uom, $target_uom) {
    $factor = getConversionFactor($source_uom, $target_uom);
    return $panjang * $factor;
}

/**
 * Cek apakah ada overlap antara 2 range
 * 
 * @param float $start1 Awal range 1
 * @param float $end1 Akhir range 1
 * @param float $start2 Awal range 2
 * @param float $end2 Akhir range 2
 * @return bool True jika overlap
 */
function isRangeOverlap($start1, $end1, $start2, $end2) {
    return $start1 < $end2 && $start2 < $end1;
}

/**
 * Hitung cutting dari kain dengan mempertimbangkan cacat
 * 
 * @param float $panjang_awal Panjang awal dalam satuan kalkulasi
 * @param float $panjang_akhir Panjang akhir dalam satuan kalkulasi
 * @param float $std_dengan_tol Standard potongan + toleransi dalam satuan kalkulasi
 * @param float $min Minimal potongan dalam satuan kalkulasi
 * @param float $max Maximal potongan dalam satuan kalkulasi
 * @param array $cacat_list Array of cacat dengan format: ['dari' => float, 'sampai' => float, 'status' => string]
 * @return array Hasil cutting dengan format:
 *         [
 *           'start_pos' => float,
 *           'end_pos' => float,
 *           'hasil_cutting' => float,
 *           'kategori' => string
 *         ]
 */
function calculateCuttingWithTolerance($panjang_awal, $panjang_akhir, $std_dengan_tol, $min, $max, $cacat_list = []) {
    $result = [];
    $current_pos = $panjang_awal;
    
    while ($current_pos < $panjang_akhir) {
        $end_pos = min($current_pos + $std_dengan_tol, $panjang_akhir);
        $is_defect_found = false;
        
        // Cek apakah ada cacat dalam range [current_pos, end_pos]
        foreach ($cacat_list as $cacat) {
            $cacat_dari = floatval($cacat['dari']);
            $cacat_sampai = floatval($cacat['sampai']);
            
            if (isRangeOverlap($current_pos, $end_pos, $cacat_dari, $cacat_sampai)) {
                $is_defect_found = true;
                
                // Potong bagian sebelum cacat
                if ($current_pos < $cacat_dari) {
                    $valid_end = min($cacat_dari, $end_pos);
                    $hasil = $valid_end - $current_pos;
                    
                    $kategori = determineCategory($hasil, $std_dengan_tol, $min);
                    
                    $result[] = [
                        'start_pos' => $current_pos,
                        'end_pos' => $valid_end,
                        'hasil_cutting' => $hasil,
                        'kategori' => $kategori
                    ];
                }
                
                // Catat cacat
                $result[] = [
                    'start_pos' => max($cacat_dari, $current_pos),
                    'end_pos' => min($cacat_sampai, $end_pos),
                    'hasil_cutting' => min($cacat_sampai, $end_pos) - max($cacat_dari, $current_pos),
                    'kategori' => $cacat['status'] ?? 'CACAT'
                ];
                
                $current_pos = $cacat_sampai;
                break;
            }
        }
        
        // Jika tidak ada cacat dalam range
        if (!$is_defect_found) {
            $hasil = $end_pos - $current_pos;
            $kategori = determineCategory($hasil, $std_dengan_tol, $min);
            
            $result[] = [
                'start_pos' => $current_pos,
                'end_pos' => $end_pos,
                'hasil_cutting' => $hasil,
                'kategori' => $kategori
            ];
            
            $current_pos = $end_pos;
        }
    }
    
    return $result;
}

/**
 * Hitung ringkasan jumlah pcs per kategori
 * 
 * @param array $cutting_result Hasil dari calculateCuttingWithTolerance()
 * @return array Ringkasan dalam format: ['KATEGORI' => ['pcs' => int, 'total_panjang' => float]]
 */
function calculateCuttingSummary($cutting_result) {
    $summary = [];
    
    foreach ($cutting_result as $item) {
        $kategori = $item['kategori'];
        $panjang = $item['hasil_cutting'];
        
        if (!isset($summary[$kategori])) {
            $summary[$kategori] = [
                'pcs' => 0,
                'total_panjang' => 0,
                'items' => []
            ];
        }
        
        $summary[$kategori]['pcs']++;
        $summary[$kategori]['total_panjang'] += $panjang;
        $summary[$kategori]['items'][] = [
            'start' => $item['start_pos'],
            'end' => $item['end_pos'],
            'hasil' => $panjang
        ];
    }
    
    return $summary;
}

/**
 * Main function - Hitung cutting berdasarkan header dan piece configuration
 * 
 * @param array $config Konfigurasi dengan keys:
 *        - panjang_awal (float): Panjang awal dalam Meter (ALWAYS in Meter)
 *        - panjang_akhir (float): Panjang akhir dalam Meter (ALWAYS in Meter)
 *        - std (float): Standard potong dalam UOM CP
 *        - min (float): Minimal potong dalam UOM CP
 *        - max (float): Maximal potong dalam UOM CP
 *        - toleransi (float): Toleransi dalam CM
 *        - uom_cp (string): Unit of Measure untuk potongan (M atau Y)
 *        - type_counter (string): Unit of Measure untuk counter/mesin (M atau Y)
 *        - cacat_list (array): Data cacat dengan format ['dari' => float, 'sampai' => float, 'status' => string]
 * @return array Hasil cutting dengan summary
 */
function performCutting($config) {
    // Extract config
    $panjang_awal = floatval($config['panjang_awal']);
    $panjang_akhir = floatval($config['panjang_akhir']);
    $std = floatval($config['std']);
    $min = floatval($config['min']);
    $max = floatval($config['max']);
    $toleransi_cm = floatval($config['toleransi'] ?? 0);
    $uom_cp = strval($config['uom_cp']);
    $type_counter = strval($config['type_counter']);
    $cacat_list = isset($config['cacat_list']) ? (array)$config['cacat_list'] : [];
    
    // Step 1: Konversi toleransi dari CM ke UOM CP
    $toleransi_in_uom_cp = convertToleranceCMtoUOM($toleransi_cm, $uom_cp);
    
    // Step 2: Hitung panjang potongan dengan toleransi
    $std_dengan_tol = $std + $toleransi_in_uom_cp;
    
    // Step 3: Konversi ke unit kalkulasi berdasarkan Type Counter
    $conversion_factor = getConversionFactor($uom_cp, $type_counter);
    
    // Konversi panjang (dari Meter ke unit kalkulasi jika perlu)
    // Panjang input selalu dalam Meter, konversi ke satuan kalkulasi
    if ($type_counter === 'Y') {
        $panjang_awal_calc = convertPanjang($panjang_awal, 'M', 'Y');
        $panjang_akhir_calc = convertPanjang($panjang_akhir, 'M', 'Y');
    } else {
        $panjang_awal_calc = $panjang_awal;
        $panjang_akhir_calc = $panjang_akhir;
    }
    
    // Konversi parameter cutting
    $std_calc = $std_dengan_tol * $conversion_factor;
    $min_calc = $min * $conversion_factor;
    $max_calc = $max * $conversion_factor;
    
    // Konversi cacat ke unit kalkulasi
    $cacat_list_calc = [];
    foreach ($cacat_list as $cacat) {
        $cacat_dari = floatval($cacat['dari']);
        $cacat_sampai = floatval($cacat['sampai']);
        
        // Konversi dari Meter ke unit kalkulasi
        if ($type_counter === 'Y') {
            $cacat_dari_calc = convertPanjang($cacat_dari, 'M', 'Y');
            $cacat_sampai_calc = convertPanjang($cacat_sampai, 'M', 'Y');
        } else {
            $cacat_dari_calc = $cacat_dari;
            $cacat_sampai_calc = $cacat_sampai;
        }
        
        $cacat_list_calc[] = [
            'dari' => $cacat_dari_calc,
            'sampai' => $cacat_sampai_calc,
            'status' => $cacat['status'] ?? 'CACAT'
        ];
    }
    
    // Step 4: Hitung cutting
    $cutting_result = calculateCuttingWithTolerance(
        $panjang_awal_calc,
        $panjang_akhir_calc,
        $std_calc,
        $min_calc,
        $max_calc,
        $cacat_list_calc
    );
    
    // Step 5: Hitung summary
    $summary = calculateCuttingSummary($cutting_result);
    
    return [
        'config' => [
            'panjang_awal' => $panjang_awal,
            'panjang_akhir' => $panjang_akhir,
            'std' => $std,
            'toleransi_cm' => $toleransi_cm,
            'std_dengan_tol' => $std_dengan_tol,
            'uom_cp' => $uom_cp,
            'type_counter' => $type_counter,
            'conversion_factor' => $conversion_factor
        ],
        'cutting' => $cutting_result,
        'summary' => $summary,
        'total_pcs' => array_sum(array_column($summary, 'pcs')),
        'total_panjang' => array_sum(array_column($summary, 'total_panjang'))
    ];
}

/**
 * Determine kategori kain berdasarkan hasil cutting
 */
function determineCategory($hasil_cutting, $standart_potong, $min_potong) {
    $hasil_rounded = round($hasil_cutting, 2);
    $standart_rounded = round($standart_potong, 2);
    
    if ($hasil_rounded == $standart_rounded) {
        return "A1 Standart";
    } elseif ($hasil_cutting >= 3 && $hasil_cutting < $min_potong) {
        return "A2";
    } elseif ($hasil_cutting <= 3) {
        return "BS KG";
    } else {
        return "A1 Non Standart";
    }
}

/**
 * Process cutting untuk satu piece dengan redistribusi A2/BS KG
 */
function processCutting($panjang_jadi, $standart_potong, $min_potong, $max_potong, $cacat_list = []) {
    $result_cutting = [];
    $current_position = 0;
    
    // ============ STEP 1: Generate cutting dengan cacat handling ============
    while ($current_position < $panjang_jadi) {
        $end_cutting = min($current_position + $standart_potong, $panjang_jadi);
        $kategori_kain = "A1 Standart";
        $is_defect_in_range = false;

        // Cek cacat
        if (is_array($cacat_list) && !empty($cacat_list)) {
            foreach ($cacat_list as $cacat) {
                $cacat_dari = floatval($cacat['dari']);
                $cacat_sampai = floatval($cacat['sampai']);
                
                if ($cacat_dari < $end_cutting && $cacat_sampai > $current_position) {
                    $is_defect_in_range = true;

                    // Potong sebelum cacat
                    if ($current_position < $cacat_dari) {
                        $valid_end_cutting = min($cacat_dari, $end_cutting);
                        $hasil_cutting = $valid_end_cutting - $current_position;
                        $kategori_kain = determineCategory($hasil_cutting, $standart_potong, $min_potong);

                        $result_cutting[] = [
                            'start_cutting' => $current_position,
                            'end_cutting' => $valid_end_cutting,
                            'hasil_cutting' => $hasil_cutting,
                            'kategori_kain' => $kategori_kain,
                            'is_cacat' => false
                        ];
                    }

                    // Tambahkan cacat
                    $status = (string)$cacat['status_kesalahan'] ?? $cacat['status'] ?? 'B1';
                    if (strcasecmp(trim($status), 'BS KG') === 0 || strcasecmp(str_replace(' ','',trim($status)), 'BSKG') === 0) {
                        $status = 'B3';
                    }

                    $result_cutting[] = [
                        'start_cutting' => $cacat_dari,
                        'end_cutting' => $cacat_sampai,
                        'hasil_cutting' => $cacat_sampai - $cacat_dari,
                        'kategori_kain' => $status,
                        'is_cacat' => true
                    ];

                    $current_position = $cacat_sampai;
                    break;
                }
            }
        }

        // Jika tidak ada cacat dalam rentang
        if (!$is_defect_in_range) {
            $hasil_cutting = $end_cutting - $current_position;
            $kategori_kain = determineCategory($hasil_cutting, $standart_potong, $min_potong);

            $result_cutting[] = [
                'start_cutting' => $current_position,
                'end_cutting' => $end_cutting,
                'hasil_cutting' => $hasil_cutting,
                'kategori_kain' => $kategori_kain,
                'is_cacat' => false
            ];

            $current_position = $end_cutting;
        }
    }

    // ============ STEP 2: Redistribusi A2 dan BS KG ============
    $final_result_cutting = [];
    $i = 0;

    while ($i < count($result_cutting)) {
        $current_cut = $result_cutting[$i];
        $katNow = isset($current_cut['kategori_kain']) ? strtoupper(trim($current_cut['kategori_kain'])) : '';
        // Normalize BS KG menjadi B3 untuk comparison
        $katNow = (strcasecmp(str_replace(' ','',trim($katNow)), 'BSKG') === 0) ? 'B3' : $katNow;

        if ($katNow === "A2" || $katNow === "B3") {
            $j = $i - 1;
            $remaining_length = (float)$current_cut['hasil_cutting'];

            while ($j >= 0 && $remaining_length > 0) {
                if (isset($final_result_cutting[$j])) {
                    $previous_cut = &$final_result_cutting[$j];
                    $prevKat = isset($previous_cut['kategori_kain']) ? strtoupper(trim($previous_cut['kategori_kain'])) : '';
                    // Normalize BS KG menjadi B3 untuk comparison
                    $prevKat = (strcasecmp(str_replace(' ','',trim($prevKat)), 'BSKG') === 0) ? 'B3' : $prevKat;
                    
                    // Jangan distribusikan ke B1 atau B2
                    if ($prevKat === "B1" || $prevKat === "B2") {
                        break;
                    }

                    $available_space = $max_potong - $previous_cut['hasil_cutting'];
                    if ($available_space > 0) {
                        $distributed_length = min($available_space, $remaining_length);
                        $new_hasil = $previous_cut['hasil_cutting'] + $distributed_length;
                        
                        // Validasi: ensure tidak melebihi max_potong
                        if ($new_hasil <= $max_potong) {
                            $previous_cut['hasil_cutting'] = $new_hasil;
                            $previous_cut['end_cutting']    += $distributed_length;
                            $remaining_length               -= $distributed_length;
                            $previous_cut['kategori_kain'] = determineCategory($previous_cut['hasil_cutting'], $standart_potong, $min_potong);
                        }
                    }
                }
                $j--;
            }

            if ($remaining_length > 0) {
                $current_cut['start_cutting'] = isset($final_result_cutting[count($final_result_cutting) - 1]['end_cutting'])
                    ? $final_result_cutting[count($final_result_cutting) - 1]['end_cutting']
                    : $current_cut['start_cutting'];

                $current_cut['hasil_cutting'] = $remaining_length;
                $current_cut['end_cutting']   = $current_cut['start_cutting'] + $remaining_length;
                $current_cut['kategori_kain'] = determineCategory($remaining_length, $standart_potong, $min_potong);
                $final_result_cutting[] = $current_cut;
            }
        } else {
            $current_cut['start_cutting'] = isset($final_result_cutting[count($final_result_cutting) - 1]['end_cutting'])
                ? $final_result_cutting[count($final_result_cutting) - 1]['end_cutting']
                : $current_cut['start_cutting'];
            $final_result_cutting[] = $current_cut;
        }
        $i++;
    }

    // ============ STEP 3: Update start_cutting dan end_cutting ============
    $previous_end_cutting = 0;
    foreach ($final_result_cutting as &$cut) {
        $cut['start_cutting'] = $previous_end_cutting;
        $cut['end_cutting'] = $cut['start_cutting'] + $cut['hasil_cutting'];
        $previous_end_cutting = $cut['end_cutting'];
    }
    unset($cut);

    return $final_result_cutting;
}

/**
 * Hitung summary kategori kain
 */
function calculateSummary($final_result_cutting) {
    $kategori_count = [];
    $total_panjang = 0;
    $total_pcs = 0;

    foreach ($final_result_cutting as $result) {
        $kategori = $result['kategori_kain'];
        $panjang = $result['hasil_cutting'];

        if (!isset($kategori_count[$kategori])) {
            $kategori_count[$kategori] = ['pcs' => 0, 'panjang' => 0];
        }

        $kategori_count[$kategori]['pcs'] += 1;
        $kategori_count[$kategori]['panjang'] += $panjang;

        $total_panjang += $panjang;
        $total_pcs += 1;
    }

    return [
        'kategori' => $kategori_count,
        'total_pcs' => $total_pcs,
        'total_panjang' => $total_panjang
    ];
}

?>
