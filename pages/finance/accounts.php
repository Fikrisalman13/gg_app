<?php
// accounts.php - Management for Cash/Bank Accounts
session_start();
ob_start();
require_once __DIR__ . '/../../koneksi.php';

date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

// Fetch Accounts
$sql = "SELECT a.*, s.SponsorName 
        FROM fin_cash_accounts a
        LEFT JOIN fin_sponsors s ON a.SponsorId = s.SponsorId
        ORDER BY a.AccountName ASC";
$stmt = sqlsrv_query($conn, $sql);
$accounts = [];
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $accounts[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

// Fetch Sponsors for mapping
$sponsors = [];
$spStmt = sqlsrv_query($conn, "SELECT SponsorId, SponsorName FROM fin_sponsors WHERE IsActive = 1");
if ($spStmt) {
    while ($row = sqlsrv_fetch_array($spStmt, SQLSRV_FETCH_ASSOC)) {
        $sponsors[] = $row;
    }
    sqlsrv_free_stmt($spStmt);
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
                <div class="col-sm-6"><h1 class="m-0 text-dark">Manajemen Kas & Bank</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                        <li class="breadcrumb-item active">Treasury</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white d-flex align-items-center">
                    <h3 class="card-title mr-auto"><i class="fas fa-wallet mr-2"></i>Daftar Rekening/Kas</h3>
                    <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#modalAddAccount">
                        <i class="fas fa-plus mr-1"></i> Tambah Kas
                    </button>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if (empty($accounts)): ?>
                        <div class="col-12 text-center py-5">
                            <div class="text-muted">
                                <i class="fas fa-wallet fa-4x mb-3" style="opacity: 0.2;"></i>
                                <h5>Belum ada akun/rekening kas</h5>
                                <p>Silakan klik tombol <strong>Tambah Kas</strong> untuk membuat rekening baru.</p>
                            </div>
                        </div>
                        <?php else: ?>
                            <?php foreach ($accounts as $a): ?>
                            <div class="col-md-4">
                                <div class="small-box bg-white border shadow-sm">
                                    <div class="inner">
                                        <h4 class="font-weight-bold text-dark"><?= htmlspecialchars($a['AccountName']) ?></h4>
                                        <h3 class="text-primary">Rp <?= number_format($a['CurrentBalance'], 0, ',', '.') ?></h3>
                                        <p class="mb-0 text-muted">
                                            <i class="fas fa-info-circle mr-1"></i> 
                                            <?= $a['SponsorId'] ? 'Khusus Sponsor: ' . htmlspecialchars($a['SponsorName']) : 'Kas Umum / General' ?>
                                        </p>
                                    </div>
                                    <div class="icon">
                                        <i class="<?= $a['SponsorId'] ? 'fas fa-hand-holding-usd' : 'fas fa-university' ?>" style="opacity: 0.1;"></i>
                                    </div>
                                    <div class="small-box-footer bg-light text-center p-2">
                                        <button class="btn btn-success btn-xs btn-topup" data-id="<?= $a['AccountId'] ?>" data-name="<?= htmlspecialchars($a['AccountName']) ?>">
                                            <i class="fas fa-plus mr-1"></i> Top-up
                                        </button>
                                        <button class="btn btn-info btn-xs btn-transfer" 
                                                data-id="<?= $a['AccountId'] ?>" 
                                                data-name="<?= htmlspecialchars($a['AccountName']) ?>"
                                                data-balance="Rp <?= number_format($a['CurrentBalance'], 0, ',', '.') ?>">
                                            <i class="fas fa-exchange-alt mr-1"></i> Transfer
                                        </button>
                                        <button class="btn btn-primary btn-xs btn-history" data-id="<?= $a['AccountId'] ?>" data-name="<?= htmlspecialchars($a['AccountName']) ?>">
                                            <i class="fas fa-history mr-1"></i> History
                                        </button>
                                        <button class="btn btn-secondary btn-xs btn-print" data-id="<?= $a['AccountId'] ?>" data-name="<?= htmlspecialchars($a['AccountName']) ?>">
                                            <i class="fas fa-print mr-1"></i> Print
                                        </button>
                                        <button class="btn btn-warning btn-xs btn-edit" 
                                                data-id="<?= $a['AccountId'] ?>" 
                                                data-name="<?= htmlspecialchars($a['AccountName']) ?>"
                                                data-sponsor="<?= $a['SponsorId'] ?>">
                                            <i class="fas fa-edit mr-1"></i> Update
                                         </button>
                                         <?php if (isset($_SESSION['GroupId']) && $_SESSION['GroupId'] == 1): ?>
                                         <button class="btn btn-danger btn-xs btn-delete" 
                                                 data-id="<?= $a['AccountId'] ?>" 
                                                 data-name="<?= htmlspecialchars($a['AccountName']) ?>">
                                             <i class="fas fa-trash mr-1"></i> Delete
                                         </button>
                                         <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add Account -->
<div class="modal fade" id="modalAddAccount" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="accounts_action.php" method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-content">
                <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                    <h5 class="modal-title font-weight-bold">Tambah Akun Kas/Bank</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Akun <span class="text-danger">*</span></label>
                        <input type="text" name="AccountName" class="form-control" placeholder="Contoh: Kas Kecil, Bank BCA" required>
                    </div>
                    <div class="form-group">
                        <label>Saldo Awal (Opsional)</label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                            <input type="text" name="InitialBalanceDisplay" class="form-control rupiah-input" placeholder="0">
                            <input type="hidden" name="InitialBalance" class="raw-value">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Kategori Sponsor (Opsional)</label>
                        <select name="SponsorId" class="form-control">
                            <option value="">-- Kas Umum (Bukan Sponsor) --</option>
                            <?php foreach ($sponsors as $s): ?>
                                <option value="<?= $s['SponsorId'] ?>"><?= htmlspecialchars($s['SponsorName']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Simpan Akun</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Topup Account -->
<div class="modal fade" id="modalTopupAccount" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="accounts_action.php" method="POST">
            <input type="hidden" name="action" value="topup">
            <input type="hidden" name="AccountId" id="topupAccountId">
            <div class="modal-content">
                <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                    <h5 class="modal-title font-weight-bold">Top-up Saldo: <span id="topupAccountName"></span></h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nominal Top-up (Rp) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                            <input type="text" id="topupDisplay" class="form-control rupiah-input" placeholder="0" required>
                            <input type="hidden" name="Amount" id="topupRaw" class="raw-value">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Catatan (Opsional)</label>
                        <textarea name="Note" class="form-control" rows="2" placeholder="Contoh: Setoran awal bulan, Hibah sponsor..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Proses Top-up <i class="fas fa-coins ml-1"></i></button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Account -->
<div class="modal fade" id="modalEditAccount" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="accounts_action.php" method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="AccountId" id="editAccountId">
            <div class="modal-content">
                <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                    <h5 class="modal-title font-weight-bold">Update Informasi Akun</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Akun <span class="text-danger">*</span></label>
                        <input type="text" name="AccountName" id="editAccountName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Kategori Sponsor (Opsional)</label>
                        <select name="SponsorId" id="editSponsorId" class="form-control">
                            <option value="">-- Kas Umum (Bukan Sponsor) --</option>
                            <?php foreach ($sponsors as $s): ?>
                                <option value="<?= $s['SponsorId'] ?>"><?= htmlspecialchars($s['SponsorName']) ?></option>
                            <?php endforeach; ?>
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

<!-- Modal History Account -->
<div class="modal fade" id="modalHistoryAccount" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                <h5 class="modal-title font-weight-bold">History Kas: <span id="historyAccountName"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Date Filter for History -->
                <div class="row mb-3 bg-light p-2 rounded shadow-sm mx-0">
                    <div class="col-md-5">
                        <div class="form-group mb-0">
                            <label class="small font-weight-bold text-muted uppercase">DARI</label>
                            <input type="date" id="histStartDate" class="form-control form-control-sm border-0 shadow-sm">
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="form-group mb-0">
                            <label class="small font-weight-bold text-muted uppercase">SAMPAI</label>
                            <input type="date" id="histEndDate" class="form-control form-control-sm border-0 shadow-sm">
                        </div>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="btn-group w-100" style="gap: 5px;">
                            <button type="button" id="btnFilterHistory" class="btn btn-primary btn-sm shadow-sm" title="Apply Filter">
                                <i class="fas fa-filter"></i>
                            </button>
                            <button type="button" id="btnResetHistory" class="btn btn-light btn-sm border shadow-sm" title="Reset Filter">
                                <i class="fas fa-undo text-secondary"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="historyDataTable" class="table table-hover table-sm w-100">
                        <thead class="bg-light">
                            <tr>
                                <th>Tanggal</th>
                                <th>Keterangan</th>
                                <th>Tipe</th>
                                <th class="text-right">Nominal</th>
                                <th>Oleh</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data populated by DataTables Server-side -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Transfer Saldo -->
<div class="modal fade" id="modalTransfer" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="accounts_action.php" method="POST">
            <input type="hidden" name="action" value="transfer">
            <div class="modal-content">
                <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                    <h5 class="modal-title font-weight-bold">Transfer Saldo Antar Akun</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Dari Akun (Sumber)</label>
                        <div class="p-3 border rounded bg-light d-flex align-items-center">
                            <i class="fas fa-wallet mr-3 text-secondary fa-lg"></i>
                            <div>
                                <div id="transferSourceLabel" class="font-weight-bold text-dark">-</div>
                                <small id="transferBalanceLabel" class="text-primary font-weight-bold"></small>
                            </div>
                            <input type="hidden" name="SourceAccountId" id="transferSourceId">
                        </div>
                    </div>
                    <div class="form-group text-center">
                        <i class="fas fa-arrow-down fa-2x text-muted"></i>
                    </div>
                    <div class="form-group">
                        <label>Ke Akun (Tujuan) <span class="text-danger">*</span></label>
                        <select name="DestAccountId" id="transferDestSelect" class="form-control" required>
                            <option value="">-- Pilih Akun Tujuan --</option>
                            <?php foreach ($accounts as $a): ?>
                                <option value="<?= $a['AccountId'] ?>"><?= htmlspecialchars($a['AccountName']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <hr>
                    <div class="form-group">
                        <label>Nominal Transfer (Rp) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text font-weight-bold">Rp</span></div>
                            <input type="text" class="form-control rupiah-input" placeholder="0" required>
                            <input type="hidden" name="Amount" class="raw-value">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Keterangan / Catatan</label>
                        <textarea name="Note" class="form-control" rows="2" placeholder="Contoh: Pemindahan dana untuk operasional..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-info text-white">Proses Transfer <i class="fas fa-paper-plane ml-1"></i></button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Print Ledger -->
<div class="modal fade" id="modalPrintLedger" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <form action="accounts_print.php" method="GET" target="_blank">
            <input type="hidden" name="id" id="printAccountId">
            <div class="modal-content">
                <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                    <h5 class="modal-title font-weight-bold"><i class="fas fa-print mr-2"></i>Cetak Mutasi</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p class="text-muted text-sm text-center mb-3">Pilih periode laporan mutasi untuk akun <b id="printAccountName"></b></p>
                    <div class="form-group">
                        <label>Dari Tanggal</label>
                        <input type="date" name="start" class="form-control" value="<?= date('Y-m-01') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Sampai Tanggal</label>
                        <input type="date" name="end" class="form-control" value="<?= date('Y-m-t') ?>" required>
                    </div>
                </div>
                <div class="modal-footer justify-content-center bg-light">
                    <button type="submit" class="btn btn-danger btn-block font-weight-bold">
                        <i class="fas fa-file-pdf mr-1"></i> Download PDF
                    </button>
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
    // Rupiah Formatting Logic
    $('.rupiah-input').on('keyup', function(){
        var val = $(this).val().replace(/[^0-9]/g, '');
        $(this).siblings('.raw-value').val(val);
        $(this).val(formatRupiah(val));
    });

    function formatRupiah(angka) {
        var number_string = angka.toString(),
            split = number_string.split(','),
            sisa = split[0].length % 3,
            rupiah = split[0].substr(0, sisa),
            ribuan = split[0].substr(sisa).match(/\d{3}/gi);

        if (ribuan) {
            separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }
        return split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
    }

    // Topup Modal Logic
    $('.btn-topup').on('click', function(){
        $('#topupAccountId').val($(this).data('id'));
        $('#topupAccountName').text($(this).data('name'));
        $('#modalTopupAccount').modal('show');
    });

    // History Modal Logic with DataTables
    var historyTable = null;
    $('.btn-history').on('click', function(){
        var id = $(this).data('id');
        $('#historyAccountName').text($(this).data('name'));
        $('#modalHistoryAccount').modal('show');
        
        if (historyTable) {
            historyTable.ajax.url('accounts_history_fetch.php?AccountId=' + id).load();
        } else {
            historyTable = $("#historyDataTable").DataTable({
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "accounts_history_fetch.php",
                    "type": "POST",
                    "data": function(d) {
                        d.AccountId = id; 
                        d.startDate = $('#histStartDate').val();
                        d.endDate = $('#histEndDate').val();
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
                }
            });
        }
    });

    // Handle History Filter Button
    $(document).on('click', '#btnFilterHistory', function(){
        if(historyTable) {
            $(this).html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
            historyTable.ajax.reload(() => {
                $(this).html('<i class="fas fa-filter"></i>').prop('disabled', false);
            });
        }
    });

    // Handle History Reset Button
    $(document).on('click', '#btnResetHistory', function(){
        $('#histStartDate').val('');
        $('#histEndDate').val('');
        if(historyTable) {
            historyTable.ajax.reload();
        }
    });

    // Reset table when modal closed to ensure ID update
    $('#modalHistoryAccount').on('hidden.bs.modal', function () {
        // Optional: you could also destroy it, but reloading with new URL is better
    });

    // Print Button
    $('.btn-print').click(function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        $('#printAccountId').val(id);
        $('#printAccountName').text(name);
        $('#modalPrintLedger').modal('show');
    });

    // Edit Modal Logic
    $('.btn-edit').on('click', function(){
        $('#editAccountId').val($(this).data('id'));
        $('#editAccountName').val($(this).data('name'));
        $('#editSponsorId').val($(this).data('sponsor'));
        $('#modalEditAccount').modal('show');
    });

    // Transfer Modal Logic
    $('.btn-transfer').on('click', function(){
        var sourceId = $(this).data('id');
        $('#transferSourceId').val(sourceId);
        $('#transferSourceLabel').text($(this).data('name'));
        $('#transferBalanceLabel').text("Sisa Saldo: " + $(this).data('balance'));
        
        // Disable Source Account in Destination Dropdown
        $('#transferDestSelect option').each(function() {
            if ($(this).val() == sourceId) {
                $(this).prop('disabled', true).hide();
            } else {
                $(this).prop('disabled', false).show();
            }
        });
        $('#transferDestSelect').val(''); // Reset selection

        $('#modalTransfer').modal('show');
    });

    // Delete Action Logic
    $('.btn-delete').on('click', function(){
        var id = $(this).data('id');
        var name = $(this).data('name');
        Swal.fire({
            title: 'Hapus Akun?',
            text: "Anda akan menghapus akun '" + name + "'. Pastikan akun tidak memiliki saldo atau history transaksi.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'accounts_action.php?action=delete&id=' + id;
            }
        });
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
