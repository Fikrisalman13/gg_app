<!-- pages/resep_obat/master_limit_warna/modal_form_limit.php -->
<div class="modal fade" id="modal-limit">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor); ?>">
                <h4 class="modal-title text-white" id="modalTitle">Tambah Limit Warna</h4>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formLimit">
                <div class="modal-body">
                    <input type="hidden" id="mode" name="mode" value="add">
                    <input type="hidden" id="limit_id" name="id">

                    <div class="form-group">
                        <label>Kode Warna <span class="text-danger">*</span></label>
                        <select class="form-control select2-color" id="kode_warna" name="kode_warna" style="width: 100%;" required>
                            <!-- AJAX Load -->
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Max Cost</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">Rp</span>
                            </div>
                            <input type="text" class="form-control currency-format" id="max_cost" name="max_cost" placeholder="0 atau - untuk tanpa batas">
                        </div>
                        <small class="text-muted">Gunakan "-" untuk tanpa batas</small>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label>Max CF Disperse</label>
                                <input type="text" class="form-control" id="max_cf_disperse" name="max_cf_disperse" placeholder="0.00 atau -">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label>Max CF Reactive</label>
                                <input type="text" class="form-control" id="max_cf_reactive" name="max_cf_reactive" placeholder="0.00 atau -">
                            </div>
                        </div>
                    </div>
                     <div class="form-group">
                        <label>Max CF Total (Disp+Rct)</label>
                        <input type="text" class="form-control" id="max_cf_total" name="max_cf_total" placeholder="0.00 atau -">
                        <small class="text-muted">Total gabungan jika resep mix.</small>
                    </div>

                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
