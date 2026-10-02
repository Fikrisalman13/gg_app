<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// Pastikan user sudah login
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';
// Pastikan koneksi tersedia
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

// Atur timezone ke Indonesia
date_default_timezone_set('Asia/Jakarta');

// Ambil GroupId dari session
$groupId = $_SESSION['GroupId'];

// Ambil MenuId untuk Inspecting Weaving
$menuId = 26; // Sesuaikan dengan MenuId yang benar

// Query untuk mengambil hak akses berdasarkan GroupId dan MenuId
$sql = "SELECT TOP 1 CanView, CanAdd, CanEdit, CanDelete 
        FROM dbo.SMGroupTrustee 
        WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

$permissions = ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) ? $row : [];
sqlsrv_free_stmt($stmt);

// Cek apakah pengguna memiliki hak akses CanView
if (isset($permissions['CanView']) && $permissions['CanView'] == 0) {
    $error_message = "Anda tidak memiliki hak untuk melihat halaman ini.";
}

$status = "";

if (isset($_GET['nik'])) {
    $nik = $_GET['nik'];

    $sql = "SELECT
                a.nik,
                a.nama_lengkap,
                a.Photo,
                b.dept,
                c.bagian,
                d.subbag,
                e.jabatan,
                g.golongan,
                a.tmp_lahir,
                a.tgl_lahir,
                a.kelamin,
                a.alamat,
                a.no_ktp,
                f.file_pdf 
            FROM
                dbo.m_emp AS a
                LEFT JOIN dbo.m_dept AS b ON a.id_dept = b.id_dept
                LEFT JOIN dbo.m_bag AS c ON a.id_bag = c.id_bag
                LEFT JOIN dbo.m_subbag AS d ON a.id_subbag = d.id_subbag
                LEFT JOIN dbo.kontrak_kerja AS f ON a.nik = f.nik
                LEFT JOIN dbo.m_jab AS e ON a.id_jab = e.id_jab
                LEFT JOIN dbo.m_gol AS g ON a.id_gol = g.id_gol
            WHERE
                a.aktif = '1' AND a.id_gol IN ('1','3') AND a.nik = ?";

    $stmt = sqlsrv_query($conn, $sql, array($nik));

    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    $employee = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if (!$employee) {
        echo "<p>Data karyawan tidak ditemukan.</p>";
        exit;
    }

    // Query untuk mendapatkan dokumen kontrak yang belum ditandatangani
    $sqlContracts = "SELECT * FROM kontrak_kerja WHERE nik = ? AND status_tanda_tangan = '0'";
    $stmtContracts = sqlsrv_query($conn, $sqlContracts, array($nik));

    if ($stmtContracts === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    $contracts = [];
    while ($row = sqlsrv_fetch_array($stmtContracts, SQLSRV_FETCH_ASSOC)) {
        $contracts[] = $row;
    }
} else {
    echo "<p>NIK tidak ditemukan.</p>";
    exit;
}
// Fungsi untuk menampilkan foto karyawan
function displayPhoto($photoData) {
    if ($photoData) {
        $base64 = base64_encode($photoData);
        return 'data:image/jpeg;base64,' . $base64;
    }
    return 'data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%22200%22%20height%3D%22200%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20200%20200%22%20preserveAspectRatio%3D%22none%22%3E%3Cdefs%3E%3Cstyle%20type%3D%22text%2Fcss%22%3E%23holder_18945b7b7e7%20text%20%7B%20fill%3A%23AAAAAA%3Bfont-weight%3Abold%3Bfont-family%3AArial%2C%20Helvetica%2C%20Open%20Sans%2C%20sans-serif%2C%20monospace%3Bfont-size%3A10pt%20%7D%20%3C%2Fstyle%3E%3C%2Fdefs%3E%3Cg%20id%3D%22holder_18945b7b7e7%22%3E%3Crect%20width%3D%22200%22%20height%3D%22200%22%20fill%3D%22%23EEEEEE%22%3E%3C%2Frect%3E%3Cg%3E%3Ctext%20x%3D%2274.421875%22%20y%3D%22104.5%22%3ENo%20Photo%3C%2Ftext%3E%3C%2Fg%3E%3C%2Fg%3E%3C%2Fsvg%3E';
}
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proses Tanda Tangan</title>
    <!-- CSS -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">
    <style>
        .employee-photo {
            width: 200px;
            height: 250px;
            object-fit: cover;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .photo-container {
            text-align: center;
            margin-bottom: 20px;
        }
        .info-label {
            font-weight: bold;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Proses Tanda Tangan</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item"><a href="/gg_app/pages/carikaryawan_staff.php">Cari Karyawan</a></li>
                            <li class="breadcrumb-item active">Proses Tanda Tangan</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                    <div class="col-12">
                        <div class="card shadow-sm rounded">
                            <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                                <h4 class="card-title mb-0">
                                    <i class="fa fa-address-card"></i> Informasi Karyawan
                                </h4>
                            </div>

                            <div class="card-body">
                                <div class="row align-items-start">
                                    <!-- FOTO KARYAWAN -->
                                    <div class="col-md-4 text-center mb-3">
                                        <div class="border p-2 rounded shadow-sm d-inline-block" style="background-color: #f8f9fa;">
                                            <img src="<?= displayPhoto($employee['Photo']) ?>"
                                                class="img-fluid rounded"
                                                style="max-height: 350px; object-fit: cover;"
                                                alt="Foto Karyawan">
                                        </div>
                                    </div>

                                    <!-- TABEL INFORMASI -->
                                    <div class="col-md-8">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered table-hover">
                                                <tbody>
                                                    <tr><th class="bg-light w-25">NIK</th><td id="nik"><?= htmlspecialchars($employee['nik']) ?></td></tr>
                                                    <tr><th class="bg-light">Nama Lengkap</th><td><?= htmlspecialchars($employee['nama_lengkap']) ?></td></tr>
                                                    <tr><th class="bg-light">Departemen</th><td><?= htmlspecialchars($employee['dept']) ?></td></tr>
                                                    <tr><th class="bg-light">Bagian</th><td><?= htmlspecialchars($employee['bagian']) ?></td></tr>
                                                    <tr><th class="bg-light">Sub Bagian</th><td><?= htmlspecialchars($employee['subbag']) ?></td></tr>
                                                    <tr><th class="bg-light">Jabatan</th><td><?= htmlspecialchars($employee['jabatan'] ?? '') ?></td></tr>
                                                    <tr><th class="bg-light">Golongan</th><td><?= htmlspecialchars($employee['golongan']) ?></td></tr>
                                                    <tr>
                                                        <th class="bg-light">Tempat, Tgl Lahir</th>
                                                        <td><?= htmlspecialchars($employee['tmp_lahir']) ?>,
                                                            <?= htmlspecialchars($employee['tgl_lahir']->format('d-m-Y')) ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th class="bg-light">Jenis Kelamin</th>
                                                        <td><?= htmlspecialchars($employee['kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan') ?></td>
                                                    </tr>
                                                    <tr><th class="bg-light">Alamat</th><td><?= htmlspecialchars($employee['alamat']) ?></td></tr>
                                                    <tr><th class="bg-light">No. KTP</th><td><?= htmlspecialchars($employee['no_ktp']) ?></td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <!-- TOMBOL -->
                                <div class="mt-4 text-center">
                                    <?php $hasGenerated = !empty($employee['file_pdf']); ?>
                                    <button class="btn btn-<?php echo htmlspecialchars($themeColor);?> btn-sm mx-1 shadow-sm"
                                        onclick="generateAction()" <?= $hasGenerated ? 'disabled' : '' ?>>
                                        <i class="fas fa-file-pdf"></i> Generate
                                    </button>

                                    <button class="btn btn-<?php echo htmlspecialchars($themeColor);?> btn-sm mx-1 shadow-sm" onclick="refreshPage()">
                                        <i class="fas fa-sync-alt"></i> Refresh
                                    </button>

                                    <button class="btn btn-secondary btn-sm mx-1 shadow-sm" onclick="goBack();">
                                        <i class="fas fa-arrow-left"></i> Kembali
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                

              
                    <div class="col-12">
                        <div class="card shadow-sm rounded">
                            <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                                <h3 class="card-title mb-0">
                                    <i class="fas fa-file-contract"></i> Dokumen Kontrak yang Belum Ditandatangani
                                </h3>
                            </div>
                            <div class="card-body">
                                <?php if (count($contracts) > 0): ?>
                                    <div class="table-responsive">
                                            <table class="table table-hover table-sm">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th style="text-align: center;">No</th>
                                                    <th style="text-align: center;">NIK</th>
                                                    <th style="text-align: center;">Nama Dokumen</th>
                                                    <th style="text-align: center;">Tanggal Dokumen</th>
                                                    <th style="text-align: center;">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($contracts as $index => $contract): ?>
                                                    <tr>
                                                        <td class="text-center"><?= $index + 1 ?></td>
                                                        <td class="text-center"><?= htmlspecialchars($contract['nik']) ?></td>
                                                        <td class="text-center"><?= htmlspecialchars($contract['nomor_dokumen']) ?></td>
                                                        <td class="text-center"><?= htmlspecialchars($contract['tanggal_dokumen']->format('d-m-Y')) ?></td>
                                                        <td class="text-center">
                                                            <?php if (!empty($contract['file_pdf'])): ?>
                                                                <?php
                                                                    $file_name = 'file_' . $contract['nik'] . '_' . $contract['id'] . '.pdf';
                                                                    $file_path = '../../files/pdf_staff/' . $file_name;
                                                                    file_put_contents($file_path, $contract['file_pdf']);
                                                                    $file_url = '/gg_app/files/pdf_staff/' . $file_name;
                                                                ?>
                                                                <a href="<?= htmlspecialchars($file_url) ?>" target="_blank" class="btn btn-info btn-sm shadow-sm">
                                                                    <i class="fas fa-file-pdf"></i> Lihat PDF
                                                                </a>
                                                            <?php else: ?>
                                                                <span class="text-muted">Tidak ada file</span>
                                                            <?php endif; ?>

                                                            <a href="#" class="btn btn-<?php echo htmlspecialchars($themeColor);?> btn-sm shadow-sm" onclick="signDocument('<?= $contract['id'] ?>')">
                                                                <i class="fas fa-signature"></i> E-Sign
                                                            </a>
                                                        </td>                                      
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning text-center" role="alert">
                                        <i class="fas fa-exclamation-circle"></i> Tidak ada dokumen kontrak yang belum ditandatangani.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                
            </div>
        </div>
    </div>
</div>

<!-- JS -->
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/js/datatables/dataTables.bootstrap5.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
    function refreshPage() {
        location.reload();
    }

    function goBack() {
        let lastPage = sessionStorage.getItem("lastPage");
        if (lastPage) {
            window.location.href = lastPage;
        } else {
            history.back();
        }
    }

    function signDocument(contractId) {
        let nik = '<?= $employee['nik'] ?>'; // Gunakan NIK dari PHP
        Swal.fire({
            title: 'Konfirmasi',
            text: 'Apakah Anda yakin ingin menandatangani dokumen ini?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Tandatangani',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '/gg_app/pages/esign/sign_document.php?id_kontrak=' + contractId + '&nik=' + nik;
            }
        });
    }

    function generateAction() {
        var nik = '<?= $employee['nik'] ?>'; // Gunakan NIK dari PHP
        var url = '/gg_app/pages/esign/generate_pdf_belumttd_staff.php?nik=' + nik;

        // Tampilkan loading
        Swal.fire({
            title: 'Sedang memproses',
            html: 'Mohon tunggu, dokumen sedang dibuat...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire({
                        title: 'Berhasil!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        location.reload(); // Refresh halaman setelah sukses
                    });
                } else {
                    Swal.fire({
                        title: 'Gagal!',
                        text: data.message || 'Terjadi kesalahan saat membuat dokumen',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            })
            .catch(error => {
                Swal.close();
                Swal.fire({
                    title: 'Error!',
                    text: 'Terjadi kesalahan saat menghubungi server: ' + error.message,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                console.error('Error:', error);
            });
    }
</script>

</body>
</html>

<?php include '../../includes/footer.php'; ?>