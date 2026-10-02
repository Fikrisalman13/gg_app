<?php
session_start();
ob_start();
include '../../koneksi.php'; // $conn: sqlsrv_connect

// ------------------- Basic checks & session values -------------------
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: ../login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';
$groupId = $_SESSION['GroupId'] ?? null;
$sessionUser = $_SESSION['UserName'];
date_default_timezone_set('Asia/Jakarta');

// MenuId khusus untuk halaman Rute (ubah jika beda)
$menuId = 104;

// ------------------- Helpers -------------------
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

// ------------------- Load permissions -------------------
$permissions = [
  'CanView' => 1, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0
];
if ($conn && $groupId !== null) {
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete 
            FROM dbo.SMGroupTrustee 
            WHERE GroupId = ? AND MenuId = ?";
    $params = [$groupId, $menuId];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt !== false) {
        if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $permissions = array_merge($permissions, $row);
        }
        sqlsrv_free_stmt($stmt);
    }
}

// If user cannot view, show message (still render layout)
if (isset($permissions['CanView']) && $permissions['CanView'] == 0) {
    $error_message = "Anda tidak memiliki hak untuk melihat halaman ini.";
}

// ------------------- MINI ROUTER AJAX (HARUS SEBELUM OUTPUT HTML) -------------------
$action = $_GET['action'] ?? $_POST['action'] ?? null;
if ($action) {
  try {
    if (!$conn) json_out(['ok'=>false,'msg'=>'DB connection error']);

    switch ($action) {
      case 'list': {
        // Ambil kolom yang ditampilkan: UpdatedAt & UpdUser dipertahankan
        $sql = "SELECT RouteMasterId, Name, Address, UpdatedAt, UpdUser
                FROM dbo.RouteMaster
                ORDER BY RouteMasterId ASC";
        $stmt = sqlsrv_query($conn, $sql);
        if ($stmt === false) throw new Exception(print_r(sqlsrv_errors(), true));
        $rows = [];
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
          $r['UpdatedAt'] = dt_to_str($r['UpdatedAt'] ?? null);
          $rows[] = $r;
        }
        sqlsrv_free_stmt($stmt);
        json_out(['ok'=>true,'rows'=>$rows]);
      }

      case 'get': {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) json_out(['ok'=>false,'msg'=>'ID tidak valid']);
        $stmt = sqlsrv_query(
          $conn,
          "SELECT RouteMasterId, Name, Address FROM dbo.RouteMaster WHERE RouteMasterId = ?",
          [$id]
        );
        if ($stmt === false) throw new Exception(print_r(sqlsrv_errors(), true));
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) ?: null;
        sqlsrv_free_stmt($stmt);
        json_out(['ok'=> (bool)$row, 'row'=>$row]);
      }

      case 'save': {
        // Permission: tambah atau edit
        if (!(isset($permissions['CanAdd']) && $permissions['CanAdd']==1) &&
            !(isset($permissions['CanEdit']) && $permissions['CanEdit']==1 && !empty($_POST['RouteMasterId'])))
        {
          json_out(['ok'=>false,'msg'=>'Anda tidak memiliki hak untuk menyimpan data.']);
        }

        $id   = isset($_POST['RouteMasterId']) && $_POST['RouteMasterId'] !== '' ? (int)$_POST['RouteMasterId'] : null;
        $name = trim($_POST['Name'] ?? '');
        $addr = trim($_POST['Address'] ?? '');
        $user = $sessionUser;

        if ($name==='' || $addr==='') {
          json_out(['ok'=>false,'msg'=>'Name dan Address wajib diisi.']);
        }

        if ($id) {
          // Update: set UpdatedAt dan UpdUser (GETDATE)
          $stmt = sqlsrv_query(
            $conn,
            "UPDATE dbo.RouteMaster 
             SET Name = ?, Address = ?, UpdatedAt = GETDATE(), UpdUser = ?
             WHERE RouteMasterId = ?",
            [$name, $addr, $user, $id]
          );
          if ($stmt === false) throw new Exception(print_r(sqlsrv_errors(), true));
          json_out(['ok'=>true,'msg'=>'Data diperbarui']);
        } else {
          // Insert: isi Name, Address, UpdatedAt & UpdUser
          $stmt = sqlsrv_query(
            $conn,
            "INSERT INTO dbo.RouteMaster (Name, Address, UpdatedAt, UpdUser)
             VALUES(?, ?, GETDATE(), ?)",
            [$name, $addr, $user]
          );
          if ($stmt === false) throw new Exception(print_r(sqlsrv_errors(), true));
          json_out(['ok'=>true,'msg'=>'Data ditambahkan']);
        }
      }

      case 'delete': {
        if (!(isset($permissions['CanDelete']) && $permissions['CanDelete']==1)) {
          json_out(['ok'=>false,'msg'=>'Anda tidak memiliki hak untuk menghapus data.']);
        }
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) json_out(['ok'=>false,'msg'=>'ID tidak valid']);
        $stmt = sqlsrv_query($conn, "DELETE FROM dbo.RouteMaster WHERE RouteMasterId = ?", [$id]);
        if ($stmt === false) {
          // Tangani FK violation jika ada (SQLSTATE 23000 / code 547)
          $err = sqlsrv_errors();
          $msg = 'Gagal menghapus';
          if ($err) {
            foreach ($err as $e) {
              if ((string)$e['SQLSTATE']==='23000' || (int)$e['code']===547) {
                $msg = 'Tidak bisa dihapus: data sudah dipakai pada transaksi Rute.';
                break;
              }
            }
          }
          json_out(['ok'=>false,'msg'=>$msg]);
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

// ------------------- View -------------------
include '../../includes/header.php';
include '../../includes/sidebar.php';
// finish output buffering so includes header/sidebar output already sent
ob_end_flush();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Master Rute</title>

  <!-- AdminLTE / DataTables CSS (bootstrap default look) -->
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">

  <!-- SweetAlert2 CSS -->
  <link rel="stylesheet" href="/gg_app/plugins/css/notifikasi/sweetalert2.min.css">

</head>
<body class="hold-transition sidebar-mini">
<div class="content-wrapper">
  <!-- Content Header -->
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2 align-items-center">
        <div class="col-sm-6">
          <h1 class="m-0">Master Rute</h1>
        
        </div>
        <div class="col-sm-6 text-end">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
            <li class="breadcrumb-item active">Rute</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <!-- Main content -->
  <section class="content">
    <div class="container-fluid">
      <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
      <?php else: ?>
        <div class="card">
          <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
              <h3 class="card-title mb-0">Daftar Master Rute</h3>
              <?php if (isset($permissions['CanAdd']) && $permissions['CanAdd'] == 1): ?>
                <button id="btnAdd" class="btn btn-success btn-sm float-right">
                  <i class="fas fa-plus"></i> Tambah Rute
                </button>
              <?php endif; ?>
            </div>

          <div class="card-body">
            <div class="table-responsive">
              <table id="tbl" class="table table-hover table-sm">
                <thead class="thead-light">
                  <tr>
                    <th style="width:60px">No</th>
                    <th>Nama Rute</th>
                    <th>Alamat</th>
                    <th style="width:180px">Tgl Diperbarui</th>
                    <th style="width:150px">Diperbarui Oleh</th>
                    <th style="width:120px">Aksi</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>

<!-- Modal Add/Edit -->
<div class="modal fade" id="rmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="rmForm" autocomplete="off" novalidate>
      <div class="modal-header">
        <h5 class="modal-title">Tambah Rute</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="RouteMasterId" id="RouteMasterId" value="">
        <div class="mb-3">
          <label for="Name" class="form-label">Nama Rute <span class="text-danger">*</span></label>
          <input type="text" class="form-control" name="Name" id="Name" required maxlength="200" placeholder="Masukkan nama rute">
        </div>
        <div class="mb-3">
          <label for="Address" class="form-label">Alamat <span class="text-danger">*</span></label>
          <textarea class="form-control" name="Address" id="Address" rows="3" required placeholder="Masukkan alamat lengkap"></textarea>
        </div>
        <div class="form-text">Kolom bertanda * wajib diisi.</div>
      </div>
      <div class="modal-footer">
        <!-- cancel hanya menutup modal -->
        <button type="button" class="btn btn-outline-secondary btn-cancel" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary" id="saveBtn">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Scripts -->
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/js/datatables/dataTables.bootstrap5.min.js"></script>

<!-- SweetAlert2 JS -->
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
(function($){
  let dt, rmModal;

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
      // normalisasi spasi/T separator
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
    $.getJSON('sm_rute.php', {action:'list'})
      .done(function(res){
        dt.clear();
        if(res.ok){
          let i = 1;
          res.rows.forEach(r=>{
            const updatedStr = fmt24(r.UpdatedAt);
            const nameEsc = $('<div>').text(r.Name || '').html();
            const addrEsc = $('<div>').text(r.Address || '').html();
            const updatedBy = r.UpdUser ? $('<div>').text(r.UpdUser).text() : '-';

            let actions = `<div class="action-btns">`;
            <?php if (isset($permissions['CanEdit']) && $permissions['CanEdit'] == 1): ?>
            actions += `<button class="btn btn-warning btn-sm btn-edit" data-id="${r.RouteMasterId}" title="Edit"><i class="fas fa-edit"></i></button> `;
            <?php endif; ?>
            <?php if (isset($permissions['CanDelete']) && $permissions['CanDelete'] == 1): ?>
            actions += `<button class="btn btn-danger btn-sm btn-del" data-id="${r.RouteMasterId}" data-name="${nameEsc}" title="Hapus"><i class="fas fa-trash"></i></button>`;
            <?php endif; ?>
            actions += `</div>`;

            dt.row.add([
              i++,
              nameEsc,
              addrEsc,
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
    rmModal = new bootstrap.Modal(document.getElementById('rmModal'));

    dt = $('#tbl').DataTable({
      paging: true,
      searching: true,
      info: true,
      ordering: true,
      // columns: No, Nama, Alamat, UpdatedAt, UpdUser, Aksi
      columnDefs: [{orderable:false, targets:[0,5]}],
      language: {
        search: 'Cari:',
        lengthMenu: 'Tampil _MENU_ data',
        info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
        zeroRecords: 'Tidak ada data'
      },
      responsive: true,
      pageLength: 10
    });

    reloadTable();

    // Open Add modal
    $('#btnAdd').on('click', function(){
      // reset saat ADD
      $('#rmForm')[0].reset();
      $('#RouteMasterId').val('');
      $('.modal-title').text('Tambah Rute');
      rmModal.show();
      // fokus input setelah modal tampil
      setTimeout(()=> $('#Name').focus(), 250);
    });

    // Edit
    $('#tbl tbody').on('click', '.btn-edit', function(){
      const id = $(this).data('id');
      $.getJSON('sm_rute.php', {action:'get', id})
        .done(function(res){
          if(!res.ok){ 
            swalToast('Data tidak ditemukan', 'error'); 
            return; 
          }
          $('#RouteMasterId').val(res.row.RouteMasterId);
          $('#Name').val(res.row.Name);
          $('#Address').val(res.row.Address);
          $('.modal-title').text('Edit Rute');
          rmModal.show();
          setTimeout(()=> $('#Name').focus(), 250);
        })
        .fail(handleAjaxErr);
    });

    // Delete dengan SweetAlert2
    $('#tbl tbody').on('click', '.btn-del', function(){
      const id = $(this).data('id');
      const name = $(this).data('name');
      
      Swal.fire({
        title: 'Hapus Data?',
        html: `Yakin ingin menghapus rute: <strong>${name}</strong>?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        reverseButtons: true
      }).then((result) => {
        if (result.isConfirmed) {
          $.post('sm_rute.php', {action:'delete', id: id})
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
    $('#rmForm').on('submit', function(e){
      e.preventDefault();
      // simple client validation
      if (!$('#Name').val().trim() || !$('#Address').val().trim()) {
        Swal.fire({
          icon: 'warning',
          title: 'Perhatian',
          text: 'Nama dan Alamat wajib diisi.',
          confirmButtonColor: '#3085d6'
        });
        return;
      }
      const data = $(this).serialize() + '&action=save';
      $('#saveBtn').prop('disabled', true);
      $.post('sm_rute.php', data)
        .done(function(res){
          if (typeof res === 'string') {
            try { res = JSON.parse(res); } catch(e){}
          }
          if(res.ok){
            swalToast(res.msg || 'Tersimpan', 'success');
            rmModal.hide();
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

    // Tombol batal hanya menutup modal, tidak mereset saat EDIT
    $('.btn-cancel, #rmModal .btn-close').on('click', function(){
      rmModal.hide();
    });

    // Reset form hanya ketika modal benar-benar tersembunyi (ini meliputi setelah klik batal atau setelah simpan)
    $('#rmModal').on('hidden.bs.modal', function () {
      $('#rmForm')[0].reset();
      $('#RouteMasterId').val('');
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

<?php include '../../includes/footer.php'; ?>