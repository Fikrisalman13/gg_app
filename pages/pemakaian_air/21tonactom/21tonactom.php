<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
$menuId = 230; // TODO: ganti dengan MenuId Washing2 di database
$permissions = getPermissions($conn, $_SESSION['GroupId'], $menuId);
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
        <h1>Pemakaian Air 21 Ton Actom</h1>
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
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title"><i class="fas fa-list mr-1"></i> List Data 21 Ton Actom</h3>
                    <a href="report_21tonactom.php" class="btn btn-info btn-sm float-right ml-2"><i class="fas fa-file-alt"></i> Detail Report</a>
                    <?php if (!empty($permissions['CanAdd']) && (int)$permissions['CanAdd'] === 1): ?>
                        <a href="#" class="btn btn-warning btn-sm float-right ml-2" id="btnTambahCatatan"><i class="fas fa-sticky-note"></i> Tambah Catatan</a>
                        <a href="#" class="btn btn-success btn-sm float-right" id="btnTambahData"><i class="fas fa-plus"></i> Tambah Data</a>
                    <?php endif; ?>
                </div>
                <div class="card-body table-responsive">
                    <div class="row mb-3">
                        <div class="col-md-3"><label for="filterStartDate">Dari Tanggal</label><input type="date" id="filterStartDate" class="form-control"></div>
                        <div class="col-md-3"><label for="filterEndDate">Sampai Tanggal</label><input type="date" id="filterEndDate" class="form-control"></div>
                        <div class="col-md-3">
                            <label for="filterParameter">Parameter</label>
                            <select id="filterParameter" class="form-control">
                                <option value="">Semua</option>
                                <option value="Air Boiler 21 Ton Actom">Air Boiler 21 Ton Actom</option>
                                <option value="Steam Boiler 21 Ton Actom">Steam Boiler 21 Ton Actom</option>
                                <option value="Air Analog 21 Ton Actom">Air Analog 21 Ton Actom</option>
                                <option value="Analog Steam 21 Ton Actom">Analog Steam 21 Ton Actom</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end"><button type="button" class="btn btn-secondary btn-sm" id="btnResetFilter"><i class="fas fa-undo"></i> Reset Filter</button></div>
                    </div>
                    <table id="tonactomTable" class="table table-hover table-sm nowrap text-center" style="width:100%">
                        <thead class="thead-light text-center">
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Parameter</th>
                                <th>Awal</th>
                                <th>Akhir</th>
                                <th>Total Pemakaian</th>
                                <th>Pemakaian Rata2 / Jam</th>
                                <th>Creat By</th>
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

<div class="modal fade" id="modalForm21TonActom" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title">Input Meter Air & Steam 21 Ton Actom</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="form21TonActom" autocomplete="off">
        <div class="modal-body">
          <div class="form-row mb-2">
            <div class="form-group col-md-3 mb-2">
              <label class="font-weight-bold">Tanggal (TGL)</label>
              <input type="date" name="tanggal" id="w2Tanggal" class="form-control" required>
            </div>
            <div class="form-group col-md-9 mb-2">
              <div class="alert alert-light border mb-0 py-2 px-3" style="height:38px;display:flex;align-items:center;">
                Gunakan isi otomatis untuk mengisi nilai awal dari nilai akhir hari sebelumnya.
              </div>
            </div>
          </div>

          <div class="form-row mb-2">
            <div class="col-md-3 mb-2">
              <button type="button" class="btn btn-outline-primary btn-sm w-100" id="btnIsiOtomatisWatt"><i class="fas fa-magic mr-1"></i> Isi Otomatis Air Boiler</button>
            </div>
            <div class="col-md-3 mb-2">
              <button type="button" class="btn btn-outline-info btn-sm w-100" id="btnIsiOtomatisSteam"><i class="fas fa-magic mr-1"></i> Isi Otomatis Steam Boiler</button>
            </div>
            <div class="col-md-3 mb-2">
              <button type="button" class="btn btn-outline-success btn-sm w-100" id="btnIsiOtomatisWater"><i class="fas fa-magic mr-1"></i> Isi Otomatis Air Analog</button>
            </div>
            <div class="col-md-3 mb-2">
              <button type="button" class="btn btn-outline-warning btn-sm w-100" id="btnIsiOtomatisAnalogSteam"><i class="fas fa-magic mr-1"></i> Isi Otomatis Analog Steam</button>
            </div>
          </div>

          <div class="table-responsive">
            <table class="ta-input-table">
              <thead>
                <tr>
                  <th class="head" colspan="4">AIR BOILER 21 TON ACTOM</th>
                  <th class="head" colspan="4">STEAM BOILER 21 TON ACTOM</th>
                  <th class="head" colspan="4">AIR ANALOG 21 TON ACTOM</th>
                  <th class="head" colspan="4">ANALOG STEAM 21 TON ACTOM</th>
                </tr>
                <tr>
                  <th class="sub">Awal</th>
                  <th class="sub">Akhir</th>
                  <th class="sub">Total Pemakaian</th>
                  <th class="sub">Rata-rata / Jam</th>
                  <th class="sub">Awal</th>
                  <th class="sub">Akhir</th>
                  <th class="sub">Total Pemakaian</th>
                  <th class="sub">Rata-rata / Jam</th>
                  <th class="sub">Awal</th>
                  <th class="sub">Akhir</th>
                  <th class="sub">Total Pemakaian</th>
                  <th class="sub">Rata-rata / Jam</th>
                  <th class="sub">Awal</th>
                  <th class="sub">Akhir</th>
                  <th class="sub">Total Pemakaian</th>
                  <th class="sub">Rata-rata / Jam</th>
                </tr>
                <tr>
                  <th class="unit">m3</th><th class="unit">m3</th><th class="unit">m3</th><th class="unit">m3</th>
                  <th class="unit">ton</th><th class="unit">ton</th><th class="unit">ton</th><th class="unit">ton</th>
                  <th class="unit">m3</th><th class="unit">m3</th><th class="unit">m3</th><th class="unit">m3</th>
                  <th class="unit">Ton</th><th class="unit">Ton</th><th class="unit">Ton</th><th class="unit">Ton</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="cell-gray"><input type="text" name="watt_awal" id="w2WattAwal" class="form-control form-control-sm num-only" data-decimals="2"></td>
                  <td class="cell-gray"><input type="text" name="watt_akhir" id="w2WattAkhir" class="form-control form-control-sm num-only" data-decimals="2"></td>
                  <td class="cell-blue"><input type="text" name="operasional_mesin" id="w2WattOperasional" class="form-control form-control-sm" readonly></td>
                  <td class="cell-gray"><input type="text" id="w2WattRata" class="form-control form-control-sm" readonly></td>

                  <td class="cell-gray"><input type="text" name="steam_awal" id="w2SteamAwal" class="form-control form-control-sm num-only" data-decimals="2"></td>
                  <td class="cell-gray"><input type="text" name="steam_akhir" id="w2SteamAkhir" class="form-control form-control-sm num-only" data-decimals="2"></td>
                  <td class="cell-blue"><input type="text" name="steam_pemakaian" id="w2SteamPemakaian" class="form-control form-control-sm" readonly></td>
                  <td class="cell-gray"><input type="text" id="w2SteamRata" class="form-control form-control-sm" readonly></td>

                  <td class="cell-gray"><input type="text" name="water_awal" id="w2WaterAwal" class="form-control form-control-sm num-only" data-decimals="2"></td>
                  <td class="cell-gray"><input type="text" name="water_akhir" id="w2WaterAkhir" class="form-control form-control-sm num-only" data-decimals="2"></td>
                  <td class="cell-blue"><input type="text" name="total_pemakaian" id="w2WaterTotal" class="form-control form-control-sm" readonly></td>
                  <td class="cell-gray"><input type="text" name="pemakaian_rata_per_jam" id="w2WaterRata" class="form-control form-control-sm" readonly></td>

                  <td class="cell-gray"><input type="text" name="analog_steam_awal" id="w2AnalogSteamAwal" class="form-control form-control-sm num-only" data-decimals="2"></td>
                  <td class="cell-gray"><input type="text" name="analog_steam_akhir" id="w2AnalogSteamAkhir" class="form-control form-control-sm num-only" data-decimals="2"></td>
                  <td class="cell-blue"><input type="text" name="analog_steam_pemakaian" id="w2AnalogSteamTotal" class="form-control form-control-sm" readonly></td>
                  <td class="cell-gray"><input type="text" name="analog_steam_rata_per_jam" id="w2AnalogSteamRata" class="form-control form-control-sm" readonly></td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="form-row mt-2">
            <div class="col-12">
              <div class="alert alert-info mb-0 py-2 px-3">
                Rumus: Total = Akhir - Awal, Rata/Jam = Total / 24.
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

<div class="modal fade" id="modalTambahCatatanWashing2" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document"><div class="modal-content">
    <div class="modal-header bg-warning text-white"><h5 class="modal-title">Tambah Catatan Washing 2</h5><button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button></div>
    <form id="formTambahCatatanWashing2" autocomplete="off"><div class="modal-body">
      <div class="form-group"><label>Tanggal</label><input type="date" name="tanggal" class="form-control" required></div>
      <div class="form-group"><label>Catatan</label><textarea name="catatan" class="form-control" rows="3" required></textarea></div>
    </div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button type="submit" class="btn btn-warning">Simpan Catatan</button></div></form>
  </div></div>
</div>

<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<style>
  .ta-input-table{border-collapse:collapse;width:100%;min-width:1680px}
  .ta-input-table th,.ta-input-table td{border:1px solid #c9ced4;padding:5px 6px;text-align:center;vertical-align:middle}
  .ta-input-table .head{background:#b7cae2;font-weight:700}
  .ta-input-table .sub{background:#f3e2e2;font-weight:700}
  .ta-input-table .unit{background:#fafafa;font-size:11px;font-weight:700}
  .ta-input-table .cell-blue{background:#c4d5e9}
  .ta-input-table .cell-gray{background:#efefef}
  .ta-input-table input{height:32px;text-align:right}
  #modalForm21TonActom .modal-body {
    max-height: calc(100vh - 230px);
    overflow-y: auto;
  }
  #modalForm21TonActom .modal-footer {
    position: sticky;
    bottom: 0;
    background: #fff;
    z-index: 2;
  }
</style>

<script>
$(function(){
  var canEdit = <?= $canEdit ? 'true' : 'false' ?>;
  var canDelete = <?= $canDelete ? 'true' : 'false' ?>;

  function parseNum(v){ if(v===null||v===undefined) return NaN; v=String(v).trim().replace(/,/g,''); if(v==='') return NaN; var n=Number(v); return isNaN(n)?NaN:n; }
  function fmt(v,d){ var n=parseNum(v); return isNaN(n)?'-':n.toLocaleString('en-US',{minimumFractionDigits:d,maximumFractionDigits:d}); }
  function fmtInput(v,d){ var n=parseNum(v); return isNaN(n)?'':n.toLocaleString('en-US',{minimumFractionDigits:d,maximumFractionDigits:d}); }

  $(document).on('click', '#btnTambahData', function(e){
    e.preventDefault();
    $('#form21TonActom')[0].reset();
    $('#w2Tanggal').val(new Date().toISOString().slice(0,10));
    $('#modalForm21TonActom').modal('show');
  });

  var table = $('#tonactomTable').DataTable({
    processing:true, serverSide:true,
    ajax:{ url:'21tonactom_serverside.php', type:'POST', data:function(d){ d.start_date=$('#filterStartDate').val(); d.end_date=$('#filterEndDate').val(); d.parameter=$('#filterParameter').val(); } },
    columns:[
      {data:null,orderable:false,searchable:false,render:function(d,t,r,m){return m.row+m.settings._iDisplayStart+1;}},
      {data:'tanggal_formatted'},
      {data:'parameter'},
      {data:'awal',render:function(d){return d===null||d===''?'-':fmt(d,2);}},
      {data:'akhir',render:function(d){return d===null||d===''?'-':fmt(d,2);}},
      {data:'total_pemakaian',render:function(d){return d===null||d===''?'-':fmt(d,2);}},
      {data:'pemakaian_rata',render:function(d){return d===null||d===''?'-':fmt(d,2);}},
      {data:'created_by',render:function(d){return d===null||d===''?'-':d;}},
      {data:'aksi',orderable:false,searchable:false,render:function(d,t,r){
        if(!r || !r.id || !r.param_key) return '-';
        var html = '<div class="btn-group btn-group-sm">';
        html += '<button class="btn btn-info btn-detail" data-id="'+r.id+'" data-param="'+r.param_key+'"><i class="fas fa-eye"></i></button>';
        if (canEdit) html += '<button class="btn btn-warning btn-edit" data-id="'+r.id+'" data-param="'+r.param_key+'"><i class="fas fa-edit"></i></button>';
        if (canDelete) html += '<button class="btn btn-danger btn-delete" data-id="'+r.id+'" data-param="'+r.param_key+'"><i class="fas fa-trash"></i></button>';
        html += '</div>';
        return html;
      }}
    ],
    ordering:false, responsive:true,
    language:{ processing:'Memproses...', lengthMenu:'Tampilkan _MENU_ data per halaman', zeroRecords:'Tidak ada data ditemukan', info:'Menampilkan _START_ - _END_ dari _TOTAL_ data', infoEmpty:'Tidak ada data tersedia', infoFiltered:'(disaring dari _MAX_ total data)', search:'Cari:', paginate:{first:'Pertama',last:'Terakhir',next:'Selanjutnya',previous:'Sebelumnya'} }
  });

  $('#filterStartDate,#filterEndDate,#filterParameter').on('change', function(){ table.ajax.reload(); });
  $('#btnResetFilter').on('click', function(){ $('#filterStartDate').val(''); $('#filterEndDate').val(''); $('#filterParameter').val(''); table.search('').draw(); });

  $('#btnTambahCatatan').on('click', function(e){ e.preventDefault(); $('#formTambahCatatanWashing2')[0].reset(); $('#modalTambahCatatanWashing2').modal('show'); });

  $(document).on('input','.num-only', function(){ this.value=this.value.replace(/[^0-9.,]/g,''); });
  $(document).on('blur','.num-only', function(){
    var decimals = parseInt($(this).data('decimals') || 2, 10);
    var n = parseNum(this.value);
    if (!isNaN(n)) this.value = fmtInput(n, decimals);
  });

  function recalcWattOperasional() {
    var awal = parseNum($('#w2WattAwal').val());
    var akhir = parseNum($('#w2WattAkhir').val());
    if (isNaN(awal) || isNaN(akhir)) {
      $('#w2WattOperasional').val('');
      $('#w2WattRata').val('');
      return;
    }
    var total = (akhir - awal);
    $('#w2WattOperasional').val(fmtInput(total, 2));
    $('#w2WattRata').val(fmtInput(total / 24, 2));
  }
  $('#w2WattAwal, #w2WattAkhir').on('input blur', recalcWattOperasional);

  function recalcSteamPemakaian() {
    var awal = parseNum($('#w2SteamAwal').val());
    var akhir = parseNum($('#w2SteamAkhir').val());
    if (isNaN(awal) || isNaN(akhir)) {
      $('#w2SteamPemakaian').val('');
      $('#w2SteamRata').val('');
      return;
    }
    var total = (akhir - awal);
    $('#w2SteamPemakaian').val(fmtInput(total, 2));
    $('#w2SteamRata').val(fmtInput(total / 24, 2));
  }
  $('#w2SteamAwal, #w2SteamAkhir').on('input blur', recalcSteamPemakaian);

  function recalcWaterFormula() {
    var awal = parseNum($('#w2WaterAwal').val());
    var akhir = parseNum($('#w2WaterAkhir').val());
    if (isNaN(awal) || isNaN(akhir)) {
      $('#w2WaterTotal').val('');
      $('#w2WaterRata').val('');
      return;
    }
    var total = akhir - awal;
    $('#w2WaterTotal').val(fmtInput(total, 2));
    $('#w2WaterRata').val(fmtInput(total / 24, 2));
  }
  $('#w2WaterAwal, #w2WaterAkhir').on('input blur', recalcWaterFormula);

  function recalcAnalogSteamFormula() {
    var awal = parseNum($('#w2AnalogSteamAwal').val());
    var akhir = parseNum($('#w2AnalogSteamAkhir').val());
    if (isNaN(awal) || isNaN(akhir)) {
      $('#w2AnalogSteamTotal').val('');
      $('#w2AnalogSteamRata').val('');
      return;
    }
    var total = akhir - awal;
    $('#w2AnalogSteamTotal').val(fmtInput(total, 2));
    $('#w2AnalogSteamRata').val(fmtInput(total / 24, 2));
  }
  $('#w2AnalogSteamAwal, #w2AnalogSteamAkhir').on('input blur', recalcAnalogSteamFormula);

  $('#btnIsiOtomatisWatt').on('click', function(){
    var tanggal = $('#w2Tanggal').val();
    if (!tanggal) {
      Swal.fire({ icon:'warning', title:'Tanggal belum dipilih', text:'Pilih tanggal dulu.' });
      return;
    }
    $.ajax({
      url:'get_21tonactom_air_boiler_prev.php',
      type:'GET',
      dataType:'json',
      data:{ tanggal:tanggal },
      success:function(resp){
        if (resp && resp.success && resp.watt_akhir !== null && resp.watt_akhir !== '') {
          $('#w2WattAwal').val(fmtInput(resp.watt_akhir, 2));
          recalcWattOperasional();
        } else {
          Swal.fire({ icon:'info', title:'Data tidak ditemukan', text:'Tidak ada data Watt Akhir sebelumnya untuk tanggal ini.' });
        }
      },
      error:function(){
        Swal.fire({ icon:'error', title:'Error', text:'Gagal mengambil data Watt Akhir sebelumnya.' });
      }
    });
  });

  $('#btnIsiOtomatisSteam').on('click', function(){
    var tanggal = $('#w2Tanggal').val();
    if (!tanggal) { Swal.fire({ icon:'warning', title:'Tanggal belum dipilih', text:'Pilih tanggal dulu.' }); return; }
    $.ajax({
      url:'get_21tonactom_steam_boiler_prev.php',
      type:'GET',
      dataType:'json',
      data:{ tanggal:tanggal },
      success:function(resp){
        if (resp && resp.success && resp.steam_akhir !== null && resp.steam_akhir !== '') {
          $('#w2SteamAwal').val(fmtInput(resp.steam_akhir, 2));
          recalcSteamPemakaian();
        } else {
          Swal.fire({ icon:'info', title:'Data tidak ditemukan', text:'Tidak ada data Steam Akhir sebelumnya untuk tanggal ini.' });
        }
      },
      error:function(){ Swal.fire({ icon:'error', title:'Error', text:'Gagal mengambil data Steam Akhir sebelumnya.' }); }
    });
  });

  $('#btnIsiOtomatisWater').on('click', function(){
    var tanggal = $('#w2Tanggal').val();
    if (!tanggal) { Swal.fire({ icon:'warning', title:'Tanggal belum dipilih', text:'Pilih tanggal dulu.' }); return; }
    $.ajax({
      url:'get_21tonactom_air_analog_prev.php',
      type:'GET',
      dataType:'json',
      data:{ tanggal:tanggal },
      success:function(resp){
        if (resp && resp.success && resp.water_akhir !== null && resp.water_akhir !== '') {
          $('#w2WaterAwal').val(fmtInput(resp.water_akhir, 2));
          recalcWaterFormula();
        } else {
          Swal.fire({ icon:'info', title:'Data tidak ditemukan', text:'Tidak ada data Water Akhir sebelumnya untuk tanggal ini.' });
        }
      },
      error:function(){ Swal.fire({ icon:'error', title:'Error', text:'Gagal mengambil data Water Akhir sebelumnya.' }); }
    });
  });

  $('#btnIsiOtomatisAnalogSteam').on('click', function(){
    var tanggal = $('#w2Tanggal').val();
    if (!tanggal) { Swal.fire({ icon:'warning', title:'Tanggal belum dipilih', text:'Pilih tanggal dulu.' }); return; }
    $.ajax({
      url:'get_21tonactom_analog_steam_prev.php',
      type:'GET',
      dataType:'json',
      data:{ tanggal:tanggal },
      success:function(resp){
        if (resp && resp.success && resp.analog_steam_akhir !== null && resp.analog_steam_akhir !== '') {
          $('#w2AnalogSteamAwal').val(fmtInput(resp.analog_steam_akhir, 2));
          recalcAnalogSteamFormula();
        } else {
          Swal.fire({ icon:'info', title:'Data tidak ditemukan', text:'Tidak ada data Analog Steam Akhir sebelumnya untuk tanggal ini.' });
        }
      },
      error:function(){ Swal.fire({ icon:'error', title:'Error', text:'Gagal mengambil data Analog Steam Akhir sebelumnya.' }); }
    });
  });

  function hasAnyValue(selectors){
    for (var i=0;i<selectors.length;i++){
      var v = $(selectors[i]).val();
      if (v !== null && v !== undefined && String(v).trim() !== '') return true;
    }
    return false;
  }

  $('#form21TonActom').on('submit', function(e){
    e.preventDefault();
    var tanggal = $('#w2Tanggal').val();
    if (!tanggal) {
      Swal.fire({ icon:'warning', title:'Tanggal belum dipilih', text:'Pilih tanggal dulu.' });
      return;
    }

    var tasks = [];
    if (hasAnyValue(['#w2WattAwal','#w2WattAkhir'])) {
      tasks.push($.ajax({
        url:'save_21tonactom_air_boiler.php',
        type:'POST',
        dataType:'json',
        data:{
          tanggal:tanggal,
          watt_awal:$('#w2WattAwal').val(),
          watt_akhir:$('#w2WattAkhir').val(),
          operasional_mesin:$('#w2WattOperasional').val()
        }
      }));
    }
    if (hasAnyValue(['#w2SteamAwal','#w2SteamAkhir'])) {
      tasks.push($.ajax({
        url:'save_21tonactom_steam_boiler.php',
        type:'POST',
        dataType:'json',
        data:{
          tanggal:tanggal,
          steam_awal:$('#w2SteamAwal').val(),
          steam_akhir:$('#w2SteamAkhir').val(),
          steam_pemakaian:$('#w2SteamPemakaian').val()
        }
      }));
    }
    if (hasAnyValue(['#w2WaterAwal','#w2WaterAkhir'])) {
      tasks.push($.ajax({
        url:'save_21tonactom_air_analog.php',
        type:'POST',
        dataType:'json',
        data:{
          tanggal:tanggal,
          water_awal:$('#w2WaterAwal').val(),
          water_akhir:$('#w2WaterAkhir').val(),
          total_pemakaian:$('#w2WaterTotal').val(),
          pemakaian_rata_per_jam:$('#w2WaterRata').val()
        }
      }));
    }
    if (hasAnyValue(['#w2AnalogSteamAwal','#w2AnalogSteamAkhir'])) {
      tasks.push($.ajax({
        url:'save_21tonactom_analog_steam.php',
        type:'POST',
        dataType:'json',
        data:{
          tanggal:tanggal,
          analog_steam_awal:$('#w2AnalogSteamAwal').val(),
          analog_steam_akhir:$('#w2AnalogSteamAkhir').val(),
          analog_steam_pemakaian:$('#w2AnalogSteamTotal').val(),
          analog_steam_rata_per_jam:$('#w2AnalogSteamRata').val()
        }
      }));
    }

    if (tasks.length === 0) {
      Swal.fire({ icon:'warning', title:'Data kosong', text:'Isi minimal salah satu parameter sebelum menyimpan.' });
      return;
    }

    $.when.apply($, tasks).done(function(){
      var responses = tasks.length === 1
        ? [arguments[0]]
        : Array.prototype.slice.call(arguments).map(function(a){ return Array.isArray(a) ? a[0] : a; });
      var failResp = responses.find(function(r){ return !r || !r.success; });
      if (failResp) {
        Swal.fire({ icon:'error', title:'Gagal', text:(failResp && failResp.message)?failResp.message:'Gagal menyimpan.' });
        return;
      }
      $('#modalForm21TonActom').modal('hide');
      Swal.fire({ icon:'success', title:'Sukses', text:'Data 21 Ton Actom berhasil disimpan.' });
      table.ajax.reload(null,false);
    }).fail(function(){
      Swal.fire({ icon:'error', title:'Error', text:'Terjadi kesalahan.' });
    });
  });

  $('#formTambahCatatanWashing2').on('submit', function(e){
    e.preventDefault();
    $.ajax({
      url:'save_catatan_21tonactom.php',
      type:'POST',
      data:$(this).serialize(),
      dataType:'json',
      success:function(resp){
        if(resp&&resp.success){
          $('#modalTambahCatatanWashing2').modal('hide');
          Swal.fire({icon:'success',title:'Sukses',text:resp.message||'Catatan tersimpan.'});
          table.ajax.reload(null,false);
        } else {
          Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal menyimpan catatan.'});
        }
      },
      error:function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan.'}); }
    });
  });

  $(document).on('click','.btn-detail',function(){
    var id=$(this).data('id'), p=$(this).data('param');
    if(id && p) window.location.href='view_21tonactom.php?id='+encodeURIComponent(id)+'&param='+encodeURIComponent(p);
  });
  $(document).on('click','.btn-edit',function(){
    var id=$(this).data('id'), p=$(this).data('param');
    if(id && p) window.location.href='edit_21tonactom.php?id='+encodeURIComponent(id)+'&param='+encodeURIComponent(p);
  });
  $(document).on('click','.btn-delete',function(){
    var id=$(this).data('id'), p=$(this).data('param');
    if(!id || !p) return;
    Swal.fire({title:'Hapus Data?',text:'Data akan dihapus permanen.',icon:'warning',showCancelButton:true,confirmButtonColor:'#d33',cancelButtonColor:'#3085d6',confirmButtonText:'Ya, Hapus!',cancelButtonText:'Batal'})
      .then(function(r){ if(r.isConfirmed){ window.location.href='delete_21tonactom.php?id='+encodeURIComponent(id)+'&param='+encodeURIComponent(p); }});
  });
});
</script>
