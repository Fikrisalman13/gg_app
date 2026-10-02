<?php
// form_edit_mlss_air.php
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
if (!isset($_GET['id'])) {
    echo '<div class="alert alert-danger">ID data tidak ditemukan.</div>';
    exit;
}
$id = intval($_GET['id']);
// Ganti query ke tabel MLSS_Air
$sql = "SELECT * FROM MLSS_Air WHERE Id = $id";
$query = sqlsrv_query($conn, $sql);
$data = $query ? sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC) : null;
if (!$data) {
    echo '<div class="alert alert-danger">Data MLSS tidak ditemukan.</div>';
    exit;
}
// Ambil theme dan list pengecek
$username = htmlspecialchars($_SESSION['NamaLengkap'] ?? '');
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>
<div class="card">
    <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><b>Edit Data MLSS Air</b></div>
    <div class="card-body">
        <form method="POST" id="formEditMLSSAir" style="margin-bottom:0;">
            <input type="hidden" name="id" value="<?= $data['Id'] ?>">
            <div class="row align-items-end mb-2">
                <div class="col-md-4">
                    <label for="tanggal" class="mb-1">Tanggal</label>
                    <input type="date" class="form-control" name="Tanggal" id="tanggal"
                        value="<?= $data['Tanggal'] instanceof DateTime ? $data['Tanggal']->format('Y-m-d') : htmlspecialchars($data['Tanggal']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="pengecek" class="mb-1">Pengecek</label>
                    <select class="form-control" name="Pengecek" id="pengecek" required>
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
                            $selected = ($data['Pengecek'] == $nama) ? 'selected' : '';
                            echo "<option value=\"$nama\" $selected>$nama</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="shift" class="mb-1">Sampel Air</label>
                    <select class="form-control" name="Shift" id="shift" required>
                        <option value="">Pilih Shift</option>
                        <option value="1" <?= $data['Shift']=='1'?'selected':'' ?>>Pagi</option>
                        <option value="2" <?= $data['Shift']=='2'?'selected':'' ?>>Siang</option>
                        <option value="3" <?= $data['Shift']=='3'?'selected':'' ?>>Malam</option>
                    </select>
                </div>
            </div>
            <div class="mt-3 mb-2"><b>Nilai MLSS per Bak</b></div>
            <div class="row">
                <?php
                $baks = [
                    'Aerasi1' => 'Aerasi 1',
                    'Aerasi2' => 'Aerasi 2',
                    'Aerasi3' => 'Aerasi 3',
                    'Aerasi4' => 'Aerasi 4',
                    'Raspam' => 'Raspam'
                ];
                foreach ($baks as $key => $label): ?>
                <div class="col-md-6 mb-2">
                    <div class="input-group">
                        <span class="input-group-text" style="min-width:110px;"> <?= $label ?> </span>
                        <input type="number" min="0" step="0.01" class="form-control" name="<?= $key ?>" value="<?= htmlspecialchars($data[$key] ?? '') ?>">
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
$('#formEditMLSSAir').on('submit', function(e) {
    e.preventDefault();
    var formData = $(this).serialize();
    $.ajax({
        url: 'proses_edit_mlss_air.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(resp) {
            if (resp && resp.success) {
                Swal.fire('Berhasil', 'Data MLSS berhasil diupdate!', 'success');
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
