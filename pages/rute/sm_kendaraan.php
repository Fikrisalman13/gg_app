<?php
// sm_kendaraan.php - improved & cleaned version (search aligned top-right)
session_start();
include '../../koneksi.php';
require_once __DIR__ . '/deletion_history.php';

// ---------- Helpers ----------
function nocache(): void {
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache'); header('Expires: 0');
}
function json_out(array $data): never {
  header('Content-Type: application/json; charset=utf-8');
  nocache(); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit;
}
function dt_to_str($v): ?string {
  if ($v instanceof DateTimeInterface) return $v->format('Y-m-d H:i:s');
  return is_string($v) ? $v : null;
}

// ---------- AJAX Router ----------
$action = $_GET['action'] ?? $_POST['action'] ?? null;
if ($action) {
  try {
    if (!$conn) { throw new Exception('Koneksi DB gagal'); }

    switch ($action) {
      case 'list': {
        $q = "SELECT VehicleId, Plate, Merk, Model, Color, UpdatedAt, UpdUser
              FROM dbo.Vehicles ORDER BY VehicleId ASC";
        $s = sqlsrv_query($conn, $q);
        if ($s === false) throw new Exception(print_r(sqlsrv_errors(), true));
        $rows = [];
        while ($r = sqlsrv_fetch_array($s, SQLSRV_FETCH_ASSOC)) {
          $r['UpdatedAt'] = dt_to_str($r['UpdatedAt'] ?? null);
          $rows[] = $r;
        }
        sqlsrv_free_stmt($s);
        json_out(['ok'=>true,'rows'=>$rows]);
      }

      case 'get': {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) json_out(['ok'=>false,'msg'=>'ID tidak valid']);
        $s = sqlsrv_query($conn,
          "SELECT VehicleId, Plate, Merk, Model, Color FROM dbo.Vehicles WHERE VehicleId=?",
          [$id]
        );
        if ($s === false) throw new Exception(print_r(sqlsrv_errors(), true));
        $row = sqlsrv_fetch_array($s, SQLSRV_FETCH_ASSOC) ?: null;
        sqlsrv_free_stmt($s);
        json_out(['ok'=>(bool)$row,'row'=>$row]);
      }

      case 'save': {
        $id    = isset($_POST['VehicleId']) && $_POST['VehicleId'] !== '' ? (int)$_POST['VehicleId'] : null;
        $plate = trim($_POST['Plate'] ?? '');
        $merk  = trim($_POST['Merk'] ?? '');
        $model = trim($_POST['Model'] ?? '');
        $color = trim($_POST['Color'] ?? '');
        $user  = $_SESSION['UserName'] ?? 'System';

        if ($plate===''||$merk===''||$model===''||$color==='') {
          json_out(['ok'=>false,'msg'=>'Semua field wajib diisi.']);
        }

        if ($id) {
          $s = sqlsrv_query($conn,
            "UPDATE dbo.Vehicles SET Plate=?, Merk=?, Model=?, Color=?, UpdatedAt=GETDATE(), UpdUser=? WHERE VehicleId=?",
            [$plate,$merk,$model,$color,$user,$id]
          );
          if ($s === false) throw new Exception(print_r(sqlsrv_errors(), true));
          json_out(['ok'=>true,'msg'=>'Data diperbarui']);
        } else {
          $s = sqlsrv_query($conn,
            "INSERT INTO dbo.Vehicles(Plate, Merk, Model, Color, UpdatedAt, UpdUser)
             VALUES(?, ?, ?, ?, GETDATE(), ?)",
            [$plate,$merk,$model,$color,$user]
          );
          if ($s === false) throw new Exception(print_r(sqlsrv_errors(), true));
          json_out(['ok'=>true,'msg'=>'Data ditambahkan']);
        }
      }

      case 'delete': {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) json_out(['ok'=>false,'msg'=>'ID tidak valid']);
        if (!sqlsrv_begin_transaction($conn)) {
          throw new Exception('Begin transaction failed: '.print_r(sqlsrv_errors(), true));
        }
        try {
          log_deleted_row($conn, 'Vehicles', 'VehicleId', $id, $_SESSION['UserName'] ?? 'System');
          $s  = sqlsrv_query($conn, "DELETE FROM dbo.Vehicles WHERE VehicleId=?", [$id]);
          if ($s === false) {
            sqlsrv_rollback($conn);
            // try to detect FK error
            $err = sqlsrv_errors();
            $msg = 'Gagal menghapus';
            if ($err) {
              foreach ($err as $e) {
                if ((string)$e['SQLSTATE']==='23000' || (int)$e['code']===547) {
                  $msg = 'Tidak bisa dihapus: data sudah dipakai pada transaksi.';
                  break;
                }
              }
            }
            json_out(['ok'=>false,'msg'=>$msg]);
          }
          if ($s) sqlsrv_free_stmt($s);
          if (!sqlsrv_commit($conn)) {
            throw new Exception('Commit failed: '.print_r(sqlsrv_errors(), true));
          }
        } catch (Throwable $e) {
          sqlsrv_rollback($conn);
          throw $e;
        }
        json_out(['ok'=>true,'msg'=>'Data dihapus']);
      }

      default:
        http_response_code(404);
        json_out(['ok'=>false,'msg'=>'Unknown action']);
    }
  } catch (Throwable $e) {
    http_response_code(500);
    json_out(['ok'=>false,'msg'=>$e->getMessage()]);
  }
}

// ---------- Render page ----------
include '../../includes/header.php';
include '../../includes/sidebar.php';

if (!isset($_SESSION['UserName'])) {
  $_SESSION['error'] = "Silakan login terlebih dahulu!";
  header('Location: /gg_app/login.php'); exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
date_default_timezone_set('Asia/Jakarta');

$groupId = $_SESSION['GroupId'] ?? 0;
$menuId  = 46;
$permissions = ['CanView'=>1,'CanAdd'=>1,'CanEdit'=>1,'CanDelete'=>1];
$st = sqlsrv_query($conn,
  "SELECT CanView, CanAdd, CanEdit, CanDelete FROM dbo.SMGroupTrustee WHERE GroupId=? AND MenuId=?",
  [$groupId, $menuId]
);
if ($st && ($row = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC))) { $permissions = $row; }
if ($st) sqlsrv_free_stmt($st);
if (empty($permissions['CanView'])) { $error_message = "Anda tidak memiliki hak untuk melihat halaman ini."; }
?>

<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2 align-items-center">
        <div class="col-sm-6">
          <h1 class="m-0">Master Kendaraan</h1>
        </div>
        <div class="col-sm-6 text-end">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
            <li class="breadcrumb-item active">Master Kendaraan</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <section class="content">
    <div class="container-fluid">
      <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
      <?php else: ?>
        <div class="card">
          <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
            <h3 class="card-title mb-0">Daftar Kendaraan</h3>
            <?php if (!empty($permissions['CanAdd'])): ?>
              <button id="btnAdd" class="btn btn-success btn-sm float-end">
                <i class="fas fa-plus"></i> Tambah Kendaraan
              </button>
            <?php endif; ?>
          </div>

          <div class="card-body table-responsive">
            <!-- search/filter akan ditampilkan oleh DataTables (bawaan) di atas tabel, kanan -->
            <table id="tblKendaraan" class="table table-hover table-sm nowrap" style="width:100%">
              <thead class="thead-light">
                <tr>
                  <th style="width:60px">No</th>
                  <th>Plate</th>
                  <th>Merk</th>
                  <th>Model</th>
                  <th>Color</th>
                  <th style="width:180px">Tgl Diperbarui</th>
                  <th style="width:150px">Diperbarui Oleh</th>
                  <th style="width:120px">Aksi</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>

<!-- modal -->
<div class="modal fade" id="vehModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="vehForm" autocomplete="off" novalidate>
      <div class="modal-header">
        <h5 class="modal-title">Tambah Kendaraan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="VehicleId" id="VehicleId">
        <div class="mb-3">
          <label for="Plate" class="form-label">Plate <span class="text-danger">*</span></label>
          <input type="text" class="form-control" name="Plate" id="Plate" required maxlength="20" placeholder="Masukkan nomor plat">
        </div>
        <div class="mb-3">
          <label for="Merk" class="form-label">Merk <span class="text-danger">*</span></label>
          <input type="text" class="form-control" name="Merk" id="Merk" required maxlength="50" placeholder="Masukkan merk kendaraan">
        </div>
        <div class="mb-3">
          <label for="Model" class="form-label">Model <span class="text-danger">*</span></label>
          <input type="text" class="form-control" name="Model" id="Model" required maxlength="50" placeholder="Masukkan model kendaraan">
        </div>
        <div class="mb-3">
          <label for="Color" class="form-label">Color <span class="text-danger">*</span></label>
          <input type="text" class="form-control" name="Color" id="Color" required maxlength="30" placeholder="Masukkan warna kendaraan">
        </div>
        <small class="form-text text-muted">Kolom bertanda * wajib diisi.</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-cancel" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary" id="saveBtn">Simpan</button>
      </div>
    </form>
  </div>
</div>

<style>
.card-header { position: relative; padding-right: 3rem; }
.card-header .card-title { display: inline-block; }
.btn.float-end { margin-top: -6px; }

/* DataTables: pastikan filter berada di pojok kanan atas */
.dataTables_wrapper .dataTables_filter {
  display: flex !important;
  justify-content: flex-end !important;
  align-items: center !important;
  margin-bottom: .5rem;
}
.dataTables_wrapper .dataTables_filter label {
  margin: 0;
  display: flex;
  align-items: center;
  gap: .5rem;
  font-weight: 400;
}
.dataTables_wrapper .dataTables_filter input {
  width: 220px;
  max-width: 40vw;
  padding: .25rem .5rem;
  height: calc(1.5em + .5rem);
  box-sizing: border-box;
}

/* Pagination/info tweaks */
.dataTables_wrapper .dataTables_info { padding-top: .35rem; }
.dataTables_wrapper .pagination { margin: .25rem 0; }

@media (max-width: 575px) {
  .card-header .btn { padding: .25rem .5rem; font-size: .85rem; }
  .dataTables_wrapper .dataTables_filter input { width: 140px; max-width: 32vw; }
}
</style>

<!-- ================= Page-specific loader + init ================= -->
<script>
(function(){
  const API_URL = window.location.href.split('#')[0].split('?')[0];
  const CAN_EDIT = <?php echo (int)($permissions['CanEdit'] ?? 0); ?>;
  const CAN_DELETE = <?php echo (int)($permissions['CanDelete'] ?? 0); ?>;

  const ASSETS = {
    jquery: '/gg_app/plugins/AdminLTE-3.2.0/plugins/jquery/jquery.min.js',
    bootstrap_bundle: '/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js',
    datatables_core: '/gg_app/plugins/js/datatables/jquery.dataTables.min.js',
    datatables_bs5_local: '/gg_app/plugins/js/datatables/dataTables.bootstrap5.min.js',
    datatables_bs5_cdn: 'https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js',
    datatables_responsive_local: '/gg_app/plugins/js/datatables/dataTables.responsive.min.js',
    datatables_responsive_bs5_local: '/gg_app/plugins/js/datatables/responsive.bootstrap5.min.js',
    datatables_responsive_bs5_cdn: 'https://cdn.datatables.net/responsive/2.5.0/responsive.bootstrap5.min.js',
    sweetalert: '/gg_app/plugins/js/notifikasi/sweetalert2@11.js'
  };

  function loadScriptOnce(src) {
    return new Promise((resolve, reject) => {
      if (document.querySelector('script[src="'+src+'"]')) return resolve(true);
      const s = document.createElement('script');
      s.src = src; s.async = false;
      s.onload = () => resolve(true);
      s.onerror = () => reject(new Error('Failed to load ' + src));
      document.head.appendChild(s);
    });
  }

  function hasDataTable($instance) {
    try { return !!($instance && $instance.fn && typeof $instance.fn.DataTable !== 'undefined'); }
    catch(e){ return false; }
  }

  function formatDateStr(dateStr){
    if(!dateStr) return '';
    try {
      const t = dateStr.replace(' ', 'T');
      const d = new Date(t);
      if (isNaN(d.getTime())) return dateStr;
      const day = String(d.getDate()).padStart(2,'0');
      const month = String(d.getMonth()+1).padStart(2,'0');
      const year = d.getFullYear();
      const hours = String(d.getHours()).padStart(2,'0');
      const minutes = String(d.getMinutes()).padStart(2,'0');
      const seconds = String(d.getSeconds()).padStart(2,'0');
      return `${day}-${month}-${year} ${hours}:${minutes}:${seconds}`;
    } catch(e){ return dateStr; }
  }

  function rebuildPaginationToBootstrap($paginate) {
    try {
      if (!$paginate || !$paginate.length) return;
      if ($paginate.find('ul.pagination').length) return;
      const $anchors = $paginate.find('a, span').filter(function(){ return $(this).text().trim() !== ''; });
      if (!$anchors.length) return;
      const $ul = $('<ul class="pagination pagination-sm mb-0"></ul>');
      $anchors.each(function(){
        const $el = $(this);
        const text = $el.text().trim();
        const isDisabled = $el.hasClass('disabled') || $el.attr('aria-disabled') === 'true';
        const isActive = $el.is('span') || $el.hasClass('current') || $el.hasClass('active');
        const $li = $('<li>').addClass('page-item' + (isDisabled? ' disabled':'') + (isActive? ' active':''));
        const $a = $('<a class="page-link" href="#"></a>').html(text);
        $a.on('click', function(ev){ ev.preventDefault(); try { $el.get(0).click(); } catch(e){ $el.trigger('click'); } });
        $li.append($a); $ul.append($li);
      });
      $paginate.empty().append($ul);
    } catch(e) { console.warn('rebuildPagination failed', e); }
  }

  async function ensureLibraries() {
    // jQuery
    if (typeof window.jQuery === 'undefined') {
      await loadScriptOnce(ASSETS.jquery).catch(()=>{ throw new Error('Gagal memuat jQuery'); });
      await new Promise(r=>setTimeout(r,20));
    }
    const $ = window.jQuery;

    // DataTables core
    if (!hasDataTable($)) {
      await loadScriptOnce(ASSETS.datatables_core).catch(()=>{ throw new Error('Gagal memuat DataTables core'); });
      await new Promise(r=>setTimeout(r,20));
    }

    // DataTables Bootstrap adapter (local or CDN)
    try { await loadScriptOnce(ASSETS.datatables_bs5_local); }
    catch(e) { await loadScriptOnce(ASSETS.datatables_bs5_cdn).catch(()=>{}); }

    // responsive
    await loadScriptOnce(ASSETS.datatables_responsive_local).catch(()=>{});
    try { await loadScriptOnce(ASSETS.datatables_responsive_bs5_local); } catch(e) { await loadScriptOnce(ASSETS.datatables_responsive_bs5_cdn).catch(()=>{}); }

    // bootstrap bundle
    if (typeof window.bootstrap === 'undefined') {
      await loadScriptOnce(ASSETS.bootstrap_bundle).catch(()=>{});
      await new Promise(r=>setTimeout(r,10));
    }

    // sweetalert2
    if (typeof window.Swal === 'undefined') {
      await loadScriptOnce(ASSETS.sweetalert).catch(()=>{});
      await new Promise(r=>setTimeout(r,10));
    }

    return window.jQuery;
  }

  function initPage($) {
    let dt;
    try {
      // dom diatur supaya filter (search) bawaan muncul di kanan atas, length di kiri
      dt = $('#tblKendaraan').DataTable({
        dom: "<'row mb-2'<'col-sm-6'l><'col-sm-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row mt-2'<'col-sm-6'i><'col-sm-6'p>>",
        responsive: true,
        autoWidth: false,
        pageLength: 10,
        lengthMenu: [ [10, 25, 50], [10, 25, 50] ],
        pagingType: 'simple_numbers',
        columnDefs: [{ orderable:false, targets:[0,7] }],
        language: {
          lengthMenu: 'Tampil _MENU_ data',
          info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
          zeroRecords: 'Tidak ada data',
          paginate: { previous: '&laquo; Sebelumnya', next: 'Berikutnya &raquo;' },
          search: '' // placeholder di-set setelah inisialisasi
        },
        drawCallback: function() {
          const $paginate = $(this).closest('.dataTables_wrapper').find('.dataTables_paginate');
          rebuildPaginationToBootstrap($paginate);
        }
      });
    } catch(e) {
      console.error('DataTable init failed', e);
      if (window.Swal) Swal.fire({ icon:'error', title:'Error', text:'Gagal inisialisasi tabel.' });
      return;
    }

    // After init: force the filter input to be small and aligned top-right (fix centering issues)
    try {
      const $dtWrap = $('#tblKendaraan').closest('.dataTables_wrapper');
      $dtWrap.find('div.dataTables_length').addClass('text-start');
      $dtWrap.find('div.dataTables_filter').addClass('text-end');
      const $filter = $dtWrap.find('div.dataTables_filter');
      $filter.css({ display: 'flex', justifyContent: 'flex-end', alignItems: 'center' });
      $filter.find('label').css({ margin: 0, display: 'flex', alignItems: 'center', gap: '0.5rem' });
      const $input = $filter.find('input');
      $input.addClass('form-control form-control-sm').attr('placeholder', 'Cari...');
      $input.css({ width: '220px', maxWidth: '40vw' });
    } catch(e) { console.warn('Failed to align DataTables filter:', e); }

    let vehModal = null;
    try { vehModal = new bootstrap.Modal(document.getElementById('vehModal')); } catch(e){}

    // pastikan tombol Batal menutup modal, mereset form, dan enable tombol Simpan
    $(document).off('click.smVehCancel').on('click.smVehCancel', '.btn-cancel, #vehModal .btn-close', function(e){
      e.preventDefault();
      try { if (vehModal && typeof vehModal.hide === 'function') vehModal.hide(); } catch(_) {}
      try { $('#vehForm')[0].reset(); } catch(_) {}
      try { $('#VehicleId').val(''); } catch(_) {}
      try { $('#saveBtn').prop('disabled', false); } catch(_) {}
    });

    function reloadTable(){
      $.getJSON(API_URL, {action:'list'})
        .done(function(res){
          dt.clear();
          if (res.ok) {
            let i = 1;
            res.rows.forEach(function(r){
              const updatedStr = formatDateStr(r.UpdatedAt);
              const updatedBy = r.UpdUser ? $('<div>').text(r.UpdUser).text() : '-';
              let actions = '<div class="action-btns">';
              if (CAN_EDIT) actions += `<button class="btn btn-warning btn-sm btn-edit" data-id="${r.VehicleId}" title="Edit"><i class="fas fa-edit"></i></button> `;
              if (CAN_DELETE) actions += `<button class="btn btn-danger btn-sm btn-del" data-id="${r.VehicleId}" data-plate="${r.Plate}" title="Hapus"><i class="fas fa-trash"></i></button>`;
              actions += '</div>';
              dt.row.add([ i++, r.Plate||'', r.Merk||'', r.Model||'', r.Color||'', updatedStr, updatedBy, actions ]);
            });
          }
          dt.draw(false);
        })
        .fail(function(xhr){
          let msg='Terjadi kesalahan koneksi';
          try { const j=JSON.parse(xhr.responseText); msg=j.msg||msg; } catch(e){}
          if (window.Swal) Swal.fire({ icon:'error', title:'Error', text: msg });
        });
    }

    reloadTable();

    // Built-in DataTables search is used (top-right). If you need a custom search box in header instead, tell me.
    $(document).off('click.smVeh').on('click.smVeh', '#btnAdd', function(){
      $('#vehForm')[0].reset(); $('#VehicleId').val(''); $('.modal-title').text('Tambah Kendaraan');
      try { $('#saveBtn').prop('disabled', false); } catch(_) {}
      if (vehModal) vehModal.show(); setTimeout(()=> $('#Plate').focus(), 250);
    });

    $(document).off('click.smVehEdit').on('click.smVehEdit', '.btn-edit', function(){
      const id = $(this).data('id');
      $.getJSON(API_URL, {action:'get', id})
        .done(function(res){
          if(!res.ok){ if (window.Swal) Swal.fire({icon:'error',title:'Gagal',text:'Data tidak ditemukan'}); return; }
          $('#VehicleId').val(res.row.VehicleId); $('#Plate').val(res.row.Plate); $('#Merk').val(res.row.Merk);
          $('#Model').val(res.row.Model); $('#Color').val(res.row.Color); $('.modal-title').text('Edit Kendaraan');
          try { $('#saveBtn').prop('disabled', false); } catch(_) {}
          if (vehModal) vehModal.show(); setTimeout(()=> $('#Plate').focus(), 250);
        }).fail(function(){ if (window.Swal) Swal.fire({icon:'error',title:'Error',text:'Gagal mengambil data'}); });
    });

    $(document).off('click.smVehDel').on('click.smVehDel', '.btn-del', function(){
      const id = $(this).data('id'); const plate = $(this).data('plate');
      if (!window.Swal) { if (!confirm(`Hapus kendaraan ${plate}?`)) return; $.post(API_URL,{action:'delete',id}).done(()=>reloadTable()); return; }
      Swal.fire({
        title: 'Hapus Data?',
        html: `Yakin ingin menghapus kendaraan: <strong>${plate}</strong>?`,
        icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal', reverseButtons: true
      }).then((result) => {
        if (result.isConfirmed) {
          $.post(API_URL, {action:'delete', id: id})
            .done(function(res){
              if (typeof res === 'string') { try{res=JSON.parse(res);}catch(e){} }
              if(res.ok){ if (window.Swal) Swal.fire({icon:'success',title:'Sukses',text:res.msg||'Berhasil'}); reloadTable(); }
              else { if (window.Swal) Swal.fire({icon:'error',title:'Gagal',text:res.msg||'Gagal menghapus'}); }
            })
            .fail(function(){ if (window.Swal) Swal.fire({icon:'error',title:'Gagal',text:'Terjadi kesalahan'}); });
        }
      });
    });

    $(document).off('submit.smVehForm').on('submit.smVehForm', '#vehForm', function(e){
      e.preventDefault();
      if (!$('#Plate').val().trim() || !$('#Merk').val().trim() || !$('#Model').val().trim() || !$('#Color').val().trim()) {
        if (window.Swal) Swal.fire({ icon:'warning', title:'Perhatian', text: 'Semua field wajib diisi.' }); else alert('Semua field wajib diisi.');
        return;
      }
      const data = $(this).serialize() + '&action=save';
      $('#saveBtn').prop('disabled', true);
      $.post(API_URL, data)
        .done(function(res){
          if (typeof res === 'string') { try{res=JSON.parse(res);}catch(e){} }
          if(res.ok){
            if (window.Swal) Swal.fire({icon:'success',title:'Sukses',text:res.msg||'Tersimpan'});
            if (vehModal) vehModal.hide();
            reloadTable();
          } else {
            if (window.Swal) Swal.fire({icon:'error',title:'Gagal',text:res.msg||'Gagal menyimpan'});
          }
        })
        .fail(function(){ if (window.Swal) Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan'}); })
        .always(function(){ $('#saveBtn').prop('disabled', false); });
    });

    $(document).on('hidden.bs.modal', '#vehModal', function(){
      $('#vehForm')[0].reset(); $('#VehicleId').val(''); $('#saveBtn').prop('disabled', false);
    });

    <?php if (!empty($_SESSION['success'])): ?>
    if (window.Swal) Swal.fire({ icon:'success', title:'Sukses', text: "<?= addslashes($_SESSION['success']) ?>", timer:2500, showConfirmButton:false });
    <?php unset($_SESSION['success']); endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
    if (window.Swal) Swal.fire({ icon:'error', title:'Error', text: "<?= addslashes($_SESSION['error']) ?>", timer:3000, showConfirmButton:false });
    <?php unset($_SESSION['error']); endif; ?>
  } // end initPage

  // runner
  (async function run(){
    try {
      const $ = await ensureLibraries();
      if (!hasDataTable($)) {
        console.error('DataTables plugin missing after load.');
        if (window.Swal) Swal.fire({ icon:'error', title:'Error', text: 'Plugin DataTables tidak ditemukan setelah dimuat.' });
        return;
      }
      setTimeout(()=> initPage(window.jQuery), 40);
    } catch(err) {
      console.error('Failed to load libraries:', err);
      if (window.Swal) Swal.fire({ icon:'error', title:'Load Error', text: err.message || String(err) });
    }
  })();

  // minimal ensureLibraries already defined above
})();
</script>

<?php
include '../../includes/footer.php';
?>
