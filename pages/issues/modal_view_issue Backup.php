<div class="modal fade" id="modalIssueDetail" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                <h5 class="modal-title" id="modalIssueDetailTitle"><i class="fas fa-eye mr-2"></i>Detail Issue</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="issueDetailLoading" class="text-center py-4">
                    <i class="fas fa-spinner fa-spin fa-2x text-muted mb-3"></i>
                    <p class="text-muted mb-0">Memuat detail issue...</p>
                </div>
                <div id="issueDetailError" class="alert alert-danger d-none" role="alert"></div>
                <div id="issueDetailContent" class="d-none">
                    <div class="issue-detail-header mb-4">
                        <div>
                            <div class="text-muted small" id="detailIssueId"></div>
                            <h4 class="mb-2" id="detailIssueName"></h4>
                            <div class="issue-detail-badges">
                                <span id="detailTypeBadge"></span>
                                <span id="detailStatusBadge"></span>
                                <span id="detailPriorityBadge"></span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="detail-label">Due Date</div>
                            <div class="detail-value" id="detailDueDate"></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="detail-section mb-3">
                                <div class="section-title"><i class="fas fa-user-circle mr-1"></i>Informasi Akun</div>
                                <dl class="detail-list">
                                    <dt>Nama</dt>
                                    <dd id="detailNama"></dd>
                                    <dt>Jabatan</dt>
                                    <dd id="detailJabatan"></dd>
                                    <dt>Departemen</dt>
                                    <dd id="detailDepartemen"></dd>
                                    <dt>Bagian</dt>
                                    <dd id="detailBagian"></dd>
                                </dl>
                            </div>
                            <div class="detail-section mb-3">
                                <div class="section-title"><i class="fas fa-layer-group mr-1"></i>Kategori & Asset</div>
                                <dl class="detail-list">
                                    <dt>Kategori</dt>
                                    <dd id="detailKategori"></dd>
                                    <dt>Sub Kategori</dt>
                                    <dd id="detailSubKategori"></dd>
                                    <dt>Asset</dt>
                                    <dd id="detailAsset"></dd>
                                    <dt>Client</dt>
                                    <dd id="detailClient"></dd>
                                </dl>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-section mb-3">
                                <div class="section-title"><i class="fas fa-clock mr-1"></i>Timeline</div>
                                <div class="detail-timeline">
                                    <div class="timeline-item">
                                        <span class="timeline-label">Dibuat</span>
                                        <span id="detailCreatedInfo"></span>
                                    </div>
                                    <div class="timeline-item">
                                        <span class="timeline-label">Update Terakhir</span>
                                        <span id="detailUpdatedInfo"></span>
                                    </div>
                                    <div class="timeline-item" id="detailCompletionGroup" style="display:none;">
                                        <span class="timeline-label">Selesai</span>
                                        <span id="detailCompletionDate"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="detail-section mb-3">
                                <div class="section-title"><i class="fas fa-list-alt mr-1"></i>Ringkasan Status</div>
                                <dl class="detail-list">
                                    <dt>Status</dt>
                                    <dd id="detailStatusText"></dd>
                                    <dt>Priority</dt>
                                    <dd id="detailPriorityText"></dd>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <div class="section-title"><i class="fas fa-align-left mr-1"></i>Deskripsi</div>
                        <div class="detail-description" id="detailDescription"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times"></i> Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    /* Ensure icons inside detail badges have spacing like the list view */
    #modalIssueDetail .issue-detail-badges span .fas,
    #modalIssueDetail .issue-detail-badges span i {
        margin-right: 0.35rem;
    }
    /* Also ensure any status/priority elements inside detail use inline-flex alignment */
    #modalIssueDetail .issue-detail-badges span,
    #modalIssueDetail .issue-detail-badges > * {
        display: inline-flex;
        align-items: center;
    }

    /* Force fix for Issue Detail Header Layout */
    #modalIssueDetail .issue-detail-header {
        display: flex !important;
        justify-content: space-between !important;
        align-items: flex-start !important;
        flex-wrap: nowrap !important;
        gap: 1rem;
    }
    #modalIssueDetail .issue-detail-header > div:first-child {
        flex: 1;
        min-width: 0; /* Allows text truncation if needed */
        padding-right: 15px;
    }
    #modalIssueDetail .issue-detail-header > div:last-child {
        flex-shrink: 0;
        text-align: right;
    }
</style>
