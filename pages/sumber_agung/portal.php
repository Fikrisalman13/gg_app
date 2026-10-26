<?php
/**
 * Web Portal Customer - Sumber Agung
 * Halaman full-page (tanpa sidebar) untuk customer Sumber Agung
 * melihat data titipan DO, download / preview Packing List dan Surat Jalan.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/koneksi.php';

// Cek autentikasi login
if (!isset($_SESSION['UserId']) || empty($_SESSION['UserId'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu untuk mengakses portal.";
    header("Location: /gg_app/login.php");
    exit;
}

$userName = htmlspecialchars($_SESSION['UserName'] ?? 'Customer');
$namaLengkap = htmlspecialchars($_SESSION['NamaLengkap'] ?? $userName);
$theme = strtolower($_SESSION['Theme'] ?? 'primary');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Customer — Sumber Agung</title>
    <link rel="icon" href="/gg_app/dist/img/sumlogo.png" type="image/x-icon">

    <!-- Google Font: Inter / Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">

    <!-- AdminLTE & FontAwesome -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">

    <!-- DataTables -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">

    <!-- SweetAlert2 & Toastr -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/toastr/toastr.min.css">

    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f1f5f9;
            color: #1e293b;
        }

        /* Navbar Portal */
        .portal-navbar {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 12px 24px;
        }
        .portal-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #ffffff !important;
            text-decoration: none;
        }
        .portal-brand img {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            background: #ffffff;
            padding: 2px;
        }
        .portal-brand-text h1 {
            font-size: 1.15rem;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
            color: #ffffff;
        }
        .portal-brand-text small {
            font-size: 11px;
            color: #94a3b8;
            font-weight: 400;
        }

        /* Cards & Components */
        .portal-card {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            margin-bottom: 24px;
            overflow: hidden;
        }
        .portal-card-header {
            padding: 18px 24px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Document Badges & Buttons */
        .btn-view-doc {
            background-color: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            font-weight: 600;
            font-size: 11.5px;
            padding: 5px 10px;
            border-radius: 6px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-view-doc:hover {
            background-color: #2563eb;
            color: #ffffff;
            border-color: #2563eb;
            transform: translateY(-1px);
        }

        .btn-view-doc-green {
            background-color: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
            font-weight: 600;
            font-size: 11.5px;
            padding: 5px 10px;
            border-radius: 6px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-view-doc-green:hover {
            background-color: #059669;
            color: #ffffff;
            border-color: #059669;
            transform: translateY(-1px);
        }

        .table-portal th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 600;
            font-size: 12.5px;
            vertical-align: middle;
            border-top: none;
        }
        .table-portal td {
            font-size: 13px;
            vertical-align: middle;
        }

        /* Hero banner */
        .hero-banner {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            border-radius: 14px;
            padding: 24px 28px;
            color: #ffffff;
            margin-bottom: 24px;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.2);
            position: relative;
            overflow: hidden;
        }
        .hero-banner::after {
            content: '\f48b';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            right: 20px;
            bottom: -20px;
            font-size: 120px;
            opacity: 0.1;
            color: #ffffff;
        }
    </style>
</head>
<body>

    <!-- PORTAL NAVBAR -->
    <nav class="navbar portal-navbar sticky-top">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <a href="/gg_app/pages/sumber_agung/portal.php" class="portal-brand">
                <img src="/gg_app/dist/img/sumlogo.png" alt="Logo SUM">
                <div class="portal-brand-text">
                    <h1>SUMBER AGUNG</h1>
                    <small>Portal Dokumen Titipan Delivery Order</small>
                </div>
            </a>

            <div class="d-flex align-items-center">
                <div class="mr-3 text-right d-none d-sm-block">
                    <div class="text-white font-weight-bold" style="font-size: 13px;">
                        <i class="fas fa-user-circle text-primary mr-1"></i> <?= $namaLengkap ?>
                    </div>
                    <small class="text-muted" style="font-size: 11px;">Customer Portal</small>
                </div>
                <a href="/gg_app/logout.php" class="btn btn-outline-danger btn-sm font-weight-bold px-3" title="Keluar dari portal">
                    <i class="fas fa-sign-out-alt mr-1"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- MAIN WRAPPER -->
    <div class="container-fluid px-md-4 py-4">

        <!-- HERO BANNER -->
        <div class="hero-banner">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="font-weight-bold mb-1" style="font-size: 1.6rem;">Selamat Datang, <?= $namaLengkap ?>!</h2>
                    <p class="mb-0 text-white-50" style="font-size: 13.5px;">
                        Halaman ini memuat daftar pengiriman titipan Delivery Order (DO) beserta dokumen Packing List dan Surat Jalan resmi.
                    </p>
                </div>
                <div class="col-md-4 text-md-right mt-3 mt-md-0">
                    <button type="button" class="btn btn-light font-weight-bold text-primary shadow-sm" id="btnRefreshPortal">
                        <i class="fas fa-sync-alt mr-1"></i> Perbarui Data
                    </button>
                </div>
            </div>
        </div>

        <!-- FILTER CARD -->
        <div class="portal-card">
            <div class="card-body py-3 px-4">
                <form id="portalFilterForm" class="row align-items-end">
                    <div class="col-md-4 col-sm-6 mb-2">
                        <label class="small text-muted font-weight-bold mb-1">Dari Tgl Titipan</label>
                        <input type="date" class="form-control form-control-sm" id="portalDateFrom" value="<?= date('Y-m-01') ?>">
                    </div>
                    <div class="col-md-4 col-sm-6 mb-2">
                        <label class="small text-muted font-weight-bold mb-1">Sampai Tgl Titipan</label>
                        <input type="date" class="form-control form-control-sm" id="portalDateTo" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-4 col-sm-12 mb-2 text-right">
                        <button type="submit" class="btn btn-primary btn-sm font-weight-bold px-3">
                            <i class="fas fa-filter mr-1"></i> Terapkan Filter
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm ml-1" id="btnResetPortalFilter">
                            Reset
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- DATA TABLE CARD -->
        <div class="portal-card">
            <div class="portal-card-header">
                <div>
                    <h5 class="font-weight-bold mb-0 text-dark" style="font-size: 1.15rem;">
                        <i class="fas fa-file-contract text-primary mr-2"></i>Daftar Titipan Delivery Order
                    </h5>
                    <small class="text-muted">Klik pada tombol dokumen untuk melihat atau mengunduh berkas PDF.</small>
                </div>
            </div>
            <div class="card-body p-3">
                <div class="table-responsive">
                    <table id="tablePortal" class="table table-hover table-striped table-bordered table-portal w-100">
                        <thead>
                            <tr>
                                <th style="width: 35px;" class="text-center">#</th>
                                <th>No Sales Order</th>
                                <th>Tgl SC</th>
                                <th>Nama Product</th>
                                <th>No Titipan</th>
                                <th>Tgl Titipan</th>
                                <th>No DO</th>
                                <th>Tgl DO</th>
                                <th class="text-right">QTY DO</th>
                                <th class="text-right">Qty Kirim</th>
                                <th>UOM</th>
                                <th>Keterangan</th>
                                <th class="text-center">Packing List</th>
                                <th class="text-center">Surat Jalan</th>
                                <th style="width: 80px;" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- FOOTER -->
        <footer class="text-center text-muted py-3 small">
            &copy; <?= date('Y') ?> <b>PT SUM</b> — Portal Layanan Sumber Agung. All rights reserved.
        </footer>

    </div>

    <!-- =====================================================================
         MODAL DETAIL CUSTOMER
         ===================================================================== -->
    <div class="modal fade" id="modalPortalDetail" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
                <div class="modal-header bg-dark text-white py-3 px-4">
                    <h5 class="modal-title font-weight-bold" style="font-size: 1.1rem;">
                        <i class="fas fa-file-invoice text-info mr-2"></i>Detail Informasi Titipan DO
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="small text-muted font-weight-bold d-block mb-1">NO TITIPAN</label>
                            <h6 class="font-weight-bold text-dark" id="pDetNoTitipan">-</h6>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="small text-muted font-weight-bold d-block mb-1">TGL TITIPAN</label>
                            <div class="text-dark font-weight-bold" id="pDetTglTitipan">-</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="small text-muted font-weight-bold d-block mb-1">NO DELIVERY ORDER (DO)</label>
                            <div class="text-dark font-weight-bold" id="pDetNoDO">-</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="small text-muted font-weight-bold d-block mb-1">TGL DO</label>
                            <div class="text-dark font-weight-bold" id="pDetTglDO">-</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="small text-muted font-weight-bold d-block mb-1">NO SALES ORDER (SC)</label>
                            <div class="text-dark font-weight-bold" id="pDetNoSC">-</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="small text-muted font-weight-bold d-block mb-1">TGL SC</label>
                            <div class="text-dark font-weight-bold" id="pDetTglSC">-</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="small text-muted font-weight-bold d-block mb-1">PRODUK</label>
                            <div class="text-dark font-weight-bold" id="pDetProduct">-</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="small text-muted font-weight-bold d-block mb-1">QTY KIRIM</label>
                            <div class="text-dark font-weight-bold text-success" id="pDetQty">-</div>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="small text-muted font-weight-bold d-block mb-1">KETERANGAN</label>
                            <div class="p-2 bg-light rounded border text-muted" id="pDetKeterangan">-</div>
                        </div>
                    </div>

                    <hr class="my-3">

                    <h6 class="font-weight-bold text-dark mb-3">
                        <i class="fas fa-paperclip text-primary mr-1"></i> Berkas Dokumen Terkait
                    </h6>

                    <div class="row">
                        <div class="col-md-6 mb-2" id="boxPL">
                            <div class="p-3 bg-light rounded border d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="font-weight-bold text-primary">
                                        <i class="fas fa-file-pdf mr-1"></i> Packing List
                                    </div>
                                    <small class="text-muted">Dokumen PDF Packing List</small>
                                </div>
                                <div id="actionPL">
                                    <span class="badge badge-secondary">Tidak Ada</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-2" id="boxSJ">
                            <div class="p-3 bg-light rounded border d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="font-weight-bold text-success">
                                        <i class="fas fa-shipping-fast mr-1"></i> Surat Jalan
                                    </div>
                                    <small class="text-muted">Dokumen PDF Surat Jalan</small>
                                </div>
                                <div id="actionSJ">
                                    <span class="badge badge-secondary">Tidak Ada</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2 px-4 bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- =====================================================================
         MODAL PREVIEW PDF PORTAL
         ===================================================================== -->
    <div class="modal fade" id="modalPortalPDF" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl" style="max-width: 90%; height: 90vh;" role="document">
            <div class="modal-content h-100 border-0 shadow-lg">
                <div class="modal-header bg-dark text-white py-2 px-3">
                    <h6 class="modal-title font-weight-bold" id="portalPdfTitle">
                        <i class="fas fa-file-pdf text-danger mr-2"></i>Dokumen PDF
                    </h6>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0 h-100">
                    <iframe id="portalPdfFrame" src="" style="width: 100%; height: calc(90vh - 110px); border: none;"></iframe>
                </div>
                <div class="modal-footer py-2 px-3 bg-light">
                    <a href="#" target="_blank" class="btn btn-sm btn-primary" id="btnPortalOpenNewTab">
                        <i class="fas fa-external-link-alt mr-1"></i> Buka di Tab Baru
                    </a>
                    <a href="#" download class="btn btn-sm btn-success" id="btnPortalDownload">
                        <i class="fas fa-download mr-1"></i> Download File
                    </a>
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/jquery/jquery.min.js"></script>
    <script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
    <script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
    <script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
    <script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.min.js"></script>
    <script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/toastr/toastr.min.js"></script>

    <script>
    $(document).ready(function () {
        const PORTAL_API = '/gg_app/pages/sumber_agung/portal_serverside.php';
        let tablePortal = null;

        // Init DataTables Portal
        tablePortal = $('#tablePortal').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: PORTAL_API,
                type: 'GET',
                data: function (d) {
                    d.action    = 'load_portal_data';
                    d.date_from = $('#portalDateFrom').val();
                    d.date_to   = $('#portalDateTo').val();
                },
                dataSrc: function (json) {
                    if (json.status !== 'ok') {
                        toastr.error(json.message || 'Gagal memuat data portal');
                        return [];
                    }
                    return json.data || [];
                }
            },
            columns: [
                { data: 'no', className: 'text-center' },
                { data: 'no_sc', render: d => `<strong>${d}</strong>` },
                { data: 'tgl_sc' },
                {
                    data: null,
                    render: function (data, type, row) {
                        return `<div><b>${row.product_name}</b></div><small class="text-muted">${row.product_code || ''}</small>`;
                    }
                },
                { data: 'no_titipan', render: d => `<span class="badge badge-light border font-weight-bold">${d}</span>` },
                { data: 'tgl_titipan' },
                { data: 'no_do' },
                { data: 'tgl_do' },
                {
                    data: 'do_qty',
                    className: 'text-right',
                    render: d => d !== null ? Number(d).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) : '-'
                },
                {
                    data: 'qty_kirim',
                    className: 'text-right font-weight-bold text-primary',
                    render: d => d !== null ? Number(d).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) : '-'
                },
                { data: 'uom' },
                {
                    data: 'ket_titipan_2',
                    render: d => d ? `<span title="${d}">${d.length > 25 ? d.substring(0, 25) + '...' : d}</span>` : '-'
                },
                {
                    data: null,
                    className: 'text-center',
                    render: function (data, type, row) {
                        if (row.has_packinglist) {
                            return `<button type="button" class="btn-view-doc btn-open-pdf" data-id="${row.id}" data-url="${row.packinglist_file}" data-title="Packing List - ${row.no_titipan}"><i class="fas fa-file-pdf"></i> Lihat PL</button>`;
                        }
                        return '<span class="text-muted small">-</span>';
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    render: function (data, type, row) {
                        if (row.has_suratjalan) {
                            return `<button type="button" class="btn-view-doc-green btn-open-pdf" data-id="${row.id}" data-url="${row.suratjalan_file}" data-title="Surat Jalan - ${row.no_titipan}"><i class="fas fa-file-alt"></i> Lihat SJ</button>`;
                        }
                        return '<span class="text-muted small">-</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: function (data, type, row) {
                        return `
                            <button type="button" class="btn btn-sm btn-outline-primary btn-portal-detail font-weight-bold" data-id="${row.id}" title="Lihat Detail Lengkap">
                                <i class="fas fa-eye mr-1"></i> Detail
                            </button>
                        `;
                    }
                }
            ],
            order: [[5, 'desc']], // Urut berdasarkan tgl_titipan
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Semua"]],
            language: {
                search: "Cari:",
                lengthMenu: "Tampilkan _MENU_ data",
                info: "Menampilkan _START_ s/d _END_ dari _TOTAL_ data",
                infoEmpty: "Menampilkan 0 data",
                infoFiltered: "(disaring dari _MAX_ total data)",
                zeroRecords: "Belum ada dokumen titipan yang dipublikasikan",
                paginate: {
                    first: "Awal",
                    last: "Akhir",
                    next: "Lanjut",
                    previous: "Kembali"
                }
            }
        });

        // Refresh & Filter
        $('#btnRefreshPortal').on('click', function () {
            tablePortal.ajax.reload(null, false);
            toastr.info('Data diperbarui');
        });

        $('#portalFilterForm').on('submit', function (e) {
            e.preventDefault();
            tablePortal.ajax.reload();
        });

        $('#btnResetPortalFilter').on('click', function () {
            $('#portalDateFrom').val('<?= date('Y-m-01') ?>');
            $('#portalDateTo').val('<?= date('Y-m-d') ?>');
            tablePortal.ajax.reload();
        });

        // ─── VIEW PDF & AUTO MARK VIEWED ────────────────────────────────────
        $(document).on('click', '.btn-open-pdf', function () {
            const id = $(this).data('id');
            const url = $(this).data('url');
            const title = $(this).data('title');

            if (!url) return;

            // Catat viewed ke server
            $.post(PORTAL_API, { action: 'mark_viewed', id: id });

            $('#portalPdfTitle').html(`<i class="fas fa-file-pdf text-danger mr-2"></i> ${title}`);
            $('#portalPdfFrame').attr('src', url);
            $('#btnPortalOpenNewTab').attr('href', url);
            $('#btnPortalDownload').attr('href', url);
            $('#modalPortalPDF').modal('show');
        });

        // ─── DETAIL MODAL CUSTOMER ──────────────────────────────────────────
        $(document).on('click', '.btn-portal-detail', function () {
            const id = $(this).data('id');

            $.getJSON(PORTAL_API, { action: 'get_portal_detail', id: id }, function (res) {
                if (res.status !== 'ok' || !res.data) {
                    toastr.error(res.message || 'Gagal memuat detail.');
                    return;
                }

                const d = res.data;
                $('#pDetNoTitipan').text(d.no_titipan);
                $('#pDetTglTitipan').text(d.tgl_titipan);
                $('#pDetNoDO').text(d.no_do);
                $('#pDetTglDO').text(d.tgl_do);
                $('#pDetNoSC').text(d.no_sc);
                $('#pDetTglSC').text(d.tgl_sc);
                $('#pDetProduct').text(`${d.product_name} (${d.product_code})`);
                $('#pDetQty').text(d.qty_kirim !== null ? `${Number(d.qty_kirim).toLocaleString('id-ID')} ${d.uom}` : '-');
                $('#pDetKeterangan').text(d.ket_titipan_2 || d.ket_titipan_1 || d.dodesc || 'Tidak ada catatan tambahan.');

                // Action PL
                if (d.packinglist_file) {
                    $('#actionPL').html(`
                        <button type="button" class="btn btn-sm btn-primary btn-open-pdf" data-id="${d.id}" data-url="${d.packinglist_file}" data-title="Packing List - ${d.no_titipan}">
                            <i class="fas fa-eye mr-1"></i> Buka File
                        </button>
                    `);
                } else {
                    $('#actionPL').html('<span class="badge badge-secondary">Belum Tersedia</span>');
                }

                // Action SJ
                if (d.suratjalan_file) {
                    $('#actionSJ').html(`
                        <button type="button" class="btn btn-sm btn-success btn-open-pdf" data-id="${d.id}" data-url="${d.suratjalan_file}" data-title="Surat Jalan - ${d.no_titipan}">
                            <i class="fas fa-eye mr-1"></i> Buka File
                        </button>
                    `);
                } else {
                    $('#actionSJ').html('<span class="badge badge-secondary">Belum Tersedia</span>');
                }

                $('#modalPortalDetail').modal('show');
            });
        });
    });
    </script>
</body>
</html>
