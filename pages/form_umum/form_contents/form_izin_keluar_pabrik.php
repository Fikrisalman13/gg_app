<?php
/**
 * Form Izin Keluar Sementara (IKS)
 * Dimuat via AJAX ke dalam #modalFormIsian di list_form.php
 */

// Pastikan koneksi tersedia (auto-fill data pegawai)
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

// Auto-fill data karyawan dari m_emp
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

// Mode edit — cek parameter
$isEdit = isset($_GET['edit']) && $_GET['edit'] == 1 && isset($_GET['ticket']);
$editTicket = $isEdit ? htmlspecialchars($_GET['ticket']) : '';
?>
<form method="POST" id="formIzinKeluarPabrik" enctype="multipart/form-data" style="margin-bottom:0;">
    <input type="hidden" name="form_type" value="izin_keluar_pabrik">
    <?php if ($isEdit): ?>
        <input type="hidden" name="ticket" value="<?= $editTicket ?>">
        <input type="hidden" name="action" value="update">
    <?php endif; ?>

    <!-- Nomor Pengajuan & Tanggal Pengajuan -->
    <div class="row">
        <div class="col-md-6">
            <div class="form-group mb-2">
                <label class="mb-1 font-weight-bold text-secondary" style="font-size:.85rem;">Nomor Pengajuan</label>
                <input type="text" class="form-control form-control-sm bg-light" name="ticket_display"
                    id="ikpTicketDisplay" value="<?= $isEdit ? $editTicket : '[Otomatis]' ?>" readonly>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group mb-2">
                <label class="mb-1 font-weight-bold text-secondary" style="font-size:.85rem;">Tanggal Pengajuan</label>
                <input type="date" name="tgl_pengajuan" id="ikpTglPengajuan"
                    class="form-control form-control-sm bg-light" readonly required>
            </div>
        </div>
    </div>    <!-- Daftar Karyawan -->
    <div class="form-group mb-2">
        <label class="mb-1 font-weight-bold text-secondary">Karyawan</label>
        <div id="ikpEmployees" class="border rounded p-2"></div>
        <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="ikpAddEmployee">
            <i class="fas fa-user-plus"></i> Tambah Karyawan
        </button>
        <input type="hidden" name="employees_json" id="ikpEmployeesJson" value="[]">
    </div>

    <!-- Legacy first-employee fields retained for backward compatibility -->
    <input type="hidden" name="nik" id="ikpNik" value="<?= htmlspecialchars($nik) ?>">
    <input type="hidden" name="nama_pemohon" id="ikpNama" value="<?= htmlspecialchars($username) ?>">
    <input type="hidden" name="departemen" id="ikpDept" value="<?= htmlspecialchars($departemen) ?>">
    <input type="hidden" name="bagian" id="ikpBagian" value="<?= htmlspecialchars($bagian) ?>">
    <input type="hidden" name="jabatan" id="ikpJabatan" value="<?= htmlspecialchars($jabatan) ?>">
    <input type="hidden" name="no_hp" id="ikpNoHp" value="<?= htmlspecialchars($no_hp) ?>">
    <input type="hidden" id="ikpNamaOriginal" value="<?= htmlspecialchars($username) ?>">
    <input type="hidden" id="ikpBagianOriginal" value="<?= htmlspecialchars($bagian) ?>">

    <!-- Tanggal Keluar, Jam Keluar, Estimasi Kembali -->
    <div class="row">
        <div class="col-md-4">
            <div class="form-group mb-2">
                <label class="mb-1 font-weight-bold text-secondary" style="font-size:.85rem;">Tanggal Keluar</label>
                <input type="date" name="tgl_keluar" id="ikpTglKeluar" class="form-control form-control-sm bg-light"
                    readonly required>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group mb-2">
                <label class="mb-1 font-weight-bold" style="font-size:.85rem;">Jam Keluar <span
                        class="text-danger">*</span></label>
                <div class="input-group input-group-sm">
                    <input type="time" name="jam_keluar" id="ikpJamKeluar" class="form-control form-control-sm"
                        required>
                    <div class="input-group-append">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnAmbilJamSekarang"
                            title="Ambil jam sekarang">
                            <i class="fas fa-clock"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group mb-2">
                <label class="mb-1 font-weight-bold" style="font-size:.85rem;">Estimasi Kembali <span
                        class="text-danger">*</span></label>
                <input type="time" name="estimasi_kembali" id="ikpEstimasiKembali" class="form-control form-control-sm"
                    required>
            </div>
        </div>
    </div>

    <!-- Kendaraan -->
    <div class="form-group mb-2">
        <label class="mb-1 font-weight-bold" style="font-size:.85rem;">Kendaraan <span
                class="text-danger">*</span></label>
        <select name="kendaraan" id="ikpKendaraan" class="form-control form-control-sm" required>
            <option value="">-- Pilih Kendaraan --</option>
            <option value="Jalan Kaki">Jalan Kaki</option>
            <option value="Motor Pribadi">Motor Pribadi</option>
            <option value="Mobil Pribadi">Mobil Pribadi</option>
            <option value="Kendaraan Perusahaan">Kendaraan Perusahaan</option>
            <option value="Lainnya">Lainnya</option>
        </select>
        <div id="kendaraanLainWrapper" class="mt-2" style="display:none;">
            <input type="text" name="kendaraan_lain" id="ikpKendaraanLain" class="form-control form-control-sm"
                placeholder="Sebutkan kendaraan lain...">
        </div>
    </div>

    <!-- Keperluan -->
    <div class="form-group mb-2">
        <label class="mb-1 font-weight-bold" style="font-size:.85rem;">Keperluan <span
                class="text-danger">*</span></label>
        <select name="keperluan" id="ikpKeperluan" class="form-control form-control-sm" required>
            <option value="">-- Pilih Keperluan --</option>
            <option value="Berobat">Berobat</option>
            <option value="Ke Bank">Ke Bank</option>
            <option value="Keperluan Keluarga">Keperluan Keluarga</option>
            <option value="Urusan Pribadi">Urusan Pribadi</option>
            <option value="Dinas Perusahaan">Dinas Perusahaan</option>
            <option value="Lainnya">Lainnya</option>
        </select>
        <div id="keperluanLainWrapper" class="mt-2" style="display:none;">
            <input type="text" name="keperluan_lain" id="ikpKeperluanLain" class="form-control form-control-sm"
                placeholder="Sebutkan keperluan lain...">
        </div>
    </div>

    <!-- Tujuan -->
    <div class="form-group mb-3">
        <label class="mb-1 font-weight-bold" style="font-size:.85rem;">Tujuan</label>
        <input type="text" name="tujuan" id="ikpTujuan" class="form-control form-control-sm"
            placeholder="Sebutkan tujuan / destinasi keluar ...">
    </div>

    <!-- Upload Lampiran (Opsional) -->
    <div class="form-group mb-3"
        style="padding: 15px; background: #f8f9fa; border-radius: 5px; border: 1px solid #dee2e6;">
        <label for="ikpLampiran" style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">
            <i class="fas fa-paperclip"></i> Upload Lampiran (Opsional)
        </label>
        <p style="font-size: 12px; color: #666; margin-bottom: 10px;">
            Maksimal 50MB. Format yang diizinkan: PDF, JPG, PNG, DOCX, XLSX
        </p>
        <div style="position: relative; overflow: hidden;">
            <input type="file" id="ikpLampiran" name="lampiran" accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx"
                style="display: block; width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px; background: white; cursor: pointer; font-size: 14px;">
        </div>
        <small id="ikpFileInfo" style="display: none; margin-top: 5px; color: #28a745;">
            <i class="fas fa-check-circle"></i> <span id="ikpFileName"></span> (<span id="ikpFileSize"></span>)
        </small>
        <small id="ikpFileError" style="display: none; margin-top: 5px; color: #dc3545;">
            <i class="fas fa-exclamation-circle"></i> <span id="ikpErrorMessage"></span>
        </small>
    </div>

    <input type="hidden" name="hari" id="ikpHari" value="">

    <!-- Tanda Tangan Pemohon -->
    <?php include __DIR__ . '/components/signature_canvas.php'; ?>
</form>

<script>
    (function initFormIKP() {
        function tick() {
            if (window.jQuery) return start(window.jQuery);
            setTimeout(tick, 100);
        }
        tick();

        function start($) {
            var DAYS = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

            var $tgl = $('#ikpTglPengajuan');
            var $tglKeluar = $('#ikpTglKeluar');
            var $hari = $('#ikpHari');
            var $form = $('#formIzinKeluarPabrik');

            function setTodayDefault() {
                if (!$tgl.val()) {
                    var now = new Date();
                    var y = now.getFullYear();
                    var m = String(now.getMonth() + 1).padStart(2, '0');
                    var d = String(now.getDate()).padStart(2, '0');
                    $tgl.val(y + '-' + m + '-' + d);
                    $tglKeluar.val(y + '-' + m + '-' + d);
                    $hari.val(DAYS[now.getDay()]);
                }
            }
            setTodayDefault();            var employeeMap = {};
            var selectedEmployees = [];

            function syncEmployees() {
                $('#ikpEmployeesJson').val(JSON.stringify(selectedEmployees));
                var first = selectedEmployees[0] || {};
                $('#ikpNik').val(first.nik || '');
                $('#ikpNama').val(first.nama_lengkap || '');
                $('#ikpDept').val(first.dept || '');
                $('#ikpBagian').val(first.bagian || '');
                $('#ikpJabatan').val(first.jabatan || '');
                $('#ikpNoHp').val(first.telp || '');
            }

            function renderEmployees() {
                var $box = $('#ikpEmployees').empty();
                if (!selectedEmployees.length) {
                    $box.html('<div class="text-muted small p-2"><i class="fas fa-info-circle mr-1"></i> Belum ada karyawan dipilih.</div>');
                    return;
                }
                $.each(selectedEmployees, function (i, emp) {
                    var nama = emp.nama_lengkap || '-';
                    var nik = emp.nik || '-';
                    var jabatan = emp.jabatan || '-';
                    var telp = emp.telp || '-';

                    var $card = $(
                        '<div class="card card-outline card-primary mb-2 shadow-none" style="border: 1px solid #dee2e6; border-left: 4px solid #007bff; background: #fff; margin-bottom: 8px;">' +
                            '<div class="card-body p-2 d-flex justify-content-between align-items-center flex-wrap" style="gap: 8px;">' +
                                '<div style="flex: 1; min-width: 220px;">' +
                                    '<div class="font-weight-bold text-dark mb-1" style="font-size: 0.95rem;">' +
                                        '<i class="fas fa-user-check text-primary mr-1"></i> ' +
                                        (i + 1) + '. ' + $('<div>').text(nama).html() +
                                    '</div>' +
                                    '<div class="d-flex flex-wrap text-muted" style="gap: 14px; font-size: 0.82rem;">' +
                                        '<span><i class="fas fa-id-card text-secondary mr-1"></i><b>NIK:</b> ' + $('<div>').text(nik).html() + '</span>' +
                                        '<span><i class="fas fa-briefcase text-secondary mr-1"></i><b>Jabatan:</b> ' + $('<div>').text(jabatan).html() + '</span>' +
                                        '<span><i class="fas fa-phone text-secondary mr-1"></i><b>No. HP:</b> ' + $('<div>').text(telp).html() + '</span>' +
                                    '</div>' +
                                '</div>' +
                                '<div>' +
                                    '<button type="button" class="btn btn-outline-danger btn-sm px-2 py-1" data-index="' + i + '" title="Hapus Karyawan">' +
                                        '<i class="fas fa-trash-alt mr-1"></i> Hapus' +
                                    '</button>' +
                                '</div>' +
                            '</div>' +
                        '</div>'
                    );
                    $box.append($card);
                });
            }

            function addEmployee(emp) {
                if (!emp || !emp.nik) return;
                if (selectedEmployees.some(function (item) { return String(item.nik) === String(emp.nik); })) return;
                selectedEmployees.push(emp);
                renderEmployees();
                syncEmployees();
            }

            $('#ikpEmployees').on('click', 'button[data-index]', function () {
                selectedEmployees.splice(Number($(this).attr('data-index')), 1);
                renderEmployees();
                syncEmployees();
            });
            $('#ikpAddEmployee').on('click', function () {
                var names = Object.keys(employeeMap);
                var options = names.map(function (name) { return '<option value="' + $('<div>').text(name).html() + '">' + $('<div>').text(name).html() + '</option>'; }).join('');
                Swal.fire({ title: 'Pilih Karyawan', html: '<select id="ikpEmployeePicker" class="form-control"><option value="">-- Pilih --</option>' + options + '</select>', showCancelButton: true, confirmButtonText: 'Tambah' }).then(function (result) {
                    if (result.isConfirmed) addEmployee(employeeMap[$('#ikpEmployeePicker').val()]);
                });
            });

            function loadEmployeesByBagian(callback) {
                var bagian = $('#ikpBagianOriginal').val();
                var currentUserName = $('#ikpNamaOriginal').val();
                $.ajax({ url: 'get_employees_by_bagian.php', method: 'GET', data: { bagian: bagian }, dataType: 'json' }).done(function (resp) {
                    if (!resp || !resp.success || !Array.isArray(resp.data)) return;
                    $.each(resp.data, function (i, emp) { employeeMap[emp.nama_lengkap] = emp; });
                    if (!isEditMode && employeeMap[currentUserName]) addEmployee(employeeMap[currentUserName]);
                    renderEmployees();
                    if (callback) callback(employeeMap);
                }).fail(function () { $('#ikpEmployees').html('<div class="text-danger small">Gagal memuat karyawan.</div>'); });
            }

            // Check if we're in edit mode
            var editTicket = $form.find('input[name="ticket"]').val();
            var isEditMode = !!editTicket;

            if (editTicket) {
                // Load employees first, then load edit data
                loadEmployeesByBagian(function (employeeMap) {
                    // Now load edit data
                    $.ajax({
                        url: '/gg_app/pages/form_umum/load_data.php',
                        method: 'GET',
                        data: { ticket: editTicket },
                        dataType: 'json'
                    }).done(function (resp) {
                        if (resp && resp.success && resp.data) {
                            var d = resp.data;
                            if (d.tgl_pengajuan) {
                                $tgl.val(d.tgl_pengajuan);
                                $tglKeluar.val(d.tgl_keluar || d.tgl_pengajuan);
                                var dt = new Date(d.tgl_pengajuan + 'T00:00:00');
                                $hari.val(DAYS[dt.getDay()]);
                            }

                            // Set nama and update other fields
                            if (Array.isArray(d.employees) && d.employees.length) {
                                selectedEmployees = [];
                                $.each(d.employees, function (i, employee) {
                                    var mapped = employeeMap[employee.nama_pemohon] || employee;
                                    addEmployee({
                                        nik: mapped.nik || employee.nik,
                                        nama_lengkap: mapped.nama_lengkap || employee.nama_pemohon,
                                        dept: mapped.dept || employee.departemen,
                                        bagian: mapped.bagian || employee.bagian,
                                        jabatan: mapped.jabatan || employee.jabatan,
                                        telp: mapped.telp || employee.no_hp
                                    });
                                });
                            } else if (d.nama_pemohon && employeeMap[d.nama_pemohon]) {
                                addEmployee(employeeMap[d.nama_pemohon]);
                            }

                            $('#ikpJamKeluar').val(d.jam_keluar || d.jam_keluar_dari || '');
                            $('#ikpEstimasiKembali').val(d.estimasi_kembali || d.jam_keluar_sampai || '');
                            $('#ikpTujuan').val(d.tujuan || '');

                            if (d.keperluan) {
                                $('#ikpKeperluan').val(d.keperluan).trigger('change');
                                if (d.keperluan === 'Lainnya') {
                                    $('#ikpKeperluanLain').val(d.keperluan_lain || '');
                                }
                            }
                            if (d.kendaraan) {
                                $('#ikpKendaraan').val(d.kendaraan).trigger('change');
                                if (d.kendaraan === 'Lainnya') {
                                    $('#ikpKendaraanLain').val(d.kendaraan_lain || '');
                                }
                            }
                        }
                    });
                });
            } else {
                // Not in edit mode, just load employees
                loadEmployeesByBagian();
            }

            // Toggle Keperluan Lain
            $('#ikpKeperluan').on('change', function () {
                if ($(this).val() === 'Lainnya') {
                    $('#keperluanLainWrapper').show();
                    $('#ikpKeperluanLain').prop('required', true);
                } else {
                    $('#keperluanLainWrapper').hide();
                    $('#ikpKeperluanLain').prop('required', false).val('');
                }
            });

            // Toggle Kendaraan Lain
            $('#ikpKendaraan').on('change', function () {
                if ($(this).val() === 'Lainnya') {
                    $('#kendaraanLainWrapper').show();
                    $('#ikpKendaraanLain').prop('required', true);
                } else {
                    $('#kendaraanLainWrapper').hide();
                    $('#ikpKendaraanLain').prop('required', false).val('');
                }
            });

            // Validasi Jam
            $('#formIzinKeluarPabrik').off('submit.ikpValidate').on('submit.ikpValidate', function (e) {
                var jamKeluar = $('#ikpJamKeluar').val();
                var estimasiKembali = $('#ikpEstimasiKembali').val();

                if (jamKeluar && estimasiKembali && estimasiKembali <= jamKeluar) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Validasi Gagal',
                            text: 'Estimasi Kembali harus lebih besar dari Jam Keluar!'
                        });
                    } else {
                        alert('Estimasi Kembali harus lebih besar dari Jam Keluar!');
                    }
                    return false;
                }
            });

            // Handler: Ambil Jam Sekarang
            $('#btnAmbilJamSekarang').on('click', function () {
                var now = new Date();
                var hours = String(now.getHours()).padStart(2, '0');
                var minutes = String(now.getMinutes()).padStart(2, '0');
                $('#ikpJamKeluar').val(hours + ':' + minutes);
            });

            // Handler: File Upload Validation
            $('#ikpLampiran').on('change', function () {
                var file = this.files[0];
                $('#ikpFileInfo').hide();
                $('#ikpFileError').hide();

                if (!file) return;

                // Validasi ukuran (50MB)
                var maxSize = 50 * 1024 * 1024;
                if (file.size > maxSize) {
                    $('#ikpErrorMessage').text('File terlalu besar. Maksimal 50MB.');
                    $('#ikpFileError').show();
                    this.value = '';
                    return;
                }

                // Validasi ekstensi
                var allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'xlsx'];
                var fileName = file.name.toLowerCase();
                var ext = fileName.substring(fileName.lastIndexOf('.') + 1);
                if (!allowedExtensions.includes(ext)) {
                    $('#ikpErrorMessage').text('Format file tidak diizinkan. Gunakan PDF, JPG, PNG, DOCX, atau XLSX.');
                    $('#ikpFileError').show();
                    this.value = '';
                    return;
                }

                // Tampilkan info file
                var sizeInMB = (file.size / (1024 * 1024)).toFixed(2);
                $('#ikpFileName').text(file.name);
                $('#ikpFileSize').text(sizeInMB + ' MB');
                $('#ikpFileInfo').show();
            });



            window._activeFormId = '#formIzinKeluarPabrik';
        }
    })();
</script>