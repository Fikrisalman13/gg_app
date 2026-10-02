<?php
// Include necessary files - use absolute path using DOCUMENT_ROOT
// Note: If koneksi.php fails with die(), it will be caught by outer buffer
if (!isset($conn) || $conn === false) {
    // Use absolute path from document root (same as list_form.php)
    $koneksiPath = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
    if (file_exists($koneksiPath)) {
        require_once $koneksiPath;
    } else {
        // Fallback: try relative path from form_contents location (3 levels up)
        // From pages/form_it/form_contents to root
        $fallbackPath = dirname(dirname(dirname(__DIR__))) . '/koneksi.php';
        if (file_exists($fallbackPath)) {
            require_once $fallbackPath;
        }
    }
}

// Ambil data username dari session (session sudah dicek di load_form.php)
$username = htmlspecialchars($_SESSION['NamaLengkap'] ?? '');

// Inisialisasi variabel default jika data tidak ditemukan
$jabatan = '';
$departemen = '';
$bagian = '';

// Query untuk mengambil data jabatan, departemen, dan bagian berdasarkan nama lengkap
if (!empty($username) && isset($conn) && $conn !== false) {
    $sql = "SELECT m_jab.jabatan, m_dept.dept, m_bag.bagian
            FROM dbo.m_emp
            LEFT JOIN dbo.m_jab ON m_emp.id_jab = m_jab.id_jab
            LEFT JOIN dbo.m_subbag ON m_emp.id_subbag = m_subbag.id_subbag
            LEFT JOIN dbo.m_bag ON m_subbag.id_bag = m_bag.id_bag
            LEFT JOIN dbo.m_dept ON m_bag.id_dept = m_dept.id_dept
            WHERE m_emp.nama_lengkap = ?";

    // Menjalankan query
    $params = array($username);
    $stmt = sqlsrv_query($conn, $sql, $params);

    // Cek jika query berhasil dijalankan
    if ($stmt !== false) {
        $data_emp = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        
        // Ambil hasil data jabatan, departemen, dan bagian
        if ($data_emp) {
            $jabatan    = $data_emp['jabatan'] ?? '';
            $departemen = $data_emp['dept'] ?? '';
            $bagian     = $data_emp['bagian'] ?? '';
        }
        
        sqlsrv_free_stmt($stmt);
    }
}
?>

<form method="POST" id="formPengajuanPerangkat" style="margin-bottom: 0;">
    <input type="hidden" name="form_type" value="pengajuan_perangkat">
    
    <!-- Row 1: Informasi Pemohon -->
    <div class="row">
        <div class="col-md-6">
            <div class="form-group mb-2">
                <label class="mb-1" style="font-size: 0.875rem; font-weight: 600;">Nama Pemohon</label>
                <input type="text" name="nama_pemohon" class="form-control form-control-sm" value="<?= htmlspecialchars($username) ?>" required readonly>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group mb-2">
                <label class="mb-1" style="font-size: 0.875rem; font-weight: 600;">Jabatan</label>
                <input type="text" name="jabatan" class="form-control form-control-sm" value="<?= htmlspecialchars($jabatan) ?>" readonly>
            </div>
        </div>
    </div>
    
    <!-- Row 2: Tanggal & Departemen -->
    <div class="row">
        <div class="col-md-4">
            <div class="form-group mb-2">
                <label class="mb-1" style="font-size: 0.875rem; font-weight: 600;">Tanggal Pengajuan</label>
                <input type="text" name="tgl_pengajuan" class="form-control form-control-sm tgl-pengajuan" placeholder="DD-MM-YYYY" readonly>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group mb-2">
                <label class="mb-1" style="font-size: 0.875rem; font-weight: 600;">Departemen</label>
                <input type="text" name="departemen" class="form-control form-control-sm" value="<?= htmlspecialchars($departemen) ?>" readonly>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group mb-2">
                <label class="mb-1" style="font-size: 0.875rem; font-weight: 600;">Bagian</label>
                <input type="text" name="bagian" class="form-control form-control-sm" value="<?= htmlspecialchars($bagian) ?>" readonly>
            </div>
        </div>
    </div>

    <hr class="my-2">
    
    <!-- Pengajuan -->
    <div class="form-group mb-2">
        <label class="mb-1" style="font-size: 0.875rem; font-weight: 600;">Pengajuan</label>
        <div class="row">
            <?php
            $opsi = ['Komputer', 'Laptop', 'Tablet', 'Handphone', 'Lainnya'];
            foreach ($opsi as $o) {
                $id = strtolower(str_replace(' ', '_', $o));
                echo "
                <div class='col-md-6 col-lg-4 mb-2'>
                    <div class='form-check d-flex align-items-center gap-2 flex-wrap'>
                        <input class='form-check-input pengajuan-check' 
                               type='checkbox' 
                               data-target='input_$id'
                               value='$o'
                               name='pengajuan[]'>

                        <label class='form-check-label' style='font-size: 0.875rem; min-width: 80px;'>$o</label>

                        <input type='number' name='qty_$id' 
                               class='form-control form-control-sm d-none qty-box' 
                               placeholder='Qty' style='width:70px;'>

                        " . ($o === 'Lainnya' ? "
                        <input type='text' name='ket_$id' 
                               class='form-control form-control-sm d-none ket-box' 
                               placeholder='Isi keterangan' style='flex: 1; min-width: 150px;'>" 
                        : "") . "
                    </div>
                </div>
                ";
            }
            ?>
        </div>
    </div>

    <!-- Spesifikasi -->
    <div class="form-group mb-2">
        <label class="mb-1" style="font-size: 0.875rem; font-weight: 600;">Spesifikasi Khusus</label>
        <textarea name="spesifikasi" class="form-control form-control-sm" rows="2" style="resize: vertical;"></textarea>
    </div>

    <hr class="my-2">
    
    <!-- Peripheral -->
    <div class="form-group mb-2">
        <label class="mb-1" style="font-size: 0.875rem; font-weight: 600;">Peripheral</label>
        <div class="row">
            <?php
            $perip = ['Keyboard', 'Mouse', 'Monitor', 'Printer', 'Scanner', 'Lainnya'];
            foreach ($perip as $p) {
                $id = strtolower(str_replace(' ', '_', $p));
                echo "
                <div class='col-md-6 col-lg-4 mb-2'>
                    <div class='form-check d-flex align-items-center gap-2 flex-wrap'>
                        <input class='form-check-input peripheral-check' 
                               type='checkbox' 
                               value='$p'
                               name='peripheral[]'>

                        <label class='form-check-label' style='font-size: 0.875rem; min-width: 80px;'>$p</label>

                        <input type='number' name='qty_perip_$id' 
                               class='form-control form-control-sm d-none qty-box' 
                               placeholder='Qty' style='width:70px;'>

                        " . ($p === 'Lainnya' ? "
                        <input type='text' name='ket_perip_$id' 
                               class='form-control form-control-sm d-none ket-box' 
                               placeholder='Isi keterangan' style='flex: 1; min-width: 150px;'>" 
                        : "") . "
                    </div>
                </div>
                ";
            }
            ?>
        </div>
    </div>

    <!-- Keterangan -->
    <div class="form-group mb-0">
        <label class="mb-1" style="font-size: 0.875rem; font-weight: 600;">Keterangan</label>
        <textarea name="keterangan" class="form-control form-control-sm" rows="2" style="resize: vertical;"></textarea>
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

        // Handler untuk checkbox pengajuan
        $(".pengajuan-check").off('change').on('change', function() {
            let row = $(this).closest(".form-check");
            let qtyBox = row.find(".qty-box");
            let ketBox = row.find(".ket-box");

            if ($(this).is(":checked")) {
                qtyBox.removeClass("d-none");
                if (ketBox.length > 0) {
                    ketBox.removeClass("d-none");
                }
            } else {
                qtyBox.addClass("d-none").val('');
                ketBox.addClass("d-none").val('');
            }
        });

        // Handler untuk checkbox peripheral
        $(".peripheral-check").off('change').on('change', function() {
            let row = $(this).closest(".form-check");
            let qtyBox = row.find(".qty-box");
            let ketBox = row.find(".ket-box");

            if ($(this).is(":checked")) {
                qtyBox.removeClass("d-none");
                if (ketBox.length > 0) {
                    ketBox.removeClass("d-none");
                }
            } else {
                qtyBox.addClass("d-none").val('');
                ketBox.addClass("d-none").val('');
            }
        });

        // Glue code: Connect Save button to Standardized Component's validation
        $('#saveBtn').off('click').on('click', function(e){
            e.preventDefault();
            // validateTTDBeforeSubmit is provided by the component
            if (typeof window.validateTTDBeforeSubmit === 'function') {
                window.validateTTDBeforeSubmit().then(function(ok){
                    if (ok) {
                        window._allowSubmit = true;
                        $('#formPengajuanPerangkat')[0].submit();
                    }
                });
            } else {
                console.warn('validateTTDBeforeSubmit not found, submitting directly');
                window._allowSubmit = true;
                $('#formPengajuanPerangkat')[0].submit();
            }
        });
    }
})();
</script>

<?php include 'components/signature_canvas.php'; ?>


