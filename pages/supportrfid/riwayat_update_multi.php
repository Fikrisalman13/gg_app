<?php
// Start session to access the username, role, etc.
require 'auth_check.php';



//hak akses
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/includes/access_helper.php';

$username = $_SESSION['username'];
$groupId = getUserGroup($username);

// 🔐 Cek hak akses ke halaman (contoh: update_multi)
if (!canAccessMenu($groupId, 'riwayatmulti')) {
    echo "<div class='container'><h3 style='color:red;text-align:center;'>🚫 Anda tidak memiliki hak akses ke halaman ini.</h3></div>";
    exit;
}


// Log processing and filter logic
$logDir = __DIR__ . '/logs';
$logFiles = glob($logDir . '/update_data_*.json');
$logs = [];

// Get filter parameters from GET request
$search = $_GET['search'] ?? '';
$tglMulai = $_GET['tgl_mulai'] ?? '';
$tglSelesai = $_GET['tgl_selesai'] ?? '';

// Load logs from files
foreach ($logFiles as $file) {
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $log = json_decode($line, true);
        if ($log) {
            // Apply search filter
             if ($search && !(
                str_contains(strtolower($log['collection'] ?? ''), strtolower($search)) ||
                str_contains(strtolower($log['identifier'] ?? ''), strtolower($search)) ||
                str_contains(strtolower($log['timestamp'] ?? ''), strtolower($search)) ||
                str_contains(strtolower($log['user_session'] ?? ''), strtolower($search)) ||
                str_contains(strtolower($log['description'] ?? ''), strtolower($search))
            )) {
                continue;
            }
            // Apply date range filter
            $logTimestamp = strtotime($log['timestamp'] ?? '');
            if ($tglMulai && $logTimestamp < strtotime($tglMulai)) {
                continue;
            }
            if ($tglSelesai && $logTimestamp > strtotime($tglSelesai)) {
                continue;
            }

            // If the log passes all filters, add it to the list
            $logs[] = $log;
        }
    }
}

// Sort logs by timestamp (latest first)
usort($logs, fn($a, $b) => strtotime($b['timestamp']) <=> strtotime($a['timestamp']));

// Pagination logic
$logsPerPage = 50;
$totalLogs = count($logs);
$totalPages = ceil($totalLogs / $logsPerPage);
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start = ($page - 1) * $logsPerPage;
$logs = array_slice($logs, $start, $logsPerPage);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Perubahan Data</title>
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
        }

        .card-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease-in-out;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .card-body {
            font-size: 14px;
        }

        .card-body table {
            width: 100%;
            margin-top: 10px;
        }

        .card-body table td {
            padding: 5px 10px;
            border-bottom: 1px solid #ddd;
        }

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
        }

        .pagination a.active {
            background: #0d47a1;
        }

        .pagination a:hover {
            background: #1565c0;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
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

/* Adjust the title and content layout for mobile devices */
@media (max-width: 768px) {
    /* Increase the spacing for the content on mobile */
    .content {
        margin-top: 100px; /* Increase top margin to give space for the header */
    }

    /* Title adjustments */
    h2 {
        margin-top: 30px;  /* Ensure the title is spaced down properly */
        font-size: 25px; /* Adjust font size for mobile view */
        text-align: center;
        padding: 20px 0; /* Add padding to avoid it sticking to the edges */
    }

    /* Adjust the filter form */
    .filter-form {
        margin-bottom: 20px;
        padding: 15px;
    }

    /* Table adjustments */
    table {
        width: 100%;
        table-layout: fixed; /* Make sure the table fits well */
    }

    th, td {
        padding: 8px 10px;
        font-size: 14px;
    }

    /* Make sure pagination buttons are more compact on mobile */
    .pagination {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-top: 20px;
    }

    .pagination a {
        padding: 8px 12px;
        font-size: 14px;
        text-align: center;
    }

    /* Ensure content has some breathing space */
    .container {
        padding: 15px;
    }
}
/* === Card biru log update === */
.log-info-card {
  background: linear-gradient(180deg, #f7faff, #eef5ff);
  border: 1px solid #d0e3ff;
  border-radius: 12px;
  padding: 12px 16px;
  margin-top: 10px;
  box-shadow: 0 2px 6px rgba(21, 101, 192, 0.08);
  font-size: 14px;
  color: #333;
}

.log-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 4px 0;
  border-bottom: 1px dashed #d9e7ff;
}

.log-row:last-child {
  border-bottom: none;
}

.log-row strong {
  color: #1565c0;
  min-width: 130px;
  display: inline-block;
  font-weight: 600;
}

.log-row span {
  flex: 1;
  text-align: right;
  color: #333;
}

.log-row.full {
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
}

.data-box {
  width: 100%;
  background: #fff;
  border: 1px solid #e3f2fd;
  border-radius: 6px;
  padding: 6px 8px;
  color: #333;
  line-height: 1.4em;
  margin-top: 2px;
  overflow-x: auto;          /* jika tabel terlalu lebar, bisa digeser */
  word-wrap: break-word;     /* pecah kata panjang */
  word-break: break-word;    /* pecah string panjang */
  white-space: normal;       /* biar teks bisa turun baris */
  max-width: 100%;           /* jangan lebih lebar dari kartu */
  box-sizing: border-box;
}

.data-box table {
  width: 100%;
  border-collapse: collapse;
}

.data-box td, 
.data-box th {
  border: none;
  padding: 4px 6px;
  vertical-align: top;
  word-break: break-word;
}


    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<!-- Content Section -->
<div class="content">

    

    <h2>📜 Riwayat Perubahan Data</h2>
<!-- Filter Form -->
    <form method="get" class="filter-form">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="🔍 Cari data...">
        <div class="date-inline">
            <label>🗓️ Tanggal Mulai:</label>
            <input type="date" name="tgl_mulai" value="<?= htmlspecialchars($tglMulai) ?>">
        </div>
        <div class="date-inline">
            <label>📅 Tanggal Akhir:</label>
            <input type="date" name="tgl_selesai" value="<?= htmlspecialchars($tglSelesai) ?>">
        </div>
        <button type="submit">🔍 Filter</button>
        <button type="button" onclick="window.location='riwayat_update.php'">🔄 Reset</button>
		<?php if ($username === 'IT7'): ?>
    <button type="button" id="deleteLogsButton" class="delete-btn">🗑️ Hapus Semua Log</button>
<?php endif; ?>
    </form>
    <!-- Card Container -->
    <div class="card-container">
        <?php if (empty($logs)): ?>
            <div class="card" style="text-align:center; color:gray;">Tidak ada log ditemukan.</div>
        <?php else: ?>
            <?php $no = $start + 1; foreach ($logs as $log): ?>
                <div class="card">
                    <div class="card-header">
                        <?= $no++ ?> - <?= htmlspecialchars($log['collection'] ?? '-') ?>
                    </div>
                    <div class="card-body log-detail">
  <div class="log-info-card">
  <div class="log-row">
    <strong>📋 Metode Update:</strong>
    <span><?= htmlspecialchars($log['identifier'] ?? '-') ?></span>
  </div>
  <div class="log-row">
    <strong>🕒 Tanggal:</strong>
    <span><?= htmlspecialchars($log['timestamp'] ?? '-') ?></span>
  </div>
  <div class="log-row">
    <strong>👤 User:</strong>
    <span><?= htmlspecialchars($log['user_session'] ?? '-') ?></span>
  </div>

  <div class="log-row full">
    <strong>📄 Data Lama:</strong>
    <div class="data-box"><?= renderDataTable($log['updated_from']); ?></div>
  </div>

  <div class="log-row full">
    <strong>📦 Data Baru:</strong>
    <div class="data-box"><?= renderDataTable($log['updated_to']); ?></div>
  </div>

  <div class="log-row full">
    <strong>📝 Keterangan:</strong>
    <div class="data-box"><?= htmlspecialchars($log['description'] ?? '-') ?></div>
  </div>
</div>

</div>

                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=1&search=<?= urlencode($search) ?>&tgl_mulai=<?= urlencode($tglMulai) ?>&tgl_selesai=<?= urlencode($tglSelesai) ?>">First</a>
            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&tgl_mulai=<?= urlencode($tglMulai) ?>&tgl_selesai=<?= urlencode($tglSelesai) ?>">Prev</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&tgl_mulai=<?= urlencode($tglMulai) ?>&tgl_selesai=<?= urlencode($tglSelesai) ?>" <?= $i === $page ? 'class="active"' : '' ?>><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&tgl_mulai=<?= urlencode($tglMulai) ?>&tgl_selesai=<?= urlencode($tglSelesai) ?>">Next</a>
            <a href="?page=<?= $totalPages ?>&search=<?= urlencode($search) ?>&tgl_mulai=<?= urlencode($tglMulai) ?>&tgl_selesai=<?= urlencode($tglSelesai) ?>">Last</a>
        <?php endif; ?>
    </div>

</div>

<!-- Function to render old and new data -->
<?php
function renderDataTable($data) {
    // Menambahkan 'balehdid' ke dalam daftar fields
    $fields = ['batchno', 'baleno', 'packingno', 'transferinitem', 'transferoutitem', 'transferinbale', 'transferoutbale', 'status', 'balehdid']; 
    
    $output = '<table>';
    
    // Iterasi untuk setiap field dalam array fields
    foreach ($fields as $field) {
        $output .= '<tr><td><strong>' . ucfirst($field) . '</strong></td><td>';
        
        // Menangani 'balehdid' agar ditampilkan dalam format yang benar
        if ($field == 'balehdid') {
            if (isset($data[$field])) {
                // Jika balehdid adalah objek BSON Long
                if ($data[$field] instanceof MongoDB\BSON\Long) {
                    $output .= htmlspecialchars($data[$field]->__toString()); // Menggunakan __toString() jika objek BSON Long
                } elseif (is_array($data[$field])) {
                    // Jika balehdid adalah array, ambil nilai pertama (misalnya, jika array hanya memiliki satu elemen)
                    $output .= htmlspecialchars(implode(", ", $data[$field])); 
                } elseif (is_object($data[$field])) {
                    // Jika balehdid adalah objek lain, ubah ke string dengan (object)->__toString()
                    $output .= htmlspecialchars($data[$field]->__toString());
                } else {
                    // Jika balehdid bukan array atau objek, cukup tampilkan nilai
                    $output .= htmlspecialchars($data[$field]);
                }
            } else {
                $output .= '-'; // Jika balehdid tidak ada
            }
        } else {
            // Untuk field lain, langsung tampilkan
            $output .= htmlspecialchars($data[$field] ?? '-');
        }
        
        $output .= '</td></tr>';
    }
    
    $output .= '</table>';
    
    return $output;
}
?>

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

            fetch('delete_data.php', {
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
