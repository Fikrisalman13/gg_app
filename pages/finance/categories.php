<?php
// categories.php - Management for Expense Categories
session_start();
ob_start();
require_once __DIR__ . '/../../koneksi.php';

date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

$sql = "SELECT * FROM fin_categories ORDER BY CategoryName ASC";
$stmt = sqlsrv_query($conn, $sql);
$categories = [];
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $categories[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

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
                <div class="col-sm-6"><h1 class="m-0 text-dark">Master Kategori Pengeluaran</h1></div>
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
                    <h3 class="card-title mr-auto"><i class="fas fa-tags mr-2"></i>Daftar Kategori</h3>
                    <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#modalAddCategory">
                        <i class="fas fa-plus mr-1"></i> Tambah Kategori
                    </button>
                </div>
                <div class="card-body">
                    <table id="categoryTable" class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th width="50">ID</th>
                                <th>Nama Kategori</th>
                                <th width="100">Status</th>
                                <th width="120">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $c): ?>
                            <tr>
                                <td data-order="<?= $c['CategoryId'] ?>"><?= $c['CategoryId'] ?></td>
                                <td><strong><?= htmlspecialchars($c['CategoryName']) ?></strong></td>
                                <td>
                                    <span class="badge badge-<?= $c['IsActive'] ? 'success' : 'danger' ?>">
                                        <?= $c['IsActive'] ? 'Aktif' : 'Non-Aktif' ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-info btn-xs btn-edit" 
                                            data-id="<?= $c['CategoryId'] ?>" 
                                            data-name="<?= htmlspecialchars($c['CategoryName']) ?>"
                                            data-active="<?= $c['IsActive'] ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-xs btn-delete" data-id="<?= $c['CategoryId'] ?>">
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

<!-- Modal Add -->
<div class="modal fade" id="modalAddCategory" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="categories_action.php" method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-content">
                <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                    <h5 class="modal-title">Tambah Kategori Baru</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="CategoryName" class="form-control" placeholder="Contoh: Gaji, Konsumsi, dll" required>
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

<!-- Modal Edit -->
<div class="modal fade" id="modalEditCategory" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="categories_action.php" method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="CategoryId" id="editCategoryId">
            <div class="modal-content">
                <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                    <h5 class="modal-title">Edit Kategori</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="CategoryName" id="editCategoryName" class="form-control" required>
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
    $("#categoryTable").DataTable({ "responsive": true, "autoWidth": false, "order": [[0, "desc"]] });

    $('.btn-edit').on('click', function() {
        $('#editCategoryId').val($(this).data('id'));
        $('#editCategoryName').val($(this).data('name'));
        $('#editIsActive').val($(this).data('active') ? "1" : "0");
        $('#modalEditCategory').modal('show');
    });

    $('.btn-delete').on('click', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Hapus Kategori?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'categories_action.php?action=delete&id=' + id;
            }
        })
    });

    <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({ icon: 'success', title: 'Berhasil!', text: '<?= $_SESSION['success'] ?>', timer: 2000, showConfirmButton: false });
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({ icon: 'error', title: 'Gagal!', text: '<?= $_SESSION['error'] ?>', timer: 2000, showConfirmButton: false });
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
});
</script>
