<?php
// org_units.php - Management for Organizational Units
session_start();
ob_start();

require_once __DIR__ . '/../../koneksi.php';

date_default_timezone_set('Asia/Jakarta');

// ====== Auth ======
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// ====== Fetch Units ======
$sql = "SELECT * FROM fin_org_units ORDER BY UnitName ASC";
$stmt = sqlsrv_query($conn, $sql);
$units = [];
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $units[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

// Theme color
$themeColor = $_SESSION['Theme'] ?? 'primary';

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1 class="m-0 text-dark">Master Unit Organisasi</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                        <li class="breadcrumb-item active">Finance Settings</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card shadow-sm">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white d-flex align-items-center">
                    <h3 class="card-title mr-auto"><i class="fas fa-sitemap mr-2"></i>Daftar Unit</h3>
                    <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#modalAddUnit">
                        <i class="fas fa-plus mr-1"></i> Tambah Unit
                    </button>
                </div>
                <div class="card-body">
                    <table id="unitTable" class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th width="50">ID</th>
                                <th>Nama Unit</th>
                                <th>Deskripsi</th>
                                <th width="100">Status</th>
                                <th width="120">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($units as $u): ?>
                            <tr>
                                <td data-order="<?= $u['UnitId'] ?>"><?= $u['UnitId'] ?></td>
                                <td><strong><?= htmlspecialchars($u['UnitName']) ?></strong></td>
                                <td><?= htmlspecialchars($u['Description'] ?? '-') ?></td>
                                <td>
                                    <span class="badge badge-<?= $u['IsActive'] ? 'success' : 'danger' ?>">
                                        <?= $u['IsActive'] ? 'Aktif' : 'Non-Aktif' ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-info btn-xs btn-edit" 
                                            data-id="<?= $u['UnitId'] ?>" 
                                            data-name="<?= htmlspecialchars($u['UnitName']) ?>"
                                            data-desc="<?= htmlspecialchars($u['Description'] ?? '') ?>"
                                            data-active="<?= $u['IsActive'] ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-xs btn-delete" data-id="<?= $u['UnitId'] ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add Unit -->
<div class="modal fade" id="modalAddUnit" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="org_units_action.php" method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-content">
                <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                    <h5 class="modal-title">Tambah Unit Baru</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Unit <span class="text-danger">*</span></label>
                        <input type="text" name="UnitName" class="form-control" placeholder="Contoh: MT Senior, Akademi, dll" required>
                    </div>
                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="Description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Unit -->
<div class="modal fade" id="modalEditUnit" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="org_units_action.php" method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="UnitId" id="editUnitId">
            <div class="modal-content">
                <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                    <h5 class="modal-title">Edit Unit</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Unit <span class="text-danger">*</span></label>
                        <input type="text" name="UnitName" id="editUnitName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="Description" id="editDescription" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="IsActive" id="editIsActive" class="form-control">
                            <option value="1">Aktif</option>
                            <option value="0">Non-Aktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-info text-white">Update</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- DataTables & Plugins -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
$(function () {
    $("#unitTable").DataTable({
        "responsive": true,
        "autoWidth": false,
        "order": [[0, "desc"]]
    });

    // Handle Edit Button
    $('.btn-edit').on('click', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        var desc = $(this).data('desc');
        var active = $(this).data('active');

        $('#editUnitId').val(id);
        $('#editUnitName').val(name);
        $('#editDescription').val(desc);
        $('#editIsActive').val(active ? "1" : "0");

        $('#modalEditUnit').modal('show');
    });

    // Handle Delete Button
    $('.btn-delete').on('click', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Hapus Unit?',
            text: "Data yang dihapus tidak dapat dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'org_units_action.php?action=delete&id=' + id;
            }
        })
    });

    // Alert Handling
    <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({ icon: 'success', title: 'Berhasil!', text: '<?= $_SESSION['success'] ?>', timer: 2500, showConfirmButton: false });
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({ icon: 'error', title: 'Gagal!', text: '<?= $_SESSION['error'] ?>', timer: 2500, showConfirmButton: false });
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
});
</script>
