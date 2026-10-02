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
  <style>
    .abl-modal-label {
      font-weight: 600;
      margin-bottom: 4px;
    }
    .modal-dialog.abl-modal-xl {
      max-width: 96%;
    }
    .abl-table-wrap {
      border: 1px solid #cdd5df;
      border-radius: 8px;
      overflow: hidden;
    }
    .abl-input-table {
      margin-bottom: 0;
    }
    .abl-input-table th,
    .abl-input-table td {
      border: 1px solid #b9c3cf !important;
      vertical-align: middle;
      text-align: center;
      white-space: nowrap;
      padding: 6px;
      font-size: 12px;
    }
    .abl-input-table .head-group {
      font-size: 14px;
      font-weight: 700;
      color: #1f2d3d;
      padding-top: 8px;
      padding-bottom: 8px;
    }
    .abl-input-table .head-flow {
      background: #c7d9eb;
    }
    .abl-input-table .head-use {
      background: #efd9d9;
    }
    .abl-input-table .head-calc {
      background: #fff2b2;
    }
    .abl-input-table .sub-flow {
      background: #dbe9f8;
      font-weight: 600;
    }
    .abl-input-table .sub-use {
      background: #f5e6e6;
      font-weight: 600;
    }
    .abl-input-table .sub-calc {
      background: #fff8d9;
      font-weight: 600;
    }
    .abl-input-table .unit {
      font-size: 11px;
      font-weight: 700;
      color: #435162;
      padding-top: 4px;
      padding-bottom: 4px;
    }
    .abl-input-table .unit.flow {
      background: #e8f1fb;
    }
    .abl-input-table .unit.use {
      background: #faefef;
    }
    .abl-input-table .unit.calc {
      background: #fffcec;
    }
    .abl-input-table input.form-control {
      min-width: 116px;
      text-align: right;
      font-size: 13px;
    }
    .abl-input-table input.abl-readonly {
      background: #eef3f9 !important;
      font-weight: 700;
    }
    .abl-help {
      font-size: 12px;
      border-radius: 6px;
      margin-top: 10px;
      margin-bottom: 0;
      padding: 8px 10px;
    }
  </style>
  <section class="content-header">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col-sm-6">
        <h1>AIR BERSIH & LIMBAH</h1>
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
          <h3 class="card-title"><i class="fas fa-list mr-1"></i> Data Air Bersih & Limbah Harian</h3>
          <div class="d-flex ml-auto" style="gap:8px;">
            <a href="report_air_bersih_limbah.php" class="btn btn-info btn-sm"><i class="fas fa-file-alt"></i> Detail Report</a>
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
          <table id="airBersihLimbahTable" class="table table-hover table-sm nowrap text-center" style="width:100%">
            <thead class="thead-light">
              <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Intake IPAB</th>
                <th>Bak 2 ke IPAL</th>
                <th>Bak 3 ke Bak 4</th>
                <th>Buangan ke IPAL</th>
                <th>Flowmeter Output</th>
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

<div class="modal fade" id="modalTambahAirBersihLimbah" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl abl-modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title">Input Air Bersih & Limbah</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="formAirBersihLimbah" autocomplete="off">
        <input type="hidden" name="id" id="xId">
        <div class="modal-body">
          <div class="form-row">
            <div class="form-group col-md-4 mb-2">
              <label class="abl-modal-label">Tanggal (TGL)</label>
              <input type="date" name="tanggal" id="xTanggal" class="form-control" required>
            </div>
            <div class="form-group col-md-8 mb-2">
              <label class="abl-modal-label">Keterangan (KET)</label>
              <input type="text" class="form-control" placeholder="Isi keterangan jika diperlukan (opsional)" readonly>
            </div>
          </div>

          <div class="abl-table-wrap">
            <div class="table-responsive">
              <table class="table table-sm abl-input-table">
                <thead>
                  <tr>
                    <th class="head-group head-flow" colspan="3">FLOW METER</th>
                    <th class="head-group head-use" colspan="11">PEMAKAIAN AIR BERSIH DARI IPAB</th>
                    <th class="head-group head-calc" colspan="2">HASIL RUMUS OTOMATIS</th>
                  </tr>
                  <tr>
                    <th class="sub-flow">Intake ke IPAB</th>
                    <th class="sub-flow">Bak 2 ke IPAL</th>
                    <th class="sub-flow">Bak 3 ke Bak 4</th>

                    <th class="sub-use">Washing 1</th>
                    <th class="sub-use">Washing 2</th>
                    <th class="sub-use">Washing 3</th>
                    <th class="sub-use">Perble Range 1</th>
                    <th class="sub-use">Perble Range 2</th>
                    <th class="sub-use">Pad Steam</th>
                    <th class="sub-use">Jetdying/Sizing/LA</th>
                    <th class="sub-use">Kantin/Mes/Pos</th>
                    <th class="sub-use">Boiler</th>
                    <th class="sub-use">Air MC & Lain-lain</th>
                    <th class="sub-use">Flowmeter Output IPAL</th>

                    <th class="sub-calc">Weaving dan Lain-lain</th>
                    <th class="sub-calc">Buangan ke IPAL</th>
                  </tr>
                  <tr>
                    <th class="unit flow">m3/hari</th>
                    <th class="unit flow">m3/hari</th>
                    <th class="unit flow">m3/hari</th>

                    <th class="unit use">m3/hari</th>
                    <th class="unit use">m3/hari</th>
                    <th class="unit use">m3/hari</th>
                    <th class="unit use">m3/hari</th>
                    <th class="unit use">m3/hari</th>
                    <th class="unit use">m3/hari</th>
                    <th class="unit use">m3/hari</th>
                    <th class="unit use">m3/hari</th>
                    <th class="unit use">m3/hari</th>
                    <th class="unit use">m3/hari</th>
                    <th class="unit use">m3/hari</th>

                    <th class="unit calc">m3/hari</th>
                    <th class="unit calc">m3/hari</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td><input type="text" name="flow_meter_intake_ipab_m3_hari" id="xIntake" class="form-control form-control-sm num-only"></td>
                    <td><input type="text" name="flow_meter_bak_dua_ke_ipal_m3_hari" id="xBak2Ipal" class="form-control form-control-sm num-only"></td>
                    <td><input type="text" name="flow_meter_bak3_ke_bak4_jasa_tirta_m3_hari" id="xBak3Bak4" class="form-control form-control-sm num-only"></td>

                    <td><input type="text" name="washing_1_m3_hari" id="xW1" class="form-control form-control-sm abl-readonly" readonly></td>
                    <td><input type="text" name="washing_2_m3_hari" id="xW2" class="form-control form-control-sm abl-readonly" readonly></td>
                    <td><input type="text" name="washing_3_m3_hari" id="xW3" class="form-control form-control-sm abl-readonly" readonly></td>
                    <td><input type="text" name="perble_range_1_m3_hari" id="xPR1" class="form-control form-control-sm abl-readonly" readonly></td>
                    <td><input type="text" name="perble_range_2_m3_hari" id="xPR2" class="form-control form-control-sm abl-readonly" readonly></td>
                    <td><input type="text" name="pad_steam_m3_hari" id="xPad" class="form-control form-control-sm abl-readonly" readonly></td>
                    <td><input type="text" name="jetdying_sizing_la_m3_hari" id="xJet" class="form-control form-control-sm abl-readonly" readonly></td>
                    <td><input type="text" name="kantin_mes_pos_security_m3_hari" id="xKantin" class="form-control form-control-sm abl-readonly" readonly></td>
                    <td><input type="text" name="boiler_m3_hari" id="xBoiler" class="form-control form-control-sm num-only"></td>
                    <td><input type="text" name="air_mc_produksi_dan_lain_lain_m3_hari" id="xAirMc" class="form-control form-control-sm num-only"></td>
                    <td><input type="text" name="flowmeter_output_ipal_m3_hari" id="xOutputIpal" class="form-control form-control-sm num-only"></td>

                    <td><input type="text" id="xWeavingLain" class="form-control form-control-sm abl-readonly" readonly></td>
                    <td><input type="text" id="xBuanganIpal" class="form-control form-control-sm abl-readonly" readonly></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="alert alert-info abl-help">
            Ikuti urutan input seperti tabel Excel: isi meter tercatat harian dahulu, lalu pemakaian area. Kolom hasil rumus dihitung otomatis.
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

<div class="modal fade" id="modalTambahCatatanAirBersihLimbah" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title">Tambah Catatan Air Bersih & Limbah</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="formCatatanAirBersihLimbah" autocomplete="off">
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
  function fetchWashingTotal(tanggal){
    if (!tanggal) {
      $('#xW1').val('');
      recalc();
      return;
    }
    $.ajax({
      url:'get_washing_total.php',
      type:'GET',
      dataType:'json',
      data:{ tanggal:tanggal },
      success:function(resp){
        if(resp && resp.success && resp.total_pemakaian !== null && resp.total_pemakaian !== ''){
          $('#xW1').val(fmtInput(resp.total_pemakaian,2));
        } else {
          $('#xW1').val('');
        }
        recalc();
      },
      error:function(){
        $('#xW1').val('');
        recalc();
      }
    });
  }
  function fetchWashing2Total(tanggal){
    if (!tanggal) {
      $('#xW2').val('');
      recalc();
      return;
    }
    $.ajax({
      url:'get_washing2_total.php',
      type:'GET',
      dataType:'json',
      data:{ tanggal:tanggal },
      success:function(resp){
        if(resp && resp.success && resp.total_pemakaian !== null && resp.total_pemakaian !== ''){
          $('#xW2').val(fmtInput(resp.total_pemakaian,2));
        } else {
          $('#xW2').val('');
        }
        recalc();
      },
      error:function(){
        $('#xW2').val('');
        recalc();
      }
    });
  }
  function fetchWashing3Total(tanggal){
    if (!tanggal) {
      $('#xW3').val('');
      recalc();
      return;
    }
    $.ajax({
      url:'get_washing3_total.php',
      type:'GET',
      dataType:'json',
      data:{ tanggal:tanggal },
      success:function(resp){
        if(resp && resp.success && resp.total_pemakaian !== null && resp.total_pemakaian !== ''){
          $('#xW3').val(fmtInput(resp.total_pemakaian,2));
        } else {
          $('#xW3').val('');
        }
        recalc();
      },
      error:function(){
        $('#xW3').val('');
        recalc();
      }
    });
  }
  function fetchPerbleRange1Debit(tanggal){
    if (!tanggal) {
      $('#xPR1').val('');
      recalc();
      return;
    }
    $.ajax({
      url:'get_perblerange1_debit.php',
      type:'GET',
      dataType:'json',
      data:{ tanggal:tanggal },
      success:function(resp){
        if(resp && resp.success && resp.total_debit !== null && resp.total_debit !== ''){
          $('#xPR1').val(fmtInput(resp.total_debit,2));
        } else {
          $('#xPR1').val('');
        }
        recalc();
      },
      error:function(){
        $('#xPR1').val('');
        recalc();
      }
    });
  }
  function fetchPerbleRange2Total(tanggal){
    if (!tanggal) {
      $('#xPR2').val('');
      recalc();
      return;
    }
    $.ajax({
      url:'get_perblerange2_total.php',
      type:'GET',
      dataType:'json',
      data:{ tanggal:tanggal },
      success:function(resp){
        if(resp && resp.success && resp.total_pemakaian !== null && resp.total_pemakaian !== ''){
          $('#xPR2').val(fmtInput(resp.total_pemakaian,2));
        } else {
          $('#xPR2').val('');
        }
        recalc();
      },
      error:function(){
        $('#xPR2').val('');
        recalc();
      }
    });
  }
  function fetchPadSteamTotal(tanggal){
    if (!tanggal) {
      $('#xPad').val('');
      recalc();
      return;
    }
    $.ajax({
      url:'get_padsteam_total.php',
      type:'GET',
      dataType:'json',
      data:{ tanggal:tanggal },
      success:function(resp){
        if(resp && resp.success && resp.total_pemakaian !== null && resp.total_pemakaian !== ''){
          $('#xPad').val(fmtInput(resp.total_pemakaian,2));
        } else {
          $('#xPad').val('');
        }
        recalc();
      },
      error:function(){
        $('#xPad').val('');
        recalc();
      }
    });
  }
  function fetchJetdyeingTotal(tanggal){
    if (!tanggal) {
      $('#xJet').val('');
      recalc();
      return;
    }
    $.ajax({
      url:'get_jetdyeing_total.php',
      type:'GET',
      dataType:'json',
      data:{ tanggal:tanggal },
      success:function(resp){
        if(resp && resp.success && resp.total_pemakaian !== null && resp.total_pemakaian !== ''){
          $('#xJet').val(fmtInput(resp.total_pemakaian,2));
        } else {
          $('#xJet').val('');
        }
        recalc();
      },
      error:function(){
        $('#xJet').val('');
        recalc();
      }
    });
  }
  function fetchMkpTotal(tanggal){
    if (!tanggal) {
      $('#xKantin').val('');
      recalc();
      return;
    }
    $.ajax({
      url:'get_mkp_total.php',
      type:'GET',
      dataType:'json',
      data:{ tanggal:tanggal },
      success:function(resp){
        if(resp && resp.success && resp.total_pemakaian !== null && resp.total_pemakaian !== ''){
          $('#xKantin').val(fmtInput(resp.total_pemakaian,2));
        } else {
          $('#xKantin').val('');
        }
        recalc();
      },
      error:function(){
        $('#xKantin').val('');
        recalc();
      }
    });
  }
  function recalc(){
    var d = parseNum($('#xBak3Bak4').val());
    var k = parseNum($('#xJet').val()), l = parseNum($('#xKantin').val()), m = parseNum($('#xBoiler').val()), n = parseNum($('#xAirMc').val());
    var o = (isNaN(d)?0:d) - ((isNaN(k)?0:k)+(isNaN(l)?0:l)+(isNaN(m)?0:m)+(isNaN(n)?0:n));
    $('#xWeavingLain').val(fmtInput(o,2));

    var e = parseNum($('#xW1').val()), f = parseNum($('#xW2').val()), g = parseNum($('#xW3').val()),
        h = parseNum($('#xPR1').val()), i = parseNum($('#xPR2').val()), j = parseNum($('#xPad').val());
    var p = (isNaN(e)?0:e)+(isNaN(f)?0:f)+(isNaN(g)?0:g)+(isNaN(h)?0:h)+(isNaN(i)?0:i)+(isNaN(j)?0:j)+(isNaN(k)?0:k)+(isNaN(l)?0:l)+(isNaN(m)?0:m)+(isNaN(n)?0:n);
    $('#xBuanganIpal').val(fmtInput(p,2));
  }

  var table = $('#airBersihLimbahTable').DataTable({
    processing:true, serverSide:true, ordering:false, responsive:true,
    ajax:{ url:'air_bersih_limbah_serverside.php', type:'POST', data:function(d){ d.start_date=$('#filterStartDate').val(); d.end_date=$('#filterEndDate').val(); }},
    columns:[
      {data:null,render:function(d,t,r,m){return m.row+m.settings._iDisplayStart+1;}},
      {data:'tanggal_formatted'},
      {data:'flow_meter_intake_ipab_m3_hari',render:function(d){return fmt(d,2)}},
      {data:'flow_meter_bak_dua_ke_ipal_m3_hari',render:function(d){return fmt(d,2)}},
      {data:'flow_meter_bak3_ke_bak4_jasa_tirta_m3_hari',render:function(d){return fmt(d,2)}},
      {data:'buangan_air_produk_ke_ipal_m3_hari',render:function(d){return fmt(d,2)}},
      {data:'flowmeter_output_ipal_m3_hari',render:function(d){return fmt(d,2)}},
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
    $('#formAirBersihLimbah')[0].reset();
    $('#xId').val('');
    $('#xTanggal').prop('readonly', false).val(new Date().toISOString().slice(0,10));
    fetchWashingTotal($('#xTanggal').val());
    fetchWashing2Total($('#xTanggal').val());
    fetchWashing3Total($('#xTanggal').val());
    fetchPerbleRange1Debit($('#xTanggal').val());
    fetchPerbleRange2Total($('#xTanggal').val());
    fetchPadSteamTotal($('#xTanggal').val());
    fetchJetdyeingTotal($('#xTanggal').val());
    fetchMkpTotal($('#xTanggal').val());
    $('#modalTambahAirBersihLimbah').modal('show');
  });
  $('#btnTambahCatatan').on('click', function(e){ e.preventDefault(); $('#formCatatanAirBersihLimbah')[0].reset(); $('#xCatatanTanggal').val(new Date().toISOString().slice(0,10)); $('#modalTambahCatatanAirBersihLimbah').modal('show'); });
  $(document).on('input','.num-only',function(){ this.value=this.value.replace(/[^0-9.,]/g,''); });
  $('#formAirBersihLimbah .num-only').on('input blur', recalc);

  $('#formAirBersihLimbah').on('submit', function(e){
    e.preventDefault();
    $.ajax({ url:'save_air_bersih_limbah.php', type:'POST', data:$(this).serialize(), dataType:'json',
      success:function(resp){
        if(resp&&resp.success){
          $('#modalTambahAirBersihLimbah').modal('hide');
          Swal.fire({icon:'success',title:'Sukses',text:resp.message||'Tersimpan.'});
          table.ajax.reload(null,false);
        } else Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal menyimpan.'});
      }, error:function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan.'}); }
    });
  });

  $('#formCatatanAirBersihLimbah').on('submit', function(e){
    e.preventDefault();
    $.ajax({ url:'save_catatan_air_bersih_limbah.php', type:'POST', data:$(this).serialize(), dataType:'json',
      success:function(resp){
        if(resp&&resp.success){
          $('#modalTambahCatatanAirBersihLimbah').modal('hide');
          Swal.fire({icon:'success',title:'Sukses',text:resp.message||'Catatan tersimpan.'});
          table.ajax.reload(null,false);
        } else Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal menyimpan catatan.'});
      }, error:function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan.'}); }
    });
  });

  $('#airBersihLimbahTable').on('click','.btn-detail',function(){ window.location.href='view_air_bersih_limbah.php?id='+$(this).data('id'); });
  $('#airBersihLimbahTable').on('click','.btn-edit',function(){
    var tr = $(this).closest('tr');
    var row = table.row(tr);
    if (!row.data() && tr.hasClass('child')) row = table.row(tr.prev());
    var d = row.data();
    if (!d) return;

    $('#formAirBersihLimbah')[0].reset();
    $('#xId').val(d.id || '');
    $('#xTanggal').prop('readonly', true).val(d.tanggal || '');
    $('#xIntake').val(fmtInput(d.flow_meter_intake_ipab_m3_hari,2));
    $('#xBak2Ipal').val(fmtInput(d.flow_meter_bak_dua_ke_ipal_m3_hari,2));
    $('#xBak3Bak4').val(fmtInput(d.flow_meter_bak3_ke_bak4_jasa_tirta_m3_hari,2));
    $('#xW1').val(fmtInput(d.washing_1_m3_hari,2));
    $('#xW2').val(fmtInput(d.washing_2_m3_hari,2));
    $('#xW3').val(fmtInput(d.washing_3_m3_hari,2));
    $('#xPR1').val(fmtInput(d.perble_range_1_m3_hari,2));
    $('#xPR2').val(fmtInput(d.perble_range_2_m3_hari,2));
    $('#xPad').val(fmtInput(d.pad_steam_m3_hari,2));
    $('#xJet').val(fmtInput(d.jetdying_sizing_la_m3_hari,2));
    $('#xKantin').val(fmtInput(d.kantin_mes_pos_security_m3_hari,2));
    $('#xBoiler').val(fmtInput(d.boiler_m3_hari,2));
    $('#xAirMc').val(fmtInput(d.air_mc_produksi_dan_lain_lain_m3_hari,2));
    $('#xOutputIpal').val(fmtInput(d.flowmeter_output_ipal_m3_hari,2));
    fetchWashingTotal(d.tanggal || '');
    fetchWashing2Total(d.tanggal || '');
    fetchWashing3Total(d.tanggal || '');
    fetchPerbleRange1Debit(d.tanggal || '');
    fetchPerbleRange2Total(d.tanggal || '');
    fetchPadSteamTotal(d.tanggal || '');
    fetchJetdyeingTotal(d.tanggal || '');
    fetchMkpTotal(d.tanggal || '');
    $('#modalTambahAirBersihLimbah').modal('show');
  });
  $('#airBersihLimbahTable').on('click','.btn-delete',function(){
    var id=$(this).data('id');
    Swal.fire({title:'Hapus data?',text:'Data ini akan dihapus permanen.',icon:'warning',showCancelButton:true,confirmButtonText:'Ya, Hapus'})
      .then(function(r){
        if(!r.isConfirmed) return;
        $.post('delete_air_bersih_limbah.php',{id:id},function(resp){
          if(resp&&resp.success){ Swal.fire({icon:'success',title:'Sukses',text:resp.message||'Terhapus.'}); table.ajax.reload(null,false); }
          else Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal menghapus.'});
        },'json').fail(function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan.'}); });
      });
  });

  $('#xTanggal').on('change', function(){
    fetchWashingTotal($(this).val());
    fetchWashing2Total($(this).val());
    fetchWashing3Total($(this).val());
    fetchPerbleRange1Debit($(this).val());
    fetchPerbleRange2Total($(this).val());
    fetchPadSteamTotal($(this).val());
    fetchJetdyeingTotal($(this).val());
    fetchMkpTotal($(this).val());
  });
});
</script>
