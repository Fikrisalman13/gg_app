<?php
// members.php - Master Data for Finance Members (Integrated/Manual)
session_start();
ob_start();
require_once __DIR__ . '/../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

// Fetch Finance Members
$sql = "SELECT m.*, u.UserName as LinkedUsername 
        FROM fin_members m
        LEFT JOIN dbo.SMUserMs u ON m.UserId = u.UserId
        ORDER BY m.FullName ASC";
$stmt = sqlsrv_query($conn, $sql);
$members = [];
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $members[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

// Fetch Active Employees for Import
$empSql = "SELECT u.UserId, e.nama_lengkap, u.UserName, e.email 
           FROM dbo.SMUserMs u 
           JOIN dbo.m_emp e ON u.EmpId = e.id_emp 
           WHERE u.Status = 1 
           AND u.UserId NOT IN (SELECT UserId FROM fin_members WHERE IsManual = 0 AND UserId IS NOT NULL)
           ORDER BY e.nama_lengkap ASC";
$empStmt = sqlsrv_query($conn, $empSql);
$employees = [];
if ($empStmt) {
    while ($row = sqlsrv_fetch_array($empStmt, SQLSRV_FETCH_ASSOC)) {
        $employees[] = $row;
    }
    sqlsrv_free_stmt($empStmt);
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
                <div class="col-sm-6"><h1 class="m-0 text-dark">Master Anggota Finance</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                        <li class="breadcrumb-item active">Master Anggota</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <!-- Summary Widgets -->
            <div class="row">
                <div class="col-md-3">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?= count($members) ?></h3>
                            <p>Total Anggota</p>
                        </div>
                        <div class="icon"><i class="fas fa-users"></i></div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white d-flex align-items-center">
                    <h3 class="card-title mr-auto"><i class="fas fa-user-friends mr-2"></i>Daftar Anggota (Persetujuan & Finance)</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-warning btn-sm mr-1" data-toggle="modal" data-target="#modalImportEmp">
                            <i class="fas fa-sync mr-1"></i> Ambil dari Karyawan
                        </button>
                        <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#modalAddManual">
                            <i class="fas fa-plus mr-1"></i> Anggota Manual
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <table class="table table-bordered table-striped" id="memberTable">
                        <thead>
                            <tr>
                                <th width="50">ID</th>
                                <th>Nama Lengkap</th>
                                <th>Username / ID</th>
                                <th>Tipe</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th width="100">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $m): ?>
                            <tr>
                                <td data-order="<?= $m['MemberId'] ?>"><?= $m['MemberId'] ?></td>
                                <td><strong><?= htmlspecialchars($m['FullName']) ?></strong></td>
                                <td><?= htmlspecialchars($m['IsManual'] ? $m['Username'] : $m['LinkedUsername']) ?></td>
                                <td>
                                    <?php if ($m['IsManual']): ?>
                                        <span class="badge badge-warning"><i class="fas fa-keyboard mr-1"></i> Manual</span>
                                    <?php else: ?>
                                        <span class="badge badge-info"><i class="fas fa-id-card mr-1"></i> Karyawan Aktif</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($m['Email'] ?? '-') ?></td>
                                <td>
                                    <span class="badge badge-<?= $m['IsActive'] ? 'success' : 'danger' ?>">
                                        <?= $m['IsActive'] ? 'Aktif' : 'Non-Aktif' ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-info btn-xs btn-edit" 
                                            data-id="<?= $m['MemberId'] ?>" 
                                            data-name="<?= htmlspecialchars($m['FullName']) ?>"
                                            data-user="<?= htmlspecialchars($m['Username'] ?? '') ?>"
                                            data-email="<?= htmlspecialchars($m['Email'] ?? '') ?>"
                                            data-manual="<?= $m['IsManual'] ?>"
                                            data-active="<?= $m['IsActive'] ?>">
                                        <i class="fas fa-edit"></i>
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

<!-- Modal Import Employee (Sync) -->
<div class="modal fade" id="modalImportEmp">
    <div class="modal-dialog modal-lg">
        <form action="members_action.php" method="POST">
            <input type="hidden" name="action" value="import">
            <div class="modal-content">
                <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                    <h5 class="modal-title"><i class="fas fa-sync mr-2"></i>Pilih dari Karyawan Aktif</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Pilih karyawan yang akan didaftarkan ke dalam database Master Anggota Finance.</p>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th width="40"><input type="checkbox" id="checkAll"></th>
                                    <th>Nama Lengkap</th>
                                    <th>Username</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($employees as $e): ?>
                                <tr>
                                    <td><input type="checkbox" name="UserIds[]" value="<?= $e['UserId'] ?>" class="user-check"></td>
                                    <td><?= htmlspecialchars($e['nama_lengkap']) ?></td>
                                    <td><?= htmlspecialchars($e['UserName']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($employees)): ?>
                                    <tr><td colspan="3" class="text-center">Semua karyawan sudah terdaftar.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning">Import Terpilih</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Add Manual -->
<div class="modal fade" id="modalAddManual">
    <div class="modal-dialog">
        <form action="members_action.php" method="POST">
            <input type="hidden" name="action" value="add_manual">
            <div class="modal-content">
                <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                    <h5 class="modal-title"><i class="fas fa-user-plus mr-2"></i>Tambah Anggota Manual</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="FullName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Username / ID Karyawan</label>
                        <input type="text" name="Username" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="Email" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Simpan Anggota</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit">
    <div class="modal-dialog">
        <form action="members_action.php" method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="MemberId" id="editMemberId">
            <div class="modal-content">
                <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                    <h5 class="modal-title">Edit Anggota</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" name="FullName" id="editFullName" class="form-control" required>
                    </div>
                    <div id="manualFields" style="display: none;">
                        <div class="form-group">
                            <label>Username / ID</label>
                            <input type="text" name="Username" id="editUsername" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="Email" id="editEmail" class="form-control">
                        </div>
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
    $('#memberTable').DataTable({ "responsive": true, "autoWidth": false, "order": [[0, "desc"]] });

    $('#checkAll').on('change', function(){
        $('.user-check').prop('checked', $(this).is(':checked'));
    });

    $('.btn-edit').on('click', function(){
        var id = $(this).data('id');
        var manual = $(this).data('manual');
        
        $('#editMemberId').val(id);
        $('#editFullName').val($(this).data('name'));
        $('#editIsActive').val($(this).data('active'));
        
        if(manual == 1) {
            $('#manualFields').show();
            $('#editUsername').val($(this).data('user'));
            $('#editEmail').val($(this).data('email'));
        } else {
            $('#manualFields').hide();
        }
        
        $('#modalEdit').modal('show');
    });

    <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({ icon: 'success', title: 'Berhasil!', text: '<?= $_SESSION['success'] ?>', timer: 2000, showConfirmButton: false });
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
});
</script>
