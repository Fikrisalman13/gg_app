<?php
session_start();
ob_start();

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

include '../../../koneksi.php';
include '../../../koneksi3.php';
include '../../../includes/header.php';
include '../../../includes/sidebar.php';

$themeColor = $_SESSION['Theme'] ?? 'primary';

$todayIso = date('Y-m-d');
$fromDateInput = trim((string)($_GET['from_date'] ?? ($_GET['period_date'] ?? $todayIso)));
$toDateInput = trim((string)($_GET['to_date'] ?? ($_GET['period_date'] ?? $todayIso)));
$planTypeInput = trim((string)($_GET['plan_type'] ?? ''));
$machineFilterInput = $_GET['machine_id'] ?? '';
$layout = trim((string)($_GET['layout'] ?? ''));
$isDateIso = static function (string $value): bool {
    return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
};
$fromDate = $isDateIso($fromDateInput) ? $fromDateInput : $todayIso;
$toDate = $isDateIso($toDateInput) ? $toDateInput : $todayIso;
$rangeSwapped = false;
if ($fromDate > $toDate) {
    $tmp = $fromDate;
    $fromDate = $toDate;
    $toDate = $tmp;
    $rangeSwapped = true;
}

$formatDateIndo = static function (string $value): string {
    if ($value === '') {
        return '-';
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date) {
        $ts = strtotime($value);
        if ($ts === false) {
            return '-';
        }
        $date = new DateTime(date('Y-m-d', $ts));
    }

    $dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $monthNames = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    $day = $dayNames[(int)$date->format('w')] ?? '';
    $month = $monthNames[(int)$date->format('n')] ?? '';

    return $day . ', ' . (int)$date->format('j') . ' ' . $month . ' ' . $date->format('Y');
};

$esc = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$formatDateCell = static function ($value): string {
    if ($value === null || $value === '') {
        return '-';
    }
    if ($value instanceof DateTimeInterface) {
        return $value->format('d/m/Y');
    }
    $raw = trim((string)$value);
    if ($raw === '') {
        return '-';
    }
    $formats = ['Y-m-d', 'Y-m-d H:i:s', 'd/m/Y', 'd-m-Y', 'Y-m-d H:i:s.u'];
    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $raw);
        if ($dt instanceof DateTime) {
            return $dt->format('d/m/Y');
        }
    }
    $ts = strtotime($raw);
    return ($ts !== false) ? date('d/m/Y', $ts) : $raw;
};

$formatDateShort = static function ($value): string {
    if ($value === null || $value === '') {
        return '-';
    }
    if ($value instanceof DateTimeInterface) {
        return $value->format('d-M-y');
    }
    $raw = trim((string)$value);
    if ($raw === '') {
        return '-';
    }
    $formats = ['Y-m-d', 'Y-m-d H:i:s', 'd/m/Y', 'd-m-Y', 'Y-m-d H:i:s.u'];
    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $raw);
        if ($dt instanceof DateTime) {
            return $dt->format('d-M-y');
        }
    }
    $ts = strtotime($raw);
    return ($ts !== false) ? date('d-M-y', $ts) : $raw;
};

$formatTimeShort = static function ($value): string {
    if ($value === null || $value === '') {
        return '-';
    }
    if ($value instanceof DateTimeInterface) {
        return $value->format('H:i');
    }
    $raw = trim((string)$value);
    if ($raw === '') {
        return '-';
    }
    if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $raw)) {
        return substr($raw, 0, 5);
    }
    $ts = strtotime($raw);
    return ($ts !== false) ? date('H:i', $ts) : $raw;
};

$formatQty = static function ($value): string {
    if ($value === null || $value === '') {
        return '-';
    }
    if (is_numeric($value)) {
        $formatted = number_format((float)$value, 0, '.', ',');
        return $formatted === '-0' ? '0' : $formatted;
    }
    $raw = trim((string)$value);
    if ($raw === '' || $raw === '-') {
        return '-';
    }
    $normalized = str_replace(',', '', $raw);
    if (is_numeric($normalized)) {
        $formatted = number_format((float)$normalized, 0, '.', ',');
        return $formatted === '-0' ? '0' : $formatted;
    }
    return $raw;
};

$toQtyNumber = static function ($value): float {
    if ($value === null || $value === '') {
        return 0.0;
    }
    if (is_numeric($value)) {
        return (float)$value;
    }
    $raw = trim((string)$value);
    if ($raw === '' || $raw === '-') {
        return 0.0;
    }
    $normalized = str_replace(',', '', $raw);
    return is_numeric($normalized) ? (float)$normalized : 0.0;
};

$normalizeCpKey = static function ($value): string {
    return strtoupper(trim((string)$value));
};

$normalizeTextUpper = static function ($value): string {
    return strtoupper(trim((string)$value));
};

$buildInPlaceholders = static function (array $items, string $prefix): array {
    $items = array_values($items);
    $params = [];
    $holders = [];
    foreach ($items as $idx => $val) {
        $key = ':' . $prefix . $idx;
        $holders[] = $key;
        $params[$key] = $val;
    }
    return [$holders, $params];
};

$normalizePlanTypeKey = static function ($value): string {
    $key = strtolower(trim((string)$value));
    if ($key === 'bakar bulu') {
        return 'bakar_bulu';
    }
    if ($key === 'jet dyeing') {
        return 'jet_dyeing';
    }
    if (
        $key === 'washing & padsteam' ||
        $key === 'washing and padsteam' ||
        $key === 'washing padsteam' ||
        $key === 'washing pad steam' ||
        $key === 'washing & pad steam'
    ) {
        return 'washing_padsteam';
    }
    if ($key === 'cpb') {
        return 'cpb';
    }
    if ($key === 'paddry' || $key === 'planning paddry' || $key === 'plan paddry') {
        return 'paddry';
    }
    if ($key === 'scouring') {
        return 'scouring';
    }
    if ($key === 'presett' || $key === 'pre sett') {
        return 'presett';
    }
    return $key;
};

$normalizeMachineIdValue = static function ($value): string {
    if (is_array($value)) {
        $value = $value[0] ?? '';
    }
    return trim((string)$value);
};

$isBreakTimeCpNo = static function (string $cpNo): bool {
    $normalized = strtolower(trim($cpNo));
    if ($normalized === '') {
        return false;
    }

    $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
    return strpos($normalized, 'break') !== false
        || strpos($normalized, 'cleaning') !== false
        || strpos($normalized, 'bilas') !== false
        || strpos($normalized, 'pm +') !== false
        || strpos($normalized, 'pm+') !== false;
};

$isTruthyFlag = static function ($value): bool {
    if ($value === null) {
        return false;
    }
    if (is_bool($value)) {
        return $value;
    }
    $normalized = strtolower(trim((string)$value));
    return in_array($normalized, ['1', 'true', 'y', 'yes'], true);
};

$sortMachinesByDisplayOrder = static function (array &$machines): void {
    $extractPadDryNumber = static function (array $row): ?int {
        $candidates = [
            trim((string)($row['machine_name'] ?? '')),
            trim((string)($row['machine_id'] ?? '')),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $normalized = strtoupper($candidate);
            $normalized = str_replace("\xc2\xa0", ' ', $normalized);
            $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
            $compact = preg_replace('/[^A-Z0-9]+/', '', $normalized) ?? $normalized;

            if (preg_match('/PADDRY(\d+)/', $compact, $m) === 1) {
                return (int)$m[1];
            }

            if (preg_match('/PAD\s*DRY\s*(\d+)/i', $normalized, $m) === 1) {
                return (int)$m[1];
            }
        }

        return null;
    };

    usort($machines, static function (array $a, array $b) use ($extractPadDryNumber): int {
        $nameA = strtoupper(trim((string)($a['machine_name'] ?? '')));
        $nameB = strtoupper(trim((string)($b['machine_name'] ?? '')));
        $nameA = preg_replace('/\s+/', ' ', $nameA) ?? $nameA;
        $nameB = preg_replace('/\s+/', ' ', $nameB) ?? $nameB;
        $padDryNumA = $extractPadDryNumber($a);
        $padDryNumB = $extractPadDryNumber($b);
        if ($padDryNumA !== null && $padDryNumB !== null && $padDryNumA !== $padDryNumB) {
            return $padDryNumA <=> $padDryNumB;
        }
        if ($padDryNumA !== null && $padDryNumB === null) {
            return -1;
        }
        if ($padDryNumA === null && $padDryNumB !== null) {
            return 1;
        }

        $cmpName = strnatcasecmp($nameA, $nameB);
        if ($cmpName !== 0) {
            return $cmpName;
        }

        $idA = strtoupper(trim((string)($a['machine_id'] ?? '')));
        $idB = strtoupper(trim((string)($b['machine_id'] ?? '')));
        return strnatcasecmp($idA, $idB);
    });
};

$tableExists = static function ($conn, string $tableName): bool {
    $stmt = sqlsrv_query(
        $conn,
        "SELECT TOP 1 1 AS ok FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = ?",
        [$tableName]
    );
    if ($stmt === false) {
        return false;
    }
    $exists = (bool)sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $exists;
};

$getTableColumns = static function ($conn, string $tableName): array {
    $stmt = sqlsrv_query(
        $conn,
        "SELECT LOWER(COLUMN_NAME) AS col_name FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = ?",
        [$tableName]
    );
    if ($stmt === false) {
        return [];
    }
    $cols = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $col = strtolower(trim((string)($row['col_name'] ?? '')));
        if ($col !== '') {
            $cols[$col] = true;
        }
    }
    sqlsrv_free_stmt($stmt);
    return $cols;
};

$resolveColumn = static function (array $availableCols, array $candidates): ?string {
    foreach ($candidates as $candidate) {
        $key = strtolower(trim((string)$candidate));
        if ($key !== '' && isset($availableCols[$key])) {
            return $key;
        }
    }
    return null;
};

$rowValue = static function (array $rowLower, array $candidates, $default = null) {
    foreach ($candidates as $candidate) {
        $key = strtolower(trim((string)$candidate));
        if ($key !== '' && array_key_exists($key, $rowLower)) {
            return $rowLower[$key];
        }
    }
    return $default;
};

$periodLabel = ($fromDate === $toDate)
    ? $formatDateIndo($fromDate)
    : ($formatDateIndo($fromDate) . ' s/d ' . $formatDateIndo($toDate));
$generatedAt = date('d/m/Y H:i');
$reportRows = [];
$pageWarnings = [];
$pageError = '';
$failGroupKeys = [];
$failGroupLabels = [];
$failGroupEncounterOrder = [];
$verpackingGroupKey = '__VERPACKING_692__';
$cpTotalCount = 0;
$cpFailCount = 0;
$cpPassCount = 0;
$cpTotalQty = 0.0;
$cpFailQty = 0.0;
$cpPassQty = 0.0;

// Kolom VERPACKING wajib tampil di ujung tabel.
$failGroupLabels[$verpackingGroupKey] = 'VERPACKING';
$failGroupKeys[] = $verpackingGroupKey;
$failGroupEncounterOrder[$verpackingGroupKey] = count($failGroupEncounterOrder);

$planningRows = [];
$planningRowCount = 0;
$cpKeyMap = [];
$masterPlanTypes = [];
$machineMasterMapByPlanType = [];
$masterMachines = [];
$targetMachineIds = [];
$targetMachineLabels = [];
$machineNameById = [];
$requestedMachineId = $normalizeMachineIdValue($machineFilterInput);
$planType = '';
$planTypeLabel = '-';

if ($rangeSwapped) {
    $pageWarnings[] = 'Tanggal awal lebih besar dari tanggal akhir. Sistem menukar otomatis rentang tanggal.';
}

$sqlMasterPlanType = "
    SELECT
        LTRIM(RTRIM(CAST(tipe_planning_name AS NVARCHAR(100)))) AS plan_type
    FROM dbo.ms_tipe_planning
    ORDER BY tipe_planning_id ASC
";
$stmtMasterPlanType = sqlsrv_query($conn, $sqlMasterPlanType);
if ($stmtMasterPlanType === false) {
    $pageWarnings[] = 'Master tipe planning tidak bisa dibaca. Pilihan tipe planning memakai input URL.';
} else {
    $planTypeMap = [];
    while ($pt = sqlsrv_fetch_array($stmtMasterPlanType, SQLSRV_FETCH_ASSOC)) {
        $planTypeName = trim((string)($pt['plan_type'] ?? ''));
        if ($planTypeName === '') {
            continue;
        }
        $masterPlanTypes[] = $planTypeName;
        $planTypeMap[strtolower($planTypeName)] = $planTypeName;
    }
    sqlsrv_free_stmt($stmtMasterPlanType);

    if (!empty($masterPlanTypes)) {
        $inputKey = strtolower($planTypeInput);
        if ($inputKey !== '' && isset($planTypeMap[$inputKey])) {
            $planType = $planTypeMap[$inputKey];
        } elseif ($inputKey !== '' && !isset($planTypeMap[$inputKey])) {
            foreach ($masterPlanTypes as $candidatePlanType) {
                if (stripos($candidatePlanType, 'paddry') !== false) {
                    $planType = $candidatePlanType;
                    break;
                }
            }
            if ($planType === '') {
                $planType = $masterPlanTypes[0];
            }
            $pageWarnings[] = 'Tipe planning dari URL tidak ada di master. Sistem memakai tipe planning default dari master.';
        } else {
            foreach ($masterPlanTypes as $candidatePlanType) {
                if (stripos($candidatePlanType, 'paddry') !== false) {
                    $planType = $candidatePlanType;
                    break;
                }
            }
            if ($planType === '') {
                $planType = $masterPlanTypes[0];
            }
        }
    }
}

if ($planType === '' && $planTypeInput !== '') {
    $planType = $planTypeInput;
}
$planTypeLabel = $planType !== '' ? $planType : '-';

$sqlMachineMasterByPlanType = "
    SELECT
        LTRIM(RTRIM(CAST(planning_type AS NVARCHAR(100)))) AS planning_type,
        LTRIM(RTRIM(CAST(machine_id AS NVARCHAR(100)))) AS machine_id,
        LTRIM(RTRIM(CAST(machine_name AS NVARCHAR(200)))) AS machine_name
    FROM dbo.ms_machine
    WHERE LTRIM(RTRIM(ISNULL(CAST(machine_id AS NVARCHAR(100)), ''))) <> ''
      AND LTRIM(RTRIM(ISNULL(CAST(planning_type AS NVARCHAR(100)), ''))) <> ''
    ORDER BY planning_type ASC, machine_name ASC, machine_id ASC
";
$stmtMachineMasterByPlanType = sqlsrv_query($conn, $sqlMachineMasterByPlanType);
if ($stmtMachineMasterByPlanType !== false) {
    $machineSeenPerPlanType = [];
    while ($mrow = sqlsrv_fetch_array($stmtMachineMasterByPlanType, SQLSRV_FETCH_ASSOC)) {
        $machinePlanType = trim((string)($mrow['planning_type'] ?? ''));
        $machineId = trim((string)($mrow['machine_id'] ?? ''));
        $machineName = trim((string)($mrow['machine_name'] ?? ''));
        if ($machinePlanType === '' || $machineId === '') {
            continue;
        }

        $planTypeKey = strtolower($machinePlanType);
        if (!isset($machineMasterMapByPlanType[$planTypeKey])) {
            $machineMasterMapByPlanType[$planTypeKey] = [];
            $machineSeenPerPlanType[$planTypeKey] = [];
        }

        $machineKey = strtoupper($machineId);
        if (isset($machineSeenPerPlanType[$planTypeKey][$machineKey])) {
            continue;
        }
        $machineSeenPerPlanType[$planTypeKey][$machineKey] = true;

        $machineMasterMapByPlanType[$planTypeKey][] = [
            'machine_id' => $machineId,
            'machine_name' => $machineName !== '' ? $machineName : $machineId,
        ];
    }
    sqlsrv_free_stmt($stmtMachineMasterByPlanType);
    foreach ($machineMasterMapByPlanType as $planTypeKey => $machineList) {
        if (!is_array($machineList) || empty($machineList)) {
            continue;
        }
        $sortMachinesByDisplayOrder($machineList);
        $machineMasterMapByPlanType[$planTypeKey] = array_values($machineList);
    }
} else {
    $pageWarnings[] = 'Master machine per tipe planning tidak bisa dibaca.';
}

$planTypeToTableMap = [
    'paddry' => 'cpp_paddry',
    'bakar_bulu' => 'cpp_bakar_bulu',
    'scouring' => 'cpp_scouring',
    'presett' => 'cpp_presett',
    'cpb' => 'cpp_cpb',
    'washing_padsteam' => 'cpp_washing_padsteam',
    'jet_dyeing' => 'cpp_jet_dyeing',
];
$planTypeKey = $normalizePlanTypeKey($planType !== '' ? $planType : $layout);
$planningTable = $planTypeToTableMap[$planTypeKey] ?? 'cpp_paddry';
if (!$tableExists($conn, $planningTable)) {
    $fallbackTable = 'cpp_paddry';
    if ($planningTable !== $fallbackTable && $tableExists($conn, $fallbackTable)) {
        $pageWarnings[] = 'Tabel planning untuk tipe ini tidak ditemukan. Sistem memakai tabel paddry.';
        $planningTable = $fallbackTable;
    } else {
        $pageError = 'Tabel sumber planning tidak ditemukan.';
    }
}

$planningTableCols = $pageError === '' ? $getTableColumns($conn, $planningTable) : [];
$planningMachineCol = $resolveColumn($planningTableCols, ['machine_id']);
$planningDateCol = $resolveColumn($planningTableCols, ['tgl', 'plan_date', 'period_date']);
$planningSeqCol = $resolveColumn($planningTableCols, ['seq_no', 'id']);

if ($pageError === '' && ($planningMachineCol === null || $planningDateCol === null || !isset($planningTableCols['cp_no']) || !isset($planningTableCols['qty']))) {
    $pageError = 'Struktur tabel sumber planning tidak sesuai (wajib: machine_id, tanggal, cp_no, qty).';
}

$masterMachines = $machineMasterMapByPlanType[strtolower($planType)] ?? [];

foreach ($masterMachines as $machineRow) {
    $mid = $machineRow['machine_id'];
    $mname = $machineRow['machine_name'];
    $machineNameById[strtoupper($mid)] = $mname;
}

if (empty($masterMachines)) {
    $pageWarnings[] = 'Belum ada machine master untuk tipe planning ini.';
}

$requestedMachineMatched = false;
if ($requestedMachineId !== '') {
    foreach ($masterMachines as $machineRow) {
        $machineId = trim((string)($machineRow['machine_id'] ?? ''));
        if ($machineId === '') {
            continue;
        }
        if (strcasecmp($machineId, $requestedMachineId) === 0) {
            $targetMachineIds[] = $machineId;
            $requestedMachineId = $machineId;
            $requestedMachineMatched = true;
            break;
        }
    }
}

if ($requestedMachineId !== '' && !$requestedMachineMatched) {
    $pageWarnings[] = 'Machine filter tidak sesuai master tipe planning terpilih. Sistem menampilkan semua machine pada tipe tersebut.';
    $requestedMachineId = '';
}

if (empty($targetMachineIds) && !empty($masterMachines)) {
    foreach ($masterMachines as $machineRow) {
        $machineId = trim((string)($machineRow['machine_id'] ?? ''));
        if ($machineId === '') {
            continue;
        }
        if (!in_array($machineId, $targetMachineIds, true)) {
            $targetMachineIds[] = $machineId;
        }
    }
}
foreach ($targetMachineIds as $selectedMachineId) {
    $targetMachineLabels[] = $machineNameById[strtoupper($selectedMachineId)] ?? $selectedMachineId;
}

$machineScopeLabel = !empty($targetMachineLabels) ? implode(', ', array_values(array_unique($targetMachineLabels))) : '-';
$machineScopeIdLabel = !empty($targetMachineIds) ? implode(', ', $targetMachineIds) : '-';

if ($pageError === '' && !empty($targetMachineIds)) {
    $machinePlaceholders = implode(', ', array_fill(0, count($targetMachineIds), '?'));
    $planningSql = "
        SELECT t.*
        FROM dbo.[$planningTable] t
        WHERE LTRIM(RTRIM(CAST(t.[$planningMachineCol] AS NVARCHAR(100)))) IN ($machinePlaceholders)
          AND TRY_CONVERT(date, t.[$planningDateCol]) >= ?
          AND TRY_CONVERT(date, t.[$planningDateCol]) <= ?
        ORDER BY
            TRY_CONVERT(date, t.[$planningDateCol]) ASC,
            LTRIM(RTRIM(CAST(t.[$planningMachineCol] AS NVARCHAR(100)))) ASC
            " . ($planningSeqCol !== null ? ", t.[$planningSeqCol] ASC" : "") . "
            " . (isset($planningTableCols['id']) ? ", t.[id] ASC" : "") . "
    ";

    $planningParams = array_merge($targetMachineIds, [$fromDate, $toDate]);
    $stmtPlanning = sqlsrv_query($conn, $planningSql, $planningParams);
    if ($stmtPlanning === false) {
        $errs = sqlsrv_errors(SQLSRV_ERR_ERRORS);
        $msg = 'Gagal membaca data planning.';
        if (is_array($errs) && !empty($errs)) {
            $parts = [];
            foreach ($errs as $err) {
                $parts[] = trim((string)($err['message'] ?? ''));
            }
            $parts = array_values(array_filter($parts, static function ($v): bool {
                return $v !== '';
            }));
            if (!empty($parts)) {
                $msg .= ' ' . implode(' | ', $parts);
            }
        }
        $pageError = $msg;
    } else {
        while ($row = sqlsrv_fetch_array($stmtPlanning, SQLSRV_FETCH_ASSOC)) {
            $rowLower = [];
            foreach ((array)$row as $key => $value) {
                $rowLower[strtolower((string)$key)] = $value;
            }

            $cpNo = trim((string)$rowValue($rowLower, ['cp_no'], ''));
            if ($cpNo === '') {
                continue;
            }
            $breakTimeName = trim((string)$rowValue($rowLower, ['break_time_name'], ''));
            $isBreakTimeFlag = $isTruthyFlag($rowValue($rowLower, ['is_break_time'], null));
            if (
                $isBreakTimeFlag
                || $isBreakTimeCpNo($cpNo)
                || ($breakTimeName !== '' && strcasecmp($breakTimeName, $cpNo) === 0)
                || $isBreakTimeCpNo($breakTimeName)
            ) {
                continue;
            }
            if (strpos($cpNo, ' ') !== false) {
                continue;
            }

            $machineIdRaw = trim((string)$rowValue($rowLower, [$planningMachineCol, 'machine_id'], ''));
            $cpKey = $normalizeCpKey($cpNo);
            $cpKeyMap[$cpKey] = $cpNo;
            $periodDateRaw = $rowValue($rowLower, [$planningDateCol, 'period_date', 'plan_date', 'tgl'], $fromDate);
            $tglPaddryRaw = $rowValue($rowLower, ['tgl', 'plan_date', 'period_date', $planningDateCol], $periodDateRaw);
            $planningRows[] = [
                'machine_id_raw' => $machineIdRaw,
                'machine_name' => $machineNameById[strtoupper($machineIdRaw)] ?? $machineIdRaw,
                'period_date_raw' => $periodDateRaw,
                'tgl_paddry_raw' => $tglPaddryRaw,
                'cp_no' => $cpNo,
                'label' => trim((string)$rowValue($rowLower, ['label'], '')),
                'cust_color' => trim((string)$rowValue($rowLower, ['cust_color', 'customer_color'], '')),
                'kode_lab' => trim((string)$rowValue($rowLower, ['kode_lab', 'color_code', 'colorcode'], '')),
                'material_name' => trim((string)$rowValue($rowLower, ['material_name', 'material'], '')),
                'qty' => $rowValue($rowLower, ['qty', 'prdqty'], null),
            ];
        }
        $planningRowCount = count($planningRows);
        sqlsrv_free_stmt($stmtPlanning);
    }

    $productionByCp = [];
    $lokasiPaddryByProduction = [];
    $failByProduction = [];
    $currentPositionByProduction = [];
    if ($pageError === '' && !empty($cpKeyMap)) {
        try {
            $cpKeys = array_keys($cpKeyMap);
            [$cpHolders, $cpParams] = $buildInPlaceholders($cpKeys, 'cpkey_');
            $cpInSql = implode(', ', $cpHolders);

            $sqlProduction = "
                WITH ranked AS (
                    SELECT
                        productionhdid,
                        TRIM(CAST(prdnmbr AS TEXT)) AS cp_no,
                        prddate,
                        ROW_NUMBER() OVER (
                            PARTITION BY UPPER(TRIM(CAST(prdnmbr AS TEXT)))
                            ORDER BY prddate DESC NULLS LAST, productionhdid DESC
                        ) AS rn
                    FROM pdproductionhd
                    WHERE UPPER(TRIM(CAST(prdnmbr AS TEXT))) IN ($cpInSql)
                )
                SELECT productionhdid, cp_no, prddate
                FROM ranked
                WHERE rn = 1
            ";
            $stmtProduction = $conn3->prepare($sqlProduction);
            $stmtProduction->execute($cpParams);
            while ($row = $stmtProduction->fetch(PDO::FETCH_ASSOC)) {
                $cpKey = $normalizeCpKey($row['cp_no'] ?? '');
                if ($cpKey === '') {
                    continue;
                }
                $productionByCp[$cpKey] = [
                    'productionhdid' => (int)($row['productionhdid'] ?? 0),
                    'prddate' => $row['prddate'] ?? null,
                ];
            }

            $productionIds = [];
            foreach ($productionByCp as $item) {
                $pid = (int)($item['productionhdid'] ?? 0);
                if ($pid > 0) {
                    $productionIds[$pid] = $pid;
                }
            }

            if (!empty($productionIds)) {
                $routingCodes = [];
                $routingCodesNormalized = [];
                $planningTypeForRouting = trim($planType) !== '' ? trim($planType) : 'Paddry';
                $sqlMasterRouting = "
                    SELECT DISTINCT LTRIM(RTRIM(CAST(routing_id AS NVARCHAR(50)))) AS routing_code
                    FROM dbo.ms_routing
                    WHERE LOWER(LTRIM(RTRIM(ISNULL(planning_type, '')))) = LOWER(LTRIM(RTRIM(?)))
                      AND LTRIM(RTRIM(ISNULL(CAST(routing_id AS NVARCHAR(50)), ''))) <> ''
                    ORDER BY LTRIM(RTRIM(CAST(routing_id AS NVARCHAR(50)))) ASC
                ";
                $stmtMasterRouting = sqlsrv_query($conn, $sqlMasterRouting, [$planningTypeForRouting]);
                if ($stmtMasterRouting !== false) {
                    while ($mr = sqlsrv_fetch_array($stmtMasterRouting, SQLSRV_FETCH_ASSOC)) {
                        $routingCode = trim((string)($mr['routing_code'] ?? ''));
                        if ($routingCode === '') {
                            continue;
                        }
                        $routingCodes[$routingCode] = $routingCode;
                        $normalized = ltrim($routingCode, '0');
                        if ($normalized === '') {
                            $normalized = '0';
                        }
                        $routingCodesNormalized[$normalized] = $normalized;
                    }
                    sqlsrv_free_stmt($stmtMasterRouting);
                }

                if (!empty($routingCodes) && !empty($routingCodesNormalized)) {
                    [$pidHoldersLokasi, $pidParamsLokasi] = $buildInPlaceholders(array_values($productionIds), 'pid_lokasi_');
                    [$rtgHolders, $rtgParams] = $buildInPlaceholders(array_values($routingCodes), 'rtg_');
                    [$rtgNormHolders, $rtgNormParams] = $buildInPlaceholders(array_values($routingCodesNormalized), 'rtgn_');

                    $sqlLokasiPaddry = "
                        SELECT
                            a.productionhdid,
                            STRING_AGG(r.rtgname, ', ' ORDER BY r.rtgname) AS lokasi_paddry
                        FROM pdproductionrtg a
                        INNER JOIN pdrtgms r
                            ON a.rtgmsid = r.rtgmsid
                        WHERE a.productionhdid IN (" . implode(', ', $pidHoldersLokasi) . ")
                          AND (
                                TRIM(CAST(r.rtgcode AS TEXT)) IN (" . implode(', ', $rtgHolders) . ")
                                OR COALESCE(
                                    NULLIF(REGEXP_REPLACE(TRIM(CAST(r.rtgcode AS TEXT)), '^0+', ''), ''),
                                    '0'
                                ) IN (" . implode(', ', $rtgNormHolders) . ")
                              )
                        GROUP BY a.productionhdid
                    ";
                    $stmtLokasi = $conn3->prepare($sqlLokasiPaddry);
                    $paramsLokasi = array_merge($pidParamsLokasi, $rtgParams, $rtgNormParams);
                    $stmtLokasi->execute($paramsLokasi);
                    while ($rowLokasi = $stmtLokasi->fetch(PDO::FETCH_ASSOC)) {
                        $pid = (int)($rowLokasi['productionhdid'] ?? 0);
                        if ($pid <= 0) {
                            continue;
                        }
                        $lokasi = trim((string)($rowLokasi['lokasi_paddry'] ?? ''));
                        if ($lokasi === '') {
                            continue;
                        }
                        $lokasiPaddryByProduction[$pid] = $lokasi;
                    }
                } else {
                    $pageWarnings[] = 'Master routing untuk planning type ini belum ditemukan, sehingga Lokasi Paddry dari routing tidak tersedia.';
                }
            }

            if (!empty($productionIds)) {
                [$pidHolders, $pidParams] = $buildInPlaceholders(array_values($productionIds), 'pid_');
                $pidInSql = implode(', ', $pidHolders);

                $sqlFail = "
                    SELECT
                        r.productionhdid,
                        r.productionrtgid,
                        r.rtgmsid,
                        r.failmsid,
                        COALESCE(r.resultdesc, '') AS resultdesc,
                        COALESCE(r.enddate, r.startdate, r.upddate) AS fail_date,
                        COALESCE(m.rtgname, '') AS rtgname,
                        COALESCE(f.failcode, '') AS failcode,
                        COALESCE(f.faildesc, '') AS faildesc
                    FROM pdproductionrtg r
                    LEFT JOIN pdrtgms m
                        ON r.rtgmsid = m.rtgmsid
                    LEFT JOIN pdfailms f
                        ON r.failmsid = f.failmsid
                    WHERE r.productionhdid IN ($pidInSql)
                      AND r.failmsid IS NOT NULL
                      AND r.failmsid <> 0
                    ORDER BY
                        r.productionhdid ASC,
                        COALESCE(r.enddate, r.startdate, r.upddate) DESC NULLS LAST,
                        r.productionrtgid DESC
                ";
                $stmtFail = $conn3->prepare($sqlFail);
                $stmtFail->execute($pidParams);

                while ($row = $stmtFail->fetch(PDO::FETCH_ASSOC)) {
                    $productionId = (int)($row['productionhdid'] ?? 0);
                    if ($productionId <= 0) {
                        continue;
                    }
                    if (!isset($failByProduction[$productionId])) {
                        $failByProduction[$productionId] = [];
                    }

                    $routeNameRaw = trim((string)($row['rtgname'] ?? ''));
                    $routeNameDisplay = preg_replace('/\s+/', ' ', $routeNameRaw) ?? $routeNameRaw;
                    if ($routeNameDisplay === '') {
                        $routeNameDisplay = 'RTG UNKNOWN';
                    }
                    $rtgmsId = (int)($row['rtgmsid'] ?? 0);
                    $routeKey = ($rtgmsId === 692) ? $verpackingGroupKey : $normalizeTextUpper($routeNameDisplay);
                    if ($routeKey === '') {
                        $routeKey = 'RTG UNKNOWN';
                    }
                    if (!isset($failGroupLabels[$routeKey])) {
                        $failGroupLabels[$routeKey] = $routeNameDisplay;
                        $failGroupKeys[] = $routeKey;
                        $failGroupEncounterOrder[$routeKey] = count($failGroupEncounterOrder);
                    }

                    $failCode = trim((string)($row['failcode'] ?? ''));
                    $failDesc = trim((string)($row['faildesc'] ?? ''));
                    $resultDesc = trim((string)($row['resultdesc'] ?? ''));
                    $failName = '-';
                    if ($failCode !== '' && $failDesc !== '') {
                        $failName = $failCode . ' - ' . $failDesc;
                    } elseif ($failDesc !== '') {
                        $failName = $failDesc;
                    } elseif ($failCode !== '') {
                        $failName = $failCode;
                    }

                    $entry = [
                        'tgl_raw' => $row['fail_date'] ?? null,
                        'nama_fail' => $failName,
                        'desc_fail' => $resultDesc !== '' ? $resultDesc : '-',
                        'route_name' => $routeNameDisplay,
                    ];

                    // Ambil satu baris fail terbaru untuk setiap routing per production.
                    if (!isset($failByProduction[$productionId][$routeKey])) {
                        $failByProduction[$productionId][$routeKey] = $entry;
                    }
                }

                // Wajib tampilkan routing VERPACKING (rtgmsid=692) meskipun failmsid null,
                // agar starttime terakhir bisa dipantau.
                $sqlVerpacking = "
                    SELECT
                        r.productionhdid,
                        r.productionrtgid,
                        r.startdate,
                        r.starttime,
                        r.enddate,
                        r.endtime
                    FROM pdproductionrtg r
                    WHERE r.productionhdid IN ($pidInSql)
                      AND r.rtgmsid = 692
                    ORDER BY
                        r.productionhdid ASC,
                        COALESCE(r.starttime, r.startdate, r.endtime, r.enddate, r.upddate) DESC NULLS LAST,
                        r.productionrtgid DESC
                ";
                $stmtVerpacking = $conn3->prepare($sqlVerpacking);
                $stmtVerpacking->execute($pidParams);
                while ($vr = $stmtVerpacking->fetch(PDO::FETCH_ASSOC)) {
                    $productionId = (int)($vr['productionhdid'] ?? 0);
                    if ($productionId <= 0) {
                        continue;
                    }
                    if (!isset($failByProduction[$productionId])) {
                        $failByProduction[$productionId] = [];
                    }
                    // Ambil baris terbaru saja per production.
                    if (isset($failByProduction[$productionId][$verpackingGroupKey])) {
                        continue;
                    }

                    $failByProduction[$productionId][$verpackingGroupKey] = [
                        'tgl_raw' => $vr['starttime'] ?? ($vr['startdate'] ?? ($vr['endtime'] ?? ($vr['enddate'] ?? null))),
                        'nama_fail' => '',
                        'desc_fail' => '',
                        'route_name' => 'VERPACKING',
                    ];
                }

                // Posisi saat ini disamakan dengan cp_planning.php:
                // - khusus paddry: pakai MIN(rtgseq) dengan prdqty=0 (next_process)
                // - selain paddry: fallback next step dari last_process seperti sebelumnya
                $isPaddryPositionLookup = ($planTypeKey === 'paddry');
                $nextProcessCteSql = '';
                $posisiHariIniExpr = "COALESCE(
                            NULLIF(TRIM(CAST(r2.rtgname AS TEXT)), ''),
                            NULLIF(TRIM(CAST(r1.rtgname AS TEXT)), ''),
                            NULLIF(TRIM(CAST(r0.rtgname AS TEXT)), ''),
                            '-'
                        )";
                $joinNextProcessSql = '';
                $joinNextRoutingSql = "LEFT JOIN pdproductionrtg b
                        ON b.productionhdid = a.productionhdid
                        AND b.rtgseq = a.rtgseq + 1";

                if ($isPaddryPositionLookup) {
                    $nextProcessCteSql = ",
                    next_process AS (
                        SELECT
                            productionhdid,
                            MIN(rtgseq) AS next_rtgseq
                        FROM pdproductionrtg
                        WHERE productionhdid IN (SELECT productionhdid FROM latest_any)
                          AND COALESCE(prdqty, 0.0000) = 0
                        GROUP BY productionhdid
                    )";
                    $posisiHariIniExpr = "COALESCE(NULLIF(TRIM(CAST(r2.rtgname AS TEXT)), ''), '-')";
                    $joinNextProcessSql = "LEFT JOIN next_process np
                        ON ps.productionhdid = np.productionhdid";
                    $joinNextRoutingSql = "LEFT JOIN pdproductionrtg b
                        ON b.productionhdid = np.productionhdid
                        AND b.rtgseq = np.next_rtgseq";
                }

                $sqlCurrentPosition = "
                    WITH latest_any AS (
                        SELECT
                            r.productionhdid,
                            r.productionrtgid,
                            r.rtgmsid,
                            r.starttime,
                            r.startdate,
                            r.endtime,
                            r.enddate,
                            ROW_NUMBER() OVER (
                                PARTITION BY r.productionhdid
                                ORDER BY
                                    COALESCE(r.starttime, r.startdate, r.endtime, r.enddate, r.upddate) DESC NULLS LAST,
                                    r.productionrtgid DESC
                            ) AS rn
                        FROM pdproductionrtg r
                        WHERE r.productionhdid IN ($pidInSql)
                    ),
                    last_process AS (
                        SELECT
                            productionhdid,
                            MAX(rtgseq) AS current_rtgseq
                        FROM pdproductionrtg
                        WHERE productionhdid IN (SELECT productionhdid FROM latest_any)
                          AND prdqty > 0
                        GROUP BY productionhdid
                    ),
                    production_scope AS (
                        SELECT DISTINCT productionhdid
                        FROM latest_any
                    ){$nextProcessCteSql}
                    SELECT
                        ps.productionhdid,
                        {$posisiHariIniExpr} AS rtgname,
                        COALESCE(
                            b.starttime, b.startdate, b.endtime, b.enddate,
                            a.starttime, a.startdate, a.endtime, a.enddate,
                            la.starttime, la.startdate, la.endtime, la.enddate
                        ) AS starttime_raw
                    FROM production_scope ps
                    LEFT JOIN last_process lp
                        ON ps.productionhdid = lp.productionhdid
                    LEFT JOIN pdproductionrtg a
                        ON a.productionhdid = lp.productionhdid
                        AND a.rtgseq = lp.current_rtgseq
                    LEFT JOIN pdrtgms r1
                        ON a.rtgmsid = r1.rtgmsid
                    {$joinNextProcessSql}
                    {$joinNextRoutingSql}
                    LEFT JOIN pdrtgms r2
                        ON b.rtgmsid = r2.rtgmsid
                    LEFT JOIN latest_any la
                        ON la.productionhdid = ps.productionhdid
                        AND la.rn = 1
                    LEFT JOIN pdrtgms r0
                        ON la.rtgmsid = r0.rtgmsid
                    ORDER BY ps.productionhdid ASC
                ";
                $stmtCurrentPosition = $conn3->prepare($sqlCurrentPosition);
                $stmtCurrentPosition->execute($pidParams);
                while ($cpRow = $stmtCurrentPosition->fetch(PDO::FETCH_ASSOC)) {
                    $productionId = (int)($cpRow['productionhdid'] ?? 0);
                    if ($productionId <= 0 || isset($currentPositionByProduction[$productionId])) {
                        continue;
                    }

                    $routeNameRaw = trim((string)($cpRow['rtgname'] ?? ''));
                    $routeNameDisplay = preg_replace('/\s+/', ' ', $routeNameRaw) ?? $routeNameRaw;
                    $latestRtgmsId = (int)($cpRow['latest_rtgmsid'] ?? 0);
                    if ($routeNameDisplay === '' && $latestRtgmsId === 692) {
                        $routeNameDisplay = 'VERPACKING';
                    }
                    if ($routeNameDisplay === '') {
                        $routeNameDisplay = '-';
                    }

                    $currentPositionByProduction[$productionId] = [
                        'rtgname' => $routeNameDisplay,
                        'starttime_raw' => $cpRow['starttime_raw'] ?? null,
                    ];
                }
            }
        } catch (Throwable $e) {
            $pageError = 'Gagal membaca data fail routing dari ERP: ' . $e->getMessage();
        }
    }

    foreach ($planningRows as $row) {
        $cpKey = $normalizeCpKey($row['cp_no'] ?? '');
        $productionId = 0;
        if ($cpKey !== '' && isset($productionByCp[$cpKey])) {
            $productionId = (int)($productionByCp[$cpKey]['productionhdid'] ?? 0);
        }

        $failMap = [];
        if ($productionId > 0 && isset($failByProduction[$productionId]) && is_array($failByProduction[$productionId])) {
            $failMap = $failByProduction[$productionId];
        }
        $currentPositionName = '-';
        $currentPositionDateRaw = null;
        if ($productionId > 0 && isset($currentPositionByProduction[$productionId]) && is_array($currentPositionByProduction[$productionId])) {
            $currentPositionName = trim((string)($currentPositionByProduction[$productionId]['rtgname'] ?? '-'));
            if ($currentPositionName === '') {
                $currentPositionName = '-';
            }
            $currentPositionDateRaw = $currentPositionByProduction[$productionId]['starttime_raw'] ?? null;
        }
        if (
            ($currentPositionName === '-' || $currentPositionName === '')
            && isset($failMap[$verpackingGroupKey])
            && is_array($failMap[$verpackingGroupKey])
        ) {
            $currentPositionName = 'VERPACKING';
        }

        $hasRealFail = false;
        foreach ($failMap as $fk => $fv) {
            if ($fk !== $verpackingGroupKey) {
                $hasRealFail = true;
                break;
            }
        }

        $qtyNumber = $toQtyNumber($row['qty'] ?? null);
        $cpTotalCount++;
        $cpTotalQty += $qtyNumber;

        $rowStatus = 'fail';
        if (!$hasRealFail) {
            $cpPassCount++;
            $cpPassQty += $qtyNumber;
            $rowStatus = 'pass';
        } else {
            $cpFailCount++;
            $cpFailQty += $qtyNumber;
        }

        $reportRows[] = [
            'tgl_planning' => $formatDateCell($row['period_date_raw'] ?? $fromDate),
            'machine_name' => trim((string)($row['machine_name'] ?? '')) !== '' ? trim((string)($row['machine_name'] ?? '')) : '-',
            'cp' => $row['cp_no'] !== '' ? $row['cp_no'] : '-',
            'label' => $row['label'] !== '' ? $row['label'] : '-',
            'cust_color' => $row['cust_color'] !== '' ? $row['cust_color'] : '-',
            'kode_lab' => $row['kode_lab'] !== '' ? $row['kode_lab'] : '-',
            'material_name' => $row['material_name'] !== '' ? $row['material_name'] : '-',
            'qty' => $formatQty($row['qty'] ?? null),
            'lokasi_paddry' => ($productionId > 0 && isset($lokasiPaddryByProduction[$productionId]) && trim((string)$lokasiPaddryByProduction[$productionId]) !== '')
                ? trim((string)$lokasiPaddryByProduction[$productionId])
                : '-',
            'tgl_celup_paddry' => $formatDateShort($row['tgl_paddry_raw'] ?? null),
            'status' => $rowStatus,
            'fail_map' => $failMap,
            'current_position_name' => $currentPositionName,
            'current_position_date_raw' => $currentPositionDateRaw,
        ];
    }
} elseif ($pageError === '' && empty($targetMachineIds)) {
    $pageWarnings[] = 'Belum ada machine scope untuk tipe planning ini.';
}

$cpTotalQtyLabel = $formatQty($cpTotalQty);
$cpFailQtyLabel = $formatQty($cpFailQty);
$cpPassQtyLabel = $formatQty($cpPassQty);

if (empty($reportRows) && $pageError === '' && !empty($targetMachineIds)) {
    if (($planningRowCount ?? 0) > 0) {
        $pageWarnings[] = 'Data planning ditemukan, tetapi tidak ada CP yang memiliki fail routing pada rentang tanggal dan machine scope ini.';
    } else {
        $pageWarnings[] = 'Data planning untuk rentang tanggal dan machine scope ini belum tersedia atau belum disimpan permanen.';
    }
}

if (!empty($failGroupKeys)) {
    $normalizeLabel = static function (string $text): string {
        $normalized = strtoupper(trim($text));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        return $normalized;
    };

    // Urutan baku (muncul jika ada data):
    // 1) ACC WARNA R
    // 2) INSPECT R
    // 3) ACC WARNA RF
    // 4) INSPECT FINAL
    $getGroupSortMeta = static function (string $groupKey) use ($failGroupLabels, $failGroupEncounterOrder, $normalizeLabel): array {
        $label = $failGroupLabels[$groupKey] ?? $groupKey;
        $n = $normalizeLabel($label);
        $priority = 99;
        $exactRank = 1;

        if ((bool)preg_match('/ACC\s*WARNA\s*RF\b/i', $n)) {
            $priority = 3;
            $exactRank = ($n === 'ACC WARNA RF') ? 0 : 1;
        } elseif ((bool)preg_match('/ACC\s*WARNA\s*R\b/i', $n)) {
            $priority = 1;
            $exactRank = ($n === 'ACC WARNA R') ? 0 : 1;
        } elseif ((bool)preg_match('/INSPECT\s*R\b/i', $n)) {
            $priority = 2;
            $exactRank = ($n === 'INSPECT R') ? 0 : 1;
        } elseif ((bool)preg_match('/INSPECT\s*FINAL\b/i', $n)) {
            $priority = 4;
            $exactRank = ($n === 'INSPECT FINAL') ? 0 : 1;
        }

        $encounter = isset($failGroupEncounterOrder[$groupKey]) ? (int)$failGroupEncounterOrder[$groupKey] : 999999;
        return [$priority, $exactRank, $encounter];
    };

    $failGroupKeys = array_values(array_unique($failGroupKeys));
    usort($failGroupKeys, static function (string $a, string $b) use ($getGroupSortMeta, $verpackingGroupKey): int {
        if ($a === $verpackingGroupKey && $b !== $verpackingGroupKey) {
            return 1;
        }
        if ($a !== $verpackingGroupKey && $b === $verpackingGroupKey) {
            return -1;
        }
        [$pa, $ea, $oa] = $getGroupSortMeta($a);
        [$pb, $eb, $ob] = $getGroupSortMeta($b);
        if ($pa !== $pb) {
            return $pa <=> $pb;
        }
        if ($ea !== $eb) {
            return $ea <=> $eb;
        }
        return $oa <=> $ob;
    });
}

$hasAnyFailGroup = !empty($failGroupKeys);
$baseColumnCount = 11;
$hasVerpackingColumn = in_array($verpackingGroupKey, $failGroupKeys, true);
$hasNonVerpackingGroup = false;
$dynamicColumnCount = 0;
foreach ($failGroupKeys as $groupKey) {
    if ($groupKey === $verpackingGroupKey) {
        $dynamicColumnCount += 2;
    } else {
        $dynamicColumnCount += 3;
        $hasNonVerpackingGroup = true;
    }
}
$emptyColspan = $baseColumnCount + $dynamicColumnCount;
$passDynamicColumnCount = $hasVerpackingColumn ? 2 : 0;
$emptyColspanPass = $baseColumnCount + $passDynamicColumnCount;
$mainHeaderRowspan = $hasNonVerpackingGroup ? 2 : 1;
$defaultFilter = ($cpFailCount > 0) ? 'fail' : 'pass';
$machineMasterMapByPlanTypeJson = json_encode($machineMasterMapByPlanType, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($machineMasterMapByPlanTypeJson === false) {
    $machineMasterMapByPlanTypeJson = '{}';
}
?>

<style>
    :root {
        --rf-head-row1-height: 34px;
    }

    /* Jaga area report tidak menimpa sidebar saat tabel melebar. */
    .content-wrapper {
        position: relative;
        z-index: 1;
        overflow-x: hidden;
    }

    .main-sidebar {
        z-index: 1060 !important;
        pointer-events: auto !important;
    }

    .main-sidebar * {
        pointer-events: auto !important;
    }

    .rf-card-title {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 700;
    }

    .rf-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 12px;
    }

    .rf-topbar-note {
        margin: 0;
        font-size: 0.79rem;
        color: #4b5563;
    }

    .rf-summary {
        border: 1px solid #dbe3ef;
        background: linear-gradient(140deg, #f7fbff 0%, #ffffff 45%, #f4f8fd 100%);
        border-radius: 12px;
        padding: 14px;
        margin-bottom: 12px;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.05);
    }

    .rf-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 10px;
    }

    .rf-summary-item {
        background: #ffffff;
        border: 1px solid #d9e3ef;
        border-left: 6px solid #3b82f6;
        border-radius: 10px;
        padding: 10px 12px;
        box-shadow: 0 4px 10px rgba(15, 23, 42, 0.08);
    }

    .rf-summary-label {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.035em;
        font-weight: 700;
        color: #64748b;
        margin-bottom: 2px;
    }

    .rf-summary-value {
        font-size: 0.86rem;
        font-weight: 700;
        color: #0f172a;
    }

    .rf-summary-grid .rf-summary-item:nth-child(1) {
        background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
        border-color: #fdba74;
        border-left-color: #f97316;
    }

    .rf-summary-grid .rf-summary-item:nth-child(2) {
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        border-color: #93c5fd;
        border-left-color: #2563eb;
    }

    .rf-summary-grid .rf-summary-item:nth-child(3) {
        background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
        border-color: #86efac;
        border-left-color: #16a34a;
    }

    .rf-summary-grid .rf-summary-item:nth-child(4) {
        background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%);
        border-color: #c4b5fd;
        border-left-color: #7c3aed;
    }

    .rf-range-form {
        margin-top: 10px;
        display: flex;
        align-items: end;
        gap: 10px;
        flex-wrap: wrap;
    }

    .rf-range-actions {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .rf-range-field {
        min-width: 180px;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .rf-range-field label {
        margin: 0;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.035em;
        font-weight: 700;
        color: #64748b;
    }

    .rf-range-field input,
    .rf-range-field select {
        height: 33px;
        border: 1px solid #cfd8e5;
        border-radius: 6px;
        padding: 4px 8px;
        font-size: 0.82rem;
        background: #fff;
    }

    .rf-range-field select[multiple] {
        height: auto;
        min-height: 96px;
    }

    .rf-kpi-grid {
        margin-top: 8px;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 8px;
    }

    .rf-kpi-card {
        border: 1px solid #d5e0ec;
        border-radius: 10px;
        padding: 8px 10px;
        text-align: left;
        background: #ffffff;
        cursor: pointer;
        transition: all 0.16s ease;
        box-shadow: 0 1px 5px rgba(15, 23, 42, 0.06);
        position: relative;
        overflow: hidden;
        min-height: 62px;
    }

    .rf-kpi-card::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        opacity: 0.9;
    }

    .rf-kpi-card:hover {
        transform: translateY(-1px) scale(1.002);
        box-shadow: 0 7px 14px rgba(15, 23, 42, 0.08);
    }

    .rf-kpi-card.active {
        border-color: #0f172a;
        box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.16), 0 8px 16px rgba(15, 23, 42, 0.11);
    }

    .rf-kpi-fail {
        background: linear-gradient(155deg, #fff6f6 0%, #ffffff 65%, #fff6f7 100%);
    }

    .rf-kpi-fail::before {
        background: linear-gradient(180deg, #ef4444 0%, #f97316 100%);
    }

    .rf-kpi-pass {
        background: linear-gradient(155deg, #f3fff8 0%, #ffffff 65%, #f3fff7 100%);
    }

    .rf-kpi-pass::before {
        background: linear-gradient(180deg, #16a34a 0%, #14b8a6 100%);
    }

    .rf-kpi-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    .rf-kpi-main {
        min-width: 0;
    }

    .rf-kpi-title {
        font-size: 0.68rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #475569;
        font-weight: 700;
        margin-bottom: 3px;
    }

    .rf-kpi-badge {
        min-width: 36px;
        height: 36px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.05rem;
        line-height: 1;
        font-weight: 800;
        color: #0f172a;
        border: 1px solid #cfd8e5;
        background: #ffffff;
        box-shadow: inset 0 -2px 0 rgba(148, 163, 184, 0.18);
    }

    .rf-kpi-badge-wrap {
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        gap: 2px;
        flex-shrink: 0;
    }

    .rf-kpi-badge-label {
        font-size: 0.62rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        font-weight: 700;
    }

    .rf-kpi-note {
        font-size: 0.7rem;
        color: #64748b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .rf-kpi-qty {
        margin-top: 2px;
        font-size: 0.72rem;
        color: #334155;
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .rf-filter-note {
        margin: 8px 2px 0;
        font-size: 0.77rem;
        color: #64748b;
    }

    .rf-pagination-top {
        margin-bottom: 0;
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
        font-size: 0.82rem;
        color: #1f2937;
        padding: 8px 10px;
        border: 1px solid #cdd7e4;
        border-radius: 8px 8px 0 0;
        background: #ffffff;
    }

    .rf-pagination-top select {
        height: 30px;
        min-width: 64px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 2px 8px;
        background: #fff;
        font-size: 0.82rem;
    }

    .rf-pagination-search {
        margin-left: auto;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }

    .rf-pagination-search label {
        margin: 0;
        font-size: 0.8rem;
        color: #475569;
    }

    .rf-pagination-search input {
        height: 30px;
        width: clamp(180px, 30vw, 320px);
        min-width: 180px;
        max-width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 4px 9px;
        background: #fff;
        font-size: 0.8rem;
    }

    .rf-pagination-search button {
        height: 30px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 0 10px;
        background: #ffffff;
        color: #334155;
        font-size: 0.78rem;
        cursor: pointer;
    }

    .rf-pagination-search button:hover {
        background: #f8fafc;
    }

    .rf-pagination-bottom {
        margin-top: 8px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
        font-size: 0.82rem;
        color: #374151;
    }

    .rf-pagination-info {
        white-space: nowrap;
    }

    .rf-pagination-nav {
        display: flex;
        align-items: center;
        gap: 0;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        overflow: hidden;
        background: #fff;
    }

    .rf-page-btn,
    .rf-page-ellipsis {
        height: 30px;
        min-width: 32px;
        padding: 0 10px;
        border: 0;
        border-right: 1px solid #e5e7eb;
        background: #fff;
        font-size: 0.8rem;
        color: #1f2937;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .rf-page-btn:last-child,
    .rf-page-ellipsis:last-child {
        border-right: 0;
    }

    .rf-page-btn {
        cursor: pointer;
    }

    .rf-page-btn:hover:not(:disabled) {
        background: #f3f4f6;
    }

    .rf-page-btn:disabled {
        color: #9ca3af;
        cursor: not-allowed;
        background: #f9fafb;
    }

    .rf-page-btn.active {
        background: #0d6efd;
        color: #fff;
        font-weight: 700;
    }

    .rf-page-ellipsis {
        color: #6b7280;
        background: #f9fafb;
    }

    .rf-table tbody tr.rf-row-pass td {
        background: #f7fff9 !important;
    }

    .rf-info-alert {
        border: 1px solid #cfd9e7;
        background: #f7fbff;
        color: #1f2937;
        border-radius: 7px;
        padding: 8px 10px;
        font-size: 0.79rem;
        margin-bottom: 10px;
    }

    .rf-info-alert.warning {
        border-color: #f6d58f;
        background: #fff7e8;
        color: #7c4a03;
    }

    .rf-info-alert.error {
        border-color: #f2b8b5;
        background: #fff1f1;
        color: #8b1d18;
    }

    .rf-table-wrap {
        border: 1px solid #cdd7e4;
        border-radius: 8px;
        overflow-x: auto;
        overflow-y: auto;
        max-height: 68vh;
        background: #ffffff;
        box-shadow: inset 0 0 0 1px #f5f7fa;
        position: relative;
        z-index: 0;
    }

    .rf-table-scroll-top {
        display: none;
        height: 16px;
        overflow-x: auto;
        overflow-y: hidden;
        border: 1px solid #cdd7e4;
        border-bottom: 0;
        border-radius: 8px 8px 0 0;
        background: #f8fafc;
        margin-bottom: 0;
        position: relative;
        z-index: 0;
    }

    .rf-table-scroll-top-inner {
        height: 1px;
    }

    .rf-table {
        width: 100%;
        min-width: 1450px;
        border-collapse: collapse;
    }

    .rf-table th,
    .rf-table td {
        border: 1px solid #d4dde8;
        padding: 6px 8px;
        font-size: 0.77rem;
        line-height: 1.25;
        vertical-align: middle;
    }

    .rf-table thead th {
        text-align: center;
        font-weight: 700;
    }

    .rf-table thead th[rowspan]:not([rowspan="1"]) {
        vertical-align: top;
    }

    #rfReportTable thead tr:first-child th {
        position: sticky;
        top: 0;
        z-index: 22;
    }

    #rfReportTable thead tr:nth-child(2) th {
        position: sticky;
        top: var(--rf-head-row1-height);
        z-index: 21;
    }

    .rf-th-main {
        background: #eef2f7;
        color: #111827;
    }

    .rf-th-paddry {
        background: #dbeafe;
        color: #1e3a8a;
    }

    .rf-th-acc-r {
        background: #fde68a;
        color: #7c2d12;
    }

    .rf-th-acc-rf {
        background: #facc15;
        color: #4c1d95;
    }

    .rf-th-verpack {
        background: #dbeafe;
        color: #1e3a8a;
    }

    .rf-th-sub {
        background: #f8fafc;
        color: #334155;
    }

    .rf-table tbody tr:nth-child(even) td {
        background: #fbfdff;
    }

    .rf-table tbody tr:hover td {
        background: #f2f8ff;
    }

    .rf-table tbody td.rf-missing-data {
        background: #edf1f5 !important;
        color: #8b97a8;
    }

    .rf-cell-center {
        text-align: center;
    }

    .rf-cell-right {
        text-align: right;
    }

    .rf-cell-empty {
        color: #9ca3af;
    }

    .rf-footer-note {
        margin-top: 9px;
        color: #6b7280;
        font-size: 0.76rem;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Generate Report All CP</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="/gg_app/pages/planning/index.php">Planning</a></li>
                        <li class="breadcrumb-item"><a href="/gg_app/pages/planning/cp_planning/cp_planning.php">CP Planning</a></li>
                        <li class="breadcrumb-item active">Report All CP</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?= $esc($themeColor) ?> text-white">
                    <h3 class="rf-card-title">Laporan All CP Berdasarkan Master Planning</h3>
                </div>
                <div class="card-body">
                    <div class="rf-topbar">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Aksi report all">
                            <a href="/gg_app/pages/planning/cp_planning/cp_planning.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left mr-1"></i>Kembali ke CP Planning
                            </a>
                        </div>
                        <p class="rf-topbar-note">Generated: <?= $esc($generatedAt) ?> | Total CP: <?= $esc((string)$cpTotalCount) ?> | Total Qty: <?= $esc($cpTotalQtyLabel) ?></p>
                    </div>

                    <div class="rf-summary">
                        <div class="rf-summary-grid">
                            <div class="rf-summary-item">
                                <div class="rf-summary-label">Periode</div>
                                <div class="rf-summary-value"><?= $esc($periodLabel) ?></div>
                            </div>
                            <div class="rf-summary-item">
                                <div class="rf-summary-label">Tipe Planning</div>
                                <div class="rf-summary-value"><?= $esc($planTypeLabel) ?></div>
                            </div>
                            <div class="rf-summary-item">
                                <div class="rf-summary-label">Machine Scope</div>
                                <div class="rf-summary-value"><?= $esc($machineScopeLabel) ?></div>
                            </div>
                            <div class="rf-summary-item">
                                <div class="rf-summary-label">Machine ID</div>
                                <div class="rf-summary-value"><?= $esc($machineScopeIdLabel) ?></div>
                            </div>
                        </div>
                        <form method="get" class="rf-range-form">
                            <div class="rf-range-field">
                                <label for="rfPlanType">Tipe Planning</label>
                                <select id="rfPlanType" name="plan_type">
                                    <?php if (!empty($masterPlanTypes)): ?>
                                        <?php foreach ($masterPlanTypes as $masterPlanType): ?>
                                            <option value="<?= $esc($masterPlanType) ?>"<?= strcasecmp($masterPlanType, $planType) === 0 ? ' selected' : '' ?>>
                                                <?= $esc($masterPlanType) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="<?= $esc($planType) ?>" selected><?= $esc($planType !== '' ? $planType : '-') ?></option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="rf-range-field">
                                <label for="rfMachineId">Machine ID</label>
                                <select id="rfMachineId" name="machine_id">
                                    <?php if (!empty($masterMachines)): ?>
                                        <option value=""<?= $requestedMachineId === '' ? ' selected' : '' ?>>Semua Machine</option>
                                        <?php foreach ($masterMachines as $masterMachine): ?>
                                            <?php
                                            $machineIdOption = trim((string)($masterMachine['machine_id'] ?? ''));
                                            if ($machineIdOption === '') {
                                                continue;
                                            }
                                            $machineNameOption = trim((string)($masterMachine['machine_name'] ?? ''));
                                            $machineLabelOption = $machineNameOption !== '' ? $machineNameOption : $machineIdOption;
                                            $isMachineSelected = ($requestedMachineId !== '' && strcasecmp($requestedMachineId, $machineIdOption) === 0);
                                            ?>
                                            <option value="<?= $esc($machineIdOption) ?>"<?= $isMachineSelected ? ' selected' : '' ?>>
                                                <?= $esc($machineLabelOption) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>Belum ada machine untuk tipe ini</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="rf-range-field">
                                <label for="rfFromDate">Dari Tanggal</label>
                                <input id="rfFromDate" type="date" name="from_date" value="<?= $esc($fromDate) ?>">
                            </div>
                            <div class="rf-range-field">
                                <label for="rfToDate">Sampai Tanggal</label>
                                <input id="rfToDate" type="date" name="to_date" value="<?= $esc($toDate) ?>">
                            </div>
                            <input type="hidden" name="layout" value="<?= $esc($layout) ?>">
                            <div class="rf-range-actions" role="group" aria-label="Aksi filter report all">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="fas fa-search mr-1"></i>Filter
                                </button>
                                <button type="submit" formaction="/gg_app/pages/planning/cp_planning/cp_planning_report_all_export_excel.php" class="btn btn-sm btn-success">
                                    <i class="fas fa-file-excel mr-1"></i>Export Excel
                                </button>
                            </div>
                        </form>
                        <div class="rf-kpi-grid" id="rfFilterCards" data-default-filter="<?= $esc($defaultFilter) ?>">
                            <button type="button" class="rf-kpi-card rf-kpi-fail<?= $defaultFilter === 'fail' ? ' active' : '' ?>" data-filter="fail">
                                <div class="rf-kpi-row">
                                    <div class="rf-kpi-main">
                                        <div class="rf-kpi-title">CP Fail</div>
                                        <div class="rf-kpi-note">Klik untuk tampilkan data fail</div>
                                        <div class="rf-kpi-qty">Total Qty: <?= $esc($cpFailQtyLabel) ?></div>
                                    </div>
                                    <div class="rf-kpi-badge-wrap">
                                        <div class="rf-kpi-badge"><?= $esc((string)$cpFailCount) ?></div>
                                        <div class="rf-kpi-badge-label">CP</div>
                                    </div>
                                </div>
                            </button>
                            <button type="button" class="rf-kpi-card rf-kpi-pass<?= $defaultFilter === 'pass' ? ' active' : '' ?>" data-filter="pass">
                                <div class="rf-kpi-row">
                                    <div class="rf-kpi-main">
                                        <div class="rf-kpi-title">CP Pass</div>
                                        <div class="rf-kpi-note">Klik untuk tampilkan data pass</div>
                                        <div class="rf-kpi-qty">Total Qty: <?= $esc($cpPassQtyLabel) ?></div>
                                    </div>
                                    <div class="rf-kpi-badge-wrap">
                                        <div class="rf-kpi-badge"><?= $esc((string)$cpPassCount) ?></div>
                                        <div class="rf-kpi-badge-label">CP</div>
                                    </div>
                                </div>
                            </button>
                        </div>
                        <div class="rf-filter-note">Filter aktif: <strong id="rfCurrentFilterLabel"><?= $defaultFilter === 'fail' ? 'CP Fail' : 'CP Pass' ?></strong></div>
                    </div>

                    <?php if ($pageError !== ''): ?>
                        <div class="rf-info-alert error"><?= $esc($pageError) ?></div>
                    <?php endif; ?>

                    <?php foreach ($pageWarnings as $warn): ?>
                        <div class="rf-info-alert warning"><?= $esc($warn) ?></div>
                    <?php endforeach; ?>

                    <div class="rf-pagination-top" id="rfPaginationTop">
                        <span>Tampilkan</span>
                        <select id="rfPageSize">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <span>data per halaman</span>
                        <div class="rf-pagination-search">
                            <label for="rfSearchInput">Cari</label>
                            <input type="text" id="rfSearchInput" placeholder="CP / Label / Cust Color / Routing" autocomplete="off">
                            <button type="button" id="rfSearchClear">Reset</button>
                        </div>
                    </div>

                    <div class="rf-table-scroll-top" id="rfTableScrollTop">
                        <div class="rf-table-scroll-top-inner" id="rfTableScrollTopInner"></div>
                    </div>
                    <div class="rf-table-wrap" id="rfTableWrap">
                        <table class="rf-table" id="rfReportTable">
                            <thead>
                                <tr>
                                    <th rowspan="<?= $mainHeaderRowspan ?>" class="rf-th-main">No</th>
                                    <th rowspan="<?= $mainHeaderRowspan ?>" class="rf-th-main">Tgl Planning</th>
                                    <th rowspan="<?= $mainHeaderRowspan ?>" class="rf-th-main">Machine</th>
                                    <th rowspan="<?= $mainHeaderRowspan ?>" class="rf-th-main">CP</th>
                                    <th rowspan="<?= $mainHeaderRowspan ?>" class="rf-th-main">Label</th>
                                    <th rowspan="<?= $mainHeaderRowspan ?>" class="rf-th-main">Cust Color</th>
                                    <th rowspan="<?= $mainHeaderRowspan ?>" class="rf-th-main">Kode Lab</th>
                                    <th rowspan="<?= $mainHeaderRowspan ?>" class="rf-th-main">Material Name</th>
                                    <th rowspan="<?= $mainHeaderRowspan ?>" class="rf-th-main">Qty</th>
                                    <th rowspan="<?= $mainHeaderRowspan ?>" class="rf-th-main">Lokasi Paddry</th>
                                    <th rowspan="<?= $mainHeaderRowspan ?>" class="rf-th-paddry">Tgl Celup Paddry</th>
                                    <?php foreach ($failGroupKeys as $gIdx => $groupKey): ?>
                                        <?php
                                        $groupLabel = $failGroupLabels[$groupKey] ?? $groupKey;
                                        $isVerpackingGroup = ($groupKey === $verpackingGroupKey);
                                        $groupVisibilityClass = $isVerpackingGroup ? 'rf-verpack-col' : 'rf-fail-col';
                                        $groupClass = $isVerpackingGroup ? 'rf-th-verpack' : ((($gIdx % 2) === 0) ? 'rf-th-acc-r' : 'rf-th-acc-rf');
                                        ?>
                                        <?php if ($isVerpackingGroup): ?>
                                            <?php if ($hasNonVerpackingGroup): ?>
                                                <th rowspan="2" class="<?= $groupClass ?> <?= $groupVisibilityClass ?>">Tgl Verpacking</th>
                                                <th rowspan="2" class="<?= $groupClass ?> <?= $groupVisibilityClass ?>">Posisi Saat Ini</th>
                                            <?php else: ?>
                                                <th class="<?= $groupClass ?> <?= $groupVisibilityClass ?>">Tgl Verpacking</th>
                                                <th class="<?= $groupClass ?> <?= $groupVisibilityClass ?>">Posisi Saat Ini</th>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <th colspan="3" class="<?= $groupClass ?> <?= $groupVisibilityClass ?>"><?= $esc($groupLabel) ?></th>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tr>
                                <?php if ($hasNonVerpackingGroup): ?>
                                    <tr id="rfFailSubHeaderRow">
                                        <?php foreach ($failGroupKeys as $groupKey): ?>
                                            <?php if ($groupKey === $verpackingGroupKey): ?>
                                                <?php continue; ?>
                                            <?php endif; ?>
                                            <th class="rf-th-sub rf-fail-col">Tgl</th>
                                            <th class="rf-th-sub rf-fail-col">Nama Fail</th>
                                            <th class="rf-th-sub rf-fail-col">Desc Fail</th>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endif; ?>
                            </thead>
                            <tbody id="rfReportTbody">
                                <?php if (!empty($reportRows)): ?>
                                    <?php foreach ($reportRows as $idx => $row): ?>
                                        <?php $rowStatus = strtolower(trim((string)($row['status'] ?? 'fail'))); ?>
                                        <tr data-status="<?= $esc($rowStatus) ?>" class="<?= $rowStatus === 'pass' ? 'rf-row-pass' : '' ?>">
                                            <td class="rf-cell-center rf-no"><?= $idx + 1 ?></td>
                                            <td class="rf-cell-center"><?= $esc($row['tgl_planning']) ?></td>
                                            <td><?= $esc($row['machine_name']) ?></td>
                                            <td><?= $esc($row['cp']) ?></td>
                                            <td><?= $esc($row['label']) ?></td>
                                            <td><?= $esc($row['cust_color']) ?></td>
                                            <td><?= $esc($row['kode_lab']) ?></td>
                                            <td><?= $esc($row['material_name']) ?></td>
                                            <td class="rf-cell-right"><?= $esc($row['qty']) ?></td>
                                            <td><?= $esc($row['lokasi_paddry']) ?></td>
                                            <td class="rf-cell-center"><?= $esc($row['tgl_celup_paddry']) ?></td>
                                            <?php foreach ($failGroupKeys as $groupKey): ?>
                                                <?php
                                                $failEntry = null;
                                                $cellVisibilityClass = ($groupKey === $verpackingGroupKey) ? 'rf-verpack-col' : 'rf-fail-col';
                                                if (isset($row['fail_map']) && is_array($row['fail_map']) && isset($row['fail_map'][$groupKey]) && is_array($row['fail_map'][$groupKey])) {
                                                    $failEntry = $row['fail_map'][$groupKey];
                                                }
                                                ?>
                                                <?php if ($groupKey === $verpackingGroupKey): ?>
                                                    <?php
                                                    $verpackDate = '';
                                                    if ($failEntry) {
                                                        $verpackDate = $formatDateShort($failEntry['tgl_raw'] ?? null);
                                                        if ($verpackDate === '-') {
                                                            $verpackDate = '';
                                                        }
                                                    }
                                                    $currentPositionName = trim((string)($row['current_position_name'] ?? '-'));
                                                    if ($currentPositionName === '') {
                                                        $currentPositionName = '-';
                                                    }
                                                    ?>
                                                    <td class="rf-cell-center <?= $cellVisibilityClass ?>"><?= $esc($verpackDate) ?></td>
                                                    <td class="<?= $cellVisibilityClass ?>"><?= $esc($currentPositionName) ?></td>
                                                <?php else: ?>
                                                    <td class="rf-cell-center <?= $cellVisibilityClass ?>"><?= $esc($failEntry ? $formatDateShort($failEntry['tgl_raw'] ?? null) : '-') ?></td>
                                                    <td class="<?= $cellVisibilityClass ?>"><?= $esc($failEntry['nama_fail'] ?? '-') ?></td>
                                                    <td class="<?= $cellVisibilityClass ?>"><?= $esc($failEntry['desc_fail'] ?? '-') ?></td>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr id="rfFilterEmptyRow" class="d-none">
                                        <td id="rfFilterEmptyCell" colspan="<?= $emptyColspan ?>" data-colspan-base="<?= $emptyColspanPass ?>" data-colspan-full="<?= $emptyColspan ?>" class="rf-cell-center rf-cell-empty">Tidak ada data untuk filter ini.</td>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="<?= $emptyColspan ?>" class="rf-cell-center rf-cell-empty">Data tidak ditemukan.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="rf-pagination-bottom" id="rfPaginationBottom">
                        <div class="rf-pagination-info" id="rfPaginationInfo">Menampilkan 0 - 0 dari 0 data</div>
                        <div class="rf-pagination-nav" id="rfPaginationNav"></div>
                    </div>

                </div>
            </div>
        </div>
    </section>
</div>

<script>
window.rfMachineMasterByPlanType = <?= $machineMasterMapByPlanTypeJson ?>;
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var planTypeSelect = document.getElementById('rfPlanType');
    var machineSelect = document.getElementById('rfMachineId');
    if (!planTypeSelect || !machineSelect) {
        return;
    }

    var machineMap = window.rfMachineMasterByPlanType || {};
    var normalizeKey = function (value) {
        return String(value || '').trim().toLowerCase();
    };

    var renderMachineOptionsByPlanType = function (planTypeValue, selectedMachineId) {
        var planTypeKey = normalizeKey(planTypeValue);
        var machines = machineMap[planTypeKey];
        machineSelect.innerHTML = '';

        if (!Array.isArray(machines) || machines.length === 0) {
            var emptyOption = document.createElement('option');
            emptyOption.value = '';
            emptyOption.textContent = 'Belum ada machine untuk tipe ini';
            emptyOption.disabled = true;
            machineSelect.appendChild(emptyOption);
            return;
        }

        var allOption = document.createElement('option');
        allOption.value = '';
        allOption.textContent = 'Semua Machine';
        allOption.selected = (String(selectedMachineId || '').trim() === '');
        machineSelect.appendChild(allOption);

        machines.forEach(function (machineRow) {
            var machineId = String((machineRow && machineRow.machine_id) || '').trim();
            if (machineId === '') {
                return;
            }
            var machineName = String((machineRow && machineRow.machine_name) || '').trim();
            var option = document.createElement('option');
            option.value = machineId;
            option.textContent = machineName !== '' ? machineName : machineId;
            option.selected = (String(selectedMachineId || '').trim().toLowerCase() === machineId.toLowerCase());
            machineSelect.appendChild(option);
        });

        if (!machineSelect.value) {
            machineSelect.value = '';
        }
    };

    planTypeSelect.addEventListener('change', function () {
        renderMachineOptionsByPlanType(planTypeSelect.value || '', '');
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var filterWrap = document.getElementById('rfFilterCards');
    var tbody = document.getElementById('rfReportTbody');
    var paginationTop = document.getElementById('rfPaginationTop');
    var paginationBottom = document.getElementById('rfPaginationBottom');
    var pageSizeSelect = document.getElementById('rfPageSize');
    var searchInput = document.getElementById('rfSearchInput');
    var searchClearButton = document.getElementById('rfSearchClear');
    var paginationInfo = document.getElementById('rfPaginationInfo');
    var paginationNav = document.getElementById('rfPaginationNav');
    var tableWrap = document.getElementById('rfTableWrap');
    var tableTopScroll = document.getElementById('rfTableScrollTop');
    var tableTopScrollInner = document.getElementById('rfTableScrollTopInner');
    var reportTable = document.getElementById('rfReportTable');
    var isTableScrollSyncing = false;
    if (!filterWrap || !tbody) {
        return;
    }

    var cards = Array.prototype.slice.call(filterWrap.querySelectorAll('.rf-kpi-card[data-filter]'));
    var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr[data-status]'));
    var failCols = Array.prototype.slice.call(document.querySelectorAll('#rfReportTable .rf-fail-col'));
    var failSubHeaderRow = document.getElementById('rfFailSubHeaderRow');
    var emptyRow = document.getElementById('rfFilterEmptyRow');
    var emptyCell = document.getElementById('rfFilterEmptyCell');
    var filterLabel = document.getElementById('rfCurrentFilterLabel');
    var numberFormatter = new Intl.NumberFormat('en-US');

    if (cards.length === 0 || rows.length === 0 || !pageSizeSelect || !paginationInfo || !paginationNav) {
        if (paginationTop) {
            paginationTop.style.display = 'none';
        }
        if (paginationBottom) {
            paginationBottom.style.display = 'none';
        }
        return;
    }

    var defaultFilter = (filterWrap.getAttribute('data-default-filter') || 'fail').toLowerCase();
    if (defaultFilter !== 'fail' && defaultFilter !== 'pass') {
        defaultFilter = 'fail';
    }
    var normalizeSearchText = function (value) {
        return String(value || '').toLowerCase().replace(/\s+/g, ' ').trim();
    };
    var state = {
        activeFilter: defaultFilter,
        currentPage: 1,
        pageSize: Math.max(1, Number(pageSizeSelect.value || 10)),
        searchKeyword: ''
    };

    rows.forEach(function (row) {
        row.setAttribute('data-search-index', normalizeSearchText(row.textContent || ''));
    });

    var updateMissingStyles = function (row) {
        var cells = row.querySelectorAll('td');
        cells.forEach(function (cell) {
            var text = (cell.textContent || '').replace(/\s+/g, ' ').trim();
            if (text === '-') {
                cell.textContent = '';
                text = '';
            }
            var isMissing = (text === '' || text === '-');
            cell.classList.toggle('rf-missing-data', isMissing);
        });
    };

    var syncEmptyColspan = function (showFailColumns) {
        if (!emptyCell) {
            return;
        }
        var baseSpan = Number(emptyCell.getAttribute('data-colspan-base') || 10);
        var fullSpan = Number(emptyCell.getAttribute('data-colspan-full') || baseSpan);
        emptyCell.setAttribute('colspan', String(showFailColumns ? fullSpan : baseSpan));
    };

    var buildPageList = function (totalPages, currentPage) {
        if (totalPages <= 0) {
            return [];
        }
        if (totalPages <= 7) {
            var allPages = [];
            for (var p = 1; p <= totalPages; p++) {
                allPages.push(p);
            }
            return allPages;
        }

        var pages = [1];
        var start = Math.max(2, currentPage - 1);
        var end = Math.min(totalPages - 1, currentPage + 1);

        if (start > 2) {
            pages.push('...');
        }
        for (var n = start; n <= end; n++) {
            pages.push(n);
        }
        if (end < totalPages - 1) {
            pages.push('...');
        }
        pages.push(totalPages);
        return pages;
    };

    var renderPagination = function (totalRows) {
        var totalPages = totalRows > 0 ? Math.ceil(totalRows / state.pageSize) : 0;
        if (totalPages > 0 && state.currentPage > totalPages) {
            state.currentPage = totalPages;
        }
        if (totalPages === 0) {
            state.currentPage = 1;
        }

        if (!paginationNav) {
            return totalPages;
        }

        if (totalPages <= 1) {
            paginationNav.innerHTML = '';
            return totalPages;
        }

        var pages = buildPageList(totalPages, state.currentPage);
        var html = '';
        html += '<button type="button" class="rf-page-btn" data-page="' + (state.currentPage - 1) + '"' + (state.currentPage <= 1 ? ' disabled' : '') + '>Sebelumnya</button>';
        pages.forEach(function (item) {
            if (item === '...') {
                html += '<span class="rf-page-ellipsis">...</span>';
                return;
            }
            var isActive = Number(item) === state.currentPage;
            html += '<button type="button" class="rf-page-btn' + (isActive ? ' active' : '') + '" data-page="' + item + '">' + item + '</button>';
        });
        html += '<button type="button" class="rf-page-btn" data-page="' + (state.currentPage + 1) + '"' + (state.currentPage >= totalPages ? ' disabled' : '') + '>Selanjutnya</button>';
        paginationNav.innerHTML = html;

        return totalPages;
    };

    var syncReportStickyOffsets = function () {
        var rootStyle = document.documentElement && document.documentElement.style;
        if (!rootStyle) {
            return;
        }

        var firstHeadRow = reportTable ? reportTable.querySelector('thead tr:first-child') : null;
        var firstRowHeight = 0;
        if (firstHeadRow) {
            var firstHeadCells = firstHeadRow.querySelectorAll('th');
            firstHeadCells.forEach(function (th) {
                var rowspan = Number.parseInt(th.getAttribute('rowspan') || '1', 10);
                if (Number.isFinite(rowspan) && rowspan > 1) {
                    return;
                }
                var h = Math.ceil(th.offsetHeight || 0);
                if (Number.isFinite(h) && h > firstRowHeight) {
                    firstRowHeight = h;
                }
            });
            if (firstRowHeight < 1) {
                firstRowHeight = Math.ceil(firstHeadRow.offsetHeight || 0);
            }
        }
        if (!Number.isFinite(firstRowHeight) || firstRowHeight < 20) {
            firstRowHeight = 34;
        }
        rootStyle.setProperty('--rf-head-row1-height', String(firstRowHeight) + 'px');
    };

    var queueReportStickyOffsetsSync = function () {
        if (typeof window.requestAnimationFrame === 'function') {
            window.requestAnimationFrame(syncReportStickyOffsets);
            return;
        }
        setTimeout(syncReportStickyOffsets, 0);
    };

    var syncTableHorizontalScrollbar = function () {
        if (!tableWrap || !tableTopScroll || !tableTopScrollInner || !reportTable) {
            return;
        }

        var tableWidth = Math.max(
            reportTable.offsetWidth || 0,
            reportTable.scrollWidth || 0,
            tableWrap.scrollWidth || 0
        );
        tableTopScrollInner.style.width = String(tableWidth) + 'px';

        var visibleWidth = tableWrap.clientWidth || 0;
        var hasHorizontalOverflow = tableWidth > (visibleWidth + 1);
        tableTopScroll.style.display = hasHorizontalOverflow ? 'block' : 'none';
        syncReportStickyOffsets();

        if (!hasHorizontalOverflow) {
            tableTopScroll.scrollLeft = 0;
            return;
        }

        if (Math.abs((tableTopScroll.scrollLeft || 0) - (tableWrap.scrollLeft || 0)) > 1) {
            tableTopScroll.scrollLeft = tableWrap.scrollLeft;
        }
    };

    var queueTableHorizontalScrollbarSync = function () {
        if (typeof window.requestAnimationFrame === 'function') {
            window.requestAnimationFrame(syncTableHorizontalScrollbar);
            return;
        }
        setTimeout(syncTableHorizontalScrollbar, 0);
    };

    var applyState = function (resetPage) {
        if (resetPage) {
            state.currentPage = 1;
        }

        var activeFilter = (state.activeFilter || 'fail').toLowerCase();
        var searchKeyword = normalizeSearchText(state.searchKeyword || '');
        if (activeFilter !== 'fail' && activeFilter !== 'pass') {
            activeFilter = 'fail';
            state.activeFilter = 'fail';
        }
        var showFailColumns = activeFilter !== 'pass';
        var filteredRows = [];

        cards.forEach(function (card) {
            var cardFilter = (card.getAttribute('data-filter') || '').toLowerCase();
            card.classList.toggle('active', cardFilter === activeFilter);
        });

        rows.forEach(function (row) {
            var rowStatus = (row.getAttribute('data-status') || '').toLowerCase();
            row.style.display = 'none';
            if (rowStatus === activeFilter) {
                if (searchKeyword !== '') {
                    var searchIndex = row.getAttribute('data-search-index') || '';
                    if (searchIndex.indexOf(searchKeyword) === -1) {
                        return;
                    }
                }
                filteredRows.push(row);
            }
        });

        failCols.forEach(function (cell) {
            cell.style.display = showFailColumns ? '' : 'none';
        });

        if (failSubHeaderRow) {
            failSubHeaderRow.style.display = '';
        }
        syncEmptyColspan(showFailColumns);

        if (filterLabel) {
            filterLabel.textContent = activeFilter === 'pass' ? 'CP Pass' : 'CP Fail';
        }

        var totalRows = filteredRows.length;
        var totalPages = renderPagination(totalRows);
        var startIndex = 0;
        var endIndex = 0;
        if (totalRows > 0 && totalPages > 0) {
            startIndex = (state.currentPage - 1) * state.pageSize;
            endIndex = Math.min(startIndex + state.pageSize, totalRows);
        }

        filteredRows.forEach(function (row, index) {
            if (index < startIndex || index >= endIndex) {
                return;
            }
            row.style.display = '';
            var noCell = row.querySelector('.rf-no');
            if (noCell) {
                noCell.textContent = String(index + 1);
            }
            updateMissingStyles(row);
        });

        if (emptyRow) {
            emptyRow.style.display = totalRows === 0 ? '' : 'none';
        }
        if (emptyCell) {
            emptyCell.textContent = (searchKeyword !== '')
                ? 'Tidak ada data yang cocok dengan pencarian.'
                : 'Tidak ada data untuk filter ini.';
        }

        var infoStart = totalRows === 0 ? 0 : (startIndex + 1);
        var infoEnd = totalRows === 0 ? 0 : endIndex;
        paginationInfo.textContent = 'Menampilkan ' + numberFormatter.format(infoStart) + ' - ' + numberFormatter.format(infoEnd) + ' dari ' + numberFormatter.format(totalRows) + ' data';

        if (paginationTop) {
            paginationTop.style.display = 'flex';
        }
        if (paginationBottom) {
            paginationBottom.style.display = totalRows > 0 ? 'flex' : 'none';
        }

        queueTableHorizontalScrollbarSync();
    };

    cards.forEach(function (card) {
        card.addEventListener('click', function () {
            state.activeFilter = card.getAttribute('data-filter') || 'fail';
            applyState(true);
        });
    });

    pageSizeSelect.addEventListener('change', function () {
        var nextSize = Math.max(1, Number(pageSizeSelect.value || 10));
        state.pageSize = nextSize;
        applyState(true);
    });

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            state.searchKeyword = searchInput.value || '';
            applyState(true);
        });

        searchInput.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }
            searchInput.value = '';
            state.searchKeyword = '';
            applyState(true);
        });
    }

    if (searchClearButton && searchInput) {
        searchClearButton.addEventListener('click', function () {
            searchInput.value = '';
            state.searchKeyword = '';
            applyState(true);
            searchInput.focus();
        });
    }

    paginationNav.addEventListener('click', function (event) {
        var target = event.target;
        if (!target || !target.classList.contains('rf-page-btn') || target.disabled) {
            return;
        }
        var page = Number(target.getAttribute('data-page') || 1);
        if (!Number.isFinite(page) || page < 1) {
            return;
        }
        state.currentPage = page;
        applyState(false);
    });

    if (tableTopScroll && tableWrap) {
        tableTopScroll.addEventListener('scroll', function () {
            if (isTableScrollSyncing) {
                return;
            }
            isTableScrollSyncing = true;
            tableWrap.scrollLeft = tableTopScroll.scrollLeft;
            isTableScrollSyncing = false;
        });

        tableWrap.addEventListener('scroll', function () {
            if (isTableScrollSyncing) {
                return;
            }
            isTableScrollSyncing = true;
            tableTopScroll.scrollLeft = tableWrap.scrollLeft;
            isTableScrollSyncing = false;
        });
    }

    window.addEventListener('resize', function () {
        queueReportStickyOffsetsSync();
        queueTableHorizontalScrollbarSync();
    });

    queueReportStickyOffsetsSync();
    applyState(false);
});
</script>

<?php include '../../../includes/footer.php'; ?>
