<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$menuId = 236;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: /gg_app/index.php');
    exit;
}
$canEdit = !empty($permissions['CanEdit']) && (int)$permissions['CanEdit'] === 1;
$canDelete = !empty($permissions['CanDelete']) && (int)$permissions['CanDelete'] === 1;

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
        <h1>Pemakaian Energi Listrik PLN - Gardu Induk</h1>
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
          <h3 class="card-title"><i class="fas fa-list mr-1"></i> List Data Listrik Gardu Induk</h3>
          <div class="d-flex ml-auto" style="gap:8px;">
            <a href="report_listrik_gardu_induk.php" class="btn btn-info btn-sm"><i class="fas fa-file-alt"></i> Detail Report</a>
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
          <table id="listrikGarduTable" class="table table-hover table-sm nowrap text-center" style="width:100%">
            <thead class="thead-light">
              <tr>
                <th>No</th><th>Tanggal</th><th>LVBP (kWh)</th><th>VBP (kWh)</th><th>KVARH</th><th>Cos &Phi;</th>
                <th>Total Daya Perday (kW)</th><th>Total Daya Perjam (kWh)</th><th>Rp/kWh</th>
                <th>Biaya Per Day (Rp)</th><th>Efisiensi (%)</th><th>KVA PLN / Jam</th><th>Ket</th><th>Created By</th><th>Aksi</th>
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

<style>
  .gardu-input-table{border-collapse:collapse;width:100%;min-width:980px}
  .gardu-input-table th,.gardu-input-table td{border:1px solid #c9ced4;padding:6px 8px;text-align:center;vertical-align:middle}
  .gardu-input-table .grp-meter{background:#ead1dc;font-weight:700}
  .gardu-input-table .grp-param{background:#fff200;font-weight:700}
  .gardu-input-table .hdr-meter{background:#cfe2f3;font-weight:700}
  .gardu-input-table .hdr-param{background:#fff200;font-weight:700}
  .gardu-input-table .sub{display:block;font-size:10px;color:#555;font-weight:600;line-height:1.1;margin-top:1px}
  .gardu-input-table .cell-meter{background:#eaf3ff}
  .gardu-input-table .cell-param{background:#fffbe0}
  .gardu-input-table input{height:32px}
</style>

<div class="modal fade" id="modalTambahListrikGardu" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><h5 class="modal-title">Input Listrik Gardu Induk</h5><button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button></div>
      <form id="formListrikGardu" autocomplete="off">
        <div class="modal-body">
          <div class="form-row mb-2">
            <div class="form-group col-md-3 mb-2">
              <label class="font-weight-bold">Tanggal (TGL)</label>
              <input type="date" name="tanggal" id="xTanggal" class="form-control" required>
            </div>
            <div class="form-group col-md-9 mb-2">
              <label class="font-weight-bold">Keterangan (KET)</label>
              <input type="text" name="ket" class="form-control" placeholder="Isi keterangan jika diperlukan">
            </div>
          </div>

          <div class="table-responsive">
            <table class="gardu-input-table">
              <thead>
                <tr>
                  <th colspan="4" class="grp-meter">METER TERCATAT</th>
                  <th colspan="4" class="grp-param">PARAMETER PERHITUNGAN</th>
                </tr>
                <tr>
                  <th class="hdr-meter">LVBP (kWh)<span class="sub">EA.EXP 2</span></th>
                  <th class="hdr-meter">VBP (kWh)<span class="sub">EA.EXP 1</span></th>
                  <th class="hdr-meter">KVARH<span class="sub">ER.EXP</span></th>
                  <th class="hdr-meter">Cos &Phi;</th>
                  <th class="hdr-param">Faktor Kali<span class="sub">CT/PT</span></th>
                  <th class="hdr-param">Rp per kWh</th>
                  <th class="hdr-param">PF Standar</th>
                  <th class="hdr-param">Kapasitas KVA</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="cell-meter"><input type="text" name="lvbp_kwh" id="xLvbp" class="form-control form-control-sm num-only" required></td>
                  <td class="cell-meter"><input type="text" name="vbp_kwh" id="xVbp" class="form-control form-control-sm num-only" required></td>
                  <td class="cell-meter"><input type="text" name="kvarh" id="xKvarh" class="form-control form-control-sm num-only"></td>
                  <td class="cell-meter"><input type="text" name="cos_phi" id="xCos" class="form-control form-control-sm num-only"></td>
                  <td class="cell-param"><input type="text" name="faktor_kali" id="xFaktor" class="form-control form-control-sm num-only" value="6000"></td>
                  <td class="cell-param position-relative">
                    <input type="text" name="rp_per_kwh" id="xTarif" class="form-control form-control-sm num-only" value="" autocomplete="off">
                    <div id="xTarifHist" class="list-group position-absolute w-100" style="z-index:30;display:none;max-height:180px;overflow:auto;left:0;top:100%;"></div>
                  </td>
                  <td class="cell-param"><input type="text" name="pf_standar" id="xPf" class="form-control form-control-sm num-only" value="0.95"></td>
                  <td class="cell-param"><input type="text" name="kapasitas_kva" id="xKapasitas" class="form-control form-control-sm num-only" value="4930.5"></td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="form-row mt-2">
            <div class="col-12">
              <div class="alert alert-info w-100 mb-0 py-2 px-3">
                <div class="font-weight-bold mb-1">Panduan perhitungan otomatis:</div>
                <ol class="mb-1 pl-3">
                  <li>Isi meter harian dulu: LVBP, VBP, KVARH, dan Cos &Phi; sesuai angka pada tanggal input.</li>
                  <li>Sistem membandingkan data ini dengan data pada tanggal berikutnya.</li>
                  <li>Total Daya Perday (kW) = ((LVBP berikutnya - LVBP sekarang) + (VBP berikutnya - VBP sekarang)) &times; Faktor Kali.</li>
                  <li>Total Daya Perjam (kWh) = Total Daya Perday / 24.</li>
                  <li>Biaya Perday (Rp) = Total Daya Perday &times; Rp per kWh.</li>
                  <li>KVA PLN/Jam = Total Daya Perjam / PF Standar.</li>
                  <li>Efisiensi (%) = (KVA PLN/Jam / Kapasitas KVA) &times; 100.</li>
                </ol>
                <small class="d-block">Catatan: jika data tanggal berikutnya belum ada, nilai output otomatis belum bisa dihitung.</small>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan</button></div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalTambahCatatanListrikGardu" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><h5 class="modal-title">Tambah Catatan Listrik Gardu Induk</h5><button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button></div>
      <form id="formCatatanListrikGardu" autocomplete="off">
        <div class="modal-body">
          <div class="form-group"><label>Tanggal</label><input type="date" name="tanggal" id="xCatatanTanggal" class="form-control" required></div>
          <div class="form-group mb-0"><label>Catatan</label><textarea name="note" id="xCatatanText" class="form-control" rows="4" required></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan</button></div>
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
  var canEdit = <?= $canEdit ? 'true' : 'false' ?>;
  var canDelete = <?= $canDelete ? 'true' : 'false' ?>;

  function parseNum(v){ if(v===null||v===undefined) return NaN; v=String(v).trim().replace(/,/g,''); if(v==='') return NaN; var n=Number(v); return isNaN(n)?NaN:n; }
  function fmt(v,d){ var n=parseNum(v); return isNaN(n)?'-':n.toLocaleString('en-US',{minimumFractionDigits:d,maximumFractionDigits:d}); }
  function fmtInput(v,d){ var n=parseNum(v); return isNaN(n)?'':n.toLocaleString('en-US',{minimumFractionDigits:d,maximumFractionDigits:d}); }
  var tarifHistCache = null;

  function renderTarifHist(entries){
    var $box = $('#xTarifHist');
    if (!entries || entries.length === 0) { $box.hide().empty(); return; }
    var html = '';
    entries.forEach(function(it){
      var val = fmtInput(it.tarif, 3);
      var tgl = it.tanggal ? ('<small class="text-muted ml-2">' + $('<div>').text(it.tanggal).html() + '</small>') : '';
      html += '<button type="button" class="list-group-item list-group-item-action py-1 px-2 text-right x-tarif-pilih" data-value="' + $('<div>').text(String(it.tarif)).html() + '">' + $('<div>').text(val).html() + tgl + '</button>';
    });
    $box.html(html).show();
  }

  function loadTarifHist(cb){
    if (Array.isArray(tarifHistCache)) { cb(tarifHistCache); return; }
    $.getJSON('get_tarif_histori_listrik_gardu_induk.php', function(resp){
      tarifHistCache = (resp && resp.success && Array.isArray(resp.data)) ? resp.data : [];
      cb(tarifHistCache);
    }).fail(function(){ cb([]); });
  }

  var table = $('#listrikGarduTable').DataTable({
    processing:true, serverSide:true, ordering:false, responsive:true,
    ajax:{ url:'listrik_gardu_induk_serverside.php', type:'POST', data:function(d){ d.start_date=$('#filterStartDate').val(); d.end_date=$('#filterEndDate').val(); }},
    columns:[
      {data:null,render:function(d,t,r,m){return m.row+m.settings._iDisplayStart+1;}},
      {data:'tanggal_formatted'},
      {data:'lvbp_kwh',render:function(d){return fmt(d,3)}},{data:'vbp_kwh',render:function(d){return fmt(d,3)}},{data:'kvarh',render:function(d){return fmt(d,3)}},
      {data:'cos_phi',render:function(d){return fmt(d,3)}},{data:'total_daya_perday_kw',render:function(d){return fmt(d,2)}},
      {data:'total_daya_perjam_kwh',render:function(d){return fmt(d,2)}},{data:'rp_per_kwh',render:function(d){return fmt(d,3)}},
      {data:'biaya_perday_rp',render:function(d){return fmt(d,2)}},{data:'efisiensi_persen',render:function(d){return fmt(d,2)}},{data:'kva_pln_perjam',render:function(d){return fmt(d,2)}},
      {data:'ket',render:function(d){return d||'-';}},{data:'created_by',render:function(d){return d||'-';}},
      {data:'id',render:function(id){
        if(!id) return '-';
        var btns = ['<button class="btn btn-info btn-detail" data-id="'+id+'"><i class="fas fa-eye"></i></button>'];
        if (canEdit) btns.push('<button class="btn btn-warning btn-edit" data-id="'+id+'"><i class="fas fa-edit"></i></button>');
        if (canDelete) btns.push('<button class="btn btn-danger btn-delete" data-id="'+id+'"><i class="fas fa-trash"></i></button>');
        return '<div class="btn-group btn-group-sm">' + btns.join('') + '</div>';
      }}
    ]
  });

  $('#filterStartDate,#filterEndDate').on('change', function(){ table.ajax.reload(); });
  $('#btnResetFilter').on('click', function(){ $('#filterStartDate,#filterEndDate').val(''); table.search('').draw(); });

  $('#btnTambahData').on('click', function(e){ e.preventDefault(); $('#formListrikGardu')[0].reset(); $('#xTanggal').val(new Date().toISOString().slice(0,10)); $('#xFaktor').val('6000'); $('#xTarif').val(''); $('#xPf').val('0.95'); $('#xKapasitas').val('4930.5'); $('#modalTambahListrikGardu').modal('show'); });
  $('#btnTambahCatatan').on('click', function(e){ e.preventDefault(); $('#formCatatanListrikGardu')[0].reset(); $('#xCatatanTanggal').val(new Date().toISOString().slice(0,10)); $('#modalTambahCatatanListrikGardu').modal('show'); });

  $(document).on('input','.num-only',function(){ this.value=this.value.replace(/[^0-9.,]/g,''); });
  $('#xTarif').on('focus click', function(){ loadTarifHist(renderTarifHist); });
  $(document).on('click', '.x-tarif-pilih', function(){ var n=parseNum($(this).data('value')); if(!isNaN(n)){ $('#xTarif').val(fmtInput(n,3)); } $('#xTarifHist').hide(); });
  $(document).on('click', function(e){ if ($(e.target).closest('#xTarif, #xTarifHist').length === 0) $('#xTarifHist').hide(); });

  $('#formListrikGardu').on('submit', function(e){
    e.preventDefault();
    $.ajax({ url:'save_listrik_gardu_induk.php', type:'POST', data:$(this).serialize(), dataType:'json',
      success:function(resp){ if(resp&&resp.success){ $('#modalTambahListrikGardu').modal('hide'); Swal.fire({icon:'success',title:'Sukses',text:resp.message||'Tersimpan.'}); table.ajax.reload(null,false);} else {Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal menyimpan.'});}},
      error:function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan.'}); }
    });
  });

  $('#formCatatanListrikGardu').on('submit', function(e){
    e.preventDefault();
    $.ajax({ url:'save_catatan_listrik_gardu_induk.php', type:'POST', data:$(this).serialize(), dataType:'json',
      success:function(resp){ if(resp&&resp.success){ $('#modalTambahCatatanListrikGardu').modal('hide'); Swal.fire({icon:'success',title:'Sukses',text:resp.message||'Catatan tersimpan.'}); table.ajax.reload(null,false);} else { Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal menyimpan catatan.'}); } },
      error:function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan saat menyimpan catatan.'}); }
    });
  });

  $(document).on('click','.btn-detail',function(){ var id=$(this).data('id'); if(id) window.location.href='view_listrik_gardu_induk.php?id='+encodeURIComponent(id); });
  $(document).on('click','.btn-edit',function(){ var id=$(this).data('id'); if(id) window.location.href='edit_listrik_gardu_induk.php?id='+encodeURIComponent(id); });
  $(document).on('click','.btn-delete',function(){ var id=$(this).data('id'); if(!id) return; Swal.fire({title:'Hapus Data?',text:'Data akan dihapus permanen.',icon:'warning',showCancelButton:true,confirmButtonColor:'#d33',cancelButtonColor:'#3085d6',confirmButtonText:'Ya, Hapus!',cancelButtonText:'Batal'}).then(function(r){ if(r.isConfirmed){ window.location.href='delete_listrik_gardu_induk.php?id='+encodeURIComponent(id); }}); });
});
</script>
