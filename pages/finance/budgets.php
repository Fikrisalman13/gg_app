<?php
// budgets.php - Management for Budget Planning
session_start();
ob_start();
require_once __DIR__ . '/../../koneksi.php';

date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

// Fetch Budgets with Unit Name
$sql = "SELECT b.*, u.UnitName 
        FROM fin_budget_plans b 
        JOIN fin_org_units u ON b.UnitId = u.UnitId 
        ORDER BY b.Period DESC, u.UnitName ASC";
$stmt = sqlsrv_query($conn, $sql);
$budgets = [];
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $budgets[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

// Fetch Units for dropdown
$units = [];
$uStmt = sqlsrv_query($conn, "SELECT UnitId, UnitName FROM fin_org_units WHERE IsActive = 1 ORDER BY UnitName");
if ($uStmt) {
    while ($row = sqlsrv_fetch_array($uStmt, SQLSRV_FETCH_ASSOC)) {
        $units[] = $row;
    }
    sqlsrv_free_stmt($uStmt);
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
                <div class="col-sm-6"><h1 class="m-0 text-dark">Perencanaan Anggaran (Budget)</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                        <li class="breadcrumb-item active">Budget Planning</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="alert alert-info shadow-sm">
                <h5><i class="icon fas fa-calculator"></i> Info Budgeting</h5>
                Gunakan halaman ini untuk menentukan limit pengeluaran per unit organisasi untuk periode tertentu. Setiap pengajuan dana nantinya dapat dikaitkan dengan budget yang tersedia.
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white d-flex align-items-center">
                    <h3 class="card-title mr-auto"><i class="fas fa-list-alt mr-2"></i>Daftar Alokasi Budget</h3>
                    <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#modalAddBudget">
                        <i class="fas fa-plus mr-1"></i> Buat Alokasi Baru
                    </button>
                </div>
                <div class="card-body">
                    <table id="budgetTable" class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th width="60">ID</th>
                                <th>Periode</th>
                                <th>Unit Organisasi</th>
                                <th>Total Alokasi</th>
                                <th width="100">Status</th>
                                <th width="120">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($budgets as $b): ?>
                            <tr>
                                <td data-order="<?= $b['BudgetId'] ?>">#<?= $b['BudgetId'] ?></td>
                                <td class="font-weight-bold"><?= $b['Period'] ?></td>
                                <td><?= htmlspecialchars($b['UnitName']) ?></td>
                                <td class="text-primary font-weight-bold">Rp <?= number_format($b['TotalAllocated'], 0, ',', '.') ?></td>
                                <td>
                                    <?php
                                    $statusClass = 'success';
                                    if ($b['Status'] == 'Closed') $statusClass = 'secondary';
                                    ?>
                                    <span class="badge badge-<?= $statusClass ?>"><?= $b['Status'] ?></span>
                                </td>
                                <td>
                                    <button class="btn btn-info btn-xs btn-edit" 
                                            data-id="<?= $b['BudgetId'] ?>" 
                                            data-unit="<?= $b['UnitId'] ?>"
                                            data-period="<?= $b['Period'] ?>"
                                            data-amount="<?= (int)$b['TotalAllocated'] ?>"
                                            data-status="<?= $b['Status'] ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-xs btn-delete" data-id="<?= $b['BudgetId'] ?>">
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
<div class="modal fade" id="modalAddBudget" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="budgets_action.php" method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-content">
                <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                    <h5 class="modal-title font-weight-bold">Buat Alokasi Anggaran</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Unit Organisasi <span class="text-danger">*</span></label>
                        <select name="UnitId" class="form-control" required>
                            <option value="">-- Pilih Unit --</option>
                            <?php foreach ($units as $u): ?>
                                <option value="<?= $u['UnitId'] ?>"><?= htmlspecialchars($u['UnitName']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Periode (YYYY-MM) <span class="text-danger">*</span></label>
                        <input type="month" name="Period" class="form-control" value="<?= date('Y-m') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Total Dana Dialokasikan (Rp) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text font-weight-bold">Rp</span></div>
                            <input type="text" class="form-control rupiah-input" placeholder="0" required>
                            <input type="hidden" name="TotalAllocated" class="raw-value">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Simpan Alokasi</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEditBudget" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="budgets_action.php" method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="BudgetId" id="editBudgetId">
            <div class="modal-content">
                <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                    <h5 class="modal-title font-weight-bold">Update Alokasi Budget</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Unit Organisasi <span class="text-danger">*</span></label>
                        <select name="UnitId" id="editUnitId" class="form-control" required>
                            <?php foreach ($units as $u): ?>
                                <option value="<?= $u['UnitId'] ?>"><?= htmlspecialchars($u['UnitName']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Periode (YYYY-MM) <span class="text-danger">*</span></label>
                        <input type="month" name="Period" id="editPeriod" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Total Dana Dialokasikan (Rp) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text font-weight-bold">Rp</span></div>
                            <input type="text" id="editDisplayAmount" class="form-control rupiah-input" required>
                            <input type="hidden" name="TotalAllocated" id="editRawAmount" class="raw-value">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="Status" id="editStatus" class="form-control">
                            <option value="Active">Active</option>
                            <option value="Closed">Closed</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-info text-white">Simpan Perubahan</button>
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
    $("#budgetTable").DataTable({ "responsive": true, "autoWidth": false, "order": [[0, "desc"]] });

    // Rupiah Formatting
    $('.rupiah-input').on('input', function(){
        var val = $(this).val().replace(/[^0-9]/g, '');
        $(this).closest('.input-group').find('.raw-value').val(val);
        $(this).val(formatRupiah(val));
    });

    function formatRupiah(angka) {
        if (!angka) return '';
        var val = Math.floor(parseFloat(angka)).toString();
        return val.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    $('.btn-edit').on('click', function() {
        var amount = $(this).data('amount');
        $('#editBudgetId').val($(this).data('id'));
        $('#editUnitId').val($(this).data('unit'));
        $('#editPeriod').val($(this).data('period'));
        $('#editRawAmount').val(amount);
        $('#editDisplayAmount').val(formatRupiah(amount));
        $('#editStatus').val($(this).data('status'));
        $('#modalEditBudget').modal('show');
    });

    $('.btn-delete').on('click', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Hapus Alokasi?',
            text: "Data alokasi anggaran akan dihapus secara permanen.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'budgets_action.php?action=delete&id=' + id;
            }
        })
    });

    <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({ icon: 'success', title: 'Berhasil!', text: '<?= $_SESSION['success'] ?>', timer: 2000, showConfirmButton: false });
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({ icon: 'error', title: 'Gagal!', text: '<?= $_SESSION['error'] ?>', timer: 3000, showConfirmButton: false });
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
});
</script>
