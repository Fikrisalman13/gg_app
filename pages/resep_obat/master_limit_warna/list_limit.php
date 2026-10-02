<?php
include '../../../koneksi.php';
include '../../../koneksi3.php';
// Helper Permissions
function checkPermissions($conn, $groupId, $menuId) {
    if (!$conn) return ['CanView' => 1, 'CanAdd' => 1, 'CanEdit' => 1, 'CanDelete' => 1]; // Fallback if no connection logic
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete
            FROM dbo.SMGroupTrustee
            WHERE GroupId = ? AND MenuId = ?";
    $params = [$groupId, $menuId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    $permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    return $permissions;
}

// Ensure Session
if (session_status() == PHP_SESSION_NONE) session_start();
$groupId = $_SESSION['GroupId'] ?? 0;
// Using MenuId 213 (Similar to Obat)
$permissions = checkPermissions($conn, $groupId, 213);

include '../../../includes/header.php';
include '../../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1>Master Limit Color Code</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="#">Master Data</a></li>
                        <li class="breadcrumb-item active">Limit Warna</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card card-<?= htmlspecialchars($themeColor); ?> card-outline">
                <div class="card-header bg-<?= htmlspecialchars($themeColor); ?> text-white">
                    <h3 class="card-title"><i class="fas fa-palette mr-1"></i> Data Limitasi Warna</h3>
                    <div class="card-tools">
                        <?php if ($permissions['CanAdd'] == 1): ?>
                        <button type="button" class="btn btn-success btn-sm" id="btnImport" data-toggle="modal" data-target="#modal-import-limit">
                            <i class="fas fa-file-excel text-white"></i> <span class="text-white">Import Excel</span>
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" id="btnAdd" data-toggle="modal" data-target="#modal-limit">
                            <i class="fas fa-plus text-white"></i> <span class="text-white">Tambah Data</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                    <table id="tableLimit" class="table table-bordered table-hover table-sm nowrap" style="width:100%">
                        <thead class="bg-gray-light">
                            <tr>
                                <th>Kode Warna</th>
                                <th>Max Cost</th>
                                <th>Max CF Disperse</th>
                                <th>Max CF Reactive</th>
                                <th>Max CF Total</th>
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
</div>

<!-- Includes for Modal will go here, loading via AJAX or specific File -->
<?php include 'modal_form_limit.php'; ?>
<?php include 'modal_import_limit.php'; ?>

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

    var table = $('#tableLimit').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": "get_limit_list.php",
        "order": [[ 5, "desc" ]], // Order by Last Updated DESC
        "columns": [
            { "data": "kode_warna" },
            { 
                "data": "max_cost",
                "render": function(data, type, row) {
                    if (type === 'display') {
                        if (data == 0 || data == null || data == '-') {
                            return '<span class="text-muted">-</span>';
                        }
                        return 'Rp ' + parseFloat(data).toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    }
                    return data;
                }
            },
            { 
                "data": "max_cf_disperse",
                "render": function(data, type, row) {
                    if (type === 'display') {
                        if (data == 0 || data == null || data == '-' || data === '') {
                            return '<span class="text-muted">-</span>';
                        }
                        return parseFloat(data).toFixed(2);
                    }
                    return data;
                }
            },
            { 
                "data": "max_cf_reactive",
                "render": function(data, type, row) {
                    if (type === 'display') {
                        if (data == 0 || data == null || data == '-' || data === '') {
                            return '<span class="text-muted">-</span>';
                        }
                        return parseFloat(data).toFixed(2);
                    }
                    return data;
                }
            },
            { 
                "data": "max_cf_total",
                "render": function(data, type, row) {
                    if (type === 'display') {
                        if (data == 0 || data == null || data == '-' || data === '') {
                            return '<span class="text-muted">-</span>';
                        }
                        return parseFloat(data).toFixed(2);
                    }
                    return data;
                }
            },
            {
                "data": "id",
                "orderable": false,
                "visible": canAction,
                "render": function(data, type, row) {
                    let buttons = '';
                    if (canEdit == 1) {
                        // Data attributes for Edit
                        let dataStr = JSON.stringify(row).replace(/"/g, '&quot;');
                        buttons += `<button class="btn btn-warning btn-sm btn-edit" data-row="${dataStr}"><i class="fas fa-edit"></i></button>`;
                    }
                    if (canDelete == 1) {
                        buttons += ` <button class="btn btn-danger btn-sm btn-delete" data-id="${data}"><i class="fas fa-trash"></i></button>`;
                    }
                    return buttons;
                }
            }
        ],
        responsive: true
    });

    // Handle Edit Button (Populates Modal)
    $('#tableLimit tbody').on('click', '.btn-edit', function() {
        let row = $(this).data('row');
        $('#mode').val('edit');
        $('#limit_id').val(row.id);
        
        // Disable Code Selection on Edit? Or allow change? usually PK logical is fixed.
        // Assuming kode_warna is key logic, usually readable but maybe let's allow re-select or lock.
        // Let's Populate Select2
        let newOption = new Option(row.kode_warna, row.kode_warna, true, true);
        $('#kode_warna').append(newOption).trigger('change');
        
        // Format max_cost for display
        let maxCostValue = row.max_cost;
        if (maxCostValue === null || maxCostValue === '' || maxCostValue === '-') {
            $('#max_cost').val('-');
        } else {
            $('#max_cost').val(formatCurrency(maxCostValue.toString()));
        }
        
        $('#max_cf_disperse').val(row.max_cf_disperse);
        $('#max_cf_reactive').val(row.max_cf_reactive);
        $('#max_cf_total').val(row.max_cf_total);
        
        $('#modalTitle').text('Edit Limit Warna');
        $('#modal-limit').modal('show');
    });

    // Reset Form on Add
    $('#btnAdd').click(function() {
        $('#formLimit')[0].reset();
        $('#mode').val('add');
        $('#limit_id').val('');
        $('#kode_warna').val(null).trigger('change');
        $('#modalTitle').text('Tambah Limit Warna');
    });

    // Init Select2 for Color in Modal
    $('.select2-color').select2({
        theme: 'bootstrap4',
        dropdownParent: $('#modal-limit'),
        placeholder: 'Cari Kode Warna...',
        allowClear: true,
        minimumInputLength: 2,
        ajax: {
            url: '../get_colors.php',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return { q: params.term };
            },
            processResults: function(data) {
                console.log('Select2 Response:', data);
                return {
                    results: data.results
                };
            },
            error: function(xhr, status, error) {
                console.error('Select2 AJAX Error:', error, xhr.responseText);
            },
            cache: true
        }
    });
    
    // Currency Formatting Function
    function formatCurrency(value) {
        // Remove non-numeric except dash
        if (value === '-') return '-';
        
        // Remove all non-numeric characters
        let number = value.replace(/[^0-9]/g, '');
        
        if (number === '') return '';
        
        // Format with thousand separator (dot)
        return number.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    
    // Apply formatting on keyup
    $(document).on('keyup', '.currency-format', function() {
        let val = $(this).val();
        if (val === '-') return; // Allow dash for unlimited
        
        let formatted = formatCurrency(val);
        $(this).val(formatted);
    });

    // Handle Form Submit
    $('#formLimit').submit(function(e) {
        e.preventDefault();
        
        if (!$('#kode_warna').val()) {
            Swal.fire('Warning', 'Pilih Kode Warna terlebih dahulu.', 'warning');
            return;
        }

        $.ajax({
            url: 'save_limit.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(resp) {
                if(resp.status === 'success') {
                    Swal.fire('Berhasil', resp.message, 'success');
                    $('#modal-limit').modal('hide');
                    $('#tableLimit').DataTable().ajax.reload();
                } else {
                    Swal.fire('Gagal', resp.message, 'error');
                }
            },
            error: function(xhr) {
                Swal.fire('Error', 'Terjadi kesalahan sistem.', 'error');
                console.error(xhr.responseText);
            }
        });
    });
    
    // Delete Handler
    $(document).on('click', '.btn-delete', function() {
        let id = $(this).data('id');
        
        Swal.fire({
            title: 'Hapus Data?',
            text: 'Data limit warna ini akan dihapus permanen!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'delete_limit.php',
                    type: 'POST',
                    data: { id: id },
                    dataType: 'json',
                    success: function(resp) {
                        if(resp.status === 'success') {
                            Swal.fire('Terhapus!', resp.message, 'success');
                            $('#tableLimit').DataTable().ajax.reload();
                        } else {
                            Swal.fire('Gagal', resp.message, 'error');
                        }
                    },
                    error: function(xhr) {
                        Swal.fire('Error', 'Terjadi kesalahan sistem.', 'error');
                        console.error(xhr.responseText);
                    }
                });
            }
        });
    });
});
</script>
