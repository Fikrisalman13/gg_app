<?php
// m_padder_spec.php - Manajemen Spesifikasi Padder
session_start();
ob_start();

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';

date_default_timezone_set('Asia/Jakarta');

// ====== Auth & Permission ======
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// helper: ambil permission user untuk menu Padder Spec
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

// Ambil permission (sesuaikan MenuId dengan menu Padder Spec di sistem Anda)
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 111); // Contoh MenuId = 58 untuk Padder Spec
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}

// ====== Get Padder List for Dropdown ======
function getPadderList($conn) {
    $sql = "SELECT padder_id, padder_name 
            FROM dbo.pad_m_padder 
            ORDER BY padder_id";
    $stmt = sqlsrv_query($conn, $sql);
    
    $padders = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $padders[] = $row;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    
    return $padders;
}

$padderList = getPadderList($conn);

// Include layout parts (header/sidebar)
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Spesifikasi Padder</title>

    <!-- CSS (AdminLTE + DataTables minimal) -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">

    <style>
        .badge-spec { font-size: .9em; padding: 5px 8px; }
        #table-spinner {
            display: none;
            margin-bottom: 8px;
            text-align: center;
        }
        #table-spinner .spinner-border { width: 1rem; height: 1rem; vertical-align: middle; }
        
        /* Button spacing in action column */
        .btn-action {
            margin: 1px;
        }
        .filter-box {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
        }
        .spec-card {
            border-left: 4px solid #007bff;
        }
    </style>
</head>
<body>
<div class="content-wrapper">
    <!-- header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1 class="m-0">Spesifikasi Padder</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="m_padder.php">Padder</a></li>
                        <li class="breadcrumb-item active">Spesifikasi</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- content -->
    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                    <h3 class="card-title">Daftar Spesifikasi Padder</h3>
                    <?php if (!empty($permissions['CanAdd']) && $permissions['CanAdd'] == 1): ?>
                        <a href="add_padder_spec.php" class="btn btn-success btn-sm float-right"><i class="fas fa-plus"></i> Tambah Spesifikasi</a>
                    <?php endif; ?>
                </div>

                <div class="card-body">
                    <!-- Filter -->
                    <div class="filter-box">
                        <form id="filterForm" class="form-inline">
                            <div class="form-group mr-3">
                                <label for="padder_id" class="mr-2">Pilih Padder:</label>
                                <select id="padder_id" name="padder_id" class="form-control form-control-sm">
                                    <option value="">Semua Padder</option>
                                    <?php foreach ($padderList as $padder): ?>
                                        <option value="<?= htmlspecialchars($padder['padder_id']) ?>">
                                            <?= htmlspecialchars($padder['padder_id']) ?> - <?= htmlspecialchars($padder['padder_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="button" id="btnFilter" class="btn btn-primary btn-sm mr-2"><i class="fas fa-filter"></i> Filter</button>
                            <button type="button" id="btnReset" class="btn btn-secondary btn-sm"><i class="fas fa-sync-alt"></i> Reset</button>
                        </form>
                    </div>

                    <!-- spinner kecil -->
                    <div id="table-spinner">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                        <small class="text-muted ml-2">Memuat data...</small>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table id="padderSpecTable" class="table table-hover table-sm" style="width:100%;">
                            <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>Padder ID</th>
                                    <th>Nama Padder</th>
                                    <th>Nama Spesifikasi</th>
                                    <th>Nilai Spesifikasi</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div> <!-- /.table-responsive -->
                </div> <!-- /.card-body -->
            </div> <!-- /.card -->
        </div>
    </div>
</div>

<!-- Modal View Spec -->
<div class="modal fade" id="viewSpecModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Detail Spesifikasi</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><strong>Padder ID:</strong></label>
                            <p id="viewPadderId" class="form-control-plaintext"></p>
                        </div>
                        <div class="form-group">
                            <label><strong>Nama Padder:</strong></label>
                            <p id="viewPadderName" class="form-control-plaintext"></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><strong>Nama Spesifikasi:</strong></label>
                            <p id="viewSpecName" class="form-control-plaintext"></p>
                        </div>
                        <div class="form-group">
                            <label><strong>Nilai Spesifikasi:</strong></label>
                            <p id="viewSpecValue" class="form-control-plaintext"></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPTS -->
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/js/datatables/dataTables.bootstrap5.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
(function(){
    // Permission fallback
    window.appPermissions = {
        canEdit: <?php echo (!empty($permissions['CanEdit']) && $permissions['CanEdit'] == 1) ? 'true' : 'false'; ?>,
        canDelete: <?php echo (!empty($permissions['CanDelete']) && $permissions['CanDelete'] == 1) ? 'true' : 'false'; ?>
    };

    var $spinner = $('#table-spinner');
    var viewModal = $('#viewSpecModal');

    var table = $('#padderSpecTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'padder_spec_serverside.php',
            type: 'POST',
            data: function(d) {
                d.padder_id = $('#padder_id').val();
                d.search_value = d.search.value;
            },
            beforeSend: function() {
                $spinner.show();
            },
            complete: function() {
                $spinner.hide();
            },
            error: function(xhr, status, error) {
                $spinner.hide();
                console.error('AJAX error', status, error);
                Swal.fire({
                    icon: 'error',
                    title: 'Terjadi kesalahan',
                    text: 'Gagal memuat data. Periksa konsol untuk detail.',
                    timer: 3500,
                    showConfirmButton: false
                });
            }
        },
        columns: [
            { 
                data: null, 
                orderable: false, 
                searchable: false, 
                render: function(data, type, row, meta) {
                    return meta.row + 1 + meta.settings._iDisplayStart;
                }
            },
            { data: 'padder_id' },
            { data: 'padder_name' },
            { data: 'spec_name' },
            { 
                data: 'spec_value',
                render: function(data, type, row) {
                    // Truncate long values for table view
                    if (data && data.length > 50) {
                        return data.substring(0, 50) + '...';
                    }
                    return data || '-';
                }
            },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: function(id, type, row) {
                    var canEdit = window.appPermissions.canEdit;
                    var canDelete = window.appPermissions.canDelete;

                    var html = '<div class="btn-group">';
                   
                    
                    if (canEdit) {
                        html += '<a href="edit_padder_spec.php?id=' + id + '" class="btn btn-warning btn-sm btn-action" title="Edit"><i class="fas fa-edit"></i></a>';
                    }
                    if (canDelete) {
                        html += '<button class="btn btn-danger btn-sm btn-action btn-delete" data-id="' + id + '" title="Hapus"><i class="fas fa-trash"></i></button>';
                    }
                    html += '</div>';
                    return html;
                }
            }
        ],
        responsive: true,
        autoWidth: false,
        lengthMenu: [[10,25,50],[10,25,50]],
        language: {
            search: "Cari:",
            lengthMenu: "Tampil _MENU_ data per halaman",
            info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
            infoFiltered: "(disaring dari _MAX_ total data)",
            zeroRecords: "Tidak ada data yang ditemukan",
            paginate: {
                first: "Pertama",
                last: "Terakhir",
                next: "Berikutnya",
                previous: "Sebelumnya"
            }
        }
    });

    // Filter buttons
    $('#btnFilter').on('click', function(){
        table.ajax.reload();
    });
    
    $('#btnReset').on('click', function(){
        $('#padder_id').val('');
        table.ajax.reload();
    });

   
    // Delete handler
    $(document).on('click', '.btn-delete', function(){
        var id = $(this).data('id');
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: 'Spesifikasi padder akan dihapus secara permanen!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then(function(result){
            if (result.isConfirmed) {
                window.location.href = 'delete_padder_spec.php?id=' + id;
            }
        });
    });

    // Show notification if any
    <?php if (isset($_SESSION['success'])): ?>
    Swal.fire({ 
        icon: 'success', 
        title: 'Sukses!', 
        text: <?= json_encode($_SESSION['success']) ?>, 
        timer: 3000, 
        showConfirmButton: false 
    });
    <?php unset($_SESSION['success']); endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    Swal.fire({ 
        icon: 'error', 
        title: 'Gagal!', 
        text: <?= json_encode($_SESSION['error']) ?>, 
        timer: 3000, 
        showConfirmButton: false 
    });
    <?php unset($_SESSION['error']); endif; ?>

})();
</script>

</body>
</html>

<?php include '../../includes/footer.php'; ?>