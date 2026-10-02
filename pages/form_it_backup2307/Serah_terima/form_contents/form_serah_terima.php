<?php
if (!isset($conn) || $conn === false) {
    $koneksiPath = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
    if (file_exists($koneksiPath)) {
        require_once $koneksiPath;
    }
}

$username = htmlspecialchars($_SESSION['NamaLengkap'] ?? '');
$jabatan = '';
$departemen = '';
$bagian = '';

if ($username !== '' && isset($conn) && $conn !== false) {
    $sql = "SELECT m_jab.jabatan, m_dept.dept, m_bag.bagian
            FROM dbo.m_emp
            LEFT JOIN dbo.m_jab ON m_emp.id_jab = m_jab.id_jab
            LEFT JOIN dbo.m_subbag ON m_emp.id_subbag = m_subbag.id_subbag
            LEFT JOIN dbo.m_bag ON m_subbag.id_bag = m_bag.id_bag
            LEFT JOIN dbo.m_dept ON m_bag.id_dept = m_dept.id_dept
            WHERE m_emp.nama_lengkap = ?";
    $stmt = sqlsrv_query($conn, $sql, [$username]);
    if ($stmt !== false) {
        $emp = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($emp) {
            $jabatan = $emp['jabatan'] ?? '';
            $departemen = $emp['dept'] ?? '';
            $bagian = $emp['bagian'] ?? '';
        }
        sqlsrv_free_stmt($stmt);
    }
}

$defaultDeskripsi = "Aplikasi ini dibuat berdasarkan permintaan user dengan tujuan:\n"
    . "- Meningkatkan efisiensi dalam proses pengelolaan dan pengumpulan data yang sebelumnya dilakukan secara manual menggunakan Microsoft Excel.\n"
    . "- Menyediakan sistem yang lebih terstruktur untuk proses input dan penyimpanan data.\n"
    . "- Memudahkan akses dan penarikan data oleh pihak-pihak yang berkepentingan.\n\n"
    . "Daftar modul aplikasi terlampir.";
?>

<form method="POST" id="formSerahTerimaAplikasi" style="margin-bottom:0;">
    <input type="hidden" name="form_type" value="serah_terima_aplikasi">

    <div class="text-center mb-3">
        <h6 class="font-weight-bold mb-1">SERAH TERIMA HASIL PEMBUATAN/PENGEMBANGAN APLIKASI</h6>
        <small class="text-muted">SUM-FM-IT-037</small>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group mb-2">
                <label class="mb-1 small font-weight-bold">Tanggal Serah Terima <span class="text-danger">*</span></label>
                <input type="date" name="tanggal_serah_terima" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group mb-2">
                <label class="mb-1 small font-weight-bold">Tanggal Selesai <span class="text-danger">*</span></label>
                <input type="date" name="tanggal_selesai" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
            </div>
        </div>
    </div>

    <div class="border rounded p-3 mb-3">
        <div class="font-weight-bold mb-2">1. Informasi Umum</div>
        <div class="form-group mb-2">
            <label class="mb-1 small font-weight-bold">Nama Aplikasi <span class="text-danger">*</span></label>
            <input type="text" name="nama_aplikasi" class="form-control form-control-sm" required>
        </div>
        <div class="form-group mb-2">
            <label class="mb-1 small font-weight-bold">Nama Modul <span class="text-danger">*</span></label>
            <textarea name="nama_modul" class="form-control form-control-sm" rows="2" required></textarea>
        </div>
        <div class="form-group mb-0">
            <label class="mb-1 small font-weight-bold">Diminta Oleh (User dan Departemen) <span class="text-danger">*</span></label>
            <input type="text" name="diminta_oleh" class="form-control form-control-sm" value="<?= htmlspecialchars(trim($username . ($departemen ? ' - ' . $departemen : ''))) ?>" required>
        </div>
    </div>

    <div class="border rounded p-3 mb-3">
        <div class="font-weight-bold mb-2">2. Deskripsi Aplikasi</div>
        <textarea name="deskripsi_aplikasi" class="form-control form-control-sm" rows="7" required><?= htmlspecialchars($defaultDeskripsi) ?></textarea>
    </div>

    <div class="border rounded p-3 mb-3">
        <div class="font-weight-bold mb-2">3. Hasil Pengujian</div>
        <label class="mb-1 small font-weight-bold">a. Status Testing <span class="text-danger">*</span></label>
        <div class="d-flex flex-wrap border rounded p-2 mb-2" style="gap:18px;">
            <div class="form-check">
                <input class="form-check-input testing-check" type="checkbox" name="status_testing_it" id="statusTestingIt" value="1">
                <label class="form-check-label" for="statusTestingIt">Sudah diuji oleh IT</label>
            </div>
            <div class="form-check">
                <input class="form-check-input testing-check" type="checkbox" name="status_testing_user" id="statusTestingUser" value="1">
                <label class="form-check-label" for="statusTestingUser">Sudah diuji oleh User</label>
            </div>
        </div>

        <label class="mb-1 small font-weight-bold">b. Hasil Testing <span class="text-danger">*</span></label>
        <div class="border rounded p-2 mb-2">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="hasil_testing" id="hasilTestingSesuai" value="sesuai" required>
                <label class="form-check-label" for="hasilTestingSesuai">Sesuai dengan permintaan</label>
            </div>
            <div class="form-check mt-1">
                <input class="form-check-input" type="radio" name="hasil_testing" id="hasilTestingRevisi" value="revisi" required>
                <label class="form-check-label" for="hasilTestingRevisi">Perlu revisi</label>
            </div>
            <textarea name="catatan_revisi" id="catatanRevisi" class="form-control form-control-sm mt-2" rows="3" placeholder="Jelaskan revisi bila ada..." disabled></textarea>
        </div>
    </div>

    <div class="border rounded p-3 mb-3">
        <div class="font-weight-bold mb-2">Pernyataan User</div>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="status_penerimaan" id="penerimaanSesuai" value="sesuai" required>
            <label class="form-check-label" for="penerimaanSesuai">Aplikasi telah sesuai dengan kebutuhan</label>
        </div>
        <div class="form-check mt-1">
            <input class="form-check-input" type="radio" name="status_penerimaan" id="penerimaanCatatan" value="catatan" required>
            <label class="form-check-label" for="penerimaanCatatan">Aplikasi diterima dengan catatan</label>
        </div>
        <div class="form-check mt-1">
            <input class="form-check-input" type="radio" name="status_penerimaan" id="penerimaanPerbaikan" value="perbaikan" required>
            <label class="form-check-label" for="penerimaanPerbaikan">Aplikasi masih membutuhkan perbaikan</label>
        </div>
        <textarea name="catatan_penerimaan" id="catatanPenerimaan" class="form-control form-control-sm mt-2" rows="3" placeholder="Catatan penerimaan/perbaikan bila ada..." disabled></textarea>
    </div>

    <input type="hidden" name="nama_pemohon" value="<?= $username ?>">
    <input type="hidden" name="jabatan" value="<?= htmlspecialchars($jabatan) ?>">
    <input type="hidden" name="departemen" value="<?= htmlspecialchars($departemen) ?>">
    <input type="hidden" name="bagian" value="<?= htmlspecialchars($bagian) ?>">

    <script>
    (function initFormSerahTerima(){
        function tick(){
            if (window.jQuery) return start(window.jQuery);
            setTimeout(tick, 100);
        }
        tick();

        function start($){
            $('input[name="hasil_testing"]').off('change.serah').on('change.serah', function(){
                var revisi = $('#hasilTestingRevisi').is(':checked');
                $('#catatanRevisi').prop('disabled', !revisi).prop('required', revisi);
                if (!revisi) $('#catatanRevisi').val('');
            });

            $('input[name="status_penerimaan"]').off('change.serah').on('change.serah', function(){
                var needNote = $('#penerimaanCatatan').is(':checked') || $('#penerimaanPerbaikan').is(':checked');
                $('#catatanPenerimaan').prop('disabled', !needNote).prop('required', needNote);
                if (!needNote) $('#catatanPenerimaan').val('');
            });

            $('#formSerahTerimaAplikasi').off('submit.serah').on('submit.serah', function(e){
                if (!$('.testing-check:checked').length) {
                    e.preventDefault();
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'warning', title: 'Validasi Gagal', text: 'Pilih minimal satu status testing.' });
                    } else {
                        alert('Pilih minimal satu status testing.');
                    }
                    return false;
                }
            });
        }
    })();
    </script>
    <?php include __DIR__ . '/../../form_contents/components/signature_canvas.php'; ?>
</form>
