<?php
// Form Pengajuan Grant/Revoke Trustee Menu ERP
if (!isset($conn) || $conn === false) {
    // Basic connection handling like in other forms
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

<form method="POST" id="formGrantRevokeTrustee">
    <input type="hidden" name="form_type" value="grant_revoke_trustee">
    
    <!-- Standard Header Fields -->
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
                <input type="date" name="tgl_pengajuan" class="form-control form-control-sm tgl-pengajuan-grt" readonly required>
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

    <hr class="my-3">

    <!-- Specific Content -->
    <div class="form-group mb-3">
        <label style="font-size:.875rem;font-weight:600;">Mengajukan permintaan untuk : <span class="text-danger">*</span></label>
        <div class="d-flex">
            <div class="custom-control custom-radio mr-4">
                <input class="custom-control-input" type="radio" name="jenis_permintaan" id="radioGrant" value="GRANT" required>
                <label class="custom-control-label font-weight-bold" for="radioGrant">GRANT</label>
            </div>
            <div class="custom-control custom-radio">
                <input class="custom-control-input" type="radio" name="jenis_permintaan" id="radioRevoke" value="REVOKE" required>
                <label class="custom-control-label font-weight-bold" for="radioRevoke">REVOKE</label>
            </div>
        </div>
    </div>

    <div class="form-group mb-3">
        <label style="font-size:.875rem;font-weight:600;">Menu : <span class="text-danger">*</span></label>
        <div id="menuListContainer">
            <div class="input-group mb-2 menu-item-row">
                <input type="text" class="form-control form-control-sm" name="menu_akses[]" placeholder="Nama Menu ERP..." required>
                <div class="input-group-append">
                    <button class="btn btn-outline-success btn-sm btn-add-menu" type="button"><i class="fas fa-plus"></i></button>
                </div>
            </div>
        </div>
        <small class="form-text text-muted">Isi nama menu yang ingin di-Grant atau di-Revoke.</small>
    </div>

    <div class="form-group mb-3">
        <label style="font-size:.875rem;font-weight:600;">Keterangan</label>
        <textarea name="keterangan" class="form-control form-control-sm" rows="3" placeholder="Isi keterangan tambahan jika perlu..."></textarea>
    </div>

 
</form>

<script>
$(document).ready(function() {
    // Set auto date
    var today = new Date();
    var day = today.getDate().toString().padStart(2, '0');
    var month = (today.getMonth() + 1).toString().padStart(2, '0');
    var year = today.getFullYear();
    var currentDate = year + '-' + month + '-' + day;
    $('.tgl-pengajuan-grt').val(currentDate);

    // Dynamic menu list
    $(document).off('click', '.btn-add-menu').on('click', '.btn-add-menu', function() {
        var newRow = `
            <div class="input-group mb-2 menu-item-row">
                <input type="text" class="form-control form-control-sm" name="menu_akses[]" placeholder="Nama Menu ERP...">
                <div class="input-group-append">
                    <button class="btn btn-outline-danger btn-sm btn-remove-menu" type="button"><i class="fas fa-minus"></i></button>
                </div>
            </div>
        `;
        $('#menuListContainer').append(newRow);
    });
    
    $(document).off('click', '.btn-remove-menu').on('click', '.btn-remove-menu', function() {
        $(this).closest('.menu-item-row').remove();
    });
});
</script>

<?php include 'components/signature_canvas.php'; ?>

<script>
    // Defensive submit handling for Signature Validation
    window._allowSubmit = false;
    $('#formGrantRevokeTrustee').off('submit.myTTD').on('submit.myTTD', function(e){
        if (!window._allowSubmit) {
            e.preventDefault();
            // Cek _skipTTDCheckOnce agar tidak looping saat script eksternal men-trigger submit ulang
            if (!window._skipTTDCheckOnce) { 
                // Panggil fungsi validasi global dari komponen
                if (typeof window.validateTTDBeforeSubmit === 'function') {
                    window.validateTTDBeforeSubmit().then(function(isSigned){
                        if (isSigned) {
                            window._allowSubmit = true;
                            $('#formGrantRevokeTrustee')[0].submit();
                        }
                    });
                } else {
                    // Fallback
                    window._allowSubmit = true;
                    $('#formGrantRevokeTrustee')[0].submit();
                }
            } else {
                 window._allowSubmit = true;
                 $('#formGrantRevokeTrustee')[0].submit();
            }
            return false;
        }
        window._allowSubmit = false;
        return true;
    });
</script>
