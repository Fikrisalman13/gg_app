<div class="modal fade" id="modalTambahDynamic" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-<?= htmlspecialchars($theme) ?> text-white">
                <h5 class="modal-title font-weight-bold" id="dynamicModalTitle"><i class="fas fa-plus-circle mr-2"></i>Tambah Dokumen</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formTambahDynamic" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_document">
                <input type="hidden" name="category_id" id="dynamicCategoryId" value="">
                
                <div class="modal-body p-4" id="dynamicFormContainer">
                    <div class="text-center py-4">
                        <i class="fas fa-spinner fa-spin fa-3x text-<?= htmlspecialchars($theme) ?>"></i>
                        <p class="mt-2 text-muted">Memuat form...</p>
                    </div>
                </div>
                
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary shadow-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-<?= htmlspecialchars($theme) ?> px-4 shadow-sm"><i class="fas fa-save mr-1"></i> Simpan Dokumen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function setDynamicCategory(catId, catName) {
    $('#dynamicModalTitle').html('<i class="fas fa-plus-circle mr-2"></i>Tambah Dokumen: ' + catName);
    $('#dynamicCategoryId').val(catId);
    
    // Load form fields dynamically
    $('#dynamicFormContainer').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-3x text-<?= htmlspecialchars($theme) ?>"></i><p class="mt-2 text-muted">Memuat form...</p></div>');
    
    $.ajax({
        url: 'ajax_handler.php',
        type: 'GET',
        data: { action: 'get_form', category_id: catId },
        success: function(res) {
            $('#dynamicFormContainer').html(res);
        },
        error: function() {
            $('#dynamicFormContainer').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle mr-2"></i>Gagal memuat form. Periksa koneksi atau server.</div>');
        }
    });
}
</script>
