<?php
session_start();
include '../../koneksi.php';

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

// ---------- AJAX Router (HARUS sebelum output HTML) ----------
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
          // Update: set UpdatedAt dan UpdUser
          $s = sqlsrv_query($conn,
            "UPDATE dbo.Vehicles SET Plate=?, Merk=?, Model=?, Color=?, UpdatedAt=GETDATE(), UpdUser=? WHERE VehicleId=?",
            [$plate,$merk,$model,$color,$user,$id]
          );
          if ($s === false) throw new Exception(print_r(sqlsrv_errors(), true));
          json_out(['ok'=>true,'msg'=>'Data diperbarui']);
        } else {
          // Insert: isi UpdatedAt dan UpdUser
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
        $s  = sqlsrv_query($conn, "DELETE FROM dbo.Vehicles WHERE VehicleId=?", [$id]);
        if ($s === false) throw new Exception(print_r(sqlsrv_errors(), true));
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

// ---------- Render Halaman ----------
include '../../includes/header.php';
include '../../includes/sidebar.php';

// Wajib login
if (!isset($_SESSION['UserName'])) {
  $_SESSION['error'] = "Silakan login terlebih dahulu!";
  header('Location: ../login.php'); exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
date_default_timezone_set('Asia/Jakarta');

// Hak akses
$groupId = $_SESSION['GroupId'];
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
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Master Kendaraan</title>

  <!-- AdminLTE + Bootstrap -->
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">

  <!-- DataTables Bootstrap 5 + Responsive -->
  <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">

  <!-- SweetAlert2 CSS -->
  <link rel="stylesheet" href="/gg_app/plugins/css/notifikasi/sweetalert2.min.css">

  <style>
    .card-header.bg-<?php echo htmlspecialchars($themeColor);?> { border-bottom:0; }
    .modal .form-group { margin-bottom: .75rem; }
    .action-btns .btn { margin-right: 0.25rem; }
  </style>
</head>
<body>
<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1 class="m-0">Master Kendaraan</h1></div>
        <div class="col-sm-6">
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
        <div class="alert alert-danger"><?= $error_message ?></div>
      <?php else: ?>
        <div class="card">
          <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white d-flex align-items-center">
            <h3 class="card-title mb-0">Daftar Kendaraan</h3>
            <?php if (!empty($permissions['CanAdd'])): ?>
              <button id="btnAdd" class="btn btn-success btn-sm ml-auto"><i class="fas fa-plus"></i> Tambah Kendaraan</button>
            <?php endif; ?>
          </div>

          <div class="card-body table-responsive">
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

<!-- MODAL ADD/EDIT -->
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

<?php include '../../includes/footer.php'; ?>

<!-- JS -->
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- DataTables + Responsive -->
<script src="/gg_app/plugins/js/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/js/datatables/dataTables.bootstrap5.min.js"></script>
<script src="/gg_app/plugins/js/datatables/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/js/datatables/responsive.bootstrap5.min.js"></script>

<!-- SweetAlert -->
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
(function($){
  const API_URL = window.location.href.split('#')[0].split('?')[0];
  const CAN_EDIT = <?php echo (int)($permissions['CanEdit'] ?? 0); ?>;
  const CAN_DELETE = <?php echo (int)($permissions['CanDelete'] ?? 0); ?>;

  let dt, vehModal;

  function swalToast(msg, icon='success'){
    const Toast = Swal.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
      didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
      }
    });
    
    Toast.fire({
      icon: icon,
      title: msg
    });
  }

  function handleAjaxErr(xhr, status, err) {
    let msg = 'Terjadi kesalahan koneksi';
    try {
      if (xhr && xhr.responseText) {
        const j = JSON.parse(xhr.responseText);
        msg = j.msg || msg;
      }
    } catch(e){}
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: msg,
      confirmButtonColor: '#3085d6'
    });
  }

  function fmt24(dateStr) {
    if (!dateStr) return '';
    try {
      const t = dateStr.replace(' ', 'T');
      const d = new Date(t);
      if (isNaN(d.getTime())) return dateStr;
      const day = String(d.getDate()).padStart(2, '0');
      const month = String(d.getMonth() + 1).padStart(2, '0');
      const year = d.getFullYear();
      const hours = String(d.getHours()).padStart(2, '0');
      const minutes = String(d.getMinutes()).padStart(2, '0');
      const seconds = String(d.getSeconds()).padStart(2, '0');
      return `${day}-${month}-${year} ${hours}:${minutes}:${seconds}`;
    } catch(e) {
      return dateStr;
    }
  }

  function reloadTable(){
    $.getJSON(API_URL, {action:'list'})
      .done(function(res){
        dt.clear();
        if(res.ok){
          let i = 1;
          res.rows.forEach(r=>{
            const updatedStr = fmt24(r.UpdatedAt);
            const updatedBy = r.UpdUser ? $('<div>').text(r.UpdUser).text() : '-';

            let actions = `<div class="action-btns">`;
            if (CAN_EDIT) {
              actions += `<button class="btn btn-warning btn-sm btn-edit" data-id="${r.VehicleId}" title="Edit"><i class="fas fa-edit"></i></button> `;
            }
            if (CAN_DELETE) {
              actions += `<button class="btn btn-danger btn-sm btn-del" data-id="${r.VehicleId}" data-plate="${r.Plate}" title="Hapus"><i class="fas fa-trash"></i></button>`;
            }
            actions += `</div>`;

            dt.row.add([
              i++,
              r.Plate || '',
              r.Merk || '',
              r.Model || '',
              r.Color || '',
              updatedStr,
              updatedBy,
              actions
            ]);
          });
        }
        dt.draw(false);
      })
      .fail(handleAjaxErr);
  }

  $(function(){
    vehModal = new bootstrap.Modal(document.getElementById('vehModal'));

    dt = $('#tblKendaraan').DataTable({
      responsive: true,
      autoWidth: false,
      pageLength: 10,
      columnDefs: [{orderable:false, targets:[0,7]}],
      language: {
        search: 'Cari:',
        lengthMenu: 'Tampil _MENU_ data',
        info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
        zeroRecords: 'Tidak ada data',
        paginate: {
          previous: 'Sebelumnya',
          next: 'Berikutnya'
        }
      }
    });

    reloadTable();

    // Tambah
    $('#btnAdd').on('click', function(){
      $('#vehForm')[0].reset();
      $('#VehicleId').val('');
      $('.modal-title').text('Tambah Kendaraan');
      vehModal.show();
      setTimeout(()=> $('#Plate').focus(), 250);
    });

    // Edit
    $('#tblKendaraan').on('click', '.btn-edit', function(){
      const id = $(this).data('id');
      $.getJSON(API_URL, {action:'get', id})
        .done(function(res){
          if(!res.ok){ 
            swalToast('Data tidak ditemukan', 'error'); 
            return; 
          }
          $('#VehicleId').val(res.row.VehicleId);
          $('#Plate').val(res.row.Plate);
          $('#Merk').val(res.row.Merk);
          $('#Model').val(res.row.Model);
          $('#Color').val(res.row.Color);
          $('.modal-title').text('Edit Kendaraan');
          vehModal.show();
          setTimeout(()=> $('#Plate').focus(), 250);
        })
        .fail(handleAjaxErr);
    });

    // Delete dengan SweetAlert2
    $('#tblKendaraan').on('click', '.btn-del', function(){
      const id = $(this).data('id');
      const plate = $(this).data('plate');
      
      Swal.fire({
        title: 'Hapus Data?',
        html: `Yakin ingin menghapus kendaraan: <strong>${plate}</strong>?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        reverseButtons: true
      }).then((result) => {
        if (result.isConfirmed) {
          $.post(API_URL, {action:'delete', id: id})
            .done(function(res){
              if (typeof res === 'string') {
                try { res = JSON.parse(res); } catch(e){}
              }
              if(res.ok){ 
                swalToast(res.msg || 'Berhasil menghapus', 'success'); 
                reloadTable(); 
              } else { 
                Swal.fire({
                  icon: 'error',
                  title: 'Gagal',
                  text: res.msg || 'Gagal menghapus',
                  confirmButtonColor: '#3085d6'
                });
              }
            })
            .fail(handleAjaxErr);
        }
      });
    });

    // Submit (save)
    $('#vehForm').on('submit', function(e){
      e.preventDefault();
      // simple client validation
      if (!$('#Plate').val().trim() || !$('#Merk').val().trim() || 
          !$('#Model').val().trim() || !$('#Color').val().trim()) {
        Swal.fire({
          icon: 'warning',
          title: 'Perhatian',
          text: 'Semua field wajib diisi.',
          confirmButtonColor: '#3085d6'
        });
        return;
      }
      const data = $(this).serialize() + '&action=save';
      $('#saveBtn').prop('disabled', true);
      $.post(API_URL, data)
        .done(function(res){
          if (typeof res === 'string') {
            try { res = JSON.parse(res); } catch(e){}
          }
          if(res.ok){
            swalToast(res.msg || 'Tersimpan', 'success');
            vehModal.hide();
            reloadTable();
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Gagal',
              text: res.msg || 'Gagal menyimpan',
              confirmButtonColor: '#3085d6'
            });
          }
        })
        .fail(handleAjaxErr)
        .always(function(){ $('#saveBtn').prop('disabled', false); });
    });

    // Tombol batal hanya menutup modal
    $('.btn-cancel, #vehModal .btn-close').on('click', function(){
      vehModal.hide();
    });

    // Reset form ketika modal tersembunyi
    $('#vehModal').on('hidden.bs.modal', function () {
      $('#vehForm')[0].reset();
      $('#VehicleId').val('');
      $('#saveBtn').prop('disabled', false);
    });

    // Show any flash messages from PHP session using SweetAlert
    <?php if (!empty($_SESSION['success'])): ?>
    Swal.fire({
      icon: 'success',
      title: 'Sukses',
      text: "<?= addslashes($_SESSION['success']) ?>",
      timer: 2500,
      showConfirmButton: false
    });
    <?php unset($_SESSION['success']); endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: "<?= addslashes($_SESSION['error']) ?>",
      timer: 3000,
      showConfirmButton: false
    });
    <?php unset($_SESSION['error']); endif; ?>
  });
})(jQuery);
</script>
</body>
</html>