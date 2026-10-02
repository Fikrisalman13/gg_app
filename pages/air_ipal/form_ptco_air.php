<?php
// form_ptco_air.php - Form input PTCO Air
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

$username = htmlspecialchars($_SESSION['NamaLengkap'] ?? '');
$dateNow = date('Y-m-d');
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>
<div class="card mx-auto" style="max-width:900px;min-width:700px;float:none;">
  <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><b>Input Data PTCO Air</b></div>
  <div class="card-body">
    <form method="POST" id="formPtcoAir" style="margin-bottom:0;">
      <div class="row align-items-end mb-3">
        <div class="col-md-4">
          <label for="tanggal" class="mb-1">Tanggal</label>
          <input type="date" class="form-control" name="tanggal" id="tanggal" value="<?= $dateNow ?>" required>
        </div>
        <div class="col-md-4">
          <label for="pengecek" class="mb-1">Pengecek</label>
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
        <div class="col-md-4">
          <label for="shift" class="mb-1">Sampel Air</label>
          <select class="form-control" name="shift" id="shift" required>
            <option value="">Pilih Shift</option>
            <option value="1">Pagi</option>
            <option value="2">Siang</option>
            <option value="3">Malam</option>
          </select>
        </div>
      </div>

      <div class="mt-2 mb-2"><b>Nilai PTCO per Bak</b></div>
      <div class="table-responsive">
        <table class="table table-bordered table-sm mb-0">
          <thead>
            <tr>
              <th style="width:50%">Nama Bak</th>
              <th style="width:50%">Nilai</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $bakList = [
              ['EqualSum', 'Equal Sum'],
              ['Daff1', 'Daff 1'],
              ['Daff2', 'Daff 2'],
              ['Daff3', 'Daff 3'],
              ['Aerasi1', 'Aerasi 1'],
              ['SedimenBiologi', 'Sedimen Biologi'],
              ['Flogulan', 'Flogulan'],
              ['PostSedimen', 'Post Sedimen'],
              ['Dwatring', 'Dwatring'],
              ['Outlet', 'Outlet']
            ];
            foreach ($bakList as $item) {
              echo '<tr>';
              echo '<td>' . $item[1] . '</td>';
              echo '<td><input type="number" step="0.01" min="0" class="form-control" name="' . $item[0] . '" autocomplete="off"></td>';
              echo '</tr>';
            }
            ?>
          </tbody>
        </table>
      </div>

      <input type="hidden" name="created_by" value="<?= $_SESSION['UserName'] ?? $username ?>">
      <div class="mt-3 d-flex justify-content-between">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>">Simpan</button>
      </div>
    </form>
  </div>
</div>
<script>
$('#formPtcoAir').on('submit', function(e) {
    e.preventDefault();
    var formData = $(this).serialize();
    $.ajax({
        url: 'simpan_ptco_air.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(resp) {
            if (resp.success) {
                Swal.fire('Berhasil', 'Data PTCO Air berhasil disimpan!', 'success');
                $('#modalPilihParameter').modal('hide');
                $('#formPtcoAir')[0].reset();
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
