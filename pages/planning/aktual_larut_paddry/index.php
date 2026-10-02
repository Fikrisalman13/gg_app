<?php
session_start();
ob_start();
include '../../../koneksi.php';
include '../../../includes/header.php';
include '../../../includes/sidebar.php';

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
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
    /* Fix Select2 alignment in SweetAlert2 */
    .select2-container--bootstrap4 .select2-results__option {
        text-align: left !important;
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
        transition: all 0.2s ease-in-out;
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

    .focus-mode-active {
        overflow: hidden;
        background: #f1f5f9 !important;
    }

    .focus-mode-active .main-header,
    .focus-mode-active .main-sidebar,
    .focus-mode-active .content-header,
    .focus-mode-active .header-toolbar,
    .focus-mode-active .main-footer {
        display: none !important;
    }

    .focus-mode-active .content-wrapper {
        margin-left: 0 !important;
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%) !important;
        height: 100vh;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 0;
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
    }

    .focus-mode-active .is-focus {
        flex: 0 0 100% !important;
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
        font-size: 4.5vh;
        background: #3b82f6;
        border: 4px solid white;
    }

    .focus-mode-active .cp-body {
        flex-grow: 1;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 3vw;
        padding: 3vh 5vw;
        align-items: center;
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

    .focus-mode-active .cp-footer {
        padding: 2vh 5vw;
        background: #f8fafc;
    }

    .focus-mode-active .btn-block {
        height: 12vh;
        font-size: 5vh;
        border-radius: 30px;
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

    #emptyState {
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    }

    #emptyState.is-visible {
        opacity: 1;
        transform: translateY(0);
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
        background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        opacity: 0.9;
        position: relative;
        z-index: 2;
        filter: drop-shadow(0 10px 15px rgba(14, 165, 233, 0.2));
    }

    .empty-glow {
        position: absolute;
        width: 40vh;
        height: 40vh;
        background: #0ea5e9;
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
</style>

<div id="fs-clock">00:00:00</div>

<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header pb-2">
            <div class="container-fluid d-flex justify-content-between align-items-center">
                <h1 class="m-0 outfit-font" style="font-weight: 700; color: #1e293b;">
                    <i class="fas fa-vial text-info mr-2"></i> Aktual <span class="text-info">Larut Produksi</span>
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
                        <div class="col-md-6 text-sm-right mt-3 mt-md-0">
                            <button id="btnRefresh" class="btn btn-info font-weight-bold px-4"
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
                    <p class="text-muted">Semua jadwal larut telah selesai atau belum tersedia untuk saat ini.</p>
                </div>

                <!-- Container Antrean Kartu -->
                <div id="cardsWrap" class="row"></div>
            </div>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.min.js"></script>
<!-- Select2 -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>

<script>
    $(document).ready(function () {
        var rawData = [];
        var wheelOptions = [];
        var delayOptions = [];

        function fetchReferences() {
            $.getJSON('api_aktual_larut_paddry.php?action=get_references', function (res) {
                if (res.success) {
                    wheelOptions = res.wheels;
                    delayOptions = res.delays;
                }
            });
        }

        function loadData() {
            var date = $('#filterDate').val();

            Swal.fire({
                title: 'Memuat Jadwal...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            $.ajax({
                url: 'api_aktual_larut_paddry.php',
                type: 'POST',
                data: { action: 'get_data', period_date: date },
                dataType: 'json',
                success: function (res) {
                    if (res.message === 'Unauthorized') {
                        window.location.href = '/gg_app/login.php';
                        return;
                    }
                    if (res.success) {
                        rawData = res.data;
                        populateMachines();
                        renderCards();
                        Swal.close();
                    } else {
                        Swal.fire('Gagal', res.message, 'error');
                    }
                },
                error: function () {
                    Swal.fire('Error', 'Gagal memuat API', 'error');
                }
            });
        }

        function populateMachines() {
            var prevMachine = $('#filterMachine').val();
            var machines = [];
            rawData.forEach(function (item) {
                var exists = machines.find(m => m.id === item.machine_id);
                if (!exists && item.machine_id) {
                    machines.push({ id: item.machine_id, name: item.machine_name || item.machine_id });
                }
            });

            var hw = '<option value="">-- Pilih Mesin --</option>';
            machines.forEach(function (m) {
                hw += '<option value="' + m.id + '">' + m.name + ' (' + m.id + ')</option>';
            });
            $('#filterMachine').html(hw);

            if (machines.length === 1) {
                $('#filterMachine').val(machines[0].id);
            } else if (machines.find(m => m.id === prevMachine)) {
                $('#filterMachine').val(prevMachine);
            }
        }

        function formatVlot(val) {
            if (!val || val === '-') return '-';
            return parseFloat(val).toString();
        }

        function formatVarianceHtml(varMin) {
            if (varMin === null || varMin === undefined) return '';
            var min = Math.round(varMin);
            var html = '';
            if (min < 0) {
                html = '<span class="variance-badge var-early">- ' + Math.abs(min) + ' mnt awal</span>';
            } else if (min > 0) {
                html = '<span class="variance-badge var-late">+ ' + min + ' mnt lambat</span>';
            } else {
                html = '<span class="variance-badge var-ontime">Tepat Waktu</span>';
            }
            return '<div class="mt-2 d-flex justify-content-center">' + html + '</div>';
        }

        window.liveTimers = window.liveTimers || null;

        function renderCards() {
            if (window.liveTimers) clearInterval(window.liveTimers);

            var selMac = $('#filterMachine').val();
            var filterData = rawData;
            if (selMac !== '') {
                filterData = rawData.filter(d => d.machine_id === selMac);
            }

            var $wrap = $('#cardsWrap');
            $wrap.empty();

            var activeFound = false;

            filterData.forEach(function (cp) {
                var isCompleted = (cp.aktual_start && cp.aktual_finish);
                var isActive = false;

                if (!isCompleted && !activeFound) {
                    isActive = true;
                    activeFound = true;
                }

                var cleanMachineName = (cp.machine_name || '').replace(/\s*\(.*?\)\s*/g, '').trim();
                var dispStart = (cp.aktual_start) ? cp.aktual_start.substring(0, 5) : '-';
                var dispFinish = (cp.aktual_finish) ? cp.aktual_finish.substring(0, 5) : '-';

                var cardCls = '';
                var headCls = '';
                var focusCls = '';
                var isRunning = (cp.aktual_start && !cp.aktual_finish);
                var headerLayoutCls = isRunning ? 'header-stack' : 'header-row';

                if (isActive) {
                    cardCls = 'active-card';
                    headCls = 'bg-active';
                    focusCls = 'is-focus';
                } else if (isCompleted) {
                    cardCls = 'completed-card'; headCls = 'bg-completed';
                }

                if (cp.is_breaktime) {
                    cardCls += ' breaktime-card';
                }

                var statusHtml = '';
                var btnHtml = '';
                var runningTimerId = '';

                if (isCompleted) {
                    statusHtml = '<span class="badge badge-success px-2 py-1 status-badge-focus"><i class="fas fa-check-circle mr-1"></i> SELESAI</span>';
                } else if (cp.aktual_start) {
                    runningTimerId = 'timer_' + cp.id;
                    statusHtml = '<span class="badge badge-warning px-2 py-1 status-badge-focus"><i class="fas fa-spinner fa-spin mr-1"></i> BERJALAN: <span id="' + runningTimerId + '" data-start="' + cp.aktual_start + '">00:00:00</span></span>';
                    if (isActive) {
                        btnHtml = '<button class="btn btn-danger btn-block font-weight-bold btn-stop" data-id="' + cp.id + '" data-cp="' + cp.cp_no + '"><i class="fas fa-stop mr-1"></i> STOP LARUT SEKARANG</button>';
                    }
                } else {
                    statusHtml = '<span class="badge badge-secondary px-2 py-1 status-badge-focus">MENUNGGU</span>';
                    if (isActive) {
                        btnHtml = `<button class="btn btn-info btn-block font-weight-bold btn-start" 
                                data-id="${cp.id}" 
                                data-cp="${cp.cp_no}" 
                                data-is-break="${cp.is_breaktime ? 'true' : 'false'}"
                                data-plan-start="${cp.rencana_start}">
                                <i class="fas fa-play mr-1"></i> START LARUT SEKARANG
                               </button>`;
                    }
                }

                var html = `
                <div class="col-md-6 col-lg-4 ${focusCls}">
                    <div class="cp-card ${cardCls}">
                        <div class="cp-header ${headCls} ${headerLayoutCls}">
                            <div class="d-flex align-items-center flex-grow-1" style="min-width: 0;">
                                <div class="seq-badge mr-3 flex-shrink-0">${cp.seq_no}</div>
                                <div style="min-width: 0;">
                                    <h5 class="m-0 font-weight-bold outfit-font text-dark">${cp.cp_no}</h5>
                                    <div class="text-muted small fs-machine-name">${cleanMachineName}</div>
                                </div>
                            </div>
                            <div class="status-container-responsive">${statusHtml}</div>
                        </div>
                        <div class="cp-body">
                            ${cp.is_breaktime ? `
                                <div class="break-content-placeholder d-flex flex-column align-items-center justify-content-center" style="min-height: 20vh; border: 2px dashed #f59e0b; border-radius: 20px; background: rgba(245, 158, 11, 0.05); margin-bottom: 2vh;">
                                    <i class="fas fa-coffee mb-3" style="font-size: 5vh; color: #f59e0b; opacity: 0.8;"></i>
                                    <h2 class="font-weight-bold outfit-font mb-0" style="color: #92400e; letter-spacing: 2px; font-size: 4vh;">BREAKTIME</h2>
                                </div>
                            ` : `
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
                                        <div class="data-label">Material:</div>
                                        <div class="data-value fs-material-val">${cp.material || '-'}</div>
                                    </div>
                                    <div class="data-row">
                                        <div class="data-label">Quantity:</div>
                                        <div class="data-value font-weight-bold">${cp.qty}</div>
                                    </div>
                                </div>
                            `}
                            
                            <div class="time-group row">
                                <div class="col-12 time-box-clock">
                                    <div class="time-box">
                                        <span class="time-label">JAM SEKARANG</span>
                                        <div class="time-val fs-live-clock">00:00:00</div>
                                    </div>
                                </div>
                                <div class="col-6 pr-1">
                                    <div class="time-box">
                                        <span class="time-label">Start Larut</span>
                                        <div class="time-val text-success">${dispStart}</div>
                                        <div class="plan-sub">Plan: ${cp.rencana_start}</div>
                                        ${formatVarianceHtml(cp.variance_start)}
                                    </div>
                                </div>
                                <div class="col-6 pl-1">
                                    <div class="time-box">
                                        <span class="time-label">Finish Larut</span>
                                        <div class="time-val text-danger">${dispFinish}</div>
                                        <div class="plan-sub">Target: ${cp.rencana_finish}</div>
                                        ${formatVarianceHtml(cp.variance_finish)}
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

            // 🌟 LOGIC EMPTY STATE 🌟
            var showEmpty = (filterData.length === 0);
            if ($('body').hasClass('focus-mode-active')) {
                // Di mode fokus, jika tidak ada antrean AKTIF, tampilkan empty state
                if (!activeFound) showEmpty = true;
            }

            if (showEmpty) {
                $('#emptyState').addClass('is-visible').show();
                if (filterData.length > 0 && !activeFound) {
                    $('#emptyState h4').text('Tugas Selesai');
                    $('#emptyState p').text('Semua jadwal larut telah selesai diproses untuk mesin ini.');
                } else {
                    $('#emptyState h4').text('Tidak Ada Antrean');
                    $('#emptyState p').text('Belum tersedia jadwal larut untuk mesin ini.');
                }
            } else {
                $('#emptyState').removeClass('is-visible').hide();
            }

            if (!$('body').hasClass('focus-mode-active')) {
                $('.fs-machine-name').hide();
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

            window.liveTimers = setInterval(function () {
                $('[id^="timer_"]').each(function () {
                    var startStr = $(this).data('start');
                    if (!startStr) return;
                    var today = new Date();
                    var parts = startStr.split(':');
                    if (parts.length >= 2) {
                        var sTime = new Date(today.getFullYear(), today.getMonth(), today.getDate(), parts[0], parts[1], parts[2] || 0);
                        var diffMs = today - sTime;
                        if (diffMs < 0) diffMs = 0;
                        var h = Math.floor(diffMs / 3600000).toString().padStart(2, '0');
                        var m = Math.floor((diffMs % 3600000) / 60000).toString().padStart(2, '0');
                        var s = Math.floor((diffMs % 60000) / 1000).toString().padStart(2, '0');
                        $(this).text(h + ':' + m + ':' + s);
                    }
                });
            }, 1000);
        }

        function toggleFocusMode() {
            $('body').toggleClass('focus-mode-active');
            if ($('body').hasClass('focus-mode-active')) {
                var elem = document.documentElement;
                if (elem.requestFullscreen) elem.requestFullscreen();
            } else {
                if (document.exitFullscreen) document.exitFullscreen();
            }
            renderCards();
        }

        $(document).on('keydown', function (e) {
            if (e.key === "Escape" && $('body').hasClass('focus-mode-active')) toggleFocusMode();
        });

        $('#btnFocusMode').click(toggleFocusMode);

        $(document).on('click', '.btn-start', function () {
            var id = $(this).data('id');
            var cpno = $(this).data('cp');
            var planStart = $(this).data('plan-start');
            var isBreak = $(this).data('is-break') === true;
            var btn = $(this);

            if (isBreak) {
                // Bypass Nomor Roda untuk Breaktime
                let isLate = false;
                if (planStart && planStart !== '-') {
                    let now = new Date();
                    let timeParts = planStart.split(':');
                    let pTime = new Date(now.getFullYear(), now.getMonth(), now.getDate(), timeParts[0], timeParts[1], 0);
                    if (now - pTime > 300000) isLate = true;
                }

                if (isLate) {
                    handleDelayPrompt(id, '', cpno);
                } else {
                    performAction('start', id, '', '');
                }
                return;
            }

            let wheelHtml = '<select id="swalWheel" class="form-control select2"><option value="">-- Pilih Nomor Roda --</option>';
            wheelOptions.forEach(w => { wheelHtml += `<option value="${w}">${w}</option>`; });
            wheelHtml += '</select>';

            Swal.fire({
                title: 'Nomor Roda',
                html: `Pilih Nomor Roda untuk <b class="text-info">${cpno}</b>:<br><br>${wheelHtml}`,
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Lanjut',
                cancelButtonText: 'Batal',
                didOpen: () => {
                    $('#swalWheel').select2({ theme: 'bootstrap4', dropdownParent: Swal.getHtmlContainer(), width: '100%' });
                },
                preConfirm: () => {
                    const wheel = document.getElementById('swalWheel').value;
                    if (!wheel) Swal.showValidationMessage('Anda harus memilih Nomor Roda!');
                    return wheel;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const selectedWheel = result.value;
                    let isLate = false;
                    if (planStart && planStart !== '-') {
                        let now = new Date();
                        let timeParts = planStart.split(':');
                        let pTime = new Date(now.getFullYear(), now.getMonth(), now.getDate(), timeParts[0], timeParts[1], 0);
                        if (now - pTime > 300000) isLate = true;
                    }

                    if (isLate) {
                        handleDelayPrompt(id, selectedWheel, cpno);
                    } else {
                        performAction('start', id, selectedWheel, '');
                    }
                }
            });
        });

        function handleDelayPrompt(id, selectedWheel, cpno) {
            let delayHtml = '<select id="swalDelay" class="form-control"><option value="">-- Pilih Alasan Delay --</option>';
            delayOptions.forEach(d => { delayHtml += `<option value="${d.code} - ${d.name}">${d.code} - ${d.name}</option>`; });
            delayHtml += '</select>';

            Swal.fire({
                title: 'Alasan Delay',
                html: `Waktu mulai terlambat (>5 mnt dari rencana). Pilih alasan delay:<br><br>${delayHtml}`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Simpan & Start',
                cancelButtonText: 'Batal',
                didOpen: () => { $('#swalDelay').select2({ theme: 'bootstrap4', dropdownParent: Swal.getHtmlContainer(), width: '100%' }); },
                preConfirm: () => {
                    const delay = document.getElementById('swalDelay').value;
                    if (!delay) Swal.showValidationMessage('Anda harus memilih alasan delay!');
                    return delay;
                }
            }).then((dResult) => {
                if (dResult.isConfirmed) performAction('start', id, selectedWheel, dResult.value);
            });
        }

        $(document).on('click', '.btn-stop', function () {
            var id = $(this).data('id');
            var cpno = $(this).data('cp');
            var btn = $(this);

            Swal.fire({
                title: 'Selesai Pelarutan?',
                html: `Konfirmasi bahwa proses larut <b class="text-danger">${cpno}</b> telah selesai?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: 'Ya, Finish!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>...');
                    performAction('stop', id);
                }
            });
        });

        function performAction(act, id, noRoda = '', ketDelay = '') {
            $.post('api_aktual_larut_paddry.php', {
                action: act,
                id: id,
                no_roda: noRoda,
                ket_delay: ketDelay
            }, function (res) {
                if (res.success) {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true,
                    });
                    Toast.fire({ icon: 'success', title: res.message });
                    loadData();
                } else {
                    Swal.fire('Gagal', res.message, 'error');
                    loadData();
                }
            }, 'json').fail(function () {
                Swal.fire('Error', 'Kesalahan koneksi API', 'error');
                loadData();
            });
        }

        $('#btnRefresh').on('click', loadData);
        $('#filterDate').on('change', loadData);
        $('#filterMachine').on('change', renderCards);

        fetchReferences();
        loadData();
    });
</script>