<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap_mikwang4.php';
require_once __DIR__ . '/pageparts.php';

$exportAction = $_GET['action'] ?? '';
$startDateRaw = trim((string) ($_GET['start_date'] ?? ''));
$endDateRaw = trim((string) ($_GET['end_date'] ?? ''));
$startDate = mk4_normalize_filter_datetime($startDateRaw, false);
$endDate = mk4_normalize_filter_datetime($endDateRaw, true);
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;

if ($exportAction === 'export_excel') {
    try {
        if (!$pdo) {
            throw new RuntimeException('Koneksi database belum siap.');
        }

        $rowsForExport = mk4_get_rows_for_export($pdo, $startDate, $endDate);
        $headersForExport = mk4_collect_export_headers($rowsForExport);
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('Mikwang4');
        $worksheet->fromArray($headersForExport, null, 'A1');

        $rowNumber = 2;
        foreach ($rowsForExport as $row) {
            $line = [];
            foreach ($headersForExport as $header) {
                $cellValue = (string) (($row['RowData'][$header] ?? ''));
                if (strtolower(mk4_normalize_header($header)) === 'time') {
                    $cellValue = mk4_format_export_datetime($cellValue);
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
        $filename = 'mikwang4_' . $filenameStart . '_' . $filenameEnd . '.xlsx';

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
        mk4_flash('danger', 'Export Excel gagal: ' . $e->getMessage());
        $redirectQuery = http_build_query(['start_date' => $startDateRaw, 'end_date' => $endDateRaw, 'page' => 1]);
        header('Location: /gg_app/pages/monitoringmesin/viewdatamikwang4.php?' . $redirectQuery);
        exit;
    }
}

$flash = $flash ?: mk4_take_flash();
$rows = [];
$headers = mk4_expected_headers();
$totalRows = 0;
$totalPages = 1;

if ($pdo) {
    $totalRows = mk4_count_rows($pdo, $startDate, $endDate);
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $page = min($page, $totalPages);
    $rows = mk4_get_rows_page($pdo, $startDate, $endDate, $page, $perPage);
    $headers = mk4_get_headers($pdo);
}

$exportUrl = '/gg_app/pages/monitoringmesin/viewdatamikwang4.php?' . http_build_query([
    'action' => 'export_excel',
    'start_date' => $startDateRaw,
    'end_date' => $endDateRaw,
]);

mm_render_page_start(
    'View Data Mikwang4',
    'Filter, export, dan lihat data hasil upload Mikwang4.',
    $themeColor,
    $flash,
    'view'
);
?>
<div class="card mm-card">
    <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h3 class="card-title mb-0">View Data Mikwang4</h3>
    </div>
    <div class="card-body">
        <form method="get" class="mm-filter mb-3">
            <div class="row align-items-end">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="datetime-local" step="1" name="start_date" class="form-control" value="<?= htmlspecialchars(mk4_format_datetime_local_value($startDateRaw)) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="datetime-local" step="1" name="end_date" class="form-control" value="<?= htmlspecialchars(mk4_format_datetime_local_value($endDateRaw)) ?>">
                    </div>
                </div>
            </div>
            <div class="mm-filter-toolbar">
                <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-success mm-export-form">
                    <i class="fas fa-file-excel mr-1"></i>Export Excel
                </a>
                <div class="mm-filter-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter mr-1"></i>Filter</button>
                    <a href="/gg_app/pages/monitoringmesin/viewdatamikwang4.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-responsive mm-table-wrap">
            <table class="table table-bordered table-sm table-hover mm-sticky">
                <thead>
                    <tr>
                        <th>No</th>
                        <?php foreach ($headers as $header): ?>
                            <th><span class="mm-header-label"><?= nl2br(htmlspecialchars(mk4_format_header_display($header))) ?></span></th>
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
                                        if (strtolower(mk4_normalize_header($header)) === 'time') {
                                            $cellValue = mk4_format_preview_datetime($cellValue);
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
                        return '/gg_app/pages/monitoringmesin/viewdatamikwang4.php?' . http_build_query([
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



