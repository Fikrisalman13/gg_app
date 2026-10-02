<?php

function ac_weaving_hours()
{
    return [
        '06:30', '07:30', '08:30', '09:30', '10:30', '11:30',
        '12:30', '13:30', '14:30', '15:30', '16:30', '17:30',
        '18:30', '19:30', '20:30', '21:30', '22:30', '23:30',
        '00:30', '01:30', '02:30', '03:30', '04:30', '05:30'
    ];
}

function ac_weaving_display_hour($hour)
{
    return $hour;
}

function ac_weaving_items()
{
    return [
        ['key' => 'dew_point', 'column' => 'Pb1_Dew_Point', 'item' => 'pB1<br>Dew Point', 'plain' => 'pB1 Dew Point', 'decimals' => 2],
        ['key' => 'humidity', 'column' => 'Humidity', 'item' => 'Humidity', 'plain' => 'Humidity', 'decimals' => 1],
        ['key' => 'amper', 'column' => 'Amper', 'item' => 'Amper', 'plain' => 'Amper', 'decimals' => 0],
        ['key' => 'differential', 'column' => 'Differential_Best_Air', 'item' => 'Differential<br>Best Air', 'plain' => 'Differential Best Air', 'decimals' => 0],
    ];
}

function ac_weaving_item_map()
{
    $map = [];
    foreach (ac_weaving_items() as $item) {
        $map[$item['key']] = $item;
    }
    return $map;
}

function ac_weaving_shift_options()
{
    return ['NON SHIFT', 'PAGI', 'SIANG', 'MALAM'];
}

function ac_weaving_mesin_options()
{
    return ['AC WEAVING 1', 'AC WEAVING 2'];
}

function ac_weaving_table_exists($conn)
{
    $stmt = sqlsrv_query($conn, "SELECT OBJECT_ID('dbo.ac_weaving', 'U') AS table_id");
    if ($stmt === false) return false;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($stmt) sqlsrv_free_stmt($stmt);
    return !empty($row['table_id']);
}

function ac_weaving_fmt_date($value, $format = 'Y-m-d')
{
    if ($value instanceof DateTime) return $value->format($format);
    if (is_string($value) && $value !== '') {
        $ts = strtotime($value);
        return $ts ? date($format, $ts) : $value;
    }
    return '';
}

function ac_weaving_fmt_time($value)
{
    if ($value instanceof DateTime) return $value->format('H:i');
    if (is_string($value) && $value !== '') {
        $ts = strtotime($value);
        return $ts ? date('H:i', $ts) : substr($value, 0, 5);
    }
    return '';
}

function ac_weaving_normalize_decimal($value)
{
    $value = trim((string)$value);
    if ($value === '') return null;
    $value = preg_replace('/[^0-9.,]/', '', $value);
    if ($value === '') return null;

    if (strpos($value, '.') !== false) {
        $v = str_replace(',', '', $value);
        if (is_numeric($v)) return $v;
    }

    $fallback = str_replace('.', '', $value);
    $fallback = str_replace(',', '.', $fallback);
    return is_numeric($fallback) ? $fallback : null;
}

function ac_weaving_fmt_num($value, $decimals = 2)
{
    if ($value === null || $value === '') return '';
    if (!is_numeric($value)) return (string)$value;
    return number_format((float)$value, $decimals, '.', '');
}

function ac_weaving_resolve_sheet($conn, $id)
{
    $sql = "SELECT TOP 1 Id, Mesin, Tanggal, Petugas, [Shift] AS ShiftName,
                Keterangan, CreatBy, CreatAt, UpdateBy, UpdateAt
            FROM dbo.ac_weaving
            WHERE Id = ?";
    $stmt = sqlsrv_query($conn, $sql, [(int)$id]);
    if ($stmt === false) return null;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($stmt) sqlsrv_free_stmt($stmt);
    return $row ?: null;
}

function ac_weaving_resolve_sheet_by_keys($conn, $tanggal, $mesin)
{
    $sql = "SELECT TOP 1 Id, Mesin, Tanggal, Petugas, [Shift] AS ShiftName,
                Keterangan, CreatBy, CreatAt, UpdateBy, UpdateAt
            FROM dbo.ac_weaving
            WHERE CAST(Tanggal AS DATE) = ?
              AND Mesin = ?
            ORDER BY Id ASC";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal, $mesin]);
    if ($stmt === false) return null;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($stmt) sqlsrv_free_stmt($stmt);
    return $row ?: null;
}

function ac_weaving_get_sheet_cells($conn, $tanggal, $mesin)
{
    $nextDate = date('Y-m-d', strtotime($tanggal . ' +1 day'));
    $sql = "SELECT Id, Mesin, Tanggal, Jam, Aktual_Check,
                Pb1_Dew_Point, Humidity, Amper, Differential_Best_Air,
                Petugas, [Shift], Keterangan, CreatBy, CreatAt
            FROM dbo.ac_weaving
            WHERE Mesin = ?
              AND (
                  CAST(Tanggal AS DATE) = ?
                  OR (CAST(Tanggal AS DATE) = ? AND CAST(Jam AS TIME) <= '05:30:00')
              )
            ORDER BY CASE WHEN CAST(Jam AS TIME) >= '06:00:00' THEN 0 ELSE 1 END ASC,
                     CAST(Jam AS TIME) ASC, Id ASC";
    $stmt = sqlsrv_query($conn, $sql, [$mesin, $tanggal, $nextDate]);
    if ($stmt === false) return [];

    $cells = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $hourKey = ac_weaving_fmt_time($row['Jam'] ?? null);
        $rowDate = ac_weaving_fmt_date($row['Tanggal'] ?? null, 'Y-m-d');

        // If we already have a cell for this hour from the exact $tanggal, don't overwrite with $nextDate
        if (isset($cells[$hourKey]) && $rowDate !== $tanggal) {
            continue;
        }

        $cells[$hourKey] = [
            'id' => $row['Id'] ?? 0,
            'jam' => $hourKey,
            'aktual_check' => ac_weaving_fmt_time($row['Aktual_Check'] ?? null),
            'dew_point' => ac_weaving_fmt_num($row['Pb1_Dew_Point'] ?? null, 2),
            'humidity' => ac_weaving_fmt_num($row['Humidity'] ?? null, 1),
            'amper' => ac_weaving_fmt_num($row['Amper'] ?? null, 0),
            'differential' => ac_weaving_fmt_num($row['Differential_Best_Air'] ?? null, 0),
            'petugas' => (string)($row['Petugas'] ?? ''),
            'shift' => (string)($row['Shift'] ?? ''),
            'keterangan' => (string)($row['Keterangan'] ?? ''),
            'creat_by' => $row['CreatBy'] ?? null,
            'creat_at' => $row['CreatAt'] ?? null,
        ];
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    return $cells;
}

function ac_weaving_shifts_for_sheet($cells)
{
    $shifts = [];
    foreach ($cells as $hour => $data) {
        if (!empty($data['shift']) && !isset($shifts[$hour])) {
            $shifts[$hour] = $data['shift'];
        }
    }
    return $shifts;
}

function ac_weaving_petugas_by_hour_for_sheet($cells)
{
    $petugas = [];
    foreach ($cells as $hour => $data) {
        if (!empty($data['petugas'])) {
            if (!isset($petugas[$hour])) {
                $petugas[$hour] = $data['petugas'];
            } else {
                $petugas[$hour] = ac_weaving_merge_petugas_names($petugas[$hour], $data['petugas']);
            }
        }
    }
    return $petugas;
}

function ac_weaving_merge_petugas_names($existing, $new)
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

function ac_weaving_petugas_for_sheet($cells)
{
    $merged = '';
    foreach ($cells as $cell) {
        if (!empty($cell['petugas'])) {
            $merged = ac_weaving_merge_petugas_names($merged, $cell['petugas']);
        }
    }
    return $merged;
}

function ac_weaving_petugas_for_sheet_query($conn, $tanggal, $mesin)
{
    $sql = "SELECT Petugas
            FROM dbo.ac_weaving
            WHERE CAST(Tanggal AS DATE) = ?
              AND Mesin = ?
              AND Petugas IS NOT NULL
            ORDER BY Jam, Id";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal, $mesin]);
    if ($stmt === false) return '';

    $merged = '';
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $merged = ac_weaving_merge_petugas_names($merged, $row['Petugas'] ?? '');
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    return $merged;
}

function ac_weaving_shifts_for_sheet_query($conn, $tanggal, $mesin)
{
    $sql = "SELECT DISTINCT [Shift]
            FROM dbo.ac_weaving
            WHERE CAST(Tanggal AS DATE) = ?
              AND Mesin = ?
              AND [Shift] IS NOT NULL
              AND LTRIM(RTRIM([Shift])) <> ''";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal, $mesin]);
    if ($stmt === false) return '';

    $foundShifts = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $shift = trim((string)($row['Shift'] ?? ''));
        if ($shift !== '') {
            $foundShifts[strtoupper($shift)] = $shift;
        }
    }
    if ($stmt) sqlsrv_free_stmt($stmt);

    if (empty($foundShifts)) return '';

    $order = ['NON SHIFT', 'PAGI', 'SIANG', 'MALAM'];
    $ordered = [];
    foreach ($order as $std) {
        if (isset($foundShifts[$std])) {
            $ordered[] = $foundShifts[$std];
            unset($foundShifts[$std]);
        }
    }
    foreach ($foundShifts as $rem) {
        $ordered[] = $rem;
    }

    return implode(', ', $ordered);
}


function ac_weaving_get_petugas_options($conn)
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

function ac_weaving_load_report_data($conn, $startDate, $endDate)
{
    $reportRows = ['AC WEAVING 1' => [], 'AC WEAVING 2' => []];
    $reportKeterangan = ['AC WEAVING 1' => [], 'AC WEAVING 2' => []];

    $startTs = strtotime($startDate);
    $endTs = strtotime($endDate);
    if (!$startTs || !$endTs) {
        return [$reportRows, $reportKeterangan];
    }

    $dates = [];
    for ($ts = $startTs; $ts <= $endTs; $ts = strtotime('+1 day', $ts)) {
        $dates[] = date('Y-m-d', $ts);
    }

    $hours = ac_weaving_hours();
    $fetchEnd = date('Y-m-d', strtotime($endDate . ' +1 day'));

    $sql = "SELECT Id, Mesin, CAST(Tanggal AS DATE) AS Tanggal, CONVERT(VARCHAR(5), Jam, 108) AS Jam,
                   Aktual_Check, Pb1_Dew_Point, Humidity, Amper, Differential_Best_Air,
                   Petugas, [Shift] AS ShiftName, Keterangan
            FROM dbo.ac_weaving
            WHERE CAST(Tanggal AS DATE) BETWEEN ? AND ?
              AND Mesin IN ('AC WEAVING 1', 'AC WEAVING 2')
            ORDER BY Mesin ASC, CAST(Tanggal AS DATE) ASC,
                     CASE WHEN CAST(Jam AS TIME) >= '06:00:00' THEN 0 ELSE 1 END ASC,
                     CAST(Jam AS TIME) ASC, Id ASC";
    $stmt = sqlsrv_query($conn, $sql, [$startDate, $fetchEnd]);
    if ($stmt === false) {
        return false;
    }

    $indexed = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $m = $row['Mesin'] ?? '';
        $t = $row['Tanggal'];
        if ($t instanceof DateTime) $t = $t->format('Y-m-d');
        $j = $row['Jam'];
        if ($j instanceof DateTime) $j = $j->format('H:i');
        elseif (is_string($j)) $j = substr($j, 0, 5);

        $indexed[$m][$t][$j] = $row;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);

    foreach (['AC WEAVING 1', 'AC WEAVING 2'] as $machine) {
        foreach ($dates as $sheetDate) {
            $nextDate = date('Y-m-d', strtotime($sheetDate . ' +1 day'));
            $sheetDateDisplay = date('d/m/Y', strtotime($sheetDate));

            foreach ($hours as $hour) {
                $row = null;
                if ($hour >= '06:00') {
                    $row = $indexed[$machine][$sheetDate][$hour] ?? null;
                } else {
                    if (isset($indexed[$machine][$sheetDate][$hour])) {
                        $row = $indexed[$machine][$sheetDate][$hour];
                    } elseif (isset($indexed[$machine][$nextDate][$hour])) {
                        $row = $indexed[$machine][$nextDate][$hour];
                    }
                }

                if ($row) {
                    $dewPoint = $row['Pb1_Dew_Point'] !== null ? number_format((float)$row['Pb1_Dew_Point'], 2, '.', '') : '';
                    $humidity = $row['Humidity'] !== null ? number_format((float)$row['Humidity'], 1, '.', '') : '';
                    $amper = $row['Amper'] !== null ? number_format((float)$row['Amper'], 0, '.', '') : '';
                    $diff = $row['Differential_Best_Air'] !== null ? number_format((float)$row['Differential_Best_Air'], 0, '.', '') : '';
                    
                    $aktual = '';
                    if ($row['Aktual_Check'] instanceof DateTime) $aktual = $row['Aktual_Check']->format('H:i');
                    elseif (is_string($row['Aktual_Check']) && $row['Aktual_Check'] !== '') $aktual = substr($row['Aktual_Check'], 0, 5);

                    $reportRows[$machine][] = [
                        'tanggal' => $sheetDateDisplay,
                        'jam' => $hour,
                        'aktual_check' => $aktual,
                        'dew_point' => $dewPoint,
                        'humidity' => $humidity,
                        'amper' => $amper,
                        'differential' => $diff,
                        'petugas' => (string)($row['Petugas'] ?? ''),
                        'shift' => (string)($row['ShiftName'] ?? ''),
                    ];

                    $ket = trim((string)($row['Keterangan'] ?? ''));
                    if ($ket !== '' && !in_array($ket, $reportKeterangan[$machine], true)) {
                        $reportKeterangan[$machine][] = $ket;
                    }
                }
            }
        }
    }

    return [$reportRows, $reportKeterangan];
}
