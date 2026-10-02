<?php
session_start();
include('../../koneksi.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

include('../../includes/header.php');
include('../../includes/sidebar.php');
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Tambah Data Arsip</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/">Home</a></li>
                        <li class="breadcrumb-item"><a href="arsip.php">Arsip</a></li>
                        <li class="breadcrumb-item active">Tambah</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-<?php echo htmlspecialchars($themeColor); ?>">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                    <h3 class="card-title"><i class="fas fa-file-medical mr-1"></i> Form Input Arsip Baru</h3>
                </div>
                <form id="formAddArsip" enctype="multipart/form-data">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Kode Arsip <span class="text-danger">*</span></label>
                                    <input type="text" name="kode_arsip" class="form-control" placeholder="Contoh: ARS-2025-001" required>
                                </div>
                                <div class="form-group">
                                    <label>Judul Arsip <span class="text-danger">*</span></label>
                                    <input type="text" name="judul_arsip" class="form-control" placeholder="Masukkan judul arsip" required>
                                </div>
                                <div class="form-group">
                                    <label>Kategori <span class="text-danger">*</span></label>
                                    <select name="id_kategori" id="id_kategori" class="form-control select2" style="width: 100%;" required>
                                        <option value="">Pilih Kategori</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Rak <span class="text-danger">*</span></label>
                                    <select name="id_rak" id="id_rak" class="form-control select2" style="width: 100%;" required>
                                        <option value="">Pilih Rak</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Penerbit</label>
                                    <select name="id_penerbit" class="form-control select2" style="width: 100%;">
                                        <option value="">Pilih Penerbit</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Penyusun</label>
                                    <select name="id_penyusun" class="form-control select2" style="width: 100%;">
                                        <option value="">Pilih Penyusun</option>
                                    </select>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Tahun Terbit</label>
                                            <input type="number" name="tahun_terbit" class="form-control" value="<?php echo date('Y'); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Stok Arsip</label>
                                            <input type="number" name="stok" class="form-control" value="1">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Cover / Gambar Arsip</label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="cover" name="cover">
                                        <label class="custom-file-label" for="cover">Pilih file</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Keterangan</label>
                            <textarea name="keterangan" class="form-control" rows="3" placeholder="Tambahkan deskripsi tambahan jika ada..."></textarea>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Data</button>
                        <a href="arsip.php" class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>

<?php include('../../includes/footer.php'); ?>

<!-- Plugins -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bs-custom-file-input/bs-custom-file-input.min.js"></script>

<script>
$(document).ready(function() {
    bsCustomFileInput.init();
    $('.select2').select2({ theme: 'bootstrap4' });

    // Load Dropdowns
    function loadOptions(action, selector, idKey, nameKey) {
        $.ajax({
            url: 'master/master_action.php',
            type: 'POST',
            data: { action: action },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    let options = '<option value="">Pilih Data</option>';
                    response.data.forEach(item => {
                        options += `<option value="${item[idKey]}">${item[nameKey]}</option>`;
                    });
                    $(selector).html(options);
                }
            }
        });
    }

    // This requires adding more "get_list" actions to master_action.php if not already there
    loadOptions('get_kategori_list', '#id_kategori', 'id_kategori', 'nama_kategori');
    loadOptions('get_penerbit_list', 'select[name="id_penerbit"]', 'id_penerbit', 'nama_penerbit');
    loadOptions('get_penyusun_list', 'select[name="id_penyusun"]', 'id_penyusun', 'nama_penyusun');

    $('#id_kategori').on('change', function() {
        let idKat = $(this).val();
        if (idKat) {
            $.ajax({
                url: 'master/master_action.php',
                type: 'POST',
                data: { action: 'get_rak_by_kategori', id_kategori: idKat },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        let options = '<option value="">Pilih Rak</option>';
                        response.data.forEach(item => {
                            options += `<option value="${item.id_rak}">${item.nama_rak}</option>`;
                        });
                        $('#id_rak').html(options);
                    }
                }
            });
        }
    });

    // Form Submit
    $('#formAddArsip').on('submit', function(e) {
        e.preventDefault();
        let formData = new FormData(this);
        formData.append('action', 'add_arsip');

        $.ajax({
            url: 'arsip_action.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text: response.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = 'arsip.php';
                    });
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            }
        });
    });
});
</script>
