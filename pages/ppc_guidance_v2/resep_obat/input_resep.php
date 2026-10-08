<?php
// pages/resep_obat/input_resep.php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/includes/resep_config.php';
require_once __DIR__ . '/includes/field_trustee_helper.php';

$showProIntMetadata = showResepPpcGuidanceProIntMetadata($conn);

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
$resep_id = $_GET['resep_id'] ?? null;
$mode = $resep_id ? 'Edit' : 'Tambah';
$data = null;
$details = [];
$fieldPermissions = loadPpcResepFieldTrustee(
    $conn,
    (string) $_SESSION['UserName'],
    (int) ($_SESSION['GroupId'] ?? 0)
);

/** Returns readonly attribute for one PPC trustee field. */
function ppcResepReadonlyAttr(string $fieldKey): string
{
    global $fieldPermissions;
    return isPpcResepFieldReadonly($fieldPermissions, $fieldKey) ? 'readonly' : '';
}

/** Returns disabled attribute for one PPC trustee field. */
function ppcResepDisabledAttr(string $fieldKey): string
{
    global $fieldPermissions;
    return isPpcResepFieldReadonly($fieldPermissions, $fieldKey) ? 'disabled' : '';
}

$canSeePrice = (int) ($_SESSION['GroupId'] ?? 0) === 1;
if (!$canSeePrice) {
    $pricePermissionStatement = sqlsrv_query(
        $conn,
        "SELECT TOP 1 g.id
         FROM dbo.resep_obat_group_members m
         INNER JOIN dbo.resep_obat_groups g ON m.group_id = g.id
         WHERE m.username = ? AND g.role_type = 'KABAG'",
        [$_SESSION['UserName']]
    );
    $canSeePrice = $pricePermissionStatement
        && (bool) sqlsrv_fetch_array($pricePermissionStatement, SQLSRV_FETCH_ASSOC);
}
$showDetailActions = !isPpcResepFieldReadonly($fieldPermissions, 'detail_items');

$sourceExperimentId = filter_input(INPUT_GET, 'source_experiment_id', FILTER_VALIDATE_INT) ?: null;
$duplicateConfirmed = ($_GET['duplicate_confirmed'] ?? '') === '1';

if ($resep_id) {
    // Fetch Header
    $stmt = sqlsrv_query($conn, "SELECT * FROM dbo.resep_obat_v2 WHERE id = ?", [$resep_id]);
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $data = $row;
    }
    // Fetch Details
    $stmtDetail = sqlsrv_query($conn, "
        SELECT
            detail.*,
            COALESCE(NULLIF(LTRIM(RTRIM(legacy_master.codeprod_proint)), ''), detail.kode) AS display_codeprod_proint
        FROM dbo.resep_obat_detail_v2 detail
        OUTER APPLY (
            SELECT TOP 1 master.codeprod_proint
            FROM dbo.resep_master_obat master
            WHERE (master.kode_obat = detail.kode OR master.codeprod_proint = detail.kode)
            ORDER BY CASE WHEN LTRIM(RTRIM(master.nama_obat)) = LTRIM(RTRIM(detail.name)) THEN 0 ELSE 1 END, master.id DESC
        ) legacy_master
        WHERE detail.id_resep = ?
        ORDER BY detail.table_index ASC, detail.sort_order ASC, detail.id ASC
    ", [$resep_id]);
    while ($stmtDetail && $rowD = sqlsrv_fetch_array($stmtDetail, SQLSRV_FETCH_ASSOC)) {
        if (!empty($rowD['display_codeprod_proint'])) {
            $rowD['codeprod'] = $rowD['display_codeprod_proint'];
            $rowD['kode'] = $rowD['display_codeprod_proint'];
        }
        $details[] = $rowD;
    }
} elseif ($sourceExperimentId) {
    require_once __DIR__ . '/../../resep_obat/experiment/experiment_visibility_helper.php';
    $conditions = ['e.id = ?', 'e.was_approved = 1'];
    $params = [$sourceExperimentId];
    resepExperimentApplyVisibility($conditions, $params, 'e', $conn);
    $draftStatement = sqlsrv_query($conn, "SELECT e.no_cp, e.kode_warna, e.color_name, e.color_desc, e.lot_no, e.weight, e.plan_qty,
        e.kode_grey, e.mesin, e.vlot, e.resep_prod_code, e.resep_prod_name, e.soi AS no_so, e.cus_color
        FROM dbo.resep_obat_experiment e WHERE " . implode(' AND ', $conditions), $params);
    $data = $draftStatement ? sqlsrv_fetch_array($draftStatement, SQLSRV_FETCH_ASSOC) : null;
    if (!$data) {
        http_response_code(404);
        exit('Experiment Approved tidak ditemukan atau tidak dapat diakses.');
    }
    $data['status_resep_lipat'] = 'Experiment';
    $detailStatement = sqlsrv_query($conn, "
        SELECT d.kode, d.name, d.category, d.receipe, d.uom, d.cf, d.uom_cf, d.std_price,
            d.total, d.price_satuan, d.price_source, d.is_manual,
            COALESCE(NULLIF(LTRIM(RTRIM(legacy_master.codeprod_proint)), ''), d.kode) AS display_codeprod_proint
        FROM dbo.resep_obat_experiment_detail d
        OUTER APPLY (
            SELECT TOP 1 master.codeprod_proint
            FROM dbo.resep_master_obat master
            WHERE (master.kode_obat = d.kode OR master.codeprod_proint = d.kode)
            ORDER BY CASE WHEN LTRIM(RTRIM(master.nama_obat)) = LTRIM(RTRIM(d.name)) THEN 0 ELSE 1 END, master.id DESC
        ) legacy_master
        WHERE d.id_resep_experiment = ?
        ORDER BY d.id
    ", [$sourceExperimentId]);
    if ($detailStatement === false) {
        http_response_code(500);
        exit('Detail experiment gagal dimuat.');
    }
    while ($detailRow = sqlsrv_fetch_array($detailStatement, SQLSRV_FETCH_ASSOC)) {
        if (!empty($detailRow['display_codeprod_proint'])) {
            $detailRow['codeprod'] = $detailRow['display_codeprod_proint'];
            $detailRow['kode'] = $detailRow['display_codeprod_proint'];
        }
        $details[] = $detailRow;
    }
}

// Fetch Limits & Config
$limits = ['min_cost' => null, 'max_cost' => null, 'category_limits' => '{}'];
$stmtLim = sqlsrv_query($conn, "SELECT top 1 min_cost, max_cost, category_limits FROM dbo.resep_config");
if ($stmtLim && $limRow = sqlsrv_fetch_array($stmtLim, SQLSRV_FETCH_ASSOC)) {
    $limits = $limRow;
}

include '../../../includes/header.php';
include '../../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><?= $mode ?> Resep Obat</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="list_resep.php">Resep</a></li>
                        <li class="breadcrumb-item active"><?= $mode ?></li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <style>
            /* Table and Input Optimizations */
            #detailTable input[readonly] {
                cursor: help;
                background-color: #f8f9fa;
            }

            #detailTable .form-control-sm {
                padding-left: 4px;
                padding-right: 4px;
            }

            #detailTable thead th {
                text-align: center;
                vertical-align: middle;
                font-size: 1rem;
                font-weight: bold;
                color: #495057;
            }

            /* Force Select2 dropdown to be wider than the narrow column */
            .select2-container--bootstrap4 .select2-dropdown {
                min-width: 350px !important;
                border-color: #80bdff !important;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
            }

            .basic-info-compact > .col-md-6 {
                display: contents;
            }

            .basic-info-compact > .col-md-6 > .form-group:not(.d-none) {
                flex: 0 0 50%;
                max-width: 50%;
                margin-left: 0;
                margin-right: 0;
                padding: 0 7.5px;
            }

            @media (max-width: 767.98px) {
                .basic-info-compact > .col-md-6 > .form-group:not(.d-none) {
                    flex-basis: 100%;
                    max-width: 100%;
                }
            }
        </style>
        <div class="container-fluid">
            <form id="resepForm" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="resep_id" value="<?= $resep_id ?? '' ?>">
                <input type="hidden" name="source_experiment_id" value="<?= $sourceExperimentId ?? '' ?>">
                <input type="hidden" name="duplicate_confirmed" value="<?= $duplicateConfirmed ? '1' : '0' ?>">

                <!-- HEADER SECTION -->
                <div class="card card-<?= htmlspecialchars($themeColor); ?>">
                    <div class="card-header">
                        <h3 class="card-title">Informasi Dasar</h3>

                    </div>
                    <div class="card-body">
                        <div class="row<?= $showProIntMetadata ? '' : ' basic-info-compact' ?>">
                            <div class="col-md-6">
                                <!-- Kode Grey (Above Kode Warna) -->
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Kode Grey</label>
                                    <div class="col-sm-8">
                                        <input type="hidden" name="kode_grey" id="kode_grey_hidden"
                                            value="<?= $data['kode_grey'] ?? '' ?>">
                                        <select id="select_kode_grey" class="form-control select2-grey"
                                            name="grey_id_select" style="width: 100%;" <?= ppcResepDisabledAttr('kode_grey') ?>>
                                            <?php if (isset($data['kode_grey']) && $data['kode_grey']): ?>
                                                <option value="<?= $data['kode_grey'] ?>" selected><?= $data['kode_grey'] ?>
                                                </option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Mesin Paddry (hidden, still submitted) -->
                                <input type="hidden" name="mesin" id="mesin" value="<?= $data['mesin'] ?? '' ?>">


                                <!-- SWAPPED: Kode Warna First -->
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Kode Warna</label>
                                    <div class="col-sm-8">
                                        <!-- Select2 for Color Lookup -->
                                        <div class="d-flex align-items-center">
                                            <div style="flex: 1;">
                                                <?php if (isPpcResepFieldReadonly($fieldPermissions, 'kode_warna')): ?>
                                                    <input type="hidden" name="kode_warna" value="<?= htmlspecialchars($data['kode_warna'] ?? '') ?>">
                                                <?php endif; ?>
                                                <select class="form-control select2-color"
                                                    name="<?= isPpcResepFieldReadonly($fieldPermissions, 'kode_warna') ? '' : 'kode_warna' ?>"
                                                    style="width: 100%;" required <?= ppcResepDisabledAttr('kode_warna') ?>>
                                                    <?php if (isset($data['kode_warna'])): ?>
                                                        <option value="<?= $data['kode_warna'] ?>" selected>
                                                            <?= $data['kode_warna'] ?> - <?= $data['color_name'] ?? '' ?>
                                                        </option>
                                                    <?php endif; ?>
                                                </select>
                                            </div>
                                            <div id="iconCheckResep" style="cursor: pointer; display: none;"
                                                class="ml-2" title="Lihat Pilihan Resep">
                                                <i class="fas fa-list-ul text-info"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Color Details Auto-filled -->
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Color Name</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="color_name" name="color_name"
                                            readonly value="<?= $data['color_name'] ?? '' ?>">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Description</label>
                                    <div class="col-sm-8">
                                        <textarea class="form-control" id="color_desc" name="color_desc" rows="2"
                                            readonly><?= $data['color_desc'] ?? '' ?></textarea>
                                    </div>
                                </div>

                                <!-- Resep Prod Info (Auto-fill) -->
                                <div class="form-group row<?= $showProIntMetadata ? '' : ' d-none' ?>">
                                    <label class="col-sm-4 col-form-label">Resep Prod Code</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="resepprodcode" name="resepprodcode"
                                            readonly value="<?= $data['resep_prod_code'] ?? '' ?>">
                                    </div>
                                </div>
                                <div class="form-group row<?= $showProIntMetadata ? '' : ' d-none' ?>">
                                    <label class="col-sm-4 col-form-label">Resep Prod Name</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="resepprodname" name="resepprodname"
                                            readonly value="<?= $data['resep_prod_name'] ?? '' ?>">
                                    </div>
                                </div>

                                <!-- No CP Second (Optional) -->
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">No CP <small
                                            class="text-muted">(Opsional)</small></label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" name="no_cp" id="no_cp"
                                            <?= ppcResepReadonlyAttr('no_cp') ?> value="<?= $data['no_cp'] ?? '' ?>">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Lot No</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" name="lot_no" required
                                            <?= ppcResepReadonlyAttr('lot_no') ?> value="<?= $data['lot_no'] ?? '' ?>">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Plan Qty</label>
                                    <div class="col-sm-8">
                                        <input type="number" step="0.01" class="form-control" name="plan_qty"
                                            id="plan_qty" required <?= ppcResepReadonlyAttr('plan_qty') ?> value="<?= $data['plan_qty'] ?? '3500' ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">

                                <div class="form-group row<?= $showProIntMetadata ? '' : ' d-none' ?>">
                                    <label class="col-sm-4 col-form-label">Resep No</label>
                                    <div class="col-sm-8">
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="resep_no_display" readonly
                                                value="<?= ($data['resep_no'] ?? '') . (isset($data['resep_seq']) ? ' Ver ' . $data['resep_seq'] : '') ?>">
                                            <input type="hidden" name="resep_no" id="resep_no"
                                                value="<?= $data['resep_no'] ?? '' ?>">
                                            <input type="hidden" name="resep_seq" id="resep_seq"
                                                value="<?= $data['resep_seq'] ?? '' ?>">
                                            <input type="hidden" name="proint_resephdid" id="proint_resephdid"
                                                value="<?= $data['proint_resephdid'] ?? '' ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group row<?= $showProIntMetadata ? '' : ' d-none' ?>">
                                    <label class="col-sm-4 col-form-label">Resep Date</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" name="resep_date" id="resep_date"
                                            readonly
                                            value="<?= isset($data['resep_date']) && $data['resep_date'] instanceof DateTime ? $data['resep_date']->format('Y-m-d') : (substr($data['resep_date'] ?? '', 0, 10)) ?>">
                                    </div>
                                </div>
                                <div class="form-group row<?= $showProIntMetadata ? '' : ' d-none' ?>">
                                    <label class="col-sm-4 col-form-label">Resep Type</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" name="resep_type" id="resep_type"
                                            readonly value="<?= $data['resep_type'] ?? '' ?>">
                                    </div>
                                </div>

                                <!-- New ProInt Fields -->
                                <div class="form-group row<?= $showProIntMetadata ? '' : ' d-none' ?>">
                                    <label class="col-sm-4 col-form-label">No CP Resep</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" name="no_cp_resep" id="no_cp_resep"
                                            readonly value="<?= $data['no_cp_resep'] ?? '' ?>">
                                    </div>
                                </div>
                                <div class="form-group row<?= $showProIntMetadata ? '' : ' d-none' ?>">
                                    <label class="col-sm-4 col-form-label">No SO</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" name="no_so" id="no_so" readonly
                                            value="<?= $data['no_so'] ?? '' ?>">
                                    </div>
                                </div>

                                <div class="form-group row<?= $showProIntMetadata ? '' : ' d-none' ?>">
                                    <label class="col-sm-4 col-form-label">Routing</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="rtg_display" readonly
                                            value="<?= !empty($data['rtg_code']) ? ($data['rtg_code'] . ' - ' . ($data['rtg_name'] ?? '')) : '' ?>">
                                        <input type="hidden" name="rtg_code" id="rtg_code"
                                            value="<?= $data['rtg_code'] ?? '' ?>">
                                        <input type="hidden" name="rtg_name" id="rtg_name"
                                            value="<?= $data['rtg_name'] ?? '' ?>">
                                    </div>
                                </div>
                                <div class="form-group row<?= $showProIntMetadata ? '' : ' d-none' ?>">
                                    <label class="col-sm-4 col-form-label">Status Desc</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" name="status_desc" id="status_desc"
                                            readonly value="<?= $data['status_desc'] ?? '' ?>">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Status Resep</label>
                                    <div class="col-sm-8">
                                        <select class="form-control" name="status_resep_lipat" id="status_resep_lipat">
                                            <option value="">-- Pilih Status --</option>
                                            <option value="Shading" <?= (isset($data['status_resep_lipat']) && $data['status_resep_lipat'] === 'Shading') ? 'selected' : '' ?>>Shading
                                            </option>
                                            <option value="Experiment" <?= (isset($data['status_resep_lipat']) && $data['status_resep_lipat'] === 'Experiment') ? 'selected' : '' ?>>Experiment
                                            </option>
                                            <option value="Kesetabilan" <?= (isset($data['status_resep_lipat']) && $data['status_resep_lipat'] === 'Kesetabilan') ? 'selected' : '' ?>>Kesetabilan
                                            </option>
                                            <option value="Master Resep" <?= (isset($data['status_resep_lipat']) && $data['status_resep_lipat'] === 'Master Resep') ? 'selected' : '' ?>>Master Resep
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Cus Color</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" name="cus_color" id="cus_color"
                                            <?= ppcResepReadonlyAttr('cus_color') ?> value="<?= $data['cus_color'] ?? '' ?>">
                                    </div>
                                </div>

                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Weight</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" name="weight" required
                                            <?= ppcResepReadonlyAttr('weight') ?> value="<?= $data['weight'] ?? '100' ?>">
                                    </div>
                                </div>

                                <!-- Vlot (hidden, still used for calculations) -->
                                <input type="hidden" name="vlot" id="vlot"
                                    value="<?= $data['vlot'] ?? '' ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DETAILS SECTION (MULTI-ROUTING TABLES) -->
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="font-weight-bold text-dark m-0"><i class="fas fa-layer-group mr-2 text-primary"></i>Detail Resep per Routing</h5>
                    <div>
                        <input type="hidden" name="is_manual" id="is_manual" value="<?= $data['is_manual'] ?? 0 ?>">
                        <button type="button" class="btn btn-outline-primary btn-sm font-weight-bold shadow-sm" id="btnAddRoutingTable"
                            <?= ppcResepDisabledAttr('detail_items') ?>>
                            <i class="fas fa-plus-circle mr-1"></i> Tambah Tabel Routing Baru
                        </button>
                    </div>
                </div>

                <div id="routingTablesContainer">
                    <!-- Dynamic routing tables will be rendered here -->
                </div>

                <?php if ($canSeePrice): ?>
                    <!-- SUMMARY / CALCULATIONS -->
                    <div class="row mt-3">
                        <div class="col-md-5 offset-md-7">
                        <div class="card shadow border-0">
                            <div class="card-header bg-<?= htmlspecialchars($themeColor); ?> p-2 d-flex justify-content-between align-items-center">
                                <h3 class="card-title text-sm text-white font-weight-bold m-0"><i class="fas fa-calculator mr-1"></i> Cost Summary Per Meter (Semua Tabel)</h3>
                                <span class="badge badge-light font-weight-bold" id="badgeGrandTotalSemuaTabel">Grand Total: Rp 0</span>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0">
                                    <thead class="text-center text-muted"
                                        style="font-size: 0.8rem; background-color: #fff;">
                                        <tr>
                                            <th class="border-top-0 text-left pl-3">Category</th>
                                            <th class="border-top-0 text-center">Total Cf</th>
                                            <th class="border-top-0 text-right pr-3">Cost</th>
                                        </tr>
                                    </thead>
                                    <tbody id="costDetails" style="font-size: 0.9rem;">
                                        <!-- Dynamic Category Costs -->
                                    </tbody>
                                    <tfoot style="border-top: 2px solid #343a40;">
                                        <tr class="bg-white">
                                            <td colspan="2" class="font-weight-bold text-navy pl-3 align-middle">Total Cost Per Meter</td>
                                            <td class="text-right pr-3"><span id="valCost"
                                                    class="font-weight-bold text-lg">Rp 0</span></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        </div>
                    </div>
                <?php endif; ?>

                <div class="row mb-4 mt-3">
                    <div class="col-12">
                        <button type="submit" class="btn btn-success float-right" id="btnSave"><i
                                class="fas fa-save"></i> Simpan Resep</button>
                        <a href="list_resep.php" class="btn btn-secondary float-right mr-2">Kembali</a>
                    </div>
                </div>

        </div>
    </div>
</div>
</div>

<!-- Modal Select Resep ProInt -->
<div class="modal fade" id="modal-pilih-resep">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor); ?>">
                <h4 class="modal-title text-white">Pilih Resep ProInt</h4>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Terdeteksi beberapa resep untuk warna ini. Silakan pilih:</p>

                <!-- Search Box -->
                <div class="form-group">
                    <input type="text" class="form-control" id="searchResep"
                        placeholder="Cari Resep (No Resep / Info)...">
                </div>

                <div class="list-group" id="listResepProInt" style="max-height: 400px; overflow-y: auto;">
                    <!-- Items -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Select Bon by No CP (Multi-Select) -->
<div class="modal fade" id="modal-pilih-bon" tabindex="-1" role="dialog" aria-labelledby="modal-pilih-bon-title" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor); ?>">
                <h4 class="modal-title text-white" id="modal-pilih-bon-title"><i class="fas fa-tasks mr-2"></i>Pilih Bon / Routing</h4>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <p class="text-muted m-0 small">No CP memiliki beberapa bon. Centang satu atau beberapa routing yang akan digunakan:</p>
                    <div class="btn-group btn-group-xs">
                        <button type="button" class="btn btn-xs btn-outline-primary" id="btnSelectAllBons"><i class="fas fa-check-double mr-1"></i>Pilih Semua</button>
                        <button type="button" class="btn btn-xs btn-outline-secondary" id="btnUnselectAllBons"><i class="fas fa-times mr-1"></i>Batal Semua</button>
                    </div>
                </div>
                <div class="list-group" id="listBonCp" style="max-height: 420px; overflow-y: auto;"></div>
            </div>
            <div class="modal-footer justify-content-between bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm font-weight-bold" id="btnApplySelectedBons" disabled>
                    <i class="fas fa-check mr-1"></i> Terapkan Routing Terpilih (<span id="countSelectedBons">0</span>)
                </button>
            </div>
        </div>
    </div>
</div>
</div>

</form>


<?php include '../../../includes/footer.php'; ?>

<!-- Select2 -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
<link rel="stylesheet"
    href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<!-- SortableJS for drag-and-drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<style>
    /* Drag handle for routing cards (table level) */
    .table-drag-handle {
        cursor: grab;
        color: #adb5bd;
        font-size: 1.2rem;
        padding: 0 8px;
        transition: color 0.15s;
        user-select: none;
    }
    .table-drag-handle:hover { color: #495057; }
    .routing-card.sortable-ghost {
        opacity: 0.4;
        border: 2px dashed #007bff !important;
        background: #e8f4ff;
    }
    .routing-card.sortable-drag {
        box-shadow: 0 8px 24px rgba(0,123,255,0.25) !important;
        opacity: 1;
    }
    /* Drag handle for rows (item level) */
    .row-drag-handle {
        cursor: grab;
        color: #adb5bd;
        font-size: 1rem;
        text-align: center;
        user-select: none;
        transition: color 0.15s;
        width: 28px;
        padding: 0 4px;
    }
    .row-drag-handle:hover { color: #495057; }
    tr.sortable-ghost td { background: #d1ecff !important; opacity: 0.5; }
    tr.sortable-drag { box-shadow: 0 4px 14px rgba(0,0,0,0.15); }
    /* Indicate draggable state */
    .table-drag-handle:active, .row-drag-handle:active { cursor: grabbing; }
</style>

<script>
    $(document).ready(function () {
        let rowIdx = 0;
        let cpLookupRequest = null;

        // Limits and Config from PHP (Initial Global Values)
        const MIN_LIMIT = <?= json_encode($limits['min_cost']) ?>;
        const PPC_FIELD_TRUSTEE = <?= json_encode($fieldPermissions) ?>;
        const CAN_SEE_PRICE = <?= $canSeePrice ? 'true' : 'false' ?>;
        const SHOW_DETAIL_ACTIONS = <?= $showDetailActions ? 'true' : 'false' ?>;
        const isTrusteeReadonly = key => PPC_FIELD_TRUSTEE[key] === true;
        let MAX_LIMIT = <?= json_encode($limits['max_cost']) ?>; // Changed to let for dynamic updates
        let LIMIT_SOURCE = 'global'; // Track if using 'global' or 'color_specific'

        // Category Limits (JSON) -> Normalize keys to Uppercase
        const RAW_CAT_LIMITS = <?= $limits['category_limits'] ?: '{}' ?>;
        let CAT_LIMITS = {}; // Changed to let for dynamic updates
        for (let key in RAW_CAT_LIMITS) {
            if (Object.prototype.hasOwnProperty.call(RAW_CAT_LIMITS, key)) {
                CAT_LIMITS[key.toUpperCase()] = parseFloat(RAW_CAT_LIMITS[key]);
            }
        }

        // Init Color Select2
        $('.select2-color').select2({
            theme: 'bootstrap4',
            ajax: {
                url: 'get_colors.php',
                dataType: 'json',
                delay: 250,
                data: function (params) { return { q: params.term }; },
                processResults: function (data) { return { results: data.results }; }
            },
            placeholder: 'Cari Kode Warna',
            minimumInputLength: 2
        });

        // Init Grey Select2
        $('.select2-grey').select2({
            theme: 'bootstrap4',
            ajax: {
                url: 'get_grey_items.php',
                dataType: 'json',
                delay: 250,
                data: function (params) { return { q: params.term }; },
                processResults: function (data) { return { results: data.results }; }
            },
            placeholder: 'Cari Kode Grey',
            minimumInputLength: 2
        }).on('select2:select', function (e) {
            let data = e.params.data.item_data;
            if (data) {
                // 1. Calculate WEIGHT first (Gramasi * PlanQty)
                let gramasival = parseFloat(data.gramasi) || 0;
                let pickupval = parseFloat(data.pickup) || 0;
                let padryval = data.padry;

                // Populate 
                $('#kode_grey_hidden').val(data.kode_gray);

                $('#mesin').val(String(data.machine_name || '').trim());

                // Store data on weight input for re-calcs
                let $weightInput = $('input[name="weight"]');
                $weightInput.data('gramasi', gramasival);
                $weightInput.data('pickup', pickupval);
                // Also update attributes as fallback/visual check
                $weightInput.attr('data-gramasi', gramasival);
                $weightInput.attr('data-pickup', pickupval);

                calcWeight();
            }
        }).on('select2:clear', function () {
            $('#kode_grey_hidden').val('');
            $('#mesin').val('');
            let $weightInput = $('input[name="weight"]');
        });

        // Recalculate VLOT when Weight changes manually
        $('input[name="weight"]').on('input', function () {
            calcVlot();
        });

        $('.select2-color').on('select2:selecting', function (e) {
            // Detect re-selection of same value
            let currentVal = $(this).val();
            let args = e.params.args || e.params; // Normalize
            let newData = args.data;

            if (currentVal && newData && String(newData.id) === String(currentVal)) {
                handleColorSelection(newData);
                // Manually close?
                $(this).select2('close');
            }
        });

        $('.select2-color').on('select2:select', function (e) {
            handleColorSelection(e.params.data);
        });

        // Icon Click Handler
        $('#iconCheckResep').on('click', function (e) {
            e.preventDefault();
            e.stopPropagation(); // Prevent Select2 from opening

            let selectedData = $('.select2-color').select2('data');
            if (selectedData && selectedData.length > 0) {
                let data = selectedData[0].color_data;
                if (data && data.colormsid) {
                    // Force show modal via function
                    // We might want to pass a flag to force logic if needed, but the existing logic is fine:
                    // existing logic: if length > 1 -> show icon & modal. 
                    // If user clicks icon, it means they want to see the modal again.
                    // So re-calling checkProIntResep should work, as it fetches and if > 1 shows (or if 1 and auto-select).
                    checkProIntResep(data.colormsid);
                }
            }
        });

        function handleColorSelection(dataWrapper) {
            let data = dataWrapper.color_data;
            if (!data) return; // Safety

            $('#color_name').val(data.name);
            $('#color_desc').val(data.desc);
            $('#cus_color').val(data.cus_color || '');
            if (Array.isArray(data.cus_color_choices) && data.cus_color_choices.length > 1) {
                let choices = Object.fromEntries(data.cus_color_choices.map(value => [value, value]));
                Swal.fire({
                    title: 'Pilih Cus Color',
                    text: `Kode warna ${dataWrapper.id} memiliki beberapa Cus Color.`,
                    input: 'select',
                    inputOptions: choices,
                    inputPlaceholder: 'Pilih Cus Color',
                    showCancelButton: true,
                    confirmButtonText: 'Pilih',
                    cancelButtonText: 'Batal',
                    inputValidator: value => value ? undefined : 'Cus Color harus dipilih.'
                }).then(result => {
                    if (result.isConfirmed) $('#cus_color').val(result.value);
                });
            }

            // Fetch Color-Specific Limits
            let kodeWarna = dataWrapper.id;
            if (kodeWarna) {
                // Fetch Color-Specific Limits
                $.ajax({
                    url: 'get_color_limit.php',
                    data: { kode: kodeWarna },
                    dataType: 'json',
                    success: function (resp) {
                        if (resp.status === 'success') {
                            // Update global limits
                            MAX_LIMIT = resp.max_cost;
                            CAT_LIMITS['DISPERSE'] = resp.limits.DISPERSE;
                            CAT_LIMITS['REACTIVE'] = resp.limits.REACTIVE;
                            CAT_LIMITS['TOTAL'] = resp.limits.TOTAL;
                            LIMIT_SOURCE = resp.source; // Track source

                            // Show notification if using color-specific limits
                            if (resp.source === 'color_specific') {
                                let msg = `Menggunakan limit khusus untuk warna ${kodeWarna}:\n`;
                                msg += `Max Cost: ${resp.max_cost.toLocaleString()}\n`;
                                msg += `Max CF Disperse: ${resp.limits.DISPERSE}\n`;
                                msg += `Max CF Reactive: ${resp.limits.REACTIVE}`;
                                if (resp.limits.TOTAL > 0) {
                                    msg += `\nMax CF Total: ${resp.limits.TOTAL}`;
                                }
                                console.log(msg);
                            }

                            // Only show real-time validation for global limits
                            // Color-specific limits only validate on save
                            if (resp.source === 'global') {
                                calcAll();
                            }
                        }
                    },
                    error: function () {
                        console.warn('Gagal mengambil limit warna, menggunakan limit global');
                    }
                });

                // Fetch Resep Prod Info - Removed (Sync with Modal Selection instead)
                // Logic moved to checkProIntResep and modal selection handler
            }

            // Auto Fill Resep Logic
            if (data.colormsid) {
                checkProIntResep(data.colormsid);
            }
        }


        // Recalculate weight when Plan Qty changes
        $('#plan_qty').on('input', function () {
            calcWeight();
            // Also triggers calcAll() via existing handler if any
        });

        function calcWeight() {
            let gramasi = parseFloat($('input[name="weight"]').data('gramasi')) || 0;

            if (gramasi > 0) {
                let planQty = parseFloat($('#plan_qty').val()) || 0;

                // Formula: Gramasi * PlanQty
                // User Request: Format "1,045.2200" from "1045220.1"
                // This implies: (Gramasi * PlanQty) / 1000 
                // And format: 4 decimal places, Comma thousand separator

                let weight = (gramasi * planQty) / 1000;

                // Format to US Locale (Comma thousand, Dot decimal)
                let fmt = weight.toLocaleString('en-US', { minimumFractionDigits: 4, maximumFractionDigits: 4 });


                $('input[name="weight"]').val(fmt);

                // Auto-update VLOT whenever Weight is auto-calculated
                calcVlot();
            }
        }

        function calcVlot() {
            let weightStr = $('input[name="weight"]').val();
            // Remove commas for parsing
            let weight = parseFloat(weightStr.replace(/,/g, '')) || 0;
            let pickup = parseFloat($('input[name="weight"]').data('pickup')) || 0;

            if (pickup > 0) {
                let rawVlot = weight * pickup;
                // Round UP to nearest 10 (620.124 -> 630)
                let vlot = Math.ceil(rawVlot / 10) * 10;
                $('#vlot').val(vlot).trigger('input');
            }
        }

        function escapeCpHtml(value) {
            return $('<div>').text(value == null ? '' : String(value)).html();
        }

        function showCpError(xhr) {
            let response = xhr.responseJSON || {};
            let message = response.message || 'Gagal mengambil detail resep.';
            if (response.request_id) message += `<br><small>Request ID: ${escapeCpHtml(response.request_id)}</small>`;
            Swal.fire({ icon: 'error', title: 'Error', html: message });
        }

        function updateBonSelectionCount() {
            let checkedCount = $('#listBonCp .chk-bon-choice:checked').length;
            $('#countSelectedBons').text(checkedCount);
            $('#btnApplySelectedBons').prop('disabled', checkedCount === 0);
        }

        $(document).on('change', '#listBonCp .chk-bon-choice', function () {
            updateBonSelectionCount();
        });

        $('#btnSelectAllBons').on('click', function () {
            $('#listBonCp .chk-bon-choice').prop('checked', true);
            updateBonSelectionCount();
        });

        $('#btnUnselectAllBons').on('click', function () {
            $('#listBonCp .chk-bon-choice').prop('checked', false);
            updateBonSelectionCount();
        });

        $('#btnApplySelectedBons').on('click', function () {
            let noCp = $('#modal-pilih-bon').data('no-cp');
            let selectedIds = [];
            $('#listBonCp .chk-bon-choice:checked').each(function () {
                selectedIds.push($(this).val());
            });
            if (selectedIds.length === 0) return;
            $('#modal-pilih-bon').modal('hide');
            importCpDetails(noCp, selectedIds);
        });

        function importCpDetails(noCp, bonreqIds) {
            if (cpLookupRequest) cpLookupRequest.abort();
            Swal.fire({ title: 'Mengambil detail resep...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            
            let idParam = Array.isArray(bonreqIds) ? bonreqIds.join(',') : bonreqIds;
            cpLookupRequest = $.ajax({ 
                url: 'get_resep_details_by_cp.php', 
                data: { no_cp: noCp, bonreq_ids: idParam }, 
                dataType: 'json' 
            })
            .done(function (resp) {
                Swal.close();
                if (!resp.ok) return;

                // Clear existing tables
                $('#routingTablesContainer').empty();
                tableCounter = 0;
                setResepSource('PROINT');

                let routings = resp.routings || [];
                if (routings.length === 0 && resp.selected) {
                    routings = [{
                        bonreqid: resp.selected.bonreqid,
                        bonno: resp.selected.bonno,
                        rtgcode: resp.selected.rtgcode,
                        rtgname: resp.selected.rtgname,
                        vlot: resp.selected.vlot,
                        bonweight: resp.selected.bonweight,
                        planqty: resp.selected.planqty,
                        details: resp.details || []
                    }];
                }

                if (routings.length > 0) {
                    let first = routings[0];
                    if (first.planqty != null) $('#plan_qty').val(Number(first.planqty).toFixed(2));
                    if (first.bonweight != null) $('input[name="weight"]').val(Number(first.bonweight).toLocaleString('en-US', { minimumFractionDigits: 4, maximumFractionDigits: 4 }));
                    if (first.vlot != null) $('#vlot').val(Number(first.vlot).toFixed(2));

                    let rtgCodes = routings.map(r => r.rtgcode).filter(Boolean).join(', ');
                    let rtgNames = routings.map(r => r.rtgname).filter(Boolean).join(', ');
                    $('#rtg_code').val(rtgCodes);
                    $('#rtg_name').val(rtgNames);
                    $('#rtg_display').val(rtgCodes ? (rtgCodes + ' - ' + rtgNames) : '');
                }

                let warnings = [];
                routings.forEach(function (routing) {
                    let $card = createRoutingTable({
                        bonno: routing.bonno,
                        rtg_code: routing.rtgcode,
                        rtg_name: routing.rtgname,
                        machine_name: routing.machine_name || routing.assigned_machine || '',
                        vlot: routing.vlot || 0
                    });

                    (routing.details || []).forEach(function (item) {
                        if (item.warning) warnings.push(item.warning);
                        item.is_manual = 0;
                        addRowToTable($card, item);
                    });
                });

                calcAll();
                if (warnings.length) {
                    Swal.fire({ 
                        icon: 'warning', 
                        title: 'Data Master Belum Lengkap', 
                        html: '<ul class="text-left">' + warnings.map(w => `<li>${escapeCpHtml(w)}</li>`).join('') + '</ul>' 
                    });
                }
            })
            .fail(function (xhr, status) { if (status !== 'abort') { Swal.close(); showCpError(xhr); } })
            .always(function () { cpLookupRequest = null; });
        }

        function lookupCpDetails() {
            let noCp = String($('#no_cp').val() || '').trim();
            if (!noCp) return;
            if (cpLookupRequest) cpLookupRequest.abort();
            Swal.fire({ title: 'Mencari bon...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            cpLookupRequest = $.ajax({ url: 'get_resep_details_by_cp.php', data: { no_cp: noCp }, dataType: 'json' })
                .done(function (resp) {
                    Swal.close();
                    let candidates = resp.candidates || [];
                    if (candidates.length === 1) return importCpDetails(noCp, [candidates[0].bonreqid]);
                    let html = candidates.map(function (bon) {
                        let date = bon.bondate ? String(bon.bondate).substring(0, 10) : '-';
                        let machineLabel = bon.machine_name ? `<span class="badge badge-light border text-dark ml-2"><i class="fas fa-cogs mr-1 text-secondary"></i>Mesin: ${escapeCpHtml(bon.machine_name)}</span>` : '';
                        return `<label class="list-group-item list-group-item-action d-flex align-items-center py-2 px-3 mb-2 rounded border bon-choice-item" style="cursor: pointer;">
                            <div class="mr-3">
                                <input type="checkbox" class="chk-bon-choice" value="${Number(bon.bonreqid)}" style="transform: scale(1.3); cursor: pointer;" checked>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong class="text-primary font-weight-bold">Bon No - ${escapeCpHtml(bon.bonno || '-')}</strong>
                                    <span class="badge badge-secondary">${escapeCpHtml(date)}</span>
                                </div>
                                <div class="font-weight-bold text-dark mt-1">${escapeCpHtml((bon.rtgcode || '') + ' - ' + (bon.rtgname || ''))} ${machineLabel}</div>
                                <small class="text-muted">VLot: <b>${escapeCpHtml(bon.vlot || '0')}</b></small>
                            </div>
                        </label>`;
                    }).join('');
                    $('#listBonCp').html(html);
                    $('#modal-pilih-bon').data('no-cp', noCp).modal('show');
                    updateBonSelectionCount();
                })
                .fail(function (xhr, status) { if (status !== 'abort') { Swal.close(); showCpError(xhr); } })
                .always(function () { cpLookupRequest = null; });
        }

        $('#no_cp').on('blur', lookupCpDetails).on('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); $(this).blur(); }
        });

        function checkProIntResep(colormsid) {
            // Reset Icon
            $('#iconCheckResep').hide();

            $.ajax({
                url: 'get_proint_resep_headers.php',
                data: { id: colormsid },
                dataType: 'json',
                success: function (resp) {
                    if (resp.results && resp.results.length > 0) {
                        // Show icon if any recipes exist (so user can re-trigger modal)
                        $('#iconCheckResep').show();

                        // Always Show Modal (Prevent auto-select even if 1 result)
                        let html = '';
                        resp.results.forEach(r => {
                            // Format Date if exists
                            let dateStr = '';
                            if (r.resepdate) {
                                // PostgreSQL timestamp is string "YYYY-MM-DD HH:MM:SS" or similar
                                let d = new Date(r.resepdate);
                                if (!isNaN(d)) {
                                    dateStr = d.toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' });
                                } else {
                                    dateStr = r.resepdate; // Fallback to raw string
                                }
                            }

                            html += `<a href="#" class="list-group-item list-group-item-action btn-select-resep" 
                                        data-id="${r.resephdid}" 
                                        data-prodcode="${r.resepprodcode}" 
                                        data-prodname="${r.resepprodname}"
                                        data-resepno="${r.resepno}"
                                        data-resepseq="${r.resepseq}"
                                        data-resepdate="${r.resepdate}"
                                        data-reseptype="${r.reseptype}"
                                        data-nocpresep="${r.prdnmbr || ''}"
                                        data-noso="${r.no_so || ''}"
                                        data-rtgcode="${r.rtgcode || ''}"
                                        data-rtgname="${r.rtgname || ''}"
                                        data-rtgname="${r.rtgname || ''}"
                                        data-statusdesc="${r.statusdesc || ''}"
                                        data-cuscolor="${r.cuscolor || ''}">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h5 class="mb-1"><strong>${r.resepno}</strong> <span class="badge badge-info ml-1">Ver: ${r.resepseq}</span></h5>
                                            <small class="text-muted"><i class="fas fa-calendar-alt"></i> ${dateStr}</small>
                                        </div>
                                        <div class="d-flex w-100 justify-content-between">
                                            <p class="mb-1 mt-1">${r.resepprodname}</p>
                                            <small class="text-secondary">Status: ${r.statusdesc || '-'}</small>
                                        </div>
                                        <div class="d-flex w-100 justify-content-between">
                                            <small class="text-secondary"><i class="fas fa-tag"></i> CP: ${r.prdnmbr || '-'}</small>
                                            <small class="text-secondary" title="Routing"><i class="fas fa-route"></i> ${r.rtgname || '-'}</small>
                                        </div>
                                     </a>`;

                        });
                        $('#listResepProInt').html(html);
                        $('#searchResep').val(''); // Clear Search
                        $('#modal-pilih-resep').modal('show');
                    } else {
                        if (resp.error) {
                            Swal.fire('Error', resp.error, 'error');
                        } else {
                            Swal.fire('Info', 'Tidak ditemukan data resep di ProInt untuk warna ini.', 'info');
                        }
                    }
                }
            }).fail(function () {
                Swal.fire('Error', 'Terjadi kesalahan koneksi.', 'error');
            });
        }

        // Select Resep Handler
        $(document).on('click', '.btn-select-resep', function (e) {
            e.preventDefault();
            let resephdid = $(this).data('id');
            let prodcode = $(this).data('prodcode');
            let prodname = $(this).data('prodname');
            let resepno = $(this).data('resepno');
            let resepseq = $(this).data('resepseq');
            let resepdate = $(this).data('resepdate');
            let reseptype = $(this).data('reseptype');

            let nocpresep = $(this).data('nocpresep');
            let noso = $(this).data('noso');
            let rtgcode = $(this).data('rtgcode');
            let rtgname = $(this).data('rtgname');
            let statusdesc = $(this).data('statusdesc');
            let cuscolor = $(this).data('cuscolor');

            // Update Info Dasar
            $('#resepprodcode').val(prodcode);
            $('#resepprodname').val(prodname);
            $('#proint_resephdid').val(resephdid);

            // Update ProInt Details
            $('#resep_no').val(resepno);
            $('#resep_seq').val(resepseq);
            $('#resep_no_display').val(resepno + ' Ver ' + resepseq);

            // Handle Date Display (YYYY-MM-DD from 'YYYY-MM-DD HH:MM:SS')
            let dateVal = resepdate;
            if (resepdate && resepdate.length >= 10) {
                dateVal = resepdate.substring(0, 10);
            }
            $('#resep_date').val(dateVal);
            $('#resep_type').val(reseptype);

            // Update New Fields
            $('#no_cp_resep').val(nocpresep);
            $('#no_so').val(noso);
            $('#rtg_code').val(rtgcode);
            $('#rtg_name').val(rtgname);
            $('#rtg_display').val(rtgcode + ' - ' + rtgname);
            $('#status_desc').val(statusdesc);
            if (cuscolor) $('#cus_color').val(cuscolor);

            $('#modal-pilih-resep').modal('hide');

            loadResepDetails(resephdid);
        });

        // Filter Search in Modal
        $('#searchResep').on('keyup', function () {
            let value = $(this).val().toLowerCase();
            $('#listResepProInt .btn-select-resep').filter(function () {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });

        function setResepSource(source) {
            $('#is_manual').val(source === 'MANUAL' ? 1 : 0);
        }

        function loadResepDetails(resephdid) {
            Swal.showLoading();

            $.ajax({
                url: 'get_proint_resep_details.php',
                data: { id: resephdid },
                dataType: 'json',
                success: function (resp) {
                    Swal.close();
                    setResepSource('PROINT');

                    $('#routingTablesContainer').empty();
                    tableCounter = 0;

                    let rtgCode = $('#rtg_code').val() || '';
                    let rtgName = $('#rtg_name').val() || '';
                    let vlot = parseFloat($('#vlot').val()) || 0;

                    let machineName = $('#mesin').val() || '';
                    let $card = createRoutingTable({
                        rtg_code: rtgCode,
                        rtg_name: rtgName,
                        bonno: $('#resep_no').val() || '',
                        machine_name: machineName,
                        vlot: vlot
                    });

                    if (resp.results && resp.results.length > 0) {
                        let warnings = [];
                        resp.results.forEach(item => {
                            if (item.warning) warnings.push(item.warning);
                            let rowData = {
                                kode: item.kode,
                                name: item.name,
                                category: item.category,
                                uom: item.uom,
                                cf: item.cf,
                                uom_cf: item.uom_cf,
                                std_price: item.std_price,
                                price_source: item.price_source,
                                satuan: item.satuan,
                                receipe: 0,
                                total: 0,
                                is_manual: 0
                            };
                            addRowToTable($card, rowData);
                        });

                        calcAll();

                        if (warnings.length > 0) {
                            Swal.fire({
                                title: 'Peringatan Data Belum Lengkap',
                                html: '<ul class="text-left">' + warnings.map(w => `<li>${w}</li>`).join('') + '</ul>',
                                icon: 'warning'
                            });
                        }
                    } else {
                        Swal.fire('Info', 'Detail resep kosong.', 'info');
                    }
                },
                error: function () {
                    Swal.fire('Error', 'Gagal memuat detail resep.', 'error');
                }
            });
        }

        // Helper to format currency (IDR)
        function fmtNum(n) {
            if (n === null || n === undefined || isNaN(parseFloat(n))) return '';
            return 'Rp ' + parseFloat(n).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
        }

        // Helper to parse currency back to number
        function parseNum(str) {
            if (!str) return 0;
            let clean = str.replace(/[Rp\s.]/g, '').replace(',', '.');
            return parseFloat(clean) || 0;
        }

        // Helper for US number format (4 decimals)
        function fmtUS(n) {
            if (n === null || n === undefined || n === '') return '';
            let val = parseFloat(n);
            if (isNaN(val)) return '';
            return val.toLocaleString('en-US', { minimumFractionDigits: 4, maximumFractionDigits: 4 });
        }

        let tableCounter = 0;

        function createRoutingTable(meta = {}) {
            tableCounter++;
            let tIdx = tableCounter;
            let rtgCode = meta.rtg_code || '';
            let rtgName = meta.rtg_name || '';
            let bonno = meta.bonno || '';
            let machineName = meta.machine_name || '';
            let vlot = meta.vlot != null ? parseFloat(meta.vlot) : (parseFloat($('#vlot').val()) || 0);

            let titleLabel = (rtgCode || rtgName) ? `${rtgCode} - ${rtgName}` : `Tabel Routing #${tIdx}`;
            let bonBadge = bonno ? `<span class="badge badge-info ml-2 font-weight-normal"><i class="fas fa-ticket-alt mr-1"></i>Bon: ${escapeCpHtml(bonno)}</span>` : '';
            let machineBadge = `<span class="badge ${machineName ? 'badge-secondary' : 'badge-light border text-muted'} ml-2 font-weight-normal table-machine-badge" title="Klik untuk ubah nama mesin" style="cursor: pointer;"><i class="fas fa-cogs mr-1"></i>Mesin: <span class="table-machine-name-text">${escapeCpHtml(machineName || '-')}</span> <i class="fas fa-pencil-alt ml-1" style="font-size: 0.7em; opacity: 0.8;"></i></span>`;

            let cardHtml = `
            <div class="card routing-card mb-4 shadow-sm border" id="routing_card_${tIdx}" data-table-index="${tIdx}">
                <input type="hidden" class="table-order-input" name="table_orders[]" value="${tIdx}">
                <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap py-2">
                    <div class="d-flex align-items-center flex-wrap">
                        <span class="table-drag-handle" title="Seret untuk ubah urutan tabel"><i class="fas fa-grip-vertical"></i></span>
                        <span class="badge badge-primary mr-2 font-weight-bold">Tabel #${tIdx}</span>
                        <h6 class="card-title font-weight-bold m-0 text-navy">${escapeCpHtml(titleLabel)} ${bonBadge} ${machineBadge}</h6>
                        <input type="hidden" class="table-meta-rtg-code" value="${escapeCpHtml(rtgCode)}">
                        <input type="hidden" class="table-meta-rtg-name" value="${escapeCpHtml(rtgName)}">
                        <input type="hidden" class="table-meta-bonno" value="${escapeCpHtml(bonno)}">
                        <input type="hidden" class="table-meta-machine-name" value="${escapeCpHtml(machineName)}">
                    </div>
                    <div class="card-tools d-flex align-items-center flex-wrap mt-2 mt-md-0">
                        <div class="input-group input-group-sm mr-2" style="width: 175px;">
                            <div class="input-group-prepend">
                                <span class="input-group-text font-weight-bold bg-white text-muted">VLot:</span>
                            </div>
                            <input type="number" step="0.01" class="form-control table-vlot-input text-right font-weight-bold" value="${vlot.toFixed(2)}" placeholder="0.00">
                        </div>
                        <button type="button" class="btn btn-primary btn-sm btn-add-row-table mr-2" ${SHOW_DETAIL_ACTIONS ? '' : 'disabled'}>
                            <i class="fas fa-plus mr-1"></i> Tambah Item
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm btn-remove-table" title="Hapus Tabel Routing Ini">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm mb-0 routing-table" id="detailTable_${tIdx}">
                            <thead style="background-color: #dbebf9;">
                                <tr>
                                    <th style="width: 22px; padding: 4px;" title="Urutan"><i class="fas fa-grip-vertical text-muted"></i></th>
                                    <th style="width: 10%;">Kode</th>
                                    <th style="width: 25%;">Name</th>
                                    <th style="width: 10%;">Category</th>
                                    <th style="width: 7%;">Qty</th>
                                    <th style="width: 4%;">Uom</th>
                                    <th style="width: 6%;">Cf</th>
                                    <th style="width: 4%;">Uom Cf</th>
                                    ${CAN_SEE_PRICE ? '<th style="width: 14%;">Price</th><th style="width: 15%;">Total</th>' : ''}
                                    ${SHOW_DETAIL_ACTIONS ? '<th style="width: 5%;">Aksi</th>' : ''}
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Rows will be added here -->
                            </tbody>
                            ${CAN_SEE_PRICE ? `
                            <tfoot>
                                <tr class="bg-light">
                                    <td colspan="${SHOW_DETAIL_ACTIONS ? 7 : 6}" class="text-right font-weight-bold align-middle">Subtotal Tabel #${tIdx}</td>
                                    <td colspan="2" class="align-middle">
                                        <input type="text" class="form-control-plaintext font-weight-bold text-right table-subtotal" readonly value="Rp 0">
                                    </td>
                                    ${SHOW_DETAIL_ACTIONS ? '<td></td>' : ''}
                                </tr>
                            </tfoot>` : ''}
                        </table>
                    </div>
                </div>
            </div>`;

            let $card = $(cardHtml);
            $('#routingTablesContainer').append($card);

            // Init SortableJS for rows inside this table
            let tbody = $card.find('.routing-table tbody')[0];
            if (tbody) {
                Sortable.create(tbody, {
                    handle: '.row-drag-handle',
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    dragClass: 'sortable-drag',
                    onEnd: function() {
                        calcAll();
                    }
                });
            }

            return $card;
        }

        // Init SortableJS for routing card containers (table level)
        Sortable.create(document.getElementById('routingTablesContainer'), {
            handle: '.table-drag-handle',
            animation: 200,
            ghostClass: 'sortable-ghost',
            dragClass: 'sortable-drag',
            onEnd: function() {
                calcAll();
            }
        });

        function addRowToTable($card, data = null) {
            rowIdx++;
            let tIdx = $card.data('table-index');
            let rtgCode = $card.find('.table-meta-rtg-code').val() || '';
            let rtgName = $card.find('.table-meta-rtg-name').val() || '';
            let bonno = $card.find('.table-meta-bonno').val() || '';
            let machineName = $card.find('.table-meta-machine-name').val() || (data && data.machine_name ? data.machine_name : '');
            let vlot = parseFloat($card.find('.table-vlot-input').val()) || 0;

            let usedType = data && data.used_type ? data.used_type : 'G';
            let isUnmapped = data && data.is_unmapped == 1;

            let itemKode = data ? (data.display_codeprod_proint || data.codeprod || data.kode || '') : '';
            let selectHtml = '';
            if (itemKode && itemKode !== '-') {
                selectHtml = `<option value="${itemKode}" selected>${itemKode} - ${data.name}</option>`;
            } else if (data && data.name) {
                selectHtml = `<option value="${itemKode || '-'}" selected>${itemKode || '-'} - ${data.name}</option>`;
            }

            if (data && data.cf && (data.receipe === null || data.receipe === undefined || data.receipe === '')) {
                if (usedType === 'P') {
                    let weightStr = $('input[name="weight"]').val() || '0';
                    let weight = parseFloat(weightStr.replace(/,/g, '')) || 0;
                    data.receipe = weight * parseFloat(data.cf) * 10;
                } else if (vlot > 0) {
                    data.receipe = vlot * parseFloat(data.cf);
                }
            }

            let isManual = (data && typeof data.is_manual !== 'undefined') ? data.is_manual : (data ? 0 : 1);

            const kodeDisabled = isTrusteeReadonly('detail_items') || isTrusteeReadonly('detail_kode') ? 'disabled' : '';
            const qtyReadonly = isTrusteeReadonly('detail_items') || isTrusteeReadonly('detail_qty') ? 'readonly' : '';
            const cfReadonly = isTrusteeReadonly('detail_items') || isTrusteeReadonly('detail_cf') ? 'readonly' : '';
            const uomCfReadonly = isTrusteeReadonly('detail_items') || isTrusteeReadonly('detail_uom_cf') ? 'readonly' : '';
            const removeButton = isTrusteeReadonly('detail_items')
                ? ''
                : '<button type="button" class="btn btn-danger btn-xs btn-remove"><i class="fas fa-trash"></i></button>';

            const priceCells = CAN_SEE_PRICE ? `<td>
                    <input type="text" class="form-control form-control-sm item-price" name="items[${rowIdx}][std_price]" readonly value="${data ? fmtNum(data.std_price) : ''}">
                    <input type="hidden" class="item-price-satuan" name="items[${rowIdx}][price_satuan]" value="${data ? (data.satuan || data.price_satuan || '') : ''}">
                    <input type="hidden" class="item-price-source" name="items[${rowIdx}][price_source]" value="${data ? (data.price_source || '') : ''}">
                    <small class="text-danger price-source" style="font-size: 0.65rem; display:block;"></small>
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm row-total" readonly value="${data && data.total ? fmtNum(data.total) : 'Rp 0'}">
                    <small class="text-muted total-info" style="font-size: 0.65rem; display:block;"></small>
                    <div class="total-info-container"></div>
                </td>` : `<td style="display:none">
                    <input type="hidden" class="item-price" name="items[${rowIdx}][std_price]" value="${data ? fmtNum(data.std_price) : ''}">
                    <input type="hidden" class="item-price-satuan" name="items[${rowIdx}][price_satuan]" value="${data ? (data.satuan || data.price_satuan || '') : ''}">
                    <input type="hidden" class="item-price-source" name="items[${rowIdx}][price_source]" value="${data ? (data.price_source || '') : ''}">
                    <input type="hidden" class="row-total" value="${data && data.total ? data.total : 0}">
                </td>`;
            const actionCell = SHOW_DETAIL_ACTIONS ? `<td class="text-center align-middle">${removeButton}</td>` : '';

            let html = `
            <tr id="row_${rowIdx}" class="detail-item-row" data-table-index="${tIdx}">
                <td class="row-drag-handle align-middle text-center" title="Seret untuk ubah urutan"><i class="fas fa-grip-vertical"></i></td>
                <input type="hidden" name="items[${rowIdx}][table_index]" class="item-table-index" value="${tIdx}">
                <input type="hidden" name="items[${rowIdx}][sort_order]" class="item-sort-order" value="0">
                <input type="hidden" name="items[${rowIdx}][bonno]" class="item-bonno" value="${escapeCpHtml(bonno)}">
                <input type="hidden" name="items[${rowIdx}][rtg_code]" class="item-rtg-code" value="${escapeCpHtml(rtgCode)}">
                <input type="hidden" name="items[${rowIdx}][rtg_name]" class="item-rtg-name" value="${escapeCpHtml(rtgName)}">
                <input type="hidden" name="items[${rowIdx}][machine_name]" class="item-machine-name" value="${escapeCpHtml(machineName)}">
                <input type="hidden" name="items[${rowIdx}][vlot]" class="item-vlot" value="${vlot}">
                <input type="hidden" name="items[${rowIdx}][used_type]" class="item-used-type" value="${escapeCpHtml(usedType)}">  
                <td>
                    <div class="d-flex flex-column">
                        <select class="form-control select2-item" name="${kodeDisabled ? '' : `items[${rowIdx}][kode]`}" style="width: 100%;" required ${kodeDisabled}>
                            ${selectHtml}
                        </select>
                        ${kodeDisabled ? `<input type="hidden" name="items[${rowIdx}][kode]" value="${itemKode}">` : ''}
                        <div class="mt-1 d-flex align-items-center flex-wrap">
                            <span class="badge badge-success item-status-proint mr-1" style="${isManual == 0 ? '' : 'display:none;'} font-size: 0.65rem;">PROINT</span>
                            <span class="badge badge-warning item-status-manual mr-1" style="${isManual == 1 ? '' : 'display:none;'} font-size: 0.65rem;">MANUAL</span>
                            <span class="badge ${usedType === 'P' ? 'badge-primary' : 'badge-secondary'} mr-1" style="font-size: 0.65rem;">${usedType === 'P' ? '% (P)' : 'G/L (G)'}</span>
                            ${isUnmapped ? `<span class="badge badge-danger mr-1" style="font-size: 0.65rem;" title="Belum terdaftar di Master Obat lokal">UNMAPPED</span>` : ''}
                            <input type="hidden" class="item-is-manual" name="items[${rowIdx}][is_manual]" value="${isManual}">
                        </div>
                    </div>
                </td>
                <td><input type="text" class="form-control form-control-sm item-name" name="items[${rowIdx}][name]" readonly value="${data ? data.name : ''}" title="${data ? data.name : ''}"></td>
                <td><input type="text" class="form-control form-control-sm item-category" name="items[${rowIdx}][category]" readonly value="${data ? data.category : ''}" title="${data ? data.category : ''}"></td>
                <td><input type="text" class="form-control form-control-sm item-qty" name="items[${rowIdx}][receipe]" required ${qtyReadonly} value="${data ? fmtUS(data.receipe) : ''}" placeholder="0.00"></td>
                <td><input type="text" class="form-control form-control-sm item-uom" name="items[${rowIdx}][uom]" readonly value="${data ? data.uom : ''}" title="${data ? data.uom : ''}"></td>
                <td><input type="text" class="form-control form-control-sm item-cf" name="items[${rowIdx}][cf]" required ${cfReadonly} value="${data ? fmtUS(data.cf) : ''}"></td>
                <td><input type="text" class="form-control form-control-sm item-uom-cf" name="items[${rowIdx}][uom_cf]" ${uomCfReadonly} value="${data ? data.uom_cf : ''}" title="${data ? data.uom_cf : ''}"></td>
                ${priceCells}
                ${actionCell}
            </tr>`;

            let $row = $(html);
            $card.find('.routing-table tbody').append($row);
            initRowSelect2($row);
            calcRow($row);

            let prodCode = data ? (data.codeprod || data.display_codeprod_proint || data.kode || '') : '';
            if (prodCode && (!data.std_price || data.std_price == 0)) {
                $.ajax({
                    url: 'get_item_price.php',
                    data: { prodcode: prodCode },
                    dataType: 'json'
                }).done(function (resp) {
                    $row.find('.item-price').val(fmtNum(resp.price || 0));
                    $row.find('.item-price-source').val(resp.source || '');
                    $row.find('.item-price-satuan').val(resp.satuan || '');
                    let sourceText = resp.source === 'PO' ? 'Price PO' : (resp.source === 'Template' ? 'Std Price' : '');
                    if (sourceText && resp.satuan) sourceText += ' Per ' + resp.satuan;
                    $row.find('.price-source').text(sourceText);
                    calcRow($row);
                });
            }

            return $row;
        }

        function initRowSelect2($row) {
            let $select = $row.find('.select2-item');
            $select.select2({
                theme: 'bootstrap4',
                ajax: {
                    url: 'get_items.php',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) { return { q: params.term }; },
                    processResults: function (data) { return { results: data.results }; }
                },
                placeholder: 'Cari Kode / Nama',
                minimumInputLength: 1,
                dropdownAutoWidth: true,
                templateResult: function (data) {
                    if (data.loading) return data.text;
                    let code = data.id || '';
                    let name = (data.item_data && data.item_data.name) ? data.item_data.name : (data.text.split(' - ')[1] || '');
                    return $(`<span><b>${code}</b> | ${name}</span>`);
                },
                templateSelection: function (data) {
                    return data.id;
                }
            });

            $select.on('select2:select', function (e) {
                let item = e.params.data.item_data;
                let $tr = $(this).closest('tr');
                $tr.find('.item-name').val(item.name).attr('title', item.name);
                $tr.find('.item-uom-cf').val(item.uom_raw).attr('title', item.uom_raw);
                $tr.find('.item-uom').val(item.uom).attr('title', item.uom);
                $tr.find('.item-category').val(item.group_obat).attr('title', item.group_obat);
                $tr.find('.item-price').val(fmtNum(0));
                $tr.find('.price-source').text('');

                $tr.find('.item-status-proint').show();
                $tr.find('.item-status-manual').hide();
                $tr.find('.item-is-manual').val(0);

                calcRowQty($tr);

                let prodCode = item.codeprod || item.kode;
                if (prodCode) {
                    $.ajax({
                        url: 'get_item_price.php',
                        data: { prodcode: prodCode },
                        dataType: 'json',
                        success: function (resp) {
                            if (resp.price) {
                                $tr.find('.item-price').val(fmtNum(resp.price));
                                let sourceText = '';
                                if (resp.source === 'PO') sourceText = 'Price PO';
                                else if (resp.source === 'Template') sourceText = 'Std Price';
                                if (sourceText && resp.satuan) sourceText += ' Per ' + resp.satuan;
                                $tr.find('.price-source').text(sourceText);
                                $tr.find('.item-price-satuan').val(resp.satuan || '');
                                $tr.find('.item-price-source').val(resp.source || '');
                                calcRow($tr);
                            }
                        }
                    });
                }
                calcRow($tr);
            });
        }

        function calcRow($row) {
            let qtyStr = $row.find('.item-qty').val();
            let qtyFn = qtyStr.replace(/,/g, '');
            let qty = parseFloat(qtyFn) || 0;

            let priceStr = $row.find('.item-price').val();
            let price = parseNum(priceStr);

            let total = qty * price;
            let uom = $row.find('.item-uom').val().toUpperCase().trim();
            let totalInfo = '';

            if (uom === 'GR' || uom === 'G/L') {
                total = total / 1000;
                totalInfo = '<small class="text-muted font-weight-bold d-block mt-1" style="font-size:0.7em">(Total Per 1000 GR)</small>';
            }

            $row.find('.row-total').val(fmtNum(total));
            $row.find('.total-info-container').html(totalInfo);

            calcAll();
        }

        function calcRowQty($row) {
            let $card = $row.closest('.routing-card');
            let vlot = parseFloat($card.find('.table-vlot-input').val()) || 0;
            $row.find('.item-vlot').val(vlot);

            let cfStr = $row.find('.item-cf').val();
            let cf = parseFloat(cfStr.replace(/,/g, '')) || 0;

            let usedType = $row.find('.item-used-type').val() || 'G';
            let qty = 0;
            if (usedType === 'P') {
                let weightStr = $('input[name="weight"]').val() || '0';
                let weight = parseFloat(weightStr.replace(/,/g, '')) || 0;
                qty = weight * cf * 10;
            } else {
                qty = vlot * cf;
            }
            $row.find('.item-qty').val(fmtUS(qty));

            calcRow($row);
        }

        function calcAll() {
            let grandTotal = 0;
            let catTotals = {};
            let catCFTotals = {};

            $('.routing-card').each(function () {
                let $card = $(this);
                let tableSubtotal = 0;

                $card.find('.row-total').each(function () {
                    let val = parseNum($(this).val());
                    tableSubtotal += val;
                    grandTotal += val;

                    let $row = $(this).closest('tr');
                    let cfStr = $row.find('.item-cf').val();
                    let cf = parseFloat(cfStr.replace(/,/g, '')) || 0;

                    let cat = $row.find('.item-category').val() || 'Others';
                    cat = cat.trim();
                    if (cat === '') cat = 'Others';

                    if (!catTotals[cat]) catTotals[cat] = 0;
                    catTotals[cat] += val;

                    if (!catCFTotals[cat]) catCFTotals[cat] = 0;
                    catCFTotals[cat] += cf;
                });

                $card.find('.table-subtotal').val(fmtNum(tableSubtotal));
            });

            $('#grandVal').val(fmtNum(grandTotal));
            $('#badgeGrandTotalSemuaTabel').text('Grand Total: ' + fmtNum(grandTotal));

            let planQty = parseFloat($('#plan_qty').val()) || 0;
            let cost = 0;

            let hasLimitError = false;
            let limitMsg = '';

            let htmlCat = '';
            if (planQty > 0) {
                cost = grandTotal / planQty;

                for (let c in catTotals) {
                    let cTot = catTotals[c];
                    let cCost = cTot / planQty;
                    let cUpper = c.toUpperCase();

                    let totalCF = catCFTotals[c] || 0;
                    let maxCF = parseFloat(CAT_LIMITS[cUpper]) || 0;
                    let cfClass = '';
                    let limitInfo = '';

                    if (maxCF > 0 && LIMIT_SOURCE === 'global') {
                        if (totalCF > maxCF) {
                            limitInfo = `<br><span class="text-xs text-danger">Max: ${fmtUS(maxCF)}</span>`;
                            cfClass = 'text-danger font-weight-bold';
                            hasLimitError = true;
                            limitMsg = `Limit CF untuk kategori ${c} terlampaui! (Max: ${maxCF})`;
                        }
                    }

                    htmlCat += `<tr>
                                <td class="pl-3 align-middle font-weight-bold">${c}</td>
                                <td class="text-center align-middle ${cfClass}">
                                    ${fmtUS(totalCF)}
                                    ${limitInfo}
                                </td>
                                <td class="text-right pr-3 font-weight-bold text-dark align-middle">${fmtNum(cCost)}</td>
                             </tr>`;
                }
            } else {
                htmlCat = '<tr><td colspan="3" class="text-center text-muted font-italic py-3">Belum ada data</td></tr>';
            }
            $('#costDetails').html(htmlCat);
            $('#valCost').html(fmtNum(cost));

            let isInvalid = false;
            let msg = '';
            if (hasLimitError) {
                isInvalid = true;
                msg = limitMsg;
            }

            if (MAX_LIMIT !== null && cost > parseFloat(MAX_LIMIT) && LIMIT_SOURCE === 'global') {
                $('#valCost').css('color', 'red');
                $('#valCost').append(`<div class="text-xs text-danger mt-1" style="font-weight:bold;">MAX LIMIT ${fmtNum(MAX_LIMIT)}</div>`);
                isInvalid = true;
                msg = 'Cost melebihi Batas Maksimal!';
            } else {
                $('#valCost').css('color', '');
            }

            if (isInvalid && LIMIT_SOURCE === 'global') {
                $('#btnSave').prop('disabled', true);
                $('#btnSave').html(`<i class="fas fa-ban"></i> ${msg}`);
            } else {
                $('#btnSave').prop('disabled', false);
                $('#btnSave').html('<i class="fas fa-save"></i> Simpan Resep');
            }
        }

        function updateRowStatus($row, status) {
            if (status === 'MANUAL') {
                $row.find('.item-is-manual').val(1);
                $row.find('.item-status-manual').show();
                $row.find('.item-status-proint').hide();
            } else {
                $row.find('.item-is-manual').val(0);
                $row.find('.item-status-manual').hide();
                $row.find('.item-status-proint').show();
            }
        }

        // Monitor for Manual Edits
        $(document).on('input change select2:select', '.routing-table input, .routing-table select', function () {
            setResepSource('MANUAL');
            let $row = $(this).closest('tr');
            updateRowStatus($row, 'MANUAL');
        });

        // Add row to specific table
        $(document).on('click', '.btn-add-row-table', function () {
            let $card = $(this).closest('.routing-card');
            addRowToTable($card);
            setResepSource('MANUAL');
        });

        // Remove row
        $(document).on('click', '.btn-remove', function () {
            let $card = $(this).closest('.routing-card');
            $(this).closest('tr').remove();
            calcAll();
            setResepSource('MANUAL');
        });

        // Remove whole table
        $(document).on('click', '.btn-remove-table', function () {
            let $card = $(this).closest('.routing-card');
            if ($('.routing-card').length <= 1) {
                Swal.fire('Info', 'Minimal harus ada 1 tabel routing.', 'info');
                return;
            }
            Swal.fire({
                title: 'Hapus Tabel Routing Ini?',
                text: 'Seluruh baris item dalam tabel ini akan dihapus.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    $card.remove();
                    calcAll();
                    setResepSource('MANUAL');
                }
            });
        });

        // Add new routing table manually
        $('#btnAddRoutingTable').click(function () {
            Swal.fire({
                title: 'Tambah Tabel Routing Baru',
                html: `
                    <div class="form-group text-left mb-2">
                        <label class="small font-weight-bold">Kode Routing:</label>
                        <input type="text" id="swal_rtg_code" class="form-control form-control-sm" placeholder="Contoh: 05001">
                    </div>
                    <div class="form-group text-left mb-2">
                        <label class="small font-weight-bold">Nama Routing:</label>
                        <input type="text" id="swal_rtg_name" class="form-control form-control-sm" placeholder="Contoh: PES">
                    </div>
                    <div class="form-group text-left mb-2">
                        <label class="small font-weight-bold">Nama Mesin (Opsional):</label>
                        <input type="text" id="swal_machine_name" class="form-control form-control-sm" placeholder="Contoh: JD6, CPB 5, MIKWANG 3...">
                    </div>
                    <div class="form-group text-left mb-2">
                        <label class="small font-weight-bold">No Bon (Opsional):</label>
                        <input type="text" id="swal_bonno" class="form-control form-control-sm" placeholder="Contoh: 05001.26.05.0077">
                    </div>
                    <div class="form-group text-left mb-0">
                        <label class="small font-weight-bold">VLot Routing:</label>
                        <input type="number" step="0.01" id="swal_vlot" class="form-control form-control-sm" value="${$('#vlot').val() || '0'}">
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-plus mr-1"></i> Tambah',
                cancelButtonText: 'Batal',
                focusConfirm: false,
                preConfirm: () => {
                    return {
                        rtg_code: $('#swal_rtg_code').val().trim(),
                        rtg_name: $('#swal_rtg_name').val().trim(),
                        bonno: $('#swal_bonno').val().trim(),
                        machine_name: $('#swal_machine_name').val().trim(),
                        vlot: parseFloat($('#swal_vlot').val()) || 0
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    let $card = createRoutingTable(result.value);
                    for (let i = 0; i < 3; i++) addRowToTable($card);
                    setResepSource('MANUAL');
                    calcAll();
                }
            });
        });

        // Click on machine badge in table header to edit machine name
        $(document).on('click', '.table-machine-badge', function () {
            let $card = $(this).closest('.routing-card');
            let $input = $card.find('.table-meta-machine-name');
            let $badge = $(this);
            let $text = $badge.find('.table-machine-name-text');
            let currentVal = $input.val() || '';
            Swal.fire({
                title: 'Ubah Nama Mesin Routing',
                input: 'text',
                inputValue: currentVal,
                inputPlaceholder: 'Contoh: JD6, CPB 5, MIKWANG 3...',
                showCancelButton: true,
                confirmButtonText: 'Simpan',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    let newVal = (result.value || '').trim();
                    $input.val(newVal);
                    $card.find('.item-machine-name').val(newVal);
                    $text.text(newVal || '-');
                    if (newVal) {
                        $badge.removeClass('badge-light border text-muted').addClass('badge-secondary');
                    } else {
                        $badge.removeClass('badge-secondary').addClass('badge-light border text-muted');
                    }
                }
            });
        });

        // When table VLot input changes: recalculate all rows with used_type === 'G' in that table!
        $(document).on('input', '.table-vlot-input', function () {
            let $card = $(this).closest('.routing-card');
            $card.find('tbody tr').each(function () {
                let usedType = $(this).find('.item-used-type').val() || 'G';
                if (usedType === 'G') {
                    calcRowQty($(this));
                }
            });
        });

        // When header Weight changes: recalculate all rows with used_type === 'P' across all tables!
        $('input[name="weight"]').on('input', function () {
            $('.routing-table tbody tr').each(function () {
                let usedType = $(this).find('.item-used-type').val() || 'G';
                if (usedType === 'P') {
                    calcRowQty($(this));
                }
            });
        });

        // Plan Qty change: recalculate all cost summaries
        $('#plan_qty').on('input', function () {
            calcAll();
        });

        $(document).on('input', '.item-cf', function () {
            let $row = $(this).closest('tr');
            calcRowQty($row);
        });

        $(document).on('input', '.item-qty', function () {
            calcRow($(this).closest('tr'));
        });

        $(document).on('change', '.item-qty, .item-cf', function () {
            let valStr = $(this).val();
            if (valStr) {
                let val = parseFloat(valStr.replace(/,/g, '')) || 0;
                $(this).val(fmtUS(val));
            }
        });

        $(document).on('input', '.item-uom-cf', function () {
            calcRow($(this).closest('tr'));
        });

        // Populate existing details if Edit mode, ELSE add 1 default table with 5 empty rows
        <?php if (!empty($details)): ?>
            let savedDetails = <?= json_encode($details) ?>;
            let grouped = {};
            savedDetails.forEach(d => {
                let tIdx = (d.table_index !== undefined && d.table_index !== null && d.table_index !== '') ? d.table_index : 1;
                if (!grouped[tIdx]) grouped[tIdx] = [];
                grouped[tIdx].push(d);
            });
            for (let tIdx in grouped) {
                let itemsInTable = grouped[tIdx];
                let first = itemsInTable[0] || {};
                let $card = createRoutingTable({
                    rtg_code: first.rtg_code || '',
                    rtg_name: first.rtg_name || '',
                    bonno: first.bonno || '',
                    machine_name: first.machine_name || <?= json_encode($data['mesin'] ?? '') ?> || '',
                    vlot: first.vlot != null ? first.vlot : 0
                });
                itemsInTable.forEach(d => addRowToTable($card, d));
            }
            setTimeout(calcAll, 500);
        <?php else: ?>
            // Default 1 table with 5 empty rows for new recipe
            let $defCard = createRoutingTable();
            for (let i = 0; i < 5; i++) addRowToTable($defCard);
        <?php endif; ?>

        // Validation function to check limits before save
        function validateLimitsBeforeSave() {
            let catTotals = {};
            let totalCost = 0;

            $('.routing-table tbody tr').each(function () {
                let cat = $(this).find('.item-category').val();
                if (!cat) return;

                cat = cat.toUpperCase();
                let cfStr = $(this).find('.item-cf').val() || '';
                let cf = parseFloat(cfStr.replace(/,/g, '')) || 0;

                let costStr = $(this).find('.row-total').val() || '';
                let cost = parseNum(costStr);

                if (!catTotals[cat]) {
                    catTotals[cat] = { cf: 0, cost: 0 };
                }
                catTotals[cat].cf += cf;
                catTotals[cat].cost += cost;
                totalCost += cost;
            });

            let warnings = [];
            let planQty = parseFloat($('#plan_qty').val()) || 1;
            let costPerMeter = totalCost / planQty;

            if (MAX_LIMIT && MAX_LIMIT < 999999999 && costPerMeter > MAX_LIMIT) {
                let costFormatted = 'Rp ' + costPerMeter.toLocaleString('id-ID', { minimumFractionDigits: 2 });
                let limitFormatted = 'Rp ' + MAX_LIMIT.toLocaleString('id-ID', { minimumFractionDigits: 2 });
                warnings.push(`Cost/Meter: ${costFormatted} > Limit: ${limitFormatted}`);
            }

            for (let cat in catTotals) {
                if (CAT_LIMITS[cat] && CAT_LIMITS[cat] < 999999999 && catTotals[cat].cf > CAT_LIMITS[cat]) {
                    warnings.push(`CF ${cat}: ${catTotals[cat].cf.toFixed(2)} > Limit: ${CAT_LIMITS[cat]}`);
                }
            }

            if (CAT_LIMITS['TOTAL'] && CAT_LIMITS['TOTAL'] < 999999999) {
                let totalCF = 0;
                for (let cat in catTotals) {
                    totalCF += catTotals[cat].cf;
                }
                if (totalCF > CAT_LIMITS['TOTAL']) {
                    warnings.push(`Total CF: ${totalCF.toFixed(2)} > Limit: ${CAT_LIMITS['TOTAL']}`);
                }
            }

            return warnings;
        }

        let isSubmitting = false;

        // Before submit: write current DOM order into sort_order and table_order hidden inputs
        function syncDragOrderBeforeSubmit() {
            // 1. Update table_order for each routing card
            $('#routingTablesContainer .routing-card').each(function(tPos) {
                $(this).find('.table-order-input').val(tPos + 1);
            });

            // 2. Update sort_order for each row per table
            $('#routingTablesContainer .routing-card').each(function(tPos) {
                let tableOrder = tPos + 1;
                $(this).find('.routing-table tbody tr.detail-item-row').each(function(rPos) {
                    $(this).find('.item-sort-order').val(rPos + 1);
                    // Also update table_index to reflect current table position
                    $(this).find('.item-table-index').val(tableOrder);
                });
            });
        }

        // Submit Handler
        $('#resepForm').on('submit', function (e) {
            e.preventDefault();

            if (isSubmitting) return;
            if ($('#btnSave').prop('disabled')) return;

            // Sync drag order before validation
            syncDragOrderBeforeSubmit();

            // Validate limits before saving
            let warnings = validateLimitsBeforeSave();
            if (warnings.length > 0) {
                isSubmitting = false;
                calcAll();

                let warningMsg = '<strong>Penggunaan Cost/CF Terlampaui:</strong><br><br>';
                warningMsg += warnings.join('<br>');

                Swal.fire({
                    title: 'Peringatan Limit',
                    html: warningMsg,
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                return;
            }

            isSubmitting = true;
            $('#btnSave').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
            submitForm();
        });

        function escapeDuplicateNoCpText(value) {
            return $('<div>').text(value == null ? '' : String(value)).html();
        }

        function resetSaveButton() {
            isSubmitting = false;
            calcAll();
            $('#btnSave').prop('disabled', false).html('<i class="fas fa-save"></i> Simpan Resep');
        }

        async function confirmDuplicateNoCp(resp) {
            const rows = (resp.duplicates || []).map(item => `<tr>
                <td><a href="view_resep.php?resep_id=${encodeURIComponent(item.id)}" target="_blank" rel="noopener">#${escapeDuplicateNoCpText(item.id)}</a></td>
                <td>${escapeDuplicateNoCpText(item.kode_warna)}</td>
                <td>${escapeDuplicateNoCpText(item.cus_color)}</td>
                <td>${escapeDuplicateNoCpText(item.status)}</td>
                <td>${escapeDuplicateNoCpText(item.created_at)}<br><small>${escapeDuplicateNoCpText(item.created_by)}</small></td>
            </tr>`).join('');
            const result = await Swal.fire({
                icon: 'warning',
                title: 'No CP Sudah Pernah Digunakan',
                html: `<p>No CP <strong>${escapeDuplicateNoCpText(resp.no_cp)}</strong> ditemukan pada resep berikut:</p>
                    <div class="table-responsive"><table class="table table-bordered table-sm text-left">
                    <thead><tr><th>Resep</th><th>Kode Warna</th><th>Cus Color</th><th>Status</th><th>Dibuat</th></tr></thead>
                    <tbody>${rows}</tbody></table></div><p class="mb-0">Tetap simpan resep ini?</p>`,
                showCancelButton: true,
                confirmButtonText: 'Tetap Simpan',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#d97706',
                width: '850px'
            });
            return result.isConfirmed;
        }

        function submitForm(noCpDuplicateConfirmed = false) {
            let fd = new FormData(document.getElementById('resepForm'));
            if (noCpDuplicateConfirmed) fd.set('no_cp_duplicate_confirmed', '1');

            $.ajax({
                url: 'save_resep.php',
                type: 'POST',
                data: fd,
                contentType: false,
                processData: false,
                dataType: 'json',
                success: async function (resp) {
                    if (resp.status === 'success') {
                        Swal.fire('Sukses', 'Data berhasil disimpan', 'success').then(() => {
                            window.location.href = 'list_resep.php';
                        });
                    } else if (resp.status === 'duplicate_no_cp_warning') {
                        const confirmed = await confirmDuplicateNoCp(resp);
                        if (confirmed && String($('#no_cp').val() || '').trim().toUpperCase() === String(resp.no_cp || '').trim().toUpperCase()) {
                            submitForm(true);
                        } else {
                            resetSaveButton();
                        }
                    } else {
                        resetSaveButton();
                        Swal.fire('Error', resp.message || 'Unknown error', 'error');
                    }
                },
                error: async function (xhr, status, error) {
                    const resp = xhr.responseJSON;
                    if (xhr.status === 409 && resp?.status === 'duplicate_no_cp_warning') {
                        const confirmed = await confirmDuplicateNoCp(resp);
                        if (confirmed && String($('#no_cp').val() || '').trim().toUpperCase() === String(resp.no_cp || '').trim().toUpperCase()) {
                            submitForm(true);
                        } else {
                            resetSaveButton();
                        }
                        return;
                    }
                    console.error(xhr.responseText);
                    resetSaveButton();
                    const requestId = resp?.request_id ? ` (Request ID: ${resp.request_id})` : '';
                    Swal.fire('Error', (resp?.message || 'Gagal menyimpan data: ' + error) + requestId, 'error');
                }
            });
        }
    });
</script>