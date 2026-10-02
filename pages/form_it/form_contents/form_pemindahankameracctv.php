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

$isPetugas = false;
if (isset($_SESSION['UserId']) && isset($conn) && $conn !== false) {
    $sqlRole = "SELECT GroupRole FROM dbo.User_TTD_Template WHERE UserId = ? AND GroupRole = 'Petugas CCTV' AND IsActive = 1";
    $stmtRole = sqlsrv_query($conn, $sqlRole, [$_SESSION['UserId']]);
    if ($stmtRole && sqlsrv_fetch_array($stmtRole, SQLSRV_FETCH_ASSOC)) {
        $isPetugas = true;
    }
    if ($stmtRole) {
        sqlsrv_free_stmt($stmtRole);
    }
}

$isEditMode = isset($_GET['edit']) && $_GET['edit'] == 1 && !empty($_GET['ticket']);
$existing = [];
if ($isEditMode && isset($conn) && $conn !== false) {
    $stmtEdit = sqlsrv_query($conn, "SELECT TOP 1 * FROM dbo.Form_Pemindahan_CCTV WHERE ticket = ?", [$_GET['ticket']]);
    if ($stmtEdit !== false) {
        $existing = sqlsrv_fetch_array($stmtEdit, SQLSRV_FETCH_ASSOC) ?: [];
        sqlsrv_free_stmt($stmtEdit);
    }
}

function pmCctvFormatDateText($value)
{
    if ($value instanceof DateTime) {
        return $value->format('d-m-Y');
    }
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        $parts = explode('-', $value);
        return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    }
    if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $value)) {
        return $value;
    }
    return $value;
}

function pmCctvFormatDateInput($value)
{
    if ($value instanceof DateTime) {
        return $value->format('Y-m-d');
    }
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }
    if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $value)) {
        $parts = explode('-', $value);
        return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    }
    return $value;
}

function pmCctvFileName($value)
{
    $path = trim((string)$value);
    if ($path === '') {
        return '';
    }
    $path = parse_url($path, PHP_URL_PATH) ?: $path;
    return basename($path);
}

$displayNamaPemohon = $isEditMode ? ($existing['nama_pemohon'] ?? $username) : $username;
$displayJabatan = $isEditMode ? ($existing['jabatan'] ?? $jabatan) : $jabatan;
$displayDepartemen = $isEditMode ? ($existing['departemen'] ?? $departemen) : $departemen;
$displayTanggalPengajuan = $isEditMode ? pmCctvFormatDateText($existing['tgl_pengajuan'] ?? '') : '';
$displayArea = $isEditMode ? ($existing['area'] ?? '') : '';
$displayDeskripsiUser = $isEditMode ? ($existing['deskripsi_user'] ?? '') : '';
$displayTanggalPengerjaan = $isEditMode ? pmCctvFormatDateInput($existing['tanggal_pengerjaan'] ?? '') : '';
$displayManDays = $isEditMode ? ($existing['man_days'] ?? '') : '';
$displayShortestDeliveryDate = $isEditMode ? pmCctvFormatDateInput($existing['shortest_delivery_date'] ?? '') : '';
$displayDeskripsiArea = $isEditMode ? ($existing['deskripsi_area'] ?? '') : '';
$displayAssignedTo = $isEditMode ? ($existing['assigned_to'] ?? 'Tim IT') : 'Tim IT';
$displayDeskripsiSolusi = $isEditMode ? ($existing['Opsi_deskripsi_solusi'] ?? '') : '';
$displayLampiranSebelum = $isEditMode ? ($existing['lampiran_sebelum'] ?? '') : '';
$displayLampiranSetelah = $isEditMode ? ($existing['lampiran_setelah'] ?? '') : '';
?>
<form method="POST" enctype="multipart/form-data" id="formPemindahanKameraCCTV" style="margin-bottom:0;">
    <input type="hidden" name="form_type" value="pemindahan_kamera_cctv">
    <?php if ($isEditMode): ?>
    <input type="hidden" name="ticket" value="<?= htmlspecialchars($_GET['ticket']) ?>">
    <input type="hidden" name="action" value="update">
    <?php endif; ?>
    <div class="row">
        <div class="col-md-6 mb-2">
            <label class="mb-1" style="font-size:.875rem;font-weight:600;">Nama Pemohon</label>
            <input type="text" name="nama_pemohon" class="form-control form-control-sm" value="<?= htmlspecialchars($displayNamaPemohon) ?>" readonly required>
        </div>
        <div class="col-md-6 mb-2">
            <label class="mb-1" style="font-size:.875rem;font-weight:600;">Jabatan</label>
            <input type="text" name="jabatan" class="form-control form-control-sm" value="<?= htmlspecialchars($displayJabatan) ?>" readonly>
        </div>
    </div>
    <div class="row">
        <div class="col-md-4 mb-2">
            <label class="mb-1" style="font-size:.875rem;font-weight:600;">Tanggal Pengajuan</label>
            <input type="text" name="tgl_pengajuan" class="form-control form-control-sm tgl-pengajuan" value="<?= htmlspecialchars($displayTanggalPengajuan) ?>" readonly>
        </div>
        <div class="col-md-4 mb-2">
            <label class="mb-1" style="font-size:.875rem;font-weight:600;">Departemen</label>
            <input type="text" name="departemen" class="form-control form-control-sm" value="<?= htmlspecialchars($displayDepartemen) ?>" readonly>
        </div>
        <div class="col-md-4 mb-2">
            <label class="mb-1" style="font-size:.875rem;font-weight:600;">Area CCTV</label>
            <input type="text" name="area" class="form-control form-control-sm" value="<?= htmlspecialchars($displayArea) ?>" placeholder="Isi area CCTV" required>
        </div>
    </div>
    <hr class="my-2">
    <div class="form-group mb-2">
        <label class="mb-1" style="font-size:.875rem;font-weight:600;">Deskripsi (Alasan Pemindahan)</label>
        <textarea name="deskripsi_user" class="form-control form-control-sm" rows="3" required><?= htmlspecialchars($displayDeskripsiUser) ?></textarea>
    </div>

    <?php if ($isPetugas || $isEditMode): ?>
    <hr class="my-2 border-info">
    <h6 class="text-info mb-2"><i class="fas fa-tools"></i> Form Operasional (Petugas CCTV)</h6>
    <div class="row">
        <div class="col-md-4 mb-2">
            <label class="mb-1" style="font-size:.875rem;font-weight:600;">Tanggal Pengerjaan</label>
            <input type="date" id="tanggalPengerjaan" name="tanggal_pengerjaan" class="form-control form-control-sm" value="<?= htmlspecialchars($displayTanggalPengerjaan) ?>" required>
        </div>
        <div class="col-md-4 mb-2">
            <label class="mb-1" style="font-size:.875rem;font-weight:600;">Man-days</label>
            <input type="text" id="manDays" name="man_days" class="form-control form-control-sm" value="<?= htmlspecialchars($displayManDays) ?>" placeholder="0">
        </div>
        <div class="col-md-4 mb-2">
            <label class="mb-1" style="font-size:.875rem;font-weight:600;">Shortest Delivery Date</label>
            <input type="date" id="shortestDeliveryDate" name="shortest_delivery_date" class="form-control form-control-sm" value="<?= htmlspecialchars($displayShortestDeliveryDate) ?>" readonly>
        </div>
    </div>
    <div class="row">
        <div class="col-md-4 mb-2">
            <label class="mb-1" style="font-size:.875rem;font-weight:600;">Deskripsi Area</label>
            <textarea name="deskripsi_area" class="form-control form-control-sm" rows="2"><?= htmlspecialchars($displayDeskripsiArea) ?></textarea>
        </div>
        <div class="col-md-4 mb-2">
            <label class="mb-1" style="font-size:.875rem;font-weight:600;">Assigned to</label>
            <input type="text" id="assignedTo" name="assigned_to" class="form-control form-control-sm" value="<?= htmlspecialchars($displayAssignedTo) ?>" readonly required>
        </div>
        <div class="col-md-4 mb-2">
            <label class="mb-1" style="font-size:.875rem;font-weight:600;">Tanggal Diterima</label>
            <input type="text" class="form-control form-control-sm" value="<?= htmlspecialchars($displayTanggalPengajuan) ?>" readonly>
        </div>
    </div>
    <div class="form-group mb-2">
        <label class="mb-1" style="font-size:.875rem;font-weight:600;">Opsi Deskripsi Solusi</label>
        <textarea name="Opsi_deskripsi_solusi" class="form-control form-control-sm" rows="2" required><?= htmlspecialchars($displayDeskripsiSolusi) ?></textarea>
    </div>
    <div class="row">
        <div class="col-md-6 mb-2">
            <label class="mb-1" style="font-size:.875rem;font-weight:600;">Lampiran Sebelum</label>
            <input type="file" name="lampiran_sebelum" class="form-control form-control-sm" accept="image/*,.pdf,.jpg,.jpeg,.png">
            <div id="lampiranSebelumLama" class="mt-1 small"><?php if (!empty($displayLampiranSebelum)): ?>Lama: <a href="<?= htmlspecialchars($displayLampiranSebelum) ?>" target="_blank"><?= htmlspecialchars(pmCctvFileName($displayLampiranSebelum)) ?></a><?php endif; ?></div>
        </div>
        <div class="col-md-6 mb-2">
            <label class="mb-1" style="font-size:.875rem;font-weight:600;">Lampiran Setelah</label>
            <input type="file" name="lampiran_setelah" class="form-control form-control-sm" accept="image/*,.pdf,.jpg,.jpeg,.png">
            <div id="lampiranSetelahLama" class="mt-1 small"><?php if (!empty($displayLampiranSetelah)): ?>Lama: <a href="<?= htmlspecialchars($displayLampiranSetelah) ?>" target="_blank"><?= htmlspecialchars(pmCctvFileName($displayLampiranSetelah)) ?></a><?php endif; ?></div>
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
        var dateStr = dd + '-' + mm + '-' + yyyy;

        $('.tgl-pengajuan').each(function(){
            if (!$(this).val()) {
                $(this).val(dateStr);
            }
        });

        function syncShortestDeliveryDate(forceClear){
            var dateValue = $('#tanggalPengerjaan').val();
            var manDaysValue = parseInt($('#manDays').val(), 10);
            if (!dateValue || isNaN(manDaysValue) || manDaysValue <= 0) {
                if (forceClear) {
                    $('#shortestDeliveryDate').val('');
                }
                return;
            }

            var parts = dateValue.split('-');
            if (parts.length !== 3) {
                if (forceClear) {
                    $('#shortestDeliveryDate').val('');
                }
                return;
            }

            var dateObj = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
            if (isNaN(dateObj.getTime())) {
                if (forceClear) {
                    $('#shortestDeliveryDate').val('');
                }
                return;
            }

            dateObj.setDate(dateObj.getDate() + manDaysValue - 1);
            var year = dateObj.getFullYear();
            var month = String(dateObj.getMonth() + 1).padStart(2, '0');
            var day = String(dateObj.getDate()).padStart(2, '0');
            $('#shortestDeliveryDate').val(year + '-' + month + '-' + day);
        }

        $('#assignedTo').val('Tim IT');
        $('#tanggalPengerjaan, #manDays').on('input change', function(){
            syncShortestDeliveryDate(true);
        });
        syncShortestDeliveryDate(false);
    }
})();
</script>
<?php include 'components/signature_canvas.php'; ?>

