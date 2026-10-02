<?php
// form_edit_cod_air.php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

$id = $_GET['id'] ?? '';
if (!$id) {
    echo '<div class="alert alert-danger">Parameter tidak lengkap.</div>';
    exit;
}
$sql = "SELECT * FROM dbo.COD_air WHERE Id = ?";
$stmt = sqlsrv_query($conn, $sql, [$id]);
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$row) {
    echo '<div class="alert alert-danger">Data tidak ditemukan.</div>';
    exit;
}

function val($row, $key) {
  if (!isset($row[$key]) || $row[$key] === null) return '';
  if ($row[$key] instanceof DateTime) {
    return $row[$key]->format('Y-m-d');
  }
  return htmlspecialchars($row[$key]);
}
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>
<div class="card" style="max-width:900px;margin:auto;">
  <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><b>Edit Data COD Air</b></div>
  <div class="card-body">
    <form method="POST" id="formEditCodAir">
      <input type="hidden" name="id" value="<?= $id ?>">
      <div class="row">
        <div class="col-md-3 mb-2">
          <label for="tanggal">Tanggal</label>
          <input type="date" class="form-control" name="tanggal" id="tanggal" value="<?= val($row, 'Tanggal') ?>" required>
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
              $selected = (val($row, 'Pengecek') == $nama) ? 'selected' : '';
              echo "<option value=\"$nama\" $selected>$nama</option>";
            }
            ?>
          </select>
        </div>
        <div class="col-md-3 mb-2">
          <label for="shift">Sampel Air</label>
          <select class="form-control" name="shift" id="shift" required>
            <option value="1" <?= val($row, 'Shift') == '1' ? 'selected' : '' ?>>Pagi</option>
            <option value="2" <?= val($row, 'Shift') == '2' ? 'selected' : '' ?>>Siang</option>
            <option value="3" <?= val($row, 'Shift') == '3' ? 'selected' : '' ?>>Malam</option>
          </select>
        </div>
        <div class="col-md-3 mb-2">
          <label for="nilai_kalibrasi">Nilai Kalibrasi</label>
          <input type="number" step="0.01" min="0" class="form-control" name="nilai_kalibrasi" id="nilai_kalibrasi" value="<?= val($row, 'NilaiKalibrasi') ?>">
        </div>
      </div>
      <div class="mt-3 mb-2"><b>Nilai COD Air per Bak</b></div>
      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm mb-0" style="background:#fff;">
          <thead class="thead-light">
            <tr>
              <th style="width: 25%">Nama Bak</th><th style="width: 25%">Nilai COD</th>
              <th style="width: 25%">Nama Bak</th><th style="width: 25%">Nilai COD</th>
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
            $bak1Key = $bakList[$i][0];
            $v1 = val($row, $bak1Key);
            echo '<td class="align-middle">' . $bakList[$i][1] . '</td>';
            echo '<td><input type="number" step="0.01" min="0" class="form-control cod-bak" name="' . $bak1Key . '" value="' . $v1 . '" autocomplete="off"></td>';
            // Kolom 2
            if (isset($bakList[$i+1])) {
              $bak2Key = $bakList[$i+1][0];
              $v2 = val($row, $bak2Key);
              echo '<td class="align-middle">' . $bakList[$i+1][1] . '</td>';
              echo '<td><input type="number" step="0.01" min="0" class="form-control cod-bak" name="' . $bak2Key . '" value="' . $v2 . '" autocomplete="off"></td>';
            } else {
              echo '<td></td><td></td>';
            }
            echo '</tr>';
          }
          ?>
          </tbody>
        </table>
      </div>
      <div class="mt-3 d-flex justify-content-between">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>
<script>
$('#formEditCodAir').on('submit', function(e) {
    e.preventDefault();
    var formData = $(this).serialize();
    $.ajax({
        url: 'update_cod_air.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(resp) {
            if (resp.success) {
                Swal.fire('Berhasil', 'Data berhasil diupdate!', 'success');
                $('#modalDetailPhAir').modal('hide');
                if (typeof window.reloadPhAirTable === 'function') window.reloadPhAirTable();
            } else {
                Swal.fire('Gagal', resp.error || 'Gagal update data', 'error');
            }
        },
        error: function() {
            Swal.fire('Gagal', 'Terjadi kesalahan saat update data', 'error');
        }
    });
});
</script>
