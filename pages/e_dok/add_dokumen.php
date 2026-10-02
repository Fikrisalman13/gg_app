<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// Check if user is logged in
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: ../login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';

// Check database connection
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

date_default_timezone_set('Asia/Jakarta');

// Check permissions (assuming MenuId for e-dokumen is 71)
$groupId = $_SESSION['GroupId'];
$menuId = 71;

$sql = "SELECT CanView, CanAdd, CanEdit, CanDelete 
        FROM dbo.SMGroupTrustee 
        WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

$permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $permissions = $row;
}
sqlsrv_free_stmt($stmt);

if ($permissions['CanAdd'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menambah dokumen.";
    header('Location: e_dokumen.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Validate required fields
        $requiredFields = [
            'id_kode_dok', 'id_kategori', 'nama_dokumen', 'revisi', 'tanggal_upload', 'format_kode'
        ];
        
        // Jika format lengkap, tambahkan field departemen dan subbagian
        $format_kode = $_POST['format_kode'] ?? 'lengkap';
        if ($format_kode === 'lengkap') {
            $requiredFields[] = 'id_dept';
            $requiredFields[] = 'id_subbag';
        }
        
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field]) && $_POST[$field] !== '0') {
                throw new Exception("Field $field harus diisi!");
            }
        }
        
        // Validasi format revisi - harus 2 digit angka (00–99)
        if (!preg_match('/^[0-9]{2}$/', $_POST['revisi'])) {
            throw new Exception("Format revisi harus berupa 2 digit angka (00-99)");
        }

        // Pastikan revisi selalu tersimpan 2 digit (misalnya input 5 → simpan "05")
        $revisi = str_pad($_POST['revisi'], 2, '0', STR_PAD_LEFT);

        
        // Handle file upload
        $file_pdf = '';
        if (isset($_FILES['file_pdf'])) {
            $file = $_FILES['file_pdf'];
            
            if ($file['error'] == UPLOAD_ERR_OK) {
                // Validate file type
                $allowedTypes = ['application/pdf'];
                $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
                $detectedType = finfo_file($fileInfo, $file['tmp_name']);
                finfo_close($fileInfo);
                
                if (!in_array($detectedType, $allowedTypes)) {
                    throw new Exception("Hanya file PDF yang diizinkan!");
                }
                
                // Generate unique filename
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = uniqid('doc_') . '.' . $ext;
                $uploadDir = '../../uploads/dokumen/';
                
                // Create directory if not exists
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $destination = $uploadDir . $filename;
                
                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    throw new Exception("Gagal mengupload file!");
                }
                
                $file_pdf = '/gg_app/uploads/dokumen/' . $filename;
            } elseif ($file['error'] != UPLOAD_ERR_NO_FILE) {
                throw new Exception("Error uploading file: " . $file['error']);
            }
        } else {
            throw new Exception("File PDF harus diupload!");
        }
        
        // Get sequence number for kode_dok_seq
        $format_kode = $_POST['format_kode'];
        if ($format_kode === 'simple') {
            $sequenceNumber = getNextSequenceNumber($conn, $_POST['id_kode_dok'], null, null, 'simple');
        } else {
            $sequenceNumber = getNextSequenceNumber($conn, $_POST['id_kode_dok'], $_POST['id_dept'], $_POST['id_subbag'], 'lengkap');
        }
        
        // Format kode dokumen
        $kode_dokumen = generateKodeDokumen(
            $conn, 
            $_POST['id_kode_dok'], 
            $_POST['id_dept'] ?? null, 
            $_POST['id_subbag'] ?? null, 
            $sequenceNumber,
            $format_kode
        );
        
        // Simpan tanggal upload dengan format lengkap (termasuk jam, menit, detik)
        $tanggal_upload = !empty($_POST['tanggal_upload']) ? date('Y-m-d H:i:s', strtotime($_POST['tanggal_upload'])) : NULL;
        
        // Tanggal terbit dan revisi
        $tanggal_terbit = !empty($_POST['tanggal_terbit']) ? date('Y-m-d', strtotime($_POST['tanggal_terbit'])) : NULL;
        $tanggal_revisi = !empty($_POST['tanggal_revisi']) ? date('Y-m-d', strtotime($_POST['tanggal_revisi'])) : NULL;
        
        // Insert data into database
        $sql = "INSERT INTO dbo.dokumen (
            id_kode_dok, id_kategori, id_dept, id_bag, id_subbag,
            kode_dok_seq, nama_dokumen, revisi, tanggal_upload, 
            tanggal_terbit, tanggal_revisi, file_pdf, deskripsi,
            upddate, upduser, format_kode
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $_POST['id_kode_dok'],
            $_POST['id_kategori'],
            $format_kode === 'simple' ? null : $_POST['id_dept'],
            $format_kode === 'simple' ? null : $_POST['id_bag'],
            $format_kode === 'simple' ? null : $_POST['id_subbag'],
            $kode_dokumen,
            $_POST['nama_dokumen'],
            $revisi,
            $tanggal_upload,
            $tanggal_terbit,
            $tanggal_revisi,
            $file_pdf,
            $_POST['deskripsi'],
            date('Y-m-d H:i:s'),
            $_SESSION['UserName'],
            $format_kode
        ];

        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt === false) {
            throw new Exception("Gagal menyimpan data: " . print_r(sqlsrv_errors(), true));
        }
        
        $_SESSION['success'] = "Dokumen berhasil ditambahkan!";
        header('Location: e_dokumen.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

/**
 * Get next sequence number for kode_dok_seq berdasarkan format
 */
function getNextSequenceNumber($conn, $id_kode_dok, $id_dept, $id_subbag, $format = 'lengkap') {
    if ($format === 'simple') {
        $sql = "SELECT CAST(RIGHT(kode_dok_seq, 3) AS INT) AS seq
                FROM dbo.dokumen
                WHERE id_kode_dok = ? AND format_kode = 'simple'
                ORDER BY seq ASC";
        $params = [$id_kode_dok];
    } else {
        $sql = "SELECT CAST(RIGHT(kode_dok_seq, 3) AS INT) AS seq
                FROM dbo.dokumen
                WHERE id_kode_dok = ? AND id_dept = ? AND id_subbag = ? AND format_kode = 'lengkap'
                ORDER BY seq ASC";
        $params = [$id_kode_dok, $id_dept, $id_subbag];
    }
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    $usedSeq = [];
    
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            if (!empty($row['seq'])) {
                $usedSeq[] = (int)$row['seq'];
            }
        }
    }
    sqlsrv_free_stmt($stmt);

    // Cari nomor bolong terkecil
    $nextSeq = 1;
    if (!empty($usedSeq)) {
        sort($usedSeq);
        $expected = 1;
        foreach ($usedSeq as $seq) {
            if ($seq != $expected) {
                $nextSeq = $expected;
                return $nextSeq;
            }
            $expected++;
        }
        $nextSeq = end($usedSeq) + 1;
    }
    return $nextSeq;
}

/**
 * Generate kode dokumen berdasarkan format yang dipilih
 */
function generateKodeDokumen($conn, $id_kode_dok, $id_dept, $id_subbag, $sequenceNumber, $format = 'lengkap') {
    // Static prefix
    $prefix = "SUM";
    
    // Get kode dokumen
    $sql = "SELECT kode_dok FROM dbo.m_kode_dok WHERE id_kode_dok = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id_kode_dok]);
    $kode_dok = '';
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $kode_dok = $row['kode_dok'];
    }
    sqlsrv_free_stmt($stmt);
    
    if ($format === 'simple') {
        // Format simple: SUM.KODE.SEQ (menggunakan titik sebagai pemisah)
        $seq = str_pad($sequenceNumber, 3, '0', STR_PAD_LEFT);
        return $prefix . '.' . $kode_dok . '.' . $seq;
    }
    
    // Format lengkap: SUM-KODE-DEPT-SUBBAG-SEQ (menggunakan dash sebagai pemisah)
    // Get department code
    $sql = "SELECT sn_dept FROM dbo.m_dept WHERE id_dept = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id_dept]);
    $sn_dept = '';
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $sn_dept = $row['sn_dept'];
    }
    sqlsrv_free_stmt($stmt);
    
    // Get subbag code
    $sql = "SELECT sn_subbag FROM dbo.m_subbag WHERE id_subbag = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id_subbag]);
    $sn_subbag = '';
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $sn_subbag = $row['sn_subbag'];
    }
    sqlsrv_free_stmt($stmt);
    
    // Format sequence number with leading zeros
    $seq = str_pad($sequenceNumber, 3, '0', STR_PAD_LEFT);
    
    // Combine all parts
    return $prefix . '-' . $kode_dok . '-' . $sn_dept . '-' . $sn_subbag . '-' . $seq;
}

ob_end_flush();
?>


    <style>
        .required:after {
            content: " *";
            color: red;
        }
        .file-upload-info {
            font-size: 0.9em;
            color: #6c757d;
        }
        .dropdown-loading {
            position: relative;
        }
        .dropdown-loading:after {
            content: " ";
            position: absolute;
            right: 10px;
            top: 50%;
            width: 20px;
            height: 20px;
            margin-top: -10px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .format-info {
            font-size: 0.85em;
            color: #6c757d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Tambah Dokumen Baru</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="e_dokumen.php">E-Dokumen</a></li>
                        <li class="breadcrumb-item active">Tambah Dokumen</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title">Form Tambah Dokumen</h3>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                            <h5><i class="icon fas fa-ban"></i> Error!</h5>
                            <?= $_SESSION['error'] ?>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>
                    
                    <form method="post" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6">
                                <!-- Format Kode Dokumen -->
                                <div class="form-group">
                                    <label for="format_kode" class="required">Format Kode Dokumen</label>
                                    <select class="form-control" id="format_kode" name="format_kode" required>
                                        <option value="lengkap" <?= isset($_POST['format_kode']) && $_POST['format_kode'] == 'lengkap' ? 'selected' : 'selected' ?>>Lengkap (SUM-KODE-DEPT-SUBBAG-SEQ)</option>
                                        <option value="simple" <?= isset($_POST['format_kode']) && $_POST['format_kode'] == 'simple' ? 'selected' : '' ?>>Simple (SUM.KODE.SEQ)</option>
                                    </select>
                                    <div class="format-info" id="format_info_lengkap">
                                        Format: [PERUSAHAAN]-[KODE_DOK]-[DEPT]-[SUBBAG]-[SEQ]
                                    </div>
                                    <div class="format-info" id="format_info_simple" style="display: none;">
                                        Format: [PERUSAHAAN].[KODE_DOK].[SEQ]
                                    </div>
                                </div>
                                
                                <!-- Kode Dokumen -->
                                <div class="form-group">
                                    <label for="id_kode_dok" class="required">Kode Dokumen</label>
                                    <select class="form-control" id="id_kode_dok" name="id_kode_dok" required>
                                        <option value="">-- Pilih Kode Dokumen --</option>
                                        <?php 
                                        $sql = "SELECT id_kode_dok, kode_dok, deskripsi FROM dbo.m_kode_dok ORDER BY kode_dok";
                                        $stmt = sqlsrv_query($conn, $sql);
                                        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
                                            <option value="<?= $row['id_kode_dok'] ?>" 
                                                <?= isset($_POST['id_kode_dok']) && $_POST['id_kode_dok'] == $row['id_kode_dok'] ? 'selected' : '' ?>
                                                data-kode="<?= htmlspecialchars($row['kode_dok']) ?>">
                                                <?= htmlspecialchars($row['kode_dok']) ?> - <?= htmlspecialchars($row['deskripsi']) ?>
                                            </option>
                                        <?php endwhile; 
                                        sqlsrv_free_stmt($stmt);
                                        ?>
                                    </select>
                                </div>
                                
                                <!-- Kategori -->
                                <div class="form-group">
                                    <label for="id_kategori" class="required">Kategori Dokumen</label>
                                    <select class="form-control" id="id_kategori" name="id_kategori" required>
                                        <option value="">-- Pilih Kategori --</option>
                                        <?php 
                                        $sql = "SELECT id_kategori, nama_kategori FROM dbo.m_kategori_dok ORDER BY nama_kategori";
                                        $stmt = sqlsrv_query($conn, $sql);
                                        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
                                            <option value="<?= $row['id_kategori'] ?>" 
                                                <?= isset($_POST['id_kategori']) && $_POST['id_kategori'] == $row['id_kategori'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($row['nama_kategori']) ?>
                                            </option>
                                        <?php endwhile; 
                                        sqlsrv_free_stmt($stmt);
                                        ?>
                                    </select>
                                </div>
                                
                                <!-- Departemen -->
                                <div class="form-group">
                                    <label for="id_dept" id="label_dept" class="required">Departemen</label>
                                    <select class="form-control" id="id_dept" name="id_dept">
                                        <option value="">-- Pilih Departemen --</option>
                                        <!-- Options akan diisi via AJAX -->
                                    </select>
                                </div>
                                
                                <!-- Bagian -->
                                <div class="form-group">
                                    <label for="id_bag" id="label_bag">Bagian</label>
                                    <select class="form-control" id="id_bag" name="id_bag" disabled>
                                        <option value="">-- Pilih Bagian --</option>
                                    </select>
                                </div>
                                
                                <!-- Sub Bagian -->
                                <div class="form-group">
                                    <label for="id_subbag" id="label_subbag" class="required">Sub Bagian</label>
                                    <select class="form-control" id="id_subbag" name="id_subbag" disabled>
                                        <option value="">-- Pilih Sub Bagian --</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <!-- Nama Dokumen -->
                                <div class="form-group">
                                    <label for="nama_dokumen" class="required">Nama Dokumen</label>
                                    <input type="text" class="form-control" id="nama_dokumen" name="nama_dokumen" 
                                        value="<?= isset($_POST['nama_dokumen']) ? htmlspecialchars($_POST['nama_dokumen']) : '' ?>" 
                                        required>
                                </div>
                                
                                <!-- Revisi -->
                                <div class="form-group">
                                    <label for="revisi" class="required">Revisi</label>
                                    <input 
                                        type="text" 
                                        class="form-control" 
                                        id="revisi" 
                                        name="revisi" 
                                        value="<?= isset($_POST['revisi']) ? htmlspecialchars(str_pad($_POST['revisi'], 2, '0', STR_PAD_LEFT)) : '00' ?>" 
                                        required
                                        maxlength="2"
                                        pattern="[0-9]{2}"
                                        title="Harus berupa 2 digit angka (00-99)"
                                        placeholder="00"
                                    >
                                    <small class="form-text text-muted">Revisi dimulai dari 00 (dokumen baru), maksimal 99</small>
                                </div>

                                
                                <!-- Tanggal Upload -->
                                <div class="form-group">
                                    <label for="tanggal_upload" class="required">Tanggal Upload</label>
                                    <input type="datetime-local" class="form-control" id="tanggal_upload" name="tanggal_upload" 
                                        value="<?= isset($_POST['tanggal_upload']) ? htmlspecialchars($_POST['tanggal_upload']) : date('Y-m-d\TH:i') ?>" 
                                        required>
                                </div>
                                
                                <!-- Tanggal Terbit -->
                                <div class="form-group">
                                    <label for="tanggal_terbit">Tanggal Terbit</label>
                                    <input type="date" class="form-control" id="tanggal_terbit" name="tanggal_terbit" 
                                        value="<?= isset($_POST['tanggal_terbit']) ? htmlspecialchars($_POST['tanggal_terbit']) : '' ?>">
                                </div>
                                
                                <!-- Tanggal Revisi -->
                                <div class="form-group">
                                    <label for="tanggal_revisi">Tanggal Revisi</label>
                                    <input type="date" class="form-control" id="tanggal_revisi" name="tanggal_revisi" 
                                        value="<?= isset($_POST['tanggal_revisi']) ? htmlspecialchars($_POST['tanggal_revisi']) : '' ?>">
                                    <small class="form-text text-muted">Diisi jika dokumen ini merupakan revisi</small>
                                </div>
                                
                                <!-- File PDF -->
                                <div class="form-group">
                                    <label for="file_pdf" class="required">File PDF</label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="file_pdf" name="file_pdf" accept=".pdf" required>
                                        <label class="custom-file-label" for="file_pdf">Pilih file PDF</label>
                                    </div>
                                    <small class="file-upload-info">Hanya file PDF yang diizinkan (maks. 5MB)</small>
                                </div>
                                
                                <!-- Preview Kode Dokumen -->
                                <div class="form-group">
                                    <label>Preview Kode Dokumen</label>
                                    <div class="form-control" id="preview_kode_dokumen" style="height: auto; background-color: #f8f9fa;">
                                        SUM-XX-XX-XX-<span id="sequence_number"></span>
                                    </div>
                                    <small class="form-text text-muted" id="preview_format_info">
                                        Format: [PERUSAHAAN]-[KODE_DOK]-[DEPT]-[SUBBAG]-[SEQ]
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="deskripsi">Deskripsi Dokumen</label>
                            <textarea name="deskripsi" id="deskripsi" class="form-control" rows="3" placeholder="Masukkan deskripsi dokumen"></textarea>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?>"><i class="fas fa-save"></i> Simpan</button>
                                <a href="e_dokumen.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===================================================
    10. IMPORT FOOTER
======================================================= -->
<?php include '../../includes/footer.php'; ?>
<!-- ===================================================
    11. JAVASCRIPT LIBRARIES
======================================================= -->
<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<!-- DataTables JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<!-- SweetAlert -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<!-- Custom JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bs-custom-file-input/bs-custom-file-input.min.js"></script>

<script>
    $(document).ready(function () {
        // Initialize file input
        bsCustomFileInput.init();
        
        // Fungsi untuk toggle required fields berdasarkan format
        function toggleFormatFields() {
            const format = $('#format_kode').val();
            const isSimple = format === 'simple';
            
            // Toggle info format
            $('#format_info_lengkap').toggle(!isSimple);
            $('#format_info_simple').toggle(isSimple);
            $('#preview_format_info').text(isSimple ? 
                'Format: [PERUSAHAAN].[KODE_DOK].[SEQ]' : 
                'Format: [PERUSAHAAN]-[KODE_DOK]-[DEPT]-[SUBBAG]-[SEQ]');
            
            // Toggle required attribute
            $('#id_dept').prop('required', !isSimple);
            $('#id_subbag').prop('required', !isSimple);
            
            // Toggle labels
            $('#label_dept').toggleClass('required', !isSimple);
            $('#label_subbag').toggleClass('required', !isSimple);
            
            // Toggle disable/enable
            $('#id_dept').prop('disabled', isSimple);
            $('#id_bag').prop('disabled', isSimple);
            $('#id_subbag').prop('disabled', isSimple);
            
            // Reset values jika format simple
            if (isSimple) {
                $('#id_dept').val('').trigger('change');
                $('#id_bag').val('').trigger('change');
                $('#id_subbag').val('').trigger('change');
            } else {
                // Enable kembali dan load data
                $('#id_dept').prop('disabled', false);
                loadDropdown('m_dept', 'id_dept', 'id_dept', 'dept', '', function() {
                    <?php if (isset($_POST['id_dept'])): ?>
                        $('#id_dept').val('<?= $_POST['id_dept'] ?>').trigger('change');
                    <?php endif; ?>
                });
            }
            
            updateKodeDokumenPreview();
        }
        
        // Event ketika format berubah
        $('#format_kode').change(function() {
            toggleFormatFields();
        });
        
        // Panggil saat halaman load
        toggleFormatFields();
        
        // Load Departemen saat halaman dibuka (jika format lengkap)
        if ($('#format_kode').val() === 'lengkap') {
            loadDropdown('m_dept', 'id_dept', 'id_dept', 'dept', '', function() {
                <?php if (isset($_POST['id_dept'])): ?>
                    // Jika ada data POST, set nilai dropdown
                    $('#id_dept').val('<?= $_POST['id_dept'] ?>').trigger('change');
                <?php endif; ?>
            });
        }
        
        // Event ketika Departemen dipilih
        $('#id_dept').change(function() {
            const deptId = $(this).val();
            $('#id_bag').html('<option value="">-- Pilih Bagian --</option>').prop('disabled', !deptId);
            $('#id_subbag').html('<option value="">-- Pilih Sub Bagian --</option>').prop('disabled', true);
            
            if (deptId) {
                loadDropdown('m_bag', 'id_bag', 'id_bag', 'bagian', `id_dept=${deptId}`, function() {
                    <?php if (isset($_POST['id_bag'])): ?>
                        // Jika ada data POST, set nilai dropdown
                        $('#id_bag').val('<?= $_POST['id_bag'] ?>').trigger('change');
                    <?php endif; ?>
                });
            }
            updateKodeDokumenPreview();
        });
        
        // Event ketika Bagian dipilih
        $('#id_bag').change(function() {
            const bagId = $(this).val();
            $('#id_subbag').html('<option value="">-- Pilih Sub Bagian --</option>').prop('disabled', !bagId);
            
            if (bagId) {
                loadDropdown('m_subbag', 'id_subbag', 'id_subbag', 'subbag', `id_bag=${bagId}`, function() {
                    <?php if (isset($_POST['id_subbag'])): ?>
                        // Jika ada data POST, set nilai dropdown
                        $('#id_subbag').val('<?= $_POST['id_subbag'] ?>');
                    <?php endif; ?>
                    updateKodeDokumenPreview();
                });
            } else {
                updateKodeDokumenPreview();
            }
        });
        
        // Event ketika Sub Bagian dipilih
        $('#id_subbag').change(function() {
            updateKodeDokumenPreview();
        });
        
        // Event ketika Kode Dokumen dipilih
        $('#id_kode_dok').change(function() {
            updateKodeDokumenPreview();
        });
        
        // Update kode dokumen preview
        function updateKodeDokumenPreview() {
            const format = $('#format_kode').val();
            const id_kode_dok = $('#id_kode_dok').val();
            
            if (format === 'simple') {
                // Format simple hanya butuh kode dokumen
                if (!id_kode_dok) {
                    $('#preview_kode_dokumen').html('SUM.XX.<span id="sequence_number"></span>');
                    return;
                }
                
                $('#preview_kode_dokumen').html('Generating... <i class="fas fa-spinner fa-spin"></i>');
                
                $.post('preview_kode.php', {
                    format: format,
                    id_kode_dok: id_kode_dok
                }, function(response) {
                    try {
                        const data = JSON.parse(response);
                        if (data.preview) {
                            $('#preview_kode_dokumen').html(data.preview + 
                                ' <small>(Next SEQ: ' + data.seq + ')</small>');
                        } else {
                            $('#preview_kode_dokumen').text('Error generating preview');
                        }
                    } catch(e) {
                        console.error("Error:", e);
                        $('#preview_kode_dokumen').text('Error parsing response');
                    }
                }).fail(function() {
                    $('#preview_kode_dokumen').text('Error fetching sequence');
                });
            } else {
                // Format lengkap
                const id_dept = $('#id_dept').val();
                const id_subbag = $('#id_subbag').val();

                if (!id_kode_dok || !id_dept || !id_subbag) {
                    $('#preview_kode_dokumen').html('SUM-XX-XX-XX-<span id="sequence_number"></span>');
                    return;
                }

                $('#preview_kode_dokumen').html('Generating... <i class="fas fa-spinner fa-spin"></i>');

                $.post('preview_kode.php', {
                    format: format,
                    id_kode_dok: id_kode_dok,
                    id_dept: id_dept,
                    id_subbag: id_subbag
                }, function(response) {
                    try {
                        const data = JSON.parse(response);
                        if (data.preview) {
                            $('#preview_kode_dokumen').html(data.preview + 
                                ' <small>(Next SEQ: ' + data.seq + ')</small>');
                        } else {
                            $('#preview_kode_dokumen').text('Error generating preview');
                        }
                    } catch(e) {
                        console.error("Error:", e);
                        $('#preview_kode_dokumen').text('Error parsing response');
                    }
                }).fail(function() {
                    $('#preview_kode_dokumen').text('Error fetching sequence');
                });
            }
        }
        
        // Fungsi untuk memuat dropdown via AJAX
        function loadDropdown(table, elementId, idCol, nameCol, where = '', callback = null) {
            const dropdown = $(`#${elementId}`);
            dropdown.addClass('dropdown-loading');
            
            $.ajax({
                url: 'get_dropdown_options.php',
                type: 'GET',
                dataType: 'json',
                data: {
                    table: table,
                    id_col: idCol,
                    name_col: nameCol,
                    where: where
                },
                success: function(data) {
                    let options = '<option value="">-- Pilih --</option>';
                    
                    data.forEach(item => {
                        const dataAttr = item.data_attr ? ` data-sn="${item.data_attr}"` : '';
                        options += `<option value="${item.id}"${dataAttr}>${item.name}</option>`;
                    });
                    
                    dropdown.html(options).prop('disabled', false);
                    dropdown.removeClass('dropdown-loading');
                    
                    if (callback && typeof callback === 'function') {
                        callback();
                    }
                },
                error: function(xhr, status, error) {
                    console.error(`Error loading ${table}:`, error);
                    dropdown.html('<option value="">Error loading data</option>');
                    dropdown.removeClass('dropdown-loading');
                }
            });
        }
        
        // Validasi revisi - memperbolehkan nilai 0
        document.addEventListener("DOMContentLoaded", function() {
            const revisiInput = document.getElementById("revisi");

            revisiInput.addEventListener("input", function() {
                // Remove any non-digit characters
                let val = this.value.replace(/\D/g, "");
                
                // Allow empty value temporarily during typing
                if (val === "") {
                    this.value = "";
                    return;
                }
                
                // Ensure value is between 0 and 9
                // Limit to 1 digit
                if (val.length > 1) {
                    val = val.substring(0, 1);
                }
                
                // Ensure value is between 0-9
                if (parseInt(val) > 9) {
                    val = "9";
                }
                
                this.value = val;
            });

            // Ensure value is properly formatted on form submit
            document.querySelector('form').addEventListener('submit', function(e) {
                const revisiValue = revisiInput.value;
                if (!revisiValue.match(/^[0-9]{1}$/)) {
                    e.preventDefault();
                    alert('Revisi harus berupa 1 digit angka (0-9)');
                    revisiInput.focus();
                }
            });
        });
        
        // Show notifications
        <?php if (isset($_SESSION['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Sukses!',
                text: "<?= $_SESSION['success'] ?>",
                timer: 3000,
                showConfirmButton: false
            });
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
    });
</script>
