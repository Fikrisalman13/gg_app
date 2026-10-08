<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../koneksi3.php';
require_once __DIR__ . '/verify_proint_item.php';
header('Content-Type: application/json; charset=utf-8');
$requestId = bin2hex(random_bytes(8));
function respond(int $status, array $payload): void { global $requestId; http_response_code($status); echo json_encode(array_merge(['request_id' => $requestId], $payload), JSON_UNESCAPED_UNICODE); exit; }
function logLookupFailure(string $message, array $context = []): void { global $requestId; $dir = __DIR__ . '/../../../logs'; if (!is_dir($dir)) @mkdir($dir, 0775, true); $entry = ['timestamp'=>date(DATE_ATOM),'severity'=>'ERROR','request_id'=>$requestId,'module'=>'ppc_guidance/resep_obat','action'=>'lookup_detail_by_cp','user'=>$_SESSION['UserName']??null,'message'=>$message,'source'=>basename(__FILE__),'context'=>$context]; @file_put_contents($dir.'/error-'.date('Y-m-d').'.log', json_encode($entry, JSON_UNESCAPED_UNICODE).PHP_EOL, FILE_APPEND|LOCK_EX); }
if (!isset($_SESSION['UserName'])) respond(401, ['ok'=>false,'message'=>'Sesi login telah berakhir.']);
$noCp = trim((string)($_GET['no_cp'] ?? ''));
$bonreqId = filter_input(INPUT_GET, 'bonreq_id', FILTER_VALIDATE_INT);
if ($noCp === '' || strlen($noCp) > 25) respond(422, ['ok'=>false,'message'=>'No CP tidak valid.']);
try {
    $sql = "SELECT br.bonreqid,br.bonno,br.bondate,br.bonseq,br.productionhdid,br.productionrtgid,br.bomhdid,br.rtgmsid,rt.rtgcode,rt.rtgname,br.planqty,br.bonweight,br.vlot,bh.bomcode,bh.bomname FROM pdproductionhd ph JOIN pdbonreq br ON br.productionhdid=ph.productionhdid LEFT JOIN pdrtgms rt ON rt.rtgmsid=br.rtgmsid LEFT JOIN pdbomhd bh ON bh.bomhdid=br.bomhdid WHERE ph.prdnmbr=:cp ORDER BY br.bondate DESC,br.bonseq,br.bonreqid";
    $stmt=$conn3->prepare($sql); $stmt->execute([':cp'=>$noCp]); $candidates=$stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$candidates) respond(404,['ok'=>false,'message'=>'Data bon untuk No CP tidak ditemukan.']);
    if (!$bonreqId) respond(200,['ok'=>true,'candidates'=>$candidates]);
    $selected=null; foreach($candidates as $candidate){ if((int)$candidate['bonreqid']===(int)$bonreqId){$selected=$candidate;break;} }
    if(!$selected) respond(404,['ok'=>false,'message'=>'Bon tidak sesuai dengan No CP.']);
    $sql="SELECT pm.prodcode AS proint_code,pm.prodname AS proint_name,pm.matqty AS qty,u.uomcode AS satuan FROM pdproductionmat pm LEFT JOIN smuom u ON u.uomid=pm.matuomid WHERE pm.productionhdid=:productionhdid AND pm.productionrtgid=:productionrtgid AND pm.fgusedtype='G' ORDER BY pm.matseq,pm.productionmatid";
    $stmt=$conn3->prepare($sql); $stmt->execute([':productionhdid'=>$selected['productionhdid'],':productionrtgid'=>$selected['productionrtgid']]); $rows=$stmt->fetchAll(PDO::FETCH_ASSOC); $details=[];
    $vlot=(float)$selected['vlot'];
    foreach($rows as $row){
        $local=checkLocalItem($row['proint_code']);
        $category=strtoupper(trim((string)($local['group_obat']??'')));
        if(!$local || (!str_starts_with($category,'DISPERSE') && !str_starts_with($category,'REACTIVE'))) continue;
        $qty=(float)$row['qty']; $cf=$vlot>0?$qty/$vlot:0;
        $details[]=['kode'=>$local['kode_obat'],'codeprod'=>$row['proint_code'],'name'=>$local['nama_obat'],'category'=>$local['group_obat'],'receipe'=>$qty,'uom'=>strtoupper(trim((string)$row['satuan']))==='G/L'?'GR':($row['satuan']??''),'cf'=>$cf,'uom_cf'=>$local['uom']??'','std_price'=>0,'price_source'=>'','satuan'=>$row['satuan']??'','warning'=>''];
    }
    respond(200,['ok'=>true,'selected'=>$selected,'details'=>$details]);
} catch(Throwable $e){ logLookupFailure('Gagal mengambil detail resep berdasarkan No CP.',['exception'=>get_class($e)]); respond(500,['ok'=>false,'message'=>'Gagal mengambil detail resep.']); }
