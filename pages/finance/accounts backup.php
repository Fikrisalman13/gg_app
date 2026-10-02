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
                <div class="card-header bg-navy text-white d-flex align-items-center">
                    <h3 class="card-title mr-auto"><i class="fas fa-wallet mr-2"></i>Daftar Rekening/Kas</h3>
                    <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#modalAddAccount">
                        <i class="fas fa-plus mr-1"></i> Tambah Kas
                    </button>
                </div>
                <div class="card-body">
                    <div class="row">
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
                                    <button class="btn btn-primary btn-xs btn-history" data-id="<?= $a['AccountId'] ?>" data-name="<?= htmlspecialchars($a['AccountName']) ?>">
                                        <i class="fas fa-history mr-1"></i> History
                                    </button>
                                    <button class="btn btn-info btn-xs btn-edit" 
                                            data-id="<?= $a['AccountId'] ?>" 
                                            data-name="<?= htmlspecialchars($a['AccountName']) ?>"
                                            data-sponsor="<?= $a['SponsorId'] ?>">
                                        <i class="fas fa-edit mr-1"></i> Update
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
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
                <div class="modal-header bg-success text-white">
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
                <div class="modal-header bg-success text-white">
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
                <div class="modal-header bg-info text-white">
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
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold">History Kas: <span id="historyAccountName"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-0">
                <div id="historyTableContainer" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover table-sm m-0">
                        <thead class="bg-light sticky-top">
                            <tr>
                                <th>Tanggal</th>
                                <th>Keterangan</th>
                                <th>Tipe</th>
                                <th class="text-right">Nominal</th>
                                <th>Oleh</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody">
                            <!-- Data loaded via JS -->
                        </tbody>
                    </table>
                </div>
                <div id="historyLoading" class="text-center p-4 d-none">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted">Memuat data...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
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

    // History Modal Logic
    $('.btn-history').on('click', function(){
        var id = $(this).data('id');
        $('#historyAccountName').text($(this).data('name'));
        $('#modalHistoryAccount').modal('show');
        
        // Fetch data
        $('#historyTableBody').html('');
        $('#historyLoading').removeClass('d-none');
        
        $.get('get_account_history.php?id=' + id, function(res){
            $('#historyLoading').addClass('d-none');
            if(res.status === 'success') {
                let html = '';
                if(res.data.length === 0) {
                    html = '<tr><td colspan="5" class="text-center p-4 text-muted">Belum ada transaksi di akun ini.</td></tr>';
                } else {
                    res.data.forEach(row => {
                        let badge = row.Type === 'IN' ? 'badge-success' : 'badge-danger';
                        let prefix = row.Type === 'IN' ? '+' : '-';
                        html += `<tr>
                            <td>${row.FormattedDate}</td>
                            <td>
                                <div>${row.Description}</div>
                                ${row.RequestTitle ? '<small class="text-info font-italic">#' + row.RequestId + ' ' + row.RequestTitle + '</small>' : ''}
                            </td>
                            <td><span class="badge ${badge}">${row.Type}</span></td>
                            <td class="text-right font-weight-bold text-${row.Type === 'IN' ? 'success' : 'danger'}">${prefix} ${row.FormattedAmount}</td>
                            <td><small>${row.UserName}</small></td>
                        </tr>`;
                    });
                }
                $('#historyTableBody').html(html);
            } else {
                Swal.fire('Gagal!', 'Tidak bisa memuat history.', 'error');
            }
        });
    });

    // Edit Modal Logic
    $('.btn-edit').on('click', function(){
        $('#editAccountId').val($(this).data('id'));
        $('#editAccountName').val($(this).data('name'));
        $('#editSponsorId').val($(this).data('sponsor'));
        $('#modalEditAccount').modal('show');
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
