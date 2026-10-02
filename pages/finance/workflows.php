<?php
// workflows.php - Management for dynamic approval steps
session_start();
ob_start();
require_once __DIR__ . '/../../koneksi.php';

date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

// Fetch Rule Information
$ruleId = $_GET['RuleId'] ?? '';
$ruleInfo = null;
if ($ruleId) {
    $rSql = "SELECT RuleName FROM fin_approval_rules WHERE RuleId = ?";
    $rStmt = sqlsrv_query($conn, $rSql, [$ruleId]);
    if ($rStmt) {
        $ruleInfo = sqlsrv_fetch_array($rStmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($rStmt);
    }
}

// Fetch Workflow Steps
$sql = "SELECT s.*, g.GroupName 
        FROM fin_workflow_steps s
        LEFT JOIN fin_groups g ON s.RequiredGroupId = g.GroupId
        WHERE s.RuleId = ?
        ORDER BY s.StepOrder ASC";
$stmt = sqlsrv_query($conn, $sql, [$ruleId]);
$steps = [];
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $steps[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

// Fetch Groups for the dropdown
$groupSql = "SELECT GroupId, GroupName FROM fin_groups WHERE IsActive = 1 ORDER BY GroupName ASC";
$groupStmt = sqlsrv_query($conn, $groupSql);
$groups = [];
if ($groupStmt) {
    while ($row = sqlsrv_fetch_array($groupStmt, SQLSRV_FETCH_ASSOC)) {
        $groups[] = $row;
    }
    sqlsrv_free_stmt($groupStmt);
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
                <div class="col-sm-6"><h1 class="m-0 text-dark"><?= $ruleInfo ? 'Tahapan: ' . htmlspecialchars($ruleInfo['RuleName']) : 'Pengaturan Tahapan Approval' ?></h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="rules.php">Rule Engine</a></li>
                        <li class="breadcrumb-item active">Workflow Designer</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="alert alert-info shadow-sm">
                <h5><i class="icon fas fa-info"></i> Info Arsitektur</h5>
                Tahapan ini menentukan siapa yang harus menyetujui anggaran atau pengajuan dana secara berurutan. 
                Urutan (Order) menentukan alur dari 1 ke seterusnya.
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white d-flex align-items-center">
                    <h3 class="card-title mr-auto"><i class="fas fa-project-diagram mr-2"></i>Urutan Tahapan untuk Rule: <?= htmlspecialchars($ruleInfo['RuleName'] ?? 'General') ?></h3>
                    <?php if ($ruleId): ?>
                    <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#modalAddStep">
                        <i class="fas fa-plus mr-1"></i> Tambah Tahap
                    </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <table id="workflowTable" class="table table-bordered">
                        <thead class="bg-light">
                            <tr>
                                <th width="80">Urutan</th>
                                <th>Nama Tahapan</th>
                                <th>Group Penanggung Jawab</th>
                                <th>Jenis Aksi</th>
                                <th width="120">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($steps as $s): ?>
                            <tr>
                                <td class="text-center font-weight-bold"><?= $s['StepOrder'] ?></td>
                                <td><?= htmlspecialchars($s['StepName']) ?></td>
                                <td>
                                    <span class="badge badge-primary p-2">
                                        <i class="fas fa-users mr-1"></i> <?= htmlspecialchars($s['GroupName'] ?? 'Admin Only') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?= htmlspecialchars($s['ActionType'] ?? 'Approval') ?></span>
                                </td>
                                <td>
                                    <button class="btn btn-info btn-xs btn-edit" 
                                            data-id="<?= $s['StepId'] ?>" 
                                            data-name="<?= htmlspecialchars($s['StepName']) ?>"
                                            data-order="<?= $s['StepOrder'] ?>"
                                            data-group="<?= $s['RequiredGroupId'] ?>"
                                            data-type="<?= htmlspecialchars($s['ActionType']) ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-xs btn-delete" data-id="<?= $s['StepId'] ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($steps)): ?>
                                <tr><td colspan="5" class="text-center text-muted">Belum ada tahapan yang dikonfigurasi.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add Step -->
<div class="modal fade" id="modalAddStep" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="workflows_action.php" method="POST">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="RuleId" value="<?= htmlspecialchars($ruleId) ?>">
            <div class="modal-content">
                <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                    <h5 class="modal-title">Tambah Tahapan Baru</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Tahapan <span class="text-danger">*</span></label>
                        <input type="text" name="StepName" class="form-control" placeholder="Contoh: Approval VP, Verifikasi Bendahara" required>
                    </div>
                    <div class="form-group">
                        <label>Urutan (Nomor) <span class="text-danger">*</span></label>
                        <input type="number" name="StepOrder" class="form-control" placeholder="1, 2, 3..." required>
                    </div>
                    <div class="form-group">
                        <label>Group User <span class="text-danger">*</span></label>
                        <select name="RequiredGroupId" class="form-control" required>
                            <option value="">-- Pilih Group --</option>
                            <?php foreach ($groups as $g): ?>
                                <option value="<?= $g['GroupId'] ?>"><?= htmlspecialchars($g['GroupName']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Jenis Aksi</label>
                        <select name="ActionType" class="form-control">
                            <option value="Approval">Persetujuan (Approval)</option>
                            <option value="Disbursement">Pencairan Dana (Disbursement)</option>
                            <option value="Verification">Verifikasi Laporan/Arsip</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Tahapan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Step -->
<div class="modal fade" id="modalEditStep" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="workflows_action.php" method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="StepId" id="editStepId">
            <input type="hidden" name="RuleId" value="<?= htmlspecialchars($ruleId) ?>">
            <div class="modal-content">
                <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                    <h5 class="modal-title">Edit Tahapan</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Tahapan <span class="text-danger">*</span></label>
                        <input type="text" name="StepName" id="editStepName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Urutan (Nomor) <span class="text-danger">*</span></label>
                        <input type="number" name="StepOrder" id="editStepOrder" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Group User <span class="text-danger">*</span></label>
                        <select name="RequiredGroupId" id="editGroupId" class="form-control" required>
                            <option value="">-- Pilih Group --</option>
                            <?php foreach ($groups as $g): ?>
                                <option value="<?= $g['GroupId'] ?>"><?= htmlspecialchars($g['GroupName']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Jenis Aksi</label>
                        <select name="ActionType" id="editActionType" class="form-control">
                            <option value="Approval">Persetujuan (Approval)</option>
                            <option value="Disbursement">Pencairan Dana (Disbursement)</option>
                            <option value="Verification">Verifikasi Laporan/Arsip</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-info text-white">Update Tahapan</button>
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
    $('#workflowTable').DataTable({ "responsive": true, "autoWidth": false, "paging": false, "info": false, "searching": false });

    $('.btn-edit').on('click', function(){
        $('#editStepId').val($(this).data('id'));
        $('#editStepName').val($(this).data('name'));
        $('#editStepOrder').val($(this).data('order'));
        $('#editGroupId').val($(this).data('group'));
        $('#editActionType').val($(this).data('type'));
        $('#modalEditStep').modal('show');
    });

    $('.btn-delete').on('click', function(){
        var id = $(this).data('id');
        Swal.fire({
            title: 'Hapus Tahapan?',
            text: "Pastikan tidak ada pengajuan aktif yang bergantung pada tahapan ini.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'workflows_action.php?action=delete&id=' + id + '&RuleId=<?= $ruleId ?>';
            }
        });
    });

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
