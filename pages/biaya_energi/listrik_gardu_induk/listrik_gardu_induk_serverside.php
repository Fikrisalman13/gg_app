<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
header('Content-Type: application/json');

$menuId = 236;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) {
    echo json_encode(['draw' => intval($_POST['draw'] ?? 1), 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Anda tidak memiliki hak melihat data.']);
    exit;
}

$draw = intval($_POST['draw'] ?? 1);
$start = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);
$search = trim($_POST['search']['value'] ?? '');
$startDate = trim($_POST['start_date'] ?? '');
$endDate = trim($_POST['end_date'] ?? '');

$where = "WHERE 1=1";
$params = [];
if ($startDate !== '') { $where .= " AND CAST(x.tanggal AS DATE) >= ?"; $params[] = $startDate; }
if ($endDate !== '') { $where .= " AND CAST(x.tanggal AS DATE) <= ?"; $params[] = $endDate; }
if ($search !== '') {
    $where .= " AND (CONVERT(VARCHAR(10), x.tanggal, 120) LIKE ? OR CONVERT(VARCHAR(50), x.lvbp_kwh) LIKE ? OR CONVERT(VARCHAR(50), x.vbp_kwh) LIKE ? OR CONVERT(VARCHAR(50), x.kvarh) LIKE ? OR x.ket LIKE ? OR COALESCE(x.updateby, x.creatby, '') LIKE ?)";
    $sp = "%{$search}%";
    $params = array_merge($params, [$sp,$sp,$sp,$sp,$sp,$sp]);
}

$baseFrom = "FROM dbo.listrik_gardu_induk_harian x";

$countAllStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total " . $baseFrom);
$totalRecords = 0;
if ($countAllStmt && ($r = sqlsrv_fetch_array($countAllStmt, SQLSRV_FETCH_ASSOC))) $totalRecords = (int)$r['total'];
if ($countAllStmt) sqlsrv_free_stmt($countAllStmt);

$countFilteredStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total " . $baseFrom . " " . $where, $params);
$totalFiltered = 0;
if ($countFilteredStmt && ($r = sqlsrv_fetch_array($countFilteredStmt, SQLSRV_FETCH_ASSOC))) $totalFiltered = (int)$r['total'];
if ($countFilteredStmt) sqlsrv_free_stmt($countFilteredStmt);

$sql = "WITH cte AS (
            SELECT x.id, x.tanggal, x.lvbp_kwh, x.vbp_kwh, x.kvarh, x.cos_phi, x.faktor_kali, x.rp_per_kwh, x.pf_standar, x.kapasitas_kva, x.ket,
                   COALESCE(x.updateby, x.creatby, '') AS created_by,
                   LEAD(x.lvbp_kwh) OVER (ORDER BY CAST(x.tanggal AS DATE), x.id) AS lvbp_next,
                   LEAD(x.vbp_kwh) OVER (ORDER BY CAST(x.tanggal AS DATE), x.id) AS vbp_next
            FROM dbo.listrik_gardu_induk_harian x
         )
         SELECT cte.id, cte.tanggal, cte.lvbp_kwh, cte.vbp_kwh, cte.kvarh, cte.cos_phi, cte.rp_per_kwh, cte.ket, cte.created_by,
                CASE WHEN cte.lvbp_next IS NULL OR cte.vbp_next IS NULL THEN NULL
                     ELSE (((cte.lvbp_next - cte.lvbp_kwh) * cte.faktor_kali) + ((cte.vbp_next - cte.vbp_kwh) * cte.faktor_kali)) END AS total_daya_perday_kw,
                CASE WHEN cte.lvbp_next IS NULL OR cte.vbp_next IS NULL THEN NULL
                     ELSE ((((cte.lvbp_next - cte.lvbp_kwh) * cte.faktor_kali) + ((cte.vbp_next - cte.vbp_kwh) * cte.faktor_kali)) / 24.0) END AS total_daya_perjam_kwh,
                CASE WHEN cte.lvbp_next IS NULL OR cte.vbp_next IS NULL THEN NULL
                     ELSE ((((cte.lvbp_next - cte.lvbp_kwh) * cte.faktor_kali) + ((cte.vbp_next - cte.vbp_kwh) * cte.faktor_kali)) * cte.rp_per_kwh) END AS biaya_perday_rp,
                CASE WHEN cte.lvbp_next IS NULL OR cte.vbp_next IS NULL OR ISNULL(cte.pf_standar,0)=0 THEN NULL
                     ELSE (((((cte.lvbp_next - cte.lvbp_kwh) * cte.faktor_kali) + ((cte.vbp_next - cte.vbp_kwh) * cte.faktor_kali)) / 24.0) / cte.pf_standar) END AS kva_pln_perjam,
                CASE WHEN cte.lvbp_next IS NULL OR cte.vbp_next IS NULL OR ISNULL(cte.kapasitas_kva,0)=0 THEN NULL
                     ELSE ((((((cte.lvbp_next - cte.lvbp_kwh) * cte.faktor_kali) + ((cte.vbp_next - cte.vbp_kwh) * cte.faktor_kali)) / 24.0) / cte.kapasitas_kva) * 100.0) END AS efisiensi_persen
         FROM cte
         WHERE cte.id IN (SELECT x.id FROM dbo.listrik_gardu_induk_harian x " . $where . ")
         ORDER BY CAST(cte.tanggal AS DATE) DESC, cte.id DESC
         OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$dataParams = $params; $dataParams[] = $start; $dataParams[] = $length;
$stmt = sqlsrv_query($conn, $sql, $dataParams);
if ($stmt === false) {
    echo json_encode(['draw'=>$draw,'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[],'error'=>'Gagal mengambil data.']);
    exit;
}

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tgl = $row['tanggal'];
    if ($tgl instanceof DateTime) { $tanggalRaw = $tgl->format('Y-m-d'); $tanggalFmt = $tgl->format('d-m-Y'); }
    else { $tanggalRaw = (string)$tgl; $tanggalFmt = $tanggalRaw !== '' ? date('d-m-Y', strtotime($tanggalRaw)) : ''; }

    $data[] = [
        'id' => $row['id'] ?? null,
        'tanggal' => $tanggalRaw,
        'tanggal_formatted' => $tanggalFmt,
        'lvbp_kwh' => $row['lvbp_kwh'] ?? 0,
        'vbp_kwh' => $row['vbp_kwh'] ?? 0,
        'kvarh' => $row['kvarh'] ?? 0,
        'cos_phi' => $row['cos_phi'] ?? 0,
        'rp_per_kwh' => $row['rp_per_kwh'] ?? 0,
        'total_daya_perday_kw' => $row['total_daya_perday_kw'],
        'total_daya_perjam_kwh' => $row['total_daya_perjam_kwh'],
        'biaya_perday_rp' => $row['biaya_perday_rp'],
        'kva_pln_perjam' => $row['kva_pln_perjam'],
        'efisiensi_persen' => $row['efisiensi_persen'],
        'ket' => $row['ket'] ?? '',
        'created_by' => $row['created_by'] ?? ''
    ];
}
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['draw'=>$draw,'recordsTotal'=>$totalRecords,'recordsFiltered'=>$totalFiltered,'data'=>$data]);
