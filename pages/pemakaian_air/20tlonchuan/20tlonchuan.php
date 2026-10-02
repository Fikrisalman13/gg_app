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
        <h1>Air dan Steam 20 Ton Lonchuan</h1>
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
          <h3 class="card-title"><i class="fas fa-list mr-1"></i> Data Laju Sesaat Boiler Lonchuan</h3>
          <div class="d-flex ml-auto" style="gap:8px;">
            <a href="report_20tlonchuan.php" class="btn btn-info btn-sm"><i class="fas fa-file-alt"></i> Detail Report</a>
            <?php if (!empty($permissions['CanAdd']) && $permissions['CanAdd'] == 1): ?>
              <a href="#" class="btn btn-success btn-sm" id="btnTambahData"><i class="fas fa-plus"></i> Tambah Data</a>
            <?php endif; ?>
          </div>
        </div>
        <div class="card-body table-responsive">
          <div class="row mb-3">
            <div class="col-md-3"><label>Dari Tanggal</label><input type="date" id="filterStartDate" class="form-control"></div>
            <div class="col-md-3"><label>Sampai Tanggal</label><input type="date" id="filterEndDate" class="form-control"></div>
            <div class="col-md-2 d-flex align-items-end"><button type="button" class="btn btn-secondary btn-sm" id="btnResetFilter"><i class="fas fa-undo"></i> Reset Filter</button></div>
          </div>
          <table id="table20TLonchuan" class="table table-hover table-sm nowrap text-center" style="width:100%">
            <thead class="thead-light">
              <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Jumlah Air (ton)</th>
                <th>Jumlah Steam (ton)</th>
                <th>Note</th>
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
  .tlc-input-table{border-collapse:collapse;width:100%;min-width:800px}
  .tlc-input-table th,.tlc-input-table td{border:1px solid #c9ced4;padding:5px 6px;text-align:center;vertical-align:middle}
  .tlc-input-table .head{background:#b7cae2;font-weight:700}
  .tlc-input-table .sub{background:#f3e2e2;font-weight:700}
  .tlc-input-table .cell-gray{background:#efefef}
  .tlc-input-table input{height:32px;text-align:right}
  .tlc-summary{background:#f8f9fa;border:1px solid #ddd;padding:8px 10px;border-radius:4px}
</style>

<div class="modal fade" id="modalTambah20TLonchuan" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title">Input Laju Sesaat Boiler Lonchuan (20T)</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="form20TLonchuan" autocomplete="off">
        <input type="hidden" name="id" id="xId">
        <div class="modal-body">
          <div class="form-row mb-2">
            <div class="form-group col-md-3 mb-2">
              <label class="font-weight-bold">Tanggal (TGL)</label>
              <input type="date" name="tanggal" id="xTanggal" class="form-control" required>
            </div>
            <div class="form-group col-md-9 mb-2">
              <label class="font-weight-bold">Note</label>
              <input type="text" name="note" id="xNote" class="form-control" placeholder="Catatan singkat (opsional)">
            </div>
          </div>

          <div class="table-responsive">
            <table class="tlc-input-table">
              <thead>
                <tr><th class="head" colspan="3">INPUT PER JAM</th></tr>
                <tr>
                  <th class="sub">Jam</th>
                  <th class="sub">Air (ton)</th>
                  <th class="sub">Steam (ton)</th>
                </tr>
              </thead>
              <tbody>
                <?php for ($idx=0; $idx<24; $idx++): $j = ($idx + 9) % 24; $label = str_pad((string)$j, 2, '0', STR_PAD_LEFT) . ':00'; ?>
                <tr>
                  <td class="cell-gray"><?= htmlspecialchars($label) ?><input type="hidden" name="jam[]" value="<?= $j ?>"></td>
                  <td class="cell-gray"><input type="text" name="air[]" id="air_<?= $j ?>" class="form-control form-control-sm num-only input-air"></td>
                  <td class="cell-gray"><input type="text" name="steam[]" id="steam_<?= $j ?>" class="form-control form-control-sm num-only input-steam"></td>
                </tr>
                <?php endfor; ?>
              </tbody>
            </table>
          </div>

          <div class="form-row mt-2">
            <div class="col-12">
              <div class="tlc-summary d-flex" style="gap:20px;">
                <div><strong>Total Air (ton):</strong> <span id="sumAir">0.00</span></div>
                <div><strong>Total Steam (ton):</strong> <span id="sumSteam">0.00</span></div>
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
    var sumAir = 0, sumSteam = 0;
    $('.input-air').each(function(){ var n=parseNum($(this).val()); if(!isNaN(n)) sumAir += n; });
    $('.input-steam').each(function(){ var n=parseNum($(this).val()); if(!isNaN(n)) sumSteam += n; });
    $('#sumAir').text(fmt(sumAir,2));
    $('#sumSteam').text(fmt(sumSteam,2));
  }

  var table = $('#table20TLonchuan').DataTable({
    processing:true, serverSide:true, ordering:false, responsive:true,
    ajax:{ url:'20tlonchuan_serverside.php', type:'POST', data:function(d){ d.start_date=$('#filterStartDate').val(); d.end_date=$('#filterEndDate').val(); }},
    columns:[
      {data:null,render:function(d,t,r,m){return m.row+m.settings._iDisplayStart+1;}},
      {data:'tanggal_formatted'},
      {data:'jumlah_air_ton',render:function(d){return fmt(d,2)}},
      {data:'jumlah_steam_ton',render:function(d){return fmt(d,2)}},
      {data:'note',render:function(d){return d||'-';}},
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
    $('#form20TLonchuan')[0].reset();
    $('#xId').val('');
    $('#xTanggal').prop('readonly', false).val(new Date().toISOString().slice(0,10));
    $('.input-air,.input-steam').val('');
    recalc();
    $('#modalTambah20TLonchuan').modal('show');
  });

  $(document).on('input','.num-only',function(){ this.value=this.value.replace(/[^0-9.,]/g,''); });
  $('#form20TLonchuan').on('input blur','.num-only', recalc);

  $('#form20TLonchuan').on('submit', function(e){
    e.preventDefault();
    $.ajax({
      url:'save_20tlonchuan.php',
      type:'POST',
      data:$(this).serialize(),
      dataType:'json',
      success:function(resp){
        if(resp&&resp.success){
          $('#modalTambah20TLonchuan').modal('hide');
          Swal.fire({icon:'success',title:'Sukses',text:resp.message||'Tersimpan.'});
          table.ajax.reload(null,false);
        } else {
          Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal menyimpan.'});
        }
      },
      error:function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan.'}); }
    });
  });

  $('#table20TLonchuan').on('click','.btn-detail',function(){ window.location.href='view_20tlonchuan.php?id='+$(this).data('id'); });
  $('#table20TLonchuan').on('click','.btn-edit',function(){
    var id=$(this).data('id');
    if(!id) return;
    $('#form20TLonchuan')[0].reset();
    $('.input-air,.input-steam').val('');
    $('#xId').val(id);
    $.getJSON('get_20tlonchuan_detail.php',{id:id},function(resp){
      if(!resp||!resp.success){
        Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal mengambil data.'});
        return;
      }
      var d = resp.data || {};
      $('#xTanggal').prop('readonly', true).val(d.tanggal || '');
      $('#xNote').val(d.note || '');
      if (d.rows && d.rows.length){
        d.rows.forEach(function(r){
          if (r.jam === undefined || r.jam === null) return;
          var j = String(r.jam);
          $('#air_'+j).val(r.air !== null ? fmtInput(r.air,2) : '');
          $('#steam_'+j).val(r.steam !== null ? fmtInput(r.steam,2) : '');
        });
      }
      recalc();
      $('#modalTambah20TLonchuan').modal('show');
    }).fail(function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan.'}); });
  });

  $('#table20TLonchuan').on('click','.btn-delete',function(){
    var id=$(this).data('id');
    Swal.fire({title:'Hapus data?',text:'Data ini akan dihapus permanen.',icon:'warning',showCancelButton:true,confirmButtonText:'Ya, Hapus'})
      .then(function(r){
        if(!r.isConfirmed) return;
        $.post('delete_20tlonchuan.php',{id:id},function(resp){
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
