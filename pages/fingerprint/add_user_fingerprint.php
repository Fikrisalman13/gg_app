<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// CEK LOGIN
if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
define('USER_FINGERPRINT_MENU_ID', 97);

// CEK PERMISSION
function checkUserPermissions($conn, $groupId, $menuId) {
    $sql = "SELECT CanAdd FROM dbo.SMGroupTrustee WHERE GroupId=? AND MenuId=?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        return $row['CanAdd'];
    }
    return 0;
}
if (checkUserPermissions($conn, $_SESSION['GroupId'], USER_FINGERPRINT_MENU_ID) != 1) {
    $_SESSION['swal_error'] = "Anda tidak memiliki izin untuk menambah user!";
    header('Location: user_fingerprint.php');
    exit;
}

// AMBIL DATA MESIN
$mesin = [];
$stmt = sqlsrv_query($conn, "SELECT * FROM dbo.m_fingerprint ORDER BY id DESC");
if ($stmt) while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $mesin[] = $row;

// AMBIL DATA KARYAWAN
$employees = [];
$stmt = sqlsrv_query($conn, "SELECT id_emp, nama_lengkap FROM dbo.m_emp ORDER BY nama_lengkap ASC");
if ($stmt) while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $employees[] = $row;

// PROSES FORM
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $mesin_id  = $_POST['mesin_id'] ?? '';
    $pinUser   = $_POST['pin'] ?? '';
    $name      = $_POST['name'] ?? '';
    $password  = $_POST['password'] ?? '';
    $privilege = $_POST['privilege'] ?? '0';

    if (empty($mesin_id) || empty($pinUser) || empty($name)) {
        $_SESSION['swal_error'] = "Mesin, PIN User, dan Nama harus diisi!";
    } else {
        // Cari IP mesin dan comm_key
        $mesin_ip = '';
        $comm_key = '';
        foreach ($mesin as $m) {
            if ($m['id'] == $mesin_id) {
                $mesin_ip = $m['ip_address'];
                $comm_key = $m['comm_key'];
                break;
            }
        }

        if (empty($mesin_ip) || empty($comm_key)) {
            $_SESSION['swal_error'] = "IP mesin atau comm_key tidak ditemukan!";
        } else {
            $result = addUserViaSOAP($mesin_ip, $comm_key, $pinUser, $name, $password, $privilege);
            if ($result['success']) {
                $_SESSION['swal_success'] = "User fingerprint berhasil ditambahkan!";
                header('Location: user_fingerprint.php');
                exit;
            } else {
                $_SESSION['swal_error'] = "Gagal: " . $result['message'];
            }
        }
    }
}

function addUserViaSOAP($ip, $comm_key, $pinUser, $name, $password, $privilege) {
    $xmlRequest = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
               xmlns:xsd="http://www.w3.org/2001/XMLSchema" 
               xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <SetUserInfo xmlns="http://tempuri.org/">
      <ArgComKey xsi:type="xsd:integer">'.htmlspecialchars($comm_key).'</ArgComKey>
      <Arg>
        <PIN xsi:type="xsd:integer">0</PIN>
        <Name xsi:type="xsd:string">'.htmlspecialchars($name).'</Name>
        <Password xsi:type="xsd:string">'.htmlspecialchars($password).'</Password>
        <Privilege xsi:type="xsd:integer">'.htmlspecialchars($privilege).'</Privilege>
        <Group xsi:type="xsd:string">0</Group>
        <Card xsi:type="xsd:integer">0</Card>
        <PIN2 xsi:type="xsd:integer">'.htmlspecialchars($pinUser).'</PIN2>
        <TZ1 xsi:type="xsd:integer">0</TZ1>
        <TZ2 xsi:type="xsd:integer">0</TZ2>
        <TZ3 xsi:type="xsd:integer">0</TZ3>
      </Arg>
    </SetUserInfo>
  </soap:Body>
</soap:Envelope>';

    $ch = curl_init("http://$ip/iWsService");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: "http://tempuri.org/SetUserInfo"',
            'Content-Length: ' . strlen($xmlRequest)
        ],
        CURLOPT_POSTFIELDS => $xmlRequest,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['success'=>false,'message'=>'Koneksi gagal: ' . $error];
    }
    
    if ($http_code != 200) {
        return ['success'=>false,'message'=>'HTTP Error: ' . $http_code];
    }
    
    // Cek response untuk konfirmasi sukses
    if (strpos($response, '<?xml') !== false) {
        // Parse response untuk detail lebih lanjut
        if (strpos($response, '<SetUserInfoResult>') !== false) {
            preg_match('/<SetUserInfoResult>(\d+)<\/SetUserInfoResult>/', $response, $matches);
            if (isset($matches[1])) {
                $resultCode = $matches[1];
                if ($resultCode == '1') {
                    return ['success'=>true,'message'=>'User berhasil ditambahkan'];
                } else {
                    return ['success'=>false,'message'=>'Mesin menolak permintaan (Code: ' . $resultCode . ')'];
                }
            }
        }
        return ['success'=>true,'message'=>'User berhasil ditambahkan'];
    }
    
    return ['success'=>false,'message'=>'Response tidak dikenali dari mesin'];
}
?>

<div class="wrapper">
  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid"><h1>Tambah User Fingerprint</h1></div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card shadow-sm">
          <div class="card-header bg-<?= htmlspecialchars($themeColor) ?>">
            <h3 class="card-title"><i class="fas fa-user-plus"></i> Form Tambah User</h3>
          </div>

          <form method="POST" id="userForm">
            <div class="card-body">
              <div class="form-group">
                <label>Pilih Mesin *</label>
                <select name="mesin_id" class="form-control" required>
                  <option value="">-- Pilih Mesin --</option>
                  <?php foreach ($mesin as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= ($_POST['mesin_id'] ?? '') == $m['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($m['nama_mesin']) ?> (<?= $m['ip_address'] ?> - Key: <?= $m['comm_key'] ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
                <small class="form-text text-muted">Comm Key akan otomatis digunakan sesuai mesin yang dipilih</small>
              </div>

              <div class="form-group">
                <label>Pilih Karyawan (PIN & Nama) *</label>
                <select name="pin" id="employeeSelect" class="form-control select2bs4" required onchange="updateName(this)">
                  <option value="">-- Pilih Karyawan --</option>
                  <?php foreach ($employees as $e): ?>
                    <option value="<?= $e['id_emp'] ?>" data-nama="<?= htmlspecialchars($e['nama_lengkap']) ?>"
                      <?= ($_POST['pin'] ?? '') == $e['id_emp'] ? 'selected' : '' ?>>
                      <?= $e['id_emp'] ?> - <?= htmlspecialchars($e['nama_lengkap']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <input type="hidden" name="name" id="empName" value="<?= $_POST['name'] ?? '' ?>">
              </div>

              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Password</label>
                    <input type="text" class="form-control" name="password" value="<?= $_POST['password'] ?? '' ?>" placeholder="Opsional">
                    <small class="form-text text-muted">Kosongkan jika tidak ingin mengatur password</small>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Privilege</label>
                    <select class="form-control" name="privilege">
                      <option value="0" <?= ($_POST['privilege'] ?? '') == '0' ? 'selected' : '' ?>>User Biasa</option>
                      <option value="1" <?= ($_POST['privilege'] ?? '') == '1' ? 'selected' : '' ?>>Administrator</option>
                      <option value="2" <?= ($_POST['privilege'] ?? '') == '2' ? 'selected' : '' ?>>Super Admin</option>
                    </select>
                  </div>
                </div>
              </div>

              <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> 
                <strong>Informasi:</strong><br>
                - Data PIN & Nama otomatis diambil dari database karyawan (m_emp)<br>
                - Comm Key akan menggunakan nilai dari mesin yang dipilih di tabel m_fingerprint<br>
                - Pastikan mesin fingerprint dalam keadaan online dan dapat diakses
              </div>
            </div>

            <div class="card-footer">
              <button type="submit" class="btn btn-<?= $themeColor ?>"><i class="fas fa-save"></i> Simpan</button>
              <a href="user_fingerprint.php" class="btn btn-default"><i class="fas fa-arrow-left"></i> Kembali</a>
            </div>
          </form>
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
<script>
$(function(){
  // aktifkan select2
  $('.select2bs4').select2({ theme: 'bootstrap4' });

  function showAlert(type, message){
    Swal.fire({icon:type,title:type==='success'?'Sukses':(type==='error'?'Error':'Info'),text:message,timer:2500,showConfirmButton:false});
  }

  <?php if (isset($_SESSION['swal_error'])): ?>
    showAlert('error','<?= $_SESSION['swal_error'] ?>');
    <?php unset($_SESSION['swal_error']); ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['swal_success'])): ?>
    showAlert('success','<?= $_SESSION['swal_success'] ?>');
    <?php unset($_SESSION['swal_success']); ?>
  <?php endif; ?>
});

// update nama otomatis
function updateName(sel){
  const nama = sel.options[sel.selectedIndex].getAttribute('data-nama');
  document.getElementById('empName').value = nama;
}
</script>
