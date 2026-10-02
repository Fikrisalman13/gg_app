<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// Check if user is logged in
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';

// Check database connection
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

date_default_timezone_set('Asia/Jakarta');

// Check permissions (assuming MenuId for e-dokumen is 71)
$groupId = $_SESSION['GroupId'];
$menuId = 71;

$sql = "SELECT CanView, CanAdd, CanEdit, CanDelete 
        FROM dbo.SMGroupTrustee 
        WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

$permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $permissions = $row;
}
sqlsrv_free_stmt($stmt);

if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}

// Get filter values
$filters = [
    'kategori' => $_GET['kategori'] ?? '',
    'kategori_name' => $_GET['kategori_name'] ?? '',
    'dept' => $_GET['dept'] ?? '',
    'dept_name' => $_GET['dept_name'] ?? '',
    'bagian' => $_GET['bagian'] ?? '',
    'bagian_name' => $_GET['bagian_name'] ?? '',
    'subbag' => $_GET['subbag'] ?? '',
    'subbag_name' => $_GET['subbag_name'] ?? ''
];

// Get dropdown options
$dropdownOptions = getDropdownOptions($conn, $filters['dept'], $filters['bagian']);

ob_end_flush();
?>
    <style>
        .filter-box {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .badge-revisi {
            font-size: 0.8em;
            padding: 3px 6px;
        }
        .file-icon {
            font-size: 1.5em;
            color: #d33;
        }
        .autoload-info {
            background: #e3f2fd;
            padding: 8px 12px;
            border-radius: 4px;
            margin-bottom: 10px;
            font-size: 0.9em;
            border-left: 4px solid #2196f3;
        }
    </style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Dokumen ISO</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Dokumen ISO</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title"><i class="fas fa-list mr-1"></i>
                        Daftar Dokumen</h3>
                    <?php if ($permissions['CanAdd'] == 1): ?>
                        <a href="add_dokumen.php" class="btn btn-success btn-sm float-right"><i class="fas fa-plus"></i> Tambah Dokumen</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <!-- Filter Section -->
                    <div class="filter-box">
                        <form method="get" id="filterForm">
                            <div class="form-row">
                                <!-- Dropdown Kategori -->
                                <div class="form-group col-md-3">
                                    <label for="kategori">Kategori:</label>
                                    <select class="form-control form-control-sm" id="kategori" name="kategori">
                                        <option value="">Semua</option>
                                        <?php foreach ($dropdownOptions['kategori'] as $row): ?>
                                            <option value="<?= $row['id_kategori'] ?>" 
                                                <?= $filters['kategori'] == $row['id_kategori'] ? 'selected' : '' ?>
                                                data-name="<?= htmlspecialchars($row['nama_kategori']) ?>">
                                                <?= htmlspecialchars($row['nama_kategori']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="kategori_name" id="kategori_name" value="<?= htmlspecialchars($filters['kategori_name']) ?>">
                                </div>

                                <!-- Dropdown Departemen -->
                                <div class="form-group col-md-3">
                                    <label for="dept">Departemen:</label>
                                    <select class="form-control form-control-sm" id="dept" name="dept">
                                        <option value="">Semua</option>
                                        <?php foreach ($dropdownOptions['dept'] as $row): ?>
                                            <option value="<?= $row['id_dept'] ?>" 
                                                <?= $filters['dept'] == $row['id_dept'] ? 'selected' : '' ?>
                                                data-name="<?= htmlspecialchars($row['dept']) ?>">
                                                <?= htmlspecialchars($row['dept']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="dept_name" id="dept_name" value="<?= htmlspecialchars($filters['dept_name']) ?>">
                                </div>

                                <!-- Dropdown Bagian -->
                                <div class="form-group col-md-3">
                                    <label for="bagian">Bagian:</label>
                                    <select class="form-control form-control-sm" id="bagian" name="bagian">
                                        <option value="">Semua</option>
                                        <?php foreach ($dropdownOptions['bagian'] as $row): ?>
                                            <option value="<?= $row['id_bag'] ?>" 
                                                <?= $filters['bagian'] == $row['id_bag'] ? 'selected' : '' ?>
                                                data-name="<?= htmlspecialchars($row['bagian']) ?>">
                                                <?= htmlspecialchars($row['bagian']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="bagian_name" id="bagian_name" value="<?= htmlspecialchars($filters['bagian_name']) ?>">
                                </div>

                                <!-- Dropdown Subbag -->
                                <div class="form-group col-md-3">
                                    <label for="subbag">Subbag:</label>
                                    <select class="form-control form-control-sm" id="subbag" name="subbag">
                                        <option value="">Semua</option>
                                        <?php foreach ($dropdownOptions['subbag'] as $row): ?>
                                            <option value="<?= $row['id_subbag'] ?>" 
                                                <?= $filters['subbag'] == $row['id_subbag'] ? 'selected' : '' ?>
                                                data-name="<?= htmlspecialchars($row['subbag']) ?>">
                                                <?= htmlspecialchars($row['subbag']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="subbag_name" id="subbag_name" value="<?= htmlspecialchars($filters['subbag_name']) ?>">
                                </div>
                            </div>

                            <!-- Buttons -->
                            <div class="form-row mt-2">
                                <div class="col">
                                    <a href="e_dokumen.php" class="btn btn-secondary btn-sm mr-2">
                                        <i class="fas fa-sync-alt"></i> Reset Filter
                                    </a>
                                    <button type="button" id="exportPdf" class="btn btn-danger btn-sm">
                                        <i class="fas fa-file-pdf"></i> Export PDF
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- DataTable -->
                    <div class="table-responsive mt-3">
                        <table id="dokumenTable" class="table table-hover table-sm">
                            <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>No Dokumen</th>                            
                                    <th>Nama Dokumen</th>
                                    <th>Revisi</th>
                                    <th>Departemen/Bagian</th>
                                    <th>Tgl Upload</th>
                                    <th>Deskripsi</th>                               
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data akan di-load via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
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

<script>
$(document).ready(function () {
    // Initialize DataTable dengan server-side processing
    var table = $('#dokumenTable').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "server_side_dokumen.php",
            "type": "POST",
            "data": function(d) {
                // Kirim nilai filter ke server
                d.kategori = $('#kategori').val();
                d.dept = $('#dept').val();
                d.bagian = $('#bagian').val();
                d.subbag = $('#subbag').val();
            },
            "error": function(xhr, error, thrown) {
                console.error('Error loading data:', error);
                console.log('Response:', xhr.responseText);
            }
        },
        "responsive": true,
        "autoWidth": false,
        "order": [[5, 'desc']], // Order by tanggal_upload desc
        "language": {
            "lengthMenu": "Tampilkan _MENU_ data per halaman",
            "zeroRecords": "Tidak ada data yang ditemukan",
            "info": "Menampilkan _START_ - _END_ dari _TOTAL_ data",
            "infoEmpty": "Tidak ada data tersedia",
            "infoFiltered": "(disaring dari _MAX_ total data)",
            "search": "Cari:",
            "paginate": {
                "first": "Pertama",
                "last": "Terakhir",
                "next": "Selanjutnya",
                "previous": "Sebelumnya"
            },
            "processing": "Memproses...",
            "loadingRecords": "Memuat data..."
        },
        "columns": [
            { 
                "data": null,
                "render": function(data, type, row, meta) {
                    return meta.row + meta.settings._iDisplayStart + 1;
                },
                "orderable": false,
                "width": "5%"
            },
            { 
                "data": "no_dokumen",
                "render": function(data, type, row) {
                    return '<div><small class="text-muted">' + (row.tanggal_terbit || '-') + '</small></div>' +
                           '<div>' + (row.kode_dok_seq || '-') + '</div>';
                },
                "orderable": true
            },
            { 
                "data": "nama_dokumen",
                "render": function(data, type, row) {
                    return '<div><small class="text-muted">' + (row.nama_kategori || '-') + '</small></div>' +
                           '<div>' + (row.nama_dokumen || '-') + '</div>';
                },
                "orderable": true
            },
            { 
                "data": "revisi",
                    "render": function(data, type, row) {
                        return '<div><small class="text-muted">' + (row.tanggal_revisi || '-') + '</small></div>' +
                               '<div><span class="badge badge-revisi badge-info">' + (typeof row.revisi !== 'undefined' && row.revisi !== null ? row.revisi : '1') + '</span></div>';
                    },
                "orderable": true
            },
            { 
                "data": "dept",
                "render": function(data, type, row) {
                    return '<div><small class="text-muted">' + (row.dept || '-') + '</small></div>' +
                           '<div>' + (row.bagian || '-') + '</div>';
                },
                "orderable": true
            },
            { 
                "data": "tanggal_upload",
                "render": function(data, type, row) {
                    return '<div><small class="text-muted">' + (row.upduser || '-') + '</small></div>' +
                           '<div>' + (row.tanggal_upload || '-') + '</div>';
                },
                "orderable": true
            },
            { 
                "data": "deskripsi",
                "orderable": false
            },
            { 
                "data": "id_dok",
                "render": function(data, type, row) {
                    let buttons = '';
                    if (row.file_pdf) {
                        buttons += '<a href="' + row.file_pdf + '" target="_blank" class="btn btn-info btn-sm" title="View"><i class="fas fa-eye"></i></a> ';
                    }
                    <?php if ($permissions['CanEdit'] == 1): ?>
                        buttons += '<a href="edit_dokumen.php?id=' + data + '" class="btn btn-warning btn-sm" title="Edit"><i class="fas fa-edit"></i></a> ';
                    <?php endif; ?>
                    <?php if ($permissions['CanDelete'] == 1): ?>
                        buttons += '<button class="btn btn-danger btn-sm btn-delete" data-id="' + data + '" title="Hapus"><i class="fas fa-trash"></i></button>';
                    <?php endif; ?>
                    return buttons;
                },
                "orderable": false,
                "width": "15%"
            }
        ]
    });

    // Handle delete button
    $(document).on('click', '.btn-delete', function () {
        let id = $(this).data('id');
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: 'Dokumen akan dihapus secara permanen!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'delete_dokumen.php?id=' + id;
            }
        });
    });

    // Export PDF
    $('#exportPdf').on('click', function() {
        const params = new URLSearchParams({
            kategori: $('#kategori').val(),
            dept: $('#dept').val(),
            bagian: $('#bagian').val(),
            subbag: $('#subbag').val(),
            export: 'pdf'
        });
        window.open('export_dokumen.php?' + params.toString(), '_blank');
    });

    // Show notifications
    <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Sukses!',
            text: "<?= $_SESSION['success'] ?>",
            timer: 3000,
            showConfirmButton: false
        });
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: "<?= $_SESSION['error'] ?>",
            timer: 3000,
            showConfirmButton: false
        });
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    // Update hidden fields dengan nama yang dipilih
    $('#kategori').change(function() {
        var selected = $(this).find('option:selected');
        $('#kategori_name').val(selected.data('name') || '');
    });

    // Handle perubahan dropdown departemen
    $('#dept').change(function() {
        var deptId = $(this).val();
        var selected = $(this).find('option:selected');
        $('#dept_name').val(selected.data('name') || '');
        
        // Reset dan disable dropdown bagian sementara
        $('#bagian').html('<option value="">Loading...</option>').prop('disabled', true);
        $('#subbag').html('<option value="">Semua</option>').prop('disabled', true);
        
        if (deptId) {
            // Ambil data bagian berdasarkan departemen
            $.ajax({
                url: 'get_bagian.php',
                type: 'GET',
                data: { dept_id: deptId },
                dataType: 'json',
                success: function(data) {
                    var options = '<option value="">Semua</option>';
                    $.each(data, function(index, item) {
                        options += '<option value="' + item.id_bag + '" data-name="' + item.bagian + '">' + item.bagian + '</option>';
                    });
                    $('#bagian').html(options).prop('disabled', false);
                },
                error: function() {
                    $('#bagian').html('<option value="">Error loading data</option>').prop('disabled', false);
                }
            });
        } else {
            $('#bagian').html('<option value="">Semua</option>').prop('disabled', false);
        }
    });

    // Handle perubahan dropdown bagian
    $('#bagian').change(function() {
        var bagianId = $(this).val();
        var selected = $(this).find('option:selected');
        $('#bagian_name').val(selected.data('name') || '');
        
        // Reset dan disable dropdown subbag sementara
        $('#subbag').html('<option value="">Loading...</option>').prop('disabled', true);
        
        if (bagianId) {
            // Ambil data subbag berdasarkan bagian
            $.ajax({
                url: 'get_subbag.php',
                type: 'GET',
                data: { bag_id: bagianId },
                dataType: 'json',
                success: function(data) {
                    var options = '<option value="">Semua</option>';
                    $.each(data, function(index, item) {
                        options += '<option value="' + item.id_subbag + '" data-name="' + item.subbag + '">' + item.subbag + '</option>';
                    });
                    $('#subbag').html(options).prop('disabled', false);
                },
                error: function() {
                    $('#subbag').html('<option value="">Error loading data</option>').prop('disabled', false);
                }
            });
        } else {
            $('#subbag').html('<option value="">Semua</option>').prop('disabled', false);
        }
    });

    // Update hidden fields dengan nama yang dipilih
    $('#subbag').change(function() {
        var selected = $(this).find('option:selected');
        $('#subbag_name').val(selected.data('name') || '');
    });

    // AUTOLOAD: Event reload tabel ketika filter berubah
    $('#kategori, #dept, #bagian, #subbag').change(function() {
        table.ajax.reload();
    });
});
</script>


<?php 


/**
 * Get dropdown options untuk filter
 */
function getDropdownOptions($conn, $selectedDept = '', $selectedBagian = '') {
    $options = [
        'kategori' => [],
        'dept' => [],
        'bagian' => [],
        'subbag' => []
    ];
    
    // Get kategori options
    $sql = "SELECT id_kategori, nama_kategori FROM dbo.m_kategori_dok ORDER BY nama_kategori";
    $stmt = sqlsrv_query($conn, $sql);
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $options['kategori'][] = $row;
    }
    sqlsrv_free_stmt($stmt);

    // Get departemen options
    $sql = "SELECT id_dept, dept FROM dbo.m_dept ORDER BY dept";
    $stmt = sqlsrv_query($conn, $sql);
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $options['dept'][] = $row;
    }
    sqlsrv_free_stmt($stmt);
    
    // Get bagian options berdasarkan departemen yang dipilih
    if (!empty($selectedDept)) {
        $sql = "SELECT id_bag, bagian FROM dbo.m_bag WHERE id_dept = ? ORDER BY bagian";
        $params = [$selectedDept];
        $stmt = sqlsrv_query($conn, $sql, $params);
    } else {
        $sql = "SELECT id_bag, bagian FROM dbo.m_bag ORDER BY bagian";
        $stmt = sqlsrv_query($conn, $sql);
    }
    
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $options['bagian'][] = $row;
    }
    sqlsrv_free_stmt($stmt);
    
    // Get subbag options berdasarkan bagian yang dipilih
    if (!empty($selectedBagian)) {
        $sql = "SELECT id_subbag, subbag FROM dbo.m_subbag WHERE id_bag = ? ORDER BY subbag";
        $params = [$selectedBagian];
        $stmt = sqlsrv_query($conn, $sql, $params);
    } else {
        $sql = "SELECT id_subbag, subbag FROM dbo.m_subbag ORDER BY subbag";
        $stmt = sqlsrv_query($conn, $sql);
    }
    
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $options['subbag'][] = $row;
    }
    sqlsrv_free_stmt($stmt);
    
    return $options;
}
?>