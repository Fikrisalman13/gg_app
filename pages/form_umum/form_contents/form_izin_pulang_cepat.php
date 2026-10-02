<?php
/**
 * Form Izin Pulang Cepat (IPC)
 * Dimuat via AJAX ke dalam #modalFormIsian di list_form.php
 */

if (!isset($conn) || $conn === false) {
    $koneksiPath = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
    if (file_exists($koneksiPath)) {
        require_once $koneksiPath;
    }
}

$username = htmlspecialchars($_SESSION['NamaLengkap'] ?? '');
$nik = '';
$departemen = '';
$bagian = '';
$jabatan = '';
$no_hp = '';

if (!empty($username) && isset($conn) && $conn !== false) {
    $sql = "SELECT m_emp.nik, m_dept.dept, m_bag.bagian, m_jab.jabatan, m_emp.telp
            FROM dbo.m_emp
            LEFT JOIN dbo.m_subbag ON m_emp.id_subbag = m_subbag.id_subbag
            LEFT JOIN dbo.m_bag    ON m_subbag.id_bag  = m_bag.id_bag
            LEFT JOIN dbo.m_dept   ON m_bag.id_dept    = m_dept.id_dept
            LEFT JOIN dbo.m_jab    ON m_emp.id_jab     = m_jab.id_jab
            WHERE m_emp.nama_lengkap = ?";
    $stmt = sqlsrv_query($conn, $sql, [$username]);
    if ($stmt !== false) {
        $emp = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($emp) {
            $nik = $emp['nik'] ?? '';
            $departemen = $emp['dept'] ?? '';
            $bagian = $emp['bagian'] ?? '';
            $jabatan = $emp['jabatan'] ?? '';
            $no_hp = $emp['telp'] ?? '';
        }
        sqlsrv_free_stmt($stmt);
    }
}

$isEdit = isset($_GET['edit']) && $_GET['edit'] == 1 && isset($_GET['ticket']);
$editTicket = $isEdit ? htmlspecialchars($_GET['ticket']) : '';
?>
<form method="POST" id="formIzinPulangCepat" enctype="multipart/form-data" style="margin-bottom:0;">
    <input type="hidden" name="form_type" value="izin_pulang_cepat">
    <?php if ($isEdit): ?>
        <input type="hidden" name="ticket" value="<?= $editTicket ?>">
        <input type="hidden" name="action" value="update">
    <?php endif; ?>


    <div class="row">
        <div class="col-md-6">
            <div class="form-group mb-2">
                <label class="mb-1 font-weight-bold text-secondary" style="font-size:.85rem;">Nomor Pengajuan</label>
                <input type="text" class="form-control form-control-sm bg-light" name="ticket_display"
                    id="ipcTicketDisplay" value="<?= $isEdit ? $editTicket : '[Otomatis]' ?>" readonly>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group mb-2">
                <label class="mb-1 font-weight-bold text-secondary" style="font-size:.85rem;">Tanggal</label>
                <input type="date" name="tanggal" id="ipcTanggal" class="form-control form-control-sm bg-light" readonly
                    required>
                <input type="hidden" name="tgl_pengajuan" id="ipcTglPengajuan" value="">
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="form-group mb-2">
                <label class="mb-1 font-weight-bold text-secondary" style="font-size:.85rem;">NIK</label>
                <input type="text" name="nik" id="ipcNik" class="form-control form-control-sm bg-light"
                    value="<?= htmlspecialchars($nik) ?>" readonly required>
            </div>
        </div>
        <div class="col-md-8">
            <div class="form-group mb-2">
                <label class="mb-1 font-weight-bold text-secondary" style="font-size:.85rem;">Nama Karyawan</label>
                <select name="nama_pemohon" id="ipcNama" class="form-control form-control-sm" required>
                    <option value="">-- Memuat data karyawan --</option>
                </select>
                <input type="hidden" id="ipcNamaOriginal" value="<?= htmlspecialchars($username) ?>">
                <input type="hidden" id="ipcBagianOriginal" value="<?= htmlspecialchars($bagian) ?>">
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="form-group mb-2">
                <label class="mb-1 font-weight-bold text-secondary" style="font-size:.85rem;">Departemen</label>
                <input type="text" name="departemen" id="ipcDept" class="form-control form-control-sm bg-light"
                    value="<?= htmlspecialchars($departemen) ?>" readonly required>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group mb-2">
                <label class="mb-1 font-weight-bold text-secondary" style="font-size:.85rem;">Bagian</label>
                <input type="text" name="bagian" id="ipcBagian" class="form-control form-control-sm bg-light"
                    value="<?= htmlspecialchars($bagian) ?>" readonly required>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group mb-2">
                <label class="mb-1 font-weight-bold text-secondary" style="font-size:.85rem;">Jabatan</label>
                <input type="text" name="jabatan" id="ipcJabatan" class="form-control form-control-sm bg-light"
                    value="<?= htmlspecialchars($jabatan) ?>" readonly required>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="form-group mb-2">
                <label class="mb-1 font-weight-bold text-secondary" style="font-size:.85rem;">No HP</label>
                <input type="text" name="no_hp" id="ipcNoHp" class="form-control form-control-sm bg-light"
                    value="<?= htmlspecialchars($no_hp) ?>" readonly>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group mb-2">
                <label class="mb-1 font-weight-bold" style="font-size:.85rem;">Jam Pulang Normal <span
                        class="text-danger">*</span></label>
                <input type="time" name="jam_pulang_normal" id="ipcJamPulangNormal" class="form-control form-control-sm"
                    value="16:15" required>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group mb-2">
                <label class="mb-1 font-weight-bold" style="font-size:.85rem;">Jam Pulang Yang Diminta <span
                        class="text-danger">*</span></label>
                <input type="time" name="jam_pulang_diminta" id="ipcJamPulangDiminta"
                    class="form-control form-control-sm" required>
            </div>
        </div>
    </div>

    <div class="form-group mb-2">
        <label class="mb-1 font-weight-bold" style="font-size:.85rem;">Alasan <span class="text-danger">*</span></label>
        <select name="alasan" id="ipcAlasan" class="form-control form-control-sm" required>
            <option value="">-- Pilih Alasan --</option>
            <option value="Sakit">Sakit</option>
            <option value="Urusan Keluarga">Urusan Keluarga</option>
            <option value="Keperluan Mendesak">Keperluan Mendesak</option>
            <option value="Dinas Perusahaan">Dinas Perusahaan</option>
            <option value="Lainnya">Lainnya</option>
        </select>
        <div id="ipcAlasanLainWrapper" class="mt-2" style="display:none;">
            <input type="text" name="alasan_lain" id="ipcAlasanLain" class="form-control form-control-sm"
                placeholder="Sebutkan alasan lain...">
        </div>
    </div>

    <div class="form-group mb-3" style="padding:15px;background:#f8f9fa;border-radius:5px;border:1px solid #dee2e6;">
        <label for="ipcLampiran" style="font-weight:600;color:#333;margin-bottom:8px;display:block;">
            <i class="fas fa-paperclip"></i> Lampiran (Opsional)
        </label>
        <p style="font-size:12px;color:#666;margin-bottom:10px;">
            Maksimal 50MB. Format yang diizinkan: PDF, JPG, PNG, DOCX, XLSX
        </p>
        <input type="file" id="ipcLampiran" name="lampiran" accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx"
            style="display:block;width:100%;padding:8px;border:1px solid #ced4da;border-radius:4px;background:white;cursor:pointer;font-size:14px;">
        <small id="ipcFileInfo" style="display:none;margin-top:5px;color:#28a745;">
            <i class="fas fa-check-circle"></i> <span id="ipcFileName"></span> (<span id="ipcFileSize"></span>)
        </small>
        <small id="ipcFileError" style="display:none;margin-top:5px;color:#dc3545;">
            <i class="fas fa-exclamation-circle"></i> <span id="ipcErrorMessage"></span>
        </small>
    </div>

    <?php include __DIR__ . '/components/signature_canvas.php'; ?>
</form>

<script>
    (function initFormIPC() {
        function tick() {
            if (window.jQuery) return start(window.jQuery);
            setTimeout(tick, 100);
        }
        tick();

        function start($) {
            var $form = $('#formIzinPulangCepat');
            var $tanggal = $('#ipcTanggal');
            var $tglPengajuan = $('#ipcTglPengajuan');
            var editTicket = $form.find('input[name="ticket"]').val();
            var isEditMode = !!editTicket;

            function todayString() {
                var now = new Date();
                var y = now.getFullYear();
                var m = String(now.getMonth() + 1).padStart(2, '0');
                var d = String(now.getDate()).padStart(2, '0');
                return y + '-' + m + '-' + d;
            }

            function setTodayDefault() {
                var today = todayString();
                if (!$tanggal.val()) $tanggal.val(today);
                if (!$tglPengajuan.val()) $tglPengajuan.val(today);
                if (!$('#ipcJamPulangNormal').val()) $('#ipcJamPulangNormal').val('16:15');
            }
            setTodayDefault();

            function updateEmployeeFields(emp) {
                $('#ipcNik').val(emp.nik || '');
                $('#ipcDept').val(emp.dept || '');
                $('#ipcBagian').val(emp.bagian || '');
                $('#ipcJabatan').val(emp.jabatan || '');
                $('#ipcNoHp').val(emp.telp || '');
            }

            function loadEmployeesByBagian(callback) {
                var bagian = $('#ipcBagianOriginal').val();
                var currentUserName = $('#ipcNamaOriginal').val();

                if (!bagian) {
                    $('#ipcNama').html('<option value="">Error: Bagian tidak ditemukan</option>');
                    return;
                }

                $.ajax({
                    url: 'get_employees_by_bagian.php',
                    method: 'GET',
                    data: { bagian: bagian },
                    dataType: 'json'
                }).done(function (resp) {
                    if (!resp || !resp.success || !resp.data || resp.data.length === 0) {
                        $('#ipcNama').html('<option value="">Tidak ada karyawan di bagian ini</option>');
                        return;
                    }

                    var $namaSelect = $('#ipcNama');
                    var employeeMap = {};
                    $namaSelect.empty().append('<option value="">-- Pilih Karyawan --</option>');

                    $.each(resp.data, function (i, emp) {
                        var $option = $('<option></option>')
                            .val(emp.nama_lengkap)
                            .text(emp.nama_lengkap)
                            .data('employee', emp);
                        employeeMap[emp.nama_lengkap] = emp;
                        $namaSelect.append($option);

                        if (!isEditMode && emp.nama_lengkap === currentUserName) {
                            $option.prop('selected', true);
                            updateEmployeeFields(emp);
                        }
                    });

                    $namaSelect.data('employeeMap', employeeMap);
                    if (callback) callback(employeeMap);
                }).fail(function () {
                    $('#ipcNama').html('<option value="">Error memuat data karyawan</option>');
                });
            }

            $('#ipcNama').on('change', function () {
                var selectedName = $(this).val();
                var employeeMap = $(this).data('employeeMap');
                if (selectedName && employeeMap && employeeMap[selectedName]) {
                    updateEmployeeFields(employeeMap[selectedName]);
                }
            });

            if (isEditMode) {
                loadEmployeesByBagian(function (employeeMap) {
                    $.ajax({
                        url: '/gg_app/pages/form_umum/load_data.php',
                        method: 'GET',
                        data: { ticket: editTicket },
                        dataType: 'json'
                    }).done(function (resp) {
                        if (resp && resp.success && resp.data) {
                            var d = resp.data;
                            $tanggal.val(d.tanggal || d.tgl_pengajuan || todayString());
                            $tglPengajuan.val(d.tgl_pengajuan || d.tanggal || todayString());
                            if (d.nama_pemohon && employeeMap[d.nama_pemohon]) {
                                $('#ipcNama').val(d.nama_pemohon);
                                updateEmployeeFields(employeeMap[d.nama_pemohon]);
                            }
                            $('#ipcJamPulangNormal').val(d.jam_pulang_normal || '16:15');
                            $('#ipcJamPulangDiminta').val(d.jam_pulang_diminta || '');
                            if (d.alasan) {
                                $('#ipcAlasan').val(d.alasan).trigger('change');
                                if (d.alasan === 'Lainnya') $('#ipcAlasanLain').val(d.alasan_lain || '');
                            }
                        }
                    });
                });
            } else {
                loadEmployeesByBagian();
            }

            $('#ipcAlasan').on('change', function () {
                if ($(this).val() === 'Lainnya') {
                    $('#ipcAlasanLainWrapper').show();
                    $('#ipcAlasanLain').prop('required', true);
                } else {
                    $('#ipcAlasanLainWrapper').hide();
                    $('#ipcAlasanLain').prop('required', false).val('');
                }
            });

            // Validasi jam pulang diminta tidak boleh kurang dari jam sekarang
            $('#ipcJamPulangDiminta').on('change blur', function() {
                var diminta = $(this).val();
                if (!diminta) return;
                
                var now = new Date();
                var currentHour = now.getHours();
                var currentMinute = now.getMinutes();
                var currentTime = currentHour * 60 + currentMinute;
                
                var [dimintaHour, dimintaMinute] = diminta.split(':').map(Number);
                var dimintaTime = dimintaHour * 60 + dimintaMinute;
                
                if (dimintaTime < currentTime) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Validasi Gagal',
                            text: 'Jam Pulang Yang Diminta tidak boleh kurang dari jam sekarang (' + 
                                  String(currentHour).padStart(2, '0') + ':' + String(currentMinute).padStart(2, '0') + ').'
                        });
                    } else {
                        alert('Jam Pulang Yang Diminta tidak boleh kurang dari jam sekarang.');
                    }
                    $(this).val('');
                }
            });

            $form.off('submit.ipcValidate').on('submit.ipcValidate', function (e) {
                var diminta = $('#ipcJamPulangDiminta').val();
                
                // Validasi jam tidak boleh kurang dari sekarang
                if (diminta) {
                    var now = new Date();
                    var currentHour = now.getHours();
                    var currentMinute = now.getMinutes();
                    var currentTime = currentHour * 60 + currentMinute;
                    
                    var [dimintaHour, dimintaMinute] = diminta.split(':').map(Number);
                    var dimintaTime = dimintaHour * 60 + dimintaMinute;
                    
                    if (dimintaTime < currentTime) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Validasi Gagal',
                                text: 'Jam Pulang Yang Diminta tidak boleh kurang dari jam sekarang.'
                            });
                        } else {
                            alert('Jam Pulang Yang Diminta tidak boleh kurang dari jam sekarang.');
                        }
                        return false;
                    }
                }
                
                // Validasi jam diminta harus lebih awal dari jam normal
                var normal = $('#ipcJamPulangNormal').val();
                if (normal && diminta && diminta >= normal) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Validasi Gagal',
                            text: 'Jam Pulang Yang Diminta harus lebih awal dari Jam Pulang Normal.'
                        });
                    } else {
                        alert('Jam Pulang Yang Diminta harus lebih awal dari Jam Pulang Normal.');
                    }
                    return false;
                }
            });

            $('#ipcLampiran').on('change', function () {
                var file = this.files[0];
                $('#ipcFileInfo').hide();
                $('#ipcFileError').hide();
                if (!file) return;

                var maxSize = 50 * 1024 * 1024;
                if (file.size > maxSize) {
                    $('#ipcErrorMessage').text('File terlalu besar. Maksimal 50MB.');
                    $('#ipcFileError').show();
                    this.value = '';
                    return;
                }

                var allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'xlsx'];
                var fileName = file.name.toLowerCase();
                var ext = fileName.substring(fileName.lastIndexOf('.') + 1);
                if (!allowedExtensions.includes(ext)) {
                    $('#ipcErrorMessage').text('Format file tidak diizinkan. Gunakan PDF, JPG, PNG, DOCX, atau XLSX.');
                    $('#ipcFileError').show();
                    this.value = '';
                    return;
                }

                $('#ipcFileName').text(file.name);
                $('#ipcFileSize').text((file.size / (1024 * 1024)).toFixed(2) + ' MB');
                $('#ipcFileInfo').show();
            });

            window._activeFormId = '#formIzinPulangCepat';
        }
    })();
</script>