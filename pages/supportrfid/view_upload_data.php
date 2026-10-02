<?php
session_start();
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/includes/access_helper.php';

$username = $_SESSION['username'] ?? '';
$groupId = getUserGroup($username);

// 🔐 Cek hak akses ke halaman (contoh: upload)
if (!canAccessMenu($groupId, 'riwayatupload')) {
    echo "<div class='container'><h3 style='color:red;text-align:center;'>🚫 Anda tidak memiliki hak akses ke halaman ini.</h3></div>";
    exit; // hentikan eksekusi halaman
}

// ✅ Hanya jika lolos cek akses, include sidebar
include BASE_PATH . '/sidebar.php';



// ================== BACA SEMUA FILE LOG DI FOLDER logs ==================
$logDir = __DIR__ . '/logs';
$logFiles = glob($logDir . '/upload_logs_*.json');
$allLogs = [];

// Baca semua data log
if ($logFiles) {
    foreach ($logFiles as $file) {
        $json = file_get_contents($file);
        $logs = json_decode($json, true);
        if (is_array($logs)) {
            foreach ($logs as $entry) {
                if (!empty($entry['data'])) {
                    foreach ($entry['data'] as $d) {
                        $d['_meta_user'] = $entry['user'] ?? 'anonymous';
                        $d['_meta_date'] = $entry['timestamp'] ?? '-';
                        $allLogs[] = $d;
                    }
                }
            }
        }
    }
}

// ================== DELETE DATA DARI DATABASE & FILE LOG ==================
require 'vendor/autoload.php';
use MongoDB\Client;
use MongoDB\BSON\ObjectId;

$client = new Client("mongodb://doitAdmin:doitIntegra.sys@192.168.7.5:27017");
$db = $client->selectDatabase("api_sum");
$collection = $db->selectCollection("picking_process");
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = trim($_POST['delete_id']);
    $notifType = 'error';
    $notifText = '';

    if (!empty($deleteId) && preg_match('/^[a-f\d]{24}$/i', $deleteId)) {
        try {
            $deleteResult = $collection->deleteOne(['_id' => new ObjectId($deleteId)]);

            if ($deleteResult->getDeletedCount() > 0) {
                // 🔹 Hapus juga dari file log
                foreach ($logFiles as $file) {
                    $json = file_get_contents($file);
                    $logs = json_decode($json, true);
                    if (is_array($logs)) {
                        foreach ($logs as &$entry) {
                            if (!empty($entry['data'])) {
                                $entry['data'] = array_filter($entry['data'], function ($item) use ($deleteId) {
                                    return ($item['_id']['$oid'] ?? '') !== $deleteId;
                                });
                            }
                        }
                        $logs = array_values(array_filter($logs, fn($e) => !empty($e['data'])));
                        file_put_contents($file, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    }
                }

                $notifType = 'success';
                $notifText = "✅ Data Berhasil Dihapus Dari Database Dan Log!";
            } else {
                $notifType = 'error';
                $notifText = "❌ Data Tidak Ditemukan Di Database.";
            }
        } catch (Exception $e) {
            $notifType = 'error';
            $notifText = "⚠️ Error: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $notifType = 'error';
        $notifText = "❌ ID tidak valid.";
    }

    // 🔹 Output notifikasi gelembung di tengah
    echo "
    <div class='bubble-center $notifType' id='bubbleNotif'>
        $notifText
    </div>
    <script>
      const bubble = document.getElementById('bubbleNotif');
      if (bubble) {
        bubble.classList.add('show');
        setTimeout(() => {
          bubble.classList.remove('show');
        }, 2000);
        setTimeout(() => {
          window.location = 'view_upload_data.php';
        }, 2300);
      }
    </script>
    ";
}


// ================== FILTER PENCARIAN DAN TANGGAL ==================
$search = $_GET['search'] ?? '';
$startDate = $_GET['tgl_mulai'] ?? '';
$endDate = $_GET['tgl_selesai'] ?? '';

$filteredLogs = array_filter($allLogs, function ($item) use ($search, $startDate, $endDate) {
    $match = true;

    // Filter teks
    if ($search) {
        $searchLower = strtolower($search);
        $text = strtolower(json_encode($item));
        if (strpos($text, $searchLower) === false) {
            $match = false;
        }
    }

    // Filter tanggal
    if ($startDate || $endDate) {
        $date = strtotime($item['_meta_date'] ?? '');
        if ($startDate && $date < strtotime($startDate . ' 00:00:00')) $match = false;
        if ($endDate && $date > strtotime($endDate . ' 23:59:59')) $match = false;
    }

    return $match;
});

// ================== PAGINATION ==================
$perPage = 20;
$totalData = count($filteredLogs);
$totalPages = ceil($totalData / $perPage);
$page = max(1, intval($_GET['page'] ?? 1));
$start = ($page - 1) * $perPage;
$logsToShow = array_slice($filteredLogs, $start, $perPage);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lihat Data Upload (Dari Logs)</title>
<style>
body {
  font-family: Arial, sans-serif;
  background: #f1f5fb;
  color: #333;
}
.container {
  margin: 40px auto;
  width: 95%;
  max-width: 1400px;
  background: white;
  padding: 20px 30px;
  border-radius: 10px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}
h2 { color: #1565c0; text-align: center; margin-bottom: 20px; }

.filter-box {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 15px;
  align-items: center;
}
.filter-box input[type="text"],
.filter-box input[type="date"] {
  padding: 6px 10px;
  border: 1px solid #ccc;
  border-radius: 6px;
}
.filter-box button {
  background: #1565c0;
  color: white;
  border: none;
  padding: 6px 12px;
  border-radius: 6px;
  cursor: pointer;
}
.filter-box button:hover { background: #0d47a1; }

table { width: 100%; border-collapse: collapse; font-size: 14px; }
thead { background: #1565c0; color: white; }
th, td { padding: 10px 8px; border: 1px solid #ddd; text-align: center; }
tr:nth-child(even) { background: #f9f9f9; }
button.delete {
  background: #e53935;
  color: white;
  border: none;
  padding: 6px 10px;
  border-radius: 4px;
  cursor: pointer;
}
button.delete:hover { background: #b71c1c; }

.notif {
  text-align: center;
  margin-bottom: 10px;
  padding: 10px;
  border-radius: 6px;
}
.notif.success { background: #c8e6c9; color: #256029; }
.notif.error { background: #ffcdd2; color: #b71c1c; }

.pagination {
  margin-top: 15px;
  text-align: center;
}
.pagination a {
  margin: 0 4px;
  padding: 6px 10px;
  background: #eee;
  border-radius: 4px;
  text-decoration: none;
  color: #1565c0;
}
.pagination a.active {
  background: #1565c0;
  color: white;
}


/* Notifikasi */
.notif {
  text-align: center;
  padding: 10px 15px;
  border-radius: 8px;
  margin-bottom: 15px;
  font-weight: 500;
  width: 60%;
  margin-left: auto;
  margin-right: auto;
}
.notif.success { background: #c8e6c9; color: #256029; }
.notif.error { background: #ffcdd2; color: #b71c1c; }

/* Tabel data */
.data-table {
  background: rgba(255,255,255,0.95);
  border-radius: 15px;
  box-shadow: 0 8px 20px rgba(33,150,243,0.2);
  overflow-x: auto;
  padding: 20px;
  max-width: 95%;
  margin: 0 auto;
}

.data-table table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

.data-table th {
  background: #1976d2;
  color: white;
  padding: 10px;
  position: sticky;
  top: 0;
}

.data-table td {
  padding: 8px 10px;
  border-bottom: 1px solid #e0e0e0;
  background: #fff;
}

.data-table tr:hover td {
  background: #e3f2fd;
  transition: background 0.3s;
}

/* Tombol hapus */
.container button {
  background: linear-gradient(45deg, #f44336, #ef5350);
  color: white;
  border: none;
  border-radius: 6px;
  padding: 6px 12px;
  cursor: pointer;
  font-size: 13px;
  transition: all 0.25s ease;
}
.container button:hover {
  transform: scale(1.05);
  background: linear-gradient(45deg, #e53935, #ef9a9a);
}

body {
    font-family: 'Segoe UI', Tahoma, sans-serif;
    background: linear-gradient(135deg, #e3f2fd, #e8f5e9);
    margin: 0;
    padding: 0;
    overflow-x: hidden;
}

.content {
    margin-left: 240px;
    padding: 25px;
    animation: fadeIn 0.6s ease-in-out;
	margin-top: 30px;
}

.container {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
    max-width: 95%;
    margin: 25px auto;
    animation: slideUp 0.8s ease;
	
}


h2 {
    color: #1565c0;
    text-align: center;
    margin-bottom: 25px;
    font-weight: 600;
    letter-spacing: 0.5px;
}

/* ==== FILTER FORM ==== */
.filter-form {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    background: #f6f9fc;
    border-left: 5px solid #2196F3;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
    animation: fadeIn 0.8s ease;
}
.filter-form input[type="text"], 
.filter-form input[type="date"] {
    padding: 8px 10px;
    border: 1px solid #cfd8dc;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.3s ease;
}
.filter-form button {
    background: linear-gradient(90deg, #2196F3, #42a5f5);
    color: white;
    border: none;
    border-radius: 6px;
    padding: 8px 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 36px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
}
.filter-form button:hover {
    background: linear-gradient(90deg, #1976d2, #2196F3);
    transform: translateY(-1px);
}

.date-inline {
    display: flex;
    align-items: center;
    gap: 6px;
}

/* ==== TABEL ==== */
.table-wrapper {
    overflow-x: auto;
    animation: fadeIn 0.6s ease;
}
table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}
th, td {
    padding: 10px 12px;
    border-bottom: 1px solid #eee;
    text-align: left;
    white-space: nowrap;
}
th {
    background: linear-gradient(90deg, #1976d2, #2196f3);
    color: white;
}
tr:hover {
    background: #f1f9ff;
}

/* ==== PAGINATION ==== */
.pagination {
    text-align: center;
    margin-top: 20px;
}
.pagination a {
    display: inline-block;
    margin: 0 4px;
    padding: 8px 14px;
    background: #2196F3;
    color: white;
    border-radius: 6px;
    text-decoration: none;
    transition: all 0.3s ease;
}
.pagination a.active {
    background: #0d47a1;
    transform: scale(1.1);
}
.pagination a:hover {
    background: #1565c0;
}

/* ==== ANIMASI ==== */
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px);} to {opacity: 1; transform: translateY(0);} }
@keyframes slideUp { from { opacity: 0; transform: translateY(30px);} to {opacity: 1; transform: translateY(0);} }

/* ==== CARD VIEW (MOBILE) ==== */
@media (max-width: 768px) {
    .content { 
        margin-left: 0; 
        padding: 10px; 
        margin-top: 70px;
    }

    .container { 
        padding: 15px; 
        margin-top: 10px; 
    }

    .filter-form { 
        flex-direction: column; 
        align-items: stretch; 
    }

    .filter-form button { 
        width: 100%; 
    }

    /* ==== CARD VIEW ==== */
    .table-wrapper table,
    .table-wrapper thead,
    .table-wrapper tbody,
    .table-wrapper th,
    .table-wrapper td,
    .table-wrapper tr {
        display: block;
        width: 100%;
    }

    .table-wrapper thead { 
        display: none; 
    }

    .table-wrapper tr {
        background: #fff;
        margin-bottom: 15px;
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        padding: 12px;
    }

    .table-wrapper td {
        border: none;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 8px 10px;
        font-size: 13px;
        border-bottom: 1px solid #eee;
        word-wrap: break-word;       /* ✅ teks panjang bisa pecah */
        overflow-wrap: break-word;   /* ✅ agar teks panjang tidak tembus */
        white-space: normal;         /* ✅ biar bisa multi-baris */
    }

    .table-wrapper td:last-child { 
        border-bottom: none; 
    }

    .table-wrapper td::before {
        content: attr(data-label);
        font-weight: 600;
        color: #1976d2;
        text-transform: capitalize;
        margin-right: 8px;
        min-width: 130px;
        flex-shrink: 0;
    }
}@media (max-width: 768px) {
    .content { 
        margin-left: 0; 
        padding: 10px; 
        margin-top: 80px;
    }

    .container { 
        padding: 15px; 
        margin-top: 15px;
    }

    /* === FILTER FORM (Perbaikan tombol sejajar) === */
    .filter-form { 
        flex-direction: column;
        align-items: stretch;
    }

    .filter-buttons {
        display: flex;
        gap: 10px;
        justify-content: space-between;
        margin-top: 5px;
    }

    .filter-buttons button {
        flex: 1;
    }

    /* === CARD VIEW === */
    .table-wrapper {
        overflow-x: hidden; /* ✅ cegah geser kanan */
    }

    .table-wrapper table,
    .table-wrapper thead,
    .table-wrapper tbody,
    .table-wrapper th,
    .table-wrapper td,
    .table-wrapper tr {
        display: block;
        width: 100%;
        box-sizing: border-box;
    }

    .table-wrapper thead { 
        display: none; 
    }

    .table-wrapper tr {
        background: #fff;
        margin-bottom: 15px;
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        padding: 10px;
        overflow: hidden;
        word-wrap: break-word;
    }

    .table-wrapper td {
        border: none;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 6px 10px;
        font-size: 13px;
        border-bottom: 1px solid #eee;
        white-space: normal !important;   /* ✅ teks bisa turun baris */
        word-wrap: break-word !important; /* ✅ potong teks panjang */
        overflow-wrap: anywhere !important;
        max-width: 100% !important;       /* ✅ batasi lebar */
    }

    .table-wrapper td:last-child { 
        border-bottom: none; 
    }

    .table-wrapper td::before {
        content: attr(data-label);
        font-weight: 600;
        color: #1976d2;
        text-transform: capitalize;
        margin-right: 8px;
        min-width: 120px;
        flex-shrink: 0;
    }
}



/* ==== FIX POSISI DI MOBILE (lebih turun dari topbar) ==== */
@media (max-width: 768px) {
    .content {
        margin: 0 !important;
        padding: 50px 16px 60px 16px !important; /* ⬅️ dari 80px jadi 100px */
        background: linear-gradient(180deg, #e3f2fd, #ffffff);
    }

    .container {
        margin-top: 30px !important; /* ⬅️ tambah jarak */
        padding: 18px !important;
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 4px 10px rgba(0,0,0,0.08);
    }

    h2 {
        margin-top: 10px !important; /* ⬅️ turun sedikit lagi */
        font-size: 18px;
        text-align: center;
        line-height: 1.4;
        color: #1976d2;
    }
}

/* === FILTER FORM === */
.filter-form {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    background: #f6f9fc;
    border-left: 5px solid #2196F3;
    padding: 12px 14px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.filter-form input[type="text"],
.filter-form input[type="date"] {
    padding: 8px 10px;
    border: 1px solid #cfd8dc;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.3s ease;
    height: 38px;
}

.filter-form input:focus {
    border-color: #42a5f5;
    box-shadow: 0 0 6px rgba(66,165,245,0.4);
    outline: none;
}

.date-inline {
    display: flex;
    align-items: center;
    gap: 6px;
}

.date-inline label {
    font-size: 13px;
    color: #555;
    white-space: nowrap;
}

/* Tombol sejajar di bar atas */
.filter-btn,
.reset-btn {
    background: linear-gradient(90deg, #2196F3, #42a5f5);
    color: white;
    border: none;
    border-radius: 6px;
    padding: 8px 14px;
    cursor: pointer;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    transition: all 0.25s ease;
}
.filter-btn:hover,
.reset-btn:hover {
    background: linear-gradient(90deg, #1976d2, #2196F3);
    transform: translateY(-1px);
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-form input,
    .filter-btn,
    .reset-btn {
        width: 100%;
    }
}

/* ==== TABEL ==== */
.table-wrapper {
    overflow-x: auto; /* Memungkinkan scroll horizontal jika data terlalu lebar */
    animation: fadeIn 0.6s ease;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

th, td {
    padding: 10px 12px;
    border-bottom: 1px solid #eee;
    text-align: left;
    white-space: normal; /* Agar teks bisa turun ke bawah (break) */
    word-wrap: break-word;  /* Agar kata panjang dipotong ke bawah */
    overflow-wrap: break-word; /* Menjamin teks panjang tidak keluar dari cell */
}

th {
    background: linear-gradient(90deg, #1976d2, #2196f3);
    color: white;
}

tr:hover {
    background: #f1f9ff;
}

/* Jika teks sangat panjang, kita akan batasi lebar kolom dan membuatnya wrap */
td {
    max-width: 200px; /* Batas lebar kolom */
    word-wrap: break-word; /* Agar kata panjang dibungkus ke bawah */
    overflow-wrap: break-word;
}
/* 💫 Gaya gelembung animasi */
.popup-bubble {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.3);
  justify-content: center;
  align-items: center;
  z-index: 9999;
  animation: fadeIn 0.3s ease forwards;
}
.popup-bubble.show {
  display: flex;
}

.popup-content {
  background: white;
  padding: 20px 25px;
  border-radius: 16px;
  box-shadow: 0 8px 25px rgba(0,0,0,0.2);
  text-align: center;
  transform: scale(0.8);
  opacity: 0;
  animation: popUp 0.25s ease forwards;
}

.popup-text {
  font-size: 16px;
  margin-bottom: 15px;
}

.popup-actions button {
  margin: 0 8px;
  padding: 8px 16px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.yes-btn {
  background: #e74c3c;
  color: white;
}
.no-btn {
  background: #ccc;
  color: #333;
}
.yes-btn:hover {
  background: #c0392b;
}
.no-btn:hover {
  background: #aaa;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
@keyframes popUp {
  to {
    opacity: 1;
    transform: scale(1);
  }
}

/* 🌈 Notifikasi gelembung di tengah layar */
.bubble-center {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%) scale(0.9);
  background: #333;
  color: white;
  padding: 20px 30px;
  border-radius: 12px;
  font-size: 15px;
  font-weight: 500;
  box-shadow: 0 10px 25px rgba(0,0,0,0.25);
  text-align: center;
  line-height: 1.5;
  opacity: 0;
  transition: all 0.4s ease;
  z-index: 9999;
}
.bubble-center.show {
  opacity: 1;
  transform: translate(-50%, -50%) scale(1);
}
.bubble-center.success {
  background: linear-gradient(135deg, #4CAF50, #43A047);
}
.bubble-center.error {
  background: linear-gradient(135deg, #e53935, #c62828);
}


</style>
</head>
<body>
<div class="content">
<div class="container">
  <h2>📄 Riwayat Upload Data</h2>

  <?php if (isset($message)) echo $message; ?>

<form method="get" class="filter-form">
    <input type="text" name="search" placeholder="🔍 Cari data..." value="<?= htmlspecialchars($search) ?>">
    <div class="date-inline">
        <label>🗓️ Tanggal Mulai:</label>
        <input type="date" name="tgl_mulai" value="<?= htmlspecialchars($startDate) ?>">
    </div>

    <div class="date-inline">
        <label>📅 Tanggal Akhir:</label>
        <input type="date" name="tgl_selesai" value="<?= htmlspecialchars($endDate) ?>">
    </div>
    <button type="submit">🔍 Filter</button>
    <button type="button" class="reset-btn" onclick="window.location='view_upload_data.php'">🔄 Reset</button>
	<?php if ($username === 'IT7'): ?>
    <button type="button" id="deleteLogsButton" class="delete-btn">🗑️ Hapus Semua Log</button>
<?php endif; ?>
  </form>
<div class="table-wrapper">
  <table>
    <thead>
      <tr>
        <th>Tanggal Upload</th>
        <th>User</th>
        <th>RFID Code</th>
        <th>TID</th>
        <th>Baleno</th>
        <th>Packing No</th>
        <th>Batch No</th>
       <?php if (canAccessButton($groupId, 'delete')): ?>
        <th>Aksi</th>
		<?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($logsToShow)): ?>
        <tr><td colspan="8" style="text-align:center;color:gray;">Tidak ada data ditemukan.</td></tr>
      <?php else: ?>
        <?php foreach ($logsToShow as $log): ?>
          <?php $oid = $log['_id']['$oid'] ?? ''; ?>
          <tr>
            <td data-label="Tanggal Upload"><?= htmlspecialchars($log['_meta_date'] ?? '-'); ?></td>
            <td data-label="User"><?= htmlspecialchars($log['_meta_user'] ?? 'anonymous'); ?></td>
            <td data-label="Rfid Code"><?= htmlspecialchars($log['rfidcode'] ?? ''); ?></td>
            <td data-label="Tid"><?= htmlspecialchars($log['tid'] ?? ''); ?></td>
            <td data-label="Baleno"><?= htmlspecialchars($log['baleno'] ?? ''); ?></td>
            <td data-label="Packing No"><?= htmlspecialchars($log['packingno'] ?? ''); ?></td>
            <td data-label="Batch No"><?= htmlspecialchars($log['batchno'] ?? ''); ?></td>
			
           <?php if (canAccessButton($groupId, 'delete')): ?>
           <td>
  <?php if ($oid): ?>
    <form action="" method="POST" class="delete-form">
      <input type="hidden" name="delete_id" value="<?= htmlspecialchars($oid); ?>">
      <button type="button" class="delete" onclick="showDeleteConfirm(this)">🗑️ Hapus</button>
    </form>
  <?php else: ?>
    <span style="color:#999;">(Tanpa ID)</span>
  <?php endif; ?>
</td>
<?php endif; ?>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

</div>
</div>



<!-- 🟢 POPUP KONFIRMASI HAPUS -->
<div id="deleteConfirmPopup" class="popup-bubble">
  <div class="popup-content">
    <div class="popup-text">Yakin Ingin Menghapus Data Ini Dari Database ?</div>
    <div class="popup-actions">
      <button id="confirmYes" class="yes-btn">Ya</button>
      <button id="confirmNo" class="no-btn">Batal</button>
    </div>
  </div>
</div>


</body>
</html>

<script>
document.getElementById('deleteLogsButton').addEventListener('click', function() {
    // Ambil tanggal mulai dan tanggal selesai dari filter
    var tglMulai = document.querySelector('[name="tgl_mulai"]').value;
    var tglSelesai = document.querySelector('[name="tgl_selesai"]').value;

    // Cek apakah tanggal mulai dan akhir sudah dipilih
    if (!tglMulai || !tglSelesai) {
        // Jika tidak ada tanggal yang dipilih, tampilkan notifikasi untuk menghapus semua logs
        if (confirm('Jika Tidak Memilih Tanggal Mulai Dan Akhir Maka Semua Logs Akan Dihapus. Apa Anda Sudah Yakin?')) {
            // Kirim request ke delete_upload.php dengan data tanggal mulai dan selesai kosong
            var formData = new FormData();
            formData.append('tgl_mulai', '');  // Kosongkan nilai tanggal mulai
            formData.append('tgl_selesai', '');  // Kosongkan nilai tanggal selesai

            fetch('delete_upload.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                alert('Semua logs berhasil dihapus.');
                location.reload();  // Refresh halaman setelah penghapusan
            })
            .catch(error => {
                alert('Terjadi kesalahan saat menghapus log.');
            });
        }
    } else {
        // Jika tanggal mulai dan akhir sudah dipilih, tampilkan notifikasi untuk menghapus berdasarkan tanggal
        if (confirm('Apakah Anda yakin ingin menghapus logs antara ' + tglMulai + ' dan ' + tglSelesai + '?')) {
            // Kirim request ke delete_upload.php dengan data tanggal mulai dan selesai yang dipilih
            var formData = new FormData();
            formData.append('tgl_mulai', tglMulai);
            formData.append('tgl_selesai', tglSelesai);

            fetch('delete_upload.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                alert('Logs berhasil dihapus.');
                location.reload();  // Refresh halaman setelah penghapusan
            })
            .catch(error => {
                alert('Terjadi kesalahan saat menghapus log.');
            });
        }
    }
});

let targetForm = null;

function showDeleteConfirm(btn) {
  targetForm = btn.closest('.delete-form');
  document.getElementById('deleteConfirmPopup').classList.add('show');
}

document.getElementById('confirmYes').addEventListener('click', ()=>{
  if (targetForm) targetForm.submit();
  document.getElementById('deleteConfirmPopup').classList.remove('show');
});

document.getElementById('confirmNo').addEventListener('click', ()=>{
  document.getElementById('deleteConfirmPopup').classList.remove('show');
});

</script>