<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
require_once __DIR__ . '/issue_crud.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/vendor/autoload.php';

use Mpdf\Mpdf;

const PURCHASING_ISSUE_PDF_MAX_ROWS = 5000;

/**
 * Mengirim halaman kegagalan aman untuk endpoint PDF tanpa mengekspos detail SQL atau driver.
 *
 * @param int $status Kode HTTP yang sesuai dengan kegagalan.
 * @param string $message Pesan aman yang dapat dibaca pengguna.
 * @return void
 */
function purchasingIssuePdfFail(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Export PDF Issues Purchasing</title></head><body><p>'
        . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</p></body></html>';
    exit;
}

/**
 * Menormalisasi tanggal-waktu filter PDF dengan format ISO menit yang ketat.
 *
 * @param mixed $value Nilai mentah dari query string.
 * @param string $label Label aman untuk pesan validasi.
 * @return array{valid: bool, value: string|null, message: string} Hasil normalisasi tanggal.
 */
function purchasingIssuePdfDateTimeFilter($value, string $label): array
{
    if ($value === null || $value === '') {
        return ['valid' => true, 'value' => null, 'message' => ''];
    }

    if (!is_string($value)) {
        return ['valid' => false, 'value' => null, 'message' => $label . ' tidak valid.'];
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $value);
    if ($date === false || $date->format('Y-m-d\\TH:i') !== $value) {
        return ['valid' => false, 'value' => null, 'message' => $label . ' tidak valid.'];
    }

    return ['valid' => true, 'value' => $date->format('Y-m-d H:i:s'), 'message' => ''];
}

/**
 * Mengubah nilai teks database menjadi HTML PDF yang aman.
 *
 * @param mixed $value Nilai yang akan ditampilkan.
 * @return string Teks HTML-escaped atau tanda strip bila kosong.
 */
function purchasingIssuePdfText($value): string
{
    $text = trim((string) ($value ?? ''));
    return htmlspecialchars($text !== '' ? $text : '-', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Mengubah tanggal SQL Server menjadi teks yang dapat dibaca pada PDF.
 *
 * @param mixed $value Nilai tanggal dari SQL Server.
 * @param string $format Format keluaran tanggal.
 * @return string Tanggal aman atau tanda strip bila kosong.
 */
function purchasingIssuePdfDate($value, string $format = 'd/m/Y H:i'): string
{
    if ($value instanceof DateTimeInterface) {
        return $value->format($format);
    }

    $text = trim((string) ($value ?? ''));
    if ($text === '') {
        return '-';
    }

    $date = date_create_immutable($text);
    if ($date instanceof DateTimeImmutable) {
        return $date->format($format);
    }

    return purchasingIssuePdfText($text);
}

/**
 * Menghasilkan label periode berdasarkan filter tanggal-waktu PDF.
 *
 * @param string|null $startDate Filter tanggal mulai yang telah tervalidasi.
 * @param string|null $endDate Filter tanggal akhir yang telah tervalidasi.
 * @return string Label periode untuk header laporan.
 */
function purchasingIssuePdfPeriodLabel(?string $startDate, ?string $endDate): string
{
    if ($startDate !== null && $endDate !== null) {
        return 'Periode: ' . date('d/m/Y H:i', strtotime($startDate)) . ' - ' . date('d/m/Y H:i', strtotime($endDate));
    }

    if ($startDate !== null) {
        return 'Dibuat mulai: ' . date('d/m/Y H:i', strtotime($startDate));
    }

    if ($endDate !== null) {
        return 'Dibuat sampai: ' . date('d/m/Y H:i', strtotime($endDate));
    }

    return 'Semua tanggal';
}

/**
 * Menjalankan export PDF Issues Purchasing untuk daftar aktif atau riwayat selesai.
 *
 * @param resource|null $conn Koneksi SQL Server aplikasi.
 * @return void
 */
function exportPurchasingIssuesPdf($conn): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        header('Allow: GET');
        purchasingIssuePdfFail(405, 'Metode permintaan tidak didukung.');
    }

    $access = purchasingIssueAccess($conn);
    if (empty($access['allowed'])) {
        purchasingIssuePdfFail((int) ($access['status'] ?? 403), (string) ($access['message'] ?? 'Akses ditolak.'));
    }

    $scope = trim((string) ($_GET['scope'] ?? ''));
    if (!in_array($scope, ['active', 'history'], true)) {
        purchasingIssuePdfFail(400, 'Jenis daftar yang akan diexport tidak valid.');
    }

    $type = trim((string) ($_GET['filterType'] ?? ''));
    if ($type !== '' && !in_array($type, ['Task', 'Support Activities'], true)) {
        purchasingIssuePdfFail(400, 'Filter jenis issue tidak valid.');
    }

    $status = trim((string) ($_GET['filterStatus'] ?? ''));
    if ($scope === 'history' && $status !== '') {
        purchasingIssuePdfFail(400, 'Filter status tidak sesuai dengan riwayat issue.');
    }
    if ($scope === 'active' && $status !== '' && !in_array($status, ['To Do', 'In Progress'], true)) {
        purchasingIssuePdfFail(400, 'Filter status tidak valid.');
    }

    $startDate = purchasingIssuePdfDateTimeFilter($_GET['filterTanggalStart'] ?? null, 'Tanggal dan jam mulai');
    $endDate = purchasingIssuePdfDateTimeFilter($_GET['filterTanggalEnd'] ?? null, 'Tanggal dan jam akhir');
    if (!$startDate['valid']) {
        purchasingIssuePdfFail(400, $startDate['message']);
    }
    if (!$endDate['valid']) {
        purchasingIssuePdfFail(400, $endDate['message']);
    }
    if ($startDate['value'] !== null && $endDate['value'] !== null && $startDate['value'] > $endDate['value']) {
        purchasingIssuePdfFail(400, 'Rentang tanggal dan jam tidak valid.');
    }

    $conditions = [];
    $parameters = [];
    if ($scope === 'history') {
        $conditions[] = 'i.status = ?';
        $parameters[] = 'Done';
    } else {
        $conditions[] = '(i.status <> ? OR i.status IS NULL)';
        $parameters[] = 'Done';
    }

    if ($type !== '') {
        $conditions[] = 'i.issue_type = ?';
        $parameters[] = $type;
    }
    if ($status !== '') {
        $conditions[] = 'i.status = ?';
        $parameters[] = $status;
    }
    if ($startDate['value'] !== null) {
        $conditions[] = 'i.created_at >= ?';
        $parameters[] = $startDate['value'];
    }
    if ($endDate['value'] !== null) {
        $conditions[] = 'i.created_at < DATEADD(minute, 1, ?)';
        $parameters[] = $endDate['value'];
    }

    $where = 'WHERE ' . implode(' AND ', $conditions);
    $countStatement = sqlsrv_query(
        $conn,
        'SELECT COUNT_BIG(*) AS total FROM dbo.purchasing_issues AS i ' . $where,
        $parameters
    );
    if ($countStatement === false) {
        $operationId = purchasingIssueOperationId();
        purchasingIssueLogFailure('export_pdf', 'count', $operationId);
        purchasingIssuePdfFail(500, 'Data issue tidak dapat diexport. ID permintaan: ' . $operationId . '.');
    }

    $countRow = sqlsrv_fetch_array($countStatement, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($countStatement);
    if (!is_array($countRow)) {
        $operationId = purchasingIssueOperationId();
        purchasingIssueLogFailure('export_pdf', 'count_fetch', $operationId);
        purchasingIssuePdfFail(500, 'Data issue tidak dapat diexport. ID permintaan: ' . $operationId . '.');
    }
    $totalRows = (int) ($countRow['total'] ?? 0);

    $dataStatement = sqlsrv_query(
        $conn,
        'SELECT TOP (' . PURCHASING_ISSUE_PDF_MAX_ROWS . ')
                i.issue_id,
                i.issue_name,
                i.issue_type,
                c.category_name,
                s.sub_name,
                i.created_by,
                i.status,
                i.priority,
                i.tanggal_selesai,
                i.created_at
         FROM dbo.purchasing_issues AS i
         LEFT JOIN dbo.purchasing_issue_categories AS c ON c.category_id = i.category_id
         LEFT JOIN dbo.purchasing_issue_sub_categories AS s
            ON s.sub_id = i.sub_id AND s.category_id = i.category_id
         ' . $where . '
         ORDER BY i.created_at ASC, i.issue_id ASC',
        $parameters
    );
    if ($dataStatement === false) {
        $operationId = purchasingIssueOperationId();
        purchasingIssueLogFailure('export_pdf', 'query', $operationId);
        purchasingIssuePdfFail(500, 'Data issue tidak dapat diexport. ID permintaan: ' . $operationId . '.');
    }

    $exportRows = [];
    $historySummary = [];
    while (($row = sqlsrv_fetch_array($dataStatement, SQLSRV_FETCH_ASSOC)) !== null) {
        if ($row === false) {
            sqlsrv_free_stmt($dataStatement);
            $operationId = purchasingIssueOperationId();
            purchasingIssueLogFailure('export_pdf', 'fetch', $operationId);
            purchasingIssuePdfFail(500, 'Data issue tidak dapat diexport. ID permintaan: ' . $operationId . '.');
        }

        $exportRows[] = $row;
        if ($scope !== 'history') {
            continue;
        }

        $creatorName = trim((string) ($row['created_by'] ?? ''));
        $issueType = trim((string) ($row['issue_type'] ?? ''));
        $summaryKey = mb_strtolower($creatorName) . "\x1F" . mb_strtolower($issueType);
        if (!isset($historySummary[$summaryKey])) {
            $historySummary[$summaryKey] = [
                'name' => $creatorName,
                'type' => $issueType,
                'total' => 0
            ];
        }
        $historySummary[$summaryKey]['total']++;
    }
    sqlsrv_free_stmt($dataStatement);
    $dataStatement = null;

    if ($scope === 'history') {
        uasort(
            $historySummary,
            static function (array $left, array $right): int {
                $nameComparison = strnatcasecmp($left['name'], $right['name']);
                if ($nameComparison !== 0) {
                    return $nameComparison;
                }

                return strnatcasecmp($left['type'], $right['type']);
            }
        );
    }

    $reportTitle = $scope === 'history'
        ? 'Laporan Riwayat Aktivitas Purchasing'
        : 'Laporan Daftar Issues Purchasing';
    $periodLabel = purchasingIssuePdfPeriodLabel($startDate['value'], $endDate['value']);
    $rowLimitNotice = $totalRows > PURCHASING_ISSUE_PDF_MAX_ROWS
        ? 'Menampilkan ' . number_format(PURCHASING_ISSUE_PDF_MAX_ROWS, 0, ',', '.') . ' dari ' . number_format($totalRows, 0, ',', '.') . ' issue yang sesuai filter.'
        : number_format($totalRows, 0, ',', '.') . ' issue yang sesuai filter.';

    try {
        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 15,
            'margin_bottom' => 12,
            'margin_header' => 8,
            'margin_footer' => 8
        ]);
        $pdf->SetTitle($reportTitle);
        $pdf->SetAuthor('GG App');
        $pdf->showImageErrors = false;

        $pdf->WriteHTML(
            '<style>'
            . 'body { font-family: sans-serif; font-size: 9px; color: #111; }'
            . 'h2 { text-align: center; font-size: 13px; margin: 0 0 3px; }'
            . '.meta { text-align: center; font-size: 9px; color: #555; margin-bottom: 4px; }'
            . 'table { width: 100%; border-collapse: collapse; table-layout: fixed; }'
            . 'th, td { border: 0.3mm solid #555; padding: 4px 5px; vertical-align: top; }'
            . 'th { background-color: #e8e8e8; font-weight: bold; text-align: center; }'
            . 'tr.even { background-color: #f7f7f7; }'
            . '.nowrap { white-space: nowrap; }'
            . '.summary-title { font-size: 10px; font-weight: bold; margin: 8px 0 3px; }'
            . '.summary-table { width: 55%; margin: 0 0 8px; table-layout: auto; }'
            . '</style>'
            . '<h2>' . purchasingIssuePdfText($reportTitle) . '</h2>'
            . '<div class="meta">' . purchasingIssuePdfText($periodLabel) . '</div>'
            . '<div class="meta">' . purchasingIssuePdfText($rowLimitNotice) . '</div>'
        );

        if ($scope === 'history') {
            $summaryHtml = '<div class="summary-title">Ringkasan Aktivitas per Nama dan Type</div>'
                . '<table class="summary-table"><thead><tr>'
                . '<th>Nama</th><th>Type</th><th style="width:15%">Total</th>'
                . '</tr></thead><tbody>';
            if ($historySummary === []) {
                $summaryHtml .= '<tr><td colspan="3" style="text-align:center">Tidak ada aktivitas sesuai filter.</td></tr>';
            } else {
                foreach ($historySummary as $summary) {
                    $summaryHtml .= '<tr>'
                        . '<td>' . purchasingIssuePdfText($summary['name']) . '</td>'
                        . '<td>' . purchasingIssuePdfText($summary['type']) . '</td>'
                        . '<td style="text-align:center">' . number_format($summary['total'], 0, ',', '.') . '</td>'
                        . '</tr>';
                }
            }
            $summaryHtml .= '</tbody></table>';
            $pdf->WriteHTML($summaryHtml);
        }

        $pdf->WriteHTML(
            '<table><thead><tr>'
            . '<th style="width:3%">No</th>'
            . '<th style="width:9%">Type</th>'
            . '<th style="width:11%">Kategori</th>'
            . '<th style="width:11%">Subkategori</th>'
            . '<th style="width:20%">Issue Name</th>'
            . '<th style="width:10%">Nama</th>'
            . '<th style="width:8%">Status</th>'
            . '<th style="width:7%">Prioritas</th>'
            . '<th class="nowrap" style="width:8%">Dibuat</th>'
            . '<th class="nowrap" style="width:8%">Selesai</th>'
            . '</tr></thead><tbody>'
        );

        $rowNumber = 1;
        $batchSize = 50;
        $batchHtml = '';
        foreach ($exportRows as $row) {
            $rowClass = $rowNumber % 2 === 0 ? 'even' : 'odd';
            $batchHtml .= '<tr class="' . $rowClass . '">'
                . '<td style="text-align:center">' . $rowNumber . '</td>'
                . '<td>' . purchasingIssuePdfText($row['issue_type'] ?? null) . '</td>'
                . '<td>' . purchasingIssuePdfText($row['category_name'] ?? null) . '</td>'
                . '<td>' . purchasingIssuePdfText($row['sub_name'] ?? null) . '</td>'
                . '<td>' . purchasingIssuePdfText($row['issue_name'] ?? null) . '</td>'
                . '<td>' . purchasingIssuePdfText($row['created_by'] ?? null) . '</td>'
                . '<td style="text-align:center">' . purchasingIssuePdfText($row['status'] ?? null) . '</td>'
                . '<td style="text-align:center">' . purchasingIssuePdfText($row['priority'] ?? null) . '</td>'
                . '<td class="nowrap">' . purchasingIssuePdfDate($row['created_at'] ?? null) . '</td>'
                . '<td class="nowrap">' . purchasingIssuePdfDate($row['tanggal_selesai'] ?? null) . '</td>'
                . '</tr>';
            $rowNumber++;

            if ($rowNumber % $batchSize === 0) {
                $pdf->WriteHTML($batchHtml);
                $batchHtml = '';
            }
        }
        if ($batchHtml !== '') {
            $pdf->WriteHTML($batchHtml);
        }
        if ($exportRows === []) {
            $pdf->WriteHTML('<tr><td colspan="10" style="text-align:center;padding:10px;">Tidak ada issue sesuai filter.</td></tr>');
        }
        $pdf->WriteHTML('</tbody></table>');
        $pdf->SetHTMLFooter('<div style="text-align:right;font-size:8px;">Halaman {PAGENO} dari {nbpg}</div>');
        $pdf->Output(
            ($scope === 'history' ? 'riwayat-issues-purchasing-' : 'daftar-issues-purchasing-') . date('Ymd-His') . '.pdf',
            'I'
        );
    } catch (Throwable $exception) {
        if (isset($dataStatement) && $dataStatement !== false && $dataStatement !== null) {
            sqlsrv_free_stmt($dataStatement);
        }
        $operationId = purchasingIssueOperationId();
        purchasingIssueLogFailure('export_pdf', 'render', $operationId);
        purchasingIssuePdfFail(500, 'PDF tidak dapat dibuat. ID permintaan: ' . $operationId . '.');
    }
}

exportPurchasingIssuesPdf($conn ?? null);
