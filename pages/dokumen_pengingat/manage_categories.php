<?php
declare(strict_types=1);
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../../includes/permissions.php';

$theme = $_SESSION['Theme'] ?? 'primary';
$username = $_SESSION['UserName'] ?? 'system';

// ─── AJAX HANDLER ──────────────────────────────────────────────────────────────
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    $action = $_POST['action'] ?? '';
    header('Content-Type: application/json');

    if ($action === 'add') {
        $name = trim($_POST['category_name'] ?? '');
        $icon = trim($_POST['icon'] ?? 'fas fa-file');
        $color = trim($_POST['color'] ?? 'primary');
        $interval = (int) ($_POST['reminder_interval'] ?? 30);
        $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int) $_POST['parent_id'] : null;

        if (!$name) {
            echo json_encode(['status' => 'error', 'message' => 'Nama kategori wajib diisi.']);
            exit;
        }
        $sql = "INSERT INTO dr_categories (category_name, icon, color, reminder_interval, parent_id, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, GETDATE())";
        $ok = sqlsrv_query($conn, $sql, [$name, $icon, $color, $interval, $parentId, $username]);
        echo json_encode($ok ? ['status' => 'success', 'message' => 'Kategori berhasil ditambahkan.'] : ['status' => 'error', 'message' => 'Gagal menyimpan.']);
        exit;
    }

    if ($action === 'get') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = sqlsrv_query($conn, "SELECT * FROM dr_categories WHERE id = ?", [$id]);
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        echo $row ? json_encode(['status' => 'success', 'data' => $row]) : json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan.']);
        exit;
    }

    if ($action === 'edit') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['category_name'] ?? '');
        $icon = trim($_POST['icon'] ?? 'fas fa-file');
        $color = trim($_POST['color'] ?? 'primary');
        $interval = (int) ($_POST['reminder_interval'] ?? 30);
        $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int) $_POST['parent_id'] : null;

        if (!$name) {
            echo json_encode(['status' => 'error', 'message' => 'Nama kategori wajib diisi.']);
            exit;
        }

        // Prevent self-selection as parent
        if ($parentId !== null && $parentId === $id) {
            echo json_encode(['status' => 'error', 'message' => 'Kategori tidak boleh menjadi induk dari dirinya sendiri.']);
            exit;
        }

        $sql = "UPDATE dr_categories SET category_name=?, icon=?, color=?, reminder_interval=?, parent_id=?, updated_by=?, updated_at=GETDATE() WHERE id=?";
        $ok = sqlsrv_query($conn, $sql, [$name, $icon, $color, $interval, $parentId, $username, $id]);
        echo json_encode($ok ? ['status' => 'success', 'message' => 'Kategori berhasil diperbarui.'] : ['status' => 'error', 'message' => 'Gagal memperbarui.']);
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        sqlsrv_begin_transaction($conn);
        try {
            // Dapatkan semua ID sub-kategori jika target adalah Parent Category
            $targetIds = [];
            $stmtChild = sqlsrv_query($conn, "SELECT id FROM dr_categories WHERE parent_id = ?", [$id]);
            if ($stmtChild) {
                while ($rc = sqlsrv_fetch_array($stmtChild, SQLSRV_FETCH_ASSOC)) {
                    $targetIds[] = (int) $rc['id'];
                }
            }
            // Tambahkan Parent ID di akhir agar anak dihapus terlebih dahulu
            $targetIds[] = $id;

            // Hapus semua dokumen, nilai, file, dan fields untuk semua target
            foreach ($targetIds as $tid) {
                $qVal = sqlsrv_query($conn, "DELETE FROM dr_doc_values WHERE document_id IN (SELECT id FROM dr_documents WHERE category_id = ?)", [$tid]);
                if ($qVal === false) {
                    throw new Exception('Gagal menghapus nilai dokumen.');
                }

                $qFiles = sqlsrv_query($conn, "DELETE FROM dr_doc_files WHERE document_id IN (SELECT id FROM dr_documents WHERE category_id = ?)", [$tid]);
                if ($qFiles === false) {
                    throw new Exception('Gagal menghapus berkas dokumen.');
                }

                $qDoc = sqlsrv_query($conn, "DELETE FROM dr_documents WHERE category_id = ?", [$tid]);
                if ($qDoc === false) {
                    throw new Exception('Gagal menghapus dokumen.');
                }

                $qFields = sqlsrv_query($conn, "DELETE FROM dr_fields WHERE category_id = ?", [$tid]);
                if ($qFields === false) {
                    throw new Exception('Gagal menghapus kolom kategori.');
                }

                $qCat = sqlsrv_query($conn, "DELETE FROM dr_categories WHERE id = ?", [$tid]);
                if ($qCat === false) {
                    throw new Exception('Gagal menghapus data kategori.');
                }
            }

            sqlsrv_commit($conn);
            echo json_encode(['status' => 'success', 'message' => 'Kategori beserta sub-kategori dan seluruh datanya berhasil dihapus.']);
        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'download_icon') {
        $iconName = isset($_POST['icon']) ? trim($_POST['icon']) : '';
        if (empty($iconName)) {
            echo json_encode(['status' => 'error', 'message' => 'Nama ikon tidak ditemukan']);
            exit;
        }

        $iconCacheDir = __DIR__ . '/../../includes/icons';
        if (!is_dir($iconCacheDir))
            @mkdir($iconCacheDir, 0777, true);

        $safeIcon = str_replace(':', '_', $iconName);
        $localPath = "$iconCacheDir/$safeIcon.svg";

        if (file_exists($localPath)) {
            echo json_encode(['status' => 'success', 'message' => 'Ikon sudah tersedia']);
            exit;
        }

        if (strpos($iconName, ':') !== false) {
            list($prefix, $name) = explode(':', $iconName, 2);
            $apiUrl = "https://api.iconify.design/$prefix/$name.svg";
            $svgContent = @file_get_contents($apiUrl);
            if ($svgContent && strlen($svgContent) > 0) {
                file_put_contents($localPath, $svgContent);
                echo json_encode(['status' => 'success', 'message' => 'Ikon berhasil didownload']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Gagal mendownload ikon dari API']);
            }
        } else {
            echo json_encode(['status' => 'success', 'message' => 'Ikon lokal']);
        }
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Action tidak dikenal.']);
    exit;
}

/**
 * Helper to render icon
 */
function renderIcon($icon, $class = "")
{
    $icon = trim($icon);
    if (empty($icon))
        return '<i class="fas fa-file ' . $class . '"></i>';
    if (strpos($icon, ':') !== false) {
        $safeIcon = str_replace(':', '_', $icon);
        $localPath = __DIR__ . "/../../includes/icons/$safeIcon.svg";
        if (file_exists($localPath)) {
            return '<span class="local-icon ' . $class . '" style="display:inline-block; width:1em; height:1em; vertical-align:middle; fill:currentColor;">' . file_get_contents($localPath) . '</span>';
        }
        return '<span class="iconify ' . $class . '" data-icon="' . htmlspecialchars($icon) . '"></span>';
    }
    return '<i class="' . htmlspecialchars($icon) . ' ' . $class . '"></i>';
}

// ─── FETCH DATA ─────────────────────────────────────────────────────────────────
$categories = [];
$stmt = sqlsrv_query($conn, "
    SELECT c.*, 
           (SELECT COUNT(*) FROM dr_fields f WHERE f.category_id = c.id) AS field_count, 
           (SELECT COUNT(*) FROM dr_documents d WHERE d.category_id = c.id) AS doc_count 
    FROM dr_categories c 
    ORDER BY c.parent_id ASC, c.id ASC
");
if ($stmt) {
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if ($r['color'] === 'user') {
            $r['color'] = $theme;
        }
        $categories[] = $r;
    }
}

// Kelompokkan Kategori menjadi Kategori Utama (Parent) & Sub-Kategori (Child)
$parentCategories = [];
$subCategories = [];

foreach ($categories as $cat) {
    if ($cat['parent_id'] === null) {
        $parentCategories[$cat['id']] = $cat;
        $parentCategories[$cat['id']]['children'] = [];
    } else {
        $subCategories[] = $cat;
    }
}

foreach ($subCategories as $sub) {
    $pId = $sub['parent_id'];
    if (isset($parentCategories[$pId])) {
        $parentCategories[$pId]['children'][] = $sub;
    } else {
        // Safe-guard: jika parent tidak ditemukan, jadikan parent mandiri
        $parentCategories[$sub['id']] = $sub;
        $parentCategories[$sub['id']]['children'] = [];
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-6">
                    <h1><i class="fas fa-folder-plus mr-2 text-<?= htmlspecialchars($theme) ?>"></i>Manajemen Kategori
                        Dokumen</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <button class="btn btn-<?= htmlspecialchars($theme) ?> shadow-sm" data-toggle="modal"
                        data-target="#modalCategory" onclick="resetModal()">
                        <i class="fas fa-plus mr-1"></i> Tambah Kategori Baru
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <!-- Search Bar Kategori -->
            <div class="row justify-content-center mb-4">
                <div class="col-md-8">
                    <div class="input-group input-group-lg shadow-sm"
                        style="border-radius: 50px; overflow: hidden; border: 2px solid <?= $theme === 'primary' ? '#007bff' : ($theme === 'success' ? '#28a745' : ($theme === 'warning' ? '#ffc107' : ($theme === 'danger' ? '#dc3545' : '#17a2b8'))) ?>; background-color: #fff;">
                        <input type="text" class="form-control border-0" id="categorySearchInput"
                            placeholder="Cari Kategori : Masukan Nama Kategori..."
                            style="padding-left: 25px; outline: none; box-shadow: none;">
                        <div class="input-group-append">
                            <button type="button" class="btn btn-white border-0 px-3 d-none" id="btnResetSearch"
                                style="color: #aaa; background-color: #fff;" title="Reset Pencarian">
                                <i class="fas fa-times-circle fa-lg"></i>
                            </button>
                            <button type="button" class="btn btn-<?= htmlspecialchars($theme) ?> border-0 px-4"
                                id="btnSearch">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (empty($parentCategories)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">Belum ada kategori dokumen</h5>
                    <p class="text-muted">Mulai dengan menambahkan kategori baru.</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($parentCategories as $parent): ?>
                        <div class="col-lg-6 col-xl-4 mb-4 category-card"
                            data-name="<?= htmlspecialchars($parent['category_name']) ?>">
                            <div
                                class="card card-outline card-<?= htmlspecialchars($parent['color']) ?> shadow hover-card h-100 bg-white">
                                <div
                                    class="card-header bg-white d-flex align-items-center justify-content-between py-3 border-bottom">
                                    <div class="d-flex align-items-center">
                                        <div class="icon-box bg-<?= htmlspecialchars($parent['color']) ?> text-white mr-3">
                                            <?= renderIcon($parent['icon'], "fa-lg") ?>
                                        </div>
                                        <div>
                                            <h5 class="mb-0 font-weight-bold"><?= htmlspecialchars($parent['category_name']) ?>
                                            </h5>
                                            <span class="badge badge-light border text-muted small">Kategori Utama</span>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center" style="gap: 5px; flex-shrink: 0;">
                                        <button class="btn btn-sm btn-outline-success btn-add-subcat"
                                            data-parent-id="<?= (int) $parent['id'] ?>"
                                            data-parent-name="<?= htmlspecialchars($parent['category_name']) ?>"
                                            title="Tambah Sub-Kategori"><i class="fas fa-plus"></i></button>
                                        <button class="btn btn-sm btn-outline-secondary btn-edit-cat"
                                            data-id="<?= (int) $parent['id'] ?>" title="Edit Kategori Utama"><i
                                                class="fas fa-edit"></i></button>
                                        <button class="btn btn-sm btn-outline-danger btn-del-cat"
                                            data-id="<?= (int) $parent['id'] ?>"
                                            data-name="<?= htmlspecialchars($parent['category_name']) ?>"
                                            title="Hapus Kategori Utama"><i class="fas fa-trash"></i></button>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="p-3 bg-light border-bottom font-weight-bold text-muted small"><i
                                            class="fas fa-sitemap mr-1"></i> SUB-KATEGORI (TIPE DOKUMEN)</div>
                                    <div class="list-group list-group-flush">
                                        <?php if (empty($parent['children'])): ?>
                                            <div class="list-group-item text-center text-muted py-4 small">
                                                <i class="fas fa-info-circle mr-1"></i> Belum ada sub-kategori.
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($parent['children'] as $child): ?>
                                                <div class="list-group-item bg-white py-3">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <span class="font-weight-bold text-dark"><i
                                                                class="far fa-file-alt text-<?= htmlspecialchars($parent['color']) ?> mr-2"></i><?= htmlspecialchars($child['category_name']) ?></span>
                                                        <div class="small text-muted">
                                                            <span class="mr-2"><i
                                                                    class="fas fa-layer-group mr-1"></i><?= (int) $child['field_count'] ?>
                                                                Kolom</span>
                                                            <span><i class="fas fa-file-alt mr-1"></i><?= (int) $child['doc_count'] ?>
                                                                Data</span>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex justify-content-end" style="gap: 5px;">
                                                        <a href="manage_fields.php?category_id=<?= (int) $child['id'] ?>"
                                                            class="btn btn-xs btn-outline-dark font-weight-bold px-2 py-1">
                                                            <i class="fas fa-list-ul mr-1"></i>Atur Kolom
                                                        </a>
                                                        <button
                                                            class="btn btn-xs btn-outline-<?= htmlspecialchars($parent['color']) ?> btn-edit-cat px-2 py-1"
                                                            data-id="<?= (int) $child['id'] ?>"><i
                                                                class="fas fa-edit mr-1"></i>Edit</button>
                                                        <button class="btn btn-xs btn-outline-danger btn-del-cat px-2 py-1"
                                                            data-id="<?= (int) $child['id'] ?>"
                                                            data-name="<?= htmlspecialchars($child['category_name']) ?>"><i
                                                                class="fas fa-trash mr-1"></i>Hapus</button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<!-- Modal Tambah / Edit Kategori -->
<div class="modal fade" id="modalCategory" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-<?= htmlspecialchars($theme) ?> text-white">
                <h5 class="modal-title font-weight-bold" id="modalCategoryTitle"><i
                        class="fas fa-plus-circle mr-2"></i>Tambah Kategori</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="formCategory">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="editId" value="">
                <div class="modal-body p-4 bg-white">
                    <div class="form-group">
                        <label class="font-weight-bold">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="category_name" id="inputName"
                            placeholder="Contoh: Izin Lingkungan, STNK, BPKB" required>
                    </div>

                    <!-- Input Kategori Induk -->
                    <div class="form-group">
                        <label class="font-weight-bold">Kategori Induk (Grup)</label>
                        <select class="form-control custom-select" name="parent_id" id="inputParentId">
                            <option value="">-- Kategori Utama (Parent) --</option>
                            <?php foreach ($parentCategories as $p): ?>
                                <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars($p['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted"></small>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Ikon</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light" id="iconPreviewContainer"
                                            style="min-width: 45px; display: flex; justify-content: center;">
                                            <i id="iconPreview" class="fas fa-file"></i>
                                        </span>
                                    </div>
                                    <input type="text" class="form-control" name="icon" id="inputIcon"
                                        placeholder="fas fa-file" value="fas fa-file">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-<?= htmlspecialchars($theme) ?>"
                                            onclick="openIconPicker()">Cari</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Warna Tema</label>
                                <select class="form-control custom-select" name="color" id="inputColor">
                                    <option value="user">Tema User (Dinamis)</option>
                                    <option value="primary">Primary (Biru)</option>
                                    <option value="success">Success (Hijau)</option>
                                    <option value="danger">Danger (Merah)</option>
                                    <option value="warning">Warning (Kuning)</option>
                                    <option value="info">Info (Cyan)</option>
                                    <option value="dark">Dark (Hitam)</option>
                                    <option value="secondary">Secondary (Abu)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Interval Pengingat (Hari sebelum expire)</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="reminder_interval" id="inputInterval"
                                value="30" min="1" required>
                            <div class="input-group-append"><span class="input-group-text">Hari</span></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-<?= htmlspecialchars($theme) ?> px-4" id="btnSubmitCat">
                        <i class="fas fa-save mr-1"></i>Simpan Kategori
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Icon Picker -->
<div class="modal fade" id="modalIconPicker" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-<?= htmlspecialchars($theme) ?> text-white">
                <h5 class="modal-title"><i class="fas fa-search mr-2"></i>Cari Ikon Online</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-0 bg-white">
                <div class="bg-light p-3 border-bottom">
                    <div class="input-group">
                        <input type="text" id="searchIcon" class="form-control"
                            placeholder="Cari ikon (misal: user, home, folder, ticket)...">
                        <div class="input-group-append"><span class="input-group-text"><i
                                    class="fas fa-search"></i></span></div>
                    </div>
                </div>
                <div id="iconLoading" class="text-center py-5 d-none">
                    <div class="spinner-border text-<?= htmlspecialchars($theme) ?>" role="status"></div>
                    <p class="mt-2 text-muted">Mencari ikon...</p>
                </div>
                <div id="iconList" class="row no-gutters text-center p-3" style="max-height: 400px; overflow-y: auto;">
                    <div class="col-12 py-5 text-muted">Ketik untuk mencari ikon...</div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <small class="text-muted mr-auto">Power by Iconify</small>
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<style>
    .icon-box {
        width: 52px;
        height: 52px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.12);
        flex-shrink: 0;
    }

    .hover-card {
        transition: transform .25s ease, box-shadow .25s ease;
        border-radius: 15px;
    }

    .hover-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.13) !important;
    }

    .local-icon svg {
        width: 100%;
        height: 100%;
        fill: currentColor;
        display: block;
    }

    .icon-item:hover .border {
        border-color: var(--primary) !important;
        background-color: #f8f9fa !important;
    }
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<script src="https://code.iconify.design/3/3.1.1/iconify.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>
<script>
    function resetModal() {
        $('#modalCategoryTitle').html('<i class="fas fa-plus-circle mr-2"></i>Tambah Kategori Baru');
        $('#formAction').val('add');
        $('#editId').val('');
        $('#formCategory')[0].reset();
        $('#inputIcon').val('fas fa-file');
        $('#iconPreview').attr('class', 'fas fa-file');
        $('#inputColor').val('user');
        $('#inputInterval').val(30);
        $('#inputParentId').val('').prop('disabled', false);
    }

    // Icon preview live
    $('#inputIcon').on('input', function () {
        updateIconPreview($(this).val().trim());
    });

    function updateIconPreview(icon) {
        const preview = $('#iconPreviewContainer');
        if (icon.includes(':')) {
            preview.html(`<span class="iconify" data-icon="${icon}" data-width="20" data-height="20"></span>`);
        } else {
            preview.html(`<i class="${icon}"></i>`);
        }
    }

    let searchTimeout = null;
    function openIconPicker() {
        $('#searchIcon').val('document');
        $('#iconList').html('<div class="col-12 py-5 text-center text-muted"><i class="fas fa-spinner fa-spin fa-2x mb-2"></i><br>Memuat rekomendasi ikon...</div>');
        $('#modalIconPicker').modal('show');
        setTimeout(() => {
            $('#searchIcon').focus();
            $('#searchIcon').trigger('keyup');
        }, 500);
    }

    $('#searchIcon').on('keyup', function () {
        clearTimeout(searchTimeout);
        const q = $(this).val();
        if (q.length < 2) return;
        searchTimeout = setTimeout(() => {
            $('#iconLoading').removeClass('d-none');
            $('#iconList').addClass('d-none');
            $.get(`https://api.iconify.design/search?query=${encodeURIComponent(q)}&limit=48`, function (data) {
                $('#iconLoading').addClass('d-none');
                $('#iconList').removeClass('d-none');
                if (data.icons && data.icons.length > 0) {
                    let html = '';
                    data.icons.forEach(icon => {
                        html += `<div class="col-3 p-2" style="cursor:pointer;" onclick="selectIcon('${icon}')">
                                <div class="p-2 border rounded shadow-sm bg-white h-100 d-flex flex-column align-items-center justify-content-center">
                                    <span class="iconify mb-1" data-icon="${icon}" data-width="24" data-height="24"></span>
                                    <div style="font-size:8px; width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${icon}</div>
                                </div>
                             </div>`;
                    });
                    $('#iconList').html(html);
                } else {
                    $('#iconList').html('<div class="col-12 py-5 text-muted">Ikon tidak ditemukan.</div>');
                }
            });
        }, 500);
    });

    function selectIcon(icon) {
        Swal.fire({ title: 'Mendownload Ikon...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
        $.post('manage_categories.php', { action: 'download_icon', icon: icon }, function (res) {
            Swal.close();
            $('#inputIcon').val(icon);
            updateIconPreview(icon);
            $('#modalIconPicker').modal('hide');
        });
    }

    // Add Sub-Category from Parent Card
    $(document).on('click', '.btn-add-subcat', function () {
        const parentId = $(this).data('parent-id');
        const parentName = $(this).data('parent-name');
        resetModal();
        $('#modalCategoryTitle').html('<i class="fas fa-plus-circle mr-2 "></i>Tambah Sub-Kategori ke: ' + parentName);
        $('#inputParentId').val(parentId).prop('disabled', true);
        $('#modalCategory').modal('show');
    });

    // Edit
    $(document).on('click', '.btn-edit-cat', function () {
        const id = $(this).data('id');
        $.ajax({
            url: 'manage_categories.php', type: 'POST', data: { action: 'get', id }, dataType: 'json',
            success(res) {
                if (res.status !== 'success') { Swal.fire('Error', res.message, 'error'); return; }
                const d = res.data;
                $('#modalCategoryTitle').html('<i class="fas fa-edit mr-2"></i>Edit Kategori');
                $('#formAction').val('edit');
                $('#editId').val(d.id);
                $('#inputName').val(d.category_name);
                $('#inputIcon').val(d.icon);
                updateIconPreview(d.icon);
                $('#inputColor').val(d.color);
                $('#inputInterval').val(d.reminder_interval);
                $('#inputParentId').val(d.parent_id || '').prop('disabled', false);
                $('#modalCategory').modal('show');
            }
        });
    });

    // Delete
    $(document).on('click', '.btn-del-cat', function () {
        const id = $(this).data('id');
        const name = $(this).data('name');
        Swal.fire({
            title: 'Hapus "' + name + '"?',
            html: 'Menghapus kategori ini juga akan menghapus seluruh <b>sub-kategori, kolom, dan data dokumen</b> di dalamnya secara permanen!',
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#d33', confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal'
        }).then(r => {
            if (!r.isConfirmed) return;
            $.ajax({
                url: 'manage_categories.php', type: 'POST', data: { action: 'delete', id }, dataType: 'json',
                success(res) {
                    Swal.fire(res.status === 'success' ? 'Berhasil' : 'Gagal', res.message, res.status).then(() => location.reload());
                }
            });
        });
    });

    // Form Submit (Add / Edit)
    $('#formCategory').on('submit', function (e) {
        e.preventDefault();

        // Temporarily enable select to ensure it serializes properly
        const selectParent = $('#inputParentId');
        const isCurrentlyDisabled = selectParent.prop('disabled');
        if (isCurrentlyDisabled) {
            selectParent.prop('disabled', false);
        }

        const serializedData = $(this).serialize();

        if (isCurrentlyDisabled) {
            selectParent.prop('disabled', true);
        }

        const btn = $('#btnSubmitCat').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Menyimpan...');
        $.ajax({
            url: 'manage_categories.php', type: 'POST', data: serializedData, dataType: 'json',
            success(res) {
                if (res.status === 'success') {
                    Swal.fire('Berhasil!', res.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Gagal!', res.message, 'error');
                    btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Simpan Kategori');
                }
            },
            error() {
                Swal.fire('Error', 'Terjadi kesalahan sistem.', 'error');
                btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Simpan Kategori');
            }
        });
    });

    // Real-time search filter for category cards
    $('#categorySearchInput').on('input', function () {
        const val = $(this).val().trim().toLowerCase();
        if (val.length > 0) {
            $('#btnResetSearch').removeClass('d-none');
            $('.category-card').each(function () {
                const cardName = $(this).data('name').toLowerCase();
                if (cardName.includes(val)) {
                    $(this).removeClass('d-none');
                } else {
                    $(this).addClass('d-none');
                }
            });
        } else {
            $('#btnResetSearch').addClass('d-none');
            $('.category-card').removeClass('d-none');
        }
    });

    $('#btnResetSearch').on('click', function () {
        $('#categorySearchInput').val('').trigger('input');
    });
</script>