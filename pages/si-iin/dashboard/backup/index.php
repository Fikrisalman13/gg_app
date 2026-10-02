<?php
// gg_app/pages/si-iin/index.php
session_start(); ob_start();
date_default_timezone_set('Asia/Jakarta');
// (opsional) setting global app
require_once __DIR__ . '/../../../koneksi.php';
// koneksi + migrasi
require_once __DIR__ . '/../_shared/siin_lib.php';



// layout
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';

// auth minimal
if (!isset($_SESSION['UserName'])) {
  $_SESSION['error'] = "Silakan login terlebih dahulu!";  
  header('Location: /login.php'); exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';

// Hitung KPI cepat
$totalProduk = (int)$pdo->query("SELECT COUNT(*) FROM siin_product")->fetchColumn();
$totalStok   = (int)$pdo->query("SELECT COALESCE(SUM(stock),0) FROM siin_product")->fetchColumn();
// Low stock default ambang 5
$lowThreshold = 5;
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Dashboard Stok – SI-IIN</title>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/css/bootstrap-datepicker.min.css">
  <style>
    .kpi-card .info-box { border-radius: 12px; }
    .kpi-card .info-box-icon { border-radius: 12px 0 0 12px; }
    .filter-box{background:#f8f9fa;padding:12px;border-radius:6px;margin-bottom:14px}
    .table-sm th,.table-sm td{vertical-align:middle!important}
    table thead th { text-align: center !important; }
    table thead th.text-right { text-align: right !important; }
    /* Make ledger more compact: reduce padding and tighten numeric columns */
    #tblLedger th, #tblLedger td { padding: 0.18rem 0.35rem !important; vertical-align: middle !important; font-size: 0.88rem; }
    /* Kolom Qty In/Out (kuning) - narrow + center */
    #tblLedger th:nth-child(4), #tblLedger td:nth-child(4),
    #tblLedger th:nth-child(5), #tblLedger td:nth-child(5) {
      width: 80px; max-width: 90px; text-align: center !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    /* Kolom Saldo (ungu) - compact and right-aligned */
    #tblLedger th:nth-child(6), #tblLedger td:nth-child(6) {
      width: 90px; max-width: 110px; text-align: right !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    /* Make some longer text columns clamp with ellipsis to avoid widening */
    #tblLedger td:nth-child(2), #tblLedger td:nth-child(3), #tblLedger td:nth-child(9) {
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px;
    }
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid d-flex justify-content-between align-items-center">
      <h1 class="m-0">Dashboard Stok</h1>
      <ol class="breadcrumb float-sm-right">
        <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
        <li class="breadcrumb-item active">Dashboard Stok</li>
      </ol>
    </div>
  </div>

  <section class="content"><div class="container-fluid">

    <!-- KPI -->
    <div class="row kpi-card">
      <div class="col-md-4">
        <div class="info-box">
          <span class="info-box-icon bg-<?= htmlspecialchars($themeColor) ?>"><i class="fas fa-boxes"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Total Produk</span>
            <span class="info-box-number"><?= number_format($totalProduk) ?></span>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="info-box">
          <span class="info-box-icon bg-success"><i class="fas fa-cubes"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Total Stok</span>
            <span class="info-box-number"><?= number_format($totalStok) ?></span>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="info-box">
          <span class="info-box-icon bg-warning"><i class="fas fa-exclamation-triangle"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Low Stock (&lt; <span id="displayLowThreshold">5</span>)</span>
            <span class="info-box-number" id="displayLowCount">0</span>
          </div>
        </div>
      </div>
    </div>

    <!-- STOK PER PRODUK -->
    <div class="card">
      <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <div class="d-flex justify-content-between align-items-center">
          <h3 class="card-title mb-0">Stok per Produk</h3>
          <button class="btn btn-light btn-sm" id="btnAddProduct"><i class="fas fa-plus"></i> Tambah Produk</button>
        </div>
      </div>
      <div class="card-body">
        <div class="filter-box">
          <div class="form-inline">
            <label class="mr-2">Filter</label>
            <input type="text" id="qProduk" class="form-control form-control-sm mr-2" placeholder="Kode/Nama/Kategori/UOM">
            <label class="mr-2">Low Stock &lt;</label>
            <input type="number" id="lowThreshold" class="form-control form-control-sm mr-2" style="width:100px" min="0" value="<?= (int)$lowThreshold ?>">
            <div class="form-check form-check-inline mr-3">
              <input class="form-check-input" type="checkbox" id="onlyLow">
              <label class="form-check-label" for="onlyLow">Hanya Low Stock</label>
            </div>
            <button class="btn btn-primary btn-sm mr-2" id="btnFilterStock"><i class="fas fa-filter"></i> Terapkan</button>
            <button class="btn btn-secondary btn-sm" id="btnResetStock"><i class="fas fa-sync"></i> Reset</button>
          </div>
        </div>
        <div class="table-responsive">
          <table id="tblStock" class="table table-sm table-striped table-bordered" style="width:100%">
            <thead class="thead-light">
              <tr>
                <th>No</th>
                <th>Kode</th>
                <th>Nama</th>
                <th>Kategori</th>
                <th>UOM</th>
                <th>Stok</th>
                <th>Aksi</th>
              </tr>
            </thead><tbody></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- LOG / LEDGER -->
    <div class="card">
      <div class="card-header bg-dark text-white">
        <h3 class="card-title">Log Pergerakan Stok (Ledger)</h3>
      </div>
      <div class="card-body">
        <div class="filter-box">
          <div class="form-inline">
            <label class="mr-2">Tanggal</label>
            <input type="text" id="ledStart" class="form-control form-control-sm datepicker mr-2" placeholder="dd-mm-yyyy">
            <input type="text" id="ledEnd"   class="form-control form-control-sm datepicker mr-2" placeholder="dd-mm-yyyy">
            <label class="mr-2">Produk</label>
            <input type="text" id="ledQ" class="form-control form-control-sm mr-2" placeholder="Kode/Nama">
            <button class="btn btn-primary btn-sm mr-2" id="btnFilterLedger"><i class="fas fa-filter"></i> Terapkan</button>
            <button class="btn btn-secondary btn-sm" id="btnResetLedger"><i class="fas fa-sync"></i> Reset</button>
          </div>
        </div>
        <div class="table-responsive">
          <table id="tblLedger" class="table table-sm table-bordered" style="width:100%">
            <thead class="thead-light"><tr>
              <th>No</th>
              <th>Waktu</th>
              <th>Produk</th>
              <th>Qty In</th>
              <th>Qty Out</th>
              <th>Saldo</th>
              <th>Ref</th>
              <th>User</th>
              <th>Catatan</th>
            </tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>

  </div></section>
</div>

<!-- MODAL TAMBAH/EDIT PRODUK -->
<div class="modal fade" id="modalProduct" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form id="frmProduct" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalProductTitle">Tambah Produk</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="prod_id">
        <div class="form-group">
          <label>Kode</label>
          <input type="text" class="form-control" name="prod_code" id="prod_code" required maxlength="50">
        </div>
        <div class="form-group">
          <label>Nama</label>
          <input type="text" class="form-control" name="prod_name" id="prod_name" required maxlength="100">
        </div>

        <div class="form-group">
          <label>Kategori</label>
          <select class="form-control" id="category_id" name="category_id">
            <option value="">— pilih kategori —</option>
          </select>
          <small class="form-text text-muted">Atau isi baru di bawah ini</small>
          <input type="text" class="form-control mt-1" id="category_new" name="category_new" placeholder="Kategori baru (opsional)">
        </div>

        <div class="form-group">
          <label>UOM</label>
          <select class="form-control" id="uom_id" name="uom_id">
            <option value="">— pilih UOM —</option>
          </select>
          <small class="form-text text-muted">Atau isi baru di bawah ini</small>
          <input type="text" class="form-control mt-1" id="uom_new" name="uom_new" placeholder="UOM baru (opsional), mis: PCS/SET">
        </div>

        <div class="alert alert-light border">
          <i class="fas fa-info-circle"></i> Saat tambah produk, <b>stok diset 0</b> secara otomatis.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
        <button type="submit" class="btn btn-primary" id="btnSaveProduct"><i class="fas fa-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>

<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/js/datatables/dataTables.bootstrap5.min.js"></script>
<script src="/gg_app/plugins/js/bootstrap-datepicker.min.js"></script>
<script src="/gg_app/plugins/js/bootstrap-datepicker.id.min.js"></script>

<script>
(function(){
  $('.datepicker').datepicker({format:'dd-mm-yyyy',language:'id',autoclose:true,todayHighlight:true});

  function loadOptions(){
    return $.getJSON('product_api.php', {action:'options'}).then(function(res){
      var $cat=$('#category_id'), $uom=$('#uom_id');
      $cat.empty().append('<option value="">— pilih kategori —</option>');
      $uom.empty().append('<option value="">— pilih UOM —</option>');
      (res.categories||[]).forEach(function(x){ $cat.append('<option value="'+x.id+'">'+x.name+'</option>'); });
      (res.uoms||[]).forEach(function(x){ $uom.append('<option value="'+x.id+'">'+x.uomname+'</option>'); });
    });
  }

  // STOCK TABLE
  var tblStock = $('#tblStock').DataTable({
    serverSide:true, processing:true, responsive:true, autoWidth:false, pageLength:10,
    ajax:{
      url:'stock_serverside.php', type:'POST',
      data:function(d){
        d.q = $('#qProduk').val()||'';
        d.only_low = $('#onlyLow').is(':checked')?1:0;
        d.low_threshold = $('#lowThreshold').val()||'5';
      }
    },
    order:[[5,'asc']],
    columnDefs:[
      {targets:[0,1,2,3,4,5,6], className:'text-center'}
    ],
    columns:[
      {data:null, orderable:false, searchable:false, render:(d,t,r,m)=> m.row+1+m.settings._iDisplayStart},
      {data:'prod_code'},
      {data:'prod_name'},
      {data:'category'},
      {data:'uom'},
      {data:'stock'},
      {data:null, orderable:false, searchable:false, render:function(d){
        return '<button class="btn btn-sm btn-info btn-edit" data-id="'+d.id+'"><i class="fas fa-edit"></i></button>';
      }}
    ]
  });
  function updateLowStockDisplay(){
    var threshold = parseInt($('#lowThreshold').val()) || 5;
    $('#displayLowThreshold').text(threshold);
    $.get('stock_serverside.php', {only_low:1, low_threshold:threshold, q:'', draw:1, start:0, length:1}, function(res){
      var count = res && res.recordsFiltered ? res.recordsFiltered : 0;
      $('#displayLowCount').text(count);
    }, 'json');
  }
  $('#btnFilterStock').on('click', function(){
    updateLowStockDisplay();
    tblStock.ajax.reload();
  });
  $('#btnResetStock').on('click', function(){
    $('#qProduk').val(''); $('#onlyLow').prop('checked', false); $('#lowThreshold').val('5');
    updateLowStockDisplay();
    tblStock.ajax.reload();
  });
  updateLowStockDisplay();

  // LEDGER TABLE
  var tblLedger = $('#tblLedger').DataTable({
    serverSide:true, processing:true, responsive:true, autoWidth:false, pageLength:10,
    ajax:{
      url:'ledger_serverside.php', type:'POST',
      data:function(d){
        d.start_date = $('#ledStart').val()||'';
        d.end_date   = $('#ledEnd').val()||'';
        d.q          = $('#ledQ').val()||'';
      }
    },
    order:[[1,'desc']],
    columnDefs:[
      {targets:[0,1,2,3,4,5,6,7,8], className:'text-center'},
      {targets:[3,4,5], className:'text-center text-right'}
    ],
    columns:[
      {data:null, orderable:false, searchable:false, render:(d,t,r,m)=> m.row+1+m.settings._iDisplayStart},
      {data:'trx_time'},
      {data:'product'},
      {data:'qty_in'},
      {data:'qty_out'},
      {data:'balance_after'},
      {data:'ref'},
      {data:'upduser'},
      {data:'note'}
    ]
  });
  $('#btnFilterLedger').on('click', ()=>tblLedger.ajax.reload());
  $('#btnResetLedger').on('click', function(){
    $('#ledStart,#ledEnd,#ledQ').val(''); tblLedger.ajax.reload();
  });

  // OPEN ADD MODAL
  $('#btnAddProduct').on('click', function(){
    $('#modalProductTitle').text('Tambah Produk');
    $('#frmProduct')[0].reset();
    $('#prod_id').val('');
    loadOptions().then(()=> $('#modalProduct').modal('show'));
  });

  // OPEN EDIT MODAL
  $('#tblStock').on('click','.btn-edit', function(){
    var id=$(this).data('id');
    $('#modalProductTitle').text('Edit Produk');
    $('#frmProduct')[0].reset();
    $('#prod_id').val(id);
    $.getJSON('product_api.php', {action:'get', id:id}).then(function(res){
      return loadOptions().then(function(){
        $('#prod_code').val(res.prod_code||'');
        $('#prod_name').val(res.prod_name||'');
        $('#category_id').val(res.category_id||'');
        $('#uom_id').val(res.uom_id||'');
        $('#category_new').val(''); $('#uom_new').val('');
        $('#modalProduct').modal('show');
      });
    }).fail(function(xhr){
      alert('Gagal ambil data produk: '+xhr.responseText);
    });
  });

  // SUBMIT FORM (ADD/EDIT)
  $('#frmProduct').on('submit', function(e){
    e.preventDefault();
    var formData=$(this).serializeArray();
    var hasId = $('#prod_id').val() ? true : false;
    formData.push({name:'action', value: hasId ? 'update' : 'create'});

    $('#btnSaveProduct').prop('disabled',true);
    $.post('product_api.php', formData, function(res){
      $('#btnSaveProduct').prop('disabled',false);
      $('#modalProduct').modal('hide');
      tblStock.ajax.reload(null,false);
    },'json').fail(function(xhr){
      $('#btnSaveProduct').prop('disabled',false);
      alert('Gagal simpan: '+ (xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : xhr.responseText));
    });
  });

})();
</script>
</body>
</html>
<?php ob_end_flush(); ?>
