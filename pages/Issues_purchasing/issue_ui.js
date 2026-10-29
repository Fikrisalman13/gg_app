(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    var badgeMaps = {
        type: {
            Task: { className: 'purchasing-type-task', icon: 'fas fa-check-square' },
            'Support Activities': { className: 'purchasing-type-support', icon: 'fas fa-book' }
        },
        status: {
            'To Do': { className: 'purchasing-status-todo', icon: 'fas fa-list-ul' },
            'In Progress': { className: 'purchasing-status-progress', icon: 'fas fa-spinner' },
            Done: { className: 'purchasing-status-done', icon: 'fas fa-check' }
        },
        priority: {
            Low: { className: 'purchasing-priority-low', icon: 'fas fa-arrow-down' },
            Medium: { className: 'purchasing-priority-medium', icon: 'fas fa-minus' },
            High: { className: 'purchasing-priority-high', icon: 'fas fa-exclamation' },
            Critical: { className: 'purchasing-priority-critical', icon: 'fas fa-exclamation-triangle' }
        }
    };

    /**
     * Mengubah teks menjadi aman untuk dimasukkan ke HTML yang dirender DataTables.
     *
     * @param {unknown} value Nilai yang akan ditampilkan.
     * @returns {string} Teks yang telah di-escape.
     */
    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : String(value)).html();
    }

    /**
     * Membuat badge berikon untuk type, status, atau priority Issue Purchasing.
     *
     * @param {'type'|'status'|'priority'} kind Kelompok badge yang ingin ditampilkan.
     * @param {unknown} value Nilai dari respons server.
     * @returns {string} HTML badge aman.
     */
    function renderBadge(kind, value) {
        var label = value === null || value === undefined || value === '' ? '-' : String(value);
        var definition = badgeMaps[kind] && badgeMaps[kind][label];
        var className = definition ? definition.className : 'purchasing-badge-unknown';
        var icon = definition ? '<i class="' + definition.icon + '" aria-hidden="true"></i>' : '';

        return '<span class="badge purchasing-badge ' + className + '">' + icon + '<span>' + escapeHtml(label) + '</span></span>';
    }

    /**
     * Membuat tombol aksi tabel dengan label aksesibel dan ikon yang konsisten.
     *
     * @param {string} action Nama aksi yang ditangani delegasi event.
     * @param {number} issueId ID Issue Purchasing valid.
     * @param {string} title Keterangan aksi untuk tooltip dan pembaca layar.
     * @param {string} buttonClass Kelas warna tombol Bootstrap.
     * @param {string} iconClass Kelas ikon Font Awesome.
     * @returns {string} HTML tombol aksi.
     */
    function renderActionButton(action, issueId, title, buttonClass, iconClass) {
        return '<button type="button" class="btn ' + buttonClass + ' btn-xs purchasing-action"'
            + ' data-action="' + escapeHtml(action) + '"'
            + ' data-issue-id="' + Number(issueId) + '"'
            + ' title="' + escapeHtml(title) + '"'
            + ' aria-label="' + escapeHtml(title) + '">'
            + '<i class="' + iconClass + '" aria-hidden="true"></i>'
            + '</button>';
    }

    /**
     * Mengubah nilai tanggal API menjadi teks lokal yang stabil untuk detail Issue.
     *
     * @param {string|null|undefined} value Nilai tanggal dari API.
     * @returns {string} Tanggal atau tanda strip saat tidak tersedia.
     */
    function formatDate(value) {
        if (!value) {
            return '-';
        }

        var date = new Date(String(value).length === 10 ? String(value) + 'T00:00:00' : value);
        if (Number.isNaN(date.getTime())) {
            return String(value);
        }

        return date.toLocaleDateString('id-ID');
    }

    /**
     * Mengubah nilai tanggal-waktu API menjadi teks lokal untuk timeline Issue.
     *
     * @param {string|null|undefined} value Nilai tanggal-waktu dari API.
     * @returns {string} Tanggal-waktu atau keterangan kosong.
     */
    function formatDateTime(value) {
        if (!value) {
            return '';
        }

        var date = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) {
            return String(value);
        }

        return date.toLocaleDateString('id-ID') + ' '
            + date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace(':', '.');
    }

    /**
     * Mengatur state loading, error, atau konten pada modal detail bersama.
     *
     * @param {JQuery} modal Modal detail tujuan.
     * @param {'loading'|'content'|'error'} state State yang ingin ditampilkan.
     * @param {string} [message] Pesan aman bila state error.
     * @returns {void}
     */
    function setDetailState(modal, state, message) {
        modal.find('[data-detail-loading]').toggleClass('d-none', state !== 'loading');
        modal.find('[data-detail-content]').toggleClass('d-none', state !== 'content');

        var error = modal.find('[data-detail-error]');
        if (state === 'error') {
            error.removeClass('d-none').text(message || 'Detail issue tidak tersedia.');
            return;
        }

        error.addClass('d-none').text('');
    }

    /**
     * Mengisi detail Issue Purchasing ke struktur modal yang aman tanpa HTML dari server.
     *
     * @param {JQuery} modal Modal detail tujuan.
     * @param {Object} issue Data detail dari endpoint Purchasing.
     * @returns {void}
     */
    function populateDetail(modal, issue) {
        var issueId = issue.issue_id ? 'Issue #' + issue.issue_id : 'Detail Issue';
        modal.find('[data-detail-title]').html('<i class="fas fa-eye mr-2" aria-hidden="true"></i>' + escapeHtml(issueId));
        modal.find('[data-detail-field="issue-id"]').text(issueId);
        modal.find('[data-detail-field="issue-name"]').text(issue.issue_name || '-');
        modal.find('[data-detail-field="type"]').html(renderBadge('type', issue.issue_type));
        modal.find('[data-detail-field="status-badge"]').html(renderBadge('status', issue.status));
        modal.find('[data-detail-field="priority-badge"]').html(renderBadge('priority', issue.priority));
        modal.find('[data-detail-field="due-date"]').text(formatDate(issue.due_date));
        modal.find('[data-detail-field="name"]').text(issue.created_by || '-');
        modal.find('[data-detail-field="position"]').text(issue.jabatan || '-');
        modal.find('[data-detail-field="department"]').text(issue.departemen || '-');
        modal.find('[data-detail-field="section"]').text(issue.bagian || '-');
        modal.find('[data-detail-field="category"]').text(issue.kategori || '-');
        modal.find('[data-detail-field="subcategory"]').text(issue.sub_kategori || '-');
        modal.find('[data-detail-field="created-info"]').text(formatDateTime(issue.created_at) || '-');

        var updatedInfo = issue.updated_at ? formatDateTime(issue.updated_at) : 'Belum pernah diperbarui';
        var updaterGroup = modal.find('[data-detail-updater]');
        modal.find('[data-detail-field="updated-info"]').text(updatedInfo);
        if (issue.updated_at && issue.updated_by) {
            modal.find('[data-detail-field="updated-by"]').text(issue.updated_by);
            updaterGroup.removeClass('d-none');
        } else {
            modal.find('[data-detail-field="updated-by"]').text('');
            updaterGroup.addClass('d-none');
        }

        var completion = modal.find('[data-detail-completion]');
        if (issue.tanggal_selesai) {
            completion.removeClass('d-none');
            modal.find('[data-detail-field="completion-date"]').text(formatDateTime(issue.tanggal_selesai));
        } else {
            completion.addClass('d-none');
        }

        modal.find('[data-detail-field="status-text"]').text(issue.status || '-');
        modal.find('[data-detail-field="priority-text"]').text(issue.priority || '-');
        modal.find('[data-detail-field="description"]').text((issue.description || '').trim() || 'Belum ada deskripsi.');
        setDetailState(modal, 'content');
    }

    /**
     * Membuka konfirmasi hapus dengan SweetAlert tanpa mengirimkan tindakan apa pun.
     *
     * @param {Object} options Konfigurasi judul, teks, dan target dialog opsional.
     * @returns {Promise<Object>} Hasil dialog SweetAlert atau pembatalan aman bila dependensi gagal dimuat.
     */
    function confirmDelete(options) {
        var settings = $.extend({
            title: 'Hapus data?',
            text: 'Tindakan ini tidak dapat dibatalkan.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal',
            reverseButtons: true,
            focusCancel: true,
            didOpen: function () {
                window.setTimeout(function () {
                    var cancelButton = window.Swal.getCancelButton();
                    if (cancelButton) {
                        cancelButton.focus();
                    }
                }, 250);
            }
        }, options || {});

        if (window.Swal && typeof window.Swal.fire === 'function') {
            return window.Swal.fire(settings);
        }

        return Promise.resolve({ isConfirmed: false });
    }

    /**
     * Menampilkan notifikasi sukses yang konsisten dengan halaman Issues referensi.
     *
     * @param {string} message Pesan sukses yang sudah disanitasi server.
     * @returns {Promise<Object>|undefined} Promise SweetAlert bila tersedia.
     */
    function showSuccess(message) {
        if (!window.Swal || typeof window.Swal.fire !== 'function') {
            return undefined;
        }

        return window.Swal.fire({
            icon: 'success',
            title: 'Sukses!',
            text: message,
            timer: 1800,
            showConfirmButton: false
        });
    }

    /**
     * Menampilkan notifikasi kegagalan yang konsisten dan tidak memakai HTML server.
     *
     * @param {string} message Pesan kegagalan aman untuk pengguna.
     * @param {Object} [options] Opsi SweetAlert tambahan seperti target modal.
     * @returns {Promise<Object>|undefined} Promise SweetAlert bila tersedia.
     */
    function showError(message, options) {
        if (!window.Swal || typeof window.Swal.fire !== 'function') {
            return undefined;
        }

        return window.Swal.fire($.extend({
            icon: 'error',
            title: 'Tidak dapat diproses',
            text: message
        }, options || {}));
    }

    window.PurchasingIssueUi = {
        confirmDelete: confirmDelete,
        formatDate: formatDate,
        formatDateTime: formatDateTime,
        populateDetail: populateDetail,
        renderActionButton: renderActionButton,
        renderBadge: renderBadge,
        setDetailState: setDetailState,
        showError: showError,
        showSuccess: showSuccess
    };
}(window, window.jQuery));
