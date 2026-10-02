<?php
// pages/resep_obat/serverside_resep.php
// ====== CLEAN OUTPUT BUFFER ======
if (ob_get_level()) ob_clean();
ob_start();

session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

error_reporting(0);
ini_set('display_errors', 0);

function formatDt($value) {
    if ($value instanceof DateTime) return $value->format('d-m-Y H:i');
    if ($value) return date('d-m-Y H:i', strtotime($value));
    return '-';
}

function buildVariantLabels($rows) {
    $counts = [];
    foreach ($rows as $row) {
        $base = trim((string)($row['variant_name'] ?? '')) ?: 'Varian';
        $counts[$base] = ($counts[$base] ?? 0) + 1;
    }

    $seen = [];
    foreach ($rows as &$row) {
        $base = trim((string)($row['variant_name'] ?? '')) ?: 'Varian';
        $seen[$base] = ($seen[$base] ?? 0) + 1;
        $row['label'] = $counts[$base] > 1 ? $base . ' #' . $seen[$base] : $base;
    }
    unset($row);
    return $rows;
}

try {
    $start  = (int)($_POST['start'] ?? 0);
    $length = (int)($_POST['length'] ?? 10);
    $search = $_POST['search']['value'] ?? '';
    $draw   = $_POST['draw'] ?? 1;

    $orderableColumns = [
        1 => 'no_cp',
        2 => 'kode_warna',
        3 => 'color_name',
        4 => 'cus_color',
        5 => 'weight',
        6 => 'plan_qty',
        7 => 'color_desc',
        8 => 'status_resep_lipat',
        9 => 'created_at',
        10 => 'updated_at',
    ];
    $orderIndex = (int)($_POST['order'][0]['column'] ?? 9);
    $orderColumn = $orderableColumns[$orderIndex] ?? 'created_at';
    $orderDirection = strtolower($_POST['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

    $whereConditions = ["r.status_resep_lipat = ?"];
    $params = ['Master Resep'];

    $cusColor = trim($_POST['cusColor'] ?? '');
    if ($cusColor !== '') {
        $whereConditions[] = "r.cus_color LIKE ?";
        $params[] = "%{$cusColor}%";
    }

    if (!empty($search)) {
        $whereConditions[] = "(r.no_cp LIKE ? OR r.kode_warna LIKE ? OR r.cus_color LIKE ? OR r.color_name LIKE ? OR r.lot_no LIKE ? OR r.created_by LIKE ?)";
        $searchTerm = "%{$search}%";
        for ($i = 0; $i < 6; $i++) $params[] = $searchTerm;
    }

    if (!empty($_POST['startDate']) && !empty($_POST['endDate'])) {
        $whereConditions[] = "(r.created_at >= ? AND r.created_at <= ?)";
        $params[] = $_POST['startDate'] . ' 00:00:00';
        $params[] = $_POST['endDate'] . ' 23:59:59';
    }

    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    $groupCols = "r.kode_warna, r.cus_color, ISNULL(r.status_resep_lipat, '')";

    $sqlTotal = "SELECT COUNT(*) AS total FROM (
        SELECT r.kode_warna, r.cus_color, ISNULL(r.status_resep_lipat, '') AS status_key
        FROM dbo.resep_obat r
        WHERE r.status_resep_lipat = ?
        GROUP BY r.kode_warna, r.cus_color, ISNULL(r.status_resep_lipat, '')
    ) g";
    $stmtTotal = sqlsrv_query($conn, $sqlTotal, ['Master Resep']);
    $totalRecords = 0;
    if ($stmtTotal && $row = sqlsrv_fetch_array($stmtTotal, SQLSRV_FETCH_ASSOC)) $totalRecords = (int)$row['total'];
    if ($stmtTotal) sqlsrv_free_stmt($stmtTotal);

    $sqlFiltered = "SELECT COUNT(*) AS total FROM (
        SELECT r.kode_warna, r.cus_color, ISNULL(r.status_resep_lipat, '') AS status_key
        FROM dbo.resep_obat r
        $whereClause
        GROUP BY $groupCols
    ) g";
    $stmtFiltered = sqlsrv_query($conn, $sqlFiltered, $params);
    $totalFiltered = 0;
    if ($stmtFiltered && $row = sqlsrv_fetch_array($stmtFiltered, SQLSRV_FETCH_ASSOC)) $totalFiltered = (int)$row['total'];
    if ($stmtFiltered) sqlsrv_free_stmt($stmtFiltered);

    // Distinct Cus Color summary follows active search/date/Cus Color filters.
    $sqlCusColorTotal = "SELECT COUNT(DISTINCT NULLIF(LTRIM(RTRIM(r.cus_color)), '')) AS total " .
        "FROM dbo.resep_obat r " . $whereClause;
    $stmtCusColorTotal = sqlsrv_query($conn, $sqlCusColorTotal, $params);
    $totalCusColor = 0;
    if ($stmtCusColorTotal && $row = sqlsrv_fetch_array($stmtCusColorTotal, SQLSRV_FETCH_ASSOC)) {
        $totalCusColor = (int) $row['total'];
    }
    if ($stmtCusColorTotal) sqlsrv_free_stmt($stmtCusColorTotal);

    $sqlData = "WITH grouped AS (
        SELECT
            r.*,
            ISNULL(r.status_resep_lipat, '') AS status_key,
            ROW_NUMBER() OVER (
                PARTITION BY r.kode_warna, r.cus_color, ISNULL(r.status_resep_lipat, '')
                ORDER BY r.created_at DESC, r.id DESC
            ) AS rn,
            COUNT(*) OVER (
                PARTITION BY r.kode_warna, r.cus_color, ISNULL(r.status_resep_lipat, '')
            ) AS variant_count
        FROM dbo.resep_obat r
        $whereClause
    )
    SELECT
        id, no_cp, kode_warna, cus_color, color_name, lot_no, weight, plan_qty, color_desc,
        resep_no, is_manual, status_resep_lipat, created_at, created_by, updated_at, updated_by,
        variant_count
    FROM grouped
    WHERE rn = 1
    ORDER BY $orderColumn $orderDirection, id DESC
    OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

    $paramsData = array_merge($params, [$start, $length]);
    $stmtData = sqlsrv_query($conn, $sqlData, $paramsData);

    $data = [];
    if ($stmtData) {
        while ($row = sqlsrv_fetch_array($stmtData, SQLSRV_FETCH_ASSOC)) {
            $variantSql = "SELECT
                    v.id,
                    COALESCE(NULLIF(LTRIM(RTRIM(v.mesin)), ''), NULLIF(LTRIM(RTRIM(fm.machine_name)), ''), 'Varian') AS variant_name,
                    v.no_cp,
                    v.created_at
                FROM dbo.resep_obat v
                OUTER APPLY (
                    SELECT TOP 1 m.machine_name
                    FROM dbo.resep_obat_machines m
                    WHERE m.id_resep = v.id
                    ORDER BY m.id ASC
                ) fm
                WHERE v.kode_warna = ?
                  AND v.cus_color = ?
                  AND ISNULL(v.status_resep_lipat, '') = ISNULL(?, '')
                ORDER BY v.created_at ASC, v.id ASC";
            $variantParams = [$row['kode_warna'], $row['cus_color'], $row['status_resep_lipat'] ?? ''];
            $stmtV = sqlsrv_query($conn, $variantSql, $variantParams);
            $variants = [];
            if ($stmtV) {
                while ($v = sqlsrv_fetch_array($stmtV, SQLSRV_FETCH_ASSOC)) {
                    $variants[] = [
                        'id' => $v['id'],
                        'variant_name' => $v['variant_name'],
                        'no_cp' => $v['no_cp'] ?? '-',
                        'is_active' => (int)$v['id'] === (int)$row['id'],
                    ];
                }
                sqlsrv_free_stmt($stmtV);
            }
            $variants = buildVariantLabels($variants);

            $data[] = [
                'id' => $row['id'],
                'no_cp' => !empty($row['no_cp']) ? $row['no_cp'] : '-',
                'kode_warna' => $row['kode_warna'] ?? '-',
                'cus_color' => $row['cus_color'] ?? '-',
                'color_name' => $row['color_name'] ?? '-',
                'lot_no' => $row['lot_no'] ?? '-',
                'weight' => $row['weight'] ?? 0,
                'plan_qty' => $row['plan_qty'] ?? 0,
                'color_desc' => $row['color_desc'] ?? '-',
                'resep_no' => $row['resep_no'] ?? '',
                'is_manual' => $row['is_manual'] ?? 0,
                'status_resep_lipat' => $row['status_resep_lipat'] ?? '-',
                'created_at' => formatDt($row['created_at']),
                'created_by' => $row['created_by'] ?? '-',
                'updated_at' => formatDt($row['updated_at']),
                'updated_by' => $row['updated_by'] ?? '-',
                'variant_count' => (int)($row['variant_count'] ?? count($variants)),
                'variants' => $variants,
            ];
        }
        sqlsrv_free_stmt($stmtData);
    }

    $response = [
        'draw' => intval($draw),
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $totalFiltered,
        'totalCusColor' => $totalCusColor,
        'data' => $data,
    ];
} catch (Exception $e) {
    $response = [
        'draw' => intval($draw ?? 0),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Terjadi kesalahan sistem: ' . $e->getMessage(),
    ];
}

ob_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
?>
