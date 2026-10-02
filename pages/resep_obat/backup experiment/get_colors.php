<?php
require_once __DIR__ . '/../../../koneksi3.php';
header('Content-Type: application/json');
$q=$_GET['q']??''; if(strlen($q)<1){echo json_encode(['results'=>[]]); exit;}
try{ $sql="SELECT colormsid,colorcode,colorname,colordesc FROM pdcolorms WHERE colorcode ILIKE :q OR colorname ILIKE :q LIMIT 20"; $stmt=$conn3->prepare($sql); $term="%$q%"; $stmt->bindParam(':q',$term); $stmt->execute(); $res=[]; while($r=$stmt->fetch(PDO::FETCH_ASSOC)){ $res[]=['id'=>$r['colorcode'],'text'=>$r['colorcode'].' - '.$r['colorname'],'color_data'=>['colormsid'=>$r['colormsid'],'name'=>$r['colorname'],'desc'=>$r['colordesc']]]; } echo json_encode(['results'=>$res]); }catch(PDOException $e){ echo json_encode(['results'=>[],'error'=>$e->getMessage()]); }
