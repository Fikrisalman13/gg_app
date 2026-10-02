<?php
// pages/fabric_knowledge/export_problem.php
session_start();
require_once __DIR__ . '/../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    die("Silakan login terlebih dahulu!");
}

// ====== Ambil filter dari GET ======
$kategori    = $_GET['kategori'] ?? '';
$tag         = $_GET['tag'] ?? '';
$status      = $_GET['status'] ?? '';
$nocp        = $_GET['nocp'] ?? '';
$tanggal_awal  = $_GET['tanggal_awal'] ?? '';
$tanggal_akhir = $_GET['tanggal_akhir'] ?? '';

// ====== Set headers untuk download Excel ======
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Data_Masalah_Lengkap_" . date("Ymd_His") . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// ====== Build Query ======
$whereConditions = [];
$params = [];

$baseQuery = "FROM dbo.fab_m_problem p 
              LEFT JOIN dbo.fab_m_kategori k ON p.id_kategori = k.id_kategori 
              LEFT JOIN dbo.fab_m_tag t ON p.id_tag = t.id_tag 
              LEFT JOIN dbo.fab_m_solution s ON p.id_problem = s.id_problem";

// ====== Filter kategori ======
if (!empty($kategori)) {
    $whereConditions[] = "p.id_kategori = ?";
    $params[] = $kategori;
}

// ====== Filter tag ======
if (!empty($tag)) {
    $whereConditions[] = "p.id_tag = ?";
    $params[] = $tag;
}

// ====== Filter status ======
if (!empty($status)) {
    $whereConditions[] = "COALESCE(s.status, 'Open') = ?";
    $params[] = $status;
}

// ====== Filter No CP ======
if (!empty($nocp)) {
    $whereConditions[] = "p.nocp LIKE ?";
    $params[] = "%{$nocp}%";
}

// ====== Filter tanggal ======
if (!empty($tanggal_awal) && !empty($tanggal_akhir)) {
    $whereConditions[] = "CAST(COALESCE(s.updated_at, p.updated_at, p.created_at) AS DATE) BETWEEN ? AND ?";
    $params[] = $tanggal_awal;
    $params[] = $tanggal_akhir;
} elseif (!empty($tanggal_awal)) {
    $whereConditions[] = "CAST(COALESCE(s.updated_at, p.updated_at, p.created_at) AS DATE) >= ?";
    $params[] = $tanggal_awal;
} elseif (!empty($tanggal_akhir)) {
    $whereConditions[] = "CAST(COALESCE(s.updated_at, p.updated_at, p.created_at) AS DATE) <= ?";
    $params[] = $tanggal_akhir;
}

// ====== Combine WHERE ======
$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// ====== Query data lengkap dengan analisa & solusi ======
$sqlData = "SELECT 
                p.id_problem,
                p.deskripsi,
                p.nocp,
                p.color,
                p.routing,
                p.status_qc,
                p.pdr,
                p.resep,
                p.analis,
                p.review,
                p.solusi,
                k.nama_kategori, 
                t.nama_tag, 
                p.created_by,
                p.created_at,
                p.updated_at,
                COALESCE(s.status, 'Open') AS status,
                COALESCE(s.updated_at, p.updated_at, p.created_at) AS last_updated,
                -- Data dari tabel solution
                s.analisa_akar_masalah,
                s.tindakan_perbaikan,
                s.tindakan_pencegahan,
                s.created_by as solusi_dibuat_oleh,
                s.created_at as solusi_dibuat_tanggal,
                s.updated_by as solusi_diperbarui_oleh,
                s.updated_at as solusi_diperbarui_tanggal,
                CASE WHEN s.id_problem IS NOT NULL THEN 'Ya' ELSE 'Tidak' END as ada_solusi
            $baseQuery
            $whereClause
            ORDER BY last_updated DESC";

$stmtData = sqlsrv_query($conn, $sqlData, $params);

if ($stmtData === false) {
    die("Error: " . print_r(sqlsrv_errors(), true));
}

// ====== Cetak tabel Excel ======
echo "<table border='1'>";
echo "<thead>
        <tr style='background-color:#2E86C1; color:white; font-weight:bold; text-align:center;'>
            <th colspan='22'>DATA MASALAH FABRIC - LENGKAP DENGAN ANALISA & SOLUSI</th>
        </tr>
        <tr style='background-color:#5DADE2; color:white; font-weight:bold; text-align:center;'>
            <th colspan='22'>Export Date: " . date('d-m-Y H:i:s') . "</th>
        </tr>
        <tr style='background-color:#d9d9d9; font-weight:bold; text-align:center;'>
            <th>No</th>
            <th>No CP</th>
            <th>Deskripsi Masalah</th>
            <th>Kategori</th>
            <th>Tag</th>
            <th>Status</th>
            <th>Kode Warna</th>
            <th>Routing</th>
            <th>Status QC</th>
            <th>PDR</th>
            <th>Resep</th>
            <th>Analis</th>
            <th>Review</th>
            <th>Solusi (Problem)</th>
            <th>Ada Solusi?</th>
            <th>Analisa Akar Masalah</th>
            <th>Tindakan Perbaikan</th>
            <th>Tindakan Pencegahan</th>
            <th>Dibuat Oleh</th>
            <th>Tanggal Dibuat</th>
            <th>Update Terakhir</th>
            <th>Solusi Dibuat Oleh</th>
        </tr>
      </thead>";
echo "<tbody>";

$no = 1;
while ($row = sqlsrv_fetch_array($stmtData, SQLSRV_FETCH_ASSOC)) {
    // Format tanggal
    $created_at = $row['created_at'] instanceof DateTime ? 
        $row['created_at']->format('d-m-Y H:i') : 
        date('d-m-Y H:i', strtotime($row['created_at']));
    
    $last_updated = $row['last_updated'] instanceof DateTime ? 
        $row['last_updated']->format('d-m-Y H:i') : 
        date('d-m-Y H:i', strtotime($row['last_updated']));
    
    $solusi_dibuat_tanggal = $row['solusi_dibuat_tanggal'] instanceof DateTime ? 
        $row['solusi_dibuat_tanggal']->format('d-m-Y H:i') : 
        ($row['solusi_dibuat_tanggal'] ? date('d-m-Y H:i', strtotime($row['solusi_dibuat_tanggal'])) : '-');
    
    echo "<tr>";
    echo "<td>" . $no++ . "</td>";
    echo "<td>" . htmlspecialchars($row['nocp'] ?? '-') . "</td>";
    echo "<td>" . htmlspecialchars($row['deskripsi'] ?? '-') . "</td>";
    echo "<td>" . htmlspecialchars($row['nama_kategori'] ?? '-') . "</td>";
    echo "<td>" . htmlspecialchars($row['nama_tag'] ?? '-') . "</td>";
    echo "<td>" . htmlspecialchars($row['status'] ?? 'Open') . "</td>";
    echo "<td>" . htmlspecialchars($row['color'] ?? '-') . "</td>";
    echo "<td>" . htmlspecialchars($row['routing'] ?? '-') . "</td>";
    echo "<td>" . htmlspecialchars($row['status_qc'] ?? '-') . "</td>";
    echo "<td>" . ($row['pdr'] ?: '-') . "</td>";
    echo "<td>" . htmlspecialchars($row['resep'] ?? '-') . "</td>";
    echo "<td>" . htmlspecialchars($row['analis'] ?? '-') . "</td>";
    echo "<td>" . htmlspecialchars($row['review'] ?? '-') . "</td>";
    echo "<td>" . htmlspecialchars($row['solusi'] ?? '-') . "</td>";
    echo "<td style='text-align:center;'>" . htmlspecialchars($row['ada_solusi'] ?? 'Tidak') . "</td>";
    echo "<td>" . htmlspecialchars($row['analisa_akar_masalah'] ?? '-') . "</td>";
    echo "<td>" . htmlspecialchars($row['tindakan_perbaikan'] ?? '-') . "</td>";
    echo "<td>" . htmlspecialchars($row['tindakan_pencegahan'] ?? '-') . "</td>";
    echo "<td>" . htmlspecialchars($row['created_by'] ?? '-') . "</td>";
    echo "<td>" . $created_at . "</td>";
    echo "<td>" . $last_updated . "</td>";
    echo "<td>" . htmlspecialchars($row['solusi_dibuat_oleh'] ?? '-') . "</td>";
    echo "</tr>";
}

echo "</tbody></table>";

sqlsrv_free_stmt($stmtData);
sqlsrv_close($conn);
?>