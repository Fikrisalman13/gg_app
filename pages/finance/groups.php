<?php
// groups.php - Management for Finance-specific groups
session_start();
ob_start();
require_once __DIR__ . '/../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

// Fetch Finance Groups
$sql = "SELECT * FROM fin_groups ORDER BY GroupName ASC";
$stmt = sqlsrv_query($conn, $sql);
$groups = [];
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $groups[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

// Fetch Users for member assignment
$userSql = "SELECT u.UserId, e.nama_lengkap FROM dbo.SMUserMs u JOIN dbo.m_emp e ON u.EmpId = e.id_emp WHERE u.Status = 1 ORDER BY e.nama_lengkap ASC";
$userStmt = sqlsrv_query($conn, $userSql);
$users = [];
if ($userStmt) {
    while ($row = sqlsrv_fetch_array($userStmt, SQLSRV_FETCH_ASSOC)) {
        $users[] = $row;
    }
    sqlsrv_free_stmt($userStmt);
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
                <div class="col-sm-6"><h1 class="m-0 text-dark">Master Group Finance</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                        <li class="breadcrumb-item active">Finance Groups</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card shadow-sm">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white d-flex align-items-center">
                    <h3 class="card-title mr-auto"><i class="fas fa-users-cog mr-2"></i>Daftar Group Persetujuan</h3>
                    <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#modalAddGroup">
                        <i class="fas fa-plus mr-1"></i> Tambah Group
                    </button>
                </div>
                <div class="card-body">
                    <table class="table table-bordered" id="groupTable">
                        <thead>
                            <tr>
                                <th width="50">ID</th>
                                <th>Nama Group</th>
                                <th>Deskripsi</th>
                                <th>Status</th>
                                <th width="150">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($groups as $g): ?>
                            <tr>
                                <td data-order="<?= $g['GroupId'] ?>"><?= $g['GroupId'] ?></td>
                                <td><strong><?= htmlspecialchars($g['GroupName']) ?></strong></td>
                                <td><?= htmlspecialchars($g['Description']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $g['IsActive'] ? 'success' : 'danger' ?>">
                                        <?= $g['IsActive'] ? 'Aktif' : 'Non-Aktif' ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-info btn-xs btn-edit" 
                                            data-id="<?= $g['GroupId'] ?>" 
                                            data-name="<?= htmlspecialchars($g['GroupName']) ?>"
                                            data-desc="<?= htmlspecialchars($g['Description']) ?>"
                                            data-active="<?= $g['IsActive'] ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="group_members.php?id=<?= $g['GroupId'] ?>" class="btn btn-primary btn-xs">
                                        <i class="fas fa-user-plus"></i> Anggota
                                    </a>
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

<!-- Modal Add Group -->
<div class="modal fade" id="modalAddGroup">
    <div class="modal-dialog">
        <form action="groups_action.php" method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-content">
                <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                    <h5 class="modal-title">Tambah Group Finance</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Group <span class="text-danger">*</span></label>
                        <input type="text" name="GroupName" class="form-control" placeholder="Contoh: Manager Operasional, Finance-1" required>
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

<!-- Modal Edit Group -->
<div class="modal fade" id="modalEditGroup">
    <div class="modal-dialog">
        <form action="groups_action.php" method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="GroupId" id="editGroupId">
            <div class="modal-content">
                <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                    <h5 class="modal-title">Edit Group Finance</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Group <span class="text-danger">*</span></label>
                        <input type="text" name="GroupName" id="editGroupName" class="form-control" required>
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
$(function(){
    $('#groupTable').DataTable({ "responsive": true, "autoWidth": false, "order": [[0, "desc"]] });

    $('.btn-edit').on('click', function(){
        $('#editGroupId').val($(this).data('id'));
        $('#editGroupName').val($(this).data('name'));
        $('#editDescription').val($(this).data('desc'));
        $('#editIsActive').val($(this).data('active'));
        $('#modalEditGroup').modal('show');
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
