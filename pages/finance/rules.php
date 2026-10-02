<?php
// rules.php - Management for Dynamic Approval Rules (Rule Engine)
session_start();
ob_start();
require_once __DIR__ . '/../../koneksi.php';

date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

// Fetch Approval Rules
$sql = "SELECT r.*, 
        (SELECT COUNT(*) FROM fin_workflow_steps s WHERE s.RuleId = r.RuleId) as TotalSteps
        FROM fin_approval_rules r 
        ORDER BY r.MinAmount ASC";
$stmt = sqlsrv_query($conn, $sql);
$rules = [];
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rules[] = $row;
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
                <div class="col-sm-6"><h1 class="m-0 text-dark">Master Rule Engine Approval</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                        <li class="breadcrumb-item active">Rule Engine</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="alert alert-warning shadow-sm border-left-warning">
                <h5><i class="icon fas fa-cogs"></i> Konfigurasi Rule Engine</h5>
                Tentukan aturan jalur approval berdasarkan nominal pengajuan. Sistem akan otomatis memilih jalur (workflow) yang sesuai dengan nominal dana yang diminta.
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white d-flex align-items-center">
                    <h3 class="card-title mr-auto"><i class="fas fa-filter mr-2"></i>Daftar Aturan (Rules)</h3>
                    <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#modalAddRule">
                        <i class="fas fa-plus mr-1"></i> Tambah Rule
                    </button>
                </div>
                <div class="card-body">
                    <table id="ruleTable" class="table table-bordered table-hover">
                        <thead>
                            <tr class="bg-light">
                                <th width="60">ID</th>
                                <th>Nama Aturan</th>
                                <th>Min Nominal</th>
                                <th>Max Nominal</th>
                                <th>Condition</th>
                                <th class="text-center">Total Steps</th>
                                <th width="100">Status</th>
                                <th width="120">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rules as $r): ?>
                            <tr>
                                <td data-order="<?= $r['RuleId'] ?>">#<?= $r['RuleId'] ?></td>
                                <td><strong><?= htmlspecialchars($r['RuleName']) ?></strong></td>
                                <td>Rp <?= number_format($r['MinAmount'], 0, ',', '.') ?></td>
                                <td><?= ($r['MaxAmount'] >= 999999999999) ? '∞' : 'Rp ' . number_format($r['MaxAmount'], 0, ',', '.') ?></td>
                                <td><span class="badge badge-secondary"><?= htmlspecialchars($r['ConditionType']) ?></span></td>
                                <td class="text-center">
                                    <span class="badge badge-pill badge-dark"><?= $r['TotalSteps'] ?> Tahapan</span>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $r['IsActive'] ? 'success' : 'danger' ?>">
                                        <?= $r['IsActive'] ? 'Aktif' : 'Non-Aktif' ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-info btn-xs btn-edit" 
                                            data-id="<?= $r['RuleId'] ?>" 
                                            data-name="<?= htmlspecialchars($r['RuleName']) ?>"
                                            data-min="<?= $r['MinAmount'] ?>"
                                            data-max="<?= $r['MaxAmount'] ?>"
                                            data-type="<?= htmlspecialchars($r['ConditionType']) ?>"
                                            data-active="<?= $r['IsActive'] ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="workflows.php?RuleId=<?= $r['RuleId'] ?>" class="btn btn-primary btn-xs" title="Manage Workflow Steps">
                                        <i class="fas fa-project-diagram"></i>
                                    </a>
                                    <button class="btn btn-danger btn-xs btn-delete" data-id="<?= $r['RuleId'] ?>">
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

<!-- Modal Add Rule -->
<div class="modal fade" id="modalAddRule" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="rules_action.php" method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-content">
                <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                    <h5 class="modal-title">Tambah Rule Baru</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Rule <span class="text-danger">*</span></label>
                        <input type="text" name="RuleName" class="form-control" placeholder="Contoh: Pengajuan Kecil, Dana Besar, Khusus Sponsor" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Min Nominal</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                                    <input type="text" class="form-control rupiah-input" placeholder="0">
                                    <input type="hidden" name="MinAmount" class="raw-value" value="0">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Max Nominal</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                                    <input type="text" class="form-control rupiah-input" placeholder="∞">
                                    <input type="hidden" name="MaxAmount" class="raw-value" value="999999999999">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Jenis Kondisi</label>
                        <select name="ConditionType" class="form-control">
                            <option value="Nominal">Berdasarkan Nominal</option>
                            <option value="Sponsor">Dana Sponsor</option>
                            <option value="Emergency">Darurat/Urgent</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Simpan Rule</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Rule -->
<div class="modal fade" id="modalEditRule" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="rules_action.php" method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="RuleId" id="editRuleId">
            <div class="modal-content">
                <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                    <h5 class="modal-title">Edit Rule</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Rule <span class="text-danger">*</span></label>
                        <input type="text" name="RuleName" id="editRuleName" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Min Nominal</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                                    <input type="text" id="editMinAmountDisplay" class="form-control rupiah-input" required>
                                    <input type="hidden" name="MinAmount" id="editMinAmount" class="raw-value">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Max Nominal</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                                    <input type="text" id="editMaxAmountDisplay" class="form-control rupiah-input" required>
                                    <input type="hidden" name="MaxAmount" id="editMaxAmount" class="raw-value">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Jenis Kondisi</label>
                        <select name="ConditionType" id="editConditionType" class="form-control">
                            <option value="Nominal">Berdasarkan Nominal</option>
                            <option value="Sponsor">Dana Sponsor</option>
                            <option value="Emergency">Darurat/Urgent</option>
                        </select>
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
                    <button type="submit" class="btn btn-info text-white">Update Rule</button>
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
    $("#ruleTable").DataTable({ "responsive": true, "autoWidth": false, "order": [[0, "desc"]] });

    // Simplified & Robust Rupiah Formatting
    function formatRupiah(angka) {
        if (!angka && angka !== 0) return '';
        // Ensure it's a string and take only numerical part (ignore decimals for IDR)
        let val = Math.floor(parseFloat(angka)).toString();
        if (isNaN(val) || val === 'NaN') return '';
        return val.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    $('.rupiah-input').on('input', function(){
        var val = $(this).val().replace(/[^0-9]/g, '');
        $(this).closest('.input-group').find('.raw-value').val(val);
        $(this).val(formatRupiah(val));
    });

    $('.btn-edit').on('click', function() {
        var min = $(this).data('min');
        var max = $(this).data('max');
        
        $('#editRuleId').val($(this).data('id'));
        $('#editRuleName').val($(this).data('name'));
        
        $('#editMinAmount').val(min);
        $('#editMinAmountDisplay').val(formatRupiah(min));
        
        $('#editMaxAmount').val(max);
        $('#editMaxAmountDisplay').val(formatRupiah(max));
        
        $('#editConditionType').val($(this).data('type'));
        $('#editIsActive').val($(this).data('active') ? "1" : "0");
        $('#modalEditRule').modal('show');
    });

    $('.btn-delete').on('click', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Hapus Rule?',
            text: "Pastikan tidak ada tahapan (steps) yang terikat pada rule ini.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'rules_action.php?action=delete&id=' + id;
            }
        })
    });

    <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({ icon: 'success', title: 'Berhasil!', text: '<?= $_SESSION['success'] ?>', timer: 2000, showConfirmButton: false });
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({ icon: 'error', title: 'Gagal!', text: '<?= $_SESSION['error'] ?>', timer: 2500, showConfirmButton: false });
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
});
</script>
