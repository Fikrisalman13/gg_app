<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
if (!isset($_SESSION['UserName'])) { header('Location: /gg_app/login.php'); exit; }
require_once $_SERVER['DOCUMENT_ROOT'].'/gg_app/koneksi.php';

function mm_json(array $data): void { header('Content-Type: application/json; charset=utf-8'); echo json_encode($data); exit; }
function mm_user(): string { return trim((string)($_SESSION['UserName'] ?? '')); }

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action=(string)($_POST['action'] ?? '');
    if ($action==='save') {
        $id=(int)($_POST['id'] ?? 0);
        $kode=strtoupper(trim((string)($_POST['kode_mesin'] ?? '')));
        $nama=trim((string)($_POST['nama_mesin'] ?? ''));
        $active=($_POST['status_active'] ?? '0')==='1' ? 1 : 0;
        if (!preg_match('/^[A-Z0-9_-]{1,20}$/',$kode)) mm_json(['success'=>false,'message'=>'Kode mesin wajib 1-20 karakter: huruf, angka, _ atau -.']);
        $dup=sqlsrv_query($conn,'SELECT TOP 1 id FROM dbo.manual_output_master_mesin WHERE kode_mesin=? AND id<>?',[$kode,$id]);
        if ($dup && sqlsrv_fetch_array($dup,SQLSRV_FETCH_ASSOC)) mm_json(['success'=>false,'message'=>'Kode mesin sudah tersedia.']);
        if ($id>0) {
            $stmt=sqlsrv_query($conn,'UPDATE dbo.manual_output_master_mesin SET kode_mesin=?,nama_mesin=?,status_active=?,updated_at=GETDATE(),updated_by=? WHERE id=?',[$kode,$nama?:null,$active,mm_user(),$id]);
        } else {
            $stmt=sqlsrv_query($conn,'INSERT INTO dbo.manual_output_master_mesin (kode_mesin,nama_mesin,status_active,created_by) VALUES (?,?,?,?)',[$kode,$nama?:null,$active,mm_user()]);
        }
        mm_json(['success'=>$stmt!==false,'message'=>$stmt!==false?'Master mesin tersimpan.':'Gagal menyimpan master mesin.']);
    }
    if ($action==='delete') {
        $id=(int)($_POST['id'] ?? 0);
        $used=sqlsrv_query($conn,'SELECT (SELECT COUNT(*) FROM dbo.manual_output WHERE mesin_id=?) + (SELECT COUNT(*) FROM dbo.manual_output_shift WHERE mesin_id=?) total',[$id,$id]);
        $usage=$used ? sqlsrv_fetch_array($used,SQLSRV_FETCH_ASSOC) : null;
        if ((int)($usage['total'] ?? 0)>0) mm_json(['success'=>false,'message'=>'Mesin sudah digunakan. Nonaktifkan mesin, jangan hapus.']);
        $stmt=sqlsrv_query($conn,'DELETE FROM dbo.manual_output_master_mesin WHERE id=?',[$id]);
        mm_json(['success'=>$stmt!==false,'message'=>$stmt!==false?'Master mesin dihapus.':'Gagal menghapus master mesin.']);
    }
    mm_json(['success'=>false,'message'=>'Aksi tidak valid.']);
}

$rows=[];$stmt=sqlsrv_query($conn,'SELECT id,kode_mesin,nama_mesin,status_active,created_at,created_by,updated_at,updated_by FROM dbo.manual_output_master_mesin ORDER BY kode_mesin');
if($stmt)while($row=sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC))$rows[]=$row;
$themes=['primary','secondary','success','danger','warning','info','light','dark','navy','olive','lime','fuchsia','maroon','blue','indigo','purple','pink','red','orange','yellow','green','teal','cyan','white','gray','gray-dark'];
$theme=strtolower((string)($_SESSION['Theme'] ?? 'primary'));if(!in_array($theme,$themes,true))$theme='primary';
$text=in_array($theme,['warning','light','lime','yellow','white'],true)?'text-dark':'text-white';
function mm_dt($value): string { return $value instanceof DateTimeInterface ? $value->format('d-m-Y H:i:s') : '-'; }
?>
<!DOCTYPE html><html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Master Mesin Output Manual</title><meta name="description" content="Kelola pilihan mesin untuk pencatatan Output Manual.">
<link rel="stylesheet" href="/gg_app/plugins/bootstrap-5.0.2-dist/css/bootstrap.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/jquery/jquery.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/dist/js/adminlte.min.js"></script>
<script src="/gg_app/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<style>
.machine-hero{border:0;overflow:hidden;background:linear-gradient(135deg,#0f4c81,#087f8c);box-shadow:0 12px 30px rgba(15,76,129,.18)}
.machine-hero .card-body{color:#fff;padding:22px}.machine-icon{width:52px;height:52px;border-radius:14px;display:grid;place-items:center;background:rgba(255,255,255,.16);font-size:1.4rem}
.machine-table thead th{white-space:nowrap}.badge-status{min-width:74px;padding:.45em .65em}.modal-content{border:0;border-radius:12px;overflow:hidden;box-shadow:0 22px 60px rgba(15,23,42,.22)}
</style></head><body class="hold-transition sidebar-mini"><div class="wrapper">
<?php include $_SERVER['DOCUMENT_ROOT'].'/gg_app/includes/header.php'; include $_SERVER['DOCUMENT_ROOT'].'/gg_app/includes/sidebar.php'; ?>
<main class="content-wrapper p-3"><section class="content"><div class="container-fluid">
<div class="card machine-hero mb-3"><div class="card-body d-flex align-items-center justify-content-between flex-wrap">
<div class="d-flex align-items-center"><div class="machine-icon mr-3"><i class="fas fa-industry"></i></div><div><h1 class="h4 mb-1 font-weight-bold">Master Mesin Output Manual</h1><p class="mb-0 text-white-50">Kelola mesin aktif untuk pencatatan produksi per shift.</p></div></div>
<button id="btnAdd" class="btn btn-light mt-3 mt-md-0"><i class="fas fa-plus mr-1"></i>Tambah Mesin</button></div></div>
<div class="card card-outline card-<?= htmlspecialchars($theme) ?>"><div class="card-header bg-<?= htmlspecialchars($theme) ?> <?= $text ?>"><strong>Daftar Mesin</strong></div><div class="card-body">
<div class="table-responsive"><table id="machineTable" class="table table-hover table-sm machine-table" style="width:100%"><thead class="thead-light"><tr><th>No</th><th>Kode</th><th>Nama Mesin</th><th>Status</th><th>Diperbarui</th><th>Oleh</th><th>Aksi</th></tr></thead><tbody>
<?php foreach($rows as $i=>$row): ?><tr><td><?= $i+1 ?></td><td><strong><?= htmlspecialchars($row['kode_mesin']) ?></strong></td><td><?= htmlspecialchars($row['nama_mesin'] ?? '-') ?></td><td><span class="badge badge-status badge-<?= $row['status_active']?'success':'secondary' ?>"><?= $row['status_active']?'Aktif':'Nonaktif' ?></span></td><td><?= htmlspecialchars(mm_dt($row['updated_at'] ?? $row['created_at'])) ?></td><td><?= htmlspecialchars($row['updated_by'] ?? $row['created_by'] ?? '-') ?></td><td><button class="btn btn-sm btn-warning btnEdit" data-row='<?= htmlspecialchars(json_encode(['id'=>(int)$row['id'],'kode_mesin'=>$row['kode_mesin'],'nama_mesin'=>$row['nama_mesin'] ?? '','status_active'=>(int)$row['status_active']]),ENT_QUOTES,'UTF-8') ?>' title="Edit mesin"><i class="fas fa-edit"></i></button> <button class="btn btn-sm btn-danger btnDelete" data-id="<?= (int)$row['id'] ?>" data-code="<?= htmlspecialchars($row['kode_mesin']) ?>" title="Hapus mesin"><i class="fas fa-trash"></i></button></td></tr><?php endforeach; ?>
</tbody></table></div></div></div></div></section></main></div>
<div class="modal fade" id="machineModal" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header bg-<?= htmlspecialchars($theme) ?> <?= $text ?>"><h2 class="modal-title h5" id="machineModalTitle">Tambah Mesin</h2><button class="close <?= $text ?>" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><form id="machineForm"><div class="modal-body"><input type="hidden" id="machine_id"><div class="form-group"><label for="kode_mesin">Kode Mesin</label><input id="kode_mesin" class="form-control text-uppercase" maxlength="20" required autocomplete="off" placeholder="Contoh: D10"></div><div class="form-group"><label for="nama_mesin">Nama Mesin</label><input id="nama_mesin" class="form-control" maxlength="100" placeholder="Nama atau keterangan mesin"></div><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="status_active" checked><label class="custom-control-label" for="status_active">Mesin aktif</label></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button type="submit" class="btn btn-<?= htmlspecialchars($theme) ?>"><i class="fas fa-save mr-1"></i>Simpan</button></div></form></div></div></div>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script><script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script>
$(function(){
 const table=$('#machineTable').DataTable({order:[[1,'asc']],language:{search:'Cari:',lengthMenu:'Tampilkan _MENU_ data',zeroRecords:'Tidak ada mesin',info:'Menampilkan _START_ - _END_ dari _TOTAL_ mesin',paginate:{next:'Selanjutnya',previous:'Sebelumnya'}}});
 const modal=$('#machineModal');
 $('#btnAdd').on('click',()=>{$('#machineForm')[0].reset();$('#machine_id').val('');$('#status_active').prop('checked',true);$('#machineModalTitle').text('Tambah Mesin');modal.modal('show');});
 $('#machineTable').on('click','.btnEdit',function(){const r=$(this).data('row');$('#machine_id').val(r.id);$('#kode_mesin').val(r.kode_mesin);$('#nama_mesin').val(r.nama_mesin);$('#status_active').prop('checked',Number(r.status_active)===1);$('#machineModalTitle').text('Edit Mesin');modal.modal('show');});
 $('#machineForm').on('submit',function(e){e.preventDefault();$.post('master_mesin_output.php',{action:'save',id:$('#machine_id').val(),kode_mesin:$('#kode_mesin').val(),nama_mesin:$('#nama_mesin').val(),status_active:$('#status_active').is(':checked')?'1':'0'},r=>Swal.fire(r.success?'Berhasil':'Gagal',r.message,r.success?'success':'error').then(()=>{if(r.success)location.reload();}),'json').fail(()=>Swal.fire('Gagal','Request tidak dapat diproses.','error'));});
 $('#machineTable').on('click','.btnDelete',function(){const id=$(this).data('id'),code=$(this).data('code');Swal.fire({icon:'warning',title:`Hapus ${code}?`,text:'Mesin yang sudah dipakai tidak dapat dihapus.',showCancelButton:true,confirmButtonText:'Hapus',cancelButtonText:'Batal'}).then(x=>{if(!x.isConfirmed)return;$.post('master_mesin_output.php',{action:'delete',id},r=>Swal.fire(r.success?'Berhasil':'Tidak dapat dihapus',r.message,r.success?'success':'warning').then(()=>{if(r.success)location.reload();}),'json');});});
});
</script></body></html>
