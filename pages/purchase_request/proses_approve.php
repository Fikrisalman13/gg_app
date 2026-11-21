<?php
session_start(); header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['UserName'])) { http_response_code(401); echo json_encode(['status'=>'error','message'=>'Silakan login terlebih dahulu.']); exit; }
require '../../koneksi3.php'; date_default_timezone_set('Asia/Jakarta');
$action=$_POST['action']??''; $numbers=$_POST['ppnmbrs']??[];
if (!in_array($action,['approve','unapprove'],true)||!is_array($numbers)||!$numbers) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Aksi atau daftar PR tidak valid.']); exit; }
$approveBy='DMR';
try { $conn3->beginTransaction();
 $sql=$action==='approve' ? "UPDATE prpphd SET fgstatus='V', approvedate=:date, approveby=:by WHERE ppnmbr=:number AND fgstatus='O'" : "UPDATE prpphd SET fgstatus='O', approvedate=NULL, approveby=NULL WHERE ppnmbr=:number AND fgstatus='V'";
 $stmt=$conn3->prepare($sql); $count=0; $now=date('Y-m-d H:i:s');
 foreach(array_unique(array_map('strval',$numbers)) as $number){ if(trim($number)==='') continue; $params=[':number'=>$number]; if($action==='approve'){$params[':date']=$now;$params[':by']=$approveBy;} $stmt->execute($params);$count+=$stmt->rowCount(); }
 $conn3->commit(); echo json_encode(['status'=>'success','processed'=>$count,'message'=>"$count PR berhasil diproses."]);
} catch(Throwable $e){
 if($conn3->inTransaction())$conn3->rollBack();
 error_log('Purchase Request approval failed for user '.($_SESSION['UserName']??'unknown').': '.$e->getMessage());
 http_response_code(500);
 echo json_encode(['status'=>'error','message'=>'Update PR gagal: '.$e->getMessage()]);
}
