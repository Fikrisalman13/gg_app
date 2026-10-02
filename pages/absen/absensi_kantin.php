<?php
session_start();
ob_start();

// Error reporting untuk development (nonaktifkan di production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

date_default_timezone_set('Asia/Jakarta');

// ================= CEK PERMISSION ================= //
$groupId = $_SESSION['GroupId'];
$menuId = 126; // MenuId untuk Absensi Kantin

/**
 * Check user permissions dengan caching
 */
function checkUserPermissions($conn, $groupId, $menuId) {
    static $permissionsCache = [];
    
    $cacheKey = $groupId . '_' . $menuId;
    if (isset($permissionsCache[$cacheKey])) {
        return $permissionsCache[$cacheKey];
    }
    
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete 
            FROM dbo.SMGroupTrustee 
            WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    
    $permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    if ($stmt !== false) {
        if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $permissions = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
    
    $permissionsCache[$cacheKey] = $permissions;
    return $permissions;
}

$permissions = checkUserPermissions($conn, $groupId, $menuId);
if ($permissions['CanView'] == 0) {
    $error_message = "Anda tidak memiliki hak untuk melihat halaman ini.";
}

/**
 * Get all mesin dengan caching
 */
function getAllMesin($conn) {
    static $mesinCache = null;
    
    if ($mesinCache !== null) {
        return $mesinCache;
    }
    
    $sql = "SELECT id, nama_mesin FROM dbo.m_fingerprint ORDER BY nama_mesin";
    $stmt = sqlsrv_query($conn, $sql);
    $mesinData = [];
    
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $mesinData[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
    
    $mesinCache = $mesinData;
    return $mesinData;
}

/**
 * Get shift time ranges
 */
function getShiftRanges() {
    return [
        'non_shift' => [
            'name' => 'Non Shift',
            'start' => '11:30:00',
            'end' => '13:30:00',
            'date_adjustment' => 0
        ],
        'shift_malam' => [
            'name' => 'Shift Malam',
            'start' => '01:00:00',
            'end' => '04:00:00',
            'date_adjustment' => 1
        ],
        'shift_pagi' => [
            'name' => 'Shift Pagi',
            'start' => '09:00:00',
            'end' => '11:30:00',
            'date_adjustment' => 0
        ],
        'shift_siang' => [
            'name' => 'Shift Siang',
            'start' => '17:00:00',
            'end' => '20:00:00',
            'date_adjustment' => 0
        ],
        'no_all' => [
            'name' => 'No All',
            'ranges' => [
                ['start' => '04:00:00', 'end' => '09:00:00', 'date_adjustment' => 0],
                ['start' => '14:00:00', 'end' => '17:00:00', 'date_adjustment' => 0],
                ['start' => '20:00:00', 'end' => '01:00:00', 'date_adjustment' => 1]
            ]
        ]
    ];
}

// Get filter data untuk form
$mesinData = getAllMesin($conn);
$shiftRanges = getShiftRanges();

// Default filter values
$filters = [
    'tanggal_awal' => $_GET['tanggal_awal'] ?? date('Y-m-01'),
    'tanggal_akhir' => $_GET['tanggal_akhir'] ?? date('Y-m-d'),
    'mesin' => $_GET['mesin'] ?? '',
    'shift' => $_GET['shift'] ?? ''
];


?>

    <style>
        .dataTables_wrapper {
            position: relative;
        }
        .table th {
            border-top: none;
        }
        .card-header {
            border-bottom: 1px solid rgba(0,0,0,.125);
        }
        .shift-badge {
            font-size: 0.75em;
            padding: 4px 8px;
        }
        .filter-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .loading-spinner {
            color: white;
            font-size: 2rem;
        }
        .auto-refresh-info {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
        }
    </style>

<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row align-items-center mb-2">
                    <div class="col-md-6">
                        <h1 class="m-0">Absensi Kantin</h1>
                    </div>
                    <div class="col-md-6 text-md-right text-sm-left mt-sm-2 mt-md-0">
                        <ol class="breadcrumb float-md-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>                            
                            <li class="breadcrumb-item active">Absensi Kantin</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header bg-<?= $themeColor ?> text-white">
                            <h3 class="card-title m-0">Data Absensi Kantin</h3>
                            <div class="card-tools">
                                <a href="#" id="btnExportExcel" class="btn btn-success btn-sm">
                                    <i class="fas fa-file-excel"></i> Export Excel
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Form Filter -->
                            <div class="filter-section">
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <label class="small">Tanggal Awal</label>
                                        <input type="date" name="tanggal_awal" class="form-control form-control-sm filter-input" value="<?= htmlspecialchars($filters['tanggal_awal']) ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="small">Tanggal Akhir</label>
                                        <input type="date" name="tanggal_akhir" class="form-control form-control-sm filter-input" value="<?= htmlspecialchars($filters['tanggal_akhir']) ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="small">Mesin</label>
                                        <select name="mesin" class="form-control form-control-sm filter-select">
                                            <option value="">Semua Mesin</option>
                                            <?php foreach ($mesinData as $mesin): ?>
                                                <option value="<?= $mesin['id'] ?>" <?= $filters['mesin'] == $mesin['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($mesin['nama_mesin']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="small">Tarikan Shift</label>
                                        <select name="shift" class="form-control form-control-sm filter-select">
                                            <option value="">Semua Shift</option>
                                            <?php foreach ($shiftRanges as $key => $shift): ?>
                                                <?php if ($key == 'no_all'): ?>
                                                    <option value="<?= $key ?>" <?= $filters['shift'] == $key ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($shift['name']) ?> (04:00-09:00, 14:00-17:00, 20:00-01:00)
                                                    </option>
                                                <?php else: ?>
                                                    <option value="<?= $key ?>" <?= $filters['shift'] == $key ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($shift['name']) ?> (<?= $shift['start'] ?> - <?= $shift['end'] ?>)
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <a href="absensi_kantin.php" class="btn btn-secondary btn-sm">
                                            <i class="fas fa-eraser"></i> Reset Filter
                                        </a>
                                        <span class="auto-refresh-info ml-2">
                                            <i class="fas fa-info-circle"></i> Data akan otomatis dimuat ulang saat filter diubah
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Loading Overlay -->
                            <div class="loading-overlay">
                                <div class="loading-spinner">
                                    <i class="fas fa-spinner fa-spin"></i> Memuat data...
                                </div>
                            </div>

                            <!-- Tabel Data -->
                            <div class="table-responsive">
                                <table id="absensiTable" class="table table-hover table-sm w-100">
                                    <thead class="thead-light">
                                        <tr class="text-center">
                                            <th>No</th>
                                            <th>Tanggal</th>
                                            <th>Jam</th>
                                            <th>Nama Karyawan</th>
                                            <th>Departemen</th>
                                            <th>Bagian</th>
                                            <th>Sub Bagian</th>
                                            <th>Mesin</th>
                                            <th>Shift</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Data akan di-load via AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
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

<script>
$(document).ready(function () {
    // URL untuk AJAX
    const ajaxUrl = 'ajax_absensi_kantin.php';
    
    // Debounce function untuk delay auto load
    let debounceTimer;
    function debounce(callback, delay) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(callback, delay);
    }
    
    // Inisialisasi DataTable dengan server-side processing
    const dataTable = $('#absensiTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: ajaxUrl,
            type: 'GET',
            data: function (d) {
                // Tambahkan parameter filter
                d.tanggal_awal = $('input[name="tanggal_awal"]').val();
                d.tanggal_akhir = $('input[name="tanggal_akhir"]').val();
                d.mesin = $('select[name="mesin"]').val();
                d.shift = $('select[name="shift"]').val();
                return d;
            },
            beforeSend: function() {
                $('.loading-overlay').show();
            },
            complete: function() {
                $('.loading-overlay').hide();
            },
            error: function (xhr, error, thrown) {
                console.error('DataTables error:', error, thrown);
                
                // Coba parse error message dari response
                let errorMsg = 'Gagal memuat data. Silakan refresh halaman.';
                
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.error) {
                        errorMsg = response.error;
                    }
                } catch (e) {
                    // Jika bukan JSON, cek jika ada HTML dalam response
                    if (xhr.responseText.includes('<!DOCTYPE')) {
                        errorMsg = 'Server mengembalikan halaman HTML bukan JSON. Silakan cek konfigurasi.';
                    }
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: errorMsg,
                    timer: 5000,
                    showConfirmButton: true
                });
            }
        },
        columns: [
            { data: 0, className: 'text-center' }, // No
            { data: 1, className: 'text-center' }, // Tanggal
            { data: 2, className: 'text-center' }, // Jam
            { data: 3 }, // Nama Karyawan
            { data: 4 }, // Departemen
            { data: 5 }, // Bagian
            { data: 6 }, // Sub Bagian
            { data: 7, className: 'text-center' }, // Mesin
            { data: 8, className: 'text-center' } // Shift
        ],
        responsive: true,
        autoWidth: false,
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        language: {
            processing: "Memproses...",
            lengthMenu: "Tampilkan _MENU_ data per halaman",
            zeroRecords: "Tidak ada data ditemukan",
            info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
            infoEmpty: "Tidak ada data tersedia",
            infoFiltered: "(disaring dari _MAX_ total data)",
            search: "Cari:",
            paginate: {
                first: "Pertama",
                last: "Terakhir",
                next: "Selanjutnya",
                previous: "Sebelumnya"
            }
        },
        order: [[1, 'desc'], [2, 'desc']],
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
    });

    // Fungsi untuk reload DataTable dengan update URL
    function reloadDataTable() {
        // Update URL dengan parameter filter
        const urlParams = new URLSearchParams();
        urlParams.append('tanggal_awal', $('input[name="tanggal_awal"]').val());
        urlParams.append('tanggal_akhir', $('input[name="tanggal_akhir"]').val());
        urlParams.append('mesin', $('select[name="mesin"]').val());
        urlParams.append('shift', $('select[name="shift"]').val());
        
        const newUrl = 'absensi_kantin.php?' + urlParams.toString();
        window.history.replaceState({}, '', newUrl);
        
        // Reload DataTable dengan filter baru
        dataTable.ajax.reload();
    }

    // Auto load ketika filter berubah
    $('.filter-input, .filter-select').on('change', function() {
        // Debounce untuk menghindari terlalu banyak request
        debounce(function() {
            reloadDataTable();
        }, 300); // Delay 300ms
    });

    // Export Excel
    $('#btnExportExcel').click(function(e) {
        e.preventDefault();
        
        const urlParams = new URLSearchParams({
            tanggal_awal: $('input[name="tanggal_awal"]').val(),
            tanggal_akhir: $('input[name="tanggal_akhir"]').val(),
            mesin: $('select[name="mesin"]').val(),
            shift: $('select[name="shift"]').val()
        });
        
        const url = `export_absensi_kantin.php?${urlParams.toString()}`;
        
        Swal.fire({
            title: 'Export Data',
            text: 'Mempersiapkan file Excel...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        window.location.href = url;
        
        setTimeout(() => {
            Swal.close();
        }, 2000);
    });

    // Notifikasi session
    <?php if (isset($_SESSION['success'])): ?>
    Swal.fire({ 
        icon: 'success', 
        title: 'Sukses!', 
        text: "<?= addslashes($_SESSION['success']) ?>", 
        timer: 3000, 
        showConfirmButton: false 
    });
    <?php unset($_SESSION['success']); endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    Swal.fire({ 
        icon: 'error', 
        title: 'Gagal!', 
        text: "<?= addslashes($_SESSION['error']) ?>", 
        timer: 3000, 
        showConfirmButton: false 
    });
    <?php unset($_SESSION['error']); endif; ?>
});
</script>
