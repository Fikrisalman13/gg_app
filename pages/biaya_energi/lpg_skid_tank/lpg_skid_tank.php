<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$menuId = 236; // TODO: ganti MenuId lpg_skid_tank yang benar
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: /gg_app/index.php');
    exit;
}

$tanks = [];
$stTank = sqlsrv_query($conn, "SELECT kode, nama, urutan FROM dbo.lpg_skid_tank_master WHERE is_active=1 ORDER BY urutan, kode");
if ($stTank) {
    $idx = 1;
    while ($r = sqlsrv_fetch_array($stTank, SQLSRV_FETCH_ASSOC)) {
        $urutan = isset($r['urutan']) ? (int)$r['urutan'] : $idx;
        if ($urutan <= 0) $urutan = $idx;
        $r['urutan'] = $urutan;
        $r['display_label'] = 'Pencatatan Pemakaian ' . $urutan;
        $tanks[] = $r;
        $idx++;
    }
    sqlsrv_free_stmt($stTank);
}

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
          <h1>LPG Skid Tank</h1>
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
          <h3 class="card-title"><i class="fas fa-list mr-1"></i> List Data LPG Skid Tank</h3>
          <div class="d-flex ml-auto" style="gap:8px;">
            <a href="report_lpg_skid_tank.php" class="btn btn-info btn-sm"><i class="fas fa-file-alt"></i> Detail Report</a>
            <?php if (!empty($permissions['CanAdd']) && $permissions['CanAdd'] == 1): ?>
              <a href="#" class="btn btn-primary btn-sm" id="btnTambahBatch"><i class="fas fa-th"></i> Input Banyak</a>
              <a href="#" class="btn btn-success btn-sm" id="btnTambahData"><i class="fas fa-plus"></i> Input Satuan</a>
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
          <table id="lpgTable" class="table table-hover table-sm nowrap text-center" style="width:100%">
            <thead class="thead-light">
              <tr>
                <th>No</th><th>Tanggal</th><th>Tank</th><th>Pemakaian (Kg)</th><th>Harga (Rp)</th><th>Biaya (Rp/hari)</th><th>Ket</th><th>Created By</th><th>Aksi</th>
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

<div class="modal fade" id="modalBatchLpg" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title">Input Banyak LPG Skid Tank</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="row mb-2">
          <div class="col-md-3">
            <label>Tanggal</label>
            <input type="date" id="batchTanggal" class="form-control" required>
          </div>
          <div class="col-md-9 d-flex align-items-end">
            <div class="text-muted small">Isi baris yang dipakai. Biaya otomatis = Pakai x Harga.</div>
          </div>
        </div>
        <div class="table-responsive" style="max-height:60vh;overflow:auto;">
          <table class="table table-sm table-bordered text-center" id="batchLpgTable">
            <thead class="thead-light">
              <tr>
                <th style="width:60px;">ID</th>
                <th>Tank</th>
                <th style="width:140px;">Pakai (Kg)</th>
                <th style="width:170px;">Harga (Rp)</th>
                <th style="width:170px;">Biaya (Rp/hari)</th>
                <th style="width:220px;">KET</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($tanks)): ?>
                <tr><td colspan="6">Master tank belum ada.</td></tr>
              <?php else: ?>
                <?php $no=1; foreach ($tanks as $t): ?>
                  <tr data-kode="<?= htmlspecialchars($t['kode']) ?>">
                    <td><?= $no++ ?></td>
                    <td class="text-left"><?= htmlspecialchars($t['display_label']) ?><br><small class="text-muted"><?= htmlspecialchars($t['nama']) ?></small></td>
                    <td><input type="text" class="form-control form-control-sm text-right num-only b-pakai" value="0"></td>
                    <td class="position-relative">
                      <input type="text" class="form-control form-control-sm text-right num-only b-harga" value="0" autocomplete="off">
                      <div class="b-harga-hist list-group position-absolute w-100" style="z-index:30;display:none;max-height:170px;overflow:auto;"></div>
                    </td>
                    <td><input type="text" class="form-control form-control-sm text-right b-biaya bg-light" value="0" readonly></td>
                    <td><input type="text" class="form-control form-control-sm b-ket" placeholder="-"></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="btnSimpanBatchLpg"><i class="fas fa-save"></i> Simpan Semua</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalTambahLpg" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><h5 class="modal-title">Input LPG Skid Tank (Satuan)</h5><button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button></div>
      <form id="formLpg" autocomplete="off">
        <div class="modal-body">
          <div class="form-group"><label>Tanggal</label><input type="date" name="tanggal" id="xTanggal" class="form-control" required></div>
          <div class="form-group"><label>Tank</label><select name="tank_kode" id="xTankKode" class="form-control" required><option value="">- Pilih Tank -</option><?php foreach ($tanks as $t): ?><option value="<?= htmlspecialchars($t['kode']) ?>"><?= htmlspecialchars($t['display_label']) ?> - <?= htmlspecialchars($t['nama']) ?></option><?php endforeach; ?></select></div>
          <div class="form-group"><label>Pemakaian (Kg)</label><input type="text" name="pemakaian_kg" id="xPakai" class="form-control num-only" required></div>
          <div class="form-group position-relative"><label>Harga (Rp)</label><input type="text" name="harga_rp" id="xHarga" class="form-control num-only" autocomplete="off" required><div id="xHargaHist" class="list-group position-absolute w-100" style="z-index:30;display:none;max-height:180px;overflow:auto;"></div></div>
          <div class="form-group"><label>Biaya (Rp/hari)</label><input type="text" id="xBiaya" class="form-control bg-light" readonly></div>
          <div class="form-group mb-0"><label>Keterangan (KET)</label><input type="text" name="ket" class="form-control"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan</button></div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalTambahCatatanLpg" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><h5 class="modal-title">Tambah Catatan LPG Skid Tank</h5><button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button></div>
      <form id="formCatatanLpg" autocomplete="off">
        <div class="modal-body">
          <div class="form-group"><label>Tanggal</label><input type="date" name="tanggal" id="xCatatanTanggal" class="form-control" required></div>
          <div class="form-group"><label>Tank</label><select name="tank_kode" id="xCatatanTankKode" class="form-control" required><option value="">- Pilih Tank -</option><?php foreach ($tanks as $t): ?><option value="<?= htmlspecialchars($t['kode']) ?>"><?= htmlspecialchars($t['display_label']) ?> - <?= htmlspecialchars($t['nama']) ?></option><?php endforeach; ?></select></div>
          <div class="form-group mb-0"><label>Catatan</label><textarea name="note" id="xCatatanText" class="form-control" rows="4" required></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan</button></div>
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
  function parseNum(v){ if(v===null||v===undefined) return NaN; v=String(v).trim().replace(/,/g,''); if(v==='') return NaN; var n=Number(v); return isNaN(n)?NaN:n; }
  function fmt(v,d){ var n=parseNum(v); return isNaN(n)?'-':n.toLocaleString('en-US',{minimumFractionDigits:d,maximumFractionDigits:d}); }
  function fmtInput(v,d){ var n=parseNum(v); return isNaN(n)?'':n.toLocaleString('en-US',{minimumFractionDigits:d,maximumFractionDigits:d}); }
  var hargaHistCache = null;

  function recalc(){
    var p=parseNum($('#xPakai').val()), h=parseNum($('#xHarga').val());
    var biaya = (!isNaN(p)&&!isNaN(h)) ? (p*h) : NaN;
    $('#xBiaya').val(!isNaN(biaya) ? fmtInput(biaya,2) : '');
  }

  function recalcBatchRow($tr){
    var p=parseNum($tr.find('.b-pakai').val()), h=parseNum($tr.find('.b-harga').val());
    var b=(!isNaN(p)&&!isNaN(h)) ? (p*h) : 0;
    $tr.find('.b-biaya').val(fmtInput(b,2));
  }

  function renderHargaHist(entries){
    var $box = $('#xHargaHist');
    if (!entries || entries.length === 0) { $box.hide().empty(); return; }
    var html = '';
    entries.forEach(function(it){
      var val = fmtInput(it.harga, 2);
      var tgl = it.tanggal ? ('<small class="text-muted ml-2">' + $('<div>').text(it.tanggal).html() + '</small>') : '';
      html += '<button type="button" class="list-group-item list-group-item-action py-1 px-2 text-right x-harga-pilih" data-value="' + $('<div>').text(String(it.harga)).html() + '">' + $('<div>').text(val).html() + tgl + '</button>';
    });
    $box.html(html).show();
  }

  function renderHargaHistBatch($input, entries){
    var $box = $input.siblings('.b-harga-hist');
    if (!$box.length) return;
    if (!entries || entries.length === 0) { $box.hide().empty(); return; }
    var html = '';
    entries.forEach(function(it){
      var val = fmtInput(it.harga, 2);
      var tgl = it.tanggal ? ('<small class="text-muted ml-2">' + $('<div>').text(it.tanggal).html() + '</small>') : '';
      html += '<button type="button" class="list-group-item list-group-item-action py-1 px-2 text-right b-harga-pilih" data-value="' + $('<div>').text(String(it.harga)).html() + '">' + $('<div>').text(val).html() + tgl + '</button>';
    });
    $box.html(html).show();
  }

  function loadHargaHist(cb){
    if (Array.isArray(hargaHistCache)) { cb(hargaHistCache); return; }
    $.getJSON('get_harga_histori_lpg_skid_tank.php', function(resp){
      hargaHistCache = (resp && resp.success && Array.isArray(resp.data)) ? resp.data : [];
      cb(hargaHistCache);
    }).fail(function(){ cb([]); });
  }

  var table = $('#lpgTable').DataTable({
    processing:true, serverSide:true, ordering:false, responsive:true,
    ajax:{ url:'lpg_skid_tank_serverside.php', type:'POST', data:function(d){ d.start_date=$('#filterStartDate').val(); d.end_date=$('#filterEndDate').val(); }},
    columns:[
      {data:null,render:function(d,t,r,m){return m.row+m.settings._iDisplayStart+1;}},
      {data:'tanggal_formatted'},
      {data:'tank_label',render:function(d,t,row){ var n=row.tank_nama||''; if(!n) return d||'-'; return (d||'-') + '<br><small class=\"text-muted\">'+$('<div>').text(n).html()+'</small>'; }},
      {data:'pemakaian_kg',render:function(d){return fmt(d,2)}},{data:'harga_rp',render:function(d){return fmt(d,2)}},
      {data:'biaya_rp',render:function(d){return fmt(d,2)}},{data:'ket',render:function(d){return d||'-';}},{data:'created_by',render:function(d){return d||'-';}},
      {data:'id',render:function(id){ if(!id) return '-'; return '<div class="btn-group btn-group-sm">'+
        '<button class="btn btn-info btn-detail" data-id="'+id+'"><i class="fas fa-eye"></i></button>'+
        '<button class="btn btn-warning btn-edit" data-id="'+id+'"><i class="fas fa-edit"></i></button>'+
        '<button class="btn btn-danger btn-delete" data-id="'+id+'"><i class="fas fa-trash"></i></button></div>'; }}
    ]
  });

  $('#filterStartDate,#filterEndDate').on('change', function(){ table.ajax.reload(); });
  $('#btnResetFilter').on('click', function(){ $('#filterStartDate,#filterEndDate').val(''); table.search('').draw(); });

  $('#btnTambahBatch').on('click', function(e){
    e.preventDefault();
    $('#batchTanggal').val(new Date().toISOString().slice(0,10));
    $('#batchLpgTable tbody tr').each(function(){
      $(this).find('.b-pakai').val('0');
      $(this).find('.b-harga').val('0');
      $(this).find('.b-ket').val('');
      recalcBatchRow($(this));
    });
    $('#modalBatchLpg').modal('show');
  });

  $('#btnTambahData').on('click', function(e){ e.preventDefault(); $('#formLpg')[0].reset(); $('#xTanggal').val(new Date().toISOString().slice(0,10)); $('#modalTambahLpg').modal('show'); });
  $('#btnTambahCatatan').on('click', function(e){ e.preventDefault(); $('#formCatatanLpg')[0].reset(); $('#xCatatanTanggal').val(new Date().toISOString().slice(0,10)); $('#modalTambahCatatanLpg').modal('show'); });

  $(document).on('input','.num-only',function(){ this.value=this.value.replace(/[^0-9.,]/g,''); });
  $('#xPakai,#xHarga').on('input blur', recalc);
  $('#batchLpgTable').on('input blur', '.b-pakai,.b-harga', function(){ recalcBatchRow($(this).closest('tr')); });

  $('#xHarga').on('focus click', function(){ loadHargaHist(renderHargaHist); });
  $(document).on('click', '.x-harga-pilih', function(){ var n=parseNum($(this).data('value')); if(!isNaN(n)){ $('#xHarga').val(fmtInput(n,2)); recalc(); } $('#xHargaHist').hide(); });
  $(document).on('focus click', '.b-harga', function(){
    var $input = $(this);
    loadHargaHist(function(entries){ renderHargaHistBatch($input, entries); });
  });
  $(document).on('click', '.b-harga-pilih', function(){
    var $btn = $(this);
    var $box = $btn.closest('.b-harga-hist');
    var $input = $box.siblings('.b-harga');
    var n = parseNum($btn.data('value'));
    if (!isNaN(n)) {
      $input.val(fmtInput(n,2));
      recalcBatchRow($input.closest('tr'));
    }
    $box.hide();
    $input.trigger('focus');
  });
  $(document).on('click', function(e){
    if ($(e.target).closest('#xHarga, #xHargaHist').length === 0) $('#xHargaHist').hide();
    if ($(e.target).closest('.b-harga, .b-harga-hist').length === 0) $('.b-harga-hist').hide();
  });

  $('#btnSimpanBatchLpg').on('click', function(){
    var tanggal = $('#batchTanggal').val();
    if(!tanggal){ Swal.fire({icon:'warning',title:'Perhatian',text:'Tanggal wajib diisi.'}); return; }

    var items = [];
    $('#batchLpgTable tbody tr[data-kode]').each(function(){
      var $tr=$(this);
      var pakai=parseNum($tr.find('.b-pakai').val());
      var harga=parseNum($tr.find('.b-harga').val());
      var ket=$tr.find('.b-ket').val() || '';
      if((!isNaN(pakai) && pakai>0) || (!isNaN(harga) && harga>0) || ket.trim()!==''){
        items.push({
          tank_kode:$tr.data('kode'),
          pemakaian_kg:isNaN(pakai)?0:pakai,
          harga_rp:isNaN(harga)?0:harga,
          ket:ket
        });
      }
    });

    if(items.length===0){ Swal.fire({icon:'warning',title:'Perhatian',text:'Tidak ada baris yang diisi.'}); return; }

    $.ajax({
      url:'save_lpg_skid_tank_bulk.php',
      type:'POST',
      dataType:'json',
      data:{ tanggal:tanggal, items: JSON.stringify(items) },
      success:function(resp){
        if(resp && resp.success){
          $('#modalBatchLpg').modal('hide');
          Swal.fire({icon:'success',title:'Sukses',text:resp.message || 'Data berhasil disimpan.'});
          table.ajax.reload(null,false);
        } else {
          Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal menyimpan data bulk.'});
        }
      },
      error:function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan saat simpan bulk.'}); }
    });
  });

  $('#formLpg').on('submit', function(e){
    e.preventDefault();
    $.ajax({ url:'save_lpg_skid_tank.php', type:'POST', data:$(this).serialize(), dataType:'json',
      success:function(resp){ if(resp&&resp.success){ $('#modalTambahLpg').modal('hide'); Swal.fire({icon:'success',title:'Sukses',text:resp.message||'Tersimpan.'}); table.ajax.reload(null,false);} else {Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal menyimpan.'});}},
      error:function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan.'}); }
    });
  });

  $('#formCatatanLpg').on('submit', function(e){
    e.preventDefault();
    $.ajax({ url:'save_catatan_lpg_skid_tank.php', type:'POST', data:$(this).serialize(), dataType:'json',
      success:function(resp){ if(resp&&resp.success){ $('#modalTambahCatatanLpg').modal('hide'); Swal.fire({icon:'success',title:'Sukses',text:resp.message||'Catatan tersimpan.'}); table.ajax.reload(null,false);} else { Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal menyimpan catatan.'}); } },
      error:function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan saat menyimpan catatan.'}); }
    });
  });

  $(document).on('click','.btn-detail',function(){ var id=$(this).data('id'); if(id) window.location.href='view_lpg_skid_tank.php?id='+encodeURIComponent(id); });
  $(document).on('click','.btn-edit',function(){ var id=$(this).data('id'); if(id) window.location.href='edit_lpg_skid_tank.php?id='+encodeURIComponent(id); });
  $(document).on('click','.btn-delete',function(){ var id=$(this).data('id'); if(!id) return; Swal.fire({title:'Hapus Data?',text:'Data akan dihapus permanen.',icon:'warning',showCancelButton:true,confirmButtonColor:'#d33',cancelButtonColor:'#3085d6',confirmButtonText:'Ya, Hapus!',cancelButtonText:'Batal'}).then(function(r){ if(r.isConfirmed){ window.location.href='delete_lpg_skid_tank.php?id='+encodeURIComponent(id); }}); });
});
</script>
