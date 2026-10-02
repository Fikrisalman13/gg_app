<?php
// group_members.php - Manage users within a Finance Group
session_start();
ob_start();
require_once __DIR__ . '/../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

$targetGroupId = $_GET['id'] ?? '';
if (empty($targetGroupId)) {
    header('Location: groups.php');
    exit;
}

// Fetch Group Info
$gSql = "SELECT GroupName FROM fin_groups WHERE GroupId = ?";
$gStmt = sqlsrv_query($conn, $gSql, [$targetGroupId]);
$groupInfo = sqlsrv_fetch_array($gStmt, SQLSRV_FETCH_ASSOC);

if (!$groupInfo) {
    die("Group tidak ditemukan.");
}

// Fetch Current Members
$sql = "SELECT m.*, u.UserName, e.nama_lengkap 
        FROM fin_group_members m
        JOIN dbo.SMUserMs u ON m.MemberId = u.UserId
        LEFT JOIN dbo.m_emp e ON u.EmpId = e.id_emp
        WHERE m.GroupId = ?
        ORDER BY e.nama_lengkap ASC";
$stmt = sqlsrv_query($conn, $sql, [$targetGroupId]);
$members = [];
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $members[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

// Fetch Available Users (not yet in this group) from System Users - Simplified
$userSql = "SELECT u.UserId, e.nama_lengkap, u.UserName 
            FROM dbo.SMUserMs u 
            LEFT JOIN dbo.m_emp e ON u.EmpId = e.id_emp 
            WHERE u.UserId NOT IN (SELECT MemberId FROM fin_group_members WHERE GroupId = ?)
            ORDER BY e.nama_lengkap ASC";
$userStmt = sqlsrv_query($conn, $userSql, [$targetGroupId]);
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

<!-- Select2 CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
<style>
    .select2-container--bootstrap4 .select2-selection--single {
        height: calc(2.25rem + 2px) !important;
    }
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1 class="m-0 text-dark">Anggota Group: <?= htmlspecialchars($groupInfo['GroupName']) ?></h1></div>
                <div class="col-sm-6 text-right">
                    <a href="groups.php" class="btn btn-default"><i class="fas fa-arrow-left"></i> Kembali</a>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <!-- Form Tambah Anggota -->
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                            <h3 class="card-title">Tambah Anggota</h3>
                        </div>
                        <div class="card-body">
                            <form action="group_members_action.php" method="POST">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="GroupId" value="<?= $targetGroupId ?>">
                                <div class="form-group">
                                    <label>Pilih User</label>
                                    <select name="MemberId" class="form-control select2bs4" required>
                                        <option value="">-- Pilih Nama Karyawan --</option>
                                        <?php foreach ($users as $u): ?>
                                            <option value="<?= $u['UserId'] ?>">
                                                <?= htmlspecialchars($u['nama_lengkap']) ?> (<?= htmlspecialchars($u['UserName']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary btn-block">Tambahkan ke Group</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Daftar Anggota -->
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                            <h3 class="card-title font-weight-bold">Daftar Anggota Saat Ini</h3>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Nama Lengkap</th>
                                        <th>Username</th>
                                        <th width="100">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($members as $m): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($m['nama_lengkap'] ?? $m['UserName']) ?></td>
                                        <td><?= htmlspecialchars($m['UserName']) ?></td>
                                        <td>
                                            <button class="btn btn-danger btn-xs btn-remove" data-id="<?= $m['GroupMemberId'] ?>" data-name="<?= htmlspecialchars($m['nama_lengkap'] ?? $m['UserName']) ?>">
                                                <i class="fas fa-user-minus"></i> Hapus
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($members)): ?>
                                        <tr><td colspan="3" class="text-center text-muted">Belum ada anggota di group ini.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<form id="formRemove" action="group_members_action.php" method="POST">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="MemberId" id="removeMemberId">
    <input type="hidden" name="GroupId" value="<?= $targetGroupId ?>">
</form>

<?php include '../../includes/footer.php'; ?>
<!-- Select2 JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
$(function(){
    // Initialize Select2
    $('.select2bs4').select2({
        theme: 'bootstrap4',
        placeholder: "-- Pilih Nama Karyawan --",
        allowClear: true
    });

    $('.btn-remove').on('click', function(){
        var id = $(this).data('id');
        var name = $(this).data('name');
        Swal.fire({
            title: 'Hapus Anggota?',
            text: "Keluarkan " + name + " dari group ini?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#removeMemberId').val(id);
                $('#formRemove').submit();
            }
        });
    });

    <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({ icon: 'success', title: 'Berhasil!', text: '<?= $_SESSION['success'] ?>', timer: 1500, showConfirmButton: false });
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({ icon: 'error', title: 'Gagal!', text: '<?= addslashes($_SESSION['error']) ?>' });
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
});
</script>
