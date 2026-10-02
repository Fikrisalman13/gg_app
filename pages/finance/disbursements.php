<?php
// disbursements.php - Finance interface to release funds
session_start();
ob_start();
require_once __DIR__ . '/../../koneksi.php';

date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

// Note: Data fetching is now handled server-side via disbursements_fetch.php

$themeColor = $_SESSION['Theme'] ?? 'primary';

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">

<style>
    /* Premium DataTable Styling */
    #disburseTable_wrapper .dataTables_filter input {
        border-radius: 20px;
        padding: 5px 15px;
        border: 1px solid #ddd;
        transition: all 0.3s;
        background: #fdfdfd;
    }
    #disburseTable_wrapper .dataTables_filter input:focus {
        border-color: #007bff;
        box-shadow: 0 0 8px rgba(0,123,255,0.15);
        outline: none;
        background: #fff;
    }
    #disburseTable_wrapper .dataTables_length select {
        border-radius: 8px;
        border: 1px solid #ddd;
        padding: 5px 10px;
        margin: 0 8px;
        width: auto;
        min-width: 60px;
        cursor: pointer;
        background: #fff;
    }
    #disburseTable_wrapper .dataTables_info {
        color: #8898aa;
        font-size: 0.85rem;
        font-weight: 500;
    }
    #disburseTable {
        border-collapse: separate !important;
        border-spacing: 0 12px !important;
        border: none !important;
        margin-top: 10px !important;
    }
    #disburseTable thead th {
        border: none !important;
        background-color: transparent !important;
        color: #8898aa;
        text-transform: uppercase;
        font-size: 0.7rem;
        letter-spacing: 1.5px;
        font-weight: 800;
        padding: 10px 15px !important;
    }
    #disburseTable tbody tr {
        background-color: #fff !important;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        transition: all 0.25s ease;
        border-radius: 12px;
    }
    #disburseTable tbody tr:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        background-color: #fff !important;
    }
    #disburseTable tbody td {
        border: none !important;
        padding: 18px 15px !important;
        vertical-align: middle !important;
    }
    #disburseTable tbody td:first-child { border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
    #disburseTable tbody td:last-child { border-top-right-radius: 12px; border-bottom-right-radius: 12px; }
    
    .badge-pill { border-radius: 50px; padding: 6px 14px; font-size: 0.75rem; letter-spacing: 0.5px; }
    .text-indigo { color: #5e72e4 !important; }

    /* Custom Select2 for Disbursement */
    .select2-container--bootstrap4 .select2-selection--single {
        height: auto !important;
        min-height: 54px !important;
        padding: 4px 12px !important;
        border-radius: 12px !important;
        display: flex !important;
        align-items: center !important;
        border: 1px solid #d1d9e6 !important;
        background-color: #fff !important;
    }
    .select2-container--bootstrap4 .select2-selection__rendered {
        line-height: 1.3 !important;
        padding: 0 !important;
        width: 100% !important;
        display: block !important;
    }
    .select2-container--bootstrap4 .select2-selection__arrow {
        top: 50% !important;
        transform: translateY(-50%) !important;
        height: 20px !important;
    }
    .account-item { 
        display: flex; 
        flex-direction: column; 
        padding-right: 5px;
    }
    .account-item .acc-name { 
        font-weight: 800; 
        color: #2d3436; 
        font-size: 0.95rem; 
        display: block;
    }
    .account-item .acc-balance { 
        font-size: 0.8rem; 
        color: #2fb344; 
        font-weight: 700; 
        display: block;
        margin-top: 1px;
    }
    /* Style for dropdown results */
    .select2-results__option {
        padding: 10px 15px !important;
        border-bottom: 1px solid #f1f4f8;
    }
    .select2-results__option--highlighted {
        background-color: #f8f9fe !important;
        color: inherit !important;
    }
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1 class="m-0 text-dark">Pencairan Dana (Disbursement)</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                        <li class="breadcrumb-item active">Disbursement</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
                <div class="card shadow-sm border-0" style="border-radius: 15px; overflow: hidden;">
                    <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white d-flex align-items-center py-3">
                        <h3 class="card-title mr-auto font-weight-bold"><i class="fas fa-money-bill-wave mr-2"></i>Daftar Siap Cair (Approved)</h3>
                    </div>
                    <div class="card-body" style="background-color: #f8f9fe;">
                        <table id="disburseTable" class="table w-100">
                            <thead>
                                <tr>
                                    <th width="60">ID</th>
                                    <th>Pengaju</th>
                                    <th>Unit</th>
                                    <th>Judul & Nominal</th>
                                    <th width="250">Rekening Sumber Antrian</th>
                                    <th width="150" class="text-center">Aksi</th>
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

<?php include '../../includes/footer.php'; ?>

<!-- DataTables & Plugins -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
$(function(){
    // Custom Select2 Dynamic Format
    function formatAccount(state) {
        if (!state.id) return state.text;
        var name = $(state.element).data('name');
        var balance = $(state.element).data('balance');
        if(!name) return state.text;
        
        return $(
            '<div class="account-item">' +
                '<span class="acc-name">' + name + '</span>' +
                '<span class="acc-balance">Saldo: ' + balance + '</span>' +
            '</div>'
        );
    }

    function initSelect2() {
        $(".select-account").select2({
            theme: 'bootstrap4',
            templateResult: formatAccount,
            templateSelection: formatAccount,
            width: '100%'
        });
    }

    // Initialize DataTable AJAX
    var table = $("#disburseTable").DataTable({ 
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "disbursements_fetch.php",
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
        },
        "drawCallback": function() {
            initSelect2(); // Re-initialize Select2 after each table redraw
        }
    });

    // Auto Refresh Table every 5 seconds
    setInterval(function(){
        if (!$('.swal2-container').is(':visible')) {
            table.ajax.reload(null, false);
        }
    }, 5000);

    $(document).on('click', '.btn-pay', function(){
        var id = $(this).data('id');
        var amount = $(this).data('amount');
        var accId = $('#acc_' + id).val();

        if(!accId) {
            Swal.fire('Gagal!', 'Pilih rekening asal dana terlebih dahulu.', 'error');
            return;
        }

        Swal.fire({
            title: 'Konfirmasi Pembayaran',
            text: "Pastikan dana sebesar Rp " + Number(amount).toLocaleString('id-ID') + " sudah disiapkan/ditransfer.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Bayar Sekarang!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'disbursement_action.php',
                    type: 'POST',
                    data: { RequestId: id, AccountId: accId },
                    dataType: 'json',
                    success: function(res) {
                        if(res.status === 'success') {
                            Swal.fire('Berhasil!', res.message, 'success').then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire('Gagal!', res.message, 'error');
                        }
                    }
                });
            }
        });
    });
});
</script>
