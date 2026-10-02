<?php
// Form Penambahan Gudang Baru Di System ERP
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
<form method="POST" id="formPenambahanGudangBaruErp" style="margin-bottom:0;">
    <input type="hidden" name="form_type" value="penambahan_gudang_baru_erp">
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
                <input type="text" name="tgl_pengajuan" class="form-control form-control-sm tgl-pengajuan-gudang-erp" readonly placeholder="DD-MM-YYYY">
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

    <div class="form-group mb-2">
        <label class="mb-1" style="font-size:.875rem;font-weight:600;">Nama gudang baru <span class="text-danger">*</span></label>
        <input type="text" name="nama_gudang_baru" class="form-control form-control-sm" placeholder="Masukkan nama gudang baru" required>
    </div>

    <div class="form-group mb-2">
        <label class="mb-1" style="font-size:.875rem;font-weight:600;">Nama user yang diberikan akses untuk gudang baru <span class="text-danger">*</span></label>
        <textarea name="user_akses_gudang" class="form-control form-control-sm" rows="3" style="resize:vertical;" placeholder="Masukkan nama user yang diberikan akses" required></textarea>
    </div>

    <div class="form-group mb-2">
        <label class="mb-1" style="font-size:.875rem;font-weight:600;">Keterangan/Alasan <span class="text-danger">*</span></label>
        <textarea name="keterangan" class="form-control form-control-sm" rows="3" style="resize:vertical;" placeholder="Isi keterangan atau alasan penambahan gudang..." required></textarea>
    </div>

    <script>
    (function initFormGudangBaruErp(){
        function tick(){
            if (window.jQuery) return start(window.jQuery);
            setTimeout(tick, 100);
        }
        tick();
        function start($){
            var today = new Date();
            var day = String(today.getDate()).padStart(2, '0');
            var month = String(today.getMonth() + 1).padStart(2, '0');
            var year = today.getFullYear();
            var dateStr = day + '-' + month + '-' + year;

            $('.tgl-pengajuan-gudang-erp').each(function(){
                if(!$(this).val()) $(this).val(dateStr);
            });
        }
    })();
    </script>
    <?php include 'components/signature_canvas.php'; ?>
</form>
