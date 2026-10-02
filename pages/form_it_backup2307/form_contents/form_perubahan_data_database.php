<?php
// Form Pengajuan Perubahan Data Via Database
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
<form method="POST" id="formPerubahanDataDatabase" style="margin-bottom:0;">
    <input type="hidden" name="form_type" value="perubahan_data_database">
    <?php if (isset($_GET['edit']) && $_GET['edit'] == 1 && isset($_GET['ticket'])): ?>
    <input type="hidden" name="ticket" value="<?= htmlspecialchars($_GET['ticket']) ?>">
    <input type="hidden" name="action" value="update">
    <?php endif; ?>
    
    <!-- HEADER INFO -->
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
                <input type="date" name="tgl_pengajuan" class="form-control form-control-sm tgl-today" readonly>
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
                <input type="text" name="bagian" class="form-control form-control-sm" value="<?= htmlspecialchars($bagian) ?>" readonly>
            </div>
        </div>
    </div>
    
    <hr class="my-2">

    <!-- REQUEST TYPE -->
    <div class="form-group mb-2">
        <label style="font-size:.875rem;font-weight:600;">Mengajukan permintaan untuk : <span class="text-danger">*</span></label>
        <div class="d-flex align-items-center">
            <div class="form-check me-3 mr-3">
                <input class="form-check-input" type="checkbox" name="req_add" id="reqAdd" value="1">
                <label class="form-check-label" for="reqAdd">Add</label>
            </div>
            <div class="form-check me-3 mr-3">
                <input class="form-check-input" type="checkbox" name="req_edit" id="reqEdit" value="1">
                <label class="form-check-label" for="reqEdit">Edit</label>
            </div>
            <div class="form-check me-3 mr-3">
                <input class="form-check-input" type="checkbox" name="req_delete" id="reqDelete" value="1">
                <label class="form-check-label" for="reqDelete">Delete</label>
            </div>
        </div>
    </div>

    <!-- APPLICATION -->
    <div class="form-group mb-2">
        <label style="font-size:.875rem;font-weight:600;">Aplikasi : <span class="text-danger">*</span></label>
        <div class="d-flex align-items-center flex-wrap">
            <div class="form-check me-3 mr-3">
                <input class="form-check-input" type="checkbox" name="app_proint" id="appProint" value="1">
                <label class="form-check-label" for="appProint">Proint</label>
            </div>
            <div class="form-check me-3 mr-3">
                <input class="form-check-input" type="checkbox" name="app_hris" id="appHris" value="1">
                <label class="form-check-label" for="appHris">HRIS</label>
            </div>
            <div class="form-check d-flex align-items-center">
                <input class="form-check-input me-1 mr-1" type="checkbox" name="app_lainnya" id="appLainnya" value="1">
                <label class="form-check-label me-2 mr-2" for="appLainnya">Lainnya :</label>
                <input type="text" name="app_lainnya_text" id="appLainnyaText" class="form-control form-control-sm" style="width:200px;" disabled>
            </div>
        </div>
    </div>

    <!-- PERUBAHAN -->
    <div class="form-group mb-2">
        <label class="mb-1" style="font-size:.875rem;font-weight:600;">Perubahan : <span class="text-danger">*</span></label>
        <textarea name="perubahan" class="form-control form-control-sm" rows="5" required placeholder="Deskripsikan perubahan yang diminta..."></textarea>
    </div>

    <!-- KETERANGAN -->
    <div class="form-group mb-2">
        <label class="mb-1" style="font-size:.875rem;font-weight:600;">Keterangan/Alasan :</label>
        <textarea name="keterangan" class="form-control form-control-sm" rows="2" placeholder="Alasan perubahan..."></textarea>
    </div>

    <script>
    (function waitForjQuery(){
        var start = Date.now();
        function tick(){
            if (window.jQuery) return init(window.jQuery);
            if (Date.now() - start > 10000) { console.error('Form Perubahan Data: jQuery not found after 10s'); return; }
            setTimeout(tick, 100);
        }
        tick();
        function init($){
            // Set Today's Date
            var today = new Date();
            var isoDate = today.toISOString().slice(0,10);
            $('.tgl-today').val(isoDate);

            // Handle App Lainnya Checkbox
            $('#appLainnya').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#appLainnyaText').prop('disabled', false).focus();
                } else {
                    $('#appLainnyaText').prop('disabled', true).val('');
                }
            });

            // Defensive submit handling (Shared logic with other forms)
            window._allowSubmit = false;
            $('#formPerubahanDataDatabase').off('submit.myTTD').on('submit.myTTD', function(e){
                if (!window._allowSubmit) {
                    e.preventDefault();
                    if (!window._skipTTDCheckOnce) {
                        if (typeof window.validateTTDBeforeSubmit === 'function') {
                            window.validateTTDBeforeSubmit().then(function(ok){
                                if(ok) {
                                    window._allowSubmit = true;
                                    $('#formPerubahanDataDatabase')[0].submit();
                                }
                            });
                        } else {
                            // Fallback
                            console.warn('validateTTDBeforeSubmit not found');
                            window._allowSubmit = true;
                            $('#formPerubahanDataDatabase')[0].submit();
                        }
                    } else {
                        window._allowSubmit = true;
                        $('#formPerubahanDataDatabase')[0].submit();
                    }
                    return false;
                }
                window._allowSubmit = false;
                return true;
            });
        }
    })();
    </script>

    <?php include 'components/signature_canvas.php'; ?>
</form>
