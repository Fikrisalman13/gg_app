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
function num($v){
  if($v===''||$v===null) return null;
  $c=str_replace([' ',','],['','.'],(string)$v);
  return is_numeric($c)?(float)$c:null;
}
// Bungkus nilai DECIMAL dengan binding eksplisit agar null PHP diterima SQL Server
function dec($v){ return [$v, SQLSRV_PARAM_IN, null, SQLSRV_SQLTYPE_DECIMAL(18,4)]; }

if(!isset($_SESSION['UserName'])) fail('Unauthorized');
$permissions=checkPermissions($conn,$_SESSION['GroupId']??0,212);
$isAdmin=(int)($_SESSION['GroupId']??0)===1;
if(($permissions['CanAdd']??0)!=1&&($permissions['CanEdit']??0)!=1) fail('Anda tidak memiliki hak untuk menyimpan Parameter Mesin Lab.');
$resep_id=(int)($_POST['resep_id']??0); if(!$resep_id) fail('ID experiment tidak ditemukan.');
$se=sqlsrv_query($conn,"SELECT e.id,e.group_id,e.experiment_status,g.group_status FROM dbo.resep_obat_experiment e LEFT JOIN dbo.resep_obat_experiment_group g ON g.id=e.group_id WHERE e.id=?",[$resep_id]);
$exp=$se?sqlsrv_fetch_array($se,SQLSRV_FETCH_ASSOC):null; if(!$exp) fail('Experiment tidak ditemukan.');
if(!$isAdmin&&(($exp['experiment_status']??'')==='Approved'||($exp['group_status']??'')==='Approved')) fail('Experiment/group Approved tidak bisa diubah.');
$user=$_SESSION['UserName']; $now=date('Y-m-d H:i:s');
$vals=[
  'machine_code'=>trim($_POST['machine_code']??''),
  'machine_name'=>trim($_POST['machine_name']??''),
  'infra_red'       =>num($_POST['infra_red']??null),
  'tekanan_padder'  =>num($_POST['tekanan_padder']??null),
  'wpu'             =>num($_POST['wpu']??null),
  'speed'           =>num($_POST['speed']??null),
  'fan1'            =>num($_POST['fan1']??null),
  'fan2'            =>num($_POST['fan2']??null),
  'temp_chamber_1'  =>num($_POST['temp_chamber_1']??null),
  'temp_chamber_1_time'=>num($_POST['temp_chamber_1_time']??null),
  'temp_chamber_2'  =>num($_POST['temp_chamber_2']??null),
  'temp_chamber_2_time'=>num($_POST['temp_chamber_2_time']??null),
  'lainnya'         =>trim($_POST['lainnya']??''),
];
$exists=0;
$sx=sqlsrv_query($conn,"SELECT TOP 1 id FROM dbo.resep_obat_experiment_lab_param WHERE id_resep_experiment=?",[$resep_id]);
if($sx&&$r=sqlsrv_fetch_array($sx,SQLSRV_FETCH_ASSOC)) $exists=(int)$r['id'];
if($exists){
  $sql="UPDATE dbo.resep_obat_experiment_lab_param SET machine_code=?,machine_name=?,infra_red=?,tekanan_padder=?,wpu=?,speed=?,fan1=?,fan2=?,temp_chamber_1=?,temp_chamber_1_time=?,temp_chamber_2=?,temp_chamber_2_time=?,lainnya=?,updated_at=?,updated_by=? WHERE id=?";
  $params=[
    $vals['machine_code'],$vals['machine_name'],
    dec($vals['infra_red']), dec($vals['tekanan_padder']), dec($vals['wpu']),
    dec($vals['speed']),     dec($vals['fan1']),           dec($vals['fan2']),
    dec($vals['temp_chamber_1']),  dec($vals['temp_chamber_1_time']),
    dec($vals['temp_chamber_2']),  dec($vals['temp_chamber_2_time']),
    $vals['lainnya'],$now,$user,$exists
  ];
}else{
  $sql="INSERT INTO dbo.resep_obat_experiment_lab_param (id_resep_experiment,machine_code,machine_name,infra_red,tekanan_padder,wpu,speed,fan1,fan2,temp_chamber_1,temp_chamber_1_time,temp_chamber_2,temp_chamber_2_time,lainnya,created_at,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
  $params=[
    $resep_id,$vals['machine_code'],$vals['machine_name'],
    dec($vals['infra_red']), dec($vals['tekanan_padder']), dec($vals['wpu']),
    dec($vals['speed']),     dec($vals['fan1']),           dec($vals['fan2']),
    dec($vals['temp_chamber_1']),  dec($vals['temp_chamber_1_time']),
    dec($vals['temp_chamber_2']),  dec($vals['temp_chamber_2_time']),
    $vals['lainnya'],$now,$user
  ];
}
$stmt=sqlsrv_query($conn,$sql,$params); if(!$stmt) fail('Simpan gagal: '.print_r(sqlsrv_errors(),true));
echo json_encode(['status'=>'success']);
