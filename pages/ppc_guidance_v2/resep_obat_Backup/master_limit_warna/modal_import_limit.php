<!-- pages/resep_obat/master_limit_warna/modal_import_limit.php -->
<div class="modal fade" id="modal-import-limit">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor); ?>">
                <h4 class="modal-title">Import Excel Limit Warna</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-12">
                        <p>1. Unduh template Excel terlebih dahulu.</p>
                        <a href="download_template_limit.php" target="_blank" class="btn btn-info btn-sm">
                            <i class="fas fa-download"></i> Download Template
                        </a>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <p>2. Upload file Excel yang sudah diisi.</p>
                        <form id="formImportLimit" enctype="multipart/form-data">
                            <div class="input-group">
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="fileImport" name="file" accept=".xlsx, .xls" required>
                                    <label class="custom-file-label" for="fileImport">Pilih file...</label>
                                </div>
                                <div class="input-group-append">
                                    <button class="btn btn-primary" type="submit" id="btnPreviewImport">
                                        <i class="fas fa-eye"></i> Preview
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div id="importPreviewContainer" style="display: none;">
                    <h5>Preview Data</h5>
                    <div class="alert alert-info py-1 px-2" style="font-size: 0.9em;">
                        <i class="fas fa-info-circle"></i> Baris berwarna <strong class="text-success">Hijau</strong> akan diimpor. Baris <strong class="text-danger">Merah</strong> (Duplikat/Sudah Ada) harus dihapus.
                    </div>
                    <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                        <div id="importPreviewTable"></div>
                    </div>
                    
                    <div class="mt-3 text-right">
                        <span id="importSummary" class="mr-2 font-weight-bold"></span>
                        <button type="button" class="btn btn-success" id="btnSaveImport" disabled>
                            <i class="fas fa-upload"></i> Import Valid Data
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Check for jQuery availability, since this file might be included before scripts
function defer(method) {
    if (window.jQuery) {
        method();
    } else {
        setTimeout(function() { defer(method) }, 50);
    }
}

defer(function() {
    // Custom File Input Label
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').addClass("selected").html(fileName);
    });

    let currentImportData = [];

    // Helper to render formatting
    const funcFmt = (v) => v === null ? '-' : v;

    function renderPreviewTable() {
        let html = '<table class="table table-bordered table-sm">';
        html += '<thead><tr>' +
                '<th>Kode Warna</th>' +
                '<th>Max Cost</th>' +
                '<th>Max Disperse</th>' +
                '<th>Max Reactive</th>' +
                '<th>Max Total</th>' +
                '<th>Status</th>' +
                '<th>Aksi</th>' +
                '</tr></thead>';
        html += '<tbody>';

        // Calculate counts explicitly
        let newCount = currentImportData.filter(i => i.valid === true && !i.is_update).length;
        let updateCount = currentImportData.filter(i => i.valid === true && i.is_update).length;
        let invalidCount = currentImportData.filter(i => i.valid !== true).length;
        let totalCount = currentImportData.length;

        if (totalCount === 0) {
             html += '<tr><td colspan="7" class="text-center">Tidak ada data.</td></tr>';
        } else {
            currentImportData.forEach((item, index) => {
                let bgClass = '';
                let statusLabel = '';
                let style = '';

                if (!item.valid) {
                    style = 'background-color:#f8d7da;'; // Red
                    statusLabel = '<span class="badge badge-danger">' + item.errors.join(', ') + '</span>';
                } else {
                    if (item.is_update) {
                         style = 'background-color:#fff3cd;'; // Yellow
                         statusLabel = '<span class="badge badge-warning">Update</span>';
                    } else {
                         style = 'background-color:#d4edda;'; // Green
                         statusLabel = '<span class="badge badge-success">New</span>';
                    }
                }

                html += `<tr style="${style}">
                    <td>${item.kode}</td>
                    <td>${funcFmt(item.max_cost)}</td>
                    <td>${funcFmt(item.max_cf_disperse)}</td>
                    <td>${funcFmt(item.max_cf_reactive)}</td>
                    <td>${funcFmt(item.max_cf_total)}</td>
                    <td>${statusLabel}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-xs btn-danger btn-remove-row" data-index="${index}" title="Hapus Baris">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>`;
            });
        }
        html += '</tbody></table>';
        
        $('#importPreviewTable').html(html);
        
        // Detailed Summary
        let summaryText = `Total: ${totalCount} | Siap Import: ${newCount} | Invalid: ${invalidCount}`;
        $('#importSummary').text(summaryText);
        
        // Console Log for debugging
        console.log("Import State:", { total: totalCount, new: newCount, update: updateCount, invalid: invalidCount });

        // Logic: Enable if (New > 0 OR Update > 0) AND (Invalid == 0)
        if ((newCount > 0 || updateCount > 0) && invalidCount === 0) {
             $('#btnSaveImport').prop('disabled', false);
             console.log("Button Enabled");
        } else {
             $('#btnSaveImport').prop('disabled', true);
             console.log("Button Disabled");
        }
    }

    $('#formImportLimit').on('submit', function(e) {
        e.preventDefault();
        let formData = new FormData(this);
        
        // Reset
        $('#importPreviewContainer').hide();
        $('#btnPreviewImport').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
        
        $.ajax({
            url: 'preview_import_limit.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function(resp) {
                $('#btnPreviewImport').prop('disabled', false).html('<i class="fas fa-eye"></i> Preview');
                
                if (resp.status === 'success') {
                    currentImportData = resp.previewData;
                    renderPreviewTable();
                    $('#importPreviewContainer').show();
                } else {
                    Swal.fire('Error', resp.message, 'error');
                }
            },
            error: function(xhr) {
                 $('#btnPreviewImport').prop('disabled', false).html('<i class="fas fa-eye"></i> Preview');
                 Swal.fire('Error', 'Gagal memproses file.', 'error');
            }
        });
    });

    // Handle Delete Row
    $(document).on('click', '.btn-remove-row', function() {
        let index = $(this).data('index');
        currentImportData.splice(index, 1);
        renderPreviewTable();
    });

    $('#btnSaveImport').on('click', function() {
        // Filter only valid data for saving
        let validData = currentImportData.filter(item => item.valid);
        
        if (validData.length === 0) return;
        
        Swal.fire({
            title: 'Konfirmasi Import',
            text: `Anda akan mengimport ${validData.length} data valid. Lanjutkan?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Import',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return $.ajax({
                    url: 'save_import_limit.php',
                    type: 'POST',
                    data: JSON.stringify({ data: validData }),
                    contentType: 'application/json',
                    dataType: 'json'
                }).then(response => {
                    if (response.status !== 'success') {
                        throw new Error(response.message || 'Import failed');
                    }
                    return response;
                }).catch(error => {
                    Swal.showValidationMessage(
                        `Request failed: ${error}`
                    );
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Berhasil!',
                    text: result.value.message,
                    icon: 'success'
                }).then(() => {
                    $('#modal-import-limit').modal('hide');
                    $('#tableLimit').DataTable().ajax.reload();
                });
            }
        });
    });
    // Reset Modal on Close
    $('#modal-import-limit').on('hidden.bs.modal', function () {
        $('#formImportLimit')[0].reset();
        $('.custom-file-label').html('Pilih file...');
        $('#importPreviewContainer').hide();
        $('#importPreviewTable').empty();
        $('#importSummary').empty();
        $('#btnSaveImport').prop('disabled', true);
        currentImportData = [];
    });
});
</script>
