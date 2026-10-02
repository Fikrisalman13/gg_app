<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$menuId = 1239;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && (int)$permissions['CanView'] !== 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: /gg_app/index.php');
    exit;
}
$canAdd = !empty($permissions['CanAdd']) && (int)$permissions['CanAdd'] === 1;
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
          <h1>Monitoring Air Boiler - Alstom</h1>
        </div>
        <div class="col-sm-6 text-right">
          <a href="/gg_app/index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="card card-primary">
        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center">
          <h3 class="card-title"><i class="fas fa-list mr-1"></i> Data Monitoring Air Boiler - Alstom</h3>
          <div class="d-flex ml-auto" style="gap:8px;">
            <a href="report_air_boiler.php" class="btn btn-info btn-sm"><i class="fas fa-file-alt"></i> Detail Report</a>
            <?php if ($canAdd): ?>
              <a href="#" class="btn btn-success btn-sm" id="btnTambahData"><i class="fas fa-plus"></i> Tambah Data</a>
            <?php endif; ?>
          </div>
        </div>
        <div class="card-body table-responsive">
          <div class="row mb-3">
            <div class="col-md-3">
              <label>Dari Tanggal</label>
              <input type="date" id="filterStartDate" class="form-control">
            </div>
            <div class="col-md-3">
              <label>Sampai Tanggal</label>
              <input type="date" id="filterEndDate" class="form-control">
            </div>
            <div class="col-md-2 d-flex align-items-end">
              <button type="button" class="btn btn-secondary btn-sm" id="btnResetFilter"><i class="fas fa-undo"></i> Reset Filter</button>
            </div>
          </div>

          <table id="tableAirBoilerAlstom" class="table table-hover table-sm nowrap text-center" style="width:100%">
            <thead class="thead-light">
              <tr>
                <th>No</th>
                <th>Tgl</th>
                <th>Temp Umpan</th>
                <th>DH</th>
                <th>TDS Umpan(ms/cm)</th>
                <th>TDS Umpan(ppm)</th>
                <th>pH</th>
                <th>Temp Boiler</th>
                <th>TDS Boiler (ms/cm)</th>
                <th>TDS Boiler (ppm)</th>
                <th>Blowdown</th>
                <th>Ket</th>
                <th>Display Umpan</th>
                <th>Display Boiler</th>
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
  .ab-input-table { border-collapse: collapse; width: 100%; min-width: 1280px; }
  .ab-input-table th, .ab-input-table td {
    border: 1px solid #c9ced4;
    padding: 5px 6px;
    text-align: center;
    vertical-align: middle;
  }
  .ab-input-table .head { background: #b7cae2; font-weight: 700; }
  .ab-input-table .sub { background: #f3e2e2; font-weight: 700; }
  .ab-input-table .unit { background: #fafafa; font-size: 11px; font-weight: 700; }
  .ab-input-table .cell-blue { background: #c4d5e9; }
  .ab-input-table .cell-gray { background: #efefef; }
  .ab-input-table input { height: 32px; text-align: right; }
</style>

<div class="modal fade" id="modalAirBoilerAlstom" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title">Input Monitoring Air Boiler - Alstom</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="formAirBoilerAlstom" autocomplete="off">
        <input type="hidden" name="id" id="xId">
        <div class="modal-body">
          <div class="form-row mb-2">
            <div class="form-group col-md-3 mb-2">
              <label class="font-weight-bold">Tanggal</label>
              <input type="date" name="tanggal" id="xTanggal" class="form-control" required>
            </div>
            <div class="form-group col-md-9 mb-2">
              <div class="alert alert-light border mb-0 py-2 px-3" style="height:38px;display:flex;align-items:center;">
                Input pada kolom display alat. Rumus: TDS ms/cm = Display x 1000, TDS ppm = TDS ms/cm x 0.64.
              </div>
            </div>
          </div>

          <div class="table-responsive">
            <table class="ab-input-table">
              <thead>
                <tr>
                  <th class="head" colspan="4">AIR UMPAN</th>
                  <th class="head" colspan="4">AIR BOILER</th>
                  <th class="head" colspan="1">BLOWDOWN</th>
                  <th class="head" rowspan="3">Keterangan</th>
                  <th class="head" colspan="2">ms/cm (display alat)</th>
                </tr>
                <tr>
                  <th class="sub">Temp</th>
                  <th class="sub">DH</th>
                  <th class="sub" colspan="2">TDS</th>
                  <th class="sub">pH</th>
                  <th class="sub">Temp</th>
                  <th class="sub" colspan="2">TDS</th>
                  <th class="sub">Jumlah blowdown</th>
                  <th class="sub">Air Umpan</th>
                  <th class="sub">Air Boiler</th>
                </tr>
                <tr>
                  <th class="unit">C</th>
                  <th class="unit">std &lt; 1</th>
                  <th class="unit">ms/cm</th>
                  <th class="unit">ppm</th>
                  <th class="unit"></th>
                  <th class="unit">C</th>
                  <th class="unit">ms/cm</th>
                  <th class="unit">ppm</th>
                  <th class="unit"></th>
                  <th class="unit"></th>
                  <th class="unit"></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="cell-gray"><input type="text" name="temp_umpan_c" id="xTempUmpan" class="form-control form-control-sm num-only"></td>
                  <td class="cell-gray"><input type="text" name="dh_std_lt1" id="xDh" class="form-control form-control-sm num-only"></td>
                  <td class="cell-blue"><input type="text" name="tds_umpan_ms" id="xTdsUmpanMs" class="form-control form-control-sm" readonly></td>
                  <td class="cell-blue"><input type="text" id="xTdsUmpanPpm" class="form-control form-control-sm" readonly></td>

                  <td class="cell-gray"><input type="text" name="ph_boiler" id="xPhBoiler" class="form-control form-control-sm text-left" style="text-align:left;"></td>
                  <td class="cell-gray"><input type="text" name="temp_boiler_c" id="xTempBoiler" class="form-control form-control-sm num-only"></td>
                  <td class="cell-blue"><input type="text" name="tds_boiler_ms" id="xTdsBoilerMs" class="form-control form-control-sm" readonly></td>
                  <td class="cell-blue"><input type="text" id="xTdsBoilerPpm" class="form-control form-control-sm" readonly></td>

                  <td class="cell-gray"><input type="text" name="blowdown_jumlah" id="xBlowdownJumlah" class="form-control form-control-sm text-left" style="text-align:left;" placeholder="Contoh: 4x"></td>
                  <td class="cell-gray"><input type="text" name="keterangan" id="xKeterangan" class="form-control form-control-sm text-left" style="text-align:left;"></td>
                  <td class="cell-gray"><input type="text" id="xDisplayUmpan" class="form-control form-control-sm num-only"></td>
                  <td class="cell-gray"><input type="text" id="xDisplayBoiler" class="form-control form-control-sm num-only"></td>
                </tr>
              </tbody>
            </table>
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

  function parseNum(v){
    if(v===null||v===undefined) return NaN;
    v = String(v).trim().replace(/,/g,'');
    if(v==='') return NaN;
    var n = Number(v);
    return isNaN(n) ? NaN : n;
  }

  function fmt(v,d){
    var n = parseNum(v);
    return isNaN(n) ? '-' : n.toLocaleString('en-US',{minimumFractionDigits:d,maximumFractionDigits:d});
  }

  function fmtInput(v,d){
    var n = parseNum(v);
    return isNaN(n) ? '' : n.toLocaleString('en-US',{minimumFractionDigits:d,maximumFractionDigits:d});
  }

  function recalc(){
    var displayUmpan = parseNum($('#xDisplayUmpan').val());
    var displayBoiler = parseNum($('#xDisplayBoiler').val());

    if (isNaN(displayUmpan)) {
      $('#xTdsUmpanMs').val('');
      $('#xTdsUmpanPpm').val('');
    } else {
      var tdsUmpanMs = displayUmpan * 1000;
      $('#xTdsUmpanMs').val(fmtInput(tdsUmpanMs, 2));
      $('#xTdsUmpanPpm').val(fmtInput(tdsUmpanMs * 0.64, 2));
    }

    if (isNaN(displayBoiler)) {
      $('#xTdsBoilerMs').val('');
      $('#xTdsBoilerPpm').val('');
    } else {
      var tdsBoilerMs = displayBoiler * 1000;
      $('#xTdsBoilerMs').val(fmtInput(tdsBoilerMs, 2));
      $('#xTdsBoilerPpm').val(fmtInput(tdsBoilerMs * 0.64, 2));
    }
  }

  var table = $('#tableAirBoilerAlstom').DataTable({
    processing:true,
    serverSide:true,
    ordering:false,
    responsive:true,
    scrollX:true,
    ajax:{
      url:'air_boiler_serverside.php',
      type:'POST',
      data:function(d){
        d.start_date = $('#filterStartDate').val();
        d.end_date = $('#filterEndDate').val();
      }
    },
    columns:[
      {data:null,render:function(d,t,r,m){return m.row+m.settings._iDisplayStart+1;}},
      {data:'tanggal_formatted'},
      {data:'temp_umpan_c',render:function(d){return fmt(d,2);}},
      {data:'dh_std_lt1',render:function(d){return fmt(d,2);}},
      {data:'tds_umpan_ms',render:function(d){return fmt(d,2);}},
      {data:'tds_umpan_ppm',render:function(d){return fmt(d,2);}},
      {data:'ph_boiler',render:function(d){return d||'-';}},
      {data:'temp_boiler_c',render:function(d){return fmt(d,2);}},
      {data:'tds_boiler_ms',render:function(d){return fmt(d,2);}},
      {data:'tds_boiler_ppm',render:function(d){return fmt(d,2);}},
      {data:'blowdown_jumlah',render:function(d){return d||'-';}},
      {data:'keterangan',render:function(d){return d||'-';}},
      {data:'tds_umpan_display_ms',render:function(d){return fmt(d,2);}},
      {data:'tds_boiler_display_ms',render:function(d){return fmt(d,2);}},
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
  $('#btnResetFilter').on('click', function(){
    $('#filterStartDate,#filterEndDate').val('');
    table.search('').draw();
  });

  $('#btnTambahData').on('click', function(e){
    e.preventDefault();
    $('#formAirBoilerAlstom')[0].reset();
    $('#xId').val('');
    $('#xTanggal').prop('readonly', false).val(new Date().toISOString().slice(0,10));
    recalc();
    $('#modalAirBoilerAlstom').modal('show');
  });

  $(document).on('input','.num-only',function(){ this.value=this.value.replace(/[^0-9.,]/g,''); });
  $('#xDisplayUmpan,#xDisplayBoiler').on('input blur', recalc);

  $('#formAirBoilerAlstom').on('submit', function(e){
    e.preventDefault();
    $.ajax({
      url:'save_air_boiler.php',
      type:'POST',
      data:$(this).serialize(),
      dataType:'json',
      success:function(resp){
        if(resp&&resp.success){
          $('#modalAirBoilerAlstom').modal('hide');
          Swal.fire({icon:'success',title:'Sukses',text:resp.message||'Data tersimpan.'});
          table.ajax.reload(null,false);
        } else {
          Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal menyimpan data.'});
        }
      },
      error:function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan saat menyimpan data.'}); }
    });
  });

  $('#tableAirBoilerAlstom').on('click','.btn-detail',function(){
    window.location.href = 'view_air_boiler.php?id=' + $(this).data('id');
  });

  $('#tableAirBoilerAlstom').on('click','.btn-edit',function(){
    var tr = $(this).closest('tr');
    var row = table.row(tr);
    if (!row.data() && tr.hasClass('child')) row = table.row(tr.prev());
    var d = row.data();
    if (!d) return;

    $('#formAirBoilerAlstom')[0].reset();
    $('#xId').val(d.id || '');
    $('#xTanggal').prop('readonly', true).val(d.tanggal || '');
    $('#xTempUmpan').val(fmtInput(d.temp_umpan_c,2));
    $('#xDh').val(fmtInput(d.dh_std_lt1,2));
    $('#xPhBoiler').val(d.ph_boiler || '');
    $('#xTempBoiler').val(fmtInput(d.temp_boiler_c,2));
    $('#xBlowdownJumlah').val(d.blowdown_jumlah || '');
    $('#xKeterangan').val(d.keterangan || '');
    $('#xDisplayUmpan').val(fmtInput(d.tds_umpan_display_ms,2));
    $('#xDisplayBoiler').val(fmtInput(d.tds_boiler_display_ms,2));
    recalc();
    $('#modalAirBoilerAlstom').modal('show');
  });

  $('#tableAirBoilerAlstom').on('click','.btn-delete',function(){
    var id = $(this).data('id');
    Swal.fire({
      title:'Hapus data?',
      text:'Data ini akan dihapus permanen.',
      icon:'warning',
      showCancelButton:true,
      confirmButtonText:'Ya, Hapus'
    }).then(function(r){
      if(!r.isConfirmed) return;
      $.post('delete_air_boiler.php',{id:id},function(resp){
        if(resp&&resp.success){
          Swal.fire({icon:'success',title:'Sukses',text:resp.message||'Data berhasil dihapus.'});
          table.ajax.reload(null,false);
        } else {
          Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal menghapus data.'});
        }
      },'json').fail(function(){
        Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan saat menghapus data.'});
      });
    });
  });
});
</script>
