<?php
/**
 * Tracking PO Online - Monitoring Pengadaan Online
 * Halaman pemantauan PO Online, PR, Kedatangan Barang (GRN), dan Pembayaran (CP/RFP)
 * Data bersumber langsung dari database PostgreSQL ERP (koneksi3.php)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootPath = dirname(__DIR__, 3);
require_once $rootPath . '/koneksi.php';

// Validasi Sesi Login
if (empty($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu untuk mengakses menu ini.";
    header('Location: /gg_app/login.php');
    exit;
}

$currentUserName   = trim($_SESSION['UserName'] ?? '');
$currentNamaLengkap = trim($_SESSION['NamaLengkap'] ?? $currentUserName);
$themeColor        = $_SESSION['Theme'] ?? 'primary';

// Include Header & Sidebar standar GG App
$headerPath  = file_exists($rootPath . '/includes/header.php')  ? ($rootPath . '/includes/header.php')  : ($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
$sidebarPath = file_exists($rootPath . '/includes/sidebar.php') ? ($rootPath . '/includes/sidebar.php') : ($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
include($headerPath);
include($sidebarPath);

// Default Filter Tanggal: Bulan Berjalan
$defaultDateFrom = date('Y-m-01');
$defaultDateTo   = date('Y-m-d');
?>

<style>
/* =========================================================
   TRACKING PO ONLINE - SUMMARY STAT CARDS
   ========================================================= */
.tracking-summary-row {
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}
.tracking-stat-card {
    flex: 1;
    min-width: 200px;
    background: #ffffff;
    border-radius: 12px;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.04);
    border: 1px solid #e2e8f0;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    overflow: hidden;
    user-select: none;
}
.tracking-stat-card::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    border-radius: 12px 0 0 12px;
    transition: width 0.2s ease;
}
.tracking-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.08);
}
.tracking-stat-card.active {
    border-color: currentColor;
    box-shadow: 0 4px 14px rgba(0,0,0,0.12);
    background: #fdfefe;
}
.tracking-stat-card.active::before {
    width: 6px;
}

/* Card Specific Colors */
.tracking-stat-card[data-status=""]::before { background: #3b82f6; }
.tracking-stat-card[data-status="Approved"]::before { background: #8b5cf6; }
.tracking-stat-card[data-status="Sudah Dibayar"]::before { background: #10b981; }
.tracking-stat-card[data-status="Sudah Datang"]::before { background: #06b6d4; }
.tracking-stat-card[data-status="Belum Datang"]::before { background: #f59e0b; }

.stat-icon-box {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}
.stat-icon-box.bg-blue { background: #eff6ff; color: #3b82f6; }
.stat-icon-box.bg-violet { background: #f5f3ff; color: #8b5cf6; }
.stat-icon-box.bg-emerald { background: #ecfdf5; color: #10b981; }
.stat-icon-box.bg-cyan { background: #ecfeff; color: #0891b2; }
.stat-icon-box.bg-amber { background: #fffbeb; color: #d97706; }

.stat-info .stat-count {
    font-size: 26px;
    font-weight: 800;
    line-height: 1.1;
    color: #0f172a;
}
.stat-info .stat-label {
    font-size: 11.5px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-top: 2px;
}
.stat-info .stat-sublabel {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 1px;
}

/* =========================================================
   FILTER BAR
   ========================================================= */
.filter-clean-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 14px 18px 8px 18px;
    margin-bottom: 18px;
}

/* =========================================================
   DATA TABLE STYLING
   ========================================================= */
#trackingPoOnlineTable {
    border-collapse: collapse !important;
    width: 100% !important;
    margin-bottom: 0 !important;
}
.dataTables_scrollHeadInner,
.dataTables_scrollHeadInner table {
    width: 100% !important;
}
.dataTables_scrollHead th,
#trackingPoOnlineTable thead th {
    background-color: #f1f5f9 !important;
    color: #334155 !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    text-align: center !important;
    vertical-align: middle !important;
    white-space: nowrap !important;
    padding: 8px 10px !important;
    border-top: 1px solid #e2e8f0 !important;
    border-bottom: 2px solid #cbd5e1 !important;
    border-right: 1px solid #e2e8f0 !important;
}
#trackingPoOnlineTable tbody td {
    vertical-align: middle !important;
    padding: 7px 9px !important;
    font-size: 12px;
    color: #1e293b;
    border-bottom: 1px solid #edf2f7 !important;
    border-right: 1px solid #edf2f7 !important;
    background-color: #ffffff;
    white-space: nowrap;
}
#trackingPoOnlineTable tbody tr:hover td {
    background-color: #f8fafc !important;
}

/* Code badges */
.po-badge-code {
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 4px;
    background-color: #eff6ff;
    color: #1e40af;
    border: 1px solid #bfdbfe;
    display: inline-block;
}
.pr-badge-code {
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 4px;
    background-color: #fefce8;
    color: #854d0e;
    border: 1px solid #fef08a;
    display: inline-block;
}
.grn-badge-code {
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 4px;
    background-color: #ecfeff;
    color: #0e7490;
    border: 1px solid #a5f3fc;
    display: inline-block;
}
.cp-badge-code {
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 4px;
    background-color: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
    display: inline-block;
}

/* Table Loading Overlay */
.table-loading-overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(255, 255, 255, 0.75);
    z-index: 10;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    backdrop-filter: blur(1px);
}

/* Checkbox Table Styling */
.po-row-checkbox, #checkAllPo {
    cursor: pointer;
    width: 16px;
    height: 16px;
    accent-color: #2563eb;
    vertical-align: middle;
}
</style>

<div class="wrapper">
<div class="content-wrapper">
    <!-- Header Content -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0 font-weight-bold text-dark">
                        <i class="fas fa-shopping-cart text-primary mr-2"></i>Tracking PO Online
                    </h1>
                    <small class="text-muted">Pemantauan alur dokumen pengadaan online, PR, kedatangan barang (GRN), dan pembayaran (CP/RFP)</small>
                </div>
                <div class="col-sm-6 text-right">
                    <ol class="breadcrumb float-sm-right bg-transparent p-0 mb-0">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                        <li class="breadcrumb-item">Purchase Request</li>
                        <li class="breadcrumb-item active">Tracking PO Online</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <section class="content">
        <div class="container-fluid">

            <!-- 5 Summary Stat Cards -->
            <div class="tracking-summary-row" id="summaryCardsContainer">
                <!-- Card 0: Total PO -->
                <div class="tracking-stat-card active" data-filter-type="all" data-status="" id="cardTotalPo" title="Klik untuk menampilkan semua data PO Online">
                    <div class="stat-icon-box bg-blue">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-count" id="countTotalPo">0</div>
                        <div class="stat-label">Total PO</div>
                        <div class="stat-sublabel">Semua Pengadaan</div>
                    </div>
                </div>

                <!-- Card 1: Sudah Dibayar -->
                <div class="tracking-stat-card" data-filter-type="bayar" data-status="Sudah Dibayar" id="cardPaid" title="Klik untuk memfilter PO yang sudah lunas dibayar (Sudah Ada No CP)">
                    <div class="stat-icon-box bg-emerald">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-count" id="countPaid">0</div>
                        <div class="stat-label">Sudah Dibayar</div>
                        <div class="stat-sublabel">Sudah Ada No CP</div>
                    </div>
                </div>

                <!-- Card 2: Belum Dibayar -->
                <div class="tracking-stat-card" data-filter-type="bayar" data-status="Belum Dibayar" id="cardUnpaid" title="Klik untuk memfilter PO yang belum dibayar (Belum Ada No CP)">
                    <div class="stat-icon-box bg-rose" style="background-color: #ffe4e6; color: #e11d48;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-count" id="countUnpaid">0</div>
                        <div class="stat-label">Belum Dibayar</div>
                        <div class="stat-sublabel">Belum Ada No CP</div>
                    </div>
                </div>

                <!-- Card 3: Sudah Datang -->
                <div class="tracking-stat-card" data-filter-type="datang" data-status="Sudah Datang" id="cardArrived" title="Klik untuk memfilter PO yang barangnya sudah datang (Ada No GRN)">
                    <div class="stat-icon-box bg-cyan">
                        <i class="fas fa-truck-loading"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-count" id="countArrived">0</div>
                        <div class="stat-label">Sudah Datang</div>
                        <div class="stat-sublabel">No GRN Terisi</div>
                    </div>
                </div>

                <!-- Card 4: Belum Datang -->
                <div class="tracking-stat-card" data-filter-type="datang" data-status="Belum Datang" id="cardNotArrived" title="Klik untuk memfilter PO yang barangnya belum datang (Belum Ada No GRN)">
                    <div class="stat-icon-box bg-amber">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-count" id="countNotArrived">0</div>
                        <div class="stat-label">Belum Datang</div>
                        <div class="stat-sublabel">No GRN Belum Ada</div>
                    </div>
                </div>
            </div>

            <!-- Main Card Container -->
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center justify-content-between py-2">
                    <h3 class="card-title font-weight-bold mb-0">
                        <i class="fas fa-list-alt mr-1"></i> Data Monitoring PO Online
                    </h3>
                </div>

                <div class="card-body">
                    <!-- Filter Controls -->
                    <div class="filter-clean-box">
                        <div class="row align-items-end">
                            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-2">
                                <label for="filterDateFrom" class="small font-weight-bold text-muted mb-1">
                                    <i class="fas fa-calendar-alt mr-1"></i> Tanggal PO Dari
                                </label>
                                <input type="date" id="filterDateFrom" class="form-control form-control-sm" value="<?= htmlspecialchars($defaultDateFrom) ?>">
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-2">
                                <label for="filterDateTo" class="small font-weight-bold text-muted mb-1">
                                    <i class="fas fa-calendar-alt mr-1"></i> Tanggal PO Sampai
                                </label>
                                <input type="date" id="filterDateTo" class="form-control form-control-sm" value="<?= htmlspecialchars($defaultDateTo) ?>">
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-2">
                                <label for="filterStatusDatang" class="small font-weight-bold text-muted mb-1">
                                    <i class="fas fa-truck mr-1 text-info"></i> Status Kedatangan
                                </label>
                                <select id="filterStatusDatang" class="form-control form-control-sm">
                                    <option value="">-- Semua Kedatangan --</option>
                                    <option value="Belum Datang">Belum Datang (Belum Ada GRN)</option>
                                    <option value="Sudah Datang">Sudah Datang (No GRN Ada)</option>
                                </select>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-2">
                                <label for="filterStatusBayar" class="small font-weight-bold text-muted mb-1">
                                    <i class="fas fa-money-bill-wave mr-1 text-success"></i> Status Pembayaran
                                </label>
                                <select id="filterStatusBayar" class="form-control form-control-sm">
                                    <option value="">-- Semua Pembayaran --</option>
                                    <option value="Sudah Dibayar">Sudah Dibayar (Sudah Ada No CP)</option>
                                    <option value="Belum Dibayar">Belum Dibayar (Belum Ada No CP)</option>
                                </select>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-2">
                                <label for="filterMetodeBayar" class="small font-weight-bold text-muted mb-1">
                                    <i class="fas fa-tags mr-1 text-warning"></i> Metode Pembayaran
                                </label>
                                <select id="filterMetodeBayar" class="form-control form-control-sm">
                                    <option value="">-- Semua Metode --</option>
                                    <option value="Kosong">Belum Diset ( - )</option>
                                    <option value="Reimburse">Reimburse</option>
                                    <option value="Normal">Normal</option>
                                </select>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-2">
                                <label class="small font-weight-bold text-muted mb-1 d-none d-sm-block">
                                    <i class="fas fa-sliders-h mr-1 text-secondary"></i> Aksi
                                </label>
                                <div class="d-flex align-items-center" style="gap: 6px;">
                                    <button type="button" class="btn btn-sm btn-primary shadow-sm" id="btnApplyFilter" title="Terapkan Filter" data-toggle="tooltip" style="width: 38px; height: 31px; display: inline-flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary shadow-sm" id="btnResetFilter" title="Reset Filter ke Kondisi Awal" data-toggle="tooltip" style="width: 38px; height: 31px; display: inline-flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-success shadow-sm font-weight-bold" id="btnExportExcel" title="Unduh Data Tabel ke Excel (.xlsx)" data-toggle="tooltip" style="width: 38px; height: 31px; display: inline-flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-file-excel text-white"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bulk Action Toolbar -->
                    <div id="bulkActionContainer" class="alert alert-light border py-2 px-3 mb-3 d-none align-items-center justify-content-between shadow-sm flex-wrap" style="gap: 10px; background-color: #f1f5f9; border-color: #cbd5e1 !important; border-radius: 8px;">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-check-square text-primary mr-2" style="font-size: 16px;"></i>
                            <span id="selectedCountText" class="font-weight-bold text-dark" style="font-size: 13px;">0 PO terpilih</span>
                        </div>
                        <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                            <span class="small font-weight-bold text-muted mr-1">Atur Metode:</span>
                            <button type="button" class="btn btn-sm btn-warning text-dark font-weight-bold px-3 shadow-sm" id="btnSetReimburse">
                                <i class="fas fa-receipt mr-1"></i> Reimburse
                            </button>
                            <button type="button" class="btn btn-sm btn-info text-white font-weight-bold px-3 shadow-sm" id="btnSetNormal">
                                <i class="fas fa-building mr-1"></i> Normal
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary px-2" id="btnResetMetode" title="Kembalikan ke status belum diset (-)">
                                <i class="fas fa-eraser mr-1"></i> Kosongkan (-)
                            </button>
                            <button type="button" class="btn btn-sm btn-light border px-2 text-muted" id="btnUnselectAll" title="Batalkan pilihan">
                                <i class="fas fa-times mr-1"></i> Batal
                            </button>
                        </div>
                    </div>

                    <!-- Table Wrapper with Position Relative for Loading Overlay -->
                    <div class="position-relative">
                        <!-- Loading Indicator -->
                        <div class="table-loading-overlay" id="tableLoading" style="display: none;">
                            <div class="spinner-border text-primary" role="status" style="width: 2.5rem; height: 2.5rem;">
                                <span class="sr-only">Memuat data...</span>
                            </div>
                            <div class="mt-2 font-weight-bold text-secondary" style="font-size: 13px;">Memuat data Tracking PO Online...</div>
                        </div>

                        <!-- Data Table: Checkbox + 8 Data Columns + Metode + Status Bayar + Aksi (Total 12 Kolom) -->
                        <div class="table-responsive">
                            <table id="trackingPoOnlineTable" class="table table-hover table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width: 35px;">
                                            <input type="checkbox" id="checkAllPo" title="Pilih Semua di Halaman Ini">
                                        </th>
                                        <th class="text-center" style="width: 40px;">No</th>
                                        <th class="text-center" style="min-width: 130px;">No PO</th>
                                        <th class="text-center" style="min-width: 90px;">Tgl PO</th>
                                        <th class="text-center" style="min-width: 140px;">Nama Supplier</th>
                                        <th class="text-center" style="min-width: 140px;">Nama Toko Online</th>
                                        <th class="text-center" style="min-width: 120px;">No PR</th>
                                        <th class="text-center" style="min-width: 70px;">Qty</th>
                                        <th class="text-center" style="min-width: 110px;">Total Amount</th>
                                        <th class="text-center" style="min-width: 125px;">Metode Pembayaran</th>
                                        <th class="text-center" style="min-width: 110px;">Status Bayar</th>
                                        <th class="text-center" style="min-width: 85px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Rendered dynamically by DataTables AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Modal Detail PO Online -->
            <div class="modal fade" id="modalDetailPoOnline" tabindex="-1" role="dialog" aria-labelledby="modalDetailPoOnlineLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document" style="max-width: 860px;">
                    <div class="modal-content shadow-lg border-0">
                        <div class="modal-header bg-primary text-white py-2 px-3">
                            <h5 class="modal-title font-weight-bold" id="modalDetailPoOnlineLabel" style="font-size: 15px;">
                                <i class="fas fa-file-invoice mr-2"></i>Detail Monitoring PO Online: <span id="mdl_title_po" class="font-weight-bold"></span>
                            </h5>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.9;">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body p-3" style="background-color: #f8fafc;">
                            <div class="row">
                                <!-- Block 1: Info PO & PR -->
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100 shadow-none border mb-0" style="border-radius: 8px;">
                                        <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center">
                                            <i class="fas fa-shopping-cart text-primary mr-2"></i>
                                            <h6 class="font-weight-bold text-dark mb-0" style="font-size: 13px;">Informasi PO &amp; PR</h6>
                                        </div>
                                        <div class="card-body p-2">
                                            <table class="table table-sm table-borderless mb-0" style="font-size: 12px;">
                                                <tr>
                                                    <td class="text-muted" style="width: 38%;">No. PO</td>
                                                    <td class="font-weight-bold text-dark" id="mdl_no_po">-</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Tgl. PO</td>
                                                    <td id="mdl_tgl_po">-</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Status PO</td>
                                                    <td id="mdl_status_po">-</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Pembuat</td>
                                                    <td class="font-weight-bold text-dark" id="mdl_pembuat">-</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Supplier</td>
                                                    <td id="mdl_supplier">-</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Toko Online</td>
                                                    <td id="mdl_toko_online">-</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">No. PR</td>
                                                    <td id="mdl_no_pr">-</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Tgl. PR</td>
                                                    <td id="mdl_tgl_pr">-</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Keterangan</td>
                                                    <td id="mdl_keterangan" class="text-break">-</td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <!-- Block 2: Detail Produk & Finansial -->
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100 shadow-none border mb-0" style="border-radius: 8px;">
                                        <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center">
                                            <i class="fas fa-box-open text-info mr-2"></i>
                                            <h6 class="font-weight-bold text-dark mb-0" style="font-size: 13px;">Detail Produk &amp; Finansial</h6>
                                        </div>
                                        <div class="card-body p-2">
                                            <table class="table table-sm table-borderless mb-0" style="font-size: 12px;">
                                                <tr>
                                                    <td class="text-muted" style="width: 38%;">Kode Produk</td>
                                                    <td class="font-weight-bold text-secondary" id="mdl_kode_produk">-</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Nama Produk</td>
                                                    <td class="font-weight-bold text-dark" id="mdl_nama_produk">-</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Qty PO</td>
                                                    <td class="font-weight-bold" id="mdl_qty_po">-</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Satuan</td>
                                                    <td id="mdl_satuan">-</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Harga Satuan</td>
                                                    <td id="mdl_harga_satuan">-</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Total Harga</td>
                                                    <td id="mdl_total_harga">-</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Total Amount</td>
                                                    <td class="font-weight-bold text-primary" style="font-size: 13px;" id="mdl_total_amount">-</td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <!-- Block 3: Penerimaan Barang (GRN) -->
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100 shadow-none border mb-0" style="border-radius: 8px;">
                                        <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center">
                                            <i class="fas fa-truck-loading text-teal mr-2"></i>
                                            <h6 class="font-weight-bold text-dark mb-0" style="font-size: 13px;">Kedatangan Barang / GRN</h6>
                                        </div>
                                        <div class="card-body p-2">
                                            <table class="table table-sm table-borderless mb-0" style="font-size: 12px;">
                                                <tr>
                                                    <td class="text-muted" style="width: 38%;">No. GRN</td>
                                                    <td id="mdl_no_grn">-</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Tgl. GRN</td>
                                                    <td id="mdl_tgl_grn">-</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Qty GRN</td>
                                                    <td class="font-weight-bold" id="mdl_qty_grn">-</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Nama Gudang</td>
                                                    <td id="mdl_nama_gudang">-</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Status Datang</td>
                                                    <td id="mdl_status_datang">-</td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <!-- Block 4: Pembayaran (RFP & CP) -->
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100 shadow-none border mb-0" style="border-radius: 8px;">
                                        <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center">
                                            <i class="fas fa-money-check-alt text-success mr-2"></i>
                                            <h6 class="font-weight-bold text-dark mb-0" style="font-size: 13px;">Pembayaran &amp; Kas (RFP / CP)</h6>
                                        </div>
                                        <div class="card-body p-2">
                                            <table class="table table-sm table-borderless mb-0" style="font-size: 12px;">
                                                <tr>
                                                    <td class="text-muted" style="width: 38%;">No. RFP</td>
                                                    <td id="mdl_no_rfp">-</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Tgl. RFP</td>
                                                    <td id="mdl_tgl_rfp">-</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">No. CP</td>
                                                    <td id="mdl_no_cp">-</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Tgl. CP</td>
                                                    <td id="mdl_tgl_cp">-</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Status CP</td>
                                                    <td id="mdl_status_cp">-</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Metode Pembayaran</td>
                                                    <td id="mdl_metode_bayar" class="font-weight-bold">-</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Status Bayar</td>
                                                    <td id="mdl_status_bayar">-</td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer py-2 px-3 bg-light">
                            <button type="button" class="btn btn-sm btn-secondary px-3" data-dismiss="modal">
                                <i class="fas fa-times mr-1"></i> Tutup
                            </button>
                        </div>
                    </div>
                </div>
            </div>

                </div>
            </div>

        </div>
    </section>
</div>
</div>

<?php 
$footerPath = file_exists($rootPath . '/includes/footer.php') ? ($rootPath . '/includes/footer.php') : ($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php');
include($footerPath); 
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">

<!-- DataTables & SweetAlert2 JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
$(document).ready(function () {
    const API_ENDPOINT = 'tracking_po_online_serverside.php';
    const EXCEL_ENDPOINT = 'export_tracking_po_online_excel.php';

    let dataTableInstance = null;
    let selectedPoNumbers = new Set();

    // Helper untuk escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Helper update Bulk Toolbar
    function updateBulkToolbar() {
        const count = selectedPoNumbers.size;
        if (count > 0) {
            $('#bulkActionContainer').removeClass('d-none').addClass('d-flex');
            $('#selectedCountText').text(count + ' PO terpilih');
        } else {
            $('#bulkActionContainer').addClass('d-none').removeClass('d-flex');
            $('#checkAllPo').prop('checked', false);
        }
    }

    // 1. Inisialisasi DataTable
    function initDataTable() {
        dataTableInstance = $('#trackingPoOnlineTable').DataTable({
            destroy: true,
            processing: false,
            serverSide: false,
            scrollX: true,
            scrollCollapse: true,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Semua"]],
            order: [[2, 'asc']], // Order by No PO (Kolom index 2)
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Cari nomor PO, toko, produk...",
                lengthMenu: "Tampilkan _MENU_ baris",
                info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                infoEmpty: "Menampilkan 0 data",
                infoFiltered: "(disaring dari _MAX_ total data)",
                zeroRecords: "Tidak ada data PO online yang sesuai filter",
                paginate: {
                    first: '<i class="fas fa-angle-double-left"></i>',
                    previous: '<i class="fas fa-angle-left"></i>',
                    next: '<i class="fas fa-angle-right"></i>',
                    last: '<i class="fas fa-angle-double-right"></i>'
                }
            },
            columns: [
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    searchable: false,
                    width: '35px',
                    render: function (data, type, row) {
                        const isChecked = selectedPoNumbers.has(row.no_po);
                        return '<input type="checkbox" class="po-row-checkbox" value="' + escapeHtml(row.no_po) + '"' + (isChecked ? ' checked' : '') + '>';
                    }
                },
                { data: 'no', className: 'text-center font-weight-bold text-muted', width: '40px' },
                { 
                    data: 'no_po',
                    className: 'text-center',
                    render: function (data) {
                        return '<span class="po-badge-code">' + escapeHtml(data) + '</span>';
                    }
                },
                { data: 'tgl_po', className: 'text-center' },
                { data: 'nama_supplier', className: 'text-left' },
                { 
                    data: 'nama_toko_online',
                    className: 'text-left',
                    render: function (data) {
                        if (!data || data === '-') return '<span class="text-muted">-</span>';
                        return '<span class="font-weight-bold text-dark"><i class="fas fa-store text-muted mr-1"></i>' + escapeHtml(data) + '</span>';
                    }
                },
                { 
                    data: 'no_pr',
                    className: 'text-center',
                    render: function (data) {
                        if (!data || data === '-') return '-';
                        return '<span class="pr-badge-code">' + escapeHtml(data) + '</span>';
                    }
                },
                { data: 'qty_po', className: 'text-right font-weight-bold' },
                { data: 'total_amount', className: 'text-right font-weight-bold text-primary' },
                {
                    data: 'metode_bayar',
                    className: 'text-center font-weight-bold',
                    render: function (data) {
                        if (data === 'Reimburse') {
                            return '<span class="badge badge-warning text-dark font-weight-bold px-2 py-1"><i class="fas fa-receipt mr-1"></i>Reimburse</span>';
                        } else if (data === 'Normal') {
                            return '<span class="badge badge-info px-2 py-1"><i class="fas fa-building mr-1"></i>Normal</span>';
                        }
                        return '<span class="text-muted font-weight-bold">-</span>';
                    }
                },
                { 
                    data: 'status_bayar',
                    className: 'text-center',
                    render: function (data, type, row) {
                        if (data === 'Sudah Dibayar' || row.is_paid) {
                            return '<span class="badge badge-success px-2 py-1"><i class="fas fa-check-double mr-1"></i>Sudah Dibayar</span>';
                        } else if (data === 'Proses Bayar' || row.is_process) {
                            return '<span class="badge badge-warning text-dark px-2 py-1"><i class="fas fa-hourglass-half mr-1"></i>Proses Bayar</span>';
                        }
                        return '<span class="badge badge-light border text-muted px-2 py-1"><i class="fas fa-clock mr-1"></i>Belum Dibayar</span>';
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    searchable: false,
                    render: function (data, type, row, meta) {
                        return '<button type="button" class="btn btn-xs btn-primary font-weight-bold px-2 py-1 btn-detail-row shadow-sm" title="Lihat rincian lengkap">' +
                               '<i class="fas fa-eye mr-1"></i>Detail</button>';
                    }
                }
            ],
            drawCallback: function () {
                let allChecked = true;
                let anyCheckbox = false;
                $('.po-row-checkbox').each(function () {
                    anyCheckbox = true;
                    const val = $(this).val();
                    if (selectedPoNumbers.has(val)) {
                        $(this).prop('checked', true);
                    } else {
                        $(this).prop('checked', false);
                        allChecked = false;
                    }
                });
                $('#checkAllPo').prop('checked', anyCheckbox && allChecked);
            }
        });
    }

    // 2. Fetch Summary Counters
    function loadSummary() {
        const dateFrom = $('#filterDateFrom').val();
        const dateTo   = $('#filterDateTo').val();

        $.ajax({
            url: API_ENDPOINT,
            type: 'GET',
            data: {
                action: 'get_summary',
                date_from: dateFrom,
                date_to: dateTo
            },
            dataType: 'json',
            success: function (res) {
                if (res && res.status === 'ok') {
                    $('#countTotalPo').text(res.summary.total_po || 0);
                    $('#countPaid').text(res.summary.paid_po || 0);
                    $('#countUnpaid').text(res.summary.unpaid_po || 0);
                    $('#countArrived').text(res.summary.grn_po || 0);
                    $('#countNotArrived').text(res.summary.not_arrived_po || 0);
                }
            },
            error: function () {
                console.warn('Gagal memuat ringkasan summary cards.');
            }
        });
    }

    // 3. Fetch Data & Refresh DataTable
    function loadTableData() {
        $('#tableLoading').fadeIn(150);

        const dateFrom           = $('#filterDateFrom').val();
        const dateTo             = $('#filterDateTo').val();
        const filterStatusDatang = $('#filterStatusDatang').val();
        const filterStatusBayar  = $('#filterStatusBayar').val();
        const filterMetodeBayar  = $('#filterMetodeBayar').val();

        $.ajax({
            url: API_ENDPOINT,
            type: 'GET',
            data: {
                action: 'load_data',
                date_from: dateFrom,
                date_to: dateTo,
                filter_status_datang: filterStatusDatang,
                filter_status_bayar: filterStatusBayar,
                filter_metode_bayar: filterMetodeBayar
            },
            dataType: 'json',
            success: function (res) {
                $('#tableLoading').fadeOut(150);
                if (res && res.status === 'ok') {
                    dataTableInstance.clear();
                    dataTableInstance.rows.add(res.data || []);
                    dataTableInstance.draw();
                    setTimeout(function () {
                        dataTableInstance.columns.adjust();
                    }, 50);
                } else {
                    Toast.fire({
                        icon: 'error',
                        title: res.message || 'Gagal memuat data dari server.'
                    });
                }
            },
            error: function (xhr) {
                $('#tableLoading').fadeOut(150);
                const errMsg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Terjadi gangguan jaringan atau server saat memuat data.';
                Toast.fire({
                    icon: 'error',
                    title: errMsg
                });
            }
        });
    }

    // Event Handler: Checkbox Per Baris
    $('#trackingPoOnlineTable tbody').on('change', '.po-row-checkbox', function () {
        const po = $(this).val();
        if ($(this).is(':checked')) {
            selectedPoNumbers.add(po);
        } else {
            selectedPoNumbers.delete(po);
        }
        updateBulkToolbar();
    });

    // Event Handler: Master Checkbox Select All
    $('#checkAllPo').on('change', function () {
        const isChecked = $(this).is(':checked');
        $('.po-row-checkbox').each(function () {
            $(this).prop('checked', isChecked);
            const po = $(this).val();
            if (isChecked) {
                selectedPoNumbers.add(po);
            } else {
                selectedPoNumbers.delete(po);
            }
        });
        updateBulkToolbar();
    });

    // Event Handler: Batal Pilihan
    $('#btnUnselectAll').on('click', function () {
        selectedPoNumbers.clear();
        $('.po-row-checkbox, #checkAllPo').prop('checked', false);
        updateBulkToolbar();
    });

    // Inisialisasi SweetAlert2 Toast Notifikasi
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3500,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });

    // Helper untuk update massal metode pembayaran dengan SweetAlert2 Modern Dialog
    function submitBulkMetode(targetMetode, labelName) {
        if (selectedPoNumbers.size === 0) {
            Toast.fire({
                icon: 'warning',
                title: 'Silakan centang minimal 1 PO terlebih dahulu.'
            });
            return;
        }

        const poArray = Array.from(selectedPoNumbers);
        const totalCount = poArray.length;

        // Preview target badge dan styling tombol
        let targetBadgeHtml = '';
        let btnColor = '#0891b2';
        let confirmIcon = 'fas fa-check';
        let alertIcon = 'question';

        if (targetMetode === 'Reimburse') {
            targetBadgeHtml = '<span class="badge badge-warning text-dark font-weight-bold px-3 py-2 shadow-sm" style="font-size: 14px;"><i class="fas fa-receipt mr-1"></i> Reimburse</span>';
            btnColor = '#f59e0b';
            confirmIcon = 'fas fa-receipt';
        } else if (targetMetode === 'Normal') {
            targetBadgeHtml = '<span class="badge badge-info text-white font-weight-bold px-3 py-2 shadow-sm" style="font-size: 14px;"><i class="fas fa-building mr-1"></i> Normal</span>';
            btnColor = '#06b6d4';
            confirmIcon = 'fas fa-building';
        } else {
            targetBadgeHtml = '<span class="badge badge-secondary font-weight-bold px-3 py-2 shadow-sm" style="font-size: 14px;"><i class="fas fa-eraser mr-1"></i> Kosongkan (-)</span>';
            btnColor = '#64748b';
            confirmIcon = 'fas fa-eraser';
            alertIcon = 'warning';
        }

        // Tampilkan daftar nomor PO terpilih (maksimal 5 buah)
        const previewBadges = poArray.slice(0, 5).map(function (po) {
            return '<span class="po-badge-code mr-1 mb-1">' + escapeHtml(po) + '</span>';
        }).join(' ');
        const extraCount = totalCount - 5;
        const extraText = extraCount > 0 ? '<div class="text-muted small mt-1 font-italic">...dan ' + extraCount + ' nomor PO lainnya</div>' : '';

        const modalHtml = 
            '<div class="text-center">' +
                '<p class="mb-2" style="font-size: 14px; color: #334155;">' +
                    'Apakah Anda yakin ingin mengatur metode pembayaran untuk <b>' + totalCount + ' PO terpilih</b> menjadi:' +
                '</p>' +
                '<div class="my-3">' + targetBadgeHtml + '</div>' +
                '<div class="p-2 border rounded text-left mt-3" style="background-color: #f8fafc; max-height: 120px; overflow-y: auto;">' +
                    '<div class="small font-weight-bold text-muted mb-1"><i class="fas fa-list-ul mr-1"></i> PO yang dipilih (' + totalCount + '):</div>' +
                    '<div class="d-flex flex-wrap" style="gap: 4px;">' + previewBadges + '</div>' +
                    extraText +
                '</div>' +
            '</div>';

        Swal.fire({
            title: 'Konfirmasi Metode Pembayaran',
            html: modalHtml,
            icon: alertIcon,
            showCancelButton: true,
            confirmButtonColor: btnColor,
            cancelButtonColor: '#94a3b8',
            confirmButtonText: '<i class="' + confirmIcon + ' mr-1"></i> Ya, Terapkan',
            cancelButtonText: '<i class="fas fa-times mr-1"></i> Batal',
            reverseButtons: true,
            focusCancel: true
        }).then(function (result) {
            if (result.isConfirmed) {
                $('#tableLoading').fadeIn(150);
                $.ajax({
                    url: API_ENDPOINT,
                    type: 'POST',
                    data: {
                        action: 'update_metode_bayar',
                        metode_bayar: targetMetode,
                        no_pos: poArray
                    },
                    dataType: 'json',
                    success: function (res) {
                        $('#tableLoading').fadeOut(150);
                        if (res && res.status === 'ok') {
                            selectedPoNumbers.clear();
                            updateBulkToolbar();
                            loadTableData();
                            Toast.fire({
                                icon: 'success',
                                title: res.message || 'Metode pembayaran berhasil diperbarui.'
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal Memperbarui',
                                text: res.message || 'Gagal memperbarui metode pembayaran.'
                            });
                        }
                    },
                    error: function (xhr) {
                        $('#tableLoading').fadeOut(150);
                        const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Terjadi gangguan server atau koneksi.';
                        Swal.fire({
                            icon: 'error',
                            title: 'Terjadi Kesalahan',
                            text: msg
                        });
                    }
                });
            }
        });
    }

    $('#btnSetReimburse').on('click', function () {
        submitBulkMetode('Reimburse', 'Reimburse');
    });

    $('#btnSetNormal').on('click', function () {
        submitBulkMetode('Normal', 'Normal');
    });

    $('#btnResetMetode').on('click', function () {
        submitBulkMetode('', 'Kosong (-)');
    });

    // 4. Sinkronisasi Status Card & Dropdowns
    function syncCardActiveState() {
        const datangVal = $('#filterStatusDatang').val();
        const bayarVal  = $('#filterStatusBayar').val();

        $('.tracking-stat-card').removeClass('active');

        if (!datangVal && !bayarVal) {
            $('#cardTotalPo').addClass('active');
        } else if (datangVal === 'Belum Datang' && !bayarVal) {
            $('#cardNotArrived').addClass('active');
        } else if (datangVal === 'Sudah Datang' && !bayarVal) {
            $('#cardArrived').addClass('active');
        } else if (!datangVal && bayarVal === 'Sudah Dibayar') {
            $('#cardPaid').addClass('active');
        } else if (!datangVal && bayarVal === 'Belum Dibayar') {
            $('#cardUnpaid').addClass('active');
        }
        // Jika kombinasi (misal Sudah Dibayar & Belum Datang), tidak ada card tunggal yang aktif
    }

    // Event Handler: Klik Summary Cards
    $('.tracking-stat-card').on('click', function () {
        const filterType   = $(this).attr('data-filter-type');
        const targetStatus = $(this).attr('data-status');

        if (filterType === 'all') {
            $('#filterStatusDatang').val('');
            $('#filterStatusBayar').val('');
        } else if (filterType === 'datang') {
            $('#filterStatusDatang').val(targetStatus);
            $('#filterStatusBayar').val('');
        } else if (filterType === 'bayar') {
            $('#filterStatusBayar').val(targetStatus);
            $('#filterStatusDatang').val('');
        }

        syncCardActiveState();
        loadTableData();
    });

    // Event Handler: Ganti Filter Status Dropdown
    $('#filterStatusDatang, #filterStatusBayar, #filterMetodeBayar').on('change', function () {
        syncCardActiveState();
        loadTableData();
    });

    // Event Handler: Tombol Terapkan Filter
    $('#btnApplyFilter').on('click', function () {
        syncCardActiveState();
        loadSummary();
        loadTableData();
    });

    // Event Handler: Reset Filter
    $('#btnResetFilter').on('click', function () {
        $('#filterDateFrom').val('<?= $defaultDateFrom ?>');
        $('#filterDateTo').val('<?= $defaultDateTo ?>');
        $('#filterStatusDatang').val('');
        $('#filterStatusBayar').val('');
        $('#filterMetodeBayar').val('');
        syncCardActiveState();
        loadSummary();
        loadTableData();
    });

    // Event Handler: Export Excel
    $('#btnExportExcel').on('click', function (e) {
        e.preventDefault();
        const dateFrom           = encodeURIComponent($('#filterDateFrom').val() || '');
        const dateTo             = encodeURIComponent($('#filterDateTo').val() || '');
        const filterStatusDatang = encodeURIComponent($('#filterStatusDatang').val() || '');
        const filterStatusBayar  = encodeURIComponent($('#filterStatusBayar').val() || '');
        const filterMetodeBayar  = encodeURIComponent($('#filterMetodeBayar').val() || '');

        const url = EXCEL_ENDPOINT + '?date_from=' + dateFrom + '&date_to=' + dateTo +
                    '&filter_status_datang=' + filterStatusDatang +
                    '&filter_status_bayar=' + filterStatusBayar +
                    '&filter_metode_bayar=' + filterMetodeBayar;
        window.location.href = url;
    });

    // Event Handler: Auto Adjust saat window resize atau sidebar toggle
    $(window).on('resize', function () {
        if (dataTableInstance) {
            dataTableInstance.columns.adjust();
        }
    });

    $('[data-widget="pushmenu"]').on('click', function () {
        setTimeout(function () {
            if (dataTableInstance) {
                dataTableInstance.columns.adjust();
            }
        }, 350);
    });

    // Event Handler: Klik Tombol Detail
    $('#trackingPoOnlineTable tbody').on('click', '.btn-detail-row', function () {
        const tr = $(this).closest('tr');
        const d = dataTableInstance.row(tr).data();
        if (!d) return;

        $('#mdl_title_po').text(d.no_po || '-');

        // Block 1: Info PO & PR
        $('#mdl_no_po').html(d.no_po ? '<span class="po-badge-code">' + escapeHtml(d.no_po) + '</span>' : '-');
        $('#mdl_tgl_po').text(d.tgl_po || '-');

        let statusPoBadge = '<span class="badge badge-light border px-2 py-1">' + escapeHtml(d.status_po || '-') + '</span>';
        if (d.status_po === 'V') {
            statusPoBadge = '<span class="badge badge-success px-2 py-1"><i class="fas fa-check mr-1"></i>Approved (V)</span>';
        } else if (d.status_po === 'X') {
            statusPoBadge = '<span class="badge badge-secondary px-2 py-1"><i class="fas fa-times mr-1"></i>Closed (X)</span>';
        } else if (d.status_po === 'O') {
            statusPoBadge = '<span class="badge badge-warning px-2 py-1"><i class="fas fa-clock mr-1"></i>Open (O)</span>';
        }
        $('#mdl_status_po').html(statusPoBadge);

        $('#mdl_pembuat').text(d.pembuat || '-');
        $('#mdl_supplier').text(d.nama_supplier || '-');
        $('#mdl_toko_online').html(d.nama_toko_online && d.nama_toko_online !== '-' ? '<i class="fas fa-store text-muted mr-1"></i>' + escapeHtml(d.nama_toko_online) : '-');
        $('#mdl_no_pr').html(d.no_pr && d.no_pr !== '-' ? '<span class="pr-badge-code">' + escapeHtml(d.no_pr) + '</span>' : '-');
        $('#mdl_tgl_pr').text(d.tgl_pr || '-');
        $('#mdl_keterangan').text(d.keterangan || '-');

        // Block 2: Detail Produk & Finansial
        $('#mdl_kode_produk').text(d.kode_produk || '-');
        $('#mdl_nama_produk').text(d.nama_produk || '-');
        $('#mdl_qty_po').text((d.qty_po || '0') + (d.satuan && d.satuan !== '-' ? ' ' + d.satuan : ''));
        $('#mdl_satuan').text(d.satuan || '-');
        $('#mdl_harga_satuan').text(d.harga_satuan || '-');
        $('#mdl_total_harga').text(d.total_harga || '-');
        $('#mdl_total_amount').text(d.total_amount || '-');

        // Block 3: Kedatangan / GRN
        $('#mdl_no_grn').html(d.no_grn && d.no_grn !== '-' ? '<span class="grn-badge-code">' + escapeHtml(d.no_grn) + '</span>' : '<span class="text-muted">-</span>');
        $('#mdl_tgl_grn').text(d.tgl_grn || '-');
        $('#mdl_qty_grn').text(d.qty_grn || '-');
        $('#mdl_nama_gudang').html(d.nama_gudang && d.nama_gudang !== '-' ? '<i class="fas fa-warehouse text-muted mr-1"></i>' + escapeHtml(d.nama_gudang) : '-');

        const isArrived = (d.no_grn && d.no_grn !== '-');
        $('#mdl_status_datang').html(isArrived ? '<span class="badge badge-info px-2 py-1"><i class="fas fa-truck mr-1"></i>Sudah Datang</span>' : '<span class="badge badge-light border text-muted px-2 py-1"><i class="fas fa-hourglass-half mr-1"></i>Belum Datang</span>');

        // Block 4: Pembayaran / CP & RFP
        $('#mdl_no_rfp').text(d.no_rfp || '-');
        $('#mdl_tgl_rfp').text(d.tgl_rfp || '-');
        $('#mdl_no_cp').html(d.no_cp && d.no_cp !== '-' ? '<span class="cp-badge-code">' + escapeHtml(d.no_cp) + '</span>' : '<span class="text-muted">-</span>');
        $('#mdl_tgl_cp').text(d.tgl_cp || '-');

        let metodeBadge = '<span class="text-muted font-weight-bold">-</span>';
        if (d.metode_bayar === 'Reimburse') {
            metodeBadge = '<span class="badge badge-warning text-dark font-weight-bold px-2 py-1"><i class="fas fa-receipt mr-1"></i>Reimburse</span>';
        } else if (d.metode_bayar === 'Normal') {
            metodeBadge = '<span class="badge badge-info px-2 py-1"><i class="fas fa-building mr-1"></i>Normal</span>';
        }
        $('#mdl_metode_bayar').html(metodeBadge);

        let statusCpBadge = '<span class="badge badge-light border text-muted px-2 py-1">Belum ( - )</span>';
        if (d.status_cp === 'V') {
            statusCpBadge = '<span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i>Approved (V)</span>';
        } else if (d.status_cp === 'O') {
            statusCpBadge = '<span class="badge badge-warning text-dark px-2 py-1"><i class="fas fa-hourglass-half mr-1"></i>Open / Proses (O)</span>';
        }
        $('#mdl_status_cp').html(statusCpBadge);

        let statusBayarBadge = '<span class="badge badge-light border text-muted px-2 py-1"><i class="fas fa-clock mr-1"></i>Belum Dibayar</span>';
        if (d.status_bayar === 'Sudah Dibayar' || d.is_paid) {
            statusBayarBadge = '<span class="badge badge-success px-2 py-1"><i class="fas fa-check-double mr-1"></i>Sudah Dibayar</span>';
        } else if (d.status_bayar === 'Proses Bayar' || d.is_process) {
            statusBayarBadge = '<span class="badge badge-warning text-dark px-2 py-1"><i class="fas fa-hourglass-half mr-1"></i>Proses Bayar</span>';
        }
        $('#mdl_status_bayar').html(statusBayarBadge);

        $('#modalDetailPoOnline').modal('show');
    });

    // Jalankan inisialisasi awal
    $('[data-toggle="tooltip"]').tooltip();
    initDataTable();
    loadSummary();
    loadTableData();
});
</script>
