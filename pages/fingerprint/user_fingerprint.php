<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// ================= CEK LOGIN ================= //
if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
define('USER_FINGERPRINT_MENU_ID', 97);

// ================= CEK PERMISSION ================= //
function checkUserPermissions($conn, $groupId, $menuId) {
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete 
            FROM dbo.SMGroupTrustee 
            WHERE GroupId=? AND MenuId=?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    return ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))
        ? $row : ['CanView'=>0,'CanAdd'=>0,'CanEdit'=>0,'CanDelete'=>0];
}
$permissions = checkUserPermissions($conn, $_SESSION['GroupId'], USER_FINGERPRINT_MENU_ID);
if ($permissions['CanView'] != 1) {
    header('Location: ../dashboard.php');
    exit;
}

// ================= AMBIL DATA MESIN ================= //
$mesin = [];
$stmt = sqlsrv_query($conn, "SELECT id,nama_mesin,ip_address FROM dbo.m_fingerprint ORDER BY id DESC");
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $mesin[] = $row;
    }
}
?>

<div class="wrapper">
  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
          <h1>User Fingerprint</h1>
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
            <li class="breadcrumb-item active">User Fingerprint</li>
          </ol>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card shadow">
          <div class="card-header bg-<?= htmlspecialchars($themeColor) ?>">
            <h3 class="card-title"><i class="fas fa-list mr-1"></i>
            Data User Fingerprint</h3>
            <div class="card-tools">
              <?php if ($permissions['CanAdd']): ?>
                <a href="add_user_fingerprint.php" class="btn btn-light" title="Tambah User">
                  <i class="fas fa-user-plus"></i>
                </a>
                <a href="user_fingerprint_database.php" class="btn btn-info" title="Lihat Database">
                  <i class="fas fa-database"></i>
                </a>
              <?php endif; ?>
            </div>
          </div>
          <div class="card-body">
            <form id="formMesin" class="form-inline mb-3">
              <label class="mr-2">Pilih Mesin:</label>
              <select id="mesinSelect" class="form-control">
                <option value="">-- Pilih Mesin --</option>
                <?php foreach ($mesin as $m): ?>
                  <option value="<?= $m['id'] ?>" data-ip="<?= $m['ip_address'] ?>">
                    <?= htmlspecialchars($m['nama_mesin']) ?> (<?= $m['ip_address'] ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <button type="button" id="btnLoadUser" class="btn btn-<?= htmlspecialchars($themeColor) ?> ml-2" title="Load Users">
                <i class="fas fa-sync"></i>
              </button> 
              <button type="button" id="btnSync" class="btn btn-success ml-2 d-none" title="Sync ke Database">
                <i class="fas fa-download"></i>
              </button>
              <button type="button" id="btnSyncWithFingerprint" class="btn btn-warning ml-2 d-none" title="Sync dengan Sidik Jari">
                <i class="fas fa-fingerprint"></i>
              </button>
              <button type="button" id="btnCheckAllFingerprint" class="btn btn-info ml-2 d-none" title="Cek Sidik Jari">
                <i class="fas fa-search"></i>
              </button>
            </form>

            <!-- Action Buttons for Multiple Selection -->
            <div class="action-buttons d-none" id="multipleActionButtons">
              <div class="d-flex align-items-center">
                <span class="font-weight-bold mr-3" id="selectedCount">0 user terpilih</span>
                <button type="button" id="btnSyncSelected" class="btn btn-success btn-sm" title="Sync Selected">
                  <i class="fas fa-download"></i>
                </button>
                <button type="button" id="btnSyncSelectedWithFingerprint" class="btn btn-warning btn-sm" title="Sync Selected dengan Sidik Jari">
                  <i class="fas fa-fingerprint"></i>
                </button>
                <?php if ($permissions['CanDelete']): ?>
                <button type="button" id="btnDeleteSelected" class="btn btn-danger btn-sm" title="Hapus Selected">
                  <i class="fas fa-trash"></i>
                </button>
                <?php endif; ?>
                <button type="button" id="btnClearSelection" class="btn btn-secondary btn-sm" title="Clear Selection">
                  <i class="fas fa-times"></i>
                </button>
              </div>
            </div>

            <div class="table-responsive">
              <table id="userTable" class="table table-hover table-sm">
                <thead class="thead-light">
                  <tr>
                    <th width="30">
                      <div class="select-all-container">
                        <input type="checkbox" id="selectAll" title="Pilih Semua">
                      </div>
                    </th>
                    <th>No</th>
                    <th>PIN</th>
                    <th>Nama</th>
                    <th>Password</th>
                    <th>Privilege</th>
                    <th>Status Sidik Jari</th>
                    <th>Template Count</th>
                    <th width="80">Aksi</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</div>

<!-- Modal Fingerprint -->
<div class="modal fade" id="fingerprintModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?>">
        <h5 class="modal-title"><i class="fas fa-fingerprint"></i> Data Sidik Jari</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="fingerprintData">
        <p class="text-muted">Tidak ada data untuk ditampilkan</p>
      </div>
    </div>
  </div>
</div>

<!-- ===================================================
    10. IMPORT FOOTER
======================================================= -->
<?php include '../../includes/footer.php'; ?>
<!-- ===================================================
    11. JAVASCRIPT LIBRARIES
======================================================= -->
<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<!-- DataTables JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<!-- SweetAlert -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
$(function() {
  const table = $('#userTable').DataTable({
        responsive: true,
        autoWidth: false
    });

  const showAlert = (type, msg) => Swal.fire({icon: type, title: msg, timer: 2500, showConfirmButton: false});
  const setLoading = (btn, text) => btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> ' + (text || ''));
  const resetLoading = (btn, icon, text) => btn.prop('disabled', false).html('<i class="' + icon + '"></i> ' + (text || ''));

  // Notifikasi session
  <?php if (isset($_SESSION['swal_success'])): ?>
    showAlert('success', '<?= $_SESSION['swal_success'] ?>');
    <?php unset($_SESSION['swal_success']); ?>
  <?php endif; ?>
  <?php if (isset($_SESSION['swal_error'])): ?>
    showAlert('error', '<?= $_SESSION['swal_error'] ?>');
    <?php unset($_SESSION['swal_error']); ?>
  <?php endif; ?>

  let fingerprintData = {};
  let selectedUsers = new Set();
  let allUsers = [];

  // Load User
  $('#btnLoadUser').on('click', function() {
    const mesinId = $('#mesinSelect').val(), ip = $('#mesinSelect option:selected').data('ip');
    if (!mesinId) return showAlert('error', 'Pilih mesin terlebih dahulu!');
    
    setLoading($(this), 'Loading...');
    fingerprintData = {}; // reset cache
    selectedUsers.clear();
    updateSelectionUI();
    
    $.post('user_fingerprint_ajax.php', {action: 'load', mesin_id: mesinId, ip: ip}, res => {
      table.clear().draw();
      allUsers = res.data || [];
      
      if (res.data?.length) {
        res.data.forEach((u, i) => {
          const pin = u.PIN2 || u.PIN || '';
          const name = u.Name || '';
          
          table.row.add([
            `<input type="checkbox" class="user-checkbox" data-index="${i}" data-pin="${pin}" title="Pilih user">`,
            i + 1, 
            pin, 
            name,
            u.Password || '', 
            u.Privilege || '',
            '<span class="badge badge-secondary status-badge">Belum Dicek</span>',
            '<span class="badge badge-secondary">0</span>',
            `<div class="table-actions">
              <?php if($permissions['CanDelete']): ?>
                <button class="btn btn-info btn-sm view-fingerprint" data-pin="${pin}" title="Lihat Sidik Jari">
                  <i class="fas fa-fingerprint"></i>
                </button>
                <button class="btn btn-danger btn-sm delete" data-pin="${pin}" title="Hapus User">
                  <i class="fas fa-trash"></i>
                </button>
              <?php endif; ?>
            </div>`
          ]);
        });
        
        table.draw(false); 
        showAlert('success', res.data.length + ' user ditemukan');
        $('#btnSync, #btnSyncWithFingerprint, #btnCheckAllFingerprint').removeClass('d-none').data('users', res.data);
      } else {
        table.row.add(['', '', 'Tidak ada data', '', '', '', '', '', '']).draw(false);
        showAlert('info', 'Tidak ada data user');
        $('#btnSync, #btnSyncWithFingerprint, #btnCheckAllFingerprint').addClass('d-none');
      }
    }, 'json').fail(() => showAlert('error', 'Gagal memuat data'))
     .always(() => resetLoading($('#btnLoadUser'), 'fas fa-sync'));
  });

  // Multiple Selection Functions
  $('#selectAll').on('change', function() {
    const isChecked = $(this).prop('checked');
    $('.user-checkbox').prop('checked', isChecked);
    
    if (isChecked) {
      // Add all users to selection
      allUsers.forEach((user, index) => {
        const pin = user.PIN2 || user.PIN || '';
        if (pin) selectedUsers.add(index);
      });
    } else {
      selectedUsers.clear();
    }
    updateSelectionUI();
    updateRowSelection();
  });

  // Individual checkbox change
  $('#userTable').on('change', '.user-checkbox', function() {
    const index = parseInt($(this).data('index'));
    const isChecked = $(this).prop('checked');
    
    if (isChecked) {
      selectedUsers.add(index);
    } else {
      selectedUsers.delete(index);
    }
    
    // Update select all checkbox
    const totalUsers = allUsers.length;
    const selectedCount = selectedUsers.size;
    $('#selectAll').prop('checked', selectedCount === totalUsers && totalUsers > 0);
    
    updateSelectionUI();
    updateRowSelection();
  });

  function updateSelectionUI() {
    const selectedCount = selectedUsers.size;
    $('#selectedCount').text(selectedCount + ' user terpilih');
    
    if (selectedCount > 0) {
      $('#multipleActionButtons').removeClass('d-none');
    } else {
      $('#multipleActionButtons').addClass('d-none');
    }
  }

  function updateRowSelection() {
    $('#userTable tbody tr').each(function(index) {
      const $row = $(this);
      const checkbox = $row.find('.user-checkbox');
      const isSelected = selectedUsers.has(parseInt(checkbox.data('index')));
      
      if (isSelected) {
        $row.addClass('selected-row');
      } else {
        $row.removeClass('selected-row');
      }
    });
  }

  function getSelectedUsers() {
    const selected = [];
    selectedUsers.forEach(index => {
      if (allUsers[index]) {
        selected.push(allUsers[index]);
      }
    });
    return selected;
  }

  // Clear selection
  $('#btnClearSelection').on('click', function() {
    selectedUsers.clear();
    $('.user-checkbox').prop('checked', false);
    $('#selectAll').prop('checked', false);
    updateSelectionUI();
    updateRowSelection();
  });

  // Sync Selected Users
  $('#btnSyncSelected').on('click', function() {
    const mesinId = $('#mesinSelect').val();
    const selectedUsers = getSelectedUsers();
    
    if (!mesinId) return showAlert('error', 'Pilih mesin terlebih dahulu!');
    if (selectedUsers.length === 0) return showAlert('error', 'Pilih user terlebih dahulu!');
    
    setLoading($(this), '');
    $.post('user_fingerprint_ajax.php', {
      action: 'download', 
      mesin_id: mesinId, 
      users: JSON.stringify(selectedUsers)
    }, resp => {
      resetLoading($('#btnSyncSelected'), 'fas fa-download');
      resp?.status === 'success' ? showAlert('success', resp.message) : showAlert('error', resp.message);
    }, 'json').fail(() => {
      resetLoading($('#btnSyncSelected'), 'fas fa-download');
      showAlert('error', 'Gagal sync');
    });
  });

  // Sync Selected dengan Sidik Jari
  $('#btnSyncSelectedWithFingerprint').on('click', function() {
    const mesinId = $('#mesinSelect').val();
    const selectedUsers = getSelectedUsers();
    
    if (!mesinId) return showAlert('error', 'Pilih mesin terlebih dahulu!');
    if (selectedUsers.length === 0) return showAlert('error', 'Pilih user terlebih dahulu!');
    
    Swal.fire({
      title: 'Sync Selected dengan Sidik Jari',
      html: `Akan sync <strong>${selectedUsers.length} user</strong> dengan data sidik jari.<br>
             <small>Proses ini mungkin memakan waktu beberapa menit.</small>`,
      icon: 'warning', 
      showCancelButton: true,
      confirmButtonText: 'Ya, Lanjutkan',
      cancelButtonText: 'Batal'
    }).then(r => {
      if (r.isConfirmed) {
        setLoading($(this), '');
        $.post('user_fingerprint_ajax.php', {
          action: 'download_with_fingerprint', 
          mesin_id: mesinId, 
          users: JSON.stringify(selectedUsers)
        }, resp => {
          resetLoading($('#btnSyncSelectedWithFingerprint'), 'fas fa-fingerprint');
          resp?.status === 'success' ? showAlert('success', resp.message) : showAlert('error', resp.message);
        }, 'json').fail(() => {
          resetLoading($('#btnSyncSelectedWithFingerprint'), 'fas fa-fingerprint');
          showAlert('error', 'Gagal sync fingerprint');
        });
      }
    });
  });

  // Hapus Selected Users
  <?php if ($permissions['CanDelete']): ?>
  $('#btnDeleteSelected').on('click', function() {
    const mesinId = $('#mesinSelect').val();
    const selectedUsers = getSelectedUsers();
    
    if (!mesinId) return showAlert('error', 'Pilih mesin terlebih dahulu!');
    if (selectedUsers.length === 0) return showAlert('error', 'Pilih user terlebih dahulu!');
    
    const userList = selectedUsers.map(user => 
      `• ${user.Name} (PIN: ${user.PIN2 || user.PIN})`
    ).join('<br>');
    
    Swal.fire({
      title: 'Hapus User Terpilih?',
      html: `Akan menghapus <strong>${selectedUsers.length} user</strong>:<br>${userList}`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Ya, Hapus!',
      cancelButtonText: 'Batal',
      confirmButtonColor: '#dc3545'
    }).then(r => {
      if (r.isConfirmed) {
        setLoading($(this), '');
        
        // Delete users one by one
        let deletedCount = 0;
        let errorCount = 0;
        const totalUsers = selectedUsers.length;
        
        const deleteNextUser = (index) => {
          if (index >= totalUsers) {
            // All users processed
            resetLoading($('#btnDeleteSelected'), 'fas fa-trash');
            if (errorCount > 0) {
              showAlert('warning', `Berhasil menghapus ${deletedCount} user, gagal ${errorCount} user`);
            } else {
              showAlert('success', `Berhasil menghapus ${deletedCount} user`);
            }
            $('#btnLoadUser').click(); // Reload data
            return;
          }
          
          const user = selectedUsers[index];
          const pin = user.PIN2 || user.PIN || '';
          
          $.post('user_fingerprint_ajax.php', {
            action: 'delete', 
            mesin_id: mesinId, 
            pin: pin
          }, resp => {
            if (resp?.status === 'success') {
              deletedCount++;
            } else {
              errorCount++;
            }
            deleteNextUser(index + 1);
          }, 'json').fail(() => {
            errorCount++;
            deleteNextUser(index + 1);
          });
        };
        
        deleteNextUser(0);
      }
    });
  });
  <?php endif; ?>

  // Cek semua fingerprint
  $('#btnCheckAllFingerprint').on('click', function() {
    const mesinId = $('#mesinSelect').val();
    const users = $(this).data('users') || [];
    if (!mesinId || !users.length) return showAlert('error', 'Tidak ada data user untuk dicek');
    
    Swal.fire({
      title: 'Cek Sidik Jari',
      text: `Akan mengecek sidik jari untuk ${users.length} user. Proses ini mungkin memakan waktu.`,
      icon: 'info', 
      showCancelButton: true,
      confirmButtonText: 'Ya, Lanjutkan', 
      cancelButtonText: 'Batal'
    }).then(r => { 
      if (r.isConfirmed) checkAllFingerprints(mesinId, users); 
    });
  });

  function checkAllFingerprints(mesinId, users) {
    let processed = 0, total = users.length;
    setLoading($('#btnCheckAllFingerprint'), `0/${total}`);
    
    users.forEach((user, i) => {
      const pin = user.PIN2 || user.PIN || '';
      if (!pin) { 
        processed++; 
        updateProgress(processed, total); 
        return; 
      }
      
      setTimeout(() => {
        $.post('user_fingerprint_ajax.php', {action: 'get_fingerprint', mesin_id: mesinId, pin: pin}, resp => {
          processed++; 
          updateProgress(processed, total);
          const hasFp = resp?.status === 'success' && resp.templates.length > 0;
          fingerprintData[pin] = {hasFingerprint: hasFp, count: resp?.templates?.length || 0};
          updateRow(pin, fingerprintData[pin]);
          if (processed >= total) finishCheck(total);
        }, 'json').fail(() => {
          processed++; 
          updateProgress(processed, total);
          fingerprintData[pin] = {hasFingerprint: false, count: 0, error: true};
          updateRow(pin, fingerprintData[pin]);
          if (processed >= total) finishCheck(total);
        });
      }, i * 400);
    });
  }

  function updateProgress(p, t) { 
    $('#btnCheckAllFingerprint').html(`<i class="fas fa-spinner fa-spin"></i> ${p}/${t}`); 
  }
  
  function finishCheck(total) { 
    resetLoading($('#btnCheckAllFingerprint'), 'fas fa-search'); 
  }
  
  function updateRow(pin, data) {
    $('#userTable tbody tr').each(function() {
      if ($(this).find('td:eq(2)').text().trim() === pin) {
        $(this).find('td:eq(6)').html(
          data.hasFingerprint ? 
          '<span class="badge badge-success status-badge">Ada</span>' : 
          '<span class="badge badge-danger status-badge">Tidak Ada</span>'
        );
        $(this).find('td:eq(7)').html(
          `<span class="badge ${data.count > 0 ? 'badge-info' : 'badge-secondary'}">${data.count}</span>`
        );
      }
    });
  }

  // Hapus user single
  $('#userTable').on('click', '.delete', function() {
    const $btn = $(this), pin = $btn.data('pin'), mesinId = $('#mesinSelect').val(), 
          name = $btn.closest('tr').find('td:eq(3)').text();
    
    if (!mesinId) return showAlert('error', 'Pilih mesin terlebih dahulu!');
    
    Swal.fire({
      title: 'Yakin hapus?',
      text: `User "${name}" dengan PIN ${pin} akan dihapus!`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Ya, Hapus!',
      cancelButtonText: 'Batal'
    }).then(r => {
      if (r.isConfirmed) {
        setLoading($btn, ''); 
        $.post('user_fingerprint_ajax.php', {action: 'delete', mesin_id: mesinId, pin: pin}, resp => {
          resetLoading($btn, 'fas fa-trash');
          resp?.status === 'success' ? 
            (showAlert('success', resp.message), $('#btnLoadUser').click()) : 
            showAlert('error', resp.message || 'Gagal hapus');
        }, 'json').fail(() => {
          resetLoading($btn, 'fas fa-trash');
          showAlert('error', 'Terjadi kesalahan');
        });
      }
    });
  });

  // Sync ke DB (all)
  $('#btnSync').on('click', function() {
    const mesinId = $('#mesinSelect').val(), users = $(this).data('users') || [];
    if (!mesinId || !users.length) return showAlert('error', 'Tidak ada data untuk disinkron');
    
    setLoading($(this), '');
    $.post('user_fingerprint_ajax.php', {action: 'download', mesin_id: mesinId, users: JSON.stringify(users)}, resp => {
      resetLoading($('#btnSync'), 'fas fa-download');
      resp?.status === 'success' ? showAlert('success', resp.message) : showAlert('error', resp.message);
    }, 'json').fail(() => {
      resetLoading($('#btnSync'), 'fas fa-download');
      showAlert('error', 'Gagal sync');
    });
  });

  // Sync dengan Sidik Jari (all)
  $('#btnSyncWithFingerprint').on('click', function() {
    const mesinId = $('#mesinSelect').val(), users = $(this).data('users') || [];
    if (!mesinId || !users.length) return showAlert('error', 'Tidak ada data untuk disinkron');
    
    Swal.fire({
      title: 'Sync dengan Sidik Jari',
      text: 'Proses ini akan mengambil data sidik jari dari mesin. Mungkin butuh beberapa menit.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Ya, Lanjutkan'
    }).then(r => {
      if (r.isConfirmed) {
        setLoading($(this), '');
        $.post('user_fingerprint_ajax.php', {action: 'download_with_fingerprint', mesin_id: mesinId, users: JSON.stringify(users)}, resp => {
          resetLoading($('#btnSyncWithFingerprint'), 'fas fa-fingerprint');
          resp?.status === 'success' ? showAlert('success', resp.message) : showAlert('error', resp.message);
        }, 'json').fail(() => {
          resetLoading($('#btnSyncWithFingerprint'), 'fas fa-fingerprint');
          showAlert('error', 'Gagal sync fingerprint');
        });
      }
    });
  });

  // Fungsi untuk melihat template sidik jari
  function viewFingerprint(pin, mesinId) {
    if (!mesinId) {
      showAlert('error', 'Pilih mesin terlebih dahulu!');
      return;
    }
    
    setLoading($('#btnLoadUser'), '');
    
    $.post('user_fingerprint_ajax.php', {
      action: 'get_fingerprint',
      mesin_id: mesinId,
      pin: pin
    }, resp => {
      resetLoading($('#btnLoadUser'), 'fas fa-sync');
      
      if (resp?.status === 'success' && resp.templates.length > 0) {
        let html = `<h6>User PIN: ${pin}</h6>`;
        html += `<p class="text-success"><i class="fas fa-check-circle"></i> ${resp.message}</p>`;
        html += '<table class="table table-sm table-bordered">';
        html += '<thead><tr><th>Finger ID</th><th>Size</th><th>Valid</th><th>Template (preview)</th></tr></thead><tbody>';
        
        resp.templates.forEach(template => {
          const templatePreview = template.Template.length > 50 ? 
            template.Template.substring(0, 50) + '...' : template.Template;
          const isValid = template.Valid == '1' || template.Valid == 1;
          
          html += `<tr>
            <td>${template.FingerID}</td>
            <td>${template.Size} bytes</td>
            <td><span class="badge badge-${isValid ? 'success' : 'danger'}">${isValid ? 'Valid' : 'Tidak Valid'}</span></td>
            <td><code title="Full Template: ${template.Template}" style="cursor:pointer;">${templatePreview}</code></td>
          </tr>`;
        });
        
        html += '</tbody></table>';
        $('#fingerprintData').html(html);
        $('#fingerprintModal').modal('show');
        
        // Update data fingerprint lokal
        fingerprintData[pin] = {
          hasFingerprint: true,
          count: resp.templates.length,
          templates: resp.templates
        };
        
      } else {
        let errorMsg = resp?.message || 'Tidak ada template sidik jari ditemukan';
        let html = `<h6>User PIN: ${pin}</h6>`;
        html += `<p class="text-danger"><i class="fas fa-exclamation-triangle"></i> ${errorMsg}</p>`;
        $('#fingerprintData').html(html);
        $('#fingerprintModal').modal('show');
        
        // Update data fingerprint lokal
        fingerprintData[pin] = {
          hasFingerprint: false,
          count: 0,
          templates: []
        };
      }
    }, 'json').fail((xhr, status, error) => {
      resetLoading($('#btnLoadUser'), 'fas fa-sync');
      
      let html = `<h6>User PIN: ${pin}</h6>`;
      html += `<p class="text-danger"><i class="fas fa-times-circle"></i> Gagal mengambil data sidik jari</p>`;
      html += `<p>Error: ${error}</p>`;
      $('#fingerprintData').html(html);
      $('#fingerprintModal').modal('show');
      
      // Update data fingerprint lokal
      fingerprintData[pin] = {
        hasFingerprint: false,
        count: 0,
        templates: [],
        error: true
      };
    });
  }

  // Event handler untuk tombol view fingerprint
  $('#userTable').on('click', '.view-fingerprint', function() {
    const pin = $(this).data('pin');
    const mesinId = $('#mesinSelect').val();
    viewFingerprint(pin, mesinId);
  });

  // Auto load ketika pilih mesin
  $('#mesinSelect').on('change', function() { 
    if (this.value) {
      $('#btnLoadUser').click();
    }
  });
});
</script>
