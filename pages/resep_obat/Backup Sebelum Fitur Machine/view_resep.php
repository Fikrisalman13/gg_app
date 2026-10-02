<?php
// pages/resep_obat/view_resep.php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../koneksi.php';

$resep_id = $_GET['resep_id'] ?? null;
if (!$resep_id) die("ID Missing");

// Fetch Header
$stmt = sqlsrv_query($conn, "SELECT * FROM dbo.resep_obat WHERE id = ?", [$resep_id]);
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$data) die("Data not found");

// Fetch Details
$items = [];
$stmtD = sqlsrv_query($conn, "SELECT * FROM dbo.resep_obat_detail WHERE id_resep = ?", [$resep_id]);
while ($row = sqlsrv_fetch_array($stmtD, SQLSRV_FETCH_ASSOC)) {
    $items[] = $row;
}

// Fetch Limits
$limits = ['min_cost' => null, 'max_cost' => null];
$stmtLim = sqlsrv_query($conn, "SELECT top 1 min_cost, max_cost FROM dbo.resep_config");
if ($stmtLim && $limRow = sqlsrv_fetch_array($stmtLim, SQLSRV_FETCH_ASSOC)) {
    $limits = $limRow;
}

// Calculations
$grandTotal = 0;
$catTotals = [];
$catCFTotals = []; // Add CF totals tracking
foreach ($items as &$item) {
    // Map UOM G/L to GR for display
    if (strtoupper(trim($item['uom'])) === 'G/L') {
        $item['uom_display'] = 'GR';
    } else {
        $item['uom_display'] = $item['uom'];
    }

    $grandTotal += $item['total'];
    
    // Category Grouping
    $cat = trim($item['category'] ?? 'Others');
    if ($cat === '') $cat = 'Others';
    if (!isset($catTotals[$cat])) $catTotals[$cat] = 0;
    $catTotals[$cat] += $item['total'];
    
    // CF Totals per Category
    if (!isset($catCFTotals[$cat])) $catCFTotals[$cat] = 0;
    $catCFTotals[$cat] += (float)($item['cf'] ?? 0);
}
unset($item);

$planQty = $data['plan_qty'] > 0 ? $data['plan_qty'] : 1;
$totalCost = $grandTotal / $planQty;

// Category Costs
$categoryCosts = [];
foreach ($catTotals as $cat => $total) {
    $categoryCosts[$cat] = $total / $planQty;
}

// Display Limits
$dispMax = is_null($limits['max_cost']) ? '-' : 'Rp ' . number_format($limits['max_cost'], 0, ',', '.');

$themeColor = $_SESSION['Theme'] ?? 'primary';

// Build a proxy URL for a stored file path (e.g. /gg_app/uploads/resep_obat/file.pdf)
function proxyUrl(string $path): string {
    $relative = ltrim(str_replace('/gg_app/', '', $path), '/');
    return '/gg_app/pages/resep_obat/serve_file.php?file=' . urlencode($relative);
}
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/sidebar.php'; ?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1>View Resep</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="list_resep.php">Resep</a></li>
                        <li class="breadcrumb-item active">View</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card card-<?= htmlspecialchars($themeColor); ?> card-outline">
                <div class="card-header bg-<?= htmlspecialchars($themeColor); ?> no-print">
                    <h3 class="card-title text-white"><i class="fas fa-file-alt mr-1"></i> Detail Resep Obat</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-default btn-sm" onclick="window.print()">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <a href="list_resep.php" class="btn btn-default btn-sm">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                    </div>
                </div>
                <div class="card-body p-4" style="background-color: white;">
                    
                    <!-- Print Only Styles -->
                    <style>
                        /* Local adjustments for this view */
                        .resep-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        .resep-table th, .resep-table td { border: 1px solid #000 !important; padding: 5px; font-size: 13px; }
                        .bg-blue-light { background-color: #dbebf9 !important; -webkit-print-color-adjust: exact; }
                        .info-label { font-weight: bold; background-color: #f4f6f9; width: 140px; }
                        
                        @media print {
                            .main-footer, .main-header, .main-sidebar, .content-header, .card-header, .no-print { display: none !important; }
                            .content-wrapper, .card { margin: 0 !important; padding: 0 !important; border: none !important; box-shadow: none !important; }
                            .card-body { padding: 0 !important; }
                            body { background-color: white !important; font-size: 11px; }
                            /* Tighten up the header info table */
                            .info-label { width: 110px; }
                            .table-sm td, .table-sm th { padding: 2px 4px !important; }
                            /* Force Background Colors */
                            .bg-blue-light { background-color: #dbebf9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                            .col-md-6 { flex: 0 0 50%; max-width: 50%; }
                            
                            /* Technical Parameters Print Optimization */
                            .row.mt-5 { margin-top: 1rem !important; display: flex !important; }
                            .row.mt-5 .card { border: 1px solid #dee2e6 !important; background: transparent !important; }
                            .row.mt-5 .card-header { padding: 0.5rem !important; }
                            .row.mt-5 .card-body { padding: 0.5rem !important; }
                            .row.mt-5 .col-md-4 { flex: 0 0 33.333% !important; max-width: 33.333% !important; padding: 5px !important; }
                            .row.mt-5 .bg-white { border: 1px solid #eee !important; box-shadow: none !important; margin-bottom: 5px !important; }
                            .row.mt-5 h4, .row.mt-5 .h4 { font-size: 1.2rem !important; }
                            .row.mt-5 .text-xs { font-size: 0.65rem !important; }

                            /* Show PDF print area */
                            #pdf-print-section { display: block !important; }
                            .pdf-print-page { display: block !important; width: 100%; page-break-inside: avoid; }
                        }
                        /* Hide PDF section on screen */
                        #pdf-print-section { display: none; }
                    </style>

                    <!-- Header Info -->
                    <!-- Header Info Split -->
                    <div class="row">
                        <div class="col-md-6">
                             <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="info-label text-left">Kode Grey</td>
                                    <td style="width: 10px;">:</td>
                                    <td><?= $data['kode_grey'] ?? '-' ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label text-left">Kode Warna</td>
                                    <td style="width: 10px;">:</td>
                                    <td><b><?= $data['kode_warna'] ?></b></td>
                                </tr>
                                <tr>
                                    <td class="info-label text-left">Color Name</td>
                                    <td style="width: 10px;">:</td>
                                    <td><?= $data['color_name'] ?? '-' ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label text-left">Description</td>
                                    <td style="width: 10px;">:</td>
                                    <td><?= $data['color_desc'] ?? '-' ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label text-left">Resep Prod Code</td>
                                    <td style="width: 10px;">:</td>
                                    <td><b><?= $data['resep_prod_code'] ?? '-' ?></b></td>
                                </tr>
                                <tr>
                                    <td class="info-label text-left">Resep Prod Name</td>
                                    <td style="width: 10px;">:</td>
                                    <td><?= $data['resep_prod_name'] ?? '-' ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label text-left">No CP</td>
                                    <td style="width: 10px;">:</td>
                                    <td><?= $data['no_cp'] ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label text-left">Lot No</td>
                                    <td style="width: 10px;">:</td>
                                    <td><?= $data['lot_no'] ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label text-left">Plan Qty</td>
                                    <td style="width: 10px;">:</td>
                                    <td><?= number_format($data['plan_qty'], 2, '.', ',') ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless mb-0">

                                <tr>
                                    <td class="info-label">Resep No</td>
                                    <td style="width: 10px;">:</td>
                                    <td><?= $data['resep_no'] ?? '-' ?> Ver <?= $data['resep_seq'] ?? '-' ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label">Resep Date</td>
                                    <td style="width: 10px;">:</td>
                                    <td><?= isset($data['resep_date']) && $data['resep_date'] instanceof DateTime ? $data['resep_date']->format('d-M-Y H:i') : ($data['resep_date'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label">Resep Type</td>
                                    <td style="width: 10px;">:</td>
                                    <td><?= $data['resep_type'] ?? '-' ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label">No CP Resep</td>
                                    <td style="width: 10px;">:</td>
                                    <td><?= $data['no_cp_resep'] ?? '-' ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label">No SO</td>
                                    <td style="width: 10px;">:</td>
                                    <td><?= $data['no_so'] ?? '-' ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label">Routing</td>
                                    <td style="width: 10px;">:</td>
                                    <td><?= !empty($data['rtg_code']) ? (($data['rtg_code'] ?? '') . ' - ' . ($data['rtg_name'] ?? '')) : '-' ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label">Status Desc</td>
                                    <td style="width: 10px;">:</td>
                                    <td><?= $data['status_desc'] ?? '-' ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label">Cus Color</td>
                                    <td style="width: 10px;">:</td>
                                    <td><?= $data['cus_color'] ?? '-' ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label">Weight</td>
                                    <td style="width: 10px;">:</td>
                                    <td><?= number_format($data['weight'], 4, '.', ',') ?></td>
                                </tr>

                                <tr>
                                    <td class="info-label">Vlot</td>
                                    <td style="width: 10px;">:</td>
                                    <td><?= isset($data['vlot']) ? number_format($data['vlot'], 2, '.', ',') : '-' ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <h4 style="margin-top: 25px; margin-bottom: 10px; border-left: 5px solid #007bff; padding-left: 10px; display: flex; align-items: center;">
                        Detail Resep
                    </h4>
                    <table class="resep-table">
                        <thead>
                            <tr class="bg-blue-light">
                                <th class="text-center">Kode</th>
                                <th class="text-center">Name</th>
                                <th class="text-center">Category</th>
                                <th class="text-center">Qty</th>
                                <th class="text-center">Uom</th>
                                <th class="text-center">Cf</th>
                                <th class="text-center">Uom Cf</th>
                                <th class="text-center">Price</th>
                                <th class="text-center">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                     <div style="font-weight: bold;"><?= $item['kode'] ?></div>
                                     <div style="margin-top: 4px;">
                                         <?php if (($item['is_manual'] ?? 0) == 1): ?>
                                             <span style="background-color: #ffc107; color: #fff; padding: 1px 5px; border-radius: 3px; font-size: 0.65rem; font-weight: bold;">MANUAL</span>
                                         <?php else: ?>
                                             <span style="background-color: #28a745; color: #fff; padding: 1px 5px; border-radius: 3px; font-size: 0.65rem; font-weight: bold;">PROINT</span>
                                         <?php endif; ?>
                                     </div>
                                 </td>
                                <td><?= $item['name'] ?></td>
                                <td class="text-center"><?= $item['category'] ?? '-' ?></td>
                                <td class="text-right"><?= number_format($item['receipe'], 4, '.', ',') ?></td>
                                <td class="text-center">
                                    <?= $item['uom_display'] ?>
                                    <?php if ($item['uom_display'] === 'GR'): ?>
                                   <?php endif; ?>
                                </td>
                                <td class="text-right"><?= number_format((float)($item['cf'] ?? 0), 4, '.', ',') ?></td>
                                <td class="text-center"><?= $item['uom_cf'] ?? '-' ?></td>
                                <td class="text-right"><?= 'Rp ' . number_format($item['std_price'], 2, ',', '.') . ($item['price_satuan'] ? ' / ' . $item['price_satuan'] : '') ?></td>
                                <td class="text-right"><?= 'Rp ' . number_format($item['total'], 2, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <!-- Grand Total -->
                            <tr>
                                <td colspan="8" class="text-right font-weight-bold">Grand Total</td>
                                <td class="text-right font-weight-bold bg-blue-light"><?= 'Rp ' . number_format($grandTotal, 2, ',', '.') ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Footer / Summary -->
                    <div class="row mt-4">
                        <div class="col-md-5 offset-md-7">
                            <div class="card shadow-sm border" style="border-radius: 8px; overflow: hidden;">
                                <div class="card-header bg-<?= htmlspecialchars($themeColor); ?> py-2">
                                    <h3 class="card-title text-sm text-white font-weight-bold">
                                        <i class="fas fa-calculator mr-1"></i> Cost Summary Per Meter
                                    </h3>
                                </div>
                                <div class="card-body p-0">
                                    <table class="table table-sm mb-0">
                                        <thead class="text-center text-muted" style="font-size: 0.75rem; background-color: #f8f9fa;">
                                            <tr>
                                                <th class="border-top-0 py-2">Category</th>
                                                <th class="border-top-0 py-2 text-center">Total CF</th>
                                                <th class="border-top-0 py-2 text-right pr-3">Cost / Plan Qty</th>
                                            </tr>
                                        </thead>
                                        <tbody style="font-size: 0.85rem;">
                                            <?php foreach ($categoryCosts as $cat => $cost): ?>
                                            <?php 
                                                $cfVal = $catCFTotals[$cat] ?? 0; 
                                                // Note: We don't check limits against config here because legacy recipes might exceed current config.
                                                // Just display the data.
                                            ?>
                                            <tr>
                                                <td class="pl-3 py-2 font-weight-bold text-muted"><?= $cat ?></td>
                                                <td class="text-center py-2"><?= number_format($cfVal, 2, '.', ',') ?></td>
                                                <td class="text-right pr-3 py-2"><?= 'Rp ' . number_format($cost, 2, ',', '.') ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot style="border-top: 2px solid #dee2e6; background-color: #fff;">
                                            <tr>
                                                <td colspan="2" class="pl-3 py-3 font-weight-bold text-navy" style="font-size: 1rem;">Total Cost</td>
                                                <td class="text-right pr-3 py-3">
                                                    <span class="font-weight-bold text-navy" style="font-size: 1.2rem;">
                                                        <?= 'Rp ' . number_format($totalCost, 2, ',', '.') ?>
                                                    </span>
                                                    <!-- Show Max Limit under text as requested -->
                                                    <?php if(!is_null($limits['max_cost']) && $totalCost > $limits['max_cost']): ?>
                                                        <div class="text-xs text-danger mt-1" style="font-weight:bold;">MAX LIMIT <?= $dispMax ?></div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Machine Parameters Redesign -->
                    <div class="row mt-5">
                        <div class="col-12">
                            <div class="card shadow-none border-0 bg-light" style="border-radius: 12px;">
                                <div class="card-header bg-transparent border-0 pt-4 px-4">
                                    <h5 class="font-weight-bold text-dark mb-0">
                                        <i class="fas fa-cogs mr-2 text-<?= htmlspecialchars($themeColor); ?>"></i> Technical Parameters
                                    </h5>
                                    <p class="text-muted text-sm mb-0">Informasi tambahan parameter mesin dan dokumentasi.</p>
                                </div>
                                <div class="card-body px-4 pb-4">
                                    <div class="row">
                                        <!-- Column 1: Main Specs -->
                                        <div class="col-md-4">
                                            <div class="p-3 mb-3 bg-white rounded shadow-sm border-left border-<?= htmlspecialchars($themeColor); ?>" style="border-left-width: 4px !important;">
                                                <div class="text-xs text-uppercase text-muted font-weight-bold mb-2">Machine Speed</div>
                                                <div class="d-flex align-items-end">
                                                    <span class="h4 mb-0 font-weight-bold text-dark"><?= isset($data['speed']) ? number_format((float)$data['speed'], 2, '.', ',') : '-' ?></span>
                                                    <span class="ml-1 text-muted text-sm pb-1">m/min</span>
                                                </div>
                                            </div>
                                            <div class="p-3 bg-white rounded shadow-sm border-left border-<?= htmlspecialchars($themeColor); ?>" style="border-left-width: 4px !important;">
                                                <div class="text-xs text-uppercase text-muted font-weight-bold mb-2">Temperature</div>
                                                <div class="d-flex align-items-end">
                                                    <span class="h4 mb-0 font-weight-bold text-dark"><?= isset($data['temperature']) ? number_format((float)$data['temperature'], 2, '.', ',') : '-' ?></span>
                                                    <span class="ml-1 text-muted text-sm pb-1">°C</span>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Column 2: Color Values (L*a*b*) -->
                                        <div class="col-md-4">
                                            <div class="bg-white rounded shadow-sm p-3 h-100">
                                                <div class="text-xs text-uppercase text-muted font-weight-bold mb-3">Color Analysis (L*a*b*)</div>
                                                <div class="row text-center">
                                                    <div class="col-4 border-right">
                                                        <div class="text-muted text-xs mb-1">L*</div>
                                                        <div class="font-weight-bold h5 mb-0 text-navy"><?= isset($data['nilai_l']) ? number_format((float)$data['nilai_l'], 2, '.', ',') : '-' ?></div>
                                                    </div>
                                                    <div class="col-4 border-right">
                                                        <div class="text-muted text-xs mb-1">a*</div>
                                                        <div class="font-weight-bold h5 mb-0 text-navy"><?= isset($data['nilai_a']) ? number_format((float)$data['nilai_a'], 2, '.', ',') : '-' ?></div>
                                                    </div>
                                                    <div class="col-4">
                                                        <div class="text-muted text-xs mb-1">b*</div>
                                                        <div class="font-weight-bold h5 mb-0 text-navy"><?= isset($data['nilai_b']) ? number_format((float)$data['nilai_b'], 2, '.', ',') : '-' ?></div>
                                                    </div>
                                                </div>
                                                <div class="mt-3 pt-2 border-top">
                                                    <small class="text-muted font-italic">Lab color space readings</small>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Column 3: Attachment Preview -->
                                        <div class="col-md-4">
                                            <div class="bg-white rounded shadow-sm p-3 h-100">
                                                <div class="text-xs text-uppercase text-muted font-weight-bold mb-3">Attachments</div>
                                                
                                                <!-- Image Attachment -->
                                                <div class="mb-3">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <span class="text-xs font-weight-bold"><i class="fas fa-image mr-1 text-muted"></i> Foto</span>
                                                        <?php if (!empty($data['lampiran_path'])): ?>
                                                            <a href="<?= htmlspecialchars(proxyUrl($data['lampiran_path'])) ?>" target="_blank" class="text-xs no-print">View Full</a>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-center rounded border bg-light d-flex align-items-center justify-content-center" style="height: 100px; overflow: hidden;">
                                                        <?php if (!empty($data['lampiran_path'])): ?>
                                                            <img src="<?= htmlspecialchars(proxyUrl($data['lampiran_path'])) ?>" alt="Lampiran" style="max-width: 100%; max-height: 100%; cursor: pointer;" onclick="window.open('<?= htmlspecialchars(proxyUrl($data['lampiran_path'])) ?>')">
                                                        <?php else: ?>
                                                            <small class="text-muted">No Image</small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <!-- PDF Attachment -->
                                                <div class="pt-2 border-top">
                                                    <div class="text-xs font-weight-bold mb-1"><i class="fas fa-file-pdf mr-1 text-muted"></i> Dokumen PDF</div>
                                                    <?php if (!empty($data['lampiran_pdf_path'])): ?>
                                                        <a href="<?= htmlspecialchars(proxyUrl($data['lampiran_pdf_path'])) ?>" target="_blank" class="btn btn-sm btn-block btn-outline-danger no-print">
                                                            <i class="far fa-file-pdf mr-1"></i> Buka PDF
                                                        </a>
                                                    <?php else: ?>
                                                        <div class="text-center py-2 rounded bg-light border border-dashed">
                                                            <small class="text-muted italic">Tidak ada PDF</small>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<?php if (!empty($data['lampiran_pdf_path'])): ?>
<!-- PDF Print Section: hidden on screen, shown on print after page break -->
<div id="pdf-print-section">
    <div style="page-break-before: always; break-before: always;">
        <div id="pdf-print-canvas-container"></div>
    </div>
</div>

<!-- PDF.js Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>
<script>
(function() {
    const pdfjsLib = window['pdfjs-dist/build/pdf'];
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';

    const pdfPath = <?= json_encode(!empty($data['lampiran_pdf_path']) ? proxyUrl($data['lampiran_pdf_path']) : '') ?>;
    let pdfReady = false;

    async function renderPdf() {
        if (pdfReady || !pdfPath) return;
        const container = document.getElementById('pdf-print-canvas-container');
        try {
            const pdf = await pdfjsLib.getDocument(pdfPath).promise;
            for (let i = 1; i <= pdf.numPages; i++) {
                const page = await pdf.getPage(i);
                const viewport = page.getViewport({ scale: 1.2 });
                const canvas = document.createElement('canvas');
                canvas.height = viewport.height;
                canvas.width  = viewport.width;
                await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
                const img = document.createElement('img');
                img.src = canvas.toDataURL('image/jpeg', 0.92);
                img.className = 'pdf-print-page';
                img.style.cssText = 'display:block;width:100%;margin:0;';
                container.appendChild(img);
            }
            pdfReady = true;
        } catch (err) {
            console.error('PDF render error:', err);
        }
    }

    // Intercept window.print so PDF renders before printing
    const _origPrint = window.print.bind(window);
    window.print = async function () {
        if (!pdfReady) {
            const swalAvail = typeof Swal !== 'undefined';
            if (swalAvail) Swal.fire({ title: 'Menyiapkan PDF...', text: 'Mohon tunggu sebentar.', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });
            await renderPdf();
            if (swalAvail) Swal.close();
        }
        _origPrint();
    };

    // Pre-render on page load so print click feels instant
    document.addEventListener('DOMContentLoaded', renderPdf);
})();
</script>
<?php endif; ?>
