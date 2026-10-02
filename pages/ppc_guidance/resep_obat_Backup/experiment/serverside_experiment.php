<?php
ob_start();
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../../koneksi.php';
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['UserName'])) { echo json_encode(['draw'=>0,'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[],'error'=>'Unauthorized']); exit; }
error_reporting(0); ini_set('display_errors', 0);

try {
    $start = (int)($_POST['start'] ?? 0); $length = (int)($_POST['length'] ?? 10); $draw = (int)($_POST['draw'] ?? 1); $search = $_POST['search']['value'] ?? '';
    $base = "FROM dbo.resep_obat_experiment r"; $where=[]; $params=[];
    if ($search !== '') { $where[] = "(r.no_cp LIKE ? OR r.kode_warna LIKE ? OR r.color_name LIKE ? OR r.resep_prod_code LIKE ? OR r.resep_prod_name LIKE ? OR r.cus_color LIKE ? OR r.lot_no LIKE ? OR r.created_by LIKE ?)"; for($i=0;$i<8;$i++) $params[]="%$search%"; }
    if (!empty($_POST['startDate']) && !empty($_POST['endDate'])) { $where[]="(r.created_at >= ? AND r.created_at <= ?)"; $params[]=$_POST['startDate'].' 00:00:00'; $params[]=$_POST['endDate'].' 23:59:59'; }
    $whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';
    $total=0; $stmt=sqlsrv_query($conn,"SELECT COUNT(*) total $base"); if($stmt && $row=sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC)) $total=(int)$row['total'];
    $filtered=0; $stmt=sqlsrv_query($conn,"SELECT COUNT(*) total $base $whereSql",$params); if($stmt && $row=sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC)) $filtered=(int)$row['total'];
    $sql="SELECT r.id,r.no_cp,r.kode_warna,r.color_name,r.resep_prod_code,r.resep_prod_name,r.cus_color,r.weight,r.plan_qty,r.created_at $base $whereSql ORDER BY r.created_at DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
    $stmt=sqlsrv_query($conn,$sql,array_merge($params,[$start,$length])); $data=[];
    if($stmt){ while($row=sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC)){ $created='-'; if($row['created_at'] instanceof DateTime) $created=$row['created_at']->format('d-m-Y H:i'); $data[]=['id'=>$row['id'],'no_cp'=>$row['no_cp']?:'-','kode_warna'=>$row['kode_warna']?:'-','color_name'=>$row['color_name']?:'-','resep_prod_code'=>$row['resep_prod_code']?:'-','resep_prod_name'=>$row['resep_prod_name']?:'-','cus_color'=>$row['cus_color']?:'-','weight'=>$row['weight']??0,'plan_qty'=>$row['plan_qty']??0,'created_at'=>$created]; }}
    ob_clean(); echo json_encode(['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$data], JSON_UNESCAPED_UNICODE); exit;
} catch(Exception $e) { ob_clean(); echo json_encode(['draw'=>(int)($_POST['draw']??0),'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[],'error'=>$e->getMessage()]); exit; }
