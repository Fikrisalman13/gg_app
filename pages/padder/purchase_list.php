<?php
// pembelian.php - Manajemen Pembelian Padder
session_start();
ob_start();

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';

date_default_timezone_set('Asia/Jakarta');

// ====== Auth & Permission ======
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// helper: ambil permission user untuk menu Pembelian
function checkPermissions($conn, $groupId, $menuId) {
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete
            FROM dbo.SMGroupTrustee
            WHERE GroupId = ? AND MenuId = ?";
    $params = [$groupId, $menuId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    $permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    if ($stmt !== false) sqlsrv_free_stmt($stmt);

    return $permissions;
}

// Ambil permission (sesuaikan MenuId dengan menu Pembelian di sistem Anda)
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 113); // Contoh MenuId = 113 untuk Pembelian
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}

// ====== Get Padder List for Dropdown ======
function getPadderList($conn) {
    $sql = "SELECT padder_id, padder_name 
            FROM dbo.pad_m_padder 
            ORDER BY padder_id";
    $stmt = sqlsrv_query($conn, $sql);
    
    $padders = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $padders[] = $row;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    
    return $padders;
}

// ====== Get Vendor List for Dropdown ======
function getVendorList($conn) {
    $sql = "SELECT vendor_id, vendor_name 
            FROM dbo.pad_m_vendor 
            ORDER BY vendor_name";
    $stmt = sqlsrv_query($conn, $sql);
    
    $vendors = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $vendors[] = $row;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    
    return $vendors;
}

$padderList = getPadderList($conn);
$vendorList = getVendorList($conn);

// Include layout parts (header/sidebar)
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Pembelian Padder</title>

    <!-- CSS (AdminLTE + DataTables minimal) -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">

    <style>
        .badge-purchase { font-size: .9em; padding: 5px 8px; }
        #table-spinner {
            display: none;
            margin-bottom: 8px;
            text-align: center;
        }
        #table-spinner .spinner-border { width: 1rem; height: 1rem; vertical-align: middle; }
        
        /* Button spacing in action column */
        .btn-action {
            margin: 1px;
        }
        .filter-box {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
        }
        .price-amount {
            font-weight: bold;
            color: #28a745;
        }
        .purchase-card {
            border-left: 4px solid #007bff;
        }
        .table td {
            vertical-align: middle;
        }
        .currency-symbol {
            font-size: 0.875rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
<div class="content-wrapper">
    <!-- header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1 class="m-0">Manajemen Pembelian Padder</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Pembelian</li>
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
                    <h3 class="card-title">Daftar Pembelian Padder</h3>
                    <?php if (!empty($permissions['CanAdd']) && $permissions['CanAdd'] == 1): ?>
                        <a href="purchase.php" class="btn btn-success btn-sm float-right"><i class="fas fa-plus"></i> Tambah Pembelian</a>
                    <?php endif; ?>
                </div>

                <div class="card-body">
                    <!-- Filter -->
                    <div class="filter-box">
                        <form id="filterForm" class="form-inline">
                            <div class="form-group mr-3">
                                <label for="padder_id" class="mr-2">Padder:</label>
                                <select id="padder_id" name="padder_id" class="form-control form-control-sm">
                                    <option value="">Semua Padder</option>
                                    <?php foreach ($padderList as $padder): ?>
                                        <option value="<?= htmlspecialchars($padder['padder_id']) ?>">
                                            <?= htmlspecialchars($padder['padder_id']) ?> - <?= htmlspecialchars($padder['padder_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group mr-3">
                                <label for="vendor_id" class="mr-2">Vendor:</label>
                                <select id="vendor_id" name="vendor_id" class="form-control form-control-sm">
                                    <option value="">Semua Vendor</option>
                                    <?php foreach ($vendorList as $vendor): ?>
                                        <option value="<?= htmlspecialchars($vendor['vendor_id']) ?>">
                                            <?= htmlspecialchars($vendor['vendor_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group mr-3">
                                <label for="start_date" class="mr-2">Dari Tanggal:</label>
                                <input type="date" id="start_date" name="start_date" class="form-control form-control-sm">
                            </div>

                            <div class="form-group mr-3">
                                <label for="end_date" class="mr-2">Sampai Tanggal:</label>
                                <input type="date" id="end_date" name="end_date" class="form-control form-control-sm">
                            </div>

                            <button type="button" id="btnFilter" class="btn btn-primary btn-sm mr-2"><i class="fas fa-filter"></i> Filter</button>
                            <button type="button" id="btnReset" class="btn btn-secondary btn-sm"><i class="fas fa-sync-alt"></i> Reset</button>
                        </form>
                    </div>

                    <!-- spinner kecil -->
                    <div id="table-spinner">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                        <small class="text-muted ml-2">Memuat data...</small>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table id="purchaseTable" class="table table-hover table-sm" style="width:100%;">
                            <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>Tanggal Beli</th>
                                    <th>Padder ID</th>
                                    <th>Nama Padder</th>
                                    <th>PO Number</th>
                                    <th>Vendor</th>
                                    <th>Harga</th>
                                    <th>Remarks</th>
                                    <th>Aksi</th>
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

<!-- Modal View Purchase -->
<div class="modal fade" id="viewPurchaseModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Detail Pembelian</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><strong>Tanggal Pembelian:</strong></label>
                            <p id="viewPurchaseDate" class="form-control-plaintext"></p>
                        </div>
                        <div class="form-group">
                            <label><strong>Padder ID:</strong></label>
                            <p id="viewPadderId" class="form-control-plaintext"></p>
                        </div>
                        <div class="form-group">
                            <label><strong>Nama Padder:</strong></label>
                            <p id="viewPadderName" class="form-control-plaintext"></p>
                        </div>
                        <div class="form-group">
                            <label><strong>PO Number:</strong></label>
                            <p id="viewPoNumber" class="form-control-plaintext"></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><strong>Vendor:</strong></label>
                            <p id="viewVendorName" class="form-control-plaintext"></p>
                        </div>
                        <div class="form-group">
                            <label><strong>Harga:</strong></label>
                            <p id="viewPrice" class="form-control-plaintext price-amount"></p>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label><strong>Remarks:</strong></label>
                    <p id="viewRemarks" class="form-control-plaintext border rounded p-2 bg-light"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPTS -->
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/js/datatables/dataTables.bootstrap5.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
(function(){
    // Permission fallback
    window.appPermissions = {
        canEdit: <?php echo (!empty($permissions['CanEdit']) && $permissions['CanEdit'] == 1) ? 'true' : 'false'; ?>,
        canDelete: <?php echo (!empty($permissions['CanDelete']) && $permissions['CanDelete'] == 1) ? 'true' : 'false'; ?>
    };

    var $spinner = $('#table-spinner');
    var viewModal = $('#viewPurchaseModal');

    var table = $('#purchaseTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'purchase_serverside.php',
            type: 'POST',
            data: function(d) {
                d.padder_id = $('#padder_id').val();
                d.vendor_id = $('#vendor_id').val();
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
                console.error('AJAX error details:', {
                    status: status,
                    error: error,
                    response: xhr.responseText,
                    readyState: xhr.readyState
                });
                
                var errorMessage = 'Gagal memuat data. ';
                if (xhr.responseText) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        errorMessage += response.error || response.message || 'Terjadi kesalahan server.';
                    } catch (e) {
                        errorMessage += 'Terjadi kesalahan pada server.';
                    }
                } else {
                    errorMessage += 'Tidak ada response dari server.';
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Terjadi kesalahan',
                    html: errorMessage + '<br><small>Periksa console untuk detail lebih lanjut.</small>',
                    timer: 5000,
                    showConfirmButton: true
                });
            }
        },
        columns: [
            { 
                data: null, 
                orderable: false, 
                searchable: false, 
                render: function(data, type, row, meta) {
                    return meta.row + 1 + meta.settings._iDisplayStart;
                }
            },
            { 
                data: 'purchase_date',
                render: function(data, type, row) {
                    if (!data) return '-';
                    var date = new Date(data);
                    return date.toLocaleDateString('id-ID');
                }
            },
            { 
                data: 'padder_id',
                render: function(data, type, row) {
                    return '<strong>' + (data || '-') + '</strong>';
                }
            },
            { 
                data: 'padder_name',
                render: function(data, type, row) {
                    return data || '-';
                }
            },
            { 
                data: 'po_number',
                render: function(data, type, row) {
                    return data || '-';
                }
            },
            { 
                data: 'vendor_name',
                render: function(data, type, row) {
                    return data || '-';
                }
            },
            { 
                data: 'price',
                render: function(data, type, row) {
                    if (!data) return '-';
                    return '<span class="price-amount">Rp ' + formatNumber(data) + '</span>';
                }
            },
            { 
                data: 'remarks',
                render: function(data, type, row) {
                    if (data && data.length > 50) {
                        return data.substring(0, 50) + '...';
                    }
                    return data || '-';
                }
            },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: function(id, type, row) {
                    if (!id) return '-';
                    
                    var canEdit = window.appPermissions.canEdit;
                    var canDelete = window.appPermissions.canDelete;

                    var html = '<div class="btn-group">';
                    
                    if (canEdit) {
                        html += '<a href="edit_purchase.php?id=' + id + '" class="btn btn-warning btn-sm btn-action" title="Edit"><i class="fas fa-edit"></i></a>';
                    }
                    if (canDelete) {
                        html += '<button class="btn btn-danger btn-sm btn-action btn-delete" data-id="' + id + '" title="Hapus"><i class="fas fa-trash"></i></button>';
                    }
                    html += '</div>';
                    return html;
                }
            }
        ],
        responsive: true,
        autoWidth: false,
        lengthMenu: [[10,25,50],[10,25,50]],
        language: {
            search: "Cari:",
            lengthMenu: "Tampil _MENU_ data per halaman",
            info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
            infoFiltered: "(disaring dari _MAX_ total data)",
            zeroRecords: "Tidak ada data yang ditemukan",
            paginate: {
                first: "Pertama",
                last: "Terakhir",
                next: "Berikutnya",
                previous: "Sebelumnya"
            }
        }
    });

    // Format number function
    function formatNumber(number) {
        return new Intl.NumberFormat('id-ID').format(number);
    }

    // Filter buttons
    $('#btnFilter').on('click', function(){
        table.ajax.reload();
    });
    
    $('#btnReset').on('click', function(){
        $('#padder_id').val('');
        $('#vendor_id').val('');
        $('#start_date').val('');
        $('#end_date').val('');
        table.ajax.reload();
    });

    // Set default dates
    var today = new Date();
    var firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    $('#start_date').val(firstDay.toISOString().split('T')[0]);
    $('#end_date').val(today.toISOString().split('T')[0]);

    // Delete handler
    $(document).on('click', '.btn-delete', function(){
        var id = $(this).data('id');
        if (!id) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'ID tidak valid',
                timer: 3000,
                showConfirmButton: false
            });
            return;
        }
        
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: 'Data pembelian akan dihapus secara permanen!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then(function(result){
            if (result.isConfirmed) {
                window.location.href = 'delete_pembelian.php?id=' + id;
            }
        });
    });

    // Show notification if any
    <?php if (isset($_SESSION['success'])): ?>
    Swal.fire({ 
        icon: 'success', 
        title: 'Sukses!', 
        text: <?= json_encode($_SESSION['success']) ?>, 
        timer: 3000, 
        showConfirmButton: false 
    });
    <?php unset($_SESSION['success']); endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    Swal.fire({ 
        icon: 'error', 
        title: 'Gagal!', 
        text: <?= json_encode($_SESSION['error']) ?>, 
        timer: 3000, 
        showConfirmButton: false 
    });
    <?php unset($_SESSION['error']); endif; ?>

})();
</script>

</body>
</html>

<?php include '../../includes/footer.php'; ?>