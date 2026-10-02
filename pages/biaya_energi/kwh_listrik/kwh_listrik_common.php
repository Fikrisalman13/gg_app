<?php

if (!function_exists('kwhl_machine_defs')) {
    function kwhl_machine_defs()
    {
        return [
            'ipab' => ['label' => 'IPAB', 'amp_key' => 'amp_ipab'],
            'jineng' => ['label' => 'JINENG', 'amp_key' => 'amp_jineng'],
            'xineng' => ['label' => 'XINENG', 'amp_key' => 'amp_xineng'],
            '20ton' => ['label' => '20TON L', 'amp_key' => 'amp_20ton_l'],
            '20ton_baru' => ['label' => '20TON BARU', 'amp_key' => 'amp_longchuan_b'],
            'ipal' => ['label' => 'IPAL', 'amp_key' => 'amp_ipal'],
        ];
    }
}

if (!function_exists('kwhl_amp_columns')) {
    function kwhl_amp_columns()
    {
        return [
            'amp_ipab',
            'amp_jineng',
            'amp_xineng',
            'amp_20ton_l',
            'amp_longchuan_b',
            'amp_ipal',
        ];
    }
}

if (!function_exists('kwhl_kwh_columns')) {
    function kwhl_kwh_columns()
    {
        return [
            'kwh_ipab',
            'kwh_jineng',
            'kwh_xineng',
            'kwh_20ton',
            'kwh_20ton_baru',
            'kwh_ipal',
        ];
    }
}

if (!function_exists('kwhl_biaya_columns')) {
    function kwhl_biaya_columns()
    {
        return [
            'biaya_ipab',
            'biaya_jineng',
            'biaya_xineng',
            'biaya_20ton',
            'biaya_20ton_baru',
            'biaya_ipal',
        ];
    }
}

if (!function_exists('kwhl_default_factor')) {
    function kwhl_default_factor()
    {
        return 633.5;
    }
}

if (!function_exists('kwhl_default_tarif')) {
    function kwhl_default_tarif()
    {
        return 1252.0;
    }
}

if (!function_exists('kwhl_parse_number')) {
    function kwhl_parse_number($value)
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
            // Jika hanya ada 1 titik (contoh: 784.300), perlakukan sebagai desimal.
            // Pola ribuan bertitik kita anggap valid hanya bila titik lebih dari satu (contoh: 1.234.567).
            $dotCount = substr_count($text, '.');
            if ($dotCount > 1 && preg_match('/^-?\d{1,3}(\.\d{3})+$/', $text)) {
                // Format: 1.234.567
                $text = str_replace('.', '', $text);
            }
        }

        return is_numeric($text) ? (float)$text : 0.0;
    }
}

if (!function_exists('kwhl_normalize_date')) {
    function kwhl_normalize_date($value)
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

if (!function_exists('kwhl_compute_row')) {
    function kwhl_compute_row(array $input)
    {
        $factor = kwhl_parse_number($input['faktor_konversi'] ?? kwhl_default_factor());
        if ($factor <= 0) {
            $factor = kwhl_default_factor();
        }
        $tarif = kwhl_parse_number($input['tarif_per_kwh'] ?? kwhl_default_tarif());
        if ($tarif <= 0) {
            $tarif = kwhl_default_tarif();
        }

        $out = [
            'faktor_konversi' => $factor,
            'tarif_per_kwh' => $tarif,
            'total_kwh' => 0.0,
            'total_biaya' => 0.0,
        ];

        foreach (kwhl_machine_defs() as $code => $def) {
            $ampKey = $def['amp_key'];
            $amp = kwhl_parse_number($input[$ampKey] ?? 0);
            $kwhKey = 'kwh_' . $code;
            $biayaKey = 'biaya_' . $code;

            $kwh = ($amp * $factor * 24.0) / 1000.0;
            $biaya = $kwh * $tarif;

            $out[$ampKey] = $amp;
            $out[$kwhKey] = $kwh;
            $out[$biayaKey] = $biaya;
            $out['total_kwh'] += $kwh;
            $out['total_biaya'] += $biaya;
        }

        return $out;
    }
}

if (!function_exists('kwhl_table_exists')) {
    function kwhl_candidate_tables()
    {
        return [
            ['schema' => 'dbo', 'name' => 'kwh_listrik'],
            ['schema' => 'dbo', 'name' => 'kwh_listrik_harian'],
        ];
    }
}

if (!function_exists('kwhl_get_table_info')) {
    function kwhl_get_table_info($conn)
    {
        foreach (kwhl_candidate_tables() as $cand) {
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

if (!function_exists('kwhl_table_full_name')) {
    function kwhl_table_full_name($conn)
    {
        $info = kwhl_get_table_info($conn);
        if (!$info) {
            return '';
        }
        return '[' . $info['schema'] . '].[' . $info['name'] . ']';
    }
}

if (!function_exists('kwhl_table_display_name')) {
    function kwhl_table_display_name($conn)
    {
        $info = kwhl_get_table_info($conn);
        if (!$info) {
            return 'kwh_listrik';
        }
        return $info['name'];
    }
}

if (!function_exists('kwhl_table_exists')) {
    function kwhl_table_exists($conn)
    {
        return kwhl_get_table_info($conn) !== null;
    }
}

if (!function_exists('kwhl_get_table_columns')) {
    function kwhl_get_table_columns($conn)
    {
        $cols = [];
        $info = kwhl_get_table_info($conn);
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

if (!function_exists('kwhl_row_from_db')) {
    function kwhl_row_from_db(array $row)
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

        $input = [
            'faktor_konversi' => $lower['faktor_konversi'] ?? kwhl_default_factor(),
            'tarif_per_kwh' => $lower['tarif_per_kwh'] ?? kwhl_default_tarif(),
        ];
        foreach (kwhl_amp_columns() as $ampCol) {
            $input[$ampCol] = $lower[$ampCol] ?? 0;
        }

        $computed = kwhl_compute_row($input);
        $mapped = [
            'id' => isset($lower['id']) ? (int)$lower['id'] : 0,
            'tanggal' => $tanggal,
            'ket' => trim((string)($lower['ket'] ?? '')),
            'note' => trim((string)($lower['note'] ?? '')),
            'created_by' => trim((string)($lower['updateby'] ?? ($lower['creatby'] ?? ($lower['created_by'] ?? ($lower['createby'] ?? ''))))),
        ];

        foreach (array_merge(kwhl_amp_columns(), kwhl_kwh_columns(), kwhl_biaya_columns(), ['faktor_konversi', 'tarif_per_kwh', 'total_kwh', 'total_biaya']) as $col) {
            if (array_key_exists($col, $lower) && is_numeric($lower[$col])) {
                $mapped[$col] = (float)$lower[$col];
            } else {
                $mapped[$col] = (float)($computed[$col] ?? 0);
            }
        }

        $kwhHariIni21t = (array_key_exists('kwh_hari_ini_21t', $lower) && is_numeric($lower['kwh_hari_ini_21t']))
            ? (float)$lower['kwh_hari_ini_21t'] : 0.0;
        $kwhKemarin21t = (array_key_exists('kwh_kemarin_21t', $lower) && is_numeric($lower['kwh_kemarin_21t']))
            ? (float)$lower['kwh_kemarin_21t'] : 0.0;
        $kwhActomStored = (array_key_exists('kwh_21ton_actom', $lower) && is_numeric($lower['kwh_21ton_actom']))
            ? (float)$lower['kwh_21ton_actom'] : 0.0;
        $biayaActomStored = (array_key_exists('biaya_21ton_actom', $lower) && is_numeric($lower['biaya_21ton_actom']))
            ? (float)$lower['biaya_21ton_actom'] : 0.0;

        $hasRaw21t = array_key_exists('kwh_hari_ini_21t', $lower) && array_key_exists('kwh_kemarin_21t', $lower)
            && is_numeric($lower['kwh_hari_ini_21t']) && is_numeric($lower['kwh_kemarin_21t']);

        $kwhActom = $kwhActomStored;
        if ($hasRaw21t) {
            $kwhActom = $kwhHariIni21t - $kwhKemarin21t;
            if ($kwhActom < 0) {
                $kwhActom = 0.0;
            }
        }

        $tarif21t = (float)($mapped['tarif_per_kwh'] ?? kwhl_default_tarif());
        if ($tarif21t <= 0) {
            $tarif21t = kwhl_default_tarif();
        }
        $biayaActom = $hasRaw21t ? ($kwhActom * $tarif21t) : $biayaActomStored;

        $mapped['kwh_hari_ini_21t'] = $kwhHariIni21t;
        $mapped['kwh_kemarin_21t'] = $kwhKemarin21t;
        $mapped['kwh_21ton_actom'] = $kwhActom;
        $mapped['biaya_21ton_actom'] = $biayaActom;

        return $mapped;
    }
}

if (!function_exists('kwhl_format_num')) {
    function kwhl_format_num($value, $decimals = 2)
    {
        if ($value === null || $value === '') {
            return '-';
        }
        if (!is_numeric($value)) {
            return (string)$value;
        }
        return number_format((float)$value, $decimals, '.', ',');
    }
}

if (!function_exists('kwhl_format_day')) {
    function kwhl_format_day($dateYmd)
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
