<?php
// pages/resep_obat/view_resep.php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/includes/resep_config.php';

$showProIntMetadata = showResepPpcGuidanceProIntMetadata($conn);

$resep_id = $_GET['resep_id'] ?? null;
if (!$resep_id)
    die("ID Missing");

// Fetch Header
$stmt = sqlsrv_query($conn, "SELECT * FROM dbo.resep_obat WHERE id = ?", [$resep_id]);
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$data)
    die("Data not found");

// Fetch Details
$items = [];
$stmtD = sqlsrv_query($conn, "SELECT * FROM dbo.resep_obat_detail WHERE id_resep = ?", [$resep_id]);
while ($row = sqlsrv_fetch_array($stmtD, SQLSRV_FETCH_ASSOC)) {
    $items[] = $row;
}

// Fetch Machines (Multi-Machine Support)
$machines = [];
$stmtM = sqlsrv_query($conn, "SELECT * FROM dbo.resep_obat_machines WHERE id_resep = ? ORDER BY id ASC", [$resep_id]);
if ($stmtM) {
    while ($rowM = sqlsrv_fetch_array($stmtM, SQLSRV_FETCH_ASSOC)) {
        $machines[] = $rowM;
    }
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
    if ($cat === '')
        $cat = 'Others';
    if (!isset($catTotals[$cat]))
        $catTotals[$cat] = 0;
    $catTotals[$cat] += $item['total'];

    // CF Totals per Category
    if (!isset($catCFTotals[$cat]))
        $catCFTotals[$cat] = 0;
    $catCFTotals[$cat] += (float) ($item['cf'] ?? 0);
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

// ============================
// Resolve current user's role
// ============================
$currentUser = $_SESSION['UserName'] ?? '';
$userRole = null;
$userGroupId = $_SESSION['GroupId'] ?? 0;

$stmtRole = sqlsrv_query(
    $conn,
    "SELECT g.role_type
     FROM dbo.resep_obat_group_members m
     INNER JOIN dbo.resep_obat_groups g ON m.group_id = g.id
     WHERE m.username = ?",
    [$currentUser]
);
if ($stmtRole && ($rRole = sqlsrv_fetch_array($stmtRole, SQLSRV_FETCH_ASSOC))) {
    $userRole = $rRole['role_type']; // 'LAB', 'PRODUCTION', or 'BOTH'
}

// Logic:
// 1. Admin (GroupId 1) has full access and sees prices.
// 2. Specialized Roles (LAB, PRODUCTION, BOTH) hide prices.
// 3. Regular users (no Role, no GroupId 1) see prices (if applicable) and can edit both (default legacy behavior).

$isAdmin = ($userGroupId == 1);
$hidePrice = (!$isAdmin && $userRole !== null);

$canUpdateLab = ($isAdmin || $userRole === 'LAB' || $userRole === 'BOTH');
$canUpdateProduction = ($isAdmin || $userRole === 'PRODUCTION' || $userRole === 'BOTH');

// Build a proxy URL for a stored file path (e.g. /gg_app/uploads/resep_obat/file.pdf)
function proxyUrl(string $path): string
{
    $relative = ltrim(str_replace('/gg_app/', '', $path), '/');
    return '/gg_app/pages/resep_obat/serve_file.php?file=' . urlencode($relative);
}
?>
<?php include '../../../includes/header.php'; ?>
<?php include '../../../includes/sidebar.php'; ?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">
                        <i class="fas fa-file-medical mr-2"></i>Detail Resep Obat
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="list_resep.php">Resep Obat</a></li>
                        <li class="breadcrumb-item active">Detail</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <!-- Print Only Styles -->
            <style>
                /* Local adjustments for this view */
                .resep-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 20px;
                }

                .resep-table th,
                .resep-table td {
                    border: 1px solid #000 !important;
                    padding: 5px;
                    font-size: 13px;
                }

                .bg-blue-light {
                    background-color: #dbebf9 !important;
                    -webkit-print-color-adjust: exact;
                }

                .info-label {
                    font-weight: bold;
                    background-color: #f4f6f9;
                    width: 140px;
                }

                @media screen {
                    .card-header {
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        flex-wrap: wrap;
                        gap: .5rem;
                    }

                    .card-header .card-tools {
                        display: flex;
                        align-items: center;
                        flex-wrap: wrap;
                        gap: .25rem;
                        margin-left: auto;
                    }

                    .card-header .card-title {
                        flex: 0 1 auto;
                    }

                    .detail-info-table th {
                        background: #f4f6f9;
                        font-weight: 600;
                        text-align: left;
                        vertical-align: middle;
                        width: 15%;
                        white-space: nowrap;
                    }

                    .detail-info-table td {
                        vertical-align: middle;
                    }

                    .resep-screen-table {
                        margin-top: 0;
                    }

                    .resep-screen-table th,
                    .resep-screen-table td {
                        border-color: #dee2e6 !important;
                        font-size: .875rem;
                        vertical-align: middle;
                    }

                    .resep-screen-table thead th {
                        background: #f4f6f9 !important;
                        color: #495057;
                        font-weight: 600;
                        white-space: nowrap;
                    }

                    .source-badge {
                        display: inline-block;
                        font-size: .68rem;
                        font-weight: 700;
                        padding: .15rem .45rem;
                        border-radius: 4px;
                        letter-spacing: .02em;
                    }

                    .source-badge-manual {
                        background: #ffc107;
                        color: #1f2d3d;
                    }

                    .source-badge-proint {
                        background: #28a745;
                        color: #fff;
                    }

                    .section-card {
                        margin-bottom: 1rem;
                    }

                    .section-card .card-header {
                        padding: .65rem 1rem;
                    }

                    .section-card .card-title {
                        margin-bottom: 0;
                    }

                    .sub-panel {
                        background: #fff;
                        border: 1px solid #dee2e6;
                        border-radius: .25rem;
                        padding: 1rem;
                        height: 100%;
                    }

                    .sub-panel-title {
                        color: #6c757d;
                        font-size: .75rem;
                        font-weight: 700;
                        letter-spacing: .03em;
                        margin-bottom: .75rem;
                        text-transform: uppercase;
                    }

                    .machine-card {
                        border: 1px solid #dee2e6;
                        border-radius: .25rem;
                        overflow: hidden;
                        height: 100%;
                    }

                    .machine-card-header {
                        background: #f8f9fa;
                        border-bottom: 1px solid #dee2e6;
                        padding: .65rem 1rem;
                    }

                    .metric-box {
                        background: #f8f9fa;
                        border-left: 3px solid #ffc107;
                        border-radius: .25rem;
                        padding: .6rem .75rem;
                        height: 100%;
                    }

                    .metric-label {
                        color: #6c757d;
                        font-size: .7rem;
                        font-weight: 700;
                        text-transform: uppercase;
                    }

                    .metric-value {
                        color: #212529;
                        font-weight: 700;
                    }

                    .temperature-grid .col-6,
                    .temperature-grid .col-md-3 {
                        margin-bottom: .5rem;
                    }

                    .empty-state {
                        padding: 2rem;
                        text-align: center;
                        color: #6c757d;
                        font-style: italic;
                    }
                }

                @media print {

                    .main-footer,
                    .main-header,
                    .main-sidebar,
                    .content-header,
                    .card-header,
                    .no-print {
                        display: none !important;
                    }

                    .detail-info-table,
                    .detail-info-table th,
                    .detail-info-table td {
                        border: none !important;
                        padding-left: 0 !important;
                        padding-right: 0 !important;
                        background: transparent !important;
                    }

                    .content-wrapper,
                    .card {
                        margin: 0 !important;
                        padding: 0 !important;
                        border: none !important;
                        box-shadow: none !important;
                    }

                    .card-body {
                        padding: 0 !important;
                    }

                    /* ===== ENHANCED BASE FONT SIZE ===== */
                    body {
                        background-color: white !important;
                        font-size: 14px !important;
                        line-height: 1.5 !important;
                    }

                    /* ===== PRINT TITLE ===== */
                    .d-print-block h4 {
                        font-size: 1.6rem !important;
                        font-weight: bold !important;
                        margin-bottom: 15px !important;
                    }

                    /* ===== HEADER INFO TABLE ===== */
                    .info-label {
                        width: 140px !important;
                        font-size: 14px !important;
                        font-weight: 600 !important;
                    }

                    .table-sm td,
                    .table-sm th {
                        padding: 6px 8px !important;
                        font-size: 14px !important;
                        line-height: 1.6 !important;
                    }

                    /* Force Background Colors */
                    .bg-blue-light {
                        background-color: #dbebf9 !important;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }

                    .col-md-6 {
                        flex: 0 0 50%;
                        max-width: 50%;
                    }

                    /* ===== SECTION HEADERS ===== */
                    h4,
                    .h4 {
                        font-size: 1.4rem !important;
                        font-weight: bold !important;
                        margin-top: 25px !important;
                        margin-bottom: 12px !important;
                    }

                    /* ===== RESEP TABLE (MAIN TABLE) ===== */
                    .resep-table {
                        font-size: 13px !important;
                    }

                    .resep-table th {
                        font-size: 14px !important;
                        font-weight: 600 !important;
                        padding: 8px 6px !important;
                    }

                    .resep-table td {
                        font-size: 13px !important;
                        padding: 7px 6px !important;
                        line-height: 1.5 !important;
                    }

                    /* Badges in table */
                    .resep-table td span {
                        font-size: 0.7rem !important;
                        padding: 2px 6px !important;
                    }

                    /* Grand Total Row */
                    .resep-table tbody tr:last-child td {
                        font-size: 15px !important;
                        font-weight: 700 !important;
                    }

                    /* ===== COST SUMMARY SECTION ===== */
                    .cost-summary-header,
                    .cost-summary-header *,
                    .cost-summary-header .card-title,
                    .cost-summary-header .text-white {
                        color: #111827 !important;
                        text-shadow: none !important;
                        font-size: 15px !important;
                        font-weight: bold !important;
                    }

                    .cost-summary-header {
                        background-color: #f3f4f6 !important;
                        border: 1px solid #9ca3af !important;
                        border-bottom: 2px solid #4b5563 !important;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                        padding: 10px 15px !important;
                    }

                    /* Cost summary table */
                    .cost-summary-header~.card-body table thead th {
                        font-size: 13px !important;
                        padding: 8px 10px !important;
                    }

                    .cost-summary-header~.card-body table tbody td {
                        font-size: 14px !important;
                        padding: 8px 10px !important;
                    }

                    .cost-summary-header~.card-body table tfoot td {
                        font-size: 15px !important;
                        padding: 12px 10px !important;
                    }

                    .cost-summary-header~.card-body table tfoot td span {
                        font-size: 1.3rem !important;
                    }

                    /* Technical Parameters Print Optimization */
                    .row.mt-5,
                    .row.mt-4 {
                        margin-top: 1.5rem !important;
                        display: block !important;
                    }

                    .row.mt-5 .card,
                    .row.mt-4 .card {
                        border: 1px solid #dee2e6 !important;
                        background: transparent !important;
                        display: block !important;
                    }

                    .row.mt-5 .card-header,
                    .row.mt-4 .card-header {
                        display: block !important;
                        padding: 0.75rem 1rem !important;
                        border-bottom: 1px solid #dee2e6 !important;
                        background-color: #f8f9fa !important;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }

                    .row.mt-5 .card-header h5,
                    .row.mt-4 .card-header h5 {
                        font-size: 1.3rem !important;
                        font-weight: bold !important;
                    }

                    .row.mt-5 .card-header p,
                    .row.mt-4 .card-header p {
                        font-size: 13px !important;
                    }

                    .row.mt-5 .card-body,
                    .row.mt-4 .card-body {
                        display: block !important;
                        padding: 1rem !important;
                        font-size: 14px !important;
                    }

                    /* Grid adjustments for print */
                    .row.mt-5 .col-md-6,
                    .row.mt-4 .col-md-6 {
                        flex: 0 0 50% !important;
                        max-width: 50% !important;
                        float: left !important;
                    }

                    .row.mt-5 .bg-white,
                    .row.mt-4 .bg-white {
                        border: 1px solid #eee !important;
                        box-shadow: none !important;
                        margin-bottom: 10px !important;
                    }

                    .row.mt-5 h4,
                    .row.mt-5 .h4,
                    .row.mt-4 h4,
                    .row.mt-4 .h4 {
                        font-size: 1.25rem !important;
                    }

                    /* Labels in production/lab sections */
                    .row.mt-5 label,
                    .row.mt-4 label {
                        font-size: 13px !important;
                        font-weight: 600 !important;
                    }

                    /* Values in production/lab sections */
                    .row.mt-5 .form-control-plaintext,
                    .row.mt-4 .form-control-plaintext,
                    .row.mt-5 p,
                    .row.mt-4 p {
                        font-size: 14px !important;
                    }

                    .row.mt-5 .text-xs,
                    .row.mt-4 .text-xs {
                        font-size: 0.8rem !important;
                    }

                    .row.mt-5 .p-3,
                    .row.mt-4 .p-3 {
                        padding: 0.75rem !important;
                    }

                    /* ===== SPACING IMPROVEMENTS ===== */
                    .row {
                        margin-bottom: 10px !important;
                    }

                    hr {
                        border-top: 2px solid #ccc !important;
                    }

                    /* ===== PAGE BREAK CONTROLS ===== */
                    .row.mt-5,
                    .row.mt-4 {
                        page-break-inside: avoid !important;
                    }

                    /* Production Data selalu mulai di halaman baru,
                       tapi mesin-mesin berikutnya tetap mengalir normal */
                    #print-production-section {
                        break-before: page;
                        page-break-before: always;
                    }

                    .machine-card {
                        break-inside: avoid;
                        page-break-inside: avoid;
                    }

                    .machine-card-header {
                        break-after: avoid;
                        page-break-after: avoid;
                    }

                    .resep-table {
                        page-break-inside: auto !important;
                    }

                    .resep-table tr {
                        page-break-inside: avoid !important;
                        page-break-after: auto !important;
                    }

                    /* Show PDF print area */
                    #pdf-print-section {
                        display: block !important;
                    }

                    .pdf-print-page {
                        display: block !important;
                        width: 100%;
                        page-break-inside: avoid;
                    }
                }

                /* Hide PDF section on screen */
                #pdf-print-section {
                    display: none;
                }
            </style>

            <!-- Header Info -->

            <!-- Print-only Title -->
            <div class="d-none d-print-block text-center mb-3">
                <h4 class="font-weight-bold" style="font-size: 1.2rem; margin-bottom: 10px;">Detail Resep Obat</h4>
                <hr style="border-top: 1px solid #ccc; margin-top: 5px; margin-bottom: 15px;">
            </div>

            <!-- Header Info Split -->
            <div class="card section-card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor); ?> text-white py-2">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-info-circle mr-1"></i> Info Resep
                        <small class="text-white-50 ml-2">Resep No <?= $data['resep_no'] ?? '-' ?> Ver.
                            <?= $data['resep_seq'] ?? '-' ?></small>
                    </h3>
                    <div class="card-tools no-print">
                        <?php if ($canUpdateLab): ?>
                            <button type="button" class="btn btn-light btn-sm mr-1" data-toggle="modal"
                                data-target="#modalLabUpdate">
                                <i class="fas fa-flask mr-1"></i> Update Lab
                            </button>
                        <?php endif; ?>
                        <?php if ($canUpdateProduction): ?>
                            <button type="button" class="btn btn-warning btn-sm mr-1" data-toggle="modal"
                                data-target="#modalProdUpdate">
                                <i class="fas fa-cog mr-1"></i> Update Produksi
                            </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-default btn-sm mr-1" onclick="window.print()">
                            <i class="fas fa-print mr-1"></i> Print
                        </button>
                        <a href="list_resep.php" class="btn btn-default btn-sm">
                            <i class="fas fa-arrow-left mr-1"></i> Kembali
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-bordered table-sm detail-info-table mb-0">
                                <tr>
                                    <td class="info-label text-left">Kode Grey</td>
                                    <td><?= $data['kode_grey'] ?? '-' ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label text-left">Mesin</td>
                                    <td><?= $data['mesin'] ?? '-' ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label text-left">Kode Warna</td>
                                    <td><b><?= $data['kode_warna'] ?></b></td>
                                </tr>
                                <tr>
                                    <td class="info-label text-left">Color Name</td>
                                    <td><?= $data['color_name'] ?? '-' ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label text-left">Description</td>
                                    <td><?= $data['color_desc'] ?? '-' ?></td>
                                </tr>
                                <?php if ($showProIntMetadata): ?>
                                <tr>
                                    <td class="info-label text-left">Resep Prod Code</td>
                                    <td><b><?= $data['resep_prod_code'] ?? '-' ?></b></td>
                                </tr>
                                <tr>
                                    <td class="info-label text-left">Resep Prod Name</td>
                                    <td><?= $data['resep_prod_name'] ?? '-' ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td class="info-label text-left">No CP</td>
                                    <td><?= $data['no_cp'] ?></td>
                                </tr>
                                <?php if ($showProIntMetadata): ?>
                                <tr>
                                    <td class="info-label text-left">Lot No</td>
                                    <td><?= $data['lot_no'] ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label text-left">Plan Qty</td>
                                    <td><?= number_format($data['plan_qty'], 2, '.', ',') ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-bordered table-sm detail-info-table mb-0">
                                <?php if (!$showProIntMetadata): ?>
                                <tr>
                                    <td class="info-label">Lot No</td>
                                    <td><?= $data['lot_no'] ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label">Plan Qty</td>
                                    <td><?= number_format($data['plan_qty'], 2, '.', ',') ?></td>
                                </tr>
                                <?php endif; ?>

                                <?php if ($showProIntMetadata): ?>
                                <tr>
                                    <td class="info-label">Resep No</td>
                                    <td><?= $data['resep_no'] ?? '-' ?> Ver <?= $data['resep_seq'] ?? '-' ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label">Resep Date</td>
                                    <td><?= isset($data['resep_date']) && $data['resep_date'] instanceof DateTime ? $data['resep_date']->format('d-M-Y H:i') : ($data['resep_date'] ?? '-') ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="info-label">Resep Type</td>
                                    <td><?= $data['resep_type'] ?? '-' ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label">No CP Resep</td>
                                    <td><?= $data['no_cp_resep'] ?? '-' ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label">No SO</td>
                                    <td><?= $data['no_so'] ?? '-' ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label">Routing</td>
                                    <td><?= !empty($data['rtg_code']) ? (($data['rtg_code'] ?? '') . ' - ' . ($data['rtg_name'] ?? '')) : '-' ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="info-label">Status Desc</td>
                                    <td><?= $data['status_desc'] ?? '-' ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td class="info-label">Status Resep</td>
                                    <td><?= $data['status_resep_lipat'] ?? '-' ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label">Cus Color</td>
                                    <td><?= $data['cus_color'] ?? '-' ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label">Weight</td>
                                    <td><?= number_format($data['weight'], 4, '.', ',') ?></td>
                                </tr>

                                <tr>
                                    <td class="info-label">Vlot</td>
                                    <td><?= isset($data['vlot']) ? number_format($data['vlot'], 2, '.', ',') : '-' ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="card section-card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor); ?> text-white">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-list mr-1"></i> Detail Resep
                    </h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="resep-table table table-bordered table-sm mb-0 resep-screen-table">
                            <thead>
                                <tr class="bg-blue-light">
                                    <th class="text-center">Kode</th>
                                    <th class="text-center">Name</th>
                                    <th class="text-center">Category</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-center">Uom</th>
                                    <th class="text-center">Cf</th>
                                    <th class="text-center">Uom Cf</th>
                                    <?php if (!$hidePrice): ?>
                                        <th class="text-center">Price</th>
                                        <th class="text-center">Total</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: bold;"><?= $item['kode'] ?></div>
                                            <div style="margin-top: 4px;">
                                                <?php if (($item['is_manual'] ?? 0) == 1): ?>
                                                    <span
                                                        style="background-color: #ffc107; color: #fff; padding: 1px 5px; border-radius: 3px; font-size: 0.65rem; font-weight: bold;">MANUAL</span>
                                                <?php else: ?>
                                                    <span
                                                        style="background-color: #28a745; color: #fff; padding: 1px 5px; border-radius: 3px; font-size: 0.65rem; font-weight: bold;">PROINT</span>
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
                                        <td class="text-right"><?= number_format((float) ($item['cf'] ?? 0), 4, '.', ',') ?>
                                        </td>
                                        <td class="text-center"><?= $item['uom_cf'] ?? '-' ?></td>
                                        <?php if (!$hidePrice): ?>
                                            <td class="text-right">
                                                <?= 'Rp ' . number_format($item['std_price'], 2, ',', '.') . ($item['price_satuan'] ? ' / ' . $item['price_satuan'] : '') ?>
                                            </td>
                                            <td class="text-right"><?= 'Rp ' . number_format($item['total'], 2, ',', '.') ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>

                                <!-- Grand Total -->
                                <?php if (!$hidePrice): ?>
                                    <tr>
                                        <td colspan="8" class="text-right font-weight-bold">Grand Total</td>
                                        <td class="text-right font-weight-bold bg-blue-light">
                                            <?= 'Rp ' . number_format($grandTotal, 2, ',', '.') ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Footer / Summary -->
            <?php if (!$hidePrice): ?>
                <div class="row mt-3">
                    <div class="col-md-5 offset-md-7">
                        <div class="card section-card">
                            <div
                                class="card-header cost-summary-header bg-<?= htmlspecialchars($themeColor); ?> text-white">
                                <h3 class="card-title mb-0 text-white font-weight-bold">
                                    <i class="fas fa-calculator mr-1"></i> Cost Summary Per Meter
                                </h3>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-bordered table-sm mb-0 resep-screen-table">
                                    <thead class="text-center text-muted"
                                        style="font-size: 0.75rem; background-color: #f8f9fa;">
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
                                                <td class="text-center py-2"><?= number_format($cfVal, 2, '.', ',') ?>
                                                </td>
                                                <td class="text-right pr-3 py-2">
                                                    <?= 'Rp ' . number_format($cost, 2, ',', '.') ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot style="border-top: 2px solid #dee2e6; background-color: #fff;">
                                        <tr>
                                            <td colspan="2" class="pl-3 py-3 font-weight-bold text-navy"
                                                style="font-size: 1rem;">Total Cost</td>
                                            <td class="text-right pr-3 py-3">
                                                <span class="font-weight-bold text-navy" style="font-size: 1.2rem;">
                                                    <?= 'Rp ' . number_format($totalCost, 2, ',', '.') ?>
                                                </span>
                                                <!-- Show Max Limit under text as requested -->
                                                <?php if (!is_null($limits['max_cost']) && $totalCost > $limits['max_cost']): ?>
                                                    <div class="text-xs text-danger mt-1" style="font-weight:bold;">MAX
                                                        LIMIT <?= $dispMax ?></div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- SECTION: Lab Data -->
            <div class="row mt-3">
                <div class="col-12">
                    <div class="card section-card">
                        <div class="card-header bg-<?= htmlspecialchars($themeColor); ?> text-white">
                            <h3 class="card-title mb-0">
                                <i class="fas fa-flask mr-1"></i> Lab Data
                            </h3>
                        </div>
                        <div class="card-body">
                            <p class="text-muted text-sm mb-3">Informasi tambahan hasil analisa laboratorium dan
                                dokumentasi.</p>
                            <div class="row">
                                <!-- Sub-column 1: Color Analysis -->
                                <div class="col-md-6 border-right">
                                    <div class="sub-panel">
                                        <div class="sub-panel-title">
                                            <i class="fas fa-chart-pie mr-1 text-muted"></i> Color Analysis (L*a*b*)
                                        </div>
                                        <div class="row text-center my-2">
                                            <div class="col-4 border-right">
                                                <div class="text-muted text-xs mb-1">L*</div>
                                                <div class="font-weight-bold h4 mb-0 text-navy">
                                                    <?= isset($data['nilai_l']) ? number_format((float) $data['nilai_l'], 2, '.', ',') : '-' ?>
                                                </div>
                                            </div>
                                            <div class="col-4 border-right">
                                                <div class="text-muted text-xs mb-1">a*</div>
                                                <div class="font-weight-bold h4 mb-0 text-navy">
                                                    <?= isset($data['nilai_a']) ? number_format((float) $data['nilai_a'], 2, '.', ',') : '-' ?>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="text-muted text-xs mb-1">b*</div>
                                                <div class="font-weight-bold h4 mb-0 text-navy">
                                                    <?= isset($data['nilai_b']) ? number_format((float) $data['nilai_b'], 2, '.', ',') : '-' ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-3 pt-2 text-center">
                                            <small class="text-muted font-italic">Lab color space readings from
                                                QC</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Sub-column 2: Attachments -->
                                <div class="col-md-6">
                                    <div class="sub-panel">
                                        <div class="sub-panel-title">
                                            <i class="fas fa-paperclip mr-1 text-muted"></i> Attachments
                                        </div>
                                        <div class="row">
                                            <div class="col-6 border-right">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="text-xs font-weight-bold">Foto</span>
                                                    <?php if (!empty($data['lampiran_path'])): ?>
                                                        <a href="<?= htmlspecialchars(proxyUrl($data['lampiran_path'])) ?>"
                                                            target="_blank" class="text-xs no-print">View</a>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-center rounded border bg-light d-flex align-items-center justify-content-center"
                                                    style="height: 80px; overflow: hidden;">
                                                    <?php if (!empty($data['lampiran_path'])): ?>
                                                        <img src="<?= htmlspecialchars(proxyUrl($data['lampiran_path'])) ?>"
                                                            alt="Lampiran"
                                                            style="max-width: 100%; max-height: 100%; cursor: pointer;"
                                                            onclick="window.open('<?= htmlspecialchars(proxyUrl($data['lampiran_path'])) ?>')">
                                                    <?php else: ?>
                                                        <small class="text-muted">No Image</small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="text-xs font-weight-bold mb-1">Dokumen PDF</div>
                                                <?php if (!empty($data['lampiran_pdf_path'])): ?>
                                                    <a href="<?= htmlspecialchars(proxyUrl($data['lampiran_pdf_path'])) ?>"
                                                        target="_blank"
                                                        class="btn btn-sm btn-block btn-outline-danger no-print mt-2">
                                                        <i class="far fa-file-pdf mr-1"></i> Buka PDF
                                                    </a>
                                                <?php else: ?>
                                                    <div
                                                        class="text-center py-2 rounded bg-light border border-dashed mt-2">
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

            <!-- SECTION: Production Data -->
            <div class="row mt-3">
                <div class="col-12">
                    <div id="print-production-section" class="card section-card">
                        <div class="card-header bg-<?= htmlspecialchars($themeColor); ?> text-white">
                            <h3 class="card-title mb-0">
                                <i class="fas fa-industry mr-1"></i> Production Data
                            </h3>
                        </div>
                        <div class="card-body">
                            <p class="text-muted text-sm mb-3">Informasi parameter setting mesin produksi dan instruksi
                                teknis.</p>
                            <div class="row">
                                <div class="col-12">
                                    <div class="sub-panel-title">
                                        <i class="fas fa-microchip mr-1 text-muted"></i> Machine Specifications
                                    </div>

                                    <div class="row">
                                        <?php if (empty($machines)): ?>
                                            <div class="col-12 empty-state border rounded">
                                                <p class="text-muted small mb-0 font-italic">Belum ada data parameter mesin
                                                    produksi.</p>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($machines as $idx => $m): ?>
                                                <div class="col-md-6 mb-4">
                                                    <div class="machine-card">
                                                        <div
                                                            class="machine-card-header d-flex justify-content-between align-items-center">
                                                            <span class="font-weight-bold text-navy text-sm">
                                                                <i class="fas fa-cog mr-1 text-muted"></i>
                                                                <?= !empty($m['machine_name']) ? htmlspecialchars($m['machine_name']) : '-' ?>
                                                            </span>
                                                            <span class="badge badge-secondary"
                                                                style="font-size: 0.65rem;">MACHINE <?= $idx + 1 ?></span>
                                                        </div>
                                                        <div class="p-3">
                                                            <div class="row">
                                                                <div class="col-6 pr-1">
                                                                    <div class="metric-box mb-3">
                                                                        <div class="metric-label">Speed</div>
                                                                        <div class="d-flex align-items-end">
                                                                            <span
                                                                                class="font-weight-bold text-dark"><?= isset($m['speed']) ? number_format((float) $m['speed'], 2, '.', ',') : '-' ?></span>
                                                                            <small class="ml-1 text-muted">m/m</small>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-6 pl-1">
                                                                    <div class="metric-box mb-3">
                                                                        <div class="metric-label">Lebar</div>
                                                                        <div class="d-flex align-items-end">
                                                                            <span
                                                                                class="font-weight-bold text-dark"><?= isset($m['lebar_kain']) ? number_format((float) $m['lebar_kain'], 2, '.', ',') : '-' ?></span>
                                                                            <small class="ml-1 text-muted">cm</small>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="metric-box">
                                                                <div class="metric-label mb-2">Temperature</div>
                                                                <div class="row temperature-grid">
                                                                    <?php for ($ch = 1; $ch <= 12; $ch++): ?>
                                                                        <?php $tempKey = $ch === 1 ? 'temperature' : 'temperature_ch' . $ch; ?>
                                                                        <div
                                                                            class="col-6 col-md-3 <?= $ch % 4 !== 0 ? 'border-right' : '' ?> mb-2">
                                                                            <small class="text-muted d-block"
                                                                                style="font-size: 0.6rem;">CH <?= $ch ?></small>
                                                                            <span
                                                                                class="font-weight-bold text-sm"><?= isset($m[$tempKey]) ? number_format((float) $m[$tempKey], 1, '.', ',') : '-' ?></span><small
                                                                                class="text-muted ml-1"
                                                                                style="font-size: 0.65rem;">°C</small>
                                                                        </div>
                                                                    <?php endfor; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<?php include '../../../includes/footer.php'; ?>

<!-- Select2 CSS (for machine dropdown) -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
<link rel="stylesheet"
    href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">

<?php $resepIdJs = (int) $resep_id; ?>

<!-- ============================
     Modal: Update Lab Data
================================-->
<?php if ($canUpdateLab): ?>
    <div class="modal fade" id="modalLabUpdate" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h5 class="modal-title"><i class="fas fa-flask mr-1"></i> Update Lab Data</h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="font-weight-bold">Color Analysis (L*a*b*)</label>
                        <div class="row">
                            <div class="col-4">
                                <label class="small">Nilai L</label>
                                <input type="number" step="0.0001" id="lab_nilai_l" class="form-control form-control-sm"
                                    value="<?= isset($data['nilai_l']) ? htmlspecialchars($data['nilai_l']) : '' ?>">
                            </div>
                            <div class="col-4">
                                <label class="small">Nilai A</label>
                                <input type="number" step="0.0001" id="lab_nilai_a" class="form-control form-control-sm"
                                    value="<?= isset($data['nilai_a']) ? htmlspecialchars($data['nilai_a']) : '' ?>">
                            </div>
                            <div class="col-4">
                                <label class="small">Nilai B</label>
                                <input type="number" step="0.0001" id="lab_nilai_b" class="form-control form-control-sm"
                                    value="<?= isset($data['nilai_b']) ? htmlspecialchars($data['nilai_b']) : '' ?>">
                            </div>
                        </div>
                    </div>
                    <hr>
                    <input type="hidden" id="lab_lampiran_existing"
                        value="<?= htmlspecialchars($data['lampiran_path'] ?? '') ?>">
                    <input type="hidden" id="lab_lampiran_pdf_existing"
                        value="<?= htmlspecialchars($data['lampiran_pdf_path'] ?? '') ?>">
                    <div class="form-group">
                        <label class="font-weight-bold">Lampiran Gambar (Foto)</label>
                        <?php if (!empty($data['lampiran_path'])): ?>
                            <div class="mb-1"><small class="text-muted">Saat ini: <a
                                        href="<?= htmlspecialchars(proxyUrl($data['lampiran_path'])) ?>" target="_blank">Lihat
                                        Gambar</a></small></div>
                        <?php endif; ?>
                        <input type="file" id="lab_lampiran" class="form-control-file" accept="image/*">
                        <small class="text-muted">Format: JPG, PNG, GIF, WebP. Kosongkan jika tidak ingin mengganti.</small>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Lampiran PDF <span class="text-muted">(Opsional)</span></label>
                        <?php if (!empty($data['lampiran_pdf_path'])): ?>
                            <div class="mb-1"><small class="text-muted">Saat ini: <a
                                        href="<?= htmlspecialchars(proxyUrl($data['lampiran_pdf_path'])) ?>"
                                        target="_blank">Lihat PDF</a></small></div>
                        <?php endif; ?>
                        <input type="file" id="lab_lampiran_pdf" class="form-control-file" accept="application/pdf">
                        <small class="text-muted">Format: PDF only. Kosongkan jika tidak ingin mengganti.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-<?= htmlspecialchars($themeColor) ?> btn-sm" id="btnSaveLab">
                        <i class="fas fa-save mr-1"></i> Simpan
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ============================
     Modal: Update Production Data
================================-->
<?php if ($canUpdateProduction): ?>
    <div class="modal fade" id="modalProdUpdate" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document"> <!-- Larger modal for multiple machines -->
            <div class="modal-content">
                <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h5 class="modal-title"><i class="fas fa-cog mr-1"></i> Update Production Data</h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body bg-light" id="prod_machines_container" style="max-height: 70vh; overflow-y: auto;">
                    <!-- Machines will be injected here by JS -->
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <div>
                        <button type="button" class="btn btn-outline-<?= htmlspecialchars($themeColor) ?> btn-sm"
                            id="btnAddMachineRow">
                            <i class="fas fa-plus mr-1"></i> Tambah Mesin
                        </button>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                        <button type="button" class="btn btn-warning btn-sm" id="btnSaveProd">
                            <i class="fas fa-save mr-1"></i> Simpan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Machine Row Template -->
    <template id="tmplMachineRow">
        <div class="machine-row card card-outline card-<?= htmlspecialchars($themeColor) ?> mb-3 shadow-sm">
            <div class="card-header py-2">
                <h3 class="card-title text-sm font-weight-bold">Data Mesin</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool text-danger btn-remove-machine" title="Hapus Mesin">
                        <i class="fas fa-times-circle"></i>
                    </button>
                </div>
            </div>
            <div class="card-body py-2 px-3">
                <div class="row align-items-end">
                    <div class="col-md-6 col-12">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold mb-1">Pilih Mesin</label>
                            <select class="form-control form-control-sm m-machine-select w-100" style="width:100%"></select>
                            <input type="hidden" class="m-machine-code">
                            <input type="hidden" class="m-machine-name">
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold mb-1">Speed</label>
                            <input type="number" step="0.01" class="form-control form-control-sm m-speed">
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold mb-1">Lebar Kain</label>
                            <input type="number" step="0.01" class="form-control form-control-sm m-lebar">
                        </div>
                    </div>
                </div>
                <div class="border-top pt-2 mt-1">
                    <div class="text-xs text-uppercase text-muted font-weight-bold mb-2">
                        <i class="fas fa-thermometer-half mr-1"></i> Temperature
                    </div>
                    <div class="row">
                        <?php for ($ch = 1; $ch <= 12; $ch++): ?>
                            <div class="col-lg-2 col-md-3 col-6">
                                <div class="form-group mb-2">
                                    <label class="small mb-1">Temp Ch <?= $ch ?> (°C)</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm m-temp<?= $ch ?>">
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
    </template>
<?php endif; ?>

<!-- Select2 + modal JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
    (function () {
        const RESEP_ID = <?= $resepIdJs ?>;
        const apiUrl = 'update_resep_params.php';

        function swal(icon, msg) {
            Swal.fire({ icon, title: msg, timer: 2200, showConfirmButton: false });
        }

        // ========================
        // LAB Modal
        // ========================
        <?php if ($canUpdateLab): ?>
            $('#btnSaveLab').on('click', function () {
                const $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');
                const fd = new FormData();
                fd.append('type', 'lab');
                fd.append('resep_id', RESEP_ID);
                fd.append('nilai_l', $('#lab_nilai_l').val());
                fd.append('nilai_a', $('#lab_nilai_a').val());
                fd.append('nilai_b', $('#lab_nilai_b').val());
                fd.append('lampiran_existing', $('#lab_lampiran_existing').val());
                fd.append('lampiran_pdf_existing', $('#lab_lampiran_pdf_existing').val());
                const imgFile = $('#lab_lampiran')[0].files[0];
                const pdfFile = $('#lab_lampiran_pdf')[0].files[0];
                if (imgFile) fd.append('lampiran', imgFile);
                if (pdfFile) fd.append('lampiran_pdf', pdfFile);

                $.ajax({
                    url: apiUrl, type: 'POST', data: fd,
                    processData: false, contentType: false,
                    success: function (res) {
                        $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan');
                        if (res.status === 'success') {
                            $('#modalLabUpdate').modal('hide');
                            swal('success', res.message);
                            setTimeout(() => location.reload(), 1800);
                        } else {
                            swal('error', res.message);
                        }
                    },
                    error: function () {
                        $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan');
                        swal('error', 'Terjadi kesalahan jaringan.');
                    }
                });
            });
        <?php endif; ?>

        // ========================
        // PRODUCTION Modal (Multi-Machine)
        // ========================
        <?php if ($canUpdateProduction): ?>
            const initialMachines = <?= json_encode($machines) ?>;

            function createMachineRow(data = {}) {
                const tmpl = document.getElementById('tmplMachineRow');
                const row = tmpl.content.cloneNode(true).querySelector('.machine-row');
                const $row = $(row);

                $row.find('.m-speed').val(data.speed || '');
                $row.find('.m-lebar').val(data.lebar_kain || '');
                $row.find('.m-temp1').val(data.temperature || '');
                for (let ch = 2; ch <= 12; ch++) {
                    $row.find('.m-temp' + ch).val(data['temperature_ch' + ch] || '');
                }
                $row.find('.m-machine-code').val(data.machine_code || '');
                $row.find('.m-machine-name').val(data.machine_name || '');

                const $select = $row.find('.m-machine-select');

                // Initialize Select2 for this specific row
                $select.select2({
                    dropdownParent: $('#modalProdUpdate'),
                    theme: 'bootstrap4',
                    placeholder: 'Cari mesin...',
                    allowClear: true,
                    ajax: {
                        url: 'get_machines.php',
                        dataType: 'json',
                        delay: 250,
                        data: params => ({ q: params.term || '' }),
                        processResults: data => ({ results: data.results || [] })
                    }
                });

                if (data.machine_code && data.machine_name) {
                    const opt = new Option(data.machine_name + ' (' + data.machine_code + ')', data.machine_code, true, true);
                    $select.append(opt).trigger('change');
                }

                $select.on('select2:select', function (e) {
                    $row.find('.m-machine-code').val(e.params.data.facode || e.params.data.id);
                    $row.find('.m-machine-name').val(e.params.data.faname || e.params.data.text);
                });
                $select.on('select2:clear', function () {
                    $row.find('.m-machine-code').val('');
                    $row.find('.m-machine-name').val('');
                });

                $row.find('.btn-remove-machine').on('click', function () {
                    $row.fadeOut(200, function () { $(this).remove(); });
                });

                $('#prod_machines_container').append($row);
            }

            // Init machines on modal open
            $('#modalProdUpdate').one('show.bs.modal', function () {
                // Populate existing machines only once
                $('#prod_machines_container').empty();
                if (initialMachines.length > 0) {
                    initialMachines.forEach(m => createMachineRow(m));
                } else {
                    createMachineRow(); // Empty row if none
                }
            });

            $('#btnAddMachineRow').on('click', () => createMachineRow());

            $('#btnSaveProd').on('click', function () {
                const $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');

                const machines = [];
                $('#prod_machines_container .machine-row').each(function () {
                    const $r = $(this);
                    machines.push({
                        machine_code: $r.find('.m-machine-code').val(),
                        machine_name: $r.find('.m-machine-name').val(),
                        speed: $r.find('.m-speed').val(),
                        lebar_kain: $r.find('.m-lebar').val(),
                        temp_ch1: $r.find('.m-temp1').val(),
                    });
                    for (let ch = 2; ch <= 12; ch++) {
                        machines[machines.length - 1]['temp_ch' + ch] = $r.find('.m-temp' + ch).val();
                    }
                });

                $.post(apiUrl, {
                    type: 'production',
                    resep_id: RESEP_ID,
                    machines: JSON.stringify(machines)
                }, function (res) {
                    $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan');
                    if (res.status === 'success') {
                        $('#modalProdUpdate').modal('hide');
                        swal('success', res.message);
                        setTimeout(() => location.reload(), 1800);
                    } else {
                        swal('error', res.message);
                    }
                }, 'json').fail(function () {
                    $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan');
                    swal('error', 'Terjadi kesalahan jaringan.');
                });
            });
        <?php endif; ?>
    })();
</script>

<?php /* Temporarily disabled PDF merging in print due to quality issues
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
(function () {
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
              canvas.width = viewport.width;
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
*/ ?>