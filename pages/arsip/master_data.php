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
                    <h1>Master Data Arsip</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/">Home</a></li>
                        <li class="breadcrumb-item active">Master Data</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <style>
                .table { background-color: #ffffff !important; }
                .table th, .table td { background-color: #ffffff !important; vertical-align: middle !important; }
                
                /* Modern Tab Navigation Styling */
                .nav-tabs {
                    border-bottom: none;
                    margin-bottom: 0;
                    overflow: hidden;
                }
                
                .nav-tabs .nav-item {
                    margin-bottom: 0;
                }
                
                .nav-tabs .nav-link {
                    border: none;
                    border-top-left-radius: 10px;
                    border-top-right-radius: 10px;
                    padding: 14px 24px;
                    margin-right: 2px;
                    color: rgba(255, 255, 255, 0.85);
                    background-color: transparent;
                    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                    font-weight: 500;
                    position: relative;
                    overflow: hidden;
                    backface-visibility: hidden;
                    -webkit-font-smoothing: antialiased;
                    cursor: pointer;
                }
                
                .nav-tabs .nav-link::after {
                    content: '';
                    position: absolute;
                    bottom: 0;
                    left: 50%;
                    width: 0;
                    height: 3px;
                    background-color: rgba(255, 255, 255, 0.5);
                    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                    transform: translateX(-50%);
                    border-radius: 2px;
                }
                
                .nav-tabs .nav-link:hover {
                    color: #ffffff !important;
                    background-color: rgba(255, 255, 255, 0.18);
                    transform: translateY(-4px) scale(1.02);
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                }
                
                .nav-tabs .nav-link:hover::after {
                    width: 70%;
                    background-color: #ffffff;
                }

                .nav-tabs .nav-link:active {
                    transform: translateY(0) scale(0.96);
                    transition: all 0.1s;
                }

                .nav-tabs .nav-link:hover i {
                    text-shadow: 0 0 8px rgba(255, 255, 255, 0.8);
                    transform: rotate(-5deg) scale(1.1);
                    transition: all 0.3s ease;
                }
                
                .nav-tabs .nav-link.active {
                    background-color: #ffffff;
                    color: var(--theme-color) !important;
                    border: none;
                    font-weight: 600;
                    position: relative;
                    transform: translateY(-1px);
                    box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.1);
                }
                
                .nav-tabs .nav-link.active::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 3px;
                    background-color: var(--theme-color);
                    border-top-left-radius: 10px;
                    border-top-right-radius: 10px;
                }
                
                .nav-tabs .nav-link.active::after {
                    display: none;
                }
                
                .nav-tabs .nav-link i {
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                    display: inline-block;
                }
                
                .nav-tabs .nav-link:hover i {
                    transform: scale(1.15) translateY(-1px);
                    color: #ffffff !important;
                }
                
                .nav-tabs .nav-link.active i {
                    color: var(--theme-color) !important;
                    transform: scale(1.1);
                }
            </style>
            <div class="card card-<?php echo htmlspecialchars($themeColor); ?> card-outline card-tabs">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white p-0 pt-1 border-bottom-0">
                    <ul class="nav nav-tabs" id="masterDataTab" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="kategori-tab" data-toggle="pill" href="#tab-kategori" role="tab" aria-controls="tab-kategori" aria-selected="true"><i class="fas fa-tags mr-2"></i> Kategori</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="rak-tab" data-toggle="pill" href="#tab-rak" role="tab" aria-controls="tab-rak" aria-selected="false"><i class="fas fa-columns mr-2"></i> Rak</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="penerbit-tab" data-toggle="pill" href="#tab-penerbit" role="tab" aria-controls="tab-penerbit" aria-selected="false"><i class="fas fa-building mr-2"></i> Penerbit</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="instansi-tab" data-toggle="pill" href="#tab-instansi" role="tab" aria-controls="tab-instansi" aria-selected="false"><i class="fas fa-university mr-2"></i> Instansi</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="penyusun-tab" data-toggle="pill" href="#tab-penyusun" role="tab" aria-controls="tab-penyusun" aria-selected="false"><i class="fas fa-user-edit mr-2"></i> Penyusun</a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="masterDataTabContent">
                        <!-- Tab Kategori -->
                        <div class="tab-pane fade show active" id="tab-kategori" role="tabpanel" aria-labelledby="kategori-tab">
                            <div class="d-flex justify-content-between mb-3 align-items-center">
                                <h5 class="mb-0 text-<?php echo htmlspecialchars($themeColor); ?> font-weight-bold">Daftar Kategori</h5>
                                <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#modalAddKategori"><i class="fas fa-plus"></i> Tambah Kategori</button>
                            </div>
                            <div class="table-responsive">
                                <table id="kategoriTable" class="table table-bordered table-hover table-sm w-100">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Kategori</th>
                                            <th>Pilihan</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>

                        <!-- Tab Rak -->
                        <div class="tab-pane fade" id="tab-rak" role="tabpanel" aria-labelledby="rak-tab">
                            <div class="d-flex justify-content-between mb-3 align-items-center">
                                <h5 class="mb-0 text-<?php echo htmlspecialchars($themeColor); ?> font-weight-bold">Daftar Rak</h5>
                                <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#modalAddRak"><i class="fas fa-plus"></i> Tambah Rak</button>
                            </div>
                            <div class="table-responsive">
                                <table id="rakTable" class="table table-bordered table-hover table-sm w-100">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Rak</th>
                                            <th>Kategori</th>
                                            <th>Pilihan</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>

                        <!-- Tab Penerbit -->
                        <div class="tab-pane fade" id="tab-penerbit" role="tabpanel" aria-labelledby="penerbit-tab">
                            <div class="d-flex justify-content-between mb-3 align-items-center">
                                <h5 class="mb-0 text-<?php echo htmlspecialchars($themeColor); ?> font-weight-bold">Daftar Penerbit</h5>
                                <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#modalAddPenerbit"><i class="fas fa-plus"></i> Tambah Penerbit</button>
                            </div>
                            <div class="table-responsive">
                                <table id="penerbitTable" class="table table-bordered table-hover table-sm w-100">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Nama Penerbit</th>
                                            <th>Keterangan</th>
                                            <th>Pilihan</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>

                        <!-- Tab Instansi -->
                        <div class="tab-pane fade" id="tab-instansi" role="tabpanel" aria-labelledby="instansi-tab">
                            <div class="d-flex justify-content-between mb-3 align-items-center">
                                <h5 class="mb-0 text-<?php echo htmlspecialchars($themeColor); ?> font-weight-bold">Daftar Instansi</h5>
                                <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#modalAddInstansi"><i class="fas fa-plus"></i> Tambah Instansi</button>
                            </div>
                            <div class="table-responsive">
                                <table id="instansiTable" class="table table-bordered table-hover table-sm w-100">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Nama Instansi</th>
                                            <th>Kota</th>
                                            <th>Pilihan</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>

                        <!-- Tab Penyusun -->
                        <div class="tab-pane fade" id="tab-penyusun" role="tabpanel" aria-labelledby="penyusun-tab">
                            <div class="d-flex justify-content-between mb-3 align-items-center">
                                <h5 class="mb-0 text-<?php echo htmlspecialchars($themeColor); ?> font-weight-bold">Daftar Penyusun</h5>
                                <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#modalAddPenyusun"><i class="fas fa-plus"></i> Tambah Penyusun</button>
                            </div>
                            <div class="table-responsive">
                                <table id="penyusunTable" class="table table-bordered table-hover table-sm w-100">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Nama Penyusun</th>
                                            <th>Keterangan</th>
                                            <th>Pilihan</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- All Modals (Add Modals) -->
<!-- Modal Kategori -->
<div class="modal fade" id="modalAddKategori" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                <h5 class="modal-title">Tambah Kategori</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="formAddKategori">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Kategori</label>
                        <input type="text" name="nama_kategori" class="form-control" placeholder="Contoh: Dokumen Teknis" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Rak -->
<div class="modal fade" id="modalAddRak" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                <h5 class="modal-title">Tambah Rak</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="formAddRak">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Rak</label>
                        <input type="text" name="nama_rak" class="form-control" placeholder="Contoh: Rak A-01" required>
                    </div>
                    <div class="form-group">
                        <label>Kategori</label>
                        <select name="id_kategori" class="form-control" required>
                            <option value="">Pilih Kategori</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Penerbit -->
<div class="modal fade" id="modalAddPenerbit" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                <h5 class="modal-title">Tambah Penerbit</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="formAddPenerbit">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Penerbit</label>
                        <input type="text" name="nama_penerbit" class="form-control" placeholder="Contoh: PT. Sumber Jaya" required>
                    </div>
                    <div class="form-group">
                        <label>Provinsi / Keterangan</label>
                        <input type="text" name="keterangan" class="form-control" placeholder="Contoh: Jawa Barat">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Instansi -->
<div class="modal fade" id="modalAddInstansi" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                <h5 class="modal-title">Tambah Instansi</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="formAddInstansi">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Instansi</label>
                        <input type="text" name="nama_instansi" class="form-control" placeholder="Contoh: Dinas Arsip" required>
                    </div>
                    <div class="form-group">
                        <label>Kota / Lokasi</label>
                        <input type="text" name="kota" class="form-control" placeholder="Contoh: Bandung">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Penyusun -->
<div class="modal fade" id="modalAddPenyusun" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                <h5 class="modal-title">Tambah Penyusun</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="formAddPenyusun">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Penyusun</label>
                        <input type="text" name="nama_penyusun" class="form-control" placeholder="Contoh: Andi Wijaya" required>
                    </div>
                    <div class="form-group">
                        <label>Keterangan</label>
                        <input type="text" name="keterangan" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- All Modals (Edit Modals) -->
<!-- Modal Edit Kategori -->
<div class="modal fade" id="modalEditKategori" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                <h5 class="modal-title font-weight-bold">Edit Kategori</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="formEditKategori">
                <input type="hidden" name="action" value="edit_kategori">
                <input type="hidden" name="id_kategori" id="edit_id_kategori">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Kategori</label>
                        <input type="text" name="nama_kategori" id="edit_nama_kategori" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success font-weight-bold">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Rak -->
<div class="modal fade" id="modalEditRak" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                <h5 class="modal-title font-weight-bold">Edit Rak</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="formEditRak">
                <input type="hidden" name="action" value="edit_rak">
                <input type="hidden" name="id_rak" id="edit_id_rak">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Rak</label>
                        <input type="text" name="nama_rak" id="edit_nama_rak" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Kategori</label>
                        <select name="id_kategori" id="edit_id_kategori_rak" class="form-control" required>
                            <option value="">Pilih Kategori</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success font-weight-bold">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Penerbit -->
<div class="modal fade" id="modalEditPenerbit" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                <h5 class="modal-title font-weight-bold">Edit Penerbit</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="formEditPenerbit">
                <input type="hidden" name="action" value="edit_penerbit">
                <input type="hidden" name="id_penerbit" id="edit_id_penerbit">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Penerbit</label>
                        <input type="text" name="nama_penerbit" id="edit_nama_penerbit" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Provinsi / Keterangan</label>
                        <input type="text" name="keterangan" id="edit_ket_penerbit" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success font-weight-bold">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Instansi -->
<div class="modal fade" id="modalEditInstansi" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                <h5 class="modal-title font-weight-bold">Edit Instansi</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="formEditInstansi">
                <input type="hidden" name="action" value="edit_instansi">
                <input type="hidden" name="id_instansi" id="edit_id_instansi">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Instansi</label>
                        <input type="text" name="nama_instansi" id="edit_nama_instansi" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Kota / Lokasi</label>
                        <input type="text" name="kota" id="edit_kota_instansi" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success font-weight-bold">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Penyusun -->
<div class="modal fade" id="modalEditPenyusun" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                <h5 class="modal-title font-weight-bold">Edit Penyusun</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="formEditPenyusun">
                <input type="hidden" name="action" value="edit_penyusun">
                <input type="hidden" name="id_penyusun" id="edit_id_penyusun">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Penyusun</label>
                        <input type="text" name="nama_penyusun" id="edit_nama_penyusun" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Keterangan</label>
                        <input type="text" name="keterangan" id="edit_ket_penyusun" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success font-weight-bold">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include('../../includes/footer.php'); ?>

<!-- Plugins -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.min.js"></script>

<script>
$(document).ready(function() {
    // Set theme color CSS variable
    const themeColors = {
        'primary': '#007bff',
        'success': '#28a745',
        'info': '#17a2b8',
        'warning': '#ffc107',
        'danger': '#dc3545',
        'secondary': '#6c757d',
        'dark': '#343a40',
        'light': '#f8f9fa'
    };
    const themeColor = '<?php echo htmlspecialchars($themeColor); ?>';
    const themeColorValue = themeColors[themeColor] || themeColors['primary'];
    document.documentElement.style.setProperty('--theme-color', themeColorValue);
    
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000
    });

    // Load Kategori options for Rak modals
    function loadKategoriOptions() {
        $.ajax({
            url: 'master/master_action.php',
            type: 'POST',
            data: { action: 'get_kategori_list' }, // Needs to be added to action.php
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    let options = '<option value="">Pilih Kategori</option>';
                    response.data.forEach(item => {
                        options += `<option value="${item.id_kategori}">${item.nama_kategori}</option>`;
                    });
                    $('select[name="id_kategori"]').html(options);
                }
            }
        });
    }

    // Shared DataTable initialization logic
    function initDataTable(selector, url, columns) {
        return $(selector).DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": {
                "url": url,
                "type": "POST"
            },
            "columns": columns,
            "responsive": true,
            "autoWidth": false
        });
    }

    // Initialize each table
    let tables = {};
    tables.kategori = initDataTable('#kategoriTable', 'master/kategori_serverside.php', [
        { "data": "no", "orderable": false },
        { "data": "nama_kategori" },
        { "data": "aksi", "orderable": false }
    ]);

    // Delay initialization of hidden tabs for performance
    $('a[data-toggle="pill"]').on('shown.bs.tab', function (e) {
        var target = $(e.target).attr("href");
        if (target == '#tab-rak' && !$.fn.DataTable.isDataTable('#rakTable')) {
            loadKategoriOptions();
            tables.rak = initDataTable('#rakTable', 'master/rak_serverside.php', [
                { "data": "no", "orderable": false },
                { "data": "nama_rak" },
                { "data": "nama_kategori" },
                { "data": "aksi", "orderable": false }
            ]);
        } else if (target == '#tab-penerbit' && !$.fn.DataTable.isDataTable('#penerbitTable')) {
            tables.penerbit = initDataTable('#penerbitTable', 'master/penerbit_serverside.php', [
                { "data": "no", "orderable": false },
                { "data": "nama_penerbit" },
                { "data": "keterangan" },
                { "data": "aksi", "orderable": false }
            ]);
        } else if (target == '#tab-instansi' && !$.fn.DataTable.isDataTable('#instansiTable')) {
            tables.instansi = initDataTable('#instansiTable', 'master/instansi_serverside.php', [
                { "data": "no", "orderable": false },
                { "data": "nama_instansi" },
                { "data": "kota" },
                { "data": "aksi", "orderable": false }
            ]);
        } else if (target == '#tab-penyusun' && !$.fn.DataTable.isDataTable('#penyusunTable')) {
            tables.penyusun = initDataTable('#penyusunTable', 'master/penyusun_serverside.php', [
                { "data": "no", "orderable": false },
                { "data": "nama_penyusun" },
                { "data": "keterangan" },
                { "data": "aksi", "orderable": false }
            ]);
        }
        $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
    });

    // Form Submissions (Add)
    function handleFormSubmit(formId, actionName, table) {
        $(formId).on('submit', function(e) {
            e.preventDefault();
            let formData = $(this).serialize() + '&action=' + actionName;
            $.ajax({
                url: 'master/master_action.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: response.message,
                            showConfirmButton: false,
                            timer: 1500
                        });
                        $(formId).closest('.modal').modal('hide');
                        $(formId)[0].reset();
                        table.ajax.reload();
                        if (actionName === 'add_kategori') loadKategoriOptions();
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                }
            });
        });
    }

    handleFormSubmit('#formAddKategori', 'add_kategori', tables.kategori);
    
    // We need to wait for other tables to be initialized when their tab is clicked
    // So we'll attach handlers in a slightly different way or just check if table exists
    $('#formAddRak').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'master/master_action.php',
            type: 'POST',
            data: $(this).serialize() + '&action=add_rak',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text: response.message,
                        showConfirmButton: false,
                        timer: 1500
                    });
                    $('#modalAddRak').modal('hide');
                    $('#formAddRak')[0].reset();
                    tables.rak.ajax.reload();
                } else { Swal.fire('Error', response.message, 'error'); }
            }
        });
    });

    $('#formAddPenerbit').on('submit', function(e) { e.preventDefault();
        $.ajax({ url: 'master/master_action.php', type: 'POST', data: $(this).serialize() + '&action=add_penerbit', dataType: 'json',
            success: function(response) { if (response.status === 'success') { 
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: response.message,
                    showConfirmButton: false,
                    timer: 1500
                });
                $('#modalAddPenerbit').modal('hide'); $('#formAddPenerbit')[0].reset(); tables.penerbit.ajax.reload(); } else { Swal.fire('Error', response.message, 'error'); } }
        });
    });

    $('#formAddInstansi').on('submit', function(e) { e.preventDefault();
        $.ajax({ url: 'master/master_action.php', type: 'POST', data: $(this).serialize() + '&action=add_instansi', dataType: 'json',
            success: function(response) { if (response.status === 'success') { 
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: response.message,
                    showConfirmButton: false,
                    timer: 1500
                });
                $('#modalAddInstansi').modal('hide'); $('#formAddInstansi')[0].reset(); tables.instansi.ajax.reload(); } else { Swal.fire('Error', response.message, 'error'); } }
        });
    });

    $('#formAddPenyusun').on('submit', function(e) { e.preventDefault();
        $.ajax({ url: 'master/master_action.php', type: 'POST', data: $(this).serialize() + '&action=add_penyusun', dataType: 'json',
            success: function(response) { if (response.status === 'success') { Toast.fire({ icon: 'success', title: response.message }); $('#modalAddPenyusun').modal('hide'); $('#formAddPenyusun')[0].reset(); tables.penyusun.ajax.reload(); } else { Swal.fire('Error', response.message, 'error'); } }
        });
    });

    // Patterns for other DeleteButtons...
    function setupDeleteHandler(btnClass, actionName, tableKey, title, extraCallback) {
        $(document).on('click', '.' + btnClass, function() {
            let id = $(this).data('id');
            Swal.fire({ 
                title: title, 
                text: "Data yang dihapus tidak dapat dikembalikan!", 
                icon: 'warning', 
                showCancelButton: true, 
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus!' 
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({ 
                        url: 'master/master_action.php', 
                        type: 'POST', 
                        data: { action: actionName, id: id }, 
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
                                tables[tableKey].ajax.reload(); 
                                if (extraCallback) extraCallback();
                            } else { 
                                Swal.fire('Gagal', response.message, 'error'); 
                            } 
                        }
                    });
                }
            });
        });
    }

    setupDeleteHandler('btn-delete-kategori', 'delete_kategori', 'kategori', 'Hapus Kategori?', loadKategoriOptions);
    setupDeleteHandler('btn-delete-rak', 'delete_rak', 'rak', 'Hapus Rak?');
    setupDeleteHandler('btn-delete-penerbit', 'delete_penerbit', 'penerbit', 'Hapus Penerbit?');
    setupDeleteHandler('btn-delete-instansi', 'delete_instansi', 'instansi', 'Hapus Instansi?');
    setupDeleteHandler('btn-delete-penyusun', 'delete_penyusun', 'penyusun', 'Hapus Penyusun?');

    // Edit Handlers (Opening Modals)
    $(document).on('click', '.btn-edit-kategori', function() {
        $('#edit_id_kategori').val($(this).data('id'));
        $('#edit_nama_kategori').val($(this).data('nama'));
        $('#modalEditKategori').modal('show');
    });

    $('#formEditKategori').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'master/master_action.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text: response.message,
                        showConfirmButton: false,
                        timer: 1500
                    });
                    $('#modalEditKategori').modal('hide');
                    tables.kategori.ajax.reload();
                    loadKategoriOptions();
                } else { Swal.fire('Error', response.message, 'error'); }
            }
        });
    });

    $(document).on('click', '.btn-edit-rak', function() {
        $('#edit_id_rak').val($(this).data('id'));
        $('#edit_nama_rak').val($(this).data('nama'));
        $('#edit_id_kategori_rak').val($(this).data('kategori'));
        $('#modalEditRak').modal('show');
    });
    
    $('#formEditRak').on('submit', function(e) {
        e.preventDefault();
        $.ajax({ url: 'master/master_action.php', type: 'POST', data: $(this).serialize(), dataType: 'json',
            success: function(response) { if (response.status === 'success') { Toast.fire({ icon: 'success', title: response.message }); $('#modalEditRak').modal('hide'); tables.rak.ajax.reload(); } else { Swal.fire('Error', response.message, 'error'); } }
        });
    });

    $(document).on('click', '.btn-edit-penerbit', function() {
        $('#edit_id_penerbit').val($(this).data('id'));
        $('#edit_nama_penerbit').val($(this).data('nama'));
        $('#edit_ket_penerbit').val($(this).data('ket'));
        $('#modalEditPenerbit').modal('show');
    });
    $('#formEditPenerbit').on('submit', function(e) { e.preventDefault();
        $.ajax({ url: 'master/master_action.php', type: 'POST', data: $(this).serialize(), dataType: 'json',
            success: function(response) { if (response.status === 'success') { 
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: response.message,
                    showConfirmButton: false,
                    timer: 1500
                });
                $('#modalEditPenerbit').modal('hide'); tables.penerbit.ajax.reload(); } else { Swal.fire('Error', response.message, 'error'); } }
        });
    });

    $(document).on('click', '.btn-edit-instansi', function() {
        $('#edit_id_instansi').val($(this).data('id'));
        $('#edit_nama_instansi').val($(this).data('nama'));
        $('#edit_kota_instansi').val($(this).data('kota'));
        $('#modalEditInstansi').modal('show');
    });
    $('#formEditInstansi').on('submit', function(e) { e.preventDefault();
        $.ajax({ url: 'master/master_action.php', type: 'POST', data: $(this).serialize(), dataType: 'json',
            success: function(response) { if (response.status === 'success') { 
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: response.message,
                    showConfirmButton: false,
                    timer: 1500
                });
                $('#modalEditInstansi').modal('hide'); tables.instansi.ajax.reload(); } else { Swal.fire('Error', response.message, 'error'); } }
        });
    });

    $(document).on('click', '.btn-edit-penyusun', function() {
        $('#edit_id_penyusun').val($(this).data('id'));
        $('#edit_nama_penyusun').val($(this).data('nama'));
        $('#edit_ket_penyusun').val($(this).data('ket'));
        $('#modalEditPenyusun').modal('show');
    });
    $('#formEditPenyusun').on('submit', function(e) { e.preventDefault();
        $.ajax({ url: 'master/master_action.php', type: 'POST', data: $(this).serialize(), dataType: 'json',
            success: function(response) { if (response.status === 'success') { 
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: response.message,
                    showConfirmButton: false,
                    timer: 1500
                });
                $('#modalEditPenyusun').modal('hide'); tables.penyusun.ajax.reload(); } else { Swal.fire('Error', response.message, 'error'); } }
        });
    });

});
</script>
