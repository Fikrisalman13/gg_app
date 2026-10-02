<?php
session_start();

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

// Set default timezone to Jakarta so displayed times are correct
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

function checkPermissions($conn, $groupId, $menuId) {
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete
            FROM dbo.SMGroupTrustee
            WHERE GroupId = ? AND MenuId = ?";
    $params = [$groupId, $menuId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    $permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    if ($stmt !== false) {
        sqlsrv_free_stmt($stmt);
    }

    return $permissions;
}

$permissions = checkPermissions($conn, $_SESSION['GroupId'], 162);
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}

$usernameRaw = $_SESSION['NamaLengkap'] ?? $_SESSION['UserName'];
$username = htmlspecialchars($usernameRaw ?? '');
$jabatan = $departemen = $bagian = '';

try {
    $sql = "SELECT m_jab.jabatan, m_dept.dept, m_bag.bagian
            FROM dbo.m_emp
            LEFT JOIN dbo.m_jab ON m_emp.id_jab = m_jab.id_jab
            LEFT JOIN dbo.m_subbag ON m_emp.id_subbag = m_subbag.id_subbag
            LEFT JOIN dbo.m_bag ON m_subbag.id_bag = m_bag.id_bag
            LEFT JOIN dbo.m_dept ON m_bag.id_dept = m_dept.id_dept
            WHERE m_emp.nama_lengkap = ?";
    $params = [$usernameRaw];
    $st = sqlsrv_query($conn, $sql, $params);
    if ($st !== false) {
        $row = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC);
        if ($row) {
            $jabatan = $row['jabatan'] ?? '';
            $departemen = $row['dept'] ?? '';
            $bagian = $row['bagian'] ?? '';
        }
        sqlsrv_free_stmt($st);
    }
} catch (Exception $e) {
    // Suppress errors for optional autofill
}

$issueCategories = [];
try {
    $sqlCat = "SELECT c.category_name, s.sub_name
               FROM dbo.issue_categories c
               LEFT JOIN dbo.issue_sub_categories s ON c.category_id = s.category_id
               WHERE c.is_active = 1
               ORDER BY c.category_name, s.sub_name";
    $stmtCat = sqlsrv_query($conn, $sqlCat);
    if ($stmtCat !== false) {
        while ($row = sqlsrv_fetch_array($stmtCat, SQLSRV_FETCH_ASSOC)) {
            $categoryName = $row['category_name'];
            $subName = $row['sub_name'];
            if (!isset($issueCategories[$categoryName])) {
                $issueCategories[$categoryName] = [];
            }
            if (!empty($subName)) {
                $issueCategories[$categoryName][] = $subName;
            }
        }
        sqlsrv_free_stmt($stmtCat);
    }
} catch (Exception $e) {
    // Ignore category loading errors
}

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
?>

<style>
    .action-btn { margin-right: 6px; }

    .issues-table.dataTable.dtr-inline.collapsed > tbody > tr > td.dtr-control:before,
    .issues-table.dataTable.dtr-inline.collapsed > tbody > tr > th.dtr-control:before {
        background-color: #007bff;
        border: none;
        box-shadow: none;
        line-height: 1;
        top: 50%;
        transform: translateY(-50%);
    }

    .issues-table.dataTable > tbody > tr.child ul.dtr-details {
        display: block;
        width: 100%;
        padding: 0;
    }
    .issues-table.dataTable > tbody > tr.child ul.dtr-details > li {
        border-bottom: 1px solid #efefef;
        padding: 8px 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        column-gap: 0.75rem;
    }
    .issues-table.dataTable > tbody > tr.child ul.dtr-details > li:last-child {
        border-bottom: none;
    }
    .issues-table.dataTable > tbody > tr.child ul.dtr-details > li .dtr-title {
        font-weight: 600;
        color: #555;
        min-width: 120px;
    }
    .issues-table.dataTable > tbody > tr.child ul.dtr-details > li .dtr-data {
        text-align: right;
        flex: 1;
        word-break: break-word;
    }
    @media (max-width: 576px) {
      .issues-table.dataTable > tbody > tr.child ul.dtr-details > li {
        flex-direction: column;
        align-items: flex-start;
      }
      .issues-table.dataTable > tbody > tr.child ul.dtr-details > li .dtr-data {
        width: 100%;
        text-align: left;
        margin-top: 4px;
      }
    }

    .badge-type {
        font-size: 0.85rem;
        padding: 0.35em 0.75em;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        border-radius: 999px;
        font-weight: 600;
        letter-spacing: 0.02em;
    }
    .badge-type i {
        font-size: 0.75rem;
        line-height: 1;
    }
    .type-task { background-color: #007bff; color: #fff; }
    .type-maintenance { background-color: #fd7e14; color: #fff; }
    .type-bug { background-color: #dc3545; color: #fff; }
    .type-improvement { background-color: #28a745; color: #fff; }
    .type-new-feature { background-color: #6f42c1; color: #fff; }
    .type-support-activities { background-color: #17a2b8; color: #fff; }
    .type-story { background-color: #17a2b8; color: #fff; } /* Legacy */

    .status-todo { background-color: #6c757d; }
    .status-inprogress { background-color: #ffc107; color: #212529; }
    .status-done { background-color: #28a745; }
    .status-closed { background-color: #343a40; }

    .priority-low { background-color: #17a2b8; }
    .priority-normal { background-color: #007bff; }
    .priority-high { background-color: #dc3545; }

    .filter-section {
        background-color: #f8f9fa;
        border-radius: 5px;
    }
        .auto-filled {
            background-color: #e9ecef;
            cursor: not-allowed;
        }

        .issue-type-option {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            line-height: 1.1;
            vertical-align: middle;
        }

        .issue-type-icon {
            width: 18px;
            height: 18px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.65rem;
            flex-shrink: 0;
            line-height: 18px;
        }

        .issue-type-icon i {
            font-size: 0.6rem;
            line-height: 1;
        }

        .issue-detail-header {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .issue-detail-badges span {
            margin-right: 0.35rem;
            display: inline-block;
        }
        .detail-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 0.85rem 1rem;
            border: 1px solid #e9ecef;
        }
        .section-title {
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        .detail-list {
            margin-bottom: 0;
        }
        .detail-list dt {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: #6c757d;
            font-weight: 600;
            margin-top: 0.35rem;
            letter-spacing: 0.05em;
        }
        .detail-list dd {
            font-size: 0.95rem;
            margin-bottom: 0.35rem;
        }
        .detail-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6c757d;
            font-weight: 600;
        }
        .detail-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #343a40;
        }
        .detail-description {
            border: 1px dashed #ced4da;
            border-radius: 8px;
            padding: 0.85rem 1rem;
            min-height: 80px;
            background: #fff;
            white-space: pre-line;
        }
        .detail-timeline {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .detail-timeline .timeline-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
        }
        .detail-timeline .timeline-label {
            font-weight: 600;
            color: #6c757d;
        }
        #issueDetailError {
            margin-bottom: 1rem;
        }
    
    /* Force wrapping even if table has nowrap class */
    table.dataTable.issues-table td.wrap-text {
        min-width: 250px; /* Ensure readability */
        max-width: 400px; /* Prevent excessive width */
        white-space: normal !important;
        word-wrap: break-word;
        vertical-align: middle;
    }
</style>

<div class="wrapper">
<div class="content-wrapper">
<section class="content-header">
    <div class="container-fluid">
        <h1>Riwayat Issues</h1>
    </div>
</section>

<section class="content">
<div class="container-fluid">
<div class="card card-primary">
    <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
        <h3 class="card-title mb-0"><i class="fas fa-list mr-1"></i> Issues Selesai</h3>
       
    </div>
    <div class="card-body">
      
        <div class="filter-section p-3 mb-3">
            <div class="row">
                <div class="col-md-2">
                    <label class="small mb-1" for="filterType">Filter Type</label>
                    <select id="filterType" class="form-control form-control-sm">
                        <option value="">Semua Type</option>
                        <option value="Task">Task</option>
                        <option value="Maintenance">Maintenance</option>
                        <option value="Bug">Bug</option>
                        <option value="Improvement">Improvement</option>
                        <option value="New Feature">New Feature</option>
                        <option value="Support Activities">Support Activities</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="small mb-1" for="filterKategori">Kategori</label>
                    <select id="filterKategori" class="form-control form-control-sm select2">
                        <option value="">Semua Kategori</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="small mb-1" for="filterSubKategori">Sub Kategori</label>
                    <select id="filterSubKategori" class="form-control form-control-sm select2">
                        <option value="">Semua Sub</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="small mb-1" for="filterTanggalStart">Tanggal Mulai</label>
                    <input type="date" id="filterTanggalStart" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <label class="small mb-1" for="filterTanggalEnd">Tanggal Akhir</label>
                    <input type="date" id="filterTanggalEnd" class="form-control form-control-sm">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button id="btnReset" class="btn btn-secondary btn-sm mr-1 w-50" title="Reset Filter">
                        <i class="fas fa-sync"></i>
                    </button>
                    <button id="btnExportPdf" class="btn btn-danger btn-sm w-50" title="Export PDF">
                        <i class="fas fa-file-pdf"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table id="riwayatIssuesTable" class="table table-hover table-sm nowrap issues-table" style="width:100%">
                <thead class="thead-light">
                    <tr>
                        <th>No</th>
                        <th>Issue Name</th>
                        <th>Type</th>
                        <th>Kategori Kegiatan</th>
                        <th>Nama</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Due Date</th>
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
</div>

<?php 
$isHistoryPage = true;
include 'modal_edit_issue.php'; 
?>
<?php include 'modal_view_issue.php'; ?>
<?php if (in_array($_SESSION['UserName'], ['ITADM', 'IT7'])) include 'modal_admin_edit_dates.php'; ?>

<?php include '../../includes/footer.php'; ?>

<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
$(function() {
    var subKategoriData = <?php echo json_encode($issueCategories); ?> || {};
    
    // Initialize Kategori Filter
    var $filterKategori = $('#filterKategori');
    var $filterSubKategori = $('#filterSubKategori');
    
    // Sort Categories alphabetically
    var categories = Object.keys(subKategoriData).sort();
    
    categories.forEach(function(cat){
        // Ensure no empty keys
        if(cat) {
            $filterKategori.append(new Option(cat, cat));
        }
    });

    // Cascading Filter
    $filterKategori.on('change', function() {
        var selectedCat = $(this).val();
        $filterSubKategori.empty().append('<option value="">Semua Sub</option>');
        
        if(selectedCat && subKategoriData[selectedCat]) {
            subKategoriData[selectedCat].sort().forEach(function(sub){
                $filterSubKategori.append(new Option(sub, sub));
            });
        }
    });

    var issueTypeMeta = {
        'Task': { label: 'Task', icon: 'fas fa-check-square', bg: '#1f8ef1', color: '#ffffff' },
        'Maintenance': { label: 'Maintenance', icon: 'fas fa-tools', bg: '#f59e0b', color: '#ffffff' },
        'Bug': { label: 'Bug', icon: 'fas fa-bug', bg: '#f97316', color: '#ffffff' },
        'Improvement': { label: 'Improvement', icon: 'fas fa-arrow-up', bg: '#0ea5e9', color: '#ffffff' },
        'New Feature': { label: 'New Feature', icon: 'fas fa-plus', bg: '#22c55e', color: '#ffffff' },
        'Support Activities': { label: 'Support Activities', icon: 'fas fa-circle', bg: 'transparent', color: '#e11d48', border: '1px solid #e11d48', shape: 'circle' },
        'Story': { label: 'Story', icon: 'fas fa-circle', bg: 'transparent', color: '#e11d48', border: '1px solid #e11d48', shape: 'circle' } // Legacy
    };
    var userDefaults = {
        nama: <?php echo json_encode($usernameRaw); ?>,
        jabatan: <?php echo json_encode($jabatan); ?>,
        departemen: <?php echo json_encode($departemen); ?>,
        bagian: <?php echo json_encode($bagian); ?>
    };
    var defaultTanggalPengajuan = <?php echo json_encode(date('d-m-Y')); ?>;

    var $detailModal = $('#modalIssueDetail');
    var $detailContent = $('#issueDetailContent');
    var $detailLoading = $('#issueDetailLoading');
    var $detailError = $('#issueDetailError');
    var $editModal = $('#modalIssueEdit');
    var $editKategori = $('#edit_kategori');
    var $editSubKategori = $('#edit_sub_kategori');
    var $editSubKategoriGroup = $('#edit_subKategoriGroup');
    var $editAssetGroup = $('#edit_assetGroup');
    var $editClientGroup = $('#edit_clientGroup');
    var $editAssetSelect = $('#edit_asset_id');
    var $editClientSelect = $('#edit_client_id');
    var $editIssueType = $('#edit_issue_type');
    var subCategoryConfigCache = null;

    function renderIssueTypeMarkup(option) {
        if (!option.id) {
            return option.text;
        }
        var meta = issueTypeMeta[option.id];
        if (!meta) {
            return option.text;
        }
        var bg = meta.bg || '#6c757d';
        var color = meta.color || '#ffffff';
        var border = meta.border || 'none';
        var radius = meta.shape === 'circle' ? '50%' : '4px';
        var icon = meta.icon ? '<i class="' + meta.icon + '"></i>' : '';
        var label = $('<div>').text(meta.label || option.text).html();
        return '<span class="issue-type-option"><span class="issue-type-icon" style="background-color:' + bg + ';color:' + color + ';border:' + border + ';border-radius:' + radius + ';">' + icon + '</span><span>' + label + '</span></span>';
    }

    function initIssueTypeSelect(context) {
        if (typeof $.fn.select2 !== 'function') {
            return;
        }
        var $targets = context ? $(context).find('.issue-type-select') : $('.issue-type-select');
        if (!$targets.length) {
            return;
        }
        $targets.each(function() {
            var $select = $(this);
            if ($select.data('select2')) {
                return;
            }
            var parentModal = $select.closest('.modal');
            var dropdownParent = parentModal.length ? parentModal : $editModal;
            var placeholder = $select.data('placeholder') || '-- Pilih Type --';
            $select.select2({
                theme: 'bootstrap4',
                width: '100%',
                dropdownParent: dropdownParent,
                placeholder: placeholder,
                allowClear: true,
                templateResult: renderIssueTypeMarkup,
                templateSelection: renderIssueTypeMarkup,
                escapeMarkup: function(markup) { return markup; }
            });
        });
    }

    function initEditSelects() {
        if (typeof $.fn.select2 !== 'function') {
            return;
        }
        var dropdownParent = $editModal.length ? $editModal : $(document.body);
        [$editKategori, $editSubKategori].forEach(function($el) {
            if (!$el || !$el.length || $el.hasClass('select2-hidden-accessible')) {
                return;
            }
            var placeholder = $el.data('placeholder') || '-- Pilih --';
            $el.select2({
                theme: 'bootstrap4',
                width: '100%',
                dropdownParent: dropdownParent,
                placeholder: placeholder,
                allowClear: true
            });
        });
    }

    function loadSubCategoryConfig() {
        if (subCategoryConfigCache !== null) return;
        $.ajax({
            url: 'get_sub_category_config.php',
            type: 'GET',
            dataType: 'json',
            cache: true,
            success: function(resp) {
                if (resp && resp.success && resp.configs) {
                    subCategoryConfigCache = resp.configs;
                } else {
                    subCategoryConfigCache = {};
                }
            },
            error: function() {
                subCategoryConfigCache = {};
                console.error('Gagal memuat konfigurasi sub-kategori.');
            }
        });
    }

    initEditSelects();
    initIssueTypeSelect($editModal);
    loadSubCategoryConfig();

    function escapeHtml(text) {
        return String(text || '').replace(/[&<>"']/g, function(ch) {
            switch (ch) {
                case '&': return '&amp;';
                case '<': return '&lt;';
                case '>': return '&gt;';
                case '"': return '&quot;';
                case "'": return '&#39;';
                default: return ch;
            }
        });
    }

    function renderBadge(meta, fallback) {
        if (!meta) {
            return escapeHtml(fallback || '-');
        }
        var icon = meta.icon ? '<i class="' + meta.icon + '"></i>' : '';
        return '<span class="badge badge-type ' + meta.className + '">' + icon + '<span>' + escapeHtml(meta.label) + '</span></span>';
    }

    function getTypeBadge(type) {
        var badges = {
            'Task': '<span class="badge badge-type type-task"><i class="fas fa-check-square mr-1"></i>Task</span>',
            'Maintenance': '<span class="badge badge-type type-maintenance"><i class="fas fa-wrench mr-1"></i>Maintenance</span>',
            'Bug': '<span class="badge badge-type type-bug"><i class="fas fa-bug mr-1"></i>Bug</span>',
            'Improvement': '<span class="badge badge-type type-improvement"><i class="fas fa-arrow-up mr-1"></i>Improvement</span>',
            'New Feature': '<span class="badge badge-type type-new-feature"><i class="fas fa-plus-circle mr-1"></i>New Feature</span>',
            'Support Activities': '<span class="badge badge-type type-support-activities"><i class="fas fa-book mr-1"></i>Support Activities</span>',
            'Story': '<span class="badge badge-type type-story"><i class="fas fa-book mr-1"></i>Story</span>' /* Legacy */
        };
        return badges[type] || escapeHtml(type || '-');
    }

    function getStatusBadge(status) {
        var map = {
            'To Do': { label: 'To Do', className: 'status-todo', icon: 'fas fa-list-ul' },
            'In Progress': { label: 'In Progress', className: 'status-inprogress', icon: 'fas fa-spinner' },
            'Done': { label: 'Done', className: 'status-done', icon: 'fas fa-check' },
            'Closed': { label: 'Closed', className: 'status-closed', icon: 'fas fa-lock' }
        };
        return renderBadge(map[status], status);
    }

    function getPriorityBadge(priority) {
        var map = {
            'Low': { label: 'Low', className: 'priority-low', icon: 'fas fa-arrow-down' },
            'Normal': { label: 'Normal', className: 'priority-normal', icon: 'fas fa-minus' },
            'High': { label: 'High', className: 'priority-high', icon: 'fas fa-exclamation' }
        };
        return renderBadge(map[priority], priority);
    }

    function normalizeDateInput(value) {
        if (!value) {
            return null;
        }
        var normalized = value;
        if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
            normalized = value + 'T00:00:00';
        } else if (value.indexOf(' ') > -1 && value.indexOf('T') === -1) {
            normalized = value.replace(' ', 'T');
        }
        var date = new Date(normalized);
        if (isNaN(date.getTime())) {
            return null;
        }
        return date;
    }

    function formatDetailDate(value) {
        var date = normalizeDateInput(value);
        if (!date) {
            return value || '-';
        }
        return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function formatDetailDateTime(value) {
        var date = normalizeDateInput(value);
        if (!date) {
            return value || '-';
        }
        var datePart = date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        var timePart = date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        timePart = timePart.replace(':', '.');
        return datePart + ' ' + timePart;
    }

    // Helper: 0 = Today, -1 = Yesterday
    function getDateStringID(offsetDays) {
        offsetDays = offsetDays || 0;
        var d = new Date();
        d.setDate(d.getDate() + offsetDays);
        var options = { day: '2-digit', month: 'long', year: 'numeric' };
        return d.toLocaleDateString('id-ID', options);
    }
    
    function renderDynamicDateHelper(text) {
        if (!text) return '';
        var displayName = escapeHtml(text);
        
        var todayStr = getDateStringID(0);
        var yesterdayStr = getDateStringID(-1);
        
        if (displayName.indexOf('{DATE}') !== -1) {
            displayName = displayName.split('{DATE}').join(todayStr);
        }
        if (displayName.indexOf('{DATE-1}') !== -1) {
            displayName = displayName.split('{DATE-1}').join(yesterdayStr);
        }
        return displayName;
    }

    function setIssueDetailState(state, message) {
        if (!$detailModal.length) {
            return;
        }
        if ($detailLoading.length) {
            $detailLoading.toggleClass('d-none', state !== 'loading');
        }
        if ($detailContent.length) {
            $detailContent.toggleClass('d-none', state !== 'content');
        }
        if ($detailError.length) {
            if (state === 'error') {
                $detailError.removeClass('d-none').text(message || 'Detail issue tidak tersedia.');
            } else {
                $detailError.addClass('d-none').text('');
            }
        }
    }

    function populateIssueDetail(data) {
        if (!data) {
            setIssueDetailState('error', 'Detail issue tidak ditemukan.');
            return;
        }
        var issueIdText = data.issue_id ? 'Issue #' + data.issue_id : 'Detail Issue';
        $('#modalIssueDetailTitle').html('<i class="fas fa-eye mr-2"></i>' + issueIdText);
        $('#detailIssueId').text(issueIdText);
        // Use renderDynamicDateHelper for visual consistency in detail
        $('#detailIssueName').html(renderDynamicDateHelper(data.issue_name || '-'));
        $('#detailTypeBadge').html(data.issue_type ? getTypeBadge(data.issue_type) : '<span class="badge badge-secondary">Type belum diisi</span>');
        $('#detailStatusBadge').html(data.status ? getStatusBadge(data.status) : '<span class="badge badge-secondary">Status belum diisi</span>');
        $('#detailPriorityBadge').html(data.priority ? getPriorityBadge(data.priority) : '<span class="badge badge-secondary">Priority belum diisi</span>');
        $('#detailStatusText').text(data.status || '-');
        $('#detailPriorityText').text(data.priority || '-');
        $('#detailDueDate').text(formatDetailDate(data.due_date));
        $('#detailKategori').text(data.kategori || '-');
        $('#detailSubKategori').text(data.sub_kategori || '-');
        $('#detailAsset').text(data.asset_label || 'Tidak ada asset terhubung');
        $('#detailClient').text(data.client_label || '-'); // Add Client
        $('#detailNama').text(data.created_by || '-');
        $('#detailJabatan').text(data.jabatan || '-');
        $('#detailDepartemen').text(data.departemen || '-');
        $('#detailBagian').text(data.bagian || '-');
        
        // Completion Date
        if (data.tanggal_selesai) {
            $('#detailCompletionDate').text(formatDetailDateTime(data.tanggal_selesai));
            $('#detailCompletionGroup').show();
        } else {
             $('#detailCompletionGroup').hide();
        }

        var createdInfo = formatDetailDateTime(data.created_at);
        var createdUser = data.created_by_username || data.created_by || '';
        if (createdUser) {
            createdInfo += ' • ' + createdUser;
        }
        $('#detailCreatedInfo').text(createdInfo || '-');

        var updatedInfo = data.updated_at ? formatDetailDateTime(data.updated_at) : 'Belum pernah diperbarui';
        if (data.updated_at) {
            var updatedUser = data.updated_by_username || data.updated_by || '';
            if (updatedUser) {
                updatedInfo += ' • ' + updatedUser;
            }
        }
        $('#detailUpdatedInfo').text(updatedInfo);

        var description = (data.description || '').trim();
        $('#detailDescription').text(description !== '' ? description : 'Belum ada deskripsi.');

        setIssueDetailState('content');
    }

    function showIssueDetail(issueId) {
        if (!issueId) {
            showError('Data issue tidak ditemukan.');
            return;
        }
        setIssueDetailState('loading');
        $detailModal.modal('show');
        $.ajax({
            url: 'get_issue_detail.php',
            type: 'GET',
            data: { issue_id: issueId },
            dataType: 'json',
            cache: false,
            success: function(resp) {
                if (resp && resp.success && resp.data) {
                    populateIssueDetail(resp.data);
                } else {
                    setIssueDetailState('error', (resp && resp.message) ? resp.message : 'Detail issue tidak ditemukan.');
                }
            },
            error: function() {
                setIssueDetailState('error', 'Terjadi kesalahan saat memuat detail issue.');
            }
        });
    }

    function collectHistoryFilters() {
        return {
            filterType: $('#filterType').val() || '',
            filterKategori: $('#filterKategori').val() || '',
            filterSubKategori: $('#filterSubKategori').val() || '',
            filterTanggalStart: $('#filterTanggalStart').val() || '',
            filterTanggalEnd: $('#filterTanggalEnd').val() || ''
        };
    }

    var table = $('#riwayatIssuesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'issues_serverside.php',
            type: 'POST',
            data: function(d) {
                var filters = collectHistoryFilters();
                filters.scope = 'done';
                $.extend(d, filters);
            }
        },
        responsive: {
            details: {
                type: 'column',
                target: 0
            }
        },
        columnDefs: [
            { className: 'dtr-control', orderable: false, targets: 0 },
            { responsivePriority: 1, targets: -1 },
            { responsivePriority: 2, className: 'wrap-text', targets: 1 },
            { responsivePriority: 3, targets: 4 },
            { responsivePriority: 4, targets: 5 },
            { responsivePriority: 6, targets: 6 }
        ],
        columns: [
            { data: 'no' },
            { 
                data: 'issue_name',
                render: function(data, type, row) {
                    if (type === 'display') {
                        return renderDynamicDateHelper(data);
                    }
                    return escapeHtml(data);
                }
            },
            {
                data: 'type',
                render: function(data) { return getTypeBadge(data); }
            },
            { data: 'kategori' },
            { data: 'nama_pemohon' },
            {
                data: 'status',
                render: function(data) { return getStatusBadge(data); }
            },
            {
                data: 'priority',
                render: function(data) { return getPriorityBadge(data); }
            },
            { data: 'due_date' },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    var issueIdAttr = row && row.issue_id ? ' data-issue-id="' + row.issue_id + '"' : '';
                    var actions = '<button class="btn btn-info btn-xs action-btn btn-view"' + issueIdAttr + ' title="Detail"><i class="fas fa-eye"></i></button>';
                    <?php if (!empty($permissions['CanEdit'])): ?>
                    actions += '<button class="btn btn-warning btn-xs action-btn btn-edit"' + issueIdAttr + ' title="Edit"><i class="fas fa-edit"></i></button>';
                    <?php endif; ?>
                    <?php if (!empty($permissions['CanDelete'])): ?>
                    actions += '<button class="btn btn-danger btn-xs action-btn btn-delete"' + issueIdAttr + ' title="Delete"><i class="fas fa-trash"></i></button>';
                    <?php endif; ?>
                    return actions;
                }
            }
        ],
        order: [],
        language: {
            processing: "Memproses...",
            lengthMenu: "Tampilkan _MENU_ data per halaman",
            zeroRecords: "Tidak ada data ditemukan",
            info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
            infoEmpty: "Tidak ada data tersedia",
            infoFiltered: "(disaring dari _MAX_ total data)",
            search: "Cari:",
            paginate: {
                first: "Pertama",
                last: "Terakhir",
                next: "Selanjutnya",
                previous: "Sebelumnya"
            }
        }
    });

    function reloadRiwayatTable() {
        table.ajax.reload(null, false);
    }

    $('#filterType, #filterKategori, #filterSubKategori, #filterTanggalStart, #filterTanggalEnd').on('change', function() {
        reloadRiwayatTable();
    });

    $('#btnReset').on('click', function() {
        $('#filterType').val('');
        $('#filterKategori').val('');
        $('#filterSubKategori').empty().append('<option value="">Semua Sub</option>');
        $('#filterTanggalStart').val('');
        $('#filterTanggalEnd').val('');
        table.search('').draw();
        reloadRiwayatTable();
    });

    $('#btnExportPdf').on('click', function() {
        var filters = collectHistoryFilters();
        filters.scope = 'done';
        var params = new URLSearchParams(filters);
        window.open('export_issues_pdf.php?' + params.toString(), '_blank');
    });

    $(document).on('click', '#riwayatIssuesTable .btn-view', function(e) {
        e.preventDefault();
        var tr = $(this).closest('tr');
        var row = table.row(tr);
        if (!row.data()) {
            tr = tr.prev('tr');
            row = table.row(tr);
        }
        var data = row.data();
        var issueId = $(this).data('issueId');
        if (!issueId && data) {
            issueId = data.issue_id;
        }
        if (!issueId) {
            showError('Data issue tidak ditemukan.');
            return;
        }
        showIssueDetail(issueId);
    });

    $(document).on('click', '#riwayatIssuesTable .btn-edit', function(e) {
        e.preventDefault();
        var tr = $(this).closest('tr');
        var row = table.row(tr);
        if (!row.data()) {
            tr = tr.prev('tr');
            row = table.row(tr);
        }
        var data = row.data();
        if (!data) {
            showError('Data tidak ditemukan.');
            return;
        }

        $('#edit_issue_id').val(data.issue_id);
        $('#edit_nama').val(data.nama_pemohon || '');
        $('#edit_jabatan').val(data.jabatan || '');
        $('#edit_departemen').val(data.departemen || '');
        $('#edit_bagian').val(data.bagian || '');
        $('#edit_tgl_pengajuan').val(data.created_at_raw || '');
        $('#edit_issue_name').val(renderDynamicDateHelper(data.issue_name || ''));
        $('#edit_issue_type').val(data.type).trigger('change');
        $('#edit_status').val(data.status);
        $('#edit_priority').val(data.priority);
        $('#edit_due_date').val(data.due_date);
        $('#edit_description').val(data.description);

        var kat = data.raw_kategori || data.kategori || '';
        var sub = data.raw_sub_kategori || data.sub_kategori || '';
        var assetId = data.asset_id;
        var clientId = data.client_id;

        $editKategori.val(kat).trigger('change');
        if (kat && subKategoriData[kat]) {
            $editSubKategori.empty().append('<option value="">-- Pilih Sub Kategori --</option>');
            subKategoriData[kat].forEach(function(item) {
                $editSubKategori.append('<option value="' + escapeHtml(item) + '">' + escapeHtml(item) + '</option>');
            });
        } else {
            $editSubKategori.empty().append('<option value="">-- Pilih Sub Kategori --</option>');
        }

        if (sub) {
            $editSubKategori.val(sub).trigger('change', [{
                asset_id: assetId,
                client_id: clientId
            }]);
            $editSubKategoriGroup.show();
        } else {
            $editSubKategori.val('').trigger('change');
            $editSubKategoriGroup.hide();
        }

        $editModal.modal('show');
    });

    function fetchClientsGeneric($select, selectedId) {
        if (!$select.length) return;
        $select.prop('disabled', true);
        $select.empty().append('<option value="">Sedang memuat client...</option>');
        $.ajax({
            url: 'get_active_employees.php',
            type: 'GET',
            dataType: 'json',
            success: function(resp) {
                $select.prop('disabled', false);
                $select.empty().append('<option value="">-- Pilih Client --</option>');
                if(resp.success && Array.isArray(resp.employees)) {
                    resp.employees.forEach(function(item) {
                        $select.append(new Option(item.text, item.id, false, false));
                    });
                    if (selectedId) $select.val(selectedId).trigger('change', [{ isPreset: true }]);
                }
            },
            error: function() {
                $select.prop('disabled', false);
                $select.empty().append('<option value="">-- Pilih Client --</option>');
            }
        });
    }

    $('#edit_kategori').on('change', function() {
        var kategori = $(this).val();
        $editSubKategori.empty().append('<option value="">-- Pilih Sub Kategori --</option>');
        $editSubKategori.val(null).trigger('change');
        $editSubKategoriGroup.hide();
        $editAssetGroup.hide();
        resetEditAssetSelect();

        if (kategori && subKategoriData[kategori]) {
            subKategoriData[kategori].forEach(function(sub) {
                $editSubKategori.append('<option value="' + escapeHtml(sub) + '">' + escapeHtml(sub) + '</option>');
            });
            $editSubKategoriGroup.show();
        }
    });

    $('#edit_sub_kategori').on('change', function(e, extraData) {
        var sub = $(this).val();
        var selectedAssetId = (typeof extraData === 'object') ? extraData.asset_id : extraData;
        var selectedClientId = (typeof extraData === 'object') ? extraData.client_id : null;
        var config = (subCategoryConfigCache && subCategoryConfigCache[sub]) ? subCategoryConfigCache[sub] : null;

        if (config && config.supports_asset) {
            $editAssetGroup.show();
            $editAssetSelect.prop('required', false);
            var cid = config.supports_client ? (selectedClientId !== null ? selectedClientId : $editClientSelect.val()) : null;
            fetchAssetsForEdit(config.asset_type || 'all', selectedAssetId, cid);
        } else {
            $editAssetGroup.hide();
            $editAssetSelect.prop('required', false);
            resetEditAssetSelect();
        }

        if (config && config.supports_client) {
            $editClientGroup.show();
            $editClientSelect.prop('required', false);
            fetchClientsGeneric($editClientSelect, selectedClientId);
        } else {
            $editClientGroup.hide();
            $editClientSelect.prop('required', false);
            $editClientSelect.empty().append('<option value="">-- Pilih Client --</option>');
        }
    });

    $editClientSelect.on('change', function(e, extraData) {
        if (extraData && extraData.isPreset) return;
        var sub = $editSubKategori.val();
        var config = (subCategoryConfigCache && subCategoryConfigCache[sub]) ? subCategoryConfigCache[sub] : null;
        if (config && config.supports_asset) {
            fetchAssetsForEdit(config.asset_type || 'all', null, $(this).val());
        }
    });

    function resetEditAssetSelect() {
        if (!$editAssetSelect.length) {
            return;
        }
        $editAssetSelect.prop('disabled', false);
        $editAssetSelect.empty().append('<option value="">-- Pilih Asset --</option>');
        $editAssetSelect.val(null).trigger('change');
    }

    function fetchAssetsForEdit(type, selectedAssetId, clientId = null) {
        if (!$editAssetSelect.length) return;
        $editAssetSelect.prop('disabled', true);
        $editAssetSelect.empty().append('<option value="">Sedang memuat asset...</option>');
        var dataParams = { type: type };
        if (clientId) dataParams.client_id = clientId;
        $.ajax({
            url: './get_assets_select.php',
            type: 'GET',
            data: dataParams,
            dataType: 'json',
            success: function(data) {
                $editAssetSelect.prop('disabled', false);
                $editAssetSelect.empty().append('<option value="">-- Pilih Asset --</option>');
                var hasMatch = false;
                if (Array.isArray(data)) {
                    data.forEach(function(item) {
                        $editAssetSelect.append('<option value="' + item.id + '">' + item.text + '</option>');
                        if (selectedAssetId && String(item.id) === String(selectedAssetId)) {
                            hasMatch = true;
                        }
                    });
                }
                if (hasMatch) $editAssetSelect.val(selectedAssetId).trigger('change');
            },
            error: function() {
                $editAssetSelect.prop('disabled', false);
                resetEditAssetSelect();
            }
        });
    }

    $('#btnUpdateIssue').on('click', function() {
        var form = $('#formIssueEdit')[0];
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        var $btn = $(this);
        $btn.prop('disabled', true);
        // Smart Save for Edit: Convert actual date back to placeholder
        var $editNameInput = $('#edit_issue_name');
        var currentNameVal = $editNameInput.val();
        if (currentNameVal) {
             var todayStr = getDateStringID(0);
             var yesterdayStr = getDateStringID(-1);
             // Check Today
             if (currentNameVal.indexOf(todayStr) !== -1) {
                 currentNameVal = currentNameVal.split(todayStr).join('{DATE}');
             }
             // Check Yesterday
             if (currentNameVal.indexOf(yesterdayStr) !== -1) {
                 currentNameVal = currentNameVal.split(yesterdayStr).join('{DATE-1}');
             }
             $editNameInput.val(currentNameVal);
        }

        $.ajax({
            url: 'save_issue.php',
            type: 'POST',
            data: $('#formIssueEdit').serialize(),
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    showSuccess(resp.message || 'Issue berhasil diupdate.');
                    reloadRiwayatTable();
                    $editModal.modal('hide');
                } else {
                    showError(resp.message || 'Gagal mengupdate issue.');
                }
            },
            error: function() {
                showError('Terjadi kesalahan server.');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    $(document).on('click', '#riwayatIssuesTable .btn-delete', function(e) {
        e.preventDefault();
        var tr = $(this).closest('tr');
        var row = table.row(tr);
        if (!row.data()) {
            tr = tr.prev('tr');
            row = table.row(tr);
        }
        var data = row.data();
        if (!data) {
            showError('Data tidak ditemukan.');
            return;
        }
        Swal.fire({
            title: 'Hapus Issue?',
            text: 'Data yang dihapus tidak dapat dikembalikan!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'save_issue.php',
                    type: 'POST',
                    data: { action: 'delete', issue_id: data.issue_id },
                    dataType: 'json',
                    success: function(resp) {
                        if (resp.success) {
                            showSuccess('Issue berhasil dihapus.');
                            reloadRiwayatTable();
                        } else {
                            showError(resp.message || 'Gagal menghapus data.');
                        }
                    },
                    error: function() {
                        showError('Gagal menghapus data.');
                    }
                });
            }
        });
    });

    $(document).on('click', '#btnAdminEditDates', function(e) {
        e.preventDefault();
        var issueId = $('#edit_issue_id').val();
        var $btn = $(this);
        $btn.addClass('fa-spin disabled');
        $.ajax({
            url: 'get_issue_detail.php',
            type: 'GET',
            data: { issue_id: issueId },
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    var data = resp.data;
                    $('#admin_issue_id').val(data.issue_id);
                    if (data.created_at) $('#admin_created_at').val(data.created_at.replace(' ', 'T'));
                    if (data.tanggal_selesai) {
                        $('#admin_tanggal_selesai').val(data.tanggal_selesai.replace(' ', 'T'));
                    } else {
                        $('#admin_tanggal_selesai').val('');
                    }
                    // Populate Update At
                    if (data.updated_at) {
                        $('#admin_update_at').val(data.updated_at.replace(' ', 'T'));
                    } else {
                        // Fallback to sync with completion date if empty
                        if (data.tanggal_selesai) {
                             $('#admin_update_at').val(data.tanggal_selesai.replace(' ', 'T'));
                        } else {
                             $('#admin_update_at').val('');
                        }
                    }
                    
                    $('#modalAdminEditDates').modal('show');
                } else {
                    showError(resp.message);
                }
            },
            error: function() {
                showError('Gagal mengambil data detail untuk admin.');
            },
            complete: function() {
                $btn.removeClass('fa-spin disabled');
            }
        });
    });

    // Sync Update Last with Completion Date
    $('#admin_tanggal_selesai').on('change input', function() {
        var val = $(this).val();
        $('#admin_update_at').val(val);
    });

    $('#btnAdminUpdateDates').on('click', function() {
        var $btn = $(this);
        var formData = $('#formAdminEditDates').serialize();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');
        $.ajax({
            url: 'update_issue_admin.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    showSuccess(resp.message);
                    $('#modalAdminEditDates').modal('hide');
                    $('#modalIssueEdit').modal('hide');
                    reloadRiwayatTable();
                    var newCreatedAt = $('#admin_created_at').val();
                    if (newCreatedAt) {
                        var parts = newCreatedAt.split('T')[0].split('-');
                        if (parts.length === 3) $('#edit_tgl_pengajuan').val(parts[2] + '-' + parts[1] + '-' + parts[0]);
                    }
                } else {
                    showError(resp.message);
                }
            },
            error: function() {
                showError('Terjadi kesalahan saat menyimpan perubahan administratif.');
            },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan Perubahan');
            }
        });
    });

    function showSuccess(message) {
        Swal.fire({
            icon: 'success',
            title: 'Sukses!',
            text: message,
            timer: 2000,
            showConfirmButton: false
        });
    }

    function showError(message) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: message
        });
    }
});
</script>
