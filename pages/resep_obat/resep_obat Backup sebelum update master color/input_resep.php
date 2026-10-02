<?php
// pages/resep_obat/input_resep.php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';

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

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1><?= $mode ?> Resep Obat</h1></div>
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
            #detailTable input[readonly] { cursor: help; background-color: #f8f9fa; }
            #detailTable .form-control-sm { padding-left: 4px; padding-right: 4px; }
            #detailTable thead th { 
                text-align: center; vertical-align: middle; 
                font-size: 1rem; font-weight: bold; color: #495057; 
            }
            /* Force Select2 dropdown to be wider than the narrow column */
            .select2-container--bootstrap4 .select2-dropdown {
                min-width: 350px !important;
                border-color: #80bdff !important;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
            }
        </style>
        <div class="container-fluid">
            <form id="resepForm">
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
                                        <select class="form-control select2-grey" name="kode_grey" style="width: 100%;">
                                            <?php if(isset($data['kode_grey']) && $data['kode_grey']): ?>
                                                <option value="<?= $data['kode_grey'] ?>" selected><?= $data['kode_grey'] ?></option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- SWAPPED: Kode Warna First -->
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Kode Warna</label>
                                    <div class="col-sm-8">
                                        <!-- Select2 for Color Lookup -->
                                        <select class="form-control select2-color" name="kode_warna" style="width: 100%;" required>
                                            <?php if(isset($data['kode_warna'])): ?>
                                                <option value="<?= $data['kode_warna'] ?>" selected><?= $data['kode_warna'] ?> - <?= $data['color_name'] ?? '' ?></option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>
                                <!-- Color Details Auto-filled -->
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Color Name</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="color_name" name="color_name" readonly value="<?= $data['color_name'] ?? '' ?>">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Description</label>
                                    <div class="col-sm-8">
                                        <textarea class="form-control" id="color_desc" name="color_desc" rows="2" readonly><?= $data['color_desc'] ?? '' ?></textarea>
                                    </div>
                                </div>

                                <!-- No CP Second (Optional) -->
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">No CP <small class="text-muted">(Opsional)</small></label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" name="no_cp" value="<?= $data['no_cp'] ?? '' ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Lot No</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" name="lot_no" required value="<?= $data['lot_no'] ?? '' ?>">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Weight</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" name="weight" required value="<?= $data['weight'] ?? '100' ?>">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Plan Qty</label>
                                    <div class="col-sm-8">
                                        <input type="number" step="0.01" class="form-control" name="plan_qty" id="plan_qty" required value="<?= $data['plan_qty'] ?? '3500' ?>">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 col-form-label">Vlot</label>
                                    <div class="col-sm-8">
                                        <input type="number" step="0.01" class="form-control" name="vlot" id="vlot" value="<?= $data['vlot'] ?? '' ?>" placeholder="0.00">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DETAILS SECTION -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h3 class="card-title">Detail Resep</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-primary btn-sm" id="btnAddRow"><i class="fas fa-plus"></i> Tambah Item</button>
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
                                        <td colspan="8" class="text-right font-weight-bold align-middle">Grand Total</td>
                                        <td colspan="2" class="align-middle"><input type="text" class="form-control-plaintext font-weight-bold text-right" id="grandVal" readonly value="Rp 0"></td>
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
                                <h3 class="card-title text-sm text-white"><i class="fas fa-calculator mr-1"></i> Cost Summary Per Meter</h3>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0">
                                    <thead class="text-center text-muted" style="font-size: 0.8rem; background-color: #fff;">
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
                                            <td colspan="2" class="font-weight-bold text-navy pl-3 align-middle">Total Cost</td>
                                            <td class="text-right pr-3"><span id="valCost" class="font-weight-bold text-lg">Rp 0</span></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                        
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-12">
                        <button type="submit" class="btn btn-success float-right" id="btnSave"><i class="fas fa-save"></i> Simpan Resep</button>
                        <a href="list_resep.php" class="btn btn-secondary float-right mr-2">Kembali</a>
                        <?php if($resep_id): ?>
                            <a href="view_resep.php?resep_id=<?= $resep_id ?>" target="_blank" class="btn btn-info float-left"><i class="fas fa-print"></i> Preview/Print</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
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
                    <input type="text" class="form-control" id="searchResep" placeholder="Cari Resep (No Resep / Info)...">
                </div>

                <div class="list-group" id="listResepProInt" style="max-height: 400px; overflow-y: auto;">
                    <!-- Items -->
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- Select2 -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
$(document).ready(function() {
    let rowIdx = 0;
    
    // Limits and Config from PHP
    const MIN_LIMIT = <?= json_encode($limits['min_cost']) ?>;
    const MAX_LIMIT = <?= json_encode($limits['max_cost']) ?>;
    
    // Category Limits (JSON) -> Normalize keys to Uppercase
    const RAW_CAT_LIMITS = <?= $limits['category_limits'] ?: '{}' ?>;
    const CAT_LIMITS = {};
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
            data: function(params) { return { q: params.term }; },
            processResults: function(data) { return { results: data.results }; }
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
            data: function(params) { return { q: params.term }; },
            processResults: function(data) { return { results: data.results }; }
        },
        placeholder: 'Cari Kode Grey',
        minimumInputLength: 2
    }).on('select2:select', function(e) {
        let data = e.params.data.item_data;
        if (data) {
             // 1. Calculate WEIGHT first (Gramasi * PlanQty)
             let gramasival = parseFloat(data.gramasi) || 0;
             let pickupval = parseFloat(data.pickup) || 0;
             
             // Store data on weight input for re-calcs
             $('input[name="weight"]').data('gramasi', gramasival);
             $('input[name="weight"]').data('pickup', pickupval);
             
             calcWeight();
        }
    });

    // Recalculate VLOT when Weight changes manually
    $('input[name="weight"]').on('input', function() {
        calcVlot();
    });

    $('.select2-color').on('select2:select', function(e) {
        let data = e.params.data.color_data;
        $('#color_name').val(data.name);
        $('#color_desc').val(data.desc);

        // Auto Fill Resep Logic
        if (data.colormsid) {
            checkProIntResep(data.colormsid);
        }
    });

    
    // Recalculate weight when Plan Qty changes
    $('#plan_qty').on('input', function() {
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

    function checkProIntResep(colormsid) {
        $.ajax({
            url: 'get_proint_resep_headers.php',
            data: { id: colormsid },
            dataType: 'json',
            success: function(resp) {
                if (resp.results && resp.results.length > 0) {
                    if (resp.results.length === 1) {
                        // Auto Select Single Result
                        loadResepDetails(resp.results[0].resephdid);
                    } else {
                        // Show Modal
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
                            
                            html += `<a href="#" class="list-group-item list-group-item-action btn-select-resep" data-id="${r.resephdid}">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h5 class="mb-1"><strong>${r.resepno}</strong> <span class="badge badge-info ml-1">Ver: ${r.resepseq}</span></h5>
                                            <small class="text-muted"><i class="fas fa-calendar-alt"></i> ${dateStr}</small>
                                        </div>
                                        <p class="mb-1 mt-1">${r.resepprodname}</p>
                                        <!-- Replaced Type with Date/Info as requested, actually Date is already above. User asked to replace Type with Date. I will put Date below too or just remove Type and ensure Date is visible? 
                                        User said: "Type:Pfd Diganti Aja Sama resepdate". 
                                        If I put date above, maybe I don't need it below. 
                                        But to strictly follow "Replace Type with Date": -->
                                     </a>`;
                            // Wait, displaying date twice is ugly. 
                            // Let's Put Date ONLY at the bottom (replacing Type) and maybe remove from top right? 
                            // Or keep top right as "Time" or something?
                            // Let's do:
                            // Top Right: Empty or maybe Type (if they wanted to swap?) No, "Diganti Aja Sama resepdate".
                            // I will put Date at the bottom where Type was. And remove from top right to be clean.
                           
                        });
                        $('#listResepProInt').html(html);
                        $('#searchResep').val(''); // Clear Search
                        $('#modal-pilih-resep').modal('show');
                    }
                } else {
                    Swal.fire('Info', 'Tidak ditemukan data resep di ProInt untuk warna ini.', 'info');
                }
            }
        });
    }

    $(document).on('click', '.btn-select-resep', function(e) {
        e.preventDefault();
        let id = $(this).data('id');
        $('#modal-pilih-resep').modal('hide');
        loadResepDetails(id);
    });
    
    // Filter Search in Modal
    $('#searchResep').on('keyup', function() {
        let value = $(this).val().toLowerCase();
        $('#listResepProInt .btn-select-resep').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });

    function loadResepDetails(resephdid) {
        // Show loading
        Swal.showLoading();
        
        $.ajax({
            url: 'get_proint_resep_details.php',
            data: { id: resephdid },
            dataType: 'json',
            success: function(resp) {
                Swal.close();
                
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
                            total: 0
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
            error: function() {
                Swal.fire('Error', 'Gagal memuat detail resep.', 'error');
            }
        });
    }

    // Helper to format currency (IDR)
    function fmtNum(n) {
        if (n === null || n === undefined || isNaN(parseFloat(n))) return '';
        return 'Rp ' + parseFloat(n).toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 2});
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
        return val.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function calcAll() {
        let grandTotal = 0;
        let catTotals = {}; // Object to store Total Cost per Category
        let catCFTotals = {}; // Object to store Total CF per Category
        
        $('.row-total').each(function() {
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
                 
                 // Validation
                 if (maxCF > 0) {
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

        // Check Max Cost
        if (MAX_LIMIT !== null && cost > parseFloat(MAX_LIMIT)) {
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
        
        if (isInvalid) {
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
        
        if (data && data.cf) {
             // If data provided (from auto-fill), check if we can calc Qty immediately
             let vlot = parseFloat($('#vlot').val()) || 0;
             if (vlot > 0) {
                 data.receipe = vlot * parseFloat(data.cf);
             }
        }

        let html = `
            <tr id="row_${rowIdx}">
                <td>
                    <select class="form-control select2-item" name="items[${rowIdx}][kode]" style="width: 100%;" required>
                        ${selectHtml}
                    </select>
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
                        ${
                            data ? (() => {
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
                data: function(params) { return { q: params.term }; },
                processResults: function(data) { return { results: data.results }; }
            },
            placeholder: 'Cari Kode / Nama',
            minimumInputLength: 1,
            dropdownAutoWidth: true,
            templateResult: function(data) {
                if (data.loading) return data.text;
                // Show "Code | Name" in dropdown for clarity
                return $(`<span><b>${data.id}</b> | ${data.text.split(' - ')[1] || ''}</span>`);
            },
            templateSelection: function(data) {
                // Only show code when selected
                return data.id; 
            }
        });
        
        // Trigger calculation for new row immediately
        calcRow($('#row_' + rowIdx));

        // If Price is missing but code exists (e.g. from Auto Fill), fetch it
        if (data && data.kode && (!data.std_price || data.std_price == 0)) {
             // Optional: Fetch price separately if needed, or just let user trigger it by re-selecting?
             // Better: Trigger the select2 logic manually or fetch price
             $.ajax({
                url: 'get_item_price.php',
                data: { prodcode: data.kode }, // Assuming code is enough, looking at get_items logic
                // Wait, get_item_price needs prodcode which is DIFFERENT from kode_obat sometimes?
                // check get_items.php: item.codeprod is what's used.
                // We don't have codeprod here from checkLocalItem...
                // Only 'kode_obat'.
                // If we want price, we might need codeprod in verify_proint_item.php too.
             });
             // For now, skip auto-price fetch on import to avoid complexity unless requested.
        }

        $select.on('select2:select', function(e) {
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
                    success: function(resp) {
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

    $('#btnAddRow').click(function() { addRow(); });

    // Remove logic moved here
    $(document).on('click', '.btn-remove', function() {
        $(this).closest('tr').remove();
        calcAll();
    });

    // Auto-CF logic removed per request

    // Formula: Qty = VLOT * CF
    $('#vlot, #plan_qty').on('input', function() {
        if (this.id === 'vlot') {
             recalcAllQty();
        } else {
             calcAll();
        }
    });

    $(document).on('input', '.item-cf', function() {
        let $row = $(this).closest('tr');
        calcRowQty($row);
    });
    
    // Also trigger when Qty changed manually? 
    // User requested "Qty/Receipe automatic from VLOT * CF".
    // If user changes Qty manually, should we reverse calc? 
    // "hapus logika cf yg merubah angka berdasarkan perubahan di qty/receipe" (Previous request).
    // So Qty input is result. 
    $(document).on('input', '.item-qty', function() {
         calcRow($(this).closest('tr'));
    });
    
    // Format Qty and CF on blur (US Format, 2 Decimals)
    $(document).on('change', '.item-qty, .item-cf', function() {
        let valStr = $(this).val();
        if(valStr) {
             let val = parseFloat(valStr.replace(/,/g, '')) || 0;
             $(this).val(val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        }
    });

    // Trigger calcRow when UOM CF changes manually (to update Total conversion)
    $(document).on('input', '.item-uom-cf', function() {
         calcRow($(this).closest('tr'));
    });

    function calcRowQty($row) {
        let vlot = parseFloat($('#vlot').val()) || 0;
        
        // CF uses US Format (Dot decimal)
        let cfStr = $row.find('.item-cf').val();
        let cf = parseFloat(cfStr.replace(/,/g, '')) || 0;
        
        // Qty = VLOT * CF
        let qty = vlot * cf;
        
        // Update Qty Input (US Format: 2 Decimal Places)
        $row.find('.item-qty').val(qty.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })); 
        
        calcRow($row);
    }
    
    function recalcAllQty() {
        $('#detailTable tbody tr').each(function() {
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
        for(let i=0; i<5; i++) addRow();
    <?php endif; ?>

    // Submit Handler
    $('#resepForm').on('submit', function(e) {
        e.preventDefault();
        
        // Double check validation before submit
        if ($('#btnSave').prop('disabled')) return;

        let formData = $(this).serialize();
        $.ajax({
            url: 'save_resep.php',
            type: 'POST',
            data: formData,
            dataType: 'json', // Explicitly expect JSON
            success: function(resp) {
                if (resp.status === 'success') {
                    Swal.fire('Sukses', 'Data berhasil disimpan', 'success').then(() => {
                        window.location.href = 'list_resep.php';
                    });
                } else {
                    Swal.fire('Error', resp.message || 'Unknown error', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error(xhr.responseText);
                Swal.fire('Error', 'Gagal menyimpan data: ' + error, 'error');
            }
        });
    });
});
</script>
