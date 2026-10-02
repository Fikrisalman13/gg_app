<?php

declare(strict_types=1);

session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../../koneksi3.php';
require_once __DIR__ . '/auto_packing_lib.php';

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu!';
    header('Location: /gg_app/login.php');
    exit;
}

$allowedThemes = ['primary', 'secondary', 'success', 'info', 'warning', 'danger', 'dark', 'light', 'indigo', 'navy', 'purple', 'fuchsia', 'pink', 'maroon', 'orange', 'lime', 'teal', 'olive'];
$userTheme = strtolower((string)($_SESSION['Theme'] ?? 'primary'));
if (!in_array($userTheme, $allowedThemes, true)) {
    $userTheme = 'primary';
}
$themeTextClass = in_array($userTheme, ['warning', 'light', 'lime'], true) ? 'text-dark' : 'text-white';

try {
    $actor = outputVpkAutoRequireEditor($conn);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        outputVpkAutoRequireCsrf();
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save_config') {
            outputVpkAutoSaveConfig($conn, isset($_POST['is_enabled']), (string)($_POST['run_time'] ?? ''), $actor['username']);
            $_SESSION['success'] = 'Pengaturan otomatisasi disimpan.';
        } elseif ($action === 'pull_now') {
            $targetDate = outputVpkAutoDate((string)($_POST['target_date'] ?? ''));
            outputVpkAutoRequirePreviewToken($targetDate, (string)($_POST['preview_token'] ?? ''));
            $result = outputVpkAutoPull($conn, $conn3, $targetDate, $actor['username']);
            $_SESSION[$result['status'] === 'success' ? 'success' : 'error'] = $result['message'];
        } else {
            throw new InvalidArgumentException('Aksi tidak valid.');
        }
        header('Location: auto_packing_settings.php');
        exit;
    }
    $status = outputVpkAutoStatus($conn);
} catch (Throwable $error) {
    $_SESSION['error'] = $error->getMessage();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Location: auto_packing_settings.php');
        exit;
    }
    $status = ['config' => ['is_enabled' => 0, 'run_time' => new DateTimeImmutable('00:15:00')], 'last_run' => null];
}

$csrfToken = outputVpkAutoCsrfToken();
$config = $status['config'];
$lastRun = $status['last_run'];
$runTime = $config['run_time'] instanceof DateTimeInterface ? $config['run_time']->format('H:i') : substr((string)$config['run_time'], 0, 5);
$lastStatus = $lastRun ? (string)$lastRun['run_status'] : 'Belum pernah diproses';
$lastStatusClass = $lastRun ? ((string)$lastRun['run_status'] === 'success' ? 'success' : ((string)$lastRun['run_status'] === 'failed' ? 'danger' : 'warning')) : 'secondary';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1 class="m-0">Tarik Otomatis Output Packing</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="data.php">Data Output Packing</a></li>
                        <li class="breadcrumb-item active">Otomatisasi</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="status">
                    <?= htmlspecialchars((string)$_SESSION['success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['success']); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars((string)$_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-6 d-flex">
                    <div class="card card-outline card-<?= htmlspecialchars($userTheme, ENT_QUOTES, 'UTF-8') ?> w-100">
                        <div class="card-header bg-<?= htmlspecialchars($userTheme, ENT_QUOTES, 'UTF-8') ?> <?= $themeTextClass ?> d-flex align-items-center">
                            <h3 class="card-title"><i class="fas fa-clock mr-2" aria-hidden="true"></i>Jadwal Harian</h3>
                        </div>
                        <form method="post">
                            <div class="card-body">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="save_config">
                                <div class="custom-control custom-switch mb-4">
                                    <input class="custom-control-input" type="checkbox" id="vpk-auto-enabled" name="is_enabled" value="1" <?= (int)$config['is_enabled'] === 1 ? 'checked' : '' ?>>
                                    <label class="custom-control-label font-weight-bold" for="vpk-auto-enabled">Aktifkan tarikan otomatis</label>
                                    <small class="form-text text-muted">Worker memproses data tanggal kemarin.</small>
                                </div>
                                <div class="form-group mb-0">
                                    <label for="vpk-auto-time">Jam tarik (WIB)</label>
                                    <input class="form-control" type="time" id="vpk-auto-time" name="run_time" value="<?= htmlspecialchars($runTime, ENT_QUOTES, 'UTF-8') ?>" required>
                                    <small class="form-text text-muted">Proses berjalan satu kali setelah jam ini tercapai.</small>
                                </div>
                            </div>
                            <div class="card-footer bg-white">
                                <button class="btn btn-<?= htmlspecialchars($userTheme, ENT_QUOTES, 'UTF-8') ?>" type="submit"><i class="fas fa-save mr-1" aria-hidden="true"></i>Simpan Pengaturan</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-lg-6 d-flex">
                    <div class="card card-outline card-<?= htmlspecialchars($userTheme, ENT_QUOTES, 'UTF-8') ?> w-100">
                        <div class="card-header bg-<?= htmlspecialchars($userTheme, ENT_QUOTES, 'UTF-8') ?> <?= $themeTextClass ?>">
                            <h3 class="card-title"><i class="fas fa-history mr-2" aria-hidden="true"></i>Status Terakhir</h3>
                            <div class="card-tools">
                                <span class="badge badge-<?= $lastStatusClass ?> px-2 py-1"><?= htmlspecialchars($lastStatus, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0" aria-label="Status proses otomatisasi terakhir">
                                <tbody>
                                    <tr><th scope="row" class="w-45">Tanggal target</th><td><?= $lastRun && $lastRun['target_date'] instanceof DateTimeInterface ? $lastRun['target_date']->format('d-m-Y') : '-' ?></td></tr>
                                    <tr><th scope="row">Waktu proses</th><td><?= $lastRun && $lastRun['completed_at'] instanceof DateTimeInterface ? $lastRun['completed_at']->format('d-m-Y H:i:s') : '-' ?></td></tr>
                                    <tr><th scope="row">Qty Packing / A1</th><td><?= $lastRun && $lastRun['qty'] !== null ? number_format((float)$lastRun['qty'], 2, ',', '.') . ' / ' . number_format((float)$lastRun['qty_a1'], 2, ',', '.') : '-' ?></td></tr>
                                    <tr><th scope="row">Keterangan</th><td><?= $lastRun ? htmlspecialchars((string)($lastRun['message'] ?? '-'), ENT_QUOTES, 'UTF-8') : '-' ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card card-outline card-<?= htmlspecialchars($userTheme, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="card-header bg-<?= htmlspecialchars($userTheme, ENT_QUOTES, 'UTF-8') ?> <?= $themeTextClass ?>">
                            <h3 class="card-title"><i class="fas fa-tools mr-2" aria-hidden="true"></i>Tarik Data Manual</h3>
                        </div>
                        <form method="post" id="vpk-auto-pull-form">
                            <div class="card-body">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="pull_now">
                                <input type="hidden" name="preview_token" id="vpk-auto-preview-token" value="">
                                <div class="row align-items-end">
                                    <div class="form-group col-md-4 mb-md-0">
                                        <label for="vpk-auto-date">Tanggal target</label>
                                        <input class="form-control" type="date" id="vpk-auto-date" name="target_date" value="<?= date('Y-m-d', strtotime('-1 day')) ?>" required>
                                    </div>
                                    <div class="col-md-8">
                                        <button class="btn btn-<?= htmlspecialchars($userTheme, ENT_QUOTES, 'UTF-8') ?>" type="button" id="vpk-auto-preview-button"><i class="fas fa-eye mr-1" aria-hidden="true"></i>Preview Data</button>
                                        <small class="d-block text-muted mt-2">Preview tidak menyimpan data. Simpan hanya tersedia setelah hasil diperiksa.</small>
                                    </div>
                                </div>
                                <div class="alert alert-light border mt-4 mb-0 d-none" id="vpk-auto-preview-result" role="status" aria-live="polite"></div>
                                <div class="mt-3 d-none" id="vpk-auto-save-actions">
                                    <button class="btn btn-success" type="submit" id="vpk-auto-save-button"><i class="fas fa-save mr-1" aria-hidden="true"></i>Simpan Snapshot</button>
                                    <button class="btn btn-outline-secondary ml-1" type="button" id="vpk-auto-cancel-preview">Batal</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<?php include '../../includes/footer.php'; ?>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script>
(function () {
    var form = document.getElementById('vpk-auto-pull-form');
    var dateInput = document.getElementById('vpk-auto-date');
    var previewButton = document.getElementById('vpk-auto-preview-button');
    var result = document.getElementById('vpk-auto-preview-result');
    var saveActions = document.getElementById('vpk-auto-save-actions');
    var cancelButton = document.getElementById('vpk-auto-cancel-preview');
    var saveButton = document.getElementById('vpk-auto-save-button');
    var previewToken = document.getElementById('vpk-auto-preview-token');

    function resetPreview() {
        result.className = 'alert alert-light border mt-4 mb-0 d-none';
        result.textContent = '';
        previewToken.value = '';
        saveActions.classList.add('d-none');
        previewButton.disabled = false;
    }

    function formatNumber(value) {
        return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value));
    }

    dateInput.addEventListener('change', resetPreview);
    cancelButton.addEventListener('click', resetPreview);

    previewButton.addEventListener('click', function () {
        if (!dateInput.value) {
            dateInput.focus();
            return;
        }
        previewButton.disabled = true;
        result.className = 'alert alert-info mt-4 mb-0';
        result.textContent = 'Memuat preview Tarikan Susut...';
        saveActions.classList.add('d-none');

        fetch('auto_packing_preview.php?target_date=' + encodeURIComponent(dateInput.value), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' }
        })
            .then(function (response) { return response.json().then(function (data) { return { response: response, data: data }; }); })
            .then(function (payload) {
                if (!payload.response.ok || !payload.data.ok) {
                    throw new Error(payload.data.message || 'Preview tidak dapat dimuat.');
                }
                if (payload.data.state !== 'ready') {
                    result.className = 'alert alert-warning mt-4 mb-0';
                    result.textContent = payload.data.message;
                    return;
                }
                result.className = 'alert alert-info mt-4 mb-0';
                result.innerHTML = '<strong>Data siap disimpan</strong><br>Quantity Packing: <strong>' + formatNumber(payload.data.summary.qty) + '</strong><br>Quantity A1: <strong>' + formatNumber(payload.data.summary.qty_a1) + '</strong>';
                previewToken.value = payload.data.preview_token;
                saveActions.classList.remove('d-none');
            })
            .catch(function (error) {
                result.className = 'alert alert-danger mt-4 mb-0';
                result.textContent = error.message || 'Preview tidak dapat dimuat.';
            })
            .finally(function () {
                previewButton.disabled = false;
            });
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (typeof Swal === 'undefined') {
            result.className = 'alert alert-danger mt-4 mb-0';
            result.textContent = 'Komponen konfirmasi tidak tersedia. Muat ulang halaman.';
            return;
        }
        Swal.fire({
            icon: 'question',
            title: 'Simpan snapshot?',
            text: 'Simpan hasil preview untuk tanggal dipilih? Data yang sudah ada tidak akan diubah.',
            showCancelButton: true,
            confirmButtonText: 'Simpan Snapshot',
            cancelButtonText: 'Batal',
            focusCancel: true
        }).then(function (confirmation) {
            if (!confirmation.isConfirmed) {
                return;
            }
            saveButton.disabled = true;
            form.submit();
        });
    });
}());
</script>