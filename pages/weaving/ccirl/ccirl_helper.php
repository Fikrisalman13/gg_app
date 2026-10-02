<?php

function ccirl_hours()
{
    return [
        '06:30', '07:30', '08:30', '09:30', '10:30', '11:30',
        '12:30', '13:30', '14:30', '15:30', '16:30', '17:30',
        '18:30', '19:30', '20:30', '21:30', '22:30', '23:30',
        '00:30', '01:30', '02:30', '03:30', '04:30', '05:30'
    ];
}

function ccirl_display_hour($hour)
{
    return $hour === '00:30' ? '00:30' : $hour;
}

function ccirl_items()
{
    return [
        ['key' => 'run_time', 'label' => 'Run Time'],
        ['key' => 'vibration_stage_1', 'label' => 'Vibration Stage 1'],
        ['key' => 'vibration_stage_2', 'label' => 'Vibration Stage 2'],
        ['key' => 'vibration_stage_3', 'label' => 'Vibration Stage 3'],
        ['key' => 'inlet_open', 'label' => 'Inlet % Open'],
        ['key' => 'air_temp_stage_1', 'label' => 'Air Temp Stage 1'],
        ['key' => 'air_temp_stage_2', 'label' => 'Air Temp Stage 2'],
        ['key' => 'air_temp_stage_3', 'label' => 'Air Temp Stage 3'],
        ['key' => 'discharge_air_temperature', 'label' => 'Discharge Air Temperatur'],
        ['key' => 'oil_temperature', 'label' => 'Oil Temperatur'],
        ['key' => 'low_oil_temperature', 'label' => 'Low Oil Temperatur'],
        ['key' => 'discharge_press_stage_1', 'label' => 'Discharge Press Stage 1'],
        ['key' => 'discharge_press_stage_2', 'label' => 'Discharge Press Stage 2'],
        ['key' => 'discharge_press_stage_3', 'label' => 'Discharge Press Stage 3'],
        ['key' => 'system_pressure_kompressor', 'label' => 'System Pressure Kompressor'],
        ['key' => 'pressure_header_bar', 'label' => 'Pressure Header (bar)'],
        ['key' => 'pressure_mesin_al_weaving_1_2', 'label' => 'Pressure mesin AL weaving 1 & 2<br>(standard range 4,0 s.d 6 bar)'],
        ['key' => 'pressure_mesin_sizing_warping', 'label' => 'Pressure mesin sizing dan warping<br>(standard range 5,0 s.d 6,5 bar)'],
        ['key' => 'pressure_mesin_df', 'label' => 'Pressure mesin - mesin DF (standard<br>range 5,1 s.d 7 bar)'],
        ['key' => 'system_press_setpoint', 'label' => 'System Press Setpoint'],
        ['key' => 'oil_pressure', 'label' => 'Oil Pressure'],
        ['key' => 'motor_current', 'label' => 'Motor Current'],
        ['key' => 'bypass_open', 'label' => 'Bypass % Open'],
        ['key' => 'seal_air_pressure', 'label' => 'Seal Air Pressure'],
        ['key' => 'dirty_inlet_filter', 'label' => 'Dirty Inlet Filter'],
        ['key' => 'press_water_in', 'label' => 'Press Water IN'],
        ['key' => 'press_water_out', 'label' => 'Press Water OUT'],
        ['key' => 'temp_water_in', 'label' => 'Temp Water IN'],
        ['key' => 'temp_water_out', 'label' => 'Temp Water OUT'],
    ];
}

function ccirl_item_map()
{
    $map = [];
    foreach (ccirl_items() as $item) $map[$item['key']] = $item;
    return $map;
}

function ccirl_no_options()
{
    return ['1', '2'];
}

function ccirl_table_exists($conn)
{
    $stmt = sqlsrv_query($conn, "SELECT OBJECT_ID('dbo.ccirl', 'U') AS table_id");
    if ($stmt === false) return false;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($stmt) sqlsrv_free_stmt($stmt);
    return !empty($row['table_id']);
}

function ccirl_fmt_date($value, $format = 'Y-m-d')
{
    if ($value instanceof DateTime) return $value->format($format);
    if (is_string($value) && $value !== '') {
        $ts = strtotime($value);
        return $ts ? date($format, $ts) : $value;
    }
    return '';
}

function ccirl_fmt_time($value)
{
    if ($value instanceof DateTime) return $value->format('H:i');
    if (is_string($value) && $value !== '') {
        $ts = strtotime($value);
        return $ts ? date('H:i', $ts) : substr($value, 0, 5);
    }
    return '';
}

function ccirl_get_petugas_options($conn)
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
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $options[] = $row['nama_lengkap'];
        sqlsrv_free_stmt($stmt);
    }
    return $options;
}

function ccirl_merge_petugas_names($existing, $new)
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

function ccirl_petugas_for_hour($cells, $items, $hour)
{
    $merged = '';
    foreach ($items as $item) {
        $key = $item['key'] . '|' . $hour;
        if (!empty($cells[$key]['petugas'])) {
            $merged = ccirl_merge_petugas_names($merged, $cells[$key]['petugas']);
        }
    }
    return $merged;
}

function ccirl_petugas_for_sheet($cells)
{
    $merged = '';
    foreach ($cells as $cell) {
        if (!empty($cell['petugas'])) {
            $merged = ccirl_merge_petugas_names($merged, $cell['petugas']);
        }
    }
    return $merged;
}

function ccirl_get_sheet($conn, $id)
{
    $sql = "SELECT TOP 1 Id, Tanggal, Compressor_No, Petugas, Keterangan, CreatBy, CreatAt, UpdateBy, UpdateAt
            FROM dbo.ccirl
            WHERE Id = ?";
    $stmt = sqlsrv_query($conn, $sql, [(int)$id]);
    if ($stmt === false) return null;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($stmt) sqlsrv_free_stmt($stmt);
    return $row ?: null;
}

function ccirl_get_cells($conn, $tanggal, $compressorNo)
{
    $sql = "SELECT Id, Item_Key, Jam, Nilai, Petugas, CreatBy, CreatAt
            FROM dbo.ccirl
            WHERE CAST(Tanggal AS DATE) = ?
              AND Compressor_No = ?";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal, $compressorNo]);
    if ($stmt === false) return [];
    $cells = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $key = ($row['Item_Key'] ?? '') . '|' . ccirl_fmt_time($row['Jam'] ?? null);
        $cells[$key] = [
            'id' => $row['Id'] ?? 0,
            'nilai' => $row['Nilai'] ?? '',
            'petugas' => $row['Petugas'] ?? '',
            'creat_by' => $row['CreatBy'] ?? '',
            'creat_at' => $row['CreatAt'] ?? null,
        ];
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    return $cells;
}

function ccirl_values_equal($v1, $v2)
{
    if ($v1 === null && $v2 === null) return true;
    if ($v1 === null || $v2 === null) {
        $nonNull = $v1 !== null ? trim((string)$v1) : trim((string)$v2);
        return $nonNull === '';
    }
    $s1 = trim(str_replace(',', '.', (string)$v1));
    $s2 = trim(str_replace(',', '.', (string)$v2));
    if ($s1 === $s2) return true;
    if (is_numeric($s1) && is_numeric($s2)) {
        return abs((float)$s1 - (float)$s2) < 0.00001;
    }
    return false;
}

function ccirl_keterangan_for_sheet_query($conn, $tanggal, $compressorNo)
{
    $sql = "SELECT TOP 1 Keterangan FROM dbo.ccirl WHERE CAST(Tanggal AS DATE) = ? AND Compressor_No = ? AND Keterangan IS NOT NULL AND Keterangan <> '' ORDER BY Id DESC";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal, $compressorNo]);
    if ($stmt) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        if ($row && !empty($row['Keterangan'])) return $row['Keterangan'];
    }
    return '';
}

