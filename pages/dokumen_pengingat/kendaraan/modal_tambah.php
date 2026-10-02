<!-- MODAL TAMBAH KENDARAAN -->
<div class="modal fade" id="modalTambahKen" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formTambahKen" enctype="multipart/form-data">
                <div class="modal-header bg-<?= htmlspecialchars($theme) ?>">
                    <h5 class="modal-title text-white"><i class="fas fa-plus mr-1"></i> Tambah Surat Kendaraan</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>No Polisi <span class="text-danger">*</span></label>
                                <input type="text" name="no_polisi" class="form-control" required placeholder="Contoh: B 1234 ABC">
                            </div>
                            <div class="form-group">
                                <label>Jenis Kendaraan <span class="text-danger">*</span></label>
                                <select name="jenis_kendaraan" class="form-control" required>
                                    <option value="Mobil">Mobil</option>
                                    <option value="Motor">Motor</option>
                                    <option value="Truk">Truk</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Nama Kendaraan <span class="text-danger">*</span></label>
                                <input type="text" name="nama_kendaraan" class="form-control" required placeholder="Contoh: Toyota Avanza">
                            </div>
                            <div class="form-group">
                                <label>Nama Pemilik <span class="text-danger">*</span></label>
                                <input type="text" name="nama_pemilik" class="form-control" required placeholder="Sesuai STNK">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Expire Date <span class="text-danger">*</span></label>
                                <input type="date" name="expire_date" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>File Kendaraan (PDF/JPG/PNG) <span class="text-danger">*</span></label>
                                <input type="file" name="file_kendaraan[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required multiple>
                                <small class="text-muted">Bisa pilih lebih dari 1 file sekaligus.</small>
                            </div>
                            <div class="form-group">
                                <label>Bagian <span class="text-danger">*</span></label>
                                <select name="bagian_id" class="form-control" required>
                                    <option value="">-- Pilih Bagian --</option>
                                    <?php foreach ($bagianList as $b): ?>
                                        <option value="<?= $b['id_bag'] ?>"><?= htmlspecialchars($b['bagian']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email Reminder</label>
                                <input type="email" name="email_reminder" class="form-control" placeholder="user@example.com">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>No Whatsapp</label>
                                <input type="text" name="no_whatsapp" class="form-control" placeholder="628xxxxxxx">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Keterangan</label>
                        <textarea name="keterangan" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save mr-1"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
