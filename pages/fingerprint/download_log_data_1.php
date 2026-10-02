<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// ================= CEK LOGIN ================= //
if (!isset($_SESSION['UserName'])) {
    header('Location: ../login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
define('DOWNLOAD_LOG_MENU_ID', 99); // ID menu untuk download log

// ================= CEK PERMISSION ================= //
function checkUserPermissions($conn, $groupId, $menuId) {
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete 
            FROM dbo.SMGroupTrustee 
            WHERE GroupId=? AND MenuId=?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    return ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))
        ? $row : ['CanView'=>0,'CanAdd'=>0,'CanEdit'=>0,'CanDelete'=>0];
}
$permissions = checkUserPermissions($conn, $_SESSION['GroupId'], DOWNLOAD_LOG_MENU_ID);
if ($permissions['CanView'] != 1) {
    header('Location: ../dashboard.php');
    exit;
}

// ================= AMBIL DATA MESIN ================= //
$mesin = [];
$stmt = sqlsrv_query($conn, "SELECT id,nama_mesin,ip_address,comm_key FROM dbo.m_fingerprint ORDER BY id DESC");
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $mesin[] = $row;
    }
}

// ================= FUNGSI PARSE DATA ================= //
function Parse_Data($data, $p1, $p2) {
    $data = " " . $data;
    $hasil = "";
    $awal = strpos($data, $p1);
    if ($awal !== false) {
        $akhir = strpos(strstr($data, $p1), $p2);
        if ($akhir !== false) {
            $hasil = substr($data, $awal + strlen($p1), $akhir - strlen($p1));
        }
    }
    return $hasil;
}

// ================= FUNGSI CLEAR LOG MESIN ================= //
function clearLogMachine($ip, $commKey) {
    // Value=3 untuk clear log (berdasarkan contoh yang bekerja)
    $value = 3;
    
    $soap_request = "<ClearData><ArgComKey xsi:type=\"xsd:integer\">".$commKey."</ArgComKey><Arg><Value xsi:type=\"xsd:integer\">".$value."</Value></Arg></ClearData>";

    $Connect = @fsockopen($ip, "80", $errno, $errstr, 1);
    if ($Connect) {
        $newLine = "\r\n";
        fputs($Connect, "POST /iWsService HTTP/1.0".$newLine);
        fputs($Connect, "Content-Type: text/xml".$newLine);
        fputs($Connect, "Content-Length: ".strlen($soap_request).$newLine.$newLine);
        fputs($Connect, $soap_request.$newLine);
        
        $buffer = "";
        while ($Response = fgets($Connect, 1024)) {
            $buffer .= $Response;
        }
        fclose($Connect);

        // Parse response - gunakan <Information> seperti di clear-data.php
        $information = Parse_Data($buffer, "<Information>", "</Information>");
        $result = Parse_Data($buffer, "<Result>", "</Result>");
        
        // Cek apakah berhasil berdasarkan Information atau Result
        if ($information) {
            return stripos($information, 'success') !== false;
        }
        if ($result) {
            return $result === "1" || stripos($result, 'success') !== false;
        }
        
        return false;
    }
    return false;
}

// ================= FUNGSI GET USER DARI MESIN (DIPERBAIKI) ================= //
function getAllUsersFromMachine($ip, $commKey) {
    $users = [];
    $soap_request = '<?xml version="1.0"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <GetAllUserInfo xmlns="http://tempuri.org/">
      <ArgComKey xsi:type="xsd:integer" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'.$commKey.'</ArgComKey>
    </GetAllUserInfo>
  </soap:Body>
</soap:Envelope>';

    $Connect = @fsockopen($ip, "80", $errno, $errstr, 1);
    if ($Connect) {
        $newLine = "\r\n";
        fputs($Connect, "POST /iWsService HTTP/1.0" . $newLine);
        fputs($Connect, "Content-Type: text/xml" . $newLine);
        fputs($Connect, "Content-Length: " . strlen($soap_request) . $newLine . $newLine);
        fputs($Connect, $soap_request . $newLine);

        $buffer = "";
        while ($Response = fgets($Connect, 1024)) {
            $buffer .= $Response;
        }
        fclose($Connect);

        $buffer = Parse_Data($buffer, "<GetAllUserInfoResponse>", "</GetAllUserInfoResponse>");
        $buffer = explode("\r\n", $buffer);

        foreach ($buffer as $line) {
            $data = Parse_Data($line, "<Row>", "</Row>");
            if ($data) {
                $PIN = Parse_Data($data, "<PIN>", "</PIN>");
                $PIN2 = Parse_Data($data, "<PIN2>", "</PIN2>");
                $Name = Parse_Data($data, "<Name>", "</Name>");
                
                if ($PIN) {
                    // Tentukan UserID yang akan digunakan
                    $userId = !empty($PIN2) ? $PIN2 : $PIN;
                    
                    // Simpan dengan multiple keys untuk pencarian yang fleksibel
                    $userInfo = [
                        'user_id' => $userId,
                        'pin' => $PIN,
                        'pin2' => $PIN2,
                        'name' => $Name ?: 'Tidak Ada Nama'
                    ];
                    
                    // Simpan dengan berbagai key untuk memudahkan pencarian
                    $users[$userId] = $userInfo;
                    $users['pin_' . $PIN] = $userInfo;
                    if (!empty($PIN2)) {
                        $users['pin_' . $PIN2] = $userInfo;
                    }
                }
            }
        }
    }
    return $users;
}

// ================= FUNGSI CARI USER (BARU) ================= //
function findUserInfo($users, $pinFromLog) {
    // Coba cari dengan berbagai key
    if (isset($users[$pinFromLog])) {
        return $users[$pinFromLog];
    }
    if (isset($users['pin_' . $pinFromLog])) {
        return $users['pin_' . $pinFromLog];
    }
    
    // Jika tidak ditemukan, return default
    return [
        'user_id' => $pinFromLog,
        'pin' => $pinFromLog,
        'pin2' => '',
        'name' => 'Tidak Diketahui'
    ];
}

// ================= PROSES CLEAR LOG ================= //
if (isset($_POST['clear_log']) && isset($_POST['mesin_id'])) {
    $mesinId = $_POST['mesin_id'];
    $selectedMesin = array_filter($mesin, fn($m) => $m['id'] == $mesinId);
    $selectedMesin = reset($selectedMesin);

    if ($selectedMesin) {
        $ip = $selectedMesin['ip_address'];
        $key = $selectedMesin['comm_key'];
        
        $success = clearLogMachine($ip, $key);
        
        if ($success) {
            $_SESSION['swal_success'] = "✅ Berhasil menghapus log absensi dari mesin " . htmlspecialchars($selectedMesin['nama_mesin']);
        } else {
            $_SESSION['swal_error'] = "❌ Gagal menghapus log absensi dari mesin " . htmlspecialchars($selectedMesin['nama_mesin']);
        }
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?mesin_id=" . $mesinId);
        exit;
    }
}

// ================= PROSES DOWNLOAD LOG (DIPERBAIKI) ================= //
$logData = [];
$selectedMesinId = $_GET['mesin_id'] ?? '';

if ($selectedMesinId) {
    $selectedMesin = array_filter($mesin, fn($m) => $m['id'] == $selectedMesinId);
    $selectedMesin = reset($selectedMesin);

    if ($selectedMesin) {
        $ip = $selectedMesin['ip_address'];
        $key = $selectedMesin['comm_key'];
        
        // Ambil data user dari mesin
        $users = getAllUsersFromMachine($ip, $key);
        
        // Log untuk debugging
        error_log("Total users dari mesin: " . count($users));

        $Connect = @fsockopen($ip, "80", $errno, $errstr, 1);
        if ($Connect) {
            $soap_request = "<GetAttLog><ArgComKey xsi:type=\"xsd:integer\">$key</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">All</PIN></Arg></GetAttLog>";
            $newLine = "\r\n";
            fputs($Connect, "POST /iWsService HTTP/1.0" . $newLine);
            fputs($Connect, "Content-Type: text/xml" . $newLine);
            fputs($Connect, "Content-Length: " . strlen($soap_request) . $newLine . $newLine);
            fputs($Connect, $soap_request . $newLine);

            $buffer = "";
            while ($Response = fgets($Connect, 1024)) $buffer .= $Response;
            fclose($Connect);

            $buffer = Parse_Data($buffer, "<GetAttLogResponse>", "</GetAttLogResponse>");
            $buffer = explode("\r\n", $buffer);

            foreach ($buffer as $line) {
                $data = Parse_Data($line, "<Row>", "</Row>");
                if ($data) {
                    $PIN = Parse_Data($data, "<PIN>", "</PIN>");
                    $DateTime = Parse_Data($data, "<DateTime>", "</DateTime>");
                    $Verified = Parse_Data($data, "<Verified>", "</Verified>");
                    $Status = Parse_Data($data, "<Status>", "</Status>");

                    if ($PIN && $DateTime) {
                        // Cari informasi user yang konsisten
                        $userInfo = findUserInfo($users, $PIN);
                        
                        $waktu = DateTime::createFromFormat('Y-m-d H:i:s', $DateTime);
                        $waktuFormatted = $waktu ? $waktu->format('Y-m-d H:i:s') : $DateTime;
                        
                        $logData[] = [
                            'UserID' => $userInfo['user_id'], // Gunakan user_id yang konsisten
                            'Nama' => $userInfo['name'],
                            'Waktu' => $waktuFormatted,
                            'Verified' => $Verified,
                            'Status' => $Status,
                            'OriginalPIN' => $PIN // Untuk debugging
                        ];
                        
                        // Log untuk verifikasi
                        error_log("Mapping - PIN: $PIN, UserID: " . $userInfo['user_id'] . ", Nama: " . $userInfo['name']);
                    }
                }
            }
            
            if (!empty($logData)) {
                $_SESSION['swal_success'] = "✅ Berhasil mengambil " . count($logData) . " data log dari mesin";
            }
        } else {
            $_SESSION['swal_error'] = "❌ Koneksi ke mesin gagal: $errstr ($errno)";
        }
    } else {
        $_SESSION['swal_error'] = "❌ Data mesin tidak ditemukan";
    }
}

// ================= SIMPAN KE DATABASE (DIPERBAIKI) ================= //
if (!empty($logData)) {
    $savedCount = 0;
    $errorCount = 0;
    $duplicateCount = 0;
    
    // Validasi data sebelum transaction
    $validData = [];
    foreach ($logData as $index => $log) {
        if (!empty($log['UserID']) && !empty($log['Waktu'])) {
            $validData[] = $log;
        } else {
            error_log("Data tidak valid - Index: $index, UserID: " . $log['UserID'] . ", Waktu: " . $log['Waktu']);
            $errorCount++;
        }
    }
    
    if (!empty($validData)) {
        // Mulai transaction untuk konsistensi data
        if (sqlsrv_begin_transaction($conn) === false) {
            $_SESSION['swal_error'] = "❌ Gagal memulai transaction database";
        } else {
            foreach ($validData as $log) {
                // Format waktu ke format SQL Server
                $waktu = DateTime::createFromFormat('Y-m-d H:i:s', $log['Waktu']);
                $waktuFormatted = $waktu ? $waktu->format('Y-m-d H:i:s') : $log['Waktu'];
                
                // Cek apakah data sudah ada (hindari duplikasi)
                $checkSql = "SELECT id FROM dbo.log_absensi 
                             WHERE mesin_id = ? AND user_id = ? AND waktu = ?";
                $checkParams = [
                    $selectedMesinId, 
                    $log['UserID'], 
                    $waktuFormatted
                ];
                
                $checkStmt = sqlsrv_query($conn, $checkSql, $checkParams);
                
                if ($checkStmt && !sqlsrv_has_rows($checkStmt)) {
                    // Data belum ada, insert baru
                    $insertSql = "INSERT INTO dbo.log_absensi 
                                 (mesin_id, user_id, nama, waktu, verified, status) 
                                 VALUES (?, ?, ?, ?, ?, ?)";
                    
                    $insertParams = [
                        $selectedMesinId,
                        $log['UserID'],
                        $log['Nama'],
                        $waktuFormatted,
                        $log['Verified'],
                        $log['Status']
                    ];
                    
                    $insertStmt = sqlsrv_query($conn, $insertSql, $insertParams);
                    
                    if ($insertStmt) {
                        $savedCount++;
                    } else {
                        $errorCount++;
                        error_log("Gagal menyimpan log absensi - UserID: " . $log['UserID'] . 
                                 ", Nama: " . $log['Nama'] . 
                                 ", Error: " . print_r(sqlsrv_errors(), true));
                    }
                } else {
                    $duplicateCount++;
                }
            }
            
            // Commit atau rollback transaction
            if ($errorCount == 0) {
                sqlsrv_commit($conn);
                $message = "✅ Berhasil mengambil " . count($logData) . " data log";
                $message .= " • Disimpan: $savedCount record baru";
                if ($duplicateCount > 0) {
                    $message .= " • Duplikat: $duplicateCount record";
                }
                if ($errorCount > 0) {
                    $message .= " • Error: $errorCount record";
                }
                $_SESSION['swal_success'] = $message;
            } else {
                sqlsrv_rollback($conn);
                $_SESSION['swal_error'] = "❌ Gagal menyimpan beberapa data: $errorCount error terjadi. Data rollback.";
            }
        }
    } else {
        $_SESSION['swal_error'] = "❌ Tidak ada data valid untuk disimpan";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Download Log Data Absensi</title>
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <h1>Download Log Data Absensi</h1>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card shadow">
          <div class="card-header bg-<?= htmlspecialchars($themeColor) ?>">
            <h3 class="card-title">Download Log Data Absensi</h3>
            <div class="card-tools">
              <?php if (!empty($logData)): ?>
                <button type="button" id="btnExport" class="btn btn-sm btn-light">
                  <i class="fas fa-download"></i> Export Data
                </button>
              <?php endif; ?>
            </div>
          </div>
          <div class="card-body">
            <!-- FORM UNTUK LOAD LOG -->
            <form method="GET" class="form-inline mb-3">
              <label class="mr-2">Pilih Mesin:</label>
              <select name="mesin_id" id="mesinSelect" class="form-control" required>
                <option value="">-- Pilih Mesin --</option>
                <?php foreach ($mesin as $m): ?>
                  <option value="<?= $m['id'] ?>" <?= ($selectedMesinId == $m['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($m['nama_mesin']) ?> (<?= $m['ip_address'] ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?> ml-2">
                <i class="fas fa-sync"></i> Load Log
              </button>
              <a href="?" class="btn btn-secondary ml-2">
                <i class="fas fa-eraser"></i> Clear Tampilan
              </a>
              
              <!-- TOMBOL CLEAR LOG MESIN -->
              <?php if ($selectedMesinId): ?>
                <button type="button" id="btnClearLog" class="btn btn-danger ml-2" 
                        data-mesin-id="<?= $selectedMesinId ?>" 
                        data-mesin-name="<?= htmlspecialchars($selectedMesin['nama_mesin'] ?? '') ?>">
                  <i class="fas fa-trash"></i> Hapus Log Absensi
                </button>
              <?php endif; ?>
            </form>

            <?php if (!empty($logData)): ?>
              <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> 
                Total data: <?= count($logData) ?> log | 
                UserID dan Nama sudah dipetakan dengan konsisten
              </div>
              <div class="table-responsive">
                <table id="logTable" class="table table-hover table-sm">
                  <thead class="thead-light">
                    <tr>
                      <th>No</th>
                      <th>User ID</th>
                      <th>Nama</th>
                      <th>Waktu</th>
                      <th>Verifikasi</th>
                      <th>Status</th>
                      <th>Original PIN</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($logData as $i => $log): ?>
                      <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($log['UserID']) ?></td>
                        <td><?= htmlspecialchars($log['Nama']) ?></td>
                        <td><?= htmlspecialchars($log['Waktu']) ?></td>
                        <td><?= htmlspecialchars($log['Verified']) ?></td>
                        <td><?= htmlspecialchars($log['Status']) ?></td>
                        <td><small class="text-muted"><?= htmlspecialchars($log['OriginalPIN']) ?></small></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php elseif ($selectedMesinId): ?>
              <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> 
                Tidak ada data log absensi pada mesin ini.
              </div>
            <?php else: ?>
              <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Silakan pilih mesin untuk menampilkan log absensi.
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>
  </div>
</div>

<!-- FORM HIDDEN UNTUK CLEAR LOG -->
<form id="clearLogForm" method="POST" style="display: none;">
  <input type="hidden" name="clear_log" value="1">
  <input type="hidden" name="mesin_id" id="clearLogMesinId">
</form>

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/jquery/jquery.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/js/dataTables.bootstrap5.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>
<script>
$(function() {
  $('#logTable').DataTable({
    responsive: true,
    lengthChange: true,
    autoWidth: false,
    pageLength: 10,
    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Semua"]],
    language: {
      emptyTable: "Tidak ada data log",
      info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ log",
      infoEmpty: "Menampilkan 0 sampai 0 dari 0 log",
      infoFiltered: "(disaring dari _MAX_ total log)",
      search: "Cari:",
      zeroRecords: "Tidak ditemukan data yang sesuai",
      lengthMenu: "Tampilkan _MENU_ data per halaman"
    }
  });

  const showAlert = (type, msg) => Swal.fire({icon: type, title: msg, timer: 2500, showConfirmButton: false});
  
  // Auto load ketika pilih mesin
  $('#mesinSelect').on('change', function() {
    if (this.value) {
      $(this).closest('form').submit();
    }
  });

  // Export Data
  $('#btnExport').on('click', function() {
    const mesinId = $('#mesinSelect').val();
    if (!mesinId) return showAlert('error', 'Pilih mesin terlebih dahulu!');
    showAlert('success', 'Fitur export akan segera tersedia!');
  });

  // Clear Log Mesin
  $('#btnClearLog').on('click', function() {
    const mesinId = $(this).data('mesin-id');
    const mesinName = $(this).data('mesin-name');
    
    Swal.fire({
      title: 'Hapus Log Absensi?',
      html: `Anda yakin ingin menghapus <strong>semua data log absensi</strong> dari mesin:<br>
             <strong>${mesinName}</strong><br><br>
             <small class="text-warning">• Hanya data attendance records yang akan dihapus</small><br>
             <small class="text-success">• Data user dan fingerprint tetap aman</small><br><br>
             <small class="text-danger"><strong>PERHATIAN:</strong> Tindakan ini tidak dapat dibatalkan!</small>`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Ya, Hapus Log Absensi!',
      cancelButtonText: 'Batal',
      width: '600px'
    }).then((result) => {
      if (result.isConfirmed) {
        // Tampilkan loading
        Swal.fire({
          title: 'Menghapus Log...',
          text: 'Sedang menghapus data log absensi dari mesin',
          allowOutsideClick: false,
          didOpen: () => {
            Swal.showLoading();
          }
        });
        
        $('#clearLogMesinId').val(mesinId);
        $('#clearLogForm').submit();
      }
    });
  });

  // Notifikasi session
  <?php if (isset($_SESSION['swal_success'])): ?>
    showAlert('success', '<?= $_SESSION['swal_success'] ?>');
    <?php unset($_SESSION['swal_success']); ?>
  <?php endif; ?>
  <?php if (isset($_SESSION['swal_error'])): ?>
    showAlert('error', '<?= $_SESSION['swal_error'] ?>');
    <?php unset($_SESSION['swal_error']); ?>
  <?php endif; ?>
});
</script>
</body>
</html>