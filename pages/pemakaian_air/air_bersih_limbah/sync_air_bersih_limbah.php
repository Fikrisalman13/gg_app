<?php

if (!function_exists('abl_normalize_date')) {
    function abl_normalize_date($value) {
        if ($value instanceof DateTime) return $value->format('Y-m-d');
        $value = trim((string)$value);
        if ($value === '') return '';
        $ts = strtotime($value);
        return $ts ? date('Y-m-d', $ts) : '';
    }
}

if (!function_exists('abl_query_sum')) {
    function abl_query_sum($conn, $sql, $alias, $tanggal) {
        $stmt = sqlsrv_query($conn, $sql, [$tanggal]);
        if ($stmt === false) return null;
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($stmt) sqlsrv_free_stmt($stmt);
        if (!$row || !array_key_exists($alias, $row) || $row[$alias] === null || $row[$alias] === '') return null;
        return is_numeric($row[$alias]) ? (float)$row[$alias] : null;
    }
}

if (!function_exists('abl_collect_source_values')) {
    function abl_collect_source_values($conn, $tanggal) {
        $tanggal = abl_normalize_date($tanggal);
        if ($tanggal === '') return [];

        return [
            'washing_1_m3_hari' => abl_query_sum(
                $conn,
                "SELECT SUM(Total_Pemakaian) AS total_pemakaian FROM dbo.washing_air WHERE CAST(Tanggal AS DATE)=?",
                'total_pemakaian',
                $tanggal
            ),
            'washing_2_m3_hari' => abl_query_sum(
                $conn,
                "SELECT SUM(TotalPemakaian) AS total_pemakaian FROM dbo.washing2_water_meter WHERE CAST(Tanggal AS DATE)=?",
                'total_pemakaian',
                $tanggal
            ),
            'washing_3_m3_hari' => abl_query_sum(
                $conn,
                "SELECT SUM(TotalPemakaian) AS total_pemakaian FROM dbo.washing3_air WHERE CAST(Tanggal AS DATE)=?",
                'total_pemakaian',
                $tanggal
            ),
            'perble_range_1_m3_hari' => abl_query_sum(
                $conn,
                "SELECT SUM(jumlah_debit_m3) AS total_debit FROM dbo.perblerange1_harian WHERE CAST(tanggal AS DATE)=?",
                'total_debit',
                $tanggal
            ),
            'perble_range_2_m3_hari' => abl_query_sum(
                $conn,
                "SELECT SUM(Total_Pemakaian) AS total_pemakaian FROM dbo.perblerange2_air WHERE CAST(Tanggal AS DATE)=?",
                'total_pemakaian',
                $tanggal
            ),
            'pad_steam_m3_hari' => abl_query_sum(
                $conn,
                "SELECT SUM(Meter_Akhir - Meter_Awal) AS total_pemakaian FROM dbo.padsteam_air WHERE CAST(Tanggal AS DATE)=?",
                'total_pemakaian',
                $tanggal
            ),
            'jetdying_sizing_la_m3_hari' => abl_query_sum(
                $conn,
                "SELECT SUM(Total_Pemakaian) AS total_pemakaian FROM dbo.jetdyeing_air WHERE CAST(Tanggal AS DATE)=?",
                'total_pemakaian',
                $tanggal
            ),
            'kantin_mes_pos_security_m3_hari' => abl_query_sum(
                $conn,
                "SELECT SUM(Total_Pemakaian) AS total_pemakaian FROM dbo.mkp_air WHERE CAST(Tanggal AS DATE)=?",
                'total_pemakaian',
                $tanggal
            ),
        ];
    }
}

if (!function_exists('abl_overlay_row_with_source_values')) {
    function abl_overlay_row_with_source_values(array $row, array $sourceValues) {
        foreach ($sourceValues as $field => $value) {
            if ($value !== null) $row[$field] = $value;
        }
        return $row;
    }
}

if (!function_exists('abl_sync_date')) {
    function abl_sync_date($conn, $tanggal, ?array $sourceValues = null) {
        $tanggal = abl_normalize_date($tanggal);
        if ($tanggal === '') return false;
        if ($sourceValues === null) $sourceValues = abl_collect_source_values($conn, $tanggal);
        if (empty($sourceValues)) return false;

        $setParts = [];
        $params = [];
        foreach ($sourceValues as $field => $value) {
            if ($value === null) continue;
            $setParts[] = $field . " = ?";
            $params[] = $value;
        }
        if (empty($setParts)) return false;

        $setParts[] = "updateat = GETDATE()";
        $sql = "UPDATE dbo.air_bersih_limbah_harian
                SET " . implode(', ', $setParts) . "
                WHERE CAST(tanggal AS DATE) = ?";
        $params[] = $tanggal;

        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) return false;
        if ($stmt) sqlsrv_free_stmt($stmt);
        return true;
    }
}

