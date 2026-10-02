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
        <h1>METER PERBLE RANGE 1</h1>
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
          <h3 class="card-title"><i class="fas fa-list mr-1"></i> Data Meter Perble Range 1</h3>
          <div class="d-flex ml-auto" style="gap:8px;">
            <a href="report_perblerange1.php" class="btn btn-info btn-sm"><i class="fas fa-file-alt"></i> Detail Report</a>
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
          <table id="tablePbr1" class="table table-hover table-sm nowrap text-center" style="width:100%">
            <thead class="thead-light">
              <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Awal Meter (m3)</th>
                <th>Akhir Meter (m3)</th>
                <th>Total Meter (m3)</th>
                <th>Awal Debit (m3)</th>
                <th>Akhir Debit (m3)</th>
                <th>Total Debit Meter (m3)</th>
                <th>Operasional Meter (jam)</th>
                <th>Rata/Jam Meter (m3)</th>
                <th>Jumlah Debit</th>
                <th>Operasional Debit</th>
                <th>Rata Debit/Jam</th>
                <th>Ket MC</th>
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
  .pbr1-input-table{border-collapse:collapse;width:100%;min-width:780px}
  .pbr1-input-table th,.pbr1-input-table td{border:1px solid #c9ced4;padding:5px 6px;text-align:center;vertical-align:middle}
  .pbr1-input-table .head{background:#b7cae2;font-weight:700}
  .pbr1-input-table .sub{background:#f1dddd;font-weight:700}
  .pbr1-input-table .unit{background:#fafafa;font-size:11px;font-weight:700}
  .pbr1-input-table .cell-blue{background:#c4d5e9}
  .pbr1-input-table .cell-gray{background:#efefef}
  .pbr1-input-table .cell-yellow{background:#ffff00}
  .pbr1-input-table input,.pbr1-input-table select{height:32px;text-align:right}
  .mode-switch .btn{min-width:130px}
</style>

<div class="modal fade" id="modalPilihFormPbr1" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title">Pilih Form Input</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body text-center">
        <p class="mb-3">Silakan pilih jenis form yang akan diinput.</p>
        <div class="d-flex justify-content-center" style="gap:10px;">
          <button type="button" class="btn btn-primary btnPilihForm" data-mode="form1">Form 1</button>
          <button type="button" class="btn btn-warning btnPilihForm" data-mode="form2">Form 2</button>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalTambahPbr1" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title" id="titleModalPbr1">Input Meter Perble Range 1</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="formPbr1" autocomplete="off">
        <input type="hidden" name="id" id="xId">
        <input type="hidden" name="form_mode" id="xFormMode" value="form1">
        <div class="modal-body">
          <div class="form-row mb-2">
            <div class="form-group col-md-4 mb-2">
              <label class="font-weight-bold">Tanggal (TGL)</label>
              <input type="date" name="tanggal" id="xTanggal" class="form-control" required>
            </div>
            <div class="form-group col-md-8 mb-2">
              <label class="font-weight-bold">Keterangan</label>
              <input type="text" name="ket" id="xKet" class="form-control" placeholder="Opsional">
            </div>
          </div>

          <div class="d-flex mode-switch mb-2" style="gap:8px;">
            <button type="button" class="btn btn-sm btn-outline-primary" id="btnMode1">Form 1 (Kolom Kiri)</button>
            <button type="button" class="btn btn-sm btn-outline-warning" id="btnMode2">Form 2 (Kolom Kanan)</button>
          </div>

          <div class="table-responsive" id="sectionForm1">
            <table class="pbr1-input-table">
              <thead>
                <tr>
                  <th class="head" colspan="5">FORM 1 - METER PERBLE RANGE 1 (KOLOM KIRI)</th>
                </tr>
                <tr>
                  <th class="sub">Awal</th>
                  <th class="sub">Akhir</th>
                  <th class="sub">Total Pemakaian</th>
                  <th class="sub">Operasional MC</th>
                  <th class="sub">Pemakaian Rata/Jam</th>
                </tr>
                <tr>
                  <th class="unit">m3</th>
                  <th class="unit">m3</th>
                  <th class="unit">m3</th>
                  <th class="unit">jam</th>
                  <th class="unit">m3</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="cell-gray"><input type="text" name="meter_awal_m3" id="xAwal" class="form-control form-control-sm num-only mode1-field"></td>
                  <td class="cell-gray"><input type="text" name="meter_akhir_m3" id="xAkhir" class="form-control form-control-sm num-only mode1-field"></td>
                  <td class="cell-blue"><input type="text" name="total_pemakaian_m3" id="xTotal" class="form-control form-control-sm mode1-field" readonly></td>
                  <td class="cell-gray"><input type="text" name="operasional_mc_pbr1_jam" id="xOps" class="form-control form-control-sm num-only mode1-field"></td>
                  <td class="cell-gray"><input type="text" name="pemakaian_rata_per_jam_m3" id="xRata" class="form-control form-control-sm mode1-field" readonly></td>
                </tr>
              </tbody>
            </table>
            <div class="text-right mt-2">
              <button type="button" class="btn btn-outline-primary btn-sm" id="btnAmbilPrevForm1">
                <i class="fas fa-history mr-1"></i> Ambil Awal = Akhir Tgl Sebelumnya
              </button>
            </div>
          </div>

          <div class="table-responsive" id="sectionForm2" style="display:none;">
            <table class="pbr1-input-table">
              <thead>
                <tr>
                  <th class="head" colspan="7">FORM 2 - METER PERBLE RANGE 1 (KOLOM KANAN / DEBIT)</th>
                </tr>
                <tr>
                  <th class="sub">Awal</th>
                  <th class="sub">Akhir</th>
                  <th class="sub">Total Pemakaian</th>
                  <th class="sub">Jumlah Debit</th>
                  <th class="sub">Operasional Debit</th>
                  <th class="sub">Pemakaian Rata/Jam</th>
                  <th class="sub">KET MC Yang Jalan</th>
                </tr>
                <tr>
                  <th class="unit">m3</th>
                  <th class="unit">m3</th>
                  <th class="unit">m3</th>
                  <th class="unit">m3</th>
                  <th class="unit">jam</th>
                  <th class="unit">m3</th>
                  <th class="unit">PBR1/PBR2</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="cell-gray"><input type="text" name="meter_awal_debit_m3" id="xAwalDebitMeter" class="form-control form-control-sm num-only mode2-field"></td>
                  <td class="cell-gray"><input type="text" name="meter_akhir_debit_m3" id="xAkhirDebitMeter" class="form-control form-control-sm num-only mode2-field"></td>
                  <td class="cell-blue"><input type="text" name="total_pemakaian_debit_m3" id="xTotalDebitMeter" class="form-control form-control-sm mode2-field" readonly></td>
                  <td class="cell-yellow"><input type="text" name="jumlah_debit_m3" id="xDebit" class="form-control form-control-sm mode2-field" readonly></td>
                  <td class="cell-yellow"><input type="text" name="operasional_mc_pbr1_debit_jam" id="xOpsDebit" class="form-control form-control-sm num-only mode2-field"></td>
                  <td class="cell-yellow"><input type="text" name="pemakaian_rata_per_jam_debit_m3" id="xRataDebit" class="form-control form-control-sm mode2-field" readonly></td>
                  <td class="cell-gray">
                    <select name="ket_mc_yang_jalan" id="xKetMc" class="form-control mode2-field" style="text-align:left;">
                      <option value="">-Pilih-</option>
                      <option value="PBR1">PBR1</option>
                      <option value="PBR2">PBR2</option>
                    </select>
                  </td>
                </tr>
              </tbody>
            </table>
            <div class="text-right mt-2">
              <button type="button" class="btn btn-outline-warning btn-sm" id="btnAmbilPrevForm2">
                <i class="fas fa-history mr-1"></i> Ambil Awal = Akhir Tgl Sebelumnya
              </button>
            </div>
          </div>

          <div class="form-row mt-2">
            <div class="col-12">
              <div class="alert alert-info mb-0 py-2 px-3">
                Rumus Form 1: Total = Akhir - Awal, Rata/Jam = Total / Operasional MC. Rumus Form 2: Total = Akhir - Awal, Rata/Jam = Jumlah Debit / Operasional Debit.
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

<div class="modal fade" id="modalTambahCatatanPbr1" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title">Tambah Catatan Perble Range 1</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="formCatatanPbr1" autocomplete="off">
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
  function setFormMode(mode){
    mode = (mode === 'form2') ? 'form2' : 'form1';
    $('#xFormMode').val(mode);
    $('#sectionForm1').toggle(mode === 'form1');
    $('#sectionForm2').toggle(mode === 'form2');
    $('.mode1-field').prop('disabled', mode !== 'form1');
    $('.mode2-field').prop('disabled', mode !== 'form2');
    $('#btnMode1').toggleClass('btn-primary', mode === 'form1').toggleClass('btn-outline-primary', mode !== 'form1');
    $('#btnMode2').toggleClass('btn-warning', mode === 'form2').toggleClass('btn-outline-warning', mode !== 'form2');
    $('#titleModalPbr1').text('Input Meter Perble Range 1 - ' + (mode === 'form1' ? 'Form 1' : 'Form 2'));
  }
  function recalc(){
    var awal = parseNum($('#xAwal').val()); if (isNaN(awal)) awal = 0;
    var akhir = parseNum($('#xAkhir').val()); if (isNaN(akhir)) akhir = 0;
    var awalDebitMeter = parseNum($('#xAwalDebitMeter').val()); if (isNaN(awalDebitMeter)) awalDebitMeter = 0;
    var akhirDebitMeter = parseNum($('#xAkhirDebitMeter').val()); if (isNaN(akhirDebitMeter)) akhirDebitMeter = 0;
    var ops = parseNum($('#xOps').val()); if (isNaN(ops) || ops <= 0) ops = 0;
    var debit = 0;
    var opsDebit = parseNum($('#xOpsDebit').val()); if (isNaN(opsDebit) || opsDebit <= 0) opsDebit = 0;

    var total = akhir - awal; if (total < 0) total = 0;
    var totalDebitMeter = akhirDebitMeter - awalDebitMeter; if (totalDebitMeter < 0) totalDebitMeter = 0;
    var rata = ops > 0 ? (total / ops) : 0;
    var rataDebit = opsDebit > 0 ? (totalDebitMeter / opsDebit) : 0;

    $('#xTotal').val(fmtInput(total,2));
    $('#xTotalDebitMeter').val(fmtInput(totalDebitMeter,2));
    $('#xRata').val(fmtInput(rata,2));
    $('#xDebit').val(fmtInput(totalDebitMeter,2));
    $('#xRataDebit').val(fmtInput(rataDebit,2));
  }
  function fetchPrev(mode){
    var tanggal = $('#xTanggal').val();
    if (!tanggal) {
      Swal.fire({icon:'warning',title:'Perhatian',text:'Pilih tanggal terlebih dahulu.'});
      return;
    }
    $.post('get_prev_perblerange1.php',{tanggal:tanggal},function(resp){
      if(!resp || !resp.success){
        Swal.fire({icon:'warning',title:'Info',text:(resp&&resp.message)?resp.message:'Data tanggal sebelumnya tidak ditemukan.'});
        return;
      }
      var d = resp.data || {};
      var usedDate = d.tanggal || '';
      if (mode === 'form1') {
        if (d.meter_akhir_m3 === null || d.meter_akhir_m3 === undefined || d.meter_akhir_m3 === '') {
          Swal.fire({icon:'warning',title:'Info',text:'Nilai meter akhir sebelumnya kosong.'});
          return;
        }
        $('#xAwal').val(fmtInput(d.meter_akhir_m3,2));
      } else {
        if (d.meter_akhir_debit_m3 === null || d.meter_akhir_debit_m3 === undefined || d.meter_akhir_debit_m3 === '') {
          Swal.fire({icon:'warning',title:'Info',text:'Nilai meter akhir debit sebelumnya kosong.'});
          return;
        }
        $('#xAwalDebitMeter').val(fmtInput(d.meter_akhir_debit_m3,2));
      }
      recalc();
      if (usedDate) {
        Swal.fire({icon:'success',title:'Berhasil',text:'Diambil dari tanggal '+usedDate+'.'});
      }
    },'json').fail(function(){
      Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan.'});
    });
  }

  var table = $('#tablePbr1').DataTable({
    processing:true, serverSide:true, ordering:false, responsive:true,
    ajax:{ url:'perblerange1_serverside.php', type:'POST', data:function(d){ d.start_date=$('#filterStartDate').val(); d.end_date=$('#filterEndDate').val(); }},
    columns:[
      {data:null,render:function(d,t,r,m){return m.row+m.settings._iDisplayStart+1;}},
      {data:'tanggal_formatted'},
      {data:'meter_awal_m3',render:function(d){return fmt(d,2)}},
      {data:'meter_akhir_m3',render:function(d){return fmt(d,2)}},
      {data:'total_pemakaian_m3',render:function(d){return fmt(d,2)}},
      {data:'meter_awal_debit_m3',render:function(d){return fmt(d,2)}},
      {data:'meter_akhir_debit_m3',render:function(d){return fmt(d,2)}},
      {data:'total_pemakaian_debit_m3',render:function(d){return fmt(d,2)}},
      {data:'operasional_mc_pbr1_jam',render:function(d){return fmt(d,2)}},
      {data:'pemakaian_rata_per_jam_m3',render:function(d){return fmt(d,2)}},
      {data:'jumlah_debit_m3',render:function(d){return fmt(d,2)}},
      {data:'operasional_mc_pbr1_debit_jam',render:function(d){return fmt(d,2)}},
      {data:'pemakaian_rata_per_jam_debit_m3',render:function(d){return fmt(d,2)}},
      {data:'ket_mc_yang_jalan',render:function(d){return d||'-';}},
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

  $('#btnMode1').on('click', function(){ setFormMode('form1'); });
  $('#btnMode2').on('click', function(){ setFormMode('form2'); });
  $('#btnAmbilPrevForm1').on('click', function(){ fetchPrev('form1'); });
  $('#btnAmbilPrevForm2').on('click', function(){ fetchPrev('form2'); });

  $('#btnTambahData').on('click', function(e){
    e.preventDefault();
    $('#modalPilihFormPbr1').modal('show');
  });
  $('.btnPilihForm').on('click', function(){
    var mode = $(this).data('mode') === 'form2' ? 'form2' : 'form1';
    $('#modalPilihFormPbr1').modal('hide');
    $('#formPbr1')[0].reset();
    $('#xId').val('');
    $('#xTanggal').prop('readonly', false).val(new Date().toISOString().slice(0,10));
    setFormMode(mode);
    recalc();
    $('#modalTambahPbr1').modal('show');
  });
  $('#btnTambahCatatan').on('click', function(e){
    e.preventDefault();
    $('#formCatatanPbr1')[0].reset();
    $('#xCatatanTanggal').val(new Date().toISOString().slice(0,10));
    $('#modalTambahCatatanPbr1').modal('show');
  });

  $(document).on('input','.num-only',function(){ this.value=this.value.replace(/[^0-9.,]/g,''); });
  $('#formPbr1 .num-only').on('input blur', recalc);

  $('#formPbr1').on('submit', function(e){
    e.preventDefault();
    var mode = $('#xFormMode').val();
    if (mode === 'form1') {
      if ($('#xTanggal').val() === '' || $('#xAwal').val().trim() === '' || $('#xAkhir').val().trim() === '') {
        Swal.fire({icon:'warning',title:'Validasi',text:'Form 1: tanggal, meter awal, dan meter akhir wajib diisi.'});
        return;
      }
    } else {
      if ($('#xTanggal').val() === '' || $('#xAwalDebitMeter').val().trim() === '' || $('#xAkhirDebitMeter').val().trim() === '') {
        Swal.fire({icon:'warning',title:'Validasi',text:'Form 2: tanggal, meter awal, dan meter akhir wajib diisi.'});
        return;
      }
    }

    $.ajax({
      url:'save_perblerange1.php',
      type:'POST',
      data:$(this).serialize(),
      dataType:'json',
      success:function(resp){
        if(resp&&resp.success){
          $('#modalTambahPbr1').modal('hide');
          Swal.fire({icon:'success',title:'Sukses',text:resp.message||'Tersimpan.'});
          table.ajax.reload(null,false);
        } else {
          Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal menyimpan.'});
        }
      },
      error:function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan.'}); }
    });
  });

  $('#formCatatanPbr1').on('submit', function(e){
    e.preventDefault();
    $.ajax({
      url:'save_catatan_perblerange1.php',
      type:'POST',
      data:$(this).serialize(),
      dataType:'json',
      success:function(resp){
        if(resp&&resp.success){
          $('#modalTambahCatatanPbr1').modal('hide');
          Swal.fire({icon:'success',title:'Sukses',text:resp.message||'Catatan tersimpan.'});
          table.ajax.reload(null,false);
        } else {
          Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal menyimpan catatan.'});
        }
      },
      error:function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan.'}); }
    });
  });

  $('#tablePbr1').on('click','.btn-detail',function(){ window.location.href='view_perblerange1.php?id='+$(this).data('id'); });
  $('#tablePbr1').on('click','.btn-edit',function(){
    var tr = $(this).closest('tr');
    var row = table.row(tr);
    if (!row.data() && tr.hasClass('child')) row = table.row(tr.prev());
    var d = row.data();
    if (!d) return;

    $('#formPbr1')[0].reset();
    $('#xId').val(d.id || '');
    $('#xTanggal').prop('readonly', true).val(d.tanggal || '');
    $('#xAwal').val(fmtInput(d.meter_awal_m3,2));
    $('#xAkhir').val(fmtInput(d.meter_akhir_m3,2));
    $('#xAwalDebitMeter').val(fmtInput(d.meter_awal_debit_m3,2));
    $('#xAkhirDebitMeter').val(fmtInput(d.meter_akhir_debit_m3,2));
    $('#xOps').val(fmtInput(d.operasional_mc_pbr1_jam,2));
    $('#xDebit').val(fmtInput(d.jumlah_debit_m3,2));
    $('#xOpsDebit').val(fmtInput(d.operasional_mc_pbr1_debit_jam,2));
    $('#xKetMc').val(d.ket_mc_yang_jalan || '');
    $('#xKet').val(d.ket || '');
    var mode = 'form1';
    if (parseNum(d.meter_awal_debit_m3) > 0 || parseNum(d.meter_akhir_debit_m3) > 0 || parseNum(d.jumlah_debit_m3) > 0 || (d.ket_mc_yang_jalan || '') !== '') {
      mode = 'form2';
    }
    setFormMode(mode);
    recalc();
    $('#modalTambahPbr1').modal('show');
  });
  $('#tablePbr1').on('click','.btn-delete',function(){
    var id=$(this).data('id');
    Swal.fire({title:'Hapus data?',text:'Data ini akan dihapus permanen.',icon:'warning',showCancelButton:true,confirmButtonText:'Ya, Hapus'})
      .then(function(r){
        if(!r.isConfirmed) return;
        $.post('delete_perblerange1.php',{id:id},function(resp){
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
