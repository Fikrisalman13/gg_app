<?php
session_start();

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
?>

<div class="wrapper">
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <h1>Master Transaksi Closingan</h1>
                <a href="list_form.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-primary">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title"><i class="fas fa-database mr-1"></i> Data Procurement & Sales</h3>
                    <button type="button" class="btn btn-success btn-sm float-right" id="btnTambah">
                        <i class="fas fa-plus"></i> Tambah Data
                    </button>
                </div>
                <div class="card-body table-responsive">
                    <table id="masterTable" class="table table-hover table-sm">
                        <thead class="thead-light">
                            <tr>
                                <th style="width:60px;">No</th>
                                <th style="width:170px;">Jenis</th>
                                <th>Transaksi</th>
                                <th style="width:120px;">Status</th>
                                <th style="width:170px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>
</div>

<div class="modal fade" id="modalFormMaster" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="formMasterTransaksi">
                <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h5 class="modal-title" id="modalMasterTitle">Tambah Data Transaksi</h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="masterId" value="0">
                    <div class="form-group">
                        <label>Jenis <span class="text-danger">*</span></label>
                        <select class="form-control" name="jenis" id="masterJenis" required>
                            <option value="">-- Pilih Jenis --</option>
                            <option value="Procurement">Procurement</option>
                            <option value="Sales">Sales</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nama Transaksi <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_transaksi" id="masterNama" required maxlength="255">
                    </div>
                    <div class="form-group mb-0">
                        <label>Status</label>
                        <select class="form-control" name="is_active" id="masterActive">
                            <option value="1">Aktif</option>
                            <option value="0">Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>
<script>
(function initMasterTransaksiPage() {
    if (!window.jQuery || !window.jQuery.fn) {
        setTimeout(initMasterTransaksiPage, 100);
        return;
    }
    var $ = window.jQuery;
    $(function() {
    var dataMaster = [];
    var useDataTable = typeof $.fn.DataTable === 'function';
    var table = null;

    function statusBadge(active) {
        return active == 1
            ? "<span class='badge badge-success'>Aktif</span>"
            : "<span class='badge badge-secondary'>Nonaktif</span>";
    }

    function renderRowsFallback(rows) {
        var html = '';
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            html += "<tr>" +
                "<td>" + (i + 1) + "</td>" +
                "<td>" + (row.jenis || '') + "</td>" +
                "<td>" + (row.nama_transaksi || '') + "</td>" +
                "<td>" + statusBadge(row.is_active) + "</td>" +
                "<td>" +
                    "<button class='btn btn-warning btn-sm btn-edit mr-1' data-id='" + row.id + "'><i class='fas fa-edit'></i></button>" +
                    "<button class='btn btn-danger btn-sm btn-delete' data-id='" + row.id + "'><i class='fas fa-trash'></i></button>" +
                "</td>" +
            "</tr>";
        }
        $('#masterTable tbody').html(html);
    }

    if (useDataTable) {
        table = $('#masterTable').DataTable({
            data: [],
            columns: [
                { data: null, render: function(data, type, row, meta){ return meta.row + 1; } },
                { data: 'jenis' },
                { data: 'nama_transaksi' },
                { data: 'is_active', render: function(v){ return statusBadge(v); } },
                {
                    data: null,
                    orderable: false,
                    render: function(row){
                        return "<button class='btn btn-warning btn-sm btn-edit mr-1' data-id='" + row.id + "'><i class='fas fa-edit'></i></button>" +
                               "<button class='btn btn-danger btn-sm btn-delete' data-id='" + row.id + "'><i class='fas fa-trash'></i></button>";
                    }
                }
            ],
            order: [[1, 'asc'], [2, 'asc']]
        });
    }

    function loadData() {
        $.getJSON('master_transaksi_api.php', { action: 'list' }, function(resp){
            if (!resp || !resp.success) {
                Swal.fire('Error', (resp && resp.message) ? resp.message : 'Gagal memuat data', 'error');
                return;
            }
            dataMaster = resp.data || [];
            if (useDataTable && table) {
                table.clear().rows.add(dataMaster).draw();
            } else {
                renderRowsFallback(dataMaster);
            }
        }).fail(function(){
            Swal.fire('Error', 'Gagal mengambil data dari server', 'error');
        });
    }

    function resetForm() {
        $('#masterId').val(0);
        $('#masterJenis').val('');
        $('#masterNama').val('');
        $('#masterActive').val('1');
    }

    $('#btnTambah').on('click', function(){
        resetForm();
        $('#modalMasterTitle').text('Tambah Data Transaksi');
        $('#modalFormMaster').modal('show');
    });

    $(document).on('click', '.btn-edit', function(){
        var id = parseInt($(this).data('id'), 10);
        var row = dataMaster.find(function(item){ return parseInt(item.id, 10) === id; });
        if (!row) return;
        $('#masterId').val(row.id);
        $('#masterJenis').val(row.jenis);
        $('#masterNama').val(row.nama_transaksi);
        $('#masterActive').val(String(row.is_active));
        $('#modalMasterTitle').text('Edit Data Transaksi');
        $('#modalFormMaster').modal('show');
    });

    $(document).on('click', '.btn-delete', function(){
        var id = parseInt($(this).data('id'), 10);
        Swal.fire({
            icon: 'warning',
            title: 'Hapus data?',
            text: 'Data transaksi akan dihapus permanen.',
            showCancelButton: true,
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then(function(result){
            if (!result.isConfirmed) return;
            $.post('master_transaksi_api.php?action=delete', { id: id }, function(resp){
                if (resp && resp.success) {
                    Swal.fire('Berhasil', resp.message || 'Data berhasil dihapus', 'success');
                    loadData();
                } else {
                    Swal.fire('Gagal', (resp && resp.message) ? resp.message : 'Gagal menghapus data', 'error');
                }
            }, 'json').fail(function(){
                Swal.fire('Error', 'Gagal memproses permintaan hapus', 'error');
            });
        });
    });

    $('#formMasterTransaksi').on('submit', function(e){
        e.preventDefault();
        $.post('master_transaksi_api.php?action=save', $(this).serialize(), function(resp){
            if (resp && resp.success) {
                $('#modalFormMaster').modal('hide');
                Swal.fire('Berhasil', resp.message || 'Data berhasil disimpan', 'success');
                loadData();
            } else {
                Swal.fire('Gagal', (resp && resp.message) ? resp.message : 'Gagal menyimpan data', 'error');
            }
        }, 'json').fail(function(){
            Swal.fire('Error', 'Gagal memproses penyimpanan', 'error');
        });
    });

    loadData();
    });
})();
</script>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
