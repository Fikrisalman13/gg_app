<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap_mikwang2.php';
require_once __DIR__ . '/pageparts.php';

$canManageMikwang2Upload = mm_can_access_upload_area('mikwang2', $currentUserId ?? '');
$allowedMikwang2UploadUserIds = mm_allowed_upload_user_ids('mikwang2');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_excel') {
    if (!$canManageMikwang2Upload) {
        mk2_flash('danger', 'User Anda tidak memiliki akses untuk upload data Mikwang2.');
        header('Location: /gg_app/pages/monitoringmesin/uploaddatamikwang2.php');
        exit;
    }

    $targetPath = null;
    try {
        if (!$pdo) {
            throw new RuntimeException('Koneksi database belum siap.');
        }

        if (!isset($_FILES['excel_file']) || ($_FILES['excel_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('File Excel wajib dipilih.');
        }

        $originalName = (string) ($_FILES['excel_file']['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension !== 'xlsx') {
            throw new RuntimeException('Format file harus .xlsx');
        }

        $uniqueCode = trim(pathinfo($originalName, PATHINFO_FILENAME));
        if ($uniqueCode === '') {
            throw new RuntimeException('Judul file Excel tidak valid untuk dijadikan kode unik.');
        }

        $uploadDir = dirname(__DIR__) . '/uploads/mikwang2';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Folder upload Mikwang2 gagal dibuat.');
        }

        $safeName = preg_replace('/[^A-Za-z0-9_\-\.]+/', '_', $originalName);
        $storedName = date('Ymd_His') . '_' . $safeName;
        $targetPath = $uploadDir . '/' . $storedName;

        if (!move_uploaded_file($_FILES['excel_file']['tmp_name'], $targetPath)) {
            throw new RuntimeException('File Excel gagal dipindahkan ke folder upload.');
        }

        $parsedWorkbook = mk2_parse_workbook($targetPath);
        $dbStoredPath = '/gg_app/pages/uploads/mikwang2/' . $storedName;

        mk2_store_upload($pdo, $parsedWorkbook, $uniqueCode, $originalName, $dbStoredPath, $currentUser);
        mk2_flash('success', "Upload berhasil. Kode unik {$uniqueCode} sudah disimpan.");
    } catch (Throwable $e) {
        if ($targetPath && is_file($targetPath)) {
            @unlink($targetPath);
        }
        mk2_flash('danger', $e->getMessage());
    }

    header('Location: /gg_app/pages/monitoringmesin/uploaddatamikwang2.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_unique_code') {
    if (!$canManageMikwang2Upload) {
        mk2_flash('danger', 'User Anda tidak memiliki akses untuk menghapus data upload Mikwang2.');
        header('Location: /gg_app/pages/monitoringmesin/uploaddatamikwang2.php');
        exit;
    }

    try {
        if (!$pdo) {
            throw new RuntimeException('Koneksi database belum siap.');
        }

        $uniqueCodeToDelete = trim((string) ($_POST['unique_code'] ?? ''));
        $deleteInfo = mk2_delete_by_unique_code($pdo, $uniqueCodeToDelete);
        mk2_flash('success', "Kode unik {$uniqueCodeToDelete} berhasil dihapus. {$deleteInfo['upload_count']} upload dibersihkan.");
    } catch (Throwable $e) {
        mk2_flash('danger', $e->getMessage());
    }

    header('Location: /gg_app/pages/monitoringmesin/uploaddatamikwang2.php');
    exit;
}

$flash = $flash ?: mk2_take_flash();
$uploads = [];
if ($pdo) {
    $uploads = mk2_get_upload_list($pdo);
}

mm_render_page_start(
    'Upload Data Mikwang2',
    'Upload Excel .xlsx untuk data Mikwang2 dan simpan ke database.',
    $themeColor,
    $flash,
    'upload'
);
?>
<div class="card mm-card">
    <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h3 class="card-title mb-0">Upload File Excel Mikwang2</h3>
    </div>
    <div class="card-body">
        <?php if ($canManageMikwang2Upload): ?>
            <form method="post" enctype="multipart/form-data" id="uploadExcelForm">
                <input type="hidden" name="action" value="upload_excel">
                <div class="form-group mb-0">
                    <label>Pilih file `.xlsx`</label>
                    <div class="mm-upload-inline">
                        <input type="file" name="excel_file" class="form-control" accept=".xlsx" required>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-file-upload mr-1"></i>Upload Sekarang
                        </button>
                    </div>
                    <small class="text-muted">Kode unik diambil dari nama file. Sistem akan menolak jika nama file sudah pernah di-upload.</small>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-warning mb-0">
                User Anda tidak terdaftar untuk upload data Mikwang2. Hubungi admin untuk menambahkan `UserId` Anda ke daftar akses.
                <?php if ($allowedMikwang2UploadUserIds !== []): ?>
                    <div class="mt-2"><small>UserId terdaftar saat ini: <?= htmlspecialchars(implode(', ', $allowedMikwang2UploadUserIds)) ?></small></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title mb-0">Riwayat Upload Terakhir</h3>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0">
                <thead>
                    <tr>
                        <th>Kode Unik</th>
                        <th>Nama File</th>
                        <th>Jumlah Row</th>
                        <th>Upload By</th>
                        <th>Uploaded At</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($uploads === []): ?>
                        <tr><td colspan="6" class="text-center text-muted">Belum ada data upload.</td></tr>
                    <?php else: ?>
                        <?php foreach ($uploads as $upload): ?>
                            <tr>
                                <td><span class="mm-code-badge"><?= htmlspecialchars($upload['UniqueCode']) ?></span></td>
                                <td><?= htmlspecialchars($upload['OriginalFileName']) ?></td>
                                <td><?= (int) $upload['TotalRows'] ?></td>
                                <td><?= htmlspecialchars((string) ($upload['UploadedBy'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string) $upload['UploadedAt']) ?></td>
                                <td class="mm-actions">
                                    <?php if ($canManageMikwang2Upload): ?>
                                        <form method="post" class="d-inline-block mm-delete-form" data-unique-code="<?= htmlspecialchars($upload['UniqueCode']) ?>">
                                            <input type="hidden" name="action" value="delete_unique_code">
                                            <input type="hidden" name="unique_code" value="<?= htmlspecialchars($upload['UniqueCode']) ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash mr-1"></i>Hapus
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">Tidak ada akses</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
mm_render_page_end();

