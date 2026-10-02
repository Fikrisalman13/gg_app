<?php
// Form Pengajuan Pembuatan Aplikasi
if (!isset($conn) || $conn === false) {
    $primaryConnectionPath = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
    $fallbackConnectionPath = dirname(dirname(dirname(__DIR__))) . '/koneksi.php';

    if (file_exists($primaryConnectionPath)) {
        require_once $primaryConnectionPath;
    } elseif (file_exists($fallbackConnectionPath)) {
        require_once $fallbackConnectionPath;
    }
}

$applicantName = htmlspecialchars($_SESSION['NamaLengkap'] ?? '', ENT_QUOTES, 'UTF-8');
$applicantPosition = '';
$applicantDepartment = '';
$applicantSection = '';

if ($applicantName !== '' && isset($conn) && $conn !== false) {
    $employeeSql = "SELECT m_jab.jabatan,
                           m_dept.dept,
                           m_bag.bagian
                    FROM dbo.m_emp
                    LEFT JOIN dbo.m_jab ON m_emp.id_jab = m_jab.id_jab
                    LEFT JOIN dbo.m_subbag ON m_emp.id_subbag = m_subbag.id_subbag
                    LEFT JOIN dbo.m_bag ON m_subbag.id_bag = m_bag.id_bag
                    LEFT JOIN dbo.m_dept ON m_bag.id_dept = m_dept.id_dept
                    WHERE m_emp.nama_lengkap = ?";
    $employeeStatement = sqlsrv_query($conn, $employeeSql, [$applicantName]);

    if ($employeeStatement !== false) {
        $employee = sqlsrv_fetch_array($employeeStatement, SQLSRV_FETCH_ASSOC);
        if ($employee) {
            $applicantPosition = $employee['jabatan'] ?? '';
            $applicantDepartment = $employee['dept'] ?? '';
            $applicantSection = $employee['bagian'] ?? '';
        }
        sqlsrv_free_stmt($employeeStatement);
    }
}
?>
<form
    method="POST"
    id="formPengajuanAplikasi"
    enctype="multipart/form-data"
    style="margin-bottom: 0;"
>
    <input type="hidden" name="form_type" value="pengajuan_aplikasi">
    <?php if (isset($_GET['edit'], $_GET['ticket']) && $_GET['edit'] == 1): ?>
        <input
            type="hidden"
            name="ticket"
            value="<?= htmlspecialchars($_GET['ticket'], ENT_QUOTES, 'UTF-8') ?>"
        >
        <input type="hidden" name="action" value="update">
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group mb-2">
                <label class="mb-1 font-weight-bold">Nama Pemohon</label>
                <input
                    type="text"
                    name="nama_pemohon"
                    class="form-control form-control-sm"
                    value="<?= $applicantName ?>"
                    readonly
                    required
                >
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group mb-2">
                <label class="mb-1 font-weight-bold">Jabatan</label>
                <input
                    type="text"
                    name="jabatan"
                    class="form-control form-control-sm"
                    value="<?= htmlspecialchars($applicantPosition, ENT_QUOTES, 'UTF-8') ?>"
                    readonly
                >
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="form-group mb-2">
                <label class="mb-1 font-weight-bold">Tanggal Pengajuan</label>
                <input
                    type="date"
                    name="tgl_pengajuan"
                    class="form-control form-control-sm tgl-today"
                    readonly
                    required
                >
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group mb-2">
                <label class="mb-1 font-weight-bold">Departemen</label>
                <input
                    type="text"
                    name="departemen"
                    class="form-control form-control-sm"
                    value="<?= htmlspecialchars($applicantDepartment, ENT_QUOTES, 'UTF-8') ?>"
                    readonly
                >
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group mb-2">
                <label class="mb-1 font-weight-bold">Bagian</label>
                <input
                    type="text"
                    name="bagian"
                    class="form-control form-control-sm"
                    value="<?= htmlspecialchars($applicantSection, ENT_QUOTES, 'UTF-8') ?>"
                    readonly
                >
            </div>
        </div>
    </div>

    <hr class="my-2">
    <div class="alert alert-light border py-2 mb-2">
        <strong>Petunjuk:</strong> Jelaskan kebutuhan dari sisi pekerjaan. Istilah teknis tidak wajib.
    </div>

    <div class="form-group mb-2">
        <label class="font-weight-bold">
            Nama Aplikasi yang Diajukan <span class="text-danger">*</span>
        </label>
        <small class="form-text text-muted mb-1">
            Jika belum ada nama resmi, gunakan nama berdasarkan fungsi aplikasi.
        </small>
        <input
            type="text"
            name="nama_aplikasi"
            maxlength="150"
            class="form-control form-control-sm"
            required
            placeholder="Contoh: Aplikasi Monitoring Permintaan Barang"
        >
    </div>

    <div class="form-group mb-2">
        <label class="font-weight-bold">
            Latar Belakang dan Kendala Saat Ini <span class="text-danger">*</span>
        </label>
        <small class="form-text text-muted mb-1">
            Jelaskan pekerjaan saat ini, cara pengerjaannya, dan masalah yang sering terjadi.
        </small>
        <textarea
            name="latar_belakang_kendala"
            rows="4"
            class="form-control form-control-sm"
            required
            placeholder="Contoh: Saat ini proses dilakukan melalui Excel dan WhatsApp. Kendalanya adalah..."
        ></textarea>
    </div>

    <div class="form-group mb-2">
        <label class="font-weight-bold">
            Tujuan Pembuatan Aplikasi <span class="text-danger">*</span>
        </label>
        <small class="form-text text-muted mb-1">
            Jelaskan hasil utama yang ingin dicapai, bukan teknologi yang akan digunakan.
        </small>
        <textarea
            name="tujuan_pembuatan"
            rows="3"
            class="form-control form-control-sm"
            required
            placeholder="Contoh: Mempermudah..., mempercepat..., dan mengurangi..."
        ></textarea>
    </div>

    <div class="form-group mb-2">
        <label class="font-weight-bold">
            Gambaran Proses yang Diharapkan <span class="text-danger">*</span>
        </label>
        <small class="form-text text-muted mb-1">
            Ceritakan urutan penggunaan: siapa mengisi, siapa memeriksa, dan hasil akhirnya.
        </small>
        <textarea
            name="gambaran_proses"
            rows="4"
            class="form-control form-control-sm"
            required
            placeholder="Contoh: Pemohon mengisi..., lalu diperiksa oleh..., setelah disetujui..."
        ></textarea>
    </div>

    <div class="form-group mb-2">
        <label class="font-weight-bold">
            Kebutuhan atau Fitur Utama <span class="text-danger">*</span>
        </label>
        <small class="form-text text-muted mb-1">Tuliskan fungsi utama dalam daftar singkat.</small>
        <textarea
            name="fitur_utama"
            rows="4"
            class="form-control form-control-sm"
            required
            placeholder="1. Input data permintaan&#10;2. Persetujuan atasan&#10;3. Informasi status&#10;4. Laporan"
        ></textarea>
    </div>

    <div class="form-group mb-2">
        <label class="font-weight-bold">
            Manfaat yang Diharapkan <span class="text-danger">*</span>
        </label>
        <small class="form-text text-muted mb-1">
            Jelaskan pekerjaan yang lebih mudah serta risiko atau kesalahan yang berkurang.
        </small>
        <textarea
            name="manfaat_diharapkan"
            rows="3"
            class="form-control form-control-sm"
            required
            placeholder="Contoh: Mengurangi kesalahan, mempercepat proses, dan mempermudah pemantauan..."
        ></textarea>
    </div>

    <div class="form-group mb-2">
        <label class="font-weight-bold">
            Lampiran Pendukung <span class="text-muted">(Opsional)</span>
        </label>
        <small class="form-text text-muted mb-1">
            Satu file, maksimum 5 MB. PDF, Excel, Word, JPG, atau PNG.
        </small>
        <input
            type="file"
            name="lampiran"
            class="form-control-file"
            accept=".pdf,.xls,.xlsx,.doc,.docx,.jpg,.jpeg,.png"
        >
        <div id="lampiranAplikasiSaatIni" class="small mt-1"></div>
        <div class="form-check mt-1 d-none" id="hapusLampiranAplikasiWrap">
            <input
                class="form-check-input"
                type="checkbox"
                name="hapus_lampiran"
                id="hapusLampiranAplikasi"
                value="1"
            >
            <label class="form-check-label" for="hapusLampiranAplikasi">
                Hapus lampiran saat ini
            </label>
        </div>
    </div>

    <script>
    (function waitForJQuery() {
        const waitStartedAt = Date.now();

        function initializeForm($) {
            $('.tgl-today').val(new Date().toISOString().slice(0, 10));
            window._allowSubmit = false;

            $('#formPengajuanAplikasi')
                .off('submit.myTTD')
                .on('submit.myTTD', function handleSubmit(event) {
                    if (window._allowSubmit) {
                        window._allowSubmit = false;
                        return true;
                    }

                    event.preventDefault();
                    const canValidateSignature = !window._skipTTDCheckOnce
                        && typeof window.validateTTDBeforeSubmit === 'function';

                    if (!canValidateSignature) {
                        window._allowSubmit = true;
                        this.submit();
                        return false;
                    }

                    window.validateTTDBeforeSubmit().then(function submitWhenSigned(isSigned) {
                        if (isSigned) {
                            window._allowSubmit = true;
                            $('#formPengajuanAplikasi')[0].submit();
                        }
                    });
                    return false;
                });
        }

        function waitUntilAvailable() {
            if (window.jQuery) {
                initializeForm(window.jQuery);
                return;
            }
            if (Date.now() - waitStartedAt > 10000) {
                console.error('Form Pengajuan Aplikasi: jQuery not found');
                return;
            }
            setTimeout(waitUntilAvailable, 100);
        }

        waitUntilAvailable();
    })();
    </script>
    <?php include 'components/signature_canvas.php'; ?>
</form>
