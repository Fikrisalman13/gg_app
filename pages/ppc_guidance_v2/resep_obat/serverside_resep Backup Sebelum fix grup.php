<?php
if (ob_get_level() > 0) {
    ob_clean();
}
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../../koneksi3.php';
require_once __DIR__ . '/label_jual_lookup.php';
header('Content-Type: application/json');

if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Sesi login telah berakhir.']);
    exit;
}

/** Format SQL Server date values for JSON display. */
function formatRecipeDate($value): string
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('d-m-Y H:i');
    }
    return $value ? date('d-m-Y H:i', strtotime((string) $value)) : '-';
}

/** Throw a safe internal exception when a SQL Server query fails. */
function requireRecipeStatement($statement, string $message)
{
    if (!$statement) {
        throw new RuntimeException($message);
    }
    return $statement;
}

try {
    $start = max(0, (int) ($_POST['start'] ?? 0));
    $length = max(1, min(100, (int) ($_POST['length'] ?? 10)));
    $draw = (int) ($_POST['draw'] ?? 1);
    $search = trim((string) ($_POST['search']['value'] ?? ''));
    $groupCode = "COALESCE(NULLIF(LTRIM(RTRIM(r.kode_warna)), ''), '-')";
    $groupCustomerColor = "COALESCE(NULLIF(LTRIM(RTRIM(r.cus_color)), ''), '-')";
    $groupStatus = "COALESCE(NULLIF(LTRIM(RTRIM(r.status_resep_lipat)), ''), '-')";

    $orderableColumns = [
        2 => 'representative.no_cp',
        4 => 'representative.kode_warna',
        5 => 'representative.color_name',
        6 => 'representative.cus_color',
        7 => 'representative.weight',
        8 => 'representative.plan_qty',
        9 => 'representative.color_desc',
        10 => 'representative.status_resep_lipat',
    ];
    $orderIndex = (int) ($_POST['order'][0]['column'] ?? 3);
    $orderColumn = $orderableColumns[$orderIndex] ?? 'representative.kode_warna';
    $orderDirection = strtolower((string) ($_POST['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';

    $whereConditions = [];
    $params = [];
    if ($search !== '') {
        $whereConditions[] = "(r.no_cp LIKE ? OR r.kode_warna LIKE ? OR r.cus_color LIKE ?
            OR r.color_name LIKE ? OR r.lot_no LIKE ? OR r.created_by LIKE ?)";
        array_push($params, ...array_fill(0, 6, "%{$search}%"));
    }
    if (!empty($_POST['startDate']) && !empty($_POST['endDate'])) {
        $whereConditions[] = '(r.created_at >= ? AND r.created_at <= ?)';
        $params[] = $_POST['startDate'] . ' 00:00:00';
        $params[] = $_POST['endDate'] . ' 23:59:59';
    }
    $statusFilter = $_POST['statusFilter'] ?? [];
    $statusFilter = is_array($statusFilter) ? $statusFilter : [$statusFilter];
    $statusFilter = array_values(array_filter(array_map('trim', $statusFilter), fn($value) => $value !== ''));
    if ($statusFilter) {
        $whereConditions[] = 'r.status_resep_lipat IN (' . implode(',', array_fill(0, count($statusFilter), '?')) . ')';
        array_push($params, ...$statusFilter);
    }
    $customerColorFilter = trim((string) ($_POST['cusColorFilter'] ?? ''));
    if ($customerColorFilter !== '') {
        $whereConditions[] = 'r.cus_color LIKE ?';
        $params[] = "%{$customerColorFilter}%";
    }

    $saleLabelFilters = $_POST['labelJualFilter'] ?? [];
    $saleLabelFilters = is_array($saleLabelFilters) ? $saleLabelFilters : [$saleLabelFilters];
    $saleLabelFilters = array_values(array_unique(array_filter(array_map(
        static fn($value): string => trim((string) $value),
        $saleLabelFilters,
    ))));
    if ($saleLabelFilters) {
        $productionNumbers = [];
        foreach ($saleLabelFilters as $saleLabelFilter) {
            array_push(
                $productionNumbers,
                ...recipeLocalProductionNumbersBySaleLabel($conn, $conn3, $saleLabelFilter),
            );
        }
        $productionNumbers = array_values(array_unique($productionNumbers));
        if (!$productionNumbers) {
            $whereConditions[] = '1 = 0';
        } else {
            $productionNumberConditions = [];
            foreach (array_chunk($productionNumbers, 500) as $productionNumberChunk) {
                $productionNumberConditions[] = 'LTRIM(RTRIM(r.no_cp)) IN ('
                    . implode(',', array_fill(0, count($productionNumberChunk), '?')) . ')';
                array_push($params, ...$productionNumberChunk);
            }
            $whereConditions[] = '(' . implode(' OR ', $productionNumberConditions) . ')';
        }
    }
    $whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    $totalStatement = requireRecipeStatement(sqlsrv_query(
        $conn,
        "SELECT COUNT(*) AS total FROM (
            SELECT $groupCode AS group_code, $groupCustomerColor AS group_customer_color, $groupStatus AS group_status
            FROM dbo.resep_obat r GROUP BY $groupCode, $groupCustomerColor, $groupStatus
        ) groups",
    ), 'Gagal menghitung seluruh group resep.');
    $totalRecords = (int) sqlsrv_fetch_array($totalStatement, SQLSRV_FETCH_ASSOC)['total'];

    $filteredStatement = requireRecipeStatement(sqlsrv_query(
        $conn,
        "SELECT COUNT(*) AS total FROM (
            SELECT $groupCode AS group_code, $groupCustomerColor AS group_customer_color, $groupStatus AS group_status
            FROM dbo.resep_obat r $whereClause GROUP BY $groupCode, $groupCustomerColor, $groupStatus
        ) groups",
        $params,
    ), 'Gagal menghitung group resep terfilter.');
    $totalFiltered = (int) sqlsrv_fetch_array($filteredStatement, SQLSRV_FETCH_ASSOC)['total'];

    $summaryStatement = requireRecipeStatement(sqlsrv_query($conn, "SELECT
        SUM(CASE WHEN LOWER(LTRIM(RTRIM(COALESCE(r.status_resep_lipat, '')))) = 'shading' THEN 1 ELSE 0 END) AS shading,
        SUM(CASE WHEN LOWER(LTRIM(RTRIM(COALESCE(r.status_resep_lipat, '')))) = 'experiment' THEN 1 ELSE 0 END) AS experiment,
        SUM(CASE WHEN LOWER(LTRIM(RTRIM(COALESCE(r.status_resep_lipat, '')))) IN ('kestabilan', 'kesetabilan') THEN 1 ELSE 0 END) AS kesetabilan,
        SUM(CASE WHEN LOWER(LTRIM(RTRIM(COALESCE(r.status_resep_lipat, '')))) IN ('master resep', 'master') THEN 1 ELSE 0 END) AS master_resep
        FROM dbo.resep_obat r $whereClause", $params), 'Gagal menghitung ringkasan status.');
    $summaryRow = sqlsrv_fetch_array($summaryStatement, SQLSRV_FETCH_ASSOC) ?: [];
    $statusSummary = [
        'shading' => (int) ($summaryRow['shading'] ?? 0),
        'experiment' => (int) ($summaryRow['experiment'] ?? 0),
        'kesetabilan' => (int) ($summaryRow['kesetabilan'] ?? 0),
        'master_resep' => (int) ($summaryRow['master_resep'] ?? 0),
    ];

    $groupStatement = requireRecipeStatement(sqlsrv_query($conn, "WITH filtered AS (
        SELECT r.*, $groupCode AS group_code, $groupCustomerColor AS group_customer_color,
            $groupStatus AS group_status,
            ROW_NUMBER() OVER (
                PARTITION BY $groupCode, $groupCustomerColor, $groupStatus
                ORDER BY r.created_at DESC, r.id DESC
            ) AS group_row_number,
            COUNT(*) OVER (PARTITION BY $groupCode, $groupCustomerColor, $groupStatus) AS filtered_group_count
        FROM dbo.resep_obat r $whereClause
    )
    SELECT * FROM filtered representative
    WHERE representative.group_row_number = 1
    ORDER BY $orderColumn $orderDirection, representative.id DESC
    OFFSET ? ROWS FETCH NEXT ? ROWS ONLY", array_merge($params, [$start, $length])), 'Gagal mengambil group resep.');

    $representatives = [];
    while ($row = sqlsrv_fetch_array($groupStatement, SQLSRV_FETCH_ASSOC)) {
        $representatives[] = $row;
    }

    $labelsByProductionNumber = recipeSaleLabelsByProductionNumber(
        $conn3,
        array_column($representatives, 'no_cp'),
    );
    $data = [];
    foreach ($representatives as $representative) {
        $productionNumber = trim((string) ($representative['no_cp'] ?? ''));
        $data[] = [
            'id' => (int) $representative['id'],
            'no_cp' => $productionNumber ?: '-',
            'label_jual' => $labelsByProductionNumber[$productionNumber] ?? '-',
            'kode_warna' => $representative['group_code'],
            'cus_color' => $representative['group_customer_color'],
            'color_name' => $representative['color_name'] ?? '-',
            'weight' => $representative['weight'] ?? 0,
            'plan_qty' => $representative['plan_qty'] ?? 0,
            'color_desc' => $representative['color_desc'] ?? '-',
            'status_resep_lipat' => $representative['group_status'],
            'created_at' => formatRecipeDate($representative['created_at'] ?? null),
            'created_by' => $representative['created_by'] ?? '-',
            'has_experiment_source' => !empty($representative['source_experiment_id']),
        ];
    }
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $totalFiltered,
        'statusSummary' => $statusSummary,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    $requestId = bin2hex(random_bytes(8));
    error_log("[$requestId] serverside_resep grouped list: " . $exception->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'draw' => (int) ($_POST['draw'] ?? 0),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'message' => 'Gagal memuat daftar resep.',
        'request_id' => $requestId,
    ]);
}
ob_end_flush();
