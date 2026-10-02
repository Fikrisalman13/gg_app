<?php
// pending_approvals.php - Interface for approvers to see tasks
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

// Note: Data fetching is now handled server-side via pending_approvals_fetch.php

$themeColor = $_SESSION['Theme'] ?? 'primary';

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">

<style>
    /* Premium DataTable Styling */
    #pendingTable_wrapper .dataTables_filter input {
        border-radius: 20px;
        padding: 5px 15px;
        border: 1px solid #ddd;
        transition: all 0.3s;
        background: #fdfdfd;
    }
    #pendingTable_wrapper .dataTables_filter input:focus {
        border-color: #007bff;
        box-shadow: 0 0 8px rgba(0,123,255,0.15);
        outline: none;
        background: #fff;
    }
    #pendingTable_wrapper .dataTables_length select {
        border-radius: 8px;
        border: 1px solid #ddd;
        padding: 5px 10px;
        margin: 0 8px;
        width: auto;
        min-width: 60px;
        cursor: pointer;
        background: #fff;
    }
    #pendingTable_wrapper .dataTables_info {
        color: #8898aa;
        font-size: 0.85rem;
        font-weight: 500;
    }
    #pendingTable {
        border-collapse: separate !important;
        border-spacing: 0 12px !important;
        border: none !important;
        margin-top: 10px !important;
    }
    #pendingTable thead th {
        border: none !important;
        background-color: transparent !important;
        color: #8898aa;
        text-transform: uppercase;
        font-size: 0.7rem;
        letter-spacing: 1.5px;
        font-weight: 800;
        padding: 10px 15px !important;
    }
    #pendingTable tbody tr {
        background-color: #fff !important;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        transition: all 0.25s ease;
        border-radius: 12px;
    }
    #pendingTable tbody tr:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        background-color: #fff !important;
    }
    #pendingTable tbody td {
        border: none !important;
        padding: 18px 15px !important;
        vertical-align: middle !important;
    }
    #pendingTable tbody td:first-child { border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
    #pendingTable tbody td:last-child { border-top-right-radius: 12px; border-bottom-right-radius: 12px; }
    
    .badge-pill { border-radius: 50px; padding: 6px 14px; font-size: 0.75rem; letter-spacing: 0.5px; }
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1 class="m-0 text-dark">Persetujuan Pending (Approvals)</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                        <li class="breadcrumb-item active">Pending Approvals</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
                <div class="card shadow-sm border-0" style="border-radius: 15px; overflow: hidden;">
                    <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white d-flex align-items-center py-3">
                        <h3 class="card-title mr-auto font-weight-bold"><i class="fas fa-tasks mr-2"></i>Antrian Approval Pending</h3>
                    </div>
                    <div class="card-body" style="background-color: #f8f9fe;">
                        <table id="pendingTable" class="table w-100">
                            <thead>
                                <tr>
                                    <th width="60">ID</th>
                                    <th>Pengaju</th>
                                    <th>Unit & Kategori</th>
                                    <th>Judul Pengajuan</th>
                                    <th>Nominal</th>
                                    <th>Tahapan Saat Ini</th>
                                    <th width="120" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data populated by DataTables AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
        </div>
    </div>
</div>

<!-- Modal Approval Action -->
<div class="modal fade" id="modalAction" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="approval_action.php" method="POST">
            <input type="hidden" name="RequestId" id="actionRequestId">
            <input type="hidden" name="Action" id="actionType">
            <div class="modal-content">
                <div id="modalHeader" class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                    <h5 class="modal-title font-weight-bold" id="modalTitleText">Konfirmasi Persetujuan</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p id="actionPromptText">Apakah Anda yakin ingin menyetujui pengajuan ini?</p>
                    <div class="form-group">
                        <label>Catatan / Alasan (Opsional)</label>
                        <textarea name="Note" class="form-control" rows="3" placeholder="Tambahkan catatan jika diperlukan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" id="btnSubmitAction" class="btn">Proses</button>
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
    // Initialize DataTable AJAX
    var table = $("#pendingTable").DataTable({ 
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "pending_approvals_fetch.php",
            "type": "POST"
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

    // Auto Refresh Table every 5 seconds
    setInterval(function(){
        if (!$('#modalAction').is(':visible') && !$('.swal2-container').is(':visible')) {
            table.ajax.reload(null, false);
        }
    }, 5000);

    $(document).on('click', '.btn-action-trigger', function(){
        var id = $(this).data('id');
        var action = $(this).data('action');
        var title = $(this).data('title');

        $('#actionRequestId').val(id);
        $('#actionType').val(action);
        
        if(action === 'Approve') {
            $('#modalTitleText').text('Approve Pengajuan');
            $('#actionPromptText').html('Setujui pengajuan: <strong>' + title + '</strong>?');
            $('#btnSubmitAction').removeClass('btn-danger').addClass('btn-success').text('Ya, Setujui');
        } else {
            $('#modalTitleText').text('Reject Pengajuan');
            $('#actionPromptText').html('Tolak pengajuan: <strong>' + title + '</strong>?');
            $('#btnSubmitAction').removeClass('btn-success').addClass('btn-danger').text('Ya, Tolak');
        }

        $('#modalAction').modal('show');
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
