<?php
// Form Pengajuan Akses Internet
// Similar structure to CCTV form
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
<form method="POST" id="formPengajuanAksesInternet" style="margin-bottom:0;">
    <input type="hidden" name="form_type" value="pengajuan_akses_internet">
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
                <input type="date" name="tgl_pengajuan" class="form-control form-control-sm tgl-pengajuan-inet" readonly>
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
        <label style="font-size:.875rem;font-weight:600;">Mengajukan permintaan untuk : <span class="text-danger">*</span></label>
        <div class="border rounded p-3">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="request_akses_internet" id="requestAksesInternet" value="1">
                <label class="form-check-label" for="requestAksesInternet">
                    <strong>Akses Internet</strong>
                </label>
            </div>
            <div id="aksesInternetSection" style="display:none; margin-left:18px;">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="akses_type" id="aksesTemporary" value="Temporary">
                    <label class="form-check-label" for="aksesTemporary">
                        <strong>Temporary</strong>
                    </label>
                    <div class="mt-2 ms-4" id="temporaryDuration" style="display:none;">
                        <label class="mb-1" style="font-size:.75rem;">Durasi dari / sampai</label>
                        <div class="d-flex gap-2">
                            <input type="date" name="akses_temporary_from" class="form-control form-control-sm" >
                            <input type="date" name="akses_temporary_to" class="form-control form-control-sm" >
                        </div>
                    </div>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="akses_type" id="aksesPermanent" value="Permanent">
                    <label class="form-check-label" for="aksesPermanent">
                        <strong>Permanent</strong>
                    </label>
                </div>
            </div>

            <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" name="request_tambah_bandwidth" id="requestTambahBandwidth" value="1">
                <label class="form-check-label" for="requestTambahBandwidth">
                    <strong>Penambahan Bandwith Internet</strong>
                </label>
            </div>
            <div id="bandwidthSection" style="display:none; margin-left:18px; margin-top:8px;">
                <div class="form-check mb-2" style="margin-left:0;">
                    <input class="form-check-input" type="radio" name="bandwidth_type" id="bandwidthTemporary" value="Temporary">
                    <label class="form-check-label" for="bandwidthTemporary">
                        <strong>Temporary</strong>
                    </label>
                    <div class="mt-2 ms-4" id="bandwidthTemporaryDuration" style="display:none;">
                        <label class="mb-1" style="font-size:.75rem;">Durasi dari / sampai</label>
                        <div class="d-flex gap-2">
                            <input type="date" name="bandwidth_temporary_from" class="form-control form-control-sm" >
                            <input type="date" name="bandwidth_temporary_to" class="form-control form-control-sm" >
                        </div>
                        <!-- Jumlah moved here so it appears under Temporary and above Permanent -->
                        <div class="mt-2">
                            <div id="bwJumlahWrapper" style="display:none;">
                                <label class="mb-1" style="font-size:.75rem;">Jumlah tambahan bandwidth (angka)</label>
                                <div class="d-flex gap-2" style="align-items:center;">
                                    <input type="number" min="1" name="tambah_bandwidth" class="form-control form-control-sm" placeholder="Contoh: 1" style="width:100px;">
                                    <select name="tambah_bandwidth_unit" class="form-control form-control-sm" style="width:80px;">
                                        <option value="Mbps">Mbps</option>
                                        <option value="Kbps">Kbps</option>
                                    </select>
                                </div>
                                <div class="form-text" style="font-size:.75rem;">Isi jumlah bandwidth yang diinginkan (angka saja, satuan di kanan).</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-check mb-2" style="margin-left:0;">
                    <input class="form-check-input" type="radio" name="bandwidth_type" id="bandwidthPermanent" value="Permanent">
                    <label class="form-check-label" for="bandwidthPermanent">
                        <strong>Permanent</strong>
                    </label>
                </div>

                <!-- Jumlah for Permanent (hidden, will be shown when Permanent selected) -->
                <div class="mt-2" style="margin-left:18px;">
                    <div id="bwJumlahWrapperPerm" style="display:none;">
                        <label class="mb-1" style="font-size:.75rem;">Jumlah tambahan bandwidth (angka)</label>
                        <div class="d-flex gap-2" style="align-items:center;">
                            <input type="number" min="1" name="tambah_bandwidth" class="form-control form-control-sm" placeholder="Contoh: 10" style="width:100px;">
                            <select name="tambah_bandwidth_unit" class="form-control form-control-sm" style="width:80px;">
                                <option value="Mbps">Mbps</option>
                                <option value="Kbps">Kbps</option>
                            </select>
                        </div>
                        <div class="form-text" style="font-size:.75rem;">Isi jumlah bandwidth yang diinginkan (angka saja, satuan di kanan).</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="form-group mb-0">
        <label class="mb-1" style="font-size:.875rem;font-weight:600;">Keterangan</label>
        <textarea name="keterangan" class="form-control form-control-sm" rows="3" style="resize:vertical;" placeholder="Isi keterangan tambahan jika perlu..."></textarea>
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
        var currentDate = year + '-' + month + '-' + day; // Input type date expects YYYY-MM-DD
        $('.tgl-pengajuan-inet').val(currentDate);

        // --- Form Interaction Logic ---
        
        // 1. Akses Internet Checkbox
        $('#requestAksesInternet').on('change', function(){
            if($(this).is(':checked')) { $('#aksesInternetSection').slideDown(); } 
            else { $('#aksesInternetSection').slideUp(); }
        });

        // 2. Akses Type Radio
        $('input[name="akses_type"]').on('change', function(){
            var val = $('input[name="akses_type"]:checked').val();
            if(val === 'Temporary') { $('#temporaryDuration').slideDown(); } 
            else { $('#temporaryDuration').slideUp(); }
        });

        // 3. Tambah Bandwidth Checkbox
        $('#requestTambahBandwidth').on('change', function(){
            if($(this).is(':checked')) { $('#bandwidthSection').slideDown(); } 
            else { $('#bandwidthSection').slideUp(); }
        });

        // 4. Bandwidth Type Radio
        $('input[name="bandwidth_type"]').on('change', function(){
            var val = $('input[name="bandwidth_type"]:checked').val();
            // Reset views
            $('#bandwidthTemporaryDuration').hide();
            $('#bwJumlahWrapperPerm').hide();
            
            if(val === 'Temporary') { 
                $('#bandwidthTemporaryDuration').slideDown(); 
                // Ensure the unit input inside temporary is shown? Field logic suggests "Jumlah" is in #bwJumlahWrapper
                $('#bwJumlahWrapper').show();
            } else if (val === 'Permanent') {
                $('#bwJumlahWrapperPerm').slideDown();
            }
        });

        // Defensive submit handling (Compatible with standardized TTD component)
        window._allowSubmit = false;
        $('#formPengajuanAksesInternet').off('submit.myTTD').on('submit.myTTD', function(e){
            // Basic validation: ensure at least one request is checked
            if (!$('#requestAksesInternet').is(':checked') && !$('#requestTambahBandwidth').is(':checked')) {
                e.preventDefault();
                alert('Pilih setidaknya satu permintaan (Akses Internet atau Penambahan Bandwidth).');
                return false;
            }

            if (!window._allowSubmit) {
                e.preventDefault();
                if (!window._skipTTDCheckOnce) {
                    // Call standardized validation
                    if (typeof window.validateTTDBeforeSubmit === 'function') {
                        window.validateTTDBeforeSubmit().then(function(ok){
                            if(ok) {
                                window._allowSubmit = true;
                                $('#formPengajuanAksesInternet')[0].submit();
                            }
                        });
                    } else {
                        // Fallback
                        window._allowSubmit = true;
                        $('#formPengajuanAksesInternet')[0].submit();
                    }
                } else {
                    window._allowSubmit = true;
                    $('#formPengajuanAksesInternet')[0].submit();
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

