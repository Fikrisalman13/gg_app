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
$menuId = 134; // Menu ID untuk halaman Asset

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

// Ambil data username dari session
$username = htmlspecialchars($_SESSION['NamaLengkap'] ?? '');

// Pastikan username ada dalam session
if (empty($username)) {
    die("Nama lengkap tidak ditemukan di session.");
}

// Query untuk mengambil data jabatan, departemen, dan bagian berdasarkan nama lengkap
$sql = "SELECT m_jab.jabatan, m_dept.dept, m_bag.bagian
        FROM dbo.m_emp
        LEFT JOIN dbo.m_jab ON m_emp.id_jab = m_jab.id_jab
        LEFT JOIN dbo.m_subbag ON m_emp.id_subbag = m_subbag.id_subbag
        LEFT JOIN dbo.m_bag ON m_subbag.id_bag = m_bag.id_bag
        LEFT JOIN dbo.m_dept ON m_bag.id_dept = m_dept.id_dept
        WHERE m_emp.nama_lengkap = ?";

// Menjalankan query
$params = array($username);
$stmt = sqlsrv_query($conn, $sql, $params);

// Cek jika query berhasil dijalankan
if ($stmt === false) {
    // Jika query gagal, tampilkan error
    die(print_r(sqlsrv_errors(), true));
} else {
    // Jika query berhasil, lanjutkan
    $data_emp = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    
    // Ambil hasil data jabatan, departemen, dan bagian
    $jabatan    = $data_emp['jabatan'];
    $departemen = $data_emp['dept'];
    $bagian     = $data_emp['bagian'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Form Pengajuan Perangkat IT</title>

<!-- Font Awesome -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">

<!-- AdminLTE -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">

<!-- DataTables (optional) -->
<link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">

</head>

<body class="hold-transition sidebar-mini layout-fixed">

<div class="wrapper">
    <!-- Content Wrapper -->
    <div class="content-wrapper">

        <!-- Header content -->
        <section class="content-header">
            <div class="container-fluid">
                <h1>Form Pengajuan Perangkat IT</h1>
            </div>
        </section>

        <!-- Main content -->
        <section class="content">
        <div class="container-fluid">

            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title">Input Pengajuan</h3>
                </div>

                <form method="POST" id="mainForm">

                    <div class="card-body">

                        <div class="form-group">
                            <label>Nama Pemohon</label>
                            <input type="text" name="nama_pemohon" class="form-control" value="<?= $username ?>" required readonly>
                        </div>

                        <div class="form-group">
                            <label>Jabatan</label>
                            <input type="text" name="jabatan" class="form-control" value="<?= $jabatan ?>" readonly>
                        </div>

                       <div class="form-group">
    <label>Tanggal Pengajuan</label>
    <input type="text" name="tgl_pengajuan" class="form-control" id="tgl_pengajuan" placeholder="DD-MM-YYYY" readonly>
</div>

                        <div class="form-group">
                            <label>Departemen</label>
                            <input type="text" name="departemen" class="form-control" value="<?= $departemen ?>" readonly>
                        </div>

                        <div class="form-group">
                            <label>Bagian</label>
                            <input type="text" name="bagian" class="form-control" value="<?= $bagian ?>" readonly>
                        </div>

                       <div class="form-group">
    <label>Pengajuan</label>
    <div class="row">

        <?php
        $opsi = ['Komputer', 'Laptop', 'Tablet', 'Handphone', 'Lainnya'];
        foreach ($opsi as $o) {
            $id = strtolower(str_replace(' ', '_', $o));
            echo "
            <div class='col-md-4 mb-2'>
                <div class='form-check d-flex align-items-center gap-2'>
                    <input class='form-check-input pengajuan-check' 
                           type='checkbox' 
                           data-target='input_$id'
                           value='$o'
                           name='pengajuan[]'>

                    <label class='form-check-label'>$o</label>

                    <!-- Qty muncul dinamis -->
                    <input type='number' name='qty_$id' 
                           class='form-control form-control-sm d-none qty-box' 
                           placeholder='Qty' style='width:70px;'>

                    <!-- Keterangan khusus untuk Lainnya -->
                    " . ($o === 'Lainnya' ? "
                    <input type='text' name='ket_$id' 
                           class='form-control form-control-sm d-none ket-box' 
                           placeholder='Isi keterangan'>" 
                    : "") . "
                </div>
            </div>
            ";
        }
        ?>
    </div>
</div>


                     

                        <div class="form-group">
                            <label>Spesifikasi Khusus</label>
                            <textarea name="spesifikasi" class="form-control" rows="3"></textarea>
                        </div>

                      <div class="form-group">
    <label>Peripheral</label>
    <div class="row">

        <?php
        $perip = ['Keyboard', 'Mouse', 'Monitor', 'Printer', 'Scanner', 'Lainnya'];
        foreach ($perip as $p) {
            $id = strtolower(str_replace(' ', '_', $p));
            echo "
            <div class='col-md-4 mb-2'>
                <div class='form-check d-flex align-items-center gap-2'>
                    <input class='form-check-input peripheral-check' 
                           type='checkbox' 
                           value='$p'
                           name='peripheral[]'>

                    <label class='form-check-label'>$p</label>

                    <!-- Qty dinamis -->
                    <input type='number' name='qty_perip_$id' 
                           class='form-control form-control-sm d-none qty-box' 
                           placeholder='Qty' style='width:70px;'>

                    <!-- Keterangan jika Lainnya -->
                    " . ($p === 'Lainnya' ? "
                    <input type='text' name='ket_perip_$id' 
                           class='form-control form-control-sm d-none ket-box' 
                           placeholder='Isi keterangan'>" 
                    : "") . "
                </div>
            </div>
            ";
        }
        ?>
    </div>
</div>


                        <div class="form-group">
                            <label>Keterangan</label>
                            <textarea name="keterangan" class="form-control" rows="3"></textarea>
                        </div>

                    </div>

                 
<div class="card-footer">

    <button type="submit" name="action" value="simpan" formaction="proses_simpan.php" class="btn btn-success">
        <i class="fas fa-save"></i> Simpan
    </button>

   
</div>

                </form>
            </div>
 
        </div>
        </section>

    </div>

</div>
    <?php include '../../includes/footer.php'; ?>

<!-- jQuery -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/jquery/jquery.min.js"></script>

<!-- Bootstrap -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

<script>
    // Fungsi untuk mendapatkan tanggal hari ini dengan format "DD-MM-YYYY"
    function setCurrentDate() {
        var today = new Date();
        var day = today.getDate().toString().padStart(2, '0'); // Format day
        var month = (today.getMonth() + 1).toString().padStart(2, '0'); // Format month (JavaScript months are 0-11)
        var year = today.getFullYear(); // Get full year

        // Format tanggal menjadi "DD-MM-YYYY"
        var currentDate = day + '-' + month + '-' + year;

        // Isi input field dengan tanggal hari ini
        document.getElementById('tgl_pengajuan').value = currentDate;
    }

    // Set tanggal otomatis saat halaman dimuat
    window.onload = setCurrentDate;
</script>
<script>
$(document).ready(function() {

    $(".pengajuan-check").change(function() {

        let row = $(this).closest(".form-check");

        let qtyBox = row.find(".qty-box");
        let ketBox = row.find(".ket-box");

        if ($(this).is(":checked")) {
            qtyBox.removeClass("d-none");

            // jika kolom adalah "Lainnya" → munculkan keterangan juga
            if (ketBox.length > 0) {
                ketBox.removeClass("d-none");
            }

        } else {
            qtyBox.addClass("d-none").val('');
            ketBox.addClass("d-none").val('');
        }

    });

});

$(document).ready(function() {

    $(".peripheral-check").change(function() {

        let row = $(this).closest(".form-check");

        let qtyBox = row.find(".qty-box");
        let ketBox = row.find(".ket-box");

        if ($(this).is(":checked")) {
            qtyBox.removeClass("d-none");

            // Keterangan khusus untuk Lainnya
            if (ketBox.length > 0) {
                ketBox.removeClass("d-none");
            }

        } else {
            qtyBox.addClass("d-none").val('');
            ketBox.addClass("d-none").val('');
        }

    });

});


</script>

</body>
</html>
