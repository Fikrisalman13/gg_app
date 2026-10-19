<?php
// pages/ppc_guidance_v2/edit_otomatis/serverside.php
if (ob_get_level() > 0) {
    ob_clean();
}
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../koneksi.php';
header('Content-Type: application/json');

if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Sesi login telah berakhir.']);
    exit;
}

try {
    $start = max(0, (int)($_POST['start'] ?? 0));
    $length = max(1, min(100, (int)($_POST['length'] ?? 10)));
    $draw = (int)($_POST['draw'] ?? 1);
    $search = trim((string)($_POST['search']['value'] ?? ''));

    // Base condition: hanya resep yang memiliki No CP dan status Shading, Master Resep, Kestabilan
    $baseConditions = [
        "r.no_cp IS NOT NULL",
        "LTRIM(RTRIM(r.no_cp)) <> ''",
        "r.no_cp <> '-'",
        "r.status_resep_lipat IN ('Shading', 'Master Resep', 'Master', 'Kestabilan', 'Kesetabilan')"
    ];
    $baseWhereClause = "WHERE " . implode(' AND ', $baseConditions);

    // Filter tambahan dari request pengguna
    $filterConditions = $baseConditions;
    $params = [];

    // Search query
    if ($search !== '') {
        $filterConditions[] = "(r.no_cp LIKE ? OR r.kode_warna LIKE ? OR r.cus_color LIKE ? OR r.color_name LIKE ? OR r.rtg_code LIKE ? OR r.mesin LIKE ?)";
        for ($i = 0; $i < 6; $i++) {
            $params[] = "%{$search}%";
        }
    }

    // Filter status khusus jika dipilih
    $statusFilter = $_POST['statusFilter'] ?? [];
    if (!empty($statusFilter)) {
        if (!is_array($statusFilter)) {
            $statusFilter = [$statusFilter];
        }
        $validStatuses = [];
        foreach ($statusFilter as $sf) {
            $sf = trim((string)$sf);
            if ($sf !== '') {
                if (strtolower($sf) === 'kestabilan' || strtolower($sf) === 'kesetabilan') {
                    $validStatuses[] = 'Kestabilan';
                    $validStatuses[] = 'Kesetabilan';
                } elseif (strtolower($sf) === 'master resep' || strtolower($sf) === 'master') {
                    $validStatuses[] = 'Master Resep';
                    $validStatuses[] = 'Master';
                } else {
                    $validStatuses[] = $sf;
                }
            }
        }
        $validStatuses = array_values(array_unique($validStatuses));
        if (!empty($validStatuses)) {
            $inPlaceholders = implode(',', array_fill(0, count($validStatuses), '?'));
            $filterConditions[] = "r.status_resep_lipat IN ($inPlaceholders)";
            foreach ($validStatuses as $st) {
                $params[] = $st;
            }
        }
    }

    // Filter tanggal update / create jika ada
    if (!empty($_POST['startDate']) && !empty($_POST['endDate'])) {
        $filterConditions[] = "(COALESCE(r.updated_at, r.created_at) >= ? AND COALESCE(r.updated_at, r.created_at) <= ?)";
        $params[] = $_POST['startDate'] . ' 00:00:00';
        $params[] = $_POST['endDate'] . ' 23:59:59';
    }

    $filterWhereClause = "WHERE " . implode(' AND ', $filterConditions);

    // 1. Total records (hanya base condition)
    $sqlTotal = "SELECT COUNT(*) AS total FROM dbo.resep_obat_v2 r $baseWhereClause";
    $stmtTotal = sqlsrv_query($conn, $sqlTotal);
    $totalRecords = 0;
    if ($stmtTotal && ($rowTotal = sqlsrv_fetch_array($stmtTotal, SQLSRV_FETCH_ASSOC))) {
        $totalRecords = (int)$rowTotal['total'];
    }

    // 2. Filtered records count
    $sqlFiltered = "SELECT COUNT(*) AS total FROM dbo.resep_obat_v2 r $filterWhereClause";
    $stmtFiltered = sqlsrv_query($conn, $sqlFiltered, $params);
    $totalFiltered = 0;
    if ($stmtFiltered && ($rowFiltered = sqlsrv_fetch_array($stmtFiltered, SQLSRV_FETCH_ASSOC))) {
        $totalFiltered = (int)$rowFiltered['total'];
    }

    // 3. Ringkasan Status (dari filter aktif)
    $sqlSummary = "SELECT 
        SUM(CASE WHEN LOWER(LTRIM(RTRIM(COALESCE(r.status_resep_lipat, '')))) = 'shading' THEN 1 ELSE 0 END) AS shading,
        SUM(CASE WHEN LOWER(LTRIM(RTRIM(COALESCE(r.status_resep_lipat, '')))) IN ('kestabilan', 'kesetabilan') THEN 1 ELSE 0 END) AS kesetabilan,
        SUM(CASE WHEN LOWER(LTRIM(RTRIM(COALESCE(r.status_resep_lipat, '')))) IN ('master resep', 'master') THEN 1 ELSE 0 END) AS master_resep
        FROM dbo.resep_obat_v2 r $filterWhereClause";
    $stmtSummary = sqlsrv_query($conn, $sqlSummary, $params);
    $summaryRow = ($stmtSummary && ($sr = sqlsrv_fetch_array($stmtSummary, SQLSRV_FETCH_ASSOC))) ? $sr : [];
    $statusSummary = [
        'shading'      => (int)($summaryRow['shading'] ?? 0),
        'kesetabilan'  => (int)($summaryRow['kesetabilan'] ?? 0),
        'master_resep' => (int)($summaryRow['master_resep'] ?? 0),
        'total'        => $totalFiltered
    ];

    // 4. Pengurutan kolom
    $orderableColumns = [
        1 => 'r.id',
        2 => 'r.no_cp',
        3 => 'r.kode_warna',
        4 => 'r.color_name',
        5 => 'r.cus_color',
        6 => 'r.status_resep_lipat',
        7 => 'r.rtg_code',
        8 => 'detail_count',
        9 => 'COALESCE(r.updated_at, r.created_at)'
    ];
    $orderColIdx = (int)($_POST['order'][0]['column'] ?? 1);
    $orderCol = $orderableColumns[$orderColIdx] ?? 'r.id';
    $orderDir = strtolower((string)($_POST['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

    // 5. Query Data Halaman dengan detail_count
    $sqlData = "SELECT r.id, r.no_cp, r.kode_warna, r.color_name, r.cus_color, r.status_resep_lipat,
                       r.rtg_code, r.rtg_name, r.mesin, r.vlot, r.plan_qty, r.weight,
                       r.created_at, r.created_by, r.updated_at, r.updated_by,
                       (SELECT COUNT(*) FROM dbo.resep_obat_detail_v2 d WHERE d.id_resep = r.id) AS detail_count
                FROM dbo.resep_obat_v2 r
                $filterWhereClause
                ORDER BY $orderCol $orderDir
                OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

    $dataParams = array_merge($params, [$start, $length]);
    $stmtData = sqlsrv_query($conn, $sqlData, $dataParams);

    $formatDt = function($val): string {
        if ($val instanceof DateTimeInterface) {
            return $val->format('d-m-Y H:i');
        }
        return $val ? date('d-m-Y H:i', strtotime((string)$val)) : '-';
    };

    $data = [];
    if ($stmtData) {
        $no = $start + 1;
        while ($row = sqlsrv_fetch_array($stmtData, SQLSRV_FETCH_ASSOC)) {
            $updatedTime = !empty($row['updated_at']) ? $formatDt($row['updated_at']) : (!empty($row['created_at']) ? $formatDt($row['created_at']) : '-');
            $updatedUser = $row['updated_by'] ?: ($row['created_by'] ?: '-');

            $data[] = [
                'DT_RowId'            => 'row_' . $row['id'],
                'no'                  => $no++,
                'id'                  => (int)$row['id'],
                'no_cp'               => htmlspecialchars($row['no_cp'] ?? ''),
                'kode_warna'          => htmlspecialchars($row['kode_warna'] ?? ''),
                'color_name'          => htmlspecialchars($row['color_name'] ?? ''),
                'cus_color'           => htmlspecialchars($row['cus_color'] ?? '-'),
                'status_resep_lipat'  => htmlspecialchars($row['status_resep_lipat'] ?? '-'),
                'rtg_code'            => htmlspecialchars($row['rtg_code'] ?? '-'),
                'rtg_name'            => htmlspecialchars($row['rtg_name'] ?? '-'),
                'mesin'               => htmlspecialchars($row['mesin'] ?? '-'),
                'vlot'                => (float)($row['vlot'] ?? 0),
                'plan_qty'            => (float)($row['plan_qty'] ?? 0),
                'weight'              => (float)($row['weight'] ?? 0),
                'detail_count'        => (int)$row['detail_count'],
                'updated_info'        => $updatedTime . '<br><small class="text-muted">' . htmlspecialchars($updatedUser) . '</small>'
            ];
        }
    }

    echo json_encode([
        'draw'            => $draw,
        'recordsTotal'    => $totalRecords,
        'recordsFiltered' => $totalFiltered,
        'data'            => $data,
        'statusSummary'   => $statusSummary
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'draw'            => (int)($_POST['draw'] ?? 1),
        'recordsTotal'    => 0,
        'recordsFiltered' => 0,
        'data'            => [],
        'error'           => $e->getMessage()
    ]);
}
