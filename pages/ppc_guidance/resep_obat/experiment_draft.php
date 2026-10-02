<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../resep_obat/experiment/experiment_visibility_helper.php';
header('Content-Type: application/json; charset=utf-8');
$requestId = bin2hex(random_bytes(8));

function respondDraft(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($_SESSION['UserName'])) respondDraft(401, ['ok' => false, 'message' => 'Sesi login berakhir.', 'request_id' => $requestId]);
$permissionStatement = sqlsrv_query($conn, 'SELECT CanView, CanAdd FROM dbo.SMGroupTrustee WHERE GroupId=? AND MenuId=215', [$_SESSION['GroupId'] ?? 0]);
$permission = $permissionStatement ? sqlsrv_fetch_array($permissionStatement, SQLSRV_FETCH_ASSOC) : null;
if (($permission['CanView'] ?? 0) != 1 || ($permission['CanAdd'] ?? 0) != 1) respondDraft(403, ['ok' => false, 'message' => 'Tidak memiliki hak menambah resep.', 'request_id' => $requestId]);

$action = $_GET['action'] ?? 'search';
if ($action === 'search') {
    $cusColor = trim((string)($_GET['cus_color'] ?? ''));
    if ($cusColor === '' || strlen($cusColor) > 50) respondDraft(422, ['ok' => false, 'message' => 'Cus Color wajib diisi.', 'request_id' => $requestId]);
    $conditions = ['UPPER(LTRIM(RTRIM(e.cus_color))) = UPPER(?)', 'e.was_approved = 1'];
    $params = [$cusColor];
    resepExperimentApplyVisibility($conditions, $params, 'e', $conn);
    $sql = "SELECT e.id, e.experiment_seq, e.experiment_status, e.no_cp, e.soi, e.kode_warna, e.color_name, e.cus_color, e.created_at, e.created_by,
                   (SELECT COUNT(*) FROM dbo.resep_obat_experiment_detail d WHERE d.id_resep_experiment=e.id) AS detail_count
            FROM dbo.resep_obat_experiment e WHERE " . implode(' AND ', $conditions) . ' ORDER BY e.created_at DESC, e.id DESC';
    $statement = sqlsrv_query($conn, $sql, $params);
    if ($statement === false) respondDraft(500, ['ok' => false, 'message' => 'Gagal mencari experiment.', 'request_id' => $requestId]);
    $results = [];
    while ($row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC)) {
        if ($row['created_at'] instanceof DateTime) $row['created_at'] = $row['created_at']->format('Y-m-d H:i:s');
        $results[] = $row;
    }
    respondDraft(200, ['ok' => true, 'results' => $results]);
}

$experimentId = filter_var($_GET['experiment_id'] ?? null, FILTER_VALIDATE_INT);
if (!$experimentId) respondDraft(422, ['ok' => false, 'message' => 'Experiment tidak valid.', 'request_id' => $requestId]);
$conditions = ['e.id = ?', 'e.was_approved = 1'];
$params = [$experimentId];
resepExperimentApplyVisibility($conditions, $params, 'e', $conn);
$sql = "SELECT e.id, e.no_cp, e.soi, e.kode_grey, e.mesin, e.kode_warna, e.color_name, e.color_desc, e.resep_prod_code, e.resep_prod_name, e.cus_color, e.lot_no, e.weight, e.plan_qty, e.vlot
        FROM dbo.resep_obat_experiment e WHERE " . implode(' AND ', $conditions);
$statement = sqlsrv_query($conn, $sql, $params);
$header = $statement ? sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC) : null;
if (!$header) respondDraft(404, ['ok' => false, 'message' => 'Experiment Approved tidak ditemukan atau tidak dapat diakses.', 'request_id' => $requestId]);
$detailStatement = sqlsrv_query($conn, 'SELECT kode, name, category, receipe, uom, cf, uom_cf, std_price, total, price_satuan, price_source, is_manual FROM dbo.resep_obat_experiment_detail WHERE id_resep_experiment=? ORDER BY id', [$experimentId]);
if ($detailStatement === false) respondDraft(500, ['ok' => false, 'message' => 'Gagal memuat detail experiment.', 'request_id' => $requestId]);
$details = [];
while ($row = sqlsrv_fetch_array($detailStatement, SQLSRV_FETCH_ASSOC)) $details[] = $row;
$duplicateParams = [$header['cus_color'], $header['kode_warna']];
$duplicateWhere = 'UPPER(LTRIM(RTRIM(cus_color)))=UPPER(?) AND UPPER(LTRIM(RTRIM(kode_warna)))=UPPER(?)';
if (trim((string)$header['no_cp']) !== '') { $duplicateWhere .= ' AND UPPER(LTRIM(RTRIM(no_cp)))=UPPER(?)'; $duplicateParams[] = $header['no_cp']; }
$duplicateStatement = sqlsrv_query($conn, "SELECT TOP 10 id, no_cp, kode_warna, color_name, cus_color, status_resep_lipat, created_at FROM dbo.resep_obat WHERE $duplicateWhere ORDER BY id DESC", $duplicateParams);
$duplicates = [];
while ($duplicateStatement && $row = sqlsrv_fetch_array($duplicateStatement, SQLSRV_FETCH_ASSOC)) { if ($row['created_at'] instanceof DateTime) $row['created_at'] = $row['created_at']->format('Y-m-d H:i:s'); $duplicates[] = $row; }
respondDraft(200, ['ok' => true, 'header' => $header, 'details' => $details, 'duplicates' => $duplicates]);
