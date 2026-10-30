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
require_once __DIR__ . '/tracking_po_issue.php';

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
.tracking-stat-card[data-status="Tunggu Tanggal Penyerahan"]::before { background: #ea580c; }
.tracking-stat-card[data-status="Diserahkan"]::before { background: #3b82f6; }
.tracking-stat-card[data-status="Belum Kembali"]::before { background: #f59e0b; }
.tracking-stat-card[data-status="Sudah Kembali"]::before { background: #06b6d4; }
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
.stat-icon-box.bg-orange { background: #fff7ed; color: #ea580c; }
.stat-icon-box.bg-blue { background: #eff6ff; color: #3b82f6; }
.stat-icon-box.bg-amber { background: #fffbeb; color: #f59e0b; }
.stat-icon-box.bg-cyan { background: #ecfeff; color: #0891b2; }
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
.badge-status-tunggu {
    background-color: #fff7ed;
    color: #ea580c;
    border: 1px solid #fed7aa;
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
    margin-top: 0.5rem;
    margin-bottom: 0.5rem;
    max-height: calc(100vh - 1rem);
}

.po-search-modal .modal-content {
    border-radius: 8px;
    border: none;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 1rem);
}

.po-search-modal .modal-body {
    min-height: 0;
    overflow-y: auto;
}

.po-search-modal .modal-footer {
    flex-shrink: 0;
}

/* Menjaga aksi pengiriman tetap terlihat saat tinggi viewport terbatas. */
#modalPilihDikirimKe .modal-dialog {
    margin-top: 0.5rem;
    margin-bottom: 0.5rem;
    max-height: calc(100vh - 1rem);
}

#modalPilihDikirimKe .modal-content {
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 1rem);
}

#modalPilihDikirimKe .modal-body {
    min-height: 0;
    overflow-y: auto;
}

#modalPilihDikirimKe .modal-footer {
    flex-shrink: 0;
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

/* =========================================================
   FLOATING ACTION BAR: KONFIRMASI MASSAL DOKUMEN KEMBALI
   ========================================================= */
.batch-floating-bar {
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 1040;
    width: max-content;
    max-width: calc(100vw - 32px);
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    pointer-events: auto;
}
.batch-floating-content {
    background: rgba(15, 23, 42, 0.95);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    color: #fff;
    padding: 8px 12px 8px 16px;
    border-radius: 9999px;
    box-shadow: 0 16px 36px -6px rgba(0, 0, 0, 0.45), 0 0 0 1px rgba(255, 255, 255, 0.12);
    display: inline-flex;
    align-items: center;
    gap: 10px;
}
.batch-counter-badge {
    background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
    color: #ffffff;
    font-weight: 700;
    font-size: 13px;
    padding: 6px 14px;
    border-radius: 9999px;
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.35);
    letter-spacing: 0.2px;
    flex-shrink: 0;
}
.batch-floating-divider {
    width: 1px;
    height: 24px;
    background: rgba(255, 255, 255, 0.18);
    flex-shrink: 0;
}
.btn-batch-cancel {
    background: rgba(255, 255, 255, 0.08);
    color: #cbd5e1 !important;
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 9999px;
    font-size: 12.5px;
    font-weight: 600;
    padding: 7px 14px;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    transition: all 0.2s ease;
    flex-shrink: 0;
}
.btn-batch-cancel:hover {
    background: rgba(239, 68, 68, 0.2);
    color: #fca5a5 !important;
    border-color: rgba(239, 68, 68, 0.35);
}
.btn-batch-ttd {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #ffffff !important;
    border: none;
    border-radius: 9999px;
    font-size: 13px;
    font-weight: 700;
    padding: 7px 16px;
    white-space: nowrap;
    box-shadow: 0 3px 12px rgba(16, 185, 129, 0.35);
    display: inline-flex;
    align-items: center;
    transition: all 0.2s ease;
    flex-shrink: 0;
}
.btn-batch-ttd:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    transform: translateY(-1px);
    box-shadow: 0 5px 16px rgba(16, 185, 129, 0.45);
}
.btn-batch-belum-ttd {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: #ffffff !important;
    border: none;
    border-radius: 9999px;
    font-size: 13px;
    font-weight: 700;
    padding: 7px 16px;
    white-space: nowrap;
    box-shadow: 0 3px 12px rgba(245, 158, 11, 0.35);
    display: inline-flex;
    align-items: center;
    transition: all 0.2s ease;
    flex-shrink: 0;
}
.btn-batch-belum-ttd:hover {
    background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
    transform: translateY(-1px);
    box-shadow: 0 5px 16px rgba(245, 158, 11, 0.45);
}
.btn-batch-approve {
    background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);
    color: #ffffff !important;
    border: none;
    border-radius: 9999px;
    font-size: 13px;
    font-weight: 700;
    padding: 7px 18px;
    white-space: nowrap;
    box-shadow: 0 3px 12px rgba(139, 92, 246, 0.4);
    display: inline-flex;
    align-items: center;
    transition: all 0.2s ease;
    flex-shrink: 0;
}
.btn-batch-approve:hover {
    background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%);
    transform: translateY(-1px);
    box-shadow: 0 5px 16px rgba(139, 92, 246, 0.5);
}
.btn-batch-delete {
    background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);
    color: #ffffff !important;
    border: none;
    border-radius: 9999px;
    font-size: 13px;
    font-weight: 700;
    padding: 7px 18px;
    white-space: nowrap;
    box-shadow: 0 3px 12px rgba(239, 68, 68, 0.4);
    display: inline-flex;
    align-items: center;
    transition: all 0.2s ease;
    flex-shrink: 0;
}
.btn-batch-delete:hover {
    background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
    transform: translateY(-1px);
    box-shadow: 0 5px 16px rgba(239, 68, 68, 0.5);
}
.batch-mode-badge {
    font-size: 11px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.16);
    color: #e2e8f0;
    display: inline-flex;
    align-items: center;
    letter-spacing: 0.3px;
}
.row-select-checkbox {
    width: 16px;
    height: 16px;
    cursor: pointer;
    vertical-align: middle;
}
.row-select-checkbox:disabled {
    cursor: not-allowed;
    opacity: 0.35;
}
#checkAllRows {
    width: 16px;
    height: 16px;
    cursor: pointer;
    vertical-align: middle;
}

@media (max-width: 768px) {
    .batch-floating-bar { width: 95%; max-width: 95%; bottom: 12px; }
    .batch-floating-content { padding: 10px 14px; border-radius: 18px; flex-wrap: wrap; justify-content: center; gap: 8px; }
    .batch-floating-divider { display: none; }
    .tracking-summary-row {
        flex-direction: column;
        gap: 8px;
    }
    .tracking-stat-card {
        min-width: 100%;
    }
}
/* Styling opsi Dikirim Ke */
.option-card {
    transition: all 0.2s ease;
    border: 1.5px solid #e2e8f0 !important;
}
.option-card:hover {
    border-color: #3b82f6 !important;
    background-color: #f8fafc !important;
}
.option-card.active-option {
    border-color: #2563eb !important;
    background-color: #eff6ff !important;
    box-shadow: 0 2px 5px rgba(37, 99, 235, 0.12);
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
                <div class="tracking-stat-card" data-status="Tunggu Tanggal Penyerahan" id="cardTungguPenyerahan">
                    <div class="stat-icon-box bg-orange"><i class="fas fa-hourglass-half"></i></div>
                    <div class="stat-info">
                        <div class="stat-count" id="countTungguPenyerahan">0</div>
                        <div class="stat-label">Tunggu Penyerahan</div>
                    </div>
                </div>
                <div class="tracking-stat-card" data-status="Diserahkan" id="cardDiserahkan">
                    <div class="stat-icon-box bg-blue"><i class="fas fa-paper-plane"></i></div>
                    <div class="stat-info">
                        <div class="stat-count" id="countDiserahkan">0</div>
                        <div class="stat-label">Diserahkan</div>
                    </div>
                </div>
                <div class="tracking-stat-card" data-status="Belum Kembali" id="cardBelumKembali">
                    <div class="stat-icon-box bg-amber"><i class="fas fa-clock"></i></div>
                    <div class="stat-info">
                        <div class="stat-count" id="countBelumKembali">0</div>
                        <div class="stat-label">Belum Kembali</div>
                    </div>
                </div>
                <div class="tracking-stat-card" data-status="Sudah Kembali" id="cardSudahKembali">
                    <div class="stat-icon-box bg-cyan"><i class="fas fa-undo"></i></div>
                    <div class="stat-info">
                        <div class="stat-count" id="countSudahKembali">0</div>
                        <div class="stat-label">Sudah Kembali (Belum TTD)</div>
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
                    <div class="card-tools float-right d-flex align-items-center" style="gap: 6px;">
                        <?php if ($userCanDelete): ?>
                        <button type="button" class="btn btn-danger btn-sm font-weight-bold shadow-sm" id="btnTopBatchDelete" style="display: none;" title="Hapus data tracking PO yang dipilih">
                            <i class="fas fa-trash-alt mr-1"></i> Hapus Terpilih (<span class="batch-delete-count">0</span>)
                        </button>
                        <?php endif; ?>
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
                                    <option value="Tunggu Tanggal Penyerahan">Tunggu Tanggal Penyerahan</option>
                                    <option value="Diserahkan">Diserahkan</option>
                                    <option value="Belum Kembali">Belum Kembali</option>
                                    <option value="Sudah Kembali">Sudah Kembali (Belum TTD)</option>
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
                                    <th style="width:32px;" class="text-center align-middle">
                                        <input type="checkbox" id="checkAllRows" title="Pilih Semua yang Belum Dikonfirmasi">
                                    </th>
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
                        <button type="button" class="btn btn-outline-secondary btn-sm ml-1" id="btnClearTanggalKirim" title="Kosongkan tanggal (status jadi Tunggu Tanggal Penyerahan)">
                            <i class="fas fa-times mr-1"></i> Kosongkan
                        </button>
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
     MODAL: PILIH TUJUAN PENGIRIMAN (DIKIRIM KE)
     ======================================================================== -->
<div class="modal fade" id="modalPilihDikirimKe" tabindex="-1" role="dialog" aria-labelledby="modalPilihDikirimKeLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title font-weight-bold" id="modalPilihDikirimKeLabel">
                    <i class="fas fa-truck-moving mr-2"></i>Pilihan Tujuan Pengiriman (Dikirim Ke)
                </h5>
                <button type="button" class="close text-white" id="btnClosePilihDikirimKe" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom flex-wrap" style="gap: 8px;">
                    <div>
                        <h6 class="font-weight-bold text-dark mb-1">Tentukan Tujuan Pengiriman</h6>
                        <small class="text-muted">Pilih lokasi pengiriman untuk berkas / barang Purchase Order yang akan diserahkan.</small>
                    </div>
                    <span id="badgeCountPilihDikirimKe" class="badge badge-primary px-3 py-2 font-weight-bold" style="font-size: 0.85rem;">
                        0 PO Terpilih
                    </span>
                </div>

                <!-- Pilihan Radio Card -->
                <div class="dikirim-ke-options mb-3">
                    <!-- Opsi 1: PT.SUM -->
                    <div class="custom-control custom-radio mb-2 p-3 rounded option-card active-option" id="cardOptSum" style="cursor: pointer;">
                        <input type="radio" id="optDikirimKe1" name="radioDikirimKe" class="custom-control-input" value="PT.SUM" checked>
                        <label class="custom-control-label font-weight-bold text-dark w-100" for="optDikirimKe1" style="cursor: pointer;">
                            <span class="d-flex align-items-center justify-content-between flex-wrap">
                                <span><i class="fas fa-building text-primary mr-2"></i>1. PT.SUM</span>
                                <span class="badge badge-primary font-weight-normal small px-2 py-1">Pusat</span>
                            </span>
                            <small class="d-block text-muted font-weight-normal mt-1" style="font-size: 0.82rem;">
                                <i class="fas fa-map-marker-alt text-danger mr-1"></i>Jl. Desa Tarajusari, No.8, Tarajusari, Banjaran, Kab.Bandung
                            </small>
                        </label>
                    </div>

                    <!-- Opsi 2: Sanso Berjaya Texindo -->
                    <div class="custom-control custom-radio mb-2 p-3 rounded option-card" id="cardOptSanso" style="cursor: pointer;">
                        <input type="radio" id="optDikirimKe2" name="radioDikirimKe" class="custom-control-input" value="Sanso Berjaya Texindo/Toko Sumber SERAGAM">
                        <label class="custom-control-label font-weight-bold text-dark w-100" for="optDikirimKe2" style="cursor: pointer;">
                            <span class="d-flex align-items-center justify-content-between flex-wrap">
                                <span><i class="fas fa-store text-success mr-2"></i>2. Sanso Berjaya Texindo/Toko Sumber SERAGAM</span>
                                <span class="badge badge-success font-weight-normal small px-2 py-1">Mitra Cabang</span>
                            </span>
                            <small class="d-block text-muted font-weight-normal mt-1" style="font-size: 0.82rem;">
                                <i class="fas fa-map-marker-alt text-danger mr-1"></i>Jl. Waas No.5B, Batununggal, Sebelah JNT, UP Ibu Verty, Telp : 022 87313631
                            </small>
                        </label>
                    </div>

                    <!-- Opsi 3: Diambil ke Supplier -->
                    <div class="custom-control custom-radio mb-2 p-3 rounded option-card" id="cardOptSupplier" style="cursor: pointer;">
                        <input type="radio" id="optDikirimKe3" name="radioDikirimKe" class="custom-control-input" value="Diambil ke Supplier">
                        <label class="custom-control-label font-weight-bold text-dark w-100" for="optDikirimKe3" style="cursor: pointer;">
                            <span class="d-flex align-items-center justify-content-between flex-wrap">
                                <span><i class="fas fa-boxes text-warning mr-2"></i>3. Diambil ke Supplier</span>
                                <span class="badge badge-warning text-dark font-weight-bold small px-2 py-1">Master Vendor ERP</span>
                            </span>
                            <small class="d-block text-muted font-weight-normal mt-1" style="font-size: 0.82rem;">
                                <i class="fas fa-info-circle text-info mr-1"></i>Alamat otomatis diambil dari master vendor / supplier masing-masing PO
                            </small>
                        </label>
                    </div>
                </div>

                <!-- Textarea Alamat Pengiriman (Editable) -->
                <div class="form-group mb-2">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <label for="inputAlamatKirim" class="font-weight-bold text-dark small mb-0">
                            <i class="fas fa-map-marked-alt text-danger mr-1"></i>Detail / Catatan Alamat Pengiriman:
                        </label>
                        <small class="text-muted" id="alamatKirimHint">(Dapat disesuaikan jika perlu catatan khusus)</small>
                    </div>
                    <textarea id="inputAlamatKirim" class="form-control font-weight-normal" rows="3" placeholder="Masukkan alamat pengiriman lengkap..."></textarea>
                </div>

                <!-- Preview Alamat Supplier Multi-PO (khusus opsi 3 jika multi PO) -->
                <div id="supplierMultiPoPreview" class="mt-2 p-2 rounded bg-light border small" style="display: none; max-height: 140px; overflow-y: auto;">
                    <div class="font-weight-bold text-secondary mb-1">
                        <i class="fas fa-list mr-1"></i>Daftar Alamat Vendor PO Terpilih (dari Master Supplier):
                    </div>
                    <div id="supplierMultiPoList"></div>
                </div>
            </div>
            <div class="modal-footer bg-light px-3 py-2 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm px-4 font-weight-bold" id="btnBatalDikirimKe">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar PO
                </button>
                <button type="button" class="btn btn-primary btn-sm px-4 font-weight-bold" id="btnConfirmDikirimKe">
                    <i class="fas fa-check-circle mr-1"></i> Lanjutkan & Serahkan PO
                </button>
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

                        <!-- Baris Tambahan: Tujuan Pengiriman (Dikirim Ke) & Alamat -->
                        <div id="modalPoShippingBox" class="mt-3 pt-2 border-top" style="display: none;">
                            <div class="d-flex align-items-start">
                                <div class="info-icon-wrapper bg-warning text-dark mr-3 shadow-sm" style="width: 38px; height: 38px; font-size: 15px;">
                                    <i class="fas fa-truck-moving"></i>
                                </div>
                                <div style="min-width: 0;">
                                    <small class="text-muted d-block font-weight-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">Tujuan Pengiriman (Dikirim Ke)</small>
                                    <div class="d-flex align-items-center flex-wrap mt-1" style="gap: 6px;">
                                        <span id="modalPoDikirimKe" class="badge badge-primary px-2 py-1 font-weight-bold" style="font-size: 0.85rem;"></span>
                                    </div>
                                    <div class="small text-dark mt-1" id="modalPoAlamatKirimWrap">
                                        <i class="fas fa-map-marker-alt text-danger mr-1"></i><span id="modalPoAlamatKirim" class="text-muted font-weight-bold"></span>
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

<!-- ========================================================================
     FLOATING ACTION BAR: AKSI MASSAL (KONFIRMASI KEMBALI & APPROVE)
     ======================================================================== -->
<div id="batchKonfirmasiFloatingBar" class="batch-floating-bar" style="display: none;">
    <div class="batch-floating-content">
        <div class="batch-counter-badge" id="floatingSelectedBadge">
            <i class="fas fa-layer-group mr-2" style="font-size: 13px;"></i>
            <span id="floatingSelectedCount" class="mr-1">0</span> PO Terpilih
        </div>
        <span id="floatingModeBadge" class="batch-mode-badge d-none d-md-inline-flex" style="display: none;"></span>
        <div class="batch-floating-divider d-none d-sm-block"></div>
        <button type="button" class="btn btn-batch-cancel" id="btnCancelBatchSelection" title="Batalkan pilihan">
            <i class="fas fa-times mr-1"></i> Batal
        </button>
        <!-- Group Konfirmasi Kembali (Purchasing) -->
        <span id="batchGroupKonfirmasi" style="display: inline-flex; gap: 8px;">
            <button type="button" class="btn btn-batch-ttd" id="btnBatchSudahTTD" title="Konfirmasi semua PO terpilih sudah ditandatangani">
                <i class="fas fa-check-circle mr-1"></i> Semua Sudah TTD
            </button>
            <button type="button" class="btn btn-batch-belum-ttd" id="btnBatchBelumTTD" title="Konfirmasi semua PO terpilih belum ditandatangani">
                <i class="fas fa-times-circle mr-1"></i> Semua Belum TTD
            </button>
        </span>
        <!-- Group Tunggu Tanggal Penyerahan (Input Tanggal Menyusul) -->
        <span id="batchGroupTunggu" style="display: none;">
            <button type="button" class="btn btn-batch-belum-ttd" id="btnBatchSetTanggal" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);" title="Input tanggal penyerahan untuk semua PO terpilih">
                <i class="fas fa-calendar-plus mr-1"></i> Input Tgl Penyerahan Terpilih
            </button>
        </span>
        <!-- Group Serahkan Kembali (Purchasing & Admin) -->
        <span id="batchGroupSerahkanKembali" style="display: none;">
            <button type="button" class="btn btn-batch-ttd" id="btnBatchSerahkanKembali" style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);" title="Serahkan kembali semua PO terpilih ke Direktur">
                <i class="fas fa-redo mr-1"></i> Serahkan Kembali Terpilih
            </button>
        </span>
        <!-- Group Hapus (Purchasing & Admin) -->
        <span id="batchGroupDelete" style="display: none;">
            <button type="button" class="btn btn-batch-delete" id="btnFloatingBatchDelete" title="Hapus semua PO terpilih">
                <i class="fas fa-trash-alt mr-1"></i> Hapus Terpilih (<span class="batch-delete-count">0</span>)
            </button>
        </span>
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
    var trackingPoCsrfToken = <?= json_encode(trackingPoIssueCsrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var trackingPoActivityRequestId = trackingPoNewRequestId();

    /**
     * Menghasilkan UUID v4 untuk satu percobaan submit batch Tracking PO.
     *
     * @returns {string} UUID yang dipakai server untuk idempotensi.
     */
    function trackingPoNewRequestId() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }

        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (character) {
            var value = Math.floor(Math.random() * 16);
            var digit = character === 'x' ? value : ((value & 0x3) | 0x8);
            return digit.toString(16);
        });
    }
    var activeCardFilter = '';
    var dataTable = null;
    var userPerms = { can_input: false, can_confirm: false, can_approve: false, is_admin: false };

    // ─── STATUS BADGE HELPER ──────────────────────────────────────────────
    function getStatusBadge(status, row) {
        switch (status) {
            case 'Tunggu Tanggal Penyerahan':
                var erpBadge = (row && row.is_approved_erp) 
                    ? ' <span class="badge badge-success ml-1 font-weight-bold" style="font-size:10px;" title="Sudah di-approve di ERP"><i class="fas fa-check"></i> ERP</span>' 
                    : '';
                return '<span class="badge-status badge-status-tunggu"><i class="fas fa-hourglass-half"></i> Tunggu Penyerahan</span>' + erpBadge;
            case 'Diserahkan':
                var erpBadge = (row && row.is_approved_erp) 
                    ? ' <span class="badge badge-success ml-1 font-weight-bold" style="font-size:10px;" title="Sudah di-approve di ERP"><i class="fas fa-check"></i> ERP</span>' 
                    : '';
                return '<span class="badge-status badge-status-diserahkan"><i class="fas fa-paper-plane"></i> Diserahkan</span>' + erpBadge;
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
            case 'Serahkan Kembali': return '#2563eb';
            case 'Set Tanggal Penyerahan': return '#f59e0b';
            case 'Konfirmasi Kembali': return '#f59e0b';
            case 'Approve': return '#8b5cf6';
            default: return '#94a3b8';
        }
    }

    // ─── LOAD SUMMARY CARDS ───────────────────────────────────────────────
    function loadSummary() {
        var params = {
            action: 'get_summary',
            date_from: $('#filterDateFrom').val() || '',
            date_to: $('#filterDateTo').val() || ''
        };
        $.getJSON(BASE_URL, params, function (res) {
            if (res.status === 'ok') {
                $('#countTungguPenyerahan').text(res.summary.tunggu_penyerahan || 0);
                $('#countDiserahkan').text(res.summary.diserahkan || 0);
                $('#countBelumKembali').text(res.summary.belum_kembali || 0);
                $('#countSudahKembali').text(res.summary.sudah_kembali || 0);
                $('#countApproved').text(res.summary.approved || 0);
            }
        });
    }

    var loadDataXhr = null;
    var selectedBatchIds = new Set();
    var batchMode = null; // null | 'konfirmasi' | 'serahkan_kembali' | 'set_tanggal'

    function updateBatchFloatingBar() {
        var count = selectedBatchIds.size;
        $('#floatingSelectedCount').text(count);
        $('.batch-delete-count').text(count);

        if (count > 0) {
            // Tampilkan tombol Hapus Terpilih di toolbar atas & floating bar jika user punya hak can_delete
            if (userPerms.can_delete) {
                $('#btnTopBatchDelete').show();
                $('#batchGroupDelete').show();
            } else {
                $('#btnTopBatchDelete').hide();
                $('#batchGroupDelete').hide();
            }

            // Tampilkan tombol alur sesuai status yang terpilih
            if (batchMode === 'serahkan_kembali') {
                $('#batchGroupKonfirmasi').hide();
                $('#batchGroupTunggu').hide();
                $('#batchGroupApprove').hide();
                $('#batchGroupSerahkanKembali').show();
                $('#floatingModeBadge').html('<i class="fas fa-redo mr-1"></i> Mode Serahkan Kembali').show();
            } else if (batchMode === 'set_tanggal') {
                $('#batchGroupKonfirmasi').hide();
                $('#batchGroupSerahkanKembali').hide();
                $('#batchGroupTunggu').show();
                $('#batchGroupApprove').hide();
                $('#floatingModeBadge').html('<i class="fas fa-hourglass-half mr-1"></i> Mode Tunggu Penyerahan').show();
            } else if (batchMode === 'konfirmasi') {
                $('#batchGroupApprove').hide();
                $('#batchGroupTunggu').hide();
                $('#batchGroupSerahkanKembali').hide();
                $('#batchGroupKonfirmasi').show();
                $('#floatingModeBadge').html('<i class="fas fa-undo mr-1"></i> Mode Konfirmasi Kembali').show();
            } else {
                // Pilihan lintas status / hapus massal
                $('#batchGroupKonfirmasi').hide();
                $('#batchGroupTunggu').hide();
                $('#batchGroupSerahkanKembali').hide();
                $('#batchGroupApprove').hide();
                $('#floatingModeBadge').html('<i class="fas fa-layer-group mr-1"></i> Pilihan Lintas Status').show();
            }

            if ($('#batchKonfirmasiFloatingBar').is(':hidden')) {
                $('#batchKonfirmasiFloatingBar').stop(true, true).fadeIn(250);
            }
        } else {
            $('#btnTopBatchDelete').hide();
            $('#batchGroupDelete').hide();
            $('#batchKonfirmasiFloatingBar').stop(true, true).fadeOut(200);
            $('#floatingModeBadge').hide();
            $('#checkAllRows').prop('checked', false).prop('indeterminate', false);
        }
    }

    function syncBatchCheckboxes() {
        var totalEnabled = 0;
        var checkedCount = 0;

        var countKonfirmasi = 0;
        var countTunggu = 0;
        var countSudahKembali = 0;
        var countSudahTTD = 0;
        var countApproved = 0;

        $('.row-select-checkbox').each(function () {
            var $cb = $(this);
            var id = parseInt($cb.val());
            var status = $cb.data('status');

            var canKonfirmasi = userPerms.can_confirm && (status === 'Diserahkan' || status === 'Belum Kembali');
            var canSetTgl = (status === 'Tunggu Tanggal Penyerahan');
            var canSerahkanKembali = userPerms.can_confirm && (status === 'Sudah Kembali');
            var canDelete = userPerms.can_delete && (status !== 'Approved' || userPerms.is_admin);

            // Baris bisa dipilih jika user punya hak hapus ATAU hak aksi alur
            var isEligible = canDelete || canKonfirmasi || canSetTgl || canSerahkanKembali;

            if (isEligible) {
                $cb.prop('disabled', false).removeAttr('title');
                totalEnabled++;
            } else {
                $cb.prop('disabled', true).attr('title', 'Tidak memiliki hak akses untuk data ini');
            }

            var isChecked = selectedBatchIds.has(id);
            $cb.prop('checked', isChecked);

            if (isChecked) {
                checkedCount++;
                if (status === 'Diserahkan' || status === 'Belum Kembali') {
                    countKonfirmasi++;
                } else if (status === 'Tunggu Tanggal Penyerahan') {
                    countTunggu++;
                } else if (status === 'Sudah Kembali') {
                    countSudahKembali++;
                } else if (status === 'Sudah TTD') {
                    countSudahTTD++;
                } else if (status === 'Approved') {
                    countApproved++;
                }
            }
        });

        // Update indeterminate / checked pada checkAllRows
        if (totalEnabled > 0 && checkedCount === totalEnabled) {
            $('#checkAllRows').prop('checked', true).prop('indeterminate', false);
        } else if (checkedCount > 0) {
            $('#checkAllRows').prop('checked', false).prop('indeterminate', true);
        } else {
            $('#checkAllRows').prop('checked', false).prop('indeterminate', false);
        }

        // Tentukan mode alur dari item-item yang terpilih
        if (checkedCount === 0) {
            batchMode = null;
        } else if (countKonfirmasi === checkedCount && userPerms.can_confirm) {
            batchMode = 'konfirmasi';
        } else if (countTunggu === checkedCount) {
            batchMode = 'set_tanggal';
        } else if (countSudahKembali === checkedCount && userPerms.can_confirm) {
            batchMode = 'serahkan_kembali';
        } else {
            batchMode = 'mixed';
        }

        updateBatchFloatingBar();
    }

    function clearBatchSelection() {
        selectedBatchIds.clear();
        batchMode = null;
        $('.row-select-checkbox').prop('checked', false);
        $('#checkAllRows').prop('checked', false).prop('indeterminate', false);
        $('#btnTopBatchDelete').hide();
        syncBatchCheckboxes();
    }

    // ─── LOAD DATA TABLE ──────────────────────────────────────────────────
    function loadData() {
        if (loadDataXhr && loadDataXhr.readyState !== 4) {
            loadDataXhr.abort();
        }

        var params = {
            action: 'load_data',
            date_from: $('#filterDateFrom').val(),
            date_to: $('#filterDateTo').val(),
            filter_status: $('#filterStatus').val()
        };

        loadDataXhr = $.getJSON(BASE_URL, params, function (res) {
            if (res.status !== 'ok') {
                Swal.fire('Error', res.message || 'Gagal memuat data.', 'error');
                return;
            }

            userPerms = res.user || userPerms;

            // Destroy existing DataTable using DataTables API
            if ($.fn.DataTable.isDataTable('#trackingPoTable')) {
                $('#trackingPoTable').DataTable().destroy();
                dataTable = null;
            }

            var html = '';
            if (res.data && res.data.length > 0) {
                $.each(res.data, function (i, row) {
                    var aksiHtml = '<div class="action-btn-group">';

                    // View Detail PO - selalu tampil
                    aksiHtml += '<button class="btn btn-info btn-view-detail" data-ponmbr="' + row.ponmbr + '" title="Lihat Detail Barang PO"><i class="fas fa-eye"></i></button>';

                    // Input Tanggal Penyerahan - khusus status Tunggu Tanggal Penyerahan
                    if (row.status === 'Tunggu Tanggal Penyerahan') {
                        aksiHtml += '<button class="btn btn-warning btn-set-tgl-penyerahan" data-id="' + row.detail_id + '" data-ponmbr="' + row.ponmbr + '" title="Input Tanggal Penyerahan"><i class="fas fa-calendar-plus"></i></button>';
                    }

                    // Serahkan Kembali - khusus status Sudah Kembali (Purchasing/Admin)
                    if (userPerms.can_confirm && row.status === 'Sudah Kembali') {
                        aksiHtml += '<button class="btn btn-primary btn-serahkan-kembali" data-id="' + row.detail_id + '" data-ponmbr="' + row.ponmbr + '" title="Serahkan Kembali ke Direktur"><i class="fas fa-redo"></i></button>';
                    }

                    // Konfirmasi Kembali - Purchasing/Admin, status Diserahkan/Belum Kembali
                    if (userPerms.can_confirm && (row.status === 'Diserahkan' || row.status === 'Belum Kembali')) {
                        aksiHtml += '<button class="btn btn-warning btn-konfirmasi" data-id="' + row.detail_id + '" data-ponmbr="' + row.ponmbr + '" data-vendor="' + (row.povendorname || '-') + '" title="Konfirmasi Dokumen Kembali"><i class="fas fa-undo"></i></button>';
                    }

                    // Log History - selalu tampil
                    aksiHtml += '<button class="btn btn-dark btn-log" data-id="' + row.detail_id + '" data-ponmbr="' + row.ponmbr + '" title="Lihat Log History"><i class="fas fa-history"></i></button>';

                    // Hapus Tracking PO - Purchasing / Admin (status bukan Approved, atau Admin)
                    if (userPerms.can_delete && (row.status !== 'Approved' || userPerms.is_admin)) {
                        aksiHtml += '<button class="btn btn-danger btn-delete-po" data-id="' + row.detail_id + '" data-ponmbr="' + row.ponmbr + '" title="Hapus Data Tracking"><i class="fas fa-trash-alt"></i></button>';
                    }

                    aksiHtml += '</div>';

                    // Checkbox kolom
                    var canKonfirmasi = userPerms.can_confirm && (row.status === 'Diserahkan' || row.status === 'Belum Kembali');
                    var canSetTgl = (row.status === 'Tunggu Tanggal Penyerahan');
                    var canSerahkanKembali = userPerms.can_confirm && (row.status === 'Sudah Kembali');
                    var canDelete = userPerms.can_delete && (row.status !== 'Approved' || userPerms.is_admin);

                    var isEligible = canDelete || canKonfirmasi || canSetTgl || canSerahkanKembali;
                    var checkTitle = isEligible ? '' : 'Tidak memiliki hak akses untuk aksi massal pada baris ini';

                    var isChecked = selectedBatchIds.has(parseInt(row.detail_id));
                    var checkAttr = '';
                    if (!isEligible) {
                        checkAttr = 'disabled title="' + checkTitle + '"';
                    } else if (isChecked) {
                        checkAttr = 'checked';
                    }

                    var checkCol = '<td class="text-center align-middle">' +
                        '<input type="checkbox" class="row-select-checkbox" value="' + row.detail_id + '" ' + checkAttr + ' data-ponmbr="' + row.ponmbr + '" data-status="' + row.status + '" data-approved-erp="' + (row.is_approved_erp ? '1' : '0') + '">' +
                        '</td>';

                    var dikirimBadge = '';
                    if (row.dikirim_ke) {
                        var titleAttr = row.alamat_kirim ? (' title="' + $('<div>').text(row.alamat_kirim).html() + '"') : '';
                        dikirimBadge = '<div class="mt-1">' +
                            '<span class="badge badge-light border text-primary" style="font-size: 11px; font-weight: 600;"' + titleAttr + '>' +
                                '<i class="fas fa-truck-moving text-info mr-1"></i>' + row.dikirim_ke +
                            '</span>' +
                        '</div>';
                    }

                    html += '<tr>' +
                        checkCol +
                        '<td class="text-center">' + row.no + '</td>' +
                        '<td class="text-center">' + row.tanggal_kirim + '</td>' +
                        '<td><span class="po-badge-code">' + row.ponmbr + '</span></td>' +
                        '<td class="font-weight-bold" style="font-size:12px;">' + row.povendorname + dikirimBadge + '</td>' +
                        '<td class="text-center">' + row.podate + '</td>' +
                        '<td style="font-size:12px;">' + row.diserahkan_oleh + '</td>' +
                        '<td class="text-center">' + getStatusBadge(row.status, row) + '</td>' +
                        '<td class="text-center">' + (row.tanggal_kembali || '-') + '</td>' +
                        '<td class="text-center">' + aksiHtml + '</td>' +
                    '</tr>';
                });
            }

            // Set tbody content
            $('#trackingPoBody').html(html);

            // Init DataTable dengan destroy: true
            dataTable = $('#trackingPoTable').DataTable({
                destroy: true,
                order: [],
                responsive: true,
                pageLength: 25,
                columnDefs: [
                    { targets: [0, -1], orderable: false, searchable: false }
                ],
                drawCallback: function () {
                    syncBatchCheckboxes();
                },
                language: {
                    processing: "Memproses...",
                    lengthMenu: "Tampilkan _MENU_ data per halaman",
                    zeroRecords: "Tidak ada data tracking PO ditemukan",
                    emptyTable: "Tidak ada data tracking PO tersedia",
                    info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
                    infoEmpty: "Menampilkan 0 data",
                    infoFiltered: "(disaring dari _MAX_ total data)",
                    search: "Cari:",
                    paginate: { first: "Pertama", last: "Terakhir", next: "›", previous: "‹" }
                }
            });

            syncBatchCheckboxes();
        }).fail(function (jqXHR, textStatus) {
            if (textStatus === 'abort') return;
            Swal.fire('Error', 'Gagal terhubung ke server.', 'error');
        });
    }

    // ─── BATCH SELECTION & ACTION HANDLERS ─────────────────────────────────
    $(document).on('change', '#checkAllRows', function () {
        var isChecked = $(this).prop('checked');
        if (!isChecked) {
            clearBatchSelection();
            return;
        }

        $('.row-select-checkbox:not(:disabled)').each(function () {
            var id = parseInt($(this).val());
            selectedBatchIds.add(id);
            $(this).prop('checked', true);
        });

        syncBatchCheckboxes();
    });

    $(document).on('change', '.row-select-checkbox', function () {
        var id = parseInt($(this).val());

        if ($(this).prop('checked')) {
            selectedBatchIds.add(id);
        } else {
            selectedBatchIds.delete(id);
        }
        syncBatchCheckboxes();
    });

    $(document).on('click', '#btnCancelBatchSelection', function () {
        clearBatchSelection();
    });

    function submitBatchKonfirmasi(isSigned) {
        if (selectedBatchIds.size === 0) return;

        var $btnTtd = $('#btnBatchSudahTTD');
        var $btnBelum = $('#btnBatchBelumTTD');
        var $btnCancel = $('#btnCancelBatchSelection');

        $btnTtd.prop('disabled', true);
        $btnBelum.prop('disabled', true);
        $btnCancel.prop('disabled', true);

        if (isSigned === 1) {
            $btnTtd.html('<i class="fas fa-spinner fa-spin mr-1"></i> Memproses...');
        } else {
            $btnBelum.html('<i class="fas fa-spinner fa-spin mr-1"></i> Memproses...');
        }

        $.post(BASE_URL, {
            action: 'batch_konfirmasi_kembali',
            detail_ids: Array.from(selectedBatchIds),
            is_signed: isSigned
        }, function (res) {
            if (res.status === 'ok') {
                clearBatchSelection();
                loadData();
                loadSummary();
            } else {
                Swal.fire('Gagal', res.message || 'Terjadi kesalahan.', 'error');
            }
        }, 'json').fail(function () {
            Swal.fire('Error', 'Gagal terhubung ke server.', 'error');
        }).always(function () {
            $btnTtd.prop('disabled', false).html('<i class="fas fa-check-circle mr-1"></i> Semua Sudah TTD');
            $btnBelum.prop('disabled', false).html('<i class="fas fa-times-circle mr-1"></i> Semua Belum TTD');
            $btnCancel.prop('disabled', false);
        });
    }

    $(document).on('click', '#btnBatchSudahTTD', function () {
        submitBatchKonfirmasi(1);
    });

    $(document).on('click', '#btnBatchBelumTTD', function () {
        submitBatchKonfirmasi(0);
    });

    function submitBatchSerahkanKembali() {
        if (selectedBatchIds.size === 0) return;
        var ids = Array.from(selectedBatchIds);
        var count = ids.length;
        var today = new Date().toISOString().split('T')[0];

        var poList = [];
        $('.row-select-checkbox:checked').each(function () {
            var p = $(this).data('ponmbr');
            if (p) poList.push(p);
        });

        var previewText = poList.slice(0, 6).join(', ') + (poList.length > 6 ? ' ...dan ' + (poList.length - 6) + ' lainnya' : '');

        Swal.fire({
            title: 'Serahkan Kembali Massal',
            html: '<div class="text-left mb-2">' +
                  '  <p class="mb-1">Total: <b>' + count + ' PO</b> (' + previewText + ')</p>' +
                  '  <p class="small text-muted mb-3">Seluruh PO terpilih akan diubah statusnya menjadi <b>Diserahkan</b> kembali ke Direktur.</p>' +
                  '  <label class="small font-weight-bold text-secondary mb-1">Tanggal Penyerahan Kembali:</label>' +
                  '  <input type="date" id="swalBatchTanggalKembali" class="form-control form-control-sm font-weight-bold mb-2" value="' + today + '">' +
                  '  <label class="small font-weight-bold text-secondary mb-1">Catatan Tambahan (Opsional):</label>' +
                  '  <textarea id="swalBatchCatatanKembali" class="form-control form-control-sm" rows="2" placeholder="Catatan untuk seluruh PO terpilih..."></textarea>' +
                  '</div>',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-redo mr-1"></i> Simpan untuk ' + count + ' PO',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#64748b',
            preConfirm: function () {
                var val = $('#swalBatchTanggalKembali').val();
                if (!val) {
                    Swal.showValidationMessage('Tanggal penyerahan kembali wajib diisi.');
                    return false;
                }
                return {
                    tanggal: val,
                    catatan: $('#swalBatchCatatanKembali').val()
                };
            }
        }).then(function (result) {
            if (!result.isConfirmed || !result.value) return;

            var $btn = $('#btnBatchSerahkanKembali');
            var $btnCancel = $('#btnCancelBatchSelection');
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Memproses...');
            $btnCancel.prop('disabled', true);

            $.post(BASE_URL, {
                action: 'batch_serahkan_kembali',
                detail_ids: ids,
                tanggal_penyerahan: result.value.tanggal,
                catatan: result.value.catatan
            }, function (res) {
                if (res.status === 'ok') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: res.message || (count + ' PO berhasil diserahkan kembali.'),
                        timer: 2000,
                        showConfirmButton: false
                    });
                    clearBatchSelection();
                    loadData();
                    loadSummary();
                } else {
                    Swal.fire('Gagal', res.message || 'Terjadi kesalahan.', 'error');
                }
            }, 'json').fail(function () {
                Swal.fire('Error', 'Gagal terhubung ke server.', 'error');
            }).always(function () {
                $btn.prop('disabled', false).html('<i class="fas fa-redo mr-1"></i> Serahkan Kembali Terpilih');
                $btnCancel.prop('disabled', false);
            });
        });
    }

    $(document).on('click', '#btnBatchSerahkanKembali', function () {
        submitBatchSerahkanKembali();
    });

    function submitBatchSetTanggal() {
        if (selectedBatchIds.size === 0) return;
        var ids = Array.from(selectedBatchIds);
        var count = ids.length;
        var today = new Date().toISOString().split('T')[0];

        var poList = [];
        $('.row-select-checkbox:checked').each(function () {
            var p = $(this).data('ponmbr');
            if (p) poList.push(p);
        });

        var previewText = poList.slice(0, 6).join(', ') + (poList.length > 6 ? ' ...dan ' + (poList.length - 6) + ' lainnya' : '');

        Swal.fire({
            title: 'Input Tanggal Penyerahan Massal',
            html: '<div class="text-left mb-2">' +
                  '  <p class="mb-1">Total: <b>' + count + ' PO</b> (' + previewText + ')</p>' +
                  '  <p class="small text-muted mb-3">Status seluruh PO terpilih akan otomatis berubah menjadi <b>Diserahkan</b> setelah tanggal disimpan.</p>' +
                  '  <label class="small font-weight-bold text-secondary mb-1">Tanggal Penyerahan Fisik:</label>' +
                  '  <input type="date" id="swalBatchTanggalPenyerahan" class="form-control form-control-sm font-weight-bold" value="' + today + '">' +
                  '</div>',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check mr-1"></i> Simpan untuk ' + count + ' PO',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#ea580c',
            cancelButtonColor: '#64748b',
            preConfirm: function () {
                var val = $('#swalBatchTanggalPenyerahan').val();
                if (!val) {
                    Swal.showValidationMessage('Tanggal penyerahan wajib diisi.');
                    return false;
                }
                return val;
            }
        }).then(function (result) {
            if (!result.isConfirmed || !result.value) return;

            var $btn = $('#btnBatchSetTanggal');
            var $btnCancel = $('#btnCancelBatchSelection');
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Memproses...');
            $btnCancel.prop('disabled', true);

            $.post(BASE_URL, {
                action: 'set_tanggal_penyerahan',
                detail_ids: JSON.stringify(ids),
                tanggal_penyerahan: result.value
            }, function (res) {
                if (res.status === 'ok') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: res.message || (count + ' PO berhasil diupdate tanggal penyerahannya.'),
                        timer: 2000,
                        showConfirmButton: false
                    });
                    clearBatchSelection();
                    loadData();
                    loadSummary();
                } else {
                    Swal.fire('Gagal', res.message || 'Terjadi kesalahan.', 'error');
                }
            }, 'json').fail(function () {
                Swal.fire('Error', 'Gagal terhubung ke server.', 'error');
            }).always(function () {
                $btn.prop('disabled', false).html('<i class="fas fa-calendar-plus mr-1"></i> Input Tgl Penyerahan Terpilih');
                $btnCancel.prop('disabled', false);
            });
        });
    }

    $(document).on('click', '#btnBatchSetTanggal', function () {
        submitBatchSetTanggal();
    });

    function submitBatchDelete() {
        var count = selectedBatchIds.size;
        if (count === 0) return;

        var poList = [];
        $('.row-select-checkbox:checked').each(function () {
            var ponmbr = $(this).data('ponmbr');
            var status = $(this).data('status');
            if (ponmbr) {
                poList.push({ ponmbr: ponmbr, status: status });
            }
        });

        var poListHtml = '<div class="text-left my-2 p-2 rounded" style="background:#f8fafc; border:1px solid #e2e8f0; max-height:160px; overflow-y:auto;">' +
                         '<ul class="list-unstyled mb-0 small">';
        $.each(poList, function (i, item) {
            poListHtml += '<li class="py-1 border-bottom d-flex justify-content-between align-items-center">' +
                          '<span><b>' + item.ponmbr + '</b></span>' +
                          '<span class="badge badge-light border">' + item.status + '</span>' +
                          '</li>';
        });
        poListHtml += '</ul></div>';

        Swal.fire({
            title: 'Hapus ' + count + ' Tracking PO?',
            html: '<p class="mb-2">Apakah Anda yakin ingin menghapus data tracking untuk <b>' + count + ' PO</b> terpilih?</p>' +
                  poListHtml +
                  '<div class="alert alert-warning text-left py-2 px-3 small mt-2 mb-0">' +
                  '  <i class="fas fa-exclamation-triangle mr-1"></i> PO yang dihapus akan dikeluarkan dari tracking dan dapat diserahkan kembali jika diperlukan.' +
                  '</div>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash-alt mr-1"></i> Ya, Hapus Semua (' + count + ' PO)',
            cancelButtonText: 'Batal'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            var $btnTop = $('#btnTopBatchDelete');
            var $btnFloat = $('#btnFloatingBatchDelete');
            var $btnCancel = $('#btnCancelBatchSelection');

            $btnTop.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menghapus...');
            $btnFloat.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menghapus...');
            $btnCancel.prop('disabled', true);

            $.post(BASE_URL, {
                action: 'batch_delete_tracking',
                detail_ids: Array.from(selectedBatchIds)
            }, function (res) {
                if (res.status === 'ok') {
                    Swal.fire('Terhapus!', res.message || (count + ' data tracking PO berhasil dihapus.'), 'success');
                    clearBatchSelection();
                    loadData();
                    loadSummary();
                } else {
                    Swal.fire('Gagal', res.message || 'Gagal menghapus data tracking.', 'error');
                }
            }, 'json').fail(function () {
                Swal.fire('Error', 'Gagal menghubungi server.', 'error');
            }).always(function () {
                $btnTop.prop('disabled', false).html('<i class="fas fa-trash-alt mr-1"></i> Hapus Terpilih (<span class="batch-delete-count">' + selectedBatchIds.size + '</span>)');
                $btnFloat.prop('disabled', false).html('<i class="fas fa-trash-alt mr-1"></i> Hapus Terpilih (<span class="batch-delete-count">' + selectedBatchIds.size + '</span>)');
                $btnCancel.prop('disabled', false);
            });
        });
    }

    $(document).on('click', '#btnTopBatchDelete, #btnFloatingBatchDelete', function () {
        submitBatchDelete();
    });

    // ─── INITIAL LOAD ─────────────────────────────────────────────────────
    loadSummary();
    loadData();

    // ─── FILTER EVENTS ────────────────────────────────────────────────────
    $('#filterDateFrom, #filterDateTo').on('change', function () {
        clearBatchSelection();
        loadData();
        loadSummary();
    });
    $('#filterStatus').on('change', function () {
        clearBatchSelection();
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
        clearBatchSelection();
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
        clearBatchSelection();
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

    // Tombol kosongkan tanggal kirim
    $('#btnClearTanggalKirim').on('click', function () {
        $('#inputTanggalKirim').val('');
    });

    // ─── LOGIKA PILIHAN DIKIRIM KE ────────────────────────────────────────
    var defaultAddresses = {
        'PT.SUM': 'Jl. Desa Tarajusari, No.8, Tarajusari, Banjaran, Kab.Bandung',
        'Sanso Berjaya Texindo/Toko Sumber SERAGAM': 'Jl. Waas No.5B, Batununggal, Sebelah JNT, UP Ibu Verty, Telp : 022 87313631',
        'Diambil ke Supplier': ''
    };

    function updateDikirimKeUI() {
        var selectedOpt = $('input[name="radioDikirimKe"]:checked').val();
        $('.option-card').removeClass('active-option');
        $('input[name="radioDikirimKe"]:checked').closest('.option-card').addClass('active-option');

        var selectedList = Object.values(selectedPoMap);
        var count = selectedList.length;

        if (selectedOpt === 'PT.SUM') {
            $('#inputAlamatKirim').val(defaultAddresses['PT.SUM']);
            $('#supplierMultiPoPreview').hide();
            $('#alamatKirimHint').text('(Dapat disesuaikan jika perlu catatan khusus)');
        } else if (selectedOpt === 'Sanso Berjaya Texindo/Toko Sumber SERAGAM') {
            $('#inputAlamatKirim').val(defaultAddresses['Sanso Berjaya Texindo/Toko Sumber SERAGAM']);
            $('#supplierMultiPoPreview').hide();
            $('#alamatKirimHint').text('(Dapat disesuaikan jika perlu catatan khusus)');
        } else if (selectedOpt === 'Diambil ke Supplier') {
            if (count === 1) {
                var singlePo = selectedList[0];
                var addr = singlePo.vendoraddr ? singlePo.vendoraddr : 'Alamat vendor belum tercatat di ERP';
                $('#inputAlamatKirim').val(addr);
                $('#supplierMultiPoPreview').hide();
                $('#alamatKirimHint').text('(Alamat dari master supplier ERP)');
            } else {
                $('#inputAlamatKirim').val('Diambil langsung ke masing-masing supplier (Alamat otomatis disimpan per vendor PO dari database ERP)');
                var listHtml = '';
                $.each(selectedList, function (idx, item) {
                    var vAddr = item.vendoraddr ? item.vendoraddr : '<i class="text-danger">Alamat belum tercatat di ERP</i>';
                    listHtml += '<div class="py-1 border-bottom">' +
                        '<strong>' + item.ponmbr + '</strong> <span class="badge badge-light border text-dark ml-1">' + (item.povendorname || '-') + '</span>: ' +
                        '<span class="text-muted">' + vAddr + '</span>' +
                    '</div>';
                });
                $('#supplierMultiPoList').html(listHtml);
                $('#supplierMultiPoPreview').slideDown(150);
                $('#alamatKirimHint').text('(Daftar master vendor masing-masing PO tercantum di bawah)');
            }
        }
    }

    $(document).on('click', '.option-card', function (e) {
        if ($(e.target).is('input[type="radio"]')) return;
        var $radio = $(this).find('input[type="radio"]');
        if (!$radio.prop('checked')) {
            $radio.prop('checked', true).trigger('change');
        }
    });

    $(document).on('change', 'input[name="radioDikirimKe"]', function () {
        updateDikirimKeUI();
    });

    // Buka Modal Pilih Dikirim Ke saat tombol Submit di Modal Search PO diklik
    $('#btnSubmitPoSearch').on('click', function () {
        var selectedKeys = Object.keys(selectedPoMap);

        if (selectedKeys.length === 0) {
            Swal.fire('Perhatian', 'Pilih minimal 1 PO untuk diserahkan.', 'warning');
            return;
        }

        $('#badgeCountPilihDikirimKe').text(selectedKeys.length + ' PO Terpilih');
        $('#modalSerahkanPo').modal('hide');

        // Pastikan opsi 1 (PT.SUM) terpilih default jika belum ada yang terpilih
        if (!$('input[name="radioDikirimKe"]:checked').length) {
            $('#optDikirimKe1').prop('checked', true);
        }
        updateDikirimKeUI();

        $('#modalPilihDikirimKe').modal('show');
    });

    // Batal / Kembali dari Modal Pilih Dikirim Ke ke Modal Search PO
    $('#btnBatalDikirimKe, #btnClosePilihDikirimKe').on('click', function () {
        $('#modalPilihDikirimKe').modal('hide');
        $('#modalSerahkanPo').modal('show');
    });

    // Konfirmasi Eksekusi Penyerahan PO dari Modal Pilih Dikirim Ke
    $('#btnConfirmDikirimKe').on('click', function () {
        var tanggalKirim = $.trim($('#inputTanggalKirim').val());
        var dikirimKe = $('input[name="radioDikirimKe"]:checked').val() || 'PT.SUM';
        var alamatKirim = $.trim($('#inputAlamatKirim').val());
        var selectedKeys = Object.keys(selectedPoMap);

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
                vendoraddr: r.vendoraddr || '',
                podate_raw: r.podate_raw || null
            });
        });

        var confirmTitle = 'Konfirmasi Penyerahan';
        var confirmHtml = '';
        var confirmBtnText = '';
        var confirmColor = '#28a745';
        var iconType = 'question';

        var badgeDikirimHtml = '<br><span class="badge badge-info px-2 py-1 mt-2 font-weight-bold" style="font-size: 0.85rem;"><i class="fas fa-truck mr-1"></i>Dikirim ke: ' + dikirimKe + '</span>';

        if (!tanggalKirim) {
            confirmTitle = 'Tunggu Tanggal Penyerahan';
            confirmHtml = 'Tanggal penyerahan <b>dikosongkan</b>.<br><br>Simpan <b>' + poItems.length + ' PO</b> dengan status <b><span class="badge badge-warning text-dark font-weight-bold px-2 py-1">Tunggu Tanggal Penyerahan</span></b>?' + badgeDikirimHtml + '<br><small class="text-muted mt-2 d-block">Tanggal penyerahan dapat diinputkan menyusul nanti.</small>';
            confirmBtnText = '<i class="fas fa-save mr-1"></i> Ya, Simpan';
            confirmColor = '#ea580c';
            iconType = 'info';
        } else {
            confirmTitle = 'Konfirmasi Penyerahan';
            confirmHtml = 'Serahkan <b>' + poItems.length + ' PO</b> ke Direktur pada tanggal <b>' + tanggalKirim + '</b>?' + badgeDikirimHtml;
            confirmBtnText = '<i class="fas fa-paper-plane mr-1"></i> Ya, Serahkan!';
            confirmColor = '#28a745';
            iconType = 'question';
        }

        Swal.fire({
            title: confirmTitle,
            html: confirmHtml,
            icon: iconType,
            showCancelButton: true,
            confirmButtonText: confirmBtnText,
            cancelButtonText: 'Batal',
            confirmButtonColor: confirmColor
        }).then(function (result) {
            if (!result.isConfirmed) return;

            var $btn = $('#btnConfirmDikirimKe');
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Memproses...');

            $.post(BASE_URL, {
                action: 'submit_serah',
                tanggal_kirim: tanggalKirim,
                dikirim_ke: dikirimKe,
                alamat_kirim: alamatKirim,
                po_items: JSON.stringify(poItems),
                csrf_token: trackingPoCsrfToken,
                activity_request_id: trackingPoActivityRequestId
            }, function (res) {
                $btn.prop('disabled', false).html('<i class="fas fa-check-circle mr-1"></i> Lanjutkan & Serahkan PO');
                if (res.status === 'ok') {
                    $('#modalPilihDikirimKe').modal('hide');
                    $('#modalSerahkanPo').modal('hide');
                    Swal.fire('Berhasil!', res.message || 'PO berhasil diproses.', 'success');
                    selectedPoMap = {};
                    trackingPoActivityRequestId = trackingPoNewRequestId();
                    loadSummary();
                    loadData();
                } else {
                    Swal.fire('Gagal', res.message || 'Gagal menyerahkan PO.', 'error');
                }
            }, 'json').fail(function () {
                $btn.prop('disabled', false).html('<i class="fas fa-check-circle mr-1"></i> Lanjutkan & Serahkan PO');
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

        var $btn = $('#btnSubmitKonfirmasi');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Memproses...');

        $.post(BASE_URL, {
            action: 'konfirmasi_kembali',
            detail_id: detailId,
            is_signed: isSigned,
            catatan: catatan
        }, function (res) {
            if (res.status === 'ok') {
                $('#modalKonfirmasiKembali').modal('hide');
                loadData();
                loadSummary();
            } else {
                Swal.fire('Gagal', res.message || 'Terjadi kesalahan.', 'error');
            }
        }, 'json').fail(function () {
            Swal.fire('Error', 'Gagal terhubung ke server.', 'error');
        }).always(function () {
            $btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i> Konfirmasi');
        });
    });

    // ─── INPUT TANGGAL PENYERAHAN (BARIS TUNGGAL) ─────────────────────────
    $(document).on('click', '.btn-set-tgl-penyerahan', function () {
        var detailId = $(this).data('id');
        var ponmbr = $(this).data('ponmbr');
        var today = new Date().toISOString().split('T')[0];

        Swal.fire({
            title: 'Input Tanggal Penyerahan',
            html: '<div class="text-left mb-2">' +
                  '  <p class="mb-1">No PO: <b>' + ponmbr + '</b></p>' +
                  '  <p class="small text-muted mb-3">Setelah tanggal disimpan, status PO akan otomatis berubah menjadi <b>Diserahkan</b>.</p>' +
                  '  <label class="small font-weight-bold text-secondary mb-1">Tanggal Penyerahan Fisik:</label>' +
                  '  <input type="date" id="swalRowTanggalPenyerahan" class="form-control form-control-sm font-weight-bold" value="' + today + '">' +
                  '</div>',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check mr-1"></i> Simpan Tanggal',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#ea580c',
            cancelButtonColor: '#64748b',
            preConfirm: function () {
                var val = $('#swalRowTanggalPenyerahan').val();
                if (!val) {
                    Swal.showValidationMessage('Tanggal penyerahan wajib diisi.');
                    return false;
                }
                return val;
            }
        }).then(function (result) {
            if (!result.isConfirmed || !result.value) return;

            $.post(BASE_URL, {
                action: 'set_tanggal_penyerahan',
                detail_id: detailId,
                tanggal_penyerahan: result.value
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
        });
    });

    // ─── SERAHKAN KEMBALI PO (BARIS TUNGGAL) ─────────────────────────────
    $(document).on('click', '.btn-serahkan-kembali', function () {
        var detailId = $(this).data('id');
        var ponmbr = $(this).data('ponmbr');
        var today = new Date().toISOString().split('T')[0];

        Swal.fire({
            title: 'Serahkan Kembali PO',
            html: '<div class="text-left mb-2">' +
                  '  <p class="mb-1">No PO: <b>' + ponmbr + '</b></p>' +
                  '  <p class="small text-muted mb-3">Status PO akan otomatis berubah kembali menjadi <b>Diserahkan</b> ke Direktur untuk ditandatangani.</p>' +
                  '  <label class="small font-weight-bold text-secondary mb-1">Tanggal Penyerahan Kembali:</label>' +
                  '  <input type="date" id="swalRowTanggalKembali" class="form-control form-control-sm font-weight-bold mb-2" value="' + today + '">' +
                  '  <label class="small font-weight-bold text-secondary mb-1">Catatan Tambahan (Opsional):</label>' +
                  '  <textarea id="swalRowCatatanKembali" class="form-control form-control-sm" rows="2" placeholder="Catatan penyerahan kembali..."></textarea>' +
                  '</div>',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-redo mr-1"></i> Serahkan Kembali',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#64748b',
            preConfirm: function () {
                var val = $('#swalRowTanggalKembali').val();
                if (!val) {
                    Swal.showValidationMessage('Tanggal penyerahan kembali wajib diisi.');
                    return false;
                }
                return {
                    tanggal: val,
                    catatan: $('#swalRowCatatanKembali').val()
                };
            }
        }).then(function (result) {
            if (!result.isConfirmed || !result.value) return;

            $.post(BASE_URL, {
                action: 'serahkan_kembali',
                detail_id: detailId,
                tanggal_penyerahan: result.value.tanggal,
                catatan: result.value.catatan
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

            if (h.dikirim_ke) {
                $('#modalPoDikirimKe').text(h.dikirim_ke);
                $('#modalPoAlamatKirim').text(h.alamat_kirim || '-');
                $('#modalPoShippingBox').show();
            } else {
                $('#modalPoShippingBox').hide();
            }

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
