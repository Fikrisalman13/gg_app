<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// Authentication check
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

if (!$conn) {
    die("Koneksi gagal: " . print_r(sqlsrv_errors(), true));
}

// Check if employee ID is provided
if (!isset($_GET['id']) || empty(trim($_GET['id']))) {
    $_SESSION['error'] = "ID karyawan tidak ditemukan!";
    header('Location: emp.php');
    exit;
}

$empId = trim($_GET['id']);

// Get employee data with proper joins
$sql = "SELECT 
            e.*, 
            g.golongan, 
            j.jabatan, 
            s.subbag, 
            b.bagian, 
            d.dept, 
            sh.shift,
            CASE 
                WHEN e.aktif = 1 THEN 'Aktif'
                ELSE 'Tidak Aktif'
            END as status_kerja
        FROM dbo.m_emp e
        LEFT JOIN dbo.m_gol g ON e.id_gol = g.id_gol
        LEFT JOIN dbo.m_jab j ON e.id_jab = j.id_jab
        LEFT JOIN dbo.m_subbag s ON e.id_subbag = s.id_subbag
        LEFT JOIN dbo.m_bag b ON e.id_bag = b.id_bag
        LEFT JOIN dbo.m_dept d ON e.id_dept = d.id_dept
        LEFT JOIN dbo.m_shift sh ON e.id_shift = sh.id_shift
        WHERE e.nik = ?";
        
$params = [$empId];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    error_log("Error retrieving employee data: " . print_r(sqlsrv_errors(), true));
    $_SESSION['error'] = "Terjadi kesalahan saat mengambil data karyawan!";
    header('Location: emp.php');
    exit;
}

$employee = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);

if (!$employee) {
    $_SESSION['error'] = "Data karyawan dengan NIK $empId tidak ditemukan!";
    header('Location: emp.php');
    exit;
}

// Helper functions
function displayPhoto($fotoPath) {
    if ($fotoPath && file_exists("../../" . $fotoPath)) {
        return '../../' . $fotoPath;
    }
    return null;
}

function formatDate($date) {
    if (!$date) return '-';
    
    if ($date instanceof DateTime) {
        return $date->format('d-m-Y');
    }
    
    try {
        return date('d-m-Y', strtotime($date));
    } catch (Exception $e) {
        return '-';
    }
}

function formatDateTime($datetime) {
    if (!$datetime) return '-';
    
    if ($datetime instanceof DateTime) {
        return $datetime->format('d-m-Y H:i');
    }
    
    try {
        return date('d-m-Y H:i', strtotime($datetime));
    } catch (Exception $e) {
        return '-';
    }
}

function getGenderText($gender) {
    return match($gender) {
        'L' => 'Laki-laki',
        'P' => 'Perempuan',
        default => '-'
    };
}

function getStatusBadge($status) {
    return $status == 1 ? 
        '<span class="badge badge-success">Aktif</span>' : 
        '<span class="badge badge-danger">Tidak Aktif</span>';
}

ob_end_flush();
?>

    <style>
        .employee-photo {
            width: 200px;
            height: 250px;
            object-fit: cover;
            border: 3px solid #dee2e6;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .photo-placeholder {
            width: 200px;
            height: 250px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }
        .info-section {
            margin-bottom: 25px;
            padding: 20px;
            border: 1px solid #e3e6f0;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .info-section h4 {
            color: #2E86AB;
            border-bottom: 2px solid #2E86AB;
            padding-bottom: 12px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .info-label {
            font-weight: 600;
            color: #495057;
            width: 160px;
        }
        .info-value {
            color: #212529;
            word-break: break-word;
        }
        .section-icon {
            margin-right: 10px;
            color: #2E86AB;
        }
        .employee-header {
           
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .badge-status {
            font-size: 0.9em;
            padding: 6px 12px;
            border-radius: 20px;
        }
        .form-group.row {
            margin-bottom: 12px;
            padding: 8px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        .form-group.row:last-child {
            border-bottom: none;
        }
        .action-buttons .btn {
            margin-right: 8px;
            margin-bottom: 8px;
        }
        @media (max-width: 768px) {
            .employee-photo, .photo-placeholder {
                width: 150px;
                height: 180px;
            }
            .info-label {
                width: 120px;
            }
        }
    </style>

<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0 text-dark">
                            Detail Karyawan
                        </h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="../../index.php">Beranda</a></li>
                            <li class="breadcrumb-item"><a href="emp.php">Manajemen Karyawan</a></li>
                            <li class="breadcrumb-item active">Detail</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="content">
            <div class="container-fluid">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <i class="icon fas fa-ban"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header bg-<?= $themeColor ?> text-white">
                        <h3 class="card-title">
                            <i class="fas fa-id-card mr-2"></i>Informasi Karyawan
                        </h3>
                        <div class="card-tools">
                            <a href="emp.php" class="btn btn-sm btn-secondary">
                                <i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Employee Header -->
                        <div class="employee-header bg-<?= $themeColor ?> text-white">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h2 class="mb-1"><?= htmlspecialchars($employee['nama_lengkap']) ?></h2>
                                    <h4 class="mb-2"><?= htmlspecialchars($employee['nik']) ?></h4>
                                    <div class="d-flex align-items-center">
                                        <?= getStatusBadge($employee['aktif']) ?>
                                        <span class="ml-3">
                                            <i class="fas fa-briefcase mr-1"></i>
                                            <?= htmlspecialchars($employee['jabatan'] ?? 'Belum ditentukan') ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-4 text-right">
                                    <div class="action-buttons">
                                        <a href="edit_emp.php?nik=<?= urlencode($employee['nik']) ?>" 
                                           class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit mr-1"></i> Edit Data
                                        </a>
                                        <a href="emp.php" class="btn btn-light btn-sm">
                                            <i class="fas fa-list mr-1"></i> Daftar Karyawan
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <!-- Photo Section -->
                            <div class="col-md-3 text-center">
                                <?php 
                                $photoPath = displayPhoto($employee['foto_path']);
                                if ($photoPath): 
                                ?>
                                    <img src="<?= $photoPath ?>" 
                                         class="employee-photo mb-3" 
                                         alt="Foto <?= htmlspecialchars($employee['nama_lengkap']) ?>"
                                         onerror="this.style.display='none'; document.getElementById('photoPlaceholder').style.display='flex';">
                                    <div id="photoPlaceholder" class="photo-placeholder mb-3" style="display: none;">
                                        <div class="text-center">
                                            <i class="fas fa-user fa-3x mb-2 text-muted"></i>
                                            <div class="text-muted">Foto tidak dapat dimuat</div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="photo-placeholder mb-3">
                                        <div class="text-center">
                                            <i class="fas fa-user fa-3x mb-2 text-muted"></i>
                                            <div class="text-muted">Belum ada foto</div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($employee['foto_path']): ?>
                                    <small class="text-muted d-block">
                                        <i class="fas fa-file-image mr-1"></i>
                                        <?= htmlspecialchars(basename($employee['foto_path'])) ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Information Sections -->
                            <div class="col-md-9">
                                <!-- Informasi Pribadi -->
                                <div class="info-section">
                                    <h4>
                                        <i class="fas fa-user section-icon"></i>Informasi Pribadi
                                    </h4>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">Tempat Lahir</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= htmlspecialchars($employee['tmp_lahir'] ?? '-') ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">Tanggal Lahir</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= formatDate($employee['tgl_lahir']) ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">Jenis Kelamin</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= getGenderText($employee['kelamin']) ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">Agama</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= htmlspecialchars($employee['agama'] ?? '-') ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">Gol. Darah</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= htmlspecialchars($employee['gol_darah'] ?? '-') ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">Status</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= htmlspecialchars($employee['status_kawin'] ?? '-') ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">Jumlah Anak</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= $employee['jml_anak'] ? htmlspecialchars($employee['jml_anak']) : '0' ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Informasi Kontak -->
                                <div class="info-section">
                                    <h4>
                                        <i class="fas fa-address-book section-icon"></i>Informasi Kontak
                                    </h4>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">Alamat</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= $employee['alamat'] ? nl2br(htmlspecialchars($employee['alamat'])) : '-' ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">Kode Pos</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= htmlspecialchars($employee['kd_pos'] ?? '-') ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">Telepon</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= htmlspecialchars($employee['telp'] ?? '-') ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">Email</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= $employee['email'] ? 
                                                            '<a href="mailto:' . htmlspecialchars($employee['email']) . '">' . 
                                                            htmlspecialchars($employee['email']) . '</a>' : 
                                                            '-' ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">Kebangsaan</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= htmlspecialchars($employee['kebangsaan'] ?? 'Indonesia') ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Informasi Pekerjaan -->
                                <div class="info-section">
                                    <h4>
                                        <i class="fas fa-briefcase section-icon"></i>Informasi Pekerjaan
                                    </h4>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">Jabatan</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= htmlspecialchars($employee['jabatan'] ?? '-') ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">Sub Bagian</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= htmlspecialchars($employee['subbag'] ?? '-') ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">Bagian</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= htmlspecialchars($employee['bagian'] ?? '-') ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">Departemen</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= htmlspecialchars($employee['dept'] ?? '-') ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">Golongan</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= htmlspecialchars($employee['golongan'] ?? '-') ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">Shift</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= htmlspecialchars($employee['shift'] ?? '-') ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">Tanggal Masuk</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= formatDate($employee['tgl_masuk']) ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">Tanggal Keluar</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= formatDate($employee['tgl_keluar']) ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Informasi Lainnya -->
                                <div class="info-section">
                                    <h4>
                                        <i class="fas fa-file-alt section-icon"></i>Informasi Lainnya
                                    </h4>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">Pendidikan</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= htmlspecialchars($employee['pend_akhir'] ?? '-') ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <?php if (!in_array($employee['pend_akhir'] ?? '', ['SD', 'SMP'], true)): ?>
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">Jurusan</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= htmlspecialchars($employee['jurusan'] ?: '-') ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">No. KTP</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= htmlspecialchars($employee['no_ktp'] ?? '-') ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">No. BPJS</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= htmlspecialchars($employee['no_bpjs'] ?? '-') ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">No. Jamsostek</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= htmlspecialchars($employee['no_jamsostek'] ?? '-') ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">Finger Key</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= htmlspecialchars($employee['finger_key'] ?? '-') ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">No. Rekening</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= htmlspecialchars($employee['no_rek'] ?? '-') ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">Bank</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= htmlspecialchars($employee['Bank'] ?? '-') ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">Update Terakhir</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= formatDateTime($employee['upddate']) ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label info-label">Oleh User</label>
                                                <div class="col-sm-8">
                                                    <p class="form-control-plaintext info-value">
                                                        <?= htmlspecialchars($employee['upduser'] ?? '-') ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- DATA HISTORY -->
                                <div class="info-section mt-4">
                                    <h4>
                                        <i class="fas fa-history section-icon"></i>Riwayat Perubahan Data
                                    </h4>
                                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                        <table class="table table-bordered mb-0" style="background-color: #ffffff;" id="tableHistory">
                                            <thead style="position: sticky; top: 0; background-color: #f8f9fa; z-index: 1; box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);">
                                                <tr>
                                                    <th style="width: 5%">No</th>
                                                    <th style="width: 20%">Tanggal & Jam</th>
                                                    <th style="width: 15%">Data Diubah</th>
                                                    <th style="width: 25%">Data Lama</th>
                                                    <th style="width: 25%">Data Baru</th>
                                                    <th style="width: 10%">Oleh</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $sql_history = "SELECT * FROM dbo.m_emp_history WHERE id_emp = ? ORDER BY tgl_perubahan DESC";
                                                $stmt_hist = sqlsrv_query($conn, $sql_history, [$employee['id_emp']]);
                                                if ($stmt_hist && sqlsrv_has_rows($stmt_hist)) {
                                                    $no = 1;
                                                    while ($h = sqlsrv_fetch_array($stmt_hist, SQLSRV_FETCH_ASSOC)) {
                                                        $tgl = $h['tgl_perubahan'] ? $h['tgl_perubahan']->format('d-m-Y H:i:s') : '-';
                                                        echo "<tr>";
                                                        echo "<td>$no</td>";
                                                        echo "<td>$tgl</td>";
                                                        echo "<td>" . htmlspecialchars($h['field_changed']) . "</td>";
                                                        echo "<td>" . htmlspecialchars($h['old_value']) . "</td>";
                                                        echo "<td>" . htmlspecialchars($h['new_value']) . "</td>";
                                                        echo "<td>" . htmlspecialchars($h['diubah_oleh']) . "</td>";
                                                        echo "</tr>";
                                                        $no++;
                                                    }
                                                } else {
                                                    echo "<tr><td colspan='6' class='text-center'>Belum ada riwayat perubahan data.</td></tr>";
                                                }
                                                if ($stmt_hist) sqlsrv_free_stmt($stmt_hist);
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                            </div>
                            <!-- DATA HISTORY -->
                            
                        </div>
                    </div>
                    </div> <!-- Close card-body -->
                    <div class="card-footer text-right">
                        <div class="action-buttons">
                            <a href="edit_emp.php?nik=<?= urlencode($employee['nik']) ?>" 
                               class="btn btn-primary">
                                <i class="fas fa-edit mr-1"></i> Edit Data
                            </a>
                            <a href="emp.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar
                            </a>
                        </div>
                    </div>
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

<script>
$(document).ready(function() {
    // Handle photo loading errors
    $('.employee-photo').on('error', function() {
        $(this).hide();
        $('#photoPlaceholder').show();
    });

    // Print functionality
    $('.btn-print').on('click', function() {
        window.print();
    });

    // Smooth scrolling for better UX
    $('a[href^="#"]').on('click', function(event) {
        const target = $(this.getAttribute('href'));
        if (target.length) {
            event.preventDefault();
            $('html, body').stop().animate({
                scrollTop: target.offset().top - 70
            }, 1000);
        }
    });
});
</script>
