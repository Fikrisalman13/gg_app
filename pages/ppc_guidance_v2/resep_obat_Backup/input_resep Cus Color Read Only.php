<?php
// pages/resep_obat/input_resep.php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
$resep_id = $_GET['resep_id'] ?? null;
$mode = $resep_id ? 'Edit' : 'Tambah';
$data = null;
$details = [];

if ($resep_id) {
    // Fetch Header
    $stmt = sqlsrv_query($conn, "SELECT * FROM dbo.resep_obat WHERE id = ?", [$resep_id]);
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $data = $row;
    }
    // Fetch Details
    $stmtDetail = sqlsrv_query($conn, "SELECT * FROM dbo.resep_obat_detail WHERE id_resep = ?", [$resep_id]);
    while ($rowD = sqlsrv_fetch_array($stmtDetail, SQLSRV_FETCH_ASSOC)) {
        $details[] = $rowD;
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
        </style>
        <div class="container-fluid">
            <form id="resepForm" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="resep_id" value="<?= $resep_id ?? '' ?>">

                <!-- HEADER SECTION -->
                <div class="card card-<?= htmlspecialchars($themeColor); ?>">
                    <div class="card-header">
                        <h3 class="card-title">Informasi Dasar</h3>

                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <!-- Kode Grey (Above Kode Warna) -->
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Kode Grey</label>
                                    <div class="col-sm-8">
                                        <input type="hidden" name="kode_grey" id="kode_grey_hidden"
                                            value="<?= $data['kode_grey'] ?? '' ?>">
                                        <select id="select_kode_grey" class="form-control select2-grey"
                                            name="grey_id_select" style="width: 100%;">
                                            <?php if (isset($data['kode_grey']) && $data['kode_grey']): ?>
                                                <option value="<?= $data['kode_grey'] ?>" selected><?= $data['kode_grey'] ?>
                                                </option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Mesin Paddry -->
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Mesin</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" name="mesin" id="mesin" readonly
                                            value="<?= $data['mesin'] ?? '' ?>" placeholder="">
                                    </div>
                                </div>


                                <!-- SWAPPED: Kode Warna First -->
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Kode Warna</label>
                                    <div class="col-sm-8">
                                        <!-- Select2 for Color Lookup -->
                                        <div class="d-flex align-items-center">
                                            <div style="flex: 1;">
                                                <select class="form-control select2-color" name="kode_warna"
                                                    style="width: 100%;" required>
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
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Resep Prod Code</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="resepprodcode" name="resepprodcode"
                                            readonly value="<?= $data['resep_prod_code'] ?? '' ?>">
                                    </div>
                                </div>
                                <div class="form-group row">
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
                                            value="<?= $data['no_cp'] ?? '' ?>">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Lot No</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" name="lot_no" required
                                            value="<?= $data['lot_no'] ?? '' ?>">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Plan Qty</label>
                                    <div class="col-sm-8">
                                        <input type="number" step="0.01" class="form-control" name="plan_qty"
                                            id="plan_qty" required value="<?= $data['plan_qty'] ?? '3500' ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">

                                <div class="form-group row">
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
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Resep Date</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" name="resep_date" id="resep_date"
                                            readonly
                                            value="<?= isset($data['resep_date']) && $data['resep_date'] instanceof DateTime ? $data['resep_date']->format('Y-m-d') : (substr($data['resep_date'] ?? '', 0, 10)) ?>">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Resep Type</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" name="resep_type" id="resep_type"
                                            readonly value="<?= $data['resep_type'] ?? '' ?>">
                                    </div>
                                </div>

                                <!-- New ProInt Fields -->
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">No CP Resep</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" name="no_cp_resep" id="no_cp_resep"
                                            readonly value="<?= $data['no_cp_resep'] ?? '' ?>">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">No SO</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" name="no_so" id="no_so" readonly
                                            value="<?= $data['no_so'] ?? '' ?>">
                                    </div>
                                </div>

                                <div class="form-group row">
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
                                <div class="form-group row">
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
                                            <option value="Top Paddry" <?= (isset($data['status_resep_lipat']) && $data['status_resep_lipat'] === 'Top Paddry') ? 'selected' : '' ?>>Top
                                                Paddry</option>
                                            <option value="Top CPB" <?= (isset($data['status_resep_lipat']) && $data['status_resep_lipat'] === 'Top CPB') ? 'selected' : '' ?>>Top CPB
                                            </option>
                                            <option value="Kesetabilan" <?= (isset($data['status_resep_lipat']) && $data['status_resep_lipat'] === 'Kesetabilan') ? 'selected' : '' ?>>Kesetabilan
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Cus Color</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" name="cus_color" id="cus_color" readonly
                                            value="<?= $data['cus_color'] ?? '' ?>">
                                    </div>
                                </div>

                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Weight</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" name="weight" required
                                            value="<?= $data['weight'] ?? '100' ?>">
                                    </div>
                                </div>

                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Vlot</label>
                                    <div class="col-sm-8">
                                        <input type="number" step="0.01" class="form-control" name="vlot" id="vlot"
                                            value="<?= $data['vlot'] ?? '' ?>" placeholder="0.00">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DETAILS SECTION -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h3 class="card-title">
                            Detail Resep
                        </h3>
                        <div class="card-tools">
                            <input type="hidden" name="is_manual" id="is_manual" value="<?= $data['is_manual'] ?? 0 ?>">
                            <button type="button" class="btn btn-primary btn-sm" id="btnAddRow"><i
                                    class="fas fa-plus"></i> Tambah Item</button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="detailTable">
                                <thead style="background-color: #dbebf9;">
                                    <tr>
                                        <th style="width: 10%;">Kode</th>
                                        <th style="width: 25%;">Name</th>
                                        <th style="width: 10%;">Category</th>
                                        <th style="width: 7%;">Qty</th>
                                        <th style="width: 4%;">Uom</th>
                                        <th style="width: 6%;">Cf</th>
                                        <th style="width: 4%;">Uom Cf</th>
                                        <th style="width: 14%;">Price</th>
                                        <th style="width: 15%;">Total</th>
                                        <th style="width: 5%;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Rows will be added here -->
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="8" class="text-right font-weight-bold align-middle">Grand Total
                                        </td>
                                        <td colspan="2" class="align-middle"><input type="text"
                                                class="form-control-plaintext font-weight-bold text-right" id="grandVal"
                                                readonly value="Rp 0"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- SUMMARY / CALCULATIONS -->
                <div class="row">
                    <div class="col-md-4 offset-md-8">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-<?= htmlspecialchars($themeColor); ?> p-2">
                                <h3 class="card-title text-sm text-white"><i class="fas fa-calculator mr-1"></i> Cost
                                    Summary Per Meter</h3>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0">
                                    <thead class="text-center text-muted"
                                        style="font-size: 0.8rem; background-color: #fff;">
                                        <tr>
                                            <th class="border-top-0">Category</th>
                                            <th class="border-top-0 text-center">Total Cf</th>
                                            <th class="border-top-0 text-right">Cost</th>
                                        </tr>
                                    </thead>
                                    <tbody id="costDetails" style="font-size: 0.9rem;">
                                        <!-- Dynamic Category Costs -->
                                    </tbody>
                                    <tfoot style="border-top: 2px solid #343a40;">
                                        <tr class="bg-white">
                                            <td colspan="2" class="font-weight-bold text-navy pl-3 align-middle">Total
                                                Cost</td>
                                            <td class="text-right pr-3"><span id="valCost"
                                                    class="font-weight-bold text-lg">Rp 0</span></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="row mb-4">
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

<!-- Modal Select Bon by No CP -->
<div class="modal fade" id="modal-pilih-bon" tabindex="-1" role="dialog" aria-labelledby="modal-pilih-bon-title" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor); ?>">
                <h4 class="modal-title text-white" id="modal-pilih-bon-title">Pilih Bon / Routing</h4>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">No CP memiliki beberapa bon. Pilih detail resep yang akan digunakan.</p>
                <div class="list-group" id="listBonCp"></div>
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

<script>
    $(document).ready(function () {
        let rowIdx = 0;
        let cpLookupRequest = null;

        // Limits and Config from PHP (Initial Global Values)
        const MIN_LIMIT = <?= json_encode($limits['min_cost']) ?>;
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

                // Populate Mesin
                if (padryval && String(padryval).trim() !== '-' && String(padryval).trim() !== '') {
                    // Format as 'Paddry X' if it's a number/identifier
                    let prefix = String(padryval).toUpperCase().includes('PAD') ? '' : 'Paddry ';
                    $('#mesin').val(prefix + padryval);
                } else {
                    $('#mesin').val('');
                }

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

        function importCpDetails(noCp, bonreqId) {
            if (cpLookupRequest) cpLookupRequest.abort();
            Swal.fire({ title: 'Mengambil detail resep...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            cpLookupRequest = $.ajax({ url: 'get_resep_details_by_cp.php', data: { no_cp: noCp, bonreq_id: bonreqId }, dataType: 'json' })
                .done(function (resp) {
                    Swal.close();
                    if (!resp.ok || !Array.isArray(resp.details)) return;
                    $('#detailTable tbody').empty();
                    rowIdx = 0;
                    setResepSource('PROINT');
                    let selected = resp.selected || {};
                    if (selected.planqty != null) $('#plan_qty').val(Number(selected.planqty).toFixed(2));
                    if (selected.bonweight != null) $('input[name="weight"]').val(Number(selected.bonweight).toLocaleString('en-US', { minimumFractionDigits: 4, maximumFractionDigits: 4 }));
                    if (selected.vlot != null) $('#vlot').val(Number(selected.vlot).toFixed(2));
                    let warnings = [];
                    resp.details.forEach(function (item) {
                        if (item.warning) warnings.push(item.warning);
                        item.is_manual = 0;
                        addRow(item);
                    });
                    calcAll();
                    if (warnings.length) Swal.fire({ icon: 'warning', title: 'Data Master Belum Lengkap', html: '<ul class="text-left">' + warnings.map(w => `<li>${escapeCpHtml(w)}</li>`).join('') + '</ul>' });
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
                    if (candidates.length === 1) return importCpDetails(noCp, candidates[0].bonreqid);
                    let html = candidates.map(function (bon) {
                        let date = bon.bondate ? String(bon.bondate).substring(0, 10) : '-';
                        return `<button type="button" class="list-group-item list-group-item-action btn-select-bon-cp" data-id="${Number(bon.bonreqid)}">
                            <div class="d-flex justify-content-between"><strong>Bon No - ${escapeCpHtml(bon.bonno || '-')}</strong><small>${escapeCpHtml(date)}</small></div>
                            <div>${escapeCpHtml((bon.rtgcode || '') + ' - ' + (bon.rtgname || ''))}</div>
                            <small class="text-muted">VLot: ${escapeCpHtml(bon.vlot || '0')}</small>
                        </button>`;
                    }).join('');
                    $('#listBonCp').html(html);
                    $('#modal-pilih-bon').data('no-cp', noCp).modal('show');
                })
                .fail(function (xhr, status) { if (status !== 'abort') { Swal.close(); showCpError(xhr); } })
                .always(function () { cpLookupRequest = null; });
        }

        $('#no_cp').on('blur', lookupCpDetails).on('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); $(this).blur(); }
        });
        $(document).on('click', '.btn-select-bon-cp', function () {
            let noCp = $('#modal-pilih-bon').data('no-cp');
            let bonreqId = $(this).data('id');
            $('#modal-pilih-bon').modal('hide');
            importCpDetails(noCp, bonreqId);
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
            $('#cus_color').val(cuscolor);

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
            // Show loading
            Swal.showLoading();

            $.ajax({
                url: 'get_proint_resep_details.php',
                data: { id: resephdid },
                dataType: 'json',
                success: function (resp) {
                    Swal.close();
                    setResepSource('PROINT');

                    // Clear existing table
                    $('#detailTable tbody').empty();
                    rowIdx = 0; // Reset index? Or keep incrementing? Safe to keep incrementing but clearing makes sense for fresh import.
                    // Resetting rowIdx might be cleaner if we clear table.

                    if (resp.results && resp.results.length > 0) {
                        let warnings = [];

                        resp.results.forEach(item => {
                            // Prepare data for addRow
                            // Mapping:
                            // Kode -> item.kode
                            // Name -> item.name
                            // Category -> item.category
                            // UOM -> item.uom
                            // CF -> item.cf (from ProInt Qty)
                            // Qty (Receipe) -> ??? User didn't say where to get Qty/Receipe input. 
                            // User said: "CF ( Dari qty Hasil Query 2 )". 
                            // Usually Recipe is the formula amount. Maybe user fills it manually? Or defaults to 0? 
                            // Let's passed keys. 

                            // "Category (... Ambil Data Groupnya, Kalau Belum Terdaftar Munculkan Peringatan)"
                            if (item.warning) {
                                warnings.push(item.warning);
                            }

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

                            // We need a special addRow that can handle partial data (like missing Code)
                            // Or we just addRow and try to set values.
                            addRow(rowData);
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

        // Helper for US number format (2 decimals)
        function fmtUS(n) {
            if (n === null || n === undefined || n === '') return '';
            let val = parseFloat(n);
            if (isNaN(val)) return '';
            return val.toLocaleString('en-US', { minimumFractionDigits: 4, maximumFractionDigits: 4 });
        }

        function calcAll() {
            let grandTotal = 0;
            let catTotals = {}; // Object to store Total Cost per Category
            let catCFTotals = {}; // Object to store Total CF per Category

            $('.row-total').each(function () {
                let val = parseNum($(this).val());
                grandTotal += val;

                // Get Category and CF
                let $row = $(this).closest('tr');
                let cfStr = $row.find('.item-cf').val();
                let cf = parseFloat(cfStr.replace(/,/g, '')) || 0;

                let cat = $row.find('.item-category').val() || 'Others';
                cat = cat.trim(); // Allow mixed case (e.g. Disperse, Reactive)
                if (cat === '') cat = 'Others';

                // Sum Cost
                if (!catTotals[cat]) catTotals[cat] = 0;
                catTotals[cat] += val;

                // Sum CF
                if (!catCFTotals[cat]) catCFTotals[cat] = 0;
                catCFTotals[cat] += cf;
            });
            $('#grandVal').val(fmtNum(grandTotal));

            let planQty = parseFloat($('#plan_qty').val()) || 0;
            let cost = 0;
            let min = 0;
            let max = 0;

            // Initialize Flags
            let hasLimitError = false;
            let limitMsg = '';

            // Render Category Costs
            let htmlCat = '';
            if (planQty > 0) {
                cost = grandTotal / planQty;

                for (let c in catTotals) {
                    let cTot = catTotals[c];
                    let cCost = cTot / planQty;

                    // Normalize Key for Lookup
                    let cUpper = c.toUpperCase();

                    let totalCF = catCFTotals[c] || 0;
                    let maxCF = parseFloat(CAT_LIMITS[cUpper]) || 0;
                    let cfClass = '';
                    let limitInfo = '';

                    // Validation - Only show red indicators for global limits
                    if (maxCF > 0 && LIMIT_SOURCE === 'global') {
                        // Condition 2: Check Logic - Only show if RED (exceeded)
                        if (totalCF > maxCF) {
                            limitInfo = `<br><span class="text-xs text-danger">Max: ${fmtUS(maxCF)}</span>`;
                            cfClass = 'text-danger font-weight-bold';
                            hasLimitError = true;
                            limitMsg = `Limit CF untuk kategori ${c} terlampaui! (Max: ${maxCF})`;
                        }
                    }

                    htmlCat += `<tr>
                                <td class="pl-3 align-middle">${c}</td>
                                <td class="text-center align-middle ${cfClass}">
                                    ${fmtUS(totalCF)}
                                    ${limitInfo}
                                </td>
                                <td class="text-right pr-3 font-weight-bold text-dark align-middle">${fmtNum(cCost)}</td>
                             </tr>`;
                }
            } else {
                htmlCat = '<tr><td colspan="2" class="text-center text-muted font-italic py-3">Belum ada data</td></tr>';
            }
            $('#costDetails').html(htmlCat);

            $('#valCost').html(fmtNum(cost)); // Use html() to allow appending

            // Display Configured Limits instead of Calculated Range
            let dispMin = (MIN_LIMIT !== null) ? fmtNum(MIN_LIMIT) : '-';
            let dispMax = (MAX_LIMIT !== null) ? fmtNum(MAX_LIMIT) : '-';

            // User Request: "max cost yg bagian Rp 1.000.000 harusnya ada di ujung kanan tepat dibawah rp total cost"
            // User Request 2: "jangan tampilkan keterangan max limit... kalau tulisannya tidak merah"
            // So we append it to #valCost ONLY IF Exceeded.


            // VALIDATION LIMITS
            let isInvalid = false;
            let msg = '';

            // Check CF Limits first
            if (typeof hasLimitError !== 'undefined' && hasLimitError) {
                isInvalid = true;
                msg = limitMsg;
            }

            // Check Max Cost - Only show red and disable for global limits
            if (MAX_LIMIT !== null && cost > parseFloat(MAX_LIMIT) && LIMIT_SOURCE === 'global') {
                $('#valCost').css('color', 'red');
                // Append Max Limit Info below the cost
                $('#valCost').append(`<div class="text-xs text-danger mt-1" style="font-weight:bold;">MAX LIMIT ${fmtNum(MAX_LIMIT)}</div>`);

                isInvalid = true;
                msg = 'Cost melebihi Batas Maksimal!';
            } else if (MIN_LIMIT !== null && cost < parseFloat(MIN_LIMIT) && cost > 0) {
                // Optional: warn if below min? User didn't specify behavior, but let's assume valid for now or warn.
                // User only said "COST Melebihi Max maka Harga COST Akan Berwarna Merah"
                $('#valCost').css('color', '');
            } else {
                $('#valCost').css('color', '');
            }

            // Only disable button for global limits
            if (isInvalid && LIMIT_SOURCE === 'global') {
                $('#btnSave').prop('disabled', true);
                $('#btnSave').html(`<i class="fas fa-ban"></i> ${msg}`);
            } else {
                $('#btnSave').prop('disabled', false);
                $('#btnSave').html('<i class="fas fa-save"></i> Simpan Resep');
            }
        }

        function addRow(data = null) {
            rowIdx++;

            let selectHtml = '';
            if (data && data.kode) {
                selectHtml = `<option value="${data.kode}" selected>${data.kode} - ${data.name}</option>`;
            }

            if (data && data.cf && (data.receipe === null || data.receipe === undefined || data.receipe === '')) {
                // Hitung Qty dari VLot form hanya bila sumber belum memberikan Qty eksplisit.
                let vlot = parseFloat($('#vlot').val()) || 0;
                if (vlot > 0) {
                    data.receipe = vlot * parseFloat(data.cf);
                }
            }

            let isManual = (data && typeof data.is_manual !== 'undefined') ? data.is_manual : (data ? 0 : 1);

            let html = `
            <tr id="row_${rowIdx}">
                <td>
                    <div class="d-flex flex-column">
                        <select class="form-control select2-item" name="items[${rowIdx}][kode]" style="width: 100%;" required>
                            ${selectHtml}
                        </select>
                        <div class="mt-1">
                            <span class="badge badge-success item-status-proint" style="${isManual == 0 ? '' : 'display:none;'} font-size: 0.65rem;">PROINT</span>
                            <span class="badge badge-warning item-status-manual" style="${isManual == 1 ? '' : 'display:none;'} font-size: 0.65rem;">MANUAL</span>
                            <input type="hidden" class="item-is-manual" name="items[${rowIdx}][is_manual]" value="${isManual}">
                        </div>
                    </div>
                 </td>
                <td><input type="text" class="form-control form-control-sm item-name" name="items[${rowIdx}][name]" readonly value="${data ? data.name : ''}" title="${data ? data.name : ''}"></td>
                <td><input type="text" class="form-control form-control-sm item-category" name="items[${rowIdx}][category]" readonly value="${data ? data.category : ''}" title="${data ? data.category : ''}"></td>
                <td><input type="text" class="form-control form-control-sm item-qty" name="items[${rowIdx}][receipe]" required value="${data ? fmtUS(data.receipe) : ''}" placeholder="0.00"></td>
                <td><input type="text" class="form-control form-control-sm item-uom" name="items[${rowIdx}][uom]" readonly value="${data ? data.uom : ''}" title="${data ? data.uom : ''}"></td>
                <td><input type="text" class="form-control form-control-sm item-cf" name="items[${rowIdx}][cf]" required value="${data ? fmtUS(data.cf) : ''}"></td>
                <td><input type="text" class="form-control form-control-sm item-uom-cf" name="items[${rowIdx}][uom_cf]" value="${data ? data.uom_cf : ''}" title="${data ? data.uom_cf : ''}"></td>
                <td>
                    <input type="text" class="form-control form-control-sm item-price" name="items[${rowIdx}][std_price]" readonly value="${data ? fmtNum(data.std_price) : ''}">
                    <input type="hidden" class="item-price-satuan" name="items[${rowIdx}][price_satuan]" value="${data ? (data.satuan || data.price_satuan || '') : ''}">
                    <input type="hidden" class="item-price-source" name="items[${rowIdx}][price_source]" value="${data ? (data.price_source || '') : ''}">
                    <small class="text-danger price-source" style="font-size: 0.7rem; font-weight: bold;">
                        ${data ? (() => {
                    let sourceText = '';
                    if (data.price_source === 'PO') sourceText = 'Price PO';
                    else if (data.price_source === 'Template') sourceText = 'Std Price';

                    let unit = data.satuan || data.price_satuan || '';
                    if (sourceText && unit) {
                        sourceText += ' Per ' + unit;
                    }
                    return sourceText;
                })() : ''
                }
                    </small>
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm row-total" readonly value="${data && data.total ? fmtNum(data.total) : 'Rp 0'}">
                    <div class="total-info-container"></div>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-danger btn-xs btn-remove"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `;
            $('#detailTable tbody').append(html);

            // Init Select2
            let $select = $(`#row_${rowIdx} .select2-item`);
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
                    // Show "Code | Name" in dropdown for clarity
                    return $(`<span><b>${data.id}</b> | ${data.text.split(' - ')[1] || ''}</span>`);
                },
                templateSelection: function (data) {
                    // Only show code when selected
                    return data.id;
                }
            });

            // Trigger calculation for new row immediately
            calcRow($('#row_' + rowIdx));

            // Auto-fill memakai kode lokal untuk pilihan item, tetapi harga dicari dengan kode produk ProInt.
            if (data && data.codeprod && (!data.std_price || data.std_price == 0)) {
                let $row = $('#row_' + rowIdx);
                $.ajax({
                    url: 'get_item_price.php',
                    data: { prodcode: data.codeprod },
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

            $select.on('select2:select', function (e) {
                let item = e.params.data.item_data;
                let $row = $(this).closest('tr');
                $row.find('.item-name').val(item.name).attr('title', item.name);
                // item.uom from get_items is Local UOM.
                // Requirement: UOM -> ProInt (via get_item_price), UOM CF -> Local.

                // Set UOM CF to Local UOM (Original, e.g. G/L)
                $row.find('.item-uom-cf').val(item.uom_raw).attr('title', item.uom_raw);

                // Set UOM to Local initially (fallback), will be overwritten by get_item_price if linked
                $row.find('.item-uom').val(item.uom).attr('title', item.uom);

                $row.find('.item-category').val(item.group_obat).attr('title', item.group_obat); // Populate Category

                // Default to 0 first
                $row.find('.item-price').val(fmtNum(0));
                $row.find('.price-source').text('');

                // Trigger calculation for Qty (if CF exists or default 0) and Totals
                calcRowQty($row);

                // Fetch Real Price if codeprod exists
                if (item.codeprod) {
                    $.ajax({
                        url: 'get_item_price.php',
                        data: { prodcode: item.codeprod },
                        dataType: 'json',
                        success: function (resp) {
                            if (resp.price) {
                                $row.find('.item-price').val(fmtNum(resp.price));

                                // Show Source Label
                                let sourceText = '';
                                if (resp.source === 'PO') sourceText = 'Price PO';
                                else if (resp.source === 'Template') sourceText = 'Std Price';

                                // resp.satuan is raw ProInt UOM from get_item_price.php
                                if (sourceText && resp.satuan) {
                                    sourceText += ' Per ' + resp.satuan;
                                }

                                $row.find('.price-source').text(sourceText);
                                $row.find('.item-price-satuan').val(resp.satuan || '');
                                $row.find('.item-price-source').val(resp.source || '');

                                calcRow($row); // Recalculate
                            }

                            // Fill UOM if available -> DISABLED per user request (Keep Local UOM)
                            /*
                            if (resp.uom) {
                                $row.find('.item-uom').val(resp.uom);
                            }
                            */
                        }
                    });
                }

                calcRow($row);
            });
        }

        function calcRow($row) {
            // Handle Qty input (US Format: Comma thousand, Dot decimal)
            let qtyStr = $row.find('.item-qty').val();
            let qtyFn = qtyStr.replace(/,/g, '');
            let qty = parseFloat(qtyFn) || 0;

            let priceStr = $row.find('.item-price').val();
            // parseNum helper defined earlier (remove Rp, dot, etc)
            let price = parseNum(priceStr);

            let total = qty * price;

            // Per User Request: Calculation based on UOM (Readonly/Master), not UOM CF
            let uom = $row.find('.item-uom').val().toUpperCase().trim();
            let totalInfo = '';

            // Convert for G/L (Divide Total by 1000)
            // User requested G/L to be renamed to GR.
            if (uom === 'GR' || uom === 'G/L') {
                total = total / 1000;
                totalInfo = '<small class="text-muted font-weight-bold d-block mt-1" style="font-size:0.7em">(Total Per 1000 GR)</small>';
            }

            $row.find('.row-total').val(fmtNum(total));
            $row.find('.total-info-container').html(totalInfo);

            calcAll();
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
        $(document).on('input change select2:select', '#detailTable input, #detailTable select', function () {
            setResepSource('MANUAL');
            let $row = $(this).closest('tr');
            updateRowStatus($row, 'MANUAL');
        });

        $('#btnAddRow').click(function () {
            addRow();
            setResepSource('MANUAL');
        });

        $(document).on('click', '.btn-remove', function () {
            $(this).closest('tr').remove();
            calcAll();
            setResepSource('MANUAL');
        });

        // Auto-CF logic removed per request

        // Formula: Qty = VLOT * CF
        $('#vlot, #plan_qty').on('input', function () {
            if (this.id === 'vlot') {
                recalcAllQty();
            } else {
                calcAll();
            }
        });

        $(document).on('input', '.item-cf', function () {
            let $row = $(this).closest('tr');
            calcRowQty($row);
        });

        // Also trigger when Qty changed manually? 
        // User requested "Qty/Receipe automatic from VLOT * CF".
        // If user changes Qty manually, should we reverse calc? 
        // "hapus logika cf yg merubah angka berdasarkan perubahan di qty/receipe" (Previous request).
        // So Qty input is result. 
        $(document).on('input', '.item-qty', function () {
            calcRow($(this).closest('tr'));
        });

        // Format Qty and CF on blur (US Format, 2 Decimals)
        $(document).on('change', '.item-qty, .item-cf', function () {
            let valStr = $(this).val();
            if (valStr) {
                let val = parseFloat(valStr.replace(/,/g, '')) || 0;
                $(this).val(fmtUS(val));
            }
        });

        // Trigger calcRow when UOM CF changes manually (to update Total conversion)
        $(document).on('input', '.item-uom-cf', function () {
            calcRow($(this).closest('tr'));
        });

        function calcRowQty($row) {
            let vlot = parseFloat($('#vlot').val()) || 0;

            // CF uses US Format (Dot decimal)
            let cfStr = $row.find('.item-cf').val();
            let cf = parseFloat(cfStr.replace(/,/g, '')) || 0;

            // Qty = VLOT * CF
            let qty = vlot * cf;

            // Update Qty Input (US Format: 4 Decimal Places)
            $row.find('.item-qty').val(fmtUS(qty));

            calcRow($row);
        }

        function recalcAllQty() {
            $('#detailTable tbody tr').each(function () {
                calcRowQty($(this));
            });
        }

        // Populate existing details if Edit mode, ELSE add 5 empty rows
        <?php if (!empty($details)): ?>
            let savedDetails = <?= json_encode($details) ?>;
            savedDetails.forEach(d => addRow(d));
            setTimeout(calcAll, 500);
        <?php else: ?>
            // Default 5 rows for new
            for (let i = 0; i < 5; i++) addRow();
        <?php endif; ?>


        // Validation function to check limits before save
        function validateLimitsBeforeSave() {
            // Calculate category totals
            let catTotals = {};
            let totalCost = 0;

            $('#detailTable tbody tr').each(function () {
                let cat = $(this).find('.item-category').val();
                if (!cat) return;

                cat = cat.toUpperCase();

                // Get CF value (US Format: Dot decimal)
                let cfStr = $(this).find('.item-cf').val() || '';
                let cf = parseFloat(cfStr.replace(/,/g, '')) || 0;

                // Get Cost value (IDR Format: Dot thousand, comma decimal)
                let costStr = $(this).find('.row-total').val() || '';
                // Use parseNum helper to handle Rp and Indonesian format
                let cost = parseNum(costStr);

                if (!catTotals[cat]) {
                    catTotals[cat] = { cf: 0, cost: 0 };
                }
                catTotals[cat].cf += cf;
                catTotals[cat].cost += cost;
                totalCost += cost;
            });

            let warnings = [];

            // Get Plan Qty for per-meter calculation
            let planQty = parseFloat($('#plan_qty').val()) || 1;
            let costPerMeter = totalCost / planQty;


            // Check Cost Limit (skip if unlimited) - Compare Cost Per Meter, not Grand Total
            if (MAX_LIMIT && MAX_LIMIT < 999999999 && costPerMeter > MAX_LIMIT) {
                let costFormatted = 'Rp ' + costPerMeter.toLocaleString('id-ID', { minimumFractionDigits: 2 });
                let limitFormatted = 'Rp ' + MAX_LIMIT.toLocaleString('id-ID', { minimumFractionDigits: 2 });
                warnings.push(`Cost/Meter: ${costFormatted} > Limit: ${limitFormatted}`);
            }

            // Check Category CF Limits (skip if unlimited)
            for (let cat in catTotals) {
                if (CAT_LIMITS[cat] && CAT_LIMITS[cat] < 999999999 && catTotals[cat].cf > CAT_LIMITS[cat]) {
                    warnings.push(`CF ${cat}: ${catTotals[cat].cf.toFixed(2)} > Limit: ${CAT_LIMITS[cat]}`);
                }
            }

            // Check Total CF Limit (if exists and not unlimited)
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

        // Submit Handler
        $('#resepForm').on('submit', function (e) {
            e.preventDefault();

            if (isSubmitting) return;
            if ($('#btnSave').prop('disabled')) return;

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

        function submitForm() {
            let fd = new FormData(document.getElementById('resepForm'));

            $.ajax({
                url: 'save_resep.php',
                type: 'POST',
                data: fd,
                contentType: false,
                processData: false,
                dataType: 'json',
                success: function (resp) {
                    if (resp.status === 'success') {
                        Swal.fire('Sukses', 'Data berhasil disimpan', 'success').then(() => {
                            window.location.href = 'list_resep.php';
                        });
                    } else {
                        isSubmitting = false;
                        calcAll();
                        $('#btnSave').prop('disabled', false).html('<i class="fas fa-save"></i> Simpan Resep');
                        Swal.fire('Error', resp.message || 'Unknown error', 'error');
                    }
                },
                error: function (xhr, status, error) {
                    console.error(xhr.responseText);
                    isSubmitting = false;
                    calcAll();
                    $('#btnSave').prop('disabled', false).html('<i class="fas fa-save"></i> Simpan Resep');
                    Swal.fire('Error', 'Gagal menyimpan data: ' + error, 'error');
                }
            });
        }
    });
</script>