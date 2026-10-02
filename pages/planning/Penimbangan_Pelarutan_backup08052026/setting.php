<?php
session_start();
// Ensure server uses Jakarta timezone for issue timestamps
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
    header("Location: ../../../login.php");
    exit();
}
$title = "Setting Aktual Paddry";
include_once '../../../koneksi.php';
include_once '../../../includes/header.php';
include_once '../../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <link rel="stylesheet"
        href="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Konfigurasi Penembakan & Pelarutan</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="index.php">Aktual Paddry</a></li>
                        <li class="breadcrumb-item active">Setting</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-8 mx-auto">
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-cogs mr-1"></i> Pengaturan Global</h3>
                        </div>
                        <div class="card-body" id="settingContainer">
                            <div class="text-center py-5" id="loader">
                                <i class="fas fa-circle-notch fa-spin fa-3x text-primary"></i>
                                <p class="mt-2 text-muted">Memuat pengaturan...</p>
                            </div>

                            <form id="settingForm" style="display: none;">

                                <h5 class="text-info font-weight-bold mb-3 border-bottom pb-2">Mode Eksekusi Antrean
                                </h5>
                                <div class="form-group mb-4">
                                    <div
                                        class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success custom-control-lg">
                                        <input type="checkbox" class="custom-control-input setting-input"
                                            id="ENFORCE_SEQUENCE" data-code="ENFORCE_SEQUENCE">
                                        <label class="custom-control-label" for="ENFORCE_SEQUENCE"
                                            id="label_ENFORCE_SEQUENCE">Mode Sequential (Berurutan)</label>
                                    </div>
                                    <small class="form-text text-muted">
                                        <i class="fas fa-info-circle text-info"></i> Jika <b
                                            class="text-success">Aktif</b>, Rencana (CP) nomor 2 tidak dapat dimulai
                                        sebelum Rencana nomor 1 (Tahap 1) selesai. <br>
                                        Jika <b class="text-danger">Nonaktif</b> (Mode Bebas), semua Rencana dapat
                                        dieksekusi tanpa melihat urutan CP.
                                    </small>
                                </div>

                                <h5 class="text-info font-weight-bold mb-3 border-bottom pb-2">Tombol Rollback</h5>
                                <div class="form-group mb-4">
                                    <div class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success custom-control-lg">
                                        <input type="checkbox" class="custom-control-input setting-input" id="SHOW_ROLLBACK_BUTTON" data-code="SHOW_ROLLBACK_BUTTON">
                                        <label class="custom-control-label" for="SHOW_ROLLBACK_BUTTON" id="label_SHOW_ROLLBACK_BUTTON">Tampilkan Tombol Rollback</label>
                                    </div>
                                    <small class="form-text text-muted">
                                        <i class="fas fa-info-circle text-info"></i> Jika <b class="text-success">Aktif</b>, tombol "Tester: Rollback Stage Terakhir" akan muncul pada kartu CP yang sedang berjalan atau sudah selesai.
                                    </small>
                                </div>

                                <h5 class="text-info font-weight-bold mb-3 border-bottom pb-2">Wajib Input Nomor Roda
                                    (Wheel No)</h5>
                                <p class="text-muted small mb-3">Tentukan tahapan routing mana saja yang mewajibkan
                                    input Nomor Roda saat tombol START ditekan.</p>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <div class="custom-control custom-switch custom-switch-on-primary">
                                                <input type="checkbox" class="custom-control-input setting-input"
                                                    id="REQ_WHEEL_876" data-code="REQ_WHEEL_876">
                                                <label class="custom-control-label" for="REQ_WHEEL_876">Penimbangan Obat
                                                    Lab (876)</label>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <div class="custom-control custom-switch custom-switch-on-primary">
                                                <input type="checkbox" class="custom-control-input setting-input"
                                                    id="REQ_WHEEL_877" data-code="REQ_WHEEL_877">
                                                <label class="custom-control-label" for="REQ_WHEEL_877">Pelarutan Obat
                                                    Lab (877)</label>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <div class="custom-control custom-switch custom-switch-on-primary">
                                                <input type="checkbox" class="custom-control-input setting-input"
                                                    id="REQ_WHEEL_880" data-code="REQ_WHEEL_880">
                                                <label class="custom-control-label" for="REQ_WHEEL_880">Pelarutan Obat
                                                    Produksi (880)</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <div class="custom-control custom-switch custom-switch-on-primary">
                                                <input type="checkbox" class="custom-control-input setting-input"
                                                    id="REQ_WHEEL_878" data-code="REQ_WHEEL_878">
                                                <label class="custom-control-label" for="REQ_WHEEL_878">Penimbangan Obat
                                                    La (878)</label>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <div class="custom-control custom-switch custom-switch-on-primary">
                                                <input type="checkbox" class="custom-control-input setting-input"
                                                    id="REQ_WHEEL_879" data-code="REQ_WHEEL_879">
                                                <label class="custom-control-label" for="REQ_WHEEL_879">Pelarutan Obat
                                                    La (879)</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <h5 class="text-info font-weight-bold mt-4 mb-3 border-bottom pb-2">Wajib Input Lebar
                                    Kain</h5>
                                <p class="text-muted small mb-3">Tentukan tahapan routing mana saja yang mewajibkan
                                    input Lebar Kain saat tahap selesai (STOP ditekan).</p>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <div class="custom-control custom-switch custom-switch-on-success">
                                                <input type="checkbox" class="custom-control-input setting-input"
                                                    id="REQ_LEBAR_876" data-code="REQ_LEBAR_876">
                                                <label class="custom-control-label" for="REQ_LEBAR_876">Penimbangan Obat
                                                    Lab (876)</label>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <div class="custom-control custom-switch custom-switch-on-success">
                                                <input type="checkbox" class="custom-control-input setting-input"
                                                    id="REQ_LEBAR_877" data-code="REQ_LEBAR_877">
                                                <label class="custom-control-label" for="REQ_LEBAR_877">Pelarutan Obat
                                                    Lab (877)</label>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <div class="custom-control custom-switch custom-switch-on-success">
                                                <input type="checkbox" class="custom-control-input setting-input"
                                                    id="REQ_LEBAR_880" data-code="REQ_LEBAR_880">
                                                <label class="custom-control-label" for="REQ_LEBAR_880">Pelarutan Obat
                                                    Produksi (880)</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <div class="custom-control custom-switch custom-switch-on-success">
                                                <input type="checkbox" class="custom-control-input setting-input"
                                                    id="REQ_LEBAR_878" data-code="REQ_LEBAR_878">
                                                <label class="custom-control-label" for="REQ_LEBAR_878">Penimbangan Obat
                                                    La (878)</label>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <div class="custom-control custom-switch custom-switch-on-success">
                                                <input type="checkbox" class="custom-control-input setting-input"
                                                    id="REQ_LEBAR_879" data-code="REQ_LEBAR_879">
                                                <label class="custom-control-label" for="REQ_LEBAR_879">Pelarutan Obat
                                                    La (879)</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <h5 class="text-info font-weight-bold mt-4 mb-3 border-bottom pb-2">Akses Tombol Breaktime</h5>
                                <p class="text-muted small mb-3">Tentukan tahapan routing mana saja yang grupnya diizinkan untuk memulai dan menghentikan Breaktime.</p>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <div class="custom-control custom-switch custom-switch-on-warning">
                                                <input type="checkbox" class="custom-control-input setting-input" id="SHOW_BREAK_876" data-code="SHOW_BREAK_876">
                                                <label class="custom-control-label" for="SHOW_BREAK_876">Penimbangan Obat Lab (876)</label>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <div class="custom-control custom-switch custom-switch-on-warning">
                                                <input type="checkbox" class="custom-control-input setting-input" id="SHOW_BREAK_877" data-code="SHOW_BREAK_877">
                                                <label class="custom-control-label" for="SHOW_BREAK_877">Pelarutan Obat Lab (877)</label>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <div class="custom-control custom-switch custom-switch-on-warning">
                                                <input type="checkbox" class="custom-control-input setting-input" id="SHOW_BREAK_880" data-code="SHOW_BREAK_880">
                                                <label class="custom-control-label" for="SHOW_BREAK_880">Pelarutan Obat Produksi (880)</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <div class="custom-control custom-switch custom-switch-on-warning">
                                                <input type="checkbox" class="custom-control-input setting-input" id="SHOW_BREAK_878" data-code="SHOW_BREAK_878">
                                                <label class="custom-control-label" for="SHOW_BREAK_878">Penimbangan Obat La (878)</label>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <div class="custom-control custom-switch custom-switch-on-warning">
                                                <input type="checkbox" class="custom-control-input setting-input" id="SHOW_BREAK_879" data-code="SHOW_BREAK_879">
                                                <label class="custom-control-label" for="SHOW_BREAK_879">Pelarutan Obat La (879)</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4 pt-3 border-top text-right">
                                    <a href="index.php" class="btn btn-default mr-2"><i class="fas fa-arrow-left"></i>
                                        Kembali ke Dashboard</a>
                                    <button type="button" class="btn btn-primary" id="btnSave"><i
                                            class="fas fa-save"></i> Simpan Pengaturan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include_once '../../../includes/footer.php'; ?>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.min.js"></script>

<style>
    .custom-switch.custom-switch-lg .custom-control-label {
        padding-left: 2rem;
        padding-bottom: 1rem;
    }

    .custom-switch.custom-switch-lg .custom-control-label::before {
        height: 1.5rem;
        width: 2.5rem;
        border-radius: 2rem;
    }

    .custom-switch.custom-switch-lg .custom-control-label::after {
        width: 1.5rem;
        height: 1.5rem;
        border-radius: 2rem;
    }

    .custom-switch-off-danger .custom-control-input:not(:checked)~.custom-control-label::before {
        background-color: #dc3545;
        border-color: #dc3545;
    }
</style>

<script>
    $(document).ready(function () {
        loadSettings();

        $('#ENFORCE_SEQUENCE').change(function () {
            if ($(this).is(':checked')) {
                $('#label_ENFORCE_SEQUENCE').text('Mode Sequential (Berurutan)');
            } else {
                $('#label_ENFORCE_SEQUENCE').text('Mode Bebas (Tanpa Urutan CP)');
            }
        });

        $('#btnSave').click(function () {
            let payload = {};
            $('.setting-input').each(function () {
                let code = $(this).data('code');
                let val = $(this).is(':checked') ? '1' : '0';
                payload[code] = val;
            });

            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');

            $.ajax({
                url: 'api_setting.php',
                type: 'POST',
                data: {
                    action: 'save_settings',
                    settings: payload
                },
                dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        Swal.fire('Berhasil', res.message, 'success');
                    } else {
                        Swal.fire('Gagal', res.message, 'error');
                    }
                },
                error: function () {
                    Swal.fire('Error', 'Terjadi kesalahan pada server.', 'error');
                },
                complete: function () {
                    btn.prop('disabled', false).html('<i class="fas fa-save"></i> Simpan Pengaturan');
                }
            });
        });
    });

    function loadSettings() {
        $.ajax({
            url: 'api_setting.php',
            type: 'GET',
            data: { action: 'get_settings' },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    let data = res.data;
                    for (let code in data) {
                        let val = data[code].value;
                        let el = $('#' + code);
                        if (el.length > 0) {
                            el.prop('checked', val === '1');
                        }
                    }
                    $('#ENFORCE_SEQUENCE').trigger('change');

                    $('#loader').hide();
                    $('#settingForm').fadeIn();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function () {
                Swal.fire('Error', 'Gagal memuat pengaturan.', 'error');
            }
        });
    }
</script>