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
            'qty' => $qty !== null && $qty !== '' ? number_format((float)$qty, 2, '.', ',') : '',
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
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rowAssoc = [];
        foreach ($r as $k => $v) {
            $rowAssoc[strtolower((string)$k)] = $v;
        }

        $qty = $rowValue($rowAssoc, ['qty']);
        $rows[] = [
            '_row_id' => 'dbp_' . (string)($rowValue($rowAssoc, ['id'], '')),
            '_save_state' => 'saved',
            'no_cp' => trim((string)$rowValue($rowAssoc, ['cp_no'], '')),
            'tgl_cp' => $toDateDisplay($rowValue($rowAssoc, ['tgl_cp'], null)),
            'label' => trim((string)$rowValue($rowAssoc, ['label', 'label_jual'], '')),
            'cust_color' => trim((string)$rowValue($rowAssoc, ['cust_color'], '')),
            'kode_lab' => trim((string)$rowValue($rowAssoc, ['kode_lab'], '')),
            'material' => trim((string)$rowValue($rowAssoc, ['material_name', 'material'], '')),
            'qty' => $qty !== null && $qty !== '' ? number_format((float)$qty, 3, '.', ',') : '',
            'posisi_hari_ini' => trim((string)$rowValue($rowAssoc, ['posisi_hari_ini'], '')),
            'speed' => trim((string)$rowValue($rowAssoc, ['speed'], '')),
            'resp_lipat' => trim((string)$rowValue($rowAssoc, ['resep_lipat', 'resp_lipat'], '')),
            'status_resp' => trim((string)$rowValue($rowAssoc, ['status_resep', 'status_resp'], '')),
            'vlot_resp' => trim((string)$rowValue($rowAssoc, ['vlot_resep', 'vlot_resp'], '')),
            'bon_resp' => trim((string)$rowValue($rowAssoc, ['bon_resep', 'bon_resp'], '')),
            'plan_description' => trim((string)$rowValue($rowAssoc, ['ket', 'plan_description'], '')),
            'plan_date' => $toDateDisplay($rowValue($rowAssoc, ['tgl', 'plan_date'], null)),
            'est_tmbng_plrtm_lalab' => $toTimeDisplay($rowValue($rowAssoc, ['est_tmbng_plrtn_lalab', 'est_tmbng_plrtm_lalab'], null)),
            'est_plrtm_prdks' => $toTimeDisplay($rowValue($rowAssoc, ['est_plrtn_prdks', 'est_plrtm_prdks'], null)),
            'plan_start' => $toTimeDisplay($rowValue($rowAssoc, ['rencana_start', 'plan_start'], null)),
            'plan_end' => $toTimeDisplay($rowValue($rowAssoc, ['rencana_finish', 'plan_finish', 'plan_end'], null)),
            'actual_start' => $toTimeDisplay($rowValue($rowAssoc, ['aktual_start', 'actual_start'], null)),
            'actual_end' => $toTimeDisplay($rowValue($rowAssoc, ['aktual_finish', 'actual_finish', 'actual_end'], null)),
            'actual_vlot' => trim((string)$rowValue($rowAssoc, ['vlot_aktual', 'actual_vlot'], '')),
            'sisa_saturator' => trim((string)$rowValue($rowAssoc, ['sisa_larut', 'sisa_saturator'], '')),
            'sample' => trim((string)$rowValue($rowAssoc, ['sample_kain', 'sample'], '')),
            'rko' => $toDateInput($rowValue($rowAssoc, ['rko'], null)),
            'next_routing' => trim((string)$rowValue($rowAssoc, ['next_routing'], '')),
            'last_update' => $toDateTimeText($rowValue($rowAssoc, ['last_update', 'modified_at', 'created_at'], null)),
            'updated_by' => trim((string)$rowValue($rowAssoc, ['update_by', 'updated_by', 'modified_by', 'created_by'], '')),
        ];
    }
    sqlsrv_free_stmt($stmt);

    cpPlannerJsonExit([
        'success' => true,
        'message' => 'OK',
        'data' => $rows,
    ]);
}

if ($action === 'search_cp') {
    header('Content-Type: application/json; charset=utf-8');

    $draw = isset($_POST['draw']) ? (int)$_POST['draw'] : 1;
    $start = isset($_POST['start']) ? (int)$_POST['start'] : 0;
    $length = isset($_POST['length']) ? (int)$_POST['length'] : 25;
    $search = trim((string)($_POST['search']['value'] ?? ''));
    $searchField = trim((string)($_POST['search_field'] ?? ''));

    $filterRouting = trim((string)($_POST['filter_routing'] ?? ''));
    $filterKategori = trim((string)($_POST['filter_kategori'] ?? ''));
    $filterColor = trim((string)($_POST['filter_color'] ?? ''));
    $selectedPlanType = trim((string)($_POST['plan_type'] ?? ''));

    $orderCol = isset($_POST['order'][0]['column']) ? (int)$_POST['order'][0]['column'] : 0;
    $orderDir = (isset($_POST['order'][0]['dir']) && strtolower((string)$_POST['order'][0]['dir']) === 'asc')
        ? 'ASC'
        : 'DESC';

    $colMap = [
        0 => 'no_cp',
        1 => 'tgl_cp',
        2 => 'routing_code',
        3 => 'current_routing',
        4 => 'product_code',
        5 => 'product_name',
        6 => 'posisi_hari_ini_routing',
        7 => 'work_center_code',
        8 => 'work_center_name',
        9 => 'kode_lab',
        10 => 'color_name',
        11 => 'label',
        12 => 'cust_color',
        13 => 'handfeel_code',
        14 => 'handfeel_name',
        15 => 'brand_code',
        16 => 'brand_name',
        17 => 'grade',
        18 => 'product_type',
        19 => 'product_structure_code',
        20 => 'product_structure_name',
    ];
    $orderColName = $colMap[$orderCol] ?? 'no_cp';

    $qi = static function (string $name): string {
        return '"' . str_replace('"', '""', $name) . '"';
    };

    $getTableColumns = static function (PDO $db, string $tableName): array {
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
        'cpb' => 'C',
        'washing padsteam' => 'W',
        'washing and padsteam' => 'W',
        'washing pad steam' => 'W',
        'jet dyeing' => 'J',
    ];

    $planTypeKey = $normalizeText($selectedPlanType);
    $planTypeCode = $planTypeMap[$planTypeKey] ?? null;

    $currUser = $_SESSION['UserName'] ?? '';
    $groupId = (int)($_SESSION['GroupId'] ?? 0);
    $isRestricted = false;
    $allowedRoutings = [];
    $planTypeRoutingCodes = [];
    $hasPlanTypeRoutingFilter = false;

    if ($selectedPlanType !== '') {
        $sqlPlanRouting = "
            SELECT DISTINCT LTRIM(RTRIM(routing_id)) AS routing_id
            FROM dbo.ms_routing
            WHERE LOWER(LTRIM(RTRIM(planning_type))) = LOWER(LTRIM(RTRIM(?)))
              AND LTRIM(RTRIM(ISNULL(routing_id, ''))) <> ''
        ";
        $stmtPlanRouting = sqlsrv_query($conn, $sqlPlanRouting, [$selectedPlanType]);

        if ($stmtPlanRouting) {
            while ($rowPlanRouting = sqlsrv_fetch_array($stmtPlanRouting, SQLSRV_FETCH_ASSOC)) {
                $routingCode = trim((string)($rowPlanRouting['routing_id'] ?? ''));
                if ($routingCode !== '') {
                    $planTypeRoutingCodes[$routingCode] = true;
                }
            }
            sqlsrv_free_stmt($stmtPlanRouting);
        }

        $planTypeRoutingCodes = array_keys($planTypeRoutingCodes);
        $hasPlanTypeRoutingFilter = true;
    }

    if ($groupId !== 1) {
        $sqlFilter = "
            SELECT DISTINCT r.rtg_name
            FROM planning_user_group u
            INNER JOIN planning_group_rtg r ON u.group_name = r.group_name
            WHERE u.username = ?
        ";
        $stmtFilter = sqlsrv_query($conn, $sqlFilter, [$currUser]);
        $hasEntries = false;
        if ($stmtFilter) {
            while ($r = sqlsrv_fetch_array($stmtFilter, SQLSRV_FETCH_ASSOC)) {
                $rtgName = trim((string)($r['rtg_name'] ?? ''));
                if ($rtgName === '') {
                    continue;
                }
                $hasEntries = true;
                $allowedRoutings[] = $rtgName;
            }
            sqlsrv_free_stmt($stmtFilter);
        }

        if (!$hasEntries) {
            echo json_encode([
                'draw' => $draw,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ]);
            exit;
        }
        $isRestricted = true;
    }

    if ($hasPlanTypeRoutingFilter && empty($planTypeRoutingCodes)) {
        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
        ]);
        exit;
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
next_process AS (
    SELECT
        rtg.productionhdid,
        MIN(rtg.rtgseq) AS next_rtgseq
    FROM pdproductionrtg rtg
    INNER JOIN last_process lp
        ON rtg.productionhdid = lp.productionhdid
    WHERE rtg.rtgseq > lp.current_rtgseq
    GROUP BY rtg.productionhdid
),
next_plan_process AS (
    SELECT
        rtg.productionhdid,
        MIN(rtg.rtgseq) AS target_rtgseq
    FROM pdproductionrtg rtg
    INNER JOIN last_process lp
        ON rtg.productionhdid = lp.productionhdid
    INNER JOIN pdcpplanrtg cp
        ON rtg.rtgmsid = cp.rtgmsid
    WHERE
        rtg.rtgseq > lp.current_rtgseq
        AND cp.fgplantype = :plan_type_code
    GROUP BY rtg.productionhdid
),
base AS (
    SELECT
        req.productionhdid,
        req.prdnumber,
        MAX(req.vlot) AS vlot,
        CASE
            WHEN mat.matqty < 500 THEN 'LAB'
            ELSE 'LA'
        END AS lokasi_timbang
    FROM pdbonreq req
    LEFT JOIN pdproductionmat mat
        ON req.productionhdid = mat.productionhdid
    WHERE
        mat.fgusedtype = 'G'
        AND mat.prodstructid = '51385'
        AND req.rtgmsid IN
        ('555','556','559','809','838','842',
         '571','572','573','814','841','844',
         '815','848','849','850')
    GROUP BY
        req.productionhdid,
        req.prdnumber,
        CASE
            WHEN mat.matqty < 500 THEN 'LAB'
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

    $baseSelect = "
SELECT DISTINCT
    h.prdnmbr AS no_cp,
    h.prddate AS tgl_cp,
    r1.rtgcode AS routing_code,
    r1.rtgname AS current_routing,
    r0.rtgname AS previous_routing,
    {$hdProductCodeExpr} AS product_code,
    COALESCE(NULLIF({$hdProductNameExpr}, ''), CAST(rm.prodname AS TEXT), {$labelExpr}, '') AS product_name,
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
    pdcolorms.colorcode AS kode_lab,
    pdcolorms.colorname AS color_name,
    rm.prodname AS material,
    rm.matqty AS qty,
    r2.rtgname AS next_routing,
    r_pos.rtgname AS posisi_hari_ini_routing,
    r_pos2.rtgname AS next_routing_harian,
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
LEFT JOIN pdresultmat rm
    ON h.productionhdid = rm.productionhdid
LEFT JOIN last_process lp
    ON h.productionhdid = lp.productionhdid
LEFT JOIN next_process np
    ON h.productionhdid = np.productionhdid
LEFT JOIN next_plan_process npp
    ON h.productionhdid = npp.productionhdid
LEFT JOIN pdproductionrtg a
    ON a.productionhdid = h.productionhdid
    AND a.rtgseq = COALESCE(npp.target_rtgseq, np.next_rtgseq, lp.current_rtgseq)
LEFT JOIN pdrtgms r1
    ON a.rtgmsid = r1.rtgmsid
LEFT JOIN pdproductionrtg b
    ON b.productionhdid = a.productionhdid
    AND b.rtgseq = a.rtgseq + 1
LEFT JOIN pdrtgms r2
    ON b.rtgmsid = r2.rtgmsid
LEFT JOIN pdproductionrtg pos
    ON pos.productionhdid = h.productionhdid
    AND pos.rtgseq = COALESCE(np.next_rtgseq, lp.current_rtgseq)
LEFT JOIN pdrtgms r_pos
    ON pos.rtgmsid = r_pos.rtgmsid
LEFT JOIN pdproductionrtg pos2
    ON pos2.productionhdid = pos.productionhdid
    AND pos2.rtgseq = pos.rtgseq + 1
LEFT JOIN pdrtgms r_pos2
    ON pos2.rtgmsid = r_pos2.rtgmsid
LEFT JOIN pdproductionrtg p
    ON p.productionhdid = a.productionhdid
    AND p.rtgseq = a.rtgseq - 1
LEFT JOIN pdrtgms r0
    ON p.rtgmsid = r0.rtgmsid
LEFT JOIN pdworkcenter wc
    ON h.workcenterid = wc.workcenterid
LEFT JOIN kategori k
    ON h.prdnmbr = k.prdnumber
WHERE
    h.workcenterid = '111'
    AND h.fgstatus = 'U'
    AND rm.matseq = '1'
";

    $whereExtra = [];
    $params = [':plan_type_code' => $planTypeCode];
    $paramsRestricted = [':plan_type_code' => $planTypeCode];

    $productCodeSearchExpr = $hdProdCodeCol !== null
        ? "CAST(h." . $qi($hdProdCodeCol) . " AS TEXT)"
        : "CAST(h.prodid AS TEXT)";
    $productNameSearchExpr = $hdProdNameCol !== null
        ? "CAST(h." . $qi($hdProdNameCol) . " AS TEXT)"
        : "CAST(rm.prodname AS TEXT)";

    if ($filterRouting !== '') {
        $whereExtra[] = "(CAST(r1.rtgname AS TEXT) ILIKE :filter_routing OR CAST(r2.rtgname AS TEXT) ILIKE :filter_routing)";
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
    if ($search !== '') {
        $searchParts = [];
        $searchFieldMap = [
            'cp_no' => "CAST(h.prdnmbr AS TEXT) ILIKE :search",
            'routing_code' => "CAST(r1.rtgcode AS TEXT) ILIKE :search",
            'routing_name' => "(CAST(r1.rtgname AS TEXT) ILIKE :search OR CAST(r2.rtgname AS TEXT) ILIKE :search)",
            'product_code' => "{$productCodeSearchExpr} ILIKE :search",
            'color_name' => "CAST(pdcolorms.colorname AS TEXT) ILIKE :search",
        ];

        if ($searchField !== '' && isset($searchFieldMap[$searchField])) {
            $searchParts[] = $searchFieldMap[$searchField];
        } else {
            $searchParts = [
                "CAST(h.prdnmbr AS TEXT) ILIKE :search",
                "CAST(r1.rtgcode AS TEXT) ILIKE :search",
                "{$productCodeSearchExpr} ILIKE :search",
                "CAST(pdcolorms.colorcode AS TEXT) ILIKE :search",
                "CAST(pdcolorms.colorname AS TEXT) ILIKE :search",
                "{$productNameSearchExpr} ILIKE :search",
                "CAST(rm.prodname AS TEXT) ILIKE :search",
                "CAST(r1.rtgname AS TEXT) ILIKE :search",
                "CAST(r2.rtgname AS TEXT) ILIKE :search",
            ];

            if ($labelCol !== null) {
                $searchParts[] = "CAST(smprodtechdata." . $qi($labelCol) . " AS TEXT) ILIKE :search";
            }
            if ($custColorCol !== null) {
                $searchParts[] = "CAST(smprodtechdata." . $qi($custColorCol) . " AS TEXT) ILIKE :search";
            }
            if ($handFeelCodeCol !== null) {
                $searchParts[] = "CAST(smprodtechdata." . $qi($handFeelCodeCol) . " AS TEXT) ILIKE :search";
            }
            if ($handFeelNameCol !== null) {
                $searchParts[] = "CAST(smprodtechdata." . $qi($handFeelNameCol) . " AS TEXT) ILIKE :search";
            }
            if ($brandCodeCol !== null) {
                $searchParts[] = "CAST(smprodtechdata." . $qi($brandCodeCol) . " AS TEXT) ILIKE :search";
            }
            if ($brandNameCol !== null) {
                $searchParts[] = "CAST(smprodtechdata." . $qi($brandNameCol) . " AS TEXT) ILIKE :search";
            }
            if ($gradeCol !== null) {
                $searchParts[] = "CAST(smprodtechdata." . $qi($gradeCol) . " AS TEXT) ILIKE :search";
            }
            if ($productTypeCol !== null) {
                $searchParts[] = "CAST(smprodtechdata." . $qi($productTypeCol) . " AS TEXT) ILIKE :search";
            }
            if ($productStructCodeCol !== null) {
                $searchParts[] = "CAST(smprodtechdata." . $qi($productStructCodeCol) . " AS TEXT) ILIKE :search";
            }
            if ($structTableExists && $structNameCol !== null) {
                $searchParts[] = "CAST(smprodstruct." . $qi($structNameCol) . " AS TEXT) ILIKE :search";
            }
        }

        $whereExtra[] = '(' . implode(' OR ', $searchParts) . ')';
        $params[':search'] = '%' . $search . '%';
    }

    if ($isRestricted && !empty($allowedRoutings)) {
        $inPlaceholders = [];
        foreach ($allowedRoutings as $i => $rtg) {
            $key = ':ar_rtg_' . $i;
            $inPlaceholders[] = $key;
            $params[$key] = $rtg;
        }
        $whereExtra[] = "(r2.rtgname IN (" . implode(', ', $inPlaceholders) . "))";
    }

    if ($hasPlanTypeRoutingFilter && !empty($planTypeRoutingCodes)) {
        $inPlaceholders = [];
        foreach ($planTypeRoutingCodes as $i => $code) {
            $key = ':pt_rtg_' . $i;
            $inPlaceholders[] = $key;
            $params[$key] = $code;
        }
        $whereExtra[] = "(TRIM(CAST(r1.rtgcode AS TEXT)) IN (" . implode(', ', $inPlaceholders) . "))";
    }

    $whereRestricted = [];
    if ($isRestricted && !empty($allowedRoutings)) {
        $inPlaceholders = [];
        foreach ($allowedRoutings as $i => $rtg) {
            $key = ':ar_rtg_' . $i;
            $inPlaceholders[] = $key;
            $paramsRestricted[$key] = $rtg;
        }
        $whereRestricted[] = "(r2.rtgname IN (" . implode(', ', $inPlaceholders) . "))";
    }

    if ($hasPlanTypeRoutingFilter && !empty($planTypeRoutingCodes)) {
        $inPlaceholders = [];
        foreach ($planTypeRoutingCodes as $i => $code) {
            $key = ':pt_rtg_' . $i;
            $inPlaceholders[] = $key;
            $paramsRestricted[$key] = $code;
        }
        $whereRestricted[] = "(TRIM(CAST(r1.rtgcode AS TEXT)) IN (" . implode(', ', $inPlaceholders) . "))";
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

    try {
        $countAllSql = $baseCte . "SELECT COUNT(*) FROM (" . $baseSelect . $restrictedClause . ") AS cnt_all";
        $stmtAll = $conn3->prepare($countAllSql);
        $bindParams($stmtAll, $paramsRestricted);
        $stmtAll->execute();
        $totalRecords = (int)$stmtAll->fetchColumn();

        $countFilteredSql = $baseCte . "SELECT COUNT(*) FROM (" . $baseSelect . $extraClause . ") AS cnt_filtered";
        $stmtFiltered = $conn3->prepare($countFilteredSql);
        $bindParams($stmtFiltered, $params);
        $stmtFiltered->execute();
        $totalFiltered = (int)$stmtFiltered->fetchColumn();

        $dataSql = $baseCte
            . "SELECT * FROM (" . $baseSelect . $extraClause . ") AS data_main"
            . " ORDER BY " . $orderColName . " " . $orderDir
            . " LIMIT :limit OFFSET :offset";
        $stmtData = $conn3->prepare($dataSql);
        $bindParams($stmtData, $params);
        $stmtData->bindValue(':limit', $length, PDO::PARAM_INT);
        $stmtData->bindValue(':offset', $start, PDO::PARAM_INT);
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
            $qty = $r['qty'] !== null ? number_format((float)$r['qty'], 2, '.', ',') : '-';

            $kat = (string)($r['kategori_penimbangan'] ?? '-');
            if ($kat === 'LAB') {
                $badge = '<span class="badge badge-pill badge-lab">LAB</span>';
            } elseif ($kat === 'LA') {
                $badge = '<span class="badge badge-pill badge-la">LA</span>';
            } elseif ($kat === 'MIX') {
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
            ];
        }

        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalFiltered,
            'data' => $data,
        ]);
    } catch (Throwable $e) {
        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
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

    .cp-period-text {
        font-size: 0.9rem;
        color: #111827;
        margin-top: 6px;
    }

    .cp-layout {
        border: 1px solid #dee2e6;
        border-radius: 5px;
        overflow: hidden;
        min-height: 440px;
    }

    .cp-machine-panel {
        border-right: 1px solid #dee2e6;
        background: #fbfcfe;
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
    }

    .cp-action-buttons .btn {
        width: 30px;
        height: 30px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .cp-action-buttons .btn + .btn {
        margin-left: 4px;
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
    }

    .cp-table tbody td {
        border: 1px solid #dee2e6;
        padding: 6px 8px;
        white-space: nowrap;
    }

    .cp-table tbody tr.row-pending td {
        background: #fffbe8;
    }

    .cp-table tbody tr.row-selected td {
        background: #dbeafe;
    }

    .cp-table tbody tr.row-pending.row-selected td {
        background: #fde68a;
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

    .cp-table .cp-cell-input.plan-desc {
        min-width: 130px;
    }

    .cp-table .text-left {
        text-align: left;
    }

    :root {
        --cp-sticky-seq-width: 62px;
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

    #cpMainTable tbody tr.row-selected td:nth-child(1),
    #cpMainTable tbody tr.row-selected td:nth-child(2) {
        background: #dbeafe;
    }

    #cpMainTable tbody tr.row-pending.row-selected td:nth-child(1),
    #cpMainTable tbody tr.row-pending.row-selected td:nth-child(2) {
        background: #fde68a;
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
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 8px;
        flex-wrap: wrap;
    }

    .cp-search-toolbar-left,
    .cp-search-toolbar-right {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
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
    }

    #cpSearchRoutingFilter {
        min-width: 210px;
    }

    .cp-search-table-wrap {
        border: 1px solid #cfd4da;
        background: #ffffff;
    }

    .cp-search-table-wrap .table {
        margin-bottom: 0;
    }

    #cpSearchTable {
        min-width: 2550px;
    }

    #cpSearchTable .cp-select-col {
        width: 42px !important;
        min-width: 42px;
        max-width: 42px;
        text-align: center;
    }

    #cpSearchTable .cp-row-check {
        pointer-events: none;
    }

    #cpSearchTable thead th {
        background: #edf1f5;
        border-color: #cfd4da;
        font-size: 0.78rem;
        color: #0f172a;
        white-space: nowrap;
        padding: 7px 8px;
    }

    #cpSearchTable tbody td {
        border-color: #d9dde2;
        font-size: 0.79rem;
        white-space: nowrap;
        padding: 6px 8px;
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
        box-sizing: border-box;
    }

    .cp-search-modal .dataTables_scrollBody td {
        border-color: #d9dde2;
        font-size: 0.79rem;
        white-space: nowrap !important;
        line-height: 1.25;
        padding: 6px 8px !important;
        vertical-align: middle;
        box-sizing: border-box;
    }

    .cp-search-modal .dataTables_scrollHead table,
    .cp-search-modal .dataTables_scrollBody table {
        border-collapse: separate !important;
        border-spacing: 0;
        table-layout: fixed !important;
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

        .cp-search-toolbar-left,
        .cp-search-toolbar-right {
            width: 100%;
        }

        .cp-search-select,
        .cp-search-input,
        #cpSearchRoutingFilter {
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
                    <h3 class="cp-card-title">CP Planning</h3>
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
                                <div id="selectedMachineTitle">Machine: -</div>
                                <div class="cp-action-buttons" role="group" aria-label="CP Planning Actions">
                                    <button type="button" id="btnNew" class="btn btn-outline-secondary btn-sm" title="New">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button type="button" id="btnEdit" class="btn btn-outline-secondary btn-sm" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" id="btnDelete" class="btn btn-outline-secondary btn-sm" title="Delet">
                                        <i class="fas fa-trash"></i>
                                    </button>
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
                                <div class="table-responsive">
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

<div class="modal fade cp-search-modal" id="cpSearchModal" tabindex="-1" role="dialog" aria-labelledby="cpSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl cp-search-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header cp-search-header">
                <h5 class="modal-title" id="cpSearchModalLabel">Search CP and Routing</h5>
                <button type="button" class="close cp-search-close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="cp-search-toolbar">
                    <div class="cp-search-toolbar-left">
                        <label for="cpSearchField" class="cp-search-inline-label">Search For</label>
                        <select id="cpSearchField" class="form-control form-control-sm cp-search-select">
                            <option value="cp_no">CP No</option>
                            <option value="routing_code">Routing Code</option>
                            <option value="routing_name">Routing Name</option>
                            <option value="product_code">Product Code</option>
                            <option value="color_name">Color Name</option>
                        </select>
                        <input type="text" id="cpSearchKeyword" class="form-control form-control-sm cp-search-input" placeholder="Ketik keyword pencarian">
                    </div>
                    <div class="cp-search-toolbar-right">
                        <label for="cpSearchRoutingFilter" class="cp-search-inline-label">Routing</label>
                        <input type="text" id="cpSearchRoutingFilter" class="form-control form-control-sm" placeholder="Filter routing...">
                        <button type="button" id="btnCpSearchApply" class="btn btn-outline-secondary btn-sm">Search</button>
                        <button type="button" id="btnCpSearchReset" class="btn btn-outline-secondary btn-sm">Reset</button>
                    </div>
                </div>
                <div class="cp-search-table-wrap">
                    <div class="table-responsive">
                        <table id="cpSearchTable" class="table table-bordered table-sm w-100 mb-0">
                            <thead>
                                <tr>
                                    <th class="cp-select-col"></th>
                                    <th>CP No</th>
                                    <th>CP Date</th>
                                    <th>Routing Code</th>
                                    <th>Routing Name</th>
                                    <th>Product Code</th>
                                    <th>Product Name</th>
                                    <th>Posisi Hari Ini</th>
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
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnSubmitCpSearch" class="btn btn-outline-primary btn-sm px-4">Submit</button>
                <button type="button" class="btn btn-outline-danger btn-sm px-4" data-dismiss="modal">Cancel</button>
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
    var rowSequence = 0;
    var orderDirty = false;
    var orderSnapshotRowIds = [];

    function safeText(value) {
        return value == null ? '' : String(value).trim();
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
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
        if (normalized.indexOf(',') !== -1 && normalized.indexOf('.') !== -1) {
            normalized = normalized.replace(/,/g, '');
        } else if (normalized.indexOf(',') !== -1) {
            normalized = normalized.replace(/,/g, '.');
        }
        var parsed = parseFloat(normalized);
        return Number.isFinite(parsed) ? parsed : NaN;
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

    function applyPaddryPlanEndForRow(row, startOverride, speedOverride) {
        if (!row || safeText(row._save_state) !== 'pending') {
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

    function recalculatePaddryPlanSchedule() {
        if (getMainTableLayoutKey() !== 'paddry') {
            return;
        }

        var previousFinish = '';
        mainTableRows.forEach(function (row, idx) {
            if (!row || typeof row !== 'object') {
                return;
            }

            var isPending = safeText(row._save_state) === 'pending';
            if (idx > 0 && isPending && safeText(previousFinish) !== '') {
                var chainedStart = addMinutesToTime(previousFinish, 60);
                if (chainedStart !== '') {
                    row.plan_start = chainedStart;
                }
            }

            if (isPending) {
                applyPaddryPlanEndForRow(row);
                row.est_tmbng_plrtm_lalab = addMinutesToTime(row.plan_start, -420);
                row.est_plrtm_prdks = addMinutesToTime(row.plan_start, -300);
            }

            previousFinish = safeText(row.plan_end);
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
        var match = raw.match(/^dbp?_(\d+)$/i);
        if (!match) {
            return 0;
        }
        var num = parseInt(match[1], 10);
        return Number.isFinite(num) ? num : 0;
    }

    function hasPendingRows() {
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

    function applyMainRowSelection() {
        var hasSelected = false;
        $('#cpTableBody tr[data-row-id]').removeClass('row-selected').each(function () {
            var rowId = safeText($(this).attr('data-row-id'));
            if (rowId && rowId === safeText(selectedMainRowId)) {
                $(this).addClass('row-selected');
                hasSelected = true;
            }
        });
        $('#cpTableBody .main-row-checkbox').prop('checked', false);
        if (hasSelected && selectedMainRowId) {
            $('#cpTableBody .main-row-checkbox[data-row-id="' + selectedMainRowId.replace(/"/g, '\\"') + '"]').prop('checked', true);
        }

        if (!hasSelected) {
            selectedMainRowId = '';
        }
    }

    function setSelectedMainRow(rowId) {
        selectedMainRowId = safeText(rowId);
        applyMainRowSelection();
    }

    function updateMainRowsFromInputs() {
        var layoutKey = getMainTableLayoutKey();
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
                target[field] = safeText($(this).val());
            });
        });

        if (layoutKey === 'paddry') {
            recalculatePaddryPlanSchedule();
            syncPaddryPlanTimeInputs();
        }
    }

    function makeTextInput(row, field, extraClass, placeholder, inputType) {
        var isSavedRow = safeText(row && row._save_state) === 'saved';
        var isBreakTimeRow = Boolean(row && (row.is_break_time === true || safeText(row.is_break_time) === '1'));
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
        if (key === 'paddry') {
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
            return 23;
        }
        if (layoutKey === 'presett') {
            return 21;
        }
        if (layoutKey === 'paddry') {
            return 28;
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
                    '<th rowspan="2">Routing Name</th>' +
                    '<th rowspan="2">Qty</th>' +
                    '<th rowspan="2">Material Name</th>' +
                    '<th rowspan="2">Grammature</th>' +
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

        if (getMainTableLayoutKey() === 'presett') {
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
                    '<th colspan="4">Actual</th>' +
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
                    '<th>Wheel No</th>' +
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
                    '<th rowspan="2">Qty</th>' +
                    '<th rowspan="2">Posisi Hari Ini</th>' +
                    '<th rowspan="2">Speed</th>' +
                    '<th rowspan="2">Resep Lipat</th>' +
                    '<th rowspan="2">Status Resep</th>' +
                    '<th rowspan="2">Vlot Resep</th>' +
                    '<th rowspan="2">Bon Resep</th>' +
                    '<th rowspan="2">Ket</th>' +
                    '<th rowspan="2">Tgl</th>' +
                    '<th rowspan="2">Est Tmbng Plrtm LA/LAB</th>' +
                    '<th rowspan="2">Est Plrtm Prdks</th>' +
                    '<th colspan="2">Rencana Celup</th>' +
                    '<th colspan="2">Aktual Celup</th>' +
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
            $tbody.append('<tr><td colspan="5" class="text-center text-muted">Belum ada data break time.</td></tr>');
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
                '<td>' + formatDateTime(row.upddate) + '</td>' +
                '<td>' + safeText(row.upduser) + '</td>' +
                '</tr>';
            $tbody.append(html);
        });

        $('#breakTimeCount').text(filtered.length + ' data');
        applyBreakSelectionToTable();
    }

    function loadBreakTimeList() {
        $('#breakTimeTable tbody').html('<tr><td colspan="5" class="text-center text-muted">Memuat data...</td></tr>');
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
            return;
        }

        var machineCode = safeText(row.facode) || '-';
        var machineName = safeText(row.faname) || '-';

        $('#selectedMachineTitle').text('Machine: ' + machineName + ' (' + machineCode + ')');
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
            $('#selectedPlanType').val('');
            machineRows = [];
            renderMainTableHeader();
            renderMainTable(mainTableRows);
            renderMachineList();
            showAlert('warning', 'Tipe planning tidak valid.');
            return $.Deferred().reject().promise();
        }

        selectedPlanType = canonical;
        $('#selectedPlanType').val(selectedPlanType);
        renderPlanTypeList();
        renderMainTableHeader();
        renderMainTable(mainTableRows);

        hideAlert();
        $('#machineList').html('<div class="text-muted small p-3">Memuat machine...</div>');

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

        if ((getMainTableLayoutKey() === 'bakar_bulu' || getMainTableLayoutKey() === 'paddry') && hasPendingRows()) {
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
            if (!breakName) {
                return;
            }

            mainTableRows.push({
                _row_id: nextRowId(),
                _save_state: 'pending',
                is_break_time: true,
                no_cp: breakName,
                tgl_cp: '',
                routing_code: '',
                routing_name: '',
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
                plan_machine: '',
                plan_date: '',
                plan_start: '00:00',
                plan_end: '00:00',
                rko: '',
                resp_lipat: '',
                status_resp: '',
                vlot_resp: '',
                bon_resp: '',
                plan_description: '',
                actual_date: '',
                actual_start: '',
                actual_end: '',
                actual_shift: '',
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
            routing: safeText($('#cpSearchRoutingFilter').val()),
            planType: safeText(selectedPlanType),
            machineId: safeText(selectedMachineId)
        };
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
        var $container = $(cpSearchTable.table().container());
        var $headTable = $container.find('.dataTables_scrollHead table');
        var $bodyTable = $container.find('.dataTables_scrollBody table');
        if (!$headTable.length || !$bodyTable.length) {
            return;
        }

        var bodyWidth = $bodyTable.outerWidth();
        if (bodyWidth && bodyWidth > 0) {
            $headTable.css('width', bodyWidth + 'px');
        }
    }

    function openCpSearchModal() {
        $('#cpSearchModal').modal('show');
        clearCpSelection();

        if (cpSearchTable) {
            reloadCpSearchTable(false);
            return;
        }

        cpSearchTable = $('#cpSearchTable').DataTable({
            processing: true,
            serverSide: true,
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
                    d.search = d.search || {};
                    d.search.value = filters.keyword;
                    d.search_field = filters.searchField;
                    d.filter_routing = filters.routing;
                    d.plan_type = filters.planType;
                    d.machine_id = filters.machineId;
                    d.filter_kategori = '';
                    d.filter_color = '';
                }
            },
            columns: [
                {
                    data: null,
                    className: 'text-center cp-select-col',
                    width: '34px',
                    orderable: false,
                    searchable: false,
                    defaultContent: '<input type="checkbox" class="cp-row-check" tabindex="-1">'
                },
                { data: 0, width: '160px' },
                { data: 1, width: '94px' },
                { data: 2, className: 'text-center', width: '96px' },
                { data: 3, width: '210px' },
                { data: 4, className: 'text-center', width: '110px' },
                { data: 5, width: '220px' },
                { data: 6, width: '210px' },
                { data: 7, className: 'text-center', width: '108px' },
                { data: 8, width: '140px' },
                { data: 9, className: 'text-center', width: '120px' },
                { data: 10, width: '130px' },
                { data: 11, width: '120px' },
                { data: 12, width: '120px' },
                { data: 13, width: '130px' },
                { data: 14, width: '130px' },
                { data: 15, width: '120px' },
                { data: 16, width: '160px' },
                { data: 17, className: 'text-center', width: '80px' },
                { data: 18, width: '130px' },
                { data: 19, width: '170px' },
                { data: 20, width: '190px' }
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

    $('#btnCpSearchApply').on('click', function () {
        reloadCpSearchTable(true);
    });

    $('#btnCpSearchReset').on('click', function () {
        $('#cpSearchField').val('cp_no');
        $('#cpSearchKeyword').val('');
        $('#cpSearchRoutingFilter').val('');
        reloadCpSearchTable(true);
    });

    $('#cpSearchField').on('change', function () {
        debounceCpSearchReload();
    });

    $('#cpSearchKeyword, #cpSearchRoutingFilter').on('input', function () {
        debounceCpSearchReload();
    });

    $('#cpSearchKeyword, #cpSearchRoutingFilter').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(cpSearchDebounceTimer);
            reloadCpSearchTable(true);
        }
    });

    $('#cpSearchModal').on('shown.bs.modal', function () {
        if (cpSearchTable) {
            cpSearchTable.columns.adjust();
            syncCpSearchTableLayout();
        }
    });

    $(window).on('resize', function () {
        if (!cpSearchTable || !$('#cpSearchModal').hasClass('show')) {
            return;
        }
        cpSearchTable.columns.adjust();
        syncCpSearchTableLayout();
    });

    $('#cpSearchModal').on('hidden.bs.modal', function () {
        clearTimeout(cpSearchDebounceTimer);
        clearCpSelection();
    });

    $('#btnNew').on('click', function () {
        openCpSearchModal();
    });

    $('#btnEdit').on('click', function () {
        updateMainRowsFromInputs();

        var targetRowId = safeText(selectedMainRowId);
        if (!targetRowId) {
            showAlert('warning', 'Pilih data CP yang ingin diedit terlebih dahulu.');
            return;
        }

        var target = null;
        for (var i = 0; i < mainTableRows.length; i++) {
            if (safeText(mainTableRows[i]._row_id) === targetRowId) {
                target = mainTableRows[i];
                break;
            }
        }

        if (!target) {
            showAlert('warning', 'Data terpilih tidak ditemukan.');
            return;
        }

        if (safeText(target._save_state) === 'saved') {
            target._original_data = $.extend(true, {}, target);
            target._edited_from_saved = true;
            target._save_state = 'pending';
            renderMainTable(mainTableRows);
            setSelectedMainRow(targetRowId);
            showAlert('info', 'Mode edit aktif untuk data terpilih. Silakan ubah data lalu klik checklist untuk simpan.');
            return;
        }

        if (safeText(target._save_state) === 'pending') {
            showAlert('info', 'Data terpilih sudah dalam mode edit.');
            return;
        }

        showAlert('warning', 'Data ini tidak dapat diedit.');
    });

    $('#cpTableBody').on('click', 'tr[data-row-id]', function () {
        var rowId = safeText($(this).attr('data-row-id'));
        if (!rowId) {
            return;
        }
        setSelectedMainRow(rowId);
    });

    $('#cpTableBody').on('input change', 'input[data-field="plan_start"], input[data-field="speed"]', function () {
        if (getMainTableLayoutKey() !== 'paddry') {
            return;
        }
        updateMainRowsFromInputs();
    });

    $('#cpTableBody').on('click', '.main-row-checkbox', function (e) {
        e.stopPropagation();
    });

    $('#cpTableBody').on('change', '.main-row-checkbox', function (e) {
        e.stopPropagation();
        var rowId = safeText($(this).attr('data-row-id'));
        if (!rowId) {
            return;
        }
        if ($(this).is(':checked')) {
            setSelectedMainRow(rowId);
        } else if (safeText(selectedMainRowId) === rowId) {
            selectedMainRowId = '';
            applyMainRowSelection();
        }
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
            recalculatePaddryPlanSchedule();
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

    $('#btnDelete').on('click', function () {
        updateMainRowsFromInputs();

        var targetRowId = safeText(selectedMainRowId);
        if (!targetRowId) {
            showAlert('warning', 'Pilih data CP yang ingin dihapus terlebih dahulu.');
            return;
        }

        var beforeRows = mainTableRows.slice();
        var deletedRow = null;
        mainTableRows = mainTableRows.filter(function (row) {
            if (safeText(row._row_id) === targetRowId) {
                deletedRow = row;
                return false;
            }
            return true;
        });

        if (!deletedRow) {
            selectedMainRowId = '';
            applyMainRowSelection();
            showAlert('warning', 'Data terpilih tidak ditemukan.');
            return;
        }

        selectedMainRowId = '';
        renderMainTable(mainTableRows);

        var layoutKey = getMainTableLayoutKey();
        var isPersistentLayout = (layoutKey === 'bakar_bulu' || layoutKey === 'paddry');
        if (!isPersistentLayout) {
            showAlert('success', 'Data CP terpilih berhasil dihapus.');
            return;
        }

        if (safeText(deletedRow._save_state) !== 'saved') {
            showAlert('success', 'Data CP terpilih berhasil dihapus.');
            return;
        }

        var periodIso = safeText($('#periodDate').val());
        var machineId = safeText(selectedMachineId);
        if (!machineId || !periodIso) {
            showAlert('warning', 'Data terhapus di tabel, namun belum bisa simpan permanen karena machine/periode belum valid.');
            return;
        }

        var dbId = getPersistedDbIdFromRowId(deletedRow._row_id);
        if (dbId <= 0) {
            showAlert('success', 'Data CP terpilih berhasil dihapus.');
            return;
        }

        var actionName = layoutKey === 'bakar_bulu' ? 'delete_bakar_bulu_row' : 'delete_paddry_row';
        var failText = layoutKey === 'bakar_bulu'
            ? 'Gagal menghapus data bakar bulu.'
            : 'Gagal menghapus data paddry.';

        var $btnDelete = $('#btnDelete');
        $btnDelete.prop('disabled', true);

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
                showAlert('danger', (res && res.message) ? res.message : failText);
                mainTableRows = beforeRows;
                renderMainTable(mainTableRows);
                return;
            }

            showAlert('success', 'Data CP terpilih berhasil dihapus permanen.');
        }).fail(function () {
            showAlert('danger', failText);
            mainTableRows = beforeRows;
            renderMainTable(mainTableRows);
        }).always(function () {
            $btnDelete.prop('disabled', false);
        });
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
            togglePendingActionButtons();
            return;
        }

        rows.forEach(function (row, idx) {
            var posisiHariIni = row.posisi_hari_ini || row.routing_name || '-';
            var nextRouting = row.next_routing || '-';
            var rowId = row._row_id || '';
            var rowClasses = [];
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
                    '<td>' + nextRouting + '</td>' +
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
                    '<td>' + (row.routing_name || '-') + '</td>' +
                    '<td class="text-right">' + (row.qty || '-') + '</td>' +
                    '<td>' + (row.material || '-') + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'grammature', 'grammature text-right', '0.0000') + '</td>' +
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
                    '<td>' + nextRouting + '</td>' +
                    '<td>' + (row.last_update || '-') + '</td>' +
                    '<td>' + (row.updated_by || '-') + '</td>' +
                    '</tr>';
            } else if (isPreSettLayout) {
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
                    '<td>' + makeTextInput(row, 'actual_wheel_no', 'actual-wheel-no', '') + '</td>' +
                    '<td>' + posisiHariIni + '</td>' +
                    '<td>' + nextRouting + '</td>' +
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
                html = '<tr data-row-id="' + escapeHtml(rowId) + '"' + rowClass + '>' +
                    '<td class="text-center">' + makeRowSelectCheckbox(rowId) + (idx + 1) + '</td>' +
                    '<td>' + (row.no_cp || '-') + '</td>' +
                    '<td>' + (row.label || '-') + '</td>' +
                    '<td>' + (row.cust_color || '-') + '</td>' +
                    '<td>' + (row.kode_lab || '-') + '</td>' +
                    '<td>' + (row.material || '-') + '</td>' +
                    '<td class="text-right">' + (row.qty || '-') + '</td>' +
                    '<td>' + posisiHariIni + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'speed', 'speed-input text-right', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'resp_lipat', 'plan-resp-lipat', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'status_resp', 'plan-status-resp', '') + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'vlot_resp', 'plan-vlot-resp text-right', '0.0000') + '</td>' +
                    '<td>' + makeTextInput(row, 'bon_resp', 'plan-bon-resp', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_description', 'plan-desc', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_date', 'plan-date', 'dd/mm/yyyy') + '</td>' +
                    '<td>' + makeTextInput(row, 'est_tmbng_plrtm_lalab', 'est-tmbng-plrtm-lalab', '00:00', 'time') + '</td>' +
                    '<td>' + makeTextInput(row, 'est_plrtm_prdks', 'est-plrtm-prdks', '00:00', 'time') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_start', 'plan-start', '00:00', 'time') + '</td>' +
                    '<td>' + makeTextInput(row, 'plan_end', 'plan-end', '00:00', 'time') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_start', 'actual-start', '00:00', 'time') + '</td>' +
                    '<td>' + makeTextInput(row, 'actual_end', 'actual-end', '00:00', 'time') + '</td>' +
                    '<td class="text-right">' + makeTextInput(row, 'actual_vlot', 'actual-vlot text-right', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'sisa_saturator', 'actual-sisa-saturator', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'sample', 'sample', '') + '</td>' +
                    '<td>' + makeTextInput(row, 'rko', 'plan-rko', '', 'date') + '</td>' +
                    '<td>' + nextRouting + '</td>' +
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

        applyMainRowSelection();
        togglePendingActionButtons();
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

        if (hasPendingRows()) {
            showAlert('warning', 'Masih ada data sementara. Klik checklist atau silang terlebih dahulu.');
            return;
        }

        updateMainRowsFromInputs();

        var periodIso = safeText($('#periodDate').val());
        var periodDisplay = formatDateDisplayFromIso(periodIso) || formatTodayDisplay();
        var isPaddrySelectedLayout = getMainTableLayoutKey() === 'paddry';
        var selectedMachine = getSelectedMachineRow();
        var selectedMachineLabel = '';
        if (selectedMachine) {
            selectedMachineLabel = safeText(selectedMachine.faname) || safeText(selectedMachine.facode);
        }
        selectedRows.forEach(function (rowData) {
            var mapped = {
                _row_id: nextRowId(),
                _save_state: 'pending',
                no_cp: rowData[0],
                tgl_cp: rowData[1],
                routing_code: rowData[2],
                routing_name: rowData[3],
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
                plan_machine: selectedMachineLabel,
                plan_date: periodDisplay,
                plan_start: '00:00',
                plan_end: '00:00',
                rko: '',
                resp_lipat: '',
                status_resp: '',
                vlot_resp: rowData[24],
                bon_resp: '',
                plan_description: '',
                actual_date: '',
                actual_start: '',
                actual_end: '',
                actual_shift: '',
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
                kategori: rowData[25]
            };
            mainTableRows.push(mapped);
        });

        if (isPaddrySelectedLayout) {
            recalculatePaddryPlanSchedule();
        }
        renderMainTable(mainTableRows);
        $('#cpSearchModal').modal('hide');
        hideAlert();
    });

    $('#btnCommitPending').on('click', function () {
        if (!hasPendingRows()) {
            return;
        }

        updateMainRowsFromInputs();

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
        }
    });

    renderMainTableHeader();
    renderMainTable(mainTableRows);
    loadPlanTypes();
    renderMachineList();
    updatePeriodText();
});
</script>
