<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/pageparts.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $redirectUrl = '/gg_app/pages/monitoringmesin/access_control.php';

    try {
        if ($action === 'add_user') {
            $userName = trim((string) ($_POST['user_name'] ?? $_POST['user_id'] ?? ''));
            mm_add_upload_access_user($userName);
            mm_flash('success', "UserName {$userName} berhasil ditambahkan ke akses upload.");
        } elseif ($action === 'remove_user') {
            $userName = trim((string) ($_POST['user_name'] ?? $_POST['user_id'] ?? ''));
            mm_remove_upload_access_user($userName);
            mm_flash('success', "UserName {$userName} berhasil dihapus dari akses upload.");
        } else {
            throw new RuntimeException('Aksi tidak dikenal.');
        }
    } catch (Throwable $e) {
        mm_flash('danger', $e->getMessage());
    }

    header('Location: ' . $redirectUrl);
    exit;
}

$flash = $flash ?: mm_take_flash();
$allowedUserNames = mm_allowed_upload_user_ids();
$availableUserNames = [];

if (isset($conn) && $conn) {
    $stmt = sqlsrv_query($conn, "SELECT UserName FROM dbo.SMUserMs WHERE ISNULL(UserName, '') <> '' ORDER BY UserName ASC");
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $userName = trim((string) ($row['UserName'] ?? ''));
            if ($userName !== '') {
                $availableUserNames[] = $userName;
            }
        }
        sqlsrv_free_stmt($stmt);
    }
}

$allowedLookup = array_map(static fn(string $value): string => strtolower(trim($value)), $allowedUserNames);
$availableUserNames = array_values(array_filter(
    array_unique($availableUserNames),
    static fn(string $userName): bool => !in_array(strtolower($userName), $allowedLookup, true)
));
sort($availableUserNames);

mm_render_page_start(
    'Access Control Upload',
    'Tambahkan atau hapus User login yang boleh mengakses semua fitur upload data.',
    $themeColor,
    $flash,
    'view'
);
?>
<div class="card mm-card mb-3">
    <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h3 class="card-title mb-0">Akses Upload Global</h3>
    </div>
    <div class="card-body">
        <div class="alert alert-info mb-3">
            Masukkan `UserName` login seperti yang tampil di pojok kanan atas, misalnya `IT1`. User yang ditambahkan di sini akan bisa mengakses Upload Data untuk `PBR`, `Mikwang2`, `Mikwang3`, `Mikwang4`, `Mikwang5`, dan `Washing03`. User yang tidak ada di daftar hanya bisa melihat View Data.
        </div>

        <form method="post" class="mb-3">
            <input type="hidden" name="action" value="add_user">
            <div class="row align-items-end">
                <div class="col-md-6">
                    <div class="form-group mb-md-0">
                        <label>Tambah User Login</label>
                        <select name="user_name" class="form-control mm-user-select" required>
                            <option value="">Pilih UserName yang belum ditambahkan</option>
                            <?php foreach ($availableUserNames as $userNameOption): ?>
                                <option value="<?= htmlspecialchars($userNameOption) ?>"><?= htmlspecialchars($userNameOption) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-user-plus mr-1"></i>Tambah Akses
                    </button>
                </div>
            </div>
        </form>

        <?php if ($availableUserNames === []): ?>
            <div class="alert alert-secondary mb-3">
                Semua UserName yang tersedia sudah ada di daftar akses, atau data user login belum dapat diambil dari database.
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width: 80px;">No</th>
                        <th>User Login</th>
                        <th style="width: 140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($allowedUserNames === []): ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted">Belum ada UserName yang diberi akses upload.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($allowedUserNames as $index => $userName): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><span class="mm-code-badge"><?= htmlspecialchars($userName) ?></span></td>
                                <td class="mm-actions">
                                    <form method="post" class="d-inline-block mm-delete-form" data-unique-code="<?= htmlspecialchars($userName) ?>" data-confirm-message="Hapus akses UserName <?= htmlspecialchars($userName) ?>?">
                                        <input type="hidden" name="action" value="remove_user">
                                        <input type="hidden" name="user_name" value="<?= htmlspecialchars($userName) ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash mr-1"></i>Hapus
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && jQuery.fn.select2) {
        jQuery('.mm-user-select').select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: 'Pilih UserName yang belum ditambahkan',
            allowClear: true
        });
    }
});
</script>
<?php
mm_render_page_end();
