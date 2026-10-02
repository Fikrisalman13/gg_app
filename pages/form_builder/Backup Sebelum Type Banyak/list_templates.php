<?php
session_start();
require_once '../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

/**
 * Helper permission untuk menu Form Builder (MenuId = 134)
 */
function checkPermissions($conn, $groupId, $menuId) {
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete
            FROM dbo.SMGroupTrustee
            WHERE GroupId = ? AND MenuId = ?";
    $params = [$groupId, $menuId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    $permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    if ($stmt !== false) {
        sqlsrv_free_stmt($stmt);
    }

    return $permissions;
}

$permissions = checkPermissions($conn, $_SESSION['GroupId'], 176);
$themeColor = $_SESSION['Theme'] ?? 'primary';
$canAdd = !empty($permissions['CanAdd']) && $permissions['CanAdd'] == 1;
$canEdit = !empty($permissions['CanEdit']) && $permissions['CanEdit'] == 1;
$canDelete = !empty($permissions['CanDelete']) && $permissions['CanDelete'] == 1;
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}

function formBuilderSupportsPublicColumn($conn) {
    static $hasColumn = null;
    if ($hasColumn !== null) {
        return $hasColumn;
    }

    $sqlCheck = "SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.Form_Dynamic_Templates') AND name = 'is_public'";
    $stmtCheck = sqlsrv_query($conn, $sqlCheck);
    $hasColumn = ($stmtCheck && sqlsrv_fetch_array($stmtCheck)) ? true : false;
    if ($stmtCheck) {
        sqlsrv_free_stmt($stmtCheck);
    }

    return $hasColumn;
}

include '../../includes/header.php';
?>
<!-- DataTables CSS -->
<link rel="stylesheet" href="../../plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="../../plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="../../plugins/AdminLTE-3.2.0/plugins/datatables-buttons/css/dataTables.bootstrap4.min.css">
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="../../plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.min.css">
<style>
    .badge-user { font-weight: 500; color: #1f2937; }
    .description-text {
        max-width: 350px;
        max-height: 80px;
        font-size: 0.9em;
        color: #6c757d;
        overflow-y: auto;
        padding-right: 5px;
    }
    /* Simple Scrollbar */
    .description-text::-webkit-scrollbar {
        width: 3px;
    }
    .description-text::-webkit-scrollbar-thumb {
        background: #ddd;
        border-radius: 10px;
    }
    .btn-action-group {
        display: flex;
        gap: 3px;
        flex-wrap: wrap;
    }
</style>

<?php
include '../../includes/sidebar.php';
$supportsPublicColumn = formBuilderSupportsPublicColumn($conn);
$requestScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$formShareBaseUrl = $requestScheme . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/form.php?template_name=';
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Form Builder</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item active">Form Builder</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php 
            $successMessage = null;
            if (isset($_SESSION['success'])) {
                $successMessage = $_SESSION['success'];
                unset($_SESSION['success']);
            }
            ?>

            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                    <h3 class="card-title"><i class="fas fa-list mr-1"></i> Daftar Template Form</h3>
                    <div class="card-tools">
                        <?php if ($canAdd): ?>
                            <a href="builder.php" class="btn btn-success btn-sm">
                                <i class="fas fa-plus"></i> Buat Form Baru
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="table-templates" class="table table-hover table-sm" style="width:100%;">
                            <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>Nama Form</th>
                                    <th style="width: 35%">Deskripsi</th>
                                    <th>Dibuat Oleh</th>
                                    <th>Tanggal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sql = "SELECT * FROM Form_Dynamic_Templates WHERE is_active = 1 ORDER BY created_at DESC";
                                $stmt = sqlsrv_query($conn, $sql);
                                $no = 1;
                                if ($stmt) {
                                    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                                        $templateNameParam = urlencode($row['template_name']);
                                        $shareUrl = $formShareBaseUrl . $templateNameParam;
                                        $isPublicTemplate = $supportsPublicColumn && !empty($row['is_public']);
                                        echo "<tr>";
                                        echo "<td>" . $no++ . "</td>";
                                        echo "<td>" . htmlspecialchars($row['template_name']) . "</td>";
                                        echo "<td><div class='description-text'>" . nl2br(htmlspecialchars($row['template_description'])) . "</div></td>";
                                        echo "<td>" . htmlspecialchars($row['created_by']) . "</td>";
                                        echo "<td>" . ($row['created_at'] ? $row['created_at']->format('d-m-Y') : '-') . "</td>";
                                        $currentUserName = $_SESSION['NamaLengkap'] ?? $_SESSION['UserName'];
                                        $canManagePublic = ($_SESSION['GroupId'] == 1 || $row['created_by'] === $currentUserName);
                                        
                                        $actionButtons = "
                                            <div class='btn-action-group'>
                                                <a href='form.php?template_name=$templateNameParam' class='btn btn-xs btn-primary' title='Isi Form'><i class='fas fa-edit'></i> Isi</a>
                                                <a href='submissions.php?template_name=$templateNameParam' class='btn btn-xs btn-info' title='Lihat Hasil'><i class='fas fa-list'></i> Hasil</a>";
                                        if ($canEdit) {
                                            $actionButtons .= "
                                            <a href='builder.php?template_name=$templateNameParam' class='btn btn-xs btn-warning' title='Edit Template'><i class='fas fa-cog'></i> Design</a>";
                                        }
                                        $actionButtons .= "
                                            <button type='button' class='btn btn-xs btn-secondary btn-share'
                                                data-share-url='" . htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8') . "'
                                                data-template-name='" . htmlspecialchars($row['template_name'], ENT_QUOTES, 'UTF-8') . "'
                                                data-template-id='" . htmlspecialchars((string)($row['id'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                                data-is-public='" . ($isPublicTemplate ? '1' : '0') . "'
                                                data-can-manage-public='" . ($canManagePublic ? '1' : '0') . "'
                                                title='Bagikan Link'><i class='fas fa-share-alt'></i> Share</button>
                                            <a href='delete_template.php?template_name=$templateNameParam' class='btn btn-xs btn-danger btn-delete' title='Hapus Template'><i class='fas fa-trash'></i> Hapus</a>
                                        </div>";
                                        echo "<td>$actionButtons</td>";
                                        echo "</tr>";
                                    }
                                    sqlsrv_free_stmt($stmt);
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </section>
</div>

<?php include '../../includes/footer.php'; ?>

<div class="modal fade" id="shareFormModal" tabindex="-1" role="dialog" aria-labelledby="shareFormModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor);?> text-white"">
                <h5 class="modal-title" id="shareFormModalLabel">Bagikan Form</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Kelola status publik sebelum membagikan <strong id="share-modal-template-name">Form</strong>.</p>
                <div id="share-public-note" class="alert mb-3 d-none"></div>
                <?php if ($supportsPublicColumn): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <p class="mb-0 font-weight-bold">Form Publik</p>
                            <small class="text-muted">Jika aktif, siapa pun dengan link dapat mengisi tanpa login.</small>
                        </div>
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="share-is-public">
                            <label class="custom-control-label" for="share-is-public"></label>
                        </div>
                    </div>
                    <div id="share-public-spinner" class="text-muted small d-none">
                        <span class="spinner-border spinner-border-sm mr-2"></span>Menyimpan perubahan...
                    </div>
                    <hr>
                <?php else: ?>
                    <div class="alert alert-warning">
                        Pengaturan form publik otomatis belum tersedia. Hubungi administrator untuk mengaktifkannya.
                    </div>
                <?php endif; ?>
                <div class="form-group mb-0">
                    <label class="font-weight-bold">Link Form</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="share-link-input" readonly>
                        <div class="input-group-append">
                            <button type="button" class="btn btn-primary" id="btn-copy-share-link">
                                <i class="fas fa-copy mr-1"></i> Salin Link
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- DataTables & Plugins -->
<script src="../../plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="../../plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="../../plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="../../plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<!-- SweetAlert2 JS -->
<script src="../../plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
$(function () {
    const supportsPublicColumn = <?php echo $supportsPublicColumn ? 'true' : 'false'; ?>;
    const $shareModal = $('#shareFormModal');
    const $shareLinkInput = $('#share-link-input');
    const $shareIsPublic = $('#share-is-public');
    const $sharePublicSpinner = $('#share-public-spinner');
    const $sharePublicNote = $('#share-public-note');
    const $btnCopyShareLink = $('#btn-copy-share-link');
    let currentShareTemplateId = null;
    let currentShareButton = null;

    $("#table-templates").DataTable({
        responsive: true,
        autoWidth: false,
        lengthMenu: [[10,25,50],[10,25,50]],
        language: {
            processing: "Memproses...",
            lengthMenu: "Tampilkan _MENU_ data per halaman",
            zeroRecords: "Tidak ada data ditemukan",
            info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
            infoEmpty: "Tidak ada data tersedia",
            infoFiltered: "(disaring dari _MAX_ total data)",
            search: "Cari:",
            paginate: {
                first: "Pertama",
                last: "Terakhir",
                next: "Selanjutnya",
                previous: "Sebelumnya"
            }
        }
    });

    // Delete confirmation with SweetAlert2
    $(document).on('click', '.btn-delete', function(e) {
        e.preventDefault();
        var deleteUrl = $(this).attr('href');
        
        Swal.fire({
            title: 'Hapus Template?',
            text: "Template yang dihapus tidak dapat dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = deleteUrl;
            }
        });
    });

    function setSharePublicNote(type, message) {
        if (!supportsPublicColumn) {
            return;
        }
        const knownClasses = 'alert alert-success alert-warning alert-danger alert-info';
        if (!type || !message) {
            $sharePublicNote.addClass('d-none').removeClass(knownClasses).text('');
            return;
        }
        $sharePublicNote
            .removeClass('d-none alert-success alert-warning alert-danger alert-info alert-secondary')
            .addClass('alert alert-' + type)
            .html(message);
    }

    function showShareStatusMessage(isPublic) {
        setSharePublicNote(
            isPublic ? 'success' : 'warning',
            isPublic
                ? 'Form ini sudah <strong>publik</strong>. Siapa pun dengan link dapat mengisi tanpa login.'
                : 'Form ini <strong>tidak publik</strong>. Hanya pengguna login yang dapat mengisi.'
        );
    }

    function handleShareModalOpen(button) {
        const shareUrl = button.data('share-url') || '';
        const templateName = button.data('template-name') || 'Form';
        currentShareTemplateId = button.data('template-id') || null;
        currentShareButton = button;

        $('#share-modal-template-name').text(templateName);
        $shareLinkInput.val(shareUrl);
        $btnCopyShareLink.prop('disabled', !shareUrl);

        if (supportsPublicColumn) {
            const initialIsPublic = String(button.data('is-public')) === '1';
            const canManage = String(button.data('can-manage-public')) === '1';
            
            $shareIsPublic.prop('checked', initialIsPublic);
            $shareIsPublic.prop('disabled', !currentShareTemplateId || !canManage);

            if (!currentShareTemplateId) {
                setSharePublicNote('danger', 'ID form tidak ditemukan. Hubungi administrator.');
            } else if (!canManage) {
                setSharePublicNote('secondary', '<i class="fas fa-lock mr-2"></i>Hanya pembuat form atau Administrator yang dapat mengubah status publik.');
            } else {
                showShareStatusMessage(initialIsPublic);
            }
        }
    }

    function resetShareModalState() {
        currentShareTemplateId = null;
        currentShareButton = null;
        if (supportsPublicColumn) {
            $shareIsPublic.prop('disabled', false);
            $sharePublicSpinner.addClass('d-none');
            setSharePublicNote(null, null);
        }
    }

    $shareModal.on('hidden.bs.modal', resetShareModalState);

    $(document).on('click', '.btn-share', function() {
        handleShareModalOpen($(this));
        $shareModal.modal('show');
    });

    function showCopySuccess() {
        Swal.fire({
            icon: 'success',
            title: 'Link Tersalin',
            text: 'URL form sudah disalin ke clipboard.',
            timer: 2000,
            showConfirmButton: false
        });
    }

    function showManualCopy(url) {
        Swal.fire({
            icon: 'info',
            title: 'Salin Manual',
            html: '<p class="text-muted mb-2">Gunakan Ctrl + C untuk menyalin link berikut:</p>',
            input: 'text',
            inputValue: url,
            inputAttributes: {
                readonly: true,
                style: 'font-size:0.85rem;text-align:center;'
            },
            confirmButtonText: 'OK',
            didOpen: () => {
                const inputEl = Swal.getInput();
                if (inputEl) {
                    inputEl.focus();
                    inputEl.select();
                }
            }
        });
    }

    async function copyShareLink() {
        const inputEl = $shareLinkInput.get(0);
        if (!inputEl) { return; }
        const url = inputEl.value;
        if (!url) { return; }

        let copied = false;

        if (navigator.clipboard && window.isSecureContext) {
            try {
                await navigator.clipboard.writeText(url);
                copied = true;
            } catch (err) {
                copied = false;
            }
        }

        if (!copied) {
            const wasReadOnly = inputEl.hasAttribute('readonly');
            if (wasReadOnly) {
                inputEl.removeAttribute('readonly');
            }
            inputEl.focus();
            inputEl.select();
            inputEl.setSelectionRange(0, url.length);
            try {
                copied = document.execCommand('copy');
            } catch (err) {
                copied = false;
            }

            if (wasReadOnly) {
                inputEl.setAttribute('readonly', 'readonly');
            }
            inputEl.blur();
            if (document.getSelection) {
                const selection = document.getSelection();
                if (selection) {
                    selection.removeAllRanges();
                }
            }
        }

        if (copied) {
            showCopySuccess();
        } else {
            showManualCopy(url);
        }
    }

    $btnCopyShareLink.on('click', function() {
        copyShareLink();
    });

    if (supportsPublicColumn) {
        $shareIsPublic.on('change', function() {
            if (!currentShareTemplateId) {
                setSharePublicNote('danger', 'ID form tidak ditemukan. Status tidak dapat diperbarui.');
                $shareIsPublic.prop('checked', !$shareIsPublic.prop('checked'));
                return;
            }

            const newIsPublic = $shareIsPublic.is(':checked');
            $sharePublicSpinner.removeClass('d-none');
            $shareIsPublic.prop('disabled', true);
            setSharePublicNote('info', 'Menyimpan perubahan...');

            $.ajax({
                url: 'update_public_status.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    template_id: currentShareTemplateId,
                    is_public: newIsPublic ? 1 : 0
                }
            }).done(function(response) {
                if (response && response.success) {
                    const updatedIsPublic = String(response.is_public) === '1';
                    $shareIsPublic.prop('checked', updatedIsPublic);
                    showShareStatusMessage(updatedIsPublic);
                    if (currentShareButton) {
                        currentShareButton.data('is-public', updatedIsPublic ? '1' : '0');
                    }
                } else {
                    $shareIsPublic.prop('checked', !newIsPublic);
                    setSharePublicNote('danger', (response && response.message) ? response.message : 'Gagal memperbarui status publik.');
                }
            }).fail(function() {
                $shareIsPublic.prop('checked', !newIsPublic);
                setSharePublicNote('danger', 'Terjadi kesalahan server saat menyimpan status publik.');
            }).always(function() {
                $sharePublicSpinner.addClass('d-none');
                $shareIsPublic.prop('disabled', false);
            });
        });
    }
    
    // Show success message with SweetAlert2
    <?php if ($successMessage): ?>
    Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: '<?php echo addslashes($successMessage); ?>',
        confirmButtonColor: '#28a745',
        timer: 3000,
        timerProgressBar: true
    });
    <?php endif; ?>
});
</script>
