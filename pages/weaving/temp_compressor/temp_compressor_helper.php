<?php

function temp_compressor_hours()
{
    return [
        '06:30', '07:30', '08:30', '09:30', '10:30', '11:30',
        '12:30', '13:30', '14:30', '15:30', '16:30', '17:30',
        '18:30', '19:30', '20:30', '21:30', '22:30', '23:30',
        '00:30', '01:30', '02:30', '03:30', '04:30', '05:30'
    ];
}

function temp_compressor_display_hour($hour)
{
    return $hour === '00:30' ? '24:30' : $hour;
}

function temp_compressor_table_exists($conn)
{
    $stmt = sqlsrv_query($conn, "SELECT OBJECT_ID('dbo.temp_compressor', 'U') AS table_id");
    if ($stmt === false) return false;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($stmt) sqlsrv_free_stmt($stmt);
    return !empty($row['table_id']);
}

function temp_compressor_fmt_date($value, $format = 'Y-m-d')
{
    if ($value instanceof DateTime) return $value->format($format);
    if (is_string($value) && $value !== '') {
        $ts = strtotime($value);
        return $ts ? date($format, $ts) : $value;
    }
    return '';
}

function temp_compressor_fmt_time($value)
{
    if ($value instanceof DateTime) return $value->format('H:i');
    if (is_string($value) && $value !== '') {
        $ts = strtotime($value);
        return $ts ? date('H:i', $ts) : substr($value, 0, 5);
    }
    return '';
}

function temp_compressor_normalize_decimal($value)
{
    $value = trim((string)$value);
    if ($value === '') return null;
    if (!preg_match('/^-?\d{1,8}([.,]\d{1,2})?$/', $value)) return null;
    return str_replace(',', '.', $value);
}

function temp_compressor_is_valid_decimal_input($value)
{
    $value = trim((string)$value);
    return $value === '' || preg_match('/^-?\d{1,8}([.,]\d{1,2})?$/', $value);
}

function temp_compressor_fmt_num($value, $decimals = 0)
{
    if ($value === null || $value === '') return '';
    return is_numeric($value) ? number_format((float)$value, $decimals, '.', '') : (string)$value;
}

function temp_compressor_get_petugas_options($conn)
{
    $options = [];
    $sql = "SELECT DISTINCT m_emp.nama_lengkap
        FROM dbo.m_emp
        LEFT JOIN dbo.m_subbag ON m_emp.id_subbag = m_subbag.id_subbag
        LEFT JOIN dbo.m_bag ON COALESCE(m_subbag.id_bag, m_emp.id_bag) = m_bag.id_bag
        LEFT JOIN dbo.m_dept ON COALESCE(m_bag.id_dept, m_emp.id_dept) = m_dept.id_dept
        WHERE m_emp.aktif = 1
          AND m_dept.dept = ?
          AND m_bag.bagian = ?
        ORDER BY m_emp.nama_lengkap";
    $stmt = sqlsrv_query($conn, $sql, ['Maintenance & Utility', 'Maintenance Weaving']);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $options[] = $row['nama_lengkap'];
        }
        sqlsrv_free_stmt($stmt);
    }
    return $options;
}

function temp_compressor_resolve_sheet($conn, $id)
{
    $sql = "SELECT TOP 1 Id, Tanggal, Petugas, Keterangan, CreatBy, CreatAt, UpdateBy, UpdateAt
            FROM dbo.temp_compressor
            WHERE Id = ?";
    $stmt = sqlsrv_query($conn, $sql, [(int)$id]);
    if ($stmt === false) return null;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($stmt) sqlsrv_free_stmt($stmt);
    return $row ?: null;
}

function temp_compressor_resolve_sheet_by_date($conn, $tanggal)
{
    $sql = "SELECT TOP 1 Id, Tanggal, Petugas, Keterangan, CreatBy, CreatAt, UpdateBy, UpdateAt
            FROM dbo.temp_compressor
            WHERE CAST(Tanggal AS DATE) = ?
            ORDER BY Id ASC";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal]);
    if ($stmt === false) return null;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($stmt) sqlsrv_free_stmt($stmt);
    return $row ?: null;
}

function temp_compressor_get_sheet_cells($conn, $tanggal)
{
    $sql = "SELECT Id, Tanggal, Jam, CreatBy, CreatAt,
                Compressor1_In_C, Compressor1_Out_C,
                Compressor2_In_C, Compressor2_Out_C,
                Amper, PressureBar_P1, PressureBar_P2,
                Temperature_T1, Temperature_T2, Temperature_T3,
                Dryer_C, TekananAir_In, TekananAir_Out,
                Petugas, Keterangan
            FROM dbo.temp_compressor
            WHERE CAST(Tanggal AS DATE) = ?
            ORDER BY CASE WHEN CAST(Jam AS TIME) >= '06:00:00' THEN 0 ELSE 1 END ASC,
                     CAST(Jam AS TIME) ASC, Id ASC";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal]);
    if ($stmt === false) return [];

    $cells = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $hourKey = temp_compressor_fmt_time($row['Jam'] ?? null);
        $cells[$hourKey] = [
            'id'          => $row['Id'] ?? 0,
            'jam'         => $hourKey,
            'c1_in'       => temp_compressor_fmt_num($row['Compressor1_In_C'] ?? null, 0),
            'c1_out'      => temp_compressor_fmt_num($row['Compressor1_Out_C'] ?? null, 0),
            'c2_in'       => temp_compressor_fmt_num($row['Compressor2_In_C'] ?? null, 0),
            'c2_out'      => temp_compressor_fmt_num($row['Compressor2_Out_C'] ?? null, 0),
            'amper'       => temp_compressor_fmt_num($row['Amper'] ?? null, 0),
            'pressure_p1' => temp_compressor_fmt_num($row['PressureBar_P1'] ?? null, 1),
            'pressure_p2' => temp_compressor_fmt_num($row['PressureBar_P2'] ?? null, 1),
            'temp_t1'     => temp_compressor_fmt_num($row['Temperature_T1'] ?? null, 1),
            'temp_t2'     => temp_compressor_fmt_num($row['Temperature_T2'] ?? null, 1),
            'temp_t3'     => temp_compressor_fmt_num($row['Temperature_T3'] ?? null, 1),
            'dryer_c'     => temp_compressor_fmt_num($row['Dryer_C'] ?? null, 0),
            'tekanan_in'  => temp_compressor_fmt_num($row['TekananAir_In'] ?? null, 1),
            'tekanan_out' => temp_compressor_fmt_num($row['TekananAir_Out'] ?? null, 1),
            'petugas'     => (string)($row['Petugas'] ?? ''),
            'keterangan'  => (string)($row['Keterangan'] ?? ''),
            'creat_by'    => (string)($row['CreatBy'] ?? ''),
            'creat_at'    => $row['CreatAt'] ?? null,
        ];
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    return $cells;
}

function temp_compressor_merge_petugas_names($existing, $new)
{
    $names = [];
    foreach (explode(',', (string)$existing) as $name) {
        $name = trim($name);
        if ($name === '') continue;
        $key = strtolower($name);
        if (!isset($names[$key])) $names[$key] = $name;
    }
    foreach (explode(',', (string)$new) as $name) {
        $name = trim($name);
        if ($name === '') continue;
        $key = strtolower($name);
        if (!isset($names[$key])) $names[$key] = $name;
    }
    return implode(', ', array_values($names));
}

function temp_compressor_petugas_for_sheet_query($conn, $tanggal)
{
    $sql = "SELECT Petugas
            FROM dbo.temp_compressor
            WHERE CAST(Tanggal AS DATE) = ?
              AND Petugas IS NOT NULL
            ORDER BY Jam, Id";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal]);
    if ($stmt === false) return '';

    $merged = '';
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $merged = temp_compressor_merge_petugas_names($merged, $row['Petugas'] ?? '');
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    return $merged;
}

function temp_compressor_values_equal($v1, $v2)
{
    $s1 = ($v1 === null) ? '' : str_replace(',', '.', trim((string)$v1));
    $s2 = ($v2 === null) ? '' : str_replace(',', '.', trim((string)$v2));
    if ($s1 === '' && $s2 === '') return true;
    if ($s1 === '' || $s2 === '') return false;
    if (is_numeric($s1) && is_numeric($s2)) {
        return abs((float)$s1 - (float)$s2) < 0.0001;
    }
    return strtolower($s1) === strtolower($s2);
}

function temp_compressor_keterangan_for_sheet_query($conn, $tanggal)
{
    $sql = "SELECT DISTINCT Keterangan
            FROM dbo.temp_compressor
            WHERE CAST(Tanggal AS DATE) = ?
              AND Keterangan IS NOT NULL
              AND Keterangan <> ''
            ORDER BY Keterangan";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal]);
    if ($stmt === false) return '';

    $keteranganList = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $k = trim((string)($row['Keterangan'] ?? ''));
        if ($k !== '') {
            $keteranganList[] = $k;
        }
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    return implode('; ', $keteranganList);
}

