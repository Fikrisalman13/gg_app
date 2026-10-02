<?php
require_once __DIR__ . '/../../../koneksi3.php';
header('Content-Type: application/json');
$colormsid=$_GET['colormsid']??''; $q=$_GET['q']??''; if($colormsid===''){echo json_encode(['results'=>[]]); exit;}
try{
$sql="SELECT DISTINCT h.resephdid,h.resepprodcode,h.resepprodname,COALESCE(s.cuscolor,'') cuscolor
FROM pdresephd h
JOIN smproduct p ON h.resepprodcode=p.prodcode
LEFT JOIN smprodtype pt ON p.prodtypeid=pt.prodtypeid
LEFT JOIN (SELECT colormsid, MAX(cuscolor) cuscolor FROM smprodtechdata GROUP BY colormsid) s ON h.colormsid=s.colormsid
WHERE h.colormsid=:id AND LOWER(pt.prodtypecode)='fg'";
if($q!=='') $sql.=" AND (h.resepprodcode ILIKE :q OR h.resepprodname ILIKE :q)";
$sql.=" ORDER BY h.resepprodcode LIMIT 30"; $stmt=$conn3->prepare($sql); $stmt->bindParam(':id',$colormsid); if($q!==''){ $term="%$q%"; $stmt->bindParam(':q',$term); } $stmt->execute(); $res=[]; while($r=$stmt->fetch(PDO::FETCH_ASSOC)){ $res[]=['id'=>$r['resepprodcode'],'text'=>$r['resepprodcode'].' - '.$r['resepprodname'],'prod_data'=>$r]; } echo json_encode(['results'=>$res]);
}catch(Exception $e){ echo json_encode(['results'=>[],'error'=>$e->getMessage()]); }
