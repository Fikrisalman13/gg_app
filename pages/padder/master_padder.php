<?php
// m_padder.php - Manajemen Padder
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

// helper: ambil permission user untuk menu Padder (MenuId = ? - sesuaikan dengan menu ID yang sesuai)
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

// Ambil permission (sesuaikan MenuId dengan menu Padder di sistem Anda)
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 111); // Contoh MenuId = 111 untuk Padder
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}

// Include layout parts (header/sidebar)
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Padder</title>

    <!-- CSS (AdminLTE + DataTables minimal) -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">

    <style>
        .badge-padder { font-size: .9em; padding: 5px 8px; }
        #table-spinner {
            display: none;
            margin-bottom: 8px;
            text-align: center;
        }
        #table-spinner .spinner-border { width: 1rem; height: 1rem; vertical-align: middle; }
        
        /* Status badge colors */
        .badge-ready { background-color: #28a745; }
        .badge-in_use { background-color: #007bff; }
        .badge-maintenance { background-color: #ffc107; color: #212529; }
        .badge-repair_vendor { background-color: #fd7e14; }
        .badge-repaired { background-color: #17a2b8; }
        .badge-scrap { background-color: #dc3545; }
        .badge-dibeli { background-color: #6c757d; }
        
        /* Button spacing in action column */
        .btn-action {
            margin: 1px;
        }
    </style>
</head>
<body>
<div class="content-wrapper">
    <!-- header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1 class="m-0">Master Padder</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Padder</li>
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
                    <h3 class="card-title">Daftar Padder</h3>
                    <?php if (!empty($permissions['CanAdd']) && $permissions['CanAdd'] == 1): ?>
                        <a href="add_padder.php" class="btn btn-success btn-sm float-right"><i class="fas fa-plus"></i> Tambah Padder</a>
                    <?php endif; ?>
                </div>

                <div class="card-body">
                    <!-- spinner kecil -->
                    <div id="table-spinner">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                        <small class="text-muted ml-2">Memuat data...</small>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table id="padderTable" class="table table-hover table-sm" style="width:100%;">
                            <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>Padder ID</th>
                                    <th>Nama Padder</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                    <th>Created At</th>
                                    <th>Created By</th>
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

    var table = $('#padderTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'padder_serverside.php',
            type: 'POST',
            data: function(d) {
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
            { 
                data: 'status', 
                render: function(data, type, row) {
                    var badgeClass = 'badge-' + data.toLowerCase().replace(' ', '_');
                    return '<span class="badge ' + badgeClass + '">' + data + '</span>';
                } 
            },
            { data: 'remarks' },
            { 
                data: 'created_at', 
                render: function(data) {
                    if (data) {
                        var date = new Date(data);
                        return date.toLocaleDateString('id-ID') + ' ' + date.toLocaleTimeString('id-ID');
                    }
                    return '-';
                }
            },
            { data: 'created_by' },
            {
                data: 'aksi',
                orderable: false,
                searchable: false,
                render: function(aksi, type, row) {
                    var id, canEdit, canDelete;
                    if (aksi && typeof aksi === 'object') {
                        id = aksi.id;
                        canEdit = aksi.can_edit == 1 || aksi.can_edit === true;
                        canDelete = aksi.can_delete == 1 || aksi.can_delete === true;
                    } else {
                        id = aksi;
                        canEdit = window.appPermissions.canEdit;
                        canDelete = window.appPermissions.canDelete;
                    }

                    var html = '<div class="btn-group">';
                    html += '<a href="view_padder.php?id=' + encodeURIComponent(id) + '" class="btn btn-info btn-sm btn-action" title="View"><i class="fas fa-eye"></i></a>';
                    
                    // QR Code Button - always visible if user can view
                    html += '<a href="generate_single_qrcode.php?id=' + encodeURIComponent(id) + '" class="btn btn-success btn-sm btn-action" title="Generate QR Code" target="_blank"><i class="fas fa-qrcode"></i></a>';
                    
                    if (canEdit) {
                        html += '<a href="edit_padder.php?id=' + encodeURIComponent(id) + '" class="btn btn-warning btn-sm btn-action" title="Edit"><i class="fas fa-edit"></i></a>';
                    }
                    if (canDelete) {
                        html += '<button class="btn btn-danger btn-sm btn-action btn-delete" data-id="' + encodeURIComponent(id) + '" title="Hapus"><i class="fas fa-trash"></i></button>';
                    }
                    html += '</div>';
                    return html;
                }
            }
        ],
        responsive: true,
        autoWidth: false,
        lengthMenu: [[10,25,50],[10,25,50]]
    });

    // Delete handler
    $(document).on('click', '.btn-delete', function(){
        var id = $(this).data('id');
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: 'Padder akan dihapus secara permanen!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then(function(result){
            if (result.isConfirmed) {
                window.location.href = 'delete_padder.php?id=' + encodeURIComponent(id);
            }
        });
    });

    // Show notification
    <?php if (isset($_SESSION['success'])): ?>
    Swal.fire({ icon: 'success', title: 'Sukses!', text: <?= json_encode($_SESSION['success']) ?>, timer:3000, showConfirmButton:false });
    <?php unset($_SESSION['success']); endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    Swal.fire({ icon: 'error', title: 'Gagal!', text: <?= json_encode($_SESSION['error']) ?>, timer:3000, showConfirmButton:false });
    <?php unset($_SESSION['error']); endif; ?>

})();
</script>

</body>
</html>

<?php include '../../includes/footer.php'; ?>