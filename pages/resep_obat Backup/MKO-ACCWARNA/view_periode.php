<?php
session_start();
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

$kodePeriode = trim((string) ($_GET['kode_periode'] ?? ''));
if ($kodePeriode === '') {
    die('Kode periode wajib diisi.');
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
$setupError = null;
$rawRows = [];
$materialRows = [];
$rekapRows = [];
$pptFailRows = [];
$pptBedaRows = [];

try {
    $gg = getSqlsrvConnection('gg');
    $conn = $gg;
    ensureTablesExist($gg);

    $rawRows = getRowsByKodePeriode(
        $gg,
        'mko_rawdata',
        $kodePeriode,
        ['id', 'productionhdid', 'rtgmsid', 'prdnmbr', 'fgresult', 'rtgname', 'fgstatus', 'startdate', 'enddate', 'created_at'],
        'startdate, productionhdid'
    );

    $materialRows = getRowsByKodePeriode(
        $gg,
        'MKO_materialobat',
        $kodePeriode,
        ['id', 'prdnmbr', 'prodcode', 'prodname', 'labeljual', 'cuscolor', 'colorcode', 'colorname', 'rtgname', 'material_code', 'material_name', 'matqty', 'uomcode', 'unit_price', 'subtotal', 'prodcf', 'kelompok', 'vlot', 'created_at'],
        'prdnmbr, material_code'
    );

    $rekapRows = getRowsByKodePeriode(
        $gg,
        'MKO_rekap',
        $kodePeriode,
        ['id', 'labeljual', 'colorname', 'cuscolor', 'colorcode', 'master_resep', 'prdnmbr', 'status_proses_acc', 'status_cp', 'vlot', 'rtgname', 'disperse', 'reactive', 'grand_total', 'created_at'],
        'prdnmbr, labeljual, colorname'
    );

    $pptFailRows = getRowsByKodePeriode(
        $gg,
        'MKO_PPT_CPSTATUS_FAIL',
        $kodePeriode,
        ['id', 'labeljual', 'colorname', 'cuscolor', 'colorcode', 'cp', 'status_proses_acc', 'status_cp', 'routing', 'disperse', 'reactive', 'created_at'],
        'colorcode, cp'
    );

    $pptBedaRows = getRowsByKodePeriode(
        $gg,
        'MKO_PPT_PERBEDAAN_RESEP',
        $kodePeriode,
        ['id', 'labeljual', 'colorname', 'cuscolor', 'colorcode', 'cp', 'status_cp', 'routing', 'disperse', 'reactive', 'grand_total', 'created_at'],
        'colorcode, cp'
    );
} catch (Throwable $e) {
    $setupError = $e->getMessage();
}

include '../../../includes/header.php';
include '../../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>View Kode Periode</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/pages/home.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">MKO ACC Warna</a></li>
                        <li class="breadcrumb-item active"><?= htmlspecialchars($kodePeriode) ?></li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if ($setupError): ?>
                <div class="alert alert-danger">
                    <strong>Gagal memuat data.</strong><br>
                    <?= htmlspecialchars($setupError) ?>
                </div>
            <?php endif; ?>

            <div class="card card-outline card-<?= htmlspecialchars($themeColor) ?>">
                <div class="card-header">
                    <h3 class="card-title">Kode Periode: <?= htmlspecialchars($kodePeriode) ?></h3>
                    <div class="card-tools">
                        <a href="export_periode_excel.php?kode_periode=<?= urlencode($kodePeriode) ?>" class="btn btn-sm btn-success">Export Excel</a>
                        <a href="index.php" class="btn btn-sm btn-default">Kembali</a>
                    </div>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs" id="periodeTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="raw-tab" data-toggle="tab" href="#raw-sheet" role="tab">Sheet mko_rawdata</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="material-tab" data-toggle="tab" href="#material-sheet" role="tab">Sheet MKO_materialobat</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="rekap-tab" data-toggle="tab" href="#rekap-sheet" role="tab">Sheet MKO_rekap</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="ppt-tab" data-toggle="tab" href="#ppt-sheet" role="tab">Sheet MKO_PPT</a>
                        </li>
                    </ul>

                    <div class="tab-content pt-3">
                        <div class="tab-pane fade show active" id="raw-sheet" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm text-nowrap">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Production HD ID</th>
                                            <th>RTGMSID</th>
                                            <th>prdnmbr</th>
                                            <th>fgresult</th>
                                            <th>rtgname</th>
                                            <th>fgstatus</th>
                                            <th>startdate</th>
                                            <th>enddate</th>
                                            <th>created_at</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!$rawRows): ?>
                                            <tr><td colspan="10" class="text-center text-muted">Belum ada data.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($rawRows as $index => $row): ?>
                                                <tr>
                                                    <td><?= $index + 1 ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['productionhdid'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['rtgmsid'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['prdnmbr'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['fgresult'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['rtgname'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['fgstatus'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars(formatDisplayDate($row['startdate'] ?? null)) ?></td>
                                                    <td><?= htmlspecialchars(formatDisplayDate($row['enddate'] ?? null)) ?></td>
                                                    <td><?= htmlspecialchars(formatDisplayDate($row['created_at'] ?? null)) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="material-sheet" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm text-nowrap">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>prdnmbr</th>
                                            <th>Prod Code</th>
                                            <th>Prod Name</th>
                                            <th>Label Jual</th>
                                            <th>Cus Color</th>
                                            <th>Color Code</th>
                                            <th>Color Name</th>
                                            <th>rtgname</th>
                                            <th>Material Code</th>
                                            <th>Material Name</th>
                                            <th>Mat Qty</th>
                                            <th>UOM</th>
                                            <th>Unit Price</th>
                                            <th>Subtotal</th>
                                            <th>prodcf</th>
                                            <th>kelompok</th>
                                            <th>Vlot</th>
                                            <th>created_at</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!$materialRows): ?>
                                            <tr><td colspan="19" class="text-center text-muted">Belum ada data.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($materialRows as $index => $row): ?>
                                                <tr>
                                                    <td><?= $index + 1 ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['prdnmbr'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['prodcode'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['prodname'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['labeljual'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['cuscolor'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['colorcode'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['colorname'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['rtgname'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['material_code'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['material_name'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars(formatDecimalValue($row['matqty'] ?? null, 2)) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['uomcode'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars(formatDecimalValue($row['unit_price'] ?? null, 2)) ?></td>
                                                    <td><?= htmlspecialchars(formatDecimalValue($row['subtotal'] ?? null, 2)) ?></td>
                                                    <td><?= htmlspecialchars(formatDecimalValue($row['prodcf'] ?? null, 2)) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['kelompok'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['vlot'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars(formatDisplayDate($row['created_at'] ?? null)) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="rekap-sheet" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm text-nowrap">
                                    <thead>
                                        <tr>
                                            <th>Label Jual</th>
                                            <th>Color Name</th>
                                            <th>Cus Color</th>
                                            <th>Color Code</th>
                                            <th>Master Resep</th>
                                            <th>prdnmbr</th>
                                            <th>Status Proses Acc Warna/Ulang</th>
                                            <th>Status CP</th>
                                            <th>Vlot</th>
                                            <th>rtgname</th>
                                            <th>Disperse</th>
                                            <th>Reactive</th>
                                            <th>Grand Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!$rekapRows): ?>
                                            <tr><td colspan="13" class="text-center text-muted">Belum ada data.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($rekapRows as $row): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars((string) ($row['labeljual'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['colorname'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['cuscolor'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['colorcode'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['master_resep'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['prdnmbr'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['status_proses_acc'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['status_cp'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['vlot'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['rtgname'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars(formatDecimalValue($row['disperse'] ?? 0, 2)) ?></td>
                                                    <td><?= htmlspecialchars(formatDecimalValue($row['reactive'] ?? 0, 2)) ?></td>
                                                    <td><?= htmlspecialchars(formatDecimalValue($row['grand_total'] ?? 0, 2)) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="ppt-sheet" role="tabpanel">
                            <div class="mb-4">
                                <h5 class="font-weight-bold">CP DENGAN STATUS FAIL (SUDAH ADA MASTER RESEP)</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm text-nowrap">
                                        <thead>
                                            <tr>
                                                <th>Label Jual</th>
                                                <th>Color Name</th>
                                                <th>Cus Color</th>
                                                <th>Color Code</th>
                                                <th>CP</th>
                                                <th>Status Proses Acc Warna/Ulang</th>
                                                <th>Status CP</th>
                                                <th>Routing</th>
                                                <th>Disperse</th>
                                                <th>Reactive</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!$pptFailRows): ?>
                                                <tr><td colspan="10" class="text-center text-muted">Belum ada data.</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($pptFailRows as $row): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars((string) ($row['labeljual'] ?? '')) ?></td>
                                                        <td><?= htmlspecialchars((string) ($row['colorname'] ?? '')) ?></td>
                                                        <td><?= htmlspecialchars((string) ($row['cuscolor'] ?? '')) ?></td>
                                                        <td><?= htmlspecialchars((string) ($row['colorcode'] ?? '')) ?></td>
                                                        <td><?= htmlspecialchars((string) ($row['cp'] ?? '')) ?></td>
                                                        <td><?= htmlspecialchars((string) ($row['status_proses_acc'] ?? '')) ?></td>
                                                        <td><?= htmlspecialchars((string) ($row['status_cp'] ?? '')) ?></td>
                                                        <td><?= htmlspecialchars((string) ($row['routing'] ?? '')) ?></td>
                                                        <td><?= htmlspecialchars(formatDecimalValue($row['disperse'] ?? 0, 2)) ?></td>
                                                        <td><?= htmlspecialchars(formatDecimalValue($row['reactive'] ?? 0, 2)) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div>
                                <h5 class="font-weight-bold">PERBEDAAN RESEP DISPERSE &amp; REACTIVE PADA KODE WARNA YANG SAMA</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm text-nowrap">
                                        <thead>
                                            <tr>
                                                <th>Label Jual</th>
                                                <th>Color Name</th>
                                                <th>Cus Color</th>
                                                <th>Color Code</th>
                                                <th>CP</th>
                                                <th>Status CP</th>
                                                <th>Routing</th>
                                                <th>Disperse</th>
                                                <th>Reactive</th>
                                                <th>Grand Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!$pptBedaRows): ?>
                                                <tr><td colspan="10" class="text-center text-muted">Belum ada data.</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($pptBedaRows as $row): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars((string) ($row['labeljual'] ?? '')) ?></td>
                                                        <td><?= htmlspecialchars((string) ($row['colorname'] ?? '')) ?></td>
                                                        <td><?= htmlspecialchars((string) ($row['cuscolor'] ?? '')) ?></td>
                                                        <td><?= htmlspecialchars((string) ($row['colorcode'] ?? '')) ?></td>
                                                        <td><?= htmlspecialchars((string) ($row['cp'] ?? '')) ?></td>
                                                        <td><?= htmlspecialchars((string) ($row['status_cp'] ?? '')) ?></td>
                                                        <td><?= htmlspecialchars((string) ($row['routing'] ?? '')) ?></td>
                                                        <td><?= htmlspecialchars(formatDecimalValue($row['disperse'] ?? 0, 2)) ?></td>
                                                        <td><?= htmlspecialchars(formatDecimalValue($row['reactive'] ?? 0, 2)) ?></td>
                                                        <td><?= htmlspecialchars(formatDecimalValue($row['grand_total'] ?? 0, 2)) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
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
