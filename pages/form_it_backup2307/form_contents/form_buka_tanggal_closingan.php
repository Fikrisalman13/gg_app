<?php
// Form Buka Tanggal Closingan
if (!isset($conn) || $conn === false) {
    $koneksiPath = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
    if (file_exists($koneksiPath)) {
        require_once $koneksiPath;
    } else {
        $fallbackPath = dirname(dirname(dirname(__DIR__))) . '/koneksi.php';
        if (file_exists($fallbackPath)) {
            require_once $fallbackPath;
        }
    }
}

$username   = htmlspecialchars($_SESSION['NamaLengkap'] ?? '');
$jabatan    = '';
$departemen = '';
$bagian     = '';

if (!empty($username) && isset($conn) && $conn !== false) {
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
            $jabatan    = $emp['jabatan'] ?? '';
            $departemen = $emp['dept'] ?? '';
            $bagian     = $emp['bagian'] ?? '';
        }
        sqlsrv_free_stmt($stmt);
    }
}
?>
<form method="POST" id="formBukaTanggalClosingan" style="margin-bottom:0;">
    <input type="hidden" name="form_type" value="buka_tanggal_closingan">
    <?php if (isset($_GET['edit']) && $_GET['edit'] == 1 && isset($_GET['ticket'])): ?>
    <input type="hidden" name="ticket" value="<?= htmlspecialchars($_GET['ticket']) ?>">
    <input type="hidden" name="action" value="update">
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-6">
            <div class="form-group mb-2">
                <label class="mb-1" style="font-size:.875rem;font-weight:600;">Nama Pemohon</label>
                <input type="text" name="nama_pemohon" class="form-control form-control-sm" value="<?= $username ?>" readonly required>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group mb-2">
                <label class="mb-1" style="font-size:.875rem;font-weight:600;">Jabatan</label>
                <input type="text" name="jabatan" class="form-control form-control-sm" value="<?= htmlspecialchars($jabatan) ?>" readonly>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-4">
            <div class="form-group mb-2">
                <label class="mb-1" style="font-size:.875rem;font-weight:600;">Tanggal Pengajuan</label>
                <input type="text" name="tgl_pengajuan" class="form-control form-control-sm tgl-pengajuan-buka-closing" readonly placeholder="DD-MM-YYYY">
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group mb-2">
                <label class="mb-1" style="font-size:.875rem;font-weight:600;">Departemen</label>
                <input type="text" name="departemen" class="form-control form-control-sm" value="<?= htmlspecialchars($departemen) ?>" readonly>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group mb-2">
                <label class="mb-1" style="font-size:.875rem;font-weight:600;">Bagian</label>
                <input type="text" name="area" class="form-control form-control-sm" value="<?= htmlspecialchars($bagian) ?>" readonly>
            </div>
        </div>
    </div>
    
    <hr class="my-2">
    
    <div class="form-group mb-2">
        <label style="font-size:.875rem;font-weight:600;">Mengajukan permintaan untuk : <span class="text-danger">*</span></label>
        <div class="d-flex align-items-center border rounded p-2" style="gap:20px;">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="request_gudang" id="reqGudang" value="1">
                <label class="form-check-label" for="reqGudang" style="font-weight:600;">Closingan Gudang</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="request_transaksi" id="reqTransaksi" value="1">
                <label class="form-check-label" for="reqTransaksi" style="font-weight:600;">Closingan Transaksi</label>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group mb-2">
                <label class="mb-1" style="font-size:.875rem;font-weight:600;">Buka Tgl <span class="text-danger">*</span></label>
                <input type="date" name="buka_tgl" class="form-control form-control-sm" required>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group mb-2">
                <label class="mb-1" style="font-size:.875rem;font-weight:600;">Gudang/Transaksi <span class="text-danger">*</span></label>
                <input type="text" name="gudang_transaksi" class="form-control form-control-sm" placeholder="Contoh: GD01 atau Transaksi Penjualan" required>
            </div>
        </div>
    </div>

    <div class="form-group mb-2">
        <label class="mb-1" style="font-size:.875rem;font-weight:600;">Keterangan/Alasan <span class="text-danger">*</span></label>
        <textarea name="keterangan" class="form-control form-control-sm" rows="3" style="resize:vertical;" placeholder="Isi alasan pembukaan closingan..." required></textarea>
    </div>

    <script>
    (function initFormBukaClosing(){
        function tick(){
            if (window.jQuery) return start(window.jQuery);
            setTimeout(tick, 100);
        }
        tick();
        function start($){
            // Standard Date Formatting (DD-MM-YYYY)
            var today = new Date();
            var day = String(today.getDate()).padStart(2, '0');
            var month = String(today.getMonth() + 1).padStart(2, '0');
            var year = today.getFullYear();
            var dateStr = day + '-' + month + '-' + year;

            $('.tgl-pengajuan-buka-closing').each(function(){
                if(!$(this).val()) $(this).val(dateStr);
            });
            
            // Signature validation
            window._allowSubmit = false;
            $('#formBukaTanggalClosingan').off('submit.closing').on('submit.closing', function(e){
                if (!window._allowSubmit) {
                    e.preventDefault();

                    // Standard Validation: At least one checkbox must be checked
                    var reqGudang = $('#reqGudang').is(':checked');
                    var reqTransaksi = $('#reqTransaksi').is(':checked');
                    
                    if (!reqGudang && !reqTransaksi) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Validasi Gagal',
                                text: 'Pilih setidaknya satu permintaan (Closingan Gudang atau Closingan Transaksi)!'
                            });
                        } else {
                            alert('Pilih setidaknya satu permintaan (Closingan Gudang atau Closingan Transaksi)!');
                        }
                        return false;
                    }

                    if (!window._skipTTDCheckOnce) {
                        if (typeof window.validateTTDBeforeSubmit === 'function') {
                            window.validateTTDBeforeSubmit().then(function(ok){
                                if(ok) {
                                    window._allowSubmit = true;
                                    $('#formBukaTanggalClosingan')[0].submit();
                                }
                            });
                        } else {
                            window._allowSubmit = true;
                            $('#formBukaTanggalClosingan')[0].submit();
                        }
                    } else {
                        window._allowSubmit = true;
                        $('#formBukaTanggalClosingan')[0].submit();
                    }
                    return false;
                }
                return true;
            });
        }
    })();
    </script>
    <?php include 'components/signature_canvas.php'; ?>
</form>
