<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
require_once __DIR__ . '/issue_access.php';

$access = purchasingIssueAccess($conn ?? null);
if (empty($access['allowed'])) {
    denyPurchasingIssueAccess($access);
}

$csrfToken = purchasingIssueCsrfToken();
$accountProfile = purchasingIssueCurrentProfile($conn ?? null, trim((string) ($_SESSION['UserName'] ?? '')));
$accountName = $accountProfile['name'];
$accountPosition = $accountProfile['jabatan'] !== '' ? $accountProfile['jabatan'] : '-';
$accountDepartment = $accountProfile['departemen'] !== '' ? $accountProfile['departemen'] : '-';
$accountSection = $accountProfile['bagian'] !== '' ? $accountProfile['bagian'] : '-';
$actionRights = [
    'canAdd' => !empty($access['can_add']),
    'canEdit' => !empty($access['can_edit']),
    'canDelete' => !empty($access['can_delete'])
];

$theme = strtolower((string) ($_SESSION['Theme'] ?? 'primary'));
$allowedThemes = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark', 'lime', 'white', 'teal', 'navy', 'purple', 'indigo', 'orange', 'maroon', 'fuchsia', 'olive'];
$theme = in_array($theme, $allowedThemes, true) ? $theme : 'primary';
$themeText = in_array($theme, ['warning', 'light', 'lime', 'white'], true) ? 'text-dark' : 'text-white';
include $_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php';
?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php'; ?>
<main class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <h1>Daftar Issues Purchasing</h1>
                <p class="text-muted mb-0">Pantau permintaan aktif tim Purchasing.</p>
            </div>
        </section>
        <section class="content">
            <div class="container-fluid">
                <div class="card card-outline card-<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="card-header bg-<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?> <?= $themeText ?>">
                        <h3 class="card-title m-0"><i class="fas fa-list mr-2" aria-hidden="true"></i>Daftar Issues Purchasing</h3>
                        <div class="card-tools">
                            <?php if ($actionRights['canAdd']): ?>
                            <button
                                type="button"
                                class="btn btn-sm btn-light"
                                id="purchasing-add-open"
                                data-toggle="modal"
                                data-target="#purchasing-issue-form-modal"
                            ><i class="fas fa-plus mr-1" aria-hidden="true"></i>Tambah Issue</button>
                            <?php endif; ?>
                            <a class="btn btn-sm btn-light" href="manage_categories.php">Kategori</a>
                            <a class="btn btn-sm btn-light" href="riwayat_issues.php">Riwayat</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-row mb-3">
                            <div class="form-group col-md-3">
                                <label for="purchasing-filter-type">Jenis</label>
                                <select class="form-control form-control-sm" id="purchasing-filter-type">
                                    <option value="">Semua jenis</option>
                                    <option value="Task">Task</option>
                                    <option value="Support Activities">Support Activities</option>
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="purchasing-filter-status">Status</label>
                                <select class="form-control form-control-sm" id="purchasing-filter-status">
                                    <option value="">Semua status aktif</option>
                                    <option value="To Do">To Do</option>
                                    <option value="In Progress">In Progress</option>
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="purchasing-filter-date-start">Dibuat mulai</label>
                                <input class="form-control form-control-sm" type="date" id="purchasing-filter-date-start">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="purchasing-filter-date-end">Dibuat sampai</label>
                                <input class="form-control form-control-sm" type="date" id="purchasing-filter-date-end">
                            </div>
                            <div class="form-group col-12 mb-0">
                                <button type="button" class="btn btn-default btn-sm" id="purchasing-reset-filters" title="Reset filter">
                                    <i class="fas fa-sync-alt mr-1" aria-hidden="true"></i>Reset
                                </button>
                                <button type="button" class="btn btn-danger btn-sm ml-1" id="purchasing-active-export-pdf" title="Export PDF daftar issue aktif">
                                    <i class="fas fa-file-pdf mr-1" aria-hidden="true"></i>Export PDF
                                </button>
                            </div>
                        </div>
                        <p id="purchasing-list-error" class="text-danger" role="alert" hidden></p>
                        <div class="table-responsive">
                            <table id="purchasing-issues-table" class="table table-hover table-sm nowrap purchasing-issues-table w-100">
                                <thead class="thead-light">
                                    <tr>
                                        <th>No</th>
                                        <th>Nama</th>
                                        <th>Issue Name</th>
                                        <th>Type</th>
                                        <th>Kategori Kegiatan</th>
                                        <th>Status</th>
                                        <th>Priority</th>
                                        <th>Tanggal Dibuat</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
                <div
                    class="modal fade"
                    id="purchasing-issue-form-modal"
                    tabindex="-1"
                    role="dialog"
                    aria-labelledby="purchasing-issue-form-title"
                    aria-hidden="true"
                    data-backdrop="static"
                >
                    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document" style="max-height: 90vh; margin: 1.75rem auto;">
                        <div class="modal-content" style="max-height: 90vh; display: flex; flex-direction: column;">
                            <div
                                class="modal-header bg-<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?> <?= $themeText ?>"
                                style="flex-shrink: 0;"
                            >
                                <h5 class="modal-title" id="purchasing-issue-form-title">
                                    <i class="fas fa-plus-circle mr-2"></i>Tambah Issue Baru
                                </h5>
                                <div class="ml-auto d-flex align-items-center">
                                    <button type="button" class="btn btn-light btn-sm mr-2" id="purchasing-template-open">
                                        <i class="fas fa-clone mr-1"></i>Gunakan Template
                                    </button>
                                <button
                                    type="button"
                                    class="close <?= $themeText ?>"
                                    data-dismiss="modal"
                                    aria-label="Tutup"
                                >
                                    <span aria-hidden="true">&times;</span>
                                </button>
                                </div>
                            </div>
                            <div class="modal-body" style="overflow-y: auto; flex: 1 1 auto; min-height: 0; padding: 1.5rem; max-height: calc(90vh - 140px);">
                                <form id="purchasing-issue-form">
                                    <input type="hidden" name="issue_id" id="purchasing-form-issue-id">
                                    <input type="hidden" name="row_version" id="purchasing-form-row-version">
                                    <input type="hidden" name="request_id" id="purchasing-form-request-id">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <p id="purchasing-form-error" class="text-danger" role="alert" hidden></p>
                                    <div class="row">
                                    <div class="col-md-6">
                                    <h6 class="text-muted mb-3"><i class="fas fa-user-circle mr-1"></i>Informasi Akun</h6>
                                    <div class="form-row">
                                        <div class="form-group col-6"><label class="form-label mb-1" for="purchasing-account-name">Nama</label><input class="form-control form-control-sm auto-filled" id="purchasing-account-name" value="<?= htmlspecialchars($accountName, ENT_QUOTES, 'UTF-8') ?>" readonly></div>
                                        <div class="form-group col-6"><label class="form-label mb-1" for="purchasing-account-position">Jabatan</label><input class="form-control form-control-sm auto-filled" id="purchasing-account-position" value="<?= htmlspecialchars($accountPosition, ENT_QUOTES, 'UTF-8') ?>" readonly></div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-4"><label class="form-label mb-1" for="purchasing-account-date">Tanggal</label><input class="form-control form-control-sm auto-filled" id="purchasing-account-date" value="<?= date('d-m-Y') ?>" readonly></div>
                                        <div class="form-group col-4"><label class="form-label mb-1" for="purchasing-account-department">Departemen</label><input class="form-control form-control-sm auto-filled" id="purchasing-account-department" value="<?= htmlspecialchars($accountDepartment, ENT_QUOTES, 'UTF-8') ?>" readonly></div>
                                        <div class="form-group col-4"><label class="form-label mb-1" for="purchasing-account-section">Bagian</label><input class="form-control form-control-sm auto-filled" id="purchasing-account-section" value="<?= htmlspecialchars($accountSection, ENT_QUOTES, 'UTF-8') ?>" readonly></div>
                                    </div>
                                    <hr class="my-3">
                                    <h6 class="text-muted mb-3"><i class="fas fa-layer-group mr-1"></i>Kategori Kegiatan Purchasing</h6>
                                    <div class="form-group">
                                        <label for="purchasing-form-category">Kategori <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" id="purchasing-form-category" name="category_id" required></select>
                                    </div>
                                    <div class="form-group">
                                        <label for="purchasing-form-subcategory">Subkategori <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" id="purchasing-form-subcategory" name="sub_id" required disabled><option value="">Pilih kategori terlebih dahulu</option></select>
                                    </div>
                                    </div>
                                    <div class="col-md-6">
                                    <h6 class="text-muted mb-3"><i class="fas fa-edit mr-1"></i>Detail Issue</h6>
                                    <div class="form-group">
                                        <label for="purchasing-form-name">Issue Name <span class="text-danger">*</span></label>
                                        <input class="form-control form-control-sm" id="purchasing-form-name" name="issue_name" maxlength="255" required>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="purchasing-form-type">Type <span class="text-danger">*</span></label>
                                            <select class="form-control form-control-sm" id="purchasing-form-type" name="issue_type" required>
                                                <option value="Task">Task</option>
                                                <option value="Support Activities">Support Activities</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="purchasing-form-status">Status <span class="text-danger">*</span></label>
                                            <select class="form-control form-control-sm" id="purchasing-form-status" name="status" required>
                                                <option value="To Do">To Do</option>
                                                <option value="In Progress">In Progress</option>
                                                <?php if ($actionRights['canEdit']): ?><option value="Done">Done</option><?php endif; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="purchasing-form-priority">Priority <span class="text-danger">*</span></label>
                                            <select class="form-control form-control-sm" id="purchasing-form-priority" name="priority" required>
                                                <option value="Low">Low</option><option value="Medium" selected>Medium</option><option value="High">High</option><option value="Critical">Critical</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="purchasing-form-due-date">Due Date</label>
                                            <input class="form-control form-control-sm" type="date" id="purchasing-form-due-date" name="due_date">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="purchasing-form-description">Description</label>
                                        <textarea class="form-control form-control-sm" id="purchasing-form-description" name="description" rows="4"></textarea>
                                    </div>
                                    </div>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer" style="flex-shrink: 0; border-top: 1px solid #dee2e6;">
                                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times"></i> Batal</button>
                                <button type="submit" form="purchasing-issue-form" class="btn btn-success btn-sm" id="purchasing-form-submit"><i class="fas fa-save"></i> Simpan</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal fade" id="purchasing-template-picker-modal" tabindex="-1" role="dialog" aria-labelledby="purchasing-template-picker-title" aria-hidden="true">
                    <div class="modal-dialog modal-lg" role="document">
                        <div class="modal-content">
                            <div class="modal-header bg-<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?> <?= $themeText ?>">
                                <h5 class="modal-title" id="purchasing-template-picker-title"><i class="fas fa-clone mr-2"></i>Pilih Template Issue</h5>
                                <button type="button" class="close <?= $themeText ?>" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-muted mb-3"><i class="fas fa-info-circle mr-1"></i>Klik Gunakan untuk mengisi form issue otomatis.</p>
                                <div class="input-group mb-3">
                                    <input type="text" inputmode="search" class="form-control" id="purchasing-template-search" placeholder="Cari nama, tipe, prioritas, status, atau deskripsi template" autocomplete="off" aria-label="Cari template Issue Purchasing">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary" id="purchasing-template-search-reset" title="Reset pencarian template" aria-label="Reset pencarian template" hidden><i class="fas fa-times" aria-hidden="true"></i></button>
                                    </div>
                                </div>
                                <p id="purchasing-template-picker-state" class="text-muted text-center py-4" role="status">Memuat template...</p>
                                <div id="purchasing-template-picker-list" class="list-group" hidden></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal fade" id="purchasing-template-save-modal" tabindex="-1" role="dialog" aria-labelledby="purchasing-template-save-title" aria-hidden="true" data-backdrop="static">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header bg-<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?> <?= $themeText ?>">
                                <h5 class="modal-title" id="purchasing-template-save-title"><i class="fas fa-save mr-2"></i>Simpan sebagai Template?</h5>
                                <button type="button" class="close <?= $themeText ?>" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-3">Issue baru berhasil disimpan. Simpan pengaturannya sebagai template pribadi untuk digunakan kembali?</p>
                                <div class="form-group mb-0">
                                    <label for="purchasing-template-save-name">Nama template</label>
                                    <input class="form-control" id="purchasing-template-save-name" maxlength="255" autocomplete="off">
                                    <p id="purchasing-template-save-error" class="text-danger small mt-2 mb-0" role="alert" hidden></p>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary btn-sm" id="purchasing-template-save-skip">Nanti saja</button>
                                <button type="button" class="btn btn-success btn-sm" id="purchasing-template-save-confirm"><i class="fas fa-save mr-1"></i>Simpan Template</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal fade" id="purchasing-issue-detail-modal" tabindex="-1" role="dialog" aria-labelledby="purchasing-issue-detail-title" aria-hidden="true" data-backdrop="static">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
                        <div class="modal-content">
                            <div class="modal-header bg-<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?> <?= $themeText ?> d-flex align-items-center">
                                <h5 class="modal-title" id="purchasing-issue-detail-title" data-detail-title><i class="fas fa-eye mr-2" aria-hidden="true"></i>Detail Issue</h5>
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
    var actionRights = <?= json_encode($actionRights, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var csrfToken = <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var templates = {};
    var issueUi = window.PurchasingIssueUi;
    var pendingTemplateData = null;

    /**
     * Menghasilkan tanggal kalender lokal untuk nilai awal input Due Date.
     *
     * @return {string} Tanggal dengan format YYYY-MM-DD.
     */
    function currentLocalDateInputValue() {
        var currentDate = new Date();
        var timezoneOffsetMilliseconds = currentDate.getTimezoneOffset() * 60 * 1000;
        return new Date(currentDate.getTime() - timezoneOffsetMilliseconds).toISOString().slice(0, 10);
    }

    function newRequestId() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        return '00000000-0000-4000-8000-000000000000'.replace(/[018]/g, function (character) {
            return (Number(character) ^ (window.crypto.getRandomValues(new Uint8Array(1))[0] & 15) >> Number(character) / 4).toString(16);
        });
    }

    function loadSubcategories(categoryId, selectedId) {
        var subcategory = $('#purchasing-form-subcategory');
        var selectedValue = String(selectedId || '');
        subcategory.empty()
            .prop('disabled', true)
            .append($('<option>', { value: '', text: 'Pilih kategori terlebih dahulu' }));
        if (!categoryId) {
            return $.Deferred().resolve().promise();
        }
        return $.getJSON('issue_master_api.php', { action: 'subcategories', category_id: categoryId })
            .done(function (response) {
                if (!response.success) {
                    $('#purchasing-form-error').text(response.message || 'Subkategori tidak dapat dimuat.').prop('hidden', false);
                    return;
                }

                var subcategories = response.data || [];
                if (subcategories.length === 0) {
                    subcategory.empty().append($('<option>', { value: '', text: 'Kategori ini belum memiliki subkategori aktif' }));
                    $('#purchasing-form-error').text('Kategori yang dipilih belum memiliki subkategori aktif. Hubungi pengelola kategori.').prop('hidden', false);
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
                    $('#purchasing-form-error').text('Subkategori dari data sebelumnya sudah tidak tersedia. Pilih subkategori lain sebelum menyimpan.').prop('hidden', false);
                }
            })
            .fail(function () {
                $('#purchasing-form-error').text('Subkategori tidak dapat dimuat. Coba kembali.').prop('hidden', false);
            });
    }

    function loadCategories(selectedId, selectedSubId) {
        var category = $('#purchasing-form-category');
        category.empty().append($('<option>', { value: '', text: 'Pilih kategori' }));
        return $.getJSON('issue_master_api.php', { action: 'categories' })
            .done(function (response) {
                if (!response.success) {
                    $('#purchasing-form-error').text(response.message || 'Kategori tidak dapat dimuat.').prop('hidden', false);
                    return;
                }
                $.each(response.data || [], function (_index, item) {
                    category.append($('<option>', { value: item.category_id, text: item.category_name }));
                });
                category.val(selectedId || '');
                loadSubcategories(category.val(), selectedSubId);
            })
            .fail(function () {
                $('#purchasing-form-error').text('Kategori tidak dapat dimuat.').prop('hidden', false);
            });
    }

    function resetIssueForm(loadDefaultCategories) {
        var form = $('#purchasing-issue-form');
        form[0].reset();
        $('#purchasing-form-issue-id, #purchasing-form-row-version').val('');
        $('#purchasing-form-request-id').val(newRequestId());
        $('#purchasing-issue-form-title').html('<i class="fas fa-plus-circle mr-2"></i>Tambah Issue Baru');
        $('#purchasing-form-status option[value="Done"]').prop('disabled', true);
        $('#purchasing-form-error').prop('hidden', true).text('');
        if (loadDefaultCategories !== false) {
            $('#purchasing-form-due-date').val(currentLocalDateInputValue());
            loadCategories('', '');
        }
    }

    /**
     * Mengubah tanggal pembuatan Issue dari API menjadi format tanggal pada Informasi Akun.
     *
     * @param {string|null|undefined} value Nilai tanggal atau tanggal-waktu dari API.
     * @returns {string} Tanggal berformat DD-MM-YYYY atau tanda strip.
     */
    function formatIssueOwnerDate(value) {
        var rawValue = String(value || '').trim();
        var matchedDate = rawValue.match(/^(\d{4})-(\d{2})-(\d{2})/);

        if (!matchedDate) {
            return rawValue || '-';
        }

        return matchedDate[3] + '-' + matchedDate[2] + '-' + matchedDate[1];
    }

    /**
     * Menampilkan snapshot akun pemilik Issue pada form Ubah tanpa mengubah kepemilikan Issue.
     *
     * @param {Object} issue Data detail Issue dari endpoint terotorisasi.
     * @returns {void}
     */
    function populateIssueOwnerAccount(issue) {
        $('#purchasing-account-name').val(issue.created_by || '-');
        $('#purchasing-account-position').val(issue.jabatan || '-');
        $('#purchasing-account-date').val(formatIssueOwnerDate(issue.created_at));
        $('#purchasing-account-department').val(issue.departemen || '-');
        $('#purchasing-account-section').val(issue.bagian || '-');
    }

    function loadTemplates() {
        templates = {};
        $('#purchasing-template-picker-list').prop('hidden', true).empty();
        $('#purchasing-template-picker-state').text('Memuat template...').prop('hidden', false);
        return $.getJSON('get_user_issue_templates.php').done(function (response) {
            if (!response.success) {
                $('#purchasing-template-picker-state').text(response.message || 'Template tidak dapat dimuat.');
                return;
            }
            $.each(response.data || [], function (_index, template) {
                templates[String(template.template_id)] = template;
            });
            renderTemplatePicker();
        }).fail(function () {
            $('#purchasing-template-picker-state').text('Template tidak dapat dimuat.');
        });
    }

    /**
     * Merender daftar template pribadi pada modal pemilih tanpa memasukkan data
     * template ke HTML sebagai string agar nama/deskripsi tetap aman ditampilkan.
     *
     * @return {void}
     */
    function renderTemplatePicker() {
        var list = $('#purchasing-template-picker-list');
        var templateItems = Object.keys(templates).map(function (templateId) {
            return templates[templateId];
        });
        var searchInput = $('#purchasing-template-search');
        var searchReset = $('#purchasing-template-search-reset');
        var searchTerm = String(searchInput.val() || '').trim().toLocaleLowerCase('id-ID');

        if (searchTerm !== '') {
            templateItems = templateItems.filter(function (template) {
                var searchableText = [
                    template.template_name,
                    template.issue_type,
                    template.priority,
                    template.initial_status,
                    template.description
                ].filter(Boolean).join(' ').toLocaleLowerCase('id-ID');
                return searchableText.indexOf(searchTerm) !== -1;
            });
        }

        list.empty();
        searchReset.prop('hidden', searchTerm === '');
        if (templateItems.length === 0 && searchTerm !== '') {
            $('#purchasing-template-picker-state').text('Tidak ada template yang cocok dengan pencarian.').prop('hidden', false);
            return;
        }

        if (templateItems.length === 0) {
            $('#purchasing-template-picker-state').text('Belum ada template yang tersimpan.').prop('hidden', false);
            return;
        }

        $.each(templateItems, function (_index, template) {
            var row = $('<div>', {
                class: 'list-group-item d-flex justify-content-between align-items-center flex-wrap'
            });
            var content = $('<div>', { class: 'pr-3 mb-2 mb-md-0' });
            $('<strong>').text(template.template_name || 'Tanpa nama').appendTo(content);
            $('<div>', { class: 'small text-muted mt-1' })
                .text([template.issue_type, template.priority, template.initial_status].filter(Boolean).join(' • '))
                .appendTo(content);
            if (template.description) {
                $('<div>', { class: 'small text-muted mt-1' }).text(template.description).appendTo(content);
            }

            var actions = $('<div>', { class: 'btn-group btn-group-sm', role: 'group' });
            $('<button>', {
                type: 'button',
                class: 'btn btn-success',
                text: 'Gunakan',
                'data-action': 'use-template',
                'data-template-id': template.template_id
            }).appendTo(actions);
            if (actionRights.canDelete) {
                $('<button>', {
                    type: 'button',
                    class: 'btn btn-outline-danger',
                    text: 'Hapus',
                    'data-action': 'delete-template',
                    'data-template-id': template.template_id
                }).appendTo(actions);
            }

            row.append(content, actions);
            list.append(row);
        });

        $('#purchasing-template-picker-state').prop('hidden', true);
        list.prop('hidden', false);
    }

    /**
     * Mengisi form Issue Purchasing dari satu template milik pengguna aktif.
     *
     * @param {Object} template Data template yang telah dimuat dari server.
     * @return {void}
     */
    function applyPurchasingIssueTemplate(template) {
        $('#purchasing-form-name').val(template.template_name || '');
        $('#purchasing-form-type').val(template.issue_type);
        $('#purchasing-form-priority').val(template.priority);
        $('#purchasing-form-status').val(template.initial_status);
        $('#purchasing-form-description').val(template.description || '');
        loadCategories(template.category_id, template.sub_id);
        if (!template.sub_id) {
            $('#purchasing-form-error').text('Template ini belum memiliki subkategori. Pilih subkategori sebelum menyimpan Issue.').prop('hidden', false);
        }
    }

    /**
     * Menyalin konfigurasi Create Issue yang telah berhasil disimpan ke payload template pribadi.
     *
     * @param {JQuery} form Form Create Issue yang menjadi sumber konfigurasi.
     * @returns {Object} Payload aman untuk endpoint template.
     */
    function buildTemplatePayload(form) {
        return {
            template_name: String(form.find('[name="issue_name"]').val() || '').trim(),
            issue_type: String(form.find('[name="issue_type"]').val() || ''),
            category_id: String(form.find('[name="category_id"]').val() || ''),
            sub_id: String(form.find('[name="sub_id"]').val() || ''),
            priority: String(form.find('[name="priority"]').val() || ''),
            description: String(form.find('[name="description"]').val() || ''),
            initial_status: String(form.find('[name="status"]').val() || '')
        };
    }

    /**
     * Memeriksa cache template pribadi berdasarkan nama agar dialog tidak ditampilkan dua kali.
     *
     * @param {string} templateName Nama template yang akan dibandingkan.
     * @returns {boolean} True bila nama sudah tersimpan.
     */
    function hasTemplateName(templateName) {
        var normalizedName = String(templateName || '').trim().toLocaleLowerCase('id-ID');
        return Object.keys(templates).some(function (templateId) {
            return String(templates[templateId].template_name || '').trim().toLocaleLowerCase('id-ID') === normalizedName;
        });
    }

    /**
     * Membuka dialog simpan template hanya bila Create menghasilkan nama template baru.
     *
     * @param {Object} templateData Konfigurasi yang dibekukan saat Create berhasil.
     * @returns {void}
     */
    function offerTemplateSave(templateData) {
        if (!templateData.template_name || !actionRights.canAdd) {
            return;
        }

        loadTemplates().always(function () {
            if (hasTemplateName(templateData.template_name)) {
                return;
            }

            pendingTemplateData = templateData;
            $('#purchasing-template-save-name').val(templateData.template_name);
            $('#purchasing-template-save-error').prop('hidden', true).text('');
            $('#purchasing-template-save-modal').modal('show');
        });
    }

    /**
     * Menunda modal template sampai SweetAlert selesai mengembalikan aksesibilitas
     * shell halaman, sehingga modal berikutnya tidak berada dalam elemen aria-hidden.
     *
     * @param {Object} templateData Konfigurasi template hasil Create Issue.
     * @returns {void}
     */
    function offerTemplateSaveWhenReady(templateData) {
        var pageShell = document.querySelector('.wrapper');
        var sweetAlertIsOpen = document.querySelector('.swal2-container') !== null;
        var pageShellIsHidden = pageShell && pageShell.getAttribute('aria-hidden') === 'true';

        if (sweetAlertIsOpen || pageShellIsHidden) {
            window.setTimeout(function () {
                offerTemplateSaveWhenReady(templateData);
            }, 50);
            return;
        }

        offerTemplateSave(templateData);
    }

    var table = $('#purchasing-issues-table').DataTable({
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
                request.scope = 'active';
                request.filterType = $('#purchasing-filter-type').val();
                request.filterStatus = $('#purchasing-filter-status').val();
                request.filterTanggalStart = $('#purchasing-filter-date-start').val();
                request.filterTanggalEnd = $('#purchasing-filter-date-end').val();
            },
            error: function () {
                $('#purchasing-list-error').text('Daftar issue tidak dapat dimuat.').prop('hidden', false);
            }
        },
        order: [],
        columnDefs: [
            { className: 'dtr-control', orderable: false, targets: 0 },
            { responsivePriority: 1, targets: -1 },
            { responsivePriority: 2, targets: 2 },
            { responsivePriority: 3, targets: 5 },
            { responsivePriority: 4, targets: 6 }
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
                data: 'status',
                render: function (data, type) {
                    return type === 'display' ? issueUi.renderBadge('status', data) : (data || '');
                }
            },
            {
                data: 'priority',
                render: function (data, type) {
                    return type === 'display' ? issueUi.renderBadge('priority', data) : (data || '');
                }
            },
            { data: 'created_at', render: $.fn.dataTable.render.text() },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function (_data, _type, row) {
                    var issueId = Number(row.issue_id);
                    var buttons = '';
                    if (row.can_mark_done === true) {
                        buttons += issueUi.renderActionButton('mark-done', issueId, 'Tandai selesai', 'btn-success', 'fas fa-check');
                    }
                    buttons += issueUi.renderActionButton('view-issue', issueId, 'Detail', 'btn-info', 'fas fa-eye');
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
            emptyTable: 'Belum ada issue untuk ditampilkan.',
            processing: 'Memproses...',
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ data per halaman',
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ issue',
            infoEmpty: 'Tidak ada issue',
            paginate: { previous: 'Sebelumnya', next: 'Berikutnya' }
        }
    });

    var searchInput = $('#purchasing-issues-table_filter input[type="search"]');
    searchInput.attr({
        id: 'purchasing-issues-search',
        'aria-label': 'Cari issue Purchasing'
    });

    $('#purchasing-filter-type, #purchasing-filter-status, #purchasing-filter-date-start, #purchasing-filter-date-end').on('change', function () {
        table.ajax.reload();
    });

    $('#purchasing-reset-filters').on('click', function () {
        $('#purchasing-filter-type, #purchasing-filter-status, #purchasing-filter-date-start, #purchasing-filter-date-end').val('');
        table.ajax.reload();
    });

    $('#purchasing-active-export-pdf').on('click', function () {
        var query = new URLSearchParams({
            scope: 'active',
            filterType: $('#purchasing-filter-type').val() || '',
            filterStatus: $('#purchasing-filter-status').val() || '',
            filterTanggalStart: $('#purchasing-filter-date-start').val() || '',
            filterTanggalEnd: $('#purchasing-filter-date-end').val() || ''
        });
        window.open('export_issues_pdf.php?' + query.toString(), '_blank', 'noopener');
    });

    $('#purchasing-add-open').on('click', function () {
        resetIssueForm(true);
    });

    $('#purchasing-template-open').on('click', function () {
        $('#purchasing-template-search').val('');
        $('#purchasing-template-search-reset').prop('hidden', true);
        $('#purchasing-template-picker-modal').modal('show');
        loadTemplates();
    });

    $('#purchasing-template-search').on('input', function () {
        renderTemplatePicker();
    });

    $('#purchasing-template-search-reset').on('click', function () {
        $('#purchasing-template-search').val('').trigger('input').trigger('focus');
    });

    $('#purchasing-template-picker-modal').on('hide.bs.modal', function () {
        if (this.contains(document.activeElement)) {
            document.activeElement.blur();
        }
    }).on('hidden.bs.modal', function () {
        $('#purchasing-template-open').trigger('focus');
    });

    $('#purchasing-template-save-skip').on('click', function () {
        $('#purchasing-template-save-modal').modal('hide');
    });

    $('#purchasing-template-save-modal').on('hidden.bs.modal', function () {
        pendingTemplateData = null;
        $('#purchasing-add-open').trigger('focus');
    });

    $('#purchasing-template-save-confirm').on('click', function () {
        var button = $(this);
        var templateName = String($('#purchasing-template-save-name').val() || '').trim();
        if (!pendingTemplateData || !templateName) {
            $('#purchasing-template-save-error').text('Nama template wajib diisi.').prop('hidden', false);
            return;
        }

        button.prop('disabled', true);
        $('#purchasing-template-save-error').prop('hidden', true).text('');
        $.ajax({
            url: 'save_issue_template.php',
            type: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            dataType: 'json',
            data: $.extend({}, pendingTemplateData, { template_name: templateName, csrf_token: csrfToken })
        }).done(function (response) {
            if (!response.success) {
                $('#purchasing-template-save-error').text(response.message || 'Template tidak dapat disimpan.').prop('hidden', false);
                return;
            }

            $('#purchasing-template-save-modal').modal('hide');
            issueUi.showSuccess(response.message || 'Template berhasil disimpan.');
            loadTemplates();
        }).fail(function (xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : 'Template tidak dapat disimpan.';
            $('#purchasing-template-save-error').text(message).prop('hidden', false);
        }).always(function () {
            button.prop('disabled', false);
        });
    });

    $('#purchasing-form-category').on('change', function () {
        loadSubcategories($(this).val(), '');
    });

    $('#purchasing-template-picker-list').on('click', '[data-action="use-template"], [data-action="delete-template"]', function () {
        var button = $(this);
        var templateId = String(button.attr('data-template-id') || '');
        var template = templates[templateId];
        if (!template) {
            return;
        }

        if (button.attr('data-action') === 'use-template') {
            applyPurchasingIssueTemplate(template);
            $('#purchasing-template-picker-modal').modal('hide');
            return;
        }

        button.prop('disabled', true);
        issueUi.confirmDelete({
            title: 'Hapus Template?',
            text: 'Template pribadi ini akan dihapus permanen.',
            target: '#purchasing-template-picker-modal .modal-content'
        }).then(function (result) {
            if (!result.isConfirmed) {
                button.prop('disabled', false);
                button.trigger('focus');
                return;
            }

            $.ajax({
                url: 'delete_issue_template.php',
                type: 'POST',
                data: { template_id: template.template_id, csrf_token: csrfToken },
                dataType: 'json',
                headers: { 'X-CSRF-Token': csrfToken }
            }).done(function (response) {
                if (response.success) {
                    issueUi.showSuccess(response.message || 'Template berhasil dihapus.');
                    loadTemplates();
                    return;
                }

                $('#purchasing-template-picker-state').text(response.message || 'Template tidak dapat dihapus.').prop('hidden', false);
                issueUi.showError(response.message || 'Template tidak dapat dihapus.', {
                    target: '#purchasing-template-picker-modal .modal-content'
                });
            }).fail(function () {
                var message = 'Template tidak dapat dihapus.';
                $('#purchasing-template-picker-state').text(message).prop('hidden', false);
                issueUi.showError(message, { target: '#purchasing-template-picker-modal .modal-content' });
            }).always(function () {
                button.prop('disabled', false);
            });
        });
    });

    $('#purchasing-issue-form').on('submit', function (event) {
        event.preventDefault();
        var form = $(this);
        var editing = $('#purchasing-form-issue-id').val() !== '';
        var templateData = editing ? null : buildTemplatePayload(form);
        var submit = $('#purchasing-form-submit');
        submit.prop('disabled', true);
        $('#purchasing-form-error').prop('hidden', true).text('');
        $.ajax({
            url: editing ? 'update_issue_admin.php' : 'save_issue.php',
            type: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            data: form.serialize(),
            dataType: 'json'
        }).done(function (response) {
            if (!response.success) {
                var failureMessage = response.message || 'Issue tidak dapat disimpan.';
                $('#purchasing-form-error').text(failureMessage).prop('hidden', false);
                issueUi.showError(failureMessage, { target: '#purchasing-issue-form-modal .modal-content' });
                return;
            }
            /**
             * Menjalankan umpan balik setelah modal form benar-benar tertutup agar
             * SweetAlert tidak menyembunyikan elemen yang sedang memegang fokus.
             *
             * @returns {void}
             */
            var afterIssueModalHidden = function () {
                table.ajax.reload(null, false);
                var successNotice = issueUi.showSuccess(response.message || 'Issue berhasil disimpan.');
                var continueAfterSuccess = function () {
                    if (!editing && templateData) {
                        offerTemplateSaveWhenReady(templateData);
                    }
                };
                if (successNotice && typeof successNotice.then === 'function') {
                    successNotice.then(continueAfterSuccess);
                    return;
                }
                continueAfterSuccess();
            };
            $('#purchasing-issue-form-modal').one('hidden.bs.modal', function () {
                window.setTimeout(afterIssueModalHidden, 0);
            });
            $('#purchasing-issue-form-modal').modal('hide');
        }).fail(function (xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Issue tidak dapat disimpan.';
            $('#purchasing-form-error').text(message).prop('hidden', false);
            issueUi.showError(message, { target: '#purchasing-issue-form-modal .modal-content' });
        }).always(function () {
            submit.prop('disabled', false);
        });
    });

    /**
     * Memuat dan menampilkan detail Issue Purchasing dari aksi tabel yang dipilih.
     *
     * @param {number} issueId ID Issue Purchasing yang valid.
     * @returns {JQuery.jqXHR|undefined} Request detail bila ID valid.
     */
    function showPurchasingIssueDetail(issueId) {
        var modal = $('#purchasing-issue-detail-modal');
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

    $('#purchasing-issues-table').on('click', '[data-action="view-issue"]', function () {
        showPurchasingIssueDetail(Number($(this).attr('data-issue-id')));
    });

    $('#purchasing-issues-table').on('click', '[data-action="mark-done"], [data-action="edit-issue"], [data-action="delete-issue"]', function () {
        var button = $(this);
        var issueId = Number(button.attr('data-issue-id'));
        var action = button.attr('data-action');
        if (!Number.isSafeInteger(issueId) || issueId < 1) {
            issueUi.showError('ID issue tidak valid.');
            return;
        }
        button.prop('disabled', true);
        $.getJSON('get_issue_detail.php', { issue_id: issueId }).done(function (response) {
            if (!response.success || !response.data) {
                var detailMessage = response.message || 'Detail issue tidak dapat dimuat.';
                $('#purchasing-list-error').text(detailMessage).prop('hidden', false);
                issueUi.showError(detailMessage);
                button.prop('disabled', false);
                return;
            }
            var issue = response.data;
            if (action === 'mark-done') {
                Swal.fire({
                    title: 'Tandai Selesai?',
                    text: 'Issue ini akan dipindahkan ke Riwayat.',
                    icon: 'success',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, selesai',
                    cancelButtonText: 'Batal'
                }).then(function (result) {
                    if (!result.isConfirmed) {
                        button.prop('disabled', false);
                        button.trigger('focus');
                        return;
                    }

                    $.ajax({
                        url: 'mark_issue_done.php',
                        type: 'POST',
                        headers: { 'X-CSRF-Token': csrfToken },
                        dataType: 'json',
                        data: {
                            issue_id: issue.issue_id,
                            row_version: issue.row_version,
                            csrf_token: csrfToken
                        }
                    }).done(function (doneResponse) {
                        if (doneResponse.success) {
                            table.ajax.reload(null, false);
                            issueUi.showSuccess(doneResponse.message || 'Issue berhasil ditandai selesai.');
                            return;
                        }

                        var doneMessage = doneResponse.message || 'Issue tidak dapat ditandai selesai.';
                        $('#purchasing-list-error').text(doneMessage).prop('hidden', false);
                        issueUi.showError(doneMessage);
                    }).fail(function (xhr) {
                        var doneMessage = xhr.responseJSON && xhr.responseJSON.message
                            ? xhr.responseJSON.message
                            : 'Issue tidak dapat ditandai selesai.';
                        $('#purchasing-list-error').text(doneMessage).prop('hidden', false);
                        issueUi.showError(doneMessage);
                    }).always(function () {
                        button.prop('disabled', false);
                    });
                });
                return;
            }

            if (action === 'delete-issue') {
                issueUi.confirmDelete({
                    title: 'Hapus Issue?',
                    text: 'Issue ini akan dihapus permanen dan tidak dapat dikembalikan.'
                }).then(function (result) {
                    if (!result.isConfirmed) {
                        button.prop('disabled', false);
                        button.trigger('focus');
                        return;
                    }

                    $.ajax({
                        url: 'delete_issue.php',
                        type: 'POST',
                        headers: { 'X-CSRF-Token': csrfToken },
                        dataType: 'json',
                        data: { issue_id: issue.issue_id, row_version: issue.row_version, csrf_token: csrfToken }
                    }).done(function (deleteResponse) {
                        if (deleteResponse.success) {
                            table.ajax.reload(null, false);
                            issueUi.showSuccess(deleteResponse.message || 'Issue berhasil dihapus.');
                            return;
                        }

                        var deleteMessage = deleteResponse.message || 'Issue tidak dapat dihapus.';
                        $('#purchasing-list-error').text(deleteMessage).prop('hidden', false);
                        issueUi.showError(deleteMessage);
                    }).fail(function (xhr) {
                        var deleteMessage = xhr.responseJSON && xhr.responseJSON.message
                            ? xhr.responseJSON.message
                            : 'Issue tidak dapat dihapus.';
                        $('#purchasing-list-error').text(deleteMessage).prop('hidden', false);
                        issueUi.showError(deleteMessage);
                    }).always(function () {
                        button.prop('disabled', false);
                    });
                });
                return;
            }
            resetIssueForm(false);
            populateIssueOwnerAccount(issue);
            $('#purchasing-form-issue-id').val(issue.issue_id);
            $('#purchasing-form-row-version').val(issue.row_version);
            $('#purchasing-form-name').val(issue.issue_name);
            $('#purchasing-form-type').val(issue.issue_type);
            $('#purchasing-form-status option[value="Done"]').prop('disabled', false);
            $('#purchasing-form-status').val(issue.status);
            $('#purchasing-form-priority').val(issue.priority);
            $('#purchasing-form-due-date').val(issue.due_date);
            $('#purchasing-form-description').val(issue.description);
            $('#purchasing-issue-form-title').html('<i class="fas fa-edit mr-2"></i>Ubah Issue');
            loadCategories(issue.category_id, issue.sub_id);
            $('#purchasing-issue-form-modal').modal('show');
            button.prop('disabled', false);
        }).fail(function (xhr) {
            var failureMessage = xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : 'Detail issue tidak dapat dimuat.';
            $('#purchasing-list-error').text(failureMessage).prop('hidden', false);
            issueUi.showError(failureMessage);
            button.prop('disabled', false);
        });
    });
}(window.jQuery));
</script>
</body>
</html>
