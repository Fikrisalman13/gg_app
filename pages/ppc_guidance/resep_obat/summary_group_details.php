<?php
if (ob_get_level() > 0) ob_clean();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../../koneksi3.php';
require_once __DIR__ . '/label_jual_lookup.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Sesi login telah berakhir.']);
    exit;
}

try {
    $category = strtolower(trim((string) ($_POST['category'] ?? 'total')));
    $allowedCategories = ['shading', 'experiment', 'kesetabilan', 'master_resep', 'total'];
    if (!in_array($category, $allowedCategories, true)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Kategori summary tidak valid.']);
        exit;
    }

    $where = [];
    $params = [];
    $search = trim((string) ($_POST['search'] ?? ''));
    if ($search !== '') {
        $where[] = "(r.no_cp LIKE ? OR r.kode_warna LIKE ? OR r.cus_color LIKE ? OR r.color_name LIKE ? OR r.lot_no LIKE ? OR r.created_by LIKE ?)";
        array_push($params, ...array_fill(0, 6, "%{$search}%"));
    }
    if (!empty($_POST['startDate']) && !empty($_POST['endDate'])) {
        $where[] = '(r.created_at >= ? AND r.created_at <= ?)';
        $params[] = $_POST['startDate'] . ' 00:00:00';
        $params[] = $_POST['endDate'] . ' 23:59:59';
    }
    $statusFilters = $_POST['statusFilter'] ?? [];
    $statusFilters = is_array($statusFilters) ? $statusFilters : [$statusFilters];
    $statusFilters = array_values(array_filter(array_map('trim', $statusFilters), static fn($value) => $value !== ''));
    if ($statusFilters) {
        $where[] = 'r.status_resep_lipat IN (' . implode(',', array_fill(0, count($statusFilters), '?')) . ')';
        array_push($params, ...$statusFilters);
    }
    $cusColor = trim((string) ($_POST['cusColorFilter'] ?? ''));
    if ($cusColor !== '') {
        $where[] = 'r.cus_color LIKE ?';
        $params[] = "%{$cusColor}%";
    }
    $labelFilters = $_POST['labelJualFilter'] ?? [];
    $labelFilters = is_array($labelFilters) ? $labelFilters : [$labelFilters];
    $labelFilters = array_values(array_unique(array_filter(array_map('trim', $labelFilters))));
    if ($labelFilters) {
        $productionNumbers = [];
        foreach ($labelFilters as $label) array_push($productionNumbers, ...recipeLocalProductionNumbersBySaleLabel($conn, $conn3, $label));
        $productionNumbers = array_values(array_unique($productionNumbers));
        if (!$productionNumbers) {
            $where[] = '1 = 0';
        } else {
            $chunks = [];
            foreach (array_chunk($productionNumbers, 500) as $chunk) {
                $chunks[] = 'LTRIM(RTRIM(r.no_cp)) IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')';
                array_push($params, ...$chunk);
            }
            $where[] = '(' . implode(' OR ', $chunks) . ')';
        }
    }

    $normalizedStatus = "LOWER(LTRIM(RTRIM(COALESCE(r.status_resep_lipat, ''))))";
    $categoryConditions = [
        'shading' => "$normalizedStatus = 'shading'",
        'experiment' => "$normalizedStatus = 'experiment'",
        'kesetabilan' => "$normalizedStatus IN ('kestabilan', 'kesetabilan')",
        'master_resep' => "$normalizedStatus IN ('master resep', 'master')",
    ];
    if (isset($categoryConditions[$category])) $where[] = $categoryConditions[$category];
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $groupCode = "COALESCE(NULLIF(LTRIM(RTRIM(r.kode_warna)), ''), '-')";
    $groupCusColor = "COALESCE(NULLIF(LTRIM(RTRIM(r.cus_color)), ''), '-')";
    $groupStatus = "COALESCE(NULLIF(LTRIM(RTRIM(r.status_resep_lipat)), ''), '-')";

    $sql = "WITH filtered AS (
        SELECT r.id, r.no_cp, r.kode_warna, r.cus_color, r.color_name, r.status_resep_lipat,
            r.weight, r.plan_qty, r.created_at, r.created_by,
            $groupCode AS group_code, $groupCusColor AS group_cus_color, $groupStatus AS group_status,
            COUNT(*) OVER (PARTITION BY $groupCode, $groupCusColor, $groupStatus) AS member_count
        FROM dbo.resep_obat r $whereSql
    )
    SELECT * FROM filtered
    ORDER BY member_count DESC, group_status, group_code, group_cus_color, created_at DESC, id DESC";
    $statement = sqlsrv_query($conn, $sql, $params);
    if (!$statement) throw new RuntimeException('Gagal mengambil rincian kelompok.');

    $groups = [];
    $rawTotal = 0;
    while ($row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC)) {
        $rawTotal++;
        $key = $row['group_code'] . "\x1f" . $row['group_cus_color'] . "\x1f" . $row['group_status'];
        if (!isset($groups[$key])) {
            $groups[$key] = ['kode_warna' => $row['group_code'], 'cus_color' => $row['group_cus_color'], 'status' => $row['group_status'], 'member_count' => (int) $row['member_count'], 'members' => []];
        }
        $createdAt = $row['created_at'];
        $groups[$key]['members'][] = [
            'id' => (int) $row['id'],
            'no_cp' => trim((string) ($row['no_cp'] ?? '')) ?: '-',
            'color_name' => trim((string) ($row['color_name'] ?? '')) ?: '-',
            'weight' => $row['weight'] ?? 0,
            'plan_qty' => $row['plan_qty'] ?? 0,
            'created_at' => $createdAt instanceof DateTimeInterface ? $createdAt->format('d-m-Y H:i') : ($createdAt ?: '-'),
            'created_by' => trim((string) ($row['created_by'] ?? '')) ?: '-',
        ];
    }
    $groupList = array_values($groups);
    echo json_encode([
        'ok' => true,
        'category' => $category,
        'raw_total' => $rawTotal,
        'grouped_total' => count($groupList),
        'collapsed_total' => $rawTotal - count($groupList),
        'duplicate_groups' => array_values(array_filter($groupList, static fn($group) => $group['member_count'] > 1)),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    $requestId = bin2hex(random_bytes(8));
    error_log("[$requestId] PPC summary group details: " . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Gagal memuat rincian summary.', 'request_id' => $requestId]);
}
