<?php

if (!function_exists('normalizeDateInputRekapBiayaEnergi')) {
    function normalizeDateInputRekapBiayaEnergi($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        if (preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $value, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        return '';
    }
}

if (!function_exists('toDateKeyRekapBiayaEnergi')) {
    function toDateKeyRekapBiayaEnergi($value)
    {
        if ($value instanceof DateTime) {
            return $value->format('Y-m-d');
        }

        $ts = strtotime((string)$value);
        if ($ts === false) {
            return '';
        }

        return date('Y-m-d', $ts);
    }
}

if (!function_exists('runDailySumMapRekapBiayaEnergi')) {
    function runDailySumMapRekapBiayaEnergi($conn, $sql, array $params, &$errorMsg)
    {
        $map = [];

        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            $err = sqlsrv_errors(SQLSRV_ERR_ERRORS);
            $msg = 'Gagal mengambil data rekap biaya energi.';
            if (!empty($err) && isset($err[0]['message'])) {
                $msg .= ' ' . $err[0]['message'];
            }
            $errorMsg = $msg;
            return $map;
        }

        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $dateKey = toDateKeyRekapBiayaEnergi($r['tanggal'] ?? null);
            if ($dateKey === '') {
                continue;
            }

            $nilai = is_numeric($r['nilai'] ?? null) ? (float)$r['nilai'] : 0.0;
            if (!isset($map[$dateKey])) {
                $map[$dateKey] = 0.0;
            }
            $map[$dateKey] += $nilai;
        }

        sqlsrv_free_stmt($stmt);
        return $map;
    }
}

if (!function_exists('fetchBiayaListrikRekap')) {
    function fetchBiayaListrikRekap($conn, $start, $end, &$errorMsg)
    {
        $map = [];
        $kwh1Map = [];
        $kwh2Map = [];

        // 1. Cek dan Ambil Data dari kwh_listrik (atau kwh_listrik_harian)
        $cand1 = ['kwh_listrik', 'kwh_listrik_harian'];
        $table1Name = null;
        foreach ($cand1 as $t) {
            $stmtCheck = sqlsrv_query($conn, "SELECT TOP 1 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='dbo' AND TABLE_NAME=?", [$t]);
            if ($stmtCheck && sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC)) {
                $table1Name = "[dbo].[" . $t . "]";
                sqlsrv_free_stmt($stmtCheck);
                break;
            }
            if ($stmtCheck) {
                sqlsrv_free_stmt($stmtCheck);
            }
        }

        if ($table1Name) {
            $sql1 = "SELECT * FROM {$table1Name} WHERE CAST(tanggal AS DATE) BETWEEN ? AND ?";
            $stmt1 = sqlsrv_query($conn, $sql1, [$start, $end]);
            if ($stmt1 !== false) {
                while ($r = sqlsrv_fetch_array($stmt1, SQLSRV_FETCH_ASSOC)) {
                    $dateKey = toDateKeyRekapBiayaEnergi($r['tanggal'] ?? null);
                    if ($dateKey === '') {
                        continue;
                    }

                    $tot = isset($r['total_biaya']) && is_numeric($r['total_biaya']) ? (float)$r['total_biaya'] : 0.0;
                    $actom = isset($r['biaya_21ton_actom']) && is_numeric($r['biaya_21ton_actom']) ? (float)$r['biaya_21ton_actom'] : 0.0;

                    $sumBiaya = 0.0;
                    $hasBiayaCols = false;
                    foreach (['biaya_ipab', 'biaya_jineng', 'biaya_xineng', 'biaya_20ton', 'biaya_20ton_baru', 'biaya_ipal'] as $bCol) {
                        if (isset($r[$bCol]) && is_numeric($r[$bCol])) {
                            $sumBiaya += (float)$r[$bCol];
                            $hasBiayaCols = true;
                        }
                    }
                    $sumBiaya += $actom;

                    $val = $tot;
                    if ($actom > 0 && ($tot + $actom) > $val) {
                        $val = $tot + $actom;
                    }
                    if ($hasBiayaCols && $sumBiaya > $val) {
                        $val = $sumBiaya;
                    }

                    $kwh1Map[$dateKey] = $val;
                }
                sqlsrv_free_stmt($stmt1);
            }
        }

        // 2. Cek dan Ambil Data dari kwh_listrik2
        $table2Name = null;
        $stmtCheck2 = sqlsrv_query($conn, "SELECT TOP 1 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='dbo' AND TABLE_NAME='kwh_listrik2'");
        if ($stmtCheck2 && sqlsrv_fetch_array($stmtCheck2, SQLSRV_FETCH_ASSOC)) {
            $table2Name = "[dbo].[kwh_listrik2]";
            sqlsrv_free_stmt($stmtCheck2);
        } elseif ($stmtCheck2) {
            sqlsrv_free_stmt($stmtCheck2);
        }

        if ($table2Name) {
            $sql2 = "SELECT * FROM {$table2Name} WHERE CAST(tanggal AS DATE) BETWEEN ? AND ?";
            $stmt2 = sqlsrv_query($conn, $sql2, [$start, $end]);
            if ($stmt2 !== false) {
                while ($r = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC)) {
                    $dateKey = toDateKeyRekapBiayaEnergi($r['tanggal'] ?? null);
                    if ($dateKey === '') {
                        continue;
                    }

                    $tot = isset($r['total_biaya']) && is_numeric($r['total_biaya']) ? (float)$r['total_biaya'] : 0.0;
                    $sumBiaya = 0.0;
                    $hasBiayaCols = false;
                    foreach (['biaya_ipab', 'biaya_jineng', 'biaya_xineng', 'biaya_20ton_l', 'biaya_20ton_baru', 'biaya_ipal', 'biaya_21ton_actom'] as $bCol) {
                        if (isset($r[$bCol]) && is_numeric($r[$bCol])) {
                            $sumBiaya += (float)$r[$bCol];
                            $hasBiayaCols = true;
                        }
                    }
                    $val = ($tot > 0) ? $tot : ($hasBiayaCols ? $sumBiaya : 0.0);
                    $kwh2Map[$dateKey] = $val;
                }
                sqlsrv_free_stmt($stmt2);
            }
        }

        // 3. Saling Melengkapi: Ambil dari kwh_listrik jika ada datanya (> 0), jika tidak ada/0 ambil dari kwh_listrik2
        $allDates = array_unique(array_merge(array_keys($kwh1Map), array_keys($kwh2Map)));
        foreach ($allDates as $d) {
            if (isset($kwh1Map[$d]) && $kwh1Map[$d] > 0) {
                $map[$d] = $kwh1Map[$d];
            } elseif (isset($kwh2Map[$d]) && $kwh2Map[$d] > 0) {
                $map[$d] = $kwh2Map[$d];
            } elseif (isset($kwh1Map[$d])) {
                $map[$d] = $kwh1Map[$d];
            } elseif (isset($kwh2Map[$d])) {
                $map[$d] = $kwh2Map[$d];
            }
        }

        return $map;
    }
}

if (!function_exists('buildRekapBiayaEnergiData')) {
    function buildRekapBiayaEnergiData($conn, $start, $end, &$errorMsg)
    {
        $result = [
            'dates' => [],
            'rows' => [],
            'totals' => [],
            'averages' => [],
            'row_count' => 0,
        ];

        $valueKeys = [
            'biaya_listrik',
            'biaya_kimia_ipab',
            'biaya_kimia_ipal',
            'biaya_kimia_boiler',
            'biaya_kimia_weaving',
            'biaya_bb_wuxi',
            'biaya_bb_oil_xineng',
            'biaya_bb_20t_lama',
            'biaya_bb_20t_baru_longchuan',
            'biaya_bb_21t_actom',
            'biaya_bb_jineng',
            'biaya_lpg_skid_tank',
        ];

        $maps = [];

        // Mengambil biaya listrik dari kwh_listrik, jika tidak ada fallback ke kwh_listrik2 (saling melengkapi)
        $maps['biaya_listrik'] = fetchBiayaListrikRekap($conn, $start, $end, $errorMsg);
        if ($errorMsg !== '') {
            return $result;
        }

        $sourceQueries = [
            'biaya_kimia_ipab' => "SELECT CAST(h.tanggal AS DATE) AS tanggal,
                                          SUM(COALESCE(NULLIF(h.biaya_rp, 0), (COALESCE(h.pakai_kg, 0) * COALESCE(h.harga_rp, 0)), 0)) AS nilai
                                   FROM dbo.kimia_ipab_harian h
                                   WHERE CAST(h.tanggal AS DATE) BETWEEN ? AND ?
                                   GROUP BY CAST(h.tanggal AS DATE)",
            'biaya_kimia_ipal' => "SELECT CAST(h.tanggal AS DATE) AS tanggal,
                                          SUM(COALESCE(NULLIF(h.biaya_rp, 0), (COALESCE(h.pakai_kg, 0) * COALESCE(h.harga_rp, 0)), 0)) AS nilai
                                   FROM dbo.kimia_ipal_harian h
                                   WHERE CAST(h.tanggal AS DATE) BETWEEN ? AND ?
                                   GROUP BY CAST(h.tanggal AS DATE)",
            'biaya_kimia_boiler' => "SELECT CAST(h.tanggal AS DATE) AS tanggal,
                                            SUM(COALESCE(NULLIF(h.biaya_rp, 0), (COALESCE(h.pakai_kg, 0) * COALESCE(h.harga_rp, 0)), 0)) AS nilai
                                     FROM dbo.kimia_boiler_harian h
                                     WHERE CAST(h.tanggal AS DATE) BETWEEN ? AND ?
                                     GROUP BY CAST(h.tanggal AS DATE)",
            'biaya_kimia_weaving' => "SELECT CAST(h.tanggal AS DATE) AS tanggal,
                                             SUM(COALESCE(NULLIF(h.biaya_rp, 0), (COALESCE(h.pakai_kg, 0) * COALESCE(h.harga_rp, 0)), 0)) AS nilai
                                      FROM dbo.kimia_weaving_harian h
                                      WHERE CAST(h.tanggal AS DATE) BETWEEN ? AND ?
                                      GROUP BY CAST(h.tanggal AS DATE)",
            'biaya_bb_wuxi' => "SELECT CAST(h.tanggal AS DATE) AS tanggal,
                                       SUM(COALESCE(
                                           NULLIF(h.total_biaya_boiler_wuxi_rp, 0),
                                           NULLIF(h.biaya_rp, 0),
                                           (COALESCE(h.pemakaian_kg, 0) * COALESCE(h.harga_rp_per_kg, 0)),
                                           0
                                       )) AS nilai
                                FROM dbo.bb_boiler_wuxi_harian h
                                WHERE CAST(h.tanggal AS DATE) BETWEEN ? AND ?
                                GROUP BY CAST(h.tanggal AS DATE)",
            'biaya_bb_oil_xineng' => "SELECT CAST(h.tanggal AS DATE) AS tanggal,
                                             SUM(COALESCE(
                                                 NULLIF(h.total_biaya_boiler_oil_rp, 0),
                                                 NULLIF(h.biaya_rp, 0),
                                                 (COALESCE(h.pemakaian_kg, 0) * COALESCE(h.harga_rp_per_kg, 0)),
                                                 0
                                             )) AS nilai
                                      FROM dbo.bb_boiler_oil_xineng_harian h
                                      WHERE CAST(h.tanggal AS DATE) BETWEEN ? AND ?
                                      GROUP BY CAST(h.tanggal AS DATE)",
            'biaya_bb_20t_lama' => "SELECT CAST(h.tanggal AS DATE) AS tanggal,
                                           SUM(COALESCE(
                                               NULLIF(h.total_biaya_boiler_rp, 0),
                                               NULLIF(h.biaya_rp, 0),
                                               (COALESCE(h.pemakaian_kg, 0) * COALESCE(h.harga_rp_per_kg, 0)),
                                               0
                                           )) AS nilai
                                    FROM dbo.bb_20t_lama_harian h
                                    WHERE CAST(h.tanggal AS DATE) BETWEEN ? AND ?
                                    GROUP BY CAST(h.tanggal AS DATE)",
            'biaya_bb_20t_baru_longchuan' => "SELECT CAST(h.tanggal AS DATE) AS tanggal,
                                                     SUM(COALESCE(
                                                         NULLIF(h.total_biaya_boiler_rp, 0),
                                                         NULLIF(h.biaya_rp, 0),
                                                         (COALESCE(h.pemakaian_kg, 0) * COALESCE(h.harga_rp_per_kg, 0)),
                                                         0
                                                     )) AS nilai
                                              FROM dbo.bb_20tbaru_longchuan_harian h
                                              WHERE CAST(h.tanggal AS DATE) BETWEEN ? AND ?
                                              GROUP BY CAST(h.tanggal AS DATE)",
            'biaya_bb_21t_actom' => "SELECT CAST(h.tanggal AS DATE) AS tanggal,
                                            SUM(COALESCE(
                                                NULLIF(h.total_biaya_boiler_rp, 0),
                                                NULLIF(h.biaya_rp, 0),
                                                (COALESCE(h.pemakaian_kg, 0) * COALESCE(h.harga_rp_per_kg, 0)),
                                                0
                                            )) AS nilai
                                     FROM dbo.bb_21t_actom_harian h
                                     WHERE CAST(h.tanggal AS DATE) BETWEEN ? AND ?
                                     GROUP BY CAST(h.tanggal AS DATE)",
            'biaya_bb_jineng' => "SELECT CAST(h.tanggal AS DATE) AS tanggal,
                                         SUM(COALESCE(
                                             NULLIF(h.total_biaya_boiler_jineng_rp, 0),
                                             NULLIF(h.biaya_rp, 0),
                                             (COALESCE(h.pemakaian_kg, 0) * COALESCE(h.harga_rp_per_kg, 0)),
                                             0
                                         )) AS nilai
                                  FROM dbo.bb_boiler_jineng_harian h
                                  WHERE CAST(h.tanggal AS DATE) BETWEEN ? AND ?
                                  GROUP BY CAST(h.tanggal AS DATE)",
            'biaya_lpg_skid_tank' => "SELECT CAST(h.tanggal AS DATE) AS tanggal,
                                             SUM(COALESCE(NULLIF(h.biaya_rp, 0), (COALESCE(h.pemakaian_kg, 0) * COALESCE(h.harga_rp, 0)), 0)) AS nilai
                                      FROM dbo.lpg_skid_tank_harian h
                                      WHERE CAST(h.tanggal AS DATE) BETWEEN ? AND ?
                                      GROUP BY CAST(h.tanggal AS DATE)",
        ];

        foreach ($sourceQueries as $key => $sql) {
            $maps[$key] = runDailySumMapRekapBiayaEnergi($conn, $sql, [$start, $end], $errorMsg);
            if ($errorMsg !== '') {
                return $result;
            }
        }

        $dateSet = [];
        foreach ($maps as $map) {
            foreach ($map as $dateKey => $_nilai) {
                $dateSet[$dateKey] = true;
            }
        }

        $dates = array_keys($dateSet);
        sort($dates);

        $totals = array_fill_keys($valueKeys, 0.0);
        $totals['total_biaya'] = 0.0;

        $rows = [];
        foreach ($dates as $dateKey) {
            $row = ['tanggal' => $dateKey];
            foreach ($valueKeys as $key) {
                $row[$key] = isset($maps[$key][$dateKey]) ? (float)$maps[$key][$dateKey] : 0.0;
            }

            $row['total_biaya'] = 0.0;
            foreach ($valueKeys as $key) {
                $row['total_biaya'] += $row[$key];
                $totals[$key] += $row[$key];
            }
            $totals['total_biaya'] += $row['total_biaya'];

            $rows[] = $row;
        }

        $rowCount = count($rows);
        $averages = $totals;
        foreach ($averages as $key => $value) {
            $averages[$key] = $rowCount > 0 ? ($value / $rowCount) : 0.0;
        }

        $result['dates'] = $dates;
        $result['rows'] = $rows;
        $result['totals'] = $totals;
        $result['averages'] = $averages;
        $result['row_count'] = $rowCount;

        return $result;
    }
}

if (!function_exists('monthLabelRekapBiayaEnergi')) {
    function monthLabelRekapBiayaEnergi($start)
    {
        $monthMap = [
            'January' => 'JANUARI',
            'February' => 'FEBRUARI',
            'March' => 'MARET',
            'April' => 'APRIL',
            'May' => 'MEI',
            'June' => 'JUNI',
            'July' => 'JULI',
            'August' => 'AGUSTUS',
            'September' => 'SEPTEMBER',
            'October' => 'OKTOBER',
            'November' => 'NOVEMBER',
            'December' => 'DESEMBER',
        ];

        $monthEn = date('F', strtotime($start));
        return ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));
    }
}

if (!function_exists('fmtNumRekapBiayaEnergi')) {
    function fmtNumRekapBiayaEnergi($value, $decimals = 2)
    {
        if (!is_numeric($value)) {
            return '';
        }
        return number_format((float)$value, $decimals, '.', ',');
    }
}

if (!function_exists('fmtDayRekapBiayaEnergi')) {
    function fmtDayRekapBiayaEnergi($ymd)
    {
        if (!$ymd) {
            return '';
        }
        return date('j', strtotime((string)$ymd));
    }
}
