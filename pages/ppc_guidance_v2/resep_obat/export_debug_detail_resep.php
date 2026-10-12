<?php
session_start();

if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    exit('Access denied');
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/debug_detail_resep_compare.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

set_time_limit(600);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mode 1: Ringkasan Cepat (dari baris yang sudah diaudit di client)
    $payload = json_decode(file_get_contents('php://input'), true);
    $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];
    if (!$rows || count($rows) > 10000) {
        http_response_code(422);
        exit('Hasil audit kosong atau melebihi batas 10.000 baris.');
    }

    $book = new Spreadsheet();
    $sum = $book->getActiveSheet();
    $sum->setTitle('Ringkasan Audit');

    $headers = [
        'ID Lokal',
        'No CP',
        'Routing Lokal',
        'Sumber Resep',
        'Status Resep',
        'Dibuat',
        'Bon ProInt',
        'Status Audit',
        'Keterangan Detail',
    ];
    $sum->fromArray($headers, null, 'A1');

    $sr = 2;
    foreach ($rows as $r) {
        $routingDesc = '';
        if (!empty($r['tables'])) {
            $routingDesc = implode(' | ', array_map(function ($t) {
                return "T{$t['table_index']}: {$t['rtg_code']} - {$t['rtg_name']}";
            }, $r['tables']));
        } else {
            $routingDesc = trim(($r['rtg_code'] ?? '') . ' - ' . ($r['rtg_name'] ?? ''), ' -');
        }

        $sum->fromArray([
            (int) ($r['id'] ?? 0),
            $r['no_cp'] ?? '',
            $routingDesc ?: '-',
            (int) ($r['is_manual'] ?? 0) === 1 ? 'MANUAL' : 'PROINT',
            $r['status_resep_lipat'] ?? '-',
            $r['created_at'] ?? '',
            $r['bonno'] ?? '-',
            $r['status'] ?? '',
            $r['status_detail'] ?? '',
        ], null, 'A' . $sr++);
    }

    $sum->getStyle('A1:I1')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $sum->freezePane('A2');
    $sum->setAutoFilter('A1:I' . ($sr - 1));
    foreach (range('A', 'I') as $c) {
        $sum->getColumnDimension($c)->setAutoSize(true);
    }

    $filename = 'Audit_Ringkasan_Resep_V2_' . date('Ymd_His') . '.xlsx';
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    (new Xlsx($book))->save('php://output');
    exit;
}

// Mode 2: Ekspor Beserta Detail (Server-Side Batch Execution)
$filters = [
    'cp' => trim((string) ($_GET['cp'] ?? '')),
    'source' => strtoupper(trim((string) ($_GET['source'] ?? ''))),
    'start' => trim((string) ($_GET['start'] ?? '')),
    'end' => trim((string) ($_GET['end'] ?? '')),
    'route' => trim((string) ($_GET['route'] ?? '')),
    'label_jual' => trim((string) ($_GET['label_jual'] ?? '')),
];

$book = new Spreadsheet();

// Sheet 1: Ringkasan
$sum = $book->getActiveSheet();
$sum->setTitle('Ringkasan Resep');
$headersSummary = [
    'ID Lokal',
    'No CP',
    'Label Jual',
    'Routing Lokal',
    'Sumber',
    'Status Resep',
    'Dibuat',
    'Bon ProInt',
    'Status Audit',
    'Keterangan Detail',
];
$sum->fromArray($headersSummary, null, 'A1');

// Sheet 2: Perbandingan Detail
$detail = $book->createSheet();
$detail->setTitle('Perbandingan Detail');
$headersDetail = [
    'ID Lokal',
    'No CP',
    'Tabel ke-',
    'Routing Tabel',
    'Bon ProInt',
    'Kode ProInt',
    'Nama Material',
    'Kategori',
    'Qty Lokal',
    'Qty ProInt',
    'Selisih Qty',
    'CF Lokal',
    'CF ProInt',
    'Selisih CF',
    'UOM Lokal',
    'UOM ProInt',
    'Status Baris',
    'Keterangan Selisih',
];
$detail->fromArray($headersDetail, null, 'A1');

$sr = 2;
$dr = 2;
$offset = 0;
$limit = 50;

do {
    $batch = auditLocalRows($filters, $offset, $limit);
    foreach ($batch['rows'] as $r) {
        $a = auditRecipe($r, null, true);

        // Routing summary string
        $routingSummary = '';
        if (!empty($r['routes_summary'])) {
            $routingSummary = implode(' | ', array_map(function ($rs) {
                return "T{$rs['table_index']}: {$rs['rtg_code']}";
            }, $r['routes_summary']));
        } else {
            $routingSummary = trim(($r['rtg_code'] ?? '') . ' - ' . ($r['rtg_name'] ?? ''), ' -');
        }

        // Bon summary
        $bonSummary = implode(', ', array_unique(array_filter(array_map(function ($t) {
            return $t['selected_bon']['bonno'] ?? $t['local_bonno'] ?? '';
        }, $a['tables'] ?? []))));

        // Write row to Sheet 1
        $sum->fromArray([
            (int) $r['id'],
            $r['no_cp'] ?? '',
            $r['label_jual'] ?? '-',
            $routingSummary ?: '-',
            (int) $r['is_manual'] === 1 ? 'MANUAL' : 'PROINT',
            $r['status_resep_lipat'] ?? '-',
            $r['created_at'] ?? '',
            $bonSummary ?: '-',
            $a['status'],
            $a['status_detail'],
        ], null, 'A' . $sr++);

        // Write rows to Sheet 2
        if (empty($a['tables'])) {
            $detail->fromArray([
                (int) $r['id'],
                $r['no_cp'] ?? '',
                'Tabel 1',
                $routingSummary ?: '-',
                '-',
                '',
                '',
                '',
                null,
                null,
                null,
                null,
                null,
                null,
                '',
                '',
                $a['status'],
                $a['status_detail'],
            ], null, 'A' . $dr++);
        } else {
            foreach ($a['tables'] as $t) {
                $tIdxLabel = 'Tabel ' . ($t['table_index'] ?? 1);
                $tRouteLabel = trim(($t['rtg_code'] ?? '') . ' - ' . ($t['rtg_name'] ?? ''), ' -');
                $tBonNo = $t['selected_bon']['bonno'] ?? ($t['local_bonno'] ?: '-');

                if (empty($t['details'])) {
                    $detail->fromArray([
                        (int) $r['id'],
                        $r['no_cp'] ?? '',
                        $tIdxLabel,
                        $tRouteLabel,
                        $tBonNo,
                        '',
                        '',
                        '',
                        null,
                        null,
                        null,
                        null,
                        null,
                        null,
                        '',
                        '',
                        $t['status'],
                        $t['status_detail'],
                    ], null, 'A' . $dr++);
                } else {
                    foreach ($t['details'] as $x) {
                        $l = $x['local'] ?? [];
                        $p = $x['proint'] ?? [];

                        $qtyDiff = (isset($l['receipe']) && isset($p['qty']))
                            ? (float) $l['receipe'] - (float) $p['qty']
                            : null;

                        $cfDiff = (isset($l['cf']) && isset($p['cf']))
                            ? (float) $l['cf'] - (float) $p['cf']
                            : null;

                        $detail->fromArray([
                            (int) $r['id'],
                            $r['no_cp'] ?? '',
                            $tIdxLabel,
                            $tRouteLabel,
                            $tBonNo,
                            $x['kode'],
                            $l['name'] ?? $p['name'] ?? '',
                            $l['category'] ?? $p['category'] ?? '',
                            isset($l['receipe']) ? (float) $l['receipe'] : null,
                            isset($p['qty']) ? (float) $p['qty'] : null,
                            $qtyDiff,
                            isset($l['cf']) ? (float) $l['cf'] : null,
                            isset($p['cf']) ? (float) $p['cf'] : null,
                            $cfDiff,
                            $l['uom'] ?? '',
                            $p['uom'] ?? '',
                            $x['status'],
                            implode(', ', $x['reasons'] ?? []),
                        ], null, 'A' . $dr++);
                    }
                }
            }
        }
    }
    $offset += count($batch['rows']);
} while ($offset < $batch['total'] && count($batch['rows']) > 0);

// Style headers
foreach ([$sum, $detail] as $sheet) {
    $last = $sheet->getHighestColumn();
    $sheet->getStyle('A1:' . $last . '1')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet->freezePane('A2');
    $sheet->setAutoFilter('A1:' . $last . $sheet->getHighestRow());
    foreach (range('A', $last) as $c) {
        $sheet->getColumnDimension($c)->setAutoSize(true);
    }
}

$filename = 'Audit_Detail_Resep_V2_' . date('Ymd_His') . '.xlsx';
while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
(new Xlsx($book))->save('php://output');
exit;
