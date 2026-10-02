<?php
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

function checkAddPermission($conn, $groupId, $menuId) {
    $sql = "SELECT CanAdd FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    $result = ['CanAdd' => 0];
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $result = $row;
    if ($stmt !== false) sqlsrv_free_stmt($stmt);
    return $result;
}

function generatePadderId($conn) {
    $sql = "SELECT MAX(CAST(SUBSTRING(padder_id, 4, LEN(padder_id)) AS INT)) as max_id 
            FROM pad_m_padder WHERE padder_id LIKE 'PD-%'";
    $stmt = sqlsrv_query($conn, $sql);
    $maxId = 0;
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $maxId = $row['max_id'] ?? 0;
    if ($stmt !== false) sqlsrv_free_stmt($stmt);
    return 'PD-' . str_pad($maxId + 1, 4, '0', STR_PAD_LEFT);
}

// Cek permission
$permissions = checkAddPermission($conn, $_SESSION['GroupId'], 111);
if ($permissions['CanAdd'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menambah data padder.";
    header('Location: master_padder.php');
    exit;
}

$padderId = generatePadderId($conn);

// ===== Form Submission =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $padderName = trim($_POST['padder_name'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        $createdBy = $_SESSION['UserName'];

        if (!$padderName) throw new Exception("Nama padder harus diisi!");

        $sql = "INSERT INTO pad_m_padder (padder_id, padder_name, status, remarks, created_by, created_at)
                VALUES (?, ?,'DIBUAT', ?, ?, GETDATE())";
        $stmt = sqlsrv_query($conn, $sql, [$padderId, $padderName, $remarks, $createdBy]);
        if ($stmt === false) throw new Exception(sqlsrv_errors()[0]['message']);

        if (!empty($_POST['spec_name'])) {
            foreach ($_POST['spec_name'] as $i => $name) {
                $value = $_POST['spec_value'][$i] ?? '';
                if ($name && $value) {
                    sqlsrv_query($conn, "INSERT INTO pad_m_padder_spec (padder_id, spec_name, spec_value) VALUES (?, ?, ?)", [$padderId, $name, $value]);
                }
            }
        }

        sqlsrv_query($conn, "INSERT INTO pad_status_log (padder_id, status, changed_by, remarks) VALUES (?, 'DIBUAT', ?, 'Registrasi padder baru')", [$padderId, $createdBy]);

        $_SESSION['success'] = "Padder berhasil ditambahkan: $padderId";
        header('Location: master_padder.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
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
                    <h1 class="m-0">Tambah Padder</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="master_padder.php">Master Padder</a></li>
                        <li class="breadcrumb-item active">Tambah</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card shadow-sm">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title">Form Tambah Padder</h3>
                </div>
                <div class="card-body">
                    <form method="POST" id="padderForm">
                        <div class="mb-3">
                            <label for="padder_id" class="form-label">Padder ID</label>
                            <input type="text" class="form-control" id="padder_id" value="<?= $padderId ?>" readonly>
                            <small class="text-muted">ID otomatis</small>
                        </div>

                        <div class="mb-3">
                            <label for="padder_name" class="form-label">Nama Padder <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="padder_name" name="padder_name" placeholder="Masukkan nama padder..." required maxlength="100">
                        </div>

                        <div class="mb-3">
                            <label for="remarks" class="form-label">Keterangan</label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="3" maxlength="255" placeholder="Keterangan tambahan..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Spesifikasi Padder</label>
                            <div id="specifications"></div>
                            <button type="button" class="btn btn-sm btn-success mt-2" id="addSpecBtn">
                                <i class="fas fa-plus"></i> Tambah Spesifikasi
                            </button>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>"><i class="fas fa-save"></i> Simpan</button>
                            <a href="master_padder.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/template" id="specTemplate">
    <div class="d-flex mb-2 spec-row">
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
    function addSpecRow() { $specContainer.append($('#specTemplate').html()); }
    $('#addSpecBtn').on('click', addSpecRow);
    $(document).on('click', '.remove-spec', function() { $(this).closest('.spec-row').remove(); });

    $('#padderForm').on('submit', function(e) {
        const padderName = $('#padder_name').val().trim();
        if(!padderName) { e.preventDefault(); Swal.fire({icon:'warning',title:'Perhatian',text:'Nama padder harus diisi!',confirmButtonColor:'#007bff'}); return; }

        const specNames = $('input[name="spec_name[]"]'), specValues = $('input[name="spec_value[]"]');
        for(let i=0;i<specNames.length;i++){
            if((specNames[i].value && !specValues[i].value) || (!specNames[i].value && specValues[i].value)){
                e.preventDefault();
                Swal.fire({icon:'warning',title:'Perhatian',text:'Nama dan nilai spesifikasi harus diisi keduanya!',confirmButtonColor:'#007bff'});
                return;
            }
        }

        e.preventDefault();
        Swal.fire({
            title:'Simpan Padder?',
            text:'Data padder baru akan disimpan ke sistem',
            icon:'question',
            showCancelButton:true,
            confirmButtonColor:'#007bff',
            cancelButtonColor:'#6c757d',
            confirmButtonText:'Ya, Simpan!',
            cancelButtonText:'Batal'
        }).then((result)=>{ if(result.isConfirmed) $('#padderForm').off('submit').submit(); });
    });

    $('#padder_name').focus();
    addSpecRow();
})();
</script>

<?php include '../../includes/footer.php'; ?>
