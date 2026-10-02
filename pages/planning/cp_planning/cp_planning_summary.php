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
$isSummaryExportBootstrap = defined('CP_SUMMARY_EXPORT_BOOTSTRAP') && CP_SUMMARY_EXPORT_BOOTSTRAP === true;
if (!$isSummaryExportBootstrap) {
    include '../../../includes/header.php';
    include '../../../includes/sidebar.php';
}

if (!function_exists('cpsSummarySqlErrorText')) {
    function cpsSummarySqlErrorText(string $fallback = 'Terjadi kesalahan database.'): string
    {
        $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
        if (!is_array($errors) || empty($errors)) {
            return $fallback;
        }

        $parts = [];
        foreach ($errors as $err) {
            $msg = trim((string)($err['message'] ?? ''));
            if ($msg !== '') {
                $parts[] = $msg;
            }
        }

        return empty($parts) ? $fallback : implode(' | ', $parts);
    }
}

if (!function_exists('cpsSummaryTableExists')) {
    function cpsSummaryTableExists($conn, string $tableName): bool
    {
        $stmt = sqlsrv_query(
            $conn,
            "SELECT TOP 1 1 AS ok
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = ?",
            [$tableName]
        );
        if ($stmt === false) {
            return false;
        }
        $exists = (bool)sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        return $exists;
    }
}

if (!function_exists('cpsSummaryTableColumns')) {
    function cpsSummaryTableColumns($conn, string $tableName): array
    {
        $stmt = sqlsrv_query(
            $conn,
            "SELECT LOWER(COLUMN_NAME) AS col_name
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = ?",
            [$tableName]
        );
        if ($stmt === false) {
            return [];
        }

        $cols = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $col = trim((string)($row['col_name'] ?? ''));
            if ($col !== '') {
                $cols[$col] = true;
            }
        }
        sqlsrv_free_stmt($stmt);
        return $cols;
    }
}

if (!function_exists('cpsSummaryResolveColumn')) {
    function cpsSummaryResolveColumn(array $availableCols, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $key = strtolower(trim((string)$candidate));
            if ($key !== '' && isset($availableCols[$key])) {
                return $key;
            }
        }
        return null;
    }
}

if (!function_exists('cpsSummaryToIsoDate')) {
    function cpsSummaryToIsoDate($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $raw = trim((string)$value);
        if ($raw === '') {
            return '';
        }

        $formats = [
            'Y-m-d',
            'Y-m-d H:i:s',
            'Y-m-d H:i:s.u',
            'd/m/Y',
            'd-m-Y',
        ];
        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $raw);
            if ($dt instanceof DateTime) {
                return $dt->format('Y-m-d');
            }
        }

        $ts = strtotime($raw);
        return ($ts !== false) ? date('Y-m-d', $ts) : '';
    }
}

if (!function_exists('cpsSummaryToFloat')) {
    function cpsSummaryToFloat($value): float
    {
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
    }
}

if (!function_exists('cpsSummaryFormatQty')) {
    function cpsSummaryFormatQty($value, int $precision = 3): string
    {
        $number = cpsSummaryToFloat($value);
        $formatted = number_format($number, $precision, '.', ',');
        $formatted = rtrim(rtrim($formatted, '0'), '.');
        return $formatted === '-0' ? '0' : $formatted;
    }
}

if (!function_exists('cpsSummaryFormatPct')) {
    function cpsSummaryFormatPct($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }
        if (!is_numeric($value)) {
            return '-';
        }
        return number_format((float)$value, 1, '.', ',') . '%';
    }
}

if (!function_exists('cpsSummaryFormatDateCell')) {
    function cpsSummaryFormatDateCell($value): string
    {
        $iso = cpsSummaryToIsoDate($value);
        if ($iso === '') {
            return '-';
        }
        $dt = DateTime::createFromFormat('Y-m-d', $iso);
        return $dt ? $dt->format('d/m/Y') : $iso;
    }
}

if (!function_exists('cpsSummaryFormatDateIndo')) {
    function cpsSummaryFormatDateIndo($value): string
    {
        $iso = cpsSummaryToIsoDate($value);
        if ($iso === '') {
            return '-';
        }

        $dt = DateTime::createFromFormat('Y-m-d', $iso);
        if (!$dt) {
            return $iso;
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

        $day = $dayNames[(int)$dt->format('w')] ?? '';
        $month = $monthNames[(int)$dt->format('n')] ?? '';

        return $day . ', ' . (int)$dt->format('j') . ' ' . $month . ' ' . $dt->format('Y');
    }
}

if (!function_exists('cpsSummaryExtractTimeMinutes')) {
    function cpsSummaryExtractTimeMinutes($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            $hour = (int)$value->format('H');
            $minute = (int)$value->format('i');
            return ($hour * 60) + $minute;
        }

        $raw = trim((string)$value);
        if ($raw === '' || $raw === '-') {
            return null;
        }

        if (preg_match('/(\d{1,2}):(\d{2})/', $raw, $m)) {
            $hour = (int)$m[1];
            $minute = (int)$m[2];
            if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
                return null;
            }
            return ($hour * 60) + $minute;
        }

        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }
        return ((int)date('H', $ts) * 60) + (int)date('i', $ts);
    }
}

if (!function_exists('cpsSummaryFetchPaddryActualCelupByCpRouting')) {
    /**
     * Sinkronkan aktual celup paddry dari ERP (PostgreSQL) berdasarkan pasangan CP + Lokasi Paddry.
     *
     * @param PDO|null $conn3
     * @param array<int,array<string,mixed>> $rows
     * @param int $routingPartIndex Index routing dari kolom lokasi_paddry (0=pertama, 1=kedua, dst)
     * @return array<string,array{actual_start:string,actual_end:string,actual_marker_at:string}>
     */
    function cpsSummaryFetchPaddryActualCelupByCpRouting($conn3, array $rows, int $routingPartIndex = 0): array
    {
        if (!($conn3 instanceof PDO) || empty($rows)) {
            return [];
        }
        $routingPartIndex = max(0, (int)$routingPartIndex);

        $normalizeCp = static function ($value): string {
            return strtoupper(trim((string)$value));
        };
        $normalizeRouting = static function ($value): string {
            $raw = trim((string)$value);
            if ($raw === '') {
                return '';
            }
            $raw = preg_replace('/\s+/', ' ', $raw) ?? $raw;
            return strtoupper($raw);
        };
        $extractRoutingParts = static function ($value) use ($normalizeRouting): array {
            $raw = trim((string)$value);
            if ($raw === '') {
                return [];
            }
            $raw = preg_replace('/\s+/', ' ', $raw) ?? $raw;

            $partsOut = [];
            $parts = preg_split('/\s*,\s*/', $raw);
            if (is_array($parts)) {
                foreach ($parts as $part) {
                    $key = $normalizeRouting($part);
                    if ($key !== '') {
                        $partsOut[$key] = $key;
                    }
                }
            }

            return array_values($partsOut);
        };
        $extractTimeHHmm = static function ($value): string {
            if ($value === null || $value === '') {
                return '';
            }
            if ($value instanceof DateTimeInterface) {
                return $value->format('H:i');
            }
            $raw = trim((string)$value);
            if ($raw === '') {
                return '';
            }
            if (preg_match('/(\d{2}):(\d{2})/', $raw, $m)) {
                return $m[1] . ':' . $m[2];
            }
            $ts = strtotime($raw);
            return $ts === false ? '' : date('H:i', $ts);
        };
        $toTimestamp = static function ($value): int {
            if ($value === null || $value === '') {
                return 0;
            }
            if ($value instanceof DateTimeInterface) {
                return (int)$value->format('U');
            }
            $raw = trim((string)$value);
            if ($raw === '') {
                return 0;
            }
            $ts = strtotime($raw);
            return $ts === false ? 0 : (int)$ts;
        };
        $toDateOnlyTs = static function ($value): int {
            if ($value === null || $value === '') {
                return 0;
            }
            if ($value instanceof DateTimeInterface) {
                return strtotime($value->format('Y-m-d')) ?: 0;
            }
            $raw = trim((string)$value);
            if ($raw === '') {
                return 0;
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw, $m)) {
                $ts = strtotime($m[0]);
                return $ts === false ? 0 : (int)$ts;
            }
            $ts = strtotime($raw);
            if ($ts === false) {
                return 0;
            }
            return strtotime(date('Y-m-d', $ts)) ?: 0;
        };
        $toActualDateTimeTs = static function ($dateValue, $timeValue) use ($toDateOnlyTs, $toTimestamp): int {
            $dateTs = $toDateOnlyTs($dateValue);
            if ($timeValue instanceof DateTimeInterface) {
                return (int)$timeValue->format('U');
            }

            $timeRaw = trim((string)($timeValue ?? ''));
            if ($timeRaw !== '') {
                if (preg_match('/^\d{4}-\d{2}-\d{2}/', $timeRaw)) {
                    return $toTimestamp($timeRaw);
                }

                if ($dateTs > 0 && preg_match('/(\d{1,2}):(\d{2})(?::(\d{2}))?/', $timeRaw, $m)) {
                    $hour = str_pad((string)(int)$m[1], 2, '0', STR_PAD_LEFT);
                    $minute = $m[2];
                    $second = isset($m[3]) ? $m[3] : '00';
                    return $toTimestamp(date('Y-m-d', $dateTs) . ' ' . $hour . ':' . $minute . ':' . $second);
                }

                return 0;
            }

            return $toTimestamp($dateValue);
        };
        $formatDateTime = static function (int $ts): string {
            return $ts > 0 ? date('Y-m-d H:i:s', $ts) : '';
        };
        $isCandidateInsidePlanningWindow = static function (array $candidate, int $referenceDateTs): bool {
            if ($referenceDateTs <= 0) {
                return true;
            }

            $markerTs = (int)($candidate['actual_marker_ts'] ?? 0);
            if ($markerTs <= 0) {
                return false;
            }

            $windowStartTs = $referenceDateTs;
            $windowEndTs = $referenceDateTs + 86400 + 330 * 60;
            return $markerTs >= $windowStartTs && $markerTs <= $windowEndTs;
        };
        $buildNamedPlaceholders = static function (array $values, string $prefix): array {
            $holders = [];
            $params = [];
            $i = 0;
            foreach ($values as $value) {
                $key = ':' . $prefix . $i;
                $holders[] = $key;
                $params[$key] = $value;
                $i++;
            }
            return [$holders, $params];
        };
        $tableExists = static function (PDO $db, string $tableName): bool {
            $stmt = $db->prepare("
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = 'public'
                  AND table_name = :table_name
                LIMIT 1
            ");
            $stmt->bindValue(':table_name', strtolower($tableName), PDO::PARAM_STR);
            $stmt->execute();
            return (bool)$stmt->fetchColumn();
        };

        $pairRows = [];
        $cpSet = [];
        $routingSet = [];
        foreach ($rows as $row) {
            $cpKey = $normalizeCp($row['cp_no'] ?? '');
            $routingSource = trim((string)($row['lokasi_paddry'] ?? ''));
            $routingKey = $normalizeRouting($routingSource);
            $routingPartKeys = $extractRoutingParts($routingSource);
            $referenceDateTs = $toDateOnlyTs($row['plan_date'] ?? '');
            $lookupKey = trim((string)($row['lookup_key'] ?? ''));
            if ($cpKey === '' || $routingKey === '' || empty($routingPartKeys)) {
                continue;
            }
            if (!isset($routingPartKeys[$routingPartIndex])) {
                continue;
            }
            $selectedRoutingKey = $normalizeRouting($routingPartKeys[$routingPartIndex]);
            if ($selectedRoutingKey === '') {
                continue;
            }
            if ($lookupKey === '') {
                $lookupKey = $cpKey . '|' . $routingKey;
            }
            $pairRows[] = [
                'cp_key' => $cpKey,
                'routing_key' => $routingKey,
                'pair_key' => $cpKey . '|' . $routingKey,
                'lookup_key' => $lookupKey,
                'selected_routing_key' => $selectedRoutingKey,
                'reference_date_ts' => $referenceDateTs,
            ];
            $cpSet[$cpKey] = $cpKey;
            $routingSet[$selectedRoutingKey] = $selectedRoutingKey;
        }
        if (empty($pairRows) || empty($cpSet) || empty($routingSet)) {
            return [];
        }

        [$routingHolders, $routingParams] = $buildNamedPlaceholders(array_values($routingSet), 'rtg_');
        if (empty($routingHolders)) {
            return [];
        }

        $sqlRouting = "
            SELECT
                CAST(rtgmsid AS INTEGER) AS rtgmsid,
                UPPER(TRIM(REGEXP_REPLACE(CAST(rtgname AS TEXT), '\s+', ' ', 'g'))) AS routing_key
            FROM pdrtgms
            WHERE UPPER(TRIM(REGEXP_REPLACE(CAST(rtgname AS TEXT), '\s+', ' ', 'g')))
                  IN (" . implode(', ', $routingHolders) . ")
        ";
        $stmtRouting = $conn3->prepare($sqlRouting);
        $stmtRouting->execute($routingParams);

        $routingToRtgms = [];
        while ($row = $stmtRouting->fetch(PDO::FETCH_ASSOC)) {
            $routingKey = $normalizeRouting($row['routing_key'] ?? '');
            $rtgmsid = (int)($row['rtgmsid'] ?? 0);
            if ($routingKey === '' || $rtgmsid <= 0) {
                continue;
            }
            if (!isset($routingToRtgms[$routingKey])) {
                $routingToRtgms[$routingKey] = [];
            }
            $routingToRtgms[$routingKey][$rtgmsid] = $rtgmsid;
        }
        if (empty($routingToRtgms)) {
            return [];
        }

        $productionTable = '';
        if ($tableExists($conn3, 'pdproductionhd')) {
            $productionTable = 'pdproductionhd';
        } elseif ($tableExists($conn3, 'pdproductionshd')) {
            $productionTable = 'pdproductionshd';
        }
        if ($productionTable === '') {
            return [];
        }

        [$cpHolders, $cpParams] = $buildNamedPlaceholders(array_values($cpSet), 'cp_');
        if (empty($cpHolders)) {
            return [];
        }

        $sqlProduction = "
            WITH ranked AS (
                SELECT
                    CAST(productionhdid AS INTEGER) AS productionhdid,
                    UPPER(TRIM(CAST(prdnmbr AS TEXT))) AS cp_key,
                    ROW_NUMBER() OVER (
                        PARTITION BY UPPER(TRIM(CAST(prdnmbr AS TEXT)))
                        ORDER BY prddate DESC NULLS LAST, productionhdid DESC
                    ) AS rn
                FROM {$productionTable}
                WHERE UPPER(TRIM(CAST(prdnmbr AS TEXT))) IN (" . implode(', ', $cpHolders) . ")
            )
            SELECT productionhdid, cp_key
            FROM ranked
            WHERE rn = 1
        ";
        $stmtProduction = $conn3->prepare($sqlProduction);
        $stmtProduction->execute($cpParams);

        $cpToProduction = [];
        while ($row = $stmtProduction->fetch(PDO::FETCH_ASSOC)) {
            $cpKey = $normalizeCp($row['cp_key'] ?? '');
            $productionId = (int)($row['productionhdid'] ?? 0);
            if ($cpKey === '' || $productionId <= 0) {
                continue;
            }
            $cpToProduction[$cpKey] = $productionId;
        }
        if (empty($cpToProduction)) {
            return [];
        }

        $pairNeeds = [];
        $productionIds = [];
        $rtgmsIds = [];
        foreach ($pairRows as $pair) {
            $cpKey = $pair['cp_key'];
            if (!isset($cpToProduction[$cpKey])) {
                continue;
            }
            $productionId = (int)$cpToProduction[$cpKey];
            $selectedRoutingKey = $normalizeRouting($pair['selected_routing_key'] ?? '');
            if ($selectedRoutingKey === '' || !isset($routingToRtgms[$selectedRoutingKey])) {
                continue;
            }
            $ids = array_values($routingToRtgms[$selectedRoutingKey]);
            if (empty($ids)) {
                continue;
            }

            $lookupKey = trim((string)($pair['lookup_key'] ?? ''));
            if ($lookupKey === '') {
                $lookupKey = (string)($pair['pair_key'] ?? ($cpKey . '|' . $pair['routing_key']));
            }
            $pairNeeds[$lookupKey] = [
                'pair_key' => $pair['pair_key'],
                'productionhdid' => $productionId,
                'rtgmsids' => $ids,
                'reference_date_ts' => (int)$pair['reference_date_ts'],
            ];
            $productionIds[$productionId] = $productionId;
            foreach ($ids as $rid) {
                $rid = (int)$rid;
                if ($rid > 0) {
                    $rtgmsIds[$rid] = $rid;
                }
            }
        }
        if (empty($pairNeeds) || empty($productionIds) || empty($rtgmsIds) || !$tableExists($conn3, 'pdproductionrtg')) {
            return [];
        }

        [$prdHolders, $prdParams] = $buildNamedPlaceholders(array_values($productionIds), 'pid_');
        [$ridHolders, $ridParams] = $buildNamedPlaceholders(array_values($rtgmsIds), 'rid_');
        $sqlRtg = "
            SELECT
                CAST(productionhdid AS INTEGER) AS productionhdid,
                CAST(rtgmsid AS INTEGER) AS rtgmsid,
                CAST(rtgseq AS INTEGER) AS rtgseq,
                startdate,
                starttime,
                enddate,
                endtime
            FROM pdproductionrtg
            WHERE productionhdid IN (" . implode(', ', $prdHolders) . ")
              AND rtgmsid IN (" . implode(', ', $ridHolders) . ")
        ";
        $stmtRtg = $conn3->prepare($sqlRtg);
        $stmtRtg->execute($prdParams + $ridParams);

        $rowsByProdAndRtg = [];
        while ($row = $stmtRtg->fetch(PDO::FETCH_ASSOC)) {
            $productionId = (int)($row['productionhdid'] ?? 0);
            $rtgmsid = (int)($row['rtgmsid'] ?? 0);
            if ($productionId <= 0 || $rtgmsid <= 0) {
                continue;
            }

            $startHm = $extractTimeHHmm($row['starttime'] ?? null);
            $endHm = $extractTimeHHmm($row['endtime'] ?? null);
            $startTs = $toActualDateTimeTs($row['startdate'] ?? null, $row['starttime'] ?? null);
            $endTs = $toActualDateTimeTs($row['enddate'] ?? null, $row['endtime'] ?? null);
            $actualMarkerTs = $startTs > 0 ? $startTs : $endTs;
            $eventTs = max($startTs, $endTs);
            $timeCount = 0;
            if ($startHm !== '') {
                $timeCount++;
            }
            if ($endHm !== '') {
                $timeCount++;
            }
            $dateTs = ($toDateOnlyTs($row['startdate'] ?? null)
                ?: $toDateOnlyTs($row['enddate'] ?? null)
                ?: ($actualMarkerTs > 0 ? (strtotime(date('Y-m-d', $actualMarkerTs)) ?: 0) : 0));

            $mapKey = $productionId . '|' . $rtgmsid;
            if (!isset($rowsByProdAndRtg[$mapKey])) {
                $rowsByProdAndRtg[$mapKey] = [];
            }
            $rowsByProdAndRtg[$mapKey][] = [
                'actual_start' => $startHm,
                'actual_end' => $endHm,
                'time_count' => $timeCount,
                'event_ts' => $eventTs,
                'actual_marker_ts' => $actualMarkerTs,
                'actual_marker_at' => $formatDateTime($actualMarkerTs),
                'rtgseq' => (int)($row['rtgseq'] ?? 0),
                'date_ts' => $dateTs,
            ];
        }
        if (empty($rowsByProdAndRtg)) {
            return [];
        }

        $isCandidateBetter = static function (?array $current, array $next, int $refDateTs): bool {
            if ($current === null) {
                return true;
            }

            $currDateTs = (int)($current['date_ts'] ?? 0);
            $nextDateTs = (int)($next['date_ts'] ?? 0);
            if ($refDateTs > 0) {
                $currHasDate = $currDateTs > 0;
                $nextHasDate = $nextDateTs > 0;
                if ($nextHasDate && !$currHasDate) {
                    return true;
                }
                if (!$nextHasDate && $currHasDate) {
                    return false;
                }
                if ($nextHasDate && $currHasDate) {
                    $currDiff = abs((int)(($currDateTs - $refDateTs) / 86400));
                    $nextDiff = abs((int)(($nextDateTs - $refDateTs) / 86400));
                    if ($nextDiff < $currDiff) {
                        return true;
                    }
                    if ($nextDiff > $currDiff) {
                        return false;
                    }
                }
            }

            $keys = ['time_count', 'event_ts', 'rtgseq'];
            foreach ($keys as $key) {
                $currVal = (int)($current[$key] ?? 0);
                $nextVal = (int)($next[$key] ?? 0);
                if ($nextVal > $currVal) {
                    return true;
                }
                if ($nextVal < $currVal) {
                    return false;
                }
            }
            return false;
        };

        $result = [];
        foreach ($pairNeeds as $pairKey => $need) {
            $productionId = (int)$need['productionhdid'];
            $referenceDateTs = (int)($need['reference_date_ts'] ?? 0);
            $best = null;
            foreach ((array)$need['rtgmsids'] as $rid) {
                $mapKey = $productionId . '|' . (int)$rid;
                if (!isset($rowsByProdAndRtg[$mapKey])) {
                    continue;
                }
                foreach ($rowsByProdAndRtg[$mapKey] as $candidate) {
                    if (!$isCandidateInsidePlanningWindow($candidate, $referenceDateTs)) {
                        continue;
                    }
                    if ($isCandidateBetter($best, $candidate, $referenceDateTs)) {
                        $best = $candidate;
                    }
                }
            }
            if ($best === null) {
                continue;
            }
            $result[$pairKey] = [
                'actual_start' => trim((string)($best['actual_start'] ?? '')),
                'actual_end' => trim((string)($best['actual_end'] ?? '')),
                'actual_marker_at' => trim((string)($best['actual_marker_at'] ?? '')),
            ];
        }

        return $result;
    }
}

if (!function_exists('cpsSummaryFetchLatestRoutingQtyByCp')) {
    /**
     * Ambil qty realisasi terbaru dari ERP: routing terakhir yang sudah punya prdqty.
     *
     * @param PDO|null $conn3
     * @param array<int,string> $cpNos
     * @return array<string,float>
     */
    function cpsSummaryFetchLatestRoutingQtyByCp($conn3, array $cpNos): array
    {
        if (!($conn3 instanceof PDO) || empty($cpNos)) {
            return [];
        }

        $cpSet = [];
        foreach ($cpNos as $cpNo) {
            $cpKey = strtoupper(trim((string)$cpNo));
            if ($cpKey !== '' && !cpsSummaryIsBreakTimeCpNo($cpKey)) {
                $cpSet[$cpKey] = $cpKey;
            }
        }
        if (empty($cpSet)) {
            return [];
        }

        $buildNamedPlaceholders = static function (array $values, string $prefix): array {
            $holders = [];
            $params = [];
            foreach (array_values($values) as $idx => $value) {
                $holder = ':' . $prefix . $idx;
                $holders[] = $holder;
                $params[$holder] = $value;
            }
            return [$holders, $params];
        };

        $result = [];
        foreach (array_chunk(array_values($cpSet), 500) as $chunk) {
            [$cpHolders, $cpParams] = $buildNamedPlaceholders($chunk, 'cp_');
            if (empty($cpHolders)) {
                continue;
            }

            $sql = "
                WITH ranked_hd AS (
                    SELECT
                        CAST(h.productionhdid AS INTEGER) AS productionhdid,
                        UPPER(TRIM(CAST(h.prdnmbr AS TEXT))) AS cp_key,
                        ROW_NUMBER() OVER (
                            PARTITION BY UPPER(TRIM(CAST(h.prdnmbr AS TEXT)))
                            ORDER BY h.prddate DESC NULLS LAST, h.productionhdid DESC
                        ) AS rn
                    FROM pdproductionhd h
                    WHERE UPPER(TRIM(CAST(h.prdnmbr AS TEXT))) IN (" . implode(', ', $cpHolders) . ")
                ),
                latest_rtg AS (
                    SELECT
                        h.cp_key,
                        r.prdqty,
                        ROW_NUMBER() OVER (
                            PARTITION BY h.cp_key
                            ORDER BY
                                CAST(r.rtgseq AS INTEGER) DESC NULLS LAST,
                                r.productionrtgid DESC
                        ) AS rn
                    FROM ranked_hd h
                    INNER JOIN pdproductionrtg r
                        ON r.productionhdid = h.productionhdid
                    WHERE h.rn = 1
                      AND COALESCE(r.prdqty, 0.0000) > 0
                )
                SELECT cp_key, prdqty
                FROM latest_rtg
                WHERE rn = 1
            ";

            $stmt = $conn3->prepare($sql);
            $stmt->execute($cpParams);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $cpKey = strtoupper(trim((string)($row['cp_key'] ?? '')));
                if ($cpKey === '') {
                    continue;
                }
                $result[$cpKey] = cpsSummaryToFloat($row['prdqty'] ?? 0);
            }
        }

        return $result;
    }
}

if (!function_exists('cpsSummaryDayName')) {
    function cpsSummaryDayName(string $isoDate): string
    {
        $dt = DateTime::createFromFormat('Y-m-d', $isoDate);
        if (!$dt) {
            return '-';
        }
        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        return $days[(int)$dt->format('w')] ?? '-';
    }
}

if (!function_exists('cpsSummaryIsBreakTimeCpNo')) {
    function cpsSummaryIsBreakTimeCpNo(string $cpNo): bool
    {
        $normalized = strtolower(trim($cpNo));
        if ($normalized === '') {
            return false;
        }

        $normalized = preg_replace('/\s+/', ' ', $normalized);
        if ($normalized === null) {
            $normalized = strtolower(trim($cpNo));
        }

        return strpos($normalized, 'break') !== false
            || strpos($normalized, 'cleaning') !== false
            || strpos($normalized, 'bilas') !== false
            || strpos($normalized, 'pm +') !== false
            || strpos($normalized, 'pm+') !== false;
    }
}

$esc = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$themeColor = $_SESSION['Theme'] ?? 'primary';
$todayIso = date('Y-m-d');

$pageWarnings = [];
$pageError = '';

$planTypeInput = trim((string)($_GET['plan_type'] ?? ''));
$machineIdInput = trim((string)($_GET['machine_id'] ?? ''));
$dateFromInput = trim((string)($_GET['date_from'] ?? ''));
$dateToInput = trim((string)($_GET['date_to'] ?? ''));

$defaultFromDate = $todayIso;
$defaultToDate = date('Y-m-d', strtotime($todayIso . ' +7 days'));
$fromDate = cpsSummaryToIsoDate($dateFromInput);
$toDate = cpsSummaryToIsoDate($dateToInput);
if ($fromDate === '') {
    $fromDate = $defaultFromDate;
}
if ($toDate === '') {
    $toDate = $defaultToDate;
}
if ($fromDate > $toDate) {
    $tmp = $fromDate;
    $fromDate = $toDate;
    $toDate = $tmp;
}

$hasMsMachine = cpsSummaryTableExists($conn, 'ms_machine');
$msMachineCols = $hasMsMachine ? cpsSummaryTableColumns($conn, 'ms_machine') : [];
$canJoinMsMachine = $hasMsMachine && isset($msMachineCols['machine_id']);
$hasMachinePlanTypeCol = $canJoinMsMachine && isset($msMachineCols['planning_type']);
$hasMachineNameCol = $canJoinMsMachine && isset($msMachineCols['machine_name']);

$machineMasterByKey = [];
$machineMasterList = [];
if ($canJoinMsMachine) {
    $machineSql = "
        SELECT
            LTRIM(RTRIM(CAST(machine_id AS NVARCHAR(100)))) AS machine_id,
            " . ($hasMachineNameCol
                ? "LTRIM(RTRIM(CAST(ISNULL(machine_name, '') AS NVARCHAR(200))))"
                : "LTRIM(RTRIM(CAST(machine_id AS NVARCHAR(100))))") . " AS machine_name,
            " . ($hasMachinePlanTypeCol
                ? "LTRIM(RTRIM(CAST(ISNULL(planning_type, '') AS NVARCHAR(100))))"
                : "CAST('' AS NVARCHAR(100))") . " AS planning_type
        FROM dbo.ms_machine
        ORDER BY machine_id ASC
    ";
    $stmtMachineMaster = sqlsrv_query($conn, $machineSql);
    if ($stmtMachineMaster === false) {
        $pageWarnings[] = 'Master machine tidak bisa dibaca: ' . cpsSummarySqlErrorText('Query machine gagal.');
    } else {
        while ($row = sqlsrv_fetch_array($stmtMachineMaster, SQLSRV_FETCH_ASSOC)) {
            $machineId = trim((string)($row['machine_id'] ?? ''));
            if ($machineId === '') {
                continue;
            }
            $machineName = trim((string)($row['machine_name'] ?? ''));
            if ($machineName === '') {
                $machineName = $machineId;
            }
            $planType = trim((string)($row['planning_type'] ?? ''));

            $machineItem = [
                'machine_id' => $machineId,
                'machine_name' => $machineName,
                'planning_type' => $planType,
            ];
            $machineMasterList[] = $machineItem;
            $machineMasterByKey[strtoupper($machineId)] = $machineItem;
        }
        sqlsrv_free_stmt($stmtMachineMaster);
    }
}

$planTypeMap = [];
$planTypeOptions = [];
if (cpsSummaryTableExists($conn, 'ms_tipe_planning')) {
    $stmtPlanType = sqlsrv_query(
        $conn,
        "SELECT LTRIM(RTRIM(CAST(tipe_planning_name AS NVARCHAR(100)))) AS plan_type
         FROM dbo.ms_tipe_planning
         ORDER BY tipe_planning_id ASC"
    );
    if ($stmtPlanType !== false) {
        while ($row = sqlsrv_fetch_array($stmtPlanType, SQLSRV_FETCH_ASSOC)) {
            $type = trim((string)($row['plan_type'] ?? ''));
            if ($type === '') {
                continue;
            }
            $key = strtolower($type);
            if (!isset($planTypeMap[$key])) {
                $planTypeMap[$key] = $type;
                $planTypeOptions[] = $type;
            }
        }
        sqlsrv_free_stmt($stmtPlanType);
    }
}

if (empty($planTypeOptions) && !empty($machineMasterList)) {
    foreach ($machineMasterList as $m) {
        $type = trim((string)($m['planning_type'] ?? ''));
        if ($type === '') {
            continue;
        }
        $key = strtolower($type);
        if (!isset($planTypeMap[$key])) {
            $planTypeMap[$key] = $type;
            $planTypeOptions[] = $type;
        }
    }
}

$pickDefaultPaddryPlanType = static function (array $planTypeMap, array $planTypeOptions): string {
    $aliases = ['paddry', 'planning paddry', 'plan paddry'];
    foreach ($aliases as $alias) {
        if (isset($planTypeMap[$alias])) {
            return (string)$planTypeMap[$alias];
        }
    }
    foreach ($planTypeOptions as $opt) {
        if (stripos((string)$opt, 'paddry') !== false) {
            return (string)$opt;
        }
    }
    return '';
};

$machineNaturalSort = static function (array $a, array $b): int {
    $normalize = static function (array $item): array {
        $nameRaw = trim((string)($item['machine_name'] ?? ''));
        $idRaw = trim((string)($item['machine_id'] ?? ''));
        $nameRaw = str_replace("\xC2\xA0", ' ', $nameRaw);
        $name = strtoupper(preg_replace('/\s+/u', ' ', $nameRaw) ?? $nameRaw);
        $id = strtoupper($idRaw);
        $prefix = trim((string)(preg_replace('/\d+/', '', $name) ?? $name));
        $number = PHP_INT_MAX;
        if (preg_match('/PAD\W*DRY\W*(\d+)/iu', $name, $m)) {
            $prefix = 'PAD DRY';
            $number = (int)$m[1];
        } elseif (preg_match('/^(.*?)(\d+)(?!.*\d)/u', $name, $m)) {
            $prefix = trim((string)$m[1]);
            $number = (int)$m[2];
        }
        return [$prefix, $number, $name, $id];
    };

    [$pa, $na, $nameA, $idA] = $normalize($a);
    [$pb, $nb, $nameB, $idB] = $normalize($b);
    $cmp = strcmp($pa, $pb);
    if ($cmp !== 0) {
        return $cmp;
    }
    if ($na !== $nb) {
        return $na <=> $nb;
    }
    $cmpName = strcmp($nameA, $nameB);
    if ($cmpName !== 0) {
        return $cmpName;
    }
    return strcmp($idA, $idB);
};

$selectedPlanType = '';
if ($planTypeInput !== '') {
    $inputKey = strtolower($planTypeInput);
    if (isset($planTypeMap[$inputKey])) {
        $selectedPlanType = $planTypeMap[$inputKey];
    } else {
        $selectedPlanType = $planTypeInput;
        $pageWarnings[] = 'Tipe planning "' . $planTypeInput . '" tidak ada di master. Filter tetap dipakai sesuai input.';
    }
} else {
    $defaultPaddryPlanType = $pickDefaultPaddryPlanType($planTypeMap, $planTypeOptions);
    if ($defaultPaddryPlanType !== '') {
        $selectedPlanType = $defaultPaddryPlanType;
    }
}

$machineOptions = [];
if (!empty($machineMasterList)) {
    foreach ($machineMasterList as $m) {
        if ($selectedPlanType !== '') {
            $mType = trim((string)($m['planning_type'] ?? ''));
            if ($mType === '' || strcasecmp($mType, $selectedPlanType) !== 0) {
                continue;
            }
        }
        $machineOptions[] = $m;
    }
    usort($machineOptions, $machineNaturalSort);
}

$selectedMachineId = $machineIdInput !== '' ? $machineIdInput : '';
$selectedMachineKey = strtoupper($selectedMachineId);
$selectedMachineInfo = $selectedMachineKey !== '' && isset($machineMasterByKey[$selectedMachineKey])
    ? $machineMasterByKey[$selectedMachineKey]
    : null;

if ($selectedMachineId !== '' && $selectedMachineInfo === null) {
    $pageWarnings[] = 'Machine "' . $selectedMachineId . '" tidak ada di master. Filter machine tetap dipakai sesuai input.';
}

if ($selectedPlanType !== '' && !$hasMachinePlanTypeCol) {
    $pageWarnings[] = 'Kolom planning_type di ms_machine tidak tersedia. Filter plan type bisa terbatas.';
}

$capacityByMachine = [];
if (cpsSummaryTableExists($conn, 'ms_max_production_capacity')) {
    $capacitySql = "
        SELECT
            LTRIM(RTRIM(CAST(machine_id AS NVARCHAR(100)))) AS machine_id,
            LTRIM(RTRIM(CAST(ISNULL(planning_type, '') AS NVARCHAR(100)))) AS planning_type,
            CAST(max_capacity_day AS FLOAT) AS max_capacity_day
        FROM dbo.ms_max_production_capacity
    ";
    $capacityParams = [];
    if ($selectedPlanType !== '') {
        $capacitySql .= " WHERE LOWER(LTRIM(RTRIM(CAST(planning_type AS NVARCHAR(100))))) = LOWER(LTRIM(RTRIM(?)))";
        $capacityParams[] = $selectedPlanType;
    }
    $capacitySql .= " ORDER BY machine_id ASC";

    $stmtCapacity = sqlsrv_query($conn, $capacitySql, $capacityParams);
    if ($stmtCapacity === false) {
        $pageWarnings[] = 'Master max capacity tidak bisa dibaca: ' . cpsSummarySqlErrorText('Query max capacity gagal.');
    } else {
        while ($row = sqlsrv_fetch_array($stmtCapacity, SQLSRV_FETCH_ASSOC)) {
            $machineId = trim((string)($row['machine_id'] ?? ''));
            if ($machineId === '') {
                continue;
            }
            $key = strtoupper($machineId);
            $maxCapacity = cpsSummaryToFloat($row['max_capacity_day'] ?? 0);
            if (!isset($capacityByMachine[$key])) {
                $capacityByMachine[$key] = [
                    'machine_id' => $machineId,
                    'planning_type' => trim((string)($row['planning_type'] ?? '')),
                    'max_capacity_day' => $maxCapacity,
                ];
            } elseif ($maxCapacity > $capacityByMachine[$key]['max_capacity_day']) {
                $capacityByMachine[$key]['max_capacity_day'] = $maxCapacity;
            }
        }
        sqlsrv_free_stmt($stmtCapacity);
    }
}

$sourceConfigs = [
    ['key' => 'paddry', 'label' => 'Paddry', 'table' => 'cpp_paddry'],
    ['key' => 'bakar_bulu', 'label' => 'Bakar Bulu', 'table' => 'cpp_bakar_bulu'],
    ['key' => 'scouring', 'label' => 'Scouring', 'table' => 'cpp_scouring'],
    ['key' => 'presett', 'label' => 'PreSett', 'table' => 'cpp_presett'],
];

$allPlanningRows = [];
$sourceAgg = [];
$dailyAgg = [];
$machineAgg = [];
$machineDailyQty = [];
$distinctCpSet = [];
$distinctMachineSet = [];
$distinctDateSet = [];
$latestFilterDate = '';
$totalQtyPlanned = 0.0;
$totalRowCount = 0;
$sourceDateMeta = [];

foreach ($sourceConfigs as $source) {
    $tableName = $source['table'];
    if (!cpsSummaryTableExists($conn, $tableName)) {
        continue;
    }

    $tableCols = cpsSummaryTableColumns($conn, $tableName);
    if (!isset($tableCols['machine_id']) || !isset($tableCols['cp_no']) || !isset($tableCols['qty'])) {
        $pageWarnings[] = 'Struktur tabel ' . $tableName . ' tidak sesuai (wajib: machine_id, cp_no, qty).';
        continue;
    }

    // Samakan perilaku dengan cp_planning.php:
    // filter utama planning memakai period_date (bukan tgl).
    $dateCol = cpsSummaryResolveColumn($tableCols, ['period_date', 'tgl', 'plan_date']);
    if ($dateCol === null) {
        $pageWarnings[] = 'Tabel ' . $tableName . ' tidak memiliki kolom tanggal planning.';
        continue;
    }

    $periodCol = cpsSummaryResolveColumn($tableCols, ['period_date', $dateCol]) ?? $dateCol;
    $labelCol = cpsSummaryResolveColumn($tableCols, ['label']);
    $materialCol = cpsSummaryResolveColumn($tableCols, ['material_name', 'material']);
    $kodeLabCol = cpsSummaryResolveColumn($tableCols, ['kode_lab', 'color_code', 'colorcode']);
    $nextRoutingCol = cpsSummaryResolveColumn($tableCols, ['next_routing']);
    $lokasiPaddryCol = cpsSummaryResolveColumn($tableCols, ['lokasi_paddry']);
    $routingQtyCol = cpsSummaryResolveColumn($tableCols, ['aktual_qty', 'actual_qty', 'routing_qty']);
    $actualStartCol = cpsSummaryResolveColumn($tableCols, ['actual_start', 'aktual_start', 'actual_celup_start', 'start_aktual_celup']);
    $actualFinishCol = cpsSummaryResolveColumn($tableCols, ['actual_finish', 'aktual_finish', 'actual_end', 'actual_celup_finish', 'finish_aktual_celup']);
    $actualToppingStartCol = cpsSummaryResolveColumn($tableCols, ['actual_topping_paddry_start', 'aktual_topping_paddry_start', 'actual_topping_start', 'aktual_topping_start']);
    $actualToppingFinishCol = cpsSummaryResolveColumn($tableCols, ['actual_topping_paddry_finish', 'aktual_topping_paddry_finish', 'actual_topping_finish', 'aktual_topping_finish']);
    $lastUpdateCol = cpsSummaryResolveColumn($tableCols, ['last_update', 'update_at', 'upddate', 'creat_at', 'created_at']);
    $seqCol = cpsSummaryResolveColumn($tableCols, ['seq_no', 'id']);

    $machineNameExpr = "CAST('' AS NVARCHAR(200))";
    $machinePlanTypeExpr = "CAST('' AS NVARCHAR(100))";
    if ($canJoinMsMachine) {
        $machineNameExpr = $hasMachineNameCol
            ? "LTRIM(RTRIM(CAST(ISNULL(m.machine_name, '') AS NVARCHAR(200))))"
            : "LTRIM(RTRIM(CAST(m.machine_id AS NVARCHAR(100))))";
        $machinePlanTypeExpr = $hasMachinePlanTypeCol
            ? "LTRIM(RTRIM(CAST(ISNULL(m.planning_type, '') AS NVARCHAR(100))))"
            : "CAST('' AS NVARCHAR(100))";
    }

    $sql = "
        SELECT
            LTRIM(RTRIM(CAST(t.machine_id AS NVARCHAR(100)))) AS machine_id,
            TRY_CONVERT(date, t.[$dateCol]) AS plan_date,
            TRY_CONVERT(date, t.[$periodCol]) AS period_date,
            LTRIM(RTRIM(CAST(t.cp_no AS NVARCHAR(100)))) AS cp_no,
            CAST(t.qty AS FLOAT) AS qty,
            " . ($routingQtyCol !== null
                ? "CAST(t.[$routingQtyCol] AS FLOAT)"
                : "CAST(0 AS FLOAT)") . " AS routing_qty,
            " . ($labelCol !== null
                ? "LTRIM(RTRIM(CAST(ISNULL(t.[$labelCol], '') AS NVARCHAR(200))))"
                : "CAST('' AS NVARCHAR(200))") . " AS label,
            " . ($kodeLabCol !== null
                ? "LTRIM(RTRIM(CAST(ISNULL(t.[$kodeLabCol], '') AS NVARCHAR(200))))"
                : "CAST('' AS NVARCHAR(200))") . " AS kode_lab,
            " . ($materialCol !== null
                ? "LTRIM(RTRIM(CAST(ISNULL(t.[$materialCol], '') AS NVARCHAR(255))))"
                : "CAST('' AS NVARCHAR(255))") . " AS material_name,
            " . ($nextRoutingCol !== null
                ? "LTRIM(RTRIM(CAST(ISNULL(t.[$nextRoutingCol], '') AS NVARCHAR(255))))"
                : "CAST('' AS NVARCHAR(255))") . " AS next_routing,
            " . ($lokasiPaddryCol !== null
                ? "LTRIM(RTRIM(CAST(ISNULL(t.[$lokasiPaddryCol], '') AS NVARCHAR(255))))"
                : "CAST('' AS NVARCHAR(255))") . " AS lokasi_paddry,
            " . ($actualStartCol !== null
                ? "LTRIM(RTRIM(CAST(ISNULL(t.[$actualStartCol], '') AS NVARCHAR(100))))"
                : "CAST('' AS NVARCHAR(100))") . " AS actual_start,
            " . ($actualFinishCol !== null
                ? "LTRIM(RTRIM(CAST(ISNULL(t.[$actualFinishCol], '') AS NVARCHAR(100))))"
                : "CAST('' AS NVARCHAR(100))") . " AS actual_finish,
            " . ($actualToppingStartCol !== null
                ? "LTRIM(RTRIM(CAST(ISNULL(t.[$actualToppingStartCol], '') AS NVARCHAR(100))))"
                : "CAST('' AS NVARCHAR(100))") . " AS actual_topping_paddry_start,
            " . ($actualToppingFinishCol !== null
                ? "LTRIM(RTRIM(CAST(ISNULL(t.[$actualToppingFinishCol], '') AS NVARCHAR(100))))"
                : "CAST('' AS NVARCHAR(100))") . " AS actual_topping_paddry_finish,
            " . ($seqCol !== null
                ? "TRY_CONVERT(INT, t.[$seqCol])"
                : "NULL") . " AS seq_no,
            " . ($lastUpdateCol !== null
                ? "t.[$lastUpdateCol]"
                : "NULL") . " AS last_update,
            $machineNameExpr AS machine_name,
            $machinePlanTypeExpr AS planning_type
        FROM dbo.[$tableName] t
        " . ($canJoinMsMachine
            ? "LEFT JOIN dbo.ms_machine m
               ON LTRIM(RTRIM(CAST(m.machine_id AS NVARCHAR(100))))
                = LTRIM(RTRIM(CAST(t.machine_id AS NVARCHAR(100))))"
            : "") . "
        WHERE TRY_CONVERT(date, t.[$dateCol]) >= ?
          AND TRY_CONVERT(date, t.[$dateCol]) <= ?
    ";
    $params = [$fromDate, $toDate];

    if ($selectedMachineId !== '') {
        $sql .= " AND LTRIM(RTRIM(CAST(t.machine_id AS NVARCHAR(100)))) = ?";
        $params[] = $selectedMachineId;
    }

    if ($selectedPlanType !== '' && $canJoinMsMachine && $hasMachinePlanTypeCol) {
        $sql .= " AND LOWER(LTRIM(RTRIM(CAST(m.planning_type AS NVARCHAR(100))))) = LOWER(LTRIM(RTRIM(?)))";
        $params[] = $selectedPlanType;
    }

    $sql .= "
        ORDER BY TRY_CONVERT(date, t.[$dateCol]) DESC,
                 LTRIM(RTRIM(CAST(t.machine_id AS NVARCHAR(100)))) ASC
                 " . ($seqCol !== null ? ", t.[$seqCol] ASC" : "") . "
    ";

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $pageWarnings[] = 'Gagal membaca data ' . $source['label'] . ': ' . cpsSummarySqlErrorText('Query summary gagal.');
        continue;
    }

    $sourceDateMeta[] = [
        'table' => $tableName,
        'date_col' => $dateCol,
    ];

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $planDateIso = cpsSummaryToIsoDate($row['plan_date'] ?? '');
        if ($planDateIso === '') {
            continue;
        }
        $periodDateIso = cpsSummaryToIsoDate($row['period_date'] ?? '');
        if ($periodDateIso === '') {
            $periodDateIso = $planDateIso;
        }

        $machineId = trim((string)($row['machine_id'] ?? ''));
        if ($machineId === '') {
            continue;
        }
        $machineKey = strtoupper($machineId);

        $cpNo = trim((string)($row['cp_no'] ?? ''));
        $cpKey = strtoupper($cpNo);
        $qty = cpsSummaryToFloat($row['qty'] ?? 0);

        $machineName = trim((string)($row['machine_name'] ?? ''));
        if ($machineName === '' && isset($machineMasterByKey[$machineKey])) {
            $machineName = (string)$machineMasterByKey[$machineKey]['machine_name'];
        }
        if ($machineName === '') {
            $machineName = $machineId;
        }

        $planningType = trim((string)($row['planning_type'] ?? ''));
        if ($planningType === '' && isset($machineMasterByKey[$machineKey])) {
            $planningType = trim((string)($machineMasterByKey[$machineKey]['planning_type'] ?? ''));
        }

        if ($selectedPlanType !== '' && $planningType !== '' && strcasecmp($planningType, $selectedPlanType) !== 0) {
            continue;
        }
        if ($selectedPlanType !== '' && $planningType === '' && $hasMachinePlanTypeCol) {
            continue;
        }

        $detailRow = [
            'source_key' => $source['key'],
            'source_label' => $source['label'],
            'plan_date' => $planDateIso,
            'period_date' => $periodDateIso,
            'machine_id' => $machineId,
            'machine_name' => $machineName,
            'planning_type' => $planningType,
            'cp_no' => $cpNo,
            'label' => trim((string)($row['label'] ?? '')),
            'kode_lab' => trim((string)($row['kode_lab'] ?? '')),
            'qty' => $qty,
            'routing_qty' => cpsSummaryToFloat($row['routing_qty'] ?? 0),
            'material_name' => trim((string)($row['material_name'] ?? '')),
            'next_routing' => trim((string)($row['next_routing'] ?? '')),
            'lokasi_paddry' => trim((string)($row['lokasi_paddry'] ?? '')),
            'actual_start' => trim((string)($row['actual_start'] ?? '')),
            'actual_finish' => trim((string)($row['actual_finish'] ?? '')),
            'actual_topping_paddry_start' => trim((string)($row['actual_topping_paddry_start'] ?? '')),
            'actual_topping_paddry_finish' => trim((string)($row['actual_topping_paddry_finish'] ?? '')),
            'actual_marker_at' => '',
            'actual_topping_marker_at' => '',
            'seq_no' => (isset($row['seq_no']) && is_numeric((string)$row['seq_no'])) ? (int)$row['seq_no'] : null,
            'last_update' => cpsSummaryToIsoDate($row['last_update'] ?? ''),
        ];
        $allPlanningRows[] = $detailRow;

        $totalRowCount++;
        $totalQtyPlanned += $qty;
        $distinctMachineSet[$machineKey] = true;
        $distinctDateSet[$planDateIso] = true;
        if ($cpKey !== '') {
            $distinctCpSet[$cpKey] = true;
        }

        if ($latestFilterDate === '' || $planDateIso > $latestFilterDate) {
            $latestFilterDate = $planDateIso;
        }

        if (!isset($sourceAgg[$source['key']])) {
            $sourceAgg[$source['key']] = [
                'source_key' => $source['key'],
                'source_label' => $source['label'],
                'row_count' => 0,
                'qty_total' => 0.0,
                'cp_set' => [],
                'latest_date' => '',
            ];
        }
        $sourceAgg[$source['key']]['row_count']++;
        $sourceAgg[$source['key']]['qty_total'] += $qty;
        if ($cpKey !== '') {
            $sourceAgg[$source['key']]['cp_set'][$cpKey] = true;
        }
        if ($sourceAgg[$source['key']]['latest_date'] === '' || $planDateIso > $sourceAgg[$source['key']]['latest_date']) {
            $sourceAgg[$source['key']]['latest_date'] = $planDateIso;
        }

        if (!isset($dailyAgg[$planDateIso])) {
            $dailyAgg[$planDateIso] = [
                'date' => $planDateIso,
                'row_count' => 0,
                'qty_total' => 0.0,
                'cp_set' => [],
                'machine_set' => [],
                'source_set' => [],
                'source_labels' => [],
            ];
        }
        $dailyAgg[$planDateIso]['row_count']++;
        $dailyAgg[$planDateIso]['qty_total'] += $qty;
        $dailyAgg[$planDateIso]['machine_set'][$machineKey] = true;
        $dailyAgg[$planDateIso]['source_set'][$source['key']] = true;
        $dailyAgg[$planDateIso]['source_labels'][$source['key']] = $source['label'];
        if ($cpKey !== '') {
            $dailyAgg[$planDateIso]['cp_set'][$cpKey] = true;
        }

        if (!isset($machineAgg[$machineKey])) {
            $machineAgg[$machineKey] = [
                'machine_key' => $machineKey,
                'machine_id' => $machineId,
                'machine_name' => $machineName,
                'planning_type' => $planningType,
                'row_count' => 0,
                'qty_total' => 0.0,
                'cp_set' => [],
                'date_set' => [],
                'earliest_date' => '',
                'latest_date' => '',
            ];
        }
        $machineAgg[$machineKey]['row_count']++;
        $machineAgg[$machineKey]['qty_total'] += $qty;
        if ($cpKey !== '') {
            $machineAgg[$machineKey]['cp_set'][$cpKey] = true;
        }
        $machineAgg[$machineKey]['date_set'][$planDateIso] = true;
        if ($machineAgg[$machineKey]['earliest_date'] === '' || $planDateIso < $machineAgg[$machineKey]['earliest_date']) {
            $machineAgg[$machineKey]['earliest_date'] = $planDateIso;
        }
        if ($machineAgg[$machineKey]['latest_date'] === '' || $planDateIso > $machineAgg[$machineKey]['latest_date']) {
            $machineAgg[$machineKey]['latest_date'] = $planDateIso;
        }
        if ($machineAgg[$machineKey]['planning_type'] === '' && $planningType !== '') {
            $machineAgg[$machineKey]['planning_type'] = $planningType;
        }

        if (!isset($machineDailyQty[$machineKey])) {
            $machineDailyQty[$machineKey] = [];
        }
        if (!isset($machineDailyQty[$machineKey][$planDateIso])) {
            $machineDailyQty[$machineKey][$planDateIso] = 0.0;
        }
        $machineDailyQty[$machineKey][$planDateIso] += $qty;
    }
    sqlsrv_free_stmt($stmt);
}

// Sinkronisasi aktual celup paddry dari ERP agar konsisten dengan cp_planning.php.
if (!empty($allPlanningRows)) {
    $paddryLookupRows = [];
    foreach ($allPlanningRows as $idx => $rowItem) {
        if (strtolower(trim((string)($rowItem['source_key'] ?? ''))) !== 'paddry') {
            continue;
        }
        $cpNo = trim((string)($rowItem['cp_no'] ?? ''));
        $lokasiPaddry = trim((string)($rowItem['lokasi_paddry'] ?? ''));
        if ($cpNo === '' || $lokasiPaddry === '') {
            $allPlanningRows[$idx]['actual_start'] = '';
            $allPlanningRows[$idx]['actual_finish'] = '';
            $allPlanningRows[$idx]['actual_topping_paddry_start'] = '';
            $allPlanningRows[$idx]['actual_topping_paddry_finish'] = '';
            $allPlanningRows[$idx]['actual_marker_at'] = '';
            $allPlanningRows[$idx]['actual_topping_marker_at'] = '';
            continue;
        }
        $paddryLookupRows[] = [
            'lookup_key' => 'row:' . (string)$idx,
            'row_index' => (int)$idx,
            'cp_no' => $cpNo,
            'lokasi_paddry' => $lokasiPaddry,
            'plan_date' => trim((string)($rowItem['plan_date'] ?? '')),
        ];
    }

    if (!empty($paddryLookupRows)) {
        $actualMap = cpsSummaryFetchPaddryActualCelupByCpRouting($conn3, $paddryLookupRows, 0);
        $actualToppingMap = cpsSummaryFetchPaddryActualCelupByCpRouting($conn3, $paddryLookupRows, 1);
        $normalizePairKey = static function ($cpNo, $routingName): string {
            $cpKey = strtoupper(trim((string)$cpNo));
            $routingRaw = trim((string)$routingName);
            if ($routingRaw !== '') {
                $routingRaw = preg_replace('/\s+/', ' ', $routingRaw) ?? $routingRaw;
            }
            $routingKey = strtoupper($routingRaw);
            if ($cpKey === '' || $routingKey === '') {
                return '';
            }
            return $cpKey . '|' . $routingKey;
        };

        foreach ($paddryLookupRows as $lookupRow) {
            $idx = (int)($lookupRow['row_index'] ?? -1);
            if ($idx < 0 || !isset($allPlanningRows[$idx])) {
                continue;
            }

            $lookupKey = trim((string)($lookupRow['lookup_key'] ?? ''));
            $pairKey = $normalizePairKey($lookupRow['cp_no'] ?? '', $lookupRow['lokasi_paddry'] ?? '');
            $actual = null;
            $actualTop = null;
            if ($lookupKey !== '' && isset($actualMap[$lookupKey])) {
                $actual = $actualMap[$lookupKey];
            } elseif ($pairKey !== '' && isset($actualMap[$pairKey])) {
                $actual = $actualMap[$pairKey];
            }
            if ($lookupKey !== '' && isset($actualToppingMap[$lookupKey])) {
                $actualTop = $actualToppingMap[$lookupKey];
            } elseif ($pairKey !== '' && isset($actualToppingMap[$pairKey])) {
                $actualTop = $actualToppingMap[$pairKey];
            }

            if (!is_array($actual)) {
                // Tidak ada aktual celup ERP untuk pair ini: paksa kosong agar tidak false-positive.
                $allPlanningRows[$idx]['actual_start'] = '';
                $allPlanningRows[$idx]['actual_finish'] = '';
                $allPlanningRows[$idx]['actual_marker_at'] = '';
            } else {
                $allPlanningRows[$idx]['actual_start'] = trim((string)($actual['actual_start'] ?? ''));
                $allPlanningRows[$idx]['actual_finish'] = trim((string)($actual['actual_end'] ?? ''));
                $allPlanningRows[$idx]['actual_marker_at'] = trim((string)($actual['actual_marker_at'] ?? ''));
            }

            if (!is_array($actualTop)) {
                // Tidak ada aktual topping ERP untuk pair ini: paksa kosong agar tidak false-positive.
                $allPlanningRows[$idx]['actual_topping_paddry_start'] = '';
                $allPlanningRows[$idx]['actual_topping_paddry_finish'] = '';
                $allPlanningRows[$idx]['actual_topping_marker_at'] = '';
                continue;
            }

            $allPlanningRows[$idx]['actual_topping_paddry_start'] = trim((string)($actualTop['actual_start'] ?? ''));
            $allPlanningRows[$idx]['actual_topping_paddry_finish'] = trim((string)($actualTop['actual_end'] ?? ''));
            $allPlanningRows[$idx]['actual_topping_marker_at'] = trim((string)($actualTop['actual_marker_at'] ?? ''));
        }
    }
}

$globalLatestDate = '';
foreach ($sourceDateMeta as $meta) {
    $tableName = $meta['table'];
    $dateCol = $meta['date_col'];

    $globalSql = "
        SELECT MAX(TRY_CONVERT(date, t.[$dateCol])) AS max_date
        FROM dbo.[$tableName] t
        " . ($canJoinMsMachine
            ? "LEFT JOIN dbo.ms_machine m
               ON LTRIM(RTRIM(CAST(m.machine_id AS NVARCHAR(100))))
                = LTRIM(RTRIM(CAST(t.machine_id AS NVARCHAR(100))))"
            : "") . "
        WHERE TRY_CONVERT(date, t.[$dateCol]) IS NOT NULL
    ";
    $globalParams = [];

    if ($selectedMachineId !== '') {
        $globalSql .= " AND LTRIM(RTRIM(CAST(t.machine_id AS NVARCHAR(100)))) = ?";
        $globalParams[] = $selectedMachineId;
    }
    if ($selectedPlanType !== '' && $canJoinMsMachine && $hasMachinePlanTypeCol) {
        $globalSql .= " AND LOWER(LTRIM(RTRIM(CAST(m.planning_type AS NVARCHAR(100))))) = LOWER(LTRIM(RTRIM(?)))";
        $globalParams[] = $selectedPlanType;
    }

    $stmtGlobal = sqlsrv_query($conn, $globalSql, $globalParams);
    if ($stmtGlobal === false) {
        continue;
    }
    $rowGlobal = sqlsrv_fetch_array($stmtGlobal, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmtGlobal);

    $maxDate = cpsSummaryToIsoDate($rowGlobal['max_date'] ?? '');
    if ($maxDate !== '' && ($globalLatestDate === '' || $maxDate > $globalLatestDate)) {
        $globalLatestDate = $maxDate;
    }
}

if (empty($allPlanningRows)) {
    $pageWarnings[] = 'Belum ada data planning pada filter yang dipilih.';
}

$sourceSummaryRows = [];
foreach ($sourceAgg as $key => $item) {
    $sourceSummaryRows[] = [
        'source_key' => $key,
        'source' => $item['source_label'],
        'row_count' => (int)$item['row_count'],
        'cp_count' => count($item['cp_set']),
        'qty_total' => $item['qty_total'],
        'latest_date' => $item['latest_date'],
    ];
}
usort($sourceSummaryRows, static function (array $a, array $b): int {
    if ($a['latest_date'] === $b['latest_date']) {
        return strcasecmp($a['source'], $b['source']);
    }
    return strcmp($b['latest_date'], $a['latest_date']);
});

$dailySummaryRows = [];
foreach ($dailyAgg as $date => $item) {
    $sourceLabels = array_values($item['source_labels']);
    sort($sourceLabels, SORT_NATURAL | SORT_FLAG_CASE);
    $dailySummaryRows[] = [
        'plan_date' => $date,
        'day_name' => cpsSummaryDayName($date),
        'machine_count' => count($item['machine_set']),
        'cp_count' => count($item['cp_set']),
        'row_count' => (int)$item['row_count'],
        'qty_total' => $item['qty_total'],
        'source_count' => count($item['source_set']),
        'source_list' => implode(', ', $sourceLabels),
    ];
}
usort($dailySummaryRows, static function (array $a, array $b): int {
    return strcmp($b['plan_date'], $a['plan_date']);
});

$machineSummaryRows = [];
$overCapacityRows = [];
foreach ($machineAgg as $machineKey => $item) {
    $latestDate = $item['latest_date'];
    $latestQty = ($latestDate !== '' && isset($machineDailyQty[$machineKey][$latestDate]))
        ? (float)$machineDailyQty[$machineKey][$latestDate]
        : 0.0;

    $capacity = isset($capacityByMachine[$machineKey])
        ? (float)$capacityByMachine[$machineKey]['max_capacity_day']
        : null;

    $utilPct = null;
    if ($capacity !== null && $capacity > 0) {
        $utilPct = ($latestQty / $capacity) * 100;
    }

    $statusText = 'No Capacity';
    if ($capacity !== null && $capacity > 0) {
        if ($latestQty > $capacity) {
            $statusText = 'Over Capacity';
        } elseif ($utilPct !== null && $utilPct >= 90) {
            $statusText = 'Near Limit';
        } else {
            $statusText = 'Normal';
        }
    }

    $overDays = 0;
    if ($capacity !== null && $capacity > 0 && isset($machineDailyQty[$machineKey])) {
        foreach ($machineDailyQty[$machineKey] as $dateIso => $qtyDay) {
            if ($qtyDay > $capacity) {
                $overDays++;
                $overCapacityRows[] = [
                    'plan_date' => $dateIso,
                    'machine_id' => $item['machine_id'],
                    'machine_name' => $item['machine_name'],
                    'plan_type' => $item['planning_type'],
                    'qty_total' => $qtyDay,
                    'max_capacity' => $capacity,
                    'selisih' => $qtyDay - $capacity,
                    'util_pct' => ($qtyDay / $capacity) * 100,
                ];
            }
        }
    }

    $machineSummaryRows[] = [
        'machine_id' => $item['machine_id'],
        'machine_name' => $item['machine_name'],
        'plan_type' => $item['planning_type'],
        'latest_date' => $latestDate,
        'cp_count' => count($item['cp_set']),
        'row_count' => (int)$item['row_count'],
        'qty_total' => $item['qty_total'],
        'latest_qty' => $latestQty,
        'max_capacity' => $capacity,
        'util_pct' => $utilPct,
        'status' => $statusText,
        'over_days' => $overDays,
    ];
}
usort($machineSummaryRows, static function (array $a, array $b): int {
    if ($a['latest_date'] === $b['latest_date']) {
        return strcasecmp($a['machine_id'], $b['machine_id']);
    }
    return strcmp($b['latest_date'], $a['latest_date']);
});

usort($overCapacityRows, static function (array $a, array $b): int {
    if ($a['plan_date'] === $b['plan_date']) {
        return strcasecmp($a['machine_id'], $b['machine_id']);
    }
    return strcmp($b['plan_date'], $a['plan_date']);
});

usort($allPlanningRows, static function (array $a, array $b): int {
    if ($a['plan_date'] === $b['plan_date']) {
        if ($a['machine_id'] === $b['machine_id']) {
            return strcasecmp($a['cp_no'], $b['cp_no']);
        }
        return strcasecmp($a['machine_id'], $b['machine_id']);
    }
    return strcmp($b['plan_date'], $a['plan_date']);
});
$recentPlanningRows = array_slice($allPlanningRows, 0, 500);

$distinctCpCount = count($distinctCpSet);
$activeMachineCount = count($distinctMachineSet);
$planningDayCount = count($distinctDateSet);
$overCapacityCount = count($overCapacityRows);
$avgQtyPerDay = ($planningDayCount > 0) ? ($totalQtyPlanned / $planningDayCount) : 0.0;

$filterPlannedUntilLabel = $latestFilterDate !== '' ? cpsSummaryFormatDateIndo($latestFilterDate) : '-';
$globalPlannedUntilLabel = $globalLatestDate !== '' ? cpsSummaryFormatDateIndo($globalLatestDate) : '-';

$globalUntilDiffLabel = '-';
if ($globalLatestDate !== '') {
    $todayDt = DateTime::createFromFormat('Y-m-d', $todayIso);
    $latestDt = DateTime::createFromFormat('Y-m-d', $globalLatestDate);
    if ($todayDt && $latestDt) {
        $diffDays = (int)$todayDt->diff($latestDt)->format('%r%a');
        if ($diffDays > 0) {
            $globalUntilDiffLabel = $diffDays . ' hari ke depan dari hari ini';
        } elseif ($diffDays < 0) {
            $globalUntilDiffLabel = abs($diffDays) . ' hari sebelum hari ini';
        } else {
            $globalUntilDiffLabel = 'Sampai hari ini';
        }
    }
}

$selectedMachineLabel = '-';
if ($selectedMachineInfo !== null) {
    $selectedMachineLabel = $selectedMachineInfo['machine_name'] . ' (' . $selectedMachineInfo['machine_id'] . ')';
} elseif ($selectedMachineId !== '') {
    $selectedMachineLabel = $selectedMachineId;
}

$detailData = [
    'daily' => [],
    'machine' => [],
    'source' => [],
    'over_capacity' => [],
    'recent' => [],
];

foreach ($dailySummaryRows as $row) {
    $detailData['daily'][] = [
        'plan_date' => $row['plan_date'],
        'day_name' => $row['day_name'],
        'machine_count' => (string)$row['machine_count'],
        'cp_count' => (string)$row['cp_count'],
        'row_count' => (string)$row['row_count'],
        'qty_total' => cpsSummaryFormatQty($row['qty_total']),
        'source_count' => (string)$row['source_count'],
        'source_list' => $row['source_list'] !== '' ? $row['source_list'] : '-',
    ];
}

foreach ($machineSummaryRows as $row) {
    $detailData['machine'][] = [
        'machine_id' => $row['machine_id'],
        'machine_name' => $row['machine_name'],
        'plan_type' => $row['plan_type'] !== '' ? $row['plan_type'] : '-',
        'latest_date' => $row['latest_date'] !== '' ? $row['latest_date'] : '-',
        'cp_count' => (string)$row['cp_count'],
        'row_count' => (string)$row['row_count'],
        'qty_total' => cpsSummaryFormatQty($row['qty_total']),
        'latest_qty' => cpsSummaryFormatQty($row['latest_qty']),
        'max_capacity' => $row['max_capacity'] !== null ? cpsSummaryFormatQty($row['max_capacity']) : '-',
        'util_pct' => $row['util_pct'] !== null ? cpsSummaryFormatPct($row['util_pct']) : '-',
        'status' => $row['status'],
        'over_days' => (string)$row['over_days'],
    ];
}

foreach ($sourceSummaryRows as $row) {
    $detailData['source'][] = [
        'source_key' => $row['source_key'],
        'source' => $row['source'],
        'row_count' => (string)$row['row_count'],
        'cp_count' => (string)$row['cp_count'],
        'qty_total' => cpsSummaryFormatQty($row['qty_total']),
        'latest_date' => $row['latest_date'] !== '' ? $row['latest_date'] : '-',
    ];
}

foreach ($overCapacityRows as $row) {
    $detailData['over_capacity'][] = [
        'plan_date' => $row['plan_date'],
        'machine_id' => $row['machine_id'],
        'machine_name' => $row['machine_name'],
        'plan_type' => $row['plan_type'] !== '' ? $row['plan_type'] : '-',
        'qty_total' => cpsSummaryFormatQty($row['qty_total']),
        'max_capacity' => cpsSummaryFormatQty($row['max_capacity']),
        'selisih' => cpsSummaryFormatQty($row['selisih']),
        'util_pct' => cpsSummaryFormatPct($row['util_pct']),
    ];
}

foreach ($recentPlanningRows as $row) {
    $detailData['recent'][] = [
        'plan_date' => $row['plan_date'],
        'source' => $row['source_label'],
        'source_key' => $row['source_key'],
        'machine_id' => $row['machine_id'],
        'machine_name' => $row['machine_name'],
        'plan_type' => $row['planning_type'] !== '' ? $row['planning_type'] : '-',
        'cp_no' => $row['cp_no'] !== '' ? $row['cp_no'] : '-',
        'label' => $row['label'] !== '' ? $row['label'] : '-',
        'qty' => cpsSummaryFormatQty($row['qty']),
        'material_name' => $row['material_name'] !== '' ? $row['material_name'] : '-',
        'next_routing' => $row['next_routing'] !== '' ? $row['next_routing'] : '-',
    ];
}

$cpNoTableRows = [];
$cpNoSeenMap = [];
foreach ($allPlanningRows as $row) {
    $cpNo = trim((string)($row['cp_no'] ?? ''));
    if ($cpNo === '') {
        continue;
    }
    $cpKey = strtoupper($cpNo);
    if (isset($cpNoSeenMap[$cpKey])) {
        continue;
    }
    $cpNoSeenMap[$cpKey] = true;
    $cpNoTableRows[] = $cpNo;
}

$machineTrendCardRows = [];
$machineDailySummaryRowsMap = [];

foreach ($machineAgg as $machineKey => $item) {
    $machineId = trim((string)($item['machine_id'] ?? ''));
    if ($machineId === '') {
        continue;
    }

    $machineName = trim((string)($item['machine_name'] ?? ''));
    if ($machineName === '') {
        $machineName = $machineId;
    }

    $dailyRowsPerMachine = [];
    $capacity = isset($capacityByMachine[$machineKey])
        ? (float)$capacityByMachine[$machineKey]['max_capacity_day']
        : null;

    foreach ($allPlanningRows as $row) {
        $rowMachineKey = strtoupper(trim((string)($row['machine_id'] ?? '')));
        if ($rowMachineKey !== $machineKey) {
            continue;
        }
        $planDate = trim((string)($row['plan_date'] ?? ''));
        if ($planDate === '') {
            continue;
        }
        $cpNo = trim((string)($row['cp_no'] ?? ''));
        if ($cpNo === '') {
            continue;
        }

        if (!isset($dailyRowsPerMachine[$planDate])) {
            $dailyRowsPerMachine[$planDate] = [
                'plan_date_iso' => $planDate,
                'plan_date' => cpsSummaryToIsoDate($planDate) !== '' ? date('d-M-y', strtotime($planDate)) : $planDate,
                'qty_total_value' => 0.0,
                'cp_set' => [],
            ];
        }
        $qty = cpsSummaryToFloat($row['qty'] ?? 0);
        $dailyRowsPerMachine[$planDate]['qty_total_value'] += $qty;
        if (!cpsSummaryIsBreakTimeCpNo($cpNo)) {
            $dailyRowsPerMachine[$planDate]['cp_set'][strtoupper($cpNo)] = true;
        }
    }

    $dailyRowsFormatted = [];
    foreach ($dailyRowsPerMachine as $dateKey => $dayRow) {
        $qtyTotalValue = (float)($dayRow['qty_total_value'] ?? 0.0);
        $isOverCapacity = ($capacity !== null && $capacity > 0 && $qtyTotalValue > $capacity);
        $keteranganKey = 'nocap';
        if ($capacity === null || $capacity <= 0) {
            $keterangan = 'Kapasitas mesin belum di-set';
            $keteranganKey = 'nocap';
        } elseif ($isOverCapacity) {
            $keterangan = 'Planning qty melebihi kapasitas mesin';
            $keteranganKey = 'over';
        } else {
            $keterangan = 'Planning qty masih di bawah kapasitas mesin';
            $keteranganKey = 'normal';
        }

        $dailyRowsFormatted[] = [
            'plan_date_iso' => (string)($dayRow['plan_date_iso'] ?? $dateKey),
            'plan_date' => (string)($dayRow['plan_date'] ?? $dateKey),
            'total_cp' => (string)count($dayRow['cp_set'] ?? []),
            'total_qty' => cpsSummaryFormatQty($qtyTotalValue),
            'capacity' => ($capacity !== null && $capacity > 0) ? cpsSummaryFormatQty($capacity) : '-',
            'keterangan' => $keterangan,
            'keterangan_key' => $keteranganKey,
        ];
    }
    usort($dailyRowsFormatted, static function (array $a, array $b): int {
        return strcmp((string)$a['plan_date_iso'], (string)$b['plan_date_iso']);
    });
    $machineDailySummaryRowsMap[$machineKey] = $dailyRowsFormatted;

    $machineTrendCardRows[] = [
        'machine_key' => $machineKey,
        'machine_id' => $machineId,
        'machine_name' => $machineName,
        'total_cp' => count($item['cp_set'] ?? []),
        'total_qty' => cpsSummaryFormatQty($item['qty_total'] ?? 0),
        'period_label' => (
            (($item['earliest_date'] ?? '') !== '' && ($item['latest_date'] ?? '') !== '')
                ? (cpsSummaryFormatDateIndo($item['earliest_date']) . ' s/d ' . cpsSummaryFormatDateIndo($item['latest_date']))
                : '-'
        ),
        'latest_date' => cpsSummaryFormatDateCell($item['latest_date'] ?? ''),
    ];
}

// Fallback: jika machine belum punya data dari hari ini, tampilkan data terakhir yang tersedia.
$existingMachineKeys = [];
foreach ($machineTrendCardRows as $cardItem) {
    $existingMachineKeys[strtoupper((string)($cardItem['machine_key'] ?? ''))] = true;
}

$targetMachinesForCards = $machineOptions;
if ($selectedMachineId !== '' && empty($targetMachinesForCards)) {
    $targetMachinesForCards[] = [
        'machine_id' => $selectedMachineId,
        'machine_name' => $selectedMachineId,
        'planning_type' => $selectedPlanType,
    ];
}

foreach ($targetMachinesForCards as $targetMachine) {
    $machineId = trim((string)($targetMachine['machine_id'] ?? ''));
    if ($machineId === '') {
        continue;
    }
    $machineKey = strtoupper($machineId);
    if (isset($existingMachineKeys[$machineKey])) {
        continue;
    }

    $fallbackLatestDate = '';
    foreach ($sourceConfigs as $sourceFb) {
        $tableNameFb = $sourceFb['table'];
        if (!cpsSummaryTableExists($conn, $tableNameFb)) {
            continue;
        }
        $colsFb = cpsSummaryTableColumns($conn, $tableNameFb);
        if (!isset($colsFb['machine_id']) || !isset($colsFb['cp_no']) || !isset($colsFb['qty'])) {
            continue;
        }
        $dateColFb = cpsSummaryResolveColumn($colsFb, ['period_date', 'tgl', 'plan_date']);
        if ($dateColFb === null) {
            continue;
        }

        $sqlMaxFb = "
            SELECT MAX(TRY_CONVERT(date, t.[$dateColFb])) AS max_date
            FROM dbo.[$tableNameFb] t
            WHERE LTRIM(RTRIM(CAST(t.machine_id AS NVARCHAR(100)))) = ?
              AND TRY_CONVERT(date, t.[$dateColFb]) < ?
        ";
        $stmtMaxFb = sqlsrv_query($conn, $sqlMaxFb, [$machineId, $todayIso]);
        if ($stmtMaxFb === false) {
            continue;
        }
        $rowMaxFb = sqlsrv_fetch_array($stmtMaxFb, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtMaxFb);

        $maxDateFb = cpsSummaryToIsoDate($rowMaxFb['max_date'] ?? '');
        if ($maxDateFb !== '' && ($fallbackLatestDate === '' || $maxDateFb > $fallbackLatestDate)) {
            $fallbackLatestDate = $maxDateFb;
        }
    }

    if ($fallbackLatestDate === '') {
        continue;
    }

    $fallbackQtyTotal = 0.0;
    $fallbackCpSet = [];
    foreach ($sourceConfigs as $sourceFbRow) {
        $tableNameFbRow = $sourceFbRow['table'];
        if (!cpsSummaryTableExists($conn, $tableNameFbRow)) {
            continue;
        }
        $colsFbRow = cpsSummaryTableColumns($conn, $tableNameFbRow);
        if (!isset($colsFbRow['machine_id']) || !isset($colsFbRow['cp_no']) || !isset($colsFbRow['qty'])) {
            continue;
        }
        $dateColFbRow = cpsSummaryResolveColumn($colsFbRow, ['period_date', 'tgl', 'plan_date']);
        if ($dateColFbRow === null) {
            continue;
        }

        $sqlRowsFb = "
            SELECT
                LTRIM(RTRIM(CAST(t.cp_no AS NVARCHAR(100)))) AS cp_no,
                CAST(t.qty AS FLOAT) AS qty
            FROM dbo.[$tableNameFbRow] t
            WHERE LTRIM(RTRIM(CAST(t.machine_id AS NVARCHAR(100)))) = ?
              AND TRY_CONVERT(date, t.[$dateColFbRow]) = ?
        ";
        $stmtRowsFb = sqlsrv_query($conn, $sqlRowsFb, [$machineId, $fallbackLatestDate]);
        if ($stmtRowsFb === false) {
            continue;
        }
        while ($rowFb = sqlsrv_fetch_array($stmtRowsFb, SQLSRV_FETCH_ASSOC)) {
            $cpNoFb = trim((string)($rowFb['cp_no'] ?? ''));
            if ($cpNoFb === '') {
                continue;
            }
            $fallbackQtyTotal += cpsSummaryToFloat($rowFb['qty'] ?? 0);
            if (!cpsSummaryIsBreakTimeCpNo($cpNoFb)) {
                $fallbackCpSet[strtoupper($cpNoFb)] = true;
            }
        }
        sqlsrv_free_stmt($stmtRowsFb);
    }

    if ($fallbackQtyTotal <= 0 && empty($fallbackCpSet)) {
        continue;
    }

    $capacityFallback = isset($capacityByMachine[$machineKey])
        ? (float)$capacityByMachine[$machineKey]['max_capacity_day']
        : null;
    $keteranganFallback = 'Kapasitas mesin belum di-set';
    $keteranganKeyFallback = 'nocap';
    if ($capacityFallback !== null && $capacityFallback > 0) {
        if ($fallbackQtyTotal > $capacityFallback) {
            $keteranganFallback = 'Planning qty melebihi kapasitas mesin';
            $keteranganKeyFallback = 'over';
        } else {
            $keteranganFallback = 'Planning qty masih di bawah kapasitas mesin';
            $keteranganKeyFallback = 'normal';
        }
    }

    $machineDailySummaryRowsMap[$machineKey] = [[
        'plan_date_iso' => $fallbackLatestDate,
        'plan_date' => date('d-M-y', strtotime($fallbackLatestDate)),
        'total_cp' => (string)count($fallbackCpSet),
        'total_qty' => cpsSummaryFormatQty($fallbackQtyTotal),
        'capacity' => ($capacityFallback !== null && $capacityFallback > 0) ? cpsSummaryFormatQty($capacityFallback) : '-',
        'keterangan' => $keteranganFallback,
        'keterangan_key' => $keteranganKeyFallback,
    ]];

    $machineName = trim((string)($targetMachine['machine_name'] ?? ''));
    if ($machineName === '') {
        $machineName = $machineId;
    }
    $machineTrendCardRows[] = [
        'machine_key' => $machineKey,
        'machine_id' => $machineId,
        'machine_name' => $machineName,
        'total_cp' => count($fallbackCpSet),
        'total_qty' => cpsSummaryFormatQty($fallbackQtyTotal),
        'period_label' => cpsSummaryFormatDateIndo($fallbackLatestDate) . ' s/d ' . cpsSummaryFormatDateIndo($fallbackLatestDate),
        'latest_date' => cpsSummaryFormatDateCell($fallbackLatestDate),
    ];
    $existingMachineKeys[$machineKey] = true;
}

usort($machineTrendCardRows, static function (array $a, array $b): int {
    return strcasecmp(($a['machine_name'] . ' ' . $a['machine_id']), ($b['machine_name'] . ' ' . $b['machine_id']));
});

$defaultTrendMachineKey = '';
if (!empty($machineTrendCardRows)) {
    $candidate = strtoupper(trim((string)$selectedMachineId));
    foreach ($machineTrendCardRows as $card) {
        if ($candidate !== '' && $candidate === (string)$card['machine_key']) {
            $defaultTrendMachineKey = (string)$card['machine_key'];
            break;
        }
    }
    if ($defaultTrendMachineKey === '') {
        $defaultTrendMachineKey = (string)$machineTrendCardRows[0]['machine_key'];
    }
}

$summaryGeneratedAt = (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('d/m/Y H:i:s');
$matrixStartIso = $fromDate;
$matrixEndIso = $toDate;
$machineTablePlannedUntilLabel = cpsSummaryFormatDateIndo($matrixEndIso);
$periodLabel = cpsSummaryFormatDateIndo($fromDate) . ' s/d ' . cpsSummaryFormatDateIndo($toDate);
$exportExcelUrl = '/gg_app/pages/planning/cp_planning/cp_planning_summary_export_excel.php'
    . '?plan_type=' . rawurlencode($selectedPlanType)
    . '&machine_id=' . rawurlencode($selectedMachineId)
    . '&date_from=' . rawurlencode($fromDate)
    . '&date_to=' . rawurlencode($toDate);
$exportPdfUrl = '/gg_app/pages/planning/cp_planning/cp_planning_summary_export_pdf.php'
    . '?plan_type=' . rawurlencode($selectedPlanType)
    . '&machine_id=' . rawurlencode($selectedMachineId)
    . '&date_from=' . rawurlencode($fromDate)
    . '&date_to=' . rawurlencode($toDate);

$summaryMatrixDates = [];
$startMatrixDt = DateTime::createFromFormat('Y-m-d', $matrixStartIso);
$endMatrixDt = DateTime::createFromFormat('Y-m-d', $matrixEndIso);
if ($startMatrixDt instanceof DateTime && $endMatrixDt instanceof DateTime) {
    $loopDate = clone $startMatrixDt;
    while ($loopDate <= $endMatrixDt) {
        $summaryMatrixDates[] = $loopDate->format('Y-m-d');
        $loopDate->modify('+1 day');
    }
}
if (empty($summaryMatrixDates)) {
    $summaryMatrixDates = [$todayIso];
}
$summaryMatrixDateSet = [];
foreach ($summaryMatrixDates as $dtIso) {
    $summaryMatrixDateSet[$dtIso] = true;
}

$summaryMatrixMachines = [];
foreach ($machineOptions as $m) {
    $machineId = trim((string)($m['machine_id'] ?? ''));
    if ($machineId === '') {
        continue;
    }
    $machineKey = strtoupper($machineId);
    if (isset($summaryMatrixMachines[$machineKey])) {
        continue;
    }
    $machineName = trim((string)($m['machine_name'] ?? ''));
    if ($machineName === '') {
        $machineName = $machineId;
    }
    $capacityVal = isset($capacityByMachine[$machineKey]) ? (float)$capacityByMachine[$machineKey]['max_capacity_day'] : null;
    $summaryMatrixMachines[$machineKey] = [
        'machine_key' => $machineKey,
        'machine_id' => $machineId,
        'machine_name' => $machineName,
        'capacity_value' => $capacityVal,
        'capacity_label' => ($capacityVal !== null && $capacityVal > 0) ? cpsSummaryFormatQty($capacityVal) : '-',
    ];
}
if ($selectedMachineId !== '') {
    $selectedKey = strtoupper($selectedMachineId);
    if (!isset($summaryMatrixMachines[$selectedKey])) {
        $selectedName = $selectedMachineInfo !== null
            ? trim((string)($selectedMachineInfo['machine_name'] ?? ''))
            : $selectedMachineId;
        if ($selectedName === '') {
            $selectedName = $selectedMachineId;
        }
        $cap = isset($capacityByMachine[$selectedKey]) ? (float)$capacityByMachine[$selectedKey]['max_capacity_day'] : null;
        $summaryMatrixMachines[$selectedKey] = [
            'machine_key' => $selectedKey,
            'machine_id' => $selectedMachineId,
            'machine_name' => $selectedName,
            'capacity_value' => $cap,
            'capacity_label' => ($cap !== null && $cap > 0) ? cpsSummaryFormatQty($cap) : '-',
        ];
    }
}
if (empty($summaryMatrixMachines) && !empty($machineTrendCardRows)) {
    foreach ($machineTrendCardRows as $card) {
        $machineId = trim((string)($card['machine_id'] ?? ''));
        if ($machineId === '') {
            continue;
        }
        $machineKey = strtoupper($machineId);
        if (isset($summaryMatrixMachines[$machineKey])) {
            continue;
        }
        $machineName = trim((string)($card['machine_name'] ?? ''));
        if ($machineName === '') {
            $machineName = $machineId;
        }
        $cap = isset($capacityByMachine[$machineKey]) ? (float)$capacityByMachine[$machineKey]['max_capacity_day'] : null;
        $summaryMatrixMachines[$machineKey] = [
            'machine_key' => $machineKey,
            'machine_id' => $machineId,
            'machine_name' => $machineName,
            'capacity_value' => $cap,
            'capacity_label' => ($cap !== null && $cap > 0) ? cpsSummaryFormatQty($cap) : '-',
        ];
    }
}

$summaryMatrixBase = [];
$realisasiEligibleRowIndex = [];
$realisasiGroups = [];
foreach ($allPlanningRows as $rowIdx => $row) {
    $machineKeyTmp = strtoupper(trim((string)($row['machine_id'] ?? '')));
    $planDateTmp = cpsSummaryToIsoDate($row['plan_date'] ?? '');
    if ($machineKeyTmp === '' || $planDateTmp === '') {
        continue;
    }
    $groupKey = $machineKeyTmp . '|' . $planDateTmp;
    if (!isset($realisasiGroups[$groupKey])) {
        $realisasiGroups[$groupKey] = [];
    }
    $realisasiGroups[$groupKey][] = [
        'row_index' => (int)$rowIdx,
        'plan_date' => $row['plan_date'] ?? '',
        'seq_no' => (isset($row['seq_no']) && $row['seq_no'] !== null) ? (int)$row['seq_no'] : null,
        'actual_start' => $row['actual_start'] ?? '',
        'actual_finish' => $row['actual_finish'] ?? '',
        'actual_topping_start' => $row['actual_topping_paddry_start'] ?? '',
        'actual_topping_finish' => $row['actual_topping_paddry_finish'] ?? '',
        'actual_topping_marker_at' => $row['actual_topping_marker_at'] ?? '',
    ];
}

foreach ($realisasiGroups as $groupRows) {
    usort($groupRows, static function (array $a, array $b): int {
        $aSeq = $a['seq_no'];
        $bSeq = $b['seq_no'];
        if ($aSeq !== null && $bSeq !== null && $aSeq !== $bSeq) {
            return $aSeq <=> $bSeq;
        }
        if ($aSeq !== null && $bSeq === null) {
            return -1;
        }
        if ($aSeq === null && $bSeq !== null) {
            return 1;
        }
        return ((int)$a['row_index']) <=> ((int)$b['row_index']);
    });

    // Aturan realisasi mengikuti pola operasional:
    // - hitung baris yang aktual start terisi
    // - setelah ada fase >05:30 lalu masuk fase <=05:30 (lewat tengah malam),
    //   maka saat muncul lagi >05:30 berikutnya proses hitung dihentikan.
    $hasSeenDayTime = false;
    $hasEnteredAfterMidnightWindow = false;
    foreach ($groupRows as $gr) {
        // Prioritas realisasi untuk paddry: pakai aktual topping paddry jika tersedia,
        // jika tidak ada maka fallback ke aktual celup.
        $toppingStartMinutes = cpsSummaryExtractTimeMinutes($gr['actual_topping_start'] ?? '');
        $toppingFinishMinutes = cpsSummaryExtractTimeMinutes($gr['actual_topping_finish'] ?? '');
        $hasToppingActual = ($toppingStartMinutes !== null || $toppingFinishMinutes !== null);
        if ($hasToppingActual) {
            $planDateIso = cpsSummaryToIsoDate($gr['plan_date'] ?? '');
            $planDateTs = $planDateIso !== '' ? strtotime($planDateIso . ' 00:00:00') : false;
            $toppingMarkerRaw = trim((string)($gr['actual_topping_marker_at'] ?? ''));
            $toppingMarkerTs = $toppingMarkerRaw !== '' ? strtotime($toppingMarkerRaw) : false;

            // Validasi aktual topping harus berada di window planning:
            // mulai tanggal planning 00:00 hingga H+1 pukul 05:30.
            if ($planDateTs === false || $toppingMarkerTs === false) {
                continue;
            }
            $windowEndTs = ((int)$planDateTs) + 86400 + (330 * 60);
            if ($toppingMarkerTs < (int)$planDateTs || $toppingMarkerTs > $windowEndTs) {
                continue;
            }
        }
        $startMinutes = $toppingStartMinutes ?? cpsSummaryExtractTimeMinutes($gr['actual_start'] ?? '');
        $finishMinutes = $toppingFinishMinutes ?? cpsSummaryExtractTimeMinutes($gr['actual_finish'] ?? '');
        if ($startMinutes === null && $finishMinutes === null) {
            continue;
        }
        $timeMarkerMinutes = $startMinutes !== null ? $startMinutes : $finishMinutes;

        if ($hasEnteredAfterMidnightWindow && $timeMarkerMinutes > 330) {
            break;
        }

        $realisasiEligibleRowIndex[(int)$gr['row_index']] = true;

        if ($timeMarkerMinutes > 330) {
            $hasSeenDayTime = true;
            continue;
        }

        if ($hasSeenDayTime) {
            $hasEnteredAfterMidnightWindow = true;
        }
    }
}

// Base summary (CP + Qty) harus selalu murni dari data planning sesuai filter,
// tanpa dipengaruhi aturan cutoff realisasi H+1 05:30.
foreach ($allPlanningRows as $rowIdx => $row) {
    $machineId = trim((string)($row['machine_id'] ?? ''));
    $machineKey = strtoupper($machineId);
    $planDateIso = cpsSummaryToIsoDate($row['plan_date'] ?? '');
    if ($machineKey === '' || $planDateIso === '' || !isset($summaryMatrixDateSet[$planDateIso])) {
        continue;
    }
    if (!isset($summaryMatrixBase[$machineKey])) {
        $summaryMatrixBase[$machineKey] = [];
    }
    if (!isset($summaryMatrixBase[$machineKey][$planDateIso])) {
        $summaryMatrixBase[$machineKey][$planDateIso] = [
            'cp_set' => [],
            'qty_total' => 0.0,
            'qty_realisasi' => 0.0,
            'routing_qty_total' => 0.0,
            'routing_qty_realisasi' => 0.0,
        ];
    }
    $cpNo = trim((string)($row['cp_no'] ?? ''));
    if ($cpNo !== '' && !cpsSummaryIsBreakTimeCpNo($cpNo)) {
        $summaryMatrixBase[$machineKey][$planDateIso]['cp_set'][strtoupper($cpNo)] = true;
    }
    $qtyValueRow = cpsSummaryToFloat($row['qty'] ?? 0);
    $routingQtyValueRow = cpsSummaryToFloat($row['routing_qty'] ?? 0);
    $summaryMatrixBase[$machineKey][$planDateIso]['qty_total'] += $qtyValueRow;
    $summaryMatrixBase[$machineKey][$planDateIso]['routing_qty_total'] += $routingQtyValueRow;
}

foreach ($allPlanningRows as $rowIdx => $row) {
    if (!isset($realisasiEligibleRowIndex[(int)$rowIdx])) {
        continue;
    }

    $machineId = trim((string)($row['machine_id'] ?? ''));
    $machineKey = strtoupper($machineId);
    $planDateIso = cpsSummaryToIsoDate($row['plan_date'] ?? '');
    if ($machineKey === '' || $planDateIso === '' || !isset($summaryMatrixDateSet[$planDateIso])) {
        continue;
    }
    if (!isset($summaryMatrixBase[$machineKey][$planDateIso])) {
        continue;
    }

    $qtyValueRow = cpsSummaryToFloat($row['qty'] ?? 0);
    $routingQtyValueRow = cpsSummaryToFloat($row['routing_qty'] ?? 0);
    $summaryMatrixBase[$machineKey][$planDateIso]['qty_realisasi'] += $qtyValueRow;
    $summaryMatrixBase[$machineKey][$planDateIso]['routing_qty_realisasi'] += $routingQtyValueRow;
}

$summaryMatrixDateTotals = [];
foreach ($summaryMatrixDates as $dtIso) {
    $summaryMatrixDateTotals[$dtIso] = [
        'cp' => 0,
        'qty' => 0.0,
        'qty_realisasi' => 0.0,
        'routing_qty' => 0.0,
        'routing_qty_realisasi' => 0.0,
    ];
}

$summaryMatrixRows = [];
foreach ($summaryMatrixMachines as $machineKey => $machineRow) {
    $capacityValue = $machineRow['capacity_value'];
    $rowTotalCp = 0;
    $rowTotalQty = 0.0;
    $rowTotalQtyRealisasi = 0.0;
    $rowTotalRoutingQty = 0.0;
    $rowTotalRoutingQtyRealisasi = 0.0;
    $rowTotalQtyPct = null;
    $rowTotalRoutingQtyPct = null;
    $dateCells = [];

    foreach ($summaryMatrixDates as $dtIso) {
        $cell = $summaryMatrixBase[$machineKey][$dtIso] ?? null;
        $cpCount = $cell ? count($cell['cp_set']) : 0;
        $qtyValue = $cell ? (float)($cell['qty_total'] ?? 0.0) : 0.0;
        $qtyRealisasiValue = $cell ? (float)($cell['qty_realisasi'] ?? 0.0) : 0.0;
        $routingQtyValue = $cell ? (float)($cell['routing_qty_total'] ?? 0.0) : 0.0;
        $routingQtyRealisasiValue = $cell ? (float)($cell['routing_qty_realisasi'] ?? 0.0) : 0.0;
        $qtyPctValue = ($qtyValue > 0) ? (($qtyRealisasiValue / $qtyValue) * 100.0) : null;
        $routingQtyPctValue = ($routingQtyValue > 0) ? (($routingQtyRealisasiValue / $routingQtyValue) * 100.0) : null;

        $rowTotalCp += $cpCount;
        $rowTotalQty += $qtyValue;
        $rowTotalQtyRealisasi += $qtyRealisasiValue;
        $rowTotalRoutingQty += $routingQtyValue;
        $rowTotalRoutingQtyRealisasi += $routingQtyRealisasiValue;
        $summaryMatrixDateTotals[$dtIso]['cp'] += $cpCount;
        $summaryMatrixDateTotals[$dtIso]['qty'] += $qtyValue;
        $summaryMatrixDateTotals[$dtIso]['qty_realisasi'] += $qtyRealisasiValue;
        $summaryMatrixDateTotals[$dtIso]['routing_qty'] += $routingQtyValue;
        $summaryMatrixDateTotals[$dtIso]['routing_qty_realisasi'] += $routingQtyRealisasiValue;

        $qtyClass = '';
        if ($qtyValue > 0 && $capacityValue !== null && $capacityValue > 0) {
            $qtyClass = ($qtyValue >= $capacityValue) ? 'cps-matrix-qty-over' : 'cps-matrix-qty-under';
        }

        $dateCells[] = [
            'cp' => $cpCount > 0 ? (string)$cpCount : '',
            'cp_raw' => $cpCount,
            'qty' => $qtyValue > 0 ? cpsSummaryFormatQty($qtyValue) : '',
            'qty_raw' => $qtyValue,
            'qty_realisasi' => $qtyRealisasiValue > 0 ? cpsSummaryFormatQty($qtyRealisasiValue, 2) : '',
            'qty_realisasi_raw' => $qtyRealisasiValue,
            'qty_pct' => ($qtyPctValue !== null ? cpsSummaryFormatPct($qtyPctValue) : ''),
            'qty_pct_raw' => $qtyPctValue,
            'routing_qty' => $routingQtyValue > 0 ? cpsSummaryFormatQty($routingQtyValue) : '',
            'routing_qty_raw' => $routingQtyValue,
            'routing_qty_realisasi' => $routingQtyRealisasiValue > 0 ? cpsSummaryFormatQty($routingQtyRealisasiValue, 2) : '',
            'routing_qty_realisasi_raw' => $routingQtyRealisasiValue,
            'routing_qty_pct' => ($routingQtyPctValue !== null ? cpsSummaryFormatPct($routingQtyPctValue) : ''),
            'routing_qty_pct_raw' => $routingQtyPctValue,
            'qty_class' => $qtyClass,
        ];
    }

    if ($rowTotalQty > 0) {
        $rowTotalQtyPct = ($rowTotalQtyRealisasi / $rowTotalQty) * 100.0;
    }
    if ($rowTotalRoutingQty > 0) {
        $rowTotalRoutingQtyPct = ($rowTotalRoutingQtyRealisasi / $rowTotalRoutingQty) * 100.0;
    }

    $summaryMatrixRows[] = [
        'machine_name' => $machineRow['machine_name'],
        'capacity' => $machineRow['capacity_label'],
        'dates' => $dateCells,
        'total_cp' => $rowTotalCp > 0 ? (string)$rowTotalCp : '',
        'total_cp_raw' => $rowTotalCp,
        'total_qty' => $rowTotalQty > 0 ? cpsSummaryFormatQty($rowTotalQty) : '',
        'total_qty_raw' => $rowTotalQty,
        'total_qty_realisasi' => $rowTotalQtyRealisasi > 0 ? cpsSummaryFormatQty($rowTotalQtyRealisasi, 2) : '',
        'total_qty_realisasi_raw' => $rowTotalQtyRealisasi,
        'total_qty_pct' => ($rowTotalQtyPct !== null ? cpsSummaryFormatPct($rowTotalQtyPct) : ''),
        'total_qty_pct_raw' => $rowTotalQtyPct,
        'total_routing_qty' => $rowTotalRoutingQty > 0 ? cpsSummaryFormatQty($rowTotalRoutingQty) : '',
        'total_routing_qty_raw' => $rowTotalRoutingQty,
        'total_routing_qty_realisasi' => $rowTotalRoutingQtyRealisasi > 0 ? cpsSummaryFormatQty($rowTotalRoutingQtyRealisasi, 2) : '',
        'total_routing_qty_realisasi_raw' => $rowTotalRoutingQtyRealisasi,
        'total_routing_qty_pct' => ($rowTotalRoutingQtyPct !== null ? cpsSummaryFormatPct($rowTotalRoutingQtyPct) : ''),
        'total_routing_qty_pct_raw' => $rowTotalRoutingQtyPct,
    ];
}

$summaryMatrixGrandCp = 0;
$summaryMatrixGrandQty = 0.0;
$summaryMatrixGrandQtyRealisasi = 0.0;
$summaryMatrixGrandRoutingQty = 0.0;
$summaryMatrixGrandRoutingQtyRealisasi = 0.0;
foreach ($summaryMatrixDateTotals as $tot) {
    $summaryMatrixGrandCp += (int)($tot['cp'] ?? 0);
    $summaryMatrixGrandQty += (float)($tot['qty'] ?? 0.0);
    $summaryMatrixGrandQtyRealisasi += (float)($tot['qty_realisasi'] ?? 0.0);
    $summaryMatrixGrandRoutingQty += (float)($tot['routing_qty'] ?? 0.0);
    $summaryMatrixGrandRoutingQtyRealisasi += (float)($tot['routing_qty_realisasi'] ?? 0.0);
}
$summaryMatrixGrandQtyPct = ($summaryMatrixGrandQty > 0)
    ? (($summaryMatrixGrandQtyRealisasi / $summaryMatrixGrandQty) * 100.0)
    : null;
$summaryMatrixGrandRoutingQtyPct = ($summaryMatrixGrandRoutingQty > 0)
    ? (($summaryMatrixGrandRoutingQtyRealisasi / $summaryMatrixGrandRoutingQty) * 100.0)
    : null;

if ($isSummaryExportBootstrap) {
    return;
}
?>

<style>
    :root {
        --cps-ink: #0f172a;
        --cps-muted: #64748b;
        --cps-surface: #f8fafc;
        --cps-line: #dbe4ef;
        --cps-primary: #0f3c8d;
        --cps-primary-soft: #eff6ff;
    }

    .cps-main-shell {
        border: 1px solid #d8e1ec;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
    }

    .cps-main-header {
        border-bottom: 0;
        padding: 12px 16px;
    }

    .cps-main-header-meta {
        font-size: 0.78rem;
        opacity: 0.92;
        font-weight: 500;
    }

    .cps-filter-card {
        border: 1px solid var(--cps-line);
        border-radius: 12px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }

    .cps-filter-card .card-body {
        background: linear-gradient(180deg, #fcfdff 0%, #f7faff 100%);
        border-radius: 12px;
    }

    .cps-filter-label {
        margin-bottom: 6px;
        font-size: 0.78rem;
        font-weight: 700;
        color: #334155;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .cps-filter-card .form-control-sm,
    .cps-filter-card .btn-sm {
        border-radius: 10px;
        height: calc(1.65em + .5rem + 2px);
    }

    .cps-filter-note {
        margin-top: 6px;
        font-size: 0.79rem;
        color: var(--cps-muted);
    }

    .cps-hero {
        border: 1px solid #dbe7f6;
        border-radius: 12px;
        background: linear-gradient(135deg, #f8fbff 0%, #eef4ff 100%);
        padding: 14px 14px 10px;
        margin-bottom: 14px;
    }

    .cps-hero-title {
        margin: 0;
        font-size: 0.98rem;
        font-weight: 800;
        color: var(--cps-primary);
    }

    .cps-hero-grid {
        margin-top: 10px;
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
    }

    .cps-hero-item {
        background: rgba(255, 255, 255, 0.74);
        border: 1px solid #d8e5f8;
        border-radius: 10px;
        padding: 8px 10px;
    }

    .cps-hero-item span {
        display: block;
        font-size: 0.72rem;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        margin-bottom: 2px;
        font-weight: 700;
    }

    .cps-hero-item strong {
        display: block;
        font-size: 0.84rem;
        color: #0f172a;
        font-weight: 700;
        line-height: 1.3;
    }

    .cps-kpi-card {
        border: 1px solid #dfe7f1;
        border-radius: 12px;
        background: #ffffff;
        padding: 12px 12px 10px;
        height: 100%;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
        transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
        text-align: center;
    }

    .cps-kpi-card:hover {
        transform: translateY(-2px);
        border-color: #bfd2ea;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
    }

    .cps-kpi-label {
        font-size: 0.78rem;
        color: var(--cps-muted);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        text-align: center;
    }

    .cps-kpi-value {
        margin-top: 4px;
        font-size: 1.2rem;
        line-height: 1.1;
        color: var(--cps-ink);
        font-weight: 800;
        text-align: center;
    }

    .cps-kpi-sub {
        margin-top: 4px;
        font-size: 0.78rem;
        color: var(--cps-muted);
        text-align: center;
    }

    .cps-kpi-card.kpi-global {
        border-color: #c6d7f8;
        background: linear-gradient(160deg, #f3f8ff 0%, #e8f1ff 100%);
    }

    .cps-kpi-card.kpi-cp {
        border-color: #cce8e6;
        background: linear-gradient(160deg, #f2fbfa 0%, #e6f7f5 100%);
    }

    .cps-kpi-card.kpi-qty {
        border-color: #ffe0b6;
        background: linear-gradient(160deg, #fff9ef 0%, #fff1dc 100%);
    }

    .cps-kpi-card.kpi-machine {
        border-color: #d8d6ff;
        background: linear-gradient(160deg, #f6f5ff 0%, #efedff 100%);
    }

    .cps-action-wrap {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 12px;
    }

    .cps-action-wrap .btn {
        border-radius: 999px;
        font-weight: 600;
        padding: .33rem .7rem;
    }

    .cps-source-wrap {
        border: 1px solid #e5eaf1;
        border-radius: 12px;
        background: #ffffff;
        padding: 10px 12px;
        margin-bottom: 12px;
    }

    .cps-source-label {
        font-size: 0.78rem;
        font-weight: 700;
        color: #334155;
        margin-bottom: 8px;
    }

    .cps-source-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .cps-source-chip {
        border-radius: 999px;
        border: 1px solid #d7e0ec;
        background: #f8fafc;
        font-weight: 600;
        color: #334155;
        line-height: 1.2;
    }

    .cps-source-chip:hover {
        border-color: #94b2dc;
        background: #eef4ff;
        color: #0f3c8d;
    }

    .cps-machine-card-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-height: 460px;
        overflow: auto;
        padding-right: 2px;
    }

    .cps-machine-card {
        width: 100%;
        border: 1px solid #cfd8e3;
        border-radius: 10px;
        background: #ffffff;
        text-align: left;
        padding: 11px 12px;
        color: #111827;
        cursor: pointer;
        transition: all .18s ease;
    }

    .cps-machine-card:hover {
        border-color: #86b5ff;
        box-shadow: 0 6px 14px rgba(15, 23, 42, 0.08);
    }

    .cps-machine-card.is-active {
        border-color: #1d4ed8;
        box-shadow: 0 6px 16px rgba(29, 78, 216, 0.2);
        background: #f3f8ff;
    }

    .cps-machine-card:focus {
        outline: 0;
        border-color: #1d4ed8;
        box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.2);
    }

    .cps-machine-title {
        font-size: 0.92rem;
        font-weight: 800;
        line-height: 1.2;
        color: #0f172a;
        margin-bottom: 6px;
    }

    .cps-machine-line {
        font-size: 0.78rem;
        color: #334155;
        line-height: 1.35;
        margin: 0 0 2px;
    }

    .cps-machine-line:last-child {
        margin-bottom: 0;
    }

    .cps-machine-card .btn-xs {
        font-size: 0.72rem;
        line-height: 1.2;
        border-radius: 999px;
        padding: 3px 9px;
        font-weight: 700;
    }

    .cps-chart-card {
        border: 1px solid #dbe4ef;
        border-radius: 12px;
    }

    .cps-chart-card .card-header {
        border-bottom: 1px solid #dbe4ef;
        background: #f8fafc;
        border-radius: 12px 12px 0 0;
        padding: 10px 12px;
    }

    .cps-chart-title {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 800;
        color: #111827;
    }

    .cps-chart-subtitle {
        margin-top: 2px;
        font-size: 0.78rem;
        color: #6b7280;
    }

    .cps-chart-card .table thead th {
        position: sticky;
        top: 0;
        z-index: 1;
    }

    .cps-daily-table {
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
    }

    .cps-daily-table thead th {
        font-size: 0.77rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        font-weight: 800;
        color: #334155;
        background: linear-gradient(180deg, #f8fbff 0%, #edf3fb 100%);
        border-top: 0;
        border-bottom: 1px solid #d4dfec;
        white-space: nowrap;
        padding: .5rem .45rem;
        text-align: center;
    }

    .cps-daily-table td {
        font-size: 0.82rem;
        color: #0f172a;
        padding: .45rem .45rem;
        vertical-align: middle;
        border-color: #dbe4ef;
        text-align: center;
    }

    .cps-daily-table tbody tr:nth-child(even) {
        background: #fbfdff;
    }

    .cps-daily-table tbody tr:hover {
        background: #f1f7ff;
    }

    .cps-daily-table tbody tr.is-over-capacity {
        background: #fff6f6;
    }

    .cps-note-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.74rem;
        font-weight: 700;
        line-height: 1.25;
        padding: .18rem .48rem;
        border-radius: 999px;
        border: 1px solid transparent;
        white-space: nowrap;
    }

    .cps-note-pill.normal {
        color: #0f7a40;
        background: #eafaf1;
        border-color: #b9ebcc;
    }

    .cps-note-pill.over {
        color: #b91c1c;
        background: #feecec;
        border-color: #f7c6c6;
    }

    .cps-note-pill.nocap {
        color: #475569;
        background: #edf2f8;
        border-color: #d4dde8;
    }

    .cps-matrix-card {
        border: 1px solid #d7e2ef;
        border-radius: 12px;
        overflow: hidden;
    }

    .cps-matrix-card .card-header {
        border-bottom: 1px solid #d7e2ef;
        background: #f8fafc;
        padding: 10px 12px;
    }

    .cps-matrix-head-wrap {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
    }

    .cps-matrix-actions {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }

    .cps-matrix-title {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 800;
        color: #111827;
    }

    .cps-matrix-subtitle {
        margin-top: 2px;
        font-size: 0.78rem;
        color: #64748b;
    }

    .cps-matrix-wrap {
        max-height: 520px;
        overflow: auto;
    }

    .cps-matrix-table {
        width: 100%;
        min-width: 980px;
        border-collapse: collapse;
        margin-bottom: 0;
    }

    .cps-matrix-table th,
    .cps-matrix-table td {
        border: 1px solid #c7d3e2;
        padding: .38rem .42rem;
        font-size: .8rem;
        vertical-align: middle;
        text-align: center;
        white-space: nowrap;
    }

    .cps-matrix-table thead th {
        background: #edf2f8;
        font-weight: 800;
        color: #1f2937;
    }

    .cps-matrix-table .is-subhead {
        background: #f4f7fb;
        font-size: .75rem;
        font-weight: 700;
    }

    .cps-matrix-table td.is-machine {
        text-align: left;
        font-weight: 700;
    }

    .cps-matrix-table tfoot td {
        background: #eef3f9;
        font-weight: 800;
    }

    .cps-matrix-note {
        padding: 6px 10px 8px;
        font-size: 0.82rem;
        font-weight: 700;
        color: #111827;
    }

    .cps-matrix-qty-under {
        color: #dc2626;
        font-weight: 700;
    }

    .cps-matrix-qty-over {
        color: #16a34a;
        font-weight: 700;
    }

    .cps-table-card {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
    }

    .cps-table-card .card-header {
        border-bottom: 1px solid #e5e7eb;
        background: #f8fafc;
        border-radius: 12px 12px 0 0;
        padding: 10px 12px;
    }

    .cps-table-title {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 700;
        color: #1f2937;
    }

    .cps-table-note {
        margin-top: 2px;
        font-size: 0.78rem;
        color: #6b7280;
    }

    .cps-table-card .table {
        margin-bottom: 0;
    }

    .cps-table-card .table th {
        font-size: 0.74rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: #4b5563;
        background: #f8fafc;
        border-top: 0;
        white-space: nowrap;
    }

    .cps-table-card .table td {
        font-size: 0.79rem;
        vertical-align: middle;
    }

    .cps-status {
        display: inline-flex;
        align-items: center;
        padding: 2px 7px;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .cps-status-normal {
        background: #e7f8ef;
        color: #0f7a40;
    }

    .cps-status-near {
        background: #fff8dd;
        color: #9a6700;
    }

    .cps-status-over {
        background: #fee2e2;
        color: #b91c1c;
    }

    .cps-status-nocap {
        background: #eef2f7;
        color: #5b6472;
    }

    .cps-modal .modal-content {
        border-radius: 12px;
        border: 0;
        box-shadow: 0 18px 40px rgba(15, 23, 42, 0.24);
    }

    .cps-modal .modal-header {
        padding: 10px 14px;
        border-bottom: 1px solid #e5e7eb;
        background: #f8fafc;
    }

    .cps-modal .modal-title {
        font-size: 0.97rem;
        font-weight: 700;
        color: #1f2937;
    }

    .cps-modal .modal-body {
        padding: 12px;
    }

    .cps-modal .table th {
        font-size: 0.73rem;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .cps-modal .table td {
        font-size: 0.79rem;
        vertical-align: middle;
    }

    .cps-empty {
        border: 1px dashed #d1d5db;
        border-radius: 12px;
        padding: 16px;
        text-align: center;
        color: #6b7280;
        font-size: 0.9rem;
    }

    @media (max-width: 1199.98px) {
        .cps-hero-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .cps-main-header {
            padding: 10px 12px;
        }

        .cps-main-header .card-title {
            font-size: 1rem;
        }

        .cps-hero-grid {
            grid-template-columns: repeat(1, minmax(0, 1fr));
        }
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 style="font-size: 1.85rem; margin: 0;">CP Planning Summary</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="/gg_app/pages/planning/index.php">Planning</a></li>
                        <li class="breadcrumb-item"><a href="/gg_app/pages/planning/cp_planning/cp_planning.php">CP Planning</a></li>
                        <li class="breadcrumb-item active">Summary</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card cps-main-shell">
                <div class="card-header cps-main-header bg-<?= $esc($themeColor) ?> text-white">
                    <div class="d-flex flex-wrap align-items-center justify-content-between">
                        <h3 class="card-title mb-0" style="font-weight:700;">CP Planning Summary</h3>
                        <div class="cps-main-header-meta">Generated: <?= $esc($summaryGeneratedAt) ?></div>
                    </div>
                </div>
                <div class="card-body pt-3">
                <div class="card cps-filter-card mb-3">
                    <div class="card-body py-3">
                        <form method="get" class="mb-0">
                            <div class="form-row">
                                <div class="form-group col-md-3 mb-2">
                                    <label class="cps-filter-label" for="planType">Tipe Planning</label>
                                    <select class="form-control form-control-sm" id="planType" name="plan_type">
                                        <option value="">Semua Tipe Planning</option>
                                        <?php foreach ($planTypeOptions as $type): ?>
                                            <option value="<?= $esc($type) ?>" <?= strcasecmp($selectedPlanType, $type) === 0 ? 'selected' : '' ?>>
                                                <?= $esc($type) ?>
                                            </option>
                                        <?php endforeach; ?>
                                        <?php if ($selectedPlanType !== '' && !in_array($selectedPlanType, $planTypeOptions, true)): ?>
                                            <option value="<?= $esc($selectedPlanType) ?>" selected><?= $esc($selectedPlanType) ?> (Input)</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="form-group col-md-3 mb-2">
                                    <label class="cps-filter-label" for="machineId">Machine</label>
                                    <select class="form-control form-control-sm" id="machineId" name="machine_id">
                                        <option value="">Semua Machine</option>
                                        <?php foreach ($machineOptions as $machine): ?>
                                            <?php $machineId = (string)$machine['machine_id']; ?>
                                            <option value="<?= $esc($machineId) ?>" <?= strcasecmp($selectedMachineId, $machineId) === 0 ? 'selected' : '' ?>>
                                                <?= $esc($machine['machine_name'] . ' (' . $machineId . ')') ?>
                                            </option>
                                        <?php endforeach; ?>
                                        <?php if ($selectedMachineId !== '' && $selectedMachineInfo === null): ?>
                                            <option value="<?= $esc($selectedMachineId) ?>" selected><?= $esc($selectedMachineId) ?> (Input)</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="form-group col-md-2 mb-2">
                                    <label class="cps-filter-label" for="dateFrom">Tanggal Awal</label>
                                    <input type="date" class="form-control form-control-sm" id="dateFrom" name="date_from" value="<?= $esc($fromDate) ?>">
                                </div>
                                <div class="form-group col-md-2 mb-2">
                                    <label class="cps-filter-label" for="dateTo">Tanggal Akhir</label>
                                    <input type="date" class="form-control form-control-sm" id="dateTo" name="date_to" value="<?= $esc($toDate) ?>">
                                </div>
                                <div class="form-group col-md-2 mb-2 d-flex align-items-end">
                                    <div class="w-100 d-flex flex-wrap" style="gap:8px;">
                                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                                            <i class="fas fa-filter"></i> Terapkan Filter
                                        </button>
                                        <a href="/gg_app/pages/planning/cp_planning/cp_planning_summary.php" class="btn btn-outline-secondary btn-sm flex-grow-1 text-center">
                                            Reset
                                        </a>
                                    </div>
                                </div>
                            </div>
                           
                   
                        </form>
                    </div>
                </div>

                <?php if (!empty($pageError)): ?>
                    <div class="alert alert-danger py-2">
                        <?= $esc($pageError) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($pageWarnings)): ?>
                    <div class="alert alert-warning py-2">
                        <ul class="mb-0 pl-3">
                            <?php foreach ($pageWarnings as $w): ?>
                                <li><?= $esc($w) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                

                

                <div class="row mb-3">
                    
                    <div class="col-md-6 col-xl-3 mb-2">
                        <div class="cps-kpi-card kpi-global">
                            <div class="cps-kpi-label">Planned s/d Global</div>
                            <div class="cps-kpi-value"><?= $esc($globalLatestDate !== '' ? $globalLatestDate : '-') ?></div>
                            <div class="cps-kpi-sub"><?= $esc($globalUntilDiffLabel) ?></div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-xl-3 mb-2">
                        <div class="cps-kpi-card kpi-cp">
                            <div class="cps-kpi-label">Total CP Distinct</div>
                            <div class="cps-kpi-value"><?= $esc(number_format((int)$summaryMatrixGrandCp, 0, '.', ',')) ?></div>
                            <div class="cps-kpi-sub">Total CP Semua Mesin</div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3 mb-2">
                        <div class="cps-kpi-card kpi-qty">
                            <div class="cps-kpi-label">Total Qty</div>
                            <div class="cps-kpi-value"><?= $esc(cpsSummaryFormatQty($totalQtyPlanned)) ?></div>
                            <div class="cps-kpi-sub">Rata-rata/hari: <?= $esc(cpsSummaryFormatQty($avgQtyPerDay)) ?></div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3 mb-2">
                        <div class="cps-kpi-card kpi-machine">
                            <div class="cps-kpi-label">Machine Aktif</div>
                            <div class="cps-kpi-value"><?= $esc(number_format($activeMachineCount, 0, '.', ',')) ?></div>
                            <div class="cps-kpi-sub">
                                Hari planning: <?= $esc(number_format($planningDayCount, 0, '.', ',')) ?> | Over capacity: <?= $esc(number_format($overCapacityCount, 0, '.', ',')) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($summaryMatrixRows) && !empty($summaryMatrixDates)): ?>
                    <div class="card cps-matrix-card mb-3">
                        <div class="card-header">
                            <div class="cps-matrix-head-wrap">
                                <div>
                                    <h5 class="cps-matrix-title">Planning Summary</h5>
                                    <div class="cps-matrix-subtitle">Planned s/d <?= $esc($machineTablePlannedUntilLabel) ?></div>
                                </div>
                                <div class="cps-matrix-actions">
                                    <a href="<?= $esc($exportExcelUrl) ?>" class="btn btn-success btn-sm">
                                        <i class="fas fa-file-excel"></i> Export Excel
                                    </a>
                                    <a href="<?= $esc($exportPdfUrl) ?>" class="btn btn-danger btn-sm">
                                        <i class="fas fa-file-pdf"></i> Export PDF
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="cps-matrix-wrap">
                                <table class="cps-matrix-table">
                                    <thead>
                                        <tr>
                                            <th rowspan="2">Mesin</th>
                                            <th rowspan="2">Kapasitas</th>
                                            <?php foreach ($summaryMatrixDates as $dtIso): ?>
                                                <th colspan="7"><?= $esc(cpsSummaryFormatDateCell($dtIso)) ?></th>
                                            <?php endforeach; ?>
                                            <th colspan="7">Total Qty</th>
                                        </tr>
                                        <tr>
                                            <?php foreach ($summaryMatrixDates as $dtIso): ?>
                                                <th class="is-subhead">CP</th>
                                                <th class="is-subhead">Planning Qty</th>
                                                <th class="is-subhead">Planning Qty Realisasi</th>
                                                <th class="is-subhead">Persentase</th>
                                                <th class="is-subhead">Routing Qty</th>
                                                <th class="is-subhead">Routing Qty Realisasi</th>
                                                <th class="is-subhead">Persentase</th>
                                            <?php endforeach; ?>
                                            <th class="is-subhead">CP</th>
                                            <th class="is-subhead">Planning Qty</th>
                                            <th class="is-subhead">Planning Qty Realisasi</th>
                                            <th class="is-subhead">Persentase</th>
                                            <th class="is-subhead">Routing Qty</th>
                                            <th class="is-subhead">Routing Qty Realisasi</th>
                                            <th class="is-subhead">Persentase</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($summaryMatrixRows as $row): ?>
                                            <tr>
                                                <td class="is-machine"><?= $esc($row['machine_name']) ?></td>
                                                <td><?= $esc($row['capacity']) ?></td>
                                                <?php foreach ($row['dates'] as $cell): ?>
                                                    <td><?= $esc($cell['cp']) ?></td>
                                                    <td class="<?= $esc($cell['qty_class']) ?>"><?= $esc($cell['qty']) ?></td>
                                                    <td><?= $esc($cell['qty_realisasi']) ?></td>
                                                    <td><?= $esc($cell['qty_pct']) ?></td>
                                                    <td><?= $esc($cell['routing_qty']) ?></td>
                                                    <td><?= $esc($cell['routing_qty_realisasi']) ?></td>
                                                    <td><?= $esc($cell['routing_qty_pct']) ?></td>
                                                <?php endforeach; ?>
                                                <td><?= $esc($row['total_cp']) ?></td>
                                                <td><?= $esc($row['total_qty']) ?></td>
                                                <td><?= $esc($row['total_qty_realisasi']) ?></td>
                                                <td><?= $esc($row['total_qty_pct']) ?></td>
                                                <td><?= $esc($row['total_routing_qty']) ?></td>
                                                <td><?= $esc($row['total_routing_qty_realisasi']) ?></td>
                                                <td><?= $esc($row['total_routing_qty_pct']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td class="is-machine" colspan="<?= $esc((string)(2 + (count($summaryMatrixDates) * 7))) ?>">Total</td>
                                            <td><?= $esc($summaryMatrixGrandCp > 0 ? (string)$summaryMatrixGrandCp : '') ?></td>
                                            <td><?= $esc($summaryMatrixGrandQty > 0 ? cpsSummaryFormatQty($summaryMatrixGrandQty) : '') ?></td>
                                            <td><?= $esc($summaryMatrixGrandQtyRealisasi > 0 ? cpsSummaryFormatQty($summaryMatrixGrandQtyRealisasi, 2) : '') ?></td>
                                            <td><?= $esc($summaryMatrixGrandQtyPct !== null ? cpsSummaryFormatPct($summaryMatrixGrandQtyPct) : '') ?></td>
                                            <td><?= $esc($summaryMatrixGrandRoutingQty > 0 ? cpsSummaryFormatQty($summaryMatrixGrandRoutingQty) : '') ?></td>
                                            <td><?= $esc($summaryMatrixGrandRoutingQtyRealisasi > 0 ? cpsSummaryFormatQty($summaryMatrixGrandRoutingQtyRealisasi, 2) : '') ?></td>
                                            <td><?= $esc($summaryMatrixGrandRoutingQtyPct !== null ? cpsSummaryFormatPct($summaryMatrixGrandRoutingQtyPct) : '') ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <div class="cps-matrix-note">
                                Qty realisasi dihitung sampai dengan pukul 05:30 pada hari berikutnya (H+1) setelah tanggal perencanaan.
                                <br>
                                Qty planning dikunci pukul 18.00.
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="cps-empty mb-3">Belum ada data matrix pada filter ini.</div>
                <?php endif; ?>

                </div>
            </div>
        </div>
    </section>
</div>

<div class="modal fade cps-modal" id="summaryDetailModal" tabindex="-1" role="dialog" aria-labelledby="summaryDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="summaryDetailModalLabel">Detail</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="summaryDetailHint" class="small text-muted mb-2"></div>
                <div class="table-responsive">
                    <table id="summaryDetailTable" class="table table-bordered table-striped table-sm w-100">
                        <thead id="summaryDetailHead"></thead>
                        <tbody id="summaryDetailBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">

<?php include '../../../includes/footer.php'; ?>

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>

<script>
$(function () {
    var machineMasterList = <?= json_encode($machineMasterList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var selectedMachineId = <?= json_encode($selectedMachineId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var machineTrendCardRows = <?= json_encode($machineTrendCardRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var machineDailySummaryRowsMap = <?= json_encode($machineDailySummaryRowsMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var defaultTrendMachineKey = <?= json_encode($defaultTrendMachineKey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var detailData = <?= json_encode($detailData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var detailTable = null;

    var detailConfigs = {
        daily: {
            title: 'Detail Planning per Tanggal',
            hint: 'Ringkasan per tanggal planning sesuai filter saat ini.',
            columns: [
                { key: 'plan_date', label: 'Tanggal' },
                { key: 'day_name', label: 'Hari' },
                { key: 'machine_count', label: 'Machine' },
                { key: 'cp_count', label: 'CP Distinct' },
                { key: 'row_count', label: 'Row' },
                { key: 'qty_total', label: 'Qty Total' },
                { key: 'source_count', label: 'Source' },
                { key: 'source_list', label: 'List Source' }
            ],
            order: [[0, 'desc']]
        },
        machine: {
            title: 'Detail Planning per Machine',
            hint: 'Utilisasi dihitung dari Qty terbaru machine terhadap max capacity/day.',
            columns: [
                { key: 'machine_id', label: 'Machine ID' },
                { key: 'machine_name', label: 'Machine Name' },
                { key: 'plan_type', label: 'Plan Type' },
                { key: 'latest_date', label: 'Latest Date' },
                { key: 'cp_count', label: 'CP Distinct' },
                { key: 'row_count', label: 'Row' },
                { key: 'qty_total', label: 'Qty Total' },
                { key: 'latest_qty', label: 'Latest Qty' },
                { key: 'max_capacity', label: 'Max Capacity' },
                { key: 'util_pct', label: 'Utilisasi' },
                { key: 'status', label: 'Status' },
                { key: 'over_days', label: 'Over Days' }
            ],
            order: [[3, 'desc']]
        },
        source: {
            title: 'Ringkasan per Source Tabel',
            hint: 'Sumber data dari tabel modul cp_planning.',
            columns: [
                { key: 'source', label: 'Source' },
                { key: 'row_count', label: 'Row' },
                { key: 'cp_count', label: 'CP Distinct' },
                { key: 'qty_total', label: 'Qty Total' },
                { key: 'latest_date', label: 'Latest Date' }
            ],
            order: [[4, 'desc']]
        },
        over_capacity: {
            title: 'Detail Hari Over Capacity',
            hint: 'Qty harian machine yang melebihi max capacity/day.',
            columns: [
                { key: 'plan_date', label: 'Tanggal' },
                { key: 'machine_id', label: 'Machine ID' },
                { key: 'machine_name', label: 'Machine Name' },
                { key: 'plan_type', label: 'Plan Type' },
                { key: 'qty_total', label: 'Qty Total' },
                { key: 'max_capacity', label: 'Max Capacity' },
                { key: 'selisih', label: 'Selisih' },
                { key: 'util_pct', label: 'Utilisasi' }
            ],
            order: [[0, 'desc']]
        },
        recent: {
            title: 'Detail CP Terbaru',
            hint: 'Daftar CP terbaru dari semua source sesuai filter.',
            columns: [
                { key: 'plan_date', label: 'Tanggal' },
                { key: 'source', label: 'Source' },
                { key: 'machine_id', label: 'Machine ID' },
                { key: 'machine_name', label: 'Machine Name' },
                { key: 'plan_type', label: 'Plan Type' },
                { key: 'cp_no', label: 'CP No' },
                { key: 'label', label: 'Label' },
                { key: 'qty', label: 'Qty' },
                { key: 'material_name', label: 'Material' },
                { key: 'next_routing', label: 'Next Routing' }
            ],
            order: [[0, 'desc']]
        }
    };

    function safeText(value) {
        return value == null ? '' : String(value);
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function normalizeText(value) {
        return value == null ? '' : String(value).trim().toLowerCase();
    }

    function getMachineLabel(item) {
        var machineId = safeText(item && item.machine_id ? item.machine_id : '');
        var machineName = safeText(item && item.machine_name ? item.machine_name : '');
        if (!machineName) {
            machineName = machineId;
        }
        return machineName;
    }

    function machineSortMeta(row) {
        var nameRaw = safeText(row && row.machine_name ? row.machine_name : '');
        var idRaw = safeText(row && row.machine_id ? row.machine_id : '');
        var normalized = nameRaw.replace(/\u00A0/g, ' ').trim().toUpperCase().replace(/\s+/g, ' ');
        var prefix = normalized.replace(/\d+/g, '').trim();
        var number = Number.MAX_SAFE_INTEGER;
        var mPadDry = normalized.match(/PAD\W*DRY\W*(\d+)/i);
        if (mPadDry) {
            prefix = 'PAD DRY';
            var parsedPadDry = parseInt(mPadDry[1], 10);
            number = isNaN(parsedPadDry) ? Number.MAX_SAFE_INTEGER : parsedPadDry;
        } else {
            var m = normalized.match(/^(.*?)(\d+)(?!.*\d)/);
            if (m) {
                prefix = safeText(m[1]).trim();
                var parsed = parseInt(m[2], 10);
                number = isNaN(parsed) ? Number.MAX_SAFE_INTEGER : parsed;
            }
        }
        return {
            prefix: prefix,
            number: number,
            normalized: normalized,
            id: idRaw.trim().toUpperCase()
        };
    }

    function buildMachineOptions(planTypeValue, keepMachineId) {
        var planTypeKey = normalizeText(planTypeValue);
        var keepMachineKey = normalizeText(keepMachineId);
        var rows = Array.isArray(machineMasterList) ? machineMasterList.slice() : [];

        if (planTypeKey) {
            rows = rows.filter(function (row) {
                return normalizeText(row && row.planning_type) === planTypeKey;
            });
        }

        rows.sort(function (a, b) {
            var am = machineSortMeta(a);
            var bm = machineSortMeta(b);
            if (am.prefix < bm.prefix) return -1;
            if (am.prefix > bm.prefix) return 1;
            if (am.number !== bm.number) return am.number - bm.number;
            if (am.normalized < bm.normalized) return -1;
            if (am.normalized > bm.normalized) return 1;
            if (am.id < bm.id) return -1;
            if (am.id > bm.id) return 1;
            return 0;
        });

        var html = '<option value="">Semua Machine</option>';
        var hasKeepMachine = false;

        rows.forEach(function (row) {
            var machineId = safeText(row && row.machine_id ? row.machine_id : '');
            if (!machineId) {
                return;
            }
            var selectedAttr = '';
            if (keepMachineKey && normalizeText(machineId) === keepMachineKey) {
                selectedAttr = ' selected';
                hasKeepMachine = true;
            }
            html += '<option value="' + escapeHtml(machineId) + '"' + selectedAttr + '>' + escapeHtml(getMachineLabel(row)) + '</option>';
        });

        if (keepMachineKey && !hasKeepMachine) {
            html += '<option value="' + escapeHtml(keepMachineId) + '" selected>' + escapeHtml(keepMachineId + ' (Input)') + '</option>';
        }

        $('#machineId').html(html);
    }

    buildMachineOptions($('#planType').val(), selectedMachineId);

    $('#planType').on('change', function () {
        buildMachineOptions($(this).val(), '');
    });

    function setActiveMachineCard(machineKey) {
        $('.js-machine-trend-card').removeClass('is-active');
        $('.js-machine-trend-card[data-machine-key="' + machineKey + '"]').addClass('is-active');
    }

    function renderMachineCpNoTable(machineKey) {
        var key = safeText(machineKey).toUpperCase();
        var rows = (machineDailySummaryRowsMap && machineDailySummaryRowsMap[key] && Array.isArray(machineDailySummaryRowsMap[key]))
            ? machineDailySummaryRowsMap[key].slice()
            : [];
        var card = null;

        if (Array.isArray(machineTrendCardRows)) {
            for (var i = 0; i < machineTrendCardRows.length; i++) {
                if (safeText(machineTrendCardRows[i].machine_key).toUpperCase() === key) {
                    card = machineTrendCardRows[i];
                    break;
                }
            }
        }

        setActiveMachineCard(key);
        if (card) {
            $('#machineTrendTitle').text('Ringkasan Planning ' + safeText(card.machine_name));
        } else {
            $('#machineTrendTitle').text('Ringkasan Planning Harian');
        }
        $('#machineTrendSubtitle').text('Jumlah hari planning: ' + rows.length);

        var bodyHtml = '';
        rows.forEach(function (row) {
            var noteKey = safeText(row && row.keterangan_key).toLowerCase();
            var noteClass = 'nocap';
            if (noteKey === 'normal' || noteKey === 'over' || noteKey === 'nocap') {
                noteClass = noteKey;
            }
            var trClass = (noteClass === 'over') ? ' class="is-over-capacity"' : '';
            bodyHtml += '<tr' + trClass + '>' +
                '<td>' + escapeHtml(safeText(row && row.plan_date)) + '</td>' +
                '<td class="text-center">' + escapeHtml(safeText(row && row.total_cp)) + '</td>' +
                '<td>' + escapeHtml(safeText(row && row.total_qty)) + '</td>' +
                '<td>' + escapeHtml(safeText(row && row.capacity)) + '</td>' +
                '<td><span class="cps-note-pill ' + noteClass + '">' + escapeHtml(safeText(row && row.keterangan)) + '</span></td>' +
            '</tr>';
        });

        $('#machineDailySummaryTableBody').html(bodyHtml);
        $('#machineTrendEmpty').toggle(rows.length === 0);
    }

    $('.js-machine-trend-card').on('click', function () {
        var machineKey = safeText($(this).attr('data-machine-key'));
        renderMachineCpNoTable(machineKey);
    });

    $('.js-machine-trend-card').on('keydown', function (event) {
        var key = safeText(event && event.key).toLowerCase();
        if (key === 'enter' || key === ' ') {
            event.preventDefault();
            $(this).trigger('click');
        }
    });

    if ($('.js-machine-trend-card').length) {
        var firstMachineKey = safeText($('.js-machine-trend-card').first().attr('data-machine-key'));
        renderMachineCpNoTable(defaultTrendMachineKey || firstMachineKey);
    }

    function destroyDetailTableIfAny() {
        if ($.fn.DataTable && $.fn.DataTable.isDataTable('#summaryDetailTable')) {
            $('#summaryDetailTable').DataTable().destroy();
        }
    }

    function applyDataTable(orderConfig) {
        if (!$.fn.DataTable) {
            return;
        }
        detailTable = $('#summaryDetailTable').DataTable({
            responsive: true,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            order: orderConfig || [[0, 'desc']],
            autoWidth: false,
            language: {
                zeroRecords: 'Data tidak ditemukan.',
                emptyTable: 'Tidak ada data.',
                search: 'Cari:',
                lengthMenu: 'Tampilkan _MENU_ data',
                info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
                infoEmpty: 'Menampilkan 0 data',
                paginate: {
                    previous: 'Prev',
                    next: 'Next'
                }
            }
        });
    }

    function renderDetail(type, opts) {
        var config = detailConfigs[type];
        if (!config) {
            return;
        }

        var rows = Array.isArray(detailData[type]) ? detailData[type].slice() : [];
        var hintSuffix = '';
        var options = opts || {};

        if (type === 'recent' && options.machineId) {
            var machineId = safeText(options.machineId).toUpperCase();
            rows = rows.filter(function (r) {
                return safeText(r.machine_id).toUpperCase() === machineId;
            });
            hintSuffix = ' | Filter machine: ' + safeText(options.machineId);
        }

        if (type === 'recent' && options.sourceKey) {
            var sourceKey = safeText(options.sourceKey).toLowerCase();
            rows = rows.filter(function (r) {
                return safeText(r.source_key).toLowerCase() === sourceKey;
            });
            hintSuffix = ' | Filter source: ' + safeText(options.sourceKey);
        }

        $('#summaryDetailModalLabel').text(config.title);
        $('#summaryDetailHint').text((config.hint || '') + hintSuffix);

        var headHtml = '<tr>';
        config.columns.forEach(function (col) {
            headHtml += '<th>' + escapeHtml(col.label) + '</th>';
        });
        headHtml += '</tr>';
        $('#summaryDetailHead').html(headHtml);

        var bodyHtml = '';
        if (!rows.length) {
            bodyHtml = '<tr><td colspan="' + config.columns.length + '" class="text-center text-muted">Tidak ada data untuk ditampilkan.</td></tr>';
        } else {
            rows.forEach(function (row) {
                bodyHtml += '<tr>';
                config.columns.forEach(function (col) {
                    bodyHtml += '<td>' + escapeHtml(safeText(row[col.key] || '-')) + '</td>';
                });
                bodyHtml += '</tr>';
            });
        }
        $('#summaryDetailBody').html(bodyHtml);

        destroyDetailTableIfAny();
        if (rows.length) {
            applyDataTable(config.order);
        }

        $('#summaryDetailModal').modal('show');
    }

    $('.js-open-detail').on('click', function () {
        var detailType = safeText($(this).attr('data-detail'));
        renderDetail(detailType, {});
    });

    $('.js-open-machine-recent').on('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        var machineId = safeText($(this).attr('data-machine'));
        renderDetail('recent', { machineId: machineId });
    });

    $('.js-open-source-recent').on('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        var sourceKey = safeText($(this).attr('data-source'));
        renderDetail('recent', { sourceKey: sourceKey });
    });

    $('#summaryDetailModal').on('hidden.bs.modal', function () {
        destroyDetailTableIfAny();
    });
});
</script>
