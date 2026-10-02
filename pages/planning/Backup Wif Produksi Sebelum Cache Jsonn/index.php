<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../koneksi3.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';

date_default_timezone_set('Asia/Jakarta');

// Permission Check (gunakan MenuId yang sesuai, default skip jika 0)
$groupId = $_SESSION['GroupId'] ?? 0;
$menuId = 0; // Set MenuId yang sesuai setelah didaftarkan di menu manager
if ($menuId > 0 && $conn) {
    $sql = "SELECT TOP 1 CanView FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, array($groupId, $menuId));
    $permissions = ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) ? $row : [];
    sqlsrv_free_stmt($stmt);
    if (isset($permissions['CanView']) && $permissions['CanView'] == 0) {
        die("Anda tidak memiliki hak untuk melihat halaman ini.");
    }
}

// Fetch Grup Akses untuk Filter
$availableGroups = [];
$currUser = $_SESSION['UserName'] ?? '';
if ($groupId == 1) {
    // Admin: semua grup
    $sqlGrp = "SELECT DISTINCT group_name FROM planning_group_rtg ORDER BY group_name";
    $stmtGrp = sqlsrv_query($conn, $sqlGrp);
} else {
    // Operator: hanya grup yang dia punya
    $sqlGrp = "SELECT DISTINCT r.group_name FROM planning_user_group u INNER JOIN planning_group_rtg r ON u.group_name = r.group_name WHERE u.username = ? ORDER BY r.group_name";
    $stmtGrp = sqlsrv_query($conn, $sqlGrp, [$currUser]);
}
if ($stmtGrp) {
    while ($gRow = sqlsrv_fetch_array($stmtGrp, SQLSRV_FETCH_ASSOC)) {
        $availableGroups[] = $gRow['group_name'];
    }
}
?>

<!-- Fonts -->
<link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@500;600;700;800&display=swap"
    rel="stylesheet">

<style>
    /* =========================================
   1. GLOBAL & TYPOGRAPHY
========================================= */
    body,
    .content-wrapper {
        font-family: 'Inter', sans-serif !important;
        background-color: #f8fafc;
    }

    h1,
    h2,
    h3,
    h4,
    h5,
    h6,
    .outfit-font {
        font-family: 'Outfit', sans-serif !important;
    }

    /* =========================================
   2. MODERN STAT CARDS (Normal Mode)
========================================= */
    .modern-stat-card {
        border-radius: 16px;
        padding: 24px 20px;
        color: white;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border: 1px solid rgba(255, 255, 255, 0.2);
        margin-bottom: 24px;
    }

    .modern-stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    .modern-stat-card i {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 5rem;
        opacity: 0.15;
        transition: transform 0.3s ease;
    }

    .modern-stat-card:hover i {
        transform: translateY(-50%) scale(1.1);
    }

    .modern-stat-card h3 {
        font-size: 2.8rem;
        font-weight: 700;
        margin: 0 0 5px 0;
        line-height: 1;
    }

    .modern-stat-card p {
        font-size: 1.05rem;
        font-weight: 500;
        margin: 0;
        opacity: 0.9;
    }

    .modern-stat-card small {
        display: block;
        opacity: 0.75;
        font-size: 0.8rem;
        margin-top: 3px;
    }

    /* Gradients */
    .bg-grad-primary {
        background: linear-gradient(135deg, #0ea5e9 0%, #3b82f6 100%);
    }

    .bg-grad-warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }

    .bg-grad-info {
        background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%);
    }

    .bg-grad-danger {
        background: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%);
    }

    /* =========================================
   3. PILL FILTERS & INPUTS
========================================= */
    .filter-pill {
        display: inline-block;
        padding: 8px 20px;
        border-radius: 50px;
        background: #e2e8f0;
        color: #475569;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        border: 2px solid transparent;
        margin-right: 8px;
        margin-bottom: 8px;
        font-size: 0.9rem;
    }

    .filter-pill:hover {
        background: #cbd5e1;
    }

    .filter-pill.active {
        background: #3b82f6;
        color: white;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    /* =========================================
   4. SLEEK DATATABLES
========================================= */
    .card-table-wrap {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        border: 1px solid #f1f5f9;
        overflow: hidden;
    }

    table.dataTable.table-sm>thead>tr>th {
        padding: 16px 12px !important;
        background: #f8fafc;
        color: #475569;
        font-weight: 600;
        border-bottom: 2px solid #e2e8f0;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        border-top: none;
        border-left: none;
        border-right: none;
    }

    table.dataTable.table-sm>tbody>tr>td {
        padding: 14px 12px;
        border-bottom: 1px solid #f1f5f9;
        border-top: none;
        border-left: none;
        border-right: none;
        vertical-align: middle;
        color: #1e293b;
        font-size: 0.95rem;
    }

    table.dataTable>tbody>tr:hover {
        background-color: #f8fafc !important;
    }

    /* Badges inside table */
    .badge-pill {
        padding: 6px 14px;
        font-weight: 600;
        border-radius: 50px;
        font-size: 0.8rem;
    }

    .badge-lab {
        background-color: #fef3c7;
        color: #d97706;
    }

    .badge-la {
        background-color: #e0f2fe;
        color: #0284c7;
    }

    .badge-mix {
        background-color: #ffe4e6;
        color: #e11d48;
    }

    /* Responsive Child Row Styling (Hidden Columns) */
    table.dataTable>tbody>tr.child {
        background-color: #f8fafc !important;
    }

    table.dataTable>tbody>tr.child ul.dtr-details {
        display: block !important;
        width: 100% !important;
        padding: 10px 20px !important;
        margin: 0 !important;
        list-style-type: none !important;
    }

    table.dataTable>tbody>tr.child ul.dtr-details>li {
        display: flex !important;
        align-items: flex-start !important;
        padding: 12px 0 !important;
        border-bottom: 1px dashed #cbd5e1 !important;
        text-align: left !important;
    }

    table.dataTable>tbody>tr.child ul.dtr-details>li:last-child {
        border-bottom: none !important;
    }

    table.dataTable>tbody>tr.child span.dtr-title {
        font-weight: 600 !important;
        color: #475569 !important;
        min-width: 180px !important;
        max-width: 180px !important;
        display: inline-block !important;
        margin-right: 15px !important;
        text-align: left !important;
    }

    table.dataTable>tbody>tr.child span.dtr-data {
        color: #0f172a !important;
        font-weight: 500 !important;
        flex: 1 !important;
        word-break: break-word !important;
        text-align: left !important;
        display: block !important;
    }

    /* =========================================
   5. TV MODE (Cyberpunk / Deep Dark)
========================================= */
    /* Fullscreen Mode Styles */
    .fullscreen-mode {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        margin: 0 !important;
        padding: 20px !important;
        background-color: #0b1120 !important;
        /* Deep Slate */
        z-index: 9999 !important;
        overflow-y: auto !important;
        box-sizing: border-box !important;
    }

    /* Hide elements in fullscreen */
    body.in-fullscreen .main-header,
    body.in-fullscreen .main-sidebar,
    body.in-fullscreen .main-footer,
    body.in-fullscreen .breadcrumb,
    body.in-fullscreen .filter-section,
    body.in-fullscreen .dataTables_length,
    body.in-fullscreen .dataTables_filter,
    body.in-fullscreen .dataTables_info,
    body.in-fullscreen .dataTables_paginate {
        display: none !important;
    }

    body.in-fullscreen .content-wrapper {
        margin-left: 0 !important;
        margin-right: 0 !important;
        width: 100vw !important;
    }

    /* Override AdminLTE sidebar margin even when collapsed (Fix Gap Kiri) */
    body.sidebar-collapse.in-fullscreen .content-wrapper,
    body.sidebar-mini.in-fullscreen .content-wrapper {
        margin-left: 0 !important;
    }

    /* SILENT LOADING IN FULLSCREEN */
    body.in-fullscreen #loadingOverlay {
        display: none !important;
    }

    /* TV Card Styles */
    body.tv-mode .modern-stat-card {
        border: 1px solid #1e293b;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    }

    body.tv-mode .card-table-wrap {
        background: transparent;
        border: none;
        box-shadow: none;
    }

    body.tv-mode .tv-header-title {
        color: #f8fafc !important;
        font-size: 3rem;
        font-weight: 800;
        margin-bottom: 2rem;
        text-shadow: 0 4px 15px rgba(255, 255, 255, 0.1);
    }

    /* TV Table Styles */
    body.tv-mode table.dataTable.table-sm>thead>tr>th {
        background: transparent;
        color: #94a3b8;
        border-bottom: 2px solid #1e293b;
        font-size: 1rem;
        padding: 20px 10px !important;
    }

    body.tv-mode table.dataTable.table-sm>tbody>tr>td {
        border-bottom: 1px solid #1e293b;
        color: #e2e8f0;
        font-size: 1.4rem;
        /* HUGE for TV */
        padding: 22px 10px;
        font-family: 'Outfit', sans-serif !important;
    }

    body.tv-mode table.dataTable>tbody>tr:hover {
        background-color: #1caf9a10 !important;
    }

    /* TV Badges (Neon Effect) */
    body.tv-mode .badge-pill {
        font-size: 1.2rem;
        padding: 10px 20px;
        border: 1px solid transparent;
    }

    body.tv-mode .badge-lab {
        background-color: #f59e0b20;
        color: #fbbf24;
        border-color: #fbbf2450;
    }

    body.tv-mode .badge-la {
        background-color: #0ea5e920;
        color: #38bdf8;
        border-color: #38bdf850;
    }

    body.tv-mode .badge-mix {
        background-color: #f43f5e20;
        color: #fb7185;
        border-color: #fb718550;
    }

    /* TV Responsive Child Row */
    body.tv-mode table.dataTable>tbody>tr.child {
        background-color: #1e293b !important;
    }

    body.tv-mode table.dataTable>tbody>tr.child ul.dtr-details>li {
        border-bottom: 1px dashed #475569;
        padding: 14px 0;
    }

    body.tv-mode table.dataTable>tbody>tr.child span.dtr-title {
        color: #94a3b8;
        min-width: 220px;
        font-size: 1.2rem;
    }

    body.tv-mode table.dataTable>tbody>tr.child span.dtr-data {
        color: #f8fafc;
        font-size: 1.2rem;
    }

    /* Fullscreen Display Helpers */
    body.in-fullscreen .d-fullscreen-block {
        display: block !important;
    }

    body.in-fullscreen .d-fullscreen-inline-block {
        display: inline-block !important;
    }

    body:not(.in-fullscreen) .d-fullscreen-block,
    body:not(.in-fullscreen) .d-fullscreen-inline-block {
        display: none !important;
    }

    body.in-fullscreen .modern-stat-card i {
        display: none !important;
    }

    /* Floating Buttons Group */
    .tv-floating-group {
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 10000;
        display: flex;
        flex-direction: column;
        gap: 15px;
        transition: opacity 0.4s ease, transform 0.4s ease;
    }

    /* In Fullscreen: hidden by default, show on mousemove */
    body.in-fullscreen .tv-floating-group {
        opacity: 0;
        transform: translateX(30px);
        pointer-events: none;
    }

    body.in-fullscreen .tv-floating-group.visible,
    body.in-fullscreen .tv-floating-group:hover {
        opacity: 1;
        transform: translateX(0);
        pointer-events: auto;
    }

    .btn-float {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        border: none;
        color: white;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
    }

    .btn-float:hover {
        transform: scale(1.1);
        box-shadow: 0 12px 25px rgba(0, 0, 0, 0.4);
    }

    .btn-float-refresh { background: #10b981; display: none !important; }
    body.in-fullscreen .btn-float-refresh { display: flex !important; }
    
    .btn-float-settings { background: #64748b; }
    .btn-float-tv { background: #3b82f6; }

    .btn-float i {
        font-size: 22px;
    }


    /* Settings Panel */
    #tvSettingsPanel {
        position: fixed;
        top: 0;
        right: -340px;
        width: 320px;
        height: 100vh;
        background: #1e293b;
        color: #f1f5f9;
        z-index: 10001;
        box-shadow: -10px 0 30px rgba(0, 0, 0, 0.4);
        transition: right 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    #tvSettingsPanel.open {
        right: 0;
    }

    .tv-settings-header {
        background: #0f172a;
        padding: 20px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #334155;
        flex-shrink: 0;
    }

    .tv-settings-header h6 {
        margin: 0;
        font-weight: 700;
        font-size: 1rem;
        color: #f8fafc;
        letter-spacing: 0.5px;
    }

    #btnCloseTvSettings {
        background: none;
        border: none;
        color: #94a3b8;
        font-size: 1.2rem;
        cursor: pointer;
        padding: 4px 8px;
        transition: color 0.2s;
    }

    #btnCloseTvSettings:hover {
        color: #f1f5f9;
    }

    .tv-settings-body {
        padding: 20px 24px;
        overflow-y: auto;
        flex: 1;
    }

    .tv-settings-body p.hint {
        color: #64748b;
        font-size: 0.82rem;
        margin-bottom: 18px;
    }

    .col-toggle-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #334155;
    }

    .col-toggle-item:last-child {
        border-bottom: none;
    }

    .col-toggle-label {
        font-size: 0.9rem;
        font-weight: 500;
        color: #cbd5e1;
    }

    /* Toggle Switch */
    .tv-switch {
        position: relative;
        display: inline-block;
        width: 42px;
        height: 24px;
    }

    .tv-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .tv-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: #334155;
        border-radius: 24px;
        transition: 0.3s;
    }

    .tv-slider:before {
        content: '';
        position: absolute;
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background: #64748b;
        border-radius: 50%;
        transition: 0.3s;
    }

    .tv-switch input:checked+.tv-slider {
        background: #3b82f6;
    }

    .tv-switch input:checked+.tv-slider:before {
        transform: translateX(18px);
        background: white;
    }

    .tv-settings-footer {
        padding: 16px 24px;
        background: #0f172a;
        border-top: 1px solid #334155;
        flex-shrink: 0;
    }

    .tv-settings-footer button {
        width: 100%;
        padding: 10px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        cursor: pointer;
        font-size: 0.9rem;
    }

    #btnResetTvCols {
        background: #334155;
        color: #94a3b8;
        margin-top: 10px;
    }

    .tv-setting-group {
        margin-bottom: 25px;
    }

    .tv-setting-title {
        font-size: 0.8rem;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 12px;
        display: block;
        border-bottom: 1px solid #334155;
        padding-bottom: 6px;
    }

    .tv-select-custom {
        width: 100%;
        background: #0f172a;
        border: 1px solid #334155;
        color: #f1f5f9;
        border-radius: 8px;
        padding: 8px 12px;
        font-size: 0.9rem;
        outline: none;
        transition: border-color 0.2s;
    }

    .tv-select-custom:focus {
        border-color: #3b82f6;
    }

    #btnResetTvCols:hover {
        background: #475569;
        color: #f1f5f9;
    }

    #tvSettingsOverlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.4);
        z-index: 10000;
    }

    #tvSettingsOverlay.open {
        display: block;
    }

    /* =========================================
   7. LOADING OVERLAY (MODAL STYLE)
========================================= */
    #loadingOverlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.4);
        /* Slate-900 with opacity */
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        display: none;
        /* Hidden by default */
        align-items: center;
        justify-content: center;
        z-index: 10000;
    }

    #loadingOverlay.active {
        display: flex !important;
    }

    .loading-box {
        background: white;
        padding: 30px 50px;
        border-radius: 20px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        min-width: 200px;
    }

    .loading-box .spinner-border {
        width: 3rem;
        height: 3rem;
        border-width: 0.25em;
        color: #3b82f6;
        /* Blue-500 */
        margin-bottom: 15px;
    }

    .loading-box p {
        margin: 0;
        font-weight: 600;
        color: #1e293b;
        font-size: 1.1rem;
        font-family: 'Outfit', sans-serif;
    }

    /* Sembunyikan label "Memuat data..." bawaan DataTables */
    div.dataTables_processing {
        display: none !important;
    }
</style>

<div class="wrapper">
    <div class="content-wrapper">

        <!-- HEADER NORMAL MODE -->
        <div class="content-header filter-section">
            <div class="container-fluid">
                <div class="row align-items-center mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0 outfit-font" style="font-weight: 700; color: #1e293b;">
                            Dashboard <span style="color: #3b82f6;">Wip Produksi Dyeing</span>
                        </h1>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">

                <!-- TITLE TV MODE (HIDDEN DI NORMAL) -->
                <div class="row mx-0 d-none d-fullscreen-block" style="display: none;">
                    <div class="col-12 text-center text-md-left">
                        <h1 class="tv-header-title">WIP PRODUKSI DYEING</h1>
                    </div>
                </div>

                <!-- 🌟 SUPER STAT CARDS 🌟 -->
                <div class="row" id="summaryCards">
                    <div class="col-12">
                        <div class="modern-stat-card bg-grad-primary">
                            <i class="fas fa-layer-group"></i>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 id="card-total" class="outfit-font">...</h3>
                                    <p id="label-total-cp">Total CP Aktif</p>
                                </div>
                                <div class="text-right d-fullscreen-block" style="display:none;">
                                    <div id="tv-group-label"
                                        style="font-size: 1.5rem; font-weight: 700; background: rgba(255,255,255,0.2); padding: 5px 15px; border-radius: 10px; margin-bottom: 5px;">
                                        -</div>
                                    <div id="tv-update-time" style="font-size: 1.1rem; font-weight: 500;"><i
                                            class="far fa-clock mr-1"></i> --:--:--</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 🌟 MAIN TABLE WRAPPER 🌟 -->
                <div class="card-table-wrap p-4 mb-5">

                    <!-- FILTER SECTION -->
                    <div class="filter-section mb-4 d-flex flex-wrap align-items-end justify-content-between">
                        <div class="d-flex flex-wrap align-items-end">

                            <?php if (count($availableGroups) >= 1): ?>
                                <div class="mr-3 d-inline-block align-top">
                                    <label class="small font-weight-bold text-secondary d-block mb-2"><i
                                            class="fas fa-layer-group mr-1"></i> Grup Akses:</label>
                                    <select id="filterGrup" class="form-control form-control-sm"
                                        style="width: 180px; border-radius: 8px;">
                                        <option value="">-- Semua Grup --</option>
                                        <?php foreach ($availableGroups as $g): ?>
                                            <option value="<?= htmlspecialchars($g) ?>"><?= htmlspecialchars($g) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php else: ?>
                                <input type="hidden" id="filterGrup"
                                    value="<?= htmlspecialchars($availableGroups[0] ?? '') ?>">
                            <?php endif; ?>

                            <div class="mr-3 d-inline-block align-top">
                                <label class="small font-weight-bold text-secondary d-block mb-2">Next Routing:</label>
                                <select id="filterRouting" class="form-control form-control-sm"
                                    style="width: 200px; border-radius: 8px;">
                                    <option value="">-- Semua Routing --</option>
                                </select>
                            </div>

                            <div class="d-inline-block align-top">
                                <label class="small font-weight-bold text-secondary d-block mb-2">Kategori :</label>
                                <div id="pillContainer">
                                    <div class="filter-pill active" data-val="">Semua</div>
                                    <div class="filter-pill" data-val="LAB"><i class="fas fa-flask mr-1"></i> LAB</div>
                                    <div class="filter-pill" data-val="LA"><i class="fas fa-warehouse mr-1"></i> LA
                                    </div>
                                    <div class="filter-pill" data-val="MIX"><i class="fas fa-sync-alt mr-1"></i> MIX
                                    </div>
                                </div>
                                <input type="hidden" id="filterKategori" value="">
                            </div>
                        </div>

                        <div class="mt-3 mt-md-0 d-flex align-items-end">
                            <div class="mr-2">
                                <label class="small font-weight-bold text-secondary d-block mb-2"><i
                                        class="fas fa-clock mr-1"></i> Auto Refresh:</label>
                                <select id="autoRefreshSelect" class="form-control form-control-sm"
                                    style="width: 140px; border-radius: 8px;">
                                    <option value="0">Nonaktif</option>
                                    <option value="15000">15 Detik</option>
                                    <option value="30000" selected>30 Detik</option>
                                    <option value="60000">1 Menit</option>
                                    <option value="300000">5 Menit</option>
                                    <option value="1800000">30 Menit</option>
                                    <option value="3600000">60 Menit</option>
                                </select>
                            </div>
                            <div>
                                <span class="text-muted small font-weight-bold d-block mb-2" id="last-updated">Update:
                                    --:--:--</span>
                                <button id="btnRefresh" class="btn btn-sm btn-light border"
                                    style="border-radius: 8px; font-weight: 600;">
                                    <i class="fas fa-sync-alt text-primary"></i> Refresh
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- DATATABLE -->
                    <div class="table-responsive">
                        <table id="planningTable" class="table table-sm table-hover w-100">
                            <thead>
                                <tr>
                                    <th width="40px">No.</th>
                                    <th>No.CP</th>
                                    <th>Tgl CP</th>
                                    <th>Label</th>
                                    <th>Cust Color</th>
                                    <th>Kode Lab</th>
                                    <th>Color Name</th>
                                    <th>Material</th>
                                    <th>Qty</th>
                                    <th>Current Routing</th>
                                    <th>Next Routing</th>
                                    <th>Vlot</th>
                                    <th>Kategori Timbang</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Buttons Group -->
<div class="tv-floating-group">
    <button id="btnFloatingRefresh" class="btn-float btn-float-refresh" title="Refresh Data Manual (Hotkey: R)">
        <i class="fas fa-sync-alt"></i>
    </button>
    <button id="btnTvSettings" class="btn-float btn-float-settings" title="Pengaturan Kolom Fullscreen Mode">
        <i class="fas fa-cog"></i>
    </button>
    <button id="btnToggleFullscreen" class="btn-float btn-float-tv" title="Toggle TV Mode (Fullscreen)">
        <i class="fas fa-tv"></i>
    </button>
</div>

<!-- TV Settings Overlay (backdrop) -->
<div id="tvSettingsOverlay"></div>

<!-- TV Settings Panel -->
<div id="tvSettingsPanel">
    <div class="tv-settings-header">
        <h6><i class="fas fa-tv mr-2"></i> Konfigurasi TV Mode</h6>
        <button id="btnCloseTvSettings"><i class="fas fa-times"></i></button>
    </div>
    <div class="tv-settings-body">
        
        <!-- Automation Settings -->
        <div class="tv-setting-group">
            <span class="tv-setting-title">Otomasi Halaman</span>
            <div class="form-group mb-3">
                <label class="small text-muted mb-1">Mode Otomasi</label>
                <select id="tvAutomationMode" class="tv-select-custom">
                    <option value="none">Matikan Otomasi</option>
                    <option value="paginate">Ganti Halaman (Lompat)</option>
                    <option value="scroll" selected>Gulir Halus (Smooth)</option>
                </select>
            </div>
            <div class="form-group">
                <label id="tvSpeedLabel" class="small text-muted mb-1">Kecepatan Gulir</label>
                <select id="tvAutomationSpeed" class="tv-select-custom mb-2">
                    <!-- Options populated by JS depending on mode -->
                </select>
                <!-- Custom Speed Input (Hidden by default) -->
                <input type="number" id="tvAutomationSpeedCustom" class="tv-select-custom" 
                       placeholder="Masukkan nilai..." style="display: none;">
                <small id="tvSpeedHint" class="text-muted mt-1" style="display: none; font-size: 0.75rem;"></small>
            </div>
        </div>

        <!-- Column Visibility -->
        <div class="tv-setting-group">
            <span class="tv-setting-title">Visibilitas Kolom</span>
            <p class="hint">Pilih kolom yang ingin ditampilkan saat mode fullscreen aktif.</p>
            <div id="tvColToggles"></div>
        </div>
    </div>
    <div class="tv-settings-footer">
        <button id="btnResetTvCols"><i class="fas fa-undo mr-2"></i>Reset Semua Pengaturan</button>
    </div>
</div>

<!-- 🌟 MODERN LOADING OVERLAY 🌟 -->
<div id="loadingOverlay">
    <div class="loading-box">
        <div class="spinner-border" role="status"></div>
        <p>Memuat data...</p>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet"
    href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">

<!-- DataTables JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>

<script>
    $(document).ready(function () {

        // ── 1. Load Summary Cards ──────────────────────────────────────────────
        function loadSummary() {
            $.post('/gg_app/pages/planning/get_summary_data.php', {
                filter_grup: $('#filterGrup').val()
            }, function (res) {
                if (res.error) return;
                $('#card-total').text(res.total);

                // Update info TV Mode
                var grupName = $('#filterGrup option:selected').text();
                if (!grupName || grupName.includes('--')) grupName = 'Semua Grup';
                $('#tv-group-label').text(grupName);

                // Populate routing dropdown (only first load, avoid reset)
                if ($('#filterRouting option').length <= 1 && res.routings) {
                    $.each(res.routings, function (i, r) {
                        $('#filterRouting').append($('<option>', { value: r, text: r }));
                    });
                }
            }, 'json');
        }
        loadSummary();

        // ── 2. DataTable ───────────────────────────────────────────────────────
        var loadingStartTime = 0;
        var minLoadingTime = 300; // ms (agar operator bisa melihat status memuat)

        // Bind events ke elemen SEBELUM inisialisasi agar load pertama tertangkap
        $('#planningTable').on('preXhr.dt', function () {
            if (isFullscreen) return; // Mode TV: Update senyap (silent)
            loadingStartTime = Date.now();
            $('#loadingOverlay').addClass('active');
        });

        $('#planningTable').on('draw.dt', function () {
            if (isFullscreen) {
                $('#loadingOverlay').removeClass('active');
                return;
            }
            var currentTime = Date.now();
            var elapsed = currentTime - loadingStartTime;
            if (elapsed < minLoadingTime) {
                setTimeout(function () {
                    $('#loadingOverlay').removeClass('active');
                }, minLoadingTime - elapsed);
            } else {
                $('#loadingOverlay').removeClass('active');
            }
        });

        var table = $('#planningTable').DataTable({
            processing: true, // Enable processing for events
            serverSide: true,
            responsive: true,
            autoWidth: false,
            ajax: {
                url: '/gg_app/pages/planning/get_planning_data.php',
                type: 'POST',
                data: function (d) {
                    d.filter_routing = $('#filterRouting').val();
                    d.filter_kategori = $('#filterKategori').val();
                    d.filter_grup = $('#filterGrup').val();
                }
            },
            columns: [
                { data: 0, className: 'text-center', width: '40px', orderable: false },
                { data: 1, className: 'text-center font-weight-bold', width: '110px' },
                { data: 2, className: 'text-center', width: '85px' },
                { data: 3 },
                { data: 4, className: 'text-center' },
                { data: 5, className: 'text-center' },
                { data: 6 },
                { data: 7 },
                { data: 8, className: 'text-right', width: '70px' },
                { data: 9 },
                { data: 10 },
                { data: 11, className: 'text-center', width: '60px' },
                { data: 12, className: 'text-center', width: '90px', orderable: false },
            ],
            order: [[1, 'asc']],
            pageLength: 25,
            language: {
                processing: '<i class="fas fa-spinner fa-spin"></i> Memuat data...',
                lengthMenu: 'Tampilkan _MENU_ data',
                zeroRecords: 'Tidak ada data ditemukan',
                info: 'Menampilkan _START_ &ndash; _END_ dari _TOTAL_ data',
                infoEmpty: 'Tidak ada data tersedia',
                infoFiltered: '(disaring dari _MAX_ total)',
                search: 'Cari:',
                paginate: {
                    first: '&laquo;',
                    last: '&raquo;',
                    next: '&rsaquo;',
                    previous: '&lsaquo;'
                }
            },
            drawCallback: function () {
                var now = new Date();
                var hh = String(now.getHours()).padStart(2, '0');
                var mm = String(now.getMinutes()).padStart(2, '0');
                var ss = String(now.getSeconds()).padStart(2, '0');
                var timeStr = hh + ':' + mm + ':' + ss;
                $('#last-updated').text('Update: ' + timeStr);
                $('#tv-update-time').html('<i class="far fa-clock mr-1"></i> ' + timeStr);
            }
        });

        // ── 3. Filter Logic (Instan) ──────────────────────────────────────────
        $('#filterRouting, #filterGrup').on('change', function () {
            table.ajax.reload();
            loadSummary(); // Sinkronisasi Total CP Aktif
        });



        $('.filter-pill').on('click', function () {
            $('.filter-pill').removeClass('active');
            $(this).addClass('active');
            $('#filterKategori').val($(this).data('val'));

            table.ajax.reload();
        });

        // ── 4. Refresh Logic ───────────────────────────────────────────
        function manualRefresh() {
            var $btnNormal = $('#btnRefresh');
            var $btnFloat = $('#btnFloatingRefresh');
            var $icons = $btnNormal.find('i').add($btnFloat.find('i'));

            $icons.addClass('fa-spin');
            table.ajax.reload(function () {
                $icons.removeClass('fa-spin');
            }, false);
            loadSummary();
        }

        $('#btnRefresh, #btnFloatingRefresh').on('click', function () {
            manualRefresh();
        });

        // Hotkey R untuk refresh
        $(document).on('keydown', function (e) {
            // Jangan trigger jika sedang ngetik di input
            if (e.key.toLowerCase() === 'r' && !$(e.target).is('input, textarea, select')) {
                manualRefresh();
            }
        });

        // ── 5. Auto-refresh & Pagination Otomatis ──────────────────────────────
        var isFullscreen = false;
        var tvModeInterval = null;
        var staticRefreshInterval = null;

        // Fungsi mulai/hentikan auto-refresh statis
        function applyStaticRefresh(ms) {
            if (staticRefreshInterval) { clearInterval(staticRefreshInterval); staticRefreshInterval = null; }
            if (!ms || ms <= 0) return;
            staticRefreshInterval = setInterval(function () {
                if (!isFullscreen) {
                    table.ajax.reload(null, false);
                    loadSummary();
                }
            }, ms);
        }

        // Inisialisasi dengan nilai dari localStorage (jika ada), jika tidak gunakan default select
        var savedRefresh = localStorage.getItem('wip_auto_refresh');
        if (savedRefresh !== null) {
            $('#autoRefreshSelect').val(savedRefresh);
        }
        applyStaticRefresh(parseInt($('#autoRefreshSelect').val()) || 0);

        // Ubah durasi saat user memilih & simpan ke localStorage
        $('#autoRefreshSelect').on('change', function () {
            var ms = $(this).val();
            localStorage.setItem('wip_auto_refresh', ms);
            applyStaticRefresh(parseInt(ms) || 0);
        });

        // ── 5. TV Mode Automation (Smooth Scroll & Pagination) ───────────────────────
        var tvModeInterval = null;
        var tvScrollRequest = null;
        
        var TV_CONFIG = {
            mode: localStorage.getItem('wip_tv_mode') || 'scroll', // paginate, scroll, none
            speed: parseInt(localStorage.getItem('wip_tv_speed')) || 2, // pixels per frame for scroll, or ms for paginate
        };

        function startTvAutomation() {
            stopTvAutomation();
            if (!isFullscreen) return;

            if (TV_CONFIG.mode === 'paginate') {
                runTvPagination();
            } else if (TV_CONFIG.mode === 'scroll') {
                runTvSmoothScroll();
            }
        }

        function stopTvAutomation() {
            if (tvModeInterval) { clearInterval(tvModeInterval); tvModeInterval = null; }
            if (tvScrollRequest) { cancelAnimationFrame(tvScrollRequest); tvScrollRequest = null; }
        }

        // Logic 1: Auto Pagination (Jump)
        function runTvPagination() {
            var ms = TV_CONFIG.speed || 30000;
            tvModeInterval = setInterval(function() {
                var info = table.page.info();
                if (info.page < info.pages - 1) {
                    table.page('next').draw('page');
                } else {
                    table.page('first').draw('page');
                    table.ajax.reload(null, false);
                    loadSummary();
                }
                $(window).scrollTop(0); // Ensure back to top
            }, ms);
        }

        // Logic 2: Smooth Scroll
        function runTvSmoothScroll() {
            var $wrapper = $('.fullscreen-mode');
            var speed = TV_CONFIG.speed || 1; 
            var pauseAtBottom = 3000; // 3 seconds at bottom before next page
            var isPaused = false;

            function scroll() {
                if (!isFullscreen || TV_CONFIG.mode !== 'scroll') return;
                
                if (!isPaused) {
                    var currScroll = $wrapper.scrollTop();
                    var maxScroll = $wrapper[0].scrollHeight - $wrapper[0].clientHeight;

                    if (currScroll < maxScroll - 1) {
                        $wrapper.scrollTop(currScroll + speed);
                    } else {
                        // Bottom reached
                        isPaused = true;
                        setTimeout(function() {
                            var info = table.page.info();
                            if (info.page < info.pages - 1) {
                                table.page('next').draw('page');
                            } else {
                                table.page('first').draw('page');
                                table.ajax.reload(null, false);
                                loadSummary();
                            }
                            $wrapper.scrollTop(0);
                            isPaused = false;
                            tvScrollRequest = requestAnimationFrame(scroll);
                        }, pauseAtBottom);
                        return; // Stop animation loop until timeout finishes
                    }
                }
                tvScrollRequest = requestAnimationFrame(scroll);
            }
            tvScrollRequest = requestAnimationFrame(scroll);
        }

        // UI Logic for Automation Settings
        function updateTvAutomationUI() {
            $('#tvAutomationMode').val(TV_CONFIG.mode);
            populateTvSpeedOptions();
            $('#tvAutomationSpeed').val(TV_CONFIG.speed);
        }

        function populateTvSpeedOptions() {
            var $sel = $('#tvAutomationSpeed');
            var mode = $('#tvAutomationMode').val();
            $sel.empty();

            if (mode === 'scroll') {
                $('#tvSpeedLabel').text('Kecepatan Gulir');
                $sel.append('<option value="1">Sangat Lambat (1px)</option>');
                $sel.append('<option value="2">Lambat (2px)</option>');
                $sel.append('<option value="4">Normal (4px)</option>');
                $sel.append('<option value="8">Cepat (8px)</option>');
                $sel.append('<option value="custom">Custom...</option>');
            } else if (mode === 'paginate') {
                $('#tvSpeedLabel').text('Durasi Per Halaman');
                $sel.append('<option value="5000">5 Detik</option>');
                $sel.append('<option value="10000">10 Detik</option>');
                $sel.append('<option value="30000">30 Detik</option>');
                $sel.append('<option value="60000">1 Menit</option>');
                $sel.append('<option value="custom">Custom...</option>');
            } else {
                $('#tvSpeedLabel').text('Kecepatan');
                $sel.append('<option value="0">-</option>');
            }
        }

        function handleTvSpeedUI() {
            var mode = $('#tvAutomationMode').val();
            var val = $('#tvAutomationSpeed').val();
            var $customInput = $('#tvAutomationSpeedCustom');
            var $hint = $('#tvSpeedHint');

            if (val === 'custom') {
                $customInput.show();
                $hint.show();
                if (mode === 'scroll') {
                    $hint.text('Satuan: pixel per frame (Contoh: 3)');
                    if (!$customInput.val()) $customInput.val(3);
                } else {
                    $hint.text('Satuan: milidetik (Contoh: 15000 untuk 15s)');
                    if (!$customInput.val()) $customInput.val(15000);
                }
            } else {
                $customInput.hide();
                $hint.hide();
            }
        }

        // Logic to update config and start
        function syncTvConfig() {
            var mode = $('#tvAutomationMode').val();
            var speedVal = $('#tvAutomationSpeed').val();
            var finalSpeed = speedVal;

            if (speedVal === 'custom') {
                finalSpeed = parseInt($('#tvAutomationSpeedCustom').val()) || 0;
            } else {
                finalSpeed = parseInt(speedVal);
            }

            TV_CONFIG.mode = mode;
            TV_CONFIG.speed = finalSpeed;
            localStorage.setItem('wip_tv_mode', mode);
            localStorage.setItem('wip_tv_speed', finalSpeed);

            if (isFullscreen) startTvAutomation();
        }

        $('#tvAutomationMode').on('change', function() {
            populateTvSpeedOptions();
            syncTvConfig();
            handleTvSpeedUI();
        });

        $('#tvAutomationSpeed').on('change', function() {
            handleTvSpeedUI();
            syncTvConfig();
        });

        $('#tvAutomationSpeedCustom').on('input', function() {
            syncTvConfig();
        });

        // Initialize UI with initial values
        (function initAutomationUI() {
            populateTvSpeedOptions();
            $('#tvAutomationMode').val(TV_CONFIG.mode);
            
            // Check if current speed matches any preset
            var found = false;
            $('#tvAutomationSpeed option').each(function() {
                if (parseInt($(this).val()) === TV_CONFIG.speed) {
                    $('#tvAutomationSpeed').val($(this).val());
                    found = true;
                }
            });

            if (!found && TV_CONFIG.mode !== 'none') {
                $('#tvAutomationSpeed').val('custom');
                $('#tvAutomationSpeedCustom').val(TV_CONFIG.speed);
            }

            handleTvSpeedUI();
        })();


        // ── 6. TV Column Settings ─────────────────────────────────────────────
        var TV_COL_STORAGE = 'wip_tv_cols';

        // [index, label, default visible]
        var tvColumns = [
            { idx: 0, label: 'No.', def: true },
            { idx: 1, label: 'No.CP', def: true },
            { idx: 2, label: 'Tgl CP', def: false },
            { idx: 3, label: 'Label', def: true },
            { idx: 4, label: 'Cust Color', def: true },
            { idx: 5, label: 'Kode Lab', def: true },
            { idx: 6, label: 'Color Name', def: true },
            { idx: 7, label: 'Material', def: true },
            { idx: 8, label: 'Qty', def: true },
            { idx: 9, label: 'Current Routing', def: true },
            { idx: 10, label: 'Next Routing', def: true },
            { idx: 11, label: 'Vlot', def: false },
            { idx: 12, label: 'Kategori Timbang', def: true },
        ];

        function loadTvColPrefs() {
            var saved = localStorage.getItem(TV_COL_STORAGE);
            if (!saved) return null;
            try { return JSON.parse(saved); } catch (e) { return null; }
        }

        function saveTvColPrefs(prefs) {
            localStorage.setItem(TV_COL_STORAGE, JSON.stringify(prefs));
        }

        function getEffectivePrefs() {
            var saved = loadTvColPrefs();
            var prefs = {};
            tvColumns.forEach(function (c) {
                prefs[c.idx] = (saved && saved[c.idx] !== undefined) ? saved[c.idx] : c.def;
            });
            return prefs;
        }

        // Build toggle list
        function buildTvColToggles() {
            var prefs = getEffectivePrefs();
            var $container = $('#tvColToggles').empty();
            tvColumns.forEach(function (c) {
                var checked = prefs[c.idx] ? 'checked' : '';
                var $item = $('\
                <div class="col-toggle-item">\
                    <span class="col-toggle-label">' + c.label + '</span>\
                    <label class="tv-switch">\
                        <input type="checkbox" data-col="' + c.idx + '" ' + checked + '>\
                        <span class="tv-slider"></span>\
                    </label>\
                </div>');
                $container.append($item);
            });

            // Save on change
            $container.on('change', 'input[type=checkbox]', function () {
                var prefs = getEffectivePrefs();
                var idx = parseInt($(this).data('col'));
                prefs[idx] = $(this).is(':checked');
                saveTvColPrefs(prefs);
                // Apply live if fullscreen active
                if (isFullscreen) applyTvColVisibility();
            });
        }

        function applyTvColVisibility() {
            var prefs = getEffectivePrefs();
            tvColumns.forEach(function (c) {
                table.column(c.idx).visible(isFullscreen ? prefs[c.idx] : true);
            });

            // Ubah header Next Routing (index 9 -> 10) saat fullscreen
            var headerText = isFullscreen
                ? 'Posisi Saat Ini (Belum Ditembak)'
                : 'Next Routing';
            $(table.column(10).header()).text(headerText);

            // Ubah label Total CP Aktif saat fullscreen
            var totalLabel = isFullscreen
                ? 'Total CP Aktif (Belum Ditembak Proint)'
                : 'Total CP Aktif';
            $('#label-total-cp').text(totalLabel);

            table.columns.adjust();
        }

        // Panel open/close
        function openTvSettings() {
            buildTvColToggles();
            $('#tvSettingsPanel').addClass('open');
            $('#tvSettingsOverlay').addClass('open');
        }
        function closeTvSettings() {
            $('#tvSettingsPanel').removeClass('open');
            $('#tvSettingsOverlay').removeClass('open');
        }

        $('#btnTvSettings').on('click', openTvSettings);
        $('#btnCloseTvSettings, #tvSettingsOverlay').on('click', closeTvSettings);

        // Reset to default
        $('#btnResetTvCols').on('click', function () {
            localStorage.removeItem(TV_COL_STORAGE);
            buildTvColToggles();
            if (isFullscreen) applyTvColVisibility();
        });

        // ── 7. Fullscreen Logic ───────────────────────────────────────────────
        $('#btnToggleFullscreen').click(function () {
            var $btn = $(this);
            var $contentWrapper = $('.content-wrapper');

            if (!isFullscreen) {
                // Enter fullscreen (TV Mode)
                $contentWrapper.addClass('fullscreen-mode');
                $('body').addClass('in-fullscreen tv-mode');
                $btn.addClass('fullscreen-active');
                $btn.find('i').removeClass('fa-tv').addClass('fa-compress');
                isFullscreen = true;

                // Apply column visibility for TV
                applyTvColVisibility();

                // API Fullscreen Browser
                if (document.documentElement.requestFullscreen) {
                    document.documentElement.requestFullscreen().catch(err => console.log(err));
                } else if (document.documentElement.webkitRequestFullscreen) {
                    document.documentElement.webkitRequestFullscreen();
                } else if (document.documentElement.msRequestFullscreen) {
                    document.documentElement.msRequestFullscreen();
                }

                // Mulai Otomasi Panel (Smooth Scroll / Paginate)
                startTvAutomation();

            } else {
                // Exit fullscreen (Normal Mode)
                $contentWrapper.removeClass('fullscreen-mode');
                $('body').removeClass('in-fullscreen tv-mode');
                $btn.removeClass('fullscreen-active');
                $btn.find('i').removeClass('fa-compress').addClass('fa-tv');
                isFullscreen = false;

                // Stop Otomasi
                stopTvAutomation();

                // Restore all columns
                applyTvColVisibility();

                if (document.exitFullscreen) {
                    document.exitFullscreen().catch(err => console.log(err));
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }

                // Hentikan Otomasi
                stopTvAutomation();
            }
        });

        // Handle ESC key untuk mematikan mode
        $(document).on('fullscreenchange webkitfullscreenchange msfullscreenchange', function () {
            if (!document.fullscreenElement && !document.webkitFullscreenElement && !document.msFullscreenElement) {
                if (isFullscreen) {
                    $('.content-wrapper').removeClass('fullscreen-mode');
                    $('body').removeClass('in-fullscreen tv-mode');
                    $('#btnToggleFullscreen').removeClass('fullscreen-active')
                        .find('i').removeClass('fa-compress').addClass('fa-tv');
                    isFullscreen = false;
                    stopTvAutomation();
                    applyTvColVisibility();
                }
            }
        });

        // ── 8. TV Mode Floating Buttons Handler ───────────────────────────────
        var tvBtnTimeout;
        $(document).on('mousemove', function () {
            if (!isFullscreen) return;
            $('.tv-floating-group').addClass('visible');
            clearTimeout(tvBtnTimeout);
            tvBtnTimeout = setTimeout(function () {
                // Jangan sembunyikan jika mouse sedang berada di atas group
                if (!$('.tv-floating-group:hover').length) {
                    $('.tv-floating-group').removeClass('visible');
                }
            }, 3000);
        });

    });
</script>