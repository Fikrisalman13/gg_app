<?php
session_start();
require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/experiment_visibility_helper.php';
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

$scope = resepExperimentVisibilityScope($conn);
$visWhere = "";
if ($scope === 'APPROVED_ONLY') {
    $visWhere = " AND e.was_approved = 1";
    $where[] = "EXISTS (SELECT 1 FROM dbo.resep_obat_experiment e_vis WHERE e_vis.group_id = g.id AND e_vis.was_approved = 1)";
} elseif ($scope === 'PROCESS_ONLY') {
    $visWhere = " AND e.was_in_process = 1";
    $where[] = "EXISTS (SELECT 1 FROM dbo.resep_obat_experiment e_vis WHERE e_vis.group_id = g.id AND e_vis.was_in_process = 1)";
}

if ($search !== '') {
    $where[] = "(g.soi LIKE ? OR g.no_cp LIKE ? OR di.di_soi LIKE ? OR di.di_no_cp LIKE ? OR di.kode_warna LIKE ? OR di.color_name LIKE ? OR di.resep_prod_code LIKE ? OR di.resep_prod_name LIKE ? OR di.cus_color LIKE ? OR ISNULL(le.experiment_status,'') LIKE ? OR ISNULL(ae.experiment_status,'') LIKE ?)";
    for ($i=0; $i<11; $i++) $params[] = "%$search%";
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$base = "
FROM dbo.resep_obat_experiment_group g
OUTER APPLY (
    SELECT TOP 1 e.id, e.experiment_seq, e.experiment_status
    FROM dbo.resep_obat_experiment e
    WHERE e.group_id = g.id $visWhere
    ORDER BY e.experiment_seq DESC, e.id DESC
) le
OUTER APPLY (
    SELECT TOP 1 e.id, e.experiment_seq, e.experiment_status
    FROM dbo.resep_obat_experiment e
    WHERE e.group_id = g.id AND e.id = g.approved_experiment_id $visWhere
) ae
OUTER APPLY (
    SELECT TOP 1 e.kode_grey, e.mesin, e.kode_warna, e.color_name, e.color_desc,
                  e.resep_prod_code, e.resep_prod_name, e.cus_color,
                  e.created_by, e.created_at,
                  e.soi AS di_soi, e.no_cp AS di_no_cp, e.experiment_status AS di_status
    FROM dbo.resep_obat_experiment e
    WHERE e.group_id = g.id $visWhere
    ORDER BY 
      CASE WHEN g.approved_experiment_id IS NOT NULL AND e.id = g.approved_experiment_id THEN 0
           WHEN e.experiment_status IN ('Approved','Prosess','Success','Lunas') AND (e.soi IS NOT NULL OR e.no_cp IS NOT NULL) THEN 1
           WHEN e.experiment_status IN ('Approved','Prosess','Success','Lunas') THEN 2
           ELSE 3 END,
      e.experiment_seq DESC, e.id DESC
) di
OUTER APPLY (
    SELECT COUNT(*) AS total_experiment
    FROM dbo.resep_obat_experiment e
    WHERE e.group_id = g.id $visWhere
) ec
OUTER APPLY (
    SELECT TOP 1 e.id, e.experiment_seq, e.experiment_status
    FROM dbo.resep_obat_experiment e
    WHERE e.group_id = g.id AND e.experiment_status IN ('Approved','Process','Prosess','Success','Lunas') $visWhere
    ORDER BY CASE e.experiment_status WHEN 'Process' THEN 0 WHEN 'Prosess' THEN 0 WHEN 'Approved' THEN 1 WHEN 'Success' THEN 2 WHEN 'Lunas' THEN 3 ELSE 4 END, e.experiment_seq DESC
) pe
";

try {
    $recordsTotal = 0;
    if ($scope === 'APPROVED_ONLY') {
        $stmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM dbo.resep_obat_experiment_group g WHERE EXISTS (SELECT 1 FROM dbo.resep_obat_experiment e_vis WHERE e_vis.group_id = g.id AND e_vis.was_approved = 1)");
    } elseif ($scope === 'PROCESS_ONLY') {
        $stmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM dbo.resep_obat_experiment_group g WHERE EXISTS (SELECT 1 FROM dbo.resep_obat_experiment e_vis WHERE e_vis.group_id = g.id AND e_vis.was_in_process = 1)");
    } else {
        $stmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM dbo.resep_obat_experiment_group");
    }
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $recordsTotal = (int)$row['total'];

    $stmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total $base $whereSql", $params);
    $recordsFiltered = 0;
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $recordsFiltered = (int)$row['total'];

    $sql = "
        SELECT g.id, g.soi, g.no_cp, g.group_status, g.created_at, g.approved_experiment_id,
               di.kode_warna, di.color_name, di.resep_prod_code, di.resep_prod_name, di.cus_color,
               di.di_soi, di.di_no_cp,
               ISNULL(ec.total_experiment, 0) AS total_experiment,
               ISNULL(le.experiment_seq, 0) AS last_experiment_seq,
               ISNULL(le.experiment_status, '-') AS last_status,
               le.id AS last_experiment_id,
               ae.experiment_seq AS approved_experiment_seq,
               ae.experiment_status AS approved_status,
               pe.experiment_seq AS process_experiment_seq,
               pe.experiment_status AS process_status
        $base $whereSql
        ORDER BY g.created_at DESC, g.id DESC
        OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
    ";
    $queryParams = array_merge($params, [$start, $length]);
    $stmt = sqlsrv_query($conn, $sql, $queryParams);
    if ($stmt === false) throw new Exception(print_r(sqlsrv_errors(), true));

    $groupRows = [];
    $groupIds = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $groupRows[] = $row;
        $groupIds[] = (int)$row['id'];
    }

    $experimentsByGroup = [];
    if ($groupIds) {
        $idPlaceholders = implode(',', array_fill(0, count($groupIds), '?'));
        $expStmt = sqlsrv_query($conn, "
            SELECT group_id, experiment_seq, experiment_status, no_cp
            FROM dbo.resep_obat_experiment e
            WHERE e.group_id IN ($idPlaceholders) $visWhere
            ORDER BY e.group_id, e.experiment_seq ASC, e.id ASC
        ", $groupIds);
        $cpList = [];
        while ($expStmt && $expRow = sqlsrv_fetch_array($expStmt, SQLSRV_FETCH_ASSOC)) {
            $gid = (int)$expRow['group_id'];
            $experimentsByGroup[$gid][] = $expRow;
            $st = trim((string)($expRow['experiment_status'] ?? 'Draft'));
            $cp = strtoupper(trim((string)($expRow['no_cp'] ?? '')));
            if ($cp !== '' && in_array($st, ['Process', 'Prosess'], true)) $cpList[] = $cp;
        }

        $historyStatusMap = [];
        if ($cpList) {
            try {
                if (!isset($conn3) || !($conn3 instanceof PDO)) require_once __DIR__ . '/../../../koneksi3.php';
                $pgPlaceholders = [];
                $pgParams = [];
                foreach (array_unique($cpList) as $idx => $cp) {
                    $key = ':cp_' . $idx;
                    $pgPlaceholders[] = $key;
                    $pgParams[$key] = $cp;
                }
                $pgStmt = $conn3->prepare("
                    WITH latest_cp AS (
                        SELECT UPPER(TRIM(CAST(prdnmbr AS TEXT))) AS cp_no, productionhdid,
                               ROW_NUMBER() OVER (PARTITION BY UPPER(TRIM(CAST(prdnmbr AS TEXT))) ORDER BY prddate DESC NULLS LAST, productionhdid DESC) AS rn
                        FROM pdproductionhd
                        WHERE UPPER(TRIM(CAST(prdnmbr AS TEXT))) IN (" . implode(',', $pgPlaceholders) . ")
                    ), acc_warna AS (
                        SELECT l.cp_no, COALESCE(r.failmsid, 0) AS failmsid, COALESCE(r.fgresult, '') AS fgresult,
                               COALESCE(f.failcode, '') AS failcode, COALESCE(f.faildesc, '') AS faildesc,
                               ROW_NUMBER() OVER (PARTITION BY l.cp_no ORDER BY CASE WHEN r.failmsid IS NOT NULL AND r.failmsid <> 0 THEN 0 ELSE 1 END ASC, r.starttime DESC NULLS LAST, r.productionrtgid DESC) AS rn
                        FROM latest_cp l
                        JOIN pdproductionrtg r ON r.productionhdid = l.productionhdid AND r.rtgmsid = 589
                        LEFT JOIN pdfailms f ON r.failmsid = f.failmsid
                        WHERE l.rn = 1
                    )
                    SELECT cp_no, failmsid, fgresult, failcode, faildesc FROM acc_warna WHERE rn = 1
                ");
                $pgStmt->execute($pgParams);
                while ($pgRow = $pgStmt->fetch(PDO::FETCH_ASSOC)) {
                    $cp = strtoupper(trim((string)($pgRow['cp_no'] ?? '')));
                    $failmsid = (int)($pgRow['failmsid'] ?? 0);
                    $fgResult = strtoupper(trim((string)($pgRow['fgresult'] ?? '')));
                    $hasFailName = trim((string)($pgRow['failcode'] ?? '')) !== '' || trim((string)($pgRow['faildesc'] ?? '')) !== '';
                    if ($failmsid !== 0 && $hasFailName) $historyStatusMap[$cp] = 'Fail';
                    elseif ($fgResult === 'P' && $failmsid === 0 && !$hasFailName) $historyStatusMap[$cp] = 'Pass';
                }
            } catch (Throwable $e) {
                error_log('group list PG status error: ' . $e->getMessage());
            }
        }

        foreach ($experimentsByGroup as $gid => $expRows) {
            foreach ($expRows as $idx => $expRow) {
                $st = trim((string)($expRow['experiment_status'] ?? 'Draft')) ?: 'Draft';
                $cp = strtoupper(trim((string)($expRow['no_cp'] ?? '')));
                if (in_array($st, ['Process', 'Prosess'], true) && isset($historyStatusMap[$cp])) $st = $historyStatusMap[$cp];
                if ($st === 'Prosess') $st = 'Process';
                $experimentsByGroup[$gid][$idx]['display_status'] = $st;
            }
        }
    }

    $aggregateGroupStatus = static function(array $expRows): array {
        $summary = ['Draft'=>0, 'Approved'=>0, 'Process'=>0, 'Pass'=>0, 'Fail'=>0];
        foreach ($expRows as $expRow) {
            $st = trim((string)($expRow['display_status'] ?? ($expRow['experiment_status'] ?? 'Draft'))) ?: 'Draft';
            if ($st === 'Prosess') $st = 'Process';
            if (!isset($summary[$st])) $summary[$st] = 0;
            $summary[$st]++;
        }
        $total = count($expRows);
        if (($summary['Pass'] ?? 0) > 0) $status = 'Pass';
        elseif ($total > 0 && ($summary['Fail'] ?? 0) === $total) $status = 'Fail';
        elseif (($summary['Process'] ?? 0) > 0 || ($summary['Fail'] ?? 0) > 0) $status = 'Process';
        elseif (($summary['Approved'] ?? 0) > 0) $status = 'Approved';
        elseif (($summary['Draft'] ?? 0) > 0) $status = 'Draft';
        else $status = 'Draft';
        return [$status, $summary];
    };

    $data = [];
    foreach ($groupRows as $row) {
        [$groupDisplayStatus, $statusSummary] = $aggregateGroupStatus($experimentsByGroup[(int)$row['id']] ?? []);
        $summaryText = 'Draft ' . (int)($statusSummary['Draft'] ?? 0) . ' • Approved ' . (int)($statusSummary['Approved'] ?? 0) . ' • Process ' . (int)($statusSummary['Process'] ?? 0) . ' • Pass ' . (int)($statusSummary['Pass'] ?? 0) . ' • Fail ' . (int)($statusSummary['Fail'] ?? 0);
        $data[] = [
            'id' => $row['id'],
            'soi' => !empty($row['di_soi']) ? $row['di_soi'] : (!empty($row['soi']) ? $row['soi'] : '-'),
            'no_cp' => !empty($row['di_no_cp']) ? $row['di_no_cp'] : (!empty($row['no_cp']) ? $row['no_cp'] : '-'),
            'kode_warna' => $row['kode_warna'] ?: '-',
            'color_name' => $row['color_name'] ?: '-',
            'resep_prod_code' => $row['resep_prod_code'] ?: '-',
            'resep_prod_name' => $row['resep_prod_name'] ?: '-',
            'cus_color' => $row['cus_color'] ?: '-',
            'group_status' => $row['group_status'] ?: 'Draft',
            'group_display_status' => $groupDisplayStatus,
            'status_summary' => $summaryText,
            'total_experiment' => (int)$row['total_experiment'],
            'last_experiment_seq' => (int)$row['last_experiment_seq'],
            'last_status' => $row['last_status'] ?: '-',
            'last_experiment_id' => $row['last_experiment_id'],
            'approved_experiment_id' => $row['approved_experiment_id'],
            'approved_experiment_seq' => $row['approved_experiment_seq'] ? (int)$row['approved_experiment_seq'] : 0,
            'approved_status' => $row['approved_status'] ?: '-',
            'process_experiment_seq' => $row['process_experiment_seq'] ? (int)$row['process_experiment_seq'] : 0,
            'process_status' => $row['process_status'] ?: '-',
        ];
    }

    echo json_encode(['draw'=>$draw,'recordsTotal'=>$recordsTotal,'recordsFiltered'=>$recordsFiltered,'data'=>$data]);
} catch (Exception $e) {
    echo json_encode(['draw'=>$draw,'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[],'error'=>$e->getMessage()]);
}
