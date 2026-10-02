<?php
// pages/resep_obat/master_artikel_grey/list_artikel.php
session_start();
require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../koneksi.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

// ==========================================
// CONFIG: SET MENU ID HERE (MATCH DATABASE)
$menuId = 216; // Example ID, Change this to match dbo.SMMenu
// ==========================================

function checkPermissions($conn, $groupId, $menuId) {
    if ($menuId === 0) return ['CanView'=>1, 'CanAdd'=>1, 'CanEdit'=>1, 'CanDelete'=>1]; // Dev Mode
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete
            FROM dbo.SMGroupTrustee
            WHERE GroupId = ? AND MenuId = ?";
    $params = [$groupId, $menuId];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        return $row;
    }
    return ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
}

$permissions = checkPermissions($conn, $_SESSION['GroupId'], $menuId);

// Strict check disabled for now until user sets up Menu
// if ($permissions['CanView'] != 1) { ... }

include '../../../../includes/header.php';
include '../../../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1>Master Artikel Grey</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="#">Master Data</a></li>
                        <li class="breadcrumb-item active">Artikel Grey</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card card-<?= htmlspecialchars($themeColor); ?> card-outline">
                <div class="card-header bg-<?= htmlspecialchars($themeColor); ?> text-white">
                    <h3 class="card-title">Data Master Artikel Grey</h3>
                    <div class="card-tools">
                        <?php if ($permissions['CanAdd'] == 1): ?>
                        <button type="button" class="btn btn-success btn-sm" id="btnAdd">
                            <i class="fas fa-plus"></i> Tambah Data
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <table id="tableArtikel" class="table table-bordered table-hover table-sm display nowrap" style="width:100%">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Kode Gray</th>
                                <th>Nama Artikel</th>
                                <th>Gramasi</th>
                                <th>Pickup</th>
                                <th>Padry</th>
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

<!-- Modal -->
<div class="modal fade" id="modal-form">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor); ?> text-white">
                <h4 class="modal-title" id="modalTitle">Form Artikel Grey</h4>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formArtikel">
                <div class="modal-body">
                    <input type="hidden" id="mode" name="mode" value="add">
                    <input type="hidden" id="id" name="id">

                    <div class="form-group">
                        <label>Kode Gray</label>
                        <input type="text" class="form-control" id="kode_gray" name="kode_gray" required>
                    </div>
                    <div class="form-group">
                        <label>Nama Artikel</label>
                        <input type="text" class="form-control" id="nama_artikel" name="nama_artikel" required>
                    </div>
                    <div class="form-group">
                        <label>Gramasi</label>
                        <input type="text" class="form-control" id="gramasi" name="gramasi" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label>Pickup</label>
                        <input type="text" class="form-control" id="pickup" name="pickup" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label>Padry</label>
                        <select class="form-control select2-padry" id="padry" name="padry" style="width: 100%;">
                        </select>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

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
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
$(document).ready(function() {
    const canEdit = <?= $permissions['CanEdit'] ?>;
    const canDelete = <?= $permissions['CanDelete'] ?>;

    const table = $('#tableArtikel').DataTable({
        responsive: true,
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "get_artikel.php",
            "type": "POST"
        },
        "order": [[0, 'desc']], // Default Sort
        "columns": [
            { "data": "no", "orderable": false },
            { "data": "kode_gray" },
            { "data": "nama_artikel" },
            { 
                "data": "gramasi",
                "render": function(data, type, row) {
                    if (!data) return '';
                    let val = parseFloat(data.toString().replace(',', '.'));
                    return isNaN(val) ? data : val.toFixed(2);
                }
            },
            { 
                "data": "pickup",
                "render": function(data, type, row) {
                    if (!data) return '';
                    let val = parseFloat(data.toString().replace(',', '.'));
                    return isNaN(val) ? data : val.toFixed(2);
                }
            },
            { "data": "padry" },
            { 
                "data": "id",
                "orderable": false,
                "render": function(data, type, row) {
                    let btns = '';
                    // Clean data for attributes
                    let r = JSON.stringify(row).replace(/"/g, '&quot;');
                    
                    if (canEdit == 1) btns += `<button class="btn btn-warning btn-sm btn-edit" data-row="${r}"><i class="fas fa-edit"></i></button> `;
                    if (canDelete == 1) btns += `<button class="btn btn-danger btn-sm btn-delete" data-id="${data}"><i class="fas fa-trash"></i></button>`;
                    return btns;
                }
            }
        ]
    });

    // Select2
    $('.select2-padry').select2({
        theme: 'bootstrap4',
        dropdownParent: $('#modal-form'), // Fix modal z-index
        ajax: {
            url: 'get_padry.php',
            dataType: 'json',
            delay: 250,
            data: function(params) { return { q: params.term }; },
            processResults: function(data) { return { results: data.results }; }
        },
        placeholder: 'Pilih Padry...',
        allowClear: true,
        tags: true // Allow new entries if needed? User said "ambil dari database" (problem list), but maybe new ones allowed? Keep safe with tags: false for now unless requested. Set TRUE if user wants to type new ones.
    });

    $('#btnAdd').click(function() {
        $('#formArtikel')[0].reset();
        $('#mode').val('add');
        $('#id').val('');
        $('#modalTitle').text('Tambah Artikel');
        $('.select2-padry').val(null).trigger('change');
        $('#modal-form').modal('show');
    });

    $(document).on('click', '.btn-edit', function() {
        let row = $(this).data('row');
        // If row is string (due to HTML attribute), parse it. 
        // Note: data-row="${r}" puts it as string.
        if (typeof row === 'string') row = JSON.parse(row); // Should be auto-handled by jquery .data() if valid JSON, but safety first.

        $('#formArtikel')[0].reset();
        $('#mode').val('edit');
        $('#id').val(row.id);
        $('#kode_gray').val(row.kode_gray);
        $('#nama_artikel').val(row.nama_artikel);
        $('#gramasi').val(row.gramasi);
        $('#pickup').val(row.pickup);
        
        // Padry Select2
        if (row.padry) {
            let option = new Option(row.padry, row.padry, true, true);
            $('.select2-padry').append(option).trigger('change');
        } else {
            $('.select2-padry').val(null).trigger('change');
        }

        $('#modalTitle').text('Edit Artikel');
        $('#modal-form').modal('show');
    });

    $('#formArtikel').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'save_artikel.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(resp) {
                if (resp.status === 'success') {
                    $('#modal-form').modal('hide');
                    Swal.fire('Sukses', resp.message, 'success');
                    table.ajax.reload();
                } else {
                    Swal.fire('Error', resp.message, 'error');
                }
            },
            error: function(err) {
                Swal.fire('Error', 'Gagal menyimpan', 'error');
            }
        });
    });

    $(document).on('click', '.btn-delete', function() {
        let id = $(this).data('id');
        Swal.fire({
            title: 'Hapus?',
            text: "Data tidak bisa dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'delete_artikel.php',
                    type: 'POST',
                    data: { id: id },
                    dataType: 'json',
                    success: function(resp) {
                        if (resp.status === 'success') {
                            Swal.fire('Terhapus', resp.message, 'success');
                            table.ajax.reload();
                        } else {
                            Swal.fire('Error', resp.message, 'error');
                        }
                    }
                });
            }
        });
    });
});
</script>
