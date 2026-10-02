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
        .emp-page {
            --emp-dark: #0f1d2c;
            --emp-dark-2: #12263a;
            --emp-accent: #2ec4b6;
            --emp-accent-2: #f6c453;
            --emp-light: #f8fafc;
            --emp-muted: #6b7280;
            --emp-left-width: 270px;
            --emp-left-pad: 18px;
            font-family: "Poppins", "Segoe UI", "Tahoma", sans-serif;
            background: radial-gradient(circle at top, #f8fafc 0%, #e2e8f0 58%, #d5dee9 100%);
            padding-bottom: 32px;
        }
        .emp-page .card {
            border: 0;
            box-shadow: none;
            background: transparent;
        }
        .emp-page .card-header {
            border-radius: 10px 10px 0 0;
        }
        .emp-cv {
            position: relative;
            background: #e2e8f0;
            color: #0f172a;
            border-radius: 18px;
            border: 1px solid #d7e0ea;
            overflow: hidden;
            box-shadow: 0 24px 50px rgba(15, 23, 42, 0.18);
        }
        .emp-cv::before {
            content: "";
            position: absolute;
            top: -140px;
            right: -120px;
            width: 320px;
            height: 320px;
            background: radial-gradient(circle, rgba(46, 196, 182, 0.25), transparent 70%);
            pointer-events: none;
        }
        .emp-cv__header {
            position: relative;
            display: grid;
            grid-template-columns: calc(var(--emp-left-width) - (var(--emp-left-pad) * 2)) 1fr auto;
            gap: 18px;
            align-items: center;
            padding: 20px 24px 20px var(--emp-left-pad);
            background: #eef2f6;
            border-bottom: 1px solid #e2e8f0;
            min-height: 220px;
        }
        .emp-cv__photo {
            display: grid;
            gap: 6px;
            justify-items: center;
            width: 100%;
            background: linear-gradient(180deg, #0f1d2c, #0b1422);
            padding: 10px 8px;
            border-radius: 14px;
            color: #e5e7eb;
        }
        .emp-cv__photo .emp-photo-note {
            color: #a8b2c1;
        }
        .emp-cv__avatar.employee-photo,
        .emp-cv__avatar.photo-placeholder {
            width: clamp(150px, 100%, 210px);
            aspect-ratio: 3 / 4;
            height: auto;
            border-radius: 14px;
        }
        .employee-photo {
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.22);
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.25);
        }
        .photo-placeholder {
            background: radial-gradient(circle at 30% 20%, #22324a, #0f1d2c);
            border: 2px dashed rgba(255, 255, 255, 0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #cbd5f5;
        }
        .emp-photo-note {
            font-size: 11px;
            color: var(--emp-muted);
            text-align: center;
        }
        .emp-cv__title {
            padding-left: 20px;
            border-left: 6px solid var(--emp-accent);
            align-self: center;
        }
        .emp-cv__name {
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 6px;
            color: #0f172a;
        }
        .emp-cv__role {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.24em;
            color: #64748b;
            margin-bottom: 6px;
        }
        .emp-cv__chips {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 16px;
        }
        .emp-chip {
            border: 1px solid #e2e8f0;
            background: #f1f5f9;
            color: #1f2937;
            padding: 7px 14px;
            border-radius: 999px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .emp-chip strong {
            font-weight: 600;
            color: #0f172a;
        }
        .emp-cv__actions .btn {
            border-radius: 10px;
            font-weight: 600;
            margin-left: 8px;
        }
        .emp-cv__actions {
            position: absolute;
            top: 18px;
            right: 20px;
        }
        .emp-cv__body {
            display: grid;
            grid-template-columns: var(--emp-left-width) 1fr;
            gap: 0;
            padding: 0;
        }
        .emp-cv__left {
            display: grid;
            gap: 14px;
            background: linear-gradient(180deg, #0f1d2c, #0b1422);
            color: #e2e8f0;
            padding: 20px var(--emp-left-pad);
        }
        .emp-cv__right {
            display: grid;
            gap: 14px;
            background: #ffffff;
            padding: 20px 24px;
            align-content: start;
        }
        .emp-block {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px;
            padding: 14px 14px 10px;
        }
        .emp-block--light {
            background: #f8fafc;
            color: #0f172a;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 18px rgba(15, 23, 42, 0.06);
        }
        .emp-block__title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.3em;
            color: #a7b0bf;
            margin-bottom: 10px;
            font-weight: 700;
        }
        .emp-block--light .emp-block__title {
            color: #475569;
        }
        .emp-field {
            display: grid;
            grid-template-columns: 110px 1fr;
            gap: 10px;
            padding: 6px 0;
            border-bottom: 1px dashed rgba(255, 255, 255, 0.12);
        }
        .emp-block--light .emp-field {
            border-bottom-color: #e2e8f0;
        }
        .emp-field:last-child {
            border-bottom: 0;
        }
        .emp-field__label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #9aa4b2;
            font-weight: 600;
        }
        .emp-field__value {
            font-size: 13px;
            font-weight: 600;
            color: #e5e7eb;
            word-break: break-word;
        }
        .emp-field__value a {
            color: inherit;
            text-decoration: underline;
        }
        .emp-block--light .emp-field__value {
            color: #0f172a;
        }
        .emp-field__value--pre {
            white-space: pre-line;
            font-weight: 500;
        }
        .emp-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 2px 18px;
        }
        .emp-grid .emp-field {
            grid-template-columns: 130px 1fr;
        }
        .emp-cv .badge {
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        @media (max-width: 992px) {
            .emp-cv__header {
                grid-template-columns: 1fr;
            }
            .emp-cv__actions {
                position: static;
                grid-column: 1 / -1;
                justify-self: start;
                margin-top: 12px;
            }
            .emp-cv__body {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 576px) {
            .emp-cv__header {
                grid-template-columns: 1fr;
                text-align: center;
            }
            .emp-cv__actions {
                justify-self: center;
            }
            .emp-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <div class="content-wrapper emp-page">
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
                    <div class="card-body p-0">
                        <?php
                        $photoPath = displayPhoto($employee['foto_path']);
                        $statusKerjaText = htmlspecialchars($employee['status_kerja'] ?? '-');
                        ?>
                        <div class="emp-cv">
                            <div class="emp-cv__header">
                                <div class="emp-cv__photo">
                                    <?php if ($photoPath): ?>
                                        <img src="<?= $photoPath ?>"
                                             class="emp-cv__avatar employee-photo"
                                             alt="Foto <?= htmlspecialchars($employee['nama_lengkap']) ?>"
                                             onerror="this.style.display='none'; document.getElementById('photoPlaceholder').style.display='flex';">
                                        <div id="photoPlaceholder" class="emp-cv__avatar photo-placeholder" style="display: none;">
                                            <div class="text-center">
                                                <i class="fas fa-user fa-3x mb-2"></i>
                                                <div>Foto tidak dapat dimuat</div>
                                            </div>
                                        </div>
                                        <div class="emp-photo-note">
                                            <?= htmlspecialchars(basename($employee['foto_path'])) ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="emp-cv__avatar photo-placeholder">
                                            <div class="text-center">
                                                <i class="fas fa-user fa-3x mb-2"></i>
                                                <div>Belum ada foto</div>
                                            </div>
                                        </div>
                                        <div class="emp-photo-note">Belum ada foto</div>
                                    <?php endif; ?>
                                </div>
                                <div class="emp-cv__title">
                                    <div class="emp-cv__name"><?= htmlspecialchars($employee['nama_lengkap']) ?></div>
                                    <div class="emp-cv__role"><?= htmlspecialchars($employee['jabatan'] ?? 'Karyawan') ?></div>
                                    <div class="emp-cv__chips">
                                        <span class="emp-chip"><strong>NIK</strong> <?= htmlspecialchars($employee['nik']) ?></span>
                                        <span class="emp-chip"><strong>Status</strong> <?= $statusKerjaText ?></span>
                                        <span class="emp-chip"><strong>Dept</strong> <?= htmlspecialchars($employee['dept'] ?? '-') ?></span>
                                        <span class="emp-chip"><strong>Shift</strong> <?= htmlspecialchars($employee['shift'] ?? '-') ?></span>
                                    </div>
                                </div>
                                <div class="emp-cv__actions">
                                    <a href="edit_emp.php?nik=<?= urlencode($employee['nik']) ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit mr-1"></i> Edit Data
                                    </a>
                                    <a href="emp.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-arrow-left mr-1"></i> Kembali
                                    </a>
                                </div>
                            </div>
                            <div class="emp-cv__body">
                                <div class="emp-cv__left">
                                    <div class="emp-block">
                                        <div class="emp-block__title">Ringkasan</div>
                                        <div class="emp-field">
                                            <div class="emp-field__label">NIK</div>
                                            <div class="emp-field__value"><?= htmlspecialchars($employee['nik']) ?></div>
                                        </div>
                                        <div class="emp-field">
                                            <div class="emp-field__label">Status</div>
                                            <div class="emp-field__value"><?= getStatusBadge($employee['aktif']) ?></div>
                                        </div>
                                        <div class="emp-field">
                                            <div class="emp-field__label">Jabatan</div>
                                            <div class="emp-field__value"><?= htmlspecialchars($employee['jabatan'] ?? '-') ?></div>
                                        </div>
                                        <div class="emp-field">
                                            <div class="emp-field__label">Departemen</div>
                                            <div class="emp-field__value"><?= htmlspecialchars($employee['dept'] ?? '-') ?></div>
                                        </div>
                                        <div class="emp-field">
                                            <div class="emp-field__label">Golongan</div>
                                            <div class="emp-field__value"><?= htmlspecialchars($employee['golongan'] ?? '-') ?></div>
                                        </div>
                                        <div class="emp-field">
                                            <div class="emp-field__label">Shift</div>
                                            <div class="emp-field__value"><?= htmlspecialchars($employee['shift'] ?? '-') ?></div>
                                        </div>
                                    </div>

                                    <div class="emp-block">
                                        <div class="emp-block__title">Profil</div>
                                        <div class="emp-field">
                                            <div class="emp-field__label">Tempat Lahir</div>
                                            <div class="emp-field__value"><?= htmlspecialchars($employee['tmp_lahir'] ?? '-') ?></div>
                                        </div>
                                        <div class="emp-field">
                                            <div class="emp-field__label">Tanggal Lahir</div>
                                            <div class="emp-field__value"><?= formatDate($employee['tgl_lahir']) ?></div>
                                        </div>
                                        <div class="emp-field">
                                            <div class="emp-field__label">Jenis Kelamin</div>
                                            <div class="emp-field__value"><?= getGenderText($employee['kelamin']) ?></div>
                                        </div>
                                        <div class="emp-field">
                                            <div class="emp-field__label">Agama</div>
                                            <div class="emp-field__value"><?= htmlspecialchars($employee['agama'] ?? '-') ?></div>
                                        </div>
                                        <div class="emp-field">
                                            <div class="emp-field__label">Gol. Darah</div>
                                            <div class="emp-field__value"><?= htmlspecialchars($employee['gol_darah'] ?? '-') ?></div>
                                        </div>
                                        <div class="emp-field">
                                            <div class="emp-field__label">Status</div>
                                            <div class="emp-field__value"><?= htmlspecialchars($employee['status_kawin'] ?? '-') ?></div>
                                        </div>
                                        <div class="emp-field">
                                            <div class="emp-field__label">Jumlah Anak</div>
                                            <div class="emp-field__value"><?= $employee['jml_anak'] ? htmlspecialchars($employee['jml_anak']) : '0' ?></div>
                                        </div>
                                    </div>

                                    <div class="emp-block">
                                        <div class="emp-block__title">Kontak</div>
                                        <div class="emp-field">
                                            <div class="emp-field__label">Alamat</div>
                                            <div class="emp-field__value emp-field__value--pre"><?= $employee['alamat'] ? htmlspecialchars($employee['alamat']) : '-' ?></div>
                                        </div>
                                        <div class="emp-field">
                                            <div class="emp-field__label">Kode Pos</div>
                                            <div class="emp-field__value"><?= htmlspecialchars($employee['kd_pos'] ?? '-') ?></div>
                                        </div>
                                        <div class="emp-field">
                                            <div class="emp-field__label">Telepon</div>
                                            <div class="emp-field__value"><?= htmlspecialchars($employee['telp'] ?? '-') ?></div>
                                        </div>
                                        <div class="emp-field">
                                            <div class="emp-field__label">Email</div>
                                            <div class="emp-field__value">
                                                <?= $employee['email'] ?
                                                    '<a href="mailto:' . htmlspecialchars($employee['email']) . '">' .
                                                    htmlspecialchars($employee['email']) . '</a>' :
                                                    '-' ?>
                                            </div>
                                        </div>
                                        <div class="emp-field">
                                            <div class="emp-field__label">Kebangsaan</div>
                                            <div class="emp-field__value"><?= htmlspecialchars($employee['kebangsaan'] ?? 'Indonesia') ?></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="emp-cv__right">
                                    <div class="emp-block emp-block--light">
                                        <div class="emp-block__title">Informasi Pekerjaan</div>
                                        <div class="emp-grid">
                                            <div class="emp-field">
                                                <div class="emp-field__label">Jabatan</div>
                                                <div class="emp-field__value"><?= htmlspecialchars($employee['jabatan'] ?? '-') ?></div>
                                            </div>
                                            <div class="emp-field">
                                                <div class="emp-field__label">Departemen</div>
                                                <div class="emp-field__value"><?= htmlspecialchars($employee['dept'] ?? '-') ?></div>
                                            </div>
                                            <div class="emp-field">
                                                <div class="emp-field__label">Sub Bagian</div>
                                                <div class="emp-field__value"><?= htmlspecialchars($employee['subbag'] ?? '-') ?></div>
                                            </div>
                                            <div class="emp-field">
                                                <div class="emp-field__label">Bagian</div>
                                                <div class="emp-field__value"><?= htmlspecialchars($employee['bagian'] ?? '-') ?></div>
                                            </div>
                                            <div class="emp-field">
                                                <div class="emp-field__label">Golongan</div>
                                                <div class="emp-field__value"><?= htmlspecialchars($employee['golongan'] ?? '-') ?></div>
                                            </div>
                                            <div class="emp-field">
                                                <div class="emp-field__label">Shift</div>
                                                <div class="emp-field__value"><?= htmlspecialchars($employee['shift'] ?? '-') ?></div>
                                            </div>
                                            <div class="emp-field">
                                                <div class="emp-field__label">Tanggal Masuk</div>
                                                <div class="emp-field__value"><?= formatDate($employee['tgl_masuk']) ?></div>
                                            </div>
                                            <div class="emp-field">
                                                <div class="emp-field__label">Tanggal Keluar</div>
                                                <div class="emp-field__value"><?= formatDate($employee['tgl_keluar']) ?></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="emp-block emp-block--light">
                                        <div class="emp-block__title">Dokumen</div>
                                        <div class="emp-grid">
                                            <div class="emp-field">
                                                <div class="emp-field__label">Pendidikan</div>
                                                <div class="emp-field__value"><?= htmlspecialchars($employee['pend_akhir'] ?? '-') ?></div>
                                            </div>
                                            <div class="emp-field">
                                                <div class="emp-field__label">No. KTP</div>
                                                <div class="emp-field__value"><?= htmlspecialchars($employee['no_ktp'] ?? '-') ?></div>
                                            </div>
                                            <div class="emp-field">
                                                <div class="emp-field__label">No. BPJS</div>
                                                <div class="emp-field__value"><?= htmlspecialchars($employee['no_bpjs'] ?? '-') ?></div>
                                            </div>
                                            <div class="emp-field">
                                                <div class="emp-field__label">No. Jamsostek</div>
                                                <div class="emp-field__value"><?= htmlspecialchars($employee['no_jamsostek'] ?? '-') ?></div>
                                            </div>
                                            <div class="emp-field">
                                                <div class="emp-field__label">Finger Key</div>
                                                <div class="emp-field__value"><?= htmlspecialchars($employee['finger_key'] ?? '-') ?></div>
                                            </div>
                                            <div class="emp-field">
                                                <div class="emp-field__label">No. Rekening</div>
                                                <div class="emp-field__value"><?= htmlspecialchars($employee['no_rek'] ?? '-') ?></div>
                                            </div>
                                            <div class="emp-field">
                                                <div class="emp-field__label">Bank</div>
                                                <div class="emp-field__value"><?= htmlspecialchars($employee['Bank'] ?? '-') ?></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="emp-block emp-block--light">
                                        <div class="emp-block__title">Riwayat Perubahan Data</div>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered mb-0">
                                                <thead class="thead-light">
                                                    <tr>
                                                        <th style="width:40px;">No</th>
                                                        <th>Tanggal &amp; Jam</th>
                                                        <th>Data Diubah</th>
                                                        <th>Data Lama</th>
                                                        <th>Data Baru</th>
                                                        <th>Oleh</th>
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
                            </div>
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
