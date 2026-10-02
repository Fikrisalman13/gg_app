<?php
session_start();
ob_start();
include '../../../koneksi.php';
include '../../../includes/header.php';
include '../../../includes/sidebar.php';

// Ensure server uses Jakarta timezone for issue timestamps
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

// Fetch User's Trustee Group
$userGroup = 'Tidak Ada Grup';
$groupsArr = [];
// Gunakan query yang lebih sederhana untuk memastikan kompatibilitas
$sqlGroup = "SELECT group_name FROM planning_trustee_user_group WHERE username = ?";
$stmtGroup = sqlsrv_query($conn, $sqlGroup, [$_SESSION['UserName']]);

if ($stmtGroup) {
    while ($rowGroup = sqlsrv_fetch_array($stmtGroup, SQLSRV_FETCH_ASSOC)) {
        if (!empty($rowGroup['group_name'])) {
            $groupsArr[] = $rowGroup['group_name'];
        }
    }
}

if (!empty($groupsArr)) {
    $userGroup = implode(', ', $groupsArr);
} else {
    $userGroup = "Tidak Ada Grup";
}

// Fetch User GroupId for rollback restriction
$userGroupId = (int) ($_SESSION['GroupId'] ?? 0);
if ($userGroupId === 0) {
    $sqlUser = "SELECT GroupId FROM msuser WHERE UPPER(UserName) = UPPER(?)";
    $stmtUser = sqlsrv_query($conn, $sqlUser, [$_SESSION['UserName']]);
    if ($stmtUser && $rowUser = sqlsrv_fetch_array($stmtUser, SQLSRV_FETCH_ASSOC)) {
        $userGroupId = (int) $rowUser['GroupId'];
    }
}

// Fetch Authorized RTGMSIDs based on User's Groups
$authorizedRtgmsids = [];
if (!empty($groupsArr)) {
    $placeholders = implode(',', array_fill(0, count($groupsArr), '?'));
    $sqlTrustee = "SELECT rtgmsid FROM planning_trustee_group WHERE group_name IN ($placeholders)";
    $stmtTrustee = sqlsrv_query($conn, $sqlTrustee, $groupsArr);
    if ($stmtTrustee) {
        while ($rowTrustee = sqlsrv_fetch_array($stmtTrustee, SQLSRV_FETCH_ASSOC)) {
            if (!empty($rowTrustee['rtgmsid'])) {
                $authorizedRtgmsids[] = (string) $rowTrustee['rtgmsid'];
            }
        }
    }
}
?>

<link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@500;600;700;800&display=swap"
    rel="stylesheet">
<!-- SweetAlert2 -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
<!-- Select2 -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
<link rel="stylesheet"
    href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">

<style>
    /* Fix Select2 alignment & Z-Index in SweetAlert2 */
    .select2-container--bootstrap4 .select2-results__option {
        text-align: left !important;
    }

    .select2-container,
    .select2-dropdown {
        z-index: 999999 !important;
    }

    body,
    .content-wrapper {
        font-family: 'Inter', sans-serif !important;
        background-color: #f1f5f9;
    }

    .outfit-font {
        font-family: 'Outfit', sans-serif !important;
    }

    .header-toolbar {
        background: white;
        border-radius: 12px;
        padding: 16px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        margin-bottom: 24px;
    }

    .cp-card {
        background: white;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        margin-bottom: 16px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        touch-action: manipulation;
        /* Mematikan zoom bawaan browser saat double-tap agar tidak merusak layout */
    }

    .cp-card:hover {
        transform: translateY(-5px) scale(1.01);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        border-color: #3b82f6;
        cursor: pointer;
    }

    .cp-card:active {
        transform: translateY(-2px) scale(0.99);
    }

    .cp-card.active-card {
        border: 2px solid #3b82f6;
        box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.2);
        transform: translateY(-2px);
    }

    .cp-card.completed-card {
        opacity: 0.6;
        background: #f8fafc;
    }

    .cp-header {
        padding: 12px 16px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        border-radius: 12px 12px 0 0;
        gap: 8px;
        position: relative;
    }

    .header-stack {
        flex-direction: column;
        align-items: flex-start;
    }

    .header-stack .status-container-responsive {
        width: 100%;
        display: flex;
        justify-content: center;
    }

    .header-row {
        flex-direction: row;
        align-items: center;
    }

    .tv-only-date {
        display: none;
    }

    body.focus-mode-active .tv-only-date {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
    }

    body.focus-mode-active .tv-only-date .date-label {
        font-size: 1.8vh;
        letter-spacing: 2px;
        color: rgba(255, 255, 255, 0.6);
        margin-bottom: 0.5vh;
        text-transform: uppercase;
        font-weight: 600;
    }

    body.focus-mode-active .tv-only-date .date-val {
        font-size: 3.8vh;
        font-weight: 800;
        color: white;
        line-height: 1.1;
    }

    /* Handle grid layout when no stages are present */
    .focus-mode-active .body-no-stages .stage-title {
        grid-column: span 2;
        margin-bottom: 2vh;
    }

    .focus-mode-active .body-no-stages .data-group,
    .focus-mode-active .body-no-stages .time-group {
        grid-row: 2;
    }

    .cp-header h5 {
        font-size: 1.1rem !important;
        margin-bottom: 0;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    .status-container-responsive {
        flex-shrink: 0;
    }

    .cp-header.bg-active {
        background: #eff6ff;
    }

    .cp-header.bg-completed {
        background: #f1f5f9;
    }

    .seq-badge {
        background: #3b82f6;
        color: white;
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-weight: 700;
        font-size: 0.9rem;
    }

    .cp-body {
        padding: 16px;
    }

    .data-row {
        display: flex;
        margin-bottom: 6px;
        font-size: 0.9rem;
    }

    .data-label {
        color: #64748b;
        width: 120px;
        font-weight: 500;
        flex-shrink: 0;
    }

    .data-value {
        color: #1e293b;
        font-weight: 600;
        flex-grow: 1;
    }

    .time-box {
        text-align: center;
        padding: 8px;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }

    .time-label {
        display: block;
        font-size: 0.75rem;
        color: #64748b;
        margin-bottom: 4px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .time-val {
        font-size: 1.1rem;
        font-weight: 700;
        color: #0f172a;
        font-family: 'Outfit', monospace;
    }

    .variance-badge {
        font-size: 0.7rem;
        padding: 3px 10px;
        border-radius: 20px;
        font-weight: 700;
        margin-top: 6px;
        display: inline-flex;
        align-items: center;
        white-space: nowrap;
        border: 1px solid transparent;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .var-early {
        background: #f0fdf4;
        color: #166534;
        border-color: #bbf7d0;
    }

    .var-late {
        background: #fef2f2;
        color: #991b1b;
        border-color: #fecaca;
    }

    .var-ontime {
        background: #f8fafc;
        color: #64748b;
        border-color: #e2e8f0;
    }

    .stage-strip {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 14px;
    }

    .stage-pill {
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #334155;
        border-radius: 999px;
        padding: 6px 10px;
        font-size: 0.75rem;
        font-weight: 700;
        line-height: 1.2;
        min-width: 78px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .stage-pill.active {
        background: #dbeafe;
        border-color: #60a5fa;
        color: #1d4ed8;
    }

    .stage-pill.done {
        background: #ecfdf5;
        border-color: #86efac;
        color: #166534;
    }

    .stage-pill.running {
        background: #fef3c7;
        border-color: #fbbf24;
        color: #92400e;
    }

    .stage-pill.failed {
        background: #fee2e2;
        border-color: #fca5a5;
        color: #991b1b;
    }

    .stage-pill.locked {
        background: #f8fafc;
        color: #94a3b8;
        border-style: dashed;
    }

    .stage-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        margin-bottom: 12px;
        padding: 10px 12px;
        border-radius: 10px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }

    .stage-name-main {
        font-weight: 800;
        color: #0f172a;
        font-size: 0.92rem;
    }

    .stage-progress-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
        border-radius: 999px;
        padding: 5px 10px;
        font-size: 0.72rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .stage-blocked-note {
        margin-top: 10px;
        padding: 10px 12px;
        border-radius: 10px;
        background: #fff7ed;
        border: 1px solid #fdba74;
        color: #9a3412;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .focus-mode-active {
        overflow: hidden;
        background: #f1f5f9 !important;
        /* Switch to a cleaner light professional background */
    }

    .focus-mode-active .main-header,
    .focus-mode-active .main-sidebar,
    .focus-mode-active .content-header,
    .focus-mode-active .header-toolbar,
    .focus-mode-active .main-footer {
        display: none !important;
    }

    /* === FULLSCREEN TRANSITION OVERLAY ===
       Menutupi layar saat browser fullscreen API sedang
       bertransisi agar renderCards() menunggu viewport final.
    */
    #fs-loading-overlay {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 99999;
        background: #1e293b;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: 1.5rem;
    }

    #fs-loading-overlay.show {
        display: flex;
    }

    #fs-loading-overlay .fs-spinner {
        width: 60px;
        height: 60px;
        border: 6px solid rgba(255, 255, 255, 0.15);
        border-top-color: #3b82f6;
        border-radius: 50%;
        animation: fsSpinAnim 0.8s linear infinite;
    }

    #fs-loading-overlay p {
        color: rgba(255, 255, 255, 0.7);
        font-size: 1rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        margin: 0;
    }

    @keyframes fsSpinAnim {
        to {
            transform: rotate(360deg);
        }
    }

    html.focus-mode-active,
    body.focus-mode-active {
        height: 100vh !important;
        height: 100dvh !important;
        overflow: hidden !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    body.focus-mode-active .content-wrapper,
    body.focus-mode-active.sidebar-collapse .content-wrapper,
    body.focus-mode-active.sidebar-mini.sidebar-collapse .content-wrapper {
        margin-left: 0 !important;
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%) !important;
        height: 100vh !important;
        height: 100dvh !important;
        min-height: 0 !important;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 0 !important;
    }

    .focus-mode-active .content,
    .focus-mode-active .container-fluid,
    .focus-mode-active #cardsWrap,
    .focus-mode-active .is-focus {
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        height: 100vh !important;
        height: 100dvh !important;
    }

    .focus-mode-active .is-focus {
        flex: 0 0 100% !important;
        animation: focusFadeIn 0.3s ease-out;
    }

    @keyframes focusFadeIn {
        from {
            opacity: 0.3;
            transform: scale(0.98);
        }

        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    .focus-mode-active #cardsWrap>div:not(.is-focus) {
        display: none !important;
    }

    .focus-mode-active .cp-card {
        background: white !important;
        border: none;
        border-radius: 0;
        color: #1e293b;
        padding: 0;
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        box-shadow: none;
    }

    .focus-mode-active .cp-header {
        background: #1e293b !important;
        border-bottom: none;
        padding: 3vh 2vw;
        color: white !important;
        border-radius: 0;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
    }

    /* Reset status container to normal (right-side) in fullscreen */
    .focus-mode-active .header-stack .status-container-responsive {
        width: auto !important;
        justify-content: flex-end !important;
    }

    .focus-mode-active .cp-header h5 {
        font-size: 5.5vh !important;
        color: white !important;
        font-weight: 800 !important;
        letter-spacing: -2px;
        line-height: 1.1;
        margin-bottom: 0.5vh !important;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    .focus-mode-active .fs-machine-name {
        font-size: 3.5vh !important;
        color: #94a3b8 !important;
        font-weight: 700 !important;
        letter-spacing: 1px;
        text-transform: uppercase;
    }

    .focus-mode-active .status-badge-focus {
        font-size: 3vh !important;
        padding: 1.5vh 3vw !important;
        border-radius: 50px !important;
        font-weight: 700 !important;
        text-transform: uppercase;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    }


    .focus-mode-active .seq-badge {
        width: 9vh;
        height: 9vh;
        font-size: 5vh;
        border-width: 4px;
        color: #1e293b;
        background: #f8fafc;
        border-color: #3b82f6;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .focus-mode-active .seq-badge:hover {
        transform: scale(1.05);
        box-shadow: 0 0 15px rgba(59, 130, 246, 0.5);
    }

    .focus-mode-active .cp-card.focus-card .status-badge-focus {
        font-size: 1.2rem !important;
        padding: 10px 20px !important;
    }

    /* Downtime Pulse */
    .pulse-red {
        animation: pulse-red-animation 2s infinite;
    }

    @keyframes pulse-red-animation {
        0% {
            transform: scale(1);
            box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
        }

        70% {
            transform: scale(1.05);
            box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
        }

        100% {
            transform: scale(1);
            box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
        }
    }

    .focus-mode-active .status-badge-focus {
        font-size: 3.5vh !important;
        padding: 1vh 3vw !important;
        border-radius: 100px !important;
    }

    .focus-mode-active .cp-body {
        flex-grow: 1;
        overflow-y: auto;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 3vw;
        padding: 3vh 5vw;
        align-items: start;
    }

    .focus-mode-active .cp-footer {
        flex-shrink: 0 !important;
        padding: 2.5vh 5vw !important;
        background: #f8fafc !important;
        border-top: 2px solid #e2e8f0 !important;
        display: block !important;
    }

    /* Scale the done-alert nicely in fullscreen */
    .focus-mode-active .cp-footer .alert {
        font-size: 2.8vh;
        padding: 2vh 2vw;
        border-radius: 20px !important;
        border-width: 2px !important;
    }

    .focus-mode-active .data-label {
        font-size: 3vh;
        color: #64748b;
        font-weight: 600;
        width: 25vh;
        flex-shrink: 0;
    }

    .focus-mode-active .data-value {
        font-size: 4.5vh;
        color: #0f172a;
        font-weight: 800 !important;
    }

    /* Style khusus material agar tidak terlalu raksasa jika teks panjang */
    .focus-mode-active .fs-material-val {
        font-size: 3.5vh !important;
        color: #475569 !important;
        line-height: 1.2;
    }

    .focus-mode-active .time-box {
        padding: 3vh;
        border-radius: 30px;
        background: #f8fafc;
        border: 2px solid #e2e8f0;
    }

    .focus-mode-active .time-label {
        font-size: 2.2vh;
        color: #3b82f6;
        margin-bottom: 1.5vh;
    }

    .focus-mode-active .time-val {
        font-size: 9vh;
        line-height: 1;
        color: #1e293b;
    }

    .focus-mode-active .plan-sub {
        font-size: 2.2vh !important;
        margin-top: 1.5vh;
        color: #94a3b8;
    }

    .focus-mode-active .variance-badge {
        font-size: 2.2vh;
        padding: 0.8vh 2vw;
        margin-top: 1.5vh;
    }

    .focus-mode-active .btn-block {
        height: 7vh;
        font-size: 3vh;
        border-radius: 20px;
    }

    #fs-clock {
        display: none !important;
    }

    .time-box-clock {
        display: none;
    }

    .focus-mode-active .time-box-clock {
        display: block;
        margin-bottom: 2vh;
    }

    .focus-mode-active .time-box-clock .time-val {
        font-size: 8vh !important;
        color: #3b82f6 !important;
    }

    .focus-mode-active #fs-clock {
        display: none !important;
    }

    .cp-footer {
        padding: 12px 16px;
        border-top: 1px solid #e2e8f0;
        background: #fafafa;
        border-radius: 0 0 12px 12px;
    }

    /* 🌟 PREMUM EMPTY STATE (FOCUS MODE) 🌟 */
    .focus-mode-active #emptyState.is-visible {
        display: flex !important;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100vh;
        width: 100vw;
        position: fixed;
        top: 0;
        left: 0;
        background: radial-gradient(circle at center, #ffffff 0%, #f1f5f9 100%) !important;
        z-index: 999;
        margin: 0 !important;
        padding: 0 !important;
        animation: emptyEnter 1.2s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .empty-icon-wrap {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 4vh;
    }

    .empty-icon-main {
        font-size: 18vh;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        opacity: 0.9;
        position: relative;
        z-index: 2;
        filter: drop-shadow(0 10px 15px rgba(59, 130, 246, 0.2));
    }

    .empty-glow {
        position: absolute;
        width: 40vh;
        height: 40vh;
        background: #3b82f6;
        filter: blur(100px);
        opacity: 0.2;
        border-radius: 50%;
        z-index: 1;
    }

    /* 🌟 BREAKTIME STYLE 🌟 */
    .cp-card.breaktime-card {
        background: #fffbeb !important;
        border: 2px solid #f59e0b !important;
    }

    .cp-card.breaktime-card .cp-header {
        background: #fef3c7 !important;
        border-bottom-color: #f59e0b !important;
    }

    .cp-card.breaktime-card .seq-badge {
        background: #f59e0b !important;
        border-color: rgba(255, 255, 255, 0.5) !important;
        box-shadow: 0 0 20px rgba(245, 158, 11, 0.4) !important;
    }

    .focus-mode-active .cp-card.breaktime-card .cp-header {
        background: #fef3c7 !important;
        border-bottom: 8px solid #f59e0b !important;
    }

    .focus-mode-active .cp-card.breaktime-card .cp-header h5 {
        color: #92400e !important;
    }

    .focus-mode-active .cp-card.breaktime-card .cp-header .fs-machine-name {
        color: #b45309 !important;
    }

    .focus-mode-active .cp-card.breaktime-card .seq-badge {
        background: #f59e0b !important;
        color: white !important;
        border: none !important;
    }

    .break-content-placeholder {
        min-height: 20vh;
        border: 2px dashed #f59e0b;
        border-radius: 20px;
        background: rgba(245, 158, 11, 0.05);
        margin-bottom: 2vh;
        transition: all 0.3s ease;
    }

    /* 🔵 PROINT ERP STYLE 🔵 */
    .cp-card.proint-card {
        background: #f0f9ff !important;
        border: 2px solid #0ea5e9 !important;
    }

    .cp-card.proint-card .cp-header {
        background: #e0f2fe !important;
        border-bottom-color: #0ea5e9 !important;
    }

    .cp-card.proint-card .seq-badge {
        background: #0ea5e9 !important;
        border-color: rgba(255, 255, 255, 0.5) !important;
        box-shadow: 0 0 20px rgba(14, 165, 233, 0.4) !important;
    }

    .focus-mode-active .cp-card.proint-card .cp-header {
        background: #e0f2fe !important;
        border-bottom: 8px solid #0ea5e9 !important;
    }

    .focus-mode-active .cp-card.proint-card .cp-header h5 {
        color: #0369a1 !important;
    }

    .focus-mode-active .cp-card.proint-card .cp-header .fs-machine-name {
        color: #075985 !important;
    }

    /* Fix Date Visibility on Proint Cards */
    .cp-card.proint-card .tv-only-date .date-label {
        color: rgba(3, 105, 161, 0.6) !important;
    }

    .cp-card.proint-card .tv-only-date .date-val {
        color: #0369a1 !important;
    }

    .break-content-placeholder {
        min-height: 20vh;
        border: 2px dashed #f59e0b;
        border-radius: 20px;
        background: rgba(245, 158, 11, 0.05);
        margin-bottom: 2vh;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .focus-mode-active .break-content-placeholder {
        min-height: 35vh !important;
        border-width: 3px !important;
        background: #fffbeb !important;
        border-style: dashed !important;
        border-radius: 30px !important;
        margin-bottom: 0 !important;
    }

    .break-content-placeholder i {
        font-size: 5vh;
        color: #f59e0b;
        opacity: 0.8;
        margin-bottom: 1rem;
    }

    .focus-mode-active .break-content-placeholder i {
        font-size: 10vh !important;
    }

    .break-content-placeholder h2 {
        font-weight: 700;
        color: #92400e;
        letter-spacing: 2px;
        font-size: 4vh;
        margin-bottom: 0;
    }

    .focus-mode-active .break-content-placeholder h2 {
        font-size: 6vh !important;
    }

    .focus-mode-active #emptyState h4 {
        font-size: 8vh !important;
        color: #1e293b !important;
        font-weight: 800 !important;
        letter-spacing: -2px;
        margin-bottom: 2vh;
        text-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    }

    .focus-mode-active #emptyState p {
        font-size: 3.5vh !important;
        color: #64748b !important;
        font-weight: 500;
        max-width: 60vw;
    }

    @keyframes emptyEnter {
        from {
            opacity: 0;
            transform: translateY(20px) scale(0.98);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    @keyframes pulse-green {
        0% {
            transform: scale(0.95);
            box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.7);
            opacity: 0.8;
        }

        70% {
            transform: scale(1);
            box-shadow: 0 0 0 6px rgba(76, 175, 80, 0);
            opacity: 1;
        }

        100% {
            transform: scale(0.95);
            box-shadow: 0 0 0 0 rgba(76, 175, 80, 0);
            opacity: 0.8;
        }
    }

    /* 🌟 QUICK ACCESS PANEL (FULLSCREEN) 🌟 */
    .fs-quick-panel {
        position: fixed;
        top: 15vh;
        left: 3vw;
        width: 350px;
        background: white;
        border-radius: 16px;
        padding: 20px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        border: 1px solid #e2e8f0;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-20px) scale(0.95);
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        transform-origin: top left;
        z-index: 1050;
    }

    .fs-quick-panel.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0) scale(1);
    }

    .focus-mode-active .fs-quick-panel .btn-block {
        height: auto !important;
        font-size: 1rem !important;
        padding: 10px;
        border-radius: 8px !important;
    }
</style>

<div id="fs-clock">00:00:00</div>

<div class="wrapper">
    <!-- Quick Access Panel (Fullscreen) - Triggered by seq-badge -->
    <div id="fsQuickPanel" class="fs-quick-panel">
        <h5 class="font-weight-bold text-dark mb-3 outfit-font"><i class="fas fa-sliders-h text-primary mr-2"></i> Akses
            Cepat</h5>
        <div class="form-group mb-3">
            <label class="small font-weight-bold text-secondary">Cari Nomor CP</label>
            <input type="text" id="fsFilterSearch" class="form-control font-weight-bold"
                placeholder="Ketik untuk mencari...">
        </div>
        <div class="form-group mb-4">
            <label class="small font-weight-bold text-secondary">Mesin / Line</label>
            <select id="fsFilterMachine" class="form-control font-weight-bold">
                <option value="">-- Memuat Mesin... --</option>
            </select>
        </div>
        <button id="btnFsExit" class="btn btn-outline-danger btn-block font-weight-bold" style="border-radius: 8px;">
            <i class="fas fa-compress mr-1"></i> Keluar Fullscreen
        </button>
    </div>

    <!-- Fullscreen Transition Loading Overlay -->
    <div id="fs-loading-overlay">
        <div class="fs-spinner"></div>
        <p>Mempersiapkan Fullscreen...</p>
    </div>

    <div class="content-wrapper">
        <div class="content-header pb-2">
            <div class="container-fluid d-flex justify-content-between align-items-center">
                <h1 class="m-0 outfit-font" style="font-weight: 800; color: #1e293b; letter-spacing: -0.5px;">
                    <i class="fas fa-play-circle text-primary mr-2"></i>Proses<span style="color: #3b82f6;"> Penimbangan
                        & Pelarutan</span>
                    <div class="d-inline-flex flex-wrap align-items-center ml-2"
                        style="gap: 6px; vertical-align: middle;">
                        <?php if (empty($groupsArr)): ?>
                            <span class="badge badge-pill badge-secondary"
                                style="font-size: 0.8rem; font-weight: 600; padding: 4px 12px; background: rgba(148, 163, 184, 0.1); color: #64748b; border: 1px solid rgba(148, 163, 184, 0.2);">
                                <i class="fas fa-shield-alt mr-1"></i> Tidak Ada Grup
                            </span>
                        <?php else: ?>
                            <?php foreach ($groupsArr as $grp): ?>
                                <span class="badge badge-pill badge-primary"
                                    style="font-size: 0.8rem; font-weight: 600; padding: 4px 12px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2); white-space: nowrap;">
                                    <i class="fas fa-shield-alt mr-1"></i> <?= htmlspecialchars($grp) ?>
                                </span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </h1>
                <button id="btnFocusMode" class="btn btn-outline-secondary font-weight-bold"
                    style="border-radius: 8px;">
                    <i class="fas fa-expand mr-1"></i> Mode Fullscreen
                </button>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">

                <!-- Toolbar Area -->
                <div class="header-toolbar">
                    <div class="row align-items-center">
                        <div class="col-md-3 col-sm-6 mb-2 mb-sm-0">
                            <label class="font-weight-bold text-secondary mb-1" style="font-size: 0.85rem;">Tanggal
                                Perencanaan</label>
                            <input type="date" id="filterDate" class="form-control font-weight-bold"
                                value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2 mb-sm-0">
                            <label class="font-weight-bold text-secondary mb-1" style="font-size: 0.85rem;">Mesin /
                                Line</label>
                            <select id="filterMachine" class="form-control font-weight-bold">
                                <option value="">-- Memuat Mesin... --</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2 mb-sm-0">
                            <label class="font-weight-bold text-secondary mb-1" style="font-size: 0.85rem;">Cari
                                CP</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-white border-right-0"><i
                                            class="fas fa-search text-muted"></i></span>
                                </div>
                                <input type="text" id="filterSearch" class="form-control font-weight-bold border-left-0"
                                    placeholder="Pencarian...">
                            </div>
                        </div>
                        <div class="col-md-3 text-sm-right mt-3 mt-md-0">
                            <button id="btnRefresh" class="btn btn-primary font-weight-bold px-4"
                                style="border-radius: 8px;">
                                <i class="fas fa-sync-alt mr-1"></i> Refresh Antrean
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Notifikasi Kosong -->
                <div id="emptyState" class="text-center py-5" style="display: none;">
                    <div class="empty-icon-wrap">
                        <i class="fas fa-calendar-check empty-icon-main"></i>
                        <div class="empty-glow"></div>
                    </div>
                    <h4 class="outfit-font text-secondary font-weight-bold">Tidak Ada Antrean</h4>
                    <p class="text-muted">Semua jadwal CP telah selesai atau belum tersedia untuk saat ini.</p>
                </div>

                <!-- Container Antrean Kartu (Mobile Friendly) -->
                <div id="cardsWrap" class="row"></div>

            </div>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>

<script>
    $(document).ready(function () {
        var rawData = [];
        var wheelOptions = [];
        var delayOptions = [];
        var shiftOptions = [];
        var failOptions = [];
        var downtimeOptions = [];
        var authorizedRtgmsids = <?= json_encode($authorizedRtgmsids) ?>;
        var userGroupStr = <?= json_encode($userGroup) ?>;
        window.userGroupId = <?= (int) $userGroupId ?>;

        // 🕒 Helper: Format Detik ke Jam:Menit:Detik
        function formatDuration(seconds) {
            const h = Math.floor(seconds / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = seconds % 60;
            return (h > 0 ? h + 'j ' : '') + (m < 10 && h > 0 ? '0' : '') + m + 'm ' + (s < 10 ? '0' : '') + s + 's';
        }

        function fetchReferences() {
            $.get('api_aktual_paddry.php', { action: 'get_references' }, function (res) {
                if (res.success) {
                    wheelOptions = res.wheels;
                    delayOptions = res.delays;
                    shiftOptions = res.shifts;
                    failOptions = res.fails;
                    downtimeOptions = res.downtimes;
                }
            }, 'json');
        }

        var lastDataHash = "";
        var autoRefreshTimer = null;
        var manualFocusCpId = null;

        // Swipe Gestures for Fullscreen Mode
        var touchstartX = 0;
        var touchendX = 0;

        function handleCardSwipe() {
            if (!$('body').hasClass('focus-mode-active')) return;
            var $wrappers = $('#cardsWrap .cp-card-wrapper');
            if ($wrappers.length <= 1) return;

            var $current = $('#cardsWrap .cp-card-wrapper.is-focus');
            if ($current.length === 0) return;

            var threshold = 50;
            if (touchendX < touchstartX - threshold) {
                // Swipe Left -> Next
                var $next = $current.next('.cp-card-wrapper');
                if ($next.length === 0) $next = $wrappers.first(); // Loop to first
                manualFocusCpId = $next.data('cp-id');
                renderCards();
            } else if (touchendX > touchstartX + threshold) {
                // Swipe Right -> Prev
                var $prev = $current.prev('.cp-card-wrapper');
                if ($prev.length === 0) $prev = $wrappers.last(); // Loop to last
                manualFocusCpId = $prev.data('cp-id');
                renderCards();
            }
        }

        $(document).on('touchstart', '#cardsWrap', function (e) {
            touchstartX = e.changedTouches[0].screenX;
        });

        $(document).on('touchend', '#cardsWrap', function (e) {
            touchendX = e.changedTouches[0].screenX;
            handleCardSwipe();
        });

        function loadData(isSilent = false) {
            var date = $('#filterDate').val();

            if (!isSilent) {
                Swal.fire({
                    title: 'Memuat Jadwal...',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });
            } else {
                $('#syncStatus').show().find('i').addClass('fa-spin');
            }

            $.ajax({
                url: 'api_aktual_paddry.php',
                type: 'POST',
                data: { action: 'get_data', period_date: date },
                dataType: 'json',
                success: function (res) {
                    $('#syncStatus').find('i').removeClass('fa-spin');

                    if (res.message === 'Unauthorized') {
                        window.location.href = '/gg_app/login.php';
                        return;
                    }

                    if (res.success) {
                        window.wheelReqs = res.wheel_reqs || {};
                        window.lebarReqs = res.lebar_reqs || {};
                        window.breakReqs = res.break_reqs || {};
                        window.showRollback = res.show_rollback;
                        // Create a simple hash/string of data for comparison
                        var currentHash = JSON.stringify(res.data);

                        if (currentHash !== lastDataHash) {
                            rawData = res.data;
                            lastDataHash = currentHash;
                            populateMachines();
                            renderCards();
                        }

                        if (!isSilent) Swal.close();
                    } else {
                        if (!isSilent) Swal.fire('Gagal', res.message, 'error');
                    }
                },
                error: function () {
                    if (!isSilent) Swal.fire('Error', 'Gagal memuat API', 'error');
                }
            });
        }

        // Auto Refresh Logic (Every 30 seconds)
        function startAutoSync() {
            if (autoRefreshTimer) clearInterval(autoRefreshTimer);
            autoRefreshTimer = setInterval(function () {
                loadData(true); // Call silently
            }, 30000);
        }

        loadData(); // Initial load
        startAutoSync(); // Start monitoring

        function populateMachines() {
            var prevMachine = $('#filterMachine').val();
            var prevFsMachine = $('#fsFilterMachine').val();
            var machines = [];
            var fsMachines = [];

            rawData.forEach(function (item) {
                var exists = machines.find(m => m.id === item.machine_id);
                if (!exists && item.machine_id) {
                    machines.push({ id: item.machine_id, name: item.machine_name || item.machine_id });
                }

                // Check authorization for FS Machine
                var isAuthorized = true;
                var selectedStage = getSelectedStage(item);

                if (!item.is_breaktime) {
                    var cpRtgId = selectedStage && selectedStage.rtgmsid ? String(selectedStage.rtgmsid) : 'NONE';
                    if (!authorizedRtgmsids.includes(cpRtgId)) {
                        isAuthorized = false;
                    }
                } else {
                    var canShowBreak = false;
                    if (authorizedRtgmsids.length > 0) {
                        authorizedRtgmsids.forEach(function (rtgId) {
                            if (window.breakReqs && window.breakReqs[rtgId]) {
                                canShowBreak = true;
                            }
                        });
                    }
                    if (!canShowBreak) {
                        isAuthorized = false;
                    }
                }

                if (isAuthorized) {
                    var fsExists = fsMachines.find(m => m.id === item.machine_id);
                    if (!fsExists && item.machine_id) {
                        fsMachines.push({ id: item.machine_id, name: item.machine_name || item.machine_id });
                    }
                }
            });

            var hw = '<option value="">-- Semua Mesin --</option>';
            machines.forEach(function (m) {
                hw += '<option value="' + m.id + '">' + m.name + ' (' + m.id + ')</option>';
            });
            $('#filterMachine').html(hw);

            var fsHw = '<option value="">-- Semua Mesin --</option>';
            fsMachines.forEach(function (m) {
                fsHw += '<option value="' + m.id + '">' + m.name + ' (' + m.id + ')</option>';
            });
            $('#fsFilterMachine').html(fsHw);

            if (machines.length === 1) {
                $('#filterMachine').val(machines[0].id);
            } else if (machines.find(m => m.id === prevMachine)) {
                $('#filterMachine').val(prevMachine);
            }

            if (fsMachines.length === 1) {
                $('#fsFilterMachine').val(fsMachines[0].id);
            } else if (fsMachines.find(m => m.id === prevFsMachine)) {
                $('#fsFilterMachine').val(prevFsMachine);
            } else if (fsMachines.find(m => m.id === prevMachine)) {
                $('#fsFilterMachine').val(prevMachine);
            }
        }

        function formatVlot(val) {
            if (!val || val === '-') return '-';
            return parseFloat(val).toString();
        }

        function formatRelativeTime(minutes) {
            var absMin = Math.abs(minutes);
            if (absMin < 60) return absMin + ' mnt';

            var totalHours = Math.floor(absMin / 60);
            var remainingMin = Math.round(absMin % 60);

            if (totalHours < 24) {
                return totalHours + 'j' + (remainingMin > 0 ? ' ' + remainingMin + 'm' : '');
            }

            var days = Math.floor(totalHours / 24);
            var remainingHours = totalHours % 24;

            // Format: 26hr 4j (lebih ringkas tapi jelas)
            return days + 'hr' + (remainingHours > 0 ? ' ' + remainingHours + 'j' : '');
        }

        function formatVarianceHtml(varMin) {
            if (varMin === null || varMin === undefined) return '';
            var min = Math.round(varMin);
            if (min === 0) {
                return '<div class="mt-2 d-flex justify-content-center"><span class="variance-badge var-ontime">Tepat Waktu</span></div>';
            }

            var timeStr = formatRelativeTime(min);
            var html = '';
            if (min < 0) {
                html = '<span class="variance-badge var-early">- ' + timeStr + ' awal</span>';
            } else {
                html = '<span class="variance-badge var-late">+ ' + timeStr + ' lambat</span>';
            }
            return '<div class="mt-2 d-flex justify-content-center">' + html + '</div>';
        }

        // Global interval variable
        window.liveTimers = window.liveTimers || null;

        function getCurrentStage(cp) {
            if (!cp || !Array.isArray(cp.stages)) return null;
            return cp.stages.find(s => s.is_current) || cp.stages[0] || null;
        }

        function getSelectedStage(cp) {
            if (!cp || !Array.isArray(cp.stages) || cp.stages.length === 0) return null;
            if (cp.selected_stage_rtgmsid) {
                var found = cp.stages.find(s => String(s.rtgmsid) === String(cp.selected_stage_rtgmsid));
                if (found) return found;
            }
            return getCurrentStage(cp);
        }

        function stageStatusLabel(stage) {
            if (!stage) return 'MENUNGGU';

            // Prioritas cek timestamp jika status tidak sinkron
            if (stage.finish_at && stage.start_at) return 'SELESAI';
            if (stage.start_at && !stage.finish_at) return 'BERJALAN';

            if (stage.status === 'done') return 'SELESAI';
            if (stage.status === 'running') return 'BERJALAN';
            if (stage.status === 'failed') return 'FAIL';
            if (stage.status === 'locked') return 'TERKUNCI';
            return 'MENUNGGU';
        }

        function renderCards() {
            if (window.liveTimers) clearInterval(window.liveTimers);

            var selMac = $('#filterMachine').val();
            var searchVal = ($('#filterSearch').val() || '').toLowerCase().trim();

            if ($('body').hasClass('focus-mode-active')) {
                searchVal = ($('#fsFilterSearch').val() || '').toLowerCase().trim();
            }

            var filterData = rawData;
            if (selMac !== '') {
                filterData = rawData.filter(d => d.machine_id === selMac);
            }

            if (searchVal !== '') {
                filterData = filterData.filter(d =>
                    (d.cp_no || '').toLowerCase().includes(searchVal) ||
                    (d.buyer || '').toLowerCase().includes(searchVal) ||
                    (d.color || '').toLowerCase().includes(searchVal) ||
                    (d.machine_name || '').toLowerCase().includes(searchVal)
                );
            }

            var $wrap = $('#cardsWrap');
            $wrap.empty();

            if (manualFocusCpId !== null) {
                var exists = filterData.find(d => d.id == manualFocusCpId);
                if (!exists) manualFocusCpId = null;
            }

            // Priority Focus Logic (Initial load or Fullscreen transition)
            if (manualFocusCpId === null && filterData.length > 0) {
                // 1. Cari yang sedang BERJALAN & PUNYA OTORITAS
                var candidate = filterData.find(cp => {
                    var sStage = getSelectedStage(cp);
                    if (!sStage || sStage.status !== 'running') return false;
                    var cpRtgId = String(sStage.rtgmsid);
                    var isAuth = authorizedRtgmsids.includes(cpRtgId);
                    if (cp.is_breaktime) {
                        isAuth = authorizedRtgmsids.some(rid => window.breakReqs && window.breakReqs[rid]);
                    }
                    return isAuth;
                });

                // 2. Jika tidak ada, cari yang MENUNGGU & PUNYA OTORITAS
                if (!candidate) {
                    candidate = filterData.find(cp => {
                        var isCompleted = cp.total_stage_count > 0 && cp.completed_stage_count === cp.total_stage_count;
                        if (isCompleted || !!cp.has_failed_stage) return false;
                        var sStage = getSelectedStage(cp);
                        if (!sStage) return false;
                        var cpRtgId = String(sStage.rtgmsid);
                        var isAuth = authorizedRtgmsids.includes(cpRtgId);
                        if (cp.is_breaktime) {
                            isAuth = authorizedRtgmsids.some(rid => window.breakReqs && window.breakReqs[rid]);
                        }
                        return isAuth;
                    });
                }

                // 3. Jika masih tidak ada, biarkan tetap null agar sistem menampilkan pesan "Tugas Selesai" di fullscreen
                if (candidate) {
                    manualFocusCpId = candidate.id;
                }
            }

            var activeFound = false;

            filterData.forEach(function (cp) {
                var currentStage = getCurrentStage(cp);
                var selectedStage = getSelectedStage(cp);
                var isCompleted = cp.total_stage_count > 0 && cp.completed_stage_count === cp.total_stage_count;
                var hasFailed = !!cp.has_failed_stage;
                var isAuthorizedForPriority = true;
                if (!cp.is_breaktime) {
                    var cpRtgId = selectedStage && selectedStage.rtgmsid ? String(selectedStage.rtgmsid) : 'NONE';
                    if (!authorizedRtgmsids.includes(cpRtgId)) {
                        isAuthorizedForPriority = false;
                    }
                } else {
                    var canShowBreakForPriority = false;
                    if (authorizedRtgmsids.length > 0) {
                        authorizedRtgmsids.forEach(function (rtgId) {
                            if (window.breakReqs && window.breakReqs[rtgId]) {
                                canShowBreakForPriority = true;
                            }
                        });
                    }
                    if (!canShowBreakForPriority) {
                        isAuthorizedForPriority = false;
                    }
                }

                var isRunningStage = !!(selectedStage && selectedStage.status === 'running');
                var isActive = false;

                if (manualFocusCpId !== null && cp.id == manualFocusCpId) {
                    isActive = true;
                    activeFound = true;
                } else if (manualFocusCpId === null && !isCompleted && !hasFailed && currentStage && !activeFound && isAuthorizedForPriority) {
                    isActive = true;
                    activeFound = true;
                }

                // Clean machine name (remove codes in parentheses)
                var cleanMachineName = (cp.machine_name || '').replace(/\s*\(.*?\)\s*/g, '').trim();

                // Format displayed time to HH:mm (strip date and seconds)
                var dispStart = (selectedStage && selectedStage.start_at) ? selectedStage.start_at.substring(11, 16) : '-';
                var dispFinish = (selectedStage && selectedStage.finish_at) ? selectedStage.finish_at.substring(11, 16) : '-';

                var cardCls = '';
                var headCls = '';
                var focusCls = '';

                // Tata letak: Hanya 'BERJALAN' yang diletakkan di bawah (header-stack) karena ada timer.
                // 'MENUNGGU' dan 'SELESAI' tetap di kanan (header-row).
                var isRunning = !!(selectedStage && selectedStage.status === 'running');
                var headerLayoutCls = isRunning ? 'header-stack' : 'header-row';

                if (isActive) {
                    cardCls = 'active-card';
                    headCls = 'bg-active';
                    focusCls = 'is-focus';
                } else if (isCompleted || hasFailed) {
                    cardCls = 'completed-card'; headCls = 'bg-completed';
                }

                if (cp.is_breaktime) {
                    cardCls += ' breaktime-card';
                }

                if (selectedStage && selectedStage.origin_system === 'PROINT') {
                    cardCls += ' proint-card';
                }

                var isBreak = !!cp.is_breaktime || (selectedStage && selectedStage.rtgname === 'BREAKTIME');

                var hasStages = !cp.is_breaktime && Array.isArray(cp.stages) && cp.stages.length > 0;
                var stageStripHtml = '';
                if (hasStages && !cp.blocked_message) {
                    stageStripHtml = '<div class="stage-strip">';
                    cp.stages.forEach(function (stage) {
                        var pillCls = 'stage-pill ' + (stage.status || 'waiting');
                        if (selectedStage && String(stage.rtgmsid) === String(selectedStage.rtgmsid)) {
                            pillCls += ' active';
                        }
                        stageStripHtml += `
                            <button type="button" class="${pillCls} btn-stage-switch"
                                data-id="${cp.id}"
                                data-rtg="${stage.rtgmsid}">
                                Tahap ${stage.stage_no}
                            </button>
                        `;
                    });
                    stageStripHtml += '</div>';
                }

                var btnHtml = '';
                var canRenderAction = (!!selectedStage || cp.is_breaktime) && !isCompleted && !hasFailed && !cp.is_cross_cp_blocked && !cp.blocked_message;
                if (canRenderAction) {
                    var isStageRunning = selectedStage && (selectedStage.status === 'running' || (selectedStage.start_at && !selectedStage.finish_at));
                    var isStageDone = selectedStage && (selectedStage.status === 'done' || (selectedStage.start_at && selectedStage.finish_at));

                    if (isStageRunning) {
                        // Cek Otoritas Trustee untuk Controlling (Downtime/Stop)
                        var isAuthorizedControl = true;
                        var disableMsgControl = '';

                        if (!cp.is_breaktime) {
                            var cpRtgId = selectedStage && selectedStage.rtgmsid ? String(selectedStage.rtgmsid) : 'NONE';
                            if (!authorizedRtgmsids.includes(cpRtgId)) {
                                isAuthorizedControl = false;
                                disableMsgControl = 'Grup Anda tidak memiliki akses untuk mengontrol tahapan ini.';
                            }
                        } else {
                            var canShowBreakControl = false;
                            if (authorizedRtgmsids.length > 0) {
                                authorizedRtgmsids.forEach(function (rtgId) {
                                    if (window.breakReqs && window.breakReqs[rtgId]) {
                                        canShowBreakControl = true;
                                    }
                                });
                            }
                            if (!canShowBreakControl) {
                                isAuthorizedControl = false;
                                disableMsgControl = 'Grup Anda tidak memiliki akses untuk mengontrol Breaktime.';
                            }
                        }

                        if (!isAuthorizedControl) {
                            btnHtml = `
                                <div class="alert alert-warning text-center mb-0" style="border-radius: 10px; border: 1px solid #fde68a; background: #fffbeb; color: #92400e; font-weight: 800;">
                                    <i class="fas fa-spinner fa-spin mr-2"></i> SEDANG DIPROSES...
                                </div>
                                <div class="text-center mt-2"><small class="text-muted"><i class="fas fa-info-circle"></i> ${disableMsgControl}</small></div>
                            `;
                        } else {
                            var dtBtnLabel = selectedStage.active_downtime_id ? 'STOP DOWNTIME' : 'DOWNTIME';
                            var dtBtnClass = selectedStage.active_downtime_id ? 'btn-outline-danger' : 'btn-warning';
                            var stopLabel = isBreak ? 'Stop Breaktime' : 'STOP ' + (selectedStage ? selectedStage.rtgname.toUpperCase() : 'PROSES');

                            var downtimeBtnHtml = cp.is_breaktime ? '' : `
                                    <button class="btn ${dtBtnClass} font-weight-bold btn-downtime" 
                                        data-id="${cp.id}" 
                                        data-cp="${cp.cp_no}" 
                                        data-stage-id="${selectedStage.id}"
                                        data-rtg="${selectedStage.rtgmsid}"
                                        data-active-dt="${selectedStage.active_downtime_id || ''}">
                                        <i class="fas fa-pause mr-1"></i> ${dtBtnLabel}
                                        ${selectedStage.total_downtime_seconds > 0 ? `<br><span class="badge badge-light mt-1 card-dt-timer" data-total-base="${selectedStage.total_downtime_seconds}" data-is-running="${selectedStage.active_downtime_id ? '1' : '0'}">${formatDuration(selectedStage.total_downtime_seconds)}</span>` : ''}
                                    </button>
                            `;

                            btnHtml = `
                                <div class="btn-group w-100">
                                    ${downtimeBtnHtml}
                                    <button class="btn btn-danger font-weight-bold btn-stop" 
                                        data-id="${cp.id}" 
                                        data-cp="${cp.cp_no}"
                                        data-stage-id="${selectedStage.id}"
                                        data-rtg="${selectedStage.rtgmsid}"
                                        data-active-dt="${selectedStage.active_downtime_id || ''}"
                                        data-is-break="${isBreak ? 'true' : 'false'}">
                                        <i class="fas fa-stop mr-1"></i> ${stopLabel}
                                    </button>
                                </div>
                            `;
                        }
                    } else if (isStageDone) {
                        btnHtml = `
                            <div class="alert alert-success text-center mb-0" style="border-radius: 10px; border: 1px solid #86efac; background: #ecfdf5; color: #166534;">
                                <i class="fas fa-check-circle mr-1"></i> <strong>TAHAPAN INI SUDAH SELESAI</strong>
                            </div>
                        `;
                    } else {
                        var isAuthorized = true;
                        var disableMsg = '';

                        // Cek Otoritas Trustee (Kecuali Breaktime)
                        if (!cp.is_breaktime) {
                            var cpRtgId = selectedStage && selectedStage.rtgmsid ? String(selectedStage.rtgmsid) : 'NONE';
                            if (!authorizedRtgmsids.includes(cpRtgId)) {
                                isAuthorized = false;
                                disableMsg = `Otoritas Gagal: Tahapan Aktif (${selectedStage ? selectedStage.rtgname : '-'}) tidak sesuai dengan Otoritas Grup Anda`;
                            } else if (cp.blocked_message) {
                                isAuthorized = false;
                                disableMsg = cp.blocked_message;
                            } else if (!selectedStage || !cp.can_start || !selectedStage.is_current) {
                                isAuthorized = false;
                                disableMsg = 'Tahap ini belum siap dijalankan.';
                            }
                        } else {
                            var canShowBreak = false;
                            if (authorizedRtgmsids.length > 0) {
                                authorizedRtgmsids.forEach(function (rtgId) {
                                    if (window.breakReqs && window.breakReqs[rtgId]) {
                                        canShowBreak = true;
                                    }
                                });
                            }

                            if (!canShowBreak) {
                                isAuthorized = false;
                                disableMsg = 'Grup Anda tidak memiliki akses untuk mengeksekusi Breaktime.';
                            }
                        }

                        var startLabel = isBreak ? 'Start Breaktime' : 'START ' + (selectedStage ? selectedStage.rtgname.toUpperCase() : 'PROSES');
                        btnHtml = `
                            <button class="btn btn-success btn-block font-weight-bold btn-start" 
                                ${!isAuthorized ? 'disabled style="opacity: 0.6; cursor: not-allowed;" title="' + disableMsg + '"' : ''}
                                data-id="${cp.id}" 
                                data-cp="${cp.cp_no}" 
                                data-is-break="${cp.is_breaktime ? 'true' : 'false'}"
                                data-plan-start="${cp.rencana_start}"
                                data-stage-id="${selectedStage ? selectedStage.id : ''}"
                                data-rtg="${selectedStage ? selectedStage.rtgmsid : '999'}">
                                <i class="fas fa-play mr-1"></i> ${startLabel}
                            </button>
                        `;

                        if (!isAuthorized && disableMsg) {
                            btnHtml += `<div class="text-center mt-2"><small class="text-danger font-weight-bold"><i class="fas fa-exclamation-triangle"></i> ${disableMsg}</small></div>`;
                        }
                    }
                }

                var totalDtHtml = '';
                // (Informasi downtime dipindah ke modal sesuai request user)

                if (window.showRollback !== false && window.userGroupId === 1 && ((selectedStage && (selectedStage.start_at || selectedStage.finish_at)) || cp.aktual_start || cp.aktual_finish)) {
                    btnHtml += `
                        <button class="btn btn-sm btn-outline-danger mt-2 w-100 font-weight-bold btn-dev-rollback" data-id="${cp.id}">
                            <i class="fas fa-undo mr-1"></i> Tester: Rollback Stage Terakhir
                        </button>
                    `;
                }

                var statusHtml = '';
                var runningTimerId = '';

                if (hasFailed) {
                    statusHtml = '<span class="badge badge-danger px-2 py-1 status-badge-focus"><i class="fas fa-times-circle mr-1"></i> FAIL</span>';
                } else if (isCompleted || (selectedStage && (selectedStage.status === 'done' || (selectedStage.start_at && selectedStage.finish_at)))) {
                    statusHtml = '<span class="badge badge-success px-2 py-1 status-badge-focus"><i class="fas fa-check-circle mr-1"></i> SELESAI</span>';
                } else if (selectedStage && (selectedStage.status === 'running' || (selectedStage.start_at && !selectedStage.finish_at))) {
                    runningTimerId = 'timer_' + cp.id + '_' + selectedStage.rtgmsid;

                    if (selectedStage.active_downtime_id) {
                        statusHtml = '<span class="badge badge-danger px-2 py-1 status-badge-focus pulse-red"><i class="fas fa-pause-circle mr-1"></i> DOWNTIME: ' + selectedStage.active_downtime_name + '</span>';
                    } else {
                        statusHtml = '<span class="badge badge-warning px-2 py-1 status-badge-focus"><i class="fas fa-spinner fa-spin mr-1"></i> BERJALAN: <span id="' + runningTimerId + '" data-start="' + selectedStage.start_at + '">00:00:00</span></span>';
                    }
                } else {
                    var stageBadgeCls = 'badge-secondary';
                    if (selectedStage) {
                        if (selectedStage.status === 'done') stageBadgeCls = 'badge-success';
                        else if (selectedStage.status === 'running') stageBadgeCls = 'badge-warning';
                        else if (selectedStage.status === 'failed') stageBadgeCls = 'badge-danger';
                    }

                    if (selectedStage && selectedStage.active_downtime_id) {
                        statusHtml = `<span class="badge badge-danger px-2 py-1 status-badge-focus pulse-red">
                            <i class="fas fa-pause-circle mr-1"></i> DOWNTIME: ${selectedStage.active_downtime_name}
                        </span>`;
                    } else {
                        statusHtml = `<span class="badge ${stageBadgeCls} px-2 py-1 status-badge-focus">
                            ${selectedStage && selectedStage.status === 'done' ? '<i class="fas fa-check-circle mr-1"></i> ' : ''}
                            ${stageStatusLabel(selectedStage)}
                        </span>`;
                    }
                }

                // Calculate stage specific variance
                var isPelarutan = selectedStage && selectedStage.rtgname && selectedStage.rtgname.toUpperCase().includes('PELARUTAN');

                var stageVarianceStartHtml = '';
                var stagePlanStart = '-';
                if (selectedStage && selectedStage.plan_start) {
                    stagePlanStart = selectedStage.plan_start;
                } else if (!isPelarutan) {
                    stagePlanStart = cp.rencana_start || '-';
                }

                if (dispStart !== '-' && stagePlanStart !== '-' && cp.period_date) {
                    // Gunakan full timestamp dari ERP jika ada agar akurat lintas hari
                    var sA = (selectedStage && selectedStage.start_at) ? new Date(selectedStage.start_at.replace(' ', 'T')) : new Date(cp.period_date + 'T' + dispStart + ':00');
                    var sP = new Date(cp.period_date + 'T' + stagePlanStart + ':00');
                    var sVar = (sA.getTime() - sP.getTime()) / 60000;
                    stageVarianceStartHtml = formatVarianceHtml(sVar);
                }

                var stageVarianceFinishHtml = '';
                var stagePlanFinish = '-';
                if (selectedStage && selectedStage.plan_finish) {
                    stagePlanFinish = selectedStage.plan_finish;
                } else if (!isPelarutan) {
                    stagePlanFinish = cp.rencana_finish || '-';
                }

                if (dispFinish !== '-' && stagePlanFinish !== '-' && cp.period_date) {
                    // Gunakan full timestamp dari ERP jika ada agar akurat lintas hari
                    var fA = (selectedStage && selectedStage.finish_at) ? new Date(selectedStage.finish_at.replace(' ', 'T')) : new Date(cp.period_date + 'T' + dispFinish + ':00');
                    var fP = new Date(cp.period_date + 'T' + stagePlanFinish + ':00');
                    var fVar = (fA.getTime() - fP.getTime()) / 60000;
                    stageVarianceFinishHtml = formatVarianceHtml(fVar);
                }
                var formattedPeriodDate = '-';
                if (cp.period_date) {
                    var dateParts = cp.period_date.split('-');
                    if (dateParts.length === 3) {
                        formattedPeriodDate = dateParts[2] + '/' + dateParts[1] + '/' + dateParts[0].substring(2, 4);
                    } else {
                        formattedPeriodDate = cp.period_date;
                    }
                }

                var html = `
                            <div class="col-md-6 col-lg-4 cp-card-wrapper ${focusCls}" data-cp-id="${cp.id}">
                                <div class="cp-card ${cardCls}">
                                    <div class="cp-header ${headCls} ${headerLayoutCls}">
                                        <div class="d-flex align-items-center flex-grow-1" style="min-width: 0;">
                                            <div class="seq-badge mr-3 flex-shrink-0" title="Klik untuk Akses Cepat">${cp.seq_no}</div>
                                            <div style="min-width: 0;">
                                                <h5 class="m-0 font-weight-bold outfit-font text-dark">${cp.cp_no}</h5>
                                                <div class="text-muted small fs-machine-name">${cleanMachineName}</div>
                                            </div>
                                        </div>
                                        ${isBreak ? '' : `
                                        <div class="tv-only-date px-3">
                                            <div class="date-label"><i class="far fa-calendar-alt mr-1"></i>TGL RENCANA</div>
                                            <div class="date-val">${formattedPeriodDate}</div>
                                        </div>
                                        `}
                                        <div class="status-container-responsive">${statusHtml}</div>
                                    </div>
                                    <div class="cp-body ${!isBreak && (!hasStages || cp.blocked_message) ? 'body-no-stages' : ''}">
                                        ${isBreak ? `
                                            <div class="break-content-placeholder">
                                                <i class="fas fa-coffee"></i>
                                                <h2 class="font-weight-bold outfit-font mb-0">BREAKTIME</h2>
                                            </div>
                                        ` : `
                                            ${stageStripHtml}
                                            <div class="stage-title">
                                                <div>
                                                    <div class="text-muted small mb-1">Routing Aktif/Terpilih</div>
                                                    <div class="stage-name-main">${cp.blocked_message ? cp.routing_name : ((selectedStage && selectedStage.rtgname) || cp.routing_name || '-')}</div>
                                                </div>
                                                ${cp.total_stage_count > 0 && !cp.blocked_message ? `
                                                    <div class="stage-progress-badge">
                                                        <i class="fas fa-layer-group"></i> ${cp.completed_stage_count}/${cp.total_stage_count}
                                                    </div>
                                                ` : ''}
                                            </div>
                                            <div class="data-group">
                                                <div class="data-row">
                                                    <div class="data-label">Label:</div>
                                                    <div class="data-value">${cp.label || '-'}</div>
                                                </div>
                                                <div class="data-row">
                                                    <div class="data-label">Cust Color:</div>
                                                    <div class="data-value text-primary font-weight-bold">${cp.warna || '-'}</div>
                                                </div>
                                                <div class="data-row">
                                                    <div class="data-label">Vlot Resep:</div>
                                                    <div class="data-value text-dark font-weight-bold">${formatVlot(cp.vlot_resep)}</div>
                                                </div>
                                                <div class="data-row">
                                                    <div class="data-label">Kode Lab:</div>
                                                    <div class="data-value fs-material-val">${cp.kode_lab || '-'}</div>
                                                </div>
                                                <div class="data-row">
                                                    <div class="data-label">Quantity:</div>
                                                    <div class="data-value font-weight-bold">${cp.qty}</div>
                                                </div>
                                                <div class="data-row">
                                                    <div class="data-label">Routing:</div>
                                                    <div class="data-value font-weight-bold text-danger">${cp.blocked_message ? cp.routing_name : ((selectedStage && selectedStage.rtgname) || cp.routing_name || '-')}</div>
                                                </div>
                                            </div>
                                        `}
                                        ${totalDtHtml}
                                        <div class="time-group row">
                                            <div class="col-12 time-box-clock">
                                                <div class="time-box">
                                                    <span class="time-label">JAM SEKARANG</span>
                                                    <div class="time-val fs-live-clock">00:00:00</div>
                                                </div>
                                            </div>
                                            <div class="col-6 pr-1">
                                                <div class="time-box">
                                                    <span class="time-label">Waktu Start</span>
                                                    <div class="time-val text-success">${dispStart}</div>
                                                    <div class="plan-sub">Plan: ${stagePlanStart}</div>
                                                    ${stageVarianceStartHtml}
                                                </div>
                                            </div>
                                            <div class="col-6 pl-1">
                                                <div class="time-box">
                                                    <span class="time-label">Waktu Finish</span>
                                                    <div class="time-val text-danger">${dispFinish}</div>
                                                    <div class="plan-sub">Plan: ${stagePlanFinish}</div>
                                                    ${stageVarianceFinishHtml}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    ${btnHtml ? `<div class="cp-footer">${btnHtml}</div>` : ''}
                                </div>
                            </div>
                        `;
                $wrap.append(html);
            });

            // 🌟 LOGIC EMPTY STATE / FINISHED 🌟
            if ($('body').hasClass('focus-mode-active') && !activeFound) {
                var emptyTaskHtml = `
                    <div class="col-12 cp-card-wrapper is-focus">
                        <div class="cp-card active-card" style="justify-content: center; align-items: center; background: #f8fafc !important; height: 100vh; height: 100dvh;">
                            <div class="text-center p-4">
                                <div class="mb-4">
                                    <i class="fas fa-check-circle text-success" style="font-size: 18vh; filter: drop-shadow(0 10px 15px rgba(34, 197, 94, 0.2));"></i>
                                </div>
                                <h1 class="outfit-font font-weight-bold text-dark mb-2" style="font-size: 7vh; letter-spacing: -1px;">TUGAS SELESAI</h1>
                                <p class="text-muted mb-4" style="font-size: 3.5vh; max-width: 800px; margin: 0 auto;">Tidak ada antrean tugas aktif untuk grup Anda saat ini. <br>Semua jadwal telah selesai atau sedang dikerjakan oleh grup lain.</p>
                                <button class="btn btn-primary btn-lg mt-2 px-5 font-weight-bold shadow-lg" onclick="location.reload()" style="border-radius: 15px; font-size: 2.5vh; padding: 1.5vh 4vh;">
                                    <i class="fas fa-sync-alt mr-2"></i> Periksa Data Terbaru
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                $wrap.append(emptyTaskHtml);
            }

            var showEmpty = (filterData.length === 0);
            if ($('body').hasClass('focus-mode-active')) {
                // Di mode fokus, jika tidak ada antrean AKTIF, tampilkan empty state
                if (!activeFound) showEmpty = true;
            }

            if (showEmpty) {
                $('#emptyState').addClass('is-visible').show();
                if (filterData.length > 0 && !activeFound) {
                    $('#emptyState h4').text('Tugas Selesai');
                    $('#emptyState p').text('Semua jadwal CP telah selesai diproses untuk mesin ini.');
                } else {
                    $('#emptyState h4').text('Tidak Ada Antrean');
                    $('#emptyState p').text('Belum tersedia jadwal CP untuk mesin ini.');
                }
            } else {
                $('#emptyState').removeClass('is-visible').hide();
            }

            // Hiding unwanted info in normal mode but keeping it for focus
            if (!$('body').hasClass('focus-mode-active')) {
                $('.fs-machine-name').hide();
            }

            // Start Live Timer for running processes
            window.liveTimers = setInterval(function () {
                // System Clock at bottom left
                var today = new Date();
                $('#fs-clock').text(today.getHours().toString().padStart(2, '0') + ':' + today.getMinutes().toString().padStart(2, '0') + ':' + today.getSeconds().toString().padStart(2, '0'));

                // 1. Timer Proses Produksi (Berjalan)
                $('[id^="timer_"]').each(function () {
                    var startStr = $(this).data('start'); // Format: YYYY-MM-DD HH:mm:ss
                    if (!startStr) return;

                    // Ganti spasi dengan T agar formatnya ISO-like dan stabil di semua browser
                    var isoStr = startStr.replace(' ', 'T');
                    var sTime = new Date(isoStr);

                    var diffMs = today - sTime;
                    if (diffMs < 0) diffMs = 0;

                    var h = Math.floor(diffMs / 3600000).toString().padStart(2, '0');
                    var m = Math.floor((diffMs % 3600000) / 60000).toString().padStart(2, '0');
                    var s = Math.floor((diffMs % 60000) / 1000).toString().padStart(2, '0');
                    $(this).text(h + ':' + m + ':' + s);
                });

                // 2. Timer Downtime di Kartu (Total Akumulasi)
                $('.card-dt-timer').each(function () {
                    var isRunning = $(this).data('is-running') == '1';
                    if (!isRunning) return;

                    var baseSecs = parseInt($(this).data('total-base')) || 0;
                    baseSecs += 1;

                    $(this).data('total-base', baseSecs);
                    $(this).text(formatDuration(baseSecs));
                });
            }, 1000);
        }

        function updateClocks() {
            var now = new Date();
            var h = String(now.getHours()).padStart(2, '0');
            var m = String(now.getMinutes()).padStart(2, '0');
            var s = String(now.getSeconds()).padStart(2, '0');
            $('.fs-live-clock').text(h + ':' + m + ':' + s);
        }
        setInterval(updateClocks, 1000);
        updateClocks();

        function showFsLoading() {
            $('#fs-loading-overlay').addClass('show');
        }
        function hideFsLoading() {
            $('#fs-loading-overlay').removeClass('show');
        }

        function toggleFocusMode(specificId = null) {
            var entering = !$('body').hasClass('focus-mode-active');
            $('body').toggleClass('focus-mode-active');

            if (entering) {
                // Sync search value from main to fs
                $('#fsFilterSearch').val($('#filterSearch').val());
                manualFocusCpId = specificId;
                var elem = document.documentElement;
                var fsSupported = !!(elem.requestFullscreen || elem.webkitRequestFullscreen || elem.msRequestFullscreen);

                if (fsSupported) {
                    // Show loading overlay to prevent rendering with old viewport dimensions
                    showFsLoading();
                    // Request fullscreen; rendering will happen AFTER fullscreenchange event fires
                    var fsPromise = elem.requestFullscreen
                        ? elem.requestFullscreen()
                        : (elem.webkitRequestFullscreen
                            ? (elem.webkitRequestFullscreen() || Promise.resolve())
                            : Promise.resolve());

                    // Force Landscape on Mobile
                    if (fsPromise && typeof fsPromise.then === 'function') {
                        fsPromise.then(function () {
                            if (screen.orientation && screen.orientation.lock) {
                                screen.orientation.lock('landscape').catch(function (e) {
                                    console.warn("[DEBUG] Orientation lock failed:", e);
                                });
                            }
                        });
                    } else {
                        // Fallback if promise not supported
                        if (screen.orientation && screen.orientation.lock) {
                            screen.orientation.lock('landscape').catch(e => { });
                        }
                    }

                    // Fallback: if fullscreenchange doesn't fire within 800ms, render anyway
                    var fsTimeout = setTimeout(function () {
                        hideFsLoading();
                        renderCards();
                    }, 800);

                    $(document).one('fullscreenchange webkitfullscreenchange', function () {
                        clearTimeout(fsTimeout);
                        // Menambah delay agar viewport di mobile benar-benar stabil setelah transisi animasi fullscreen
                        setTimeout(function () {
                            hideFsLoading();
                            renderCards();
                        }, 400);
                    });
                } else {
                    // No fullscreen API (older browser) — render immediately
                    renderCards();
                }
            } else {
                // Sync search value from fs back to main
                $('#filterSearch').val($('#fsFilterSearch').val());
                manualFocusCpId = null;
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }

                if (screen.orientation && screen.orientation.unlock) {
                    screen.orientation.unlock();
                }
                $('#btnExitFocus').remove();
                $('#fsQuickPanel').removeClass('show');
                renderCards();
            }
        }

        // Catch native fullscreen exit (e.g. user pressed F11 or Esc natively)
        $(document).on('fullscreenchange webkitfullscreenchange mozfullscreenchange MSFullscreenChange', function () {
            if (!document.fullscreenElement && !document.webkitIsFullScreen && !document.mozFullScreen && !document.msFullscreenElement) {
                if ($('body').hasClass('focus-mode-active')) {
                    $('body').removeClass('focus-mode-active');
                    $('#fsQuickPanel').removeClass('show');
                    manualFocusCpId = null; // Reset manual focus when natively exiting
                    if (screen.orientation && screen.orientation.unlock) {
                        screen.orientation.unlock();
                    }
                    renderCards();
                }
            }
        });

        $(document).on('keydown', function (e) {
            if (e.key === "Escape" && $('body').hasClass('focus-mode-active')) {
                toggleFocusMode();
            }
        });

        $('#btnFocusMode').click(toggleFocusMode);

        // Quick Access Menu (Fullscreen) - Triggered by seq-badge
        $(document).on('click', '.seq-badge', function (e) {
            if ($('body').hasClass('focus-mode-active')) {
                e.stopPropagation();
                $('#fsQuickPanel').toggleClass('show');

                // Set initial values only when opening
                if ($('#fsQuickPanel').hasClass('show')) {
                    $('#fsFilterMachine').val($('#filterMachine').val());
                }
            }
        });

        // Close quick access panel when clicking outside
        $(document).on('click', function (e) {
            if (!$(e.target).closest('#fsQuickPanel').length && !$(e.target).closest('.seq-badge').length) {
                $('#fsQuickPanel').removeClass('show');
            }
        });

        $('#filterSearch').on('input', renderCards);
        $('#fsFilterSearch').on('input', function () {
            // Re-render immediately when typing in FS
            renderCards();
        });

        $('#fsFilterMachine').on('change', function () {
            var selectedMachine = $(this).val();
            if (selectedMachine) {
                // Apply value to main filter and render immediately (client-side filter)
                $('#filterMachine').val(selectedMachine).trigger('change');
                $('#fsQuickPanel').removeClass('show');
            }
        });

        // Double Click to Fullscreen
        $(document).on('dblclick', '.cp-card', function (e) {
            if (!$('body').hasClass('focus-mode-active')) {
                e.preventDefault();
                e.stopPropagation();
                var id = $(this).closest('.cp-card-wrapper').data('cp-id');
                toggleFocusMode(id);
            }
        });

        $('#btnFsExit').on('click', function () {
            if ($('body').hasClass('focus-mode-active')) {
                toggleFocusMode();
            }
            $('#fsQuickPanel').removeClass('show');
        });

        $(document).on('click', '.btn-stage-switch', function () {
            var id = $(this).data('id');
            var rtgmsid = $(this).data('rtg');

            if ($('body').hasClass('focus-mode-active')) {
                manualFocusCpId = id;
            }

            rawData = rawData.map(function (item) {
                if (String(item.id) === String(id)) {
                    item.selected_stage_rtgmsid = String(rtgmsid);
                }
                return item;
            });
            renderCards();
        });

        // Aksi Klik Start


        // Aksi Klik Start
        $(document).on('click', '.btn-start', function () {
            var id = $(this).data('id');
            var cpno = $(this).data('cp');
            var planStart = $(this).data('plan-start');
            var isBreak = $(this).data('is-break') === true;
            var rtgmsid = $(this).data('rtg');
            var btn = $(this);

            if (isBreak) {
                // Bypass Nomor Roda untuk Breaktime, langsung ke Shift
                proceedToShiftDialog(cpno, '', '', id, rtgmsid, planStart);
                return;
            }

            var requireWheel = true;
            if (rtgmsid && window.wheelReqs && window.wheelReqs[rtgmsid] !== undefined) {
                requireWheel = window.wheelReqs[rtgmsid];
            }

            if (!requireWheel) {
                proceedToShiftDialog(cpno, '', '', id, rtgmsid, planStart);
                return;
            }

            // Step 1: Input Nomor Roda
            let wheelHtml = '<select id="swalWheel" class="form-control select2"><option value="">-- Pilih Nomor Roda --</option>';
            wheelOptions.forEach(w => {
                wheelHtml += `<option value="${w.no}" data-msid="${w.id}">${w.no}</option>`;
            });
            wheelHtml += '</select>';

            Swal.fire({
                title: 'Nomor Roda',
                html: `Pilih Nomor Roda untuk <b class="text-primary">${cpno}</b>:<br><br>${wheelHtml}`,
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Lanjut',
                cancelButtonText: 'Batal',
                didOpen: () => {
                    $('#swalWheel').select2({
                        theme: 'bootstrap4',
                        dropdownParent: Swal.getPopup(),
                        width: '100%'
                    });
                },
                preConfirm: () => {
                    const wheelSelect = document.getElementById('swalWheel');
                    const wheel = wheelSelect.value;
                    const wheelmsid = wheelSelect.options[wheelSelect.selectedIndex].getAttribute('data-msid');
                    if (!wheel) {
                        Swal.showValidationMessage('Anda harus memilih Nomor Roda!');
                    }
                    return { wheel: wheel, wheelmsid: wheelmsid };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    proceedToShiftDialog(cpno, result.value.wheel, result.value.wheelmsid, id, rtgmsid, planStart);
                }
            });
        });

        function proceedToShiftDialog(cpno, wheelNo, wheelMsid, id, rtgmsid, planStart) {
            let shiftHtml = '<select id="swalShift" class="form-control select2"><option value="">-- Pilih Shift --</option>';
            shiftOptions.forEach(s => {
                shiftHtml += `<option value="${s.id}" data-name="${s.name}">${s.name} - ${s.group}</option>`;
            });
            shiftHtml += '</select>';

            Swal.fire({
                title: 'Pilih Shift',
                html: `Pilih Shift untuk <b class="text-primary">${cpno}</b>:<br><br>${shiftHtml}`,
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Lanjut',
                cancelButtonText: 'Batal',
                didOpen: () => {
                    $('#swalShift').select2({
                        theme: 'bootstrap4',
                        dropdownParent: Swal.getPopup(),
                        width: '100%'
                    });
                },
                preConfirm: () => {
                    const shiftSelect = document.getElementById('swalShift');
                    const shiftId = shiftSelect.value;
                    const shiftName = shiftSelect.options[shiftSelect.selectedIndex].getAttribute('data-name');
                    if (!shiftId) {
                        Swal.showValidationMessage('Anda harus memilih Shift!');
                    }
                    return { shiftId: shiftId, shiftName: shiftName };
                }
            }).then((sResult) => {
                if (sResult.isConfirmed) {
                    const selectedShiftId = sResult.value.shiftId;
                    const selectedShiftName = sResult.value.shiftName;

                    performAction('start', id, rtgmsid, wheelNo, '', selectedShiftId, selectedShiftName, '', '', '', '', '', '', '', wheelMsid);
                }
            });
        }




        let downtimeInterval;

        // Aksi Klik Downtime
        $(document).on('click', '.btn-downtime', function () {
            const id = $(this).data('id');
            const cpno = $(this).data('cp');
            const stageId = $(this).data('stage-id');

            function openDowntimeModal() {
                if (downtimeInterval) clearInterval(downtimeInterval);

                Swal.fire({
                    title: 'Memuat...',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                $.get('api_aktual_paddry.php', { action: 'get_downtime_history', stage_id: stageId }, function (res) {
                    Swal.close();
                    if (!res.success) {
                        Swal.fire('Error', res.message, 'error');
                        return;
                    }

                    const history = res.data;
                    let initialTotalSecs = res.total_secs;

                    let historyRows = '';
                    if (history.length === 0) {
                        historyRows = '<tr><td colspan="5" class="text-center py-5 text-muted small" style="font-style: italic; opacity: 0.6;">Belum ada riwayat aktivitas</td></tr>';
                    } else {
                        history.forEach(h => {
                            const isRunning = !h.stop_fmt;
                            const statusBadge = isRunning
                                ? `<span class="badge badge-danger pulse-red px-2 py-1" style="font-size: 0.65rem; border-radius: 4px;"><i class="fas fa-sync-alt fa-spin mr-1"></i> RUNNING</span>`
                                : `<span class="text-muted" style="font-size: 0.75rem;">${h.stop_fmt}</span>`;

                            historyRows += `
                                <tr style="border-bottom: 1px solid #f8fafc;">
                                    <td class="align-middle py-2 pl-3">
                                        <div class="font-weight-bold text-dark mb-0" style="font-size: 0.85rem;">${h.kd_downtime}</div>
                                        <div class="small text-muted text-truncate" style="max-width: 140px;">${h.nm_downtime}</div>
                                    </td>
                                    <td class="text-center align-middle" style="font-size: 0.75rem; color: #6c757d;">${h.start_fmt}</td>
                                    <td class="text-center align-middle">${statusBadge}</td>
                                    <td class="text-right align-middle font-weight-bold text-primary duration-cell" 
                                        data-initial="${h.duration_secs}" 
                                        data-running="${isRunning ? '1' : '0'}"
                                        style="font-size: 0.9rem;">
                                        ${formatDuration(h.duration_secs)}
                                    </td>
                                    <td class="text-center align-middle pr-3">
                                        ${isRunning ? `<button class="btn btn-sm btn-danger btn-stop-dt-inner font-weight-bold" data-dtid="${h.id}" title="Stop Sesi Ini"><i class="fas fa-stop mr-1"></i> STOP</button>` : '<i class="fas fa-check-circle text-success opacity-40"></i>'}
                                    </td>
                                </tr>
                            `;
                        });
                    }

                    let dtSelectHtml = '<select id="innerDtCode" class="form-control select2"><option value="">-- Pilih Alasan --</option>';
                    downtimeOptions.forEach(d => {
                        dtSelectHtml += `<option value="${d.code}" data-name="${d.name}" data-msid="${d.id}">${d.code} - ${d.name}</option>`;
                    });
                    dtSelectHtml += '</select>';

                    const isAnyRunning = history.some(h => !h.stop_fmt);

                    let modalHtml = `
                        <div class="text-left" style="color: #333; overflow: hidden;">
                            <div class="row no-gutters">
                                <!-- LEFT COLUMN: Timer & History -->
                                <div class="col-md-7 pr-md-3" style="border-right: 1px solid #dee2e6;">
                                    <!-- Compact Timer Card -->
                                    <div class="position-relative mb-3 overflow-hidden" style="background: #343a40; border-radius: 8px; padding: 15px 20px;">
                                        <div class="position-absolute" style="right: -5px; bottom: -15px; opacity: 0.1; font-size: 5rem; color: #fff;">
                                            <i class="fas fa-stopwatch"></i>
                                        </div>
                                        <div class="position-relative z-index-1">
                                            <div class="text-warning small font-weight-bold text-uppercase mb-1" style="letter-spacing: 1px;">Total Durasi Downtime</div>
                                            <div class="h2 m-0 font-weight-bold text-white" id="modalTotalDuration" data-total="${initialTotalSecs}">
                                                ${formatDuration(initialTotalSecs)}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="px-1 mb-2 d-flex justify-content-between align-items-center">
                                        <h6 class="m-0 font-weight-bold text-dark"><i class="fas fa-list-ul mr-2 text-primary"></i>Riwayat Sesi</h6>
                                        <span class="badge badge-secondary" style="font-size: 0.7rem;">${history.length} Entri</span>
                                    </div>
                                    
                                    <div class="table-responsive" style="height: 280px; border: 1px solid #dee2e6; border-radius: 4px;">
                                        <table class="table table-hover table-sm mb-0">
                                            <thead class="bg-light" style="position: sticky; top: 0; z-index: 10;">
                                                <tr class="text-uppercase text-muted" style="font-size: 0.7rem; border-bottom: 2px solid #dee2e6;">
                                                    <th class="py-2 pl-3 border-0">Alasan</th>
                                                    <th class="text-center py-2 border-0">Mulai</th>
                                                    <th class="text-center py-2 border-0">Selesai</th>
                                                    <th class="text-right py-2 border-0">Durasi</th>
                                                    <th class="text-center py-2 pr-3 border-0">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody id="dtHistoryBody">${historyRows}</tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- RIGHT COLUMN: Form Start -->
                                <div class="col-md-5 pl-md-3 d-flex flex-column">
                                    <div style="border: 1px solid #dee2e6; border-radius: 6px; background: #fff; height: 100%;">
                                        <div class="p-3" style="background: #f8f9fa; border-bottom: 1px solid #dee2e6; border-radius: 6px 6px 0 0;">
                                            <h6 class="m-0 font-weight-bold text-dark"><i class="fas fa-plus-circle mr-2 text-success"></i>Mulai Downtime Baru</h6>
                                        </div>
                                        <div class="p-3">
                                            ${isAnyRunning ? `
                                                <div class="alert alert-warning mb-3" style="font-size: 0.85rem; border-radius: 4px; border: 1px solid #ffeeba;">
                                                    <i class="fas fa-exclamation-triangle mr-2"></i> <b>Sesi Aktif Ditemukan!</b><br>
                                                    Hentikan sesi yang sedang berjalan sebelum memulai sesi baru.
                                                </div>
                                            ` : ''}
                                            <div class="form-group mb-3" ${isAnyRunning ? 'style="opacity: 0.5; pointer-events: none;"' : ''}>
                                                <label class="font-weight-bold text-dark small">Alasan Downtime <span class="text-danger">*</span></label>
                                                ${dtSelectHtml}
                                            </div>
                                            <div class="form-group mb-4" ${isAnyRunning ? 'style="opacity: 0.5; pointer-events: none;"' : ''}>
                                                <label class="font-weight-bold text-dark small">Keterangan Tambahan</label>
                                                <textarea id="innerDtKet" class="form-control" rows="4" placeholder="Detail keterangan... (opsional)" style="border-radius: 4px; resize: none;"></textarea>
                                            </div>
                                            <button id="btnStartDtInner" class="btn btn-success btn-block font-weight-bold" 
                                                style="padding: 12px; font-size: 1rem; border-radius: 4px;"
                                                ${isAnyRunning ? 'disabled' : ''}>
                                                <i class="fas fa-play mr-2"></i> ${isAnyRunning ? 'SESI SEDANG BERJALAN' : 'MULAI SEKARANG'}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;

                    Swal.fire({
                        title: `
                            <div class="d-flex justify-content-between align-items-center w-100" style="border-bottom: 2px solid #dee2e6; padding-bottom: 15px; margin-top: -10px;">
                                <h5 class="m-0 font-weight-bold text-dark text-left" style="font-size: 1.25rem;">
                                    <i class="fas fa-tools text-warning mr-2"></i> PANEL KONTROL DOWNTIME
                                </h5>
                                <span class="badge badge-primary px-3 py-2 shadow-sm" style="font-size: 1.1rem; border-radius: 4px;">CP: ${cpno}</span>
                            </div>
                        `,
                        html: modalHtml,
                        width: '950px',
                        showConfirmButton: false,
                        showCancelButton: true,
                        cancelButtonText: '<i class="fas fa-times mr-1"></i> TUTUP PANEL',
                        cancelButtonColor: '#6c757d',
                        padding: '1.5rem',
                        willClose: () => {
                            if (downtimeInterval) clearInterval(downtimeInterval);
                        },
                        didOpen: () => {
                            $('#innerDtCode').select2({
                                theme: 'bootstrap4',
                                dropdownParent: Swal.getPopup(),
                                width: '100%'
                            });

                            // 🕒 LIVE TIMER LOGIC
                            downtimeInterval = setInterval(() => {
                                let anyRunning = false;
                                let newTotal = 0;

                                $('.duration-cell').each(function () {
                                    let baseSecs = parseInt($(this).data('initial'));
                                    let isRunning = $(this).data('running') == '1';

                                    if (isRunning) {
                                        baseSecs += 1;
                                        $(this).data('initial', baseSecs);
                                        $(this).text(formatDuration(baseSecs));
                                        anyRunning = true;
                                    }
                                    newTotal += baseSecs;
                                });

                                if (anyRunning) {
                                    $('#modalTotalDuration').text(formatDuration(newTotal));
                                }
                            }, 1000);

                            // Handler STOP
                            $('.btn-stop-dt-inner').on('click', function () {
                                const dtId = $(this).data('dtid');
                                Swal.fire({
                                    title: 'Selesaikan Downtime?',
                                    icon: 'question',
                                    showCancelButton: true,
                                    confirmButtonText: 'Ya, Selesai',
                                    confirmButtonColor: '#ef4444',
                                    cancelButtonColor: '#94a3b8'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        $.post('api_aktual_paddry.php', { action: 'stop_downtime', active_dt_id: dtId }, function (sres) {
                                            if (sres.success) {
                                                lastDataHash = ""; loadData(true);
                                                openDowntimeModal();
                                            }
                                        }, 'json');
                                    }
                                });
                            });

                            $('#btnStartDtInner').on('click', function () {
                                const dtSelect = $('#innerDtCode');
                                const code = dtSelect.val();
                                const name = dtSelect.find('option:selected').data('name');
                                const msid = dtSelect.find('option:selected').data('msid');
                                const ket = $('#innerDtKet').val();

                                if (!code) {
                                    Swal.showValidationMessage('Pilih alasan downtime!');
                                    return;
                                }

                                $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> MEMPROSES...');

                                $.post('api_aktual_paddry.php', {
                                    action: 'start_downtime',
                                    id: id,
                                    stage_id: stageId,
                                    kd_downtime: code,
                                    nm_dt: name,
                                    downtimemsid: msid,
                                    keterangan: ket
                                }, function (ires) {
                                    if (ires.success) {
                                        lastDataHash = ""; loadData(true);
                                        openDowntimeModal();
                                    } else {
                                        Swal.fire('Gagal', ires.message, 'error');
                                        $('#btnStartDtInner').prop('disabled', false).html('<i class="fas fa-play-circle mr-2"></i> MULAI SEKARANG');
                                    }
                                }, 'json');
                            });
                        }
                    });
                }, 'json');
            }

            openDowntimeModal();
        });

        // Aksi Klik Stop
        $(document).on('click', '.btn-stop', function () {
            var id = $(this).data('id');
            var cpno = $(this).data('cp');
            var activeDtId = $(this).data('active-dt');
            var rtgmsid = $(this).data('rtg');
            var isBreak = $(this).data('is-break') === true;
            var reqLebar = window.lebarReqs && window.lebarReqs[rtgmsid] === true;
            var btn = $(this);

            if (activeDtId) {
                Swal.fire({
                    title: 'Downtime Masih Berjalan!',
                    text: 'Terdapat proses downtime yang sedang berjalan. Selesaikan (Stop) downtime terlebih dahulu di panel downtime sebelum melakukan Stop Celup.',
                    icon: 'warning',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            let shiftEndHtml = '<select id="swalShiftEnd" class="form-control select2"><option value="">-- Pilih Shift End --</option>';
            shiftOptions.forEach(s => {
                shiftEndHtml += `<option value="${s.id}" data-name="${s.name}">${s.name} - ${s.group}</option>`;
            });
            shiftEndHtml += '</select>';

            let failReasonHtml = '<select id="swalFailCode" class="form-control select2"><option value="">-- Pilih Alasan Fail --</option>';
            failOptions.forEach(f => {
                failReasonHtml += `<option value="${f.code}" data-desc="${f.name}" data-msid="${f.id}">${f.code} - ${f.name}</option>`;
            });
            failReasonHtml += '</select>';

            var modalTitle = isBreak ? 'Selesai Breaktime' : 'Selesai Proses Celup';

            var lebarHtml = '';
            if (!isBreak && reqLebar) {
                lebarHtml = `
                        <label class="font-weight-bold small text-secondary mt-2">LEBAR KAIN (cm)</label>
                        <input type="text" id="swalLebar" class="form-control mb-3" placeholder="Masukkan lebar kain">
                `;
            }

            var prodSection = isBreak ? '' : `
                        ${lebarHtml}
                        
                        <label class="font-weight-bold small text-secondary mt-2">HASIL CELUP</label>
                        <div class="d-flex mt-1 mb-3">
                            <div class="custom-control custom-radio mr-4">
                                <input type="radio" id="resPass" name="hasilCelup" class="custom-control-input" value="PASS" checked>
                                <label class="custom-control-label font-weight-bold text-success" for="resPass">PASS</label>
                            </div>
                            <div class="custom-control custom-radio">
                                <input type="radio" id="resFail" name="hasilCelup" class="custom-control-input" value="FAIL">
                                <label class="custom-control-label font-weight-bold text-danger" for="resFail">FAIL</label>
                            </div>
                        </div>

                        <div id="failSection" style="display: none; border-top: 1px solid #eee; padding-top: 15px;">
                            <label class="font-weight-bold small text-danger">ALASAN FAIL</label>
                            <div class="mb-3">${failReasonHtml}</div>
                            
                            <label class="font-weight-bold small text-danger">KETERANGAN FAIL</label>
                            <textarea id="swalKetFail" class="form-control" rows="2" placeholder="Masukkan keterangan tambahan jika ada"></textarea>
                        </div>
            `;

            Swal.fire({
                title: modalTitle,
                html: `
                    <div class="text-left">
                        <p class="mb-2">Lengkapi data akhir untuk <b class="text-primary">${cpno}</b>:</p>
                        
                        <label class="font-weight-bold small text-secondary">SHIFT END</label>
                        <div class="mb-3">${shiftEndHtml}</div>
                        ${prodSection}
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: 'Ya, Selesai!',
                cancelButtonText: 'Batal',
                didOpen: () => {
                    $('#swalShiftEnd').select2({
                        theme: 'bootstrap4',
                        dropdownParent: Swal.getPopup(),
                        width: '100%'
                    });
                    $('#swalFailCode').select2({
                        theme: 'bootstrap4',
                        dropdownParent: Swal.getPopup(),
                        width: '100%'
                    });

                    // Toggle Fail Section
                    $('input[name="hasilCelup"]').on('change', function () {
                        if ($(this).val() === 'FAIL') {
                            $('#failSection').slideDown();
                        } else {
                            $('#failSection').slideUp();
                        }
                    });
                },
                preConfirm: () => {
                    const shiftId = $('#swalShiftEnd').val();
                    const shiftName = $('#swalShiftEnd option:selected').data('name');

                    if (!shiftId) {
                        Swal.showValidationMessage('Anda harus memilih Shift End!');
                        return false;
                    }

                    if (isBreak) {
                        return { shiftId, shiftName, lebar: '', hasil: 'PASS', failCode: '', failDesc: '', ketFail: '', failMsid: '' };
                    }

                    const lebar = $('#swalLebar').length > 0 ? $('#swalLebar').val() : '';
                    const hasil = $('input[name="hasilCelup"]:checked').val();

                    const failCodeSelect = $('#swalFailCode');
                    const failCode = failCodeSelect.val();
                    const failDesc = failCodeSelect.find('option:selected').data('desc');
                    const failMsid = failCodeSelect.find('option:selected').data('msid');
                    const ketFail = $('#swalKetFail').val();

                    if (!isBreak && reqLebar && !lebar) {
                        Swal.showValidationMessage('Anda harus mengisi Lebar Kain!');
                        return false;
                    }

                    if (hasil === 'FAIL') {
                        if (!failCode) {
                            Swal.showValidationMessage('Anda harus memilih Alasan Fail!');
                            return false;
                        }
                    }

                    return { shiftId, shiftName, lebar, hasil, failCode, failDesc, ketFail, failMsid };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    console.log("[DEBUG] STOP Action Confirmed:", {
                        id, rtgmsid,
                        values: result.value
                    });
                    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');
                    performAction('stop', id, rtgmsid, '', '', '', '', result.value.shiftId, result.value.shiftName, result.value.lebar, result.value.hasil, result.value.failCode, result.value.failDesc, result.value.ketFail, '', result.value.failMsid);
                }
            });
        });

        function performAction(act, id, rtgmsid = '', noRoda = '', ketDelay = '', shiftId = '', shiftName = '', shiftEndId = '', shiftEndName = '', lebarKain = '', hasilCelup = '', failCode = '', failDesc = '', ketFail = '', wheelMsid = '', failMsid = '') {
            $.post('api_aktual_paddry.php', {
                action: act,
                id: id,
                rtgmsid: rtgmsid,
                no_roda: noRoda,
                ket_delay: ketDelay,
                shift_id: shiftId,
                shift_name: shiftName,
                shift_end_id: shiftEndId,
                shift_end_name: shiftEndName,
                lebar_kain: lebarKain,
                hasil_celup: hasilCelup,
                fail_code: failCode,
                fail_desc: failDesc,
                ket_fail: ketFail,
                wheel_msid: wheelMsid,
                fail_msid: failMsid
            }, function (res) {
                console.log("[DEBUG] API Response (" + act + "):", res);
                if (res.success) {
                    if (res.sync_error) {
                        Swal.fire({
                            title: 'Data Tersimpan (Local)',
                            html: 'Namun <b>GAGAL</b> sinkronisasi ke ERP PostgreSQL!<br><br><small class="text-danger">' + res.sync_error + '</small>',
                            icon: 'warning'
                        });
                    } else {
                        const Toast = Swal.mixin({
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 2000,
                            timerProgressBar: true,
                        });
                        Toast.fire({ icon: 'success', title: res.message });
                    }
                    loadData(); // Soft refresh
                } else {
                    console.error("[DEBUG] API Error (" + act + "):", res.message);
                    Swal.fire('Gagal Menyimpan', res.message, 'error');
                    loadData();
                }
            }, 'json').fail(function (xhr, status, error) {
                console.error("[DEBUG] AJAX Fail:", { status, error, responseText: xhr.responseText });
                let errorMsg = 'Kesalahan koneksi API';
                if (xhr.responseText && xhr.responseText.length < 500) {
                    errorMsg += ': ' + xhr.responseText;
                }
                Swal.fire('Error', errorMsg, 'error');
                loadData();
            });
        }

        $('#btnRefresh').on('click', loadData);
        $('#filterDate').on('change', loadData);
        $('#filterMachine').on('change', renderCards);

        // Aksi Rollback Tester
        $(document).on('click', '.btn-dev-rollback', function () {
            var id = $(this).data('id');
            var btn = $(this);
            Swal.fire({
                title: 'Rollback Data?',
                text: 'Aksi ini akan menghapus semua history (Start, Stop, Downtime) untuk CP ini di SQL Server & ERP. Hanya gunakan untuk testing!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus History!'
            }).then((result) => {
                if (result.isConfirmed) {
                    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Loading...');
                    $.post('api_aktual_paddry.php', { action: 'dev_rollback', id: id }, function (res) {
                        if (res.success) {
                            Swal.fire('Berhasil!', res.message, 'success');
                            loadData();
                        } else {
                            Swal.fire('Gagal', res.message, 'error');
                            btn.prop('disabled', false).html('<i class="fas fa-undo mr-1"></i> Tester: Rollback Data');
                        }
                    }, 'json');
                }
            });
        });

        // Load Pertama Kali
        fetchReferences();
    });
</script>