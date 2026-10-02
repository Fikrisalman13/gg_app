<?php
// simpan_analisa_warna.php
// Endpoint AJAX untuk menyimpan data analisa warna (beserta kelompok hasil edit manual) ke database SQL Server GG

session_start();
header('Content-Type: application/json; charset=utf-8');

// Include koneksi ke SQL Server GG
require_once '../../koneksi.php';

// Auth check
if (!isset($_SESSION['UserName'])) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Sesi login telah berakhir. Silakan login kembali.'
    ]);
    exit;
}

if (!$conn) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Koneksi ke database SQL Server GG gagal.'
    ]);
    exit;
}

// Ensure table analisa_warna_data exists in GG
$checkTableSql = "
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'analisa_warna_data')
BEGIN
    CREATE TABLE analisa_warna_data (
        id INT IDENTITY(1,1) PRIMARY KEY,
        nomor_produksi NVARCHAR(50) NOT NULL UNIQUE,
        nomor_wo NVARCHAR(50),
        nama_material NVARCHAR(255),
        kode_warna NVARCHAR(50),
        warna NVARCHAR(100),
        cust_color NVARCHAR(100),
        label_jual NVARCHAR(100),
        qty_normal DECIMAL(19,2) DEFAULT 0,
        qty_repro DECIMAL(19,2) DEFAULT 0,
        qty_produksi DECIMAL(19,2) DEFAULT 0,
        nama_routing NVARCHAR(100),
        status NVARCHAR(50),
        fail_desc NVARCHAR(500),
        kelompok NVARCHAR(50),
        tanggal_selesai DATE,
        jam_selesai NVARCHAR(20),
        rtgmsid NVARCHAR(50) DEFAULT '589',
        workcenterid NVARCHAR(10) DEFAULT '111',
        created_at DATETIME DEFAULT GETDATE(),
        created_by NVARCHAR(50),
        updated_at DATETIME DEFAULT GETDATE()
    );
    CREATE INDEX idx_analisa_warna_prdnmbr ON analisa_warna_data(nomor_produksi);
    CREATE INDEX idx_analisa_warna_tgl ON analisa_warna_data(tanggal_selesai);
    CREATE INDEX idx_analisa_warna_kelompok ON analisa_warna_data(kelompok);
END
";
@sqlsrv_query($conn, $checkTableSql);

// Read JSON payload
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data) || empty($data)) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Data yang dikirim kosong atau format tidak valid.'
    ]);
    exit;
}

$currentUser = $_SESSION['UserName'] ?? 'System';
$savedCount = 0;
$errorCount = 0;

// Mulai transaksi SQL Server
if (sqlsrv_begin_transaction($conn) === false) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Gagal memulai transaksi database: ' . print_r(sqlsrv_errors(), true)
    ]);
    exit;
}

$upsertSql = "
MERGE analisa_warna_data AS target
USING (SELECT ? AS nomor_produksi) AS source
ON (target.nomor_produksi = source.nomor_produksi)
WHEN MATCHED THEN
    UPDATE SET 
        nomor_wo = ?,
        nama_material = ?,
        kode_warna = ?,
        warna = ?,
        cust_color = ?,
        label_jual = ?,
        qty_normal = ?,
        qty_repro = ?,
        qty_produksi = ?,
        nama_routing = ?,
        status = ?,
        fail_desc = ?,
        kelompok = ?,
        tanggal_selesai = ?,
        jam_selesai = ?,
        rtgmsid = ?,
        workcenterid = ?,
        updated_at = GETDATE()
WHEN NOT MATCHED THEN
    INSERT (
        nomor_produksi, nomor_wo, nama_material, kode_warna, warna, cust_color, label_jual,
        qty_normal, qty_repro, qty_produksi, nama_routing, status, fail_desc, kelompok,
        tanggal_selesai, jam_selesai, rtgmsid, workcenterid, created_at, created_by, updated_at
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), ?, GETDATE());
";

$stmt = sqlsrv_prepare($conn, $upsertSql, [
    // Source key
    &$nomor_produksi_src,
    // Update params
    &$nomor_wo, &$nama_material, &$kode_warna, &$warna, &$cust_color, &$label_jual,
    &$qty_normal, &$qty_repro, &$qty_produksi, &$nama_routing, &$status, &$fail_desc,
    &$kelompok, &$tanggal_selesai, &$jam_selesai, &$rtgmsid, &$workcenterid,
    // Insert params
    &$nomor_produksi, &$nomor_wo, &$nama_material, &$kode_warna, &$warna, &$cust_color, &$label_jual,
    &$qty_normal, &$qty_repro, &$qty_produksi, &$nama_routing, &$status, &$fail_desc, &$kelompok,
    &$tanggal_selesai, &$jam_selesai, &$rtgmsid, &$workcenterid, &$currentUser
]);

if ($stmt === false) {
    sqlsrv_rollback($conn);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Gagal menyiapkan statement database: ' . print_r(sqlsrv_errors(), true)
    ]);
    exit;
}

foreach ($data as $item) {
    $nomor_produksi = trim($item['nomor_produksi'] ?? '');
    if (empty($nomor_produksi)) continue;

    $nomor_produksi_src = $nomor_produksi;
    $nomor_wo           = $item['nomor_wo'] ?? null;
    $nama_material      = $item['nama_material'] ?? null;
    $kode_warna         = $item['kode_warna'] ?? null;
    $warna              = $item['warna'] ?? null;
    $cust_color         = $item['cust_color'] ?? null;
    $label_jual         = $item['label_jual'] ?? null;
    $qty_normal         = (float)($item['qty_normal'] ?? 0);
    $qty_repro          = (float)($item['qty_repro'] ?? 0);
    $qty_produksi       = (float)($item['qty_produksi'] ?? 0);
    $nama_routing       = $item['nama_routing'] ?? null;
    $status             = $item['status'] ?? null;
    $fail_desc          = $item['fail_desc'] ?? null;
    $kelompok           = trim($item['kelompok'] ?? '');
    $tanggal_selesai    = !empty($item['tanggal_selesai']) ? trim($item['tanggal_selesai']) : null;
    if (!empty($tanggal_selesai) && strlen($tanggal_selesai) === 8 && ctype_digit($tanggal_selesai)) {
        $tanggal_selesai = substr($tanggal_selesai, 0, 4) . '-' . substr($tanggal_selesai, 4, 2) . '-' . substr($tanggal_selesai, 6, 2);
    }
    $jam_selesai        = $item['jam_selesai'] ?? null;
    $rtgmsid            = !empty($item['rtgmsid']) ? (string)$item['rtgmsid'] : '589';
    $workcenterid       = !empty($item['workcenterid']) ? (string)$item['workcenterid'] : '111';

    if (sqlsrv_execute($stmt)) {
        $savedCount++;
    } else {
        $errorCount++;
    }
}

sqlsrv_free_stmt($stmt);
sqlsrv_commit($conn);

echo json_encode([
    'status'      => 'success',
    'saved_count' => $savedCount,
    'error_count' => $errorCount,
    'message'     => "Berhasil menyimpan $savedCount data ke database SQL Server GG!"
]);
exit;
