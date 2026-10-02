<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/pageparts.php';

$exportAction = $_GET['action'] ?? '';
$sheetConfigs = mm_sheet_configs();
$startDateRaw = trim((string) ($_GET['start_date'] ?? ''));
$endDateRaw = trim((string) ($_GET['end_date'] ?? ''));
$startDate = mm_normalize_filter_datetime($startDateRaw, false);
$endDate = mm_normalize_filter_datetime($endDateRaw, true);
$sheet = $_GET['sheet'] ?? 'Data_AB';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$canManagePbrUpload = mm_can_access_pbr_upload($currentUserId ?? '');

if (!array_key_exists($sheet, $sheetConfigs)) {
    $sheet = 'Data_AB';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_excel') {
    if (!$canManagePbrUpload) {
        mm_flash('danger', 'User Anda tidak memiliki akses untuk upload data PBR.');
        header('Location: /gg_app/pages/monitoringmesin/viewdatapbr.php?sheet=' . urlencode($sheet));
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

        $uploadDir = dirname(__DIR__) . '/uploads/monitoringmesin';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Folder upload monitoring mesin gagal dibuat.');
        }

        $safeName = preg_replace('/[^A-Za-z0-9_\-\.]+/', '_', $originalName);
        $storedName = date('Ymd_His') . '_' . $safeName;
        $targetPath = $uploadDir . '/' . $storedName;

        if (!move_uploaded_file($_FILES['excel_file']['tmp_name'], $targetPath)) {
            throw new RuntimeException('File Excel gagal dipindahkan ke folder upload.');
        }

        $parsedWorkbook = mm_parse_workbook($targetPath);
        $dbStoredPath = '/gg_app/pages/uploads/monitoringmesin/' . $storedName;

        mm_store_upload($pdo, $parsedWorkbook, $uniqueCode, $originalName, $dbStoredPath, $currentUser);
        mm_flash('success', "Upload berhasil. Kode unik {$uniqueCode} sudah disimpan.");
    } catch (Throwable $e) {
        if ($targetPath && is_file($targetPath)) {
            @unlink($targetPath);
        }
        mm_flash('danger', $e->getMessage());
    }

    header('Location: /gg_app/pages/monitoringmesin/viewdatapbr.php?sheet=' . urlencode($sheet));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_unique_code') {
    if (!$canManagePbrUpload) {
        mm_flash('danger', 'User Anda tidak memiliki akses untuk menghapus data upload PBR.');
        header('Location: /gg_app/pages/monitoringmesin/viewdatapbr.php?sheet=' . urlencode($sheet));
        exit;
    }

    try {
        if (!$pdo) {
            throw new RuntimeException('Koneksi database belum siap.');
        }

        $uniqueCodeToDelete = trim((string) ($_POST['unique_code'] ?? ''));
        $deleteInfo = mm_delete_by_unique_code($pdo, $uniqueCodeToDelete);
        mm_flash(
            'success',
            "Kode unik {$uniqueCodeToDelete} berhasil dihapus. {$deleteInfo['upload_count']} upload dibersihkan."
        );
    } catch (Throwable $e) {
        mm_flash('danger', $e->getMessage());
    }

    header('Location: /gg_app/pages/monitoringmesin/viewdatapbr.php?sheet=' . urlencode($sheet));
    exit;
}

if ($exportAction === 'export_excel') {
    try {
        if (!$pdo) {
            throw new RuntimeException('Koneksi database belum siap.');
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheetIndex = 0;

        foreach (array_keys($sheetConfigs) as $sheetName) {
            $rowsForExport = mm_get_sheet_rows_for_export(
                $pdo,
                $sheetName,
                $startDate,
                $endDate,
                null
            );

            $headersForExport = mm_collect_export_headers($rowsForExport);
            $worksheet = $sheetIndex === 0
                ? $spreadsheet->getActiveSheet()
                : $spreadsheet->createSheet($sheetIndex);

            $worksheet->setTitle($sheetName);
            $worksheet->fromArray($headersForExport, null, 'A1');

            $rowNumber = 2;
            foreach ($rowsForExport as $row) {
                $line = [];
                foreach ($headersForExport as $header) {
                    $cellValue = (string) (($row['RowData'][$header] ?? ''));
                    $normalizedHeader = strtolower(mm_normalize_header($header));
                    if (in_array($normalizedHeader, ['time', 'date'], true)) {
                        $cellValue = mm_format_export_datetime($cellValue);
                    }
                    $line[] = $cellValue;
                }

                $worksheet->fromArray($line, null, 'A' . $rowNumber);
                $rowNumber++;
            }

            foreach (range(1, count($headersForExport)) as $columnIndex) {
                $worksheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
            }

            $sheetIndex++;
        }

        $spreadsheet->setActiveSheetIndex(0);
        $filenameStart = $startDate ? str_replace([' ', ':'], ['_', '-'], $startDate) : 'all';
        $filenameEnd = $endDate ? str_replace([' ', ':'], ['_', '-'], $endDate) : 'all';
        $filename = 'monitoringmesin_' . $filenameStart . '_' . $filenameEnd . '.xlsx';

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
        mm_flash('danger', 'Export Excel gagal: ' . $e->getMessage());
        $redirectQuery = http_build_query([
            'sheet' => 'Data_AB',
            'start_date' => $startDateRaw,
            'end_date' => $endDateRaw,
            'page' => 1,
        ]);
        header('Location: /gg_app/pages/monitoringmesin/viewdatapbr.php?' . $redirectQuery);
        exit;
    }
}

$flash = $flash ?: mm_take_flash();
$sheetRows = [];
$sheetHeaders = [];
$totalRows = 0;
$totalPages = 1;
$uploads = [];
$allowedUploadUserIds = mm_allowed_pbr_upload_user_ids();

if ($pdo) {
    $totalRows = mm_count_sheet_rows($pdo, $sheet, $startDate, $endDate, null);
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $page = min($page, $totalPages);
    $sheetRows = mm_get_sheet_rows_page($pdo, $sheet, $startDate, $endDate, null, $page, $perPage);
    $sheetHeaders = mm_get_headers_for_sheet($pdo, $sheet, null);
    $uploads = mm_get_upload_list($pdo, null);
}

$exportUrl = '/gg_app/pages/monitoringmesin/viewdatapbr.php?' . http_build_query([
    'action' => 'export_excel',
    'sheet' => $sheet,
    'start_date' => $startDateRaw,
    'end_date' => $endDateRaw,
]);

mm_render_page_start(
    'View Data Monitoring Mesin',
    'Filter, export, dan upload data hasil sheet `Data_AB`, `Alarm_AB`, dan `ErrorLog`.',
    $themeColor,
    $flash,
    'view'
);
?>
<?php if ($canManagePbrUpload): ?>
<div class="card mm-card mb-3">
    <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h3 class="card-title mb-0">Upload Data PBR</h3>
    </div>
    <div class="card-body">
            <form method="post" enctype="multipart/form-data" id="uploadExcelForm">
                <input type="hidden" name="action" value="upload_excel">
                <input type="hidden" name="sheet" value="<?= htmlspecialchars($sheet) ?>">
                <div class="form-group mb-0">
                    <label>Pilih file `.xlsx`</label>
                    <div class="mm-upload-inline">
                        <input type="file" name="excel_file" class="form-control" accept=".xlsx" required>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-file-upload mr-1"></i>Upload Sekarang
                        </button>
                    </div>
                    <small class="text-muted">Kode unik diambil dari judul file. Jika nama file sama dengan yang pernah di-upload, sistem akan menolak.</small>
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
                        <th>Data_AB</th>
                        <th>Alarm_AB</th>
                        <th>ErrorLog</th>
                        <th>Upload By</th>
                        <th>Uploaded At</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($uploads === []): ?>
                        <tr><td colspan="8" class="text-center text-muted">Belum ada data upload.</td></tr>
                    <?php else: ?>
                        <?php foreach ($uploads as $upload): ?>
                            <tr>
                                <td><span class="mm-code-badge"><?= htmlspecialchars($upload['UniqueCode']) ?></span></td>
                                <td><?= htmlspecialchars($upload['OriginalFileName']) ?></td>
                                <td><?= (int) $upload['DataAbCount'] ?></td>
                                <td><?= (int) $upload['AlarmAbCount'] ?></td>
                                <td><?= (int) $upload['ErrorLogCount'] ?></td>
                                <td><?= htmlspecialchars((string) ($upload['UploadedBy'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string) $upload['UploadedAt']) ?></td>
                                <td class="mm-actions">
                                    <?php if ($canManagePbrUpload): ?>
                                        <form method="post" class="d-inline-block mm-delete-form" data-unique-code="<?= htmlspecialchars($upload['UniqueCode']) ?>">
                                            <input type="hidden" name="action" value="delete_unique_code">
                                            <input type="hidden" name="unique_code" value="<?= htmlspecialchars($upload['UniqueCode']) ?>">
                                            <input type="hidden" name="sheet" value="<?= htmlspecialchars($sheet) ?>">
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
        <h3 class="card-title mb-0">View Data</h3>
    </div>
    <div class="card-body">
        <form method="get" class="mm-filter mb-3">
            <div class="row align-items-end">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Sheet</label>
                        <select name="sheet" class="form-control">
                            <?php foreach (array_keys(mm_sheet_configs()) as $sheetOption): ?>
                                <option value="<?= htmlspecialchars($sheetOption) ?>" <?= $sheet === $sheetOption ? 'selected' : '' ?>><?= htmlspecialchars($sheetOption) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="datetime-local" step="1" name="start_date" class="form-control" value="<?= htmlspecialchars(mm_format_datetime_local_value($startDateRaw)) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="datetime-local" step="1" name="end_date" class="form-control" value="<?= htmlspecialchars(mm_format_datetime_local_value($endDateRaw)) ?>">
                    </div>
                </div>
            </div>
            <div class="mm-filter-toolbar">
                <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-success mm-export-form">
                    <i class="fas fa-file-excel mr-1"></i>Export Excel 3 Sheet
                </a>
                <div class="mm-filter-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter mr-1"></i>Filter</button>
                    <a href="/gg_app/pages/monitoringmesin/viewdatapbr.php?sheet=<?= urlencode($sheet) ?>" class="btn btn-outline-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="mb-3">
            <a class="btn btn-sm <?= $sheet === 'Data_AB' ? 'btn-primary' : 'btn-outline-primary' ?>" href="/gg_app/pages/monitoringmesin/viewdatapbr.php?sheet=Data_AB&start_date=<?= urlencode($startDateRaw) ?>&end_date=<?= urlencode($endDateRaw) ?>">Data_AB</a>
            <a class="btn btn-sm <?= $sheet === 'Alarm_AB' ? 'btn-primary' : 'btn-outline-primary' ?>" href="/gg_app/pages/monitoringmesin/viewdatapbr.php?sheet=Alarm_AB&start_date=<?= urlencode($startDateRaw) ?>&end_date=<?= urlencode($endDateRaw) ?>">Alarm_AB</a>
            <a class="btn btn-sm <?= $sheet === 'ErrorLog' ? 'btn-primary' : 'btn-outline-primary' ?>" href="/gg_app/pages/monitoringmesin/viewdatapbr.php?sheet=ErrorLog&start_date=<?= urlencode($startDateRaw) ?>&end_date=<?= urlencode($endDateRaw) ?>">ErrorLog</a>
        </div>

        <div class="table-responsive mm-table-wrap">
            <table class="table table-bordered table-sm table-hover mm-sticky">
                <thead>
                    <tr>
                        <th>No</th>
                        <?php foreach ($sheetHeaders as $header): ?>
                            <th><span class="mm-header-label"><?= nl2br(htmlspecialchars(mm_format_header_display($header))) ?></span></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($sheetRows === []): ?>
                        <tr><td colspan="<?= count($sheetHeaders) + 1 ?>" class="text-center text-muted">Tidak ada data untuk filter ini.</td></tr>
                    <?php else: ?>
                        <?php foreach ($sheetRows as $index => $row): ?>
                            <tr>
                                <td><?= (($page - 1) * $perPage) + $index + 1 ?></td>
                                <?php foreach ($sheetHeaders as $header): ?>
                                    <?php
                                        $cellValue = (string) ($row['RowData'][$header] ?? '');
                                        $normalizedHeader = strtolower(mm_normalize_header($header));
                                        if (in_array($normalizedHeader, ['time', 'date'], true)) {
                                            $cellValue = mm_format_preview_datetime($cellValue);
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
                    $buildPageUrl = static function (int $targetPage) use ($sheet, $startDateRaw, $endDateRaw): string {
                        return '/gg_app/pages/monitoringmesin/viewdatapbr.php?' . http_build_query([
                            'sheet' => $sheet,
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
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= htmlspecialchars($buildPageUrl($prevPage)) ?>">Previous</a>
                    </li>
                    <?php if ($windowStart > 1): ?>
                        <li class="page-item"><a class="page-link" href="<?= htmlspecialchars($buildPageUrl(1)) ?>">1</a></li>
                    <?php endif; ?>
                    <?php if ($windowStart > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <?php for ($i = $windowStart; $i <= $windowEnd; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= htmlspecialchars($buildPageUrl($i)) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($windowEnd < $totalPages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <?php if ($windowEnd < $totalPages): ?>
                        <li class="page-item"><a class="page-link" href="<?= htmlspecialchars($buildPageUrl($totalPages)) ?>"><?= $totalPages ?></a></li>
                    <?php endif; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= htmlspecialchars($buildPageUrl($nextPage)) ?>">Next</a>
                    </li>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
mm_render_page_end();
