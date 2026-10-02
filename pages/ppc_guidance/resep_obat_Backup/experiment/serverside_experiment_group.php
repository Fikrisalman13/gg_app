<?php
session_start();
require_once __DIR__ . '/../../../../koneksi.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['draw'=>(int)($_POST['draw']??1),'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[],'error'=>'Unauthorized']);
    exit;
}

$draw = (int)($_POST['draw'] ?? 1);
$start = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 10);
$search = trim($_POST['search']['value'] ?? '');
$startDate = $_POST['startDate'] ?? '';
$endDate = $_POST['endDate'] ?? '';

$params = [];
$where = [];
if ($startDate !== '') { $where[] = "CAST(g.created_at AS DATE) >= ?"; $params[] = $startDate; }
if ($endDate !== '') { $where[] = "CAST(g.created_at AS DATE) <= ?"; $params[] = $endDate; }
if ($search !== '') {
    $where[] = "(g.soi LIKE ? OR g.no_cp LIKE ? OR di.kode_warna LIKE ? OR di.color_name LIKE ? OR di.resep_prod_code LIKE ? OR di.resep_prod_name LIKE ? OR di.cus_color LIKE ? OR ISNULL(le.experiment_status,'') LIKE ? OR ISNULL(ae.experiment_status,'') LIKE ?)";
    for ($i=0; $i<9; $i++) $params[] = "%$search%";
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$base = "
FROM dbo.resep_obat_experiment_group g
OUTER APPLY (
    SELECT TOP 1 e.id, e.experiment_seq, e.experiment_status
    FROM dbo.resep_obat_experiment e
    WHERE e.group_id = g.id
    ORDER BY e.experiment_seq DESC, e.id DESC
) le
OUTER APPLY (
    SELECT TOP 1 e.id, e.experiment_seq, e.experiment_status
    FROM dbo.resep_obat_experiment e
    WHERE e.group_id = g.id AND e.id = g.approved_experiment_id
) ae
OUTER APPLY (
    SELECT TOP 1 e.*
    FROM dbo.resep_obat_experiment e
    WHERE e.group_id = g.id
      AND ((g.approved_experiment_id IS NOT NULL AND e.id = g.approved_experiment_id) OR g.approved_experiment_id IS NULL)
    ORDER BY CASE WHEN e.id = g.approved_experiment_id THEN 0 ELSE 1 END, e.experiment_seq DESC, e.id DESC
) di
OUTER APPLY (
    SELECT COUNT(*) AS total_experiment
    FROM dbo.resep_obat_experiment e2
    WHERE e2.group_id = g.id
) ec
";

try {
    $stmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM dbo.resep_obat_experiment_group");
    $recordsTotal = 0;
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $recordsTotal = (int)$row['total'];

    $stmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total $base $whereSql", $params);
    $recordsFiltered = 0;
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $recordsFiltered = (int)$row['total'];

    $sql = "
        SELECT g.id, g.soi, g.no_cp, g.group_status, g.created_at, g.approved_experiment_id,
               di.kode_warna, di.color_name, di.resep_prod_code, di.resep_prod_name, di.cus_color,
               ISNULL(ec.total_experiment, 0) AS total_experiment,
               ISNULL(le.experiment_seq, 0) AS last_experiment_seq,
               ISNULL(le.experiment_status, '-') AS last_status,
               le.id AS last_experiment_id,
               ae.experiment_seq AS approved_experiment_seq,
               ae.experiment_status AS approved_status
        $base $whereSql
        ORDER BY g.created_at DESC, g.id DESC
        OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
    ";
    $queryParams = array_merge($params, [$start, $length]);
    $stmt = sqlsrv_query($conn, $sql, $queryParams);
    if ($stmt === false) throw new Exception(print_r(sqlsrv_errors(), true));

    $data = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $data[] = [
            'id' => $row['id'],
            'soi' => $row['soi'] ?: '-',
            'no_cp' => $row['no_cp'] ?: '-',
            'kode_warna' => $row['kode_warna'] ?: '-',
            'color_name' => $row['color_name'] ?: '-',
            'resep_prod_code' => $row['resep_prod_code'] ?: '-',
            'resep_prod_name' => $row['resep_prod_name'] ?: '-',
            'cus_color' => $row['cus_color'] ?: '-',
            'group_status' => $row['group_status'] ?: 'Draft',
            'total_experiment' => (int)$row['total_experiment'],
            'last_experiment_seq' => (int)$row['last_experiment_seq'],
            'last_status' => $row['last_status'] ?: '-',
            'last_experiment_id' => $row['last_experiment_id'],
            'approved_experiment_id' => $row['approved_experiment_id'],
            'approved_experiment_seq' => $row['approved_experiment_seq'] ? (int)$row['approved_experiment_seq'] : 0,
            'approved_status' => $row['approved_status'] ?: '-',
        ];
    }

    echo json_encode(['draw'=>$draw,'recordsTotal'=>$recordsTotal,'recordsFiltered'=>$recordsFiltered,'data'=>$data]);
} catch (Exception $e) {
    echo json_encode(['draw'=>$draw,'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[],'error'=>$e->getMessage()]);
}
