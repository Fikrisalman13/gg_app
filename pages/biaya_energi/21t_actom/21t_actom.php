<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$menuId = 230; // TODO: ganti MenuId 21t_actom yang benar
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: /gg_app/index.php');
    exit;
}

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<div class="wrapper">
<div class="content-wrapper">
  <section class="content-header">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col-sm-6">
        <h1>PEMAKAIAN BATU BARA STEAM 21TON ACTOM, PEMBUANGAN BOTTOM ASH DAN FLY ASH</h1>
      </div>
      <div class="col-sm-6 text-right">
        <a href="/gg_app/pages/biaya_energi/biaya_energi.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
      </div>
    </div>
  </div>
</section>
  <section class="content">
    <div class="container-fluid">
      <div class="card card-primary">
        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center">
          <h3 class="card-title"><i class="fas fa-list mr-1"></i> List Data Batu Bara Boiler 21T Actom</h3>
          <div class="d-flex ml-auto" style="gap:8px;">
            <a href="report_21t_actom.php" class="btn btn-info btn-sm"><i class="fas fa-file-alt"></i> Detail Report</a>
            <?php if (!empty($permissions['CanAdd']) && $permissions['CanAdd'] == 1): ?>
              <a href="#" class="btn btn-success btn-sm" id="btnTambahData"><i class="fas fa-plus"></i> Tambah Data</a>
              <a href="#" class="btn btn-warning btn-sm" id="btnTambahCatatan"><i class="fas fa-sticky-note"></i> Tambah Catatan</a>
            <?php endif; ?>
          </div>
        </div>
        <div class="card-body table-responsive">
          <div class="row mb-3">
            <div class="col-md-3"><label>Dari Tanggal</label><input type="date" id="filterStartDate" class="form-control"></div>
            <div class="col-md-3"><label>Sampai Tanggal</label><input type="date" id="filterEndDate" class="form-control"></div>
            <div class="col-md-2 d-flex align-items-end"><button type="button" class="btn btn-secondary btn-sm" id="btnResetFilter"><i class="fas fa-undo"></i> Reset Filter</button></div>
          </div>
          <table id="ActomTable" class="table table-hover table-sm nowrap text-center" style="width:100%">
            <thead class="thead-light">
              <tr>
                <th>No</th><th>Tanggal</th><th>Pemakaian (Kg)</th><th>Harga/Kg (Rp)</th><th>Biaya (Rp)</th><th>Total Biaya Boiler (Rp)</th>
                <th>Extractor (Kg)</th><th>C/Grate (Kg)</th><th>Fly Ash (Kg)</th><th>Total (Kg)</th><th>Ket</th><th>Created By</th><th>Aksi</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
</div>

<div class="modal fade" id="modalTambahActom" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><h5 class="modal-title">Input BB Boiler 21T Actom</h5><button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button></div>
      <form id="formActom" autocomplete="off">
        <div class="modal-body">
          <div class="form-group">
            <label>Tanggal</label>
            <input type="date" name="tanggal" id="xTanggal" class="form-control" required>
          </div>

          <div class="border rounded p-2 mb-3">
            <div class="font-weight-bold mb-2 text-primary">BATU BARA ADB 5600-5800</div>
            <div class="form-row">
              <div class="form-group col-md-4"><label>Pemakaian (Kg)</label><input type="text" name="pemakaian_kg" id="xPakai" class="form-control num-only" data-decimals="2" required></div>
              <div class="form-group col-md-4 position-relative">
                <label>Harga/Kg (Rp)</label>
                <input type="text" name="harga_rp_per_kg" id="xHarga" class="form-control num-only" data-decimals="2" autocomplete="off" required>
                <div id="xHargaHist" class="list-group position-absolute w-100" style="z-index:30;display:none;max-height:180px;overflow:auto;"></div>
              </div>
              <div class="form-group col-md-4"><label>Biaya (Rp)</label><input type="text" id="xBiaya" class="form-control bg-light" readonly></div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-6"><label>Total Biaya Boiler (Rp)</label><input type="text" name="total_biaya_boiler_rp" id="xTotalBoiler" class="form-control bg-light" readonly required></div>
              <div class="form-group col-md-6"><label>Keterangan (KET)</label><input type="text" name="ket" class="form-control"></div>
            </div>
          </div>

          <div class="border rounded p-2 mb-1">
            <div class="font-weight-bold mb-2 text-primary">BOTTOM ASH (PEMBUANGAN)</div>
            <div class="form-row">
              <div class="form-group col-md-3"><label>Extractor (Kg)</label><input type="text" name="extractor_kg" id="xExtractor" class="form-control num-only" data-decimals="2" required></div>
              <div class="form-group col-md-3"><label>C/Grate (Kg)</label><input type="text" name="cgrate_kg" id="xCgrate" class="form-control num-only" data-decimals="2" required></div>
              <div class="form-group col-md-3"><label>Fly Ash (Kg)</label><input type="text" name="fly_ash_kg" id="xFlyAsh" class="form-control num-only" data-decimals="2" required></div>
              <div class="form-group col-md-3"><label>Total (Kg)</label><input type="text" id="xTotalKg" class="form-control bg-light font-weight-bold" readonly></div>
            </div>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan</button></div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalTambahCatatanActom" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title">Tambah Catatan BB Boiler 21T Actom</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="formCatatanActom" autocomplete="off">
        <div class="modal-body">
          <div class="form-group">
            <label>Tanggal</label>
            <input type="date" name="tanggal" id="xCatatanTanggal" class="form-control" required>
          </div>
          <div class="form-group mb-0">
            <label>Catatan</label>
            <textarea name="note" id="xCatatanText" class="form-control" rows="4" placeholder="Isi catatan..." required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script>
$(function(){
  function parseNum(v){ if(v===null||v===undefined) return NaN; v=String(v).trim().replace(/,/g,''); if(v==='') return NaN; var n=Number(v); return isNaN(n)?NaN:n; }
  function fmt(v,d){ var n=parseNum(v); return isNaN(n)?'-':n.toLocaleString('en-US',{minimumFractionDigits:d,maximumFractionDigits:d}); }
  function fmtInput(v,d){ var n=parseNum(v); return isNaN(n)?'':n.toLocaleString('en-US',{minimumFractionDigits:d,maximumFractionDigits:d}); }
  var hargaHistCache = null;

  function renderHargaHist(entries){
    var $box = $('#xHargaHist');
    if (!entries || entries.length === 0) { $box.hide().empty(); return; }
    var html = '';
    entries.forEach(function(it){
      var val = fmtInput(it.harga, 2);
      var tgl = it.tanggal ? ('<small class="text-muted ml-2">' + $('<div>').text(it.tanggal).html() + '</small>') : '';
      html += '<button type="button" class="list-group-item list-group-item-action py-1 px-2 text-right x-harga-pilih" data-value="' + $('<div>').text(String(it.harga)).html() + '">' + $('<div>').text(val).html() + tgl + '</button>';
    });
    $box.html(html).show();
  }

  function loadHargaHist(cb){
    if (Array.isArray(hargaHistCache)) { cb(hargaHistCache); return; }
    $.getJSON('get_harga_histori_21t_actom.php', function(resp){
      hargaHistCache = (resp && resp.success && Array.isArray(resp.data)) ? resp.data : [];
      cb(hargaHistCache);
    }).fail(function(){ cb([]); });
  }

  function recalc(){
    var p=parseNum($('#xPakai').val()), h=parseNum($('#xHarga').val()), e=parseNum($('#xExtractor').val()), c=parseNum($('#xCgrate').val()), y=parseNum($('#xFlyAsh').val());
    var biaya = (!isNaN(p)&&!isNaN(h)) ? (p*h) : NaN;
    var biayaFmt = !isNaN(biaya) ? fmtInput(biaya,2) : '';
    $('#xBiaya').val(biayaFmt);
    $('#xTotalBoiler').val(biayaFmt);
    var t = (isNaN(e)?0:e) + (isNaN(c)?0:c) + (isNaN(y)?0:y);
    $('#xTotalKg').val(fmtInput(t,2));
  }

  var table = $('#ActomTable').DataTable({
    processing:true, serverSide:true, ordering:false, responsive:true,
    ajax:{ url:'21t_actom_serverside.php', type:'POST', data:function(d){ d.start_date=$('#filterStartDate').val(); d.end_date=$('#filterEndDate').val(); }},
    columns:[
      {data:null,render:function(d,t,r,m){return m.row+m.settings._iDisplayStart+1;}},
      {data:'tanggal_formatted'},{data:'pemakaian_kg',render:function(d){return fmt(d,2)}},{data:'harga_rp_per_kg',render:function(d){return fmt(d,2)}},
      {data:'biaya_rp',render:function(d){return fmt(d,2)}},{data:'total_biaya_boiler_rp',render:function(d){return fmt(d,2)}},
      {data:'extractor_kg',render:function(d){return fmt(d,2)}},{data:'cgrate_kg',render:function(d){return fmt(d,2)}},{data:'fly_ash_kg',render:function(d){return fmt(d,2)}},
      {data:'total_kg',render:function(d){return fmt(d,2)}},{data:'ket',render:function(d){return d||'-';}},{data:'created_by',render:function(d){return d||'-';}},
      {data:'id',render:function(id){ if(!id) return '-'; return '<div class="btn-group btn-group-sm">'+
        '<button class="btn btn-info btn-detail" data-id="'+id+'"><i class="fas fa-eye"></i></button>'+
        '<button class="btn btn-warning btn-edit" data-id="'+id+'"><i class="fas fa-edit"></i></button>'+
        '<button class="btn btn-danger btn-delete" data-id="'+id+'"><i class="fas fa-trash"></i></button></div>'; }}
    ]
  });

  $('#filterStartDate,#filterEndDate').on('change', function(){ table.ajax.reload(); });
  $('#btnResetFilter').on('click', function(){ $('#filterStartDate,#filterEndDate').val(''); table.search('').draw(); });

  $('#btnTambahData').on('click', function(e){ e.preventDefault(); $('#formActom')[0].reset(); $('#xTanggal').val(new Date().toISOString().slice(0,10)); $('#modalTambahActom').modal('show'); });
  $('#btnTambahCatatan').on('click', function(e){
    e.preventDefault();
    $('#formCatatanActom')[0].reset();
    $('#xCatatanTanggal').val(new Date().toISOString().slice(0,10));
    $('#modalTambahCatatanActom').modal('show');
  });
  $(document).on('input','.num-only',function(){ this.value=this.value.replace(/[^0-9.,]/g,''); });
  $('#xPakai,#xHarga,#xExtractor,#xCgrate,#xFlyAsh').on('input blur', recalc);
  $('#xHarga').on('focus click', function(){ loadHargaHist(renderHargaHist); });
  $(document).on('click', '.x-harga-pilih', function(){
    var n = parseNum($(this).data('value'));
    if (!isNaN(n)) { $('#xHarga').val(fmtInput(n,2)); recalc(); }
    $('#xHargaHist').hide();
    $('#xHarga').trigger('focus');
  });
  $(document).on('click', function(e){
    if ($(e.target).closest('#xHarga, #xHargaHist').length === 0) $('#xHargaHist').hide();
  });

  $('#formActom').on('submit', function(e){
    e.preventDefault();
    $.ajax({ url:'save_21t_actom.php', type:'POST', data:$(this).serialize(), dataType:'json',
      success:function(resp){ if(resp&&resp.success){ $('#modalTambahActom').modal('hide'); Swal.fire({icon:'success',title:'Sukses',text:resp.message||'Tersimpan.'}); table.ajax.reload(null,false);} else {Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal menyimpan.'});}},
      error:function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan.'}); }
    });
  });

  $('#formCatatanActom').on('submit', function(e){
    e.preventDefault();
    $.ajax({
      url:'save_catatan_21t_actom.php',
      type:'POST',
      data:$(this).serialize(),
      dataType:'json',
      success:function(resp){
        if(resp && resp.success){
          $('#modalTambahCatatanActom').modal('hide');
          Swal.fire({icon:'success',title:'Sukses',text:resp.message||'Catatan tersimpan.'});
          table.ajax.reload(null,false);
        } else {
          Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal menyimpan catatan.'});
        }
      },
      error:function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan saat menyimpan catatan.'}); }
    });
  });

  $(document).on('click','.btn-detail',function(){ var id=$(this).data('id'); if(id) window.location.href='view_21t_actom.php?id='+encodeURIComponent(id); });
  $(document).on('click','.btn-edit',function(){ var id=$(this).data('id'); if(id) window.location.href='edit_21t_actom.php?id='+encodeURIComponent(id); });
  $(document).on('click','.btn-delete',function(){ var id=$(this).data('id'); if(!id) return; Swal.fire({title:'Hapus Data?',text:'Data akan dihapus permanen.',icon:'warning',showCancelButton:true,confirmButtonColor:'#d33',cancelButtonColor:'#3085d6',confirmButtonText:'Ya, Hapus!',cancelButtonText:'Batal'}).then(function(r){ if(r.isConfirmed){ window.location.href='delete_21t_actom.php?id='+encodeURIComponent(id); }}); });
});
</script>
