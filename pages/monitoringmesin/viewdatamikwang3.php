<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap_mikwang3.php';
require_once __DIR__ . '/pageparts.php';

$exportAction = $_GET['action'] ?? '';
$startDateRaw = trim((string) ($_GET['start_date'] ?? ''));
$endDateRaw = trim((string) ($_GET['end_date'] ?? ''));
$startDate = mk3_normalize_filter_datetime($startDateRaw, false);
$endDate = mk3_normalize_filter_datetime($endDateRaw, true);
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$canManageMikwang3Upload = mm_can_access_upload_area('mikwang3', $currentUserId ?? '');
$allowedMikwang3UploadUserIds = mm_allowed_upload_user_ids('mikwang3');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_excel') {
    if (!$canManageMikwang3Upload) {
        mk3_flash('danger', 'User Anda tidak memiliki akses untuk upload data Mikwang3.');
        header('Location: /gg_app/pages/monitoringmesin/viewdatamikwang3.php');
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

        $uploadDir = dirname(__DIR__) . '/uploads/mikwang3';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Folder upload Mikwang3 gagal dibuat.');
        }

        $safeName = preg_replace('/[^A-Za-z0-9_\-\.]+/', '_', $originalName);
        $storedName = date('Ymd_His') . '_' . $safeName;
        $targetPath = $uploadDir . '/' . $storedName;

        if (!move_uploaded_file($_FILES['excel_file']['tmp_name'], $targetPath)) {
            throw new RuntimeException('File Excel gagal dipindahkan ke folder upload.');
        }

        $parsedWorkbook = mk3_parse_workbook($targetPath);
        $dbStoredPath = '/gg_app/pages/uploads/mikwang3/' . $storedName;

        mk3_store_upload($pdo, $parsedWorkbook, $uniqueCode, $originalName, $dbStoredPath, $currentUser);
        mk3_flash('success', "Upload berhasil. Kode unik {$uniqueCode} sudah disimpan.");
    } catch (Throwable $e) {
        if ($targetPath && is_file($targetPath)) {
            @unlink($targetPath);
        }
        mk3_flash('danger', $e->getMessage());
    }

    header('Location: /gg_app/pages/monitoringmesin/viewdatamikwang3.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_unique_code') {
    if (!$canManageMikwang3Upload) {
        mk3_flash('danger', 'User Anda tidak memiliki akses untuk menghapus data upload Mikwang3.');
        header('Location: /gg_app/pages/monitoringmesin/viewdatamikwang3.php');
        exit;
    }

    try {
        if (!$pdo) {
            throw new RuntimeException('Koneksi database belum siap.');
        }

        $uniqueCodeToDelete = trim((string) ($_POST['unique_code'] ?? ''));
        $deleteInfo = mk3_delete_by_unique_code($pdo, $uniqueCodeToDelete);
        mk3_flash('success', "Kode unik {$uniqueCodeToDelete} berhasil dihapus. {$deleteInfo['upload_count']} upload dibersihkan.");
    } catch (Throwable $e) {
        mk3_flash('danger', $e->getMessage());
    }

    header('Location: /gg_app/pages/monitoringmesin/viewdatamikwang3.php');
    exit;
}

if ($exportAction === 'export_excel') {
    try {
        if (!$pdo) {
            throw new RuntimeException('Koneksi database belum siap.');
        }

        $rowsForExport = mk3_get_rows_for_export($pdo, $startDate, $endDate);
        $headersForExport = mk3_collect_export_headers($rowsForExport);
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('Mikwang3');
        $worksheet->fromArray($headersForExport, null, 'A1');

        $rowNumber = 2;
        foreach ($rowsForExport as $row) {
            $line = [];
            foreach ($headersForExport as $header) {
                $cellValue = (string) (($row['RowData'][$header] ?? ''));
                if (strtolower(mk3_normalize_header($header)) === 'time') {
                    $cellValue = mk3_format_export_datetime($cellValue);
                }
                $line[] = $cellValue;
            }
            $worksheet->fromArray($line, null, 'A' . $rowNumber);
            $rowNumber++;
        }

        foreach (range(1, count($headersForExport)) as $columnIndex) {
            $worksheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }

        $filenameStart = $startDate ? str_replace([' ', ':'], ['_', '-'], $startDate) : 'all';
        $filenameEnd = $endDate ? str_replace([' ', ':'], ['_', '-'], $endDate) : 'all';
        $filename = 'mikwang3_' . $filenameStart . '_' . $filenameEnd . '.xlsx';

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        exit;
    } catch (Throwable $e) {
        mk3_flash('danger', 'Export Excel gagal: ' . $e->getMessage());
        $redirectQuery = http_build_query(['start_date' => $startDateRaw, 'end_date' => $endDateRaw, 'page' => 1]);
        header('Location: /gg_app/pages/monitoringmesin/viewdatamikwang3.php?' . $redirectQuery);
        exit;
    }
}

$flash = $flash ?: mk3_take_flash();
$rows = [];
$headers = mk3_expected_headers();
$totalRows = 0;
$totalPages = 1;
$uploads = [];

if ($pdo) {
    $totalRows = mk3_count_rows($pdo, $startDate, $endDate);
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $page = min($page, $totalPages);
    $rows = mk3_get_rows_page($pdo, $startDate, $endDate, $page, $perPage);
    $headers = mk3_get_headers($pdo);
    $uploads = mk3_get_upload_list($pdo);
}

$exportUrl = '/gg_app/pages/monitoringmesin/viewdatamikwang3.php?' . http_build_query([
    'action' => 'export_excel',
    'start_date' => $startDateRaw,
    'end_date' => $endDateRaw,
]);

mm_render_page_start(
    'View Data Mikwang3',
    'Filter, export, dan lihat data hasil upload Mikwang3.',
    $themeColor,
    $flash,
    'view'
);
?>
<?php if ($canManageMikwang3Upload): ?>
<div class="card mm-card mb-3">
    <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h3 class="card-title mb-0">Upload Data Mikwang3</h3>
    </div>
    <div class="card-body">
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
    </div>
</div>
<div class="card mb-3">
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
                                    <?php if ($canManageMikwang3Upload): ?>
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
<?php endif; ?>
<div class="card mm-card">
    <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h3 class="card-title mb-0">View Data Mikwang3</h3>
    </div>
    <div class="card-body">
        <form method="get" class="mm-filter mb-3">
            <div class="row align-items-end">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="datetime-local" step="1" name="start_date" class="form-control" value="<?= htmlspecialchars(mk3_format_datetime_local_value($startDateRaw)) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="datetime-local" step="1" name="end_date" class="form-control" value="<?= htmlspecialchars(mk3_format_datetime_local_value($endDateRaw)) ?>">
                    </div>
                </div>
            </div>
            <div class="mm-filter-toolbar">
                <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-success mm-export-form">
                    <i class="fas fa-file-excel mr-1"></i>Export Excel
                </a>
                <div class="mm-filter-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter mr-1"></i>Filter</button>
                    <a href="/gg_app/pages/monitoringmesin/viewdatamikwang3.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-responsive mm-table-wrap">
            <table class="table table-bordered table-sm table-hover mm-sticky">
                <thead>
                    <tr>
                        <th>No</th>
                        <?php foreach ($headers as $header): ?>
                            <th><span class="mm-header-label"><?= nl2br(htmlspecialchars(mk3_format_header_display($header))) ?></span></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="<?= count($headers) + 1 ?>" class="text-center text-muted">Tidak ada data untuk filter ini.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $index => $row): ?>
                            <tr>
                                <td><?= (($page - 1) * $perPage) + $index + 1 ?></td>
                                <?php foreach ($headers as $header): ?>
                                    <?php
                                        $cellValue = (string) ($row['RowData'][$header] ?? '');
                                        if (strtolower(mk3_normalize_header($header)) === 'time') {
                                            $cellValue = mk3_format_preview_datetime($cellValue);
                                        }
                                    ?>
                                    <td class="mm-value"><?= htmlspecialchars($cellValue !== '' ? $cellValue : '-') ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center flex-wrap mt-3">
            <small class="text-muted d-block mb-2 mb-md-0">Menampilkan 15 record per halaman. Total data: <?= (int) $totalRows ?>.</small>
            <?php if ($totalPages > 1): ?>
                <?php
                    $buildPageUrl = static function (int $targetPage) use ($startDateRaw, $endDateRaw): string {
                        return '/gg_app/pages/monitoringmesin/viewdatamikwang3.php?' . http_build_query([
                            'start_date' => $startDateRaw,
                            'end_date' => $endDateRaw,
                            'page' => $targetPage,
                        ]);
                    };
                    $prevPage = max(1, $page - 1);
                    $nextPage = min($totalPages, $page + 1);
                    $windowStart = max(1, $page - 2);
                    $windowEnd = min($totalPages, $page + 2);
                ?>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= htmlspecialchars($buildPageUrl($prevPage)) ?>">Previous</a></li>
                    <?php if ($windowStart > 1): ?>
                        <li class="page-item"><a class="page-link" href="<?= htmlspecialchars($buildPageUrl(1)) ?>">1</a></li>
                    <?php endif; ?>
                    <?php if ($windowStart > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <?php for ($i = $windowStart; $i <= $windowEnd; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= htmlspecialchars($buildPageUrl($i)) ?>"><?= $i ?></a></li>
                    <?php endfor; ?>
                    <?php if ($windowEnd < $totalPages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <?php if ($windowEnd < $totalPages): ?>
                        <li class="page-item"><a class="page-link" href="<?= htmlspecialchars($buildPageUrl($totalPages)) ?>"><?= $totalPages ?></a></li>
                    <?php endif; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="<?= htmlspecialchars($buildPageUrl($nextPage)) ?>">Next</a></li>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
mm_render_page_end();


