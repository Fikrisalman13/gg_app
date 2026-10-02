<?php

function dryer_weaving_hours()
{
    return [
        '06:30', '07:30', '08:30', '09:30', '10:30', '11:30',
        '12:30', '13:30', '14:30', '15:30', '16:30', '17:30',
        '18:30', '19:30', '20:30', '21:30', '22:30', '23:30',
        '00:30', '01:30', '02:30', '03:30', '04:30', '05:30'
    ];
}

function dryer_weaving_display_hour($hour)
{
    return $hour === '00:30' ? '24:30' : $hour;
}

function dryer_weaving_items()
{
    return [
        ['key' => 'pb1_dew_point', 'category' => 'DRYER', 'item' => 'pB1<br>Dew Point', 'plain' => 'pB1 Dew Point', 'standard' => '3,0 - 5,0'],
        ['key' => 'pb2_air_inlet_temp', 'category' => 'DRYER', 'item' => 'pB2<br>(Air Inlet Temp)', 'plain' => 'pB2 (Air Inlet Temp)', 'standard' => '7,9 - 9,0'],
        ['key' => 'pb3_ambient_temp', 'category' => 'DRYER', 'item' => 'pB3<br>(Ambient Temp)', 'plain' => 'pB3 (Ambient Temp)', 'standard' => '26 - 33'],
        ['key' => 'pb4_suction_temp', 'category' => 'DRYER', 'item' => 'pB4<br>(Suction Temp)', 'plain' => 'pB4 (Suction Temp)', 'standard' => '4,0 - 7,0'],
        ['key' => 'suction_presure_biru_lp', 'category' => 'DRYER', 'item' => 'Suction Presure<br>(Biru) LP', 'plain' => 'Suction Presure (Biru) LP', 'standard' => '0,3 - 0,8'],
        ['key' => 'discharge_pressure_merah_hp', 'category' => 'DRYER', 'item' => 'Discharge Pressure<br>(Merah) HP', 'plain' => 'Discharge Pressure (Merah) HP', 'standard' => '1,5 - 1,9'],
        ['key' => 'flow_return_pompa_dari_ir', 'category' => 'COOLING TOWER (CT)', 'item' => 'Flow Return pompa<br>dari IR', 'plain' => 'Flow Return pompa dari IR', 'standard' => 'KELUAR AIR (KA)'],
        ['key' => 'periksa_level_bak_ct', 'category' => 'COOLING TOWER (CT)', 'item' => 'Periksa level bak CT', 'plain' => 'Periksa level bak CT', 'standard' => 'PENUH (P)'],
        ['key' => 'suara_getaran_motor_supply_ir', 'category' => 'COOLING TOWER (CT)', 'item' => 'Suara/getaran<br>motor pompa<br>supply ke IR', 'plain' => 'Suara/getaran motor pompa supply ke IR', 'standard' => 'NORMAL (N)'],
        ['key' => 'suara_getaran_motor_sirkulasi_ct', 'category' => 'COOLING TOWER (CT)', 'item' => 'Suara/getaran<br>motor pompa<br>sirkulasi CT', 'plain' => 'Suara/getaran motor pompa sirkulasi CT', 'standard' => 'NORMAL (N)'],
        ['key' => 'periksa_fan_blower', 'category' => 'COOLING TOWER (CT)', 'item' => 'Periksa Fan Blower', 'plain' => 'Periksa Fan Blower', 'standard' => 'ON/FUNGSI (O)'],
        ['key' => 'periksa_tekanan_air_ipab', 'category' => 'COOLING TOWER (CT)', 'item' => 'Periksa tekanan air<br>dari IPAB', 'plain' => 'Periksa tekanan air dari IPAB', 'standard' => '2 - 2,5 bar'],
        ['key' => 'check_ph_bak_ct', 'category' => 'COOLING TOWER (CT)', 'item' => 'Check PH bak CT', 'plain' => 'Check PH bak CT', 'standard' => '6 - 7 ph'],
        ['key' => 'tekanan_air_sebelum_strainer', 'category' => 'COOLING TOWER (CT)', 'item' => 'Periksa tekanan air<br>pada pipa sebelum<br>strainer', 'plain' => 'Periksa tekanan air pada pipa sebelum strainer', 'standard' => '1 - 2,5 bar'],
        ['key' => 'tekanan_air_sesudah_strainer', 'category' => 'COOLING TOWER (CT)', 'item' => 'Periksa tekanan air<br>pada pipa sesudah<br>strainer', 'plain' => 'Periksa tekanan air pada pipa sesudah strainer', 'standard' => '1 - 2,5 bar'],
    ];
}

function dryer_weaving_item_map()
{
    $map = [];
    foreach (dryer_weaving_items() as $item) {
        $map[$item['key']] = $item;
    }
    return $map;
}

function dryer_weaving_shift_options()
{
    return ['NON SHIFT', 'PAGI', 'SIANG', 'MALAM'];
}

function dryer_weaving_no_options()
{
    return ['01', '02'];
}

function dryer_weaving_table_exists($conn)
{
    $stmt = sqlsrv_query($conn, "SELECT OBJECT_ID('dbo.dryer_weaving', 'U') AS table_id");
    if ($stmt === false) return false;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($stmt) sqlsrv_free_stmt($stmt);
    return !empty($row['table_id']);
}

function dryer_weaving_fmt_date($value, $format = 'Y-m-d')
{
    if ($value instanceof DateTime) return $value->format($format);
    if (is_string($value) && $value !== '') {
        $ts = strtotime($value);
        return $ts ? date($format, $ts) : $value;
    }
    return '';
}

function dryer_weaving_fmt_time($value)
{
    if ($value instanceof DateTime) return $value->format('H:i');
    if (is_string($value) && $value !== '') {
        $ts = strtotime($value);
        return $ts ? date('H:i', $ts) : substr($value, 0, 5);
    }
    return '';
}

function dryer_weaving_normalize_compare_text($value)
{
    $value = strtoupper(trim(strip_tags((string)$value)));
    $value = preg_replace('/\s+/', ' ', $value);
    return $value;
}

function dryer_weaving_parse_decimal_value($value)
{
    $value = trim((string)$value);
    if ($value === '') return null;
    if (!preg_match('/^-?\d+([.,]\d+)?$/', $value)) return null;
    return (float)str_replace(',', '.', $value);
}

function dryer_weaving_is_out_of_standard($value, $standard)
{
    $value = trim((string)$value);
    $standard = trim(strip_tags((string)$standard));
    if ($value === '' || $standard === '') return false;

    preg_match_all('/-?\d+(?:[,.]\d+)?/', $standard, $numberMatches);
    if (count($numberMatches[0]) >= 2) {
        $min = (float)str_replace(',', '.', $numberMatches[0][0]);
        $max = (float)str_replace(',', '.', $numberMatches[0][1]);
        $actual = dryer_weaving_parse_decimal_value($value);
        if ($actual === null) return true;
        return $actual < min($min, $max) || $actual > max($min, $max);
    }

    $expected = [dryer_weaving_normalize_compare_text($standard)];
    if (preg_match('/\(([^)]+)\)/', $standard, $match)) {
        $expected[] = dryer_weaving_normalize_compare_text($match[1]);
        $expected[] = dryer_weaving_normalize_compare_text(preg_replace('/\s*\([^)]+\)\s*/', '', $standard));
    }

    return !in_array(dryer_weaving_normalize_compare_text($value), array_unique($expected), true);
}

function dryer_weaving_resolve_sheet($conn, $id)
{
    $sql = "SELECT TOP 1 Id, Tanggal, Dryer_No, Ct_No, Petugas, [Shift] AS ShiftName,
                Keterangan, CreatBy, CreatAt, UpdateBy, UpdateAt
            FROM dbo.dryer_weaving
            WHERE Id = ?";
    $stmt = sqlsrv_query($conn, $sql, [(int)$id]);
    if ($stmt) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        if ($row) return $row;
    }

    // Fallback 1: Jika Id sudah terhapus/tergantikan, cari sheet aktif yang terdekat (Id > $id)
    $fbSql1 = "SELECT TOP 1 Id, Tanggal, Dryer_No, Ct_No, Petugas, [Shift] AS ShiftName,
                    Keterangan, CreatBy, CreatAt, UpdateBy, UpdateAt
                FROM dbo.dryer_weaving
                WHERE Id > ?
                ORDER BY Id ASC";
    $fbStmt1 = sqlsrv_query($conn, $fbSql1, [(int)$id]);
    if ($fbStmt1) {
        $fbRow1 = sqlsrv_fetch_array($fbStmt1, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($fbStmt1);
        if ($fbRow1) return $fbRow1;
    }

    // Fallback 2: Jika Id berada di ujung akhir, cari Id < $id
    $fbSql2 = "SELECT TOP 1 Id, Tanggal, Dryer_No, Ct_No, Petugas, [Shift] AS ShiftName,
                    Keterangan, CreatBy, CreatAt, UpdateBy, UpdateAt
                FROM dbo.dryer_weaving
                WHERE Id < ?
                ORDER BY Id DESC";
    $fbStmt2 = sqlsrv_query($conn, $fbSql2, [(int)$id]);
    if ($fbStmt2) {
        $fbRow2 = sqlsrv_fetch_array($fbStmt2, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($fbStmt2);
        if ($fbRow2) return $fbRow2;
    }

    return null;
}

function dryer_weaving_resolve_sheet_by_keys($conn, $tanggal, $dryerNo, $ctNo)
{
    $sql = "SELECT TOP 1 Id, Tanggal, Dryer_No, Ct_No, Petugas, [Shift] AS ShiftName,
                Keterangan, CreatBy, CreatAt, UpdateBy, UpdateAt
            FROM dbo.dryer_weaving
            WHERE CAST(Tanggal AS DATE) = ?
              AND Dryer_No = ?
              AND Ct_No = ?
            ORDER BY Id ASC";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal, $dryerNo, $ctNo]);
    if ($stmt === false) return null;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($stmt) sqlsrv_free_stmt($stmt);
    return $row ?: null;
}


function dryer_weaving_get_sheet_cells($conn, $tanggal, $dryerNo, $ctNo)
{
    $sql = "SELECT Id, Item_Key, Jam, Nilai, Petugas, [Shift], CreatBy, CreatAt
            FROM dbo.dryer_weaving
            WHERE CAST(Tanggal AS DATE) = ?
              AND Dryer_No = ?
              AND Ct_No = ?";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal, $dryerNo, $ctNo]);
    if ($stmt === false) return [];

    $cells = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $hourKey = dryer_weaving_fmt_time($row['Jam'] ?? null);
        $key = ($row['Item_Key'] ?? '') . '|' . $hourKey;
        $cells[$key] = [
            'id' => $row['Id'] ?? 0,
            'nilai' => $row['Nilai'] ?? '',
            'petugas' => $row['Petugas'] ?? '',
            'shift' => $row['Shift'] ?? '',
            'creat_by' => $row['CreatBy'] ?? '',
            'creat_at' => $row['CreatAt'] ?? null,
        ];
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    return $cells;
}

function dryer_weaving_shifts_for_sheet($cells)
{
    $shifts = [];
    foreach ($cells as $key => $data) {
        $parts = explode('|', (string)$key);
        if (count($parts) === 2) {
            $hour = $parts[1];
            if (!empty($data['shift']) && !isset($shifts[$hour])) {
                $shifts[$hour] = $data['shift'];
            }
        }
    }
    return $shifts;
}

function dryer_weaving_petugas_by_hour_for_sheet($cells)
{
    $petugas = [];
    foreach ($cells as $key => $data) {
        $parts = explode('|', (string)$key);
        if (count($parts) === 2) {
            $hour = $parts[1];
            if (!empty($data['petugas'])) {
                if (!isset($petugas[$hour])) {
                    $petugas[$hour] = $data['petugas'];
                } else {
                    $petugas[$hour] = dryer_weaving_merge_petugas_names($petugas[$hour], $data['petugas']);
                }
            }
        }
    }
    return $petugas;
}

function dryer_weaving_merge_petugas_names($existing, $new)
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

function dryer_weaving_petugas_for_sheet($cells)
{
    $merged = '';
    foreach ($cells as $cell) {
        if (!empty($cell['petugas'])) {
            $merged = dryer_weaving_merge_petugas_names($merged, $cell['petugas']);
        }
    }
    return $merged;
}

function dryer_weaving_petugas_for_sheet_query($conn, $tanggal, $dryerNo, $ctNo)
{
    $sql = "SELECT Petugas
            FROM dbo.dryer_weaving
            WHERE CAST(Tanggal AS DATE) = ?
              AND Dryer_No = ?
              AND Ct_No = ?
              AND Petugas IS NOT NULL
            ORDER BY Jam, Id";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal, $dryerNo, $ctNo]);
    if ($stmt === false) return '';

    $merged = '';
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $merged = dryer_weaving_merge_petugas_names($merged, $row['Petugas'] ?? '');
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    return $merged;
}

function dryer_weaving_get_petugas_options($conn)
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

function dryer_weaving_values_equal($v1, $v2)
{
    if ($v1 === null && $v2 === null) return true;
    if ($v1 === null || $v2 === null) return false;
    $s1 = str_replace(',', '.', trim((string)$v1));
    $s2 = str_replace(',', '.', trim((string)$v2));
    if ($s1 === '' && $s2 === '') return true;
    if ($s1 === '' || $s2 === '') return false;
    if (is_numeric($s1) && is_numeric($s2)) {
        return abs((float)$s1 - (float)$s2) < 0.0001;
    }
    return strtolower(trim((string)$v1)) === strtolower(trim((string)$v2));
}

function dryer_weaving_shifts_for_sheet_query($conn, $tanggal, $dryerNo, $ctNo)
{
    $sql = "SELECT DISTINCT [Shift]
            FROM dbo.dryer_weaving
            WHERE CAST(Tanggal AS DATE) = ?
              AND Dryer_No = ?
              AND Ct_No = ?
              AND [Shift] IS NOT NULL AND [Shift] <> ''
            ORDER BY [Shift]";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal, $dryerNo, $ctNo]);
    if ($stmt === false) return '';

    $shifts = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if (!empty($row['Shift'])) {
            $shifts[] = $row['Shift'];
        }
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    return implode(', ', $shifts);
}

function dryer_weaving_keterangan_for_sheet_query($conn, $tanggal, $dryerNo, $ctNo)
{
    $sql = "SELECT DISTINCT Keterangan
            FROM dbo.dryer_weaving
            WHERE CAST(Tanggal AS DATE) = ?
              AND Dryer_No = ?
              AND Ct_No = ?
              AND Keterangan IS NOT NULL
              AND Keterangan <> ''
            ORDER BY Keterangan";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal, $dryerNo, $ctNo]);
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
