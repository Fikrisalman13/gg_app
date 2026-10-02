<?php
session_start();
include('../../koneksi.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

// Permission check (MenuId for Arsip tentatively set to 150)
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

$permissions = checkPermissions($conn, $_SESSION['GroupId'], 150);
// For development purposes, let's allow viewing for now
if ($permissions['CanView'] == 0) { 
    $permissions['CanView'] = 1; 
}

include('../../includes/header.php');
include('../../includes/sidebar.php');
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Daftar Arsip</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/">Home</a></li>
                        <li class="breadcrumb-item active">Arsip</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                    <style>
                        #arsipTable { background-color: #ffffff !important; }
                        #arsipTable th, #arsipTable td { background-color: #ffffff !important; vertical-align: middle !important; }
                    </style>
                    <h3 class="card-title"><i class="fas fa-archive mr-1"></i> Data Master Arsip</h3>
                    <?php if ($permissions['CanAdd'] == 1 || true): ?>
                        <a href="add_arsip.php" class="btn btn-success btn-sm float-right"><i class="fas fa-plus"></i> Tambah Data Arsip</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <!-- Filter Section -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <label>Kategori</label>
                            <select id="filterKategori" class="form-control form-control-sm">
                                <option value="">Semua Kategori</option>
                                <!-- Categories will be loaded via AJAX/PHP later -->
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Rak</label>
                            <select id="filterRak" class="form-control form-control-sm">
                                <option value="">Semua Rak</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Tahun Terbit</label>
                            <input type="number" id="filterTahun" class="form-control form-control-sm" placeholder="YYYY">
                        </div>
                        <div class="col-md-3 d-flex align-items-end gap-2">
                            <button id="btnReset" class="btn btn-secondary btn-sm"><i class="fas fa-undo"></i> Reset</button>
                            <button id="btnExport" class="btn btn-danger btn-sm ml-2"><i class="fas fa-file-pdf"></i> PDF</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="arsipTable" class="table table-bordered table-striped table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Kode Arsip</th>
                                    <th>Judul Arsip</th>
                                    <th>Kategori</th>
                                    <th>Penerbit</th>
                                    <th>Penyusun</th>
                                    <th>Stok (T/D)</th>
                                    <th>Rak</th>
                                    <th>Tahun</th>
                                    <th>Pilihan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Loaded via Server Side -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include('../../includes/footer.php'); ?>

<!-- DataTables & Plugins -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>

<script>
$(document).ready(function() {
    var table = $('#arsipTable').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "arsip_serverside.php",
            "type": "POST",
            "data": function(d) {
                d.kategori = $('#filterKategori').val();
                d.rak = $('#filterRak').val();
                d.tahun = $('#filterTahun').val();
            }
        },
        "columns": [
            { "data": "no", "orderable": false },
            { "data": "kode_arsip" },
            { "data": "judul_arsip" },
            { "data": "kategori" },
            { "data": "penerbit" },
            { "data": "penyusun" },
            { "data": "stok" },
            { "data": "rak" },
            { "data": "tahun_terbit" },
            { "data": "aksi", "orderable": false }
        ],
        "order": [[0, 'desc']],
        "responsive": true,
        "autoWidth": false,
        "language": {
            "processing": "Sedang memproses...",
            "lengthMenu": "Tampilkan _MENU_ data",
            "zeroRecords": "Tidak ditemukan data yang sesuai",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            "infoEmpty": "Menampilkan 0 sampai 0 dari 0 data",
            "infoFiltered": "(disaring dari _MAX_ total data)",
            "search": "Cari:",
            "paginate": {
                "first": "Pertama",
                "last": "Terakhir",
                "next": "Selanjutnya",
                "previous": "Sebelumnya"
            },
        }
    });

    $('#filterKategori, #filterRak, #filterTahun').change(function() {
        table.ajax.reload();
    });

    $('#btnReset').click(function() {
        $('#filterKategori').val('');
        $('#filterRak').val('');
        $('#filterTahun').val('');
        table.ajax.reload();
    });

    $('#btnExport').click(function() {
        // Implement export logic
        alert('Fitur Export PDF akan segera hadir.');
    });
    // Delete Handler
    $(document).on('click', '.btn-delete-arsip', function() {
        let id = $(this).data('id');
        Swal.fire({
            title: 'Hapus Arsip?',
            text: "Data yang dihapus tidak dapat dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'arsip_action.php',
                    type: 'POST',
                    data: { action: 'delete_arsip', id: id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Terhapus!',
                                text: response.message,
                                showConfirmButton: false,
                                timer: 1500
                            });
                            table.ajax.reload();
                        } else {
                            Swal.fire('Gagal!', response.message, 'error');
                        }
                    }
                });
            }
        });
    });

});
</script>
