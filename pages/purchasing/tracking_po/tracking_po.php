<?php
/**
 * Tracking PO - Pemantauan Dokumen PO ke Direktur
 * Halaman utama untuk memantau alur dokumen fisik PO yang diserahkan
 * purchasing ke direktur untuk ditandatangani.
 *
 * Status: Diserahkan → Belum Kembali (auto) → Sudah Kembali/Sudah TTD → Approved
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootPath = dirname(__DIR__, 3);
require_once $rootPath . '/koneksi.php';
require_once $rootPath . '/includes/permissions.php';

// Menu ID Tracking PO (sesuaikan jika sudah didaftarkan di dbo.SMMenu)
$menuId = null;
$permissions = ['CanView' => 1, 'CanAdd' => 1, 'CanEdit' => 1, 'CanDelete' => 1];

if (!empty($menuId) && !empty($_SESSION['GroupId'])) {
    $perm = getPermissions($conn, $_SESSION['GroupId'], $menuId);
    if (!empty($perm)) {
        $permissions = $perm;
    }
    if ($permissions['CanView'] != 1) {
        $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
        header('Location: /gg_app/index.php');
        exit;
    }
}

// Ambil data user saat ini
$currentUserName = trim($_SESSION['UserName'] ?? '');
$currentNamaLengkap = trim($_SESSION['NamaLengkap'] ?? '');
$currentDeptName = '';
$currentGroupId = $_SESSION['GroupId'] ?? null;
$currentGroupName = $_SESSION['GroupName'] ?? '';

if (!empty($currentUserName) && !empty($conn)) {
    $qUser = sqlsrv_query($conn, "SELECT u.GroupId, g.GroupName, e.nama_lengkap, d.id_dept, d.dept
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

// Evaluasi role
$isAdminUser = (
    (!empty($currentGroupName) && strcasecmp(trim($currentGroupName), 'Administrator') === 0) ||
    (int)$currentGroupId === 1
);
$isPurchasingUser = (
    stripos($currentDeptName, 'Purchasing') !== false ||
    stripos($currentDeptName, 'Pembelian') !== false
);
$isITUser = (
    stripos($currentDeptName, 'Information Technology') !== false
);

$userCanInput   = ($isAdminUser || $isPurchasingUser);
$userCanConfirm = ($isAdminUser || $isPurchasingUser);
$userCanApprove = ($isAdminUser || $isITUser);
$userCanDelete  = ($isAdminUser || $isPurchasingUser);

$headerPath = file_exists($rootPath . '/includes/header.php') ? ($rootPath . '/includes/header.php') : ($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
$sidebarPath = file_exists($rootPath . '/includes/sidebar.php') ? ($rootPath . '/includes/sidebar.php') : ($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
include($headerPath);
include($sidebarPath);
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<style>
/* =========================================================
   TRACKING PO - SUMMARY CARDS
   ========================================================= */
.tracking-summary-row {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}
.tracking-stat-card {
    flex: 1;
    min-width: 170px;
    background: #ffffff;
    border-radius: 10px;
    padding: 16px 18px;
    display: flex;
    align-items: center;
    gap: 14px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    border: 1px solid #e2e8f0;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
}
.tracking-stat-card::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    border-radius: 10px 0 0 10px;
}
.tracking-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
}
.tracking-stat-card.active {
    border-color: currentColor;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.tracking-stat-card[data-status="Diserahkan"]::before { background: #3b82f6; }
.tracking-stat-card[data-status="Belum Kembali"]::before { background: #f59e0b; }
.tracking-stat-card[data-status="Sudah TTD"]::before { background: #10b981; }
.tracking-stat-card[data-status="Approved"]::before { background: #8b5cf6; }
.stat-icon-box {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
.stat-icon-box.bg-blue { background: #eff6ff; color: #3b82f6; }
.stat-icon-box.bg-amber { background: #fffbeb; color: #f59e0b; }
.stat-icon-box.bg-emerald { background: #ecfdf5; color: #10b981; }
.stat-icon-box.bg-violet { background: #f5f3ff; color: #8b5cf6; }
.stat-info .stat-count {
    font-size: 22px;
    font-weight: 800;
    line-height: 1.1;
    color: #0f172a;
}
.stat-info .stat-label {
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

/* =========================================================
   FILTER BOX
   ========================================================= */
.filter-clean-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 14px 16px 6px 16px;
    margin-bottom: 16px;
}

/* =========================================================
   DATA TABLE
   ========================================================= */
#trackingPoTable {
    border-collapse: collapse !important;
    border: 1px solid #e2e8f0;
}
#trackingPoTable thead th {
    background-color: #f1f5f9 !important;
    color: #334155 !important;
    font-size: 11.5px !important;
    font-weight: 700 !important;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-top: 1px solid #e2e8f0 !important;
    border-bottom: 2px solid #cbd5e1 !important;
    padding: 10px 8px !important;
    vertical-align: middle !important;
    white-space: nowrap;
}
#trackingPoTable tbody td {
    vertical-align: middle !important;
    padding: 7px 8px !important;
    font-size: 12.5px;
    color: #1e293b;
    border: 1px solid #edf2f7 !important;
    background-color: #ffffff;
}
#trackingPoTable tbody tr:hover td {
    background-color: #f8fafc !important;
}

/* =========================================================
   STATUS BADGES
   ========================================================= */
.badge-status {
    font-weight: 700;
    font-size: 11px;
    border-radius: 20px;
    padding: 4px 10px;
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
    gap: 4px;
}
.badge-status-diserahkan {
    background-color: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}
.badge-status-belum-kembali {
    background-color: #fef3c7;
    color: #92400e;
    border: 1px solid #fcd34d;
}
.badge-status-sudah-kembali {
    background-color: #fff7ed;
    color: #c2410c;
    border: 1px solid #fed7aa;
}
.badge-status-sudah-ttd {
    background-color: #dcfce7;
    color: #166534;
    border: 1px solid #86efac;
}
.badge-status-approved {
    background-color: #f5f3ff;
    color: #6d28d9;
    border: 1px solid #c4b5fd;
}

/* =========================================================
   ACTION BUTTONS
   ========================================================= */
.action-btn-group {
    display: inline-flex;
    gap: 4px;
    justify-content: center;
    align-items: center;
}
.action-btn-group .btn {
    width: 28px;
    height: 28px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    font-size: 12px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.06);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.action-btn-group .btn:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 3px 6px rgba(0,0,0,0.15);
}

/* =========================================================
   MODAL DETAIL PO
   ========================================================= */
.modal-po-info-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}
.info-segment {
    padding: 10px 14px;
}
.info-icon-wrapper {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}
#tableModalItems thead th {
    background-color: #f8fafc;
    color: #475569;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e2e8f0;
    padding: 8px 6px;
    white-space: nowrap;
}
#tableModalItems tbody td {
    font-size: 12.5px;
    padding: 6px 6px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}
#tableModalItems tfoot td {
    font-size: 12px;
    padding: 8px 6px;
    border-top: 2px solid #e2e8f0;
    background-color: #f8fafc;
}

/* =========================================================
   MODAL SEARCH PURCHASE ORDER (Model Search CP and Routing)
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

#poSearchTable {
    width: 100%;
    margin-bottom: 0;
}

#poSearchTable thead th {
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

#poSearchTable tbody td {
    border-color: #d9dde2;
    font-size: 0.8rem;
    white-space: nowrap;
    padding: 6px 8px;
    vertical-align: middle;
}

#poSearchTable tbody tr {
    cursor: pointer;
    transition: background-color 0.1s ease;
}

#poSearchTable tbody tr:hover td {
    background: #fff8eb;
}

#poSearchTable tbody tr.table-active td {
    background: #e0f2fe !important;
    border-top-color: #38bdf8;
    border-bottom-color: #38bdf8;
}

#poSearchTable tbody tr.row-disabled {
    cursor: not-allowed;
    background-color: #f8fafc !important;
    opacity: 0.65;
}

#poSearchTable tbody tr.row-disabled:hover td {
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

/* =========================================================
   LOG TIMELINE
   ========================================================= */
.log-timeline-item {
    display: flex;
    gap: 12px;
    padding: 8px 0;
    border-bottom: 1px solid #f1f5f9;
}
.log-timeline-item:last-child {
    border-bottom: none;
}
.log-timeline-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    margin-top: 5px;
    flex-shrink: 0;
}
.log-timeline-content {
    flex: 1;
}

@media (max-width: 768px) {
    .tracking-summary-row {
        flex-direction: column;
        gap: 8px;
    }
    .tracking-stat-card {
        min-width: 100%;
    }
}
</style>

<div class="wrapper">
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0 font-weight-bold">Tracking PO</h1>
                    <small class="text-muted">Pemantauan dokumen PO yang diserahkan ke direktur untuk ditandatangani</small>
                </div>
                <div class="col-sm-6 text-right">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                        <li class="breadcrumb-item">Purchasing</li>
                        <li class="breadcrumb-item active">Tracking PO</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">

            <!-- Summary Cards -->
            <div class="tracking-summary-row" id="summaryCards">
                <div class="tracking-stat-card" data-status="Diserahkan" id="cardDiserahkan">
                    <div class="stat-icon-box bg-blue"><i class="fas fa-paper-plane"></i></div>
                    <div class="stat-info">
                        <div class="stat-count" id="countDiserahkan">0</div>
                        <div class="stat-label">Diserahkan Hari Ini</div>
                    </div>
                </div>
                <div class="tracking-stat-card" data-status="Belum Kembali" id="cardBelumKembali">
                    <div class="stat-icon-box bg-amber"><i class="fas fa-clock"></i></div>
                    <div class="stat-info">
                        <div class="stat-count" id="countBelumKembali">0</div>
                        <div class="stat-label">Belum Kembali</div>
                    </div>
                </div>
                <div class="tracking-stat-card" data-status="Sudah TTD" id="cardSudahTTD">
                    <div class="stat-icon-box bg-emerald"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-info">
                        <div class="stat-count" id="countSudahTTD">0</div>
                        <div class="stat-label">Sudah TTD</div>
                    </div>
                </div>
                <div class="tracking-stat-card" data-status="Approved" id="cardApproved">
                    <div class="stat-icon-box bg-violet"><i class="fas fa-lock"></i></div>
                    <div class="stat-info">
                        <div class="stat-count" id="countApproved">0</div>
                        <div class="stat-label">Approved</div>
                    </div>
                </div>
            </div>

            <!-- Main Card -->
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white clearfix">
                    <h3 class="card-title font-weight-bold pt-1 mb-0">
                        <i class="fas fa-shipping-fast mr-1"></i> List Tracking Dokumen PO
                    </h3>
                    <div class="card-tools float-right">
                        <?php if ($userCanInput): ?>
                        <button type="button" class="btn btn-success btn-sm font-weight-bold shadow-sm" id="btnSerahkanPo">
                            <i class="fas fa-plus mr-1"></i> Serahkan PO
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Filter Bar -->
                    <div class="filter-clean-box">
                        <div class="row align-items-end">
                            <div class="col-md-3 col-sm-6 mb-2">
                                <label for="filterDateFrom" class="small font-weight-bold text-muted mb-1">
                                    <i class="fas fa-calendar-alt mr-1"></i> Dari Tanggal
                                </label>
                                <input type="date" id="filterDateFrom" class="form-control form-control-sm" autocomplete="off">
                            </div>
                            <div class="col-md-3 col-sm-6 mb-2">
                                <label for="filterDateTo" class="small font-weight-bold text-muted mb-1">
                                    <i class="fas fa-calendar-alt mr-1"></i> Sampai Tanggal
                                </label>
                                <input type="date" id="filterDateTo" class="form-control form-control-sm" autocomplete="off">
                            </div>
                            <div class="col-md-3 col-sm-6 mb-2">
                                <label for="filterStatus" class="small font-weight-bold text-muted mb-1">
                                    <i class="fas fa-filter mr-1"></i> Status
                                </label>
                                <select id="filterStatus" class="form-control form-control-sm">
                                    <option value="">Semua Status</option>
                                    <option value="Diserahkan">Diserahkan</option>
                                    <option value="Belum Kembali">Belum Kembali</option>
                                    <option value="Sudah Kembali">Sudah Kembali</option>
                                    <option value="Sudah TTD">Sudah TTD</option>
                                    <option value="Approved">Approved</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-2 d-flex align-items-end" style="gap:6px;">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnResetFilter" title="Reset Filter">
                                    <i class="fas fa-sync-alt mr-1"></i> Reset
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- DataTable -->
                    <div class="table-responsive">
                        <table id="trackingPoTable" class="table table-hover table-sm mb-0" style="width:100%">
                            <thead>
                                <tr>
                                    <th style="width:35px;" class="text-center">No</th>
                                    <th style="width:95px;" class="text-center">Tgl Kirim</th>
                                    <th style="min-width:140px;">No PO</th>
                                    <th style="min-width:140px;">Vendor</th>
                                    <th style="width:90px;" class="text-center">Tgl PO</th>
                                    <th style="min-width:140px;">Diserahkan Oleh</th>
                                    <th style="width:120px;" class="text-center">Status</th>
                                    <th style="width:95px;" class="text-center">Tgl Kembali</th>
                                    <th style="width:135px; min-width:135px;" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="trackingPoBody">
                                <!-- Data dimuat via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>
</div>

<!-- ========================================================================
     MODAL: SEARCH & PILIH PURCHASE ORDER (SERAHKAN PO KE DIREKTUR)
     Model desain identik dengan Search CP and Routing di cp_planning.php
     ======================================================================== -->
<div class="modal fade po-search-modal" id="modalSerahkanPo" tabindex="-1" role="dialog" aria-labelledby="modalSerahkanPoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl po-search-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header po-search-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title font-weight-bold" id="modalSerahkanPoLabel">
                    <i class="fas fa-file-invoice mr-2"></i>Search Purchase Order (Serahkan Dokumen ke Direktur)
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3" style="position: relative;">
                <!-- Overlay loading animasi di dalam modal-body -->
                <div id="loadingOverlayPoSearch" class="loading-overlay-search" style="display: none;">
                    <div class="loading-box-search text-center p-3 bg-white rounded shadow-sm border">
                        <div class="spinner-border text-primary" role="status" style="width: 2.5rem; height: 2.5rem;"></div>
                        <p class="font-weight-bold text-dark mt-2 mb-0" style="font-size: 0.85rem;">Memuat data Purchase Order...</p>
                    </div>
                </div>
                
                <!-- Toolbar Pencarian Model Search CP and Routing -->
                <div class="po-search-toolbar mb-3 p-2 rounded">
                    <div class="po-search-toolbar-left d-flex align-items-center flex-wrap" style="gap: 8px;">
                        <label for="poSearchField" class="po-search-inline-label mb-0 font-weight-bold text-muted small">Search For</label>
                        <select id="poSearchField" class="form-control form-control-sm po-search-select" style="width: auto; min-width: 170px;">
                            <option value="all">Semua Kolom</option>
                            <option value="ponmbr">No PO</option>
                            <option value="povendorname">Vendor / Supplier</option>
                            <option value="requested_by">Requested By Name</option>
                            <option value="pic_pembuat">PIC Pembuat PO</option>
                            <option value="podesc">Deskripsi / Keperluan</option>
                        </select>
                        <input type="text" id="poSearchKeyword" class="form-control form-control-sm po-search-input" 
                               placeholder="Ketik keyword pencarian..." style="width: 240px;" autocomplete="off">
                        <button type="button" id="btnPoSearchApply" class="btn btn-outline-secondary btn-sm font-weight-bold">
                            <i class="fas fa-search mr-1"></i> Search
                        </button>
                        <button type="button" id="btnPoSearchReset" class="btn btn-outline-secondary btn-sm font-weight-bold">
                            <i class="fas fa-sync-alt mr-1"></i> Reset
                        </button>
                        <button type="button" id="btnPoSearchSelectAll" class="btn btn-outline-primary btn-sm font-weight-bold">
                            <i class="fas fa-check-double mr-1"></i> Select All
                        </button>
                    </div>
                </div>

                <!-- Table Container dengan Horizontal & Vertical Scroll -->
                <div class="po-search-table-wrap table-responsive" style="max-height: 480px; overflow: auto; border: 1px solid #cbd5e1; border-radius: 6px;">
                    <table id="poSearchTable" class="table table-bordered table-sm w-100 mb-0">
                        <thead>
                            <tr>
                                <th class="po-select-col text-center" style="width: 75px; min-width: 75px;">Seq</th>
                                <th style="width: 140px; min-width: 140px;" class="text-center">PO No</th>
                                <th style="width: 95px; min-width: 95px;" class="text-center">PO Date</th>
                                <th style="min-width: 190px;">Vendor / Supplier</th>
                                <th style="min-width: 160px;">Requested By Name</th>
                                <th style="min-width: 140px;">PIC Pembuat PO</th>
                                <th style="min-width: 260px;">Description</th>
                                <th style="width: 110px; min-width: 110px;" class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody id="poSearchTableBody">
                            <!-- Data dimuat via AJAX -->
                        </tbody>
                    </table>
                </div>

                <!-- Bar Paginasi & Info Data di Bawah Tabel -->
                <div class="d-flex align-items-center justify-content-between flex-wrap mt-2 pt-2 border-top" style="gap: 10px;">
                    <div class="small font-weight-bold text-muted" id="poSearchPaginationInfo">
                        Menampilkan 0 - 0 dari 0 data
                    </div>
                    <div id="poSearchPaginationNav" class="btn-group btn-group-sm">
                        <!-- Tombol halaman dibuat dinamis via JS -->
                    </div>
                </div>
            </div>

            <!-- Footer: Tanggal Kirim + Submit & Cancel -->
            <div class="modal-footer d-flex align-items-center justify-content-between flex-wrap bg-light px-3 py-2">
                <div class="d-flex align-items-center flex-wrap" style="gap: 12px;">
                    <div class="d-flex align-items-center">
                        <label for="inputTanggalKirim" class="small font-weight-bold mb-0 mr-2 text-dark">
                            <i class="fas fa-calendar-alt text-primary mr-1"></i> Tanggal Kirim:
                        </label>
                        <input type="date" id="inputTanggalKirim" class="form-control form-control-sm font-weight-bold" 
                               value="<?= date('Y-m-d') ?>" style="width: 145px;">
                    </div>
                    <div>
                        <span id="poSelectedCountBadge" class="badge badge-primary px-3 py-2 font-weight-bold" style="font-size: 0.85rem;">
                            0 PO Terpilih
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-center" style="gap: 8px;">
                    <button type="button" id="btnSubmitPoSearch" class="btn btn-outline-primary btn-sm px-4 font-weight-bold" disabled>
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

<!-- ========================================================================
     MODAL: KONFIRMASI KEMBALI
     ======================================================================== -->
<div class="modal fade" id="modalKonfirmasiKembali" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title font-weight-bold">
                    <i class="fas fa-undo mr-2"></i>Konfirmasi Dokumen Kembali
                </h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="konfirmasiDetailId">
                <div class="mb-3 p-3 bg-light rounded border">
                    <div class="small text-muted font-weight-bold">NO PO</div>
                    <div class="font-weight-bold text-primary" id="konfirmasiPonmbr" style="font-size: 1.1rem;"></div>
                    <div class="small text-muted mt-1" id="konfirmasiVendor"></div>
                </div>
                <div class="form-group">
                    <label class="font-weight-bold small mb-2">Apakah dokumen sudah ditandatangani oleh Direktur?</label>
                    <div class="d-flex flex-column" style="gap: 8px;">
                        <label class="d-flex align-items-center p-2 rounded border" style="cursor:pointer; transition: all 0.15s;">
                            <input type="radio" name="konfirmasiTTD" value="1" class="mr-2">
                            <i class="fas fa-check-circle text-success mr-2"></i>
                            <span class="font-weight-bold small">Ya, sudah ditandatangani</span>
                        </label>
                        <label class="d-flex align-items-center p-2 rounded border" style="cursor:pointer; transition: all 0.15s;">
                            <input type="radio" name="konfirmasiTTD" value="0" class="mr-2" checked>
                            <i class="fas fa-times-circle text-danger mr-2"></i>
                            <span class="font-weight-bold small">Tidak, belum ditandatangani</span>
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label class="font-weight-bold small"><i class="fas fa-sticky-note mr-1"></i> Catatan (opsional)</label>
                    <textarea id="konfirmasiCatatan" class="form-control form-control-sm" rows="2" placeholder="Catatan tambahan..."></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm font-weight-bold" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Batal
                </button>
                <button type="button" class="btn btn-warning btn-sm font-weight-bold" id="btnSubmitKonfirmasi">
                    <i class="fas fa-check mr-1"></i> Konfirmasi
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================
     MODAL: DETAIL PO (View Rincian Barang dari ERP)
     ======================================================================== -->
<div class="modal fade" id="modalDetailPo" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title font-weight-bold d-flex align-items-center">
                    <i class="fas fa-boxes mr-2"></i>Detail Purchase Order : <span id="modalPoNumber" class="ml-2 badge badge-light text-dark px-2 py-1" style="font-family: monospace;"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-3">
                <!-- Loading State -->
                <div id="modalDetailLoading" class="text-center py-4">
                    <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                    <div class="mt-2 text-muted small font-weight-bold">Memuat detail PO...</div>
                </div>

                <!-- Content (hidden until loaded) -->
                <div id="modalDetailContent" style="display: none;">
                    <!-- Header Info PO -->
                    <div class="modal-po-info-box p-3 mb-3">
                        <div class="row no-gutters">
                            <!-- 1. Nomor PO -->
                            <div class="col-lg-4 col-md-6 col-12 info-segment">
                                <div class="d-flex align-items-center">
                                    <div class="info-icon-wrapper bg-primary text-white mr-3 shadow-sm">
                                        <i class="fas fa-file-invoice"></i>
                                    </div>
                                    <div style="min-width: 0;">
                                        <small class="text-muted d-block font-weight-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">Nomor PO</small>
                                        <span id="modalPoTitle" class="font-weight-bold text-primary text-truncate d-block" style="font-size: 1.05rem;"></span>
                                        <small class="text-muted d-block" style="font-size: 0.78rem;">
                                            <i class="far fa-calendar-alt text-secondary mr-1"></i><span id="modalPoDate"></span>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <!-- 2. Vendor / Supplier -->
                            <div class="col-lg-4 col-md-6 col-12 info-segment">
                                <div class="d-flex align-items-center">
                                    <div class="info-icon-wrapper bg-info text-white mr-3 shadow-sm">
                                        <i class="fas fa-building"></i>
                                    </div>
                                    <div style="min-width: 0;">
                                        <small class="text-muted d-block font-weight-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">Vendor / Supplier</small>
                                        <span id="modalPoVendor" class="font-weight-bold text-dark text-truncate d-block" style="font-size: 0.95rem;"></span>
                                        <small class="text-muted d-block" style="font-size: 0.78rem;">Mitra Penyedia Barang</small>
                                    </div>
                                </div>
                            </div>
                            <!-- 3. PIC Pembuat PO -->
                            <div class="col-lg-4 col-md-6 col-12 info-segment">
                                <div class="d-flex align-items-center">
                                    <div class="info-icon-wrapper text-white mr-3 shadow-sm" style="background-color: #6f42c1;">
                                        <i class="fas fa-user-edit"></i>
                                    </div>
                                    <div style="min-width: 0;">
                                        <small class="text-muted d-block font-weight-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">PIC Pembuat PO</small>
                                        <span id="modalPoPic" class="font-weight-bold text-dark text-truncate d-block" style="font-size: 0.95rem;"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabel Detail Item / Product -->
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0" id="tableModalItems">
                            <thead>
                                <tr>
                                    <th style="width: 35px;" class="text-center">No</th>
                                    <th style="width: 130px;">Kode Barang</th>
                                    <th style="min-width: 220px;">Nama Barang / Produk</th>
                                    <th style="min-width: 140px;">Struct Name</th>
                                    <th style="width: 90px;" class="text-right">Qty PO</th>
                                </tr>
                            </thead>
                            <tbody id="modalItemsBody"></tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="text-right text-uppercase small text-muted font-weight-bold">Total Ringkasan:</td>
                                    <td class="text-right font-weight-bold text-primary" id="modalFooterTotalQty">0</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light px-3 py-2 border-top">
                <button type="button" class="btn btn-secondary btn-sm px-3 font-weight-bold" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================
     MODAL: LOG HISTORY
     ======================================================================== -->
<div class="modal fade" id="modalLogHistory" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title font-weight-bold">
                    <i class="fas fa-history mr-2"></i>Log History : <span id="logPonmbr"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-3">
                <div id="logTimelineContainer"></div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm font-weight-bold" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<?php 
$footerPath = file_exists($rootPath . '/includes/footer.php') ? ($rootPath . '/includes/footer.php') : ($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php');
include($footerPath); 
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">

<!-- DataTables JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>

<!-- SweetAlert2 -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
$(document).ready(function () {
    var BASE_URL = '/gg_app/pages/purchasing/tracking_po/tracking_po_serverside.php';
    var activeCardFilter = '';
    var dataTable = null;
    var userPerms = { can_input: false, can_confirm: false, can_approve: false, is_admin: false };

    // ─── STATUS BADGE HELPER ──────────────────────────────────────────────
    function getStatusBadge(status) {
        switch (status) {
            case 'Diserahkan':
                return '<span class="badge-status badge-status-diserahkan"><i class="fas fa-paper-plane"></i> Diserahkan</span>';
            case 'Belum Kembali':
                return '<span class="badge-status badge-status-belum-kembali"><i class="fas fa-clock"></i> Belum Kembali</span>';
            case 'Sudah Kembali':
                return '<span class="badge-status badge-status-sudah-kembali"><i class="fas fa-undo"></i> Sudah Kembali</span>';
            case 'Sudah TTD':
                return '<span class="badge-status badge-status-sudah-ttd"><i class="fas fa-check-circle"></i> Sudah TTD</span>';
            case 'Approved':
                return '<span class="badge-status badge-status-approved"><i class="fas fa-lock"></i> Approved</span>';
            default:
                return '<span class="badge badge-secondary">' + status + '</span>';
        }
    }

    // ─── LOG DOT COLOR ────────────────────────────────────────────────────
    function getLogDotColor(action) {
        switch (action) {
            case 'Diserahkan': return '#3b82f6';
            case 'Konfirmasi Kembali': return '#f59e0b';
            case 'Approve': return '#8b5cf6';
            default: return '#94a3b8';
        }
    }

    // ─── LOAD SUMMARY CARDS ───────────────────────────────────────────────
    function loadSummary() {
        $.getJSON(BASE_URL + '?action=get_summary', function (res) {
            if (res.status === 'ok') {
                $('#countDiserahkan').text(res.summary.diserahkan);
                $('#countBelumKembali').text(res.summary.belum_kembali);
                $('#countSudahTTD').text(res.summary.sudah_ttd);
                $('#countApproved').text(res.summary.approved);
            }
        });
    }

    // ─── LOAD DATA TABLE ──────────────────────────────────────────────────
    function loadData() {
        var params = {
            action: 'load_data',
            date_from: $('#filterDateFrom').val(),
            date_to: $('#filterDateTo').val(),
            filter_status: $('#filterStatus').val()
        };

        $.getJSON(BASE_URL, params, function (res) {
            if (res.status !== 'ok') {
                Swal.fire('Error', res.message || 'Gagal memuat data.', 'error');
                return;
            }

            userPerms = res.user || userPerms;

            // Destroy existing DataTable
            if (dataTable) {
                dataTable.destroy();
                dataTable = null;
            }

            var html = '';
            if (res.data.length === 0) {
                html = '<tr><td colspan="9" class="text-center py-3 text-muted">Tidak ada data tracking PO.</td></tr>';
            } else {
                $.each(res.data, function (i, row) {
                    var aksiHtml = '<div class="action-btn-group">';

                    // View Detail PO - selalu tampil
                    aksiHtml += '<button class="btn btn-info btn-view-detail" data-ponmbr="' + row.ponmbr + '" title="Lihat Detail Barang PO"><i class="fas fa-eye"></i></button>';

                    // Konfirmasi Kembali - Purchasing/Admin, status Diserahkan/Belum Kembali
                    if (userPerms.can_confirm && (row.status === 'Diserahkan' || row.status === 'Belum Kembali')) {
                        aksiHtml += '<button class="btn btn-warning btn-konfirmasi" data-id="' + row.detail_id + '" data-ponmbr="' + row.ponmbr + '" data-vendor="' + (row.povendorname || '-') + '" title="Konfirmasi Dokumen Kembali"><i class="fas fa-undo"></i></button>';
                    }

                    // Approve - IT/Admin, status Sudah TTD
                    if (userPerms.can_approve && row.status === 'Sudah TTD') {
                        aksiHtml += '<button class="btn btn-success btn-approve" data-id="' + row.detail_id + '" data-ponmbr="' + row.ponmbr + '" title="Approve PO"><i class="fas fa-check"></i></button>';
                    }

                    // Log History - selalu tampil
                    aksiHtml += '<button class="btn btn-dark btn-log" data-id="' + row.detail_id + '" data-ponmbr="' + row.ponmbr + '" title="Lihat Log History"><i class="fas fa-history"></i></button>';

                    // Hapus Tracking PO - Purchasing / Admin (status bukan Approved, atau Admin)
                    if (userPerms.can_delete && (row.status !== 'Approved' || userPerms.is_admin)) {
                        aksiHtml += '<button class="btn btn-danger btn-delete-po" data-id="' + row.detail_id + '" data-ponmbr="' + row.ponmbr + '" title="Hapus Data Tracking"><i class="fas fa-trash-alt"></i></button>';
                    }

                    aksiHtml += '</div>';

                    html += '<tr>' +
                        '<td class="text-center">' + row.no + '</td>' +
                        '<td class="text-center">' + row.tanggal_kirim + '</td>' +
                        '<td><span class="po-badge-code">' + row.ponmbr + '</span></td>' +
                        '<td class="font-weight-bold" style="font-size:12px;">' + row.povendorname + '</td>' +
                        '<td class="text-center">' + row.podate + '</td>' +
                        '<td style="font-size:12px;">' + row.diserahkan_oleh + '</td>' +
                        '<td class="text-center">' + getStatusBadge(row.status) + '</td>' +
                        '<td class="text-center">' + (row.tanggal_kembali || '-') + '</td>' +
                        '<td class="text-center">' + aksiHtml + '</td>' +
                    '</tr>';
                });
            }

            $('#trackingPoBody').html(html);

            // Init DataTable
            dataTable = $('#trackingPoTable').DataTable({
                order: [],
                responsive: true,
                pageLength: 25,
                language: {
                    processing: "Memproses...",
                    lengthMenu: "Tampilkan _MENU_ data per halaman",
                    zeroRecords: "Tidak ada data tracking PO ditemukan",
                    info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
                    infoEmpty: "Tidak ada data tersedia",
                    infoFiltered: "(disaring dari _MAX_ total data)",
                    search: "Cari:",
                    paginate: { first: "Pertama", last: "Terakhir", next: "›", previous: "‹" }
                }
            });
        }).fail(function () {
            Swal.fire('Error', 'Gagal terhubung ke server.', 'error');
        });
    }

    // ─── INITIAL LOAD ─────────────────────────────────────────────────────
    loadSummary();
    loadData();

    // ─── FILTER EVENTS ────────────────────────────────────────────────────
    $('#filterDateFrom, #filterDateTo').on('change', function () {
        loadData();
        loadSummary();
    });
    $('#filterStatus').on('change', function () {
        // Deactivate card highlights
        $('.tracking-stat-card').removeClass('active');
        var val = $(this).val();
        if (val) {
            $('.tracking-stat-card[data-status="' + val + '"]').addClass('active');
        }
        activeCardFilter = val;
        loadData();
    });

    // ─── CARD FILTER CLICK ────────────────────────────────────────────────
    $(document).on('click', '.tracking-stat-card', function () {
        var clickedStatus = $(this).data('status');
        if (activeCardFilter === clickedStatus) {
            // Toggle off
            $('#filterStatus').val('');
            activeCardFilter = '';
            $('.tracking-stat-card').removeClass('active');
        } else {
            $('#filterStatus').val(clickedStatus);
            activeCardFilter = clickedStatus;
            $('.tracking-stat-card').removeClass('active');
            $(this).addClass('active');
        }
        loadData();
    });

    // ─── RESET FILTER ─────────────────────────────────────────────────────
    $('#btnResetFilter').on('click', function () {
        $('#filterDateFrom').val('');
        $('#filterDateTo').val('');
        $('#filterStatus').val('');
        activeCardFilter = '';
        $('.tracking-stat-card').removeClass('active');
        loadData();
        loadSummary();
    });

    // =========================================================================
    // SEARCH PO MODAL (Model Search CP and Routing di cp_planning.php)
    // =========================================================================
    var selectedPoMap = {};       // Menyimpan objek PO terpilih: { ponmbr: { ponmbr, pohdid, povendorname, podate_raw, ... } }
    var currentPoGridData = [];   // Cache data halaman saat ini
    var currentPoSearchPage = 1;
    var totalPoSearchPages = 1;

    function updatePoSelectionUI() {
        var selectedCount = Object.keys(selectedPoMap).length;
        $('#poSelectedCountBadge').text(selectedCount + ' PO Terpilih');
        $('#btnSubmitPoSearch').prop('disabled', selectedCount === 0);

        // Update checkbox dan highlight kelas table-active pada baris halaman saat ini
        $('#poSearchTableBody tr').each(function () {
            var ponmbr = $(this).data('ponmbr');
            var isTracked = $(this).hasClass('row-disabled');
            if (isTracked) return;

            var isChecked = !!selectedPoMap[ponmbr];
            $(this).find('.po-row-check').prop('checked', isChecked);
            if (isChecked) {
                $(this).addClass('table-active');
            } else {
                $(this).removeClass('table-active');
            }
        });
    }

    function renderPoSearchPagination(currentPage, totalPages, totalRows, limit) {
        var startRow = totalRows > 0 ? ((currentPage - 1) * limit + 1) : 0;
        var endRow = Math.min(currentPage * limit, totalRows);
        $('#poSearchPaginationInfo').html('Menampilkan <strong>' + startRow + ' - ' + endRow + '</strong> dari <strong>' + totalRows.toLocaleString('id-ID') + '</strong> data');

        var navHtml = '';
        if (totalPages <= 1) {
            $('#poSearchPaginationNav').html('');
            return;
        }

        // Tombol Previous
        var prevDisabled = currentPage === 1 ? 'disabled' : '';
        navHtml += '<button type="button" class="btn btn-outline-secondary btn-po-page" data-page="' + (currentPage - 1) + '" ' + prevDisabled + '>&laquo;</button>';

        // Logika tampilan nomor halaman
        var startPage = Math.max(1, currentPage - 2);
        var endPage = Math.min(totalPages, currentPage + 2);

        if (startPage > 1) {
            navHtml += '<button type="button" class="btn btn-outline-secondary btn-po-page" data-page="1">1</button>';
            if (startPage > 2) {
                navHtml += '<button type="button" class="btn btn-outline-secondary" disabled>...</button>';
            }
        }

        for (var p = startPage; p <= endPage; p++) {
            var activeClass = (p === currentPage) ? 'btn-primary active text-white' : 'btn-outline-secondary';
            navHtml += '<button type="button" class="btn ' + activeClass + ' btn-po-page" data-page="' + p + '">' + p + '</button>';
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                navHtml += '<button type="button" class="btn btn-outline-secondary" disabled>...</button>';
            }
            navHtml += '<button type="button" class="btn btn-outline-secondary btn-po-page" data-page="' + totalPages + '">' + totalPages + '</button>';
        }

        // Tombol Next
        var nextDisabled = currentPage === totalPages ? 'disabled' : '';
        navHtml += '<button type="button" class="btn btn-outline-secondary btn-po-page" data-page="' + (currentPage + 1) + '" ' + nextDisabled + '>&raquo;</button>';

        $('#poSearchPaginationNav').html(navHtml);
    }

    function loadPoSearchGrid(page) {
        currentPoSearchPage = page || 1;
        $('#loadingOverlayPoSearch').fadeIn(100);

        var params = {
            action: 'search_po_grid',
            page: currentPoSearchPage,
            limit: 25,
            search_field: $('#poSearchField').val(),
            keyword: $.trim($('#poSearchKeyword').val())
        };

        $.getJSON(BASE_URL, params, function (res) {
            $('#loadingOverlayPoSearch').fadeOut(100);
            if (res.status !== 'ok') {
                Swal.fire('Error', res.message || 'Gagal memuat daftar PO.', 'error');
                return;
            }

            currentPoGridData = res.data || [];
            totalPoSearchPages = res.total_pages || 1;
            var rowsHtml = '';

            if (currentPoGridData.length === 0) {
                rowsHtml = '<tr><td colspan="8" class="text-center py-4 text-muted"><i class="fas fa-info-circle mr-1"></i> Tidak ada data Purchase Order ditemukan.</td></tr>';
            } else {
                $.each(currentPoGridData, function (i, r) {
                    var isSelected = !!selectedPoMap[r.ponmbr];
                    var isTracked = !!r.already_tracked;

                    var trClass = '';
                    if (isTracked) trClass = 'row-disabled';
                    else if (isSelected) trClass = 'table-active';

                    var checkAttr = '';
                    if (isTracked) checkAttr = 'disabled title="PO ini sudah dalam proses tracking"';
                    else if (isSelected) checkAttr = 'checked';

                    var statusBadge = isTracked
                        ? '<span class="badge badge-secondary px-2 py-1"><i class="fas fa-lock mr-1"></i>Sedang Ditracking</span>'
                        : '<span class="badge badge-warning px-2 py-1 text-dark"><i class="fas fa-edit mr-1"></i>Draft (O)</span>';

                    var reqDisplay = r.requested_by_name !== '-'
                        ? '<span class="font-weight-bold text-dark">' + r.requested_by_name + '</span>' + (r.requested_by_id ? '<br><small class="text-muted">' + r.requested_by_id + '</small>' : '')
                        : '<span class="text-muted">-</span>';

                    var vendorDisplay = '<span class="font-weight-bold text-dark">' + (r.povendorname || '-') + '</span>' +
                        (r.povendorcode ? ' <span class="badge badge-light border text-muted small">' + r.povendorcode + '</span>' : '');

                    rowsHtml += '<tr class="' + trClass + '" data-ponmbr="' + r.ponmbr + '" data-index="' + i + '">' +
                        '<td class="po-select-col">' +
                            '<div class="po-seq-wrap">' +
                                '<input type="checkbox" class="po-row-check" ' + checkAttr + '>' +
                                '<span class="po-row-seq">' + r.seq + '</span>' +
                            '</div>' +
                        '</td>' +
                        '<td class="text-center font-weight-bold"><span class="po-badge-code">' + r.ponmbr + '</span></td>' +
                        '<td class="text-center text-muted">' + r.podate + '</td>' +
                        '<td>' + vendorDisplay + '</td>' +
                        '<td>' + reqDisplay + '</td>' +
                        '<td class="text-secondary font-weight-bold">' + r.pic_pembuat + '</td>' +
                        '<td class="text-truncate text-break" style="max-width: 280px;" title="' + (r.podesc || '') + '">' + r.podesc + '</td>' +
                        '<td class="text-center">' + statusBadge + '</td>' +
                    '</tr>';
                });
            }

            $('#poSearchTableBody').html(rowsHtml);
            renderPoSearchPagination(currentPoSearchPage, totalPoSearchPages, res.total_rows || 0, res.limit || 25);
            updatePoSelectionUI();
        }).fail(function () {
            $('#loadingOverlayPoSearch').fadeOut(100);
            Swal.fire('Error', 'Gagal menghubungi server.', 'error');
        });
    }

    // Buka Modal Search PO saat tombol + Serahkan PO diklik
    $('#btnSerahkanPo').on('click', function () {
        $('#inputTanggalKirim').val(new Date().toISOString().split('T')[0]);
        $('#poSearchKeyword').val('');
        $('#poSearchField').val('all');
        selectedPoMap = {};
        updatePoSelectionUI();
        $('#modalSerahkanPo').modal('show');
        loadPoSearchGrid(1);
    });

    // Pindah halaman via pagination
    $(document).on('click', '.btn-po-page', function () {
        var page = parseInt($(this).data('page'));
        if (page && page >= 1 && page <= totalPoSearchPages && page !== currentPoSearchPage) {
            loadPoSearchGrid(page);
        }
    });

    // Cari PO via toolbar
    $('#btnPoSearchApply').on('click', function () {
        loadPoSearchGrid(1);
    });
    $('#poSearchKeyword').on('keypress', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            loadPoSearchGrid(1);
        }
    });

    // Reset toolbar pencarian
    $('#btnPoSearchReset').on('click', function () {
        $('#poSearchKeyword').val('');
        $('#poSearchField').val('all');
        loadPoSearchGrid(1);
    });

    // Toggle baris PO klik
    $(document).on('click', '#poSearchTableBody tr', function (e) {
        if ($(this).hasClass('row-disabled')) return;
        if ($(e.target).is('input.po-row-check')) {
            var checked = $(e.target).prop('checked');
            var ponmbr = $(this).data('ponmbr');
            var idx = $(this).data('index');
            var rowData = currentPoGridData[idx];
            if (checked && rowData) {
                selectedPoMap[ponmbr] = rowData;
            } else {
                delete selectedPoMap[ponmbr];
            }
            updatePoSelectionUI();
            return;
        }

        var ponmbr = $(this).data('ponmbr');
        var idx = $(this).data('index');
        var rowData = currentPoGridData[idx];
        if (!rowData) return;

        if (selectedPoMap[ponmbr]) {
            delete selectedPoMap[ponmbr];
        } else {
            selectedPoMap[ponmbr] = rowData;
        }
        updatePoSelectionUI();
    });

    // Select All di halaman saat ini
    $('#btnPoSearchSelectAll').on('click', function () {
        var allCheckedOnPage = true;
        var selectableRows = [];

        $('#poSearchTableBody tr').each(function () {
            if ($(this).hasClass('row-disabled')) return;
            var ponmbr = $(this).data('ponmbr');
            var idx = $(this).data('index');
            var rowData = currentPoGridData[idx];
            if (rowData) {
                selectableRows.push(rowData);
                if (!selectedPoMap[ponmbr]) {
                    allCheckedOnPage = false;
                }
            }
        });

        if (selectableRows.length === 0) return;

        if (allCheckedOnPage) {
            $.each(selectableRows, function (i, r) {
                delete selectedPoMap[r.ponmbr];
            });
        } else {
            $.each(selectableRows, function (i, r) {
                selectedPoMap[r.ponmbr] = r;
            });
        }
        updatePoSelectionUI();
    });

    // Submit batch PO yang diserahkan
    $('#btnSubmitPoSearch').on('click', function () {
        var tanggalKirim = $('#inputTanggalKirim').val();
        var selectedKeys = Object.keys(selectedPoMap);

        if (!tanggalKirim) {
            Swal.fire('Perhatian', 'Pilih tanggal kirim terlebih dahulu.', 'warning');
            return;
        }
        if (selectedKeys.length === 0) {
            Swal.fire('Perhatian', 'Pilih minimal 1 PO untuk diserahkan.', 'warning');
            return;
        }

        var poItems = [];
        $.each(selectedPoMap, function (k, r) {
            poItems.push({
                ponmbr: r.ponmbr,
                pohdid: r.pohdid,
                povendorname: r.povendorname || '-',
                podate_raw: r.podate_raw || null
            });
        });

        Swal.fire({
            title: 'Konfirmasi Penyerahan',
            html: 'Serahkan <b>' + poItems.length + ' PO</b> ke Direktur pada tanggal <b>' + tanggalKirim + '</b>?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-paper-plane mr-1"></i> Ya, Serahkan!',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#28a745'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            var $btn = $('#btnSubmitPoSearch');
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Memproses...');

            $.post(BASE_URL, {
                action: 'submit_serah',
                tanggal_kirim: tanggalKirim,
                po_items: JSON.stringify(poItems)
            }, function (res) {
                $btn.prop('disabled', false).html('<i class="fas fa-paper-plane mr-1"></i> Submit');
                if (res.status === 'ok') {
                    $('#modalSerahkanPo').modal('hide');
                    Swal.fire('Berhasil!', res.message || 'PO berhasil diserahkan ke Direktur.', 'success');
                    selectedPoMap = {};
                    loadSummary();
                    loadData();
                } else {
                    Swal.fire('Gagal', res.message || 'Gagal menyerahkan PO.', 'error');
                }
            }, 'json').fail(function () {
                $btn.prop('disabled', false).html('<i class="fas fa-paper-plane mr-1"></i> Submit');
                Swal.fire('Error', 'Gagal menghubungi server.', 'error');
            });
        });
    });

    // ─── OPEN MODAL KONFIRMASI KEMBALI ────────────────────────────────────
    $(document).on('click', '.btn-konfirmasi', function () {
        var detailId = $(this).data('id');
        var ponmbr = $(this).data('ponmbr');
        var vendor = $(this).data('vendor');

        $('#konfirmasiDetailId').val(detailId);
        $('#konfirmasiPonmbr').text(ponmbr);
        $('#konfirmasiVendor').text('Vendor: ' + vendor);
        $('input[name="konfirmasiTTD"][value="0"]').prop('checked', true);
        $('#konfirmasiCatatan').val('');
        $('#modalKonfirmasiKembali').modal('show');
    });

    // ─── SUBMIT KONFIRMASI KEMBALI ────────────────────────────────────────
    $('#btnSubmitKonfirmasi').on('click', function () {
        var detailId = $('#konfirmasiDetailId').val();
        var isSigned = $('input[name="konfirmasiTTD"]:checked').val();
        var catatan = $('#konfirmasiCatatan').val();

        if (!detailId) {
            Swal.fire('Error', 'Data PO tidak valid.', 'error');
            return;
        }

        var signText = isSigned === '1' ? 'Sudah Ditandatangani' : 'Belum Ditandatangani';

        Swal.fire({
            title: 'Konfirmasi',
            html: 'Konfirmasi dokumen sudah kembali dengan status: <strong>' + signText + '</strong>?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check mr-1"></i> Ya, Konfirmasi',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#f59e0b'
        }).then(function (result) {
            if (result.isConfirmed) {
                $('#btnSubmitKonfirmasi').prop('disabled', true);

                $.post(BASE_URL, {
                    action: 'konfirmasi_kembali',
                    detail_id: detailId,
                    is_signed: isSigned,
                    catatan: catatan
                }, function (res) {
                    if (res.status === 'ok') {
                        Swal.fire('Berhasil!', res.message, 'success');
                        $('#modalKonfirmasiKembali').modal('hide');
                        loadData();
                        loadSummary();
                    } else {
                        Swal.fire('Gagal', res.message || 'Terjadi kesalahan.', 'error');
                    }
                }, 'json').fail(function () {
                    Swal.fire('Error', 'Gagal terhubung ke server.', 'error');
                }).always(function () {
                    $('#btnSubmitKonfirmasi').prop('disabled', false);
                });
            }
        });
    });

    // ─── APPROVE PO ───────────────────────────────────────────────────────
    $(document).on('click', '.btn-approve', function () {
        var detailId = $(this).data('id');
        var ponmbr = $(this).data('ponmbr');

        Swal.fire({
            title: 'Approve PO',
            html: 'Approve PO <strong>' + ponmbr + '</strong>?<br><small class="text-muted">Aksi ini tidak dapat dibatalkan.</small>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check mr-1"></i> Ya, Approve',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#10b981'
        }).then(function (result) {
            if (result.isConfirmed) {
                $.post(BASE_URL, {
                    action: 'approve',
                    detail_id: detailId
                }, function (res) {
                    if (res.status === 'ok') {
                        Swal.fire('Berhasil!', res.message, 'success');
                        loadData();
                        loadSummary();
                    } else {
                        Swal.fire('Gagal', res.message || 'Terjadi kesalahan.', 'error');
                    }
                }, 'json').fail(function () {
                    Swal.fire('Error', 'Gagal terhubung ke server.', 'error');
                });
            }
        });
    });

    // ─── VIEW DETAIL PO (Rincian Barang dari ERP) ─────────────────────────
    $(document).on('click', '.btn-view-detail', function () {
        var ponmbr = $(this).data('ponmbr');
        $('#modalPoNumber').text(ponmbr);
        $('#modalDetailLoading').show();
        $('#modalDetailContent').hide();
        $('#modalDetailPo').modal('show');

        $.getJSON(BASE_URL, { action: 'view_detail_po', ponmbr: ponmbr }, function (res) {
            $('#modalDetailLoading').hide();

            if (res.status !== 'ok') {
                $('#modalDetailContent').html(
                    '<div class="text-center py-4 text-danger">' +
                    '<i class="fas fa-exclamation-triangle fa-2x mb-2"></i>' +
                    '<div class="font-weight-bold">' + (res.message || 'Gagal memuat detail.') + '</div></div>'
                ).show();
                return;
            }

            var h = res.header;
            $('#modalPoTitle').text(h.ponmbr);
            $('#modalPoDate').text(h.podate);
            $('#modalPoVendor').text(h.povendorname + (h.povendorcode ? ' (' + h.povendorcode + ')' : ''));
            $('#modalPoPic').text(h.pic_pembuat || '-');

            // Render items
            var itemsHtml = '';
            if (res.items && res.items.length > 0) {
                $.each(res.items, function (idx, item) {
                    var structHtml = (item.struct_name && item.struct_name !== '-')
                        ? '<span class="badge badge-light border text-secondary px-1" style="font-size:0.78rem; font-weight:600;">' +
                          '<i class="fas fa-layer-group mr-1" style="opacity:0.6;"></i>' + item.struct_name + '</span>'
                        : '<span class="text-muted">-</span>';

                    itemsHtml += '<tr>' +
                        '<td class="text-center font-weight-bold text-muted">' + (item.poseq || (idx + 1)) + '</td>' +
                        '<td><code class="text-secondary font-weight-bold px-1 py-0 rounded" style="background:#f1f5f9; font-size:0.83rem;">' + item.poprodcode + '</code></td>' +
                        '<td class="font-weight-bold text-dark text-break" style="line-height: 1.35;">' + item.poprodname + '</td>' +
                        '<td class="text-secondary small" style="line-height: 1.3;">' + structHtml + '</td>' +
                        '<td class="text-right font-weight-bold text-dark">' + item.poqty + '</td>' +
                    '</tr>';
                });
            } else {
                itemsHtml = '<tr><td colspan="5" class="text-center py-3 text-muted">Tidak ada rincian item barang.</td></tr>';
            }

            $('#modalItemsBody').html(itemsHtml);
            $('#modalFooterTotalQty').text(res.total_qty || '0');
            $('#modalDetailContent').show();
        }).fail(function () {
            $('#modalDetailLoading').hide();
            $('#modalDetailContent').html(
                '<div class="text-center py-4 text-danger">' +
                '<i class="fas fa-wifi fa-2x mb-2"></i>' +
                '<div class="font-weight-bold">Gagal terhubung ke server.</div></div>'
            ).show();
        });
    });

    // ─── VIEW LOG HISTORY ─────────────────────────────────────────────────
    $(document).on('click', '.btn-log', function () {
        var detailId = $(this).data('id');
        var ponmbr = $(this).data('ponmbr');

        $('#logPonmbr').text(ponmbr);
        $('#logTimelineContainer').html('<div class="text-center py-3"><i class="fas fa-spinner fa-spin text-muted"></i></div>');
        $('#modalLogHistory').modal('show');

        $.getJSON(BASE_URL, { action: 'get_log', detail_id: detailId }, function (res) {
            if (res.status !== 'ok' || !res.logs || res.logs.length === 0) {
                $('#logTimelineContainer').html('<div class="text-center py-3 text-muted">Belum ada log history.</div>');
                return;
            }

            var html = '';
            $.each(res.logs, function (i, log) {
                var dotColor = getLogDotColor(log.action);
                html += '<div class="log-timeline-item">' +
                    '<div class="log-timeline-dot" style="background-color:' + dotColor + ';"></div>' +
                    '<div class="log-timeline-content">' +
                        '<div class="d-flex justify-content-between align-items-start">' +
                            '<strong class="small">' + log.action + '</strong>' +
                            '<small class="text-muted">' + log.performed_at + '</small>' +
                        '</div>' +
                        '<div class="small text-muted">' + log.performed_by + '</div>' +
                        (log.keterangan ? '<div class="small mt-1 text-secondary" style="line-height:1.3;">' + log.keterangan + '</div>' : '') +
                    '</div>' +
                '</div>';
            });
            $('#logTimelineContainer').html(html);
        }).fail(function () {
            $('#logTimelineContainer').html('<div class="text-center py-3 text-danger">Gagal memuat log.</div>');
        });
    });

    // ─── HAPUS DATA TRACKING PO ───────────────────────────────────────────
    $(document).on('click', '.btn-delete-po', function () {
        var detailId = $(this).data('id');
        var ponmbr = $(this).data('ponmbr');

        Swal.fire({
            title: 'Hapus Tracking PO?',
            html: 'Apakah Anda yakin ingin menghapus data tracking untuk PO <b>' + ponmbr + '</b>?<br><small class="text-muted">PO ini akan dihapus dari daftar tracking dan dapat diserahkan kembali.</small>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash-alt mr-1"></i> Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $.post(BASE_URL, {
                action: 'delete_tracking',
                detail_id: detailId
            }, function (res) {
                if (res.status === 'ok') {
                    Swal.fire('Terhapus!', res.message || 'Data tracking PO berhasil dihapus.', 'success');
                    loadSummary();
                    loadData();
                } else {
                    Swal.fire('Gagal', res.message || 'Gagal menghapus data tracking.', 'error');
                }
            }, 'json').fail(function () {
                Swal.fire('Error', 'Gagal menghubungi server.', 'error');
            });
        });
    });

    // ─── RADIO BUTTON HIGHLIGHT ───────────────────────────────────────────
    $('input[name="konfirmasiTTD"]').on('change', function () {
        $(this).closest('.form-group').find('label.border').css('background', '');
        $(this).closest('label.border').css('background', '#f0fdf4');
    });
});
</script>
