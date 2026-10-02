<?php

function air_dryer_hours()
{
    return [
        '06:00', '07:00', '08:00', '09:00', '10:00', '11:00',
        '12:00', '13:00', '14:00', '15:00', '16:00', '17:00',
        '18:00', '19:00', '20:00', '21:00', '22:00', '23:00',
        '01:00', '02:00', '03:00', '04:00', '05:00'
    ];
}

function air_dryer_table_exists($conn)
{
    $stmt = sqlsrv_query($conn, "SELECT OBJECT_ID('dbo.air_dryer', 'U') AS table_id");
    if ($stmt === false) return false;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($stmt) sqlsrv_free_stmt($stmt);
    return !empty($row['table_id']);
}

function air_dryer_fmt_date($value, $format = 'Y-m-d')
{
    if ($value instanceof DateTime) return $value->format($format);
    if (is_string($value) && $value !== '') {
        $ts = strtotime($value);
        return $ts ? date($format, $ts) : $value;
    }
    return '';
}

function air_dryer_fmt_time($value)
{
    if ($value instanceof DateTime) return $value->format('H:i');
    if (is_string($value) && $value !== '') {
        $ts = strtotime($value);
        return $ts ? date('H:i', $ts) : substr($value, 0, 5);
    }
    return '';
}

function air_dryer_normalize_decimal($value)
{
    $value = trim((string)$value);
    if ($value === '') return null;
    if (!preg_match('/^-?\d{1,8}([.,]\d{1,2})?$/', $value)) return null;
    return str_replace(',', '.', $value);
}

function air_dryer_is_valid_decimal_input($value)
{
    $value = trim((string)$value);
    return $value === '' || preg_match('/^-?\d{1,8}([.,]\d{1,2})?$/', $value);
}

function air_dryer_fmt_num($value, $decimals = 1)
{
    if ($value === null || $value === '') return '';
    if (!is_numeric($value)) return (string)$value;
    $number = (float)$value;
    if (floor($number) == $number) return number_format($number, 0, '.', '');
    return number_format($number, $decimals, '.', '');
}

function air_dryer_get_row($conn, $id)
{
    $sql = "SELECT TOP 1 *
            FROM dbo.air_dryer
            WHERE Id = ?";
    $stmt = sqlsrv_query($conn, $sql, [(int)$id]);
    if ($stmt === false) return null;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($stmt) sqlsrv_free_stmt($stmt);
    return $row ?: null;
}

function air_dryer_get_petugas_options($conn)
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

function air_dryer_resolve_sheet($conn, $id)
{
    $sql = "SELECT TOP 1 Id, Tanggal, Petugas, Keterangan, CreatBy, CreatAt, UpdateBy, UpdateAt
            FROM dbo.air_dryer
            WHERE Id = ?";
    $stmt = sqlsrv_query($conn, $sql, [(int)$id]);
    if ($stmt === false) return null;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($stmt) sqlsrv_free_stmt($stmt);
    return $row ?: null;
}

function air_dryer_get_sheet_cells($conn, $tanggal)
{
    $sql = "SELECT Id, Tanggal, Jam_Pengecekan, CreatAt, CreatBy,
                AirDryer1_Temp_In_C, AirDryer1_Temp_Out_C,
                AirDryer1_Tekanan_In_Bar, AirDryer1_Tekanan_Out_Bar,
                AirDryer2_Temp_In_C, AirDryer2_Temp_Out_C,
                AirDryer2_Tekanan_In_Bar, AirDryer2_Tekanan_Out_Bar,
                Petugas, Keterangan
            FROM dbo.air_dryer
            WHERE CAST(Tanggal AS DATE) = ?
            ORDER BY CASE WHEN CAST(Jam_Pengecekan AS TIME) >= '06:00:00' THEN 0 ELSE 1 END ASC,
                     CAST(Jam_Pengecekan AS TIME) ASC, Id ASC";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal]);
    if ($stmt === false) return [];

    $cells = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $hourKey = air_dryer_fmt_time($row['Jam_Pengecekan'] ?? null);
        $cells[$hourKey] = [
            'id' => $row['Id'] ?? 0,
            'jam' => $hourKey,
            'aktual_check' => air_dryer_fmt_time($row['CreatAt'] ?? null),
            'creat_at' => $row['CreatAt'] ?? null,
            'creat_by' => (string)($row['CreatBy'] ?? ''),
            'ad1_temp_in' => air_dryer_fmt_num($row['AirDryer1_Temp_In_C'] ?? null, 1),
            'ad1_temp_out' => air_dryer_fmt_num($row['AirDryer1_Temp_Out_C'] ?? null, 1),
            'ad1_press_in' => air_dryer_fmt_num($row['AirDryer1_Tekanan_In_Bar'] ?? null, 1),
            'ad1_press_out' => air_dryer_fmt_num($row['AirDryer1_Tekanan_Out_Bar'] ?? null, 1),
            'ad2_temp_in' => air_dryer_fmt_num($row['AirDryer2_Temp_In_C'] ?? null, 1),
            'ad2_temp_out' => air_dryer_fmt_num($row['AirDryer2_Temp_Out_C'] ?? null, 1),
            'ad2_press_in' => air_dryer_fmt_num($row['AirDryer2_Tekanan_In_Bar'] ?? null, 1),
            'ad2_press_out' => air_dryer_fmt_num($row['AirDryer2_Tekanan_Out_Bar'] ?? null, 1),
            'petugas' => (string)($row['Petugas'] ?? ''),
            'keterangan' => (string)($row['Keterangan'] ?? ''),
        ];
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    return $cells;
}

function air_dryer_merge_petugas_names($existing, $new)
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

function air_dryer_petugas_for_sheet_query($conn, $tanggal)
{
    $sql = "SELECT Petugas
            FROM dbo.air_dryer
            WHERE CAST(Tanggal AS DATE) = ?
              AND Petugas IS NOT NULL
            ORDER BY Jam_Pengecekan, Id";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal]);
    if ($stmt === false) return '';

    $merged = '';
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $merged = air_dryer_merge_petugas_names($merged, $row['Petugas'] ?? '');
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    return $merged;
}

function air_dryer_values_equal($v1, $v2)
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

function air_dryer_keterangan_for_sheet_query($conn, $tanggal)
{
    $sql = "SELECT DISTINCT Keterangan
            FROM dbo.air_dryer
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
