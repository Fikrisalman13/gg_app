<?php
session_start();
require_once '../../koneksi.php';
require_once '../../includes/app_version.php';
date_default_timezone_set('Asia/Jakarta');
// Cek login dan role (GroupId 1 = Administrator)
if (!isset($_SESSION['UserId'])) {
    header("Location: ../../login.php");
    exit();
}

$isAdmin = ($_SESSION['GroupId'] == 1);
$themeColor = $_SESSION['Theme'] ?? 'primary';
$backupDir = __DIR__ . '/../../backups'; // Define backup directory
?>
<style>
    .swal2-dark-custom {
        border-radius: 15px !important;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3) !important;
    }
    .swal2-title {
        font-weight: 700 !important;
    }
</style>
<?php
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark">System Settings</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active">Settings</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <!-- System Maintenance Card -->
                <div class="col-md-5">
                    <div class="card card-<?= $themeColor ?> card-outline elevation-2">
                        <div class="card-header bg-<?= $themeColor ?> text-white">
                            <h3 class="card-title"><i class="fas fa-server mr-2"></i> Maintenance</h3>
                        </div>
                        <div class="card-body">
                            <h6 class="text-uppercase text-secondary text-xs font-weight-bold mb-3">Backup & Recovery</h6>
                            
                            <div class="d-flex align-items-center justify-content-between bg-light p-3 rounded mb-3">
                                <div>
                                    <strong class="d-block text-dark">Web Files Backup</strong>
                                    <small class="text-muted">Download source code archive</small>
                                </div>
                                <a href="system_action.php?action=web_backup" id="btn-web-backup" class="btn btn-sm btn-outline-info">
                                    <i class="fas fa-download mr-1"></i> Download .zip
                                </a>
                            </div>

                            <hr class="my-4">

                            <h6 class="text-uppercase text-secondary text-xs font-weight-bold mb-3">Disaster Recovery</h6>
                            <form action="system_action.php?action=web_restore" method="post" enctype="multipart/form-data">
                                <label class="text-sm text-dark">Restore Web Files</label>
                                <div class="input-group input-group-sm">
                                    <div class="custom-file">
                                        <input type="file" name="web_file" class="custom-file-input" accept=".zip" required>
                                        <label class="custom-file-label">Choose .zip file</label>
                                    </div>
                                    <div class="input-group-append">
                                        <button type="submit" class="btn btn-warning btn-restore-web">
                                            Restore
                                        </button>
                                    </div>
                                </div>
                                <small class="text-danger mt-1 d-block"><i class="fas fa-exclamation-triangle mr-1"></i> Overwrites current system files!</small>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Update Center Card -->
                <div class="col-md-7">
                    <div class="card card-<?= $themeColor ?> card-outline elevation-2">
                        <div class="card-header bg-<?= $themeColor ?> text-white d-flex justify-content-between align-items-center">
                            <h3 class="card-title"><i class="fas fa-sync-alt mr-2"></i> Update Center</h3>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-center mb-4">
                                <div class="col-md-8">
                                    <h5 class="font-weight-bold text-dark">System Status</h5>
                                    <p class="text-muted mb-0">Current version is <span class="badge badge-success">v<?= APP_VERSION ?></span></p>
                                </div>
                                <div class="col-md-4 text-right">
                                    <button type="button" id="btn-check-update" class="btn btn-<?= $themeColor ?> btn-block elevation-1">
                                        <i class="fas fa-search mr-2"></i> Check Updates
                                    </button>
                                </div>
                            </div>

                            <div id="update-status" class="alert alert-light border text-center" style="display:none;">
                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                <span class="ml-2 text-muted">Contacting update server...</span>
                            </div>

                            <div id="update-result" style="display:none;">
                                <!-- AJAX Content -->
                            </div>

                            <div id="rollback-container" class="mt-4 pt-3 border-top" style="<?= !file_exists($backupDir . '/rollback_info.json') ? 'display:none;' : '' ?>">
                                <?php 
                                $rbInfo = file_exists($backupDir . '/rollback_info.json') ? json_decode(file_get_contents($backupDir . '/rollback_info.json'), true) : null;
                                ?>
                                <div class="callout callout-warning py-2 px-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong class="text-warning text-sm">Review Previous Version</strong><br>
                                            <small class="text-muted">v<?= $rbInfo['version'] ?? '?' ?> (Snapshot: <?= $rbInfo['date'] ?? '-' ?>)</small>
                                        </div>
                                        <button type="button" id="btn-rollback" class="btn btn-xs btn-outline-danger">
                                            <i class="fas fa-undo mr-1"></i> Rollback
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <?php if ($isAdmin): ?>
                                <hr class="my-4">
                                <div class="p-3 border rounded bg-light">
                                    <div class="row align-items-center">
                                        <div class="col-md-7">
                                            <h6 class="font-weight-bold mb-1"><i class="fas fa-rocket text-primary mr-2"></i>Deploy New Version</h6>
                                            <p class="text-muted text-xs mb-0">Upload and release a new update package with ease.</p>
                                        </div>
                                        <div class="col-md-5 text-md-right mt-3 mt-md-0">
                                            <button type="button" class="btn btn-sm btn-primary elevation-1 px-4" data-toggle="modal" data-target="#modal-release-update">
                                                <i class="fas fa-cloud-upload-alt mr-1"></i> Release New Update
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Release Update Modal -->
<div class="modal fade" id="modal-release-update" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= $themeColor ?> text-white">
                <h5 class="modal-title"><i class="fas fa-rocket mr-2"></i>Release System Update</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="system_action.php?action=give_update" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Update Package (.zip)</label>
                        <div class="custom-file">
                            <input type="file" name="update_file" class="custom-file-input" accept=".zip" required>
                            <label class="custom-file-label">Choose file...</label>
                        </div>
                        <small class="form-text text-muted">
                            <i class="fas fa-info-circle text-info"></i> Package should contain `pages`, `includes`, `assets` folders.
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label>Version Number</label>
                        <input type="text" name="new_version" class="form-control" placeholder="e.g. 1.2.0" required>
                    </div>

                    <div class="form-group">
                        <label>Changelog / Release Notes</label>
                        <textarea name="changelog" class="form-control" rows="4" placeholder="What's new in this version?" required></textarea>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <a href="system_action.php?action=download_template" class="btn btn-outline-info">
                        <i class="fas fa-file-download mr-1"></i> Download Guide & Template
                    </a>
                    <button type="submit" class="btn btn-<?= $themeColor ?> btn-give-update">
                        <i class="fas fa-paper-plane mr-1"></i> Release Now
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- SweetAlert2 -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.min.js"></script>

<script>
$(document).ready(function () {
    // Bs-custom-file-input manual change listener for better reliability
    $('.custom-file-input').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').addClass("selected").html(fileName);
    });

    // Web Backup Handler
    $('#btn-web-backup').on('click', function(e) {
        e.preventDefault();
        
        Swal.fire({
            title: 'Create Web Backup?',
            text: "This will archive current system files. This process may take a moment depending on system size.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Start Backup',
            confirmButtonColor: '#17a2b8',
            cancelButtonColor: '#3085d6',
            customClass: {
                popup: 'swal2-dark-custom'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Creating Backup...',
                    html: `
                        <div class="mt-2">
                            <i class="fas fa-cog fa-spin fa-3x text-info mb-3"></i>
                            <p>Please wait while we archive your system files.<br>This may take up to a minute.</p>
                        </div>
                    `,
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    customClass: {
                        popup: 'swal2-dark-custom'
                    },
                    didOpen: () => {
                        // Optional: you could add an actual progress bar here if you have a progress API
                    }
                });

                $.ajax({
                    url: 'system_action.php?action=web_backup',
                    type: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        if (data.status === 'success') {
                            Swal.close();
                            // Trigger the actual file download
                            window.location.href = 'system_action.php?action=download_backup&file=' + data.file;
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Backup Failed',
                                text: data.message,
                                customClass: { popup: 'swal2-dark-custom' }
                            });
                        }
                    },
                    error: function(xhr) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An unexpected error occurred. Connection may have timed out or server failed.',
                            customClass: { popup: 'swal2-dark-custom' }
                        });
                    }
                });
            }
        });
    });

    // Success/Error Toast
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000
    });

    <?php 
    $flash_status = $_SESSION['flash_status'] ?? null;
    $flash_message = $_SESSION['flash_message'] ?? null;
    if ($flash_status): 
        unset($_SESSION['flash_status']);
        unset($_SESSION['flash_message']);
        $isSuccess = ($flash_status == 'success');
    ?>
        Swal.fire({
            icon: '<?= $isSuccess ? 'success' : 'error' ?>',
            title: '<?= $isSuccess ? 'Operation Successful' : 'Operation Failed' ?>',
            html: '<?= addslashes($flash_message) ?>',
            confirmButtonText: 'OK',
            confirmButtonColor: '<?= $isSuccess ? '#28a745' : '#d33' ?>',
            customClass: {
                popup: 'swal2-dark-custom'
            }
        });
    <?php endif; ?>

    // Confirmation for Restore
    $('.btn-restore-db, .btn-restore-web').closest('form').on('submit', function(e) {
        e.preventDefault();
        var form = this;
        var btn = $(form).find('button');
        var type = btn.hasClass('btn-restore-db') ? 'Database' : 'Web Files';
        
        Swal.fire({
            title: 'Restore ' + type + '?',
            text: "This will overwrite your current " + (btn.hasClass('btn-restore-db') ? 'data' : 'codebase') + "!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, Restore!'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });

    // Confirmation for Give Update
    $('.btn-give-update').closest('form').on('submit', function(e) {
        e.preventDefault();
        var form = this;
        var version = $(form).find('input[name="new_version"]').val();

        Swal.fire({
            title: 'Release update v' + version + '?',
            text: "This update will be staged. You must then 'Check for Update' to apply it to this server.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, Release!'
        }).then((result) => {
            if (result.isConfirmed) {
                $('.btn-give-update').addClass('disabled').attr('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Releasing...');
                form.submit();
            }
        });
    });

    // Help Icon Click
    $('#btn-update-help').on('click', function() {
        Swal.fire({
            title: 'Update Package Guide',
            html: `
                <div class="text-left text-sm">
                    <p>The ZIP package should contain the updated core files of the application. Recommended structure:</p>
                    <ul class="pl-3">
                        <li><b>includes/</b> - Core logic and layout</li>
                        <li><b>pages/</b> - New or updated features</li>
                        <li><b>assets/</b> - CSS, JS, and Images</li>
                    </ul>
                    <p class="mb-0 text-danger"><i class="fas fa-exclamation-triangle"></i> Do NOT include <b>backups/</b>, <b>releases/</b>, or <b>koneksi.php</b> unless necessary.</p>
                </div>
            `,
            icon: 'info',
            confirmButtonText: 'Understood'
        });
    });

    $('#btn-check-update').on('click', function() {
        var $btn = $(this);
        var $status = $('#update-status');
        var $result = $('#update-result');

        $btn.addClass('disabled').attr('disabled', true);
        $status.fadeIn();
        $result.fadeOut();

        $.ajax({
            url: 'system_action.php?action=check_update',
            type: 'GET',
            cache: false,
            dataType: 'json',
            success: function(data) {
                $status.hide();
                $btn.removeClass('disabled').attr('disabled', false);
                $result.show();

                        if (data.update_available) {
                            // Format changelog newlines to <br>
                            var formattedChangelog = (data.changelog || 'N/A').replace(/\n/g, '<br>');
                            
                            $result.html(`
                                <div class="alert alert-info">
                                    <h5><i class="icon fas fa-info"></i> Update Available!</h5>
                                    A new version <b>v${data.latest_version}</b> is ready to install.<br>
                                    <div class="mt-2 text-left bg-white text-dark p-2 rounded" style="font-size: 0.9em;">
                                        <strong>Changelog:</strong><br>
                                        ${formattedChangelog}
                                    </div>
                                    <div id="update-progress-container" style="display:none;" class="mt-3">
                                        <div class="progress progress-sm active">
                                            <div id="update-progress-bar" class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                                        </div>
                                        <small class="text-xs text-center d-block mt-1">Installing version ${data.latest_version}... Please wait.</small>
                                    </div>
                                    <button id="btn-apply-update" class="btn btn-info btn-block mt-3" data-version="${data.latest_version}">
                                        <i class="fas fa-download mr-1"></i> Apply Update Now
                                    </button>
                                </div>
                            `);
                        } else {
                            $result.hide();
                            Swal.fire({
                                icon: 'success',
                                title: 'Up to date',
                                text: 'Your system is up to date.',
                                confirmButtonColor: '#28a745'
                            });
                        }
            },
            error: function() {
                $status.hide();
                $btn.removeClass('disabled').attr('disabled', false);
                $result.show().html('<div class="alert alert-danger">Error checking for updates.</div>');
            }
        });
    });

    $(document).on('click', '#btn-apply-update', function() {
        var $btn = $(this);
        var version = $btn.data('version');

        Swal.fire({
            title: 'Apply Update v' + version + '?',
            text: "The system will extract new files and update the version. This may take a moment.",
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Yes, Apply Now'
        }).then((result) => {
            if (result.isConfirmed) {
                $btn.hide();
                $('#update-progress-container').fadeIn();
                
                // Smoother, more linear progress simulation
                var progress = 0;
                var interval = setInterval(function() {
                    progress += 0.5; // Small, steady increments
                    if (progress > 98) {
                        progress = 98;
                        clearInterval(interval);
                    }
                    $('#update-progress-bar').css('width', progress + '%');
                }, 200);

                $.ajax({
                    url: 'system_action.php?action=install_update',
                    type: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        clearInterval(interval);
                        $('#update-progress-bar').css('width', '100%');
                        
                        setTimeout(function() {
                            if (data.status === 'success') {
                                Swal.fire('Success!', 'System updated to v' + version, 'success').then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire('Failed!', 'Update failed: ' + data.message, 'error');
                                $btn.show();
                                $('#update-progress-container').hide();
                            }
                        }, 500);
                    },
                    error: function() {
                        clearInterval(interval);
                        Swal.fire('Error!', 'Unexpected error during update.', 'error');
                        $btn.show();
                        $('#update-progress-container').hide();
                    }
                });
            }
        });
    });

    $(document).on('click', '#btn-rollback', function() {
        Swal.fire({
            title: 'Rollback to previous version?',
            text: "This will revert all web files and the version number to the snapshot taken before the last update.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, Rollback!'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Rolling back...',
                    html: 'Restoring files from snapshot. Please wait.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Using AJAX for rollback to ensure loading stays visible
                $.ajax({
                    url: 'system_action.php?action=rollback',
                    type: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        if (data.status === 'success') {
                            // After success, reload to show the result from session
                            window.location.href = 'settings.php';
                        } else {
                            Swal.fire('Failed!', data.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'An unexpected error occurred during rollback.', 'error');
                    }
                });
            }
        });
    });
});
</script>
