<?php
declare(strict_types=1);

session_start();
ob_start();

require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/helper.php';

date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserId']) || empty($_SESSION['UserId'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
$currentUser = $_SESSION['UserName'] ?? 'SYSTEM';
$flash = null;

try {
    $pdo = mm_pdo();
    mm_ensure_tables($pdo);
} catch (Throwable $e) {
    $pdo = null;
    $flash = ['type' => 'danger', 'message' => 'Gagal menyiapkan tabel monitoring mesin: ' . $e->getMessage()];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_excel') {
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

    header('Location: /gg_app/pages/monitoringmesin/index.php?tab=upload');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_unique_code') {
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

    header('Location: /gg_app/pages/monitoringmesin/index.php?tab=upload');
    exit;
}

$exportAction = $_GET['action'] ?? '';
$sheetConfigs = mm_sheet_configs();
$startDateRaw = trim((string) ($_GET['start_date'] ?? ''));
$endDateRaw = trim((string) ($_GET['end_date'] ?? ''));
$startDate = mm_normalize_filter_datetime($startDateRaw, false);
$endDate = mm_normalize_filter_datetime($endDateRaw, true);
$uniqueCodeFilter = trim((string) ($_GET['unique_code'] ?? ''));

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
                $uniqueCodeFilter !== '' ? $uniqueCodeFilter : null
            );

            $headersForExport = mm_collect_export_headers($rowsForExport);
            $worksheet = $sheetIndex === 0
                ? $spreadsheet->getActiveSheet()
                : $spreadsheet->createSheet($sheetIndex);

            $worksheet->setTitle($sheetName);

            $excelHeaders = $headersForExport;
            $worksheet->fromArray($excelHeaders, null, 'A1');

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

            foreach (range(1, count($excelHeaders)) as $columnIndex) {
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
            'tab' => 'view',
            'sheet' => 'Data_AB',
            'start_date' => $startDateRaw,
            'end_date' => $endDateRaw,
            'unique_code' => $uniqueCodeFilter,
        ]);
        header('Location: /gg_app/pages/monitoringmesin/index.php?' . $redirectQuery);
        exit;
    }
}

$flash = $flash ?: mm_take_flash();
$activeTab = ($_GET['tab'] ?? 'upload') === 'view' ? 'view' : 'upload';
$sheet = $_GET['sheet'] ?? 'Data_AB';
if (!array_key_exists($sheet, $sheetConfigs)) {
    $sheet = 'Data_AB';
}

$uploads = [];
$sheetRows = [];
$sheetHeaders = [];

if ($pdo) {
    $uploads = mm_get_upload_list($pdo, $uniqueCodeFilter !== '' ? $uniqueCodeFilter : null);
    $sheetRows = mm_get_sheet_rows(
        $pdo,
        $sheet,
        $startDate,
        $endDate,
        $uniqueCodeFilter !== '' ? $uniqueCodeFilter : null
    );
    $sheetHeaders = mm_get_headers_for_sheet($pdo, $sheet, $uniqueCodeFilter !== '' ? $uniqueCodeFilter : null);
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
ob_end_flush();
?>
<style>
    .mm-card { border-top: 3px solid #0d6efd; }
    .mm-filter .form-control, .mm-filter .btn { height: 38px; }
    .mm-table-wrap { max-height: 560px; overflow: auto; }
    .mm-sticky th { position: sticky; top: 0; z-index: 2; background: #fff; white-space: nowrap; }
    .mm-code-badge { font-size: .85rem; padding: .35rem .6rem; border-radius: 999px; background: #e9f2ff; color: #0d6efd; font-weight: 600; }
    .mm-value { max-width: 260px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .mm-loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(255, 255, 255, 0.78);
        z-index: 99999;
        display: none;
        align-items: center;
        justify-content: center;
        flex-direction: column;
    }
    .mm-loading-icon {
        width: 68px;
        height: 68px;
        animation: mm-spin 1.2s linear infinite;
    }
    .mm-loading-text {
        margin-top: 18px;
        font-size: 1.15rem;
        color: #444;
        font-weight: 600;
    }
    .mm-actions {
        white-space: nowrap;
        width: 110px;
    }
    @keyframes mm-spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>

<div id="mmLoadingOverlay" class="mm-loading-overlay">
    <img src="https://cdn-icons-png.flaticon.com/512/189/189792.png" alt="Loading" class="mm-loading-icon">
    <div class="mm-loading-text">Memuat data, mohon menunggu...</div>
</div>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0">Monitoring Mesin</h1>
                    <small class="text-muted">Upload Excel `.xlsx`, simpan sheet `Data_AB`, `Alarm_AB`, dan `ErrorLog`.</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                        <li class="breadcrumb-item active">Monitoring Mesin</li>
                    </ol>
                </div>
            </div>

            <ul class="nav nav-pills">
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'upload' ? 'active' : '' ?>" href="/gg_app/pages/monitoringmesin/index.php?tab=upload">Upload Data</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'view' ? 'active' : '' ?>" href="/gg_app/pages/monitoringmesin/index.php?tab=view&sheet=<?= urlencode($sheet) ?>">View Data</a>
                </li>
            </ul>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <?php if ($flash): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show">
                    <?= htmlspecialchars($flash['message']) ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <?php if ($activeTab === 'upload'): ?>
                <div class="card mm-card">
                    <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                        <h3 class="card-title mb-0">Upload File Excel</h3>
                    </div>
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data" id="uploadExcelForm">
                            <input type="hidden" name="action" value="upload_excel">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label>Pilih file `.xlsx`</label>
                                        <input type="file" name="excel_file" class="form-control" accept=".xlsx" required>
                                        <small class="text-muted">Kode unik diambil dari judul file. Jika nama file sama dengan yang pernah di-upload, sistem akan menolak.</small>
                                    </div>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-file-upload mr-1"></i>Upload Sekarang
                                    </button>
                                </div>
                            </div>
                        </form>
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
                                                    <form method="post" class="d-inline-block mm-delete-form" data-unique-code="<?= htmlspecialchars($upload['UniqueCode']) ?>">
                                                        <input type="hidden" name="action" value="delete_unique_code">
                                                        <input type="hidden" name="unique_code" value="<?= htmlspecialchars($upload['UniqueCode']) ?>">
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
            <?php else: ?>
                <div class="card mm-card">
                    <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                        <h3 class="card-title mb-0">View Data</h3>
                    </div>
                    <div class="card-body">
                        <form method="get" class="mm-filter">
                            <input type="hidden" name="tab" value="view">
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Sheet</label>
                                        <select name="sheet" class="form-control">
                                            <?php foreach (array_keys(mm_sheet_configs()) as $sheetOption): ?>
                                                <option value="<?= htmlspecialchars($sheetOption) ?>" <?= $sheet === $sheetOption ? 'selected' : '' ?>><?= htmlspecialchars($sheetOption) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Start Date</label>
                                        <input type="datetime-local" step="1" name="start_date" class="form-control" value="<?= htmlspecialchars(mm_format_datetime_local_value($startDateRaw)) ?>">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>End Date</label>
                                        <input type="datetime-local" step="1" name="end_date" class="form-control" value="<?= htmlspecialchars(mm_format_datetime_local_value($endDateRaw)) ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Kode Unik</label>
                                        <input type="text" name="unique_code" class="form-control" value="<?= htmlspecialchars($uniqueCodeFilter) ?>" placeholder="Cari nama file / kode unik">
                                    </div>
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <div class="w-100">
                                        <button type="submit" class="btn btn-primary mr-2"><i class="fas fa-filter mr-1"></i>Filter</button>
                                        <a href="/gg_app/pages/monitoringmesin/index.php?tab=view&sheet=<?= urlencode($sheet) ?>" class="btn btn-outline-secondary">Reset</a>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <form method="get" class="mb-3 mm-export-form">
                            <input type="hidden" name="action" value="export_excel">
                            <input type="hidden" name="tab" value="view">
                            <input type="hidden" name="sheet" value="<?= htmlspecialchars($sheet) ?>">
                            <input type="hidden" name="start_date" value="<?= htmlspecialchars($startDateRaw) ?>">
                            <input type="hidden" name="end_date" value="<?= htmlspecialchars($endDateRaw) ?>">
                            <input type="hidden" name="unique_code" value="<?= htmlspecialchars($uniqueCodeFilter) ?>">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-file-excel mr-1"></i>Export Excel 3 Sheet
                            </button>
                        </form>

                        <div class="mb-3">
                            <a class="btn btn-sm <?= $sheet === 'Data_AB' ? 'btn-primary' : 'btn-outline-primary' ?>" href="/gg_app/pages/monitoringmesin/index.php?tab=view&sheet=Data_AB&start_date=<?= urlencode($startDateRaw) ?>&end_date=<?= urlencode($endDateRaw) ?>&unique_code=<?= urlencode($uniqueCodeFilter) ?>">Data_AB</a>
                            <a class="btn btn-sm <?= $sheet === 'Alarm_AB' ? 'btn-primary' : 'btn-outline-primary' ?>" href="/gg_app/pages/monitoringmesin/index.php?tab=view&sheet=Alarm_AB&start_date=<?= urlencode($startDateRaw) ?>&end_date=<?= urlencode($endDateRaw) ?>&unique_code=<?= urlencode($uniqueCodeFilter) ?>">Alarm_AB</a>
                            <a class="btn btn-sm <?= $sheet === 'ErrorLog' ? 'btn-primary' : 'btn-outline-primary' ?>" href="/gg_app/pages/monitoringmesin/index.php?tab=view&sheet=ErrorLog&start_date=<?= urlencode($startDateRaw) ?>&end_date=<?= urlencode($endDateRaw) ?>&unique_code=<?= urlencode($uniqueCodeFilter) ?>">ErrorLog</a>
                        </div>

                        <div class="table-responsive mm-table-wrap">
                            <table class="table table-bordered table-sm table-hover mm-sticky">
                                <thead>
                                    <tr>
                                        <th>Kode Unik</th>
                                        <th>Baris</th>
                                        <th>Record Date</th>
                                        <?php foreach ($sheetHeaders as $header): ?>
                                            <th><?= htmlspecialchars($header) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($sheetRows === []): ?>
                                        <tr><td colspan="<?= count($sheetHeaders) + 3 ?>" class="text-center text-muted">Tidak ada data untuk filter ini.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($sheetRows as $row): ?>
                                            <tr>
                                                <td><span class="mm-code-badge"><?= htmlspecialchars($row['UniqueCode']) ?></span></td>
                                                <td><?= (int) $row['RowNumber'] ?></td>
                                                <td><?= htmlspecialchars(mm_format_preview_datetime($row['RecordDate'] ?: $row['RecordDateText'] ?: '')) ?></td>
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
                        <small class="text-muted d-block mt-2">Menampilkan maksimal 200 baris terbaru sesuai filter.</small>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var overlay = document.getElementById('mmLoadingOverlay');
    var uploadForm = document.getElementById('uploadExcelForm');
    var deleteForms = document.querySelectorAll('.mm-delete-form');
    var exportForms = document.querySelectorAll('.mm-export-form');
    var exportHideTimer = null;

    function showOverlay() {
        if (overlay) {
            overlay.style.display = 'flex';
        }
    }

    function hideOverlay() {
        if (overlay) {
            overlay.style.display = 'none';
        }
    }

    if (uploadForm && overlay) {
        uploadForm.addEventListener('submit', function () {
            showOverlay();
        });
    }

    deleteForms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var uniqueCode = form.getAttribute('data-unique-code') || '';
            var confirmed = window.confirm('Hapus semua data dengan kode unik ' + uniqueCode + '?');
            if (!confirmed) {
                event.preventDefault();
                return;
            }

            showOverlay();
        });
    });

    exportForms.forEach(function (form) {
        form.addEventListener('submit', function () {
            showOverlay();

            if (exportHideTimer) {
                window.clearTimeout(exportHideTimer);
            }

            exportHideTimer = window.setTimeout(function () {
                hideOverlay();
            }, 5000);
        });
    });

    window.addEventListener('pageshow', function () {
        hideOverlay();
    });

    window.addEventListener('focus', function () {
        if (exportHideTimer) {
            window.clearTimeout(exportHideTimer);
            exportHideTimer = null;
        }
        hideOverlay();
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
