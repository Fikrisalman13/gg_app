<?php
// Form Buka Tanggal Closingan (Umum)
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
<form method="POST" id="formBukaTanggalClosingan" enctype="multipart/form-data" style="margin-bottom:0;">
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

    <?php
    $gudangList = [];
    $koneksi3Path = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi3.php';
    if (file_exists($koneksi3Path)) {
        try {
            require_once $koneksi3Path;
            if (isset($conn3)) {
                $sqlGudang = "SELECT DISTINCT wrhscode, wrhsname FROM whwrhs WHERE fgstatus = 'A' ORDER BY wrhsname";
                $stmtGudang = $conn3->query($sqlGudang);
                if ($stmtGudang) {
                    $gudangList = $stmtGudang->fetchAll(PDO::FETCH_ASSOC);
                }
            }
        } catch (Exception $e) {
            // silent fail
        }
    }
    $transaksiOptions = [];
    $helperPath = dirname(__DIR__) . '/transaksi_master_helper.php';
    if (file_exists($helperPath)) {
        require_once $helperPath;
        $transaksiOptions = getTransaksiClosinganOptions($conn, true);
    }
    if (empty($transaksiOptions)) {
        $transaksiOptions = [
            'Procurement' => [],
            'Sales' => []
        ];
    }

    // Build gudang options HTML for template
    $gudangOptionsHtml = '<option value=""></option>';
    foreach ($gudangList as $g) {
        $gudangCode = trim((string)($g['wrhscode'] ?? ''));
        $gudangName = trim((string)($g['wrhsname'] ?? ''));
        $gudangDisplay = trim($gudangCode . ' - ' . $gudangName, ' -');
        $gudangOptionsHtml .= '<option value="' . htmlspecialchars($gudangDisplay) . '">' . htmlspecialchars($gudangDisplay) . '</option>';
    }
    ?>

    <!-- Items Container (Repeater) -->
    <div id="itemsContainer"></div>

    <!-- Tombol Tambah Item -->
    <div class="mb-3">
        <button type="button" id="btnTambahItem" class="btn btn-sm btn-success">
            <i class="fas fa-plus"></i> Tambah Item
        </button>
    </div>

    <!-- Upload Lampiran (Opsional) -->
    <div class="form-group mb-3" style="padding: 15px; background: #f8f9fa; border-radius: 5px; border: 1px solid #dee2e6;">
        <label for="lampiran" style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">
            <i class="fas fa-paperclip"></i> Upload Lampiran (Opsional)
        </label>
        <p style="font-size: 12px; color: #666; margin-bottom: 10px;">
            Maksimal 50MB. Format yang diizinkan: PDF, JPG, PNG, DOCX, XLSX
        </p>
        <div style="position: relative; overflow: hidden;">
            <input type="file" 
                   id="lampiran" 
                   name="lampiran" 
                   accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx"
                   style="display: block; width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px; background: white; cursor: pointer; font-size: 14px;">
        </div>
        <small id="fileInfo" style="display: none; margin-top: 5px; color: #28a745;">
            <i class="fas fa-check-circle"></i> <span id="fileName"></span> (<span id="fileSize"></span>)
        </small>
        <small id="fileError" style="display: none; margin-top: 5px; color: #dc3545;">
            <i class="fas fa-exclamation-circle"></i> <span id="errorMessage"></span>
        </small>
    </div>

    <!-- Hidden field gudang_transaksi di level form (di luar container item) -->
    <input type="hidden" name="gudang_transaksi" id="gudangTransaksiJson">
    <!-- Hidden field buka_tgl level tiket untuk backward compatibility -->
    <input type="hidden" name="buka_tgl" id="bukaTglTicket">

    <!-- Template Item Block -->
    <script type="text/template" id="itemBlockTemplate">
        <div class="item-block card mb-3" data-index="__INDEX__">
            <div class="card-header d-flex align-items-center py-2 bg-light">
                <span class="item-number font-weight-bold" style="font-size:.9rem; color:#495057;">
                    #__NUM__
                </span>
                <button type="button" class="btn btn-sm btn-danger btn-hapus-item ml-auto" disabled>
                    <i class="fas fa-trash"></i> Hapus
                </button>
            </div>
            <div class="card-body">
                <!-- Checkbox permintaan -->
                <div class="form-group mb-3">
                    <label style="font-size:.875rem;font-weight:600;">
                        Mengajukan permintaan untuk <span class="text-danger">*</span>
                    </label>
                    <div class="d-flex align-items-center border rounded p-2" style="gap:20px;">
                        <div class="form-check">
                            <label class="form-check-label" style="font-weight:600; cursor:pointer;">
                                <input class="form-check-input chk-gudang" type="checkbox" name="items[__INDEX__][request_gudang]" value="1">
                                Closingan Gudang
                            </label>
                        </div>
                        <div class="form-check">
                            <label class="form-check-label" style="font-weight:600; cursor:pointer;">
                                <input class="form-check-input chk-transaksi" type="checkbox" name="items[__INDEX__][request_transaksi]" value="1">
                                Closingan Transaksi
                            </label>
                        </div>
                    </div>
                    <div class="item-error-msg text-danger mt-1" style="display:none;font-size:.8rem;">
                        Pilih setidaknya satu permintaan (Closingan Gudang atau Closingan Transaksi)!
                    </div>
                </div>

                <!-- Row 1: Buka Tgl, Gudang, Jenis Transaksi -->
                <div class="row mb-2">
                    <div class="col-md-4">
                        <div class="form-group mb-0">
                            <label class="mb-1" style="font-size:.875rem;font-weight:600;">
                                Buka Tgl <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="items[__INDEX__][buka_tgl]" class="form-control form-control-sm field-buka-tgl" required>
                        </div>
                    </div>

                    <!-- Field Gudang (kondisional) -->
                    <div class="col-md-4 container-gudang-item" style="display:none;">
                        <div class="form-group mb-0">
                            <label class="mb-1" style="font-size:.875rem;font-weight:600;">
                                Gudang <span class="text-danger">*</span>
                            </label>
                            <select name="items[__INDEX__][input_gudang]" class="form-control form-control-sm select2-gudang" data-placeholder="-- Pilih Gudang --" style="width:100%;">
                                __GUDANG_OPTIONS__
                            </select>
                        </div>
                    </div>

                    <!-- Field Jenis Transaksi (kondisional) -->
                    <div class="col-md-4 container-transaksi-item" style="display:none;">
                        <div class="form-group mb-0">
                            <label class="mb-1" style="font-size:.875rem;font-weight:600;">
                                Jenis Transaksi <span class="text-danger">*</span>
                            </label>
                            <select name="items[__INDEX__][jenis_transaksi]" class="form-control form-control-sm select-jenis-transaksi" style="width:100%;">
                                <option value="">-- Pilih Jenis --</option>
                                <option value="Procurement">Procurement</option>
                                <option value="Sales">Sales</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Row 2: Detail Transaksi (kondisional) -->
                <div class="row mb-2 container-detail-transaksi" style="display:none;">
                    <div class="col-md-4">
                        <div class="form-group mb-0">
                            <label class="mb-1" style="font-size:.875rem;font-weight:600;">
                                Transaksi <span class="text-danger">*</span>
                            </label>
                            <select name="items[__INDEX__][input_transaksi]" class="form-control form-control-sm select-input-transaksi" style="width:100%;">
                                <option value="">-- Pilih Transaksi --</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-0">
                            <label class="mb-1" style="font-size:.875rem;font-weight:600;">
                                Nomor Transaksi <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="items[__INDEX__][nomor_transaksi]" class="form-control form-control-sm input-nomor-transaksi" placeholder="Masukkan nomor transaksi">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-0">
                            <label class="mb-1" style="font-size:.875rem;font-weight:600;">
                                Vendor/Cust <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="items[__INDEX__][vendor_cust]" class="form-control form-control-sm input-vendor-cust" placeholder="Masukkan vendor atau customer">
                        </div>
                    </div>
                </div>

                <!-- Row 3: Keterangan -->
                <div class="row">
                    <div class="col-12">
                        <div class="form-group mb-0">
                            <label class="mb-1" style="font-size:.875rem;font-weight:600;">
                                Keterangan/Alasan
                            </label>
                            <textarea name="items[__INDEX__][keterangan]" class="form-control form-control-sm" rows="2" style="resize:vertical;" placeholder="Isi alasan pembukaan closingan..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </script>

    <script>
    (function initFormBukaClosing(){
        function tick(){
            if (window.jQuery) return start(window.jQuery);
            setTimeout(tick, 100);
        }
        tick();
        function start($){
            var transaksiOptions = <?= json_encode($transaksiOptions, JSON_UNESCAPED_UNICODE); ?>;
            var gudangOptionsHtml = <?= json_encode($gudangOptionsHtml, JSON_UNESCAPED_UNICODE); ?>;

            // ── Tanggal pengajuan ──────────────────────────────────────────────
            var today = new Date();
            var day   = String(today.getDate()).padStart(2, '0');
            var month = String(today.getMonth() + 1).padStart(2, '0');
            var year  = today.getFullYear();
            var dateStr = day + '-' + month + '-' + year;

            $('.tgl-pengajuan-buka-closing').each(function(){
                if (!$(this).val()) $(this).val(dateStr);
            });

            // ── File Upload Validation ────────────────────────────────────────
            $('#lampiran').on('change', function(e) {
                var file = e.target.files[0];
                var $fileInfo = $('#fileInfo');
                var $fileError = $('#fileError');
                var $fileName = $('#fileName');
                var $fileSize = $('#fileSize');
                var $errorMessage = $('#errorMessage');
                
                // Reset display
                $fileInfo.hide();
                $fileError.hide();
                
                if (!file) return;
                
                // Validasi ukuran file (50MB = 50 * 1024 * 1024 bytes)
                var maxSize = 50 * 1024 * 1024;
                if (file.size > maxSize) {
                    $errorMessage.text('Ukuran file terlalu besar. Maksimal 50MB.');
                    $fileError.show();
                    $(this).val(''); // Reset input
                    return;
                }
                
                // Validasi tipe file
                var allowedTypes = [
                    'application/pdf', 
                    'image/jpeg', 
                    'image/png', 
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                ];
                
                // Fallback: check extension if MIME type is not reliable
                var fileName = file.name.toLowerCase();
                var allowedExtensions = ['.pdf', '.jpg', '.jpeg', '.png', '.docx', '.xlsx'];
                var hasValidExtension = allowedExtensions.some(function(ext) {
                    return fileName.endsWith(ext);
                });
                
                if (!allowedTypes.includes(file.type) && !hasValidExtension) {
                    $errorMessage.text('Tipe file tidak diizinkan. Gunakan PDF, JPG, PNG, DOCX, atau XLSX.');
                    $fileError.show();
                    $(this).val(''); // Reset input
                    return;
                }
                
                // Tampilkan info file
                $fileName.text(file.name);
                $fileSize.text(formatFileSize(file.size));
                $fileInfo.show();
            });

            // Helper function untuk format ukuran file
            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                var k = 1024;
                var sizes = ['Bytes', 'KB', 'MB'];
                var i = Math.floor(Math.log(bytes) / Math.log(k));
                return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
            }

            // ── Repeater counter ───────────────────────────────────────────────
            var _itemCounter = 0;

            // ── addItemBlock ───────────────────────────────────────────────────
            function addItemBlock() {
                var idx = _itemCounter++;
                var num = $('#itemsContainer .item-block').length + 1;

                var tpl = $('#itemBlockTemplate').html();
                tpl = tpl.replace(/__INDEX__/g, idx)
                         .replace(/__NUM__/g, num)
                         .replace(/__GUDANG_OPTIONS__/g, gudangOptionsHtml);

                var $block = $(tpl);
                $('#itemsContainer').append($block);

                initItemBlockEvents($block);
                initItemBlockSelect2($block);
                renumberItems();
                updateDeleteButtons();
            }

            // ── removeItemBlock ────────────────────────────────────────────────
            function removeItemBlock(btn) {
                var $block = $(btn).closest('.item-block');

                // Destroy Select2 sebelum hapus elemen
                var $sel2 = $block.find('.select2-gudang');
                if ($sel2.length && $sel2.data('select2')) {
                    try { $sel2.select2('destroy'); } catch(e) {}
                }

                $block.remove();
                renumberItems();
                updateDeleteButtons();
            }

            // ── renumberItems ──────────────────────────────────────────────────
            function renumberItems() {
                $('#itemsContainer .item-block').each(function(i){
                    $(this).find('.item-number').text('#' + (i + 1));
                });
            }

            // ── updateDeleteButtons ────────────────────────────────────────────
            function updateDeleteButtons() {
                var $blocks = $('#itemsContainer .item-block');
                var count   = $blocks.length;
                $blocks.find('.btn-hapus-item').prop('disabled', count <= 1);
            }

            // ── initItemBlockEvents ────────────────────────────────────────────
            function initItemBlockEvents($block) {
                // Checkbox Gudang
                $block.find('.chk-gudang').on('change', function(){
                    var checked = $(this).is(':checked');
                    var $cg = $block.find('.container-gudang-item');
                    var $sel = $block.find('.select2-gudang');
                    if (checked) {
                        $cg.show();
                        $sel.prop('required', true);
                        // Re-init Select2 jika belum
                        initItemBlockSelect2($block);
                    } else {
                        $cg.hide();
                        $sel.prop('required', false);
                        if ($sel.data('select2')) {
                            try { $sel.val('').trigger('change.select2'); } catch(e) {}
                        } else {
                            $sel.val('');
                        }
                    }
                    clearItemError($block);
                });

                // Checkbox Transaksi
                $block.find('.chk-transaksi').on('change', function(){
                    var checked = $(this).is(':checked');
                    var $ct = $block.find('.container-transaksi-item');
                    var $detailRow = $block.find('.container-detail-transaksi');
                    var $jenisSelect = $block.find('.select-jenis-transaksi');
                    var $inputTrans  = $block.find('.select-input-transaksi');
                    var $nomorTrans  = $block.find('.input-nomor-transaksi');
                    var $vendorCust  = $block.find('.input-vendor-cust');
                    if (checked) {
                        $ct.show();
                        $jenisSelect.prop('required', true);
                    } else {
                        $ct.hide();
                        $jenisSelect.prop('required', false).val('');
                        $detailRow.hide();
                        $inputTrans.prop('required', false)
                                   .html('<option value="">-- Pilih Transaksi --</option>').val('');
                        $nomorTrans.prop('required', false).val('');
                        $vendorCust.prop('required', false).val('');
                    }
                    clearItemError($block);
                });

                // Select Jenis Transaksi
                $block.find('.select-jenis-transaksi').on('change', function(){
                    var jenis = $(this).val() || '';
                    var $detailRow  = $block.find('.container-detail-transaksi');
                    var $inputTrans = $block.find('.select-input-transaksi');
                    var $nomorTrans = $block.find('.input-nomor-transaksi');
                    var $vendorCust = $block.find('.input-vendor-cust');

                    if (jenis) {
                        $detailRow.show();
                        $inputTrans.prop('required', true);
                        $nomorTrans.prop('required', true);
                        $vendorCust.prop('required', true);
                        // Populate options
                        var opts = transaksiOptions[jenis] || [];
                        var html = '<option value="">-- Pilih Transaksi --</option>';
                        for (var i = 0; i < opts.length; i++) {
                            var v = $('<div>').text(opts[i]).html();
                            html += '<option value="' + v + '">' + v + '</option>';
                        }
                        $inputTrans.html(html).val('');
                    } else {
                        $detailRow.hide();
                        $inputTrans.prop('required', false)
                                   .html('<option value="">-- Pilih Transaksi --</option>').val('');
                        $nomorTrans.prop('required', false).val('');
                        $vendorCust.prop('required', false).val('');
                    }
                });

                // Tombol Hapus Item
                $block.find('.btn-hapus-item').on('click', function(){
                    removeItemBlock(this);
                });
            }

            // ── initItemBlockSelect2 ───────────────────────────────────────────
            function initItemBlockSelect2($block) {
                if (!$.fn.select2) return;
                var $sel = $block.find('.select2-gudang');
                if (!$sel.length) return;
                if ($sel.data('select2')) return; // sudah diinisialisasi

                $sel.select2({
                    placeholder: $sel.data('placeholder') || '-- Pilih Gudang --',
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#modalFormIsian')
                });
            }

            // ── clearItemError ─────────────────────────────────────────────────
            function clearItemError($block) {
                $block.find('.item-error-msg').hide();
                $block.find('.border').removeClass('border-danger');
            }

            // ── serializeItems ─────────────────────────────────────────────────
            function serializeItems() {
                var items = [];
                $('#itemsContainer .item-block').each(function(){
                    var $b = $(this);
                    items.push({
                        buka_tgl:          $b.find('.field-buka-tgl').val() || '',
                        request_gudang:    $b.find('.chk-gudang').is(':checked') ? 1 : 0,
                        request_transaksi: $b.find('.chk-transaksi').is(':checked') ? 1 : 0,
                        gudang:            $b.find('.select2-gudang').val() || '',
                        jenis_transaksi:   $b.find('.select-jenis-transaksi').val() || '',
                        transaksi:         $b.find('.select-input-transaksi').val() || '',
                        nomor_transaksi:   $b.find('.input-nomor-transaksi').val() || '',
                        vendor_cust:       $b.find('.input-vendor-cust').val() || '',
                        keterangan:        $b.find('textarea').val() || ''
                    });
                });
                return items;
            }

            // ── validateItems ──────────────────────────────────────────────────
            function validateItems() {
                var valid = true;
                var $firstError = null;

                $('#itemsContainer .item-block').each(function(){
                    var $b = $(this);
                    var reqGudang    = $b.find('.chk-gudang').is(':checked');
                    var reqTransaksi = $b.find('.chk-transaksi').is(':checked');
                    var bukaTgl      = $b.find('.field-buka-tgl').val() || '';

                    // Validasi buka_tgl tidak kosong
                    if (!bukaTgl) {
                        $b.find('.field-buka-tgl').addClass('is-invalid');
                        valid = false;
                        if (!$firstError) $firstError = $b;
                    } else {
                        $b.find('.field-buka-tgl').removeClass('is-invalid');
                    }

                    // Validasi minimal satu checkbox dicentang
                    if (!reqGudang && !reqTransaksi) {
                        $b.find('.item-error-msg').show();
                        $b.find('.d-flex.border').addClass('border-danger');
                        valid = false;
                        if (!$firstError) $firstError = $b;
                    } else {
                        $b.find('.item-error-msg').hide();
                        $b.find('.d-flex.border').removeClass('border-danger');
                    }
                });

                if (!valid && $firstError) {
                    // Scroll ke item pertama yang error
                    var offset = $firstError.offset();
                    if (offset) {
                        $('html, body').animate({ scrollTop: offset.top - 80 }, 400);
                    }
                    // Juga scroll dalam modal jika ada
                    var $modal = $('#modalFormIsian');
                    if ($modal.length && $modal.hasClass('show')) {
                        var $modalBody = $modal.find('.modal-body');
                        if ($modalBody.length) {
                            var itemTop = $firstError.position() ? $firstError.position().top : 0;
                            $modalBody.animate({ scrollTop: $modalBody.scrollTop() + itemTop - 80 }, 400);
                        }
                    }
                }

                return valid;
            }

            // ── Tombol Tambah Item ─────────────────────────────────────────────
            $('#btnTambahItem').off('click.additem').on('click.additem', function(){
                addItemBlock();
            });

            // ── Render satu Item Block default ─────────────────────────────────
            if ($('#itemsContainer .item-block').length === 0) {
                addItemBlock();
            }

            // ── Submit handler ─────────────────────────────────────────────────
            window._allowSubmit = false;
            $('#formBukaTanggalClosingan').off('submit.closing').on('submit.closing', function(e){
                if (!window._allowSubmit) {
                    e.preventDefault();

                    // Validasi semua item blocks
                    if (!validateItems()) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Validasi Gagal',
                                text: 'Periksa kembali isian pada setiap item. Pastikan Buka Tgl diisi dan minimal satu permintaan dipilih!'
                            });
                        } else {
                            alert('Periksa kembali isian pada setiap item. Pastikan Buka Tgl diisi dan minimal satu permintaan dipilih!');
                        }
                        return false;
                    }

                    // Serialisasi items ke JSON
                    var items = serializeItems();

                    if (!items || items.length === 0) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Validasi Gagal',
                                text: 'Minimal satu item harus diisi!'
                            });
                        } else {
                            alert('Minimal satu item harus diisi!');
                        }
                        return false;
                    }

                    // Masukkan JSON ke hidden field
                    $('#gudangTransaksiJson').val(JSON.stringify(items));

                    // Isi buka_tgl level tiket dari item pertama (backward compatibility)
                    $('#bukaTglTicket').val(items[0].buka_tgl || '');

                    if (!window._skipTTDCheckOnce) {
                        if (typeof window.validateTTDBeforeSubmit === 'function') {
                            window.validateTTDBeforeSubmit().then(function(ok){
                                if (ok) {
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

            // ── Expose fungsi untuk populateFormWithData (edit mode) ───────────
            window.closingRepeater = {
                addItemBlock: addItemBlock,
                removeItemBlock: removeItemBlock,
                renumberItems: renumberItems,
                updateDeleteButtons: updateDeleteButtons,
                initItemBlockEvents: initItemBlockEvents,
                initItemBlockSelect2: initItemBlockSelect2,
                serializeItems: serializeItems,
                clearAll: function() {
                    // Destroy semua Select2 sebelum kosongkan container
                    $('#itemsContainer .item-block').each(function(){
                        var $sel = $(this).find('.select2-gudang');
                        if ($sel.length && $sel.data('select2')) {
                            try { $sel.select2('destroy'); } catch(e) {}
                        }
                    });
                    $('#itemsContainer').empty();
                    _itemCounter = 0;
                },
                populateItemBlocks: function(items) {
                    // Kosongkan container
                    window.closingRepeater.clearAll();

                    if (!items || items.length === 0) {
                        addItemBlock();
                        return;
                    }

                    for (var i = 0; i < items.length; i++) {
                        addItemBlock();
                        var $block = $('#itemsContainer .item-block').last();
                        var item   = items[i];

                        // Isi buka_tgl
                        $block.find('.field-buka-tgl').val(item.buka_tgl || '');

                        // Isi keterangan
                        $block.find('textarea').val(item.keterangan || '');

                        // Checkbox Gudang
                        if (item.request_gudang == 1 || item.request_gudang === true) {
                            $block.find('.chk-gudang').prop('checked', true).trigger('change');
                            // Set nilai gudang setelah Select2 siap
                            (function($b, gudangVal){
                                setTimeout(function(){
                                    var $sel = $b.find('.select2-gudang');
                                    if ($sel.data('select2')) {
                                        $sel.val(gudangVal).trigger('change');
                                    } else {
                                        $sel.val(gudangVal);
                                    }
                                }, 100);
                            })($block, item.gudang || '');
                        }

                        // Checkbox Transaksi
                        if (item.request_transaksi == 1 || item.request_transaksi === true) {
                            $block.find('.chk-transaksi').prop('checked', true).trigger('change');
                            // Set jenis_transaksi
                            if (item.jenis_transaksi) {
                                $block.find('.select-jenis-transaksi').val(item.jenis_transaksi).trigger('change');
                                // Set transaksi & nomor setelah options di-populate
                                (function($b, transVal, nomorVal, vendorCustVal){
                                    setTimeout(function(){
                                        $b.find('.select-input-transaksi').val(transVal);
                                        $b.find('.input-nomor-transaksi').val(nomorVal);
                                        $b.find('.input-vendor-cust').val(vendorCustVal);
                                    }, 50);
                                })($block, item.transaksi || '', item.nomor_transaksi || '', item.vendor_cust || '');
                            }
                        }
                    }
                }
            };
        }
    })();
    </script>
    <?php include 'components/signature_canvas.php'; ?>
</form>
