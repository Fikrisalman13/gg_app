<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__.'/../../../koneksi.php';
header('Content-Type: application/json; charset=utf-8');

if(!isset($_SESSION['UserName'])){echo json_encode(['status'=>'error','message'=>'Unauthorized']);exit;}
$user=$_SESSION['UserName'];
$now=date('Y-m-d H:i:s');
$isAdmin = (int)($_SESSION['GroupId'] ?? 0) === 1;

function cleanHeaderNum($s){return ($s===''||$s===null)?0:floatval(str_replace(',','',$s));}
function cleanPriceNum($s){$v=str_replace(['Rp',' '],'',$s);$v=str_replace('.','',$v);$v=str_replace(',','.',$v);return floatval($v);}
function cleanUSNum($s){return floatval(str_replace(',','',$s??0));}
function fail($m){ echo json_encode(['status'=>'error','message'=>$m]); exit; }

$resep_id = $_POST['resep_id'] ?? '';
$group_id = $_POST['group_id'] ?? '';
$next_group_id = $_POST['next_group_id'] ?? '';
$kode_warna = trim($_POST['kode_warna'] ?? '');
if ($kode_warna === '') fail('Kode Warna wajib diisi');

$groupVals = [
  'soi'=>trim($_POST['soi']??''),
  'no_cp'=>trim($_POST['no_cp']??''),
  'kode_grey'=>$_POST['kode_grey']??'',
  'mesin'=>$_POST['mesin']??'',
  'kode_warna'=>$kode_warna,
  'color_name'=>$_POST['color_name']??'',
  'color_desc'=>$_POST['color_desc']??'',
  'resep_prod_code'=>$_POST['resepprodcode']??'',
  'resep_prod_name'=>$_POST['resepprodname']??'',
  'cus_color'=>$_POST['cus_color']??'',
  'proint_resephdid'=>($_POST['proint_resephdid']??'')!==''?(int)$_POST['proint_resephdid']:null,
];
$expVals = [
  'lot_no'=>$_POST['lot_no']??'',
  'weight'=>cleanUSNum($_POST['weight']??0),
  'plan_qty'=>cleanHeaderNum($_POST['plan_qty']??0),
  'vlot'=>cleanHeaderNum($_POST['vlot']??0),
  'experiment_status'=>$_POST['experiment_status']??'Draft',
  'experiment_note'=>$_POST['experiment_note']??'',
];

if ($resep_id) {
  $stmt = sqlsrv_query($conn, "SELECT group_id FROM dbo.resep_obat_experiment WHERE id=?", [$resep_id]);
  $row = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
  if (!$row) fail('Data experiment tidak ditemukan');
  $group_id = $row['group_id'];
  if (!$group_id) fail('Group experiment tidak ditemukan');

  // Data umum group tidak di-update saat edit experiment.
  // View group mengambil info dari experiment terbaru, atau experiment approved jika ada.

  $statusClauseE = $isAdmin ? '' : "AND experiment_status <> 'Approved'";
  $sqlE = "UPDATE dbo.resep_obat_experiment SET lot_no=?,weight=?,plan_qty=?,vlot=?,experiment_status=?,experiment_note=?,updated_at=?,updated_by=?,kode_grey=?,mesin=?,kode_warna=?,color_name=?,color_desc=?,resep_prod_code=?,resep_prod_name=?,cus_color=?,proint_resephdid=?,no_cp=? WHERE id=? $statusClauseE";
  $paramsE = [$expVals['lot_no'],$expVals['weight'],$expVals['plan_qty'],$expVals['vlot'],$expVals['experiment_status'],$expVals['experiment_note'],$now,$user,$groupVals['kode_grey'],$groupVals['mesin'],$groupVals['kode_warna'],$groupVals['color_name'],$groupVals['color_desc'],$groupVals['resep_prod_code'],$groupVals['resep_prod_name'],$groupVals['cus_color'],$groupVals['proint_resephdid'],$groupVals['no_cp'],$resep_id];
  $stmtE = sqlsrv_query($conn, $sqlE, $paramsE);
  if (!$stmtE) fail('Update experiment gagal: '.print_r(sqlsrv_errors(),true));
  sqlsrv_query($conn,"DELETE FROM dbo.resep_obat_experiment_detail WHERE id_resep_experiment=?",[$resep_id]);
  $id = $resep_id;
} elseif ($next_group_id) {
  $group_id = (int)$next_group_id;
  $sg = sqlsrv_query($conn, "SELECT group_status FROM dbo.resep_obat_experiment_group WHERE id=?", [$group_id]);
  $g = $sg ? sqlsrv_fetch_array($sg, SQLSRV_FETCH_ASSOC) : null;
  if (!$g) fail('Group tidak ditemukan');
  if (!$isAdmin && ($g['group_status'] ?? '') === 'Approved') fail('Group sudah Approved');

  $sl = sqlsrv_query($conn, "SELECT TOP 1 experiment_seq FROM dbo.resep_obat_experiment WHERE group_id=? ORDER BY experiment_seq DESC, id DESC", [$group_id]);
  $lr = $sl ? sqlsrv_fetch_array($sl, SQLSRV_FETCH_ASSOC) : null;
  $nextSeq = $lr ? ((int)$lr['experiment_seq'] + 1) : 1;

  $sqlE = "INSERT INTO dbo.resep_obat_experiment (group_id,experiment_seq,experiment_status,experiment_note,no_cp,kode_grey,mesin,kode_warna,color_name,color_desc,resep_prod_code,resep_prod_name,cus_color,proint_resephdid,lot_no,weight,plan_qty,vlot,created_at,created_by) OUTPUT INSERTED.id VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
  $paramsE = [$group_id,$nextSeq,$expVals['experiment_status'],$expVals['experiment_note'],$groupVals['no_cp'],$groupVals['kode_grey'],$groupVals['mesin'],$groupVals['kode_warna'],$groupVals['color_name'],$groupVals['color_desc'],$groupVals['resep_prod_code'],$groupVals['resep_prod_name'],$groupVals['cus_color'],$groupVals['proint_resephdid'],$expVals['lot_no'],$expVals['weight'],$expVals['plan_qty'],$expVals['vlot'],$now,$user];
  $stmtE = sqlsrv_query($conn,$sqlE,$paramsE);
  if (!$stmtE) fail('Insert experiment gagal: '.print_r(sqlsrv_errors(),true));
  $eRow = sqlsrv_fetch_array($stmtE, SQLSRV_FETCH_ASSOC);
  $id = $eRow['id'] ?? null;
  if (!$id) fail('Gagal mendapatkan ID experiment baru');
} else {
  $sqlG = "INSERT INTO dbo.resep_obat_experiment_group (soi,no_cp,kode_grey,mesin,kode_warna,color_name,color_desc,resep_prod_code,resep_prod_name,cus_color,proint_resephdid,group_status,created_at,created_by) OUTPUT INSERTED.id VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
  $paramsG = [$groupVals['soi'],$groupVals['no_cp'],$groupVals['kode_grey'],$groupVals['mesin'],$groupVals['kode_warna'],$groupVals['color_name'],$groupVals['color_desc'],$groupVals['resep_prod_code'],$groupVals['resep_prod_name'],$groupVals['cus_color'],$groupVals['proint_resephdid'],'Draft',$now,$user];
  $stmtG = sqlsrv_query($conn,$sqlG,$paramsG);
  if (!$stmtG) fail('Insert group gagal: '.print_r(sqlsrv_errors(),true));
  $gRow = sqlsrv_fetch_array($stmtG, SQLSRV_FETCH_ASSOC);
  $group_id = $gRow['id'] ?? null;
  if (!$group_id) fail('Gagal mendapatkan ID group baru');

  $sqlE = "INSERT INTO dbo.resep_obat_experiment (group_id,experiment_seq,experiment_status,experiment_note,no_cp,kode_grey,mesin,kode_warna,color_name,color_desc,resep_prod_code,resep_prod_name,cus_color,proint_resephdid,lot_no,weight,plan_qty,vlot,created_at,created_by) OUTPUT INSERTED.id VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
  $paramsE = [$group_id,1,$expVals['experiment_status'],$expVals['experiment_note'],$groupVals['no_cp'],$groupVals['kode_grey'],$groupVals['mesin'],$groupVals['kode_warna'],$groupVals['color_name'],$groupVals['color_desc'],$groupVals['resep_prod_code'],$groupVals['resep_prod_name'],$groupVals['cus_color'],$groupVals['proint_resephdid'],$expVals['lot_no'],$expVals['weight'],$expVals['plan_qty'],$expVals['vlot'],$now,$user];
  $stmtE = sqlsrv_query($conn,$sqlE,$paramsE);
  if (!$stmtE) fail('Insert experiment gagal: '.print_r(sqlsrv_errors(),true));
  $eRow = sqlsrv_fetch_array($stmtE, SQLSRV_FETCH_ASSOC);
  $id = $eRow['id'] ?? null;
  if (!$id) fail('Gagal mendapatkan ID experiment baru');
}

$items=$_POST['items']??[]; $err='';
if(is_array($items)){
  $sqlD="INSERT INTO dbo.resep_obat_experiment_detail (id_resep_experiment,kode,name,category,receipe,uom,cf,uom_cf,std_price,total,created_at,created_by,price_satuan,price_source,is_manual) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
  foreach($items as $it){
    $kode=trim($it['kode']??''); if($kode==='') continue;
    $qty=cleanUSNum($it['receipe']??0); $price=cleanPriceNum($it['std_price']??0); $total=$qty*$price; $uom=strtoupper(trim($it['uom']??'')); if($uom==='GR'||$uom==='G/L')$total/=1000;
    $paramsD=[$id,$kode,$it['name']??'',$it['category']??'',$qty,$it['uom']??'',cleanUSNum($it['cf']??0),$it['uom_cf']??'',$price,$total,$now,$user,$it['price_satuan']??'',$it['price_source']??'',1];
    $sd=sqlsrv_query($conn,$sqlD,$paramsD); if(!$sd)$err.='Item '.$kode.' gagal: '.print_r(sqlsrv_errors(),true);
  }
}
echo json_encode(['status'=>'success','id'=>$id,'group_id'=>$group_id,'warning'=>$err]);
