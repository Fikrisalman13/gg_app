<?php
// pages/fabric_knowledge/export_summary_warna.php
session_start();
require_once __DIR__ . '/../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    die("Silakan login terlebih dahulu!");
}

// ====== Ambil filter dari GET ======
$kategori    = $_GET['kategori'] ?? '';
$tanggal_awal  = $_GET['tanggal_awal'] ?? '';
$tanggal_akhir = $_GET['tanggal_akhir'] ?? '';

// Validasi: harus ada kategori dan tanggal
if (empty($kategori)) {
    die("Error: Kategori harus dipilih!");
}

// Jika tanggal tidak diisi, gunakan bulan berjalan
if (empty($tanggal_awal) || empty($tanggal_akhir)) {
    $tanggal_awal = date('Y-m-01'); // Awal bulan ini
    $tanggal_akhir = date('Y-m-t'); // Akhir bulan ini
}

// ====== Ambil nama kategori ======
$sql_kategori = "SELECT nama_kategori FROM dbo.fab_m_kategori WHERE id_kategori = ?";
$stmt_kategori = sqlsrv_query($conn, $sql_kategori, [$kategori]);
$kategori_row = sqlsrv_fetch_array($stmt_kategori, SQLSRV_FETCH_ASSOC);
$nama_kategori = $kategori_row['nama_kategori'] ?? 'Unknown';

// ====== Set headers untuk download Excel ======
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Summary_" . str_replace(' ', '_', $nama_kategori) . "_" . date("Ymd_His") . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// ====== Query untuk data agregat per tanggal ======
// 1. Total masalah baru yang dibuat per tanggal
$sql_new = "SELECT CAST(created_at AS DATE) as tanggal, COUNT(*) as jumlah_baru
            FROM dbo.fab_m_problem
            WHERE id_kategori = ?
              AND CAST(created_at AS DATE) BETWEEN ? AND ?
            GROUP BY CAST(created_at AS DATE)
            ORDER BY tanggal";

$stmt_new = sqlsrv_query($conn, $sql_new, [$kategori, $tanggal_awal, $tanggal_akhir]);
if ($stmt_new === false) {
    die("Error query new problems: " . print_r(sqlsrv_errors(), true));
}

$data_new = [];
while ($row = sqlsrv_fetch_array($stmt_new, SQLSRV_FETCH_ASSOC)) {
    $date_key = $row['tanggal'] instanceof DateTime ? $row['tanggal']->format('Y-m-d') : $row['tanggal'];
    $data_new[$date_key] = $row['jumlah_baru'];
}

// 2. Total masalah yang solved per tanggal (berdasarkan updated_at di tabel solution)
$sql_solved = "SELECT CAST(s.updated_at AS DATE) as tanggal, COUNT(*) as jumlah_solved
               FROM dbo.fab_m_solution s
               INNER JOIN dbo.fab_m_problem p ON s.id_problem = p.id_problem
               WHERE p.id_kategori = ?
                 AND s.status = 'Solved'
                 AND CAST(s.updated_at AS DATE) BETWEEN ? AND ?
               GROUP BY CAST(s.updated_at AS DATE)
               ORDER BY tanggal";

$stmt_solved = sqlsrv_query($conn, $sql_solved, [$kategori, $tanggal_awal, $tanggal_akhir]);
if ($stmt_solved === false) {
    die("Error query solved problems: " . print_r(sqlsrv_errors(), true));
}

$data_solved = [];
while ($row = sqlsrv_fetch_array($stmt_solved, SQLSRV_FETCH_ASSOC)) {
    $date_key = $row['tanggal'] instanceof DateTime ? $row['tanggal']->format('Y-m-d') : $row['tanggal'];
    $data_solved[$date_key] = $row['jumlah_solved'];
}

// 3. Total akumulasi masalah sebelum tanggal_awal (untuk baseline)
$sql_baseline = "SELECT COUNT(*) as total_before
                 FROM dbo.fab_m_problem
                 WHERE id_kategori = ?
                   AND CAST(created_at AS DATE) < ?";

$stmt_baseline = sqlsrv_query($conn, $sql_baseline, [$kategori, $tanggal_awal]);
$row_baseline = sqlsrv_fetch_array($stmt_baseline, SQLSRV_FETCH_ASSOC);
$total_akumulasi = $row_baseline['total_before'] ?? 0;

// ====== Generate data per tanggal ======
$date_range = [];
$start = new DateTime($tanggal_awal);
$end = new DateTime($tanggal_akhir);

for ($date = clone $start; $date <= $end; $date->modify('+1 day')) {
    $date_str = $date->format('Y-m-d');
    
    // Tambahkan jumlah baru pada tanggal ini
    $jumlah_baru = $data_new[$date_str] ?? 0;
    $total_akumulasi += $jumlah_baru;
    
    // Jumlah solved pada tanggal ini
    $jumlah_solved = $data_solved[$date_str] ?? 0;
    
    $date_range[] = [
        'tanggal' => $date_str,
        'tanggal_display' => $date->format('d-m-Y'),
        'bulan' => $date->format('F Y'), // Nama bulan untuk grouping
        'hari' => $date->format('d'),
        'jumlah_baru' => $jumlah_baru,
        'total_akumulasi' => $total_akumulasi,
        'jumlah_solved' => $jumlah_solved
    ];
}

// ====== Group data by month dan hitung rowspan ======
$grouped_by_month = [];
foreach ($date_range as $row) {
    $month_key = $row['bulan'];
    if (!isset($grouped_by_month[$month_key])) {
        $grouped_by_month[$month_key] = [];
    }
    $grouped_by_month[$month_key][] = $row;
}

// ====== Cetak tabel Excel dengan cell merging ======
echo "<table border='1' cellpadding='5' cellspacing='0'>";

// Header utama - styling hanya di level cell untuk menghindari color bleeding
echo "<thead>
        <tr>
            <th colspan='5' style='background-color:#2E86C1; color:white; font-weight:bold; text-align:center;'>SUMMARY REPORT - " . strtoupper(htmlspecialchars($nama_kategori)) . "</th>
        </tr>
        <tr>
            <th colspan='5' style='background-color:#5DADE2; color:white; font-weight:bold; text-align:center;'>Periode: " . date('d-m-Y', strtotime($tanggal_awal)) . " s/d " . date('d-m-Y', strtotime($tanggal_akhir)) . "</th>
        </tr>
        <tr>
            <th colspan='5' style='background-color:#85C1E9; color:white; font-weight:bold; text-align:center;'>Export Date: " . date('d-m-Y H:i:s') . "</th>
        </tr>
        <tr>
            <th colspan='2' style='background-color:#d9d9d9; font-weight:bold; text-align:center;'>Tanggal</th>
            <th style='background-color:#d9d9d9; font-weight:bold; text-align:center;'>Total Masalah Warna</th>
            <th style='background-color:#d9d9d9; font-weight:bold; text-align:center;'>Tambahan Input Masalah Warna</th>
            <th style='background-color:#d9d9d9; font-weight:bold; text-align:center;'>Solved</th>
        </tr>
      </thead>";

echo "<tbody>";

// Loop through each month
foreach ($grouped_by_month as $month_name => $dates) {
    $rowspan = count($dates);
    $first_row = true;
    
    foreach ($dates as $row) {
        echo "<tr>";
        
        // Column A: Month name (with rowspan for first row only)
        if ($first_row) {
            echo "<td rowspan='{$rowspan}' style='text-align:center; vertical-align:middle; font-weight:bold; background-color:#f8f9fa;'>" 
                 . htmlspecialchars($month_name) . "</td>";
            $first_row = false;
        }
        
        // Column B: Date number
        echo "<td style='text-align:center;'>" . htmlspecialchars($row['hari']) . "</td>";
        
        // Column C: Total Akumulasi
        echo "<td style='text-align:center;'>" . htmlspecialchars($row['total_akumulasi']) . "</td>";
        
        // Column D: Jumlah Baru
        echo "<td style='text-align:center;'>" . htmlspecialchars($row['jumlah_baru']) . "</td>";
        
        // Column E: Jumlah Solved
        echo "<td style='text-align:center;'>" . htmlspecialchars($row['jumlah_solved']) . "</td>";
        
        echo "</tr>";
    }
}

echo "</tbody></table>";

sqlsrv_free_stmt($stmt_new);
sqlsrv_free_stmt($stmt_solved);
sqlsrv_free_stmt($stmt_baseline);
sqlsrv_free_stmt($stmt_kategori);
sqlsrv_close($conn);
?>
