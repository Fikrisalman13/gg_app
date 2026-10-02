<?php
// Ambil Kategori dan Sub Kategori dari Database
$issueCategories = [];
try {
    // Only fetch if connection exists (it happens in list_issues)
    if (isset($conn)) {
        $sqlCat = "SELECT c.category_name, s.sub_name 
                   FROM dbo.issue_categories c
                   LEFT JOIN dbo.issue_sub_categories s ON c.category_id = s.category_id
                   WHERE c.is_active = 1 
                   ORDER BY c.category_name, s.sub_name";
        $stmtCat = sqlsrv_query($conn, $sqlCat);
        if ($stmtCat !== false) {
            while ($row = sqlsrv_fetch_array($stmtCat, SQLSRV_FETCH_ASSOC)) {
                $cName = $row['category_name'];
                $sName = $row['sub_name'];
                
                if (!isset($issueCategories[$cName])) {
                    $issueCategories[$cName] = [];
                }
                if (!empty($sName)) {
                    $issueCategories[$cName][] = $sName;
                }
            }
        }
    }
} catch (Exception $e) {
    // Fallback if needed or log
}
?>

<!-- Pass PHP data to JavaScript globally -->
<script>
    var subKategoriData = <?php echo json_encode($issueCategories); ?>;
</script>

<!-- Modal Add/Edit Issue -->
<div class="modal fade" id="modalIssue" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document" style="max-height: 90vh; margin: 1.75rem auto;">
        <div class="modal-content" style="max-height: 90vh; display: flex; flex-direction: column;">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary');?> text-white" style="flex-shrink: 0;">
                <h5 class="modal-title" id="modalIssueTitle"><i class="fas fa-plus-circle mr-2"></i>Tambah Issue Baru</h5>
                <div class="ml-auto d-flex align-items-center">
                    <button type="button" class="btn btn-light btn-sm mr-2" id="btnShowTemplatePicker">
                        <i class="fas fa-clone mr-1"></i>Gunakan Template
                    </button>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>
            <div class="modal-body" style="overflow-y: auto; flex: 1 1 auto; min-height: 0; padding: 1.5rem; max-height: calc(90vh - 140px);">
                <form id="formIssue">
                    <input type="hidden" name="issue_id" id="issue_id">
                    <input type="hidden" name="action" id="action" value="add">
                    
                    <div class="row">
                        <!-- Left Column - Auto-filled Fields -->
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3"><i class="fas fa-user-circle mr-1"></i>Informasi Akun</h6>
                            
                            <div class="form-row">
                                <div class="col-6">
                                    <label class="form-label mb-1">Nama</label>
                                    <input type="text" class="form-control form-control-sm auto-filled" 
                                           name="nama" id="nama" 
                                           value="<?php echo $username ?? '';?>" readonly>
                                </div>
                                <div class="col-6">
                                    <label class="form-label mb-1">Jabatan</label>
                                    <input type="text" class="form-control form-control-sm auto-filled" 
                                           name="jabatan" id="jabatan" 
                                           value="<?php echo htmlspecialchars($jabatan ?? '');?>" readonly>
                                </div>
                            </div>
                            
                            <div class="form-row mt-2">
                                <div class="col-4">
                                    <label class="form-label mb-1">Tanggal</label>
                                    <input type="text" class="form-control form-control-sm auto-filled" 
                                           name="tgl_pengajuan" id="tgl_pengajuan" 
                                           value="<?php echo date('d-m-Y');?>" readonly>
                                </div>
                                <div class="col-4">
                                    <label class="form-label mb-1">Departemen</label>
                                    <input type="text" class="form-control form-control-sm auto-filled" 
                                           name="departemen" id="departemen" 
                                           value="<?php echo htmlspecialchars($departemen ?? '');?>" readonly>
                                </div>
                                <div class="col-4">
                                    <label class="form-label mb-1">Bagian</label>
                                    <input type="text" class="form-control form-control-sm auto-filled" 
                                           name="bagian" id="bagian" 
                                           value="<?php echo htmlspecialchars($bagian ?? '');?>" readonly>
                                </div>
                            </div>
                            
                            <hr class="my-3">
                            
                            <h6 class="text-muted mb-3"><i class="fas fa-layer-group mr-1"></i>Kategori Kegiatan IT</h6>
                            
                            <div class="form-group">
                                <label>Kategori <span class="text-danger">*</span></label>
                                <select class="form-control form-control-sm" name="kategori" id="kategori" required>
                                    <option value="">-- Pilih Kategori --</option>
                                    <?php foreach ($issueCategories as $cat => $subs): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group" id="subKategoriGroup" style="display: none;">
                                <label>Sub Kategori <span class="text-danger">*</span></label>
                                <select class="form-control form-control-sm" name="sub_kategori" id="sub_kategori">
                                    <option value="">-- Pilih Sub Kategori --</option>
                                </select>
                            </div>
                            
                            <div class="form-group" id="clientGroup" style="display: none;">
                                <label>Pilih Client</label>
                                <select class="form-control client-search-select" name="client_id" id="client_id" data-placeholder="Cari atau pilih client" style="width: 100%;">
                                    <option value="">-- Pilih Client --</option>
                                </select>
                            </div>

                            <div class="form-group" id="assetGroup" style="display: none;">
                                <label>Pilih Asset</label>
                                <select class="form-control asset-search-select" name="asset_id" id="asset_id" data-placeholder="Cari atau pilih asset" style="width: 100%;">
                                    <option value="">-- Pilih Asset --</option>
                                </select>
                            </div>
                            
                            <!-- Sub Detail Group hidden as per logic but retained if needed -->
                            <div class="form-group" id="subDetailGroup" style="display: none;">
                                <label>Sub Detail</label>
                                <select class="form-control form-control-sm" name="sub_detail" id="sub_detail">
                                    <option value="">-- Pilih Sub Detail --</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Right Column - Manual Input Fields -->
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3"><i class="fas fa-edit mr-1"></i>Detail Issue</h6>
                            
                            <div class="form-group">
                                <label>Issue Name <span class="text-danger">*</span></label>
                                <div class="position-relative">
                                    <div class="input-group">
                                        <input type="text" class="form-control form-control-sm" 
                                               id="issue_name_input" 
                                               placeholder="Ketik lalu tekan Enter untuk menambahkan" autocomplete="off">
                                    </div>
                                    <div id="dateSuggestionBubble" class="position-absolute bg-white shadow-sm border rounded px-2 py-1" style="display:none; top: 100%; left: 0; z-index: 100; margin-top: 4px; border-color: #007bff !important; min-width: 250px;">
                                        <div class="d-flex flex-column">
                                            <small class="text-muted mb-1" style="font-size:0.75rem;">Sisipkan Tanggal (Dinamis):</small>
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-xs btn-outline-info" id="btnSuggestYesterday">Kemarin: <span id="txtSuggestYesterday"></span></button>
                                                <button type="button" class="btn btn-xs btn-outline-primary" id="btnSuggestToday">Hari Ini: <span id="txtSuggestToday"></span></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <small class="form-text text-muted">Gunakan tanggal <b>Hari Ini</b> atau <b>Kemarin</b> agar otomatis menjadi dinamis (berubah sesuai tanggal hari H). Gunakan Enter Untuk Menambahkan Issues Name Lebih Dari 1</small>
                                


                                <div id="issue_names_list" class="mt-2 is-empty">
                                    <!-- chips will appear here -->
                                </div>

                                <!-- Hidden compatibility fields -->
                                <input type="hidden" name="issue_name" id="issue_name">
                                <input type="hidden" name="issue_names_json" id="issue_names_json">
                            </div>
                            
                            <div class="form-group">
                                <label>Type <span class="text-danger">*</span></label>
                                <select class="form-control form-control-sm issue-type-select" name="issue_type" id="issue_type" data-placeholder="Pilih tipe issue" required>
                                    <option value="" selected>-- Pilih Type --</option>
                                    <option value="Task">Task</option>
                                    <option value="Maintenance">Maintenance</option>
                                    <option value="Bug">Bug</option>
                                    <option value="Improvement">Improvement</option>
                                    <option value="New Feature">New Feature</option>
                                    <option value="Story">Story</option>
                                </select>
                            </div>
                            
                            <div class="form-row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label>Status <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" name="status" id="status" required>
                                            <option value="To Do" selected>To Do</option>
                                            <option value="In Progress">In Progress</option>
                                            <option value="Done">Done</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label>Priority <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" name="priority" id="priority" required>
                                            <option value="Low">Low</option>
                                            <option value="Normal" selected>Normal</option>
                                            <option value="High">High</option>
                                
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Due Date</label>
                                <input type="date" class="form-control form-control-sm" 
                                       name="due_date" id="due_date"
                                       value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Description</label>
                                <textarea class="form-control form-control-sm" 
                                          name="description" id="description" 
                                          rows="4" 
                                          placeholder="Deskripsi detail kegiatan (optional)"></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="flex-shrink: 0; border-top: 1px solid #dee2e6;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times"></i> Batal
                </button>
                <button type="button" class="btn btn-success btn-sm" id="btnSimpanIssue">
                    <i class="fas fa-save"></i> Simpan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Template Picker -->
<div class="modal fade" id="modalTemplatePicker" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary');?> text-white">
                <h5 class="modal-title"><i class="fas fa-clone mr-2"></i>Pilih Template Issue</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="issueTemplateEmptyState" class="text-muted text-center py-4" style="display: none;">
                    <i class="fas fa-inbox fa-3x mb-3 text-muted"></i>
                    <p>Belum ada template yang tersimpan.</p>
                </div>
                <div id="templateListContainer" style="display: none;">
                    <p class="text-muted mb-3"><i class="fas fa-info-circle mr-1"></i>Klik template untuk mengisi form otomatis</p>
                    <div id="templateListItems" class="list-group">
                        <!-- Search Input -->
                        <div class="mb-3">
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-white border-right-0">
                                        <i class="fas fa-search text-muted"></i>
                                    </span>
                                </div>
                                <input type="text" id="templateSearchInput" class="form-control border-left-0" placeholder="Cari template berdasarkan nama, tipe, atau kategori..." autocomplete="off">
                                <div class="input-group-append" id="btnResetTemplateSearch" style="display: none;">
                                    <button class="btn btn-outline-secondary" type="button">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <!-- Template items will be inserted here -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Simpan Template -->
<div class="modal fade" id="modalSaveTemplate" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary');?> text-white">
                <h5 class="modal-title"><i class="fas fa-save mr-2"></i>Simpan sebagai Template</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Apakah Anda ingin menyimpan issue ini sebagai template untuk digunakan lagi nanti?</p>
                <div class="form-group">
                    <label>Nama Template <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="template_name_input" 
                           placeholder="Masukkan nama template (default: nama issue pertama)" required>
                    <div id="template_name_preview" class="mt-2" style="font-size: 0.9rem; min-height: 1.2em;"></div>
                    <small class="form-text text-muted">Template dengan nama yang sama akan ditimpa.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="btnSkipTemplate">
                    <i class="fas fa-times"></i> Tidak, Terima Kasih
                </button>
                <button type="button" class="btn btn-primary" id="btnConfirmSaveTemplate">
                    <i class="fas fa-check"></i> Ya, Simpan Template
                </button>
            </div>
        </div>
    </div>
</div>
