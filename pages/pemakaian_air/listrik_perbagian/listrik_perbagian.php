<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$menuId = 230;
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
        <h1>PEMAKAIAN LISTRIK PER BAGIAN</h1>
      </div>
      <div class="col-sm-6 text-right">
        <a href="/gg_app/pages/pemakaian_air/pemakaian_energi.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
      </div>
    </div>
  </div>
</section>
  <section class="content">
    <div class="container-fluid">
      <div class="card card-primary">
        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center">
          <h3 class="card-title"><i class="fas fa-list mr-1"></i> Data Listrik Per Bagian Harian</h3>
          <div class="d-flex ml-auto" style="gap:8px;">
            <a href="report_listrik_perbagian.php" class="btn btn-info btn-sm"><i class="fas fa-file-alt"></i> Detail Report</a>
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
          <table id="listrikPerBagianTable" class="table table-hover table-sm nowrap text-center" style="width:100%">
            <thead class="thead-light">
              <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>KWH PLN</th>
                <th>Utility AMP</th>
                <th>Utility kWh/hari</th>
                <th>DF AMP</th>
                <th>DF kWh/hari</th>
                <th>Weaving 1 AMP</th>
                <th>Weaving 1 kWh/hari</th>
                <th>Weaving 2 AMP</th>
                <th>Weaving 2 kWh/hari</th>
                <th>Jumlah kWh/hari</th>
                <th>Jumlah Ampere</th>
                <th>KWH/JAM</th>
                <th>Efisiensi (%)</th>
                <th>Ket</th>
                <th>Created By</th>
                <th>Aksi</th>
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
  .lpb-input-table{border-collapse:collapse;width:100%;min-width:1320px}
  .lpb-input-table th,.lpb-input-table td{border:1px solid #c9ced4;padding:5px 6px;text-align:center;vertical-align:middle}
  .lpb-input-table .grp-main{background:#b7c9de;font-weight:700;font-size:30px}
  .lpb-input-table .grp-kwh{background:#e7b07c;font-weight:700}
  .lpb-input-table .grp-utility{background:#f0f0f0;font-weight:700}
  .lpb-input-table .grp-df{background:#c9d6e6;font-weight:700}
  .lpb-input-table .grp-w1{background:#ead9d9;font-weight:700}
  .lpb-input-table .grp-w2{background:#ead9d9;font-weight:700}
  .lpb-input-table .grp-jumlah{background:#f0dfdf;font-weight:700}
  .lpb-input-table .grp-lain{background:#f0dfdf;font-weight:700}
  .lpb-input-table .sub{background:#f8f9fa;font-weight:700}
  .lpb-input-table .unit{font-size:11px;background:#fafafa;font-weight:700}
  .lpb-input-table .cell-blue{background:#c2d4e7}
  .lpb-input-table .cell-yellow{background:#ffff00}
  .lpb-input-table input{height:32px;text-align:right}
</style>

<div class="modal fade" id="modalTambahListrikPerBagian" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl" style="max-width:95%;" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title">Input Listrik Per Bagian</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="formListrikPerBagian" autocomplete="off">
        <input type="hidden" name="id" id="xId">
        <div class="modal-body">
          <div class="form-row mb-2">
            <div class="form-group col-md-3 mb-2">
              <label class="font-weight-bold">Tanggal (TGL)</label>
              <input type="date" name="tanggal" id="xTanggal" class="form-control" required>
            </div>
            <div class="form-group col-md-7 mb-2">
              <label class="font-weight-bold">Keterangan (KET)</label>
              <input type="text" name="ket" id="xKet" class="form-control" placeholder="Isi keterangan jika diperlukan">
            </div>
            <div class="form-group col-md-2 mb-2 d-flex align-items-end">
              <button type="button" class="btn btn-info btn-sm w-100" id="btnAmbilKwhPln"><i class="fas fa-sync-alt"></i> Ambil KWH PLN</button>
            </div>
          </div>

          <div class="table-responsive">
            <table class="lpb-input-table">
              <thead>
                <tr><th class="grp-main" colspan="13">PEMAKAIAN KWH METER PLN</th></tr>
                <tr>
                  <th class="grp-kwh" rowspan="2">KWH PLN<br>(KWH)</th>
                  <th class="grp-utility" colspan="2">KWH UTILITY</th>
                  <th class="grp-df" colspan="2">DF</th>
                  <th class="grp-w1" colspan="2">WEAVING 1</th>
                  <th class="grp-w2" colspan="2">WEAVING 2</th>
                  <th class="grp-jumlah" colspan="1">JUMLAH</th>
                  <th class="grp-lain" colspan="1">JUMLAH AMPERE</th>
                  <th class="grp-lain" colspan="1">KWH/JAM</th>
                  <th class="grp-lain" colspan="1">EFISIENSI</th>
                </tr>
                <tr>
                  <th class="sub">AMP ACB</th><th class="sub">Kwh/hari</th>
                  <th class="sub">AMP ACB</th><th class="sub">Kwh/hari</th>
                  <th class="sub">AMP ACB</th><th class="sub">Kwh/hari</th>
                  <th class="sub">AMP ACB</th><th class="sub">Kwh/hari</th>
                  <th class="sub">Kwh/hari</th>
                  <th class="sub">AMPERE</th>
                  <th class="sub">Kwh/JAM</th>
                  <th class="sub">PERSEN</th>
                </tr>
                <tr>
                  <th class="unit">KWH</th>
                  <th class="unit">AMP</th><th class="unit">kWh/hari</th>
                  <th class="unit">AMP</th><th class="unit">kWh/hari</th>
                  <th class="unit">AMP</th><th class="unit">kWh/hari</th>
                  <th class="unit">AMP</th><th class="unit">kWh/hari</th>
                  <th class="unit">kWh/hari</th>
                  <th class="unit">AMPERE</th>
                  <th class="unit">kWh/JAM</th>
                  <th class="unit">%</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="cell-blue"><input type="text" name="kwh_pln" id="xKwhPln" class="form-control form-control-sm num-only"></td>
                  <td class="cell-blue"><input type="text" name="kwh_utility_amp_acb" id="xUtilityAmp" class="form-control form-control-sm num-only" required></td>
                  <td class="cell-yellow"><input type="text" name="kwh_utility_kwh_hari" id="xUtilityKwh" class="form-control form-control-sm" readonly></td>
                  <td class="cell-blue"><input type="text" name="df_amp_acb" id="xDfAmp" class="form-control form-control-sm num-only" required></td>
                  <td class="cell-yellow"><input type="text" name="df_kwh_hari" id="xDfKwh" class="form-control form-control-sm" readonly></td>
                  <td class="cell-blue"><input type="text" name="weaving1_amp_acb" id="xW1Amp" class="form-control form-control-sm num-only" required></td>
                  <td class="cell-yellow"><input type="text" name="weaving1_kwh_hari" id="xW1Kwh" class="form-control form-control-sm" readonly></td>
                  <td class="cell-blue"><input type="text" name="weaving2_amp_acb" id="xW2Amp" class="form-control form-control-sm num-only" required></td>
                  <td class="cell-yellow"><input type="text" name="weaving2_kwh_hari" id="xW2Kwh" class="form-control form-control-sm" readonly></td>
                  <td class="cell-yellow"><input type="text" name="jumlah_kwh_hari" id="xJumlahKwh" class="form-control form-control-sm" readonly></td>
                  <td class="cell-blue"><input type="text" name="jumlah_ampere" id="xJumlahAmp" class="form-control form-control-sm" readonly></td>
                  <td class="cell-yellow"><input type="text" name="kwh_per_jam" id="xKwhJam" class="form-control form-control-sm" readonly></td>
                  <td class="cell-blue"><input type="text" name="efisiensi_persen" id="xEfisiensi" class="form-control form-control-sm" readonly></td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="form-row mt-2">
            <div class="form-group col-md-3 mb-0">
              <label class="font-weight-bold">Faktor Konversi</label>
              <input type="text" name="faktor_konversi" id="xFaktor" class="form-control form-control-sm num-only" value="650">
            </div>
            <div class="form-group col-md-3 mb-0">
              <label class="font-weight-bold">Kapasitas Pembagi</label>
              <input type="text" name="kapasitas_pembagi" id="xKapasitas" class="form-control form-control-sm num-only" value="3292">
            </div>
            <div class="form-group col-md-6 mb-0 d-flex align-items-end">
              <div class="alert alert-info mb-0 py-2 px-3 w-100">
                Rumus mengikuti Excel: D/F/H/J = (AMP*650)/1000*24, K = D+F+H+(J/1000*24), L = C+E+G+I, M = K/24, N = M/3292*100.
              </div>
            </div>
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

<div class="modal fade" id="modalTambahCatatanListrikPerBagian" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title">Tambah Catatan Listrik Per Bagian</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="formCatatanListrikPerBagian" autocomplete="off">
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
  var canEdit = <?= $canEdit ? 'true' : 'false' ?>;
  var canDelete = <?= $canDelete ? 'true' : 'false' ?>;
  function parseNum(v){ if(v===null||v===undefined) return NaN; v=String(v).trim().replace(/,/g,''); if(v==='') return NaN; var n=Number(v); return isNaN(n)?NaN:n; }
  function fmt(v,d){ var n=parseNum(v); return isNaN(n)?'-':n.toLocaleString('en-US',{minimumFractionDigits:d,maximumFractionDigits:d}); }
  function fmtInput(v,d){ var n=parseNum(v); return isNaN(n)?'':n.toLocaleString('en-US',{minimumFractionDigits:d,maximumFractionDigits:d}); }

  function recalc(){
    var faktor = parseNum($('#xFaktor').val()); if (isNaN(faktor)) faktor = 650;
    var kapasitas = parseNum($('#xKapasitas').val()); if (isNaN(kapasitas) || kapasitas === 0) kapasitas = 3292;

    var c = parseNum($('#xUtilityAmp').val()); if (isNaN(c)) c = 0;
    var e = parseNum($('#xDfAmp').val()); if (isNaN(e)) e = 0;
    var g = parseNum($('#xW1Amp').val()); if (isNaN(g)) g = 0;
    var i = parseNum($('#xW2Amp').val()); if (isNaN(i)) i = 0;

    var d = ((c * faktor) / 1000) * 24;
    var f = ((e * faktor) / 1000) * 24;
    var h = ((g * faktor) / 1000) * 24;
    var j = ((i * faktor) / 1000) * 24;
    var k = d + f + h + ((j / 1000) * 24);
    var l = c + e + g + i;
    var m = k / 24;
    var n = kapasitas !== 0 ? (m / kapasitas) * 100 : 0;

    $('#xUtilityKwh').val(fmtInput(d,2));
    $('#xDfKwh').val(fmtInput(f,2));
    $('#xW1Kwh').val(fmtInput(h,2));
    $('#xW2Kwh').val(fmtInput(j,2));
    $('#xJumlahKwh').val(fmtInput(k,2));
    $('#xJumlahAmp').val(fmtInput(l,2));
    $('#xKwhJam').val(fmtInput(m,2));
    $('#xEfisiensi').val(fmtInput(n,2));
  }

  function loadKwhPlnByDate(dateValue){
    if(!dateValue){ return; }
    $.getJSON('get_kwh_pln_listrik_perbagian.php', { tanggal: dateValue }, function(resp){
      if(resp && resp.success){
        $('#xKwhPln').val(fmtInput(resp.kwh_pln,2));
      }
    });
  }

  var table = $('#listrikPerBagianTable').DataTable({
    processing:true, serverSide:true, ordering:false, responsive:true,
    ajax:{ url:'listrik_perbagian_serverside.php', type:'POST', data:function(d){ d.start_date=$('#filterStartDate').val(); d.end_date=$('#filterEndDate').val(); }},
    columns:[
      {data:null,render:function(d,t,r,m){return m.row+m.settings._iDisplayStart+1;}},
      {data:'tanggal_formatted'},
      {data:'kwh_pln',render:function(d){return fmt(d,2)}},
      {data:'kwh_utility_amp_acb',render:function(d){return fmt(d,2)}},
      {data:'kwh_utility_kwh_hari',render:function(d){return fmt(d,2)}},
      {data:'df_amp_acb',render:function(d){return fmt(d,2)}},
      {data:'df_kwh_hari',render:function(d){return fmt(d,2)}},
      {data:'weaving1_amp_acb',render:function(d){return fmt(d,2)}},
      {data:'weaving1_kwh_hari',render:function(d){return fmt(d,2)}},
      {data:'weaving2_amp_acb',render:function(d){return fmt(d,2)}},
      {data:'weaving2_kwh_hari',render:function(d){return fmt(d,2)}},
      {data:'jumlah_kwh_hari',render:function(d){return fmt(d,2)}},
      {data:'jumlah_ampere',render:function(d){return fmt(d,2)}},
      {data:'kwh_per_jam',render:function(d){return fmt(d,2)}},
      {data:'efisiensi_persen',render:function(d){return fmt(d,2)}},
      {data:'ket',render:function(d){return d||'-';}},
      {data:'created_by',render:function(d){return d||'-';}},
      {data:'id',render:function(id){
        if(!id) return '-';
        var html = '<div class="btn-group btn-group-sm">';
        html += '<button class="btn btn-info btn-detail" data-id="'+id+'"><i class="fas fa-eye"></i></button>';
        if (canEdit) html += '<button class="btn btn-warning btn-edit" data-id="'+id+'"><i class="fas fa-edit"></i></button>';
        if (canDelete) html += '<button class="btn btn-danger btn-delete" data-id="'+id+'"><i class="fas fa-trash"></i></button>';
        html += '</div>';
        return html;
      }}
    ]
  });

  $('#filterStartDate,#filterEndDate').on('change', function(){ table.ajax.reload(); });
  $('#btnResetFilter').on('click', function(){ $('#filterStartDate,#filterEndDate').val(''); table.search('').draw(); });
  $('#btnTambahData').on('click', function(e){
    e.preventDefault();
    $('#formListrikPerBagian')[0].reset();
    $('#xId').val('');
    $('#xTanggal').prop('readonly', false);
    $('#xTanggal').val(new Date().toISOString().slice(0,10));
    $('#xFaktor').val('650');
    $('#xKapasitas').val('3292');
    loadKwhPlnByDate($('#xTanggal').val());
    recalc();
    $('#modalTambahListrikPerBagian').modal('show');
  });
  $('#btnTambahCatatan').on('click', function(e){
    e.preventDefault();
    $('#formCatatanListrikPerBagian')[0].reset();
    $('#xCatatanTanggal').val(new Date().toISOString().slice(0,10));
    $('#modalTambahCatatanListrikPerBagian').modal('show');
  });
  $('#btnAmbilKwhPln').on('click', function(){ loadKwhPlnByDate($('#xTanggal').val()); });
  $('#xTanggal').on('change', function(){ loadKwhPlnByDate($(this).val()); });
  $(document).on('input','.num-only',function(){ this.value=this.value.replace(/[^0-9.,]/g,''); });
  $('#formListrikPerBagian .num-only').on('input blur', recalc);

  $('#formListrikPerBagian').on('submit', function(e){
    e.preventDefault();
    $.ajax({
      url:'save_listrik_perbagian.php',
      type:'POST',
      data:$(this).serialize(),
      dataType:'json',
      success:function(resp){
        if(resp&&resp.success){
          $('#modalTambahListrikPerBagian').modal('hide');
          Swal.fire({icon:'success',title:'Sukses',text:resp.message||'Tersimpan.'});
          table.ajax.reload(null,false);
        } else {
          Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal menyimpan.'});
        }
      },
      error:function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan.'}); }
    });
  });

  $('#formCatatanListrikPerBagian').on('submit', function(e){
    e.preventDefault();
    $.ajax({
      url:'save_catatan_listrik_perbagian.php',
      type:'POST',
      data:$(this).serialize(),
      dataType:'json',
      success:function(resp){
        if(resp&&resp.success){
          $('#modalTambahCatatanListrikPerBagian').modal('hide');
          Swal.fire({icon:'success',title:'Sukses',text:resp.message||'Catatan tersimpan.'});
          table.ajax.reload(null,false);
        } else {
          Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal menyimpan catatan.'});
        }
      },
      error:function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan.'}); }
    });
  });

  $('#listrikPerBagianTable').on('click','.btn-detail',function(){ window.location.href='view_listrik_perbagian.php?id='+$(this).data('id'); });
  $('#listrikPerBagianTable').on('click','.btn-edit',function(){
    var tr = $(this).closest('tr');
    var row = table.row(tr);
    if (!row.data() && tr.hasClass('child')) row = table.row(tr.prev());
    var d = row.data();
    if (!d) {
      Swal.fire({icon:'warning',title:'Perhatian',text:'Data tidak ditemukan untuk diedit.'});
      return;
    }

    $('#formListrikPerBagian')[0].reset();
    $('#xId').val(d.id || '');
    $('#xTanggal').val(d.tanggal || '');
    $('#xTanggal').prop('readonly', true);
    $('#xKwhPln').val(fmtInput(d.kwh_pln,2));
    $('#xUtilityAmp').val(fmtInput(d.kwh_utility_amp_acb,2));
    $('#xDfAmp').val(fmtInput(d.df_amp_acb,2));
    $('#xW1Amp').val(fmtInput(d.weaving1_amp_acb,2));
    $('#xW2Amp').val(fmtInput(d.weaving2_amp_acb,2));
    $('#xKet').val(d.ket || '');
    $('#xFaktor').val('650');
    $('#xKapasitas').val('3292');
    recalc();
    $('#modalTambahListrikPerBagian').modal('show');
  });
  $('#listrikPerBagianTable').on('click','.btn-delete',function(){
    var id=$(this).data('id');
    Swal.fire({title:'Hapus data?',text:'Data ini akan dihapus permanen.',icon:'warning',showCancelButton:true,confirmButtonText:'Ya, Hapus'})
      .then(function(r){
        if(!r.isConfirmed) return;
        $.post('delete_listrik_perbagian.php',{id:id},function(resp){
          if(resp&&resp.success){
            Swal.fire({icon:'success',title:'Sukses',text:resp.message||'Terhapus.'});
            table.ajax.reload(null,false);
          } else {
            Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal menghapus.'});
          }
        },'json').fail(function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan.'}); });
      });
  });
});
</script>
