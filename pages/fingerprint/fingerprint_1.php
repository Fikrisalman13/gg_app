<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// CEK USERNAME
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// THEMA
$themeColor = $_SESSION['Theme'] ?? 'primary';
date_default_timezone_set('Asia/Jakarta');

// CEK KONEKSI
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

// CEK MENU ID 
define('FINGERPRINT_MENU_ID', 96);

// CEK USER PERMISI
function checkUserPermissions($conn, $groupId, $menuId) {
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete 
            FROM dbo.SMGroupTrustee 
            WHERE GroupId = ? AND MenuId = ?";
    $params = [$groupId, $menuId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    $permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
        sqlsrv_free_stmt($stmt);
    }
    return $permissions;
}

// AMBIL DATA MESIN
function getAllMesin($conn) {
    $sql = "SELECT * FROM dbo.m_fingerprint ORDER BY id DESC";
    $stmt = sqlsrv_query($conn, $sql);
    $mesin = [];
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $mesin[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
    return $mesin;
}

// CEK HALAMAN PERMISI
$permissions = checkUserPermissions($conn, $_SESSION['GroupId'], FINGERPRINT_MENU_ID);
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}

/**
 * Mengirim request SOAP ke mesin fingerprint untuk mengecek koneksi
 */
function testMesinConnection($ip, $timeout = 5) {
    $xmlRequest = '<?xml version="1.0" encoding="utf-8"?>
    <GetDate><ArgComKey>0</ArgComKey><Arg><PIN>1</PIN></Arg></GetDate>'; // contoh request sederhana

    try {
        $url = "http://$ip/iWsService";
        $headers = [
            "Content-type: text/xml;charset=\"utf-8\"",
            "Accept: text/xml",
            "Cache-Control: no-cache",
            "Pragma: no-cache",
            "SOAPAction: \"\"",
            "Content-length: " . strlen($xmlRequest)
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlRequest);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response && $httpCode == 200) {
            return "Connected";
        } else {
            return "Disconnected";
        }
    } catch (Exception $e) {
        return "Disconnected";
    }
}

// AMBIL MESIN
$mesin = getAllMesin($conn);

ob_end_flush();
?>


<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Daftar Mesin Fingerprint</title>

  <!-- CSS -->
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">

</head>

<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid d-flex justify-content-between">
        <h1>Mesin Fingerprint</h1>
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
          <li class="breadcrumb-item active">Fingerprint</li>
        </ol>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">

        <div class="card">
          <div class="card-header bg-<?= htmlspecialchars($themeColor) ?>">
            <h3 class="card-title">Daftar Mesin Fingerprint</h3>
            <div class="card-tools">
    <?php if ($permissions['CanAdd'] == 1): ?>
        <a href="add_fingerprint.php" class="btn btn-<?= htmlspecialchars($themeColor) ?>">
            <i class="fas fa-plus"></i> Tambah
        </a>
    <?php endif; ?>
    <button id="btn-test-all" class="btn btn-info btn-sm">
        <i class="fas fa-plug"></i> Test Semua Mesin
    </button>
    <button id="btn-sync-all" class="btn btn-success btn-sm">
    <i class="fas fa-clock"></i> Sync Semua Mesin
    </button>


</div>
          </div>
          <div class="card-body">
            <table id="mesinTable" class="table table-hover table-sm">
              <thead class="thead-light">
                <tr>
                  <th>No</th>
                  <th>Nama Mesin</th>          
                  <th>IP Address</th>
                  <th>Comm Key</th>
                  <th>Lokasi</th>
                  <th>Deskripsi</th>
                  <th>Status</th>       
                  <th>Update Terakhir</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
              <?php if (empty($mesin)): ?>
                <tr><td colspan="11" class="text-center">Belum ada mesin fingerprint</td></tr>
              <?php else: ?>
                <?php foreach ($mesin as $i => $row): ?>
                <tr>
                  <td><?= $i+1 ?></td>
                  <td><?= htmlspecialchars($row['nama_mesin']) ?></td>
              
                  <td><?= htmlspecialchars($row['ip_address']) ?></td>
                  <td><?= htmlspecialchars($row['comm_key']) ?></td>
                  <td><?= htmlspecialchars($row['lokasi']) ?></td>
                  <td><?= htmlspecialchars($row['deskripsi']) ?></td>
                  <td><span class="badge badge-secondary status-badge" data-ip="<?= $row['ip_address'] ?>">-</span></td>               
                  <td><?= $row['UpdDate'] ? $row['UpdDate']->format('d-m-Y H:i:s') : '-' ?></td>
                  <td>
                    <?php if ($permissions['CanEdit']==1): ?>
                      <a href="edit_fingerprint.php?id=<?= $row['id'] ?>" class="btn btn-warning btn-sm"><i class="fas fa-edit"></i></a>
                    <?php endif; ?>
                    <?php if ($permissions['CanDelete']==1): ?>
                      <button class="btn btn-danger btn-sm btn-delete" data-id="<?= $row['id'] ?>">
                        <i class="fas fa-trash"></i>
                      </button>
                    <?php endif; ?>
                    <?php if ($permissions['CanEdit']==1): ?>
                      <button class="btn btn-success btn-sm btn-sync" data-ip="<?= $row['ip_address'] ?>" data-key="<?= $row['comm_key'] ?>">
                        <i class="fas fa-clock"></i> Sync Time
                        </button>
                    <?php endif; ?>
                    <?php if ($permissions['CanEdit']==1): ?>
                        <button class="btn btn-primary btn-sm btn-restart" data-ip="<?= $row['ip_address'] ?>" data-key="<?= $row['comm_key'] ?>">
                            <i class="fas fa-redo"></i> Restart
                        </button>
                    <?php endif; ?>
                    <!-- TOMBOL CLEAR DATA PER MESIN -->
    <?php if ($permissions['CanDelete']==1): ?>
        <button class="btn btn-dark btn-sm btn-clear" data-ip="<?= $row['ip_address'] ?>" data-key="<?= $row['comm_key'] ?>">
            <i class="fas fa-broom"></i> Clear Data
        </button>
    <?php endif; ?>

                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </section>
  </div>
</div>

<!-- JS -->
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/js/datatables/dataTables.bootstrap5.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
$(document).ready(function () {
    $('#mesinTable').DataTable({
        responsive: true,
        language: {
            lengthMenu: "Tampilkan _MENU_ data per halaman",
            zeroRecords: "Tidak ada data yang ditemukan",
            info: "Menampilkan halaman _PAGE_ dari _PAGES_",
            infoEmpty: "Tidak ada data tersedia",
            infoFiltered: "(disaring dari _MAX_ total data)",
            search: "Cari:",
            paginate: {
                first: "Pertama",
                last: "Terakhir",
                next: "Selanjutnya",
                previous: "Sebelumnya"
            }
        }
    });

    <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Sukses!',
            text: "<?= $_SESSION['success'] ?>",
            timer: 3000,
            showConfirmButton: false
        });
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: "<?= $_SESSION['error'] ?>",
            timer: 3000,
            showConfirmButton: false
        });
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    // Hapus mesin
    $(document).on('click', '.btn-delete', function () {
        let mesinId = $(this).data('id');
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: 'Mesin akan dihapus secara permanen!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'delete_fingerprint.php',
                    type: 'POST',
                    data: { id: mesinId },
                    success: function () {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: 'Mesin telah dihapus.',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    },
                    error: function () {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            text: 'Terjadi kesalahan saat menghapus mesin.'
                        });
                    }
                });
            }
        });
    });
    
    function updateStatus(ip, element) {
    $.ajax({
        url: 'test_connection.php',
        type: 'POST',
        data: { ip: ip },
        success: function(resp) {
            $(element)
                .text(resp.status)
                .removeClass('badge-secondary badge-success badge-danger')
                .addClass(resp.status == 'Connected' ? 'badge-success' : 'badge-danger');
        },
        error: function() {
            $(element)
                .text('Disconnected')
                .removeClass('badge-secondary badge-success')
                .addClass('badge-danger');
        }
    });
}

$('#btn-test-all').click(function() {
    Swal.fire({
        title: 'Menguji koneksi semua mesin...',
        didOpen: () => { Swal.showLoading() },
        allowOutsideClick: false
    });

    $('.status-badge').each(function() {
        let ip = $(this).data('ip');
        let badge = this;
        updateStatus(ip, badge);
    });

    setTimeout(() => Swal.close(), 1000);
});

// Fungsi sinkronisasi mesin
function syncMesin(ip, key, callback){
    Swal.fire({
        title: 'Sinkronisasi waktu...',
        html: 'IP: '+ip,
        didOpen: () => { Swal.showLoading() },
        allowOutsideClick: false
    });

    $.post('sync_time.php', {ip: ip, key: key}, function(resp){
        Swal.close();
        if(resp.status === 'success'){
            Swal.fire({
                icon: 'success',
                title: 'Sukses!',
                html: `
                    IP: <b>${ip}</b><br>
                    Sebelum: <b>${resp.before_time}</b><br>
                    Sesudah: <b>${resp.after_time}</b><br>
                    Result: ${resp.message}
                `
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                html: `
                    IP: <b>${ip}</b><br>
                    Message: ${resp.message}
                `
            });
        }

        if(typeof callback === 'function') callback();
    }, 'json');
}

// Fungsi sinkronisasi mesin tunggal dengan cek status
function syncMesin(ip, key){
    Swal.fire({
        title: 'Mengecek koneksi mesin...',
        html: 'IP: '+ip,
        didOpen: () => { Swal.showLoading() },
        allowOutsideClick: false
    });

    // Cek status mesin
    $.post('test_connection.php', {ip: ip}, function(resp){
        if(resp.status === 'Connected'){
            // Mesin online → sinkron waktu
            $.post('sync_time.php', {ip: ip, key: key}, function(syncResp){
                Swal.close();
                if(syncResp.status === 'success'){
                    Swal.fire({
                        icon: 'success',
                        title: 'Sukses!',
                        html: `
                            IP: <b>${ip}</b><br>
                            Sebelum: <b>${syncResp.before_time}</b><br>
                            Sesudah: <b>${syncResp.after_time}</b><br>
                            Result: ${syncResp.message}
                        `
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        html: `
                            IP: <b>${ip}</b><br>
                            Message: ${syncResp.message}
                        `
                    });
                }
            }, 'json');
        } else {
            // Mesin offline → skip
            Swal.close();
            Swal.fire({
                icon: 'warning',
                title: 'Mesin Offline',
                html: `IP: <b>${ip}</b><br>Mesin offline, sinkronisasi dibatalkan.`
            });
        }
    }, 'json');
}

// Tombol sync mesin tunggal
$(document).on('click', '.btn-sync', function(){
    let ip = $(this).data('ip');
    let key = $(this).data('key');
    syncMesin(ip, key);
});


// Tombol sync semua mesin
$('#btn-sync-all').click(function(){
    let mesinButtons = $('.btn-sync');
    let total = mesinButtons.length;
    let completed = 0;
    let results = [];

    if(total === 0){
        Swal.fire('Info', 'Belum ada mesin untuk disinkronisasi', 'info');
        return;
    }

    Swal.fire({
        title: 'Sinkronisasi semua mesin...',
        html: `0 / ${total} selesai`,
        didOpen: () => { Swal.showLoading() },
        allowOutsideClick: false
    });

    mesinButtons.each(function(){
        let btn = $(this);
        let ip = btn.data('ip');
        let key = btn.data('key');
        let badge = btn.closest('tr').find('.status-badge');

        // Cek status mesin
        $.post('test_connection.php', {ip: ip}, function(resp){
            if(resp.status === 'Connected'){
                // Sinkron waktu
                $.post('sync_time.php', {ip: ip, key: key}, function(syncResp){
                    results.push({
                        ip: ip,
                        before: syncResp.before_time,
                        after: syncResp.after_time,
                        message: syncResp.message
                    });
                    completed++;
                    Swal.update({html: `${completed} / ${total} selesai`});

                    if(completed === total){
                        Swal.close();
                        showSyncResults(results, total);
                    }
                }, 'json');
            } else {
                // Mesin offline, skip
                results.push({
                    ip: ip,
                    before: '-',
                    after: '-',
                    message: 'Mesin offline, tidak disinkron'
                });
                completed++;
                Swal.update({html: `${completed} / ${total} selesai`});

                if(completed === total){
                    Swal.close();
                    showSyncResults(results, total);
                }
            }
        }, 'json');
    });
});

// Fungsi menampilkan hasil semua mesin
function showSyncResults(results, total){
    let html = '<table class="table table-bordered"><thead><tr><th>IP</th><th>Sebelum</th><th>Sesudah</th><th>Status</th></tr></thead><tbody>';
    results.forEach(r=>{
        html += `<tr>
                    <td>${r.ip}</td>
                    <td>${r.before}</td>
                    <td>${r.after}</td>
                    <td>${r.message}</td>
                 </tr>`;
    });
    html += '</tbody></table>';

    Swal.fire({
        icon: 'info',
        title: 'Hasil Sinkronisasi',
        html: html,
        width: '600px'
    });
}
// Tombol restart mesin tunggal
$(document).on('click', '.btn-restart', function(){
    let ip = $(this).data('ip');
    let key = $(this).data('key');

    Swal.fire({
        title: 'Mengecek koneksi mesin...',
        html: 'IP: '+ip,
        didOpen: () => { Swal.showLoading() },
        allowOutsideClick: false
    });

    // Cek status mesin
    $.post('test_connection.php', {ip: ip}, function(resp){
        if(resp.status === 'Connected'){
            // Mesin online → restart
            $.post('restart_fingerprint.php', {ip: ip, key: key}, function(r){
                Swal.close();
                if(r.status === 'success'){
                    Swal.fire({
                        icon: 'success',
                        title: 'Restart Berhasil',
                        html: `IP: <b>${ip}</b><br>Result: ${r.message}`
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Restart Gagal',
                        html: `IP: <b>${ip}</b><br>Message: ${r.message}`
                    });
                }
            }, 'json');
        } else {
            Swal.close();
            Swal.fire({
                icon: 'warning',
                title: 'Mesin Offline',
                html: `IP: <b>${ip}</b><br>Mesin offline, restart dibatalkan.`
            });
        }
    }, 'json');
});
// Fungsi clear data untuk mesin tunggal
function clearMesinData(ip, key) {
    Swal.fire({
        title: 'Clear All Data',
        html: `IP: <b>${ip}</b>`,
        text: "Semua data (user, log, template) akan dihapus permanen! Lanjutkan?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus Semua Data!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Menghapus semua data...',
                html: `IP: ${ip}`,
                didOpen: () => { Swal.showLoading() },
                allowOutsideClick: false
            });

            $.post('clear_data.php', {
                action: 'clear_single',
                ip: ip,
                key: key
            }, function(resp) {
                Swal.close();
                
                const icon = resp.status === 'success' ? 'success' : 'error';
                const title = resp.status === 'success' ? 'Berhasil!' : 'Gagal!';
                
                Swal.fire({
                    icon: icon,
                    title: title,
                    html: `IP: <b>${ip}</b><br>${resp.message}`,
                    width: '500px'
                });
            }, 'json');
        }
    });
}

// Tombol clear data per mesin
$(document).on('click', '.btn-clear', function(){
    let ip = $(this).data('ip');
    let key = $(this).data('key');
    clearMesinData(ip, key);
});

// Tombol clear all data untuk semua mesin
$('#btn-clear-all').click(function(){
    Swal.fire({
        title: 'Clear All Data',
        text: "Semua data di SEMUA mesin akan dihapus permanen!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus Semua!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Menghapus semua data...',
                text: 'Menghapus data dari semua mesin',
                didOpen: () => { Swal.showLoading() },
                allowOutsideClick: false
            });

            $.post('clear_data.php', {
                action: 'clear_all_mesin'
            }, function(resp) {
                Swal.close();
                
                if(resp.status === 'completed') {
                    let html = '<table class="table table-bordered table-sm"><thead><tr><th>IP</th><th>Status</th><th>Message</th></tr></thead><tbody>';
                    resp.results.forEach(r => {
                        const statusClass = r.status === 'success' ? 'text-success' : 'text-danger';
                        const statusIcon = r.status === 'success' ? '✓' : '✗';
                        html += `<tr>
                            <td>${r.ip}</td>
                            <td class="${statusClass}"><b>${statusIcon} ${r.status}</b></td>
                            <td>${r.message}</td>
                        </tr>`;
                    });
                    html += '</tbody></table>';

                    Swal.fire({
                        icon: 'info',
                        title: 'Hasil Clear Data',
                        html: html,
                        width: '700px'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: resp.message
                    });
                }
            }, 'json');
        }
    });
});

});
</script>
</body>
</html>
