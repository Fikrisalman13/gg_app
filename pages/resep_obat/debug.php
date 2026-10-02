<?php
// pages/resep_obat/debug_lab_production.php
session_start();
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

function fetchDebugRows($conn) {
    $sql = "
        SELECT
            r.id,
            r.no_cp,
            r.cus_color,
            r.kode_warna,
            ISNULL(NULLIF(LTRIM(RTRIM(r.status_resep_lipat)), ''), '-') AS status_resep_lipat,
            CASE
                WHEN r.nilai_l IS NOT NULL
                  OR r.nilai_a IS NOT NULL
                  OR r.nilai_b IS NOT NULL
                  OR NULLIF(LTRIM(RTRIM(r.lampiran_path)), '') IS NOT NULL
                  OR NULLIF(LTRIM(RTRIM(r.lampiran_pdf_path)), '') IS NOT NULL
                THEN 'Ada'
                ELSE 'Tidak Ada'
            END AS lab_status,
            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM dbo.resep_obat_machines m
                    WHERE m.id_resep = r.id
                )
                THEN 'Ada'
                ELSE 'Tidak'
            END AS produksi_status
        FROM dbo.resep_obat r
        WHERE NOT (
            (
                r.nilai_l IS NOT NULL
                OR r.nilai_a IS NOT NULL
                OR r.nilai_b IS NOT NULL
                OR NULLIF(LTRIM(RTRIM(r.lampiran_path)), '') IS NOT NULL
                OR NULLIF(LTRIM(RTRIM(r.lampiran_pdf_path)), '') IS NOT NULL
            )
            AND EXISTS (
                SELECT 1
                FROM dbo.resep_obat_machines m
                WHERE m.id_resep = r.id
            )
        )
        ORDER BY r.created_at DESC, r.id DESC
    ";

    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) {
        throw new RuntimeException(print_r(sqlsrv_errors(), true));
    }

    $rows = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    return $rows;
}

try {
    $rows = fetchDebugRows($conn);
} catch (Throwable $e) {
    http_response_code(500);
    die('<pre>' . htmlspecialchars($e->getMessage()) . '</pre>');
}

if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Debug Lab Produksi');

    $headers = ['No', 'No CP', 'Cust Color', 'Kode Warna', 'Status Resep', 'Lab', 'Produksi'];
    $sheet->fromArray($headers, null, 'A1');

    $rowNum = 2;
    foreach ($rows as $index => $row) {
        $sheet->setCellValue('A' . $rowNum, $index + 1);
        $sheet->setCellValue('B' . $rowNum, $row['no_cp'] ?: '-');
        $sheet->setCellValue('C' . $rowNum, $row['cus_color'] ?: '-');
        $sheet->setCellValue('D' . $rowNum, $row['kode_warna'] ?: '-');
        $sheet->setCellValue('E' . $rowNum, $row['status_resep_lipat'] ?: '-');
        $sheet->setCellValue('F' . $rowNum, $row['lab_status']);
        $sheet->setCellValue('G' . $rowNum, $row['produksi_status']);
        $rowNum++;
    }

    $lastRow = max(1, $rowNum - 1);
    $range = 'A1:G' . $lastRow;

    $sheet->getStyle('A1:G1')->applyFromArray([
        'font' => ['bold' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'EDEDED'],
        ],
    ]);
    $sheet->getStyle($range)->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000'],
            ],
        ],
    ]);
    $sheet->getStyle('A:A')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('F:G')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    foreach (range('A', 'G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $filename = 'debug_lab_produksi_' . date('Ymd_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0"><i class="fas fa-bug mr-2"></i>Debug Lab & Produksi</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="list_resep.php">Master Resep</a></li>
                        <li class="breadcrumb-item active">Debug Lab & Produksi</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-<?= htmlspecialchars($themeColor) ?> card-outline">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title mb-0"><i class="fas fa-table mr-1"></i> Data Master Resep</h3>
                    <div class="card-tools">
                        <a href="?export=excel" class="btn btn-success btn-sm">
                            <i class="fas fa-file-excel mr-1"></i> Export Excel
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-info py-2">
                        Menampilkan data resep yang belum lengkap Lab Data atau Production Data.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm table-hover">
                            <thead class="thead-light text-center">
                                <tr>
                                    <th style="width:70px;">No</th>
                                    <th>No CP</th>
                                    <th>Cust Color</th>
                                    <th>Kode Warna</th>
                                    <th>Status Resep</th>
                                    <th style="width:120px;">Lab</th>
                                    <th style="width:120px;">Produksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rows)): ?>
                                    <tr><td colspan="7" class="text-center text-muted">Data tidak ditemukan.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($rows as $i => $row): ?>
                                        <tr>
                                            <td class="text-right"><?= $i + 1 ?></td>
                                            <td><?= htmlspecialchars($row['no_cp'] ?: '-') ?></td>
                                            <td><?= htmlspecialchars($row['cus_color'] ?: '-') ?></td>
                                            <td><?= htmlspecialchars($row['kode_warna'] ?: '-') ?></td>
                                            <td><?= htmlspecialchars($row['status_resep_lipat'] ?: '-') ?></td>
                                            <td class="text-center">
                                                <span class="badge badge-<?= $row['lab_status'] === 'Ada' ? 'success' : 'danger' ?>">
                                                    <?= htmlspecialchars($row['lab_status']) ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-<?= $row['produksi_status'] === 'Ada' ? 'success' : 'danger' ?>">
                                                    <?= htmlspecialchars($row['produksi_status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include '../../includes/footer.php'; ?>
