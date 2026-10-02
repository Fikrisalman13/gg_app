<?php
// ======================================================
// AVERAGE USAGE — DataTables UI like absensi_kantin.php
// ======================================================

session_start();
ob_start();
// support POST->GET like before
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_GET['start']  = $_POST['start'] ?? '';
    $_GET['end']    = $_POST['end'] ?? '';
    $_GET['search'] = $_POST['search'] ?? '';
}

require_once '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header("Location: /gg_app/login.php");
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

date_default_timezone_set("Asia/Jakarta");

// Escape output
function e($s){
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

// Convert bytes
function formatBytes($bytes){
    if (!is_numeric($bytes)) return "0 B";
    if ($bytes >= 1073741824) return round($bytes/1073741824,2)." GB";
    if ($bytes >= 1048576) return round($bytes/1048576,2)." MB";
    if ($bytes >= 1024) return round($bytes/1024,2)." KB";
    return $bytes . " B";
}

// Get filter parameters (for initial values)
$search      = trim($_GET['search'] ?? '');
$start_date  = $_GET['start'] ?? '';
$end_date    = $_GET['end'] ?? '';
$perpage_raw = $_GET['perpage'] ?? 10;
$page_raw    = $_GET['page'] ?? 1;

// compute summary for display (same SQL as before)
$params = [];
$whereParts = [];
if ($search !== "") {
    $whereParts[] = "(LOWER(name) LIKE ? OR LOWER(target) LIKE ?)";
    $params[] = "%".strtolower($search)."%";
    $params[] = "%".strtolower($search)."%";
}
if (!empty($start_date) && !empty($end_date)) {
    $whereParts[] = "(CAST([date] AS DATE) BETWEEN ? AND ?)";
    $params[] = $start_date;
    $params[] = $end_date;
}
$whereSQL = (!empty($whereParts)) ? "WHERE " . implode(" AND ", $whereParts) : "";

$sum_sql = "
    SELECT AVG(upload) AS up, AVG(download) AS dn, AVG(total) AS tot
    FROM monitoring_jaringan
    $whereSQL
";
$sum_stmt = sqlsrv_query($conn, $sum_sql, $params);
$sum = $sum_stmt ? sqlsrv_fetch_array($sum_stmt, SQLSRV_FETCH_ASSOC) : ['up'=>0,'dn'=>0,'tot'=>0];
?>

<style>
/* Style for date input with days counter */
.date-with-days {
    position: relative;
    padding-right: 70px !important;
    background-color: white !important;
}
.date-with-days::-webkit-calendar-picker-indicator {
    position: relative;
    z-index: 1;
}
.date-with-days::after {
    content: attr(data-days) ' hari';
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
    pointer-events: none;
    background: white;
    padding: 0 5px;
    font-size: 0.8rem;
    z-index: 0;
}

.filter-section { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
.summary-box { border: 1px solid #ddd; padding: 12px; border-radius: 6px; }
.summary-icon { width: 42px; height: 42px; font-size: 16px; }
.loading-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center; }
.loading-spinner { color: white; font-size: 2rem; }
.auto-refresh-info { font-size: 0.85rem; color: #6c757d; margin-top: 5px; }
/* Make No column narrower */
th.col-no, td.col-no {
    width: 50px !important;
    min-width: 50px !important;
    max-width: 50px !important;
    text-align: center !important;
}
</style>

<div class="content-wrapper">

    <div class="content-header">
        <div class="container-fluid">
            <div class="row align-items-center mb-2">
                <div class="col-md-6"><h1 class="m-0">Average Usage</h1></div>
                <div class="col-md-6 text-md-right text-sm-left mt-sm-2 mt-md-0">
                    <ol class="breadcrumb float-md-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Average Usage</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?= e($themeColor) ?> text-white">
                    <h3 class="card-title m-0">Data Average Usage</h3>
                    <div class="card-tools">
                        <button id="btnExportPDF" class="btn btn-danger btn-sm"><i class="fas fa-file-pdf"></i> Export PDF</button>
                        <button id="btnExportExcel" class="btn btn-success btn-sm ml-2"><i class="fas fa-file-excel"></i> Export Excel</button>
                        <button id="btnDelete" class="btn btn-light btn-sm ml-2"><i class="fas fa-trash text-danger"></i> Hapus Data</button>
                    </div>
                </div>
                <div class="card-body">

                    <div class="row mb-4">
                        <div class="col-md-3 col-sm-6 mb-2">
                            <div class="summary-box d-flex align-items-center shadow-sm">
                                <div class="bg-primary text-white rounded summary-icon d-flex justify-content-center align-items-center"><i class="fas fa-upload"></i></div>
                                <div class="pl-2"><div class="text-muted small">Avg Upload</div><div class="font-weight-bold" id="avgUploadBox"><?= formatBytes($sum['up']) ?></div></div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2">
                            <div class="summary-box d-flex align-items-center shadow-sm">
                                <div class="bg-success text-white rounded summary-icon d-flex justify-content-center align-items-center"><i class="fas fa-download"></i></div>
                                <div class="pl-2"><div class="text-muted small">Avg Download</div><div class="font-weight-bold" id="avgDownloadBox"><?= formatBytes($sum['dn']) ?></div></div>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6 mb-2">
                            <div class="summary-box d-flex align-items-center shadow-sm">
                                <div class="bg-warning text-white rounded summary-icon d-flex justify-content-center align-items-center"><i class="fas fa-exchange-alt"></i></div>
                                <div class="pl-2"><div class="text-muted small">Total Avg Trafik</div><div class="font-weight-bold" id="avgTotalBox"><?= formatBytes($sum['tot']) ?></div></div>
                            </div>
                        </div>
                    </div>

                    <div class="filter-section">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="small">Cari (Nama / Target)</label>
                                <input type="text" name="search_average" class="form-control form-control-sm filter-input" placeholder="Cari nama atau target..." value="<?= e($search) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="small">Tanggal Awal</label>
                                <input type="date" name="start_date" id="start_date" class="form-control form-control-sm filter-input" value="<?= e($start_date) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="small">Tanggal Akhir</label>
                                <input type="date" 
                                       name="end_date" 
                                       id="end_date" 
                                       class="form-control form-control-sm filter-input date-with-days" 
                                       value="<?= e($end_date) ?>"
                                       data-days="0">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <a href="average_usage.php" class="btn btn-secondary btn-sm"><i class="fas fa-eraser"></i> Reset Filter</a>
                                <span class="auto-refresh-info ml-2"><i class="fas fa-info-circle"></i> Data akan otomatis dimuat ulang saat filter diubah</span>
                            </div>
                        </div>
                    </div>

                    <div class="loading-overlay"><div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Memuat data...</div></div>

                    <div id="dateHint" class="alert alert-info text-center mb-3" style="font-size:1.1rem;<?= (!empty($start_date) && !empty($end_date)) ? 'display:none;' : '' ?>">
    <i class="fas fa-calendar-alt mr-2"></i> Pilih tanggal untuk menampilkan data
</div>
<div class="table-responsive">
    <table id="averageUsageTable" class="table table-hover table-sm w-100">
                            <thead class="thead-light">
                                <tr class="text-center">
                                    <th>No</th>
                                    <th>Nama</th>
                                    <th>Target</th>
                                    <th>Upload</th>
                                    <th>Download</th>
                                    <th>Total</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </section>

</div>

<?php include '../../includes/footer.php'; ?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<!-- DataTables JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<!-- SweetAlert -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
$(document).ready(function () {
    const ajaxUrl = 'ajax_average_usage.php';
    let debounceTimer;
    function debounce(cb, ms){ clearTimeout(debounceTimer); debounceTimer = setTimeout(cb, ms); }

    // --- SweetAlert2 helper seperti user_fingerprint.php ---
    const showAlert = (type, msg) => Swal.fire({icon: type, title: msg, timer: 2500, showConfirmButton: false});
    const setLoading = (btn, text) => btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> ' + (text || ''));
    const resetLoading = (btn, icon, text) => btn.prop('disabled', false).html('<i class="' + icon + '"></i> ' + (text || ''));

    const table = $('#averageUsageTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: ajaxUrl,
            type: 'GET',
            data: function(d){
                d.search_average = $('input[name="search_average"]').val();
                d.start_date = $('input[name="start_date"]').val();
                d.end_date = $('input[name="end_date"]').val();
                
                // Add sorting parameters
                if (d.order && d.order.length > 0) {
                    d.orderBy = d.columns[d.order[0].column].data;
                    d.orderDir = d.order[0].dir;
                }
                
                return d;
            },
            beforeSend: function(){},
            complete: function(){},
            error: function(xhr, status, err){
                let msg = 'Gagal memuat data. Silakan refresh halaman.';
                try{ const res = JSON.parse(xhr.responseText); if(res.error) msg = res.error; }catch(e){ if(xhr.responseText.includes('<!DOCTYPE')) msg = 'Server mengembalikan halaman HTML bukan JSON. Silakan cek konfigurasi.'; }
                showAlert('error', msg);
            }
        },
        columns: [
            { data: 0, className: 'text-center col-no', orderable: false }, // No
            { data: 2, orderable: true }, // Nama
            { data: 3, orderable: true }, // Target
            { data: 4, className: 'text-center', orderable: true }, // Upload
            { data: 5, className: 'text-center', orderable: true }, // Download
            { data: 6, className: 'text-center', orderable: true }, // Total
            { data: 1, className: 'text-center', orderable: true }  // Tanggal
        ],
        order: [[5, 'desc']], // Default sort by Total (6th column, 0-indexed) descending
        
        responsive: true,
        autoWidth: false,
        pageLength: 25,
        lengthMenu: [[10,25,50,100],[10,25,50,100]],
        dom: '<"top"l>rt<"bottom"ip>',
        language: {
            processing: 'Memproses...', 
            lengthMenu: 'Tampilkan _MENU_ data per halaman', 
            zeroRecords: 'Tidak ada data yang ditemukan', 
            info: 'Menampilkan _START_ hingga _END_ dari _TOTAL_ data', 
            infoEmpty: 'Menampilkan 0 hingga 0 dari 0 data', 
            infoFiltered: '(disaring dari _MAX_ total data)', 
            search: '', // Empty string to hide the search label
            searchPlaceholder: '', // Remove search placeholder
            paginate: { first: 'Pertama', last: 'Terakhir', next: 'Selanjutnya', previous: 'Sebelumnya' }
        }
    });

    // Handle search and filter changes
    // Function to calculate and update days between dates
    function updateDayCount() {
        const startDate = new Date($('#start_date').val());
        const endDate = new Date($('#end_date').val());
        let diffDays = 0;
        
        if (startDate && endDate && !isNaN(startDate.getTime()) && !isNaN(endDate.getTime()) && startDate <= endDate) {
            const diffTime = Math.abs(endDate - startDate);
            diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; // +1 to include both dates
        }
        
        // Update the data-days attribute which is used by the CSS
        $('#end_date').attr('data-days', diffDays);
        
        return diffDays;
    }
    
    // Initial update
    updateDayCount();
    
    // Handle date changes
    $('input[name="start_date"], input[name="end_date"]').on('change', function() {
        updateDayCount();
        // Show/hide dateHint alert
        const st = $('input[name="start_date"]').val();
        const en = $('input[name="end_date"]').val();
        if (st && en) {
            $('#dateHint').hide();
        } else {
            $('#dateHint').show();
        }
        debounce(function() { 
            table.ajax.reload(null, false);
            // Fetch summary for selected date range
            if (st && en) {
                $.get('ajax_average_usage.php', { summary: 1, start_date: st, end_date: en }, function(res) {
                    if (res && res.up !== undefined && res.dn !== undefined && res.tot !== undefined) {
                        $('#avgUploadBox').text(res.up);
                        $('#avgDownloadBox').text(res.dn);
                        $('#avgTotalBox').text(res.tot);
                    }
                }, 'json');
            }
            setTimeout(function() {
                // Ambil info jumlah data setelah reload
                const info = table.page.info();
                if (info && info.recordsTotal > 0) {
                    Swal.fire({ icon: 'success', title: 'Data ditemukan', timer: 1800, showConfirmButton: false });
                }
            }, 600);
        }, 400);
    });
    
    // Handle search input
    $('input[name="search_average"]').on('keyup', function() {
        debounce(function() { 
            table.ajax.reload(null, false);
        }, 400);
    });
    
    // Hide the default DataTables search box
    $('.dataTables_filter').hide();

    // Hapus overlay loading jika ada
    $('.loading-overlay').remove();
    
    // Make sure the date picker icon is clickable
    $(document).on('click', '.date-with-days', function(e) {
        // If clicking on the days text, focus the input to show date picker
        const rect = e.target.getBoundingClientRect();
        if (e.clientX > rect.right - 80) { // Clicked on the right side where days are shown
            $(this).focus();
        }
    });

    // Export buttons
    $('#btnExportPDF').click(function() {
        setLoading($(this), 'Export...');
        const s = encodeURIComponent($('input[name="search_average"]').val());
        const st = encodeURIComponent($('input[name="start_date"]').val());
        const en = encodeURIComponent($('input[name="end_date"]').val());
        window.open(`export_pdf_average_usage.php?search=${s}&start=${st}&end=${en}`,'_blank');
        resetLoading($(this), 'fas fa-file-pdf', 'Export PDF');
    });
    $('#btnExportExcel').click(function() {
        setLoading($(this), 'Export...');
        const s = encodeURIComponent($('input[name="search_average"]').val());
        const st = encodeURIComponent($('input[name="start_date"]').val());
        const en = encodeURIComponent($('input[name="end_date"]').val());
        window.open(`export_excel_average_usage.php?search=${s}&start=${st}&end=${en}`,'_blank');
        resetLoading($(this), 'fas fa-file-excel', 'Export Excel');
    });

    // Delete
    $('#btnDelete').click(function(){
        const st = $('input[name="start_date"]').val();
        const en = $('input[name="end_date"]').val();
        if (!st || !en) return showAlert('warning','Silakan pilih rentang tanggal!');
        Swal.fire({ title: 'Konfirmasi', text: `Hapus data dari ${st} sampai ${en}?`, icon: 'warning', showCancelButton: true }).then(res => {
            if (res.isConfirmed) {
                setLoading($('#btnDelete'), 'Menghapus...');
                fetch(`delete_average_data.php?start=${encodeURIComponent(st)}&end=${encodeURIComponent(en)}`)
                    .then(r => r.text())
                    .then(msg => { showAlert('success', msg); table.ajax.reload(); })
                    .catch(() => showAlert('error', 'Gagal menghapus data'))
                    .finally(() => resetLoading($('#btnDelete'), 'fas fa-trash', 'Hapus Data'));
            }
        });
    });
});
</script>
