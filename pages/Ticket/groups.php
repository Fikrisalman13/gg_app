<?php
// ===================================================
// 1. INISIALISASI DAN AUTENTIKASI
// ===================================================
session_start();
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/theme_helper.php';
if (!isset($_SESSION['UserId'])) { header('Location: /gg_app/login.php'); exit; }

// ===================================================
// 1.1 HAK AKSES MENU TICKET (ID 148)
// ===================================================
if (!function_exists('checkPermissions')) {
  function checkPermissions($conn, $groupId, $menuId) {
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    $permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
      $permissions = $row;
    }
    if ($stmt !== false) { sqlsrv_free_stmt($stmt); }
    return $permissions;
  }
}

$permissions = checkPermissions($conn, $_SESSION['GroupId'] ?? 0, 148);
if (($permissions['CanView'] ?? 0) != 1) {
  $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
  header('Location: ../dashboard.php');
  exit;
}

// ===================================================
// 2. LAYOUT & KONFIGURASI TEMA
// ===================================================
$includeTicketThemeCss = true;
$themeColor = ticket_normalize_theme($_SESSION['Theme'] ?? 'primary');
$GLOBALS['ticketThemeOverride'] = $themeColor;
$themeClassSafe = htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8');
$themeBtnClass = 'btn-' . $themeClassSafe;
$themeBgClass = 'bg-' . $themeClassSafe;
$themeTextClass = 'text-' . $themeClassSafe;
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';

// ===================================================
// 3. DATA AWAL: DAFTAR GRUP & TEKNISI
// ===================================================
// Fetch Groups
$groups = [];
$sqlG = "SELECT * FROM dbo.ticket_tech_groups ORDER BY group_name";
$stmtG = sqlsrv_query($conn, $sqlG);
while ($row = sqlsrv_fetch_array($stmtG, SQLSRV_FETCH_ASSOC)) {
    $groups[] = $row;
}

// Fetch All IT Techs for Dropdown
$techs = [];
$sqlT = "SELECT e.id_emp, e.nama_lengkap, j.jabatan 
         FROM dbo.m_emp e
         LEFT JOIN dbo.m_jab j ON e.id_jab = j.id_jab
         LEFT JOIN dbo.m_dept d ON e.id_dept = d.id_dept
         WHERE d.dept = 'Information Technology'
           AND e.aktif = 1
           AND NOT EXISTS (
                SELECT 1 FROM dbo.ticket_tech_members tm WHERE tm.id_emp = e.id_emp
           )
         ORDER BY e.nama_lengkap";
$stmtT = sqlsrv_query($conn, $sqlT);
while ($row = sqlsrv_fetch_array($stmtT, SQLSRV_FETCH_ASSOC)) {
    $techs[] = $row;
}
?>

<style>
  /* Fix Header Icon Clickability */
  .main-header { z-index: 1100 !important; position: relative; }
  /* Fix Modal z-index to prevent header overlap */
  .modal { z-index: 9999 !important; }
  .modal-backdrop { z-index: 9998 !important; }
  .group-item {
    border-left: 4px solid transparent;
    transition: background-color 0.2s ease, color 0.2s ease;
  }
  .group-item .btn {
    transition: opacity 0.15s ease;
  }
  .group-item.active .btn {
    opacity: 0.9;
  }
</style>

<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1 class="m-0">Manage Technician Groups</h1></div>
        <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="/gg_app/pages/ticket/list.php">Tickets</a></li><li class="breadcrumb-item active">Groups</li></ol></div>
      </div>
    </div>
  </div>

  <section class="content">
    <div class="container-fluid">
      <div class="row">
        <!-- LEFT: Groups List -->
        <div class="col-md-4">
          <div class="card card-<?php echo $themeColor; ?> card-outline">
            <div class="card-header bg-<?php echo htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8'); ?> text-white border-0">
              <h3 class="card-title">Groups</h3>
              <div class="card-tools">
                <?php if (!empty($permissions['CanAdd']) && $permissions['CanAdd'] == 1): ?>
                <button class="btn btn-sm <?php echo htmlspecialchars($themeBtnClass, ENT_QUOTES, 'UTF-8'); ?>" id="btnNewGroup"><i class="fas fa-plus"></i> New Group</button>
                <?php endif; ?>
              </div>
            </div>
            <div class="card-body p-0">
              <ul class="list-group list-group-flush" id="groupList">
                <?php foreach($groups as $g): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center group-item" data-id="<?php echo $g['id']; ?>" style="cursor:pointer">
                  <span class="font-weight-bold group-name"><?php echo htmlspecialchars($g['group_name']); ?></span>
                  <div>
                    <?php if (!empty($permissions['CanEdit']) && $permissions['CanEdit'] == 1): ?>
                    <button class="btn btn-xs btn-warning btn-edit-group" data-id="<?php echo $g['id']; ?>" data-name="<?php echo htmlspecialchars($g['group_name']); ?>"><i class="fas fa-edit"></i></button>
                    <?php endif; ?>
                    <?php if (!empty($permissions['CanDelete']) && $permissions['CanDelete'] == 1): ?>
                    <button class="btn btn-xs btn-danger btn-del-group" data-id="<?php echo $g['id']; ?>"><i class="fas fa-trash"></i></button>
                    <?php endif; ?>
                  </div>
                </li>
                <?php endforeach; ?>
                <?php if(empty($groups)): ?>
                <li class="list-group-item text-center text-muted">Belum ada grup</li>
                <?php endif; ?>
              </ul>
            </div>
          </div>
        </div>

        <!-- RIGHT: Members List -->
        <div class="col-md-8">
          <div class="card card-<?php echo $themeColor; ?> card-outline">
            <div class="card-header bg-<?php echo htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8'); ?> text-white border-0">
              <h3 class="card-title">Members: <span id="selectedGroupName" class="font-weight-bold text-<?php echo $themeClassSafe; ?>">Select a Group</span></h3>
              <div class="card-tools">
                <?php if (!empty($permissions['CanEdit']) && $permissions['CanEdit'] == 1): ?>
                <button class="btn btn-sm <?php echo htmlspecialchars($themeBtnClass, ENT_QUOTES, 'UTF-8'); ?>" id="btnAddMember" disabled><i class="fas fa-user-plus"></i> Add Member</button>
                <?php endif; ?>
              </div>
            </div>
            <div class="card-body table-responsive p-0">
              <table class="table table-hover text-nowrap">
                <thead>
                  <tr>
                    <th>Nama</th>
                    <th>Jabatan</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody id="memberTableBody">
                  <tr><td colspan="3" class="text-center text-muted">Pilih grup di sebelah kiri untuk melihat anggota</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<!-- Modal Add Group -->
<div class="modal fade" id="modalAddGroup" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-<?php echo $themeColor; ?> text-white">
        <h5 class="modal-title">Tambah Grup Baru</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group"><label>Nama Grup</label><input type="text" class="form-control" id="newGroupName"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button type="button" class="btn <?php echo htmlspecialchars($themeBtnClass, ENT_QUOTES, 'UTF-8'); ?>" id="btnSaveGroup">Simpan</button></div>
    </div>
  </div>
</div>

<!-- Modal Edit Group -->
<div class="modal fade" id="modalEditGroup" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-<?php echo $themeColor; ?> text-white">
        <h5 class="modal-title">Edit Grup</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="editGroupId">
        <div class="form-group"><label>Nama Grup</label><input type="text" class="form-control" id="editGroupName"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button type="button" class="btn <?php echo htmlspecialchars($themeBtnClass, ENT_QUOTES, 'UTF-8'); ?>" id="btnUpdateGroup">Update</button></div>
    </div>
  </div>
</div>

<!-- Modal Add Member -->
<div class="modal fade" id="modalAddMember" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-<?php echo $themeColor; ?> text-white">
        <h5 class="modal-title">Tambah Anggota</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group">
            <label>Pilih Teknisi</label>
            <select class="form-control" id="selectTech">
                <option value="">-- Pilih --</option>
                <?php foreach($techs as $t): ?>
                <option value="<?php echo $t['id_emp']; ?>"><?php echo htmlspecialchars($t['nama_lengkap']); ?> - <?php echo htmlspecialchars($t['jabatan']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button type="button" class="btn <?php echo htmlspecialchars($themeBtnClass, ENT_QUOTES, 'UTF-8'); ?>" id="btnSaveMember">Tambah</button></div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
<script>
  window.groupPermissions = {
    canAdd: <?php echo (($permissions['CanAdd'] ?? 0) == 1) ? 'true' : 'false'; ?>,
    canEdit: <?php echo (($permissions['CanEdit'] ?? 0) == 1) ? 'true' : 'false'; ?>,
    canDelete: <?php echo (($permissions['CanDelete'] ?? 0) == 1) ? 'true' : 'false'; ?>
  };
  window.ticketGroupTheme = {
    bgClass: '<?php echo htmlspecialchars($themeBgClass, ENT_QUOTES, 'UTF-8'); ?>',
    btnClass: '<?php echo htmlspecialchars($themeBtnClass, ENT_QUOTES, 'UTF-8'); ?>'
  };
</script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>
<script>
$(function(){
    var groupPermissions = window.groupPermissions || { canAdd:false, canEdit:false, canDelete:false };
    var selectedGroupId = 0;

  function refreshAvailableTechs() {
    var $select = $('#selectTech');
    $select.prop('disabled', true).html('<option value="">Memuat...</option>');
    $.getJSON('get_technicians.php', { available_only: 1 })
      .done(function(resp){
        var options = ['<option value="">-- Pilih --</option>'];
        if (resp && resp.success && Array.isArray(resp.data) && resp.data.length) {
          resp.data.forEach(function(t){
            var label = t.nama_lengkap;
            if (t.jabatan) {
              label += ' - ' + t.jabatan;
            }
            options.push('<option value="'+t.id_emp+'">'+label+'</option>');
          });
        } else {
          options = ['<option value="">Tidak ada teknisi tersedia</option>'];
        }
        $select.html(options.join(''));
      })
      .fail(function(){
        $select.html('<option value="">Gagal memuat teknisi</option>');
      })
      .always(function(){
        $select.prop('disabled', false);
      });
  }

  refreshAvailableTechs();

    // Open Add Group Modal
    $('#btnNewGroup').click(function(){
        if (!groupPermissions.canAdd) { Swal.fire('Akses ditolak','Anda tidak memiliki izin menambah grup.','warning'); return; }
        $('#newGroupName').val(''); // Clear input
        $('#modalAddGroup').modal('show');
    });

    // --- Group Actions ---
    $('#btnSaveGroup').click(function(){
      if (!groupPermissions.canAdd) { Swal.fire('Akses ditolak','Anda tidak memiliki izin menambah grup.','warning'); return; }
        var name = $('#newGroupName').val();
        if(!name) return Swal.fire('Error','Nama grup harus diisi','error');
        
        console.log('Sending request to add group:', name);
        
        $.post('group_action.php', { action:'add_group', group_name:name }, function(r){
            console.log('Response:', r);
            if(r.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: 'Grup berhasil dibuat',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('Error', r.message, 'error');
            }
        }, 'json').fail(function(xhr, status, error){
            console.error('AJAX Error:', status, error);
            console.error('Response:', xhr.responseText);
            Swal.fire('Error', 'Gagal menghubungi server: ' + error, 'error');
        });
    });

    $('.btn-edit-group').click(function(e){
        e.stopPropagation();
      if (!groupPermissions.canEdit) { Swal.fire('Akses ditolak','Anda tidak memiliki izin mengedit grup.','warning'); return; }
        var id = $(this).data('id');
        var name = $(this).data('name');
        $('#editGroupId').val(id);
        $('#editGroupName').val(name);
        $('#modalEditGroup').modal('show');
    });

    $('#btnUpdateGroup').click(function(){
      if (!groupPermissions.canEdit) { Swal.fire('Akses ditolak','Anda tidak memiliki izin mengedit grup.','warning'); return; }
        var id = $('#editGroupId').val();
        var name = $('#editGroupName').val();
        $.post('group_action.php', { action:'edit_group', id:id, group_name:name }, function(r){
            if(r.success) location.reload();
            else Swal.fire('Error', r.message, 'error');
        }, 'json');
    });

    $('.btn-del-group').click(function(e){
        e.stopPropagation();
      if (!groupPermissions.canDelete) { Swal.fire('Akses ditolak','Anda tidak memiliki izin menghapus grup.','warning'); return; }
        var id = $(this).data('id');
        Swal.fire({
            title: 'Hapus Grup?', text: 'Semua anggota akan dihapus dari grup ini.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, Hapus'
        }).then((res) => {
            if(res.isConfirmed) {
                $.post('group_action.php', { action:'delete_group', id:id }, function(r){
                    if(r.success) location.reload();
                    else Swal.fire('Error', r.message, 'error');
                }, 'json');
            }
        });
    });

    // --- Select Group ---
    var groupTheme = window.ticketGroupTheme || {};
    var themeBgClass = groupTheme.bgClass || 'bg-primary';

    $('.group-item').click(function(){
      $('.group-item').removeClass('active text-white bg-light').removeClass(themeBgClass);
      $(this).addClass('active text-white').addClass(themeBgClass);
        selectedGroupId = $(this).data('id');
        var name = $(this).find('.group-name').text();
        $('#selectedGroupName').text(name);
        $('#btnAddMember').prop('disabled', false);
        loadMembers(selectedGroupId);
    });

    // --- Member Actions ---
    // Mengambil daftar anggota untuk grup yang dipilih
    function loadMembers(groupId) {
        $.post('group_action.php', { action:'get_members', group_id:groupId }, function(r){
            if(r.success) {
                var html = '';
                if(r.data.length === 0) {
                    html = '<tr><td colspan="3" class="text-center text-muted">Belum ada anggota</td></tr>';
                } else {
                    r.data.forEach(function(m){
                        html += '<tr>' +
                            '<td>' + m.nama_lengkap + '</td>' +
                            '<td>' + (m.jabatan || '-') + '</td>' +
                            '<td><button class="btn btn-xs btn-danger btn-del-member" data-id="' + m.id + '"><i class="fas fa-trash"></i></button></td>' +
                            '</tr>';
                    });
                }
                $('#memberTableBody').html(html);
            }
        }, 'json');
    }

    // Open Add Member Modal
    $('#btnAddMember').click(function(){
      if(!$(this).prop('disabled')) {
        if (!groupPermissions.canEdit) { Swal.fire('Akses ditolak','Anda tidak memiliki izin menambah anggota.','warning'); return; }
            refreshAvailableTechs();
            $('#selectTech').val('');
            $('#modalAddMember').modal('show');
        }
    });

    $('#btnSaveMember').click(function(){
      if (!groupPermissions.canEdit) { Swal.fire('Akses ditolak','Anda tidak memiliki izin menambah anggota.','warning'); return; }
        var empId = $('#selectTech').val();
        if(!empId) return Swal.fire('Error','Pilih teknisi','error');
        $.post('group_action.php', { action:'add_member', group_id:selectedGroupId, id_emp:empId }, function(r){
            if(r.success) {
                $('#modalAddMember').modal('hide');
                loadMembers(selectedGroupId);
            refreshAvailableTechs();
                Swal.fire('Success', 'Anggota ditambahkan', 'success');
            } else {
                Swal.fire('Error', r.message, 'error');
            }
        }, 'json');
    });

    $(document).on('click', '.btn-del-member', function(){
      if (!groupPermissions.canDelete && !groupPermissions.canEdit) { Swal.fire('Akses ditolak','Anda tidak memiliki izin menghapus anggota.','warning'); return; }
        var id = $(this).data('id');
        Swal.fire({
            title: 'Hapus Anggota?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, Hapus'
        }).then((res) => {
            if(res.isConfirmed) {
                $.post('group_action.php', { action:'remove_member', id:id }, function(r){
                  if(r.success) {
                    loadMembers(selectedGroupId);
                    refreshAvailableTechs();
                  }
                    else Swal.fire('Error', r.message, 'error');
                }, 'json');
            }
        });
    });
});
</script>
