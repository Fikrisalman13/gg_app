<?php
declare(strict_types=1);
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../../includes/permissions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$theme = $_SESSION['Theme'] ?? 'primary';
$username = $_SESSION['UserName'] ?? 'system';

// Ambil permission menu Trustee Manager (ID: 1286)
$permissions = getPermissions($conn, $_SESSION['GroupId'], 1286);
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}

// ─── AJAX HANDLER ──────────────────────────────────────────────────────────────
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    $action = $_POST['action'] ?? '';
    header('Content-Type: application/json');

    if ($action === 'save') {
        $targetUser = trim($_POST['username'] ?? '');
        if (!$targetUser) {
            $targetUser = trim($_POST['username_edit'] ?? '');
        }

        $categoriesData = $_POST['categories'] ?? [];

        if (!$targetUser) {
            echo json_encode(['status' => 'error', 'message' => 'User wajib dipilih.']);
            exit;
        }

        sqlsrv_begin_transaction($conn);

        // Hapus semua hak akses lama untuk user ini
        $delSql = "DELETE FROM dr_trustees WHERE username = ?";
        $delStmt = sqlsrv_query($conn, $delSql, [$targetUser]);

        if ($delStmt === false) {
            sqlsrv_rollback($conn);
            echo json_encode(['status' => 'error', 'message' => 'Gagal mereset hak akses lama.']);
            exit;
        }

        $ok = true;
        foreach ($categoriesData as $key => $perms) {
            $canView = isset($perms['view']) && (int)$perms['view'] === 1 ? 1 : 0;
            if ($canView !== 1) {
                continue; // Jika View tidak dicentang, abaikan
            }

            $canEdit = isset($perms['edit']) && (int)$perms['edit'] === 1 ? 1 : 0;
            $canDelete = isset($perms['delete']) && (int)$perms['delete'] === 1 ? 1 : 0;

            // Parsing Key (format: "kategori_tipe|category_id")
            $parts = explode('|', $key);
            $kategoriTipe = $parts[0];
            $categoryId = (isset($parts[1]) && is_numeric($parts[1])) ? (int) $parts[1] : null;

            $insSql = "INSERT INTO dr_trustees (username, kategori_tipe, category_id, can_view, can_edit, can_delete, created_at, created_by) VALUES (?, ?, ?, 1, ?, ?, GETDATE(), ?)";
            $insStmt = sqlsrv_query($conn, $insSql, [$targetUser, $kategoriTipe, $categoryId, $canEdit, $canDelete, $username]);
            if ($insStmt === false) {
                $ok = false;
                break;
            }
        }

        if ($ok) {
            sqlsrv_commit($conn);
            echo json_encode(['status' => 'success', 'message' => 'Hak akses berhasil disimpan.']);
        } else {
            sqlsrv_rollback($conn);
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan beberapa konfigurasi hak akses baru.']);
        }
        exit;
    }

    if ($action === 'get_user_permissions') {
        $targetUser = trim($_POST['username'] ?? '');
        $stmt = sqlsrv_query($conn, "SELECT kategori_tipe, category_id, can_view, can_edit, can_delete FROM dr_trustees WHERE username = ?", [$targetUser]);
        $perms = [];
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $key = $row['kategori_tipe'] . '|' . ($row['category_id'] ?? '');
                $perms[$key] = [
                    'view' => (int)$row['can_view'],
                    'edit' => (int)$row['can_edit'],
                    'delete' => (int)$row['can_delete']
                ];
            }
        }
        echo json_encode(['status' => 'success', 'permissions' => $perms]);
        exit;
    }

    if ($action === 'delete_user') {
        $targetUser = trim($_POST['username'] ?? '');
        $ok = sqlsrv_query($conn, "DELETE FROM dr_trustees WHERE username = ?", [$targetUser]);
        echo json_encode($ok ? ['status' => 'success', 'message' => 'Seluruh hak akses user berhasil dihapus.'] : ['status' => 'error', 'message' => 'Gagal menghapus hak akses.']);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Action tidak dikenal.']);
    exit;
}

// ─── FETCH DATA UNTUK FORM & TABEL ────────────────────────────────────────────────
// Fetch Kategori & Sub-Kategori Dinamis (Hanya Sub-Kategori / Child yang diberi akses)
$subCategories = [];
$stmtCats = sqlsrv_query($conn, "
    SELECT c.id, c.category_name, p.category_name AS parent_name, p.color AS parent_color, p.icon AS parent_icon
    FROM dr_categories c
    JOIN dr_categories p ON c.parent_id = p.id
    WHERE c.parent_id IS NOT NULL
    ORDER BY p.category_name ASC, c.category_name ASC
");
if ($stmtCats) {
    while ($rc = sqlsrv_fetch_array($stmtCats, SQLSRV_FETCH_ASSOC)) {
        if ($rc['parent_color'] === 'user') {
            $rc['parent_color'] = $theme;
        }
        $subCategories[] = $rc;
    }
}

$groupedCats = [];
foreach ($subCategories as $sc) {
    $parent = $sc['parent_name'];
    if (!isset($groupedCats[$parent])) {
        $groupedCats[$parent] = [
            'name' => $parent,
            'icon' => $sc['parent_icon'] ?: 'fas fa-folder',
            'color' => $sc['parent_color'] ?: 'primary',
            'items' => []
        ];
    }
    $groupedCats[$parent]['items'][] = $sc;
}

// Fetch Semua User (untuk Select2)
$users = [];
$stmtUsers = sqlsrv_query($conn, "
    SELECT u.UserName, e.nama_lengkap AS Name 
    FROM dbo.SMUserMs u
    LEFT JOIN dbo.m_emp e ON u.EmpId = e.id_emp
    ORDER BY u.UserName ASC
");
if ($stmtUsers) {
    while ($ru = sqlsrv_fetch_array($stmtUsers, SQLSRV_FETCH_ASSOC)) {
        $users[] = $ru;
    }
}

// Fetch Data Trustee yang terdaftar
$trustees = [];
$sqlTrustees = "
SELECT 
    t.id, t.username, t.kategori_tipe, t.category_id, t.can_view, t.can_edit, t.can_delete, t.created_at,
    e.nama_lengkap as full_name,
    c.category_name as dyn_category_name,
    p.category_name as parent_category_name,
    COALESCE(p.icon, c.icon) as category_icon,
    COALESCE(p.color, c.color) as category_color
FROM dr_trustees t
LEFT JOIN dbo.SMUserMs u ON t.username = u.UserName
LEFT JOIN dbo.m_emp e ON u.EmpId = e.id_emp
LEFT JOIN dr_categories c ON t.category_id = c.id
LEFT JOIN dr_categories p ON c.parent_id = p.id
ORDER BY t.created_at DESC
";
$stmtT = sqlsrv_query($conn, $sqlTrustees);
if ($stmtT) {
    while ($rt = sqlsrv_fetch_array($stmtT, SQLSRV_FETCH_ASSOC)) {
        if ($rt['category_color'] === 'user') {
            $rt['category_color'] = $theme;
        }
        $trustees[] = $rt;
    }
}

// Group trustees by username untuk menyederhanakan baris (1 baris = 1 user)
$groupedTrustees = [];
foreach ($trustees as $t) {
    $uname = $t['username'];
    if (!isset($groupedTrustees[$uname])) {
        $groupedTrustees[$uname] = [
            'username' => $uname,
            'full_name' => $t['full_name'] ?: $uname,
            'created_at' => $t['created_at'],
            'categories' => []
        ];
    }
    $groupedTrustees[$uname]['categories'][] = $t;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<!-- CSS DataTables & Select2 -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">

<style>
    .select2-container .select2-selection--single {
        height: 38px !important;
    }
    #tableTrustees tbody tr {
        background-color: #ffffff !important;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Pengaturan Hak Akses (Trustee)</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active">Trustee</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="row mb-3">
                <div class="col-12">
                    <button type="button" class="btn btn-success font-weight-bold" data-toggle="modal" data-target="#modalTrustee" id="btnAddTrustee">
                        <i class="fas fa-plus mr-1"></i> Tambah Hak Akses
                    </button>
                </div>
            </div>

            <div class="card card-outline card-<?= htmlspecialchars($theme) ?>">
                <div class="card-header bg-<?= htmlspecialchars($theme) ?>">
                    <h3 class="card-title text-white font-weight-bold">Daftar Trustee</h3>
                </div>
                <div class="card-body">
                    <table id="tableTrustees" class="table table-bordered bg-white">
                        <thead>
                            <tr>
                                <th style="width: 5%">No</th>
                                <th style="width: 25%">User</th>
                                <th style="width: 45%">Kategori & Hak Akses</th>
                                <th style="width: 15%">Ditambahkan Pada</th>
                                <th style="width: 10%" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            foreach ($groupedTrustees as $uname => $gt):
                                $latestDate = '-';
                                foreach ($gt['categories'] as $c) {
                                    if ($c['created_at']) {
                                        $d = is_object($c['created_at']) ? $c['created_at']->format('Y-m-d H:i') : $c['created_at'];
                                        if ($latestDate === '-' || $d > $latestDate) {
                                            $latestDate = $d;
                                        }
                                    }
                                }
                                ?>
                                <tr>
                                    <td class="align-middle"><?= $no++ ?></td>
                                    <td class="align-middle">
                                        <strong style="font-size: 15px;"><?= htmlspecialchars($gt['full_name']) ?></strong>
                                        <br><small class="text-muted"><i class="fas fa-user-circle mr-1"></i><?= htmlspecialchars($gt['username']) ?></small>
                                    </td>
                                    <td class="align-middle">
                                        <div class="d-flex flex-wrap">
                                            <?php foreach ($gt['categories'] as $tc): 
                                                $parentName = $tc['parent_category_name'] ?: 'Umum';
                                                $childName = $tc['dyn_category_name'] ?: 'Tidak Diketahui';
                                                $icon = $tc['category_icon'] ?: 'fa-file-alt';
                                                $color = $tc['category_color'] ?: 'secondary';
                                                ?>
                                                <div class="d-inline-flex align-items-center p-1 px-2 m-1 border rounded bg-white shadow-sm" style="font-size: 13px; gap: 5px;" title="Grup: <?= htmlspecialchars($parentName) ?>">
                                                    <span class="text-<?= $color ?> font-weight-bold mr-1">
                                                        <i class="fas <?= htmlspecialchars($icon) ?> mr-1"></i><?= htmlspecialchars($parentName) ?> &gt; <?= htmlspecialchars($childName) ?>
                                                    </span>
                                                    <span class="badge badge-success px-1" title="Bisa View" style="font-size:10px;">V</span>
                                                    <?php if ($tc['can_edit']): ?>
                                                        <span class="badge badge-primary px-1" title="Bisa Edit" style="font-size:10px;">E</span>
                                                    <?php endif; ?>
                                                    <?php if ($tc['can_delete']): ?>
                                                        <span class="badge badge-danger px-1" title="Bisa Delete" style="font-size:10px;">D</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td class="align-middle"><?= $latestDate ?></td>
                                    <td class="text-center align-middle">
                                        <button class="btn btn-xs btn-primary btn-edit font-weight-bold mr-1" 
                                                data-username="<?= htmlspecialchars($gt['username']) ?>"
                                                data-fullname="<?= htmlspecialchars($gt['full_name']) ?>"
                                                title="Edit Akses Kategori"><i class="fas fa-edit mr-1"></i>Edit</button>
                                        <button class="btn btn-xs btn-danger btn-delete font-weight-bold" 
                                                data-username="<?= htmlspecialchars($gt['username']) ?>"
                                                data-fullname="<?= htmlspecialchars($gt['full_name']) ?>"
                                                title="Hapus Seluruh Akses"><i class="fas fa-trash mr-1"></i>Hapus</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- MODAL TAMBAH/EDIT TRUSTEE -->
<div class="modal fade" id="modalTrustee" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow-lg border-0">
            <form id="formTrustee">
                <input type="hidden" name="action" id="formAction" value="save">
                <input type="hidden" name="username_edit" id="inputUserEdit" disabled>

                <div class="modal-header bg-<?= htmlspecialchars($theme) ?>">
                    <h5 class="modal-title text-white font-weight-bold" id="modalTitle"><i class="fas fa-user-shield mr-1"></i> Pengaturan Hak Akses</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body bg-white">
                    <!-- Dropdown Pilih User (Hanya Tampil saat Tambah Baru) -->
                    <div class="form-group" id="groupUser">
                        <label class="font-weight-bold">Pilih User <span class="text-danger">*</span></label>
                        <select name="username" id="selectUser" class="form-control select2bs4" style="width: 100%;" required>
                            <option value="">-- Cari Username / Nama --</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= htmlspecialchars($u['UserName']) ?>"><?= htmlspecialchars($u['Name'] ?: $u['UserName']) ?> (<?= htmlspecialchars($u['UserName']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Display Text Nama User (Hanya Tampil saat Edit) -->
                    <div class="form-group d-none" id="editUserDisplay">
                        <label class="font-weight-bold text-muted">User yang Diatur</label>
                        <div class="form-control-plaintext font-weight-bold text-primary" id="textUserDisplay" style="font-size: 16px;"></div>
                    </div>

                    <!-- Tabel Grid Hak Akses Kategori (Dikelompokkan Secara Hirarkis) -->
                    <label class="font-weight-bold mt-3"><i class="fas fa-list mr-1"></i> Konfigurasi Akses Kategori Dokumen</label>
                    <div class="table-responsive">
                        <table class="table table-bordered table-valign-middle bg-white" id="tableModalPerms">
                            <thead class="bg-light">
                                <tr>
                                    <th>Kategori Dokumen</th>
                                    <th class="text-center" style="width: 15%">View</th>
                                    <th class="text-center" style="width: 15%">Edit</th>
                                    <th class="text-center" style="width: 15%">Delete</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groupedCats as $gName => $group): ?>
                                    <tr class="bg-light">
                                        <td colspan="4" class="font-weight-bold text-<?= htmlspecialchars($group['color']) ?>" style="font-size: 14px;">
                                            <i class="fas <?= htmlspecialchars($group['icon']) ?> mr-2"></i><?= htmlspecialchars($gName) ?>
                                        </td>
                                    </tr>
                                    <?php foreach ($group['items'] as $item): ?>
                                        <?php $key = "dynamic|{$item['id']}"; ?>
                                        <tr>
                                            <td class="pl-4 text-dark font-weight-normal" style="font-size: 14px;">
                                                <i class="far fa-file-alt mr-2 text-muted"></i><?= htmlspecialchars($item['category_name']) ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="custom-control custom-checkbox">
                                                    <input class="custom-control-input cb-view" type="checkbox" id="cb_view_<?= $item['id'] ?>" name="categories[<?= $key ?>][view]" value="1">
                                                    <label for="cb_view_<?= $item['id'] ?>" class="custom-control-label"></label>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <div class="custom-control custom-checkbox">
                                                    <input class="custom-control-input cb-edit" type="checkbox" id="cb_edit_<?= $item['id'] ?>" name="categories[<?= $key ?>][edit]" value="1">
                                                    <label for="cb_edit_<?= $item['id'] ?>" class="custom-control-label"></label>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <div class="custom-control custom-checkbox">
                                                    <input class="custom-control-input cb-delete" type="checkbox" id="cb_delete_<?= $item['id'] ?>" name="categories[<?= $key ?>][delete]" value="1">
                                                    <label for="cb_delete_<?= $item['id'] ?>" class="custom-control-label"></label>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success font-weight-bold" id="btnSimpan"><i class="fas fa-save mr-1"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<!-- DataTables & Select2 Plugins -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
    $(function () {
        $('#tableTrustees').DataTable({ responsive: true, autoWidth: false });
        $('.select2bs4').select2({ 
            theme: 'bootstrap4',
            dropdownParent: $('#modalTrustee')
        });

        // ─── LOGIKA CEK KONDISIONAL CHECKBOX MODAL ────────────────────────────────
        $(document).on('change', '.cb-edit, .cb-delete', function() {
            if ($(this).is(':checked')) {
                $(this).closest('tr').find('.cb-view').prop('checked', true);
            }
        });
        $(document).on('change', '.cb-view', function() {
            if (!$(this).is(':checked')) {
                $(this).closest('tr').find('.cb-edit, .cb-delete').prop('checked', false);
            }
        });

        // Handle Modal Reset untuk Tambah Hak Akses Baru
        $('#btnAddTrustee').on('click', function () {
            $('#formAction').val('save');
            $('#inputUserEdit').prop('disabled', true);
            $('#modalTitle').html('<i class="fas fa-user-shield mr-1"></i> Tambah Hak Akses');
            $('#groupUser').show();
            $('#selectUser').prop('required', true).val('').trigger('change');
            $('#editUserDisplay').addClass('d-none');
            $('#modalTrustee .custom-control-input').prop('checked', false);
        });

        // Handle Klik Tombol Edit Hak Akses
        $(document).on('click', '.btn-edit', function () {
            const username = $(this).data('username');
            const fullname = $(this).data('fullname');

            $('#formAction').val('save');
            $('#modalTitle').html('<i class="fas fa-edit mr-1"></i> Edit Hak Akses Kategori');
            $('#groupUser').hide();
            $('#selectUser').prop('required', false);
            $('#editUserDisplay').removeClass('d-none');
            $('#textUserDisplay').text(fullname + ' (' + username + ')');
            $('#inputUserEdit').prop('disabled', false).val(username);

            Swal.fire({
                title: 'Memuat data akses...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            $.post('manage_trustees.php', { action: 'get_user_permissions', username: username }, function (res) {
                Swal.close();
                $('#modalTrustee .custom-control-input').prop('checked', false);

                if (res.status === 'success') {
                    $.each(res.permissions, function(key, val) {
                        document.getElementsByName(`categories[${key}][view]`).forEach(el => el.checked = (val.view === 1));
                        document.getElementsByName(`categories[${key}][edit]`).forEach(el => el.checked = (val.edit === 1));
                        document.getElementsByName(`categories[${key}][delete]`).forEach(el => el.checked = (val.delete === 1));
                    });
                    
                    $('#modalTrustee').modal('show');
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }).fail(function() {
                Swal.close();
                Swal.fire('Error', 'Gagal memuat data dari server.', 'error');
            });
        });

        // Handle Submit Form
        $('#formTrustee').on('submit', function (e) {
            e.preventDefault();
            const btn = $('#btnSimpan');
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');

            $.post('manage_trustees.php', $(this).serialize(), function (res) {
                if (res.status === 'success') {
                    Swal.fire('Berhasil!', res.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Gagal!', res.message || 'Terjadi kesalahan.', 'error');
                    btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan');
                }
            }).fail(function () {
                Swal.fire('Error!', 'Terjadi kesalahan sistem.', 'error');
                btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan');
            });
        });

        // Handle Hapus Semua Akses User
        $(document).on('click', '.btn-delete', function () {
            const username = $(this).data('username');
            const fullname = $(this).data('fullname');

            Swal.fire({
                title: 'Hapus Semua Akses?',
                html: `Apakah Anda yakin ingin menghapus <b>seluruh</b> hak akses kategori untuk user <b>${fullname} (${username})</b>?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus Semua!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('manage_trustees.php', { action: 'delete_user', username: username }, function (res) {
                        if (res.status === 'success') {
                            Swal.fire('Terhapus!', res.message, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Gagal!', res.message, 'error');
                        }
                    });
                }
            });
        });
    });
</script>