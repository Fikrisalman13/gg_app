<?php

if (!function_exists('kwhl2_machine_defs')) {
    function kwhl2_machine_defs()
    {
        return [
            'ipab' => [
                'label' => 'IPAB',
                'hi_key' => 'kwh_hari_ini_ipab',
                'km_key' => 'kwh_kemarin_ipab',
                'kwh_key' => 'kwh_ipab',
                'biaya_key' => 'biaya_ipab',
            ],
            'jineng' => [
                'label' => 'JINENG',
                'hi_key' => 'kwh_hari_ini_jineng',
                'km_key' => 'kwh_kemarin_jineng',
                'kwh_key' => 'kwh_jineng',
                'biaya_key' => 'biaya_jineng',
            ],
            'xineng' => [
                'label' => 'XINENG',
                'hi_key' => 'kwh_hari_ini_xineng',
                'km_key' => 'kwh_kemarin_xineng',
                'kwh_key' => 'kwh_xineng',
                'biaya_key' => 'biaya_xineng',
            ],
            '20ton_l' => [
                'label' => '20TON Lama dan Baru',
                'hi_key' => 'kwh_hari_ini_20ton_l',
                'km_key' => 'kwh_kemarin_20ton_l',
                'kwh_key' => 'kwh_20ton_l',
                'biaya_key' => 'biaya_20ton_l',
                'alt_kwh_key' => 'kwh_20ton',
                'alt_biaya_key' => 'biaya_20ton',
            ],
            'ipal' => [
                'label' => 'IPAL',
                'hi_key' => 'kwh_hari_ini_ipal',
                'km_key' => 'kwh_kemarin_ipal',
                'kwh_key' => 'kwh_ipal',
                'biaya_key' => 'biaya_ipal',
            ],
            '21ton_actom' => [
                'label' => '21TON ACTOM',
                'hi_key' => 'kwh_hari_ini_21ton_actom',
                'km_key' => 'kwh_kemarin_21ton_actom',
                'kwh_key' => 'kwh_21ton_actom',
                'biaya_key' => 'biaya_21ton_actom',
                'alt_hi_key' => 'kwh_hari_ini_21t',
                'alt_km_key' => 'kwh_kemarin_21t',
            ],
        ];
    }
}

if (!function_exists('kwhl2_default_tarif')) {
    function kwhl2_default_tarif()
    {
        return 1252.0;
    }
}

if (!function_exists('kwhl2_parse_number')) {
    function kwhl2_parse_number($value)
    {
        $text = trim((string)($value ?? ''));
        if ($text === '') {
            return 0.0;
        }

        $text = preg_replace('/[^0-9,.\-]/', '', $text);
        if ($text === '' || $text === '-' || $text === '.' || $text === ',') {
            return 0.0;
        }

        $hasDot = strpos($text, '.') !== false;
        $hasComma = strpos($text, ',') !== false;

        if ($hasDot && $hasComma) {
            $lastDot = strrpos($text, '.');
            $lastComma = strrpos($text, ',');
            if ($lastDot !== false && $lastComma !== false && $lastDot > $lastComma) {
                // Format: 1,234.56
                $text = str_replace(',', '', $text);
            } else {
                // Format: 1.234,56
                $text = str_replace('.', '', $text);
                $text = str_replace(',', '.', $text);
            }
        } elseif ($hasComma) {
            if (preg_match('/^-?\d{1,3}(,\d{3})+$/', $text)) {
                // Format: 1,234,567
                $text = str_replace(',', '', $text);
            } else {
                // Format: 1234,56
                $text = str_replace(',', '.', $text);
            }
        } elseif ($hasDot) {
            $dotCount = substr_count($text, '.');
            if ($dotCount > 1 && preg_match('/^-?\d{1,3}(\.\d{3})+$/', $text)) {
                // Format: 1.234.567
                $text = str_replace('.', '', $text);
            }
        }

        return is_numeric($text) ? (float)$text : 0.0;
    }
}

if (!function_exists('kwhl2_normalize_date')) {
    function kwhl2_normalize_date($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        if (preg_match('/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/', $value, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return '';
        }
        return date('Y-m-d', $ts);
    }
}

if (!function_exists('kwhl2_compute_row')) {
    function kwhl2_compute_row(array $input)
    {
        $tarif = kwhl2_parse_number($input['tarif_per_kwh'] ?? kwhl2_default_tarif());
        if ($tarif <= 0) {
            $tarif = kwhl2_default_tarif();
        }

        $out = [
            'tarif_per_kwh' => $tarif,
            'total_kwh' => 0.0,
            'total_biaya' => 0.0,
        ];

        foreach (kwhl2_machine_defs() as $code => $def) {
            $hiKey = $def['hi_key'];
            $kmKey = $def['km_key'];
            $kwhKey = $def['kwh_key'];
            $biayaKey = $def['biaya_key'];

            $hi = kwhl2_parse_number($input[$hiKey] ?? ($input[$def['alt_hi_key'] ?? ''] ?? 0));
            $km = kwhl2_parse_number($input[$kmKey] ?? ($input[$def['alt_km_key'] ?? ''] ?? 0));

            // Perhitungan: Pemakaian = KWH Hari Ini - KWH Kemarin (seperti 21 Ton Actom)
            $pemakaian = $hi - $km;
            if ($pemakaian < 0) {
                $pemakaian = 0.0;
            }
            $biaya = $pemakaian * $tarif;

            $out[$hiKey] = $hi;
            $out[$kmKey] = $km;
            $out[$kwhKey] = $pemakaian;
            $out[$biayaKey] = $biaya;

            // Simpan juga alt_key jika ada
            if (isset($def['alt_kwh_key'])) {
                $out[$def['alt_kwh_key']] = $pemakaian;
            }
            if (isset($def['alt_biaya_key'])) {
                $out[$def['alt_biaya_key']] = $biaya;
            }

            $out['total_kwh'] += $pemakaian;
            $out['total_biaya'] += $biaya;
        }

        return $out;
    }
}

if (!function_exists('kwhl2_candidate_tables')) {
    function kwhl2_candidate_tables()
    {
        return [
            ['schema' => 'dbo', 'name' => 'kwh_listrik2'],
        ];
    }
}

if (!function_exists('kwhl2_get_table_info')) {
    function kwhl2_get_table_info($conn)
    {
        foreach (kwhl2_candidate_tables() as $cand) {
            $stmt = sqlsrv_query(
                $conn,
                "SELECT TOP 1 1 AS ok
                 FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_TYPE='BASE TABLE' AND TABLE_SCHEMA=? AND TABLE_NAME=?",
                [$cand['schema'], $cand['name']]
            );
            if ($stmt === false) {
                continue;
            }
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmt);
            if ($row) {
                return $cand;
            }
        }
        return null;
    }
}

if (!function_exists('kwhl2_table_full_name')) {
    function kwhl2_table_full_name($conn)
    {
        $info = kwhl2_get_table_info($conn);
        if (!$info) {
            return '';
        }
        return '[' . $info['schema'] . '].[' . $info['name'] . ']';
    }
}

if (!function_exists('kwhl2_table_display_name')) {
    function kwhl2_table_display_name($conn)
    {
        $info = kwhl2_get_table_info($conn);
        if (!$info) {
            return 'kwh_listrik2';
        }
        return $info['name'];
    }
}

if (!function_exists('kwhl2_table_exists')) {
    function kwhl2_table_exists($conn)
    {
        return kwhl2_get_table_info($conn) !== null;
    }
}

if (!function_exists('kwhl2_get_table_columns')) {
    function kwhl2_get_table_columns($conn)
    {
        $cols = [];
        $info = kwhl2_get_table_info($conn);
        if (!$info) {
            return $cols;
        }
        $sql = "SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA=? AND TABLE_NAME=?";
        $stmt = sqlsrv_query($conn, $sql, [$info['schema'], $info['name']]);
        if ($stmt === false) {
            return $cols;
        }
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $name = strtolower(trim((string)($row['COLUMN_NAME'] ?? '')));
            if ($name !== '') {
                $cols[$name] = true;
            }
        }
        sqlsrv_free_stmt($stmt);
        return $cols;
    }
}

if (!function_exists('kwhl2_row_from_db')) {
    function kwhl2_row_from_db(array $row)
    {
        $lower = [];
        foreach ($row as $k => $v) {
            $lower[strtolower((string)$k)] = $v;
        }

        $dateVal = $lower['tanggal'] ?? null;
        if ($dateVal instanceof DateTime) {
            $tanggal = $dateVal->format('Y-m-d');
        } else {
            $tanggal = $dateVal ? date('Y-m-d', strtotime((string)$dateVal)) : '';
        }

        $tarif = (float)($lower['tarif_per_kwh'] ?? kwhl2_default_tarif());
        if ($tarif <= 0) {
            $tarif = kwhl2_default_tarif();
        }

        $mapped = [
            'id' => isset($lower['id']) ? (int)$lower['id'] : 0,
            'tanggal' => $tanggal,
            'tarif_per_kwh' => $tarif,
            'ket' => trim((string)($lower['ket'] ?? '')),
            'note' => trim((string)($lower['note'] ?? '')),
            'created_by' => trim((string)($lower['updateby'] ?? ($lower['creatby'] ?? ($lower['created_by'] ?? ($lower['createby'] ?? ''))))),
            'total_kwh' => 0.0,
            'total_biaya' => 0.0,
        ];

        foreach (kwhl2_machine_defs() as $code => $def) {
            $hiKey = $def['hi_key'];
            $kmKey = $def['km_key'];
            $kwhKey = $def['kwh_key'];
            $biayaKey = $def['biaya_key'];

            $hiVal = (float)($lower[$hiKey] ?? ($lower[$def['alt_hi_key'] ?? ''] ?? 0));
            $kmVal = (float)($lower[$kmKey] ?? ($lower[$def['alt_km_key'] ?? ''] ?? 0));

            // Jika kwh sudah ada di database, gunakan itu; jika tidak, hitung hi - km
            if (array_key_exists($kwhKey, $lower) && is_numeric($lower[$kwhKey])) {
                $kwhVal = (float)$lower[$kwhKey];
            } else {
                $kwhVal = $hiVal - $kmVal;
                if ($kwhVal < 0) {
                    $kwhVal = 0.0;
                }
            }

            if (array_key_exists($biayaKey, $lower) && is_numeric($lower[$biayaKey])) {
                $biayaVal = (float)$lower[$biayaKey];
            } else {
                $biayaVal = $kwhVal * $tarif;
            }

            $mapped[$hiKey] = $hiVal;
            $mapped[$kmKey] = $kmVal;
            $mapped[$kwhKey] = $kwhVal;
            $mapped[$biayaKey] = $biayaVal;

            if (isset($def['alt_kwh_key'])) {
                $mapped[$def['alt_kwh_key']] = $kwhVal;
            }
            if (isset($def['alt_biaya_key'])) {
                $mapped[$def['alt_biaya_key']] = $biayaVal;
            }

            $mapped['total_kwh'] += $kwhVal;
            $mapped['total_biaya'] += $biayaVal;
        }

        if (array_key_exists('total_kwh', $lower) && is_numeric($lower['total_kwh']) && (float)$lower['total_kwh'] > 0) {
            $mapped['total_kwh'] = (float)$lower['total_kwh'];
        }
        if (array_key_exists('total_biaya', $lower) && is_numeric($lower['total_biaya']) && (float)$lower['total_biaya'] > 0) {
            $mapped['total_biaya'] = (float)$lower['total_biaya'];
        }

        return $mapped;
    }
}

if (!function_exists('kwhl2_format_num')) {
    function kwhl2_format_num($value, $decimals = 2)
    {
        if ($value === null || $value === '') {
            return '-';
        }
        if (!is_numeric($value)) {
            return (string)$value;
        }
        $val = (float)$value;
        if ($val == 0.0) {
            return '0';
        }
        return number_format($val, $decimals, '.', ',');
    }
}

if (!function_exists('kwhl2_format_day')) {
    function kwhl2_format_day($dateYmd)
    {
        $d = trim((string)$dateYmd);
        if ($d === '') {
            return '';
        }
        $ts = strtotime($d);
        if ($ts === false) {
            return '';
        }
        return date('j', $ts);
    }
}
