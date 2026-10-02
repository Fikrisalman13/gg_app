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

if (!array_key_exists($sheet, $sheetConfigs)) {
    $sheet = 'Data_AB';
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

if ($pdo) {
    $totalRows = mm_count_sheet_rows($pdo, $sheet, $startDate, $endDate, null);
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $page = min($page, $totalPages);
    $sheetRows = mm_get_sheet_rows_page($pdo, $sheet, $startDate, $endDate, null, $page, $perPage);
    $sheetHeaders = mm_get_headers_for_sheet($pdo, $sheet, null);
}

$exportUrl = '/gg_app/pages/monitoringmesin/viewdatapbr.php?' . http_build_query([
    'action' => 'export_excel',
    'sheet' => $sheet,
    'start_date' => $startDateRaw,
    'end_date' => $endDateRaw,
]);

mm_render_page_start(
    'View Data Monitoring Mesin',
    'Filter dan export data hasil upload sheet `Data_AB`, `Alarm_AB`, dan `ErrorLog`.',
    $themeColor,
    $flash,
    'view'
);
?>
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

