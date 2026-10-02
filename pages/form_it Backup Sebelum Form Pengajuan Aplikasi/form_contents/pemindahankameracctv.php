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
            $jabatan = $emp['jabatan'] ?? '';
            $departemen = $emp['dept'] ?? '';
            $bagian = $emp['bagian'] ?? '';
        }
        sqlsrv_free_stmt($stmt);
    }
}

// Cek apakah user adalah Petugas CCTV
$isPetugas = false;
if (isset($_SESSION['UserId']) && isset($conn) && $conn !== false) {
    $sqlRole = "SELECT GroupRole FROM dbo.User_TTD_Template WHERE UserId = ? AND GroupRole = 'Petugas CCTV' AND IsActive = 1";
    $stmtRole = sqlsrv_query($conn, $sqlRole, [$_SESSION['UserId']]);
    if ($stmtRole && sqlsrv_fetch_array($stmtRole, SQLSRV_FETCH_ASSOC)) {
        $isPetugas = true;
    }
    if ($stmtRole) sqlsrv_free_stmt($stmtRole);
}

// Tambahan buat baca data mode edit (dari load_data.php yang disuntik ke DOM atau kalau edit mode by JS)
// Disini kita cuma set UI beda berdasar role.
?>
<form method="POST" enctype="multipart/form-data" id="formPemindahanKameraCCTV" style="margin-bottom:0;">
    <input type="hidden" name="form_type" value="pemindahan_kamera_cctv">
    <div class="row">
        <div class="col-md-6 mb-2">
            <label class="mb-1" style="font-size:.875rem;font-weight:600;">Nama Pemohon</label>
            <input type="text" name="nama_pemohon" class="form-control form-control-sm" value="<?= $username ?>" readonly required>
        </div>
        <div class="col-md-6 mb-2">
            <label class="mb-1" style="font-size:.875rem;font-weight:600;">Jabatan</label>
            <input type="text" name="jabatan" class="form-control form-control-sm" value="<?= htmlspecialchars($jabatan) ?>" readonly>
        </div>
    </div>
    <div class="row">
        <div class="col-md-4 mb-2">
            <label class="mb-1" style="font-size:.875rem;font-weight:600;">Tanggal Pengajuan</label>
            <input type="text" name="tgl_pengajuan" class="form-control form-control-sm tgl-pengajuan" readonly>
        </div>
        <div class="col-md-4 mb-2">
            <label class="mb-1" style="font-size:.875rem;font-weight:600;">Departemen</label>
            <input type="text" name="departemen" class="form-control form-control-sm" value="<?= htmlspecialchars($departemen) ?>" readonly>
        </div>
        <div class="col-md-4 mb-2">
            <label class="mb-1" style="font-size:.875rem;font-weight:600;">Area (Bagian)</label>
            <input type="text" name="area" class="form-control form-control-sm" value="<?= htmlspecialchars($bagian) ?>" readonly>
        </div>
    </div>
    <hr class="my-2">
    
    <div class="row">
        <div class="col-md-12 mb-2">
            <label class="mb-1" style="font-size:.875rem;font-weight:600;">Deskripsi (Alasan Pemindahan)</label>
            <textarea name="deskripsi_user" class="form-control form-control-sm" rows="3" required <?= $isPetugas ? 'readonly' : '' ?>></textarea>
        </div>
    </div>

    <?php if ($isPetugas): ?>
    <hr class="my-2 border-info">
    <h6 class="text-info mb-2"><i class="fas fa-tools"></i> Form Operasional (Petugas CCTV)</h6>
    <div class="row">
        <div class="col-md-6 mb-2">
            <label class="mb-1" style="font-size:.875rem;font-weight:600;">Tanggal Pengerjaan</label>
            <input type="date" name="tanggal_pengerjaan" class="form-control form-control-sm" required>
        </div>
        <div class="col-md-6 mb-2">
            <label class="mb-1" style="font-size:.875rem;font-weight:600;">Opsi Deskripsi Solusi</label>
            <textarea name="Opsi_deskripsi_solusi" class="form-control form-control-sm" rows="1" required></textarea>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-2">
            <label class="mb-1" style="font-size:.875rem;font-weight:600;">Lampiran Sebelum</label>
            <input type="file" name="lampiran_sebelum" class="form-control form-control-sm" accept="image/*,.pdf,.jpg,.jpeg,.png">
        </div>
        <div class="col-md-6 mb-2">
            <label class="mb-1" style="font-size:.875rem;font-weight:600;">Lampiran Setelah</label>
            <input type="file" name="lampiran_setelah" class="form-control form-control-sm" accept="image/*,.pdf,.jpg,.jpeg,.png">
        </div>
    </div>
    <?php endif; ?>
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
        var today = new Date();
        var dd = String(today.getDate()).padStart(2, '0');
        var mm = String(today.getMonth() + 1).padStart(2, '0');
        var yyyy = today.getFullYear();
        $('.tgl-pengajuan').val(dd + '-' + mm + '-' + yyyy);
        
        window._allowSubmit = false;
        $('#formPemindahanKameraCCTV').off('submit.myTTD').on('submit.myTTD', function(e){
            e.preventDefault();
            if (typeof window.validateTTDBeforeSubmit === 'function') {
                window.validateTTDBeforeSubmit().then(function(ok){ if (ok) { window._allowSubmit = true; $('#formPemindahanKameraCCTV')[0].submit(); } });
            } else { window._allowSubmit = true; $('#formPemindahanKameraCCTV')[0].submit(); }
            return false;
        });
    }
})();
</script>
<?php include 'components/signature_canvas.php'; ?>
