<?php
// form_cod_air.php - Form input COD Air
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

$username = htmlspecialchars($_SESSION['NamaLengkap'] ?? '');
$dateNow = date('Y-m-d');
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>
<div class="card" style="max-width:900px;margin:auto;">
  <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><b>Input Data COD Air</b></div>
  <div class="card-body">
    <form method="POST" id="formCodAir" style="margin-bottom:0;">
      <div class="row">
        <div class="col-md-3 mb-2">
          <label for="tanggal">Tanggal</label>
          <input type="date" class="form-control" name="tanggal" id="tanggal" value="<?= $dateNow ?>" required>
        </div>
        <div class="col-md-3 mb-2">
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
        <div class="col-md-3 mb-2">
          <label for="shift">Sampel Air</label>
          <select class="form-control" name="shift" id="shift" required>
            <option value="">Pilih Shift</option>
            <option value="1">Pagi</option>
            <option value="2">Siang</option>
            <option value="3">Malam</option>
          </select>
        </div>
      </div>
      <div class="row mb-2">
        <div class="col-md-3 ms-auto">
          <label for="nilai_kalibrasi">Nilai Kalibrasi</label>
          <input type="number" step="0.01" min="0" class="form-control" name="nilai_kalibrasi" id="nilai_kalibrasi">
        </div>
      </div>
      <div class="mt-3 mb-2"><b>Nilai COD Air per Bak</b></div>
      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm mb-0" style="background:#fff;">
          <thead class="thead-light">
            <tr>
              <th style="width: 22%">Nama Bak</th><th style="width: 18%">Nilai COD</th>
              <th style="width: 22%">Nama Bak</th><th style="width: 18%">Nilai COD</th>
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
            ['Dwatring', 'Dwatring'],
            ['Sedimen', 'Sedimen'],
            ['Outlet', 'Outlet'],
            ['EqualSum', 'Equal Sum'],
          ];
          for ($i = 0; $i < count($bakList); $i += 2) {
            echo '<tr>';
            // Kolom 1
            echo '<td class="align-middle">' . $bakList[$i][1] . '</td>';
            echo '<td><input type="number" step="0.01" min="0" class="form-control cod-bak" name="' . $bakList[$i][0] . '" autocomplete="off"></td>';
            // Kolom 2
            if (isset($bakList[$i+1])) {
              echo '<td class="align-middle">' . $bakList[$i+1][1] . '</td>';
              echo '<td><input type="number" step="0.01" min="0" class="form-control cod-bak" name="' . $bakList[$i+1][0] . '" autocomplete="off"></td>';
            } else {
              echo '<td></td><td></td>';
            }
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
$('#formCodAir').on('submit', function(e) {
    e.preventDefault();
    var formData = $(this).serialize();
    $.ajax({
        url: 'simpan_cod_air.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(resp) {
            if (resp.success) {
                Swal.fire('Berhasil', 'Data COD Air berhasil disimpan!', 'success');
                $('#modalPilihParameter').modal('hide');
                $('#formCodAir')[0].reset();
                if (typeof window.reloadCodAirTable === 'function') window.reloadCodAirTable();
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
