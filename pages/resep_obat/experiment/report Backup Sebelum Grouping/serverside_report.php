<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../../koneksi.php';
require_once __DIR__ . '/../experiment_visibility_helper.php';
header('Content-Type: application/json; charset=utf-8');
function reportPerm($conn): bool { $stmt = sqlsrv_query($conn, "SELECT CanView FROM dbo.SMGroupTrustee WHERE GroupId=? AND MenuId=212", [$_SESSION['GroupId'] ?? 0]); return $stmt && ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) && (int)($r['CanView'] ?? 0) === 1; }
function fmtDate($v): string { if ($v instanceof DateTimeInterface) return $v->format('d/m/Y'); if (!$v) return '-'; $ts = strtotime((string)$v); return $ts ? date('d/m/Y', $ts) : '-'; }
function fmtNum($v): string { return number_format((float)($v ?? 0), 2, '.', ','); }
if (!isset($_SESSION['UserName']) || !reportPerm($conn)) { echo json_encode(['data'=>[], 'recordsTotal'=>0, 'recordsFiltered'=>0]); exit; }
$start=(int)($_GET['start']??0); $length=(int)($_GET['length']??50); $search=trim($_GET['search']['value']??''); $orderBy=(int)($_GET['order'][0]['column']??1); $dir=strtolower($_GET['order'][0]['dir']??'desc'); if(!in_array($dir,['asc','desc'],true))$dir='desc';
$cols=[null,'e.created_at','e.kode_warna',null,'e.mesin','e.no_cp','e.kode_warna','e.plan_qty',null,null,'e.qc_keputusan','e.qc_catatan',null]; $orderByCol=$cols[$orderBy]??'e.created_at';
$conditions=[]; $params=[]; resepExperimentApplyVisibility($conditions,$params,'e',$conn);
if($search!==''){ $conditions[]="(e.no_cp LIKE ? OR e.kode_warna LIKE ? OR e.color_name LIKE ? OR e.mesin LIKE ? OR e.qc_keputusan LIKE ? OR e.qc_catatan LIKE ?)"; $s="%$search%"; array_push($params,$s,$s,$s,$s,$s,$s); }
if(trim($_GET['filter_date_from']??'')!==''){ $conditions[]='e.created_at >= ?'; $params[]=$_GET['filter_date_from']; }
if(trim($_GET['filter_date_to']??'')!==''){ $conditions[]='e.created_at <= ?'; $params[]=$_GET['filter_date_to'].' 23:59:59'; }
if(trim($_GET['filter_created_by']??'')!==''){ $createdByFilter=array_values(array_filter(array_map('trim',explode('|',(string)$_GET['filter_created_by'])),fn($v)=>$v!=='')); if($createdByFilter){ $conditions[]='e.created_by IN ('.implode(',',array_fill(0,count($createdByFilter),'?')).')'; array_push($params,...$createdByFilter); } }
$where=$conditions?'WHERE '.implode(' AND ',$conditions):'';
$stmtCount=sqlsrv_query($conn,"SELECT COUNT(*) AS cnt FROM dbo.resep_obat_experiment e $where",$params); $filtered=0; if($stmtCount&&$r=sqlsrv_fetch_array($stmtCount,SQLSRV_FETCH_ASSOC))$filtered=(int)$r['cnt']; if($stmtCount)sqlsrv_free_stmt($stmtCount);
$baseConditions=[]; $baseParams=[]; resepExperimentApplyVisibility($baseConditions,$baseParams,'e',$conn); $whereBase=$baseConditions?'WHERE '.implode(' AND ',$baseConditions):''; $stmtAll=sqlsrv_query($conn,"SELECT COUNT(*) AS cnt FROM dbo.resep_obat_experiment e $whereBase",$baseParams); $total=$filtered; if($stmtAll&&$r=sqlsrv_fetch_array($stmtAll,SQLSRV_FETCH_ASSOC))$total=(int)$r['cnt']; if($stmtAll)sqlsrv_free_stmt($stmtAll);
$sql="SELECT e.id,e.created_at,e.kode_warna,e.color_name,e.mesin,e.no_cp,e.plan_qty,e.qc_keputusan,e.qc_catatan FROM dbo.resep_obat_experiment e $where ORDER BY $orderByCol $dir OFFSET $start ROWS FETCH NEXT $length ROWS ONLY"; $stmt=sqlsrv_query($conn,$sql,$params); $data=[];
while($stmt&&$r=sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC)){ $hasCp=trim((string)($r['no_cp']??''))!==''; $data[]=['id'=>(int)$r['id'],'tgl_match'=>fmtDate($r['created_at']??null),'warna'=>$r['color_name']??'-','tgl_celup_padd'=>$hasCp?'<span class="erp-spinner" data-erp="tgl"></span>':'-','mesin_paddry'=>$r['mesin']??'-','no_cp'=>$r['no_cp']??'-','kode_lab'=>$r['kode_warna']??'-','qty'=>fmtNum($r['plan_qty']??0),'posisi_hari_ini'=>$hasCp?'<span class="erp-spinner" data-erp="pos"></span>':'-','acc_warna_r_status'=>'-','acc_warna_r_tgl'=>$hasCp?'<span class="erp-spinner" data-erp="acc"></span>':'-','keputusan'=>trim((string)($r['qc_keputusan']??''))?:'-','qc_catatan'=>trim((string)($r['qc_catatan']??''))?:'-']; }
if($stmt)sqlsrv_free_stmt($stmt); echo json_encode(['draw'=>(int)($_GET['draw']??1),'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$data]);
