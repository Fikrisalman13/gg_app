<?php
declare(strict_types=1);
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../../includes/permissions.php';

$theme = $_SESSION['Theme'] ?? 'primary';
$username = $_SESSION['UserName'] ?? 'system';
$catId = (int) ($_GET['category_id'] ?? 0);

if ($catId <= 0) {
    header('Location: manage_categories.php');
    exit;
}

// ─── AJAX HANDLER ──────────────────────────────────────────────────────────────
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    $action = $_POST['action'] ?? '';
    header('Content-Type: application/json');

    if ($action === 'add') {
        $label = trim($_POST['field_label'] ?? '');
        $name = trim($_POST['field_name'] ?? '');
        $type = trim($_POST['field_type'] ?? 'text');
        $required = isset($_POST['is_required']) ? 1 : 0;
        $showTable = isset($_POST['is_show_on_table']) ? 1 : 0;
        $sort = (int) ($_POST['sort_order'] ?? 1);

        if (!$label || !$name) {
            echo json_encode(['status' => 'error', 'message' => 'Label dan nama field wajib diisi.']);
            exit;
        }

        // Validasi ketat nama field (Backend)
        if (!preg_match('/^[a-z0-9_]+$/', $name)) {
            echo json_encode(['status' => 'error', 'message' => 'Nama field hanya boleh berisi huruf kecil, angka, dan underscore.']);
            exit;
        }

        // Cek duplikat dalam kategori yang sama
        $check = sqlsrv_query($conn, "SELECT 1 FROM dr_fields WHERE category_id = ? AND field_name = ?", [$catId, $name]);
        if (sqlsrv_fetch_array($check)) {
            echo json_encode(['status' => 'error', 'message' => 'Nama field sudah digunakan di kategori ini.']);
            exit;
        }

        $sql = "INSERT INTO dr_fields (category_id, field_label, field_name, field_type, is_required, is_show_on_table, sort_order, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,GETDATE())";
        $ok = sqlsrv_query($conn, $sql, [$catId, $label, $name, $type, $required, $showTable, $sort, $username]);
        echo json_encode($ok ? ['status' => 'success', 'message' => 'Kolom berhasil ditambahkan.'] : ['status' => 'error', 'message' => 'Gagal menyimpan.']);
        exit;
    }

    if ($action === 'get') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = sqlsrv_query($conn, "SELECT * FROM dr_fields WHERE id = ?", [$id]);
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        echo $row ? json_encode(['status' => 'success', 'data' => $row]) : json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan.']);
        exit;
    }

    if ($action === 'edit') {
        $id = (int) ($_POST['id'] ?? 0);
        $label = trim($_POST['field_label'] ?? '');
        $type = trim($_POST['field_type'] ?? 'text');
        $required = isset($_POST['is_required']) ? 1 : 0;
        $showTable = isset($_POST['is_show_on_table']) ? 1 : 0;
        $sort = (int) ($_POST['sort_order'] ?? 1);
        if (!$label) {
            echo json_encode(['status' => 'error', 'message' => 'Label wajib diisi.']);
            exit;
        }
        $sql = "UPDATE dr_fields SET field_label=?, field_type=?, is_required=?, is_show_on_table=?, sort_order=? WHERE id=?";
        $ok = sqlsrv_query($conn, $sql, [$label, $type, $required, $showTable, $sort, $id]);
        echo json_encode($ok ? ['status' => 'success', 'message' => 'Kolom berhasil diperbarui.'] : ['status' => 'error', 'message' => 'Gagal memperbarui.']);
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        sqlsrv_query($conn, "DELETE FROM dr_doc_values WHERE field_id = ?", [$id]);
        $ok = sqlsrv_query($conn, "DELETE FROM dr_fields WHERE id = ?", [$id]);
        echo json_encode($ok ? ['status' => 'success', 'message' => 'Kolom berhasil dihapus.'] : ['status' => 'error', 'message' => 'Gagal menghapus.']);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Action tidak dikenal.']);
    exit;
}

// ─── FETCH DATA ─────────────────────────────────────────────────────────────────
$stmtCat = sqlsrv_query($conn, "SELECT * FROM dr_categories WHERE id = ?", [$catId]);
$category = $stmtCat ? sqlsrv_fetch_array($stmtCat, SQLSRV_FETCH_ASSOC) : null;
if ($category && $category['color'] === 'user') {
    $category['color'] = $theme;
}
if (!$category || $category['parent_id'] === null) {
    header('Location: manage_categories.php');
    exit;
}

// Self-healing: Ensure system fields exist for this category
$systemFields = [
    ['field_name' => 'system_bagian', 'field_label' => 'Bagian', 'field_type' => 'select', 'is_required' => 1, 'is_show_on_table' => 1, 'sort_order' => 95, 'grid_class' => 'col-md-6'],
    ['field_name' => 'system_expire_date', 'field_label' => 'Expire', 'field_type' => 'date', 'is_required' => 1, 'is_show_on_table' => 1, 'sort_order' => 96, 'grid_class' => 'col-md-6'],
    ['field_name' => 'system_file_dokumen', 'field_label' => 'Upload Dokumen', 'field_type' => 'file', 'is_required' => 1, 'is_show_on_table' => 0, 'sort_order' => 97, 'grid_class' => 'col-md-12'],
    ['field_name' => 'system_email_reminder', 'field_label' => 'Email Reminder', 'field_type' => 'email', 'is_required' => 0, 'is_show_on_table' => 1, 'sort_order' => 98, 'grid_class' => 'col-md-6'],
    ['field_name' => 'system_no_whatsapp', 'field_label' => 'No WA', 'field_type' => 'text', 'is_required' => 0, 'is_show_on_table' => 1, 'sort_order' => 99, 'grid_class' => 'col-md-6']
];

foreach ($systemFields as $sf) {
    $checkSF = sqlsrv_query($conn, "SELECT 1 FROM dr_fields WHERE category_id = ? AND field_name = ?", [$catId, $sf['field_name']]);
    if ($checkSF && !sqlsrv_fetch_array($checkSF)) {
        sqlsrv_query($conn, "INSERT INTO dr_fields (category_id, field_label, field_name, field_type, is_required, is_show_on_table, sort_order, grid_class, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'system', GETDATE())", [
            $catId,
            $sf['field_label'],
            $sf['field_name'],
            $sf['field_type'],
            $sf['is_required'],
            $sf['is_show_on_table'],
            $sf['sort_order'],
            $sf['grid_class']
        ]);
    }
}

$fields = [];
$stmtF = sqlsrv_query($conn, "SELECT * FROM dr_fields WHERE category_id = ? ORDER BY sort_order ASC", [$catId]);
if ($stmtF) {
    while ($r = sqlsrv_fetch_array($stmtF, SQLSRV_FETCH_ASSOC)) {
        $fields[] = $r;
    }
}

// Calculate next sort order for custom fields (excluding system fields)
$customFieldsCount = 0;
foreach ($fields as $f) {
    if (strpos($f['field_name'], 'system_') !== 0) {
        $customFieldsCount++;
    }
}
$nextSortOrder = $customFieldsCount + 1;

$typeColors = ['text' => 'info', 'textarea' => 'secondary', 'number' => 'warning', 'date' => 'success', 'select' => 'primary', 'file' => 'danger', 'email' => 'dark'];
$typeIcons = ['text' => 'fas fa-font', 'textarea' => 'fas fa-align-left', 'number' => 'fas fa-hashtag', 'date' => 'fas fa-calendar', 'select' => 'fas fa-list', 'file' => 'fas fa-file', 'email' => 'fas fa-envelope'];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<style>
    .ui-state-highlight {
        height: 80px;
        background: rgba(0, 0, 0, 0.05);
        border: 2px dashed #007bff;
        border-radius: 15px;
    }

    .ui-resizable-e {
        cursor: ew-resize !important;
        width: 12px !important;
        right: 2px !important;
        background-color: rgba(0, 0, 0, 0.03);
        border-left: 1px dashed #ccc;
    }

    .ui-resizable-e:hover {
        background-color: rgba(0, 123, 255, 0.2);
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-6">
                    <h1>
                        <i
                            class="<?= htmlspecialchars($category['icon']) ?> mr-2 text-<?= htmlspecialchars($category['color']) ?>"></i>
                        Kolom: <span
                            class="text-<?= htmlspecialchars($category['color']) ?>"><?= htmlspecialchars($category['category_name']) ?></span>
                    </h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="manage_categories.php" class="btn btn-outline-secondary mr-2">
                        <i class="fas fa-arrow-left mr-1"></i>Kembali
                    </a>
                    <button class="btn btn-<?= htmlspecialchars($theme) ?> shadow-sm" data-toggle="modal"
                        data-target="#modalField" onclick="resetFieldModal()">
                        <i class="fas fa-plus mr-1"></i>Tambah Kolom
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card shadow-lg border-0 card-<?= htmlspecialchars($category['color']) ?> card-outline card-tabs"
                style="border-radius:15px;">
                <div class="card-header p-0 pt-1 border-bottom-0" style="border-radius:15px 15px 0 0;">
                    <ul class="nav nav-tabs" id="fieldTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active font-weight-bold" id="tab-table" data-toggle="pill"
                                href="#content-table" role="tab">
                                <i
                                    class="fas fa-table mr-2 text-<?= htmlspecialchars($category['color']) ?>"></i>Struktur
                                Data Kategori
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link font-weight-bold" id="tab-visual" data-toggle="pill"
                                href="#content-visual" role="tab">
                                <i
                                    class="fas fa-drafting-compass mr-2 text-<?= htmlspecialchars($category['color']) ?>"></i>Visual
                                Designer (Drag & Drop)
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-0">
                    <div class="tab-content">
                        <!-- TAB: TABLE -->
                        <div class="tab-pane fade show active" id="content-table" role="tabpanel">
                            <?php if (empty($fields)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Belum ada kolom. Klik "Tambah Kolom" untuk memulai.</p>
                                </div>
                            <?php else: ?>
                                <table class="table table-hover mb-0">
                                    <thead class="bg-light text-uppercase small font-weight-bold text-muted">
                                        <tr>
                                            <th width="60" class="text-center">Urutan</th>
                                            <th>Label Kolom</th>
                                            <th>Nama Database</th>
                                            <th>Tipe Data</th>
                                            <th class="text-center">Wajib?</th>
                                            <th class="text-center">Tampil di Tabel?</th>
                                            <th width="100" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($fields as $f): ?>
                                            <tr>
                                                <td class="text-center align-middle">
                                                    <?php if (strpos($f['field_name'], 'system_') === 0): ?>
                                                        <span class="text-muted">-</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary"><?= (int) $f['sort_order'] ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="font-weight-bold align-middle">
                                                    <?= htmlspecialchars($f['field_label']) ?>
                                                </td>
                                                <td class="text-monospace small align-middle text-muted">
                                                    <?= htmlspecialchars($f['field_name']) ?>
                                                </td>
                                                <td class="align-middle">
                                                    <span
                                                        class="badge badge-<?= $typeColors[$f['field_type']] ?? 'secondary' ?>">
                                                        <i
                                                            class="<?= $typeIcons[$f['field_type']] ?? 'fas fa-question' ?> mr-1"></i>
                                                        <?= htmlspecialchars(ucfirst($f['field_type'])) ?>
                                                    </span>
                                                </td>
                                                <td class="text-center align-middle">
                                                    <a href="javascript:void(0)" class="toggle-field-status" 
                                                       data-id="<?= $f['id'] ?>" 
                                                       data-type="is_required" 
                                                       data-val="<?= $f['is_required'] ?>"
                                                       title="Klik untuk mengubah status Wajib">
                                                        <?= $f['is_required'] ? '<i class="fas fa-check-circle text-success fa-lg"></i>' : '<i class="fas fa-times-circle text-muted fa-lg"></i>' ?>
                                                    </a>
                                                </td>
                                                <td class="text-center align-middle">
                                                    <a href="javascript:void(0)" class="toggle-field-status" 
                                                       data-id="<?= $f['id'] ?>" 
                                                       data-type="is_show_on_table" 
                                                       data-val="<?= $f['is_show_on_table'] ?>"
                                                       title="Klik untuk mengubah status Tampil di Tabel">
                                                        <?= $f['is_show_on_table'] ? '<i class="fas fa-eye text-primary fa-lg"></i>' : '<i class="fas fa-eye-slash text-muted fa-lg"></i>' ?>
                                                    </a>
                                                </td>
                                                <td class="text-center align-middle">
                                                    <?php if (strpos($f['field_name'], 'system_') === 0): ?>
                                                        <span class="badge badge-light border text-muted"><i
                                                                class="fas fa-lock mr-1"></i>Sistem</span>
                                                    <?php else: ?>
                                                        <button class="btn btn-xs btn-outline-primary btn-edit-field mr-1"
                                                            data-id="<?= (int) $f['id'] ?>"><i class="fas fa-edit"></i></button>
                                                        <button class="btn btn-xs btn-outline-danger btn-del-field"
                                                            data-id="<?= (int) $f['id'] ?>"
                                                            data-name="<?= htmlspecialchars($f['field_label']) ?>"><i
                                                                class="fas fa-trash"></i></button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>

                        <!-- TAB: VISUAL DESIGNER -->
                        <div class="tab-pane fade p-4 bg-light" id="content-visual" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h5 class="font-weight-bold mb-0 text-<?= htmlspecialchars($category['color']) ?>">
                                        <i class="fas fa-magic mr-2"></i>Kanvas Desain Visual
                                    </h5>
                                    <small class="text-muted"><i class="fas fa-info-circle mr-1"></i>Tarik (<i
                                            class="fas fa-arrows-alt"></i>) kotak untuk memindah urutan. Tarik tepi
                                        kanan kotak (<i class="fas fa-arrows-alt-h"></i>) untuk mengatur lebar (25% -
                                        100%).</small>
                                </div>
                                <button type="button"
                                    class="btn btn-success shadow-sm font-weight-bold rounded-pill px-4"
                                    id="btnSaveVisual">
                                    <i class="fas fa-save mr-1"></i> Simpan Desain
                                </button>
                            </div>

                            <div class="border p-4 bg-white shadow-sm" style="border-radius: 15px; min-height: 300px;">
                                <div class="row" id="visualBuilderCanvas">
                                    <?php foreach ($fields as $f):
                                        $gc = !empty($f['grid_class']) ? $f['grid_class'] : 'col-md-6';
                                        $isSystem = (strpos($f['field_name'], 'system_') === 0);
                                        $cardTheme = $isSystem ? 'secondary' : htmlspecialchars($category['color']);
                                        if ($f['field_name'] === 'system_expire_date')
                                            $cardTheme = 'danger'; // Red indicator for expire date
                                        ?>
                                        <div class="<?= htmlspecialchars($gc) ?> visual-field mb-3"
                                            data-id="<?= $f['id'] ?>" style="position: relative;">
                                            <div class="card card-outline card-<?= $cardTheme ?> h-100 shadow-sm"
                                                style="cursor: move; transition: none;">
                                                <div class="card-body p-3">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <label class="mb-0 text-truncate">
                                                            <i class="fas fa-grip-vertical text-muted mr-2"></i>
                                                            <?= htmlspecialchars($f['field_label']) ?>
                                                            <?php if ($isSystem): ?>
                                                                <span class="badge badge-secondary ml-1"
                                                                    style="font-size:10px;"><i
                                                                        class="fas fa-lock mr-1"></i>Sistem</span>
                                                            <?php endif; ?>
                                                        </label>
                                                        <span
                                                            class="badge badge-light border grid-label"><?= str_replace('col-md-', 'L', $gc) ?></span>
                                                    </div>
                                                    <input type="text" class="form-control form-control-sm" disabled
                                                        placeholder="<?= htmlspecialchars(ucfirst($f['field_type'])) ?>">
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
                <div class="card-footer bg-white border-top text-center py-3">
                    <p class="text-muted small mb-0">
                        <i class="fas fa-info-circle mr-1"></i>
                        Kolom <strong>Expire</strong>, <strong>Upload File</strong>, <strong>Email
                            Reminder</strong>, dan <strong>No WA</strong>
                        sudah disediakan otomatis oleh sistem.
                    </p>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Modal Tambah / Edit Field -->
<div class="modal fade" id="modalField" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-<?= htmlspecialchars($theme) ?> text-white">
                <h5 class="modal-title font-weight-bold" id="modalFieldTitle">
                    <i class="fas fa-plus-circle mr-2"></i>Tambah Kolom Baru
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="formField">
                <input type="hidden" name="action" id="fieldAction" value="add">
                <input type="hidden" name="id" id="fieldEditId" value="">
                <div class="modal-body p-4">
                    <div class="form-group">
                        <label class="font-weight-bold">Label Kolom (tampil di form) <span
                                class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="field_label" id="inputFieldLabel" placeholder=""
                            required>
                    </div>
                    <div class="form-group" id="rowFieldName">
                        <label class="font-weight-bold">Nama Field (huruf kecil, tanpa spasi) <span
                                class="text-danger">*</span></label>
                        <input type="text" class="form-control text-monospace" name="field_name" id="inputFieldName"
                            placeholder="" pattern="[a-z0-9_]+"
                            title="Hanya huruf kecil, angka, dan underscore (tanpa spasi)" required>
                        <small class="text-muted">Akan digunakan sebagai identifier di database.</small>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Tipe Data</label>
                                <select class="form-control custom-select" name="field_type" id="inputFieldType">
                                    <option value="text">Text (Baris Tunggal)</option>
                                    <option value="textarea">Textarea (Paragraf)</option>
                                    <option value="number">Angka</option>
                                    <option value="date">Tanggal</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Urutan Tampil</label>
                                <input type="number" class="form-control" name="sort_order" id="inputSortOrder"
                                    value="<?= $nextSortOrder ?>" min="1">
                            </div>
                        </div>
                    </div>
                    <div class="custom-control custom-switch mt-2 mb-2">
                        <input type="checkbox" class="custom-control-input" name="is_required" id="swIsRequired"
                            checked>
                        <label class="custom-control-label font-weight-bold" for="swIsRequired">Wajib diisi
                            (Required)</label>
                    </div>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" name="is_show_on_table" id="swShowTable"
                            checked>
                        <label class="custom-control-label font-weight-bold" for="swShowTable">Tampilkan di Tabel
                            Utama</label>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-<?= htmlspecialchars($theme) ?> px-4" id="btnSubmitField">
                        <i class="fas fa-save mr-1"></i>Simpan Kolom
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>
<script>
    const CATEGORY_ID = <?= $catId ?>;

    function resetFieldModal() {
        $('#modalFieldTitle').html('<i class="fas fa-plus-circle mr-2"></i>Tambah Kolom Baru');
        $('#fieldAction').val('add');
        $('#fieldEditId').val('');
        $('#formField')[0].reset();
        $('#rowFieldName').show();
        $('#inputFieldName').prop('readonly', false);
        $('#swIsRequired').prop('checked', true);
        $('#swShowTable').prop('checked', true);
    }

    // Auto-generate field_name from label & Clean input
    $('#inputFieldLabel').on('input', function () {
        if ($('#fieldAction').val() !== 'add') return;
        const v = $(this).val().toLowerCase()
            .replace(/\s+/g, '_')           // Spasi jadi underscore
            .replace(/[^a-z0-9_]+/g, '')    // Hapus karakter ilegal
            .replace(/_{2,}/g, '_')         // Cegah double underscore
            .replace(/^_|_$/g, '');         // Hapus underscore di awal/akhir
        $('#inputFieldName').val(v);
    });

    // Force clean inputFieldName while typing
    $('#inputFieldName').on('input', function () {
        const v = $(this).val().toLowerCase().replace(/[^a-z0-9_]+/g, '');
        $(this).val(v);
    });

    // Edit Field
    $(document).on('click', '.btn-edit-field', function () {
        const id = $(this).data('id');
        $.ajax({
            url: '', type: 'POST', data: { action: 'get', id }, dataType: 'json',
            success(res) {
                if (res.status !== 'success') { Swal.fire('Error', res.message, 'error'); return; }
                const d = res.data;
                $('#modalFieldTitle').html('<i class="fas fa-edit mr-2"></i>Edit Kolom');
                $('#fieldAction').val('edit');
                $('#fieldEditId').val(d.id);
                $('#inputFieldLabel').val(d.field_label);
                $('#inputFieldName').val(d.field_name).prop('readonly', true);
                $('#inputFieldType').val(d.field_type);
                $('#inputSortOrder').val(d.sort_order);
                $('#swIsRequired').prop('checked', d.is_required == 1);
                $('#swShowTable').prop('checked', d.is_show_on_table == 1);
                $('#rowFieldName').hide();
                $('#modalField').modal('show');
            }
        });
    });

    // Delete Field
    $(document).on('click', '.btn-del-field', function () {
        const id = $(this).data('id');
        const name = $(this).data('name');
        Swal.fire({
            title: 'Hapus Kolom "' + name + '"?',
            text: 'Semua nilai data untuk kolom ini juga akan dihapus.',
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#d33', confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal'
        }).then(r => {
            if (!r.isConfirmed) return;
            $.ajax({
                url: '', type: 'POST', data: { action: 'delete', id }, dataType: 'json',
                success(res) {
                    Swal.fire(res.status === 'success' ? 'Berhasil' : 'Gagal', res.message, res.status).then(() => location.reload());
                }
            });
        });
    });

    // Form Submit
    $('#formField').on('submit', function (e) {
        e.preventDefault();
        const btn = $('#btnSubmitField').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Menyimpan...');
        $.ajax({
            url: '', type: 'POST', data: $(this).serialize(), dataType: 'json',
            success(res) {
                if (res.status === 'success') {
                    Swal.fire('Berhasil!', res.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Gagal!', res.message, 'error');
                    btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Simpan Kolom');
                }
            },
            error() {
                Swal.fire('Error', 'Terjadi kesalahan sistem.', 'error');
                btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Simpan Kolom');
            }
        });
    });
</script>

<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
    // --- VISUAL BUILDER LOGIC ---
    $(function () {
        // 1. Sortable (Drag to Move)
        $('#visualBuilderCanvas').sortable({
            items: '.visual-field',
            placeholder: 'ui-state-highlight mb-3',
            handle: '.card-body',
            tolerance: 'pointer',
            forcePlaceholderSize: true,
            opacity: 0.8
        });

        // 2. Resizable (Drag to Resize)
        $('.visual-field').resizable({
            handles: 'e', // East edge only
            minWidth: 150,
            stop: function (event, ui) {
                // Calculate relative width percentage
                var parentWidth = $('#visualBuilderCanvas').width();
                var widthPct = (ui.size.width / parentWidth) * 100;

                // Snap to Bootstrap Grid
                var newClass = 'col-md-12';
                var label = 'L 100%';

                if (widthPct <= 28) { newClass = 'col-md-3'; label = 'L 25%'; }
                else if (widthPct <= 40) { newClass = 'col-md-4'; label = 'L 33%'; }
                else if (widthPct <= 58) { newClass = 'col-md-6'; label = 'L 50%'; }
                else if (widthPct <= 70) { newClass = 'col-md-8'; label = 'L 66%'; }
                else if (widthPct <= 85) { newClass = 'col-md-9'; label = 'L 75%'; }

                // Apply new grid class
                $(this).removeClass('col-md-3 col-md-4 col-md-6 col-md-8 col-md-9 col-md-12').addClass(newClass);
                $(this).find('.grid-label').text(label);

                // Reset inline styles so Bootstrap grid takes over
                $(this).css({ width: '', height: '' });
            }
        });

        // 3. Save Visual Design
        $('#btnSaveVisual').on('click', function () {
            const btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');

            let visualData = [];
            $('#visualBuilderCanvas .visual-field').each(function (index) {
                let id = $(this).data('id');
                // Extract the col-md-* class
                let gridClass = $(this).attr('class').split(' ').find(c => c.startsWith('col-md-')) || 'col-md-6';

                visualData.push({
                    id: id,
                    sort_order: index + 1,
                    grid_class: gridClass
                });
            });

            $.ajax({
                url: 'ajax_handler.php',
                type: 'POST',
                data: {
                    action: 'save_visual_design',
                    fields: visualData
                },
                dataType: 'json',
                success: function (res) {
                    if (res.status === 'success') {
                        Swal.fire('Tersimpan!', 'Layout form berhasil diperbarui.', 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Gagal!', res.message || 'Terjadi kesalahan.', 'error');
                        btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan Desain');
                    }
                },
                error: function () {
                    Swal.fire('Error!', 'Tidak dapat terhubung ke server.', 'error');
                    btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan Desain');
                }
            });
        });

        // Toggle status Wajib & Tampil di Tabel secara Real-Time
        $(document).on('click', '.toggle-field-status', function(e) {
            e.preventDefault();
            const $this = $(this);
            const fieldId = $this.data('id');
            const fieldType = $this.data('type');
            const currentVal = parseInt($this.attr('data-val'));
            const newVal = currentVal === 1 ? 0 : 1;
            
            const originalHtml = $this.html();
            $this.html('<i class="fas fa-spinner fa-spin text-muted fa-lg"></i>');
            
            $.ajax({
                url: 'ajax_handler.php',
                type: 'POST',
                data: {
                    action: 'toggle_field_status',
                    id: fieldId,
                    field: fieldType,
                    value: newVal
                },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        $this.attr('data-val', newVal);
                        $this.data('val', newVal);
                        
                        if (fieldType === 'is_required') {
                            $this.html(newVal ? '<i class="fas fa-check-circle text-success fa-lg"></i>' : '<i class="fas fa-times-circle text-muted fa-lg"></i>');
                        } else {
                            $this.html(newVal ? '<i class="fas fa-eye text-primary fa-lg"></i>' : '<i class="fas fa-eye-slash text-muted fa-lg"></i>');
                        }
                        
                        const Toast = Swal.mixin({
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 2000,
                            timerProgressBar: true
                        });
                        Toast.fire({
                            icon: 'success',
                            title: 'Status berhasil diperbarui'
                        });
                    } else {
                        Swal.fire('Gagal!', res.message, 'error');
                        $this.html(originalHtml);
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Gagal menghubungi server.', 'error');
                    $this.html(originalHtml);
                }
            });
        });
    });
</script>