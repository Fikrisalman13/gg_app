<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
$menuId = 190; // Ganti dengan MenuId PH Air di database
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
            <h1>Pengecekan Kualitas Air IPAL</h1>
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
                                                <option value="pH Air">pH Air</option>
                                                <option value="COD">COD</option>
                                                <option value="TSS">TSS</option>
                                                <option value="MLSS">MLSS</option>
                                                <option value="PTCO">PTCO</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3 d-flex align-items-end">
                                            <button type="button" class="btn btn-secondary btn-sm" id="btnResetFilter">
                                                <i class="fas fa-undo"></i> Reset Filter
                                            </button>
                                        </div>
                                        
                                    </div>
                                    <table id="phAirTable" class="table table-hover table-sm nowrap" style="width:100%">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>No</th>
                                                <th>Tanggal</th>
                                                <th>Pengecek</th>
                                                <th>Sampel Air</th>
                                                <th>Parameter</th>
                                                <th>Created By</th>
                                                <th>Update At</th>
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

<!-- Modal Detail PH Air -->
<div class="modal fade" id="modalDetailPhAir" tabindex="-1" role="dialog" aria-labelledby="modalDetailPhAirLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title" id="modalDetailPhAirLabel">Detail</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="phAirDetailBody">
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
<style>
#modalPilihParameter .modal-dialog.ipal-picker-dialog {
    max-width: min(640px, calc(100vw - 32px));
}
#modalPilihParameter .modal-dialog.ipal-form-dialog,
#modalDetailPhAir .modal-dialog.ipal-form-dialog {
    max-width: min(1080px, calc(100vw - 32px));
}
.ipal-parameter-body {
    background: #f8fafc;
}
.ipal-parameter-search label {
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 7px;
}
.ipal-parameter-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-top: 12px;
}
.ipal-parameter-card {
    align-items: flex-start;
    background: #fff;
    border: 1px solid #d8dee5;
    border-radius: 6px;
    color: #343a40;
    display: flex;
    gap: 10px;
    min-height: 78px;
    padding: 12px;
    transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
}
.ipal-parameter-card:hover,
.ipal-parameter-card:focus {
    border-color: #6f42c1;
    box-shadow: 0 6px 16px rgba(33, 37, 41, .10);
    color: #212529;
    text-decoration: none;
    transform: translateY(-1px);
}
.ipal-parameter-icon {
    align-items: center;
    background: #eef2ff;
    border-radius: 6px;
    color: #4b1fa4;
    display: inline-flex;
    flex: 0 0 34px;
    height: 34px;
    justify-content: center;
    width: 34px;
}
.ipal-parameter-card h6 {
    font-size: 14px;
    font-weight: 700;
    margin: 0 0 3px;
}
.ipal-parameter-card p {
    line-height: 1.35;
    margin: 0;
}
@media (max-width: 767.98px) {
    #modalPilihParameter .modal-dialog.ipal-picker-dialog,
    #modalPilihParameter .modal-dialog.ipal-form-dialog,
    #modalDetailPhAir .modal-dialog.ipal-form-dialog {
        max-width: calc(100vw - 16px);
        margin: .5rem auto;
    }
    .ipal-parameter-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
$(document).ready(function() {
    var table = $('#phAirTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'ph_air_serverside.php',
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
            { data: 'updated_at', defaultContent: '-' },
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
        $('#filterParameter').prop('selectedIndex', 0);
        if ($('#filterShift').length) $('#filterShift').prop('selectedIndex', 0);
        table.ajax.reload();
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
        $('#modalDetailPhAir .modal-dialog').removeClass('ipal-form-dialog modal-xl').addClass('modal-lg');
        $('#modalDetailPhAirLabel').text('Detail');
        $('#phAirDetailBody').html('<div class="text-center"><span class="spinner-border"></span> Memuat data...</div>');
        var urlDetail = 'ph_air_detail.php';
        if (rowData.parameter && rowData.parameter.toLowerCase() === 'cod') {
            urlDetail = 'cod_air_detail.php';
        } else if (rowData.parameter && rowData.parameter.toLowerCase() === 'tss') {
            urlDetail = 'tss_air_detail.php';
        } else if (rowData.parameter && rowData.parameter.toLowerCase() === 'mlss') {
            urlDetail = 'mlss_air_detail.php';
        } else if (rowData.parameter && rowData.parameter.toLowerCase() === 'ptco') {
            urlDetail = 'ptco_air_detail.php';
        }
        $.ajax({
            url: urlDetail,
            type: 'GET',
            data: { id: rowData.id },
            success: function(html) {
                $('#phAirDetailBody').html(html);
            },
            error: function() {
                $('#phAirDetailBody').html('<div class="alert alert-danger">Gagal memuat detail data.</div>');
            }
        });
        $('#modalDetailPhAir').modal('show');
    });
    $(document).on('click', '.btn-edit', function() {
        var rowData = getRowData($(this));
        if (!rowData || !rowData.id) return;
        var isTssEdit = rowData.parameter && rowData.parameter.toLowerCase() === 'tss';
        $('#modalDetailPhAir .modal-dialog')
            .toggleClass('ipal-form-dialog modal-xl', isTssEdit)
            .toggleClass('modal-lg', !isTssEdit);
        $('#modalDetailPhAirLabel').text(isTssEdit ? 'Edit Data TSS Air' : 'Edit Data');
        $('#phAirDetailBody').html('<div class="text-center"><span class="spinner-border"></span> Memuat form edit...</div>');
        var urlEdit = 'form_edit_ph_air.php';
        if (rowData.parameter && rowData.parameter.toLowerCase() === 'cod') {
            urlEdit = 'form_edit_cod_air.php';
        } else if (rowData.parameter && rowData.parameter.toLowerCase() === 'tss') {
            urlEdit = 'form_edit_tss_air.php';
        } else if (rowData.parameter && rowData.parameter.toLowerCase() === 'mlss') {
            urlEdit = 'form_edit_mlss_air.php';
        } else if (rowData.parameter && rowData.parameter.toLowerCase() === 'ptco') {
            urlEdit = 'form_edit_ptco_air.php';
        }
        $.get(urlEdit, { id: rowData.id }, function(html) {
            $('#phAirDetailBody').html(html);
        }).fail(function() {
            $('#phAirDetailBody').html('<div class="alert alert-danger">Gagal memuat form edit.</div>');
        });
        $('#modalDetailPhAir').modal('show');
    });

    // Reload tabel dari form edit jika update sukses
    window.reloadPhAirTable = function() {
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
                var urlDelete = 'delete_ph_air.php';
                if (rowData.parameter && rowData.parameter.toLowerCase() === 'cod') {
                    urlDelete = 'delete_cod_air.php';
                } else if (rowData.parameter && rowData.parameter.toLowerCase() === 'tss') {
                    urlDelete = 'delete_tss_air.php';
                } else if (rowData.parameter && rowData.parameter.toLowerCase() === 'mlss') {
                    urlDelete = 'delete_mlss_air.php';
                } else if (rowData.parameter && rowData.parameter.toLowerCase() === 'ptco') {
                    urlDelete = 'delete_ptco_air.php';
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
    <div class="modal-dialog ipal-picker-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title" id="modalPilihParameterLabel">Pilih Parameter Air</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body ipal-parameter-body">
                <div class="ipal-parameter-search">
                    <label><i class="fas fa-search"></i> Cari Parameter:</label>
                    <input type="text" id="searchParameter" class="form-control" placeholder="Ketik untuk mencari parameter...">
                </div>
                <div id="parameterList">
                    <div class="ipal-parameter-grid">
                        <a href="#" class="parameter-item ipal-parameter-card" data-form="ph">
                            <span class="ipal-parameter-icon"><i class="fas fa-tint"></i></span>
                            <div>
                                <h6>pH Air</h6>
                                <p class="small text-muted">Input data pH air hasil pengecekan mesin mettler toledo</p>
                            </div>
                        </a>
                        <a href="#" class="parameter-item ipal-parameter-card" data-form="cod">
                            <span class="ipal-parameter-icon"><i class="fas fa-vial"></i></span>
                            <div>
                                <h6>COD</h6>
                                <p class="small text-muted">Input data Chemical Oxygen Demand (COD)</p>
                            </div>
                        </a>
                        <a href="#" class="parameter-item ipal-parameter-card" data-form="tss">
                            <span class="ipal-parameter-icon"><i class="fas fa-flask"></i></span>
                            <div>
                                <h6>TSS</h6>
                                <p class="small text-muted">Input data Total Suspended Solid (TSS)</p>
                            </div>
                        </a>
                        <a href="#" class="parameter-item ipal-parameter-card" data-form="mlss">
                            <span class="ipal-parameter-icon"><i class="fas fa-water"></i></span>
                            <div>
                                <h6>MLSS</h6>
                                <p class="small text-muted">Input data Mixed Liquor Suspended Solids (MLSS)</p>
                            </div>
                        </a>
                        <a href="#" class="parameter-item ipal-parameter-card" data-form="ptco">
                            <span class="ipal-parameter-icon"><i class="fas fa-vial"></i></span>
                            <div>
                                <h6>PTCO</h6>
                                <p class="small text-muted">Input data PTCO</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
// Fitur cari parameter di modal
$(document).on('keyup', '#searchParameter', function() {
    var value = $(this).val().toLowerCase();
    $('.parameter-item').filter(function() {
        $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
    });
});

function getParameterPickerHtml() {
    return `
        <div class="ipal-parameter-search">
            <label><i class="fas fa-search"></i> Cari Parameter:</label>
            <input type="text" id="searchParameter" class="form-control" placeholder="Ketik untuk mencari parameter...">
        </div>
        <div id="parameterList">
            <div class="ipal-parameter-grid">
                <a href="#" class="parameter-item ipal-parameter-card" data-form="ph">
                    <span class="ipal-parameter-icon"><i class="fas fa-tint"></i></span>
                    <div>
                        <h6>pH Air</h6>
                        <p class="small text-muted">Input data pH air hasil pengecekan mesin mettler toledo</p>
                    </div>
                </a>
                <a href="#" class="parameter-item ipal-parameter-card" data-form="cod">
                    <span class="ipal-parameter-icon"><i class="fas fa-vial"></i></span>
                    <div>
                        <h6>COD</h6>
                        <p class="small text-muted">Input data Chemical Oxygen Demand (COD)</p>
                    </div>
                </a>
                <a href="#" class="parameter-item ipal-parameter-card" data-form="tss">
                    <span class="ipal-parameter-icon"><i class="fas fa-flask"></i></span>
                    <div>
                        <h6>TSS</h6>
                        <p class="small text-muted">Input data Total Suspended Solid (TSS)</p>
                    </div>
                </a>
                <a href="#" class="parameter-item ipal-parameter-card" data-form="mlss">
                    <span class="ipal-parameter-icon"><i class="fas fa-water"></i></span>
                    <div>
                        <h6>MLSS</h6>
                        <p class="small text-muted">Input data Mixed Liquor Suspended Solids (MLSS)</p>
                    </div>
                </a>
                <a href="#" class="parameter-item ipal-parameter-card" data-form="ptco">
                    <span class="ipal-parameter-icon"><i class="fas fa-vial"></i></span>
                    <div>
                        <h6>PTCO</h6>
                        <p class="small text-muted">Input data PTCO</p>
                    </div>
                </a>
            </div>
        </div>
    `;
}

function resetParameterPicker() {
    $('#modalPilihParameterLabel').text('Pilih Parameter Air');
    $('#modalPilihParameter .modal-dialog')
        .removeClass('ipal-form-dialog modal-xl')
        .addClass('ipal-picker-dialog');
    $('#modalPilihParameter .modal-body')
        .addClass('ipal-parameter-body')
        .html(getParameterPickerHtml());
}

// Tampilkan form input sesuai parameter yang dipilih
$(document).on('click', '.parameter-item', function(e) {
    e.preventDefault();
    var formKey = $(this).data('form');
    var formMap = {
        ph: { url: 'form_ph_air.php', title: 'Input Data pH Air' },
        cod: { url: 'form_cod_air.php', title: 'Input Data COD Air' },
        tss: { url: 'form_tss_air.php', title: 'Input Data TSS Air' },
        mlss: { url: 'form_mlss_air.php', title: 'Input Data MLSS Air' },
        ptco: { url: 'form_ptco_air.php', title: 'Input Data PTCO Air' }
    };
    var config = formMap[formKey];
    if (!config) {
        Swal.fire('Belum tersedia', 'Form untuk parameter ini belum dibuat.', 'info');
        return;
    }

    $('#modalPilihParameterLabel').text(config.title);
    $('#modalPilihParameter .modal-dialog')
        .removeClass('ipal-picker-dialog')
        .addClass('ipal-form-dialog modal-xl');
    $('#modalPilihParameter .modal-body')
        .removeClass('ipal-parameter-body')
        .html('<div class="text-center py-4"><span class="spinner-border"></span> Memuat form...</div>');

    $.get(config.url, function(html) {
        $('#modalPilihParameter .modal-body').html(html);
    }).fail(function() {
        Swal.fire('Belum tersedia', config.title + ' belum dapat dimuat.', 'info');
        resetParameterPicker();
    });
});

// Reset isi modal pilih parameter ke tampilan awal setiap kali modal ditutup
$('#modalPilihParameter').on('hidden.bs.modal', resetParameterPicker);
</script>
