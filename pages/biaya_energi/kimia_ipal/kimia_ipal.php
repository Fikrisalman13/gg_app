<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
$menuId = 236; // TODO: ganti MenuId kimia_ipal yang benar
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: /gg_app/index.php');
    exit;
}

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
$themeColor = $_SESSION['Theme'] ?? 'primary';

$masterItems = [];
$masterSql = "SELECT id, kode, nama_item, grup_laporan, satuan_pakai
              FROM dbo.kimia_ipal_master
              WHERE aktif = 1
              ORDER BY
                CASE grup_laporan
                    WHEN 'IPAL' THEN 1
                    WHEN 'PROSES' THEN 2
                    WHEN 'DAF_LAMA' THEN 3
                    WHEN 'DAF3_BARU' THEN 4
                    WHEN 'DAF2_BARU' THEN 5
                    ELSE 99
                END,
                id ASC";
$masterStmt = sqlsrv_query($conn, $masterSql);
if ($masterStmt) {
    while ($row = sqlsrv_fetch_array($masterStmt, SQLSRV_FETCH_ASSOC)) {
        $masterItems[] = $row;
    }
    sqlsrv_free_stmt($masterStmt);
}

$groupLabels = [
    'IPAL' => 'IPAL',
    'PROSES' => 'PROSES',
    'DAF_LAMA' => 'DAF LAMA',
    'DAF3_BARU' => 'DAF 3 BARU',
    'DAF2_BARU' => 'DAF 2 BARU'
];

$grouped = [];
foreach ($masterItems as $item) {
    $g = $item['grup_laporan'] ?? 'LAINNYA';
    if (!isset($grouped[$g])) $grouped[$g] = [];
    $grouped[$g][] = $item;
}
?>

<div class="wrapper">
<div class="content-wrapper">
    <section class="content-header">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col-sm-6">
        <h1>Pemakaian Kimia IPAL</h1>
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
                    <h3 class="card-title"><i class="fas fa-list mr-1"></i> List Data Kimia IPAL</h3>
                    <div class="d-flex ml-auto" style="gap:8px;">
                        <a href="report_biaya_kimia_ipal.php" class="btn btn-info btn-sm"><i class="fas fa-file-alt"></i> Detail Report</a>
                        <?php if (!empty($permissions['CanAdd']) && $permissions['CanAdd'] == 1): ?>
                            <a href="#" class="btn btn-info btn-sm" id="btnTambahBatch"><i class="fas fa-th"></i> Input Banyak</a>
                        <?php endif; ?>
                        <?php if (!empty($permissions['CanAdd']) && $permissions['CanAdd'] == 1): ?>
                            <a href="#" class="btn btn-warning btn-sm" id="btnTambahCatatan"><i class="fas fa-sticky-note"></i> Tambah Catatan</a>
                        <?php endif; ?>
                        <?php if (!empty($permissions['CanAdd']) && $permissions['CanAdd'] == 1): ?>
                            <a href="#" class="btn btn-success btn-sm" id="btnTambahData"><i class="fas fa-plus"></i> Tambah Data</a>
                        <?php endif; ?>
                        <?php if (!empty($permissions['CanEdit']) && $permissions['CanEdit'] == 1): ?>
                            <a href="#" class="btn btn-warning btn-sm" id="btnSettingMaster"><i class="fas fa-cog"></i> Setting Master</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body table-responsive">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label for="filterStartDate">Dari Tanggal</label>
                            <input type="date" id="filterStartDate" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label for="filterEndDate">Sampai Tanggal</label>
                            <input type="date" id="filterEndDate" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label for="filterGroup">Grup</label>
                            <select id="filterGroup" class="form-control">
                                <option value="">Semua</option>
                                <option value="IPAL">IPAL</option>
                                <option value="PROSES">PROSES</option>
                                <option value="DAF_LAMA">DAF LAMA</option>
                                <option value="DAF3_BARU">DAF 3 BARU</option>
                                <option value="DAF2_BARU">DAF 2 BARU</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" class="btn btn-secondary btn-sm" id="btnResetFilter">
                                <i class="fas fa-undo"></i> Reset Filter
                            </button>
                        </div>
                    </div>
                    <table id="kimiaIpalTable" class="table table-hover table-sm nowrap text-center" style="width:100%">
                        <thead class="thead-light text-center">
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Parameter</th>
                                <th>Grup</th>
                                <th>Pakai (Kg)</th>
                                <th>Harga (Rp)</th>
                                <th>Biaya (Rp)</th>
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

<div class="modal fade" id="modalPilihFormKimiaIpal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title">Pilih Form Input Kimia IPAL</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body" id="pilihFormBodyKimia" style="max-height:65vh;overflow-y:auto;">
        <div class="text-muted">Memuat master kimia...</div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalFormKimiaIpal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="kimiaFormTitle">Input Kimia IPAL</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="formKimiaIpal" autocomplete="off">
        <div class="modal-body">
          <input type="hidden" name="master_id" id="kimiaMasterId">
          <div class="form-group">
            <label>Parameter</label>
            <input type="text" id="kimiaParameterName" class="form-control" readonly>
          </div>
          <div class="form-group">
            <label>Grup</label>
            <input type="text" id="kimiaGroupName" class="form-control" readonly>
          </div>
          <div class="form-group">
            <label>Tanggal</label>
            <input type="date" name="tanggal" id="kimiaTanggal" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Pakai (<span id="kimiaSatuanLabel">Kg</span>)</label>
            <input type="text" name="pakai_kg" id="kimiaPakai" class="form-control num-only" data-decimals="2" required>
          </div>
          <div class="form-group">
            <label>Harga (Rp)</label>
            <input type="text" name="harga_rp" id="kimiaHarga" class="form-control num-only" data-decimals="2" required>
          </div>
          <div class="form-group">
            <label>Biaya (Rp)</label>
            <input type="text" id="kimiaBiaya" class="form-control" readonly>
          </div>
          <div class="form-group">
            <label>Catatan</label>
            <textarea name="catatan" id="kimiaCatatan" class="form-control" rows="2"></textarea>
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

<div class="modal fade" id="modalTambahCatatanKimiaIpal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title">Tambah Catatan Kimia IPAL</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="formTambahCatatanKimiaIpal" autocomplete="off">
        <div class="modal-body">
          <div class="form-group">
            <label for="kimiaCatatanTanggal">Tanggal</label>
            <input type="date" class="form-control" id="kimiaCatatanTanggal" name="tanggal" required>
          </div>
          <div class="form-group">
            <label for="kimiaCatatanMaster">Parameter</label>
            <select class="form-control" id="kimiaCatatanMaster" name="master_id" required>
              <option value="">Memuat master...</option>
            </select>
          </div>
          <div class="form-group">
            <label for="kimiaCatatanText">Catatan</label>
            <textarea class="form-control" id="kimiaCatatanText" name="catatan" rows="4" placeholder="Tulis catatan..." required></textarea>
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

<div class="modal fade" id="modalBatchKimiaIpal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title">Input Banyak Kimia IPAL</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="row mb-2">
          <div class="col-md-3">
            <label>Tanggal</label>
            <input type="date" id="batchTanggal" class="form-control" required>
          </div>
          <div class="col-md-3">
            <label>Grup</label>
            <select id="batchGroup" class="form-control">
              <option value="PROSES">PROSES</option>
              <option value="DAF_LAMA">DAF LAMA</option>
              <option value="DAF3_BARU">DAF 3 BARU</option>
              <option value="DAF2_BARU">DAF 2 BARU</option>
            </select>
          </div>
          <div class="col-md-6 d-flex align-items-end">
            <div class="text-muted small">Isi hanya item yang dipakai. Biaya otomatis = Pakai x Harga.</div>
          </div>
        </div>
        <div class="table-responsive" style="max-height:60vh;overflow:auto;">
          <table class="table table-sm table-bordered text-center" id="batchKimiaTable">
            <thead class="thead-light">
              <tr>
                <th style="width:60px;">ID</th>
                <th>Parameter</th>
                <th style="width:120px;">Satuan</th>
                <th style="width:140px;">Pakai</th>
                <th style="width:160px;">Harga (Rp)</th>
                <th style="width:160px;">Biaya (Rp)</th>
                <th style="width:220px;">Catatan</th>
              </tr>
            </thead>
            <tbody>
              <tr><td colspan="7">Memuat master...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="btnSimpanBatchKimia"><i class="fas fa-save"></i> Simpan Semua</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalSettingMasterKimia" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title">Setting Master Kimia IPAL</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <form id="formMasterKimia" class="border rounded p-3 mb-3" autocomplete="off">
          <input type="hidden" name="id" id="masterId">
          <div class="row">
            <div class="col-md-3 form-group">
              <label>Kode</label>
              <input type="text" name="kode" id="masterKode" class="form-control" required>
            </div>
            <div class="col-md-3 form-group">
              <label>Nama Item</label>
              <input type="text" name="nama_item" id="masterNama" class="form-control" required>
            </div>
            <div class="col-md-2 form-group">
              <label>Grup</label>
              <select name="grup_laporan" id="masterGrup" class="form-control" required>
                <option value="IPAL">IPAL</option>
                <option value="PROSES">PROSES</option>
                <option value="DAF_LAMA">DAF LAMA</option>
                <option value="DAF3_BARU">DAF 3 BARU</option>
                <option value="DAF2_BARU">DAF 2 BARU</option>
              </select>
            </div>
            <div class="col-md-2 form-group">
              <label>Satuan</label>
              <input type="text" name="satuan_pakai" id="masterSatuan" class="form-control" value="Kg" required>
            </div>
            <div class="col-md-2 form-group">
              <label>Aktif</label>
              <select name="aktif" id="masterAktif" class="form-control" required>
                <option value="1">Ya</option>
                <option value="0">Tidak</option>
              </select>
            </div>
          </div>
          <div class="d-flex" style="gap:8px;">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Simpan Master</button>
            <button type="button" class="btn btn-secondary btn-sm" id="btnResetFormMaster"><i class="fas fa-undo"></i> Reset Form</button>
          </div>
        </form>

        <div class="table-responsive">
          <div class="row mb-2">
            <div class="col-md-3">
              <label for="filterMasterGroup" class="mb-1">Filter Grup Master</label>
              <select id="filterMasterGroup" class="form-control form-control-sm">
                <option value="">Semua</option>
                <option value="IPAL">IPAL</option>
                <option value="PROSES">PROSES</option>
                <option value="DAF_LAMA">DAF LAMA</option>
                <option value="DAF3_BARU">DAF 3 BARU</option>
                <option value="DAF2_BARU">DAF 2 BARU</option>
              </select>
            </div>
          </div>
          <table class="table table-sm table-bordered text-center" id="masterKimiaTable">
            <thead class="thead-light">
              <tr>
                <th style="width:60px;">ID</th>
                <th>Kode</th>
                <th>Nama Item</th>
                <th style="width:130px;">Grup</th>
                <th style="width:90px;">Satuan</th>
                <th style="width:80px;">Aktif</th>
                <th style="width:100px;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr><td colspan="7">Memuat data master...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
      </div>
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

<style>
  #modalFormKimiaIpal .modal-body {
    max-height: calc(100vh - 230px);
    overflow-y: auto;
  }
  #modalFormKimiaIpal .modal-footer {
    position: sticky;
    bottom: 0;
    background: #fff;
    z-index: 2;
  }
</style>

<script>
$(function(){
  var groupLabels = {
    'IPAL': 'IPAL',
    'PROSES': 'PROSES',
    'DAF_LAMA': 'DAF LAMA',
    'DAF3_BARU': 'DAF 3 BARU',
    'DAF2_BARU': 'DAF 2 BARU'
  };

  function escapeHtml(str){
    return String(str || '')
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;')
      .replace(/'/g,'&#039;');
  }

  function parseNum(v){ if(v===null||v===undefined) return NaN; v=String(v).trim().replace(/,/g,''); if(v==='') return NaN; var n=Number(v); return isNaN(n)?NaN:n; }
  function fmt(v,d){ var n=parseNum(v); return isNaN(n)?'-':n.toLocaleString('en-US',{minimumFractionDigits:d,maximumFractionDigits:d}); }
  function fmtInput(v,d){ var n=parseNum(v); return isNaN(n)?'':n.toLocaleString('en-US',{minimumFractionDigits:d,maximumFractionDigits:d}); }

  var table = $('#kimiaIpalTable').DataTable({
    processing:true, serverSide:true,
    ajax:{
      url:'kimia_ipal_serverside.php',
      type:'POST',
      data:function(d){
        d.start_date=$('#filterStartDate').val();
        d.end_date=$('#filterEndDate').val();
        d.grup=$('#filterGroup').val();
      }
    },
    columns:[
      {data:null,orderable:false,searchable:false,render:function(d,t,r,m){return m.row+m.settings._iDisplayStart+1;}},
      {data:'tanggal_formatted'},
      {data:'nama_item'},
      {data:'grup_laporan'},
      {data:'pakai_kg',render:function(d){return d===null||d===''?'-':fmt(d,2);}},
      {data:'harga_rp',render:function(d){return d===null||d===''?'-':fmt(d,2);}},
      {data:'biaya_rp',render:function(d){return d===null||d===''?'-':fmt(d,2);}},
      {data:'created_by',render:function(d){return d||'-';}},
      {data:'aksi',orderable:false,searchable:false,render:function(d,t,r){
        if(!r || !r.id) return '-';
        return '<div class="btn-group btn-group-sm">'+
          '<button class="btn btn-info btn-detail" data-id="'+r.id+'"><i class="fas fa-eye"></i></button>'+
          '<button class="btn btn-warning btn-edit" data-id="'+r.id+'"><i class="fas fa-edit"></i></button>'+
          '<button class="btn btn-danger btn-delete" data-id="'+r.id+'"><i class="fas fa-trash"></i></button>'+
        '</div>';
      }}
    ],
    ordering:false, responsive:true,
    language:{ processing:'Memproses...', lengthMenu:'Tampilkan _MENU_ data per halaman', zeroRecords:'Tidak ada data ditemukan', info:'Menampilkan _START_ - _END_ dari _TOTAL_ data', infoEmpty:'Tidak ada data tersedia', infoFiltered:'(disaring dari _MAX_ total data)', search:'Cari:', paginate:{first:'Pertama',last:'Terakhir',next:'Selanjutnya',previous:'Sebelumnya'} }
  });

  $('#filterStartDate,#filterEndDate,#filterGroup').on('change', function(){ table.ajax.reload(); });
  $('#btnResetFilter').on('click', function(){
    $('#filterStartDate').val('');
    $('#filterEndDate').val('');
    $('#filterGroup').val('');
    table.search('').draw();
  });

  function buildPilihFormMaster(data){
    var activeItems = (data || []).filter(function(i){ return String(i.aktif) === '1'; });
    if (activeItems.length === 0) {
      $('#pilihFormBodyKimia').html('<div class="alert alert-warning mb-0">Master kimia belum tersedia. Tambahkan dari Setting Master.</div>');
      return;
    }

    var grouped = {};
    activeItems.forEach(function(i){
      var g = i.grup_laporan || 'LAINNYA';
      if (!grouped[g]) grouped[g] = [];
      grouped[g].push(i);
    });

    var order = ['IPAL','PROSES','DAF_LAMA','DAF3_BARU','DAF2_BARU'];
    var html = '';

    function appendGroup(grupKey, items){
      html += '<div class="mb-3">';
      html += '<h6 class="font-weight-bold mb-2">'+escapeHtml(groupLabels[grupKey] || grupKey)+'</h6>';
      html += '<div class="row">';
      items.forEach(function(it){
        html += '<div class="col-md-6 mb-2">'+
          '<button class="btn btn-block btn-outline-primary btnPilihItemKimia" '+
          'data-id="'+escapeHtml(it.id)+'" '+
          'data-name="'+escapeHtml(it.nama_item)+'" '+
          'data-group="'+escapeHtml(it.grup_laporan)+'" '+
          'data-satuan="'+escapeHtml(it.satuan_pakai)+'">'+
          escapeHtml(it.nama_item)+
          '</button>'+
        '</div>';
      });
      html += '</div></div>';
    }

    order.forEach(function(k){ if (grouped[k] && grouped[k].length) appendGroup(k, grouped[k]); });
    Object.keys(grouped).forEach(function(k){ if (order.indexOf(k) === -1) appendGroup(k, grouped[k]); });

    $('#pilihFormBodyKimia').html(html);
  }

  function loadPilihFormMaster(showAfterLoad){
    $('#pilihFormBodyKimia').html('<div class="text-muted">Memuat master kimia...</div>');
    $.getJSON('get_master_kimia_ipal.php', function(resp){
      if (!resp || !resp.success) {
        $('#pilihFormBodyKimia').html('<div class="alert alert-danger mb-0">'+((resp&&resp.message)?resp.message:'Gagal memuat master kimia.')+'</div>');
        if (showAfterLoad) $('#modalPilihFormKimiaIpal').modal('show');
        return;
      }
      buildPilihFormMaster(resp.data || []);
      if (showAfterLoad) $('#modalPilihFormKimiaIpal').modal('show');
    }).fail(function(){
      $('#pilihFormBodyKimia').html('<div class="alert alert-danger mb-0">Terjadi kesalahan saat memuat master kimia.</div>');
      if (showAfterLoad) $('#modalPilihFormKimiaIpal').modal('show');
    });
  }

  $('#btnTambahData').on('click', function(e){
    e.preventDefault();
    loadPilihFormMaster(true);
  });

  $('#btnTambahCatatan').on('click', function(e){
    e.preventDefault();
    $('#formTambahCatatanKimiaIpal')[0].reset();
    $('#kimiaCatatanMaster').html('<option value=\"\">Memuat master...</option>');
    $.getJSON('get_master_kimia_ipal.php', function(resp){
      if (!resp || !resp.success || !resp.data) {
        $('#kimiaCatatanMaster').html('<option value=\"\">Gagal memuat master</option>');
        return;
      }
      var opts = '<option value=\"\">Pilih parameter</option>';
      resp.data.forEach(function(i){
        if (String(i.aktif) !== '1') return;
        var label = (i.grup_laporan ? i.grup_laporan + ' - ' : '') + (i.nama_item || '');
        opts += '<option value=\"' + escapeHtml(i.id) + '\">' + escapeHtml(label) + '</option>';
      });
      $('#kimiaCatatanMaster').html(opts);
    }).fail(function(){
      $('#kimiaCatatanMaster').html('<option value=\"\">Gagal memuat master</option>');
    });
    $('#modalTambahCatatanKimiaIpal').modal('show');
  });

  function buildBatchTable(items, groupKey){
    var filtered = (items || []).filter(function(i){ return String(i.aktif) === '1' && String(i.grup_laporan || '') === String(groupKey); });
    if (filtered.length === 0) {
      $('#batchKimiaTable tbody').html('<tr><td colspan="7">Tidak ada master pada grup ini.</td></tr>');
      return;
    }
    var rows = '';
    filtered.forEach(function(i){
      rows += '<tr data-id="'+escapeHtml(i.id)+'">'+
        '<td>'+escapeHtml(i.id)+'</td>'+
        '<td class="text-left">'+escapeHtml(i.nama_item||'')+'</td>'+
        '<td>'+escapeHtml(i.satuan_pakai||'Kg')+'</td>'+
        '<td><input type="text" class="form-control form-control-sm text-right num-only batch-pakai" data-decimals="2" placeholder="0"></td>'+
        '<td class="position-relative">'+
          '<input type="text" class="form-control form-control-sm text-right num-only batch-harga" data-decimals="2" placeholder="0" autocomplete="off">'+
          '<div class="batch-harga-hist list-group position-absolute w-100" style="z-index:30;display:none;max-height:170px;overflow:auto;"></div>'+
        '</td>'+
        '<td><input type="text" class="form-control form-control-sm text-right batch-biaya" readonly></td>'+
        '<td><input type="text" class="form-control form-control-sm batch-catatan" placeholder="-"></td>'+
      '</tr>';
    });
    $('#batchKimiaTable tbody').html(rows);
  }

  var hargaHistoryCache = {};
  function renderHargaHistory($input, entries){
    var $box = $input.siblings('.batch-harga-hist');
    if (!$box.length) return;
    if (!entries || entries.length === 0) {
      $box.hide().empty();
      return;
    }
    var html = '';
    entries.forEach(function(it){
      var val = fmtInput(it.harga, 2);
      var tgl = it.tanggal ? ('<small class="text-muted ml-2">' + escapeHtml(it.tanggal) + '</small>') : '';
      html += '<button type="button" class="list-group-item list-group-item-action py-1 px-2 text-right batch-harga-pilih" data-value="' + escapeHtml(String(it.harga)) + '">' + escapeHtml(val) + tgl + '</button>';
    });
    $box.html(html).show();
  }

  function loadHargaHistory(masterId, cb){
    masterId = parseInt(masterId || 0, 10);
    if (!masterId) { cb([]); return; }
    if (hargaHistoryCache[masterId]) { cb(hargaHistoryCache[masterId]); return; }
    $.getJSON('get_harga_histori_kimia_ipal.php', { master_id: masterId }, function(resp){
      var data = (resp && resp.success && Array.isArray(resp.data)) ? resp.data : [];
      hargaHistoryCache[masterId] = data;
      cb(data);
    }).fail(function(){
      cb([]);
    });
  }

  function loadBatchMaster(groupKey){
    $('#batchKimiaTable tbody').html('<tr><td colspan="7">Memuat master...</td></tr>');
    $.getJSON('get_master_kimia_ipal.php', function(resp){
      if(!resp || !resp.success){
        $('#batchKimiaTable tbody').html('<tr><td colspan="7">Gagal memuat master.</td></tr>');
        return;
      }
      window.masterKimiaData = resp.data || [];
      buildBatchTable(window.masterKimiaData, groupKey);
    }).fail(function(){
      $('#batchKimiaTable tbody').html('<tr><td colspan="7">Terjadi kesalahan saat memuat master.</td></tr>');
    });
  }

  $('#btnTambahBatch').on('click', function(e){
    e.preventDefault();
    $('#batchTanggal').val(new Date().toISOString().slice(0,10));
    var g = $('#batchGroup').val() || 'PROSES';
    loadBatchMaster(g);
    $('#modalBatchKimiaIpal').modal('show');
  });

  $('#batchGroup').on('change', function(){
    var g = $(this).val() || 'PROSES';
    loadBatchMaster(g);
  });

  function recalcBatchRow($tr){
    var p = parseNum($tr.find('.batch-pakai').val());
    var h = parseNum($tr.find('.batch-harga').val());
    if (isNaN(p) || isNaN(h)) { $tr.find('.batch-biaya').val(''); return; }
    $tr.find('.batch-biaya').val(fmtInput((p*h), 2));
  }

  $(document).on('input', '.batch-pakai,.batch-harga', function(){
    var $tr = $(this).closest('tr');
    recalcBatchRow($tr);
  });

  $(document).on('focus click', '.batch-harga', function(){
    var $input = $(this);
    var masterId = $input.closest('tr').data('id');
    loadHargaHistory(masterId, function(entries){
      renderHargaHistory($input, entries);
    });
  });

  $(document).on('click', '.batch-harga-pilih', function(){
    var $btn = $(this);
    var $box = $btn.closest('.batch-harga-hist');
    var $input = $box.siblings('.batch-harga');
    var raw = parseNum($btn.data('value'));
    if (!isNaN(raw)) {
      $input.val(fmtInput(raw, 2));
      recalcBatchRow($input.closest('tr'));
    }
    $box.hide();
    $input.trigger('focus');
  });

  $(document).on('click', function(e){
    if ($(e.target).closest('.batch-harga, .batch-harga-hist').length === 0) {
      $('.batch-harga-hist').hide();
    }
  });

  $('#btnSimpanBatchKimia').on('click', function(){
    var tanggal = ($('#batchTanggal').val() || '').trim();
    if (!tanggal) { Swal.fire({icon:'warning',title:'Tanggal kosong',text:'Pilih tanggal dulu.'}); return; }

    var items = [];
    $('#batchKimiaTable tbody tr').each(function(){
      var id = $(this).data('id');
      if (!id) return;
      var p = parseNum($(this).find('.batch-pakai').val());
      var h = parseNum($(this).find('.batch-harga').val());
      var cat = ($(this).find('.batch-catatan').val() || '').trim();
      if (isNaN(p) || isNaN(h)) return;
      items.push({ master_id: id, pakai_kg: p, harga_rp: h, catatan: cat });
    });

    if (items.length === 0) {
      Swal.fire({icon:'info',title:'Tidak ada data',text:'Isi minimal satu item.'});
      return;
    }

    $.ajax({
      url: 'save_kimia_ipal_bulk.php',
      type: 'POST',
      dataType: 'json',
      data: { tanggal: tanggal, items: JSON.stringify(items) },
      success: function(resp){
        if (resp && resp.success){
          Swal.fire({icon:'success',title:'Sukses',text:resp.message||'Data tersimpan.'});
          $('#modalBatchKimiaIpal').modal('hide');
          table.ajax.reload(null,false);
        } else {
          Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal menyimpan data.'});
        }
      },
      error: function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan.'}); }
    });
  });

  $('#formTambahCatatanKimiaIpal').on('submit', function(e){
    e.preventDefault();
    $.ajax({
      url: 'save_catatan_kimia_ipal.php',
      type: 'POST',
      data: $(this).serialize(),
      dataType: 'json',
      success: function(resp){
        if (resp && resp.success){
          $('#modalTambahCatatanKimiaIpal').modal('hide');
          Swal.fire({icon:'success',title:'Sukses',text:resp.message||'Catatan tersimpan.'});
        } else {
          Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal menyimpan catatan.'});
        }
      },
      error: function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan saat menyimpan catatan.'}); }
    });
  });

  function resetMasterForm() {
    $('#masterId').val('');
    $('#masterKode').val('');
    $('#masterNama').val('');
    $('#masterGrup').val('IPAL');
    $('#masterSatuan').val('Kg');
    $('#masterAktif').val('1');
  }

  function loadMasterTable() {
    $('#masterKimiaTable tbody').html('<tr><td colspan="7">Memuat data master...</td></tr>');
    $.getJSON('get_master_kimia_ipal.php', function(resp){
      if(!resp || !resp.success){
        $('#masterKimiaTable tbody').html('<tr><td colspan="7">'+((resp&&resp.message)?resp.message:'Gagal memuat master')+'</td></tr>');
        return;
      }
      if(!resp.data || resp.data.length===0){
        $('#masterKimiaTable tbody').html('<tr><td colspan="7">Belum ada data master.</td></tr>');
        return;
      }
      var rows = '';
      var selectedGroup = $('#filterMasterGroup').val() || '';
      var filtered = resp.data.filter(function(i){
        if (!selectedGroup) return true;
        return (i.grup_laporan || '') === selectedGroup;
      });
      if (filtered.length === 0) {
        $('#masterKimiaTable tbody').html('<tr><td colspan="7">Tidak ada master pada grup ini.</td></tr>');
        window.masterKimiaData = resp.data;
        return;
      }
      filtered.forEach(function(i){
        rows += '<tr>'+
          '<td>'+(i.id||'')+'</td>'+
          '<td>'+(i.kode||'')+'</td>'+
          '<td class="text-left">'+(i.nama_item||'')+'</td>'+
          '<td>'+(i.grup_laporan||'')+'</td>'+
          '<td>'+(i.satuan_pakai||'')+'</td>'+
          '<td>'+(String(i.aktif)==='1'?'Ya':'Tidak')+'</td>'+
          '<td><div class="btn-group btn-group-sm">'+
          '<button class="btn btn-warning btn-edit-master" data-id="'+i.id+'" title="Edit"><i class="fas fa-edit"></i></button>'+
          '<button class="btn btn-danger btn-delete-master" data-id="'+i.id+'" title="Delete"><i class="fas fa-trash"></i></button>'+
          '</div></td>'+
        '</tr>';
      });
      $('#masterKimiaTable tbody').html(rows);
      window.masterKimiaData = resp.data;
    }).fail(function(){
      $('#masterKimiaTable tbody').html('<tr><td colspan="7">Terjadi kesalahan saat memuat master.</td></tr>');
    });
  }

  $('#btnSettingMaster').on('click', function(e){
    e.preventDefault();
    resetMasterForm();
    $('#filterMasterGroup').val('');
    loadMasterTable();
    $('#modalSettingMasterKimia').modal('show');
  });

  $('#filterMasterGroup').on('change', function(){
    loadMasterTable();
  });

  $('#btnResetFormMaster').on('click', function(){ resetMasterForm(); });

  $('#formMasterKimia').on('submit', function(e){
    e.preventDefault();
    $.ajax({
      url:'save_master_kimia_ipal.php',
      type:'POST',
      data:$(this).serialize(),
      dataType:'json',
      success:function(resp){
        if(resp && resp.success){
          Swal.fire({icon:'success',title:'Sukses',text:resp.message||'Master berhasil disimpan.'});
          resetMasterForm();
          loadMasterTable();
          loadPilihFormMaster(false);
        } else {
          Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal menyimpan master.'});
        }
      },
      error:function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan.'}); }
    });
  });

  $(document).on('click', '.btn-edit-master', function(){
    var id = $(this).data('id');
    if(!window.masterKimiaData) return;
    var found = null;
    for (var i = 0; i < window.masterKimiaData.length; i++) {
      if (String(window.masterKimiaData[i].id) === String(id)) { found = window.masterKimiaData[i]; break; }
    }
    if(!found) return;
    $('#masterId').val(found.id || '');
    $('#masterKode').val(found.kode || '');
    $('#masterNama').val(found.nama_item || '');
    $('#masterGrup').val(found.grup_laporan || 'IPAL');
    $('#masterSatuan').val(found.satuan_pakai || 'Kg');
    $('#masterAktif').val(String(found.aktif || '1'));
    $('#masterKode').trigger('focus');
  });

  $(document).on('click', '.btn-delete-master', function(){
    var id = $(this).data('id');
    if(!id) return;
    Swal.fire({
      title:'Hapus Master?',
      text:'Master yang sudah dipakai transaksi tidak bisa dihapus.',
      icon:'warning',
      showCancelButton:true,
      confirmButtonColor:'#d33',
      cancelButtonColor:'#3085d6',
      confirmButtonText:'Ya, Hapus!',
      cancelButtonText:'Batal'
    }).then(function(r){
      if(!r.isConfirmed) return;
      $.ajax({
        url:'delete_master_kimia_ipal.php',
        type:'POST',
        data:{ id:id },
        dataType:'json',
        success:function(resp){
          if(resp && resp.success){
            Swal.fire({icon:'success',title:'Sukses',text:resp.message||'Master berhasil dihapus.'});
            resetMasterForm();
            loadMasterTable();
            loadPilihFormMaster(false);
          } else {
            Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal menghapus master.'});
          }
        },
        error:function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan.'}); }
      });
    });
  });

  $(document).on('click', '.btnPilihItemKimia', function(){
    var id = $(this).data('id');
    var name = $(this).data('name') || '';
    var group = $(this).data('group') || '';
    var satuan = $(this).data('satuan') || 'Kg';
    $('#kimiaMasterId').val(id);
    $('#kimiaParameterName').val(name);
    $('#kimiaGroupName').val(group);
    $('#kimiaSatuanLabel').text(satuan);
    $('#kimiaFormTitle').text('Input ' + name);
    $('#formKimiaIpal')[0].reset();
    $('#kimiaMasterId').val(id);
    $('#kimiaParameterName').val(name);
    $('#kimiaGroupName').val(group);
    $('#kimiaSatuanLabel').text(satuan);
    $('#modalPilihFormKimiaIpal').modal('hide');
    $('#modalFormKimiaIpal').modal('show');
  });

  $(document).on('input','.num-only', function(){ this.value=this.value.replace(/[^0-9.,]/g,''); });
  $(document).on('blur','.num-only', function(){
    var decimals = parseInt($(this).data('decimals') || 2, 10);
    var n = parseNum(this.value);
    if (!isNaN(n)) this.value = fmtInput(n, decimals);
  });

  function recalcBiaya(){
    var p = parseNum($('#kimiaPakai').val());
    var h = parseNum($('#kimiaHarga').val());
    if (isNaN(p) || isNaN(h)) { $('#kimiaBiaya').val(''); return; }
    $('#kimiaBiaya').val(fmtInput((p*h), 2));
  }
  $('#kimiaPakai,#kimiaHarga').on('input blur', recalcBiaya);

  $('#formKimiaIpal').on('submit', function(e){
    e.preventDefault();
    $.ajax({
      url:'save_kimia_ipal.php',
      type:'POST',
      data:$(this).serialize(),
      dataType:'json',
      success:function(resp){
        if(resp && resp.success){
          $('#modalFormKimiaIpal').modal('hide');
          Swal.fire({icon:'success',title:'Sukses',text:resp.message||'Data berhasil disimpan.'});
          table.ajax.reload(null,false);
        } else {
          Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal menyimpan data.'});
        }
      },
      error:function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan.'}); }
    });
  });

  $(document).on('click','.btn-detail',function(){
    var id=$(this).data('id');
    if(id) window.location.href='view_kimia_ipal.php?id='+encodeURIComponent(id);
  });
  $(document).on('click','.btn-edit',function(){
    var id=$(this).data('id');
    if(id) window.location.href='edit_kimia_ipal.php?id='+encodeURIComponent(id);
  });
  $(document).on('click','.btn-delete',function(){
    var id=$(this).data('id');
    if(!id) return;
    Swal.fire({title:'Hapus Data?',text:'Data akan dihapus permanen.',icon:'warning',showCancelButton:true,confirmButtonColor:'#d33',cancelButtonColor:'#3085d6',confirmButtonText:'Ya, Hapus!',cancelButtonText:'Batal'})
      .then(function(r){ if(r.isConfirmed){ window.location.href='delete_kimia_ipal.php?id='+encodeURIComponent(id); }});
  });
});
</script>
