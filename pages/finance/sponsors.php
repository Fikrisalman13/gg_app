<?php
// sponsors.php - Management for External Sponsors
session_start();
ob_start();
require_once __DIR__ . '/../../koneksi.php';

date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

$sql = "SELECT * FROM fin_sponsors ORDER BY SponsorName ASC";
$stmt = sqlsrv_query($conn, $sql);
$sponsors = [];
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $sponsors[] = $row;
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
                <div class="col-sm-6"><h1 class="m-0 text-dark">Master Sponsor / Pendonor</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                        <li class="breadcrumb-item active">Sponsors</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white d-flex align-items-center">
                    <h3 class="card-title mr-auto"><i class="fas fa-hand-holding-usd mr-2"></i>Daftar Sponsor</h3>
                    <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#modalAddSponsor">
                        <i class="fas fa-plus mr-1"></i> Tambah Sponsor
                    </button>
                </div>
                <div class="card-body">
                    <table id="sponsorTable" class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th width="50">ID</th>
                                <th>Nama Sponsor</th>
                                <th>Deskripsi / Kontak</th>
                                <th width="100">Status</th>
                                <th width="120">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sponsors as $s): ?>
                            <tr>
                                <td data-order="<?= $s['SponsorId'] ?>"><?= $s['SponsorId'] ?></td>
                                <td><strong><?= htmlspecialchars($s['SponsorName']) ?></strong></td>
                                <td><?= htmlspecialchars($s['Description'] ?? '-') ?></td>
                                <td>
                                    <span class="badge badge-<?= $s['IsActive'] ? 'success' : 'danger' ?>">
                                        <?= $s['IsActive'] ? 'Aktif' : 'Non-Aktif' ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-info btn-xs btn-edit" 
                                            data-id="<?= $s['SponsorId'] ?>" 
                                            data-name="<?= htmlspecialchars($s['SponsorName']) ?>"
                                            data-desc="<?= htmlspecialchars($s['Description'] ?? '') ?>"
                                            data-active="<?= $s['IsActive'] ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-xs btn-delete" data-id="<?= $s['SponsorId'] ?>" data-name="<?= htmlspecialchars($s['SponsorName']) ?>">
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
<div class="modal fade" id="modalAddSponsor" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="sponsors_action.php" method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-content">
                <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                    <h5 class="modal-title">Tambah Sponsor Baru</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Sponsor <span class="text-danger">*</span></label>
                        <input type="text" name="SponsorName" class="form-control" placeholder="Contoh: PT ABC, Bapak X" required>
                    </div>
                    <div class="form-group">
                        <label>Deskripsi / Kontak</label>
                        <textarea name="Description" class="form-control" rows="3" placeholder="Informasi detail sponsor..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Simpan Sponsor</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEditSponsor" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="sponsors_action.php" method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="SponsorId" id="editSponsorId">
            <div class="modal-content">
                <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                    <h5 class="modal-title">Edit Sponsor</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Sponsor <span class="text-danger">*</span></label>
                        <input type="text" name="SponsorName" id="editSponsorName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Deskripsi / Kontak</label>
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
                    <button type="submit" class="btn btn-info text-white">Update Sponsor</button>
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
    $("#sponsorTable").DataTable({ "responsive": true, "autoWidth": false, "order": [[0, "desc"]] });

    $('.btn-edit').on('click', function() {
        $('#editSponsorId').val($(this).data('id'));
        $('#editSponsorName').val($(this).data('name'));
        $('#editDescription').val($(this).data('desc'));
        $('#editIsActive').val($(this).data('active') ? "1" : "0");
        $('#modalEditSponsor').modal('show');
    });

    $('.btn-delete').on('click', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        Swal.fire({
            title: 'Hapus Sponsor?',
            text: "Sponsor '" + name + "' akan dihapus secara permanen.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'sponsors_action.php?action=delete&id=' + id;
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
