<?php
// Form Pengajuan Rekaman CCTV
// Mirip struktur form perangkat: ambil jabatan/dept/bagian dari session & DB
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
<form method="POST" id="formPengajuanCCTV" style="margin-bottom:0;">
    <input type="hidden" name="form_type" value="pengajuan_cctv">
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
                <input type="text" name="tgl_pengajuan" class="form-control form-control-sm tgl-pengajuan" readonly placeholder="DD-MM-YYYY">
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
                <label class="mb-1" style="font-size:.875rem;font-weight:600;">Area (Bagian)</label>
                <input type="text" name="area" class="form-control form-control-sm" value="<?= htmlspecialchars($bagian) ?>" readonly>
            </div>
        </div>
    </div>
    <hr class="my-2">
    <div class="form-group mb-2">
        <label style="font-size:.875rem;font-weight:600;">Permintaan Rekaman CCTV</label>
        <div class="row">
            <div class="col-md-6">
                <div class="border rounded p-2 mb-2">
                    <label class="mb-1" style="font-size:.75rem;font-weight:600;">Periode 1</label>
                    <div class="form-group mb-1">
                        <input type="date" name="tanggal_1" class="form-control form-control-sm tgl-cctv" placeholder="Tanggal" required>
                    </div>
                    <div class="d-flex gap-2 mb-1">
                        <input type="time" name="jam_mulai_1" class="form-control form-control-sm" required>
                        <span class="align-self-center px-1" style="font-size:.8rem;">s/d</span>
                        <input type="time" name="jam_selesai_1" class="form-control form-control-sm" required>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="border rounded p-2 mb-2">
                    <label class="mb-1" style="font-size:.75rem;font-weight:600;">Periode 2 (Opsional)</label>
                    <div class="form-group mb-1">
                        <input type="date" name="tanggal_2" class="form-control form-control-sm tgl-cctv" placeholder="Tanggal">
                    </div>
                    <div class="d-flex gap-2 mb-1">
                        <input type="time" name="jam_mulai_2" class="form-control form-control-sm">
                        <span class="align-self-center px-1" style="font-size:.8rem;">s/d</span>
                        <input type="time" name="jam_selesai_2" class="form-control form-control-sm">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="form-group mb-0">
        <label class="mb-1" style="font-size:.875rem;font-weight:600;">Keterangan</label>
        <textarea name="keterangan" class="form-control form-control-sm" rows="2" style="resize:vertical;" placeholder="Contoh: Area Gudang – Periksa aktivitas orang masuk saat jam istirahat" required></textarea>
    </div>
</form>


<script>
(function(){
    var start = Date.now();
    function tick(){
        if (window.jQuery) init(window.jQuery);
        else if (Date.now() - start > 10000) console.error('jQuery not found');
        else setTimeout(tick, 100);
    }
    tick();

    function init($){
        // Set tanggal otomatis
        var today = new Date();
        var day = today.getDate().toString().padStart(2, '0');
        var month = (today.getMonth() + 1).toString().padStart(2, '0');
        var year = today.getFullYear();
        var currentDate = day + '-' + month + '-' + year;
        $('.tgl-pengajuan').val(currentDate);
        
        // Helper: validate required fields before programmatic submit
        function validateCCTVForm() {
            var k = $.trim($('textarea[name="keterangan"]').val() || '');
            if (!k) {
                alert('Kolom Keterangan wajib diisi.');
                $('textarea[name="keterangan"]').focus();
                return false;
            }
            return true;
        }

        // Defensive submit handling
        window._allowSubmit = false;
        $('#formPengajuanCCTV').off('submit.myTTD').on('submit.myTTD', function(e){
            // If validation fails, stop everything
            if (!validateCCTVForm()) {
                e.preventDefault();
                return false;
            }

            if (!window._allowSubmit) {
                e.preventDefault();
                if (!window._skipTTDCheckOnce) {
                    // Trigger TTD check via the Main Save Button (which Component listens to)
                    // If this form has #btnSimpanForm, the component handles it.
                    // If not, we might need to rely on the component's fallback or trigger it manually.
                    
                    // Note: The component automatically finds #btnSimpanForm or #saveBtn.
                    // But we need to initiate the check. The component doesn't auto-intercept form submit 
                    // unless we use specific button clicks.
                    
                    // Actually, the Component handles the "Save TTD" click.
                    // We need to trigger the checking flow.
                    // THE COMPONENT PROVIDES validateTTDBeforeSubmit()!
                    
                    if (typeof window.validateTTDBeforeSubmit === 'function') {
                        window.validateTTDBeforeSubmit().then(function(ok){
                            if(ok) {
                                window._allowSubmit = true;
                                $('#formPengajuanCCTV')[0].submit();
                            }
                        });
                    } else {
                        // Fallback if component not ready
                        window._allowSubmit = true;
                        $('#formPengajuanCCTV')[0].submit();
                    }
                } else {
                    window._allowSubmit = true;
                    // form submits
                }
                return false;
            }
            // window._allowSubmit is true
            window._allowSubmit = false; // reset
            return true;
        });
    }
})();
</script>

<?php include 'components/signature_canvas.php'; ?>

