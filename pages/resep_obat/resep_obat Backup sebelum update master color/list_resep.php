<?php
// pages/resep_obat/list_resep.php
session_start();

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

/// helper: ambil permission user untuk menu Asset (MenuId = 215)
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
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 215);
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1 class="m-0">Resep Obat</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Resep Obat</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor); ?> text-white">
                    <h3 class="card-title"><i class="fas fa-flask mr-1"></i> Daftar Resep Obat</h3>
                    <div class="float-right">
                        <?php if ($permissions['CanAdd'] == 1): ?>
                        <a href="input_resep.php" class="btn btn-success btn-sm">
                            <i class="fas fa-plus"></i> Tambah Resep
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table id="resepTable" class="table table-bordered table-striped table-hover table-sm nowrap" style="width:100%;">
                            <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>No CP</th>
                                    <th>Kode Warna</th>
                                    <th>Lot No</th>
                                    <th>Weight</th>
                                    <th>Plan Qty</th>
                                    <th>Created At</th>
                                    <th>Created By</th>
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

<?php include '../../includes/footer.php'; ?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<!-- DataTables JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<!-- SweetAlert -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
$(document).ready(function() {
    // Pass Permissions to JS
    const canEdit = <?= $permissions['CanEdit'] ?>;
    const canDelete = <?= $permissions['CanDelete'] ?>;

    var table = $('#resepTable').DataTable({
        responsive: true,
        processing: true,
        serverSide: true,
        ajax: {
            url: 'serverside_resep.php',
            type: 'POST',
            error: function(xhr, error, thrown) {
                console.error('DataTables error:', xhr.responseText);
                Swal.fire('Error', 'Gagal memuat data. Cek console.', 'error');
            }
        },
        columns: [
            { 
                data: null, 
                orderable: false, 
                render: function (data, type, row, meta) {
                    return meta.row + meta.settings._iDisplayStart + 1;
                }
            },
            { data: 'no_cp' },
            { data: 'kode_warna' },
            { data: 'lot_no' },
            { data: 'weight' },
            { data: 'plan_qty' },
            { data: 'created_at' },
            { data: 'created_by' },
            { 
                data: 'id',
                orderable: false,
                render: function(data, type, row) {
                    let buttons = '';
                    
                    // View is always accessible if they can see the page, but let's keep it
                    buttons += `<a href="view_resep.php?resep_id=${data}" class="btn btn-info btn-sm" title="View"><i class="fas fa-eye"></i></a>`;
                    
                    if (canEdit == 1) {
                        buttons += ` <a href="input_resep.php?resep_id=${data}" class="btn btn-warning btn-sm" title="Edit"><i class="fas fa-edit"></i></a>`;
                    }
                    
                    if (canDelete == 1) {
                        buttons += ` <button class="btn btn-danger btn-sm btn-delete" data-id="${data}" title="Hapus"><i class="fas fa-trash"></i></button>`;
                    }
                    
                    return buttons;
                }
            }
        ],
        order: [[6, 'desc']]
    });

    $(document).on('click', '.btn-delete', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Hapus Resep?',
            text: "Data tidak bisa dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'delete_resep.php',
                    type: 'POST',
                    data: { id: id },
                    dataType: 'json',
                    success: function(resp) {
                        if (resp.status === 'success') {
                            Swal.fire('Terhapus!', 'Data berhasil dihapus.', 'success');
                            table.ajax.reload();
                        } else {
                            Swal.fire('Gagal!', resp.message, 'error');
                        }
                    },
                    error: function(xhr) {
                        Swal.fire('Error', 'Gagal request hapus', 'error');
                    }
                });
            }
        });
    });
});
</script>
