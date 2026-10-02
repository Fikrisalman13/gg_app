<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized',
    ]);
    exit;
}

include '../../../koneksi.php';
include '../../../koneksi3.php';

$defaultPlanTypes = [
    'Bakar Bulu',
    'Scouring',
    'PreSett',
    'Paddry',
    'CPB',
    'Washing & PadSteam',
    'Jet Dyeing',
    'Mikwang',
];

function responseJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function sqlsrvErrorText(): string
{
    $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
    if (!is_array($errors)) {
        return 'Unknown SQL Server error.';
    }

    $parts = [];
    foreach ($errors as $err) {
        $state = $err['SQLSTATE'] ?? '';
        $code = $err['code'] ?? '';
        $message = $err['message'] ?? '';
        $parts[] = trim("[{$state}] ({$code}) {$message}");
    }

    return implode(' | ', $parts);
}

function sqlsrvExec($conn, string $sql, array $params = [])
{
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        throw new RuntimeException(sqlsrvErrorText());
    }
    return $stmt;
}

function escapeSqlName(string $name): string
{
    return '[' . str_replace(']', ']]', $name) . ']';
}

function sqlsrvTableExists($conn, string $tableName, string $schemaName = 'dbo'): bool
{
    $stmt = sqlsrvExec(
        $conn,
        "
            SELECT TOP 1 1 AS exists_flag
            FROM sys.tables t
            INNER JOIN sys.schemas s ON t.schema_id = s.schema_id
            WHERE t.name = ?
              AND s.name = ?
        ",
        [$tableName, $schemaName]
    );
    $exists = (bool)sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $exists;
}

function sqlsrvColumnExists($conn, string $tableName, string $columnName, string $schemaName = 'dbo'): bool
{
    $stmt = sqlsrvExec(
        $conn,
        "
            SELECT TOP 1 1 AS exists_flag
            FROM sys.columns c
            INNER JOIN sys.tables t ON c.object_id = t.object_id
            INNER JOIN sys.schemas s ON t.schema_id = s.schema_id
            WHERE t.name = ?
              AND s.name = ?
              AND c.name = ?
        ",
        [$tableName, $schemaName, $columnName]
    );
    $exists = (bool)sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $exists;
}

function ensureBreakTimeMinutesColumn($conn): void
{
    if (!sqlsrvTableExists($conn, 'ms_break_time')) {
        return;
    }

    if (sqlsrvColumnExists($conn, 'ms_break_time', 'break_time_minutes')) {
        return;
    }

    $sql = "
        ALTER TABLE dbo.ms_break_time
        ADD break_time_minutes INT NOT NULL
            CONSTRAINT DF_ms_break_time_break_time_minutes DEFAULT (0)
    ";
    $stmt = sqlsrvExec($conn, $sql);
    sqlsrv_free_stmt($stmt);
}

function findRelatedRowsByColumn($conn, string $columnName, array $values, array $excludeTables = []): array
{
    $values = array_values(array_filter($values, function ($v) {
        return $v !== null && trim((string)$v) !== '';
    }));
    if (empty($values)) {
        return [];
    }

    $excludeMap = [];
    foreach ($excludeTables as $tbl) {
        $excludeMap[strtolower($tbl)] = true;
    }

    $tables = [];
    $stmt = sqlsrvExec(
        $conn,
        "
            SELECT s.name AS schema_name, t.name AS table_name
            FROM sys.columns c
            INNER JOIN sys.tables t ON c.object_id = t.object_id
            INNER JOIN sys.schemas s ON t.schema_id = s.schema_id
            WHERE c.name = ?
              AND t.is_ms_shipped = 0
        ",
        [$columnName]
    );
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $schema = (string)($row['schema_name'] ?? '');
        $table = (string)($row['table_name'] ?? '');
        if ($schema === '' || $table === '') {
            continue;
        }
        $fullName = strtolower($schema . '.' . $table);
        if (isset($excludeMap[$fullName])) {
            continue;
        }
        $tables[] = [
            'schema' => $schema,
            'table' => $table,
        ];
    }
    sqlsrv_free_stmt($stmt);

    if (empty($tables)) {
        return [];
    }

    $related = [];
    $columnEsc = escapeSqlName($columnName);
    $holders = implode(',', array_fill(0, count($values), '?'));

    foreach ($tables as $tbl) {
        $tableName = escapeSqlName($tbl['schema']) . '.' . escapeSqlName($tbl['table']);
        $sql = "SELECT COUNT(1) AS total FROM {$tableName} WHERE {$columnEsc} IN ({$holders})";
        $stmtCount = sqlsrvExec($conn, $sql, $values);
        $row = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtCount);
        $count = (int)($row['total'] ?? 0);
        if ($count > 0) {
            $related[$tbl['schema'] . '.' . $tbl['table']] = $count;
        }
    }

    return $related;
}

function getAuditUser(): string
{
    $user = trim((string)($_SESSION['UserName'] ?? 'SYSTEM'));
    if ($user === '') {
        $user = 'SYSTEM';
    }
    return substr($user, 0, 50);
}

function normalizePlanTypes(array $types): array
{
    $clean = [];
    $seen = [];

    foreach ($types as $type) {
        $val = trim((string)$type);
        if ($val === '') {
            continue;
        }

        $key = strtolower($val);
        if (isset($seen[$key])) {
            continue;
        }

        $clean[] = substr($val, 0, 100);
        $seen[$key] = true;
    }

    return $clean;
}

function sqlsrvDateToString($value): string
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d H:i:s');
    }

    if (is_string($value) && trim($value) !== '') {
        return trim($value);
    }

    return '';
}

function normalizeYn($value): string
{
    return strtoupper((string)$value) === 'Y' ? 'Y' : 'N';
}

function ensureMaxProductionCapacityTable($conn): void
{
    $sql = "
        IF OBJECT_ID(N'dbo.ms_max_production_capacity', N'U') IS NULL
        BEGIN
            CREATE TABLE dbo.ms_max_production_capacity
            (
                id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                planning_type NVARCHAR(100) NOT NULL,
                machine_id NVARCHAR(20) NOT NULL,
                max_capacity_day DECIMAL(18,3) NOT NULL CONSTRAINT DF_ms_max_prod_capacity_value DEFAULT (0),
                creat_by NVARCHAR(50) NULL,
                creat_at DATETIME2(0) NULL CONSTRAINT DF_ms_max_prod_capacity_creat_at DEFAULT (SYSDATETIME()),
                update_by NVARCHAR(50) NULL,
                update_at DATETIME2(0) NULL CONSTRAINT DF_ms_max_prod_capacity_update_at DEFAULT (SYSDATETIME())
            );

            CREATE UNIQUE NONCLUSTERED INDEX UX_ms_max_prod_capacity_plan_machine
                ON dbo.ms_max_production_capacity (planning_type, machine_id);
        END
    ";

    $stmt = sqlsrvExec($conn, $sql);
    sqlsrv_free_stmt($stmt);
}

function normalizeCapacityPerDay($value): ?float
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return null;
    }

    // Untuk input seperti 54.000 / 54,000 / 54000
    $digits = preg_replace('/[^0-9]/', '', $raw);
    if (!is_string($digits) || $digits === '') {
        return null;
    }

    return (float)$digits;
}

function resolvePlanType(string $input, array $planTypes): ?string
{
    foreach ($planTypes as $type) {
        if (strcasecmp($type, $input) === 0) {
            return $type;
        }
    }

    return null;
}

function countMasterPlanTypes($conn): int
{
    $stmt = sqlsrvExec($conn, "SELECT COUNT(1) AS total FROM dbo.ms_tipe_planning");
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return (int)($row['total'] ?? 0);
}

function findMasterPlanTypeByName($conn, string $name): ?array
{
    $stmt = sqlsrvExec(
        $conn,
        "
            SELECT TOP 1
                tipe_planning_id,
                tipe_planning_name
            FROM dbo.ms_tipe_planning
            WHERE LOWER(LTRIM(RTRIM(tipe_planning_name))) = LOWER(LTRIM(RTRIM(?)))
            ORDER BY tipe_planning_id ASC
        ",
        [$name]
    );
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);

    if (!$row) {
        return null;
    }

    return [
        'tipe_planning_id' => (int)($row['tipe_planning_id'] ?? 0),
        'tipe_planning_name' => trim((string)($row['tipe_planning_name'] ?? '')),
    ];
}

function insertMasterPlanTypeIfMissing($conn, string $name, string $user): bool
{
    $name = substr(trim($name), 0, 100);
    if ($name === '') {
        return false;
    }

    $existing = findMasterPlanTypeByName($conn, $name);
    if ($existing !== null) {
        return false;
    }

    $stmt = sqlsrvExec(
        $conn,
        "
            INSERT INTO dbo.ms_tipe_planning
            (
                tipe_planning_name,
                creat_at,
                creat_by,
                update_at,
                update_by
            )
            VALUES (?, SYSDATETIME(), ?, SYSDATETIME(), ?)
        ",
        [$name, $user, $user]
    );
    sqlsrv_free_stmt($stmt);

    return true;
}

function syncMasterPlanTypesFromRouting($conn, string $user): void
{
    $stmt = sqlsrvExec(
        $conn,
        "
            SELECT DISTINCT planning_type
            FROM dbo.ms_routing
            WHERE planning_type IS NOT NULL
              AND LTRIM(RTRIM(planning_type)) <> ''
            ORDER BY planning_type ASC
        "
    );

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $type = trim((string)($row['planning_type'] ?? ''));
        if ($type === '') {
            continue;
        }
        insertMasterPlanTypeIfMissing($conn, $type, $user);
    }

    sqlsrv_free_stmt($stmt);
}

function ensureMasterPlanTypeInitialized($conn, array $defaultPlanTypes, string $user): void
{
    if (countMasterPlanTypes($conn) <= 0) {
        foreach (normalizePlanTypes($defaultPlanTypes) as $type) {
            insertMasterPlanTypeIfMissing($conn, $type, $user);
        }
    }

    syncMasterPlanTypesFromRouting($conn, $user);
}

function fetchMasterPlanTypeRows($conn): array
{
    $stmt = sqlsrvExec(
        $conn,
        "
            SELECT
                tipe_planning_id,
                tipe_planning_name
            FROM dbo.ms_tipe_planning
            ORDER BY tipe_planning_id ASC
        "
    );

    $rows = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = [
            'tipe_planning_id' => (int)($row['tipe_planning_id'] ?? 0),
            'tipe_planning_name' => trim((string)($row['tipe_planning_name'] ?? '')),
        ];
    }

    sqlsrv_free_stmt($stmt);
    return $rows;
}

function fetchMasterPlanTypeNames($conn): array
{
    return array_map(fn ($r) => $r['tipe_planning_name'], fetchMasterPlanTypeRows($conn));
}

function fetchRoutingCountsByPlanType($conn): array
{
    $sql = "
        SELECT planning_type, COUNT(1) AS total
        FROM dbo.ms_routing
        GROUP BY planning_type
    ";

    $stmt = sqlsrvExec($conn, $sql);
    $counts = [];

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $type = trim((string)($row['planning_type'] ?? ''));
        if ($type === '') {
            continue;
        }

        $key = strtolower($type);
        if (!isset($counts[$key])) {
            $counts[$key] = [
                'plan_type' => $type,
                'route_count' => 0,
            ];
        }
        $counts[$key]['route_count'] += (int)($row['total'] ?? 0);
    }

    sqlsrv_free_stmt($stmt);
    return $counts;
}

function fetchRoutingDetails(PDO $conn3, array $rtgmsIds): array
{
    $rtgmsIds = array_values(array_unique(array_map('intval', $rtgmsIds)));
    $rtgmsIds = array_values(array_filter($rtgmsIds, fn ($v) => $v > 0));

    if (empty($rtgmsIds)) {
        return [];
    }

    $params = [];
    $holders = [];
    foreach ($rtgmsIds as $i => $id) {
        $key = ':id' . $i;
        $holders[] = $key;
        $params[$key] = $id;
    }
    $inClause = implode(',', $holders);

    $sql = "
        SELECT
            m.rtgmsid,
            m.rtgcode,
            m.rtgname,
            m.fgmachine,
            m.upddate,
            m.upduser
        FROM pdrtgms m
        WHERE m.rtgmsid IN ($inClause)
    ";

    $stmt = $conn3->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function searchRouting(PDO $conn3, string $searchBy, string $keyword, int $limit = 500): array
{
    $searchBy = ($searchBy === 'rtgname') ? 'rtgname' : 'rtgcode';
    $limit = max(1, min(1000, $limit));
    $params = [];

    $where = "WHERE COALESCE(m.updflag, 'Y') = 'Y'";
    if ($keyword !== '') {
        if ($searchBy === 'rtgname') {
            $where .= " AND m.rtgname ILIKE :kw";
        } else {
            $where .= " AND m.rtgcode ILIKE :kw";
        }
        $params[':kw'] = '%' . $keyword . '%';
    }

    $sql = "
        SELECT
            m.rtgmsid,
            m.rtgcode,
            m.rtgname,
            m.fgmachine
        FROM pdrtgms m
        $where
        ORDER BY m.rtgcode ASC
        LIMIT $limit
    ";

    $stmt = $conn3->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchMachineDetails(PDO $conn3, array $famasterIds): array
{
    $famasterIds = array_values(array_unique(array_map('intval', $famasterIds)));
    $famasterIds = array_values(array_filter($famasterIds, fn ($v) => $v > 0));

    if (empty($famasterIds)) {
        return [];
    }

    $params = [];
    $holders = [];
    foreach ($famasterIds as $i => $id) {
        $key = ':id' . $i;
        $holders[] = $key;
        $params[$key] = $id;
    }
    $inClause = implode(',', $holders);

    $sql = "
        SELECT
            m.famasterid,
            m.facode,
            m.faname,
            m.faalias
        FROM famaster m
        WHERE m.famasterid IN ($inClause)
          AND UPPER(COALESCE(m.facode, '')) LIKE 'FAM%'
    ";

    $stmt = $conn3->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function searchMachine(PDO $conn3, string $searchBy, string $keyword, int $limit = 500): array
{
    $searchBy = ($searchBy === 'faname') ? 'faname' : 'facode';
    $limit = max(1, min(1000, $limit));
    $params = [];

    $where = "WHERE UPPER(COALESCE(m.facode, '')) LIKE 'FAM%'";
    if ($keyword !== '') {
        if ($searchBy === 'faname') {
            $where .= " AND COALESCE(m.faname, '') ILIKE :kw";
        } else {
            $where .= " AND COALESCE(m.facode, '') ILIKE :kw";
        }
        $params[':kw'] = '%' . $keyword . '%';
    }

    $sql = "
        SELECT
            m.famasterid,
            m.facode,
            m.faname,
            m.faalias
        FROM famaster m
        $where
        ORDER BY m.facode ASC
        LIMIT $limit
    ";

    $stmt = $conn3->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$action = $_REQUEST['action'] ?? '';
$userLogin = getAuditUser();

try {
    $needsMasterPlanType = in_array($action, [
        'plan_type_list',
        'plan_type_add',
        'plan_type_delete',
        'list',
        'add',
        'update',
        'machine_list',
        'machine_add',
        'machine_update',
        'max_capacity_list',
        'max_capacity_add',
        'max_capacity_update',
        'max_capacity_delete',
    ], true);

    if ($needsMasterPlanType) {
        ensureMasterPlanTypeInitialized($conn, $defaultPlanTypes, $userLogin);
    }

    if (in_array($action, ['break_list', 'break_add', 'break_update'], true)) {
        ensureBreakTimeMinutesColumn($conn);
    }

    if ($action === 'plan_type_list') {
        $masterRows = fetchMasterPlanTypeRows($conn);
        $dbCounts = fetchRoutingCountsByPlanType($conn);

        $data = [];
        foreach ($masterRows as $row) {
            $type = $row['tipe_planning_name'];
            $key = strtolower($type);
            $data[] = [
                'tipe_planning_id' => (int)$row['tipe_planning_id'],
                'plan_type' => $type,
                'route_count' => (int)($dbCounts[$key]['route_count'] ?? 0),
            ];
        }

        responseJson([
            'success' => true,
            'data' => $data,
        ]);
    }

    if ($action === 'plan_type_add') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responseJson([
                'success' => false,
                'message' => 'Method tidak didukung.',
            ], 405);
        }

        $newType = substr(trim((string)($_POST['plan_type'] ?? '')), 0, 100);
        if ($newType === '') {
            responseJson([
                'success' => false,
                'message' => 'Nama tipe planning wajib diisi.',
            ], 422);
        }

        if (findMasterPlanTypeByName($conn, $newType) !== null) {
            responseJson([
                'success' => false,
                'message' => 'Tipe planning sudah ada.',
            ], 422);
        }

        insertMasterPlanTypeIfMissing($conn, $newType, $userLogin);

        responseJson([
            'success' => true,
            'message' => 'Tipe planning berhasil ditambahkan.',
        ]);
    }

    if ($action === 'plan_type_delete') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responseJson([
                'success' => false,
                'message' => 'Method tidak didukung.',
            ], 405);
        }

        $inputType = trim((string)($_POST['plan_type'] ?? ''));
        if ($inputType === '') {
            responseJson([
                'success' => false,
                'message' => 'Tipe planning tidak valid.',
            ], 422);
        }

        $master = findMasterPlanTypeByName($conn, $inputType);
        if ($master === null) {
            responseJson([
                'success' => false,
                'message' => 'Tipe planning tidak ditemukan.',
            ], 404);
        }

        if (countMasterPlanTypes($conn) <= 1) {
            responseJson([
                'success' => false,
                'message' => 'Minimal harus ada satu tipe planning.',
            ], 422);
        }

        $stmtCheckRouting = sqlsrvExec(
            $conn,
            "
                SELECT COUNT(1) AS total
                FROM dbo.ms_routing
                WHERE LOWER(LTRIM(RTRIM(planning_type))) = LOWER(LTRIM(RTRIM(?)))
            ",
            [$master['tipe_planning_name']]
        );
        $rowCheckRouting = sqlsrv_fetch_array($stmtCheckRouting, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtCheckRouting);
        $routingCount = (int)($rowCheckRouting['total'] ?? 0);

        $stmtCheckMachine = sqlsrvExec(
            $conn,
            "
                SELECT COUNT(1) AS total
                FROM dbo.ms_machine
                WHERE LOWER(LTRIM(RTRIM(planning_type))) = LOWER(LTRIM(RTRIM(?)))
            ",
            [$master['tipe_planning_name']]
        );
        $rowCheckMachine = sqlsrv_fetch_array($stmtCheckMachine, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtCheckMachine);
        $machineCount = (int)($rowCheckMachine['total'] ?? 0);

        $relatedOther = findRelatedRowsByColumn(
            $conn,
            'planning_type',
            [$master['tipe_planning_name']],
            ['dbo.ms_tipe_planning', 'dbo.ms_routing', 'dbo.ms_machine']
        );

        if ($routingCount > 0 || $machineCount > 0 || !empty($relatedOther)) {
            $parts = [];
            if ($routingCount > 0) {
                $parts[] = "Routing ({$routingCount})";
            }
            if ($machineCount > 0) {
                $parts[] = "Machine ({$machineCount})";
            }
            foreach ($relatedOther as $tbl => $cnt) {
                $parts[] = "{$tbl} ({$cnt})";
            }
            $detail = implode(', ', $parts);

            responseJson([
                'success' => false,
                'message' => 'Gagal menghapus karena master terkait dengan data. Relasi: ' . $detail,
            ], 422);
        }

        if (!sqlsrv_begin_transaction($conn)) {
            throw new RuntimeException(sqlsrvErrorText());
        }

        try {
            $stmtDeleteRouting = sqlsrvExec(
                $conn,
                "
                    DELETE FROM dbo.ms_routing
                    WHERE LOWER(LTRIM(RTRIM(planning_type))) = LOWER(LTRIM(RTRIM(?)))
                ",
                [$master['tipe_planning_name']]
            );
            $removedMappings = sqlsrv_rows_affected($stmtDeleteRouting);
            sqlsrv_free_stmt($stmtDeleteRouting);

            $stmtDeleteMachine = sqlsrvExec(
                $conn,
                "
                    DELETE FROM dbo.ms_machine
                    WHERE LOWER(LTRIM(RTRIM(planning_type))) = LOWER(LTRIM(RTRIM(?)))
                ",
                [$master['tipe_planning_name']]
            );
            $removedMachineMappings = sqlsrv_rows_affected($stmtDeleteMachine);
            sqlsrv_free_stmt($stmtDeleteMachine);

            $stmtDeleteMaster = sqlsrvExec(
                $conn,
                "DELETE FROM dbo.ms_tipe_planning WHERE tipe_planning_id = ?",
                [(int)$master['tipe_planning_id']]
            );
            sqlsrv_free_stmt($stmtDeleteMaster);

            if (!sqlsrv_commit($conn)) {
                throw new RuntimeException(sqlsrvErrorText());
            }

            if ($removedMappings === false || $removedMappings < 0) {
                $removedMappings = 0;
            }
            if ($removedMachineMappings === false || $removedMachineMappings < 0) {
                $removedMachineMappings = 0;
            }
            $removedTotal = (int)$removedMappings + (int)$removedMachineMappings;

            responseJson([
                'success' => true,
                'message' => 'Tipe planning berhasil dihapus.',
                'removed_mappings' => $removedTotal,
            ]);
        } catch (Throwable $inner) {
            sqlsrv_rollback($conn);
            throw $inner;
        }
    }

    if ($action === 'list') {
        $planTypes = fetchMasterPlanTypeNames($conn);
        $planType = trim((string)($_GET['plan_type'] ?? ''));
        if ($planType !== '') {
            $canonicalType = resolvePlanType($planType, $planTypes);
            if ($canonicalType === null) {
                responseJson([
                    'success' => false,
                    'message' => 'Tipe planning tidak valid.',
                ], 422);
            }
            $planType = $canonicalType;
        }

        $sql = "
            SELECT
                routing_id,
                planning_type,
                routing_name,
                use_mechine,
                creat_by,
                creat_at,
                update_by,
                update_at
            FROM dbo.ms_routing
        ";
        $params = [];
        if ($planType !== '') {
            $sql .= " WHERE planning_type = ?";
            $params[] = $planType;
        }
        $sql .= " ORDER BY planning_type ASC, routing_id ASC";

        $stmt = sqlsrvExec($conn, $sql, $params);
        $rows = [];

        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $lastUpdate = $row['update_at'] ?? null;
            if ($lastUpdate === null) {
                $lastUpdate = $row['creat_at'] ?? null;
            }

            $updatedBy = trim((string)($row['update_by'] ?? ''));
            if ($updatedBy === '') {
                $updatedBy = trim((string)($row['creat_by'] ?? ''));
            }

            $routingId = trim((string)($row['routing_id'] ?? ''));
            $rows[] = [
                'id' => $routingId,
                'plan_type' => (string)($row['planning_type'] ?? ''),
                'rtgmsid' => 0,
                'rtgcode' => $routingId,
                'rtgname' => (string)($row['routing_name'] ?? ''),
                'fgmachine' => normalizeYn($row['use_mechine'] ?? 'N'),
                'upddate' => sqlsrvDateToString($lastUpdate),
                'upduser' => $updatedBy,
            ];
        }
        sqlsrv_free_stmt($stmt);

        responseJson([
            'success' => true,
            'data' => $rows,
        ]);
    }

    if ($action === 'search') {
        $searchBy = trim((string)($_GET['search_by'] ?? 'rtgcode'));
        $keyword = trim((string)($_GET['keyword'] ?? ''));
        $rows = searchRouting($conn3, $searchBy, $keyword, 500);

        $mapped = array_map(function ($row) {
            return [
                'rtgmsid' => (int)($row['rtgmsid'] ?? 0),
                'rtgcode' => (string)($row['rtgcode'] ?? ''),
                'rtgname' => (string)($row['rtgname'] ?? ''),
                'fgmachine' => normalizeYn($row['fgmachine'] ?? 'N'),
            ];
        }, $rows);

        responseJson([
            'success' => true,
            'data' => $mapped,
        ]);
    }

    if ($action === 'add') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responseJson([
                'success' => false,
                'message' => 'Method tidak didukung.',
            ], 405);
        }

        $planTypes = fetchMasterPlanTypeNames($conn);
        $inputType = trim((string)($_POST['plan_type'] ?? ''));
        $planType = resolvePlanType($inputType, $planTypes);
        if ($planType === null) {
            responseJson([
                'success' => false,
                'message' => 'Tipe planning tidak valid.',
            ], 422);
        }

        $rtgmsids = $_POST['rtgmsids'] ?? [];
        if (!is_array($rtgmsids)) {
            $rtgmsids = [$rtgmsids];
        }

        $rtgmsids = array_values(array_unique(array_map('intval', $rtgmsids)));
        $rtgmsids = array_values(array_filter($rtgmsids, fn ($v) => $v > 0));

        if (empty($rtgmsids)) {
            responseJson([
                'success' => false,
                'message' => 'Pilih routing terlebih dahulu.',
            ], 422);
        }

        $validRoutings = fetchRoutingDetails($conn3, $rtgmsids);
        if (empty($validRoutings)) {
            responseJson([
                'success' => false,
                'message' => 'Routing tidak ditemukan di pdrtgms.',
            ], 422);
        }

        $payloadByCode = [];
        $skippedLength = 0;
        foreach ($validRoutings as $routing) {
            $routingId = trim((string)($routing['rtgcode'] ?? ''));
            if ($routingId === '') {
                continue;
            }

            if (strlen($routingId) > 20) {
                $skippedLength++;
                continue;
            }

            $codeKey = strtolower($routingId);
            $payloadByCode[$codeKey] = [
                'routing_id' => $routingId,
                'planning_type' => $planType,
                'routing_name' => substr(trim((string)($routing['rtgname'] ?? '')), 0, 200),
                'use_mechine' => normalizeYn($routing['fgmachine'] ?? 'N'),
            ];
        }

        if (empty($payloadByCode)) {
            responseJson([
                'success' => false,
                'message' => 'Routing tidak valid untuk disimpan.',
            ], 422);
        }

        if (!sqlsrv_begin_transaction($conn)) {
            throw new RuntimeException(sqlsrvErrorText());
        }

        try {
            $inserted = 0;
            $updated = 0;

            foreach ($payloadByCode as $item) {
                $routingId = $item['routing_id'];

                $stmtCheck = sqlsrvExec(
                    $conn,
                    "SELECT routing_id FROM dbo.ms_routing WHERE routing_id = ?",
                    [$routingId]
                );
                $exists = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
                sqlsrv_free_stmt($stmtCheck);

                if ($exists) {
                    $stmtUpdate = sqlsrvExec(
                        $conn,
                        "
                            UPDATE dbo.ms_routing
                            SET planning_type = ?,
                                routing_name = ?,
                                use_mechine = ?,
                                update_by = ?,
                                update_at = SYSDATETIME()
                            WHERE routing_id = ?
                        ",
                        [
                            $item['planning_type'],
                            $item['routing_name'],
                            $item['use_mechine'],
                            $userLogin,
                            $routingId,
                        ]
                    );
                    sqlsrv_free_stmt($stmtUpdate);
                    $updated++;
                } else {
                    $stmtInsert = sqlsrvExec(
                        $conn,
                        "
                            INSERT INTO dbo.ms_routing
                            (
                                routing_id,
                                planning_type,
                                routing_name,
                                use_mechine,
                                creat_by,
                                creat_at,
                                update_by,
                                update_at
                            )
                            VALUES (?, ?, ?, ?, ?, SYSDATETIME(), ?, SYSDATETIME())
                        ",
                        [
                            $item['routing_id'],
                            $item['planning_type'],
                            $item['routing_name'],
                            $item['use_mechine'],
                            $userLogin,
                            $userLogin,
                        ]
                    );
                    sqlsrv_free_stmt($stmtInsert);
                    $inserted++;
                }
            }

            if (!sqlsrv_commit($conn)) {
                throw new RuntimeException(sqlsrvErrorText());
            }

            $message = 'Data routing tidak berubah.';
            if ($inserted > 0 && $updated > 0) {
                $message = "Berhasil simpan {$inserted} data baru, {$updated} data diupdate.";
            } elseif ($inserted > 0) {
                $message = "Berhasil menambahkan {$inserted} routing.";
            } elseif ($updated > 0) {
                $message = "Berhasil mengupdate {$updated} routing.";
            }
            if ($skippedLength > 0) {
                $message .= " {$skippedLength} data dilewati karena Routing Code melebihi 20 karakter.";
            }

            responseJson([
                'success' => true,
                'message' => $message,
            ]);
        } catch (Throwable $inner) {
            sqlsrv_rollback($conn);
            throw $inner;
        }
    }

    if ($action === 'update') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responseJson([
                'success' => false,
                'message' => 'Method tidak didukung.',
            ], 405);
        }

        $routingId = trim((string)($_POST['routing_id'] ?? ''));
        if ($routingId === '') {
            responseJson([
                'success' => false,
                'message' => 'Routing ID wajib diisi.',
            ], 422);
        }
        if (strlen($routingId) > 20) {
            responseJson([
                'success' => false,
                'message' => 'Routing ID maksimal 20 karakter.',
            ], 422);
        }

        $planTypes = fetchMasterPlanTypeNames($conn);
        $inputType = trim((string)($_POST['plan_type'] ?? ''));
        $planType = resolvePlanType($inputType, $planTypes);
        if ($planType === null) {
            responseJson([
                'success' => false,
                'message' => 'Tipe planning tidak valid.',
            ], 422);
        }

        $routingName = substr(trim((string)($_POST['routing_name'] ?? '')), 0, 200);
        if ($routingName === '') {
            responseJson([
                'success' => false,
                'message' => 'Routing Name wajib diisi.',
            ], 422);
        }

        $useMechine = normalizeYn($_POST['use_mechine'] ?? 'N');

        $stmtCheck = sqlsrvExec(
            $conn,
            "SELECT routing_id FROM dbo.ms_routing WHERE routing_id = ?",
            [$routingId]
        );
        $exists = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtCheck);

        if (!$exists) {
            responseJson([
                'success' => false,
                'message' => 'Data routing tidak ditemukan.',
            ], 404);
        }

        $stmtUpdate = sqlsrvExec(
            $conn,
            "
                UPDATE dbo.ms_routing
                SET planning_type = ?,
                    routing_name = ?,
                    use_mechine = ?,
                    update_by = ?,
                    update_at = SYSDATETIME()
                WHERE routing_id = ?
            ",
            [
                $planType,
                $routingName,
                $useMechine,
                $userLogin,
                $routingId,
            ]
        );
        sqlsrv_free_stmt($stmtUpdate);

        responseJson([
            'success' => true,
            'message' => 'Routing berhasil diupdate.',
        ]);
    }

    if ($action === 'delete') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responseJson([
                'success' => false,
                'message' => 'Method tidak didukung.',
            ], 405);
        }

        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $ids = array_values(array_filter(array_map(function ($v) {
            return trim((string)$v);
        }, $ids)));

        if (empty($ids)) {
            responseJson([
                'success' => false,
                'message' => 'Tidak ada data yang dipilih.',
            ], 422);
        }

        $holders = implode(',', array_fill(0, count($ids), '?'));
        $stmtTarget = sqlsrvExec(
            $conn,
            "SELECT routing_id, LTRIM(RTRIM(ISNULL(routing_name, ''))) AS routing_name FROM dbo.ms_routing WHERE routing_id IN ($holders)",
            $ids
        );
        $targetRows = [];
        while ($row = sqlsrv_fetch_array($stmtTarget, SQLSRV_FETCH_ASSOC)) {
            $routingId = trim((string)($row['routing_id'] ?? ''));
            if ($routingId === '') {
                continue;
            }
            $targetRows[] = [
                'routing_id' => $routingId,
                'routing_name' => trim((string)($row['routing_name'] ?? '')),
            ];
        }
        sqlsrv_free_stmt($stmtTarget);

        if (empty($targetRows)) {
            responseJson([
                'success' => true,
                'message' => 'Data tidak ditemukan.',
            ]);
        }

        $targetNameSet = [];
        foreach ($targetRows as $row) {
            $name = trim((string)($row['routing_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $targetNameSet[strtolower($name)] = $name;
        }

        $removableNameSet = [];
        if (!empty($targetNameSet)) {
            $stmtRemain = sqlsrvExec(
                $conn,
                "
                    SELECT DISTINCT LTRIM(RTRIM(ISNULL(routing_name, ''))) AS routing_name
                    FROM dbo.ms_routing
                    WHERE routing_id NOT IN ($holders)
                      AND LTRIM(RTRIM(ISNULL(routing_name, ''))) <> ''
                ",
                $ids
            );
            $remainingNameSet = [];
            while ($row = sqlsrv_fetch_array($stmtRemain, SQLSRV_FETCH_ASSOC)) {
                $name = trim((string)($row['routing_name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $remainingNameSet[strtolower($name)] = true;
            }
            sqlsrv_free_stmt($stmtRemain);

            foreach ($targetNameSet as $nameKey => $nameValue) {
                if (!isset($remainingNameSet[$nameKey])) {
                    $removableNameSet[$nameKey] = $nameValue;
                }
            }
        }

        $mappingKeys = [];
        foreach ($ids as $id) {
            $id = trim((string)$id);
            if ($id !== '') {
                $mappingKeys[strtolower($id)] = $id;
            }
        }
        foreach ($removableNameSet as $nameValue) {
            $name = trim((string)$nameValue);
            if ($name !== '') {
                $mappingKeys[strtolower($name)] = $name;
            }
        }

        if (!sqlsrv_begin_transaction($conn)) {
            throw new RuntimeException(sqlsrvErrorText());
        }

        try {
            $removedGroup = 0;
            $removedSetting = 0;
            $hasPlanningGroupTable = sqlsrvTableExists($conn, 'planning_group_rtg');
            $hasPlanningSettingTable = sqlsrvTableExists($conn, 'planning_setting');

            if (!empty($mappingKeys)) {
                $mappingValues = array_values($mappingKeys);
                $mappingHolders = implode(',', array_fill(0, count($mappingValues), '?'));

                if ($hasPlanningGroupTable) {
                    $stmtDelGroup = sqlsrvExec(
                        $conn,
                        "DELETE FROM planning_group_rtg WHERE rtg_name IN ($mappingHolders)",
                        $mappingValues
                    );
                    $removedGroup = sqlsrv_rows_affected($stmtDelGroup);
                    sqlsrv_free_stmt($stmtDelGroup);
                }

                if ($hasPlanningSettingTable) {
                    $stmtDelSetting = sqlsrvExec(
                        $conn,
                        "DELETE FROM planning_setting WHERE rtg_name IN ($mappingHolders)",
                        $mappingValues
                    );
                    $removedSetting = sqlsrv_rows_affected($stmtDelSetting);
                    sqlsrv_free_stmt($stmtDelSetting);
                }
            }

            $relatedOther = findRelatedRowsByColumn(
                $conn,
                'routing_id',
                $ids,
                ['dbo.ms_routing']
            );
            if (!empty($relatedOther)) {
                $parts = [];
                foreach ($relatedOther as $tbl => $cnt) {
                    $parts[] = "{$tbl} ({$cnt})";
                }
                $detail = implode(', ', $parts);
                sqlsrv_rollback($conn);
                responseJson([
                    'success' => false,
                    'message' => 'Gagal menghapus karena routing masih dipakai pada tabel lain. Relasi: ' . $detail,
                ], 422);
            }

            $stmtDelete = sqlsrvExec($conn, "DELETE FROM dbo.ms_routing WHERE routing_id IN ($holders)", $ids);
            $deleted = sqlsrv_rows_affected($stmtDelete);
            sqlsrv_free_stmt($stmtDelete);

            if (!sqlsrv_commit($conn)) {
                throw new RuntimeException(sqlsrvErrorText());
            }

            if ($deleted === false || $deleted < 0) {
                $deleted = 0;
            }
            if ($removedGroup === false || $removedGroup < 0) {
                $removedGroup = 0;
            }
            if ($removedSetting === false || $removedSetting < 0) {
                $removedSetting = 0;
            }

            responseJson([
                'success' => true,
                'message' => $deleted > 0
                    ? "Berhasil menghapus {$deleted} data."
                    : 'Data tidak ditemukan.',
                'removed_group_mapping' => (int)$removedGroup,
                'removed_user_setting' => (int)$removedSetting,
            ]);
        } catch (Throwable $inner) {
            sqlsrv_rollback($conn);
            throw $inner;
        }
    }

    if ($action === 'machine_list') {
        $planTypes = fetchMasterPlanTypeNames($conn);
        $planType = trim((string)($_GET['plan_type'] ?? ''));
        if ($planType !== '') {
            $canonicalType = resolvePlanType($planType, $planTypes);
            if ($canonicalType === null) {
                responseJson([
                    'success' => false,
                    'message' => 'Tipe planning tidak valid.',
                ], 422);
            }
            $planType = $canonicalType;
        }

        $sql = "
            SELECT
                machine_id,
                planning_type,
                machine_name,
                machine_desc,
                creat_by,
                creat_at,
                update_by,
                update_at
            FROM dbo.ms_machine
        ";
        $params = [];
        if ($planType !== '') {
            $sql .= " WHERE planning_type = ?";
            $params[] = $planType;
        }
        $sql .= " ORDER BY planning_type ASC, machine_id ASC";

        $stmt = sqlsrvExec($conn, $sql, $params);
        $rows = [];

        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $lastUpdate = $row['update_at'] ?? null;
            if ($lastUpdate === null) {
                $lastUpdate = $row['creat_at'] ?? null;
            }

            $updatedBy = trim((string)($row['update_by'] ?? ''));
            if ($updatedBy === '') {
                $updatedBy = trim((string)($row['creat_by'] ?? ''));
            }

            $machineId = trim((string)($row['machine_id'] ?? ''));
            $rows[] = [
                'id' => $machineId,
                'plan_type' => (string)($row['planning_type'] ?? ''),
                'facode' => $machineId,
                'faname' => (string)($row['machine_name'] ?? ''),
                'faalias' => (string)($row['machine_desc'] ?? ''),
                'upddate' => sqlsrvDateToString($lastUpdate),
                'upduser' => $updatedBy,
            ];
        }
        sqlsrv_free_stmt($stmt);

        responseJson([
            'success' => true,
            'data' => $rows,
        ]);
    }

    if ($action === 'max_capacity_list') {
        ensureMaxProductionCapacityTable($conn);

        $planTypes = fetchMasterPlanTypeNames($conn);
        $planType = trim((string)($_GET['plan_type'] ?? ''));
        if ($planType !== '') {
            $canonicalType = resolvePlanType($planType, $planTypes);
            if ($canonicalType === null) {
                responseJson([
                    'success' => false,
                    'message' => 'Tipe planning tidak valid.',
                ], 422);
            }
            $planType = $canonicalType;
        }

        $sql = "
            SELECT
                c.id,
                c.planning_type,
                c.machine_id,
                c.max_capacity_day,
                c.creat_by,
                c.creat_at,
                c.update_by,
                c.update_at,
                LTRIM(RTRIM(ISNULL(m.machine_name, ''))) AS machine_name
            FROM dbo.ms_max_production_capacity c
            LEFT JOIN dbo.ms_machine m
                ON m.machine_id = c.machine_id
               AND LOWER(LTRIM(RTRIM(m.planning_type))) = LOWER(LTRIM(RTRIM(c.planning_type)))
        ";
        $params = [];
        if ($planType !== '') {
            $sql .= " WHERE c.planning_type = ?";
            $params[] = $planType;
        }
        $sql .= " ORDER BY c.planning_type ASC, c.machine_id ASC";

        $stmt = sqlsrvExec($conn, $sql, $params);
        $rows = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $lastUpdate = $row['update_at'] ?? null;
            if ($lastUpdate === null) {
                $lastUpdate = $row['creat_at'] ?? null;
            }

            $updatedBy = trim((string)($row['update_by'] ?? ''));
            if ($updatedBy === '') {
                $updatedBy = trim((string)($row['creat_by'] ?? ''));
            }

            $machineCode = trim((string)($row['machine_id'] ?? ''));
            $machineName = trim((string)($row['machine_name'] ?? ''));
            $machineLabel = $machineName !== '' ? ($machineName . ' (' . $machineCode . ')') : $machineCode;

            $rows[] = [
                'id' => (int)($row['id'] ?? 0),
                'plan_type' => trim((string)($row['planning_type'] ?? '')),
                'machine_id' => $machineCode,
                'machine_name' => $machineName,
                'machine_label' => $machineLabel,
                'max_capacity_day' => (float)($row['max_capacity_day'] ?? 0),
                'upddate' => sqlsrvDateToString($lastUpdate),
                'upduser' => $updatedBy,
            ];
        }
        sqlsrv_free_stmt($stmt);

        responseJson([
            'success' => true,
            'data' => $rows,
        ]);
    }

    if ($action === 'max_capacity_add') {
        ensureMaxProductionCapacityTable($conn);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responseJson([
                'success' => false,
                'message' => 'Method tidak didukung.',
            ], 405);
        }

        $planTypes = fetchMasterPlanTypeNames($conn);
        $inputType = trim((string)($_POST['plan_type'] ?? ''));
        $planType = resolvePlanType($inputType, $planTypes);
        if ($planType === null) {
            responseJson([
                'success' => false,
                'message' => 'Tipe planning tidak valid.',
            ], 422);
        }

        $machineId = substr(trim((string)($_POST['machine_id'] ?? '')), 0, 20);
        if ($machineId === '') {
            responseJson([
                'success' => false,
                'message' => 'Machine wajib dipilih.',
            ], 422);
        }

        $maxCapacity = normalizeCapacityPerDay($_POST['max_capacity_day'] ?? '');
        if ($maxCapacity === null || $maxCapacity <= 0) {
            responseJson([
                'success' => false,
                'message' => 'Max Capacity/Day harus diisi dengan angka lebih dari 0.',
            ], 422);
        }

        $stmtMachine = sqlsrvExec(
            $conn,
            "
                SELECT TOP 1 machine_id
                FROM dbo.ms_machine
                WHERE machine_id = ?
                  AND LOWER(LTRIM(RTRIM(planning_type))) = LOWER(LTRIM(RTRIM(?)))
            ",
            [$machineId, $planType]
        );
        $machineExists = sqlsrv_fetch_array($stmtMachine, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtMachine);
        if (!$machineExists) {
            responseJson([
                'success' => false,
                'message' => 'Machine tidak ditemukan untuk plan type terpilih.',
            ], 422);
        }

        $stmtDup = sqlsrvExec(
            $conn,
            "
                SELECT TOP 1 id
                FROM dbo.ms_max_production_capacity
                WHERE LOWER(LTRIM(RTRIM(planning_type))) = LOWER(LTRIM(RTRIM(?)))
                  AND LOWER(LTRIM(RTRIM(machine_id))) = LOWER(LTRIM(RTRIM(?)))
            ",
            [$planType, $machineId]
        );
        $dup = sqlsrv_fetch_array($stmtDup, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtDup);
        if ($dup) {
            responseJson([
                'success' => false,
                'message' => 'Data max capacity untuk plan type dan machine tersebut sudah ada.',
            ], 422);
        }

        $stmtInsert = sqlsrvExec(
            $conn,
            "
                INSERT INTO dbo.ms_max_production_capacity
                (
                    planning_type,
                    machine_id,
                    max_capacity_day,
                    creat_by,
                    creat_at,
                    update_by,
                    update_at
                )
                VALUES (?, ?, ?, ?, SYSDATETIME(), ?, SYSDATETIME())
            ",
            [$planType, $machineId, $maxCapacity, $userLogin, $userLogin]
        );
        sqlsrv_free_stmt($stmtInsert);

        responseJson([
            'success' => true,
            'message' => 'Max production capacity berhasil ditambahkan.',
        ]);
    }

    if ($action === 'max_capacity_update') {
        ensureMaxProductionCapacityTable($conn);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responseJson([
                'success' => false,
                'message' => 'Method tidak didukung.',
            ], 405);
        }

        $capacityId = (int)($_POST['capacity_id'] ?? 0);
        if ($capacityId <= 0) {
            responseJson([
                'success' => false,
                'message' => 'ID max capacity tidak valid.',
            ], 422);
        }

        $planTypes = fetchMasterPlanTypeNames($conn);
        $inputType = trim((string)($_POST['plan_type'] ?? ''));
        $planType = resolvePlanType($inputType, $planTypes);
        if ($planType === null) {
            responseJson([
                'success' => false,
                'message' => 'Tipe planning tidak valid.',
            ], 422);
        }

        $machineId = substr(trim((string)($_POST['machine_id'] ?? '')), 0, 20);
        if ($machineId === '') {
            responseJson([
                'success' => false,
                'message' => 'Machine wajib dipilih.',
            ], 422);
        }

        $maxCapacity = normalizeCapacityPerDay($_POST['max_capacity_day'] ?? '');
        if ($maxCapacity === null || $maxCapacity <= 0) {
            responseJson([
                'success' => false,
                'message' => 'Max Capacity/Day harus diisi dengan angka lebih dari 0.',
            ], 422);
        }

        $stmtCheck = sqlsrvExec(
            $conn,
            "SELECT id FROM dbo.ms_max_production_capacity WHERE id = ?",
            [$capacityId]
        );
        $exists = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtCheck);
        if (!$exists) {
            responseJson([
                'success' => false,
                'message' => 'Data max capacity tidak ditemukan.',
            ], 404);
        }

        $stmtMachine = sqlsrvExec(
            $conn,
            "
                SELECT TOP 1 machine_id
                FROM dbo.ms_machine
                WHERE machine_id = ?
                  AND LOWER(LTRIM(RTRIM(planning_type))) = LOWER(LTRIM(RTRIM(?)))
            ",
            [$machineId, $planType]
        );
        $machineExists = sqlsrv_fetch_array($stmtMachine, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtMachine);
        if (!$machineExists) {
            responseJson([
                'success' => false,
                'message' => 'Machine tidak ditemukan untuk plan type terpilih.',
            ], 422);
        }

        $stmtDup = sqlsrvExec(
            $conn,
            "
                SELECT TOP 1 id
                FROM dbo.ms_max_production_capacity
                WHERE id <> ?
                  AND LOWER(LTRIM(RTRIM(planning_type))) = LOWER(LTRIM(RTRIM(?)))
                  AND LOWER(LTRIM(RTRIM(machine_id))) = LOWER(LTRIM(RTRIM(?)))
            ",
            [$capacityId, $planType, $machineId]
        );
        $dup = sqlsrv_fetch_array($stmtDup, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtDup);
        if ($dup) {
            responseJson([
                'success' => false,
                'message' => 'Data max capacity untuk plan type dan machine tersebut sudah ada.',
            ], 422);
        }

        $stmtUpdate = sqlsrvExec(
            $conn,
            "
                UPDATE dbo.ms_max_production_capacity
                SET planning_type = ?,
                    machine_id = ?,
                    max_capacity_day = ?,
                    update_by = ?,
                    update_at = SYSDATETIME()
                WHERE id = ?
            ",
            [$planType, $machineId, $maxCapacity, $userLogin, $capacityId]
        );
        sqlsrv_free_stmt($stmtUpdate);

        responseJson([
            'success' => true,
            'message' => 'Max production capacity berhasil diupdate.',
        ]);
    }

    if ($action === 'max_capacity_delete') {
        ensureMaxProductionCapacityTable($conn);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responseJson([
                'success' => false,
                'message' => 'Method tidak didukung.',
            ], 405);
        }

        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($v) => $v > 0)));
        if (empty($ids)) {
            responseJson([
                'success' => false,
                'message' => 'Tidak ada data yang dipilih.',
            ], 422);
        }

        $holders = implode(',', array_fill(0, count($ids), '?'));
        $stmtDelete = sqlsrvExec(
            $conn,
            "DELETE FROM dbo.ms_max_production_capacity WHERE id IN ($holders)",
            $ids
        );
        $deleted = sqlsrv_rows_affected($stmtDelete);
        sqlsrv_free_stmt($stmtDelete);

        if ($deleted === false || $deleted < 0) {
            $deleted = 0;
        }

        responseJson([
            'success' => true,
            'message' => $deleted > 0
                ? "Berhasil menghapus {$deleted} data."
                : 'Data tidak ditemukan.',
        ]);
    }

    if ($action === 'machine_search') {
        $searchBy = trim((string)($_GET['search_by'] ?? 'facode'));
        $keyword = trim((string)($_GET['keyword'] ?? ''));
        $rows = searchMachine($conn3, $searchBy, $keyword, 500);

        $mapped = array_map(function ($row) {
            return [
                'famasterid' => (int)($row['famasterid'] ?? 0),
                'facode' => (string)($row['facode'] ?? ''),
                'faname' => (string)($row['faname'] ?? ''),
                'faalias' => (string)($row['faalias'] ?? ''),
            ];
        }, $rows);

        responseJson([
            'success' => true,
            'data' => $mapped,
        ]);
    }

    if ($action === 'machine_add') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responseJson([
                'success' => false,
                'message' => 'Method tidak didukung.',
            ], 405);
        }

        $planTypes = fetchMasterPlanTypeNames($conn);
        $inputType = trim((string)($_POST['plan_type'] ?? ''));
        $planType = resolvePlanType($inputType, $planTypes);
        if ($planType === null) {
            responseJson([
                'success' => false,
                'message' => 'Tipe planning tidak valid.',
            ], 422);
        }

        $famasterids = $_POST['famasterids'] ?? [];
        if (!is_array($famasterids)) {
            $famasterids = [$famasterids];
        }

        $famasterids = array_values(array_unique(array_map('intval', $famasterids)));
        $famasterids = array_values(array_filter($famasterids, fn ($v) => $v > 0));

        if (empty($famasterids)) {
            responseJson([
                'success' => false,
                'message' => 'Pilih machine terlebih dahulu.',
            ], 422);
        }

        $validMachines = fetchMachineDetails($conn3, $famasterids);
        if (empty($validMachines)) {
            responseJson([
                'success' => false,
                'message' => 'Machine tidak ditemukan di famaster.',
            ], 422);
        }

        $payloadByCode = [];
        $skippedLength = 0;
        foreach ($validMachines as $machine) {
            $machineId = trim((string)($machine['facode'] ?? ''));
            if ($machineId === '') {
                continue;
            }

            if (strlen($machineId) > 20) {
                $skippedLength++;
                continue;
            }

            $codeKey = strtolower($machineId);
            $machineName = trim((string)($machine['faname'] ?? ''));
            if ($machineName === '') {
                $machineName = $machineId;
            }
            $payloadByCode[$codeKey] = [
                'machine_id' => $machineId,
                'planning_type' => $planType,
                'machine_name' => substr($machineName, 0, 200),
                'machine_desc' => substr(trim((string)($machine['faalias'] ?? '')), 0, 255),
            ];
        }

        if (empty($payloadByCode)) {
            responseJson([
                'success' => false,
                'message' => 'Machine tidak valid untuk disimpan.',
            ], 422);
        }

        if (!sqlsrv_begin_transaction($conn)) {
            throw new RuntimeException(sqlsrvErrorText());
        }

        try {
            $inserted = 0;
            $updated = 0;

            foreach ($payloadByCode as $item) {
                $machineId = $item['machine_id'];

                $stmtCheck = sqlsrvExec(
                    $conn,
                    "SELECT machine_id FROM dbo.ms_machine WHERE machine_id = ?",
                    [$machineId]
                );
                $exists = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
                sqlsrv_free_stmt($stmtCheck);

                if ($exists) {
                    $stmtUpdate = sqlsrvExec(
                        $conn,
                        "
                            UPDATE dbo.ms_machine
                            SET planning_type = ?,
                                machine_name = ?,
                                machine_desc = ?,
                                update_by = ?,
                                update_at = SYSDATETIME()
                            WHERE machine_id = ?
                        ",
                        [
                            $item['planning_type'],
                            $item['machine_name'],
                            $item['machine_desc'],
                            $userLogin,
                            $machineId,
                        ]
                    );
                    sqlsrv_free_stmt($stmtUpdate);
                    $updated++;
                } else {
                    $stmtInsert = sqlsrvExec(
                        $conn,
                        "
                            INSERT INTO dbo.ms_machine
                            (
                                machine_id,
                                planning_type,
                                machine_name,
                                machine_desc,
                                creat_by,
                                creat_at,
                                update_by,
                                update_at
                            )
                            VALUES (?, ?, ?, ?, ?, SYSDATETIME(), ?, SYSDATETIME())
                        ",
                        [
                            $item['machine_id'],
                            $item['planning_type'],
                            $item['machine_name'],
                            $item['machine_desc'],
                            $userLogin,
                            $userLogin,
                        ]
                    );
                    sqlsrv_free_stmt($stmtInsert);
                    $inserted++;
                }
            }

            if (!sqlsrv_commit($conn)) {
                throw new RuntimeException(sqlsrvErrorText());
            }

            $message = 'Data machine tidak berubah.';
            if ($inserted > 0 && $updated > 0) {
                $message = "Berhasil simpan {$inserted} data baru, {$updated} data diupdate.";
            } elseif ($inserted > 0) {
                $message = "Berhasil menambahkan {$inserted} machine.";
            } elseif ($updated > 0) {
                $message = "Berhasil mengupdate {$updated} machine.";
            }
            if ($skippedLength > 0) {
                $message .= " {$skippedLength} data dilewati karena Machine Code melebihi 20 karakter.";
            }

            responseJson([
                'success' => true,
                'message' => $message,
            ]);
        } catch (Throwable $inner) {
            sqlsrv_rollback($conn);
            throw $inner;
        }
    }

    if ($action === 'machine_update') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responseJson([
                'success' => false,
                'message' => 'Method tidak didukung.',
            ], 405);
        }

        $machineId = trim((string)($_POST['machine_id'] ?? ''));
        if ($machineId === '') {
            responseJson([
                'success' => false,
                'message' => 'Machine ID wajib diisi.',
            ], 422);
        }
        if (strlen($machineId) > 20) {
            responseJson([
                'success' => false,
                'message' => 'Machine ID maksimal 20 karakter.',
            ], 422);
        }

        $planTypes = fetchMasterPlanTypeNames($conn);
        $inputType = trim((string)($_POST['plan_type'] ?? ''));
        $planType = resolvePlanType($inputType, $planTypes);
        if ($planType === null) {
            responseJson([
                'success' => false,
                'message' => 'Tipe planning tidak valid.',
            ], 422);
        }

        $machineName = substr(trim((string)($_POST['machine_name'] ?? '')), 0, 200);
        if ($machineName === '') {
            responseJson([
                'success' => false,
                'message' => 'Machine Name wajib diisi.',
            ], 422);
        }
        $machineDesc = substr(trim((string)($_POST['machine_desc'] ?? '')), 0, 255);

        $stmtCheck = sqlsrvExec(
            $conn,
            "SELECT machine_id FROM dbo.ms_machine WHERE machine_id = ?",
            [$machineId]
        );
        $exists = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtCheck);

        if (!$exists) {
            responseJson([
                'success' => false,
                'message' => 'Data machine tidak ditemukan.',
            ], 404);
        }

        $stmtUpdate = sqlsrvExec(
            $conn,
            "
                UPDATE dbo.ms_machine
                SET planning_type = ?,
                    machine_name = ?,
                    machine_desc = ?,
                    update_by = ?,
                    update_at = SYSDATETIME()
                WHERE machine_id = ?
            ",
            [
                $planType,
                $machineName,
                $machineDesc,
                $userLogin,
                $machineId,
            ]
        );
        sqlsrv_free_stmt($stmtUpdate);

        responseJson([
            'success' => true,
            'message' => 'Machine berhasil diupdate.',
        ]);
    }

    if ($action === 'machine_delete') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responseJson([
                'success' => false,
                'message' => 'Method tidak didukung.',
            ], 405);
        }

        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $ids = array_values(array_filter(array_map(function ($v) {
            return trim((string)$v);
        }, $ids)));

        if (empty($ids)) {
            responseJson([
                'success' => false,
                'message' => 'Tidak ada data yang dipilih.',
            ], 422);
        }

        $relatedOther = findRelatedRowsByColumn(
            $conn,
            'machine_id',
            $ids,
            ['dbo.ms_machine']
        );
        if (!empty($relatedOther)) {
            $parts = [];
            foreach ($relatedOther as $tbl => $cnt) {
                $parts[] = "{$tbl} ({$cnt})";
            }
            $detail = implode(', ', $parts);
            responseJson([
                'success' => false,
                'message' => 'Gagal menghapus karena master terkait dengan data. Relasi: ' . $detail,
            ], 422);
        }

        $holders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "DELETE FROM dbo.ms_machine WHERE machine_id IN ($holders)";
        $stmtDelete = sqlsrvExec($conn, $sql, $ids);
        $deleted = sqlsrv_rows_affected($stmtDelete);
        sqlsrv_free_stmt($stmtDelete);

        if ($deleted === false || $deleted < 0) {
            $deleted = 0;
        }

        responseJson([
            'success' => true,
            'message' => $deleted > 0
                ? "Berhasil menghapus {$deleted} data."
                : 'Data tidak ditemukan.',
        ]);
    }

    if ($action === 'break_list') {
        $sql = "
            SELECT
                break_id,
                break_time_name,
                ISNULL(break_time_minutes, 0) AS break_time_minutes,
                creat_by,
                creat_at,
                update_by,
                update_at
            FROM dbo.ms_break_time
            ORDER BY break_time_name ASC
        ";

        $stmt = sqlsrvExec($conn, $sql);
        $rows = [];

        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $lastUpdate = $row['update_at'] ?? null;
            if ($lastUpdate === null) {
                $lastUpdate = $row['creat_at'] ?? null;
            }

            $updatedBy = trim((string)($row['update_by'] ?? ''));
            if ($updatedBy === '') {
                $updatedBy = trim((string)($row['creat_by'] ?? ''));
            }

            $rows[] = [
                'id' => (string)($row['break_id'] ?? ''),
                'break_time_name' => (string)($row['break_time_name'] ?? ''),
                'break_time_minutes' => (int)($row['break_time_minutes'] ?? 0),
                'upddate' => sqlsrvDateToString($lastUpdate),
                'upduser' => $updatedBy,
            ];
        }
        sqlsrv_free_stmt($stmt);

        responseJson([
            'success' => true,
            'data' => $rows,
        ]);
    }

    if ($action === 'break_add') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responseJson([
                'success' => false,
                'message' => 'Method tidak didukung.',
            ], 405);
        }

        $breakTimeName = substr(trim((string)($_POST['break_time_name'] ?? '')), 0, 200);
        if ($breakTimeName === '') {
            responseJson([
                'success' => false,
                'message' => 'Break Time wajib diisi.',
            ], 422);
        }

        $breakTimeMinutes = (int)($_POST['break_time_minutes'] ?? 0);
        if ($breakTimeMinutes < 0) {
            responseJson([
                'success' => false,
                'message' => 'Waktu (Menit) tidak valid.',
            ], 422);
        }

        $stmtCheck = sqlsrvExec(
            $conn,
            "
                SELECT TOP 1 break_id
                FROM dbo.ms_break_time
                WHERE LOWER(LTRIM(RTRIM(break_time_name))) = LOWER(LTRIM(RTRIM(?)))
            ",
            [$breakTimeName]
        );
        $exists = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtCheck);

        if ($exists) {
            responseJson([
                'success' => false,
                'message' => 'Break time sudah ada.',
            ], 422);
        }

        $stmtInsert = sqlsrvExec(
            $conn,
            "
                INSERT INTO dbo.ms_break_time
                (
                    break_time_name,
                    break_time_minutes,
                    creat_by,
                    creat_at,
                    update_by,
                    update_at
                )
                VALUES (?, ?, ?, SYSDATETIME(), ?, SYSDATETIME())
            ",
            [
                $breakTimeName,
                $breakTimeMinutes,
                $userLogin,
                $userLogin,
            ]
        );
        sqlsrv_free_stmt($stmtInsert);

        responseJson([
            'success' => true,
            'message' => 'Break time berhasil ditambahkan.',
        ]);
    }

    if ($action === 'break_update') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responseJson([
                'success' => false,
                'message' => 'Method tidak didukung.',
            ], 405);
        }

        $breakId = (int)($_POST['break_id'] ?? 0);
        if ($breakId <= 0) {
            responseJson([
                'success' => false,
                'message' => 'Break ID tidak valid.',
            ], 422);
        }

        $breakTimeName = substr(trim((string)($_POST['break_time_name'] ?? '')), 0, 200);
        if ($breakTimeName === '') {
            responseJson([
                'success' => false,
                'message' => 'Break Time wajib diisi.',
            ], 422);
        }

        $breakTimeMinutes = (int)($_POST['break_time_minutes'] ?? 0);
        if ($breakTimeMinutes < 0) {
            responseJson([
                'success' => false,
                'message' => 'Waktu (Menit) tidak valid.',
            ], 422);
        }

        $stmtCheck = sqlsrvExec(
            $conn,
            "SELECT break_id FROM dbo.ms_break_time WHERE break_id = ?",
            [$breakId]
        );
        $exists = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtCheck);

        if (!$exists) {
            responseJson([
                'success' => false,
                'message' => 'Data break time tidak ditemukan.',
            ], 404);
        }

        $stmtDup = sqlsrvExec(
            $conn,
            "
                SELECT TOP 1 break_id
                FROM dbo.ms_break_time
                WHERE break_id <> ?
                  AND LOWER(LTRIM(RTRIM(break_time_name))) = LOWER(LTRIM(RTRIM(?)))
            ",
            [$breakId, $breakTimeName]
        );
        $dup = sqlsrv_fetch_array($stmtDup, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtDup);

        if ($dup) {
            responseJson([
                'success' => false,
                'message' => 'Break time sudah ada.',
            ], 422);
        }

        $stmtUpdate = sqlsrvExec(
            $conn,
            "
                UPDATE dbo.ms_break_time
                SET break_time_name = ?,
                    break_time_minutes = ?,
                    update_by = ?,
                    update_at = SYSDATETIME()
                WHERE break_id = ?
            ",
            [
                $breakTimeName,
                $breakTimeMinutes,
                $userLogin,
                $breakId,
            ]
        );
        sqlsrv_free_stmt($stmtUpdate);

        responseJson([
            'success' => true,
            'message' => 'Break time berhasil diupdate.',
        ]);
    }

    if ($action === 'break_delete') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responseJson([
                'success' => false,
                'message' => 'Method tidak didukung.',
            ], 405);
        }

        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($v) => $v > 0)));
        if (empty($ids)) {
            responseJson([
                'success' => false,
                'message' => 'Tidak ada data yang dipilih.',
            ], 422);
        }

        $relatedOther = findRelatedRowsByColumn(
            $conn,
            'break_id',
            $ids,
            ['dbo.ms_break_time']
        );
        if (!empty($relatedOther)) {
            $parts = [];
            foreach ($relatedOther as $tbl => $cnt) {
                $parts[] = "{$tbl} ({$cnt})";
            }
            $detail = implode(', ', $parts);
            responseJson([
                'success' => false,
                'message' => 'Gagal menghapus karena master terkait dengan data. Relasi: ' . $detail,
            ], 422);
        }

        $holders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "DELETE FROM dbo.ms_break_time WHERE break_id IN ($holders)";
        $stmtDelete = sqlsrvExec($conn, $sql, $ids);
        $deleted = sqlsrv_rows_affected($stmtDelete);
        sqlsrv_free_stmt($stmtDelete);

        if ($deleted === false || $deleted < 0) {
            $deleted = 0;
        }

        responseJson([
            'success' => true,
            'message' => $deleted > 0
                ? "Berhasil menghapus {$deleted} data."
                : 'Data tidak ditemukan.',
        ]);
    }

    // DOWNTIME LIST
    if ($action === 'downtime_list') {
        $sql = "
            SELECT
                id,
                down_time_name,
                created_by,
                created_at,
                updated_by,
                updated_at
            FROM dbo.ms_downtime
            ORDER BY down_time_name ASC
        ";

        $stmt = sqlsrvExec($conn, $sql);
        $rows = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $lastUpdate = $row['updated_at'] ?? null;
            if ($lastUpdate === null) {
                $lastUpdate = $row['created_at'] ?? null;
            }

            $updatedBy = trim((string)($row['updated_by'] ?? ''));
            if ($updatedBy === '') {
                $updatedBy = trim((string)($row['created_by'] ?? ''));
            }

            $rows[] = [
                'id' => (string)($row['id'] ?? ''),
                'down_time_name' => (string)($row['down_time_name'] ?? ''),
                'upddate' => sqlsrvDateToString($lastUpdate),
                'upduser' => $updatedBy,
            ];
        }
        sqlsrv_free_stmt($stmt);

        responseJson([
            'success' => true,
            'data' => $rows,
        ]);
    }

    // DOWNTIME ADD
    if ($action === 'downtime_add') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responseJson([
                'success' => false,
                'message' => 'Method tidak didukung.',
            ], 405);
        }

        $downTimeName = substr(trim((string)($_POST['down_time_name'] ?? '')), 0, 200);
        if ($downTimeName === '') {
            responseJson([
                'success' => false,
                'message' => 'Down Time wajib diisi.',
            ], 422);
        }

        $stmtCheck = sqlsrvExec(
            $conn,
            "
                SELECT TOP 1 id
                FROM dbo.ms_downtime
                WHERE LOWER(LTRIM(RTRIM(down_time_name))) = LOWER(LTRIM(RTRIM(?)))
            ",
            [$downTimeName]
        );
        $exists = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtCheck);

        if ($exists) {
            responseJson([
                'success' => false,
                'message' => 'Down time sudah ada.',
            ], 422);
        }

        $stmtInsert = sqlsrvExec(
            $conn,
            "
                INSERT INTO dbo.ms_downtime
                (
                    down_time_name,
                    created_by,
                    created_at,
                    updated_by,
                    updated_at
                )
                VALUES (?, ?, SYSDATETIME(), ?, SYSDATETIME())
            ",
            [
                $downTimeName,
                $userLogin,
                $userLogin,
            ]
        );
        sqlsrv_free_stmt($stmtInsert);

        responseJson([
            'success' => true,
            'message' => 'Down time berhasil ditambahkan.',
        ]);
    }

    // DOWNTIME UPDATE
    if ($action === 'downtime_update') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responseJson([
                'success' => false,
                'message' => 'Method tidak didukung.',
            ], 405);
        }

        $downTimeId = (int)($_POST['downtime_id'] ?? 0);
        if ($downTimeId <= 0) {
            responseJson([
                'success' => false,
                'message' => 'Down Time ID tidak valid.',
            ], 422);
        }

        $downTimeName = substr(trim((string)($_POST['down_time_name'] ?? '')), 0, 200);
        if ($downTimeName === '') {
            responseJson([
                'success' => false,
                'message' => 'Down Time wajib diisi.',
            ], 422);
        }

        $stmtCheck = sqlsrvExec(
            $conn,
            "SELECT id FROM dbo.ms_downtime WHERE id = ?",
            [$downTimeId]
        );
        $exists = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtCheck);

        if (!$exists) {
            responseJson([
                'success' => false,
                'message' => 'Data down time tidak ditemukan.',
            ], 404);
        }

        $stmtDup = sqlsrvExec(
            $conn,
            "
                SELECT TOP 1 id
                FROM dbo.ms_downtime
                WHERE id <> ?
                  AND LOWER(LTRIM(RTRIM(down_time_name))) = LOWER(LTRIM(RTRIM(?)))
            ",
            [$downTimeId, $downTimeName]
        );
        $dup = sqlsrv_fetch_array($stmtDup, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtDup);

        if ($dup) {
            responseJson([
                'success' => false,
                'message' => 'Down time sudah ada.',
            ], 422);
        }

        $stmtUpdate = sqlsrvExec(
            $conn,
            "
                UPDATE dbo.ms_downtime
                SET down_time_name = ?,
                    updated_by = ?,
                    updated_at = SYSDATETIME()
                WHERE id = ?
            ",
            [
                $downTimeName,
                $userLogin,
                $downTimeId,
            ]
        );
        sqlsrv_free_stmt($stmtUpdate);

        responseJson([
            'success' => true,
            'message' => 'Down time berhasil diupdate.',
        ]);
    }

    // DOWNTIME DELETE
    if ($action === 'downtime_delete') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responseJson([
                'success' => false,
                'message' => 'Method tidak didukung.',
            ], 405);
        }

        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($v) => $v > 0)));
        if (empty($ids)) {
            responseJson([
                'success' => false,
                'message' => 'Tidak ada down time yang dipilih.',
            ], 422);
        }

        $relatedOther = findRelatedRowsByColumn(
            $conn,
            'downtime_id',
            $ids,
            ['dbo.ms_downtime']
        );
        $relatedOtherByAlt = findRelatedRowsByColumn(
            $conn,
            'down_time_id',
            $ids,
            ['dbo.ms_downtime']
        );
        foreach ($relatedOtherByAlt as $tbl => $cnt) {
            $relatedOther[$tbl] = ($relatedOther[$tbl] ?? 0) + (int)$cnt;
        }
        if (!empty($relatedOther)) {
            $parts = [];
            foreach ($relatedOther as $tbl => $cnt) {
                $parts[] = "{$tbl} ({$cnt})";
            }
            $detail = implode(', ', $parts);
            responseJson([
                'success' => false,
                'message' => 'Gagal menghapus karena master terkait dengan data. Relasi: ' . $detail,
            ], 422);
        }

        $holders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "DELETE FROM dbo.ms_downtime WHERE id IN ($holders)";
        $stmtDelete = sqlsrvExec($conn, $sql, $ids);
        $deleted = sqlsrv_rows_affected($stmtDelete);
        sqlsrv_free_stmt($stmtDelete);

        if ($deleted === false || $deleted < 0) {
            $deleted = 0;
        }

        responseJson([
            'success' => true,
            'message' => $deleted > 0
                ? "Berhasil menghapus {$deleted} down time."
                : 'Data tidak ditemukan.',
        ]);
    }

    responseJson([
        'success' => false,
        'message' => 'Action tidak dikenali.',
    ], 400);
} catch (Throwable $e) {
    responseJson([
        'success' => false,
        'message' => $e->getMessage(),
    ], 500);
}
