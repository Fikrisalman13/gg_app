<?php
// ===================================================
// 1. INISIALISASI DAN KONFIGURASI
// ===================================================

// Mulai session
session_start();

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// ===================================================
// 3. VALIDASI AUTENTIKASI
// ===================================================
if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// ===================================================
// 4. KONEKSI DATABASE
// ===================================================
include '../koneksi.php';
if (!$conn) {
    $errors = sqlsrv_errors();
    error_log("Koneksi database gagal: " . print_r($errors, true));
    die("Terjadi kesalahan sistem. Silakan hubungi administrator.");
}

// ===================================================
// 5. IMPORT DEPENDENSI
// ===================================================
include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/menu_constants.php';
include '../includes/permissions.php';

// ===================================================
// 6. VALIDASI IZIN AKSES
// ===================================================
// requireAdd($conn, MENU_GROUP_ACCESS); // Uncomment jika ada konstanta ini

// ===================================================
// 7. PROSES SIMPAN DATA (DIPERBAIKI)
// ===================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    error_log("POST data received: " . print_r($_POST, true));
    
    $groupId = $_POST['groupId'] ?? null;
    $menuIds = $_POST['menuId'] ?? [];
    $canView = isset($_POST['canView']) ? 1 : 0;
    $canAdd = isset($_POST['canAdd']) ? 1 : 0;
    $canEdit = isset($_POST['canEdit']) ? 1 : 0;
    $canDelete = isset($_POST['canDelete']) ? 1 : 0;

    // Validate input
    if (empty($groupId) || empty($menuIds)) {
        $_SESSION['error'] = "Group dan Menu harus dipilih!";
        header('Location: hakaksesgroup_trustee.php');
        exit;
    }

    error_log("Processing Group ID: $groupId, Menu IDs: " . count($menuIds));
    error_log("Permissions - View: $canView, Add: $canAdd, Edit: $canEdit, Delete: $canDelete");

    try {
        // Cek apakah tabel SMGroupTrustee ada
        $checkTableSql = "SELECT TABLE_NAME 
                         FROM INFORMATION_SCHEMA.TABLES 
                         WHERE TABLE_NAME IN ('SMGroupTrustee', 'HakAksesGroup', 'GroupMenu', 'UserGroupMenu', 'GroupPermission')
                         AND TABLE_SCHEMA = 'dbo'";
        
        $checkTableStmt = sqlsrv_query($conn, $checkTableSql);
        
        if ($checkTableStmt === false) {
            throw new Exception("Error checking tables: " . print_r(sqlsrv_errors(), true));
        }
        
        $availableTables = [];
        while ($table = sqlsrv_fetch_array($checkTableStmt, SQLSRV_FETCH_ASSOC)) {
            $availableTables[] = $table['TABLE_NAME'];
        }
        
        error_log("Available tables: " . print_r($availableTables, true));
        
        if (empty($availableTables)) {
            throw new Exception("Tabel hak akses tidak ditemukan di database.");
        }
        
        // Gunakan tabel yang tersedia (prioritaskan SMGroupTrustee)
        $tableName = in_array('SMGroupTrustee', $availableTables) ? 'SMGroupTrustee' : $availableTables[0];
        error_log("Using table: $tableName");
        
        // Ambil struktur kolom dari tabel
        $colSql = "SELECT COLUMN_NAME, DATA_TYPE 
                  FROM INFORMATION_SCHEMA.COLUMNS 
                  WHERE TABLE_NAME = ? AND TABLE_SCHEMA = 'dbo'";
        
        $colStmt = sqlsrv_query($conn, $colSql, [$tableName]);
        $columns = [];
        $columnNames = [];
        
        if ($colStmt !== false) {
            while ($col = sqlsrv_fetch_array($colStmt, SQLSRV_FETCH_ASSOC)) {
                $columns[$col['COLUMN_NAME']] = $col['DATA_TYPE'];
                $columnNames[] = $col['COLUMN_NAME'];
            }
            sqlsrv_free_stmt($colStmt);
        }
        
        error_log("Table columns: " . print_r($columnNames, true));
        
        // Tentukan nama kolom berdasarkan tabel yang tersedia
        $groupIdCol = '';
        $menuIdCol = '';
        $canViewCol = '';
        $canAddCol = '';
        $canEditCol = '';
        $canDeleteCol = '';
        
        // Mapping kolom berdasarkan pola nama
        foreach ($columnNames as $col) {
            $lowerCol = strtolower($col);
            
            if (strpos($lowerCol, 'group') !== false && strpos($lowerCol, 'id') !== false) {
                $groupIdCol = $col;
            } elseif (strpos($lowerCol, 'menu') !== false && strpos($lowerCol, 'id') !== false) {
                $menuIdCol = $col;
            } elseif (strpos($lowerCol, 'canview') !== false || strpos($lowerCol, 'view') !== false) {
                $canViewCol = $col;
            } elseif (strpos($lowerCol, 'canadd') !== false || strpos($lowerCol, 'add') !== false) {
                $canAddCol = $col;
            } elseif (strpos($lowerCol, 'canedit') !== false || strpos($lowerCol, 'edit') !== false) {
                $canEditCol = $col;
            } elseif (strpos($lowerCol, 'candelete') !== false || strpos($lowerCol, 'delete') !== false) {
                $canDeleteCol = $col;
            }
        }
        
        error_log("Mapped columns - Group: $groupIdCol, Menu: $menuIdCol, View: $canViewCol, Add: $canAddCol, Edit: $canEditCol, Delete: $canDeleteCol");
        
        // Mulai transaksi
        sqlsrv_begin_transaction($conn);
        
        // Hapus hak akses yang ada untuk group ini (opsional, tergantung kebutuhan)
        // $deleteSql = "DELETE FROM dbo.$tableName WHERE $groupIdCol = ?";
        // $deleteStmt = sqlsrv_query($conn, $deleteSql, [$groupId]);
        
        // if (!$deleteStmt) {
        //     throw new Exception("Gagal menghapus hak akses lama: " . print_r(sqlsrv_errors(), true));
        // }
        
        // Proses setiap menu yang dipilih
        foreach ($menuIds as $menuId) {
            // Cek apakah record sudah ada
            $checkSql = "SELECT COUNT(*) as count FROM dbo.$tableName 
                        WHERE $groupIdCol = ? AND $menuIdCol = ?";
            
            $checkStmt = sqlsrv_query($conn, $checkSql, [$groupId, $menuId]);
            
            if ($checkStmt === false) {
                throw new Exception("Error checking existing record: " . print_r(sqlsrv_errors(), true));
            }
            
            $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
            $exists = $row['count'] > 0;
            sqlsrv_free_stmt($checkStmt);
            
            if ($exists) {
                // Update existing record jika ada kolom permission
                if ($canViewCol && $canAddCol && $canEditCol && $canDeleteCol) {
                    $updateSql = "UPDATE dbo.$tableName 
                                 SET $canViewCol = ?, $canAddCol = ?, $canEditCol = ?, $canDeleteCol = ? 
                                 WHERE $groupIdCol = ? AND $menuIdCol = ?";
                    
                    $updateParams = [$canView, $canAdd, $canEdit, $canDelete, $groupId, $menuId];
                    $updateStmt = sqlsrv_query($conn, $updateSql, $updateParams);
                    
                    if (!$updateStmt) {
                        throw new Exception("Gagal memperbarui hak akses: " . print_r(sqlsrv_errors(), true));
                    }
                    error_log("Updated permission for Group: $groupId, Menu: $menuId");
                }
            } else {
                // Insert new record
                if ($canViewCol && $canAddCol && $canEditCol && $canDeleteCol) {
                    // Tabel dengan permission lengkap
                    $insertSql = "INSERT INTO dbo.$tableName 
                                 ($groupIdCol, $menuIdCol, $canViewCol, $canAddCol, $canEditCol, $canDeleteCol) 
                                 VALUES (?, ?, ?, ?, ?, ?)";
                    
                    $insertParams = [$groupId, $menuId, $canView, $canAdd, $canEdit, $canDelete];
                } else {
                    // Tabel sederhana tanpa permission flags
                    $insertSql = "INSERT INTO dbo.$tableName ($groupIdCol, $menuIdCol) VALUES (?, ?)";
                    $insertParams = [$groupId, $menuId];
                }
                
                $insertStmt = sqlsrv_query($conn, $insertSql, $insertParams);
                
                if (!$insertStmt) {
                    throw new Exception("Gagal menambahkan hak akses: " . print_r(sqlsrv_errors(), true));
                }
                error_log("Inserted permission for Group: $groupId, Menu: $menuId");
            }
        }
        
        // Commit transaksi
        sqlsrv_commit($conn);
        
        $_SESSION['success'] = "Hak akses berhasil disimpan untuk " . count($menuIds) . " menu!";
        error_log("Successfully saved permissions for Group: $groupId");
        
        header('Location: hakaksesgroup.php');
        exit;
        
    } catch (Exception $e) {
        // Rollback transaksi jika terjadi error
        if ($conn) {
            sqlsrv_rollback($conn);
        }
        
        error_log("Error saving permissions: " . $e->getMessage());
        $_SESSION['error'] = "Gagal menyimpan hak akses: " . $e->getMessage();
        
        header('Location: hakaksesgroup_trustee.php');
        exit;
    }
}

// ===================================================
// 8. MENDAPATKAN DATA UNTUK FORM
// ===================================================
// Get all groups
$groupSql = "SELECT GroupId, GroupName FROM dbo.SMUserGroup ORDER BY GroupName";
$groupStmt = sqlsrv_query($conn, $groupSql);

if ($groupStmt === false) {
    $error = sqlsrv_errors();
    error_log("Error fetching groups: " . print_r($error, true));
    $_SESSION['error'] = "Terjadi kesalahan dalam memuat data group";
}

// Mendapatkan tema dari session
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<!-- ===================================================
    9. HTML: STRUKTUR HALAMAN
======================================================= -->

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Kelola Trustee</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="hakaksesgroup.php">Hak Akses Group</a></li>
                        <li class="breadcrumb-item active">Kelola Trustee</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title">Form Kelola Trustee</h3>
                </div>
                
                <div class="card-body">
                    <form method="POST" action="hakaksesgroup_trustee.php" id="trusteeForm">
                        <!-- Group Selection -->
                        <div class="form-group">
                            <label for="groupId">Group <span class="text-danger">*</span></label>
                            <select class="form-control select2" id="groupId" name="groupId" required>
                                <option value="">Pilih Group</option>
                                <?php 
                                if ($groupStmt !== false) {
                                    while ($group = sqlsrv_fetch_array($groupStmt, SQLSRV_FETCH_ASSOC)): ?>
                                        <option value="<?= htmlspecialchars($group['GroupId']) ?>">
                                            <?= htmlspecialchars($group['GroupName']) ?>
                                        </option>
                                    <?php endwhile;
                                    sqlsrv_free_stmt($groupStmt);
                                } else {
                                    echo '<option value="">Error loading groups</option>';
                                }
                                ?>
                            </select>
                        </div>
                        
                        <!-- Menu Loading -->
                        <div id="menuLoading" class="text-center py-3" style="display: none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="mt-2">Memuat daftar menu...</p>
                        </div>
                        
                        <!-- Menu Selection Container -->
                        <div id="menuSelectionContainer" style="display: none;">
                            <div id="menuInfo" class="mb-3"></div>
                            
                            <!-- All Available Menus -->
                            <div class="form-group">
                                <label>Pilih Menu <span id="selectedCount" class="badge badge-info">0</span></label>
                                <div class="card">
                                    <div class="card-body p-2" style="max-height: 300px; overflow-y: auto;">
                                        <div id="menuTree"></div>
                                    </div>
                                </div>
                                <small class="form-text text-muted">
                                    Centang menu yang ingin diberikan hak akses.
                                </small>
                            </div>
                            
                            <!-- Selected Menus (hidden field) -->
                            <div id="selectedMenusContainer"></div>
                        </div>
                        
                        <!-- Permission Checkboxes -->
                        <div class="card mt-3">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="card-title mb-0">Hak Akses</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" 
                                                   id="canView" name="canView" checked>
                                            <label class="custom-control-label" for="canView">
                                                <i class="fas fa-eye text-primary"></i> Can View
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" 
                                                   id="canAdd" name="canAdd">
                                            <label class="custom-control-label" for="canAdd">
                                                <i class="fas fa-plus text-success"></i> Can Add
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" 
                                                   id="canEdit" name="canEdit">
                                            <label class="custom-control-label" for="canEdit">
                                                <i class="fas fa-edit text-warning"></i> Can Edit
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" 
                                                   id="canDelete" name="canDelete">
                                            <label class="custom-control-label" for="canDelete">
                                                <i class="fas fa-trash text-danger"></i> Can Delete
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="form-group mt-4">
                            <button type="submit" class="btn btn-<?= $themeColor ?>">
                                <i class="fas fa-save"></i> Simpan Hak Akses
                            </button>
                            <a href="hakaksesgroup.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Kembali ke Daftar
                            </a>
                            <button type="button" class="btn btn-info" id="selectAllMenus">
                                <i class="fas fa-check-double"></i> Pilih Semua
                            </button>
                            <button type="button" class="btn btn-danger" id="clearAllMenus">
                                <i class="fas fa-trash"></i> Hapus Pilihan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
<!-- SweetAlert -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<!-- Script JavaScript yang DIPERBAIKI -->
<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap4',
        width: '100%',
        allowClear: true
    });

    // Show notifications
    <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Sukses!',
            text: "<?= addslashes($_SESSION['success']) ?>",
            timer: 3000,
            showConfirmButton: false
        });
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: "<?= addslashes($_SESSION['error']) ?>",
            timer: 3000,
            showConfirmButton: false
        });
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    // Variables untuk menyimpan data menu
    let availableMenus = [];
    
    // Load available menus when group is selected
    $('#groupId').on('change', function() {
        const groupId = $(this).val();
        
        if (!groupId) {
            resetMenuSelections();
            return;
        }
        
        // Show loading spinner
        showLoading(true);
        
        // Reset selections
        resetMenuSelections();
        
        // AJAX request to get available menus
        $.ajax({
            url: 'get_available_menus.php',
            type: 'GET',
            data: { groupId: groupId },
            dataType: 'json',
            success: function(response) {
                console.log('Menu response:', response);
                
                if (response.success) {
                    availableMenus = response.data || [];
                    
                    if (availableMenus.length === 0) {
                        $('#menuInfo').html(`
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> 
                                Tidak ada menu yang tersedia untuk group ini.
                            </div>
                        `).show();
                        
                        $('#menuSelectionContainer').show();
                        menuTreeContainer.html('<div class="alert alert-warning">Tidak ada menu yang tersedia.</div>');
                    } else {
                        // Render menu tree
                        renderMenuTree(availableMenus);
                        
                        // Show menu container
                        $('#menuSelectionContainer').show();
                        
                        // Show info about available menus
                        showMenuInfo(response);
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        text: response.error || 'Terjadi kesalahan saat memuat menu.',
                        timer: 3000,
                        showConfirmButton: false
                    });
                }
                showLoading(false);
            },
            error: function(xhr, status, error) {
                console.error('Error loading menus:', xhr.responseText);
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal!',
                    text: 'Terjadi kesalahan saat memuat menu.',
                    timer: 3000,
                    showConfirmButton: false
                });
                showLoading(false);
                resetMenuSelections();
            }
        });
    });
    
    // Render menu tree
    function renderMenuTree(menus) {
        const menuTreeContainer = $('#menuTree');
        const selectedMenusContainer = $('#selectedMenusContainer');
        
        menuTreeContainer.empty();
        selectedMenusContainer.empty();
        
        let html = '';
        
        // Group menus by parent
        const mainMenus = menus.filter(menu => !menu.ParentMenuId || menu.ParentMenuId === 0);
        const subMenus = menus.filter(menu => menu.ParentMenuId && menu.ParentMenuId !== 0);
        
        // Render main menus
        mainMenus.forEach(mainMenu => {
            // Get submenus for this main menu
            const childMenus = subMenus.filter(sub => sub.ParentMenuId == mainMenu.MenuId);
            
            html += `
                <div class="card mb-2">
                    <div class="card-header p-2" style="background-color: #e3f2fd;">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input main-menu-checkbox" 
                                   id="main_menu_${mainMenu.MenuId}" 
                                   data-menu-id="${mainMenu.MenuId}">
                            <label class="custom-control-label font-weight-bold text-primary" 
                                   for="main_menu_${mainMenu.MenuId}">
                                <i class="fas ${mainMenu.MenuIcon || 'fa-folder'} mr-1"></i>
                                ${escapeHtml(mainMenu.MenuName)}
                            </label>
                        </div>
                    </div>
            `;
            
            if (childMenus.length > 0) {
                html += `<div class="card-body p-2">`;
                childMenus.forEach(childMenu => {
                    html += `
                        <div class="custom-control custom-checkbox ml-4 mb-1">
                            <input type="checkbox" class="custom-control-input sub-menu-checkbox" 
                                   id="sub_menu_${childMenu.MenuId}" 
                                   data-menu-id="${childMenu.MenuId}"
                                   data-parent-id="${mainMenu.MenuId}">
                            <label class="custom-control-label" for="sub_menu_${childMenu.MenuId}">
                                <i class="fas ${childMenu.MenuIcon || 'fa-caret-right'} mr-1"></i>
                                ${escapeHtml(childMenu.MenuName)}
                            </label>
                        </div>
                    `;
                    
                    // Add hidden field for this submenu
                    selectedMenusContainer.append(
                        `<input type="hidden" name="menuId[]" id="hidden_menu_${childMenu.MenuId}" value="${childMenu.MenuId}" disabled>`
                    );
                });
                html += `</div>`;
            }
            
            html += `</div>`;
            
            // Add hidden field for main menu
            selectedMenusContainer.append(
                `<input type="hidden" name="menuId[]" id="hidden_menu_${mainMenu.MenuId}" value="${mainMenu.MenuId}" disabled>`
            );
        });
        
        menuTreeContainer.html(html);
        
        // Attach event handlers
        attachCheckboxEvents();
        
        // Update selected count
        updateSelectedCount();
    }
    
    // Attach checkbox event handlers
    function attachCheckboxEvents() {
        // Main menu checkbox handler
        $('.main-menu-checkbox').on('change', function() {
            const menuId = $(this).data('menu-id');
            const isChecked = $(this).is(':checked');
            const hiddenField = $(`#hidden_menu_${menuId}`);
            
            // Toggle hidden field
            hiddenField.prop('disabled', !isChecked);
            
            // Toggle all submenus under this main menu
            $(`[data-parent-id="${menuId}"]`).each(function() {
                const subMenuId = $(this).data('menu-id');
                const subHiddenField = $(`#hidden_menu_${subMenuId}`);
                
                $(this).prop('checked', isChecked);
                subHiddenField.prop('disabled', !isChecked);
            });
            
            updateSelectedCount();
        });
        
        // Submenu checkbox handler
        $('.sub-menu-checkbox').on('change', function() {
            const menuId = $(this).data('menu-id');
            const isChecked = $(this).is(':checked');
            const hiddenField = $(`#hidden_menu_${menuId}`);
            
            // Toggle hidden field
            hiddenField.prop('disabled', !isChecked);
            
            // Update parent checkbox state
            const parentId = $(this).data('parent-id');
            updateParentCheckboxState(parentId);
            
            updateSelectedCount();
        });
    }
    
    // Update parent checkbox state
    function updateParentCheckboxState(parentId) {
        const parentCheckbox = $(`#main_menu_${parentId}`);
        const subCheckboxes = $(`[data-parent-id="${parentId}"]`);
        
        const checkedCount = subCheckboxes.filter(':checked').length;
        const totalCount = subCheckboxes.length;
        
        if (checkedCount === 0) {
            parentCheckbox.prop('checked', false);
            parentCheckbox.prop('indeterminate', false);
        } else if (checkedCount === totalCount) {
            parentCheckbox.prop('checked', true);
            parentCheckbox.prop('indeterminate', false);
        } else {
            parentCheckbox.prop('checked', false);
            parentCheckbox.prop('indeterminate', true);
        }
    }
    
    // Update selected count
    function updateSelectedCount() {
        const selectedCount = $('input[name="menuId[]"]:not(:disabled)').length;
        $('#selectedCount').text(selectedCount);
    }
    
    // Show loading state
    function showLoading(isLoading) {
        if (isLoading) {
            $('#menuLoading').show();
            $('#menuSelectionContainer').hide();
        } else {
            $('#menuLoading').hide();
        }
    }
    
    // Reset all menu selections
    function resetMenuSelections() {
        $('#menuTree').empty();
        $('#selectedMenusContainer').empty();
        $('#menuSelectionContainer').hide();
        $('#selectedCount').text('0');
        availableMenus = [];
    }
    
    // Show menu info
    function showMenuInfo(response) {
        const totalAvailable = response.total_available || 0;
        const totalAssigned = response.total_assigned || 0;
        const totalAll = response.total_all || 0;
        
        $('#menuInfo').html(`
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> 
                Tersedia: <strong>${totalAvailable}</strong> menu 
                (${totalAssigned} sudah di-assign, total ${totalAll} menu)
            </div>
        `).show();
    }
    
    // Select all menus button
    $('#selectAllMenus').click(function() {
        $('.main-menu-checkbox, .sub-menu-checkbox').prop('checked', true);
        $('input[name="menuId[]"]').prop('disabled', false);
        updateSelectedCount();
    });
    
    // Clear all selections button
    $('#clearAllMenus').click(function() {
        $('.main-menu-checkbox, .sub-menu-checkbox').prop('checked', false);
        $('input[name="menuId[]"]').prop('disabled', true);
        updateSelectedCount();
    });
    
    // Form validation
    $('#trusteeForm').on('submit', function(e) {
        const groupId = $('#groupId').val();
        const selectedMenus = $('input[name="menuId[]"]:not(:disabled)').length;
        
        if (!groupId) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: 'Silakan pilih group terlebih dahulu.',
                confirmButtonColor: '#ffc107'
            });
            return false;
        }
        
        if (selectedMenus === 0) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: 'Silakan pilih minimal satu menu.',
                confirmButtonColor: '#ffc107'
            });
            return false;
        }
        
        return true;
    });
    
    // Helper function untuk escape HTML
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});
</script>