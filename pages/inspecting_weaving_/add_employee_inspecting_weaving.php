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

// Pastikan koneksi tersedia
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}
$themeColor = $_SESSION['Theme'] ?? 'primary';
// Ambil GroupId dari session
$groupId = $_SESSION['GroupId'];

// Ambil MenuId untuk Employee Weaving Inspector
$menuId = 10; // Sesuaikan dengan MenuId yang benar

// Query untuk mengambil hak akses berdasarkan GroupId dan MenuId
$sql = "SELECT CanAdd 
        FROM dbo.SMGroupTrustee 
        WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

// Cek jika query berhasil dan ambil hak akses
$canAdd = false;
if ($stmt === false) {
    die("Terjadi kesalahan dalam query: " . print_r(sqlsrv_errors(), true));
} else {
    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $canAdd = $row['CanAdd'] == 1;
    }
    sqlsrv_free_stmt($stmt);
}

// Cek apakah pengguna memiliki hak akses CanAdd
if (!$canAdd) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menambah data.";
    header('Location: employee_inspecting_weaving.php');
    exit;
}

// Query untuk mengambil data department, bagian, dan shift
$sqlDept = "SELECT DeptId, DeptName FROM dbo.SMDeptWInspector";
$sqlBagian = "SELECT BagianId, BagianName FROM dbo.SMBagianWInspector";
$sqlShift = "SELECT ShiftId, ShiftKet, ShiftKode FROM dbo.SMShiftWInspector";

$stmtDept = sqlsrv_query($conn, $sqlDept);
$stmtBagian = sqlsrv_query($conn, $sqlBagian);
$stmtShift = sqlsrv_query($conn, $sqlShift);

if ($stmtDept === false || $stmtBagian === false || $stmtShift === false) {
    die("Terjadi kesalahan dalam mengambil data: " . print_r(sqlsrv_errors(), true));
}

// Proses form jika ada data yang dikirim
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $empName = $_POST['empName'];
    $deptId = $_POST['deptId'];
    $bagianId = $_POST['bagianId'];
    $shiftId = $_POST['shiftId'];

    // Query untuk menambahkan data baru
    $sql = "INSERT INTO dbo.SMEmployeeInspector (EmpName, DeptId, BagianId, ShiftId, UpdDate, UpdUser) 
            VALUES (?, ?, ?, ?, GETDATE(), ?)";
    $params = [$empName, $deptId, $bagianId, $shiftId, $_SESSION['UserName']];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $_SESSION['error'] = "Terjadi kesalahan saat menambahkan data.";
    } else {
        $_SESSION['success'] = "Data Employee berhasil ditambahkan.";
        header('Location: employee_inspecting_weaving.php');
        exit;
    }
}
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Karyawan Inspect Weaving</title>
    <!-- CSS -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">

</head>
<body>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Tambah Karyawan Inspect Weaving</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="employee_inspecting_weaving.php">Karyawan Inspect Weaving</a></li>
                        <li class="breadcrumb-item active">Tambah Karyawan</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title">Form Tambah Karyawan</h3>
                </div>
                <div class="card-body table-responsive">
                    <form method="POST">
                        <div class="form-group">
                            <label for="empName">Nama Karyawan</label>
                            <input type="text" class="form-control" id="empName" name="empName" required>
                        </div>
                        <div class="form-group">
                            <label for="deptId">Departemen</label>
                            <select class="form-control" id="deptId" name="deptId" required>
                                <?php while ($row = sqlsrv_fetch_array($stmtDept, SQLSRV_FETCH_ASSOC)): ?>
                                    <option value="<?= $row['DeptId'] ?>"><?= htmlspecialchars($row['DeptName']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="bagianId">Bagian</label>
                            <select class="form-control" id="bagianId" name="bagianId" required>
                                <?php while ($row = sqlsrv_fetch_array($stmtBagian, SQLSRV_FETCH_ASSOC)): ?>
                                    <option value="<?= $row['BagianId'] ?>"><?= htmlspecialchars($row['BagianName']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="shiftId">Shift</label>
                            <select class="form-control" id="shiftId" name="shiftId" required>
                                <?php while ($row = sqlsrv_fetch_array($stmtShift, SQLSRV_FETCH_ASSOC)): ?>
                                    <option value="<?= $row['ShiftId'] ?>"><?= htmlspecialchars($row['ShiftKode']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?>"> <i class="fas fa-save"></i> Simpan</button>
                        <a href="employee_inspecting_weaving.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                    </form>
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

</body>
</html>

<?php include '../../includes/footer.php'; ?>