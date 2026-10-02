<?php
session_start();
include('../../../koneksi.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

include('../../../includes/header.php');
include('../../../includes/sidebar.php');
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Transaksi Peminjaman Arsip</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/">Home</a></li>
                        <li class="breadcrumb-item active">Transaksi</li>
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
            </style>
            <div class="card card-<?php echo htmlspecialchars($themeColor); ?> card-outline">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                    <h3 class="card-title"><i class="fas fa-exchange-alt mr-1"></i> Daftar Peminjaman</h3>
                    <button class="btn btn-success btn-sm float-right" data-toggle="modal" data-target="#modalAddPeminjaman"><i class="fas fa-plus"></i> Tambah Peminjaman</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="peminjamanTable" class="table table-bordered table-hover table-sm w-100">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Peminjam</th>
                                    <th>Tgl Pinjam</th>
                                    <th>Est. Kembali</th>
                                    <th>Tgl Kembali</th>
                                    <th>Status</th>
                                    <th>Pilihan</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Modal Add Peminjaman -->
<div class="modal fade" id="modalAddPeminjaman" role="dialog" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                <h5 class="modal-title"><i class="fas fa-plus mr-1"></i> Tambah Peminjaman</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="formAddPeminjaman">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nama Peminjam <span class="text-danger">*</span></label>
                                <select name="nama_peminjam" id="select_peminjam" class="form-control select2" style="width: 100%;" required>
                                    <option value="">Pilih Peminjam</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tanggal Pinjam</label>
                                <input type="date" name="tgl_pinjam" class="form-control" value="<?php echo date('Y-m-d'); ?>" readonly style="background-color: #e9ecef;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Estimasi Kembali <span class="text-danger">*</span></label>
                                <input type="date" name="tgl_kembali_rencana" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-2">
                        <label>Pilih Arsip untuk Dipinjam <span class="text-danger">*</span></label>
                        <select id="select_arsip" class="form-control select2" style="width: 100%;">
                            <option value="">Cari dan Pilih Arsip...</option>
                        </select>
                        <small class="text-muted">Gunakan dropdown ini untuk menambahkan satu atau lebih arsip ke dalam daftar di bawah.</small>
                    </div>

                    <div class="table-responsive mt-3 mb-3" style="max-height: 250px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 4px;">
                        <table class="table table-sm table-striped table-hover mb-0" id="tableSelectedArsip">
                            <thead class="bg-light sticky-top">
                                <tr class="small text-center">
                                    <th width="15%">Kode</th>
                                    <th>Judul Arsip</th>
                                    <th width="15%">Stok</th>
                                    <th width="10%">Hapus</th>
                                </tr>
                            </thead>
                            <tbody id="listSelectedArsip">
                                <tr id="rowEmptyArsip">
                                    <td colspan="4" class="text-center text-muted py-3">Belum ada arsip yang dipilih.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="form-group">
                        <label>Keterangan</label>
                        <textarea name="keterangan" class="form-control" rows="3" placeholder="Tujuan peminjaman atau catatan lainnya..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Simpan Peminjaman</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Peminjaman -->
<div class="modal fade" id="modalEditPeminjaman" role="dialog" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                <h5 class="modal-title"><i class="fas fa-edit mr-1"></i> Edit Peminjaman</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="formEditPeminjaman">
                <input type="hidden" name="id_peminjaman" id="edit_id_peminjaman">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nama Peminjam <span class="text-danger">*</span></label>
                                <select name="nama_peminjam" id="edit_select_peminjam" class="form-control select2-edit" style="width: 100%;" required>
                                    <option value="">Pilih Peminjam</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tanggal Pinjam</label>
                                <input type="date" name="tgl_pinjam" id="edit_tgl_pinjam" class="form-control" readonly style="background-color: #e9ecef;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Estimasi Kembali <span class="text-danger">*</span></label>
                                <input type="date" name="tgl_kembali_rencana" id="edit_tgl_kembali_rencana" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-2">
                        <label>Tambah/Pilih Arsip <span class="text-danger">*</span></label>
                        <select id="edit_select_arsip" class="form-control select2-edit" style="width: 100%;">
                            <option value="">Cari dan Pilih Arsip...</option>
                        </select>
                        <small class="text-muted">Gunakan dropdown ini untuk menambah arsip baru ke peminjaman ini.</small>
                    </div>

                    <div class="table-responsive mt-3 mb-3" style="max-height: 250px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 4px;">
                        <table class="table table-sm table-striped table-hover mb-0">
                            <thead class="bg-light sticky-top">
                                <tr class="small text-center">
                                    <th width="15%">Kode</th>
                                    <th>Judul Arsip</th>
                                    <th width="15%">Stok</th>
                                    <th width="10%">Hapus</th>
                                </tr>
                            </thead>
                            <tbody id="edit_listSelectedArsip">
                                <!-- Loaded via JS -->
                            </tbody>
                        </table>
                    </div>
                    <div class="form-group">
                        <label>Keterangan</label>
                        <textarea name="keterangan" id="edit_keterangan" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Perbarui Peminjaman</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Detail Peminjaman -->
<div class="modal fade" id="modalDetailPeminjaman" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content text-dark">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                <h5 class="modal-title"><i class="fas fa-info-circle mr-1"></i> Detail Peminjaman</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="detail_content">
                <!-- Content will be loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<?php include('../../../includes/footer.php'); ?>

<!-- Plugins -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.min.js"></script>

<script>
$(document).ready(function() {
    $('.select2').select2({ 
        theme: 'bootstrap4',
        dropdownParent: $('#modalAddPeminjaman'),
        placeholder: 'Pilih data...'
    });

    $('.select2-edit').select2({ 
        theme: 'bootstrap4',
        dropdownParent: $('#modalEditPeminjaman'),
        placeholder: 'Pilih data...'
    });

    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000
    });

    var table = $('#peminjamanTable').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "peminjaman_serverside.php",
            "type": "POST"
        },
        "columns": [
            { "data": "no", "orderable": false },
            { "data": "nama_peminjam" },
            { "data": "tgl_pinjam" },
            { "data": "tgl_kembali_rencana" },
            { "data": "tgl_kembali" },
            { "data": "status" },
            { "data": "aksi", "orderable": false }
        ],
        "order": [[0, 'desc']],
        "responsive": true,
        "autoWidth": false,
    });

    // Load Employee List
    function loadEmployeeList(target = '#select_peminjam') {
        $.ajax({
            url: 'peminjaman_action.php',
            type: 'POST',
            data: { action: 'get_employee_list' },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    let options = '<option value="">Pilih Peminjam</option>';
                    response.data.forEach(item => {
                        options += `<option value="${item.id}">${item.text}</option>`;
                    });
                    $(target).html(options).trigger('change');
                }
            }
        });
    }

    let allArsipData = []; // Store full objects

    // Function to render dropdown based on current status
    function renderArsipDropdown(targetSelect = '#select_arsip', targetList = '#listSelectedArsip') {
        const selectedIds = [];
        // Generic finder for both modals
        const context = (targetSelect === '#edit_select_arsip') ? '#edit_listSelectedArsip' : '#listSelectedArsip';
        
        $(`${context} input[name="id_arsip[]"]`).each(function() {
            selectedIds.push($(this).val());
        });

        let options = '<option value="">Cari dan Pilih Arsip...</option>';
        allArsipData.forEach(item => {
            if (!selectedIds.includes(item.id_arsip.toString())) {
                options += `<option value="${item.id_arsip}">${item.judul} (Stok: ${item.stok})</option>`;
            }
        });
        $(targetSelect).html(options).trigger('change');
    }

    // Load Arsip List for Modal
    function loadArsipList(targetSelect = '#select_arsip') {
        $.ajax({
            url: 'peminjaman_action.php',
            type: 'POST',
            data: { action: 'get_arsip_list' },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    allArsipData = response.data;
                    renderArsipDropdown(targetSelect);
                }
            }
        });
    }

    // Handle Archive Selection (Add)
    $('#select_arsip').on('change', function() {
        const id = $(this).val();
        if (!id) return;

        const archive = allArsipData.find(a => a.id_arsip == id);
        if (archive) {
            $('#rowEmptyArsip').hide();
            const newRow = `
                <tr id="row-arsip-${archive.id_arsip}" class="small align-middle">
                    <td class="text-center py-2"><span class="badge badge-light border text-muted px-2">${archive.kode}</span></td>
                    <td class="py-2">${archive.judul}</td>
                    <td class="text-center font-weight-bold py-2">${archive.stok}</td>
                    <td class="text-center py-2">
                        <input type="hidden" name="id_arsip[]" value="${archive.id_arsip}">
                        <button type="button" class="btn btn-xs btn-outline-danger btn-remove-arsip" onclick="removeSelectedArsip('${archive.id_arsip}', '#select_arsip', '#listSelectedArsip')">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </td>
                </tr>
            `;
            $('#listSelectedArsip').append(newRow);
            renderArsipDropdown('#select_arsip'); 
        }
    });

    // Handle Archive Selection (Edit)
    $('#edit_select_arsip').on('change', function() {
        const id = $(this).val();
        if (!id) return;

        const archive = allArsipData.find(a => a.id_arsip == id);
        if (archive) {
            const newRow = `
                <tr id="edit-row-arsip-${archive.id_arsip}" class="small align-middle">
                    <td class="text-center py-2"><span class="badge badge-light border text-muted px-2">${archive.kode}</span></td>
                    <td class="py-2">${archive.judul}</td>
                    <td class="text-center font-weight-bold py-2">${archive.stok}</td>
                    <td class="text-center py-2">
                        <input type="hidden" name="id_arsip[]" value="${archive.id_arsip}">
                        <button type="button" class="btn btn-xs btn-outline-danger btn-remove-arsip" onclick="removeSelectedArsip('${archive.id_arsip}', '#edit_select_arsip', '#edit_listSelectedArsip')">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </td>
                </tr>
            `;
            $('#edit_listSelectedArsip').append(newRow);
            renderArsipDropdown('#edit_select_arsip'); 
        }
    });

    // Global function to remove row
    window.removeSelectedArsip = function(id, targetSelect, targetList) {
        if (targetList === '#edit_listSelectedArsip') {
            $(`#edit-row-arsip-${id}`).remove();
        } else {
            $(`#row-arsip-${id}`).remove();
            if ($('#listSelectedArsip tr').length <= 1) {
                $('#rowEmptyArsip').show();
            }
        }
        renderArsipDropdown(targetSelect, targetList); 
    };

    $('#modalAddPeminjaman').on('show.bs.modal', function() {
        $('#listSelectedArsip').find('tr:not(#rowEmptyArsip)').remove();
        $('#rowEmptyArsip').show();
        loadEmployeeList('#select_peminjam');
        loadArsipList('#select_arsip');
    });

    // Handle Open Edit Modal
    $(document).on('click', '.btn-edit-pinjam', function() {
        let id = $(this).data('id');
        $('#modalEditPeminjaman').modal('show');
        $('#edit_id_peminjaman').val(id);
        $('#edit_listSelectedArsip').empty();

        $.ajax({
            url: 'peminjaman_action.php',
            type: 'POST',
            data: { action: 'get_edit_data', id: id },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    let d = response.data;
                    
                    // 1. Employees
                    $.ajax({
                        url: 'peminjaman_action.php',
                        type: 'POST',
                        data: { action: 'get_employee_list' },
                        dataType: 'json',
                        success: function(empRes) {
                            if (empRes.status === 'success') {
                                let options = '<option value="">Pilih Peminjam</option>';
                                empRes.data.forEach(item => {
                                    options += `<option value="${item.id}" ${item.id == d.nama_peminjam ? 'selected' : ''}>${item.text}</option>`;
                                });
                                $('#edit_select_peminjam').html(options).trigger('change');
                            }
                        }
                    });

                    // 2. Info
                    $('#edit_tgl_pinjam').val(d.tgl_pinjam);
                    $('#edit_tgl_kembali_rencana').val(d.tgl_kembali_rencana);
                    $('#edit_keterangan').val(d.keterangan);

                    // 3. Archives
                    d.items.forEach(item => {
                        const row = `
                            <tr id="edit-row-arsip-${item.id_arsip}" class="small align-middle">
                                <td class="text-center py-2"><span class="badge badge-light border text-muted px-2">${item.kode_arsip}</span></td>
                                <td class="py-2">${item.judul_arsip}</td>
                                <td class="text-center font-weight-bold py-2">${item.stok}</td>
                                <td class="text-center py-2">
                                    <input type="hidden" name="id_arsip[]" value="${item.id_arsip}">
                                    <button type="button" class="btn btn-xs btn-outline-danger btn-remove-arsip" onclick="removeSelectedArsip('${item.id_arsip}', '#edit_select_arsip', '#edit_listSelectedArsip')">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                        $('#edit_listSelectedArsip').append(row);
                    });

                    // 4. Load Dropdown
                    loadArsipList('#edit_select_arsip');
                }
            }
        });
    });

    // Handle Edit Form Submit
    $('#formEditPeminjaman').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'peminjaman_action.php',
            type: 'POST',
            data: $(this).serialize() + '&action=edit_peminjaman',
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
                    $('#modalEditPeminjaman').modal('hide');
                    table.ajax.reload();
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            }
        });
    });

    // Handle Add
    $('#formAddPeminjaman').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'peminjaman_action.php',
            type: 'POST',
            data: $(this).serialize() + '&action=add_peminjaman',
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
                    $('#modalAddPeminjaman').modal('hide');
                    $('#formAddPeminjaman')[0].reset();
                    table.ajax.reload();
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function(xhr) {
                Swal.fire('Error', 'Terjadi kesalahan sistem: ' + xhr.responseText, 'error');
            }
        });
    });

    // Handle Return (Kembalikan)
    $(document).on('click', '.btn-kembali', function() {
        let id = $(this).data('id');
        Swal.fire({
            title: 'Kembalikan Arsip?',
            text: "Konfirmasi pengembalian arsip ini.",
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Ya, Sudah Kembali'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'peminjaman_action.php',
                    type: 'POST',
                    data: { action: 'kembalikan', id: id },
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
                            table.ajax.reload();
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    }
                });
            }
        });
    });

    // Handle Delete
    $(document).on('click', '.btn-delete-pinjam', function() {
        let id = $(this).data('id');
        Swal.fire({
            title: 'Hapus Data?',
            text: "Data peminjaman akan dihapus secara permanen!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Hapus'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'peminjaman_action.php',
                    type: 'POST',
                    data: { action: 'delete_peminjaman', id: id },
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
                            Swal.fire('Error', response.message, 'error');
                        }
                    }
                });
            }
        });
    });

    // Handle Detail
    $(document).on('click', '.btn-detail-pinjam', function() {
        let id = $(this).data('id');
        $('#modalDetailPeminjaman').modal('show');
        $('#detail_content').html('<div class="text-center py-4"><div class="spinner-border text-info" role="status"><span class="sr-only">Loading...</span></div></div>');
        
        $.ajax({
            url: 'peminjaman_action.php',
            type: 'POST',
            data: { action: 'get_peminjaman_detail', id: id },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    let d = response.data;
                    let badgeClass = (d.status === 'Kembali') ? 'success' : 'warning';
                    
                    let itemsHtml = '';
                    d.items.forEach((item, index) => {
                        itemsHtml += `
                            <tr>
                                <td class="text-center">${index + 1}</td>
                                <td>${item.kode_arsip}</td>
                                <td>${item.judul_arsip}</td>
                                <td>${item.nama_kategori || '-'}</td>
                                <td>${item.nama_rak || '-'}</td>
                            </tr>
                        `;
                    });

                    let html = `
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <h6 class="text-info border-bottom pb-2 mb-3"><i class="fas fa-user-tag mr-1"></i> Data Peminjam</h6>
                                <table class="table table-sm table-borderless">
                                    <tr><th width="30%">Nama</th><td>: <span class="font-weight-bold text-primary">${d.nama_peminjam}</span></td></tr>
                                    <tr><th>Tanggal Pinjam</th><td>: ${d.tgl_pinjam_fmt}</td></tr>
                                    <tr><th>Estimasi Kembali</th><td>: ${d.tgl_kembali_rencana_fmt}</td></tr>
                                    <tr><th>Tanggal Kembali</th><td>: ${d.tgl_kembali_fmt}</td></tr>
                                    <tr>
                                        <th>Waktu Pengembalian</th>
                                        <td>: 
                                            ${(() => {
                                                if (d.status === 'Kembali') {
                                                    return d.late_days > 0 ? '<span class="text-danger font-weight-bold">' + d.late_days + ' Hari</span>' : '<span class="text-success">Tepat Waktu</span>';
                                                } else {
                                                    // Status Dipinjam, check if late against today
                                                    const today = new Date();
                                                    today.setHours(0,0,0,0);
                                                    const rencana = d.tgl_kembali_rencana ? new Date(d.tgl_kembali_rencana) : null;
                                                    if (rencana) {
                                                        rencana.setHours(0,0,0,0);
                                                        if (today > rencana) {
                                                            const diffTime = Math.abs(today - rencana);
                                                            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                                                            return `<span class="text-danger font-weight-bold">Telat ${diffDays} Hari</span>`;
                                                        }
                                                    }
                                                    return '-';
                                                }
                                            })()}
                                        </td>
                                    </tr>
                                    <tr><th>Status</th><td>: <span class="badge badge-${badgeClass} py-1 px-2">${d.status}</span></td></tr>
                                </table>
                            </div>
                        </div>

                        <h6 class="text-info border-bottom pb-2 mb-3"><i class="fas fa-archive mr-1"></i> Daftar Arsip yang Dipinjam</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-sm">
                                <thead class="bg-light">
                                    <tr class="text-center small font-weight-bold">
                                        <th width="5%">No</th>
                                        <th width="15%">Kode</th>
                                        <th>Judul Arsip</th>
                                        <th width="15%">Kategori</th>
                                        <th width="10%">Rak</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${itemsHtml}
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3">
                            <h6 class="text-info border-bottom pb-2 mb-2"><i class="fas fa-comment-dots mr-1"></i> Catatan</h6>
                            <div class="bg-light p-3 rounded border text-justify">
                                ${d.keterangan || '<span class="text-muted font-italic">Tidak ada catatan khusus.</span>'}
                            </div>
                        </div>

                        <div class="mt-4 pt-2 text-right small text-muted">
                            <p class="mb-0">Petugas: <strong>${d.CreatedBy}</strong></p>
                        </div>
                    `;
                    $('#detail_content').html(html);
                } else {
                    $('#detail_content').html('<div class="alert alert-danger">' + response.message + '</div>');
                }
            },
            error: function() {
                $('#detail_content').html('<div class="alert alert-danger">Gagal memuat data dari server.</div>');
            }
        });
    });
});
</script>
