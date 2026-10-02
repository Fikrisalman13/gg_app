<?php
// Start session and output buffering
session_start();
ob_start();

// Set default timezone
date_default_timezone_set('Asia/Jakarta');

// Include necessary files
require_once '../../koneksi.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Check if user is logged in
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';

// Check database connection
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

// Authorization check
$groupId = $_SESSION['GroupId'];
$menuId = 56; // Menu ID untuk halaman Asset

// Check if user has CanAdd permission
$sql = "SELECT CanAdd FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die("Kesalahan saat mengambil hak akses: " . print_r(sqlsrv_errors(), true));
}

$canAdd = false;
if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $canAdd = $row['CanAdd'] == 1;
}
sqlsrv_free_stmt($stmt);

// Redirect if user doesn't have permission
if (!$canAdd) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menambah data.";
    header('Location: asset.php');
    exit;
}

/**
 * Generate auto code for asset
 * 
 * @param resource $conn Database connection
 * @param int $idKode Kode ID
 * @return string Generated code
 */
function generateAutoCode($conn, $idKode) {
    // Ambil prefix kode dari m_kode_asset
    $sql = "SELECT kode_asset FROM dbo.m_kode_asset WHERE id_kode = ?";
    $params = [$idKode];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false || !($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
        return '';
    }

    $prefix = $row['kode_asset'];

    // Ambil semua nomor urut yang sudah dipakai untuk prefix ini
    $sql = "SELECT CAST(SUBSTRING(kode_asset_seq, LEN(?) + 2, 3) AS INT) AS nomor 
            FROM dbo.m_asset 
            WHERE id_kode = ? AND kode_asset_seq LIKE ? + '-%' 
            ORDER BY nomor";
    $params = [$prefix, $idKode, $prefix];
    $stmt = sqlsrv_query($conn, $sql, $params);

    $usedNumbers = [];
    while ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if (is_numeric($row['nomor'])) {
            $usedNumbers[] = (int)$row['nomor'];
        }
    }

    // Cari nomor terkecil yang belum dipakai
    $nextNumber = 1;
    while (in_array($nextNumber, $usedNumbers)) {
        $nextNumber++;
    }

    return $prefix . '-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
}

// Initialize variables
$kodeAssets = $kategori = $merks = $tipes = $lokasi = $status = $karyawan = [];
$previewCode = 'LP-001'; // Default value
$errorMessages = [];

// Fetch dropdown data
$dropdownQueries = [
    'kodeAssets' => "SELECT id_kode, kode_asset FROM dbo.m_kode_asset ORDER BY kode_asset",
    'kategori' => "SELECT id_kategori, nama_kategori FROM dbo.m_kategori ORDER BY nama_kategori",
    'merks' => "SELECT id_merk, nama_merk FROM dbo.m_merk ORDER BY nama_merk",
    'lokasi' => "SELECT id_lokasi, nama_lokasi FROM dbo.m_lokasi ORDER BY nama_lokasi",
    'status' => "SELECT id_status, nama_status FROM dbo.m_status ORDER BY nama_status",
    'karyawan' => "SELECT id_emp, nama_lengkap FROM dbo.m_emp ORDER BY nama_lengkap"
];

foreach ($dropdownQueries as $var => $query) {
    $stmt = sqlsrv_query($conn, $query);
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            ${$var}[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $id_kode = filter_input(INPUT_POST, 'id_kode', FILTER_VALIDATE_INT);
    $id_kategori = filter_input(INPUT_POST, 'id_kategori', FILTER_VALIDATE_INT);
    $id_merk = filter_input(INPUT_POST, 'id_merk', FILTER_VALIDATE_INT);
    $id_tipe = filter_input(INPUT_POST, 'id_tipe', FILTER_VALIDATE_INT);
    $id_lokasi = filter_input(INPUT_POST, 'id_lokasi', FILTER_VALIDATE_INT);
    $id_status = filter_input(INPUT_POST, 'id_status', FILTER_VALIDATE_INT);
    $id_emp = filter_input(INPUT_POST, 'id_emp', FILTER_VALIDATE_INT) ?: null;
    
    $serial_number = trim(filter_input(INPUT_POST, 'serial_number', FILTER_SANITIZE_STRING));
    $tanggal_pembelian = trim(filter_input(INPUT_POST, 'tanggal_pembelian', FILTER_SANITIZE_STRING));
    $no_po = trim(filter_input(INPUT_POST, 'no_po', FILTER_SANITIZE_STRING));
    $keterangan = trim(filter_input(INPUT_POST, 'keterangan', FILTER_SANITIZE_STRING));
    
    $kode_asset_seq = generateAutoCode($conn, $id_kode);
    $upduser = $_SESSION['UserName'];

    // Validate required fields
    if (empty($id_kode)) $errorMessages[] = "Jenis Asset harus dipilih";
    if (empty($id_kategori)) $errorMessages[] = "Kategori harus dipilih";
    if (empty($id_merk)) $errorMessages[] = "Merk harus dipilih";
    if (empty($id_tipe)) $errorMessages[] = "Tipe harus dipilih";
    if (empty($id_lokasi)) $errorMessages[] = "Lokasi harus dipilih";
    if (empty($id_status)) $errorMessages[] = "Status harus dipilih";
    if (empty($kode_asset_seq)) $errorMessages[] = "Kode asset tidak boleh kosong";
    if (empty($serial_number)) $errorMessages[] = "Serial number tidak boleh kosong";

    // Validate date format if provided
    if (!empty($tanggal_pembelian) && !DateTime::createFromFormat('d-m-Y', $tanggal_pembelian)) {
        $errorMessages[] = "Format tanggal pembelian tidak valid (harus dd-mm-yyyy)";
    }

    if (empty($errorMessages)) {
        // Convert date to SQL Server format
        $tanggal_pembelian_sql = !empty($tanggal_pembelian) 
            ? DateTime::createFromFormat('d-m-Y', $tanggal_pembelian)->format('Y-m-d') 
            : null;

        $sql = "INSERT INTO dbo.m_asset (
                    id_kode, id_kategori, id_merk, id_tipe, id_lokasi, 
                    id_status, id_emp, kode_asset_seq, serial_number, 
                    tanggal_pembelian, no_po, keterangan, upddate, upduser
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), ?)";
        
        $params = [
            $id_kode, $id_kategori, $id_merk, $id_tipe, $id_lokasi,
            $id_status, $id_emp, $kode_asset_seq, $serial_number,
            $tanggal_pembelian_sql, $no_po, $keterangan, $upduser
        ];
        
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            $_SESSION['error'] = "Gagal menambahkan asset: " . print_r(sqlsrv_errors(), true);
        } else {
            $_SESSION['success'] = "Asset berhasil ditambahkan.";
            header('Location: asset.php');
            exit;
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errorMessages);
    }
} elseif (!empty($kodeAssets)) {
    // Generate preview code for initial view
    $previewCode = generateAutoCode($conn, $kodeAssets[0]['id_kode']);
}
?>


    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1>Tambah Asset</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                            <li class="breadcrumb-item"><a href="asset.php">Asset</a></li>
                            <li class="breadcrumb-item active">Tambah Asset</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-12">                    
                        <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                                <h3 class="card-title">Form Tambah Asset</h3>
                            </div>
                            
                            <form method="POST" autocomplete="off">
                                <div class="card-body">
                                    <?php if (isset($_SESSION['error'])): ?>
                                        <div class="alert alert-danger">
                                            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="id_kode">Jenis Asset <span class="text-danger">*</span></label>
                                                <select class="form-control" id="id_kode" name="id_kode" required>
                                                    <option value="">-- Pilih Jenis Asset --</option>
                                                    <?php foreach ($kodeAssets as $kode): ?>
                                                        <option value="<?= $kode['id_kode'] ?>" 
                                                            data-kode="<?= htmlspecialchars($kode['kode_asset']) ?>"
                                                            <?= (isset($_POST['id_kode']) && $_POST['id_kode'] == $kode['id_kode']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($kode['kode_asset']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <small class="text-muted">Kode otomatis: <span id="auto-code"><?= htmlspecialchars($previewCode) ?></span></small>
                                                <input type="hidden" name="kode_asset_seq" id="kode_asset_seq" value="<?= htmlspecialchars($previewCode) ?>">
                                            </div>

                                            <div class="form-group">
                                                <label for="id_kategori">Kategori <span class="text-danger">*</span></label>
                                                <select class="form-control" id="id_kategori" name="id_kategori" required>
                                                    <option value="">-- Pilih Kategori --</option>
                                                    <?php foreach ($kategori as $kat): ?>
                                                        <option value="<?= $kat['id_kategori'] ?>"
                                                            <?= (isset($_POST['id_kategori']) && $_POST['id_kategori'] == $kat['id_kategori']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($kat['nama_kategori']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="form-group">
                                                <label for="id_merk">Merk <span class="text-danger">*</span></label>
                                                <select class="form-control" id="id_merk" name="id_merk" required>
                                                    <option value="">-- Pilih Merk --</option>
                                                    <?php foreach ($merks as $merk): ?>
                                                        <option value="<?= $merk['id_merk'] ?>" 
                                                            <?= (isset($_POST['id_merk']) && $_POST['id_merk'] == $merk['id_merk']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($merk['nama_merk']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="form-group">
                                                <label for="id_tipe">Tipe <span class="text-danger">*</span></label>
                                                <select class="form-control" id="id_tipe" name="id_tipe" required>
                                                    <option value="">-- Pilih Merk terlebih dahulu --</option>
                                                    <?php if (isset($_POST['id_tipe'])): ?>
                                                        <option value="<?= $_POST['id_tipe'] ?>" selected>
                                                            <?= htmlspecialchars($_POST['id_tipe']) ?>
                                                        </option>
                                                    <?php endif; ?>
                                                </select>
                                            </div>

                                            <div class="form-group">
                                                <label for="serial_number">Serial Number <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="serial_number" name="serial_number" 
                                                       placeholder="Masukkan serial number" required maxlength="100"
                                                       value="<?= isset($_POST['serial_number']) ? htmlspecialchars($_POST['serial_number']) : '' ?>">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="id_lokasi">Lokasi <span class="text-danger">*</span></label>
                                                <select class="form-control" id="id_lokasi" name="id_lokasi" required>
                                                    <option value="">-- Pilih Lokasi --</option>
                                                    <?php foreach ($lokasi as $lok): ?>
                                                        <option value="<?= $lok['id_lokasi'] ?>" 
                                                            <?= (isset($_POST['id_lokasi']) && $_POST['id_lokasi'] == $lok['id_lokasi']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($lok['nama_lokasi']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="form-group">
                                                <label for="id_status">Status <span class="text-danger">*</span></label>
                                                <select class="form-control" id="id_status" name="id_status" required>
                                                    <option value="">-- Pilih Status --</option>
                                                    <?php foreach ($status as $stat): ?>
                                                        <option value="<?= $stat['id_status'] ?>" 
                                                            <?= (isset($_POST['id_status']) && $_POST['id_status'] == $stat['id_status']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($stat['nama_status']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="form-group">
                                                <label for="id_emp">Pegawai</label>
                                                <select class="form-control select2" id="id_emp" name="id_emp">
                                                    <option value="">-- Pilih Pegawai --</option>
                                                    <?php foreach ($karyawan as $emp): ?>
                                                        <option value="<?= $emp['id_emp'] ?>" 
                                                            <?= (isset($_POST['id_emp']) && $_POST['id_emp'] == $emp['id_emp']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($emp['nama_lengkap']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="form-group">
                                                <label for="tanggal_pembelian">Tanggal Pembelian</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control datepicker" id="tanggal_pembelian" 
                                                        name="tanggal_pembelian" placeholder="dd-mm-yyyy"
                                                        value="<?= isset($_POST['tanggal_pembelian']) ? htmlspecialchars($_POST['tanggal_pembelian']) : '' ?>">
                                                    <div class="input-group-append">
                                                        <span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label for="no_po">No PO</label>
                                                <input type="text" class="form-control" id="no_po" name="no_po" 
                                                       placeholder="Masukkan nomor PO" maxlength="50"
                                                       value="<?= isset($_POST['no_po']) ? htmlspecialchars($_POST['no_po']) : '' ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="keterangan">Keterangan</label>
                                        <textarea class="form-control" id="keterangan" name="keterangan" 
                                                  rows="3" placeholder="Masukkan keterangan tambahan"><?= isset($_POST['keterangan']) ? htmlspecialchars($_POST['keterangan']) : '' ?></textarea>
                                    </div>
                                </div>

                                <div class="card-footer">
                                    <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?>"><i class="fas fa-save"></i> Simpan</button>
                                    <a href="asset.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>


</div>
<?php include '../../includes/footer.php'; ?>

<script src="/gg_app/plugins/js/bootstrap-datepicker.min.js"></script>
<script src="/gg_app/plugins/js/bootstrap-datepicker.id.min.js"></script>


<script>
$(document).ready(function() {
    // Initialize datepicker
    $('.datepicker').datepicker({
        format: 'dd-mm-yyyy',
        language: 'id',
        autoclose: true,
        todayHighlight: true,
        orientation: "bottom auto"
    });

    // Auto-generate code when asset type is selected
    $('#id_kode').change(function() {
        var idKode = $(this).val();
        if (idKode) {
            $.ajax({
                url: 'generate_asset_code.php',
                type: 'POST',
                data: {id_kode: idKode},
                success: function(response) {
                    $('#auto-code').text(response);
                    $('#kode_asset_seq').val(response);
                    
                    // Auto-fill kategori based on selected kode
                    var selectedKode = $('#id_kode option:selected').data('kode');
                    if (selectedKode) {
                        $.ajax({
                            url: 'get_kategori_by_kode.php',
                            type: 'POST',
                            data: {kode: selectedKode},
                            success: function(response) {
                                $('#id_kategori').html(response);
                            },
                            error: function() {
                                console.error('Failed to load categories');
                            }
                        });
                    }
                },
                error: function() {
                    console.error('Failed to generate asset code');
                }
            });
        } else {
            $('#auto-code').text('');
            $('#kode_asset_seq').val('');
        }
    });

    // Load types based on selected brand
    $('#id_merk').change(function() {
        var idMerk = $(this).val();
        if (idMerk) {
            $.ajax({
                url: 'get_tipe_by_merk.php',
                type: 'POST',
                data: {id_merk: idMerk},
                success: function(response) {
                    $('#id_tipe').html(response);
                },
                error: function() {
                    $('#id_tipe').html('<option value="">Gagal memuat tipe</option>');
                    console.error('Failed to load types');
                }
            });
        } else {
            $('#id_tipe').html('<option value="">-- Pilih Merk terlebih dahulu --</option>');
        }
    });

    // Preserve form data on validation error
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        $('#id_kode').trigger('change');
        $('#id_merk').trigger('change');
    <?php endif; ?>
});
</script>
<script>
    $(document).ready(function() {
        $('.select2').select2({
            theme: 'bootstrap',
            width: '100%',
            placeholder: "Pilih Karyawan",
            allowClear: true
        });
    });
</script>

<?php
ob_end_flush();
?>