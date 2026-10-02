<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

include '../koneksi.php';

$configDir = '../config';
$configPath = $configDir . '/menu_order.json';

// Ensure directory exists
if (!is_dir($configDir)) {
    mkdir($configDir, 0777, true);
}

// Load sorting weights
$menuWeights = [];
if (file_exists($configPath)) {
    $menuWeights = json_decode(file_get_contents($configPath), true) ?? [];
}

$iconCacheDir = '../includes/icons';
if (!is_dir($iconCacheDir)) {
    mkdir($iconCacheDir, 0777, true);
}

// Handle AJAX Sorting & Icon Download (BEFORE any HTML output)
if (isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action == 'save_order') {
        $orderData = $_POST['order'] ?? [];
        foreach ($orderData as $index => $id) {
            $menuWeights[$id] = $index;
        }
        
        $success = file_put_contents($configPath, json_encode($menuWeights, JSON_PRETTY_PRINT));
        if ($success !== false) {
            echo json_encode(['status' => 'success', 'message' => 'Urutan berhasil disimpan']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan file konfigurasi.']);
        }
        exit;
    }

    if ($action == 'download_icon') {
        header('Content-Type: application/json; charset=utf-8');
        ob_clean(); // Clear any accidental output
        
        $iconName = isset($_POST['icon']) ? trim($_POST['icon']) : '';
        
        if (empty($iconName)) {
            echo json_encode(['status' => 'error', 'message' => 'Nama ikon tidak ditemukan']);
            exit;
        }
        
        $safeIcon = str_replace(':', '_', $iconName);
        $localPath = "$iconCacheDir/$safeIcon.svg";
        
        if (file_exists($localPath)) {
            echo json_encode(['status' => 'success', 'message' => 'Ikon sudah tersedia offline']);
            exit;
        }
        
        if (strpos($iconName, ':') !== false) {
            list($prefix, $name) = explode(':', $iconName, 2);
            $prefix = trim($prefix);
            $name = trim($name);
            
            if (empty($prefix) || empty($name)) {
                echo json_encode(['status' => 'error', 'message' => 'Format ikon tidak valid']);
                exit;
            }
            
            $apiUrl = "https://api.iconify.design/$prefix/$name.svg";
            
            // Try file_get_contents first
            $svgContent = @file_get_contents($apiUrl, false, stream_context_create([
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
                'http' => ['timeout' => 10]
            ]));
            
            if (!$svgContent && function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $apiUrl,
                    CURLOPT_RETURNTRANSFER => 1,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 3,
                    CURLOPT_USERAGENT => 'PHP Script'
                ]);
                
                $svgContent = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);
                
                if (!$svgContent) {
                    error_log("Icon Download CURL Error: $curlError (HTTP $httpCode) for $apiUrl");
                    echo json_encode(['status' => 'error', 'message' => "Gagal download via CURL. HTTP $httpCode: $curlError"]);
                    exit;
                }
            }
            
            if (empty($svgContent) || strlen($svgContent) == 0) {
                error_log("Icon Download Failed: No content from $apiUrl");
                echo json_encode(['status' => 'error', 'message' => 'Gagal mendownload ikon dari API. Server tidak mengembalikan data.']);
                exit;
            }
            
            // Validate SVG content
            if (strpos($svgContent, '<svg') === false) {
                error_log("Icon Download Invalid: Response doesn't contain SVG tag for $iconName");
                echo json_encode(['status' => 'error', 'message' => 'Respons API tidak valid. Pastikan icon tersedia.']);
                exit;
            }
            
            // Ensure directory exists and is writable
            if (!is_dir($iconCacheDir)) {
                @mkdir($iconCacheDir, 0777, true);
            }
            
            if (!is_writable($iconCacheDir)) {
                error_log("Icon Cache Directory not writable: $iconCacheDir");
                echo json_encode(['status' => 'error', 'message' => 'Folder cache tidak dapat diakses. Hubungi administrator.']);
                exit;
            }
            
            if (file_put_contents($localPath, $svgContent) !== false) {
                error_log("Icon Downloaded Successfully: $iconName -> $localPath");
                echo json_encode(['status' => 'success', 'message' => 'Ikon berhasil didownload']);
            } else {
                error_log("Icon Save Failed: Could not write to $localPath");
                echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan ikon ke folder cache']);
            }
        } else {
            // Icon doesn't contain colon, treat as local icon (e.g., Bootstrap Icon or FontAwesome)
            echo json_encode(['status' => 'success', 'message' => 'Ikon lokal tidak perlu didownload']);
        }
        exit;
    }
}

// Handle CRUD Actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action == 'add' || $action == 'edit') {
        $menuName     = $_POST['MenuName'];
        $menuUrl      = $_POST['MenuUrl'];
        $menuIcon     = $_POST['MenuIcon'];
        $parentMenuId = !empty($_POST['ParentMenuId']) ? $_POST['ParentMenuId'] : null;
        
        if ($action == 'add') {
            $sql = "INSERT INTO SMMenu (MenuName, MenuUrl, MenuIcon, ParentMenuId, CreatedDate, CreatedBy) VALUES (?, ?, ?, ?, GETDATE(), ?)";
            $params = [$menuName, $menuUrl, $menuIcon, $parentMenuId, $_SESSION['UserName']];
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if ($stmt) {
                $_SESSION['success'] = "Menu berhasil ditambahkan";
            } else {
                $_SESSION['error'] = "Gagal menambahkan menu: " . print_r(sqlsrv_errors(), true);
            }
        } else {
            $menuId = $_POST['MenuId'];
            $sql = "UPDATE SMMenu SET MenuName = ?, MenuUrl = ?, MenuIcon = ?, ParentMenuId = ? WHERE MenuId = ?";
            $params = [$menuName, $menuUrl, $menuIcon, $parentMenuId, $menuId];
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if ($stmt) {
                $_SESSION['success'] = "Menu berhasil diperbarui";
            } else {
                $_SESSION['error'] = "Gagal memperbarui menu: " . print_r(sqlsrv_errors(), true);
            }
        }
        header("Location: menu_manager.php");
        exit;
    }
    
    if ($action == 'delete') {
        $menuId = $_POST['MenuId'];
        
        // 0. Cek apakah menu memiliki sub-menu
        $sqlCheckChild = "SELECT COUNT(*) as childCount FROM SMMenu WHERE ParentMenuId = ?";
        $stmtCheckChild = sqlsrv_query($conn, $sqlCheckChild, array($menuId));
        
        if ($stmtCheckChild === false) {
             echo json_encode(['status' => 'error', 'message' => 'Gagal mengecek sub-menu.']);
             exit;
        }
        
        $rowCheck = sqlsrv_fetch_array($stmtCheckChild, SQLSRV_FETCH_ASSOC);
        if ($rowCheck['childCount'] > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Menu tidak bisa dihapus karena memiliki sub-menu.']);
            exit;
        }

        // 1. Hapus referensi di SMGroupTrustee (Hak Akses Group)
        $sqlTrustee = "DELETE FROM SMGroupTrustee WHERE MenuId = ?";
        sqlsrv_query($conn, $sqlTrustee, [$menuId]);

        // 2. Hapus referensi Submenu di SMGroupTrustee (jika ini adalah Parent)
        // Cari status apakah ini parent?
        $sqlCheckChild = "SELECT MenuId FROM SMMenu WHERE ParentMenuId = ?";
        $stmtCheck = sqlsrv_query($conn, $sqlCheckChild, [$menuId]);
        if ($stmtCheck) {
            while ($childKey = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC)) {
                $childId = $childKey['MenuId'];
                // Hapus trustee untuk anak buahnya juga
                sqlsrv_query($conn, "DELETE FROM SMGroupTrustee WHERE MenuId = ?", [$childId]);
                // Hapus anak buah dari SMMenu
                sqlsrv_query($conn, "DELETE FROM SMMenu WHERE MenuId = ?", [$childId]);
            }
        }

        // 3. Hapus Menu Utama
        $sql = "DELETE FROM SMMenu WHERE MenuId = ?";
        $stmt = sqlsrv_query($conn, $sql, [$menuId]);

        if ($stmt) {
            if (isset($menuWeights[$menuId])) {
                unset($menuWeights[$menuId]);
                file_put_contents($configPath, json_encode($menuWeights, JSON_PRETTY_PRINT));
            }
            echo json_encode(['status' => 'success', 'message' => 'Menu berhasil dihapus']);
        } else {
            // Ambil error detailed
            if( ($errors = sqlsrv_errors() ) != null) {
                $msg = "SQL Error: ";
                foreach( $errors as $error ) {
                    $msg .= $error[ 'message']."; ";
                }
                echo json_encode(['status' => 'error', 'message' => $msg]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus menu']);
            }
        }
        exit;
    }
}

// Include HTML structure AFTER AJAX handlers
include '../includes/header.php';
include '../includes/sidebar.php';

// Get all menus for display
$sqlMenu = "SELECT * FROM SMMenu";
$stmtMenu = sqlsrv_query($conn, $sqlMenu);
$allMenus = [];
if ($stmtMenu) {
    while ($row = sqlsrv_fetch_array($stmtMenu, SQLSRV_FETCH_ASSOC)) {
        // Use PHP_INT_MAX as default so new menus appear at the bottom
        $row['SortOrder'] = $menuWeights[$row['MenuId']] ?? PHP_INT_MAX;
        $allMenus[] = $row;
    }
}

// Sort $allMenus using weights
usort($allMenus, function($a, $b) {
    if ($a['ParentMenuId'] == $b['ParentMenuId']) {
        return $a['SortOrder'] <=> $b['SortOrder'];
    }
    // Parents first (NULL)
    if ($a['ParentMenuId'] === null) return -1;
    if ($b['ParentMenuId'] === null) return 1;
    return $a['ParentMenuId'] <=> $b['ParentMenuId'];
});

// Group for tree view
$menuTree = [];
foreach ($allMenus as $m) {
    if ($m['ParentMenuId'] == null) {
        $menuTree[$m['MenuId']] = $m;
        $menuTree[$m['MenuId']]['children'] = [];
    }
}
foreach ($allMenus as $m) {
    if ($m['ParentMenuId'] != null && isset($menuTree[$m['ParentMenuId']])) {
        $menuTree[$m['ParentMenuId']]['children'][] = $m;
    }
}

// Sort the tree based on weights
uasort($menuTree, function($a, $b) {
    return $a['SortOrder'] <=> $b['SortOrder'];
});
foreach ($menuTree as $id => $parent) {
    usort($menuTree[$id]['children'], function($a, $b) {
        return $a['SortOrder'] <=> $b['SortOrder'];
    });
}

/**
 * Render Icon Helper for Table
 */
function renderIcon($icon) {
    $icon = trim($icon);
    if (empty($icon)) return '<i class="fas fa-question text-muted"></i>';
    
    // Check if it's iconify format (contains colon)
    if (strpos($icon, ':') !== false) {
        $safeIcon = str_replace(':', '_', $icon);
        $localPath = __DIR__ . "/../includes/icons/$safeIcon.svg";
        
        if (file_exists($localPath)) {
            $svgContent = file_get_contents($localPath);
            return '<span class="local-icon" style="display:inline-block; width:20px; height:20px; vertical-align:middle;">' . $svgContent . '</span>';
        }
        return '<span class="iconify" data-icon="' . htmlspecialchars($icon) . '" data-width="20" data-height="20"></span>';
    }
    
    // Default FontAwesome
    return '<i class="' . htmlspecialchars($icon) . '"></i>';
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="text-<?= htmlspecialchars($themeColor) ?>"><i class="fas fa-sitemap mr-2"></i>Menu Manager</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Menu Manager</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-outline card-<?= htmlspecialchars($themeColor) ?>">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title text-white"><i class="fas fa-arrows-alt mr-1"></i>Struktur Menu (Drag & Drop untuk Mengatur Urutan)</h3>
                    <button class="btn btn-success btn-sm float-right" data-toggle="modal" data-target="#modalMenu" onclick="resetForm()">
                        <i class="fas fa-plus"></i> Tambah Menu Utama
                    </button>
                </div>
                <div class="card-body">
                    <div class="alert alert-info alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <h5><i class="icon fas fa-info"></i> Petunjuk</h5>
                        Anda dapat menarik (drag) baris menu untuk mengatur urutan secara langsung. Perubahan akan disimpan otomatis.
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm" id="menuTable">
                            <thead class="bg-light">
                                <tr>
                                    <th width="30" class="text-center"><i class="fas fa-grip-vertical"></i></th>
                                    <th width="50">ID</th>
                                    <th>Nama Menu</th>
                                    <th>URL</th>
                                    <th width="100" class="text-center">Icon</th>
                                    <th width="150" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="parent-list">
                                <?php if (empty($menuTree)): ?>
                                    <tr><td colspan="6" class="text-center py-4 text-muted">Belum ada data menu.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($menuTree as $parent): ?>
                                        <tr class="parent-row bg-light font-weight-bold" data-id="<?= $parent['MenuId'] ?>">
                                            <td class="drag-handle text-center" style="cursor:move; color:#ccc;">
                                                <i class="fas fa-grip-lines"></i>
                                            </td>
                                            <td><?= $parent['MenuId'] ?></td>
                                            <td>
                                                <span class="mr-2"><?= renderIcon($parent['MenuIcon']) ?></span>
                                                <?= htmlspecialchars($parent['MenuName']) ?>
                                            </td>
                                            <td><code><?= htmlspecialchars($parent['MenuUrl']) ?></code></td>
                                            <td class="text-center"><?= renderIcon($parent['MenuIcon']) ?></td>
                                            <td class="text-center">
                                                <button class="btn btn-xs btn-primary shadow-sm" onclick='editMenu(<?= json_encode($parent) ?>)' title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-xs btn-success shadow-sm" onclick='addSubmenu(<?= $parent['MenuId'] ?>)' title="Tambah Submenu">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                                <button class="btn btn-xs btn-danger shadow-sm" onclick='deleteMenu(<?= $parent['MenuId'] ?>)' title="Hapus">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr class="inner-row-container" data-parent-id="<?= $parent['MenuId'] ?>">
                                            <td colspan="6" style="padding:0; border:none;">
                                                <table class="table table-sm table-borderless mb-0 sub-table">
                                                    <tbody class="child-list" data-parent="<?= $parent['MenuId'] ?>">
                                                        <?php if (!empty($parent['children'])): ?>
                                                            <?php foreach ($parent['children'] as $child): ?>
                                                                <tr class="child-row" data-id="<?= $child['MenuId'] ?>">
                                                                    <td width="30" class="drag-handle text-center" style="cursor:move; color:#ddd;">
                                                                        <i class="fas fa-grip-lines"></i>
                                                                    </td>
                                                                    <td width="50"><?= $child['MenuId'] ?></td>
                                                                    <td style="padding-left: 30px;">
                                                                        <span class="mr-2"><?= renderIcon($child['MenuIcon']) ?></span>
                                                                        <?= htmlspecialchars($child['MenuName']) ?>
                                                                    </td>
                                                                    <td><code><?= htmlspecialchars($child['MenuUrl']) ?></code></td>
                                                                    <td width="100" class="text-center"><?= renderIcon($child['MenuIcon']) ?></td>
                                                                    <td width="150" class="text-center">
                                                                        <button class="btn btn-xs btn-primary shadow-sm" onclick='editMenu(<?= json_encode($child) ?>)' title="Edit">
                                                                            <i class="fas fa-edit"></i>
                                                                        </button>
                                                                        <button class="btn btn-xs btn-danger shadow-sm" onclick='deleteMenu(<?= $child['MenuId'] ?>)' title="Hapus">
                                                                            <i class="fas fa-trash"></i>
                                                                        </button>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Modal Add/Edit Menu -->
<div class="modal fade" id="modalMenu" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content border-top-5 border-<?= htmlspecialchars($themeColor) ?>">
            <form id="formMenu" method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="MenuId" id="formMenuId">
                <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h5 class="modal-title" id="modalTitle">Tambah Menu</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Parent Menu</label>
                        <select name="ParentMenuId" id="formParentMenuId" class="form-control">
                            <option value="">-- Menu Utama --</option>
                            <?php foreach ($menuTree as $m): ?>
                                <option value="<?= $m['MenuId'] ?>"><?= htmlspecialchars($m['MenuName']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nama Menu <span class="text-danger">*</span></label>
                        <input type="text" name="MenuName" id="formMenuName" class="form-control" placeholder="Contoh: Dashboard" required>
                    </div>
                    <div class="form-group">
                        <label>URL / Route <span class="text-danger">*</span></label>
                        <input type="text" name="MenuUrl" id="formMenuUrl" class="form-control" placeholder="/gg_app/pages/file.php" required>
                    </div>
                    <div class="form-group">
                        <label>Icon <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-light" id="iconPreview" style="min-width: 45px; display: flex; justify-content: center;">
                                    <i class="fas fa-caret-right"></i>
                                </span>
                            </div>
                            <input type="text" name="MenuIcon" id="formMenuIcon" class="form-control" placeholder="fas fa-caret-right atau mdi:home" required>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-<?= htmlspecialchars($themeColor) ?>" onclick="openIconPicker()">Cari Icon</button>
                            </div>
                        </div>
                        <small class="text-muted">Gunakan FontAwesome (fas fa-caret-right) atau Iconify (mdi:home).</small>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Icon Picker -->
<div class="modal fade" id="modalIconPicker" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content border-top-5 border-<?= htmlspecialchars($themeColor) ?>">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title"><i class="fas fa-search mr-2"></i>Cari Ikon Online</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <div class="bg-light p-3 border-bottom d-flex align-items-center">
                    <div class="input-group">
                        <input type="text" id="searchIcon" class="form-control" placeholder="Cari ikon di internet (misal: ticket, user, home, store)...">
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fas fa-globe"></i></span>
                        </div>
                    </div>
                </div>
                <div id="iconLoading" class="text-center py-5 d-none">
                    <div class="spinner-border text-<?= htmlspecialchars($themeColor) ?>" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Mencari ikon terbaik untuk Anda...</p>
                </div>
                <div id="iconList" class="row no-gutters text-center p-3" style="max-height: 500px; overflow-y: auto;">
                    <!-- Icons will be loaded here via JS -->
                    <div class="col-12 py-5 text-muted">
                        <i class="fas fa-keyboard fa-3x mb-3"></i>
                        <p>Ketik sesuatu untuk mulai mencari jutaan ikon online.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <small class="text-muted mr-auto">Bekerja dengan <a href="https://iconify.design" target="_blank">Iconify API</a></small>
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<!-- Iconify -->
<script src="https://code.iconify.design/3/3.1.1/iconify.min.js"></script>
<!-- SortableJS -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<!-- SweetAlert2 -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
let searchTimeout = null;

function resetForm() {
    $('#modalTitle').text('Tambah Menu Utama');
    $('#formAction').val('add');
    $('#formMenuId').val('');
    $('#formParentMenuId').val('');
    $('#formMenuName').val('');
    $('#formMenuUrl').val('');
    updateIconPreview('fas fa-caret-right');
}

function editMenu(data) {
    $('#modalTitle').text('Edit Menu');
    $('#formAction').val('edit');
    $('#formMenuId').val(data.MenuId);
    $('#formParentMenuId').val(data.ParentMenuId || '');
    $('#formMenuName').val(data.MenuName);
    $('#formMenuUrl').val(data.MenuUrl);
    updateIconPreview(data.MenuIcon.trim());
    $('#modalMenu').modal('show');
}

function updateIconPreview(iconString) {
    $('#formMenuIcon').val(iconString);
    if (iconString.includes(':')) {
        $('#iconPreview').html(`<span class="iconify" data-icon="${iconString}" data-width="20" data-height="20"></span>`);
    } else {
        $('#iconPreview').html(`<i class="${iconString}"></i>`);
    }
}

function addSubmenu(parentId) {
    resetForm();
    $('#modalTitle').text('Tambah Submenu');
    $('#formParentMenuId').val(parentId);
    $('#modalMenu').modal('show');
}

function deleteMenu(id) {
    Swal.fire({
        title: 'Hapus menu ini?',
        text: "Semua submenu mungkin juga akan terpengaruh!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('menu_manager.php', { action: 'delete', MenuId: id }, function(response) {
                try {
                    const res = JSON.parse(response);
                    if (res.status === 'success') {
                        Swal.fire('Terhapus!', res.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error!', res.message, 'error');
                    }
                } catch (e) {
                    Swal.fire('Error!', 'Respons server tidak valid.', 'error');
                }
            });
        }
    });
}

function openIconPicker() {
    $('#searchIcon').val('');
    $('#iconList').html(`<div class="col-12 py-5 text-muted">
                        <i class="fas fa-keyboard fa-3x mb-3"></i>
                        <p>Ketik sesuatu untuk mulai mencari jutaan ikon online.</p>
                    </div>`);
    $('#modalIconPicker').modal('show');
    setTimeout(() => $('#searchIcon').focus(), 500);
}

/**
 * Fetch Icons from Iconify API
 */
function fetchIcons(query) {
    if (!query || query.length < 2) return;
    
    $('#iconLoading').removeClass('d-none');
    $('#iconList').addClass('d-none');

    $.get(`https://api.iconify.design/search?query=${encodeURIComponent(query)}&limit=64`, function(data) {
        $('#iconLoading').addClass('d-none');
        $('#iconList').removeClass('d-none');
        
        if (data.icons && data.icons.length > 0) {
            let html = '';
            data.icons.forEach(icon => {
                html += `<div class="col-3 col-md-2 p-2 icon-item" style="cursor:pointer;" onclick="selectIcon('${icon}')">
                            <div class="p-2 border rounded shadow-sm bg-white h-100 d-flex flex-column align-items-center justify-content-center">
                                <span class="iconify mb-2" data-icon="${icon}" data-width="32" data-height="32"></span>
                                <div class="text-xs text-muted" style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:9px; width:100%">${icon}</div>
                            </div>
                         </div>`;
            });
            $('#iconList').html(html);
        } else {
            $('#iconList').html('<div class="col-12 py-5 text-muted">Aduh, ikon tidak ditemukan. Coba kata kunci lain?</div>');
        }
    }).fail(function() {
        $('#iconLoading').addClass('d-none');
        $('#iconList').removeClass('d-none');
        $('#iconList').html('<div class="col-12 py-5 text-danger">Gagal terhubung ke server ikon. Pastikan koneksi internet aktif.</div>');
    });
}

function selectIcon(icon) {
    Swal.fire({
        title: 'Mendownload Ikon...',
        text: 'Mohon tunggu sebentar',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    $.ajax({
        type: 'POST',
        url: 'menu_manager.php',
        data: { action: 'download_icon', icon: icon },
        dataType: 'json',
        timeout: 15000,
        success: function(res) {
            Swal.close();
            if (res.status === 'success') {
                updateIconPreview(icon);
                $('#modalIconPicker').modal('hide');
                const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
                Toast.fire({ icon: 'success', title: res.message });
            } else {
                Swal.fire('Gagal', res.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            Swal.close();
            let errorMsg = 'Gagal menghubungi server.';
            if (status === 'timeout') {
                errorMsg = 'Request timeout. Server tidak merespons dalam waktu yang ditentukan.';
            } else if (xhr.status === 0) {
                errorMsg = 'Gagal terhubung ke server. Periksa koneksi jaringan.';
            } else if (xhr.status === 404) {
                errorMsg = 'File tidak ditemukan.';
            } else if (xhr.status === 500) {
                errorMsg = 'Error Server: ' + (xhr.responseText ? xhr.responseText.substring(0, 100) : 'Unknown error');
            } else if (xhr.responseText) {
                errorMsg = 'Error: ' + xhr.responseText.substring(0, 100);
            }
            Swal.fire('Error', errorMsg, 'error');
            console.log('AJAX Error:', { status: xhr.status, statusText: xhr.statusText, response: xhr.responseText });
        }
    });
}

function saveOrdering() {
    let order = [];
    $('.parent-row').each(function() {
        order.push($(this).data('id'));
        let parentId = $(this).data('id');
        $(`.child-list[data-parent="${parentId}"] .child-row`).each(function() {
            order.push($(this).data('id'));
        });
    });
    
    $.post('menu_manager.php', { action: 'save_order', order: order }, function(response) {
        try {
            const res = JSON.parse(response);
            if (res.status === 'success') {
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000,
                    timerProgressBar: true
                });
                Toast.fire({ icon: 'success', title: res.message });
            }
        } catch(e) {}
    });
}

$(function() {
    // Parent Sorting
    new Sortable(document.getElementById('parent-list'), {
        handle: '.drag-handle',
        draggable: '.parent-row',
        animation: 150,
        ghostClass: 'bg-light',
        onEnd: function (evt) {
            let parentRow = evt.item;
            let containerRow = $(`.inner-row-container[data-parent-id="${$(parentRow).data('id')}"]`);
            $(parentRow).after(containerRow);
            saveOrdering();
        }
    });

    // Child Sorting
    $('.child-list').each(function() {
        new Sortable(this, {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'bg-light',
            onEnd: function () {
                saveOrdering();
            }
        });
    });

    // Live Search with Debounce
    $('#searchIcon').on('keyup', function() {
        clearTimeout(searchTimeout);
        let query = $(this).val();
        searchTimeout = setTimeout(() => fetchIcons(query), 500);
    });
    
    $('#formMenuIcon').on('input', function() {
        updateIconPreview($(this).val());
    });

    <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({ icon: 'success', title: 'Sukses', text: '<?= $_SESSION['success'] ?>', timer: 2000 });
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({ icon: 'error', title: 'Error', text: '<?= $_SESSION['error'] ?>' });
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
});
</script>

<style>
.local-icon svg { width: 100%; height: 100%; fill: currentColor; display: block; }
.icon-item:hover .border {
    border-color: #<?= ($themeColor == 'primary' ? '007bff' : '28a745') ?> !important;
    background-color: #f8f9fa !important;
}
.parent-row:hover { background-color: #f8f9fa !important; }
.child-row:hover { background-color: #f1f1f1 !important; }
.border-top-5 { border-top: 5px solid; }
.text-xs { font-size: 0.75rem; }
.bg-light { background-color: #f4f6f9 !important; }
.drag-handle { width: 30px; display: table-cell; vertical-align: middle; }
code { background: #eee; padding: 2px 4px; border-radius: 4px; font-size: 90%; color: #c7254e; }
</style>
