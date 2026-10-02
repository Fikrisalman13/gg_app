<?php
// edit_dh_ipab.php - Form edit data DH IPAB
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

$id = $_GET['id'] ?? '';
if (!$id) {
    echo '<div class="alert alert-danger">Parameter tidak lengkap.</div>';
    exit;
}

$sql = "SELECT * FROM dbo.dh_ipab WHERE Id = ?";
$params = [$id];
$stmt = sqlsrv_query($conn, $sql, $params);
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$row) {
    echo '<div class="alert alert-danger">Data tidak ditemukan.</div>';
    exit;
}

$username = htmlspecialchars($_SESSION['NamaLengkap'] ?? '');
$themeColor = $_SESSION['Theme'] ?? 'primary';

$shiftValue = $row['Shift'];
if ($shiftValue === 'Pagi') $shiftValue = '1';
if ($shiftValue === 'Siang') $shiftValue = '2';
if ($shiftValue === 'Malam') $shiftValue = '3';
?>
<div class="card">
  <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><b>Edit Data DH</b></div>
  <div class="card-body">
    <form method="POST" id="formEditDhIpab" style="margin-bottom:0;">
      <input type="hidden" name="id" value="<?= $id ?>">
      <div class="row">
        <div class="col-md-4 mb-2">
          <label for="tanggal">Tanggal</label>
          <input type="date" class="form-control" name="tanggal" id="tanggal" value="<?= $row['Tanggal']->format('Y-m-d') ?>" required>
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
              $selected = ($row['Pengecek'] == $nama) ? 'selected' : '';
              echo "<option value=\"$nama\" $selected>$nama</option>";
            }
            ?>
          </select>
        </div>
        <div class="col-md-4 mb-2">
          <label for="shift">Sampel Air</label>
          <select class="form-control" name="shift" id="shift" required>
            <option value="">Pilih Shift</option>
            <option value="1" <?= $shiftValue=='1'?'selected':'' ?>>Pagi</option>
            <option value="2" <?= $shiftValue=='2'?'selected':'' ?>>Siang</option>
            <option value="3" <?= $shiftValue=='3'?'selected':'' ?>>Malam</option>
          </select>
        </div>
      </div>
      <div class="mt-3 mb-2"><b>Nilai DH per Bak</b></div>
      <div class="table-responsive">
        <table class="table table-bordered table-sm mb-0">
          <thead>
            <tr>
              <th>Nama Bak</th><th>DH</th>
              <th>Nama Bak</th><th>DH</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Bak 3</td>
              <td><input type="text" inputmode="numeric" class="form-control" name="Bak_3" value="<?= htmlspecialchars($row['Bak_3'] ?? '') ?>"></td>
              <td>Bak 4</td>
              <td><input type="text" inputmode="numeric" class="form-control" name="Bak_4" value="<?= htmlspecialchars($row['Bak_4'] ?? '') ?>"></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="mt-3 d-flex justify-content-end">
        <button type="button" class="btn btn-secondary mr-2" data-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>
<script>
$('#formEditDhIpab').on('submit', function(e) {
    e.preventDefault();
    var formData = $(this).serialize();
    $.ajax({
        url: 'update_dh_ipab.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(resp) {
            if (resp.success) {
                Swal.fire('Berhasil', 'Data berhasil diupdate!', 'success');
                $('#modalDetailIpab').modal('hide');
                if (typeof window.reloadIpabTable === 'function') window.reloadIpabTable();
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
