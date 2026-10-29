<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
require_once __DIR__ . '/issue_access.php';

$access = purchasingIssueAccess($conn ?? null);
if (empty($access['allowed'])) {
    denyPurchasingIssueAccess($access);
}

$csrfToken = purchasingIssueCsrfToken();
$actionRights = [
    'canEdit' => !empty($access['can_edit']),
    'canDelete' => !empty($access['can_delete'])
];
$theme = strtolower((string) ($_SESSION['Theme'] ?? 'primary'));
$allowedThemes = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark', 'lime', 'white', 'teal', 'navy', 'purple', 'indigo', 'orange', 'maroon', 'fuchsia', 'olive'];
$theme = in_array($theme, $allowedThemes, true) ? $theme : 'primary';
$themeText = in_array($theme, ['warning', 'light', 'lime', 'white'], true) ? 'text-dark' : 'text-white';
$GLOBALS['pageTitle'] = 'Riwayat Issues Purchasing';
$GLOBALS['pageDescription'] = 'Riwayat issue Purchasing yang telah selesai.';
include $_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php';
?>
<main class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1>Riwayat Issues Purchasing</h1>
            <p class="text-muted mb-0">Issue berstatus selesai.</p>
        </div>
    </section>
    <section class="content">
        <div class="container-fluid">
            <div class="card card-outline card-<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>">
                <div class="card-header bg-<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?> <?= $themeText ?>">
                    <h3 class="card-title m-0"><i class="fas fa-history mr-2" aria-hidden="true"></i>Riwayat Issues Purchasing</h3>
                    <div class="card-tools">
                        <a class="btn btn-sm btn-light" href="list_issues.php"><i class="fas fa-list mr-1" aria-hidden="true"></i>Daftar aktif</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-row mb-3">
                        <div class="form-group col-md-3">
                            <label for="purchasing-history-filter-type">Jenis</label>
                            <select class="form-control form-control-sm" id="purchasing-history-filter-type">
                                <option value="">Semua jenis</option>
                                <option value="Task">Task</option>
                                <option value="Support Activities">Support Activities</option>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="purchasing-history-filter-date-start">Dibuat mulai</label>
                            <input class="form-control form-control-sm" type="date" id="purchasing-history-filter-date-start">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="purchasing-history-filter-date-end">Dibuat sampai</label>
                            <input class="form-control form-control-sm" type="date" id="purchasing-history-filter-date-end">
                        </div>
                        <div class="form-group col-12 mb-0">
                            <button type="button" class="btn btn-default btn-sm" id="purchasing-history-reset-filters" title="Reset filter">
                                <i class="fas fa-sync-alt mr-1" aria-hidden="true"></i>Reset
                            </button>
                            <button type="button" class="btn btn-danger btn-sm ml-1" id="purchasing-history-export-pdf" title="Export PDF riwayat issue">
                                <i class="fas fa-file-pdf mr-1" aria-hidden="true"></i>Export PDF
                            </button>
                        </div>
                    </div>
                    <p id="purchasing-history-error" class="text-danger" role="alert" hidden></p>
                    <div class="table-responsive">
                        <table id="purchasing-history-table" class="table table-hover table-sm nowrap purchasing-issues-table w-100">
                            <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>Nama</th>
                                    <th>Issue Name</th>
                                    <th>Type</th>
                                    <th>Kategori Kegiatan</th>
                                    <th>Priority</th>
                                    <th>Tanggal Dibuat</th>
                                    <th>Selesai</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
            <div
                class="modal fade"
                id="purchasing-history-edit-modal"
                tabindex="-1"
                role="dialog"
                aria-labelledby="purchasing-history-edit-title"
                aria-hidden="true"
                data-backdrop="static"
            >
                <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?> <?= $themeText ?>">
                            <h5 class="modal-title" id="purchasing-history-edit-title">
                                <i class="fas fa-edit mr-2" aria-hidden="true"></i>Ubah Issue
                            </h5>
                            <button type="button" class="close <?= $themeText ?>" data-dismiss="modal" aria-label="Tutup">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <form id="purchasing-history-edit-form">
                            <div class="modal-body">
                                <input type="hidden" name="issue_id" id="purchasing-history-edit-issue-id">
                                <input type="hidden" name="row_version" id="purchasing-history-edit-row-version">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <p id="purchasing-history-edit-error" class="text-danger" role="alert" hidden></p>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="purchasing-history-edit-created-by">Pemohon</label>
                                        <input class="form-control form-control-sm" id="purchasing-history-edit-created-by" readonly>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="purchasing-history-edit-name">Nama issue <span class="text-danger">*</span></label>
                                        <input class="form-control form-control-sm" type="text" id="purchasing-history-edit-name" name="issue_name" maxlength="255" required>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label for="purchasing-history-edit-type">Type <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" id="purchasing-history-edit-type" name="issue_type" required>
                                            <option value="Task">Task</option>
                                            <option value="Support Activities">Support Activities</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="purchasing-history-edit-status">Status <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" id="purchasing-history-edit-status" name="status" required>
                                            <option value="To Do">To Do</option>
                                            <option value="In Progress">In Progress</option>
                                            <option value="Done">Done</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="purchasing-history-edit-priority">Prioritas <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" id="purchasing-history-edit-priority" name="priority" required>
                                            <option value="Low">Low</option>
                                            <option value="Medium">Medium</option>
                                            <option value="High">High</option>
                                            <option value="Critical">Critical</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="purchasing-history-edit-category">Kategori <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" id="purchasing-history-edit-category" name="category_id" required></select>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="purchasing-history-edit-subcategory">Subkategori <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" id="purchasing-history-edit-subcategory" name="sub_id" required disabled></select>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="purchasing-history-edit-due-date">Due Date</label>
                                        <input class="form-control form-control-sm" type="date" id="purchasing-history-edit-due-date" name="due_date">
                                    </div>
                                </div>
                                <div class="form-group mb-0">
                                    <label for="purchasing-history-edit-description">Deskripsi</label>
                                    <textarea class="form-control form-control-sm" id="purchasing-history-edit-description" name="description" rows="5" maxlength="4000"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                                    <i class="fas fa-times mr-1" aria-hidden="true"></i>Batal
                                </button>
                                <button type="submit" class="btn btn-success btn-sm" id="purchasing-history-edit-submit">
                                    <i class="fas fa-save mr-1" aria-hidden="true"></i>Simpan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal fade" id="purchasing-history-detail-modal" tabindex="-1" role="dialog" aria-labelledby="purchasing-history-detail-title" aria-hidden="true" data-backdrop="static">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
                        <div class="modal-content">
                            <div class="modal-header bg-<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?> <?= $themeText ?> d-flex align-items-center">
                                <h5 class="modal-title" id="purchasing-history-detail-title" data-detail-title><i class="fas fa-eye mr-2" aria-hidden="true"></i>Detail Issue</h5>
                                <button type="button" class="close <?= $themeText ?>" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                            </div>
                            <div class="modal-body">
                                <div data-detail-loading class="text-center py-4" role="status">
                                    <i class="fas fa-spinner fa-spin fa-2x text-muted mb-3" aria-hidden="true"></i>
                                    <p class="text-muted mb-0">Memuat detail issue...</p>
                                </div>
                                <div data-detail-error class="alert alert-danger d-none" role="alert"></div>
                                <div data-detail-content class="d-none">
                                    <div class="purchasing-detail-header mb-4">
                                        <div>
                                            <div class="text-muted small" data-detail-field="issue-id"></div>
                                            <h4 class="mb-2" data-detail-field="issue-name"></h4>
                                            <div class="purchasing-detail-badges">
                                                <span data-detail-field="type"></span>
                                                <span data-detail-field="status-badge"></span>
                                                <span data-detail-field="priority-badge"></span>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="purchasing-detail-label">Due Date</div>
                                            <div class="purchasing-detail-value" data-detail-field="due-date"></div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="purchasing-detail-section mb-3">
                                                <div class="purchasing-detail-section-title"><i class="fas fa-user-circle mr-1" aria-hidden="true"></i>Informasi Akun</div>
                                                <dl class="purchasing-detail-list">
                                                    <dt>Nama</dt>
                                                    <dd data-detail-field="name"></dd>
                                                    <dt>Jabatan</dt>
                                                    <dd data-detail-field="position"></dd>
                                                    <dt>Departemen</dt>
                                                    <dd data-detail-field="department"></dd>
                                                    <dt>Bagian</dt>
                                                    <dd data-detail-field="section"></dd>
                                                </dl>
                                            </div>
                                            <div class="purchasing-detail-section mb-3">
                                                <div class="purchasing-detail-section-title"><i class="fas fa-layer-group mr-1" aria-hidden="true"></i>Kategori Kegiatan Purchasing</div>
                                                <dl class="purchasing-detail-list">
                                                    <dt>Kategori</dt>
                                                    <dd data-detail-field="category"></dd>
                                                    <dt>Subkategori</dt>
                                                    <dd data-detail-field="subcategory"></dd>
                                                </dl>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="purchasing-detail-section mb-3">
                                                <div class="purchasing-detail-section-title"><i class="fas fa-clock mr-1" aria-hidden="true"></i>Timeline</div>
                                                <div class="purchasing-detail-timeline">
                                                    <div class="purchasing-detail-timeline-item">
                                                        <span>Dibuat</span>
                                                        <span data-detail-field="created-info"></span>
                                                    </div>
                                                    <div class="purchasing-detail-timeline-item">
                                                        <span>Update Terakhir</span>
                                                        <span data-detail-field="updated-info"></span>
                                                    </div>
                                                    <div class="purchasing-detail-timeline-item d-none" data-detail-updater>
                                                        <span>Diperbarui oleh</span>
                                                        <span data-detail-field="updated-by"></span>
                                                    </div>
                                                    <div class="purchasing-detail-timeline-item d-none" data-detail-completion>
                                                        <span>Selesai</span>
                                                        <span data-detail-field="completion-date"></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="purchasing-detail-section mb-3">
                                                <div class="purchasing-detail-section-title"><i class="fas fa-list-alt mr-1" aria-hidden="true"></i>Ringkasan Status</div>
                                                <dl class="purchasing-detail-list">
                                                    <dt>Status</dt>
                                                    <dd data-detail-field="status-text"></dd>
                                                    <dt>Prioritas</dt>
                                                    <dd data-detail-field="priority-text"></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="purchasing-detail-section">
                                        <div class="purchasing-detail-section-title"><i class="fas fa-align-left mr-1" aria-hidden="true"></i>Deskripsi</div>
                                        <div class="purchasing-detail-description" data-detail-field="description"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1" aria-hidden="true"></i>Tutup</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'; ?>
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="issue_ui.css?v=20261029-detail-layout">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script src="issue_ui.js"></script>
<script>
(function ($) {
    'use strict';
    var issueUi = window.PurchasingIssueUi;
    var actionRights = <?= json_encode($actionRights, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var csrfToken = <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var table = $('#purchasing-history-table').DataTable({
        processing: true,
        serverSide: true,
        responsive: {
            details: {
                type: 'column',
                target: 0
            }
        },
        ajax: {
            url: 'issues_serverside.php',
            type: 'GET',
            data: function (request) {
                request.scope = 'history';
                request.filterStatus = 'Done';
                request.filterType = $('#purchasing-history-filter-type').val();
                request.filterTanggalStart = $('#purchasing-history-filter-date-start').val();
                request.filterTanggalEnd = $('#purchasing-history-filter-date-end').val();
            },
            error: function () {
                $('#purchasing-history-error').text('Riwayat issue tidak dapat dimuat.').prop('hidden', false);
            }
        },
        order: [[6, 'desc']],
        columnDefs: [
            { className: 'dtr-control', orderable: false, targets: 0 },
            { responsivePriority: 1, targets: -1 },
            { responsivePriority: 2, targets: 2 },
            { responsivePriority: 3, targets: 5 }
        ],
        columns: [
            {
                data: null,
                orderable: false,
                searchable: false,
                defaultContent: '',
                render: function (_data, _type, _row, meta) {
                    return meta.row + meta.settings._iDisplayStart + 1;
                }
            },
            { data: 'nama_pemohon', render: $.fn.dataTable.render.text() },
            { data: 'issue_name', render: $.fn.dataTable.render.text() },
            {
                data: 'type',
                render: function (data, type) {
                    return type === 'display' ? issueUi.renderBadge('type', data) : (data || '');
                }
            },
            { data: 'kategori', render: $.fn.dataTable.render.text() },
            {
                data: 'priority',
                render: function (data, type) {
                    return type === 'display' ? issueUi.renderBadge('priority', data) : (data || '');
                }
            },
            { data: 'created_at', render: $.fn.dataTable.render.text() },
            { data: 'tanggal_selesai', render: $.fn.dataTable.render.text() },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function (_data, _type, row) {
                    var issueId = Number(row.issue_id);
                    var buttons = issueUi.renderActionButton('view-issue', issueId, 'Detail', 'btn-info', 'fas fa-eye');
                    if (actionRights.canEdit) {
                        buttons += issueUi.renderActionButton('edit-issue', issueId, 'Ubah', 'btn-warning', 'fas fa-edit');
                    }
                    if (actionRights.canDelete) {
                        buttons += issueUi.renderActionButton('delete-issue', issueId, 'Hapus', 'btn-danger', 'fas fa-trash');
                    }
                    return buttons;
                }
            }
        ],
        language: {
            emptyTable: 'Belum ada riwayat issue.',
            processing: 'Memuat data...',
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ baris',
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ issue',
            infoEmpty: 'Tidak ada issue',
            paginate: { previous: 'Sebelumnya', next: 'Berikutnya' }
        }
    });

    var searchInput = $('#purchasing-history-table_filter input[type="search"]');
    searchInput.attr({
        id: 'purchasing-history-search',
        'aria-label': 'Cari riwayat issue Purchasing'
    });

    $('#purchasing-history-filter-type, #purchasing-history-filter-date-start, #purchasing-history-filter-date-end').on('change', function () {
        table.ajax.reload();
    });

    $('#purchasing-history-reset-filters').on('click', function () {
        $('#purchasing-history-filter-type, #purchasing-history-filter-date-start, #purchasing-history-filter-date-end').val('');
        table.search('').draw();
    });

    $('#purchasing-history-export-pdf').on('click', function () {
        var query = new URLSearchParams({
            scope: 'history',
            filterType: $('#purchasing-history-filter-type').val() || '',
            filterTanggalStart: $('#purchasing-history-filter-date-start').val() || '',
            filterTanggalEnd: $('#purchasing-history-filter-date-end').val() || ''
        });
        window.open('export_issues_pdf.php?' + query.toString(), '_blank', 'noopener');
    });

    /**
     * Memuat subkategori aktif untuk form Edit Riwayat sesuai kategori yang dipilih.
     *
     * @param {string|number} categoryId ID kategori yang dipilih.
     * @param {string|number} selectedId ID subkategori yang perlu dipilih kembali.
     * @returns {JQuery.Promise} Promise request master subkategori.
     */
    function loadHistoryEditSubcategories(categoryId, selectedId) {
        var subcategory = $('#purchasing-history-edit-subcategory');
        var selectedValue = String(selectedId || '');
        subcategory.empty()
            .prop('disabled', true)
            .append($('<option>', { value: '', text: 'Pilih kategori terlebih dahulu' }));

        if (!categoryId) {
            return $.Deferred().resolve().promise();
        }

        return $.getJSON('issue_master_api.php', {
            action: 'subcategories',
            category_id: categoryId
        }).done(function (response) {
            if (!response.success) {
                $('#purchasing-history-edit-error').text(response.message || 'Subkategori tidak dapat dimuat.').prop('hidden', false);
                return;
            }

            var subcategories = response.data || [];
            if (subcategories.length === 0) {
                subcategory.empty().append($('<option>', { value: '', text: 'Kategori ini belum memiliki subkategori aktif' }));
                $('#purchasing-history-edit-error').text('Kategori yang dipilih belum memiliki subkategori aktif.').prop('hidden', false);
                return;
            }

            subcategory.empty()
                .prop('disabled', false)
                .append($('<option>', { value: '', text: 'Pilih subkategori' }));
            $.each(subcategories, function (_index, item) {
                subcategory.append($('<option>', { value: item.sub_id, text: item.sub_name }));
            });
            subcategory.val(selectedValue);
            if (selectedValue !== '' && subcategory.val() === null) {
                $('#purchasing-history-edit-error').text('Subkategori dari issue ini sudah tidak tersedia. Pilih subkategori aktif sebelum menyimpan.').prop('hidden', false);
            }
        }).fail(function () {
            $('#purchasing-history-edit-error').text('Subkategori tidak dapat dimuat. Coba kembali.').prop('hidden', false);
        });
    }

    /**
     * Memuat kategori aktif untuk form Edit Riwayat dan meneruskan pemilihan subkategori.
     *
     * @param {string|number} selectedId ID kategori yang perlu dipilih kembali.
     * @param {string|number} selectedSubId ID subkategori yang perlu dipilih kembali.
     * @returns {JQuery.Promise} Promise request master kategori.
     */
    function loadHistoryEditCategories(selectedId, selectedSubId) {
        var category = $('#purchasing-history-edit-category');
        category.empty().append($('<option>', { value: '', text: 'Pilih kategori' }));
        return $.getJSON('issue_master_api.php', {
            action: 'categories'
        }).done(function (response) {
            if (!response.success) {
                $('#purchasing-history-edit-error').text(response.message || 'Kategori tidak dapat dimuat.').prop('hidden', false);
                return;
            }

            $.each(response.data || [], function (_index, item) {
                category.append($('<option>', { value: item.category_id, text: item.category_name }));
            });
            category.val(String(selectedId || ''));
            if (selectedId && category.val() === null) {
                $('#purchasing-history-edit-error').text('Kategori dari issue ini sudah tidak tersedia. Pilih kategori aktif sebelum menyimpan.').prop('hidden', false);
            }
            loadHistoryEditSubcategories(category.val(), selectedSubId);
        }).fail(function () {
            $('#purchasing-history-edit-error').text('Kategori tidak dapat dimuat. Coba kembali.').prop('hidden', false);
        });
    }

    /**
     * Membuka modal Edit Riwayat menggunakan detail terbaru agar rowversion tidak berasal dari tabel lama.
     *
     * @param {number} issueId ID Issue Purchasing yang akan diubah.
     * @param {JQuery} trigger Tombol yang memulai permintaan edit.
     * @returns {void}
     */
    function openHistoryEdit(issueId, trigger) {
        if (!Number.isSafeInteger(issueId) || issueId < 1) {
            issueUi.showError('ID issue tidak valid.');
            return;
        }

        trigger.prop('disabled', true);
        $.getJSON('get_issue_detail.php', { issue_id: issueId }).done(function (response) {
            if (!response.success || !response.data) {
                issueUi.showError(response.message || 'Detail issue tidak dapat dimuat.');
                trigger.prop('disabled', false);
                return;
            }

            var issue = response.data;
            $('#purchasing-history-edit-form')[0].reset();
            $('#purchasing-history-edit-error').prop('hidden', true).text('');
            $('#purchasing-history-edit-issue-id').val(issue.issue_id);
            $('#purchasing-history-edit-row-version').val(issue.row_version);
            $('#purchasing-history-edit-created-by').val(issue.created_by || '-');
            $('#purchasing-history-edit-name').val(issue.issue_name || '');
            $('#purchasing-history-edit-type').val(issue.issue_type || 'Task');
            $('#purchasing-history-edit-status').val(issue.status || 'Done');
            $('#purchasing-history-edit-priority').val(issue.priority || 'Medium');
            $('#purchasing-history-edit-due-date').val(issue.due_date || '');
            $('#purchasing-history-edit-description').val(issue.description || '');
            loadHistoryEditCategories(issue.category_id, issue.sub_id);
            $('#purchasing-history-edit-modal').modal('show');
        }).fail(function (xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : 'Detail issue tidak dapat dimuat.';
            issueUi.showError(message);
        }).always(function () {
            trigger.prop('disabled', false);
        });
    }

    $('#purchasing-history-edit-category').on('change', function () {
        loadHistoryEditSubcategories($(this).val(), '');
    });

    $('#purchasing-history-edit-form').on('submit', function (event) {
        event.preventDefault();
        var form = this;
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        var submit = $('#purchasing-history-edit-submit');
        submit.prop('disabled', true);
        $('#purchasing-history-edit-error').prop('hidden', true).text('');
        $.ajax({
            url: 'update_issue_admin.php',
            type: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            dataType: 'json',
            data: $(form).serialize()
        }).done(function (response) {
            if (!response.success) {
                var failureMessage = response.message || 'Issue tidak dapat disimpan.';
                $('#purchasing-history-edit-error').text(failureMessage).prop('hidden', false);
                issueUi.showError(failureMessage, { target: '#purchasing-history-edit-modal .modal-content' });
                return;
            }

            $('#purchasing-history-edit-modal').one('hidden.bs.modal', function () {
                table.ajax.reload(null, false);
                issueUi.showSuccess(response.message || 'Issue berhasil diperbarui.');
            }).modal('hide');
        }).fail(function (xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : 'Issue tidak dapat disimpan.';
            $('#purchasing-history-edit-error').text(message).prop('hidden', false);
            issueUi.showError(message, { target: '#purchasing-history-edit-modal .modal-content' });
        }).always(function () {
            submit.prop('disabled', false);
        });
    });

    /**
     * Memuat detail Issue Purchasing yang selesai untuk ditampilkan di modal riwayat.
     *
     * @param {number} issueId ID Issue Purchasing yang valid.
     * @returns {JQuery.jqXHR|undefined} Request detail bila ID valid.
     */
    function showPurchasingHistoryDetail(issueId) {
        var modal = $('#purchasing-history-detail-modal');
        if (!Number.isSafeInteger(issueId) || issueId < 1) {
            issueUi.setDetailState(modal, 'error', 'ID issue tidak valid.');
            modal.modal('show');
            return undefined;
        }

        issueUi.setDetailState(modal, 'loading');
        modal.modal('show');
        return $.getJSON('get_issue_detail.php', { issue_id: issueId })
            .done(function (response) {
                if (!response.success || !response.data) {
                    issueUi.setDetailState(modal, 'error', response.message || 'Detail issue tidak dapat dimuat.');
                    return;
                }

                issueUi.populateDetail(modal, response.data);
            })
            .fail(function () {
                issueUi.setDetailState(modal, 'error', 'Detail issue tidak dapat dimuat.');
            });
    }

    $('#purchasing-history-table').on('click', '[data-action="view-issue"]', function () {
        showPurchasingHistoryDetail(Number($(this).attr('data-issue-id')));
    });

    $('#purchasing-history-table').on('click', '[data-action="edit-issue"]', function () {
        openHistoryEdit(Number($(this).attr('data-issue-id')), $(this));
    });

    $('#purchasing-history-table').on('click', '[data-action="delete-issue"]', function () {
        var button = $(this);
        var issueId = Number(button.attr('data-issue-id'));
        if (!Number.isSafeInteger(issueId) || issueId < 1) {
            issueUi.showError('ID issue tidak valid.');
            return;
        }

        button.prop('disabled', true);
        $.getJSON('get_issue_detail.php', { issue_id: issueId }).done(function (response) {
            if (!response.success || !response.data) {
                issueUi.showError(response.message || 'Detail issue tidak dapat dimuat.');
                button.prop('disabled', false);
                return;
            }

            var issue = response.data;
            issueUi.confirmDelete({
                title: 'Hapus Issue?',
                text: 'Issue selesai ini akan dihapus permanen dan tidak dapat dikembalikan.'
            }).then(function (result) {
                if (!result.isConfirmed) {
                    button.prop('disabled', false);
                    return;
                }

                $.ajax({
                    url: 'delete_issue.php',
                    type: 'POST',
                    headers: { 'X-CSRF-Token': csrfToken },
                    dataType: 'json',
                    data: {
                        issue_id: issue.issue_id,
                        row_version: issue.row_version,
                        csrf_token: csrfToken
                    }
                }).done(function (deleteResponse) {
                    if (deleteResponse.success) {
                        table.ajax.reload(null, false);
                        issueUi.showSuccess(deleteResponse.message || 'Issue berhasil dihapus.');
                        return;
                    }

                    issueUi.showError(deleteResponse.message || 'Issue tidak dapat dihapus.');
                }).fail(function (xhr) {
                    var message = xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : 'Issue tidak dapat dihapus.';
                    issueUi.showError(message);
                }).always(function () {
                    button.prop('disabled', false);
                });
            });
        }).fail(function (xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : 'Detail issue tidak dapat dimuat.';
            issueUi.showError(message);
            button.prop('disabled', false);
        });
    });
}(window.jQuery));
</script>
</body>
</html>
