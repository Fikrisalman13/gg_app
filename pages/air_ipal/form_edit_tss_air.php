<?php
// form_edit_tss_air.php - Form edit data TSS Air
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

$id = $_GET['id'] ?? '';
if (!$id) {
    echo '<div class="alert alert-danger">Parameter tidak lengkap.</div>';
    exit;
}
$sql = "SELECT * FROM dbo.TSS_Air WHERE Id = ?";
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
    <form method="POST" id="formEditTssAir" style="margin-bottom:0;">
      <input type="hidden" name="id" value="<?= $id ?>">
      <div class="ipal-tss-meta">
        <div>
          <label for="tanggal" class="form-label">Tanggal</label>
          <input type="date" class="form-control" name="tanggal" id="tanggal" value="<?= $row['Tanggal'] instanceof DateTime ? $row['Tanggal']->format('Y-m-d') : $row['Tanggal'] ?>" required>
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
              $selected = ($row['Pengecek'] == $nama) ? 'selected' : '';
              echo "<option value=\"$nama\" $selected>$nama</option>";
            }
            ?>
          </select>
        </div>
        <div>
          <label for="shift" class="form-label">Sampel Air</label>
          <select class="form-control" name="shift" id="shift" required>
            <option value="">Pilih Shift</option>
            <option value="1" <?= $row['Shift']=='1'?'selected':'' ?>>Pagi</option>
            <option value="2" <?= $row['Shift']=='2'?'selected':'' ?>>Siang</option>
            <option value="3" <?= $row['Shift']=='3'?'selected':'' ?>>Malam</option>
          </select>
        </div>
      </div>
      <div class="ipal-tss-section-title">
        <div>Nilai TSS Air per Bak</div>
        <span>mg/L</span>
      </div>
      <div class="ipal-tss-grid">
        <?php
        $baks = [
          'TSS_0' => 'TSS 0',
          'Pekat_Besar' => 'Pekat Besar',
          'Pekat_Kecil' => 'Pekat Kecil',
          'Dwatring' => 'Dwatring',
          'Anoxit' => 'Anoxit',
          'Equal' => 'Equal Sum',
          'Daff_1' => 'Daff 1',
          'Daff_2' => 'Daff 2',
          'Daff_3' => 'Daff 3',
          'Sedimen' => 'Sedimen',
          'Outlet' => 'Outlet',
        ];
        foreach ($baks as $key => $label): ?>
        <div class="ipal-tss-field">
          <label for="edit_tss_<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></label>
          <input type="number" min="0" inputmode="numeric" class="form-control" name="<?= htmlspecialchars($key) ?>" id="edit_tss_<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($row[$key] ?? '') ?>" placeholder="0">
        </div>
        <?php endforeach; ?>
      </div>
      <div class="mt-3 d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>">Simpan Perubahan</button>
      </div>
    </form>
</div>
<script>
$('#formEditTssAir input[type="number"]').on('focus', function() {
  this.select();
});
$('#formEditTssAir').on('submit', function(e) {
  e.preventDefault();
  var formData = $(this).serialize();
  $.ajax({
    url: 'update_tss_air.php',
    type: 'POST',
    data: formData,
    dataType: 'json',
    success: function(resp) {
      if (resp.success) {
        Swal.fire('Berhasil', 'Data berhasil diupdate!', 'success');
        $('#modalDetailPhAir').modal('hide');
        if (typeof window.reloadPhAirTable === 'function') window.reloadPhAirTable();
      } else {
        let debugMsg = '';
        if (resp.debug) {
          debugMsg = '<pre style="text-align:left;white-space:pre-wrap;">'+JSON.stringify(resp.debug, null, 2)+'</pre>';
        }
        Swal.fire({
          icon: 'error',
          title: 'Gagal',
          html: (resp.error || 'Gagal update data') + debugMsg
        });
      }
    },
    error: function(xhr) {
      let msg = 'Terjadi kesalahan saat update data';
      if (xhr.responseText) {
        try {
          let resp = JSON.parse(xhr.responseText);
          if (resp.error) msg += '<br>' + resp.error;
          if (resp.debug) msg += '<pre style="text-align:left;white-space:pre-wrap;">'+JSON.stringify(resp.debug, null, 2)+'</pre>';
        } catch(e) {}
      }
      Swal.fire({icon:'error',title:'Gagal',html:msg});
    }
  });
});
</script>
