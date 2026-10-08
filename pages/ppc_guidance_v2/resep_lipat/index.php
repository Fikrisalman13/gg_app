<?php
// pages/resep_lipat/index.php
session_start();

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../koneksi.php';

date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu!';
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
$themeColor = strtolower($themeColor);

include '../../../includes/header.php';
include '../../../includes/sidebar.php';
?>

<link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">

<style>
    .required-dot {
        color: #dc2626;
        font-weight: 700;
    }

    .status-master {
        color: #dc2626;
        font-style: italic;
        font-weight: 700;
    }

    .status-badge {
        display: inline-block;
        font-size: .75rem;
        font-weight: 600;
        padding: .25rem .6rem;
        border-radius: 4px;
    }

    .status-master-resep {
        background: #28a745;
        color: #fff;
    }

    .status-shading {
        background: #ffc107;
        color: #1f2d3d;
    }

    .status-top-paddry {
        background: #17a2b8;
        color: #fff;
    }

    .status-top-cpb {
        background: #6c757d;
        color: #fff;
    }

    .status-default {
        background: #e3f2fd;
        color: #1976d2;
    }

    #resepLipatTable tbody tr {
        background-color: #ffffff !important;
    }

    .resep-table-toolbar {
        align-items: center;
        display: flex;
        gap: .5rem;
        justify-content: space-between;
        margin-bottom: .75rem;
    }

    .selected-counter {
        background: #e3f2fd;
        border: 1px solid #90caf9;
        color: #1565c0;
        font-size: .8rem;
        font-weight: 600;
        padding: .35rem .75rem;
    }

    .detail-placeholder-btn {
        min-width: 74px;
    }

    @media (max-width: 767.98px) {
        .resep-table-toolbar {
            align-items: stretch;
            flex-direction: column;
        }
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0"><i class="fas fa-layer-group mr-2"></i>Resep Lipat</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Resep Lipat</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card mb-3">
                <div class="card-header bg-<?= htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8'); ?> text-white">
                    <h3 class="card-title mb-0"><i class="fas fa-filter mr-1"></i> Filter Pencarian</h3>
                </div>
                <div class="card-body">
                    <form id="filterForm" autocomplete="off">
                        <div class="row">
                            <div class="col-lg-4 col-md-6 mb-3">
                                <label for="custcolor">Kode Warna Cust <span class="required-dot">*</span></label>
                                <input type="text" class="form-control" id="custcolor" name="custcolor"
                                    placeholder="Contoh: 0242" maxlength="80">
                            </div>
                            <div class="col-lg-4 col-md-6 mb-3">
                                <label for="colorcode">Kode Warna/Lab</label>
                                <input type="text" class="form-control" id="colorcode" name="colorcode"
                                    placeholder="Opsional">
                            </div>

                        </div>
                        <div class="d-flex flex-wrap align-items-center" style="gap:.5rem;">
                            <button type="submit" id="btnCari"
                                class="btn btn-<?= htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fas fa-search mr-1"></i> Cari
                            </button>
                            <button type="button" id="btnReset" class="btn btn-outline-secondary">
                                <i class="fas fa-undo mr-1"></i> Reset
                            </button>
                            <span class="text-muted small ml-md-2">Data baru dimuat setelah tombol <b>Cari</b>
                                diklik.</span>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8'); ?> text-white">
                    <h3 class="card-title mb-0"><i class="fas fa-table mr-1"></i> Hasil Data Resep Lipat</h3>
                </div>
                <div class="card-body">
                    <div class="resep-table-toolbar">
                        <div class="selected-counter" id="selectedCounter"><i class="fas fa-check-square mr-1"></i> 0
                            data dipilih</div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnBulkPlaceholder" disabled>
                            <i class="fas fa-tasks mr-1"></i> Bulk Action (coming soon)
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table id="resepLipatTable" class="table table-bordered table-hover table-sm nowrap"
                            style="width:100%;">
                            <thead>
                                <tr>
                                    <th style="width:38px;"><input type="checkbox" id="selectAllRows"
                                            title="Pilih semua halaman ini"></th>
                                    <th>No</th>
                                    <th>Kode Warna/Lab</th>
                                    <th>Nama Warna</th>
                                    <th>Kode Warna Cust</th>
                                    <th>Tgl Resep</th>
                                    <th>Ver</th>
                                    <th>Tipe Resep</th>
                                    <th>Process</th>
                                    <th>Kode Produk</th>
                                    <th>Nama Produk</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include '../../../includes/footer.php'; ?>

<script src="/gg_app/plugins/js/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/js/datatables/dataTables.bootstrap5.min.js"></script>
<script src="/gg_app/plugins/js/datatables/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
    $(function () {
        var selectedRows = {};
        var hasSearched = false;

        function escapeHtml(value) {
            return $('<div>').text(value == null ? '' : value).html();
        }

        // Buat unique key per baris: gabungan id + row index dari server
        function rowKey(id, meta) {
            return id + '_' + (meta.row + meta.settings._iDisplayStart);
        }

        function updateSelectedCounter() {
            var count = Object.keys(selectedRows).length;
            $('#selectedCounter').html('<i class="fas fa-check-square mr-1"></i> ' + count + ' data dipilih');
            $('#btnBulkPlaceholder').prop('disabled', count === 0);
        }

        function showRequiredCuscolorMessage() {
            Swal.fire({
                icon: 'warning',
                title: 'Kode Warna Cust wajib diisi',
                text: 'Silakan isi Kode Warna Cust terlebih dahulu sebelum mencari data.',
                confirmButtonText: 'Mengerti',
                confirmButtonColor: '#3085d6'
            });
        }

        var table = $('#resepLipatTable').DataTable({
            processing: true,
            serverSide: true,
            responsive: false,
            autoWidth: false,
            scrollX: true,
            searching: true,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            order: [[4, 'desc']],
            ajax: {
                url: 'resep_lipat_serverside.php',
                type: 'POST',
                data: function (d) {
                    if (!hasSearched) {
                        d.cuscolor = '';
                        return;
                    }
                    d.cuscolor = $.trim($('#custcolor').val());
                    d.colorcode = $.trim($('#colorcode').val());
                    d.processcode = $.trim($('#processcode').val());
                },
                error: function (xhr) {
                    var message = 'Gagal memuat data. Silakan cek koneksi atau hubungi IT.';
                    if (xhr && xhr.responseJSON && xhr.responseJSON.error) {
                        message = xhr.responseJSON.error;
                    }
                    Swal.fire({
                        icon: 'error',
                        title: 'Data gagal dimuat',
                        text: message,
                        confirmButtonText: 'Tutup',
                        confirmButtonColor: '#dc3545'
                    });
                }
            },
            columns: [
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    render: function (data, type, row, meta) {
                        var key = rowKey(data, meta);
                        var checked = selectedRows[key] ? 'checked' : '';
                        return '<input type="checkbox" class="row-select" data-key="' + escapeHtml(key) + '" ' + checked + '>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-center font-weight-bold',
                    render: function (data, type, row, meta) {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    }
                },
                { data: 'colorcode', render: $.fn.dataTable.render.text() },
                { data: 'colorname', render: $.fn.dataTable.render.text() },
                { data: 'cuscolor', className: 'text-center', render: $.fn.dataTable.render.text() },
                { data: 'resepdate', className: 'text-center', render: $.fn.dataTable.render.text() },
                { data: 'resepseq', className: 'text-center', render: $.fn.dataTable.render.text() },
                { data: 'reseptype', className: 'text-center', render: $.fn.dataTable.render.text() },
                { data: 'processcode', className: 'text-center', render: $.fn.dataTable.render.text() },
                { data: 'resepprodcode', render: $.fn.dataTable.render.text() },
                { data: 'resepprodname', render: $.fn.dataTable.render.text() },
                 {
                    data: 'statusdesc',
                    className: 'text-center',
                    render: function (data) {
                        var label = escapeHtml(data || '-');
                        var lowerData = (data || '').toLowerCase();
                        if (lowerData === 'master resep' || lowerData === 'master') {
                            return '<span class="status-badge status-master-resep">' + label + '</span>';
                        } else if (lowerData === 'shading') {
                            return '<span class="status-badge status-shading">' + label + '</span>';
                        } else if (lowerData === 'top paddry') {
                            return '<span class="status-badge status-top-paddry">' + label + '</span>';
                        } else if (lowerData === 'top cpb') {
                            return '<span class="status-badge status-top-cpb">' + label + '</span>';
                        }
                        return '<span class="status-badge status-default">' + label + '</span>';
                    }
                },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    render: function (data) {
                        return '<button type="button" class="btn btn-info btn-sm detail-placeholder-btn btn-detail" data-id="' + escapeHtml(data) + '" title="Detail"><i class="fas fa-eye mr-1"></i> Detail</button>';
                    }
                }
            ],
            drawCallback: function () {
                // Setelah halaman di-render, cek apakah semua checkbox di halaman ini sudah terpilih
                var allChecked = $('.row-select').length > 0 && $('.row-select').length === $('.row-select:checked').length;
                $('#selectAllRows').prop('checked', allChecked);
            },
            language: {
                processing: 'Memuat data...',
                search: 'Cari:',
                lengthMenu: 'Tampilkan _MENU_ data',
                info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
                infoEmpty: 'Tidak ada data',
                infoFiltered: '(difilter dari _MAX_ total data)',
                zeroRecords: hasSearched ? 'Data tidak ditemukan' : 'Isi filter lalu klik Cari',
                paginate: {
                    first: 'Pertama',
                    last: 'Terakhir',
                    next: 'Berikutnya',
                    previous: 'Sebelumnya'
                }
            }
        });

        $('#filterForm').on('submit', function (e) {
            e.preventDefault();
            if ($.trim($('#custcolor').val()) === '') {
                showRequiredCuscolorMessage();
                $('#custcolor').focus();
                return;
            }
            hasSearched = true;
            selectedRows = {};
            updateSelectedCounter();
            table.ajax.reload();
        });

        $('#btnReset').on('click', function () {
            $('#filterForm')[0].reset();
            selectedRows = {};
            hasSearched = false;
            updateSelectedCounter();
            table.search('').draw();
        });

        // Per-row checkbox: simpan/hapus berdasarkan unique key
        $(document).on('change', '.row-select', function () {
            var key = $(this).data('key');
            if ($(this).is(':checked')) {
                selectedRows[key] = true;
            } else {
                delete selectedRows[key];
            }
            updateSelectedCounter();

            // Sync selectAll checkbox
            var allChecked = $('.row-select').length > 0 && $('.row-select').length === $('.row-select:checked').length;
            $('#selectAllRows').prop('checked', allChecked);
        });

        // Select all: hanya berlaku untuk halaman saat ini
        $('#selectAllRows').on('change', function () {
            var checked = $(this).is(':checked');
            $('.row-select').each(function () {
                var key = $(this).data('key');
                $(this).prop('checked', checked);
                if (checked) {
                    selectedRows[key] = true;
                } else {
                    delete selectedRows[key];
                }
            });
            updateSelectedCounter();
        });

        $(document).on('click', '.btn-detail', function () {
            var id = $(this).data('id');
            if (!id) {
                Swal.fire({
                    icon: 'error',
                    title: 'ID tidak valid',
                    text: 'ID resep tidak ditemukan.',
                    confirmButtonText: 'Tutup',
                    confirmButtonColor: '#dc3545'
                });
                return;
            }
            window.location.href = 'detail.php?id=' + encodeURIComponent(id);
        });

        $('#btnBulkPlaceholder').on('click', function () {
            Swal.fire({
                icon: 'info',
                title: 'Bulk action belum tersedia',
                text: 'Multiple select sudah disiapkan untuk pengembangan bulk action berikutnya.',
                confirmButtonText: 'Tutup',
                confirmButtonColor: '#3085d6'
            });
        });

        updateSelectedCounter();
    });
</script>