<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../koneksi.php';
header('Content-Type: application/json; charset=utf-8');
function fail($m){echo json_encode(['status'=>'error','message'=>$m]);exit;}
function checkPermissions($conn, $groupId, $menuId) {
  $stmt=sqlsrv_query($conn,"SELECT CanView,CanAdd,CanEdit,CanDelete FROM dbo.SMGroupTrustee WHERE GroupId=? AND MenuId=?",[$groupId,$menuId]);
  $p=['CanView'=>0,'CanAdd'=>0,'CanEdit'=>0,'CanDelete'=>0]; if($stmt&&$r=sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC))$p=$r; return $p;
}
function num($v){return ($v===''||$v===null)?null:(float)str_replace(',','.',str_replace(' ','',$v));}
if(!isset($_SESSION['UserName'])) fail('Unauthorized');
$permissions=checkPermissions($conn,$_SESSION['GroupId']??0,212);
$isAdmin=(int)($_SESSION['GroupId']??0)===1;
if(($permissions['CanAdd']??0)!=1&&($permissions['CanEdit']??0)!=1) fail('Anda tidak memiliki hak untuk menyimpan Lab Data.');
$resep_id=(int)($_POST['resep_id']??0); if(!$resep_id) fail('ID experiment tidak ditemukan.');
$se=sqlsrv_query($conn,"SELECT e.id,e.group_id,e.experiment_status,g.group_status FROM dbo.resep_obat_experiment e LEFT JOIN dbo.resep_obat_experiment_group g ON g.id=e.group_id WHERE e.id=?",[$resep_id]);
$exp=$se?sqlsrv_fetch_array($se,SQLSRV_FETCH_ASSOC):null; if(!$exp) fail('Experiment tidak ditemukan.');
if(!$isAdmin && (($exp['experiment_status']??'')==='Approved'||($exp['group_status']??'')==='Approved')) fail('Experiment/group Approved tidak bisa diubah.');
$user=$_SESSION['UserName']; $now=date('Y-m-d H:i:s');
$vals=[num($_POST['delta_l']??null),num($_POST['delta_a']??null),num($_POST['delta_b']??null),num($_POST['delta_e']??null)];
$exists=0;$sx=sqlsrv_query($conn,"SELECT TOP 1 id FROM dbo.resep_obat_experiment_lab_data WHERE id_resep_experiment=?",[$resep_id]); if($sx&&$r=sqlsrv_fetch_array($sx,SQLSRV_FETCH_ASSOC))$exists=(int)$r['id'];
if($exists){
  $sql="UPDATE dbo.resep_obat_experiment_lab_data SET delta_l=?,delta_a=?,delta_b=?,delta_e=?,updated_at=?,updated_by=? WHERE id=?";
  $params=array_merge($vals,[$now,$user,$exists]);
}else{
  $sql="INSERT INTO dbo.resep_obat_experiment_lab_data (id_resep_experiment,delta_l,delta_a,delta_b,delta_e,created_at,created_by) VALUES (?,?,?,?,?,?,?)";
  $params=array_merge([$resep_id],$vals,[$now,$user]);
}
$stmt=sqlsrv_query($conn,$sql,$params); if(!$stmt) fail('Simpan gagal: '.print_r(sqlsrv_errors(),true));
echo json_encode(['status'=>'success']);
