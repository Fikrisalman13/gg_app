<?php
// pages/resep_obat/master_obat/list_obat.php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../koneksi.php';

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

include '../../../../includes/header.php';
include '../../../../includes/sidebar.php';
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
                                <th>Kode Obat</th>
                                <th>Code Pro-Int</th>
                                <th>Nama Obat</th>
                                <th>Group</th>
                                <th>UOM</th>
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
                        <label>Kode Obat</label>
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
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

</div> 
<!-- /.content-wrapper -->

<?php include '../../../../includes/footer.php'; ?>

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
                                data-uom="${uom}">
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
        
        // Reset Form
        $('#obatForm')[0].reset();
        
        // Populate
        $('#mode').val('edit');
        $('#obat_id').val(pk);
        $('#kode_obat').val(id);
        $('#nama_obat').val(name);
        $('#group_obat').val(group);
        $('#uom').val(uom);
        
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
    
    // Form Submit (Save)
    $('#obatForm').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'save_obat.php',
            type: 'POST',
            data: $(this).serialize(),
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
});
</script>
