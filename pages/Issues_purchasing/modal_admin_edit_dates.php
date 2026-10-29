<!-- Modal Admin Edit Dates (Hidden by default, triggered by clock icon) -->
<div class="modal fade" id="modalAdminEditDates" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog" role="document">
        <div class="modal-content border-<?php echo htmlspecialchars($themeColor ?? 'primary');?>">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary');?> text-white">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-tools mr-2"></i>Admin: Edit Timestamps</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="formAdminEditDates">
                    <input type="hidden" name="issue_id" id="admin_issue_id">
                    
                    <div class="form-group">
                        <label class="font-weight-bold text-dark">Tanggal Mulai</label>
                        <input type="datetime-local" class="form-control" name="created_at" id="admin_created_at" step="1">
                        <small class="text-muted">Waktu awal issue ini dibuat atau dilaporkan.</small>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold text-dark">Tanggal Selesai</label>
                        <input type="datetime-local" class="form-control" name="tanggal_selesai" id="admin_tanggal_selesai" step="1">
                        <small class="text-muted">Waktu saat issue ini diselesaikan (Status Done).</small>
                    </div>

                    <div class="alert alert-info py-2 mb-0 mt-3" style="font-size: 0.85rem;">
                        <i class="fas fa-info-circle mr-1"></i> Perubahan ini akan langsung disimpan ke database dan mengabaikan logika otomatis.
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-warning btn-sm font-weight-bold" id="btnAdminUpdateDates">
                    <i class="fas fa-save mr-1"></i> Simpan Perubahan
                </button>
            </div>
        </div>
    </div>
</div>
