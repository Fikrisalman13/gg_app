<?php
session_start();

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
$menuUrlVariants = [
    '/gg_app/pages/form_it/Serah_terima/list_serah_terima.php',
    '/gg_app/pages/form_it/serah_terima/list_serah_terima.php',
];

function getCurrentMenuId($conn, array $menuUrlVariants, $fallbackMenuId = 134)
{
    $placeholders = implode(',', array_fill(0, count($menuUrlVariants), '?'));
    $sql = "SELECT TOP 1 MenuId FROM dbo.SMMenu WHERE MenuUrl IN ($placeholders)";
    $stmt = sqlsrv_query($conn, $sql, $menuUrlVariants);

    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        sqlsrv_free_stmt($stmt);
        return (int) $row['MenuId'];
    }

    if ($stmt !== false) {
        sqlsrv_free_stmt($stmt);
    }

    return $fallbackMenuId;
}

function checkPermissions($conn, $groupId, $menuId)
{
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete
            FROM dbo.SMGroupTrustee
            WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);

    $permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }

    if ($stmt !== false) {
        sqlsrv_free_stmt($stmt);
    }

    return $permissions;
}

$menuId = getCurrentMenuId($conn, $menuUrlVariants);
$permissions = checkPermissions($conn, $_SESSION['GroupId'], $menuId);
if ((int) $permissions['CanView'] !== 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: /gg_app/index.php');
    exit;
}

$filterKategori = $_GET['kategori'] ?? '';
$filterTanggal = $_GET['tanggal'] ?? '';

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
?>

<style>
    .action-btn {
        margin-right: 6px;
    }

    table.dataTable.dtr-inline.collapsed > tbody > tr > td.dtr-control:before,
    table.dataTable.dtr-inline.collapsed > tbody > tr > th.dtr-control:before {
        background-color: #007bff;
        border: none;
        box-shadow: none;
        line-height: 1em;
        top: 50%;
        transform: translateY(-50%);
    }

    table.dataTable > tbody > tr.child ul.dtr-details {
        display: block;
        width: 100%;
        padding: 0;
    }

    table.dataTable > tbody > tr.child ul.dtr-details > li {
        border-bottom: 1px solid #efefef;
        padding: 8px 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    table.dataTable > tbody > tr.child ul.dtr-details > li:last-child {
        border-bottom: none;
    }

    table.dataTable > tbody > tr.child ul.dtr-details > li .dtr-title {
        font-weight: 600;
        color: #555;
        min-width: 120px;
    }

    table.dataTable > tbody > tr.child ul.dtr-details > li .dtr-data {
        text-align: right;
        flex: 1;
        word-break: break-word;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1>Form Serah Terima</h1>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-primary">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                    <h3 class="card-title">
                        <i class="fas fa-list mr-1"></i> List Serah Terima
                    </h3>
                    <?php if (!empty($permissions['CanAdd']) && (int) $permissions['CanAdd'] === 1): ?>
                        <button type="button" class="btn btn-success btn-sm float-right" id="btnTambahForm">
                            <i class="fas fa-plus"></i> Tambah Form
                        </button>
                    <?php endif; ?>
                </div>

                <div class="card-body table-responsive">
                    <div class="filter-section p-3 mb-3" style="background-color: #f8f9fa; border-radius: 5px;">
                        <div class="row">
                            <div class="col-md-3">
                                <label class="small mb-1" for="filterKategori">Filter Kategori</label>
                                <select id="filterKategori" class="form-control form-control-sm">
                                    <option value="">Semua Kategori</option>
                                    <option value="Serah Terima Aplikasi" <?php echo ($filterKategori == 'Serah Terima Aplikasi') ? 'selected' : ''; ?>>Serah Terima Aplikasi</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="small mb-1" for="filterTanggal">Filter Tanggal</label>
                                <input type="date" id="filterTanggal" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filterTanggal); ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button id="btnReset" class="btn btn-secondary btn-sm" title="Reset Filter">
                                    <i class="fas fa-sync"></i> Reset
                                </button>
                            </div>
                            <div class="col-md-4 d-flex align-items-end justify-content-end">
                                <button id="btnExportPdf" class="btn btn-danger btn-sm">
                                    <i class="fas fa-file-pdf"></i> Export PDF
                                </button>
                            </div>
                        </div>
                    </div>

                    <table id="serahTerimaTable" class="table table-hover table-sm nowrap" style="width:100%">
                        <thead class="thead-light">
                            <tr>
                                <th>No</th>
                                <th>No Pengajuan</th>
                                <th>Kategori</th>
                                <th>Pemohon</th>
                                <th>Departemen</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="modal fade" id="modalDetailSerahTerima" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document" style="max-width: 95%;">
        <div class="modal-content" style="height: 90vh; display: flex; flex-direction: column;">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                <h5 class="modal-title">Detail Serah Terima : <span id="modalTicketNumber"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding:0; flex: 1 1 auto;">
                <iframe id="iframeDetailSerahTerima" src="about:blank" style="border:0; width:100%; height:100%;"></iframe>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalPilihForm" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                <h5 class="modal-title">Pilih Form</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label><i class="fas fa-search"></i> Cari Form:</label>
                    <input type="text" id="searchForm" class="form-control" placeholder="Ketik untuk mencari form...">
                </div>
                <hr>
                <div id="formList">
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action form-item" data-form-type="serah_terima_aplikasi">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><i class="fas fa-handshake"></i> Form Serah Terima</h6>
                            </div>
                            <p class="mb-1 small text-muted">Serah terima hasil pembuatan/pengembangan aplikasi</p>
                        </a>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalFormIsian" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document" style="max-height: 90vh; margin: 1.75rem auto;">
        <div class="modal-content" style="max-height: 90vh; display: flex; flex-direction: column;">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white" style="flex-shrink: 0;">
                <h5 class="modal-title" id="modalFormTitle">Form Serah Terima</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="modalFormBody" style="overflow-y: auto; flex: 1 1 auto; min-height: 0; padding: 1rem; max-height: calc(90vh - 140px);">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <p class="mt-2">Memuat form...</p>
                </div>
            </div>
            <div class="modal-footer" style="flex-shrink: 0; border-top: 1px solid #dee2e6;">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success" id="btnSimpanForm">
                    <i class="fas fa-save"></i> Simpan
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>

<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
$(document).ready(function() {
    var table = $('#serahTerimaTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'list_serah_terima_serverside.php',
            type: 'POST',
            data: function(d) {
                d.kategori = $('#filterKategori').val();
                d.tanggal = $('#filterTanggal').val();
                d.search_value = d.search.value;
            },
            error: function(xhr, error, thrown) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Gagal memuat data: ' + (thrown || 'Unknown error')
                });
            }
        },
        columns: [
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function(data, type, row, meta) {
                    return meta.row + meta.settings._iDisplayStart + 1;
                }
            },
            { data: 'ticket' },
            { data: 'kategori' },
            { data: 'nama_pemohon' },
            { data: 'departemen' },
            { data: 'tgl_pengajuan' },
            { data: 'status_ticket' },
            { data: 'aksi', orderable: false, searchable: false }
        ],
        order: [[5, 'desc']],
        responsive: true,
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

    $('#filterKategori, #filterTanggal').on('change', function() {
        table.ajax.reload();
    });

    $('#btnReset').on('click', function() {
        $('#filterKategori').val('');
        $('#filterTanggal').val('');
        table.ajax.reload();
    });

    $('#btnExportPdf').on('click', function() {
        var params = [];
        var kategori = $('#filterKategori').val();
        var tanggal = $('#filterTanggal').val();

        if (kategori) {
            params.push('kategori=' + encodeURIComponent(kategori));
        }

        if (tanggal) {
            params.push('tanggal=' + encodeURIComponent(tanggal));
        }

        window.open('export_pdf_list.php?' + params.join('&'), '_blank');
    });

    $('#btnTambahForm').on('click', function() {
        $('#modalPilihForm').modal('show');
    });

    $('#searchForm').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $('.form-item').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });

    $('.form-item').on('click', function(e) {
        e.preventDefault();
        var formType = $(this).data('form-type');
        var formTitle = $(this).find('h6').text().trim();

        $('#modalPilihForm').modal('hide');
        $('#modalFormTitle').text(formTitle);
        $('#modalFormIsian').modal('show');
        loadFormContent(formType);
    });

    function loadFormContent(formType) {
        $('#modalFormBody').html(`
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
                <p class="mt-2">Memuat form...</p>
            </div>
        `);

        $.ajax({
            url: 'load_form.php',
            type: 'GET',
            data: { form_type: formType },
            dataType: 'json',
            success: function(response) {
                if (response && response.success) {
                    $('#modalFormBody').html(response.content);
                    $('#modalFormBody').find('script').each(function() {
                        try {
                            eval($(this).html());
                        } catch (e) {
                            console.error('Error executing script:', e);
                        }
                    });
                } else {
                    $('#modalFormBody').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Gagal memuat form: ' + (response.error || 'Unknown error') + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $('#modalFormBody').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error: ' + error + '</div>');
            }
        });
    }

    $('#btnSimpanForm').on('click', function() {
        var formId = $('#formSerahTerimaAplikasi').length ? '#formSerahTerimaAplikasi' : '';

        if (!formId || !$(formId).length) {
            return;
        }

        if (!$(formId)[0].reportValidity()) {
            return;
        }

        if (typeof window.validateTTDBeforeSubmit === 'function') {
            if (window._skipTTDCheckOnce) {
                window._skipTTDCheckOnce = false;
                submitFormAjax(formId);
                return;
            }

            window.validateTTDBeforeSubmit().then(function(canSubmit) {
                if (canSubmit) {
                    submitFormAjax(formId);
                }
            }).catch(function() {
                submitFormAjax(formId);
            });
            return;
        }

        submitFormAjax(formId);
    });

    function submitFormAjax(formId) {
        $.ajax({
            url: 'proses_simpan.php',
            type: 'POST',
            data: $(formId).serialize(),
            dataType: 'json',
            beforeSend: function() {
                $('#btnSimpanForm').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');
            },
            success: function(response) {
                if (response && response.success) {
                    var ticket = response.ticket;
                    if (ticket && typeof window.saveTTDWithTicket === 'function') {
                        window.saveTTDWithTicket(ticket).then(function() {
                            showSaveSuccess(response, ticket);
                        }).catch(function() {
                            showSaveSuccess(response, ticket);
                        });
                    } else {
                        showSaveSuccess(response, ticket);
                    }
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal Menyimpan', html: response.message || 'Gagal menyimpan data.' });
                    $('#btnSimpanForm').prop('disabled', false).html('<i class="fas fa-save"></i> Simpan');
                }
            },
            error: function(xhr) {
                var msg = 'Terjadi kesalahan saat menyimpan data.';
                try {
                    var res = JSON.parse(xhr.responseText);
                    msg = res.message || res.error || msg;
                } catch (e) {
                    if (xhr.responseText) msg = xhr.responseText.substring(0, 200);
                }
                Swal.fire({ icon: 'error', title: 'Error', html: msg });
                $('#btnSimpanForm').prop('disabled', false).html('<i class="fas fa-save"></i> Simpan');
            }
        });
    }

    function showSaveSuccess(response, ticket) {
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            html: (response.message || 'Data berhasil disimpan') + (ticket ? '<br><strong>No. Pengajuan: ' + ticket + '</strong>' : ''),
            timer: 2500,
            showConfirmButton: true
        });

        setTimeout(function() {
            $('#modalFormIsian').modal('hide');
            $('#btnSimpanForm').prop('disabled', false).html('<i class="fas fa-save"></i> Simpan');
            table.ajax.reload();
        }, 1200);
    }

    $(document).on('click', '.btn-delete', function() {
        var ticket = $(this).data('ticket');
        if (!ticket) return;

        Swal.fire({
            title: 'Hapus Data?',
            html: 'Apakah Anda yakin ingin menghapus serah terima:<br><strong>' + ticket + '</strong>?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then(function(result) {
            if (!result.isConfirmed) return;

            $.ajax({
                url: 'proses_delete.php',
                type: 'POST',
                data: { ticket: ticket },
                dataType: 'json',
                success: function(response) {
                    if (response && response.success) {
                        Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Data berhasil dihapus.' });
                        table.ajax.reload();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal', html: response.message || 'Gagal menghapus data.' });
                    }
                },
                error: function() {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan saat menghapus data.' });
                }
            });
        });
    });

    $(document).on('click', '.btn-detail', function() {
        var ticket = $(this).data('ticket');
        var detailUrl = $(this).data('detail-url');

        if (!ticket || !detailUrl) {
            return;
        }

        $('#modalTicketNumber').text(ticket);
        $('#iframeDetailSerahTerima').attr('src', detailUrl);
        $('#modalDetailSerahTerima').modal('show');
    });

    $('#modalDetailSerahTerima').on('hidden.bs.modal', function() {
        $('#iframeDetailSerahTerima').attr('src', 'about:blank');
    });

    $('#modalFormIsian').on('hidden.bs.modal', function() {
        $('#modalFormBody').html(`
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
                <p class="mt-2">Memuat form...</p>
            </div>
        `);
        $('#btnSimpanForm').prop('disabled', false).html('<i class="fas fa-save"></i> Simpan');
    });
});
</script>
