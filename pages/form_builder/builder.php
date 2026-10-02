<?php
session_start();
require_once '../../koneksi.php';

if (!isset($_SESSION['UserId'])) {
    header("Location: ../../login.php");
    exit();
}

function formBuilderSupportsPublicColumn($conn) {
    static $hasColumn = null;
    if ($hasColumn !== null) {
        return $hasColumn;
    }

    $sqlCheck = "SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.Form_Dynamic_Templates') AND name = 'is_public'";
    $stmtCheck = sqlsrv_query($conn, $sqlCheck);
    $hasColumn = ($stmtCheck && sqlsrv_fetch_array($stmtCheck)) ? true : false;
    if ($stmtCheck) {
        sqlsrv_free_stmt($stmtCheck);
    }

    return $hasColumn;
}

include '../../includes/header.php';
include '../../includes/sidebar.php';

$themeColor = $_SESSION['Theme'] ?? 'primary';
$supportsPublicColumn = formBuilderSupportsPublicColumn($conn);

// Load existing template if editing
$templateParam = isset($_GET['template_name']) ? trim($_GET['template_name']) : null;
$templateIdParam = $_GET['id'] ?? null; // legacy support
$templateId = null;
$templateName = '';
$templateDescription = '';
$isPublic = false;
$fields = [];

if (!empty($templateParam) || !empty($templateIdParam)) {
    if (!empty($templateParam)) {
        $sql = "SELECT * FROM Form_Dynamic_Templates WHERE template_name = ?";
        $stmt = sqlsrv_query($conn, $sql, [$templateParam]);
    } else {
        $sql = "SELECT * FROM Form_Dynamic_Templates WHERE id = ?";
        $stmt = sqlsrv_query($conn, $sql, [$templateIdParam]);
    }
    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $templateId = $row['id'];
        $templateName = $row['template_name'];
        $templateDescription = $row['template_description'];
        $isPublic = $supportsPublicColumn && !empty($row['is_public']);
        $fields = json_decode($row['fields_json'], true);
    }
}
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1>Design Form</h1>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            <?php 
            $successMessage = null;
            if (isset($_SESSION['success'])) {
                $successMessage = $_SESSION['success'];
                unset($_SESSION['success']);
            }
            ?>
        </div>
        <div class="container-fluid">
            <form action="process_builder.php" method="POST" id="form-builder" enctype="multipart/form-data">
                <input type="hidden" name="template_id" value="<?php echo htmlspecialchars($templateId); ?>">
                <input type="hidden" name="original_template_name" value="<?php echo htmlspecialchars($templateId ? $templateName : ''); ?>">
                
                <div class="card card-<?php echo htmlspecialchars($themeColor); ?>">
                    <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                        <h3 class="card-title">Informasi Form</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="template_name">Nama Form <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="template_name" value="<?php echo htmlspecialchars($templateName); ?>" required placeholder="Contoh: Form Pengajuan Lembur">
                        </div>
                        <div class="form-group">
                            <label for="template_description">Deskripsi</label>
                            <textarea class="form-control" name="template_description" rows="2" placeholder="Penjelasan singkat tentang form ini"><?php echo htmlspecialchars($templateDescription); ?></textarea>
                        </div>
                        <?php if ($supportsPublicColumn): ?>
                            <div class="form-group">
                                <label class="d-block">Akses Form</label>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="is_public" name="is_public" value="1" <?php echo $isPublic ? 'checked' : ''; ?>>
                                    <label class="custom-control-label" for="is_public">Form Publik (bisa diisi tanpa login)</label>
                                </div>
                                <small class="text-muted">Jika dimatikan, hanya user yang login yang dapat membuka dan mengisi form ini.</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card card-<?php echo htmlspecialchars($themeColor); ?> card-outline">
                    <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                        <h3 class="card-title">Struktur Form (Fields)</h3>
                        <br><small class="text-white-50"><i class="fas fa-info-circle"></i> Drag and drop untuk mengubah urutan</small>
                    </div>
                    <div class="card-body" id="fields-container">
                        <!-- Fields will be added here -->
                    </div>
                    <div class="card-footer">
                        <button type="button" class="btn btn-sm btn-info" onclick="addField()">
                            <i class="fas fa-plus"></i> Tambah Field
                        </button>
                        <button type="button" class="btn btn-sm btn-success" onclick="addExample()">
                            <i class="fas fa-image"></i> Tambah Contoh
                        </button>
                        <button type="submit" class="btn btn-sm btn-<?php echo htmlspecialchars($themeColor); ?> float-right">
                            <i class="fas fa-save"></i> Simpan Template
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </section>
</div>

<!-- Field Template (Hidden) -->
<template id="field-template">
    <div class="field-item card mb-3 builder-field-card" data-item-type="field">
        <input type="hidden" class="item-order" name="item_orders[]" value="">
        <input type="hidden" class="item-type" name="item_types[]" value="field">
        <div class="card-body py-3">
            <div class="form-row align-items-end">
                <div class="col-auto pr-md-0 mb-2 mb-md-0 text-center">
                    <span class="drag-handle btn btn-light btn-sm btn-square" title="Drag untuk mengubah urutan">
                        <i class="fas fa-grip-vertical text-muted"></i>
                    </span>
                </div>
                <div class="col-md mb-3 mb-md-0">
                    <label class="small text-muted text-uppercase font-weight-bold mb-1">Label Field</label>
                    <input type="text" class="form-control form-control-sm" name="field_labels[]" placeholder="Label Field (misal: Alasan)" required>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <label class="small text-muted text-uppercase font-weight-bold mb-1">Tipe</label>
                    <select class="form-control form-control-sm field-type" name="field_types[]" onchange="toggleOptions(this)">
                        <option value="text">Teks Singkat</option>
                        <option value="textarea">Teks Panjang (Paragraf)</option>
                        <option value="number">Angka</option>
                        <option value="date">Tanggal</option>
                        <option value="select">Pilihan (Dropdown)</option>
                        <option value="radio">Tombol Radio (Pilihan Tunggal)</option>
                        <option value="checkbox">Kotak Centang (Pilihan Ganda)</option>
                        <option value="time">Waktu</option>
                        <option value="color">Warna</option>
                        <option value="range">Rentang (Slider)</option>
                        <option value="file">Upload File</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3 mb-md-0">
                    <label class="small text-muted text-uppercase font-weight-bold mb-1 d-block">Validasi</label>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input required-checkbox" id="" onchange="updateRequiredHidden(this)">
                        <input type="hidden" name="field_required[]" value="0">
                        <label class="custom-control-label required-label" for="">Wajib Diisi</label>
                    </div>
                </div>
                <div class="col-md-auto text-right">
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeField(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <div class="form-row mt-3 validation-details-container d-none">
                <div class="col-md-6 mb-2">
                    <label class="small text-muted text-uppercase font-weight-bold mb-1">Placeholder (Tampilan Teks)</label>
                    <input type="text" class="form-control form-control-sm mb-2" name="field_visual_placeholders[]" placeholder="Contoh: Masukkan NIK anda">
                    
                    <div class="format-validation-group">
                        <label class="small text-muted text-uppercase font-weight-bold mb-1">Format Validasi (Logika Pattern)</label>
                        <div class="row">
                            <div class="col-md-6">
                                <input type="text" class="form-control form-control-sm" name="field_placeholders[]" placeholder="Contoh: GRN/12345" oninput="updateFormatDescription(this)">
                            </div>
                            <div class="col-md-6">
                                <input type="text" class="form-control form-control-sm bg-light" readonly placeholder="Hasil: 3 huruf, /, 5 Angka">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-2">
                    <label class="small text-muted text-uppercase font-weight-bold mb-1">Max Length</label>
                    <input type="number" class="form-control form-control-sm mb-2" name="field_maxlengths[]" placeholder="Max karakter">
                    
                    <div class="format-capitalization-group">
                        <label class="small text-muted text-uppercase font-weight-bold mb-1">Format Huruf</label>
                        <select class="form-control form-control-sm" name="field_capitalizations[]">
                            <option value="">Normal</option>
                            <option value="uppercase">Huruf Kapital (UPPERCASE)</option>
                            <option value="lowercase">Huruf Kecil (lowercase)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="form-row mt-3 options-container d-none">
                <div class="col-12">
                    <label class="small text-muted text-uppercase font-weight-bold mb-1">Daftar Pilihan</label>
                    <input type="text" class="form-control form-control-sm" name="field_options[]" placeholder="Pisahkan dengan koma, misal: Ya, Tidak, Ragu">
                </div>
            </div>
        </div>
    </div>
</template>

<!-- Example Section Template (Hidden) -->
<template id="example-template">
    <div class="example-item card mb-3 builder-example-card" data-item-type="example">
        <input type="hidden" class="item-order" name="item_orders[]" value="">
        <input type="hidden" class="item-type" name="item_types[]" value="example">
        <div class="card-body py-3">
            <div class="form-row align-items-center mb-3">
                <div class="col-auto pr-md-0 text-center mb-2 mb-md-0">
                    <span class="drag-handle btn btn-light btn-sm btn-square" title="Drag untuk mengubah urutan">
                        <i class="fas fa-grip-vertical text-muted"></i>
                    </span>
                </div>
                <div class="col">
                    <span class="badge badge-info badge-pill px-3 py-2"><i class="fas fa-image mr-1"></i> Contoh / Keterangan</span>
                </div>
                <div class="col-auto text-right">
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeExample(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label class="small text-muted text-uppercase font-weight-bold">Deskripsi / Keterangan</label>
                <textarea class="form-control form-control-sm" name="example_descriptions[]" rows="3" placeholder="Tuliskan keterangan atau instruksi untuk user..."></textarea>
            </div>
            <div class="form-group mb-0">
                <label class="small text-muted text-uppercase font-weight-bold">Gambar Contoh (opsional)</label>
                <div class="custom-file custom-file-sm">
                    <input type="file" class="custom-file-input example-image" name="example_images[]" accept="image/*" onchange="previewExampleImage(this)">
                    <label class="custom-file-label">Pilih gambar...</label>
                </div>
                <input type="hidden" name="example_existing_images[]" value="">
                <div class="example-image-preview mt-3 d-none">
                    <img src="" class="img-thumbnail" style="max-width: 300px; max-height: 200px;">
                    <button type="button" class="btn btn-outline-danger btn-sm ml-2" onclick="removeExampleImage(this)">
                        <i class="fas fa-times"></i> Hapus Gambar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
.builder-field-card,
.builder-example-card {
    border: 1px solid rgba(0, 0, 0, 0.08);
    border-radius: 0.85rem;
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.08);
}
.builder-example-card {
    background: rgba(0, 123, 255, 0.04);
}
.drag-handle.btn {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: inset 0 1px 0 rgba(255,255,255,.6);
}
.btn-square {
    width: 38px;
    height: 38px;
    padding: 0;
}
.custom-file-sm .custom-file-label,
.custom-file-sm .custom-file-label::after {
    padding-top: 0.35rem;
    padding-bottom: 0.35rem;
    height: auto;
}
</style>

<?php include '../../includes/footer.php'; ?>

<!-- SortableJS for drag and drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="../../plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.min.css">
<!-- SweetAlert2 JS -->
<script src="../../plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
let fieldCount = 0;
let sortableInstance = null;

function addField(data = null) {
    const container = document.getElementById('fields-container');
    const template = document.getElementById('field-template');
    const clone = template.content.cloneNode(true);
    const uid = `required-field-${Date.now()}-${Math.floor(Math.random() * 1000)}`;
    const requiredCheckbox = clone.querySelector('.required-checkbox');
    const requiredLabel = clone.querySelector('.required-label');
    if (requiredCheckbox) {
        requiredCheckbox.id = uid;
    }
    if (requiredLabel) {
        requiredLabel.setAttribute('for', uid);
    }
    
    if (data) {
        clone.querySelector('input[name="field_labels[]"]').value = data.label;
        const typeSelect = clone.querySelector('select[name="field_types[]"]');
        typeSelect.value = data.type;
        if (data.required) {
            clone.querySelector('.required-checkbox').checked = true;
            clone.querySelector('input[name="field_required[]"]').value = "1";
        }
        
        // Populate new validation fields
        const validationContainer = clone.querySelector('.validation-details-container');
        if (['text', 'textarea', 'number'].includes(data.type)) {
            validationContainer.classList.remove('d-none');
            if (data.visual_placeholder) clone.querySelector('input[name="field_visual_placeholders[]"]').value = data.visual_placeholder;
            if (data.placeholder) {
                const formatInput = clone.querySelector('input[name="field_placeholders[]"]');
                formatInput.value = data.placeholder;
                // Trigger description update
                setTimeout(() => updateFormatDescription(formatInput), 0);
            }
            if (data.maxlength) clone.querySelector('input[name="field_maxlengths[]"]').value = data.maxlength;
            if (data.capitalization) clone.querySelector('select[name="field_capitalizations[]"]').value = data.capitalization;
        }

        if (['select', 'radio', 'checkbox'].includes(data.type)) {
            clone.querySelector('.options-container').classList.remove('d-none');
            clone.querySelector('input[name="field_options[]"]').value = data.options ? data.options.join(', ') : '';
        }
        
        // Trigger toggleOptions to hide/show appropriate fields
        container.appendChild(clone);
        const addedItem = container.lastElementChild;
        const addedSelect = addedItem.querySelector('select[name="field_types[]"]');
        if (addedSelect) {
            toggleOptions(addedSelect);
        }
    } else {
        // Default: show validation fields for 'text' (default type)
        clone.querySelector('.validation-details-container').classList.remove('d-none');
        container.appendChild(clone);
    }
}

function removeField(btn) {
    btn.closest('.field-item').remove();
    updateItemOrders();
}

function toggleOptions(select) {
    const item = select.closest('.field-item');
    const optionsContainer = item.querySelector('.options-container');
    const validationContainer = item.querySelector('.validation-details-container');
    const formatValidationGroup = item.querySelector('.format-validation-group');
    const formatCapitalizationGroup = item.querySelector('.format-capitalization-group');
    
    if (['select', 'radio', 'checkbox'].includes(select.value)) {
        optionsContainer.classList.remove('d-none');
    } else {
        optionsContainer.classList.add('d-none');
    }

    if (['text', 'textarea', 'number'].includes(select.value)) {
        validationContainer.classList.remove('d-none');
    } else {
        validationContainer.classList.add('d-none');
    }
    
    // Hide Format Validasi and Format Huruf for number type
    if (select.value === 'number') {
        if (formatValidationGroup) formatValidationGroup.classList.add('d-none');
        if (formatCapitalizationGroup) formatCapitalizationGroup.classList.add('d-none');
    } else if (['text', 'textarea'].includes(select.value)) {
        if (formatValidationGroup) formatValidationGroup.classList.remove('d-none');
        if (formatCapitalizationGroup) formatCapitalizationGroup.classList.remove('d-none');
    }
}

function updateRequiredHidden(checkbox) {
    const hidden = checkbox.nextElementSibling;
    hidden.value = checkbox.checked ? "1" : "0";
}

// Example section functions
function addExample(data = null) {
    const container = document.getElementById('fields-container');
    const template = document.getElementById('example-template');
    const clone = template.content.cloneNode(true);
    
    if (data) {
        // Populate description
        if (data.description) {
            clone.querySelector('textarea[name="example_descriptions[]"]').value = data.description;
        }
        // Populate existing image
        if (data.example_image) {
            clone.querySelector('input[name="example_existing_images[]"]').value = data.example_image;
            const preview = clone.querySelector('.example-image-preview');
            const img = preview.querySelector('img');
            img.src = '../../uploads/form_examples/' + data.example_image;
            preview.classList.remove('d-none');
        }
    }
    
    container.appendChild(clone);
}

function removeExample(btn) {
    btn.closest('.example-item').remove();
    updateItemOrders();
}

function previewExampleImage(input) {
    const exampleItem = input.closest('.example-item');
    const preview = exampleItem.querySelector('.example-image-preview');
    const img = preview.querySelector('img');
    const label = input.nextElementSibling;
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            preview.classList.remove('d-none');
        };
        reader.readAsDataURL(input.files[0]);
        label.textContent = input.files[0].name;
    }
}

function removeExampleImage(btn) {
    const exampleItem = btn.closest('.example-item');
    const preview = exampleItem.querySelector('.example-image-preview');
    const fileInput = exampleItem.querySelector('.example-image');
    const hiddenInput = exampleItem.querySelector('input[name="example_existing_images[]"]');
    const label = fileInput.nextElementSibling;
    
    preview.classList.add('d-none');
    preview.querySelector('img').src = '';
    fileInput.value = '';
    hiddenInput.value = '';
    label.textContent = 'Pilih gambar...';
}

// Initialize SortableJS for drag and drop
function initSortable() {
    const container = document.getElementById('fields-container');
    if (container) {
        if (sortableInstance) {
            sortableInstance.destroy();
        }
        sortableInstance = new Sortable(container, {
            animation: 150,
            handle: '.drag-handle',
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            forceFallback: true,
            fallbackTolerance: 5,
            onEnd: function(evt) {
                updateItemOrders();
            }
        });
    }
}

// Update item orders after drag and drop
function updateItemOrders() {
    const container = document.getElementById('fields-container');
    const items = container.querySelectorAll('.field-item, .example-item');
    items.forEach((item, index) => {
        const orderInput = item.querySelector('.item-order');
        if (orderInput) {
            orderInput.value = index;
        }
    });
}

// Store original functions
const originalAddField = addField;
const originalAddExample = addExample;

// Override addField to reinitialize sortable
window.addField = function(data) {
    originalAddField(data);
    setTimeout(function() {
        initSortable();
        updateItemOrders();
    }, 50);
};

// Override addExample to reinitialize sortable
window.addExample = function(data) {
    originalAddExample(data);
    setTimeout(function() {
        initSortable();
        updateItemOrders();
    }, 50);
};

// Initialize existing fields if any
<?php if (!empty($fields)): ?>
    <?php foreach ($fields as $f): ?>
        <?php if (isset($f['item_type']) && $f['item_type'] === 'example'): ?>
            addExample(<?php echo json_encode($f); ?>);
        <?php else: ?>
            addField(<?php echo json_encode($f); ?>);
        <?php endif; ?>
    <?php endforeach; ?>
<?php else: ?>
    // Add one empty field by default
    addField();
<?php endif; ?>

// Initialize sortable when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize sortable after fields are loaded
    setTimeout(function() {
        initSortable();
        updateItemOrders();
    }, 100);
    
    // Show success message with SweetAlert2
    <?php if ($successMessage): ?>
    Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: '<?php echo addslashes($successMessage); ?>',
        confirmButtonColor: '#28a745',
        timer: 3000,
        timerProgressBar: true
    });
    <?php endif; ?>
});

// Add CSS for sortable drag effect
const style = document.createElement('style');
style.textContent = `
    .sortable-ghost {
        opacity: 0.4;
        background: #f0f0f0;
    }
    .sortable-chosen {
        cursor: grabbing;
    }
    .sortable-drag {
        opacity: 0.8;
    }
    .field-item, .example-item {
        transition: transform 0.2s;
    }
`;
document.head.appendChild(style);

function updateFormatDescription(input) {
    const val = input.value;
    const resultInput = input.parentElement.nextElementSibling.querySelector('input');
    
    if (!val) {
        resultInput.value = '';
        return;
    }

    let description = [];
    let i = 0;
    while (i < val.length) {
        const char = val[i];
        if (/[a-zA-Z]/.test(char)) {
            let count = 0;
            while (i < val.length && /[a-zA-Z]/.test(val[i])) {
                count++;
                i++;
            }
            description.push(count + ' Huruf');
        } else if (/\d/.test(char)) {
            let count = 0;
            while (i < val.length && /\d/.test(val[i])) {
                count++;
                i++;
            }
            description.push(count + ' Angka');
        } else {
            description.push(char);
            i++;
        }
    }
    
    resultInput.value = description.join(', ');
}
</script>
