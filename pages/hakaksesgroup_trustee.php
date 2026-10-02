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
requireAdd($conn, MENU_GROUP_ACCESS);

if (empty($_SESSION['hakaksesgroup_trustee_csrf'])) {
    $_SESSION['hakaksesgroup_trustee_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['hakaksesgroup_trustee_csrf'];

// ===================================================
// 7. PROSES SIMPAN DATA
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = bin2hex(random_bytes(8));
    $postedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    $groupId = filter_input(INPUT_POST, 'groupId', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $menuIds = isset($_POST['menuId']) && is_array($_POST['menuId']) ? $_POST['menuId'] : [];
    $postedPermissions = isset($_POST['permissions']) && is_array($_POST['permissions']) ? $_POST['permissions'] : [];

    if ($postedToken === '' || !hash_equals($csrfToken, $postedToken)) {
        $_SESSION['error'] = "Token keamanan tidak valid. Request ID: $requestId";
        header('Location: hakaksesgroup_trustee.php');
        exit;
    }

    $menuIds = array_values(array_unique(array_map('intval', $menuIds)));
    $menuIds = array_values(array_filter($menuIds, function ($menuId) {
        return $menuId > 0;
    }));

    if (!$groupId || empty($menuIds)) {
        $_SESSION['error'] = "Group dan minimal satu Menu harus dipilih!";
        header('Location: hakaksesgroup_trustee.php');
        exit;
    }

    $permissionsByMenu = [];
    foreach ($menuIds as $menuId) {
        $permission = $postedPermissions[$menuId] ?? null;
        if (!is_array($permission)) {
            $_SESSION['error'] = "Data hak akses menu tidak lengkap. Request ID: $requestId";
            header('Location: hakaksesgroup_trustee.php');
            exit;
        }

        foreach (['CanView', 'CanAdd', 'CanEdit', 'CanDelete'] as $column) {
            $value = filter_var($permission[$column] ?? null, FILTER_VALIDATE_INT);
            if (!in_array($value, [0, 1], true)) {
                $_SESSION['error'] = "Nilai hak akses tidak valid. Request ID: $requestId";
                header('Location: hakaksesgroup_trustee.php');
                exit;
            }
            $permissionsByMenu[$menuId][$column] = $value;
        }
    }

    if (!sqlsrv_begin_transaction($conn)) {
        $_SESSION['error'] = "Gagal memulai penyimpanan. Request ID: $requestId";
        header('Location: hakaksesgroup_trustee.php');
        exit;
    }

    try {
        $checkSql = "SELECT TrusteeId FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
        $updateSql = "UPDATE dbo.SMGroupTrustee
                      SET CanView = ?, CanAdd = ?, CanEdit = ?, CanDelete = ?
                      WHERE GroupId = ? AND MenuId = ?";
        $insertSql = "INSERT INTO dbo.SMGroupTrustee
                      (GroupId, MenuId, CanView, CanAdd, CanEdit, CanDelete)
                      VALUES (?, ?, ?, ?, ?, ?)";

        foreach ($menuIds as $menuId) {
            $permission = $permissionsByMenu[$menuId];
            $checkStmt = sqlsrv_query($conn, $checkSql, [$groupId, $menuId]);
            if ($checkStmt === false) {
                throw new RuntimeException('Gagal memeriksa data hak akses.');
            }

            $exists = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC) !== null;
            sqlsrv_free_stmt($checkStmt);

            if ($exists) {
                $params = [
                    $permission['CanView'], $permission['CanAdd'],
                    $permission['CanEdit'], $permission['CanDelete'],
                    $groupId, $menuId
                ];
                $stmt = sqlsrv_query($conn, $updateSql, $params);
            } else {
                $params = [
                    $groupId, $menuId, $permission['CanView'],
                    $permission['CanAdd'], $permission['CanEdit'], $permission['CanDelete']
                ];
                $stmt = sqlsrv_query($conn, $insertSql, $params);
            }

            if ($stmt === false) {
                throw new RuntimeException('Gagal menyimpan salah satu hak akses menu.');
            }
            sqlsrv_free_stmt($stmt);
        }

        if (!sqlsrv_commit($conn)) {
            throw new RuntimeException('Gagal menyelesaikan transaksi hak akses.');
        }

        $_SESSION['success'] = "Hak akses berhasil disimpan untuk " . count($menuIds) . " menu!";
        header('Location: hakaksesgroup.php');
        exit;
    } catch (Throwable $e) {
        sqlsrv_rollback($conn);
        $logDirectory = dirname(__DIR__) . '/logs';
        if (!is_dir($logDirectory)) {
            @mkdir($logDirectory, 0775, true);
        }
        $entry = [
            'timestamp' => date(DATE_ATOM),
            'severity' => 'ERROR',
            'request_id' => $requestId,
            'module' => 'hakaksesgroup',
            'action' => 'bulk_save_permissions',
            'user' => substr((string) ($_SESSION['UserName'] ?? ''), 0, 40),
            'message' => 'Bulk permission save failed',
            'source_file' => basename(__FILE__),
            'source_line' => $e->getLine(),
            'context' => [
                'stage' => 'transaction',
                'exception_class' => get_class($e),
                'group_id' => $groupId,
                'menu_count' => count($menuIds),
                'dependency' => 'SQL Server GG'
            ]
        ];
        @file_put_contents(
            $logDirectory . '/error-' . date('Y-m-d') . '.log',
            json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
        $_SESSION['error'] = "Gagal menyimpan hak akses. Request ID: $requestId";
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
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
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

                                <!-- Search box for menus -->
                                <div class="input-group mb-2">
                                    <input type="text" id="menuSearch" class="form-control" placeholder="Cari menu atau parent...">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" id="clearMenuSearch">Clear</button>
                                    </div>
                                </div>

                                <div class="card">
                                    <div class="card-body p-2" style="max-height: 300px; overflow-y: auto;" id="menuTreeScroll">
                                        <div id="menuTree"></div>
                                    </div>
                                </div>
                                <small class="form-text text-muted">
                                    Centang menu yang ingin diberikan hak akses.
                                </small>
                            </div>
                            
                            <!-- Selected menus and explicit permissions are populated before submit -->
                            <div id="selectedMenusContainer"></div>
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
    let filteredMenus = [];
    const selectedMenuIds = new Set();
    const permissionsByParent = {};

    function defaultPermissions() {
        return { CanView: 1, CanAdd: 0, CanEdit: 0, CanDelete: 0 };
    }

    function getParentId(menu) {
        return String(menu.ParentMenuId && menu.ParentMenuId !== 0 ? menu.ParentMenuId : menu.MenuId);
    }

    function rememberVisiblePermissions() {
        $('.permission-options').each(function() {
            const parentId = String(this.id.replace('perms_', ''));
            permissionsByParent[parentId] = {
                CanView: $(`#v_${parentId}`).is(':checked') ? 1 : 0,
                CanAdd: $(`#a_${parentId}`).is(':checked') ? 1 : 0,
                CanEdit: $(`#e_${parentId}`).is(':checked') ? 1 : 0,
                CanDelete: $(`#d_${parentId}`).is(':checked') ? 1 : 0
            };
        });
    }
    
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
                    filteredMenus = availableMenus.slice();
                    // Hidden payload is built once during submit.
                    $('#selectedMenusContainer').empty();
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
                        renderMenuTree(filteredMenus);
                        
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
        menuTreeContainer.empty();
        
        let html = '';
        
        // Group menus by parent
        const mainMenus = menus.filter(menu => !menu.ParentMenuId || menu.ParentMenuId === 0);
        const subMenus = menus.filter(menu => menu.ParentMenuId && menu.ParentMenuId !== 0);
        
        // Render main menus
        mainMenus.forEach(mainMenu => {
            const childMenus = subMenus.filter(sub => sub.ParentMenuId == mainMenu.MenuId);
            const isAssigned = mainMenu.AlreadyAssigned;

            html += `
                <div class="card mb-2 border-primary">
                    <div class="card-header p-2 d-flex align-items-center justify-content-between" style="background-color: ${isAssigned ? '#f8f9fa' : '#e3f2fd'};">
                        <div class="custom-control custom-checkbox flex-grow-1">
                            <input type="checkbox" class="custom-control-input main-menu-checkbox" 
                                   id="main_menu_${mainMenu.MenuId}" 
                                   data-menu-id="${mainMenu.MenuId}"
                                   ${isAssigned ? 'disabled' : ''}>
                            <label class="custom-control-label font-weight-bold ${isAssigned ? 'text-muted' : 'text-primary'}" 
                                   for="main_menu_${mainMenu.MenuId}">
                                <i class="fas ${mainMenu.MenuIcon || 'fa-folder'} mr-1"></i>
                                ${escapeHtml(mainMenu.MenuName)}
                                ${isAssigned ? '<span class="badge badge-secondary ml-1">assigned</span>' : ''}
                            </label>
                        </div>
                        <div class="permission-options" id="perms_${mainMenu.MenuId}" style="display: none;">
                            <div class="btn-group btn-group-sm ml-2">
                                <div class="custom-control custom-checkbox custom-control-inline mr-1">
                                    <input type="checkbox" class="custom-control-input perm-cb" id="v_${mainMenu.MenuId}" name="canView[${mainMenu.MenuId}]" value="1" checked>
                                    <label class="custom-control-label" for="v_${mainMenu.MenuId}"><i class="fas fa-eye text-primary"></i></label>
                                </div>
                                <div class="custom-control custom-checkbox custom-control-inline mr-1">
                                    <input type="checkbox" class="custom-control-input perm-cb" id="a_${mainMenu.MenuId}" name="canAdd[${mainMenu.MenuId}]" value="1">
                                    <label class="custom-control-label" for="a_${mainMenu.MenuId}"><i class="fas fa-plus text-success"></i></label>
                                </div>
                                <div class="custom-control custom-checkbox custom-control-inline mr-1">
                                    <input type="checkbox" class="custom-control-input perm-cb" id="e_${mainMenu.MenuId}" name="canEdit[${mainMenu.MenuId}]" value="1">
                                    <label class="custom-control-label" for="e_${mainMenu.MenuId}"><i class="fas fa-edit text-warning"></i></label>
                                </div>
                                <div class="custom-control custom-checkbox custom-control-inline">
                                    <input type="checkbox" class="custom-control-input perm-cb" id="d_${mainMenu.MenuId}" name="canDelete[${mainMenu.MenuId}]" value="1">
                                    <label class="custom-control-label" for="d_${mainMenu.MenuId}"><i class="fas fa-trash text-danger"></i></label>
                                </div>
                            </div>
                        </div>
                    </div>
            `;

            if (childMenus.length > 0) {
                html += `<div class="card-body p-2 bg-light">`;
                childMenus.forEach(childMenu => {
                    const isChildAssigned = childMenu.AlreadyAssigned;
                    html += `
                        <div class="d-flex align-items-center justify-content-between ml-4 mb-2 p-1 border-bottom">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input sub-menu-checkbox" 
                                       id="sub_menu_${childMenu.MenuId}" 
                                       data-menu-id="${childMenu.MenuId}"
                                       data-parent-id="${mainMenu.MenuId}"
                                       ${isChildAssigned ? 'disabled' : ''}>
                                <label class="custom-control-label ${isChildAssigned ? 'text-muted' : ''}" for="sub_menu_${childMenu.MenuId}">
                                    <i class="fas ${childMenu.MenuIcon || 'fa-caret-right'} mr-1"></i>
                                    ${escapeHtml(childMenu.MenuName)}
                                    ${isChildAssigned ? '<span class="badge badge-secondary ml-1">assigned</span>' : ''}
                                </label>
                            </div>
                        </div>
                    `;
                });
                html += `</div>`;
            }

            html += `</div>`;
        });
        
        menuTreeContainer.html(html);

        menus.forEach(function(menu) {
            const menuId = String(menu.MenuId);
            const parentId = getParentId(menu);
            const permission = permissionsByParent[parentId] || defaultPermissions();
            const checkbox = $(`#main_menu_${menuId}, #sub_menu_${menuId}`);
            checkbox.prop('checked', selectedMenuIds.has(menuId));
            $(`#v_${parentId}`).prop('checked', permission.CanView === 1);
            $(`#a_${parentId}`).prop('checked', permission.CanAdd === 1);
            $(`#e_${parentId}`).prop('checked', permission.CanEdit === 1);
            $(`#d_${parentId}`).prop('checked', permission.CanDelete === 1);
            $(`#perms_${parentId}`).toggle(selectedMenuIds.has(parentId));
        });
        
        // Attach event handlers (ensure no duplicate bindings)
        attachCheckboxEvents();
        
        // Update selected count
        updateSelectedCount();
    }

    // Debounce helper
    function debounce(fn, delay) {
        let t = null;
        return function() {
            const args = arguments;
            clearTimeout(t);
            t = setTimeout(function() {
                fn.apply(null, args);
            }, delay);
        };
    }

    // Filter availableMenus by search term and re-render
    function filterAndRender(term) {
        rememberVisiblePermissions();
        if (!term) {
            filteredMenus = availableMenus.slice();
        } else {
            const q = term.trim().toLowerCase();
            // First, find direct matches
            const matches = availableMenus.filter(m => {
                const name = (m.MenuName || '').toString().toLowerCase();
                const parent = (m.ParentMenuName || '').toString().toLowerCase();
                const display = (m.DisplayName || '').toString().toLowerCase();
                return name.includes(q) || parent.includes(q) || display.includes(q);
            });

            // Ensure that when a child matches, its parent is included in results
            const resultMap = {};
            matches.forEach(m => { resultMap[m.MenuId] = m; });
            matches.forEach(m => {
                if (m.ParentMenuId && m.ParentMenuId !== 0) {
                    const pid = m.ParentMenuId;
                    // find parent in availableMenus
                    const parent = availableMenus.find(x => x.MenuId == pid);
                    if (parent) resultMap[parent.MenuId] = parent;
                }
            });

            filteredMenus = Object.keys(resultMap).map(k => resultMap[k]);
        }

        renderMenuTree(filteredMenus);
    }

    const debouncedFilter = debounce(function() {
        const term = $('#menuSearch').val();
        filterAndRender(term);
    }, 250);

    // Wire search input
    $(document).on('input', '#menuSearch', debouncedFilter);
    $('#clearMenuSearch').on('click', function() {
        $('#menuSearch').val('');
        filterAndRender('');
    });
    
    // Attach checkbox event handlers
    function attachCheckboxEvents() {
        // Main menu checkbox handler
        $('.main-menu-checkbox').off('change').on('change', function() {
            const menuId = String($(this).data('menu-id'));
            const isChecked = $(this).is(':checked');
            if (isChecked) selectedMenuIds.add(menuId);
            else selectedMenuIds.delete(menuId);

            $(`#perms_${menuId}`).toggle(isChecked);
            
            // Toggle all submenus under this main menu
            $(`[data-parent-id="${menuId}"]`).each(function() {
                const childId = String($(this).data('menu-id'));
                $(this).prop('checked', isChecked);
                if (isChecked) selectedMenuIds.add(childId);
                else selectedMenuIds.delete(childId);
            });
            
            updateSelectedCount();
        });
        
        // Submenu checkbox handler
        $('.sub-menu-checkbox').off('change').on('change', function() {
            const menuId = String($(this).data('menu-id'));
            const isChecked = $(this).is(':checked');
            if (isChecked) selectedMenuIds.add(menuId);
            else selectedMenuIds.delete(menuId);
            
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
        if (!parentCheckbox.length) return;
        
        const checkedCount = subCheckboxes.filter(':checked').length;
        const totalCount = subCheckboxes.length;
        
        if (checkedCount > 0) {
            // Jika ada sub yang dicek, parent HARUS dicek agar terkirim ke server
            parentCheckbox.prop('checked', true).prop('indeterminate', false);
            selectedMenuIds.add(String(parentId));
            $(`#perms_${parentId}`).show();
        } 
        // Note: we don't automatically uncheck parent if all sub-menus are unchecked 
        // to follow user's "ceklis menu... baru uncheck sub menunya" preference.
    }
    
    // Update selected count
    function updateSelectedCount() {
        const selectedCount = selectedMenuIds.size;
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
        selectedMenuIds.clear();
        Object.keys(permissionsByParent).forEach(key => delete permissionsByParent[key]);
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
        // Only select visible checkboxes (skip parents that are already assigned and not rendered as checkboxes)
        $('.main-menu-checkbox:visible, .sub-menu-checkbox:visible').each(function() {
            $(this).prop('checked', true).prop('indeterminate', false);
            const id = String($(this).data('menu-id'));
            selectedMenuIds.add(id);
            const menu = availableMenus.find(item => String(item.MenuId) === id);
            const parentId = menu ? getParentId(menu) : id;
            $(`#perms_${parentId}`).show();
        });
        updateSelectedCount();
    });
    
    // Clear all selections button
    $('#clearAllMenus').click(function() {
        $('.main-menu-checkbox:visible, .sub-menu-checkbox:visible').each(function() {
            $(this).prop('checked', false).prop('indeterminate', false);
            const id = String($(this).data('menu-id'));
            selectedMenuIds.delete(id);
            const menu = availableMenus.find(item => String(item.MenuId) === id);
            const parentId = menu ? getParentId(menu) : id;
            $(`#perms_${parentId}`).hide();
        });
        updateSelectedCount();
    });
    
    $('#trusteeForm').on('submit', function(e) {
        const groupId = $('#groupId').val();
        
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
        
        if (selectedMenuIds.size === 0) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: 'Silakan pilih minimal satu menu.',
                confirmButtonColor: '#ffc107'
            });
            return false;
        }

        rememberVisiblePermissions();
        const payloadContainer = $('#selectedMenusContainer').empty();
        selectedMenuIds.forEach(function(menuId) {
            const menu = availableMenus.find(item => String(item.MenuId) === menuId);
            if (!menu) return;
            const parentId = getParentId(menu);
            const values = permissionsByParent[parentId] || defaultPermissions();

            payloadContainer.append($('<input>', { type: 'hidden', name: 'menuId[]', value: menuId }));
            Object.keys(values).forEach(function(permission) {
                payloadContainer.append($('<input>', {
                    type: 'hidden',
                    name: `permissions[${menuId}][${permission}]`,
                    value: values[permission]
                }));
            });
        });

        $(this).find('button[type="submit"]').prop('disabled', true);
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