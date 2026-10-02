<?php
session_start();
ob_start();

// Error reporting untuk development
error_reporting(E_ALL);
ini_set('display_errors', 0);

include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// CEK LOGIN
if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

// CEK PERMISSION
define('USER_FINGERPRINT_MENU_ID', 97);

/**
 * Check user permissions dengan caching
 */
function checkUserPermissions($conn, $groupId, $menuId) {
    static $permissionsCache = [];
    
    $cacheKey = $groupId . '_' . $menuId;
    if (isset($permissionsCache[$cacheKey])) {
        return $permissionsCache[$cacheKey];
    }
    
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete 
            FROM dbo.SMGroupTrustee 
            WHERE GroupId=? AND MenuId=?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    
    $permissions = ['CanView'=>0,'CanAdd'=>0,'CanEdit'=>0,'CanDelete'=>0];
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    
    $permissionsCache[$cacheKey] = $permissions;
    return $permissions;
}

$permissions = checkUserPermissions($conn, $_SESSION['GroupId'], USER_FINGERPRINT_MENU_ID);
if ($permissions['CanView'] != 1) {
    header('Location: ../dashboard.php');
    exit;
}

/**
 * Get all mesin dengan caching
 */
function getAllMesin($conn) {
    static $mesinCache = null;
    
    if ($mesinCache !== null) {
        return $mesinCache;
    }
    
    $sql = "SELECT * FROM dbo.m_fingerprint ORDER BY nama_mesin";
    $stmt = sqlsrv_query($conn, $sql);
    $mesin = [];
    
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $mesin[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
    
    $mesinCache = $mesin;
    return $mesin;
}

// Main processing untuk halaman utama
try {
    $mesin = getAllMesin($conn);
    
    // Validasi dan sanitize input
    $selectedMesinId = isset($_GET['mesin_id']) ? (int)$_GET['mesin_id'] : '';
    if ($selectedMesinId && !in_array($selectedMesinId, array_column($mesin, 'id'))) {
        $selectedMesinId = '';
    }
    
} catch (Exception $e) {
    // Log error
    error_log("Error in user_fingerprint_database: " . $e->getMessage());
    
    // Untuk non-AJAX, set default values
    $mesin = [];
    $selectedMesinId = '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Fingerprint Database</title>
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
  
  <style>
    .action-buttons { margin-bottom: 15px; }
    .table-checkbox { width: 40px; text-align: center; }
    .selected-count { 
        background-color: #e9ecef; 
        padding: 8px 15px; 
        border-radius: 4px; 
        margin-left: 10px; 
        display: inline-block; 
    }
    .badge-template { font-size: 0.75em; }
    .fingerprint-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 5px;
    }
    .has-fingerprint { background-color: #28a745; }
    .no-fingerprint { background-color: #6c757d; }
    .dataTables_wrapper {
        position: relative;
    }
    .password-field {
        font-family: monospace;
    }
    .selection-persistence {
        background-color: #fff3cd !important;
    }
    .loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255,255,255,0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
    }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid d-flex justify-content-between">
        <h1>User Fingerprint Database</h1>
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item"><a href="user_fingerprint.php">Fingerprint</a></li>
          <li class="breadcrumb-item active">Database</li>
        </ol>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">

        <!-- Filter Form -->
        <form method="GET" class="mb-3">
          <div class="row">
            <div class="col-md-4">
              <label>Filter Mesin:</label>
              <select name="mesin_id" class="form-control select2bs4" onchange="this.form.submit()">
                <option value="">-- Semua Mesin --</option>
                <?php foreach ($mesin as $m): ?>
                  <option value="<?= $m['id'] ?>" <?= $selectedMesinId == $m['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($m['nama_mesin']) ?> (<?= $m['ip_address'] ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </form>

        <div class="card">
          <div class="card-header bg-<?= htmlspecialchars($themeColor) ?>">
            <h3 class="card-title">Data User di Database</h3>
            <div class="card-tools">
              <span class="selected-count badge badge-light" id="selectedCount" style="display:none">0 dipilih</span>
            </div>
          </div>
          <div class="card-body">
            <!-- Action Buttons -->
            <div class="action-buttons" id="actionButtons" style="display:none">
              <?php if ($permissions['CanDelete']): ?>
              <button class="btn btn-danger btn-sm" id="deleteSelectedBtn">
                <i class="fas fa-trash"></i> Hapus User Terpilih
              </button>
              <?php endif; ?>
              <button class="btn btn-success btn-sm" id="uploadToMachineBtn">
                <i class="fas fa-upload"></i> Upload ke Mesin Lain
              </button>
              <button class="btn btn-info btn-sm" id="uploadWithFingerprintBtn">
                <i class="fas fa-fingerprint"></i> Upload + Sidik Jari
              </button>
              <button class="btn btn-secondary btn-sm" id="clearSelectionBtn">
                <i class="fas fa-times"></i> Batalkan Pilihan
              </button>
            </div>
            
            <div class="table-responsive">
              <table id="userTable" class="table table-hover table-sm w-100">
                <thead class="thead-light">
                  <tr>
                    <th class="table-checkbox">
                      <input type="checkbox" id="selectAll">
                    </th>
                    <th>No</th>
                    <th>PIN</th>
                    <th>Nama</th>
                    <th>Privilege</th>
                    <th>Password</th>
                    <th>Mesin</th>
                    <th>Sidik Jari</th>
                    <th>Template Size</th>
                    <th>Created</th>
                    <th>Updated</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Data akan di-load via AJAX -->
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </section>
  </div>
</div>

<!-- Modal Upload ke Mesin Lain -->
<div class="modal fade" id="uploadModal" tabindex="-1" role="dialog" aria-labelledby="uploadModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="uploadModalLabel">Upload User ke Mesin</h5>
        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label for="targetMachine">Pilih Mesin Tujuan:</label>
          <select id="targetMachine" class="form-control">
            <option value="">-- Pilih Mesin --</option>
            <?php foreach ($mesin as $m): ?>
              <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama_mesin']) ?> (<?= $m['ip_address'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="includeFingerprint" checked>
            <label class="custom-control-label" for="includeFingerprint">Sertakan data sidik jari</label>
          </div>
          <small class="form-text text-muted" id="fingerprintInfo">
            Menyertakan template sidik jari akan membutuhkan waktu lebih lama
          </small>
        </div>
        <div class="selected-users-info">
          <p><strong>User yang akan diupload:</strong></p>
          <ul id="selectedUsersList" class="list-group"></ul>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="confirmUploadBtn">Upload</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Detail Sidik Jari -->
<div class="modal fade" id="fingerprintDetailModal" tabindex="-1" role="dialog" aria-labelledby="fingerprintDetailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= $themeColor ?> text-white">
        <h5 class="modal-title" id="fingerprintDetailModalLabel">Detail Sidik Jari</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="fingerprintDetailContent">
        <div class="text-center">
          <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Loading...</span>
          </div>
          <p>Memuat data sidik jari...</p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Edit User -->
<div class="modal fade" id="editUserModal" tabindex="-1" role="dialog" aria-labelledby="editUserModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="editUserForm">
          <input type="hidden" id="editUserId" name="user_id">
          <div class="form-group">
            <label for="editPin">PIN:</label>
            <input type="text" class="form-control" id="editPin" name="pin" required>
          </div>
          <div class="form-group">
            <label for="editName">Nama:</label>
            <input type="text" class="form-control" id="editName" name="name" required>
          </div>
          <div class="form-group">
            <label for="editPrivilege">Privilege:</label>
            <select class="form-control" id="editPrivilege" name="privilege" required>
              <option value="0">User</option>
              <option value="1">Admin</option>
              <option value="2">Super Admin</option>
            </select>
          </div>
          <div class="form-group">
            <label for="editPassword">Password:</label>
            <input type="text" class="form-control password-field" id="editPassword" name="password" placeholder="Kosongkan jika tidak ingin mengubah">
            <small class="form-text text-muted">Password akan ditampilkan dalam bentuk teks biasa</small>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-warning" id="confirmEditBtn">Update User</button>
      </div>
    </div>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>

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
$(function(){
  'use strict';
  
  // Cache DOM elements
  const $selectAll = $('#selectAll');
  const $selectedCount = $('#selectedCount');
  const $actionButtons = $('#actionButtons');
  
  // State management untuk multiple select across pages
  let selectedUsers = [];
  let dataTable;
  
  // Inisialisasi DataTable dengan server-side processing
  function initializeDataTable() {
    const mesinId = '<?= $selectedMesinId ?>';
    
    dataTable = $('#userTable').DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: 'user_fingerprint_database_ajax.php?action=get_users&mesin_id=' + mesinId,
        type: 'GET',
        dataType: 'json',
        error: function (xhr, error, thrown) {
          console.error('DataTables error:', error, thrown);
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Gagal memuat data: ' + (thrown || 'Unknown error')
          });
        }
      },
      columns: [
        { 
          data: null,
          orderable: false,
          searchable: false,
          className: 'table-checkbox',
          render: function(data, type, row) {
            return row[0];
          }
        },
        { data: 1, className: 'dt-body-center' },
        { data: 2 },
        { data: 3 },
        { data: 4 },
        { data: 5 },
        { data: 6 },
        { data: 7 },
        { data: 8 },
        { data: 9 },
        { data: 10 },
        { 
          data: 11,
          orderable: false,
          searchable: false,
          className: 'text-center'
        }
      ],
      responsive: true,
      lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
      pageLength: 10,
      language: {
        processing: "Memproses...",
        lengthMenu: "Tampilkan _MENU_ data per halaman",
        zeroRecords: "Tidak ada data yang ditemukan",
        info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
        infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
        infoFiltered: "(disaring dari _MAX_ total data)",
        search: "Cari:",
        paginate: {
          first: "Pertama",
          last: "Terakhir",
          next: "Berikutnya",
          previous: "Sebelumnya"
        }
      },
      order: [[1, 'asc']],
      drawCallback: function(settings) {
        // Update checkbox state berdasarkan selectedUsers
        updateCheckboxStates();
        
        // Update selectAll checkbox state
        updateSelectAllState();
      }
    });
  }
  
  // Fungsi untuk update checkbox states berdasarkan selectedUsers
  function updateCheckboxStates() {
    $('.user-checkbox').each(function() {
      const $checkbox = $(this);
      const userId = $checkbox.val();
      const rowIndex = $checkbox.data('row-index');
      
      // Cek apakah user ini ada di selectedUsers
      const isSelected = selectedUsers.some(user => user.id === userId);
      $checkbox.prop('checked', isSelected);
      
      // Update row styling
      const $row = $checkbox.closest('tr');
      if (isSelected) {
        $row.addClass('selection-persistence');
      } else {
        $row.removeClass('selection-persistence');
      }
      
      // Update data attributes
      const $rowCells = $row.find('td');
      const pin = $rowCells.eq(2).text();
      const name = $rowCells.eq(3).text();
      const fingerprintCell = $rowCells.eq(7).html();
      const hasFingerprint = fingerprintCell.includes('has-fingerprint');
      
      $checkbox.data('pin', pin);
      $checkbox.data('name', name);
      $checkbox.data('has-fingerprint', hasFingerprint ? '1' : '0');
    });
    
    updateSelectedCount();
  }
  
  // Fungsi untuk update selectAll checkbox state
  function updateSelectAllState() {
    const visibleCheckboxes = $('.user-checkbox:visible');
    const checkedVisibleCheckboxes = visibleCheckboxes.filter(':checked');
    
    if (visibleCheckboxes.length === 0) {
      $selectAll.prop('checked', false);
      $selectAll.prop('indeterminate', false);
    } else if (checkedVisibleCheckboxes.length === visibleCheckboxes.length) {
      $selectAll.prop('checked', true);
      $selectAll.prop('indeterminate', false);
    } else if (checkedVisibleCheckboxes.length > 0) {
      $selectAll.prop('checked', false);
      $selectAll.prop('indeterminate', true);
    } else {
      $selectAll.prop('checked', false);
      $selectAll.prop('indeterminate', false);
    }
  }
  
  $('.select2bs4').select2({ theme: 'bootstrap4' });

  // Optimized event handlers
  function updateSelectedCount() {
    const count = selectedUsers.length;
    const withFingerprint = selectedUsers.filter(user => user.has_fingerprint === '1').length;
    
    $selectedCount.text(`${count} dipilih (${withFingerprint} dengan sidik jari)`).toggle(count > 0);
    $actionButtons.toggle(count > 0);
    
    if (count > 0) {
      updateSelectedUsersList();
    }
  }
  
  function updateSelectedUsersList() {
    const withFingerprint = selectedUsers.filter(user => user.has_fingerprint === '1').length;
    const userList = $('#selectedUsersList');
    
    userList.empty();
    selectedUsers.forEach(user => {
      const fingerprintBadge = user.has_fingerprint === '1' ? 
        '<span class="badge badge-success ml-2">Sidik Jari</span>' : 
        '<span class="badge badge-secondary ml-2">Tanpa Sidik Jari</span>';
      
      userList.append(
        `<li class="list-group-item d-flex justify-content-between align-items-center">
          ${user.pin} - ${user.name} ${fingerprintBadge}
        </li>`
      );
    });
    
    // Update fingerprint info
    const $fingerprintInfo = $('#fingerprintInfo');
    if (withFingerprint > 0) {
      $fingerprintInfo.html(
        `<span class="text-success">
          <i class="fas fa-check-circle"></i> ${withFingerprint} user memiliki data sidik jari
        </span>`
      );
      $('#includeFingerprint').prop('disabled', false);
    } else {
      $fingerprintInfo.html(
        `<span class="text-warning">
          <i class="fas fa-exclamation-triangle"></i> Tidak ada user yang memiliki data sidik jari
        </span>`
      );
      $('#includeFingerprint').prop('checked', false).prop('disabled', true);
    }
  }
  
  // Event delegation untuk better performance
  $(document)
    .on('click', '#selectAll', function() {
      const isChecked = $(this).prop('checked');
      const currentPageUserIds = [];
      
      // Collect all user IDs on current page
      $('.user-checkbox').each(function() {
        currentPageUserIds.push($(this).val());
      });
      
      if (isChecked) {
        // Add all users from current page to selection
        $('.user-checkbox').each(function() {
          const $this = $(this);
          const userData = {
            id: $this.val(),
            pin: $this.data('pin'),
            name: $this.data('name'),
            has_fingerprint: $this.data('has-fingerprint')
          };
          
          // Only add if not already in selectedUsers
          if (!selectedUsers.some(user => user.id === userData.id)) {
            selectedUsers.push(userData);
          }
        });
      } else {
        // Remove only users from current page
        selectedUsers = selectedUsers.filter(user => !currentPageUserIds.includes(user.id));
      }
      
      updateCheckboxStates();
    })
    .on('change', '.user-checkbox', function() {
      const $this = $(this);
      const userData = {
        id: $this.val(),
        pin: $this.data('pin'),
        name: $this.data('name'),
        has_fingerprint: $this.data('has-fingerprint')
      };
      
      if ($this.prop('checked')) {
        // Add to selection
        if (!selectedUsers.some(user => user.id === userData.id)) {
          selectedUsers.push(userData);
        }
      } else {
        // Remove from selection
        selectedUsers = selectedUsers.filter(user => user.id !== userData.id);
      }
      
      updateSelectedCount();
      updateSelectAllState();
      
      // Update row styling
      const $row = $this.closest('tr');
      if ($this.prop('checked')) {
        $row.addClass('selection-persistence');
      } else {
        $row.removeClass('selection-persistence');
      }
    })
    .on('click', '#clearSelectionBtn', function() {
      selectedUsers = [];
      updateCheckboxStates();
    })
    .on('click', '.view-password-btn', function() {
      const password = $(this).data('password');
      Swal.fire({
        title: 'Password',
        html: `<div class="text-left">
                <p><strong>Password:</strong></p>
                <code class="password-field" style="font-size: 1.1em; background: #f8f9fa; padding: 10px; border-radius: 4px; display: block;">${password}</code>
              </div>`,
        icon: 'info',
        confirmButtonText: 'Tutup'
      });
    })
    .on('click', '.view-fingerprint-btn', function() {
      const userId = $(this).data('user-id');
      const pin = $(this).data('pin');
      
      $('#fingerprintDetailContent').html(`
        <div class="text-center">
          <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Loading...</span>
          </div>
          <p>Memuat data sidik jari...</p>
        </div>
      `);
      
      $('#fingerprintDetailModal').modal('show');
      
      loadFingerprintData(userId, pin);
    })
    .on('click', '.edit-user', function() {
      const userId = $(this).data('user-id');
      const pin = $(this).data('pin');
      const name = $(this).data('name');
      const privilege = $(this).data('privilege');
      const password = $(this).data('password') || '';
      
      $('#editUserId').val(userId);
      $('#editPin').val(pin);
      $('#editName').val(name);
      $('#editPrivilege').val(privilege);
      $('#editPassword').val(password);
      
      $('#editUserModal').modal('show');
    })
    .on('click', '.delete-user', function() {
      const userId = $(this).data('user-id');
      const pin = $(this).data('pin');
      deleteSingleUser(userId, pin);
    });

  // Delete single user function
  function deleteSingleUser(userId, pin) {
    Swal.fire({
      title: 'Hapus User?',
      text: `User dengan PIN ${pin} akan dihapus dari database`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Ya, Hapus!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: 'user_fingerprint_ajax.php',
          type: 'POST',
          data: {
            action: 'delete_single',
            user_id: userId
          },
          success: function(response) {
            if (response.status === 'success') {
              // Remove from selectedUsers if exists
              selectedUsers = selectedUsers.filter(user => user.id !== userId);
              
              Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: response.message
              }).then(() => {
                dataTable.ajax.reload();
              });
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: response.message
              });
            }
          },
          error: function() {
            Swal.fire({
              icon: 'error',
              title: 'Error!',
              text: 'Terjadi kesalahan saat menghapus user'
            });
          }
        });
      }
    });
  }

  // Load fingerprint data
  function loadFingerprintData(userId, pin) {
    $.ajax({
      url: 'user_fingerprint_ajax.php',
      type: 'POST',
      data: {
        action: 'get_fingerprint_from_db',
        user_id: userId
      },
      success: function(response) {
        if (response.status === 'success') {
          let html = `<h6>User: ${pin} - ${response.user_name}</h6>`;
          html += `<p class="text-success"><i class="fas fa-check-circle"></i> ${response.templates.length} template sidik jari ditemukan</p>`;
          html += '<table class="table table-sm table-bordered">';
          html += '<thead><tr><th>Finger ID</th><th>Size</th><th>Valid</th><th>Template Preview</th></tr></thead><tbody>';
          
          response.templates.forEach(template => {
            const templatePreview = template.template.length > 30 ? 
              template.template.substring(0, 30) + '...' : template.template;
            
            html += `<tr>
              <td>${template.finger_id}</td>
              <td>${template.size} bytes</td>
              <td><span class="badge badge-success">Valid</span></td>
              <td><code style="cursor:pointer;" title="Full Template: ${template.template}">${templatePreview}</code></td>
            </tr>`;
          });
          
          html += '</tbody></table>';
          $('#fingerprintDetailContent').html(html);
        } else {
          $('#fingerprintDetailContent').html(`
            <div class="alert alert-warning">
              <i class="fas fa-exclamation-triangle"></i> ${response.message}
            </div>
          `);
        }
      },
      error: function() {
        $('#fingerprintDetailContent').html(`
          <div class="alert alert-danger">
            <i class="fas fa-times-circle"></i> Gagal memuat data sidik jari
          </div>
        `);
      }
    });
  }

  // Event handler untuk edit user
  $('#confirmEditBtn').on('click', function() {
    const formData = {
      action: 'update_user',
      user_id: $('#editUserId').val(),
      pin: $('#editPin').val(),
      name: $('#editName').val(),
      privilege: $('#editPrivilege').val(),
      password: $('#editPassword').val()
    };
    
    if (!formData.pin || !formData.name) {
      Swal.fire({icon:'warning',title:'PIN dan Nama harus diisi!'});
      return;
    }
    
    $.ajax({
      url: 'user_fingerprint_ajax.php',
      type: 'POST',
      data: formData,
      success: function(response) {
        if (response.status === 'success') {
          Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: response.message
          }).then(() => {
            $('#editUserModal').modal('hide');
            dataTable.ajax.reload();
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: response.message
          });
        }
      },
      error: function() {
        Swal.fire({
          icon: 'error',
          title: 'Error!',
          text: 'Terjadi kesalahan saat mengupdate user'
        });
      }
    });
  });

  // Event handlers untuk action buttons
  $('#deleteSelectedBtn').on('click', function() {
    if (selectedUsers.length === 0) {
      Swal.fire({icon:'warning',title:'Pilih user terlebih dahulu!'});
      return;
    }
    
    Swal.fire({
      title: 'Hapus User?',
      html: `Anda akan menghapus <strong>${selectedUsers.length}</strong> user dari database.<br>Tindakan ini tidak dapat dibatalkan!`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Ya, Hapus!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: 'user_fingerprint_ajax.php',
          type: 'POST',
          data: {
            action: 'delete_multiple',
            users: JSON.stringify(selectedUsers)
          },
          success: function(response) {
            if (response.status === 'success') {
              Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: response.message
              }).then(() => {
                // Clear selection after successful deletion
                selectedUsers = [];
                dataTable.ajax.reload();
              });
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                html: response.message + (response.errors ? '<br><small>' + response.errors.join('<br>') + '</small>' : '')
              });
            }
          },
          error: function() {
            Swal.fire({
              icon: 'error',
              title: 'Error!',
              text: 'Terjadi kesalahan saat menghapus user'
            });
          }
        });
      }
    });
  });

  $('#uploadToMachineBtn').on('click', function() {
    if (selectedUsers.length === 0) {
      Swal.fire({icon:'warning',title:'Pilih user terlebih dahulu!'});
      return;
    }
    
    $('#includeFingerprint').prop('checked', false);
    $('#uploadModal').modal('show');
  });

  $('#uploadWithFingerprintBtn').on('click', function() {
    if (selectedUsers.length === 0) {
      Swal.fire({icon:'warning',title:'Pilih user terlebih dahulu!'});
      return;
    }
    
    const withFingerprint = selectedUsers.filter(user => user.has_fingerprint === '1').length;
    if (withFingerprint === 0) {
      Swal.fire({
        icon: 'warning',
        title: 'Tidak ada sidik jari',
        text: 'Tidak ada user yang memiliki data sidik jari untuk diupload'
      });
      return;
    }
    
    $('#includeFingerprint').prop('checked', true);
    $('#uploadModal').modal('show');
  });

  $('#confirmUploadBtn').on('click', function() {
    const targetMachineId = $('#targetMachine').val();
    const includeFingerprint = $('#includeFingerprint').prop('checked');
    
    if (!targetMachineId) {
      Swal.fire({icon:'warning',title:'Pilih mesin tujuan!'});
      return;
    }
    
    $('#uploadModal').modal('hide');
    
    const action = includeFingerprint ? 'upload_with_fingerprint_to_machine' : 'upload_to_machine';
    const title = includeFingerprint ? 'Upload User dengan Sidik Jari?' : 'Upload User?';
    const text = includeFingerprint ? 
      `Anda akan mengupload ${selectedUsers.length} user beserta data sidik jari ke mesin tujuan` :
      `Anda akan mengupload ${selectedUsers.length} user ke mesin tujuan`;
    
    Swal.fire({
      title: title,
      text: text,
      icon: 'info',
      showCancelButton: true,
      confirmButtonColor: '#28a745',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Ya, Upload!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        // Mulai proses upload
        startUploadProcess(action, targetMachineId);
      }
    });
  });

  // Fungsi untuk memulai proses upload
  function startUploadProcess(action, targetMachineId) {
    // Tampilkan loading indicator
    const $loadingOverlay = $('<div class="loading-overlay"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div></div>');
    $('body').append($loadingOverlay);
    
    // Kirim request upload
    $.ajax({
      url: 'user_fingerprint_ajax.php',
      type: 'POST',
      data: {
        action: action,
        users: JSON.stringify(selectedUsers),
        target_machine_id: targetMachineId
      },
      success: function(response) {
        $loadingOverlay.remove();
        
        if (response.status === 'success') {
          Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: response.message
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            html: response.message + (response.errors ? '<br><small>' + response.errors.slice(0, 3).join('<br>') + '</small>' : '')
          });
        }
      },
      error: function(xhr, status, error) {
        $loadingOverlay.remove();
        Swal.fire({
          icon: 'error',
          title: 'Error!',
          text: 'Terjadi kesalahan saat mengupload user: ' + error
        });
      }
    });
  }

  // Initialize DataTable
  initializeDataTable();

  // Notifikasi session
  <?php if(isset($_SESSION['success'])): ?>
    Swal.fire({
      icon: 'success',
      title: 'Berhasil!',
      text: '<?= addslashes($_SESSION['success']) ?>',
      timer: 2000,
      showConfirmButton: false
    });
    <?php unset($_SESSION['success']); ?>
  <?php endif; ?>
  <?php if(isset($_SESSION['error'])): ?>
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: '<?= addslashes($_SESSION['error']) ?>',
      timer: 2000,
      showConfirmButton: false
    });
    <?php unset($_SESSION['error']); ?>
  <?php endif; ?>
});
</script>