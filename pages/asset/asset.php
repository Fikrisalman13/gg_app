<?php
// asset.php - versi rapi & optimized (DataTables server-side)
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

// helper: ambil permission user untuk menu Asset (MenuId = 56)
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

// Ambil permission (dipakai untuk tombol "Tambah" dan fallback JS)
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 56);
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}

// ====== Dropdown options (kategori, lokasi, status) ======
function getDropdownOptions($conn) {
    $options = ['kategori' => [], 'lokasi' => [], 'status' => []];

    $sql = "SELECT id_kategori, nama_kategori FROM dbo.m_kategori ORDER BY nama_kategori";
    $stmt = sqlsrv_query($conn, $sql);
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $options['kategori'][] = $row;
    }
    if ($stmt !== false) sqlsrv_free_stmt($stmt);

    $sql = "SELECT id_lokasi, nama_lokasi FROM dbo.m_lokasi ORDER BY nama_lokasi";
    $stmt = sqlsrv_query($conn, $sql);
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $options['lokasi'][] = $row;
    }
    if ($stmt !== false) sqlsrv_free_stmt($stmt);

    $sql = "SELECT id_status, nama_status FROM dbo.m_status ORDER BY nama_status";
    $stmt = sqlsrv_query($conn, $sql);
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $options['status'][] = $row;
    }
    if ($stmt !== false) sqlsrv_free_stmt($stmt);

    return $options;
}

$dropdownOptions = getDropdownOptions($conn);

// ====== Export redirect (pdf / qrcode) - tetap pakai redirect ke generator yg ada ======
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    header('Location: generate_pdf_asset.php?' . http_build_query($_GET));
    exit;
}
if (isset($_GET['export']) && $_GET['export'] === 'qrcode') {
    header('Location: generate_qrcode_asset.php?' . http_build_query($_GET));
    exit;
}

// Include layout parts (header/sidebar)
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

    <style>
        /* small clean styles */
        .filter-box {
            background: #f8f9fa;
            padding: 12px;
            margin-bottom: 18px;
            border-radius: 6px;
        }
        .badge-asset { font-size: .9em; padding: 5px 8px; }
        /* small spinner above table */
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
                <div class="col-sm-6"><h1 class="m-0">Manajemen Asset</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Asset</li>
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
                    <h3 class="card-title"><i class="fas fa-list mr-1"></i>
                    Daftar Asset</h3>
                    <?php if (!empty($permissions['CanAdd']) && $permissions['CanAdd'] == 1): ?>
                        <a href="add_asset.php" class="btn btn-success btn-sm float-right"><i class="fas fa-plus"></i> Tambah Asset</a>
                    <?php endif; ?>
                </div>

                <div class="card-body">
                    <!-- Filter -->
                    <div class="filter-box">
                        <form id="filterForm" method="get" class="form-inline">
                            <div class="form-group mr-2">
                                <label for="kategori" class="mr-2">Kategori:</label>
                                <select id="kategori" name="kategori" class="form-control form-control-sm">
                                    <option value="">Semua</option>
                                    <?php foreach ($dropdownOptions['kategori'] as $row): ?>
                                        <option value="<?= htmlspecialchars($row['id_kategori']) ?>"><?= htmlspecialchars($row['nama_kategori']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group mr-2">
                                <label for="lokasi" class="mr-2">Lokasi:</label>
                                <select id="lokasi" name="lokasi" class="form-control form-control-sm">
                                    <option value="">Semua</option>
                                    <?php foreach ($dropdownOptions['lokasi'] as $row): ?>
                                        <option value="<?= htmlspecialchars($row['id_lokasi']) ?>"><?= htmlspecialchars($row['nama_lokasi']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group mr-2">
                                <label for="status" class="mr-2">Status:</label>
                                <select id="status" name="status" class="form-control form-control-sm">
                                    <option value="">Semua</option>
                                    <?php foreach ($dropdownOptions['status'] as $row): ?>
                                        <option value="<?= htmlspecialchars($row['id_status']) ?>"><?= htmlspecialchars($row['nama_status']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="button" id="btnFilter" class="btn btn-primary btn-sm mr-2"><i class="fas fa-filter"></i> Filter</button>
                            <button type="button" id="btnReset" class="btn btn-secondary btn-sm mr-2"><i class="fas fa-sync-alt"></i> Reset</button>

                            <div class="btn-group float-right" role="group" aria-label="exports" style="float:right;">
                                <a id="exportPdf" href="#" class="btn btn-danger btn-sm mr-1"><i class="fas fa-file-pdf"></i> Export PDF</a>
                                <a id="exportQr" href="#" class="btn btn-success btn-sm"><i class="fas fa-qrcode"></i> Generate QR</a>
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
                        <table id="assetTable" class="table table-hover table-sm" style="width:100%;">
                            <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>Kode Asset</th>
                                    <th>Kategori</th>
                                    <th>Merk/Tipe</th>
                                    <th>Lokasi</th>
                                    <th>Status</th>
                                    <th>Client</th>
                                    <th>Keterangan</th>
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
<!-- Chart CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/chart.js/Chart.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/chart.js/Chart.css">

<!-- DataTables JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<!-- SweetAlert -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
(function(){
    // Permission fallback: kalau server-side tidak mengembalikan per-row permission,
    // gunakan permission dari PHP (diberi ke JS di window.appPermissions)
    window.appPermissions = {
        canEdit: <?php echo (!empty($permissions['CanEdit']) && $permissions['CanEdit'] == 1) ? 'true' : 'false'; ?>,
        canDelete: <?php echo (!empty($permissions['CanDelete']) && $permissions['CanDelete'] == 1) ? 'true' : 'false'; ?>
    };

    var $spinner = $('#table-spinner');

    var table = $('#assetTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
        url: 'asset_serverside.php',
        type: 'POST',
        data: function(d) {
            d.kategori = $('#kategori').val();
            d.lokasi = $('#lokasi').val();
            d.status = $('#status').val();
            d.search_value = d.search.value; // ✅ tambahkan ini
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
                    text: 'Gagal memuat data. Periksa konsol untuk detail.',
                    timer: 3500,
                    showConfirmButton: false
                });
            }
        },
        columns: [
            { data: null, orderable: false, searchable: false, render: function(data, type, row, meta) {
                return meta.row + 1 + meta.settings._iDisplayStart;
            }},
            { data: 'kode_asset_seq' },
            { data: 'nama_kategori' },
            { data: 'merk_tipe' },
            { data: 'nama_lokasi' },
            { data: 'nama_status', render: function(d){ return '<span class="badge badge-info">'+ (d||'-') +'</span>'; } },
            { data: 'nama_lengkap' },
            { data: 'keterangan' },
            {
                data: 'aksi',
                orderable: false,
                searchable: false,
                render: function(aksi, type, row) {
                    // Support two formats:
                    // 1) aksi is just an id (legacy) -> fallback to appPermissions
                    // 2) aksi is an object: { id:..., can_edit:1/0, can_delete:1/0 }
                    var id, canEdit, canDelete;
                    if (aksi && typeof aksi === 'object') {
                        id = aksi.id;
                        canEdit = aksi.can_edit == 1 || aksi.can_edit === true;
                        canDelete = aksi.can_delete == 1 || aksi.can_delete === true;
                    } else {
                        id = aksi;
                        canEdit = window.appPermissions.canEdit;
                        canDelete = window.appPermissions.canDelete;
                    }

                    var html = '<a href="view_asset.php?id=' + encodeURIComponent(id) + '" class="btn btn-info btn-sm" title="View"><i class="fas fa-eye"></i></a> ';
                    if (canEdit) {
                        html += '<a href="edit_asset.php?id=' + encodeURIComponent(id) + '" class="btn btn-warning btn-sm" title="Edit"><i class="fas fa-edit"></i></a> ';
                    }
                    if (canDelete) {
                        html += '<button class="btn btn-danger btn-sm btn-delete" data-id="' + encodeURIComponent(id) + '" title="Hapus"><i class="fas fa-trash"></i></button>';
                    }
                    return html;
                }
            }
        ],
        
        responsive: true,
        autoWidth: false,
        lengthMenu: [[10,25,50],[10,25,50]],
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
        }

    });

    // Filter buttons
    $('#btnFilter').on('click', function(){
        table.ajax.reload();
    });
    $('#btnReset').on('click', function(){
        $('#kategori, #lokasi, #status').val('');
        table.ajax.reload();
    });

    // Export links: gunakan nilai filter saat ini
    function buildExportUrl(type) {
        var q = $.param({
            kategori: $('#kategori').val() || '',
            lokasi: $('#lokasi').val() || '',
            status: $('#status').val() || '',
            search_value: table.search() || '',
            export: type
        });
        return 'asset.php?' + q;
    }
    $('#exportPdf').on('click', function(e){
        e.preventDefault();
        window.location.href = buildExportUrl('pdf');
    });
    $('#exportQr').on('click', function(e){
        e.preventDefault();
        window.location.href = buildExportUrl('qrcode');
    });

    // Delete handler (redirect confirmation)
    $(document).on('click', '.btn-delete', function(){
        var id = $(this).data('id');
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: 'Asset akan dihapus secara permanen!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then(function(result){
            if (result.isConfirmed) {
                // redirect ke delete script (seperti request)
                window.location.href = 'delete_asset.php?id=' + encodeURIComponent(id);
            }
        });
    });

    // Show notification if any (session-based) - simple approach: server already echos into DOM via PHP session (we kept original behavior)
    <?php if (isset($_SESSION['success'])): ?>
    Swal.fire({ icon: 'success', title: 'Sukses!', text: <?= json_encode($_SESSION['success']) ?>, timer:3000, showConfirmButton:false });
    <?php unset($_SESSION['success']); endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    Swal.fire({ icon: 'error', title: 'Gagal!', text: <?= json_encode($_SESSION['error']) ?>, timer:3000, showConfirmButton:false });
    <?php unset($_SESSION['error']); endif; ?>

})();
</script>
