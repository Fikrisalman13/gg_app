<?php
// form_ph_air.php - Form input pH Air
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

$username = htmlspecialchars($_SESSION['NamaLengkap'] ?? '');
$dateNow = date('Y-m-d');
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>
<div class="card">
  <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><b>Input Data PH Air</b></div>
  <div class="card-body">
    <form method="POST" id="formPhAir" style="margin-bottom:0;">
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
      <div class="mt-3 mb-2"><b>Nilai pH Air per Bak</b></div>
      <div class="table-responsive">
        <table class="table table-bordered table-sm mb-0">
          <thead>
            <tr>
              <th>Nama Bak</th><th>pH Air</th>
              <th>Nama Bak</th><th>pH Air</th>
            </tr>
          </thead>
          <tbody>
          <?php
          $bakList = [
            ['PekatBesar', 'Pekat Besar'],
            ['PekatKecil', 'Pekat Kecil'],
            ['Anoxit', 'Anoxit'],
            ['Daff1', 'Daff 1'],
            ['Daff2', 'Daff 2'],
            ['Daff3', 'Daff 3'],
            ['Aerasi1', 'Aerasi 1'],
            ['Aerasi2', 'Aerasi 2'],
            ['Aerasi3', 'Aerasi 3'],
            ['Aerasi4', 'Aerasi 4'],
            ['SelokanPekat', 'Selokan Pekat'],
            ['SelokanReaktif', 'Selokan Reaktif'],
            ['Sedimen', 'Sedimen'],
            ['Outlet', 'Outlet'],
            ['EqualSum', 'Equal Sum'],
          ];
          for ($i = 0; $i < count($bakList); $i += 2) {
            echo '<tr>';
            // Kolom 1
            echo '<td>' . $bakList[$i][1] . '</td>';
            echo '<td><input type="number" step="0.01" min="0" max="14" class="form-control" name="' . $bakList[$i][0] . '"></td>';
            // Kolom 2
            if (isset($bakList[$i+1])) {
              echo '<td>' . $bakList[$i+1][1] . '</td>';
              echo '<td><input type="number" step="0.01" min="0" max="14" class="form-control" name="' . $bakList[$i+1][0] . '"></td>';
            } else {
              echo '<td></td><td></td>';
            }
            echo '</tr>';
          }
          ?>
          </tbody>
        </table>
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
$('#formPhAir').on('submit', function(e) {
    e.preventDefault();
    var formData = $(this).serialize();
    $.ajax({
        url: 'simpan_ph_air.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(resp) {
            if (resp.success) {
                Swal.fire('Berhasil', 'Data pH Air berhasil disimpan!', 'success');
                $('#modalPilihParameter').modal('hide');
                $('#formPhAir')[0].reset();
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
