<?php
// ===================================================
// 1. INISIALISASI DAN KONFIGURASI
// ===================================================

// Mulai session
session_start();

// Set timezone
date_default_timezone_set('Asia/Jakarta');

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
requireEdit($conn, MENU_DEPT_INSPECT);

// ===================================================
// 7. MENDAPATKAN DATA
// ===================================================
// Mendapatkan Id Emp dari URL
$empId = $_GET['id'] ?? null;

if (!$empId) {
    $_SESSION['error'] = "Invalid Emp ID!";
    header("Location: employee_inspecting_weaving.php");
    exit();
}
// Mendapatkan data employee yang akan diedit
$empSql = "
        SELECT 
            a.EmpId, 
            a.EmpName, 
            a.DeptId, 
            a.BagianId, 
            a.ShiftId, 
            a.UpdDate, 
            a.UpdUser
        FROM dbo.SMEmployeeInspector AS a
        WHERE a.EmpId = ?
";
$empStmt = sqlsrv_query($conn, $empSql, array($empId));
if ($empStmt === false) {
    error_log("Failed to fetch employee data: " . print_r(sqlsrv_errors(), true));
    $_SESSION['error'] = "Failed to retrieve emp data.";
    header("Location: employee_inspecting_weaving.php");
    exit;
}

$empData = sqlsrv_fetch_array($empStmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($empStmt);

if (!$empData) {
    $_SESSION['error'] = "Employee not found!";
    header("Location: employee_inspecting_weaving.php");
    exit;
}
// Mendapatkan tema dari session untuk konsistensi UI
$themeColor = $_SESSION['Theme'] ?? 'primary';

// ===================================================
// 7. PROSES UPDATE DATA EMP
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Ambil data dari form
    $empName  = trim($_POST['empName'] ?? '');
    $deptId   = (int) ($_POST['deptId'] ?? 0);
    $bagianId = (int) ($_POST['bagianId'] ?? 0);
    $shiftId  = (int) ($_POST['shiftId'] ?? 0);

    $updUser  = $_SESSION['UserName'];
    $updDate  = date('Y-m-d H:i:s');

    // Validasi input wajib isi
    if (!empty($empName) && $deptId > 0 && $bagianId > 0 && $shiftId > 0) {

        // Query update
        $sqlUpdate = "UPDATE dbo.SMEmployeeInspector 
                      SET EmpName = ?, DeptId = ?, BagianId = ?, ShiftId = ?, 
                          UpdDate = ?, UpdUser = ?
                      WHERE EmpId = ?";

        $paramsUpdate = [
            $empName,
            $deptId,
            $bagianId,
            $shiftId,
            $updDate,
            $updUser,
            $empId
        ];

        $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, $paramsUpdate);

        if ($stmtUpdate) {
            sqlsrv_free_stmt($stmtUpdate);
            $_SESSION['success'] = "Employee berhasil diperbarui.";
            header("Location: employee_inspecting_weaving.php");
            exit;

        } else {
            $_SESSION['error'] = "Terjadi kesalahan saat memperbarui data employee: " 
                                 . print_r(sqlsrv_errors(), true);
        }

    } else {
        $_SESSION['error'] = "Mohon isi semua field yang wajib!";
    }
}

// ===================================================
// 8. LOAD DROPDOWN LIST
// ===================================================
$sqlDept   = "SELECT DeptId, DeptName FROM dbo.SMDeptWInspector ORDER BY DeptName";
$sqlBagian = "SELECT BagianId, BagianName FROM dbo.SMBagianWInspector ORDER BY BagianName";
$sqlShift  = "SELECT ShiftId, ShiftKet, ShiftKode FROM dbo.SMShiftWInspector ORDER BY ShiftId";

$stmtDept   = sqlsrv_query($conn, $sqlDept);
$stmtBagian = sqlsrv_query($conn, $sqlBagian);
$stmtShift  = sqlsrv_query($conn, $sqlShift);
?>
<!-- ===================================================
    8. HTML: STRUKTUR HALAMAN
======================================================= -->
<!-- CONTENT WRAPPER -->
<div class="content-wrapper">

    <!-- PAGE HEADER -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Edit Employee Weaving</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="employee_inspecting_weaving.php">Employee Weaving</a></li>
                        <li class="breadcrumb-item active">Edit Employee Weaving</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <section class="content">
        <div class="container-fluid">

            <!-- ALERT MESSAGES -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <i class="icon fas fa-check"></i>
                    <?= htmlspecialchars($_SESSION['success']); ?>
                    <?php unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <i class="icon fas fa-ban"></i>
                    <?= htmlspecialchars($_SESSION['error']); ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <!-- EDIT EMPLOYEE FORM -->
            <div class="card shadow-sm">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title">
                        <i class="fas fa-edit mr-2"></i>
                        Form Edit Employee Weaving - <?= htmlspecialchars($empData['EmpName']) ?>
                    </h3>
                </div>

                <div class="card-body">

                    <form action="" method="POST" id="editEmployeeForm" autocomplete="off">

                        <!-- HIDDEN ID -->
                        <input type="hidden" name="empId" value="<?= htmlspecialchars($empData['EmpId']) ?>">

                        <!-- EMPLOYEE NAME FIELD -->
                        <div class="form-group">
                            <label for="empName" class="font-weight-bold">Nama Karyawan *</label>
                            <input type="text"
                                class="form-control"
                                id="empName"
                                name="empName"
                                value="<?= htmlspecialchars($empData['EmpName']) ?>"
                                required
                                autocomplete="empName">
                        </div>

                        <!-- DEPARTEMEN FIELD -->
                        <div class="form-group">
                            <label for="deptId" class="font-weight-bold">Departemen *</label>
                            <select class="form-control" id="deptId" name="deptId" required>
                                <?php while ($row = sqlsrv_fetch_array($stmtDept, SQLSRV_FETCH_ASSOC)): ?>
                                    <option value="<?= $row['DeptId'] ?>"
                                        <?= $row['DeptId'] == $empData['DeptId'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($row['DeptName']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- BAGIAN FIELD -->
                        <div class="form-group">
                            <label for="bagianId" class="font-weight-bold">Bagian *</label>
                            <select class="form-control" id="bagianId" name="bagianId" required>
                                <?php while ($row = sqlsrv_fetch_array($stmtBagian, SQLSRV_FETCH_ASSOC)): ?>
                                    <option value="<?= $row['BagianId'] ?>"
                                        <?= $row['BagianId'] == $empData['BagianId'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($row['BagianName']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- SHIFT FIELD -->
                        <div class="form-group">
                            <label for="shiftId" class="font-weight-bold">Shift *</label>
                            <select class="form-control" id="shiftId" name="shiftId" required>
                                <?php while ($row = sqlsrv_fetch_array($stmtShift, SQLSRV_FETCH_ASSOC)): ?>
                                    <option value="<?= $row['ShiftId'] ?>"
                                        <?= $row['ShiftId'] == $empData['ShiftId'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($row['ShiftKode']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- ACTION BUTTONS -->
                        <div class="form-group mt-4">
                            <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>">
                                <i class="fas fa-save mr-2"></i> Simpan
                            </button>

                            <a href="employee_inspecting_weaving.php" class="btn btn-secondary ml-2">
                                <i class="fas fa-arrow-left mr-2"></i> Kembali
                            </a>
                        </div>

                    </form>

                </div>
            </div>

        </div>
    </section>

</div>

<!-- ===================================================
    9. IMPORT FOOTER
======================================================= -->
<?php include '../includes/footer.php'; ?>
