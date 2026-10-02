<?php
if (function_exists('ini_set')) {
    @ini_set('display_errors', '0');
    @ini_set('display_startup_errors', '0');
}
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);

while (ob_get_level() > 0) {
    @ob_end_clean();
}
ob_start();

session_start();

if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

include '../../../koneksi.php';
include '../../../koneksi3.php';

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
$planType = '';
$isPaddryContext = (strtolower($layout) === 'paddry') || (strtolower($planType) === 'paddry') || (strtolower($planType) === 'planning paddry');

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

$formatQty = static function ($value): string {
    if ($value === null || $value === '') {
        return '-';
    }
    if (is_numeric($value)) {
        return number_format((float)$value, 0, '.', ',');
    }
    $raw = trim((string)$value);
    if ($raw === '' || $raw === '-') {
        return '-';
    }
    $normalized = str_replace(',', '', $raw);
    if (is_numeric($normalized)) {
        return number_format((float)$normalized, 0, '.', ',');
    }
    return $raw;
};

$normalizeCpKey = static function ($value): string {
    return strtoupper(trim((string)$value));
};

$normalizeTextUpper = static function ($value): string {
    return strtoupper(trim((string)$value));
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

$xmlEsc = static function ($value): string {
    $text = (string)$value;

    if (function_exists('mb_convert_encoding')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8,ISO-8859-1,Windows-1252');
    } elseif (function_exists('iconv')) {
        $converted = iconv('UTF-8', 'UTF-8//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }

    $text = preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $text) ?? '';
    return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
};

$sheetNameSafe = static function (string $name): string {
    $name = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', ' ', $name) ?? $name;
    $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);
    if ($name === '') {
        $name = 'Sheet';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($name, 0, 31, 'UTF-8');
    }
    return substr($name, 0, 31);
};

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

$failGroupLabels[$verpackingGroupKey] = 'VERPACKING';
$failGroupKeys[] = $verpackingGroupKey;
$failGroupEncounterOrder[$verpackingGroupKey] = count($failGroupEncounterOrder);

if ($rangeSwapped) {
    $pageWarnings[] = 'Tanggal awal lebih besar dari tanggal akhir. Sistem menukar otomatis rentang tanggal.';
}

$planningRows = [];
$planningRowCount = 0;
$cpKeyMap = [];
$masterPlanTypes = [];
$masterMachines = [];
$targetMachineIds = [];
$targetMachineLabels = [];
$machineNameById = [];
$requestedMachineId = $normalizeMachineIdValue($machineFilterInput);
$planTypeLabel = '-';
$machineScopeLabel = '-';
$machineScopeIdLabel = '-';

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

$isPaddryContext = (strtolower($layout) === 'paddry')
    || (strtolower($planType) === 'paddry')
    || (strtolower($planType) === 'planning paddry');
if (!$isPaddryContext) {
    $pageWarnings[] = 'Report ini berbasis data cpp_paddry. Pastikan tipe planning dan machine sesuai konteks Paddry.';
}

$machineScopeSql = "
    SELECT
        LTRIM(RTRIM(CAST(machine_id AS NVARCHAR(100)))) AS machine_id,
        LTRIM(RTRIM(CAST(machine_name AS NVARCHAR(200)))) AS machine_name
    FROM dbo.ms_machine
    WHERE LOWER(LTRIM(RTRIM(CAST(planning_type AS NVARCHAR(100))))) = LOWER(LTRIM(RTRIM(?)))
    ORDER BY machine_name ASC, machine_id ASC
";
$stmtMachineScope = ($planType !== '') ? sqlsrv_query($conn, $machineScopeSql, [$planType]) : false;
if ($stmtMachineScope !== false) {
    while ($mr = sqlsrv_fetch_array($stmtMachineScope, SQLSRV_FETCH_ASSOC)) {
        $machineIdRaw = trim((string)($mr['machine_id'] ?? ''));
        $machineNameRaw = trim((string)($mr['machine_name'] ?? ''));
        if ($machineIdRaw === '') {
            continue;
        }
        $masterMachines[] = [
            'machine_id' => $machineIdRaw,
            'machine_name' => $machineNameRaw !== '' ? $machineNameRaw : $machineIdRaw,
        ];
    }
    sqlsrv_free_stmt($stmtMachineScope);
} else {
    if ($planType !== '') {
        $pageWarnings[] = 'Master machine untuk tipe planning ini tidak bisa dibaca.';
    }
}

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

if (!empty($targetMachineIds)) {
    $machinePlaceholders = implode(', ', array_fill(0, count($targetMachineIds), '?'));
    $planningSql = "
        SELECT
            LTRIM(RTRIM(CAST(machine_id AS NVARCHAR(100)))) AS machine_id,
            period_date,
            tgl,
            cp_no,
            label,
            cust_color,
            kode_lab,
            material_name,
            qty,
            seq_no
        FROM dbo.cpp_paddry
        WHERE LTRIM(RTRIM(CAST(machine_id AS NVARCHAR(100)))) IN ($machinePlaceholders)
          AND period_date >= ?
          AND period_date <= ?
        ORDER BY period_date ASC, machine_id ASC, seq_no ASC, id ASC
    ";

    $planningParams = array_merge($targetMachineIds, [$fromDate, $toDate]);
    $stmtPlanning = sqlsrv_query($conn, $planningSql, $planningParams);
    if ($stmtPlanning === false) {
        $errs = sqlsrv_errors(SQLSRV_ERR_ERRORS);
        $msg = 'Gagal membaca data planning paddry.';
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
            $cpNo = trim((string)($row['cp_no'] ?? ''));
            if ($cpNo === '' || strpos($cpNo, ' ') !== false) {
                continue;
            }
            if ($isBreakTimeCpNo($cpNo)) {
                continue;
            }

            $machineIdRaw = trim((string)($row['machine_id'] ?? ''));
            $cpKey = $normalizeCpKey($cpNo);
            $cpKeyMap[$cpKey] = $cpNo;
            $planningRows[] = [
                'machine_id_raw' => $machineIdRaw,
                'machine_name' => $machineNameById[strtoupper($machineIdRaw)] ?? $machineIdRaw,
                'period_date_raw' => $row['period_date'] ?? $fromDate,
                'tgl_paddry_raw' => $row['tgl'] ?? ($row['period_date'] ?? $fromDate),
                'cp_no' => $cpNo,
                'label' => trim((string)($row['label'] ?? '')),
                'cust_color' => trim((string)($row['cust_color'] ?? '')),
                'kode_lab' => trim((string)($row['kode_lab'] ?? '')),
                'material_name' => trim((string)($row['material_name'] ?? '')),
                'qty' => $row['qty'] ?? null,
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

                    if (!isset($failByProduction[$productionId][$routeKey])) {
                        $failByProduction[$productionId][$routeKey] = $entry;
                    }
                }

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

                $sqlCurrentPosition = "
                    SELECT
                        r.productionhdid,
                        r.productionrtgid,
                        COALESCE(m.rtgname, '') AS rtgname,
                        r.starttime,
                        r.startdate,
                        r.endtime,
                        r.enddate
                    FROM pdproductionrtg r
                    LEFT JOIN pdrtgms m
                        ON r.rtgmsid = m.rtgmsid
                    WHERE r.productionhdid IN ($pidInSql)
                    ORDER BY
                        r.productionhdid ASC,
                        COALESCE(r.starttime, r.startdate, r.endtime, r.enddate, r.upddate) DESC NULLS LAST,
                        r.productionrtgid DESC
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
                    if ($routeNameDisplay === '') {
                        $routeNameDisplay = '-';
                    }

                    $currentPositionByProduction[$productionId] = [
                        'rtgname' => $routeNameDisplay,
                        'starttime_raw' => $cpRow['starttime'] ?? ($cpRow['startdate'] ?? ($cpRow['endtime'] ?? ($cpRow['enddate'] ?? null))),
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
        if ($productionId > 0 && isset($currentPositionByProduction[$productionId]) && is_array($currentPositionByProduction[$productionId])) {
            $currentPositionName = trim((string)($currentPositionByProduction[$productionId]['rtgname'] ?? '-'));
            if ($currentPositionName === '') {
                $currentPositionName = '-';
            }
        }

        $hasRealFail = false;
        foreach ($failMap as $fk => $fv) {
            if ($fk !== $verpackingGroupKey) {
                $hasRealFail = true;
                break;
            }
        }

        $cpTotalCount++;

        $rowStatus = 'fail';
        if (!$hasRealFail) {
            $cpPassCount++;
            $rowStatus = 'pass';
        } else {
            $cpFailCount++;
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
        ];
    }
}

if (empty($reportRows) && $pageError === '' && !empty($targetMachineIds)) {
    if (($planningRowCount ?? 0) > 0) {
        $pageWarnings[] = 'Data planning ditemukan, tetapi tidak ada CP yang memiliki fail routing pada rentang tanggal dan machine scope ini.';
    } else {
        $pageWarnings[] = 'Data planning paddry untuk rentang tanggal dan machine scope ini belum tersedia atau belum disimpan permanen.';
    }
}

if ($pageError !== '') {
    http_response_code(500);
    echo $pageError;
    exit;
}

if (!empty($failGroupKeys)) {
    $normalizeLabel = static function (string $text): string {
        $normalized = strtoupper(trim($text));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        return $normalized;
    };

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

$baseColumns = [
    'No',
    'Tgl Planning',
    'Machine',
    'CP',
    'Label',
    'Cust Color',
    'Kode Lab',
    'Material Name',
    'Qty',
    'Lokasi Paddry',
    'Tgl Celup Paddry',
];

$hasNonVerpackingGroup = false;
foreach ($failGroupKeys as $groupKey) {
    if ($groupKey !== $verpackingGroupKey) {
        $hasNonVerpackingGroup = true;
        break;
    }
}

$buildBaseLine = static function (array $row, int $seq): array {
    return [
        (string)$seq,
        (string)($row['tgl_planning'] ?? '-'),
        (string)($row['machine_name'] ?? '-'),
        (string)($row['cp'] ?? '-'),
        (string)($row['label'] ?? '-'),
        (string)($row['cust_color'] ?? '-'),
        (string)($row['kode_lab'] ?? '-'),
        (string)($row['material_name'] ?? '-'),
        (string)($row['qty'] ?? '-'),
        (string)($row['lokasi_paddry'] ?? '-'),
        (string)($row['tgl_celup_paddry'] ?? '-'),
    ];
};

$buildFailRows = static function () use ($reportRows, $failGroupKeys, $verpackingGroupKey, $formatDateShort, $buildBaseLine): array {
    $rows = [];
    $seq = 0;
    foreach ($reportRows as $row) {
        $status = strtolower(trim((string)($row['status'] ?? 'fail')));
        if ($status !== 'fail') {
            continue;
        }

        $seq++;
        $line = $buildBaseLine($row, $seq);

        foreach ($failGroupKeys as $groupKey) {
            $failEntry = null;
            if (isset($row['fail_map']) && is_array($row['fail_map']) && isset($row['fail_map'][$groupKey]) && is_array($row['fail_map'][$groupKey])) {
                $failEntry = $row['fail_map'][$groupKey];
            }

            if ($groupKey === $verpackingGroupKey) {
                $verpackDate = '';
                if ($failEntry) {
                    $verpackDate = $formatDateShort($failEntry['tgl_raw'] ?? null);
                    if ($verpackDate === '-') {
                        $verpackDate = '';
                    }
                }
                $position = trim((string)($row['current_position_name'] ?? '-'));
                if ($position === '') {
                    $position = '-';
                }
                $line[] = $verpackDate;
                $line[] = $position;
                continue;
            }

            $line[] = $failEntry ? $formatDateShort($failEntry['tgl_raw'] ?? null) : '-';
            $line[] = (string)($failEntry['nama_fail'] ?? '-');
            $line[] = (string)($failEntry['desc_fail'] ?? '-');
        }

        $rows[] = $line;
    }
    return $rows;
};

$buildPassRows = static function () use ($reportRows, $failGroupKeys, $verpackingGroupKey, $formatDateShort, $buildBaseLine): array {
    $rows = [];
    $seq = 0;
    foreach ($reportRows as $row) {
        $status = strtolower(trim((string)($row['status'] ?? 'fail')));
        if ($status !== 'pass') {
            continue;
        }

        $seq++;
        $line = $buildBaseLine($row, $seq);

        $failEntry = null;
        if (isset($row['fail_map']) && is_array($row['fail_map']) && isset($row['fail_map'][$verpackingGroupKey]) && is_array($row['fail_map'][$verpackingGroupKey])) {
            $failEntry = $row['fail_map'][$verpackingGroupKey];
        }

        $verpackDate = '';
        if ($failEntry) {
            $verpackDate = $formatDateShort($failEntry['tgl_raw'] ?? null);
            if ($verpackDate === '-') {
                $verpackDate = '';
            }
        }
        $position = trim((string)($row['current_position_name'] ?? '-'));
        if ($position === '') {
            $position = '-';
        }
        $line[] = $verpackDate;
        $line[] = $position;

        $rows[] = $line;
    }
    return $rows;
};

$rowsFail = $buildFailRows();
$rowsPass = $buildPassRows();

$failHeaderTop = [];
$mainHeaderMergeDown = $hasNonVerpackingGroup ? 1 : 0;
foreach ($baseColumns as $baseHeader) {
    $failHeaderTop[] = [$baseHeader, 0, $mainHeaderMergeDown];
}
foreach ($failGroupKeys as $groupKey) {
    if ($groupKey === $verpackingGroupKey) {
        $failHeaderTop[] = ['Tgl Verpacking', 0, $mainHeaderMergeDown];
        $failHeaderTop[] = ['Posisi Saat Ini', 0, $mainHeaderMergeDown];
        continue;
    }
    $groupLabel = trim((string)($failGroupLabels[$groupKey] ?? $groupKey));
    if ($groupLabel === '') {
        $groupLabel = $groupKey;
    }
    $failHeaderTop[] = [$groupLabel, 2, 0];
}

$failHeaderRows = [$failHeaderTop];
if ($hasNonVerpackingGroup) {
    $failHeaderSub = [];
    foreach ($failGroupKeys as $groupKey) {
        if ($groupKey === $verpackingGroupKey) {
            continue;
        }
        $failHeaderSub[] = ['Tgl', 0, 0];
        $failHeaderSub[] = ['Nama Fail', 0, 0];
        $failHeaderSub[] = ['Desc Fail', 0, 0];
    }
    if (!empty($failHeaderSub)) {
        $failHeaderRows[] = [
            '_start_index' => count($baseColumns) + 1,
            '_cells' => $failHeaderSub,
        ];
    }
}

$passHeaderTop = [];
foreach ($baseColumns as $baseHeader) {
    $passHeaderTop[] = [$baseHeader, 0, 0];
}
$passHeaderTop[] = ['Tgl Verpacking', 0, 0];
$passHeaderTop[] = ['Posisi Saat Ini', 0, 0];
$passHeaderRows = [$passHeaderTop];

$failTotalColumns = 0;
foreach ($failHeaderTop as $cellMeta) {
    $failTotalColumns += ((int)$cellMeta[1]) + 1;
}
$passTotalColumns = count($passHeaderTop);

$emitSheet = static function (string $sheetTitle, array $rows, array $headerRows, int $totalColumns, array $metaRows) use ($xmlEsc, $sheetNameSafe): void {
    $safeSheet = $sheetNameSafe($sheetTitle);
    $expandedRows = count($metaRows) + count($headerRows) + 1 + (empty($rows) ? 1 : count($rows));
    echo '<Worksheet ss:Name="' . $xmlEsc($safeSheet) . '">';
    echo '<Table ss:ExpandedColumnCount="' . $totalColumns . '" ss:ExpandedRowCount="' . $expandedRows . '" x:FullColumns="1" x:FullRows="1">';

    $mergeAcross = max(0, $totalColumns - 1);

    echo '<Row>';
    echo '<Cell ss:StyleID="Title" ss:MergeAcross="' . $mergeAcross . '"><Data ss:Type="String">' . $xmlEsc($sheetTitle) . '</Data></Cell>';
    echo '</Row>';

    foreach ($metaRows as $metaText) {
        echo '<Row>';
        echo '<Cell ss:StyleID="Meta" ss:MergeAcross="' . $mergeAcross . '"><Data ss:Type="String">' . $xmlEsc($metaText) . '</Data></Cell>';
        echo '</Row>';
    }

    foreach ($headerRows as $headerRowRaw) {
        $headerRow = $headerRowRaw;
        $rowStartIndex = null;
        if (is_array($headerRowRaw) && array_key_exists('_cells', $headerRowRaw)) {
            $headerRow = is_array($headerRowRaw['_cells']) ? $headerRowRaw['_cells'] : [];
            $rowStartIndex = isset($headerRowRaw['_start_index']) ? (int)$headerRowRaw['_start_index'] : null;
        }

        echo '<Row>';
        foreach ($headerRow as $cellIdx => $headerCell) {
            $text = (string)($headerCell[0] ?? '');
            $cellMergeAcross = (int)($headerCell[1] ?? 0);
            $cellMergeDown = (int)($headerCell[2] ?? 0);
            $attrAcross = $cellMergeAcross > 0 ? ' ss:MergeAcross="' . $cellMergeAcross . '"' : '';
            $attrDown = $cellMergeDown > 0 ? ' ss:MergeDown="' . $cellMergeDown . '"' : '';
            $attrIndex = '';
            if ($cellIdx === 0 && $rowStartIndex !== null && $rowStartIndex > 1) {
                $attrIndex = ' ss:Index="' . $rowStartIndex . '"';
            }
            echo '<Cell ss:StyleID="Header"' . $attrIndex . $attrAcross . $attrDown . '><Data ss:Type="String">' . $xmlEsc($text) . '</Data></Cell>';
        }
        echo '</Row>';
    }

    if (empty($rows)) {
        echo '<Row>';
        echo '<Cell ss:StyleID="Empty" ss:MergeAcross="' . $mergeAcross . '"><Data ss:Type="String">Tidak ada data.</Data></Cell>';
        echo '</Row>';
    } else {
        foreach ($rows as $line) {
            echo '<Row>';
            foreach ($line as $cell) {
                echo '<Cell ss:StyleID="Data"><Data ss:Type="String">' . $xmlEsc((string)$cell) . '</Data></Cell>';
            }
            echo '</Row>';
        }
    }

    echo '</Table>';
    echo '</Worksheet>';
};

$generatedAt = date('d/m/Y H:i');
$planTypeLabel = $planType !== '' ? $planType : '-';
$periodMeta = ($fromDate === $toDate) ? $fromDate : ($fromDate . ' s/d ' . $toDate);
$metaRows = [
    'Periode: ' . $periodMeta,
    'Tipe Planning: ' . $planTypeLabel,
    'Machine Scope: ' . $machineScopeLabel,
    'Generated: ' . $generatedAt,
];
foreach ($pageWarnings as $warn) {
    $warnText = trim((string)$warn);
    if ($warnText !== '') {
        $metaRows[] = 'Catatan: ' . $warnText;
    }
}

$periodToken = ($fromDate === $toDate) ? $fromDate : ($fromDate . '_to_' . $toDate);
$periodToken = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $periodToken);
$planToken = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $planType !== '' ? $planType : 'all');
$filename = 'cp_planning_report_all_' . $planToken . '_' . $periodToken . '.xls';

if (ob_get_length()) {
    ob_clean();
}

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:html="http://www.w3.org/TR/REC-html40">';
echo '<Styles>';
echo '<Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="10"/><Borders/><Interior/><NumberFormat/><Protection/></Style>';
echo '<Style ss:ID="Title"><Font ss:Bold="1" ss:Size="12"/><Interior ss:Color="#D9E1F2" ss:Pattern="Solid"/></Style>';
echo '<Style ss:ID="Meta"><Font ss:Size="10"/><Interior ss:Color="#F7F7F7" ss:Pattern="Solid"/></Style>';
echo '<Style ss:ID="Header"><Font ss:Bold="1"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Interior ss:Color="#DEEAF6" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
echo '<Style ss:ID="Data"><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
echo '<Style ss:ID="Empty"><Font ss:Italic="1" ss:Color="#6B7280"/><Interior ss:Color="#F9FAFB" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
echo '</Styles>';

$emitSheet('CP Fail', $rowsFail, $failHeaderRows, $failTotalColumns, $metaRows);
$emitSheet('CP Pass', $rowsPass, $passHeaderRows, $passTotalColumns, $metaRows);

echo '</Workbook>';
exit;
