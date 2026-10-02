<?php
// form_dh_ipab.php - Form input DH IPAB
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

$username = htmlspecialchars($_SESSION['NamaLengkap'] ?? '');
$dateNow = date('Y-m-d');
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>
<div class="card">
  <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><b>Input Data DH</b></div>
  <div class="card-body">
    <form method="POST" id="formDhIpab" style="margin-bottom:0;">
      <div class="row">
        <div class="col-md-4 mb-2">
          <label for="tanggal">Tanggal</label>
          <input type="date" class="form-control" name="tanggal" id="tanggal" value="<?= $dateNow ?>" required>
        </div>
        <div class="col-md-4 mb-2">
          <label for="pengecek">Pengecek</label>
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
        <div class="col-md-4 mb-2">
          <label for="shift">Sampel Air</label>
          <select class="form-control" name="shift" id="shift" required>
            <option value="">Pilih Shift</option>
            <option value="1">Pagi</option>
            <option value="2">Siang</option>
            <option value="3">Malam</option>
          </select>
        </div>
      </div>
      <div class="mt-3 mb-2"><b>Nilai DH per Bak</b></div>
      <div class="row">
        <div class="col-md-6 mb-2">
          <label class="mb-1">Bak 3</label>
          <input type="text" inputmode="numeric" class="form-control" name="Bak3">
        </div>
        <div class="col-md-6 mb-2">
          <label class="mb-1">Bak 4</label>
          <input type="text" inputmode="numeric" class="form-control" name="Bak4">
        </div>
      </div>
      <input type="hidden" name="created_by" value="<?= $username ?>">
      <div class="mt-3 d-flex justify-content-between">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>">Simpan</button>
      </div>
    </form>
  </div>
</div>
<script>
$('#formDhIpab').on('submit', function(e) {
    e.preventDefault();
    var formData = $(this).serialize();
    $.ajax({
        url: 'simpan_dh_ipab.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(resp) {
            if (resp.success) {
                Swal.fire('Berhasil', 'Data DH berhasil disimpan!', 'success');
                $('#modalPilihParameter').modal('hide');
                $('#formDhIpab')[0].reset();
                if (typeof window.reloadIpabTable === 'function') window.reloadIpabTable();
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
