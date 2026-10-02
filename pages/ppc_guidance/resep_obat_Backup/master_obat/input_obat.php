<?php
// pages/resep_obat/master_obat/input_obat.php
session_start();
require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

$obat_id = $_GET['id'] ?? null;
$mode = $obat_id ? 'Edit' : 'Tambah';
$data = null;

if ($obat_id) {
    $stmt = sqlsrv_query($conn, "SELECT * FROM dbo.resep_master_obat WHERE id = ?", [$obat_id]);
    if ($stmt) {
        $data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    }
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
include '../../../../includes/header.php';
include '../../../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1><?= $mode ?> Obat</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="list_obat.php">Master Obat</a></li>
                        <li class="breadcrumb-item active"><?= $mode ?></li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card card-<?= htmlspecialchars($themeColor); ?>">
                <div class="card-header">
                    <h3 class="card-title">Form Obat</h3>
                </div>
                <form id="obatForm">
                    <input type="hidden" name="obat_id" value="<?= $obat_id ?? '' ?>">
                    <div class="card-body">
                        <div class="form-group">
                            <label>Kode Obat</label>
                            <input type="text" class="form-control" name="kode_obat" required value="<?= $data['kode_obat'] ?? '' ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Code Prod ProInt</label>
                            <!-- Changed to Select2 -->
                            <select class="form-control select2-proint" name="codeprod_proint" style="width: 100%;">
                                <?php if(!empty($data['codeprod_proint'])): ?>
                                    <option value="<?= $data['codeprod_proint'] ?>" selected><?= $data['codeprod_proint'] ?></option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Nama Obat</label>
                            <!-- User remains able to edit this -->
                            <input type="text" class="form-control" id="nama_obat" name="nama_obat" required value="<?= $data['nama_obat'] ?? '' ?>">
                        </div>
                        <div class="form-group">
                            <label>Group Obat</label>
                            <input type="text" class="form-control" id="group_obat" name="group_obat" value="<?= $data['group_obat'] ?? '' ?>">
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                        <a href="list_obat.php" class="btn btn-default float-right">Kembali</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../../../../includes/footer.php'; ?>

<!-- Select2 -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
$(document).ready(function() {
    // Init Select2 for ProInt Product
    $('.select2-proint').select2({
        theme: 'bootstrap4',
        ajax: {
            url: 'get_proint_items.php',
            dataType: 'json',
            delay: 250,
            data: function(params) { return { q: params.term }; },
            processResults: function(data) { return { results: data.results }; }
        },
        placeholder: 'Cari Produk ProInt...',
        allowClear: true,
        minimumInputLength: 3
    });

    // Auto-fill logic
    $('.select2-proint').on('select2:select', function(e) {
        let item = e.params.data.item_data;
        if(item) {
             // Auto-fill Group Obat from ProInt Structname
             $('#group_obat').val(item.structname);
        }
    });

    $('#obatForm').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'save_obat.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(resp) {
                if (resp.status === 'success') {
                    Swal.fire('Sukses', 'Data berhasil disimpan', 'success').then(() => {
                        window.location.href = 'list_obat.php';
                    });
                } else {
                    Swal.fire('Error', resp.message, 'error');
                }
            },
            error: function(xhr) {
                Swal.fire('Error', 'Gagal menyimpan data', 'error');
            }
        });
    });
});
</script>
