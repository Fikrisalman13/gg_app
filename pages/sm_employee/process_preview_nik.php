<?php
session_start();
include '../../koneksi.php';

header('Content-Type: application/json');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['status' => 'error', 'message' => 'Silakan login terlebih dahulu!']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$kategori = $_POST['kategori'] ?? '';
$prefix_input = $_POST['prefix'] ?? '';
$tahun = $_POST['tahun'] ?? '';
$bulan = $_POST['bulan'] ?? '';
$sequence_start = (int)($_POST['sequence'] ?? 1);

if (empty($kategori)) {
    echo json_encode(['status' => 'error', 'message' => 'Kategori tidak boleh kosong.']);
    exit;
}

$prefix = $prefix_input . $tahun . $bulan;
$whereClause = "";

// Tentukan aturan filter data berdasarkan kategori
if ($kategori === 'magang') {
    $whereClause = "g.golongan LIKE '%Magang%'";
} elseif ($kategori === 'harian_lepas') {
    $whereClause = "g.golongan = 'Harian Lepas'";
} elseif ($kategori === 'reguler') {
    // Reguler = Selain Tetap, Outsourcing, Magang, HL, dan bukan Staff/Clerk area Surabaya
    $whereClause = "g.golongan NOT IN ('Karyawan Tetap', 'Outsourcing', 'Staff', 'Clerk', 'Harian Lepas') 
                    AND g.golongan NOT LIKE '%Magang%' 
                    AND (d.dept IS NULL OR d.dept NOT LIKE '%Surabaya%')";
} elseif ($kategori === 'clerk_staff') {
    // Clerk & Staff = Golongan murni Staff / Clerk
    $whereClause = "g.golongan IN ('Staff', 'Clerk') 
                    AND (d.dept IS NULL OR d.dept NOT LIKE '%Surabaya%')";
} else {
    echo json_encode(['status' => 'error', 'message' => 'Kategori tidak valid.']);
    exit;
}

// Ambil data karyawan yang sesuai
$sql = "SELECT 
            e.id_emp,
            e.nik,
            e.nama_lengkap,
            d.dept,
            b.bagian,
            g.golongan
        FROM dbo.m_emp e
        LEFT JOIN dbo.m_dept d ON e.id_dept = d.id_dept
        LEFT JOIN dbo.m_bag b ON e.id_bag = b.id_bag
        LEFT JOIN dbo.m_gol g ON e.id_gol = g.id_gol
        WHERE e.aktif = 1 AND ($whereClause)
        ORDER BY e.nama_lengkap ASC";

$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    echo json_encode(['status' => 'error', 'message' => 'Gagal mengambil data: ' . print_r(sqlsrv_errors(), true)]);
    exit;
}

$data = [];
$sequence = $sequence_start;

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $seqFormatted = str_pad($sequence, 3, '0', STR_PAD_LEFT);
    $newNik = $prefix . $seqFormatted;
    
    $data[] = [
        'id_emp' => $row['id_emp'],
        'nama_lengkap' => $row['nama_lengkap'],
        'dept' => $row['dept'] ?? '-',
        'bagian' => $row['bagian'] ?? '-',
        'golongan' => $row['golongan'] ?? '-',
        'nik_lama' => $row['nik'],
        'nik_baru' => $newNik
    ];
    
    $sequence++;
}

sqlsrv_free_stmt($stmt);

echo json_encode(['status' => 'success', 'data' => $data]);
?>
