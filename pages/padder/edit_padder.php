<?php
// edit_padder.php
ob_start();
session_start();

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';

date_default_timezone_set('Asia/Jakarta');

// ===== Auth & Permission =====
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit();
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

// Cek permission edit
function checkEditPermission($conn, $groupId, $menuId) {
    $sql = "SELECT CanEdit FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    $result = ['CanEdit' => 0];
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $result = $row;
    if ($stmt !== false) sqlsrv_free_stmt($stmt);
    return $result;
}

$permissions = checkEditPermission($conn, $_SESSION['GroupId'], 111);
if ($permissions['CanEdit'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit data padder.";
    header('Location: master_padder.php');
    exit;
}

// Ambil padder ID
$padderId = $_GET['id'] ?? '';
if (!$padderId) {
    $_SESSION['error'] = "Padder ID tidak valid!";
    header('Location: master_padder.php');
    exit;
}

// Fetch padder
$sqlPadder = "SELECT * FROM pad_m_padder WHERE padder_id = ?";
$stmtPadder = sqlsrv_query($conn, $sqlPadder, [$padderId]);
$padder = sqlsrv_fetch_array($stmtPadder, SQLSRV_FETCH_ASSOC);
if (!$padder) {
    $_SESSION['error'] = "Padder tidak ditemukan!";
    header('Location: master_padder.php');
    exit;
}

// Fetch spesifikasi
$sqlSpec = "SELECT * FROM pad_m_padder_spec WHERE padder_id = ? ORDER BY id";
$stmtSpec = sqlsrv_query($conn, $sqlSpec, [$padderId]);
$specs = [];
while ($row = sqlsrv_fetch_array($stmtSpec, SQLSRV_FETCH_ASSOC)) {
    $specs[] = $row;
}

// ===== Form Submission =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $padderName = trim($_POST['padder_name'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        $updatedBy = $_SESSION['UserName'];

        if (!$padderName) throw new Exception("Nama padder harus diisi!");

        // Begin transaction
        sqlsrv_begin_transaction($conn);

        // Update padder
        $sqlUpdate = "UPDATE pad_m_padder SET padder_name=?, remarks=?, updated_by=?, updated_at=GETDATE() WHERE padder_id=?";
        $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, [$padderName, $remarks, $updatedBy, $padderId]);
        if ($stmtUpdate === false) throw new Exception("Gagal update padder");

        // Handle spesifikasi
        $existingSpecIds = [];
        $newSpecs = [];
        if (!empty($_POST['spec_id']) && is_array($_POST['spec_id'])) {
            foreach ($_POST['spec_id'] as $i => $specId) {
                $name = trim($_POST['spec_name'][$i] ?? '');
                $value = trim($_POST['spec_value'][$i] ?? '');
                if ($name && $value) {
                    if ($specId != 'new') {
                        $sqlUpdSpec = "UPDATE pad_m_padder_spec SET spec_name=?, spec_value=? WHERE id=? AND padder_id=?";
                        $stmtUpdSpec = sqlsrv_query($conn, $sqlUpdSpec, [$name, $value, $specId, $padderId]);
                        if ($stmtUpdSpec === false) throw new Exception("Gagal update spesifikasi");
                        $existingSpecIds[] = $specId;
                    } else {
                        $newSpecs[] = ['name' => $name, 'value' => $value];
                    }
                }
            }
        }

        // Delete spesifikasi yang dihapus
        $sqlDeleteSpec = "DELETE FROM pad_m_padder_spec WHERE padder_id=? " . 
                         (empty($existingSpecIds) ? "" : "AND id NOT IN (" . implode(',', array_map('intval', $existingSpecIds)) . ")");
        sqlsrv_query($conn, $sqlDeleteSpec, [$padderId]);

        // Insert spesifikasi baru
        foreach ($newSpecs as $s) {
            sqlsrv_query($conn, "INSERT INTO pad_m_padder_spec (padder_id, spec_name, spec_value) VALUES (?, ?, ?)",
                [$padderId, $s['name'], $s['value']]);
        }

        // Log update
        sqlsrv_query($conn, "INSERT INTO pad_status_log (padder_id, status, changed_by, remarks) VALUES (?, ?, ?, ?)",
            [$padderId, $padder['status'], $updatedBy, 'Update data padder']);

        // Commit
        sqlsrv_commit($conn);

        $_SESSION['success'] = "Padder berhasil diupdate: $padderId";
        header("Location: master_padder.php?id=$padderId");
        exit;

    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        $_SESSION['error'] = $e->getMessage();

        // Reload data dari POST agar form tidak kosong
        $padder['padder_name'] = $_POST['padder_name'] ?? $padder['padder_name'];
        $padder['remarks'] = $_POST['remarks'] ?? $padder['remarks'];
        $specs = [];
        if (!empty($_POST['spec_name']) && is_array($_POST['spec_name'])) {
            foreach ($_POST['spec_name'] as $i => $name) {
                $specs[] = [
                    'id' => $_POST['spec_id'][$i] ?? 'new',
                    'spec_name' => $name,
                    'spec_value' => $_POST['spec_value'][$i] ?? ''
                ];
            }
        }
    }
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Edit Padder - <?= htmlspecialchars($padder['padder_id']) ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="master_padder.php">Master Padder</a></li>
                        <li class="breadcrumb-item active">Edit</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card shadow-sm">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title">Form Edit Padder</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
                    <?php endif; ?>
                    <form method="POST" id="padderForm">
                        <div class="mb-3">
                            <label for="padder_id" class="form-label">Padder ID</label>
                            <input type="text" class="form-control" id="padder_id" value="<?= htmlspecialchars($padder['padder_id']) ?>" readonly>
                        </div>

                        <div class="mb-3">
                            <label for="padder_name" class="form-label">Nama Padder <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="padder_name" name="padder_name" 
                                value="<?= htmlspecialchars($padder['padder_name']) ?>" required maxlength="100">
                        </div>

                        <div class="mb-3">
                            <label for="remarks" class="form-label">Keterangan</label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="3" maxlength="255"><?= htmlspecialchars($padder['remarks'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Spesifikasi Padder</label>
                            <div id="specifications">
                                <?php if (!empty($specs)): ?>
                                    <?php foreach ($specs as $spec): ?>
                                        <div class="d-flex mb-2 spec-row">
                                            <input type="hidden" name="spec_id[]" value="<?= htmlspecialchars($spec['id']) ?>">
                                            <input type="text" class="form-control me-2" name="spec_name[]" value="<?= htmlspecialchars($spec['spec_name']) ?>" placeholder="Nama spesifikasi" required>
                                            <input type="text" class="form-control me-2" name="spec_value[]" value="<?= htmlspecialchars($spec['spec_value']) ?>" placeholder="Nilai spesifikasi" required>
                                            <button type="button" class="btn btn-danger btn-sm remove-spec"><i class="fas fa-trash"></i></button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="d-flex mb-2 spec-row">
                                        <input type="hidden" name="spec_id[]" value="new">
                                        <input type="text" class="form-control me-2" name="spec_name[]" placeholder="Nama spesifikasi" required>
                                        <input type="text" class="form-control me-2" name="spec_value[]" placeholder="Nilai spesifikasi" required>
                                        <button type="button" class="btn btn-danger btn-sm remove-spec"><i class="fas fa-trash"></i></button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="btn btn-success btn-sm mt-2" id="addSpecBtn"><i class="fas fa-plus"></i> Tambah Spesifikasi</button>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>"><i class="fas fa-save"></i> Simpan</button>
                            <a href="master_padder.php?id=<?= $padderId ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/template" id="specTemplate">
    <div class="d-flex mb-2 spec-row">
        <input type="hidden" name="spec_id[]" value="new">
        <input type="text" class="form-control me-2" name="spec_name[]" placeholder="Nama spesifikasi" required>
        <input type="text" class="form-control me-2" name="spec_value[]" placeholder="Nilai spesifikasi" required>
        <button type="button" class="btn btn-danger btn-sm remove-spec"><i class="fas fa-trash"></i></button>
    </div>
</script>

<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
(() => {
    const $specContainer = $('#specifications');
    const addSpecRow = () => $specContainer.append($('#specTemplate').html());

    $('#addSpecBtn').on('click', addSpecRow);
    $(document).on('click', '.remove-spec', function(){ $(this).closest('.spec-row').remove(); });

    $('#padderForm').on('submit', function(e){
        const padderName = $('#padder_name').val().trim();
        if(!padderName){ e.preventDefault(); Swal.fire({icon:'warning',title:'Perhatian',text:'Nama padder harus diisi!',confirmButtonColor:'#007bff'}); return; }

        const specNames = $('input[name="spec_name[]"]');
        const specValues = $('input[name="spec_value[]"]');
        for(let i=0;i<specNames.length;i++){
            if((specNames[i].value && !specValues[i].value) || (!specNames[i].value && specValues[i].value)){
                e.preventDefault();
                Swal.fire({icon:'warning',title:'Perhatian',text:'Nama dan nilai spesifikasi harus diisi keduanya!',confirmButtonColor:'#007bff'});
                return;
            }
        }

        e.preventDefault();
        Swal.fire({
            title:'Update Padder?',
            text:'Data padder akan diperbarui',
            icon:'question',
            showCancelButton:true,
            confirmButtonColor:'#007bff',
            cancelButtonColor:'#6c757d',
            confirmButtonText:'Ya, Update!',
            cancelButtonText:'Batal'
        }).then((result)=>{ if(result.isConfirmed) $('#padderForm').off('submit').submit(); });
    });

    $('#padder_name').focus();
})();
</script>

<?php include '../../includes/footer.php'; ?>
