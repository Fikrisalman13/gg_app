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
        <h1>METER AIR DAN STEAM 20 TON LAMA</h1>
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
          <h3 class="card-title"><i class="fas fa-list mr-1"></i> Data Meter Air & Steam 20 Ton Lama</h3>
          <div class="d-flex ml-auto" style="gap:8px;">
            <a href="report_20tonlama.php" class="btn btn-info btn-sm"><i class="fas fa-file-alt"></i> Detail Report</a>
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
          <table id="table20TonLama" class="table table-hover table-sm nowrap text-center" style="width:100%">
            <thead class="thead-light">
              <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Air Awal (m3)</th>
                <th>Air Akhir (m3)</th>
                <th>Air Total (m3)</th>
                <th>Air Rata/Jam</th>
                <th>Steam Awal (ton)</th>
                <th>Steam Akhir (ton)</th>
                <th>Steam Total (ton)</th>
                <th>Steam Rata/Jam</th>
                <th>Cut Off</th>
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
  .tl-input-table{border-collapse:collapse;width:100%;min-width:1200px}
  .tl-input-table th,.tl-input-table td{border:1px solid #c9ced4;padding:5px 6px;text-align:center;vertical-align:middle}
  .tl-input-table .head-air{background:#b7cae2;font-weight:700}
  .tl-input-table .head-steam{background:#b7cae2;font-weight:700}
  .tl-input-table .sub{background:#f3e2e2;font-weight:700}
  .tl-input-table .unit{background:#fafafa;font-size:11px;font-weight:700}
  .tl-input-table .cell-blue{background:#c4d5e9}
  .tl-input-table .cell-gray{background:#efefef}
  .tl-input-table input{height:32px;text-align:right}
  .tl-input-table .input-group-sm .btn{height:32px}
</style>

<div class="modal fade" id="modalTambah20TonLama" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title">Input Meter Air & Steam 20 Ton Lama</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="form20TonLama" autocomplete="off">
        <input type="hidden" name="id" id="xId">
        <div class="modal-body">
          <div class="form-row mb-2">
            <div class="form-group col-md-3 mb-2">
              <label class="font-weight-bold">Tanggal (TGL)</label>
              <input type="date" name="tanggal" id="xTanggal" class="form-control" required>
            </div>
            <div class="form-group col-md-3 mb-2">
              <label class="font-weight-bold">Cut Off Jam</label>
              <input type="time" name="cut_off_jam" id="xCutOff" class="form-control" value="09:00">
            </div>
            <div class="form-group col-md-6 mb-2">
              <div class="alert alert-light border mb-0 py-2 px-3" style="height:38px;display:flex;align-items:center;">
                Isi keterangan pada kolom KET Air / KET Steam.
              </div>
            </div>
          </div>

          <div class="table-responsive">
            <table class="tl-input-table">
              <thead>
                <tr>
                  <th class="head-air" colspan="5">METER AIR BOILER STEAM 20 TON</th>
                  <th class="head-steam" colspan="5">METER STEAM BOILER STEAM 20 TON</th>
                </tr>
                <tr>
                  <th class="sub">Awal</th>
                  <th class="sub">Akhir</th>
                  <th class="sub">Total Pemakaian</th>
                  <th class="sub">Rata-rata / Jam</th>
                  <th class="sub">KET</th>
                  <th class="sub">Awal</th>
                  <th class="sub">Akhir</th>
                  <th class="sub">Total Pemakaian</th>
                  <th class="sub">Rata-rata / Jam</th>
                  <th class="sub">KET</th>
                </tr>
                <tr>
                  <th class="unit">m3</th>
                  <th class="unit">m3</th>
                  <th class="unit">m3</th>
                  <th class="unit">m3</th>
                  <th class="unit"></th>
                  <th class="unit">ton</th>
                  <th class="unit">ton</th>
                  <th class="unit">ton</th>
                  <th class="unit">ton</th>
                  <th class="unit"></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="cell-gray">
                    <div class="input-group input-group-sm">
                      <input type="text" name="air_awal_m3" id="xAirAwal" class="form-control form-control-sm num-only" required>
                      <div class="input-group-append">
                        <button type="button" class="btn btn-outline-secondary" id="btnAutoAirAwal" title="Ambil dari Air Akhir tanggal sebelumnya">
                          <i class="fas fa-download"></i>
                        </button>
                      </div>
                    </div>
                  </td>
                  <td class="cell-gray"><input type="text" name="air_akhir_m3" id="xAirAkhir" class="form-control form-control-sm num-only" required></td>
                  <td class="cell-blue"><input type="text" name="air_total_pemakaian_m3" id="xAirTotal" class="form-control form-control-sm" readonly></td>
                  <td class="cell-gray"><input type="text" name="air_rata_rata_per_jam_m3" id="xAirRata" class="form-control form-control-sm" readonly></td>
                  <td class="cell-gray"><input type="text" name="air_ket" id="xAirKet" class="form-control form-control-sm text-left" style="text-align:left;"></td>
                  <td class="cell-gray">
                    <div class="input-group input-group-sm">
                      <input type="text" name="steam_awal_ton" id="xSteamAwal" class="form-control form-control-sm num-only" required>
                      <div class="input-group-append">
                        <button type="button" class="btn btn-outline-secondary" id="btnAutoSteamAwal" title="Ambil dari Steam Akhir tanggal sebelumnya">
                          <i class="fas fa-download"></i>
                        </button>
                      </div>
                    </div>
                  </td>
                  <td class="cell-gray"><input type="text" name="steam_akhir_ton" id="xSteamAkhir" class="form-control form-control-sm num-only" required></td>
                  <td class="cell-blue"><input type="text" name="steam_total_pemakaian_ton" id="xSteamTotal" class="form-control form-control-sm" readonly></td>
                  <td class="cell-gray"><input type="text" name="steam_rata_rata_per_jam_ton" id="xSteamRata" class="form-control form-control-sm" readonly></td>
                  <td class="cell-gray"><input type="text" name="steam_ket" id="xSteamKet" class="form-control form-control-sm text-left" style="text-align:left;"></td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="form-row mt-2">
            <div class="col-12">
              <div class="alert alert-info mb-0 py-2 px-3">
                Rumus: Total Air = Air Akhir - Air Awal, Rata/Jam Air = Total Air / 24. Total Steam = Steam Akhir - Steam Awal, Rata/Jam Steam = Total Steam / 24.
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

<div class="modal fade" id="modalTambahCatatan20TonLama" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title">Tambah Catatan 20 Ton Lama</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="formCatatan20TonLama" autocomplete="off">
        <div class="modal-body">
          <div class="form-group">
            <label>Tanggal</label>
            <input type="date" name="tanggal" id="xCatatanTanggal" class="form-control" required>
          </div>
          <div class="form-group mb-0">
            <label>Catatan</label>
            <textarea name="note" id="xCatatanText" class="form-control" rows="4" required></textarea>
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
    var airAwal = parseNum($('#xAirAwal').val()); if (isNaN(airAwal)) airAwal = 0;
    var airAkhir = parseNum($('#xAirAkhir').val()); if (isNaN(airAkhir)) airAkhir = 0;
    var airTotal = airAkhir - airAwal; if (airTotal < 0) airTotal = 0;
    var airRata = airTotal / 24;
    $('#xAirTotal').val(fmtInput(airTotal,2));
    $('#xAirRata').val(fmtInput(airRata,2));

    var steamAwal = parseNum($('#xSteamAwal').val()); if (isNaN(steamAwal)) steamAwal = 0;
    var steamAkhir = parseNum($('#xSteamAkhir').val()); if (isNaN(steamAkhir)) steamAkhir = 0;
    var steamTotal = steamAkhir - steamAwal; if (steamTotal < 0) steamTotal = 0;
    var steamRata = steamTotal / 24;
    $('#xSteamTotal').val(fmtInput(steamTotal,2));
    $('#xSteamRata').val(fmtInput(steamRata,2));
  }

  var table = $('#table20TonLama').DataTable({
    processing:true, serverSide:true, ordering:false, responsive:true,
    ajax:{ url:'20tonlama_serverside.php', type:'POST', data:function(d){ d.start_date=$('#filterStartDate').val(); d.end_date=$('#filterEndDate').val(); }},
    columns:[
      {data:null,render:function(d,t,r,m){return m.row+m.settings._iDisplayStart+1;}},
      {data:'tanggal_formatted'},
      {data:'air_awal_m3',render:function(d){return fmt(d,2)}},
      {data:'air_akhir_m3',render:function(d){return fmt(d,2)}},
      {data:'air_total_pemakaian_m3',render:function(d){return fmt(d,2)}},
      {data:'air_rata_rata_per_jam_m3',render:function(d){return fmt(d,2)}},
      {data:'steam_awal_ton',render:function(d){return fmt(d,2)}},
      {data:'steam_akhir_ton',render:function(d){return fmt(d,2)}},
      {data:'steam_total_pemakaian_ton',render:function(d){return fmt(d,2)}},
      {data:'steam_rata_rata_per_jam_ton',render:function(d){return fmt(d,2)}},
      {data:'cut_off_jam',render:function(d){return d||'09:00';}},
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
    $('#form20TonLama')[0].reset();
    $('#xId').val('');
    $('#xTanggal').prop('readonly', false).val(new Date().toISOString().slice(0,10));
    $('#xCutOff').val('09:00');
    recalc();
    $('#modalTambah20TonLama').modal('show');
  });
  $('#btnTambahCatatan').on('click', function(e){
    e.preventDefault();
    $('#formCatatan20TonLama')[0].reset();
    $('#xCatatanTanggal').val(new Date().toISOString().slice(0,10));
    $('#modalTambahCatatan20TonLama').modal('show');
  });

  $(document).on('input','.num-only',function(){ this.value=this.value.replace(/[^0-9.,]/g,''); });
  $('#form20TonLama .num-only').on('input blur', recalc);

  function isiAwalOtomatis(type){
    var tanggal = $('#xTanggal').val();
    if (!tanggal) {
      Swal.fire({icon:'warning',title:'Tanggal belum dipilih',text:'Pilih tanggal terlebih dahulu.'});
      return;
    }

    $.ajax({
      url:'get_prev_20tonlama.php',
      type:'GET',
      dataType:'json',
      data:{tanggal:tanggal},
      success:function(resp){
        if(!(resp && resp.success)){
          Swal.fire({icon:'error',title:'Gagal',text:(resp && resp.message) ? resp.message : 'Gagal mengambil data sebelumnya.'});
          return;
        }

        if(type === 'air'){
          if(resp.air_akhir_m3 !== null && resp.air_akhir_m3 !== ''){
            $('#xAirAwal').val(fmtInput(resp.air_akhir_m3,2));
            recalc();
          } else {
            Swal.fire({icon:'info',title:'Data tidak ditemukan',text:'Tidak ada Air Akhir tanggal sebelumnya untuk tanggal ini.'});
          }
          return;
        }

        if(resp.steam_akhir_ton !== null && resp.steam_akhir_ton !== ''){
          $('#xSteamAwal').val(fmtInput(resp.steam_akhir_ton,2));
          recalc();
        } else {
          Swal.fire({icon:'info',title:'Data tidak ditemukan',text:'Tidak ada Steam Akhir tanggal sebelumnya untuk tanggal ini.'});
        }
      },
      error:function(){
        Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan saat mengambil data sebelumnya.'});
      }
    });
  }

  $('#btnAutoAirAwal').on('click', function(){ isiAwalOtomatis('air'); });
  $('#btnAutoSteamAwal').on('click', function(){ isiAwalOtomatis('steam'); });

  $('#form20TonLama').on('submit', function(e){
    e.preventDefault();
    $.ajax({
      url:'save_20tonlama.php',
      type:'POST',
      data:$(this).serialize(),
      dataType:'json',
      success:function(resp){
        if(resp&&resp.success){
          $('#modalTambah20TonLama').modal('hide');
          Swal.fire({icon:'success',title:'Sukses',text:resp.message||'Tersimpan.'});
          table.ajax.reload(null,false);
        } else {
          Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal menyimpan.'});
        }
      },
      error:function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan.'}); }
    });
  });

  $('#formCatatan20TonLama').on('submit', function(e){
    e.preventDefault();
    $.ajax({
      url:'save_catatan_20tonlama.php',
      type:'POST',
      data:$(this).serialize(),
      dataType:'json',
      success:function(resp){
        if(resp&&resp.success){
          $('#modalTambahCatatan20TonLama').modal('hide');
          Swal.fire({icon:'success',title:'Sukses',text:resp.message||'Catatan tersimpan.'});
          table.ajax.reload(null,false);
        } else {
          Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal menyimpan catatan.'});
        }
      },
      error:function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan.'}); }
    });
  });

  $('#table20TonLama').on('click','.btn-detail',function(){ window.location.href='view_20tonlama.php?id='+$(this).data('id'); });
  $('#table20TonLama').on('click','.btn-edit',function(){
    var tr = $(this).closest('tr');
    var row = table.row(tr);
    if (!row.data() && tr.hasClass('child')) row = table.row(tr.prev());
    var d = row.data();
    if (!d) return;

    $('#form20TonLama')[0].reset();
    $('#xId').val(d.id || '');
    $('#xTanggal').prop('readonly', true).val(d.tanggal || '');
    $('#xCutOff').val(d.cut_off_jam || '09:00');
    $('#xAirAwal').val(fmtInput(d.air_awal_m3,2));
    $('#xAirAkhir').val(fmtInput(d.air_akhir_m3,2));
    $('#xAirKet').val(d.air_ket || '');
    $('#xSteamAwal').val(fmtInput(d.steam_awal_ton,2));
    $('#xSteamAkhir').val(fmtInput(d.steam_akhir_ton,2));
    $('#xSteamKet').val(d.steam_ket || '');
    recalc();
    $('#modalTambah20TonLama').modal('show');
  });
  $('#table20TonLama').on('click','.btn-delete',function(){
    var id=$(this).data('id');
    Swal.fire({title:'Hapus data?',text:'Data ini akan dihapus permanen.',icon:'warning',showCancelButton:true,confirmButtonText:'Ya, Hapus'})
      .then(function(r){
        if(!r.isConfirmed) return;
        $.post('delete_20tonlama.php',{id:id},function(resp){
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
