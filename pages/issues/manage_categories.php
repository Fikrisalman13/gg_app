<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

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

$permissions = checkPermissions($conn, $_SESSION['GroupId'], 165);
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: /gg_app/dashboard.php');
    exit;
}

// Normalize theme and determine text contrast
$themeColor = $_SESSION['Theme'] ?? 'primary';
$textClass = in_array($themeColor, ['light', 'warning', 'white', 'lime']) ? 'text-dark' : 'text-white';

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
?>
<style>
    .btn-action {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 0.25rem;
    }
    /* Modal issues table: force white background for all cells and remove zebra striping */
    #modalSubIssuesTable.dataTable tbody tr,
    #modalSubIssuesTable.dataTable tbody td,
    #modalSubIssuesTable.dataTable thead th {
        background: #ffffff !important;
        color: #212529 !important;
    }
    /* Remove default row striping */
    #modalSubIssuesTable.dataTable tbody tr.odd,
    #modalSubIssuesTable.dataTable tbody tr.even {
        background: #ffffff !important;
    }
    /* Ensure header stays white as well */
    #modalSubIssuesTable.dataTable thead th {
        background: #ffffff !important;
        border-bottom: 1px solid #dee2e6;
    }
</style>
<div class="wrapper">
    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <h1>Kelola Kategori Issue</h1>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <!-- Category List -->
                    <div class="col-md-6">
                        <div class="card card-<?php echo $themeColor; ?>">
                                <div class="card-header bg-<?php echo $themeColor; ?> <?php echo $textClass; ?>">
                                <h3 class="card-title">Kategori Utama</h3>
                                <div class="card-tools">
                                    <?php if (!empty($permissions['CanAdd'])): ?>
                                        <button class="btn btn-tool" id="btnAddCategory"><i class="fas fa-plus"></i> Tambah</button>
                                    <?php else: ?>
                                        <button class="btn btn-tool" id="btnAddCategory" disabled title="Tidak punya hak menambah"><i class="fas fa-plus"></i> Tambah</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body table-responsive p-0" style="max-height:70vh; overflow-y:auto;">
                                <table class="table table-hover table-sm text-nowrap" id="tableCategories">
                                    <thead>
                                        <tr>
                                            <th style="width: 10px">No</th>
                                                <th>Nama Kategori</th>
                                                <th style="width: 110px">Digunakan</th>
                                                <th style="width: 100px">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="listCategories">
                                        <!-- Loaded via AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Sub Category List -->
                    <div class="col-md-6">
                                 <div class="card card-secondary" id="cardSub" style="display:none;">
                                     <div class="card-header bg-<?php echo $themeColor; ?> <?php echo $textClass; ?>">
                                <h3 class="card-title">Sub Kategori: <span id="selectedCategoryName"></span></h3>
                                <div class="card-tools">
                                    <?php if (!empty($permissions['CanAdd'])): ?>
                                        <button class="btn btn-tool" id="btnAddSub"><i class="fas fa-plus"></i> Tambah Sub</button>
                                    <?php else: ?>
                                        <button class="btn btn-tool" id="btnAddSub" disabled title="Tidak punya hak menambah"><i class="fas fa-plus"></i> Tambah Sub</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body table-responsive p-0" style="max-height:70vh; overflow-y:auto;">
                                <table class="table table-hover table-sm text-nowrap">
                                    <thead>
                                        <tr>
                                            <th style="width: 10px">No</th>
                                            <th>Nama Sub Kategori</th>
                                            <th style="width: 110px">Digunakan</th>
                                            <th style="width: 100px">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="listSubCategories">
                                        <!-- Loaded via AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                                                <!-- Modal: Issues using subcategory -->
                                                <div class="modal fade" id="modalSubIssues" tabindex="-1" role="dialog" aria-hidden="true">
                                                    <div class="modal-dialog modal-lg" role="document">
                                                        <div class="modal-content">
                                                            <div class="modal-header bg-<?php echo $themeColor; ?> <?php echo $textClass; ?>">
                                                                <h5 class="modal-title">Issues Yg Menggunakan: <span id="modalSubTitle"></span></h5>
                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                                        <div class="modal-body">
                                                                            <div class="table-responsive">
                                                                                <table id="modalSubIssuesTable" class="table table-sm table-striped table-bordered" style="width:100%">
                                                                                    <thead>
                                                                                        <tr>
                                                                                            <th>No</th>
                                                                                            <th>Issue</th>
                                                                                            <th>Type</th>
                                                                                            <th>Created By</th>
                                                                                            <th>Status</th>
                                                                                        </tr>
                                                                                    </thead>
                                                                                    <tbody></tbody>
                                                                                </table>
                                                                            </div>
                                                                        </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                         <div class="alert alert-info" id="msgSelectCat">Silakan pilih kategori untuk melihat sub-kategori.</div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- SweetAlert -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<!-- DataTables (required for modal server-side table) -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>

<script>
$(document).ready(function() {
    var activeCategoryId = null;

    loadCategories();

    function loadCategories() {
        $.ajax({
            url: 'data_categories.php',
            type: 'GET',
            data: { type: 'category' },
            dataType: 'json',
            success: function(data) {
                var html = '';
                    if(data.length > 0) {
                    data.forEach(function(item, index) {
                        var usedHtml = item.issue_count && item.issue_count > 0 ? `<span class="badge badge-info badge-pill" title="Jumlah Issues">${item.issue_count}x</span>` : '';
                        var delCatBtn = `<button class="btn btn-danger btn-action btn-del-cat" data-id="${item.category_id}"><i class="fas fa-trash"></i></button>`;
                        if (item.issue_count && item.issue_count > 0) {
                            delCatBtn = `<button class="btn btn-danger btn-action btn-del-cat" data-id="${item.category_id}" disabled title="Tidak bisa dihapus, masih dipakai"><i class="fas fa-trash"></i></button>`;
                        }
                        // disable delete/edit buttons if user lacks permissions
                        var editBtn = `<button class="btn btn-warning btn-action btn-edit-cat" data-id="${item.category_id}" data-name="${item.category_name}"><i class="fas fa-edit"></i></button>`;
                        var deleteBtn = delCatBtn;
                        if (typeof userPermissions !== 'undefined') {
                            if (!userPermissions.CanEdit) {
                                editBtn = `<button class="btn btn-warning btn-action btn-edit-cat" data-id="${item.category_id}" data-name="${item.category_name}" disabled title="Tidak punya hak edit"><i class="fas fa-edit"></i></button>`;
                            }
                            if (!userPermissions.CanDelete) {
                                deleteBtn = `<button class="btn btn-danger btn-action btn-del-cat" data-id="${item.category_id}" disabled title="Tidak punya hak hapus"><i class="fas fa-trash"></i></button>`;
                            }
                        }
                        html += `
                            <tr class="category-row ${activeCategoryId == item.category_id ? 'bg-lightblue' : ''}" data-id="${item.category_id}" data-name="${item.category_name}" style="cursor:pointer;">
                                <td>${index + 1}</td>
                                <td>${item.category_name}</td>
                                <td>${usedHtml}</td>
                                <td>
                                    ${editBtn}
                                    ${deleteBtn}
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    html = '<tr><td colspan="4" class="text-center">Belum ada data</td></tr>';
                }
                $('#listCategories').html(html);
            }
        });
    }

    function loadSubCategories(catId) {
        $.ajax({
            url: 'data_categories.php',
            type: 'GET',
            data: { type: 'sub', category_id: catId },
            dataType: 'json',
            success: function(data) {
                var html = '';
                if(data.length > 0) {
                    data.forEach(function(item, index) {
                        var badges = '';
                        badges += item.supports_asset == 1 ? '<span class="badge badge-info ml-1" title="Butuh Asset"><i class="fas fa-desktop"></i></span>' : '';
                        badges += item.supports_client == 1 ? '<span class="badge badge-warning ml-1" title="Butuh Client"><i class="fas fa-user"></i></span>' : '';
                        var countHtml = item.issue_count && item.issue_count > 0 ? `<a href="#" class="sub-issue-count" data-subid="${item.sub_id}"><span class="badge badge-info badge-pill">${item.issue_count}x</span></a>` : '';
                        var delButton = `<button class="btn btn-danger btn-action btn-del-sub" data-id="${item.sub_id}"><i class="fas fa-trash"></i></button>`;
                        if (item.issue_count && item.issue_count > 0) {
                            // disable delete if used
                            delButton = `<button class="btn btn-danger btn-action btn-del-sub" data-id="${item.sub_id}" disabled title="Tidak bisa dihapus, masih dipakai"><i class="fas fa-trash"></i></button>`;
                        }

                        var editSubBtn = `<button class="btn btn-warning btn-action btn-edit-sub" 
                                        data-id="${item.sub_id}" 
                                        data-name="${item.sub_name}"
                                        data-asset="${item.supports_asset}"
                                        data-client="${item.supports_client}"
                                        data-asset-type="${item.asset_type}">
                                        <i class="fas fa-edit"></i>
                                    </button>`;
                        var deleteSubBtn = delButton;
                        if (typeof userPermissions !== 'undefined') {
                            if (!userPermissions.CanEdit) {
                                editSubBtn = `<button class="btn btn-warning btn-action btn-edit-sub" disabled title="Tidak punya hak edit"><i class="fas fa-edit"></i></button>`;
                            }
                            if (!userPermissions.CanDelete) {
                                deleteSubBtn = `<button class="btn btn-danger btn-action btn-del-sub" disabled title="Tidak punya hak hapus"><i class="fas fa-trash"></i></button>`;
                            }
                        }

                        html += `
                            <tr>
                                <td>${index + 1}</td>
                                <td>${item.sub_name} ${badges}</td>
                                <td>${countHtml}</td>
                                <td>
                                    ${editSubBtn}
                                    ${deleteSubBtn}
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    html = '<tr><td colspan="4" class="text-center">Belum ada sub kategori</td></tr>';
                }
                $('#listSubCategories').html(html);
            }
        });
    }

    // Click count to view issues using this subcategory
    var modalTable = null;
    var currentSubId = null;
    $(document).on('click', '.sub-issue-count', function(e) {
        e.preventDefault();
        var subId = $(this).data('subid');
        currentSubId = subId;
        $('#modalSubTitle').text('Memuat...');
        // Show modal first for better UX
        $('#modalSubIssues').modal({ show: true });

        // Initialize or reload DataTable
        if ($.fn.DataTable === undefined) {
            // Fallback: load via AJAX if DataTables not available
            $('#modalSubIssuesTable tbody').html('<tr><td colspan="5">DataTables plugin tidak tersedia.</td></tr>');
            return;
        }

        if (modalTable) {
            // Update ajax url and reload
            modalTable.ajax.url('data_categories.php?type=issues_by_sub&sub_id=' + encodeURIComponent(subId)).load();
        } else {
            modalTable = $('#modalSubIssuesTable').DataTable({
                processing: true,
                serverSide: true,
                searching: true,
                lengthChange: true,
                pageLength: 10,
                ajax: {
                    url: 'data_categories.php',
                    type: 'GET',
                    data: function(d) {
                        d.type = 'issues_by_sub';
                        d.sub_id = currentSubId;
                    }
                },
                columns: [
                    { data: null, orderable: false, searchable: false, render: function(data, type, row, meta) { return meta.row + meta.settings._iDisplayStart + 1; } },
                    { data: 'issue_name' },
                    { data: 'issue_type' },
                    { data: 'created_by' },
                    { data: 'status' }
                ],
                order: [[4, 'desc']],
                responsive: true,
                language: {
                    processing: 'Memuat...'
                }
            });
        }

        // After table loads, update title using the JSON extra fields if available
        $('#modalSubIssuesTable').one('xhr.dt', function(e, settings, json) {
            if (json && (json.sub_name || json.category_name)) {
                $('#modalSubTitle').text((json.sub_name || '') + ' (' + (json.category_name || '') + ')');
            } else {
                $('#modalSubTitle').text('Daftar Issues');
            }
        });
    });

    // Cleanup when modal hidden? keep table for reuse but if you prefer destroy uncomment below
    // $('#modalSubIssues').on('hidden.bs.modal', function(){ if(modalTable){ modalTable.clear().draw(); }});

    // Simple htmlspecialchars helper
    function htmlspecialchars(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // Click Category Row
    $(document).on('click', '.category-row', function(e) {
        if ($(e.target).closest('button').length) return; // Ignore if button clicked
        
        $('.category-row').removeClass('bg-lightblue');
        $(this).addClass('bg-lightblue');
        
        activeCategoryId = $(this).data('id');
        var name = $(this).data('name');
        
        $('#cardSub').show();
        $('#msgSelectCat').hide();
        $('#selectedCategoryName').text(name);
        loadSubCategories(activeCategoryId);
    });

    // Add Category
    $('#btnAddCategory').click(async function() {
        const { value: name } = await Swal.fire({
            title: 'Tambah Kategori',
            input: 'text',
            inputLabel: 'Nama Kategori',
            showCancelButton: true
        });

        if (name) {
            saveData('add_cat', { name: name });
        }
    });

    // Edit Category
    $(document).on('click', '.btn-edit-cat', async function() {
        var id = $(this).data('id');
        var oldName = $(this).data('name');
        
        const { value: name } = await Swal.fire({
            title: 'Edit Kategori',
            input: 'text',
            inputLabel: 'Nama Kategori',
            inputValue: oldName,
            showCancelButton: true
        });

        if (name) {
            saveData('edit_cat', { id: id, name: name });
        }
    });

    // Delete Category
    $(document).on('click', '.btn-del-cat', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Hapus Kategori?',
            text: "Semua sub-kategori juga akan terhapus!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Hapus'
        }).then((result) => {
            if (result.isConfirmed) {
                saveData('del_cat', { id: id });
            }
        });
    });

    // Add Sub Helper
    const getSubFormHtml = (name='', sAsset=0, sClient=0, aType='all') => {
        return `
            <div class="text-left">
                <div class="form-group">
                    <label>Nama Sub Kategori</label>
                    <input type="text" id="swalSubName" class="form-control" placeholder="Nama Sub Kategori" value="${name}">
                </div>
                <div class="form-check mt-3">
                    <input type="checkbox" class="form-check-input" id="swalChkAsset" ${sAsset==1?'checked':''}>
                    <label class="form-check-label" for="swalChkAsset">Perlu Input Asset</label>
                </div>
                <div class="form-group mt-1 pl-4" id="swalDivAssetType" style="display:${sAsset==1?'block':'none'};">
                    <label>Tipe Asset</label>
                    <select class="form-control" id="swalSelAssetType">
                        <option value="all" ${aType=='all'?'selected':''}>Semua Asset</option>
                        <option value="cctv" ${aType=='cctv'?'selected':''}>CCTV</option>
                        <option value="peripheral" ${aType=='peripheral'?'selected':''}>Peripheral</option>
                    </select>
                </div>
                <div class="form-check mt-2">
                    <input type="checkbox" class="form-check-input" id="swalChkClient" ${sClient==1?'checked':''}>
                    <label class="form-check-label" for="swalChkClient">Perlu Input Client</label>
                </div>
            </div>
        `;
    };

    // Add Sub
    $('#btnAddSub').click(async function() {
        if(!activeCategoryId) return;
        
        const { value: formValues } = await Swal.fire({
            title: 'Tambah Sub Kategori',
            html: getSubFormHtml(),
            focusConfirm: false,
            showCancelButton: true,
            didOpen: () => {
                const chkAsset = Swal.getPopup().querySelector('#swalChkAsset');
                const divType = Swal.getPopup().querySelector('#swalDivAssetType');
                chkAsset.addEventListener('change', () => {
                    divType.style.display = chkAsset.checked ? 'block' : 'none';
                });
            },
            preConfirm: () => {
                return {
                    name: document.getElementById('swalSubName').value,
                    supports_asset: document.getElementById('swalChkAsset').checked ? 1 : 0,
                    supports_client: document.getElementById('swalChkClient').checked ? 1 : 0,
                    asset_type: document.getElementById('swalSelAssetType').value
                }
            }
        });

        if (formValues && formValues.name) {
            saveData('add_sub', { 
                category_id: activeCategoryId, 
                name: formValues.name,
                supports_asset: formValues.supports_asset,
                supports_client: formValues.supports_client,
                asset_type: formValues.asset_type
            });
        }
    });

    // Edit Sub
    $(document).on('click', '.btn-edit-sub', async function() {
        var id = $(this).data('id');
        var oldName = $(this).data('name');
        var sAsset = $(this).data('asset');
        var sClient = $(this).data('client');
        var aType = $(this).data('asset-type');
        
        const { value: formValues } = await Swal.fire({
            title: 'Edit Sub Kategori',
            html: getSubFormHtml(oldName, sAsset, sClient, aType),
            focusConfirm: false,
            showCancelButton: true,
            didOpen: () => {
                const chkAsset = Swal.getPopup().querySelector('#swalChkAsset');
                const divType = Swal.getPopup().querySelector('#swalDivAssetType');
                chkAsset.addEventListener('change', () => {
                    divType.style.display = chkAsset.checked ? 'block' : 'none';
                });
            },
            preConfirm: () => {
                return {
                    name: document.getElementById('swalSubName').value,
                    supports_asset: document.getElementById('swalChkAsset').checked ? 1 : 0,
                    supports_client: document.getElementById('swalChkClient').checked ? 1 : 0,
                    asset_type: document.getElementById('swalSelAssetType').value
                }
            }
        });

        if (formValues && formValues.name) {
            saveData('edit_sub', { 
                id: id, 
                name: formValues.name,
                supports_asset: formValues.supports_asset,
                supports_client: formValues.supports_client,
                asset_type: formValues.asset_type
            });
        }
    });

    // Delete Sub
    $(document).on('click', '.btn-del-sub', function() {
        var id = $(this).data('id');
        Swal.fire({
             title: 'Hapus Sub Kategori?',
             text: "Yakin ingin menghapus?",
             icon: 'warning',
             showCancelButton: true,
             confirmButtonColor: '#d33',
             confirmButtonText: 'Hapus'
         }).then((result) => {
             if (result.isConfirmed) {
                 saveData('del_sub', { id: id });
             }
         });
    });

    function saveData(action, data) {
        data.action = action;
        $.ajax({
            url: 'data_categories.php',
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(resp) {
                if(resp.success) {
                    Swal.fire('Sukses', resp.message, 'success');
                    if(action.includes('_cat')) {
                        loadCategories();
                        if(action === 'del_cat') {
                            $('#cardSub').hide();
                            $('#msgSelectCat').show();
                            activeCategoryId = null;
                        } else if (action === 'edit_cat' && activeCategoryId == data.id) {
                            $('#selectedCategoryName').text(data.name);
                        }
                    } else {
                        loadSubCategories(activeCategoryId);
                    }
                } else {
                    Swal.fire('Error', resp.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Terjadi kesalahan sistem', 'error');
            }
        });
    }
});
</script>
<script>
// Expose permissions to frontend
var userPermissions = <?php echo json_encode($permissions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> || {CanView:0,CanAdd:0,CanEdit:0,CanDelete:0};

// Defensive JS: prevent actions if no permission (extra layer beyond disabled buttons)
$(function() {
    $('#btnAddCategory, #btnAddSub').on('click', function(e) {
        if (!userPermissions.CanAdd) {
            e.preventDefault();
            Swal.fire('Akses Ditolak', 'Anda tidak memiliki hak untuk menambah.', 'error');
            return false;
        }
    });

    $(document).on('click', '.btn-edit-cat, .btn-edit-sub', function(e) {
        if (!userPermissions.CanEdit) {
            e.preventDefault();
            Swal.fire('Akses Ditolak', 'Anda tidak memiliki hak untuk mengedit.', 'error');
            return false;
        }
    });

    $(document).on('click', '.btn-del-cat, .btn-del-sub', function(e) {
        // If button is disabled, don't proceed
        if ($(this).is(':disabled') || !userPermissions.CanDelete) {
            e.preventDefault();
            if (!userPermissions.CanDelete) {
                Swal.fire('Akses Ditolak', 'Anda tidak memiliki hak untuk menghapus.', 'error');
            }
            return false;
        }
    });
});
</script>
