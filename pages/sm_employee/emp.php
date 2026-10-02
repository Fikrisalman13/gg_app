<?php
session_start();
ob_start();
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

$groupId = $_SESSION['GroupId'];
$menuId = 43; // MenuId untuk Employee

$sql = "SELECT CanView, CanAdd, CanEdit, CanDelete 
        FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);

$permissions = [];
if ($stmt === false) {
    die("Query gagal: " . print_r(sqlsrv_errors(), true));
} else {
    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    sqlsrv_free_stmt($stmt);
}

if (isset($permissions['CanView']) && $permissions['CanView'] == 0) {
    $error_message = "Anda tidak memiliki hak untuk melihat halaman ini.";
}

ob_end_flush();
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row align-items-center mb-2">
                <div class="col-md-6">
                    <h1 class="m-0">Data Karyawan</h1>
                </div>
                <div class="col-md-6 text-md-right text-sm-left mt-sm-2 mt-md-0">
                    <ol class="breadcrumb float-md-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Employee</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?= $error_message ?></div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                        <h3 class="card-title m-0"><i class="fas fa-list mr-1"></i>
                        Daftar Karyawan</h3>
                        <?php if (!empty($permissions['CanAdd'])): ?>
                            <a href="add_emp.php" class="btn btn-success btn-sm float-right">
                                <i class="fas fa-plus"></i> Tambah Karyawan
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($permissions['CanEdit'])): ?>
                            <a href="update_nik_massal.php" class="btn btn-warning btn-sm float-right mr-2 text-dark">
                                <i class="fas fa-id-badge"></i> Update NIK Massal
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <!-- Form Filter -->
                        <div class="row mb-3">
                            <div class="col-md-2">
                                <select id="filterDept" class="form-control form-control-sm">
                                    <option value="">Semua Departemen</option>
                                    <?php
                                    $sqlDept = "SELECT id_dept, dept FROM dbo.m_dept ORDER BY dept";
                                    $stmtDept = sqlsrv_query($conn, $sqlDept);
                                    while ($rowDept = sqlsrv_fetch_array($stmtDept, SQLSRV_FETCH_ASSOC)) {
                                        echo '<option value="' . $rowDept['id_dept'] . '">' . htmlspecialchars($rowDept['dept']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select id="filterBag" class="form-control form-control-sm" disabled>
                                    <option value="">Semua Bagian</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select id="filterSubbag" class="form-control form-control-sm" disabled>
                                    <option value="">Semua Subbag</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select id="filterJab" class="form-control form-control-sm" disabled>
                                    <option value="">Semua Jabatan</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select id="filterGol" class="form-control form-control-sm">
                                    <option value="">Semua Golongan</option>
                                    <?php
                                    $sqlGol = "SELECT id_gol, golongan FROM dbo.m_gol ORDER BY golongan";
                                    $stmtGol = sqlsrv_query($conn, $sqlGol);
                                    while ($rowGol = sqlsrv_fetch_array($stmtGol, SQLSRV_FETCH_ASSOC)) {
                                        echo '<option value="' . $rowGol['id_gol'] . '">' . htmlspecialchars($rowGol['golongan']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select id="filterStatus" class="form-control form-control-sm">
                                    <option value="">Semua Status</option>
                                    <option value="1">Aktif</option>
                                    <option value="0">Nonaktif</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-10 text-left">
                                <?php if (!empty($permissions['CanView'])): ?>
                                    <a href="#" id="btnExportExcel" class="btn btn-success btn-sm mr-1">
                                        <i class="fas fa-file-excel"></i> Export Excel
                                    </a>
                                    <a href="#" id="btnExportPDF" class="btn btn-danger btn-sm">
                                        <i class="fas fa-file-pdf"></i> Export PDF
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-2">
                                <button id="btnReset" class="btn btn-<?php echo htmlspecialchars($themeColor);?> btn-sm btn-block">Reset Filter</button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table id="empTable" class="table table-hover table-sm">
                                <thead class="thead-light">
                                    <tr class="text-center">
                                        <th>No</th>
                                        <th>Id Emp</th>
                                        <th>NIK</th>
                                        <th>Nama</th>
                                        <th>Status</th>
                                        <th>Departemen</th>
                                        <th>Bagian</th>
                                        <th>Sub Bagian</th>
                                        <th>Jabatan</th>
                                        <th>Golongan</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
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
    var table = $('#empTable').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "emp_data.php",
            "type": "POST",
            "data": function(d) {
                d.filterDept   = $('#filterDept').val();
                d.filterBag    = $('#filterBag').val();
                d.filterSubbag = $('#filterSubbag').val();
                d.filterJab    = $('#filterJab').val();
                d.filterGol    = $('#filterGol').val();
                d.filterStatus = $('#filterStatus').val();
            }
        },
        "responsive": true,
        "autoWidth": false,
        "language": {
            "lengthMenu": "Tampilkan _MENU_ data per halaman",
            "zeroRecords": "Tidak ada data ditemukan",
            "info": "Menampilkan _START_ - _END_ dari _TOTAL_ data",
            "infoEmpty": "Tidak ada data tersedia",
            "infoFiltered": "(disaring dari _MAX_ total data)",
            "search": "Cari:",
            "paginate": {
                "first": "Pertama",
                "last": "Terakhir",
                "next": "Selanjutnya",
                "previous": "Sebelumnya"
            }
        },
        "columnDefs": [
            { "orderable": false, "targets": -1 }
        ]
    });

    // Export PDF
    $('#btnExportPDF').click(function() {
        let filters = {
            filterDept: $('#filterDept').val(),
            filterBag: $('#filterBag').val(),
            filterSubbag: $('#filterSubbag').val(),
            filterJab: $('#filterJab').val(),
            filterGol: $('#filterGol').val(),
            filterStatus: $('#filterStatus').val()
        };
        let query = $.param(filters);
        window.open('export_emp_pdf.php?' + query, '_blank');
    });

    // Export Excel
    $('#btnExportExcel').click(function() {
        let filters = {
            filterDept: $('#filterDept').val(),
            filterBag: $('#filterBag').val(),
            filterSubbag: $('#filterSubbag').val(),
            filterJab: $('#filterJab').val(),
            filterGol: $('#filterGol').val(),
            filterStatus: $('#filterStatus').val()
        };
        let query = $.param(filters);
        window.open('export_emp_excel.php?' + query, '_blank');
    });


    // Fungsi untuk memuat Bagian berdasarkan Departemen
    function loadBags(deptId) {
        if (deptId) {
            $.ajax({
                url: 'get_bags.php',
                type: 'POST',
                data: { dept_id: deptId },
                success: function(response) {
                    $('#filterBag').html('<option value="">Semua Bagian</option>' + response);
                    $('#filterBag').prop('disabled', false);
                    $('#filterSubbag').html('<option value="">Semua Subbag</option>');
                    $('#filterSubbag').prop('disabled', true);
                    $('#filterJab').html('<option value="">Semua Jabatan</option>');
                    $('#filterJab').prop('disabled', true);
                }
            });
        } else {
            $('#filterBag').html('<option value="">Semua Bagian</option>');
            $('#filterBag').prop('disabled', true);
            $('#filterSubbag').html('<option value="">Semua Subbag</option>');
            $('#filterSubbag').prop('disabled', true);
            $('#filterJab').html('<option value="">Semua Jabatan</option>');
            $('#filterJab').prop('disabled', true);
        }
    }

    // Fungsi untuk memuat Subbag berdasarkan Bagian
    function loadSubbags(bagId) {
        if (bagId) {
            $.ajax({
                url: 'get_subbags.php',
                type: 'POST',
                data: { bag_id: bagId },
                success: function(response) {
                    $('#filterSubbag').html('<option value="">Semua Subbag</option>' + response);
                    $('#filterSubbag').prop('disabled', false);
                    $('#filterJab').html('<option value="">Semua Jabatan</option>');
                    $('#filterJab').prop('disabled', true);
                }
            });
        } else {
            $('#filterSubbag').html('<option value="">Semua Subbag</option>');
            $('#filterSubbag').prop('disabled', true);
            $('#filterJab').html('<option value="">Semua Jabatan</option>');
            $('#filterJab').prop('disabled', true);
        }
    }

    // Fungsi untuk memuat Jabatan berdasarkan Departemen, Bagian, dan Subbag
    function loadJabatan(deptId, bagId, subbagId) {
        if (deptId || bagId || subbagId) {
            $.ajax({
                url: 'get_jabatan.php',
                type: 'POST',
                data: { 
                    dept_id: deptId,
                    bag_id: bagId,
                    subbag_id: subbagId
                },
                success: function(response) {
                    $('#filterJab').html('<option value="">Semua Jabatan</option>' + response);
                    $('#filterJab').prop('disabled', false);
                }
            });
        } else {
            $('#filterJab').html('<option value="">Semua Jabatan</option>');
            $('#filterJab').prop('disabled', true);
        }
    }

    // Event ketika filter Departemen berubah
    $('#filterDept').change(function() {
        var deptId = $(this).val();
        loadBags(deptId);
        loadJabatan(deptId, $('#filterBag').val(), $('#filterSubbag').val());
        table.ajax.reload();
    });

    // Event ketika filter Bagian berubah
    $('#filterBag').change(function() {
        var bagId = $(this).val();
        loadSubbags(bagId);
        loadJabatan($('#filterDept').val(), bagId, $('#filterSubbag').val());
        table.ajax.reload();
    });

    // Event ketika filter Subbag berubah
    $('#filterSubbag').change(function() {
        var subbagId = $(this).val();
        loadJabatan($('#filterDept').val(), $('#filterBag').val(), subbagId);
        table.ajax.reload();
    });

    // Event ketika filter Jabatan berubah
    $('#filterJab').change(function() {
        table.ajax.reload();
    });

    // Event ketika filter Status berubah
    $('#filterStatus').change(function() {
        table.ajax.reload();
    });

    // Reset filter
    $('#btnReset').click(function() {
        $('#filterDept, #filterStatus').val('');
        $('#filterBag').html('<option value="">Semua Bagian</option>');
        $('#filterBag').prop('disabled', true);
        $('#filterSubbag').html('<option value="">Semua Subbag</option>');
        $('#filterSubbag').prop('disabled', true);
        $('#filterJab').html('<option value="">Semua Jabatan</option>');
        $('#filterJab').prop('disabled', true);
        table.ajax.reload();
    });

    // Event reload tabel ketika filter berubah
    $('#filterDept, #filterBag, #filterSubbag, #filterJab, #filterGol, #filterStatus').change(function() {
        table.ajax.reload();
    });

    // Handle delete        
    $(document).on('click', '.btn-delete', function () {
        let id = $(this).data('id');
        Swal.fire({
            title: 'Hapus Data?',
            text: 'Data tidak dapat dikembalikan setelah dihapus!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'delete_emp.php?nik=' + encodeURIComponent(id);
            }
        });
    });
    <?php if (isset($_SESSION['success'])): ?>
    Swal.fire({ icon: 'success', title: 'Sukses!', text: "<?= $_SESSION['success'] ?>", timer: 3000, showConfirmButton: false });
    <?php unset($_SESSION['success']); endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    Swal.fire({ icon: 'error', title: 'Gagal!', text: "<?= $_SESSION['error'] ?>", timer: 3000, showConfirmButton: false });
    <?php unset($_SESSION['error']); endif; ?>
});


</script>

