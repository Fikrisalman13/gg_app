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

$action = $_GET['action'] ?? $_POST['action'] ?? '';
if (!function_exists('cpPlannerJsonExit')) {
    function cpPlannerJsonExit(array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    }
}

if (!function_exists('cpPlannerSqlsrvErrorText')) {
    function cpPlannerSqlsrvErrorText(string $fallback = 'Terjadi kesalahan database.'): string
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

        if (empty($parts)) {
            return $fallback;
        }
        return implode(' | ', $parts);
    }
}

if (!function_exists('cpPlannerNormalizeMachineId')) {
    function cpPlannerNormalizeMachineId($value): string
    {
        return trim((string)($value ?? ''));
    }
}

if (!function_exists('cpPlannerFormatQtyDisplay')) {
    function cpPlannerFormatQtyDisplay($value, int $precision = 3, string $empty = ''): string
    {
        if ($value === null || $value === '') {
            return $empty;
        }

        $raw = trim((string)$value);
        if ($raw === '') {
            return $empty;
        }

        $normalized = str_replace(',', '', $raw);
        if (!is_numeric($normalized)) {
            return $raw;
        }

        $formatted = number_format((float)$normalized, $precision, '.', ',');
        $formatted = rtrim(rtrim($formatted, '0'), '.');
        if ($formatted === '-0') {
            return '0';
        }

        return $formatted;
    }
}

if (!function_exists('cpPlannerFetchLatestPaddryPlanningByCp')) {
    function cpPlannerFetchLatestPaddryPlanningByCp($conn, array $cpNos): array
    {
        $cleanCpNos = [];
        $seenCp = [];
        foreach ($cpNos as $cpNoRaw) {
            $cpNo = trim((string)$cpNoRaw);
            if ($cpNo === '') {
                continue;
            }
            $cpKey = strtoupper($cpNo);
            if (isset($seenCp[$cpKey])) {
                continue;
            }
            $seenCp[$cpKey] = true;
            $cleanCpNos[] = $cpKey;
        }
        if (empty($cleanCpNos)) {
            return [];
        }

        $tableExists = static function (string $tableName) use ($conn): bool {
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

        $getTableColumns = static function (string $tableName) use ($conn): array {
            $stmt = sqlsrv_query(
                $conn,
                "SELECT LOWER(COLUMN_NAME) AS col_name FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = ?",
                [$tableName]
            );
            if ($stmt === false) {
                return [];
            }
            $cols = [];
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $col = trim((string)($r['col_name'] ?? ''));
                if ($col !== '') {
                    $cols[$col] = true;
                }
            }
            sqlsrv_free_stmt($stmt);
            return $cols;
        };

        if (!$tableExists('cpp_paddry')) {
            return [];
        }

        $paddryCols = $getTableColumns('cpp_paddry');
        if (empty($paddryCols) || !isset($paddryCols['cp_no']) || !isset($paddryCols['machine_id'])) {
            return [];
        }

        $dateCol = 'period_date';
        if (isset($paddryCols['tgl'])) {
            $dateCol = 'tgl';
        } elseif (isset($paddryCols['plan_date'])) {
            $dateCol = 'plan_date';
        }

        $updatedCol = null;
        foreach (['last_update', 'modified_at', 'created_at', 'update_at'] as $candidate) {
            if (isset($paddryCols[$candidate])) {
                $updatedCol = $candidate;
                break;
            }
        }

        $orderParts = [
            "CASE WHEN p.[$dateCol] IS NULL THEN 1 ELSE 0 END ASC",
            "p.[$dateCol] DESC",
        ];
        if ($updatedCol !== null) {
            $orderParts[] = "CASE WHEN p.[$updatedCol] IS NULL THEN 1 ELSE 0 END ASC";
            $orderParts[] = "p.[$updatedCol] DESC";
        }
        if (isset($paddryCols['id'])) {
            $orderParts[] = "p.[id] DESC";
        } elseif (isset($paddryCols['seq_no'])) {
            $orderParts[] = "p.[seq_no] DESC";
        }

        $hasMachineMaster = false;
        if ($tableExists('ms_machine')) {
            $machineCols = $getTableColumns('ms_machine');
            $hasMachineMaster = isset($machineCols['machine_id']) && isset($machineCols['machine_name']);
        }

        $placeholders = implode(', ', array_fill(0, count($cleanCpNos), '?'));
        $sql = "
            WITH ranked AS (
                SELECT
                    LTRIM(RTRIM(CAST(p.[cp_no] AS NVARCHAR(200)))) AS cp_no,
                    LTRIM(RTRIM(CAST(p.[machine_id] AS NVARCHAR(200)))) AS machine_id,
                    p.[$dateCol] AS paddry_date,
                    ROW_NUMBER() OVER (
                        PARTITION BY UPPER(LTRIM(RTRIM(CAST(p.[cp_no] AS NVARCHAR(200)))))
                        ORDER BY " . implode(', ', $orderParts) . "
                    ) AS rn
                FROM dbo.[cpp_paddry] p
                WHERE UPPER(LTRIM(RTRIM(CAST(p.[cp_no] AS NVARCHAR(200))))) IN ($placeholders)
            )
            SELECT
                r.cp_no,
                r.machine_id,
                " . ($hasMachineMaster
                    ? "LTRIM(RTRIM(CAST(m.[machine_name] AS NVARCHAR(200))))"
                    : "LTRIM(RTRIM(CAST(r.machine_id AS NVARCHAR(200))))") . " AS machine_name,
                r.paddry_date
            FROM ranked r
            " . ($hasMachineMaster
                ? "LEFT JOIN dbo.[ms_machine] m
                   ON LTRIM(RTRIM(CAST(m.[machine_id] AS NVARCHAR(200)))) = r.machine_id"
                : "") . "
            WHERE r.rn = 1
        ";

        $stmt = sqlsrv_query($conn, $sql, $cleanCpNos);
        if ($stmt === false) {
            return [];
        }

        $map = [];
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $cpNo = trim((string)($r['cp_no'] ?? ''));
            if ($cpNo === '') {
                continue;
            }
            $cpKey = strtoupper($cpNo);
            $machineName = trim((string)($r['machine_name'] ?? ''));
            if ($machineName === '') {
                $machineName = trim((string)($r['machine_id'] ?? ''));
            }
            $map[$cpKey] = [
                'cp_no' => $cpNo,
                'machine_id' => trim((string)($r['machine_id'] ?? '')),
                'plan_paddry' => $machineName,
                'plan_date_raw' => $r['paddry_date'] ?? null,
            ];
        }
        sqlsrv_free_stmt($stmt);

        return $map;
    }
}

if (!function_exists('cpPlannerFetchPaddryActualCelupByCpRouting')) {
    /**
     * Ambil data aktual celup (start/end) dari source PostgreSQL berdasarkan pasangan CP No + Lokasi Paddry.
     *
     * @param PDO|null $conn3
     * @param array<int,array<string,mixed>> $rows
     * @param int $routingPartIndex Index routing dari kolom lokasi_paddry (0=pertama, 1=kedua, dst)
     * @return array<string,array{actual_start:string,actual_end:string}>
     */
    function cpPlannerFetchPaddryActualCelupByCpRouting($conn3, array $rows, int $routingPartIndex = 0): array
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
        $isCandidateBetterForRef = static function (?array $current, array $next, int $refDateTs): bool {
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

            $keys = ['time_count', 'event_ts', 'rtgseq', 'id'];
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
            $cpKey = $normalizeCp($row['no_cp'] ?? $row['cp_no'] ?? '');
            $routingSource = trim((string)($row['lokasi_paddry'] ?? ''));
            $referenceDate = trim((string)($row['reference_date'] ?? ($row['plan_date'] ?? ($row['period_date'] ?? ''))));
            $referenceDateTs = $toDateOnlyTs($referenceDate);
            $routingKey = $normalizeRouting($routingSource);
            $routingPartKeys = $extractRoutingParts($routingSource);
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

            $pairRows[] = [
                'cp_key' => $cpKey,
                'pair_key' => $cpKey . '|' . $routingKey,
                'selected_routing_key' => $selectedRoutingKey,
                'reference_date_ts' => $referenceDateTs,
            ];
            $cpSet[$cpKey] = $cpKey;
            $routingSet[$selectedRoutingKey] = $selectedRoutingKey;
        }
        if (empty($pairRows) || empty($cpSet) || empty($routingSet)) {
            return [];
        }

        // 1) Routing Name -> rtgmsid (support perbedaan spasi/case)
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

        // 2) CP No -> latest productionhdid
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
        if (empty($cpToProduction) || !$tableExists($conn3, 'pdproductionrtg')) {
            return [];
        }

        // 3) Kumpulkan kebutuhan pair (cp+routing -> productionhdid + kandidat rtgmsid)
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
            $pairKey = (string)($pair['pair_key'] ?? ($cpKey . '|' . $selectedRoutingKey));
            $pairNeeds[$pairKey] = [
                'productionhdid' => $productionId,
                'rtgmsids' => $ids,
                'reference_date_ts' => (int)($pair['reference_date_ts'] ?? 0),
            ];
            $productionIds[$productionId] = $productionId;
            foreach ($ids as $rid) {
                $rid = (int)$rid;
                if ($rid > 0) {
                    $rtgmsIds[$rid] = $rid;
                }
            }
        }
        if (empty($pairNeeds) || empty($productionIds) || empty($rtgmsIds)) {
            return [];
        }

        // 4) Ambil starttime/endtime dari pdproductionrtg
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
            $startTs = $toTimestamp($row['starttime'] ?? ($row['startdate'] ?? null));
            $endTs = $toTimestamp($row['endtime'] ?? ($row['enddate'] ?? null));
            $eventTs = max($startTs, $endTs);
            $timeCount = 0;
            if ($startHm !== '') {
                $timeCount++;
            }
            if ($endHm !== '') {
                $timeCount++;
            }

            $candidate = [
                'actual_start' => $startHm,
                'actual_end' => $endHm,
                'time_count' => $timeCount,
                'event_ts' => $eventTs,
                'rtgseq' => (int)($row['rtgseq'] ?? 0),
                'date_ts' => ($toDateOnlyTs($row['startdate'] ?? null) ?: $toDateOnlyTs($row['enddate'] ?? null)),
                'id' => 0,
            ];

            $mapKey = $productionId . '|' . $rtgmsid;
            if (!isset($rowsByProdAndRtg[$mapKey])) {
                $rowsByProdAndRtg[$mapKey] = [];
            }
            $rowsByProdAndRtg[$mapKey][] = $candidate;
        }
        if (empty($rowsByProdAndRtg)) {
            return [];
        }

        // 5) Hasil final per pair CP+Routing
        $result = [];
        foreach ($pairNeeds as $pairKey => $need) {
            $productionId = (int)$need['productionhdid'];
            $rtgmsList = $need['rtgmsids'];
            $referenceDateTs = (int)($need['reference_date_ts'] ?? 0);
            $best = null;
            foreach ($rtgmsList as $rid) {
                $mapKey = $productionId . '|' . (int)$rid;
                if (!isset($rowsByProdAndRtg[$mapKey]) || !is_array($rowsByProdAndRtg[$mapKey])) {
                    continue;
                }
                foreach ($rowsByProdAndRtg[$mapKey] as $candidate) {
                    if (!is_array($candidate)) {
                        continue;
                    }
                    if ($isCandidateBetterForRef($best, $candidate, $referenceDateTs)) {
                        $best = $candidate;
                    }
                }
            }

            if ($best !== null) {
                $result[$pairKey] = [
                    'actual_start' => trim((string)($best['actual_start'] ?? '')),
                    'actual_end' => trim((string)($best['actual_end'] ?? '')),
                ];
            }
        }

        return $result;
    }
}

if (!function_exists('cpPlannerFetchPaddryActualPrepByFixedRtgms')) {
    /**
     * Ambil jam aktual proses preparasi paddry dari rtgmsid fixed:
     * 876=Act.Tmbg LAB, 878=Act.Tmbg LA, 877=Act.Larut LAB, 879=Act.Larut LA, 880=Act.Larut Produksi
     *
     * @param PDO|null $conn3
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,array<string,string>>
     */
    function cpPlannerFetchPaddryActualPrepByFixedRtgms($conn3, array $rows): array
    {
        if (!($conn3 instanceof PDO) || empty($rows)) {
            return [];
        }

        $normalizeCp = static function ($value): string {
            return strtoupper(trim((string)$value));
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
        $isCandidateBetterForRef = static function (?array $current, array $next, int $refDateTs): bool {
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

            $keys = ['time_count', 'event_ts', 'rtgseq', 'id'];
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

        $cpSet = [];
        $cpRefDateTs = [];
        foreach ($rows as $row) {
            $cpKey = $normalizeCp($row['no_cp'] ?? $row['cp_no'] ?? '');
            if ($cpKey === '') {
                continue;
            }
            $refDate = trim((string)($row['reference_date'] ?? ($row['plan_date'] ?? ($row['period_date'] ?? ''))));
            $refTs = $toDateOnlyTs($refDate);
            $cpSet[$cpKey] = $cpKey;
            if (!isset($cpRefDateTs[$cpKey]) || $refTs > (int)$cpRefDateTs[$cpKey]) {
                $cpRefDateTs[$cpKey] = $refTs;
            }
        }
        if (empty($cpSet)) {
            return [];
        }

        $productionTable = '';
        if ($tableExists($conn3, 'pdproductionhd')) {
            $productionTable = 'pdproductionhd';
        } elseif ($tableExists($conn3, 'pdproductionshd')) {
            $productionTable = 'pdproductionshd';
        }
        if ($productionTable === '' || !$tableExists($conn3, 'pdproductionrtg')) {
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
        $productionToCp = [];
        while ($row = $stmtProduction->fetch(PDO::FETCH_ASSOC)) {
            $cpKey = $normalizeCp($row['cp_key'] ?? '');
            $productionId = (int)($row['productionhdid'] ?? 0);
            if ($cpKey === '' || $productionId <= 0) {
                continue;
            }
            $cpToProduction[$cpKey] = $productionId;
            $productionToCp[$productionId] = $cpKey;
        }
        if (empty($cpToProduction)) {
            return [];
        }

        $fixedRtgms = [876, 878, 877, 879, 880];
        [$prdHolders, $prdParams] = $buildNamedPlaceholders(array_values($cpToProduction), 'pid_');
        [$ridHolders, $ridParams] = $buildNamedPlaceholders($fixedRtgms, 'rid_');
        if (empty($prdHolders) || empty($ridHolders)) {
            return [];
        }

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

        $bestByCpAndRtgms = [];
        while ($row = $stmtRtg->fetch(PDO::FETCH_ASSOC)) {
            $productionId = (int)($row['productionhdid'] ?? 0);
            $rtgmsid = (int)($row['rtgmsid'] ?? 0);
            if ($productionId <= 0 || $rtgmsid <= 0 || !isset($productionToCp[$productionId])) {
                continue;
            }

            $cpKey = $productionToCp[$productionId];
            $startHm = $extractTimeHHmm($row['starttime'] ?? null);
            $endHm = $extractTimeHHmm($row['endtime'] ?? null);
            $startTs = $toTimestamp($row['starttime'] ?? ($row['startdate'] ?? null));
            $endTs = $toTimestamp($row['endtime'] ?? ($row['enddate'] ?? null));
            $eventTs = max($startTs, $endTs);
            $timeCount = 0;
            if ($startHm !== '') {
                $timeCount++;
            }
            if ($endHm !== '') {
                $timeCount++;
            }

            $candidate = [
                'actual_start' => $startHm,
                'actual_end' => $endHm,
                'time_count' => $timeCount,
                'event_ts' => $eventTs,
                'rtgseq' => (int)($row['rtgseq'] ?? 0),
                'date_ts' => ($toDateOnlyTs($row['startdate'] ?? null) ?: $toDateOnlyTs($row['enddate'] ?? null)),
                'id' => 0,
            ];

            if (!isset($bestByCpAndRtgms[$cpKey])) {
                $bestByCpAndRtgms[$cpKey] = [];
            }
            $current = $bestByCpAndRtgms[$cpKey][$rtgmsid] ?? null;
            $refTs = (int)($cpRefDateTs[$cpKey] ?? 0);
            if ($isCandidateBetterForRef($current, $candidate, $refTs)) {
                $bestByCpAndRtgms[$cpKey][$rtgmsid] = $candidate;
            }
        }
        if (empty($bestByCpAndRtgms)) {
            return [];
        }

        $fieldMap = [
            876 => ['actual_timbang_lab_start', 'actual_timbang_lab_finish'],
            878 => ['actual_timbang_la_start', 'actual_timbang_la_finish'],
            877 => ['actual_larut_lab_start', 'actual_larut_lab_finish'],
            879 => ['actual_larut_la_start', 'actual_larut_la_finish'],
            880 => ['actual_larut_prdks_start', 'actual_larut_prdks_finish'],
        ];

        $result = [];
        foreach ($bestByCpAndRtgms as $cpKey => $rtgRows) {
            $rowOut = [];
            foreach ($fieldMap as $rtgmsid => $fields) {
                $startField = $fields[0];
                $finishField = $fields[1];
                $candidate = $rtgRows[$rtgmsid] ?? null;
                $rowOut[$startField] = is_array($candidate) ? trim((string)($candidate['actual_start'] ?? '')) : '';
                $rowOut[$finishField] = is_array($candidate) ? trim((string)($candidate['actual_end'] ?? '')) : '';
            }
            $result[$cpKey] = $rowOut;
        }

        return $result;
    }
}

if ($action === 'load_bakar_bulu') {
    $machineId = cpPlannerNormalizeMachineId($_POST['machine_id'] ?? '');
    $periodDate = trim((string)($_POST['period_date'] ?? ''));

    if ($machineId === '') {
        cpPlannerJsonExit([
            'success' => false,
            'message' => 'Machine tidak valid.',
            'data' => [],
        ]);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodDate)) {
        cpPlannerJsonExit([
            'success' => false,
            'message' => 'Periode tidak valid.',
            'data' => [],
        ]);
    }

    $toDateDisplay = static function ($value): string {
        if ($value === null || $value === '') {
            return '';
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('d/m/Y');
        }
        $raw = trim((string)$value);
        if ($raw === '') {
            return '';
        }
        $formats = ['Y-m-d', 'Y-m-d H:i:s', 'd/m/Y', 'd-m-Y'];
        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $raw);
            if ($dt instanceof DateTime) {
                return $dt->format('d/m/Y');
            }
        }
        $ts = strtotime($raw);
        if ($ts !== false) {
            return date('d/m/Y', $ts);
        }
        return $raw;
    };

    $toTimeDisplay = static function ($value): string {
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
        if (preg_match('/^\d{2}:\d{2}/', $raw)) {
            return substr($raw, 0, 5);
        }
        $ts = strtotime($raw);
        if ($ts !== false) {
            return date('H:i', $ts);
        }
        return $raw;
    };

    $toDateTimeText = static function ($value): string {
        if ($value === null || $value === '') {
            return '-';
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('d/m/Y H:i');
        }
        $raw = trim((string)$value);
        if ($raw === '') {
            return '-';
        }
        $ts = strtotime($raw);
        if ($ts !== false) {
            return date('d/m/Y H:i', $ts);
        }
        return $raw;
    };

    $sql = "
        SELECT
            id,
            seq_no,
            cp_no,
            tgl_cp,
            label_jual,
            cust_color,
            kode_lab,
            routing_name,
            qty,
            material_name,
            plan_machine,
            plan_date,
            plan_start,
            plan_end,
            plan_description,
            actual_date,
            actual_start,
            actual_end,
            actual_shift,
            actual_realisasi,
            posisi_hari_ini,
            next_routing,
            last_update,
            updated_by
        FROM dbo.cpp_bakar_bulu
        WHERE machine_id = ? AND period_date = ?
        ORDER BY seq_no ASC, id ASC
    ";

    $stmt = sqlsrv_query($conn, $sql, [$machineId, $periodDate]);
    if ($stmt === false) {
        cpPlannerJsonExit([
            'success' => false,
            'message' => cpPlannerSqlsrvErrorText('Gagal memuat data bakar bulu.'),
            'data' => [],
        ]);
    }

    $rows = [];
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $qty = $r['qty'];
        $actualRealisasi = $r['actual_realisasi'];
        $rows[] = [
            '_row_id' => 'db_' . (string)($r['id'] ?? ''),
            '_save_state' => 'saved',
            'no_cp' => trim((string)($r['cp_no'] ?? '')),
            'tgl_cp' => $toDateDisplay($r['tgl_cp'] ?? null),
            'label' => trim((string)($r['label_jual'] ?? '')),
            'cust_color' => trim((string)($r['cust_color'] ?? '')),
            'kode_lab' => trim((string)($r['kode_lab'] ?? '')),
            'routing_name' => trim((string)($r['routing_name'] ?? '')),
            'qty' => cpPlannerFormatQtyDisplay($qty, 2),
            'material' => trim((string)($r['material_name'] ?? '')),
            'plan_machine' => trim((string)($r['plan_machine'] ?? '')),
            'plan_date' => $toDateDisplay($r['plan_date'] ?? null),
            'plan_start' => $toTimeDisplay($r['plan_start'] ?? null),
            'plan_end' => $toTimeDisplay($r['plan_end'] ?? null),
            'plan_description' => trim((string)($r['plan_description'] ?? '')),
            'actual_date' => $toDateDisplay($r['actual_date'] ?? null),
            'actual_start' => $toTimeDisplay($r['actual_start'] ?? null),
            'actual_end' => $toTimeDisplay($r['actual_end'] ?? null),
            'actual_shift' => trim((string)($r['actual_shift'] ?? '')),
            'actual_realisasi' => $actualRealisasi !== null && $actualRealisasi !== '' ? number_format((float)$actualRealisasi, 4, '.', '') : '',
            'posisi_hari_ini' => trim((string)($r['posisi_hari_ini'] ?? '')),
            'next_routing' => trim((string)($r['next_routing'] ?? '')),
            'last_update' => $toDateTimeText($r['last_update'] ?? null),
            'updated_by' => trim((string)($r['updated_by'] ?? '')),
        ];
    }
    sqlsrv_free_stmt($stmt);

    cpPlannerJsonExit([
        'success' => true,
        'message' => 'OK',
        'data' => $rows,
    ]);
}

if ($action === 'load_paddry') {
    $machineId = cpPlannerNormalizeMachineId($_POST['machine_id'] ?? '');
    $periodDate = trim((string)($_POST['period_date'] ?? ''));

    if ($machineId === '') {
        cpPlannerJsonExit([
            'success' => false,
            'message' => 'Machine tidak valid.',
            'data' => [],
        ]);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodDate)) {
        cpPlannerJsonExit([
            'success' => false,
            'message' => 'Periode tidak valid.',
            'data' => [],
        ]);
    }

    $toDateDisplay = static function ($value): string {
        if ($value === null || $value === '') {
            return '';
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('d/m/Y');
        }
        $raw = trim((string)$value);
        if ($raw === '') {
            return '';
        }
        $formats = ['Y-m-d', 'Y-m-d H:i:s', 'd/m/Y', 'd-m-Y'];
        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $raw);
            if ($dt instanceof DateTime) {
                return $dt->format('d/m/Y');
            }
        }
        $ts = strtotime($raw);
        if ($ts !== false) {
            return date('d/m/Y', $ts);
        }
        return $raw;
    };

    $toDateInput = static function ($value): string {
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
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }
        $formats = ['d/m/Y', 'd-m-Y', 'Y-m-d H:i:s', 'd/m/Y H:i:s'];
        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $raw);
            if ($dt instanceof DateTime) {
                return $dt->format('Y-m-d');
            }
        }
        $ts = strtotime($raw);
        return $ts === false ? '' : date('Y-m-d', $ts);
    };

    $toTimeDisplay = static function ($value): string {
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
        if (preg_match('/^\d{2}:\d{2}/', $raw)) {
            return substr($raw, 0, 5);
        }
        $ts = strtotime($raw);
        if ($ts !== false) {
            return date('H:i', $ts);
        }
        return $raw;
    };

    $toDateTimeText = static function ($value): string {
        if ($value === null || $value === '') {
            return '-';
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('d/m/Y H:i');
        }
        $raw = trim((string)$value);
        if ($raw === '') {
            return '-';
        }
        $ts = strtotime($raw);
        if ($ts !== false) {
            return date('d/m/Y H:i', $ts);
        }
        return $raw;
    };

    $rowValue = static function (array $rowAssoc, array $keys, $default = null) {
        foreach ($keys as $k) {
            $key = strtolower((string)$k);
            if (array_key_exists($key, $rowAssoc)) {
                return $rowAssoc[$key];
            }
        }
        return $default;
    };

    $sql = "
        SELECT *
        FROM dbo.cpp_paddry
        WHERE machine_id = ? AND period_date = ?
        ORDER BY seq_no ASC, id ASC
    ";
    $stmt = sqlsrv_query($conn, $sql, [$machineId, $periodDate]);
    if ($stmt === false) {
        cpPlannerJsonExit([
            'success' => false,
            'message' => cpPlannerSqlsrvErrorText('Gagal memuat data paddry.'),
            'data' => [],
        ]);
    }

    $rows = [];
    $actualCelupLookupRows = [];
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rowAssoc = [];
        foreach ($r as $k => $v) {
            $rowAssoc[strtolower((string)$k)] = $v;
        }

        $qty = $rowValue($rowAssoc, ['qty']);
        $cpNoRaw = trim((string)$rowValue($rowAssoc, ['cp_no'], ''));
        $routingNameRaw = trim((string)$rowValue($rowAssoc, ['routing_name'], ''));
        // Khusus aktual celup paddry: routing referensi wajib dari lokasi_paddry di cpp_paddry.
        $lokasiPaddryRaw = trim((string)$rowValue($rowAssoc, ['lokasi_paddry'], ''));
        $posisiHariIniRaw = trim((string)$rowValue($rowAssoc, ['posisi_hari_ini'], ''));
        if ($posisiHariIniRaw === '' || $posisiHariIniRaw === '-') {
            $routingNameCompact = strtoupper(preg_replace('/\s+/', '', $routingNameRaw) ?? $routingNameRaw);
            if (strpos($routingNameCompact, 'VERPACK') !== false) {
                $posisiHariIniRaw = 'VERPACKING';
            }
        }
        $planDateRaw = $rowValue($rowAssoc, ['tgl', 'plan_date'], null);
        // Jika kolom tgl/plan_date tidak ada di tabel (atau kosong), tampilkan default dari period_date agar kolom Tgl tidak hilang setelah reload.
        if ($planDateRaw === null || $planDateRaw === '') {
            $planDateRaw = $periodDate;
        }
        $rows[] = [
            '_row_id' => 'dbp_' . (string)($rowValue($rowAssoc, ['id'], '')),
            '_save_state' => 'saved',
            'is_break_time' => trim((string)$rowValue($rowAssoc, ['is_break_time'], '')),
            'break_time_name' => trim((string)$rowValue($rowAssoc, ['break_time_name'], '')),
            'no_cp' => $cpNoRaw,
            'tgl_cp' => $toDateDisplay($rowValue($rowAssoc, ['tgl_cp'], null)),
            'label' => trim((string)$rowValue($rowAssoc, ['label', 'label_jual'], '')),
            'cust_color' => trim((string)$rowValue($rowAssoc, ['cust_color'], '')),
            'kode_lab' => trim((string)$rowValue($rowAssoc, ['kode_lab'], '')),
            'routing_name' => $routingNameRaw,
            'lokasi_paddry' => $lokasiPaddryRaw,
            'material' => trim((string)$rowValue($rowAssoc, ['material_name', 'material'], '')),
            'qty' => cpPlannerFormatQtyDisplay($qty, 3),
            'routing_qty' => cpPlannerFormatQtyDisplay($rowValue($rowAssoc, ['aktual_qty'], null), 3, ''),
            'posisi_hari_ini' => $posisiHariIniRaw,
            'speed' => trim((string)$rowValue($rowAssoc, ['speed'], '')),
            'temperatur' => trim((string)$rowValue($rowAssoc, ['temperatur', 'temperature', 'temp'], '')),
            'resp_lipat' => trim((string)$rowValue($rowAssoc, ['resep_lipat', 'resp_lipat'], '')),
            'status_resp' => trim((string)$rowValue($rowAssoc, ['status_resep', 'status_resp'], '')),
            'vlot_resp' => trim((string)$rowValue($rowAssoc, ['vlot_resep', 'vlot_resp'], '')),
            'bon_resp' => trim((string)$rowValue($rowAssoc, ['bon_resep', 'bon_resp'], '')),
            'plan_description' => trim((string)$rowValue($rowAssoc, ['ket', 'plan_description'], '')),
            'plan_date' => $toDateDisplay($planDateRaw),
            'est_tmbng_plrtm_lalab' => $toTimeDisplay($rowValue($rowAssoc, ['est_tmbng_plrtn_lalab', 'est_tmbng_plrtm_lalab'], null)),
            'est_plrtm_prdks' => $toTimeDisplay($rowValue($rowAssoc, ['est_plrtn_prdks', 'est_plrtm_prdks'], null)),
            'plan_start' => $toTimeDisplay($rowValue($rowAssoc, ['rencana_start', 'plan_start'], null)),
            'plan_end' => $toTimeDisplay($rowValue($rowAssoc, ['rencana_finish', 'plan_finish', 'plan_end'], null)),
            // Aktual celup di-load ulang dari ERP (pdproductionrtg) agar sinkron routing aktual.
            'actual_start' => '',
            'actual_end' => '',
            'actual_timbang_lab_start' => $toTimeDisplay($rowValue($rowAssoc, ['aktual_timbang_lab_start', 'actual_timbang_lab_start', 'aktual_timbang_lalab_start', 'actual_timbang_lalab_start'], null)),
            'actual_timbang_lab_finish' => $toTimeDisplay($rowValue($rowAssoc, ['aktual_timbang_lab_finish', 'actual_timbang_lab_finish', 'aktual_timbang_lalab_finish', 'actual_timbang_lalab_finish'], null)),
            'actual_timbang_la_start' => $toTimeDisplay($rowValue($rowAssoc, ['aktual_timbang_la_start', 'actual_timbang_la_start'], null)),
            'actual_timbang_la_finish' => $toTimeDisplay($rowValue($rowAssoc, ['aktual_timbang_la_finish', 'actual_timbang_la_finish'], null)),
            'actual_larut_lab_start' => $toTimeDisplay($rowValue($rowAssoc, ['aktual_larut_lab_start', 'actual_larut_lab_start', 'aktual_larut_lalab_start', 'actual_larut_lalab_start'], null)),
            'actual_larut_lab_finish' => $toTimeDisplay($rowValue($rowAssoc, ['aktual_larut_lab_finish', 'actual_larut_lab_finish', 'aktual_larut_lalab_finish', 'actual_larut_lalab_finish'], null)),
            'actual_larut_la_start' => $toTimeDisplay($rowValue($rowAssoc, ['aktual_larut_la_start', 'actual_larut_la_start'], null)),
            'actual_larut_la_finish' => $toTimeDisplay($rowValue($rowAssoc, ['aktual_larut_la_finish', 'actual_larut_la_finish'], null)),
            'actual_timbang_lalab_start' => $toTimeDisplay($rowValue($rowAssoc, ['aktual_timbang_lalab_start', 'actual_timbang_lalab_start'], null)),
            'actual_timbang_lalab_finish' => $toTimeDisplay($rowValue($rowAssoc, ['aktual_timbang_lalab_finish', 'actual_timbang_lalab_finish'], null)),
            'actual_larut_lalab_start' => $toTimeDisplay($rowValue($rowAssoc, ['aktual_larut_lalab_start', 'actual_larut_lalab_start'], null)),
            'actual_larut_lalab_finish' => $toTimeDisplay($rowValue($rowAssoc, ['aktual_larut_lalab_finish', 'actual_larut_lalab_finish'], null)),
            'actual_larut_prdks_start' => $toTimeDisplay($rowValue($rowAssoc, ['aktual_larut_prdks_start', 'actual_larut_prdks_start', 'aktual_larut_produksi_start', 'actual_larut_produksi_start'], null)),
            'actual_larut_prdks_finish' => $toTimeDisplay($rowValue($rowAssoc, ['aktual_larut_prdks_finish', 'actual_larut_prdks_finish', 'aktual_larut_produksi_finish', 'actual_larut_produksi_finish'], null)),
            // Aktual topping paddry disinkronkan ulang dari ERP (routing ke-2 di lokasi_paddry).
            'actual_topping_paddry_start' => '',
            'actual_topping_paddry_finish' => '',
            'actual_realisasi' => cpPlannerFormatQtyDisplay($rowValue($rowAssoc, ['actual_realisasi', 'aktual_qty'], null), 3, ''),
            'actual_vlot' => trim((string)$rowValue($rowAssoc, ['vlot_aktual', 'actual_vlot'], '')),
            'sisa_saturator' => trim((string)$rowValue($rowAssoc, ['sisa_larut', 'sisa_saturator'], '')),
            'sample' => trim((string)$rowValue($rowAssoc, ['sample_kain', 'sample'], '')),
            'rko' => $toDateInput($rowValue($rowAssoc, ['rko'], null)),
            'next_routing' => trim((string)$rowValue($rowAssoc, ['next_routing'], '')),
            'late_move_source_flag' => trim((string)$rowValue($rowAssoc, ['is_late_move_source'], '0')),
            'late_move_at' => $toDateTimeText($rowValue($rowAssoc, ['late_move_at'], null)),
            'late_move_target_period_date' => $toDateInput($rowValue($rowAssoc, ['late_move_target_period_date'], null)),
            'late_move_target_machine_id' => trim((string)$rowValue($rowAssoc, ['late_move_target_machine_id'], '')),
            'last_update' => $toDateTimeText($rowValue($rowAssoc, ['last_update', 'modified_at', 'created_at'], null)),
            'updated_by' => trim((string)$rowValue($rowAssoc, ['update_by', 'updated_by', 'modified_by', 'created_by'], '')),
        ];
        $actualCelupLookupRows[] = [
            'no_cp' => $cpNoRaw,
            'routing_name' => $routingNameRaw,
            'lokasi_paddry' => $lokasiPaddryRaw,
            'reference_date' => $toDateInput($planDateRaw),
        ];
    }
    sqlsrv_free_stmt($stmt);

    if (!empty($rows) && !empty($actualCelupLookupRows)) {
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
        $normalizeCpKey = static function ($value): string {
            return strtoupper(trim((string)$value));
        };

        // Routing pertama = aktual celup, routing kedua = aktual topping paddry.
        $actualCelupMap = cpPlannerFetchPaddryActualCelupByCpRouting($conn3, $actualCelupLookupRows, 0);
        $actualToppingMap = cpPlannerFetchPaddryActualCelupByCpRouting($conn3, $actualCelupLookupRows, 1);
        $actualPrepMap = cpPlannerFetchPaddryActualPrepByFixedRtgms($conn3, $actualCelupLookupRows);

        foreach ($rows as $idx => &$rowItem) {
            $lookup = $actualCelupLookupRows[$idx] ?? null;
            if (!is_array($lookup)) {
                continue;
            }

            $pairKey = $normalizePairKey($lookup['no_cp'] ?? '', $lookup['lokasi_paddry'] ?? '');
            if ($pairKey !== '' && isset($actualCelupMap[$pairKey])) {
                $actualData = $actualCelupMap[$pairKey];
                $rowItem['actual_start'] = trim((string)($actualData['actual_start'] ?? ''));
                $rowItem['actual_end'] = trim((string)($actualData['actual_end'] ?? ''));
            }
            if ($pairKey !== '' && isset($actualToppingMap[$pairKey])) {
                $actualTopData = $actualToppingMap[$pairKey];
                $rowItem['actual_topping_paddry_start'] = trim((string)($actualTopData['actual_start'] ?? ''));
                $rowItem['actual_topping_paddry_finish'] = trim((string)($actualTopData['actual_end'] ?? ''));
            }

            $cpKey = $normalizeCpKey($lookup['no_cp'] ?? '');
            if ($cpKey !== '' && isset($actualPrepMap[$cpKey]) && is_array($actualPrepMap[$cpKey])) {
                $prepData = $actualPrepMap[$cpKey];
                $kategoriPenimbangan = strtoupper(trim((string)($rowItem['plan_description'] ?? '')));
                $isKategoriEmpty = ($kategoriPenimbangan === '');
                $isKategoriLabOnly = ($kategoriPenimbangan === 'LAB');
                $isKategoriLaOnly = ($kategoriPenimbangan === 'LA');
                $isKategoriMix = ($kategoriPenimbangan === 'MIX');
                $allowLabColumns = !$isKategoriEmpty && ($isKategoriMix || !$isKategoriLaOnly);
                $allowLaColumns = !$isKategoriEmpty && ($isKategoriMix || !$isKategoriLabOnly);

                $timbangLabStart = trim((string)($prepData['actual_timbang_lab_start'] ?? ''));
                $timbangLabFinish = trim((string)($prepData['actual_timbang_lab_finish'] ?? ''));
                $timbangLaStart = trim((string)($prepData['actual_timbang_la_start'] ?? ''));
                $timbangLaFinish = trim((string)($prepData['actual_timbang_la_finish'] ?? ''));
                $larutLabStart = trim((string)($prepData['actual_larut_lab_start'] ?? ''));
                $larutLabFinish = trim((string)($prepData['actual_larut_lab_finish'] ?? ''));
                $larutLaStart = trim((string)($prepData['actual_larut_la_start'] ?? ''));
                $larutLaFinish = trim((string)($prepData['actual_larut_la_finish'] ?? ''));
                $larutPrdksStart = trim((string)($prepData['actual_larut_prdks_start'] ?? ''));
                $larutPrdksFinish = trim((string)($prepData['actual_larut_prdks_finish'] ?? ''));

                if ($isKategoriLaOnly) {
                    $rowItem['actual_timbang_lab_start'] = '';
                    $rowItem['actual_timbang_lab_finish'] = '';
                    $rowItem['actual_timbang_lalab_start'] = '';
                    $rowItem['actual_timbang_lalab_finish'] = '';
                    $rowItem['actual_larut_lab_start'] = '';
                    $rowItem['actual_larut_lab_finish'] = '';
                    $rowItem['actual_larut_lalab_start'] = '';
                    $rowItem['actual_larut_lalab_finish'] = '';
                }
                if ($isKategoriEmpty) {
                    $rowItem['actual_timbang_lab_start'] = '';
                    $rowItem['actual_timbang_lab_finish'] = '';
                    $rowItem['actual_timbang_lalab_start'] = '';
                    $rowItem['actual_timbang_lalab_finish'] = '';
                    $rowItem['actual_larut_lab_start'] = '';
                    $rowItem['actual_larut_lab_finish'] = '';
                    $rowItem['actual_larut_lalab_start'] = '';
                    $rowItem['actual_larut_lalab_finish'] = '';
                    $rowItem['actual_timbang_la_start'] = '';
                    $rowItem['actual_timbang_la_finish'] = '';
                    $rowItem['actual_larut_la_start'] = '';
                    $rowItem['actual_larut_la_finish'] = '';
                }

                if ($allowLabColumns && $timbangLabStart !== '') {
                    $rowItem['actual_timbang_lab_start'] = $timbangLabStart;
                    $rowItem['actual_timbang_lalab_start'] = $timbangLabStart;
                }
                if ($allowLabColumns && $timbangLabFinish !== '') {
                    $rowItem['actual_timbang_lab_finish'] = $timbangLabFinish;
                    $rowItem['actual_timbang_lalab_finish'] = $timbangLabFinish;
                }
                if ($isKategoriLabOnly) {
                    $rowItem['actual_timbang_la_start'] = '';
                    $rowItem['actual_timbang_la_finish'] = '';
                    $rowItem['actual_larut_la_start'] = '';
                    $rowItem['actual_larut_la_finish'] = '';
                } elseif ($allowLaColumns && $timbangLaStart !== '') {
                    $rowItem['actual_timbang_la_start'] = $timbangLaStart;
                }
                if ($allowLaColumns && $timbangLaFinish !== '') {
                    $rowItem['actual_timbang_la_finish'] = $timbangLaFinish;
                }
                if ($allowLabColumns && $larutLabStart !== '') {
                    $rowItem['actual_larut_lab_start'] = $larutLabStart;
                    $rowItem['actual_larut_lalab_start'] = $larutLabStart;
                }
                if ($allowLabColumns && $larutLabFinish !== '') {
                    $rowItem['actual_larut_lab_finish'] = $larutLabFinish;
                    $rowItem['actual_larut_lalab_finish'] = $larutLabFinish;
                }
                if ($allowLaColumns && $larutLaStart !== '') {
                    $rowItem['actual_larut_la_start'] = $larutLaStart;
                }
                if ($allowLaColumns && $larutLaFinish !== '') {
                    $rowItem['actual_larut_la_finish'] = $larutLaFinish;
                }
                if ($larutPrdksStart !== '') {
                    $rowItem['actual_larut_prdks_start'] = $larutPrdksStart;
                }
                if ($larutPrdksFinish !== '') {
                    $rowItem['actual_larut_prdks_finish'] = $larutPrdksFinish;
                }
            }
        }
        unset($rowItem);
    }

    cpPlannerJsonExit([
        'success' => true,
        'message' => 'OK',
        'data' => $rows,
    ]);
}

if ($action === 'load_scouring') {
    $machineId = cpPlannerNormalizeMachineId($_POST['machine_id'] ?? '');
    $periodDate = trim((string)($_POST['period_date'] ?? ''));

    if ($machineId === '') {
        cpPlannerJsonExit([
            'success' => false,
            'message' => 'Machine tidak valid.',
            'data' => [],
        ]);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodDate)) {
        cpPlannerJsonExit([
            'success' => false,
            'message' => 'Periode tidak valid.',
            'data' => [],
        ]);
    }

    $toDateDisplay = static function ($value): string {
        if ($value === null || $value === '') {
            return '';
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('d/m/Y');
        }
        $raw = trim((string)$value);
        if ($raw === '') {
            return '';
        }
        $formats = ['Y-m-d', 'Y-m-d H:i:s', 'd/m/Y', 'd-m-Y'];
        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $raw);
            if ($dt instanceof DateTime) {
                return $dt->format('d/m/Y');
            }
        }
        $ts = strtotime($raw);
        if ($ts !== false) {
            return date('d/m/Y', $ts);
        }
        return $raw;
    };

    $toTimeDisplay = static function ($value): string {
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
        if (preg_match('/^\d{2}:\d{2}/', $raw)) {
            return substr($raw, 0, 5);
        }
        $ts = strtotime($raw);
        if ($ts !== false) {
            return date('H:i', $ts);
        }
        return $raw;
    };

    $toDateTimeText = static function ($value): string {
        if ($value === null || $value === '') {
            return '-';
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('d/m/Y H:i');
        }
        $raw = trim((string)$value);
        if ($raw === '') {
            return '-';
        }
        $ts = strtotime($raw);
        if ($ts !== false) {
            return date('d/m/Y H:i', $ts);
        }
        return $raw;
    };

    $sql = "
        SELECT
            id,
            seq_no,
            cp_no,
            tgl_cp,
            label_jual,
            cust_color,
            kode_lab,
            routing_name,
            qty,
            material_name,
            speed,
            grammature,
            plan_machine,
            plan_date,
            plan_paddry,
            plan_description,
            actual_date,
            actual_start,
            actual_end,
            actual_operator,
            actual_shift,
            down_time,
            actual_realisasi,
            posisi_hari_ini,
            next_routing,
            last_update,
            updated_by
        FROM dbo.cpp_scouring
        WHERE machine_id = ? AND period_date = ?
        ORDER BY seq_no ASC, id ASC
    ";

    $stmt = sqlsrv_query($conn, $sql, [$machineId, $periodDate]);
    if ($stmt === false) {
        cpPlannerJsonExit([
            'success' => false,
            'message' => cpPlannerSqlsrvErrorText('Gagal memuat data scouring.'),
            'data' => [],
        ]);
    }

    $rows = [];
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $qty = $r['qty'];
        $speed = $r['speed'];
        $speedDisplay = '';
        if ($speed !== null && $speed !== '') {
            if (is_numeric((string)$speed)) {
                $speedText = rtrim(rtrim(number_format((float)$speed, 2, '.', ''), '0'), '.');
                $speedDisplay = $speedText . ' m/m';
            } else {
                $speedText = trim((string)$speed);
                if ($speedText !== '') {
                    $speedDisplay = (stripos($speedText, 'm/m') !== false) ? $speedText : ($speedText . ' m/m');
                }
            }
        }
        $grammature = $r['grammature'];
        $actualRealisasi = $r['actual_realisasi'];
        $rows[] = [
            '_row_id' => 'dbs_' . (string)($r['id'] ?? ''),
            '_save_state' => 'saved',
            'no_cp' => trim((string)($r['cp_no'] ?? '')),
            'tgl_cp' => $toDateDisplay($r['tgl_cp'] ?? null),
            'label' => trim((string)($r['label_jual'] ?? '')),
            'cust_color' => trim((string)($r['cust_color'] ?? '')),
            'kode_lab' => trim((string)($r['kode_lab'] ?? '')),
            'routing_name' => trim((string)($r['routing_name'] ?? '')),
            'qty' => cpPlannerFormatQtyDisplay($qty, 2),
            'material' => trim((string)($r['material_name'] ?? '')),
            'speed' => $speedDisplay,
            'grammature' => $grammature !== null && $grammature !== '' ? number_format((float)$grammature, 4, '.', '') : '',
            'plan_machine' => trim((string)($r['plan_machine'] ?? '')),
            'plan_date' => $toDateDisplay($r['plan_date'] ?? null),
            'plan_paddry' => trim((string)($r['plan_paddry'] ?? '')),
            'plan_description' => trim((string)($r['plan_description'] ?? '')),
            'actual_date' => $toDateDisplay($r['actual_date'] ?? null),
            'actual_start' => $toTimeDisplay($r['actual_start'] ?? null),
            'actual_end' => $toTimeDisplay($r['actual_end'] ?? null),
            'actual_operator' => trim((string)($r['actual_operator'] ?? '')),
            'actual_shift' => trim((string)($r['actual_shift'] ?? '')),
            'down_time' => trim((string)($r['down_time'] ?? '')),
            'actual_realisasi' => $actualRealisasi !== null && $actualRealisasi !== '' ? number_format((float)$actualRealisasi, 4, '.', '') : '',
            'posisi_hari_ini' => trim((string)($r['posisi_hari_ini'] ?? '')),
            'next_routing' => trim((string)($r['next_routing'] ?? '')),
            'last_update' => $toDateTimeText($r['last_update'] ?? null),
            'updated_by' => trim((string)($r['updated_by'] ?? '')),
        ];
    }
    sqlsrv_free_stmt($stmt);

    cpPlannerJsonExit([
        'success' => true,
        'message' => 'OK',
        'data' => $rows,
    ]);
}

if ($action === 'load_presett') {
    $machineId = cpPlannerNormalizeMachineId($_POST['machine_id'] ?? '');
    $periodDate = trim((string)($_POST['period_date'] ?? ''));

    if ($machineId === '') {
        cpPlannerJsonExit([
            'success' => false,
            'message' => 'Machine tidak valid.',
            'data' => [],
        ]);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodDate)) {
        cpPlannerJsonExit([
            'success' => false,
            'message' => 'Periode tidak valid.',
            'data' => [],
        ]);
    }

    $toDateDisplay = static function ($value): string {
        if ($value === null || $value === '') {
            return '';
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('d/m/Y');
        }
        $raw = trim((string)$value);
        if ($raw === '') {
            return '';
        }
        $formats = ['Y-m-d', 'Y-m-d H:i:s', 'd/m/Y', 'd-m-Y'];
        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $raw);
            if ($dt instanceof DateTime) {
                return $dt->format('d/m/Y');
            }
        }
        $ts = strtotime($raw);
        if ($ts !== false) {
            return date('d/m/Y', $ts);
        }
        return $raw;
    };

    $toTimeDisplay = static function ($value): string {
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
        if (preg_match('/^\d{2}:\d{2}/', $raw)) {
            return substr($raw, 0, 5);
        }
        $ts = strtotime($raw);
        if ($ts !== false) {
            return date('H:i', $ts);
        }
        return $raw;
    };

    $toDateTimeText = static function ($value): string {
        if ($value === null || $value === '') {
            return '-';
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('d/m/Y H:i');
        }
        $raw = trim((string)$value);
        if ($raw === '') {
            return '-';
        }
        $ts = strtotime($raw);
        if ($ts !== false) {
            return date('d/m/Y H:i', $ts);
        }
        return $raw;
    };

    $sql = "
        SELECT
            id,
            seq_no,
            cp_no,
            tgl_cp,
            urut_plan,
            label_jual,
            cust_color,
            kode_lab,
            material_name,
            qty,
            ket,
            posisi_hari_ini,
            plan_date,
            plan_machine,
            actual_start,
            actual_end,
            actual_operator,
            actual_shift,
            status,
            down_time,
            keterangan,
            last_update,
            updated_by
        FROM dbo.cpp_presett
        WHERE machine_id = ? AND period_date = ?
        ORDER BY seq_no ASC, id ASC
    ";

    $stmt = sqlsrv_query($conn, $sql, [$machineId, $periodDate]);
    if ($stmt === false) {
        cpPlannerJsonExit([
            'success' => false,
            'message' => cpPlannerSqlsrvErrorText('Gagal memuat data presett.'),
            'data' => [],
        ]);
    }

    $rows = [];
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $qty = $r['qty'];
        $urutPlan = $r['urut_plan'];
        $rows[] = [
            '_row_id' => 'dbps_' . (string)($r['id'] ?? ''),
            '_save_state' => 'saved',
            'no_cp' => trim((string)($r['cp_no'] ?? '')),
            'tgl_cp' => $toDateDisplay($r['tgl_cp'] ?? null),
            'urut_plan' => $urutPlan !== null && $urutPlan !== '' ? trim((string)$urutPlan) : '',
            'label' => trim((string)($r['label_jual'] ?? '')),
            'cust_color' => trim((string)($r['cust_color'] ?? '')),
            'kode_lab' => trim((string)($r['kode_lab'] ?? '')),
            'material' => trim((string)($r['material_name'] ?? '')),
            'qty' => cpPlannerFormatQtyDisplay($qty, 2),
            'ket' => trim((string)($r['ket'] ?? '')),
            'posisi_hari_ini' => trim((string)($r['posisi_hari_ini'] ?? '')),
            'plan_date' => $toDateDisplay($r['plan_date'] ?? null),
            'plan_machine' => trim((string)($r['plan_machine'] ?? '')),
            'actual_start' => $toTimeDisplay($r['actual_start'] ?? null),
            'actual_end' => $toTimeDisplay($r['actual_end'] ?? null),
            'actual_operator' => trim((string)($r['actual_operator'] ?? '')),
            'actual_shift' => trim((string)($r['actual_shift'] ?? '')),
            'actual_wheel_no' => trim((string)($r['status'] ?? '')),
            'down_time' => trim((string)($r['down_time'] ?? '')),
            'plan_description' => trim((string)($r['keterangan'] ?? '')),
            'last_update' => $toDateTimeText($r['last_update'] ?? null),
            'updated_by' => trim((string)($r['updated_by'] ?? '')),
        ];
    }
    sqlsrv_free_stmt($stmt);

    cpPlannerJsonExit([
        'success' => true,
        'message' => 'OK',
        'data' => $rows,
    ]);
}

if ($action === 'lookup_paddry_planning') {
    $cpNosInput = $_POST['cp_nos'] ?? [];
    if (!is_array($cpNosInput)) {
        $decoded = json_decode((string)$cpNosInput, true);
        $cpNosInput = is_array($decoded) ? $decoded : [];
    }

    $lookup = cpPlannerFetchLatestPaddryPlanningByCp($conn, $cpNosInput);

    $toDateDisplay = static function ($value): string {
        if ($value === null || $value === '') {
            return '';
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('d/m/Y');
        }
        $raw = trim((string)$value);
        if ($raw === '') {
            return '';
        }
        $formats = ['Y-m-d', 'Y-m-d H:i:s', 'd/m/Y', 'd-m-Y'];
        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $raw);
            if ($dt instanceof DateTime) {
                return $dt->format('d/m/Y');
            }
        }
        $ts = strtotime($raw);
        if ($ts !== false) {
            return date('d/m/Y', $ts);
        }
        return $raw;
    };

    $rows = [];
    foreach ($lookup as $cpKey => $item) {
        $rows[$cpKey] = [
            'plan_date' => $toDateDisplay($item['plan_date_raw'] ?? null),
            'plan_paddry' => trim((string)($item['plan_paddry'] ?? '')),
            'machine_id' => trim((string)($item['machine_id'] ?? '')),
        ];
    }

    cpPlannerJsonExit([
        'success' => true,
        'message' => 'OK',
        'data' => $rows,
    ]);
}

if ($action === 'lookup_current_position') {
    $cpNosInput = $_POST['cp_nos'] ?? [];
    if (!is_array($cpNosInput)) {
        $decoded = json_decode((string)$cpNosInput, true);
        $cpNosInput = is_array($decoded) ? $decoded : [];
    }

    $cpNos = [];
    $seenCpNos = [];
    foreach ($cpNosInput as $cpNoRaw) {
        $cpNo = strtoupper(trim((string)$cpNoRaw));
        if ($cpNo === '') {
            continue;
        }
        if (isset($seenCpNos[$cpNo])) {
            continue;
        }
        $seenCpNos[$cpNo] = true;
        $cpNos[] = $cpNo;
    }

    if (empty($cpNos)) {
        cpPlannerJsonExit([
            'success' => true,
            'message' => 'OK',
            'data' => [],
        ]);
    }

    if (!($conn3 instanceof PDO)) {
        cpPlannerJsonExit([
            'success' => false,
            'message' => 'Koneksi source posisi saat ini tidak tersedia.',
            'data' => [],
        ]);
    }

    $lookupLayout = strtolower(trim((string)($_POST['layout'] ?? '')));
    $lookupPlanTypeRaw = trim((string)($_POST['plan_type'] ?? ''));
    $normalizePlanTypeText = static function (string $value): string {
        $value = strtolower(trim($value));
        $value = str_replace(['&', '/', '\\'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return trim($value);
    };
    $lookupPlanType = $normalizePlanTypeText($lookupPlanTypeRaw);
    $isPaddryLookup = ($lookupLayout === 'paddry')
        || in_array($lookupPlanType, ['paddry', 'planning paddry', 'plan paddry'], true);

    $placeholders = [];
    $params = [];
    foreach ($cpNos as $idx => $cpNo) {
        $key = ':cp_' . $idx;
        $placeholders[] = $key;
        $params[$key] = $cpNo;
    }

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

    $getTableColumns = static function (PDO $db, string $tableName): array {
        $stmt = $db->prepare("
            SELECT LOWER(column_name) AS col_name
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = :table_name
        ");
        $stmt->bindValue(':table_name', strtolower($tableName), PDO::PARAM_STR);
        $stmt->execute();
        $cols = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $col = trim((string)($r['col_name'] ?? ''));
            if ($col !== '') {
                $cols[$col] = true;
            }
        }
        return $cols;
    };

    $joinTech = '';
    $labelExpr = "''";
    $custColorExpr = "''";
    if ($tableExists($conn3, 'smprodtechdata')) {
        $techCols = $getTableColumns($conn3, 'smprodtechdata');
        if (isset($techCols['prodid'])) {
            $joinTech = "LEFT JOIN smprodtechdata st ON h.prodid = st.prodid";
            if (isset($techCols['labeljual'])) {
                $labelExpr = "COALESCE(NULLIF(TRIM(CAST(st.labeljual AS TEXT)), ''), '')";
            }
            if (isset($techCols['cuscolor'])) {
                $custColorExpr = "COALESCE(NULLIF(TRIM(CAST(st.cuscolor AS TEXT)), ''), '')";
            }
        }
    }

    $nextProcessCteSql = '';
    if ($isPaddryLookup) {
        $nextProcessCteSql = ",
        next_process AS (
            SELECT
                productionhdid,
                MIN(rtgseq) AS next_rtgseq
            FROM pdproductionrtg
            WHERE COALESCE(prdqty, 0.0000) = 0
            GROUP BY productionhdid
        )";
    }

    $posisiHariIniExpr = $isPaddryLookup
        ? "CASE
                WHEN NULLIF(TRIM(CAST(r2.rtgname AS TEXT)), '') IS NULL
                     AND POSITION(
                         'VERPACK' IN UPPER(
                             REGEXP_REPLACE(
                                 COALESCE(
                                     NULLIF(TRIM(CAST(r1.rtgname AS TEXT)), ''),
                                     NULLIF(TRIM(CAST(r4.rtgname AS TEXT)), ''),
                                     ''
                                 ),
                                 '\s+',
                                 '',
                                 'g'
                             )
                         )
                     ) > 0
                    THEN 'VERPACKING'
                ELSE COALESCE(NULLIF(TRIM(CAST(r2.rtgname AS TEXT)), ''), '-')
            END"
        : "COALESCE(
                NULLIF(TRIM(CAST(r2.rtgname AS TEXT)), ''),
                NULLIF(TRIM(CAST(r1.rtgname AS TEXT)), ''),
                '-'
            )";

    $joinNextProcessSql = $isPaddryLookup
        ? "LEFT JOIN next_process np
            ON rh.productionhdid = np.productionhdid"
        : '';

    $joinNextRoutingSql = $isPaddryLookup
        ? "LEFT JOIN pdproductionrtg b
            ON b.productionhdid = np.productionhdid
            AND b.rtgseq = np.next_rtgseq"
        : "LEFT JOIN pdproductionrtg b
            ON b.productionhdid = a.productionhdid
            AND b.rtgseq = a.rtgseq + 1";

    $sql = "
        WITH ranked_hd AS (
            SELECT
                h.productionhdid,
                UPPER(TRIM(CAST(h.prdnmbr AS TEXT))) AS cp_key,
                ROW_NUMBER() OVER (
                    PARTITION BY UPPER(TRIM(CAST(h.prdnmbr AS TEXT)))
                    ORDER BY h.prddate DESC NULLS LAST, h.productionhdid DESC
                ) AS rn
            FROM pdproductionhd h
            WHERE UPPER(TRIM(CAST(h.prdnmbr AS TEXT))) IN (" . implode(', ', $placeholders) . ")
        ),
        last_process AS (
            SELECT
                productionhdid,
                MAX(rtgseq) AS current_rtgseq
            FROM pdproductionrtg
            WHERE prdqty > 0
            GROUP BY productionhdid
        ),
        last_defined_process AS (
            SELECT
                productionhdid,
                MAX(rtgseq) AS last_rtgseq
            FROM pdproductionrtg
            GROUP BY productionhdid
        ){$nextProcessCteSql},
        rm_rank AS (
            SELECT
                productionhdid,
                prodname,
                matqty,
                ROW_NUMBER() OVER (
                    PARTITION BY productionhdid
                    ORDER BY matqty DESC NULLS LAST
                ) AS rn
            FROM pdresultmat
            WHERE fgusedtype = 'A'
        ),
        base AS (
            SELECT
                pdbonreq.productionhdid,
                pdbonreq.prdnumber,
                MAX(pdbonreq.vlot) AS vlot,
                CASE
                    WHEN pdproductionmat.matqty < 500 THEN 'LAB'
                    ELSE 'LA'
                END AS lokasi_timbang
            FROM pdbonreq
            LEFT JOIN pdproductionmat
                ON pdbonreq.productionhdid = pdproductionmat.productionhdid
            WHERE
                pdproductionmat.fgusedtype = 'G'
                AND pdproductionmat.prodstructid IN ('51385','51386','39592')
                AND pdbonreq.rtgmsid IN
                ('555','556','559','809','838','842',
                 '571','572','573','814','841','844',
                 '815','848','849','850')
            GROUP BY
                pdbonreq.productionhdid,
                pdbonreq.prdnumber,
                CASE
                    WHEN pdproductionmat.matqty < 500 THEN 'LAB'
                    ELSE 'LA'
                END
        ),
        kategori AS (
            SELECT
                prdnumber,
                MAX(vlot) AS vlot,
                COUNT(DISTINCT lokasi_timbang) AS jumlah_kategori,
                MAX(lokasi_timbang) AS jenis_kategori
            FROM base
            GROUP BY prdnumber
        )
        SELECT
            rh.cp_key,
            {$posisiHariIniExpr} AS posisi_hari_ini,
            COALESCE(NULLIF(TRIM(CAST(r3.rtgname AS TEXT)), ''), '-') AS next_routing,
            COALESCE(NULLIF(TRIM(CAST(pdcolorms.colorcode AS TEXT)), ''), '') AS kode_lab,
            COALESCE(NULLIF(TRIM(CAST(pdcolorms.colorname AS TEXT)), ''), '') AS color_name,
            {$labelExpr} AS label,
            {$custColorExpr} AS cust_color,
            COALESCE(NULLIF(TRIM(CAST(rm.prodname AS TEXT)), ''), '') AS material,
            COALESCE(rm.matqty, 0) AS qty,
            COALESCE(k.vlot, 0) AS vlot,
            CASE
                WHEN k.jumlah_kategori = 2 THEN 'MIX'
                WHEN k.jenis_kategori = 'LAB' THEN 'LAB'
                WHEN k.jenis_kategori = 'LA' THEN 'LA'
                ELSE ''
            END AS kategori_penimbangan
        FROM ranked_hd rh
        LEFT JOIN pdproductionhd h
            ON h.productionhdid = rh.productionhdid
        LEFT JOIN pdcolorms
            ON h.colorid = pdcolorms.colormsid
        {$joinTech}
        LEFT JOIN rm_rank rm
            ON rm.productionhdid = rh.productionhdid
            AND rm.rn = 1
        LEFT JOIN last_process lp
            ON rh.productionhdid = lp.productionhdid
        LEFT JOIN pdproductionrtg a
            ON a.productionhdid = lp.productionhdid
            AND a.rtgseq = lp.current_rtgseq
        LEFT JOIN pdrtgms r1
            ON a.rtgmsid = r1.rtgmsid
        LEFT JOIN last_defined_process ldp
            ON rh.productionhdid = ldp.productionhdid
        LEFT JOIN pdproductionrtg d
            ON d.productionhdid = ldp.productionhdid
            AND d.rtgseq = ldp.last_rtgseq
        LEFT JOIN pdrtgms r4
            ON d.rtgmsid = r4.rtgmsid
        {$joinNextProcessSql}
        {$joinNextRoutingSql}
        LEFT JOIN pdrtgms r2
            ON b.rtgmsid = r2.rtgmsid
        LEFT JOIN pdproductionrtg c
            ON c.productionhdid = b.productionhdid
            AND c.rtgseq = b.rtgseq + 1
        LEFT JOIN pdrtgms r3
            ON c.rtgmsid = r3.rtgmsid
        LEFT JOIN kategori k
            ON UPPER(TRIM(CAST(k.prdnumber AS TEXT))) = rh.cp_key
        WHERE rh.rn = 1
    ";

    try {
        $stmt = $conn3->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $map = [];
        foreach ($rows as $row) {
            $cpKey = strtoupper(trim((string)($row['cp_key'] ?? '')));
            if ($cpKey === '') {
                continue;
            }
            $map[$cpKey] = [
                'posisi_hari_ini' => trim((string)($row['posisi_hari_ini'] ?? '')),
                'next_routing' => trim((string)($row['next_routing'] ?? '')),
                'kode_lab' => trim((string)($row['kode_lab'] ?? '')),
                'color_name' => trim((string)($row['color_name'] ?? '')),
                'label' => trim((string)($row['label'] ?? '')),
                'cust_color' => trim((string)($row['cust_color'] ?? '')),
                'material' => trim((string)($row['material'] ?? '')),
                'qty' => cpPlannerFormatQtyDisplay($row['qty'] ?? null, 3, ''),
                'vlot' => cpPlannerFormatQtyDisplay($row['vlot'] ?? null, 3, ''),
                'kategori_penimbangan' => strtoupper(trim((string)($row['kategori_penimbangan'] ?? ''))),
            ];
        }

        cpPlannerJsonExit([
            'success' => true,
            'message' => 'OK',
            'data' => $map,
        ]);
    } catch (Throwable $e) {
        cpPlannerJsonExit([
            'success' => false,
            'message' => $e->getMessage(),
            'data' => [],
        ]);
    }
}

if ($action === 'search_cp') {
    header('Content-Type: application/json; charset=utf-8');

    // Client-Side DataTables: hanya butuh filter utama, bukan SSP params
    $filterRouting = trim((string)($_POST['filter_routing'] ?? ''));
    $filterKategori = trim((string)($_POST['filter_kategori'] ?? ''));
    $filterColor = trim((string)($_POST['filter_color'] ?? ''));
    $selectedPlanType = trim((string)($_POST['plan_type'] ?? ''));

    $qi = static function (string $name): string {
        return '"' . str_replace('"', '""', $name) . '"';
    };

    $getTableColumns = static function (PDO $db, string $tableName): array {
        $cacheDir = __DIR__ . '/cache';
        if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0777, true); }
        $metaFile = $cacheDir . '/metadata_' . strtolower($tableName) . '.json';
        $metaTTL = 86400; // 24 jam

        if (file_exists($metaFile) && (time() - filemtime($metaFile)) < $metaTTL) {
            $data = @file_get_contents($metaFile);
            if ($data) {
                $decoded = json_decode($data, true);
                if (is_array($decoded)) return $decoded;
            }
        }
        $stmt = $db->prepare("
            SELECT column_name
            FROM information_schema.columns
            WHERE table_name = :table_name
        ");
        $stmt->bindValue(':table_name', strtolower($tableName), PDO::PARAM_STR);
        $stmt->execute();

        $cols = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $col) {
            $name = strtolower((string)$col);
            if ($name !== '') {
                $cols[$name] = true;
            }
        }
        @file_put_contents($metaFile, json_encode($cols));
        return $cols;
    };

    $firstExistingColumn = static function (array $cols, array $candidates): ?string {
        foreach ($candidates as $candidate) {
            $key = strtolower($candidate);
            if (isset($cols[$key])) {
                return $key;
            }
        }
        return null;
    };

    $tableExists = static function (PDO $db, string $tableName): bool {
        $stmt = $db->prepare("
            SELECT 1
            FROM information_schema.tables
            WHERE table_name = :table_name
            LIMIT 1
        ");
        $stmt->bindValue(':table_name', strtolower($tableName), PDO::PARAM_STR);
        $stmt->execute();
        return (bool)$stmt->fetchColumn();
    };

    $normalizeText = static function (string $value): string {
        $value = strtolower(trim($value));
        $value = str_replace(['&', '/', '\\'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return trim($value);
    };

    $planTypeMap = [
        'bakar bulu' => 'B',
        'scouring' => 'S',
        'presett' => 'P',
        'pre sett' => 'P',
        'paddry' => 'D',
        'planning paddry' => 'D',
        'plan paddry' => 'D',
        'cpb' => 'C',
        'washing padsteam' => 'W',
        'washing and padsteam' => 'W',
        'washing pad steam' => 'W',
        'jetdyeing' => 'J',
        'jet-dyeing' => 'J',
        'jet dyeing' => 'J',
    ];

    $planTypeKey = $normalizeText($selectedPlanType);
    $planTypeCode = $planTypeMap[$planTypeKey] ?? null;
    $isPaddryPlanType = ($planTypeCode === 'D');
    $isBakarBuluPlanType = ($planTypeKey === 'bakar bulu');
    $isScouringPlanType = ($planTypeKey === 'scouring');
    $isPreSettPlanType = ($planTypeKey === 'presett' || $planTypeKey === 'pre sett');
    $isCpbPlanType = ($planTypeKey === 'cpb');
    $isWashingPadSteamPlanType = ($planTypeCode === 'W');
    $isJetDyeingPlanType = ($planTypeCode === 'J');

    $masterRoutingCodes = [];
    $masterRoutingCodesNormalized = [];
    $masterRoutingPlaceholders = [];
    $masterRoutingParams = [];
    $masterRoutingNormalizedPlaceholders = [];
    $masterRoutingNormalizedParams = [];
    $masterRoutingInSql = '';
    $masterRoutingNormalizedInSql = '';

    if (($isPaddryPlanType || $isBakarBuluPlanType || $isScouringPlanType || $isPreSettPlanType || $isCpbPlanType || $isWashingPadSteamPlanType || $isJetDyeingPlanType) && $selectedPlanType !== '') {
        $sqlMasterRouting = "
            SELECT DISTINCT LTRIM(RTRIM(CAST(routing_id AS NVARCHAR(50)))) AS routing_code
            FROM dbo.ms_routing
            WHERE LOWER(LTRIM(RTRIM(ISNULL(planning_type, '')))) = LOWER(LTRIM(RTRIM(?)))
              AND LTRIM(RTRIM(ISNULL(CAST(routing_id AS NVARCHAR(50)), ''))) <> ''
            ORDER BY LTRIM(RTRIM(CAST(routing_id AS NVARCHAR(50)))) ASC
        ";
        $stmtMasterRouting = sqlsrv_query($conn, $sqlMasterRouting, [$selectedPlanType]);
        if ($stmtMasterRouting === false) {
            echo json_encode([
                'data' => [],
                'error' => cpPlannerSqlsrvErrorText('Gagal memuat master routing.'),
            ]);
            exit;
        }
        while ($rowMaster = sqlsrv_fetch_array($stmtMasterRouting, SQLSRV_FETCH_ASSOC)) {
            $routingCode = trim((string)($rowMaster['routing_code'] ?? ''));
            if ($routingCode === '') {
                continue;
            }
            $masterRoutingCodes[$routingCode] = $routingCode;
            $normalizedRoutingCode = ltrim($routingCode, '0');
            if ($normalizedRoutingCode === '') {
                $normalizedRoutingCode = '0';
            }
            $masterRoutingCodesNormalized[$normalizedRoutingCode] = $normalizedRoutingCode;
        }
        sqlsrv_free_stmt($stmtMasterRouting);
    }

    $masterRoutingCodes = array_values($masterRoutingCodes);
    $masterRoutingCodesNormalized = array_values($masterRoutingCodesNormalized);
    if (($isPaddryPlanType || $isBakarBuluPlanType || $isScouringPlanType || $isPreSettPlanType || $isCpbPlanType || $isWashingPadSteamPlanType || $isJetDyeingPlanType) && empty($masterRoutingCodes)) {
        echo json_encode(['data' => []]);
        exit;
    }

    if ($isPaddryPlanType || $isBakarBuluPlanType || $isScouringPlanType || $isPreSettPlanType || $isCpbPlanType || $isWashingPadSteamPlanType || $isJetDyeingPlanType) {
        foreach ($masterRoutingCodes as $idx => $routingCode) {
            $paramKey = ':ms_rtg_' . $idx;
            $masterRoutingPlaceholders[] = $paramKey;
            $masterRoutingParams[$paramKey] = $routingCode;
        }
        foreach ($masterRoutingCodesNormalized as $idx => $routingCodeNormalized) {
            $paramKey = ':ms_rtg_norm_' . $idx;
            $masterRoutingNormalizedPlaceholders[] = $paramKey;
            $masterRoutingNormalizedParams[$paramKey] = $routingCodeNormalized;
        }
        $masterRoutingInSql = implode(', ', $masterRoutingPlaceholders);
        $masterRoutingNormalizedInSql = implode(', ', $masterRoutingNormalizedPlaceholders);
    }

    $currUser = $_SESSION['UserName'] ?? '';
    $groupId = (int)($_SESSION['GroupId'] ?? 0);
    $isRestricted = false;
    $allowedRoutings = [];
    // Akses Search CP dibuka untuk semua user group.
    // Filter berbasis mapping planning_user_group/planning_group_rtg dinonaktifkan.

    $paddryCteSql = '';
    $bakarBuluCteSql = '';
    $scouringCteSql = '';
    $preSettCteSql = '';
    $cpbCteSql = '';
    $washingCteSql = '';
    $jetDyeingCteSql = '';
    if ($isPaddryPlanType) {
        $paddryCteSql = ",
paddry AS (
    SELECT
        a.productionhdid,
        STRING_AGG(r.rtgname, ', ' ORDER BY r.rtgname) AS lokasi_paddry
    FROM pdproductionrtg a
    INNER JOIN pdrtgms r
        ON a.rtgmsid = r.rtgmsid
    WHERE (
        LTRIM(RTRIM(CAST(r.rtgcode AS TEXT))) IN ({$masterRoutingInSql})
        OR COALESCE(
            NULLIF(REGEXP_REPLACE(LTRIM(RTRIM(CAST(r.rtgcode AS TEXT))), '^0+', ''), ''),
            '0'
        ) IN ({$masterRoutingNormalizedInSql})
    )
    GROUP BY a.productionhdid
)";
    }
    if ($isBakarBuluPlanType) {
        $bakarBuluCteSql = ",
bakar_bulu AS (
    SELECT
        a.productionhdid,
        STRING_AGG(r.rtgname, ', ' ORDER BY r.rtgname) AS lokasi_bakar_bulu
    FROM pdproductionrtg a
    INNER JOIN pdrtgms r
        ON a.rtgmsid = r.rtgmsid
    WHERE (
        LTRIM(RTRIM(CAST(r.rtgcode AS TEXT))) IN ({$masterRoutingInSql})
        OR COALESCE(
            NULLIF(REGEXP_REPLACE(LTRIM(RTRIM(CAST(r.rtgcode AS TEXT))), '^0+', ''), ''),
            '0'
        ) IN ({$masterRoutingNormalizedInSql})
    )
    GROUP BY a.productionhdid
)";
    }
    if ($isScouringPlanType) {
        $scouringCteSql = ",
scouring AS (
    SELECT
        a.productionhdid,
        STRING_AGG(r.rtgname, ', ' ORDER BY r.rtgname) AS lokasi_scouring
    FROM pdproductionrtg a
    INNER JOIN pdrtgms r
        ON a.rtgmsid = r.rtgmsid
    WHERE (
        LTRIM(RTRIM(CAST(r.rtgcode AS TEXT))) IN ({$masterRoutingInSql})
        OR COALESCE(
            NULLIF(REGEXP_REPLACE(LTRIM(RTRIM(CAST(r.rtgcode AS TEXT))), '^0+', ''), ''),
            '0'
        ) IN ({$masterRoutingNormalizedInSql})
    )
    GROUP BY a.productionhdid
)";
    }
    if ($isPreSettPlanType) {
        $preSettCteSql = ",
presett AS (
    SELECT
        a.productionhdid,
        STRING_AGG(r.rtgname, ', ' ORDER BY r.rtgname) AS lokasi_presett
    FROM pdproductionrtg a
    INNER JOIN pdrtgms r
        ON a.rtgmsid = r.rtgmsid
    WHERE (
        LTRIM(RTRIM(CAST(r.rtgcode AS TEXT))) IN ({$masterRoutingInSql})
        OR COALESCE(
            NULLIF(REGEXP_REPLACE(LTRIM(RTRIM(CAST(r.rtgcode AS TEXT))), '^0+', ''), ''),
            '0'
        ) IN ({$masterRoutingNormalizedInSql})
    )
    GROUP BY a.productionhdid
)";
    }
    if ($isCpbPlanType) {
        $cpbCteSql = ",
cpb AS (
    SELECT
        a.productionhdid,
        STRING_AGG(r.rtgname, ', ' ORDER BY r.rtgname) AS lokasi_cpb
    FROM pdproductionrtg a
    INNER JOIN pdrtgms r
        ON a.rtgmsid = r.rtgmsid
    WHERE (
        LTRIM(RTRIM(CAST(r.rtgcode AS TEXT))) IN ({$masterRoutingInSql})
        OR COALESCE(
            NULLIF(REGEXP_REPLACE(LTRIM(RTRIM(CAST(r.rtgcode AS TEXT))), '^0+', ''), ''),
            '0'
        ) IN ({$masterRoutingNormalizedInSql})
    )
    GROUP BY a.productionhdid
)";
    }
    if ($isWashingPadSteamPlanType) {
        $washingCteSql = ",
washing AS (
    SELECT
        a.productionhdid,
        STRING_AGG(r.rtgname, ', ' ORDER BY r.rtgname) AS lokasi_washing
    FROM pdproductionrtg a
    INNER JOIN pdrtgms r
        ON a.rtgmsid = r.rtgmsid
    WHERE (
        LTRIM(RTRIM(CAST(r.rtgcode AS TEXT))) IN ({$masterRoutingInSql})
        OR COALESCE(
            NULLIF(REGEXP_REPLACE(LTRIM(RTRIM(CAST(r.rtgcode AS TEXT))), '^0+', ''), ''),
            '0'
        ) IN ({$masterRoutingNormalizedInSql})
    )
    GROUP BY a.productionhdid
)";
    }
    if ($isJetDyeingPlanType) {
        $jetDyeingCteSql = ",
jetdyeing AS (
    SELECT
        a.productionhdid,
        STRING_AGG(r.rtgname, ', ' ORDER BY r.rtgname) AS lokasi_jetdyeing
    FROM pdproductionrtg a
    INNER JOIN pdrtgms r
        ON a.rtgmsid = r.rtgmsid
    WHERE (
        LTRIM(RTRIM(CAST(r.rtgcode AS TEXT))) IN ({$masterRoutingInSql})
        OR COALESCE(
            NULLIF(REGEXP_REPLACE(LTRIM(RTRIM(CAST(r.rtgcode AS TEXT))), '^0+', ''), ''),
            '0'
        ) IN ({$masterRoutingNormalizedInSql})
    )
    GROUP BY a.productionhdid
)";
    }

    $baseCte = "
WITH last_process AS (
    SELECT
        productionhdid,
        MAX(rtgseq) AS current_rtgseq
    FROM pdproductionrtg
    WHERE prdqty > 0
    GROUP BY productionhdid
),
last_defined_process AS (
    SELECT
        productionhdid,
        MAX(rtgseq) AS last_rtgseq
    FROM pdproductionrtg
    GROUP BY productionhdid
),
next_process AS (
    SELECT
        productionhdid,
        MIN(rtgseq) AS next_rtgseq
    FROM pdproductionrtg
    WHERE COALESCE(prdqty, 0.0000) = 0
    GROUP BY productionhdid
),
base AS (
    SELECT
        pdbonreq.productionhdid,
        pdbonreq.prdnumber,
        MAX(pdbonreq.vlot) AS vlot,
        CASE
            WHEN pdproductionmat.matqty < 500 THEN 'LAB'
            ELSE 'LA'
        END AS lokasi_timbang
    FROM pdbonreq
    LEFT JOIN pdproductionmat
        ON pdbonreq.productionhdid = pdproductionmat.productionhdid
    WHERE
        pdproductionmat.fgusedtype = 'G'
        AND pdproductionmat.prodstructid IN ('51385','51386','39592')
        AND pdbonreq.rtgmsid IN
        ('555','556','559','809','838','842',
         '571','572','573','814','841','844',
         '815','848','849','850')
    GROUP BY
        pdbonreq.productionhdid,
        pdbonreq.prdnumber,
        CASE
            WHEN pdproductionmat.matqty < 500 THEN 'LAB'
            ELSE 'LA'
        END
),
kategori AS (
    SELECT
        prdnumber,
        MAX(vlot) AS vlot,
        COUNT(DISTINCT lokasi_timbang) AS jumlah_kategori,
        MAX(lokasi_timbang) AS jenis_kategori
    FROM base
    GROUP BY prdnumber
){$paddryCteSql}{$bakarBuluCteSql}{$scouringCteSql}{$preSettCteSql}{$cpbCteSql}{$washingCteSql}{$jetDyeingCteSql}
";

    $techCols = $getTableColumns($conn3, 'smprodtechdata');
    $hdCols = $getTableColumns($conn3, 'pdproductionhd');

    $structTableExists = $tableExists($conn3, 'smprodstruct');
    $structCols = $structTableExists ? $getTableColumns($conn3, 'smprodstruct') : [];

    $gradeTableExists = $tableExists($conn3, 'pdgradems');
    $gradeCols = $gradeTableExists ? $getTableColumns($conn3, 'pdgradems') : [];

    $brandTableExists = $tableExists($conn3, 'pdbrandms');
    $brandCols = $brandTableExists ? $getTableColumns($conn3, 'pdbrandms') : [];

    $productTypeTableExists = $tableExists($conn3, 'smproducttype');
    $productTypeCols = $productTypeTableExists ? $getTableColumns($conn3, 'smproducttype') : [];

    $labelCol = $firstExistingColumn($techCols, ['labeljual']);
    $custColorCol = $firstExistingColumn($techCols, ['cuscolor']);
    $handFeelCodeCol = $firstExistingColumn($techCols, ['handfeelcode', 'handfeel_code', 'hfcode']);
    $handFeelNameCol = $firstExistingColumn($techCols, ['handfeelname', 'handfeel_name', 'hfname']);
    $brandCodeCol = $firstExistingColumn($techCols, ['brandcode', 'brand_code', 'brcode']);
    $brandNameCol = $firstExistingColumn($techCols, ['brandname', 'brand_name', 'brname']);
    $gradeCol = $firstExistingColumn($techCols, ['grade']);
    $productTypeCol = $firstExistingColumn($techCols, ['prodtypename', 'product_type', 'prodtype']);
    $productStructCodeCol = $firstExistingColumn($techCols, ['prodstructid', 'prodstructcode', 'product_structure_code']);

    $techGradeMsIdCol = $firstExistingColumn($techCols, ['grademsid', 'gradeid']);
    $techBrandMsIdCol = $firstExistingColumn($techCols, ['brandmsid', 'brandid']);

    $hdProdTypeCol = $firstExistingColumn($hdCols, ['prodtype', 'product_type', 'prodtypecode']);
    $hdProdStructIdCol = $firstExistingColumn($hdCols, ['prodstructid', 'prodstructcode', 'product_structure_code']);
    $hdProdCodeCol = $firstExistingColumn($hdCols, ['prodcode', 'product_code', 'prodid']);
    $hdProdNameCol = $firstExistingColumn($hdCols, ['prodname', 'product_name']);

    $gradeMsIdCol = $firstExistingColumn($gradeCols, ['grademsid', 'gradeid']);
    $gradeCodeCol = $firstExistingColumn($gradeCols, ['gradecode', 'grade']);
    $gradeNameCol = $firstExistingColumn($gradeCols, ['gradedesc', 'gradename']);

    $brandMsIdCol = $firstExistingColumn($brandCols, ['brandmsid', 'brandid']);
    $brandCodeMasterCol = $firstExistingColumn($brandCols, ['brandcode', 'brand_code']);
    $brandNameMasterCol = $firstExistingColumn($brandCols, ['brandname', 'brand_name']);

    $productTypeCodeCol = $firstExistingColumn($productTypeCols, ['prodtypecode', 'product_type_code', 'prodtype']);
    $productTypeNameCol = $firstExistingColumn($productTypeCols, ['prodtypename', 'product_type', 'prodtypenameid', 'prodtypenameen']);

    $structIdCol = $firstExistingColumn($structCols, ['prodstructid', 'prodstructcode', 'product_structure_code']);
    $structCodeCol = $firstExistingColumn($structCols, ['structcode', 'prodstructcode', 'product_structure_code']);
    $structNameCol = $firstExistingColumn($structCols, ['structname', 'prodstructname', 'product_structure_name']);

    $exprOrEmpty = static function (?string $colName, string $tableAlias) use ($qi): string {
        if ($colName === null || $colName === '') {
            return "''";
        }
        return "COALESCE(CAST({$tableAlias}." . $qi($colName) . " AS TEXT), '')";
    };

    $labelExpr = $exprOrEmpty($labelCol, 'smprodtechdata');
    $custColorExpr = $exprOrEmpty($custColorCol, 'smprodtechdata');
    $handFeelCodeExpr = $exprOrEmpty($handFeelCodeCol, 'smprodtechdata');
    $handFeelNameExpr = $exprOrEmpty($handFeelNameCol, 'smprodtechdata');

    $brandCodeExprBase = $exprOrEmpty($brandCodeCol, 'smprodtechdata');
    $brandNameExprBase = $exprOrEmpty($brandNameCol, 'smprodtechdata');
    $gradeExprBase = $exprOrEmpty($gradeCol, 'smprodtechdata');
    $productTypeExprBase = $exprOrEmpty($productTypeCol, 'smprodtechdata');
    $productStructCodeExprBase = $exprOrEmpty($productStructCodeCol, 'smprodtechdata');

    $hdProductTypeExpr = $exprOrEmpty($hdProdTypeCol, 'h');
    $hdProductStructExpr = $exprOrEmpty($hdProdStructIdCol, 'h');
    $hdProductCodeExpr = $exprOrEmpty($hdProdCodeCol, 'h');
    $hdProductNameExpr = $exprOrEmpty($hdProdNameCol, 'h');

    $brandCodeExpr = "COALESCE(NULLIF({$brandCodeExprBase}, ''), '')";
    $brandNameExpr = "COALESCE(NULLIF({$brandNameExprBase}, ''), '')";
    $gradeExpr = "COALESCE(NULLIF({$gradeExprBase}, ''), '')";
    $productTypeExpr = "COALESCE(NULLIF({$productTypeExprBase}, ''), NULLIF({$hdProductTypeExpr}, ''), '')";
    $productStructCodeExpr = "COALESCE(NULLIF({$productStructCodeExprBase}, ''), NULLIF({$hdProductStructExpr}, ''), '')";
    $productStructNameExpr = "''";

    $joinGrade = '';
    if ($gradeTableExists && $techGradeMsIdCol !== null && $gradeMsIdCol !== null) {
        $joinGrade = "
LEFT JOIN pdgradems
    ON CAST(smprodtechdata." . $qi($techGradeMsIdCol) . " AS TEXT) = CAST(pdgradems." . $qi($gradeMsIdCol) . " AS TEXT)
";
        $gradeCodeExpr = $exprOrEmpty($gradeCodeCol, 'pdgradems');
        $gradeNameExpr = $exprOrEmpty($gradeNameCol, 'pdgradems');
        $gradeExpr = "COALESCE(NULLIF({$gradeCodeExpr}, ''), NULLIF({$gradeNameExpr}, ''), NULLIF({$gradeExprBase}, ''), '')";
    }

    $joinBrand = '';
    if ($brandTableExists && $techBrandMsIdCol !== null && $brandMsIdCol !== null) {
        $joinBrand = "
LEFT JOIN pdbrandms
    ON CAST(smprodtechdata." . $qi($techBrandMsIdCol) . " AS TEXT) = CAST(pdbrandms." . $qi($brandMsIdCol) . " AS TEXT)
";
        $brandCodeMasterExpr = $exprOrEmpty($brandCodeMasterCol, 'pdbrandms');
        $brandNameMasterExpr = $exprOrEmpty($brandNameMasterCol, 'pdbrandms');
        $brandCodeExpr = "COALESCE(NULLIF({$brandCodeMasterExpr}, ''), NULLIF({$brandCodeExprBase}, ''), '')";
        $brandNameExpr = "COALESCE(NULLIF({$brandNameMasterExpr}, ''), NULLIF({$brandNameExprBase}, ''), '')";
    }

    $joinProductType = '';
    if ($productTypeTableExists && $hdProdTypeCol !== null && $productTypeCodeCol !== null) {
        $joinProductType = "
LEFT JOIN smproducttype
    ON CAST(h." . $qi($hdProdTypeCol) . " AS TEXT) = CAST(smproducttype." . $qi($productTypeCodeCol) . " AS TEXT)
";
        $productTypeMasterExpr = $exprOrEmpty($productTypeNameCol, 'smproducttype');
        $productTypeExpr = "COALESCE(NULLIF({$productTypeMasterExpr}, ''), NULLIF({$productTypeExprBase}, ''), NULLIF({$hdProductTypeExpr}, ''), '')";
    }

    $joinProdStruct = '';
    if ($structTableExists && $hdProdStructIdCol !== null && $structIdCol !== null) {
        $joinProdStruct = "
LEFT JOIN smprodstruct
    ON CAST(h." . $qi($hdProdStructIdCol) . " AS TEXT) = CAST(smprodstruct." . $qi($structIdCol) . " AS TEXT)
";
        $productStructCodeMasterExpr = $exprOrEmpty($structCodeCol, 'smprodstruct');
        $productStructNameMasterExpr = $exprOrEmpty($structNameCol, 'smprodstruct');
        $productStructCodeExpr = "COALESCE(NULLIF({$productStructCodeMasterExpr}, ''), NULLIF({$productStructCodeExprBase}, ''), NULLIF({$hdProductStructExpr}, ''), '')";
        $productStructNameExpr = "COALESCE(NULLIF({$productStructNameMasterExpr}, ''), '')";
    }

    $lokasiPaddryExpr = $isPaddryPlanType ? "COALESCE(padd.lokasi_paddry, '-')" : "'-'";
    $lokasiBakarBuluExpr = $isBakarBuluPlanType ? "COALESCE(bb.lokasi_bakar_bulu, '-')" : "'-'";
    $lokasiScouringExpr = $isScouringPlanType ? "COALESCE(sc.lokasi_scouring, '-')" : "'-'";
    $lokasiPreSettExpr = $isPreSettPlanType ? "COALESCE(ps.lokasi_presett, '-')" : "'-'";
    $lokasiCpbExpr = $isCpbPlanType ? "COALESCE(cpb.lokasi_cpb, '-')" : "'-'";
    $lokasiWashingExpr = $isWashingPadSteamPlanType ? "COALESCE(ws.lokasi_washing, '-')" : "'-'";
    $lokasiJetDyeingExpr = $isJetDyeingPlanType ? "COALESCE(jd.lokasi_jetdyeing, '-')" : "'-'";
    $joinPaddrySql = $isPaddryPlanType
        ? "LEFT JOIN paddry padd\n    ON h.productionhdid = padd.productionhdid\n"
        : '';
    $wherePaddrySql = $isPaddryPlanType ? "  AND padd.productionhdid IS NOT NULL\n" : '';
    $joinBakarBuluSql = $isBakarBuluPlanType
        ? "LEFT JOIN bakar_bulu bb\n    ON h.productionhdid = bb.productionhdid\n"
        : '';
    $whereBakarBuluSql = $isBakarBuluPlanType ? "  AND bb.productionhdid IS NOT NULL\n" : '';
    $joinScouringSql = $isScouringPlanType
        ? "LEFT JOIN scouring sc\n    ON h.productionhdid = sc.productionhdid\n"
        : '';
    $whereScouringSql = $isScouringPlanType ? "  AND sc.productionhdid IS NOT NULL\n" : '';
    $joinPreSettSql = $isPreSettPlanType
        ? "LEFT JOIN presett ps\n    ON h.productionhdid = ps.productionhdid\n"
        : '';
    $wherePreSettSql = $isPreSettPlanType ? "  AND ps.productionhdid IS NOT NULL\n" : '';
    $joinCpbSql = $isCpbPlanType
        ? "LEFT JOIN cpb\n    ON h.productionhdid = cpb.productionhdid\n"
        : '';
    $whereCpbSql = $isCpbPlanType ? "  AND cpb.productionhdid IS NOT NULL\n" : '';
    $joinWashingSql = $isWashingPadSteamPlanType
        ? "LEFT JOIN washing ws\n    ON h.productionhdid = ws.productionhdid\n"
        : '';
    $whereWashingSql = $isWashingPadSteamPlanType ? "  AND ws.productionhdid IS NOT NULL\n" : '';
    $joinJetDyeingSql = $isJetDyeingPlanType
        ? "LEFT JOIN jetdyeing jd\n    ON h.productionhdid = jd.productionhdid\n"
        : '';
    $whereJetDyeingSql = $isJetDyeingPlanType ? "  AND jd.productionhdid IS NOT NULL\n" : '';
    $nextRoutingExpr = "r3.rtgname";
    $paddryPosisiHariIniExpr = "CASE
        WHEN COALESCE(NULLIF(TRIM(CAST(r2.rtgname AS TEXT)), ''), '') = ''
             AND POSITION(
                 'VERPACK' IN UPPER(
                     REGEXP_REPLACE(
                         COALESCE(
                             NULLIF(TRIM(CAST(r1.rtgname AS TEXT)), ''),
                             NULLIF(TRIM(CAST(r4.rtgname AS TEXT)), ''),
                             ''
                         ),
                         '\s+',
                         '',
                         'g'
                     )
                 )
             ) > 0
            THEN 'VERPACKING'
        ELSE r2.rtgname
    END";
    $posisiHariIniExpr = $isPaddryPlanType ? $paddryPosisiHariIniExpr : "COALESCE(r2.rtgname, r1.rtgname)";
    $nextRoutingHarianExpr = "r3.rtgname";
    $joinNextProcessSql = $isPaddryPlanType
        ? "LEFT JOIN next_process np\n    ON h.productionhdid = np.productionhdid\n"
        : '';
    $joinNextRoutingSql = $isPaddryPlanType
        ? "LEFT JOIN pdproductionrtg b\n    ON b.productionhdid = np.productionhdid\n    AND b.rtgseq = np.next_rtgseq\n"
        : "LEFT JOIN pdproductionrtg b\n    ON b.productionhdid = a.productionhdid\n    AND b.rtgseq = a.rtgseq + 1\n";
    $joinNextRoutingHarianSql = "LEFT JOIN pdproductionrtg c\n    ON c.productionhdid = b.productionhdid\n    AND c.rtgseq = b.rtgseq + 1\nLEFT JOIN pdrtgms r3\n    ON c.rtgmsid = r3.rtgmsid\n";
    $fgStatusClause = $isPaddryPlanType ? "h.fgstatus IN ('U','V')" : "h.fgstatus = 'U'";
    $filterRoutingPosisiExpr = $isPaddryPlanType ? $paddryPosisiHariIniExpr : "COALESCE(r2.rtgname, r1.rtgname)";
    $filterRoutingNextExpr = "r3.rtgname";
    $joinResultMatSql = $isPaddryPlanType
        ? ''
        : "LEFT JOIN pdresultmat rm\n    ON h.productionhdid = rm.productionhdid\n";
    $joinBomSql = $isPaddryPlanType
        ? "LEFT JOIN pdbomdt\n    ON h.bomhdid = pdbomdt.bomhdid\nLEFT JOIN smproduct\n    ON pdbomdt.prodid = smproduct.prodid\n"
        : '';
    $materialExpr = $isPaddryPlanType ? "smproduct.prodname" : "rm.prodname";
    // Qty mengikuti prdqty pada routing terakhir yang sudah terealisasi.
    // Jika seluruh routing masih 0 (termasuk baru berada di rtgseq 1), gunakan qty header.
    $qtyExpr = "CASE
        WHEN COALESCE(a.prdqty, 0) > 0 THEN a.prdqty
        ELSE h.prdqty
    END";
    $usedTypeWhereClause = $isPaddryPlanType ? "pdbomdt.fgusedtype = 'A'" : "rm.fgusedtype = 'A'";
    $productNameExpr = $isPaddryPlanType
        ? "COALESCE(NULLIF({$hdProductNameExpr}, ''), CAST(smproduct.prodname AS TEXT), {$labelExpr}, '')"
        : "COALESCE(NULLIF({$hdProductNameExpr}, ''), CAST(rm.prodname AS TEXT), {$labelExpr}, '')";

    $baseSelect = "
SELECT DISTINCT
    h.prdnmbr AS no_cp,
    h.prddate AS tgl_cp,
    r1.rtgcode AS routing_code,
    r1.rtgname AS current_routing,
    r0.rtgname AS previous_routing,
    {$hdProductCodeExpr} AS product_code,
    {$productNameExpr} AS product_name,
    COALESCE(CAST(wc.workcentercode AS TEXT), CAST(h.workcenterid AS TEXT), '') AS work_center_code,
    COALESCE(CAST(wc.workcentername AS TEXT), CAST(wc.workcentercode AS TEXT), CAST(h.workcenterid AS TEXT), '') AS work_center_name,
    {$labelExpr} AS label,
    {$custColorExpr} AS cust_color,
    {$handFeelCodeExpr} AS handfeel_code,
    {$handFeelNameExpr} AS handfeel_name,
    {$brandCodeExpr} AS brand_code,
    {$brandNameExpr} AS brand_name,
    {$gradeExpr} AS grade,
    {$productTypeExpr} AS product_type,
    {$productStructCodeExpr} AS product_structure_code,
    {$productStructNameExpr} AS product_structure_name,
    {$lokasiPaddryExpr} AS lokasi_paddry,
    {$lokasiBakarBuluExpr} AS lokasi_bakar_bulu,
    {$lokasiScouringExpr} AS lokasi_scouring,
    {$lokasiPreSettExpr} AS lokasi_presett,
    {$lokasiCpbExpr} AS lokasi_cpb,
    {$lokasiWashingExpr} AS lokasi_washing,
    {$lokasiJetDyeingExpr} AS lokasi_jetdyeing,
    pdcolorms.colorcode AS kode_lab,
    pdcolorms.colorname AS color_name,
    {$materialExpr} AS material,
    {$qtyExpr} AS qty,
    {$nextRoutingExpr} AS next_routing,
    {$posisiHariIniExpr} AS posisi_hari_ini_routing,
    {$nextRoutingHarianExpr} AS next_routing_harian,
    k.vlot,
    CASE
        WHEN k.jumlah_kategori = 2 THEN 'MIX'
        WHEN k.jenis_kategori = 'LAB' THEN 'LAB'
        WHEN k.jenis_kategori = 'LA' THEN 'LA'
    END AS kategori_penimbangan
FROM pdproductionhd h
LEFT JOIN pdcolorms
    ON h.colorid = pdcolorms.colormsid
LEFT JOIN smprodtechdata
    ON h.prodid = smprodtechdata.prodid
{$joinGrade}
{$joinBrand}
{$joinProductType}
{$joinProdStruct}
{$joinResultMatSql}
{$joinBomSql}
LEFT JOIN last_process lp
    ON h.productionhdid = lp.productionhdid
LEFT JOIN pdproductionrtg a
    ON a.productionhdid = lp.productionhdid
    AND a.rtgseq = lp.current_rtgseq
LEFT JOIN pdrtgms r1
    ON a.rtgmsid = r1.rtgmsid
LEFT JOIN last_defined_process ldp
    ON h.productionhdid = ldp.productionhdid
LEFT JOIN pdproductionrtg d
    ON d.productionhdid = ldp.productionhdid
    AND d.rtgseq = ldp.last_rtgseq
LEFT JOIN pdrtgms r4
    ON d.rtgmsid = r4.rtgmsid
{$joinNextProcessSql}
{$joinNextRoutingSql}
LEFT JOIN pdrtgms r2
    ON b.rtgmsid = r2.rtgmsid
{$joinNextRoutingHarianSql}
LEFT JOIN pdproductionrtg prev_rtg
    ON prev_rtg.productionhdid = a.productionhdid
    AND prev_rtg.rtgseq = a.rtgseq - 1
LEFT JOIN pdrtgms r0
    ON prev_rtg.rtgmsid = r0.rtgmsid
LEFT JOIN pdworkcenter wc
    ON h.workcenterid = wc.workcenterid
LEFT JOIN kategori k
    ON h.prdnmbr = k.prdnumber
{$joinPaddrySql}
{$joinBakarBuluSql}
{$joinScouringSql}
{$joinPreSettSql}
{$joinCpbSql}
{$joinWashingSql}
{$joinJetDyeingSql}
WHERE h.workcenterid = '111'
  AND {$fgStatusClause}
  AND {$usedTypeWhereClause}
{$wherePaddrySql}
{$whereBakarBuluSql}
{$whereScouringSql}
{$wherePreSettSql}
{$whereCpbSql}
{$whereWashingSql}
{$whereJetDyeingSql}
";

    $whereExtra = [];
    $params = array_merge($masterRoutingParams, $masterRoutingNormalizedParams);
    $paramsRestricted = [];

    $productCodeSearchExpr = $hdProdCodeCol !== null
        ? "CAST(h." . $qi($hdProdCodeCol) . " AS TEXT)"
        : "CAST(h.prodid AS TEXT)";
    $productNameSearchExpr = $hdProdNameCol !== null
        ? "CAST(h." . $qi($hdProdNameCol) . " AS TEXT)"
        : "CAST(rm.prodname AS TEXT)";

    if ($filterRouting !== '') {
        $whereExtra[] = "(
            CAST({$filterRoutingPosisiExpr} AS TEXT) ILIKE :filter_routing
            OR CAST({$filterRoutingNextExpr} AS TEXT) ILIKE :filter_routing
            OR CAST(r1.rtgname AS TEXT) ILIKE :filter_routing
        )";
        $params[':filter_routing'] = '%' . $filterRouting . '%';
    }
    if ($filterColor !== '') {
        $whereExtra[] = "pdcolorms.colorname ILIKE :filter_color";
        $params[':filter_color'] = '%' . $filterColor . '%';
    }
    if ($filterKategori !== '') {
        if ($filterKategori === 'MIX') {
            $whereExtra[] = "k.jumlah_kategori = 2";
        } elseif ($filterKategori === 'LAB') {
            $whereExtra[] = "(k.jumlah_kategori = 1 AND k.jenis_kategori = 'LAB')";
        } elseif ($filterKategori === 'LA') {
            $whereExtra[] = "(k.jumlah_kategori = 1 AND k.jenis_kategori = 'LA')";
        }
    }
    // Client-side mode: hanya filter utama di server (permission)
    // Keyword search dan pagination ditangani oleh DataTables di sisi klien

    if ($isRestricted && !empty($allowedRoutings)) {
        $inPlaceholders = [];
        foreach ($allowedRoutings as $i => $rtg) {
            $key = ':ar_rtg_' . $i;
            $inPlaceholders[] = $key;
            $params[$key] = $rtg;
        }
        $whereExtra[] = "({$filterRoutingPosisiExpr} IN (" . implode(', ', $inPlaceholders) . "))";
    }

    $whereRestricted = [];
    if ($isRestricted && !empty($allowedRoutings)) {
        $inPlaceholders = [];
        foreach ($allowedRoutings as $i => $rtg) {
            $key = ':ar_rtg_' . $i;
            $inPlaceholders[] = $key;
            $paramsRestricted[$key] = $rtg;
        }
        $whereRestricted[] = "({$filterRoutingPosisiExpr} IN (" . implode(', ', $inPlaceholders) . "))";
    }

    $restrictedClause = count($whereRestricted) ? ' AND ' . implode(' AND ', $whereRestricted) : '';
    $extraClause = count($whereExtra) ? ' AND ' . implode(' AND ', $whereExtra) : '';

    $bindParams = static function (PDOStatement $stmt, array $bindValues): void {
        foreach ($bindValues as $key => $value) {
            if ($value === null) {
                $stmt->bindValue($key, null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
    };

    // --- CACHING LOGIC ---
    $cacheDir = __DIR__ . '/cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }

    // Cache key sederhana: tidak perlu search/order/paging karena client-side
    $cacheKeyData = [
        'version' => 2,
        'routing' => $filterRouting,
        'planType' => $selectedPlanType,
        'masterRouting' => md5(implode('|', $masterRoutingCodes) . '|' . implode('|', $masterRoutingCodesNormalized)),
        'group' => $groupId ?? 0,
        'user' => (!empty($isRestricted) && $isRestricted) ? ($currUser ?? '') : 'ALL'
    ];
    $cacheKey = md5(json_encode($cacheKeyData));
    $cacheFile = $cacheDir . '/search_cp_' . $cacheKey . '.json';
    $cacheTime = 120; // Smart TTL: 2 menit
    $forceRefresh = (isset($_POST['force_refresh']) && $_POST['force_refresh'] == '1');

    if (!$forceRefresh && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
        $cachedData = @file_get_contents($cacheFile);
        if ($cachedData) {
            $decoded = json_decode($cachedData, true);
            if ($decoded && is_array($decoded)) {
                echo json_encode($decoded);
                exit;
            }
        }
    }
    // --- END CACHING LOGIC ---

    try {
        // Client-Side mode: ambil SEMUA data sekaligus, tanpa COUNT & tanpa LIMIT
        $dataSql = $baseCte
            . "SELECT * FROM (" . $baseSelect . $extraClause . ") AS data_main"
            . " ORDER BY no_cp ASC";
        $stmtData = $conn3->prepare($dataSql);
        $bindParams($stmtData, $params);
        $stmtData->execute();
        $rows = $stmtData->fetchAll(PDO::FETCH_ASSOC);

        $data = [];
        $fmtText = static function ($value): string {
            $text = trim((string)($value ?? ''));
            if ($text === '') {
                $text = '-';
            }
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        };

        foreach ($rows as $r) {
            $tglCp = !empty($r['tgl_cp']) ? date('d-m-Y', strtotime((string)$r['tgl_cp'])) : '-';
            $qty = cpPlannerFormatQtyDisplay($r['qty'] ?? null, 2, '-');

            $katRaw = strtoupper(trim((string)($r['kategori_penimbangan'] ?? '')));
            if (!in_array($katRaw, ['LAB', 'LA', 'MIX'], true)) {
                $katRaw = '';
            }

            if ($katRaw === 'LAB') {
                $badge = '<span class="badge badge-pill badge-lab">LAB</span>';
            } elseif ($katRaw === 'LA') {
                $badge = '<span class="badge badge-pill badge-la">LA</span>';
            } elseif ($katRaw === 'MIX') {
                $badge = '<span class="badge badge-pill badge-mix">MIX</span>';
            } else {
                $badge = '<span class="badge badge-pill badge-secondary">-</span>';
            }

            $data[] = [
                $fmtText($r['no_cp'] ?? null),
                $tglCp,
                $fmtText($r['routing_code'] ?? null),
                $fmtText($r['current_routing'] ?? null),
                $fmtText($r['product_code'] ?? null),
                $fmtText($r['product_name'] ?? null),
                $fmtText($r['posisi_hari_ini_routing'] ?? null),
                $fmtText($r['work_center_code'] ?? null),
                $fmtText($r['work_center_name'] ?? null),
                $fmtText($r['kode_lab'] ?? null),
                $fmtText($r['color_name'] ?? null),
                $fmtText($r['label'] ?? null),
                $fmtText($r['cust_color'] ?? null),
                $fmtText($r['handfeel_code'] ?? null),
                $fmtText($r['handfeel_name'] ?? null),
                $fmtText($r['brand_code'] ?? null),
                $fmtText($r['brand_name'] ?? null),
                $fmtText($r['grade'] ?? null),
                $fmtText($r['product_type'] ?? null),
                $fmtText($r['product_structure_code'] ?? null),
                $fmtText($r['product_structure_name'] ?? null),
                $fmtText($r['material'] ?? null),
                $qty,
                $fmtText($r['next_routing'] ?? null),
                $fmtText($r['vlot'] ?? null),
                $badge,
                $fmtText($r['previous_routing'] ?? null),
                $fmtText($r['posisi_hari_ini_routing'] ?? null),
                $fmtText($r['next_routing_harian'] ?? null),
                $katRaw,
                $fmtText($r['lokasi_paddry'] ?? null),
                $fmtText($r['lokasi_bakar_bulu'] ?? null),
                $fmtText($r['lokasi_scouring'] ?? null),
                $fmtText($r['lokasi_presett'] ?? null),
                $fmtText($r['lokasi_cpb'] ?? null),
                $fmtText($r['lokasi_washing'] ?? null),
                $fmtText($r['lokasi_jetdyeing'] ?? null),
            ];
        }

        $responseData = ['data' => $data];

        // Simpan hasil ke cache JSON file
        @file_put_contents($cacheFile, json_encode($responseData));

        echo json_encode($responseData);
    } catch (Throwable $e) {
        echo json_encode([
            'data' => [],
            'error' => $e->getMessage(),
        ]);
    }
    exit;
}

include '../../../includes/header.php';
include '../../../includes/sidebar.php';

$themeColor = $_SESSION['Theme'] ?? 'primary';

date_default_timezone_set('Asia/Jakarta');
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

$dayIndex = (int)date('w');
$dayName = $dayNames[$dayIndex] ?? '';
$day = (int)date('j');
$month = (int)date('n');
$year = (int)date('Y');
$periodText = $dayName . ', ' . $day . ' ' . ($monthNames[$month] ?? '') . ' ' . $year;
?>

<style>
    .cp-card-header-main {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    .cp-card-title {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 700;
    }

    .cp-label {
        margin-bottom: 4px;
        font-weight: 700;
        font-size: 0.82rem;
        color: #1f2937;
    }

    .cp-btn-update-all {
        margin-left: 16px !important;
        border-radius: 0.2rem !important;
    }

    .cp-btn-export-wip {
        white-space: nowrap;
        font-weight: 600;
    }

    .cp-btn-export-all {
        white-space: nowrap;
        font-weight: 600;
        margin-right: 8px;
    }

    .cp-period-text {
        font-size: 0.9rem;
        color: #111827;
        margin-top: 6px;
    }

    .cp-layout {
        border: 1px solid #dee2e6;
        border-radius: 5px;
        overflow: visible;
        min-height: 440px;
    }

    .cp-machine-panel {
        border-right: 1px solid #dee2e6;
        background: #fbfcfe;
    }

    @media (min-width: 768px) {
        .cp-layout > .cp-machine-panel {
            flex: 0 0 15%;
            max-width: 15%;
        }

        .cp-layout > .cp-main-panel {
            flex: 0 0 85%;
            max-width: 85%;
        }
    }

    .cp-machine-title,
    .cp-main-title {
        padding: 8px 10px;
        font-size: 0.92rem;
        font-weight: 700;
        border-bottom: 1px solid #dee2e6;
        background: #f3f4f6;
    }

    .cp-main-title {
        color: #0a58ca;
    }

    .cp-main-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        min-height: 42px;
    }

    .cp-main-heading {
        display: flex;
        flex-direction: column;
        gap: 1px;
        min-width: 0;
    }

    .cp-capacity-line {
        font-size: 0.84rem;
        font-weight: 700;
        color: #1f2937;
        letter-spacing: 0.01em;
    }

    .cp-capacity-value {
        font-weight: 700;
    }

    .cp-capacity-value.cp-capacity-over {
        color: #198754;
    }

    .cp-capacity-value.cp-capacity-under {
        color: #dc3545;
    }

    .cp-capacity-modal .modal-content {
        border: 0;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 20px 45px rgba(15, 23, 42, 0.28);
    }

    .cp-capacity-modal .modal-header {
        border-bottom: 0;
        padding: 14px 16px 10px;
        background: linear-gradient(180deg, #fff8e6 0%, #ffffff 100%);
    }

    .cp-capacity-modal .modal-body {
        padding: 10px 16px 8px;
    }

    .cp-capacity-modal .modal-footer {
        border-top: 0;
        padding: 10px 16px 14px;
    }

    .cp-capacity-head {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .cp-capacity-icon {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #b45309;
        background: #ffedd5;
        border: 1px solid #fcd9a8;
        font-size: 0.98rem;
    }

    .cp-capacity-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: #111827;
        line-height: 1.2;
    }

    .cp-capacity-subtitle {
        margin-top: 2px;
        font-size: 0.82rem;
        color: #8a5a12;
        font-weight: 600;
    }

    .cp-capacity-close {
        opacity: 0.6;
        transition: opacity 0.16s ease;
    }

    .cp-capacity-close:hover {
        opacity: 1;
    }

    .cp-capacity-summary {
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        background: #f8fafc;
        padding: 10px 12px;
    }

    .cp-capacity-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 14px;
        font-size: 0.86rem;
    }

    .cp-capacity-row + .cp-capacity-row {
        margin-top: 7px;
        padding-top: 7px;
        border-top: 1px dashed #d5dbe5;
    }

    .cp-capacity-row span {
        color: #6b7280;
    }

    .cp-capacity-row strong {
        color: #111827;
        font-weight: 700;
        text-align: right;
    }

    .cp-capacity-question {
        margin-top: 12px;
        font-size: 0.9rem;
        font-weight: 700;
        color: #1f2937;
    }

    .cp-capacity-modal .btn {
        min-width: 118px;
        border-radius: 9px;
        font-weight: 600;
    }

    .cp-capacity-modal .btn-keep-save {
        color: #fff;
        border-color: #198754;
        background: linear-gradient(180deg, #2eb872 0%, #198754 100%);
    }

    .cp-capacity-modal .btn-keep-save:hover {
        border-color: #157347;
        background: linear-gradient(180deg, #27a767 0%, #157347 100%);
    }

    .cp-action-buttons {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
        padding: 4px 6px;
        border: 1px solid #d8dee7;
        border-radius: 10px;
        background: linear-gradient(145deg, #ffffff 0%, #f7f9fc 100%);
        box-shadow: inset 0 0 0 1px #eef2f7, 0 1px 3px rgba(15, 23, 42, 0.06);
    }

    .cp-action-buttons .btn {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 7px;
        transition: transform 0.14s ease, box-shadow 0.14s ease, background-color 0.14s ease, color 0.14s ease, border-color 0.14s ease;
    }

    .cp-action-buttons .btn i {
        font-size: 0.82rem;
    }

    .cp-action-buttons .btn + .btn {
        margin-left: 0;
    }

    .cp-action-buttons .btn-group {
        display: inline-flex;
        vertical-align: middle;
        margin-left: 0;
    }

    .cp-action-buttons .btn-group + .btn,
    .cp-action-buttons .btn + .btn-group,
    .cp-action-buttons .btn-group + .btn-group {
        margin-left: 0;
    }

    .cp-action-buttons .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(15, 23, 42, 0.14);
    }

    .cp-action-buttons .btn:focus {
        box-shadow: 0 0 0 0.18rem rgba(59, 130, 246, 0.2);
    }

    .cp-action-divider {
        width: 1px;
        height: 22px;
        background: #d5dce8;
        border-radius: 999px;
        margin: 0 1px;
    }

    .cp-action-buttons .btn-fill-speed {
        width: auto;
        min-width: 108px;
        height: 32px;
        padding: 0 11px;
        font-size: 0.72rem;
        font-weight: 600;
        border-radius: 7px;
    }

    .cp-action-buttons .btn-report-fail {
        width: auto;
        min-width: 88px;
        height: 32px;
        padding: 0 10px;
        gap: 5px;
        font-size: 0.72rem;
        font-weight: 700;
        white-space: nowrap;
        border-color: #f1a6ab;
        background: linear-gradient(145deg, #fff6f7 0%, #fff0f1 100%);
        color: #c62839;
    }

    .cp-action-buttons .btn-report-fail:hover {
        border-color: #dc3545;
        background: linear-gradient(145deg, #dc3545 0%, #c82333 100%);
        color: #ffffff;
    }

    .cp-action-buttons .btn-update-position {
        width: auto;
        min-width: 74px;
        height: 32px;
        padding: 0 10px;
        gap: 5px;
        font-size: 0.72rem;
        font-weight: 700;
        white-space: nowrap;
        border-color: #8fd0ef;
        background: linear-gradient(145deg, #f1fbff 0%, #e6f6ff 100%);
        color: #1178a8;
    }

    .cp-action-buttons .btn-update-position:hover {
        border-color: #0ea5e9;
        background: linear-gradient(145deg, #0ea5e9 0%, #0284c7 100%);
        color: #ffffff;
    }

    .cp-action-buttons .btn-update-position:disabled {
        transform: none;
        box-shadow: none;
    }

    .cp-action-buttons .btn-move-date {
        width: auto;
        min-width: 108px;
        height: 32px;
        padding: 0 10px;
        gap: 5px;
        font-size: 0.72rem;
        font-weight: 700;
        white-space: nowrap;
        border-color: #8fd0ef;
        background: linear-gradient(145deg, #f1fbff 0%, #e6f6ff 100%);
        color: #1178a8;
    }

    .cp-action-buttons .btn-move-date:hover {
        border-color: #0ea5e9;
        background: linear-gradient(145deg, #0ea5e9 0%, #0284c7 100%);
        color: #ffffff;
    }

    .cp-action-buttons #btnImportExcel {
        border-color: #92bfff;
        background: #eef5ff;
    }

    .cp-action-buttons #btnImportExcel:hover {
        background: #0d6efd;
        border-color: #0d6efd;
        color: #ffffff;
    }

    .cp-action-buttons #btnExportExcel {
        border-color: #7fd49a;
        background: #effbf3;
    }

    .cp-action-buttons #btnExportExcel:hover {
        background: #198754;
        border-color: #198754;
        color: #ffffff;
    }

    .cp-action-buttons #btnDelete {
        border-color: #efb0b8;
        color: #c0394a;
        background: #fff5f6;
    }

    .cp-action-buttons #btnDelete:hover {
        border-color: #dc3545;
        background: #dc3545;
        color: #ffffff;
    }

    .cp-action-buttons #btnCommitPending {
        border-color: #66bb8a;
        background: #edf9f1;
    }

    .cp-action-buttons #btnCancelPending {
        border-color: #efb0b8;
        background: #fff2f3;
    }

    .cp-mode-menu .dropdown-item {
        font-size: 0.79rem;
        padding: 0.38rem 0.85rem;
    }

    .cp-mode-menu .dropdown-item.active,
    .cp-mode-menu .dropdown-item:active {
        background: #e8f1ff;
        color: #0a58ca;
    }

    #machineList {
        max-height: 520px;
        overflow-y: auto;
        padding: 0;
    }

    .machine-item {
        width: 100%;
        border: 0;
        border-bottom: 1px solid #e5e7eb;
        background: #fff;
        text-align: left;
        padding: 10px 10px;
        cursor: pointer;
        transition: background 0.2s ease;
    }

    .machine-item:hover {
        background: #f3f8ff;
    }

    .machine-item.active {
        background: #e8f1ff;
        box-shadow: inset 3px 0 0 #007bff;
    }

    .machine-code {
        display: block;
        font-weight: 700;
        color: #0056b3;
        font-size: 0.9rem;
    }

    .machine-name {
        display: block;
        color: #111827;
        font-size: 0.9rem;
        margin-top: 2px;
    }

    .cp-main-panel {
        background: #ffffff;
    }

    .cp-empty {
        min-height: 370px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: #6b7280;
        font-size: 1.15rem;
    }

    .cp-detail {
        padding: 14px;
    }

    .cp-detail-label {
        display: block;
        font-size: 0.78rem;
        color: #6b7280;
        margin-bottom: 3px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .cp-detail-value {
        display: block;
        padding: 7px 9px;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        min-height: 34px;
        background: #fff;
        font-size: 0.92rem;
        color: #111827;
    }

    .cp-table-wrap {
        border-top: 1px solid #dee2e6;
        background: #fff;
    }

    .cp-table-scroll-top {
        display: none;
        height: 16px;
        overflow-x: auto;
        overflow-y: hidden;
        border-bottom: 1px solid #dee2e6;
        background: #f8fafc;
    }

    .cp-table-scroll-top-inner {
        height: 1px;
    }

    .cp-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }

    .cp-table thead th {
        background: #f1f3f5;
        border: 1px solid #dee2e6;
        padding: 6px 8px;
        text-align: center;
        white-space: nowrap;
        position: relative;
    }

    .cp-table tbody td {
        border: 1px solid #dee2e6;
        padding: 6px 8px;
        white-space: nowrap;
    }

    .cp-table tbody tr.row-pending td {
        background: #fffbe8;
    }

    .cp-table tbody tr.row-late-move-source td {
        background: #fff6bf;
    }

    .cp-table tbody tr.row-selected td {
        background: #dbeafe;
    }

    .cp-table tbody tr.row-late-move-source.row-selected td {
        background: #ffe08a;
    }

    .cp-table tbody tr.row-pending.row-selected td {
        background: #fde68a;
    }

    .cp-table tbody tr.row-actual-celup-missing td {
        background: inherit;
    }

    .cp-table tbody tr.row-actual-celup-missing.row-selected td {
        background: inherit;
    }

    .cp-table tbody tr.cp-total-row td {
        background: #eef2f7;
        font-weight: 700;
    }

    /* Total row: pertahankan sticky di 2 kolom kiri agar total selalu terlihat
       saat tabel digeser horizontal. */
    #cpMainTable tbody tr.cp-total-row td:nth-child(1),
    #cpMainTable tbody tr.cp-total-row td:nth-child(2) {
        background: #eef2f7;
        z-index: 9;
    }

    #cpMainTable tbody tr.cp-total-row td:nth-child(2) {
        box-shadow: 2px 0 0 #dee2e6;
    }

    /* Kolom selain sticky pada baris total tidak perlu sticky. */
    #cpMainTable tbody tr.cp-total-row td:nth-child(n+3) {
        position: static !important;
        left: auto !important;
        z-index: auto !important;
        box-shadow: none !important;
    }

    #cpMainTable tbody tr.cp-total-row td.cp-total-label-cell {
        padding-left: 12px;
        white-space: nowrap;
        text-align: left;
    }

    #cpTableBody tr[data-row-id] {
        cursor: pointer;
    }

    .cp-table .cp-cell-input {
        min-width: 70px;
        height: 24px;
        font-size: 0.78rem;
        padding: 2px 6px;
        border-radius: 2px;
    }

    .cp-table .cp-cell-input.cp-cell-readonly {
        background: #f8fafc;
        color: #4b5563;
        cursor: not-allowed;
    }

    .cp-table .main-row-checkbox {
        width: 14px;
        height: 14px;
        vertical-align: middle;
        margin-right: 4px;
    }

    .cp-table .main-row-check-all {
        width: 14px;
        height: 14px;
        vertical-align: middle;
        margin-right: 4px;
    }

    .cp-table .cp-cell-input.plan-desc {
        min-width: 130px;
    }

    .cp-table .cp-cell-input.plan-paddry {
        min-width: 115px;
    }

    .cp-table .cp-cell-input.plan-machine {
        min-width: 115px;
    }

    .cp-table .cp-cell-input.posisi-hari-ini {
        min-width: 180px;
    }

    .cp-table .text-left {
        text-align: left;
    }

    .cp-col-resizer {
        position: absolute;
        top: 0;
        right: -4px;
        width: 8px;
        height: 100%;
        cursor: col-resize;
        user-select: none;
        touch-action: none;
        z-index: 20;
    }

    .cp-col-resizer::after {
        content: '';
        position: absolute;
        left: 50%;
        top: 20%;
        bottom: 20%;
        width: 1px;
        transform: translateX(-50%);
        background: transparent;
        transition: background-color 0.15s ease;
    }

    #cpMainTableHead th:hover .cp-col-resizer::after,
    .cp-col-resizer.active::after {
        background: #38bdf8;
    }

    body.cp-col-resizing {
        cursor: col-resize !important;
        user-select: none !important;
    }

    :root {
        --cp-sticky-seq-width: 62px;
        --cp-main-sticky-top: 0px;
        --cp-main-toolbar-height: 42px;
        --cp-main-scroll-top-height: 0px;
        --cp-main-head-row1-height: 34px;
        --cp-main-table-max-height: 420px;
    }

    .cp-main-toolbar {
        position: sticky;
        top: var(--cp-main-sticky-top);
        z-index: 26;
        box-shadow: 0 1px 0 #dee2e6;
    }

    #cpMainTableScrollTop {
        position: sticky;
        top: calc(var(--cp-main-sticky-top) + var(--cp-main-toolbar-height));
        z-index: 25;
    }

    #cpMainTableResponsive {
        overflow-y: auto;
        max-height: var(--cp-main-table-max-height);
        overscroll-behavior: contain;
    }

    #cpMainTable thead th {
        background: #f1f3f5;
    }

    #cpMainTable thead th[rowspan]:not([rowspan="1"]) {
        vertical-align: top;
    }

    #cpMainTable thead tr:first-child th {
        position: sticky;
        top: 0;
        z-index: 13;
    }

    #cpMainTable thead tr:nth-child(2) th {
        position: sticky;
        top: var(--cp-main-head-row1-height);
        z-index: 12;
    }

    #cpMainTable thead tr:first-child th:nth-child(1),
    #cpMainTable tbody td:nth-child(1) {
        position: sticky;
        left: 0;
        min-width: var(--cp-sticky-seq-width);
        max-width: var(--cp-sticky-seq-width);
    }

    #cpMainTable thead tr:first-child th:nth-child(2),
    #cpMainTable tbody td:nth-child(2) {
        position: sticky;
        left: var(--cp-sticky-seq-width);
    }

    #cpMainTable thead tr:first-child th:nth-child(1),
    #cpMainTable thead tr:first-child th:nth-child(2) {
        z-index: 14;
        background: #f1f3f5;
    }

    #cpMainTable tbody td:nth-child(1),
    #cpMainTable tbody td:nth-child(2) {
        z-index: 6;
        background: #fff;
    }

    #cpMainTable thead tr:first-child th:nth-child(2),
    #cpMainTable tbody td:nth-child(2) {
        box-shadow: 2px 0 0 #dee2e6;
    }

    #cpMainTable tbody tr.row-pending td:nth-child(1),
    #cpMainTable tbody tr.row-pending td:nth-child(2) {
        background: #fffbe8;
    }

    #cpMainTable tbody tr.row-late-move-source td:nth-child(1),
    #cpMainTable tbody tr.row-late-move-source td:nth-child(2) {
        background: #fff6bf;
    }

    #cpMainTable tbody tr.row-selected td:nth-child(1),
    #cpMainTable tbody tr.row-selected td:nth-child(2) {
        background: #dbeafe;
    }

    #cpMainTable tbody tr.row-late-move-source.row-selected td:nth-child(1),
    #cpMainTable tbody tr.row-late-move-source.row-selected td:nth-child(2) {
        background: #ffe08a;
    }

    #cpMainTable tbody tr.row-pending.row-selected td:nth-child(1),
    #cpMainTable tbody tr.row-pending.row-selected td:nth-child(2) {
        background: #fde68a;
    }

    #cpMainTable tbody tr.row-actual-celup-missing td:nth-child(1),
    #cpMainTable tbody tr.row-actual-celup-missing td:nth-child(2),
    #cpMainTable tbody tr.row-pending.row-actual-celup-missing td:nth-child(1),
    #cpMainTable tbody tr.row-pending.row-actual-celup-missing td:nth-child(2) {
        background: #fca5a5;
    }

    #cpMainTable tbody tr.row-actual-celup-missing.row-selected td:nth-child(1),
    #cpMainTable tbody tr.row-actual-celup-missing.row-selected td:nth-child(2),
    #cpMainTable tbody tr.row-pending.row-actual-celup-missing.row-selected td:nth-child(1),
    #cpMainTable tbody tr.row-pending.row-actual-celup-missing.row-selected td:nth-child(2) {
        background: #f87171;
    }

    #cpMainTable tbody tr.row-late-move-source.row-actual-celup-missing td:nth-child(1),
    #cpMainTable tbody tr.row-late-move-source.row-actual-celup-missing td:nth-child(2),
    #cpMainTable tbody tr.row-pending.row-late-move-source.row-actual-celup-missing td:nth-child(1),
    #cpMainTable tbody tr.row-pending.row-late-move-source.row-actual-celup-missing td:nth-child(2) {
        background: #fff6bf;
    }

    #cpMainTable tbody tr.row-late-move-source.row-actual-celup-missing.row-selected td:nth-child(1),
    #cpMainTable tbody tr.row-late-move-source.row-actual-celup-missing.row-selected td:nth-child(2),
    #cpMainTable tbody tr.row-pending.row-late-move-source.row-actual-celup-missing.row-selected td:nth-child(1),
    #cpMainTable tbody tr.row-pending.row-late-move-source.row-actual-celup-missing.row-selected td:nth-child(2) {
        background: #ffe08a;
    }

    .cp-plan-drawer-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(17, 24, 39, 0.22);
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s ease;
        z-index: 1030;
    }

    .cp-plan-drawer-backdrop.show {
        opacity: 1;
        pointer-events: auto;
    }

    .cp-plan-drawer {
        position: fixed;
        top: 0;
        right: -340px;
        width: 320px;
        max-width: calc(100vw - 24px);
        height: 100vh;
        background: #fff;
        border-left: 1px solid #dee2e6;
        box-shadow: -6px 0 20px rgba(0, 0, 0, 0.12);
        z-index: 1040;
        transition: right 0.25s ease;
        display: flex;
        flex-direction: column;
    }

    .cp-plan-drawer.open {
        right: 0;
    }

    .cp-plan-drawer-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 14px;
        border-bottom: 1px solid #dee2e6;
    }

    .cp-plan-drawer-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
    }

    .cp-plan-drawer-list {
        padding: 10px;
        overflow-y: auto;
    }

    .plan-type-item {
        width: 100%;
        border: 1px solid #dee2e6;
        background: #fff;
        border-radius: 4px;
        text-align: left;
        padding: 8px 10px;
        margin-bottom: 8px;
        cursor: pointer;
        font-size: 0.9rem;
    }

    .plan-type-item:hover {
        background: #f4f9ff;
        border-color: #bad8ff;
    }

    .plan-type-item.active {
        background: #eaf2ff;
        border-color: #86b7fe;
        color: #0b5ed7;
        font-weight: 700;
    }

    .cp-inline-muted {
        color: #6b7280;
        font-size: 0.88rem;
        margin-top: 6px;
    }

    .cp-search-modal .cp-search-dialog {
        max-width: 1080px;
    }

    .cp-search-modal .modal-content {
        border: 1px solid #cfd4da;
        border-radius: 0;
        background: #f7f7f7;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.22);
    }

    .cp-search-modal .cp-search-header {
        background: #eceff3;
        border-bottom: 1px solid #cfd4da;
        padding: 10px 14px;
    }

    .cp-search-modal .cp-search-header .modal-title {
        margin: 0;
        color: #0f172a;
        font-size: 1.02rem;
        font-weight: 700;
    }

    .cp-search-modal .cp-search-close {
        color: #6b7280;
        opacity: 1;
        text-shadow: none;
        outline: 0;
    }

    .cp-search-modal .modal-body {
        padding: 10px 12px 6px;
        background: #f7f7f7;
    }

    .cp-search-toolbar {
        display: flex;
        align-items: flex-end;
        justify-content: flex-start;
        gap: 10px;
        margin-bottom: 8px;
        flex-wrap: wrap;
    }

    .cp-search-toolbar-left {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: nowrap;
        width: 100%;
    }

    .cp-search-inline-label {
        margin: 0;
        font-size: 0.82rem;
        font-weight: 700;
        color: #374151;
        white-space: nowrap;
    }

    .cp-search-select {
        min-width: 138px;
    }

    .cp-search-input {
        min-width: 215px;
        flex: 1 1 auto;
    }

    #btnCpSearchSelectAll {
        white-space: nowrap;
        min-width: 86px;
    }

    .cp-search-table-wrap {
        border: 1px solid #cfd4da;
        background: #ffffff;
        overflow-x: auto;
        max-height: 500px;
        overflow-y: auto;
    }

    .cp-search-table-wrap .table {
        margin-bottom: 0;
        table-layout: fixed;
    }

    #cpSearchTable {
        min-width: auto;
        width: 100%;
    }

    #cpSearchTable .cp-select-col {
        width: 72px !important;
        min-width: 72px;
        max-width: 72px;
        text-align: center;
    }

    #cpSearchTable .cp-row-check {
        pointer-events: none;
    }

    #cpSearchTable .cp-seq-wrap {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    #cpSearchTable .cp-row-seq {
        display: inline-block;
        min-width: 18px;
        text-align: right;
        color: #374151;
        font-size: 0.76rem;
    }

    #cpSearchTable thead th {
        background: #edf1f5;
        border-color: #cfd4da;
        font-size: 0.78rem;
        color: #0f172a;
        white-space: nowrap;
        padding: 7px 8px;
        text-align: center !important;
        vertical-align: middle !important;
    }

    /* DataTables (scrollX) render header di table clone dalam wrapper */
    #cpSearchTable_wrapper .dataTables_scrollHead th,
    #cpSearchTable_wrapper .dataTables_scrollHeadInner th,
    #cpSearchTable_wrapper table.dataTable thead th {
        text-align: center !important;
        vertical-align: middle !important;
    }

    #cpSearchTable tbody td {
        border-color: #d9dde2;
        font-size: 0.79rem;
        white-space: nowrap;
        padding: 6px 8px;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    #cpSearchTable tbody td.long-text-col {
        max-width: 200px;
    }

    #cpSearchTable tbody tr:hover td {
        background: #fff8eb;
    }

    #cpSearchTable tbody tr.table-active td {
        background: #fff0db !important;
        border-top-color: #de8c41;
        border-bottom-color: #de8c41;
    }

    .cp-search-modal .dataTables_scrollHead th {
        background: #edf1f5;
        border-color: #cfd4da;
        color: #0f172a;
        font-size: 0.78rem;
        white-space: nowrap !important;
        line-height: 1.25;
        padding: 6px 8px !important;
        vertical-align: middle;
    }

    .cp-search-modal .dataTables_scrollBody td {
        border-color: #d9dde2;
        font-size: 0.79rem;
        white-space: nowrap !important;
        line-height: 1.25;
        padding: 6px 8px !important;
        vertical-align: middle;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .cp-search-modal .dataTables_scrollHead table,
    .cp-search-modal .dataTables_scrollBody table {
        border-collapse: separate !important;
        border-spacing: 0;
        table-layout: fixed !important;
        margin: 0 !important;
    }

    .cp-search-modal .dataTables_wrapper .dataTables_filter {
        display: none;
    }

    .cp-search-modal .dataTables_wrapper .dataTables_info,
    .cp-search-modal .dataTables_wrapper .dataTables_length,
    .cp-search-modal .dataTables_wrapper .dataTables_paginate {
        padding-top: 8px;
        font-size: 0.78rem;
    }

    .cp-search-modal .dataTables_wrapper .dataTables_paginate .paginate_button {
        padding: 0.2em 0.65em;
    }

    .cp-search-modal .modal-footer {
        border-top: 1px solid #cfd4da;
        padding: 9px 12px;
        background: #f2f4f7;
    }

    @media (max-width: 767.98px) {
        .cp-main-toolbar {
            align-items: flex-start;
            flex-direction: column;
        }

        .cp-main-heading {
            width: 100%;
        }

        .cp-action-buttons {
            width: 100%;
            justify-content: flex-start;
        }

        .cp-action-divider {
            display: none;
        }

        .cp-layout {
            min-height: unset;
        }

        .cp-machine-panel {
            border-right: 0;
            border-bottom: 1px solid #dee2e6;
        }

        #machineList {
            max-height: 280px;
        }

        .cp-empty {
            min-height: 220px;
            font-size: 1rem;
        }

        .cp-search-toolbar-left {
            width: 100%;
            flex-wrap: wrap;
        }

        .cp-search-select,
        .cp-search-input {
            width: 100%;
            min-width: 0;
        }
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>CP Planning</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="/gg_app/pages/planning/index.php">Planning</a></li>
                        <li class="breadcrumb-item active">CP Planning</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <div class="cp-card-header-main">
                        <h3 class="cp-card-title">CP Planning</h3>
                        <div class="d-flex align-items-center">
                            <button type="button" id="btnExportPlanningAll" class="btn btn-outline-light btn-sm cp-btn-export-all" title="Export semua planning per machine pada tanggal terpilih">
                                <i class="fas fa-file-excel"></i>
                                <span class="ml-1">Export All Planning</span>
                            </button>
                            <button type="button" id="btnExportWip" class="btn btn-outline-light btn-sm cp-btn-export-wip" title="Export semua data Search CP and Routing">
                                <i class="fas fa-file-export"></i>
                                <span class="ml-1">Export WIP</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div id="cpAlert" class="alert d-none py-2 mb-3" role="alert"></div>

                    <div class="form-row mb-3">
                        <div class="form-group col-md-3 mb-2">
                            <label class="cp-label" for="periodDate">Periode</label>
                            <input type="date" id="periodDate" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                            <div class="cp-period-text" id="periodText"><?= htmlspecialchars($periodText) ?></div>
                        </div>
                        <div class="form-group col-md-4 mb-2">
                            <label for="selectedPlanType" class="cp-label">Tipe Planning</label>
                            <div class="input-group input-group-sm">
                                <input type="text" id="selectedPlanType" class="form-control" placeholder="Klik tombol list untuk pilih tipe" readonly>
                                <div class="input-group-append">
                                    <button type="button" id="btnOpenPlanTypeDrawer" class="btn btn-outline-secondary" title="Pilih Tipe Planning">
                                        <i class="fas fa-list"></i>
                                    </button>
                                    <button type="button" id="btnUpdateAllCurrentPosition" class="btn btn-outline-primary cp-btn-update-all" title="Update Posisi Hari Ini Semua Machine">
                                        <i class="fas fa-sync-alt"></i>
                                        <span class="ml-1">Update All</span>
                                    </button>
                                </div>
                            </div>
                            <div class="cp-inline-muted">Pilih tipe planning terlebih dahulu untuk menampilkan list machine.</div>
                        </div>
                    </div>

                    <div class="row no-gutters cp-layout">
                        <div class="col-md-3 cp-machine-panel">
                            <div class="cp-machine-title">Machine</div>
                            <div id="machineList"></div>
                        </div>
                        <div class="col-md-9 cp-main-panel">
                            <div class="cp-main-title cp-main-toolbar">
                                <div class="cp-main-heading">
                                    <div id="selectedMachineTitle">Machine: -</div>
                                    <div id="selectedMachineCapacity" class="cp-capacity-line">Capacity (Qty): 0/-</div>
                                </div>
                                <div class="cp-action-buttons" role="group" aria-label="CP Planning Actions">
                                    <div class="btn-group d-none" id="cpScheduleModeGroup">
                                        <button type="button" id="btnScheduleMode" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Mode input paddry: Otomatis">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-right cp-mode-menu" id="cpScheduleModeMenu">
                                            <button type="button" class="dropdown-item schedule-mode-item" data-mode="auto">
                                                Mode Otomatis
                                            </button>
                                            <button type="button" class="dropdown-item schedule-mode-item" data-mode="manual">
                                                Mode Manual
                                            </button>
                                        </div>
                                    </div>
                                    <button type="button" id="btnFillSameSpeed" class="btn btn-outline-primary btn-sm btn-fill-speed d-none" title="Isi semua speed baris bawah mengikuti speed baris pertama">
                                        Isi Speed Sama
                                    </button>
                                    <button type="button" id="btnImportExcel" class="btn btn-outline-primary btn-sm" title="Import Excel Paddry">
                                        <i class="fas fa-file-import"></i>
                                    </button>
                                    <button type="button" id="btnExportExcel" class="btn btn-outline-success btn-sm" title="Export Excel">
                                        <i class="fas fa-file-excel"></i>
                                    </button>
                                    <button type="button" id="btnGenerateFailReport" class="btn btn-outline-danger btn-sm btn-report-fail" title="Generate Report Fail CP">
                                        <i class="fas fa-file-alt"></i>
                                        <span>CP Result</span>
                                    </button>
                                    <button type="button" id="btnUpdateCurrentPosition" class="btn btn-sm btn-update-position" title="Update Posisi Hari Ini Permanen">
                                        <i class="fas fa-sync-alt"></i>
                                        <span>Update</span>
                                    </button>
                                    <span class="cp-action-divider" aria-hidden="true"></span>
                                    <button type="button" id="btnNew" class="btn btn-outline-secondary btn-sm" title="New">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button type="button" id="btnEdit" class="btn btn-outline-secondary btn-sm" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" id="btnDelete" class="btn btn-outline-secondary btn-sm" title="Delet">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button type="button" id="btnMoveDate" class="btn btn-sm btn-move-date" title="Pindah Tanggal">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span>Pindah</span>
                                    </button>
                                    <span class="cp-action-divider" aria-hidden="true"></span>
                                    <button type="button" id="btnBreakTime" class="btn btn-outline-secondary btn-sm" title="Breaktime">
                                        <i class="fas fa-clock"></i>
                                    </button>
                                    <button type="button" id="btnMoveUp" class="btn btn-outline-secondary btn-sm" title="Up">
                                        <i class="fas fa-arrow-up"></i>
                                    </button>
                                    <button type="button" id="btnMoveDown" class="btn btn-outline-secondary btn-sm" title="Down">
                                        <i class="fas fa-arrow-down"></i>
                                    </button>
                                    <button type="button" id="btnCommitPending" class="btn btn-outline-success btn-sm d-none" title="Simpan Permanen">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button type="button" id="btnCancelPending" class="btn btn-outline-danger btn-sm d-none" title="Batal Simpan">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="cp-table-wrap">
                                <input type="file" id="paddryImportFile" accept=".xlsx" class="d-none">
                                <div class="cp-table-scroll-top" id="cpMainTableScrollTop">
                                    <div class="cp-table-scroll-top-inner" id="cpMainTableScrollTopInner"></div>
                                </div>
                                <div class="table-responsive" id="cpMainTableResponsive">
                                    <table class="cp-table" id="cpMainTable">
                                        <thead id="cpMainTableHead">
                                            <tr>
                                                <th rowspan="2">Seq</th>
                                                <th rowspan="2">CP No</th>
                                                <th rowspan="2">Label Jual</th>
                                                <th rowspan="2">Cust Color</th>
                                                <th rowspan="2">Kode Lab</th>
                                                <th rowspan="2">Routing Name</th>
                                                <th rowspan="2">Qty</th>
                                                <th rowspan="2">Speed</th>
                                                <th rowspan="2">Material Name</th>
                                                <th colspan="8">Paddry Planning</th>
                                                <th rowspan="2">Plan Description</th>
                                                <th colspan="7">Actual</th>
                                                <th rowspan="2">Posisi Hari Ini</th>
                                                <th rowspan="2">Next Routing</th>
                                                <th rowspan="2">Down Time (Mnt)</th>
                                                <th rowspan="2">Sample</th>
                                                <th rowspan="2">Last Update</th>
                                                <th rowspan="2">Updated By</th>
                                            </tr>
                                            <tr>
                                                <th>Date</th>
                                                <th>Start</th>
                                                <th>End</th>
                                                <th>RKO</th>
                                                <th>Resp Lipat</th>
                                                <th>Status Resp</th>
                                                <th>VLot Resp</th>
                                                <th>Bon Resp</th>
                                                <th>Date</th>
                                                <th>Start</th>
                                                <th>End</th>
                                                <th>Shift</th>
                                                <th>VLot</th>
                                                <th>Sisa di Tanggal</th>
                                                <th>Sisa Saturator</th>
                                            </tr>
                                        </thead>
                                        <tbody id="cpTableBody">
                                            <tr>
                                                <td colspan="30" class="text-center text-muted">No Data</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="cp-plan-drawer-backdrop" id="planTypeBackdrop"></div>
<aside class="cp-plan-drawer" id="planTypeDrawer">
    <div class="cp-plan-drawer-header">
        <h4 class="cp-plan-drawer-title">Tipe Planning</h4>
        <button type="button" id="btnClosePlanTypeDrawer" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="cp-plan-drawer-list" id="planTypeList"></div>
</aside>

<div class="modal fade" id="breakTimeModal" tabindex="-1" role="dialog" aria-labelledby="breakTimeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title" id="breakTimeModalLabel">Break Time</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-row align-items-end mb-3">
                    <div class="form-group col-md-6 mb-2 mb-md-0">
                        <label for="breakTimeSearch" class="cp-label">Search</label>
                        <input type="text" id="breakTimeSearch" class="form-control form-control-sm" placeholder="Cari break time...">
                    </div>
                    <div class="form-group col-md-6 mb-2 mb-md-0 text-right">
                        <small class="text-muted" id="breakTimeCount">0 data</small>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm w-100" id="breakTimeTable">
                        <thead class="thead-light">
                            <tr>
                                <th style="width:42px;" class="text-center"></th>
                                <th style="width:60px;" class="text-center">No</th>
                                <th>Break Time</th>
                                <th style="width:120px;" class="text-center">Waktu (Menit)</th>
                                <th style="width:170px;">Last Update</th>
                                <th style="width:120px;">Updated By</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnSubmitBreakTime" class="btn btn-outline-primary btn-sm px-4" disabled>Submit</button>
                <button type="button" class="btn btn-outline-danger btn-sm px-4" data-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="moveDateModal" tabindex="-1" role="dialog" aria-labelledby="moveDateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title" id="moveDateModalLabel">Pindah Tanggal</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3" id="moveDateSelectedCount">0 data dipilih.</p>
                <div class="form-group">
                    <label for="moveDateTargetDate" class="cp-label">Tanggal Tujuan</label>
                    <input type="date" id="moveDateTargetDate" class="form-control form-control-sm">
                </div>
                <div class="form-group mb-0">
                    <label for="moveDateTargetMachine" class="cp-label">Machine Tujuan</label>
                    <select id="moveDateTargetMachine" class="form-control form-control-sm">
                        <option value="">Pilih machine tujuan</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnSubmitMoveDate" class="btn btn-outline-primary btn-sm px-4">Pindah</button>
                <button type="button" class="btn btn-outline-danger btn-sm px-4" data-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<style>
    /* =========================================
   LOADING OVERLAY (MODAL STYLE)
========================================= */
    #loadingOverlaySearch {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        border-radius: 0.3rem; 
    }

    #loadingOverlaySearch.active {
        display: flex !important;
    }

    .loading-box-search {
        background: white;
        padding: 30px 50px;
        border-radius: 20px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        min-width: 200px;
    }

    .loading-box-search .spinner-border {
        width: 3rem;
        height: 3rem;
        border-width: 0.25em;
        color: #3b82f6;
        margin-bottom: 15px;
    }

    .loading-box-search p {
        margin: 0;
        font-weight: 600;
        color: #1e293b;
        font-size: 1.1rem;
        font-family: 'Outfit', sans-serif;
    }

    .cp-update-loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.45);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1065;
    }

    .cp-update-loading-overlay.active {
        display: flex !important;
    }

    .cp-update-loading-box {
        background: #ffffff;
        border-radius: 16px;
        padding: 20px 24px;
        min-width: 280px;
        max-width: 90vw;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 16px 40px rgba(15, 23, 42, 0.22);
    }

    .cp-update-loading-box .spinner-border {
        width: 1.8rem;
        height: 1.8rem;
        color: #0ea5e9;
        flex: 0 0 auto;
    }

    .cp-update-loading-text {
        margin: 0;
        font-size: 0.94rem;
        font-weight: 600;
        color: #0f172a;
        line-height: 1.35;
    }

    .cp-update-success-icon {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin: 2px auto 12px;
        background: #eafaf1;
        color: #198754;
        font-size: 1.5rem;
    }

    /* Sembunyikan label bawaan DataTables */
    div.dataTables_processing {
        display: none !important;
    }

    /* Cegah teks panjang "nembus" kolom sebelah (modal Search CP) */
    #cpSearchTable th.cp-col-psname,
    #cpSearchTable td.cp-col-psname {
        min-width: 360px;
        max-width: 360px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
</style>

<div class="modal fade cp-search-modal" id="cpSearchModal" tabindex="-1" role="dialog" aria-labelledby="cpSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl cp-search-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header cp-search-header">
                <h5 class="modal-title" id="cpSearchModalLabel">Search CP and Routing</h5>
                <button type="button" class="close cp-search-close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="position: relative;">
                <!-- Overlay loading animasi di dalam modal_body -->
                <div id="loadingOverlaySearch">
                    <div class="loading-box-search">
                        <div class="spinner-border" role="status"></div>
                        <p>Memuat data...</p>
                    </div>
                </div>
                
                <div class="cp-search-toolbar">
                    <div class="cp-search-toolbar-left">
                        <label for="cpSearchField" class="cp-search-inline-label">Search For</label>
                        <select id="cpSearchField" class="form-control form-control-sm cp-search-select">
                            <option value="cp_no">CP No</option>
                            <option value="product_code">Product Code</option>
                            <option value="posisi_hari_ini">Posisi Hari Ini</option>
                            <option value="lokasi_jetdyeing">Lokasi Jet Dyeing</option>
                            <option value="lokasi_washing">Lokasi Washing</option>
                            <option value="lokasi_cpb">Lokasi CPB</option>
                            <option value="lokasi_presett">Lokasi PreSett</option>
                            <option value="lokasi_scouring">Lokasi Scouring</option>
                            <option value="lokasi_paddry">Lokasi Paddry</option>
                            <option value="lokasi_bakar_bulu">Lokasi Bakar Bulu</option>
                            <option value="color_name">Color Name</option>
                        </select>
                        <input type="text" id="cpSearchKeyword" class="form-control form-control-sm cp-search-input" placeholder="Ketik keyword pencarian">
                        <button type="button" id="btnCpSearchApply" class="btn btn-outline-secondary btn-sm">Search</button>
                        <button type="button" id="btnCpSearchReset" class="btn btn-outline-secondary btn-sm">Reset</button>
                        <button type="button" id="btnCpSearchSelectAll" class="btn btn-outline-primary btn-sm">Select All</button>
                    </div>
                </div>
                <div class="cp-search-table-wrap">
                    <table id="cpSearchTable" class="table table-bordered table-sm w-100 mb-0">
                        <thead>
                            <tr>
                                <th class="cp-select-col">Seq</th>
                                <th>CP No</th>
                                <th>CP Date</th>
                                <th>Product Code</th>
                                <th>Product Name</th>
                                <th>Posisi Hari Ini</th>
                                <th>Lokasi Jet Dyeing</th>
                                <th>Lokasi Washing</th>
                                <th>Lokasi CPB</th>
                                <th>Lokasi PreSett</th>
                                <th>Lokasi Scouring</th>
                                <th>Lokasi Paddry</th>
                                <th>Lokasi Bakar Bulu</th>
                                <th>Qty</th>
                                <th>Work Center Code</th>
                                <th>Work Center Name</th>
                                <th>Color Code</th>
                                <th>Color Name</th>
                                <th>Label Jual</th>
                                <th>Cust Color</th>
                                <th>HandFeel Code</th>
                                <th>HandFeel Name</th>
                                <th>Brand Code</th>
                                <th>Brand Name</th>
                                <th>Grade</th>
                                <th>Product Type</th>
                                <th>Product Structure Code</th>
                                <th>Product Structure Name</th>
                                <th>Material</th>
                                <th>Kategori Penimbangan</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnSubmitCpSearch" class="btn btn-outline-primary btn-sm px-4">Submit</button>
                <button type="button" class="btn btn-outline-danger btn-sm px-4" data-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade cp-capacity-modal" id="capacityOverLimitModal" tabindex="-1" role="dialog" aria-labelledby="capacityOverLimitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div class="cp-capacity-head">
                    <span class="cp-capacity-icon"><i class="fas fa-exclamation-triangle"></i></span>
                    <div>
                        <h5 class="cp-capacity-title" id="capacityOverLimitModalLabel">Konfirmasi Simpan Permanen</h5>
                        <div class="cp-capacity-subtitle">Qty melebihi max capacity machine</div>
                    </div>
                </div>
                <button type="button" class="close cp-capacity-close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="cp-capacity-summary">
                    <div class="cp-capacity-row">
                        <span>Machine</span>
                        <strong id="capacityOverLimitMachine">-</strong>
                    </div>
                    <div class="cp-capacity-row">
                        <span>Capacity (Qty)</span>
                        <strong id="capacityOverLimitQty">-</strong>
                    </div>
                </div>
                <div class="cp-capacity-question">Tetap simpan data permanen?</div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnCapacityKeepSave" class="btn btn-sm btn-keep-save px-3">Tetap Simpan</button>
                <button type="button" id="btnCapacityCancelSave" class="btn btn-outline-danger btn-sm px-3" data-dismiss="modal">Batal Simpan</button>
            </div>
        </div>
    </div>
</div>

<div id="positionUpdateLoadingOverlay" class="cp-update-loading-overlay" aria-hidden="true">
    <div class="cp-update-loading-box" role="status" aria-live="polite">
        <div class="spinner-border" aria-hidden="true"></div>
        <p id="positionUpdateLoadingText" class="cp-update-loading-text">Memproses update posisi saat ini...</p>
    </div>
</div>

<div class="modal fade" id="positionUpdateSuccessModal" tabindex="-1" role="dialog" aria-labelledby="positionUpdateSuccessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <div class="cp-update-success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h5 id="positionUpdateSuccessModalLabel" class="mb-2">Update Berhasil</h5>
                <div id="positionUpdateSuccessText" class="text-muted small mb-0">Posisi hari ini berhasil diperbarui.</div>
            </div>
            <div class="modal-footer justify-content-center pt-0">
                <button type="button" class="btn btn-success btn-sm px-4" data-dismiss="modal">OK</button>
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
    var apiUrl = '/gg_app/pages/planning/setting_master/set_ms_routing_api.php';
    var planTypeRows = [];
    var machineRows = [];
    var machineMaxCapacityMap = {};
    var selectedPlanType = '';
    var selectedMachineId = '';
    var breakTimeRows = [];
    var selectedBreakRows = {};
    var cpSearchTable = null;
    var selectedCpRows = {};
    var cpSearchDebounceTimer = null;
    var currentUser = <?= json_encode($_SESSION['UserName'] ?? 'SYSTEM') ?>;
    var mainTableRows = [];
    var selectedMainRowId = '';
    var selectedMainRowIds = {};
    var rowSequence = 0;
    var orderDirty = false;
    var orderSnapshotRowIds = [];
    var moveDateRowIds = [];
    var cpSearchForceRefresh = false;
    var cpSearchDuplicateFilterRegistered = false;
    var cpSearchExistingKeyMap = {};
    var isMainTableScrollSyncing = false;
    var paddryInputMode = 'auto';
    var capacityOverLimitConfirmBypass = false;
    var mainTableColumnWidthsByLayout = {};
    var activeMainTableResize = null;

    function safeText(value) {
        return value == null ? '' : String(value).trim();
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function normalizeKategoriPenimbangan(value) {
        var raw = safeText(value).toUpperCase();
        if (raw === 'LAB' || raw === 'LA' || raw === 'MIX') {
            return raw;
        }
        return '';
    }

    function getKategoriPenimbanganFromRowData(rowData) {
        if (!Array.isArray(rowData)) {
            return '';
        }

        var kategori = normalizeKategoriPenimbangan(rowData[29]);
        if (kategori) {
            return kategori;
        }

        var badgeHtml = rowData[25];
        if (badgeHtml != null && badgeHtml !== '') {
            var badgeText = $('<div>').html(String(badgeHtml)).text();
            kategori = normalizeKategoriPenimbangan(badgeText);
            if (kategori) {
                return kategori;
            }
        }

        return '';
    }

    function normalizeCpLookupKey(value) {
        var plain = $('<div>').html(value == null ? '' : String(value)).text();
        return safeText(plain).toUpperCase();
    }

    function formatDateDisplayFromIso(isoDate) {
        var raw = safeText(isoDate);
        if (!raw) return '';
        var parts = raw.split('-');
        if (parts.length !== 3) return raw;
        return parts[2] + '/' + parts[1] + '/' + parts[0];
    }

    function formatIsoDateFromDisplay(displayDate) {
        var raw = safeText(displayDate);
        if (!raw) return '';
        if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
            return raw;
        }

        var match = raw.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
        if (!match) {
            return '';
        }
        return match[3] + '-' + match[2] + '-' + match[1];
    }

    function formatTodayDisplay() {
        var now = new Date();
        var d = String(now.getDate()).padStart(2, '0');
        var m = String(now.getMonth() + 1).padStart(2, '0');
        var y = String(now.getFullYear());
        return d + '/' + m + '/' + y;
    }

    function getCurrentTimeHHmm() {
        var now = new Date();
        var hh = String(now.getHours()).padStart(2, '0');
        var mm = String(now.getMinutes()).padStart(2, '0');
        return hh + ':' + mm;
    }

    function parseFlexibleNumber(value) {
        var raw = safeText(value);
        if (!raw) {
            return NaN;
        }
        var normalized = raw.replace(/\s+/g, '');
        var hasComma = normalized.indexOf(',') !== -1;
        var hasDot = normalized.indexOf('.') !== -1;
        if (hasComma && hasDot) {
            normalized = normalized.replace(/,/g, '');
        } else if (hasComma) {
            var isThousandsComma = /^\-?\d{1,3}(,\d{3})+$/.test(normalized);
            if (isThousandsComma) {
                normalized = normalized.replace(/,/g, '');
            } else {
                var commaParts = normalized.split(',');
                if (commaParts.length === 2 && commaParts[1].length > 0 && commaParts[1].length <= 2) {
                    normalized = commaParts[0] + '.' + commaParts[1];
                } else {
                    normalized = normalized.replace(/,/g, '');
                }
            }
        } else if (hasDot) {
            var isThousandsDot = /^\-?\d{1,3}(\.\d{3})+$/.test(normalized);
            if (isThousandsDot) {
                normalized = normalized.replace(/\./g, '');
            }
        }
        var parsed = parseFloat(normalized);
        return Number.isFinite(parsed) ? parsed : NaN;
    }

    function normalizeMachineKey(value) {
        return safeText(value).toUpperCase();
    }

    function resetMachineMaxCapacityMap() {
        machineMaxCapacityMap = {};
    }

    function setMachineMaxCapacityRows(rows) {
        resetMachineMaxCapacityMap();
        if (!Array.isArray(rows)) {
            return;
        }

        rows.forEach(function (row) {
            if (!row || typeof row !== 'object') {
                return;
            }
            var machineKey = normalizeMachineKey(row.machine_id);
            if (!machineKey) {
                return;
            }
            var value = Number(row.max_capacity_day);
            machineMaxCapacityMap[machineKey] = Number.isFinite(value) ? value : 0;
        });
    }

    function getSelectedMachineMaxCapacity() {
        var machineKey = normalizeMachineKey(selectedMachineId);
        if (!machineKey || !Object.prototype.hasOwnProperty.call(machineMaxCapacityMap, machineKey)) {
            return NaN;
        }

        var value = Number(machineMaxCapacityMap[machineKey]);
        return Number.isFinite(value) ? value : NaN;
    }

    function parseTimeToMinutes(value) {
        var raw = safeText(value);
        var match = raw.match(/^(\d{1,2}):(\d{2})$/);
        if (!match) {
            return NaN;
        }
        var hh = parseInt(match[1], 10);
        var mm = parseInt(match[2], 10);
        if (!Number.isFinite(hh) || !Number.isFinite(mm) || hh < 0 || hh > 23 || mm < 0 || mm > 59) {
            return NaN;
        }
        return (hh * 60) + mm;
    }

    function formatMinutesToTime(totalMinutes) {
        if (!Number.isFinite(totalMinutes)) {
            return '';
        }
        var minutesInDay = 24 * 60;
        var normalized = Math.floor(totalMinutes) % minutesInDay;
        if (normalized < 0) {
            normalized += minutesInDay;
        }
        var hh = Math.floor(normalized / 60);
        var mm = normalized % 60;
        return String(hh).padStart(2, '0') + ':' + String(mm).padStart(2, '0');
    }

    function calculatePaddryPlanEnd(qtyValue, speedValue, startValue) {
        var qty = parseFlexibleNumber(qtyValue);
        var speed = parseFlexibleNumber(speedValue);
        var startMinutes = parseTimeToMinutes(startValue);
        if (!Number.isFinite(qty) || !Number.isFinite(speed) || !Number.isFinite(startMinutes)) {
            return '';
        }
        if (speed <= 0) {
            return '';
        }

        var durationMinutes = qty / speed;
        if (!Number.isFinite(durationMinutes) || durationMinutes < 0) {
            return '';
        }

        return formatMinutesToTime(startMinutes + durationMinutes);
    }

    function addMinutesToTime(timeValue, extraMinutes) {
        var base = parseTimeToMinutes(timeValue);
        var extra = parseFlexibleNumber(extraMinutes);
        if (!Number.isFinite(base) || !Number.isFinite(extra)) {
            return '';
        }
        return formatMinutesToTime(base + extra);
    }

    function getBreakDurationMinutes(startValue, endValue) {
        var startMinutes = parseTimeToMinutes(startValue);
        var endMinutes = parseTimeToMinutes(endValue);
        if (!Number.isFinite(startMinutes) || !Number.isFinite(endMinutes)) {
            return NaN;
        }
        var duration = endMinutes - startMinutes;
        if (duration < 0) {
            duration += (24 * 60);
        }
        return duration;
    }

    function isBreakTimeMainRow(row) {
        function normalizeBreakText(value) {
            return safeText(value).toLowerCase().replace(/\s+/g, ' ').trim();
        }

        if (!row || typeof row !== 'object') {
            return false;
        }

        var rawFlag = safeText(row.is_break_time).toLowerCase();
        if (row.is_break_time === true || rawFlag === '1' || rawFlag === 'true' || rawFlag === 'y' || rawFlag === 'yes') {
            return true;
        }

        var breakName = normalizeBreakText(row.break_time_name);
        var cpNo = normalizeBreakText(row.no_cp);
        if (breakName !== '' && cpNo !== '' && breakName === cpNo) {
            return true;
        }

        if (cpNo === '' || !Array.isArray(breakTimeRows) || !breakTimeRows.length) {
            return false;
        }

        var matched = false;
        $.each(breakTimeRows, function (_, br) {
            var name = normalizeBreakText(br && br.break_time_name);
            if (name !== '' && name === cpNo) {
                matched = true;
                return false;
            }
        });
        return matched;
    }

    function syncMainTableHorizontalScrollbar() {
        var $topScroll = $('#cpMainTableScrollTop');
        var $topScrollInner = $('#cpMainTableScrollTopInner');
        var $tableWrap = $('#cpMainTableResponsive');
        var $table = $('#cpMainTable');
        if (!$topScroll.length || !$topScrollInner.length || !$tableWrap.length || !$table.length) {
            return;
        }

        var wrapEl = $tableWrap.get(0);
        var tableWidth = Math.max($table.outerWidth() || 0, wrapEl ? wrapEl.scrollWidth : 0);
        $topScrollInner.width(tableWidth);

        var visibleWidth = $tableWrap.innerWidth() || 0;
        var hasHorizontalOverflow = tableWidth > (visibleWidth + 1);
        $topScroll.toggle(hasHorizontalOverflow);
        queueMainTableStickyHeaderOffsetSync();

        if (!hasHorizontalOverflow || !wrapEl) {
            return;
        }

        var topEl = $topScroll.get(0);
        if (!topEl) {
            return;
        }

        if (Math.abs((topEl.scrollLeft || 0) - (wrapEl.scrollLeft || 0)) > 1) {
            topEl.scrollLeft = wrapEl.scrollLeft;
        }
    }

    function syncMainTableStickyHeaderOffset() {
        var rootStyle = document.documentElement && document.documentElement.style;
        if (!rootStyle) {
            return;
        }

        var stickyTop = 0;
        if ($('body').hasClass('layout-navbar-fixed')) {
            var $navbar = $('.main-header:visible').first();
            stickyTop = Math.ceil($navbar.outerHeight() || 0);
        }
        rootStyle.setProperty('--cp-main-sticky-top', stickyTop + 'px');

        var $toolbar = $('.cp-main-panel .cp-main-toolbar:visible').first();
        var toolbarHeight = Math.ceil($toolbar.outerHeight() || 0);
        if (!Number.isFinite(toolbarHeight) || toolbarHeight < 1) {
            toolbarHeight = 42;
        }
        rootStyle.setProperty('--cp-main-toolbar-height', toolbarHeight + 'px');

        var $topScroll = $('#cpMainTableScrollTop:visible');
        var topScrollHeight = Math.ceil($topScroll.outerHeight() || 0);
        if (!Number.isFinite(topScrollHeight) || topScrollHeight < 0) {
            topScrollHeight = 0;
        }
        rootStyle.setProperty('--cp-main-scroll-top-height', topScrollHeight + 'px');

        var $tableWrap = $('#cpMainTableResponsive:visible').first();
        if ($tableWrap.length) {
            var wrapEl = $tableWrap.get(0);
            var viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
            var rect = wrapEl.getBoundingClientRect();
            var bottomGap = 14;
            var maxTableHeight = Math.floor(viewportHeight - rect.top - bottomGap);
            if (!Number.isFinite(maxTableHeight) || maxTableHeight < 220) {
                maxTableHeight = 220;
            }
            rootStyle.setProperty('--cp-main-table-max-height', maxTableHeight + 'px');
        }

        var $firstRow = $('#cpMainTableHead tr:first-child');
        var firstRowHeight = 0;
        $firstRow.children('th').each(function () {
            var rowspan = parseInt($(this).attr('rowspan'), 10);
            if (Number.isFinite(rowspan) && rowspan > 1) {
                return;
            }
            var h = Math.ceil($(this).outerHeight() || 0);
            if (Number.isFinite(h) && h > firstRowHeight) {
                firstRowHeight = h;
            }
        });

        if (!Number.isFinite(firstRowHeight) || firstRowHeight < 1) {
            firstRowHeight = Math.ceil($firstRow.outerHeight() || 0);
        }
        if (!Number.isFinite(firstRowHeight) || firstRowHeight < 1) {
            firstRowHeight = 34;
        }
        if (firstRowHeight < 20) {
            firstRowHeight = 20;
        }
        rootStyle.setProperty('--cp-main-head-row1-height', firstRowHeight + 'px');
    }

    function queueMainTableStickyHeaderOffsetSync() {
        if (typeof window.requestAnimationFrame === 'function') {
            window.requestAnimationFrame(syncMainTableStickyHeaderOffset);
            return;
        }
        setTimeout(syncMainTableStickyHeaderOffset, 0);
    }

    function queueMainTableHorizontalScrollbarSync() {
        if (typeof window.requestAnimationFrame === 'function') {
            window.requestAnimationFrame(syncMainTableHorizontalScrollbar);
            return;
        }
        setTimeout(syncMainTableHorizontalScrollbar, 0);
    }

    function initMainTableHorizontalScrollbarSync() {
        var $topScroll = $('#cpMainTableScrollTop');
        var $tableWrap = $('#cpMainTableResponsive');
        if (!$topScroll.length || !$tableWrap.length) {
            return;
        }

        $topScroll.off('scroll.cpMainTable').on('scroll.cpMainTable', function () {
            if (isMainTableScrollSyncing) {
                return;
            }
            isMainTableScrollSyncing = true;
            $tableWrap.scrollLeft($(this).scrollLeft());
            isMainTableScrollSyncing = false;
        });

        $tableWrap.off('scroll.cpMainTable').on('scroll.cpMainTable', function () {
            if (isMainTableScrollSyncing) {
                return;
            }
            isMainTableScrollSyncing = true;
            $topScroll.scrollLeft($(this).scrollLeft());
            isMainTableScrollSyncing = false;
        });
    }

    function ensureMainTableColumnWidthStyleElement() {
        var $style = $('#cpMainTableColumnWidthStyle');
        if ($style.length) {
            return $style;
        }
        $style = $('<style id="cpMainTableColumnWidthStyle"></style>');
        $('head').append($style);
        return $style;
    }

    function getMainTableColumnWidthMap() {
        var layoutKey = safeText(getMainTableLayoutKey());
        if (!layoutKey) {
            layoutKey = 'default';
        }
        if (!mainTableColumnWidthsByLayout[layoutKey] || typeof mainTableColumnWidthsByLayout[layoutKey] !== 'object') {
            mainTableColumnWidthsByLayout[layoutKey] = {};
        }
        return mainTableColumnWidthsByLayout[layoutKey];
    }

    function getMainTableLeafColumns() {
        var $rows = $('#cpMainTableHead tr');
        if (!$rows.length) {
            return [];
        }

        var grid = [];
        var maxCols = 0;

        $rows.each(function (rowIndex) {
            if (!grid[rowIndex]) {
                grid[rowIndex] = [];
            }

            var colCursor = 0;
            $(this).children('th').each(function () {
                while (grid[rowIndex][colCursor]) {
                    colCursor += 1;
                }

                var colspan = parseInt($(this).attr('colspan'), 10);
                var rowspan = parseInt($(this).attr('rowspan'), 10);
                colspan = Number.isFinite(colspan) && colspan > 0 ? colspan : 1;
                rowspan = Number.isFinite(rowspan) && rowspan > 0 ? rowspan : 1;

                for (var rr = 0; rr < rowspan; rr++) {
                    var targetRow = rowIndex + rr;
                    if (!grid[targetRow]) {
                        grid[targetRow] = [];
                    }
                    for (var cc = 0; cc < colspan; cc++) {
                        grid[targetRow][colCursor + cc] = this;
                    }
                }

                colCursor += colspan;
                if (colCursor > maxCols) {
                    maxCols = colCursor;
                }
            });
        });

        var columns = [];
        for (var colIdx = 0; colIdx < maxCols; colIdx++) {
            var leafCell = null;
            for (var rowIdx = grid.length - 1; rowIdx >= 0; rowIdx--) {
                if (grid[rowIdx] && grid[rowIdx][colIdx]) {
                    leafCell = grid[rowIdx][colIdx];
                    break;
                }
            }
            if (!leafCell) {
                continue;
            }
            columns.push({
                index: colIdx + 1,
                cell: leafCell
            });
        }
        return columns;
    }

    function applyMainTableColumnWidthStyles() {
        var widthMap = getMainTableColumnWidthMap();
        var rules = [];

        $.each(widthMap, function (colKey, widthValue) {
            var colIndex = parseInt(colKey, 10);
            var width = parseInt(widthValue, 10);
            if (!Number.isFinite(colIndex) || colIndex <= 2 || !Number.isFinite(width) || width < 70) {
                return;
            }

            rules.push(
                '#cpMainTable tbody td:nth-child(' + colIndex + ') {' +
                    'width:' + width + 'px;' +
                    'min-width:' + width + 'px;' +
                '}'
            );
            rules.push(
                '#cpMainTableHead th[data-col-index="' + colIndex + '"] {' +
                    'width:' + width + 'px;' +
                    'min-width:' + width + 'px;' +
                '}'
            );
        });

        ensureMainTableColumnWidthStyleElement().text(rules.join('\n'));
        queueMainTableHorizontalScrollbarSync();
    }

    function initializeMainTableColumnResize() {
        var $head = $('#cpMainTableHead');
        if (!$head.length) {
            return;
        }

        $head.find('.cp-col-resizer').remove();
        $head.find('th').removeAttr('data-col-index');

        var columns = getMainTableLeafColumns();
        var handledCells = [];
        $.each(columns, function (_, col) {
            var colIndex = parseInt(col && col.index, 10);
            if (!Number.isFinite(colIndex)) {
                return;
            }

            var $th = $(col.cell);
            if (!$th.length) {
                return;
            }

            if (!$th.attr('data-col-index')) {
                $th.attr('data-col-index', String(colIndex));
            }

            if (colIndex <= 2) {
                return;
            }

            var thEl = $th.get(0);
            if (handledCells.indexOf(thEl) !== -1) {
                return;
            }
            handledCells.push(thEl);

            $th.append('<span class="cp-col-resizer" data-col-index="' + colIndex + '" title="Geser untuk ubah lebar kolom"></span>');
        });

        applyMainTableColumnWidthStyles();
    }

    function bindMainTableColumnResizeEvents() {
        var $head = $('#cpMainTableHead');
        $head.off('mousedown.cpColResize', '.cp-col-resizer').on('mousedown.cpColResize', '.cp-col-resizer', function (e) {
            var colIndex = parseInt($(this).attr('data-col-index'), 10);
            if (!Number.isFinite(colIndex) || colIndex <= 2) {
                return;
            }

            var $th = $(this).closest('th');
            var startWidth = Math.round($th.outerWidth() || 0);
            if (!Number.isFinite(startWidth) || startWidth < 70) {
                startWidth = 120;
            }

            activeMainTableResize = {
                colIndex: colIndex,
                startX: e.pageX,
                startWidth: startWidth,
                minWidth: 70,
                $handle: $(this)
            };

            activeMainTableResize.$handle.addClass('active');
            $('body').addClass('cp-col-resizing');
            e.preventDefault();
            e.stopPropagation();
        });

        $head.off('dblclick.cpColResize', '.cp-col-resizer').on('dblclick.cpColResize', '.cp-col-resizer', function (e) {
            var colIndex = parseInt($(this).attr('data-col-index'), 10);
            if (!Number.isFinite(colIndex) || colIndex <= 2) {
                return;
            }
            var widthMap = getMainTableColumnWidthMap();
            delete widthMap[String(colIndex)];
            applyMainTableColumnWidthStyles();
            e.preventDefault();
            e.stopPropagation();
        });

        $(document).off('mousemove.cpColResize').on('mousemove.cpColResize', function (e) {
            if (!activeMainTableResize) {
                return;
            }

            var delta = e.pageX - activeMainTableResize.startX;
            var nextWidth = Math.round(activeMainTableResize.startWidth + delta);
            nextWidth = Math.max(activeMainTableResize.minWidth, nextWidth);

            var widthMap = getMainTableColumnWidthMap();
            widthMap[String(activeMainTableResize.colIndex)] = nextWidth;
            applyMainTableColumnWidthStyles();
        });

        $(document).off('mouseup.cpColResize').on('mouseup.cpColResize', function () {
            if (!activeMainTableResize) {
                return;
            }
            if (activeMainTableResize.$handle && activeMainTableResize.$handle.length) {
                activeMainTableResize.$handle.removeClass('active');
            }
            activeMainTableResize = null;
            $('body').removeClass('cp-col-resizing');
        });
    }

    function applyPaddryPlanEndForRow(row, startOverride, speedOverride, includeSavedRows) {
        var allowSaved = (includeSavedRows === true);
        var isPending = safeText(row && row._save_state) === 'pending';
        if (!row || (!isPending && !allowSaved)) {
            return safeText(row && row.plan_end);
        }
        var startTime = safeText(startOverride != null ? startOverride : row.plan_start);
        var speed = safeText(speedOverride != null ? speedOverride : row.speed);
        var finish = calculatePaddryPlanEnd(row.qty, speed, startTime);
        row.plan_start = startTime;
        row.speed = speed;
        row.plan_end = finish;
        return finish;
    }

    function isPaddryManualPlanValue(row, field) {
        if (!row || typeof row !== 'object') {
            return false;
        }
        var flagKey = field === 'plan_end' ? '_manual_plan_end' : '_manual_plan_start';
        if (row[flagKey] === true) {
            return true;
        }
        var raw = safeText(row[flagKey]).toLowerCase();
        return raw === '1' || raw === 'true' || raw === 'y' || raw === 'yes';
    }

    function setPaddryManualPlanValue(row, field, isManual) {
        if (!row || typeof row !== 'object') {
            return;
        }
        var flagKey = field === 'plan_end' ? '_manual_plan_end' : '_manual_plan_start';
        if (isManual) {
            row[flagKey] = true;
            return;
        }
        if (Object.prototype.hasOwnProperty.call(row, flagKey)) {
            delete row[flagKey];
        }
    }

    function recalculatePaddryPlanSchedule(includeSavedRows) {
        if (getMainTableLayoutKey() !== 'paddry') {
            return;
        }

        var allowSaved = (includeSavedRows === true);
        var previousFinish = '';
        var previousWasBreakTime = false;
        mainTableRows.forEach(function (row, idx) {
            if (!row || typeof row !== 'object') {
                return;
            }

            var isPending = safeText(row._save_state) === 'pending';
            var canAutoRecalc = isPending || allowSaved;
            var isBreakTimeRow = isBreakTimeMainRow(row);
            var hasManualStart = isPaddryManualPlanValue(row, 'plan_start');

            if (hasManualStart && !Number.isFinite(parseTimeToMinutes(row.plan_start))) {
                hasManualStart = false;
                setPaddryManualPlanValue(row, 'plan_start', false);
            }

            var breakDuration = isBreakTimeRow ? getBreakDurationMinutes(row.plan_start, row.plan_end) : NaN;
            if (idx > 0 && canAutoRecalc && !hasManualStart && safeText(previousFinish) !== '') {
                var previousRow = mainTableRows[idx - 1] || null;
                var currentKodeLab = normalizeCpKeyText(row && row.kode_lab);
                var previousKodeLab = normalizeCpKeyText(previousRow && previousRow.kode_lab);
                var gapMinutes = 30;

                if (isBreakTimeRow || previousWasBreakTime) {
                    gapMinutes = 0;
                } else if (currentKodeLab !== '' && currentKodeLab === previousKodeLab) {
                    gapMinutes = 0;
                }

                var chainedStart = addMinutesToTime(previousFinish, gapMinutes);
                if (chainedStart !== '') {
                    row.plan_start = chainedStart;
                }
            }

            if (canAutoRecalc) {
                if (!isBreakTimeRow) {
                    applyPaddryPlanEndForRow(row, null, null, allowSaved);
                    row.est_tmbng_plrtm_lalab = addMinutesToTime(row.plan_start, -420);
                    row.est_plrtm_prdks = addMinutesToTime(row.plan_start, -300);
                } else {
                    var hasManualEndBreak = isPaddryManualPlanValue(row, 'plan_end');
                    if (hasManualEndBreak && !Number.isFinite(parseTimeToMinutes(row.plan_end))) {
                        hasManualEndBreak = false;
                        setPaddryManualPlanValue(row, 'plan_end', false);
                    }
                    if (!hasManualEndBreak && Number.isFinite(breakDuration)) {
                        row.plan_end = addMinutesToTime(row.plan_start, breakDuration);
                    }
                    row.est_tmbng_plrtm_lalab = '';
                    row.est_plrtm_prdks = '';
                }
            }

            previousFinish = safeText(row.plan_end);
            previousWasBreakTime = isBreakTimeRow;
        });
    }

    function syncPaddryPlanTimeInputs() {
        $('#cpTableBody tr[data-row-id]').each(function () {
            var rowId = safeText($(this).attr('data-row-id'));
            if (!rowId) {
                return;
            }

            var target = null;
            for (var i = 0; i < mainTableRows.length; i++) {
                if (safeText(mainTableRows[i]._row_id) === rowId) {
                    target = mainTableRows[i];
                    break;
                }
            }
            if (!target) {
                return;
            }

            $(this).find('input[data-field="plan_start"]').val(safeText(target.plan_start));
            $(this).find('input[data-field="plan_end"]').val(safeText(target.plan_end));
            $(this).find('input[data-field="est_tmbng_plrtm_lalab"]').val(safeText(target.est_tmbng_plrtm_lalab));
            $(this).find('input[data-field="est_plrtm_prdks"]').val(safeText(target.est_plrtm_prdks));
        });
    }

    function getSelectedMachineRow() {
        var found = null;
        $.each(machineRows, function (_, row) {
            if (safeText(row.id) === safeText(selectedMachineId)) {
                found = row;
                return false;
            }
        });
        return found;
    }

    function isPersistentLayoutKey(layoutKey) {
        var key = safeText(layoutKey);
        return key === 'bakar_bulu' || key === 'paddry' || key === 'scouring' || key === 'presett';
    }

    function buildMoveDateMachineOptions(selectedMachine) {
        var selectedId = safeText(selectedMachine);
        var selectedIdUpper = selectedId.toUpperCase();
        var html = '<option value="">Pilih machine tujuan</option>';
        var hasSelected = false;

        machineRows.forEach(function (row) {
            var machineId = safeText(row && row.id);
            if (!machineId) {
                return;
            }
            var machineCode = safeText(row && row.facode) || machineId;
            var machineName = safeText(row && row.faname) || machineCode;
            var isSelected = machineId.toUpperCase() === selectedIdUpper;
            if (isSelected) {
                hasSelected = true;
            }
            html += '<option value="' + escapeHtml(machineId) + '"' + (isSelected ? ' selected' : '') + '>' +
                escapeHtml(machineName + ' (' + machineCode + ')') +
            '</option>';
        });

        if (selectedId && !hasSelected) {
            html += '<option value="' + escapeHtml(selectedId) + '" selected>' + escapeHtml(selectedId) + '</option>';
        }

        $('#moveDateTargetMachine').html(html);
    }

    function collectMoveDateSelectedRowIds() {
        var selectedRowIds = getCheckedMainRowIdsInOrder();
        if (!selectedRowIds.length && safeText(selectedMainRowId) !== '') {
            selectedRowIds = [safeText(selectedMainRowId)];
        }
        if (!selectedRowIds.length) {
            return [];
        }

        var selectedMap = {};
        selectedRowIds.forEach(function (rowId) {
            selectedMap[safeText(rowId)] = true;
        });

        var persistedRowIds = [];
        mainTableRows.forEach(function (row) {
            var rowId = safeText(row && row._row_id);
            if (!rowId || !Object.prototype.hasOwnProperty.call(selectedMap, rowId)) {
                return;
            }
            if (safeText(row && row._save_state) === 'saved' && getPersistedDbIdFromRowId(rowId) > 0) {
                persistedRowIds.push(rowId);
            }
        });
        return persistedRowIds;
    }

    function resetMoveDateModalState() {
        moveDateRowIds = [];
        $('#moveDateSelectedCount').text('0 data dipilih.');
        $('#moveDateTargetDate').val('');
        $('#moveDateTargetMachine').html('<option value="">Pilih machine tujuan</option>');
        $('#btnSubmitMoveDate').prop('disabled', false);
    }

    function openMoveDateModal() {
        var layoutKey = getMainTableLayoutKey();
        if (!isPersistentLayoutKey(layoutKey)) {
            showAlert('warning', 'Fitur pindah tanggal hanya tersedia untuk layout bakar bulu, paddry, scouring, dan presett.');
            return;
        }

        if (hasPendingRows()) {
            showAlert('warning', 'Masih ada data sementara. Klik checklist atau silang terlebih dahulu.');
            return;
        }

        updateMainRowsFromInputs();
        var selectedRowIds = getCheckedMainRowIdsInOrder();
        if (!selectedRowIds.length && safeText(selectedMainRowId) !== '') {
            selectedRowIds = [safeText(selectedMainRowId)];
        }
        var persistedRowIds = collectMoveDateSelectedRowIds();
        if (!persistedRowIds.length) {
            showAlert('warning', 'Pilih data yang sudah tersimpan permanen untuk dipindahkan.');
            return;
        }

        moveDateRowIds = persistedRowIds.slice();
        $('#moveDateSelectedCount').text(moveDateRowIds.length + ' data dipilih.');
        $('#moveDateTargetDate').val(safeText($('#periodDate').val()));
        buildMoveDateMachineOptions(safeText(selectedMachineId));
        $('#moveDateModal').modal('show');

        var skippedCount = Math.max(0, selectedRowIds.length - moveDateRowIds.length);
        if (skippedCount > 0) {
            showAlert('info', skippedCount + ' data belum tersimpan permanen sehingga tidak ikut dipindahkan.');
        }
    }

    function nextRowId() {
        rowSequence += 1;
        return 'tmp_' + rowSequence;
    }

    function captureCurrentRowOrder() {
        return mainTableRows.map(function (row) {
            return safeText(row && row._row_id);
        }).filter(function (rowId) {
            return rowId !== '';
        });
    }

    function clearOrderDirtyState() {
        orderDirty = false;
        orderSnapshotRowIds = [];
    }

    function restoreRowOrderFromSnapshot() {
        if (!orderSnapshotRowIds.length || !mainTableRows.length) {
            return false;
        }

        var rowMap = {};
        mainTableRows.forEach(function (row) {
            var rowId = safeText(row && row._row_id);
            if (rowId !== '') {
                rowMap[rowId] = row;
            }
        });

        var reordered = [];
        orderSnapshotRowIds.forEach(function (rowId) {
            if (Object.prototype.hasOwnProperty.call(rowMap, rowId)) {
                reordered.push(rowMap[rowId]);
                delete rowMap[rowId];
            }
        });

        mainTableRows.forEach(function (row) {
            var rowId = safeText(row && row._row_id);
            if (rowId === '' || Object.prototype.hasOwnProperty.call(rowMap, rowId)) {
                reordered.push(row);
                if (rowId !== '') {
                    delete rowMap[rowId];
                }
            }
        });

        if (!reordered.length) {
            return false;
        }

        mainTableRows = reordered;
        return true;
    }

    function getPersistedDbIdFromRowId(rowId) {
        var raw = safeText(rowId);
        var match = raw.match(/^(?:db|dbp|dbs|dbps)_(\d+)$/i);
        if (!match) {
            return 0;
        }
        var num = parseInt(match[1], 10);
        return Number.isFinite(num) ? num : 0;
    }

    function hasPendingRows() {
        if (!Array.isArray(mainTableRows) || mainTableRows.length === 0) {
            if (orderDirty) {
                clearOrderDirtyState();
            }
            return false;
        }
        if (orderDirty) {
            return true;
        }
        return mainTableRows.some(function (row) {
            return safeText(row._save_state) === 'pending';
        });
    }

    function togglePendingActionButtons() {
        var show = hasPendingRows();
        $('#btnCommitPending, #btnCancelPending').toggleClass('d-none', !show);
    }

    function isMainRowChecked(rowId) {
        var key = safeText(rowId);
        return key !== '' && selectedMainRowIds[key] === true;
    }

    function setMainRowChecked(rowId, isChecked) {
        var key = safeText(rowId);
        if (key === '') {
            return;
        }
        if (isChecked) {
            selectedMainRowIds[key] = true;
            return;
        }
        if (Object.prototype.hasOwnProperty.call(selectedMainRowIds, key)) {
            delete selectedMainRowIds[key];
        }
    }

    function clearAllMainRowChecked() {
        selectedMainRowIds = {};
    }

    function pruneMainRowCheckedState() {
        var valid = {};
        mainTableRows.forEach(function (row) {
            var rowId = safeText(row && row._row_id);
            if (rowId !== '') {
                valid[rowId] = true;
            }
        });
        Object.keys(selectedMainRowIds).forEach(function (rowId) {
            if (!valid[rowId]) {
                delete selectedMainRowIds[rowId];
            }
        });
    }

    function getCheckedMainRowIdsInOrder() {
        var ids = [];
        mainTableRows.forEach(function (row) {
            var rowId = safeText(row && row._row_id);
            if (rowId !== '' && isMainRowChecked(rowId)) {
                ids.push(rowId);
            }
        });
        return ids;
    }

    function getFirstCheckedMainRowId() {
        var ids = getCheckedMainRowIdsInOrder();
        return ids.length ? ids[0] : '';
    }

    function makeMainTableSelectAllHeaderControl() {
        return '<label class="mb-0 d-inline-flex align-items-center">' +
            '<input type="checkbox" class="main-row-check-all" title="Select all rows">' +
            '<span>Seq</span>' +
        '</label>';
    }

    function syncMainRowSelectAllCheckbox() {
        var $checkAll = $('#cpMainTableHead .main-row-check-all');
        if (!$checkAll.length) {
            return;
        }

        var totalRows = 0;
        var checkedRows = 0;
        mainTableRows.forEach(function (row) {
            var rowId = safeText(row && row._row_id);
            if (rowId === '') {
                return;
            }
            totalRows += 1;
            if (isMainRowChecked(rowId)) {
                checkedRows += 1;
            }
        });

        var allChecked = totalRows > 0 && checkedRows === totalRows;
        var noneChecked = checkedRows === 0;
        $checkAll.prop('checked', allChecked);
        $checkAll.prop('indeterminate', !noneChecked && !allChecked);
        $checkAll.prop('disabled', totalRows === 0);
    }

    function decorateMainTableSelectAllHeader() {
        var $seqHead = $('#cpMainTableHead tr:first-child th:first-child');
        if (!$seqHead.length) {
            return;
        }
        $seqHead.html(makeMainTableSelectAllHeaderControl());
        syncMainRowSelectAllCheckbox();
    }

    function applyMainRowSelection() {
        pruneMainRowCheckedState();
        var hasActiveRow = false;
        $('#cpTableBody tr[data-row-id]').removeClass('row-selected').each(function () {
            var rowId = safeText($(this).attr('data-row-id'));
            var isActive = rowId !== '' && rowId === safeText(selectedMainRowId);
            var isChecked = isMainRowChecked(rowId);
            if (isActive || isChecked) {
                $(this).addClass('row-selected');
                if (isActive) {
                    hasActiveRow = true;
                }
            }
            $(this).find('.main-row-checkbox').prop('checked', isChecked);
        });

        if (!hasActiveRow) {
            selectedMainRowId = getFirstCheckedMainRowId();
        }
        syncMainRowSelectAllCheckbox();
    }

    function setSelectedMainRow(rowId) {
        selectedMainRowId = safeText(rowId);
        applyMainRowSelection();
    }

    function updateMainRowsFromInputs() {
        var layoutKey = getMainTableLayoutKey();
        var paddryAutoEnabled = isPaddryAutoScheduleEnabled();
        $('#cpTableBody tr[data-row-id]').each(function () {
            var rowId = safeText($(this).attr('data-row-id'));
            if (!rowId) {
                return;
            }

            var target = null;
            for (var i = 0; i < mainTableRows.length; i++) {
                if (safeText(mainTableRows[i]._row_id) === rowId) {
                    target = mainTableRows[i];
                    break;
                }
            }

            if (!target) {
                return;
            }

            $(this).find('input[data-field]').each(function () {
                var field = safeText($(this).attr('data-field'));
                if (!field) {
                    return;
                }
                var nextValue = safeText($(this).val());
                if (layoutKey === 'paddry' && (field === 'plan_start' || field === 'plan_end')) {
                    if (paddryAutoEnabled) {
                        if (field === 'plan_start') {
                            var prevStart = safeText(target[field]);
                            if (nextValue !== prevStart) {
                                setPaddryManualPlanValue(target, field, nextValue !== '');
                            } else if (nextValue === '') {
                                setPaddryManualPlanValue(target, field, false);
                            }
                        } else if (field === 'plan_end') {
                            // Mode auto paddry: finish selalu ikut rumus start + (qty/speed)
                            setPaddryManualPlanValue(target, field, false);
                        }
                    } else {
                        setPaddryManualPlanValue(target, field, false);
                    }
                }
                target[field] = nextValue;
            });
        });

        if (layoutKey === 'paddry' && paddryAutoEnabled) {
            recalculatePaddryPlanSchedule();
            syncPaddryPlanTimeInputs();
        }
    }

    function getFirstRowSpeedValue() {
        var $firstRow = $('#cpTableBody tr[data-row-id]:first');
        if (!$firstRow.length) {
            return '';
        }
        var $speedInput = $firstRow.find('input[data-field="speed"]');
        if (!$speedInput.length || $speedInput.prop('readonly')) {
            return '';
        }
        return safeText($speedInput.val());
    }

    function hasEditableSpeedRowsBelowFirst() {
        var hasTarget = false;
        $('#cpTableBody tr[data-row-id]').each(function (idx) {
            if (idx === 0) {
                return;
            }
            var $speedInput = $(this).find('input[data-field="speed"]');
            if ($speedInput.length && !$speedInput.prop('readonly')) {
                hasTarget = true;
                return false;
            }
        });
        return hasTarget;
    }

    function updateFillSameSpeedButtonState() {
        var $btn = $('#btnFillSameSpeed');
        if (!$btn.length) {
            return;
        }

        var firstSpeed = getFirstRowSpeedValue();
        var canApply = firstSpeed !== '' && hasEditableSpeedRowsBelowFirst();
        $btn.toggleClass('d-none', !canApply);
        if (canApply) {
            $btn.attr('title', 'Isi semua speed baris bawah dengan nilai ' + firstSpeed);
        } else {
            $btn.attr('title', 'Isi semua speed baris bawah mengikuti speed baris pertama');
        }
    }

    function applyCheckedRowsSameFieldValue(sourceInput) {
        var $source = $(sourceInput);
        if (!$source.length || $source.prop('readonly')) {
            return 0;
        }

        var field = safeText($source.attr('data-field'));
        var sourceRowId = safeText($source.attr('data-row-id'));
        if (!field || !sourceRowId || !isMainRowChecked(sourceRowId)) {
            return 0;
        }

        var value = safeText($source.val());
        var copiedCount = 0;

        $('#cpTableBody tr[data-row-id]').each(function () {
            var rowId = safeText($(this).attr('data-row-id'));
            if (!rowId || rowId === sourceRowId || !isMainRowChecked(rowId)) {
                return;
            }

            var $targetInput = $(this).find('input[data-field="' + field + '"]');
            if (!$targetInput.length || $targetInput.prop('readonly')) {
                return;
            }

            $targetInput.val(value);
            copiedCount += 1;
        });

        return copiedCount;
    }

    function normalizePaddryInputMode(value) {
        return safeText(value).toLowerCase() === 'manual' ? 'manual' : 'auto';
    }

    function isPaddryAutoScheduleEnabled() {
        return getMainTableLayoutKey() === 'paddry' && normalizePaddryInputMode(paddryInputMode) === 'auto';
    }

    function applyPaddryInputMode(nextMode, options) {
        var opts = options || {};
        var mode = normalizePaddryInputMode(nextMode);
        var previousMode = normalizePaddryInputMode(paddryInputMode);
        paddryInputMode = mode;
        updatePaddryInputModeUi();

        if (getMainTableLayoutKey() !== 'paddry') {
            return;
        }

        if (mode === 'auto') {
            if (previousMode !== 'auto') {
                mainTableRows.forEach(function (row) {
                    if (safeText(row && row._save_state) !== 'pending') {
                        return;
                    }
                    setPaddryManualPlanValue(row, 'plan_start', false);
                    setPaddryManualPlanValue(row, 'plan_end', false);
                });
            }
            recalculatePaddryPlanSchedule();
            syncPaddryPlanTimeInputs();
            renderMainTable(mainTableRows);
            if (!opts.silent) {
                showAlert('info', 'Mode input paddry: otomatis. Rencana celup dan estimasi mengikuti kalkulasi sistem.');
            }
            return;
        }

        if (!opts.silent) {
            showAlert('info', 'Mode input paddry: manual. Rencana celup serta Est Tmbng/Est Plrtm bisa diisi manual.');
        }
    }

    function updatePaddryInputModeUi() {
        var isPaddryLayout = getMainTableLayoutKey() === 'paddry';
        var mode = normalizePaddryInputMode(paddryInputMode);
        var modeLabel = mode === 'manual' ? 'Manual' : 'Otomatis';
        var $group = $('#cpScheduleModeGroup');
        var $button = $('#btnScheduleMode');
        $('#btnImportExcel').toggleClass('d-none', !isPaddryLayout);
        $group.toggleClass('d-none', !isPaddryLayout);
        if ($button.length) {
            $button.attr('title', 'Mode input paddry: ' + modeLabel);
        }

        $('#cpScheduleModeMenu .schedule-mode-item').each(function () {
            var itemMode = normalizePaddryInputMode($(this).attr('data-mode'));
            var isActive = itemMode === mode;
            $(this).toggleClass('active', isActive);
            $(this).attr('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    function buildImportedPaddryRow(rawRow, periodDisplay, machineLabel) {
        var src = (rawRow && typeof rawRow === 'object') ? rawRow : {};
        var mapped = {
            _row_id: nextRowId(),
            _save_state: 'pending',
            no_cp: safeText(src.no_cp),
            urut_plan: '',
            ket: '',
            tgl_cp: safeText(src.tgl_cp),
            routing_code: '',
            routing_name: '',
            lokasi_paddry: safeText(src.lokasi_paddry) || safeText(src.routing_name),
            product_code: '',
            product_name: '',
            work_center_code: '',
            work_center_name: '',
            kode_lab: safeText(src.kode_lab),
            color_name: '',
            label: safeText(src.label),
            cust_color: safeText(src.cust_color),
            material: safeText(src.material),
            qty: safeText(src.qty),
            grammature: '0.0000',
            speed: safeText(src.speed),
            temperatur: safeText(src.temperatur),
            plan_machine: safeText(machineLabel),
            plan_date: safeText(src.plan_date) || periodDisplay,
            plan_paddry: '',
            plan_start: safeText(src.plan_start),
            plan_end: safeText(src.plan_end),
            rko: safeText(src.rko),
            resp_lipat: safeText(src.resp_lipat),
            status_resp: safeText(src.status_resp),
            vlot_resp: safeText(src.vlot_resp),
            bon_resp: safeText(src.bon_resp),
            plan_description: safeText(src.plan_description),
            actual_date: safeText(src.actual_date),
            actual_start: safeText(src.actual_start),
            actual_end: safeText(src.actual_end),
            actual_timbang_lab_start: safeText(src.actual_timbang_lab_start) || safeText(src.actual_timbang_lalab_start),
            actual_timbang_lab_finish: safeText(src.actual_timbang_lab_finish) || safeText(src.actual_timbang_lalab_finish),
            actual_timbang_la_start: safeText(src.actual_timbang_la_start),
            actual_timbang_la_finish: safeText(src.actual_timbang_la_finish),
            actual_larut_lab_start: safeText(src.actual_larut_lab_start) || safeText(src.actual_larut_lalab_start),
            actual_larut_lab_finish: safeText(src.actual_larut_lab_finish) || safeText(src.actual_larut_lalab_finish),
            actual_larut_la_start: safeText(src.actual_larut_la_start),
            actual_larut_la_finish: safeText(src.actual_larut_la_finish),
            actual_timbang_lalab_start: safeText(src.actual_timbang_lalab_start),
            actual_timbang_lalab_finish: safeText(src.actual_timbang_lalab_finish),
            actual_larut_lalab_start: safeText(src.actual_larut_lalab_start),
            actual_larut_lalab_finish: safeText(src.actual_larut_lalab_finish),
            actual_larut_prdks_start: safeText(src.actual_larut_prdks_start),
            actual_larut_prdks_finish: safeText(src.actual_larut_prdks_finish),
            actual_topping_paddry_start: safeText(src.actual_topping_paddry_start) || safeText(src.actual_topping_start),
            actual_topping_paddry_finish: safeText(src.actual_topping_paddry_finish) || safeText(src.actual_topping_finish),
            actual_operator: safeText(src.actual_operator),
            actual_shift: safeText(src.actual_shift),
            down_time: safeText(src.down_time),
            actual_wheel_no: '',
            actual_realisasi: '',
            actual_vlot: safeText(src.actual_vlot),
            cpb_actual_date: '',
            cpb_actual_start: '',
            cpb_actual_end: '',
            bt_jam: '',
            resep_ke_gdg: '',
            est_tmbng_plrtm_lalab: safeText(src.est_tmbng_plrtm_lalab),
            est_plrtm_prdks: safeText(src.est_plrtm_prdks),
            recipe_alkali: '',
            recipe_dyestuff: '',
            recipe_be: '',
            initial_alkali: '',
            initial_dyestuff: '',
            remaining_alkali: '',
            remaining_dyestuff: '',
            sisa_tanggal: '',
            sisa_saturator: safeText(src.sisa_saturator),
            posisi_hari_ini: safeText(src.posisi_hari_ini) || '-',
            next_routing: safeText(src.next_routing) || '-',
            sample: safeText(src.sample),
            last_update: periodDisplay,
            updated_by: safeText(currentUser) || '-',
            vlot: safeText(src.vlot_resp),
            kategori: safeText(src.kategori)
        };

        if (mapped.plan_start !== '') {
            mapped._manual_plan_start = true;
        }
        if (mapped.plan_end !== '') {
            mapped._manual_plan_end = true;
        }

        return mapped;
    }

    function makeTextInput(row, field, extraClass, placeholder, inputType) {
        var isSavedRow = safeText(row && row._save_state) === 'saved';
        var isBreakTimeRow = isBreakTimeMainRow(row);
        var breakTimeEditableFields = {
            plan_description: true,
            plan_start: true,
            plan_end: true,
            actual_start: true,
            actual_end: true
        };
        var isBreakTimeFieldEditable = !!breakTimeEditableFields[safeText(field)];
        var isReadOnlyRow = isSavedRow || (isBreakTimeRow && !isBreakTimeFieldEditable);
        var classes = 'form-control form-control-sm cp-cell-input ' + safeText(extraClass);
        if (isReadOnlyRow) {
            classes += ' cp-cell-readonly';
        }
        var value = row && row[field] != null ? row[field] : '';
        var rowId = row && row._row_id ? row._row_id : '';
        var finalInputType = safeText(inputType) || 'text';

        if (finalInputType === 'date') {
            value = formatIsoDateFromDisplay(value);
        }
        if (finalInputType === 'time') {
            var rawTime = safeText(value);
            if (/^\d{2}:\d{2}:\d{2}$/.test(rawTime)) {
                value = rawTime.substring(0, 5);
            } else {
                value = rawTime;
            }
        }

        return '<input type="' + escapeHtml(finalInputType) + '" class="' + escapeHtml(classes) + '"' +
            ' data-row-id="' + escapeHtml(rowId) + '"' +
            ' data-field="' + escapeHtml(field) + '"' +
            ' value="' + escapeHtml(safeText(value)) + '"' +
            (isReadOnlyRow ? ' readonly tabindex="-1"' : '') +
            ' placeholder="' + escapeHtml(safeText(placeholder)) + '">';
    }

    function makeRowSelectCheckbox(rowId) {
        var checked = safeText(rowId) !== '' && safeText(rowId) === safeText(selectedMainRowId);
        return '<input type="checkbox" class="main-row-checkbox" data-row-id="' + escapeHtml(safeText(rowId)) + '"' + (checked ? ' checked' : '') + '>';
    }

    function showAlert(type, message) {
        var map = {
            success: 'alert-success',
            info: 'alert-info',
            warning: 'alert-warning',
            danger: 'alert-danger'
        };

        var cssClass = map[type] || 'alert-info';
        $('#cpAlert')
            .removeClass('d-none alert-success alert-info alert-warning alert-danger')
            .addClass(cssClass)
            .text(message);
    }

    function hideAlert() {
        $('#cpAlert')
            .addClass('d-none')
            .removeClass('alert-success alert-info alert-warning alert-danger')
            .text('');
    }

    function showPositionUpdateLoading(message) {
        $('#positionUpdateLoadingText').text(safeText(message) || 'Memproses update posisi saat ini...');
        $('#positionUpdateLoadingOverlay').addClass('active').attr('aria-hidden', 'false');
    }

    function hidePositionUpdateLoading() {
        $('#positionUpdateLoadingOverlay').removeClass('active').attr('aria-hidden', 'true');
    }

    function showPositionUpdateSuccess(message) {
        $('#positionUpdateSuccessText').text(safeText(message) || 'Posisi hari ini berhasil diperbarui.');
        $('#positionUpdateSuccessModal').modal('show');
    }

    function openPlanTypeDrawer() {
        $('#planTypeDrawer').addClass('open');
        $('#planTypeBackdrop').addClass('show');
    }

    function closePlanTypeDrawer() {
        $('#planTypeDrawer').removeClass('open');
        $('#planTypeBackdrop').removeClass('show');
    }

    function buildBreakSelectionKey(row) {
        if (!row || typeof row !== 'object') {
            return '';
        }
        var id = safeText(row.id);
        if (id !== '') {
            return id;
        }
        return safeText(row.break_time_name).toLowerCase();
    }

    function getBreakRowByKey(key) {
        var needle = safeText(key);
        if (!needle) {
            return null;
        }
        var found = null;
        $.each(breakTimeRows, function (_, row) {
            if (buildBreakSelectionKey(row) === needle) {
                found = row;
                return false;
            }
        });
        return found;
    }

    function getSelectedBreakRowsList() {
        return Object.keys(selectedBreakRows).map(function (key) {
            return selectedBreakRows[key];
        });
    }

    function updateBreakSubmitButtonState() {
        $('#btnSubmitBreakTime').prop('disabled', getSelectedBreakRowsList().length === 0);
    }

    function clearBreakSelection() {
        selectedBreakRows = {};
        $('#breakTimeTable tbody tr').removeClass('table-active');
        $('#breakTimeTable tbody .break-row-check').prop('checked', false);
        updateBreakSubmitButtonState();
    }

    function ensureBreakTimeRowsReady() {
        if (Array.isArray(breakTimeRows) && breakTimeRows.length > 0) {
            return $.Deferred().resolve().promise();
        }

        return $.getJSON(apiUrl, { action: 'break_list' }).done(function (res) {
            if (res && res.success === true) {
                breakTimeRows = Array.isArray(res.data) ? res.data : [];
            }
        });
    }

    function applyBreakSelectionToTable() {
        $('#breakTimeTable tbody tr[data-break-key]').removeClass('table-active').each(function () {
            var key = safeText($(this).attr('data-break-key'));
            var checked = key && Object.prototype.hasOwnProperty.call(selectedBreakRows, key);
            $(this).find('.break-row-check').prop('checked', checked);
            if (checked) {
                $(this).addClass('table-active');
            }
        });
        updateBreakSubmitButtonState();
    }

    function findPlanTypeCaseInsensitive(target) {
        var needle = safeText(target).toLowerCase();
        var found = '';

        $.each(planTypeRows, function (_, row) {
            var type = safeText(row.plan_type);
            if (type.toLowerCase() === needle) {
                found = type;
                return false;
            }
        });

        return found;
    }

    function normalizePlanTypeKey(value) {
        return safeText(value).toLowerCase().replace(/\s+/g, ' ');
    }

    function getMainTableLayoutKey() {
        var key = normalizePlanTypeKey(selectedPlanType);
        if (key === 'bakar bulu') {
            return 'bakar_bulu';
        }
        if (key === 'mikwang' || key === 'mik wang') {
            return 'mikwang';
        }
        if (key === 'jet dyeing' || key === 'jetdyeing' || key === 'jet-dyeing') {
            return 'jet_dyeing';
        }
        if (
            key === 'washing & padsteam' ||
            key === 'washing and padsteam' ||
            key === 'washing padsteam' ||
            key === 'washing pad steam' ||
            key === 'washing & pad steam'
        ) {
            return 'washing_padsteam';
        }
        if (key === 'cpb') {
            return 'cpb';
        }
        if (key === 'paddry' || key === 'planning paddry' || key === 'plan paddry') {
            return 'paddry';
        }
        if (key === 'scouring') {
            return 'scouring';
        }
        if (key === 'presett' || key === 'pre sett') {
            return 'presett';
        }
        return 'default';
    }

    function getMainTableNoDataColspan() {
        var layoutKey = getMainTableLayoutKey();
        if (layoutKey === 'bakar_bulu') {
            return 22;
        }
        if (layoutKey === 'scouring') {
            return 25;
        }
        if (layoutKey === 'presett') {
            return 21;
        }
        if (layoutKey === 'paddry') {
            return 41;
        }
        if (layoutKey === 'mikwang') {
            return 17;
        }
        if (layoutKey === 'jet_dyeing') {
            return 22;
        }
        if (layoutKey === 'washing_padsteam') {
            return 25;
        }
        if (layoutKey === 'cpb') {
            return 29;
        }
        return 30;
    }

    function getMainTableQtyColumnIndex() {
        var layoutKey = getMainTableLayoutKey();
        if (layoutKey === 'scouring') {
            return 6;
        }
        if (layoutKey === 'presett') {
            return 8;
        }
        return 7;
    }

    function parseQtyCellValue(value) {
        var raw = safeText(value);
        if (!raw || raw === '-') {
            return NaN;
        }

        var normalized = raw.replace(/\s+/g, '');
        if (normalized.indexOf(',') !== -1 && normalized.indexOf('.') !== -1) {
            normalized = normalized.replace(/,/g, '');
        } else if (normalized.indexOf(',') !== -1) {
            normalized = normalized.replace(/,/g, '');
        }

        var parsed = parseFloat(normalized);
        return Number.isFinite(parsed) ? parsed : NaN;
    }

    function formatQtyTotalValue(value) {
        if (!Number.isFinite(value)) {
            return '0';
        }
        return value.toLocaleString('en-US', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 3
        });
    }

    function formatCapacityValue(value) {
        if (!Number.isFinite(value)) {
            return '-';
        }
        return value.toLocaleString('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 3
        });
    }

    function calculateMainTableTotalQty(rows) {
        if (!Array.isArray(rows) || rows.length === 0) {
            return 0;
        }

        var totalQty = 0;
        rows.forEach(function (row) {
            if (!row || typeof row !== 'object') {
                return;
            }
            var qtyNumber = parseQtyCellValue(row.qty);
            if (!Number.isFinite(qtyNumber)) {
                return;
            }
            totalQty += qtyNumber;
        });
        return totalQty;
    }

    function calculateMainTableTotalActualQty(rows) {
        if (!Array.isArray(rows) || rows.length === 0) {
            return 0;
        }

        var totalActualQty = 0;
        rows.forEach(function (row) {
            if (!row || typeof row !== 'object') {
                return;
            }
            var actualQtyNumber = parseQtyCellValue(
                safeText(row.routing_qty || row.actual_realisasi || row.aktual_qty)
            );
            if (!Number.isFinite(actualQtyNumber)) {
                return;
            }
            totalActualQty += actualQtyNumber;
        });
        return totalActualQty;
    }

    function updateMachineCapacityIndicator() {
        var $target = $('#selectedMachineCapacity');
        if (!$target.length) {
            return;
        }

        var totalQty = calculateMainTableTotalQty(mainTableRows);
        var maxCapacity = getSelectedMachineMaxCapacity();
        var isOverCapacity = Number.isFinite(maxCapacity) && totalQty > maxCapacity;
        var valueClass = 'cp-capacity-value';
        if (Number.isFinite(maxCapacity)) {
            valueClass += isOverCapacity ? ' cp-capacity-over' : ' cp-capacity-under';
        }
        var valueLabel = formatCapacityValue(totalQty) + '/' + formatCapacityValue(maxCapacity);

        $target.html(
            'Capacity (Qty): <span class="' + valueClass + '">' + escapeHtml(valueLabel) + '</span>'
        );
    }

    function getCapacityOverLimitInfo() {
        var totalQty = calculateMainTableTotalQty(mainTableRows);
        var maxCapacity = getSelectedMachineMaxCapacity();
        if (!Number.isFinite(maxCapacity) || totalQty <= maxCapacity) {
            return null;
        }

        return {
            totalQty: totalQty,
            maxCapacity: maxCapacity,
            totalLabel: formatCapacityValue(totalQty),
            maxLabel: formatCapacityValue(maxCapacity)
        };
    }

    function askCapacityOverLimitConfirmation(info) {
        var deferred = $.Deferred();
        var overInfo = info || getCapacityOverLimitInfo();
        if (!overInfo) {
            deferred.resolve(true);
            return deferred.promise();
        }

        var $modal = $('#capacityOverLimitModal');
        if (!$modal.length) {
            var fallbackMessage = 'Total Qty (' + overInfo.totalLabel + ') melebihi Max Capacity (' + overInfo.maxLabel + '). Tetap simpan?';
            deferred.resolve(window.confirm(fallbackMessage) === true);
            return deferred.promise();
        }

        var selectedMachine = getSelectedMachineRow();
        var machineCode = selectedMachine ? (safeText(selectedMachine.facode) || '-') : (safeText(selectedMachineId) || '-');
        var machineName = selectedMachine ? (safeText(selectedMachine.faname) || '-') : '-';
        var machineLabel = machineName + ' (' + machineCode + ')';

        $('#capacityOverLimitMachine').text(machineLabel);
        $('#capacityOverLimitQty').text(overInfo.totalLabel + '/' + overInfo.maxLabel);

        var resolved = false;
        var finish = function (approved) {
            if (resolved) {
                return;
            }
            resolved = true;
            deferred.resolve(approved === true);
        };

        $modal.off('.cpOverCapacityConfirm');
        $('#btnCapacityKeepSave').off('.cpOverCapacityConfirm');
        $('#btnCapacityCancelSave').off('.cpOverCapacityConfirm');

        $('#btnCapacityKeepSave').on('click.cpOverCapacityConfirm', function () {
            finish(true);
            $modal.modal('hide');
        });

        $('#btnCapacityCancelSave').on('click.cpOverCapacityConfirm', function () {
            finish(false);
            $modal.modal('hide');
        });

        $modal.on('hidden.bs.modal.cpOverCapacityConfirm', function () {
            finish(false);
            $modal.off('.cpOverCapacityConfirm');
            $('#btnCapacityKeepSave').off('.cpOverCapacityConfirm');
            $('#btnCapacityCancelSave').off('.cpOverCapacityConfirm');
        });

        $modal.modal({
            backdrop: 'static',
            keyboard: false
        });

        return deferred.promise();
    }

    function appendMainTableQtyTotalRow($tbody, rows) {
        if (!$tbody || !$tbody.length || !Array.isArray(rows) || rows.length === 0) {
            return;
        }

        if (getMainTableLayoutKey() === 'paddry') {
            var totalPlanningQty = calculateMainTableTotalQty(rows);
            var totalRoutingQty = calculateMainTableTotalActualQty(rows);
            var totalColsPaddry = getMainTableNoDataColspan();
            var qtyColIndex = getMainTableQtyColumnIndex();
            var labelColspan = Math.max(1, qtyColIndex - 1);
            var totalFixedCols = 1 + labelColspan;                   // kolom Seq + label
            var trailingCols = Math.max(0, totalColsPaddry - totalFixedCols);

            var planningHtml = '<tr class="cp-total-row">' +
                '<td></td>' +
                '<td colspan="' + labelColspan + '" class="font-weight-bold cp-total-label-cell">Total Planning Qty : ' + escapeHtml(formatQtyTotalValue(totalPlanningQty)) + '</td>';
            for (var p = 0; p < trailingCols; p++) {
                planningHtml += '<td></td>';
            }
            planningHtml += '</tr>';
            $tbody.append(planningHtml);

            var routingHtml = '<tr class="cp-total-row">' +
                '<td></td>' +
                '<td colspan="' + labelColspan + '" class="font-weight-bold cp-total-label-cell">Total Routing Qty : ' + escapeHtml(formatQtyTotalValue(totalRoutingQty)) + '</td>';
            for (var r = 0; r < trailingCols; r++) {
                routingHtml += '<td></td>';
            }
            routingHtml += '</tr>';
            $tbody.append(routingHtml);
            return;
        }

        var totalQty = calculateMainTableTotalQty(rows);

        var totalCols = getMainTableNoDataColspan();
        var qtyColIndex = getMainTableQtyColumnIndex();
        var labelColspan = Math.max(1, qtyColIndex - 1);
        var trailingCols = Math.max(0, totalCols - qtyColIndex);
        var html = '<tr class="cp-total-row">' +
            '<td colspan="' + labelColspan + '" class="text-center font-weight-bold">Total Qty</td>' +
            '<td class="text-right font-weight-bold">' + escapeHtml(formatQtyTotalValue(totalQty)) + '</td>';

        for (var i = 0; i < trailingCols; i++) {
            html += '<td></td>';
        }
        html += '</tr>';
        $tbody.append(html);
    }

    function renderMainTableHeader() {
        var $head = $('#cpMainTableHead');
        if (!$head.length) {
            return;
        }

        if (getMainTableLayoutKey() === 'bakar_bulu') {
            $head.html(
                '<tr>' +
                    '<th rowspan="2">Seq</th>' +
                    '<th rowspan="2">CP No</th>' +
                    '<th rowspan="2">Label Jual</th>' +
                    '<th rowspan="2">Cust Color</th>' +
                    '<th rowspan="2">Kode Lab</th>' +
                    '<th rowspan="2">Routing Name</th>' +
                    '<th rowspan="2">Qty</th>' +
                    '<th rowspan="2">Material Name</th>' +
                    '<th colspan="4">Paddry Planning</th>' +
                    '<th rowspan="2">Plant Description</th>' +
                    '<th colspan="5">Actual</th>' +
                    '<th rowspan="2">Posisi Hari Ini</th>' +
                    '<th rowspan="2">Next Routing</th>' +
                    '<th rowspan="2">Last Update</th>' +
                    '<th rowspan="2">Updated By</th>' +
                '</tr>' +
                '<tr>' +
                    '<th>Machine</th>' +
                    '<th>Date</th>' +
                    '<th>Start</th>' +
                    '<th>End</th>' +
                    '<th>Date</th>' +
                    '<th>Start</th>' +
                    '<th>End</th>' +
                    '<th>Shift</th>' +
                    '<th>Realisasi</th>' +
                '</tr>'
            );
            return;
        }

        if (getMainTableLayoutKey() === 'scouring') {
            $head.html(
                '<tr>' +
                    '<th rowspan="2">Seq</th>' +
                    '<th rowspan="2">CP No</th>' +
                    '<th rowspan="2">Label Jual</th>' +
                    '<th rowspan="2">Cust Color</th>' +
                    '<th rowspan="2">Kode Lab</th>' +
                    '<th rowspan="2">Qty</th>' +
                    '<th rowspan="2">Material Name</th>' +
                    '<th rowspan="2">Speed</th>' +
                    '<th rowspan="2">Grammature</th>' +
                    '<th rowspan="2">Machine</th>' +
                    '<th colspan="2">Paddry Planning</th>' +
                    '<th rowspan="2">Plan Description</th>' +
                    '<th colspan="3">Aktual Proses</th>' +
                    '<th rowspan="2">Oprator</th>' +
                    '<th rowspan="2">Shift</th>' +
                    '<th rowspan="2">Down Time (Mnt)</th>' +
                    '<th rowspan="2">Realisasi</th>' +
                    '<th rowspan="2">Posisi Hari Ini</th>' +
                    '<th rowspan="2">Next Routing</th>' +
                    '<th rowspan="2">Last Update</th>' +
                    '<th rowspan="2">Updated By</th>' +
                '</tr>' +
                '<tr>' +
                    '<th>Tgl</th>' +
                    '<th>Paddry</th>' +
                    '<th>Tgl</th>' +
                    '<th>Start</th>' +
                    '<th>Finish</th>' +
                '</tr>'
            );
            return;
        }

        if (getMainTableLayoutKey() === 'presett') {
            $head.html(
                '<tr>' +
                    '<th rowspan="2">Seq</th>' +
                    '<th rowspan="2">No CP</th>' +
                    '<th rowspan="2">Urut Plan</th>' +
                    '<th rowspan="2">Label Jual</th>' +
                    '<th rowspan="2">Cust Color</th>' +
                    '<th rowspan="2">Kode Lab</th>' +
                    '<th rowspan="2">Material Name</th>' +
                    '<th rowspan="2">Qty</th>' +
                    '<th rowspan="2">Ket</th>' +
                    '<th rowspan="2">Posisi Hari Ini</th>' +
                    '<th colspan="2">Rencana Paddry</th>' +
                    '<th colspan="2">Aktual Proses</th>' +
                    '<th rowspan="2">Operator</th>' +
                    '<th rowspan="2">Shift</th>' +
                    '<th rowspan="2">Status</th>' +
                    '<th rowspan="2">Downtime (Mnt)</th>' +
                    '<th rowspan="2">Keterangan</th>' +
                    '<th rowspan="2">Last Update</th>' +
                    '<th rowspan="2">Update By</th>' +
                '</tr>' +
                '<tr>' +
                    '<th>Tgl</th>' +
                    '<th>Paddry</th>' +
                    '<th>Start</th>' +
                    '<th>Finish</th>' +
                '</tr>'
            );
            return;
        }

        if (getMainTableLayoutKey() === 'paddry') {
            $head.html(
                '<tr>' +
                    '<th rowspan="2">Seq</th>' +
                    '<th rowspan="2">CP No</th>' +
                    '<th rowspan="2">Label</th>' +
                    '<th rowspan="2">Cust Color</th>' +
                    '<th rowspan="2">Kode Lab</th>' +
                    '<th rowspan="2">Material Name</th>' +
                    '<th rowspan="2">Planning Qty</th>' +
                    '<th rowspan="2">Routing Qty</th>' +
                    '<th rowspan="2">Posisi Hari Ini</th>' +
                    '<th rowspan="2">Speed</th>' +
                    '<th rowspan="2">Resep Lipat</th>' +
                    '<th rowspan="2">Status Resep</th>' +
                    '<th rowspan="2">Vlot Resep</th>' +
                    '<th rowspan="2">Bon Penimbangan</th>' +
                    '<th rowspan="2">Kategori Penimbangan</th>' +
                    '<th rowspan="2">Tgl</th>' +
                    '<th rowspan="2">Est Tmbng Plrtn LA/LAB</th>' +
                    '<th colspan="2">Act. Tmbg LAB</th>' +
                    '<th colspan="2">Act. Tmbg LA</th>' +
                    '<th colspan="2">Act. Larut LAB</th>' +
                    '<th colspan="2">Act. Larut LA</th>' +
                    '<th rowspan="2">Est Plrtm Prdks</th>' +
                    '<th colspan="2">Act. Larut Produksi</th>' +
                    '<th colspan="2">Rencana Celup</th>' +
                    '<th colspan="2">Aktual Celup</th>' +
                    '<th colspan="2">Aktual Topping Paddry</th>' +
                    '<th rowspan="2">Vlot Aktual</th>' +
                    '<th rowspan="2">Sisa Larut</th>' +
                    '<th rowspan="2">Sample Kain</th>' +
                    '<th rowspan="2">RKO</th>' +
                    '<th rowspan="2">Next Routing</th>' +
                    '<th rowspan="2">Last Update</th>' +
                    '<th rowspan="2">Update By</th>' +
                '</tr>' +
                '<tr>' +
                    '<th>Start</th>' +
                    '<th>Finish</th>' +
                    '<th>Start</th>' +
                    '<th>Finish</th>' +
                    '<th>Start</th>' +
                    '<th>Finish</th>' +
                    '<th>Start</th>' +
                    '<th>Finish</th>' +
                    '<th>Start</th>' +
                    '<th>Finish</th>' +
                    '<th>Start</th>' +
                    '<th>Finish</th>' +
                    '<th>Start</th>' +
                    '<th>Finish</th>' +
                    '<th>Start</th>' +
                    '<th>Finish</th>' +
                '</tr>'
            );
            return;
        }

        if (getMainTableLayoutKey() === 'cpb') {
            $head.html(
                '<tr>' +
                    '<th rowspan="2">Seq</th>' +
                    '<th rowspan="2">CP No</th>' +
                    '<th rowspan="2">Label Jual</th>' +
                    '<th rowspan="2">Cust Color</th>' +
                    '<th rowspan="2">Kode Lab</th>' +
                    '<th rowspan="2">Routing Name</th>' +
                    '<th rowspan="2">Qty</th>' +
                    '<th rowspan="2">Speed</th>' +
                    '<th rowspan="2">Material Name</th>' +
                    '<th colspan="3">Planning</th>' +
                    '<th colspan="3">Larutan Di Resep</th>' +
                    '<th colspan="2">Larutan Awal</th>' +
                    '<th colspan="3">Larutan Sisa</th>' +
                    '<th rowspan="2">Plan Description</th>' +
                    '<th colspan="3">Actual</th>' +
                    '<th rowspan="2">Posisi Hari Ini</th>' +
                    '<th rowspan="2">Next Routing</th>' +
                    '<th rowspan="2">Sample</th>' +
                    '<th rowspan="2">Last Update</th>' +
                    '<th rowspan="2">Update By</th>' +
                '</tr>' +
                '<tr>' +
                    '<th>Date</th>' +
                    '<th>Start</th>' +
                    '<th>End</th>' +
                    '<th>Alkali</th>' +
                    '<th>Dyestuff</th>' +
                    '<th>BE</th>' +
                    '<th>Alkali</th>' +
                    '<th>Dyestuff</th>' +
                    '<th>Alkali</th>' +
                    '<th>Dyestuff</th>' +
                    '<th>Satulator</th>' +
                    '<th>Date</th>' +
                    '<th>Start</th>' +
                    '<th>End</th>' +
                '</tr>'
            );
            return;
        }

        if (getMainTableLayoutKey() === 'washing_padsteam') {
            $head.html(
                '<tr>' +
                    '<th rowspan="2">Seq</th>' +
                    '<th rowspan="2">CP No</th>' +
                    '<th rowspan="2">Label Jual</th>' +
                    '<th rowspan="2">Cust Color</th>' +
                    '<th rowspan="2">Kode Lab</th>' +
                    '<th rowspan="2">Routing Name</th>' +
                    '<th rowspan="2">Qty</th>' +
                    '<th rowspan="2">Speed</th>' +
                    '<th rowspan="2">Material Name</th>' +
                    '<th colspan="3">CPB Actual</th>' +
                    '<th colspan="3">Planning</th>' +
                    '<th rowspan="2">(BT Jam)</th>' +
                    '<th rowspan="2">Plan Description</th>' +
                    '<th colspan="3">Actual</th>' +
                    '<th rowspan="2">Posisi Hari Ini</th>' +
                    '<th rowspan="2">Next Routing</th>' +
                    '<th rowspan="2">Sample</th>' +
                    '<th rowspan="2">Last Update</th>' +
                    '<th rowspan="2">Update By</th>' +
                '</tr>' +
                '<tr>' +
                    '<th>Date</th>' +
                    '<th>Start</th>' +
                    '<th>End</th>' +
                    '<th>Date</th>' +
                    '<th>Start</th>' +
                    '<th>End</th>' +
                    '<th>Date</th>' +
                    '<th>Start</th>' +
                    '<th>End</th>' +
                '</tr>'
            );
            return;
        }

        if (getMainTableLayoutKey() === 'jet_dyeing') {
            $head.html(
                '<tr>' +
                    '<th rowspan="2">Seq</th>' +
                    '<th rowspan="2">CP No</th>' +
                    '<th rowspan="2">Label Jual</th>' +
                    '<th rowspan="2">Cust Color</th>' +
                    '<th rowspan="2">Kode Lab</th>' +
                    '<th rowspan="2">Routing Name</th>' +
                    '<th rowspan="2">Qty</th>' +
                    '<th rowspan="2">Speed</th>' +
                    '<th rowspan="2">Material Name</th>' +
                    '<th colspan="3">Planning</th>' +
                    '<th rowspan="2">Bon Resep</th>' +
                    '<th rowspan="2">Resep ke Gdg</th>' +
                    '<th rowspan="2">Plan Description</th>' +
                    '<th colspan="3">Actual</th>' +
                    '<th rowspan="2">Posisi Hari Ini</th>' +
                    '<th rowspan="2">Next Routing</th>' +
                    '<th rowspan="2">Last Update</th>' +
                    '<th rowspan="2">Update By</th>' +
                '</tr>' +
                '<tr>' +
                    '<th>Date</th>' +
                    '<th>Start</th>' +
                    '<th>End</th>' +
                    '<th>Date</th>' +
                    '<th>Start</th>' +
                    '<th>End</th>' +
                '</tr>'
            );
            return;
        }

        if (getMainTableLayoutKey() === 'mikwang') {
            $head.html(
                '<tr>' +
                    '<th rowspan="2">Seq</th>' +
                    '<th rowspan="2">CP No</th>' +
                    '<th rowspan="2">Label Jual</th>' +
                    '<th rowspan="2">Cust Color</th>' +
                    '<th rowspan="2">Kode Lab</th>' +
                    '<th rowspan="2">Routing Name</th>' +
                    '<th rowspan="2">Qty</th>' +
                    '<th rowspan="2">Material Name</th>' +
                    '<th rowspan="2">Bon Resep</th>' +
                    '<th rowspan="2">Plan Description</th>' +
                    '<th colspan="3">Actual</th>' +
                    '<th rowspan="2">Posisi Hari Ini</th>' +
                    '<th rowspan="2">Next Routing</th>' +
                    '<th rowspan="2">Last Update</th>' +
                    '<th rowspan="2">Update By</th>' +
                '</tr>' +
                '<tr>' +
                    '<th>Date</th>' +
                    '<th>Start</th>' +
                    '<th>End</th>' +
                '</tr>'
            );
            return;
        }

        $head.html(
            '<tr>' +
                '<th rowspan="2">Seq</th>' +
                '<th rowspan="2">CP No</th>' +
                '<th rowspan="2">Label Jual</th>' +
                '<th rowspan="2">Cust Color</th>' +
                '<th rowspan="2">Kode Lab</th>' +
                '<th rowspan="2">Routing Name</th>' +
                '<th rowspan="2">Qty</th>' +
                '<th rowspan="2">Speed</th>' +
                '<th rowspan="2">Material Name</th>' +
                '<th colspan="8">Paddry Planning</th>' +
                '<th rowspan="2">Plan Description</th>' +
                '<th colspan="7">Actual</th>' +
                '<th rowspan="2">Posisi Hari Ini</th>' +
                '<th rowspan="2">Next Routing</th>' +
                '<th rowspan="2">Sample</th>' +
                '<th rowspan="2">Last Update</th>' +
                '<th rowspan="2">Updated By</th>' +
            '</tr>' +
            '<tr>' +
                '<th>Date</th>' +
                '<th>Start</th>' +
                '<th>End</th>' +
                '<th>RKO</th>' +
                '<th>Resp Lipat</th>' +
                '<th>Status Resp</th>' +
                '<th>VLot Resp</th>' +
                '<th>Bon Resp</th>' +
                '<th>Date</th>' +
                '<th>Start</th>' +
                '<th>End</th>' +
                '<th>Shift</th>' +
                '<th>VLot</th>' +
                '<th>Sisa di Tanggal</th>' +
                '<th>Sisa Saturator</th>' +
            '</tr>'
        );
    }

    function formatDateTime(val) {
        if (!val) return '-';
        var dt = new Date(val);
        if (!isNaN(dt.getTime())) {
            return dt.toLocaleString('id-ID');
        }
        return String(val);
    }

    function renderPlanTypeList() {
        var $list = $('#planTypeList');
        $list.empty();

        if (!planTypeRows.length) {
            $list.append(
                $('<div class="text-muted small">Belum ada data tipe planning.</div>')
            );
            return;
        }

        $.each(planTypeRows, function (_, row) {
            var type = safeText(row.plan_type);
            if (!type) {
                return;
            }

            var $btn = $('<button type="button" class="plan-type-item"></button>');
            $btn.attr('data-type', type);
            $btn.text(type);
            if (type === selectedPlanType) {
                $btn.addClass('active');
            }

            $list.append($btn);
        });
    }

    function renderBreakTimeTable() {
        var $tbody = $('#breakTimeTable tbody');
        $tbody.empty();

        var keyword = safeText($('#breakTimeSearch').val()).toLowerCase();
        var filtered = breakTimeRows.filter(function (row) {
            if (!keyword) return true;
            return safeText(row.break_time_name).toLowerCase().indexOf(keyword) >= 0;
        });

        if (!filtered.length) {
            $tbody.append('<tr><td colspan="6" class="text-center text-muted">Belum ada data break time.</td></tr>');
            $('#breakTimeCount').text('0 data');
            updateBreakSubmitButtonState();
            return;
        }

        filtered.forEach(function (row, idx) {
            var rowKey = buildBreakSelectionKey(row);
            var checked = rowKey && Object.prototype.hasOwnProperty.call(selectedBreakRows, rowKey);
            var html = '<tr data-break-key="' + escapeHtml(rowKey) + '"' + (checked ? ' class="table-active"' : '') + '>' +
                '<td class="text-center"><input type="checkbox" class="break-row-check" data-break-key="' + escapeHtml(rowKey) + '"' + (checked ? ' checked' : '') + '></td>' +
                '<td class="text-center">' + (idx + 1) + '</td>' +
                '<td>' + safeText(row.break_time_name) + '</td>' +
                '<td class="text-center">' + safeText(row.break_time_minutes || 0) + '</td>' +
                '<td>' + formatDateTime(row.upddate) + '</td>' +
                '<td>' + safeText(row.upduser) + '</td>' +
                '</tr>';
            $tbody.append(html);
        });

        $('#breakTimeCount').text(filtered.length + ' data');
        applyBreakSelectionToTable();
    }

    function loadBreakTimeList() {
        $('#breakTimeTable tbody').html('<tr><td colspan="6" class="text-center text-muted">Memuat data...</td></tr>');
        $('#breakTimeCount').text('0 data');

        return $.getJSON(apiUrl, { action: 'break_list' }).done(function (res) {
            if (!res || res.success !== true) {
                breakTimeRows = [];
                renderBreakTimeTable();
                showAlert('danger', (res && res.message) ? res.message : 'Gagal memuat data break time.');
                return;
            }

            breakTimeRows = Array.isArray(res.data) ? res.data : [];
            renderBreakTimeTable();
        }).fail(function () {
            breakTimeRows = [];
            renderBreakTimeTable();
            showAlert('danger', 'Gagal memuat data break time.');
        });
    }

    function renderMachineDetail(row) {
        if (!row) {
            $('#selectedMachineTitle').text('Machine: -');
            updateMachineCapacityIndicator();
            return;
        }

        var machineCode = safeText(row.facode) || '-';
        var machineName = safeText(row.faname) || '-';

        $('#selectedMachineTitle').text('Machine: ' + machineName + ' (' + machineCode + ')');
        updateMachineCapacityIndicator();
    }

    function loadMachineMaxCapacityByPlanType(planType) {
        var canonical = findPlanTypeCaseInsensitive(planType);
        if (!canonical) {
            resetMachineMaxCapacityMap();
            updateMachineCapacityIndicator();
            return $.Deferred().resolve().promise();
        }

        var requestTypeKey = safeText(canonical).toLowerCase();

        return $.getJSON(apiUrl, {
            action: 'max_capacity_list',
            plan_type: canonical
        }).done(function (res) {
            if (safeText(selectedPlanType).toLowerCase() !== requestTypeKey) {
                return;
            }
            if (!res || res.success !== true) {
                resetMachineMaxCapacityMap();
                updateMachineCapacityIndicator();
                return;
            }

            setMachineMaxCapacityRows(Array.isArray(res.data) ? res.data : []);
            updateMachineCapacityIndicator();
        }).fail(function () {
            if (safeText(selectedPlanType).toLowerCase() !== requestTypeKey) {
                return;
            }
            resetMachineMaxCapacityMap();
            updateMachineCapacityIndicator();
        });
    }

    function loadBakarBuluPersistedRows(options) {
        var opts = options || {};
        var silent = opts.silent === true;

        if (getMainTableLayoutKey() !== 'bakar_bulu') {
            return $.Deferred().resolve().promise();
        }

        var periodIso = safeText($('#periodDate').val());
        var machineId = safeText(selectedMachineId);

        if (!periodIso || !machineId) {
            mainTableRows = [];
            clearOrderDirtyState();
            renderMainTable(mainTableRows);
            return $.Deferred().resolve().promise();
        }

        return $.ajax({
            url: '/gg_app/pages/planning/cp_planning/cp_planning.php?action=load_bakar_bulu',
            type: 'POST',
            dataType: 'json',
            data: {
                machine_id: machineId,
                period_date: periodIso
            }
        }).done(function (res) {
            if (!res || res.success !== true) {
                mainTableRows = [];
                clearOrderDirtyState();
                renderMainTable(mainTableRows);
                if (!silent) {
                    showAlert('danger', (res && res.message) ? res.message : 'Gagal memuat data bakar bulu.');
                }
                return;
            }

            mainTableRows = Array.isArray(res.data) ? res.data : [];
            clearOrderDirtyState();
            renderMainTable(mainTableRows);
            if (!silent) {
                hideAlert();
            }
        }).fail(function () {
            mainTableRows = [];
            clearOrderDirtyState();
            renderMainTable(mainTableRows);
            if (!silent) {
                showAlert('danger', 'Gagal memuat data bakar bulu.');
            }
        });
    }

    function loadPaddryPersistedRows(options) {
        var opts = options || {};
        var silent = opts.silent === true;

        if (getMainTableLayoutKey() !== 'paddry') {
            return $.Deferred().resolve().promise();
        }

        var periodIso = safeText($('#periodDate').val());
        var machineId = safeText(selectedMachineId);

        if (!periodIso || !machineId) {
            mainTableRows = [];
            clearOrderDirtyState();
            renderMainTable(mainTableRows);
            return $.Deferred().resolve().promise();
        }

        return $.ajax({
            url: '/gg_app/pages/planning/cp_planning/cp_planning.php?action=load_paddry',
            type: 'POST',
            dataType: 'json',
            data: {
                machine_id: machineId,
                period_date: periodIso
            }
        }).done(function (res) {
            if (!res || res.success !== true) {
                mainTableRows = [];
                clearOrderDirtyState();
                renderMainTable(mainTableRows);
                if (!silent) {
                    showAlert('danger', (res && res.message) ? res.message : 'Gagal memuat data paddry.');
                }
                return;
            }

            mainTableRows = Array.isArray(res.data) ? res.data : [];
            clearOrderDirtyState();
            renderMainTable(mainTableRows);
            if (!silent) {
                hideAlert();
            }
        }).fail(function () {
            mainTableRows = [];
            clearOrderDirtyState();
            renderMainTable(mainTableRows);
            if (!silent) {
                showAlert('danger', 'Gagal memuat data paddry.');
            }
        });
    }

    function loadScouringPersistedRows(options) {
        var opts = options || {};
        var silent = opts.silent === true;

        if (getMainTableLayoutKey() !== 'scouring') {
            return $.Deferred().resolve().promise();
        }

        var periodIso = safeText($('#periodDate').val());
        var machineId = safeText(selectedMachineId);

        if (!periodIso || !machineId) {
            mainTableRows = [];
            clearOrderDirtyState();
            renderMainTable(mainTableRows);
            return $.Deferred().resolve().promise();
        }

        return $.ajax({
            url: '/gg_app/pages/planning/cp_planning/cp_planning.php?action=load_scouring',
            type: 'POST',
            dataType: 'json',
            data: {
                machine_id: machineId,
                period_date: periodIso
            }
        }).done(function (res) {
            if (!res || res.success !== true) {
                mainTableRows = [];
                clearOrderDirtyState();
                renderMainTable(mainTableRows);
                if (!silent) {
                    showAlert('danger', (res && res.message) ? res.message : 'Gagal memuat data scouring.');
                }
                return;
            }

            mainTableRows = Array.isArray(res.data) ? res.data : [];
            clearOrderDirtyState();
            renderMainTable(mainTableRows);
            if (!silent) {
                hideAlert();
            }
        }).fail(function () {
            mainTableRows = [];
            clearOrderDirtyState();
            renderMainTable(mainTableRows);
            if (!silent) {
                showAlert('danger', 'Gagal memuat data scouring.');
            }
        });
    }

    function loadPreSettPersistedRows(options) {
        var opts = options || {};
        var silent = opts.silent === true;

        if (getMainTableLayoutKey() !== 'presett') {
            return $.Deferred().resolve().promise();
        }

        var periodIso = safeText($('#periodDate').val());
        var machineId = safeText(selectedMachineId);

        if (!periodIso || !machineId) {
            mainTableRows = [];
            clearOrderDirtyState();
            renderMainTable(mainTableRows);
            return $.Deferred().resolve().promise();
        }

        return $.ajax({
            url: '/gg_app/pages/planning/cp_planning/cp_planning.php?action=load_presett',
            type: 'POST',
            dataType: 'json',
            data: {
                machine_id: machineId,
                period_date: periodIso
            }
        }).done(function (res) {
            if (!res || res.success !== true) {
                mainTableRows = [];
                clearOrderDirtyState();
                renderMainTable(mainTableRows);
                if (!silent) {
                    showAlert('danger', (res && res.message) ? res.message : 'Gagal memuat data presett.');
                }
                return;
            }

            mainTableRows = Array.isArray(res.data) ? res.data : [];
            clearOrderDirtyState();
            renderMainTable(mainTableRows);
            if (!silent) {
                hideAlert();
            }
        }).fail(function () {
            mainTableRows = [];
            clearOrderDirtyState();
            renderMainTable(mainTableRows);
            if (!silent) {
                showAlert('danger', 'Gagal memuat data presett.');
            }
        });
    }

    function setSelectedMachine(machineId) {
        selectedMachineId = safeText(machineId);
        selectedMainRowId = '';

        $('#machineList .machine-item').removeClass('active');
        $('#machineList .machine-item').each(function () {
            if (safeText($(this).attr('data-id')) === selectedMachineId) {
                $(this).addClass('active');
            }
        });

        var picked = null;
        $.each(machineRows, function (_, row) {
            if (safeText(row.id) === selectedMachineId) {
                picked = row;
                return false;
            }
        });

        renderMachineDetail(picked);

        if (getMainTableLayoutKey() === 'bakar_bulu') {
            loadBakarBuluPersistedRows({ silent: false });
        } else if (getMainTableLayoutKey() === 'paddry') {
            loadPaddryPersistedRows({ silent: false });
        } else if (getMainTableLayoutKey() === 'scouring') {
            loadScouringPersistedRows({ silent: false });
        } else if (getMainTableLayoutKey() === 'presett') {
            loadPreSettPersistedRows({ silent: false });
        } else {
            mainTableRows = [];
            clearOrderDirtyState();
            renderMainTable(mainTableRows);
        }
    }

    function renderMachineList() {
        var $list = $('#machineList');
        $list.empty();

        if (!selectedPlanType) {
            $list.append(
                $('<div class="text-muted small p-3">Pilih tipe planning terlebih dahulu.</div>')
            );
            renderMachineDetail(null);
            return;
        }

        if (!machineRows.length) {
            $list.append(
                $('<div class="text-muted small p-3">Belum ada machine pada tipe ini.</div>')
            );
            renderMachineDetail(null);
            return;
        }

        $.each(machineRows, function (_, row) {
            var machineId = safeText(row.id);
            var code = safeText(row.facode);
            var name = safeText(row.faname);

            var $item = $('<button type="button" class="machine-item"></button>');
            $item.attr('data-id', machineId);
            $item.append($('<span class="machine-name"></span>').text(name || '-'));
            $item.append($('<span class="machine-code"></span>').text(code || '-'));

            $list.append($item);
        });

        var hasCurrentSelection = false;
        $.each(machineRows, function (_, row) {
            if (safeText(row.id) === selectedMachineId) {
                hasCurrentSelection = true;
                return false;
            }
        });

        if (!hasCurrentSelection) {
            selectedMachineId = safeText(machineRows[0].id);
        }
        setSelectedMachine(selectedMachineId);
    }

    function loadMachineByPlanType(planType) {
        var canonical = findPlanTypeCaseInsensitive(planType);
        if (!canonical) {
            selectedPlanType = '';
            selectedMachineId = '';
            resetMachineMaxCapacityMap();
            $('#selectedPlanType').val('');
            syncCpSearchByPlanType();
            machineRows = [];
            mainTableRows = [];
            selectedMainRowId = '';
            clearOrderDirtyState();
            renderMainTableHeader();
            queueMainTableStickyHeaderOffsetSync();
            initializeMainTableColumnResize();
            renderMainTable(mainTableRows);
            renderMachineList();
            showAlert('warning', 'Tipe planning tidak valid.');
            return $.Deferred().reject().promise();
        }

        selectedPlanType = canonical;
        $('#selectedPlanType').val(selectedPlanType);
        resetMachineMaxCapacityMap();
        syncCpSearchByPlanType();
        renderPlanTypeList();
        mainTableRows = [];
        selectedMainRowId = '';
        clearOrderDirtyState();
        renderMainTableHeader();
        queueMainTableStickyHeaderOffsetSync();
        initializeMainTableColumnResize();
        renderMainTable(mainTableRows);

        hideAlert();
        $('#machineList').html('<div class="text-muted small p-3">Memuat machine...</div>');
        loadMachineMaxCapacityByPlanType(selectedPlanType);

        return $.getJSON(apiUrl, {
            action: 'machine_list',
            plan_type: selectedPlanType
        }).done(function (res) {
            if (!res || res.success !== true) {
                machineRows = [];
                selectedMachineId = '';
                renderMachineList();
                showAlert('danger', (res && res.message) ? res.message : 'Gagal memuat data machine.');
                return;
            }

            machineRows = Array.isArray(res.data) ? res.data : [];
            machineRows.sort(function (a, b) {
                return safeText(a.facode).localeCompare(safeText(b.facode), 'id', { sensitivity: 'base' });
            });

            selectedMachineId = '';
            renderMachineList();
        }).fail(function () {
            machineRows = [];
            selectedMachineId = '';
            renderMachineList();
            showAlert('danger', 'Gagal memuat data machine.');
        });
    }

    function loadPlanTypes() {
        hideAlert();
        $('#planTypeList').html('<div class="text-muted small">Memuat tipe planning...</div>');

        return $.getJSON(apiUrl, { action: 'plan_type_list' }).done(function (res) {
            if (!res || res.success !== true) {
                planTypeRows = [];
                renderPlanTypeList();
                showAlert('danger', (res && res.message) ? res.message : 'Gagal memuat tipe planning.');
                return;
            }

            planTypeRows = Array.isArray(res.data) ? res.data : [];
            renderPlanTypeList();
        }).fail(function () {
            planTypeRows = [];
            renderPlanTypeList();
            showAlert('danger', 'Gagal memuat tipe planning.');
        });
    }

    $('#btnOpenPlanTypeDrawer, #selectedPlanType').on('click', function () {
        openPlanTypeDrawer();
    });

    $('#btnClosePlanTypeDrawer, #planTypeBackdrop').on('click', function () {
        closePlanTypeDrawer();
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            closePlanTypeDrawer();
        }
    });

    $('#planTypeList').on('click', '.plan-type-item', function () {
        var pickedType = safeText($(this).attr('data-type'));
        if (!pickedType) {
            return;
        }
        closePlanTypeDrawer();
        loadMachineByPlanType(pickedType);
    });

    $('#machineList').on('click', '.machine-item', function () {
        var machineId = safeText($(this).attr('data-id'));
        if (!machineId) {
            return;
        }

        if ((getMainTableLayoutKey() === 'bakar_bulu' || getMainTableLayoutKey() === 'paddry' || getMainTableLayoutKey() === 'scouring') && hasPendingRows()) {
            showAlert('warning', 'Masih ada data sementara. Klik checklist atau silang terlebih dahulu.');
            return;
        }

        setSelectedMachine(machineId);
    });

    $('#btnBreakTime').on('click', function () {
        clearBreakSelection();
        $('#breakTimeModal').modal('show');
        loadBreakTimeList();
    });

    $('#btnMoveDate').on('click', function () {
        openMoveDateModal();
    });

    $('#moveDateModal').on('hidden.bs.modal', function () {
        resetMoveDateModalState();
    });

    $('#cpScheduleModeMenu').on('click', '.schedule-mode-item', function () {
        var pickedMode = safeText($(this).attr('data-mode'));
        if (!pickedMode) {
            return;
        }
        applyPaddryInputMode(pickedMode);
    });

    $('#btnImportExcel').on('click', function () {
        if (getMainTableLayoutKey() !== 'paddry') {
            showAlert('warning', 'Import Excel hanya tersedia untuk tipe planning Paddry.');
            return;
        }
        if (!safeText(selectedPlanType)) {
            showAlert('warning', 'Pilih tipe planning Paddry terlebih dahulu.');
            return;
        }
        if (!safeText(selectedMachineId)) {
            showAlert('warning', 'Pilih machine terlebih dahulu.');
            return;
        }
        $('#paddryImportFile').val('');
        $('#paddryImportFile').trigger('click');
    });

    $('#paddryImportFile').on('change', function () {
        var input = this;
        if (!input || !input.files || !input.files.length) {
            return;
        }
        if (getMainTableLayoutKey() !== 'paddry') {
            showAlert('warning', 'Import Excel hanya tersedia untuk tipe planning Paddry.');
            input.value = '';
            return;
        }

        var file = input.files[0];
        var periodIso = safeText($('#periodDate').val());
        if (!periodIso) {
            showAlert('warning', 'Periode wajib diisi.');
            input.value = '';
            return;
        }
        if (!safeText(selectedMachineId)) {
            showAlert('warning', 'Pilih machine terlebih dahulu.');
            input.value = '';
            return;
        }

        var formData = new FormData();
        formData.append('import_file', file);
        formData.append('machine_id', safeText(selectedMachineId));
        formData.append('period_date', periodIso);
        formData.append('plan_type', safeText(selectedPlanType));

        var $btnImport = $('#btnImportExcel');
        $btnImport.prop('disabled', true);

        $.ajax({
            url: '/gg_app/pages/planning/cp_planning/cp_planning_import_api.php?action=import_paddry_excel',
            type: 'POST',
            dataType: 'json',
            data: formData,
            processData: false,
            contentType: false
        }).done(function (res) {
            if (!res || res.success !== true) {
                showAlert('danger', (res && res.message) ? res.message : 'Gagal import data Excel paddry.');
                return;
            }

            var importedRows = (res.data && Array.isArray(res.data.rows)) ? res.data.rows : [];
            if (!importedRows.length) {
                showAlert('warning', (res && res.message) ? res.message : 'Tidak ada data valid yang bisa diimport.');
                return;
            }
            if (importedRows.length > 200) {
                var preview = (res.data && Array.isArray(res.data.preview_cp)) ? res.data.preview_cp : [];
                var sheetName = safeText(res.data && res.data.sheet_name);
                var previewText = preview.length ? (' Contoh CP: ' + preview.join(', ') + '.') : '';
                var sheetText = sheetName ? (' Sheet terbaca: ' + sheetName + '.') : '';
                showAlert('danger', 'Import dihentikan karena terbaca ' + importedRows.length + ' baris (anomali, seharusnya sesuai file).'+ sheetText + previewText + ' Coba cek ulang sheet Excel yang dipilih.');
                return;
            }

            // Data dari Excel biasanya sudah berisi rencana aktual/manual.
            // Aktifkan mode manual agar nilai import tidak tertimpa kalkulasi otomatis.
            applyPaddryInputMode('manual', { silent: true });

            var periodDisplay = formatDateDisplayFromIso(periodIso) || formatTodayDisplay();
            var selectedMachine = getSelectedMachineRow();
            var machineLabel = '';
            if (selectedMachine) {
                machineLabel = safeText(selectedMachine.faname) || safeText(selectedMachine.facode);
            }

            var importedCount = 0;
            var nextImportedRows = [];
            importedRows.forEach(function (rowObj) {
                var mapped = buildImportedPaddryRow(rowObj, periodDisplay, machineLabel);
                if (safeText(mapped.no_cp) === '') {
                    return;
                }
                nextImportedRows.push(mapped);
                importedCount += 1;
            });

            if (importedCount <= 0) {
                showAlert('warning', 'Tidak ada data No CP valid yang berhasil diimport.');
                return;
            }

            mainTableRows = nextImportedRows;
            selectedMainRowId = '';
            clearOrderDirtyState();
            renderMainTable(mainTableRows);

            var skipped = Number(res.data && res.data.skipped_count);
            if (!Number.isFinite(skipped) || skipped < 0) {
                skipped = 0;
            }
            var info = importedCount + ' baris berhasil diimport dari Excel (replace data tabel saat ini).';
            if (skipped > 0) {
                info += ' ' + skipped + ' baris dilewati.';
            }
            info += ' Mode input paddry otomatis diubah ke manual.';
            showAlert('success', info);
        }).fail(function () {
            showAlert('danger', 'Gagal import data Excel paddry.');
        }).always(function () {
            $btnImport.prop('disabled', false);
            input.value = '';
        });
    });

    $('#breakTimeSearch').on('input', function () {
        renderBreakTimeTable();
    });

    $('#breakTimeModal').on('hidden.bs.modal', function () {
        clearBreakSelection();
        $('#breakTimeSearch').val('');
    });

    $('#breakTimeTable').on('click', 'tbody tr[data-break-key]', function (e) {
        if ($(e.target).is('input.break-row-check')) {
            return;
        }
        var key = safeText($(this).attr('data-break-key'));
        if (!key) {
            return;
        }
        if (Object.prototype.hasOwnProperty.call(selectedBreakRows, key)) {
            delete selectedBreakRows[key];
        } else {
            var rowObj = getBreakRowByKey(key);
            if (rowObj) {
                selectedBreakRows[key] = rowObj;
            }
        }
        applyBreakSelectionToTable();
    });

    $('#breakTimeTable').on('change', '.break-row-check', function (e) {
        e.stopPropagation();
        var key = safeText($(this).attr('data-break-key'));
        if (!key) {
            return;
        }
        if ($(this).is(':checked')) {
            var rowObj = getBreakRowByKey(key);
            if (rowObj) {
                selectedBreakRows[key] = rowObj;
            }
        } else {
            delete selectedBreakRows[key];
        }
        applyBreakSelectionToTable();
    });

    $('#btnSubmitBreakTime').on('click', function () {
        var selectedRows = getSelectedBreakRowsList();
        if (!selectedRows.length) {
            return;
        }

        if (!safeText(selectedPlanType)) {
            showAlert('warning', 'Pilih tipe planning terlebih dahulu.');
            return;
        }
        if (!safeText(selectedMachineId)) {
            showAlert('warning', 'Pilih machine terlebih dahulu.');
            return;
        }

        updateMainRowsFromInputs();
        var periodIso = safeText($('#periodDate').val());
        var periodDisplay = formatDateDisplayFromIso(periodIso) || formatTodayDisplay();

        var insertedCount = 0;
        selectedRows.forEach(function (rowObj) {
            var breakName = safeText(rowObj.break_time_name);
            var breakMinutes = parseInt(safeText(rowObj.break_time_minutes), 10);
            if (!Number.isFinite(breakMinutes) || breakMinutes < 0) {
                breakMinutes = 0;
            }
            if (!breakName) {
                return;
            }

            mainTableRows.push({
                _row_id: nextRowId(),
                _save_state: 'pending',
                is_break_time: true,
                break_time_name: breakName,
                break_time_minutes: breakMinutes,
                no_cp: breakName,
                tgl_cp: '',
                routing_code: '',
                routing_name: '',
                lokasi_paddry: '',
                product_code: '',
                product_name: '',
                work_center_code: '',
                work_center_name: '',
                kode_lab: '',
                color_name: '',
                label: '',
                cust_color: '',
                material: '',
                qty: '',
                grammature: '',
                speed: '',
                temperatur: '',
                plan_machine: '',
                plan_date: '',
                plan_paddry: '',
                plan_start: '00:00',
                plan_end: breakMinutes > 0 ? addMinutesToTime('00:00', breakMinutes) : '',
                ket: '',
                rko: '',
                resp_lipat: '',
                status_resp: '',
                vlot_resp: '',
                bon_resp: '',
                plan_description: '',
                actual_date: '',
                actual_start: '',
                actual_end: '',
                actual_timbang_lab_start: '',
                actual_timbang_lab_finish: '',
                actual_timbang_la_start: '',
                actual_timbang_la_finish: '',
                actual_larut_lab_start: '',
                actual_larut_lab_finish: '',
                actual_larut_la_start: '',
                actual_larut_la_finish: '',
                actual_timbang_lalab_start: '',
                actual_timbang_lalab_finish: '',
                actual_larut_lalab_start: '',
                actual_larut_lalab_finish: '',
                actual_larut_prdks_start: '',
                actual_larut_prdks_finish: '',
                actual_topping_paddry_start: '',
                actual_topping_paddry_finish: '',
                actual_operator: '',
                actual_shift: '',
                down_time: '',
                actual_wheel_no: '',
                actual_realisasi: '',
                actual_vlot: '',
                cpb_actual_date: '',
                cpb_actual_start: '',
                cpb_actual_end: '',
                bt_jam: '',
                resep_ke_gdg: '',
                est_tmbng_plrtm_lalab: '',
                est_plrtm_prdks: '',
                recipe_alkali: '',
                recipe_dyestuff: '',
                recipe_be: '',
                initial_alkali: '',
                initial_dyestuff: '',
                remaining_alkali: '',
                remaining_dyestuff: '',
                sisa_tanggal: '',
                sisa_saturator: '',
                posisi_hari_ini: '',
                next_routing: '',
                sample: '',
                last_update: periodDisplay,
                updated_by: safeText(currentUser) || '-',
                vlot: '',
                kategori: ''
            });
            insertedCount += 1;
        });

        if (isPaddryAutoScheduleEnabled()) {
            recalculatePaddryPlanSchedule();
        }
        renderMainTable(mainTableRows);
        $('#breakTimeModal').modal('hide');
        if (insertedCount > 0) {
            showAlert('success', insertedCount + ' data break time berhasil ditambahkan ke tabel.');
        }
    });

    function getCpSearchPayload() {
        return {
            searchField: safeText($('#cpSearchField').val()) || 'cp_no',
            keyword: safeText($('#cpSearchKeyword').val()),
            planType: safeText(selectedPlanType),
            machineId: safeText(selectedMachineId)
        };
    }

    function normalizeCpKeyText(value) {
        return safeText(value).toUpperCase();
    }

    function normalizeCpKeyQty(value) {
        var raw = safeText(value);
        if (raw === '') {
            return '';
        }
        var num = parseFlexibleNumber(raw);
        if (!Number.isFinite(num)) {
            return raw;
        }
        return num.toFixed(6);
    }

    function buildCpUniqueKeyFromSearchRow(rowData) {
        if (!Array.isArray(rowData)) {
            return '';
        }
        var noCp = normalizeCpKeyText(rowData[0]);
        if (noCp === '') {
            return '';
        }
        var kodeLab = normalizeCpKeyText(rowData[9]);
        var material = normalizeCpKeyText(rowData[21]);
        var qty = normalizeCpKeyQty(rowData[22]);
        return [noCp, kodeLab, material, qty].join('|');
    }

    function buildCpUniqueKeyFromMainRow(row) {
        if (!row || typeof row !== 'object') {
            return '';
        }
        var isBreakTimeRow = isBreakTimeMainRow(row);
        if (isBreakTimeRow) {
            return '';
        }
        var noCp = normalizeCpKeyText(row.no_cp);
        if (noCp === '') {
            return '';
        }
        var kodeLab = normalizeCpKeyText(row.kode_lab);
        var material = normalizeCpKeyText(row.material);
        var qty = normalizeCpKeyQty(row.qty);
        return [noCp, kodeLab, material, qty].join('|');
    }

    function rebuildCpExistingKeyMap() {
        cpSearchExistingKeyMap = {};
        mainTableRows.forEach(function (row) {
            var key = buildCpUniqueKeyFromMainRow(row);
            if (key !== '') {
                cpSearchExistingKeyMap[key] = true;
            }
        });
    }

    function ensureCpSearchDuplicateFilter() {
        if (cpSearchDuplicateFilterRegistered) {
            return;
        }
        $.fn.dataTable.ext.search.push(function (settings, searchData, dataIndex) {
            if (!settings || !settings.nTable || settings.nTable.id !== 'cpSearchTable') {
                return true;
            }
            var rowData = (settings.aoData && settings.aoData[dataIndex]) ? settings.aoData[dataIndex]._aData : null;
            var key = buildCpUniqueKeyFromSearchRow(rowData);
            if (key === '') {
                return true;
            }
            return !Object.prototype.hasOwnProperty.call(cpSearchExistingKeyMap, key);
        });
        cpSearchDuplicateFilterRegistered = true;
    }

    function buildCpSelectionKey(rowData) {
        if (!Array.isArray(rowData)) {
            return '';
        }
        return JSON.stringify(rowData);
    }

    function getSelectedCpRowsList() {
        return Object.keys(selectedCpRows).map(function (key) {
            return selectedCpRows[key];
        });
    }

    function updateCpSubmitButtonState() {
        var selectedCount = getSelectedCpRowsList().length;
        $('#btnSubmitCpSearch').prop('disabled', selectedCount === 0);
    }

    function applyCpSelectionToCurrentPage() {
        if (!cpSearchTable) {
            return;
        }

        $('#cpSearchTable tbody tr').removeClass('table-active');
        $('#cpSearchTable tbody tr').each(function () {
            var rowData = cpSearchTable.row(this).data();
            var rowKey = buildCpSelectionKey(rowData);
            var checked = rowKey && Object.prototype.hasOwnProperty.call(selectedCpRows, rowKey);
            $(this).find('input.cp-row-check').prop('checked', checked);
            if (checked) {
                $(this).addClass('table-active');
            }
        });
    }

    function clearCpSelection() {
        selectedCpRows = {};
        $('#cpSearchTable tbody tr').removeClass('table-active');
        $('#cpSearchTable tbody').find('input.cp-row-check').prop('checked', false);
        updateCpSubmitButtonState();
    }

    function reloadCpSearchTable(resetPaging) {
        if (!cpSearchTable) {
            return;
        }
        clearCpSelection();
        cpSearchTable.ajax.reload(null, resetPaging !== false);
    }

    function debounceCpSearchReload() {
        clearTimeout(cpSearchDebounceTimer);
        cpSearchDebounceTimer = setTimeout(function () {
            reloadCpSearchTable(true);
        }, 350);
    }

    function syncCpSearchTableLayout() {
        if (!cpSearchTable) {
            return;
        }
        cpSearchTable.columns.adjust();
    }

    function getCpSearchLocationMode() {
        var layoutKey = getMainTableLayoutKey();
        return {
            showCpb: layoutKey === 'cpb',
            showPreSett: layoutKey === 'presett',
            showPaddry: layoutKey === 'paddry',
            showBakarBulu: layoutKey === 'bakar_bulu',
            showScouring: layoutKey === 'scouring',
            showWashing: layoutKey === 'washing_padsteam',
            showJetDyeing: layoutKey === 'jet_dyeing'
        };
    }

    function renderCpSearchFieldOptions() {
        var current = safeText($('#cpSearchField').val()) || 'cp_no';
        var mode = getCpSearchLocationMode();
        var options = [
            { value: 'cp_no', label: 'CP No' },
            { value: 'product_code', label: 'Product Code' },
            { value: 'posisi_hari_ini', label: 'Posisi Hari Ini' }
        ];

        if (mode.showPaddry) {
            options.push({ value: 'lokasi_paddry', label: 'Lokasi Paddry' });
        }
        if (mode.showBakarBulu) {
            options.push({ value: 'lokasi_bakar_bulu', label: 'Lokasi Bakar Bulu' });
        }
        if (mode.showWashing) {
            options.push({ value: 'lokasi_washing', label: 'Lokasi Washing' });
        }
        if (mode.showJetDyeing) {
            options.push({ value: 'lokasi_jetdyeing', label: 'Lokasi Jet Dyeing' });
        }
        if (mode.showCpb) {
            options.push({ value: 'lokasi_cpb', label: 'Lokasi CPB' });
        }
        if (mode.showScouring) {
            options.push({ value: 'lokasi_scouring', label: 'Lokasi Scouring' });
        }
        if (mode.showPreSett) {
            options.push({ value: 'lokasi_presett', label: 'Lokasi PreSett' });
        }

        options.push({ value: 'color_name', label: 'Color Name' });

        var $field = $('#cpSearchField');
        $field.empty();

        var hasCurrent = false;
        options.forEach(function (opt) {
            if (opt.value === current) {
                hasCurrent = true;
            }
            $('<option></option>')
                .attr('value', opt.value)
                .text(opt.label)
                .appendTo($field);
        });

        $field.val(hasCurrent ? current : 'cp_no');
    }

    function applyCpSearchLocationColumnVisibility() {
        if (!cpSearchTable) {
            return;
        }

        var mode = getCpSearchLocationMode();
        var paddryCol = cpSearchTable.column('lokasi_paddry:name');
        if (paddryCol && typeof paddryCol.index === 'function' && paddryCol.index() !== undefined) {
            paddryCol.visible(mode.showPaddry, false);
        }

        var bakarBuluCol = cpSearchTable.column('lokasi_bakar_bulu:name');
        if (bakarBuluCol && typeof bakarBuluCol.index === 'function' && bakarBuluCol.index() !== undefined) {
            bakarBuluCol.visible(mode.showBakarBulu, false);
        }

        var washingCol = cpSearchTable.column('lokasi_washing:name');
        if (washingCol && typeof washingCol.index === 'function' && washingCol.index() !== undefined) {
            washingCol.visible(mode.showWashing, false);
        }

        var jetDyeingCol = cpSearchTable.column('lokasi_jetdyeing:name');
        if (jetDyeingCol && typeof jetDyeingCol.index === 'function' && jetDyeingCol.index() !== undefined) {
            jetDyeingCol.visible(mode.showJetDyeing, false);
        }

        var cpbCol = cpSearchTable.column('lokasi_cpb:name');
        if (cpbCol && typeof cpbCol.index === 'function' && cpbCol.index() !== undefined) {
            cpbCol.visible(mode.showCpb, false);
        }

        var scouringCol = cpSearchTable.column('lokasi_scouring:name');
        if (scouringCol && typeof scouringCol.index === 'function' && scouringCol.index() !== undefined) {
            scouringCol.visible(mode.showScouring, false);
        }

        var preSettCol = cpSearchTable.column('lokasi_presett:name');
        if (preSettCol && typeof preSettCol.index === 'function' && preSettCol.index() !== undefined) {
            preSettCol.visible(mode.showPreSett, false);
        }
        cpSearchTable.columns.adjust();
        cpSearchTable.draw(false);
        syncCpSearchTableLayout();
    }

    function syncCpSearchByPlanType() {
        var previousField = safeText($('#cpSearchField').val()) || 'cp_no';
        renderCpSearchFieldOptions();

        if (cpSearchTable) {
            applyCpSearchLocationColumnVisibility();
            var currentField = safeText($('#cpSearchField').val()) || 'cp_no';
            if (currentField !== previousField && safeText($('#cpSearchKeyword').val()) !== '') {
                applyCpClientSearch();
            }
        }
    }

    function openCpSearchModal() {
        syncCpSearchByPlanType();
        $('#cpSearchModal').modal('show');
        clearCpSelection();
        rebuildCpExistingKeyMap();
        ensureCpSearchDuplicateFilter();

        if (cpSearchTable) {
            syncCpSearchByPlanType();
            reloadCpSearchTable(false);
            return;
        }

        var loadingStartTimeSearch = 0;
        var minLoadingTimeSearch = 300;

        // Overlay HANYA saat AJAX request (bukan saat pagination/draw client-side)
        $('#cpSearchTable').on('preXhr.dt', function () {
            loadingStartTimeSearch = Date.now();
            $('#loadingOverlaySearch').addClass('active');
        });

        $('#cpSearchTable').on('xhr.dt', function () {
            var elapsed = Date.now() - loadingStartTimeSearch;
            var remaining = Math.max(0, minLoadingTimeSearch - elapsed);
            setTimeout(function () {
                $('#loadingOverlaySearch').removeClass('active');
            }, remaining);
        });

        cpSearchTable = $('#cpSearchTable').DataTable({
            processing: false,
            serverSide: false,
            responsive: false,
            autoWidth: false,
            scrollX: true,
            scrollY: '360px',
            scrollCollapse: true,
            lengthChange: false,
            dom: "rt<'row mt-2 align-items-center'<'col-sm-6'i><'col-sm-6'p>>",
            ajax: {
                url: '/gg_app/pages/planning/cp_planning/cp_planning.php?action=search_cp',
                type: 'POST',
                data: function (d) {
                    var filters = getCpSearchPayload();
                    d.filter_routing = '';
                    d.plan_type = filters.planType;
                    d.machine_id = filters.machineId;
                    d.force_refresh = cpSearchForceRefresh ? '1' : '0';
                },
                dataSrc: 'data'
            },
            columns: [
                {
                    data: null,
                    className: 'text-center cp-select-col',
                    width: '72px',
                    orderable: false,
                    searchable: false,
                    render: function (data, type, row, meta) {
                        var rowNumber = (meta && typeof meta.row === 'number') ? (meta.row + 1) : 1;
                        if (meta && meta.settings && typeof meta.settings._iDisplayStart === 'number') {
                            rowNumber += meta.settings._iDisplayStart;
                        }
                        return '<span class="cp-seq-wrap"><input type="checkbox" class="cp-row-check" tabindex="-1"><span class="cp-row-seq">' + rowNumber + '</span></span>';
                    }
                },
                { data: 0, name: 'cp_no', width: '160px' },
                { data: 1, width: '94px' },
                { data: 4, name: 'product_code', className: 'text-center', width: '110px' },
                { data: 5, width: '220px' },
                { data: 6, name: 'posisi_hari_ini', width: '210px' },
                { data: 36, name: 'lokasi_jetdyeing', width: '230px' },
                { data: 35, name: 'lokasi_washing', width: '220px' },
                { data: 34, name: 'lokasi_cpb', width: '220px' },
                { data: 33, name: 'lokasi_presett', width: '220px' },
                { data: 32, name: 'lokasi_scouring', width: '210px' },
                { data: 30, name: 'lokasi_paddry', width: '210px' },
                { data: 31, name: 'lokasi_bakar_bulu', width: '230px' },
                { data: 22, className: 'text-left', width: '100px' },
                { data: 7, className: 'text-center', width: '108px' },
                { data: 8, width: '140px' },
                { data: 9, className: 'text-center', width: '120px' },
                { data: 10, name: 'color_name', width: '130px' },
                { data: 11, width: '120px' },
                { data: 12, width: '120px' },
                { data: 13, width: '130px' },
                { data: 14, width: '130px' },
                { data: 15, width: '120px' },
                { data: 16, width: '160px' },
                { data: 17, className: 'text-center', width: '80px' },
                { data: 18, width: '130px' },
                { data: 19, width: '170px' },
                { data: 20, className: 'cp-col-psname', width: '360px' },
                { data: 21, width: '220px' },
                { data: 29, className: 'text-center', width: '150px' }
            ],
            order: [[1, 'asc']],
            pageLength: 25,
            language: {
                processing: 'Memuat data...',
                zeroRecords: 'Tidak ada data ditemukan',
                info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
                infoEmpty: 'Tidak ada data tersedia',
                infoFiltered: '(disaring dari _MAX_ total)',
                paginate: {
                    first: 'Awal',
                    last: 'Akhir',
                    next: '>',
                    previous: '<'
                }
            },
            drawCallback: function () {
                applyCpSelectionToCurrentPage();
                updateCpSubmitButtonState();
                this.api().columns.adjust();
                syncCpSearchTableLayout();
            }
        });

        syncCpSearchByPlanType();

        $('#cpSearchTable tbody').on('click', 'tr', function () {
            if (!cpSearchTable) return;
            if ($(this).hasClass('dataTables_empty')) return;

            var rowData = cpSearchTable.row(this).data();
            var rowKey = buildCpSelectionKey(rowData);
            if (!rowKey) {
                return;
            }

            if (Object.prototype.hasOwnProperty.call(selectedCpRows, rowKey)) {
                delete selectedCpRows[rowKey];
                $(this).removeClass('table-active');
                $(this).find('input.cp-row-check').prop('checked', false);
            } else {
                selectedCpRows[rowKey] = rowData;
                $(this).addClass('table-active');
                $(this).find('input.cp-row-check').prop('checked', true);
            }
            updateCpSubmitButtonState();
        });
    }

    function reloadCpSearchTable(resetPaging, force) {
        if (!cpSearchTable) {
            return;
        }
        cpSearchForceRefresh = (force === true);
        clearCpSelection();
        cpSearchTable.ajax.reload(function () {
            cpSearchForceRefresh = false;
            // Re-apply filter client-side yang aktif setelah data segar dimuat
            applyCpClientSearch();
        }, resetPaging !== false);
    }

    function debounceCpSearchReload() {
        clearTimeout(cpSearchDebounceTimer);
        cpSearchDebounceTimer = setTimeout(function () {
            applyCpClientSearch();
        }, 300);
    }

    // Mapping field dropdown ke name kolom DataTables
    var cpSearchColMap = {
        'cp_no': 'cp_no',
        'product_code': 'product_code',
        'posisi_hari_ini': 'posisi_hari_ini',
        'lokasi_jetdyeing': 'lokasi_jetdyeing',
        'lokasi_washing': 'lokasi_washing',
        'lokasi_cpb': 'lokasi_cpb',
        'lokasi_presett': 'lokasi_presett',
        'lokasi_scouring': 'lokasi_scouring',
        'lokasi_paddry': 'lokasi_paddry',
        'lokasi_bakar_bulu': 'lokasi_bakar_bulu',
        'color_name': 'color_name'
    };

    function applyCpClientSearch() {
        if (!cpSearchTable) { return; }
        var keyword  = safeText($('#cpSearchKeyword').val());
        var field    = safeText($('#cpSearchField').val()) || 'cp_no';
        rebuildCpExistingKeyMap();

        // Reset semua kolom search dulu
        cpSearchTable.columns().search('');
        cpSearchTable.search('');

        // Terapkan keyword per kolom yang dipilih
        if (keyword) {
            var colName = cpSearchColMap[field];
            if (colName) {
                var targetCol = cpSearchTable.column(colName + ':name');
                if (targetCol && typeof targetCol.index === 'function' && targetCol.index() !== undefined) {
                    targetCol.search(keyword, false, false);
                } else {
                    cpSearchTable.search(keyword, false, false);
                }
            } else {
                cpSearchTable.search(keyword, false, false);
            }
        }

        clearCpSelection();
        cpSearchTable.draw();
    }

    function syncCpSearchTableLayout() {
        if (!cpSearchTable) {
            return;
        }
        cpSearchTable.columns.adjust();
    }

    $('#btnCpSearchApply').on('click', function () {
        if (!cpSearchTable) { return; }
        reloadCpSearchTable(true, true);
    });

    $('#btnCpSearchReset').on('click', function () {
        $('#cpSearchField').val('cp_no');
        $('#cpSearchKeyword').val('');
        if (cpSearchTable) {
            cpSearchTable.columns().search('');
            cpSearchTable.search('');
            clearCpSelection();
            cpSearchTable.draw();
        }
    });

    $('#btnCpSearchSelectAll').on('click', function () {
        if (!cpSearchTable) {
            return;
        }

        clearCpSelection();
        var filteredRows = cpSearchTable.rows({ search: 'applied' }).data();
        for (var i = 0; i < filteredRows.length; i++) {
            var rowData = filteredRows[i];
            var rowKey = buildCpSelectionKey(rowData);
            if (!rowKey) {
                continue;
            }
            selectedCpRows[rowKey] = rowData;
        }

        applyCpSelectionToCurrentPage();
        updateCpSubmitButtonState();
    });

    $('#cpSearchField').on('change', function () {
        if (safeText($('#cpSearchKeyword').val())) {
            applyCpClientSearch();
        }
    });

    $('#cpSearchKeyword').on('input', function () {
        debounceCpSearchReload();
    });

    $('#cpSearchKeyword').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(cpSearchDebounceTimer);
            applyCpClientSearch();
        }
    });

    $('#cpSearchModal').on('shown.bs.modal', function () {
        if (cpSearchTable) {
            syncCpSearchByPlanType();
            cpSearchTable.columns.adjust();
            syncCpSearchTableLayout();
        }
    });

    $(window).on('resize', function () {
        if (!cpSearchTable || !$('#cpSearchModal').hasClass('show')) {
            queueMainTableStickyHeaderOffsetSync();
            queueMainTableHorizontalScrollbarSync();
            return;
        }
        cpSearchTable.columns.adjust();
        syncCpSearchTableLayout();
        queueMainTableStickyHeaderOffsetSync();
        queueMainTableHorizontalScrollbarSync();
    });

    $(window).on('scroll.cpMainSticky', function () {
        queueMainTableStickyHeaderOffsetSync();
    });

    $('#cpSearchModal').on('hidden.bs.modal', function () {
        clearTimeout(cpSearchDebounceTimer);
        clearCpSelection();
    });

    $('#btnNew').on('click', function () {
        openCpSearchModal();
    });

    function submitWipExportForm(payload, targetName) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '/gg_app/pages/planning/cp_planning/export_wip.php';
        form.target = safeText(targetName) || '_blank';
        form.style.display = 'none';

        Object.keys(payload).forEach(function (key) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = payload[key] == null ? '' : String(payload[key]);
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

    function submitPlanningExcelExportForm(payload, targetName) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '/gg_app/pages/planning/cp_planning/cp_planning_export_excel.php';
        form.target = safeText(targetName) || '_blank';
        form.style.display = 'none';

        Object.keys(payload).forEach(function (key) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = payload[key] == null ? '' : String(payload[key]);
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

    function ensureWipExportFrame() {
        var frameId = 'cpWipDownloadFrame';
        var frame = document.getElementById(frameId);
        if (!frame) {
            frame = document.createElement('iframe');
            frame.id = frameId;
            frame.name = frameId;
            frame.style.display = 'none';
            frame.setAttribute('aria-hidden', 'true');
            document.body.appendChild(frame);
        }
        return frame.name;
    }

    function ensurePlanningAllExportFrame() {
        var frameId = 'cpPlanningAllDownloadFrame';
        var frame = document.getElementById(frameId);
        if (!frame) {
            frame = document.createElement('iframe');
            frame.id = frameId;
            frame.name = frameId;
            frame.style.display = 'none';
            frame.setAttribute('aria-hidden', 'true');
            document.body.appendChild(frame);
        }
        return frame.name;
    }

    function ensurePlanningExcelExportFrame() {
        var frameId = 'cpPlanningExcelDownloadFrame';
        var frame = document.getElementById(frameId);
        if (!frame) {
            frame = document.createElement('iframe');
            frame.id = frameId;
            frame.name = frameId;
            frame.style.display = 'none';
            frame.setAttribute('aria-hidden', 'true');
            document.body.appendChild(frame);
        }
        return frame.name;
    }

    function setExportWipLoading(isLoading) {
        var loading = (isLoading === true);
        var $btn = $('#btnExportWip');
        if (!$btn.length) {
            return;
        }

        if (loading) {
            $btn.prop('disabled', true);
            $btn.data('original-html', $btn.html());
            $btn.html('<span class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span><span>Memproses...</span>');
            showPositionUpdateLoading('Menyiapkan export WIP...');
            return;
        }

        var originalHtml = $btn.data('original-html');
        if (originalHtml) {
            $btn.html(originalHtml);
        }
        $btn.prop('disabled', false);
        hidePositionUpdateLoading();
    }

    $('#btnExportWip').on('click', function () {
        if (!safeText(selectedPlanType)) {
            showAlert('warning', 'Pilih tipe planning terlebih dahulu.');
            return;
        }

        var payload = getCpSearchPayload();
        var layout = getMainTableLayoutKey();
        var searchField = safeText($('#cpSearchField').val()) || 'cp_no';
        var keyword = safeText($('#cpSearchKeyword').val());
        var targetName = ensureWipExportFrame();

        setExportWipLoading(true);

        $.ajax({
            url: '/gg_app/pages/planning/cp_planning/cp_planning.php?action=search_cp',
            type: 'POST',
            dataType: 'json',
            data: {
                filter_routing: '',
                plan_type: payload.planType,
                machine_id: payload.machineId,
                force_refresh: '1'
            }
        }).done(function (res) {
            var rows = (res && Array.isArray(res.data)) ? res.data : [];
            submitWipExportForm({
                plan_type: payload.planType,
                machine_id: payload.machineId,
                layout: layout,
                search_field: searchField,
                keyword: keyword,
                rows_json: JSON.stringify(rows)
            }, targetName);
        }).fail(function () {
            showAlert('danger', 'Gagal mengambil data WIP untuk export.');
        }).always(function () {
            setExportWipLoading(false);
        });
    });

    $('#btnExportPlanningAll').on('click', function () {
        if (!safeText(selectedPlanType)) {
            showAlert('warning', 'Pilih tipe planning terlebih dahulu.');
            return;
        }

        var layoutKey = getMainTableLayoutKey();
        if (layoutKey !== 'paddry' && layoutKey !== 'bakar_bulu' && layoutKey !== 'scouring' && layoutKey !== 'presett') {
            showAlert('warning', 'Export all saat ini hanya tersedia untuk Paddry, Bakar Bulu, Scouring, dan PreSett.');
            return;
        }

        var periodIso = safeText($('#periodDate').val());
        if (!periodIso) {
            showAlert('warning', 'Periode wajib diisi.');
            return;
        }

        var targetName = ensurePlanningAllExportFrame();
        showPositionUpdateLoading('Menyiapkan export planning semua machine...');

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '/gg_app/pages/planning/cp_planning/planning_export_all.php';
        form.target = targetName;
        form.style.display = 'none';

        var payload = {
            layout: layoutKey,
            plan_type: safeText(selectedPlanType),
            period_date: periodIso
        };
        Object.keys(payload).forEach(function (key) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = payload[key] == null ? '' : String(payload[key]);
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);

        setTimeout(function () {
            hidePositionUpdateLoading();
        }, 900);
    });

    $('#btnExportExcel').on('click', function () {
        updateMainRowsFromInputs();

        if (!safeText(selectedPlanType)) {
            showAlert('warning', 'Pilih tipe planning terlebih dahulu.');
            return;
        }
        if (!safeText(selectedMachineId)) {
            showAlert('warning', 'Pilih machine terlebih dahulu.');
            return;
        }

        var layoutKey = getMainTableLayoutKey();
        if (layoutKey !== 'paddry' && layoutKey !== 'bakar_bulu' && layoutKey !== 'scouring' && layoutKey !== 'presett') {
            showAlert('warning', 'Export Excel hanya tersedia untuk Paddry, Bakar Bulu, Scouring, dan PreSett.');
            return;
        }

        var periodIso = safeText($('#periodDate').val());
        if (!periodIso) {
            showAlert('warning', 'Periode wajib diisi.');
            return;
        }

        var selectedMachine = getSelectedMachineRow();
        var machineName = '';
        if (selectedMachine) {
            machineName = safeText(selectedMachine.faname) || safeText(selectedMachine.facode);
        }
        var targetName = ensurePlanningExcelExportFrame();
        showPositionUpdateLoading('Menyiapkan export excel...');

        submitPlanningExcelExportForm({
            layout: layoutKey,
            machine_id: safeText(selectedMachineId),
            machine_name: machineName,
            period_date: periodIso,
            rows_json: JSON.stringify(Array.isArray(mainTableRows) ? mainTableRows : [])
        }, targetName);

        setTimeout(function () {
            hidePositionUpdateLoading();
        }, 900);
    });

    $('#btnGenerateFailReport').on('click', function () {
        updateMainRowsFromInputs();

        if (!safeText(selectedPlanType)) {
            showAlert('warning', 'Pilih tipe planning terlebih dahulu.');
            return;
        }
        if (!safeText(selectedMachineId)) {
            showAlert('warning', 'Pilih machine terlebih dahulu.');
            return;
        }
        if (getMainTableLayoutKey() !== 'paddry') {
            showAlert('warning', 'Generate Report Fail CP saat ini hanya tersedia untuk layout Paddry.');
            return;
        }
        if (hasPendingRows()) {
            showAlert('warning', 'Masih ada data sementara. Klik checklist untuk simpan permanen sebelum generate report.');
            return;
        }

        var periodIso = safeText($('#periodDate').val());
        if (!periodIso) {
            showAlert('warning', 'Periode wajib diisi.');
            return;
        }

        var selectedMachine = getSelectedMachineRow();
        var machineName = '';
        if (selectedMachine) {
            machineName = safeText(selectedMachine.faname) || safeText(selectedMachine.facode);
        }

        var url = '/gg_app/pages/planning/cp_planning/cp_planning_report_fail.php'
            + '?period_date=' + encodeURIComponent(periodIso)
            + '&plan_type=' + encodeURIComponent(safeText(selectedPlanType))
            + '&layout=' + encodeURIComponent(getMainTableLayoutKey())
            + '&machine_id=' + encodeURIComponent(safeText(selectedMachineId))
            + '&machine_name=' + encodeURIComponent(machineName);

        window.location.href = url;
    });

    function getPersistedLoadActionByLayout(layoutKey) {
        if (layoutKey === 'bakar_bulu') {
            return 'load_bakar_bulu';
        }
        if (layoutKey === 'paddry') {
            return 'load_paddry';
        }
        if (layoutKey === 'scouring') {
            return 'load_scouring';
        }
        if (layoutKey === 'presett') {
            return 'load_presett';
        }
        return '';
    }

    function loadPersistedRowsByMachine(layoutKey, machineId, periodIso) {
        var actionName = getPersistedLoadActionByLayout(layoutKey);
        if (!actionName) {
            return $.Deferred().reject().promise();
        }
        return $.ajax({
            url: '/gg_app/pages/planning/cp_planning/cp_planning.php?action=' + encodeURIComponent(actionName),
            type: 'POST',
            dataType: 'json',
            data: {
                machine_id: safeText(machineId),
                period_date: safeText(periodIso)
            }
        });
    }

    function reloadCurrentMachineByLayout(layoutKey) {
        if (layoutKey === 'bakar_bulu') {
            return loadBakarBuluPersistedRows({ silent: true });
        }
        if (layoutKey === 'paddry') {
            return loadPaddryPersistedRows({ silent: true });
        }
        if (layoutKey === 'scouring') {
            return loadScouringPersistedRows({ silent: true });
        }
        return loadPreSettPersistedRows({ silent: true });
    }

    $('#btnUpdateAllCurrentPosition').on('click', function () {
        updateMainRowsFromInputs();

        if (!safeText(selectedPlanType)) {
            showAlert('warning', 'Pilih tipe planning terlebih dahulu.');
            return;
        }

        var layoutKey = getMainTableLayoutKey();
        var isPersistentLayout = (layoutKey === 'bakar_bulu' || layoutKey === 'paddry' || layoutKey === 'scouring' || layoutKey === 'presett');
        if (!isPersistentLayout) {
            showAlert('warning', 'Update permanen hanya tersedia untuk Bakar Bulu, Paddry, Scouring, dan PreSett.');
            return;
        }

        if (hasPendingRows()) {
            showAlert('warning', 'Masih ada data sementara. Simpan permanen atau batalkan dulu sebelum update all.');
            return;
        }

        var periodIso = safeText($('#periodDate').val());
        if (!periodIso) {
            showAlert('warning', 'Periode wajib diisi.');
            return;
        }

        var machineIds = [];
        if (Array.isArray(machineRows)) {
            machineRows.forEach(function (row) {
                var id = safeText(row && row.id);
                if (id !== '') {
                    machineIds.push(id);
                }
            });
        }
        if (!machineIds.length) {
            showAlert('warning', 'Data machine tidak ditemukan untuk tipe planning ini.');
            return;
        }
        if (!window.confirm('Update posisi hari ini untuk semua machine pada tanggal ' + periodIso + '?')) {
            return;
        }

        var $btnUpdate = $('#btnUpdateCurrentPosition');
        var $btnUpdateAll = $('#btnUpdateAllCurrentPosition');
        $btnUpdate.prop('disabled', true);
        $btnUpdateAll.prop('disabled', true);
        var finishUpdateAllFlow = function () {
            hidePositionUpdateLoading();
            $btnUpdate.prop('disabled', false);
            $btnUpdateAll.prop('disabled', false);
        };

        var allTargets = [];
        var cpNosPayload = [];
        var cpNosSeen = {};
        var loadErrorMachines = [];

        var collectRowsFromMachineIndex = function (idx) {
            if (idx >= machineIds.length) {
                if (!allTargets.length) {
                    var msgNoData = loadErrorMachines.length
                        ? 'Tidak ada data permanen yang bisa di-update. ' + loadErrorMachines.length + ' machine gagal dimuat.'
                        : 'Tidak ada data permanen yang bisa di-update.';
                    showAlert('warning', msgNoData);
                    finishUpdateAllFlow();
                    return;
                }
                if (!cpNosPayload.length) {
                    showAlert('warning', 'CP No tidak valid. Data terbaru tidak bisa diambil.');
                    finishUpdateAllFlow();
                    return;
                }

                showPositionUpdateLoading('Mengambil data terbaru dari source Search CP (semua machine)...');
                $.ajax({
                    url: '/gg_app/pages/planning/cp_planning/cp_planning.php?action=lookup_current_position',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        cp_nos: JSON.stringify(cpNosPayload),
                        layout: layoutKey,
                        plan_type: safeText(selectedPlanType)
                    }
                }).done(function (lookupRes) {
                    if (!lookupRes || lookupRes.success !== true) {
                        showAlert('danger', (lookupRes && lookupRes.message) ? lookupRes.message : 'Gagal mengambil data terbaru.');
                        finishUpdateAllFlow();
                        return;
                    }

                    var lookupMap = {};
                    if (lookupRes.data && typeof lookupRes.data === 'object' && !Array.isArray(lookupRes.data)) {
                        lookupMap = lookupRes.data;
                    }

                    var refreshedCount = 0;
                    var missingCount = 0;
                    var qtyRedirectCount = 0;
                    var rowsPayloadByMachine = {};

                    allTargets.forEach(function (target) {
                        var cpKey = safeText(target.cp_key);
                        if (cpKey === '' || !Object.prototype.hasOwnProperty.call(lookupMap, cpKey)) {
                            missingCount += 1;
                            return;
                        }

                        var latest = lookupMap[cpKey] || {};
                        var rowRef = target.row_ref || {};
                        var rowChanged = false;
                        var applyIfDifferent = function (field, value, fallbackDash, allowEmpty) {
                            var nextVal = safeText(value);
                            if (nextVal === '' && fallbackDash === true) {
                                nextVal = '-';
                            }
                            if (nextVal === '' && allowEmpty !== true) {
                                return;
                            }
                            if (safeText(rowRef[field]) !== nextVal) {
                                rowRef[field] = nextVal;
                                rowChanged = true;
                            }
                        };

                        applyIfDifferent('posisi_hari_ini', latest.posisi_hari_ini, true);
                        applyIfDifferent('next_routing', latest.next_routing, true);
                        applyIfDifferent('kode_lab', latest.kode_lab, false);
                        applyIfDifferent('label', latest.label, false);
                        applyIfDifferent('cust_color', latest.cust_color, false);
                        applyIfDifferent('material', latest.material, false);
                        applyIfDifferent('vlot_resp', latest.vlot, false);
                        applyIfDifferent('plan_description', latest.kategori_penimbangan, false, true);

                        var latestQty = safeText(latest.qty);
                        target.qty_latest = latestQty;
                        if (latestQty !== '') {
                            if (layoutKey === 'paddry') {
                                if (safeText(rowRef.actual_realisasi) !== latestQty) {
                                    rowRef.actual_realisasi = latestQty;
                                    rowChanged = true;
                                    qtyRedirectCount += 1;
                                }
                            } else {
                                if (safeText(rowRef.qty) !== latestQty) {
                                    rowRef.qty = latestQty;
                                    rowChanged = true;
                                }
                            }
                        }

                        if (rowChanged) {
                            refreshedCount += 1;
                        }

                        var machineId = safeText(target.machine_id);
                        if (machineId === '') {
                            return;
                        }
                        if (!Object.prototype.hasOwnProperty.call(rowsPayloadByMachine, machineId)) {
                            rowsPayloadByMachine[machineId] = [];
                        }
                        rowsPayloadByMachine[machineId].push({
                            row_id: target.row_id,
                            posisi_hari_ini: safeText(rowRef.posisi_hari_ini),
                            next_routing: safeText(rowRef.next_routing),
                            kode_lab: safeText(rowRef.kode_lab),
                            label: safeText(rowRef.label),
                            cust_color: safeText(rowRef.cust_color),
                            material: safeText(rowRef.material),
                            vlot_resp: safeText(rowRef.vlot_resp),
                            plan_description: safeText(rowRef.plan_description),
                            actual_realisasi: safeText(rowRef.actual_realisasi),
                            qty_latest: safeText(target.qty_latest)
                        });
                    });

                    var machinePayloadIds = Object.keys(rowsPayloadByMachine);
                    if (!machinePayloadIds.length) {
                        showAlert('warning', 'Data terbaru tidak ditemukan dari source untuk semua CP di semua machine.');
                        finishUpdateAllFlow();
                        return;
                    }

                    var updatedCountTotal = 0;
                    var persistErrorMachines = [];
                    var persistMessages = [];

                    var persistMachineAt = function (persistIdx) {
                        if (persistIdx >= machinePayloadIds.length) {
                            reloadCurrentMachineByLayout(layoutKey).always(function () {
                                var extraInfo = [];
                                if (refreshedCount > 0) {
                                    extraInfo.push(refreshedCount + ' data disegarkan dari source');
                                }
                                if (qtyRedirectCount > 0 && layoutKey === 'paddry') {
                                    extraInfo.push(qtyRedirectCount + ' qty dialihkan ke Aktual Qty');
                                }
                                if (missingCount > 0) {
                                    extraInfo.push(missingCount + ' data tidak ditemukan di source');
                                }
                                if (loadErrorMachines.length > 0) {
                                    extraInfo.push(loadErrorMachines.length + ' machine gagal dimuat');
                                }
                                if (persistErrorMachines.length > 0) {
                                    extraInfo.push(persistErrorMachines.length + ' machine gagal disimpan');
                                }

                                var msg = updatedCountTotal + ' data berhasil diupdate permanen (semua machine).';
                                if (persistMessages.length > 0) {
                                    msg = persistMessages.join(' ');
                                }
                                if (extraInfo.length > 0) {
                                    msg += ' (' + extraInfo.join(', ') + ').';
                                }

                                if (persistErrorMachines.length > 0) {
                                    showAlert('warning', msg);
                                } else {
                                    showAlert('success', msg);
                                    showPositionUpdateSuccess(msg);
                                }
                                finishUpdateAllFlow();
                            });
                            return;
                        }

                        var machineId = machinePayloadIds[persistIdx];
                        var payloadRows = rowsPayloadByMachine[machineId] || [];
                        if (!payloadRows.length) {
                            persistMachineAt(persistIdx + 1);
                            return;
                        }

                        showPositionUpdateLoading('Menyimpan update permanen semua machine... (' + (persistIdx + 1) + '/' + machinePayloadIds.length + ')');
                        $.ajax({
                            url: '/gg_app/pages/planning/cp_planning/cp_planning_persist_api.php?action=update_posisi_hari_ini',
                            type: 'POST',
                            dataType: 'json',
                            data: {
                                machine_id: machineId,
                                period_date: periodIso,
                                layout: layoutKey,
                                rows: JSON.stringify(payloadRows)
                            }
                        }).done(function (res) {
                            if (!res || res.success !== true) {
                                persistErrorMachines.push(machineId);
                                return;
                            }
                            var updatedCount = parseInt(res.updated_count, 10);
                            if (Number.isFinite(updatedCount)) {
                                updatedCountTotal += updatedCount;
                            }
                            if (safeText(res.message) !== '') {
                                persistMessages.push(res.message);
                            }
                        }).fail(function () {
                            persistErrorMachines.push(machineId);
                        }).always(function () {
                            persistMachineAt(persistIdx + 1);
                        });
                    };

                    persistMachineAt(0);
                }).fail(function () {
                    showAlert('danger', 'Gagal mengambil data terbaru.');
                    finishUpdateAllFlow();
                });
                return;
            }

            var machineId = machineIds[idx];
            showPositionUpdateLoading('Memuat data permanen machine... (' + (idx + 1) + '/' + machineIds.length + ')');
            loadPersistedRowsByMachine(layoutKey, machineId, periodIso)
                .done(function (res) {
                    if (!res || res.success !== true) {
                        loadErrorMachines.push(machineId);
                        return;
                    }

                    var rows = Array.isArray(res.data) ? res.data : [];
                    rows.forEach(function (row) {
                        var dbId = getPersistedDbIdFromRowId(row && row._row_id);
                        if (dbId <= 0) {
                            return;
                        }
                        if (isBreakTimeMainRow(row)) {
                            return;
                        }
                        var cpKey = normalizeCpLookupKey(row && row.no_cp);
                        if (cpKey !== '' && !Object.prototype.hasOwnProperty.call(cpNosSeen, cpKey)) {
                            cpNosSeen[cpKey] = true;
                            cpNosPayload.push(cpKey);
                        }
                        allTargets.push({
                            machine_id: machineId,
                            row_ref: row,
                            row_id: dbId,
                            cp_key: cpKey
                        });
                    });
                })
                .fail(function () {
                    loadErrorMachines.push(machineId);
                })
                .always(function () {
                    collectRowsFromMachineIndex(idx + 1);
                });
        };

        collectRowsFromMachineIndex(0);
    });

    $('#btnUpdateCurrentPosition').on('click', function () {
        updateMainRowsFromInputs();

        if (!safeText(selectedPlanType)) {
            showAlert('warning', 'Pilih tipe planning terlebih dahulu.');
            return;
        }
        if (!safeText(selectedMachineId)) {
            showAlert('warning', 'Pilih machine terlebih dahulu.');
            return;
        }

        var layoutKey = getMainTableLayoutKey();
        var isPersistentLayout = (layoutKey === 'bakar_bulu' || layoutKey === 'paddry' || layoutKey === 'scouring' || layoutKey === 'presett');
        if (!isPersistentLayout) {
            showAlert('warning', 'Update permanen hanya tersedia untuk Bakar Bulu, Paddry, Scouring, dan PreSett.');
            return;
        }

        if (hasPendingRows()) {
            showAlert('warning', 'Masih ada data sementara. Simpan permanen atau batalkan dulu sebelum update posisi.');
            return;
        }

        var periodIso = safeText($('#periodDate').val());
        if (!periodIso) {
            showAlert('warning', 'Periode wajib diisi.');
            return;
        }

        var persistedTargets = [];
        var cpNosPayload = [];
        var cpNosSeen = {};
        mainTableRows.forEach(function (row) {
            var dbId = getPersistedDbIdFromRowId(row && row._row_id);
            if (dbId <= 0) {
                return;
            }
            if (isBreakTimeMainRow(row)) {
                return;
            }

            var cpKey = normalizeCpLookupKey(row && row.no_cp);
            if (cpKey !== '' && !Object.prototype.hasOwnProperty.call(cpNosSeen, cpKey)) {
                cpNosSeen[cpKey] = true;
                cpNosPayload.push(cpKey);
            }

            persistedTargets.push({
                row_ref: row,
                row_id: dbId,
                cp_key: cpKey
            });
        });

        if (!persistedTargets.length) {
            showAlert('warning', 'Tidak ada data permanen yang bisa di-update.');
            return;
        }
        if (!cpNosPayload.length) {
            showAlert('warning', 'CP No tidak valid. Data terbaru tidak bisa diambil.');
            return;
        }

        var $btnUpdate = $('#btnUpdateCurrentPosition');
        $btnUpdate.prop('disabled', true);
        showPositionUpdateLoading('Mengambil data terbaru dari source Search CP...');
        var finishUpdateFlow = function () {
            hidePositionUpdateLoading();
            $btnUpdate.prop('disabled', false);
        };

        $.ajax({
            url: '/gg_app/pages/planning/cp_planning/cp_planning.php?action=lookup_current_position',
            type: 'POST',
            dataType: 'json',
            data: {
                cp_nos: JSON.stringify(cpNosPayload),
                layout: layoutKey,
                plan_type: safeText(selectedPlanType)
            }
        }).done(function (lookupRes) {
            if (!lookupRes || lookupRes.success !== true) {
                showAlert('danger', (lookupRes && lookupRes.message) ? lookupRes.message : 'Gagal mengambil data terbaru.');
                finishUpdateFlow();
                return;
            }

            var lookupMap = {};
            if (lookupRes.data && typeof lookupRes.data === 'object' && !Array.isArray(lookupRes.data)) {
                lookupMap = lookupRes.data;
            }

            var refreshedCount = 0;
            var missingCount = 0;
            var qtyRedirectCount = 0;
            var updatableTargets = [];
            persistedTargets.forEach(function (target) {
                var cpKey = safeText(target.cp_key);
                if (cpKey === '' || !Object.prototype.hasOwnProperty.call(lookupMap, cpKey)) {
                    missingCount += 1;
                    return;
                }
                updatableTargets.push(target);

                var latest = lookupMap[cpKey] || {};
                var rowRef = target.row_ref || {};
                var rowChanged = false;
                var applyIfDifferent = function (field, value, fallbackDash, allowEmpty) {
                    var nextVal = safeText(value);
                    if (nextVal === '' && fallbackDash === true) {
                        nextVal = '-';
                    }
                    if (nextVal === '' && allowEmpty !== true) {
                        return;
                    }
                    if (safeText(rowRef[field]) !== nextVal) {
                        rowRef[field] = nextVal;
                        rowChanged = true;
                    }
                };

                applyIfDifferent('posisi_hari_ini', latest.posisi_hari_ini, true);
                applyIfDifferent('next_routing', latest.next_routing, true);
                applyIfDifferent('kode_lab', latest.kode_lab, false);
                applyIfDifferent('label', latest.label, false);
                applyIfDifferent('cust_color', latest.cust_color, false);
                applyIfDifferent('material', latest.material, false);
                applyIfDifferent('vlot_resp', latest.vlot, false);
                applyIfDifferent('plan_description', latest.kategori_penimbangan, false, true);

                var latestQty = safeText(latest.qty);
                target.qty_latest = latestQty;
                if (latestQty !== '') {
                    if (layoutKey === 'paddry') {
                        if (safeText(rowRef.actual_realisasi) !== latestQty) {
                            rowRef.actual_realisasi = latestQty;
                            rowChanged = true;
                            qtyRedirectCount += 1;
                        }
                    } else {
                        if (safeText(rowRef.qty) !== latestQty) {
                            rowRef.qty = latestQty;
                            rowChanged = true;
                        }
                    }
                }

                if (rowChanged) {
                    refreshedCount += 1;
                }
            });

            if (!updatableTargets.length) {
                showAlert('warning', 'Data terbaru tidak ditemukan dari source untuk semua CP terpilih.');
                finishUpdateFlow();
                return;
            }

            renderMainTable(mainTableRows);

            var rowsPayload = updatableTargets.map(function (target) {
                return {
                    row_id: target.row_id,
                    posisi_hari_ini: safeText(target.row_ref && target.row_ref.posisi_hari_ini),
                    next_routing: safeText(target.row_ref && target.row_ref.next_routing),
                    kode_lab: safeText(target.row_ref && target.row_ref.kode_lab),
                    label: safeText(target.row_ref && target.row_ref.label),
                    cust_color: safeText(target.row_ref && target.row_ref.cust_color),
                    material: safeText(target.row_ref && target.row_ref.material),
                    vlot_resp: safeText(target.row_ref && target.row_ref.vlot_resp),
                    plan_description: safeText(target.row_ref && target.row_ref.plan_description),
                    actual_realisasi: safeText(target.row_ref && target.row_ref.actual_realisasi),
                    qty_latest: safeText(target.qty_latest)
                };
            });

            showPositionUpdateLoading('Menyimpan update data ke permanen...');
            $.ajax({
                url: '/gg_app/pages/planning/cp_planning/cp_planning_persist_api.php?action=update_posisi_hari_ini',
                type: 'POST',
                dataType: 'json',
                data: {
                    machine_id: safeText(selectedMachineId),
                    period_date: periodIso,
                    layout: layoutKey,
                    rows: JSON.stringify(rowsPayload)
                }
            }).done(function (res) {
                if (!res || res.success !== true) {
                    showAlert('danger', (res && res.message) ? res.message : 'Gagal update data permanen.');
                    finishUpdateFlow();
                    return;
                }

                var reloadPromise;
                if (layoutKey === 'bakar_bulu') {
                    reloadPromise = loadBakarBuluPersistedRows({ silent: true });
                } else if (layoutKey === 'paddry') {
                    reloadPromise = loadPaddryPersistedRows({ silent: true });
                } else if (layoutKey === 'scouring') {
                    reloadPromise = loadScouringPersistedRows({ silent: true });
                } else {
                    reloadPromise = loadPreSettPersistedRows({ silent: true });
                }

                reloadPromise.always(function () {
                    var extraInfo = [];
                    if (refreshedCount > 0) {
                        extraInfo.push(refreshedCount + ' data disegarkan dari source');
                    }
                    if (qtyRedirectCount > 0 && layoutKey === 'paddry') {
                        extraInfo.push(qtyRedirectCount + ' qty dialihkan ke Aktual Qty');
                    }
                    if (missingCount > 0) {
                        extraInfo.push(missingCount + ' data tidak ditemukan di source');
                    }
                    var msg = res.message || 'Data berhasil diupdate permanen.';
                    if (extraInfo.length > 0) {
                        msg += ' (' + extraInfo.join(', ') + ').';
                    }
                    showAlert('success', msg);
                    showPositionUpdateSuccess(msg);
                    finishUpdateFlow();
                });
            }).fail(function () {
                showAlert('danger', 'Gagal update data permanen.');
                finishUpdateFlow();
            });
        }).fail(function () {
            showAlert('danger', 'Gagal mengambil data terbaru.');
            finishUpdateFlow();
        });
    });

    $('#btnEdit').on('click', function () {
        updateMainRowsFromInputs();

        if (!mainTableRows.length) {
            showAlert('warning', 'Tidak ada data CP yang bisa diedit.');
            return;
        }

        var changedCount = 0;
        var alreadyPendingCount = 0;
        for (var i = 0; i < mainTableRows.length; i++) {
            var row = mainTableRows[i];
            if (safeText(row._save_state) === 'saved') {
                row._original_data = $.extend(true, {}, row);
                row._edited_from_saved = true;
                row._save_state = 'pending';
                changedCount += 1;
            } else if (safeText(row._save_state) === 'pending') {
                alreadyPendingCount += 1;
            }
        }

        if (changedCount > 0) {
            if (getMainTableLayoutKey() === 'paddry') {
                ensureBreakTimeRowsReady().always(function () {
                    // Samakan perilaku dengan mode pending sebelum simpan permanen:
                    // urutan (termasuk break time) langsung memicu kalkulasi jam berantai.
                    if (isPaddryAutoScheduleEnabled()) {
                        recalculatePaddryPlanSchedule(true);
                    }
                    renderMainTable(mainTableRows);
                    showAlert('info', 'Mode edit aktif untuk semua data. Silakan ubah data lalu klik checklist untuk simpan.');
                });
                return;
            }
            renderMainTable(mainTableRows);
            showAlert('info', 'Mode edit aktif untuk semua data. Silakan ubah data lalu klik checklist untuk simpan.');
            return;
        }

        if (alreadyPendingCount > 0) {
            if (getMainTableLayoutKey() === 'paddry') {
                ensureBreakTimeRowsReady().always(function () {
                    if (isPaddryAutoScheduleEnabled()) {
                        recalculatePaddryPlanSchedule(true);
                    }
                    renderMainTable(mainTableRows);
                    showAlert('info', 'Semua data sudah dalam mode edit.');
                });
                return;
            }
            showAlert('info', 'Semua data sudah dalam mode edit.');
            return;
        }

        showAlert('warning', 'Tidak ada data yang dapat diedit.');
    });

    $('#cpTableBody').on('click', 'tr[data-row-id]', function () {
        var rowId = safeText($(this).attr('data-row-id'));
        if (!rowId) {
            return;
        }
        setSelectedMainRow(rowId);
    });

    $('#cpTableBody').on('input change', 'input[data-field="plan_start"], input[data-field="speed"], input[data-field="plan_end"]', function () {
        if (getMainTableLayoutKey() !== 'paddry') {
            return;
        }
        updateMainRowsFromInputs();
    });

    $('#cpTableBody').on('input change', 'input[data-field="speed"]', function () {
        var copiedCount = applyCheckedRowsSameFieldValue(this);
        if (copiedCount > 0) {
            updateMainRowsFromInputs();
        }
        updateFillSameSpeedButtonState();
    });

    $('#btnFillSameSpeed').on('click', function () {
        var firstSpeed = getFirstRowSpeedValue();
        if (!firstSpeed) {
            showAlert('warning', 'Isi Speed pada baris pertama terlebih dahulu.');
            updateFillSameSpeedButtonState();
            return;
        }

        var appliedCount = 0;
        $('#cpTableBody tr[data-row-id]').each(function (idx) {
            if (idx === 0) {
                return;
            }
            var $speedInput = $(this).find('input[data-field="speed"]');
            if (!$speedInput.length || $speedInput.prop('readonly')) {
                return;
            }
            $speedInput.val(firstSpeed);
            appliedCount += 1;
        });

        if (appliedCount === 0) {
            showAlert('info', 'Tidak ada baris bawah yang bisa diisi otomatis.');
            updateFillSameSpeedButtonState();
            return;
        }

        updateMainRowsFromInputs();
        updateFillSameSpeedButtonState();
        showAlert('success', 'Speed berhasil disamakan ke ' + appliedCount + ' baris.');
    });

    $('#cpTableBody').on('click', '.main-row-checkbox', function (e) {
        e.stopPropagation();
    });

    $('#cpMainTableHead').on('click', '.main-row-check-all', function (e) {
        e.stopPropagation();
    });

    $('#cpMainTableHead').on('change', '.main-row-check-all', function (e) {
        e.stopPropagation();
        var shouldCheck = $(this).is(':checked');
        pruneMainRowCheckedState();

        if (!shouldCheck) {
            clearAllMainRowChecked();
            selectedMainRowId = '';
            applyMainRowSelection();
            return;
        }

        mainTableRows.forEach(function (row) {
            var rowId = safeText(row && row._row_id);
            if (rowId !== '') {
                setMainRowChecked(rowId, true);
            }
        });
        selectedMainRowId = getFirstCheckedMainRowId();
        applyMainRowSelection();
    });

    $('#cpTableBody').on('change', '.main-row-checkbox', function (e) {
        e.stopPropagation();
        var rowId = safeText($(this).attr('data-row-id'));
        if (!rowId) {
            return;
        }
        if ($(this).is(':checked')) {
            setMainRowChecked(rowId, true);
            selectedMainRowId = rowId;
        } else {
            setMainRowChecked(rowId, false);
            if (safeText(selectedMainRowId) === rowId) {
                selectedMainRowId = getFirstCheckedMainRowId();
            }
        }
        applyMainRowSelection();
    });

    function moveSelectedRow(step) {
        updateMainRowsFromInputs();

        var targetRowId = safeText(selectedMainRowId);
        if (!targetRowId) {
            showAlert('warning', 'Pilih data CP yang ingin dipindahkan terlebih dahulu.');
            return;
        }

        var fromIdx = -1;
        for (var i = 0; i < mainTableRows.length; i++) {
            if (safeText(mainTableRows[i]._row_id) === targetRowId) {
                fromIdx = i;
                break;
            }
        }

        if (fromIdx < 0) {
            showAlert('warning', 'Data terpilih tidak ditemukan.');
            return;
        }

        var toIdx = fromIdx + step;
        if (toIdx < 0 || toIdx >= mainTableRows.length) {
            return;
        }

        if (!orderDirty) {
            orderSnapshotRowIds = captureCurrentRowOrder();
        }

        var tmp = mainTableRows[fromIdx];
        mainTableRows[fromIdx] = mainTableRows[toIdx];
        mainTableRows[toIdx] = tmp;
        orderDirty = true;

        if (getMainTableLayoutKey() === 'paddry') {
            ensureBreakTimeRowsReady().always(function () {
                if (isPaddryAutoScheduleEnabled()) {
                    recalculatePaddryPlanSchedule(true);
                }
                renderMainTable(mainTableRows);
                setSelectedMainRow(targetRowId);
            });
            return;
        }
        renderMainTable(mainTableRows);
        setSelectedMainRow(targetRowId);
    }

    $('#btnMoveUp').on('click', function () {
        moveSelectedRow(-1);
    });

    $('#btnMoveDown').on('click', function () {
        moveSelectedRow(1);
    });

    $('#btnSubmitMoveDate').on('click', function () {
        var layoutKey = getMainTableLayoutKey();
        if (!isPersistentLayoutKey(layoutKey)) {
            showAlert('warning', 'Layout saat ini tidak mendukung pindah tanggal.');
            return;
        }

        var sourcePeriodDate = safeText($('#periodDate').val());
        var sourceMachineId = safeText(selectedMachineId);
        var targetPeriodDate = safeText($('#moveDateTargetDate').val());
        var targetMachineId = safeText($('#moveDateTargetMachine').val());

        if (!sourceMachineId || !sourcePeriodDate) {
            showAlert('warning', 'Machine atau periode asal tidak valid.');
            return;
        }
        if (!targetPeriodDate) {
            showAlert('warning', 'Pilih tanggal tujuan terlebih dahulu.');
            return;
        }
        if (!targetMachineId) {
            showAlert('warning', 'Pilih machine tujuan terlebih dahulu.');
            return;
        }
        if (sourceMachineId === targetMachineId && sourcePeriodDate === targetPeriodDate) {
            showAlert('warning', 'Tujuan pindah harus berbeda dari machine dan tanggal asal.');
            return;
        }
        if (!moveDateRowIds.length) {
            showAlert('warning', 'Tidak ada data yang dipilih untuk dipindahkan.');
            return;
        }

        var idSet = {};
        var dbIds = [];
        moveDateRowIds.forEach(function (rowId) {
            var dbId = getPersistedDbIdFromRowId(rowId);
            if (dbId <= 0 || Object.prototype.hasOwnProperty.call(idSet, dbId)) {
                return;
            }
            idSet[dbId] = true;
            dbIds.push(dbId);
        });
        if (!dbIds.length) {
            showAlert('warning', 'Data terpilih belum tersimpan permanen.');
            return;
        }

        var $btnMoveSubmit = $('#btnSubmitMoveDate');
        $btnMoveSubmit.prop('disabled', true);

        $.ajax({
            url: '/gg_app/pages/planning/cp_planning/cp_planning_persist_api.php?action=move_planning_rows',
            type: 'POST',
            dataType: 'json',
            data: {
                layout: layoutKey,
                source_machine_id: sourceMachineId,
                source_period_date: sourcePeriodDate,
                target_machine_id: targetMachineId,
                target_period_date: targetPeriodDate,
                row_ids: JSON.stringify(dbIds)
            }
        }).done(function (res) {
            if (!res || res.success !== true) {
                showAlert('danger', (res && res.message) ? res.message : 'Gagal memindahkan data.');
                return;
            }

            clearAllMainRowChecked();
            selectedMainRowId = '';
            applyMainRowSelection();

            var reloadPromise = $.Deferred().resolve().promise();
            if (layoutKey === 'bakar_bulu') {
                reloadPromise = loadBakarBuluPersistedRows({ silent: true });
            } else if (layoutKey === 'paddry') {
                reloadPromise = loadPaddryPersistedRows({ silent: true });
            } else if (layoutKey === 'scouring') {
                reloadPromise = loadScouringPersistedRows({ silent: true });
            } else if (layoutKey === 'presett') {
                reloadPromise = loadPreSettPersistedRows({ silent: true });
            }

            reloadPromise.always(function () {
                var targetDateText = formatDateDisplayFromIso(targetPeriodDate) || targetPeriodDate;
                var targetMachine = targetMachineId;
                var msg = res.message || 'Data berhasil dipindahkan.';
                msg += ' Tujuan: ' + targetDateText + ', machine ' + targetMachine + '.';
                showAlert('success', msg);
                $('#moveDateModal').modal('hide');
            });
        }).fail(function () {
            showAlert('danger', 'Gagal memindahkan data.');
        }).always(function () {
            $btnMoveSubmit.prop('disabled', false);
        });
    });

    $('#btnDelete').on('click', function () {
        updateMainRowsFromInputs();

        var selectedRowIds = getCheckedMainRowIdsInOrder();
        if (!selectedRowIds.length && safeText(selectedMainRowId) !== '') {
            selectedRowIds = [safeText(selectedMainRowId)];
        }
        if (!selectedRowIds.length) {
            showAlert('warning', 'Pilih data CP yang ingin dihapus terlebih dahulu.');
            return;
        }

        var selectedMap = {};
        selectedRowIds.forEach(function (rowId) {
            selectedMap[safeText(rowId)] = true;
        });

        var beforeRows = mainTableRows.slice();
        var deletedRows = [];
        mainTableRows = mainTableRows.filter(function (row) {
            var rowId = safeText(row && row._row_id);
            if (rowId !== '' && Object.prototype.hasOwnProperty.call(selectedMap, rowId)) {
                deletedRows.push(row);
                return false;
            }
            return true;
        });

        if (!deletedRows.length) {
            selectedMainRowId = getFirstCheckedMainRowId();
            applyMainRowSelection();
            showAlert('warning', 'Data terpilih tidak ditemukan.');
            return;
        }

        selectedRowIds.forEach(function (rowId) {
            setMainRowChecked(rowId, false);
        });
        if (Object.prototype.hasOwnProperty.call(selectedMap, safeText(selectedMainRowId))) {
            selectedMainRowId = getFirstCheckedMainRowId();
        }
        renderMainTable(mainTableRows);

        var layoutKey = getMainTableLayoutKey();
        var isPersistentLayout = (layoutKey === 'bakar_bulu' || layoutKey === 'paddry' || layoutKey === 'scouring' || layoutKey === 'presett');
        if (!isPersistentLayout) {
            showAlert('success', deletedRows.length + ' data CP terpilih berhasil dihapus.');
            return;
        }

        var persistedTargets = deletedRows.filter(function (row) {
            return safeText(row && row._save_state) === 'saved' && getPersistedDbIdFromRowId(row && row._row_id) > 0;
        });
        if (!persistedTargets.length) {
            showAlert('success', deletedRows.length + ' data CP terpilih berhasil dihapus.');
            return;
        }

        var periodIso = safeText($('#periodDate').val());
        var machineId = safeText(selectedMachineId);
        if (!machineId || !periodIso) {
            showAlert('warning', 'Data terhapus di tabel, namun belum bisa simpan permanen karena machine/periode belum valid.');
            return;
        }

        var actionName = layoutKey === 'bakar_bulu'
            ? 'delete_bakar_bulu_row'
            : (layoutKey === 'scouring'
                ? 'delete_scouring_row'
                : (layoutKey === 'presett' ? 'delete_presett_row' : 'delete_paddry_row'));
        var failText = layoutKey === 'bakar_bulu'
            ? 'Gagal menghapus data bakar bulu.'
            : (layoutKey === 'scouring'
                ? 'Gagal menghapus data scouring.'
                : (layoutKey === 'presett' ? 'Gagal menghapus data presett.' : 'Gagal menghapus data paddry.'));

        var $btnDelete = $('#btnDelete');
        $btnDelete.prop('disabled', true);

        var failedTargets = [];
        var latestFailureMessage = '';
        var requestIndex = 0;

        function finalizeDeleteBatch() {
            if (failedTargets.length > 0) {
                var failedMap = {};
                failedTargets.forEach(function (row) {
                    failedMap[safeText(row && row._row_id)] = true;
                });

                mainTableRows = beforeRows.filter(function (row) {
                    var rowId = safeText(row && row._row_id);
                    if (rowId === '' || !Object.prototype.hasOwnProperty.call(selectedMap, rowId)) {
                        return true;
                    }
                    return Object.prototype.hasOwnProperty.call(failedMap, rowId);
                });
                renderMainTable(mainTableRows);

                var successCount = persistedTargets.length - failedTargets.length;
                var warnText = successCount > 0
                    ? (successCount + ' data berhasil dihapus permanen, ' + failedTargets.length + ' data gagal dihapus.')
                    : (failedTargets.length + ' data gagal dihapus.');
                if (latestFailureMessage) {
                    warnText += ' ' + latestFailureMessage;
                }
                showAlert('warning', warnText);
                $btnDelete.prop('disabled', false);
                return;
            }

            showAlert('success', persistedTargets.length + ' data CP terpilih berhasil dihapus permanen.');
            $btnDelete.prop('disabled', false);
        }

        function deleteNextPersistedRow() {
            if (requestIndex >= persistedTargets.length) {
                finalizeDeleteBatch();
                return;
            }

            var row = persistedTargets[requestIndex];
            requestIndex += 1;
            var dbId = getPersistedDbIdFromRowId(row && row._row_id);
            if (dbId <= 0) {
                failedTargets.push(row);
                latestFailureMessage = 'ID data tidak valid.';
                deleteNextPersistedRow();
                return;
            }

            $.ajax({
                url: '/gg_app/pages/planning/cp_planning/cp_planning_persist_api.php?action=' + actionName,
                type: 'POST',
                dataType: 'json',
                data: {
                    machine_id: machineId,
                    period_date: periodIso,
                    row_id: dbId
                }
            }).done(function (res) {
                if (!res || res.success !== true) {
                    failedTargets.push(row);
                    latestFailureMessage = (res && res.message) ? res.message : failText;
                }
            }).fail(function () {
                failedTargets.push(row);
                latestFailureMessage = failText;
            }).always(function () {
                deleteNextPersistedRow();
            });
        }

        deleteNextPersistedRow();
    });

    $(document).on('focus click', '#cpTableBody input.plan-rko[type="date"]', function () {
        if (this.readOnly) {
            return;
        }
        if (typeof this.showPicker === 'function') {
            try {
                this.showPicker();
            } catch (err) {
                // Ignore browser-specific picker errors.
            }
        }
    });

    $(document).on('click', '#cpTableBody input[data-field="actual_start"], #cpTableBody input[data-field="actual_end"]', function () {
        if (this.readOnly) {
            return;
        }
        if (getMainTableLayoutKey() !== 'paddry') {
            return;
        }

        var nowValue = getCurrentTimeHHmm();
        var $input = $(this);
        $input.val(nowValue);

        var rowId = safeText($input.attr('data-row-id'));
        var field = safeText($input.attr('data-field'));
        if (!rowId || !field) {
            return;
        }

        for (var i = 0; i < mainTableRows.length; i++) {
            if (safeText(mainTableRows[i]._row_id) === rowId) {
                mainTableRows[i][field] = nowValue;
                break;
            }
        }
    });

    function renderMainTable(rows) {
        var $tbody = $('#cpTableBody');
        $tbody.empty();
        decorateMainTableSelectAllHeader();
        var isBakarBuluLayout = getMainTableLayoutKey() === 'bakar_bulu';
        var isMikwangLayout = getMainTableLayoutKey() === 'mikwang';
        var isJetDyeingLayout = getMainTableLayoutKey() === 'jet_dyeing';
        var isWashingPadSteamLayout = getMainTableLayoutKey() === 'washing_padsteam';
        var isCpbLayout = getMainTableLayoutKey() === 'cpb';
        var isPaddryLayout = getMainTableLayoutKey() === 'paddry';
        var isScouringLayout = getMainTableLayoutKey() === 'scouring';
        var isPreSettLayout = getMainTableLayoutKey() === 'presett';

        if (!rows.length) {
            selectedMainRowId = '';
            $tbody.append('<tr><td colspan="' + getMainTableNoDataColspan() + '" class="text-center text-muted">No Data</td></tr>');
            updateMachineCapacityIndicator();
            updateFillSameSpeedButtonState();
            togglePendingActionButtons();
            queueMainTableStickyHeaderOffsetSync();
            queueMainTableHorizontalScrollbarSync();
            return;
        }

        rows.forEach(function (row, idx) {
            var posisiHariIni = row.posisi_hari_ini || row.routing_name || '-';
            var nextRouting = row.next_routing || '-';
            var rowId = row._row_id || '';
            var rowClasses = [];
            var actualStartText = safeText(row.actual_start).replace(/\s+/g, '').trim();
            var actualEndText = safeText(row.actual_end).replace(/\s+/g, '').trim();
            var isActualStartEmpty = (actualStartText === '' || actualStartText === '-' || actualStartText === '--:--');
            var isActualEndEmpty = (actualEndText === '' || actualEndText === '-' || actualEndText === '--:--');
            if (isPaddryLayout && isActualStartEmpty && isActualEndEmpty) {
                rowClasses.push('row-actual-celup-missing');
            }
            var lateMoveFlagRaw = safeText(row.late_move_source_flag || row.is_late_move_source).toLowerCase();
            var isLateMoveSource = (
                lateMoveFlagRaw === '1' ||
                lateMoveFlagRaw === 'true' ||
                lateMoveFlagRaw === 'y' ||
                lateMoveFlagRaw === 'yes'
            );
            if (isPaddryLayout && isLateMoveSource) {
                rowClasses.push('row-late-move-source');
            }
            if (safeText(row._save_state) === 'pending') {
                rowClasses.push('row-pending');
            }
            if (safeText(rowId) !== '' && safeText(rowId) === safeText(selectedMainRowId)) {
                rowClasses.push('row-selected');
            }
            var rowClass = rowClasses.length ? ' class="' + rowClasses.join(' ') + '"' : '';

            var html = '';
            if (isBakarBuluLayout) {
                html = '<tr data-row-id="' + escapeHtml(rowId) + '"' + rowClass + '>' +
                    '<td class="text-center">' + makeRowSelectCheckbox(rowId) + (idx + 1) + '</td>' +
                    '<td>' + (row.no_cp || '-') + '</td>' +
                    '<td>' + (row.label || '-') + '</td>' +
                    '<td>' + (row.cust_color || '-') + '</td>' +
                    '<td>' + (row.kode_lab || '-') + '</td>' +
                    '<td>' + (row.routing_name || '-') + '</td>' +
                    '<td class="text-right">' + (row.qty || '-') + '</td>' +
                    '<td>' + (row.material || '-') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_machine', 'plan-machine', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_date', 'plan-date', 'dd/mm/yyyy') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_start', 'plan-start', '00:00') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_end', 'plan-end', '00:00') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_description', 'plan-desc', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_date', 'actual-date', 'dd/mm/yyyy') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_start', 'actual-start', '00:00') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_end', 'actual-end', '00:00') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_shift', 'actual-shift', '') + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'actual_realisasi', 'actual-realisasi text-right', '0.0000') + '</td>' +
                    '<td>' + posisiHariIni + '</td>' +
                    '<td>' + makeTextInput(row, 'next_routing', 'next-routing', '') + '</td>' +
                    '<td>' + (row.last_update || '-') + '</td>' +
                    '<td>' + (row.updated_by || '-') + '</td>' +
                    '</tr>';
            } else if (isScouringLayout) {
                html = '<tr data-row-id="' + escapeHtml(rowId) + '"' + rowClass + '>' +
                    '<td class="text-center">' + makeRowSelectCheckbox(rowId) + (idx + 1) + '</td>' +
                    '<td>' + (row.no_cp || '-') + '</td>' +
                    '<td>' + (row.label || '-') + '</td>' +
                    '<td>' + (row.cust_color || '-') + '</td>' +
                    '<td>' + (row.kode_lab || '-') + '</td>' +
                    '<td class="text-right">' + (row.qty || '-') + '</td>' +
                    '<td>' + (row.material || '-') + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'speed', 'speed-input text-right', '') + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'grammature', 'grammature text-right', '0.0000') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_machine', 'plan-machine', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_date', 'plan-date', 'dd/mm/yyyy') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_paddry', 'plan-paddry', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_description', 'plan-desc', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_date', 'actual-date', 'dd/mm/yyyy') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_start', 'actual-start', '00:00') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_end', 'actual-end', '00:00') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_operator', 'actual-operator', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_shift', 'actual-shift', '') + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'down_time', 'down-time text-right', '0') + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'actual_realisasi', 'actual-realisasi text-right', '0.0000') + '</td>' +
                    '<td>' + posisiHariIni + '</td>' +
                    '<td>' + makeTextInput(row, 'next_routing', 'next-routing', '') + '</td>' +
                    '<td>' + (row.last_update || '-') + '</td>' +
                    '<td>' + (row.updated_by || '-') + '</td>' +
                    '</tr>';
            } else if (isPreSettLayout) {
                html = '<tr data-row-id="' + escapeHtml(rowId) + '"' + rowClass + '>' +
                    '<td class="text-center">' + makeRowSelectCheckbox(rowId) + (idx + 1) + '</td>' +
                    '<td>' + (row.no_cp || '-') + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'urut_plan', 'urut-plan text-right', '') + '</td>' +
                    '<td>' + (row.label || '-') + '</td>' +
                    '<td>' + (row.cust_color || '-') + '</td>' +
                    '<td>' + (row.kode_lab || '-') + '</td>' +
                    '<td>' + (row.material || '-') + '</td>' +
                    '<td class="text-right">' + (row.qty || '-') + '</td>' +
                    '<td>' + makeTextInput(row, 'ket', 'ket', '') + '</td>' +
                    '<td>' + posisiHariIni + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_date', 'plan-date', 'dd/mm/yyyy') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_machine', 'plan-machine', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_start', 'actual-start', '00:00') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_end', 'actual-end', '00:00') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_operator', 'actual-operator', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_shift', 'actual-shift', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_wheel_no', 'actual-wheel-no', '') + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'down_time', 'down-time text-right', '0') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_description', 'plan-desc', '') + '</td>' +
                    '<td>' + (row.last_update || '-') + '</td>' +
                    '<td>' + (row.updated_by || '-') + '</td>' +
                    '</tr>';
            } else if (isCpbLayout) {
                html = '<tr data-row-id="' + escapeHtml(rowId) + '"' + rowClass + '>' +
                    '<td class="text-center">' + makeRowSelectCheckbox(rowId) + (idx + 1) + '</td>' +
                    '<td>' + (row.no_cp || '-') + '</td>' +
                    '<td>' + (row.label || '-') + '</td>' +
                    '<td>' + (row.cust_color || '-') + '</td>' +
                    '<td>' + (row.kode_lab || '-') + '</td>' +
                    '<td>' + (row.routing_name || '-') + '</td>' +
                    '<td class="text-right">' + (row.qty || '-') + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'speed', 'speed-input text-right', '') + '</td>' +
                    '<td>' + (row.material || '-') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_date', 'plan-date', 'dd/mm/yyyy') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_start', 'plan-start', '00:00') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_end', 'plan-end', '00:00') + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'recipe_alkali', 'cpb-recipe-alkali text-right', '0.0000') + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'recipe_dyestuff', 'cpb-recipe-dyestuff text-right', '0.0000') + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'recipe_be', 'cpb-recipe-be text-right', '0.0000') + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'initial_alkali', 'cpb-initial-alkali text-right', '0.0000') + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'initial_dyestuff', 'cpb-initial-dyestuff text-right', '0.0000') + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'remaining_alkali', 'cpb-remaining-alkali text-right', '0.0000') + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'remaining_dyestuff', 'cpb-remaining-dyestuff text-right', '0.0000') + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'sisa_saturator', 'actual-sisa-saturator text-right', '0.0000') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_description', 'plan-desc', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_date', 'actual-date', 'dd/mm/yyyy') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_start', 'actual-start', '00:00') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_end', 'actual-end', '00:00') + '</td>' +
                    '<td>' + posisiHariIni + '</td>' +
                    '<td>' + nextRouting + '</td>' +
                    '<td>' + makeTextInput(row, 'sample', 'sample', '') + '</td>' +
                    '<td>' + (row.last_update || '-') + '</td>' +
                    '<td>' + (row.updated_by || '-') + '</td>' +
                    '</tr>';
            } else if (isWashingPadSteamLayout) {
                html = '<tr data-row-id="' + escapeHtml(rowId) + '"' + rowClass + '>' +
                    '<td class="text-center">' + makeRowSelectCheckbox(rowId) + (idx + 1) + '</td>' +
                    '<td>' + (row.no_cp || '-') + '</td>' +
                    '<td>' + (row.label || '-') + '</td>' +
                    '<td>' + (row.cust_color || '-') + '</td>' +
                    '<td>' + (row.kode_lab || '-') + '</td>' +
                    '<td>' + (row.routing_name || '-') + '</td>' +
                    '<td class="text-right">' + (row.qty || '-') + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'speed', 'speed-input text-right', '') + '</td>' +
                    '<td>' + (row.material || '-') + '</td>' +
                    '<td>' + makeTextInput(row, 'cpb_actual_date', 'cpb-actual-date', 'dd/mm/yyyy') + '</td>' +
                    '<td>' + makeTextInput(row, 'cpb_actual_start', 'cpb-actual-start', '00:00') + '</td>' +
                    '<td>' + makeTextInput(row, 'cpb_actual_end', 'cpb-actual-end', '00:00') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_date', 'plan-date', 'dd/mm/yyyy') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_start', 'plan-start', '00:00') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_end', 'plan-end', '00:00') + '</td>' +
                    '<td>' + makeTextInput(row, 'bt_jam', 'bt-jam text-right', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_description', 'plan-desc', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_date', 'actual-date', 'dd/mm/yyyy') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_start', 'actual-start', '00:00') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_end', 'actual-end', '00:00') + '</td>' +
                    '<td>' + posisiHariIni + '</td>' +
                    '<td>' + nextRouting + '</td>' +
                    '<td>' + makeTextInput(row, 'sample', 'sample', '') + '</td>' +
                    '<td>' + (row.last_update || '-') + '</td>' +
                    '<td>' + (row.updated_by || '-') + '</td>' +
                    '</tr>';
            } else if (isJetDyeingLayout) {
                html = '<tr data-row-id="' + escapeHtml(rowId) + '"' + rowClass + '>' +
                    '<td class="text-center">' + makeRowSelectCheckbox(rowId) + (idx + 1) + '</td>' +
                    '<td>' + (row.no_cp || '-') + '</td>' +
                    '<td>' + (row.label || '-') + '</td>' +
                    '<td>' + (row.cust_color || '-') + '</td>' +
                    '<td>' + (row.kode_lab || '-') + '</td>' +
                    '<td>' + (row.routing_name || '-') + '</td>' +
                    '<td class="text-right">' + (row.qty || '-') + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'speed', 'speed-input text-right', '') + '</td>' +
                    '<td>' + (row.material || '-') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_date', 'plan-date', 'dd/mm/yyyy') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_start', 'plan-start', '00:00') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_end', 'plan-end', '00:00') + '</td>' +
                    '<td>' + makeTextInput(row, 'bon_resp', 'plan-bon-resp', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'resep_ke_gdg', 'plan-resep-gdg', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_description', 'plan-desc', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_date', 'actual-date', 'dd/mm/yyyy') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_start', 'actual-start', '00:00') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_end', 'actual-end', '00:00') + '</td>' +
                    '<td>' + posisiHariIni + '</td>' +
                    '<td>' + nextRouting + '</td>' +
                    '<td>' + (row.last_update || '-') + '</td>' +
                    '<td>' + (row.updated_by || '-') + '</td>' +
                    '</tr>';
            } else if (isMikwangLayout) {
                html = '<tr data-row-id="' + escapeHtml(rowId) + '"' + rowClass + '>' +
                    '<td class="text-center">' + makeRowSelectCheckbox(rowId) + (idx + 1) + '</td>' +
                    '<td>' + (row.no_cp || '-') + '</td>' +
                    '<td>' + (row.label || '-') + '</td>' +
                    '<td>' + (row.cust_color || '-') + '</td>' +
                    '<td>' + (row.kode_lab || '-') + '</td>' +
                    '<td>' + (row.routing_name || '-') + '</td>' +
                    '<td class="text-right">' + (row.qty || '-') + '</td>' +
                    '<td>' + (row.material || '-') + '</td>' +
                    '<td>' + makeTextInput(row, 'bon_resp', 'plan-bon-resp', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_description', 'plan-desc', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_date', 'actual-date', 'dd/mm/yyyy') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_start', 'actual-start', '00:00') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_end', 'actual-end', '00:00') + '</td>' +
                    '<td>' + posisiHariIni + '</td>' +
                    '<td>' + nextRouting + '</td>' +
                    '<td>' + (row.last_update || '-') + '</td>' +
                    '<td>' + (row.updated_by || '-') + '</td>' +
                    '</tr>';
            } else if (isPaddryLayout) {
                row.actual_timbang_lab_start = safeText(row.actual_timbang_lab_start) || safeText(row.actual_timbang_lalab_start);
                row.actual_timbang_lab_finish = safeText(row.actual_timbang_lab_finish) || safeText(row.actual_timbang_lalab_finish);
                row.actual_timbang_la_start = safeText(row.actual_timbang_la_start);
                row.actual_timbang_la_finish = safeText(row.actual_timbang_la_finish);
                row.actual_larut_lab_start = safeText(row.actual_larut_lab_start) || safeText(row.actual_larut_lalab_start);
                row.actual_larut_lab_finish = safeText(row.actual_larut_lab_finish) || safeText(row.actual_larut_lalab_finish);
                row.actual_larut_la_start = safeText(row.actual_larut_la_start);
                row.actual_larut_la_finish = safeText(row.actual_larut_la_finish);
                row.actual_topping_paddry_start = safeText(row.actual_topping_paddry_start) || safeText(row.actual_topping_start) || safeText(row.aktual_topping_paddry_start) || safeText(row.aktual_topping_start);
                row.actual_topping_paddry_finish = safeText(row.actual_topping_paddry_finish) || safeText(row.actual_topping_finish) || safeText(row.aktual_topping_paddry_finish) || safeText(row.aktual_topping_finish);
                var posisiHariIniRaw = safeText(row.posisi_hari_ini);
                var routingNameRaw = safeText(row.routing_name);
                if ((posisiHariIniRaw === '' || posisiHariIniRaw === '-') && /VER\s*PACK/i.test(routingNameRaw)) {
                    row.posisi_hari_ini = 'VERPACKING';
                }
                html = '<tr data-row-id="' + escapeHtml(rowId) + '"' + rowClass + '>' +
                    '<td class="text-center">' + makeRowSelectCheckbox(rowId) + (idx + 1) + '</td>' +
                    '<td>' + (row.no_cp || '-') + '</td>' +
                    '<td>' + (row.label || '-') + '</td>' +
                    '<td>' + (row.cust_color || '-') + '</td>' +
                    '<td>' + (row.kode_lab || '-') + '</td>' +
                    '<td>' + makeTextInput(row, 'material', 'material-name', '') + '</td>' +
                    '<td class="text-right">' + (row.qty || '-') + '</td>' +
                    '<td class="text-right">' + (row.routing_qty || '-') + '</td>' +
                    '<td>' + makeTextInput(row, 'posisi_hari_ini', 'posisi-hari-ini', '') + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'speed', 'speed-input text-right', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'resp_lipat', 'plan-resp-lipat', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'status_resp', 'plan-status-resp', '') + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'vlot_resp', 'plan-vlot-resp text-right', '0.0000') + '</td>' +
                    '<td>' + makeTextInput(row, 'bon_resp', 'plan-bon-resp', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_description', 'plan-desc', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_date', 'plan-date', 'dd/mm/yyyy') + '</td>' +
                    '<td>' + makeTextInput(row, 'est_tmbng_plrtm_lalab', 'est-tmbng-plrtm-lalab', '00:00', 'time') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_timbang_lab_start', 'actual-timbang-lab-start', '00:00', 'time') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_timbang_lab_finish', 'actual-timbang-lab-finish', '00:00', 'time') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_timbang_la_start', 'actual-timbang-la-start', '00:00', 'time') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_timbang_la_finish', 'actual-timbang-la-finish', '00:00', 'time') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_larut_lab_start', 'actual-larut-lab-start', '00:00', 'time') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_larut_lab_finish', 'actual-larut-lab-finish', '00:00', 'time') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_larut_la_start', 'actual-larut-la-start', '00:00', 'time') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_larut_la_finish', 'actual-larut-la-finish', '00:00', 'time') + '</td>' +
                    '<td>' + makeTextInput(row, 'est_plrtm_prdks', 'est-plrtm-prdks', '00:00', 'time') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_larut_prdks_start', 'actual-larut-prdks-start', '00:00', 'time') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_larut_prdks_finish', 'actual-larut-prdks-finish', '00:00', 'time') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_start', 'plan-start', '00:00', 'time') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_end', 'plan-end', '00:00', 'time') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_start', 'actual-start', '00:00', 'time') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_end', 'actual-end', '00:00', 'time') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_topping_paddry_start', 'actual-topping-paddry-start', '00:00', 'time') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_topping_paddry_finish', 'actual-topping-paddry-finish', '00:00', 'time') + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'actual_vlot', 'actual-vlot text-right', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'sisa_saturator', 'actual-sisa-saturator', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'sample', 'sample', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'rko', 'plan-rko', '', 'date') + '</td>' +
                    '<td>' + makeTextInput(row, 'next_routing', 'next-routing', '') + '</td>' +
                    '<td>' + (row.last_update || '-') + '</td>' +
                    '<td>' + (row.updated_by || '-') + '</td>' +
                    '</tr>';
            } else {
                html = '<tr data-row-id="' + escapeHtml(rowId) + '"' + rowClass + '>' +
                    '<td class="text-center">' + makeRowSelectCheckbox(rowId) + (idx + 1) + '</td>' +
                    '<td>' + (row.no_cp || '-') + '</td>' +
                    '<td>' + (row.label || '-') + '</td>' +
                    '<td>' + (row.cust_color || '-') + '</td>' +
                    '<td>' + (row.kode_lab || '-') + '</td>' +
                    '<td>' + (row.routing_name || '-') + '</td>' +
                    '<td class="text-right">' + (row.qty || '-') + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'speed', 'speed-input text-right', '') + '</td>' +
                    '<td>' + (row.material || '-') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_date', 'plan-date', 'dd/mm/yyyy') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_start', 'plan-start', '00:00') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_end', 'plan-end', '00:00') + '</td>' +
                    '<td>' + makeTextInput(row, 'rko', 'plan-rko', '', 'date') + '</td>' +
                    '<td>' + makeTextInput(row, 'resp_lipat', 'plan-resp-lipat', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'status_resp', 'plan-status-resp', '') + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'vlot_resp', 'plan-vlot-resp text-right', '0.0000') + '</td>' +
                    '<td>' + makeTextInput(row, 'bon_resp', 'plan-bon-resp', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_description', 'plan-desc', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_date', 'actual-date', 'dd/mm/yyyy') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_start', 'actual-start', '00:00') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_end', 'actual-end', '00:00') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_shift', 'actual-shift', '') + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'actual_vlot', 'actual-vlot text-right', '0.0000') + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'sisa_tanggal', 'actual-sisa-tanggal text-right', '0.0000') + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'sisa_saturator', 'actual-sisa-saturator text-right', '0.0000') + '</td>' +
                    '<td>' + posisiHariIni + '</td>' +
                    '<td>' + nextRouting + '</td>' +
                    '<td>' + makeTextInput(row, 'sample', 'sample', '') + '</td>' +
                    '<td>' + (row.last_update || '-') + '</td>' +
                    '<td>' + (row.updated_by || '-') + '</td>' +
                    '</tr>';
            }
            $tbody.append(html);
        });
        appendMainTableQtyTotalRow($tbody, rows);
        updateMachineCapacityIndicator();

        updatePaddryInputModeUi();
        applyMainRowSelection();
        updateFillSameSpeedButtonState();
        togglePendingActionButtons();
        queueMainTableStickyHeaderOffsetSync();
        queueMainTableHorizontalScrollbarSync();
    }

    $('#btnSubmitCpSearch').on('click', function () {
        var selectedRows = getSelectedCpRowsList();
        if (!selectedRows.length) {
            return;
        }

        if (!safeText(selectedPlanType)) {
            showAlert('warning', 'Pilih tipe planning terlebih dahulu.');
            return;
        }

        if (!safeText(selectedMachineId)) {
            showAlert('warning', 'Pilih machine terlebih dahulu.');
            return;
        }

        updateMainRowsFromInputs();

        var periodIso = safeText($('#periodDate').val());
        var periodDisplay = formatDateDisplayFromIso(periodIso) || formatTodayDisplay();
        var isPaddrySelectedLayout = getMainTableLayoutKey() === 'paddry';
        var isScouringSelectedLayout = getMainTableLayoutKey() === 'scouring';
        var isPreSettSelectedLayout = getMainTableLayoutKey() === 'presett';
        var usesPaddryReferenceLayout = isScouringSelectedLayout || isPreSettSelectedLayout;
        var selectedMachine = getSelectedMachineRow();
        var selectedMachineLabel = '';
        if (selectedMachine) {
            selectedMachineLabel = safeText(selectedMachine.faname) || safeText(selectedMachine.facode);
        }

        var appendSelectedRows = function (cpLookupMap) {
            var paddryMap = (cpLookupMap && typeof cpLookupMap === 'object' && !Array.isArray(cpLookupMap)) ? cpLookupMap : {};
            rebuildCpExistingKeyMap();
            var skippedCount = 0;

            selectedRows.forEach(function (rowData) {
                var rowUniqueKey = buildCpUniqueKeyFromSearchRow(rowData);
                if (rowUniqueKey !== '' && Object.prototype.hasOwnProperty.call(cpSearchExistingKeyMap, rowUniqueKey)) {
                    skippedCount += 1;
                    return;
                }

                var kategoriPenimbangan = getKategoriPenimbanganFromRowData(rowData);
                var autoKetPaddry = isPaddrySelectedLayout ? kategoriPenimbangan : '';
                var cpLookupKey = normalizeCpLookupKey(rowData[0]);
                var paddryRef = (cpLookupKey !== '' && Object.prototype.hasOwnProperty.call(paddryMap, cpLookupKey))
                    ? paddryMap[cpLookupKey]
                    : null;
                var autoPaddryDate = paddryRef ? safeText(paddryRef.plan_date) : '';
                var autoPaddryMachine = paddryRef ? safeText(paddryRef.plan_paddry) : '';

                var mapped = {
                    _row_id: nextRowId(),
                    _save_state: 'pending',
                    no_cp: rowData[0],
                    urut_plan: '',
                    ket: '',
                    tgl_cp: rowData[1],
                    routing_code: rowData[2],
                    routing_name: rowData[3],
                    lokasi_paddry: isPaddrySelectedLayout
                        ? (rowData[30] || rowData[3] || '')
                        : '',
                    product_code: rowData[4],
                    product_name: rowData[5],
                    work_center_code: rowData[7],
                    work_center_name: rowData[8],
                    kode_lab: rowData[9],
                    color_name: rowData[10],
                    label: rowData[11],
                    cust_color: rowData[12],
                    material: rowData[21],
                    qty: rowData[22],
                    grammature: '0.0000',
                    speed: '',
                    temperatur: '',
                    plan_machine: isPreSettSelectedLayout
                        ? autoPaddryMachine
                        : selectedMachineLabel,
                    plan_date: usesPaddryReferenceLayout ? autoPaddryDate : periodDisplay,
                    plan_paddry: (isScouringSelectedLayout && autoPaddryMachine !== '') ? autoPaddryMachine : '',
                    plan_start: '00:00',
                    plan_end: '00:00',
                    rko: '',
                    resp_lipat: '',
                    status_resp: '',
                    vlot_resp: rowData[24],
                    bon_resp: '',
                    plan_description: autoKetPaddry,
                    actual_date: '',
                    actual_start: '',
                    actual_end: '',
                    actual_timbang_lab_start: '',
                    actual_timbang_lab_finish: '',
                    actual_timbang_la_start: '',
                    actual_timbang_la_finish: '',
                    actual_larut_lab_start: '',
                    actual_larut_lab_finish: '',
                    actual_larut_la_start: '',
                    actual_larut_la_finish: '',
                    actual_timbang_lalab_start: '',
                    actual_timbang_lalab_finish: '',
                    actual_larut_lalab_start: '',
                    actual_larut_lalab_finish: '',
                    actual_larut_prdks_start: '',
                    actual_larut_prdks_finish: '',
                    actual_topping_paddry_start: '',
                    actual_topping_paddry_finish: '',
                    actual_operator: '',
                    actual_shift: '',
                    down_time: '',
                    actual_wheel_no: '',
                    actual_realisasi: '',
                    actual_vlot: '',
                    cpb_actual_date: '',
                    cpb_actual_start: '',
                    cpb_actual_end: '',
                    bt_jam: '',
                    resep_ke_gdg: '',
                    est_tmbng_plrtm_lalab: '00:00',
                    est_plrtm_prdks: '00:00',
                    recipe_alkali: '',
                    recipe_dyestuff: '',
                    recipe_be: '',
                    initial_alkali: '',
                    initial_dyestuff: '',
                    remaining_alkali: '',
                    remaining_dyestuff: '',
                    sisa_tanggal: '',
                    sisa_saturator: '',
                    posisi_hari_ini: rowData[6] || rowData[27] || rowData[3] || '-',
                    next_routing: rowData[28] || rowData[23] || '-',
                    sample: '',
                    last_update: periodDisplay,
                    updated_by: safeText(currentUser) || '-',
                    vlot: rowData[24],
                    kategori: kategoriPenimbangan,
                    lokasi_washing: rowData[35] || '-',
                    lokasi_jetdyeing: rowData[36] || '-'
                };
                mainTableRows.push(mapped);
                if (rowUniqueKey !== '') {
                    cpSearchExistingKeyMap[rowUniqueKey] = true;
                }
            });

            if (isPaddrySelectedLayout) {
                if (isPaddryAutoScheduleEnabled()) {
                    recalculatePaddryPlanSchedule();
                }
            }
            renderMainTable(mainTableRows);
            if (cpSearchTable && $('#cpSearchModal').hasClass('show')) {
                clearCpSelection();
                cpSearchTable.draw(false);
            } else {
                clearCpSelection();
            }
            if (skippedCount > 0) {
                showAlert('info', skippedCount + ' data CP duplikat tidak ditambahkan.');
                return;
            }
            hideAlert();
        };

        if (!usesPaddryReferenceLayout) {
            appendSelectedRows({});
            return;
        }

        var cpNosPayload = [];
        var cpNosSeen = {};
        selectedRows.forEach(function (rowData) {
            var cpKey = normalizeCpLookupKey(rowData[0]);
            if (cpKey === '' || Object.prototype.hasOwnProperty.call(cpNosSeen, cpKey)) {
                return;
            }
            cpNosSeen[cpKey] = true;
            cpNosPayload.push(cpKey);
        });

        if (!cpNosPayload.length) {
            appendSelectedRows({});
            return;
        }

        var $btnSubmit = $('#btnSubmitCpSearch');
        $btnSubmit.prop('disabled', true);

        $.ajax({
            url: '/gg_app/pages/planning/cp_planning/cp_planning.php?action=lookup_paddry_planning',
            type: 'POST',
            dataType: 'json',
            data: {
                cp_nos: JSON.stringify(cpNosPayload)
            }
        }).done(function (res) {
            var map = {};
            if (res && res.data && typeof res.data === 'object' && !Array.isArray(res.data)) {
                map = res.data;
            }
            appendSelectedRows(map);
        }).fail(function () {
            appendSelectedRows({});
            showAlert('warning', 'Referensi paddry tidak bisa dimuat otomatis. Data tetap ditambahkan tanpa auto-fill paddry.');
        }).always(function () {
            $btnSubmit.prop('disabled', false);
        });
    });

    $('#btnCommitPending').on('click', function () {
        if (!hasPendingRows()) {
            capacityOverLimitConfirmBypass = false;
            return;
        }

        updateMainRowsFromInputs();

        if (!capacityOverLimitConfirmBypass) {
            var overLimitInfo = getCapacityOverLimitInfo();
            if (overLimitInfo) {
                askCapacityOverLimitConfirmation(overLimitInfo).done(function (approved) {
                    if (!approved) {
                        showAlert('info', 'Simpan permanen dibatalkan.');
                        return;
                    }
                    capacityOverLimitConfirmBypass = true;
                    $('#btnCommitPending').trigger('click');
                });
                return;
            }
        }
        capacityOverLimitConfirmBypass = false;

        if (getMainTableLayoutKey() === 'bakar_bulu') {
            var periodIso = safeText($('#periodDate').val());
            var machineId = safeText(selectedMachineId);

            if (!machineId) {
                showAlert('warning', 'Pilih machine terlebih dahulu.');
                return;
            }
            if (!periodIso) {
                showAlert('warning', 'Periode wajib diisi.');
                return;
            }

            var rowsPayload = mainTableRows.map(function (row, idx) {
                var mapped = $.extend({}, row);
                delete mapped._original_data;
                delete mapped._edited_from_saved;
                mapped.seq_no = idx + 1;
                return mapped;
            });

            var $btnCommit = $('#btnCommitPending');
            $btnCommit.prop('disabled', true);

            $.ajax({
                url: '/gg_app/pages/planning/cp_planning/cp_planning_persist_api.php?action=save_bakar_bulu',
                type: 'POST',
                dataType: 'json',
                data: {
                    machine_id: machineId,
                    period_date: periodIso,
                    rows: JSON.stringify(rowsPayload)
                }
            }).done(function (res) {
                if (!res || res.success !== true) {
                    showAlert('danger', (res && res.message) ? res.message : 'Gagal menyimpan data bakar bulu.');
                    return;
                }

                loadBakarBuluPersistedRows({ silent: true }).always(function () {
                    showAlert('success', res.message || 'Data bakar bulu berhasil disimpan permanen.');
                });
            }).fail(function () {
                showAlert('danger', 'Gagal menyimpan data bakar bulu.');
            }).always(function () {
                $btnCommit.prop('disabled', false);
            });
            return;
        }

        if (getMainTableLayoutKey() === 'paddry') {
            var periodIsoPaddry = safeText($('#periodDate').val());
            var machineIdPaddry = safeText(selectedMachineId);

            if (!machineIdPaddry) {
                showAlert('warning', 'Pilih machine terlebih dahulu.');
                return;
            }
            if (!periodIsoPaddry) {
                showAlert('warning', 'Periode wajib diisi.');
                return;
            }

            var rowsPayloadPaddry = mainTableRows.map(function (row, idx) {
                var mapped = $.extend({}, row);
                delete mapped._original_data;
                delete mapped._edited_from_saved;
                delete mapped._manual_plan_start;
                delete mapped._manual_plan_end;
                mapped.seq_no = idx + 1;
                return mapped;
            });

            var $btnCommitPaddry = $('#btnCommitPending');
            $btnCommitPaddry.prop('disabled', true);

            $.ajax({
                url: '/gg_app/pages/planning/cp_planning/cp_planning_persist_api.php?action=save_paddry',
                type: 'POST',
                dataType: 'json',
                data: {
                    machine_id: machineIdPaddry,
                    period_date: periodIsoPaddry,
                    rows: JSON.stringify(rowsPayloadPaddry)
                }
            }).done(function (res) {
                if (!res || res.success !== true) {
                    showAlert('danger', (res && res.message) ? res.message : 'Gagal menyimpan data paddry.');
                    return;
                }

                loadPaddryPersistedRows({ silent: true }).always(function () {
                    showAlert('success', res.message || 'Data paddry berhasil disimpan permanen.');
                });
            }).fail(function () {
                showAlert('danger', 'Gagal menyimpan data paddry.');
            }).always(function () {
                $btnCommitPaddry.prop('disabled', false);
            });
            return;
        }

        if (getMainTableLayoutKey() === 'scouring') {
            var periodIsoScouring = safeText($('#periodDate').val());
            var machineIdScouring = safeText(selectedMachineId);

            if (!machineIdScouring) {
                showAlert('warning', 'Pilih machine terlebih dahulu.');
                return;
            }
            if (!periodIsoScouring) {
                showAlert('warning', 'Periode wajib diisi.');
                return;
            }

            var rowsPayloadScouring = mainTableRows.map(function (row, idx) {
                var mapped = $.extend({}, row);
                delete mapped._original_data;
                delete mapped._edited_from_saved;
                mapped.seq_no = idx + 1;
                return mapped;
            });

            var $btnCommitScouring = $('#btnCommitPending');
            $btnCommitScouring.prop('disabled', true);

            $.ajax({
                url: '/gg_app/pages/planning/cp_planning/cp_planning_persist_api.php?action=save_scouring',
                type: 'POST',
                dataType: 'json',
                data: {
                    machine_id: machineIdScouring,
                    period_date: periodIsoScouring,
                    rows: JSON.stringify(rowsPayloadScouring)
                }
            }).done(function (res) {
                if (!res || res.success !== true) {
                    showAlert('danger', (res && res.message) ? res.message : 'Gagal menyimpan data scouring.');
                    return;
                }

                loadScouringPersistedRows({ silent: true }).always(function () {
                    showAlert('success', res.message || 'Data scouring berhasil disimpan permanen.');
                });
            }).fail(function () {
                showAlert('danger', 'Gagal menyimpan data scouring.');
            }).always(function () {
                $btnCommitScouring.prop('disabled', false);
            });
            return;
        }

        if (getMainTableLayoutKey() === 'presett') {
            var periodIsoPreSett = safeText($('#periodDate').val());
            var machineIdPreSett = safeText(selectedMachineId);

            if (!machineIdPreSett) {
                showAlert('warning', 'Pilih machine terlebih dahulu.');
                return;
            }
            if (!periodIsoPreSett) {
                showAlert('warning', 'Periode wajib diisi.');
                return;
            }

            var rowsPayloadPreSett = mainTableRows.map(function (row, idx) {
                var mapped = $.extend({}, row);
                delete mapped._original_data;
                delete mapped._edited_from_saved;
                mapped.seq_no = idx + 1;
                return mapped;
            });

            var $btnCommitPreSett = $('#btnCommitPending');
            $btnCommitPreSett.prop('disabled', true);

            $.ajax({
                url: '/gg_app/pages/planning/cp_planning/cp_planning_persist_api.php?action=save_presett',
                type: 'POST',
                dataType: 'json',
                data: {
                    machine_id: machineIdPreSett,
                    period_date: periodIsoPreSett,
                    rows: JSON.stringify(rowsPayloadPreSett)
                }
            }).done(function (res) {
                if (!res || res.success !== true) {
                    showAlert('danger', (res && res.message) ? res.message : 'Gagal menyimpan data presett.');
                    return;
                }

                loadPreSettPersistedRows({ silent: true }).always(function () {
                    showAlert('success', res.message || 'Data presett berhasil disimpan permanen.');
                });
            }).fail(function () {
                showAlert('danger', 'Gagal menyimpan data presett.');
            }).always(function () {
                $btnCommitPreSett.prop('disabled', false);
            });
            return;
        }

        var nowDisplay = formatTodayDisplay();
        mainTableRows = mainTableRows.map(function (row) {
            if (safeText(row._save_state) === 'pending') {
                row._save_state = 'saved';
                row.last_update = nowDisplay;
                row.updated_by = safeText(currentUser) || '-';
            }
            return row;
        });

        clearOrderDirtyState();
        renderMainTable(mainTableRows);
        showAlert('success', 'Data sementara dikonfirmasi. Status: siap untuk simpan permanen ke database.');
    });

    function cancelPendingChanges() {
        var hasDataPendingBefore = mainTableRows.some(function (row) {
            return safeText(row._save_state) === 'pending';
        });
        if (!hasDataPendingBefore && !orderDirty) {
            togglePendingActionButtons();
            showAlert('info', 'Tidak ada data sementara yang perlu dibatalkan.');
            return;
        }

        updateMainRowsFromInputs();
        var hasDataPending = mainTableRows.some(function (row) {
            return safeText(row._save_state) === 'pending';
        });
        if (!hasDataPending && orderDirty) {
            var restoredOrderOnly = restoreRowOrderFromSnapshot();
            clearOrderDirtyState();
            renderMainTable(mainTableRows);
            togglePendingActionButtons();
            if (restoredOrderOnly) {
                showAlert('info', 'Perubahan urutan data dibatalkan.');
            } else {
                showAlert('info', 'Tidak ada perubahan urutan yang perlu dibatalkan.');
            }
            return;
        }

        var restoredCount = 0;
        var removedCount = 0;
        mainTableRows = mainTableRows.reduce(function (acc, row) {
            if (safeText(row._save_state) !== 'pending') {
                acc.push(row);
                return acc;
            }

            if (row && row._edited_from_saved === true && row._original_data && typeof row._original_data === 'object') {
                var restored = $.extend(true, {}, row._original_data);
                restored._save_state = 'saved';
                delete restored._edited_from_saved;
                delete restored._original_data;
                acc.push(restored);
                restoredCount += 1;
                return acc;
            }

            removedCount += 1;
            return acc;
        });

        if (orderDirty) {
            restoreRowOrderFromSnapshot();
        }
        clearOrderDirtyState();
        selectedMainRowId = '';
        renderMainTable(mainTableRows);
        togglePendingActionButtons();

        if (restoredCount > 0 && removedCount > 0) {
            showAlert('info', restoredCount + ' data edit dikembalikan, ' + removedCount + ' data baru dibatalkan.');
        } else if (restoredCount > 0) {
            showAlert('info', restoredCount + ' data edit dikembalikan ke kondisi semula.');
        } else {
            showAlert('info', 'Data sementara dibatalkan.');
        }
    }

    $(document).off('click.cpCancelPending', '#btnCancelPending').on('click.cpCancelPending', '#btnCancelPending', function (e) {
        e.preventDefault();
        cancelPendingChanges();
    });

    function updatePeriodText() {
        var raw = safeText($('#periodDate').val());
        if (!raw) {
            $('#periodText').text('-');
            return;
        }

        var parts = raw.split('-');
        if (parts.length !== 3) {
            $('#periodText').text(raw);
            return;
        }

        var year = Number(parts[0]);
        var month = Number(parts[1]);
        var day = Number(parts[2]);
        var dateObj = new Date(year, month - 1, day);
        if (isNaN(dateObj.getTime())) {
            $('#periodText').text(raw);
            return;
        }

        var dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        var monthNames = [
            'Januari',
            'Februari',
            'Maret',
            'April',
            'Mei',
            'Juni',
            'Juli',
            'Agustus',
            'September',
            'Oktober',
            'November',
            'Desember'
        ];

        var label = dayNames[dateObj.getDay()] + ', ' + dateObj.getDate() + ' ' + monthNames[dateObj.getMonth()] + ' ' + dateObj.getFullYear();
        $('#periodText').text(label);
    }

    $('#periodDate').on('change', function () {
        updatePeriodText();
        if (getMainTableLayoutKey() === 'bakar_bulu') {
            if (hasPendingRows()) {
                showAlert('warning', 'Masih ada data sementara. Klik checklist atau silang terlebih dahulu.');
                return;
            }
            loadBakarBuluPersistedRows({ silent: false });
        } else if (getMainTableLayoutKey() === 'paddry') {
            if (hasPendingRows()) {
                showAlert('warning', 'Masih ada data sementara. Klik checklist atau silang terlebih dahulu.');
                return;
            }
            loadPaddryPersistedRows({ silent: false });
        } else if (getMainTableLayoutKey() === 'scouring') {
            if (hasPendingRows()) {
                showAlert('warning', 'Masih ada data sementara. Klik checklist atau silang terlebih dahulu.');
                return;
            }
            loadScouringPersistedRows({ silent: false });
        }
    });

    renderMainTableHeader();
    queueMainTableStickyHeaderOffsetSync();
    bindMainTableColumnResizeEvents();
    initializeMainTableColumnResize();
    renderMainTable(mainTableRows);
    initMainTableHorizontalScrollbarSync();
    queueMainTableHorizontalScrollbarSync();
    ensureBreakTimeRowsReady();
    loadPlanTypes();
    renderMachineList();
    updatePeriodText();
});
</script>
