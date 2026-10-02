<?php
require 'vendor/autoload.php';
require 'auth_check.php';

//hak akses
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/includes/access_helper.php';

$username = $_SESSION['username'];
$groupId = getUserGroup($username);

// 🔐 Cek hak akses ke halaman (contoh: update_multi)
if (!canAccessMenu($groupId, 'riwayatgudang')) {
    echo "<div class='container'><h3 style='color:red;text-align:center;'>🚫 Anda tidak memiliki hak akses ke halaman ini.</h3></div>";
    exit;
}


// === KONFIGURASI ===
$logDir = __DIR__ . '/logs';
$perPage = 100;

// === FILTER INPUT ===
$userFilter   = $_GET['user'] ?? '';
$gudangFilter = $_GET['gudang'] ?? '';
$tglMulai     = $_GET['tgl_mulai'] ?? '';
$tglSelesai   = $_GET['tgl_selesai'] ?? '';
$searchQuery  = trim($_GET['search'] ?? '');
$page         = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// === BACA SEMUA FILE LOG ===
$logFiles = glob($logDir . '/update_log_*.json');
$logs = [];

foreach ($logFiles as $file) {
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $log = json_decode($line, true);
        if ($log) $logs[] = $log;
    }
}

// === URUTKAN DARI TERBARU ===
usort($logs, fn($a, $b) => strtotime($b['timestamp'] ?? '0') <=> strtotime($a['timestamp'] ?? '0'));

// === FILTER DATA ===
$filteredLogs = array_filter($logs, function($log) use ($userFilter, $gudangFilter, $tglMulai, $tglSelesai, $searchQuery) {
    $ok = true;
    if ($userFilter && ($log['user_session'] ?? '') !== $userFilter) $ok = false;
    if ($gudangFilter && (($log['updated_to']['wrhsname'] ?? '') !== $gudangFilter)) $ok = false;

    if ($tglMulai && $tglSelesai && isset($log['timestamp'])) {
        $ts = strtotime($log['timestamp']);
        $start = strtotime("$tglMulai 00:00:00");
        $end   = strtotime("$tglSelesai 23:59:59");
        if ($ts < $start || $ts > $end) $ok = false;
    }

    if ($searchQuery) {
        $haystack = strtolower(implode(' ', [
            $log['identifier'] ?? '',
            $log['updated_from']['wrhsname'] ?? '',
            $log['updated_to']['wrhsname'] ?? '',
            $log['user_session'] ?? '',
            $log['description'] ?? '',
            $log['ip_address'] ?? '',
        ]));
        if (strpos($haystack, strtolower($searchQuery)) === false) $ok = false;
    }

    return $ok;
});

$filteredLogs = array_values($filteredLogs);

// === PAGINATION ===
$totalLogs = count($filteredLogs);
$totalPages = max(1, ceil($totalLogs / $perPage));
$page = min($page, $totalPages);
$startIndex = ($page - 1) * $perPage;
$pagedLogs = array_slice($filteredLogs, $startIndex, $perPage);
?>


<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Riwayat Update Gudang</title>
<style>
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


</style>
</head>

<body>
<?php include 'sidebar.php'; ?>

<div class="content">
<div class="container">
<h2>🕒 Riwayat Update Data Gudang (Log Aktivitas)</h2>
<form method="get" class="filter-form">
    <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="🔍 Cari data...">

    <div class="date-inline">
        <label>🗓️ Tanggal Mulai:</label>
        <input type="date" name="tgl_mulai" value="<?= htmlspecialchars($tglMulai) ?>">
    </div>

    <div class="date-inline">
        <label>📅 Tanggal Akhir:</label>
        <input type="date" name="tgl_selesai" value="<?= htmlspecialchars($tglSelesai) ?>">
    </div>

    <button type="submit" class="filter-btn">🔍 Filter</button>
    <button type="button" class="reset-btn" onclick="window.location='riwayat_update.php'">🔄 Reset</button>
	
<?php if ($username === 'IT7'): ?>
    <button type="button" id="deleteLogsButton" class="delete-btn">🗑️ Hapus Semua Log</button>
<?php endif; ?>




</form>



<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Baleno / Batchno / Packingno</th>
                <th>Warehouse Lama</th>
                <th>Warehouse Baru</th>
                <th>User</th>
                <th>IP</th>
                <th>Tanggal</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($pagedLogs)): ?>
            <tr><td colspan="8" style="text-align:center;color:gray;">Tidak ada data log ditemukan.</td></tr>
        <?php else: ?>
            <?php $no = $startIndex + 1; foreach ($pagedLogs as $log): ?>
            <tr>
                <td data-label="No"><?= $no++ ?></td>
                <td data-label="Baleno / Batchno / Packingno"><?= htmlspecialchars($log['identifier'] ?? '-') ?></td>
                <td data-label="Warehouse Lama"><?= htmlspecialchars($log['updated_from']['wrhsname'] ?? '-') ?></td>
                <td data-label="Warehouse Baru"><?= htmlspecialchars($log['updated_to']['wrhsname'] ?? '-') ?></td>
                <td data-label="User"><?= htmlspecialchars($log['user_session'] ?? '-') ?></td>
                <td data-label="IP"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
                <td data-label="Tanggal"><?= htmlspecialchars($log['timestamp'] ?? '-') ?></td>
                <td data-label="Keterangan"><?= htmlspecialchars($log['description'] ?? '-') ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php
    $queryString = http_build_query(array_filter([
        'user' => $userFilter,
        'gudang' => $gudangFilter,
        'tgl_mulai' => $tglMulai,
        'tgl_selesai' => $tglSelesai,
        'search' => $searchQuery
    ]));
    ?>
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?<?= $queryString ?>&page=<?= $i ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
</div>
</div>
<style>

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
</style>
<script>
document.getElementById('deleteLogsButton').addEventListener('click', function() {
    // Ambil tanggal mulai dan tanggal selesai dari filter
    var tglMulai = document.querySelector('[name="tgl_mulai"]').value;
    var tglSelesai = document.querySelector('[name="tgl_selesai"]').value;

    // Cek apakah tanggal mulai dan akhir sudah dipilih
    if (!tglMulai || !tglSelesai) {
        // Jika tidak ada tanggal yang dipilih, tampilkan notifikasi untuk menghapus semua logs
        if (confirm('Jika Tidak Memilih Tanggal Mulai Dan Akhir Maka Semua Logs Akan Dihapus. Apa Anda Sudah Yakin?')) {
            // Kirim request ke delete_logs.php dengan data tanggal mulai dan selesai kosong
            var formData = new FormData();
            formData.append('tgl_mulai', '');  // Kosongkan nilai tanggal mulai
            formData.append('tgl_selesai', '');  // Kosongkan nilai tanggal selesai

            fetch('delete_logs.php', {
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
            // Kirim request ke delete_logs.php dengan data tanggal mulai dan selesai yang dipilih
            var formData = new FormData();
            formData.append('tgl_mulai', tglMulai);
            formData.append('tgl_selesai', tglSelesai);

            fetch('delete_logs.php', {
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


</script>
</body>
</html>
