<?php
// ===================================================
// 1. INISIALISASI DAN KONFIGURASI
// ===================================================

// Mulai session
session_start();

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// ===================================================
// 2. PENGATURAN NOTIFIKASI
// ===================================================
// *Menangani notifikasi sukses dan error dari session
$successMessage = $_SESSION['success'] ?? null;
$errorMessage = $_SESSION['error'] ?? null;
// *Hapus notifikasi dari session setelah diambil
unset($_SESSION['success'], $_SESSION['error']);

// ===================================================
// 3. VALIDASI AUTENTIKASI
// ===================================================
// *Jika belum, redirect ke halaman login
if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// ===================================================
// 4. KONEKSI DATABASE
// ===================================================
/**
 * Membuat koneksi ke database
 * Menghentikan eksekusi jika koneksi gagal
 */
include '../../koneksi.php';
if (!$conn) {
    $errors = sqlsrv_errors();
    error_log("Koneksi database gagal: " . print_r($errors, true));
    die("Terjadi kesalahan sistem. Silakan hubungi administrator.");
}

// ===================================================
// 5. IMPORT DEPENDENSI
// ===================================================
// Import layout dan komponen
include '../../includes/header.php';
include '../../includes/sidebar.php';
// Import konstanta dan permission
include '../../includes/menu_constants.php';
include '../../includes/permissions.php';

// ===================================================
// 6. VALIDASI IZIN AKSES
// ===================================================
requireView($conn, MENU_DASHBOARD_ESIGN);
$perm = userPermissions($conn, MENU_DASHBOARD_ESIGN);
// ===================================================
// 7. FUNGSI BANTU
// ===================================================

// Query total semua karyawan kontrak
$queryTotalKontrak = "SELECT COUNT(nik) AS total_karyawan FROM dbo.m_emp
                      WHERE aktif = '1' 
                      AND id_gol IN ('2','7','8','10','11')
                      ";

$resultTotalKontrak = sqlsrv_query($conn, $queryTotalKontrak);
$totalKaryawanKontrak = ($row = sqlsrv_fetch_array($resultTotalKontrak, SQLSRV_FETCH_ASSOC)) ? $row['total_karyawan'] : 0;

// Query total karyawan kontrak yang sudah tanda tangan
$querySignedKontrak = "SELECT
                            COUNT(a.nik) AS signed_karyawan
                        FROM
                            dbo.m_emp AS a
                            LEFT JOIN
                            dbo.kontrak_kerja AS h
                            ON 
                                a.nik = h.nik
                        WHERE
                            a.aktif = '1' AND
                            h.status_tanda_tangan = '1' AND
                            a.id_gol IN ('2','7','8','10','11')";

$resultSignedKontrak = sqlsrv_query($conn, $querySignedKontrak);
$signedKaryawanKontrak = ($row = sqlsrv_fetch_array($resultSignedKontrak, SQLSRV_FETCH_ASSOC)) ? $row['signed_karyawan'] : 0;

// Hitung karyawan kontrak yang belum tanda tangan
$notSignedKaryawanKontrak = $totalKaryawanKontrak - $signedKaryawanKontrak;

// Hitung persentase karyawan kontrak
$percentSignedKontrak = ($totalKaryawanKontrak > 0) ? round(($signedKaryawanKontrak / $totalKaryawanKontrak) * 100, 2) : 0;
$percentNotSignedKontrak = ($totalKaryawanKontrak > 0) ? round(($notSignedKaryawanKontrak / $totalKaryawanKontrak) * 100, 2) : 0;

// Query total semua karyawan staff/clerk
$queryTotalStaff = "SELECT COUNT
                        ( nik ) AS total_karyawan 
                    FROM
                        dbo.m_emp 
                    WHERE
                        aktif = '1' 
                        AND id_gol IN ('1','3')";

$resultTotalStaff = sqlsrv_query($conn, $queryTotalStaff);
$totalKaryawanStaff = ($row = sqlsrv_fetch_array($resultTotalStaff, SQLSRV_FETCH_ASSOC)) ? $row['total_karyawan'] : 0;

// Query total karyawan staff/clerk yang sudah tanda tangan
$querySignedStaff = "SELECT COUNT
                        ( a.nik ) AS signed_karyawan 
                    FROM
                        dbo.m_emp AS a
                        LEFT JOIN dbo.kontrak_kerja AS h ON a.nik = h.nik 
                    WHERE
                        a.aktif = '1' 
                        AND h.status_tanda_tangan = '1' 
                        AND id_gol IN ('1','3')";

$resultSignedStaff = sqlsrv_query($conn, $querySignedStaff);
$signedKaryawanStaff = ($row = sqlsrv_fetch_array($resultSignedStaff, SQLSRV_FETCH_ASSOC)) ? $row['signed_karyawan'] : 0;

// Hitung karyawan staff/clerk yang belum tanda tangan
$notSignedKaryawanStaff = $totalKaryawanStaff - $signedKaryawanStaff;

// Hitung persentase karyawan staff/clerk
$percentSignedStaff = ($totalKaryawanStaff > 0) ? round(($signedKaryawanStaff / $totalKaryawanStaff) * 100, 2) : 0;
$percentNotSignedStaff = ($totalKaryawanStaff > 0) ? round(($notSignedKaryawanStaff / $totalKaryawanStaff) * 100, 2) : 0;


?>

   
    <style>
        :root {
            --primary: #2c80b9;
            --secondary: #6c757d;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --light: #f8f9fa;
            --dark: #343a40;
        }
        
        .content-wrapper {
            background-color: #f4f6f9;
        }
        
        .card {
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
            border: none;
            transition: transform 0.2s ease;
        }
        
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.12);
        }
        
        .stat-card {
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card .inner {
            position: relative;
            z-index: 2;
        }
        
        .stat-card h2 {
            font-size: 2.2rem;
            margin: 0;
            font-weight: 600;
        }
        
        .stat-card p {
            font-size: 0.95rem;
            margin: 5px 0 0;
            opacity: 0.9;
        }
        
        .stat-card .icon {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 50px;
            opacity: 0.2;
        }
        
        .bg-primary { background-color: var(--primary) !important; }
        .bg-success { background-color: var(--success) !important; }
        .bg-danger { background-color: var(--danger) !important; }
        .bg-info { background-color: var(--info) !important; }
        
        .section-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
            padding-bottom: 8px;
            border-bottom: 1px solid #eaeaea;
        }
        
        .progress-container {
            margin-top: 15px;
        }
        
        .progress {
            height: 10px;
            border-radius: 5px;
            background-color: #e9ecef;
            overflow: hidden;
        }
        
        .progress-bar {
            border-radius: 5px;
        }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .summary-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f1f1f1;
        }
        
        .summary-item:last-child {
            border-bottom: none;
        }
        
        .stat-badge {
            font-size: 0.85rem;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 500;
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
        }
        
        @media (max-width: 768px) {
            .stat-card h2 {
                font-size: 1.8rem;
            }
            
            .stat-card .icon {
                font-size: 40px;
            }
            
            .section-title {
                font-size: 1.2rem;
            }
        }
    </style>

<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Dashboard E-Sign</h1>
                    </div>
                    
                </div>
           
                <!-- Section Karyawan Kontrak -->
                <div class="row mt-4">
                    <div class="col-12">
                        <h3 class="section-title">Karyawan Kontrak</h3>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-4 col-md-6">
                        <div class="card">
                            <div class="stat-card bg-primary">
                                <div class="inner">
                                    <h2><?= number_format($totalKaryawanKontrak); ?></h2>
                                    <p>Total Karyawan Kontrak</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 col-md-6">
                        <a href="/gg_app/pages/esign/data_ttd_kontrak.php" style="text-decoration: none;">
                            <div class="card">
                                <div class="stat-card bg-success">
                                    <div class="inner">
                                        <h2><?= number_format($signedKaryawanKontrak); ?></h2>
                                        <p>Sudah Tanda Tangan</p>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-file-signature"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>

                    <div class="col-lg-4 col-md-6">
                        <a href="/gg_app/pages/esign/data_kontrak_belumttd.php" style="text-decoration: none;">
                            <div class="card">
                                <div class="stat-card bg-danger">
                                    <div class="inner">
                                        <h2><?= number_format($notSignedKaryawanKontrak); ?></h2>
                                        <p>Belum Tanda Tangan</p>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-user-times"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
                
                <!-- Progress Bar Karyawan Kontrak -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Progress Tanda Tangan Karyawan Kontrak</h5>
                                <div class="progress-container">
                                    <div class="progress-label">
                                        <span>Sudah Tanda Tangan: <?= $percentSignedKontrak ?>%</span>
                                        <span>Belum Tanda Tangan: <?= $percentNotSignedKontrak ?>%</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $percentSignedKontrak ?>%" aria-valuenow="<?= $percentSignedKontrak ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        <div class="progress-bar bg-danger" role="progressbar" style="width: <?= $percentNotSignedKontrak ?>%" aria-valuenow="<?= $percentNotSignedKontrak ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section Karyawan Staff/Clerk -->
                <div class="row mt-4">
                    <div class="col-12">
                        <h3 class="section-title">Karyawan Staff</h3>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-4 col-md-6">
                        <div class="card">
                            <div class="stat-card bg-info">
                                <div class="inner">
                                    <h2><?= number_format($totalKaryawanStaff); ?></h2>
                                    <p>Total Karyawan Staff</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-briefcase"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 col-md-6">
                        <a href="/gg_app/pages/esign/data_ttd_staff.php" style="text-decoration: none;">
                            <div class="card">
                                <div class="stat-card bg-success">
                                    <div class="inner">
                                        <h2><?= number_format($signedKaryawanStaff); ?></h2>
                                        <p>Sudah Tanda Tangan</p>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-file-signature"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>

                    <div class="col-lg-4 col-md-6">
                        <a href="/gg_app/pages/esign/data_staff_belumttd.php" style="text-decoration: none;">
                            <div class="card">
                                <div class="stat-card bg-danger">
                                    <div class="inner">
                                        <h2><?= number_format($notSignedKaryawanStaff); ?></h2>
                                        <p>Belum Tanda Tangan</p>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-user-times"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
                
                <!-- Progress Bar Karyawan Staff -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Progress Tanda Tangan Karyawan Staff</h5>
                                <div class="progress-container">
                                    <div class="progress-label">
                                        <span>Sudah Tanda Tangan: <?= $percentSignedStaff ?>%</span>
                                        <span>Belum Tanda Tangan: <?= $percentNotSignedStaff ?>%</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $percentSignedStaff ?>%" aria-valuenow="<?= $percentSignedStaff ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        <div class="progress-bar bg-danger" role="progressbar" style="width: <?= $percentNotSignedStaff ?>%" aria-valuenow="<?= $percentNotSignedStaff ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Ringkasan Statistik -->
                <div class="row">
                    <div class="col-md">
                        <div class="summary-card">
                            <h5 class="font-weight-bold mb-3">Ringkasan Keseluruhan</h5>
                            <div class="summary-item">
                                <span>Total Karyawan</span>
                                <span class="font-weight-bold"><?= number_format($totalKaryawanKontrak + $totalKaryawanStaff); ?></span>
                            </div>
                            <div class="summary-item">
                                <span>Sudah Tanda Tangan</span>
                                <span class="stat-badge bg-success text-white"><?= number_format($signedKaryawanKontrak + $signedKaryawanStaff); ?></span>
                            </div>
                            <div class="summary-item">
                                <span>Belum Tanda Tangan</span>
                                <span class="stat-badge bg-danger text-white"><?= number_format($notSignedKaryawanKontrak + $notSignedKaryawanStaff); ?></span>
                            </div>
                            <div class="summary-item">
                                <span>Persentase TTD</span>
                                <span class="font-weight-bold text-primary">
                                    <?php 
                                        $totalKaryawan = $totalKaryawanKontrak + $totalKaryawanStaff;
                                        $totalSigned = $signedKaryawanKontrak + $signedKaryawanStaff;
                                        $percentage = $totalKaryawan > 0 ? round(($totalSigned / $totalKaryawan) * 100, 2) : 0;
                                        echo $percentage . '%';
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div> 
            </div>
        </section>
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



