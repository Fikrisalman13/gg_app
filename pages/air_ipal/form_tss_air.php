<?php
// form_tss_air.php - Form input TSS Air
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

$username = htmlspecialchars($_SESSION['NamaLengkap'] ?? '');
$dateNow = date('Y-m-d');
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>
<style>
.ipal-tss-form {
  width: 100%;
  max-width: 1040px;
  min-width: 0;
  margin: 0 auto;
}
.ipal-tss-form .form-label {
  display: block;
  font-size: 12px;
  font-weight: 700;
  margin-bottom: 6px;
}
.ipal-tss-meta {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}
.ipal-tss-section-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin: 16px 0 10px;
  font-weight: 700;
}
.ipal-tss-section-title span {
  color: #6c757d;
  font-size: 12px;
  font-weight: 600;
}
.ipal-tss-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}
.ipal-tss-field {
  border: 1px solid #d8dee5;
  border-radius: 6px;
  background: #fff;
  padding: 9px;
}
.ipal-tss-field label {
  color: #343a40;
  display: block;
  font-size: 12px;
  font-weight: 700;
  margin-bottom: 6px;
}
.ipal-tss-field input {
  height: 38px;
  text-align: right;
}
@media (max-width: 991.98px) {
  .ipal-tss-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 767.98px) {
  .ipal-tss-meta,
  .ipal-tss-grid {
    grid-template-columns: 1fr;
  }
}
</style>
<div class="ipal-tss-form">
    <form method="POST" id="formTssAir" style="margin-bottom:0;">
      <div class="ipal-tss-meta">
        <div>
          <label for="tanggal" class="form-label">Tanggal</label>
          <input type="date" class="form-control" name="tanggal" id="tanggal" value="<?= $dateNow ?>" required>
        </div>
        <div>
          <label for="pengecek" class="form-label">Pengecek</label>
          <select class="form-control" name="pengecek" id="pengecek" required>
            <option value="">Pilih Pengecek</option>
            <?php
            $listPengecek = [
              'Budi Santoso',
              'Roni Hermadi',
              'Yunus Abdul Mutaqin',
              'Irwan Hidayat',
              'M Rivaldy',
              'M Rizki Apriatna',
              'Deviyanti Nugraha',
              'Devi Nabila'
            ];
            foreach ($listPengecek as $nama) {
              $selected = ($username == $nama) ? 'selected' : '';
              echo "<option value=\"$nama\" $selected>$nama</option>";
            }
            ?>
          </select>
        </div>
        <div>
          <label for="shift" class="form-label">Sampel Air</label>
          <select class="form-control" name="shift" id="shift" required>
            <option value="">Pilih Shift</option>
            <option value="1">Pagi</option>
            <option value="2">Siang</option>
            <option value="3">Malam</option>
          </select>
        </div>
      </div>
      <div class="ipal-tss-section-title">
        <div>Nilai TSS Air per Bak</div>
        <span>mg/L</span>
      </div>
      <div class="ipal-tss-grid">
        <?php
        $bakList = [
          ['TSS_0', 'TSS 0'],
          ['Pekat_Besar', 'Pekat Besar'],
          ['Pekat_Kecil', 'Pekat Kecil'],
          ['Dwatring', 'Dwatring'],
          ['Anoxit', 'Anoxit'],
          ['Daff_1', 'Daff 1'],
          ['Daff_2', 'Daff 2'],
          ['Daff_3', 'Daff 3'],
          ['Sedimen', 'Sedimen'],
          ['Equal', 'Equal Sum'],
          ['Outlet', 'Outlet'],
        ];
        foreach ($bakList as [$key, $label]): ?>
          <div class="ipal-tss-field">
            <label for="tss_<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></label>
            <input type="number" min="0" inputmode="numeric" class="form-control" name="<?= htmlspecialchars($key) ?>" id="tss_<?= htmlspecialchars($key) ?>" placeholder="0">
          </div>
        <?php endforeach; ?>
      </div>
      <input type="hidden" name="created_by" value="<?= $username ?>">
      <div class="mt-3 d-flex justify-content-between">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>">Simpan</button>
      </div>
    </form>
</div>
<script>
$('#formTssAir input[type="number"]').on('focus', function() {
    this.select();
});
$('#formTssAir').on('submit', function(e) {
    e.preventDefault();
    var formData = $(this).serialize();
    $.ajax({
        url: 'simpan_tss_air.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(resp) {
            if (resp.success) {
                Swal.fire('Berhasil', 'Data TSS Air berhasil disimpan!', 'success');
                $('#modalPilihParameter').modal('hide');
                $('#formTssAir')[0].reset();
                if (typeof window.reloadPhAirTable === 'function') window.reloadPhAirTable();
            } else {
                Swal.fire('Gagal', resp.error || 'Gagal menyimpan data', 'error');
            }
        },
        error: function() {
            Swal.fire('Gagal', 'Terjadi kesalahan saat menyimpan data', 'error');
        }
    });
});
</script>
