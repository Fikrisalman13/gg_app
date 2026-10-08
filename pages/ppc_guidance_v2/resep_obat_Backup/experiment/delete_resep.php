<?php
session_start(); require_once __DIR__.'/../../../../koneksi.php'; header('Content-Type: application/json');
if(!isset($_SESSION['UserName'])){echo json_encode(['status'=>'error','message'=>'Unauthorized']);exit;} $id=$_POST['id']??''; if(!$id){echo json_encode(['status'=>'error','message'=>'ID tidak valid']);exit;}
$stmt=sqlsrv_query($conn,"DELETE FROM dbo.resep_obat_experiment_detail WHERE id_resep_experiment=?",[$id]); if($stmt===false){echo json_encode(['status'=>'error','message'=>'Gagal hapus detail: '.print_r(sqlsrv_errors(),true)]);exit;}
$stmt=sqlsrv_query($conn,"DELETE FROM dbo.resep_obat_experiment WHERE id=?",[$id]); if($stmt===false){echo json_encode(['status'=>'error','message'=>'Gagal hapus header: '.print_r(sqlsrv_errors(),true)]);exit;} echo json_encode(['status'=>'success']);
