<?php
// gg_app/pages/si-iin/ht/index.php
date_default_timezone_set('Asia/Jakarta');

// koneksi + boot (pastikan $pdo tersedia untuk SIIN)
require_once __DIR__ . '/../../../koneksi.php';

$BOOT = realpath(__DIR__ . '/../_shared/boot.php');
if (!$BOOT) { $BOOT = realpath($_SERVER['DOCUMENT_ROOT'] . '/gg_app/pages/si-iin/_shared/boot.php'); }
if ($BOOT) { require_once $BOOT; }

// permission helper (userPermissions) jika tersedia
require_once __DIR__ . '/../../../includes/permissions.php';

// layout (header/sidebar/footer handle session + basic assets)
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';

if (!isset($_SESSION['UserName'])) {
  $_SESSION['error'] = "Silakan login terlebih dahulu!";
  header('Location: /login.php'); exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';

$groupId = $_SESSION['GroupId'] ?? null;
$menuId = 132;
$permissions = function_exists('userPermissions') ? userPermissions($conn ?? null, $menuId) : [];
$canAdd = isset($permissions['CanAdd']) ? ((int)$permissions['CanAdd'] === 1) : true;
?>
<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid d-flex justify-content-between">
      <h1 class="m-0">Serah Terima</h1>
      <ol class="breadcrumb float-sm-right">
        <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
        <li class="breadcrumb-item active">Serah Terima</li>
      </ol>
    </div>
  </div>


  <style>
    /* Scrollbar horizontal untuk tabel utama dan tabel item */
    .table-responsive-scroll {
      width: 100%;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }
    .table-responsive-scroll table {
      min-width: 800px;
    }
  </style>

  <section class="content"><div class="container-fluid">
    <div class="card">
      <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h3 class="card-title">Daftar Serah Terima</h3>
        <?php if ($canAdd): ?>
        <button class="btn btn-success btn-sm float-right" id="btnAdd"><i class="fas fa-plus"></i> Serah Terima</button>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <div class="table-responsive-scroll">
          <table id="tbl" class="table table-sm table-hover" style="width:100%">
            <thead class="thead-light"><tr>
              <th>No</th><th>Tanggal</th><th>No GRN</th><th>Nomor IR</th><th>Karyawan</th><th>Departemen</th>
              <th>Produk</th><th>Status</th><th>Aksi</th>
            </tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal Create / Edit / View HT -->
    <div class="modal fade" id="modalHT" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl"><div class="modal-content">
        <form id="formHT" autocomplete="off">
          <input type="hidden" name="mode" value="create">
          <input type="hidden" name="id" value="">
          <input type="hidden" name="id_kode" id="id_kode" value="">
          <div class="modal-header">
            <h5 class="modal-title" id="modalTitle">Buat Serah Terima</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <div class="form-row align-items-end">
              <div class="form-group col-md-3">
                <label>Tanggal</label>
                <input type="text" name="ht_date" class="form-control datepicker" placeholder="dd-mm-yyyy" required>
              </div>
              <div class="form-group col-md-3">
                <label>Nomor IR</label>
                <select name="ir_no" id="ir_no" class="form-control sel-ir" required></select>
              </div>
              <div class="form-group col-md-3">
                <label>Karyawan</label>
                <select name="emp_name" id="emp_name" class="form-control sel-emp" required></select>
              </div>
              <div class="form-group col-md-3">
                <label>Departemen</label>
                <input type="text" name="dept_name" class="form-control" placeholder="Otomatis" readonly>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-md-4">
                <label>Aset Karyawan</label>
                <select name="emp_asset_id" id="emp_asset_id" class="form-control sel-asset" disabled></select>
              </div>
            </div>

            <div class="form-group">
              <label>Deskripsi</label>
              <input type="text" name="descr" class="form-control" placeholder="Catatan tambahan">
            </div>

            <div class="d-flex justify-content-between align-items-center">
              <h6 class="mb-2">Item</h6>
              <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddRow">
                <i class="fas fa-plus"></i> Tambah Item
              </button>
            </div>
            <div class="table-responsive-scroll">
              <table class="table table-sm table-bordered table-items" id="tblItems">
                <thead class="thead-light"><tr>
                  <th style="width:20%">Product Code</th>
                  <th style="width:28%">Product Name</th>
                  <th style="width:12%">UOM</th>
                  <th style="width:12%">Qty</th>
                  <th style="width:12%">Stock (IR)</th>
                  <th style="width:4%"></th>
                </tr></thead><tbody></tbody>
              </table>
            </div>

          </div>
          <div class="modal-footer">
            <span class="note-approved d-none" id="ApprovedNote"><i class="fas fa-lock"></i> Dokumen telah disetujui. Form dikunci.</span>
            <button type="button" class="btn btn-warning d-none" id="btnEditMode">
              <i class="fas fa-edit"></i> Serah Terima
            </button>
            <button class="btn btn-<?= htmlspecialchars($themeColor) ?>" id="btnSave"><i class="fas fa-save"></i> Simpan</button>
            <button class="btn btn-secondary" type="button" data-dismiss="modal">Tutup</button>
          </div>
        </form>
      </div></div>
    </div>

    <!-- Modal Approve -->
    <div class="modal fade" id="modalApprove" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog"><div class="modal-content">
        <form id="formApprove">
          <input type="hidden" name="id" id="ap_id">
          <input type="hidden" name="sign_image_data" id="ap_sign_data">
          <div class="modal-header">
            <h5 class="modal-title">Approve & Tanda Tangan (Yang Menerima)</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label>Nama Penanda Tangan</label>
              <input type="text" class="form-control" id="ap_sign_name" name="sign_name" required>
            </div>
            <div class="sig-box"><canvas id="sigCanvas"></canvas></div>
            <div class="mt-2">
              <button type="button" class="btn btn-sm btn-outline-secondary" id="btnClearSig"><i class="fas fa-eraser"></i> Hapus</button>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-<?= htmlspecialchars($themeColor) ?>" id="btnApprovedo"><i class="fas fa-check"></i> Simpan & Approve</button>
            <button class="btn btn-secondary" type="button" data-dismiss="modal">Batal</button>
          </div>
        </form>
      </div></div>
    </div>

    <!-- Modal E-Sign Menyerahkan -->
    <div class="modal fade" id="modalSender" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog"><div class="modal-content">
        <form id="formSender">
          <input type="hidden" name="id" id="sender_id">
          <input type="hidden" name="sign_image_data" id="sender_sign_data">
          <div class="modal-header">
            <h5 class="modal-title">Tanda Tangan Yang Menyerahkan</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label>Nama Penanda Tangan</label>
              <input type="text" class="form-control" id="sender_sign_name" name="sign_name" readonly>
            </div>
            <div class="sig-box"><canvas id="sigCanvasSender"></canvas></div>
            <div class="mt-2">
              <button type="button" class="btn btn-sm btn-outline-secondary" id="btnClearSigSender">
                <i class="fas fa-eraser"></i> Hapus
              </button>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-<?= htmlspecialchars($themeColor) ?>" id="btnSenderDo">
              <i class="fas fa-check"></i> Simpan Tanda Tangan
            </button>
            <button class="btn btn-secondary" type="button" data-dismiss="modal">Batal</button>
          </div>
        </form>
      </div></div>
    </div>

  </div></section>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>

<!-- tambahan assets (footer biasanya memuat jQuery/Bootstrap/AdminLTE) -->
<link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">
<link rel="stylesheet" href="/gg_app/plugins/css/bootstrap-datepicker.min.css">

<script src="/gg_app/plugins/js/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/js/datatables/dataTables.bootstrap5.min.js"></script>
<script src="/gg_app/plugins/js/bootstrap-datepicker.min.js"></script>
<script src="/gg_app/plugins/js/bootstrap-datepicker.id.min.js"></script>
<script src="/gg_app/plugins/js/select2.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
/* === FULL JS IIFE (disesuaikan) ===
   Saya memasukkan seluruh script JS IIFE yang Anda kirim sebelumnya,
   hanya memastikan endpoint dan mode sesuai dengan server-side script Anda.
   Di sini saya paste skrip utuh (sedikit diformat).
*/
let IR_CACHE = { ir_no:null, outstandingByPid:{} };
let IR_LOAD_TOKEN = 0;
let FORM_LOADING = false;
let CURRENT_EMP_ID = null;

function dmY(dateStr){
  if(!dateStr) return '';
  const p = String(dateStr).split('-'); if(p.length!==3) return dateStr;
  return [p[2],p[1],p[0]].join('-');
}

function resetFormHT() {
  $('#formHT')[0].reset();
  $('#formHT').removeClass('readonly');
  $('#ApprovedNote').addClass('d-none');
  $('#btnSave').removeClass('btn-save-hidden');
  $('#btnEditMode').addClass('d-none');
  $('#btnAddRow').show();
  $('#tblItems .btnDelRow').show();

  const $ir = $('#ir_no'), $emp = $('#emp_name'), $asset = $('#emp_asset_id');
  if ($ir.hasClass('select2-hidden-accessible')) $ir.select2('destroy');
  if ($emp.hasClass('select2-hidden-accessible')) $emp.select2('destroy');
  if ($asset.hasClass('select2-hidden-accessible')) $asset.select2('destroy');

  $ir.empty().val(null);
  $emp.empty().val(null);
  $asset.empty().val(null).prop('disabled', true);

  $('input[name=dept_name]').val('');
  $('input[name=id]').val('');
  $('input[name=mode]').val('create');
  $('#id_kode').val('');

  $('#tblItems tbody').empty();

  IR_CACHE = { ir_no:null, outstandingByPid:{} };
  IR_LOAD_TOKEN++;
  FORM_LOADING = false;
  CURRENT_EMP_ID = null;
  $('#sectionSender').addClass('d-none');
}

(function(){
  $('.datepicker').datepicker({format:'dd-mm-yyyy', language:'id', autoclose:true, todayHighlight:true});

  const table = $('#tbl').DataTable({
    serverSide:true, processing:true, responsive:true, autoWidth:false,
    ajax:{ url:'./ht_serverside.php', type:'POST' },
    columns:[
      {data:null, render:(d,t,r,m)=> m.row+1+m.settings._iDisplayStart},
      {data:'ht_date'}, {data:'no_grn'}, {data:'ir_no'},
      {data:'emp_name'}, {data:'dept_name'}, {data:'produk'},
      {data:'status'}, {data:'aksi'}
    ]
  });

  function ensureUom($select, id, name){
    if(!id) return;
    if(name && name.toString().trim()!==''){
      const opt = new Option(name, id, true, true);
      $select.append(opt).trigger('change');
      return;
    }
    $.get('./ht_serverside.php', {mode:'uom_name', id:id}, function(res){
      const text = (res && res.uomname) ? res.uomname : id;
      const opt = new Option(text, id, true, true);
      $select.append(opt).trigger('change');
    }, 'json');
  }

  function setSelect2Value($sel, value, text){
    if(!value) return;
    if(!$sel.find('option[value="'+value+'"]').length){
      const label = (text && String(text).trim()!=='') ? text : value;
      $sel.append(new Option(label, value, true, true));
    }
    $sel.val(value).trigger('change.select2');
  }

  function refreshAllRowStocks(){
    $('#tblItems tbody tr').each(function(){
      const $r = $(this);
      const pidCode = $r.find('.sel-prod-code').val();
      const pidName = $r.find('.sel-prod-name').val();
      const pid = parseInt(pidCode || pidName || 0, 10) || 0;
      if(pid>0){
        const outstanding = IR_CACHE.outstandingByPid[pid] || 0;
        $r.find('.cell-stock').html('<span class="stock-pill">'+ outstanding +'</span>');
      }
    });
  }

  function buildIRCacheOnly(ir_no){
    const myToken = ++IR_LOAD_TOKEN;
    IR_CACHE.ir_no = ir_no;
    IR_CACHE.outstandingByPid = {};
    return $.get('./ht_serverside.php', {mode:'ir_items', ir_no: ir_no}, null, 'json')
      .then(function(res){
        if (myToken !== IR_LOAD_TOKEN) return;
        const items = (res && res.success && Array.isArray(res.items)) ? res.items : [];
        items.forEach(it=>{
          const pid = parseInt(it.product_id,10)||0;
          const ost = parseFloat(it.outstanding_qty||0) || 0;
          IR_CACHE.outstandingByPid[pid] = (IR_CACHE.outstandingByPid[pid]||0) + ost;
        });
      });
  }

  function initIRSelect($sel){
    $sel.select2({
      theme:'bootstrap',
      placeholder:'Pilih IR',
      minimumInputLength: 0,
      dropdownParent: $('#modalHT'),
      ajax:{
        url:'./ht_serverside.php',
        dataType:'json',
        delay:200,
        data:(p)=>({q:(p.term||''), mode:'ir'}),
        processResults:(d)=>({results:d})
      }
    })
    .on('select2:select', function(e){
      if (FORM_LOADING) return;
      const d = e.params.data || {};
      const ir = d && d.id ? d.id : null;
      if (ir) loadIRItems(ir);
    })
    .on('change', function(){
      if (FORM_LOADING) return;
      const ir = $(this).val();
      if (ir) loadIRItems(ir);
    });
  }

  function initAssetSelect($sel){
    $sel.select2({
      theme: 'bootstrap',
      placeholder: 'Pilih Aset',
      dropdownParent: $('#modalHT'),
      ajax: {
        url: './ht_serverside.php',
        dataType: 'json',
        delay: 200,
        data: function(params){
          return {
            mode: 'emp_assets',
            id_emp: CURRENT_EMP_ID || '',
            q: params.term || ''
          };
        },
        processResults: function(data){
          return { results: data };
        }
      }
    }).on('select2:select', function(e){
      const d = e.params.data || {};
      $('#id_kode').val(d.id_kode || '');
    });
  }

  function initEmpSelect($sel){
    $sel.select2({
      theme:'bootstrap',
      placeholder:'Pilih Karyawan',
      dropdownParent: $('#modalHT'),
      ajax:{
        url:'./ht_serverside.php',
        dataType:'json',
        delay:200,
        data:(p)=>({q:p.term,mode:'emp'}),
        processResults:(d)=>({results:d})
      }
    }).on('select2:select', function(e){
      const d=e.params.data||{};
      if(d.dept){ $('input[name=dept_name]').val(d.dept); }

      CURRENT_EMP_ID = d.id || null;
      const $asset = $('#emp_asset_id');

      if (CURRENT_EMP_ID) {
        if (!$asset.hasClass('select2-hidden-accessible')) {
          initAssetSelect($asset);
        }
        $asset.prop('disabled', false).val(null).trigger('change');
        $('#id_kode').val('');
      } else {
        $asset.prop('disabled', true).val(null).trigger('change');
        $('#id_kode').val('');
      }
    });
  }

  function buildProductSelect($el, mode){
    $el.select2({
      theme:'bootstrap',
      tags:true,
      placeholder: mode==='code' ? 'Pilih / ketik kode' : 'Pilih / ketik nama',
      dropdownParent: $('#modalHT'),
      ajax:{
        url:'../ir/ref_product.php',
        dataType:'json',
        delay:250,
        data:params=>({q:params.term,mode:mode}),
        processResults:data=>({results:data})
      }
    });
  }
  function buildUomSelect($el){
    $el.select2({
      theme:'bootstrap',
      tags:true,
      placeholder:'UOM',
      dropdownParent: $('#modalHT'),
      ajax:{
        url:'../ir/ref_uom.php',
        dataType:'json',
        delay:200,
        data:params=>({q:params.term}),
        processResults:data=>({results:data})
      }
    });
  }

  function showIRStock($row, product_id){
    const outstanding = IR_CACHE.outstandingByPid[product_id] || 0;
    $row.find('.cell-stock').html('<span class="stock-pill">'+ outstanding +'</span>');
  }

  function loadIRItems(ir_no){
    if (FORM_LOADING) return;
    const myToken = ++IR_LOAD_TOKEN;
    IR_CACHE.ir_no = ir_no;
    IR_CACHE.outstandingByPid = {};
    $('#tblItems tbody').empty();

    return $.get('./ht_serverside.php', {mode:'ir_items', ir_no: ir_no}, null, 'json')
      .then(function(res){
        if (myToken !== IR_LOAD_TOKEN) return;
        const ok = res && res.success;
        const items = ok ? (res.items||[]) : [];

        items.forEach(it=>{
          const pid = parseInt(it.product_id,10)||0;
          const ost = parseFloat(it.outstanding_qty||0);
          IR_CACHE.outstandingByPid[pid] = (IR_CACHE.outstandingByPid[pid]||0) + ost;
        });

        if(!items.length){ addRow(); return; }
        items.forEach(it=>{
          const defQty = (parseFloat(it.outstanding_qty||0)>0) ? parseFloat(it.outstanding_qty||0) : '';
          addRow({
            product_id: it.product_id,
            prod_code: it.prod_code,
            prod_name: it.prod_name,
            uom_id: it.uom_id,
            uomname: it.uomname,
            qty: defQty
          });
        });

        refreshAllRowStocks();
      })
      .fail(function(){
        if (myToken !== IR_LOAD_TOKEN) return;
        addRow();
      });
  }

  async function suggestIRForProduct(product_id){
    if(!product_id) return null;
    try{
      const res = await $.get('./ht_serverside.php', {mode:'ir_suggest_by_product', product_id: product_id});
      if(res && res.success && res.ir && res.ir.ir_no) return res.ir.ir_no;
    }catch(e){}
    return null;
  }

  function linkSync($row){
    const $code = $row.find('.sel-prod-code');
    const $name = $row.find('.sel-prod-name');
    const $uom  = $row.find('.sel-uom');

    async function afterPickProduct(d){
      if(!d || !d.id || String(d.id).startsWith('tag:')) return;
      if(d.prod_name){
        const optName = new Option(d.prod_name, d.id, true, true);
        $name.append(optName).trigger('change');
      }
      if(d.uom_id || d.uomname){
        const uid = d.uom_id || d.uomname;
        const uname= d.uomname || null;
        ensureUom($uom, uid, uname);
      }

      const pid = parseInt(d.id,10);
      const currentIR = $('#ir_no').val();
      const bestIR = await suggestIRForProduct(pid);

      if(bestIR && bestIR !== currentIR){
        const $ir = $('#ir_no');
        if(!$ir.find('option[value="'+bestIR+'"]').length){
          $ir.append(new Option(bestIR+' (AUTO)', bestIR, true, true));
        }
        $ir.val(bestIR).trigger('change');
      } else if (currentIR) {
        await loadIRItems(currentIR);
      }

      showIRStock($row, pid);
      const outQty = IR_CACHE.outstandingByPid[pid] || 0;
      if(!$row.find('.inp-qty').val()) $row.find('.inp-qty').val(outQty);
    }

    $code.on('select2:select', e => afterPickProduct(e.params.data||{}));
    $name.on('select2:select', e => {
      const d=e.params.data||{};
      if(d && d.id && !String(d.id).startsWith('tag:')){
        const label = d.prod_code ? d.prod_code : d.text;
        const optCode=new Option(label, d.id, true, true);
        $code.append(optCode).trigger('change');
      }
      afterPickProduct(d);
    });
  }

  function rowHtml(idx){
    return `<tr>
      <td><select class="form-control sel-prod-code" name="items[${idx}][product_id_code]"></select></td>
      <td><select class="form-control sel-prod-name" name="items[${idx}][product_id_name]"></select></td>
      <td><select class="form-control sel-uom" name="items[${idx}][uom_id]"></select></td>
      <td><input type="number" name="items[${idx}][qty]" class="form-control inp-qty" min="1" required></td>
      <td class="cell-stock text-center"><span class="stock-pill">0</span></td>
      <td><button type="button" class="btn btn-sm btn-outline-danger btnDelRow"><i class="fas fa-times"></i></button></td>
    </tr>`;
  }
  function enhanceRow($row, pref){
    buildProductSelect($row.find('.sel-prod-code'),'code');
    buildProductSelect($row.find('.sel-prod-name'),'name');
    buildUomSelect($row.find('.sel-uom'));
    linkSync($row);

    if(pref){
      if(pref.product_id){
        $row.find('.sel-prod-code')
            .append(new Option(pref.prod_code||pref.product_id, pref.product_id, true, true)).trigger('change');
        $row.find('.sel-prod-name')
            .append(new Option(pref.prod_name||pref.product_id, pref.product_id, true, true)).trigger('change');
        showIRStock($row, pref.product_id);
      }
      if(pref.uom_id){ ensureUom($row.find('.sel-uom'), pref.uom_id, pref.uomname||null); }
      if(pref.qty){ $row.find('.inp-qty').val(pref.qty); }
    }
  }
  function addRow(pref){
    const idx = $('#tblItems tbody').children().length;
    const $row = $(rowHtml(idx)).appendTo('#tblItems tbody');
    enhanceRow($row, pref);
  }
  $('#btnAddRow').on('click', ()=>addRow());
  $(document).on('click','.btnDelRow',function(){ $(this).closest('tr').remove(); });

  // ==== Open modal add (Serah Terima baru) ====
  $('#btnAdd').on('click', function(){
   resetFormHT();
    addRow();

    const $ir  = $('#ir_no');
    const $emp = $('#emp_name');
    const $asset = $('#emp_asset_id');
    initIRSelect($ir);
    initEmpSelect($emp);
    $asset.prop('disabled', true);

    $('#sectionSender').removeClass('d-none'); // kalau dipakai

    $('#modalTitle').text('Buat Serah Terima');
    $('#modalHT').modal('show');
  });

  $('#modalHT').on('hidden.bs.modal', function () {
    resetFormHT();
  });

  // ==== Submit save ====
  $('#formHT').on('submit', function(e){
    e.preventDefault();
    const ir_no = $('#ir_no').val();
    if(!ir_no){
      Swal.fire({icon:'warning',title:'Pilih IR dahulu'});
      return;
    }

    const totalByPid = {};
    $('#tblItems tbody tr').each(function(){
      const $r=$(this);
      const pidCode = $r.find('.sel-prod-code').val();
      const pidName = $r.find('.sel-prod-name').val();
      const pid = parseInt(pidCode||pidName||0,10)||0;
      const q = parseFloat($r.find('.inp-qty').val()||0)||0;
      if(pid>0 && q>0){ totalByPid[pid] = (totalByPid[pid]||0) + q; }
    });
    for(const pid in totalByPid){
      const need = totalByPid[pid];
      const ost  = IR_CACHE.outstandingByPid[pid] || 0;
      if(need > ost){
        Swal.fire({icon:'error',title:'Qty melebihi outstanding IR', text:'Ada produk yang qty input melebihi sisa IR.'});
        return;
      }
    }

    // Konversi items[] ke prod_code[], uom_id[], qty[] agar backend tetap kompatibel
    // Hapus field prod_code[], uom_id[], qty[] lama
    $(this).find('input[name="prod_code[]"], input[name="uom_id[]"], input[name="qty[]"], select[name="prod_code[]"], select[name="uom_id[]"], input[name="qty[]"]').remove();
    // Ambil data dari baris item
    $('#tblItems tbody tr').each(function(){
      const $r = $(this);
      // Product ID
      const pid = $r.find('.sel-prod-code').val() || '';
      // UOM
      const uom = $r.find('.sel-uom').val() || '';
      // Qty
      const qty = $r.find('.inp-qty').val() || '';
      // Tambahkan hidden input ke form
      $('<input type="hidden" name="prod_code[]">').val(pid).appendTo('#formHT');
      $('<input type="hidden" name="uom_id[]">').val(uom).appendTo('#formHT');
      $('<input type="hidden" name="qty[]">').val(qty).appendTo('#formHT');
    });

    const mode = $('input[name=mode]').val() || 'create';
    const url = (mode === 'update' || mode === 'edit') ? './ht_update.php' : './ht_save.php';
    $.post(url, $(this).serialize(), function(res){
      if(res && res.success){
        $('#modalHT').modal('hide'); table.ajax.reload(null,false);
        Swal.fire({icon:'success',title:'Disimpan',timer:1200,showConfirmButton:false});
      } else {
        Swal.fire({icon:'error',title:'Gagal',text:res.message||'Error'});
      }
    }, 'json').fail(()=>Swal.fire({icon:'error',title:'Gagal koneksi'}));
  });

  // ==== Approve / etc tetap ====
  let cvs,ctx;
  function initCanvas(){
    cvs=document.getElementById('sigCanvas'); ctx=cvs.getContext('2d');
    const dpr=window.devicePixelRatio||1; const w=cvs.parentElement.clientWidth||420; const h=140;
    cvs.width=w*dpr; cvs.height=h*dpr; cvs.style.width=w+'px'; cvs.style.height=h+'px'; ctx.scale(dpr,dpr);
    ctx.fillStyle='#fff'; ctx.fillRect(0,0,w,h);
    let drawing = false, lastX = 0, lastY = 0;
    function drawLine(x, y) {
      ctx.lineTo(x, y);
      ctx.stroke();
    }
    function getPos(e) {
      const rect = cvs.getBoundingClientRect();
      if (e.touches && e.touches.length === 1) {
        return {
          x: e.touches[0].clientX - rect.left,
          y: e.touches[0].clientY - rect.top
        };
      } else if (typeof e.offsetX !== 'undefined') {
        return { x: e.offsetX, y: e.offsetY };
      } else if (typeof e.clientX !== 'undefined') {
        return {
          x: e.clientX - rect.left,
          y: e.clientY - rect.top
        };
      }
      return {x:0, y:0};
    }
    // Pointer events (modern)
    cvs.onpointerdown = function(e) {
      const pos = getPos(e);
      drawing = true;
      ctx.beginPath();
      ctx.moveTo(pos.x, pos.y);
      lastX = pos.x; lastY = pos.y;
    };
    cvs.onpointermove = function(e) {
      if (!drawing) return;
      const pos = getPos(e);
      drawLine(pos.x, pos.y);
      lastX = pos.x; lastY = pos.y;
    };
    cvs.onpointerup = cvs.onpointerleave = function(e) {
      drawing = false;
    };
    // Touch events (for iOS/Android fallback)
    cvs.addEventListener('touchstart', function(e) {
      const pos = getPos(e);
      drawing = true;
      ctx.beginPath();
      ctx.moveTo(pos.x, pos.y);
      lastX = pos.x; lastY = pos.y;
      e.preventDefault();
    }, {passive:false});
    cvs.addEventListener('touchmove', function(e) {
      if (!drawing) return;
      const pos = getPos(e);
      drawLine(pos.x, pos.y);
      lastX = pos.x; lastY = pos.y;
      e.preventDefault();
    }, {passive:false});
    cvs.addEventListener('touchend', function(e) {
      drawing = false;
      e.preventDefault();
    }, {passive:false});
    $('#btnClearSig').off('click').on('click',()=>{ctx.clearRect(0,0,w,h);ctx.fillStyle='#fff';ctx.fillRect(0,0,w,h);});
  }
  $(document).on('click','.btn-approve',function(){
    const id = $(this).data('id');
    $('#ap_id').val(id);
    $.get('./ht_serverside.php', {mode:'ht_sender', id:id}, function(res){
      if (res && res.success && res.name) {
        $('#ap_sign_name').val(res.name);
      } else {
        $('#ap_sign_name').val('');
      }
      $('#modalApprove').modal({backdrop:'static', keyboard:false});
      setTimeout(initCanvas,150);
    }, 'json').fail(function(){
      $('#ap_sign_name').val('');
      $('#modalApprove').modal({backdrop:'static', keyboard:false});
      setTimeout(initCanvas,150);
    });
  });
  $('#formApprove').on('submit',function(e){
    e.preventDefault();
    const dataUrl=cvs.toDataURL('image/png'); $('#ap_sign_data').val(dataUrl);
    $.post('./ht_approve.php', $(this).serialize(), function(res){
      if(res.success){
        $('#modalApprove').modal('hide');
        table.ajax.reload(null,false);
        Swal.fire({icon:'success',title:'Approved',timer:1000,showConfirmButton:false});
      }
      else Swal.fire({icon:'error',title:'Gagal',text:res.message});
    },'json');
  });
  $(document).on('click','.btn-unapprove',function(){
    const id=$(this).data('id');
    Swal.fire({title:'Unapprove?',icon:'warning',showCancelButton:true}).then(x=>{
      if(!x.isConfirmed)return;
      $.post('./ht_unapprove.php',{id:id},function(res){
        if(res.success){
          table.ajax.reload(null,false);
          Swal.fire({icon:'success',title:'UnApproved',timer:1000,showConfirmButton:false});
        }
        else Swal.fire({icon:'error',title:'Gagal',text:res.message});
      },'json');
    });
  });
  $(document).on('click','.btn-pdf',function(){
    const id=$(this).data('id');
    window.open('./ht_generate_pdf.php?id='+id,'_blank');
  });
  $(document).on('click','.btn-delete',function(){
    const id = $(this).data('id');
    Swal.fire({
      title:'Hapus HT ini?',
      text:'Stock & qty IR akan dikembalikan bila dokumen sudah Approved. Data HT dihapus permanen.',
      icon:'warning', showCancelButton:true,
      confirmButtonText:'Ya, hapus', cancelButtonText:'Batal'
    }).then(x=>{
      if(!x.isConfirmed) return;
      $.post('./ht_delete.php',{id:id}, function(res){
        if(res && res.success){
          Swal.fire({icon:'success',title:'Terhapus',timer:1200,showConfirmButton:false});
          $('#tbl').DataTable().ajax.reload(null,false);
        }else{
          Swal.fire({icon:'error',title:'Gagal',text:(res && res.message)||'Error'});
        }
      },'json').fail(()=>Swal.fire({icon:'error',title:'Gagal koneksi'}));
    });
  });

  // ==== View: auto isi semua field + read-only (termasuk Aset Karyawan) ====
  $(document).on('click','.btn-view',function(){
    const id = $(this).data('id');
    $.get('./ht_serverside.php', {mode:'ht_detail', id:id}, function(res){
      if(!res || !res.success){
        Swal.fire({icon:'error',title:'Gagal',text:(res && res.message)||'Tidak bisa memuat data'});
        return;
      }

      resetFormHT();
      FORM_LOADING = true;

      const $ir    = $('#ir_no');
      const $emp   = $('#emp_name');
      const $asset = $('#emp_asset_id');
      initIRSelect($ir);
      initEmpSelect($emp);
      $asset.prop('disabled', true);

      const H     = res.header || {};
      const items = res.items || [];

      $('input[name=id]').val(H.id||'');
      $('input[name=ht_date]').val(dmY(H.ht_date||''));
      $('input[name=descr]').val(H.descr||'');
      $('input[name=dept_name]').val(H.dept_name||'');
      $('#id_kode').val(H.id_kode || '');

      setSelect2Value($ir, H.ir_no, H.ir_no);
      setSelect2Value($emp, H.emp_name, H.emp_display || H.emp_name);
      CURRENT_EMP_ID = H.emp_name || null;

      if (CURRENT_EMP_ID) {
        if (!$asset.hasClass('select2-hidden-accessible')) {
          initAssetSelect($asset);
        }
        $asset.prop('disabled', true);

        if (H.id_asset && H.asset_text) {
          setSelect2Value($asset, H.id_asset, H.asset_text);
        }
      }

      buildIRCacheOnly(H.ir_no).always(function(){
        if(items.length===0){ addRow(); }
        else {
          items.forEach(it=>{
            addRow({
              product_id: it.product_id,
              prod_code : it.prod_code,
              prod_name : it.prod_name,
              uom_id    : it.uom_id,
              uomname   : it.uomname,
              qty       : it.qty
            });
          });
        }
        refreshAllRowStocks();

        const st = String(H.status||'').toLowerCase();

        $('input[name=mode]').val('view');
        $('#formHT').addClass('readonly');

        $('#formHT .form-control').prop('disabled', true);
        $('#formHT select').each(function () {
          const $s = $(this);
          $s.prop('disabled', true);
          if ($s.hasClass('select2-hidden-accessible')) {
            $s.trigger('change.select2');
          }
        });

        $('#btnAddRow').hide();
        $('#tblItems .btnDelRow').hide();
        $('#btnSave').addClass('btn-save-hidden');

        if(st === 'approved'){
          $('#ApprovedNote').removeClass('d-none');
          $('#modalTitle').text('Lihat Serah Terima #' + (H.id||'') + ' (Approved)');
          $('#btnEditMode').addClass('d-none');
        } else {
          $('#ApprovedNote').addClass('d-none');
          $('#modalTitle').text('Lihat Serah Terima #' + (H.id||''));
          $('#btnEditMode').removeClass('d-none');
        }

        FORM_LOADING = false;
        $('#modalHT').modal('show');
      });

    }, 'json')
    .fail(()=> Swal.fire({icon:'error',title:'Gagal koneksi'}));
  });

  // Edit mode switch
  $('#btnEditMode').on('click', function(){
    $('input[name=mode]').val('update');
    $('#formHT').removeClass('readonly');

    $('#formHT .form-control').prop('disabled', false);
    $('input[name=dept_name]').prop('disabled', false);

    $('#formHT select').each(function () {
      const $s = $(this);
      $s.prop('disabled', false);
      if ($s.hasClass('select2-hidden-accessible')) {
        $s.trigger('change.select2');
      }
    });

    $('#btnAddRow').show();
    $('#tblItems .btnDelRow').show();
    $('#btnSave').removeClass('btn-save-hidden');
    $('#ApprovedNote').addClass('d-none');
    $('#btnEditMode').addClass('d-none');

    const id = $('input[name=id]').val() || '';
    $('#modalTitle').text('Edit Serah Terima #' + id);
  });

  // E-Sign Menyerahkan
  let cvsSender, ctxSender;
  function initCanvasSender(){
    cvsSender = document.getElementById('sigCanvasSender');
    ctxSender = cvsSender.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    const w   = cvsSender.parentElement.clientWidth || 420;
    const h   = 140;
    cvsSender.width  = w * dpr;
    cvsSender.height = h * dpr;
    cvsSender.style.width  = w + 'px';
    cvsSender.style.height = h + 'px';
    ctxSender.scale(dpr, dpr);
    ctxSender.fillStyle = '#fff';
    ctxSender.fillRect(0,0,w,h);

    let drawing = false, lastX = 0, lastY = 0;
    function drawLine(x, y) {
      ctxSender.lineTo(x, y);
      ctxSender.stroke();
    }
    function getPos(e) {
      const rect = cvsSender.getBoundingClientRect();
      if (e.touches && e.touches.length === 1) {
        return {
          x: e.touches[0].clientX - rect.left,
          y: e.touches[0].clientY - rect.top
        };
      } else if (typeof e.offsetX !== 'undefined') {
        return { x: e.offsetX, y: e.offsetY };
      } else if (typeof e.clientX !== 'undefined') {
        return {
          x: e.clientX - rect.left,
          y: e.clientY - rect.top
        };
      }
      return {x:0, y:0};
    }
    cvsSender.onpointerdown = function(e) {
      const pos = getPos(e);
      drawing = true;
      ctxSender.beginPath();
      ctxSender.moveTo(pos.x, pos.y);
      lastX = pos.x; lastY = pos.y;
    };
    cvsSender.onpointermove = function(e) {
      if (!drawing) return;
      const pos = getPos(e);
      drawLine(pos.x, pos.y);
      lastX = pos.x; lastY = pos.y;
    };
    cvsSender.onpointerup = cvsSender.onpointerleave = function(e) {
      drawing = false;
    };
    cvsSender.addEventListener('touchstart', function(e) {
      const pos = getPos(e);
      drawing = true;
      ctxSender.beginPath();
      ctxSender.moveTo(pos.x, pos.y);
      lastX = pos.x; lastY = pos.y;
      e.preventDefault();
    }, {passive:false});
    cvsSender.addEventListener('touchmove', function(e) {
      if (!drawing) return;
      const pos = getPos(e);
      drawLine(pos.x, pos.y);
      lastX = pos.x; lastY = pos.y;
      e.preventDefault();
    }, {passive:false});
    cvsSender.addEventListener('touchend', function(e) {
      drawing = false;
      e.preventDefault();
    }, {passive:false});

    $('#btnClearSigSender').off('click').on('click', ()=>{
      ctxSender.clearRect(0,0,w,h);
      ctxSender.fillStyle = '#fff';
      ctxSender.fillRect(0,0,w,h);
    });
  }

  $(document).on('click', '.btn-esign-sender', function(){
    const id   = $(this).data('id');
    const nama = $(this).data('nama') || '';
    $('#sender_id').val(id);
    $('#sender_sign_name').val(nama);
    $('#modalSender').modal({backdrop:'static', keyboard:false});
    setTimeout(initCanvasSender, 150);
  });

  $('#formSender').on('submit', function(e){
    e.preventDefault();
    if (!cvsSender) return;
    const dataUrl = cvsSender.toDataURL('image/png');
    $('#sender_sign_data').val(dataUrl);

    $.post('./ht_esign_menyerahkan.php', $(this).serialize(), function(res){
      if (res && res.success) {
        $('#modalSender').modal('hide');
        $('#tbl').DataTable().ajax.reload(null, false);
        Swal.fire({icon:'success', title:'Tanda tangan disimpan', timer:1200, showConfirmButton:false});
      } else {
        Swal.fire({icon:'error', title:'Gagal', text:(res && res.message) || 'Error'});
      }
    }, 'json').fail(()=>{ Swal.fire({icon:'error', title:'Gagal koneksi'}); });
  });

})(); // end IIFE
</script>
</body>
</html>
