<?php
session_start();
ob_start();

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu!';
    header('Location: /gg_app/login.php');
    exit;
}

$isProcessReportExportBootstrap = defined('PROCESS_REPORT_EXPORT_BOOTSTRAP') && PROCESS_REPORT_EXPORT_BOOTSTRAP === true;

include '../../../koneksi.php';
include '../../../koneksi3.php';
if (!$isProcessReportExportBootstrap) {
    include '../../../includes/header.php';
    include '../../../includes/sidebar.php';
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
$todayIso = date('Y-m-d');
$monthStartIso = date('Y-m-01');

$dateFromInput = trim((string)($_GET['date_from'] ?? $monthStartIso));
$dateToInput = trim((string)($_GET['date_to'] ?? $todayIso));
$selectedProcess = trim((string)($_GET['process'] ?? ''));

$isIsoDate = static function (string $value): bool {
    return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
};

$dateFrom = $isIsoDate($dateFromInput) ? $dateFromInput : $monthStartIso;
$dateTo = $isIsoDate($dateToInput) ? $dateToInput : $todayIso;

if ($dateFrom > $dateTo) {
    $tmp = $dateFrom;
    $dateFrom = $dateTo;
    $dateTo = $tmp;
}

$esc = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$formatDate = static function ($value): string {
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

    $formats = ['Y-m-d', 'Y-m-d H:i:s', 'Y-m-d H:i:s.u', 'd/m/Y', 'd-m-Y'];
    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $raw);
        if ($dt instanceof DateTime) {
            return $dt->format('d/m/Y');
        }
    }

    $ts = strtotime($raw);
    return ($ts !== false) ? date('d/m/Y', $ts) : $raw;
};

$formatQty = static function ($value, int $precision = 0): string {
    $precision = max(0, $precision);
    if ($value === null || $value === '') {
        return '-';
    }
    if (is_numeric($value)) {
        return number_format((float)$value, $precision, '.', ',');
    }
    $raw = trim((string)$value);
    if ($raw === '' || $raw === '-') {
        return '-';
    }
    $normalized = str_replace(',', '', $raw);
    return is_numeric($normalized) ? number_format((float)$normalized, $precision, '.', ',') : $raw;
};

$formatPercent = static function ($value): string {
    if (!is_numeric($value)) {
        return '0.0%';
    }
    return number_format((float)$value, 1, '.', ',') . '%';
};

$toQtyFloat = static function ($value): float {
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

$toIsoDate = static function ($value): string {
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

    $formats = ['Y-m-d', 'Y-m-d H:i:s', 'Y-m-d H:i:s.u', 'd/m/Y', 'd-m-Y'];
    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $raw);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d');
        }
    }

    $ts = strtotime($raw);
    return ($ts !== false) ? date('Y-m-d', $ts) : '';
};

$extractProcess = static function (string $kodeLab): string {
    $kodeLab = trim($kodeLab);
    if ($kodeLab === '') {
        return '';
    }

    if (preg_match('/^\s*\d+\.\d+\.(\d+)(?:\.|$)/', $kodeLab, $m) === 1) {
        return ltrim($m[1], '0') === '' ? '0' : ltrim($m[1], '0');
    }

    $parts = explode('.', $kodeLab);
    if (count($parts) >= 3) {
        $candidate = trim((string)$parts[2]);
        if ($candidate !== '' && preg_match('/^\d+$/', $candidate) === 1) {
            return ltrim($candidate, '0') === '' ? '0' : ltrim($candidate, '0');
        }
    }

    return '';
};

$normalizeCpKey = static function ($value): string {
    return strtoupper(trim((string)$value));
};

$normalizeRoutingKey = static function ($value): string {
    $raw = trim((string)$value);
    if ($raw === '') {
        return '';
    }
    $raw = preg_replace('/\s+/', ' ', $raw) ?? $raw;
    return strtoupper($raw);
};

$extractRoutingParts = static function ($value) use ($normalizeRoutingKey): array {
    $raw = trim((string)$value);
    if ($raw === '') {
        return [];
    }
    $parts = preg_split('/\s*,\s*/', $raw);
    if (!is_array($parts)) {
        return [];
    }

    $out = [];
    foreach ($parts as $part) {
        $key = $normalizeRoutingKey($part);
        if ($key === '') {
            continue;
        }
        $out[$key] = $key;
    }
    return array_values($out);
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

$tableColumns = static function ($conn, string $tableName): array {
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
        $name = strtolower(trim((string)($row['col_name'] ?? '')));
        if ($name !== '') {
            $cols[$name] = true;
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

$pgQuoteIdentifier = static function (string $identifier): string {
    return '"' . str_replace('"', '""', $identifier) . '"';
};

$pgTableColumns = static function ($pdo, string $tableName): array {
    if (!($pdo instanceof PDO)) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT LOWER(column_name) AS col_name
        FROM information_schema.columns
        WHERE LOWER(table_name) = LOWER(:table_name)
          AND table_schema NOT IN ('pg_catalog', 'information_schema')
    ");
    if (!($stmt instanceof PDOStatement) || !$stmt->execute([':table_name' => $tableName])) {
        return [];
    }

    $cols = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $name = strtolower(trim((string)($row['col_name'] ?? '')));
        if ($name !== '') {
            $cols[$name] = true;
        }
    }
    return $cols;
};

$sqlErrText = static function (): string {
    $errs = sqlsrv_errors(SQLSRV_ERR_ERRORS);
    if (!is_array($errs) || empty($errs)) {
        return 'Terjadi kesalahan SQL Server.';
    }

    $messages = [];
    foreach ($errs as $err) {
        $msg = trim((string)($err['message'] ?? ''));
        if ($msg !== '') {
            $messages[] = $msg;
        }
    }

    return empty($messages) ? 'Terjadi kesalahan SQL Server.' : implode(' | ', $messages);
};

$pageWarnings = [];
$pageError = '';
$rows = [];
$planningByCp = [];

if (($conn3 ?? null) instanceof PDO) {
    try {
        $sqlPlanningSource = "
            WITH verpacking AS (
                SELECT
                    r.productionhdid,
                    CAST(COALESCE(r.starttime, r.startdate, r.endtime, r.enddate) AS DATE) AS verpacking_date,
                    ROW_NUMBER() OVER (
                        PARTITION BY r.productionhdid
                        ORDER BY
                            CASE WHEN r.starttime IS NOT NULL THEN 0 ELSE 1 END ASC,
                            COALESCE(r.starttime, r.startdate, r.endtime, r.enddate, r.upddate) DESC NULLS LAST,
                            r.productionrtgid DESC
                    ) AS rn
                FROM pdproductionrtg r
                WHERE r.rtgmsid = 692
                  AND COALESCE(r.starttime, r.startdate, r.endtime, r.enddate) IS NOT NULL
            ),
            ranked AS (
                SELECT
                    h.productionhdid,
                    TRIM(CAST(h.prdnmbr AS TEXT)) AS cp_no,
                    UPPER(TRIM(CAST(COALESCE(h.fgstatus, '') AS TEXT))) AS fgstatus,
                    h.prddate,
                    v.verpacking_date,
                    ROW_NUMBER() OVER (
                        PARTITION BY UPPER(TRIM(CAST(h.prdnmbr AS TEXT)))
                        ORDER BY v.verpacking_date DESC NULLS LAST, h.prddate DESC NULLS LAST, h.productionhdid DESC
                    ) AS rn
                FROM pdproductionhd h
                INNER JOIN verpacking v
                    ON v.productionhdid = h.productionhdid
                   AND v.rn = 1
                WHERE TRIM(COALESCE(CAST(h.prdnmbr AS TEXT), '')) <> ''
                  AND DATE(v.verpacking_date) >= :date_from
                  AND DATE(v.verpacking_date) <= :date_to
            )
            SELECT
                productionhdid,
                cp_no,
                fgstatus,
                prddate,
                verpacking_date
            FROM ranked
            WHERE rn = 1
              AND fgstatus = 'X'
            ORDER BY
                verpacking_date ASC NULLS LAST,
                productionhdid ASC
        ";
        $stmtPlanningSource = $conn3->prepare($sqlPlanningSource);
        $stmtPlanningSource->bindValue(':date_from', $dateFrom, PDO::PARAM_STR);
        $stmtPlanningSource->bindValue(':date_to', $dateTo, PDO::PARAM_STR);
        $stmtPlanningSource->execute();

        while ($r = $stmtPlanningSource->fetch(PDO::FETCH_ASSOC)) {
            $cpNo = trim((string)($r['cp_no'] ?? ''));
            if ($cpNo === '' || $isBreakTimeCpNo($cpNo)) {
                continue;
            }

            $cpKey = $normalizeCpKey($cpNo);
            $pid = (int)($r['productionhdid'] ?? 0);
            if ($cpKey === '' || $pid <= 0) {
                continue;
            }

            $prdDateIso = $toIsoDate($r['prddate'] ?? null);
            $verpackingDateIso = $toIsoDate($r['verpacking_date'] ?? null);
            $planningByCp[$cpKey] = [
                'source' => 'pdproductionhd',
                'seq_no' => $pid,
                'tgl_cp' => $prdDateIso,
                'cp_no' => $cpNo,
                'material_name' => '',
                'qty' => 0,
                'label' => '',
                'cust_color' => '',
                'kode_lab' => '',
                'lokasi_paddry' => '',
                'tgl_verpacking' => $verpackingDateIso,
                'rank_date' => $prdDateIso,
                'rank_seq' => $pid,
            ];
        }
    } catch (Throwable $e) {
        $pageWarnings[] = 'Gagal membaca data pdproductionhd: ' . $e->getMessage();
    }
}

// Sumber CP dari pdproductionhd, lalu diperkaya data ERP:
// CP -> pdproductionhd.prdnmbr (fgstatus X) -> routing VERPACKING / PEMARTAIAN / qty / metadata.
if (($conn3 ?? null) instanceof PDO) {
    try {
        $productionByCp = [];
        $productionIds = [];
        $cpKeys = array_values(array_keys($planningByCp));
        if (!empty($cpKeys)) {
            $cpParams = [];
            $cpHolders = [];
            foreach ($cpKeys as $i => $cpKey) {
                $holder = ':cp' . $i;
                $cpHolders[] = $holder;
                $cpParams[$holder] = $cpKey;
            }

            $sqlProduction = "
                WITH ranked AS (
                    SELECT
                        h.productionhdid,
                        TRIM(CAST(h.prdnmbr AS TEXT)) AS cp_no,
                        UPPER(TRIM(CAST(COALESCE(h.fgstatus, '') AS TEXT))) AS fgstatus,
                        h.prddate,
                        ROW_NUMBER() OVER (
                            PARTITION BY UPPER(TRIM(CAST(h.prdnmbr AS TEXT)))
                            ORDER BY h.prddate DESC NULLS LAST, h.productionhdid DESC
                        ) AS rn
                FROM pdproductionhd h
                WHERE UPPER(TRIM(CAST(h.prdnmbr AS TEXT))) IN (" . implode(', ', $cpHolders) . ")
                  AND EXISTS (
                        SELECT 1
                        FROM pdproductionrtg r
                        WHERE r.productionhdid = h.productionhdid
                          AND r.rtgmsid = 692
                          AND COALESCE(r.starttime, r.startdate, r.endtime, r.enddate) IS NOT NULL
                          AND DATE(COALESCE(r.starttime, r.startdate, r.endtime, r.enddate)) >= :date_from
                          AND DATE(COALESCE(r.starttime, r.startdate, r.endtime, r.enddate)) <= :date_to
                  )
                )
                SELECT productionhdid, cp_no, fgstatus, prddate
                FROM ranked
                WHERE rn = 1
                  AND fgstatus = 'X'
            ";
            $stmtProduction = $conn3->prepare($sqlProduction);
            foreach ($cpParams as $holder => $value) {
                $stmtProduction->bindValue($holder, $value, PDO::PARAM_STR);
            }
            $stmtProduction->bindValue(':date_from', $dateFrom, PDO::PARAM_STR);
            $stmtProduction->bindValue(':date_to', $dateTo, PDO::PARAM_STR);
            $stmtProduction->execute();

            while ($prod = $stmtProduction->fetch(PDO::FETCH_ASSOC)) {
                $cpKey = $normalizeCpKey($prod['cp_no'] ?? '');
                $pid = (int)($prod['productionhdid'] ?? 0);
                if ($cpKey === '' || $pid <= 0) {
                    continue;
                }

                $productionByCp[$cpKey] = [
                    'productionhdid' => $pid,
                    'cp_no' => trim((string)($prod['cp_no'] ?? '')),
                    'fgstatus' => strtoupper(trim((string)($prod['fgstatus'] ?? ''))),
                    'prddate' => $prod['prddate'] ?? null,
                ];
                $productionIds[$pid] = $pid;
            }
        }

        if (!empty($productionIds)) {
            $pidParams = [];
            $pidHolders = [];
            foreach (array_values($productionIds) as $i => $pid) {
                $holder = ':pid' . $i;
                $pidHolders[] = $holder;
                $pidParams[$holder] = $pid;
            }

            $buildInPlaceholders = static function (array $values, string $prefix): array {
                $holders = [];
                $params = [];
                $idx = 0;
                foreach ($values as $value) {
                    $holder = ':' . $prefix . $idx;
                    $holders[] = $holder;
                    $params[$holder] = $value;
                    $idx++;
                }
                return [$holders, $params];
            };

            // Samakan sumber lokasi paddry dengan cp_planning.php:
            // ambil routing master dari ms_routing (planning_type paddry), lalu map ke pdproductionrtg+pdrtgms.
            $lokasiPaddryByProduction = [];
            $routingCodes = [];
            $routingCodesNormalized = [];
            $planningTypeCandidates = ['Paddry', 'Planning Paddry', 'Plan Paddry'];
            $typeHolders = implode(', ', array_fill(0, count($planningTypeCandidates), '?'));
            $sqlMasterRouting = "
                SELECT DISTINCT LTRIM(RTRIM(CAST(routing_id AS NVARCHAR(50)))) AS routing_code
                FROM dbo.ms_routing
                WHERE LOWER(LTRIM(RTRIM(ISNULL(planning_type, '')))) IN ($typeHolders)
                  AND LTRIM(RTRIM(ISNULL(CAST(routing_id AS NVARCHAR(50)), ''))) <> ''
                ORDER BY LTRIM(RTRIM(CAST(routing_id AS NVARCHAR(50)))) ASC
            ";
            $stmtMasterRouting = sqlsrv_query(
                $conn,
                $sqlMasterRouting,
                array_map(static fn($v) => strtolower(trim((string)$v)), $planningTypeCandidates)
            );
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

            $erpMetaByProduction = [];
            $hdColsPg = $pgTableColumns($conn3, 'pdproductionhd');
            $techColsPg = $pgTableColumns($conn3, 'smprodtechdata');
            $colorColsPg = $pgTableColumns($conn3, 'pdcolorms');
            $workCenterColsPg = $pgTableColumns($conn3, 'pdworkcenter');
            $productColsPg = $pgTableColumns($conn3, 'smproduct');

            $hdProdIdColPg = $resolveColumn($hdColsPg, ['prodid']);
            $hdColorIdColPg = $resolveColumn($hdColsPg, ['colorid']);
            $hdWorkcenterIdColPg = $resolveColumn($hdColsPg, ['workcenterid']);
            $hdProdNameColPg = $resolveColumn($hdColsPg, ['prodname', 'product_name']);

            $techProdIdColPg = $resolveColumn($techColsPg, ['prodid']);
            $techLabelColPg = $resolveColumn($techColsPg, ['labeljual', 'label_jual']);
            $techCustColorColPg = $resolveColumn($techColsPg, ['cuscolor', 'cust_color', 'customer_color']);

            $colorIdColPg = $resolveColumn($colorColsPg, ['colormsid', 'colorid']);
            $colorCodeColPg = $resolveColumn($colorColsPg, ['colorcode', 'kode_lab']);
            $colorNameColPg = $resolveColumn($colorColsPg, ['colorname', 'color_name']);

            $workCenterIdColPg = $resolveColumn($workCenterColsPg, ['workcenterid']);
            $workCenterCodeColPg = $resolveColumn($workCenterColsPg, ['workcentercode', 'work_center_code']);
            $workCenterNameColPg = $resolveColumn($workCenterColsPg, ['workcentername', 'work_center_name']);

            $productIdColPg = $resolveColumn($productColsPg, ['prodid']);
            $productNameColPg = $resolveColumn($productColsPg, ['prodname', 'product_name']);

            $joinTechSql = ($hdProdIdColPg !== null && $techProdIdColPg !== null && !empty($techColsPg))
                ? "LEFT JOIN smprodtechdata t ON CAST(h." . $pgQuoteIdentifier($hdProdIdColPg) . " AS TEXT) = CAST(t." . $pgQuoteIdentifier($techProdIdColPg) . " AS TEXT)"
                : '';
            $joinColorSql = ($hdColorIdColPg !== null && $colorIdColPg !== null && !empty($colorColsPg))
                ? "LEFT JOIN pdcolorms c ON CAST(h." . $pgQuoteIdentifier($hdColorIdColPg) . " AS TEXT) = CAST(c." . $pgQuoteIdentifier($colorIdColPg) . " AS TEXT)"
                : '';
            $joinWorkCenterSql = ($hdWorkcenterIdColPg !== null && $workCenterIdColPg !== null && !empty($workCenterColsPg))
                ? "LEFT JOIN pdworkcenter w ON CAST(h." . $pgQuoteIdentifier($hdWorkcenterIdColPg) . " AS TEXT) = CAST(w." . $pgQuoteIdentifier($workCenterIdColPg) . " AS TEXT)"
                : '';
            $joinProductSql = ($hdProdIdColPg !== null && $productIdColPg !== null && !empty($productColsPg))
                ? "LEFT JOIN smproduct p ON CAST(h." . $pgQuoteIdentifier($hdProdIdColPg) . " AS TEXT) = CAST(p." . $pgQuoteIdentifier($productIdColPg) . " AS TEXT)"
                : '';

            $exprHdProductName = ($hdProdNameColPg !== null)
                ? "TRIM(CAST(COALESCE(h." . $pgQuoteIdentifier($hdProdNameColPg) . ", '') AS TEXT))"
                : "''";
            $exprTechLabel = ($techLabelColPg !== null && $joinTechSql !== '')
                ? "TRIM(CAST(COALESCE(t." . $pgQuoteIdentifier($techLabelColPg) . ", '') AS TEXT))"
                : "''";
            $exprTechCustColor = ($techCustColorColPg !== null && $joinTechSql !== '')
                ? "TRIM(CAST(COALESCE(t." . $pgQuoteIdentifier($techCustColorColPg) . ", '') AS TEXT))"
                : "''";
            $exprColorCode = ($colorCodeColPg !== null && $joinColorSql !== '')
                ? "TRIM(CAST(COALESCE(c." . $pgQuoteIdentifier($colorCodeColPg) . ", '') AS TEXT))"
                : "''";
            $exprColorName = ($colorNameColPg !== null && $joinColorSql !== '')
                ? "TRIM(CAST(COALESCE(c." . $pgQuoteIdentifier($colorNameColPg) . ", '') AS TEXT))"
                : "''";
            $exprWcCode = ($workCenterCodeColPg !== null && $joinWorkCenterSql !== '')
                ? "TRIM(CAST(COALESCE(w." . $pgQuoteIdentifier($workCenterCodeColPg) . ", '') AS TEXT))"
                : "''";
            $exprWcName = ($workCenterNameColPg !== null && $joinWorkCenterSql !== '')
                ? "TRIM(CAST(COALESCE(w." . $pgQuoteIdentifier($workCenterNameColPg) . ", '') AS TEXT))"
                : "''";
            $exprProductName = ($productNameColPg !== null && $joinProductSql !== '')
                ? "TRIM(CAST(COALESCE(p." . $pgQuoteIdentifier($productNameColPg) . ", '') AS TEXT))"
                : "''";

            $sqlErpMeta = "
                SELECT
                    h.productionhdid,
                    {$exprHdProductName} AS hd_product_name,
                    {$exprTechLabel} AS label_jual,
                    {$exprTechCustColor} AS cust_color,
                    {$exprColorCode} AS kode_lab,
                    {$exprColorName} AS color_name,
                    {$exprWcCode} AS work_center_code,
                    {$exprWcName} AS work_center_name,
                    {$exprProductName} AS product_name
                FROM pdproductionhd h
                {$joinTechSql}
                {$joinColorSql}
                {$joinWorkCenterSql}
                {$joinProductSql}
                WHERE h.productionhdid IN (" . implode(', ', $pidHolders) . ")
            ";
            $stmtErpMeta = $conn3->prepare($sqlErpMeta);
            foreach ($pidParams as $holder => $value) {
                $stmtErpMeta->bindValue($holder, $value, PDO::PARAM_INT);
            }
            $stmtErpMeta->execute();
            while ($meta = $stmtErpMeta->fetch(PDO::FETCH_ASSOC)) {
                $pid = (int)($meta['productionhdid'] ?? 0);
                if ($pid <= 0) {
                    continue;
                }
                $erpMetaByProduction[$pid] = [
                    'hd_product_name' => trim((string)($meta['hd_product_name'] ?? '')),
                    'label_jual' => trim((string)($meta['label_jual'] ?? '')),
                    'cust_color' => trim((string)($meta['cust_color'] ?? '')),
                    'kode_lab' => trim((string)($meta['kode_lab'] ?? '')),
                    'color_name' => trim((string)($meta['color_name'] ?? '')),
                    'work_center_code' => trim((string)($meta['work_center_code'] ?? '')),
                    'work_center_name' => trim((string)($meta['work_center_name'] ?? '')),
                    'product_name' => trim((string)($meta['product_name'] ?? '')),
                ];
            }

            $sqlVerpacking = "
                SELECT
                    r.productionhdid,
                    r.productionrtgid,
                    r.prdqty,
                    r.startdate,
                    r.starttime,
                    r.enddate,
                    r.endtime
                FROM pdproductionrtg r
                WHERE r.productionhdid IN (" . implode(', ', $pidHolders) . ")
                  AND r.rtgmsid = 692
                ORDER BY
                    r.productionhdid ASC,
                    CASE WHEN r.starttime IS NOT NULL THEN 0 ELSE 1 END ASC,
                    COALESCE(r.starttime, r.startdate, r.endtime, r.enddate, r.upddate) DESC NULLS LAST,
                    r.productionrtgid DESC
            ";
            $stmtVerpacking = $conn3->prepare($sqlVerpacking);
            foreach ($pidParams as $holder => $value) {
                $stmtVerpacking->bindValue($holder, $value, PDO::PARAM_INT);
            }
            $stmtVerpacking->execute();

            $verpackingByProduction = [];
            while ($vr = $stmtVerpacking->fetch(PDO::FETCH_ASSOC)) {
                $pid = (int)($vr['productionhdid'] ?? 0);
                if ($pid <= 0) {
                    continue;
                }
                $hasStarttime = $vr['starttime'] !== null && trim((string)$vr['starttime']) !== '';
                $dateRaw = $vr['starttime']
                    ?? ($vr['startdate'] ?? ($vr['endtime'] ?? ($vr['enddate'] ?? null)));

                if (!isset($verpackingByProduction[$pid])) {
                    $verpackingByProduction[$pid] = [
                        'has_route' => true,
                        'has_starttime' => $hasStarttime,
                        'date_raw' => $dateRaw,
                        'prdqty' => $vr['prdqty'] ?? null,
                    ];
                    continue;
                }

                if ($hasStarttime) {
                    $verpackingByProduction[$pid]['has_starttime'] = true;
                }
            }

            $pemartaianByProduction = [];
            $sqlPemartaian = "
                SELECT
                    r.productionhdid,
                    r.productionrtgid,
                    r.starttime
                FROM pdproductionrtg r
                WHERE r.productionhdid IN (" . implode(', ', $pidHolders) . ")
                  AND r.rtgmsid = 409
                  AND r.starttime IS NOT NULL
                ORDER BY
                    r.productionhdid ASC,
                    r.starttime DESC NULLS LAST,
                    r.productionrtgid DESC
            ";
            $stmtPemartaian = $conn3->prepare($sqlPemartaian);
            foreach ($pidParams as $holder => $value) {
                $stmtPemartaian->bindValue($holder, $value, PDO::PARAM_INT);
            }
            $stmtPemartaian->execute();
            while ($pm = $stmtPemartaian->fetch(PDO::FETCH_ASSOC)) {
                $pid = (int)($pm['productionhdid'] ?? 0);
                if ($pid <= 0 || isset($pemartaianByProduction[$pid])) {
                    continue;
                }
                $startRaw = $pm['starttime'] ?? null;
                if ($startRaw === null || trim((string)$startRaw) === '') {
                    continue;
                }
                $pemartaianByProduction[$pid] = $startRaw;
            }

            $celupByProdRtg = [];
            $routingNeedSet = [];
            foreach ($planningByCp as $planningRow) {
                $routingSource = trim((string)($planningRow['lokasi_paddry'] ?? ''));
                if ($routingSource === '') {
                    continue;
                }
                foreach ($extractRoutingParts($routingSource) as $routingKey) {
                    $routingNeedSet[$routingKey] = $routingKey;
                }
            }
            foreach ($lokasiPaddryByProduction as $lokasiText) {
                $routingSource = trim((string)$lokasiText);
                if ($routingSource === '') {
                    continue;
                }
                foreach ($extractRoutingParts($routingSource) as $routingKey) {
                    $routingNeedSet[$routingKey] = $routingKey;
                }
            }

            $routingToRtgms = [];
            if (!empty($routingNeedSet)) {
                [$routingHolders, $routingParams] = $buildInPlaceholders(array_values($routingNeedSet), 'rk_');
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
                while ($rt = $stmtRouting->fetch(PDO::FETCH_ASSOC)) {
                    $routingKey = $normalizeRoutingKey($rt['routing_key'] ?? '');
                    $rtgmsId = (int)($rt['rtgmsid'] ?? 0);
                    if ($routingKey === '' || $rtgmsId <= 0) {
                        continue;
                    }
                    if (!isset($routingToRtgms[$routingKey])) {
                        $routingToRtgms[$routingKey] = [];
                    }
                    $routingToRtgms[$routingKey][$rtgmsId] = $rtgmsId;
                }
            }

            if (!empty($routingToRtgms)) {
                $allRtgmsIds = [];
                foreach ($routingToRtgms as $idMap) {
                    foreach ($idMap as $rid) {
                        $allRtgmsIds[(int)$rid] = (int)$rid;
                    }
                }
                if (!empty($allRtgmsIds)) {
                    [$ridHolders, $ridParams] = $buildInPlaceholders(array_values($allRtgmsIds), 'rid_');
                    $sqlCelup = "
                        SELECT
                            r.productionhdid,
                            CAST(r.rtgmsid AS INTEGER) AS rtgmsid,
                            r.productionrtgid,
                            r.starttime
                        FROM pdproductionrtg r
                        WHERE r.productionhdid IN (" . implode(', ', $pidHolders) . ")
                          AND r.rtgmsid IN (" . implode(', ', $ridHolders) . ")
                          AND r.starttime IS NOT NULL
                        ORDER BY
                            r.productionhdid ASC,
                            r.rtgmsid ASC,
                            r.starttime DESC NULLS LAST,
                            r.productionrtgid DESC
                    ";
                    $stmtCelup = $conn3->prepare($sqlCelup);
                    $paramsCelup = array_merge($pidParams, $ridParams);
                    $stmtCelup->execute($paramsCelup);
                    while ($rc = $stmtCelup->fetch(PDO::FETCH_ASSOC)) {
                        $pid = (int)($rc['productionhdid'] ?? 0);
                        $rid = (int)($rc['rtgmsid'] ?? 0);
                        if ($pid <= 0 || $rid <= 0) {
                            continue;
                        }
                        $startRaw = $rc['starttime'] ?? null;
                        if ($startRaw === null || trim((string)$startRaw) === '') {
                            continue;
                        }
                        $pairKey = $pid . '|' . $rid;
                        if (!isset($celupByProdRtg[$pairKey])) {
                            $celupByProdRtg[$pairKey] = $startRaw;
                        }
                    }
                }
            }

            $qtyByProduction = [];
            $materialByProduction = [];
            foreach ($verpackingByProduction as $pid => $verInfo) {
                $pid = (int)$pid;
                if ($pid <= 0 || !is_array($verInfo)) {
                    continue;
                }

                $prdQtyRaw = $verInfo['prdqty'] ?? null;
                if ($prdQtyRaw === null || $prdQtyRaw === '') {
                    continue;
                }
                $qtyByProduction[$pid] = (float)$prdQtyRaw;
            }

            $sqlResultMat = "
                SELECT
                    r.productionhdid,
                    TRIM(CAST(COALESCE(m.prodname, '') AS TEXT)) AS prodname,
                    m.productionrtgid
                FROM pdresultmat m
                INNER JOIN pdproductionrtg r
                    ON m.productionrtgid = r.productionrtgid
                WHERE r.productionhdid IN (" . implode(', ', $pidHolders) . ")
                  AND m.matseq = 1
                ORDER BY
                    r.productionhdid ASC,
                    m.productionrtgid DESC
            ";
            $stmtResultMat = $conn3->prepare($sqlResultMat);
            foreach ($pidParams as $holder => $value) {
                $stmtResultMat->bindValue($holder, $value, PDO::PARAM_INT);
            }
            $stmtResultMat->execute();
            while ($rm = $stmtResultMat->fetch(PDO::FETCH_ASSOC)) {
                $pid = (int)($rm['productionhdid'] ?? 0);
                if ($pid <= 0) {
                    continue;
                }

                if (!isset($materialByProduction[$pid])) {
                    $materialByProduction[$pid] = trim((string)($rm['prodname'] ?? ''));
                }
            }

            foreach ($planningByCp as $cpKey => $planning) {
                if (!isset($productionByCp[$cpKey])) {
                    continue;
                }
                $prodRow = $productionByCp[$cpKey];
                $pid = (int)($prodRow['productionhdid'] ?? 0);
                if ($pid <= 0) {
                    continue;
                }

                $verInfo = $verpackingByProduction[$pid] ?? null;
                $hasRoute = is_array($verInfo) && !empty($verInfo['has_route']);
                $hasStarttime = is_array($verInfo) && !empty($verInfo['has_starttime']);
                if (!$hasRoute && !$hasStarttime) {
                    continue;
                }

                $meta = $erpMetaByProduction[$pid] ?? [];
                $cpNo = trim((string)($prodRow['cp_no'] ?? ''));
                if ($cpNo === '') {
                    $cpNo = trim((string)($planning['cp_no'] ?? ''));
                }
                if ($cpNo === '' || $isBreakTimeCpNo($cpNo)) {
                    continue;
                }

                $materialPlanning = trim((string)($planning['material_name'] ?? ''));
                $materialErp = trim((string)($materialByProduction[$pid] ?? ''));
                if ($materialErp === '') {
                    $materialErp = trim((string)($meta['product_name'] ?? ''));
                }
                if ($materialErp === '') {
                    $materialErp = trim((string)($meta['hd_product_name'] ?? ''));
                }
                $materialName = $materialPlanning !== '' ? $materialPlanning : $materialErp;

                $labelPlanning = trim((string)($planning['label'] ?? ''));
                $custColorPlanning = trim((string)($planning['cust_color'] ?? ''));
                $kodeLabPlanning = trim((string)($planning['kode_lab'] ?? ''));

                $labelErp = trim((string)($meta['label_jual'] ?? ''));
                $custColorErp = trim((string)($meta['cust_color'] ?? ''));
                $kodeLabErp = trim((string)($meta['kode_lab'] ?? ''));

                $label = $labelPlanning !== '' ? $labelPlanning : $labelErp;
                $custColor = $custColorPlanning !== '' ? $custColorPlanning : $custColorErp;
                $kodeLab = $kodeLabPlanning !== '' ? $kodeLabPlanning : $kodeLabErp;

                $lokasiPaddry = trim((string)($lokasiPaddryByProduction[$pid] ?? ''));
                if ($lokasiPaddry === '') {
                    $lokasiPaddry = trim((string)($planning['lokasi_paddry'] ?? ''));
                }

                $tglCelupIso = '';
                if ($lokasiPaddry !== '' && !empty($routingToRtgms) && !empty($celupByProdRtg)) {
                    // Prioritaskan routing terakhir di string lokasi paddry
                    // (contoh: "..., TOP PADDRY ..."), lalu fallback ke routing sebelumnya.
                    $routingParts = array_reverse($extractRoutingParts($lokasiPaddry));
                    foreach ($routingParts as $routingKey) {
                        if (!isset($routingToRtgms[$routingKey])) {
                            continue;
                        }

                        $bestRaw = null;
                        $bestTs = 0;
                        foreach ($routingToRtgms[$routingKey] as $rid) {
                            $pairKey = $pid . '|' . (int)$rid;
                            if (!isset($celupByProdRtg[$pairKey])) {
                                continue;
                            }
                            $candidateRaw = $celupByProdRtg[$pairKey];
                            $ts = strtotime((string)$candidateRaw);
                            if ($ts === false) {
                                continue;
                            }
                            if ($ts > $bestTs) {
                                $bestTs = $ts;
                                $bestRaw = $candidateRaw;
                            }
                        }

                        if ($bestRaw !== null) {
                            $tglCelupIso = $toIsoDate($bestRaw);
                            break;
                        }
                    }
                }

                $process = $extractProcess($kodeLab);
                if ($process === '') {
                    $process = 'Unknown';
                }

                $tglCpIso = $toIsoDate($prodRow['prddate'] ?? null);
                if ($tglCpIso === '') {
                    continue;
                }

                $tglVerpackingIso = '';
                if (is_array($verInfo)) {
                    $tglVerpackingIso = $toIsoDate($verInfo['date_raw'] ?? null);
                }
                if ($tglVerpackingIso === '') {
                    $tglVerpackingIso = $toIsoDate($planning['tgl_verpacking'] ?? '');
                }
                $tglPemartaianIso = $toIsoDate($pemartaianByProduction[$pid] ?? null);

                $sourceLabel = trim((string)($planning['source'] ?? ''));
                if ($sourceLabel === '') {
                    $sourceLabel = 'ERP';
                }

                $rows[] = [
                    'source' => $sourceLabel,
                    'seq_no' => $planning['seq_no'] ?? null,
                    'tgl_cp' => $tglCpIso,
                    'tgl_pemartaian' => $tglPemartaianIso,
                    'tgl_celup' => $tglCelupIso,
                    'cp_no' => $cpNo,
                    'material_name' => $materialName,
                    'qty' => $qtyByProduction[$pid] ?? ($planning['qty'] ?? 0),
                    'label' => $label,
                    'cust_color' => $custColor,
                    'kode_lab' => $kodeLab,
                    'color_name' => trim((string)($meta['color_name'] ?? '')),
                    'work_center_code' => trim((string)($meta['work_center_code'] ?? '')),
                    'work_center_name' => trim((string)($meta['work_center_name'] ?? '')),
                    'process' => $process,
                    'lokasi_paddry' => $lokasiPaddry,
                    'tgl_verpacking' => $tglVerpackingIso,
                    'fgstatus' => (string)($prodRow['fgstatus'] ?? ''),
                    'has_verpacking_route' => $hasRoute,
                    'has_verpacking_starttime' => $hasStarttime,
                ];
            }
        }
    } catch (Throwable $e) {
        $pageWarnings[] = 'Sumber ERP (pdproductionhd/pdproductionrtg) belum bisa dimuat: ' . $e->getMessage();
    }
}

$rows = array_values(array_filter($rows, static function (array $row) use ($dateFrom, $dateTo): bool {
    $fgstatus = strtoupper(trim((string)($row['fgstatus'] ?? '')));
    $hasVerpackingDate = trim((string)($row['tgl_verpacking'] ?? '')) !== '';
    $tglVerpacking = trim((string)($row['tgl_verpacking'] ?? ''));
    return $fgstatus === 'X'
        && $hasVerpackingDate
        && $tglVerpacking >= $dateFrom
        && $tglVerpacking <= $dateTo;
}));

$rowsForCards = $rows;

$processCards = [];
$allCpSet = [];
$allQtyTotal = 0.0;
foreach ($rowsForCards as $rowCard) {
    $proc = trim((string)($rowCard['process'] ?? ''));
    if ($proc === '') {
        continue;
    }
    if (!isset($processCards[$proc])) {
        $processCards[$proc] = [
            'process' => $proc,
            'cp_total' => 0,
            'qty_total' => 0.0,
            'cp_set' => [],
        ];
    }

    $cpKey = $normalizeCpKey($rowCard['cp_no'] ?? '');
    $qtyNum = $toQtyFloat($rowCard['qty'] ?? 0);
    if ($cpKey !== '') {
        if (!isset($allCpSet[$cpKey])) {
            $allCpSet[$cpKey] = true;
            $allQtyTotal += $qtyNum;
        }
    }

    if ($cpKey !== '' && !isset($processCards[$proc]['cp_set'][$cpKey])) {
        $processCards[$proc]['cp_set'][$cpKey] = true;
        $processCards[$proc]['cp_total']++;
        $processCards[$proc]['qty_total'] += $qtyNum;
    }
}

$processCardRows = array_values($processCards);
usort($processCardRows, static function (array $a, array $b): int {
    return ((int)($a['process'] ?? 0) <=> (int)($b['process'] ?? 0));
});

$validProcessMap = [];
foreach ($processCardRows as $cardRow) {
    $proc = trim((string)($cardRow['process'] ?? ''));
    if ($proc !== '') {
        $validProcessMap[$proc] = true;
    }
}
if ($selectedProcess !== '' && !isset($validProcessMap[$selectedProcess])) {
    $selectedProcess = '';
}

$totalCpAllProcess = count($allCpSet);
$allCpPercent = $totalCpAllProcess > 0 ? 100.0 : 0.0;
$allQtyPercent = $allQtyTotal > 0 ? 100.0 : 0.0;

usort($rows, static function (array $a, array $b): int {
    $cmpDate = strcmp((string)($a['tgl_cp'] ?? ''), (string)($b['tgl_cp'] ?? ''));
    if ($cmpDate !== 0) {
        return $cmpDate;
    }

    $cmpSource = strcasecmp((string)($a['source'] ?? ''), (string)($b['source'] ?? ''));
    if ($cmpSource !== 0) {
        return $cmpSource;
    }

    $seqA = $a['seq_no'] ?? PHP_INT_MAX;
    $seqB = $b['seq_no'] ?? PHP_INT_MAX;
    if ($seqA !== $seqB) {
        return $seqA <=> $seqB;
    }

    return strcasecmp((string)($a['cp_no'] ?? ''), (string)($b['cp_no'] ?? ''));
});

$generatedAt = date('d/m/Y H:i:s');
$exportExcelUrl = '/gg_app/pages/planning/cp_planning/process_report_export.php'
    . '?date_from=' . rawurlencode($dateFrom)
    . '&date_to=' . rawurlencode($dateTo)
    . '&process=' . rawurlencode($selectedProcess);

if ($isProcessReportExportBootstrap) {
    return;
}
?>

<style>
    .pr-filter-label {
        font-size: 0.76rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #64748b;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .pr-meta {
        font-size: 0.78rem;
        color: #64748b;
    }

    .pr-process-card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
        margin-bottom: 14px;
    }

    .pr-process-card {
        border: 1px solid #d8e4f3;
        border-radius: 14px;
        padding: 12px 14px;
        background: linear-gradient(145deg, #ffffff 0%, #f8fbff 58%, #f2f7ff 100%);
        cursor: pointer;
        text-align: left;
        transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease, background 0.2s ease;
        box-shadow: 0 3px 10px rgba(15, 23, 42, 0.07);
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        min-height: 148px;
    }

    .pr-process-card::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 5px;
        background: linear-gradient(180deg, #3b82f6 0%, #1d4ed8 100%);
        opacity: 0.85;
    }

    .pr-process-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(15, 23, 42, 0.12);
        border-color: #b9d0f5;
    }

    .pr-process-card.active {
        border-color: #1d4ed8;
        box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22), 0 10px 22px rgba(30, 64, 175, 0.22);
        background: linear-gradient(145deg, #eef5ff 0%, #e3efff 100%);
    }

    .pr-process-card.pr-process-card--all::before {
        background: linear-gradient(180deg, #16a34a 0%, #0f766e 100%);
    }

    .pr-process-card-title {
        font-size: 0.69rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #475569;
        margin-bottom: 2px;
        font-weight: 700;
        position: relative;
        z-index: 1;
    }

    .pr-process-card-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 8px;
        margin-bottom: 6px;
        position: relative;
        z-index: 1;
    }

    .pr-process-card-chip {
        border: 1px solid #c9d8ef;
        background: #ffffff;
        color: #1e3a5f;
        border-radius: 999px;
        padding: 2px 8px;
        font-size: 0.67rem;
        font-weight: 700;
        line-height: 1.2;
        white-space: nowrap;
    }

    .pr-process-card-value {
        font-size: 1.55rem;
        font-weight: 900;
        color: #0b1f44;
        line-height: 1;
        letter-spacing: 0.01em;
        position: relative;
        z-index: 1;
    }

    .pr-process-card-main-label {
        font-size: 0.7rem;
        color: #4e647f;
        margin-top: 2px;
        font-weight: 600;
        position: relative;
        z-index: 1;
    }

    .pr-process-metrics {
        margin-top: 10px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4px 8px;
        position: relative;
        z-index: 1;
    }

    .pr-process-metric {
        display: flex;
        align-items: baseline;
        justify-content: flex-start;
        gap: 4px;
        font-size: 0.73rem;
        color: #3f526e;
        border-bottom: 1px dashed #dbe7f6;
        padding-bottom: 2px;
    }

    .pr-process-metric-label {
        color: #5d708a;
        font-weight: 600;
    }

    .pr-process-metric-label::after {
        content: ':';
        margin-left: 1px;
    }

    .pr-process-metric-value {
        color: #1c355a;
        font-weight: 700;
    }

    .pr-table-wrap {
        overflow-x: auto;
    }

    .pr-table-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
        flex-wrap: wrap;
    }

    .pr-table-toolbar label {
        margin: 0;
        font-size: 0.8rem;
        color: #334155;
        font-weight: 700;
    }

    .pr-table-search-input {
        min-width: 220px;
        width: 280px;
        max-width: 100%;
    }

    .pr-table-toolbar-left,
    .pr-table-toolbar-right {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex-wrap: nowrap;
    }

    .pr-table-toolbar-right .btn {
        white-space: nowrap;
    }

    @media (max-width: 768px) {
        .pr-table-toolbar-right {
            width: 100%;
        }

        .pr-table-search-input {
            width: 100%;
            min-width: 0;
        }
    }

    .pr-page-length-select {
        width: 78px;
    }

    .pr-table-footer {
        margin-top: 8px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
    }

    .pr-page-info {
        font-size: 0.8rem;
        color: #475569;
    }

    .pr-pagination {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        flex-wrap: wrap;
    }

    .pr-page-btn {
        border: 1px solid #d1d9e6;
        background: #fff;
        color: #334155;
        border-radius: 4px;
        font-size: 0.78rem;
        padding: 4px 8px;
        min-width: 34px;
        text-align: center;
        line-height: 1.2;
        cursor: pointer;
    }

    .pr-page-btn:hover:not(:disabled) {
        border-color: #9fb4d8;
        background: #f8fbff;
    }

    .pr-page-btn.active {
        background: #1e73e8;
        border-color: #1e73e8;
        color: #fff;
        font-weight: 700;
    }

    .pr-page-btn:disabled {
        opacity: 0.55;
        cursor: not-allowed;
        background: #f4f6fa;
    }

    .pr-table th,
    .pr-table td {
        white-space: nowrap;
        vertical-align: middle;
        font-size: 0.82rem;
    }

    .pr-table thead th.pr-sortable {
        cursor: pointer;
        position: relative;
        padding-right: 18px;
        user-select: none;
    }

    .pr-table thead th.pr-sortable::after {
        position: absolute;
        right: 6px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 0.7rem;
        line-height: 1;
        color: #94a3b8;
        font-weight: 700;
        content: "↕";
    }

    .pr-table thead th.pr-sort-asc::after {
        content: "▲";
        color: #334155;
    }

    .pr-table thead th.pr-sort-desc::after {
        content: "▼";
        color: #334155;
    }

    .pr-process-badge {
        display: inline-block;
        min-width: 34px;
        text-align: center;
        border-radius: 999px;
        padding: 2px 8px;
        font-weight: 700;
        border: 1px solid #d5dbe5;
        background: #f8fafc;
        color: #0f172a;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 style="font-size: 1.85rem; margin: 0;">Process Report</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="/gg_app/pages/planning/index.php">Planning</a></li>
                        <li class="breadcrumb-item"><a href="/gg_app/pages/planning/cp_planning/cp_planning.php">CP Planning</a></li>
                        <li class="breadcrumb-item active">Process Report</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?= $esc($themeColor) ?> text-white">
                    <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap:8px;">
                        <h3 class="card-title mb-0" style="font-weight:700;">Process Report Planning</h3>
                        <div class="pr-meta text-white">Generated: <?= $esc($generatedAt) ?></div>
                    </div>
                </div>
                <div class="card-body">
                    <form method="get" class="mb-3">
                        <div class="form-row">
                            <div class="form-group col-md-4 mb-2">
                                <label class="pr-filter-label" for="dateFrom">Tanggal Awal</label>
                                <input type="date" class="form-control form-control-sm" id="dateFrom" name="date_from" value="<?= $esc($dateFrom) ?>">
                            </div>
                            <div class="form-group col-md-4 mb-2">
                                <label class="pr-filter-label" for="dateTo">Tanggal Akhir</label>
                                <input type="date" class="form-control form-control-sm" id="dateTo" name="date_to" value="<?= $esc($dateTo) ?>">
                            </div>
                            <div class="form-group col-md-4 mb-2 d-flex align-items-end">
                                <div class="w-100 d-flex" style="gap:8px;">
                                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                                        <i class="fas fa-filter"></i> Terapkan
                                    </button>
                                    <a href="<?= $esc($exportExcelUrl) ?>" class="btn btn-success btn-sm flex-grow-1 text-center">
                                        <i class="fas fa-file-excel"></i> Export Excel
                                    </a>
                                    <a href="/gg_app/pages/planning/cp_planning/process_report.php" class="btn btn-outline-secondary btn-sm flex-grow-1 text-center">
                                        Reset
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>

                    <?php if ($pageError !== ''): ?>
                        <div class="alert alert-danger py-2"><?= $esc($pageError) ?></div>
                    <?php endif; ?>

                    <?php if (!empty($pageWarnings)): ?>
                        <div class="alert alert-warning py-2">
                            <ul class="mb-0 pl-3">
                                <?php foreach ($pageWarnings as $warning): ?>
                                    <li><?= $esc($warning) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div id="processCardGrid" class="pr-process-card-grid" data-selected-process="<?= $esc($selectedProcess) ?>">
                        <button type="button" class="pr-process-card pr-process-card--all<?= $selectedProcess === '' ? ' active' : '' ?>" data-process="">
                            <div class="pr-process-card-head">
                                <div class="pr-process-card-title">Semua Process</div>
                                <span class="pr-process-card-chip">ALL</span>
                            </div>
                            <div class="pr-process-card-value"><?= $esc((string)$totalCpAllProcess) ?></div>
                            <div class="pr-process-card-main-label">CP Total</div>
                            <div class="pr-process-metrics">
                                <div class="pr-process-metric">
                                    <span class="pr-process-metric-label">Qty Total</span>
                                    <span class="pr-process-metric-value"><?= $esc($formatQty($allQtyTotal)) ?></span>
                                </div>
                                <div class="pr-process-metric">
                                    <span class="pr-process-metric-label">CP %</span>
                                    <span class="pr-process-metric-value"><?= $esc($formatPercent($allCpPercent)) ?></span>
                                </div>
                                <div class="pr-process-metric">
                                    <span class="pr-process-metric-label">Qty %</span>
                                    <span class="pr-process-metric-value"><?= $esc($formatPercent($allQtyPercent)) ?></span>
                                </div>
                            </div>
                        </button>
                        <?php foreach ($processCardRows as $card): ?>
                            <?php
                                $procValue = (string)($card['process'] ?? '');
                                if ($procValue === '') {
                                    continue;
                                }
                                $cpTotal = (int)($card['cp_total'] ?? 0);
                                $qtyTotal = (float)($card['qty_total'] ?? 0);
                                $cpPercent = $totalCpAllProcess > 0 ? (($cpTotal / $totalCpAllProcess) * 100) : 0.0;
                                $qtyPercent = $allQtyTotal > 0 ? (($qtyTotal / $allQtyTotal) * 100) : 0.0;
                                $isActive = ($selectedProcess !== '' && $selectedProcess === $procValue);
                            ?>
                            <button type="button" class="pr-process-card<?= $isActive ? ' active' : '' ?>" data-process="<?= $esc($procValue) ?>">
                                <div class="pr-process-card-head">
                                    <div class="pr-process-card-title">Process <?= $esc($procValue) ?></div>
                                    <span class="pr-process-card-chip">P<?= $esc($procValue) ?></span>
                                </div>
                                <div class="pr-process-card-value"><?= $esc((string)$cpTotal) ?></div>
                                <div class="pr-process-card-main-label">CP Total</div>
                                <div class="pr-process-metrics">
                                    <div class="pr-process-metric">
                                        <span class="pr-process-metric-label">Qty Total</span>
                                        <span class="pr-process-metric-value"><?= $esc($formatQty($qtyTotal)) ?></span>
                                    </div>
                                    <div class="pr-process-metric">
                                        <span class="pr-process-metric-label">CP %</span>
                                        <span class="pr-process-metric-value"><?= $esc($formatPercent($cpPercent)) ?></span>
                                    </div>
                                    <div class="pr-process-metric">
                                        <span class="pr-process-metric-label">Qty %</span>
                                        <span class="pr-process-metric-value"><?= $esc($formatPercent($qtyPercent)) ?></span>
                                    </div>
                                </div>
                            </button>
                        <?php endforeach; ?>
                    </div>



                    <div class="pr-table-toolbar">
                        <div class="pr-table-toolbar-left">
                            <label for="prPageLength">Tampilkan</label>
                            <select id="prPageLength" class="form-control form-control-sm pr-page-length-select">
                                <option value="10" selected>10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            <span class="pr-meta">data per halaman</span>
                        </div>
                        <div class="pr-table-toolbar-right">
                            <label for="prSearchInput">Cari</label>
                            <input
                                type="text"
                                id="prSearchInput"
                                class="form-control form-control-sm pr-table-search-input"
                                placeholder="CP / Label / Cust Color / Kode Lab / Material"
                            >
                            <button type="button" id="prSearchReset" class="btn btn-outline-secondary btn-sm">Reset</button>
                        </div>
                    </div>

                    <div class="pr-table-wrap">
                        <table id="processReportTable" class="table table-bordered table-hover table-sm pr-table mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th class="text-center">No.</th>
                                    <th>CP No</th>
                                    <th class="text-center">Tgl CP</th>
                                    <th class="text-center">Tgl Pemartaian</th>
                                    <th>Material Name</th>
                                    <th class="text-right">Qty</th>
                                    <th>Label</th>
                                    <th>Cust Color</th>
                                    <th>Kode Lab</th>
                                    <th class="text-center">Tgl Celup</th>
                                    <th>Lokasi Paddry</th>
                                    <th class="text-center">Tgl Verpacking</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $idx => $row): ?>
                                    <tr data-process="<?= $esc((string)($row['process'] ?? '')) ?>">
                                        <td class="text-center"><?= $esc((string)($idx + 1)) ?></td>
                                        <td><?= $esc($row['cp_no'] !== '' ? $row['cp_no'] : '-') ?></td>
                                        <td class="text-center"><?= $esc($formatDate($row['tgl_cp'])) ?></td>
                                        <td class="text-center"><?= $esc($formatDate($row['tgl_pemartaian'] ?? '')) ?></td>
                                        <td><?= $esc($row['material_name'] !== '' ? $row['material_name'] : '-') ?></td>
                                        <td class="text-right"><?= $esc($formatQty($row['qty'])) ?></td>
                                        <td><?= $esc($row['label'] !== '' ? $row['label'] : '-') ?></td>
                                        <td><?= $esc($row['cust_color'] !== '' ? $row['cust_color'] : '-') ?></td>
                                        <td><?= $esc($row['kode_lab'] !== '' ? $row['kode_lab'] : '-') ?></td>
                                        <td class="text-center"><?= $esc($formatDate($row['tgl_celup'] ?? '')) ?></td>
                                        <td><?= $esc((string)($row['lokasi_paddry'] ?? '')) ?></td>
                                        <td class="text-center"><?= $esc($formatDate($row['tgl_verpacking'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="pr-table-footer">
                        <div id="prPageInfo" class="pr-page-info">Menampilkan 0 - 0 dari 0 data</div>
                        <div id="prPagination" class="pr-pagination"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var table = document.getElementById('processReportTable');
    if (!table) return;

    var tbody = table.tBodies[0];
    if (!tbody) return;

    var headers = table.tHead ? table.tHead.rows[0].cells : [];
    if (!headers || !headers.length) return;
    var totalRowsEl = document.getElementById('prTotalRows');
    var processCardGrid = document.getElementById('processCardGrid');
    var processCards = processCardGrid ? Array.prototype.slice.call(processCardGrid.querySelectorAll('.pr-process-card')) : [];
    var currentProcessFilter = processCardGrid ? String(processCardGrid.getAttribute('data-selected-process') || '').trim() : '';
    var searchInput = document.getElementById('prSearchInput');
    var searchResetBtn = document.getElementById('prSearchReset');
    var pageLengthSelect = document.getElementById('prPageLength');
    var pageInfoEl = document.getElementById('prPageInfo');
    var paginationEl = document.getElementById('prPagination');
    var currentSearchText = '';
    var currentPage = 1;
    var pageLength = pageLengthSelect ? parseInt(pageLengthSelect.value, 10) : 10;
    if (!Number.isFinite(pageLength) || pageLength <= 0) {
        pageLength = 10;
    }

    function renumberRows(visibleRows, startNumber) {
        for (var i = 0; i < visibleRows.length; i++) {
            if (visibleRows[i].cells.length > 0) {
                visibleRows[i].cells[0].textContent = String(startNumber + i);
            }
        }
    }

    function updateTopTotal(totalFilteredRows) {
        if (totalRowsEl) {
            totalRowsEl.textContent = String(totalFilteredRows);
        }
    }

    function updatePageInfo(totalFilteredRows, startIdx, endIdx) {
        if (!pageInfoEl) return;
        if (totalFilteredRows <= 0) {
            pageInfoEl.textContent = 'Menampilkan 0 - 0 dari 0 data';
            return;
        }
        pageInfoEl.textContent = 'Menampilkan ' + startIdx + ' - ' + endIdx + ' dari ' + totalFilteredRows + ' data';
    }

    function buildPageItems(totalPages, page) {
        var items = [];
        if (totalPages <= 7) {
            for (var i = 1; i <= totalPages; i++) items.push(i);
            return items;
        }

        items.push(1);
        var start = Math.max(2, page - 1);
        var end = Math.min(totalPages - 1, page + 1);
        if (start > 2) items.push('...');
        for (var p = start; p <= end; p++) items.push(p);
        if (end < totalPages - 1) items.push('...');
        items.push(totalPages);
        return items;
    }

    function renderPagination(totalPages) {
        if (!paginationEl) return;
        paginationEl.innerHTML = '';

        if (totalPages <= 1) {
            return;
        }

        var prevBtn = document.createElement('button');
        prevBtn.type = 'button';
        prevBtn.className = 'pr-page-btn';
        prevBtn.textContent = 'Sebelumnya';
        prevBtn.disabled = currentPage <= 1;
        prevBtn.addEventListener('click', function () {
            if (currentPage <= 1) return;
            currentPage--;
            applyProcessFilter();
        });
        paginationEl.appendChild(prevBtn);

        var items = buildPageItems(totalPages, currentPage);
        for (var i = 0; i < items.length; i++) {
            if (items[i] === '...') {
                var dot = document.createElement('span');
                dot.className = 'pr-meta';
                dot.textContent = '...';
                paginationEl.appendChild(dot);
                continue;
            }
            (function (targetPage) {
                var pageBtn = document.createElement('button');
                pageBtn.type = 'button';
                pageBtn.className = 'pr-page-btn' + (targetPage === currentPage ? ' active' : '');
                pageBtn.textContent = String(targetPage);
                pageBtn.addEventListener('click', function () {
                    if (currentPage === targetPage) return;
                    currentPage = targetPage;
                    applyProcessFilter();
                });
                paginationEl.appendChild(pageBtn);
            })(items[i]);
        }

        var nextBtn = document.createElement('button');
        nextBtn.type = 'button';
        nextBtn.className = 'pr-page-btn';
        nextBtn.textContent = 'Selanjutnya';
        nextBtn.disabled = currentPage >= totalPages;
        nextBtn.addEventListener('click', function () {
            if (currentPage >= totalPages) return;
            currentPage++;
            applyProcessFilter();
        });
        paginationEl.appendChild(nextBtn);
    }

    function getMatchedRows() {
        var rows = Array.prototype.slice.call(tbody.rows);
        var needle = currentSearchText.toLowerCase();
        var matched = [];
        for (var i = 0; i < rows.length; i++) {
            var rowProcess = String(rows[i].getAttribute('data-process') || '').trim();
            var matchesProcess = (currentProcessFilter === '' || rowProcess === currentProcessFilter);
            var rowText = rows[i].textContent ? rows[i].textContent.toLowerCase() : '';
            var matchesSearch = (needle === '' || rowText.indexOf(needle) !== -1);
            if (matchesProcess && matchesSearch) {
                matched.push(rows[i]);
            }
        }
        return matched;
    }

    function parseValue(text) {
        var v = String(text || '').trim();
        if (v === '') return { type: 'string', value: '' };

        var dateMatch = v.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
        if (dateMatch) {
            var iso = dateMatch[3] + '-' + dateMatch[2] + '-' + dateMatch[1];
            var t = Date.parse(iso);
            if (!Number.isNaN(t)) return { type: 'number', value: t };
        }

        var normalizedNum = v.replace(/,/g, '');
        if (/^-?\d+(\.\d+)?$/.test(normalizedNum)) {
            return { type: 'number', value: parseFloat(normalizedNum) };
        }

        return { type: 'string', value: v.toLowerCase() };
    }

    function clearSortClass() {
        for (var h = 0; h < headers.length; h++) {
            headers[h].classList.remove('pr-sort-asc', 'pr-sort-desc');
        }
    }

    function setActiveProcessCard() {
        for (var i = 0; i < processCards.length; i++) {
            var cardProc = String(processCards[i].getAttribute('data-process') || '').trim();
            if (cardProc === currentProcessFilter) {
                processCards[i].classList.add('active');
            } else {
                processCards[i].classList.remove('active');
            }
        }
    }

    function applyProcessFilter() {
        var allRows = Array.prototype.slice.call(tbody.rows);
        var matchedRows = getMatchedRows();
        var totalFilteredRows = matchedRows.length;
        var totalPages = Math.max(1, Math.ceil(totalFilteredRows / pageLength));
        if (currentPage > totalPages) {
            currentPage = totalPages;
        }
        if (currentPage < 1) {
            currentPage = 1;
        }

        var startOffset = (currentPage - 1) * pageLength;
        var endOffset = Math.min(startOffset + pageLength, totalFilteredRows);

        for (var r = 0; r < allRows.length; r++) {
            allRows[r].style.display = 'none';
        }

        var visibleRows = matchedRows.slice(startOffset, endOffset);
        for (var v = 0; v < visibleRows.length; v++) {
            visibleRows[v].style.display = '';
        }

        renumberRows(visibleRows, totalFilteredRows > 0 ? (startOffset + 1) : 0);
        updateTopTotal(totalFilteredRows);
        updatePageInfo(totalFilteredRows, totalFilteredRows > 0 ? (startOffset + 1) : 0, endOffset);
        renderPagination(totalPages);
        setActiveProcessCard();
    }

    for (var col = 1; col < headers.length; col++) {
        (function (colIndex) {
            var th = headers[colIndex];
            th.classList.add('pr-sortable');
            th.setAttribute('title', 'Klik untuk urutkan');
            th.dataset.sortDir = 'none';

            th.addEventListener('click', function () {
                var currentDir = th.dataset.sortDir === 'asc' ? 'asc' : (th.dataset.sortDir === 'desc' ? 'desc' : 'none');
                var nextDir = currentDir === 'asc' ? 'desc' : 'asc';

                clearSortClass();
                for (var h = 1; h < headers.length; h++) {
                    headers[h].dataset.sortDir = 'none';
                }

                th.dataset.sortDir = nextDir;
                th.classList.add(nextDir === 'asc' ? 'pr-sort-asc' : 'pr-sort-desc');

                var rows = Array.prototype.slice.call(tbody.rows);
                rows.sort(function (a, b) {
                    var aCell = a.cells[colIndex] ? a.cells[colIndex].textContent : '';
                    var bCell = b.cells[colIndex] ? b.cells[colIndex].textContent : '';
                    var pa = parseValue(aCell);
                    var pb = parseValue(bCell);

                    if (pa.type === 'number' && pb.type === 'number') {
                        return nextDir === 'asc' ? (pa.value - pb.value) : (pb.value - pa.value);
                    }

                    if (pa.value < pb.value) return nextDir === 'asc' ? -1 : 1;
                    if (pa.value > pb.value) return nextDir === 'asc' ? 1 : -1;
                    return 0;
                });

                for (var i = 0; i < rows.length; i++) {
                    tbody.appendChild(rows[i]);
                }
                applyProcessFilter();
            });
        })(col);
    }

    for (var c = 0; c < processCards.length; c++) {
        processCards[c].addEventListener('click', function () {
            currentProcessFilter = String(this.getAttribute('data-process') || '').trim();
            currentPage = 1;
            applyProcessFilter();
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            currentSearchText = String(this.value || '').trim();
            currentPage = 1;
            applyProcessFilter();
        });
    }

    if (searchResetBtn) {
        searchResetBtn.addEventListener('click', function () {
            currentSearchText = '';
            if (searchInput) {
                searchInput.value = '';
                searchInput.focus();
            }
            currentPage = 1;
            applyProcessFilter();
        });
    }

    if (pageLengthSelect) {
        pageLengthSelect.addEventListener('change', function () {
            var nextLen = parseInt(this.value, 10);
            if (!Number.isFinite(nextLen) || nextLen <= 0) {
                nextLen = 10;
            }
            pageLength = nextLen;
            currentPage = 1;
            applyProcessFilter();
        });
    }

    applyProcessFilter();
});
</script>

<?php if (!$isProcessReportExportBootstrap) { include '../../../includes/footer.php'; } ?>
