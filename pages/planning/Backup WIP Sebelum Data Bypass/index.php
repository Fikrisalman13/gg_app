<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', '86400');
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/gg_app/',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true
    ]);
    session_start();
}
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

    /* RowGroup Header Styling — Normal Mode (colors controlled by JS) */
    tr.dtrg-group td {
        font-family: 'Outfit', sans-serif !important;
        font-weight: 800 !important;
        font-size: 1rem !important;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        /* No hardcoded colors — set dynamically via JS */
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
        padding: 0 !important; /* Reset total untuk kontrol manual */
        background-color: #0b1120 !important;
        /* Deep Slate */
        z-index: 9999 !important;
        overflow-y: auto !important;
        box-sizing: border-box !important;
        
        /* GPU ACCELERATION FOR SMART TV */
        transform: translateZ(0);
        backface-visibility: hidden;
        perspective: 1000;
        will-change: scroll-position;

        /* HIDE SCROLLBAR */
        -ms-overflow-style: none;
        /* IE and Edge */
        scrollbar-width: none;
        /* Firefox */
    }

    .fullscreen-mode::-webkit-scrollbar {
        display: none;
        /* Chrome, Safari and Opera */
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
    body.sidebar-mini.in-fullscreen .content-wrapper,
    body.in-fullscreen .content-wrapper {
        margin-left: 0 !important;
        padding-left: 0 !important;
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

    /* TV Top Fixed Section - Seamless Integration */
    body.tv-mode.tv-freeze-header .tv-sticky-header-wrapper {
        position: sticky !important;
        top: 0 !important;
        z-index: 500 !important;
        background: #0b1120 !important;
        
        /* Memberikan ruang napas extra agar tidak terpotong saat gulir */
        padding: 25px 30px 30px 30px !important; 
        width: 100% !important;
        box-sizing: border-box !important;
        border-bottom: 3px solid #1e293b;
    }

    /* TV Table Wrapper padding sync */
    body.tv-mode .card-table-wrap {
        padding: 0 30px 30px 30px !important;
        background: transparent;
        border: none;
        box-shadow: none;
    }

    /* TV Table Styles - Default No Sticky unless class applied */
    body.tv-mode.tv-freeze-header table.dataTable.table-sm>thead>tr>th {
        position: sticky !important;
        /* Rumus Koreksi Zoom: Offset / Faktor Zoom */
        top: calc(var(--tv-header-offset, 250px) / var(--tv-zoom, 1)) !important; 
        z-index: 100 !important;
        background: #0b1120 !important; /* Non-transparent deep slate */
        color: #94a3b8;
        border-bottom: 2px solid #1e293b;
        font-size: 1rem;
        padding: 20px 10px !important;
    }

    /* Force overflow visible for sticky to work */
    body.tv-mode.tv-freeze-header .table-responsive,
    body.tv-mode.tv-freeze-header .card-table-wrap {
        overflow: visible !important;
    }

    /* If NOT frozen, still apply colors but no sticky */
    body.tv-mode:not(.tv-freeze-header) table.dataTable.table-sm>thead>tr>th {
        background: #0b1120 !important;
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

    /* Vertical line accent for group belonging — REMOVED STATIC BLUE, NOW SET VIA JS */
    body.tv-mode table.dataTable.table-sm>tbody>tr:not(.dtrg-group)>td:first-child {
        /* border-left color will be set dynamically in createdRow */
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

    /* RowGroup Header Styling — TV Mode (Kembali ke Normal, Tidak Beku) */
    body.tv-mode tr.dtrg-group td {
        position: relative !important;
        z-index: 10 !important;
        
        /* GPU ACCELERATION FOR STICKY LAYERS */
        transform: translateZ(0);
        
        background: linear-gradient(90deg, rgba(30, 41, 59, 0.95), rgba(11, 17, 32, 1)) !important;
        color: #fff !important;
        font-size: 1.8rem !important;
        font-weight: 900 !important;
        font-family: 'Outfit', sans-serif !important;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        padding: 20px 20px !important;
        border-top: 4px solid rgba(255,255,255,0.05) !important;
        border-bottom: 2px solid rgba(255,255,255,0.1) !important;
        /* border-left: controlled dynamically by JS */
        text-shadow: 0 2px 8px rgba(0,0,0,0.5);
    }

    /* Add spacing after group if needed - simulated via extra padding */
    body.tv-mode tr.dtrg-group + tr td {
        padding-top: 30px !important;
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

    .btn-float-refresh {
        background: #10b981;
        display: none !important;
    }

    body.in-fullscreen .btn-float-refresh {
        display: flex !important;
    }

    .btn-float-settings {
        background: #64748b;
    }

    .btn-float-tv {
        background: #3b82f6;
    }

    /* Floating Button Animations */
    .btn-float-refresh.loading {
        background: #059669 !important;
        animation: btn-pulse 1.5s infinite;
    }

    @keyframes btn-pulse {
        0% {
            transform: scale(1);
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
        }

        70% {
            transform: scale(1.1);
            box-shadow: 0 0 0 15px rgba(16, 185, 129, 0);
        }

        100% {
            transform: scale(1);
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
        }
    }

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

    /* REORDER BUTTONS */
    .tv-col-controls {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-reorder {
        background: #0f172a;
        border: 1px solid #334155;
        color: #94a3b8;
        width: 28px;
        height: 28px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 0.8rem;
    }

    .btn-reorder:hover:not(:disabled) {
        border-color: #3b82f6;
        color: #f1f5f9;
        background: #1e293b;
    }

    .btn-reorder:disabled {
        opacity: 0.3;
        cursor: not-allowed;
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

    /* DEBUG MENU */
    .debug-section {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 2px dashed #334155;
    }

    .debug-card {
        background: #0f172a;
        border-radius: 10px;
        padding: 15px;
        font-family: 'Consolas', monospace;
        font-size: 0.75rem;
        color: #10b981;
    }

    .debug-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 5px;
    }

    .debug-label {
        color: #64748b;
    }

    .debug-value {
        font-weight: bold;
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

    /* Status Delay Badges */
    .status-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 6px;
    }
    .dot-delay { background: #f43f5e; box-shadow: 0 0 8px rgba(244, 63, 94, 0.5); }
    .dot-ontime { background: #10b981; }
    .dot-early { background: #3b82f6; }
    .dot-waiting { background: #94a3b8; }

    .pulse-delay {
        animation: pulse-delay-anim 2s infinite;
    }
    @keyframes pulse-delay-anim {
        0% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.4); opacity: 0.5; }
        100% { transform: scale(1); opacity: 1; }
    }

    .badge-status {
        font-size: 0.85rem;
        font-weight: 800;
        padding: 5px 12px;
        border-radius: 8px;
        text-transform: uppercase;
        display: inline-block;
        white-space: nowrap;
    }
    .tv-mode .badge-status {
        font-size: 1.1rem;
        padding: 8px 16px;
        border-radius: 10px;
    }
    .badge-done { background: #d1fae5; color: #065f46; border: 1.5px solid #a7f3d0; }
    .badge-running { background: #fef3c7; color: #92400e; border: 1.5px solid #fde68a; }
    .badge-waiting { background: #f1f5f9; color: #475569; border: 1.5px solid #e2e8f0; }
    .badge-delay { background: #fee2e2; color: #b91c1c; border: 1.5px solid #f87171; }

    .text-time-plan { color: #334155; }
    .tv-mode .text-time-plan { color: #fff; }

    /* Time Cell Font Sizes */
    .time-plan-val { font-size: 1.1rem; font-weight: 800; }
    .time-actual-val { font-size: 0.85rem; font-weight: 800; }
    .time-pill-val { font-size: 0.75rem; font-weight: 800; }

    .tv-mode .time-plan-val { font-size: 1.5rem; }
    .tv-mode .time-actual-val { font-size: 1.25rem; }
    .tv-mode .time-pill-val { font-size: 1rem; }

    /* Sembunyikan label "Memuat data..." bawaan DataTables */
    div.dataTables_processing {
        display: none !important;
    }

    /* LIVE CLOCK - TV MODE */
    .tv-live-clock {
        font-family: 'Outfit', monospace;
        font-weight: 800;
        line-height: 1;
        color: #fff;
        text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    }

    #tv-clock-hm,
    #tv-clock-s {
        font-size: 3.5rem;
        letter-spacing: 2px;
    }

    /* =========================================
       NETWORK STATUS TOAST
    ========================================= */
    #network-status-toast {
        position: fixed;
        bottom: 24px;
        left: 50%;
        transform: translateX(-50%) translateY(20px);
        padding: 10px 18px;
        border-radius: 999px;
        font-size: 0.82rem;
        font-weight: 600;
        font-family: 'Outfit', sans-serif;
        display: flex;
        align-items: center;
        gap: 7px;
        box-shadow: 0 4px 18px rgba(0, 0, 0, 0.18);
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease, transform 0.3s ease;
        z-index: 9999;
        white-space: nowrap;
    }

    #network-status-toast.show {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
        pointer-events: auto;
    }

    #network-status-toast.toast-error {
        background: #1e293b;
        color: #fca5a5;
    }

    #network-status-toast.toast-success {
        background: #1e293b;
        color: #86efac;
    }

    /* FS CHALLENGE OVERLAY (For auto_fs reload) */
    #fs-challenge-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(15, 23, 42, 0.9);
        backdrop-filter: blur(8px);
        z-index: 10000;
        display: none;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: white;
        cursor: pointer;
    }

    #fs-challenge-overlay .icon {
        font-size: 4rem;
        margin-bottom: 20px;
        color: #3b82f6;
        animation: pulse-blue 2s infinite;
    }

    #fs-challenge-overlay h2 {
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        margin-bottom: 10px;
    }

    #fs-challenge-overlay p {
        color: #94a3b8;
    }

    @keyframes pulse-blue {
        0% {
            transform: scale(1);
            opacity: 0.8;
        }

        50% {
            transform: scale(1.1);
            opacity: 1;
        }

        100% {
            transform: scale(1);
            opacity: 0.8;
        }
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

                <div class="tv-sticky-header-wrapper">
                    <!-- TITLE TV MODE (HIDDEN DI NORMAL) -->
                    <div class="row mx-0 d-none d-fullscreen-block" style="display: none;">
                        <div class="col-12 d-flex align-items-center justify-content-between px-2">
                            <h1 class="tv-header-title mb-0">WIP PRODUKSI DYEING</h1>
                            <div class="tv-live-clock">
                                <span id="tv-clock-hm">--:--</span><span id="tv-clock-s">:--</span>
                            </div>
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
                                    <th>Tgl Planning</th>
                                    <th>No.CP</th>
                                    <th>Tgl CP</th>
                                    <th>Waktu Start</th>
                                    <th>Waktu Finish</th>
                                    <th>Status</th>
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
            <div class="form-group mb-3 d-flex align-items-center justify-content-between">
                <label class="small text-muted mb-0">Bekukan Header (Sticky)</label>
                <label class="tv-switch">
                    <input type="checkbox" id="tvFreezeHeader">
                    <span class="tv-slider"></span>
                </label>
            </div>
        </div>

        <!-- Column Visibility -->
        <div class="tv-setting-group">
            <span class="tv-setting-title">Visibilitas Kolom</span>
            <p class="hint">Pilih kolom yang ingin ditampilkan saat mode fullscreen aktif.</p>
            <div id="tvColToggles"></div>
        </div>

        <!-- DEBUG MENU SECTION -->
        <div class="debug-section">
            <span class="tv-setting-title">System Diagnostics</span>
            <div class="debug-card">
                <div class="debug-item">
                    <span class="debug-label">Last Sync:</span>
                    <span class="debug-value" id="debug-sync">-</span>
                </div>
                <div class="debug-item">
                    <span class="debug-label">SQL Processing:</span>
                    <span class="debug-value" id="debug-duration">0s</span>
                </div>
                <div class="debug-item">
                    <span class="debug-label">Data Count:</span>
                    <span class="debug-value" id="debug-rows">0 rows</span>
                </div>
                <div class="debug-item">
                    <span class="debug-label">Cache Age:</span>
                    <span class="debug-value" id="debug-age">0s</span>
                </div>
            </div>
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
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-colreorder/css/colReorder.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-rowgroup/css/rowGroup.bootstrap4.min.css">

<!-- DataTables JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-colreorder/js/dataTables.colReorder.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-rowgroup/js/dataTables.rowGroup.min.js"></script>

<script>
    var globalSettings = {};
    var table = null;
    var lastRenderedCacheToken = '';
    var lastRenderedUpdateText = 'Update: -';
    var lastRenderedTvUpdateHtml = '<i class="far fa-clock mr-1"></i> Update: -';
    var isManualRefreshing = false;
    var lastSettingsTime = 0; // Untuk sinkronisasi setting global
    
    // Cache data scroll untuk Smart TV (Mencegah Layout Thrashing)
    var tvScrollData = {
        $el: null,
        maxScroll: 0,
        isReady: false
    };

    /* Global Function for Dynamic Group Coloring in RowGroup */
    function getGroupColor(group) {
        var colors = [
            '#3b82f6', // Blue
            '#10b981', // Emerald
            '#f59e0b', // Amber
            '#f43f5e', // Rose
            '#6366f1', // Indigo
            '#8b5cf6', // Violet
            '#06b6d4', // Cyan
            '#f97316'  // Orange
        ];
        if (!group || group === '-' || group === 'LOKASI TIDAK TERDEFINISI') return '#64748b'; // Slate for unknown

        // Simple hash to pick consistent color
        var hash = 0;
        var str = String(group);
        for (var i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
        }
        var idx = Math.abs(hash) % colors.length;
        return colors[idx];
    }

    $(document).ready(function () {
        // Hindari popup "DataTables warning: Invalid JSON response" saat koneksi putus.
        $.fn.dataTable.ext.errMode = 'none';

        function isLikelyNetworkError(xhr, textStatus) {
            if (!navigator.onLine) return true;
            if (textStatus === 'timeout' || textStatus === 'error' || textStatus === 'parsererror') return true;
            if (!xhr) return false;
            if (xhr.status === 0) return true;
            return false;
        }

        function setNetworkStatus(message, isError) {
            var $el = $('#network-status-toast');
            if (!$el.length) {
                $el = $('<div id="network-status-toast"></div>');
                $('body').append($el);
            }
            if (!message) {
                $el.removeClass('show');
                return;
            }
            var icon = isError
                ? '<i class="fas fa-wifi" style="opacity:.7"></i> '
                : '<i class="fas fa-check-circle"></i> ';
            $el
                .toggleClass('toast-error', !!isError)
                .toggleClass('toast-success', !isError)
                .html(icon + message)
                .addClass('show');
        }

        function stopManualRefreshLoading() {
            var $btnNormal = $('#btnRefresh');
            var $btnFloat = $('#btnFloatingRefresh');
            var $icons = $btnNormal.find('i').add($btnFloat.find('i'));
            $icons.removeClass('fa-spin');
            $btnFloat.removeClass('loading');
            isManualRefreshing = false;
        }

        window.addEventListener('offline', function () {
            setNetworkStatus('Koneksi terputus. Menunggu jaringan kembali...', true);
            stopManualRefreshLoading();
        });

        window.addEventListener('online', function () {
            setNetworkStatus('Koneksi kembali normal.', false);
            setTimeout(function () { setNetworkStatus('', false); }, 2500);
            if (table) {
                table.ajax.reload(null, false);
                loadSummary();
            }
        });

        function syncSettings(action, data, callback) {
            var url = '/gg_app/pages/planning/api_planning_settings.php?action=' + action + '&_t=' + new Date().getTime();
            var method = (action === 'save') ? 'POST' : 'GET';
            var body = (action === 'save') ? JSON.stringify(data) : null;

            $.ajax({
                url: url,
                type: method,
                contentType: 'application/json',
                cache: false,
                data: body,
                success: function (res) {
                    if (res.error) {
                        console.error('Settings Error:', res.error);
                        return;
                    }
                    var normalized = (res && res.settings) ? res.settings : res;
                    globalSettings = normalized || {};
                    if (callback) callback(globalSettings);
                },
                error: function (xhr, textStatus) {
                    if (isLikelyNetworkError(xhr, textStatus)) {
                        setNetworkStatus('Koneksi tidak stabil, setting belum tersimpan.', true);
                    }
                }
            });
        }

        // ── 1. Load Summary Cards ──────────────────────────────────────────────
        function loadSummary() {
            $.post('/gg_app/pages/planning/get_summary_data.php', {
                filter_grup: $('#filterGrup').val()
            }, function (res) {
                if (res.error) return;
                $('#card-total').text(res.total);

                var grupName = $('#filterGrup option:selected').text();
                if (!grupName || grupName.includes('--')) grupName = 'Semua Grup';
                $('#tv-group-label').text(grupName);

                if ($('#filterRouting option').length <= 1 && res.routings) {
                    $.each(res.routings, function (i, r) {
                        $('#filterRouting').append($('<option>', { value: r, text: r }));
                    });
                }
            }, 'json').fail(function (xhr, textStatus) {
                if (isLikelyNetworkError(xhr, textStatus)) {
                    setNetworkStatus('Koneksi terputus saat memuat ringkasan.', true);
                }
            });
        }

        // ── 2. Dashboard Initialization (Wait for Server Settings) ───────────
        function initDashboard() {
            loadSummary();

            var loadingStartTime = 0;
            var minLoadingTime = 300;

            $('#planningTable').on('preXhr.dt', function () {
                if (isFullscreen) return;
                loadingStartTime = Date.now();
                $('#loadingOverlay').addClass('active');
            });

            $('#planningTable').on('draw.dt', function () {
                // Update Cache Scroll khusus untuk mode Fullscreen/TV
                if (isFullscreen) {
                    setTimeout(function() {
                        var $wrapper = $('.fullscreen-mode');
                        if (!$wrapper.length) $wrapper = $('.content-wrapper');
                        
                        var el = $wrapper[0];
                        if (el) {
                            tvScrollData.$el = $wrapper;
                            tvScrollData.maxScroll = el.scrollHeight - el.clientHeight;
                            tvScrollData.isReady = true;
                        }
                    }, 500); // Beri waktu DOM untuk render sempurna
                    
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

            $('#planningTable').on('xhr.dt', function (e, settings, json, xhr) {
                if (json && json.error === 'Unauthorized') {
                    window.location.href = '/gg_app/login.php';
                }
            });
            $('#planningTable').on('error.dt', function (e, settings, techNote, message) {
                setNetworkStatus('Koneksi bermasalah. Data akan dicoba ulang otomatis.', true);
                stopManualRefreshLoading();

                // JIKA JSON ERROR (biasanya techNote 1 atau message mengandung json)
                // Kita TIDAK melakukan reload halaman agar mode Fullscreen tidak lepas.
                // Sebagai gantinya, kita lakukan retry otomatis tiap 5 detik.
                var isJsonError = (techNote === 1) || (message && message.toLowerCase().includes('json'));
                if (isJsonError) {
                    setNetworkStatus('Data tidak valid (Server Update). Mencoba menghubungkan kembali...', true);
                    console.log('JSON Error detected. Starting silent retry to preserve Fullscreen...');

                    // Retry setelah 5 detik tanpa refresh halaman
                    setTimeout(function () {
                        if (table) table.ajax.reload(null, false);
                    }, 5000);
                }
            });

            var savedOrder = loadTvColOrder();

            table = $('#planningTable').DataTable({
                processing: true,
                serverSide: true,
                responsive: false, // OFF: Bentrok dengan manual toggle visibility
                autoWidth: false,
                colReorder: {
                    order: savedOrder || null
                },
                ajax: {
                    url: '/gg_app/pages/planning/get_planning_data.php?debug_sort=1',
                    type: 'POST',
                    data: function (d) {
                        d.filter_routing = $('#filterRouting').val();
                        d.filter_kategori = $('#filterKategori').val();
                        d.filter_grup = $('#filterGrup').val();
                        if (window.forcePlanningUpdate) {
                            d.force_update = 1;
                            window.forcePlanningUpdate = false;
                        }
                    },
                    error: function (xhr, textStatus) {
                        if (isLikelyNetworkError(xhr, textStatus)) {
                            setNetworkStatus('Koneksi terputus. Menunggu koneksi kembali...', true);
                        }
                        stopManualRefreshLoading();
                    }
                },
                columns: [
                    { data: 'no', name: 'no', className: 'text-center', width: '40px', orderable: false },
                    { data: 'tgl_plan', name: 'tgl_plan', className: 'text-center', width: '85px', orderable: false },
                    { 
                        data: 'no_cp', 
                        name: 'no_cp', 
                        className: 'text-center font-weight-bold', 
                        width: '110px', 
                        orderable: false,
                        render: function(data, type, row) {
                            if (type === 'display') {
                                var dotCls = 'dot-' + (row.status_delay || 'waiting');
                                var pulseCls = (row.status_delay === 'delay') ? 'pulse-delay' : '';
                                return `<div class="d-flex align-items-center justify-content-center">
                                            <span class="status-dot ${dotCls} ${pulseCls}" title="Status: ${row.status_delay}"></span>
                                            ${data}
                                        </div>`;
                            }
                            return data;
                        }
                    },
                    { data: 'tgl_cp', name: 'tgl_cp', className: 'text-center', width: '85px', orderable: false },
                    { 
                        data: 'plan_start', 
                        name: 'waktu_start', 
                        className: 'text-center', 
                        width: '100px', 
                        orderable: true,
                        render: function(data, type, row) {
                            var displayPlan = (data === '-' || !data) ? '-' : data;
                            var actual = row.waktu_start || '-';
                            var varStr = row.var_start || '';
                            
                            var varHtml = '';
                            var actualColor = '#10b981'; // Default green
                            if (varStr) {
                                var isLambat = varStr.includes('LAMBAT');
                                if (isLambat) actualColor = '#ef4444'; // Red if late
                                
                                var pillBg = isLambat ? '#fee2e2' : '#d1fae5';
                                var pillColor = isLambat ? '#b91c1c' : '#065f46';
                                var pillBorder = isLambat ? '#f87171' : '#34d399';
                                varHtml = `<div class="time-pill-val" style="padding: 2px 10px; border-radius: 12px; background: ${pillBg}; color: ${pillColor}; border: 1.5px solid ${pillBorder}; margin-top: 5px; white-space: nowrap; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">${varStr}</div>`;
                            }
                            
                            if (actual === '-') {
                                return `<span class="text-time-plan time-plan-val">${displayPlan}</span>`;
                            }
                            
                            return `<div class="d-flex flex-column align-items-center" style="line-height: 1.2; padding: 4px 0;">
                                        <span class="text-time-plan time-plan-val" style="letter-spacing: 0.5px;">${displayPlan}</span>
                                        <div class="time-actual-val" style="color: ${actualColor}; white-space: nowrap; margin-top: 2px;">
                                            Akt: ${actual}
                                        </div>
                                        ${varHtml}
                                    </div>`;
                        }
                    },
                    { 
                        data: 'plan_finish', 
                        name: 'waktu_finish', 
                        className: 'text-center', 
                        width: '100px', 
                        orderable: false,
                        render: function(data, type, row) {
                            var displayPlan = (data === '-' || !data) ? '-' : data;
                            var actual = row.waktu_finish || '-';
                            var varStr = row.var_finish || '';

                            var varHtml = '';
                            var actualColor = '#10b981'; // Default green
                            if (varStr) {
                                var isLambat = varStr.includes('LAMBAT');
                                if (isLambat) actualColor = '#ef4444'; // Red if late

                                var pillBg = isLambat ? '#fee2e2' : '#d1fae5';
                                var pillColor = isLambat ? '#b91c1c' : '#065f46';
                                var pillBorder = isLambat ? '#f87171' : '#34d399';
                                varHtml = `<div class="time-pill-val" style="padding: 2px 10px; border-radius: 12px; background: ${pillBg}; color: ${pillColor}; border: 1.5px solid ${pillBorder}; margin-top: 4px; white-space: nowrap; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">${varStr}</div>`;
                            }
                            
                            if (actual === '-') {
                                return `<span class="text-time-plan time-plan-val">${displayPlan}</span>`;
                            }

                            return `<div class="d-flex flex-column align-items-center" style="line-height: 1.2; padding: 4px 0;">
                                        <span class="text-time-plan time-plan-val" style="letter-spacing: 0.5px;">${displayPlan}</span>
                                        <div class="time-actual-val" style="color: ${actualColor}; white-space: nowrap; margin-top: 2px;">
                                            Akt: ${actual}
                                        </div>
                                        ${varHtml}
                                    </div>`;
                        }
                    },
                    { 
                        data: 'status_operasional', 
                        name: 'status_operasional', 
                        className: 'text-center', 
                        width: '120px', 
                        orderable: false,
                        render: function(data, type, row) {
                            // PRIORITAS: Jika ada delay (Start atau Finish), tampilkan DELAY
                            if (row.status_delay === 'delay') {
                                return `<span class="badge-status badge-delay">DELAY</span>`;
                            }

                            var cls = data || 'waiting';
                            var label = 'MENUNGGU';
                            if (cls === 'running') label = 'BERJALAN';
                            if (cls === 'done') label = 'SELESAI';
                            
                            return `<span class="badge-status badge-${cls}">${label}</span>`;
                        }
                    },
                    { data: 'label', name: 'label', orderable: false },
                    { data: 'cust_color', name: 'cust_color', className: 'text-center', orderable: false },
                    { data: 'kode_lab', name: 'kode_lab', className: 'text-center', orderable: false },
                    { data: 'color_name', name: 'color_name', orderable: false },
                    { data: 'material', name: 'material', orderable: false },
                    { data: 'qty', name: 'qty', className: 'text-right', width: '70px', orderable: false },
                    { data: 'current_routing', name: 'current_routing', orderable: false },
                    { data: 'next_routing', name: 'next_routing', orderable: false },
                    { data: 'vlot', name: 'vlot', className: 'text-center', width: '60px', orderable: false },
                    { data: 'kategori_timbang', name: 'kategori_timbang', className: 'text-center', width: '90px', orderable: false },
                    { data: 'lokasi_paddry', name: 'lokasi_paddry', visible: false, searchable: false, orderable: false },
                ],
                order: [[4, 'asc']],
                pageLength: 25,
                rowGroup: {
                    dataSrc: 'lokasi_paddry',
                    startRender: function(rows, group) {
                        var color = getGroupColor(group);
                        var label = (group === '-' || group === '') ? 'LOKASI TIDAK TERDEFINISI' : 'LOKASI ' + group.toUpperCase();
                        var isTvMode = $('body').hasClass('tv-mode');

                        // Build HTML with dynamic color baked into style attribute
                        var borderWidth = isTvMode ? '12px' : '6px';
                        var bgColor     = isTvMode ? color + '33' : color + '12';
                        var textColor   = isTvMode ? '#fff' : color;
                        var borderTop   = isTvMode ? '4px solid rgba(255,255,255,0.05)' : '3px solid ' + color + '44';
                        var borderBot   = isTvMode ? '2px solid rgba(255,255,255,0.1)' : '1px solid ' + color + '33';

                        var tdStyle = [
                            'padding:0 !important',
                            'background:' + bgColor + ' !important',
                            'border-left:' + borderWidth + ' solid ' + color + ' !important',
                            'border-top:' + borderTop + ' !important',
                            'border-bottom:' + borderBot + ' !important',
                            'color:' + textColor + ' !important'
                        ].join(';');

                        var badgeTextColor = isTvMode ? '#fff' : color;
                        var badgeBg = isTvMode ? (color + '44') : (color + '20');
                        var badgeBorder = isTvMode ? (color + '88') : (color + 'bb');
                        var badgeStyle = 'font-size:0.85em; font-weight:800; padding:4px 14px; ' +
                                         'background:' + badgeBg + '; ' +
                                         'color:' + badgeTextColor + '; ' +
                                         'border:2px solid ' + badgeBorder + '; ' +
                                         'border-radius:20px; letter-spacing:0.05em; white-space:nowrap;';

                        var html = '<td colspan="18" style="' + tdStyle + '">' +
                                   '<div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;">' +
                                   '<span><i class="fas fa-map-marker-alt mr-2" style="color:' + color + '"></i>' + label + '</span>' +
                                   '<span style="' + badgeStyle + '">' + rows.count() + ' CP</span>' +
                                   '</div></td>';

                        return $('<tr/>').append(html);
                    }
                },
                createdRow: function(row, data, dataIndex) {
                    // Only apply colored left border in TV Mode (detected via body class)
                    if ($('body').hasClass('tv-mode')) {
                        var group = data.lokasi_paddry;
                        var color = getGroupColor(group);
                        
                        // Match the border-left width and lightness
                        $(row).find('td:first-child').css({
                            'border-left': '12px solid ' + color,
                            'border-left-color': color,
                            'opacity': '1'
                        }).css('border-left-color', color.replace('#', 'rgba(' + parseInt(color.slice(1, 3), 16) + ',' + parseInt(color.slice(3, 5), 16) + ',' + parseInt(color.slice(5, 7), 16) + ', 0.3)')); 
                        // Note: Using 0.3 opacity for the line to be subtle but distinct
                    }
                },
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
                drawCallback: function (settings) {
                    var json = settings.json;

                    if (json) {
                        var cacheToken = String(json.cache_token || json.cache_time || '');
                        var cacheTime = json.cache_time ? String(json.cache_time).split(' ')[1] : '-';

                        // Update label hanya saat cache server benar-benar berubah.
                        if (cacheToken && cacheToken !== lastRenderedCacheToken) {
                            lastRenderedCacheToken = cacheToken;
                            lastRenderedUpdateText = 'Update: ' + cacheTime;
                            lastRenderedTvUpdateHtml = '<i class="far fa-clock mr-1"></i> Update: ' + cacheTime;
                        }

                        // Update Header Text Info
                        // (dibuat statis berdasarkan cache update, bukan waktu render browser)

                        // Update Debug Menu Section
                        $('#debug-sync').text(json.cache_time || '-');
                        $('#debug-duration').text((json.cache_duration || 0) + 's');
                        $('#debug-rows').text((json.cache_rows || 0) + ' rows');

                        if (json.cache_time) {
                            var lastTs = new Date(json.cache_time).getTime();
                            var nowTs = new Date().getTime();
                            var diffAge = Math.floor((nowTs - lastTs) / 1000);
                            $('#debug-age').text(diffAge + 's ago');
                        }

                        // Update Summary Cards secara sinkron 1:1 dengan tabel
                        if (json.summary) {
                            $('#card-total').text(json.summary.total);
                        }

                        // GLOBAL SETTINGS SYNC: Cek jika ada user lain yang ubah setting
                        if (json.settings_time) {
                            var serverSettingsTime = parseInt(json.settings_time);
                            if (lastSettingsTime > 0 && serverSettingsTime > lastSettingsTime) {
                                console.log('Settings update detected from other PC, syncing...');
                                lastSettingsTime = serverSettingsTime;
                                syncSettings('get', null, function (newSettings) {
                                    // Re-apply critical settings without reload
                                    if (newSettings.auto_refresh) {
                                        $('#autoRefreshSelect').val(newSettings.auto_refresh);
                                        applyStaticRefresh(parseInt(newSettings.auto_refresh) || 0);
                                    }
                                    if (newSettings.tv_mode) {
                                        TV_CONFIG.mode = newSettings.tv_mode;
                                        TV_CONFIG.speed = newSettings.tv_speed || 2;
                                        updateTvAutomationUI();
                                        if (isFullscreen) startTvAutomation();
                                    }
                                    // Visibilitas kolom (Hanya apply jika dalam TV mode)
                                    if (isFullscreen) applyTvColVisibility();
                                });
                            } else {
                                lastSettingsTime = serverSettingsTime;
                            }
                        }
                    }

                    $('#last-updated').text(lastRenderedUpdateText);
                    $('#tv-update-time').html(lastRenderedTvUpdateHtml);
                    setNetworkStatus('', false);

                    if (isFullscreen) {
                        applyTvColVisibility();
                    }
                }
            });

            // ── 3. Event Handling ──────────────────────────────────────────────
            $('#filterRouting, #filterGrup').on('change', function () {
                table.ajax.reload();
                loadSummary();
            });

            $('.filter-pill').on('click', function () {
                $('.filter-pill').removeClass('active');
                $(this).addClass('active');
                $('#filterKategori').val($(this).data('val'));
                table.ajax.reload();
            });

            $('#btnRefresh, #btnFloatingRefresh').on('click', function () {
                manualRefresh();
            });

            // Apply Server Settings to UI
            if (globalSettings.auto_refresh) {
                $('#autoRefreshSelect').val(globalSettings.auto_refresh);
                applyStaticRefresh(parseInt(globalSettings.auto_refresh) || 0);
            }
            if (globalSettings.tv_mode) {
                TV_CONFIG.mode = globalSettings.tv_mode;
                TV_CONFIG.speed = globalSettings.tv_speed || 2;
                TV_CONFIG.freeze_header = (globalSettings.tv_freeze_header === 'true' || globalSettings.tv_freeze_header === true);
                updateTvAutomationUI();
            }
        }

        // START BOOTSTRAP: Fetch settings first
        syncSettings('get', null, function (settings) {
            initDashboard();
            checkAutoFsChallenge();
        });

        // ── 4. Refresh Logic ───────────────────────────────────────────
        function manualRefresh() {
            if (isManualRefreshing) return;
            var $btnNormal = $('#btnRefresh');
            var $btnFloat = $('#btnFloatingRefresh');
            var $icons = $btnNormal.find('i').add($btnFloat.find('i'));
            isManualRefreshing = true;

            $btnNormal.find('i').addClass('fa-spin');
            $btnFloat.addClass('loading').find('i').addClass('fa-spin');

            window.forcePlanningUpdate = true; // Signal for next AJAX call
            table.ajax.reload(function () {
                $icons.removeClass('fa-spin');
                $btnFloat.removeClass('loading');
                isManualRefreshing = false;
                loadSummary();
            }, false);
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

        // Ubah durasi saat user memilih & simpan ke server
        $('#autoRefreshSelect').on('change', function () {
            var ms = $(this).val();
            syncSettings('save', { auto_refresh: ms });
            applyStaticRefresh(parseInt(ms) || 0);
        });

        // ── 5. TV Mode Automation (Smooth Scroll & Pagination) ───────────────────────
        var tvModeInterval = null;
        var tvScrollRequest = null;

        var TV_CONFIG = {
            mode: globalSettings.tv_mode || 'scroll',
            speed: parseInt(globalSettings.tv_speed) || 2,
            freeze_header: globalSettings.tv_freeze_header === 'true' || globalSettings.tv_freeze_header === true
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
            tvModeInterval = setInterval(function () {
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

        // Logic 2: Smooth Scroll (Optimasi Smart TV)
        function runTvSmoothScroll() {
            if (tvScrollRequest) {
                cancelAnimationFrame(tvScrollRequest);
                tvScrollRequest = null;
            }

            var speed = parseFloat(TV_CONFIG.speed) || 1.0;
            if (speed > 50) speed = 2.0; // Safety fallback

            // Sub-pixel accumulator to prevent rounding freeze on Smart TV
            var scrollAccumulator = 0; 
            var pauseAtBottom = 3000;
            var isPaused = false;
            
            // Re-sync scroll data if needed
            if (!tvScrollData.isReady) {
                var $wrapper = $('.fullscreen-mode');
                if (!$wrapper.length) $wrapper = $('.content-wrapper');
                var el = $wrapper[0];
                if (el) {
                    tvScrollData.$el = $wrapper;
                    tvScrollData.maxScroll = el.scrollHeight - el.clientHeight;
                    tvScrollData.isReady = true;
                }
            }

            function scroll() {
                if (!isFullscreen || TV_CONFIG.mode !== 'scroll') {
                    if (tvScrollRequest) cancelAnimationFrame(tvScrollRequest);
                    tvScrollRequest = null;
                    return;
                }

                if (!isPaused && tvScrollData.isReady) {
                    var container = tvScrollData.$el[0];
                    scrollAccumulator += speed;
                    
                    if (scrollAccumulator >= 1.0) {
                        var move = Math.floor(scrollAccumulator);
                        var currScroll = container.scrollTop;
                        
                        if (currScroll < tvScrollData.maxScroll - 2) {
                            container.scrollTop = currScroll + move;
                            scrollAccumulator -= move; 
                        } else {
                            isPaused = true;
                            scrollAccumulator = 0;
                            setTimeout(function () {
                                if (!isFullscreen || TV_CONFIG.mode !== 'scroll') return;
                                var info = table.page.info();
                                if (info.page < info.pages - 1) {
                                    table.page('next').draw('page');
                                } else {
                                    table.page('first').draw('page');
                                    table.ajax.reload(null, false);
                                    loadSummary();
                                }
                                setTimeout(() => {
                                    if (tvScrollData.$el) tvScrollData.$el.scrollTop(0);
                                    $(window).scrollTop(0);
                                    isPaused = false;
                                }, 400);
                            }, pauseAtBottom);
                        }
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
            
            // Sync Freeze Header UI
            $('#tvFreezeHeader').prop('checked', TV_CONFIG.freeze_header);
            applyTvFreezeHeader();
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

        function applyTvFreezeHeader() {
            if (TV_CONFIG.freeze_header) {
                $('body').addClass('tv-freeze-header');
                updateStickyOffsets();
            } else {
                $('body').removeClass('tv-freeze-header');
            }
        }

        function updateStickyOffsets() {
            if (isFullscreen && TV_CONFIG.freeze_header) {
                var $wrapper = $('.tv-sticky-header-wrapper');
                if ($wrapper.length) {
                    var h = $wrapper.outerHeight();
                    // Offset: Height exactly (karena container padding sudah 0)
                    document.documentElement.style.setProperty('--tv-header-offset', h + 'px');
                }
            }
        }

        // Logic to update config and start
        function syncTvConfig() {
            var mode = $('#tvAutomationMode').val();
            var speedVal = $('#tvAutomationSpeed').val();
            var isFrozen = $('#tvFreezeHeader').is(':checked');
            var finalSpeed = speedVal;

            if (speedVal === 'custom') {
                finalSpeed = parseInt($('#tvAutomationSpeedCustom').val()) || 0;
            } else {
                finalSpeed = parseInt(speedVal);
            }

            TV_CONFIG.mode = mode;
            TV_CONFIG.speed = finalSpeed;
            TV_CONFIG.freeze_header = isFrozen;
            
            syncSettings('save', { 
                tv_mode: mode, 
                tv_speed: finalSpeed,
                tv_freeze_header: isFrozen 
            });

            applyTvFreezeHeader();
            if (isFullscreen) startTvAutomation();
        }

        $('#tvAutomationMode').on('change', function () {
            populateTvSpeedOptions();
            syncTvConfig();
            handleTvSpeedUI();
        });

        $('#tvAutomationSpeed').on('change', function () {
            handleTvSpeedUI();
            syncTvConfig();
        });

        $('#tvAutomationSpeedCustom').on('input', function () {
            syncTvConfig();
        });

        $('#tvFreezeHeader').on('change', function () {
            syncTvConfig();
        });

        // Initialize UI with initial values
        (function initAutomationUI() {
            populateTvSpeedOptions();
            $('#tvAutomationMode').val(TV_CONFIG.mode);

            // Check if current speed matches any preset
            var found = false;
            $('#tvAutomationSpeed option').each(function () {
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
            
            // Initialize Freeze Header Toggle
            $('#tvFreezeHeader').prop('checked', TV_CONFIG.freeze_header);
            applyTvFreezeHeader();
        })();


        // ── 6. TV Column Settings ─────────────────────────────────────────────
        var TV_COL_STORAGE = 'wip_tv_cols';
        var TV_ORDER_STORAGE = 'wip_tv_order';

        // [index, label, default visible]
        var tvColumns = [
            { idx: 0, name: 'no', label: 'No.', def: true },
            { idx: 1, name: 'tgl_plan', label: 'Tgl Planning', def: true },
            { idx: 2, name: 'no_cp', label: 'No.CP', def: true },
            { idx: 3, name: 'tgl_cp', label: 'Tgl CP', def: false },
            { idx: 4, name: 'waktu_start', label: 'Start', def: true },
            { idx: 5, name: 'waktu_finish', label: 'Finish', def: false },
            { idx: 6, name: 'status_delay', label: 'Status', def: false },
            { idx: 7, name: 'label', label: 'Label', def: true },
            { idx: 8, name: 'cust_color', label: 'Cust Color', def: true },
            { idx: 9, name: 'kode_lab', label: 'Kode Lab', def: true },
            { idx: 10, name: 'color_name', label: 'Color Name', def: true },
            { idx: 11, name: 'material', label: 'Material', def: true },
            { idx: 12, name: 'qty', label: 'Qty', def: true },
            { idx: 13, name: 'current_routing', label: 'Current Routing', def: true },
            { idx: 14, name: 'next_routing', label: 'Next Routing', def: true },
            { idx: 15, name: 'vlot', label: 'Vlot', def: false },
            { idx: 16, name: 'kategori_timbang', label: 'Kategori Timbang', def: true },
        ];

        function loadTvColPrefs() {
            return globalSettings.col_prefs || null;
        }

        function loadTvColOrder() {
            var arr = globalSettings.col_order;
            // Jika data corrupt jadi object {"0":0}, ubah kembali ke array
            if (arr && typeof arr === 'object' && !Array.isArray(arr)) {
                arr = Object.values(arr);
            }
            if (Array.isArray(arr) && arr.length !== tvColumns.length) {
                return null;
            }
            return (Array.isArray(arr)) ? arr : null;
        }

        function saveTvColPrefs(prefs) {
            globalSettings.col_prefs = prefs; // Optimistic update
            syncSettings('save', { col_prefs: prefs });
        }

        function saveTvColOrder(order) {
            globalSettings.col_order = order; // Optimistic update
            syncSettings('save', { col_order: order });
        }

        function getEffectivePrefs() {
            var saved = loadTvColPrefs();
            var prefs = {};
            tvColumns.forEach(function (c) {
                // Handle possible string/int key mismatch from JSON
                var val = (saved && saved[c.idx] !== undefined) ? saved[c.idx] :
                    (saved && saved[String(c.idx)] !== undefined ? saved[String(c.idx)] : c.def);
                prefs[c.idx] = val;
            });
            return prefs;
        }

        function applyTvColOrder(order) {
            if (!order || !order.length) return;
            table.colReorder.order(order, true);
        }

        // Build toggle list
        function buildTvColToggles() {
            var prefs = getEffectivePrefs();
            var currentOrder = table.colReorder.order();
            var $container = $('#tvColToggles').empty();

            // Sort tvColumns based on currentOrder to render them in the current display order
            var sortedCols = [...tvColumns].sort((a, b) => {
                return currentOrder.indexOf(a.idx) - currentOrder.indexOf(b.idx);
            });

            sortedCols.forEach(function (c, index) {
                var checked = prefs[c.idx] ? 'checked' : '';
                var isFirst = (index === 0);
                var isLast = (index === sortedCols.length - 1);

                var $item = $('\
                <div class="col-toggle-item" data-idx="' + c.idx + '">\
                    <div class="col-toggle-info">\
                        <span class="col-toggle-label">' + c.label + '</span>\
                    </div>\
                    <div class="tv-col-controls">\
                        <div class="reorder-btns mr-3">\
                            <button class="btn-reorder btn-up" ' + (isFirst ? 'disabled' : '') + ' title="Pindahkan ke Atas">\
                                <i class="fas fa-arrow-up"></i>\
                            </button>\
                            <button class="btn-reorder btn-down" ' + (isLast ? 'disabled' : '') + ' title="Pindahkan ke Bawah">\
                                <i class="fas fa-arrow-down"></i>\
                            </button>\
                        </div>\
                        <label class="tv-switch">\
                            <input type="checkbox" data-col="' + c.idx + '" ' + checked + '>\
                            <span class="tv-slider"></span>\
                        </label>\
                    </div>\
                </div>');
                $container.append($item);
            });

            // Reorder Handlers
            $container.off('click', '.btn-up').on('click', '.btn-up', function () {
                var origIdx = $(this).closest('.col-toggle-item').data('idx');
                moveTvCol(origIdx, 'up');
            });

            $container.off('click', '.btn-down').on('click', '.btn-down', function () {
                var origIdx = $(this).closest('.col-toggle-item').data('idx');
                moveTvCol(origIdx, 'down');
            });

            // Save on change visibility
            $container.off('change', 'input[type=checkbox]').on('change', 'input[type=checkbox]', function () {
                var prefs = getEffectivePrefs();
                var idx = parseInt($(this).data('col'));
                prefs[idx] = $(this).is(':checked');

                // Urutan: Update local state -> Save to server -> Apply UI
                globalSettings.col_prefs = prefs;
                saveTvColPrefs(prefs);

                if (isFullscreen) applyTvColVisibility();
            });
        }

        function moveTvCol(origIdx, direction) {
            var currentOrder = table.colReorder.order();
            var currPos = currentOrder.indexOf(origIdx);
            var targetPos = (direction === 'up') ? currPos - 1 : currPos + 1;

            if (targetPos < 0 || targetPos >= currentOrder.length) return;

            // Swap in array
            var temp = currentOrder[currPos];
            currentOrder[currPos] = currentOrder[targetPos];
            currentOrder[targetPos] = temp;

            // Apply and Save
            table.colReorder.order(currentOrder, true);
            saveTvColOrder(currentOrder);

            // Re-render list
            buildTvColToggles();

            // Refresh visibility (if columns moved while visibility was applied)
            if (isFullscreen) applyTvColVisibility();
        }

        function applyTvColVisibility() {
            var prefs = getEffectivePrefs();

            table.columns().every(function () {
                var colIdx = this.index();
                var colName = this.context[0].aoColumns[colIdx].sName;

                var config = tvColumns.find(c => c.name === colName || c.idx === colIdx);
                if (config) {
                    // SEKARANG: Selalu gunakan prefensi, jangan paksa 'true' di luar fullscreen
                    var isVisible = (prefs[config.idx] === true || prefs[config.idx] === 'true');
                    this.visible(isVisible, false);
                }
            });

            table.columns.adjust().draw(false);

            // Re-apply special headers ...
            var headerText = isFullscreen ? 'Posisi Saat Ini (Belum Ditembak)' : 'Next Routing';
            var nextRoutingCol = table.column('next_routing:name');
            if (nextRoutingCol.length) {
                $(nextRoutingCol.header()).text(headerText);
            }

            var totalLabel = isFullscreen ? 'Total CP Aktif (Belum Ditembak Proint)' : 'Total CP Aktif';
            $('#label-total-cp').text(totalLabel);

            // AUTO-ZOOM CALCULATION (Option C)
            if (isFullscreen) {
                setTimeout(adjustTvZoom, 300);
            } else {
                $('.card-table-wrap').css('zoom', '');
            }
        }

        function adjustTvZoom() {
            if (!isFullscreen) return;

            var $container = $('.card-table-wrap');
            var $table = $('#planningTable');
            
            // 1. Reset sementara untuk ukur lebar asli
            $container.css('zoom', '1');
            $table.css('width', 'max-content'); // Paksa kolom melebar sesuai isinya
            
            var naturalWidth = $table[0].scrollWidth;
            var viewportWidth = $(window).width() - 60; // Sync dengan padding CSS (30px kiri + 30px kanan)
            
            console.log('TV Zoom Calculation:', { naturalWidth, viewportWidth });

            if (naturalWidth > viewportWidth) {
                var zoomRatio = viewportWidth / naturalWidth;
                
                // Gunakan rasio murni agar selalu pas di layar (Zero Overflow)
                if (zoomRatio < 0.6) zoomRatio = 0.6;
                
                $container.css({
                    'zoom': zoomRatio,
                    'transform-origin': 'top center'
                });
                document.documentElement.style.setProperty('--tv-zoom', zoomRatio);
                
                console.log('Applied Zoom Ratio:', zoomRatio);
                setTimeout(updateStickyOffsets, 300); // Re-calc after zoom
            } else {
                $container.css('zoom', '1');
                document.documentElement.style.setProperty('--tv-zoom', '1');
                setTimeout(updateStickyOffsets, 300);
            }
            
            // Kembalikan lebar tabel ke 100% dari container yang sudah di-zoom
            $table.css('width', '100%');
        }

        $(window).on('resize', function() {
            if (isFullscreen) adjustTvZoom();
        });

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

        $('#btnResetTvCols').on('click', function () {
            if (!confirm('Pindahkan urutan dan visibilitas kolom ke default?')) return;

            // Reset order to default [0, 1, 2, ..., 13]
            var defaultOrder = Array.from({ length: tvColumns.length }, (_, i) => i);
            var defaultPrefs = {};
            tvColumns.forEach(c => defaultPrefs[c.idx] = c.def);

            // --- SERVER TIME SYNC LOGIC ---
            var serverTime = <?php echo time(); ?> * 1000; // Server time in ms
            var localTime = new Date().getTime();
            var serverTimeOffset = serverTime - localTime; // Selisih waktu server vs lokal

            function updateClock() {
                var now = new Date();
                // Terapkan offset server agar jam sinkron dengan server
                var adjustedTime = new Date(now.getTime() + serverTimeOffset);
                
                var hours = adjustedTime.getHours();
                var minutes = adjustedTime.getMinutes();
                var seconds = adjustedTime.getSeconds();
                
                hours = hours < 10 ? '0' + hours : hours;
                minutes = minutes < 10 ? '0' + minutes : minutes;
                seconds = seconds < 10 ? '0' + seconds : seconds;
                
                var timeString = hours + ':' + minutes + ':' + seconds;
                $('#tv-clock').text(timeString);
                $('#current-time').text(timeString);
            }

            syncSettings('save', {
                col_order: defaultOrder,
                col_prefs: defaultPrefs
            }, function () {
                table.colReorder.order(defaultOrder, true);
                buildTvColToggles();
                applyTvColVisibility();
            });
        });

        // ── 7. Fullscreen Logic ───────────────────────────────────────────────
        var tvClockInterval = null;

        function startTvClock() {
            function tick() {
                var now = new Date();
                var hm = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
                var s = ':' + String(now.getSeconds()).padStart(2, '0');
                $('#tv-clock-hm').text(hm);
                $('#tv-clock-s').text(s);
            }
            tick();
            tvClockInterval = setInterval(tick, 1000);
        }

        function stopTvClock() {
            if (tvClockInterval) { clearInterval(tvClockInterval); tvClockInterval = null; }
            $('#tv-clock-hm').text('--:--');
            $('#tv-clock-s').text(':--');
        }

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
                tvScrollData.isReady = false; // Force re-calculate for TV

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
                startTvClock();

                // Recalculate sticky offsets after entering TV mode
                setTimeout(updateStickyOffsets, 500);

                // Apply saved order if exists
                var savedOrder = loadTvColOrder();
                if (savedOrder) applyTvColOrder(savedOrder);

                // Tambahkan param auto_fs di URL agar jika di-refresh tetap masuk FS
                var url = new URL(window.location.href);
                url.searchParams.set('auto_fs', '1');
                window.history.replaceState({}, '', url.toString());

            } else {
                // Exit fullscreen (Normal Mode)
                $contentWrapper.removeClass('fullscreen-mode');
                $('body').removeClass('in-fullscreen tv-mode');
                $btn.removeClass('fullscreen-active');
                $btn.find('i').removeClass('fa-compress').addClass('fa-tv');
                isFullscreen = false;
                tvScrollData.isReady = false;

                // Hapus param auto_fs
                var url = new URL(window.location.href);
                url.searchParams.delete('auto_fs');
                window.history.replaceState({}, '', url.toString());

                // Stop Otomasi
                stopTvAutomation();
                stopTvClock();

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

        // ── 8. Browser Fullscreen Sync ───────────────────────────────────────────────
        // Bootstrap to handle auto_fs with interaction challenge (Security bypass)
        function checkAutoFsChallenge() {
            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('auto_fs') === '1') {
                // Mencoba langsung masuk fullscreen
                try {
                    var p = document.documentElement.requestFullscreen ? document.documentElement.requestFullscreen() : null;
                    if (p && p.catch) {
                        p.catch(function (err) {
                            // Dibatasi browser karena belum ada interaksi user
                            showFsChallenge();
                        });
                    } else if (!isFullscreen) {
                        $('#btnToggleFullscreen').trigger('click');
                    }
                } catch (e) {
                    showFsChallenge();
                }
            }
        }

        function showFsChallenge() {
            if ($('#fs-challenge-overlay').length) return;
            console.warn('Fullscreen automatic check failed, showing interaction challenge.');
            $('body').append('<div id="fs-challenge-overlay"><div class="icon"><i class="fas fa-expand-arrows-alt"></i></div><h2>TV Mode Auto-Sync</h2><p>Klik di mana saja untuk mengaktifkan tampilan Fullscreen</p></div>');
            $('#fs-challenge-overlay').fadeIn(300).css('display', 'flex').on('click', function () {
                $(this).fadeOut(300, function () { $(this).remove(); });
                $('#btnToggleFullscreen').trigger('click');
            });
        }

        // Handle ESC key browser (Sync UI state)
        $(document).on('fullscreenchange webkitfullscreenchange msfullscreenchange', function () {
            var isBrowserFs = !!(document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement);
            if (!isBrowserFs && isFullscreen) {
                // Jika browser keluar FS tapi UI kita masih FS (berarti user pencet Esc)
                // Panggil click sekali lagi untuk reset UI (karena toggle)
                $('#btnToggleFullscreen').trigger('click');
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
