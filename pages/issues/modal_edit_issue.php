<?php
// This modal is dedicated for editing a single issue (no multi-name, no template picker)
?>

<div class="modal fade" id="modalIssueEdit" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document" style="max-height: 90vh; margin: 1.75rem auto;">
        <div class="modal-content" style="max-height: 90vh; display: flex; flex-direction: column;">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary');?> text-white" style="flex-shrink: 0;">
                <h5 class="modal-title" id="modalIssueEditTitle"><i class="fas fa-edit mr-2"></i>Edit Issue</h5>
                <div class="ml-auto d-flex align-items-center">
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>
            <div class="modal-body" style="overflow-y: auto; flex: 1 1 auto; min-height: 0; padding: 1.5rem; max-height: calc(90vh - 140px);">
                <form id="formIssueEdit">
                    <input type="hidden" name="issue_id" id="edit_issue_id">
                    <input type="hidden" name="action" id="edit_action" value="edit">

                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3"><i class="fas fa-user-circle mr-1"></i>Informasi Akun</h6>
                            <div class="form-row">
                                <div class="col-6">
                                    <label class="form-label mb-1">Nama</label>
                                    <input type="text" class="form-control form-control-sm auto-filled" name="nama" id="edit_nama" readonly>
                                </div>
                                <div class="col-6">
                                    <label class="form-label mb-1">Jabatan</label>
                                    <input type="text" class="form-control form-control-sm auto-filled" name="jabatan" id="edit_jabatan" readonly>
                                </div>
                            </div>

                            <div class="form-row mt-2">
                                <div class="col-4">
                                    <label class="form-label mb-1">
                                        Tanggal
                                        <?php if (isset($isHistoryPage) && $isHistoryPage === true && in_array($_SESSION['UserName'], ['ITADM', 'IT7'])): ?>
                                            <a href="#" id="btnAdminEditDates" class="text-warning ml-1" title="Admin: Edit Timestamps">
                                                <i class="fas fa-clock shadow-sm"></i>
                                            </a>
                                        <?php endif; ?>
                                    </label>
                                    <input type="text" class="form-control form-control-sm auto-filled" name="tgl_pengajuan" id="edit_tgl_pengajuan" readonly>
                                </div>
                                <div class="col-4">
                                    <label class="form-label mb-1">Departemen</label>
                                    <input type="text" class="form-control form-control-sm auto-filled" name="departemen" id="edit_departemen" readonly>
                                </div>
                                <div class="col-4">
                                    <label class="form-label mb-1">Bagian</label>
                                    <input type="text" class="form-control form-control-sm auto-filled" name="bagian" id="edit_bagian" readonly>
                                </div>
                            </div>

                            <hr class="my-3">

                            <h6 class="text-muted mb-3"><i class="fas fa-layer-group mr-1"></i>Kategori Kegiatan IT</h6>

                            <div class="form-group">
                                <label>Kategori <span class="text-danger">*</span></label>
                                <select class="form-control form-control-sm" name="kategori" id="edit_kategori" required>
                                    <option value="">-- Pilih Kategori --</option>
                                    <?php foreach ($issueCategories as $cat => $subs): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group" id="edit_subKategoriGroup" style="display: none;">
                                <label>Sub Kategori <span class="text-danger">*</span></label>
                                <select class="form-control form-control-sm" name="sub_kategori" id="edit_sub_kategori"></select>
                            </div>

                            <div class="form-group" id="edit_clientGroup" style="display: none;">
                                <label>Pilih Client</label>
                                <select class="form-control client-search-select" name="client_id" id="edit_client_id" style="width: 100%;">
                                    <option value="">-- Pilih Client --</option>
                                </select>
                            </div>

                            <div class="form-group" id="edit_assetGroup" style="display: none;">
                                <label>Pilih Asset</label>
                                <select class="form-control asset-search-select" name="asset_id" id="edit_asset_id" style="width: 100%;">
                                    <option value="">-- Pilih Asset --</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h6 class="text-muted mb-3"><i class="fas fa-edit mr-1"></i>Detail Issue</h6>

                            <div class="form-group">
                                <label>Issue Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" name="issue_name" id="edit_issue_name" required>
                            </div>

                            <div class="form-group">
                                <label>Project</label>
                                <select class="form-control form-control-sm project-search-select" name="projectid" id="edit_projectid" data-placeholder="Pilih project" style="width: 100%;">
                                    <option value="">-- Pilih Project --</option>
                                    <?php foreach (($projectOptions ?? []) as $projectOpt): ?>
                                        <option value="<?php echo (int) $projectOpt['projectid']; ?>">
                                            #<?php echo (int) $projectOpt['projectid']; ?> - <?php echo htmlspecialchars($projectOpt['projectname']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Type <span class="text-danger">*</span></label>
                                <select class="form-control form-control-sm issue-type-select" name="issue_type" id="edit_issue_type" required>
                                    <option value="" selected>-- Pilih Type --</option>
                                    <option value="Task">Task</option>
                                    <option value="Maintenance">Maintenance</option>
                                    <option value="Bug">Bug</option>
                                    <option value="Improvement">Improvement</option>
                                    <option value="New Feature">New Feature</option>
                                    <option value="Support Activities">Support Activities</option>
                                </select>
                            </div>

                            <div class="form-row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label>Status <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" name="status" id="edit_status" required>
                                            <option value="To Do">To Do</option>
                                            <option value="In Progress">In Progress</option>
                                            <option value="Done">Done</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label>Priority <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" name="priority" id="edit_priority" required>
                                            <option value="Low">Low</option>
                                            <option value="Normal">Normal</option>
                                            <option value="High">High</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Due Date</label>
                                <input type="date" class="form-control form-control-sm" name="due_date" id="edit_due_date">
                            </div>

                            <div class="form-group">
                                <label>Description</label>
                                <textarea class="form-control form-control-sm" name="description" id="edit_description" rows="4"></textarea>
                            </div>

                            <div class="form-group">
                                <label>Attachment</label>
                                <div id="current_attachment_container" style="display: none; margin-bottom: 8px;">
                                    <a id="current_attachment_link" href="#" target="_blank" class="btn btn-sm btn-outline-info">
                                        <i class="fas fa-paperclip"></i> Lihat Lampiran Saat Ini
                                    </a>
                                    <div class="custom-control custom-checkbox mt-2">
                                        <input type="checkbox" class="custom-control-input" id="remove_attachment" name="remove_attachment" value="1">
                                        <label class="custom-control-label text-danger" for="remove_attachment">Hapus lampiran ini</label>
                                    </div>
                                </div>
                                <input type="file" class="form-control-file form-control-sm" name="attachment" id="edit_attachment" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx,.zip">
                                <small class="form-text text-muted">Unggah file baru untuk mengganti lampiran saat ini. Format: jpg, png, pdf, docx, xlsx, zip.</small>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="flex-shrink: 0; border-top: 1px solid #dee2e6;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times"></i> Batal
                </button>
                <button type="button" class="btn btn-success btn-sm" id="btnUpdateIssue">
                    <i class="fas fa-save"></i> Update
                </button>
            </div>
        </div>
    </div>
</div>
