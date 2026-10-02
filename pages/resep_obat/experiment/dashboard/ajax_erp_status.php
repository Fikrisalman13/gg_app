<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../../koneksi.php';
require_once __DIR__ . '/../../../../koneksi3.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['UserName'])) { http_response_code(401); echo json_encode(['ok'=>false,'message'=>'Unauthorized']); exit; }
$p = sqlsrv_query($conn, "SELECT CanView FROM dbo.SMGroupTrustee WHERE GroupId=? AND MenuId=212", [$_SESSION['GroupId'] ?? 0]);
if (!$p || !($pr = sqlsrv_fetch_array($p, SQLSRV_FETCH_ASSOC)) || (int)($pr['CanView'] ?? 0) !== 1) { http_response_code(403); echo json_encode(['ok'=>false,'message'=>'Forbidden']); exit; }

$cpList = $_POST['cp'] ?? [];
if (!is_array($cpList)) $cpList = [];
$cpList = array_values(array_filter(array_unique(array_map(static fn($v) => strtoupper(trim((string)$v)), $cpList))));
if (count($cpList) > 250) { http_response_code(400); echo json_encode(['ok'=>false,'message'=>'Maksimal 250 No CP per request']); exit; }
if (!$cpList) { echo json_encode(['ok'=>true,'data'=>[]]); exit; }

try {
    $keys = []; $params = [];
    foreach ($cpList as $i => $cp) { $key = ':cp_' . $i; $keys[] = $key; $params[$key] = $cp; }
    $sql = "WITH latest_cp AS (
              SELECT UPPER(TRIM(CAST(prdnmbr AS TEXT))) cp_no,productionhdid,
                     ROW_NUMBER() OVER (PARTITION BY UPPER(TRIM(CAST(prdnmbr AS TEXT))) ORDER BY prddate DESC NULLS LAST,productionhdid DESC) rn
              FROM pdproductionhd WHERE UPPER(TRIM(CAST(prdnmbr AS TEXT))) IN (" . implode(',', $keys) . ")
            ), acc AS (
              SELECT l.cp_no,COALESCE(r.failmsid,0) failmsid,COALESCE(r.fgresult,'') fgresult,
                     COALESCE(f.failcode,'') failcode,COALESCE(f.faildesc,'') faildesc,
                     ROW_NUMBER() OVER (PARTITION BY l.cp_no ORDER BY CASE WHEN COALESCE(r.failmsid,0)<>0 THEN 0 ELSE 1 END,r.starttime DESC NULLS LAST,r.productionrtgid DESC) rn
              FROM latest_cp l JOIN pdproductionrtg r ON r.productionhdid=l.productionhdid AND r.rtgmsid=589
              LEFT JOIN pdfailms f ON f.failmsid=r.failmsid WHERE l.rn=1
            ) SELECT cp_no,failmsid,fgresult,failcode,faildesc FROM acc WHERE rn=1";
    $stmt = $conn3->prepare($sql); $stmt->execute($params); $out = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $fail = (int)($r['failmsid'] ?? 0); $fg = strtoupper(trim((string)($r['fgresult'] ?? '')));
        $hasFail = trim((string)($r['failcode'] ?? '')) !== '' || trim((string)($r['faildesc'] ?? '')) !== '';
        $status = null;
        if ($fail !== 0 && $hasFail) $status = 'Fail';
        elseif ($fg === 'P' && $fail === 0 && !$hasFail) $status = 'Pass';
        if ($status) $out[strtoupper(trim((string)$r['cp_no']))] = ['status'=>$status];
    }
    echo json_encode(['ok'=>true,'data'=>$out], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('dashboard ERP error: ' . $e->getMessage());
    http_response_code(503); echo json_encode(['ok'=>false,'message'=>'Status ERP gagal dimuat']);
}

