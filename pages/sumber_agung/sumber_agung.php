<?php
/**
 * Modul Sumber Agung - Manajemen Titipan DO
 * Halaman utama internal staff untuk mengelola data titipan,
 * upload dokumen (Packing List & Surat Jalan PDF), serta publish ke web portal.
 *
 * Desain modal Search Titipan disesuaikan dengan Search PO di tracking_po.php.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/koneksi.php';
require_once $rootPath . '/includes/permissions.php';

// Cek hak akses
$currentUserName = trim($_SESSION['UserName'] ?? '');
$currentNamaLengkap = trim($_SESSION['NamaLengkap'] ?? '');
$currentDeptName = '';
$currentGroupId = $_SESSION['GroupId'] ?? null;
$currentGroupName = $_SESSION['GroupName'] ?? '';

if (!empty($currentUserName) && !empty($conn)) {
    $qUser = sqlsrv_query($conn, "SELECT u.GroupId, g.GroupName, e.nama_lengkap, d.dept
        FROM dbo.SMUserMs u
        LEFT JOIN dbo.SMUserGroup g ON u.GroupId = g.GroupId
        LEFT JOIN dbo.m_emp e ON u.EmpId = e.id_emp
        LEFT JOIN dbo.m_subbag sb ON e.id_subbag = sb.id_subbag
        LEFT JOIN dbo.m_bag b ON sb.id_bag = b.id_bag
        LEFT JOIN dbo.m_dept d ON b.id_dept = d.id_dept
        WHERE u.UserName = ?", [$currentUserName]);
    if ($qUser && ($uRow = sqlsrv_fetch_array($qUser, SQLSRV_FETCH_ASSOC))) {
        if (!empty($uRow['nama_lengkap'])) $currentNamaLengkap = trim($uRow['nama_lengkap']);
        if (!empty($uRow['dept'])) $currentDeptName = trim($uRow['dept']);
        if (!empty($uRow['GroupId'])) $currentGroupId = $uRow['GroupId'];
        if (!empty($uRow['GroupName'])) $currentGroupName = trim($uRow['GroupName']);
    }
}
if (empty($currentNamaLengkap)) $currentNamaLengkap = $currentUserName;

$isAdminUser = (
    (!empty($currentGroupName) && strcasecmp(trim($currentGroupName), 'Administrator') === 0) ||
    (int)$currentGroupId === 1
);

$headerPath = file_exists($rootPath . '/includes/header.php') ? ($rootPath . '/includes/header.php') : ($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
$sidebarPath = file_exists($rootPath . '/includes/sidebar.php') ? ($rootPath . '/includes/sidebar.php') : ($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
include($headerPath);
include($sidebarPath);
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<!-- DataTables & Plugins CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-fixedcolumns/css/fixedColumns.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/toastr/toastr.min.css">

<style>
/* =========================================================
   SUMBER AGUNG - SUMMARY CARDS
   ========================================================= */
.sa-summary-row {
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}
.sa-stat-card {
    flex: 1;
    min-width: 220px;
    background: #ffffff;
    border-radius: 12px;
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    border: 1px solid #e2e8f0;
    cursor: pointer;
    transition: all 0.25s ease;
    position: relative;
    overflow: hidden;
}
.sa-stat-card::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 5px;
    border-radius: 12px 0 0 12px;
}
.sa-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.1);
}
.sa-stat-card.active {
    box-shadow: 0 4px 14px rgba(0,0,0,0.15);
    border-color: #94a3b8;
}
.sa-stat-card[data-status="belum_lengkap"]::before { background: #f59e0b; }
.sa-stat-card[data-status="published"]::before { background: #3b82f6; }
.sa-stat-card[data-status="sudah_dilihat"]::before { background: #10b981; }

.sa-icon-box {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}
.sa-icon-box.bg-amber { background: #fef3c7; color: #d97706; }
.sa-icon-box.bg-blue { background: #dbeafe; color: #2563eb; }
.sa-icon-box.bg-emerald { background: #d1fae5; color: #059669; }

.sa-stat-info .sa-stat-count {
    font-size: 26px;
    font-weight: 700;
    line-height: 1.1;
    color: #1e293b;
}
.sa-stat-info .sa-stat-label {
    font-size: 13px;
    color: #64748b;
    font-weight: 500;
    margin-top: 3px;
}

/* Floating Action Bar */
.sa-floating-bar {
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%) translateY(120px);
    background: #1e293b;
    color: #ffffff;
    padding: 12px 24px;
    border-radius: 50px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.25);
    display: flex;
    align-items: center;
    gap: 16px;
    z-index: 1040;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.sa-floating-bar.show {
    transform: translateX(-50%) translateY(0);
}

/* Badges */
.badge-doc-yes {
    background-color: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
    font-weight: 600;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 11px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.badge-doc-no {
    background-color: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
    font-weight: 600;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 11px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.badge-pub-draft {
    background-color: #f1f5f9;
    color: #475569;
    border: 1px solid #cbd5e1;
    font-weight: 600;
    padding: 4px 9px;
    border-radius: 6px;
    font-size: 11.5px;
}
.badge-pub-published {
    background-color: #dbeafe;
    color: #1e40af;
    border: 1px solid #bfdbfe;
    font-weight: 600;
    padding: 4px 9px;
    border-radius: 6px;
    font-size: 11.5px;
}
.badge-pub-viewed {
    background-color: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
    font-weight: 600;
    padding: 4px 9px;
    border-radius: 6px;
    font-size: 11.5px;
}
.badge-prodcode {
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 4px;
    background-color: #f1f5f9;
    color: #334155;
    border: 1px solid #cbd5e1;
    display: inline-block;
}

/* Header Tabel Utama 1 Baris (Nowrap) */
#tableTitipan thead th {
    white-space: nowrap !important;
    vertical-align: middle;
}

/* DataTables Fixed Columns & Sticky Left Styling */
table.dataTable thead th.dtfc-fixed-left {
    background-color: #f8fafc !important;
    z-index: 5 !important;
}
table.dataTable tbody td.dtfc-fixed-left {
    background-color: #ffffff !important;
    z-index: 4 !important;
}
table.dataTable tbody tr.even td.dtfc-fixed-left {
    background-color: #ffffff !important;
}
table.dataTable tbody tr.odd td.dtfc-fixed-left {
    background-color: #fcfcfd !important;
}
table.dataTable tbody tr:hover td.dtfc-fixed-left {
    background-color: #f1f5f9 !important;
}

/* Pembatas garis di sebelah kanan kolom No SO (kolom ke-4 yang dibekukan) */
#tableTitipan thead th.dtfc-fixed-left:nth-child(4),
#tableTitipan tbody td.dtfc-fixed-left:nth-child(4) {
    box-shadow: 4px 0 6px -3px rgba(0, 0, 0, 0.12);
    border-right: 2px solid #cbd5e1 !important;
}

/* Posisi Pagination Tetap di Pojok Kanan Bawah Kartu */
#tableTitipan_wrapper {
    width: 100% !important;
}
#tableTitipan_wrapper .dataTables_paginate {
    float: right !important;
    text-align: right !important;
    margin: 0 !important;
}
#tableTitipan_wrapper .dataTables_paginate ul.pagination {
    justify-content: flex-end !important;
    margin: 2px 0 !important;
}

/* Action Buttons Modern Soft Style */
.btn-action-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    white-space: nowrap;
}
.btn-action-item {
    width: 32px;
    height: 32px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 13px;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    border: 1px solid transparent;
    cursor: pointer;
}
.btn-action-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.12);
}
.btn-action-item:active {
    transform: translateY(0);
}
.btn-action-detail {
    background-color: #e0f2fe;
    color: #0284c7;
    border-color: #bae6fd;
}
.btn-action-detail:hover {
    background-color: #0284c7;
    color: #ffffff;
    border-color: #0284c7;
}
.btn-action-delete {
    background-color: #fee2e2;
    color: #dc2626;
    border-color: #fecaca;
}
.btn-action-delete:hover {
    background-color: #dc2626;
    color: #ffffff;
    border-color: #dc2626;
}

/* Detail Modal Styling (Modern & Professional) */
.sa-field-label {
    width: 36%;
    background-color: #f8fafc;
    color: #64748b;
    font-size: 12px;
    font-weight: 600;
    vertical-align: middle;
    border-top: 1px solid #f1f5f9 !important;
    padding: 8px 12px !important;
}
.sa-field-val {
    color: #1e293b;
    font-size: 12.5px;
    vertical-align: middle;
    border-top: 1px solid #f1f5f9 !important;
    padding: 8px 12px !important;
}
.sa-detail-table tr:first-child .sa-field-label,
.sa-detail-table tr:first-child .sa-field-val {
    border-top: none !important;
}
.sa-upload-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    transition: all 0.2s ease;
}
.sa-upload-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}
.custom-file-label::after {
    content: "Pilih" !important;
}

/* =========================================================
   MODAL SEARCH PURCHASE ORDER / TITIPAN (Model Tracking PO)
   ========================================================= */
.po-search-modal .modal-dialog {
    max-width: 96% !important;
    width: 96% !important;
}

.po-search-modal .modal-content {
    border-radius: 8px;
    border: none;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    overflow: hidden;
}

.po-search-header {
    padding: 10px 18px;
}

.po-search-toolbar {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}

.po-search-inline-label {
    font-size: 0.82rem;
    font-weight: 700;
    color: #475569;
}

.po-search-select, .po-search-input {
    font-size: 0.82rem;
    height: 32px;
}

#erpSearchTable {
    width: 100%;
    margin-bottom: 0;
}

#erpSearchTable thead th {
    background: #edf1f5;
    border-color: #cfd4da;
    font-size: 0.78rem;
    font-weight: 700;
    color: #0f172a;
    white-space: nowrap;
    padding: 7px 8px;
    text-align: center !important;
    vertical-align: middle !important;
}

#erpSearchTable tbody td {
    border-color: #d9dde2;
    font-size: 0.8rem;
    white-space: nowrap;
    padding: 6px 8px;
    vertical-align: middle;
}

#erpSearchTable tbody tr {
    cursor: pointer;
    transition: background-color 0.1s ease;
}

#erpSearchTable tbody tr:hover td {
    background: #fff8eb;
}

#erpSearchTable tbody tr.table-active td {
    background: #e0f2fe !important;
    border-top-color: #38bdf8;
    border-bottom-color: #38bdf8;
}

#erpSearchTable tbody tr.row-disabled {
    cursor: not-allowed;
    background-color: #f8fafc !important;
    opacity: 0.65;
}

#erpSearchTable tbody tr.row-disabled:hover td {
    background-color: #f8fafc !important;
}

.po-select-col {
    width: 75px !important;
    min-width: 75px;
    text-align: center;
}

.po-seq-wrap {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.po-row-seq {
    display: inline-block;
    min-width: 22px;
    text-align: right;
    color: #475569;
    font-size: 0.76rem;
    font-weight: 600;
}

.po-row-check {
    cursor: pointer;
}

.loading-overlay-search {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.75);
    z-index: 1050;
    display: flex;
    align-items: center;
    justify-content: center;
}

.po-badge-code {
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 11.5px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 4px;
    background-color: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
    display: inline-block;
}
</style>

<div class="content-wrapper">
    <!-- Header Section -->
    <div class="content-header pb-1">
        <div class="container-fluid">
            <div class="row align-items-center mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark font-weight-bold" style="font-size: 1.5rem;">
                        <i class="fas fa-truck-loading text-primary mr-2"></i>Sumber Agung
                    </h1>
                    <small class="text-muted">Kelola data titipan DO, upload dokumen Packing List & Surat Jalan, serta publish ke portal.</small>
                </div>
                <div class="col-sm-6 text-right">
                    <button type="button" class="btn btn-success font-weight-bold shadow-sm" id="btnTambahData">
                        <i class="fas fa-plus-circle mr-1"></i> Tambah Data Titipan
                    </button>
                    <button type="button" class="btn btn-outline-secondary ml-1" id="btnRefresh" title="Refresh data">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <section class="content">
        <div class="container-fluid">

            <!-- SUMMARY CARDS -->
            <div class="sa-summary-row">
                <div class="sa-stat-card" data-status="belum_lengkap" id="cardBelumLengkap">
                    <div class="sa-icon-box bg-amber">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="sa-stat-info">
                        <div class="sa-stat-count" id="countBelumLengkap">0</div>
                        <div class="sa-stat-label">Dokumen Belum Lengkap</div>
                    </div>
                </div>

                <div class="sa-stat-card" data-status="published" id="cardPublished">
                    <div class="sa-icon-box bg-blue">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <div class="sa-stat-info">
                        <div class="sa-stat-count" id="countPublished">0</div>
                        <div class="sa-stat-label">Published ke Portal</div>
                    </div>
                </div>

                <div class="sa-stat-card" data-status="sudah_dilihat" id="cardSudahDilihat">
                    <div class="sa-icon-box bg-emerald">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="sa-stat-info">
                        <div class="sa-stat-count" id="countSudahDilihat">0</div>
                        <div class="sa-stat-label">Sudah Dilihat Customer</div>
                    </div>
                </div>
            </div>

            <!-- FILTER CARD -->
            <div class="card card-outline card-primary shadow-sm mb-3">
                <div class="card-body py-2 px-3">
                    <form id="filterForm" class="row align-items-center">
                        <div class="col-md-3 col-sm-6 mb-2">
                            <label class="small text-muted mb-1 font-weight-bold">Dari Tgl Kirim</label>
                            <input type="date" class="form-control form-control-sm" id="filterDateFrom" name="date_from" value="<?= date('Y-m-01') ?>">
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2">
                            <label class="small text-muted mb-1 font-weight-bold">Sampai Tgl Kirim</label>
                            <input type="date" class="form-control form-control-sm" id="filterDateTo" name="date_to" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-2 col-sm-6 mb-2">
                            <label class="small text-muted mb-1 font-weight-bold">Status Dokumen</label>
                            <select class="form-control form-control-sm" id="filterDoc">
                                <option value="">Semua Status Dokumen</option>
                                <option value="belum_lengkap">Belum Lengkap</option>
                                <option value="lengkap">Lengkap</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6 mb-2">
                            <label class="small text-muted mb-1 font-weight-bold">Status Publish</label>
                            <select class="form-control form-control-sm" id="filterPub">
                                <option value="">Semua Status</option>
                                <option value="belum_publish">Belum Publish</option>
                                <option value="published">Published</option>
                                <option value="sudah_dilihat">Sudah Dilihat</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-12 mb-2 text-right pt-md-3">
                            <button type="submit" class="btn btn-primary btn-sm px-3 font-weight-bold">
                                <i class="fas fa-filter mr-1"></i> Filter
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm ml-1" id="btnResetFilter">
                                Reset
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- DATA TABLE CARD -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-3">
                    <table id="tableTitipan" class="table table-hover table-striped table-bordered text-sm w-100">
                        <thead class="bg-light text-nowrap">
                            <tr>
                                <th style="width: 30px;" class="text-center">
                                    <input type="checkbox" id="checkAll">
                                </th>
                                <th style="width: 35px;" class="text-center">#</th>
                                <th>Tgl Kirim</th>
                                <th>No SO</th>
                                <th>Tgl SC</th>
                                <th>Prod Code</th>
                                <th>Nama Produk</th>
                                <th>Label Jual</th>
                                <th>Cust Color</th>
                                <th>No Titipan</th>
                                <th>Tgl Titipan</th>
                                <th>No DO</th>
                                <th>Tgl DO</th>
                                <th>No Packing</th>
                                <th>No Bale</th>
                                <th class="text-right">Qty DO</th>
                                <th>UOM</th>
                                <th>Keterangan</th>
                                <th class="text-center">Packing List</th>
                                <th class="text-center">Surat Jalan</th>
                                <th class="text-center">Status</th>
                                <th style="min-width: 95px; width: 95px;" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </section>
</div>

<!-- FLOATING ACTION BAR FOR MASS PUBLISH -->
<div class="sa-floating-bar" id="floatingBar">
    <div>
        <i class="fas fa-check-circle text-success mr-1"></i>
        <span class="font-weight-bold" id="selectedCount">0</span> data titipan dipilih
    </div>
    <div class="d-flex align-items-center">
        <button type="button" class="btn btn-primary btn-sm font-weight-bold px-3 mr-2" id="btnPublishSelected">
            <i class="fas fa-cloud-upload-alt mr-1"></i> Publish ke Portal
        </button>
        <button type="button" class="btn btn-outline-light btn-sm" id="btnCancelSelect">
            Batal
        </button>
    </div>
</div>

<!-- =========================================================================
     MODAL 1: TAMBAH DATA DARI ERP (POSTGRESQL) - TAMPILAN PERSIS TRACKING PO
     ========================================================================= -->
<div class="modal fade po-search-modal" id="modalTambahData" tabindex="-1" role="dialog" aria-labelledby="modalTambahDataLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-xl po-search-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header po-search-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title font-weight-bold" id="modalTambahDataLabel">
                    <i class="fas fa-file-invoice mr-2"></i>Search Data Titipan (Ambil dari ERP PostgreSQL)
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3" style="position: relative;">
                <!-- Loading Animation Overlay -->
                <div id="loadingOverlayErpSearch" class="loading-overlay-search" style="display: none;">
                    <div class="loading-box-search text-center p-3 bg-white rounded shadow-sm border">
                        <div class="spinner-border text-primary" role="status" style="width: 2.5rem; height: 2.5rem;"></div>
                        <p class="font-weight-bold text-dark mt-2 mb-0" style="font-size: 0.85rem;">Memuat data Titipan DO dari ERP...</p>
                    </div>
                </div>

                <!-- Toolbar Pencarian Model Search CP and Routing -->
                <div class="po-search-toolbar mb-3 p-2 rounded">
                    <div class="po-search-toolbar-left d-flex align-items-center flex-wrap" style="gap: 8px;">
                        <label for="erpDateFrom" class="po-search-inline-label mb-0 font-weight-bold text-muted small"><i class="fas fa-calendar-alt mr-1"></i> Tgl DO:</label>
                        <input type="date" id="erpDateFrom" class="form-control form-control-sm po-search-input" value="<?= date('Y-m-d') ?>" style="width: 135px;" title="Dari Tanggal DO">
                        <span class="text-muted small font-weight-bold">s/d</span>
                        <input type="date" id="erpDateTo" class="form-control form-control-sm po-search-input" value="<?= date('Y-m-d') ?>" style="width: 135px;" title="Sampai Tanggal DO">

                        <label for="erpSearchField" class="po-search-inline-label mb-0 font-weight-bold text-muted small ml-1">Search For</label>
                        <select id="erpSearchField" class="form-control form-control-sm po-search-select" style="width: auto; min-width: 160px;">
                            <option value="all">Semua Kolom</option>
                            <option value="no_do">No DO</option>
                            <option value="no_packing">No Packing</option>
                            <option value="no_bale">No Bale</option>
                            <option value="cust_name">Vendor / Supplier / Cust</option>
                            <option value="sonmbr">No SO (Sales Order)</option>
                            <option value="prodcode">Prod Code</option>
                            <option value="prodname">Nama Produk</option>
                            <option value="label_jual">Label Jual</option>
                            <option value="cust_color">Cust Color</option>
                        </select>
                        <input type="text" id="erpSearchKeyword" class="form-control form-control-sm po-search-input" 
                               placeholder="Ketik keyword pencarian..." style="width: 220px;" autocomplete="off">
                        <button type="button" id="btnErpSearchApply" class="btn btn-primary btn-sm font-weight-bold">
                            <i class="fas fa-search mr-1"></i> Search
                        </button>
                        <button type="button" id="btnErpSearchReset" class="btn btn-outline-secondary btn-sm font-weight-bold">
                            <i class="fas fa-sync-alt mr-1"></i> Reset
                        </button>
                        <button type="button" id="btnErpSearchSelectAll" class="btn btn-outline-primary btn-sm font-weight-bold">
                            <i class="fas fa-check-double mr-1"></i> Select All
                        </button>
                    </div>
                </div>

                <!-- Table Container dengan Horizontal & Vertical Scroll -->
                <div class="po-search-table-wrap table-responsive" style="max-height: 480px; overflow: auto; border: 1px solid #cbd5e1; border-radius: 6px;">
                    <table id="erpSearchTable" class="table table-bordered table-sm w-100 mb-0">
                        <thead>
                            <tr>
                                <th class="po-select-col text-center" style="width: 70px; min-width: 70px;">Seq</th>
                                <th style="width: 130px; min-width: 130px;" class="text-center">No DO</th>
                                <th style="width: 95px; min-width: 95px;" class="text-center">Tgl DO</th>
                                <th style="width: 115px; min-width: 115px;" class="text-center">No Packing</th>
                                <th style="width: 110px; min-width: 110px;" class="text-center">No Bale</th>
                                <th style="width: 95px; min-width: 95px;" class="text-right">Qty Yard</th>
                                <th style="width: 95px; min-width: 95px;" class="text-right">Qty Meter</th>
                                <th style="width: 70px; min-width: 70px;" class="text-center">Batch</th>
                                <th style="min-width: 185px;">Vendor / Supplier</th>
                                <th style="min-width: 135px;" class="text-center">No SO</th>
                                <th style="width: 95px; min-width: 95px;" class="text-center">Tgl SC</th>
                                <th style="width: 135px; min-width: 135px;" class="text-center">Prod Code</th>
                                <th style="min-width: 190px;">Nama Produk</th>
                                <th style="min-width: 95px;" class="text-center">Label Jual</th>
                                <th style="width: 95px; min-width: 95px;" class="text-center">Cust Color</th>
                                <th style="width: 60px; min-width: 60px;" class="text-center">UOM</th>
                                <th style="min-width: 220px;">Keterangan</th>
                                <th style="width: 105px; min-width: 105px;" class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody id="erpSearchTableBody">
                            <!-- Data dimuat via AJAX -->
                        </tbody>
                    </table>
                </div>

                <!-- Bar Paginasi & Info Data di Bawah Tabel -->
                <div class="d-flex align-items-center justify-content-between flex-wrap mt-2 pt-2 border-top" style="gap: 10px;">
                    <div class="small font-weight-bold text-muted" id="erpSearchPaginationInfo">
                        Menampilkan 0 - 0 dari 0 data
                    </div>
                    <div id="erpSearchPaginationNav" class="btn-group btn-group-sm">
                        <!-- Tombol halaman dibuat dinamis via JS -->
                    </div>
                </div>
            </div>

            <!-- Footer: Tanggal Kirim + Submit & Cancel -->
            <div class="modal-footer d-flex align-items-center justify-content-between flex-wrap bg-light px-3 py-2">
                <div class="d-flex align-items-center flex-wrap" style="gap: 12px;">
                    <div class="d-flex align-items-center">
                        <label for="inputErpTanggalKirim" class="small font-weight-bold mb-0 mr-2 text-dark">
                            <i class="fas fa-calendar-alt text-primary mr-1"></i> Tanggal Kirim:
                        </label>
                        <input type="date" id="inputErpTanggalKirim" class="form-control form-control-sm font-weight-bold" 
                               value="<?= date('Y-m-d') ?>" style="width: 145px;">
                        <button type="button" class="btn btn-outline-secondary btn-sm ml-1" id="btnClearErpTanggalKirim" title="Kosongkan tanggal">
                            <i class="fas fa-times mr-1"></i> Kosongkan
                        </button>
                    </div>
                    <div>
                        <span id="erpSelectedCountBadge" class="badge badge-primary px-3 py-2 font-weight-bold" style="font-size: 0.85rem;">
                            0 Titipan Terpilih
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-center" style="gap: 8px;">
                    <button type="button" id="btnSubmitErpSearch" class="btn btn-outline-primary btn-sm px-4 font-weight-bold" disabled>
                        <i class="fas fa-paper-plane mr-1"></i> Submit
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm px-4 font-weight-bold" data-dismiss="modal">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 2: DETAIL DATA & UPLOAD DOKUMEN (REDESIGNED MODERN & PROFESSIONAL)
     ========================================================================= -->
<div class="modal fade" id="modalDetail" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
            <div class="modal-header text-white py-3 px-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
                <div class="d-flex align-items-center">
                    <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(59, 130, 246, 0.2); border: 1px solid rgba(59, 130, 246, 0.4); display: flex; align-items: center; justify-content: center; margin-right: 12px; color: #60a5fa;">
                        <i class="fas fa-file-invoice" style="font-size: 17px;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title font-weight-bold mb-0" style="font-size: 1.15rem; letter-spacing: -0.2px;">
                            Detail Titipan & Kelola Dokumen
                        </h5>
                        <small style="color: #94a3b8; font-size: 12px;">Rincian pengiriman dan upload berkas Packing List & Surat Jalan</small>
                    </div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.8; outline: none;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4" style="background-color: #f8fafc;">
                
                <!-- Top Highlight Banner -->
                <div class="p-3 mb-3 bg-white border rounded shadow-sm d-flex justify-content-between align-items-center flex-wrap" style="gap: 12px; border-radius: 10px !important;">
                    <div class="d-flex align-items-center" style="gap: 12px;">
                        <div style="width: 44px; height: 44px; border-radius: 10px; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                            <i class="fas fa-truck-loading"></i>
                        </div>
                        <div>
                            <small class="text-muted font-weight-bold d-block text-uppercase" style="font-size: 10.5px; letter-spacing: 0.5px;">Nomor DO Titipan</small>
                            <span class="font-weight-bold text-dark" style="font-family: monospace; font-size: 1.15rem;" id="detNoTitipan">-</span>
                        </div>
                    </div>
                    <div class="d-flex align-items-center" style="gap: 24px;">
                        <div>
                            <small class="text-muted font-weight-bold d-block text-uppercase" style="font-size: 10.5px; letter-spacing: 0.5px;">Tanggal Kirim</small>
                            <span class="badge badge-light border text-dark font-weight-bold px-2 py-1" style="font-size: 13px;" id="detTglKirim">-</span>
                        </div>
                        <div class="text-right">
                            <small class="text-muted font-weight-bold d-block text-uppercase" style="font-size: 10.5px; letter-spacing: 0.5px;">Total Qty Kirim</small>
                            <span class="font-weight-bold text-primary" style="font-size: 1.2rem;" id="detQty">-</span>
                        </div>
                    </div>
                </div>

                <!-- Dua Kolom Informasi Data -->
                <div class="row">
                    <!-- Panel Kiri: Data Dokumen & Pengiriman -->
                    <div class="col-md-6 mb-3">
                        <div class="card h-100 border-0 shadow-sm" style="border-radius: 10px; overflow: hidden;">
                            <div class="card-header py-2 px-3 bg-white border-bottom font-weight-bold text-dark" style="font-size: 13px;">
                                <i class="fas fa-file-contract text-primary mr-1"></i> Data Dokumen & Customer
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm table-striped mb-0 sa-detail-table">
                                    <tbody>
                                        <tr>
                                            <td class="sa-field-label">Customer</td>
                                            <td class="sa-field-val font-weight-bold text-dark" id="detCustomer">-</td>
                                        </tr>
                                        <tr>
                                            <td class="sa-field-label">No DO</td>
                                            <td class="sa-field-val text-dark font-weight-bold" id="detNoDO">-</td>
                                        </tr>
                                        <tr>
                                            <td class="sa-field-label">Tgl DO</td>
                                            <td class="sa-field-val text-dark" id="detTglDO">-</td>
                                        </tr>
                                        <tr>
                                            <td class="sa-field-label">No Packing</td>
                                            <td class="sa-field-val text-dark" id="detNoPacking">-</td>
                                        </tr>
                                        <tr>
                                            <td class="sa-field-label">No Bale</td>
                                            <td class="sa-field-val font-weight-bold text-primary" id="detNoBale">-</td>
                                        </tr>
                                        <tr>
                                            <td class="sa-field-label">No SO</td>
                                            <td class="sa-field-val text-dark font-weight-bold" id="detNoSC">-</td>
                                        </tr>
                                        <tr>
                                            <td class="sa-field-label">Tgl SC</td>
                                            <td class="sa-field-val text-dark" id="detTglSC">-</td>
                                        </tr>
                                        <tr>
                                            <td class="sa-field-label">No Titipan</td>
                                            <td class="sa-field-val text-muted" id="detRowNoTitipan">-</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Panel Kanan: Data Produk & Spesifikasi -->
                    <div class="col-md-6 mb-3">
                        <div class="card h-100 border-0 shadow-sm" style="border-radius: 10px; overflow: hidden;">
                            <div class="card-header py-2 px-3 bg-white border-bottom font-weight-bold text-dark" style="font-size: 13px;">
                                <i class="fas fa-boxes text-info mr-1"></i> Data Produk & Spesifikasi
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm table-striped mb-0 sa-detail-table">
                                    <tbody>
                                        <tr>
                                            <td class="sa-field-label">Produk</td>
                                            <td class="sa-field-val text-dark font-weight-bold" id="detProduct">-</td>
                                        </tr>
                                        <tr>
                                            <td class="sa-field-label">Label Jual</td>
                                            <td class="sa-field-val">
                                                <span class="badge badge-light border text-dark font-weight-bold px-2 py-1" id="detLabelJual">-</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="sa-field-label">Cust Color</td>
                                            <td class="sa-field-val">
                                                <span class="badge badge-light text-primary border font-weight-bold px-2 py-1" id="detCustColor">-</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="sa-field-label">Rincian Qty</td>
                                            <td class="sa-field-val text-dark font-weight-bold" id="detRincianQty">-</td>
                                        </tr>
                                        <tr>
                                            <td class="sa-field-label">Jumlah Batch</td>
                                            <td class="sa-field-val text-dark" id="detBatch">-</td>
                                        </tr>
                                        <tr>
                                            <td class="sa-field-label">Ket. DO Detail</td>
                                            <td class="sa-field-val text-dark small" id="detDodtDesc" style="line-height: 1.4;">-</td>
                                        </tr>
                                        <tr>
                                            <td class="sa-field-label">Keterangan</td>
                                            <td class="sa-field-val text-muted small" id="detKet2" style="line-height: 1.4;">-</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bagian Upload Berkas Dokumen (2 Kolom Kartu Modern) -->
                <div class="row">
                    <!-- UPLOAD PACKING LIST -->
                    <div class="col-md-6 mb-3">
                        <div class="sa-upload-card h-100" style="border-left: 4px solid #3b82f6;">
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                <span class="font-weight-bold text-dark" style="font-size: 13px;">
                                    <i class="fas fa-file-pdf text-danger mr-1"></i> Packing List (PL)
                                </span>
                                <span id="badgePLStatus">-</span>
                            </div>

                            <!-- Info Jika PL Sudah Diupload -->
                            <div id="infoPLUploaded" class="mb-3 p-2 bg-light rounded border" style="display: none;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="mr-2 overflow-hidden">
                                        <div class="font-weight-bold text-truncate text-dark small" id="textPLFile">-</div>
                                        <div class="text-muted" style="font-size: 10.5px;" id="textPLMeta">-</div>
                                    </div>
                                    <div class="d-flex align-items-center" style="gap: 4px; flex-shrink: 0;">
                                        <button type="button" class="btn btn-xs btn-outline-primary btn-preview-pdf px-2 py-1" id="btnPreviewPL" title="Lihat PDF">
                                            <i class="fas fa-eye mr-1"></i> Lihat
                                        </button>
                                        <a href="#" target="_blank" class="btn btn-xs btn-outline-secondary px-2 py-1" id="btnDownloadPL" download title="Download PDF">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Form Upload PL -->
                            <form id="formUploadPL" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="upload_doc">
                                <input type="hidden" name="doc_type" value="packinglist">
                                <input type="hidden" name="id" id="uploadPLId">

                                <div class="mb-2 d-flex align-items-center" style="gap: 12px;">
                                    <div class="custom-control custom-radio custom-control-inline mb-0">
                                        <input type="radio" id="typePLFile" name="input_type" class="custom-control-input radio-input-type" value="file" checked data-target="pl">
                                        <label class="custom-control-label small font-weight-bold" for="typePLFile">Upload File</label>
                                    </div>
                                    <div class="custom-control custom-radio custom-control-inline mb-0">
                                        <input type="radio" id="typePLUrl" name="input_type" class="custom-control-input radio-input-type" value="url" data-target="pl">
                                        <label class="custom-control-label small font-weight-bold" for="typePLUrl">URL Link</label>
                                    </div>
                                </div>

                                <div id="containerPLFile" class="mb-2">
                                    <div class="custom-file">
                                        <input type="file" name="doc_file" class="custom-file-input custom-file-input-sm" id="inputPLFile" accept=".pdf">
                                        <label class="custom-file-label custom-file-label-sm small text-truncate" for="inputPLFile" data-browse="Pilih">Pilih file PDF...</label>
                                    </div>
                                    <div class="d-flex justify-content-between mt-1">
                                        <small class="text-muted" style="font-size: 10.5px;"><i class="fas fa-info-circle mr-1"></i>Maksimal 5MB, format .pdf</small>
                                    </div>
                                </div>

                                <div id="containerPLUrl" class="mb-2" style="display: none;">
                                    <input type="url" name="doc_url" class="form-control form-control-sm" placeholder="https://domain.com/file.pdf">
                                </div>

                                <button type="submit" class="btn btn-primary btn-sm btn-block font-weight-bold mt-2 shadow-sm">
                                    <i class="fas fa-cloud-upload-alt mr-1"></i> Simpan / Upload PL
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- UPLOAD SURAT JALAN -->
                    <div class="col-md-6 mb-3">
                        <div class="sa-upload-card h-100" style="border-left: 4px solid #10b981;">
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                <span class="font-weight-bold text-dark" style="font-size: 13px;">
                                    <i class="fas fa-file-invoice text-success mr-1"></i> Surat Jalan (SJ)
                                </span>
                                <span id="badgeSJStatus">-</span>
                            </div>

                            <!-- Info Jika SJ Sudah Diupload -->
                            <div id="infoSJUploaded" class="mb-3 p-2 bg-light rounded border" style="display: none;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="mr-2 overflow-hidden">
                                        <div class="font-weight-bold text-truncate text-dark small" id="textSJFile">-</div>
                                        <div class="text-muted" style="font-size: 10.5px;" id="textSJMeta">-</div>
                                    </div>
                                    <div class="d-flex align-items-center" style="gap: 4px; flex-shrink: 0;">
                                        <button type="button" class="btn btn-xs btn-outline-success btn-preview-pdf px-2 py-1" id="btnPreviewSJ" title="Lihat PDF">
                                            <i class="fas fa-eye mr-1"></i> Lihat
                                        </button>
                                        <a href="#" target="_blank" class="btn btn-xs btn-outline-secondary px-2 py-1" id="btnDownloadSJ" download title="Download PDF">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Form Upload SJ -->
                            <form id="formUploadSJ" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="upload_doc">
                                <input type="hidden" name="doc_type" value="suratjalan">
                                <input type="hidden" name="id" id="uploadSJId">

                                <div class="mb-2 d-flex align-items-center" style="gap: 12px;">
                                    <div class="custom-control custom-radio custom-control-inline mb-0">
                                        <input type="radio" id="typeSJFile" name="input_type" class="custom-control-input radio-input-type" value="file" checked data-target="sj">
                                        <label class="custom-control-label small font-weight-bold" for="typeSJFile">Upload File</label>
                                    </div>
                                    <div class="custom-control custom-radio custom-control-inline mb-0">
                                        <input type="radio" id="typeSJUrl" name="input_type" class="custom-control-input radio-input-type" value="url" data-target="sj">
                                        <label class="custom-control-label small font-weight-bold" for="typeSJUrl">URL Link</label>
                                    </div>
                                </div>

                                <div id="containerSJFile" class="mb-2">
                                    <div class="custom-file">
                                        <input type="file" name="doc_file" class="custom-file-input custom-file-input-sm" id="inputSJFile" accept=".pdf">
                                        <label class="custom-file-label custom-file-label-sm small text-truncate" for="inputSJFile" data-browse="Pilih">Pilih file PDF...</label>
                                    </div>
                                    <div class="d-flex justify-content-between mt-1">
                                        <small class="text-muted" style="font-size: 10.5px;"><i class="fas fa-info-circle mr-1"></i>Maksimal 5MB, format .pdf</small>
                                    </div>
                                </div>

                                <div id="containerSJUrl" class="mb-2" style="display: none;">
                                    <input type="url" name="doc_url" class="form-control form-control-sm" placeholder="https://domain.com/file.pdf">
                                </div>

                                <button type="submit" class="btn btn-success btn-sm btn-block font-weight-bold mt-2 shadow-sm">
                                    <i class="fas fa-cloud-upload-alt mr-1"></i> Simpan / Upload SJ
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Status Banner Publish & Customer View -->
                <div class="p-3 bg-white rounded border shadow-sm">
                    <div class="row align-items-center">
                        <div class="col-md-6 mb-2 mb-md-0 d-flex align-items-center" style="gap: 10px;">
                            <div style="width: 32px; height: 32px; border-radius: 8px; background: #eff6ff; color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                                <i class="fas fa-share-alt"></i>
                            </div>
                            <div>
                                <small class="text-muted font-weight-bold d-block text-uppercase" style="font-size: 10px;">Status Publikasi Portal</small>
                                <span id="detPublishInfo" class="small">-</span>
                            </div>
                        </div>
                        <div class="col-md-6 d-flex align-items-center" style="gap: 10px;">
                            <div style="width: 32px; height: 32px; border-radius: 8px; background: #ecfdf5; color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div>
                                <small class="text-muted font-weight-bold d-block text-uppercase" style="font-size: 10px;">Aktivitas Customer</small>
                                <span id="detViewInfo" class="small">-</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer py-2 px-3 bg-white border-top">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3 font-weight-bold" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 3: PREVIEW PDF
     ========================================================================= -->
<div class="modal fade" id="modalPreviewPDF" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" style="max-width: 90%; height: 90vh;" role="document">
        <div class="modal-content h-100">
            <div class="modal-header bg-dark text-white py-2 px-3">
                <h6 class="modal-title font-weight-bold" id="pdfPreviewTitle">
                    <i class="fas fa-file-pdf text-danger mr-2"></i>Preview PDF
                </h6>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0 h-100">
                <iframe id="pdfFrame" src="" style="width: 100%; height: calc(90vh - 110px); border: none;"></iframe>
            </div>
            <div class="modal-footer py-1 px-3">
                <a href="#" target="_blank" class="btn btn-sm btn-primary" id="btnOpenNewTab">
                    <i class="fas fa-external-link-alt mr-1"></i> Buka di Tab Baru
                </a>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<?php include $rootPath . '/includes/footer.php'; ?>

<!-- Plugins JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-fixedcolumns/js/dataTables.fixedColumns.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/toastr/toastr.min.js"></script>

<script>
$(document).ready(function () {
    const API_URL = '/gg_app/pages/sumber_agung/sumber_agung_serverside.php';

    let tableTitipan = null;
    let selectedIds = new Set();

    // Toastr options
    toastr.options = {
        closeButton: true,
        progressBar: true,
        positionClass: "toast-top-right",
        timeOut: 3000
    };

    // ─── INIT DATATABLES UTAMA ──────────────────────────────────────────────
    function initDataTable() {
        tableTitipan = $('#tableTitipan').DataTable({
            processing: true,
            serverSide: false, // Client-side DataTables on top of loaded JSON
            scrollX: true,
            scrollCollapse: true,
            autoWidth: false,
            fixedColumns: {
                left: 4
            },
            ajax: {
                url: API_URL,
                type: 'GET',
                data: function (d) {
                    d.action     = 'load_data';
                    d.date_from  = $('#filterDateFrom').val();
                    d.date_to    = $('#filterDateTo').val();
                    d.filter_doc = $('#filterDoc').val();
                    d.filter_pub = $('#filterPub').val();
                },
                dataSrc: function (json) {
                    if (json.status !== 'ok') {
                        toastr.error(json.message || 'Gagal memuat data');
                        return [];
                    }
                    return json.data || [];
                }
            },
            columns: [
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: function (data, type, row) {
                        const checked = selectedIds.has(row.id) ? 'checked' : '';
                        return `<input type="checkbox" class="row-checkbox" data-id="${row.id}" ${checked}>`;
                    }
                },
                { data: 'no', className: 'text-center' },
                {
                    data: 'tgl_kirim',
                    className: 'font-weight-bold text-dark',
                    render: d => d && d !== '-' ? d : '<span class="text-muted">-</span>'
                },
                { data: 'no_sc', render: d => `<strong>${d}</strong>` },
                { data: 'tgl_sc' },
                {
                    data: 'product_code',
                    className: 'text-center',
                    render: d => d ? `<span class="badge-prodcode">${d}</span>` : '-'
                },
                {
                    data: 'product_name',
                    render: d => `<b>${d || '-'}</b>`
                },
                {
                    data: 'label_jual',
                    render: d => d && d !== '-' ? `<span class="badge badge-light border text-dark font-weight-bold">${d}</span>` : '-'
                },
                {
                    data: 'cust_color',
                    className: 'text-center',
                    render: d => d && d !== '-' ? `<span class="badge badge-light border text-primary font-weight-bold">${d}</span>` : '-'
                },
                { data: 'no_titipan', render: d => d && d !== '-' ? `<span class="badge badge-light border font-weight-bold">${d}</span>` : '-' },
                { data: 'tgl_titipan' },
                { data: 'no_do' },
                { data: 'tgl_do' },
                {
                    data: 'no_packing',
                    render: d => d && d !== '-' ? `<span class="badge badge-light border text-dark font-weight-bold">${d}</span>` : '-'
                },
                {
                    data: 'no_bale',
                    render: d => d && d !== '-' ? `<span class="badge badge-light border text-primary font-weight-bold">${d}</span>` : '-'
                },
                {
                    data: 'do_qty',
                    className: 'text-right',
                    render: d => d !== null ? Number(d).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) : '-'
                },
                { data: 'uom' },
                {
                    data: 'dodtdesc',
                    render: function (data, type, row) {
                        const desc = row.dodtdesc || row.ket_titipan_2 || row.dodesc || '-';
                        if (!desc || desc === '-') return '-';
                        const short = desc.length > 30 ? desc.substring(0, 30) + '...' : desc;
                        return `<span title="${desc}">${short}</span>`;
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    render: function (data, type, row) {
                        if (row.has_packinglist) {
                            return `<span class="badge-doc-yes btn-open-doc" data-id="${row.id}" data-type="packinglist" style="cursor:pointer;" title="Klik untuk lihat file"><i class="fas fa-check"></i> Sudah</span>`;
                        }
                        return `<span class="badge-doc-no"><i class="fas fa-times"></i> Belum</span>`;
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    render: function (data, type, row) {
                        if (row.has_suratjalan) {
                            return `<span class="badge-doc-yes btn-open-doc" data-id="${row.id}" data-type="suratjalan" style="cursor:pointer;" title="Klik untuk lihat file"><i class="fas fa-check"></i> Sudah</span>`;
                        }
                        return `<span class="badge-doc-no"><i class="fas fa-times"></i> Belum</span>`;
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    render: function (data, type, row) {
                        if (row.is_published && row.is_viewed) {
                            return `<span class="badge-pub-viewed"><i class="fas fa-eye mr-1"></i> Sudah Dilihat</span>`;
                        } else if (row.is_published) {
                            return `<span class="badge-pub-published"><i class="fas fa-cloud-upload-alt mr-1"></i> Published</span>`;
                        } else {
                            return `<span class="badge-pub-draft">Belum Publish</span>`;
                        }
                    }
                },
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: function (data, type, row) {
                        return `
                            <div class="btn-action-wrap">
                                <button type="button" class="btn btn-action-item btn-action-detail btn-detail" data-id="${row.id}" title="Detail & Upload Dokumen">
                                    <i class="fas fa-search-plus"></i>
                                </button>
                                <button type="button" class="btn btn-action-item btn-action-delete btn-delete" data-id="${row.id}" data-no="${row.no_titipan}" title="Hapus Data">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        `;
                    }
                }
            ],
            order: [[2, 'desc']], // Urut berdasarkan Tanggal Kirim (kolom ke-3)
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Semua"]],
            pagingType: "simple_numbers",
            language: {
                processing: "Memproses...",
                search: "Cari:",
                lengthMenu: "Tampilkan _MENU_ data per halaman",
                info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
                infoEmpty: "Menampilkan 0 data",
                infoFiltered: "(disaring dari _MAX_ total data)",
                zeroRecords: "Tidak ada data titipan yang cocok",
                emptyTable: "Tidak ada data titipan yang tersedia",
                paginate: {
                    first: "Pertama",
                    last: "Terakhir",
                    next: "›",
                    previous: "‹"
                }
            },
            drawCallback: function () {
                updateFloatingBar();
                updateCheckAllState();
            }
        });
    }

    // ─── LOAD SUMMARY CARDS ─────────────────────────────────────────────────
    function loadSummary() {
        $.getJSON(API_URL, {
            action: 'get_summary',
            date_from: $('#filterDateFrom').val(),
            date_to: $('#filterDateTo').val()
        }, function (res) {
            if (res.status === 'ok' && res.summary) {
                $('#countBelumLengkap').text(Number(res.summary.belum_lengkap || 0).toLocaleString('id-ID'));
                $('#countPublished').text(Number(res.summary.published || 0).toLocaleString('id-ID'));
                $('#countSudahDilihat').text(Number(res.summary.sudah_dilihat || 0).toLocaleString('id-ID'));
            }
        });
    }

    // Initial load
    initDataTable();
    loadSummary();

    // ─── REFRESH & FILTER ───────────────────────────────────────────────────
    $('#btnRefresh').on('click', function () {
        selectedIds.clear();
        updateFloatingBar();
        tableTitipan.ajax.reload(null, false);
        loadSummary();
        toastr.info('Data diperbarui');
    });

    $('#filterForm').on('submit', function (e) {
        e.preventDefault();
        selectedIds.clear();
        updateFloatingBar();
        $('.sa-stat-card').removeClass('active');
        tableTitipan.ajax.reload();
        loadSummary();
    });

    $('#btnResetFilter').on('click', function () {
        $('#filterDateFrom').val('<?= date('Y-m-01') ?>');
        $('#filterDateTo').val('<?= date('Y-m-d') ?>');
        $('#filterDoc').val('');
        $('#filterPub').val('');
        $('.sa-stat-card').removeClass('active');
        selectedIds.clear();
        updateFloatingBar();
        tableTitipan.ajax.reload();
        loadSummary();
    });

    // ─── CARD CLICK FILTER ──────────────────────────────────────────────────
    $('.sa-stat-card').on('click', function () {
        const status = $(this).data('status');
        const isActive = $(this).hasClass('active');

        $('.sa-stat-card').removeClass('active');

        if (!isActive) {
            $(this).addClass('active');
            if (status === 'belum_lengkap') {
                $('#filterDoc').val('belum_lengkap');
                $('#filterPub').val('');
            } else if (status === 'published') {
                $('#filterDoc').val('');
                $('#filterPub').val('published');
            } else if (status === 'sudah_dilihat') {
                $('#filterDoc').val('');
                $('#filterPub').val('sudah_dilihat');
            }
        } else {
            $('#filterDoc').val('');
            $('#filterPub').val('');
        }
        tableTitipan.ajax.reload();
    });

    // ─── CHECKBOX MANAGEMENT ───────────────────────────────────────────────
    $('#checkAll').on('change', function () {
        const isChecked = $(this).is(':checked');
        $('.row-checkbox').each(function () {
            const id = parseInt($(this).data('id'));
            $(this).prop('checked', isChecked);
            if (isChecked) {
                selectedIds.add(id);
            } else {
                selectedIds.delete(id);
            }
        });
        updateFloatingBar();
    });

    $(document).on('change', '.row-checkbox', function () {
        const id = parseInt($(this).data('id'));
        if ($(this).is(':checked')) {
            selectedIds.add(id);
        } else {
            selectedIds.delete(id);
        }
        updateCheckAllState();
        updateFloatingBar();
    });

    function updateCheckAllState() {
        const totalRows = $('.row-checkbox').length;
        const totalChecked = $('.row-checkbox:checked').length;
        $('#checkAll').prop('checked', totalRows > 0 && totalRows === totalChecked);
    }

    function updateFloatingBar() {
        const count = selectedIds.size;
        $('#selectedCount').text(count);
        if (count > 0) {
            $('#floatingBar').addClass('show');
        } else {
            $('#floatingBar').removeClass('show');
        }
    }

    $('#btnCancelSelect').on('click', function () {
        selectedIds.clear();
        $('.row-checkbox').prop('checked', false);
        $('#checkAll').prop('checked', false);
        updateFloatingBar();
    });

    // ─── MASS PUBLISH ──────────────────────────────────────────────────────
    $('#btnPublishSelected').on('click', function () {
        const count = selectedIds.size;
        if (count === 0) return;

        Swal.fire({
            title: 'Publish ke Web Portal?',
            text: `${count} data titipan akan dipublikasikan ke Portal Sumber Agung dan dapat dilihat oleh customer.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-cloud-upload-alt mr-1"></i> Ya, Publish!',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#2563eb'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post(API_URL, {
                    action: 'publish',
                    ids: Array.from(selectedIds)
                }, function (res) {
                    if (res.status === 'ok') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: res.message || `${res.count} data berhasil dipublish.`,
                            timer: 2000,
                            showConfirmButton: false
                        });
                        selectedIds.clear();
                        updateFloatingBar();
                        tableTitipan.ajax.reload(null, false);
                        loadSummary();
                    } else {
                        Swal.fire('Gagal', res.message || 'Gagal mempublish data.', 'error');
                    }
                }, 'json').fail(function () {
                    Swal.fire('Error', 'Gagal menghubungi server.', 'error');
                });
            }
        });
    });

    // =========================================================================
    // SEARCH TITIPAN MODAL (Model Search PO di Tracking PO)
    // =========================================================================
    let selectedErpMap = {};       // Menyimpan objek titipan terpilih: { key: rowObj }
    let currentErpGridData = [];   // Cache data halaman saat ini
    let currentErpSearchPage = 1;
    let totalErpSearchPages = 1;

    function updateErpSelectionUI() {
        const selectedCount = Object.keys(selectedErpMap).length;
        $('#erpSelectedCountBadge').text(selectedCount + ' Titipan Terpilih');
        $('#btnSubmitErpSearch').prop('disabled', selectedCount === 0);

        // Update checkbox dan highlight kelas table-active pada baris halaman saat ini
        $('#erpSearchTableBody tr').each(function () {
            const key = $(this).data('key');
            const isTracked = $(this).hasClass('row-disabled');
            if (isTracked) return;

            const isChecked = !!selectedErpMap[key];
            $(this).find('.po-row-check').prop('checked', isChecked);
            if (isChecked) {
                $(this).addClass('table-active');
            } else {
                $(this).removeClass('table-active');
            }
        });
    }

    function renderErpSearchPagination(currentPage, totalPages, totalRows, limit) {
        const startRow = totalRows > 0 ? ((currentPage - 1) * limit + 1) : 0;
        const endRow = Math.min(currentPage * limit, totalRows);
        $('#erpSearchPaginationInfo').html('Menampilkan <strong>' + startRow + ' - ' + endRow + '</strong> dari <strong>' + totalRows.toLocaleString('id-ID') + '</strong> data');

        let navHtml = '';
        if (totalPages <= 1) {
            $('#erpSearchPaginationNav').html('');
            return;
        }

        // Tombol Previous
        const prevDisabled = currentPage === 1 ? 'disabled' : '';
        navHtml += '<button type="button" class="btn btn-outline-secondary btn-erp-page" data-page="' + (currentPage - 1) + '" ' + prevDisabled + '>&laquo;</button>';

        // Logika tampilan nomor halaman
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);

        if (startPage > 1) {
            navHtml += '<button type="button" class="btn btn-outline-secondary btn-erp-page" data-page="1">1</button>';
            if (startPage > 2) {
                navHtml += '<button type="button" class="btn btn-outline-secondary" disabled>...</button>';
            }
        }

        for (let p = startPage; p <= endPage; p++) {
            const activeClass = (p === currentPage) ? 'btn-primary active text-white' : 'btn-outline-secondary';
            navHtml += '<button type="button" class="btn ' + activeClass + ' btn-erp-page" data-page="' + p + '">' + p + '</button>';
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                navHtml += '<button type="button" class="btn btn-outline-secondary" disabled>...</button>';
            }
            navHtml += '<button type="button" class="btn btn-outline-secondary btn-erp-page" data-page="' + totalPages + '">' + totalPages + '</button>';
        }

        // Tombol Next
        const nextDisabled = currentPage === totalPages ? 'disabled' : '';
        navHtml += '<button type="button" class="btn btn-outline-secondary btn-erp-page" data-page="' + (currentPage + 1) + '" ' + nextDisabled + '>&raquo;</button>';

        $('#erpSearchPaginationNav').html(navHtml);
    }

    function loadErpSearchGrid(page) {
        currentErpSearchPage = page || 1;
        $('#loadingOverlayErpSearch').fadeIn(100);

        const params = {
            action: 'search_titipan',
            page: currentErpSearchPage,
            limit: 25,
            date_from: $.trim($('#erpDateFrom').val()),
            date_to: $.trim($('#erpDateTo').val()),
            search_field: $('#erpSearchField').val(),
            keyword: $.trim($('#erpSearchKeyword').val())
        };

        $.getJSON(API_URL, params, function (res) {
            $('#loadingOverlayErpSearch').fadeOut(100);
            if (res.status !== 'ok') {
                Swal.fire('Error', res.message || 'Gagal memuat data DO dari ERP.', 'error');
                return;
            }

            currentErpGridData = res.data || [];
            totalErpSearchPages = res.total_pages || 1;
            let rowsHtml = '';

            if (currentErpGridData.length === 0) {
                rowsHtml = '<tr><td colspan="18" class="text-center py-4 text-muted"><i class="fas fa-info-circle mr-1"></i> Tidak ada data Delivery Order (DO) ditemukan pada periode tanggal ini.</td></tr>';
            } else {
                $.each(currentErpGridData, function (i, r) {
                    const key = r.unique_key || (r.no_do + '|' + r.no_sc + '|' + r.product_code + '|' + (r.no_bale || ''));
                    const isSelected = !!selectedErpMap[key];
                    const isSaved = !!r.already_saved;

                    let trClass = '';
                    if (isSaved) trClass = 'row-disabled';
                    else if (isSelected) trClass = 'table-active';

                    let checkAttr = '';
                    if (isSaved) checkAttr = 'disabled title="Data DO/Bale ini sudah tersimpan"';
                    else if (isSelected) checkAttr = 'checked';

                    const statusBadge = isSaved
                        ? '<span class="badge badge-secondary px-2 py-1"><i class="fas fa-check mr-1"></i>Sudah Ada</span>'
                        : '<span class="badge badge-warning px-2 py-1 text-dark"><i class="fas fa-edit mr-1"></i>Draft (O)</span>';

                    const custDisplay = '<span class="font-weight-bold text-dark">' + (r.cust_name || '-') + '</span>' +
                        (r.cust_id ? ' <span class="badge badge-light border text-muted small">' + r.cust_id + '</span>' : '');

                    const desc = r.dodtdesc || r.dodesc || '-';
                    const qtyYardDisplay = r.totqtyyard !== null ? Number(r.totqtyyard).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) : '-';
                    const qtyMDisplay = r.totqtym !== null ? Number(r.totqtym).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) : '-';
                    const batchDisplay = r.countbatch !== null ? r.countbatch : '-';

                    rowsHtml += '<tr class="' + trClass + '" data-key="' + key + '" data-index="' + i + '">' +
                        '<td class="po-select-col">' +
                            '<div class="po-seq-wrap">' +
                                '<input type="checkbox" class="po-row-check" ' + checkAttr + '>' +
                                '<span class="po-row-seq">' + r.seq + '</span>' +
                            '</div>' +
                        '</td>' +
                        '<td class="text-center font-weight-bold text-dark">' + (r.no_do || '-') + '</td>' +
                        '<td class="text-center text-muted">' + (r.tgl_do || '-') + '</td>' +
                        '<td class="text-center"><span class="badge badge-light border text-dark font-weight-bold">' + (r.no_packing || '-') + '</span></td>' +
                        '<td class="text-center font-weight-bold"><span class="po-badge-code">' + (r.no_bale || '-') + '</span></td>' +
                        '<td class="text-right font-weight-bold text-primary">' + qtyYardDisplay + '</td>' +
                        '<td class="text-right text-muted">' + qtyMDisplay + '</td>' +
                        '<td class="text-center"><span class="badge badge-light border text-info font-weight-bold">' + batchDisplay + '</span></td>' +
                        '<td>' + custDisplay + '</td>' +
                        '<td class="text-center font-weight-bold text-primary">' + (r.no_sc || '-') + '</td>' +
                        '<td class="text-center text-muted">' + (r.tgl_sc || '-') + '</td>' +
                        '<td class="text-center"><span class="badge-prodcode">' + (r.product_code || '-') + '</span></td>' +
                        '<td><b>' + (r.product_name || '-') + '</b></td>' +
                        '<td class="text-center"><span class="badge badge-light border text-dark font-weight-bold">' + (r.label_jual || '-') + '</span></td>' +
                        '<td class="text-center"><span class="badge badge-light border text-primary font-weight-bold">' + (r.cust_color || '-') + '</span></td>' +
                        '<td class="text-center">' + (r.uom || '-') + '</td>' +
                        '<td class="text-truncate text-break" style="max-width: 220px;" title="' + desc + '">' + desc + '</td>' +
                        '<td class="text-center">' + statusBadge + '</td>' +
                    '</tr>';
                });
            }

            $('#erpSearchTableBody').html(rowsHtml);
            renderErpSearchPagination(currentErpSearchPage, totalErpSearchPages, res.total_rows || 0, res.limit || 25);
            updateErpSelectionUI();
        }).fail(function () {
            $('#loadingOverlayErpSearch').fadeOut(100);
            Swal.fire('Error', 'Gagal menghubungi server backend ERP.', 'error');
        });
    }

    // Buka Modal Search Titipan saat tombol Tambah Data diklik
    $('#btnTambahData').on('click', function () {
        const today = new Date().toISOString().split('T')[0];
        $('#inputErpTanggalKirim').val(today);
        $('#erpDateFrom').val(today);
        $('#erpDateTo').val(today);
        $('#erpSearchKeyword').val('');
        $('#erpSearchField').val('all');
        selectedErpMap = {};
        updateErpSelectionUI();
        $('#modalTambahData').modal('show');
        loadErpSearchGrid(1);
    });

    // Pindah halaman via pagination
    $(document).on('click', '.btn-erp-page', function () {
        const page = parseInt($(this).data('page'));
        if (page && page >= 1 && page <= totalErpSearchPages && page !== currentErpSearchPage) {
            loadErpSearchGrid(page);
        }
    });

    // Cari via toolbar
    $('#btnErpSearchApply').on('click', function () {
        loadErpSearchGrid(1);
    });
    $('#erpSearchKeyword').on('keypress', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            loadErpSearchGrid(1);
        }
    });

    // Event perubahan tanggal DO
    $('#erpDateFrom, #erpDateTo').on('change', function () {
        loadErpSearchGrid(1);
    });

    // Reset toolbar pencarian
    $('#btnErpSearchReset').on('click', function () {
        const today = new Date().toISOString().split('T')[0];
        $('#erpDateFrom').val(today);
        $('#erpDateTo').val(today);
        $('#erpSearchKeyword').val('');
        $('#erpSearchField').val('all');
        loadErpSearchGrid(1);
    });

    // Select All
    $('#btnErpSearchSelectAll').on('click', function () {
        const availableRows = currentErpGridData.filter(function (r) { return !r.already_saved; });
        if (availableRows.length === 0) return;

        const allSelectedInView = availableRows.every(function (r) {
            const key = r.unique_key || (r.no_do + '|' + r.no_sc + '|' + r.product_code + '|' + (r.no_bale || ''));
            return !!selectedErpMap[key];
        });

        $.each(availableRows, function (i, r) {
            const key = r.unique_key || (r.no_do + '|' + r.no_sc + '|' + r.product_code + '|' + (r.no_bale || ''));
            if (allSelectedInView) {
                delete selectedErpMap[key];
            } else {
                selectedErpMap[key] = r;
            }
        });

        updateErpSelectionUI();
    });

    // Klik Baris Tabel untuk Toggle Select
    $(document).on('click', '#erpSearchTableBody tr', function (e) {
        if ($(this).hasClass('row-disabled')) return;
        if ($(e.target).is('a') || $(e.target).closest('a').length) return;

        const idx = $(this).data('index');
        const r = currentErpGridData[idx];
        if (!r) return;
        const key = r.unique_key || (r.no_do + '|' + r.no_sc + '|' + r.product_code + '|' + (r.no_bale || ''));

        if (selectedErpMap[key]) {
            delete selectedErpMap[key];
        } else {
            selectedErpMap[key] = r;
        }

        updateErpSelectionUI();
    });

    // Kosongkan Tanggal Kirim
    $('#btnClearErpTanggalKirim').on('click', function () {
        $('#inputErpTanggalKirim').val('');
    });

    // Submit Titipan Terpilih
    $('#btnSubmitErpSearch').on('click', function () {
        const selectedItems = Object.values(selectedErpMap);
        const count = selectedItems.length;
        if (count === 0) return;

        const tglKirimVal = $.trim($('#inputErpTanggalKirim').val());
        if (!tglKirimVal) {
            Swal.fire({
                icon: 'warning',
                title: 'Tanggal Kirim Wajib Diisi',
                text: 'Harap tentukan Tanggal Kirim terlebih dahulu sebelum menyimpan data titipan.',
                confirmButtonColor: '#2563eb'
            });
            $('#inputErpTanggalKirim').focus();
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');

        $.post(API_URL, {
            action: 'submit_titipan',
            tgl_kirim: tglKirimVal,
            items: JSON.stringify(selectedItems)
        }, function (res) {
            $btn.prop('disabled', false).html('<i class="fas fa-paper-plane mr-1"></i> Submit');
            if (res.status === 'ok') {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: res.message || (count + ' data titipan berhasil disimpan.'),
                    timer: 2000,
                    showConfirmButton: false
                });
                $('#modalTambahData').modal('hide');
                selectedErpMap = {};
                tableTitipan.ajax.reload(null, false);
                loadSummary();
            } else {
                Swal.fire('Gagal Menyimpan', res.message || 'Terjadi kesalahan.', 'error');
            }
        }, 'json').fail(function () {
            $btn.prop('disabled', false).html('<i class="fas fa-paper-plane mr-1"></i> Submit');
            Swal.fire('Error', 'Gagal terhubung ke server.', 'error');
        });
    });

    // ─── DETAIL & UPLOAD MODAL ──────────────────────────────────────────────
    $(document).on('click', '.btn-detail', function () {
        const id = $(this).data('id');
        openDetailModal(id);
    });

    function openDetailModal(id) {
        $('#uploadPLId').val(id);
        $('#uploadSJId').val(id);

        // Reset form & custom file labels
        if ($('#formUploadPL').length) $('#formUploadPL')[0].reset();
        if ($('#formUploadSJ').length) $('#formUploadSJ')[0].reset();
        $('#containerPLFile .custom-file-label').removeClass('selected').text('Pilih file PDF...');
        $('#containerSJFile .custom-file-label').removeClass('selected').text('Pilih file PDF...');
        $('#typePLFile').prop('checked', true);
        $('#typeSJFile').prop('checked', true);
        $('#containerPLFile').show();
        $('#containerPLUrl').hide();
        $('#containerSJFile').show();
        $('#containerSJUrl').hide();

        $.getJSON(API_URL, { action: 'get_detail', id: id }, function (res) {
            if (res.status !== 'ok' || !res.data) {
                toastr.error(res.message || 'Gagal memuat detail data.');
                return;
            }

            const d = res.data;
            $('#detTglKirim').text(d.tgl_kirim || '-');
            const noBaleDisplay = d.no_bale && d.no_bale !== '-' ? `${d.no_do} (Bale: ${d.no_bale})` : (d.no_titipan && d.no_titipan !== '-' ? d.no_titipan : d.no_do);
            $('#detNoTitipan').text(noBaleDisplay);
            $('#detRowNoTitipan').text(d.no_titipan || '-');
            $('#detNoDO').text(d.no_do || '-');
            $('#detTglDO').text(d.tgl_do || '-');
            $('#detNoPacking').text(d.no_packing || '-');
            $('#detNoBale').text(d.no_bale || '-');
            $('#detCustomer').text(d.cust_name ? `${d.cust_name} (${d.cust_id})` : '-');

            $('#detNoSC').text(d.no_sc || '-');
            $('#detTglSC').text(d.tgl_sc || '-');
            $('#detProduct').text(`${d.product_name} (${d.product_code})`);
            $('#detLabelJual').text(d.label_jual || '-');
            $('#detCustColor').text(d.cust_color || '-');

            let rincianQtyText = '';
            if (d.totqtyyard !== null) {
                rincianQtyText += `${Number(d.totqtyyard).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} Yard`;
            }
            if (d.totqtym !== null) {
                rincianQtyText += (rincianQtyText ? ' / ' : '') + `${Number(d.totqtym).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} Meter`;
            }
            $('#detRincianQty').text(rincianQtyText || (d.qty_kirim !== null ? `${Number(d.qty_kirim).toLocaleString('id-ID')} ${d.uom}` : '-'));
            $('#detBatch').text(d.countbatch !== null && d.countbatch !== undefined ? `${d.countbatch} Roll / Batch` : '-');
            $('#detDodtDesc').text(d.dodtdesc || '-');
            $('#detQty').text(d.qty_kirim !== null ? `${Number(d.qty_kirim).toLocaleString('id-ID')} ${d.uom}` : '-');
            $('#detKet2').text(d.ket_titipan_2 || d.dodesc || '-');

            // Packing List status
            if (d.packinglist_file) {
                $('#badgePLStatus').html('<span class="badge badge-success px-2 py-1"><i class="fas fa-check mr-1"></i> Sudah Ada</span>');
                $('#textPLFile').text(d.packinglist_file.split('/').pop());
                $('#textPLMeta').text(`Diupload oleh: ${d.packinglist_by || '-'} pada ${d.packinglist_at || '-'}`);
                $('#btnPreviewPL').attr('data-url', d.packinglist_file);
                $('#btnDownloadPL').attr('href', d.packinglist_file);
                $('#infoPLUploaded').show();
            } else {
                $('#badgePLStatus').html('<span class="badge badge-warning px-2 py-1"><i class="fas fa-clock mr-1"></i> Belum Ada</span>');
                $('#infoPLUploaded').hide();
            }

            // Surat Jalan status
            if (d.suratjalan_file) {
                $('#badgeSJStatus').html('<span class="badge badge-success px-2 py-1"><i class="fas fa-check mr-1"></i> Sudah Ada</span>');
                $('#textSJFile').text(d.suratjalan_file.split('/').pop());
                $('#textSJMeta').text(`Diupload oleh: ${d.suratjalan_by || '-'} pada ${d.suratjalan_at || '-'}`);
                $('#btnPreviewSJ').attr('data-url', d.suratjalan_file);
                $('#btnDownloadSJ').attr('href', d.suratjalan_file);
                $('#infoSJUploaded').show();
            } else {
                $('#badgeSJStatus').html('<span class="badge badge-warning px-2 py-1"><i class="fas fa-clock mr-1"></i> Belum Ada</span>');
                $('#infoSJUploaded').hide();
            }

            // Publish info
            if (d.is_published) {
                $('#detPublishInfo').html(`<span class="text-primary font-weight-bold"><i class="fas fa-check-circle mr-1"></i> Published</span> <span class="text-muted small">(${d.published_by || '-'} pada ${d.published_at || '-'})</span>`);
            } else {
                $('#detPublishInfo').html('<span class="badge badge-secondary px-2 py-1">Belum dipublish</span>');
            }

            if (d.is_viewed) {
                $('#detViewInfo').html(`<span class="text-success font-weight-bold"><i class="fas fa-eye mr-1"></i> Sudah dilihat</span> <span class="text-muted small">(${d.viewed_by || '-'} pada ${d.viewed_at || '-'})</span>`);
            } else {
                $('#detViewInfo').html('<span class="badge badge-light border text-muted px-2 py-1">Belum dibuka customer</span>');
            }

            $('#modalDetail').modal('show');
        });
    }

    // Dynamic filename for custom file input
    $(document).on('change', '.custom-file-input', function () {
        var fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').addClass("selected").html(fileName || 'Pilih file PDF...');
    });

    // Radio input toggle (file vs url)
    $('.radio-input-type').on('change', function () {
        const target = $(this).data('target');
        const val = $(this).val();
        if (target === 'pl') {
            if (val === 'file') {
                $('#containerPLFile').show();
                $('#containerPLUrl').hide();
            } else {
                $('#containerPLFile').hide();
                $('#containerPLUrl').show();
            }
        } else if (target === 'sj') {
            if (val === 'file') {
                $('#containerSJFile').show();
                $('#containerSJUrl').hide();
            } else {
                $('#containerSJFile').hide();
                $('#containerSJUrl').show();
            }
        }
    });

    // UPLOAD PACKING LIST FORM
    $('#formUploadPL').on('submit', function (e) {
        e.preventDefault();
        const fileInput = $(this).find('input[name="doc_file"]')[0];
        if (fileInput && fileInput.files && fileInput.files[0]) {
            if (fileInput.files[0].size > 5 * 1024 * 1024) {
                Swal.fire('File Terlalu Besar', 'Ukuran file PDF maksimal 5MB.', 'warning');
                return;
            }
        }

        const formData = new FormData(this);
        const submitBtn = $(this).find('button[type="submit"]');

        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Mengupload...');

        $.ajax({
            url: API_URL,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (res) {
                submitBtn.prop('disabled', false).html('<i class="fas fa-upload mr-1"></i> Upload PL');
                if (res.status === 'ok') {
                    toastr.success(res.message);
                    const currentId = $('#uploadPLId').val();
                    openDetailModal(currentId);
                    tableTitipan.ajax.reload(null, false);
                    loadSummary();
                } else {
                    Swal.fire('Gagal Upload', res.message || 'Terjadi kesalahan.', 'error');
                }
            },
            error: function () {
                submitBtn.prop('disabled', false).html('<i class="fas fa-upload mr-1"></i> Upload PL');
                Swal.fire('Error', 'Gagal menghubungi server.', 'error');
            }
        });
    });

    // UPLOAD SURAT JALAN FORM
    $('#formUploadSJ').on('submit', function (e) {
        e.preventDefault();
        const fileInput = $(this).find('input[name="doc_file"]')[0];
        if (fileInput && fileInput.files && fileInput.files[0]) {
            if (fileInput.files[0].size > 5 * 1024 * 1024) {
                Swal.fire('File Terlalu Besar', 'Ukuran file PDF maksimal 5MB.', 'warning');
                return;
            }
        }

        const formData = new FormData(this);
        const submitBtn = $(this).find('button[type="submit"]');

        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Mengupload...');

        $.ajax({
            url: API_URL,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (res) {
                submitBtn.prop('disabled', false).html('<i class="fas fa-upload mr-1"></i> Upload SJ');
                if (res.status === 'ok') {
                    toastr.success(res.message);
                    const currentId = $('#uploadSJId').val();
                    openDetailModal(currentId);
                    tableTitipan.ajax.reload(null, false);
                    loadSummary();
                } else {
                    Swal.fire('Gagal Upload', res.message || 'Terjadi kesalahan.', 'error');
                }
            },
            error: function () {
                submitBtn.prop('disabled', false).html('<i class="fas fa-upload mr-1"></i> Upload SJ');
                Swal.fire('Error', 'Gagal menghubungi server.', 'error');
            }
        });
    });

    // ─── PDF PREVIEW ────────────────────────────────────────────────────────
    $(document).on('click', '.btn-preview-pdf', function () {
        const fileUrl = $(this).attr('data-url');
        if (!fileUrl) return;

        $('#pdfFrame').attr('src', fileUrl);
        $('#btnOpenNewTab').attr('href', fileUrl);
        $('#modalPreviewPDF').modal('show');
    });

    $(document).on('click', '.btn-open-doc', function () {
        const id = $(this).data('id');
        openDetailModal(id);
    });

    // ─── DELETE DATA ────────────────────────────────────────────────────────
    $(document).on('click', '.btn-delete', function () {
        const id = $(this).data('id');
        const noTitipan = $(this).data('no');

        Swal.fire({
            title: 'Hapus Data Titipan?',
            html: `Apakah Anda yakin ingin menghapus titipan <b>${noTitipan}</b>?<br><small class="text-muted">Data akan dihapus dari daftar titipan internal.</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-trash-alt mr-1"></i> Ya, Hapus!',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#dc3545'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post(API_URL, {
                    action: 'delete_data',
                    id: id
                }, function (res) {
                    if (res.status === 'ok') {
                        toastr.success(res.message || 'Data berhasil dihapus');
                        selectedIds.delete(id);
                        updateFloatingBar();
                        tableTitipan.ajax.reload(null, false);
                        loadSummary();
                    } else {
                        Swal.fire('Gagal', res.message || 'Gagal menghapus data.', 'error');
                    }
                }, 'json').fail(function () {
                    Swal.fire('Error', 'Gagal menghubungi server.', 'error');
                });
            }
        });
    });
});
</script>
