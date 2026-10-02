<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../koneksi.php';
if (!isset($_SESSION['UserName'])) { header('Location:/gg_app/login.php'); exit; }
function checkPermissions($conn, $groupId, $menuId) {
  $stmt = sqlsrv_query($conn, "SELECT CanView,CanAdd,CanEdit,CanDelete FROM dbo.SMGroupTrustee WHERE GroupId=? AND MenuId=?", [$groupId, $menuId]);
  $p = ['CanView'=>0,'CanAdd'=>0,'CanEdit'=>0,'CanDelete'=>0];
  if ($stmt && $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $p = $r;
  return $p;
}
$permissions = checkPermissions($conn, $_SESSION['GroupId'] ?? 0, 212);
if (($permissions['CanView'] ?? 0) != 1) { $_SESSION['error']='Anda tidak memiliki hak akses.'; header('Location:../../dashboard.php'); exit; }
$resep_id = $_GET['resep_id'] ?? '';
$next_group_id = $_GET['next_group_id'] ?? '';
$mode = $resep_id ? 'Edit' : 'Tambah';
if ($next_group_id) $mode = 'Tambah Experiment Berikutnya';
$data = ['experiment_seq'=>1,'experiment_status'=>'Draft','group_id'=>$next_group_id]; $group = ['soi'=>'','no_cp'=>'']; $details=[];
if ($resep_id) {
  $s = sqlsrv_query($conn, "SELECT e.*, g.soi, g.no_cp AS group_no_cp, g.kode_grey AS g_kode_grey, g.mesin AS g_mesin, g.kode_warna AS g_kode_warna, g.color_name AS g_color_name, g.color_desc AS g_color_desc, g.resep_prod_code AS g_resep_prod_code, g.resep_prod_name AS g_resep_prod_name, g.cus_color AS g_cus_color, g.proint_resephdid AS g_proint_resephdid, g.group_status FROM dbo.resep_obat_experiment e LEFT JOIN dbo.resep_obat_experiment_group g ON g.id=e.group_id WHERE e.id=?", [$resep_id]);
  if ($s && $r = sqlsrv_fetch_array($s, SQLSRV_FETCH_ASSOC)) $data = $r; else die('Data not found');
  $group = ['soi'=>$data['soi']??'', 'no_cp'=>$data['group_no_cp']??($data['no_cp']??'')];
  // Data teknis di form edit diambil dari row experiment itu sendiri, bukan dari group.
  $sd = sqlsrv_query($conn, "SELECT * FROM dbo.resep_obat_experiment_detail WHERE id_resep_experiment=?", [$resep_id]);
  while ($sd && $r = sqlsrv_fetch_array($sd, SQLSRV_FETCH_ASSOC)) $details[] = $r;
} elseif ($next_group_id) {
  // Load experiment terakhir dalam group sebagai template, tapi belum insert ke DB.
  $sg = sqlsrv_query($conn, "SELECT * FROM dbo.resep_obat_experiment_group WHERE id=?", [$next_group_id]);
  $gdata = $sg ? sqlsrv_fetch_array($sg, SQLSRV_FETCH_ASSOC) : null;
  if (!$gdata) die('Group not found');
  $isAdminLocal = (int)($_SESSION['GroupId'] ?? 0) === 1;
  if (!$isAdminLocal && ($gdata['group_status'] ?? '') === 'Approved') die('Group sudah Approved');
  $sl = sqlsrv_query($conn, "SELECT TOP 1 * FROM dbo.resep_obat_experiment WHERE group_id=? ORDER BY experiment_seq DESC, id DESC", [$next_group_id]);
  $last = $sl ? sqlsrv_fetch_array($sl, SQLSRV_FETCH_ASSOC) : null;
  $nextSeq = $last ? ((int)$last['experiment_seq'] + 1) : 1;
  $data = [
    'group_id'=>$next_group_id,
    'experiment_seq'=>$nextSeq,
    'experiment_status'=>'Draft',
    'experiment_note'=>'',
    'kode_grey'=>$gdata['kode_grey']??'',
    'mesin'=>$gdata['mesin']??'',
    'kode_warna'=>$gdata['kode_warna']??'',
    'color_name'=>$gdata['color_name']??'',
    'color_desc'=>$gdata['color_desc']??'',
    'resep_prod_code'=>$gdata['resep_prod_code']??'',
    'resep_prod_name'=>$gdata['resep_prod_name']??'',
    'cus_color'=>$gdata['cus_color']??'',
    'proint_resephdid'=>$gdata['proint_resephdid']??null,
    'lot_no'=>$last['lot_no']??'',
    'weight'=>$last['weight']??0,
    'plan_qty'=>$last['plan_qty']??3500,
    'vlot'=>$last['vlot']??0,
  ];
  $group = ['soi'=>$gdata['soi']??'', 'no_cp'=>$gdata['no_cp']??''];
  if ($last) {
    $sd = sqlsrv_query($conn, "SELECT * FROM dbo.resep_obat_experiment_detail WHERE id_resep_experiment=? ORDER BY id ASC", [$last['id']]);
    while ($sd && $r = sqlsrv_fetch_array($sd, SQLSRV_FETCH_ASSOC)) $details[] = $r;
  }
}
$isAdmin = (int)($_SESSION['GroupId'] ?? 0) === 1;
$isApproved = !$isAdmin && ((($data['experiment_status'] ?? '') === 'Approved') || (($data['group_status'] ?? '') === 'Approved'));
$themeColor = $_SESSION['Theme'] ?? 'primary';
include __DIR__ . '/../../../includes/header.php';
include __DIR__ . '/../../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<style>#detailTable thead th{text-align:center;vertical-align:middle;background:#f4f6f9}.select2-container--bootstrap4 .select2-dropdown{min-width:350px!important}</style>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1><?= $mode ?> Resep Obat Experiment</h1></div><div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="list_experiment.php">Experiment</a></li><li class="breadcrumb-item active"><?= $mode ?></li></ol></div></div></div></div>
  <section class="content"><div class="container-fluid">
    <form id="resepForm" novalidate>
      <input type="hidden" name="resep_id" value="<?= htmlspecialchars($resep_id) ?>">
      <input type="hidden" name="group_id" value="<?= htmlspecialchars($data['group_id'] ?? '') ?>">
      <input type="hidden" name="next_group_id" value="<?= htmlspecialchars($next_group_id) ?>">
      <input type="hidden" name="proint_resephdid" id="proint_resephdid" value="<?= htmlspecialchars($data['proint_resephdid'] ?? '') ?>">
      <div class="card card-<?= htmlspecialchars($themeColor) ?>"><div class="card-header"><h3 class="card-title">Informasi Dasar</h3></div><div class="card-body"><div class="row">
        <div class="col-md-6">
          <?php if ($resep_id): ?>
          <div class="form-group row"><label class="col-sm-4 col-form-label">Urutan Eksperimen</label><div class="col-sm-8"><input readonly class="form-control" value="EXP #<?= (int)($data['experiment_seq'] ?? 1) ?>"></div></div>
          <div class="form-group row"><label class="col-sm-4 col-form-label">Status</label><div class="col-sm-8"><select class="form-control" name="experiment_status" <?= $isApproved?'disabled':'' ?>><?php foreach(['Draft','Gagal','Sukses'] as $st): ?><option value="<?= $st ?>" <?= (($data['experiment_status']??'Draft')===$st)?'selected':'' ?>><?= $st ?></option><?php endforeach; ?></select></div></div>
          <?php else: ?>
          <input type="hidden" name="experiment_status" value="Draft">
          <?php endif; ?>
          <div class="form-group row"><label class="col-sm-4 col-form-label">Kode Grey</label><div class="col-sm-8"><input type="hidden" name="kode_grey" id="kode_grey_hidden" value="<?= htmlspecialchars($data['kode_grey'] ?? '') ?>"><select id="select_kode_grey" class="form-control select2-grey" style="width:100%" <?= $isApproved?'disabled':'' ?>><?php if(!empty($data['kode_grey'])):?><option selected value="<?= htmlspecialchars($data['kode_grey']) ?>"><?= htmlspecialchars($data['kode_grey']) ?></option><?php endif;?></select></div></div>
          <div class="form-group row"><label class="col-sm-4 col-form-label">Mesin</label><div class="col-sm-8"><input readonly class="form-control" name="mesin" id="mesin" value="<?= htmlspecialchars($data['mesin'] ?? '') ?>"></div></div>
          <div class="form-group row"><label class="col-sm-4 col-form-label">Kode Warna</label><div class="col-sm-8"><select class="form-control select2-color" name="kode_warna" style="width:100%" required <?= $isApproved?'disabled':'' ?>><?php if(!empty($data['kode_warna'])):?><option selected value="<?= htmlspecialchars($data['kode_warna']) ?>"><?= htmlspecialchars($data['kode_warna'].' - '.($data['color_name']??'')) ?></option><?php endif;?></select></div></div>
          <div class="form-group row"><label class="col-sm-4 col-form-label">Color Name</label><div class="col-sm-8"><input readonly class="form-control" id="color_name" name="color_name" value="<?= htmlspecialchars($data['color_name'] ?? '') ?>"></div></div>
          <div class="form-group row"><label class="col-sm-4 col-form-label">Description</label><div class="col-sm-8"><textarea readonly class="form-control" id="color_desc" name="color_desc" rows="2"><?= htmlspecialchars($data['color_desc'] ?? '') ?></textarea></div></div>
        </div>
        <div class="col-md-6">
          <div class="form-group row"><label class="col-sm-4 col-form-label">Resep Prod Code</label><div class="col-sm-8"><div class="input-group"><input type="text" class="form-control" id="resepprodcode" name="resepprodcode" readonly value="<?= htmlspecialchars($data['resep_prod_code'] ?? '') ?>" placeholder="Klik tombol untuk pilih"><div class="input-group-append"><button type="button" class="btn btn-default" id="btnPilihProd" <?= $isApproved?'disabled':'' ?>><i class="fas fa-ellipsis-h"></i></button></div></div></div></div>
          <div class="form-group row"><label class="col-sm-4 col-form-label">Resep Prod Name</label><div class="col-sm-8"><input readonly class="form-control" id="resepprodname" name="resepprodname" value="<?= htmlspecialchars($data['resep_prod_name'] ?? '') ?>"></div></div>
          <div class="form-group row"><label class="col-sm-4 col-form-label">Cus Color</label><div class="col-sm-8"><input readonly class="form-control" id="cus_color" name="cus_color" value="<?= htmlspecialchars($data['cus_color'] ?? '') ?>"></div></div>
          <div class="form-group row"><label class="col-sm-4 col-form-label">Lot No</label><div class="col-sm-8"><input class="form-control" name="lot_no" value="<?= htmlspecialchars($data['lot_no'] ?? '') ?>" <?= $isApproved?'readonly':'' ?>></div></div>
          <div class="form-group row"><label class="col-sm-4 col-form-label">Plan Qty</label><div class="col-sm-8"><input type="number" step="0.01" class="form-control" name="plan_qty" id="plan_qty" required value="<?= htmlspecialchars($data['plan_qty'] ?? '3500') ?>" <?= $isApproved?'readonly':'' ?>></div></div>
          <div class="form-group row"><label class="col-sm-4 col-form-label">Weight</label><div class="col-sm-8"><input class="form-control" name="weight" value="<?= htmlspecialchars($data['weight'] ?? '100') ?>" <?= $isApproved?'readonly':'' ?>></div></div>
          <div class="form-group row"><label class="col-sm-4 col-form-label">Vlot</label><div class="col-sm-8"><input type="number" step="0.01" class="form-control" name="vlot" id="vlot" value="<?= htmlspecialchars($data['vlot'] ?? '') ?>" <?= $isApproved?'readonly':'' ?>></div></div>
          <div class="form-group row"><label class="col-sm-4 col-form-label">Catatan</label><div class="col-sm-8"><textarea class="form-control" name="experiment_note" rows="2" <?= $isApproved?'readonly':'' ?>><?= htmlspecialchars($data['experiment_note'] ?? '') ?></textarea></div></div>
        </div>
      </div></div></div>
      <div class="card"><div class="card-header bg-light"><h3 class="card-title">Detail Resep Manual</h3><div class="card-tools"><?php if(!$isApproved): ?><button type="button" class="btn btn-primary btn-sm" id="btnAddRow"><i class="fas fa-plus"></i> Tambah Item</button><?php endif; ?></div></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-bordered table-sm" id="detailTable"><thead><tr><th>Kode</th><th>Name</th><th>Category</th><th>Qty</th><th>Uom</th><th>Cf</th><th>Uom Cf</th><th>Price</th><th>Total</th><th>Aksi</th></tr></thead><tbody></tbody><tfoot><tr><td colspan="8" class="text-right font-weight-bold">Grand Total</td><td colspan="2"><input readonly class="form-control-plaintext font-weight-bold text-right" id="grandVal" value="Rp 0"></td></tr></tfoot></table></div></div></div>
      <div class="row mb-4"><div class="col-12"><?php if(!$isApproved): ?><button type="submit" class="btn btn-success float-right" id="btnSave"><i class="fas fa-save"></i> Simpan Experiment</button><?php endif; ?><a href="<?= !empty($data['group_id'])?'view_group.php?group_id='.(int)$data['group_id']:'list_experiment.php' ?>" class="btn btn-secondary float-right mr-2">Kembali</a></div></div>
    </form>
  </div></section>
  <div class="modal fade" id="modalPilihProd" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content"><div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><h5 class="modal-title">Pilih Resep Prod Code</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div><div class="modal-body"><div class="table-responsive"><table id="prodTable" class="table table-bordered table-hover table-sm nowrap" style="width:100%"><thead class="thead-light"><tr><th>Resep Prod Code</th><th>Resep Prod Name</th><th>Cus Color</th><th>Prod Type</th><th>Aksi</th></tr></thead><tbody></tbody></table></div></div></div></div></div>
</div><?php include __DIR__ . '/../../../includes/footer.php'; ?>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script><script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script><script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script><script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script><script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script><script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script>
$(function(){
const isApproved=<?= $isApproved?'true':'false' ?>; let rowIdx=0,isSubmitting=false,currentColorMsid=''; function fmtNum(n){return 'Rp '+(parseFloat(n)||0).toLocaleString('id-ID',{maximumFractionDigits:2});} function parseNum(s){return parseFloat((s||'').replace(/[Rp\s.]/g,'').replace(',','.'))||0;} function fmtUS(n){return (parseFloat(n)||0).toLocaleString('en-US',{minimumFractionDigits:4,maximumFractionDigits:4});} function escapeHtml(s){return (s==null?'':String(s)).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
$('.select2-color').select2({theme:'bootstrap4',ajax:{url:'get_colors.php',dataType:'json',delay:250,data:p=>({q:p.term}),processResults:d=>({results:d.results})},placeholder:'Cari Kode Warna',minimumInputLength:2}).on('select2:select',function(e){let d=e.params.data.color_data||{};currentColorMsid=d.colormsid||'';$('#color_name').val(d.name||'');$('#color_desc').val(d.desc||'');$('#resepprodcode,#resepprodname,#cus_color,#proint_resephdid').val('');if(prodTable)prodTable.ajax.reload();});
let prodTable=null; function initProdTable(){if(prodTable)return;prodTable=$('#prodTable').DataTable({processing:true,serverSide:true,responsive:true,ajax:{url:'serverside_resep_prod.php',type:'POST',data:d=>{d.colormsid=currentColorMsid;}},columns:[{data:'resepprodcode'},{data:'resepprodname'},{data:'cuscolor'},{data:'prodtypecode'},{data:null,orderable:false,searchable:false,className:'text-center',render:(d,t,row)=>`<button type="button" class="btn btn-primary btn-sm btn-select-prod" data-id="${row.resephdid}" data-code="${escapeHtml(row.resepprodcode)}" data-name="${escapeHtml(row.resepprodname)}" data-cus="${escapeHtml(row.cuscolor)}"><i class="fas fa-check"></i> Pilih</button>`}],language:{processing:'Sedang memproses...',search:'Cari:',lengthMenu:'Tampilkan _MENU_ data',zeroRecords:'Tidak ada Resep Prod Code ditemukan',info:'Menampilkan _START_ - _END_ dari _TOTAL_ data',infoEmpty:'Tidak ada data',paginate:{next:'Selanjutnya',previous:'Sebelumnya'}}});}
$('#btnPilihProd,#resepprodcode').on('click',function(){if(isApproved)return;if(!currentColorMsid){Swal.fire('Pilih Kode Warna','Silakan pilih Kode Warna terlebih dahulu.','warning');return;}initProdTable();$('#modalPilihProd').modal('show');prodTable.ajax.reload();}); $(document).on('click','.btn-select-prod',function(){$('#proint_resephdid').val($(this).data('id')||'');$('#resepprodcode').val($(this).data('code')||'');$('#resepprodname').val($(this).data('name')||'');$('#cus_color').val($(this).data('cus')||'');$('#modalPilihProd').modal('hide');});
$('.select2-grey').select2({theme:'bootstrap4',ajax:{url:'get_grey_items.php',dataType:'json',delay:250,data:p=>({q:p.term}),processResults:d=>({results:d.results})},placeholder:'Cari Kode Grey',minimumInputLength:1}).on('select2:select',function(e){let d=e.params.data.item_data||{};$('#kode_grey_hidden').val(d.kode_gray||'');$('#mesin').val(d.padry?((String(d.padry).toUpperCase().includes('PAD')?'':'Paddry ')+d.padry):'');let g=parseFloat(d.gramasi)||0,p=parseFloat(d.pickup)||0;$('input[name="weight"]').data('gramasi',g).data('pickup',p);calcWeight();});
function calcWeight(){let g=parseFloat($('input[name="weight"]').data('gramasi'))||0,q=parseFloat($('#plan_qty').val())||0;if(g>0){$('input[name="weight"]').val(((g*q)/1000).toLocaleString('en-US',{minimumFractionDigits:4,maximumFractionDigits:4}));calcVlot();}} function calcVlot(){let w=parseFloat(($('input[name="weight"]').val()||'').replace(/,/g,''))||0,p=parseFloat($('input[name="weight"]').data('pickup'))||0;if(p>0)$('#vlot').val(Math.ceil((w*p)/10)*10).trigger('input');} $('#plan_qty').on('input',calcWeight);$('input[name="weight"]').on('input',calcVlot);
function addRow(data={}){rowIdx++;let ro=isApproved?'readonly':'';let dis=isApproved?'disabled':'';let html=`<tr id="row_${rowIdx}"><td><select class="form-control select2-item" name="items[${rowIdx}][kode]" style="width:100%" ${dis}>${data.kode?`<option selected value="${data.kode}">${data.kode} - ${data.name||''}</option>`:''}</select><input type="hidden" name="items[${rowIdx}][is_manual]" value="1"></td><td><input readonly class="form-control form-control-sm item-name" name="items[${rowIdx}][name]" value="${data.name||''}"></td><td><input readonly class="form-control form-control-sm item-category" name="items[${rowIdx}][category]" value="${data.category||''}"></td><td><input class="form-control form-control-sm item-qty" name="items[${rowIdx}][receipe]" value="${data.receipe?fmtUS(data.receipe):''}" ${ro}></td><td><input readonly class="form-control form-control-sm item-uom" name="items[${rowIdx}][uom]" value="${data.uom||''}"></td><td><input class="form-control form-control-sm item-cf" name="items[${rowIdx}][cf]" value="${data.cf?fmtUS(data.cf):''}" ${ro}></td><td><input class="form-control form-control-sm item-uom-cf" name="items[${rowIdx}][uom_cf]" value="${data.uom_cf||''}" ${ro}></td><td><input readonly class="form-control form-control-sm item-price" name="items[${rowIdx}][std_price]" value="${data.std_price?fmtNum(data.std_price):''}"><input type="hidden" name="items[${rowIdx}][price_satuan]" class="item-price-satuan" value="${data.price_satuan||''}"><input type="hidden" name="items[${rowIdx}][price_source]" class="item-price-source" value="${data.price_source||''}"><small class="text-danger price-source"></small></td><td><input readonly class="form-control form-control-sm row-total" value="Rp 0"></td><td class="text-center">${isApproved?'':`<button type="button" class="btn btn-danger btn-xs btn-remove"><i class="fas fa-trash"></i></button>`}</td></tr>`;$('#detailTable tbody').append(html);let $s=$(`#row_${rowIdx} .select2-item`).select2({theme:'bootstrap4',ajax:{url:'get_items.php',dataType:'json',delay:250,data:p=>({q:p.term}),processResults:d=>({results:d.results})},placeholder:'Cari Kode / Nama',minimumInputLength:1});$s.on('select2:select',function(e){let it=e.params.data.item_data,$r=$(this).closest('tr');$r.find('.item-name').val(it.name);$r.find('.item-category').val(it.group_obat);$r.find('.item-uom').val(it.uom);$r.find('.item-uom-cf').val(it.uom_raw);if(it.codeprod){$.getJSON('get_item_price.php',{prodcode:it.codeprod},function(resp){$r.find('.item-price').val(fmtNum(resp.price||0));$r.find('.item-price-source').val(resp.source||'');$r.find('.item-price-satuan').val(resp.satuan||'');calcRow($r);});}calcRow($r);});calcRow($('#row_'+rowIdx));}
function calcRow($r){let qty=parseFloat(($r.find('.item-qty').val()||'').replace(/,/g,''))||0,price=parseNum($r.find('.item-price').val()),total=qty*price,uom=($r.find('.item-uom').val()||'').toUpperCase();if(uom==='GR'||uom==='G/L')total/=1000;$r.find('.row-total').val(fmtNum(total));calcAll();} function calcAll(){let gt=0;$('.row-total').each(function(){gt+=parseNum($(this).val());});$('#grandVal').val(fmtNum(gt));} function calcRowQty($r){let v=parseFloat($('#vlot').val())||0,cf=parseFloat(($r.find('.item-cf').val()||'').replace(/,/g,''))||0;$r.find('.item-qty').val(fmtUS(v*cf));calcRow($r);} $(document).on('input','.item-qty',function(){calcRow($(this).closest('tr'));});$(document).on('input','.item-cf,#vlot',function(){if(this.id==='vlot')$('#detailTable tbody tr').each(function(){calcRowQty($(this));});else calcRowQty($(this).closest('tr'));});$(document).on('click','.btn-remove',function(){$(this).closest('tr').remove();calcAll();});$('#btnAddRow').click(()=>addRow());
<?php if($details): ?>let saved=<?= json_encode($details) ?>; saved.forEach(d=>addRow(d));<?php else: ?>for(let i=0;i<5;i++)addRow();<?php endif; ?>
$('#resepForm').on('submit',function(e){e.preventDefault();if(isSubmitting||isApproved)return;isSubmitting=true;$('#btnSave').prop('disabled',true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');$.ajax({url:'save_resep.php',type:'POST',data:new FormData(this),contentType:false,processData:false,dataType:'json'}).done(function(r){if(r.status==='success')Swal.fire('Sukses','Data berhasil disimpan.','success').then(()=>location.href='view_group.php?group_id='+r.group_id);else{isSubmitting=false;$('#btnSave').prop('disabled',false).html('<i class="fas fa-save"></i> Simpan Experiment');Swal.fire('Gagal',r.message||'Gagal menyimpan.','error');}}).fail(function(x){isSubmitting=false;$('#btnSave').prop('disabled',false).html('<i class="fas fa-save"></i> Simpan Experiment');Swal.fire('Error','Gagal menyimpan data.','error');console.error(x.responseText);});});
(function initColorMsidFromExisting(){const existingCode=$('.select2-color').val();if(!existingCode)return;$.getJSON('get_colors.php',{q:existingCode},function(resp){const match=(resp.results||[]).find(r=>String(r.id)===String(existingCode));if(match&&match.color_data){currentColorMsid=match.color_data.colormsid||'';if(!$('#color_name').val())$('#color_name').val(match.color_data.name||'');if(!$('#color_desc').val())$('#color_desc').val(match.color_data.desc||'');}});})();
});
</script>