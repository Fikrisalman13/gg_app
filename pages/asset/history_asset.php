<?php
// history_asset.php
session_start();
ob_start();

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';

date_default_timezone_set('Asia/Jakarta');

// Check if user is logged in
if (!isset($_SESSION['UserName'])) {
    die("Akses ditolak: Silakan login terlebih dahulu.");
}

// Check permissions
function checkPermissions($conn, $groupId, $menuId) {
    $sql = "SELECT CanView FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $params = [$groupId, $menuId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    $permissions = ['CanView' => 0];
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    sqlsrv_free_stmt($stmt);
    return $permissions;
}

$permissions = checkPermissions($conn, $_SESSION['GroupId'], 228);
if ($permissions['CanView'] != 1) {
    die("Akses ditolak: Anda tidak memiliki hak akses.");
}


// Include layout parts (header/sidebar)
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

    <style>
        .filter-box {
            background: #f8f9fa;
            padding: 12px;
            margin-bottom: 18px;
            border-radius: 6px;
        }
        #table-spinner {
            display: none;
            margin-bottom: 8px;
            text-align: center;
        }
        #table-spinner .spinner-border { width: 1rem; height: 1rem; vertical-align: middle; }
    </style>

<div class="content-wrapper">
    <!-- header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1 class="m-0">History Asset</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="/gg_app/pages/asset/asset.php">Asset</a></li>
                        <li class="breadcrumb-item active">History Asset</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- content -->
    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                    <h3 class="card-title"><i class="fas fa-history mr-1"></i>
                    Daftar History Perubahan Asset</h3>
                </div>

                <div class="card-body">
                    <!-- Filter -->
                    <div class="filter-box">
                        <form id="filterForm" method="get" class="form-inline">
                            <div class="form-group mr-2 mb-2">
                                <label for="start_date" class="mr-2">Mulai:</label>
                                <input type="date" id="start_date" name="start_date" class="form-control form-control-sm" value="<?= date('Y-m-01') ?>">
                            </div>
                            
                            <div class="form-group mr-2 mb-2">
                                <label for="end_date" class="mr-2">Sampai:</label>
                                <input type="date" id="end_date" name="end_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                            </div>

                            <div class="form-group mb-2">
                                <button type="button" id="btnFilter" class="btn btn-primary btn-sm mr-2"><i class="fas fa-filter"></i> Filter</button>
                                <button type="button" id="btnReset" class="btn btn-secondary btn-sm mr-2"><i class="fas fa-sync-alt"></i> Reset</button>
                            </div>

                            <div class="btn-group ml-auto mb-2" role="group" aria-label="exports">
                                <a id="exportExcel" href="#" class="btn btn-success btn-sm mr-1"><i class="fas fa-file-excel"></i> Export Excel</a>
                                <a id="exportPdf" href="#" class="btn btn-danger btn-sm"><i class="fas fa-file-pdf"></i> Export PDF</a>
                            </div>
                        </form>
                    </div>

                    <!-- spinner kecil -->
                    <div id="table-spinner">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                        <small class="text-muted ml-2">Memuat data...</small>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table id="historyTable" class="table table-hover table-sm" style="width:100%;">
                            <thead class="thead-light">
                                <tr>
                                    <th width="5%">No</th>
                                    <th>Tanggal</th>
                                    <th>User</th>
                                    <th>Kode Asset</th>
                                    <th>Merk / Tipe</th>
                                    <th>Perubahan Status</th>
                                    <th>Jenis Perubahan</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div> <!-- /.table-responsive -->
                </div> <!-- /.card-body -->
            </div> <!-- /.card -->
        </div>
    </div>
</div>
<!-- ===================================================
    10. IMPORT FOOTER
======================================================= -->
<?php include '../../includes/footer.php'; ?>
<!-- ===================================================
    11. JAVASCRIPT LIBRARIES
======================================================= -->
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
<!-- Moment JS for parsing dates in client if needed -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/moment/moment.min.js"></script>

<script>
(function(){
    var $spinner = $('#table-spinner');

    var table = $('#historyTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'history_asset_serverside.php',
            type: 'POST',
            data: function(d) {
                d.start_date = $('#start_date').val();
                d.end_date = $('#end_date').val();
                d.search_value = d.search.value;
            },
            beforeSend: function() {
                $spinner.show();
            },
            complete: function() {
                $spinner.hide();
            },
            error: function(xhr, status, error) {
                $spinner.hide();
                console.error('AJAX error', status, error);
                Swal.fire({
                    icon: 'error',
                    title: 'Terjadi kesalahan',
                    text: 'Gagal memuat data history. Periksa konsol untuk detail.',
                    timer: 3500,
                    showConfirmButton: false
                });
            }
        },
        columns: [
            { data: null, orderable: false, searchable: false, render: function(data, type, row, meta) {
                return meta.row + 1 + meta.settings._iDisplayStart;
            }},
            { data: 'created_at', render: function(data){
                return data ? moment(data).format('DD-MM-YYYY HH:mm') : '-';
            }},
            { data: 'created_by' },
            { data: 'kode_asset_seq', render: function(data, type, row) {
                return '<a href="view_asset.php?id=' + row.id_asset + '">' + (data || '-') + '</a>';
            }},
            { data: 'merk_tipe' },
            { data: 'perubahan_status', orderable: false, searchable: false, render: function(d){
                return d || '-';
            }},
            { data: 'jenis_perubahan' },
            { data: 'note', orderable: false }
        ],
        order: [[1, 'desc']], // Default order by created_at DESC 
        responsive: true,
        autoWidth: false,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        language: {
            processing: "Memproses...",
            lengthMenu: "Tampilkan _MENU_ data per halaman",
            zeroRecords: "Tidak ada data ditemukan",
            info: "Menampilkan _START_ - _END_ dari _TOTAL_ data history",
            infoEmpty: "Tidak ada data history tersedia",
            infoFiltered: "(disaring dari _MAX_ total data history)",
            search: "Cari:",
            paginate: {
                first: "Pertama",
                last: "Terakhir",
                next: "Selanjutnya",
                previous: "Sebelumnya"
            }
        }
    });

    // Filter buttons
    $('#btnFilter').on('click', function(){
        table.ajax.reload();
    });
    
    $('#btnReset').on('click', function(){
        $('#start_date').val('<?= date('Y-m-01') ?>');
        $('#end_date').val('<?= date('Y-m-d') ?>');
        table.search('').draw();
    });

    // Export links builder
    function buildExportUrl(scriptName) {
        var params = $.param({
            start_date: $('#start_date').val() || '',
            end_date: $('#end_date').val() || ''
        });
        return scriptName + '?' + params;
    }
    
    $('#exportExcel').on('click', function(e){
        e.preventDefault();
        window.location.href = buildExportUrl('export_history_excel.php');
    });
    
    $('#exportPdf').on('click', function(e){
        e.preventDefault();
        window.location.href = buildExportUrl('export_history_pdf.php');
    });

})();
</script>

<?php
ob_end_flush();
?>
