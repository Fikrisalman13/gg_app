<?php
// pages/resep_obat/master_obat/list_obat.php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

/// helper: ambil permission user untuk menu Asset (MenuId = 213)
function checkPermissions($conn, $groupId, $menuId) {
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete
            FROM dbo.SMGroupTrustee
            WHERE GroupId = ? AND MenuId = ?";
    $params = [$groupId, $menuId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    $permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    if ($stmt !== false) sqlsrv_free_stmt($stmt);

    return $permissions;
}

// Ambil permission (dipakai untuk tombol "Tambah" dan fallback JS)
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 213);
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}


$themeColor = $_SESSION['Theme'] ?? 'primary';
$showMinimumStockImport = isset($_GET['debug']) && $permissions['CanEdit'] == 1;

include '../../../includes/header.php';
include '../../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1>Master Obat</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="#">Master Data</a></li>
                        <li class="breadcrumb-item active">Obat</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <!-- Table Card -->
            <div class="card card-<?= htmlspecialchars($themeColor); ?> card-outline">
                <div class="card-header bg-<?= htmlspecialchars($themeColor); ?> text-white">
                    <h3 class="card-title"><i class="fas fa-pills mr-1"></i> Data Master Obat</h3>
                    <div class="card-tools">
                        <?php if ($showMinimumStockImport): ?>
                        <button type="button" class="btn btn-info btn-sm mr-1" id="master-obat-import-open" data-toggle="modal" data-target="#master-obat-import-modal">
                            <i class="fas fa-file-excel text-white"></i> <span class="text-white">Import Target Minimum</span>
                        </button>
                        <?php endif; ?>
                        <?php if ($permissions['CanAdd'] == 1): ?>
                        <button type="button" class="btn btn-success btn-sm" id="btnAddObat" data-toggle="modal" data-target="#modal-obat">
                            <i class="fas fa-plus text-white"></i> <span class="text-white">Tambah Data</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tableObat" class="table table-bordered table-hover table-sm nowrap" style="width:100%">
                        <thead class="bg-gray-light">
                            <tr>
                                <th>Kode Obat Kurabo</th>
                                <th>Code Pro-Int</th>
                                <th>Nama Obat</th>
                                <th>Group</th>
                                <th>UOM</th>
                                <th>Target Minimum KG</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL ADD/EDIT -->
<div class="modal fade" id="modal-obat">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor); ?>">
                <h4 class="modal-title text-white" id="modalTitle">Tambah Obat</h4>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="obatForm">
                <div class="modal-body">
                    <!-- Hidden field for Edit Mode -->
                    <input type="hidden" id="mode" name="mode" value="add">
                    <!-- Primary Key ID -->
                    <input type="hidden" id="obat_id" name="obat_id">

                    <div class="form-group">
                        <label>Kode Obat Kurabo</label>
                        <input type="text" class="form-control" id="kode_obat" name="kode_obat" required>
                    </div>

                    <div class="form-group">
                        <label>Code Prod ProInt</label>
                        <select class="form-control select2-proint" id="codeprod_proint" name="codeprod_proint" style="width: 100%;">
                            <!-- Option will be added dynamically -->
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Nama Obat</label>
                        <input type="text" class="form-control" id="nama_obat" name="nama_obat" required>
                    </div>

                    <div class="form-group">
                        <label>Group Obat</label>
                        <input type="text" class="form-control" id="group_obat" name="group_obat" readonly>
                    </div>

                    <div class="form-group">
                        <label>UOM</label>
                        <input type="text" class="form-control" id="uom" name="uom" value="G/L" placeholder="KG">
                    </div>

                    <div class="form-group">
                        <label for="master-obat-minimum-stock-kg">Target Minimum Stok (KG)</label>
                        <input type="text" inputmode="decimal" class="form-control" id="master-obat-minimum-stock-kg" name="minimum_stock_kg" placeholder="Kosong = default 500 KG">
                        <small class="form-text text-muted">Kosong memakai target default 500 KG. Nilai 0 mematikan peringatan stok minimum.</small>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($showMinimumStockImport): ?>
<div class="modal fade" id="master-obat-import-modal" tabindex="-1" role="dialog" aria-labelledby="master-obat-import-title" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor); ?> text-white">
                <div><h4 class="modal-title" id="master-obat-import-title">Import Target Minimum KG</h4><small>Preview wajib sebelum perubahan diterapkan</small></div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="master-obat-import-form" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="master-obat-import-file">File Excel</label>
                        <input type="file" class="form-control-file" id="master-obat-import-file" name="excel_file" accept=".xlsx,.xls" required>
                        <small class="form-text text-muted">Kolom wajib: PRODUCT ID dan PENGELUARAN. Tanda - menjadi target 0. Data Tidak Ditemukan akan dibuat sebagai Master Obat baru saat Apply. Maksimal 5 MB / 5.000 baris.</small>
                    </div>
                    <button type="submit" class="btn btn-info" id="master-obat-import-preview"><i class="fas fa-search mr-1"></i> Preview</button>
                </form>
                <div id="master-obat-import-results" class="d-none mt-3">
                    <div id="master-obat-import-summary" class="d-flex flex-wrap mb-2"></div>
                    <div class="table-responsive" style="max-height:55vh">
                        <table class="table table-sm table-bordered table-hover">
                            <thead><tr><th>Baris</th><th>Code Pro-Int</th><th>Nama Excel</th><th>Nama Master</th><th class="text-right">Lama</th><th class="text-right">Baru</th><th>Status</th><th>Alasan</th></tr></thead>
                            <tbody id="master-obat-import-rows"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-success d-none" id="master-obat-import-apply"><i class="fas fa-check mr-1"></i> Terapkan Perubahan</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

</div> 
<!-- /.content-wrapper -->

<?php include '../../../includes/footer.php'; ?>

<!-- DataTables & Plugins -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<!-- Select2 -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>
<!-- SweetAlert2 -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
$(document).ready(function() {
    // PERMISSIONS
    const canEdit = <?= $permissions['CanEdit'] ?>;
    const canDelete = <?= $permissions['CanDelete'] ?>;
    const canAction = (canEdit == 1 || canDelete == 1);

    const normalizeMinimumStockInput = value => {
        if (value === null || value === undefined || value === '') return '';
        const numberValue = Number(value);
        if (!Number.isFinite(numberValue)) return value;
        const normalized = numberValue.toLocaleString('id-ID', { maximumFractionDigits: 4, useGrouping: false });
        return normalized === '-0' ? '0' : normalized;
    };

    let table = $('#tableObat').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": "get_obat_list.php", 
        "order": [], // Disable client-side initial order to let server handle Default (ID DESC)
        "columns": [
            { "data": "kode_obat" },
            { "data": "codeprod_proint" },
            { "data": "nama_obat" },
            { "data": "group_obat" },
            { "data": "uom" },
            {
                "data": "minimum_stock_kg",
                "render": function(data) {
                    return data === null || data === ''
                        ? '<span class="badge badge-secondary">Default 500 KG</span>'
                        : Number(data).toLocaleString('id-ID', { maximumFractionDigits: 4 }) + ' KG';
                }
            },
            { 
                "data": "id",
                "orderable": false,
                "visible": canAction, // Hide column if no permission
                "render": function(data, type, row) {
                    // Escape data to prevent XSS issues in data attributes
                    let id = row.kode_obat ? row.kode_obat.replace(/"/g, '&quot;') : '';
                    let name = row.nama_obat ? row.nama_obat.replace(/"/g, '&quot;') : '';
                    let group = row.group_obat ? row.group_obat.replace(/"/g, '&quot;') : '';
                    let proint = row.codeprod_proint ? row.codeprod_proint.replace(/"/g, '&quot;') : '';
                    let uom = row.uom ? row.uom.replace(/"/g, '&quot;') : '';
                    let minimumStockKg = row.minimum_stock_kg == null ? '' : normalizeMinimumStockInput(row.minimum_stock_kg);
                    let pk = data; // data = "id" column
                    
                    let buttons = '';

                    if (canEdit == 1) {
                        buttons += `
                            <button type="button" class="btn btn-warning btn-sm btn-edit" 
                                data-pk="${pk}"
                                data-id="${id}"
                                data-name="${name}"
                                data-group="${group}"
                                data-proint="${proint}"
                                data-uom="${uom}"
                                data-minimum-stock-kg="${minimumStockKg}">
                                <i class="fas fa-edit"></i>
                            </button>
                        `;
                    }

                    if (canDelete == 1) {
                        buttons += ` <button class="btn btn-danger btn-sm btn-delete" data-id="${pk}"><i class="fas fa-trash"></i></button>`;
                    }

                    return buttons;
                }
            }
        ],
        responsive: true
    });

    // Init Select2 inside Modal
    $('.select2-proint').select2({
        theme: 'bootstrap4',
        dropdownParent: $('#modal-obat'), // Important for Modal
        ajax: {
            url: 'get_proint_items.php',
            dataType: 'json',
            delay: 250,
            data: function(params) { return { q: params.term }; },
            processResults: function(data) { return { results: data.results }; }
        },
        placeholder: 'Cari Produk ProInt...',
        allowClear: true,
        minimumInputLength: 3
    });

    $('#btnAddObat').click(function() {
        $('#obatForm')[0].reset();
        $('#mode').val('add');
        $('#obat_id').val('');
        $('#uom').val('G/L'); // Default
        $('#master-obat-minimum-stock-kg').val('');
        $('#modalTitle').text('Tambah Obat');
        $('#codeprod_proint').val(null).trigger('change');
    });

    // Handle ProInt Selection
    $('.select2-proint').on('select2:select', function(e) {
        let item = e.params.data.item_data;
        if(item) {
             // Auto-fill Group Obat
             $('#group_obat').val(item.structname);
        }
    });

    // Handle Edit Click (Dynamic)
    $(document).on('click', '.btn-edit', function() {
        let pk = $(this).data('pk');
        let id = $(this).data('id');
        let name = $(this).data('name');
        let group = $(this).data('group');
        let proint = $(this).data('proint');
        let uom = $(this).data('uom');
        let minimumStockKg = $(this).attr('data-minimum-stock-kg');
        
        // Reset Form
        $('#obatForm')[0].reset();
        
        // Populate
        $('#mode').val('edit');
        $('#obat_id').val(pk);
        $('#kode_obat').val(id);
        $('#nama_obat').val(name);
        $('#group_obat').val(group);
        $('#uom').val(uom);
        $('#master-obat-minimum-stock-kg').val(minimumStockKg);
        
        // For Select2 ProInt, we need to add the option if it has value
        if (proint) {
            let option = new Option(proint, proint, true, true);
            $('#codeprod_proint').append(option).trigger('change');
        } else {
            $('#codeprod_proint').val(null).trigger('change');
        }
        
        // Update Title and Show Modal
        $('#modalTitle').text('Edit Obat');
        $('#modal-obat').modal('show');
    });
    
    $('#master-obat-minimum-stock-kg').on('input', function() {
        let value = this.value.replace(/[^0-9,.]/g, '');
        const separatorIndex = value.search(/[,.]/);
        if (separatorIndex !== -1) {
            value = value.slice(0, separatorIndex + 1) + value.slice(separatorIndex + 1).replace(/[,.]/g, '');
        }
        this.value = value;
    });

    // Form Submit (Save)
    $('#obatForm').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serializeArray().map(field => {
            if (field.name === 'minimum_stock_kg') field.value = field.value.replace(',', '.');
            return field;
        });
        $.ajax({
            url: 'save_obat.php',
            type: 'POST',
            data: $.param(formData),
            dataType: 'json',
            success: function(resp) {
                if (resp.status === 'success') {
                    $('#modal-obat').modal('hide');
                    Swal.fire('Sukses', 'Data berhasil disimpan', 'success');
                    table.ajax.reload(null, false);
                } else {
                    Swal.fire('Error', resp.message, 'error');
                }
            },
            error: function(xhr) {
                console.log(xhr.responseText);
                Swal.fire('Error', 'Gagal menyimpan data: ' + xhr.statusText, 'error');
            }
        });
    });

    $(document).on('click', '.btn-delete', function() {
        let id = $(this).data('id');
        Swal.fire({
            title: 'Hapus Data?',
            text: "Data yang dihapus tidak dapat dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'delete_obat.php',
                    type: 'POST',
                    data: { id: id },
                    dataType: 'json',
                    success: function(resp) {
                        if (resp.status === 'success') {
                            Swal.fire('Terhapus!', 'Data berhasil dihapus.', 'success');
                            table.ajax.reload(null, false);
                        } else {
                            Swal.fire('Gagal!', resp.message, 'error');
                        }
                    }
                });
            }
        });
    });
    <?php if ($showMinimumStockImport): ?>
    let minimumStockImportToken = '';
    const importStatusLabels = {
        changed: 'Siap Diubah', unchanged: 'Tidak Berubah', not_found: 'Tidak Ditemukan',
        invalid: 'Invalid', duplicate_file: 'Duplikat File', ambiguous_master: 'Master Ambigu'
    };
    const importStatusClasses = {
        changed: 'success', unchanged: 'secondary', not_found: 'danger',
        invalid: 'danger', duplicate_file: 'warning', ambiguous_master: 'warning'
    };
    const formatImportTarget = value => value === null ? 'Default 500' : Number(value).toLocaleString('en-US', { maximumFractionDigits: 4, useGrouping: false });
    const importError = xhr => {
        let response = xhr.responseJSON;
        if (!response && xhr.responseText) {
            try { response = JSON.parse(xhr.responseText); } catch (_) { response = null; }
        }
        const request = response && response.request_id ? ` (Request ID: ${response.request_id})` : '';
        Swal.fire('Import Gagal', (response && response.message ? response.message : 'Respons server tidak valid.') + request, 'error');
    };

    $('#master-obat-import-modal').on('hidden.bs.modal', function() {
        minimumStockImportToken = '';
        $('#master-obat-import-form')[0].reset();
        $('#master-obat-import-results').addClass('d-none');
        $('#master-obat-import-summary,#master-obat-import-rows').empty();
        $('#master-obat-import-apply').addClass('d-none').prop('disabled', false);
    });

    $('#master-obat-import-form').on('submit', function(event) {
        event.preventDefault();
        const button = $('#master-obat-import-preview').prop('disabled', true);
        minimumStockImportToken = '';
        $('#master-obat-import-apply').addClass('d-none');
        $.ajax({
            url: 'import_minimum_stock.php?action=preview&debug=1', type: 'POST',
            data: new FormData(this), processData: false, contentType: false, dataType: 'json'
        }).done(function(response) {
            if (!response || !response.ok) return importError({ responseJSON: response });
            minimumStockImportToken = response.token;
            const summary = response.summary || {};
            $('#master-obat-import-summary').html(Object.keys(importStatusLabels).map(status => {
                const count = Number(summary[status] || 0);
                const exportable = status === 'not_found' && count > 0;
                const tag = exportable ? 'button' : 'span';
                const action = exportable ? ' data-action="master-obat-export-not-found" title="Export Excel-compatible CSV"' : '';
                return `<${tag} type="button" class="badge badge-${importStatusClasses[status]} p-2 mr-2 mb-2 border-0"${action}>${importStatusLabels[status]}: ${count}</${tag}>`;
            }).join(''));
            $('#master-obat-import-rows').html((response.rows || []).map(row => `<tr>
                <td>${Number(row.excel_row)}</td><td><code>${$('<div>').text(row.code || '-').html()}</code></td>
                <td>${$('<div>').text(row.excel_name || '-').html()}</td><td>${$('<div>').text(row.master_name || '-').html()}</td>
                <td class="text-right">${formatImportTarget(row.old_target)}</td><td class="text-right">${formatImportTarget(row.new_target)}</td>
                <td><span class="badge badge-${importStatusClasses[row.status] || 'secondary'}">${importStatusLabels[row.status] || row.status}</span></td>
                <td>${$('<div>').text(row.reason || '-').html()}</td></tr>`).join(''));
            $('#master-obat-import-results').removeClass('d-none');
            $('#master-obat-import-apply').toggleClass('d-none', Number(summary.changed || 0) === 0);
        }).fail(importError).always(() => button.prop('disabled', false));
    });

    $('#master-obat-import-summary').on('click', '[data-action="master-obat-export-not-found"]', function() {
        if (!minimumStockImportToken) return;
        const link = document.createElement('a');
        link.href = `import_minimum_stock.php?action=export_not_found&debug=1&token=${encodeURIComponent(minimumStockImportToken)}`;
        document.body.appendChild(link);
        link.click();
        link.remove();
    });

    $('#master-obat-import-apply').on('click', function() {
        if (!minimumStockImportToken) return;
        Swal.fire({
            title: 'Terapkan perubahan?', text: 'Baris Siap Diubah akan update target. Baris Tidak Ditemukan akan dibuat sebagai Master Obat baru.',
            icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, Terapkan', cancelButtonText: 'Batal'
        }).then(result => {
            if (!result.isConfirmed) return;
            const button = $('#master-obat-import-apply').prop('disabled', true);
            $.ajax({
                url: 'import_minimum_stock.php?action=apply&debug=1', type: 'POST', dataType: 'json',
                data: { token: minimumStockImportToken }
            }).done(function(response) {
                if (!response || !response.ok) return importError({ responseJSON: response });
                minimumStockImportToken = '';
                $('#master-obat-import-modal').modal('hide');
                table.ajax.reload(null, false);
                Swal.fire('Sukses', response.message, 'success');
            }).fail(importError).always(() => button.prop('disabled', false));
        });
    });
    <?php endif; ?>
});
</script>
