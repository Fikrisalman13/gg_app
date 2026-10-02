<?php
// form_edit_ph_air.php - Form edit data PH Air
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

$id = $_GET['id'] ?? '';
if (!$id) {
    echo '<div class="alert alert-danger">Parameter tidak lengkap.</div>';
    exit;
}
$sql = "SELECT * FROM dbo.PH_Air WHERE Id = ?";
$params = [$id];
$stmt = sqlsrv_query($conn, $sql, $params);
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$row) {
    echo '<div class="alert alert-danger">Data tidak ditemukan.</div>';
    exit;
}
$username = htmlspecialchars($_SESSION['NamaLengkap'] ?? '');
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>
<div class="card">
  <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><b>Edit Data PH Air</b></div>
  <div class="card-body">
    <form method="POST" id="formEditPhAir" style="margin-bottom:0;">
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
            <option value="1" <?= $row['Shift']=='1'?'selected':'' ?>>Pagi</option>
            <option value="2" <?= $row['Shift']=='2'?'selected':'' ?>>Siang</option>
            <option value="3" <?= $row['Shift']=='3'?'selected':'' ?>>Malam</option>
          </select>
        </div>
      </div>
      <div class="mt-3 mb-2"><b>Nilai pH Air per Bak</b></div>
      <div class="row">
        <?php
        $baks = [
          'PekatBesar' => 'Pekat Besar',
          'PekatKecil' => 'Pekat Kecil',
          'Anoxit' => 'Anoxit',
          'Daff1' => 'Daff 1',
          'Daff2' => 'Daff 2',
          'Daff3' => 'Daff 3',
          'Aerasi1' => 'Aerasi 1',
          'Aerasi2' => 'Aerasi 2',
          'Aerasi3' => 'Aerasi 3',
          'Aerasi4' => 'Aerasi 4',
          'SelokanPekat' => 'Selokan Pekat',
          'SelokanReaktif' => 'Selokan Reaktif',
          'Sedimen' => 'Sedimen',
          'Outlet' => 'Outlet',
          'EqualSum' => 'Equal Sum',
        ];
        foreach ($baks as $key => $label): ?>
        <div class="col-md-6 mb-2">
          <div class="input-group">
            <span class="input-group-text" style="min-width:110px;"> <?= $label ?> </span>
            <input type="number" step="0.01" min="0" max="14" class="form-control" name="<?= $key ?>" value="<?= htmlspecialchars($row[$key]) ?>">
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="mt-3 d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>
<script>
$('#formEditPhAir').on('submit', function(e) {
    e.preventDefault();
    var formData = $(this).serialize();
    $.ajax({
        url: 'update_ph_air.php',
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
