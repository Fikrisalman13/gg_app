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

    // Query untuk mendapatkan dokumen kontrak yang sudah ditandatangani
    // DIUBAH: Gunakan LEFT JOIN agar data dari dashboard (status_tanda_tangan=1) tetap muncul meski belum ada record di kontrak_kerja_perjanjian
    $sqlSigned = "SELECT k.*, p.id as id_perjanjian, p.tgl_mulai, p.tgl_selesai, 
                  CASE WHEN p.file_pdf_signed IS NOT NULL THEN 1 ELSE 0 END as has_blob
                  FROM kontrak_kerja k
                  LEFT JOIN kontrak_kerja_perjanjian p ON k.id = p.id_kontrak
                  WHERE k.nik = ? AND k.status_tanda_tangan = '1'
                  ORDER BY k.upddate DESC";
    $stmtSigned = sqlsrv_query($conn, $sqlSigned, array($nik));
    $signedContracts = [];
    if ($stmtSigned) {
        while ($row = sqlsrv_fetch_array($stmtSigned, SQLSRV_FETCH_ASSOC)) {
            $signedContracts[] = $row;
        }
    }

    // Cek apakah tombol generate harus dinonaktifkan
    $isGenerateDisabled = false;
    $disableReason = "";
    $isEarlyGenerate = false;
    $activeReason = "";
    
    if (!empty($contracts)) {
        $isGenerateDisabled = true;
        $disableReason = "Ada kontrak belum ditandatangani";
    } elseif (!empty($signedContracts)) {
        $latestContract = $signedContracts[0]; // Karena sudah ORDER BY upddate DESC
        if ($latestContract['tgl_selesai']) {
            $today = new DateTime('today');
            $tglSelesai = clone $latestContract['tgl_selesai'];
            $batasGenerate = clone $tglSelesai;
            $batasGenerate->modify('-8 days');

            if ($today < $batasGenerate) {
                $isGenerateDisabled = true;
                $disableReason = "Kontrak aktif s.d " . $tglSelesai->format('d-m-Y') . " (Bisa generate H-8)";
            } elseif ($today <= $tglSelesai) {
                $isEarlyGenerate = true;
                $activeReason = "Kontrak aktif s.d " . $tglSelesai->format('d-m-Y');
            }
        } else {
            // Fallback untuk 'data lama' sesuai permintaan user
            $today = new DateTime('today');
            $fallbackSelesai = new DateTime('2026-03-17');
            $batasGenerate = clone $fallbackSelesai;
            $batasGenerate->modify('-8 days');

            if ($today < $batasGenerate) {
                $isGenerateDisabled = true;
                $disableReason = "Kontrak aktif s.d 17-03-2026 (Data Lama, Bisa generate H-8)";
            } elseif ($today <= $fallbackSelesai) {
                $isEarlyGenerate = true;
                $activeReason = "Kontrak aktif s.d 17-03-2026 (Data Lama)";
            }
        }
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

                                <div class="mt-4 text-center">
                                    <?php if ($isGenerateDisabled): ?>
                                        <button class="btn btn-secondary mx-1 shadow-sm" title="<?= $disableReason ?>" disabled>
                                            <i class="fas fa-file-pdf mr-1"></i> Generate (<?= $disableReason ?>)
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-<?php echo htmlspecialchars($themeColor); ?> mx-1 shadow-sm" data-toggle="modal" data-target="#modalGenerate" title="<?= htmlspecialchars($activeReason ?? '') ?>">
                                            <i class="fas fa-file-pdf mr-1"></i> <?= isset($isEarlyGenerate) && $isEarlyGenerate ? 'Generate Lebih Awal (' . htmlspecialchars($activeReason) . ')' : 'Generate' ?>
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-primary mx-1 shadow-sm" onclick="refreshPage()">
                                        <i class="fas fa-sync-alt mr-1"></i> Refresh
                                    </button>
                                    <button class="btn btn-secondary mx-1 shadow-sm" onclick="goBack();">
                                        <i class="fas fa-arrow-left mr-1"></i> Kembali
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
                                                            <a href="#" class="btn btn-danger btn-sm shadow-sm" onclick="confirmDelete('<?= $contract['id'] ?>')">
                                                                <i class="fas fa-trash"></i> Hapus
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

                    <div class="col-12 mt-4">
                        <div class="card shadow-sm rounded">
                            <div class="card-header bg-success text-white">
                                <h3 class="card-title mb-0">
                                    <i class="fas fa-check-circle"></i> Dokumen Kontrak yang Sudah Ditandatangani
                                </h3>
                            </div>
                            <div class="card-body">
                                <?php if (count($signedContracts) > 0): ?>
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
                                                <?php foreach ($signedContracts as $index => $contract): ?>
                                                    <tr>
                                                        <td class="text-center"><?= $index + 1 ?></td>
                                                        <td class="text-center"><?= htmlspecialchars($contract['nik']) ?></td>
                                                        <td class="text-center"><?= htmlspecialchars($contract['nomor_dokumen']) ?></td>
                                                        <td class="text-center"><?= $contract['tanggal_dokumen'] ? htmlspecialchars($contract['tanggal_dokumen']->format('d-m-Y')) : '-' ?></td>
                                                        <td class="text-center">
                                                        <?php
                                                            $isStaff = (isset($employee['jenis_karyawan']) && $employee['jenis_karyawan'] == 'Staff') || !isset($employee['jenis_karyawan']);
                                                            $pdfScript = $isStaff ? 'generate_pdf_sudahttd_staff.php' : 'generate_pdf_sudahttd.php';
                                                            $pdfUrl = $pdfScript . "?nik=" . urlencode($contract['nik']) . "&id_kontrak=" . $contract['id'];
                                                        ?>
                                                        <a href="<?= $pdfUrl ?>" target="_blank" class="btn btn-success btn-sm shadow-sm">
                                                            <i class="fas fa-file-pdf"></i> Lihat PDF
                                                        </a>
                                                        <button type="button" class="btn btn-danger btn-sm shadow-sm ml-1" onclick="confirmDeleteSigned(<?= $contract['id'] ?>)">
                                                            <i class="fas fa-trash"></i> Hapus
                                                        </button>
                                                        </td>                                      
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info text-center" role="alert">
                                        <i class="fas fa-info-circle"></i> Tidak ada dokumen kontrak yang sudah ditandatangani.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                
            </div>
        </div>
    </div>
<!-- Modal Generate -->
<div class="modal fade" id="modalGenerate" tabindex="-1" role="dialog" aria-labelledby="modalGenerateLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                <h5 class="modal-title" id="modalGenerateLabel">Input Jangka Waktu Kontrak Staff</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="modal_tgl_mulai">Tanggal Mulai Kerja:</label>
                    <input type="date" id="modal_tgl_mulai" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label for="modal_tgl_selesai">Tanggal Selesai Kerja:</label>
                    <input type="date" id="modal_tgl_selesai" class="form-control" value="<?php echo date('Y-m-d', strtotime('+3 months')); ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-<?php echo htmlspecialchars($themeColor); ?>" onclick="generateAction()">Generate Dokumen</button>
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

    function signDocument(id) {
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
                window.location.href = '/gg_app/pages/esign/sign_document.php?id_kontrak=' + id + '&nik=' + nik + '&source=staff';
            }
        });
    }

    function confirmDelete(id) {
        let nik = '<?= $employee['nik'] ?>';
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: "Data kontrak yang belum ditandatangani ini akan dihapus permanen!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `/gg_app/pages/esign/hapus_data_kontrak.php?id=${id}&nik=${nik}`;
            }
        })
    }

    function confirmDeleteSigned(id) {
        let nik = '<?= $employee['nik'] ?>';
        let jenisKaryawan = '<?= $employee['jenis_karyawan'] ?? 'Staff' ?>';
        Swal.fire({
            title: 'Hapus Data?',
            text: "Data tanda tangan akan dihapus permanen dan dokumen kembali ke status 'Belum Ditandatangani'.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                // Pilih script berdasarkan jenis karyawan
                let script = (jenisKaryawan == 'Contract' || jenisKaryawan == 'Kontrak') ? 'hapus_data_ttd_kontrak.php' : 'hapus_data_ttd_staff.php';

                // Gunakan AJAX untuk menghapus agar tidak redirect
                fetch(`/gg_app/pages/esign/${script}?nik=${nik}&id=${id}&ajax=1`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Berhasil!', data.message, 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Gagal!', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire('Error!', 'Terjadi kesalahan saat menghapus data.', 'error');
                    });
            }
        })
    }

    function generateAction() {
        var nik = '<?= $employee['nik'] ?>'; // Gunakan NIK dari PHP
        const tgl_mulai = document.getElementById('modal_tgl_mulai').value;
        const tgl_selesai = document.getElementById('modal_tgl_selesai').value;
        
        // Tutup modal
        $('#modalGenerate').modal('hide');
        
        var url = `/gg_app/pages/esign/generate_pdf_belumttd_staff.php?nik=${nik}&tgl_mulai=${tgl_mulai}&tgl_selesai=${tgl_selesai}`;

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