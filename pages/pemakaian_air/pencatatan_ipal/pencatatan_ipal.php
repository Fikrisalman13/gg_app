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
        <h1>PENCATATAN IPAL</h1>
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
          <h3 class="card-title"><i class="fas fa-list mr-1"></i> Data Pencatatan SV30, pH, Sludge IPAL</h3>
          <div class="d-flex ml-auto" style="gap:8px;">
            <a href="report_pencatatan_ipal.php" class="btn btn-info btn-sm"><i class="fas fa-file-alt"></i> Detail Report</a>
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
            <div class="col-md-2"><label>Shift</label>
              <select id="filterShift" class="form-control">
                <option value="">Semua</option>
                <option value="P">P</option>
                <option value="S">S</option>
                <option value="M">M</option>
              </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
              <button type="button" class="btn btn-secondary btn-sm" id="btnResetFilter"><i class="fas fa-undo"></i> Reset Filter</button>
            </div>
          </div>

          <table id="tableIpal" class="table table-hover table-sm nowrap text-center" style="width:100%">
            <thead class="thead-light">
              <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Shift</th>
                <th>SV30 A1 (%)</th>
                <th>SV30 A2 (%)</th>
                <th>SV30 A3 (%)</th>
                <th>SV30 A4 (%)</th>
                <th>pH Equal</th>
                <th>pH Akhir</th>
                <th>Dewatering Bawah</th>
                <th>Dewat Atas & Sinci1</th>
                <th>Sinci2</th>
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

<div class="modal fade" id="modalTambahIpal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title">Input Pencatatan IPAL</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="formIpal" autocomplete="off">
        <div class="modal-body">
          <div class="form-row mb-2">
            <div class="form-group col-md-3 mb-2">
              <label class="font-weight-bold">Tanggal (TGL)</label>
              <input type="date" name="tanggal" id="xTanggal" class="form-control" required>
            </div>
          </div>

          <div class="table-responsive">
            <table class="ipal-input-table">
              <thead>
                <tr class="head">
                  <th rowspan="2">SHIFT</th>
                  <th colspan="4">SV30 AERASI (%)</th>
                  <th colspan="2">pH</th>
                  <th rowspan="2">DEWATERING BAWAH<br><small>Per Day</small></th>
                  <th rowspan="2">DEWATERING ATAS &amp; SINCI 1<br><small>Per Day/Ton</small></th>
                  <th rowspan="2">SINCI 2<br><small>Per Day/Ton</small></th>
                  <th rowspan="2">KET</th>
                </tr>
                <tr class="sub">
                  <th>AERASI 1</th>
                  <th>AERASI 2</th>
                  <th>AERASI 3</th>
                  <th>AERASI 4</th>
                  <th>Equal</th>
                  <th>Akhir</th>
                </tr>
              </thead>
              <tbody>
                <tr class="shift-row" data-shift="P">
                  <td class="shift-cell">P<input type="hidden" id="xId_P"></td>
                  <td><input type="text" id="xSv1_P" class="form-control form-control-sm num-only"></td>
                  <td><input type="text" id="xSv2_P" class="form-control form-control-sm num-only"></td>
                  <td><input type="text" id="xSv3_P" class="form-control form-control-sm num-only"></td>
                  <td><input type="text" id="xSv4_P" class="form-control form-control-sm num-only"></td>
                  <td><input type="text" id="xPhEqual_P" class="form-control form-control-sm num-only"></td>
                  <td><input type="text" id="xPhAkhir_P" class="form-control form-control-sm num-only"></td>
                  <td><input type="text" id="xDebBawah_P" class="form-control form-control-sm num-only"></td>
                  <td><input type="text" id="xDebAtas_P" class="form-control form-control-sm num-only"></td>
                  <td><input type="text" id="xSinci2_P" class="form-control form-control-sm num-only"></td>
                  <td><input type="text" id="xKet_P" class="form-control form-control-sm text-left" style="text-align:left;"></td>
                </tr>
                <tr class="shift-row" data-shift="S">
                  <td class="shift-cell">S<input type="hidden" id="xId_S"></td>
                  <td><input type="text" id="xSv1_S" class="form-control form-control-sm num-only"></td>
                  <td><input type="text" id="xSv2_S" class="form-control form-control-sm num-only"></td>
                  <td><input type="text" id="xSv3_S" class="form-control form-control-sm num-only"></td>
                  <td><input type="text" id="xSv4_S" class="form-control form-control-sm num-only"></td>
                  <td><input type="text" id="xPhEqual_S" class="form-control form-control-sm num-only"></td>
                  <td><input type="text" id="xPhAkhir_S" class="form-control form-control-sm num-only"></td>
                  <td><input type="text" id="xDebBawah_S" class="form-control form-control-sm num-only"></td>
                  <td><input type="text" id="xDebAtas_S" class="form-control form-control-sm num-only"></td>
                  <td><input type="text" id="xSinci2_S" class="form-control form-control-sm num-only"></td>
                  <td><input type="text" id="xKet_S" class="form-control form-control-sm text-left" style="text-align:left;"></td>
                </tr>
                <tr class="shift-row" data-shift="M">
                  <td class="shift-cell">M<input type="hidden" id="xId_M"></td>
                  <td><input type="text" id="xSv1_M" class="form-control form-control-sm num-only"></td>
                  <td><input type="text" id="xSv2_M" class="form-control form-control-sm num-only"></td>
                  <td><input type="text" id="xSv3_M" class="form-control form-control-sm num-only"></td>
                  <td><input type="text" id="xSv4_M" class="form-control form-control-sm num-only"></td>
                  <td><input type="text" id="xPhEqual_M" class="form-control form-control-sm num-only"></td>
                  <td><input type="text" id="xPhAkhir_M" class="form-control form-control-sm num-only"></td>
                  <td><input type="text" id="xDebBawah_M" class="form-control form-control-sm num-only"></td>
                  <td><input type="text" id="xDebAtas_M" class="form-control form-control-sm num-only"></td>
                  <td><input type="text" id="xSinci2_M" class="form-control form-control-sm num-only"></td>
                  <td><input type="text" id="xKet_M" class="form-control form-control-sm text-left" style="text-align:left;"></td>
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

<style>
  .ipal-input-table{border-collapse:collapse;width:100%;min-width:1200px}
  .ipal-input-table th,.ipal-input-table td{border:1px solid #c9ced4;padding:5px 6px;text-align:center;vertical-align:middle}
  .ipal-input-table .head{background:#dfe8d2;font-weight:700}
  .ipal-input-table .sub{background:#efefef;font-weight:700}
  .ipal-input-table .shift-cell{background:#f3f0d7;font-weight:700;width:60px}
  .ipal-input-table input{height:30px;text-align:right}
  .ipal-input-table .text-left{ text-align:left; }
</style>

<div class="modal fade" id="modalTambahCatatanIpal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title">Tambah Catatan Pencatatan IPAL</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="formCatatanIpal" autocomplete="off">
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
  var editModeShift = '';
  function parseNum(v){ if(v===null||v===undefined) return NaN; v=String(v).trim().replace(/,/g,''); if(v==='') return NaN; var n=Number(v); return isNaN(n)?NaN:n; }
  function fmt(v,d){ var n=parseNum(v); return isNaN(n)?'-':n.toLocaleString('en-US',{minimumFractionDigits:d,maximumFractionDigits:d}); }
  function fmtInput(v,d){ var n=parseNum(v); return isNaN(n)?'':n.toLocaleString('en-US',{minimumFractionDigits:d,maximumFractionDigits:d}); }
  var shiftFields = ['Sv1','Sv2','Sv3','Sv4','PhEqual','PhAkhir','DebBawah','DebAtas','Sinci2','Ket'];

  function setRowEnabled(shift, enabled){
    shiftFields.forEach(function(f){ $('#x'+f+'_'+shift).prop('disabled', !enabled); });
  }
  function clearRow(shift){
    shiftFields.forEach(function(f){ $('#x'+f+'_'+shift).val(''); });
    $('#xId_'+shift).val('');
  }
  function rowHasValue(shift){
    for (var i=0;i<shiftFields.length;i++){
      var v = $('#x'+shiftFields[i]+'_'+shift).val();
      if (v !== null && v !== undefined && String(v).trim() !== '') return true;
    }
    return false;
  }
  function rowData(shift){
    return {
      id: $('#xId_'+shift).val(),
      shift_kode: shift,
      sv30_aerasi1_pct: $('#xSv1_'+shift).val(),
      sv30_aerasi2_pct: $('#xSv2_'+shift).val(),
      sv30_aerasi3_pct: $('#xSv3_'+shift).val(),
      sv30_aerasi4_pct: $('#xSv4_'+shift).val(),
      ph_equal: $('#xPhEqual_'+shift).val(),
      ph_akhir: $('#xPhAkhir_'+shift).val(),
      dewatering_bawah_per_day: $('#xDebBawah_'+shift).val(),
      dewatering_atas_sinci1_per_day_ton: $('#xDebAtas_'+shift).val(),
      sinci2_per_day_ton: $('#xSinci2_'+shift).val(),
      ket: $('#xKet_'+shift).val()
    };
  }

  var table = $('#tableIpal').DataTable({
    processing:true, serverSide:true, ordering:false, responsive:true,
    ajax:{ url:'pencatatan_ipal_serverside.php', type:'POST', data:function(d){ d.start_date=$('#filterStartDate').val(); d.end_date=$('#filterEndDate').val(); d.shift=$('#filterShift').val(); }},
    columns:[
      {data:null,render:function(d,t,r,m){return m.row+m.settings._iDisplayStart+1;}},
      {data:'tanggal_formatted'},
      {data:'shift_kode',render:function(d){return d||'-';}},
      {data:'sv30_aerasi1_pct',render:function(d){return fmt(d,2)}},
      {data:'sv30_aerasi2_pct',render:function(d){return fmt(d,2)}},
      {data:'sv30_aerasi3_pct',render:function(d){return fmt(d,2)}},
      {data:'sv30_aerasi4_pct',render:function(d){return fmt(d,2)}},
      {data:'ph_equal',render:function(d){return fmt(d,2)}},
      {data:'ph_akhir',render:function(d){return fmt(d,2)}},
      {data:'dewatering_bawah_per_day',render:function(d){return fmt(d,2)}},
      {data:'dewatering_atas_sinci1_per_day_ton',render:function(d){return fmt(d,2)}},
      {data:'sinci2_per_day_ton',render:function(d){return fmt(d,2)}},
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

  $('#filterStartDate,#filterEndDate,#filterShift').on('change', function(){ table.ajax.reload(); });
  $('#btnResetFilter').on('click', function(){ $('#filterStartDate,#filterEndDate,#filterShift').val(''); table.search('').draw(); });

  $('#btnTambahData').on('click', function(e){
    e.preventDefault();
    $('#formIpal')[0].reset();
    editModeShift = '';
    ['P','S','M'].forEach(function(s){ clearRow(s); setRowEnabled(s,true); });
    $('#xTanggal').prop('readonly', false).val(new Date().toISOString().slice(0,10));
    $('#modalTambahIpal').modal('show');
  });
  $('#btnTambahCatatan').on('click', function(e){
    e.preventDefault();
    $('#formCatatanIpal')[0].reset();
    $('#xCatatanTanggal').val(new Date().toISOString().slice(0,10));
    $('#modalTambahCatatanIpal').modal('show');
  });

  $(document).on('input','.num-only',function(){ this.value=this.value.replace(/[^0-9.,]/g,''); });

  $('#formIpal').on('submit', function(e){
    e.preventDefault();
    var tanggal = $('#xTanggal').val();
    if (tanggal==='') {
      Swal.fire({icon:'warning',title:'Validasi',text:'Tanggal wajib diisi.'});
      return;
    }

    var shifts = editModeShift ? [editModeShift] : ['P','S','M'];
    var tasks = [];

    shifts.forEach(function(s){
      if (editModeShift || rowHasValue(s)) {
        var payload = rowData(s);
        payload.tanggal = tanggal;
        tasks.push($.ajax({
          url:'save_pencatatan_ipal.php',
          type:'POST',
          dataType:'json',
          data: payload
        }));
      }
    });

    if (tasks.length === 0) {
      Swal.fire({icon:'warning',title:'Data kosong',text:'Isi minimal satu shift sebelum menyimpan.'});
      return;
    }

    $.when.apply($, tasks).done(function(){
      var args = Array.prototype.slice.call(arguments);
      var responses = args.map(function(a){ return Array.isArray(a) ? a[0] : a; });
      var failResp = responses.find(function(r){ return !r || !r.success; });
      if (failResp) {
        Swal.fire({icon:'error',title:'Gagal',text:(failResp && failResp.message)?failResp.message:'Gagal menyimpan.'});
        return;
      }
      $('#modalTambahIpal').modal('hide');
      Swal.fire({icon:'success',title:'Sukses',text:'Data IPAL berhasil disimpan.'});
      table.ajax.reload(null,false);
    }).fail(function(){
      Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan.'});
    });
  });

  $('#formCatatanIpal').on('submit', function(e){
    e.preventDefault();
    $.ajax({
      url:'save_catatan_pencatatan_ipal.php',
      type:'POST',
      data:$(this).serialize(),
      dataType:'json',
      success:function(resp){
        if(resp&&resp.success){
          $('#modalTambahCatatanIpal').modal('hide');
          Swal.fire({icon:'success',title:'Sukses',text:resp.message||'Catatan tersimpan.'});
        } else {
          Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal menyimpan catatan.'});
        }
      },
      error:function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan.'}); }
    });
  });

  $('#tableIpal').on('click','.btn-detail',function(){
    var tr = $(this).closest('tr');
    var row = table.row(tr);
    if (!row.data() && tr.hasClass('child')) row = table.row(tr.prev());
    var d = row.data();
    if (!d) return;
    var id = d.id || '';
    var tanggal = d.tanggal || '';
    var shift = d.shift_kode || '';
    var url = 'view_pencatatan_ipal.php?id=' + encodeURIComponent(id);
    if (tanggal !== '') url += '&tanggal=' + encodeURIComponent(tanggal);
    if (shift !== '') url += '&shift=' + encodeURIComponent(shift);
    window.location.href = url;
  });
  $('#tableIpal').on('click','.btn-edit',function(){
    var tr = $(this).closest('tr');
    var row = table.row(tr);
    if (!row.data() && tr.hasClass('child')) row = table.row(tr.prev());
    var d = row.data();
    if (!d) return;
    $('#formIpal')[0].reset();
    ['P','S','M'].forEach(function(s){ clearRow(s); setRowEnabled(s,true); });
    editModeShift = (d.shift_kode || '').toUpperCase();
    $('#xTanggal').prop('readonly', true).val(d.tanggal || '');
    if (editModeShift) {
      $('#xId_'+editModeShift).val(d.id || '');
      $('#xSv1_'+editModeShift).val(fmtInput(d.sv30_aerasi1_pct,2));
      $('#xSv2_'+editModeShift).val(fmtInput(d.sv30_aerasi2_pct,2));
      $('#xSv3_'+editModeShift).val(fmtInput(d.sv30_aerasi3_pct,2));
      $('#xSv4_'+editModeShift).val(fmtInput(d.sv30_aerasi4_pct,2));
      $('#xPhEqual_'+editModeShift).val(fmtInput(d.ph_equal,2));
      $('#xPhAkhir_'+editModeShift).val(fmtInput(d.ph_akhir,2));
      $('#xDebBawah_'+editModeShift).val(fmtInput(d.dewatering_bawah_per_day,2));
      $('#xDebAtas_'+editModeShift).val(fmtInput(d.dewatering_atas_sinci1_per_day_ton,2));
      $('#xSinci2_'+editModeShift).val(fmtInput(d.sinci2_per_day_ton,2));
      $('#xKet_'+editModeShift).val(d.ket || '');
      ['P','S','M'].forEach(function(s){ if (s !== editModeShift) setRowEnabled(s,false); });
    }
    $('#modalTambahIpal').modal('show');
  });
  $('#tableIpal').on('click','.btn-delete',function(){
    var id=$(this).data('id');
    Swal.fire({title:'Hapus data?',text:'Data ini akan dihapus permanen.',icon:'warning',showCancelButton:true,confirmButtonText:'Ya, Hapus'})
      .then(function(r){
        if(!r.isConfirmed) return;
        $.post('delete_pencatatan_ipal.php',{id:id},function(resp){
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
