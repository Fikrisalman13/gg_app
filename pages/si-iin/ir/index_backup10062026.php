<?php
// gg_app/pages/si-iin/ir.php
// Disesuaikan untuk header.php / sidebar.php / footer.php / permissions.php
date_default_timezone_set('Asia/Jakarta');

// koneksi + helper + permissions
require_once __DIR__ . '/../_shared/siin_lib.php';
require_once __DIR__ . '/../../../includes/permissions.php';

// layout (header + sidebar)
// header.php sudah melakukan session_start() dan validasi login
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';

// minimal auth (header.php sudah redirect bila belum login, tapi double-check nama user untuk safety)
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';

// export PDF shortcut (optional)
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    header('Location: ir_export_pdf.php?' . http_build_query($_GET));
    exit;
}
?>

<!-- content-wrapper (header.php already membuka .wrapper dan navbar/sidebar) -->
<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0">Penerimaan IR</h1>
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
          <li class="breadcrumb-item active">IR</li>
        </ol>
      </div>
    </div>
  </div>

  <section class="content">
    <div class="container-fluid">

      <div class="card">
        <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
          <h3 class="card-title">Daftar IR</h3>
          <button class="btn btn-success btn-sm float-right" id="btnBuatIR">
            <i class="fas fa-plus"></i> Buat IR
          </button>
        </div>

        <div class="card-body">
          <div class="filter-box">
            <div class="form-inline">
              <label class="mr-2">Start Date</label>
              <input type="text" id="startDate" class="form-control form-control-sm datepicker mr-3" placeholder="dd-mm-yyyy">
              <label class="mr-2">End Date</label>
              <input type="text" id="endDate" class="form-control form-control-sm datepicker mr-3" placeholder="dd-mm-yyyy">
              <button class="btn btn-primary btn-sm mr-2" id="btnFilter"><i class="fas fa-filter"></i> Filter</button>
              <button class="btn btn-secondary btn-sm mr-2" id="btnReset"><i class="fas fa-sync"></i> Reset</button>
              <a class="btn btn-danger btn-sm" id="btnExportPdf"><i class="fas fa-file-pdf"></i> Export PDF</a>
            </div>
          </div>

          <div class="table-responsive">
            <table id="irTable" class="table table-hover table-sm" style="width:100%">
              <thead class="thead-light">
                <tr>
                  <th class="col-no">No</th>
                  <th class="col-req">Date</th>
                  <th class="col-req">Request No</th>
                  <th class="col-descr">Description</th>
                  <th class="col-status">Status</th>
                  <th class="col-aksi">Aksi</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- === Modal Buat / Edit / View IR === -->
      <div class="modal fade" id="modalIR" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
          <div class="modal-content">
            <form id="formIR" autocomplete="off">
              <input type="hidden" name="mode" value="create">
              <input type="hidden" name="id" value="">
              <div class="modal-header">
                <h5 class="modal-title" id="titleIR">Buat IR</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
              </div>
              <div class="modal-body">

                <!-- baris 1: Tanggal + Request No -->
                <div class="form-row">
                  <div class="form-group col-md-3">
                    <label>Tanggal</label>
                    <input type="text" name="ir_date" class="form-control datepicker" required placeholder="dd-mm-yyyy">
                  </div>
                  <div class="form-group col-md-5">
                    <label>Request No</label>
                    <input type="text" name="request_no" class="form-control" placeholder="IR/2025/11/0001" required>
                  </div>
                </div>

                <!-- baris 2: Deskripsi header (manual) -->
                <div class="form-row">
                  <div class="form-group col-md-12">
                    <label>Deskripsi</label>
                    <textarea name="descr"
                              class="form-control descr-textarea"
                              rows="3"
                              placeholder="Keterangan IR (manual)"></textarea>
                  </div>
                </div>

                <hr>

                <div class="d-flex justify-content-between align-items-center mb-2">
                  <h6 class="mb-0">Item</h6>
                  <button type="button" class="btn btn-sm btn-outline-primary ml-auto" id="btnAddRow">
                    <i class="fas fa-plus"></i> Tambah Item
                  </button>
                </div>

                <div class="table-responsive">
                  <table class="table table-sm table-items" id="tblItems">
                    <thead>
                      <tr>
                        <th style="width:18%">Product Code</th>
                        <th style="width:22%">Product Name</th>
                        <th style="width:10%">Qty</th>
                        <th style="width:12%">UOM</th>
                        <th style="width:16%">GRN No</th>
                        <th style="width:22%">Catatan</th>
                        <th style="width:4%"></th>
                      </tr>
                    </thead>
                    <tbody></tbody>
                  </table>
                </div>
                <small class="text-muted d-block">
                  Product, UOM & GRN memakai Select2. Catatan akan terisi otomatis saat pilih GRN, tetapi masih bisa diedit.
                </small>
              </div>
              <div class="modal-footer">
                <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor)?>">
                  <i class="fas fa-save"></i> Simpan
                </button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
              </div>
            </form>
          </div>
        </div>
      </div>
      <!-- === end modal IR === -->

    </div>
  </section>
</div>

<?php
// include footer (footer.php menutup wrapper/body/html dan memuat jQuery, bootstrap, adminlte, select2)
require_once __DIR__ . '/../../../includes/footer.php';
?>

<!-- Tambahan resource yang footer.php belum sediakan -->
<link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">
<link rel="stylesheet" href="/gg_app/plugins/css/bootstrap-datepicker.min.css">

<script src="/gg_app/plugins/js/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/js/datatables/dataTables.bootstrap5.min.js"></script>
<script src="/gg_app/plugins/js/bootstrap-datepicker.min.js"></script>
<script src="/gg_app/plugins/js/bootstrap-datepicker.id.min.js"></script>
<!-- SweetAlert (eksternal) -->
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
(function(){
  // === Datepicker ===
  $('.datepicker').datepicker({
    format:'dd-mm-yyyy',
    language:'id',
    autoclose:true,
    todayHighlight:true
  });

  // === DataTable daftar IR ===
  var table = $('#irTable').DataTable({
    serverSide:true,
    processing:true,
    responsive:true,
    autoWidth:false,
    search:{ return:true },
    ajax:{
      url:'ir_serverside.php',
      type:'POST',
      data:function(d){
        d.start_date=$('#startDate').val();
        d.end_date=$('#endDate').val();
      }
    },
    columns:[
      {
        data:null,
        orderable:false,
        searchable:false,
        className:'col-no',
        render:(d,t,r,m)=> m.row+1+m.settings._iDisplayStart
      },
      {data:'ir_date',     className:'col-req'},
      {data:'request_no',  className:'col-req'},
      {data:'descr',       className:'col-descr'},
      {data:'status_badge',className:'col-status', orderable:false, searchable:false},
      {data:'aksi',        className:'col-aksi',   orderable:false, searchable:false}
    ]
  });

  $('#btnFilter').on('click', ()=>table.ajax.reload());
  $('#btnReset').on('click', ()=>{ $('#startDate,#endDate').val(''); table.ajax.reload(); });
  $('#btnExportPdf').on('click', function(e){
    e.preventDefault();
    const qs = $.param({
      export:'pdf',
      start_date:$('#startDate').val()||'',
      end_date:$('#endDate').val()||''
    });
    location.href='ir.php?'+qs;
  });

  // === Select2 builder ===
  function buildProductSelect($el, mode){
    $el.select2({
      theme:'bootstrap',
      width:'100%',
      tags:true,
      dropdownParent: $('#modalIR'),
      placeholder: mode==='code' ? 'Pilih / ketik kode' : 'Pilih / ketik nama',
      minimumInputLength: 0,
      ajax:{
        url:'ref_product.php',
        dataType:'json',
        delay:250,
        data:function(params){
          return {
            q: params.term || '',
            mode: mode
          };
        },
        processResults:function(data){
          return { results:data };
        }
      }
    });
  }

  function buildUomSelect($el){
    $el.select2({
      theme:'bootstrap',
      width:'100%',
      tags:true,
      dropdownParent: $('#modalIR'),
      placeholder:'UOM',
      minimumInputLength: 0,
      ajax:{
        url:'ref_uom.php',
        dataType:'json',
        delay:200,
        data:function(params){
          return { q: params.term || '' };
        },
        processResults:function(data){
          return { results:data };
        }
      }
    });
  }

  function buildItemGrnSelect($el){
    const $row = $el.closest('tr');
    $el.select2({
      theme:'bootstrap',
      width:'100%',
      placeholder:'Pilih GRN No',
      allowClear:true,
      dropdownParent: $('#modalIR'),
      minimumInputLength: 0,
      ajax:{
        url:'ref_grn.php',
        dataType:'json',
        delay:250,
        data:function(params){
          const codeData = $row.find('.sel-code').select2('data');
          let prodCode = '';
          if(codeData && codeData.length){
            const d = codeData[0];
            prodCode = d.prod_code || d.text || '';
          }
          return {
            q: params.term || '',
            prod_code: prodCode
          };
        },
        processResults:function(data){
          return { results:data };
        }
      }
    }).on('select2:opening', function(e){
      const codeData = $row.find('.sel-code').select2('data');
      if(!codeData || !codeData.length){
        e.preventDefault();
        Swal.fire({
          icon:'warning',
          title:'Pilih product dulu',
          text:'Silakan pilih Product Code / Product Name terlebih dahulu.'
        });
      }
    }).on('select2:select', function(e){
      const d = e.params.data || {};
      if(d.descr){
        $row.find('textarea[name^="items"][name$="[note]"]').val(d.descr);
      }
      if(d.ppnmbr){  $row.find('input[name^="items"][name$="[ppnmbr]"]').val(d.ppnmbr); }
      if(d.ppdate){  $row.find('input[name^="items"][name$="[ppdate]"]').val(d.ppdate); }
      if(d.ponmbr){  $row.find('input[name^="items"][name$="[ponmbr]"]').val(d.ponmbr); }
      if(d.podate){  $row.find('input[name^="items"][name$="[podate]"]').val(d.podate); }
      if(d.grndate){ $row.find('input[name^="items"][name$="[grndate]"]').val(d.grndate); }
    }).on('select2:clear', function(){
      $row.find('textarea[name^="items"][name$="[note]"]').val('');
      $row.find('input[name^="items"][name$="[ppnmbr]"]').val('');
      $row.find('input[name^="items"][name$="[ppdate]"]').val('');
      $row.find('input[name^="items"][name$="[ponmbr]"]').val('');
      $row.find('input[name^="items"][name$="[podate]"]').val('');
      $row.find('input[name^="items"][name$="[grndate]"]').val('');
    });
  }

  function linkSync($row){
    const $code = $row.find('.sel-code');
    const $name = $row.find('.sel-name');
    const $uom  = $row.find('.sel-uom');
    const $grn  = $row.find('.sel-grn');

    function resetGrnRow(){
      $grn.val(null).trigger('change');
      $row.find('textarea[name^="items"][name$="[note]"]').val('');
      $row.find('input[name^="items"][name$="[ppnmbr]"]').val('');
      $row.find('input[name^="items"][name$="[ppdate]"]').val('');
      $row.find('input[name^="items"][name$="[ponmbr]"]').val('');
      $row.find('input[name^="items"][name$="[podate]"]').val('');
      $row.find('input[name^="items"][name$="[grndate]"]').val('');
    }

    $code.on('select2:select', function(e){
      const d=e.params.data||{};
      if(d && d.id && !String(d.id).startsWith('tag:')){
        if(d.prod_name){
          const optName=new Option(d.prod_name, d.id, true, true);
          $name.append(optName).trigger('change');
        }
        if(d.uom_id || d.uomname){
          const uid=d.uom_id || d.uomname, utx=d.uomname || d.uom_id;
          const optUom=new Option(utx, uid, true, true);
          $uom.append(optUom).trigger('change');
        }
      }
      resetGrnRow();
    });

    $name.on('select2:select', function(e){
      const d=e.params.data||{};
      if(d && d.id && !String(d.id).startsWith('tag:')){
        const label = d.prod_code ? d.prod_code : d.text;
        const optCode=new Option(label, d.id, true, true);
        $code.append(optCode).trigger('change');
        if(d.uom_id || d.uomname){
          const uid=d.uom_id || d.uomname, utx=d.uomname || d.uom_id;
          const optUom=new Option(utx, uid, true, true);
          $uom.append(optUom).trigger('change');
        }
      }
      resetGrnRow();
    });
  }

  function rowHtml(idx){
    return `
      <tr>
        <td>
          <select name="items[${idx}][prod_code]" class="form-control form-control-sm sel-code"></select>
        </td>
        <td>
          <select name="items[${idx}][prod_name]" class="form-control form-control-sm sel-name"></select>
        </td>
        <td>
          <input type="number" step="1" min="1" class="form-control form-control-sm" name="items[${idx}][qty]" required>
        </td>
        <td>
          <select name="items[${idx}][uom_id]" class="form-control form-control-sm sel-uom"></select>
        </td>
        <td>
          <select name="items[${idx}][grnnmbr]" class="form-control form-control-sm sel-grn"></select>
        </td>
        <td>
          <textarea class="form-control form-control-sm descr-textarea" name="items[${idx}][note]" rows="2"></textarea>
          <input type="hidden" name="items[${idx}][ppnmbr]">
          <input type="hidden" name="items[${idx}][ppdate]">
          <input type="hidden" name="items[${idx}][ponmbr]">
          <input type="hidden" name="items[${idx}][podate]">
          <input type="hidden" name="items[${idx}][grndate]">
        </td>
        <td class="text-right">
          <button type="button" class="btn btn-sm btn-outline-danger btn-remove">
            <i class="fas fa-trash"></i>
          </button>
        </td>
      </tr>`;
  }

  function addRow(pref){
    const idx = $('#tblItems tbody tr').length;
    const $row = $(rowHtml(idx)).appendTo('#tblItems tbody');

    buildProductSelect($row.find('.sel-code'),'code');
    buildProductSelect($row.find('.sel-name'),'name');
    buildUomSelect($row.find('.sel-uom'));
    buildItemGrnSelect($row.find('.sel-grn'));
    linkSync($row);

    if(pref){
      if(pref.product_id){
        const codeTxt=pref.prod_code||pref.product_id;
        const nameTxt=pref.prod_name||pref.product_id;
        $row.find('.sel-code')
            .append(new Option(codeTxt, pref.product_id, true, true))
            .trigger('change');
        $row.find('.sel-name')
            .append(new Option(nameTxt, pref.product_id, true, true))
            .trigger('change');
      }
      if(pref.uom_id || pref.uomname){
        const uid=pref.uom_id||pref.uomname, utx=pref.uomname||pref.uom_id;
        $row.find('.sel-uom')
            .append(new Option(utx, uid, true, true))
            .trigger('change');
      }
      if(pref.grnnmbr){
        $row.find('.sel-grn')
            .append(new Option(pref.grnnmbr, pref.grnnmbr, true, true))
            .trigger('change');
      }
      if(pref.qty)  $row.find('input[name^="items"][name$="[qty]"]').val(pref.qty);
      if(pref.note) $row.find('textarea[name^="items"][name$="[note]"]').val(pref.note);

      if(pref.ppnmbr)  $row.find('input[name^="items"][name$="[ppnmbr]"]').val(pref.ppnmbr);
      if(pref.ppdate)  $row.find('input[name^="items"][name$="[ppdate]"]').val(pref.ppdate);
      if(pref.ponmbr)  $row.find('input[name^="items"][name$="[ponmbr]"]').val(pref.ponmbr);
      if(pref.podate)  $row.find('input[name^="items"][name$="[podate]"]').val(pref.podate);
      if(pref.grndate) $row.find('input[name^="items"][name$="[grndate]"]').val(pref.grndate);
    }
  }

  $('#btnAddRow').on('click', ()=>addRow());
  $(document).on('click','.btn-remove', function(){ $(this).closest('tr').remove(); });

  // Modal create
  $('#btnBuatIR').on('click', function(){
    $('#formIR')[0].reset();
    $('input[name=mode]').val('create');
    $('input[name=id]').val('');
    $('#tblItems tbody').empty();
    addRow();

    $('#titleIR').text('Buat IR');
    $('#formIR .form-control').prop('disabled', false);
    $('#formIR button[type=submit]').show();
    $('#btnAddRow').show();
    $('#tblItems .btn-remove').show();

    $('#modalIR').modal('show');
  });

  // View/Edit/Delete
  $(document).on('click','.btn-view', function(){
    const id=$(this).data('id');
    $.get('ir_get.php',{id:id}, function(res){
      if(!res||!res.header){ Swal.fire({icon:'error',title:'Data tidak ditemukan'}); return; }
      openModal(res,'view');
    },'json');
  });
  $(document).on('click','.btn-edit', function(){
    const id=$(this).data('id');
    $.get('ir_get.php',{id:id}, function(res){
      if(!res||!res.header){ Swal.fire({icon:'error',title:'Data tidak ditemukan'}); return; }
      openModal(res,'edit');
    },'json');
  });
  $(document).on('click','.btn-delete', function(){
    const id=$(this).data('id');
    Swal.fire({title:'Hapus IR?',icon:'warning',showCancelButton:true})
      .then(x=>{
        if(!x.isConfirmed) return;
        $.post('ir_delete.php',{id:id}, function(r){
          if(r.success){
            table.ajax.reload(null,false);
            Swal.fire({icon:'success',title:'Terhapus',timer:1000,showConfirmButton:false});
          }else{
            Swal.fire({icon:'error',title:'Gagal',text:r.message||'Error'});
          }
        },'json');
      });
  });

  function openModal(res, mode){
    const h=res.header, items=res.items||[];
    $('#formIR')[0].reset();
    $('input[name=mode]').val(mode==='edit'?'edit':'view');
    $('input[name=id]').val(h.id);

    $('input[name=ir_date]').val(h.ir_date_dmY);
    $('input[name=request_no]').val(h.request_no);
    $('textarea[name=descr]').val(h.descr||'');

    $('#tblItems tbody').empty();
    if(items.length){
      items.forEach(it=>{
        addRow({
          product_id:it.product_id,
          prod_code:it.prod_code,
          prod_name:it.prod_name,
          uom_id:it.uom_id,
          uomname:it.uomname,
          grnnmbr:it.grnnmbr ?? it.grnmbr ?? '',
          qty:it.qty,
          note:it.note,
          ppnmbr:it.ppnmbr,
          ppdate:it.ppdate,
          ponmbr:it.ponmbr,
          podate:it.podate,
          grndate:it.grndate
        });
      });
    } else {
      addRow();
    }

    if(mode==='view'){
      $('#titleIR').text('Detail IR');
      $('#formIR .form-control').prop('disabled', true);
      $('#formIR button[type=submit]').hide();
      $('#btnAddRow').hide();
      $('#tblItems .btn-remove').hide();
    }else{
      $('#titleIR').text('Edit IR');
      $('#formIR .form-control').prop('disabled', false);
      $('#formIR button[type=submit]').show();
      $('#btnAddRow').show();
      $('#tblItems .btn-remove').show();
    }

    $('#modalIR').modal('show');
  }

  $('#formIR').on('submit', function(e){
    e.preventDefault();
    const url = ($('input[name=mode]').val()==='edit') ? 'ir_update.php' : 'ir_save.php';
    $.post(url, $(this).serialize(), function(res){
      if(res && res.success){
        $('#modalIR').modal('hide');
        table.ajax.reload(null,false);
        Swal.fire({icon:'success',title:'Tersimpan',timer:1600,showConfirmButton:false});
      }else{
        Swal.fire({icon:'error',title:'Gagal',text:res.message||'Error'});
      }
    },'json').fail(()=>{ Swal.fire({icon:'error',title:'Gagal koneksi'}); });
  });

})();
</script>

<?php ob_flush(); ?>
