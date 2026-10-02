<?php
session_start();

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

/// helper: ambil permission user untuk menu Asset (MenuId = 56)
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
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 134);
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}

// Hanya include header & sidebar setelah semua pengecekan selesai,
// supaya redirect tidak memicu "headers already sent".
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');


// ———————————————
// SERVER-SIDE PROCESSING SETUP
// ———————————————
// Data fetching logic moved to list_form_serverside.php
// We only need to pass initial filter values to the view if needed, 
// but for now we'll handle everything via AJAX.
$filterKategori = $_GET['kategori'] ?? '';
$filterTanggal = $_GET['tanggal'] ?? '';
?>


<style>
    /* Small spacing between action buttons to match problem_list.php */
    .action-btn {
        margin-right: 6px;
    }
    
    /* DataTables Responsive Detail Styling */
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


<div class="wrapper">

<div class="content-wrapper">

<section class="content-header">
    <div class="container-fluid">
        <h1>Form Pengajuan IT</h1>
    </div>
</section>

<section class="content">
<div class="container-fluid">

<div class="card card-primary">
    <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
        <h3 class="card-title"><i class="fas fa-list mr-1"></i>
        List Pengajuan</h3>        
                    <?php if (!empty($permissions['CanAdd']) && $permissions['CanAdd'] == 1): ?>
                        <button type="button" class="btn btn-success btn-sm float-right" id="btnTambahForm">
                            <i class="fas fa-plus"></i> Tambah Form
                        </button>
                    <?php endif; ?>
    </div>

    <div class="card-body table-responsive">
        
        <!-- Filter Section (Compact Style) -->
        <div class="filter-section p-3 mb-3" style="background-color: #f8f9fa; border-radius: 5px;">
            <div class="row">
                <div class="col-md-3">
                    <label class="small mb-1" for="filterKategori">Filter Kategori</label>
                    <select id="filterKategori" class="form-control form-control-sm">
                        <option value="">Semua Kategori</option>
                        <option value="Perangkat IT" <?php echo ($filterKategori == 'Perangkat IT') ? 'selected' : ''; ?>>Perangkat IT</option>
                        <option value="CCTV" <?php echo ($filterKategori == 'CCTV') ? 'selected' : ''; ?>>CCTV</option>
                        <option value="Akses Internet" <?php echo ($filterKategori == 'Akses Internet') ? 'selected' : ''; ?>>Akses Internet</option>
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

        <table id="tiketTable" class="table table-hover table-sm nowrap" style="width:100%">
            <thead class="thead-light">
                <tr>
                    <th>No</th>
                    <th>No Pengajuan</th>
                    <th>Kategori</th>
                    <th>Pemohon</th>
                    <th>Departemen</th>
                    <th>Tanggal</th>
                    <!-- Kolom Pengajuan & Peripheral dihapus sesuai permintaan -->
                    <th>Status</th>
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
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                <h5 class="modal-title">Detail Pengajuan : <span id="modalTicketNumber"></span></h5>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <button type="button" class="btn btn-sm btn-danger" id="btnRejectFromModal" style="display: none;">
                        <i class="fas fa-ban"></i> Tolak Pengajuan
                    </button>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>
            <div class="modal-body" style="padding:0; flex: 1 1 auto;">
                <iframe id="iframeDetailTiket" src="about:blank" style="border:0; width:100%; height:100%;"></iframe>
            </div>
        </div>
    </div>
</div>

</div>
</section>

</div>



</div>

<!-- Modal Reject Pengajuan dari List -->
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
          <label for="rejectReasonFromList"><b>Alasan Penolakan ( Opsional )</b> <span class="text-danger">*</span></label>
          <textarea class="form-control" id="rejectReasonFromList" name="reject_reason" rows="4" placeholder="Masukkan alasan penolakan..." required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-danger" id="btnConfirmRejectFromList">Ya, Tolak Pengajuan</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Pilih Form -->
<div class="modal fade" id="modalPilihForm" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
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
                        <a href="#" class="list-group-item list-group-item-action form-item d-none" data-form-type="maintenance_it">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><i class="fas fa-tools"></i> Form Maintenance IT</h6>
                            </div>
                            <p class="mb-1 small text-muted">Form untuk permintaan maintenance perangkat IT</p>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action form-item" data-form-type="pengajuan_perangkat">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><i class="fas fa-laptop"></i> Form Pengajuan Perangkat IT</h6>
                            </div>
                            <p class="mb-1 small text-muted">Form untuk pengajuan perangkat IT baru (Komputer, Laptop, dll)</p>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action form-item d-none" data-form-type="pengajuan_product_ga">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><i class="fas fa-box"></i> Form Pengajuan Product ke GA</h6>
                            </div>
                            <p class="mb-1 small text-muted">Form untuk pengajuan product ke General Affairs</p>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action form-item" data-form-type="pengajuan_cctv">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><i class="fas fa-video"></i> Form Pengajuan Rekaman CCTV</h6>
                            </div>
                            <p class="mb-1 small text-muted">Form untuk permintaan rekaman CCTV sesuai periode waktu</p>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action form-item" data-form-type="pengajuan_akses_internet">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><i class="fas fa-wifi"></i> Form Pengajuan Akses Internet</h6>
                            </div>
                            <p class="mb-1 small text-muted">Form untuk permintaan akses internet (Temporary/Permanent)</p>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action form-item" data-form-type="pengajuan_email_account">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><i class="fas fa-envelope"></i> Form Pengajuan Email Account</h6>
                            </div>
                            <p class="mb-1 small text-muted">Form untuk permintaan pembuatan email account baru</p>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action form-item" data-form-type="grant_revoke_trustee">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><i class="fas fa-key"></i> Form Pengajuan Grant/Revoke Trustee</h6>
                            </div>
                            <p class="mb-1 small text-muted">Form untuk permintaan Grant atau Revoke akses menu ERP</p>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action form-item" data-form-type="perubahan_data_database">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><i class="fas fa-database"></i> Form Pengajuan Perubahan Data Via Database</h6>
                            </div>
                            <p class="mb-1 small text-muted">Form untuk permintaan perubahan data via database (Add/Edit/Delete)</p>
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
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document" style="max-height: 90vh; margin: 1.75rem auto;">
        <div class="modal-content" style="max-height: 90vh; display: flex; flex-direction: column;">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor);?> text-white" style="flex-shrink: 0;">
                <h5 class="modal-title" id="modalFormTitle">Form</h5>
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
<!-- DataTables JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<!-- SweetAlert -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<!-- ===================================================
    12. JAVASCRIPT CUSTOM
======================================================= -->
<script>
$(document).ready(function() {
    // Initialize DataTable with Server-Side Processing
    var table = $('#tiketTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'list_form_serverside.php',
            type: 'POST',
            data: function(d) {
                d.kategori = $('#filterKategori').val();
                d.tanggal = $('#filterTanggal').val();
                d.search_value = d.search.value;
            },
            error: function(xhr, error, thrown) {
                console.error('DataTables Error:', xhr, error, thrown);
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
                render: function (data, type, row, meta) {
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
        order: [[5, 'desc']], // Sort by tgl_pengajuan (index 5) by default
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

    // Auto Filter Action (Reload Table)
    $('#filterKategori, #filterTanggal').on('change', function() {
        table.ajax.reload();
    });

    // Reset Button Action
    $('#btnReset').on('click', function() {
        $('#filterKategori').val('');
        $('#filterTanggal').val('');
        table.ajax.reload();
    });

    // Export PDF Action
    $('#btnExportPdf').on('click', function() {
        var kategori = $('#filterKategori').val();
        var tanggal = $('#filterTanggal').val();
        
        var url = 'export_pdf_list.php?';
        var params = [];
        
        if (kategori) {
            params.push('kategori=' + encodeURIComponent(kategori));
        }
        if (tanggal) {
            params.push('tanggal=' + encodeURIComponent(tanggal));
        }
        
        window.open(url + params.join('&'), '_blank');
    });

    // SweetAlert Toast notification helper
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        },
        showClass: {
            popup: 'animated fadeInRight'
        },
        hideClass: {
            popup: 'animated fadeOutRight'
        }
    });

    // Success notification function
    function showSuccess(message, title = 'Sukses!') {
        Swal.fire({
            icon: 'success',
            title: title,
            html: message, // Use html instead of text to render HTML tags
            timer: 3000,
            timerProgressBar: true,
            showConfirmButton: true,
            confirmButtonColor: '#28a745',
            showClass: {
                popup: 'animated zoomIn'
            },
            hideClass: {
                popup: 'animated zoomOut'
            }
        });
    }

    // Error notification function
    function showError(message, title = 'Error!') {
        Swal.fire({
            icon: 'error',
            title: title,
            html: message, // Use html instead of text to render HTML tags
            timer: 4000,
            timerProgressBar: true,
            showConfirmButton: true,
            confirmButtonColor: '#dc3545',
            showClass: {
                popup: 'animated shake'
            },
            hideClass: {
                popup: 'animated fadeOut'
            }
        });
    }

    // Warning notification function
    function showWarning(message, title = 'Peringatan!') {
        Swal.fire({
            icon: 'warning',
            title: title,
            html: message, // Use html instead of text to render HTML tags
            timer: 3500,
            timerProgressBar: true,
            showConfirmButton: true,
            confirmButtonColor: '#ffc107',
            showClass: {
                popup: 'animated wobble'
            },
            hideClass: {
                popup: 'animated fadeOut'
            }
        });
    }

    // Info toast notification
    function showToast(message, icon = 'info') {
        Toast.fire({
            icon: icon,
            title: message
        });
    }

    // Handle "Tambah Form" button with debounce
    $('#btnTambahForm').on('click', function() {
        var btn = $(this);
        if (btn.prop('disabled')) return;

        btn.prop('disabled', true);
        
        // Show modal
        $('#modalPilihForm').modal('show');

        // Re-enable after 1 second
        setTimeout(function() {
            btn.prop('disabled', false);
        }, 1000);
    });

    // Search form functionality
    $('#searchForm').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $('.form-item').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });

    // Handle form selection
    $('.form-item').on('click', function(e) {
        e.preventDefault();
        var $this = $(this);
        
        // Prevent multiple clicks
        if ($this.hasClass('disabled') || $this.data('clicked')) return;
        $this.data('clicked', true).addClass('disabled');
        
        // Re-enable after 2 seconds (enough time for modal transition)
        setTimeout(function() {
            $this.data('clicked', false).removeClass('disabled');
        }, 2000);

        var formType = $this.data('form-type');
        var formTitle = $(this).find('h6').text();
        var $modalPilih = $('#modalPilihForm');
        var $modalForm = $('#modalFormIsian');
        
        // Clean up any existing backdrops first
        $('.modal-backdrop').not('.modal-backdrop:first').remove();
        
        // Close modal pilih form and wait for it to be hidden
        $modalPilih.one('hidden.bs.modal', function() {
            // Remove all backdrop overlays
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open');
            $('body').css({
                'padding-right': '',
                'overflow': ''
            });
            
            // Small delay to ensure cleanup is complete
            setTimeout(function() {
                // Set form title
                $('#modalFormTitle').text(formTitle);
                
                // Show form modal
                $modalForm.modal('show');
                
                // Load form content via AJAX
                loadFormContent(formType);
            }, 150);
        });
        
        $modalPilih.modal('hide');
    });

    // Clean up hidden form items so list-group styling remains tidy
    // Remove any .form-item elements with the d-none class so the first visible item has correct rounded corners
    (function tidyFormList(){
        $('#formList .list-group .form-item.d-none').remove();
    })();

    // Function to load form content
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
                    // Execute any scripts in the loaded content
                    var scripts = $('#modalFormBody').find('script');
                    scripts.each(function() {
                        try {
                            eval($(this).html());
                        } catch (e) {
                            console.error('Error executing script:', e);
                        }
                    });
                } else {
                    $('#modalFormBody').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> Gagal memuat form: ${response.error || 'Unknown error'}
                        </div>
                    `);
                }
            },
            error: function(xhr, status, error) {
                var errorMsg = 'Error: ' + error;
                try {
                    // Try to parse error response as JSON
                    var errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.error) {
                        errorMsg = errorResponse.error;
                    }
                } catch (e) {
                    // If response is not JSON, check if it's HTML
                    if (xhr.responseText && xhr.responseText.trim().startsWith('<')) {
                        errorMsg = 'Server mengembalikan HTML instead of JSON. Kemungkinan ada error PHP.';
                    } else if (xhr.responseText) {
                        errorMsg = xhr.responseText.substring(0, 200);
                    }
                }
                $('#modalFormBody').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> ${errorMsg}
                        <br><small>Status: ${xhr.status} ${xhr.statusText}</small>
                    </div>
                `);
            }
        });
    }

    // Handle form submission
    $('#btnSimpanForm').on('click', function() {
        var formId = '';
        var formAction = 'proses_simpan.php';
        
        // Determine which form is active
        if ($('#formPengajuanPerangkat').length) {
            formId = '#formPengajuanPerangkat';
        } else if ($('#formMaintenanceIT').length) {
            formId = '#formMaintenanceIT';
        } else if ($('#formPengajuanProductGA').length) {
            formId = '#formPengajuanProductGA';
        } else if ($('#formPengajuanCCTV').length) {
            formId = '#formPengajuanCCTV';
        } else if ($('#formPengajuanAksesInternet').length) {
            formId = '#formPengajuanAksesInternet';
        } else if ($('#formPengajuanEmailAccount').length) {
            formId = '#formPengajuanEmailAccount';
        } else if ($('#formGrantRevokeTrustee').length) {
            formId = '#formGrantRevokeTrustee';
        } else if ($('#formPerubahanDataDatabase').length) {
            formId = '#formPerubahanDataDatabase';
        }
        
        if (formId && $(formId).length) {
            // Validate form and show validation UI
            if (!$(formId)[0].reportValidity()) {
                // Form is invalid, reportValidity() already showed the validation message
                return;
            }
            
            // Form is valid, proceed with submission
            // For Pengajuan Perangkat, CCTV, and Internet Access, check TTD first (unless it was just saved)
            if ((formId === '#formPengajuanPerangkat' || formId === '#formPengajuanCCTV' || formId === '#formPengajuanAksesInternet' || formId === '#formPengajuanEmailAccount' || formId === '#formGrantRevokeTrustee' || formId === '#formPerubahanDataDatabase') && typeof window.validateTTDBeforeSubmit === 'function') {
                // Check if this is a re-submit after TTD was saved
                var skipTTDCheck = window._skipTTDCheckOnce;
                if (skipTTDCheck) {
                    window._skipTTDCheckOnce = false;
                    submitFormAjax(formId);
                    return;
                }
                
                window.validateTTDBeforeSubmit().then(function(canSubmit) {
                    if (canSubmit) {
                        submitFormAjax(formId);
                    }
                    // If not canSubmit, the modal will be shown and user can save TTD
                    // After TTD is saved, _skipTTDCheckOnce will be set to true and form will be submitted
                }).catch(function(err) {
                    console.error('TTD validation error:', err);
                    submitFormAjax(formId);
                });
                return; // Exit, TTD check will handle submission
            }
            
            // For other forms, submit directly
            submitFormAjax(formId);
        }
    });
    
    // Helper function to submit form via AJAX
    function submitFormAjax(formId) {
        var formAction = 'proses_simpan.php';
        var formData = $(formId).serialize();
        var isUpdate = formData.indexOf('action=update') !== -1;
        // Routing update khusus untuk email account
        if (isUpdate && formId === '#formPengajuanEmailAccount') {
            formAction = 'proses_update_email_account.php';
        } else {
            formAction = isUpdate ? 'proses_update.php' : 'proses_simpan.php';
        }
        
        // Submit form via AJAX
        $.ajax({
            url: formAction,
            type: 'POST',
            data: formData,
            dataType: 'json',
            beforeSend: function() {
                $('#btnSimpanForm').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');
            },
            success: function(response) {
                // Handle JSON response
                if (response && response.success) {
                    // Check if this is a Pengajuan Perangkat, CCTV, or Internet Access form and we need to save TTD
                    var ticket = response.ticket;
                    var formId = $('#formPengajuanPerangkat').length ? '#formPengajuanPerangkat' : 
                                 ($('#formPengajuanCCTV').length ? '#formPengajuanCCTV' : 
                                 ($('#formPengajuanAksesInternet').length ? '#formPengajuanAksesInternet' : 
                                 ($('#formPengajuanEmailAccount').length ? '#formPengajuanEmailAccount' : 
                                 ($('#formGrantRevokeTrustee').length ? '#formGrantRevokeTrustee' : 
                                 ($('#formPerubahanDataDatabase').length ? '#formPerubahanDataDatabase' : null)))));
                    
                    if (formId && ticket && window.saveTTDWithTicket) {
                        // Save TTD after form is saved
                        window.saveTTDWithTicket(ticket).then(function(ttdSaved) {
                            showFormSaveSuccess(response, ticket);
                        }).catch(function(err) {
                            // Even if TTD save fails, form was already saved, so show success
                            showFormSaveSuccess(response, ticket);
                        });
                    } else {
                        showFormSaveSuccess(response, ticket);
                    }
                } else {
                    showError(response.message || 'Gagal menyimpan data. Silakan coba lagi.', 'Gagal Menyimpan');
                    $('#btnSimpanForm').prop('disabled', false).html('<i class="fas fa-save"></i> Simpan');
                }
            },
            error: function(xhr, status, error) {
                // Try to parse error response as JSON
                var errorMsg = 'Terjadi kesalahan saat menyimpan data.';
                try {
                    var errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.message) {
                        errorMsg = errorResponse.message;
                    } else if (errorResponse.error) {
                        errorMsg = errorResponse.error;
                    }
                } catch (e) {
                    // If not JSON, use default message
                    if (xhr.responseText) {
                        errorMsg = xhr.responseText.substring(0, 200);
                    }
                }
                showError(errorMsg, 'Error Menyimpan Data');
                $('#btnSimpanForm').prop('disabled', false).html('<i class="fas fa-save"></i> Simpan');
            }
        });
    }
    
    // Helper function to show form save success
    function showFormSaveSuccess(response, ticket) {
        var message = 'Data berhasil ' + (response.message ? response.message : 'disimpan') + '!';
        if (ticket) {
            message += '<br><strong>No. Pengajuan: ' + ticket + '</strong>';
        }
        showSuccess(message, 'Berhasil!');
        
        // Close modal after a short delay
        setTimeout(function() {
            $('#modalFormIsian').modal('hide');
            setTimeout(function() {
                location.reload(); // Reload page to show new data
            }, 300);
        }, 1500);
    }

    // Handle Edit button click
    $(document).on('click', '.btn-edit', function() {
        var btn = $(this);
        if (btn.prop('disabled')) return;
        
        // Disable temporarily
        btn.prop('disabled', true);
        setTimeout(function() { btn.prop('disabled', false); }, 1500);

        var ticket = btn.data('ticket');
        if (!ticket) return;
        
        // Set form title
        $('#modalFormTitle').text('Edit Form Pengajuan IT');
        
        // Show form modal
        $('#modalFormIsian').modal('show');
        
        // Load data and form
        loadEditForm(ticket);
    });
    
    // Function to load edit form with data (supports Perangkat & CCTV)
    function loadEditForm(ticket) {
        $('#modalFormBody').html(`
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
                <p class="mt-2">Memuat data...</p>
            </div>
        `);
        
        // Load data first
        $.ajax({
            url: 'load_data.php',
            type: 'GET',
            data: { ticket: ticket },
            dataType: 'json',
            success: function(response) {
                if (response && response.success) {
                    var formType = response.isEmail ? 'pengajuan_email_account' :
                                   (response.isInternet ? 'pengajuan_akses_internet' : 
                                   (response.isCCTV ? 'pengajuan_cctv' : 
                                   (response.isGrantRevoke ? 'grant_revoke_trustee' : 
                                   (response.isDatabase ? 'perubahan_data_database' : 'pengajuan_perangkat'))));
                    loadFormContentForEdit(response.data, formType);
                } else {
                    $('#modalFormBody').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> Gagal memuat data: ${response.error || 'Unknown error'}
                        </div>
                    `);
                }
            },
            error: function(xhr, status, error) {
                var errorMsg = 'Error: ' + error;
                try {
                    var errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.error) {
                        errorMsg = errorResponse.error;
                    }
                } catch (e) {
                    if (xhr.responseText && xhr.responseText.trim().startsWith('<')) {
                        errorMsg = 'Server mengembalikan HTML instead of JSON. Kemungkinan ada error PHP.';
                    }
                }
                $('#modalFormBody').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> ${errorMsg}
                    </div>
                `);
            }
        });
    }
    
    // Function to load form content for edit
    function loadFormContentForEdit(data, formType) {
        
        $.ajax({
            url: 'load_form.php',
            type: 'GET',
            data: { form_type: formType, ticket: data.ticket, edit: 1 },
            dataType: 'json',
            success: function(response) {
                if (response && response.success) {
                    $('#modalFormBody').html(response.content);
                    
                    // Execute scripts FIRST so event handlers (change on checkboxes) are bound
                    var scripts = $('#modalFormBody').find('script');
                    scripts.each(function() {
                        try {
                            eval($(this).html());
                        } catch (e) {
                            console.error('Error executing script:', e);
                        }
                    });

                    // After handlers are bound, populate form
                    populateFormWithData(data, formType);
                } else {
                    $('#modalFormBody').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> Gagal memuat form: ${response.error || 'Unknown error'}
                        </div>
                    `);
                }
            },
            error: function() {
                $('#modalFormBody').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> Error memuat form
                    </div>
                `);
            }
        });
    }
    
    // Function to populate form with data
    function populateFormWithData(data, formType) {
                if (formType === 'perubahan_data_database') {
            $('input[name="nama_pemohon"]').val(data.nama_pemohon || '');
            $('input[name="jabatan"]').val(data.jabatan || '');
            $('input[name="departemen"]').val(data.departemen || '');
            $('input[name="bagian"]').val(data.bagian || ''); // Ensure this field exists in form
            
            // Handle date
            if (data.tgl_pengajuan) {
               var dateStr = data.tgl_pengajuan;
               if (dateStr.includes('T')) dateStr = dateStr.split('T')[0];
               // If form expects YYYY-MM-DD (input type date) or DD-MM-YYYY (text)
               // The form uses input type="date" usually but let's check. 
               // Actually the generic date handler at bottom of function handles .tgl-pengajuan class.
               // But let's set it here if specific name used.
               $('input[name="tgl_pengajuan"]').val(dateStr);
            }

            // Checkboxes
            $('#reqAdd').prop('checked', !!data.req_add);
            $('#reqEdit').prop('checked', !!data.req_edit);
            $('#reqDelete').prop('checked', !!data.req_delete);
            
            $('#appProint').prop('checked', !!data.app_proint);
            $('#appHris').prop('checked', !!data.app_hris);
            $('#appLainnya').prop('checked', !!data.app_lainnya);
            
            if (data.app_lainnya) {
                $('input[name="app_lainnya_text"]').val(data.app_lainnya_text || '').prop('disabled', false).removeClass('d-none');
            } else {
                 $('input[name="app_lainnya_text"]').val('').prop('disabled', true).addClass('d-none');
            }

            $('textarea[name="perubahan"]').val(data.perubahan || '');
            $('textarea[name="keterangan"]').val(data.keterangan || '');
            return;
        }

        if (formType === 'pengajuan_email_account') {
                    $('input[name="nama_pemohon"]').val(data.nama_pemohon || '');
                    $('input[name="jabatan"]').val(data.jabatan || '');
                    $('input[name="departemen"]').val(data.departemen || '');
                    $('input[name="area"]').val(data.area || '');
                    $('input[name="tgl_pengajuan"]').val(data.tgl_pengajuan || '');
                    $('input[name="email_user"]').val(data.email_account || '');
                    $('textarea[name="keterangan"]').val(data.keterangan || '');
                    // Checkbox fields
                    $('#requestEmailAccount').prop('checked', !!data.request_email_account);
                    $('#requestChangeAccess').prop('checked', !!data.request_change_access);
                    $('#requestSettingDevice').prop('checked', !!data.request_setting_device).trigger('change');
                    $('#deviceAndroid').prop('checked', !!data.device_android);
                    $('#deviceIOS').prop('checked', !!data.device_ios);
                    $('#deviceLainnya').prop('checked', !!data.device_lainnya);
                    $('input[name="device_lainnya_text"]').val(data.device_lainnya_text || '');
                    $('#aksesLocal').prop('checked', !!data.akses_email_local);
                    $('#aksesGlobal').prop('checked', !!data.akses_email_global).trigger('change');
                    $('#globalKirim').prop('checked', !!data.global_kirim);
                    $('#globalTerima').prop('checked', !!data.global_terima);
                    $('#globalFull').prop('checked', !!data.global_full);
                    return;
                }
        // Populate basic fields
        if (formType === 'pengajuan_cctv') {
            $('input[name="nama_pemohon"]').val(data.nama_pemohon || '');
            $('input[name="jabatan"]').val(data.jabatan || '');
            $('input[name="departemen"]').val(data.departemen || '');
            $('input[name="area"]').val(data.area || '');
            $('textarea[name="keterangan"]').val(data.keterangan || '');
        } else if (formType === 'pengajuan_akses_internet') {
            console.log('Populating akses internet form with data:', data);
            
            $('input[name="nama_pemohon"]').val(data.nama_pemohon || '');
            $('input[name="jabatan"]').val(data.jabatan || '');
            $('input[name="departemen"]').val(data.departemen || '');
            $('input[name="area"]').val(data.area || '');
            $('textarea[name="keterangan"]').val(data.keterangan || '');
            
            // 1. Populate "Akses Internet" Checkbox & Section
            if (data.request_akses_internet == 1 || data.request_akses_internet === '1' || data.request_akses_internet === true) {
                $('#requestAksesInternet').prop('checked', true);
                // show section and trigger change so form handlers run
                $('#aksesInternetSection').show();
                
                // Populate Akses Type (Temporary/Permanent)
                if (data.akses_type) {
                    $('input[name="akses_type"][value="' + data.akses_type + '"]').prop('checked', true);
                    
                    // If Temporary, show duration and populate dates
                    if (data.akses_type === 'Temporary') {
                        $('#temporaryDuration').show();
                        
                        if (data.akses_temporary_from) {
                            // Format date to YYYY-MM-DD for input type="date"
                            var dateFrom = data.akses_temporary_from;
                            if (dateFrom.includes('T')) dateFrom = dateFrom.split('T')[0];
                            // Handle DD-MM-YYYY if present
                            if (dateFrom.match(/^\d{2}-\d{2}-\d{4}$/)) {
                                var parts = dateFrom.split('-');
                                dateFrom = parts[2] + '-' + parts[1] + '-' + parts[0];
                            }
                            $('input[name="akses_temporary_from"]').val(dateFrom);
                        }
                        
                        if (data.akses_temporary_to) {
                            var dateTo = data.akses_temporary_to;
                            if (dateTo.includes('T')) dateTo = dateTo.split('T')[0];
                            if (dateTo.match(/^\d{2}-\d{2}-\d{4}$/)) {
                                var parts = dateTo.split('-');
                                dateTo = parts[2] + '-' + parts[1] + '-' + parts[0];
                            }
                            $('input[name="akses_temporary_to"]').val(dateTo);
                        }
                    }
                }
                // trigger changes so UI toggles run
                $('input[name="akses_type"]').trigger('change');
                $('#requestAksesInternet').trigger('change');
            }
            
            // 2. Populate "Penambahan Bandwidth" Checkbox & Section
            if (data.request_tambah_bandwidth == 1 || data.request_tambah_bandwidth === '1' || data.request_tambah_bandwidth === true) {
                $('#requestTambahBandwidth').prop('checked', true);
                // show section and let handlers handle inner visibility
                $('#bandwidthSection').show();
                
                // Populate Bandwidth Type
                if (data.bandwidth_type) {
                    $('input[name="bandwidth_type"][value="' + data.bandwidth_type + '"]').prop('checked', true);
                    
                    if (data.bandwidth_type === 'Temporary') {
                        $('#bandwidthTemporaryDuration').show();
                        
                        if (data.bandwidth_temporary_from) {
                            var dateFrom = data.bandwidth_temporary_from;
                            if (dateFrom.includes('T')) dateFrom = dateFrom.split('T')[0];
                            if (dateFrom.match(/^\d{2}-\d{2}-\d{4}$/)) {
                                var parts = dateFrom.split('-');
                                dateFrom = parts[2] + '-' + parts[1] + '-' + parts[0];
                            }
                            $('input[name="bandwidth_temporary_from"]').val(dateFrom);
                        }
                        
                        if (data.bandwidth_temporary_to) {
                            var dateTo = data.bandwidth_temporary_to;
                            if (dateTo.includes('T')) dateTo = dateTo.split('T')[0];
                            if (dateTo.match(/^\d{2}-\d{2}-\d{4}$/)) {
                                var parts = dateTo.split('-');
                                dateTo = parts[2] + '-' + parts[1] + '-' + parts[0];
                            }
                            $('input[name="bandwidth_temporary_to"]').val(dateTo);
                        }
                    }
                }
                
                // Populate Amount & Unit
                if (data.tambah_bandwidth) {
                    // there are two inputs with same name (temp and perm wrappers)
                    // set both values so whichever becomes visible has the value
                    var tbInputs = $('input[name="tambah_bandwidth"]');
                    tbInputs.val(data.tambah_bandwidth);
                }
                if (data.tambah_bandwidth_unit) {
                    $('select[name="tambah_bandwidth_unit"]').val(data.tambah_bandwidth_unit);
                }
                // ensure correct jumlah wrapper shown depending on selected type
                var bwTypeSel = data.bandwidth_type || $('input[name="bandwidth_type"]:checked').val();
                if (bwTypeSel === 'Temporary') {
                    $('#bwJumlahWrapper').show(); $('#bwJumlahWrapperPerm').hide();
                    // enable inputs in shown wrapper, disable hidden duplicates
                    $('#bwJumlahWrapper').find('input[name="tambah_bandwidth"]').prop('disabled', false).prop('required', true);
                    $('#bwJumlahWrapperPerm').find('input[name="tambah_bandwidth"]').prop('disabled', true).prop('required', false).val('');
                    $('#bwJumlahWrapper').find('select[name="tambah_bandwidth_unit"]').prop('disabled', false);
                    $('#bwJumlahWrapperPerm').find('select[name="tambah_bandwidth_unit"]').prop('disabled', true).val('Mbps');
                } else if (bwTypeSel === 'Permanent') {
                    $('#bwJumlahWrapperPerm').show(); $('#bwJumlahWrapper').hide();
                    $('#bwJumlahWrapperPerm').find('input[name="tambah_bandwidth"]').prop('disabled', false).prop('required', true);
                    $('#bwJumlahWrapper').find('input[name="tambah_bandwidth"]').prop('disabled', true).prop('required', false).val('');
                    $('#bwJumlahWrapperPerm').find('select[name="tambah_bandwidth_unit"]').prop('disabled', false);
                    $('#bwJumlahWrapper').find('select[name="tambah_bandwidth_unit"]').prop('disabled', true).val('Mbps');
                } else { $('#bwJumlahWrapper, #bwJumlahWrapperPerm').hide(); }

                // trigger changes so form handlers run
                $('input[name="bandwidth_type"]').trigger('change');
                $('#requestTambahBandwidth').trigger('change');
            }
        } else {
            $('input[name="nama_pemohon"]').val(data.nama_pemohon || '');
            $('input[name="jabatan"]').val(data.jabatan || '');
            $('input[name="departemen"]').val(data.departemen || '');
            $('input[name="bagian"]').val(data.bagian || '');
            $('textarea[name="spesifikasi"]').val(data.spesifikasi || '');
            $('textarea[name="keterangan"]').val(data.keterangan || '');
        }
        
        // Format and set date
        if (data.tgl_pengajuan) {
            var dateStr = data.tgl_pengajuan;
            if (dateStr.includes('T')) {
                dateStr = dateStr.split('T')[0];
            }
            // Convert YYYY-MM-DD to DD-MM-YYYY
            var parts = dateStr.split('-');
            if (parts.length === 3) {
                dateStr = parts[2] + '-' + parts[1] + '-' + parts[0];
            }
            $('.tgl-pengajuan').val(dateStr);
        }

        // CCTV specific date/time fields
        if (formType === 'pengajuan_cctv') {
            // ... (existing cctv logic) ...
            if (data.tanggal_1) {
                var date1Str = data.tanggal_1;
                if (date1Str.includes('T')) {
                    date1Str = date1Str.split('T')[0];
                }
                $('input[name="tanggal_1"]').val(date1Str);
            }
            if (data.tanggal_2) {
                var date2Str = data.tanggal_2;
                if (date2Str.includes('T')) {
                    date2Str = date2Str.split('T')[0];
                }
                $('input[name="tanggal_2"]').val(date2Str);
            }
            $('input[name="jam_mulai_1"]').val(data.jam_mulai_1 || '');
            $('input[name="jam_selesai_1"]').val(data.jam_selesai_1 || '');
            $('input[name="jam_mulai_2"]').val(data.jam_mulai_2 || '');
            $('input[name="jam_selesai_2"]').val(data.jam_selesai_2 || '');
        }

        // Grant/Revoke specific fields
        if (formType === 'grant_revoke_trustee') {
            // Populate Jenis Permintaan
            if (data.jenis_permintaan) {
                var $radio = $('input[name="jenis_permintaan"][value="' + data.jenis_permintaan + '"]');
                $radio.prop('checked', true);
            }
            
            // Populate Menu Akses (Dynamic Rows)
            var menuContainer = $('#menuListContainer');
            
            menuContainer.empty(); // Clear existing rows
            
            if (data.menu_akses) {
                var menus = data.menu_akses.split('\n');
                
                menus.forEach(function(menu, index) {
                    if (menu.trim() !== '') {
                        var btnClass = index === 0 ? 'btn-outline-success btn-add-menu' : 'btn-outline-danger btn-remove-menu';
                        var iconClass = index === 0 ? 'fa-plus' : 'fa-minus';
                        
                        var newRow = `
                        <div class="input-group mb-2 menu-item-row">
                            <input type="text" class="form-control form-control-sm" name="menu_akses[]" placeholder="Nama Menu ERP..." value="${menu.trim()}" required>
                            <div class="input-group-append">
                                <button class="btn ${btnClass} btn-sm" type="button"><i class="fas ${iconClass}"></i></button>
                            </div>
                        </div>`;
                        menuContainer.append(newRow);
                    }
                });
            } else {
                 // If empty, add default row
                var newRow = `
                <div class="input-group mb-2 menu-item-row">
                    <input type="text" class="form-control form-control-sm" name="menu_akses[]" placeholder="Nama Menu ERP..." required>
                    <div class="input-group-append">
                        <button class="btn btn-outline-success btn-sm btn-add-menu" type="button"><i class="fas fa-plus"></i></button>
                    </div>
                </div>`;
                menuContainer.append(newRow);
            }
            
             // Populate Area (since it's not in base fields)
            if (data.area) {
                 $('input[name="area"]').val(data.area);
            }
        }


        // Reset visibility & values for qty/ket boxes
        $('.qty-box').addClass('d-none').val('');
        $('.ket-box').addClass('d-none').val('');

        // Reset checked state, akan di-set ulang sesuai data
        $('input[name="pengajuan[]"]').prop('checked', false);
        $('input[name="peripheral[]"]').prop('checked', false);

        // Populate pengajuan checkboxes (only for perangkat IT)
        if (formType !== 'pengajuan_cctv' && data.pengajuan) {
            var pengajuanItems = data.pengajuan.split(',');
            pengajuanItems.forEach(function(item) {
                item = item.trim();
                if (!item) return;

                var checkbox = $('input[name="pengajuan[]"][value="' + item + '"]');
                if (checkbox.length) {
                    checkbox.prop('checked', true);

                    var row = checkbox.closest('.form-check');
                    var id = item.toLowerCase().replace(/\s+/g, '_');

                    // Qty box
                    var qtyBox = row.find('input[name="qty_' + id + '"]');
                    if (qtyBox.length) {
                        qtyBox.removeClass('d-none');
                        if (typeof data['qty_' + id] !== 'undefined') {
                            qtyBox.val(data['qty_' + id]);
                        }
                    }

                    // Keterangan lainnya
                    if (item === 'Lainnya') {
                        var ketBox = row.find('input[name="ket_' + id + '"]');
                        if (ketBox.length) {
                            ketBox.removeClass('d-none');
                            if (data.ket_lainnya) {
                                ketBox.val(data.ket_lainnya);
                            }
                        }
                    }
                }
            });
        }

        // Populate peripheral checkboxes (only for perangkat IT)
        if (formType !== 'pengajuan_cctv' && data.peripheral) {
            var peripheralItems = data.peripheral.split(',');
            peripheralItems.forEach(function(item) {
                item = item.trim();
                if (!item) return;

                var checkbox = $('input[name="peripheral[]"][value="' + item + '"]');
                if (checkbox.length) {
                    checkbox.prop('checked', true);

                    var row = checkbox.closest('.form-check');
                    var id = item.toLowerCase().replace(/\s+/g, '_');

                    // Qty box
                    var qtyBox = row.find('input[name="qty_perip_' + id + '"]');
                    if (qtyBox.length) {
                        qtyBox.removeClass('d-none');
                        if (typeof data['qty_perip_' + id] !== 'undefined') {
                            qtyBox.val(data['qty_perip_' + id]);
                        }
                    }

                    // Keterangan peripheral lainnya
                    if (item === 'Lainnya') {
                        var ketBox = row.find('input[name="ket_perip_' + id + '"]');
                        if (ketBox.length) {
                            ketBox.removeClass('d-none');
                            if (data.ket_perip_lainnya) {
                                ketBox.val(data.ket_perip_lainnya);
                            }
                        }
                    }
                }
            });
        }

        // Ensure hidden field for update mode is unique
        // Ensure hidden field for update mode only in active form
        var activeFormSelector = formType === 'pengajuan_akses_internet' ? '#formPengajuanAksesInternet' :
                                 (formType === 'pengajuan_cctv' ? '#formPengajuanCCTV' : 
                                 (formType === 'grant_revoke_trustee' ? '#formGrantRevokeTrustee' : 
                                 (formType === 'perubahan_data_database' ? '#formPerubahanDataDatabase' : '#formPengajuanPerangkat')));
        var $activeForm = $(activeFormSelector);
        if ($activeForm.length) {
            $activeForm.find('input[name="ticket"]').remove();
            $activeForm.find('input[name="action"]').remove();
            $activeForm.append('<input type="hidden" name="ticket" value="' + (data.ticket || '') + '">');
            $activeForm.append('<input type="hidden" name="action" value="update">');
        }
    }
    
    // Handle Delete button click
    $(document).on('click', '.btn-delete', function() {
        var btn = $(this);
        if (btn.prop('disabled')) return;
        
        // Disable temporarily
        btn.prop('disabled', true);
        setTimeout(function() { btn.prop('disabled', false); }, 1000);

        var ticket = btn.data('ticket');
        if (!ticket) return;
        
        Swal.fire({
            title: 'Hapus Data?',
            html: 'Apakah Anda yakin ingin menghapus pengajuan dengan nomor:<br><strong>' + ticket + '</strong><br><br>Data yang dihapus tidak dapat dikembalikan!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
            showClass: {
                popup: 'animated zoomIn'
            },
            hideClass: {
                popup: 'animated zoomOut'
            }
        }).then(function(result) {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Menghapus...',
                    html: 'Sedang menghapus data...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Delete via AJAX
                $.ajax({
                    url: 'proses_delete.php',
                    type: 'POST',
                    data: { ticket: ticket },
                    dataType: 'json',
                    success: function(response) {
                        if (response && response.success) {
                            showSuccess('Data berhasil dihapus!', 'Berhasil Dihapus');
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            showError(response.message || 'Gagal menghapus data.', 'Gagal Menghapus');
                        }
                    },
                    error: function(xhr) {
                        var errorMsg = 'Terjadi kesalahan saat menghapus data.';
                        try {
                            var errorResponse = JSON.parse(xhr.responseText);
                            if (errorResponse.message) {
                                errorMsg = errorResponse.message;
                            }
                        } catch (e) {
                            if (xhr.responseText) {
                                errorMsg = xhr.responseText.substring(0, 200);
                            }
                        }
                        showError(errorMsg, 'Error Menghapus Data');
                    }
                });
            }
        });
    });

    // Reset form modal when closed
    $('#modalFormIsian').on('hidden.bs.modal', function() {
        // Clean up any remaining backdrop
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open');
        $('body').css({
            'padding-right': '',
            'overflow': ''
        });
        
        $('#modalFormBody').html(`
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
                <p class="mt-2">Memuat form...</p>
            </div>
        `);
        $('#btnSimpanForm').prop('disabled', false).html('<i class="fas fa-save"></i> Simpan');
        
        // Reset scroll position
        $('#modalFormBody').scrollTop(0);
    });
    
    // Clean up modal pilih form when closed
    $('#modalPilihForm').on('hidden.bs.modal', function() {
        // Clean up any remaining backdrop
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open');
        $('body').css({
            'padding-right': '',
            'overflow': ''
        });
    });
    
    // Update status berdasarkan jumlah TTD
    function updateStatusBadge(ticket, row) {
        $.ajax({
            url: 'get_ttd_count.php?ticket=' + encodeURIComponent(ticket),
            method: 'GET',
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.success) {
                    var statusText = resp.status;
                    var badgeHtml = '';
                    var count = resp.count || 0;
                    var required = resp.required || (ticket.startsWith('CCTV-') ? 4 : 5);

                    if (statusText === 'Ditolak') {
                        badgeHtml = '<span class="badge badge-danger">' + statusText + '</span>';
                    } else if (count === 0) {
                        badgeHtml = '<span class="badge badge-warning">' + statusText + '</span>';
                    } else if (count >= required) {
                        badgeHtml = '<span class="badge badge-success">' + statusText + '</span>';
                    } else {
                        badgeHtml = '<span class="badge badge-info">' + statusText + '</span>';
                    }
                    row.find('td').eq(6).html(badgeHtml);

                    if (statusText === 'Ditolak') {
                        try {
                            row.find('i.fa-file-pdf').closest('a').remove();
                            row.find('i.fa-edit').closest('button').remove();
                        } catch (e) {
                            console.log('Error hiding action buttons for rejected ticket', ticket, e);
                        }
                    }
                }
            }
        });
    }
    
    // Load status untuk semua tiket saat halaman pertama kali load
    function initializeStatusBadges() {
        $('#tiketTable tbody tr').each(function() {
            var row = $(this);
            var ticket = row.find('.btn-detail').data('ticket');
            if (ticket) {
                updateStatusBadge(ticket, row);
            }
        });
    }
    
    // Initialize status badges on page load
    initializeStatusBadges();
    
    // Update status setiap 5 detik
    setInterval(function() {
        initializeStatusBadges();
    }, 5000);

    // Handler untuk tombol Detail (lihat detail_tiket di dalam modal)
    $(document).on('click', '.btn-detail', function() {
        var btn = $(this);
        if (btn.prop('disabled')) return;
        
        // Disable temporarily
        btn.prop('disabled', true);
        setTimeout(function() { btn.prop('disabled', false); }, 1500);

        var ticket = btn.data('ticket');
        var kategori = btn.data('kategori') || '';
        if (!ticket) return;

        $('#modalTicketNumber').text(ticket);
        var isCCTV = ticket.startsWith('CCTV-') || kategori.toLowerCase() === 'cctv';
        var isInternet = ticket.startsWith('INET-') || kategori.toLowerCase() === 'akses internet';
        var isEmailAccount = ticket.startsWith('EMAIL-') || kategori.toLowerCase() === 'pengajuan email account';
        var isGrantRevoke = ticket.startsWith('GRT-') || kategori.toLowerCase() === 'grant/revoke trustee';
        var url;
        if (isCCTV) {
            url = 'detail_cctv.php?ticket=' + encodeURIComponent(ticket);
        } else if (isInternet) {
            url = 'detail_akses_internet.php?ticket=' + encodeURIComponent(ticket);
        } else if (isEmailAccount) {
            url = 'detail_email_account.php?ticket=' + encodeURIComponent(ticket);
        } else if (isGrantRevoke) {
            url = 'detail_grant_revoke.php?ticket=' + encodeURIComponent(ticket);
        } else if (ticket.startsWith('DB-')) {
            url = 'detail_perubahan_data_database.php?ticket=' + encodeURIComponent(ticket);
        } else {
            url = 'detail_tiket.php?ticket=' + encodeURIComponent(ticket);
        }
        $('#iframeDetailTiket').attr('src', url);
        $('#modalDetailTiket').modal('show');
    });

    $('#modalDetailTiket').on('hidden.bs.modal', function() {
        $('#iframeDetailTiket').attr('src', 'about:blank');
    });

    // Ensure cleanup when modal is hidden (general cleanup)
    $(document).on('hidden.bs.modal', '.modal', function() {
        // Wait a bit then clean up to ensure all modals are closed
        setTimeout(function() {
            var openModals = $('.modal.show').length;
            if (openModals === 0) {
                // No modals are open, clean up everything
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open');
                $('body').css({
                    'padding-right': '',
                    'overflow': ''
                });
            }
        }, 100);
    });

    // Variable untuk tracking ticket yang akan ditolak
    var ticketToReject = null;

    // Handle Reject button from modal header
    $(document).on('click', '#btnRejectFromModal', function() {
        ticketToReject = $('#modalTicketNumber').text();
        if (!ticketToReject) {
            showError('Ticket tidak ditemukan', 'Error');
            return;
        }
        $('#rejectReasonFromList').val('');
        $('#modalRejectFromList').modal('show');
    });

    // Handle Confirm Reject from list modal
    $('#btnConfirmRejectFromList').on('click', function() {
        var reason = $('#rejectReasonFromList').val().trim();
        
            // Alasan sekarang opsional, tidak perlu validasi wajib

        if (!ticketToReject) {
            showError('Ticket tidak ditemukan', 'Error');
            return;
        }

        // Disable button and show loading
        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');

        $.ajax({
            url: './proses_reject.php',
            type: 'POST',
            data: {
                ticket: ticketToReject,
                 alasan: reason // boleh kosong
            },
            dataType: 'json',
            success: function(resp) {
                $('#btnConfirmRejectFromList').prop('disabled', false).html('Ya, Tolak Pengajuan');

                if (resp && resp.success) {
                    $('#modalRejectFromList').modal('hide');
                    $('#modalDetailTiket').modal('hide');

                    showSuccess('Pengajuan berhasil ditolak!<br><strong>' + ticketToReject + '</strong>', 'Berhasil');

                    // Update the row in the table immediately so user sees buttons disappear
                    try {
                        // Find the table row by matching the Detail button data-ticket
                        var $row = $('button.btn-detail[data-ticket="' + ticketToReject + '"]').closest('tr');
                        if ($row && $row.length) {
                            // Update Status column (8th column zero-based index 7)
                            $row.find('td').eq(6).html("<span class='badge badge-danger'>Ditolak</span>");

                            // Remove PDF button (icon fa-file-pdf) and Edit button (icon fa-edit)
                            $row.find('i.fa-file-pdf').closest('a').remove();
                            $row.find('i.fa-edit').closest('button').remove();

                            // Optionally remove Reject button if present in the modal header
                            $('#btnRejectFromModal').hide();
                        }
                    } catch (e) {
                        console.log('Error updating row UI after reject:', e);
                    }

                    // No reload fallback — UI updated in-place
                } else {
                    showError(resp.message || 'Gagal menolak pengajuan.', 'Error');
                }
            },
            error: function(xhr, status, error) {
                $('#btnConfirmRejectFromList').prop('disabled', false).html('Ya, Tolak Pengajuan');
                
                var errorMsg = 'Terjadi kesalahan saat mengirim permintaan.';
                var responseText = xhr.responseText;
                
                console.log('AJAX Error:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: responseText,
                    error: error
                });

                try {
                    var errorResponse = JSON.parse(responseText);
                    if (errorResponse.message) {
                        errorMsg = errorResponse.message;
                    } else if (errorResponse.error) {
                        errorMsg = errorResponse.error;
                    }
                } catch (e) {
                    // If not JSON, show first 200 chars
                    if (responseText && responseText.length > 0) {
                        errorMsg = 'Server Error: ' + responseText.substring(0, 200);
                    }
                }
                
                showError(errorMsg + '<br><small style="color:#666;">Status: ' + xhr.status + ' ' + xhr.statusText + '</small>', 'Error');
            }
        });
    });

    // Check if user can reject form - show/hide button
    // This will be called from iframe via postMessage or when modal is shown
    window.updateRejectButtonVisibility = function(canReject, ticket) {
        if (canReject) {
            $('#btnRejectFromModal').show();
            ticketToReject = ticket;
        } else {
            $('#btnRejectFromModal').hide();
        }
    };
});
</script>

