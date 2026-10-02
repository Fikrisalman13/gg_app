<?php
session_start();

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

function checkPermissions($conn, $groupId, $menuId)
{
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete
            FROM dbo.SMGroupTrustee
            WHERE GroupId = ? AND MenuId = ?";
    $params = [$groupId, $menuId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    // Fallback: jika menu belum terdaftar di SMGroupTrustee, izinkan user yang login.
    $permissions = ['CanView' => 1, 'CanAdd' => 1, 'CanEdit' => 1, 'CanDelete' => 1];
    if ($stmt !== false) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($row)
            $permissions = $row;
    }
    if ($stmt !== false)
        sqlsrv_free_stmt($stmt);
    return $permissions;
}

// MenuId fallback untuk Form Purchasing
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 0);
$isAdmin = (isset($_SESSION['GroupId']) && (int) $_SESSION['GroupId'] === 1);

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');

$filterTanggal = $_GET['tanggal'] ?? '';
?>

<style>
    .action-btn {
        margin-right: 6px;
    }

    table.dataTable.dtr-inline.collapsed>tbody>tr>td.dtr-control:before,
    table.dataTable.dtr-inline.collapsed>tbody>tr>th.dtr-control:before {
        background-color: #007bff;
        border: none;
        box-shadow: none;
        line-height: 1em;
        top: 50%;
        transform: translateY(-50%);
    }

    table.dataTable>tbody>tr.child ul.dtr-details {
        display: block;
        width: 100%;
        padding: 0;
    }

    table.dataTable>tbody>tr.child ul.dtr-details>li {
        border-bottom: 1px solid #efefef;
        padding: 8px 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    table.dataTable>tbody>tr.child ul.dtr-details>li:last-child {
        border-bottom: none;
    }

    table.dataTable>tbody>tr.child ul.dtr-details>li .dtr-title {
        font-weight: 600;
        color: #555;
        min-width: 120px;
    }

    table.dataTable>tbody>tr.child ul.dtr-details>li .dtr-data {
        text-align: right;
        flex: 1;
        word-break: break-word;
    }

    #modalFormIsian > .modal-dialog {
        max-width: 95% !important;
        width: 95% !important;
    }
</style>

<div class="wrapper">
    <div class="content-wrapper">

        <section class="content-header">
            <div class="container-fluid">
                <h1>Form Purchasing</h1>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">

                <div class="card card-primary">
                    <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                        <h3 class="card-title"><i class="fas fa-list mr-1"></i> List Pengajuan Purchasing</h3>
                        <?php if (!empty($permissions['CanAdd']) && (int) $permissions['CanAdd'] === 1): ?>
                            <button type="button" class="btn btn-success btn-sm float-right" id="btnTambahForm">
                                <i class="fas fa-plus"></i> Tambah Form
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="card-body table-responsive">
                        <div class="filter-section p-3 mb-3" style="background-color: #f8f9fa; border-radius: 5px;">
                            <div class="row align-items-end">
                                <div class="col-md-3">
                                    <label class="small mb-1 font-weight-bold">Jenis Report</label>
                                    <select class="form-control form-control-sm" id="pdfReportType">
                                        <option value="">-- Semua --</option>
                                        <option value="rekap_cash_on_delivery">Rekap Pengajuan Cash On Delivery</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="small mb-1 font-weight-bold" for="filterTanggal">Tgl Dari</label>
                                    <input type="date" id="filterTanggal" class="form-control form-control-sm"
                                        value="<?php echo htmlspecialchars($filterTanggal); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="small mb-1 font-weight-bold" for="filterTanggalSampai">Tgl Sampai</label>
                                    <input type="date" id="filterTanggalSampai" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-2">
                                    <label class="small mb-1 font-weight-bold">Status</label>
                                    <select class="form-control form-control-sm" id="pdfFilterStatus" disabled>
                                        <option value="">Pilih Jenis Report terlebih dahulu</option>
                                    </select>
                                </div>
                                <div class="col-md-1 pl-0">
                                    <button id="btnReset" class="btn btn-secondary btn-sm w-100" title="Reset Filter">
                                        <i class="fas fa-sync"></i> Reset
                                    </button>
                                </div>
                                <div class="col d-flex align-items-end justify-content-end">
                                    <button type="button" class="btn btn-danger btn-sm" id="btnGeneratePdf">
                                        <i class="fas fa-file-pdf"></i> Generate PDF
                                    </button>
                                </div>
                            </div>
                        </div>

                        <table id="tiketTable" class="table table-hover table-sm nowrap" style="width:100%">
                            <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>No Pengajuan</th>
                                    <th>No PO</th>
                                    <th>Kategori</th>
                                    <th>Pemohon</th>
                                    <th>Tanggal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Modal Detail Tiket -->
                <div class="modal fade" id="modalDetailTiket" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-xl" role="document" style="max-width: 95%;">
                        <div class="modal-content" style="height: 90vh; display: flex; flex-direction: column;">
                            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                                <h5 class="modal-title">Detail Pengajuan : <span id="modalTicketNumber"></span></h5>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <button type="button" class="btn btn-sm btn-danger" id="btnRejectFromModal"
                                        style="display: none;">
                                        <i class="fas fa-ban"></i> Tolak Pengajuan
                                    </button>
                                    <button type="button" class="close text-white" data-dismiss="modal"
                                        aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            </div>
                            <div class="modal-body" style="padding:0; flex: 1 1 auto;">
                                <iframe id="iframeDetailTiket" src="about:blank"
                                    style="border:0; width:100%; height:100%;"></iframe>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </div>

    <!-- Modal Reject Pengajuan -->
    <div class="modal fade" id="modalRejectFromList" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Tolak Pengajuan</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menolak pengajuan ini?</p>
                    <hr>
                    <div class="form-group">
                        <label for="rejectReasonFromList"><b>Alasan Penolakan (Opsional)</b></label>
                        <textarea class="form-control" id="rejectReasonFromList" name="reject_reason" rows="4"
                            placeholder="Masukkan alasan penolakan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-danger" id="btnConfirmRejectFromList">Ya, Tolak
                        Pengajuan</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Pilih Form -->
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
                        <input type="text" id="searchForm" class="form-control"
                            placeholder="Ketik untuk mencari form...">
                    </div>
                    <hr>
                    <div id="formList">
                        <div class="list-group">
                            <a href="#" class="list-group-item list-group-item-action form-item"
                                data-form-type="cash_on_delivery">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><i class="fas fa-money-bill-wave"></i> Form Pengajuan Cash On Delivery
                                    </h6>
                                </div>
                                <p class="mb-1 small text-muted">Form pengajuan pembelian / pembayaran Cash On Delivery (COD)</p>
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

    <!-- Modal Form Isian -->
    <div class="modal fade" id="modalFormIsian" tabindex="-1" role="dialog" data-backdrop="static">
        <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document"
            style="max-width: 95% !important; width: 95% !important; max-height: 90vh; margin: 1.75rem auto;">
            <div class="modal-content" style="max-height: 90vh; display: flex; flex-direction: column;">
                <div class="modal-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white"
                    style="flex-shrink: 0;">
                    <h5 class="modal-title" id="modalFormTitle">Form</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="modalFormBody"
                    style="overflow-y: auto; flex: 1 1 auto; min-height: 0; padding: 1rem; max-height: calc(90vh - 140px);">
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

</div>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet"
    href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<!-- DataTables JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<!-- SweetAlert -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
    var isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
    $(document).ready(function () {

        // ─── DataTable ───────────────────────────────────────────────────
        var table = $('#tiketTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: 'list_form_serverside.php',
                type: 'POST',
                data: function (d) {
                    d.tanggal = $('#filterTanggal').val();
                    d.tanggal_sampai = $('#filterTanggalSampai').val();
                    d.status_filter = $('#pdfFilterStatus').val();
                    d.report_type = $('#pdfReportType').val();
                    d.search_value = d.search.value;
                },
                error: function (xhr, error, thrown) {
                    console.warn('DataTables info:', xhr, error, thrown);
                }
            },
            columns: [
                {
                    data: null, orderable: false, searchable: false,
                    render: function (data, type, row, meta) { return meta.row + meta.settings._iDisplayStart + 1; }
                },
                { data: 'ticket' },
                { data: 'no_po' },
                { data: 'kategori' },
                { data: 'nama_pemohon' },
                { data: 'tgl_pengajuan' },
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
                paginate: { first: "Pertama", last: "Terakhir", next: "Selanjutnya", previous: "Sebelumnya" }
            }
        });

        // ─── Filter ──────────────────────────────────────────────────────
        $('#filterTanggal, #filterTanggalSampai, #pdfFilterStatus').on('change', function () {
            table.ajax.reload();
        });

        // Update status dropdown berdasarkan Jenis Report
        $('#pdfReportType').on('change', function () {
            var val = $(this).val();
            var $statusSelect = $('#pdfFilterStatus');
            $statusSelect.empty();
            
            if (!val || val === '') {
                $statusSelect.prop('disabled', true);
                $statusSelect.append('<option value="">Pilih Jenis Report terlebih dahulu</option>');
            } else {
                $statusSelect.prop('disabled', false);
                $statusSelect.append('<option value="">-- Semua --</option>');
                $statusSelect.append('<option value="Pending">Pending</option>');
                $statusSelect.append('<option value="Approved">Approved</option>');
                $statusSelect.append('<option value="Ditolak">Ditolak</option>');
            }
            
            table.ajax.reload();
        });

        $('#btnReset').on('click', function () {
            $('#filterTanggal').val('');
            $('#filterTanggalSampai').val('');
            $('#pdfReportType').val('');
            
            var $statusSelect = $('#pdfFilterStatus');
            $statusSelect.empty();
            $statusSelect.prop('disabled', true);
            $statusSelect.append('<option value="">Pilih Jenis Report terlebih dahulu</option>');
            
            table.ajax.reload();
        });

        // ─── Generate PDF Rekap ──────────────────────────────────────────
        $('#btnGeneratePdf').on('click', function () {
            var reportType = $('#pdfReportType').val();
            if (!reportType) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Pilih Jenis Report',
                    text: 'Silakan pilih Jenis Report terlebih dahulu untuk men-generate PDF Rekap.'
                });
                return;
            }

            var tglDari = $('#filterTanggal').val();
            var tglSampai = $('#filterTanggalSampai').val();
            var status = $('#pdfFilterStatus').val();

            var params = new URLSearchParams();
            if (tglDari) params.append('dari', tglDari);
            if (tglSampai) params.append('sampai', tglSampai);
            if (status) params.append('status', status);

            var targetUrl = 'generate_pdf_rekap_cod.php';
            var queryString = params.toString();
            var fullUrl = targetUrl + (queryString ? '?' + queryString : '');
            window.open(fullUrl, '_blank');
        });

        // ─── Notification helpers ─────────────────────────────────────────
        function showSuccess(message, title) {
            title = title || 'Sukses!';
            Swal.fire({ icon: 'success', title: title, html: message, timer: 3000, timerProgressBar: true, showConfirmButton: true, confirmButtonColor: '#28a745' });
        }
        function showError(message, title) {
            title = title || 'Error!';
            Swal.fire({ icon: 'error', title: title, html: message, timer: 4000, timerProgressBar: true, showConfirmButton: true, confirmButtonColor: '#dc3545' });
        }

        // ─── Tambah Form ─────────────────────────────────────────────────
        $('#btnTambahForm').on('click', function () {
            var btn = $(this);
            if (btn.prop('disabled')) return;
            btn.prop('disabled', true);
            $('#modalPilihForm').modal('show');
            setTimeout(function () { btn.prop('disabled', false); }, 1000);
        });

        $('#searchForm').on('keyup', function () {
            var value = $(this).val().toLowerCase();
            $('.form-item').filter(function () {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
            });
        });

        $('.form-item').on('click', function (e) {
            e.preventDefault();
            var $this = $(this);
            if ($this.hasClass('disabled') || $this.data('clicked')) return;
            $this.data('clicked', true).addClass('disabled');
            setTimeout(function () { $this.data('clicked', false).removeClass('disabled'); }, 2000);

            var formType = $this.data('form-type');
            var formTitle = $(this).find('h6').text().trim();
            var $modalPilih = $('#modalPilihForm');
            var $modalForm = $('#modalFormIsian');

            $('.modal-backdrop').not('.modal-backdrop:first').remove();

            $modalPilih.one('hidden.bs.modal', function () {
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open').css({ 'padding-right': '', 'overflow': '' });
                setTimeout(function () {
                    $('#modalFormTitle').text(formTitle);
                    $modalForm.modal('show');
                    loadFormContent(formType);
                }, 150);
            });
            $modalPilih.modal('hide');
        });

        // ─── Load Form Content ────────────────────────────────────────────
        function loadFormContent(formType) {
            $('#modalFormBody').html('<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div><p class="mt-2">Memuat form...</p></div>');
            $.ajax({
                url: 'load_form.php',
                type: 'GET',
                data: { form_type: formType },
                dataType: 'json',
                success: function (response) {
                    if (response && response.success) {
                        $('#modalFormBody').html(response.content);
                        $('#modalFormBody').find('script').each(function () {
                            var t = ($(this).attr('type') || '').toLowerCase();
                            if (t === 'text/template' || t === 'text/html' || t === 'text/x-template') return;
                            try { eval($(this).html()); } catch (e) { console.error('Script error:', e); }
                        });
                    } else {
                        $('#modalFormBody').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Gagal memuat form: ' + (response.error || 'Unknown error') + '</div>');
                    }
                },
                error: function (xhr, status, error) {
                    var errorMsg = 'Error: ' + error;
                    try {
                        var r = JSON.parse(xhr.responseText);
                        errorMsg = r.error || errorMsg;
                    } catch (e) { }
                    $('#modalFormBody').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + errorMsg + '</div>');
                }
            });
        }

        // ─── Simpan Form ──────────────────────────────────────────────────
        $('#btnSimpanForm').on('click', function () {
            var formId = '';
            if ($('#formCashOnDelivery').length) formId = '#formCashOnDelivery';
            else if ($('form', '#modalFormBody').length) formId = '#' + $('form', '#modalFormBody').first().attr('id');

            if (!formId || !$(formId).length) return;

            if (window.prepareCodSubmission && typeof window.prepareCodSubmission === 'function') {
                if (!window.prepareCodSubmission()) return;
            }

            if (!$(formId)[0].reportValidity()) return;

            submitFormAjax(formId);
        });

        function submitFormAjax(formId) {
            var isMultipart = $(formId).attr('enctype') === 'multipart/form-data';
            var formData;
            var isUpdate = false;

            if (isMultipart) {
                formData = new FormData($(formId)[0]);
                isUpdate = formData.get('action') === 'update';
            } else {
                formData = $(formId).serialize();
                isUpdate = formData.indexOf('action=update') !== -1;
            }

            var formAction = isUpdate ? 'proses_update.php' : 'proses_simpan.php';

            var ajaxSettings = {
                url: formAction,
                type: 'POST',
                data: formData,
                dataType: 'json',
                beforeSend: function () {
                    $('#btnSimpanForm').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');
                },
                success: function (response) {
                    if (response && response.success) {
                        var ticket = response.ticket;
                        var message = 'Data berhasil disimpan!';
                        if (ticket) message += '<br><strong>No. Pengajuan: ' + ticket + '</strong>';
                        showSuccess(message, 'Berhasil!');
                        setTimeout(function () {
                            $('#modalFormIsian').modal('hide');
                            setTimeout(function () {
                                table.ajax.reload(null, false);
                            }, 300);
                        }, 1500);
                    } else {
                        showError(response.message || 'Gagal menyimpan data.', 'Gagal Menyimpan');
                        $('#btnSimpanForm').prop('disabled', false).html('<i class="fas fa-save"></i> Simpan');
                    }
                },
                error: function (xhr) {
                    var errorMsg = 'Terjadi kesalahan saat menyimpan data.';
                    try {
                        var r = JSON.parse(xhr.responseText);
                        errorMsg = r.message || r.error || errorMsg;
                    } catch (e) { }
                    showError(errorMsg, 'Error');
                    $('#btnSimpanForm').prop('disabled', false).html('<i class="fas fa-save"></i> Simpan');
                }
            };

            if (isMultipart) {
                ajaxSettings.processData = false;
                ajaxSettings.contentType = false;
            }

            $.ajax(ajaxSettings);
        }

        // ─── Edit ─────────────────────────────────────────────────────────
        $(document).on('click', '.btn-edit', function () {
            var btn = $(this);
            if (btn.prop('disabled')) return;
            btn.prop('disabled', true);
            setTimeout(function () { btn.prop('disabled', false); }, 1500);

            var ticket = btn.data('ticket');
            if (!ticket) return;

            $('#modalFormTitle').text('Edit Form Pengajuan Cash On Delivery');
            $('#modalFormIsian').modal('show');
            loadEditForm(ticket);
        });

        function loadEditForm(ticket) {
            $('#modalFormBody').html('<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div><p class="mt-2">Memuat data...</p></div>');
            $.ajax({
                url: 'load_data.php', type: 'GET', data: { ticket: ticket }, dataType: 'json',
                success: function (response) {
                    if (response && response.success) {
                        loadFormContentForEdit(response.data);
                    } else {
                        $('#modalFormBody').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Gagal memuat data: ' + (response.error || 'Unknown') + '</div>');
                    }
                },
                error: function () {
                    $('#modalFormBody').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error memuat data</div>');
                }
            });
        }

        function loadFormContentForEdit(data) {
            $.ajax({
                url: 'load_form.php', type: 'GET', data: { form_type: 'cash_on_delivery', ticket: data.ticket, edit: 1 }, dataType: 'json',
                success: function (response) {
                    if (response && response.success) {
                        $('#modalFormBody').html(response.content);
                        $('#modalFormBody').find('script').each(function () {
                            var t = ($(this).attr('type') || '').toLowerCase();
                            if (t === 'text/template' || t === 'text/html' || t === 'text/x-template') return;
                            try { eval($(this).html()); } catch (e) { console.warn('Script eval error:', e); }
                        });
                        populateFormWithData(data);
                    } else {
                        $('#modalFormBody').html('<div class="alert alert-danger">Gagal memuat form edit.</div>');
                    }
                },
                error: function () {
                    $('#modalFormBody').html('<div class="alert alert-danger">Error memuat form edit.</div>');
                }
            });
        }

        function populateFormWithData(data) {
            var $form = $('#formCashOnDelivery');
            if (!$form.length) return;
            
            $.each(data, function(key, val) {
                var $input = $form.find('[name="' + key + '"]');
                if ($input.length) {
                    $input.val(val);
                }
            });

            // Restore items repeater table
            if (data.items_json) {
                try {
                    var items = typeof data.items_json === 'string' ? JSON.parse(data.items_json) : data.items_json;
                    if (window.populateCodItems) {
                        window.populateCodItems(items);
                    }
                } catch(e) {
                    console.error('Error parsing items_json:', e);
                }
            }

            $form.find('input[name="ticket"]').remove();
            $form.find('input[name="action"]').remove();
            $form.append('<input type="hidden" name="ticket" value="' + (data.ticket || '') + '">');
            $form.append('<input type="hidden" name="action" value="update">');
        }

        // ─── Delete ───────────────────────────────────────────────────────
        $(document).on('click', '.btn-delete', function () {
            var btn = $(this);
            if (btn.prop('disabled')) return;
            btn.prop('disabled', true);
            setTimeout(function () { btn.prop('disabled', false); }, 1000);

            var ticket = btn.data('ticket');
            if (!ticket) return;

            Swal.fire({
                title: 'Hapus Data?',
                html: 'Apakah Anda yakin ingin menghapus pengajuan:<br><strong>' + ticket + '</strong><br><br>Data tidak dapat dikembalikan!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33', cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal'
            }).then(function (result) {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'Menghapus...', html: 'Sedang menghapus data...', allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false, didOpen: () => { Swal.showLoading(); } });
                    $.ajax({
                        url: 'proses_delete.php', type: 'POST', data: { ticket: ticket }, dataType: 'json',
                        success: function (response) {
                            if (response && response.success) {
                                showSuccess('Data berhasil dihapus!', 'Berhasil Dihapus');
                                setTimeout(function () {
                                    table.ajax.reload(null, false);
                                }, 1500);
                            } else {
                                showError(response.message || 'Gagal menghapus data.', 'Gagal Menghapus');
                            }
                        },
                        error: function () { showError('Terjadi kesalahan saat menghapus data.', 'Error'); }
                    });
                }
            });
        });

        // ─── Modal cleanup ─────────────────────────────────────────────────
        $('#modalFormIsian').on('hidden.bs.modal', function (e) {
            if (e.target !== this) return; // Cegah bubbling dari inner modal (misal #modalPilihRekening)
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open').css({ 'padding-right': '', 'overflow': '' });
            $('#modalFormBody').html('<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div><p class="mt-2">Memuat form...</p></div>');
            $('#btnSimpanForm').prop('disabled', false).html('<i class="fas fa-save"></i> Simpan');
            $('#modalFormBody').scrollTop(0);
            $('#modalPilihRekening').remove();
        });

        $('#modalPilihForm').on('hidden.bs.modal', function (e) {
            if (e.target !== this) return;
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open').css({ 'padding-right': '', 'overflow': '' });
        });

        $(document).on('hidden.bs.modal', '.modal', function () {
            setTimeout(function () {
                if ($('.modal.show').length === 0) {
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open').css({ 'padding-right': '', 'overflow': '' });
                } else {
                    $('body').addClass('modal-open');
                }
            }, 100);
        });

        // ── Auto-refresh setiap 15 detik ──────────────────────────────────
        var refreshInterval = setInterval(function () {
            if ($('.modal.show').length === 0) {
                table.ajax.reload(null, false);
            }
        }, 15000);

        $('#modalDetailTiket').on('hidden.bs.modal', function () {
            setTimeout(function () {
                table.ajax.reload(null, false);
            }, 500);
        });

        // ─── Detail Modal ─────────────────────────────────────────────────
        $(document).on('click', '.btn-detail', function () {
            var btn = $(this);
            if (btn.prop('disabled')) return;
            btn.prop('disabled', true);
            setTimeout(function () { btn.prop('disabled', false); }, 1500);

            var ticket = btn.data('ticket');
            if (!ticket) return;

            var detailUrl = 'detail_cash_on_delivery.php?ticket=' + encodeURIComponent(ticket);

            $('#modalTicketNumber').text(ticket);
            $('#iframeDetailTiket').attr('src', detailUrl);
            $('#modalDetailTiket').modal('show');
        });

        $('#modalDetailTiket').on('hidden.bs.modal', function () {
            $('#iframeDetailTiket').attr('src', 'about:blank');
            $('#btnRejectFromModal').hide();
        });

        // ─── Reject ───────────────────────────────────────────────────────
        var ticketToReject = null;

        $(document).on('click', '#btnRejectFromModal', function () {
            ticketToReject = $('#modalTicketNumber').text();
            if (!ticketToReject) { showError('Ticket tidak ditemukan', 'Error'); return; }
            $('#rejectReasonFromList').val('');
            $('#modalRejectFromList').modal('show');
        });

        $('#btnConfirmRejectFromList').on('click', function () {
            var reason = $('#rejectReasonFromList').val().trim();
            if (!ticketToReject) { showError('Ticket tidak ditemukan', 'Error'); return; }

            $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');

            $.ajax({
                url: './proses_reject.php', type: 'POST',
                data: { ticket: ticketToReject, alasan: reason },
                dataType: 'json',
                success: function (resp) {
                    $('#btnConfirmRejectFromList').prop('disabled', false).html('Ya, Tolak Pengajuan');
                    if (resp && resp.success) {
                        $('#modalRejectFromList').modal('hide');
                        $('#modalDetailTiket').modal('hide');
                        showSuccess('Pengajuan berhasil ditolak!<br><strong>' + ticketToReject + '</strong>', 'Berhasil');
                        table.ajax.reload(null, false);
                    } else {
                        showError(resp.message || 'Gagal menolak pengajuan.', 'Error');
                    }
                },
                error: function (xhr) {
                    $('#btnConfirmRejectFromList').prop('disabled', false).html('Ya, Tolak Pengajuan');
                    var errorMsg = 'Terjadi kesalahan saat mengirim permintaan.';
                    try {
                        var r = JSON.parse(xhr.responseText);
                        errorMsg = r.message || r.error || errorMsg;
                    } catch (e) { }
                    showError(errorMsg, 'Error');
                }
            });
        });

        window.updateRejectButtonVisibility = function (canReject, ticket) {
            if (canReject) {
                $('#btnRejectFromModal').show();
                ticketToReject = ticket;
            } else {
                $('#btnRejectFromModal').hide();
            }
        };

    });
</script>
