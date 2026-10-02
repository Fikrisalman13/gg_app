<?php
session_start();

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
$lightThemeOptions = ['warning','light','lime','white'];
$isLightTheme = in_array($themeColor, $lightThemeOptions, true);
$issueChipTextClass = $isLightTheme ? 'text-dark' : 'text-white';

// Helper: ambil permission user untuk menu Issues (MenuId = 162)
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
    if ($stmt !== false) sqlsrv_free_stmt($stmt);

    return $permissions;
}

// Ambil permission
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 162);
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}

// Get auto-fill data from m_emp
$username = htmlspecialchars($_SESSION['NamaLengkap'] ?? $_SESSION['UserName']);
$jabatan = $departemen = $bagian = '';

try {
    $sql = "SELECT m_jab.jabatan, m_dept.dept, m_bag.bagian
            FROM dbo.m_emp
            LEFT JOIN dbo.m_jab ON m_emp.id_jab = m_jab.id_jab
            LEFT JOIN dbo.m_subbag ON m_emp.id_subbag = m_subbag.id_subbag
            LEFT JOIN dbo.m_bag ON m_subbag.id_bag = m_bag.id_bag
            LEFT JOIN dbo.m_dept ON m_bag.id_dept = m_dept.id_dept
            WHERE m_emp.nama_lengkap = ?";
    $params = array($username);
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
} catch (Exception $e) {}

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
?>

<style>
    .action-btn {
        margin-right: 6px;
    }
    
    /* DataTables Responsive Detail Styling */
    table.dataTable.dtr-inline.collapsed > tbody > tr > td.dtr-control:before,
    table.dataTable.dtr-inline.collapsed > tbody > tr > th.dtr-control:before {
        background-color: #007bff;
        border: none;
        box-shadow: none;
        line-height: 1em;
        top: 50%;
        transform: translateY(-50%);
    }
    
    /* Badge styling for Type */
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
    
    .type-task { background-color: #007bff; color: white; }
    .type-maintenance { background-color: #fd7e14; color: white; }
    .type-bug { background-color: #dc3545; color: white; }
    .type-improvement { background-color: #28a745; color: white; }
    .type-new-feature { background-color: #6f42c1; color: white; }
    .type-support-activities { background-color: #17a2b8; color: white; }
    .type-story { background-color: #17a2b8; color: white; } /* Legacy */
    
    /* Status badges */
    .status-todo { background-color: #6c757d; }
    .status-inprogress { background-color: #ffc107; color: #212529; }
    .status-done { background-color: #28a745; }
    .status-closed { background-color: #343a40; }
    
    /* Priority badges */
    .priority-low { background-color: #17a2b8; }
    .priority-normal { background-color: #007bff; }
    .priority-high { background-color: #dc3545; }
    
    /* Auto-filled field styling */
    .auto-filled {
        background-color: #e9ecef;
        cursor: not-allowed;
    }

    /* Issue type select with icons */
    .issue-type-option {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 0.9rem;
        line-height: 1.1;
        vertical-align: middle;
        position: relative;
        top: -1px;
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

    /* Issue name chips styling */
    #issue_names_list {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 6px; /* vertical horizontal */
        align-items: center;
        min-height: 0;
    }
    #issue_names_list.is-empty {
        gap: 0;
    }
    #issue_names_list .badge {
        padding: 0.35rem 0.5rem;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        white-space: nowrap;
        border: 1px solid transparent;
        transition: box-shadow 0.2s ease, border-color 0.2s ease;
    }
    #issue_names_list .issue-name-chip.bg-white,
    #issue_names_list .issue-name-chip.bg-light,
    #issue_names_list .issue-name-chip.bg-warning,
    #issue_names_list .issue-name-chip.bg-lime {
        border-color: rgba(0,0,0,0.12);
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.5);
    }
    #issue_names_list .issue-name-chip .chip-label { cursor: pointer; }
    #issue_names_list .remove-name {
        cursor: pointer;
        text-decoration: none;
        color: inherit;
        opacity: 0.85;
        font-weight: 600;
        margin-left: 6px;
        transition: opacity 0.2s ease;
    }
    #issue_names_list .remove-name:hover {
        opacity: 1;
    }

    /* Responsive DataTables styling (match ticket history behavior) */
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

    /* Detail modal styling */
    .issue-detail-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: nowrap;
        gap: 1rem;
    }
    .issue-detail-header > div:first-child {
        flex: 1;
        min-width: 0; /* Allows text truncation if needed */
        padding-right: 15px;
    }
    .issue-detail-header > div:last-child {
        flex-shrink: 0;
        text-align: right;
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
</style>

<div class="wrapper">

<div class="content-wrapper">

<section class="content-header">
    <div class="container-fluid">
        <h1>IT Issues & Activities</h1>
    </div>
</section>

<section class="content">
<div class="container-fluid">

<div class="card card-primary">
    <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
        <h3 class="card-title"><i class="fas fa-list mr-1"></i>
        Daftar Issues</h3>        
        <?php if (!empty($permissions['CanAdd']) && $permissions['CanAdd'] == 1): ?>
            <button type="button" class="btn btn-success btn-sm float-right" id="btnTambahIssue">
                <i class="fas fa-plus"></i> Tambah Issue
            </button>
        <?php endif; ?>
    </div>

    <div class="card-body">
        
        <!-- Filter Section -->
        <div class="filter-section p-3 mb-3" style="background-color: #f8f9fa; border-radius: 5px;">
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
                <div class="col-md-3">
                    <label class="small mb-1" for="filterTanggalStart">Tanggal Mulai</label>
                    <input type="date" id="filterTanggalStart" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <label class="small mb-1" for="filterTanggalEnd">Tanggal Akhir</label>
                    <input type="date" id="filterTanggalEnd" class="form-control form-control-sm">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button id="btnReset" class="btn btn-secondary btn-sm mr-2" title="Reset Filter">
                        <i class="fas fa-sync"></i> Reset
                    </button>
                    <button id="btnExportPdf" class="btn btn-danger btn-sm">
                        <i class="fas fa-file-pdf"></i> Export PDF
                    </button>
                </div>
            </div>
        </div>

        <div class="table-responsive">
        <table id="issuesTable" class="table table-hover table-sm nowrap issues-table" style="width:100%">
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

            <tbody>
                <!-- Data loaded via AJAX / Server-side processing -->
            </tbody>

        </table>
        </div>

    </div>
</div>

</div>
</section>

</div>

</div>

<!-- Modal Add (existing) and Edit (separate) -->
<?php include 'modal_add_issue.php'; ?>
<?php include 'modal_edit_issue.php'; ?>
<?php include 'modal_view_issue.php'; ?>

<?php include '../../includes/footer.php'; ?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<!-- DataTables JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<!-- SweetAlert -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
var userThemeColor = <?php echo json_encode($themeColor); ?>;
var issueChipTextClass = <?php echo json_encode($issueChipTextClass); ?>;

$(document).ready(function() {
// Data loaded from server
    // var sampleData = ... removed

    var issueTypeMeta = {
        'Task': { label: 'Task', icon: 'fas fa-check-square', bg: '#1f8ef1', color: '#ffffff' },
        'Maintenance': { label: 'Maintenance', icon: 'fas fa-tools', bg: '#f59e0b', color: '#ffffff' },
        'Bug': { label: 'Bug', icon: 'fas fa-bug', bg: '#f97316', color: '#ffffff' },
        'Improvement': { label: 'Improvement', icon: 'fas fa-arrow-up', bg: '#0ea5e9', color: '#ffffff' },
        'New Feature': { label: 'New Feature', icon: 'fas fa-plus', bg: '#22c55e', color: '#ffffff' },
        'Support Activities': { label: 'Support Activities', icon: 'fas fa-circle', bg: 'transparent', color: '#e11d48', border: '1px solid #e11d48', shape: 'circle' },
        'Story': { label: 'Story', icon: 'fas fa-circle', bg: 'transparent', color: '#e11d48', border: '1px solid #e11d48', shape: 'circle' } // Legacy
    };

    var $assetGroup = $('#assetGroup');
    var $assetSelect = $('#asset_id');
    var $modalIssue = $('#modalIssue');
    var $detailModal = $('#modalIssueDetail');
    var $detailContent = $('#issueDetailContent');
    var $detailLoading = $('#issueDetailLoading');
    var $detailError = $('#issueDetailError');
    var $templatePickerModal = $('#modalTemplatePicker');
    var $btnShowTemplatePicker = $('#btnShowTemplatePicker');
    var assetDropdownPlaceholder = $assetSelect.data('placeholder') || '-- Pilih Asset --';
    var assetLoadingLabel = 'Sedang memuat asset...';
    var assetSelectWarningShown = false;
    var issueTemplateCache = null;
    var userDefaults = {
        nama: <?php echo json_encode($username); ?>,
        jabatan: <?php echo json_encode($jabatan); ?>,
        departemen: <?php echo json_encode($departemen); ?>,
        bagian: <?php echo json_encode($bagian); ?>
    };
    var defaultTanggalPengajuan = <?php echo json_encode(date('d-m-Y')); ?>;
    var issueNames = []; // array of strings
    var isFromTemplate = false; // Flag to track if form is filled from template

    function renderIssueNames() {
        var $container = $('#issue_names_list');
        if (!$container.length) return;
        $container.empty();
        issueNames.forEach(function(name, idx) {
            // Chip uses user theme color to stay consistent with the selected AdminLTE theme
            var chipClasses = ['badge', 'badge-pill', 'mr-1', 'mb-1', 'issue-name-chip', 'bg-' + userThemeColor];
            if (issueChipTextClass) {
                chipClasses.push(issueChipTextClass);
            }
            var $chip = $('<span>', {
                'class': chipClasses.join(' '),
                'data-idx': idx
            }).css({ fontSize: '90%', display: 'inline-flex', alignItems: 'center', gap: '6px' });

            var $label = $('<span>', {
                'class': 'chip-label',
                'data-idx': idx
            }).text(name);

            var removeClasses = ['remove-name'];
            if (issueChipTextClass) {
                removeClasses.push(issueChipTextClass);
            }
            var $remove = $('<a>', {
                href: '#',
                'class': removeClasses.join(' '),
                'data-idx': idx,
                title: 'Hapus'
            }).html('&times;');

            $chip.append($label).append($remove);
            $container.append($chip);
        });
        $container.toggleClass('is-empty', issueNames.length === 0);
        $('#issue_name').val(issueNames.length ? issueNames[0] : '');
        $('#issue_names_json').val(JSON.stringify(issueNames));
    }

    // add name from input (trim, ignore empty)
    function addIssueNameFromInput() {
        var $input = $('#issue_name_input');
        if (!$input.length) return;
        var v = $input.val().trim();
        if (!v) return;
        issueNames.push(v);
        $input.val('');
        isFromTemplate = false; // Reset template flag on change
        renderIssueNames();
        $input.focus();
    }

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
        var label = escapeHtml(meta.label || option.text);
        return '<span class="issue-type-option"><span class="issue-type-icon" style="background-color:' + bg + ';color:' + color + ';border:' + border + ';border-radius:' + radius + ';">' + icon + '</span><span>' + label + '</span></span>';
    }

    function initAssetSearchDropdown() {
        if (!$assetSelect.length) {
            return;
        }
        if (typeof $.fn.select2 !== 'function') {
            if (!assetSelectWarningShown) {
                console.warn('Select2 belum dimuat. Fitur pencarian asset tidak aktif.');
                assetSelectWarningShown = true;
            }
            return;
        }
        if ($assetSelect.hasClass('select2-hidden-accessible')) {
            return;
        }
        $assetSelect.select2({
            theme: 'bootstrap4',
            width: '100%',
            dropdownParent: $modalIssue,
            placeholder: assetDropdownPlaceholder,
            allowClear: true,
            language: {
                noResults: function() { return 'Asset tidak ditemukan'; },
                searching: function() { return 'Mencari...'; }
            }
        });
    }

    function resetAssetSelect() {
        if (!$assetSelect.length) {
            return;
        }
        $assetSelect.val(null).trigger('change');
    }

    initAssetSearchDropdown();
    initIssueTypeSelect();
    initIssueTemplateSelect();

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
            var dropdownParent = parentModal.length ? parentModal : ($modalIssue.length ? $modalIssue : $(document.body));
            var typePlaceholder = $select.data('placeholder') || '-- Pilih Type --';
            $select.select2({
                theme: 'bootstrap4',
                width: '100%',
                dropdownParent: dropdownParent,
                placeholder: typePlaceholder,
                allowClear: true,
                templateResult: renderIssueTypeMarkup,
                templateSelection: renderIssueTypeMarkup,
                escapeMarkup: function(markup) { return markup; }
            });
        });
    }

    function initIssueTemplateSelect() {
        // Template list akan di-populate saat modal dibuka
        populateIssueTemplateOptions([]);
    }

    function toggleIssueTemplateGroup(shouldShow) {
        // Function kept for compatibility but not needed anymore
        // Template visibility is now handled in populateTemplateList
    }

    function toggleTemplateTrigger(hasTemplates) {
        if (!$btnShowTemplatePicker.length) {
            return;
        }
        // Requirement: Tombol Gunakan Template Tidak Akan Muncul Jika Akun tersebut Tidak Mempunyai Template
        if (hasTemplates) {
            $btnShowTemplatePicker.show();
            $btnShowTemplatePicker.prop('disabled', false); // Ensure enabled
            $btnShowTemplatePicker.attr('title', 'Gunakan issue sebelumnya');
        } else {
            $btnShowTemplatePicker.hide();
        }
    }

    function setIssueTemplateLoading(isLoading) {
        var $emptyState = $('#issueTemplateEmptyState');
        var $container = $('#templateListContainer');
        if (isLoading) {
            $container.hide();
            $emptyState.html('<i class="fas fa-spinner fa-spin fa-2x mb-3"></i><p>Memuat template...</p>').show();
            toggleTemplateTrigger(false);
        }
    }

    function resetIssueTemplateSelection() {
        // Function kept for compatibility but not needed anymore
    }

    function populateIssueTemplateOptions(templates) {
        var hasTemplates = Array.isArray(templates) && templates.length > 0;
        toggleTemplateTrigger(hasTemplates);
        
        // Populate template list with delete buttons
        populateTemplateList(templates);
    }
    
    function populateTemplateList(templates, searchTerm) {
        var $container = $('#templateListContainer');
        var $listItems = $('#templateListItems');
        var $emptyState = $('#issueTemplateEmptyState');
        var $searchInput = $('#templateSearchInput');
        var $btnReset = $('#btnResetTemplateSearch');

        if (!$container.length || !$listItems.length) {
            return;
        }

        // Simpan referensi ke item pencarian agar tidak terhapus saat empty()
        var $searchWrapper = $searchInput.closest('.mb-3');
        $listItems.find('.template-item').remove();
        $listItems.find('.no-results-item').remove();

        var displayTemplates = templates;
        if (searchTerm) {
            searchTerm = searchTerm.toLowerCase();
            displayTemplates = templates.filter(function(item) {
                var name = (item.template_name || '').toLowerCase();
                var type = (item.issue_type || '').toLowerCase();
                var cat = (item.kategori || '').toLowerCase();
                var subCat = (item.sub_kategori || '').toLowerCase();
                return name.includes(searchTerm) || 
                       type.includes(searchTerm) || 
                       cat.includes(searchTerm) || 
                       subCat.includes(searchTerm);
            });
            $btnReset.show();
        } else {
            $btnReset.hide();
        }

        if (Array.isArray(displayTemplates) && displayTemplates.length > 0) {
            displayTemplates.forEach(function(item) {
                // Parse issue names untuk cek jumlah
                var issueNamesCount = 1;
                var issueNamesPreview = '';
                if (item.issue_names_json) {
                    try {
                        var parsedNames = JSON.parse(item.issue_names_json);
                        if (Array.isArray(parsedNames) && parsedNames.length > 0) {
                            issueNamesCount = parsedNames.length;
                            // Ambil 3 nama pertama untuk preview
                            var previewNames = parsedNames.slice(0, 3);
                            issueNamesPreview = previewNames.map(function(n) {
                                return escapeHtml(n);
                            }).join(', ');
                            if (parsedNames.length > 3) {
                                issueNamesPreview += '...';
                            }
                        }
                    } catch (e) {
                        // Jika parsing gagal, gunakan default
                    }
                }
                
                var itemHtml = '<div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center template-item" style="cursor: pointer; padding: 0.75rem 1rem;" data-template-id="' + item.template_id + '">';
                itemHtml += '<div class="flex-grow-1">';
                itemHtml += '<div class="d-flex align-items-center mb-1">';
                itemHtml += '<strong class="mr-2">' + escapeHtml(item.template_name) + '</strong>';
                if (item.issue_type) {
                    itemHtml += '<span class="badge badge-info mr-1">' + escapeHtml(item.issue_type) + '</span>';
                }
                // Badge untuk multi issue names
                if (issueNamesCount > 1) {
                    itemHtml += '<span class="badge badge-success" title="Template ini memiliki ' + issueNamesCount + ' issue names">';
                    itemHtml += '<i class="fas fa-layer-group mr-1"></i>' + issueNamesCount + ' Issues';
                    itemHtml += '</span>';
                }
                itemHtml += '</div>';
                
                // Preview issue names jika ada
                if (issueNamesPreview) {
                    itemHtml += '<small class="text-primary d-block mb-1" title="Issue Names: ' + escapeHtml(issueNamesPreview) + '">';
                    itemHtml += '<i class="fas fa-tags mr-1"></i>' + issueNamesPreview;
                    itemHtml += '</small>';
                }
                
                // Kategori info
                if (item.kategori) {
                    itemHtml += '<small class="text-muted d-block"><i class="fas fa-folder mr-1"></i>' + escapeHtml(item.kategori);
                    if (item.sub_kategori) {
                        itemHtml += ' > ' + escapeHtml(item.sub_kategori);
                    }
                    itemHtml += '</small>';
                }
                itemHtml += '</div>';
                itemHtml += '<div class="d-flex align-items-center">';
                itemHtml += '<button class="btn btn-danger btn-sm btn-delete-template ml-2" data-template-id="' + item.template_id + '" data-template-name="' + escapeHtml(item.template_name) + '" title="Hapus template" style="height: fit-content;">';
                itemHtml += '<i class="fas fa-trash"></i>';
                itemHtml += '</button>';
                itemHtml += '</div>';
                itemHtml += '</div>';
                
                $listItems.append(itemHtml);
            });
            $container.show();
            $emptyState.hide();
        } else {
            if (searchTerm) {
                $listItems.append('<div class="text-center py-4 no-results-item"><i class="fas fa-search fa-2x text-muted mb-2"></i><p class="text-muted">Tidak ada template yang cocok dengan pencarian Anda.</p></div>');
                $container.show();
                $emptyState.hide();
            } else {
                $container.hide();
                $emptyState.html('<div class="text-center py-4"><i class="fas fa-folder-open fa-3x text-muted mb-3"></i><p class="text-muted">Belum ada template yang tersimpan.</p></div>');
                $emptyState.show();
            }
        }
    }

    // Search event listener
    $(document).on('input', '#templateSearchInput', function() {
        var val = $(this).val();
        populateTemplateList(issueTemplateCache || [], val);
    });

    $(document).on('click', '#btnResetTemplateSearch', function() {
        $('#templateSearchInput').val('').trigger('input').focus();
    });

    // Reset search when modal is shown
    $('#modalTemplatePicker').on('show.bs.modal', function() {
        $('#templateSearchInput').val('');
        $('#btnResetTemplateSearch').hide();
        // Pastikan list kembali ke kondisi awal (tanpa filter)
        if (Array.isArray(issueTemplateCache)) {
            populateTemplateList(issueTemplateCache);
        }
    });

    function loadIssueTemplates(forceRefresh) {
        toggleTemplateTrigger(false);
        if (forceRefresh) {
            issueTemplateCache = null;
        }
        if (Array.isArray(issueTemplateCache)) {
            populateIssueTemplateOptions(issueTemplateCache);
            return;
        }
        setIssueTemplateLoading(true);
        $.ajax({
            url: 'get_user_issue_templates.php',
            type: 'GET',
            dataType: 'json',
            cache: false,
            success: function(resp) {
                if (resp && resp.success) {
                    issueTemplateCache = Array.isArray(resp.templates) ? resp.templates : [];
                } else {
                    issueTemplateCache = [];
                    if (resp && resp.message) {
                        console.warn(resp.message);
                    }
                }
                setIssueTemplateLoading(false);
                populateIssueTemplateOptions(issueTemplateCache);
            },
            error: function() {
                issueTemplateCache = [];
                setIssueTemplateLoading(false);
                populateIssueTemplateOptions(issueTemplateCache);
                console.error('Gagal memuat template issue.');
            }
        });
    }

    function prepareIssueFormForNewEntry(options) {
        options = options || {};
        var shouldReloadTemplates = options.reloadTemplates !== false;
        var $form = $('#formIssue');
        if ($form.length) {
            $form[0].reset();
        }
        $('#issue_id').val('');
        $('#action').val('add');
        $('#nama').val(userDefaults.nama || '');
        $('#jabatan').val(userDefaults.jabatan || '');
        $('#departemen').val(userDefaults.departemen || '');
        $('#bagian').val(userDefaults.bagian || '');
        $('#tgl_pengajuan').val(defaultTanggalPengajuan);
        $('#due_date').val(new Date().toISOString().split('T')[0]);
        $('#status').val('To Do');
        $('#priority').val('Normal');
        $('#issue_type').val('').trigger('change');
        $('#kategori').val('').trigger('change');
        $('#sub_kategori').val('').trigger('change');
        if (shouldReloadTemplates) {
            loadIssueTemplates(true);
        } else {
            var hasTemplates = Array.isArray(issueTemplateCache) && issueTemplateCache.length > 0;
            toggleTemplateTrigger(hasTemplates);
        }
        $('#subKategoriGroup').hide();
        $assetGroup.hide();
        $('#subDetailGroup').hide();
        $assetSelect.prop('required', false);
        resetAssetSelect();
        // reset multi-name UI
        issueNames = [];
        renderIssueNames();
        isFromTemplate = false; // Reset template flag
    }

    function applyIssueTemplate(template) {
        if (!template) {
            return;
        }
        
        isFromTemplate = true; // Set template flag

        // Restore issue names dari template
        if (template.issue_names_json) {
            try {
                var parsedNames = JSON.parse(template.issue_names_json);
                if (Array.isArray(parsedNames) && parsedNames.length > 0) {
                    issueNames = parsedNames;
                } else {
                    issueNames = [template.template_name || ''];
                }
            } catch (e) {
                // Jika parsing gagal, gunakan template_name
                issueNames = [template.template_name || ''];
            }
        } else {
            issueNames = [template.template_name || ''];
        }
        renderIssueNames();
        
        $('#issue_type').val(template.issue_type || '').trigger('change');
        // Apply status from template if available; otherwise keep default 'To Do'
        if (template.status) {
            $('#status').val(template.status);
        } else {
            $('#status').val('To Do');
        }
        $('#priority').val(template.priority || 'Normal');
        $('#description').val(template.description || '');

        var kategoriValue = template.kategori || '';
        $('#kategori').val(kategoriValue).trigger('change');

        if (kategoriValue && template.sub_kategori) {
            $('#sub_kategori').val(template.sub_kategori).trigger('change', [template.asset_id]);
        } else {
            $('#sub_kategori').val('').trigger('change');
        }
    }
    
    // Function to get type badge
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
        return badges[type] || type;
    }
    
    function renderBadge(meta, fallback) {
        if (!meta) {
            return escapeHtml(fallback || '-');
        }
        var icon = meta.icon ? '<i class="' + meta.icon + '"></i>' : '';
        return '<span class="badge badge-type ' + meta.className + '">' + icon + '<span>' + escapeHtml(meta.label) + '</span></span>';
    }

    // Function to get status badge
    function getStatusBadge(status) {
        var map = {
            'To Do': { label: 'To Do', className: 'status-todo', icon: 'fas fa-list-ul' },
            'In Progress': { label: 'In Progress', className: 'status-inprogress', icon: 'fas fa-spinner' },
            'Done': { label: 'Done', className: 'status-done', icon: 'fas fa-check' },
            'Closed': { label: 'Closed', className: 'status-closed', icon: 'fas fa-lock' }
        };
        return renderBadge(map[status], status);
    }
    
    // Function to get priority badge
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
        return datePart + ' ' + timePart;
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
        $('#detailIssueName').text(data.issue_name || '-');

        var typeBadge = data.issue_type ? getTypeBadge(data.issue_type) : '<span class="badge badge-secondary">Type belum diisi</span>';
        var statusBadge = data.status ? getStatusBadge(data.status) : '<span class="badge badge-secondary">Status belum diisi</span>';
        var priorityBadge = data.priority ? getPriorityBadge(data.priority) : '<span class="badge badge-secondary">Priority belum diisi</span>';
        $('#detailTypeBadge').html(typeBadge);
        $('#detailStatusBadge').html(statusBadge);
        $('#detailPriorityBadge').html(priorityBadge);
        $('#detailStatusText').text(data.status || '-');
        $('#detailPriorityText').text(data.priority || '-');

        $('#detailDueDate').text(formatDetailDate(data.due_date));
        $('#detailKategori').text(data.kategori || '-');
        $('#detailSubKategori').text(data.sub_kategori || '-');
        $('#detailAsset').text(data.asset_label || 'Tidak ada asset terhubung');

        $('#detailNama').text(data.created_by || '-');
        $('#detailJabatan').text(data.jabatan || '-');
        $('#detailDepartemen').text(data.departemen || '-');
        $('#detailBagian').text(data.bagian || '-');

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

    function collectIssueFilters() {
        return {
            filterType: $('#filterType').val() || '',
            filterTanggalStart: $('#filterTanggalStart').val() || '',
            filterTanggalEnd: $('#filterTanggalEnd').val() || ''
        };
    }
    
// Initialize DataTable
    var table = $('#issuesTable').DataTable({
        ajax: {
            url: 'get_issues.php',
            type: 'POST',
            data: function(d) {
                var filters = collectIssueFilters();
                filters.scope = 'active';
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
            { responsivePriority: 1, targets: -1 }, /* Aksi should be most important */
            { responsivePriority: 2, targets: 1 },  /* Issue Name */
            { responsivePriority: 3, targets: 4 },  /* Nama pemohon */
            { responsivePriority: 4, targets: 5 },  /* Status */
            { responsivePriority: 6, targets: 6 },  /* Priority - lower priority to hide */
        ],
        columns: [
            { data: 'no' },
            { data: 'issue_name' },
            { 
                data: 'type',
                render: function(data) {
                    return getTypeBadge(data);
                }
            },
            { data: 'kategori' },
            { data: 'nama_pemohon' },
            { 
                data: 'status',
                render: function(data) {
                    return getStatusBadge(data);
                }
            },
            { 
                data: 'priority',
                render: function(data) {
                    return getPriorityBadge(data);
                }
            },
            { data: 'due_date' },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    var issueIdAttr = row && row.issue_id ? ' data-issue-id="' + row.issue_id + '"' : '';
                    var actions = '<button class="btn btn-info btn-xs action-btn btn-view"' + issueIdAttr + ' title="Detail"><i class="fas fa-eye"></i></button>';
                    <?php if ($permissions['CanEdit']): ?>
                    actions += '<button class="btn btn-warning btn-xs action-btn btn-edit"' + issueIdAttr + ' title="Edit"><i class="fas fa-edit"></i></button>';
                    <?php endif; ?>
                    <?php if ($permissions['CanDelete']): ?>
                    actions += '<button class="btn btn-danger btn-xs action-btn btn-delete"' + issueIdAttr + ' title="Delete"><i class="fas fa-trash"></i></button>';
                    <?php endif; ?>
                    return actions;
                }
            }
        ],
        order: [[1, 'desc']],
        // keep language options unchanged
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
    function reloadIssuesTable() {
        table.ajax.reload(null, false);
    }

    $('#filterType, #filterTanggalStart, #filterTanggalEnd').on('change', function() {
        reloadIssuesTable();
    });

    $(document).on('click', '#issuesTable .btn-view', function(e) {
        e.preventDefault();
        var tr = $(this).closest('tr');
        var row = table.row(tr);
        if (!row.data()) {
            tr = tr.prev('tr');
            row = table.row(tr);
        }
        var rowData = row.data();
        var issueId = $(this).data('issueId');
        if (!issueId && rowData) {
            issueId = rowData.issue_id;
        }
        if (!issueId) {
            showError('Data issue tidak ditemukan.');
            return;
        }
        showIssueDetail(issueId);
    });
    
    $btnShowTemplatePicker.on('click', function() {
        if ($(this).prop('disabled')) {
            return;
        }
        if (!Array.isArray(issueTemplateCache)) {
            loadIssueTemplates(true);
        }
        $templatePickerModal.modal('show');
    });

    // Handler klik pada template item
    $(document).on('click', '.template-item', function(e) {
        // Jangan trigger jika klik tombol delete
        if ($(e.target).closest('.btn-delete-template').length) {
            return;
        }
        
        var templateId = $(this).data('template-id');
        console.log('Template clicked:', templateId);
        
        if (!templateId || !Array.isArray(issueTemplateCache)) {
            showError('Template tidak ditemukan.');
            return;
        }
        
        var templateData = issueTemplateCache.find(function(item) {
            return String(item.template_id) === String(templateId);
        });
        
        if (!templateData) {
            showError('Template tidak ditemukan.');
            return;
        }
        
        console.log('Applying template:', templateData);
        applyIssueTemplate(templateData);
        $templatePickerModal.modal('hide');
    });

    // Cascading dropdown data is defined globally in modal_add_issue.php
    // var subKategoriData = { ... };

    // Initialize Select2 for Kategori & Sub Kategori
    $('#kategori').select2({
        theme: 'bootstrap4',
        width: '100%',
        dropdownParent: $('#modalIssue'),
        placeholder: '-- Pilih Kategori --',
        allowClear: true
    });

    $('#sub_kategori').select2({
        theme: 'bootstrap4',
        width: '100%',
        dropdownParent: $('#modalIssue'),
        placeholder: '-- Pilih Sub Kategori --',
        allowClear: true
    });
    
    // Initialize select2 for Edit modal's kategori/sub_kategori and issue type
    $('#edit_kategori').select2({
        theme: 'bootstrap4',
        width: '100%',
        dropdownParent: $('#modalIssueEdit'),
        placeholder: '-- Pilih Kategori --',
        allowClear: true
    });

    $('#edit_sub_kategori').select2({
        theme: 'bootstrap4',
        width: '100%',
        dropdownParent: $('#modalIssueEdit'),
        placeholder: '-- Pilih Sub Kategori --',
        allowClear: true
    });

    // Cascading dropdown logic for edit modal
    $('#edit_kategori').on('change', function() {
        var kategori = $(this).val();
        var $subKategori = $('#edit_sub_kategori');
        $subKategori.empty().append('<option value="">-- Pilih Sub Kategori --</option>');
        $subKategori.val(null).trigger('change');
        $('#edit_subKategoriGroup').hide();
        $('#edit_assetGroup').hide();
        $('#edit_asset_id').prop('required', false);
        $('#edit_asset_id').empty().append('<option value="">-- Pilih Asset --</option>');

        if (kategori && subKategoriData[kategori]) {
            subKategoriData[kategori].forEach(function(sub) {
                $subKategori.append('<option value="' + escapeHtml(sub) + '">' + escapeHtml(sub) + '</option>');
            });
            $('#edit_subKategoriGroup').show();
            $subKategori.prop('required', true);
        } else {
            $subKategori.prop('required', false);
        }
    });

    // edit sub_kategori change => show assets when needed
    $('#edit_sub_kategori').on('change', function(e, selectedAssetId) {
        var sub = $(this).val();
        var cctvSubs = ['Perbaikan CCTV/NVR/DVR', 'Instalasi CCTV baru'];
        var assetSubs = ['Instalasi Asset', 'Perbaikan hardware', 'Testing koneksi jaringan'];

        if (cctvSubs.includes(sub)) {
            $('#edit_assetGroup').show();
            $('#edit_asset_id').prop('required', true);
            fetchAssetsForEdit('cctv', selectedAssetId);
        } else if (assetSubs.includes(sub)) {
            $('#edit_assetGroup').show();
            $('#edit_asset_id').prop('required', true);
            fetchAssetsForEdit('all', selectedAssetId);
        } else {
            $('#edit_assetGroup').hide();
            $('#edit_asset_id').prop('required', false);
            $('#edit_asset_id').empty().append('<option value="">-- Pilih Asset --</option>');
        }
    });

    function fetchAssetsForEdit(type, selectedAssetId) {
        var $sel = $('#edit_asset_id');
        $sel.prop('disabled', true);
        $sel.empty().append('<option value="">Sedang memuat asset...</option>');
        $.ajax({
            url: './get_assets_select.php',
            type: 'GET',
            data: { type: type },
            dataType: 'json',
            success: function(data) {
                $sel.prop('disabled', false);
                $sel.empty().append('<option value="">-- Pilih Asset --</option>');
                var hasMatch = false;
                if (Array.isArray(data)) {
                    data.forEach(function(item) {
                        $sel.append('<option value="' + item.id + '">' + item.text + '</option>');
                        if (selectedAssetId && String(item.id) === String(selectedAssetId)) {
                            hasMatch = true;
                        }
                    });
                }
                if (hasMatch) {
                    $sel.val(selectedAssetId).trigger('change');
                }
            },
            error: function() {
                $sel.prop('disabled', false);
                $sel.empty().append('<option value="">-- Pilih Asset --</option>');
                console.error('Gagal memuat daftar asset untuk edit.');
            }
        });
    }
    
    // Cascading dropdown logic
    $('#kategori').on('change', function() {
        var kategori = $(this).val();
        var $subKategori = $('#sub_kategori');
        
        $subKategori.empty().append('<option value="">-- Pilih Sub Kategori --</option>');
        
        // Reset sub select2
        $subKategori.val(null).trigger('change');

        $('#subKategoriGroup').hide();
        $assetGroup.hide();
        $('#subDetailGroup').hide();
        $assetSelect.prop('required', false);
        resetAssetSelect();
        
        if (kategori && subKategoriData[kategori]) {
            subKategoriData[kategori].forEach(function(sub) {
                $subKategori.append('<option value="' + sub + '">' + sub + '</option>');
            });
            $('#subKategoriGroup').show();
            $subKategori.prop('required', true);
        } else {
            $subKategori.prop('required', false);
        }
    });

        // issue name input Enter handler
        $(document).on('keydown', '#issue_name_input', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addIssueNameFromInput();
            }
        });

        // remove name from chip
        $(document).on('click', '#issue_names_list .remove-name', function(e) {
            e.preventDefault();
            var idx = parseInt($(this).data('idx'));
            if (!isNaN(idx) && idx >= 0 && idx < issueNames.length) {
                issueNames.splice(idx, 1);
                isFromTemplate = false; // Reset template flag on change
                renderIssueNames();
            }
        });

        // click on chip label to edit that name
        $(document).on('click', '#issue_names_list .chip-label', function(e) {
            e.preventDefault();
            var idx = parseInt($(this).attr('data-idx'));
            if (isNaN(idx) || idx < 0 || idx >= issueNames.length) return;

            var name = issueNames[idx];
            // remove the clicked name from the list so user edits it and re-adds
            issueNames.splice(idx, 1);
            renderIssueNames();

            // populate input with the name for editing
            var $input = $('#issue_name_input');
            $input.val(name).focus().select();
            isFromTemplate = false; // editing overrides template flag
        });

    // Function to fetch assets
    function fetchAssets(type, selectedAssetId = null) {
        initAssetSearchDropdown();
        if (!$assetSelect.length) {
            return;
        }

        var previousValue = selectedAssetId || $assetSelect.val();
        $assetSelect.prop('disabled', true);
        $assetSelect.empty().append('<option value="">' + assetLoadingLabel + '</option>');
        $assetSelect.trigger('change');
        
        $.ajax({
            url: './get_assets_select.php',
            type: 'GET',
            data: { type: type },
            dataType: 'json',
            success: function(data) {
                $assetSelect.prop('disabled', false);
                $assetSelect.empty().append('<option value="">' + assetDropdownPlaceholder + '</option>');
                var hasMatch = false;
                if (Array.isArray(data)) {
                    data.forEach(function(item) {
                        if (previousValue && String(item.id) === String(previousValue)) {
                            hasMatch = true;
                        }
                        $assetSelect.append('<option value="' + item.id + '">' + item.text + '</option>');
                    });
                }
                if (hasMatch) {
                    $assetSelect.val(previousValue).trigger('change');
                } else {
                    resetAssetSelect();
                }
            },
            error: function() {
                $assetSelect.prop('disabled', false);
                resetAssetSelect();
                showError('Gagal memuat daftar asset. Silakan coba ulang.');
            }
        });
    }

    // Logic Sub Kategori Change (Show Asset)
    // Logic Sub Kategori Change (Show Asset)
    $('#sub_kategori').on('change', function(e, selectedAssetId) {
        var sub = $(this).val();
        var cctvSubs = ['Perbaikan CCTV/NVR/DVR', 'Instalasi CCTV baru'];
        var assetSubs = ['Instalasi Asset', 'Perbaikan hardware', 'Testing koneksi jaringan'];
        
        if (cctvSubs.includes(sub)) {
            $assetGroup.show();
            $assetSelect.prop('required', true);
            resetAssetSelect();
            fetchAssets('cctv', selectedAssetId);
        } else if (assetSubs.includes(sub)) {
            $assetGroup.show();
            $assetSelect.prop('required', true);
            resetAssetSelect();
            fetchAssets('all', selectedAssetId);
        } else {
            $assetGroup.hide();
            $assetSelect.prop('required', false);
            resetAssetSelect();
        }
    });
    
    // Tambah Issue button
    $('#btnTambahIssue').on('click', function() {
        $('#modalIssueTitle').html('<i class="fas fa-plus-circle mr-2"></i>Tambah Issue Baru');
        prepareIssueFormForNewEntry();
        $('#modalIssue').modal('show');
    });
    
    function submitIssueForm(keepOpen) {
        var formElement = $('#formIssue')[0];
        if (!formElement.checkValidity()) {
            formElement.reportValidity();
            return;
        }

        var $saveButton = $('#btnSimpanIssue');
        // capture any value currently in the input box before serializing
        addIssueNameFromInput();
        var formData = $('#formIssue').serialize();
        $saveButton.prop('disabled', true);

        $.ajax({
            url: 'save_issue.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sukses',
                        text: resp.message,
                        timer: keepOpen ? 1200 : 1500,
                        showConfirmButton: !keepOpen
                    }).then(function() {
                        table.ajax.reload(null, false);
                        if (keepOpen) {
                            prepareIssueFormForNewEntry();
                            $('#issue_name_input').focus();
                        } else {
                            $('#modalIssue').modal('hide');
                            
                            // Cek apakah perlu menampilkan pop-up simpan template
                            // Hanya untuk action 'add'
                            var action = $('#action').val();
                            if (action === 'add') {
                                checkAndShowSaveTemplatePopup();
                            }
                        }
                    });
                } else {
                    showError(resp.message || 'Gagal menyimpan issue.');
                }
            },
            error: function() {
                showError('Terjadi kesalahan server.');
            },
            complete: function() {
                $saveButton.prop('disabled', false);
            }
        });
    }

    function checkAndShowSaveTemplatePopup() {
        // If from template, don't ask to save as template
        if (isFromTemplate) {
            return;
        }

        // Ambil nama issue pertama dari array issueNames
        var firstIssueName = issueNames.length > 0 ? issueNames[0] : '';
        
        if (!firstIssueName) {
            return; // Tidak ada issue name, skip
        }
        
        // Cek apakah template dengan nama yang sama sudah ada
        $.ajax({
            url: 'check_template_exists.php',
            type: 'POST',
            data: { template_name: firstIssueName },
            dataType: 'json',
            success: function(resp) {
                if (resp.exists) {
                    // Template dengan nama yang sama sudah ada, tidak tampilkan pop-up
                    return;
                } else {
                    // Template belum ada, tampilkan pop-up
                    showSaveTemplateModal(firstIssueName);
                }
            },
            error: function() {
                // Jika error, tetap tampilkan pop-up untuk keamanan
                showSaveTemplateModal(firstIssueName);
            }
        });
    }
    
    function showSaveTemplateModal(defaultName) {
        $('#template_name_input').val(defaultName || '');
        $('#modalSaveTemplate').modal('show');
    }

    $('#btnSimpanIssue').on('click', function() {
        submitIssueForm(false);
    });


    // Edit Handler - uses a dedicated single-item edit modal
    $(document).on('click', '#issuesTable .btn-edit', function(e) {
        e.preventDefault();
        var tr = $(this).closest('tr');
        var row = table.row(tr);

        // Handle responsive mode
        if (!row.data()) {
            tr = $(this).closest('tr').prev('tr');
            row = table.row(tr);
        }

        var data = row.data();
        if (!data) {
            showError('Data tidak ditemukan.');
            return;
        }

        // populate edit modal fields
        $('#edit_issue_id').val(data.issue_id);
        $('#edit_nama').val(userDefaults.nama || '');
        $('#edit_jabatan').val(userDefaults.jabatan || '');
        $('#edit_departemen').val(userDefaults.departemen || '');
        $('#edit_bagian').val(userDefaults.bagian || '');
        $('#edit_tgl_pengajuan').val(defaultTanggalPengajuan);

        $('#edit_issue_name').val(data.issue_name || '');
        $('#edit_issue_type').val(data.type).trigger('change');
        $('#edit_status').val(data.status);
        $('#edit_priority').val(data.priority);
        $('#edit_due_date').val(data.due_date);
        $('#edit_description').val(data.description);

        // Kategori/Sub handling - reuse existing subKategoriData
        var kat = data.raw_kategori;
        var sub = data.raw_sub_kategori;
        var assetId = data.asset_id;

        // populate edit kategori options
        $('#edit_kategori').val(kat).trigger('change');
        if (sub) {
            $('#edit_sub_kategori').empty().append('<option value="">-- Pilih Sub Kategori --</option>');
            if (subKategoriData && subKategoriData[kat]) {
                subKategoriData[kat].forEach(function(s) {
                    $('#edit_sub_kategori').append('<option value="'+escapeHtml(s)+'">'+escapeHtml(s)+'</option>');
                });
            }
            $('#edit_sub_kategori').val(sub).trigger('change', [assetId]);
            $('#edit_subKategoriGroup').show();
        } else {
            $('#edit_sub_kategori').val('').trigger('change');
            $('#edit_subKategoriGroup').hide();
        }

        $('#modalIssueEdit').modal('show');
    });

    // Submit handler for edit modal
    $('#btnUpdateIssue').on('click', function() {
        var form = $('#formIssueEdit')[0];
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true);

        var data = $('#formIssueEdit').serialize();
        $.ajax({
            url: 'save_issue.php',
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    showSuccess(resp.message || 'Issue berhasil diupdate.');
                    table.ajax.reload(null, false);
                    $('#modalIssueEdit').modal('hide');
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

    // Delete Handler
    $(document).on('click', '#issuesTable .btn-delete', function(e) {
        e.preventDefault();
        var tr = $(this).closest('tr');
        var row = table.row(tr);
        
        // Handle responsive mode
        if (!row.data()) {
            tr = $(this).closest('tr').prev('tr');
            row = table.row(tr);
        }
        
        var data = row.data();
        
        if (!data) {
            showError('Data tidak ditemukan.');
            return;
        }
        
        Swal.fire({
            title: 'Hapus Issue?',
            text: "Data yang dihapus tidak dapat dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'save_issue.php',
                    type: 'POST',
                    data: { action: 'delete', issue_id: data.issue_id },
                    dataType: 'json',
                    success: function(resp) {
                        if (resp.success) {
                            showSuccess('Issue berhasil dihapus.');
                            table.ajax.reload();
                        } else {
                            showError(resp.message);
                        }
                    },
                    error: function() {
                        showError('Gagal menghapus data.');
                    }
                });
            }
        });
    });
    
    // Reset Filter
    $('#btnReset').on('click', function() {
        $('#filterType').val('');
        $('#filterTanggalStart').val('');
        $('#filterTanggalEnd').val('');
        table.search('').draw();
        reloadIssuesTable();
    });
    
    // Export PDF
    $('#btnExportPdf').on('click', function() {
        var filters = collectIssueFilters();
        filters.scope = 'active';
        var params = new URLSearchParams(filters);
        window.open('export_issues_pdf.php?' + params.toString(), '_blank');
    });

    $modalIssue.on('hidden.bs.modal', function() {
        toggleTemplateTrigger(false);
        $templatePickerModal.modal('hide');
    });
    
    // Handler untuk modal simpan template
    $('#btnSkipTemplate').on('click', function() {
        $('#modalSaveTemplate').modal('hide');
    });
    
    $('#btnConfirmSaveTemplate').on('click', function() {
        var templateName = $('#template_name_input').val().trim();
        
        if (!templateName) {
            showError('Nama template harus diisi.');
            return;
        }
        
        // Ambil data dari form issue yang baru saja disimpan
        var templateData = {
            template_name: templateName,
            issue_names_json: $('#issue_names_json').val(),
            issue_type: $('#issue_type').val(),
            kategori: $('#kategori').val(),
            sub_kategori: $('#sub_kategori').val(),
            asset_id: $('#asset_id').val(),
            priority: $('#priority').val(),
            status: $('#status').val(),
            description: $('#description').val()
        };
        
        $.ajax({
            url: 'save_issue_template.php',
            type: 'POST',
            data: templateData,
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sukses',
                        text: resp.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                    $('#modalSaveTemplate').modal('hide');
                    // Reload template cache
                    loadIssueTemplates(true);
                } else {
                    showError(resp.message || 'Gagal menyimpan template.');
                }
            },
            error: function() {
                showError('Terjadi kesalahan server saat menyimpan template.');
            }
        });
    });
    
    // Handler untuk hapus template
    $(document).on('click', '.btn-delete-template', function() {
        var templateId = $(this).data('template-id');
        var templateName = $(this).data('template-name');
        
        Swal.fire({
            title: 'Hapus Template?',
            text: 'Template "' + templateName + '" akan dihapus permanen!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'delete_issue_template.php',
                    type: 'POST',
                    data: { template_id: templateId },
                    dataType: 'json',
                    success: function(resp) {
                        if (resp.success) {
                            showSuccess('Template berhasil dihapus.');
                            // Reload template list
                            loadIssueTemplates(true);
                        } else {
                            showError(resp.message || 'Gagal menghapus template.');
                        }
                    },
                    error: function() {
                        showError('Terjadi kesalahan server saat menghapus template.');
                    }
                });
            }
        });
    });
    
    // SweetAlert helpers
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
