<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
$menuId = 222; // Ganti dengan MenuId IPAB di database
$permissions = getPermissions($conn, $_SESSION['GroupId'], $menuId);
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: /gg_app/index.php');
    exit;
}

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<div class="wrapper">
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1>Pengecekan Kualitas Air IPAB</h1>
        </div>
    </section>
    <section class="content">
        <div class="container-fluid">
            <div class="card card-primary">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title"><i class="fas fa-list mr-1"></i> List Pengecekan Kualitas Air</h3>
                    <?php if (!empty($permissions['CanAdd']) && $permissions['CanAdd'] == 1): ?>
                        <a href="#" class="btn btn-success btn-sm float-right" id="btnTambahData">
                            <i class="fas fa-plus"></i> Tambah Data
                        </a>
                    <?php endif; ?>
                </div>
                                <div class="card-body table-responsive">
                                    <div class="row mb-3">
                                        <div class="col-md-3">
                                            <label for="filterTanggal">Filter Tanggal</label>
                                            <input type="date" id="filterTanggal" name="filterTanggal" class="form-control" autocomplete="off">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="filterParameter">Filter Parameter</label>
                                            <select id="filterParameter" class="form-control">
                                                <option value="">Semua Parameter</option>
                                                <option value="pH">pH</option>
                                                <option value="DH">DH</option>
                                                <option value="Turbidity">Turbidity</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end">
                                            <button type="button" class="btn btn-secondary btn-sm" id="btnResetFilter">
                                                <i class="fas fa-undo"></i> Reset Filter
                                            </button>
                                        </div>
                                        
                                    </div>
                                    <table id="ipabTable" class="table table-hover table-sm nowrap text-center" style="width:100%">
                                        <thead class="thead-light text-center">
                                            <tr>
                                                <th>No</th>
                                                <th>Tanggal</th>
                                                <th>Pengecek</th>
                                                <th>Sampel Air</th>
                                                <th>Parameter</th>
                                                <th>Created By</th>
                                                <th>Created At</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Data loaded via AJAX -->
                                        </tbody>
                                    </table>
                                </div>
                        </div>
                </div>
        </section>
</div>

<!-- Modal Detail IPAB -->
<div class="modal fade" id="modalDetailIpab" tabindex="-1" role="dialog" aria-labelledby="modalDetailIpabLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title" id="modalDetailIpabLabel">Detail</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="ipabDetailBody">
                <div class="text-center"><span class="spinner-border"></span> Memuat data...</div>
            </div>
        </div>
    </div>
</div>
            </div>
        </div>
    </section>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
</div>

<!-- DataTables & SweetAlert -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
$(document).ready(function() {
    var table = $('#ipabTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'ipab_serverside.php',
            type: 'POST',
            data: function(d) {
                d.filterTanggal = $('#filterTanggal').val();
                d.filterParameter = $('#filterParameter').val();
                d.filterShift = $('#filterShift').val();
            },
            error: function(xhr, error, thrown) {
                let msg = 'Gagal memuat data: ' + (thrown || 'Unknown error');
                try {
                    let json = JSON.parse(xhr.responseText);
                    if (json.error) {
                        msg += '\nSQL Error: ' + JSON.stringify(json.error);
                    }
                    if (json.debug) {
                        msg += '\n\n--- DEBUG ---\n';
                        msg += 'SQL: ' + json.debug.sql + '\n';
                        msg += 'PARAMS: ' + JSON.stringify(json.debug.params) + '\n';
                        msg += 'POST: ' + JSON.stringify(json.debug.post) + '\n';
                    }
                } catch (e) {
                    msg += '\nResponse: ' + xhr.responseText;
                }
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: msg
                });
            }
        },
        columns: [
            { data: null, orderable: false, searchable: false, render: function (data, type, row, meta) { return meta.row + meta.settings._iDisplayStart + 1; } },
            { data: 'tanggal' },
            { data: 'pengecek' },
            { data: 'shift' },
            { data: 'parameter' },
            { data: 'created_by' },
            { data: 'created_at' },
            { data: 'aksi', orderable: false, searchable: false },
            { data: 'id', visible: false } // hidden, for detail
        ],
        order: [[1, 'desc']],
        responsive: true,
        language: {
            processing: "Memproses...",
            lengthMenu: "Tampilkan _MENU_ data per halaman",
            zeroRecords: "Tidak ada data ditemukan",
            info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
            infoEmpty: "Tidak ada data tersedia",
            infoFiltered: "(disaring dari _MAX_ total data)",
            search: "Cari:",
            paginate: { first: "Pertama", last: "Terakhir", next: "Selanjutnya", previous: "Sebelumnya" }
        }
    });

    // Event handler untuk filter
    $('#filterTanggal, #filterParameter, #filterShift').on('change', function() {
        table.ajax.reload();
    });
    $('#btnResetFilter').on('click', function() {
        $('#filterTanggal').val('');
        $('#filterParameter').val('');
        if ($('#filterShift').length) $('#filterShift').val('');
        table.search('').draw();
    });

    $('#btnTambahData').on('click', function(e) {
        e.preventDefault();
        // Reset isi modal pilih parameter ke default (list parameter)
        $.get(window.location.pathname, function(data) {
            var html = $(data).find('#modalPilihParameter .modal-body').html();
            $('#modalPilihParameter .modal-body').html(html);
            $('#modalPilihParameter').modal('show');
        });
    });
    // Helper to retrieve DataTable row data even when Responsive displays child rows
    function getRowData($btn) {
        var $tr = $btn.closest('tr');
        var row = table.row($tr);
        var data = row.data();
        if (!data) {
            // If Responsive has moved details into a child row, the clickable element
            // might be inside the child. The parent row is usually the previous <tr>.
            var $parentTr = $tr.prev('tr');
            data = table.row($parentTr).data();
        }
        return data;
    }
    $(document).on('click', '.btn-detail', function() {
        var rowData = getRowData($(this));
        if (!rowData || !rowData.id) return;
        $('#ipabDetailBody').html('<div class="text-center"><span class="spinner-border"></span> Memuat data...</div>');
        var urlDetail = 'view_ph_ipab.php';
        if (rowData.parameter && rowData.parameter.toLowerCase() === 'dh') {
            urlDetail = 'view_dh_ipab.php';
        } else if (rowData.parameter && rowData.parameter.toLowerCase() === 'turbidity') {
            urlDetail = 'view_turbidity_ipab.php';
        }
        $.ajax({
            url: urlDetail,
            type: 'GET',
            data: { id: rowData.id },
            success: function(html) {
                $('#ipabDetailBody').html(html);
            },
            error: function() {
                $('#ipabDetailBody').html('<div class="alert alert-danger">Gagal memuat detail data.</div>');
            }
        });
        $('#modalDetailIpab').modal('show');
    });
    $(document).on('click', '.btn-edit', function() {
        var rowData = getRowData($(this));
        if (!rowData || !rowData.id) return;
        $('#ipabDetailBody').html('<div class="text-center"><span class="spinner-border"></span> Memuat form edit...</div>');
        if (rowData.parameter && rowData.parameter.toLowerCase() === 'ph') {
            $.get('edit_ph_ipab.php', { id: rowData.id }, function(html) {
                $('#ipabDetailBody').html(html);
            }).fail(function() {
                $('#ipabDetailBody').html('<div class="alert alert-danger">Gagal memuat form edit.</div>');
            });
            $('#modalDetailIpab').modal('show');
        } else if (rowData.parameter && rowData.parameter.toLowerCase() === 'dh') {
            $.get('edit_dh_ipab.php', { id: rowData.id }, function(html) {
                $('#ipabDetailBody').html(html);
            }).fail(function() {
                $('#ipabDetailBody').html('<div class="alert alert-danger">Gagal memuat form edit.</div>');
            });
            $('#modalDetailIpab').modal('show');
        } else if (rowData.parameter && rowData.parameter.toLowerCase() === 'turbidity') {
            $.get('edit_turbidity_ipab.php', { id: rowData.id }, function(html) {
                $('#ipabDetailBody').html(html);
            }).fail(function() {
                $('#ipabDetailBody').html('<div class="alert alert-danger">Gagal memuat form edit.</div>');
            });
            $('#modalDetailIpab').modal('show');
        } else {
            Swal.fire('Belum tersedia', 'Form edit untuk parameter ini belum dibuat.', 'info');
        }
    });

    // Reload tabel dari form edit jika update sukses
    window.reloadIpabTable = function() {
        table.ajax.reload(null, false);
    };
    $(document).on('click', '.btn-delete', function() {
        var rowData = getRowData($(this));
        if (!rowData || !rowData.id) return;
        Swal.fire({
            title: 'Hapus Data?',
            text: 'Data yang dihapus tidak dapat dikembalikan!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then(function(result) {
            if (result.isConfirmed) {
                // Cek parameter, hapus ke file yang sesuai
                var urlDelete = 'delete_ph_ipab.php';
                if (rowData.parameter && rowData.parameter.toLowerCase() === 'dh') {
                    urlDelete = 'delete_dh_ipab.php';
                } else if (rowData.parameter && rowData.parameter.toLowerCase() === 'turbidity') {
                    urlDelete = 'delete_turbidity_ipab.php';
                }
                $.post(urlDelete, { id: rowData.id }, function(resp) {
                    if (resp.success) {
                        Swal.fire('Terhapus!', 'Data berhasil dihapus.', 'success');
                        table.ajax.reload(null, false);
                    } else {
                        Swal.fire('Gagal', resp.error || 'Gagal menghapus data', 'error');
                    }
                }, 'json').fail(function() {
                    Swal.fire('Gagal', 'Terjadi kesalahan saat menghapus data', 'error');
                });
            }
        });
    });
});
// Modal Pilih Parameter
</script>

<!-- Modal Pilih Parameter Air -->
<div class="modal fade" id="modalPilihParameter" tabindex="-1" role="dialog" aria-labelledby="modalPilihParameterLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title" id="modalPilihParameterLabel">Pilih Parameter Air</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label><i class="fas fa-search"></i> Cari Parameter:</label>
                    <input type="text" id="searchParameter" class="form-control" placeholder="Ketik untuk mencari parameter...">
                </div>
                <hr>
                <div id="parameterList">
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action parameter-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><i class="fas fa-tint"></i> pH</h6>
                            </div>
                            <p class="mb-1 small text-muted">Input data pH air hasil pengecekan mesin mettler toledo</p>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action parameter-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><i class="fas fa-water"></i> DH</h6>
                            </div>
                            <p class="mb-1 small text-muted">Input data DH</p>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action parameter-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><i class="fas fa-vial"></i> Turbidity</h6>
                            </div>
                            <p class="mb-1 small text-muted">Input data Turbidity</p>
                        </a>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
// Fitur cari parameter di modal
$('#searchParameter').on('keyup', function() {
    var value = $(this).val().toLowerCase();
    $('.parameter-item').filter(function() {
        $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
    });
});

// Tampilkan form pH Air jika dipilih
$(document).on('click', '.parameter-item', function(e) {
    e.preventDefault();
    var text = $(this).text().toLowerCase();
    if (text.includes('ph')) {
        // Load form_ph_ipab.php ke dalam modal
        $.get('form_ph_ipab.php', function(html) {
            $('#modalPilihParameter .modal-body').html(html);
        });
    } else if (text.includes('dh')) {
        $.get('form_dh_ipab.php', function(html) {
            $('#modalPilihParameter .modal-body').html(html);
        });
    } else if (text.includes('turbidity')) {
        $.get('form_turbidity_ipab.php', function(html) {
            $('#modalPilihParameter .modal-body').html(html);
        });
    } else {
        Swal.fire('Belum tersedia', 'Form untuk parameter ini belum dibuat.', 'info');
    }
});

// Reset isi modal pilih parameter ke tampilan awal setiap kali modal ditutup
$('#modalPilihParameter').on('hidden.bs.modal', function () {
                var defaultBody = `
        <div class="form-group">
            <label><i class="fas fa-search"></i> Cari Parameter:</label>
            <input type="text" id="searchParameter" class="form-control" placeholder="Ketik untuk mencari parameter...">
        </div>
        <hr>
        <div id="parameterList">
            <div class="list-group">
                <a href="#" class="list-group-item list-group-item-action parameter-item">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1"><i class="fas fa-tint"></i> pH</h6>
                    </div>
                    <p class="mb-1 small text-muted">Input data pH air hasil pengecekan mesin mettler toledo</p>
                </a>
                <a href="#" class="list-group-item list-group-item-action parameter-item">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1"><i class="fas fa-water"></i> DH</h6>
                    </div>
                    <p class="mb-1 small text-muted">Input data DH</p>
                </a>
                <a href="#" class="list-group-item list-group-item-action parameter-item">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1"><i class="fas fa-vial"></i> Turbidity</h6>
                    </div>
                    <p class="mb-1 small text-muted">Input data Turbidity</p>
                </a>
            </div>
        </div>
    `;
    $('#modalPilihParameter .modal-body').html(defaultBody);
});
</script>
