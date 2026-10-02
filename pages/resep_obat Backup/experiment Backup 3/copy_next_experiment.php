<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../koneksi.php';
if (!isset($_SESSION['UserName'])) { header('Location:/gg_app/login.php'); exit; }
$user = $_SESSION['UserName']; $now = date('Y-m-d H:i:s');
$group_id = $_GET['group_id'] ?? $_POST['group_id'] ?? '';
if (!$group_id) die('Group ID Missing');

$sg = sqlsrv_query($conn, "SELECT id, group_status FROM dbo.resep_obat_experiment_group WHERE id=?", [$group_id]);
$group = $sg ? sqlsrv_fetch_array($sg, SQLSRV_FETCH_ASSOC) : null;
if (!$group) die('Group not found');
$isAdmin = (int)($_SESSION['GroupId'] ?? 0) === 1;
if (!$isAdmin && ($group['group_status'] ?? '') === 'Approved') die('Group sudah Approved');
// Redirect ke form tambah dengan parameter next_group_id.
// Dengan cara ini, data baru benar-benar dibuat saat user submit,
// sehingga kalau user batal/tutup halaman, tidak ada orphan draft.
header('Location: input_resep.php?next_group_id='.(int)$group_id);
exit;

$sl = sqlsrv_query($conn, "SELECT TOP 1 * FROM dbo.resep_obat_experiment WHERE group_id=? ORDER BY experiment_seq DESC, id DESC", [$group_id]);
$last = $sl ? sqlsrv_fetch_array($sl, SQLSRV_FETCH_ASSOC) : null;
$nextSeq = $last ? ((int)$last['experiment_seq'] + 1) : 1;

$sql = "INSERT INTO dbo.resep_obat_experiment (group_id,experiment_seq,experiment_status,experiment_note,no_cp,kode_grey,mesin,kode_warna,color_name,color_desc,resep_prod_code,resep_prod_name,cus_color,proint_resephdid,lot_no,weight,plan_qty,vlot,created_at,created_by) OUTPUT INSERTED.id VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
$params = [
  $group_id,$nextSeq,'Draft','',($group['no_cp']??''),($group['kode_grey']??''),($group['mesin']??''),($group['kode_warna']??''),($group['color_name']??''),($group['color_desc']??''),($group['resep_prod_code']??''),($group['resep_prod_name']??''),($group['cus_color']??''),($group['proint_resephdid']??null),
  $last['lot_no'] ?? '', $last['weight'] ?? 0, $last['plan_qty'] ?? 3500, $last['vlot'] ?? 0, $now, $user
];
$stmt = sqlsrv_query($conn, $sql, $params);
if (!$stmt) die('Insert experiment gagal: '.print_r(sqlsrv_errors(),true));
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
$newId = $row['id'] ?? null;
if (!$newId) die('Gagal mendapatkan ID baru');

if ($last) {
  $sd = sqlsrv_query($conn, "SELECT * FROM dbo.resep_obat_experiment_detail WHERE id_resep_experiment=? ORDER BY id ASC", [$last['id']]);
  $sqlD = "INSERT INTO dbo.resep_obat_experiment_detail (id_resep_experiment,kode,name,category,receipe,uom,cf,uom_cf,std_price,total,created_at,created_by,price_satuan,price_source,is_manual,master_obat_id,codeprod_proint) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
  while ($sd && $d = sqlsrv_fetch_array($sd, SQLSRV_FETCH_ASSOC)) {
    sqlsrv_query($conn, $sqlD, [$newId,$d['kode'],$d['name'],$d['category'],$d['receipe'],$d['uom'],$d['cf'],$d['uom_cf'],$d['std_price'],$d['total'],$now,$user,$d['price_satuan'],$d['price_source'],$d['is_manual'],$d['master_obat_id'] ?? null,$d['codeprod_proint'] ?? '']);
  }
}
header('Location: input_resep.php?resep_id='.(int)$newId);
exit;
