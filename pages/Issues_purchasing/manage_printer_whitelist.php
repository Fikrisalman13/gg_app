<?php
session_start();

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/printer_whitelist_helpers.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
$lightThemeOptions = ['warning', 'light', 'lime', 'white'];
$isLightTheme = in_array($themeColor, $lightThemeOptions, true);
$headerTextClass = $isLightTheme ? 'text-dark' : 'text-white';
$pageError = '';
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editData = null;

function checkPermissions($conn, $groupId, $menuId)
{
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

if (!ensurePrinterWhitelistTable($conn)) {
    $pageError = 'Gagal menyiapkan tabel whitelist printer.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pageError === '') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $whitelistId = (int) ($_POST['id'] ?? 0);
        $idEmp = (int) ($_POST['id_emp'] ?? 0);
        $idAsset = (int) ($_POST['id_asset'] ?? 0);
        $jenisTinta = trim((string) ($_POST['jenis_tinta'] ?? ''));

        if ($idEmp <= 0 || $idAsset <= 0 || !in_array($jenisTinta, ['Tinta Cair', 'Tinta Serbuk'], true)) {
            $_SESSION['error'] = 'Data whitelist printer belum lengkap.';
        } else {
            $checkSql = "SELECT TOP 1
                            pw.id,
                            ISNULL(emp_current.nama_lengkap, emp_whitelist.nama_lengkap) AS nama_lengkap
                         FROM dbo.printer_user_whitelist pw
                         INNER JOIN dbo.m_asset a ON pw.id_asset = a.id_asset
                         LEFT JOIN dbo.m_emp emp_whitelist ON pw.id_emp = emp_whitelist.id_emp
                         LEFT JOIN dbo.m_emp emp_current ON a.id_emp = emp_current.id_emp
                         WHERE pw.id_asset = ?
                           AND pw.id <> ?
                           AND pw.is_active = 1";
            $checkStmt = sqlsrv_query($conn, $checkSql, [$idAsset, $whitelistId]);
            $existingId = 0;
            $existingName = '';
            if ($checkStmt !== false && $existing = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
                $existingId = (int) ($existing['id'] ?? 0);
                $existingName = trim((string) ($existing['nama_lengkap'] ?? ''));
            }
            if ($checkStmt !== false) {
                sqlsrv_free_stmt($checkStmt);
            }

            if ($existingId > 0) {
                $_SESSION['error'] = $existingName !== ''
                    ? 'Asset printer tersebut sudah terdaftar pada whitelist aktif atas nama ' . $existingName . '.'
                    : 'Asset printer tersebut sudah terdaftar pada whitelist aktif.';
                header('Location: /gg_app/pages/issues/manage_printer_whitelist.php');
                exit;
            }

            if ($whitelistId > 0) {
                $saveSql = "UPDATE dbo.printer_user_whitelist
                            SET id_emp = ?, id_asset = ?, jenis_tinta = ?, is_active = 1, updated_at = GETDATE(), updated_by = ?
                            WHERE id = ?";
                $saveParams = [$idEmp, $idAsset, $jenisTinta, $_SESSION['UserName'], $whitelistId];
            } else {
                $saveSql = "INSERT INTO dbo.printer_user_whitelist (id_emp, id_asset, jenis_tinta, is_active, created_by)
                            VALUES (?, ?, ?, 1, ?)";
                $saveParams = [$idEmp, $idAsset, $jenisTinta, $_SESSION['UserName']];
            }

            $saveStmt = sqlsrv_query($conn, $saveSql, $saveParams);
            if ($saveStmt === false) {
                $_SESSION['error'] = 'Gagal menyimpan whitelist printer.';
            } else {
                sqlsrv_free_stmt($saveStmt);
                $_SESSION['success'] = $whitelistId > 0 ? 'Whitelist printer berhasil diperbarui.' : 'Whitelist printer berhasil disimpan.';
            }
        }

        header('Location: /gg_app/pages/issues/manage_printer_whitelist.php');
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $deleteSql = "UPDATE dbo.printer_user_whitelist
                          SET is_active = 0, updated_at = GETDATE(), updated_by = ?
                          WHERE id = ?";
            $deleteStmt = sqlsrv_query($conn, $deleteSql, [$_SESSION['UserName'], $id]);
            if ($deleteStmt === false) {
                $_SESSION['error'] = 'Gagal menghapus whitelist printer.';
            } else {
                sqlsrv_free_stmt($deleteStmt);
                $_SESSION['success'] = 'Whitelist printer berhasil dihapus.';
            }
        }

        header('Location: /gg_app/pages/issues/manage_printer_whitelist.php');
        exit;
    }
}

if ($editId > 0 && $pageError === '') {
    $editSql = "SELECT TOP 1 id, id_emp, id_asset, jenis_tinta
                FROM dbo.printer_user_whitelist
                WHERE id = ? AND is_active = 1";
    $editStmt = sqlsrv_query($conn, $editSql, [$editId]);
    if ($editStmt !== false) {
        $editData = sqlsrv_fetch_array($editStmt, SQLSRV_FETCH_ASSOC) ?: null;
        sqlsrv_free_stmt($editStmt);
    }
}

$employeeOptions = [];
if ($pageError === '') {
    $empSql = "SELECT DISTINCT
                    e.id_emp,
                    e.nama_lengkap
               FROM dbo.m_asset a
               INNER JOIN dbo.m_emp e ON a.id_emp = e.id_emp
               WHERE a.id_kode = ?
                 AND a.id_status IN (1, 6)
               ORDER BY e.nama_lengkap ASC";
    $empStmt = sqlsrv_query($conn, $empSql, [4]);
    if ($empStmt !== false) {
        while ($row = sqlsrv_fetch_array($empStmt, SQLSRV_FETCH_ASSOC)) {
            $employeeOptions[] = $row;
        }
        sqlsrv_free_stmt($empStmt);
    }
}

$whitelistRows = [];
if ($pageError === '') {
    $listSql = "SELECT
                    pw.id,
                    pw.id_asset,
                    pw.jenis_tinta,
                    ISNULL(emp_current.nama_lengkap, emp_whitelist.nama_lengkap) AS nama_lengkap,
                    a.kode_asset_seq,
                    tp.nama_tipe,
                    pw.created_at
                FROM dbo.printer_user_whitelist pw
                INNER JOIN dbo.m_asset a ON pw.id_asset = a.id_asset
                LEFT JOIN dbo.m_emp emp_whitelist ON pw.id_emp = emp_whitelist.id_emp
                LEFT JOIN dbo.m_emp emp_current ON a.id_emp = emp_current.id_emp
                LEFT JOIN dbo.m_tipe tp ON a.id_tipe = tp.id_tipe
                WHERE pw.is_active = 1
                  AND a.id_status IN (1, 6)
                ORDER BY ISNULL(emp_current.nama_lengkap, emp_whitelist.nama_lengkap) ASC, a.kode_asset_seq ASC";
    $listStmt = sqlsrv_query($conn, $listSql);
    if ($listStmt !== false) {
        while ($row = sqlsrv_fetch_array($listStmt, SQLSRV_FETCH_ASSOC)) {
            $whitelistRows[] = $row;
        }
        sqlsrv_free_stmt($listStmt);
    }
}

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
?>

<div class="wrapper">
    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <h1>Add User Printer</h1>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <div class="card card-primary">
                    <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> <?php echo $headerTextClass; ?>">
                        <h3 class="card-title"><i class="fas fa-user-plus mr-1"></i>Whitelist User Printer</h3>
                        <a href="/gg_app/pages/issues/monitoring.php" class="btn btn-secondary btn-sm float-right">
                            <i class="fas fa-arrow-left"></i> Kembali Monitoring
                        </a>
                    </div>

                    <div class="card-body">
                        <?php if ($pageError !== ''): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($pageError); ?></div>
                        <?php endif; ?>

                        <?php if (!empty($_SESSION['error'])): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
                        <?php endif; ?>

                        <?php if (!empty($_SESSION['success'])): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
                        <?php endif; ?>

                        <form method="post" class="mb-4">
                            <input type="hidden" name="action" value="save">
                            <input type="hidden" name="id" value="<?php echo (int) ($editData['id'] ?? 0); ?>">
                            <div class="row">
                                <div class="col-md-4">
                                    <label for="id_emp">Nama Lengkap</label>
                                    <select name="id_emp" id="id_emp" class="form-control" required>
                                        <option value="">Pilih nama lengkap</option>
                                        <?php foreach ($employeeOptions as $emp): ?>
                                            <option value="<?php echo (int) $emp['id_emp']; ?>" <?php echo ((int) ($editData['id_emp'] ?? 0) === (int) $emp['id_emp']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars((string) ($emp['nama_lengkap'] ?? '-')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="id_asset">Kode Asset - Tipe</label>
                                    <select name="id_asset" id="id_asset" class="form-control" required disabled>
                                        <option value="">Pilih nama lengkap terlebih dahulu</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="jenis_tinta">Jenis Tinta</label>
                                    <select name="jenis_tinta" id="jenis_tinta" class="form-control" required>
                                        <option value="">Pilih jenis tinta</option>
                                        <option value="Tinta Cair" <?php echo (($editData['jenis_tinta'] ?? '') === 'Tinta Cair') ? 'selected' : ''; ?>>Tinta Cair</option>
                                        <option value="Tinta Serbuk" <?php echo (($editData['jenis_tinta'] ?? '') === 'Tinta Serbuk') ? 'selected' : ''; ?>>Tinta Serbuk</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> <?php echo $editData ? 'Update Whitelist' : 'Simpan Whitelist'; ?>
                                </button>
                                <?php if ($editData): ?>
                                    <a href="/gg_app/pages/issues/manage_printer_whitelist.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Batal Edit
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table id="whitelistTable" class="table table-hover table-sm nowrap" style="width:100%">
                                <thead class="thead-light">
                                    <tr>
                                        <th>No</th>
                                        <th>Nama Lengkap</th>
                                        <th>Kode Asset</th>
                                        <th>Tipe</th>
                                        <th>Jenis Tinta</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($whitelistRows as $index => $row): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars((string) ($row['nama_lengkap'] ?? '-')); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($row['kode_asset_seq'] ?? '-')); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($row['nama_tipe'] ?? '-')); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($row['jenis_tinta'] ?? '-')); ?></td>
                                            <td>
                                                <form method="post" onsubmit="return confirm('Hapus whitelist printer ini?');" class="d-inline">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">
                                                        <i class="fas fa-trash"></i> Hapus
                                                    </button>
                                                </form>
                                                <a href="/gg_app/pages/issues/manage_printer_whitelist.php?edit=<?php echo (int) $row['id']; ?>" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>

<script>
$(function () {
    var editAssetId = <?php echo json_encode((string) ($editData['id_asset'] ?? '')); ?>;

    $('#whitelistTable').DataTable({
        responsive: true,
        autoWidth: false,
        pageLength: 25,
        order: [[1, 'asc'], [2, 'asc']],
        language: {
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ data',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            zeroRecords: 'Data tidak ditemukan',
            paginate: {
                first: 'Awal',
                last: 'Akhir',
                next: 'Berikutnya',
                previous: 'Sebelumnya'
            }
        }
    });

    $('#id_emp').on('change', function () {
        var idEmp = $(this).val();
        var $assetSelect = $('#id_asset');

        $assetSelect.prop('disabled', true).html('<option value="">Memuat asset printer...</option>');

        if (!idEmp) {
            $assetSelect.html('<option value="">Pilih nama lengkap terlebih dahulu</option>').prop('disabled', true);
            return;
        }

        $.getJSON('/gg_app/pages/issues/get_printer_assets_by_emp.php', { id_emp: idEmp })
            .done(function (response) {
                if (!response.success || !Array.isArray(response.data)) {
                    $assetSelect.html('<option value="">Data asset printer tidak tersedia</option>').prop('disabled', true);
                    return;
                }

                var options = '<option value="">Pilih kode asset - tipe</option>';
                response.data.forEach(function (item) {
                    var suffix = item.is_whitelisted && item.jenis_tinta ? ' [' + item.jenis_tinta + ']' : '';
                    var selected = editAssetId && String(editAssetId) === String(item.id_asset) ? ' selected' : '';
                    options += '<option value="' + item.id_asset + '"' + selected + '>' + item.label + suffix + '</option>';
                });

                $assetSelect.html(options).prop('disabled', false);
            })
            .fail(function () {
                $assetSelect.html('<option value="">Gagal mengambil asset printer</option>').prop('disabled', true);
            });
    });

    if ($('#id_emp').val()) {
        $('#id_emp').trigger('change');
    }
});
</script>
