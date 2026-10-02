<?php
// requests.php - Main dashboard for financial requests
session_start();
ob_start();
require_once __DIR__ . '/../../koneksi.php';

date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

$userId = $_SESSION['UserId'];
$groupId = $_SESSION['GroupId'];

// Note: Request fetching is now handled server-side via requests_fetch.php
// (Code removed: accidental paste)

// Fetch Units and Categories for the form
$units = [];
$uStmt = sqlsrv_query($conn, "SELECT UnitId, UnitName FROM fin_org_units WHERE IsActive = 1");
if ($uStmt) {
    while ($row = sqlsrv_fetch_array($uStmt, SQLSRV_FETCH_ASSOC)) $units[] = $row;
    sqlsrv_free_stmt($uStmt);
}

$categories = [];
$cStmt = sqlsrv_query($conn, "SELECT CategoryId, CategoryName FROM fin_categories WHERE IsActive = 1");
if ($cStmt) {
    while ($row = sqlsrv_fetch_array($cStmt, SQLSRV_FETCH_ASSOC)) $categories[] = $row;
    sqlsrv_free_stmt($cStmt);
}

// Fetch Active Budgets with Remaining Balance Calculation
$budgets = [];
$budgetSql = "SELECT bp.BudgetId, bp.UnitId, bp.Period, bp.TotalAllocated,
              (SELECT ISNULL(SUM(Amount), 0) FROM fin_requests r WHERE r.BudgetId = bp.BudgetId AND r.Status NOT IN ('Rejected', 'Draft')) as UsedAmount
              FROM fin_budget_plans bp 
              WHERE bp.Status = 'Active'";
$bStmt = sqlsrv_query($conn, $budgetSql);
if ($bStmt) {
    while ($row = sqlsrv_fetch_array($bStmt, SQLSRV_FETCH_ASSOC)) $budgets[] = $row;
    sqlsrv_free_stmt($bStmt);
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">

<style>
    /* Premium DataTable Styling */
    #requestTable_wrapper .dataTables_filter input {
        border-radius: 20px;
        padding: 5px 15px;
        border: 1px solid #ddd;
        transition: all 0.3s;
        background: #fdfdfd;
    }
    #requestTable_wrapper .dataTables_filter input:focus {
        border-color: #007bff;
        box-shadow: 0 0 8px rgba(0,123,255,0.15);
        outline: none;
        background: #fff;
    }
    #requestTable_wrapper .dataTables_length select {
        border-radius: 8px;
        border: 1px solid #ddd;
        padding: 5px 10px;
        margin: 0 8px;
        width: auto;
        min-width: 60px;
        cursor: pointer;
        background: #fff;
    }
    #requestTable_wrapper .dataTables_info {
        color: #8898aa;
        font-size: 0.85rem;
        font-weight: 500;
    }
    #requestTable {
        border-collapse: separate !important;
        border-spacing: 0 12px !important;
        border: none !important;
        margin-top: 10px !important;
    }
    #requestTable thead th {
        border: none !important;
        background-color: transparent !important;
        color: #8898aa;
        text-transform: uppercase;
        font-size: 0.7rem;
        letter-spacing: 1.5px;
        font-weight: 800;
        padding: 10px 15px !important;
    }
    #requestTable tbody tr {
        background-color: #fff !important;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        transition: all 0.25s ease;
        border-radius: 12px;
    }
    #requestTable tbody tr:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        background-color: #fff !important;
    }
    #requestTable tbody td {
        border: none !important;
        padding: 18px 15px !important;
        vertical-align: middle !important;
    }
    #requestTable tbody td:first-child { border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
    #requestTable tbody td:last-child { border-top-right-radius: 12px; border-bottom-right-radius: 12px; }
    
    /* Pagination Styling */
    .pagination .page-item.active .page-link {
        background-color: #007bff;
        border-color: #007bff;
        border-radius: 8px;
        margin: 0 3px;
    }
    .pagination .page-link {
        border-radius: 8px;
        margin: 0 3px;
        border: none;
        color: #8898aa;
        font-weight: 600;
    }

    .badge-pill { border-radius: 50px; padding: 6px 14px; font-size: 0.75rem; letter-spacing: 0.5px; }
    .text-indigo { color: #5e72e4 !important; }
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1 class="m-0 text-dark font-weight-bold">Pengajuan Transaksi Finansial</h1></div>
                <div class="col-sm-6 text-right">
                    <button type="button" class="btn btn-primary px-4 shadow-sm" style="border-radius: 10px;" data-toggle="modal" data-target="#modalAddRequest">
                        <i class="fas fa-plus-circle mr-1"></i> Buat Pengajuan Baru
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm border-0" style="border-radius: 15px; overflow: hidden;">
                        <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white d-flex align-items-center py-3">
                            <h3 class="card-title mr-auto font-weight-bold"><i class="fas fa-list-alt mr-2"></i>Daftar Pengajuan Transaksi</h3>
                        </div>
                        <div class="card-body bg-light-gray" style="background-color: #f8f9fe;">
                            <!-- Date Filter -->
                            <div class="row mb-4 bg-white p-3 mx-1 shadow-sm" style="border-radius: 12px;">
                                <div class="col-md-4">
                                    <div class="form-group mb-0">
                                        <label class="small font-weight-bold text-muted uppercase" style="letter-spacing: 1px;">DARI TANGGAL</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text bg-light border-0"><i class="fas fa-calendar-alt text-primary"></i></span>
                                            </div>
                                            <input type="date" id="startDate" class="form-control border-0 bg-light" style="border-radius: 0 8px 8px 0;">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-0">
                                        <label class="small font-weight-bold text-muted uppercase" style="letter-spacing: 1px;">SAMPAI TANGGAL</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text bg-light border-0"><i class="fas fa-calendar-alt text-primary"></i></span>
                                            </div>
                                            <input type="date" id="endDate" class="form-control border-0 bg-light" style="border-radius: 0 8px 8px 0;">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <div class="btn-group w-100" style="gap: 8px;">
                                        <button type="button" id="btnFilter" class="btn btn-primary shadow-sm flex-grow-1" style="border-radius: 8px;">
                                            <i class="fas fa-filter mr-1"></i> Filter
                                        </button>
                                        <button type="button" id="btnReset" class="btn btn-light border shadow-sm" style="border-radius: 8px;" title="Reset Filter">
                                            <i class="fas fa-undo text-secondary"></i>
                                        </button>
                                        <button type="button" id="btnExportPDF" class="btn btn-outline-danger shadow-sm" style="border-radius: 8px;" title="Export PDF">
                                            <i class="fas fa-file-pdf"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <table id="requestTable" class="table w-100">
                                <thead>
                                    <tr>
                                        <th width="60">ID</th>
                                        <th width="100">Tanggal</th>
                                        <th>Unit</th>
                                        <th>Judul Pengajuan</th>
                                        <th>Kategori</th>
                                        <th>Nominal</th>
                                        <th class="text-center">Status</th>
                                        <th>Posisi Approval</th>
                                        <th width="110" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Data populated by DataTables Server-side -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add Request -->
<div class="modal fade" id="modalAddRequest" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="requests_action.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <div class="modal-content">
                <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                    <h5 class="modal-title font-weight-bold"><i class="fas fa-plus-circle mr-2"></i>Form Pengajuan Dana Baru</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-6 border-right">
                            <div class="form-group">
                                <label class="font-weight-bold">Unit Organisasi <span class="text-danger">*</span></label>
                                <select name="UnitId" id="requestUnitId" class="form-control" required>
                                    <option value="">-- Pilih Unit --</option>
                                    <?php foreach ($units as $u): ?>
                                        <option value="<?= $u['UnitId'] ?>"><?= htmlspecialchars($u['UnitName']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold">Kategori Pengeluaran <span class="text-danger">*</span></label>
                                <select name="CategoryId" class="form-control" required>
                                    <option value="">-- Pilih Kategori --</option>
                                    <?php foreach ($categories as $c): ?>
                                        <option value="<?= $c['CategoryId'] ?>"><?= htmlspecialchars($c['CategoryName']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div id="budgetRow" style="display:none;" class="bg-light p-3 rounded mb-3 border">
                                <div class="form-group mb-0">
                                    <label class="small font-weight-bold">Alokasi Budget (Opsional)</label>
                                    <select name="BudgetId" id="requestBudgetId" class="form-control form-control-sm">
                                        <option value="">-- Gunakan Dana Umum --</option>
                                        <?php foreach ($budgets as $b): 
                                            $remaining = $b['TotalAllocated'] - $b['UsedAmount'];
                                        ?>
                                            <option value="<?= $b['BudgetId'] ?>" data-unit="<?= $b['UnitId'] ?>" style="display:none;">
                                                <?= $b['Period'] ?> (Sisa: Rp <?= number_format($remaining, 0, ',', '.') ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Judul Pengajuan <span class="text-danger">*</span></label>
                                <input type="text" name="Title" class="form-control" placeholder="Contoh: Pembelian Perlengkapan Kantor" required>
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold">Nominal yang Dibutuhkan <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text font-weight-bold border-dark">Rp</span></div>
                                    <input type="text" id="displayAmount" class="form-control form-control-lg font-weight-bold text-dark border-dark" placeholder="0" required>
                                    <input type="hidden" name="Amount" id="rawAmount">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group mt-3">
                        <label class="font-weight-bold">Deskripsi Detail / Keperluan</label>
                        <textarea name="Description" class="form-control" rows="3" placeholder="Jelaskan rincian penggunaan dana..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-default px-4" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-5 font-weight-bold shadow-sm">Kirim Pengajuan <i class="fas fa-paper-plane ml-1"></i></button>
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
    // Inisialisasi DataTable Server-side
    var table = $("#requestTable").DataTable({ 
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "requests_fetch.php",
            "type": "POST",
            "data": function(d) {
                d.startDate = $('#startDate').val();
                d.endDate = $('#endDate').val();
            }
        },
        "responsive": true, 
        "autoWidth": false, 
        "order": [[0, "desc"]],
        "language": {
            "search": "Cari:",
            "lengthMenu": "Tampilkan _MENU_ entri",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            "paginate": { "next": "Next", "previous": "Prev" }
        },
        "columnDefs": [
            { "orderable": false, "targets": 8 } // Nonaktifkan sortir untuk kolom Aksi
        ]
    });

    // Handle Filter Button
    $('#btnFilter').click(function(){
        $(this).html('<i class="fas fa-spinner fa-spin mr-1"></i> Filtering...').prop('disabled', true);
        table.ajax.reload(function(){
            $('#btnFilter').html('<i class="fas fa-filter mr-1"></i> Filter').prop('disabled', false);
        });
    });

    // Handle Reset Button
    $('#btnReset').click(function(){
        $('#startDate').val('');
        $('#endDate').val('');
        table.ajax.reload();
    });

    // Handle Export PDF Button
    $('#btnExportPDF').click(function(){
        var start = $('#startDate').val();
        var end = $('#endDate').val();
        var url = 'requests_pdf_export.php?startDate=' + start + '&endDate=' + end;
        window.open(url, '_blank');
    });

    // Notifikasi SweetAlert (Pembersihan logic redundant)
    <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({ icon: 'success', title: 'Berhasil!', text: '<?= $_SESSION['success'] ?>', timer: 2500, showConfirmButton: false });
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({ icon: 'error', title: 'Gagal!', text: '<?= $_SESSION['error'] ?>' });
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    // Format Rupiah Live
    $('#displayAmount').on('input', function() {
        let val = this.value.replace(/\D/g, '');
        $('#rawAmount').val(val);
        this.value = val.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    });

    // Filter Budget berdasarkan Unit
    $('#requestUnitId').on('change', function() {
        var unitId = $(this).val();
        $('#requestBudgetId').val('');
        var count = 0;
        $('#requestBudgetId option').each(function() {
            var optUnit = $(this).data('unit');
            if (optUnit == unitId) {
                $(this).show();
                count++;
            } else if (!optUnit) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
        if (count > 0) $('#budgetRow').slideDown();
        else $('#budgetRow').slideUp();
    });

    // Aksi Hapus
    $(document).on('click', '.btn-delete', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Hapus Pengajuan?',
            text: "Data akan dihapus permanen dari sistem.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'requests_action.php?action=delete&id=' + id;
            }
        })
    });
});
</script>

