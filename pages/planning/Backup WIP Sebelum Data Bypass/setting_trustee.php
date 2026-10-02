<?php
session_start();
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

// Fetch Users for Dropdown
$users = [];
$sqlU = "
SELECT u.UserName, m.nama_lengkap, b.bagian
FROM SMUserMs u
INNER JOIN m_emp m ON u.EmpId = m.id_emp
LEFT JOIN m_bag b ON m.id_bag = b.id_bag
WHERE ISNULL(m.aktif, 0) = 1
ORDER BY m.nama_lengkap
";
$stmtU = sqlsrv_query($conn, $sqlU);
if ($stmtU) {
    while ($row = sqlsrv_fetch_array($stmtU, SQLSRV_FETCH_ASSOC)) {
        if (!empty($row['UserName'])) {
            $users[] = [
                'UserName' => $row['UserName'],
                'FullName' => $row['nama_lengkap'],
                'Bagian' => $row['bagian'] ?? 'Belum Diatur'
            ];
        }
    }
}

// Fetch Existing Groups for Dropdown
$groups = [];
$sqlG = "SELECT DISTINCT group_name FROM planning_trustee_group ORDER BY group_name";
$stmtG = sqlsrv_query($conn, $sqlG);
if ($stmtG) {
    while ($row = sqlsrv_fetch_array($stmtG, SQLSRV_FETCH_ASSOC)) {
        if (!empty($row['group_name'])) {
            $groups[] = $row['group_name'];
        }
    }
}

// Fetch Routings for Dropdown (Trustee Version)
$routings = [];
try {
    $routingSql = "SELECT rtgmsid, rtgcode, rtgname FROM pdrtgms WHERE rtgname IS NOT NULL ORDER BY rtgname";
    $stmtRtg = $conn3->query($routingSql);
    $routings = $stmtRtg->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // ignore
}
?>

<!-- Fonts -->
<link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@500;600;700;800&display=swap"
    rel="stylesheet">
<!-- Select2 -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
<link rel="stylesheet"
    href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
<!-- SweetAlert2 -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
<!-- DataTables -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">

<style>
    body,
    .content-wrapper {
        font-family: 'Inter', sans-serif !important;
        background-color: #f8fafc;
    }

    .outfit-font {
        font-family: 'Outfit', sans-serif !important;
    }

    .card {
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        border: 1px solid #f1f5f9;
    }

    .card-header {
        border-bottom: 1px solid #f1f5f9;
        background: white;
        border-radius: 12px 12px 0 0 !important;
    }

    .btn-save {
        min-width: 120px;
        transition: all 0.2s;
    }

    .btn-save:disabled {
        cursor: not-allowed;
        opacity: 0.7;
    }

    .nav-pills .nav-link {
        border-radius: 50px;
        font-weight: 600;
        color: #475569;
        padding: 10px 24px;
    }

    .nav-pills .nav-link.active {
        background-color: #3b82f6;
        color: white;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    /* Fix Select2 Multiple Selection Tags Visibility */
    .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice {
        background-color: #3b82f6 !important;
        border: 1px solid #2563eb !important;
        color: #fff !important;
        border-radius: 4px;
        padding: 0 5px;
    }

    .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice__remove {
        color: rgba(255, 255, 255, 0.8) !important;
    }

    .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice__remove:hover {
        color: #fff !important;
    }

    /* Optimize Select2 Selection Height (Scrollable) */
    .select2-container .select2-selection--multiple {
        max-height: 100px !important;
        overflow-y: auto !important;
        border-radius: 8px !important;
    }

    /* Fix Highlighted Results & Selected Item Background */
    .select2-container--bootstrap4 .select2-results__option--highlighted[aria-selected] {
        background-color: #3b82f6 !important;
        color: #fff !important;
    }

    .select2-container--bootstrap4 .select2-results__option[aria-selected=true] {
        background-color: #f1f5f9 !important;
        color: #3b82f6 !important;
    }
</style>

<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row align-items-center mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0 outfit-font" style="font-weight: 700; color: #1e293b;">
                            Pengaturan <span style="color: #3b82f6;">Grup Trustee</span>
                        </h1>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">

                <!-- Tab Navigations -->
                <ul class="nav nav-pills mb-4" id="pills-tab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="pills-group-tab" data-toggle="pill" href="#pills-group"
                            role="tab">
                            <i class="fas fa-layer-group mr-2"></i> Manajemen Grup Trustee
                        </a>
                    </li>
                    <li class="nav-item ml-2">
                        <a class="nav-link" id="pills-user-tab" data-toggle="pill" href="#pills-user" role="tab">
                            <i class="fas fa-users-cog mr-2"></i> User Grup Trustee
                        </a>
                    </li>
                </ul>

                <div class="tab-content" id="pills-tabContent">

                    <!-- TAB 1: Manajemen GRUP -->
                    <div class="tab-pane fade show active" id="pills-group" role="tabpanel">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header pb-2">
                                        <h3 class="card-title font-weight-bold" style="color: #475569;">
                                            <i class="fas fa-plus-circle text-primary mr-2"></i> Buat Kategori Grup
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <form id="formGrup">
                                            <div class="form-group">
                                                <label for="group_name">Sebutkan Nama Grup <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="group_name"
                                                    name="group_name" placeholder="Misal: GRUP OBAT LAB" required
                                                    style="border-radius: 8px;">
                                                <small class="text-muted mt-1 d-block">Gunakan huruf besar untuk
                                                    kerapihan.</small>
                                            </div>
                                            <div class="form-group">
                                                <label for="rtg_data">Pilih Komponen Routing <span
                                                        class="text-danger">*</span></label>
                                                <select class="select2bs4" id="rtg_data" name="rtg_data[]"
                                                    multiple="multiple" data-placeholder="-- Pilih Routing --"
                                                    style="width: 100%;" required>
                                                    <?php foreach ($routings as $r): ?>
                                                        <option
                                                            value="<?= $r['rtgmsid'] ?>|<?= htmlspecialchars($r['rtgcode']) ?>|<?= htmlspecialchars($r['rtgname']) ?>">
                                                            <?= htmlspecialchars($r['rtgcode'] . ' - ' . $r['rtgname']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <small class="text-muted mt-1 d-block">Pilihan ganda: Bisa cari dan klik
                                                    banyak routing sekaligus.</small>
                                            </div>
                                            <button type="submit" id="btnSimpanGrup"
                                                class="btn btn-primary d-block w-100 font-weight-bold mt-4 btn-save"
                                                style="border-radius: 8px;">
                                                <i class="fas fa-save mr-1"></i> Simpan ke Grup
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-body p-0">
                                        <div class="table-responsive p-3">
                                            <table id="tableGrup" class="table table-hover table-sm w-100">
                                                <thead class="bg-light">
                                                    <tr>
                                                        <th>Nama Grup</th>
                                                        <th>Memuat Routing</th>
                                                        <th>Didaftarkan Oleh</th>
                                                        <th>Waktu</th>
                                                        <th class="text-center" width="80px">Aksi</th>
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

                    <!-- TAB 2: Penugasan USER -->
                    <div class="tab-pane fade" id="pills-user" role="tabpanel">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header pb-2">
                                        <h3 class="card-title font-weight-bold" style="color: #475569;">
                                            <i class="fas fa-user-plus text-primary mr-2"></i> Tugaskan User ke Grup
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <form id="formUser">
                                            <div class="form-group">
                                                <label for="assign_group">Pilih Grup Trustee <span
                                                        class="text-danger">*</span></label>
                                                <select class="form-control select2bs4" id="assign_group"
                                                    name="group_name" required>
                                                    <option value="">-- Pilih Grup --</option>
                                                    <?php foreach ($groups as $g): ?>
                                                        <option value="<?= htmlspecialchars($g) ?>">
                                                            <?= htmlspecialchars($g) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <small class="text-muted mt-1 d-block text-warning"><i
                                                        class="fas fa-info-circle"></i> Jika grup tujuan belum ada, buat
                                                    di Tab sebelah kiri lalu Refresh halaman (F5).</small>
                                            </div>
                                            <div class="form-group">
                                                <label for="username">Pilih Pengguna <span
                                                        class="text-danger">*</span></label>
                                                <select class="form-control select2bs4" id="username" name="username"
                                                    required>
                                                    <option value="">-- Pilih User --</option>
                                                    <?php foreach ($users as $u): ?>
                                                        <option value="<?= htmlspecialchars($u['UserName']) ?>">
                                                            <?= htmlspecialchars($u['FullName'] . ' - ' . $u['Bagian']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <button type="submit" id="btnSimpanUser"
                                                class="btn btn-warning d-block w-100 font-weight-bold mt-4 btn-save text-dark"
                                                style="border-radius: 8px;">
                                                <i class="fas fa-link mr-1"></i> Hubungkan User
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-body p-0">
                                        <div class="table-responsive p-3">
                                            <table id="tableUser" class="table table-hover table-sm w-100">
                                                <thead class="bg-light">
                                                    <tr>
                                                        <th>Nama Pengguna</th>
                                                        <th>Memegang Akses (Grup)</th>
                                                        <th>Didaftarkan Oleh</th>
                                                        <th>Waktu</th>
                                                        <th class="text-center" width="80px">Aksi</th>
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

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detail Routing -->
<div class="modal fade" id="modalViewRouting" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none; overflow: hidden;">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <h5 class="modal-title outfit-font font-weight-bold" id="modalLabel"><i
                        class="fas fa-layer-group mr-2"></i> Detail Isi <span id="modalGroupName"
                        class="text-warning"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body bg-light p-4">
                <ul class="list-group list-group-flush rounded shadow-sm" id="modalRoutingList"
                    style="border: 1px solid #e2e8f0;">
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit Group Name -->
<div class="modal fade" id="modalEditGroup" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none; overflow: hidden;">
            <div class="modal-header bg-info text-white border-0 py-3">
                <h5 class="modal-title outfit-font font-weight-bold"><i class="fas fa-edit mr-2"></i> Ubah Nama Grup
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formEditGroup">
                <div class="modal-body bg-light p-4">
                    <input type="hidden" name="old_group_name" id="old_group_name">
                    <div class="form-group">
                        <label>Nama Grup Sekarang</label>
                        <input type="text" class="form-control" id="display_old_group" readonly
                            style="background-color: #e2e8f0; border: 1px solid #cbd5e1;">
                    </div>
                    <div class="form-group">
                        <label>Nama Grup Baru <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="new_group_name" id="new_group_name"
                            placeholder="Misal: GRUP BARU" required style="border-radius: 8px;">
                    </div>
                    <div class="form-group">
                        <label>Edit Komponen Routing <span class="text-danger">*</span></label>
                        <select class="form-control select2bs4" id="edit_rtg_data" name="rtg_data[]" multiple="multiple"
                            data-placeholder="-- Pilih Routing --" style="width: 100%;" required>
                            <?php foreach ($routings as $r): ?>
                                <option
                                    value="<?= $r['rtgmsid'] ?>|<?= htmlspecialchars($r['rtgcode']) ?>|<?= htmlspecialchars($r['rtgname']) ?>">
                                    <?= htmlspecialchars($r['rtgcode'] . ' - ' . $r['rtgname']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-white">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" id="btnUpdateGroupName" class="btn btn-info font-weight-bold px-4">
                        <i class="fas fa-save mr-1"></i> Simpan Nama
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit User Group -->
<div class="modal fade" id="modalEditUser" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none; overflow: hidden;">
            <div class="modal-header bg-info text-white border-0 py-3">
                <h5 class="modal-title outfit-font font-weight-bold"><i class="fas fa-user-edit mr-2"></i> Edit
                    Penugasan Grup</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formEditUser">
                <div class="modal-body bg-light p-4">
                    <input type="hidden" name="id" id="edit_user_id">
                    <div class="form-group">
                        <label>Nama Pengguna</label>
                        <input type="text" class="form-control" id="edit_fullname" readonly
                            style="background-color: #e2e8f0; border: 1px solid #cbd5e1;">
                    </div>
                    <div class="form-group">
                        <label>Pindah ke Grup <span class="text-danger">*</span></label>
                        <select class="form-control select2bs4" id="edit_assign_group" name="group_name" required
                            style="width: 100%;">
                            <option value="">-- Pilih Grup Akses --</option>
                            <?php foreach ($groups as $g): ?>
                                <option value="<?= htmlspecialchars($g) ?>"><?= htmlspecialchars($g) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-white">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" id="btnUpdateUser" class="btn btn-info font-weight-bold px-4">
                        <i class="fas fa-save mr-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- Select2 -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>
<!-- SweetAlert2 -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.min.js"></script>
<!-- DataTables -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>

<script>
    $(document).ready(function () {
        $('.select2bs4').select2({ theme: 'bootstrap4' });

        // Table Tab 1
        var tableGrup = $('#tableGrup').DataTable({
            serverSide: true,
            processing: true,
            ajax: { url: 'get_setting_trustee_group.php', type: 'POST' },
            columns: [
                { data: 'group_name', className: 'font-weight-bold align-middle text-primary' },
                { data: 'rtg_count', className: 'align-middle font-weight-bold text-center' },
                { data: 'created_by', className: 'align-middle text-muted small' },
                { data: 'created_at', className: 'align-middle text-muted small' },
                { data: 'actions', className: 'text-center align-middle', orderable: false }
            ],
            order: [[0, 'asc']],
            language: { url: "//cdn.datatables.net/plug-ins/1.10.25/i18n/Indonesian.json" }
        });

        // Table Tab 2
        var tableUser = $('#tableUser').DataTable({
            serverSide: true,
            processing: true,
            ajax: { url: 'get_setting_trustee_user.php', type: 'POST' },
            columns: [
                { data: 'username', className: 'font-weight-bold align-middle text-danger' },
                { data: 'group_name', className: 'align-middle text-primary font-weight-bold' },
                { data: 'created_by', className: 'align-middle text-muted small' },
                { data: 'created_at', className: 'align-middle text-muted small' },
                { data: 'actions', className: 'text-center align-middle', orderable: false }
            ],
            order: [[3, 'desc']],
            language: { url: "//cdn.datatables.net/plug-ins/1.10.25/i18n/Indonesian.json" }
        });

        // Simpan Tab 1
        $('#formGrup').on('submit', function (e) {
            e.preventDefault();
            var btn = $('#btnSimpanGrup');
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');

            $.ajax({
                url: 'save_setting_trustee_group.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (res) {
                    if (res.status === 'success') {
                        Swal.fire({ icon: 'success', title: 'Berhasil', text: res.message });
                        $('#rtg_data').val(null).trigger('change');
                        $('#group_name').val('');
                        tableGrup.ajax.reload();
                        location.reload(); // Refresh dropdowns
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal', text: res.message });
                    }
                },
                complete: function () { btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan ke Grup'); }
            });
        });

        // Simpan Tab 2
        $('#formUser').on('submit', function (e) {
            e.preventDefault();
            var btn = $('#btnSimpanUser');
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyambungkan...');

            $.ajax({
                url: 'save_setting_trustee_user.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (res) {
                    if (res.status === 'success') {
                        Swal.fire({ icon: 'success', title: 'Berhasil', text: res.message });
                        $('#username').val('').trigger('change');
                        tableUser.ajax.reload();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal', text: res.message });
                    }
                },
                complete: function () { btn.prop('disabled', false).html('<i class="fas fa-link mr-1"></i> Hubungkan User'); }
            });
        });

        // Handle Tinjauan Grup (Modal)
        $(document).on('click', '.btn-view-group', function () {
            var groupName = $(this).data('group');
            var routings = $(this).data('routings');

            $('#modalGroupName').text(groupName);
            var htmlList = '';
            if (routings.length > 0) {
                routings.forEach(function (r) {
                    htmlList += `
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <span class="font-weight-bold" style="color: #334155;">${r.rtgcode}</span> - ${r.rtgname}
                    </div>
                    <button class="btn btn-sm btn-outline-danger btn-delete-routing" data-id="${r.id}" data-name="${r.rtgname}"><i class="fas fa-times"></i></button>
                </li>
                `;
                });
            }
            $('#modalRoutingList').html(htmlList);
            $('#modalViewRouting').modal('show');
        });

        // Delete Routing Specific
        $(document).on('click', '.btn-delete-routing', function () {
            var id = $(this).data('id');
            $.post('delete_setting_trustee_group.php', { id: id }, function (r) {
                if (r.status === 'success') {
                    tableGrup.ajax.reload();
                    $('#modalViewRouting').modal('hide');
                }
            }, 'json');
        });

        // Delete Full Group
        $(document).on('click', '.btn-delete-fullgroup', function () {
            var groupName = $(this).data('group');
            Swal.fire({
                title: 'Hapus Grup?', text: "Seluruh mapping rute dan user di grup ini akan dihapus!", icon: 'warning', showCancelButton: true
            }).then((res) => {
                if (res.isConfirmed) {
                    $.post('delete_setting_trustee_group.php', { group_name: groupName }, function (r) {
                        if (r.status === 'success') { tableGrup.ajax.reload(); tableUser.ajax.reload(); }
                    }, 'json');
                }
            });
        });

        // Delete User Mapping
        $(document).on('click', '.btn-delete-user', function () {
            var id = $(this).data('id');
            $.post('delete_setting_trustee_user.php', { id: id }, function (r) {
                if (r.status === 'success') { tableUser.ajax.reload(); }
            }, 'json');
        });

        // Handle Edit Group
        $(document).on('click', '.btn-edit-group', function () {
            var group = $(this).data('group');
            var routings = $(this).data('routings');
            $('#old_group_name').val(group);
            $('#display_old_group').val(group);
            $('#new_group_name').val(group);

            var selected = [];
            routings.forEach(function (r) {
                selected.push(r.rtgmsid + '|' + r.rtgcode + '|' + r.rtgname);
            });
            $('#edit_rtg_data').val(selected).trigger('change');
            $('#modalEditGroup').modal('show');
        });

        $('#formEditGroup').on('submit', function (e) {
            e.preventDefault();
            $.ajax({
                url: 'update_setting_trustee_group.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (res) {
                    if (res.status === 'success') {
                        $('#modalEditGroup').modal('hide');
                        tableGrup.ajax.reload();
                        location.reload();
                    }
                }
            });
        });

        // Edit User
        $(document).on('click', '.btn-edit-user', function () {
            var id = $(this).data('id');
            var fullname = $(this).data('fullname');
            var group = $(this).data('group');
            $('#edit_user_id').val(id);
            $('#edit_fullname').val(fullname);
            $('#edit_assign_group').val(group).trigger('change');
            $('#modalEditUser').modal('show');
        });

        $('#formEditUser').on('submit', function (e) {
            e.preventDefault();
            $.ajax({
                url: 'update_setting_trustee_user.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (res) {
                    if (res.status === 'success') {
                        $('#modalEditUser').modal('hide');
                        tableUser.ajax.reload();
                    }
                }
            });
        });
    });
</script>