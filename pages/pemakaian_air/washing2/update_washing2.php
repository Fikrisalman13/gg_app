<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'].'/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'].'/gg_app/includes/permissions.php');
if (!isset($_SESSION['UserName'])) { $_SESSION['error']='Silakan login terlebih dahulu.'; header('Location:/gg_app/login.php'); exit; }
$menuId=230; $permissions=getPermissions($conn,$_SESSION['GroupId'],$menuId);
if (!empty($permissions) && isset($permissions['CanEdit']) && $permissions['CanEdit'] != 1) { $_SESSION['error']='Anda tidak memiliki hak untuk mengedit data.'; header('Location:washing2.php'); exit; }
if ($_SERVER['REQUEST_METHOD']!=='POST') { $_SESSION['error']='Metode request tidak valid.'; header('Location:washing2.php'); exit; }

function n($v){ $v=trim((string)$v); if($v==='') return null; $v=preg_replace('/[^0-9.,]/','',$v); if($v==='') return null; if(strpos($v,'.')!==false){ $a=str_replace(',','',$v); if(is_numeric($a)) return $a; } $f=str_replace('.','',$v); $f=str_replace(',','.',$f); return is_numeric($f)?$f:null; }

$tglOld = trim($_POST['tanggal_lama'] ?? '');
$tglNew = trim($_POST['tanggal'] ?? '');
if ($tglOld==='' || $tglNew==='') { $_SESSION['error']='Tanggal tidak valid.'; header('Location:washing2.php'); exit; }

$wAw = n($_POST['watt_awal'] ?? ''); $wAk = n($_POST['watt_akhir'] ?? ''); $wOpInput = n($_POST['watt_operasional'] ?? '');
$sAw = n($_POST['steam_awal'] ?? ''); $sAk = n($_POST['steam_akhir'] ?? ''); $sPm = n($_POST['steam_pemakaian'] ?? '');
$mAw = n($_POST['water_awal'] ?? ''); $mAk = n($_POST['water_akhir'] ?? ''); $mTp = n($_POST['total_pemakaian'] ?? '');
$mOp = n($_POST['water_operasional'] ?? ''); $mRt = n($_POST['pemakaian_rata'] ?? '');
$ket = trim($_POST['keterangan'] ?? ''); $cat = trim($_POST['catatan'] ?? '');
$sPm = ($sAw!==null && $sAk!==null) ? round((float)$sAk-(float)$sAw,2) : $sPm;
$mTp = ($mAw!==null && $mAk!==null) ? round((float)$mAk-(float)$mAw,2) : $mTp;
$mRt = ($mTp!==null && $mOp!==null && (float)$mOp!=0) ? round((float)$mTp/(float)$mOp,2) : $mRt;
$wOp = ($wAw!==null && $wAk!==null) ? round((float)$wAk-(float)$wAw,2) : $wOpInput;

// upsert helper per table by old/new tanggal
function upsert_table($conn, $table, $oldDate, $newDate, $fields, $values, $user){
  $cek = sqlsrv_query($conn, "SELECT TOP 1 Id FROM dbo.$table WHERE Tanggal=?", [$oldDate]);
  $row = $cek ? sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC) : null;
  if ($cek) sqlsrv_free_stmt($cek);
  if ($row) {
    $set = "Tanggal=?";
    $params = [$newDate];
    foreach($fields as $f){ $set .= ",$f=?"; $params[] = $values[$f]; }
    $set .= ",UpdateBy=?, UpdateAt=GETDATE()";
    $params[] = $user; $params[] = $oldDate;
    return sqlsrv_query($conn, "UPDATE dbo.$table SET $set WHERE Tanggal=?", $params);
  }
  $notNull = false;
  foreach($fields as $f){ if($values[$f]!==null && $values[$f]!==''){ $notNull=true; break; } }
  if (!$notNull) return true;
  $cols = "Tanggal"; $q = "?"; $params = [$newDate];
  foreach($fields as $f){ $cols .= ",$f"; $q .= ",?"; $params[] = $values[$f]; }
  $cols .= ",CreatBy"; $q .= ",?"; $params[] = $user;
  return sqlsrv_query($conn, "INSERT INTO dbo.$table ($cols) VALUES ($q)", $params);
}

$u = $_SESSION['UserName'];
$ok1 = upsert_table($conn,'washing2_watt_meter',$tglOld,$tglNew,['WattAwal','WattAkhir','OperasionalMesin'],['WattAwal'=>$wAw,'WattAkhir'=>$wAk,'OperasionalMesin'=>$wOp],$u);
$ok2 = upsert_table($conn,'washing2_steam_meter',$tglOld,$tglNew,['SteamAwal','SteamAkhir','SteamPemakaian'],['SteamAwal'=>$sAw,'SteamAkhir'=>$sAk,'SteamPemakaian'=>$sPm],$u);
$ok3 = upsert_table($conn,'washing2_water_meter',$tglOld,$tglNew,['WaterAwal','WaterAkhir','TotalPemakaian','OperasionalMesin','PemakaianRataPerJam','Keterangan','Catatan'],['WaterAwal'=>$mAw,'WaterAkhir'=>$mAk,'TotalPemakaian'=>$mTp,'OperasionalMesin'=>$mOp,'PemakaianRataPerJam'=>$mRt,'Keterangan'=>$ket,'Catatan'=>$cat],$u);

if ($ok1===false || $ok2===false || $ok3===false) { $_SESSION['error']='Gagal mengupdate data.'; header('Location:edit_washing2.php?tanggal='.urlencode($tglOld)); exit; }
$_SESSION['success']='Data berhasil diupdate.'; header('Location:washing2.php'); exit;
