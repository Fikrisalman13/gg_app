<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
require_once __DIR__ . '/issue_access.php';

$access = purchasingIssueAccess($conn ?? null);
if (empty($access['allowed'])) {
    denyPurchasingIssueAccess($access);
}

$csrfToken = purchasingIssueCsrfToken();
$theme = strtolower((string) ($_SESSION['Theme'] ?? 'primary'));
$allowedThemes = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark', 'lime', 'white', 'teal', 'navy', 'purple', 'indigo', 'orange', 'maroon', 'fuchsia', 'olive'];
$theme = in_array($theme, $allowedThemes, true) ? $theme : 'primary';
$themeText = in_array($theme, ['warning', 'light', 'lime', 'white'], true) ? 'text-dark' : 'text-white';
$rights = [
    'canAdd' => !empty($access['can_add']),
    'canEdit' => !empty($access['can_edit']),
    'canDelete' => !empty($access['can_delete'])
];

include $_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php';
?>
<style>
    .purchasing-category-action {
        align-items: center;
        display: inline-flex;
        height: 32px;
        justify-content: center;
        margin-right: 0.25rem;
        padding: 0;
        width: 32px;
    }

    .purchasing-category-row {
        cursor: pointer;
    }
</style>

<main class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1>Kelola Kategori Issue Purchasing</h1>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <p id="purchasing-category-message" class="alert" role="alert" hidden></p>

            <div class="row">
                <div class="col-md-6">
                    <div class="card card-outline card-<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="card-header bg-<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?> <?= $themeText ?>">
                            <h3 class="card-title">Kategori Utama</h3>
                            <div class="card-tools">
                                <button
                                    class="btn btn-tool"
                                    id="purchasing-add-category"
                                    type="button"
                                    <?= $rights['canAdd'] ? '' : 'disabled title="Tidak punya hak menambah"' ?>
                                >
                                    <i class="fas fa-plus"></i> Tambah
                                </button>
                            </div>
                        </div>
                        <div class="card-body table-responsive p-0" style="max-height: 70vh; overflow-y: auto;">
                            <table class="table table-hover table-sm text-nowrap" id="purchasing-category-table">
                                <thead>
                                    <tr>
                                        <th style="width: 10px;">No</th>
                                        <th>Nama Kategori</th>
                                        <th style="width: 110px;">Digunakan</th>
                                        <th style="width: 100px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="purchasing-category-list">
                                    <tr>
                                        <td class="text-center" colspan="4">Memuat data...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card card-outline card-<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>" id="purchasing-subcategory-card" hidden>
                        <div class="card-header bg-<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?> <?= $themeText ?>">
                            <h3 class="card-title">Sub Kategori: <span id="purchasing-selected-category-name"></span></h3>
                            <div class="card-tools">
                                <button
                                    class="btn btn-tool"
                                    id="purchasing-add-subcategory"
                                    type="button"
                                    <?= $rights['canAdd'] ? '' : 'disabled title="Tidak punya hak menambah"' ?>
                                >
                                    <i class="fas fa-plus"></i> Tambah Sub
                                </button>
                            </div>
                        </div>
                        <div class="card-body table-responsive p-0" style="max-height: 70vh; overflow-y: auto;">
                            <table class="table table-hover table-sm text-nowrap" id="purchasing-subcategory-table">
                                <thead>
                                    <tr>
                                        <th style="width: 10px;">No</th>
                                        <th>Nama Sub Kategori</th>
                                        <th style="width: 110px;">Digunakan</th>
                                        <th style="width: 100px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="purchasing-subcategory-list"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="alert alert-info" id="purchasing-select-category-message">
                        Silakan pilih kategori untuk melihat sub-kategori.
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'; ?>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script>
(function ($) {
    'use strict';

    var csrfToken = <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var rights = <?= json_encode($rights, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var selectedCategory = null;

    /**
     * Menampilkan pesan aman di atas daftar master Purchasing.
     *
     * @param {string} text Pesan untuk pengguna.
     * @param {boolean} isError Menentukan gaya sukses atau gagal.
     * @return {void}
     */
    function showMessage(text, isError) {
        $('#purchasing-category-message')
            .removeClass('alert-success alert-danger')
            .addClass(isError ? 'alert-danger' : 'alert-success')
            .text(text)
            .prop('hidden', false);
    }

    /**
     * Mengirim mutasi master dengan CSRF dan format respons JSON yang konsisten.
     *
     * @param {Object} payload Operasi dan parameter yang sudah divalidasi oleh UI.
     * @return {jqXHR} Permintaan AJAX ke endpoint master Purchasing.
     */
    function requestMasterChange(payload) {
        return $.ajax({
            url: 'issue_master_api.php',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-Token': csrfToken },
            data: $.extend({}, payload, { csrf_token: csrfToken })
        });
    }

    /**
     * Membuat tombol aksi bergaya halaman Issues tanpa menyisipkan nama master ke HTML.
     *
     * @param {string} action Aksi tombol yang ditangani delegasi event.
     * @param {number} id ID kategori atau subkategori.
     * @param {string} title Label bantu tombol.
     * @param {string} color Kelas warna Bootstrap.
     * @param {string} icon Kelas ikon Font Awesome.
     * @param {boolean} disabled Menentukan apakah tombol bisa diklik.
     * @return {jQuery} Tombol aksi.
     */
    function actionButton(action, id, title, color, icon, disabled) {
        return $('<button>', {
            type: 'button',
            class: 'btn ' + color + ' purchasing-category-action',
            title: title,
            disabled: disabled,
            'data-action': action,
            'data-id': id
        }).append($('<i>', { class: icon }));
    }

    /**
     * Mengembalikan angka referensi master dari respons API secara aman.
     *
     * @param {Object} item Baris master dari API.
     * @return {number} Jumlah referensi issue dan template.
     */
    function referenceCount(item) {
        var count = Number(item.reference_count || 0);
        return Number.isSafeInteger(count) && count > 0 ? count : 0;
    }

    /**
     * Merender rincian penggunaan agar Issue dan template tidak tercampur dalam
     * satu angka yang membingungkan pengguna.
     *
     * @param {Object} item Baris master dari API.
     * @returns {JQuery|string} Badge penggunaan atau string kosong bila tidak dipakai.
     */
    function referenceBadges(item) {
        var issueCount = Number(item.issue_reference_count || 0);
        var templateCount = Number(item.template_reference_count || 0);
        var badges = $();

        if (Number.isSafeInteger(issueCount) && issueCount > 0) {
            badges = badges.add($('<span>', {
                class: 'badge badge-info badge-pill mr-1',
                text: issueCount + ' Issue'
            }));
        }

        if (Number.isSafeInteger(templateCount) && templateCount > 0) {
            badges = badges.add($('<span>', {
                class: 'badge badge-secondary badge-pill',
                text: templateCount + ' Template'
            }));
        }

        return badges.length > 0 ? badges : '';
    }

    /**
     * Merender daftar kategori utama dan mempertahankan kategori yang dipilih.
     *
     * @param {Array<Object>} categories Data kategori dari API.
     * @return {void}
     */
    function renderCategories(categories) {
        var list = $('#purchasing-category-list').empty();
        list.data('categories', categories);
        if (categories.length === 0) {
            list.append($('<tr>').append($('<td>', {
                class: 'text-center',
                colspan: 4,
                text: 'Belum ada data'
            })));
            return;
        }

        $.each(categories, function (index, category) {
            var categoryId = Number(category.category_id);
            var count = referenceCount(category);
            var row = $('<tr>', {
                class: 'purchasing-category-row' + (selectedCategory && selectedCategory.id === categoryId ? ' bg-lightblue' : ''),
                'data-category-id': categoryId,
                'data-category-name': category.category_name,
                'data-is-active': Number(category.is_active) === 1 ? '1' : '0'
            });
            var nameCell = $('<td>').text(category.category_name);
            if (Number(category.is_active) !== 1) {
                nameCell.append(' ').append($('<span>', {
                    class: 'badge badge-secondary',
                    text: 'Nonaktif'
                }));
            }

            var actions = $('<td>');
            actions.append(actionButton('edit-category', categoryId, 'Ubah kategori', 'btn-warning', 'fas fa-edit', !rights.canEdit));
            actions.append(actionButton('delete-category', categoryId, count > 0 ? 'Tidak dapat dihapus karena masih digunakan' : 'Hapus kategori', 'btn-danger', 'fas fa-trash', !rights.canDelete || count > 0));

            row.append(
                $('<td>').text(index + 1),
                nameCell,
                $('<td>').append(referenceBadges(category)),
                actions
            );
            list.append(row);
        });
    }

    /**
     * Merender subkategori untuk satu kategori yang sudah dipilih.
     *
     * @param {Array<Object>} subcategories Data subkategori dari API.
     * @return {void}
     */
    function renderSubcategories(subcategories) {
        var list = $('#purchasing-subcategory-list').empty();
        if (subcategories.length === 0) {
            list.append($('<tr>').append($('<td>', {
                class: 'text-center',
                colspan: 4,
                text: 'Belum ada sub kategori'
            })));
            return;
        }

        $.each(subcategories, function (index, subcategory) {
            var subcategoryId = Number(subcategory.sub_id);
            var count = referenceCount(subcategory);
            var nameCell = $('<td>').text(subcategory.sub_name);
            if (Number(subcategory.is_active) !== 1) {
                nameCell.append(' ').append($('<span>', {
                    class: 'badge badge-secondary',
                    text: 'Nonaktif'
                }));
            }

            var actions = $('<td>');
            actions.append(actionButton('edit-subcategory', subcategoryId, 'Ubah sub kategori', 'btn-warning', 'fas fa-edit', !rights.canEdit));
            actions.append(actionButton('delete-subcategory', subcategoryId, count > 0 ? 'Tidak dapat dihapus karena masih digunakan' : 'Hapus sub kategori', 'btn-danger', 'fas fa-trash', !rights.canDelete || count > 0));

            list.append($('<tr>', {
                'data-subcategory-id': subcategoryId,
                'data-is-active': Number(subcategory.is_active) === 1 ? '1' : '0'
            }).append(
                $('<td>').text(index + 1),
                nameCell,
                $('<td>').append(referenceBadges(subcategory)),
                actions
            ));
        });
    }

    /**
     * Memuat satu daftar subkategori dengan kategori yang dipilih.
     *
     * @return {void}
     */
    function loadSelectedSubcategories() {
        if (!selectedCategory) {
            return;
        }

        $('#purchasing-subcategory-list').html('<tr><td class="text-center" colspan="4">Memuat data...</td></tr>');
        $.getJSON('issue_master_api.php', {
            action: 'subcategories',
            category_id: selectedCategory.id,
            include_inactive: 1
        }).done(function (response) {
            if (!response.success) {
                showMessage(response.message || 'Sub kategori tidak dapat dimuat.', true);
                return;
            }
            renderSubcategories(response.data || []);
        }).fail(function (xhr) {
            showMessage((xhr.responseJSON || {}).message || 'Sub kategori tidak dapat dimuat.', true);
        });
    }

    /**
     * Memuat kategori dari endpoint Purchasing dan menyegarkan panel aktif.
     *
     * @return {void}
     */
    function loadCategories() {
        $.getJSON('issue_master_api.php', {
            action: 'categories',
            include_inactive: 1
        }).done(function (response) {
            if (!response.success) {
                showMessage(response.message || 'Kategori tidak dapat dimuat.', true);
                return;
            }

            var categories = response.data || [];
            if (selectedCategory && !categories.some(function (category) {
                return Number(category.category_id) === selectedCategory.id;
            })) {
                selectedCategory = null;
                $('#purchasing-subcategory-card').prop('hidden', true);
                $('#purchasing-select-category-message').prop('hidden', false);
            }

            renderCategories(categories);
            if (selectedCategory) {
                loadSelectedSubcategories();
            }
        }).fail(function (xhr) {
            showMessage((xhr.responseJSON || {}).message || 'Kategori tidak dapat dimuat.', true);
        });
    }

    /**
     * Membuka dialog teks konsisten dengan halaman kategori Issues referensi.
     *
     * @param {string} title Judul dialog.
     * @param {string} label Label input.
     * @param {string} value Nilai awal untuk Edit.
     * @return {Promise<string|undefined>} Nama master atau undefined bila dibatalkan.
     */
    function askMasterName(title, label, value) {
        return Swal.fire({
            title: title,
            input: 'text',
            inputLabel: label,
            inputValue: value || '',
            inputAttributes: { maxlength: 200, autocomplete: 'off' },
            showCancelButton: true,
            inputValidator: function (input) {
                return input.trim() === '' ? label + ' wajib diisi.' : undefined;
            }
        }).then(function (result) {
            return result.isConfirmed ? result.value.trim() : undefined;
        });
    }

    /**
     * Menjalankan mutasi dan menyegarkan daftar hanya ketika server menyatakan sukses.
     *
     * @param {Object} payload Payload endpoint master Purchasing.
     * @return {void}
     */
    function mutateAndRefresh(payload) {
        requestMasterChange(payload).done(function (response) {
            if (!response.success) {
                showMessage(response.message || 'Operasi gagal.', true);
                return;
            }
            showMessage(response.message || 'Data master berhasil disimpan.', false);
            loadCategories();
        }).fail(function (xhr) {
            showMessage((xhr.responseJSON || {}).message || 'Operasi gagal.', true);
        });
    }

    $('#purchasing-add-category').on('click', function () {
        askMasterName('Tambah Kategori', 'Nama Kategori').then(function (name) {
            if (name) {
                mutateAndRefresh({ operation: 'create_category', name: name });
            }
        });
    });

    $('#purchasing-add-subcategory').on('click', function () {
        if (!selectedCategory) {
            return;
        }
        askMasterName('Tambah Sub Kategori', 'Nama Sub Kategori').then(function (name) {
            if (name) {
                mutateAndRefresh({
                    operation: 'create_subcategory',
                    category_id: selectedCategory.id,
                    name: name
                });
            }
        });
    });

    $('#purchasing-category-list').on('click', '.purchasing-category-row', function (event) {
        if ($(event.target).closest('button').length) {
            return;
        }

        selectedCategory = {
            id: Number($(this).attr('data-category-id')),
            name: String($(this).attr('data-category-name') || '')
        };
        $('#purchasing-selected-category-name').text(selectedCategory.name);
        $('#purchasing-subcategory-card').prop('hidden', false);
        $('#purchasing-select-category-message').prop('hidden', true);
        renderCategories($('#purchasing-category-list').data('categories') || []);
        loadSelectedSubcategories();
    });

    $(document).on('click', '[data-action="edit-category"]', function () {
        var id = Number($(this).attr('data-id'));
        var row = $(this).closest('tr');
        var currentName = row.attr('data-category-name') || row.children().eq(1).clone().children().remove().end().text().trim();
        var isActive = row.attr('data-is-active') === '1' ? 1 : 0;
        askMasterName('Edit Kategori', 'Nama Kategori', currentName).then(function (name) {
            if (name) {
                mutateAndRefresh({ operation: 'update_category', id: id, name: name, is_active: isActive });
            }
        });
    });

    $(document).on('click', '[data-action="delete-category"]', function () {
        var id = Number($(this).attr('data-id'));
        Swal.fire({
            title: 'Hapus Kategori?',
            text: 'Kategori yang masih dipakai Issue atau Template tidak dapat dihapus.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Hapus'
        }).then(function (result) {
            if (result.isConfirmed) {
                mutateAndRefresh({ operation: 'delete_category', id: id });
            }
        });
    });

    $(document).on('click', '[data-action="edit-subcategory"]', function () {
        var id = Number($(this).attr('data-id'));
        var row = $(this).closest('tr');
        var currentName = row.children().eq(1).clone().children().remove().end().text().trim();
        var isActive = row.attr('data-is-active') === '1' ? 1 : 0;
        askMasterName('Edit Sub Kategori', 'Nama Sub Kategori', currentName).then(function (name) {
            if (name && selectedCategory) {
                mutateAndRefresh({
                    operation: 'update_subcategory',
                    id: id,
                    category_id: selectedCategory.id,
                    name: name,
                    is_active: isActive
                });
            }
        });
    });

    $(document).on('click', '[data-action="delete-subcategory"]', function () {
        var id = Number($(this).attr('data-id'));
        Swal.fire({
            title: 'Hapus Sub Kategori?',
            text: 'Sub kategori yang masih dipakai Issue atau Template tidak dapat dihapus.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Hapus'
        }).then(function (result) {
            if (result.isConfirmed) {
                mutateAndRefresh({ operation: 'delete_subcategory', id: id });
            }
        });
    });

    loadCategories();
}(window.jQuery));
</script>
</body>
</html>
